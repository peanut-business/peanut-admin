# Peanut Admin 3.0 开发指南

> 本文件是 Peanut Admin 源仓的人类可读版本。create-app 会在派生应用的同一路径生成一份
> 应用专属简版，不会复制完整 `docs-site/`。详细、可导航的公开版本见
> [开发与目录](https://peanut-admin-doc.007345.xyz/guide/development)和
> [Module 开发教程](https://peanut-admin-doc.007345.xyz/guide/module-development)。

## 5 分钟速读

- Peanut Core 提供 Account、Tenant、RBAC 和扩展合同；本仓 Host 拥有应用路由、页面、
  安装和产品能力。
- 默认一套部署对应一个应用实例。一个实例可包含多个 Tenant、客户端和 Module；不同
  实例不能共享私有表。
- 管理登录唯一使用 `Account/Credential`，租户成员唯一使用 `TenantMember`；客户侧业务
  会员使用独立 `pa_member`。登录身份、组织成员和业务档案不得混写。
- 3.0 只支持空库安装。`server/database/init.sql` 是 canonical Schema，
  `server/database/migrations/` 只接收基线后的追加式变更。
- 新业务放入独立 Module，通过公开命令、查询 DTO 或已验证的事件合同协作，不直接访问
  其他 Module 私有表。
- DCS 是派生应用，不是 Peanut Admin 内建模块；商品、库存、采购等领域文档由 DCS 仓库拥有。

## 目录和 owner

```text
server/app/adminapi/       管理 API Host
server/app/api/            业务会员和公开 API Host
server/app/platform/       PlatformOperator 与实例内 Tenant 治理
server/app/tenant/         Tenant 管理会话 Host
server/app/common/         应用公共模型、服务和横切适配
server/app/Modules/        应用 Module 后端
server/database/           canonical Schema、安装器和追加 migration
server/route/app.php       HTTP 路由入口
web/                       Vue 管理端
platform/                  Vue 实例 Platform 控制面
pc/                        Nuxt PC 客户端
uniapp/                    H5/小程序客户端
plugins/ + plugins.lock    Plugin 制品与当前部署锁
docs-site/                 源仓公开教程、参考与故障处理；派生应用不复制
resources/                 项目资源事实源
scripts/                   创建应用、资源门禁和维护命令
```

Core 只拥有通用身份、Tenant、权限和公开 Host 合同；应用拥有业务表、HTTP 装配、菜单、
页面与产品配置；Module 只拥有自己的表、用例、权限和公开合同；Plugin 是一个或多个
Module 的不可变交付制品，不等于 Tenant 开通或成员授权。

多租户部署还包含独立 `platform/` 前端，发布产物位于 `server/public/platform/`，入口为
`/platform/`。Platform Host、公共 Tenant Admin Host 与 Tenant 专属绑定 Host 必须由反向代理
保留原始 Host；Platform API 只接收 `PLATFORM_HOSTS`，绑定入口不允许切换 Tenant。

Platform 维护窗口使用 Core 的公开 Ops Console 合同，由应用的 PDO Adapter 和全局 HTTP
middleware 装配。窗口生效时，除受 `platform.ops.maintenance.manage` 权限保护的计划与关闭
接口外，所有 HTTP 写方法都拒绝并写入 Platform 审计；不能通过菜单、前端或 Host 别名绕过。

### 应用升级就绪合同

`GET /api/platform/v1/ops/upgrade-readiness` 是应用拥有的只读投影。它不会下载 Release、执行
命令、创建备份、计划维护、修改数据库、替换文件或触发升级；请求没有参数，不能提供路径、
URL、Release key、命令、镜像或凭据。Deployment owner 只可以把已在发布流程中验证过的目标
放入固定目录：

```text
.peanut/upgrade-target/
├── target.json
├── from/
│   ├── scaffold-manifest.json
│   └── files/...
├── to/
│   ├── scaffold-manifest.json
│   └── files/...
└── release/
    ├── plugins.lock
    ├── plugins/...
    ├── server/app/Modules/...
    └── web/src/modules/...
```

Host 不扫描磁盘、网络或历史 Release 猜测目标。`target.json` 必须把正式 Release commit/tree、
P0-E 资格、两份 scaffold manifest SHA-256、from/to migration 清单和目标应用组合解析后的
`release/plugins.lock` SHA-256 与目标 Kernel 精确 SemVer 固定在同一描述符中。整个
`release/` 是只读目标源码根，任何 symlink、特殊文件或越界解析均拒绝；Module lock、manifest、
后端 Module、前端 contribution 和包身份只能相互解析到这棵目标树。目标 scaffold manifest 的
`release.source_commit/source_tree` 必须分别等于描述符中的 `release.commit/tree`，不能用
一个已资格候选的身份包装另一份目标模板。

`target.json` 只接受以下精确字段集合；未知字段、缺项、未排序 migration、摘要不匹配、资格
不足 7 组、存在清理残留或租约未释放均 fail closed：

| 对象 | 精确字段 |
|---|---|
| 顶层 | `schema_version=1`、`protocol=peanut.application-upgrade-target.v1`、`release`、`scaffold`、`migrations`、`modules` |
| `release` | `key`、`commit`、`tree`、`qualification`；资格 candidate 必须等于 Release commit/tree |
| `scaffold` | `from_version`、`from_manifest_sha256`、`to_version`、`to_manifest_sha256` |
| `migrations.from/to` | `inventory_sha256`、按 `migration_id` 严格升序的 `{migration_id, sha256}` 列表 |
| `modules` | `lock_sha256`、`kernel_version`；摘要必须匹配固定 `release/plugins.lock`，Kernel 必须是精确 SemVer |

检查顺序固定为：目标 Release 描述符/资格/摘要；`.peanut/application-manifest.json` 来源身份；
同大版本且目标版本更高；Runtime 健康、仓库干净和当前 migration；from/to migration 不删除、
不改写、不倒序、不冲突；目标 `release/` 内 Module lock/源码、目标 Kernel/依赖和已安装 Module；与 CLI 相同的
scaffold ownership/conflict；匹配当前 Runtime 的已验证配对备份、引用同一
`backup_reference_key` 的恢复 evidence，以及 active `planned-upgrade` 维护窗口。跨大版本固定
返回 `UPGRADE_FRESH_REBUILD_REQUIRED`。

`preflight.state` 只覆盖前七类静态检查，使 PC42 可以在静态预检通过后创建新备份并进入维护
窗口；顶层 `state` 只有动态保护条件也满足时才为 `ready`。恢复 evidence 与最新备份不配对时
固定返回 `UPGRADE_RESTORE_BACKUP_MISMATCH`，且不生成 recovery pointer。Scaffold 投影只返回
动作数量、稳定原因、managed/app-owned 摘要和 app-owned 数量，不返回绝对路径或文件内容；
`ScaffoldUpgradeRunner::preview()` 与 CLI preflight 共享分类规则，但不写 plan 或 ledger。

PC42 只能消费完整 `target.json`、descriptor SHA-256、readiness check 列表和 opaque recovery
pointer。它不得重新解释 Web 输入、从移动分支推导目标，或在 blocker 存在时跳到部署/迁移。
顺序固定为：静态预检 → 已验证配对备份 → `planned-upgrade` 维护 → 完整 readiness → 部署/迁移/
smoke → 关闭维护或停在已记录恢复指针。跨实例升级仍属于独立运营平台；生产覆盖恢复仍需独立
授权。

PC42 的提交接口只接受空 JSON 对象和幂等键。服务器把当前 Runtime 与 PC41 固定目标身份写入
`ops.upgrade.execute`，登记的 `peanut-admin-production-upgrade-control-worker` 再按静态预检 →
新备份 → 同一备份的隔离恢复验证 → planned-upgrade 维护 → `deploy-release` update → Runtime
smoke → recovery pointer 的顺序执行。各步状态和摘要可在 Platform“运行与维护”页面观察，
失败停在当前步骤；页面不能传入路径、命令或部署目标。

实际生产执行前必须读取并核验 `resources/project-resources.json` 中的上述 worker、
`peanut-admin-production-deployment`、`peanut-admin-production-backups` 和
`peanut-admin-production-restore-verification-deployment`，取得具体生产动作授权及资源 lease 后，
仅从登记的固定 checkout 运行 `scripts/ops-upgrade-worker --once`。完整边界与恢复语义见
[`应用升级执行合同`](architecture/product-closure-upgrade-execution.md)。

## 开发最小路径

1. 在 `server/app/Modules/<Vendor>/<Module>/` 定义 `Domain`、`Application`、`Contracts`、
   `Infrastructure`、`Database/Migrations`、`Resources` 和 `Tests`。
2. Module 表必须有明确 Tenant owner；SQL、唯一键、关联、缓存、文件和任务都保留 Tenant
   维度。请求参数不得覆盖可信 TenantContext。
3. 对外只公开命令接口和只读 DTO。调用方依赖 `Contracts`，由 Host/Provider 绑定实现。
4. 管理端 contribution 放在 `web/src/modules/<module>/`，菜单和权限由 Module Resources
   声明；Plugin 安装、TenantModule 开通、成员 RBAC 是三道独立 Gate。
5. 最低测试覆盖 Tenant A 正常读写、Tenant B 读取/写入同一 ID 被拒绝、Tenant 暂停、
   Module 未开通和伪造资源 ID。

完整纵向示例、目录树、跨 Module 商品入库流程和常见错误见公开文档的
[Module 开发教程](https://peanut-admin-doc.007345.xyz/guide/module-development)。API 响应、
认证、权限和 Host 入口见 [API 与扩展](https://peanut-admin-doc.007345.xyz/api)。

## 资源与运行

根 `.env.example` 只描述 Docker 端口、镜像和构建代理；后台唯一配置样例是
`server/.env.example`，复制为权限 `0600` 的 `server/.env` 后维护 `APP_*`、`DB_*`、JWT、
部署模式和 Tenant/Platform 配置。PHP、CLI、安装器和测试不接受 `PHP_*` 别名绕过。

Peanut Admin 源仓维护者必须先读 `resources/project-resources.json`，显式选择登记的资源
ID、环境和地址，再连接数据库、启动服务或执行迁移。派生应用生成后必须把该文件替换为
自身版本化资源登记；不得沿用 Peanut Admin 的开发主机、凭据引用或端口。

空库安装的最低输入：

```bash
# 先在 server/.env 中填写 DEPLOYMENT_MODE、ADMIN_INITIAL_* 和两项 HMAC key。
php server/database/install.php
php server/database/install.php --migrate --current
```

`multi-tenant` 模式另需独立的 `PLATFORM_INITIAL_EMAIL` 与
`PLATFORM_INITIAL_PASSWORD`。3.0 不接受旧大版本数据库、`--adopt-existing`、legacy map 或
scaffold 原地升级。

## 进一步阅读

- 架构、身份、Tenant 与部署：[开发与目录](https://peanut-admin-doc.007345.xyz/guide/development)
- Module 纵向教程：[Module 开发教程](https://peanut-admin-doc.007345.xyz/guide/module-development)
- API 与扩展参考：[API 与扩展](https://peanut-admin-doc.007345.xyz/api)
- 核心能力边界：[核心概念](https://peanut-admin-doc.007345.xyz/guide/concepts)
- 部署与故障停止线：[部署与升级](https://peanut-admin-doc.007345.xyz/guide/deployment-upgrade)
- 管理员操作：`docs/peanut-admin-user-manual.md`
