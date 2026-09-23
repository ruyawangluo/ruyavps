#!/usr/bin/env bash
# ============================================================================
#  节点端安装脚本（计算节点）
#
#  本脚本由主控端 /deploy/agent.sh 动态提供（已内嵌主控地址）。
#  在计算节点上以 root 执行：
#
#    curl -fsSL <主控地址>/deploy/agent.sh | sudo bash
#
#  安装内容：被控程序 + 本地 SQLite 库 + 本地控制 API（systemd 常驻）。
#  安装完成后会打印节点的「IP / 端口 / KEY」，用于在主控端「计算节点」里接入。
#
#  Incus 不在此脚本中安装 —— 请随后单独执行主控端提供的 Incus 工具脚本：
#    /deploy/tools/incus-manage.sh   安装 Incus 与网桥
#    /deploy/tools/incus-storage.sh  创建存储池
#    /deploy/tools/incus-image.sh    导入镜像
# ============================================================================
set -euo pipefail

MASTER_URL="__MASTER_URL__"
DEST="/opt/incus-node"
NODE_PORT="${NODE_PORT:-8787}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "请以 root 运行（使用 sudo）" >&2
  exit 1
fi

echo "==> [1/5] 安装依赖 (php-cli / php-sqlite3 / curl)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y php-cli php-sqlite3 php-curl curl ca-certificates

echo "==> [2/5] 下载被控程序"
mkdir -p "${DEST}"
TMP="$(mktemp /tmp/incus-node.XXXXXX.tar)"
curl -fsSL "${MASTER_URL}/deploy/agent.tar" -o "${TMP}"
tar -xf "${TMP}" -C "${DEST}"
rm -f "${TMP}"

echo "==> [3/5] 生成节点密钥与配置"
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

echo "==> [4/5] 初始化本地数据库"
php -r "require '${DEST}/lib/Db.php'; \Agent\Db::connect('${DEST}/node.db'); echo \"ok\n\";"

echo "==> [5/5] 安装并启动 systemd 服务"
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
echo ""
echo "============================================================"
echo " 节点端安装完成"
echo "------------------------------------------------------------"
echo " 节点接入信息（填入主控端「计算节点」）："
echo "   IP   : ${NODE_IP}"
echo "   端口 : ${NODE_PORT}"
echo "   KEY  : ${NODE_KEY}"
echo "============================================================"
echo " 下一步（可选，Incus 单独配置）："
echo "   curl -fsSL ${MASTER_URL}/deploy/tools/incus-manage.sh  -o /tmp/i.sh && bash /tmp/i.sh   # 安装 Incus"
echo "   curl -fsSL ${MASTER_URL}/deploy/tools/incus-storage.sh -o /tmp/i.sh && bash /tmp/i.sh   # 存储池"
echo "   curl -fsSL ${MASTER_URL}/deploy/tools/incus-image.sh   -o /tmp/i.sh && bash /tmp/i.sh   # 镜像"
echo " 日志： journalctl -u incus-node-api -f"
