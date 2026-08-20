-- VPN销售系统数据库结构
-- MySQL 5.7+ / 8.0+
-- 在宝塔面板创建数据库 vpn_sales 后导入此文件

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 用户表 (商城用户)
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(100) NOT NULL COMMENT '邮箱',
    `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
    `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '账户余额',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=正常',
    `is_admin` TINYINT NOT NULL DEFAULT 0 COMMENT '是否管理员',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商城用户表';

-- 套餐表 (套餐定义, 不含价格; 价格由 package_pricing 表按周期区分)
CREATE TABLE IF NOT EXISTS `packages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT '套餐名称',
    `description` TEXT COMMENT '套餐描述',
    `up_rate` INT NOT NULL DEFAULT 10240 COMMENT '上传速率(Kbps)',
    `down_rate` INT NOT NULL DEFAULT 10240 COMMENT '下载速率(Kbps)',
    `active_num` INT NOT NULL DEFAULT 3 COMMENT '并发连接数',
    `data_limit` BIGINT NOT NULL DEFAULT 0 COMMENT '流量限制(GB), 0=不限',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `radius_profile` VARCHAR(64) DEFAULT NULL COMMENT 'NETORA-Radius Profile名称(留空取全局RADIUS_PROFILE)',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态: 0=下架 1=上架',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='VPN套餐表';

-- 套餐定价表 (同一套餐支持月付/季付/年付等不同周期和价格)
CREATE TABLE IF NOT EXISTS `package_pricing` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `package_id` INT UNSIGNED NOT NULL COMMENT '所属套餐ID',
    `billing_cycle` VARCHAR(20) NOT NULL COMMENT '计费周期: monthly/quarterly/yearly',
    `duration_days` INT NOT NULL COMMENT '有效天数',
    `price` DECIMAL(10,2) NOT NULL COMMENT '价格(元)',
    `original_price` DECIMAL(10,2) DEFAULT NULL COMMENT '原价(用于显示折扣)',
    `is_popular` TINYINT NOT NULL DEFAULT 0 COMMENT '是否推荐: 0=否 1=是',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态: 0=停用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_package_id` (`package_id`),
    KEY `idx_billing_cycle` (`billing_cycle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='套餐定价表';

-- VPN账户表 (对应ToughRadius中的用户, 一个商城用户可以有多个VPN子账号)
CREATE TABLE IF NOT EXISTS `vpn_accounts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '所属商城用户',
    `username` VARCHAR(50) NOT NULL COMMENT 'VPN账号(如1001, 1001a, 1001b)',
    `password` VARCHAR(128) NOT NULL COMMENT 'VPN密码(明文, 用于展示)',
    `radius_user_id` VARCHAR(64) DEFAULT NULL COMMENT 'RADIUS用户标识（NETORA-Radius使用用户名）',
    `package_id` INT UNSIGNED NOT NULL COMMENT '当前套餐ID',
    `up_rate` INT NOT NULL COMMENT '上传速率',
    `down_rate` INT NOT NULL COMMENT '下载速率',
    `active_num` INT NOT NULL DEFAULT 3 COMMENT '并发连接数',
    `data_limit_gb` BIGINT NOT NULL DEFAULT 0 COMMENT '流量限制(GB)',
    `radius_profile` VARCHAR(64) DEFAULT NULL COMMENT '当前生效的NETORA-Radius Profile名称',
    `data_used_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已用流量(字节)',
    `expire_time` DATETIME NOT NULL COMMENT '到期时间',
    `status` VARCHAR(20) NOT NULL DEFAULT 'enabled' COMMENT '状态: enabled/disabled/traffic_exceeded',
    `remark` VARCHAR(100) DEFAULT NULL COMMENT '备注(如: 主账号/给朋友用)',
    `last_online_at` DATETIME DEFAULT NULL COMMENT '最后在线时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_expire_time` (`expire_time`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='VPN账户表';

-- 订单表
CREATE TABLE IF NOT EXISTS `orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `vpn_account_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'VPN账户ID',
    `target_account_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '购买时指定的目标VPN账号ID(null=创建新子账号)',
    `package_id` INT UNSIGNED NOT NULL COMMENT '套餐ID',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '订单金额',
    `package_name` VARCHAR(100) NOT NULL COMMENT '套餐名称(快照)',
    `duration_days` INT NOT NULL COMMENT '有效天数(快照)',
    `up_rate` INT NOT NULL COMMENT '上传速率(快照)',
    `down_rate` INT NOT NULL COMMENT '下载速率(快照)',
    `active_num` INT NOT NULL COMMENT '并发连接数(快照)',
    `data_limit_gb` BIGINT NOT NULL DEFAULT 0 COMMENT '流量限制(快照)',
    `pay_method` VARCHAR(20) NOT NULL DEFAULT 'balance' COMMENT '支付方式: balance/pockyt/payssion',
    `billing_cycle` VARCHAR(20) DEFAULT NULL COMMENT '计费周期: monthly/quarterly/yearly',
    `pricing_id` INT UNSIGNED DEFAULT NULL COMMENT '套餐定价ID',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '订单状态: pending/paid/cancelled/expired',
    `paid_at` DATETIME DEFAULT NULL COMMENT '支付时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单表';

-- VPN订阅表 (每次购买套餐生成一条订阅, 按时间排队生效)
CREATE TABLE IF NOT EXISTS `vpn_subscriptions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vpn_account_id` BIGINT UNSIGNED NOT NULL COMMENT 'VPN账户ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `order_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联订单ID',
    `package_id` INT UNSIGNED NOT NULL COMMENT '套餐ID',
    `pricing_id` INT UNSIGNED DEFAULT NULL COMMENT '定价方案ID',
    `package_name` VARCHAR(100) NOT NULL COMMENT '套餐名称(快照)',
    `billing_cycle` VARCHAR(20) DEFAULT NULL COMMENT '计费周期: monthly/quarterly/yearly',
    `duration_days` INT NOT NULL COMMENT '有效天数',
    `up_rate` INT NOT NULL COMMENT '上传速率(Kbps)',
    `down_rate` INT NOT NULL COMMENT '下载速率(Kbps)',
    `active_num` INT NOT NULL COMMENT '并发连接数',
    `data_limit_gb` BIGINT NOT NULL DEFAULT 0 COMMENT '流量限制(GB), 0=不限',
    `radius_profile` VARCHAR(64) DEFAULT NULL COMMENT 'NETORA-Radius Profile名称(留空取全局配置)',
    `data_used_bytes` BIGINT NOT NULL DEFAULT 0 COMMENT '本订阅周期已用流量(字节)',
    `traffic_baseline_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订阅开始时RADIUS累计流量基线(字节)',
    `start_time` DATETIME NOT NULL COMMENT '生效开始时间',
    `expire_time` DATETIME NOT NULL COMMENT '生效结束时间',
    `status` VARCHAR(20) NOT NULL DEFAULT 'queued' COMMENT '状态: active=当前生效 / queued=排队等待 / expired=已过期 / cancelled=已取消',
    `activated_at` DATETIME DEFAULT NULL COMMENT '实际激活时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vpn_account_id` (`vpn_account_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='VPN订阅表';

-- 充值卡密表
CREATE TABLE IF NOT EXISTS `recharge_cards` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_no` VARCHAR(32) NOT NULL COMMENT '卡密',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '面值',
    `status` VARCHAR(20) NOT NULL DEFAULT 'unused' COMMENT '状态: unused/used/disabled',
    `used_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '使用人ID',
    `used_at` DATETIME DEFAULT NULL COMMENT '使用时间',
    `order_no` VARCHAR(32) DEFAULT NULL COMMENT '关联订单号',
    `batch_no` VARCHAR(32) DEFAULT NULL COMMENT '批次号',
    `expire_at` DATETIME DEFAULT NULL COMMENT '过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_card_no` (`card_no`),
    KEY `idx_status` (`status`),
    KEY `idx_batch_no` (`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值卡密表';

-- 第三方支付订单表 (跟踪Pockyt/Payssion等支付流水)
CREATE TABLE IF NOT EXISTS `payment_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_no` VARCHAR(64) NOT NULL COMMENT '支付流水号',
    `order_no` VARCHAR(32) NOT NULL COMMENT '关联订单号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `gateway` VARCHAR(20) NOT NULL COMMENT '支付网关: pockyt/payssion',
    `gateway_method` VARCHAR(50) DEFAULT NULL COMMENT '支付方式: alipay/wechat/usdt/paypal等',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '支付金额',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD' COMMENT '币种',
    `gateway_trade_no` VARCHAR(128) DEFAULT NULL COMMENT '网关交易号',
    `gateway_trans_id` VARCHAR(128) DEFAULT NULL COMMENT '网关流水ID',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '状态: pending/paid/failed/cancelled',
    `notify_data` TEXT COMMENT '回调通知数据(JSON)',
    `paid_at` DATETIME DEFAULT NULL COMMENT '支付时间',
    `expired_at` DATETIME DEFAULT NULL COMMENT '支付过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_payment_no` (`payment_no`),
    KEY `idx_order_no` (`order_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='第三方支付订单表';

-- 流量记录表 (从ToughRadius同步)
CREATE TABLE IF NOT EXISTS `traffic_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vpn_account_id` BIGINT UNSIGNED NOT NULL COMMENT 'VPN账户ID',
    `username` VARCHAR(50) NOT NULL COMMENT 'VPN账号',
    `input_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上行流量(字节)',
    `output_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '下行流量(字节)',
    `session_id` VARCHAR(64) DEFAULT NULL COMMENT '会话ID',
    `nas_addr` VARCHAR(45) DEFAULT NULL COMMENT 'NAS地址',
    `framed_ip` VARCHAR(45) DEFAULT NULL COMMENT '分配的IP',
    `start_time` DATETIME DEFAULT NULL COMMENT '会话开始时间',
    `stop_time` DATETIME DEFAULT NULL COMMENT '会话结束时间',
    `duration` INT NOT NULL DEFAULT 0 COMMENT '会话时长(秒)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vpn_account_id` (`vpn_account_id`),
    KEY `idx_username` (`username`),
    KEY `idx_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='流量记录表';

-- 系统设置表
CREATE TABLE IF NOT EXISTS `settings` (
    `key_name` VARCHAR(100) NOT NULL,
    `value` TEXT,
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置表';

-- 操作日志表
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '操作人ID',
    `action` VARCHAR(100) NOT NULL COMMENT '操作',
    `target` VARCHAR(100) DEFAULT NULL COMMENT '操作对象',
    `detail` TEXT COMMENT '详情',
    `ip` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';

-- 工单表
CREATE TABLE IF NOT EXISTS `tickets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_no` VARCHAR(32) NOT NULL COMMENT '工单号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '提交用户ID',
    `subject` VARCHAR(200) NOT NULL COMMENT '工单标题',
    `category` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT '分类: general/billing/connection/other',
    `priority` VARCHAR(20) NOT NULL DEFAULT 'normal' COMMENT '优先级: low/normal/high/urgent',
    `status` VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT '状态: open/replied/closed',
    `assigned_to` BIGINT UNSIGNED DEFAULT NULL COMMENT '处理人ID',
    `last_reply_at` DATETIME DEFAULT NULL COMMENT '最后回复时间',
    `last_reply_by` TINYINT NOT NULL DEFAULT 0 COMMENT '最后回复方: 0=用户 1=客服',
    `closed_at` DATETIME DEFAULT NULL COMMENT '关闭时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ticket_no` (`ticket_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单表';

-- 工单回复表
CREATE TABLE IF NOT EXISTS `ticket_replies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id` BIGINT UNSIGNED NOT NULL COMMENT '工单ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '回复人ID',
    `is_staff` TINYINT NOT NULL DEFAULT 0 COMMENT '是否客服回复: 0=用户 1=客服',
    `content` TEXT NOT NULL COMMENT '回复内容',
    `attachments` TEXT DEFAULT NULL COMMENT '附件(JSON)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ticket_id` (`ticket_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单回复表';

-- 优惠码表
CREATE TABLE IF NOT EXISTS `coupon_codes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL COMMENT '优惠码',
    `name` VARCHAR(100) DEFAULT NULL COMMENT '名称/备注',
    `discount_type` VARCHAR(20) NOT NULL DEFAULT 'fixed' COMMENT 'fixed=固定金额 percent=百分比',
    `discount_value` DECIMAL(10,2) NOT NULL COMMENT '折扣值(金额或百分比)',
    `min_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '最低消费门槛',
    `package_id` INT UNSIGNED DEFAULT NULL COMMENT '限定套餐ID(null=不限)',
    `pricing_id` INT UNSIGNED DEFAULT NULL COMMENT '限定定价ID(null=不限)',
    `starts_at` DATETIME DEFAULT NULL COMMENT '生效时间',
    `expires_at` DATETIME DEFAULT NULL COMMENT '过期时间',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '0=停用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠码表';

-- AFF推荐码表 (每个用户一个推荐码)
CREATE TABLE IF NOT EXISTS `aff_referral_codes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '所属用户ID',
    `ref_code` VARCHAR(20) NOT NULL COMMENT '推荐码',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ref_code` (`ref_code`),
    UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AFF推荐码表';

-- AFF邀请记录表
CREATE TABLE IF NOT EXISTS `aff_invites` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referrer_id` BIGINT UNSIGNED NOT NULL COMMENT '推广人ID',
    `invited_user_id` BIGINT UNSIGNED NOT NULL COMMENT '被邀请人ID',
    `ref_code` VARCHAR(20) NOT NULL COMMENT '使用的推荐码',
    `order_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '首单订单ID',
    `status` VARCHAR(20) NOT NULL DEFAULT 'registered' COMMENT 'registered=已注册 ordered=已下单',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_invited_user_id` (`invited_user_id`),
    KEY `idx_referrer_id` (`referrer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AFF邀请记录表';

-- AFF佣金记录表
CREATE TABLE IF NOT EXISTS `aff_commissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referrer_id` BIGINT UNSIGNED NOT NULL COMMENT '推广人ID',
    `invited_user_id` BIGINT UNSIGNED NOT NULL COMMENT '被邀请人ID',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '订单ID',
    `order_amount` DECIMAL(10,2) NOT NULL COMMENT '订单金额',
    `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT '佣金比例(%)',
    `commission` DECIMAL(10,2) NOT NULL COMMENT '佣金金额',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending=待审核 approved=已通过 locked=提现中 withdrawn=已提现',
    `withdrawal_id` INT UNSIGNED DEFAULT NULL COMMENT '关联提现ID',
    `approved_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_referrer_id` (`referrer_id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AFF佣金记录表';

-- AFF提现记录表
CREATE TABLE IF NOT EXISTS `aff_withdrawals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '提现金额',
    `method` VARCHAR(20) NOT NULL COMMENT '提现方式: alipay/wechat/bank/usdt',
    `account` VARCHAR(200) NOT NULL COMMENT '收款账号',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending=待处理 approved=已通过 rejected=已驳回',
    `processed_at` DATETIME DEFAULT NULL COMMENT '处理时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AFF提现记录表';

-- 教程表
CREATE TABLE IF NOT EXISTS `tutorials` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL COMMENT '标题',
    `slug` VARCHAR(100) NOT NULL COMMENT 'URL别名',
    `category` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT '分类: windows/mac/ios/android/router/general',
    `content` LONGTEXT NOT NULL COMMENT '内容(HTML)',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态: 0=隐藏 1=显示',
    `views` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '浏览量',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_category` (`category`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='教程表';

-- 初始化设置
INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('site_name', 'VPN商店', '站点名称'),
('site_template', 'default', '站点模板: default=经典, modern=现代渐变, dark=深色专业, cloud=清爽云蓝'),
('admin_layout', 'topbar', '后台布局: topbar=顶部导航, sidebar=左侧导航'),
('site_announcement', '', '站点公告'),
('site_notice', '欢迎使用VPN服务', '首页公告'),
('payment_card_enabled', '1', '是否启用卡密充值'),
('min_order_amount', '0.01', '最低订单金额'),
('free_trial_enabled', '0', '是否启用免费试用'),
('free_trial_days', '1', '免费试用天数'),
('free_trial_package_id', '1', '免费试用套餐ID'),
('account_prefix', '', '账号前缀(留空则纯数字)'),
('next_account_number', '1000', '下一个账号编号'),
('payment_pockyt_enabled', '0', '是否启用Pockyt支付'),
('payment_pockyt_api_key', '', 'Pockyt API Key'),
('payment_pockyt_secret_key', '', 'Pockyt Secret Key'),
('payment_pockyt_gateway', 'https://openapi.pockyt.io', 'Pockyt API地址'),
('payment_pockyt_currency', 'USD', 'Pockyt支付币种'),
('payment_payssion_enabled', '0', '是否启用Payssion支付'),
('payment_payssion_api_key', '', 'Payssion API Key'),
('payment_payssion_secret_key', '', 'Payssion Secret Key'),
('payment_currency', 'USD', '默认支付币种'),
('traffic_reset_price', '5.00', '流量重置价格(元)'),
('traffic_reset_enabled', '1', '是否启用付费重置流量'),
('balance_payment_enabled', '1', '是否启用余额支付'),
('aff_enabled', '1', '是否启用AFF推广'),
('aff_commission_rate', '10', 'AFF佣金比例(%)'),
('aff_min_withdrawal', '10', 'AFF最低提现金额(元)')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- 插入默认套餐 (2个套餐, 各含月付/季付/年付3种周期)
INSERT INTO `packages` (`id`, `name`, `description`, `up_rate`, `down_rate`, `active_num`, `data_limit`, `sort_order`, `status`) VALUES
(1, '标准套餐', '10Mbps带宽 / 3并发连接', 10240, 10240, 3, 0, 1, 1),
(2, '高级套餐', '20Mbps带宽 / 5并发连接', 20480, 20480, 5, 0, 2, 1);

-- 插入默认定价 (每个套餐含月付/季付/年付)
INSERT INTO `package_pricing` (`package_id`, `billing_cycle`, `duration_days`, `price`, `original_price`, `is_popular`, `sort_order`, `status`) VALUES
-- 标准套餐
(1, 'monthly',   30,  15.00,  18.00, 1, 1, 1),
(1, 'quarterly', 90,  40.00,  45.00, 0, 2, 1),
(1, 'yearly',   365, 150.00, 180.00, 0, 3, 1),
-- 高级套餐
(2, 'monthly',   30,  30.00,  36.00, 1, 1, 1),
(2, 'quarterly', 90,  80.00,  90.00, 0, 2, 1),
(2, 'yearly',   365, 280.00, 360.00, 0, 3, 1);

-- 插入默认管理员账号 (密码需要通过 scripts/reset_admin_password.php 重置)
INSERT INTO `users` (`email`, `password_hash`, `is_admin`, `status`) VALUES
('admin@localhost', '', 1, 1)
ON DUPLICATE KEY UPDATE `email` = `email`;

-- 插入默认教程
INSERT INTO `tutorials` (`title`, `slug`, `category`, `content`, `sort_order`, `status`) VALUES
('Windows 使用教程', 'windows-guide', 'windows', '<h2>Windows 连接教程</h2><p>1. 下载并安装 OpenConnect 客户端</p><p>2. 打开客户端, 输入服务器地址</p><p>3. 输入VPN账号和密码</p><p>4. 点击连接即可</p>', 1, 1),
('macOS 使用教程', 'macos-guide', 'mac', '<h2>macOS 连接教程</h2><p>1. 下载并安装 OpenConnect 客户端</p><p>2. 打开终端或客户端</p><p>3. 输入服务器地址、账号和密码</p><p>4. 连接成功</p>', 2, 1),
('iOS 使用教程', 'ios-guide', 'ios', '<h2>iOS 连接教程</h2><p>1. 在App Store搜索 Cisco AnyConnect</p><p>2. 安装后打开应用</p><p>3. 添加VPN连接, 输入服务器地址</p><p>4. 输入账号密码连接</p>', 3, 1),
('Android 使用教程', 'android-guide', 'android', '<h2>Android 连接教程</h2><p>1. 下载 OpenConnect for Android</p><p>2. 新建连接, 输入服务器地址</p><p>3. 输入账号和密码</p><p>4. 连接</p>', 4, 1),
('常见问题', 'faq', 'general', '<h2>常见问题</h2><p>Q: 连接不上怎么办? A: 检查账号密码是否正确, 服务器地址是否输入正确。</p><p>Q: 如何修改密码? A: 登录后在VPN账户页面点击修改密码。</p>', 5, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 插入测试优惠码
INSERT INTO `coupon_codes` (`code`, `name`, `discount_type`, `discount_value`, `min_amount`, `status`) VALUES
('TEST10', '测试9折', 'percent', 10, 0, 1)
ON DUPLICATE KEY UPDATE `code` = `code`;

SET FOREIGN_KEY_CHECKS = 1;
