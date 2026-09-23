#!/usr/bin/env bash
# ============================================================================
#  Incus 云控制台 · 面板端安装 / 升级脚本（适配 1Panel）
#
#  用法：
#     bash install/install-master.sh            # 安装（首次）
#     bash install/install-master.sh update     # 升级（保留 config.php）
#     bash install/install-master.sh uninstall  # 仅提示（不删数据）
#
#  安装前请先在 1Panel 完成以下环境（本脚本不再负责装 Web 环境）：
#     1) 安装 1Panel 后，在「应用商店」安装：OpenResty、PHP 8+、MySQL
#     2) PHP 8 需启用扩展：pdo_mysql、mbstring、curl、openssl
#     3) 在 1Panel「网站」里新建一个站点，站点代号（目录名）建议 ruyavps
#        —— 新版 1Panel 站点目录形如：/opt/1panel/www/sites/<代号>/index
#        本脚本默认部署到 /opt/1panel/www/sites/ruyavps/index
#     4) 用 root（sudo）执行本脚本
#
#  本脚本会：部署面板代码 → 创建/导入数据库 → 写 config.php → 建管理员账号。
#  可用环境变量免交互（否则逐项询问）：
#     SITE_DIR  DB_HOST DB_PORT DB_NAME DB_USER DB_PASS  SITE_URL  ADMIN_USER ADMIN_PASS
# ============================================================================
set -euo pipefail

MODE="${1:-install}"
SITE_DIR="${SITE_DIR:-/opt/1panel/www/sites/ruyavps/index}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-ruyavps}"
DB_USER="${DB_USER:-ruyavps}"
DB_PASS="${DB_PASS:-}"
SITE_URL="${SITE_URL:-}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

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

[ "$(id -u)" = "0" ] || { err "请用 root 运行（sudo bash install/install-master.sh）"; exit 1; }

if [ "$MODE" = "uninstall" ]; then
  warn "不执行删除。若需卸载：删除站点目录 ${SITE_DIR} 并自行 drop 数据库 ${DB_NAME}。"
  exit 0
fi

# ---------------------------------------------------------------------------
c "==> 环境检查"
command -v php >/dev/null 2>&1 || { err "未找到 php。请先在 1Panel 安装 PHP 8+。"; exit 1; }
PHP_VER="$(php -r 'echo PHP_VERSION;')"
php -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);' || { err "PHP 版本过低（${PHP_VER}），需 8.1+。"; exit 1; }
for ext in pdo_mysql mbstring curl openssl; do
  php -m | grep -qi "^${ext}$" || { err "PHP 缺少扩展：${ext}（请在 1Panel 的 PHP 设置中安装）。"; exit 1; }
done
ok "PHP ${PHP_VER} 及扩展 OK"
[ -d /opt/1panel ] && ok "检测到 1Panel" || warn "未检测到 /opt/1panel（如非 1Panel，请自行确认站点目录）"

# ---------------------------------------------------------------------------
if [ "$MODE" = "install" ]; then
  c "==> 安装参数（回车使用默认/已设值）"
  ask SITE_DIR   "站点目录" "$SITE_DIR"
  ask DB_HOST    "数据库主机" "$DB_HOST"
  ask DB_PORT    "数据库端口" "$DB_PORT"
  ask DB_NAME    "数据库名" "$DB_NAME"
  ask DB_USER    "数据库用户" "$DB_USER"
  ask DB_PASS    "数据库密码"
  ask SITE_URL   "站点地址（如 https://vpsm.ruyawangluo.cn）" "http://127.0.0.1"
  ask ADMIN_USER "管理员用户名" "$ADMIN_USER"
  ask ADMIN_PASS "管理员密码"
  [ -n "$DB_PASS" ] || { err "数据库密码不能为空"; exit 1; }
  [ -n "$ADMIN_PASS" ] || { err "管理员密码不能为空"; exit 1; }
fi

# 升级模式：从已有 config.php 读取关键值
if [ "$MODE" = "update" ]; then
  CFG="${SITE_DIR}/config.php"
  [ -f "$CFG" ] || { err "未找到 ${CFG}，请先执行安装。"; exit 1; }
  SITE_DIR="$(php -r "include '$CFG'; echo dirname('$CFG');")" >/dev/null 2>&1 || true
  c "==> 升级模式：覆盖程序文件，保留 config.php（${SITE_DIR}）"
fi

mkdir -p "$SITE_DIR"

# ---------------------------------------------------------------------------
c "==> 部署面板代码到 ${SITE_DIR}"
cp -r "$ROOT/master/app" "$ROOT/master/public" "$ROOT/master/bin" "$SITE_DIR/"
[ -d "$ROOT/master/resources" ] && cp -r "$ROOT/master/resources" "$SITE_DIR/"
# 被控端分发源码与安装脚本
rm -rf "${SITE_DIR}/agent-src"
cp -r "$ROOT/agent" "${SITE_DIR}/agent-src"
cp "$ROOT/install/agent-install.sh" "${SITE_DIR}/agent-install.sh"
# 清掉不该部署的
rm -f "${SITE_DIR}/agent-src/config.php"

if [ "$MODE" = "update" ]; then
  ok "升级完成。"
  exit 0
fi

# ---------------------------------------------------------------------------
c "==> 创建/校验数据库并导入结构"
if [ -f "${SITE_DIR}/config.php" ]; then
  warn "检测到已存在 config.php：跳过数据库初始化（如需重装请先删除它）。"
else
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" \
    -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null \
    || { err "数据库连接失败（用户/密码/主机）。请先在 1Panel 创建数据库与用户，或检查凭据。"; exit 1; }
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$ROOT/install/schema.sql"
  ok "数据库 ${DB_NAME} 结构已导入"

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
];
PHP
  ok "config.php 已写入"

  HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$ADMIN_PASS")"
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    -e "INSERT INTO admins (username,password_hash,nickname,role,status,created_at) VALUES ('${ADMIN_USER}','${HASH}','超级管理员','super',1,NOW());"
  ok "管理员账号已创建"
fi

# 归属：与站点目录保持原有属主
OWNER="$(stat -c '%u:%g' "$SITE_DIR" 2>/dev/null || echo '')"
if [ -n "$OWNER" ]; then chown -R "$OWNER" "$SITE_DIR" 2>/dev/null || true; fi

echo
ok "============================================================"
ok " 面板端部署完成"
echo " 站点目录 : ${SITE_DIR}"
echo " 后台地址 : ${SITE_URL%/}/admin/login"
echo " 管理员   : ${ADMIN_USER} / ${ADMIN_PASS}"
echo "------------------------------------------------------------"
echo " 还需在 1Panel："
echo "   1) 站点「运行目录」设为 ${SITE_DIR}"
echo "   2) 站点「伪静态」填："
echo "        location / { try_files \$uri \$uri/ /index.php?\$query_string; }"
echo "   3) 使用 PHP 8 运行环境"
echo "============================================================"
