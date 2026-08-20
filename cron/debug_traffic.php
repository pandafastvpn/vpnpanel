<?php

/**
 * 诊断脚本 - 排查流量统计问题
 * 
 * 用法:
 *   php /www/wwwroot/jiasupan.com/cron/debug_traffic.php
 *   php /www/wwwroot/jiasupan.com/cron/debug_traffic.php 1000
 * 
 * 参数: VPN用户名 (可选, 默认取第一个启用的账户)
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\ToughRadiusClient;

echo "========================================\n";
echo "  ToughRadius 流量诊断工具\n";
echo "========================================\n\n";

$db = Database::getInstance();

// 1. 检查数据库里有没有VPN账户
echo "[1] 检查数据库中的VPN账户...\n";
$username = $argv[1] ?? null;

if ($username) {
    $account = $db->fetch("SELECT * FROM vpn_accounts WHERE username = ?", [$username]);
    if (!$account) {
        echo "  ✗ 未找到用户名为 {$username} 的VPN账户\n";
        echo "  数据库中所有VPN账户:\n";
        $all = $db->fetchAll("SELECT id, username, status, data_used_bytes FROM vpn_accounts ORDER BY id LIMIT 20");
        if (empty($all)) {
            echo "  (数据库中没有任何VPN账户)\n";
            exit(1);
        }
        foreach ($all as $a) {
            echo "    - {$a['username']} (状态: {$a['status']}, 流量: {$a['data_used_bytes']} bytes)\n";
        }
        echo "\n  请用上面的用户名重新运行, 例如: php debug_traffic.php {$all[0]['username']}\n";
        exit(1);
    }
} else {
    $account = $db->fetch("SELECT * FROM vpn_accounts WHERE status IN ('enabled','traffic_exceeded') ORDER BY id ASC LIMIT 1");
    if (!$account) {
        echo "  没有启用状态的VPN账户, 尝试获取所有账户...\n";
        $all = $db->fetchAll("SELECT id, username, status FROM vpn_accounts ORDER BY id ASC LIMIT 20");
        if (empty($all)) {
            echo "  ✗ 数据库中没有任何VPN账户\n";
            exit(1);
        }
        echo "  所有VPN账户:\n";
        foreach ($all as $a) {
            echo "    - {$a['username']} (状态: {$a['status']})\n";
        }
        $account = $all[0];
        $username = $account['username'];
        echo "\n  默认使用第一个账户: {$username}\n";
    } else {
        $username = $account['username'];
    }
}

echo "  ✓ 选中账户: {$account['username']} (ID: {$account['id']})\n";
echo "  - 状态: {$account['status']}\n";
echo "  - 当前流量: " . number_format($account['data_used_bytes']) . " bytes\n";
echo "  - RADIUS用户ID: " . ($account['radius_user_id'] ?: '(无)') . "\n\n";

// 2. 测试ToughRadius API连接
echo "[2] 测试ToughRadius API连接...\n";

$radius = new ToughRadiusClient();

try {
    $token = $radius->login();
    echo "  ✓ API登录成功, 获取到token\n";
} catch (\Exception $e) {
    echo "  ✗ API登录失败: " . $e->getMessage() . "\n";
    echo "\n  请检查 config/config.php 中的 ToughRadius 配置:\n";
    echo "    RADIUS_API_URL, RADIUS_API_USER, RADIUS_API_PASS\n";
    exit(1);
}

echo "\n";

// 3. 拉取在线会话
echo "[3] 拉取在线会话 (/api/v1/sessions/online)...\n";
try {
    $sessions = $radius->getUserSessions($username);
    if (empty($sessions)) {
        echo "  - 没有在线会话 (用户可能未连接VPN)\n";
    } else {
        echo "  ✓ 找到 " . count($sessions) . " 个在线会话:\n";
        foreach ($sessions as $i => $session) {
            echo "\n  --- 会话 #" . ($i + 1) . " ---\n";
            echo "  原始数据:\n";
            echo json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            echo "\n  字段解析:\n";
            $inputFields = ['acct_input_total', 'input_octets', 'acct_input_octets', 'input_total'];
            $outputFields = ['acct_output_total', 'output_octets', 'acct_output_octets', 'output_total'];
            $input = 0;
            $output = 0;
            foreach ($inputFields as $f) {
                if (isset($session[$f])) {
                    echo "    流量上行 {$f} = {$session[$f]}\n";
                    $input = (int)$session[$f];
                    break;
                }
            }
            foreach ($outputFields as $f) {
                if (isset($session[$f])) {
                    echo "    流量下行 {$f} = {$session[$f]}\n";
                    $output = (int)$session[$f];
                    break;
                }
            }
            echo "    合计: " . number_format($input + $output) . " bytes (" . formatBytes($input + $output) . ")\n";
        }
    }
} catch (\Exception $e) {
    echo "  ✗ 拉取在线会话失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. 拉取历史计费记录
echo "[4] 拉取历史计费记录 (/api/v1/accounting)...\n";
try {
    $records = $radius->getUserAccountingRecords($username);
    if (empty($records)) {
        echo "  - 没有历史计费记录\n";
        echo "  ⚠ 这说明 ToughRadius 没有收到过这个用户的 accounting 数据\n";
        echo "  可能原因:\n";
        echo "    1. RemLink/ocserv 未配置 RADIUS accounting 上报\n";
        echo "    2. ToughRadius 的 accounting 接口路径不同\n";
        echo "    3. 用户从未连接过VPN\n";
    } else {
        echo "  ✓ 找到 " . count($records) . " 条历史计费记录:\n";
        foreach ($records as $i => $record) {
            if ($i < 5) {
                echo "\n  --- 记录 #" . ($i + 1) . " ---\n";
                echo json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        if (count($records) > 5) {
            echo "\n  ... 还有 " . (count($records) - 5) . " 条记录未显示\n";
        }

        $totalInput = 0;
        $totalOutput = 0;
        $inputFields = ['acct_input_octets', 'input_octets', 'acct_input_total', 'input_total'];
        $outputFields = ['acct_output_octets', 'output_octets', 'acct_output_total', 'output_total'];
        foreach ($records as $record) {
            foreach ($inputFields as $f) {
                if (isset($record[$f])) {
                    $totalInput += (int)$record[$f];
                    break;
                }
            }
            foreach ($outputFields as $f) {
                if (isset($record[$f])) {
                    $totalOutput += (int)$record[$f];
                    break;
                }
            }
        }
        echo "\n  历史流量汇总:\n";
        echo "    总上行: " . number_format($totalInput) . " bytes (" . formatBytes($totalInput) . ")\n";
        echo "    总下行: " . number_format($totalOutput) . " bytes (" . formatBytes($totalOutput) . ")\n";
        echo "    合计: " . number_format($totalInput + $totalOutput) . " bytes (" . formatBytes($totalInput + $totalOutput) . ")\n";
    }
} catch (\Exception $e) {
    echo "  ✗ 拉取历史计费记录失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. 检查ToughRadius Dashboard
echo "[5] 检查ToughRadius Dashboard...\n";
try {
    $dashboard = $radius->getDashboard();
    echo "  Dashboard数据:\n";
    echo "  " . json_encode($dashboard, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Exception $e) {
    echo "  ✗ 获取Dashboard失败: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "  诊断完成\n";
echo "========================================\n";

function formatBytes($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int) floor(log($bytes) / log(1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
