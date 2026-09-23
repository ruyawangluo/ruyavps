#!/usr/bin/env bash
# ============================================================================
#  打包并发布到 GitHub 发行版（Releases）
#
#  用法：
#     GITHUB_TOKEN=ghp_xxx bash scripts/release.sh
#     # 或依赖 git 已保存的凭据（git credential fill）
#     bash scripts/release.sh
#
#  做的事：
#     1) 从 master/app/Core/Version.php 读取版本号（如 2.2.1 -> v2.2.1）
#     2) 打包面板端与节点端到 dist/
#     3) 在 ruyawangluo/ruyavps 创建同名 Release 并上传两个包
#
#  可用环境变量：REPO_OWNER、REPO_NAME、BRANCH、DIST_DIR
# ============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REPO_OWNER="${REPO_OWNER:-ruyawangluo}"
REPO_NAME="${REPO_NAME:-ruyavps}"
BRANCH="${BRANCH:-main}"
DIST_DIR="${DIST_DIR:-$ROOT/dist}"
REPO="$REPO_OWNER/$REPO_NAME"

VER="$(grep -oP "MASTER = '\K[0-9.]+" "$ROOT/master/app/Core/Version.php")"
NVER="$(grep -oP "VERSION = '\K[0-9.]+" "$ROOT/agent/lib/Version.php")"
TAG="v$VER"
[ -n "$VER" ] || { echo "无法读取版本号"; exit 1; }
echo "==> 版本：$TAG（面板 $VER / 节点 $NVER）"

# ---- 令牌 ----
TOKEN="${GITHUB_TOKEN:-}"
if [ -z "$TOKEN" ]; then
  TOKEN="$(printf 'protocol=https\nhost=github.com\n\n' | git credential fill 2>/dev/null | sed -n 's/^password=//p' || true)"
fi
[ -n "$TOKEN" ] || { echo "缺少令牌：请设置 GITHUB_TOKEN 或配置 git 凭据。"; exit 1; }

# ---- 打包 ----
STAGE="$(mktemp -d /tmp/incus-release.XXXXXX)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/incus-panel-master" "$STAGE/incus-panel-node"

cp -r "$ROOT/master" "$STAGE/incus-panel-master/master"
cp -r "$ROOT/scripts" "$STAGE/incus-panel-master/scripts"
cp -r "$ROOT/install" "$STAGE/incus-panel-master/install"
cp "$ROOT/README.md" "$STAGE/incus-panel-master/README.md"
cp -r "$ROOT/agent" "$STAGE/incus-panel-node/agent"
cp -r "$ROOT/scripts" "$STAGE/incus-panel-node/scripts"
mkdir -p "$STAGE/incus-panel-node/install"
cp "$ROOT/install/agent-install.sh" "$STAGE/incus-panel-node/install/"
cp "$ROOT/README.md" "$STAGE/incus-panel-node/README.md"
find "$STAGE" \( -name 'config.php' -o -name '*.log' -o -name 'node.db*' \) -delete

mkdir -p "$DIST_DIR"
A="$DIST_DIR/incus-panel-master-$TAG.tar.gz"
B="$DIST_DIR/incus-panel-node-$TAG.tar.gz"
tar czf "$A" -C "$STAGE" incus-panel-master
tar czf "$B" -C "$STAGE" incus-panel-node
echo "==> 已打包："
ls -lh "$A" "$B"

# ---- 创建 Release ----
BODY="Incus 云控制台 $TAG

- 面板端（incus-panel-master）：仅适配 1Panel，网站目录 /opt/1panel/www/sites/ruyavps/index；安装脚本从 GitHub 拉取程序包并导入数据库结构。
- 节点端（incus-panel-node）：从 GitHub 直接安装（被控程序 + 本地 SQLite + 本地控制 API），无需面板参与，安装后打印 IP/端口/KEY 供面板对接。"

RESP="$(curl -s -X POST -H "Authorization: token $TOKEN" -H "Accept: application/vnd.github+json" \
  "https://api.github.com/repos/$REPO/releases" \
  -d "$(python3 -c "import json,sys;print(json.dumps({'tag_name':sys.argv[1],'target_commitish':sys.argv[2],'name':'Incus 云控制台 '+sys.argv[1],'body':sys.argv[3]},ensure_ascii=False))" "$TAG" "$BRANCH" "$BODY")")"
RID="$(printf '%s' "$RESP" | python3 -c "import json,sys;print(json.load(sys.stdin).get('id',''))" 2>/dev/null || true)"
if [ -z "$RID" ]; then
  # tag 可能已存在：复用已有 release
  RID="$(curl -s -H "Authorization: token $TOKEN" "https://api.github.com/repos/$REPO/releases/tags/$TAG" \
    | python3 -c "import json,sys;print(json.load(sys.stdin).get('id',''))" 2>/dev/null || true)"
fi
[ -n "$RID" ] || { echo "创建/获取 Release 失败：$RESP"; exit 1; }
echo "==> Release：https://github.com/$REPO/releases/tag/$TAG （id=$RID）"

# ---- 上传资产（已存在则跳过） ----
EXIST="$(curl -s -H "Authorization: token $TOKEN" "https://api.github.com/repos/$REPO/releases/$RID/assets" \
  | python3 -c "import json,sys;print(' '.join(a['name'] for a in json.load(sys.stdin)))" 2>/dev/null || true)"
for f in "$A" "$B"; do
  name="$(basename "$f")"
  case " $EXIST " in *" $name "*) echo "  已存在，跳过: $name"; continue ;; esac
  curl -s -X POST -H "Authorization: token $TOKEN" -H "Content-Type: application/gzip" \
    --data-binary @"$f" "https://uploads.github.com/repos/$REPO/releases/$RID/assets?name=$name" \
    | python3 -c "import json,sys;d=json.load(sys.stdin);print('  上传:', d.get('name'), d.get('state') or d.get('message'))"
done
echo "==> 完成。"
