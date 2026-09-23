#!/bin/bash
# ============================================================================
#  Incus 存储池管理脚本（在计算节点上手动执行）
#  创建 / 删除 / 列出存储池（ZFS 或 Btrfs）。
#  由主控端 /deploy/tools/incus-storage.sh 分发。
#
#  用法： curl -fsSL <主控地址>/deploy/tools/incus-storage.sh -o /tmp/incus-storage.sh && sudo bash /tmp/incus-storage.sh
# ============================================================================
set -e

echo "==> Incus 存储池管理"
echo ""
echo "选择操作："
echo "1) 创建存储池"
echo "2) 删除存储池"
echo "3) 列出存储池"
read -p "请选择 (1/2/3) [1]: " action
action=${action:-1}

if [ "$action" = "3" ]; then
    incus storage list
    exit 0
fi

if [ "$action" = "2" ]; then
    incus storage list
    read -p "输入要删除的存储池名称: " pool_name
    if [ -z "$pool_name" ]; then
        echo "错误：存储池名称不能为空"; exit 1
    fi
    read -p "确认删除存储池 '$pool_name'? (yes/no): " confirm
    if [ "$confirm" = "yes" ]; then
        incus storage delete "$pool_name"
        echo "==> 存储池已删除！"
    else
        echo "取消删除"
    fi
    exit 0
fi

echo "==> 选择存储驱动："
echo "1) ZFS"
echo "2) Btrfs"
read -p "请选择 (1/2) [1]: " driver_choice
driver_choice=${driver_choice:-1}

if [ "$driver_choice" = "1" ]; then
    driver="zfs"
    if ! command -v zfs >/dev/null 2>&1; then
        os_id=""
        [ -r /etc/os-release ] && . /etc/os-release && os_id="${ID,,}"
        case "$os_id" in
            debian)
                if ! grep -q "contrib" /etc/apt/sources.list.d/debian.sources 2>/dev/null; then
                    sed -i 's/Components: main/Components: main contrib non-free non-free-firmware/' /etc/apt/sources.list.d/debian.sources 2>/dev/null || true
                fi
                apt update >/dev/null 2>&1
                apt install -y linux-headers-$(uname -r) zfsutils-linux zfs-dkms || { echo "错误：ZFS 安装失败"; exit 1; }
                ;;
            ubuntu)
                apt update >/dev/null 2>&1
                apt install -y zfsutils-linux || { echo "错误：ZFS 安装失败"; exit 1; }
                ;;
            *) echo "错误：当前系统不支持自动安装 ZFS: $os_id"; exit 1 ;;
        esac
    fi
else
    driver="btrfs"
    command -v btrfs >/dev/null 2>&1 || apt install -y btrfs-progs || { echo "错误：Btrfs 安装失败"; exit 1; }
fi

if ! incus storage list -f csv | grep -q "^default,"; then
    default_name="default"
else
    i=1
    while incus storage list -f csv | grep -q "^pool$i,"; do i=$((i + 1)); done
    default_name="pool$i"
fi

read -p "存储池名称 [$default_name]: " pool_name
pool_name=${pool_name:-$default_name}

echo "选择存储方式："
echo "1) 自动创建（从根分区划出空间）"
echo "2) 使用独立磁盘"
storage_type=1
if [ -t 0 ]; then
    read -p "请选择 (1/2) [1]: " storage_type_input
    storage_type=${storage_type_input:-1}
fi

if [ "$storage_type" = "2" ]; then
    lsblk -d -n -o NAME,SIZE,TYPE | grep disk
    read -p "输入磁盘设备 (如 sdb): " device
    incus storage create "$pool_name" "$driver" source="/dev/$device" source.wipe=true
else
    available=$(df /var/lib/incus 2>/dev/null | awk 'NR==2 {print int($4/1024/1024)}')
    default_size=$((available - 5))
    [ "$default_size" -lt 10 ] && default_size=10
    read -p "存储池大小 GB [$default_size]: " size
    size=${size:-$default_size}
    incus storage create "$pool_name" "$driver" size="${size}GB"
fi

echo "==> 存储池创建成功！"
incus storage list
