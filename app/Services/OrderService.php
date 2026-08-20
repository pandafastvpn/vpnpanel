<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Auth;

/**
 * 订单服务
 */
class OrderService
{
    private $db;
    private $vpnService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->vpnService = new VpnAccountService();
    }

    /**
     * 创建订单 (使用定价ID, 支持多周期)
     */
    public function createOrderWithPricing($userId, $packageId, $pricingId, $targetAccountId = null, $discountAmount = 0, $couponCode = null)
    {
        $package = $this->db->fetch("SELECT * FROM packages WHERE id = ? AND status = 1", [$packageId]);
        if (!$package) {
            throw new \Exception('套餐不存在或已下架');
        }

        $pricing = $this->db->fetch(
            "SELECT * FROM package_pricing WHERE id = ? AND package_id = ? AND status = 1",
            [$pricingId, $packageId]
        );
        if (!$pricing) {
            throw new \Exception('定价方案不存在');
        }

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            throw new \Exception('用户不存在');
        }

        $finalAmount = max(0, (float) $pricing['price'] - (float) $discountAmount);

        if ($user['balance'] < $finalAmount) {
            throw new \Exception('余额不足, 请先充值或选择在线支付');
        }

        $cycleNames = ['monthly' => '月付', 'quarterly' => '季付', 'yearly' => '年付', 'weekly' => '周付'];
        $packageName = $package['name'] . ' - ' . ($cycleNames[$pricing['billing_cycle']] ?? $pricing['billing_cycle']);

        // 获取用户已有的VPN账号
        $existingAccounts = $this->db->fetchAll(
            "SELECT * FROM vpn_accounts WHERE user_id = ? ORDER BY id ASC",
            [$userId]
        );

        // 判断是续费已有账号 还是 创建新账号
        $targetAccount = null;
        if ($targetAccountId !== null && $targetAccountId > 0) {
            // 用户指定了绑定到哪个已有账号
            foreach ($existingAccounts as $acc) {
                if ($acc['id'] == $targetAccountId) {
                    $targetAccount = $acc;
                    break;
                }
            }
            if (!$targetAccount) {
                throw new \Exception('指定的VPN账号不存在');
            }
        }

        $orderNo = $this->generateOrderNo();

        $orderId = $this->db->insert('orders', [
            'order_no' => $orderNo,
            'user_id' => $userId,
            'package_id' => $packageId,
            'amount' => $finalAmount,
            'package_name' => $packageName,
            'duration_days' => $pricing['duration_days'],
            'up_rate' => $package['up_rate'],
            'down_rate' => $package['down_rate'],
            'active_num' => $package['active_num'],
            'data_limit_gb' => $package['data_limit'],
            'pay_method' => 'balance',
            'billing_cycle' => $pricing['billing_cycle'],
            'pricing_id' => $pricingId,
            'target_account_id' => $targetAccountId,
            'status' => 'pending',
        ]);

        // 扣除余额
        $this->db->update('users', [
            'balance' => $user['balance'] - $finalAmount,
        ], 'id = ?', [$userId]);

        // 标记订单为已支付
        $this->db->update('orders', [
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$orderId]);

        // 创建或续费VPN账户
        $pricingInfo = [
            'pricing_id' => $pricing['id'],
            'billing_cycle' => $pricing['billing_cycle'],
        ];
        if ($targetAccount) {
            // 续费/绑定到已有账号
            $result = $this->vpnService->renewAccount($targetAccount['id'], $packageId, $orderId, $pricing['duration_days'], $pricingInfo);
            $this->db->update('orders', [
                'vpn_account_id' => $targetAccount['id'],
            ], 'id = ?', [$orderId]);
        } else {
            // 创建新子账号
            $result = $this->vpnService->createAccount($userId, $packageId, $orderId, $pricing['duration_days'], $pricingInfo);
            if (isset($result['id'])) {
                $this->db->update('orders', [
                    'vpn_account_id' => $result['id'],
                ], 'id = ?', [$orderId]);
            }
        }

        return [
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'amount' => $finalAmount,
            'discount_amount' => (float) $discountAmount,
            'coupon_code' => $couponCode,
            'package_name' => $packageName,
            'vpn_account' => $result,
        ];
    }

    /**
     * 创建订单 (旧接口兼容, 使用第一个定价方案)
     */
    public function createOrder($userId, $packageId)
    {
        $pricing = $this->db->fetch(
            "SELECT * FROM package_pricing WHERE package_id = ? AND status = 1 ORDER BY sort_order ASC LIMIT 1",
            [$packageId]
        );
        if (!$pricing) {
            throw new \Exception('该套餐没有可用的定价方案');
        }
        return $this->createOrderWithPricing($userId, $packageId, $pricing['id']);
    }

    /**
     * 生成订单号
     */
    private function generateOrderNo()
    {
        return 'ORD' . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * 获取用户订单列表
     */
    public function getUserOrders($userId, $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        $orders = $this->db->fetchAll(
            "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [$userId]
        );
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM orders WHERE user_id = ?",
            [$userId]
        );

        return [
            'data' => $orders,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }
}
