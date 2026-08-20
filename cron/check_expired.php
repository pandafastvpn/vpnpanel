<?php

/**
 * 定时任务脚本 - 检查并处理过期VPN账户
 * 
 * 在宝塔面板添加计划任务:
 *   脚本类型: PHP脚本
 *   执行周期: 每5分钟
 *   脚本内容: php /www/wwwroot/your-domain/cron/check_expired.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Services\VpnAccountService;

try {
    $vpnService = new VpnAccountService();
    $count = $vpnService->checkExpiredAccounts();

    if ($count > 0) {
        echo date('Y-m-d H:i:s') . " - 已自动禁用 {$count} 个过期VPN账户\n";
    }
} catch (\Exception $e) {
    echo date('Y-m-d H:i:s') . " - 错误: " . $e->getMessage() . "\n";
}
