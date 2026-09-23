#!/usr/bin/env bash
# ============================================================================
#  Incus 云控制台 · 节点端安装脚本（从 GitHub 拉取，无需面板参与）
#
#  用法（在计算节点上以 root 执行）：
#     curl -fsSL https://raw.githubusercontent.com/ruyawangluo/ruyavps/main/install/agent-install.sh -o /tmp/agent-install.sh && sudo bash /tmp/agent-install.sh
#
#  安装内容：被控程序 + 本地 SQLite 库 + 本地控制 API（systemd 常驻）。
#  完成后会打印节点的「IP / 端口 / KEY」，拿到面板端「计算节点 → 接入节点」填入即可对接。
#  不需要面板地址、不需要数据库账号（本地自带 SQLite）。
#
#  Incus 不在此脚本安装 —— 请随后单独执行（同样从 GitHub 拉取）：
#     scripts/incus-manage.sh   安装 Incus 与网桥
#     scripts/incus-storage.sh  创建存储池
#     scripts/incus-image.sh    导入镜像
#
#  可用环境变量：NODE_PORT(默认 8787)、REPO_ARCHIVE(默认 GitHub main 归档)
# ============================================================================
set -euo pipefail

DEST="/opt/incus-node"
NODE_PORT="${NODE_PORT:-8787}"
REPO_RAW="${REPO_RAW:-https://raw.githubusercontent.com/ruyawangluo/ruyavps/main}"
REPO_ARCHIVE="${REPO_ARCHIVE:-https://github.com/ruyawangluo/ruyavps/archive/refs/heads/main.tar.gz}"

c() { printf '\033[1;36m%s\033[0m\n' "$*"; }
ok() { printf '\033[1;32m%s\033[0m\n' "$*"; }

[ "$(id -u)" = "0" ] || { echo "请以 root 运行（使用 sudo）" >&2; exit 1; }

c "==> [1/5] 安装依赖 (php-cli / php-sqlite3 / curl)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y php-cli php-sqlite3 php-curl curl ca-certificates

c "==> [2/5] 从 GitHub 下载节点端程序"
TMP="$(mktemp -d /tmp/incus-node.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
curl -fsSL "$REPO_ARCHIVE" -o "$TMP/repo.tar.gz"
tar -xzf "$TMP/repo.tar.gz" -C "$TMP"
SRC="$(find "$TMP" -maxdepth 1 -type d -name 'ruyavps-*' | head -1)"
[ -n "$SRC" ] && [ -d "$SRC/agent" ] || { echo "程序包结构异常（未找到 agent/）" >&2; exit 1; }

c "==> [3/5] 部署到 ${DEST}"
mkdir -p "$DEST"
cp -r "$SRC/agent/." "$DEST/"
rm -f "$DEST/config.php"

NODE_KEY="$(openssl rand -hex 24 2>/dev/null || head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
cat > "${DEST}/config.php" <<PHP
<?php
return [
    'node_name'    => '$(hostname)',
    'node_ip'      => '',
    'api_bind'     => '0.0.0.0',
    'api_port'     => ${NODE_PORT},
    'node_key'     => '${NODE_KEY}',
    'db_file'      => '${DEST}/node.db',
    'incus_bin'    => 'incus',
    'storage_pool' => 'default',
    'bridge'       => 'incusbr0',
    'instance_prefix' => 'c-',
    'log_file'     => '${DEST}/node.log',
];
PHP

c "==> [4/5] 初始化本地数据库"
php -r "require '${DEST}/lib/Db.php'; \Agent\Db::connect('${DEST}/node.db'); echo \"ok\n\";"

c "==> [5/5] 安装并启动 systemd 服务"
cat > /etc/systemd/system/incus-node-api.service <<UNIT
[Unit]
Description=Incus Node API
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
WorkingDirectory=${DEST}
ExecStart=/usr/bin/php -S 0.0.0.0:${NODE_PORT} ${DEST}/server.php
Restart=always
RestartSec=5
User=root
StandardOutput=append:/var/log/incus-node.log
StandardError=append:/var/log/incus-node.log

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now incus-node-api

sleep 2
NODE_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
echo
ok "============================================================"
ok " 节点端安装完成 —— 拿去面板端「计算节点 → 接入节点」对接："
echo "   IP   : ${NODE_IP}"
echo "   端口 : ${NODE_PORT}"
echo "   KEY  : ${NODE_KEY}"
echo "============================================================"
echo " 下一步（可选，Incus 单独配置，均从 GitHub 拉取）："
echo "   curl -fsSL ${REPO_RAW}/scripts/incus-manage.sh  -o /tmp/i.sh && bash /tmp/i.sh   # 安装 Incus"
echo "   curl -fsSL ${REPO_RAW}/scripts/incus-storage.sh -o /tmp/i.sh && bash /tmp/i.sh   # 存储池"
echo "   curl -fsSL ${REPO_RAW}/scripts/incus-image.sh   -o /tmp/i.sh && bash /tmp/i.sh   # 镜像"
echo " 日志： journalctl -u incus-node-api -f"
