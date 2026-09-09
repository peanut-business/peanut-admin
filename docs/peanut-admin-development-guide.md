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
server/app/adminapi/       管理 API 与 Tenant 会话 Application
server/app/api/            业务会员和公开 API Host
server/app/platform/       PlatformOperator 与实例内 Tenant 治理
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

默认部署单位是完整应用 Release。目标 Release 在任何代码根清理或数据库动作前，从自身根运行
`php server/think plugin:release-composition --current-root=<current-physical-root>`，只读比较当前
`plugins.lock`、安装账本和目标 lock。全新空库已包含锁定 private Package 源码时，授权部署 owner
在标准安装后执行 `php server/think plugin:install <private-key>` 初始化表与 catalog；既有安装在
部署后运行 `php server/think plugin:reconcile --release-locked`。后者同时处理 official 与既有
installed private，身份不变且全部 Module 为正常 maintenance 禁用态时返回
`preserved_disabled` 并保持禁用。三个入口都不能把在线上传 tar、通用生产 worker 或热更新推断为
已经具备。private Module 的 `Http/routes.php` 也不会由安装命令自动注册；应用 owner 必须在
app-owned 路由装配中显式引入它并沿用认证、Module 与权限 middleware，退役时由同一 owner 去除
接线，不复制业务 handler 或新增在线动态 loader。

签名 archive 的 `module:install-package` / `module:update-package` 入口只允许 development、debug
Standalone。Multi-tenant 派生应用当前没有从 archive 到应用仓源码和 `plugins.lock` 的受支持采用
命令；`plugin:install` 只初始化已经锁入 Release 的源码，不能替代这一步。该缺口留给独立合同，
不能靠放宽 Edition 门禁解决。

多租户部署还包含独立 `platform/` 前端，发布产物位于 `server/public/platform/`，入口为
`/platform/`。Platform Host、公共 Tenant Admin Host 与 Tenant 专属绑定 Host 必须由反向代理
保留原始 Host；Platform API 只接收 `PLATFORM_HOSTS`，绑定入口不允许切换 Tenant。

Platform 维护窗口使用 Core 的公开 Ops Console 合同，由应用的 ThinkPHP 数据库/事务适配和全局 HTTP
middleware 装配。窗口生效时，除受 `platform.ops.maintenance.manage` 权限保护的计划与关闭
接口外，所有 HTTP 写方法都拒绝并写入 Platform 审计；不能通过菜单、前端或 Host 别名绕过。

现有 `ops-module:request preview/prepare` 与 `scripts/ops-module-worker --once` 使用受限 inbox、登记
target 和 opaque `modreq_*` key，只证明既有 Package 源码/数据库生命周期；它们没有覆盖 Composer/
npm、前端构建、服务重启或完整应用 Release 切换，不能代替上面的默认部署流程，也不能称为生产
在线更新。任何 HTTP 请求都不能提供 archive、路径、URL、命令、host、数据库、凭据、确认计划或
目标地址。若以后补齐独立运维闭环，仍须保留配对备份、隔离恢复、维护、smoke、审计和 recovery
pointer 等原有门禁。

### Provider 生产资格合同

`GET /platformapi/v1/ops/providers` 是 Application 拥有的 Platform-only 只读聚合，复用
`platform.ops.read`。Payment、Notification、OAuth 与 Storage contributor 只读取各自权威
配置；GET 和 Platform 页面刷新不得运行外部 probe、发送消息或发生资金动作。

受信业务成功、回调验签或受控资格适配器通过 Application 内部
`ProviderQualificationRecorder` 追加 evidence。证据必须绑定当前配置 HMAC digest 和 TTL；配置
变化或过期后旧证据不参与资格。公开 DTO 只能暴露 opaque scope key、布尔状态、时间、稳定原因
码、最近安全失败和 evidence digest，不得暴露 Tenant ID、内部 config digest、秘密、PII、交易
号或原始错误。完整边界见
[`外部 Provider 生产资格合同`](architecture/product-closure-provider-qualification.md)。

### 应用升级就绪合同

`GET /platformapi/v1/ops/upgrade-readiness` 是应用拥有的只读投影。它不会下载 Release、执行
命令、创建备份、计划维护、修改数据库、替换文件或触发升级；请求没有参数，不能提供路径、
URL、Release key、命令、镜像或凭据。Deployment owner 只可以把已在发布流程中验证过的目标
放入固定目录：

```text
.peanut/upgrade-target/
├── target.json
├── from/
│   └── scaffold-manifest.json
├── to/
│   └── scaffold-manifest.json
└── release/
    ├── .peanut/application-manifest.json
    ├── RELEASE_METADATA.json
    ├── release-versions.json
    ├── plugins.lock
    ├── server/...
    ├── web/...
    └── <应用自己的其余源码>
```

Host 不扫描磁盘、网络或历史 Release 猜测目标。`target.json` 必须把正式应用 Release
commit/tree、P0-E 资格、两份 scaffold manifest SHA-256、from/to migration 清单和目标应用组合
解析后的 `release/plugins.lock` SHA-256 与目标 Kernel 精确 SemVer 固定在同一描述符中。整个
`release/` 是应用 owner 发布的只读、完整源码根；Host 逐文件流式重算 Git blob/tree，连同目录
排序、普通文件与 executable mode 一起匹配 `release.tree`。任何 symlink、特殊文件、读取异常或
越界解析均拒绝；Module lock、manifest、后端 Module、前端 contribution 和包身份只能相互解析到
同一棵已验证的应用树。

应用 Release 身份与 scaffold 来源是两条独立轴。目标 `.peanut/application-manifest.json` 的
`application.slug/package_identity` 必须与当前应用一致；展示名称可以变化。目标
`RELEASE_METADATA.json.application_identity` 必须等于 package identity，metadata 的
`version/expected_tag`、`release-versions.json.product_release` 和描述符 `release.key` 必须表示
同一个应用 owner 创建的不可变发布。生成时得到的 baseline metadata 只证明生成输入，不能直接
当作正式应用 Release。目标 manifest 的 template 四元组与外部 `to/scaffold-manifest.json`
绑定，当前 manifest 的 template 四元组与 `from/scaffold-manifest.json` 绑定；两者不要求等于
应用 Release commit/tree。

`target.json` 只接受以下精确字段集合；未知字段、缺项、未排序 migration、摘要不匹配、资格
不足 7 组、存在清理残留或租约未释放均 fail closed：

| 对象 | 精确字段 |
|---|---|
| 顶层 | `schema_version=1`、`protocol=peanut.application-upgrade-target.v1`、`release`、`scaffold`、`migrations`、`modules` |
| `release` | `key`、`commit`、`tree`、`qualification`；资格 candidate 必须等于 Release commit/tree |
| `scaffold` | `from_version`、`from_manifest_sha256`、`to_version`、`to_manifest_sha256` |
| `migrations.from/to` | `inventory_sha256`、按 `migration_id` 严格升序的 `{migration_id, sha256}` 列表 |
| `modules` | `lock_sha256`、`kernel_version`；摘要必须匹配固定 `release/plugins.lock`，Kernel 必须是精确 SemVer |

检查顺序固定为：目标 Release 描述符/资格/完整 tree 与应用身份；当前应用身份和 from scaffold
来源；当前 `product_release` 严格小于目标应用版本；scaffold 不降级且不跨大版本；Runtime 健康、
仓库干净和当前 migration；from/to migration 不删除、不改写、不倒序、不冲突；目标 `release/`
内 Module lock/源码、目标 Kernel/依赖和已安装 Module；目标应用已经采用的 scaffold provenance；
匹配当前 Runtime 的已验证配对备份、引用同一
`backup_reference_key` 的恢复 evidence，以及 active `planned-upgrade` 维护窗口。scaffold 跨大版本
固定返回 `UPGRADE_FRESH_REBUILD_REQUIRED`。from/to scaffold 版本相同时，只有两份 manifest
SHA-256 完全相同才允许继续，因此可以部署不改变 scaffold 的纯
应用升级。缺当前 application manifest 或未暂存目标时为 `configuration_required`；身份文件存在
但格式或绑定错误时为 blocked，不从 canonical 仓库或历史名称猜测 fallback。

该 readiness 中 active Module 缺失/降级检查继续保留；直接部署入口还必须在切换前运行目标
Release 自带的 composition guard，覆盖 Plugin artifact、private Package 与异常生命周期状态。
readiness 和 guard 是相邻门禁，不能用其中一个的通过结果代替另一个。

`preflight.state` 只覆盖前七类静态检查，使 PC42 可以在静态预检通过后创建新备份并进入维护
窗口；顶层 `state` 只有动态保护条件也满足时才为 `ready`。恢复 evidence 与最新备份不配对时
固定返回 `UPGRADE_RESTORE_BACKUP_MISMATCH`，且不生成 recovery pointer。Scaffold 投影只说明目标
完整应用 Release 已通过 provenance 绑定；其中 automatic/preserved/conflicts 等计数为 0 表示
Runtime 没有执行 scaffold 合并动作，不是目标应用的真实文件数量。开发期的
`ScaffoldUpgradeRunner` 采用与三方比较必须在应用 owner 形成正式应用 Release 前完成，生产
readiness 不重复该合并。

PC42 只能消费完整 `target.json`、descriptor SHA-256、readiness check 列表和 opaque recovery
pointer。它不得重新解释 Web 输入、从移动分支推导目标，或在 blocker 存在时跳到部署/迁移。
顺序固定为：静态预检 → 已验证配对备份 → `planned-upgrade` 维护 → 完整 readiness → 部署/迁移/
smoke → 关闭维护或停在已记录恢复指针。跨实例升级仍属于独立运营平台；生产覆盖恢复仍需独立
授权。

PC42 的提交接口只接受空 JSON 对象和幂等键。服务器把当前 Runtime 与 PC41 固定目标身份写入
`ops.upgrade.execute`，登记的 `peanut-admin-production-upgrade-control-worker` 再按静态预检 →
新备份 → 同一备份的隔离恢复验证 → planned-upgrade 维护 → `deploy-release` update → Runtime
smoke → recovery pointer 的顺序执行。task 在 claim 和首次/重入 deploy 响应中固定目标
commit/tree；worker 把两者成对交给 `deploy-release`，后者先核对远端 annotated tag 的实际身份，
再只从固定 commit 读取并归档。各步状态和摘要可在 Platform“运行与维护”页面观察，失败停在
当前步骤；页面不能传入路径、命令或部署目标。

实际生产执行前必须读取并核验 `resources/project-resources.json` 中的上述 worker、
`peanut-admin-production-deployment`、`peanut-admin-production-backups` 和
`peanut-admin-production-restore-verification-deployment`，取得具体生产动作授权及资源 lease 后，
仅从登记的固定 checkout 运行 `scripts/ops-upgrade-worker --once`。完整边界与恢复语义见
[`应用升级执行合同`](architecture/product-closure-upgrade-execution.md)。

上述 worker 是 canonical Peanut 的固定生产实现，不构成独立应用的通用远程部署平台。独立应用
必须由自己的 owner 提供资源登记、固定 checkout/执行器、不可变 Release 与生产授权；这些输入
缺失时升级保持阻断。既有应用中的 app-owned Host 不会被 scaffold 自动覆盖，owner 需要审阅并
采用新的版本合同修正。

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
