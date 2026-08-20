<?php

/**
 * 诊断脚本 - 检查 RemLink SQLite 数据库结构
 * 
 * 用法:
 *   php /www/wwwroot/www.jiasupan.com/cron/debug_remlink_db.php
 *   php /www/wwwroot/www.jiasupan.com/cron/debug_remlink_db.php 1000
 */

// RemLink 数据库路径 (可能需要根据实际情况调整)
$remlinkDbPath = '/www/server/remlink/conf/remlink.db';
if (!file_exists($remlinkDbPath)) {
    // 尝试其他常见路径
    $paths = [
        '/etc/remlink/conf/remlink.db',
        '/opt/remlink/conf/remlink.db',
        '/usr/local/remlink/conf/remlink.db',
        '/root/remlink/conf/remlink.db',
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) {
            $remlinkDbPath = $p;
            break;
        }
    }
}

echo "========================================\n";
echo "  RemLink 数据库诊断工具\n";
echo "========================================\n\n";

if (!file_exists($remlinkDbPath)) {
    echo "✗ 找不到 remlink.db 文件\n";
    echo "  尝试过的路径:\n";
    echo "    /www/server/remlink/conf/remlink.db\n";
    echo "    /etc/remlink/conf/remlink.db\n";
    echo "    /opt/remlink/conf/remlink.db\n";
    echo "    /usr/local/remlink/conf/remlink.db\n";
    echo "    /root/remlink/conf/remlink.db\n";
    echo "\n  请用以下命令查找数据库位置:\n";
    echo "    find / -name 'remlink.db' 2>/dev/null\n";
    exit(1);
}

echo "[1] 找到数据库: {$remlinkDbPath}\n";
echo "    文件大小: " . formatSize(filesize($remlinkDbPath)) . "\n\n";

// 连接 SQLite
try {
    $pdo = new PDO('sqlite:' . $remlinkDbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[2] ✓ SQLite 连接成功\n\n";
} catch (\Exception $e) {
    echo "[2] ✗ SQLite 连接失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 列出所有表
echo "[3] 数据库中的表:\n";
try {
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo "  (没有任何表)\n";
        exit(1);
    }
    foreach ($tables as $table) {
        echo "  - {$table}\n";
    }
} catch (\Exception $e) {
    echo "  ✗ 获取表列表失败: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// 遍历每个表, 显示结构和前3条数据
echo "[4] 各表结构和数据:\n\n";

foreach ($tables as $table) {
    echo "=== 表: {$table} ===\n";
    
    // 表结构
    try {
        $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
        echo "  字段:\n";
        foreach ($columns as $col) {
            echo "    - {$col['name']} ({$col['type']})\n";
        }
    } catch (\Exception $e) {
        echo "  ✗ 获取表结构失败: " . $e->getMessage() . "\n";
        continue;
    }
    
    // 数据条数
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "  总记录数: {$count}\n";
    } catch (\Exception $e) {
        echo "  ✗ 获取记录数失败: " . $e->getMessage() . "\n";
    }
    
    // 前3条数据
    try {
        $rows = $pdo->query("SELECT * FROM {$table} LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            echo "  示例数据:\n";
            foreach ($rows as $i => $row) {
                echo "    --- 第" . ($i + 1) . "条 ---\n";
                foreach ($row as $key => $val) {
                    $display = $val;
                    if ($val !== null && strlen($val) > 200) {
                        $display = substr($val, 0, 200) . '...';
                    }
                    echo "      {$key}: {$display}\n";
                }
            }
        }
    } catch (\Exception $e) {
        echo "  ✗ 获取数据失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// 如果指定了用户名, 尝试在所有表里搜索
$username = $argv[1] ?? null;
if ($username) {
    echo "[5] 在所有表中搜索用户 {$username}:\n\n";
    foreach ($tables as $table) {
        try {
            $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
            $textCols = array_filter($columns, function($c) {
                $type = strtolower($c['type']);
                return in_array($type, ['text', 'varchar', 'char', '']) || strpos($type, 'int') !== false;
            });
            
            foreach ($textCols as $col) {
                $colName = $col['name'];
                try {
                    $rows = $pdo->query("SELECT * FROM {$table} WHERE CAST({$colName} AS TEXT) LIKE '%{$username}%' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($rows)) {
                        echo "  表 {$table}.{$colName} 中找到匹配:\n";
                        foreach ($rows as $row) {
                            echo "    " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
                        }
                        echo "\n";
                    }
                } catch (\Exception $e) {
                    // 跳过不支持的列
                }
            }
        } catch (\Exception $e) {
            // 跳过
        }
    }
}

echo "========================================\n";
echo "  诊断完成\n";
echo "========================================\n";

function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
