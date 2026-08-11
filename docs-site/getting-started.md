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
# 编辑 .env，填写 DB_*、随机 JWT_SECRET 和首次安装用 ADMIN_INITIAL_PASSWORD
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
php server/database/install.php
```

安装器只接受空数据库，创建管理员 `admin`，且不会回显初始密码。已有环境不要重复运行首次安装器，也不要设置该变量：先完成数据库与存储备份，尚未进入迁移账本的历史安装只执行一次 `php server/database/migrate.php --adopt-existing`，已接管环境及后续发布执行 `php server/database/migrate.php`。命令会按账本处理未登记迁移并校验 SHA-256；不要手工改写账本或已登记迁移。

## 启动服务

终端 A 启动 ThinkPHP 后端：

```bash
cd server
php think run --host 0.0.0.0 --port 8000
```

终端 B 启动管理端前端：

```bash
cd web
pnpm install
pnpm dev
```

打开 `http://localhost:5173`，使用管理员 `admin` 和安装时提供的密码登录；首次登录后请改为个人凭据。

## 下一步

- 需要理解目录和分层时，阅读[开发与部署指南](/guide/development)。
- 需要执行后台业务操作时，阅读[管理员使用手册](/guide/user-manual)。
- 只查接口响应和认证规则时，阅读[API 约定](/api)。
