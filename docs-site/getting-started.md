---
title: 开始使用
description: Peanut Admin 本地开发环境的安装与启动步骤。
---

# 开始使用

本页给出从仓库到可登录开发环境的最短路径。生产环境请同时阅读[部署清单](/deployment)和[开发与部署指南](/guide/development)。

## 环境准备

- PHP 8.3。
- Composer。
- MySQL 8。
- Node.js 与 pnpm（管理端前端使用）。

支付和 OAuth 场景还需要 cURL、OpenSSL；XLSX 导出需要 ZipArchive；手机号和中文校验使用 mbstring。

## 克隆与配置

```bash
git clone <repo-url>
cd peanut-admin
cp .env.example .env
# 编辑根 .env，填写 DB_*、随机 JWT_SECRET、ADMIN_INITIAL_EMAIL 和 ADMIN_INITIAL_PASSWORD
cd server && composer install && cd ..
```

根 `.env` 是生产 Compose 的唯一人工配置样例；`PHP_*` 只由启动器自动派生给 ThinkPHP，
不要手工维护第二份 `server/.env`。项目维护开发环境仍先选择资源登记中的空 MySQL 数据库。Peanut Admin 维护仓的日常开发资源是
`peanut-admin-mysql84-development`；本地多租户体验使用隔离的
`peanut-admin-mysql84-local-multi-tenant-demo`。先取得对应 lease，再由资源选择器写出
非秘密连接信息；凭据只从登记的 credential reference 注入：

```bash
./scripts/project-resource-registry validate
./scripts/project-resource-registry database-env \
  --deployment-target local-development --consumer host
```

不要依次尝试 `localhost`、默认端口或默认 root 密码，也不要静默创建未登记数据库。

安装基础结构、增量迁移和种子数据：

```bash
export ADMIN_INITIAL_PASSWORD='<至少 12 位且同时包含字母和数字>'
export ADMIN_INITIAL_EMAIL='owner@example.com'
php server/database/install.php
```

安装器只接受空数据库，创建默认 Tenant、原生 Account/TenantMember 和首 owner，且不会回显
初始密码。2.0.0 不支持接管 1.x 数据库；目标库已有任何表时停止并换用已登记的空库。
安装后可执行 `php server/database/migrate.php --current` 校验 canonical `init.sql` 与基线后
追加 migration 的 SHA-256。不要手工改写账本或已登记 SQL。

## 启动服务

日常开发使用登记的宿主 PHP/Composer，并由项目脚本统一管理 PID、日志和健康：

```bash
./scripts/local-stack.sh dev-up
./scripts/local-stack.sh status
```

登记的默认网关为 `http://127.0.0.1:20187/admin/`，独立 Platform 为
`http://127.0.0.1:20177/platform/`；直接开发端口分别为 Admin `20181`、API `20180`。
使用安装时提供的管理员邮箱和密码登录 Tenant Admin；Platform 使用独立的
`PLATFORM_INITIAL_EMAIL`/`PLATFORM_INITIAL_PASSWORD`。两套身份不能互换。
首次登录后请改为个人凭据。本地监听来自 `.local/stack.env`（或
`PEANUT_LOCAL_ENV_FILE`），其他 clone/worktree 可覆盖登记默认端口。停止服务运行
`./scripts/local-stack.sh dev-down`。

本项目维护者使用隔离的多租户体验环境时，`/etc/hosts` 中登记的四个名称都指向
`127.0.0.1`：Platform `platform.peanut-admin.test`、公共 Admin
`admin.peanut-admin.test`、两个 Tenant 入口 `tenant-a.peanut-admin.test` 与
`tenant-b.peanut-admin.test`。Platform 使用端口 `20176`，三个 Admin 入口使用 `20179`，
API 使用 `20178`。这些域名、端口和 demo 数据库必须由同一个
`local-multi-tenant-demo` lease 持有，不能与日常 development 数据库混用。

在 lease 已固定到当前提交后，使用项目脚本准备私有 env、应用当前 migration 并启动三个入口：

```bash
./scripts/local-multi-tenant-demo prepare
./scripts/local-multi-tenant-demo up
./scripts/local-multi-tenant-demo status
./scripts/local-multi-tenant-demo credentials
```

脚本不会创建替代数据库，也不会打印数据库密码。`credentials` 只按本地体验要求显示合成的
Tenant Owner 与 PlatformOperator 账号密码；停止时运行 `./scripts/local-multi-tenant-demo down`。

## 下一步

- 需要理解目录和分层时，阅读[开发与部署指南](/guide/development)。
- 需要执行后台业务操作时，阅读[管理员使用手册](/guide/user-manual)。
- 只查接口响应和认证规则时，阅读[API 约定](/api)。
- 需要创建 Tenant、邀请 Owner 或配置域名时，阅读[实例平台管理](/platform)。
