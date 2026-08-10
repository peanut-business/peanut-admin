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
# 编辑 .env，填写外部数据库地址、账号密码和 JWT_SECRET

docker compose up -d --build
```

生产镜像是多阶段构建：web 管理端放到 `server/public/admin/`，uniapp H5 放到 `server/public/mobile/`，Nuxt PC 放到 `server/public/pc/`，API 统一走 `/api/`。PHP 容器入口会自动执行可跳过已安装数据库的安装器。可以连接外部 MySQL，也可以为单机部署启用 `bundled-db`；外部地址必须能从生产服务器实际路由。

当前 `peanut-admin.007345.xyz` 演示部署使用 `bundled-db`，因为公网服务器不能直连开发局域网 `192.168.192.2`。2026-08-11 已完成 24 条迁移账本接管和三端生产 smoke；这不会改变其他部署选择外部 MySQL 的能力。

默认服务为 PHP-FPM、Nginx 和后端 scheduler。单机演示需要内置 MySQL 时，将 `DB_HOST=mysql` 并启用 `bundled-db` profile；需要 Redis 时显式启用可选 profile：

```bash
docker compose --profile bundled-db up -d --build
docker compose --profile redis up -d
```

Redis 没有应用依赖边，只在明确接入时启用。外部数据库模式必须填写 `DB_HOST`、应用数据库账号密码和 `JWT_SECRET`；`MYSQL_ROOT_PASSWORD` 只在启用 `bundled-db` 时必填。

Compose 默认把 Nginx 绑定到宿主机 `127.0.0.1:18092`。宝塔面板新增反向代理，目标填写 `http://127.0.0.1:18092`；Cloudflare DNS 对应记录开启代理（橙色云朵）。宝塔站点必须安装覆盖应用域名的有效证书（Cloudflare Origin CA 或自动续期的 Let's Encrypt）并开启 HTTPS，Cloudflare SSL/TLS 模式使用 `Full (strict)`。不要直接暴露 PHP-FPM 或 MySQL。

`peanut-admin-doc.007345.xyz` 专用于 Cloudflare Pages 文档站，不经过宝塔。服务器应用使用 `peanut-admin.007345.xyz`，避免一个 DNS 名称同时绑定 Pages 和源站。

## 已有应用升级

升级必须先备份数据库和 `php-storage` 卷，再按“构建 → 迁移 → 切换”执行，不能先启动依赖新结构的应用代码：

```bash
git pull --ff-only
docker compose build
docker compose run --rm --no-deps php php server/database/migrate.php
docker compose up -d --no-build
```

包含 PB06 的版本会由迁移账本执行 `20260811-content-asset-reference.sql`，扩充文章封面和 Tabbar 图标列以保存完整云/CDN URL。必须保持“先迁移、后切换”顺序；该迁移不搬迁素材对象，也不改写历史相对 URI。

包含 PB07 通知切片的版本会执行 `20260811-notification-host-security.sql`：历史验证码立即失效并从内容快照脱敏，`verify_code` 原位改为 `verify_code_hash`，同时撤销已退出的通用模板写权限菜单。迁移不删除 `pa_notice_template` 历史数据；升级后必须在通知渠道页确认唯一短信 Provider 和四个固定 scene 配置，不能期待旧验证码继续可用。

PB07 支付切片不新增数据库结构，但微信预支付、退款请求和退款查询现在都要求响应带有效的平台证书签名。升级前确认 `wx_pay_platform_cert_path` 指向 PHP-FPM 可读且与微信响应 serial 匹配的当前平台证书；证书缺失、过期或不匹配会 fail closed。部署 smoke 只能使用真实商户沙箱/低风险订单验证，不能关闭验签或手工改成功状态。

PB07 OAuth/渠道切片会执行 `20260811-oauth-channel-host.sql`，删除旧 `channel` 微信/QQ 九字段和 `oa_setting` 中未实现的 AES 两字段；这些行可能包含敏感凭据，迁移前必须完成数据库备份。迁移不删除当前公众号、小程序、开放平台、菜单、回复、OAuth 身份或会员数据。反向代理必须正确传递外部 HTTPS scheme/Host；微信开放平台登记 `/api/oauth/wechat/redirect/pc`，公众号网页授权登记 `/api/oauth/wechat/redirect/official-account`。只有真实凭据、微信平台域名/白名单和一次低风险回跳通过后，才能声明生产 OAuth 可用；当前封存验收没有调用真实微信。

历史安装第一次进入迁移账本时，把普通迁移命令替换为 `php server/database/migrate.php --adopt-existing`。失败时保持旧容器运行，核对 DDL 实际结果并前滚修复。

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
