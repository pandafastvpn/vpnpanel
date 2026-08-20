# VPN销售系统 - 宝塔面板部署指南

## 目录

1. [环境要求](#1-环境要求)
2. [PHP 8.3 必装扩展](#2-php-83-必装扩展)
3. [宝塔面板部署步骤](#3-宝塔面板部署步骤)
4. [数据库配置](#4-数据库配置)
5. [Nginx 伪静态配置](#5-nginx-伪静态配置)
6. [ToughRadius 对接配置](#6-toughradius-对接配置)
7. [定时任务配置](#7-定时任务配置)
8. [管理员密码重置](#8-管理员密码重置)
9. [安全加固](#9-安全加固)
10. [常见问题](#10-常见问题)

---

## 1. 环境要求

| 组件 | 最低版本 | 推荐版本 |
|------|---------|---------|
| PHP  | 8.1     | 8.3      |
| MySQL| 5.7     | 8.0+     |
| Nginx| 1.18    | 1.24+    |
| ToughRadius | 最新版 | 最新版 |
| ocserv | 1.1.0+ | 最新版 |

> 本系统不需要 Composer，不需要 Node.js，纯 PHP 原生开发。

---

## 2. PHP 8.3 必装扩展

在宝塔面板中：**软件商店 → PHP 8.3 → 设置 → 安装扩展**

### 必须安装的扩展（缺一不可）

| 扩展 | 用途 | 不装的后果 |
|------|------|-----------|
| `pdo_mysql` | 数据库连接 | 系统直接无法运行 |
| `curl` | 调用 ToughRadius API | VPN账号无法创建/管理 |
| `openssl` | 加密、HTTPS、密码哈希 | 注册登录功能失效 |
| `mbstring` | 多字节字符处理 | 中文字符乱码 |
| `json` | JSON 编解码 | API 接口全部报错 |
| `session` | 用户登录会话 | 无法登录 |
| `filter` | 输入过滤 | 安全验证失效 |

### 建议安装的扩展

| 扩展 | 用途 |
|------|------|
| `fileinfo` | 文件上传识别（教程附件等） |
| `redis` | 可选，未来用于缓存加速 |
| `opcache` | PHP 字节码缓存，提升性能 |
| `bcmath` | 高精度数学计算（金额处理更精确） |

### 宝塔面板安装扩展的步骤

```
宝塔面板 → 软件商店 → 找到 "PHP-8.3" → 点击 "设置"
→ "安装扩展" 选项卡
→ 找到上述扩展，点击 "安装"
```

### 禁用函数检查

宝塔默认会禁用一些函数。在 **PHP 设置 → 禁用函数** 中，确保以下函数没有被禁用：

```
curl_init, curl_exec, curl_close     ← ToughRadius API 调用
session_start, session_destroy       ← 用户登录
json_encode, json_decode              ← API 响应
file_get_contents, file_put_contents ← 文件操作
random_bytes, bin2hex                ← CSRF Token 生成
password_hash, password_verify       ← 密码加密验证
```

> **特别注意**: 宝塔面板默认可能禁用了 `putenv`, `proc_open` 等，这些在本系统中不需要，可以保持禁用。

---

## 3. 宝塔面板部署步骤

### 步骤 1: 创建网站

```
宝塔面板 → 网站 → 添加站点
  - 域名: your-domain.com (或 IP)
  - 根目录: /www/wwwroot/your-domain.com
  - PHP版本: PHP-8.3
  - 数据库: MySQL (UTF8MB4)
    - 数据库名: vpn_sales
    - 用户名: vpn_sales
    - 密码: (自动生成，记录下来)
```

### 步骤 2: 上传代码

将项目所有文件上传到网站根目录：

```
/www/wwwroot/your-domain.com/
├── app/                    ← 应用代码
├── config/                 ← 配置文件
├── cron/                   ← 定时任务
├── database/               ← 数据库SQL
├── public/                 ← 网站入口（Nginx 根目录指向这里）
│   └── index.php
├── scripts/                ← 命令行工具
├── storage/                ← 存储目录（需可写）
└── ...
```

### 步骤 3: 设置运行目录

**关键！** 宝塔面板 → 网站 → 你的站点 → 设置 → 网站目录

```
运行目录: /public
```

> 如果不设置运行目录为 `/public`，访问网站会看到目录列表而不是页面。

### 步骤 4: 设置权限

```bash
# SSH 执行
chown -R www:www /www/wwwroot/your-domain.com
chmod -R 755 /www/wwwroot/your-domain.com
chmod -R 775 /www/wwwroot/your-domain.com/storage
```

---

## 4. 数据库配置

### 步骤 1: 导入数据库结构

```
宝塔面板 → 数据库 → 找到 vpn_sales → 导入
→ 上传 database/schema.sql → 执行
```

或通过 SSH 命令行：
```bash
mysql -u vpn_sales -p vpn_sales < /www/wwwroot/your-domain.com/database/schema.sql
```

### 步骤 2: 修改配置文件

```bash
# 复制示例配置
cp /www/wwwroot/your-domain.com/config/config.sample.php \
   /www/wwwroot/your-domain.com/config/config.php
```

编辑 `config/config.php`：

```php
// 数据库配置
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'vpn_sales');          // 宝塔创建的数据库名
define('DB_USER', 'vpn_sales');          // 数据库用户名
define('DB_PASS', '你的数据库密码');       // 宝塔创建时生成的密码
define('DB_CHARSET', 'utf8mb4');

// 站点配置
define('SITE_NAME', 'VPN商店');
define('SITE_URL', 'https://your-domain.com');
define('SITE_DEBUG', false);             // 生产环境务必设为 false
```

> **重要**: `SITE_DEBUG` 在生产环境必须设为 `false`，否则会暴露错误信息。

---

## 5. Nginx 伪静态配置

宝塔面板 → 网站 → 你的站点 → 设置 → 伪静态

粘贴以下内容：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# 禁止访问敏感目录
location ~ ^/(config|app|database|scripts|storage|cron)/ {
    deny all;
    return 404;
}

# 禁止访问 .htaccess .git 等隐藏文件
location ~ /\. {
    deny all;
    return 404;
}
```

> **为什么需要伪静态？** 本系统使用前端控制器模式，所有请求通过 `public/index.php` 路由。Nginx 需要把所有请求转发到 `index.php`。

---

## 6. ToughRadius 对接配置

### 步骤 1: 安装 ToughRadius

参考 ToughRadius 官方文档：https://www.toughradius.net/zh/quickstart.html

### 步骤 2: 在 ToughRadius 中创建计费策略

登录 ToughRadius 管理界面（默认 `http://IP:1816`）：

1. 进入 **计费策略** 页面
2. 创建一个策略，例如：
   - 名称: `vpn-default`
   - 并发数: 3
   - 上行速率: 10240 Kbps (10Mbps)
   - 下行速率: 10240 Kbps (10Mbps)
3. 记录策略的 **ID**（通常从 1 开始）

### 步骤 3: 在 ToughRadius 中注册 NAS 设备

1. 进入 **NAS设备** 页面
2. 添加你的 ocserv 服务器：
   - IP: ocserv 服务器 IP
   - 共享密钥: 自己设一个强密码（ocserv 配置中要一致）
   - 厂商代码: `0` (标准 RADIUS)

### 步骤 4: 填写配置

编辑 `config/config.php`：

```php
// ToughRadius API 配置
define('RADIUS_API_URL', 'http://127.0.0.1:1816');  // ToughRadius 地址
define('RADIUS_API_USER', 'admin');                   // ToughRadius 管理员
define('RADIUS_API_PASS', 'your_toughradius_password'); // ToughRadius 管理员密码
define('RADIUS_PROFILE_ID', 1);   // 上一步创建的计费策略ID
define('RADIUS_NAS_ID', 1);       // NAS设备ID

// ocserv 连接信息（显示给用户的）
define('OCSERV_HOST', 'vpn.your-domain.com');
define('OCSERV_PORT', 443);
define('OCSERV_PROTO', 'anyconnect');
```

---

## 7. 定时任务配置

宝塔面板 → 计划任务 → 添加任务

### 任务 1: 检查过期账户和流量超限（每 5 分钟）

```
任务类型: Shell 脚本
任务名称: VPN过期检查
执行周期: 每5分钟
脚本内容:
```

```bash
#!/bin/bash
# PHP 路径（宝塔默认路径，根据实际修改）
PHP_BIN=/www/server/php/83/bin/php

# 项目路径
PROJECT_PATH=/www/wwwroot/your-domain.com

# 执行检查
$PHP_BIN $PROJECT_PATH/cron/check_expired.php >> $PROJECT_PATH/storage/cron.log 2>&1
```

> **作用**: 自动禁用过期的 VPN 账户和流量超限的账户，断开 Radius 连接。

---

## 8. 管理员密码重置

数据库导入后，管理员账号已创建但密码为空，需要通过脚本重置：

```bash
# SSH 执行
cd /www/wwwroot/your-domain.com
php scripts/reset_admin_password.php
```

输出示例：
```
=========================================
  管理员密码重置成功
=========================================
邮箱: admin@localhost
密码: a1b2c3d4e5f6g7h8
请妥善保管此密码!
=========================================
```

然后访问 `https://your-domain.com/login`，用上述邮箱密码登录。

> 登录后建议在「个人设置」页面修改邮箱和密码。

---

## 9. 安全加固

### 9.1 配置 HTTPS

```
宝塔面板 → 网站 → 你的站点 → SSL → Let's Encrypt
→ 申请免费 SSL 证书 → 开启强制 HTTPS
```

### 9.2 修改 HASH_KEY

编辑 `config/config.php`：

```php
// 将 change_this_to_random_string 改为一个随机字符串
define('HASH_KEY', '你的随机字符串_至少32位');
```

生成随机字符串：
```bash
openssl rand -hex 32
```

### 9.3 设置存储目录权限

```bash
chmod -R 775 /www/wwwroot/your-domain.com/storage
chown -R www:www /www/wwwroot/your-domain.com/storage
```

### 9.4 关闭调试模式

生产环境务必设置：
```php
define('SITE_DEBUG', false);
```

### 9.5 宝塔防火墙

- 只开放 80/443 (Web) 和 SSH 端口
- ToughRadius 的 1816 端口不要对公网开放，只允许本机访问
- RADIUS 的 1812/1813 端口只对 ocserv 服务器开放

---

## 10. 常见问题

### Q: 页面显示空白 / 500 错误

```
原因1: PHP 扩展缺失
解决: 检查 pdo_mysql, curl, openssl, mbstring 是否已安装

原因2: config.php 不存在
解决: cp config/config.sample.php config/config.php 并修改

原因3: storage 目录权限不对
解决: chmod -R 775 storage && chown -R www:www storage
```

### Q: 登录页面正常，但登录后报错

```
原因: session 目录不可写
解决: 
  检查 PHP session.save_path 目录权限
  宝塔 → PHP设置 → session保存路径 → 确认目录存在且可写
```

### Q: 创建 VPN 账号失败

```
原因1: ToughRadius API 连不上
解决: 
  1. 确认 ToughRadius 服务正在运行: systemctl status toughradius
  2. 确认 RADIUS_API_URL 正确
  3. 确认 RADIUS_API_USER / RADIUS_API_PASS 正确
  4. 确认 PHP curl 扩展已安装
  5. 确认服务器能访问 ToughRadius 端口 (1816)

原因2: RADIUS_PROFILE_ID 不正确
解决: 在 ToughRadius 管理界面确认计费策略 ID
```

### Q: 购买套餐扣款成功但 VPN 不通

```
原因: ocserv 的 RADIUS 配置不正确
解决:
  1. 确认 ocserv 配置中 RADIUS 服务器地址正确
  2. 确认共享密钥与 ToughRadius NAS 设备中一致
  3. 查看 ocserv 日志: journalctl -u ocserv -f
  4. 查看 ToughRadius 日志确认是否有认证请求
```

### Q: 定时任务不执行

```
原因: 宝塔计划任务 PHP 路径不对
解决: 
  确认 PHP 路径: which php 或 ls /www/server/php/
  宝塔 PHP 8.3 默认路径: /www/server/php/83/bin/php
  在脚本中使用完整路径而不是 php
```

### Q: 宝塔禁用函数导致报错

```
报错: call to undefined function curl_init()
解决: 宝塔 → PHP设置 → 禁用函数 → 删除 curl_init, curl_exec 等
```

### Q: MySQL 8.0 认证方式问题

```
报错: The server requested authentication method unknown to the client
解决:
  MySQL 8.0 默认使用 caching_sha2_password 认证
  需要改为 mysql_native_password:
  
  ALTER USER 'vpn_sales'@'%' IDENTIFIED WITH mysql_native_password BY '你的密码';
  FLUSH PRIVILEGES;
```

---

## 部署检查清单

- [ ] PHP 8.3 已安装 pdo_mysql, curl, openssl, mbstring, json, session 扩展
- [ ] 宝塔已创建网站，运行目录设为 `/public`
- [ ] Nginx 伪静态规则已配置
- [ ] 数据库 `schema.sql` 已导入
- [ ] `config/config.php` 已创建并填写正确
- [ ] `SITE_DEBUG` 设为 `false`
- [ ] `HASH_KEY` 已修改为随机字符串
- [ ] storage 目录权限 775
- [ ] ToughRadius 已安装并配置计费策略
- [ ] `RADIUS_API_URL`, `RADIUS_API_USER`, `RADIUS_API_PASS` 已填写
- [ ] `RADIUS_PROFILE_ID` 已确认
- [ ] 管理员密码已通过 `reset_admin_password.php` 重置
- [ ] 定时任务（过期检查）已配置，每 5 分钟执行
- [ ] HTTPS 已配置
- [ ] 防火墙只开放 80/443 和 SSH 端口
