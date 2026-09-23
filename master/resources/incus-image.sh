#!/bin/bash
# ============================================================================
#  Incus 镜像导入脚本（在计算节点上手动执行）
#  下载预制的 LXC / VM 镜像包并 `incus image import --alias` 导入本地。
#  导入后别名即是本地镜像名，面板「镜像」里把「镜像服务器」填 local、别名填该名称即可。
#  由主控端 /deploy/tools/incus-image.sh 分发。
#
#  用法： curl -fsSL <主控地址>/deploy/tools/incus-image.sh -o /tmp/incus-image.sh && sudo bash /tmp/incus-image.sh
#  可用环境变量：IMAGE_BASE_URL_LXC / IMAGE_BASE_URL_VM 覆盖镜像下载源
# ============================================================================
set -e

apt update -y 2>/dev/null || true
apt install curl -y 2>/dev/null || true

LXC_BASE_URL="${IMAGE_BASE_URL_LXC:-https://github.com/xkatld/vpsm-lxc-download/releases/download/v1.1.0}"
VM_BASE_URL="${IMAGE_BASE_URL_VM:-https://github.com/xkatld/vpsm-vm-download/releases/download/v1.1.0}"

ARCH=$(uname -m)
case "$ARCH" in
    x86_64) ARCH="amd64" ;;
    aarch64) ARCH="arm64" ;;
    *) echo "错误：不支持的架构 $ARCH"; exit 1 ;;
esac

echo "==> Incus 镜像管理  (架构: $ARCH)"
echo ""
echo "选择操作："
echo "1) 导入镜像"
echo "2) 列出本地镜像"
echo "3) 删除镜像"
read -p "请选择 [1]: " action
action=${action:-1}

if [ "$action" = "2" ]; then
    incus image list
    exit 0
fi

if [ "$action" = "3" ]; then
    incus image list
    read -p "输入要删除的镜像别名或指纹: " image_id
    if [ -z "$image_id" ]; then
        echo "错误：镜像 ID 不能为空"; exit 1
    fi
    incus image delete "$image_id"
    echo "==> 镜像已删除！"
    exit 0
fi

echo "选择虚拟化类型："
echo "1) LXC"
echo "2) VM"
virt_type=1
if [ -t 0 ]; then
    read -p "请选择 [1]: " virt_type_input
    virt_type=${virt_type_input:-1}
fi

if [ "$virt_type" = "2" ]; then
    TYPE="kvm"
    if [ "$ARCH" = "arm64" ]; then
        echo "错误：VM 镜像不支持 ARM64 架构"; exit 1
    fi
else
    TYPE="lxc"
fi

echo "选择镜像版本："
echo "1) all 预装常用软件包"
echo "2) lite 仅安装 SSH"
edition_type=1
if [ -t 0 ]; then
    read -p "请选择 [1]: " edition_type_input
    edition_type=${edition_type_input:-1}
fi
EDITION=$([ "$edition_type" = "2" ] && echo "lite" || echo "all")

IMAGE_LIST=(
    "almalinux8" "almalinux9" "almalinux10"
    "alpine321" "alpine322" "alpine323"
    "debian11" "debian12" "debian13"
    "rockylinux8" "rockylinux9" "rockylinux10"
    "ubuntu2204" "ubuntu2404" "ubuntu2604"
)

BASE_URL="$LXC_BASE_URL"
[ "$TYPE" = "kvm" ] && BASE_URL="$VM_BASE_URL"

echo "是否使用自定义 GitHub 加速下载头："
echo "1) 是   2) 否"
proxy_choice=2
if [ -t 0 ]; then
    read -p "请选择 [2]: " proxy_choice_input
    proxy_choice=${proxy_choice_input:-2}
fi
[ "$proxy_choice" != "1" ] && [ "$proxy_choice" != "2" ] && proxy_choice=2

proxy_prefix="https://proxy.gitwarp.top/"
if [ "$proxy_choice" = "1" ]; then
    read -p "请输入加速头 [默认 https://proxy.gitwarp.top/]: " proxy_prefix_input
    [ -n "$proxy_prefix_input" ] && proxy_prefix="$proxy_prefix_input"
    case "$proxy_prefix" in */) ;; *) proxy_prefix="${proxy_prefix}/" ;; esac
fi

echo ""
echo "可用镜像："
for i in "${!IMAGE_LIST[@]}"; do
    printf "%2d. %s\n" $((i+1)) "${IMAGE_LIST[$i]}"
done
echo " 0. 全部下载"
read -p "输入序号选择镜像，多选用空格分隔: " -a selections

if [ ${#selections[@]} -eq 0 ]; then
    echo "错误：未选择任何镜像"; exit 1
fi
if [[ " ${selections[@]} " =~ " 0 " ]]; then
    selections=()
    for i in "${!IMAGE_LIST[@]}"; do selections+=($((i+1))); done
    echo "==> 已选择全部 ${#selections[@]} 个镜像"
fi

for num in "${selections[@]}"; do
    if ! [[ "$num" =~ ^[0-9]+$ ]] || [ "$num" -lt 1 ] || [ "$num" -gt "${#IMAGE_LIST[@]}" ]; then
        echo "错误：无效的编号 $num"; exit 1
    fi
done

for num in "${selections[@]}"; do
    image_base="${IMAGE_LIST[$((num-1))]}"
    image_name="${image_base}-${EDITION}-${ARCH}-${TYPE}"
    raw_url="$BASE_URL/${image_name}.tar.gz"
    if [ "$proxy_choice" = "1" ]; then url="${proxy_prefix}${raw_url}"; else url="$raw_url"; fi

    echo ""
    echo "==> 下载镜像: $image_name"
    tmp_file="/tmp/${image_name}.tar.gz"
    if ! curl -L --progress-bar -o "$tmp_file" "$url"; then
        echo "警告：镜像 $image_name 下载失败，跳过"; rm -f "$tmp_file"; continue
    fi
    echo "==> 导入镜像到 Incus..."
    if incus image import "$tmp_file" --alias "$image_name" 2>/dev/null; then
        echo "==> 镜像 $image_name 导入成功！（面板里用 local:$image_name）"
    else
        echo "警告：镜像 $image_name 导入失败"
    fi
    rm -f "$tmp_file"
done

echo ""
echo "==> 完成！"
incus image list
