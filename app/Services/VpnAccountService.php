<?php

namespace App\Services;

use App\Core\Database;
use App\Core\RadiusUiClient;

/**
 * VPN账户服务
 * 
 * 管理VPN账户的生命周期:
 * - 创建账户(在NETORA-Radius中创建用户, 账号从1000开始递增)
 * - 续费账户(延长到期时间)
 * - 套餐变更(更新速率/并发数)
 * - 禁用/启用账户
 * - 流量同步
 */
class VpnAccountService
{
    private $db;
    private $radius;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->radius = new RadiusUiClient();
    }

    /**
     * 生成下一个VPN主账号(数字, 从1000开始递增)
     * 用于用户第一次购买套餐时创建的第一个账号
     */
    public function generateNextUsername()
    {
        $nextNumber = (int) $this->db->fetchColumn(
            "SELECT value FROM settings WHERE key_name = 'next_account_number'"
        );

        if ($nextNumber < ACCOUNT_START_NUMBER) {
            $nextNumber = ACCOUNT_START_NUMBER;
        }

        $username = (string) $nextNumber;

        // 确保账号不重复
        while ($this->db->fetch("SELECT id FROM vpn_accounts WHERE username = ?", [$username])) {
            $nextNumber++;
            $username = (string) $nextNumber;
        }

        // 更新计数器
        $this->db->query(
            "UPDATE settings SET value = ? WHERE key_name = 'next_account_number'",
            [$nextNumber + 1]
        );

        return $username;
    }

    /**
     * 为已有用户生成子账号用户名
     * 方案: 用户第一个账号是纯数字(如1001), 后续子账号追加字母后缀(1001a, 1001b, 1001c...)
     * 
     * @param int $userId 用户ID
     * @return string 子账号用户名
     */
    public function generateSubAccountUsername($userId)
    {
        // 获取该用户的所有VPN账号
        $accounts = $this->db->fetchAll(
            "SELECT username FROM vpn_accounts WHERE user_id = ? ORDER BY id ASC",
            [$userId]
        );

        if (empty($accounts)) {
            // 没有账号, 生成主账号
            return $this->generateNextUsername();
        }

        // 第一个账号是主账号(纯数字), 后续追加字母后缀
        $baseUsername = $accounts[0]['username'];

        // 主账号固定使用数字部分作为子账号基准，例如1000a、1000b。
        $prefix = \App\Core\View::getSetting('account_prefix', '');
        if ($prefix && strpos($baseUsername, $prefix) === 0) {
            $baseUsername = substr($baseUsername, strlen($prefix));
        }
        $baseUsername = preg_replace('/[^0-9].*$/', '', $baseUsername);

        // 已有子账号的字母后缀
        $usedSuffixes = [];
        foreach ($accounts as $acc) {
            $uname = $acc['username'];
            if ($prefix && strpos($uname, $prefix) === 0) {
                $uname = substr($uname, strlen($prefix));
            }
            // 如果是纯数字, 跳过(这是主账号)
            if (ctype_digit($uname)) {
                continue;
            }
            // 提取字母后缀 (如 1001a → a)
            $suffix = preg_replace('/^\d+/', '', $uname);
            if ($suffix) {
                $usedSuffixes[] = strtolower($suffix);
            }
        }

        // 生成下一个字母后缀: a, b, c, ... z, aa, ab, ...
        $suffixes = range('a', 'z');
        $chosenSuffix = null;
        foreach ($suffixes as $letter) {
            if (!in_array($letter, $usedSuffixes)) {
                $chosenSuffix = $letter;
                break;
            }
        }

        // 如果a-z用完, 用aa, ab, ac...
        if ($chosenSuffix === null) {
            foreach ($suffixes as $first) {
                foreach ($suffixes as $second) {
                    $combo = $first . $second;
                    if (!in_array($combo, $usedSuffixes)) {
                        $chosenSuffix = $combo;
                        break 2;
                    }
                }
            }
        }

        return $baseUsername . $chosenSuffix;
    }

    /**
     * 生成随机密码
     */
    public function generatePassword($length = 12)
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * 创建VPN账户 (支持一个用户多个子账号)
     * 
     * @param int $userId 用户ID
     * @param int $packageId 套餐ID
     * @param int|null $orderId 关联订单ID
     * @param int|null $durationDays 有效天数
     * @param array|null $pricingInfo 定价信息 (billing_cycle, pricing_id)
     * @param string|null $remark 备注 (如: 主账号/给朋友用)
     * @return array
     */
    public function createAccount($userId, $packageId, $orderId = null, $durationDays = null, $pricingInfo = null, $remark = null)
    {
        $package = $this->db->fetch("SELECT * FROM packages WHERE id = ? AND status = 1", [$packageId]);
        if (!$package) {
            throw new \Exception('套餐不存在或已下架');
        }

        // 检查用户已有多少个VPN账号
        $existingAccounts = $this->db->fetchAll(
            "SELECT id, username FROM vpn_accounts WHERE user_id = ? ORDER BY id ASC",
            [$userId]
        );

        // 如果没有账号, 生成主账号(纯数字); 否则生成子账号(带字母后缀)
        if (empty($existingAccounts)) {
            $username = $this->generateNextUsername();
            $remark = $remark ?: '主账号';
        } else {
            $username = $this->generateSubAccountUsername($userId);
            $remark = $remark ?: '子账号' . chr(96 + count($existingAccounts));
        }

        // 优先使用传入的天数, 否则从定价表获取
        if ($durationDays === null) {
            $pricing = $this->db->fetch(
                "SELECT * FROM package_pricing WHERE package_id = ? AND status = 1 ORDER BY sort_order ASC LIMIT 1",
                [$packageId]
            );
            $durationDays = $pricing ? $pricing['duration_days'] : 30;
        }

        $cycleName = $pricingInfo && isset($pricingInfo['billing_cycle']) 
            ? ' - ' . $this->getCycleName($pricingInfo['billing_cycle']) 
            : '';
        $packageName = $package['name'] . $cycleName;

        $password = $this->generatePassword();
        $expireTime = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));

        // 远端用户创建成功后，本地写入失败必须删除远端用户，避免产生孤立账号。
        $packageProfile = trim((string) ($package['radius_profile'] ?? ''));
        if ($packageProfile === '') {
            $packageProfile = defined('RADIUS_PROFILE') ? RADIUS_PROFILE : '';
        }
        if ($packageProfile === '') {
            throw new \Exception('套餐未绑定 NETORA-Radius Profile，且未配置全局 RADIUS_PROFILE');
        }
        $radiusUser = $this->radius->createUser($username, $password, $packageProfile, [
            'status' => 'enabled',
        ]);

        // 下单流程可能已持有数据库事务；避免在同一个 PDO 连接上重复开启事务。
        $transactionStarted = false;
        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $transactionStarted = true;
            }

            $vpnAccountId = $this->db->insert('vpn_accounts', [
                'user_id' => $userId,
                'username' => $username,
                'password' => $password,
                'radius_user_id' => $radiusUser['id'] ?? $username,
                'package_id' => $packageId,
                'up_rate' => $package['up_rate'],
                'down_rate' => $package['down_rate'],
                'active_num' => $package['active_num'],
                'data_limit_gb' => $package['data_limit'],
                'data_used_bytes' => 0,
                'radius_profile' => $packageProfile,
                'expire_time' => $expireTime,
                'status' => 'enabled',
                'remark' => $remark,
            ]);

            $subService = new \App\Services\SubscriptionService();
            $subService->addSubscription($vpnAccountId, $userId, $orderId, [
                'package_id' => $packageId,
                'pricing_id' => $pricingInfo['pricing_id'] ?? null,
                'package_name' => $packageName,
                'billing_cycle' => $pricingInfo['billing_cycle'] ?? null,
                'duration_days' => $durationDays,
                'up_rate' => $package['up_rate'],
                'down_rate' => $package['down_rate'],
                'active_num' => $package['active_num'],
                'data_limit_gb' => $package['data_limit'],
                'radius_profile' => $packageProfile,
            ]);

            if ($transactionStarted) {
                $this->db->commit();
                $transactionStarted = false;
            }
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollBack();
            }
            try {
                $this->radius->deleteUser($username);
            } catch (\Throwable $cleanupError) {
                error_log('RADIUS孤立用户清理失败 [' . $username . ']: ' . $cleanupError->getMessage());
            }
            throw $e;
        }

        return [
            'id' => $vpnAccountId,
            'username' => $username,
            'password' => $password,
            'expire_time' => $expireTime,
            'package_name' => $packageName,
        ];
    }

    /**
     * 续费VPN账户 — 通过订阅队列
     * 
     * 不再直接覆盖套餐参数, 而是创建一条新的订阅记录。
     * 如果当前订阅未到期, 新订阅排队等待; 如果已到期, 新订阅立即生效。
     * 
     * @param int $vpnAccountId VPN账户ID
     * @param int $packageId 套餐ID
     * @param int|null $orderId 关联订单ID
     * @param int|null $durationDays 有效天数
     * @param array|null $pricingInfo 定价信息 (billing_cycle, pricing_id)
     * @return array
     */
    public function renewAccount($vpnAccountId, $packageId, $orderId = null, $durationDays = null, $pricingInfo = null)
    {
        $account = $this->db->fetch("SELECT * FROM vpn_accounts WHERE id = ?", [$vpnAccountId]);
        if (!$account) {
            throw new \Exception('VPN账户不存在');
        }

        $package = $this->db->fetch("SELECT * FROM packages WHERE id = ? AND status = 1", [$packageId]);
        if (!$package) {
            throw new \Exception('套餐不存在或已下架');
        }

        if ($durationDays === null) {
            $pricing = $this->db->fetch(
                "SELECT * FROM package_pricing WHERE package_id = ? AND status = 1 ORDER BY sort_order ASC LIMIT 1",
                [$packageId]
            );
            $durationDays = $pricing ? $pricing['duration_days'] : 30;
        }

        $packageData = [
            'package_id' => $packageId,
            'pricing_id' => $pricingInfo['pricing_id'] ?? null,
            'package_name' => $package['name'] . ($pricingInfo && isset($pricingInfo['billing_cycle']) 
                ? ' - ' . ($this->getCycleName($pricingInfo['billing_cycle'])) 
                : ''),
            'billing_cycle' => $pricingInfo['billing_cycle'] ?? null,
            'duration_days' => $durationDays,
            'up_rate' => $package['up_rate'],
            'down_rate' => $package['down_rate'],
            'active_num' => $package['active_num'],
            'data_limit_gb' => $package['data_limit'],
        ];

        $subService = new \App\Services\SubscriptionService();
        $subResult = $subService->addSubscription($vpnAccountId, $account['user_id'], $orderId, $packageData);

        // 先尝试切换订阅(如果当前订阅已过期)
        $subService->checkAndSwitchSubscription($vpnAccountId);

        return [
            'expire_time' => $subResult['expire_time'],
            'package_name' => $packageData['package_name'],
            'subscription_status' => $subResult['status'],
        ];
    }

    private function getCycleName($cycle)
    {
        $map = ['monthly' => '月付', 'quarterly' => '季付', 'yearly' => '年付', 'weekly' => '周付', 'biannually' => '半年付'];
        return $map[$cycle] ?? $cycle;
    }

    /**
     * 禁用VPN账户
     */
    public function disableAccount($vpnAccountId)
    {
        $account = $this->db->fetch("SELECT * FROM vpn_accounts WHERE id = ?", [$vpnAccountId]);
        if (!$account) {
            throw new \Exception('VPN账户不存在');
        }

        if ($account['username']) {
            $this->radius->disableUser($account['username']);
        }

        $this->db->update('vpn_accounts', [
            'status' => 'disabled',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$vpnAccountId]);

        return true;
    }

    /**
     * 启用VPN账户
     */
    public function enableAccount($vpnAccountId)
    {
        $account = $this->db->fetch("SELECT * FROM vpn_accounts WHERE id = ?", [$vpnAccountId]);
        if (!$account) {
            throw new \Exception('VPN账户不存在');
        }

        if (strtotime($account['expire_time']) < time()) {
            throw new \Exception('账户已过期, 请先续费');
        }

        if ($account['username']) {
            $profile = trim((string) ($account['radius_profile'] ?? ''));
            if ($profile === '') {
                $activeSub = $this->db->fetch(
                    "SELECT radius_profile FROM vpn_subscriptions WHERE vpn_account_id = ? AND status = 'in_use'",
                    [$vpnAccountId]
                );
                $profile = trim((string) ($activeSub['radius_profile'] ?? ''));
            }
            $this->radius->updateUser($account['username'], [
                'password' => $account['password'],
                'profile' => $profile,
                'status' => 'enabled',
            ]);
        }

        $this->db->update('vpn_accounts', [
            'status' => 'enabled',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$vpnAccountId]);

        return true;
    }

    /**
     * 重置VPN密码 (随机生成)
     */
    public function resetPassword($vpnAccountId)
    {
        return $this->changePassword($vpnAccountId, $this->generatePassword());
    }

    /**
     * 修改VPN密码 (用户自定义)
     */
    public function changePassword($vpnAccountId, $newPassword)
    {
        if (strlen($newPassword) < 3) {
            throw new \Exception('密码长度至少3位');
        }
        if (strlen($newPassword) > 128) {
            throw new \Exception('密码长度不能超过128位');
        }

        $account = $this->db->fetch("SELECT * FROM vpn_accounts WHERE id = ?", [$vpnAccountId]);
        if (!$account) {
            throw new \Exception('VPN账户不存在');
        }

        if ($account['username']) {
            $this->radius->updateUser($account['username'], [
                'password' => $newPassword,
                'profile' => $account['radius_profile'] ?? null,
            ]);
        }

        $this->db->update('vpn_accounts', [
            'password' => $newPassword,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$vpnAccountId]);

        return $newPassword;
    }

    /**
     * 管理员修改VPN账户到期时间 (同步到ToughRadius)
     * 
     * @param int $vpnAccountId VPN账户ID
     * @param string $newExpireTime 新到期时间 (Y-m-d H:i:s 格式)
     * @return array
     */
    public function adminUpdateExpireTime($vpnAccountId, $newExpireTime)
    {
        $account = $this->db->fetch("SELECT * FROM vpn_accounts WHERE id = ?", [$vpnAccountId]);
        if (!$account) {
            throw new \Exception('VPN账户不存在');
        }

        $oldExpireTime = $account['expire_time'];
        $newTimestamp = strtotime($newExpireTime);
        if (!$newTimestamp) {
            throw new \Exception('到期时间格式无效');
        }

        // 如果新到期时间在未来, 且账户之前是因为过期被禁用的, 重新启用
        $newStatus = $account['status'];
        if ($newTimestamp > time() && in_array($account['status'], ['disabled', 'traffic_exceeded'])) {
            $newStatus = 'enabled';
        }

        // 如果新到期时间已过去, 禁用账户
        if ($newTimestamp <= time()) {
            $newStatus = 'disabled';
        }

        // 更新ToughRadius中的用户到期时间
        if ($account['username']) {
            $this->radius->updateUser($account['username'], [
                'password' => $account['password'],
                'profile' => $account['radius_profile'] ?? null,
                'status' => ($newStatus === 'enabled') ? 'enabled' : 'disabled',
            ]);
        }

        // 更新本地VPN账户
        $this->db->update('vpn_accounts', [
            'expire_time' => $newExpireTime,
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$vpnAccountId]);

        // 同步更新当前 in_use 订阅的到期时间
        $this->db->update('vpn_subscriptions', [
            'expire_time' => $newExpireTime,
        ], 'vpn_account_id = ? AND status = ?', [$vpnAccountId, 'in_use']);

        // 记录日志
        $this->db->insert('admin_logs', [
            'user_id' => $account['user_id'],
            'action' => 'admin_update_expire',
            'target' => $account['username'],
            'detail' => "到期时间修改: {$oldExpireTime} → {$newExpireTime} (状态: {$newStatus})",
            'ip' => \App\Core\Auth::getClientIp(),
        ]);

        return [
            'old_expire_time' => $oldExpireTime,
            'new_expire_time' => $newExpireTime,
            'status' => $newStatus,
        ];
    }

    /**
     * 同步账户流量和在线状态
     * 
     * 包含以下检查:
     * 1. 过期检查 → 到期自动禁用
     * 2. 流量同步 → 从ToughRadius获取实时流量
     * 3. 流量超限检查 → 已用流量 >= 限制流量时自动禁用
     */
    public function syncAccountStatus($vpnAccountId)
    {
        $account = $this->db->fetch("SELECT * FROM vpn_accounts WHERE id = ?", [$vpnAccountId]);
        if (!$account) {
            throw new \Exception('VPN账户不存在');
        }

        $updated = false;

        // 检查是否过期
        if (strtotime($account['expire_time']) < time() && $account['status'] === 'enabled') {
            if ($account['radius_user_id']) {
                $this->radius->disableUser($account['radius_user_id']);
            }
            $this->db->update('vpn_accounts', [
                'status' => 'disabled',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$vpnAccountId]);
            $account['status'] = 'disabled';
            $updated = true;
        }

        // 从RADIUS获取在线会话和流量
        try {
            // accounting 查询结果与在线会话分别累计，避免同一在线会话重复计数。
            // 1. 先拉在线会话(获取当前在线状态和实时流量)
            $sessions = $this->radius->getUserSessions($account['username']);
            $totalInput = 0;
            $totalOutput = 0;
            $lastOnline = null;

            if (!empty($sessions)) {
                foreach ($sessions as $session) {
                    // 兼容多种字段名
                    $input = 0;
                    foreach (['acct_input_total', 'input_octets', 'acct_input_octets', 'input_total', 'rx_bytes'] as $f) {
                        if (isset($session[$f])) { $input = (int)$session[$f]; break; }
                    }
                    $output = 0;
                    foreach (['acct_output_total', 'output_octets', 'acct_output_octets', 'output_total', 'tx_bytes'] as $f) {
                        if (isset($session[$f])) { $output = (int)$session[$f]; break; }
                    }
                    $totalInput += $input;
                    $totalOutput += $output;
                    
                    $timeStr = $session['acct_start_time'] ?? $session['start_time'] ?? null;
                    if ($timeStr && strtotime($timeStr) > strtotime($lastOnline ?? '2000-01-01')) {
                        $lastOnline = $timeStr;
                    }
                }
            }

            // 2. 同时拉历史计费记录(获取已断开会话的累计流量)
            $hasAccounting = false;
            try {
                $accountingRecords = $this->radius->getUserAccountingRecords($account['username']);
                if (!empty($accountingRecords)) {
                    $hasAccounting = true;
                    foreach ($accountingRecords as $record) {
                        // 累加所有历史记录的流量
                        // 兼容多种字段名
                        $input = 0;
                        foreach (['acctinputoctets', 'acct_input_octets', 'input_octets', 'acct_input_total', 'input_total', 'rx_bytes'] as $f) {
                            if (isset($record[$f])) { $input = (int)$record[$f]; break; }
                        }
                        $output = 0;
                        foreach (['acctoutputoctets', 'acct_output_octets', 'output_octets', 'acct_output_total', 'output_total', 'tx_bytes'] as $f) {
                            if (isset($record[$f])) { $output = (int)$record[$f]; break; }
                        }
                        $totalInput += $input;
                        $totalOutput += $output;
                    }
                }
            } catch (\Exception $e2) {
                // accounting拉取失败不影响在线会话流量
            }

            $radiusTotalBytes = $totalInput + $totalOutput;
            $currentSubscription = $this->db->fetch(
                "SELECT id, traffic_baseline_bytes FROM vpn_subscriptions WHERE vpn_account_id = ? AND status = 'in_use'",
                [$vpnAccountId]
            );
            $baselineBytes = $currentSubscription ? (int) $currentSubscription['traffic_baseline_bytes'] : 0;
            $totalBytes = max(0, $radiusTotalBytes - $baselineBytes);

            // 以订阅激活时的RADIUS累计值为基线，避免历史流量重新计入本期。
            if ($totalBytes > 0 || $lastOnline !== null || $currentSubscription) {
                $this->db->update('vpn_accounts', [
                    'data_used_bytes' => $totalBytes,
                    'last_online_at' => $lastOnline,
                ], 'id = ?', [$vpnAccountId]);

                if ($currentSubscription) {
                    $this->db->update('vpn_subscriptions', [
                        'data_used_bytes' => $totalBytes,
                    ], 'id = ?', [$currentSubscription['id']]);
                }

                $account['data_used_bytes'] = $totalBytes;
                $account['last_online_at'] = $lastOnline;
                $updated = true;
            }

            // 记录流量日志(方便排查)
            $this->db->insert('traffic_logs', [
                'vpn_account_id' => $vpnAccountId,
                'username' => $account['username'],
                'input_bytes' => $totalInput,
                'output_bytes' => $totalOutput,
                'start_time' => $lastOnline,
            ]);
        } catch (\Exception $e) {
            // 流量同步失败不阻断操作
        }

        // 检查流量是否超限 (有限流量套餐且当前启用状态)
        if ($account['status'] === 'enabled' && $account['data_limit_gb'] > 0) {
            $limitBytes = $account['data_limit_gb'] * 1024 * 1024 * 1024;
            if ($account['data_used_bytes'] >= $limitBytes) {
                // 流量超限, 禁用Radius用户(断开当前连接 + 拒绝下次连接)
                if ($account['radius_user_id']) {
                    $this->radius->disableUser($account['radius_user_id']);
                }
                $this->db->update('vpn_accounts', [
                    'status' => 'traffic_exceeded',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$vpnAccountId]);
                $account['status'] = 'traffic_exceeded';
                $updated = true;
            }
        }

        // 如果流量重置后恢复, 重新启用
        if ($account['status'] === 'traffic_exceeded' && $account['data_limit_gb'] > 0) {
            $limitBytes = $account['data_limit_gb'] * 1024 * 1024 * 1024;
            if ($account['data_used_bytes'] < $limitBytes && strtotime($account['expire_time']) > time()) {
                if ($account['radius_user_id']) {
                    $this->radius->enableUser($account['radius_user_id']);
                }
                $this->db->update('vpn_accounts', [
                    'status' => 'enabled',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$vpnAccountId]);
                $account['status'] = 'enabled';
                $updated = true;
            }
        }

        return $account;
    }

    /**
     * 检查并处理过期账户 + 流量超限账户
     */
    public function checkExpiredAccounts()
    {
        $count = 0;

        // 1. 处理过期账户
        $expired = $this->db->fetchAll(
            "SELECT id, radius_user_id, username FROM vpn_accounts 
             WHERE status = 'enabled' AND expire_time < NOW()"
        );

        foreach ($expired as $account) {
            try {
                if ($account['radius_user_id']) {
                    $this->radius->disableUser($account['radius_user_id']);
                }
                $this->db->update('vpn_accounts', [
                    'status' => 'disabled',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$account['id']]);
                $count++;
            } catch (\Exception $e) {
                // 记录错误但继续处理
            }
        }

        // 2. 处理流量超限账户 (有限流量套餐且已用流量达到上限)
        $overLimit = $this->db->fetchAll(
            "SELECT id, radius_user_id, username, data_limit_gb, data_used_bytes 
             FROM vpn_accounts 
             WHERE status = 'enabled' 
               AND data_limit_gb > 0 
               AND data_used_bytes >= data_limit_gb * 1024 * 1024 * 1024"
        );

        foreach ($overLimit as $account) {
            try {
                if ($account['radius_user_id']) {
                    $this->radius->disableUser($account['radius_user_id']);
                }
                $this->db->update('vpn_accounts', [
                    'status' => 'traffic_exceeded',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$account['id']]);
                $count++;
            } catch (\Exception $e) {
                // 记录错误但继续处理
            }
        }

        return $count;
    }

    /**
     * 重置流量 (将已用流量清零)
     * 
     * @param int $vpnAccountId VPN账户ID
     * @return bool
     */
    public function resetTraffic($vpnAccountId)
    {
        $account = $this->db->fetch("SELECT * FROM vpn_accounts WHERE id = ?", [$vpnAccountId]);
        if (!$account) {
            throw new \Exception('VPN账户不存在');
        }

        // 更新本地数据库: 清零已用流量
        $this->db->update('vpn_accounts', [
            'data_used_bytes' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$vpnAccountId]);

        // 记录日志
        $this->db->insert('admin_logs', [
            'user_id' => $account['user_id'],
            'action' => 'traffic_reset',
            'target' => $account['username'],
            'detail' => '流量已重置 (清零)',
            'ip' => \App\Core\Auth::getClientIp(),
        ]);

        return true;
    }

    /**
     * 购买流量重置 (用户付费重置流量)
     * 
     * @param int $userId 用户ID
     * @param int $vpnAccountId VPN账户ID
     * @param float $price 重置价格
     * @return array
     */
    public function purchaseTrafficReset($userId, $vpnAccountId, $price)
    {
        $subService = new \App\Services\SubscriptionService();
        return $subService->resetSubscriptionTraffic($vpnAccountId, $userId, $price);
    }

    /**
     * 获取流量重置价格 (从系统设置读取)
     */
    public function getTrafficResetPrice()
    {
        return self::getTrafficResetPriceStatic();
    }

    public static function getTrafficResetPriceStatic()
    {
        $db = Database::getInstance();
        $price = $db->fetchColumn("SELECT value FROM settings WHERE key_name = 'traffic_reset_price'");
        return $price ? (float)$price : 5.00; // 默认5元
    }
}
