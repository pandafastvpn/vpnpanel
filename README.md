# ocserv VPN 销售系统

基于 [NETORA-Radius](https://github.com/desienkz-slp/radius-ui) 和 [ocserv](https://gitlab.com/openconnect/ocserv) 的 VPN 账户销售管理系统。

## 架构概览

```
用户浏览器  ──>  VPN销售系统 (PHP/MySQL, 宝塔面板)
                        │
                        ├── NETORA-Radius Admin API (REST)
                        │     ├── 创建/禁用/删除 RADIUS 用户
                        │     └── 管理计费策略、在线会话
                        │
                        └── ToughRadius RADIUS 服务
                                │
                                └── ocserv VPN 服务器
                                      ↑ PAP认证 + 计费
                                      │
                                VPN客户端 (AnyConnect/OpenConnect)
```

## 核心功能

### 用户端
- **注册/登录** - 邮箱注册, 密码登录
- **套餐购买** - 多种套餐选择, 余额支付自动开通
- **VPN账户管理** - 查看账号密码、连接信息、在线设备
- **账户续费** - 套餐到期前续费延长有效期
- **密码重置** - 一键重置VPN连接密码
- **卡密充值** - 使用卡密充值余额
- **订单记录** - 完整的购买/充值历史

### 管理后台
- **仪表盘** - 用户数、VPN账户数、收入统计、订单趋势
- **用户管理** - 查看用户、启用/禁用、调整余额
- **VPN账户管理** - 查看所有VPN账户、启用/禁用、重置密码
- **套餐管理** - 创建/编辑/删除套餐，设置价格和速率
- **卡密管理** - 批量生成卡密、导出CSV、查看使用状态
- **订单管理** - 查看所有订单、筛选搜索
- **系统设置** - 站点名称、公告、支付开关等
- **操作日志** - 完整的管理操作审计日志

### VPN账户机制
- 账号为纯数字，从 **1000** 开始自动递增
- 购买套餐后自动在 ToughRadius 中创建 RADIUS 用户
- 续费时自动延长 ToughRadius 中的到期时间
- 过期后自动禁用 (定时任务)
- 密码为随机12位字母数字

## 系统要求

- PHP 7.4+ (宝塔面板安装)
- MySQL 5.7+ / MariaDB 10.3+
- ToughRadius (运行在VPN服务器上)
- ocserv (运行在VPN服务器上)
- 宝塔面板 (推荐，也可手动配置)

## 安装指南

### 第一步: 部署VPN销售系统

1. **上传代码到服务器**

   将本项目上传到宝塔面板的网站目录，例如 `/www/wwwroot/vpn-shop/`

2. **宝塔面板创建网站**
   - 添加站点
   - 域名: 你的域名或IP
   - 根目录: `/www/wwwroot/vpn-shop/public`
   - PHP版本: 7.4 或更高

3. **创建数据库**
   - 在宝塔面板中创建 MySQL 数据库
   - 数据库名: `vpn_sales`
   - 导入 `database/schema.sql`

4. **配置项目**
   ```bash
   cp config/config.sample.php config/config.php
   ```
   
   编辑 `config/config.php`，填写数据库信息:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'vpn_sales');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   ```

5. **设置伪静态规则**
   
   网站根目录必须指向项目的 `public/` 目录，例如 `/www/wwwroot/vpn-shop/public`。
   在宝塔面板 > 网站 > 设置 > 伪静态 中粘贴项目里的 `public/nginx.conf`。
   如果只使用最小配置，也必须至少包含：
   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }

   location ~ ^/(app|config|storage|scripts|cron|vendor)(/|$) {
       deny all;
       return 404;
   }

   location ~ ^/(\\.env|\\.git|composer\\.(json|lock)|.*\\.(sql|md|log|bak|conf|yml|yaml))$ {
       deny all;
       return 404;
   }
   ```

6. **设置目录权限**
   ```bash
   chown -R www:www /www/wwwroot/vpn-shop/
   chmod -R 755 /www/wwwroot/vpn-shop/
   ```

7. **重置管理员密码**
   ```bash
   cd /www/wwwroot/vpn-shop
   php scripts/reset_admin_password.php
   ```
   记住输出的邮箱和密码。

8. **添加定时任务**
   
   在宝塔面板 > 计划任务 中添加:
   - 任务类型: Shell脚本
   - 执行周期: 每5分钟
   - 脚本内容:
     ```bash
     php /www/wwwroot/vpn-shop/cron/check_expired.php
     ```

### 第二步: 安装和配置 NETORA-Radius

1. **安装 ToughRadius**

   参考项目根目录的 `ocserv-config/install-ocserv-radius.sh` 一键脚本，或手动安装:
   ```bash
   # 下载
   mkdir -p /opt/toughradius && cd /opt/toughradius
   wget https://github.com/talkincode/toughradius/releases/latest/download/toughradius-linux-amd64.tar.gz
   tar xzf toughradius-linux-amd64.tar.gz
   chmod +x toughradius
   ```

2. **配置 ToughRadius**
   ```bash
   cp toughradius.yml toughradius.prod.yml
   ```
   
   编辑 `toughradius.prod.yml`:
   ```yaml
   system:
     appid: ToughRADIUS
     location: Asia/Shanghai
     workdir: ./rundata
   
   database:
     type: sqlite
     name: toughradius.db
   
   radiusd:
     enabled: true
     host: 0.0.0.0
     auth_port: 1812
     acct_port: 1813
   
   web:
     host: 0.0.0.0
     port: 1816
     secret: "YOUR_RANDOM_LONG_SECRET_HERE"
   ```

3. **初始化数据库**
   ```bash
   ./toughradius -initdb -c toughradius.prod.yml
   ```

4. **启动 ToughRadius**
   ```bash
   ./toughradius -c toughradius.prod.yml
   ```
   
   首次启动后，初始密码在 `rundata/private/admin-bootstrap-password` 文件中。
   
   也可通过环境变量设置初始密码:
   ```bash
   export TOUGHRADIUS_ADMIN_PASSWORD="your_admin_password"
   ./toughradius -c toughradius.prod.yml
   ```

5. **在 ToughRadius 管理界面中配置:**

   访问 `http://你的服务器IP:1816`

   a. **添加 NAS 设备** (Network > NAS)
   - 名称: ocserv
   - IP地址: 127.0.0.1 (如果ocserv在同一台服务器) 或 ocserv服务器IP
   - 共享密钥: 自己设定一个密钥 (例如 `my_radius_secret`)
   - 状态: enabled

   b. **创建计费策略** (RADIUS Profile)
   - 名称: default
   - 状态: enabled
   - 并发连接数: 3
   - 上传速率(Kbps): 10240 (即10Mbps)
   - 下载速率(Kbps): 10240
   - 记下创建后的 **Profile ID**

6. **配置VPN销售系统连接 ToughRadius**
   
   编辑 `config/config.php`:
   ```php
   define('RADIUS_API_URL', 'http://127.0.0.1:3001');
   define('RADIUS_API_TOKEN', 'your_radius_ui_api_token');
   define('RADIUS_API_USER', 'superadmin'); // Token 缺失时用于登录
   define('RADIUS_API_PASS', 'your_radius_ui_password');
   define('RADIUS_PROFILE', 'default'); // NETORA-Radius中的Profile名称
   ```

### 站点模板切换

系统内置 4 套前端模板，登录后台后进入“系统设置 → 站点模板”即可一键切换，保存后刷新立即生效：

| 标识 | 名称 | 布局 | 风格 |
|---|---|---|---|
| `default` | 经典 | 左侧导航（可切顶部） | 传统后台风格 |
| `modern` | 现代渐变 | 左侧导航 | 蓝紫渐变、大圆角卡片 |
| `dark` | 深色专业 | 左侧导航 | 暗黑科技风 |
| `cloud` | 清爽云蓝 | 左侧导航 | 明亮轻量、蓝天白云 |

所有模板使用统一的页面骨架（左侧导航 + 内容区），仅切换视觉风格，因此切换时不会发生布局错位。

- 模板只影响商城和用户中心页面；登录/注册页使用独立空白布局，不受影响。
- “后台布局”设置仅对经典模板生效。
- 支持预览：系统设置中选择模板后点击“预览”按钮，会以对应模板临时打开首页。

### 后台布局模板

后台支持两种可切换布局：

- **顶部导航（推荐）**：取消固定右侧/左侧导航，菜单位于顶部，适合管理页面和宽屏使用。
- **左侧导航**：保留传统左侧菜单，适合需要频繁切换后台模块的场景。

登录后台后进入“系统设置”，修改“后台布局”，保存后刷新页面即可生效。新安装默认使用顶部导航。

### 多套餐与 NETORA-Radius Profile 绑定

每个套餐可以在后台“套餐管理”的编辑弹窗中填写“NETORA-Radius Profile 名称”：

- 留空：创建账号时使用 `config/config.php` 中的全局 `RADIUS_PROFILE`。
- 填写名称：创建/续费该套餐时，账号会绑定到 NETORA-Radius 中同名 Profile。

推荐做法：

1. 在 NETORA-Radius 后台为每个套餐创建独立 Profile，例如 `standard`、`premium`。
2. 在每个 Profile 中配置对应的并发数 (`Simultaneous-Use`) 和限速属性。
3. 在商城“套餐管理”中把套餐绑定到对应 Profile。
4. 对已有账号，可在“VPN账户 → 启用/切换订阅”触发 Profile 更新。

### 第三步: 配置 ocserv

1. **安装 ocserv**
   ```bash
   # CentOS/RHEL
   yum install -y epel-release
   yum install -y ocserv
   
   # Ubuntu/Debian
   apt update && apt install -y ocserv
   ```

2. **生成TLS证书**
   ```bash
   mkdir -p /etc/ocserv/certs
   cd /etc/ocserv/certs
   openssl genrsa -out server-key.pem 2048
   openssl req -new -key server-key.pem -out server.csr -subj "/CN=VPN-Server"
   openssl x509 -req -days 3650 -in server.csr -signkey server-key.pem -out server-cert.pem
   rm server.csr
   ```

3. **配置 ocserv**
   
   编辑 `/etc/ocserv/ocserv.conf` (参考 `ocserv-config/ocserv.conf`):
   ```
   auth = "radius[auth=true,acct=true]"
   radius-server = "127.0.0.1:1812"
   radius-secret = "my_radius_secret"
   radius-acct-port = 1813
   
   listen = "0.0.0.0:443"
   server-cert = /etc/ocserv/certs/server-cert.pem
   server-key = /etc/ocserv/certs/server-key.pem
   
   ipv4-network = 192.168.99.0
   ipv4-netmask = 255.255.255.0
   mtu = 1400
   dns = 8.8.8.8
   
   max-clients = 100
   max-same-clients = 5
   ```

4. **开启IP转发和NAT**
   ```bash
   echo "net.ipv4.ip_forward = 1" >> /etc/sysctl.conf
   sysctl -p
   
   iptables -t nat -A POSTROUTING -s 192.168.99.0/24 -j MASQUERADE
   # 保存iptables规则
   # CentOS: service iptables save
   # Ubuntu: apt install iptables-persistent && netfilter-persistent save
   ```

5. **启动 ocserv**
   ```bash
   systemctl start ocserv
   systemctl enable ocserv
   ```

6. **配置防火墙**
   ```bash
   # 宝塔面板安全 > 放行端口 443 (TCP+UDP)
   # 或者使用firewalld:
   firewall-cmd --permanent --add-port=443/tcp
   firewall-cmd --permanent --add-port=443/udp
   firewall-cmd --reload
   ```

### 第四步: 配置 ocserv 连接信息

编辑 `config/config.php`:
```php
define('OCSERV_HOST', 'your_vpn_server_ip');
define('OCSERV_PORT', 443);
define('OCSERV_PROTO', 'anyconnect');
```

## 使用流程

### 管理员操作
1. 登录管理后台 `/admin`
2. 在套餐管理中配置VPN套餐
3. 在卡密管理中生成卡密(可选, 用于线下销售)
4. 系统设置中配置站点信息

### 用户操作
1. 注册账号
2. 使用卡密充值余额
3. 选择套餐购买
4. 系统自动创建VPN账号(数字从1000开始)
5. 查看VPN连接信息(服务器地址、账号、密码)
6. 下载OpenConnect/Cisco AnyConnect客户端连接

## 客户端下载

- **OpenConnect** (免费, 推荐): https://www.openconnect-vpn.com/download/
- **Cisco AnyConnect**: 从应用商店下载

### 客户端配置
- 服务器地址: `你的VPN服务器IP`
- 端口: `443`
- 用户名: VPN账号 (数字, 如 `1001`)
- 密码: VPN密码 (系统生成)

## 目录结构

```
ocserv/
├── app/
│   ├── Controllers/        # 控制器
│   │   ├── HomeController.php    # 用户端控制器
│   │   └── AdminController.php   # 管理后台控制器
│   ├── Core/               # 核心库
│   │   ├── Auth.php              # 认证管理
│   │   ├── Database.php          # 数据库操作
│   │   ├── Router.php            # 路由器
│   │   ├── View.php              # 视图渲染
│   │   └── RadiusUiClient.php    # NETORA-Radius API客户端
│   ├── Middleware/         # 中间件
│   │   └── AuthMiddleware.php
│   ├── Services/           # 业务服务
│   │   ├── VpnAccountService.php # VPN账户服务
│   │   └── OrderService.php      # 订单和充值服务
│   ├── Views/              # 视图模板
│   │   ├── layouts/              # 布局
│   │   ├── auth/                 # 登录注册
│   │   ├── home/                 # 首页、套餐
│   │   ├── dashboard/            # 用户面板
│   │   └── admin/                # 管理后台
│   ├── bootstrap.php      # 引导文件
│   └── routes.php         # 路由定义
├── config/
│   └── config.sample.php  # 配置模板
├── database/
│   └── schema.sql         # 数据库结构
├── ocserv-config/         # ocserv和ToughRadius配置参考
├── cron/                  # 定时任务
│   └── check_expired.php
├── scripts/               # 工具脚本
│   └── reset_admin_password.php
├── public/                # Web根目录
│   ├── index.php          # 入口文件
│   ├── .htaccess
│   └── nginx.conf
├── storage/               # 存储目录
└── composer.json
```

## ToughRadius API 集成

本系统通过 ToughRadius 的 Admin REST API (`/api/v1`) 管理VPN用户:

| 操作 | ToughRadius API | 调用时机 |
|------|----------------|---------|
| 创建RADIUS用户 | `POST /api/v1/users` | 用户购买套餐时 |
| 更新到期时间 | `PUT /api/v1/users/:id` | 用户续费时 |
| 禁用用户 | `PUT /api/v1/users/:id` (status=disabled) | 账户过期或管理员禁用时 |
| 启用用户 | `PUT /api/v1/users/:id` (status=enabled) | 管理员启用时 |
| 重置密码 | `PUT /api/v1/users/:id` (password) | 用户或管理员重置密码时 |
| 查看在线会话 | `GET /api/v1/sessions/online` | 显示在线设备时 |

## 安全注意事项

1. **修改默认密钥** - `config.php` 中的 `HASH_KEY` 必须改为随机字符串
2. **HTTPS** - 建议在宝塔面板中配置SSL证书，强制HTTPS
3. **防火墙** - ToughRadius的Web管理端口(1816)不要对外开放，仅限本机访问
4. **数据库安全** - 数据库用户只授予对 `vpn_sales` 数据库的权限
5. **定期备份** - 定期备份MySQL数据库
6. **ToughRadius Web Secret** - 生产环境必须设置 `web.secret`

## 常见问题

### Q: ToughRadius API连接失败?
A: 检查以下几点:
- ToughRadius 是否正常运行
- `RADIUS_API_URL` 配置是否正确
- `RADIUS_API_USER` 和 `RADIUS_API_PASS` 是否正确
- ToughRadius 的 `web.secret` 是否已设置(生产模式必须)

### Q: VPN客户端无法连接?
A: 检查以下几点:
- ocserv 是否正常运行 (`systemctl status ocserv`)
- ToughRadius 是否正常接收认证请求
- ocserv 配置中的 `radius-server` 和 `radius-secret` 是否正确
- 防火墙是否放行了 443 端口 (TCP + UDP)
- IP转发是否开启 (`sysctl net.ipv4.ip_forward`)

### Q: 用户购买后VPN账号是什么?
A: VPN账号是自动生成的纯数字，从1000开始递增 (1000, 1001, 1002...)。密码是随机生成的12位字母数字组合。用户可以在"VPN账户"页面查看。

### Q: 如何修改套餐的速率限制?
A: 在管理后台 > 套餐管理中编辑套餐的上传/下载速率。注意：修改套餐只影响新购买的账户，已有账户续费时会同步新速率。要修改已有账户的速率，需要在ToughRadius管理界面中修改对应的计费策略(Profile)。

### Q: 账户过期后如何处理?
A: 系统会通过定时任务(每5分钟检查)自动禁用过期的VPN账户，同时在ToughRadius中也会禁用该用户。用户续费后系统会自动重新启用。

## License

MIT License - 可自由使用和修改
