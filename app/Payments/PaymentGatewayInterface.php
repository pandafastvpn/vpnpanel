<?php

namespace App\Payments;

/**
 * 支付网关抽象接口
 */
interface PaymentGatewayInterface
{
    /**
     * 创建支付订单 - 获取支付链接或跳转URL
     * 
     * @param array $order 订单信息 (payment_no, amount, currency, subject, user_id)
     * @param string $method 支付方式 (alipay/wechat/usdt/paypal等)
     * @return array ['success'=>bool, 'pay_url'=>string, 'payment_no'=>string]
     */
    public function createPayment($order, $method);

    /**
     * 验证回调签名
     * 
     * @param array $data 回调数据
     * @return bool
     */
    public function verifyCallback($data);

    /**
     * 解析回调数据获取支付状态
     * 
     * @param array $data 回调数据
     * @return array ['status'=>'paid/failed', 'payment_no'=>string, 'amount'=>float, 'gateway_trans_id'=>string]
     */
    public function parseCallback($data);

    /**
     * 查询支付状态
     * 
     * @param string $paymentNo 支付流水号
     * @return array ['status'=>'paid/failed/pending']
     */
    public function queryPayment($paymentNo);

    /**
     * 获取支持的支付方式列表
     * 
     * @return array [['id'=>'alipay','name'=>'支付宝','icon'=>'...'], ...]
     */
    public function getSupportedMethods();
}
