# Peanut Admin v2

基于 ThinkPHP 8 + Arco Design Pro Vue 的企业后台管理脚手架，完整复刻 likeadmin 标准版能力。

## 技术栈

| 层 | 技术 |
|---|---|
| 后端 | ThinkPHP 8、PHP 8.1+、JWT（firebase/php-jwt） |
| 前端 | Arco Design Pro Vue 3、Vite、TypeScript |
| 数据库 | MySQL 8 |

## 快速开始

### 1. 克隆项目

```bash
git clone <repo-url> && cd peanut-v2
```

### 2. 初始化数据库

```bash
mysql -u root -p -e "CREATE DATABASE peanut_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p peanut_admin < server/database/init.sql
```

`init.sql` 包含全部表结构、菜单种子和默认管理员（`admin / admin123456`）。

### 3. 配置后端

```bash
cd server
cp .env.example .env   # 填写 DB_HOST / DB_NAME / DB_USER / DB_PASS / JWT_SECRET
composer install
```

### 4. 启动

```bash
# 后端（端口 8000）
cd server && php think run --host 0.0.0.0 --port 8000

# 前端（端口 5173，/api/* 自动代理到 :8000）
cd web && pnpm install && pnpm dev
```

浏览器打开 `http://localhost:5173`，账号 `admin / admin123456`（**首次登录请修改密码**）。

## 目录结构

```
peanut-v2/
├── server/                  # ThinkPHP 8 后端
│   ├── app/
│   │   ├── adminapi/        # 管理后台 API（controller / logic）
│   │   ├── api/             # 用户端 API
│   │   └── common/          # 公共 model / service / enum
│   ├── config/              # 框架配置
│   ├── database/init.sql    # 一键初始化脚本
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

1. `cd web && pnpm build` — 产物在 `dist/`，部署到 Nginx 并配置 `try_files $uri /index.html`
2. 后端配置 PHP-FPM，入口指向 `server/public/index.php`
3. 确保 `server/runtime/` 目录可写
