<?php

/**
 * VPN销售系统 - 核心配置文件
 * 
 * 复制此文件为 config.php 并修改配置项
 */

// 数据库配置 (宝塔面板创建的数据库)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'vpn_sales');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 站点配置
define('SITE_NAME', 'VPN商店');
define('SITE_URL', 'http://localhost');
define('SITE_DEBUG', true);
// 建议生产环境把 site_url 改成你的正式域名, 例如 https://www.jiasupan.com

// NETORA-Radius API 配置
// 项目: https://github.com/desienkz-slp/radius-ui
// 默认 API 端口为 3001；建议在防火墙中仅允许商城服务器访问该端口。
define('RADIUS_API_URL', 'http://127.0.0.1:3000');
define('RADIUS_API_TOKEN', 'replace_with_a_long_random_api_token');
// Token 未配置时，客户端可用以下管理账号登录并临时获取 Token。
define('RADIUS_API_USER', 'superadmin');
define('RADIUS_API_PASS', 'change_this_password');
define('RADIUS_API_TIMEOUT', 30);
define('RADIUS_API_VERIFY_SSL', true);
// radius-ui 使用 Profile 名称而非 ToughRADIUS 的数字 Profile ID。
// 这是全局兜底 Profile；每个套餐还可在后台「套餐管理」中绑定各自的 Profile 名称。
// 例如 standard / premium，留空则使用这里的默认值。
define('RADIUS_PROFILE', 'default');

// 账户编号起始值
define('ACCOUNT_START_NUMBER', 1000);

// VPN 服务器节点配置 (支持多节点)
// 格式: 每个节点包含 host/port/proto/label
// 用户可以在这些节点中任选一个连接, 认证统一走 NETORA-Radius
//
// 新增节点时:
//   1. 在新服务器上部署 ocserv/RemLink, 配置 RADIUS 指向 ToughRadius
//   2. 在 ToughRadius 管理 → NAS设备 中添加该服务器 (IP+共享密钥+厂商代码)
//   3. 在这里添加一行节点配置
//   4. 商城会自动展示所有节点供用户选择
$vpnNodes = [
    [
        'label' => '节点1 - 默认',
        'host'  => 'your_vpn_server_ip',
        'port'  => 443,
        'proto' => 'anyconnect',
    ],
    // [
    //     'label' => '节点2 - 上海',
    //     'host'  => 'shanghai.example.com',
    //     'port'  => 443,
    //     'proto' => 'anyconnect',
    // ],
    // [
    //     'label' => '节点3 - 广州',
    //     'host'  => 'guangzhou.example.com',
    //     'port'  => 443,
    //     'proto' => 'anyconnect',
    // ],
];

// 旧版兼容: 单节点配置 (如果没有 $vpnNodes 则使用这些常量)
define('OCSERV_HOST', 'your_vpn_server_ip');
define('OCSERV_PORT', 443);
define('OCSERV_PROTO', 'anyconnect');
define('OCSERV_CERT_HASH', ''); // 服务器证书SHA256指纹，可选

// 支付配置 - 目前支持手动充值和卡密兑换
define('PAYMENT_ENABLED', true);

// 套餐默认配置
define('DEFAULT_UP_RATE', 10240);   // 默认上传速率 10Mbps
define('DEFAULT_DOWN_RATE', 10240); // 默认下载速率 10Mbps
define('DEFAULT_ACTIVE_NUM', 3);    // 默认并发连接数

// 时区
date_default_timezone_set('Asia/Shanghai');

// 会话配置
define('SESSION_LIFETIME', 7200); // 2小时
define('SESSION_NAME', 'VPN_SESS');

// 安全配置
define('HASH_KEY', 'change_this_to_random_string'); // 用于密码哈希的盐
define('CSRF_TOKEN_NAME', '_csrf_token');

// 文件路径 (bootstrap.php 中已定义, 这里用 if 保护避免重复定义)
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
if (!defined('APP_PATH')) define('APP_PATH', ROOT_PATH . '/app');
if (!defined('VIEW_PATH')) define('VIEW_PATH', ROOT_PATH . '/app/views');
if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', ROOT_PATH . '/public');
if (!defined('STORAGE_PATH')) define('STORAGE_PATH', ROOT_PATH . '/storage');
if (!defined('CONFIG_PATH')) define('CONFIG_PATH', ROOT_PATH . '/config');
