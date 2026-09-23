#!/bin/bash
# ============================================================================
#  Incus 管理脚本（在计算节点上手动执行）
#  安装 / 卸载 Incus，并初始化默认网桥与 profile。
#  由主控端 /deploy/tools/incus-manage.sh 分发。
#
#  用法： curl -fsSL <主控地址>/deploy/tools/incus-manage.sh -o /tmp/incus-manage.sh && sudo bash /tmp/incus-manage.sh
#  可用环境变量：BRIDGE(默认 incusbr0)、IPV4(默认 10.88.0.1/16)、IPV6(默认 fd88:8888::1/64)
# ============================================================================
set -e

BRIDGE="${BRIDGE:-incusbr0}"
IPV4="${IPV4:-10.88.0.1/16}"
IPV6="${IPV6:-fd88:8888::1/64}"

echo "==> Incus 管理"
echo ""
echo "选择操作："
echo "1) 安装 Incus"
echo "2) 卸载 Incus"
read -p "请选择 (1/2) [1]: " action
action=${action:-1}

if [ "$action" = "2" ]; then
    read -p "确认卸载 Incus? 这将删除所有容器和数据 (yes/no): " confirm
    if [ "$confirm" = "yes" ]; then
        echo "==> 停止所有实例..."
        incus stop --all 2>/dev/null || true
        echo "==> 删除所有实例..."
        incus delete --all --force 2>/dev/null || true
        echo "==> 停止 Incus 服务..."
        systemctl stop incus 2>/dev/null || true
        systemctl stop incus.socket 2>/dev/null || true
        systemctl disable incus 2>/dev/null || true
        pkill -9 incus 2>/dev/null || true
        pkill -9 dnsmasq 2>/dev/null || true
        ip link delete "$BRIDGE" 2>/dev/null || true
        echo "==> 卸载 Incus..."
        apt remove -y incus qemu-system 2>/dev/null || true
        if [ -t 0 ]; then
            read -p "是否删除数据目录 /var/lib/incus? (yes/no): " del_data
        fi
        if [ "$del_data" = "yes" ]; then
            rm -rf /var/lib/incus
        fi
        rm -f /etc/apt/sources.list.d/incus.list
        rm -f /etc/apt/keyrings/zabbly.gpg
        echo "==> Incus 已卸载！"
    else
        echo "取消卸载"
    fi
    exit 0
fi

echo "==> 清理残留软件源..."
rm -f /etc/apt/sources.list.d/incus.list
rm -f /etc/apt/keyrings/zabbly.gpg

echo "==> 步骤 1: 安装必要工具..."
apt install -y gpg lsb-release curl

echo "==> 步骤 2: 添加 Zabbly GPG 密钥..."
mkdir -p /etc/apt/keyrings
curl -fsSL https://pkgs.zabbly.com/key.asc | gpg --dearmor -o /etc/apt/keyrings/zabbly.gpg

echo "==> 步骤 3: 添加 Zabbly 软件源..."
echo "deb [signed-by=/etc/apt/keyrings/zabbly.gpg] https://pkgs.zabbly.com/incus/stable $(lsb_release -cs) main" | tee /etc/apt/sources.list.d/incus.list

echo "==> 步骤 4: 更新软件包索引..."
apt update

echo "==> 步骤 5: 安装 Incus..."
if [ -e "/dev/kvm" ]; then
    read -p "检测到 KVM，是否同时安装 qemu-system 以运行虚拟机? (y/n) [n]: " install_qemu
    install_qemu=${install_qemu:-n}
    if [ "$install_qemu" = "y" ]; then
        apt install -y incus qemu-system
    else
        apt install -y incus
    fi
else
    echo "未检测到 KVM，跳过虚拟机支持组件。"
    apt install -y incus
fi

echo "==> 步骤 6: 启动 Incus 服务..."
systemctl start incus
systemctl enable incus
sleep 3

echo "==> 步骤 7: 初始化 Incus（网桥 $BRIDGE）..."
cat <<EOF | incus admin init --preseed
config:
  images.auto_update_interval: "0"
networks:
- config:
    ipv4.address: $IPV4
    ipv6.address: $IPV6
  name: $BRIDGE
  type: ""
  project: default
storage_pools: []
storage_volumes: []
profiles:
- config: {}
  description: ""
  devices:
    eth0:
      name: eth0
      network: $BRIDGE
      type: nic
  name: default
  project: default
projects: []
cluster: null
EOF

echo ""
echo "==> Incus 安装完成！"
echo "    网桥: $BRIDGE (IPv4 $IPV4 / IPv6 $IPV6)"
echo "    提示：在主控端节点配置里把「网关」填为 ${IPV4%%/*}、网桥填 $BRIDGE"
incus version
