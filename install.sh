#!/usr/bin/env bash

set -Eeuo pipefail

# ============================================================
# Debian 12
# ocserv + radcli + RADIUS + nftables
#
# Designed for:
#   ocserv
#   radcli
#   TOUGHRADIUS / FreeRADIUS
#
# IMPORTANT:
#   This script installs the VPN access server only.
#   It does NOT modify your RADIUS server.
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

ok() {
    echo -e "${GREEN}[OK]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

fail() {
    echo -e "${RED}[ERROR]${NC} $1"
}

die() {
    fail "$1"
    exit 1
}

trap 'fail "脚本在第 ${LINENO} 行发生错误"; exit 1' ERR


# ============================================================
# Root check
# ============================================================

if [[ "$EUID" -ne 0 ]]; then
    die "请使用 root 用户执行。"
fi


# ============================================================
# Debian 12 check
# ============================================================

if [[ ! -f /etc/os-release ]]; then
    die "无法读取 /etc/os-release"
fi

source /etc/os-release

if [[ "${ID}" != "debian" ]]; then
    die "本脚本只支持 Debian。当前：${PRETTY_NAME}"
fi

if [[ "${VERSION_ID}" != "12" ]]; then
    die "本脚本只支持 Debian 12。当前：${PRETTY_NAME}"
fi


echo
echo "============================================================"
echo "       Debian 12 OCServ + RADIUS 安装程序 V3"
echo "============================================================"
echo


# ============================================================
# Detect IP
# ============================================================

PUBLIC_IP=""

if command -v curl >/dev/null 2>&1; then
    PUBLIC_IP="$(curl -4 -fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)"
fi

if [[ -z "$PUBLIC_IP" ]]; then
    PUBLIC_IP="$(hostname -I | awk '{print $1}')"
fi

info "公网 IP：${PUBLIC_IP}"


# ============================================================
# Detect WAN interface
# ============================================================

WAN_IF="$(ip route get 1.1.1.1 2>/dev/null | \
    awk '{
        for (i=1; i<=NF; i++) {
            if ($i=="dev") {
                print $(i+1)
                exit
            }
        }
    }')"

if [[ -z "$WAN_IF" ]]; then
    WAN_IF="$(ip route | awk '/default/ {print $5; exit}')"
fi

[[ -n "$WAN_IF" ]] || die "无法检测公网网卡。"

info "公网网卡：${WAN_IF}"


# ============================================================
# Input
# ============================================================

echo
echo "---------------- RADIUS 配置 ----------------"
echo

read -rp "TOUGHRADIUS服务器 IP/域名: " RADIUS_SERVER

[[ -n "$RADIUS_SERVER" ]] || \
    die "RADIUS服务器不能为空"

read -rp "RADIUS认证端口 [1812]: " RADIUS_AUTH_PORT
RADIUS_AUTH_PORT="${RADIUS_AUTH_PORT:-1812}"

read -rp "RADIUS Accounting端口 [1813]: " RADIUS_ACCT_PORT
RADIUS_ACCT_PORT="${RADIUS_ACCT_PORT:-1813}"

read -rsp "RADIUS Shared Secret: " RADIUS_SECRET
echo

[[ -n "$RADIUS_SECRET" ]] || \
    die "RADIUS Shared Secret 不能为空"

if [[ "$RADIUS_SECRET" =~ [[:space:]] ]]; then
    die "RADIUS Shared Secret 不能包含空格。"
fi


echo
echo "---------------- VPN 配置 ----------------"
echo

read -rp "VPN域名/IP [${PUBLIC_IP}]: " VPN_HOST
VPN_HOST="${VPN_HOST:-$PUBLIC_IP}"

read -rp "VPN端口 [443]: " VPN_PORT
VPN_PORT="${VPN_PORT:-443}"

read -rp "VPN虚拟网段 [10.10.10.0/24]: " VPN_NETWORK
VPN_NETWORK="${VPN_NETWORK:-10.10.10.0/24}"

read -rp "VPN DNS [1.1.1.1]: " VPN_DNS
VPN_DNS="${VPN_DNS:-1.1.1.1}"


echo
echo "---------------- SSH 配置 ----------------"
echo

read -rp "SSH端口 [22]: " SSH_PORT
SSH_PORT="${SSH_PORT:-22}"


# ============================================================
# Validate values
# ============================================================

if ! [[ "$RADIUS_AUTH_PORT" =~ ^[0-9]+$ ]]; then
    die "RADIUS认证端口无效"
fi

if ! [[ "$RADIUS_ACCT_PORT" =~ ^[0-9]+$ ]]; then
    die "RADIUS Accounting端口无效"
fi

if ! [[ "$VPN_PORT" =~ ^[0-9]+$ ]]; then
    die "VPN端口无效"
fi

if ! [[ "$SSH_PORT" =~ ^[0-9]+$ ]]; then
    die "SSH端口无效"
fi

if [[ "$VPN_NETWORK" != */24 ]]; then
    die "为了避免网络计算错误，目前只支持 /24 VPN网段，例如 10.10.10.0/24"
fi


VPN_NET="${VPN_NETWORK%/24}"
VPN_NETMASK="255.255.255.0"


# ============================================================
# Confirmation
# ============================================================

echo
echo "============================================================"
echo "配置确认"
echo "============================================================"
echo
echo "公网 IP       : ${PUBLIC_IP}"
echo "公网网卡      : ${WAN_IF}"
echo
echo "RADIUS Server : ${RADIUS_SERVER}"
echo "RADIUS Auth   : ${RADIUS_AUTH_PORT}"
echo "RADIUS Acct   : ${RADIUS_ACCT_PORT}"
echo
echo "VPN Host      : ${VPN_HOST}"
echo "VPN Port      : ${VPN_PORT}"
echo "VPN Network   : ${VPN_NETWORK}"
echo "VPN DNS       : ${VPN_DNS}"
echo
echo "SSH Port      : ${SSH_PORT}"
echo
echo "============================================================"
echo

read -rp "确认开始安装？[y/N]: " CONFIRM

if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo "已取消。"
    exit 0
fi


# ============================================================
# Backup
# ============================================================

BACKUP_DIR="/root/ocserv-backup-$(date +%Y%m%d-%H%M%S)"

mkdir -p "$BACKUP_DIR"

if [[ -f /etc/ocserv/ocserv.conf ]]; then
    cp -a /etc/ocserv/ocserv.conf "$BACKUP_DIR/"
fi

if [[ -d /etc/radcli ]]; then
    cp -a /etc/radcli "$BACKUP_DIR/"
fi

if [[ -f /etc/nftables.conf ]]; then
    cp -a /etc/nftables.conf "$BACKUP_DIR/"
fi

ok "配置备份：${BACKUP_DIR}"


# ============================================================
# APT
# ============================================================

info "更新软件包列表..."

export DEBIAN_FRONTEND=noninteractive

apt-get update


# ============================================================
# Install packages
# ============================================================

info "安装软件..."

apt-get install -y \
    ocserv \
    libradcli4 \
    nftables \
    curl \
    ca-certificates \
    openssl \
    iproute2 \
    iptables \
    net-tools \
    procps

ok "软件安装完成"


# ============================================================
# Check ocserv
# ============================================================

if ! command -v ocserv >/dev/null 2>&1; then
    die "ocserv 安装失败"
fi

OCSERV_VERSION="$(ocserv --version 2>&1 | head -n 1 || true)"

ok "OCServ：${OCSERV_VERSION}"


# ============================================================
# Check radcli
# ============================================================

if ! dpkg -s libradcli4 >/dev/null 2>&1; then
    die "libradcli4 安装失败"
fi

RADCLI_VERSION="$(dpkg-query -W -f='${Version}' libradcli4)"

ok "radcli：${RADCLI_VERSION}"


# ============================================================
# Check /etc/radcli
# ============================================================

mkdir -p /etc/radcli


# ------------------------------------------------------------
# Locate dictionary
# ------------------------------------------------------------

if [[ -f /etc/radcli/dictionary ]]; then

    ok "发现 /etc/radcli/dictionary"

else

    DICT_FILE=""

    for f in \
        /usr/share/radcli/dictionary \
        /usr/share/radius/dictionary \
        /etc/radcli/dictionary
    do
        if [[ -f "$f" ]]; then
            DICT_FILE="$f"
            break
        fi
    done

    if [[ -n "$DICT_FILE" ]]; then
        cp "$DICT_FILE" /etc/radcli/dictionary
        ok "已复制 dictionary：${DICT_FILE}"
    else
        die "找不到 radcli dictionary"
    fi

fi


# ============================================================
# Configure radcli
# ============================================================

info "配置 radcli..."


cat > /etc/radcli/radiusclient.conf <<EOF
#
# radcli configuration for ocserv
#

nas-identifier ocserv

authserver ${RADIUS_SERVER}:${RADIUS_AUTH_PORT}
acctserver ${RADIUS_SERVER}:${RADIUS_ACCT_PORT}

servers /etc/radcli/servers
dictionary /etc/radcli/dictionary

default_realm

radius_timeout 10
radius_retries 3

bindaddr *
EOF


chmod 644 /etc/radcli/radiusclient.conf


# ------------------------------------------------------------
# RADIUS server secret
# ------------------------------------------------------------

cat > /etc/radcli/servers <<EOF
${RADIUS_SERVER} ${RADIUS_SECRET}
EOF

chmod 600 /etc/radcli/servers


ok "radcli 配置完成"


# ============================================================
# Generate SSL certificate
# ============================================================

if [[ ! -f /etc/ocserv/server-cert.pem ]] || \
   [[ ! -f /etc/ocserv/server-key.pem ]]; then

    info "生成测试 SSL 证书..."

    openssl req \
        -x509 \
        -nodes \
        -newkey rsa:2048 \
        -keyout /etc/ocserv/server-key.pem \
        -out /etc/ocserv/server-cert.pem \
        -days 3650 \
        -subj "/CN=${VPN_HOST}"

    chmod 600 /etc/ocserv/server-key.pem
    chmod 644 /etc/ocserv/server-cert.pem

    ok "SSL证书生成完成"

else

    ok "检测到已有 SSL证书，不覆盖"

fi


# ============================================================
# OCServ directories
# ============================================================

mkdir -p /etc/ocserv/config-per-user
mkdir -p /etc/ocserv/config-per-group


# ============================================================
# OCServ configuration
# ============================================================

info "生成 ocserv.conf..."


cat > /etc/ocserv/ocserv.conf <<EOF
#
# ============================================================
# OCServ + RADIUS
# Debian 12
# ============================================================

# ------------------------------------------------------------
# RADIUS Authentication
# ------------------------------------------------------------

auth = "radius[config=/etc/radcli/radiusclient.conf,groupconfig=true]"

# ------------------------------------------------------------
# RADIUS Accounting
# ------------------------------------------------------------

acct = "radius[config=/etc/radcli/radiusclient.conf]"

# Accounting interim update interval
stats-report-time = 300

# ------------------------------------------------------------
# VPN ports
# ------------------------------------------------------------

tcp-port = ${VPN_PORT}
udp-port = ${VPN_PORT}

# ------------------------------------------------------------
# VPN device
# ------------------------------------------------------------

device = vpns

# ------------------------------------------------------------
# VPN network
# ------------------------------------------------------------

ipv4-network = ${VPN_NET}.0
ipv4-netmask = ${VPN_NETMASK}

# ------------------------------------------------------------
# DNS
# ------------------------------------------------------------

dns = ${VPN_DNS}

# Route all traffic through VPN
route = default

# ------------------------------------------------------------
# SSL
# ------------------------------------------------------------

server-cert = /etc/ocserv/server-cert.pem
server-key = /etc/ocserv/server-key.pem

# ------------------------------------------------------------
# Process
# ------------------------------------------------------------

run-as-user = nobody
run-as-group = nogroup

socket-file = /run/ocserv-socket

# ------------------------------------------------------------
# Client limits
# ------------------------------------------------------------

max-clients = 1024
max-same-clients = 2

# ------------------------------------------------------------
# Keepalive
# ------------------------------------------------------------

keepalive = 300
dpd = 60
mobile-dpd = 300

switch-to-tcp-timeout = 25

try-mtu-discovery = true

mtu = 1420

# ------------------------------------------------------------
# Authentication timeout
# ------------------------------------------------------------

auth-timeout = 240

cookie-timeout = 300

idle-timeout = 1200

mobile-idle-timeout = 1800

min-reauth-time = 300

# ------------------------------------------------------------
# Security
# ------------------------------------------------------------

compression = false

cisco-client-compat = true

# ------------------------------------------------------------
# Logging
# ------------------------------------------------------------

log-syslog = true

log-level = 2

EOF


ok "ocserv.conf 已生成"


# ============================================================
# IPv4 forwarding
# ============================================================

info "开启 IPv4 转发..."

cat > /etc/sysctl.d/99-ocserv.conf <<EOF
net.ipv4.ip_forward=1
EOF

sysctl --system >/dev/null

if [[ "$(sysctl -n net.ipv4.ip_forward)" != "1" ]]; then
    die "IPv4 转发开启失败"
fi

ok "IPv4 转发已开启"


# ============================================================
# nftables
# ============================================================

info "配置 nftables..."


cat > /etc/nftables.conf <<EOF
#!/usr/sbin/nft -f

flush ruleset


table inet ocserv_filter {

    chain input {
        type filter hook input priority 0;
        policy drop;

        # Loopback
        iifname "lo" accept;

        # Established / related
        ct state established,related accept;

        # ICMP
        ip protocol icmp accept;

        # SSH
        tcp dport ${SSH_PORT} accept;

        # OCServ TCP
        tcp dport ${VPN_PORT} accept;

        # OCServ UDP / DTLS
        udp dport ${VPN_PORT} accept;
    }


    chain forward {
        type filter hook forward priority 0;
        policy drop;

        # Established connections
        ct state established,related accept;

        # VPN -> Internet
        ip saddr ${VPN_NETWORK} oifname "${WAN_IF}" accept;

        # Internet -> VPN replies
        ip daddr ${VPN_NETWORK} iifname "${WAN_IF}" ct state established,related accept;
    }


    chain output {
        type filter hook output priority 0;
        policy accept;
    }
}


table ip ocserv_nat {

    chain postrouting {
        type nat hook postrouting priority 100;
        policy accept;

        ip saddr ${VPN_NETWORK} oifname "${WAN_IF}" masquerade;
    }
}

EOF


# ============================================================
# Validate nftables BEFORE applying
# ============================================================

info "检查 nftables 配置语法..."

if ! nft -c -f /etc/nftables.conf; then
    die "nftables 配置检查失败，未启动防火墙。"
fi

ok "nftables 语法检查通过"


# ============================================================
# Enable nftables
# ============================================================

systemctl enable nftables

systemctl restart nftables

if ! systemctl is-active --quiet nftables; then
    die "nftables 启动失败"
fi

ok "nftables 已启动"


# ============================================================
# Verify NAT
# ============================================================

if ! nft list table ip ocserv_nat >/dev/null 2>&1; then
    die "NAT规则没有成功加载"
fi

ok "NAT规则已加载"


# ============================================================
# OCServ config validation
# ============================================================

info "检查 ocserv 配置..."

if ! ocserv -t -c /etc/ocserv/ocserv.conf; then

    echo
    echo "============================================================"
    echo "OCServ 配置检查失败"
    echo "============================================================"
    echo

    ocserv -t -c /etc/ocserv/ocserv.conf || true

    exit 1
fi

ok "ocserv 配置检查通过"


# ============================================================
# Enable and start ocserv
# ============================================================

systemctl daemon-reload

systemctl enable ocserv

info "启动 ocserv..."

systemctl restart ocserv

sleep 3


if ! systemctl is-active --quiet ocserv; then

    echo
    echo "============================================================"
    echo "OCServ 启动失败"
    echo "============================================================"
    echo

    systemctl status ocserv --no-pager || true

    echo
    echo "最近日志："
    journalctl -u ocserv -n 100 --no-pager || true

    exit 1
fi


ok "ocserv 已正常运行"


# ============================================================
# Create management command
# ============================================================

cat > /usr/local/bin/ocvpn <<'EOF'
#!/usr/bin/env bash

case "${1:-}" in

    status)

        echo
        echo "========== OCServ =========="
        systemctl status ocserv --no-pager

        echo
        echo "========== Listening =========="

        ss -lntup | grep -E ':(443|4443)\b' || true

        echo
        echo "========== RADIUS =========="

        grep -E '^(authserver|acctserver|servers|dictionary)' \
            /etc/radcli/radiusclient.conf || true

        ;;


    test)

        echo
        echo "========== OCServ Test =========="
        echo

        echo "[1] OCServ:"
        ocserv --version || true

        echo
        echo "[2] radcli:"
        dpkg-query -W -f='${Package} ${Version}\n' libradcli4 \
            2>/dev/null || true

        echo
        echo "[3] RADIUS config:"
        grep -E '^(authserver|acctserver|servers|dictionary)' \
            /etc/radcli/radiusclient.conf || true

        echo
        echo "[4] RADIUS server:"
        sed 's/ .*/ ********/' /etc/radcli/servers || true

        echo
        echo "[5] OCServ config:"
        ocserv -t -c /etc/ocserv/ocserv.conf

        echo
        echo "[6] nftables:"
        nft list table inet ocserv_filter

        echo
        echo "[7] NAT:"
        nft list table ip ocserv_nat

        echo
        echo "[8] IP forwarding:"
        sysctl net.ipv4.ip_forward

        echo
        echo "测试完成。"

        ;;


    restart)

        ocserv -t -c /etc/ocserv/ocserv.conf

        systemctl restart ocserv

        echo "OCServ 已重启。"

        ;;


    start)

        systemctl start ocserv

        ;;


    stop)

        systemctl stop ocserv

        ;;


    logs)

        journalctl -u ocserv -f

        ;;


    logs100)

        journalctl -u ocserv -n 100 --no-pager

        ;;


    connections)

        echo
        echo "========== Network Connections =========="
        ss -tunp | grep ocserv || true

        echo
        echo "========== OCCTL =========="
        occtl show users 2>/dev/null || true

        ;;


    firewall)

        nft list ruleset

        ;;


    radius)

        echo
        echo "========== RADIUS CONFIG =========="
        cat /etc/radcli/radiusclient.conf

        echo
        echo "========== RADIUS SERVERS =========="
        sed 's/ .*/ ********/' /etc/radcli/servers

        ;;


    config)

        nano /etc/ocserv/ocserv.conf

        ;;


    version)

        ocserv --version

        ;;


    *)

        echo
        echo "================================================"
        echo " OCServ 管理工具"
        echo "================================================"
        echo
        echo "ocvpn status"
        echo "    查看服务状态"
        echo
        echo "ocvpn test"
        echo "    全面检查配置"
        echo
        echo "ocvpn connections"
        echo "    查看VPN连接"
        echo
        echo "ocvpn logs"
        echo "    实时查看日志"
        echo
        echo "ocvpn logs100"
        echo "    查看最近100条日志"
        echo
        echo "ocvpn restart"
        echo "    重启OCServ"
        echo
        echo "ocvpn config"
        echo "    编辑OCServ配置"
        echo
        echo "ocvpn radius"
        echo "    查看RADIUS配置"
        echo
        echo "ocvpn firewall"
        echo "    查看防火墙"
        echo
        echo "================================================"

        ;;

esac
EOF


chmod 755 /usr/local/bin/ocvpn


# ============================================================
# Final check
# ============================================================

echo
echo "============================================================"
echo "              安装成功"
echo "============================================================"
echo
echo "VPN 地址       : ${VPN_HOST}"
echo "VPN TCP        : ${VPN_PORT}"
echo "VPN UDP        : ${VPN_PORT}"
echo "VPN 网段       : ${VPN_NETWORK}"
echo "VPN DNS        : ${VPN_DNS}"
echo "公网网卡       : ${WAN_IF}"
echo
echo "RADIUS Server  : ${RADIUS_SERVER}"
echo "RADIUS Auth    : ${RADIUS_AUTH_PORT}"
echo "RADIUS Acct    : ${RADIUS_ACCT_PORT}"
echo
echo "============================================================"
echo
echo "请先执行："
echo
echo "    ocvpn test"
echo
echo "然后："
echo
echo "    ocvpn status"
echo
echo "查看日志："
echo
echo "    ocvpn logs"
echo
echo "============================================================"
echo
echo "下一步需要在 TOUGHRADIUS 中："
echo
echo "1. 添加本机公网IP为 RADIUS Client/NAS"
echo "2. 设置相同的 Shared Secret"
echo "3. 创建测试用户"
echo "4. 使用 OpenConnect 客户端测试"
echo
echo "============================================================"
