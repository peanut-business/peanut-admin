# Peanut Admin 生产发布与部署

生产部署只采用一条主路径：服务器拉取已经开发完成的应用仓，使用 Docker Compose 构建并启动，宝塔反代本机端口，Cloudflare 代理服务器域名。服务器不是用模板创建新应用的地方。

## 发布内容

一次生产构建包含全部运行入口：

| URL | 来源 | 运行方式 |
|---|---|---|
| `/admin/` | `web/` 管理端 | 构建到 Nginx 镜像的 `server/public/admin/` |
| `/mobile/` | `uniapp/` H5 | 构建到 Nginx 镜像的 `server/public/mobile/` |
| `/pc/` | `pc/` Nuxt 3 | 静态构建到 Nginx 镜像的 `server/public/pc/` |
| `/api/` | `server/` ThinkPHP | PHP-FPM |
| `/storage/` | 后端文件 | PHP 与 Nginx 共享持久卷 |

三个前端只写入各自子目录，不替换 `server/public/index.php`、服务器规则或存储目录。三套客户端统一使用同源 `/api/`。

## 服务器要求

- Git
- Docker Engine
- Docker Compose v2
- 宝塔 Nginx（只做外层反向代理和证书）

宿主机不需要安装 PHP、Composer、Node.js 或 pnpm。Node.js 只存在于 Docker 构建阶段，不是生产运行时服务。Redis 默认不启动。

运行版本见 [`deploy/VERSIONS.md`](../deploy/VERSIONS.md)。

## 首次部署

```bash
git clone git@github.com:peanut-business/peanut-admin.git
cd peanut-admin
cp deploy/production.env.example deploy/production.env
chmod 600 deploy/production.env
```

编辑 `deploy/production.env`，至少替换 `DB_PASS`、`MYSQL_ROOT_PASSWORD` 和 `JWT_SECRET`。该文件已被 Git 和 Docker 构建上下文排除。

然后只执行：

```bash
docker compose \
  --env-file deploy/production.env \
  -f deploy/docker-compose.prod.yml \
  up -d --build
```

Compose 会启动 MySQL、PHP-FPM、Nginx 和定时任务。空数据库由 PHP 入口安全初始化；已有完整数据库不会重复安装。首次安装后的管理员账号为 `admin / admin123456`，登录后立即修改密码。

最低检查：

```bash
docker compose --env-file deploy/production.env \
  -f deploy/docker-compose.prod.yml ps
curl -fsS http://127.0.0.1:18082/healthz
```

### 可选构建代理

生产服务器可以直接访问软件源时，不要设置 `BUILD_*_PROXY`。确需代理时，代理地址必须能从 Docker builder 访问，不能使用 builder 自身的 `127.0.0.1`。Compose 会将同一配置同时写入大小写代理变量，以兼容 apt、Composer、npm 和 pnpm：

```dotenv
BUILD_HTTP_PROXY=http://<docker-builder-visible-host>:7890
BUILD_HTTPS_PROXY=http://<docker-builder-visible-host>:7890
BUILD_ALL_PROXY=http://<docker-builder-visible-host>:7890
```

Docker Desktop 可通过 `docker buildx inspect --bootstrap` 查看 `moby.host-gateway-ip`，再确认该地址的代理端口可访问。

### 已验证基线

2026-08-07 在独立 Compose 项目和全新 MySQL 卷中完成一次生产构建与启动：`/healthz`、`/admin/`、`/mobile/`、`/pc/` 均返回 HTTP 200；首次安装生成 42 张表、1 个默认超级管理员、170 个菜单和 59 项配置。验证后已删除测试容器、网络和卷。

## 宝塔与 Cloudflare

宝塔创建站点并将全部请求反向代理到：

```text
http://127.0.0.1:18082
```

Compose 只监听宿主机回环地址，不直接暴露 PHP-FPM、MySQL 或 PC 容器端口。宝塔负责公网 80/443 和源站证书。

Cloudflare 中将应用域名的 A/AAAA 记录指向服务器公网地址并开启代理。Cloudflare 访问宝塔，宝塔再访问本机 `18082`；本方案不需要 Cloudflare Tunnel 容器。

## Redis

当前应用没有 Redis 硬依赖。确有需要时才启用：

```bash
docker compose --env-file deploy/production.env \
  -f deploy/docker-compose.prod.yml \
  --profile redis up -d
```

## 后续升级

升级由应用 release tag 驱动。正式升级前先备份 MySQL 与 `php-storage` 卷，阅读该版本迁移清单并执行尚未应用的迁移，再重建容器：

```bash
git fetch --tags
git checkout <release-tag>
docker compose --env-file deploy/production.env \
  -f deploy/docker-compose.prod.yml \
  up -d --build
```

`--skip-if-installed` 只避免容器重启时重复执行首次安装，不代替版本化数据库迁移。自动升级管理将在独立运营平台实现前保持手动。

`scripts/package-release.sh` 仅保留为管理端 + PHP 的原生制品工具，不是完整三端生产部署方案。生产环境以 Docker Compose 为唯一推荐入口。
