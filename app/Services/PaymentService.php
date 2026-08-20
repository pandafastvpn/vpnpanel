<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Auth;
use App\Payments\PaymentGatewayFactory;
use App\Payments\PaymentGatewayInterface;

/**
 * 支付服务
 * 
 * 处理第三方支付完整流程:
 * 1. 创建支付订单(关联VPN订单, 调用网关获取支付URL)
 * 2. 处理支付回调(验证签名 -> 更新订单状态 -> 自动开通/续费VPN)
 * 3. 查询支付状态(供前端轮询)
 */
class PaymentService
{
    private $db;
    private $vpnService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->vpnService = new VpnAccountService();
    }

    /**
     * 创建支付订单
     * 
     * @param int $userId 用户ID
     * @param int $packageId 套餐ID
     * @param int $pricingId 套餐定价ID
     * @param string $gateway 支付网关 pockyt/payssion
     * @param string $method 支付方式 alipay/wechat/usdt/paypal等
     * @return array ['success', 'pay_url', 'payment_no', 'order_no']
     */
    public function createPaymentOrder($userId, $packageId, $pricingId, $gateway, $method, $targetAccountId = null, $discountAmount = 0, $couponCode = null)
    {
        // 获取套餐和定价信息
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

        // 获取用户信息
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            throw new \Exception('用户不存在');
        }

        // 创建VPN订单(pending状态, 等待支付完成)
        $orderNo = 'ORD' . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $finalAmount = max(0, (float) $pricing['price'] - (float) $discountAmount);

        $orderId = $this->db->insert('orders', [
            'order_no' => $orderNo,
            'user_id' => $userId,
            'package_id' => $packageId,
            'amount' => $finalAmount,
            'package_name' => $package['name'] . ' - ' . $this->getCycleName($pricing['billing_cycle']),
            'duration_days' => $pricing['duration_days'],
            'up_rate' => $package['up_rate'],
            'down_rate' => $package['down_rate'],
            'active_num' => $package['active_num'],
            'data_limit_gb' => $package['data_limit'],
            'pay_method' => $gateway,
            'billing_cycle' => $pricing['billing_cycle'],
            'pricing_id' => $pricingId,
            'target_account_id' => $targetAccountId,
            'status' => 'pending',
        ]);

        // 创建支付订单
        $paymentNo = 'PAY' . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $currency = $this->db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_currency'") ?: 'USD';

        $paymentOrderId = $this->db->insert('payment_orders', [
            'payment_no' => $paymentNo,
            'order_no' => $orderNo,
            'user_id' => $userId,
            'gateway' => $gateway,
            'gateway_method' => $method,
            'amount' => $finalAmount,
            'currency' => $currency,
            'status' => 'pending',
            'expired_at' => date('Y-m-d H:i:s', time() + 1800),
        ]);

        // 调用支付网关创建支付
        try {
            $gatewayInstance = PaymentGatewayFactory::create($gateway);

            $gatewayResult = $gatewayInstance->createPayment([
                'payment_no' => $paymentNo,
                'amount' => $finalAmount,
                'currency' => $currency,
                'subject' => $package['name'] . ' ' . $this->getCycleName($pricing['billing_cycle']),
                'user_id' => $userId,
                'user_email' => $user['email'],
            ], $method);

            if (!$gatewayResult['success']) {
                // 更新支付订单为失败
                $this->db->update('payment_orders', [
                    'status' => 'failed',
                ], 'id = ?', [$paymentOrderId]);

                // 取消订单
                $this->db->update('orders', [
                    'status' => 'cancelled',
                ], 'id = ?', [$orderId]);

                throw new \Exception($gatewayResult['message'] ?? '支付创建失败');
            }

            // 保存网关交易号
            if (isset($gatewayResult['gateway_trans_id']) && $gatewayResult['gateway_trans_id']) {
                $this->db->update('payment_orders', [
                    'gateway_trans_id' => $gatewayResult['gateway_trans_id'],
                ], 'id = ?', [$paymentOrderId]);
            }

            return [
                'success' => true,
                'pay_url' => $gatewayResult['pay_url'],
                'payment_no' => $paymentNo,
                'order_no' => $orderNo,
            ];
        } catch (\Exception $e) {
            // 取消订单
            $this->db->update('orders', [
                'status' => 'cancelled',
            ], 'id = ?', [$orderId]);

            $this->db->update('payment_orders', [
                'status' => 'failed',
            ], 'id = ?', [$paymentOrderId]);

            throw $e;
        }
    }

    /**
     * 处理支付回调
     * 
     * @param string $gateway 支付网关名称
     * @param array $data 回调数据
     * @return array ['success', 'message']
     */
    public function handleCallback($gateway, $data)
    {
        try {
            $gatewayInstance = PaymentGatewayFactory::create($gateway);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => '网关不可用: ' . $e->getMessage()];
        }

        // 验证签名
        if (!$gatewayInstance->verifyCallback($data)) {
            return ['success' => false, 'message' => '签名验证失败'];
        }

        // 解析回调数据
        $parsed = $gatewayInstance->parseCallback($data);
        $paymentNo = $parsed['payment_no'];

        if (empty($paymentNo)) {
            return ['success' => false, 'message' => '无法获取支付流水号'];
        }

        // 查找支付订单
        $paymentOrder = $this->db->fetch("SELECT * FROM payment_orders WHERE payment_no = ?", [$paymentNo]);
        if (!$paymentOrder) {
            return ['success' => false, 'message' => '支付订单不存在'];
        }

        // 防止重复处理
        if ($paymentOrder['status'] === 'paid') {
            return ['success' => true, 'message' => '已处理过'];
        }

        // 查找关联的VPN订单
        $order = $this->db->fetch("SELECT * FROM orders WHERE order_no = ?", [$paymentOrder['order_no']]);
        if (!$order) {
            return ['success' => false, 'message' => '关联订单不存在'];
        }

        // 更新支付订单
        $this->db->update('payment_orders', [
            'status' => $parsed['status'] === 'paid' ? 'paid' : 'failed',
            'gateway_trade_no' => $parsed['gateway_trans_id'] ?? null,
            'notify_data' => json_encode($parsed['raw'], JSON_UNESCAPED_UNICODE),
            'paid_at' => $parsed['status'] === 'paid' ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [$paymentOrder['id']]);

        // 如果支付成功, 激活订单
        if ($parsed['status'] === 'paid') {
            $this->activateOrder($order);
            return ['success' => true, 'message' => '支付成功, 已自动开通'];
        }

        // 支付失败, 取消订单
        $this->db->update('orders', [
            'status' => 'cancelled',
        ], 'id = ?', [$order['id']]);

        return ['success' => false, 'message' => '支付未成功'];
    }

    /**
     * 激活订单 - 自动创建或续费VPN账户
     * 
     * @param array $order 订单信息
     */
    private function activateOrder($order)
    {
        $this->db->beginTransaction();

        try {
            // 标记订单为已支付
            $this->db->update('orders', [
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$order['id']]);

            // 检查用户已有的VPN账号
            $existingAccounts = $this->db->fetchAll(
                "SELECT * FROM vpn_accounts WHERE user_id = ? ORDER BY id ASC",
                [$order['user_id']]
            );

            // 构建套餐数据(从订单快照恢复)
            $packageData = [
                'id' => $order['package_id'],
                'name' => $order['package_name'],
                'duration_days' => $order['duration_days'],
                'up_rate' => $order['up_rate'],
                'down_rate' => $order['down_rate'],
                'active_num' => $order['active_num'],
                'data_limit' => $order['data_limit_gb'],
            ];

            $pricingInfo = [
                'pricing_id' => $order['pricing_id'] ?? null,
                'billing_cycle' => $order['billing_cycle'] ?? null,
            ];

            // 对于第三方支付, 默认行为: 如果有账号就续费第一个, 没有就创建
            // (第三方支付下单时已将 target_account_id 存入订单, 支付完成时恢复)
            $targetAccountId = $order['target_account_id'] ?? null;
            $targetAccount = null;
            if ($targetAccountId) {
                foreach ($existingAccounts as $acc) {
                    if ($acc['id'] == $targetAccountId) {
                        $targetAccount = $acc;
                        break;
                    }
                }
            }

            if ($targetAccount) {
                // 续费指定账号
                $result = $this->vpnService->renewAccount($targetAccount['id'], $order['package_id'], $order['id'], $order['duration_days'], $pricingInfo);
                $this->db->update('orders', [
                    'vpn_account_id' => $targetAccount['id'],
                ], 'id = ?', [$order['id']]);
            } elseif (!empty($existingAccounts)) {
                // 有账号但未指定 → 续费第一个
                $result = $this->vpnService->renewAccount($existingAccounts[0]['id'], $order['package_id'], $order['id'], $order['duration_days'], $pricingInfo);
                $this->db->update('orders', [
                    'vpn_account_id' => $existingAccounts[0]['id'],
                ], 'id = ?', [$order['id']]);
            } else {
                // 新建
                $result = $this->vpnService->createAccount($order['user_id'], $order['package_id'], $order['id'], $order['duration_days'], $pricingInfo);
                if (isset($result['id'])) {
                    $this->db->update('orders', [
                        'vpn_account_id' => $result['id'],
                    ], 'id = ?', [$order['id']]);
                }
            }

            // 记录日志
            $this->db->insert('admin_logs', [
                'user_id' => $order['user_id'],
                'action' => 'payment_activated',
                'target' => $order['order_no'],
                'detail' => "支付完成自动激活: {$order['package_name']} ¥{$order['amount']}",
                'ip' => Auth::getClientIp(),
            ]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 查询支付状态(供前端轮询)
     */
    public function checkPaymentStatus($paymentNo)
    {
        $paymentOrder = $this->db->fetch(
            "SELECT * FROM payment_orders WHERE payment_no = ? AND user_id = ?",
            [$paymentNo, Auth::id()]
        );

        if (!$paymentOrder) {
            return ['status' => 'not_found'];
        }

        // 如果本地状态还是pending, 主动查询网关
        if ($paymentOrder['status'] === 'pending') {
            try {
                $gatewayInstance = PaymentGatewayFactory::create($paymentOrder['gateway']);
                $queryResult = $gatewayInstance->queryPayment($paymentNo);

                if ($queryResult['status'] === 'paid') {
                    // 网关显示已支付, 激活订单
                    $order = $this->db->fetch("SELECT * FROM orders WHERE order_no = ?", [$paymentOrder['order_no']]);
                    if ($order && $order['status'] === 'pending') {
                        $this->activateOrder($order);

                        $this->db->update('payment_orders', [
                            'status' => 'paid',
                            'gateway_trans_id' => $queryResult['gateway_trans_id'] ?? null,
                            'paid_at' => date('Y-m-d H:i:s'),
                        ], 'id = ?', [$paymentOrder['id']]);

                        return ['status' => 'paid'];
                    }
                }

                return ['status' => $queryResult['status']];
            } catch (\Exception $e) {
                return ['status' => 'pending'];
            }
        }

        return ['status' => $paymentOrder['status']];
    }

    /**
     * 获取计费周期名称
     */
    private function getCycleName($cycle)
    {
        $map = [
            'monthly' => '月付',
            'quarterly' => '季付',
            'yearly' => '年付',
            'weekly' => '周付',
            'biannually' => '半年付',
        ];
        return $map[$cycle] ?? $cycle;
    }
}
