# Incus 云控制台

基于 **Incus** 的云控制台，采用「**节点自治 + 面板控制面**」架构：

- **面板端**：纯 Web UI + 自己的数据库（管理员、用户、节点接入信息、实例索引、日志、设置）。不直接操作 Incus。
- **节点端**：被控程序 + **自己的数据库（SQLite）** + Incus；实例、镜像、存储池的真实数据都在节点上。
- 节点只把 **`ip / 端口 / key`** 交给面板；面板通过节点 API 远程控制。
- 业务开通由上层财务/前台系统经**开放 API** 调用面板完成；每台机器有独立 **访问 Key** 可供单机调用。

技术栈：面板端 PHP 8.1+ / MySQL；节点端 PHP 8.1+（`pdo_sqlite`）/ Incus。无 Composer / 框架依赖。

---

## 架构

```
财务/前台 ──开放API──▶ 面板(Web UI + MySQL: 用户/节点/实例索引)
                          │  节点 API（HTTP + X-Node-Token）
                          ▼
              节点1: 被控程序 + SQLite + Incus
              节点2: 被控程序 + SQLite + Incus
```

- 面板拉取节点状态、转发实例操作；节点离线也不影响节点上已运行的机器。
- 节点可独立运行：不接面板也能用本地 API 直接管理本机实例。

---

## 部署顺序

### 1. 面板端（仅适配 1Panel：OpenResty + MySQL + PHP 8）

前置（手动完成）：
1. 安装 1Panel，在应用商店装 OpenResty、PHP 8+、MySQL。
2. PHP 8 启用扩展：`pdo_mysql / mbstring / curl / openssl`。
3. 新建站点，站点目录为 **`/opt/1panel/www/sites/ruyavps/index`**（代号 ruyavps）。
4. 在 1Panel 建好 MySQL 数据库与用户（脚本不建库，只导入表结构）。

然后在服务器上以 root 运行安装脚本（自动从 GitHub 下载程序包 → 解压到站点目录 → 对接数据库并导入结构 → 写 config.php → 建管理员）：

```bash
curl -fsSL https://raw.githubusercontent.com/ruyawangluo/ruyavps/main/install/install-master.sh -o /tmp/install-master.sh && sudo bash /tmp/install-master.sh
```

按提示填写：数据库主机/端口/库名/用户/密码、站点地址、管理员账号密码。完成后脚本会提示你去 1Panel 把该站点「运行目录」改为 **`/public`**、伪静态填 `try_files`，然后访问站点即可。

> 升级：`sudo bash install-master.sh update`（重新拉取并覆盖程序，保留 config.php）。

### 2. 节点端（在每台计算节点上，root；从 GitHub 直接安装）

```bash
curl -fsSL https://raw.githubusercontent.com/ruyawangluo/ruyavps/main/install/agent-install.sh -o /tmp/agent-install.sh && sudo bash /tmp/agent-install.sh
```

完成后会打印节点的接入信息：

```
 节点端安装完成 —— 拿去面板端「计算节点 → 接入节点」对接：
   IP   : 1.2.3.4
   端口 : 8787
   KEY  : <随机 key>
```

在面板「计算节点 → 接入节点」填入 IP / 端口 / KEY 即完成对接。

> 节点端不需要面板地址、不需要数据库（自带本地 SQLite）。

### 3. Incus 单独配置（在节点上，从 GitHub 拉取）

| 脚本 | 作用 |
|------|------|
| `incus-manage.sh` | 安装/卸载 Incus（Zabbly 源），初始化网桥与默认 profile |
| `incus-storage.sh` | 创建/删除/列出存储池（ZFS / Btrfs） |
| `incus-image.sh` | 下载并 `incus image import` 导入 LXC / VM 镜像到本地 |

节点端的镜像、存储池在面板「镜像 / 存储池」页只读展示；导入镜像后用本地别名（如 `local:ubuntu2404-lite-amd64-lxc`）开通实例。

---

## 目录结构

```
.
├── install/
│   ├── install-master.sh       # 面板端安装/升级脚本（从 GitHub 拉取程序包）
│   ├── agent-install.sh        # 节点端安装脚本（从 GitHub 拉取）
│   ├── schema.sql              # 面板数据库结构
│   └── nginx.conf.sample
├── scripts/                    # 节点运维脚本（从 GitHub 拉取）
│   ├── incus-manage.sh         # 安装/卸载 Incus
│   ├── incus-storage.sh        # 存储池
│   └── incus-image.sh          # 镜像导入
├── master/                     # 面板端
│   ├── config.sample.php
│   ├── public/                 # Web 根目录（index.php / router.php / assets）
│   ├── app/
│   │   ├── Core/               # Db/NodeClient/NodeApi…/OpenAuth/InstanceAuth/Setting/Tar/Version
│   │   ├── Services/           # NodeService（节点注册表）/ InstanceService（索引+转发）
│   │   ├── Controllers/        # Admin\* / Client\* / OpenApiController / InstanceApiController
│   │   └── views/
│   └── bin/cron.php            # 可选：定时刷新节点/实例状态
└── agent/                      # 节点端（被控程序 + 本地库 + 本地 API）
    ├── config.sample.php
    ├── server.php              # 本地控制 API 入口（php -S 运行）
    ├── lib/Db.php              # SQLite 封装与建表
    ├── lib/NodeStore.php       # 本地实例/事件/设置
    ├── lib/NodeApi.php         # API 路由与处理
    ├── lib/Incus.php           # Incus CLI 驱动
    ├── lib/Version.php
    └── systemd/incus-node-api.service
```

---

## 面板端功能

| 模块 | 说明 |
|------|------|
| 控制台 | 容器/虚拟机数量、运行中、节点在线数；最近实例与操作 |
| 计算节点 | 接入/编辑/测试/启停/移除；仅保存 `ip/端口/key`；节点安装与 Incus 工具命令（GitHub 拉取） |
| 云服务器 | 开通、详情（含节点实时状态/用量/事件）、开机/关机/重启、重置密码、调整规格、带宽/IO/CPU 上限、快照、执行命令、删除、每机访问 Key |
| 镜像 | 按节点查看该节点 Incus 镜像（只读） |
| 存储池 | 按节点查看该节点存储池（只读） |
| 用户 | 增删、启停、按邮箱管理，实例数统计 |
| 操作日志 | 管理员/用户/API 审计 |
| 系统设置 | 站点、开放 API 凭据 |
| 关于 | 面板版本、节点版本、升级方式（脚本） |

用户面板：登录、实例列表/详情、开关机、重置密码、快照、删除、查看访问 Key。

---

## 节点本地 API（节点端自带）

由 systemd `incus-node-api.service` 常驻（默认 `0.0.0.0:8787`），鉴权 `X-Node-Token`：

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/health` | 健康检查（无需鉴权） |
| GET | `/node` | 节点自身信息 `{name,ip,port,key}` |
| GET | `/instances` | 本机实例列表（含状态/用量） |
| POST | `/instances` | 创建实例 |
| GET | `/instances/{name}` | 实例详情 |
| POST | `/instances/{name}/action` | 操作：start/stop/reboot/reset_password/resize/set_limits/snapshot_*/exec |
| DELETE | `/instances/{name}` | 删除 |
| GET | `/images` · `/storage` | 本机镜像 / 存储池 |

节点本地状态（访问 Key、归属、规格、事件）存于 `node.db`（SQLite），**不依赖面板**。

---

## 开放 API（面板端，供上层财务/前台）

在「系统设置」生成平台凭据。所有接口 **POST JSON**，HMAC-SHA256 签名（头 `X-Api-Key / X-Timestamp / X-Signature`）：签名串 `api_key\n timestamp\n sha256(body)`。

| 接口 | 说明 |
|------|------|
| `/api/v1/open/ping` | 连通性 |
| `/api/v1/open/user/create` · `/user/get` · `/user/list` | 用户 |
| `/api/v1/open/node/list` | 节点列表 |
| `/api/v1/open/image/list` | 某节点镜像（`node_id`） |
| `/api/v1/open/instance/create` | 开通（`email`/`user_id`、`node_id`、`source`、`cpu`、`memory_mb`、`disk_gb`、`storage_pool`、`ip_mode`、`ip`、`root_password`、`config`、`inbound_mbps`… ） |
| `/api/v1/open/instance/get` · `/list` | 查询（`instance_id`/`access_key`/`incus_name`） |
| `/api/v1/open/instance/action` · `/delete` | 操作 / 删除 |

### 单机 API（凭每台机器访问 Key）

头 `X-Instance-Key: <access_key>`（可选 `X-Instance-Email` 校验归属）：

- `POST /api/v1/instance/info` — 该机信息（节点、IP、用量、事件）
- `POST /api/v1/instance/action` — 操作该机

> 财务/前台用「邮箱 + 该机 Key」即可定位并控制单台机器。

---

## 版本

- 面板端：`master/app/Core/Version.php`（`MASTER`）。
- 节点端：`agent/lib/Version.php`（`VERSION`）；面板「关于与更新」展示各节点上报版本。
- 升级面板：在服务器上运行 `bash install/install-master.sh update`（覆盖程序、保留 config.php）。
- 升级节点：在节点上重新执行安装命令。

## 发行版（Releases）

程序包发布在 GitHub 发行版：https://github.com/ruyawangluo/ruyavps/releases

一键打包并发布（需 `GITHUB_TOKEN` 或已配置 git 凭据）：

```bash
bash scripts/release.sh
```

会读取 `master/app/Core/Version.php` 的版本号 → 打包面板端/节点端 → 创建同名 Release(Tag) 并上传两个包。
