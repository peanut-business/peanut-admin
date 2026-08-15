# Peanut Admin

Peanut Admin 是基于 ThinkPHP 8、Vue 3、Element Plus、Nuxt 3 与 UniApp 的企业应用脚手架。
当前源码是 `2.0.0` fresh-only 开发候选，同一代码线支持单实例（`standalone`）和多租户
（`multi-tenant`）部署，覆盖管理端、PC、H5/小程序、Tenant 隔离和实例内平台管理。

[1.x 历史演示应用](https://peanut-admin.007345.xyz) ·
[文档中心](https://peanut-admin-doc.007345.xyz) ·
[1.x 历史 Release](https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.5) ·
[更新日志](CHANGELOG.md)

## 当前稳定能力

- 管理后台：菜单、角色、管理员、部门、岗位、字典、文件、定时任务、日志和系统设置。
- 业务模块：会员、标签、余额、通知、充值退款、文章、装修、热门搜索和客服设置。
- 多端应用：Vue 3 管理端、Nuxt 3 PC、UniApp H5/小程序。
- 多租户：默认 Tenant、可信 TenantContext、Tenant-first 数据访问、缓存/文件/任务/审计隔离。
- 实例内平台管理：独立 PlatformOperator、Tenant 生命周期、首个 owner 和 TenantModule 管理。
- 交付：2.0.0 canonical Schema 空库安装、基线后追加迁移账本和 Docker Compose 生产部署。

`2.0.0` 不支持 1.x 数据库或脚手架原地升级，也不包含套餐、订阅、计费、试用、发票、
应用市场或跨实例运营平台。短信、支付、微信/OAuth 和对象存储仍需部署方提供真实凭据并
完成平台登记。当前候选尚未创建正式 tag、GitHub Release 或生产部署证明。

## 技术栈

| 层 | 技术 |
| --- | --- |
| 后端 | ThinkPHP 8、PHP 8.3、JWT |
| 管理端 | Vue 3、Element Plus、Vite、TypeScript |
| PC | Nuxt 3、Element Plus |
| H5 / 小程序 | UniApp |
| 数据库 | MySQL 8.0.36+ / 8.4 |
| 生产运行 | Docker Compose、Nginx、PHP-FPM |

## 快速开始

### 1. 准备环境

- PHP 8.3、Composer 2.8
- MySQL 8.0.36+ 或 8.4
- Node.js 20/22、pnpm 9

### 2. 创建独立应用与配置

```bash
git clone git@github.com:peanut-business/peanut-admin.git
cd peanut-admin
php scripts/create-app \
  --name="Acme Console" \
  --slug=acme-console \
  --package=acme/acme-console \
  --target=/absolute/path/to/acme-console
cd /absolute/path/to/acme-console
git init
cp server/.env.example server/.env
```

创建器使用完整版本化 inventory，生成 `.peanut/application-manifest.json` 和受管文件基线；
应用业务与稳定 Host/override 入口属于 app-owned，不由 future scaffold 默认接管。直接 clone
仍用于维护 Peanut Admin 参考应用，不再是创建正式下游应用的入口。详见
[创建独立应用](docs/create-application.md)。

编辑 `server/.env`，至少填写数据库连接、`JWT_SECRET` 和下列安装身份：

```dotenv
DEPLOYMENT_MODE=standalone
ADMIN_INITIAL_EMAIL=admin@example.com
ADMIN_INITIAL_PASSWORD=<至少 12 位且同时包含字母和数字>
TENANT_IDENTIFIER_HMAC_KEY=<至少 32 字节的稳定随机值>
PLATFORM_IDENTIFIER_HMAC_KEY=<另一份至少 32 字节的稳定随机值>
```

多租户部署把 `DEPLOYMENT_MODE` 改为 `multi-tenant`，并额外提供与管理员不同的
`PLATFORM_INITIAL_EMAIL` 和 `PLATFORM_INITIAL_PASSWORD`。HMAC 生成后必须稳定保存；随意更换
会使既有身份索引失配。

### 3. 安装与启动

本项目日常开发的唯一默认入口是 `scripts/local-stack.sh`。它使用 Peanut Admin 项目登记的
`/opt/homebrew/bin/php` 8.3.24 与 `/usr/local/bin/composer` 2.8.10 托管宿主 API，Web、PC、
Mobile、Docs 和固定网关可由 development Compose 运行；Docker PHP 仅用于本机生产模式
预览、生产构建和显式容器等价 Gate。

```bash
./scripts/local-stack.sh dev-up
./scripts/local-stack.sh status
```

登记的默认入口为 `http://127.0.0.1:20187/admin/`；API、Web、Mobile、MySQL、PC、Docs 与
本地生产预览的登记默认端口依次为 `20180`、`20181`、`20182`、`20183`、`20185`、
`20186`、`20190`。除唯一数据库 `20183` 外，本地监听均从 `.local/stack.env`（或
`PEANUT_LOCAL_ENV_FILE`）读取；每个 clone/worktree 可覆盖，`ensure_env` 不会重写已有值。
非秘密示例见 `deploy/local-stack.env.example`。停止时运行
`./scripts/local-stack.sh dev-down`，该命令会同时停止容器和受 PID/日志管理的宿主 PHP。
使用安装时提供的管理员邮箱和密码登录。安装器只接受空数据库；1.x 数据库不能原地升级
为 2.0.0，应保留旧实例并为新版本准备独立空库。

## 生产入口

| 入口 | 地址 |
| --- | --- |
| 1.x 历史演示应用 / 管理端 | https://peanut-admin.007345.xyz/admin/ |
| 1.x 历史演示应用 / PC | https://peanut-admin.007345.xyz/pc/ |
| 1.x 历史演示应用 / H5 | https://peanut-admin.007345.xyz/mobile/ |
| 官方文档 | https://peanut-admin-doc.007345.xyz |

生产环境使用根 `compose.yaml`，从不可变 release tag 构建 PHP/Nginx 镜像。首次部署、
`standalone`/`multi-tenant` 配置、空库安装、数据库备份和回滚停止线见
[部署与安装](https://peanut-admin-doc.007345.xyz/deployment)。

## 目录结构

```text
peanut-admin/
├── server/       # ThinkPHP 后端、数据库安装器与迁移
├── web/          # Vue 3 管理端
├── pc/           # Nuxt 3 PC 客户端
├── uniapp/       # UniApp H5 / 小程序
├── docs-site/    # VitePress 官方文档站
├── deploy/       # Docker 与 Nginx 生产配置
└── docs/         # 架构、开发和发布文档
```

## API 与扩展

- 响应：`{"code": 20000, "msg": "ok", "data": {...}}`
- 认证：`Authorization: Bearer <token>`
- 常用错误码：未登录 `40100`、无权限 `40300`、业务错误 `40000`
- 扩展业务应通过应用 Module/Host 接入，不复制 Core Runtime。

完整的认证、权限、公开包和外部回调边界见
[API 与扩展](https://peanut-admin-doc.007345.xyz/api)。

## 文档

- [开始使用](https://peanut-admin-doc.007345.xyz/getting-started)
- [管理员手册](https://peanut-admin-doc.007345.xyz/guide/user-manual)
- [开发指南](https://peanut-admin-doc.007345.xyz/guide/development)
- [部署与安装](https://peanut-admin-doc.007345.xyz/deployment)
- [版本与发布](https://peanut-admin-doc.007345.xyz/releases)

文档源码位于 `docs-site/`，由 Cloudflare Pages 项目 `peanut-admin-docs` 发布到
`peanut-admin-doc.007345.xyz`：

```bash
cd docs-site
pnpm install --frozen-lockfile
PEANUT_DOCS_SITE_URL=https://peanut-admin-doc.007345.xyz pnpm build
npx wrangler pages deploy .vitepress/dist --project-name=peanut-admin-docs --branch=main
```

## 版本与许可证

当前源码版本为 `2.0.0` 开发候选，尚未正式发布。最后一个已封存的 1.x 历史版本是
[`v1.1.5`](https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.5)；它的发布附件
继续作为历史证据，不代表 2.0.0 已通过发布验收。

Peanut Admin 应用当前采用专有 / All Rights Reserved 许可；公开 Core 包维持 Apache-2.0。
具体边界见 [LICENSE](LICENSE)、[NOTICE](NOTICE) 和
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md)。
