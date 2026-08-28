# 可消费 Module Package 生命周期合同

Document ID: `pa-docs-architecture-consumer-module-lifecycle-contract`

Status: `current`

Owner: `product-architecture`

Audience: `maintainer, architect, operator, ai`

Upstream: [`Module development guide`](../module-development-guide.md)、
[`Maintenance gate`](product-closure-maintenance-gate.md)、
[`Paired backup provider`](product-closure-backup-provider-contract.md)、当前
`PluginPackageInstaller`、`PluginLifecycleService`、`PluginRuntimeGovernanceService` 和
`PluginLifecycleCommands`。

## 1. 决定与边界

可消费交付只有一个 Package 生命周期，不给 install 增加“猜测更新”的隐式分支。调用者必须显式
选择 `install`、`update`、`disable`、`reactivate`、`retire` 或 `purge`；只读入口为 `check`、
`dry-run`、`preview` 和 `recovery-plan`。旧内部 `plugin:*` 命令可继续作为实现原语，但不是新的
消费者合同，也不得与公共入口形成两套状态机。

Package、ModuleInstallation、TenantModule 与成员 RBAC 是四层独立事实：

| 层 | 权威事实 | 生命周期操作允许改变 | 禁止的隐式副作用 |
|---|---|---|---|
| Package | `plugins.lock`、`pa_plugin_installation`、artifact identity | 当前受信 Package 身份与部署状态 | 自动开通 Tenant、自动授予角色 |
| ModuleInstallation | `pa_module_installation`、`pa_plugin_module`、migration/catalog | Package 成员的安装和 catalog 状态 | 拆装 Bundle 单个成员 |
| TenantModule | `pa_tenant_module` | 仅 Tenant 开通入口 | Package install/update 改写开通状态 |
| 成员 RBAC | Tenant role/permission binding | 仅授权入口 | TenantModule 或 Package 操作自动授权 |

所有变更按完整 Package key 加同一 advisory lock；输入 module key 时必须先解析到完整 Package，输出
总是返回 `package_key`、完整 `affected_modules` 和不可变 artifact identity。受保护 Module、活动
TenantModule 或活动业务依赖中的任一项都阻止整个 Package 的 disable、retire 和 purge。

## 2. 固定状态机

| 操作 | 允许起点 | 成功终点 | 必须 fail-closed 的情况 |
|---|---|---|---|
| `install` | Package 未登记，或已完成 purge | artifact active | key 已安装、目标路径有非同身份内容、依赖/签名/checksum/manifest/migration preflight 失败 |
| `update --dry-run` | `active` 或可修复的 `failed` | 零写入，返回固定 plan | 目标不是同 key 更高版本、缺依赖、降级、未知/不可逆 migration 无恢复前置 |
| `update` | `active`，或原失败身份的更高 repair 版本 | 新 artifact/lock/Package/Module/migration/catalog 为同一身份 | 任一 preflight、stage、promotion、migration、catalog 或 recovery pointer 失败 |
| `disable` | Package active，全部 TenantModule disabled | Package 身份保留，Module catalog 为 maintenance | protected、活动 TenantModule、活动依赖或 lifecycle busy |
| `reactivate` | 仅 disable 产生的 maintenance，且仍是同一 artifact | 原 Package active | artifact、lock、manifest 或 migration identity 改变；retire 后不得伪装成 disable 恢复 |
| `retire` | active 或 disabled | 代码进入单一 quarantine，业务表/migration ledger/ownership 保留 | blockers 非空、确认 plan 变化、quarantine identity 不唯一 |
| `purge` | active、disabled 或单一可验证 quarantine | Package、catalog、owned tables、migration ledger 按确认计划删除 | 无双确认、无已验证备份、外部外键/FK cycle、protected、活动 TenantModule/依赖、计划漂移 |

同版本同 identity 的 `update` 返回 `unchanged`；同版本不同 identity 返回
`PACKAGE_VERSION_IDENTITY_CONFLICT`。任何较低版本都返回 `PLUGIN_DOWNGRADE_REJECTED`，不能通过
`--force`、自动降级或回放未知 DDL 绕过。失败安装只能用原 immutable identity 继续，或用更高
repair 版本前滚；repair migration 必须显式引用被修复的旧 migration key。

`retire` 与 `purge` 使用 schema-versioned canonical plan 和 SHA-256 digest 双确认。Purge 写入
`MODULE_PURGE_IN_PROGRESS` 后中断时，只允许用原 plan/digest 和同一 quarantine identity 重试；
不得重新猜测已删除范围。无法证明边界时保持 maintenance、保留恢复坐标并停止。

## 3. Package update 原子性与恢复

update 的唯一顺序固定为：

1. 在锁外只读校验 archive SHA-256、可选 Ed25519 trusted key、package/module manifest、版本、
   依赖、权限/菜单/前端入口、owned tables、migration checksum 与 app-owned 冲突；
2. 取得 Package advisory lock，重新核对当前 Package/lock/DB identity 和 plan digest；
3. 把新 artifact 解包到 stage，把会被替换的 managed scope 与 `plugins.lock` 保存到本次 recovery
   root；app-owned 文件不属于 promotion scope；
4. 原子提升 managed scopes 和 canonical lock，再由现有 lifecycle owner执行 migration 与 catalog；
5. 只有 artifact、lock、`pa_plugin_installation`、`pa_module_installation`、migration ledger 和
   catalog 全部指向目标 identity，才删除临时 stage 并报告 `updated`；
6. 任何失败先隔离目标 artifact，恢复旧 managed scopes/lock；若已应用不可逆 migration，则保持
   failed/maintenance，返回已验证数据库备份和 artifact recovery pointer，不冒充自动回滚。

dry-run 必须使用相同 preflight 与计划编码器，但不得创建 recovery root、替换文件/lock、取得会
改变数据库的锁、执行 migration、更新 catalog/installation、写审计或改变 Tenant/RBAC。验收同时
比较文件、lock、数据库表、migration ledger、TenantModule 和 RBAC 摘要，证明零写入。

## 4. 交付环境编排

Package executor 不拥有主机、数据库、路径、命令或凭据选择。CR12 的 deployment-owned CLI/worker
只能从 `resources/project-resources.json` 解析一个显式环境和登记 target，并按下列固定顺序调用
现有产品闭环能力：

1. 只读 preflight：目标、Package、版本、签名、依赖、计划和当前 Runtime identity；
2. 对 `update`、`retire`、`purge` 创建新的配对 DB/files 备份并完成隔离恢复验证；
3. 建立并核对维护窗口，停止非控制写入；
4. 执行一次固定 Package 操作；Purge 还须回传原始 confirm plan/digest；
5. 对 Package/Module/TenantModule/RBAC 四层、迁移和直接业务入口做 smoke；
6. 持久化安全 recovery pointer，再退出维护。失败时维护保持 active，直到 operator 按指针恢复或
   明确确认安全退出。

`disable`/`reactivate` 不删除数据或应用 migration，但在交付环境仍通过同一维护、审计和 smoke
编排；无需制造新的配对备份。生产数据覆盖、从备份恢复到活动目标仍需独立授权。

## 5. 权限、审计和 HTTP 禁区

- 本地作者入口继续受 `development + debug + Standalone + InstanceToolAccessGuard` 限制。
- 交付入口属于 deployment owner，不复用 dev-tools HTTP 上传或浏览器操作；它只接受已登记 target、
  受信本地 Package reference、精确操作和显式确认，不监听端口。
- 生产 HTTP 永远不接受 archive/upload、文件路径、URL、命令、host、数据库、资源 ID、凭据、
  recovery root、任意 target 或 Purge confirm plan。未来若提供 Platform 投影，只能提交服务器已
  固定的 opaque task key，不能扩展本合同输入面。
- 每次 mutating 操作记录 actor、environment、target resource ID、package key、operation、source/
  target identity、plan digest、backup/maintenance/recovery opaque key、结果和稳定 error code；
  不记录密钥、路径、命令输出、archive 内容或数据库连接信息。

稳定错误至少包括：`MODULE_LIFECYCLE_BUSY`、`PLUGIN_NOT_INSTALLED`、
`PLUGIN_ALREADY_INSTALLED`、`PLUGIN_STATE_INVALID`、`PLUGIN_DOWNGRADE_REJECTED`、
`PACKAGE_VERSION_IDENTITY_CONFLICT`、`PLUGIN_MODULE_CONFLICT`、
`PLUGIN_TENANT_MODULE_ACTIVE`、`MODULE_DEPENDENT_INSTALLED`、
`MODULE_LIFECYCLE_PROTECTED`、`MODULE_UNINSTALL_PLAN_CHANGED`、
`MODULE_OWNED_TABLE_EXTERNAL_REFERENCE`、`MODULE_PURGE_IN_PROGRESS`、
`PACKAGE_UPDATE_RECOVERY_REQUIRED`。消费者输出统一包含 `code`、`reason` 和 `remediation`，未知异常
映射为稳定失败码并把原文留在受限日志。

## 6. CR11/CR12 直接下游

CR11 的唯一实现 owner 为后续 `feat/consumer-ready-cr11`，首个可合入切片是显式
`module:update-package`、共享 package update service 和聚焦 update 合同测试。预计写集限定为：

- `server/app/command/ModuleUpdatePackage.php`、`server/config/console.php`；
- `server/app/platform/service/plugin/PluginPackageInstaller.php` 及必要的同目录 package DTO/codec；
- `server/app/platform/service/plugin/PluginLifecycleService.php`，仅补齐 staged target 与恢复结果；
- `server/tests/Productization/ModuleBundleLifecycleTest.php` 或一个单一聚焦 update test；
- 直接命令索引和本计划状态。

CR11 不修改 TenantModule/RBAC、生产 HTTP、Marketplace、Provider 或 app-owned 文件。CR12 在 CR11
固定 package executor 后独立拥有 deployment CLI/worker、资源选择、维护/备份/审计/smoke/recovery
编排；不得把这些职责塞回 `PluginPackageInstaller`。

## 7. 最低验收矩阵

CR11 必须固定：active v1→v2 dry-run 零写、成功同一身份、同版本不同 artifact、降级、依赖缺失、
签名/checksum、managed/app-owned 冲突、migration 失败和更高 repair；CR12 再固定维护/备份/恢复
验证失败均在破坏性步骤前停止。CR21 纵向 fixture 负责从两个独立生成应用消费公共入口；CR30/31
才运行实际生成物资格和唯一完整组合 Gate。
