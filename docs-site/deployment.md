---
title: 部署与安装
description: Peanut Admin 2.0.0 的应用实例边界、Docker 部署、空库安装与回滚停止线。
---

# 部署与安装

Peanut Admin 的生产部署面向已经存在的应用仓。服务器只需要 Git 和 Docker Compose；生产 Compose 在容器内完成 web 管理端、uniapp H5、Nuxt PC、PHP 依赖和服务启动，宿主机不需要 Node.js、PHP 或 Composer。

## 5 分钟速读

- 默认一套部署对应一个应用实例，拥有自己的数据库、密钥、文件和生命周期。
- 一个实例可以有多个 Tenant、客户端和 Module；多个实例不能共享私有业务表。
- 2.0.0 是 fresh-only 主版本：新应用从空数据库安装，不支持 1.x 数据库或脚手架原地升级。
- canonical `init.sql` 是完整应用 Schema；`migrations/` 只保存 2.0.0 基线之后的追加变更。
- 管理身份直接使用 Account/Credential/TenantMember/RBAC，不创建 legacy 映射或兼容 Admin 表。
- 旧 tag、Release、迁移和升级证据仍可追溯，但不进入当前 Runtime、Schema、create-app 或日常操作路径。

## 应用实例和部署边界

Application 是可独立发布的代码产品，Application Instance 是它在某个环境的一次部署。
同一 Docker 主机、Kubernetes 集群或云账号可以运行多个实例，但每个实例仍要有显式资源
ID、数据库/schema/namespace、密钥和备份责任。共享基础设施不等于共享应用数据。

默认优先在一个实例内使用多个 Tenant、TenantModule 和客户端。只有法律/合同隔离、地区
合规、安全或故障域、独立发布回滚、独立团队 owner 或产品生命周期要求成立时，才拆成
多个应用实例。跨实例协作使用受控 API/事件，不直连数据库。

## 版本范围

- PHP 8.3.x。
- MySQL 8.0.36+ 或 8.4.x。
- Nginx 1.24+；Redis 7.x 可选且默认不启动。
- 构建机使用 Node.js 20/22、pnpm 9 和 Composer 2.8。

Node.js、pnpm 和 Composer 只在开发机或 Docker 构建容器中使用。三个前端均构建为静态目录，生产运行时不需要 Node.js。原生发布包仍可作为不使用 Docker 时的备选。

## 部署模式与身份输入

Standalone 和多租户能力来自同一 2.0.0 代码线。每个部署必须显式设置
`DEPLOYMENT_MODE=standalone` 或 `DEPLOYMENT_MODE=multi-tenant`；缺失值或其他拼写会按
fail-closed 处理。两种模式都要为 `TENANT_IDENTIFIER_HMAC_KEY` 与
`PLATFORM_IDENTIFIER_HMAC_KEY` 提供彼此独立、至少 32 字节的稳定随机值，发布后不能随意
更换，否则现有身份索引无法继续匹配。

首次安装必须提供 `ADMIN_INITIAL_EMAIL` 和 `ADMIN_INITIAL_PASSWORD`。多租户模式另需
提供与管理员邮箱不同的
`PLATFORM_INITIAL_EMAIL` 和至少 12 位、同时含字母与数字的
`PLATFORM_INITIAL_PASSWORD`；它们只建立独立 PlatformOperator，不会把该身份加入默认
Tenant。秘密值只保存在权限受控的部署环境文件/Secret 中，不写进 Git 或日志。

多租户部署还必须显式配置 `PLATFORM_HOSTS` 与 `TENANT_ADMIN_HOSTS`。前者只允许实例平台
控制面，后者是可切换 Tenant 的公共管理入口；Tenant 专属域名由 Platform 绑定，不重复写入
`.env`。未知 Host、在 Tenant 域调用 Platform API、在 Platform 域调用 Tenant Admin API
都会由应用层拒绝，不能只依赖外层 Nginx 默认站点。

## Docker 生产部署（推荐）

生产和开发 Compose 严格分离。根目录 `compose.yaml` 是生产入口，并引用 `deploy/docker-compose.prod.yml`；开发环境使用 `deploy/docker-compose.dev.yml`，不要混用。首次部署时拉取已经存在的应用仓、复制受保护的环境文件，然后只执行一条构建并启动命令：

```bash
git clone git@github.com:peanut-business/peanut-admin.git /srv/peanut-admin
cd /srv/peanut-admin
cp .env.example .env
chmod 600 .env
# 编辑 .env，填写数据库、JWT_SECRET、部署模式、两项 HMAC；
# 空库还要填写 ADMIN_INITIAL_EMAIL / ADMIN_INITIAL_PASSWORD；
# multi-tenant 另填 PLATFORM_INITIAL_EMAIL / PLATFORM_INITIAL_PASSWORD、
# PLATFORM_HOSTS / TENANT_ADMIN_HOSTS 和 OWNER_INVITATION_DELIVERY_MODE

docker compose up -d --build
```

生产镜像是多阶段构建：Tenant Admin 放到 `server/public/admin/`，独立 Platform 放到
`server/public/platform/`，UniApp H5 放到 `server/public/mobile/`，Nuxt PC 放到
`server/public/pc/`，API 统一走 `/api/`。PHP 容器入口会自动执行可跳过已安装数据库的安装器。
可以连接外部 MySQL，也可以为单机部署启用 `bundled-db`；外部地址必须能从生产服务器实际路由。

外部 MySQL 地址必须能从 PHP 容器实际路由；不要把开发局域网地址写成生产默认值。单机部署可使用 `bundled-db`，多机部署则显式提供数据库主机并在 MySQL 侧限制来源。

默认服务为 PHP-FPM、Nginx 和后端 scheduler。单机演示需要内置 MySQL 时，将 `DB_HOST=mysql` 并启用 `bundled-db` profile；需要 Redis 时显式启用可选 profile：

```bash
docker compose --profile bundled-db up -d --build
docker compose --profile redis up -d
```

Redis 没有应用依赖边，只在明确接入时启用。外部数据库模式必须填写 `DB_HOST`、应用数据库账号密码和 `JWT_SECRET`；`MYSQL_ROOT_PASSWORD` 只在启用 `bundled-db` 时必填。

Compose 默认把 Nginx 绑定到宿主机 `127.0.0.1:18092`。使用 Nginx、宝塔或等价入口反向代理到该地址，并为实际应用域名安装有效证书；使用 Cloudflare 时 SSL/TLS 模式采用 `Full (strict)`。不要直接暴露 PHP-FPM 或 MySQL。

文档站若发布到 Cloudflare Pages，不经过应用服务器。应用与文档可使用独立域名或同站分区；同一个 DNS 名称不要同时绑定 Pages 和应用源站。

## Fresh-only 基线

2.0.0 只有一条数据库路径：空库安装。`install.php` 创建 Core Schema、应用 canonical
`init.sql`、默认 Tenant、首个 Tenant owner，以及多租户模式下可选的 PlatformOperator。
安装后执行 `migrate.php --current` 只核对 `init.sql` 与基线后追加 migration 的 checksum。

```bash
export ADMIN_INITIAL_EMAIL='owner@example.com'
export ADMIN_INITIAL_PASSWORD='<至少 12 位且同时包含字母和数字>'
php server/database/install.php
php server/database/migrate.php --current
```

禁止把 1.x 数据库交给上述安装器或迁移器，也不要复制 1.x 的表、数据、migration ledger、
scaffold baseline 或 app-owned 文件到 2.0.0 生成物。需要保留旧环境时并行运行旧版本实例，
通过显式业务导出/导入项目迁移必要数据；本版本不提供通用迁移工具。

### 当前 Schema 边界

- 不存在 `pa_legacy_*_tenant_map`、`pa_default_tenant_bootstrap`、旧 Admin/RBAC 双模型。
- 管理端只从原生 Tenant session 建立 Account/TenantMember/TenantContext。
- 业务会员 `pa_member` 继续独立于管理 Account，不把客户档案塞进登录身份。
- 会员余额和流水各保留一个权威字段，不执行兼容双写。
- `pa_jobs` 是应用岗位字典，不是旧身份映射表；成员组织关系使用 Core Department/RBAC。
- 当前交付的文件、通知、OAuth、支付、会员、任务、导入导出和文章能力都必须满足相同的
  Tenant 隔离 Gate，不能以“可选模块”为由接受单租户实现。

## Tenant 域名与客户端入口

生产可以使用共享登录入口后由成员选择 Tenant，也可以由域名限定 Tenant。Platform 中配置的
`host + client_key -> tenant` 是本实例唯一入口映射；当前 `client_key` 为 `admin-web` 和
`member-api`。反向代理必须把浏览器原始 `Host` 传给 PHP，不能固定改写为内部容器名。

部署负责人仍需提供：实际域名清单、DNS、覆盖所有入口的 TLS 证书和反向代理 Host 规则。
Owner 邀请默认 `OWNER_INVITATION_DELIVERY_MODE=auto`，生产环境必须接入真实投递 Provider；
私有部署也可显式设置 `manual`，由具备权限的 PlatformOperator 复制只显示一次的邀请链接。
人工模式不会把明文 Token 写数据库或日志，也不能冒充邮件已发送。缺少这些信息时不要反复尝试线上部署；本地可在 hosts 中把测试域名指向
`127.0.0.1`，再从 Platform 创建对应绑定。绑定冲突、禁用绑定、暂停 Tenant 或显式
`tenant_code` 与 Host 不一致时都会拒绝登录，不会猜测其他 Tenant。
启用系统代理时还要把 `*.peanut-admin.test` 加入代理绕过列表；否则即使 hosts 解析正确，
浏览器仍可能从代理收到 503，无法证明 Tenant Host Runtime 是否正确。

绑定是持续边界：登录、challenge 选择、管理 Token 后续请求都必须匹配同一 Tenant，绑定
入口禁止切换；只有未绑定公共入口允许账号在自己的 TenantMember 列表中切换。一个站点可以
把多个域名代理到同一实例 origin，不需要为每个 Tenant 重复部署应用；DNS、TLS 和外层 Nginx
只负责保留各自 Host，Tenant 归属仍由实例内绑定表决定。

Tenant 与 Platform access token 都是 15 分钟短会话，浏览器使用各自独立的 HttpOnly refresh
Cookie 自动轮换；两条 token 前缀、Cookie、会话表和 RBAC 不能互换。刷新只接受同源浏览器
请求，绑定 Tenant 域刷新后仍要继续满足同一 Host 边界。

Platform 默认与当前实例同库同部署，但使用独立 `/platform/` 前端、会话、RBAC 和审计。
它不是管理端中的一个业务子应用，也不是跨多个 Peanut 实例的运营平台。

### 状态说明

2.0.0 当前是开发候选。隔离的 `production-candidate` 已使用独立空库、Compose project、
origin 和四个域名上线；Platform、公共 Admin、DNS、TLS、Host 保留和反向代理已验证。
当前生产候选使用登记的人工 Owner 邀请交付模式，已创建第二 Tenant，并完成两个 Tenant
域名的应用内持续绑定；跨域名或错误 Tenant 账号登录会被拒绝。该线上体验验收不能替代
正式双模式 P0-E、annotated tag 或 GitHub Release。

| 候选体验入口 | 地址 |
| --- | --- |
| 实例平台 | `https://pa-platform.007345.xyz/platform/` |
| 公共管理端 | `https://pa-admin.007345.xyz/admin/` |
| Tenant A 绑定入口 | `https://pa-tenant-a.007345.xyz/admin/` |
| Tenant B 绑定入口 | `https://pa-tenant-b.007345.xyz/admin/` |

邮件 Provider 是自动投递 Owner 邀请的可选生产集成，不是 Tenant 创建或域名绑定的 Runtime
前置。人工模式下必须通过受控渠道交付一次性邀请 Token；不要把 `APP_ENV` 降级为
development，也不要在生产响应、日志或版本库中暴露 Token。

### 回滚停止线

- 2.0.0 基线后的数据库迁移只前滚，不提供自动 down migration；不要删除账本记录、改写已登记 SQL，或假定 MySQL DDL 会随事务完整回滚。
- 只有新迁移对旧应用保持向后兼容时，才可把应用镜像切回上一版本；切回前仍需确认后台任务、缓存和三端静态资源与旧版本匹配。
- 如果迁移已产生旧应用不能读取的结构或数据，立即停止写流量，不启动旧镜像；只能修复并继续前滚，或恢复发布前同一时点的数据库与 `php-storage` 成对备份。
- 真实支付、通知或 OAuth 外部状态不能靠数据库恢复撤销；恢复前先停止对应回调/任务并人工对账。

## 原生发布包（备选）

无法使用 Docker 时，可以在构建机生成原生发布包，再交给 PHP-FPM 和 Nginx 主机：

```bash
./scripts/package-release.sh release/peanut-admin-<version>
```

脚本按 lockfile 构建 `web/dist/`，复制到发布暂存目录的 `server/public/admin/`，安装生产 Composer 依赖，并生成目录和 `.tar.gz`。发布包不包含 `.env`、Node 依赖和运行日志。该备选脚本当前只包含管理端；完整三端发布以 Docker 多阶段方案为唯一推荐路径。

Nginx 根目录指向发布制品的 `server/public/`：

```nginx
location = / {
    return 302 /admin/;
}

location = /admin {
    return 302 /admin/;
}

location ~ ^/admin/login/(?:login|logout)/?$ {
    try_files $uri /index.php?$query_string;
}

location ^~ /api/ {
    try_files $uri /index.php?$query_string;
}

location /admin/ {
    try_files $uri $uri/ /admin/index.html;
}

location /platform/ {
    try_files $uri $uri/ /platform/index.html;
}

location /mobile/ {
    try_files $uri $uri/ /mobile/index.html;
}

location /pc/ {
    try_files $uri $uri/ /pc/index.html;
}

location / {
    try_files $uri $uri/ /admin/index.html;
}

location ^~ /storage/ {
    try_files $uri =404;
}
```

Tenant Admin、Platform、H5 和 PC 静态文件分别位于 `server/public/admin/`、
`server/public/platform/`、`server/public/mobile/` 和 `server/public/pc/`；`/` 重定向到
`/admin/`，`/api/` 与管理登录路由进入 ThinkPHP。实际域名、PHP-FPM socket、目录和 HTTPS
证书按目标环境配置。

## 周期任务

原生部署通过 ThinkPHP Console 和系统 cron 执行周期任务；Docker 生产配置已包含同等调度器。原生调度器每分钟执行一次：

```cron
* * * * * cd /var/www/peanut-admin/server && /usr/bin/php think crontab >> /var/log/peanut-crontab.log 2>&1
```

命令必须来自 `server/config/console.php` 中的显式注册项。

## 品牌与官网发布

首次安装前，脚手架默认品牌来自 `server/config/brand.json` 与 `server/public/brand/`。修改后运行 `node scripts/sync-brand-assets.mjs`，再构建四端和官网；安装完成后只通过管理端“应用设置 → 网站设置”修改 Runtime 品牌，不手改生成 JSON 或静态副本。

官方网站与文档门户位于 `docs-site/`。目标站点地址只在构建时注入，用于 sitemap canonical host：

```bash
cd docs-site
pnpm install --frozen-lockfile
PEANUT_DOCS_SITE_URL=https://docs.example.com pnpm build
```

省略 `PEANUT_DOCS_SITE_URL` 仍可本地构建。域名、静态托管项目名、账号和令牌由目标环境提供，不写成模板默认值；站点可访问后再在网站设置中填写 `official_url`。

官方文档站使用以下固定发布目标：

```bash
PEANUT_DOCS_SITE_URL=https://peanut-admin-doc.007345.xyz pnpm build
npx wrangler pages deploy .vitepress/dist --project-name=peanut-admin-docs --branch=main
```

## 发布后检查

正式部署应检出不可变 release tag，并核对根 `RELEASE_METADATA.json`；源码 release 的完整 commit 与 archive SHA-256 以 GitHub Release 附件 `RELEASE_MANIFEST.json` 为准。应用生产入口保持源码文件名，必须能取得 `/legal/LICENSE`、`/legal/NOTICE`、`/legal/THIRD_PARTY_NOTICES.md` 与 `/legal/RELEASE_SBOM.spdx.json`；本站法律下载区为下载友好另使用 `.txt` 后缀。PB09 不发布预构建 PHP/Nginx 镜像。

- 确认服务器没有使用开发 Compose，Tenant Admin、Platform、H5 与 PC 均由生产 Compose 构建并运行。
- 确认 `/`、`/admin/`、`/platform/`、`/mobile/`、`/pc/` 和 `/api/` 的入口分别符合路由契约。
- 登录并确认管理端菜单与当前角色一致。
- 在多租户模式下确认 Platform Host、公共 Admin Host 与一个绑定 Tenant Host 均保留原始 Host；错误
  Tenant Token、错误 Tenant 登录或绑定入口切换必须被拒绝。
- 检查 `/api` 请求、上传和导出目录权限。
- 用受限账号验证一个列表、详情和写操作，确认权限拒绝仍返回 `40300`。
- 确认日志、支付和渠道配置中没有泄露密钥。
- 已有数据库升级前先备份并核对迁移清单；不得再次运行空库安装器。
