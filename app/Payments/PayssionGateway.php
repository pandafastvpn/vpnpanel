<?php

namespace App\Payments;

/**
 * Payssion 支付网关
 * 
 * Payssion 支持: 支付宝、微信、本地银行、GrabPay 等全球支付方式
 * API文档: https://payssion.docs.apiary.io
 * 
 * 工作流程:
 * 1. 用户下单 -> 调用 createPayment 获取支付URL
 * 2. 用户在Payssion页面完成支付
 * 3. Payssion回调 notify_url -> verifyCallback + parseCallback
 */
class PayssionGateway implements PaymentGatewayInterface
{
    private $apiKey;
    private $secretKey;
    private $currency;

    public function __construct($apiKey, $secretKey, $currency = 'USD')
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->currency = $currency;
    }

    /**
     * 创建Payssion支付订单
     * 调用 Payssion API /v1/payment/create
     */
    public function createPayment($order, $method)
    {
        // Payssion支付方式映射
        $methodMap = [
            'alipay' => 'alipay',
            'wechat' => 'wechat',
            'paypal' => 'paypal',
            'unionpay' => 'unionpay',
            'grabpay' => 'grabpay',
            'boost' => 'boost',
            'fpx' => 'fpx',
        ];

        $gatewayMethod = $methodMap[$method] ?? $method;

        $params = [
            'APIKey' => $this->apiKey,
            'amount' => number_format($order['amount'], 2, '.', ''),
            'currency' => $order['currency'] ?? $this->currency,
            'description' => $order['subject'] ?? 'VPN Service',
            'track_id' => $order['payment_no'],
            'payer_ref' => 'user_' . $order['user_id'],
            'payer_name' => $order['user_email'] ?? '',
            'payer_email' => $order['user_email'] ?? '',
            'return_url' => SITE_URL . '/payment/return',
            'notify_url' => SITE_URL . '/payment/notify/payssion',
            'methods' => $gatewayMethod,
        ];

        // 生成签名
        $params['checksum'] = $this->generateChecksum($params);

        $response = $this->httpPost('https://www.payssion.com/payment/create.html', $params);

        if (isset($response['result']) && $response['result'] === 'success') {
            return [
                'success' => true,
                'pay_url' => $response['redirect_url'] ?? $response['checkout_url'] ?? '',
                'payment_no' => $order['payment_no'],
                'gateway_trans_id' => $response['transaction_id'] ?? '',
            ];
        }

        // 如果API返回的是直接跳转URL模式
        if (isset($response['redirect_url'])) {
            return [
                'success' => true,
                'pay_url' => $response['redirect_url'],
                'payment_no' => $order['payment_no'],
            ];
        }

        return [
            'success' => false,
            'message' => $response['error'] ?? $response['message'] ?? 'Payssion创建支付失败',
        ];
    }

    /**
     * 验证Payssion回调签名
     */
    public function verifyCallback($data)
    {
        if (empty($data['checksum'])) {
            return false;
        }

        $checksum = $data['checksum'];
        unset($data['checksum']);

        $expectedChecksum = $this->generateChecksum($data);
        return hash_equals($expectedChecksum, $checksum);
    }

    /**
     * 解析Payssion回调数据
     */
    public function parseCallback($data)
    {
        $status = 'failed';
        if (isset($data['state'])) {
            // Payssion状态: paid / pending / cancelled / failed
            if ($data['state'] === 'paid' || $data['state'] === 'complete') {
                $status = 'paid';
            } elseif ($data['state'] === 'pending') {
                $status = 'pending';
            }
        }

        return [
            'status' => $status,
            'payment_no' => $data['track_id'] ?? $data['payment_no'] ?? '',
            'gateway_trans_id' => $data['transaction_id'] ?? $data['order_id'] ?? '',
            'amount' => isset($data['amount']) ? (float)$data['amount'] : 0,
            'method' => $data['methods'] ?? $data['method'] ?? '',
            'raw' => $data,
        ];
    }

    /**
     * 查询Payssion支付状态
     * 调用 /api/v1/payment/status
     */
    public function queryPayment($paymentNo)
    {
        $params = [
            'APIKey' => $this->apiKey,
            'track_id' => $paymentNo,
        ];
        $params['checksum'] = $this->generateChecksum($params);

        $response = $this->httpPost('https://www.payssion.com/api/v1/payment/status', $params);

        $status = 'pending';
        if (isset($response['state'])) {
            if ($response['state'] === 'paid' || $response['state'] === 'complete') {
                $status = 'paid';
            } elseif ($response['state'] === 'failed' || $response['state'] === 'cancelled') {
                $status = 'failed';
            }
        }

        return [
            'status' => $status,
            'gateway_trans_id' => $response['transaction_id'] ?? '',
            'amount' => isset($response['amount']) ? (float)$response['amount'] : 0,
        ];
    }

    /**
     * 获取Payssion支持的支付方式
     */
    public function getSupportedMethods()
    {
        return [
            ['id' => 'alipay', 'name' => '支付宝', 'icon' => 'bi-alipay'],
            ['id' => 'wechat', 'name' => '微信支付', 'icon' => 'bi-wechat'],
            ['id' => 'paypal', 'name' => 'PayPal', 'icon' => 'bi-paypal'],
            ['id' => 'unionpay', 'name' => '银联', 'icon' => 'bi-credit-card'],
            ['id' => 'grabpay', 'name' => 'GrabPay', 'icon' => 'bi-wallet'],
            ['id' => 'fpx', 'name' => 'FPX网银', 'icon' => 'bi-bank'],
        ];
    }

    /**
     * 生成Payssion校验码
     * 算法: APIKey + amount + currency + track_id + secretKey -> MD5
     */
    private function generateChecksum($params)
    {
        $str = $this->apiKey
            . ($params['amount'] ?? '')
            . ($params['currency'] ?? '')
            . ($params['track_id'] ?? '')
            . $this->secretKey;

        return md5($str);
    }

    /**
     * HTTP POST请求 (form-data格式)
     */
    private function httpPost($url, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        return $decoded ?: [];
    }
}
