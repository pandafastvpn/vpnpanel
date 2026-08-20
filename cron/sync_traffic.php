<?php

/**
 * 定时任务脚本 - 同步所有VPN账户流量
 * 
 * 从ToughRadius拉取在线会话+历史计费记录, 更新 data_used_bytes
 * 
 * 在宝塔面板添加计划任务:
 *   脚本类型: PHP脚本
 *   执行周期: 每1分钟 (或每5分钟)
 *   脚本内容: php /www/wwwroot/www.jiasupan.com/cron/sync_traffic.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Services\VpnAccountService;

try {
    $db = Database::getInstance();
    $vpnService = new VpnAccountService();

    // 同步所有账户(包括disabled的, 因为用户可能断开后流量还没同步)
    // 但排除已经禁用超过7天的(不浪费API调用)
    $accounts = $db->fetchAll(
        "SELECT id, username, status, data_used_bytes FROM vpn_accounts 
         WHERE status IN ('enabled', 'traffic_exceeded')
            OR (status = 'disabled' AND updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY))
         ORDER BY id ASC"
    );

    $synced = 0;
    $errors = 0;
    $totalBytes = 0;
    $hasData = 0;

    foreach ($accounts as $account) {
        try {
            $result = $vpnService->syncAccountStatus($account['id']);
            $bytes = (int)($result['data_used_bytes'] ?? 0);
            $totalBytes += $bytes;
            $synced++;
            
            if ($bytes > 0) {
                $hasData++;
            }
        } catch (\Exception $e) {
            $errors++;
            echo date('Y-m-d H:i:s') . " - 账户 {$account['username']} 同步失败: " . $e->getMessage() . "\n";
        }
    }

    echo date('Y-m-d H:i:s') . " - 流量同步完成: 同步 {$synced} 个账户 (有流量数据 {$hasData} 个), 总流量 " . formatBytes($totalBytes) . ($errors > 0 ? ", {$errors} 个错误" : "") . "\n";

} catch (\Exception $e) {
    echo date('Y-m-d H:i:s') . " - 致命错误: " . $e->getMessage() . "\n";
}

function formatBytes($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int) floor(log($bytes) / log(1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
