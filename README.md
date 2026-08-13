# Peanut Admin

Peanut Admin 是基于 ThinkPHP 8、Vue 3、Element Plus、Nuxt 3 与 UniApp 的企业应用脚手架。
同一份 `1.1.0` release 同时支持单实例（`standalone`）和多租户（`multi-tenant`）部署，
覆盖管理端、PC、H5/小程序、Tenant 隔离和实例内平台管理。

[在线应用](https://peanut-admin.007345.xyz) ·
[文档中心](https://peanut-admin-doc.007345.xyz) ·
[v1.1.0 Release](https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.0) ·
[更新日志](CHANGELOG.md)

## 当前稳定能力

- 管理后台：菜单、角色、管理员、部门、岗位、字典、文件、定时任务、日志和系统设置。
- 业务模块：会员、标签、余额、通知、充值退款、文章、装修、热门搜索和客服设置。
- 多端应用：Vue 3 管理端、Nuxt 3 PC、UniApp H5/小程序。
- 多租户：默认 Tenant、可信 TenantContext、Tenant-first 数据访问、缓存/文件/任务/审计隔离。
- 实例内平台管理：独立 PlatformOperator、Tenant 生命周期、首个 owner 和 TenantModule 管理。
- 交付：空库安装、`v1.0.0 → v1.1.0` 前滚、50 条迁移账本和 Docker Compose 生产部署。

`1.1.0` 是稳定多租户应用脚手架，不包含套餐、订阅、计费、试用、发票、应用市场或
跨实例运营平台。短信、支付、微信/OAuth 和对象存储仍需部署方提供真实凭据并完成平台登记。

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

### 2. 获取源码与配置

```bash
git clone git@github.com:peanut-business/peanut-admin.git
cd peanut-admin
cp server/.env.example server/.env
```

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

本项目日常开发的唯一默认入口是 `scripts/local-stack.sh`。它使用 CompanyOS 登记的
`/opt/homebrew/bin/php` 8.3.24 与 `/usr/local/bin/composer` 2.8.10 托管宿主 API，Web、PC、
Mobile、Docs 和固定网关可由 development Compose 运行；Docker PHP 仅用于本机生产模式
预览、生产构建和显式容器等价 Gate。

```bash
./scripts/local-stack.sh dev-up
./scripts/local-stack.sh status
```

默认入口为 `http://127.0.0.1:8080/admin/`；API、Web、PC、Mobile、Docs 与生产预览固定
端口依次为 `8000`、`5173`、`3100`、`5174`、`4173`、`18092`。停止时运行
`./scripts/local-stack.sh dev-down`，该命令会同时停止容器和受 PID/日志管理的宿主 PHP。
使用账号 `admin` 和安装时提供的密码登录。
安装器只接受空数据库；已有安装必须按[部署与升级文档](https://peanut-admin-doc.007345.xyz/deployment)
执行备份和前滚迁移，不能重新运行空库安装器。

## 生产入口

| 入口 | 地址 |
| --- | --- |
| 应用首页 / 管理端 | https://peanut-admin.007345.xyz/admin/ |
| PC 客户端 | https://peanut-admin.007345.xyz/pc/ |
| H5 客户端 | https://peanut-admin.007345.xyz/mobile/ |
| 官方文档 | https://peanut-admin-doc.007345.xyz |

生产环境使用根 `compose.yaml`，从不可变 release tag 构建 PHP/Nginx 镜像。首次部署、
`standalone`/`multi-tenant` 配置、数据库备份、前滚顺序和回滚停止线见
[部署与升级](https://peanut-admin-doc.007345.xyz/deployment)。

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
- [部署与升级](https://peanut-admin-doc.007345.xyz/deployment)
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

当前稳定版本为 [`v1.1.0`](https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.0)。
发布附件包含源码归档、Release manifest、许可证、第三方告知和 SPDX SBOM。

Peanut Admin 应用当前采用专有 / All Rights Reserved 许可；公开 Core 包维持 Apache-2.0。
具体边界见 [LICENSE](LICENSE)、[NOTICE](NOTICE) 和
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md)。
