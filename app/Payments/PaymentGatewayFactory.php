<?php

namespace App\Payments;

use App\Core\Database;

/**
 * 支付网关工厂
 * 根据配置创建对应的支付网关实例
 */
class PaymentGatewayFactory
{
    /**
     * 获取指定网关实例
     */
    public static function create($gatewayName)
    {
        $db = Database::getInstance();

        switch ($gatewayName) {
            case 'pockyt':
                $enabled = $db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_pockyt_enabled'");
                if ($enabled !== '1') {
                    throw new \Exception('Pockyt支付未启用');
                }
                $apiKey = $db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_pockyt_api_key'");
                $secretKey = $db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_pockyt_secret_key'");
                $gatewayUrl = $db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_pockyt_gateway'");
                $currency = $db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_pockyt_currency'") ?: 'USD';

                if (empty($apiKey) || empty($secretKey)) {
                    throw new \Exception('Pockyt支付配置不完整');
                }

                return new PockytGateway($apiKey, $secretKey, $gatewayUrl, $currency);

            case 'payssion':
                $enabled = $db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_payssion_enabled'");
                if ($enabled !== '1') {
                    throw new \Exception('Payssion支付未启用');
                }
                $apiKey = $db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_payssion_api_key'");
                $secretKey = $db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_payssion_secret_key'");
                $currency = $db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_currency'") ?: 'USD';

                if (empty($apiKey) || empty($secretKey)) {
                    throw new \Exception('Payssion支付配置不完整');
                }

                return new PayssionGateway($apiKey, $secretKey, $currency);

            default:
                throw new \Exception("不支持的支付网关: {$gatewayName}");
        }
    }

    /**
     * 获取所有已启用的支付网关及其支付方式
     */
    public static function getEnabledGateways()
    {
        $db = Database::getInstance();
        $gateways = [];

        // Pockyt
        if ($db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_pockyt_enabled'") === '1') {
            try {
                $gateway = self::create('pockyt');
                $gateways[] = [
                    'id' => 'pockyt',
                    'name' => 'Pockyt',
                    'methods' => $gateway->getSupportedMethods(),
                ];
            } catch (\Exception $e) {
            }
        }

        // Payssion
        if ($db->fetchColumn("SELECT value FROM settings WHERE key_name = 'payment_payssion_enabled'") === '1') {
            try {
                $gateway = self::create('payssion');
                $gateways[] = [
                    'id' => 'payssion',
                    'name' => 'Payssion',
                    'methods' => $gateway->getSupportedMethods(),
                ];
            } catch (\Exception $e) {
            }
        }

        return $gateways;
    }
}
