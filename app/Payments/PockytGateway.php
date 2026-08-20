<?php

namespace App\Payments;

/**
 * Pockyt 支付网关
 * 
 * Pockyt 支持: 支付宝、微信、USDT、PayPal 等多种支付方式
 * API文档: https://www.pockyt.io/docs
 * 
 * 工作流程:
 * 1. 用户下单 -> 调用 createPayment 获取支付URL
 * 2. 用户在Pockyt页面完成支付
 * 3. Pockyt回调 notify_url -> verifyCallback + parseCallback
 * 4. 用户跳转回 return_url
 */
class PockytGateway implements PaymentGatewayInterface
{
    private $apiKey;
    private $secretKey;
    private $gatewayUrl;
    private $currency;

    public function __construct($apiKey, $secretKey, $gatewayUrl, $currency = 'USD')
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->gatewayUrl = rtrim($gatewayUrl, '/');
        $this->currency = $currency;
    }

    /**
     * 创建Pockyt支付订单
     * 调用 Pockyt API /payment/v2/pre-creation
     */
    public function createPayment($order, $method)
    {
        $methodMap = [
            'alipay' => 'AlipayBS',
            'wechat' => 'WeChatPay',
            'usdt' => 'USDT',
            'paypal' => 'PayPal',
        ];

        $gatewayMethod = $methodMap[$method] ?? $method;

        $params = [
            'apiKey' => $this->apiKey,
            'merchantNo' => $this->apiKey,
            'storeNo' => $this->apiKey,
            'outOrderNo' => $order['payment_no'],
            'transCurrency' => $order['currency'] ?? $this->currency,
            'transAmount' => number_format($order['amount'], 2, '.', ''),
            'payType' => $gatewayMethod,
            'returnUrl' => SITE_URL . '/payment/return',
            'notifyUrl' => SITE_URL . '/payment/notify/pockyt',
            'subject' => $order['subject'] ?? 'VPN Service',
            'body' => $order['subject'] ?? 'VPN Service',
            'validTime' => 1800, // 30分钟有效
        ];

        // 生成签名
        $params['sign'] = $this->generateSign($params);

        $response = $this->httpPost('/payment/v2/pre-creation', $params);

        if (isset($response['ret_code']) && $response['ret_code'] === '000000') {
            return [
                'success' => true,
                'pay_url' => $response['cashierUrl'] ?? $response['redirectUrl'] ?? '',
                'payment_no' => $order['payment_no'],
                'gateway_trans_id' => $response['transNo'] ?? '',
            ];
        }

        return [
            'success' => false,
            'message' => $response['ret_msg'] ?? 'Pockyt创建支付失败',
        ];
    }

    /**
     * 验证Pockyt回调签名
     */
    public function verifyCallback($data)
    {
        if (empty($data['sign'])) {
            return false;
        }

        $sign = $data['sign'];
        unset($data['sign']);

        $expectedSign = $this->generateSign($data);
        return hash_equals($expectedSign, $sign);
    }

    /**
     * 解析Pockyt回调数据
     */
    public function parseCallback($data)
    {
        $status = 'failed';
        if (isset($data['tradeStatus']) && $data['tradeStatus'] === 'success') {
            $status = 'paid';
        } elseif (isset($data['status']) && $data['status'] === 'success') {
            $status = 'paid';
        }

        return [
            'status' => $status,
            'payment_no' => $data['outOrderNo'] ?? $data['payment_no'] ?? '',
            'gateway_trans_id' => $data['transNo'] ?? $data['orderNo'] ?? '',
            'amount' => isset($data['transAmount']) ? (float)$data['transAmount'] : 0,
            'method' => $data['payType'] ?? '',
            'raw' => $data,
        ];
    }

    /**
     * 查询Pockyt支付状态
     * 调用 /payment/v2/inquiry
     */
    public function queryPayment($paymentNo)
    {
        $params = [
            'apiKey' => $this->apiKey,
            'merchantNo' => $this->apiKey,
            'outOrderNo' => $paymentNo,
        ];
        $params['sign'] = $this->generateSign($params);

        $response = $this->httpPost('/payment/v2/inquiry', $params);

        $status = 'pending';
        if (isset($response['ret_code']) && $response['ret_code'] === '000000') {
            if (($response['tradeStatus'] ?? '') === 'success') {
                $status = 'paid';
            } elseif (($response['tradeStatus'] ?? '') === 'failed') {
                $status = 'failed';
            }
        }

        return [
            'status' => $status,
            'gateway_trans_id' => $response['transNo'] ?? '',
            'amount' => isset($response['transAmount']) ? (float)$response['transAmount'] : 0,
        ];
    }

    /**
     * 获取Pockyt支持的支付方式
     */
    public function getSupportedMethods()
    {
        return [
            ['id' => 'alipay', 'name' => '支付宝', 'icon' => 'bi-alipay'],
            ['id' => 'wechat', 'name' => '微信支付', 'icon' => 'bi-wechat'],
            ['id' => 'usdt', 'name' => 'USDT', 'icon' => 'bi-currency-bitcoin'],
            ['id' => 'paypal', 'name' => 'PayPal', 'icon' => 'bi-paypal'],
        ];
    }

    /**
     * 生成签名
     * Pockyt签名算法: 按参数名ASCII排序 -> 拼接键值对 -> 末尾拼接secretKey -> MD5
     */
    private function generateSign($params)
    {
        // 过滤空值和sign本身
        $params = array_filter($params, function($v) {
            return $v !== '' && $v !== null;
        });
        unset($params['sign']);

        // 按ASCII排序
        ksort($params);

        // 拼接
        $str = '';
        foreach ($params as $key => $value) {
            $str .= $key . '=' . $value . '&';
        }
        $str = rtrim($str, '&');

        // 末尾拼接secretKey
        $str .= $this->secretKey;

        return md5($str);
    }

    /**
     * HTTP POST请求
     */
    private function httpPost($path, $params)
    {
        $url = $this->gatewayUrl . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        return $decoded ?: [];
    }
}
