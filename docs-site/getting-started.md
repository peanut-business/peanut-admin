---
title: 开始使用
description: Peanut Admin 本地开发环境的安装与启动步骤。
---

# 开始使用

本页给出从仓库到可登录开发环境的最短路径。生产环境请同时阅读[部署清单](/deployment)和[开发与部署指南](/guide/development)。

## 5 分钟速读

| 你要做什么 | 使用入口 | 预期结果 |
| --- | --- | --- |
| 从脚手架创建独立应用 | `scripts/create-app` | 得到独立仓库目录、应用身份和文件所有权清单 |
| 第一次安装本地开发库 | 空库安装器 | 得到默认 Tenant、首 owner 和可登录 Admin |
| 启动日常开发环境 | `local-stack.sh dev-up` | 固定端口的 API、Web 和网关可用 |
| 体验多租户域名 | `local-multi-tenant-demo` | Platform、公共 Admin、Tenant A/B 四个入口 |
| 部署生产 | [部署与安装](/deployment) | 独立应用实例、数据库和域名；不要复用本地 demo |

第一次执行命令前，先看[参数表与统一停止线](/guide/reading-guide)。

## 创建独立应用

正式消费脚手架时使用当前正式的 `v2.1.4` Tag/Release，不从移动的 `dev` 分支或带未提交修改的
维护工作树生成应用。

| 参数 | 必填 | 示例 | 作用 |
| --- | --- | --- | --- |
| `--name` | 是 | `Acme Console` | 人类可读产品名 |
| `--slug` | 是 | `acme-console` | 小写 kebab-case 应用标识 |
| `--package` | 是 | `acme/acme-console` | Composer 风格的应用身份 |
| `--target` | 是 | `/absolute/path/to/acme-console` | 不存在或为空的绝对目录 |
| `--application-version` | 否 | `0.1.0` | 派生应用自己的版本，不是 Peanut 版本 |

```bash
php scripts/create-app \
  --name="Acme Console" \
  --slug=acme-console \
  --package=acme/acme-console \
  --target=/absolute/path/to/acme-console
```

生成结果包含 `.peanut/application-manifest.json` 和 managed baseline，可以证明采用了哪个
Release、每个文件归谁所有。当前已有 `v2.0.0 -> v2.0.1` 的派生应用资格，`v2.0.1 -> v2.1.4`
随本版本继续使用相同的 preflight/apply/verify/recover 合同；执行器
只更新受管文件并保留 `app-owned` 代码。Peanut 依赖、数据库 migration 和业务 migration
仍需按发布计划由各自 owner 执行，不能把脚手架升级误解成业务数据迁移。

## 环境准备

- PHP 8.3。
- Composer。
- MySQL 8。
- Node.js 与 pnpm（管理端前端使用）。

支付和 OAuth 场景还需要 cURL、OpenSSL；XLSX 导出需要 ZipArchive；手机号和中文校验使用 mbstring。

## 克隆与配置

### 前置条件

- 已确认当前 checkout 和目标环境。
- 数据库资源已在 `resources/project-resources.json` 登记、健康，并且用于首次安装时为空。
- 凭据从登记的 credential reference 注入，不写入命令历史或仓库。

```bash
git clone <repo-url>
cd peanut-admin
cp .env.example .env
# 编辑根 .env，填写 DB_*、随机 JWT_SECRET、ADMIN_INITIAL_EMAIL 和 ADMIN_INITIAL_PASSWORD
cd server && composer install && cd ..
```

根 `.env` 是生产 Compose 的唯一人工配置样例；`PHP_*` 只由启动器自动派生给 ThinkPHP，
不要手工维护第二份 `server/.env`。空库安装必须使用应用 owner 登记并确认为空的目标；
Peanut Admin 维护仓的 `peanut-admin-mysql84-development` 是持久开发数据，不能假定为空。
需要重建本地多租户体验时，使用隔离的
`peanut-admin-mysql84-local-multi-tenant-demo`，并先取得对应 lease。资源选择器只写出非秘密
连接信息；凭据只从登记的 credential reference 注入：

```bash
./scripts/project-resource-registry validate
./scripts/project-resource-registry database-env \
  --deployment-target local-development --consumer host
```

不要依次尝试 `localhost`、默认端口或默认 root 密码，也不要静默创建未登记数据库。

### 空库安装参数

| 参数 | 必填 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `ADMIN_INITIAL_EMAIL` | 是 | 无 | 首个 Tenant owner 的登录邮箱 |
| `ADMIN_INITIAL_PASSWORD` | 是 | 无 | 至少 6 位 |
| `DEPLOYMENT_MODE` | 是 | 无 | 只能是 `standalone` 或 `multi-tenant` |
| `DB_*` | 是 | 无 | 来自已登记的空数据库资源 |
| `PLATFORM_INITIAL_*` | 仅多租户 | 无 | 独立 PlatformOperator；不能与 owner 邮箱相同 |

安装基础结构、增量迁移和种子数据：

```bash
export ADMIN_INITIAL_PASSWORD='<至少 6 位>'
export ADMIN_INITIAL_EMAIL='owner@example.com'
php server/database/install.php
```

安装器只接受已登记且已确认为空的数据库，创建默认 Tenant、原生 Account/TenantMember 和首
owner，且不会回显初始密码。2.0.0 不支持接管 1.x 数据库；目标库已有任何表时停止，先登记并
选择新的空库，不能把共享开发库重置为安装目标。
安装后可执行 `php server/database/migrate.php --current` 校验 canonical `init.sql` 与基线后
追加 migration 的 SHA-256。不要手工改写账本或已登记 SQL。

预期结果：安装器明确完成 canonical Schema、默认 Tenant 和首 owner 创建，并且不会回显密码。
如果它报告数据库非空或 checksum 不一致，立即停止；不要清空共享库或改写 migration 来“继续”。

## 启动服务

日常开发使用登记的宿主 PHP/Composer，并由项目脚本统一管理 PID、日志和健康：

```bash
./scripts/local-stack.sh dev-up
./scripts/local-stack.sh status
```

| 命令 | 作用 | 成功标志 |
| --- | --- | --- |
| `dev-up` | 启动登记的开发栈 | 命令无错误退出 |
| `status` | 查看固定端口和健康状态 | API、Web、网关均为 running/healthy |
| `dev-down` | 停止本 worktree 的开发栈 | 对应进程/容器停止，lease 可释放 |

登记的默认网关为 `http://127.0.0.1:20187/admin/`，独立 Platform 为
`http://127.0.0.1:20177/platform/`；直接开发端口分别为 Admin `20181`、API `20180`。
使用安装时提供的管理员邮箱和密码登录 Tenant Admin；Platform 使用独立的
`PLATFORM_INITIAL_EMAIL`/`PLATFORM_INITIAL_PASSWORD`。两套身份不能互换。
首次登录后请改为个人凭据。本地监听来自 `.local/stack.env`（或
`PEANUT_LOCAL_ENV_FILE`），其他 clone/worktree 可覆盖登记默认端口。停止服务运行
`./scripts/local-stack.sh dev-down`。

本项目维护者使用隔离的多租户体验环境时，`/etc/hosts` 中登记的四个名称都指向
`127.0.0.1`：Platform `platform.peanut-admin.test`、公共 Admin
`admin.peanut-admin.test`、两个 Tenant 入口 `tenant-a.peanut-admin.test` 与
`tenant-b.peanut-admin.test`。Platform 使用端口 `20176`，三个 Admin 入口使用 `20179`，
API 使用 `20178`。这些域名、端口和 demo 数据库必须由同一个
`local-multi-tenant-demo` lease 持有，不能与日常 development 数据库混用。

在 lease 已固定到当前提交后，使用项目脚本准备私有 env、应用当前 migration 并启动三个入口：

```bash
./scripts/local-multi-tenant-demo prepare
./scripts/local-multi-tenant-demo up
./scripts/local-multi-tenant-demo status
./scripts/local-multi-tenant-demo credentials
```

预期结果：Platform 入口只能使用 PlatformOperator；公共 Admin 可在账号已加入的 Tenant 中选择；
Tenant A/B 绑定入口只能进入各自 Tenant。浏览器验收还要人工检查图片、文字、加载状态和交互，
不能只以 HTTP 200 判断通过。

脚本不会创建替代数据库，也不会打印数据库密码。`credentials` 只按本地体验要求显示合成的
Tenant Owner 与 PlatformOperator 账号密码；停止时运行 `./scripts/local-multi-tenant-demo down`。
如果电脑启用了 HTTP/HTTPS 系统代理，还必须把 `*.peanut-admin.test` 加入代理绕过列表；
`/etc/hosts` 只负责解析到 `127.0.0.1`，不能阻止浏览器把这些请求交给代理。

### 常见失败

| 现象 | 先检查什么 | 不要做什么 |
| --- | --- | --- |
| `*.test` 打不开或出现代理错误 | hosts 解析、系统代理绕过、登记端口 | 不要改用随机域名或关闭 Tenant Host 校验 |
| 一个账号能看到多个 Tenant | 查看该 Account 的 active TenantMember | 不要误认为 PlatformOperator 自动加入了 Tenant |
| 绑定入口不能切换 Tenant | 这是持续 Host 边界 | 不要在前端强行显示切换按钮 |
| Platform 登录失败 | 使用独立 PlatformOperator 凭据和 Platform Host | 不要拿 Tenant owner Token 调 Platform API |

## 下一步

- 需要理解目录和分层时，阅读[开发与部署指南](/guide/development)。
- 需要执行后台业务操作时，阅读[管理员使用手册](/guide/user-manual)。
- 只查接口响应和认证规则时，阅读[API 约定](/api)。
- 需要创建 Tenant、邀请 Owner 或配置域名时，阅读[实例平台管理](/platform)。
