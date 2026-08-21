# Peanut Admin 发布与部署

> 本文件是 Peanut Admin 源仓的人类可读版本。create-app 会在派生应用的同一路径生成一份
> 应用专属简版，不会复制完整 `docs-site/`。详细说明见公开的
> [部署文档](https://peanut-admin-doc.007345.xyz/deployment)。

## 5 分钟速读

- 3.0 的首次安装使用 fresh 基线；常规更新只替换应用容器，绝不删除数据库或上传卷。
- 默认一套部署对应一个应用实例。一个实例可服务多个 Tenant、客户端和 Module。
- 生产推荐从不可变源码版本构建 Docker Compose；数据库、密钥、文件空间和备份由该实例
  独立拥有。
- `standalone` 与 `multi-tenant` 使用同一 canonical Schema；Standalone 也创建正式默认
  Tenant，不使用 legacy bootstrap。
- 只有 annotated tag、固定资格和独立部署证据齐全时，才能声明对应版本已正式发布并运行。

## 首次部署

### 发布前收口

发布不是“先在分支上验收，再把提交复制到 main”。必须先把发布相关修改合入远端 `main`，
然后从最新 `origin/main` 取最终 commit，在这个提交上运行资格 Gate；资格通过后只能给同一
个 commit 创建 annotated tag，并把资格摘要传给发布脚本：

```bash
scripts/check-release-consistency \
  --candidate <origin-main-commit> \
  --require-main \
  --qualification /absolute/path/to/summary.json

scripts/publish-github-release <version> \
  --qualification /absolute/path/to/summary.json \
  --prepare-only --output /absolute/empty/release-dir
```

发布脚本会再次确认 tag、资格摘要和 `origin/main` 是同一提交，然后生成
`RELEASE_CANDIDATE_LOCK.json`，并将它与源码归档、许可证文件和
`RELEASE_MANIFEST.json` 一起发布。候选锁在 Release 外部保存精确 commit/tree 和资格摘要，
不会把候选自己的 commit 写回候选文件，因此不会再因自引用而重复封存。

### 源仓维护者的无人值守发布脚本

`scripts/deploy-release` 明确区分首次安装、同大版本常规更新和破坏性 fresh。常规更新会
保留数据库与上传卷，并执行不可变、追加式的应用 migration；源仓维护者不要在服务器上调用旧的
`scripts/production-upgrade`。脚本
从本地不可变 annotated tag 生成归档，传输到登记的
`oracle3` 部署目录，保留 `.env` 与备份目录，并按目标选择独立的 Compose project、端口和
数据库资源。它不会通过默认值猜测另一套部署。

```bash
# 首次安装（目标卷必须不存在）；先只核对计划
scripts/deploy-release v3.0.0 --target production-candidate --install --dry-run

# 常规更新：旧服务必须运行，保留数据库与上传卷
scripts/deploy-release v3.0.0 --target production --update --dry-run

# 破坏性 fresh：先核对计划（要求已验证 DB/files 配对备份）
scripts/deploy-release v3.0.0 --target production --fresh \
  --confirm-destroy production --dry-run

# 明确允许破坏性 fresh；候选校验和镜像构建成功后才停止旧服务并删除登记卷
scripts/deploy-release v3.0.0 --target production --fresh \
  --confirm-destroy production --apply
```

| 参数 | 是否必填 | 含义 |
| --- | --- | --- |
| `<vX.Y.Z>` | 是 | 已发布的 annotated tag，例如 `v3.0.0` |
| `--target` | 是 | 只接受登记的 `production` 或 `production-candidate` |
| `--install` / `--update` / `--fresh` | 三选一 | 首次安装、常规应用更新或破坏性重装 |
| `--confirm-destroy <target>` | fresh 必填 | 值必须与 `--target` 完全一致，避免误删另一实例 |
| `--dry-run` / `--apply` | 二选一 | 只展示计划，或实际执行 |
| `--overlay <file.tar>` | 否 | 仅用于登记的演示候选，把有摘要的 demo patch 叠加到正式 tag |

三种模式都会先校验 tag、归档摘要、目标登记和候选 Compose，并在旧服务仍运行时解包和
构建带不可变版本标签的 PHP/Nginx 镜像。`--install` 要求数据库和上传卷不存在，然后安装
完整基线；`--update` 要求旧 PHP/Nginx/cron 正在运行，只执行 `up -d --no-deps php nginx cron`（Compose
仅重建配置或镜像有变化的应用容器），不会执行 `down --volumes`、删除数据库或上传卷；`--fresh`
则必须显式 `--confirm-destroy=<target>`，并验证登记的数据库 dump 与 php-storage 配对备份
后，才允许停止服务、删除登记卷并安装基线。候选解包、配置或镜像构建失败时，旧服务和
数据卷保持不动；脚本的代码归档只写部署根目录，不写持久卷。

### 演示站补丁

演示补丁不改正式 tag，也不进入普通生产默认行为。先从干净提交生成 overlay，再用 fresh
重建可丢弃的 `production-candidate`；脚本会核对 base tag、overlay SHA-256、登记路径和
数据库资源，随后创建 Tenant A/B、独立 TenantMember/Owner、域名绑定和合成数据。

```bash
scripts/build-demo-site-patch v3.0.0 output/deployment/demo-site-v3.0.0.tar

export PEANUT_GENERATED_ADMIN_EMAIL='admin@pa-demo.example'
export PEANUT_GENERATED_ADMIN_PASSWORD='peanut1234'
export PEANUT_GENERATED_PLATFORM_EMAIL='platform@pa-demo.example'
export PEANUT_GENERATED_PLATFORM_PASSWORD='peanut1234'
export PEANUT_DEMO_MODE=enabled
export PEANUT_DEMO_TENANT_A_EMAIL='tenant-a@pa-demo.example'
export PEANUT_DEMO_TENANT_B_EMAIL='tenant-b@pa-demo.example'
export PEANUT_DEMO_SHARED_PASSWORD='peanut1234'
export PEANUT_DEMO_TENANT_A_HOST='pa-tenant-a.007345.xyz'
export PEANUT_DEMO_TENANT_B_HOST='pa-tenant-b.007345.xyz'
export PEANUT_DEMO_DOCS_URL='https://peanut-admin-doc.007345.xyz'

scripts/deploy-release v3.0.0 --target production-candidate --install \
  --overlay output/deployment/demo-site-v3.0.0.tar --apply
```

只有 `PEANUT_DEMO_MODE=enabled` 时，租户登录页才会预填公开演示账号；服务端会拒绝演示账号
修改密码、菜单、角色、管理员、组织、配置、装修，以及 Platform 端的权限和租户关键操作。
演示候选的 bootstrap、Platform 和 Tenant A/B 初始密码统一为 `peanut1234`。关闭该变量后，
正式应用不返回演示凭据，也不限制正常账号修改密码。

fresh 部署必须显式提供管理员邮箱和密码；脚本不会生成或回显密码。它们只写入服务器
root-owned `.env`。演示候选中的 bootstrap 管理员只拥有系统默认 Tenant；Tenant A/B 由演示
补丁分别创建独立 owner，并使用同一组公开演示密码。Tenant A 的 Account 还会成为 Tenant B
的 active owner，因此公共 Admin 使用 Tenant A 账号时可以选择 A/B；Tenant A/B 的绑定 Host
仍只能进入各自 Tenant。未知 Host 不会返回任何演示凭据。

`--fresh` 不带 overlay 时会明确关闭并清空旧演示配置，避免同一部署目录从演示候选变成
普通实例后继续暴露公开账号。overlay 只允许与 `--fresh` 一起使用。

准备应用自己的空数据库和受保护环境文件。至少配置：

```dotenv
DEPLOYMENT_MODE=standalone
PUBLIC_DEFAULT_TENANT_FALLBACK=true
DB_HOST=<database-host>
DB_PORT=3306
DB_NAME=<empty-database>
DB_USER=<application-user>
DB_PASS=<secret>
JWT_SECRET=<openssl rand -hex 32 生成的稳定密钥>
JWT_EXPIRE=7200
TENANT_IDENTIFIER_HMAC_KEY=<at-least-32-bytes>
PLATFORM_IDENTIFIER_HMAC_KEY=<different-at-least-32-bytes>
ADMIN_INITIAL_EMAIL=owner@example.com
ADMIN_INITIAL_PASSWORD=<at-least-12-letters-and-digits>
```

`JWT_SECRET` 没有默认值，必须是至少 32 字节的部署专用随机值，不得复用示例文本。
`JWT_EXPIRE` 是会员 API Token 的唯一有效期配置，默认为 7200 秒。当前会员 Token
固定使用 HS256，并严格校验 `iss`/`aud`/`sub`/`iat`/`nbf`/`exp`；此合同不保留
旧 JWT 的兼容入口，升级后旧 Token 全部失效，会员需重新登录。

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
必要的 PlatformOperator。安装后只运行当前基线检查：

```bash
php server/database/environment-guard.php --current
```

3.0 首次安装仍必须使用空库；跨大版本不得原地升级，必须先备份并走显式 `--fresh` 重建。
同一大版本的普通更新使用 `scripts/deploy-release --update`，由
`php server/database/install.php --migrate --target-version=X.Y.Z` 校验 checksum 并按账本
执行追加 SQL；不得手工修改或删除已应用记录。需要保留旧系统时，继续隔离运行旧实例，并为
3.0 准备独立空库。Plugin Module 自己的 `pa_module_migration` 属于插件生命周期，应用追加
migration 使用 `pa_schema_migration`。

## 发布最低检查

1. 固定源码 commit/tag 与版本元数据一致。
2. 从空库完成一次目标部署模式安装和 `environment-guard.php --current`。
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
故障停止线见公开的 [部署文档](https://peanut-admin-doc.007345.xyz/deployment)。
