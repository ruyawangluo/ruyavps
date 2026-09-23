#!/usr/bin/env bash
# ============================================================================
#  Incus 云控制台 · 面板端安装 / 升级脚本（仅适配 1Panel）
#
#  用法（可直接从 GitHub 拉取执行，无需先把仓库放到服务器）：
#     curl -fsSL https://raw.githubusercontent.com/ruyawangluo/ruyavps/main/install/install-master.sh -o /tmp/install-master.sh && sudo bash /tmp/install-master.sh
#     sudo bash install-master.sh update      # 升级（覆盖程序，保留 config.php）
#
#  前置环境（本脚本不负责安装 Web 环境）：
#     1) 已安装 1Panel，并在「应用商店」安装：OpenResty、PHP 8+、MySQL
#     2) PHP 8 已启用扩展：pdo_mysql、mbstring、curl、openssl
#     3) 已在 1Panel 新建站点，且站点目录为：
#          /opt/1panel/www/sites/ruyavps/index        （本站固定目录，代号 ruyavps）
#     4) 已在 1Panel 建好 MySQL 数据库与账号（本脚本只导入表结构，不建库）
#     5) 用 root（sudo）执行
#
#  本脚本会：从 GitHub 下载程序包 → 解压到网站目录 → 对接已建好的数据库并导入结构
#            → 写 config.php → 建管理员账号 → 提示你去 1Panel 改运行目录并访问站点。
#
#  可用环境变量免交互：DB_HOST DB_PORT DB_NAME DB_USER DB_PASS SITE_URL ADMIN_USER ADMIN_PASS
#                      REPO_ARCHIVE（默认 GitHub main 分支归档）
# ============================================================================
set -euo pipefail

MODE="${1:-install}"
SITE_DIR="/opt/1panel/www/sites/ruyavps/index"          # 固定目录（仅适配 1Panel）
REPO_ARCHIVE="${REPO_ARCHIVE:-https://github.com/ruyawangluo/ruyavps/archive/refs/heads/main.tar.gz}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
DB_PASS="${DB_PASS:-}"
SITE_URL="${SITE_URL:-}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-}"

c() { printf '\033[1;36m%s\033[0m\n' "$*"; }
ok() { printf '\033[1;32m%s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m%s\033[0m\n' "$*"; }
err() { printf '\033[1;31m%s\033[0m\n' "$*" >&2; }

ask() { # ask VAR "提示" "默认值"
  local var="$1" prompt="$2" def="${3:-}" cur val
  cur="$(eval "echo \"\${$var:-}\"")"
  if [ -n "$cur" ]; then return; fi
  if [ -n "$def" ]; then read -r -p "$prompt [$def]: " val; val="${val:-$def}"; else read -r -p "$prompt: " val; fi
  eval "$var=\"\$val\""
}

[ "$(id -u)" = "0" ] || { err "请用 root 运行（sudo bash install-master.sh）"; exit 1; }
[ "$MODE" = "uninstall" ] && { warn "卸载请自行删除 ${SITE_DIR} 并 drop 数据库。"; exit 0; }

# ---------------------------------------------------------------------------
c "==> 环境检查"
command -v curl >/dev/null 2>&1 || { err "缺少 curl。"; exit 1; }
command -v php  >/dev/null 2>&1 || { err "未找到 php，请先在 1Panel 安装 PHP 8+。"; exit 1; }
command -v mysql>/dev/null 2>&1 || { err "未找到 mysql 客户端，请先安装 MySQL。"; exit 1; }
PHP_VER="$(php -r 'echo PHP_VERSION;')"
php -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);' || { err "PHP 版本过低（${PHP_VER}），需 8.1+。"; exit 1; }
for ext in pdo_mysql mbstring curl openssl; do
  php -m | grep -qi "^${ext}$" || { err "PHP 缺少扩展：${ext}（请在 1Panel 的 PHP 设置中安装）。"; exit 1; }
done
ok "PHP ${PHP_VER} 及扩展 OK"
[ -d /opt/1panel ] && ok "检测到 1Panel" || warn "未检测到 /opt/1panel（本脚本仅适配 1Panel，请确认站点目录）"

# ---------------------------------------------------------------------------
if [ "$MODE" = "install" ]; then
  c "==> 安装参数（回车使用默认/已设值；数据库请先在 1Panel 建好）"
  ask DB_HOST    "数据库主机" "$DB_HOST"
  ask DB_PORT    "数据库端口" "$DB_PORT"
  ask DB_NAME    "数据库名（已在 1Panel 建好）"
  ask DB_USER    "数据库用户"
  ask DB_PASS    "数据库密码"
  ask SITE_URL   "站点地址（如 https://vpsm.ruyawangluo.cn）"
  ask ADMIN_USER "管理员用户名" "$ADMIN_USER"
  ask ADMIN_PASS "管理员密码"
  [ -n "$DB_NAME" ] || { err "数据库名不能为空"; exit 1; }
  [ -n "$DB_USER" ] || { err "数据库用户不能为空"; exit 1; }
  [ -n "$DB_PASS" ] || { err "数据库密码不能为空"; exit 1; }
  [ -n "$ADMIN_PASS" ] || { err "管理员密码不能为空"; exit 1; }
fi

# ---------------------------------------------------------------------------
c "==> 从 GitHub 下载程序包"
TMP="$(mktemp -d /tmp/incus-master.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
curl -fsSL "$REPO_ARCHIVE" -o "$TMP/repo.tar.gz" || { err "下载失败：${REPO_ARCHIVE}"; exit 1; }
tar -xzf "$TMP/repo.tar.gz" -C "$TMP"
SRC="$(find "$TMP" -maxdepth 1 -type d -name 'ruyavps-*' | head -1)"
[ -n "$SRC" ] && [ -d "$SRC/master" ] || { err "程序包结构异常（未找到 master/）。"; exit 1; }

c "==> 解压到网站目录 ${SITE_DIR}"
mkdir -p "$SITE_DIR"
cp -r "$SRC/master/app" "$SRC/master/public" "$SRC/master/bin" "$SITE_DIR/"
[ -d "$SRC/master/resources" ] && cp -r "$SRC/master/resources" "$SITE_DIR/"

if [ "$MODE" = "update" ]; then
  ok "升级完成（config.php 未改动）。"
  exit 0
fi

# ---------------------------------------------------------------------------
c "==> 对接数据库并导入结构"
if [ -f "${SITE_DIR}/config.php" ]; then
  warn "已存在 config.php，跳过数据库初始化（如需重装请先删除它）。"
else
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" >/dev/null 2>&1 \
    || { err "数据库连接失败（库/用户/密码/主机）。请先在 1Panel 建好数据库与用户。"; exit 1; }
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SRC/install/schema.sql"
  ok "表结构已导入到 ${DB_NAME}"

  APP_KEY="$(openssl rand -hex 24 2>/dev/null || head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
  cat > "${SITE_DIR}/config.php" <<PHP
<?php
return [
    'debug'    => false,
    'site_url' => '${SITE_URL}',
    'timezone' => 'Asia/Shanghai',
    'db' => [
        'host' => '${DB_HOST}', 'port' => ${DB_PORT},
        'name' => '${DB_NAME}', 'user' => '${DB_USER}', 'pass' => '${DB_PASS}',
        'charset' => 'utf8mb4',
    ],
    'app_key' => '${APP_KEY}',
    'session_name' => 'incus_panel_sid',
    'page_size' => 20,
    'github_raw'     => 'https://raw.githubusercontent.com/ruyawangluo/ruyavps/main',
    'github_archive' => 'https://github.com/ruyawangluo/ruyavps/archive/refs/heads/main.tar.gz',
];
PHP
  ok "config.php 已写入"

  HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$ADMIN_PASS")"
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    -e "INSERT INTO admins (username,password_hash,nickname,role,status,created_at) VALUES ('${ADMIN_USER}','${HASH}','超级管理员','super',1,NOW());"
  ok "管理员账号已创建"
fi

# 归属与站点目录保持一致
OWNER="$(stat -c '%u:%g' "$SITE_DIR" 2>/dev/null || echo '')"
[ -n "$OWNER" ] && chown -R "$OWNER" "$SITE_DIR" 2>/dev/null || true

echo
ok "============================================================"
ok " 面板端部署完成"
echo " 网站目录 : ${SITE_DIR}"
echo " 管理员   : ${ADMIN_USER} / ${ADMIN_PASS}"
echo "------------------------------------------------------------"
echo " 还需在 1Panel 做两步："
echo "   1) 该站点「运行目录」设为： /public  （即 ${SITE_DIR}/public）"
echo "   2) 该站点「伪静态」填："
echo "        location / { try_files \$uri \$uri/ /index.php?\$query_string; }"
echo " 然后访问站点："
echo "   ${SITE_URL%/}/admin/login"
echo "============================================================"
