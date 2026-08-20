<?php

namespace App\Services;

use App\Core\Database;
use App\Core\RadiusUiClient;

/**
 * 订阅服务
 * 
 * 核心概念: 每次购买套餐生成一条 vpn_subscriptions 记录。
 * 同一个VPN账号可以有多条订阅, 每条订阅独立计时:
 *   - active:    已激活的订阅 (可以多条同时存在, 每条独立计时期)
 *   - in_use:    当前正在使用的订阅 (同一时间只有一条, 由用户选择)
 *   - expired:   已过期
 *   - cancelled: 已取消
 * 
 * 用户可以在多个已激活的订阅之间自由切换, VPN参数取当前 in_use 订阅的值。
 * 当 in_use 订阅到期后, 如果还有其他 active 订阅, 自动切换到下一个。
 */
class SubscriptionService
{
    private $db;
    private $radius;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->radius = new RadiusUiClient();
    }

    /**
     * 添加订阅 (购买套餐时调用)
     * 
     * 新规则: 每次购买都立即激活, 从当前时间开始计时, 独立到期。
     * 如果是第一个订阅, 自动设为 in_use。
     * 如果已有 in_use 订阅, 新订阅为 active 但不自动切换 in_use。
     * 
     * @param int $vpnAccountId VPN账户ID
     * @param int $userId 用户ID
     * @param int $orderId 关联订单ID
     * @param array $packageData 套餐数据
     * @return array 新创建的订阅
     */
    public function addSubscription($vpnAccountId, $userId, $orderId, $packageData)
    {
        // 检查是否已有 in_use 的订阅
        $currentInUse = $this->db->fetch(
            "SELECT * FROM vpn_subscriptions WHERE vpn_account_id = ? AND status = 'in_use'",
            [$vpnAccountId]
        );

        $now = time();
        $durationDays = $packageData['duration_days'];
        $startTime = date('Y-m-d H:i:s', $now);
        $expireTime = date('Y-m-d H:i:s', strtotime("+{$durationDays} days", $now));

        // 第一个订阅自动设为 in_use, 其余为 active
        $status = $currentInUse ? 'active' : 'in_use';
        $activatedAt = date('Y-m-d H:i:s', $now);

        $subId = $this->db->insert('vpn_subscriptions', [
            'vpn_account_id' => $vpnAccountId,
            'user_id' => $userId,
            'order_id' => $orderId,
            'package_id' => $packageData['package_id'],
            'pricing_id' => $packageData['pricing_id'] ?? null,
            'package_name' => $packageData['package_name'],
            'billing_cycle' => $packageData['billing_cycle'] ?? null,
            'duration_days' => $durationDays,
            'up_rate' => $packageData['up_rate'],
            'down_rate' => $packageData['down_rate'],
            'active_num' => $packageData['active_num'],
            'data_limit_gb' => $packageData['data_limit_gb'],
            'radius_profile' => $packageData['radius_profile'] ?? null,
            'data_used_bytes' => 0,
            'traffic_baseline_bytes' => $this->radius->getUserTotalTraffic($this->getAccountUsername($vpnAccountId)),
            'start_time' => $startTime,
            'expire_time' => $expireTime,
            'status' => $status,
            'activated_at' => $activatedAt,
        ]);

        // 如果是第一个订阅(in_use), 应用到VPN账户
        if ($status === 'in_use') {
            $this->applySubscriptionToAccount($vpnAccountId, $subId);
        }

        return [
            'id' => $subId,
            'status' => $status,
            'start_time' => $startTime,
            'expire_time' => $expireTime,
        ];
    }

    private function getAccountUsername($vpnAccountId)
    {
        return $this->db->fetchColumn("SELECT username FROM vpn_accounts WHERE id = ?", [$vpnAccountId]);
    }

    /**
     * 将订阅参数应用到VPN账户和NETORA-Radius
     * 用户切换订阅时调用
     * 
     * 关键: 切换前先把当前VPN账户的已用流量保存回旧订阅,
     * 然后用新订阅的流量数据覆盖VPN账户。
     */
    public function applySubscriptionToAccount($vpnAccountId, $subscriptionId)
    {
        $sub = $this->db->fetch("SELECT * FROM vpn_subscriptions WHERE id = ?", [$subscriptionId]);
        if (!$sub) {
            throw new \Exception('订阅不存在');
        }

        if ($sub['status'] !== 'active' && $sub['status'] !== 'in_use') {
            throw new \Exception('该订阅状态不可用: ' . $sub['status']);
        }

        if (strtotime($sub['expire_time']) <= time()) {
            throw new \Exception('该订阅已过期');
        }

        $account = $this->db->fetch("SELECT * FROM vpn_accounts WHERE id = ?", [$vpnAccountId]);
        if (!$account) {
            throw new \Exception('VPN账户不存在');
        }

        $transactionStarted = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $transactionStarted = true;
        }
        try {
            // 1. 保存当前VPN账户的已用流量回旧订阅(如果当前有in_use的订阅)
            $oldSub = $this->db->fetch(
                "SELECT * FROM vpn_subscriptions WHERE vpn_account_id = ? AND status = 'in_use'",
                [$vpnAccountId]
            );
            if ($oldSub && $oldSub['id'] != $subscriptionId) {
                // 把VPN账户当前的已用流量写回旧订阅
                $this->db->update('vpn_subscriptions', [
                    'status' => 'active',
                    'data_used_bytes' => $account['data_used_bytes'],
                ], 'id = ?', [$oldSub['id']]);
            }

            // 2. 将新选择的订阅设为 in_use
            $this->db->update('vpn_subscriptions', [
                'status' => 'in_use',
            ], 'id = ?', [$subscriptionId]);

            // 3. 更新NETORA-Radius中的用户参数
            if ($account['username']) {
                $subProfile = $sub['radius_profile'] ?? '';
                if ($subProfile === '') {
                    $subProfile = defined('RADIUS_PROFILE') ? RADIUS_PROFILE : '';
                }
                $this->radius->updateUser($account['username'], [
                    'status' => 'enabled',
                    'password' => $account['password'],
                    'profile' => $subProfile,
                ]);
            }

            // 更新本地VPN账户 (参数取自当前 in_use 的订阅)
            $this->db->update('vpn_accounts', [
                'package_id' => $sub['package_id'],
                'up_rate' => $sub['up_rate'],
                'down_rate' => $sub['down_rate'],
                'active_num' => $sub['active_num'],
                'data_limit_gb' => $sub['data_limit_gb'],
                'radius_profile' => $subProfile,
                'data_used_bytes' => $sub['data_used_bytes'],
                'expire_time' => $sub['expire_time'], 
                'status' => 'enabled',
            ], 'id = ?', [$vpnAccountId]);

            // 记录日志
            $this->db->insert('admin_logs', [
                'user_id' => $account['user_id'],
                'action' => 'subscription_switch',
                'target' => $account['username'],
                'detail' => "切换到订阅: {$sub['package_name']} (到期: {$sub['expire_time']})",
                'ip' => \App\Core\Auth::getClientIp(),
            ]);

            if ($transactionStarted) {
                $this->db->commit();
            }
        } catch (\Exception $e) {
            if ($transactionStarted) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 用户手动切换订阅
     * 
     * @param int $userId 用户ID
     * @param int $vpnAccountId VPN账户ID
     * @param int $subscriptionId 要切换到的订阅ID
     * @return array
     */
    public function switchSubscription($userId, $vpnAccountId, $subscriptionId)
    {
        $account = $this->db->fetch(
            "SELECT * FROM vpn_accounts WHERE id = ? AND user_id = ?",
            [$vpnAccountId, $userId]
        );
        if (!$account) {
            throw new \Exception('VPN账户不存在');
        }

        $sub = $this->db->fetch(
            "SELECT * FROM vpn_subscriptions WHERE id = ? AND vpn_account_id = ?",
            [$subscriptionId, $vpnAccountId]
        );
        if (!$sub) {
            throw new \Exception('订阅不存在');
        }

        if ($sub['status'] === 'in_use') {
            throw new \Exception('该订阅正在使用中');
        }

        if ($sub['status'] !== 'active') {
            throw new \Exception('该订阅状态不可用');
        }

        if (strtotime($sub['expire_time']) <= time()) {
            throw new \Exception('该订阅已过期');
        }

        $this->applySubscriptionToAccount($vpnAccountId, $subscriptionId);

        return [
            'subscription_id' => $subscriptionId,
            'package_name' => $sub['package_name'],
            'expire_time' => $sub['expire_time'],
        ];
    }

    /**
     * 检查并处理过期订阅
     * 当 in_use 订阅到期时, 自动切换到下一个可用的 active 订阅
     * 
     * @param int $vpnAccountId VPN账户ID
     * @return bool 是否发生了切换
     */
    public function checkAndSwitchSubscription($vpnAccountId)
    {
        $now = time();

        // 先把所有已过期的 active/in_use 订阅标记为 expired
        $this->db->update('vpn_subscriptions', [
            'status' => 'expired',
        ], 'vpn_account_id = ? AND status IN (?, ?) AND expire_time <= NOW()', 
           [$vpnAccountId, 'active', 'in_use']);

        // 检查当前是否有 in_use 订阅
        $inUseSub = $this->db->fetch(
            "SELECT * FROM vpn_subscriptions WHERE vpn_account_id = ? AND status = 'in_use'",
            [$vpnAccountId]
        );

        if ($inUseSub) {
            // 当前有 in_use 订阅且未过期, 无需切换
            return false;
        }

        // 没有 in_use 订阅, 查找下一个可用的 active 订阅
        $nextSub = $this->db->fetch(
            "SELECT * FROM vpn_subscriptions 
             WHERE vpn_account_id = ? AND status = 'active' AND expire_time > NOW()
             ORDER BY expire_time DESC LIMIT 1",
            [$vpnAccountId]
        );

        if ($nextSub) {
            // 激活该订阅为 in_use
            $this->db->update('vpn_subscriptions', [
                'status' => 'in_use',
            ], 'id = ?', [$nextSub['id']]);

            // 应用到VPN账户
            $this->applySubscriptionToAccount($vpnAccountId, $nextSub['id']);

            return true;
        }

        // 没有可用的订阅了, 禁用VPN账户
        $account = $this->db->fetch("SELECT * FROM vpn_accounts WHERE id = ?", [$vpnAccountId]);
        if ($account && $account['username']) {
            $this->radius->disableUser($account['username']);
        }
        $this->db->update('vpn_accounts', [
            'status' => 'disabled',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$vpnAccountId]);

        return true;
    }

    /**
     * 获取VPN账户的所有有效订阅 (active + in_use)
     */
    public function getAccountSubscriptions($vpnAccountId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM vpn_subscriptions 
             WHERE vpn_account_id = ? AND status IN ('active', 'in_use') AND expire_time > NOW()
             ORDER BY status ASC, created_at ASC",
            [$vpnAccountId]
        );
    }

    /**
     * 获取当前正在使用的订阅
     */
    public function getActiveSubscription($vpnAccountId)
    {
        return $this->db->fetch(
            "SELECT * FROM vpn_subscriptions WHERE vpn_account_id = ? AND status = 'in_use'",
            [$vpnAccountId]
        );
    }

    /**
     * 获取其他已激活的订阅 (非 in_use 的)
     */
    public function getOtherSubscriptions($vpnAccountId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM vpn_subscriptions 
             WHERE vpn_account_id = ? AND status = 'active' AND expire_time > NOW()
             ORDER BY created_at ASC",
            [$vpnAccountId]
        );
    }

    /**
     * 重置当前订阅周期的流量 (用户付费重置)
     */
    public function resetSubscriptionTraffic($vpnAccountId, $userId, $price)
    {
        $account = $this->db->fetch(
            "SELECT * FROM vpn_accounts WHERE id = ? AND user_id = ?",
            [$vpnAccountId, $userId]
        );
        if (!$account) {
            throw new \Exception('VPN账户不存在');
        }

        $activeSub = $this->getActiveSubscription($vpnAccountId);
        if (!$activeSub) {
            throw new \Exception('当前没有生效的订阅');
        }

        if ($activeSub['data_limit_gb'] <= 0) {
            throw new \Exception('当前套餐为不限流量, 无需重置');
        }

        if ($activeSub['data_used_bytes'] <= 0) {
            throw new \Exception('当前没有已用流量, 无需重置');
        }

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        if ($user['balance'] < $price) {
            throw new \Exception('余额不足, 请先充值');
        }

        $transactionStarted = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $transactionStarted = true;
        }
        try {
            $this->db->update('users', [
                'balance' => $user['balance'] - $price,
            ], 'id = ?', [$userId]);

            $orderNo = 'TRF' . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $this->db->insert('orders', [
                'order_no' => $orderNo,
                'user_id' => $userId,
                'package_id' => $activeSub['package_id'],
                'amount' => $price,
                'package_name' => '流量重置 - ' . $account['username'],
                'duration_days' => 0,
                'up_rate' => $activeSub['up_rate'],
                'down_rate' => $activeSub['down_rate'],
                'active_num' => $activeSub['active_num'],
                'data_limit_gb' => $activeSub['data_limit_gb'],
                'pay_method' => 'balance',
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
            ]);

            // 重置本期流量：把当前RADIUS累计值设为新的基线，而不是清空远端历史记录。
            $radiusTotal = $this->radius->getUserTotalTraffic($account['username']);
            $this->db->update('vpn_subscriptions', [
                'traffic_baseline_bytes' => $radiusTotal,
                'data_used_bytes' => 0,
            ], 'id = ?', [$activeSub['id']]);

            $this->db->update('vpn_accounts', [
                'data_used_bytes' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$vpnAccountId]);

            $this->db->insert('admin_logs', [
                'user_id' => $userId,
                'action' => 'traffic_reset_purchase',
                'target' => $account['username'],
                'detail' => "付费重置流量 ¥{$price} (订阅: {$activeSub['package_name']})",
                'ip' => \App\Core\Auth::getClientIp(),
            ]);

            if ($transactionStarted) {
                $this->db->commit();
            }

            return [
                'order_no' => $orderNo,
                'amount' => $price,
                'new_balance' => $user['balance'] - $price,
            ];
        } catch (\Exception $e) {
            if ($transactionStarted) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
