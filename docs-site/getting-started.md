---
title: 快速开始
description: 从仓库 checkout 到可验证本地开发栈的最短路径。
---

# 快速开始

## 前置条件

- PHP 8.3、Composer 2、Docker Compose、Node.js 20 和 pnpm 9。
- 一个明确选择的数据库。Peanut Admin 仓库维护者必须使用项目资源登记；派生应用维护自己的环境登记。
- 首次安装使用空数据库，并准备独立的管理员邮箱和强密码。

## 目标

启动 API、Admin、Platform、PC、移动端与本站的本地开发入口，并用项目状态命令确认它们来自同一个 checkout。

## 1. 准备代码与依赖

```bash
git clone https://github.com/peanut-business/peanut-admin.git
cd peanut-admin
cp .env.example .env
cp server/.env.example server/.env
chmod 600 .env server/.env
composer install --working-dir=server
```

根 `.env` 只控制编排；PHP、数据库、身份和 Module 配置只写在 `server/.env`。不要提交这两个文件。

## 2. 配置安装模式与数据库

至少填写：

```dotenv
DEPLOYMENT_MODE=standalone
PEANUT_INSTALLATION_MODE=automatic
```

同时填写 `DB_HOST`、`DB_PORT`、`DB_NAME`、`DB_USER` 和 `DB_PASS`。初始身份不得写入
`server/.env`；多租户部署还需要独立的 Platform 身份与 Host 配置。完整字段以
`server/.env.example` 为准。

仓库维护者在使用数据库、端口或服务前，先执行：

```bash
./scripts/project-resource-registry validate
```

不要依次尝试默认主机、端口或密码，也不要把共享开发库当成空库。

## 3. 安装空库

```bash
ADMIN_INITIAL_EMAIL=you@example.com \
ADMIN_INITIAL_PASSWORD='<your-strong-password>' \
php server/database/install.php
```

成功结果是 canonical Schema、当前增量 migration、官方 Module 选择和首次身份均由唯一
安装 Host 完成，且命令不回显密码。若目标非空或 checksum 不一致，停止并选择正确资源；
不要清空未知数据库。人工部署也可在 `server/.env` 选择 `guided` 并配置高熵 setup token，
启动后访问 `/admin/installation`；页面不接收数据库连接信息，也不保存 token 或密码。

## 4. 启动开发栈

```bash
./scripts/local-stack.sh dev-up
./scripts/local-stack.sh status
```

脚本从项目登记生成本地编排参数，并打印本次实际的 localhost 入口。端口可以由 worktree 的 `.local/stack.env` 覆盖，因此不要从旧文档猜端口。

## 验证

- `status` 显示宿主 PHP Runtime 与开发 Compose 服务正常。
- 打开命令打印的 Admin 地址，使用你刚设置的管理员身份登录。
- 请求命令打印的 API 入口不会返回网关连接错误。

停止本次开发栈：

```bash
./scripts/local-stack.sh dev-down
```

## 下一步

先读[核心概念](/guide/concepts)，再从[开发总览](/guide/development)选择后端、前端或 Module 路径。
