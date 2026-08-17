# Peanut Admin 2.0 发布与部署

> 本文件随 create-app 进入派生应用。详细说明位于 `docs-site/deployment.md`。

## 5 分钟速读

- 2.0 是 fresh-only：只向空数据库安装，不接管 1.x 表、migration ledger 或 scaffold。
- 默认一套部署对应一个应用实例。一个实例可服务多个 Tenant、客户端和 Module。
- 生产推荐从不可变源码版本构建 Docker Compose；数据库、密钥、文件空间和备份由该实例
  独立拥有。
- `standalone` 与 `multi-tenant` 使用同一 canonical Schema；Standalone 也创建正式默认
  Tenant，不使用 legacy bootstrap。
- 当前应用尚未完成正式发布时，不得把开发分支、未提交 worktree 或历史 1.x 生产记录写成
  production-ready。

## 首次部署

准备应用自己的空数据库和受保护环境文件。至少配置：

```dotenv
DEPLOYMENT_MODE=standalone
DB_HOST=<database-host>
DB_PORT=3306
DB_NAME=<empty-database>
DB_USER=<application-user>
DB_PASS=<secret>
JWT_SECRET=<stable-secret>
TENANT_IDENTIFIER_HMAC_KEY=<at-least-32-bytes>
PLATFORM_IDENTIFIER_HMAC_KEY=<different-at-least-32-bytes>
ADMIN_INITIAL_EMAIL=owner@example.com
ADMIN_INITIAL_PASSWORD=<at-least-12-letters-and-digits>
```

多租户模式将 `DEPLOYMENT_MODE` 改为 `multi-tenant`，并增加与管理员不同的
`PLATFORM_INITIAL_EMAIL` 和 `PLATFORM_INITIAL_PASSWORD`，同时配置：

```dotenv
PLATFORM_HOSTS=platform.example.com
TENANT_ADMIN_HOSTS=admin.example.com
OWNER_INVITATION_DELIVERY_MODE=auto
```

Tenant 专属 Host 在 Platform 中动态绑定。未知 Host 会被应用拒绝；`auto` 在生产要求真实
邀请投递 Provider，私有部署可显式改为 `manual` 并由平台操作员人工交付一次性链接。
安装完成后使用 `ADMIN_INITIAL_EMAIL` 对应的邮箱登录；2.0 不提供共享用户名或默认凭据。

```bash
cp .env.example .env
chmod 600 .env
# 编辑 .env 后：
docker compose up -d --build
docker compose ps
curl -fsS http://127.0.0.1:18092/healthz
```

生产 Compose 构建 Tenant Admin、独立 Platform、H5、PC 和 PHP Runtime；Nginx 统一暴露
`/admin/`、`/platform/`、`/mobile/`、`/pc/`、`/api/` 与 `/storage/`。多租户部署还必须让
`PLATFORM_HOSTS`、公共 `TENANT_ADMIN_HOSTS` 和 Tenant 绑定 Host 保留原始 Host。MySQL、PHP-FPM
和内部服务端口不得直接暴露公网。

## 数据库边界

`server/database/install.php` 创建 Core Schema、应用 `init.sql`、默认 Tenant、首 owner 和
必要的 PlatformOperator。安装后只运行：

```bash
php server/database/migrate.php --current
```

禁止执行或恢复 1.x 的 `--adopt-existing`、legacy Admin/Role/Dept map、默认 Tenant
bootstrap、余额双写或旧 scaffold upgrade Runtime。需要保留旧系统时，继续隔离运行旧
实例，并为 2.0 准备独立空库；业务数据迁移必须另立字段映射、校验和回滚方案。
旧接管和升级步骤只随对应 1.x tag、Release 与文档快照保留为历史证据，不得复制到 2.0
部署流程，也不得把旧 migration 数量、`admin` 用户名或 scaffold identity 当作当前默认值。

2.0 基线后的 SQL 只允许追加 migration。不要改写已登记 SQL 或删除账本。发布前创建数据库
与文件存储的同一时点备份；若新 Schema 已不兼容旧 Runtime，不能直接切回旧镜像，只能
继续前滚修复或恢复配对备份。

## 发布最低检查

1. 固定源码 commit/tag 与版本元数据一致。
2. 从空库完成一次目标部署模式安装和 `migrate.php --current`。
3. 管理员登录、菜单/RBAC、Tenant 切换或 Standalone 默认 Tenant 正常。
4. 使用第二 Tenant 验证一个列表、详情和写操作均 fail closed；绑定 Tenant Host 不允许切换。
5. `/admin/`、`/platform/`、`/mobile/`、`/pc/`、`/api/`、上传和导出入口可访问，Platform API
   只从 `PLATFORM_HOSTS` 访问。
6. 日志、回调和错误响应不泄露密码、token、证书或 Provider secret。
7. 当前 Module lock、资源登记、数据库和构建制品属于同一固定候选。

真实支付、短信、OAuth 和对象存储必须分别配置真实 Provider，并完成对应部署 smoke 后才能
宣称可用。源码存在 adapter 或页面不等于外部能力已经开通。

## 反向代理

Compose 默认把 Nginx 绑定到宿主回环地址 `127.0.0.1:18092`。外层 Nginx、负载均衡器或
等价入口终止 HTTPS，并转发正确的 Host 与 scheme。证书必须覆盖实际应用域名；数据库与
PHP-FPM 不对公网开放。

同一实例可以把 Platform、公共 Admin 和多个 Tenant 域名都反向代理到同一 origin。绑定域名
是持续 Tenant 边界：登录、选择和后续 API 均必须匹配绑定 Tenant，并禁止切换到其他 Tenant；
未绑定公共 Admin 才允许账号在自己的 active TenantMember 列表中切换。

完整 Compose profile、原生发布备选、Nginx location、定时任务、品牌同步、发布后检查和
故障停止线见 `docs-site/deployment.md`。
