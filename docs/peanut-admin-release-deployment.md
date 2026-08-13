# Peanut Admin 生产发布与部署

生产部署只采用一条主路径：服务器拉取已经开发完成的应用仓，使用 Docker Compose 构建并启动，再由目标环境的 HTTPS 反向代理接入。服务器不是用模板创建新应用的地方。

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

编辑 `.env`，至少填写数据库的 `DB_HOST`、`DB_PORT`、`DB_NAME`、`DB_USER`、`DB_PASS`，替换 `JWT_SECRET`；空库首次安装还必须填写 `ADMIN_INITIAL_PASSWORD`。示例的 `DB_HOST=mysql` 对应可选 `bundled-db` profile，外部 MySQL 部署必须改为可从 PHP 容器访问的地址。该文件已被 Git 和 Docker 构建上下文排除。

然后只执行：

```bash
docker compose up -d --build
```

Compose 默认只启动 PHP-FPM、Nginx 和定时任务，并连接 `.env` 指定的 MySQL。空数据库由 PHP 入口安全初始化，并将全部 migrations 记入 `pa_schema_migration`；已有完整数据库不会重复安装。首次空库安装必须设置 `ADMIN_INITIAL_PASSWORD`，管理员用户名为 `admin`，密码不会写入日志或响应；首次登录后应改为个人凭据。

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

2026-08-11 已把服务器从历史功能分支升级到 `dev`，完成数据库/存储备份、24 条迁移账本接管、生产 API 路由修复和三端镜像更新。当前四个服务健康/运行，真实 Chromium 已通过管理端登录、文章页、UniApp H5、Nuxt PC 与文档站 smoke；证据见 `output/playwright/production-baseline/final-summary.json`。

2026-08-11 PB09 正式部署检出不可变 `v1.0.0@0d3c848…`，在部署端从源码构建 PHP/Nginx 镜像；数据库与 `php-storage` 配对备份后，迁移账本从 24 条一次前滚到 28 条，再以 `up -d --no-build` 切换。MySQL/PHP/Nginx 健康且 cron 运行；一次最低外部 smoke 覆盖应用健康、管理端/PC/H5 HTTP、`RELEASE_METADATA.json`、法律资产和官网版本页，不重复 PB08B 浏览器矩阵。

2026-08-13 生产主机 `oracle3` 已检出不可变 `v1.1.0@c6a165f…`，在原有数据库与
`php-storage` 成对备份之后由源码构建 `v1.1.0` PHP/Nginx 镜像。迁移按“先迁移、后切换”
从 28 条一次前滚到 50 条，默认 Tenant、Account、TenantMember 与 owner 映射完成；旧管理员
凭据不满足 Core 最低长度时先保持 `v1.0.0` 流量，完成受控密码轮换后再继续迁移，没有绕过
密码策略。切换后 MySQL/PHP/Nginx 健康、cron 运行，`/healthz`、管理端、PC、H5、release
metadata 与管理端登录最低检查通过。应用入口为 <https://peanut-admin.007345.xyz>；文档站由
Cloudflare Pages 项目 `peanut-admin-docs` 独立发布到
<https://peanut-admin-doc.007345.xyz>，不经过 `oracle3`。

以上只记录已封存的验收范围，不是模板域名、IP、数据库地址或云平台默认值。

## HTTPS 反向代理

Compose 默认只监听宿主机回环地址。可使用 Nginx、宝塔或等价入口，把应用域名的请求反向代理到：

```text
http://127.0.0.1:18092
```

不要直接暴露 PHP-FPM、MySQL 或 PC 容器端口。外层入口负责公网 80/443、有效证书、Host 与外部 HTTPS scheme 转发。

如使用 Cloudflare，将应用域名的 A/AAAA 记录指向源站并开启代理，再由源站反向代理到本机 `18092`；本方案不要求 Cloudflare Tunnel 容器。

站点 443 必须安装覆盖实际应用域名的有效证书，否则 HTTPS 可能落到默认站点。可使用 Cloudflare Origin CA，也可使用自动续期的 Let's Encrypt 证书；使用 Cloudflare 时加密模式应为 **Full (strict)**，不要使用 Flexible。源站 80/443 应只接受可信来源，且不得绕过代理策略对外提供服务。

应用与文档站可以使用独立域名或同站分区；若文档站发布到 Cloudflare Pages，它不经过应用服务器，也不安装应用源站证书。同一个 DNS 名称不要同时绑定 Pages 和应用源站。

## Redis

当前应用没有 Redis 硬依赖。确有需要时才启用：

```bash
docker compose --profile redis up -d
```

## 可选内置 MySQL

模板支持连接外部数据库，也支持单机部署的内置数据库 profile。启用内置数据库时将 `DB_HOST` 设为 `mysql`：

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

包含 PB06 的版本会由迁移账本执行 `20260811-content-asset-reference.sql`，扩充文章封面和 Tabbar 图标列以保存完整云/CDN URL。必须保持“先迁移、后切换”顺序；该迁移不搬迁素材对象，也不改写历史相对 URI。

包含 PB07 通知切片的版本会执行 `20260811-notification-host-security.sql`：历史验证码立即失效并从内容快照脱敏，`verify_code` 原位改为 `verify_code_hash`，同时撤销已退出的通用模板写权限菜单。迁移不删除 `pa_notice_template` 历史数据；升级后必须在通知渠道页确认唯一短信 Provider 和四个固定 scene 配置，不能期待旧验证码继续可用。

PB07 支付切片不新增数据库结构，但微信预支付、退款请求和退款查询现在都要求响应带有效的平台证书签名。升级前确认 `wx_pay_platform_cert_path` 指向 PHP-FPM 可读且与微信响应 serial 匹配的当前平台证书；证书缺失、过期或不匹配会 fail closed。部署 smoke 只能使用真实商户沙箱/低风险订单验证，不能关闭验签或手工改成功状态。

PB07 OAuth/渠道切片会执行 `20260811-oauth-channel-host.sql`，删除旧 `channel` 微信/QQ 九字段和 `oa_setting` 中未实现的 AES 两字段；这些行可能包含敏感凭据，迁移前必须完成数据库备份。迁移不删除当前公众号、小程序、开放平台、菜单、回复、OAuth 身份或会员数据。反向代理必须正确传递外部 HTTPS scheme/Host；微信开放平台登记 `/api/oauth/wechat/redirect/pc`，公众号网页授权登记 `/api/oauth/wechat/redirect/official-account`。只有真实凭据、微信平台域名/白名单和一次低风险回跳通过后，才能声明生产 OAuth 可用；当前封存验收没有调用真实微信。

历史安装首次升级时，把普通迁移命令替换为只执行一次的历史接管；接管成功后再启动新容器：

```bash
docker compose build
docker compose run --rm --no-deps php php server/database/migrate.php --adopt-existing
docker compose up -d --no-build
```

接管会先完整校验历史基线，再登记历史迁移并执行未登记文件。`php` 服务继承镜像工作目录 `/var/www/peanut-admin`，所以命令使用 `server/database/migrate.php`；在 server 工作目录中等价于 `php database/migrate.php`。迁移只处理账本中未登记的文件，并校验 SHA-256；无待执行文件时返回 `up_to_date`。迁移失败时不要启动新容器：MySQL DDL 不能假定事务回滚，应核对已执行结构、备份和失败记录，编写前滚修复后再继续。历史接管命令不可在后续发布中重复执行。

### 回滚停止线

- 数据库迁移只前滚，不提供自动 down migration；不要删除账本记录、改写已登记 SQL，或假定 MySQL DDL 会随事务完整回滚。
- 只有新迁移对旧应用保持向后兼容时，才可把应用镜像切回上一版本；切回前确认后台任务、缓存和三端静态资源与旧版本匹配。
- 如果迁移已产生旧应用不能读取的结构或数据，立即停止写流量，不启动旧镜像；只能修复并继续前滚，或恢复发布前同一时点的数据库与 `php-storage` 成对备份。
- 真实支付、通知或 OAuth 外部状态不能靠数据库恢复撤销；恢复前先停止对应回调/任务并人工对账。

需要严格锁版或回滚时，再使用 release tag；这不是首次部署和日常升级的必需步骤。

### 1.1.0 双模式与身份输入

`1.1.0` 的 Standalone 和多租户能力来自同一 release。部署必须显式设置
`DEPLOYMENT_MODE=standalone` 或 `DEPLOYMENT_MODE=multi-tenant`；缺失或未知值不会自动
回退为 Standalone。两种模式都必须设置彼此独立、至少 32 字节且发布后保持稳定的
`TENANT_IDENTIFIER_HMAC_KEY` 和 `PLATFORM_IDENTIFIER_HMAC_KEY`。

首次安装和首次将历史库纳入 Tenant Account 模型时提供 `ADMIN_INITIAL_EMAIL`，空库还要
提供合格的 `ADMIN_INITIAL_PASSWORD`。多租户模式另需提供与管理员邮箱不同的
`PLATFORM_INITIAL_EMAIL` 和合格的 `PLATFORM_INITIAL_PASSWORD`，以建立独立
PlatformOperator；它不会成为默认 TenantMember。上述秘密只能存放在受控部署环境或
Secret 中，不得写入 Git、日志或发布制品。

从 `v1.0.0` 前滚到 `v1.1.0` 会把迁移账本从 28 条推进到 50 条，并建立默认 Tenant、
Account/TenantMember/owner、租户化 RBAC/组织和代表业务所有权。升级前成对备份数据库与
`php-storage`，固定部署模式和身份输入，严格按“构建 → 迁移 → 切换”执行。Tenant
所有权、身份映射或复合外键校验失败时保持旧流量，不得先切换新代码。

`--skip-if-installed` 只避免容器重启时重复执行首次安装，不代替版本化数据库迁移。自动升级管理将在独立运营平台实现前保持手动。

`scripts/package-release.sh` 仅保留为管理端 + PHP 的原生制品工具，不是完整三端生产部署方案。生产环境以 Docker Compose 为唯一推荐入口。

正式部署应检出不可变 release tag，并核对根 `RELEASE_METADATA.json`；源码 release 的完整 commit 与 archive SHA-256 以 GitHub Release 附件 `RELEASE_MANIFEST.json` 为准。生产 Nginx 镜像保持源码文件名，把 `/legal/LICENSE`、`/legal/NOTICE`、`/legal/THIRD_PARTY_NOTICES.md`、`/legal/RELEASE_SBOM.spdx.json`、`/legal/CHANGELOG.md` 与 `/legal/RELEASE_METADATA.json` 放入公开目录；PHP 镜像保留同一份 `legal/` 目录。官网静态站为下载友好另使用 `.txt` 后缀。PB09 不发布预构建镜像，这些镜像由部署端从 source tag 本地构建。

## 品牌与官网发布

首次安装前，脚手架默认品牌来自 `server/config/brand.json` 与 `server/public/brand/`，运行 `node scripts/sync-brand-assets.mjs` 后再构建。安装完成后只通过管理端“应用设置 → 网站设置”修改产品名称、各端 logo/favicon、slogan、版权、官网和 GitHub 地址；不要手改生成文件。

官方网站与文档门户位于 `docs-site/`。发布前以目标站点地址构建 sitemap，并把 `.vitepress/dist/` 交给静态托管平台：

```bash
cd docs-site
pnpm install --frozen-lockfile
PEANUT_DOCS_SITE_URL=https://docs.example.com pnpm build
```

域名、Pages 项目名、账号和令牌由目标环境提供，不写入模板默认值。应用网站设置中的 `official_url` 应在站点可访问后再填写。
