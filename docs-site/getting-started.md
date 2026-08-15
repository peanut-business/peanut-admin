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

cd server
cp .env.example .env
# 编辑 .env，填写 DB_*、随机 JWT_SECRET、ADMIN_INITIAL_EMAIL 和 ADMIN_INITIAL_PASSWORD
composer install
cd ..
```

创建一个空的 MySQL 数据库：

```bash
mysql -u root -p -e "CREATE DATABASE peanut_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

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

默认打开 `http://127.0.0.1:20187/admin/`，使用安装时提供的管理员邮箱和密码登录；
首次登录后请改为个人凭据。本地监听来自 `.local/stack.env`（或
`PEANUT_LOCAL_ENV_FILE`），其他 clone/worktree 可覆盖登记默认端口。停止服务运行
`./scripts/local-stack.sh dev-down`。

## 下一步

- 需要理解目录和分层时，阅读[开发与部署指南](/guide/development)。
- 需要执行后台业务操作时，阅读[管理员使用手册](/guide/user-manual)。
- 只查接口响应和认证规则时，阅读[API 约定](/api)。
