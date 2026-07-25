# Peanut Admin v2

基于 ThinkPHP 8 + Arco Design Pro Vue 的企业后台管理系统。

## 技术栈

- **后端**：ThinkPHP 8 + PHP 8.1+ + JWT（firebase/php-jwt）
- **前端**：Vue 3 + Arco Design Pro Vue + Vite

## 目录结构

```
peanut-v2/
  server/    ← 后端（ThinkPHP 8）
  web/       ← 前端（Arco Design Pro Vue）
```

## 快速开始

### 后端

```bash
cd server
cp .env.example .env      # 填写数据库配置
mysql -u root -p < database/init.sql   # 初始化数据库
composer install
php think run             # 启动（默认 8000 端口）
```

### 前端

```bash
cd web
pnpm install
pnpm dev                  # 启动（默认 5173 端口，自动代理 /api 到 8000）
```

### 默认账号

- 账号：`admin`
- 密码：`admin123456`

## API 规范

- 响应格式：`{"code": 20000, "msg": "success", "data": {...}}`
- 认证：`Authorization: Bearer <token>`
- 未登录错误码：`40100`
- 无权限错误码：`40300`
