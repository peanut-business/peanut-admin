# Peanut Admin

基于 ThinkPHP 8、Vue 3 与 Element Plus 的企业后台管理应用模板。LikeAdmin 1.9.4 标准版 parity 已按仓库封存合同完成；应用暂时采用专有 / All Rights Reserved，第三方组件继续受各自许可证约束。

## 项目身份与当前基线

- 产品名称：Peanut Admin（无版本后缀）
- GitHub 仓库：`peanut-business/peanut-admin`
- PC package name：`peanut-admin-pc`
- 集成分支：`dev`；稳定分支：`main`
- LikeAdmin parity：已完成并独立验证，证据见 `output/playwright/v02/`；后续不重复验收
- 数据库基线：`server/database/install.php` + `server/database/migrate.php` + `init.sql` + 50 个 migrations
- 当前稳定基线：同一 `1.1.0` release 支持 `standalone` 与 `multi-tenant`；空库与 `v1.0.0` 前滚均已通过 81 张表、50 条迁移账本的集中验收
- 多租户边界：已交付 Tenant 隔离、实例内 PlatformOperator/Tenant 生命周期、租户会话与代表业务闭环；订阅计费和跨实例运营平台不在本 release 内

## 技术栈

| 层 | 技术 |
|---|---|
| 后端 | ThinkPHP 8、PHP 8.3、JWT（firebase/php-jwt） |
| 管理端 | Vue 3、Element Plus、Vite、TypeScript |
| PC | Nuxt 3、Element Plus |
| H5 / 小程序 | UniApp |
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
cp .env.example .env   # 填写 DB_* / JWT_SECRET / ADMIN_INITIAL_PASSWORD
composer install
cd ..
php server/database/install.php
```

安装器只接受空数据库。首次安装前必须通过环境变量或 `server/.env` 显式设置至少 12 位且同时包含字母和数字的 `ADMIN_INITIAL_PASSWORD`；安装器不会回显密码。它按顺序执行基础结构和全部迁移，并校验完整表结构、菜单、配置及管理员 `admin`。已有数据库不得运行首次安装器：先完成数据库与存储备份，尚未进入迁移账本的历史安装只执行一次 `php server/database/migrate.php --adopt-existing`，已接管环境及后续发布执行 `php server/database/migrate.php`。不要手工改写账本或已登记迁移。

### 4. 启动

```bash
# 后端（端口 8000）
cd server && php think run --host 0.0.0.0 --port 8000

# 前端（端口 5173，/api/* 自动代理到 :8000）
cd web && pnpm install && pnpm dev
```

浏览器打开 `http://localhost:5173/admin/`，使用账号 `admin` 和安装时提供的 `ADMIN_INITIAL_PASSWORD` 登录（**首次登录后建议立即改为个人凭据**）。

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
├── web/                     # Vue 3 + Element Plus 管理端
    └── src/
        ├── api/             # axios 请求封装
        ├── views/           # 页面组件
        └── router/          # 前端路由
├── pc/                      # Nuxt 3 PC 客户端
├── uniapp/                  # UniApp H5 / 小程序客户端
└── docs-site/               # 独立 VitePress 文档站
```

## 已实现模块

**系统管理**：菜单、角色、管理员（含个人中心）、部门、岗位、字典、文件管理、定时任务、操作日志、系统维护、网站配置、存储设置（本地 / 七牛 / 阿里 OSS / 腾讯 COS）

**会员**：会员列表（CRUD + 余额调整）、会员标签

**通知**：短信渠道、四个固定验证码场景、发送日志

**财务**：账户流水、充值记录、退款记录

**内容**：文章分类、文章管理

**应用设置**：支付配置（微信 / 支付宝）、渠道配置（公众号 / 小程序 / 开放平台 / H5）、页面装修、热门搜索、客服设置、交易设置

## API 规范

- 响应格式：`{"code": 20000, "msg": "ok", "data": {...}}`
- 认证：`Authorization: Bearer <token>`
- 未登录：`40100`，无权限：`40300`，业务错误：`40000`

## 生产部署

生产部署面向已经存在的应用仓，不在服务器重新克隆模板创建应用。服务器只安装 Git 和 Docker，拉取应用 release、在根目录 `.env` 配置可从容器路由的 MySQL 后执行 `docker compose up -d --build`；宿主机不需要 Node.js、PHP 或 Composer。同一 Compose 构建分别生成 PHP 运行镜像和包含管理端 `web/`、PC 端 `pc/`、UniApp H5 `uniapp/` 静态产物的 Nginx 镜像。单机部署可使用 `bundled-db` profile。

同一入口分别提供 `/admin/`（管理端静态 SPA）、`/pc/`（Nuxt 静态 SPA）、`/mobile/`（UniApp H5）、`/api/`（ThinkPHP）和 `/storage/`。三个前端都写入各自子目录，不覆盖后端 public 根文件。完整命令、版本范围和首次部署流程见 `docs/peanut-admin-release-deployment.md`。

## 文档站

Peanut Admin 官方网站与文档门户位于 `docs-site/`。站点包含产品首页、能力与场景、快速开始、开发、部署升级、API/扩展、管理员手册和版本信息；构建使用：

```bash
cd docs-site
pnpm install --frozen-lockfile
PEANUT_DOCS_SITE_URL=https://docs.example.com pnpm build
```

`PEANUT_DOCS_SITE_URL` 只用于生成目标环境的 sitemap canonical host；省略时仍可完成本地构建。部署平台、项目名和公开域名由目标环境决定，不作为脚手架默认值提交。

## 许可证与发布告知

Peanut Admin 应用版权主体显示为“花生科技”，package manifests 使用 `proprietary/UNLICENSED` 语义；两个公开核心包仍为 Apache-2.0。源码使用边界见 [LICENSE](LICENSE)，来源与第三方告知见 [NOTICE](NOTICE) 和 [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md)，完整五锁图依赖库存见 [RELEASE_SBOM.spdx.json](RELEASE_SBOM.spdx.json)。

应用 `1.1.0` 的变更、升级要求和边界见 [CHANGELOG.md](CHANGELOG.md)；正式发布后以 annotated [`v1.1.0`](https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.0) 与同 tag GitHub Release 为不可变身份，附件摘要见 Release 的 `RELEASE_MANIFEST.json`。历史 `v1.0.0` 保持不变。

## 目标架构

管理端 Element Plus、两个公开核心包、标准覆盖 Host、PC/UniApp 无 UI client、三端 Docker、品牌单一 Runtime、官网/文档门户和 PB08B 正式候选集成验收已经完成。产品无关且已获采用授权的能力由核心包拥有；会员/财务、内容/装修、支付/OAuth 等产品领域由应用 Module 唯一拥有。PB09 的许可证策略、发布授权、根法律文件、第三方告知、SBOM、`dev`/`main` 合入、annotated tag、GitHub Release、既有应用/官网部署和一次最低线上 smoke 均已完成；产品化正式基线至此封存。契约见 `docs/architecture/application-package-and-release-contract.md`，执行队列见 `docs/productization-baseline-plan.md`。
