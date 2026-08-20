#!/bin/bash
# ocserv + NETORA-Radius 安装辅助脚本
# NETORA-Radius: https://github.com/desienkz-slp/radius-ui

set -euo pipefail

if [ "$(id -u)" != "0" ]; then
    echo "错误: 请使用 root 用户运行"
    exit 1
fi

if [ ! -f /etc/os-release ]; then
    echo "错误: 无法检测操作系统"
    exit 1
fi

. /etc/os-release
if [ "$ID" != "ubuntu" ]; then
    echo "错误: NETORA-Radius 官方安装器要求全新 Ubuntu 20.04/22.04"
    exit 1
fi

case "$VERSION_ID" in
    20.04|22.04) ;;
    *) echo "错误: 当前 Ubuntu $VERSION_ID 不在官方支持列表中"; exit 1 ;;
esac

apt update
apt install -y git curl ocserv freeradius-utils

RADIUS_DIR="/var/www/radius-ui"
if [ ! -d "$RADIUS_DIR/.git" ]; then
    git clone https://github.com/desienkz-slp/radius-ui.git "$RADIUS_DIR"
fi

cd "$RADIUS_DIR"
chmod +x install.sh
cat <<'NOTICE'
即将运行 NETORA-Radius 官方安装器。
注意: 官方安装器会安装 Nginx、MariaDB、Node.js、FreeRADIUS、WireGuard 和 L2TP，
建议只在全新的 Ubuntu 服务器上执行。
NOTICE
./install.sh

cat <<'NEXT'

NETORA-Radius 已安装。接下来请完成：
1. 浏览器打开服务器 IP，以 superadmin / admin123 登录并立即修改密码。
2. 在 NAS 页面添加 ocserv 服务器 IP 和共享密钥。
3. 创建名为 default 的 Profile。
4. 编辑 /etc/ocserv/ocserv.conf，启用 RADIUS：
     auth = "radius[config=/etc/radcli/radiusclient.conf,groupconfig=true]"
5. 在 radcli 配置中将 authserver/acctserver 指向 NETORA-Radius 的 FreeRADIUS，
   并在 servers 文件中填写与 NAS 页面一致的共享密钥。
6. 重启 ocserv：systemctl restart ocserv
7. 在商城 config/config.php 中配置 RADIUS_API_URL、RADIUS_API_TOKEN、RADIUS_PROFILE。

API 默认由 Node 服务监听 3001 端口。请勿将 3001 暴露给公网；仅允许商城服务器访问。
NEXT
