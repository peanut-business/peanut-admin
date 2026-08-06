# Peanut Admin

基于 ThinkPHP 8 + Arco Design Pro Vue 的企业后台管理脚手架，完整复刻 likeadmin 标准版能力。

## 项目身份与当前基线

- 产品名称：Peanut Admin（无版本后缀）
- 当前工作目录：`/Users/xing/Documents/company-projects/peanut-admin`
- GitHub 仓库：`peanut-business/peanut-admin`
- PC package name：`peanut-admin-pc`
- 当前主线：`main` 已完成并推送 LikeAdmin 1.9.4 标准版 parity 的 9 个 commits；已完成使命的功能分支不再作为后续工作基线
- 数据库基线：`server/database/install.php` + `init.sql` + 23 个 migrations，共 42 张表
- 独立验证：42 tables / 170 menus / 59 configs / 1 default admin
- SaaS 多租户：仅为 `docs/design/saas-roadmap/` 中的 roadmap 设计，当前代码尚未实现

## 技术栈

| 层 | 技术 |
|---|---|
| 后端 | ThinkPHP 8、PHP 8.1+、JWT（firebase/php-jwt） |
| 前端 | Arco Design Pro Vue 3、Vite、TypeScript |
| 数据库 | MySQL 8 |

## 快速开始

### 1. 克隆项目

```bash
git clone <repo-url> && cd peanut-admin
```

### 2. 初始化数据库

```bash
mysql -u root -p -e "CREATE DATABASE peanut_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. 配置后端

```bash
cd server
cp .env.example .env   # 填写 DB_HOST / DB_NAME / DB_USER / DB_PASS / JWT_SECRET
composer install
cd ..
php server/database/install.php
```

安装器只接受空数据库，按顺序执行基础结构和全部迁移，并校验完整表结构、菜单、配置及默认管理员（`admin / admin123456`）。已有数据库不得运行首次安装器，应备份后按 `server/database/migrations/` 的文件名顺序执行尚未应用的迁移。

### 4. 启动

```bash
# 后端（端口 8000）
cd server && php think run --host 0.0.0.0 --port 8000

# 前端（端口 5173，/api/* 自动代理到 :8000）
cd web && pnpm install && pnpm dev
```

浏览器打开 `http://localhost:5173/admin/`，账号 `admin / admin123456`（**首次登录请修改密码**）。

## 目录结构

```
peanut-admin/
├── server/                  # ThinkPHP 8 后端
│   ├── app/
│   │   ├── adminapi/        # 管理后台 API（controller / logic）
│   │   ├── api/             # 用户端 API
│   │   └── common/          # 公共 model / service / enum
│   ├── config/              # 框架配置
│   ├── database/install.php # 空数据库首次安装器
│   ├── database/init.sql    # 基础结构和种子
│   ├── database/migrations/ # 增量业务迁移
│   └── route/app.php        # 全部路由
└── web/                     # Arco Design Vue 3 前端
    └── src/
        ├── api/             # axios 请求封装
        ├── views/           # 页面组件
        └── router/          # 前端路由
```

## 已实现模块

**系统管理**：菜单、角色、管理员（含个人中心）、部门、岗位、字典、文件管理、定时任务、操作日志、系统维护、网站配置、存储设置（本地 / 七牛 / 阿里 OSS / 腾讯 COS）

**会员**：会员列表（CRUD + 余额调整）、会员标签

**通知**：渠道配置（短信 / 邮件）、消息模板、发送日志

**财务**：账户流水、充值记录、退款记录

**内容**：文章分类、文章管理

**应用设置**：支付配置（微信 / 支付宝）、渠道配置（公众号 / 小程序）、页面装修、热门搜索、客服设置、交易设置

## API 规范

- 响应格式：`{"code": 20000, "msg": "ok", "data": {...}}`
- 认证：`Authorization: Bearer <token>`
- 未登录：`40100`，无权限：`40300`，业务错误：`40000`

## 生产部署

生产部署面向已经存在的应用仓，不在服务器重新克隆模板创建应用。推荐服务器只安装 Git 和 Docker，拉取应用 release 后由 `deploy/docker-compose.prod.yml` 在容器内完成构建和启动；宿主机不需要 Node.js、PHP 或 Composer。同一 Compose 构建分别生成 PHP 运行镜像和包含管理端 `web/`、PC 端 `pc/`、UniApp H5 `uniapp/` 静态产物的 Nginx 镜像。

同一入口分别提供 `/admin/`（管理端静态 SPA）、`/pc/`（Nuxt 静态 SPA）、`/mobile/`（UniApp H5）、`/api/`（ThinkPHP）和 `/storage/`。三个前端都写入各自子目录，不覆盖后端 public 根文件。完整命令、版本范围和首次部署流程见 `docs/peanut-admin-release-deployment.md`。

## 目标架构

当前代码仍是已完成业务验收的 Arco/应用内实现。已确认的下一阶段目标是 Web 管理端统一 Element Plus，应用只直接安装一个 Composer 核心包和一个 npm 管理端核心包，并以标准注册表支持应用覆盖；迁移完成前不将这些目标描述为现成功能。契约见 `docs/architecture/application-package-and-release-contract.md`。
