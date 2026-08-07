---
title: 部署清单
description: Peanut Admin 生产发布前的配置与检查清单。
---

# 部署清单

Peanut Admin 的生产部署面向已经存在的应用仓。服务器只需要 Git 和 Docker Compose；生产 Compose 在容器内完成 web 管理端、uniapp H5、Nuxt PC、PHP 依赖和服务启动，宿主机不需要 Node.js、PHP 或 Composer。

## 版本范围

- PHP 8.3.x。
- MySQL 8.0.36+ 或 8.4.x。
- Nginx 1.24+；Redis 7.x 可选且默认不启动。
- 构建机使用 Node.js 20/22、pnpm 9 和 Composer 2.8。

Node.js、pnpm 和 Composer 只在开发机或 Docker 构建容器中使用。三个前端均构建为静态目录，生产运行时不需要 Node.js。原生发布包仍可作为不使用 Docker 时的备选。

## Docker 生产部署（推荐）

生产和开发 Compose 严格分离。根目录 `compose.yaml` 是生产入口，并引用 `deploy/docker-compose.prod.yml`；开发环境使用 `deploy/docker-compose.dev.yml`，不要混用。首次部署时拉取已经存在的应用仓、复制受保护的环境文件，然后只执行一条构建并启动命令：

```bash
git clone git@github.com:peanut-business/peanut-admin.git /srv/peanut-admin
cd /srv/peanut-admin
cp .env.example .env
chmod 600 .env
# 编辑 .env，填写数据库密码和 JWT_SECRET

docker compose up -d --build
```

生产镜像是多阶段构建：web 管理端放到 `server/public/admin/`，uniapp H5 放到 `server/public/mobile/`，Nuxt PC 放到 `server/public/pc/`，API 统一走 `/api/`。PHP 容器入口会自动执行可跳过已安装数据库的安装器，不需要额外启动 MySQL 或手动 `run` 安装命令。

默认服务为 MySQL、PHP-FPM、Nginx 和后端 scheduler。需要 Redis 时显式启用可选 profile：

```bash
docker compose --profile redis up -d
```

Redis 没有应用依赖边，只在明确接入时启用。生产数据库密码、MySQL root 密码和 `JWT_SECRET` 均为必填项。

Compose 默认把 Nginx 绑定到宿主机 `127.0.0.1:18082`。宝塔面板新增反向代理，目标填写 `http://127.0.0.1:18082`；Cloudflare DNS 对应记录开启代理（橙色云朵）。不要直接暴露 PHP-FPM 或 MySQL。

## 原生发布包（备选）

无法使用 Docker 时，可以在构建机生成原生发布包，再交给 PHP-FPM 和 Nginx 主机：

```bash
./scripts/package-release.sh release/peanut-admin-<version>
```

脚本按 lockfile 构建 `web/dist/`，复制到发布暂存目录的 `server/public/admin/`，安装生产 Composer 依赖，并生成目录和 `.tar.gz`。发布包不包含 `.env`、Node 依赖和运行日志。该备选脚本当前只包含管理端；完整三端发布以 Docker 多阶段方案为唯一推荐路径。

Nginx 根目录指向发布制品的 `server/public/`：

```nginx
location = / {
    return 302 /admin/;
}

location = /admin {
    return 302 /admin/;
}

location ~ ^/admin/login/(?:login|logout)/?$ {
    try_files $uri /index.php?$query_string;
}

location ^~ /api/ {
    try_files $uri /index.php?$query_string;
}

location /admin/ {
    try_files $uri $uri/ /admin/index.html;
}

location /mobile/ {
    try_files $uri $uri/ /mobile/index.html;
}

location /pc/ {
    try_files $uri $uri/ /pc/index.html;
}

location / {
    try_files $uri $uri/ /admin/index.html;
}

location ^~ /storage/ {
    try_files $uri =404;
}
```

管理端、H5 和 PC 静态文件分别位于 `server/public/admin/`、`server/public/mobile/` 和 `server/public/pc/`；`/` 重定向到 `/admin/`，`/api/` 和 legacy `/admin/login/*` 进入 ThinkPHP。实际域名、PHP-FPM socket、目录和 HTTPS 证书按目标环境配置。

## 周期任务

原生部署通过 ThinkPHP Console 和系统 cron 执行周期任务；Docker 生产配置已包含同等调度器。原生调度器每分钟执行一次：

```cron
* * * * * cd /var/www/peanut-admin/server && /usr/bin/php think crontab >> /var/log/peanut-crontab.log 2>&1
```

命令必须来自 `server/config/console.php` 中的显式注册项。

## 发布后检查

- 确认服务器没有使用开发 Compose，三端均由生产 Compose 构建并运行。
- 确认 `/`、`/admin/`、`/mobile/`、`/pc/` 和 `/api/` 的入口分别符合路由契约。
- 登录并确认管理端菜单与当前角色一致。
- 检查 `/api` 请求、上传和导出目录权限。
- 用受限账号验证一个列表、详情和写操作，确认权限拒绝仍返回 `40300`。
- 确认日志、支付和渠道配置中没有泄露密钥。
- 已有数据库升级前先备份并核对迁移清单；不得再次运行空库安装器。
