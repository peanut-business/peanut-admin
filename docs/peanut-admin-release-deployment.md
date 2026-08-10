# Peanut Admin 生产发布与部署

生产部署只采用一条主路径：服务器拉取已经开发完成的应用仓，使用 Docker Compose 构建并启动，宝塔反代本机端口，Cloudflare 代理服务器域名。服务器不是用模板创建新应用的地方。

## 发布内容

一次生产构建包含全部运行入口：

| URL | 来源 | 运行方式 |
|---|---|---|
| `/admin/` | `web/` 管理端 | 构建到 Nginx 镜像的 `server/public/admin/` |
| `/mobile/` | `uniapp/` H5 | 构建到 Nginx 镜像的 `server/public/mobile/` |
| `/pc/` | `pc/` Nuxt 3 | 静态构建到 Nginx 镜像的 `server/public/pc/` |
| `/api/` | `server/` ThinkPHP | PHP-FPM |
| `/storage/` | 后端文件 | PHP 与 Nginx 共享持久卷 |

三个前端只写入各自子目录，不替换 `server/public/index.php`、服务器规则或存储目录。三套客户端统一使用同源 `/api/`。

## 服务器要求

- Git
- Docker Engine
- Docker Compose v2
- 宝塔 Nginx（只做外层反向代理和证书）

宿主机不需要安装 PHP、Composer、Node.js 或 pnpm。Node.js 只存在于 Docker 构建阶段，不是生产运行时服务。Redis 默认不启动。

运行版本见 [`deploy/VERSIONS.md`](../deploy/VERSIONS.md)。

## 首次部署

```bash
git clone git@github.com:peanut-business/peanut-admin.git
cd peanut-admin
cp .env.example .env
chmod 600 .env
```

编辑 `.env`，至少填写局域网数据库的 `DB_HOST`、`DB_PORT`、`DB_NAME`、`DB_USER`、`DB_PASS`，并替换 `JWT_SECRET`。该文件已被 Git 和 Docker 构建上下文排除。生产默认连接外部 MySQL，不依赖容器名解析。

然后只执行：

```bash
docker compose up -d --build
```

Compose 默认只启动 PHP-FPM、Nginx 和定时任务，并连接 `.env` 指定的 MySQL。空数据库由 PHP 入口安全初始化，并将全部 migrations 记入 `pa_schema_migration`；已有完整数据库不会重复安装。首次安装后的管理员账号为 `admin / admin123456`，登录后立即修改密码。

最低检查：

```bash
docker compose ps
curl -fsS http://127.0.0.1:18092/healthz
```

### 可选构建代理

生产服务器可以直接访问软件源时，不要设置 `BUILD_*_PROXY`。确需代理时，代理地址必须能从 Docker builder 访问，不能使用 builder 自身的 `127.0.0.1`。Compose 会将同一配置同时写入大小写代理变量，以兼容 apt、Composer、npm 和 pnpm：

```dotenv
BUILD_HTTP_PROXY=http://<docker-builder-visible-host>:7890
BUILD_HTTPS_PROXY=http://<docker-builder-visible-host>:7890
BUILD_ALL_PROXY=http://<docker-builder-visible-host>:7890
```

Docker Desktop 可通过 `docker buildx inspect --bootstrap` 查看 `moby.host-gateway-ip`，再确认该地址的代理端口可访问。

### 已验证基线

2026-08-07 在独立 Compose 项目和全新 MySQL 卷中完成一次生产构建与启动：`/healthz`、`/admin/`、`/mobile/`、`/pc/` 均返回 HTTP 200；首次安装生成 42 张表、1 个默认超级管理员、170 个菜单和 59 项配置。验证后已删除测试容器、网络和卷。

同日完成首次服务器部署与公网接入：生产 Compose 在 `161.153.52.6` 运行，宝塔反向代理服务器实际配置的 `127.0.0.1:18092`；Cloudflare 代理记录 `peanut-admin.007345.xyz` 和 `peanut-admin-doc.007345.xyz` 已生效。

2026-08-11 已把服务器从历史功能分支升级到 `dev`，完成数据库/存储备份、24 条迁移账本接管、生产 API 路由修复和三端镜像更新。当前四个服务健康/运行，真实 Chromium 已通过管理端登录、文章页、UniApp H5、Nuxt PC 与文档站 smoke；证据见 `output/playwright/production-baseline/final-summary.json`。

## 宝塔与 Cloudflare

宝塔创建站点并将全部请求反向代理到：

```text
http://127.0.0.1:18092
```

Compose 只监听宿主机回环地址，不直接暴露 PHP-FPM、MySQL 或 PC 容器端口。宝塔负责公网 80/443 和源站证书。

Cloudflare 中将应用域名的 A/AAAA 记录指向服务器公网地址并开启代理。Cloudflare 访问宝塔，宝塔再访问本机 `18092`；本方案不需要 Cloudflare Tunnel 容器。

宝塔对应站点的 443 必须安装覆盖 `peanut-admin.007345.xyz` 的有效证书，否则 HTTPS 会落到宝塔默认站点。可使用 Cloudflare Origin CA，也可使用自动续期的 Let's Encrypt 证书；当前生产服务器使用后者。宝塔开启 HTTPS 后，Cloudflare 加密模式固定为 **Full (strict)**，不要使用 Flexible。源站 80/443 应只接受可信来源，且不得绕过 Cloudflare 对外提供服务。

服务器应用域名固定为 `peanut-admin.007345.xyz`。文档站域名 `peanut-admin-doc.007345.xyz` 属于 Cloudflare Pages，不经过宝塔，也不安装 Origin CA 证书；同一个 DNS 名称不能同时指向 Pages 和服务器源站。

## Redis

当前应用没有 Redis 硬依赖。确有需要时才启用：

```bash
docker compose --profile redis up -d
```

## 可选内置 MySQL

模板默认支持连接外部数据库。当前公网演示服务器无法路由开发局域网的 `192.168.192.2`，所以实际启用内置数据库 profile，并将 `DB_HOST` 设为 `mysql`：

```bash
docker compose --profile bundled-db up -d --build
```

若应用和 MySQL 位于两台可互通的机器，不能使用 Docker 服务名通信；必须填写数据库服务器对应用服务器可路由的 IP/主机名，并在 MySQL 侧授权来源地址。公网服务器不得填写只能在开发局域网访问的地址。

## 后续升级

默认沿用服务器当前稳定发布分支。正式升级前先备份 MySQL 与 `php-storage` 卷。升级顺序固定为：拉取代码、构建新镜像、用新镜像迁移数据库，迁移成功后才切换运行容器：

```bash
git pull --ff-only
docker compose build
docker compose run --rm --no-deps php php server/database/migrate.php
docker compose up -d --no-build
```

历史安装首次升级时，把普通迁移命令替换为只执行一次的历史接管；接管成功后再启动新容器：

```bash
docker compose build
docker compose run --rm --no-deps php php server/database/migrate.php --adopt-existing
docker compose up -d --no-build
```

接管会先完整校验历史基线，再登记历史迁移并执行未登记文件。`php` 服务继承镜像工作目录 `/var/www/peanut-admin`，所以命令使用 `server/database/migrate.php`；在 server 工作目录中等价于 `php database/migrate.php`。迁移只处理账本中未登记的文件，并校验 SHA-256；无待执行文件时返回 `up_to_date`。迁移失败时不要启动新容器：MySQL DDL 不能假定事务回滚，应核对已执行结构、备份和失败记录，编写前滚修复后再继续。历史接管命令不可在后续发布中重复执行。

需要严格锁版或回滚时，再使用 release tag；这不是首次部署和日常升级的必需步骤。

`--skip-if-installed` 只避免容器重启时重复执行首次安装，不代替版本化数据库迁移。自动升级管理将在独立运营平台实现前保持手动。

`scripts/package-release.sh` 仅保留为管理端 + PHP 的原生制品工具，不是完整三端生产部署方案。生产环境以 Docker Compose 为唯一推荐入口。
