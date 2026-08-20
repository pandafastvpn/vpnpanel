<?php

/**
 * 管理员密码重置工具
 * 
 * 使用方法: php scripts/reset_admin_password.php
 * 生成一个随机的管理员密码并更新数据库
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$db = Database::getInstance();

$newPassword = bin2hex(random_bytes(8));
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

$email = 'admin@localhost';

$existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);

if ($existing) {
    $db->update('users', [
        'password_hash' => $hash,
        'is_admin' => 1,
        'status' => 1,
    ], 'email = ?', [$email]);
} else {
    $db->insert('users', [
        'email' => $email,
        'password_hash' => $hash,
        'is_admin' => 1,
        'status' => 1,
        'balance' => 0,
    ]);
}

echo "=========================================\n";
echo "  管理员密码重置成功\n";
echo "=========================================\n";
echo "邮箱: {$email}\n";
echo "密码: {$newPassword}\n";
echo "请妥善保管此密码!\n";
echo "=========================================\n";
