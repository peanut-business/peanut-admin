# 用 Module 开发独立业务

> 本文是 Peanut Admin Module/Plugin 的详细开发参考。先读“5 分钟速读”，再按纵向切片
> 完成第一个模块。本文只描述当前仓库已验证的扩展面；DCS 等派生应用的领域设计由其
> 自己的仓库拥有。

## 5 分钟速读

Peanut Admin 把四件事分开：

- **Module** 是业务代码和数据的唯一 owner。
- **Plugin** 是一个或多个 Module 的不可变安装包。
- **Host** 校验 Plugin、执行 Module migration、汇总菜单/权限/设置和前端入口。
- **TenantModule** 表示某个 Tenant 是否开通 Module；成员权限是另一层判断。

一个功能真正可用，需要同时满足：

```text
Plugin active
  -> TenantModule enabled
      -> TenantMember 拥有功能权限和数据权限
          -> 可信 TenantContext 进入 Module Application Service
```

当前正式创建的应用以空 `plugins.lock` 开始，不携带源码仓的 fixture。安装 Plugin 不会把
Controller、Model 或 Vue 文件复制进公共目录，卸载也不会默认删除业务表。

## 当前支持边界

| 能力 | 状态 | 当前合同 |
| --- | --- | --- |
| Plugin manifest 和不可变 lock | **当前已支持** | identity、来源、摘要和前后端包不一致时 fail closed |
| Module migration | **当前已支持** | Module 自有、追加式、带校验和和 durable ledger |
| 权限、菜单、设置登记 | **当前已支持** | 安装后登记，失败不进入 active |
| TenantModule 开通/停用 | **当前已支持** | Plugin active 不替代租户开通 |
| 前端 contribution | **当前已支持** | 从 lock 汇总路由入口，不复制到共享 views |
| 同步 Module 命令停用 Guard | **当前已支持（fixture）** | 授予成员权限后停用仍返回 `MODULE_TENANT_DISABLED` |
| Module HTTP/任务/回调/专属文件统一 Guard | **推荐新增** | 现有共享 Host 已做 Tenant 隔离，但不是可停用 Module 的完整运行时证明 |
| 跨模块命令/查询扩展点 | **当前已支持** | PHP `Contracts/` 公开接口可由应用显式装配；当前没有两个 Module 的可运行示例 |
| 两个 Module 的命令/查询示例 | **推荐新增** | 当前 fixture 只证明单 Module 合同，不能冒充跨 Module 运行证据 |
| 通用 Outbox/事件总线 | **推荐新增** | 当前 fixture 没有事件，业务模块不能假定已有可靠事件传输 |
| DCS Product 等领域模块 | **暂不建议放入 Peanut** | 只允许在 DCS 独立应用中实现 |

## 真实目录与所有权

源码仓的资格 fixture 使用以下真实布局：

```text
plugins.lock
plugins/fixture.delivery-record/
  plugin.json
server/app/Modules/Fixture/DeliveryRecord/
  module.json
  composer.json
  ModuleProvider.php
  Application/
    DeliveryRecordAccess.php
    DeliveryRecordService.php
  Contracts/
    DeliveryRecordCommands.php
  Infrastructure/
    Authorization/PdoDeliveryRecordAccess.php
    Persistence/PdoDeliveryRecordRepository.php
  Database/Migrations/
    20260814050101_create_fixture_delivery_records.sql
    OwnedMigration.php
  Resources/
    menus.json
    permissions.json
    setting-definitions.json
web/src/modules/fixture-delivery-record/
  contribution.ts
  package.json
  views/index.vue
```

目录职责：

| 目录或文件 | owner 和用途 |
| --- | --- |
| `module.json` | Module 身份、依赖、数据表、公开合同、前后端入口和停用行为 |
| `ModuleProvider.php` | 把公开合同装配到应用服务和基础设施 adapter |
| `Application/` | 用例、事务编排、权限调用和失败语义 |
| `Contracts/` | 其他模块唯一允许依赖的接口、DTO 和事件声明 |
| `Infrastructure/` | PDO/ORM、外部服务和授权 adapter，不对其他模块公开 |
| `Database/Migrations/` | 只创建或修改本 Module 声明拥有的表 |
| `Resources/` | 权限、菜单和设置定义；不是业务真值表 |
| `contribution.ts` | 前端路由和页面入口，声明 Module key 与所需权限 |
| `plugins.lock` | 当前部署唯一允许加载的 Plugin 版本和内容摘要 |

派生应用可以在同一结构中增加自己的 vendor/module 命名空间，例如
`server/app/Modules/Acme/Inventory/`。不要把派生应用 Module 放进 `server/app/common/`，
也不要复制 Peanut Core 类到应用目录。

## 第一个纵向切片

## 本地 Plugin 工具链

工具不连接数据库；它们只读取 Module、前端 contribution 和 JSON schema。先完成 Module
目录与前端 contribution，再生成 manifest 和 lock：

```bash
cd server
php think plugin:make acme.inventory 1.0.0 \
  --module=acme.inventory=server/app/Modules/Acme/Inventory
php think plugin:lock --write
php think plugin:lock --check
```

`plugin:make` 从每个 Module 的 `composer.json`，及同名 slug 前端目录
`web/src/modules/<module-key-with-dashes>/` 自动生成 Composer、npm、frontend 和 canonical
contents 摘要，并使用 `resources/schemas/plugin.schema.json` 与已安装的 `opis/json-schema`
校验后写入 `plugins/<plugin-key>/plugin.json`。`plugin:lock --write` 对全部 manifest 重新校验
并确定性写入 `plugins.lock`；`--check` 不写文件，适合提交前检查。

### 制品信任与兼容结果

`plugin.json` 与 `plugins.lock` 同时固定下列可消费矩阵；Host 只接受两者完全一致、且内容摘要
可重算的记录。安装、reconcile 和 upgrade 在执行 migration 前都读取同一结果，资格无效时以
`PLUGIN_TRUST_QUALIFICATION_INVALID` 停止，而不是尝试备用 Plugin Runtime。

| 维度 | 当前 bundled 资格 | 安装/升级解释 |
| --- | --- | --- |
| 版本、Kernel、依赖 | 每个 Module 的版本、`kernel_constraint` 和精确依赖约束 | 仍由单一 Module Registry 编译和依赖检查；不兼容即停止 |
| 权限 | `backend.permissions` 的相对路径和 SHA-256 | 路径或定义内容漂移会改变 canonical contents/lock 身份 |
| migration | 每条 SQL 的 key/SHA-256；统一标记 `verified-backup-required` | 没有自动回滚；有 pending migration 的 upgrade 必须先有已验证备份 |
| 来源与内容 | `repository-contents` 与 canonical contents SHA-256 | 仅可从锁定 bundled source 安装，摘要不符 fail closed |
| archive、签名、SBOM | bundled source 当前都明确为 `not-issued`，不是伪造的 SHA、签名或 SBOM | 这不阻塞已锁定的源码仓 bundled 安装；开发态手工 archive 必须提供 `--sha256`，或通过既有受信签名校验 |
| 许可证 | Module 声明的共享 license | 同一 Plugin 内 license 不一致时无法生成 manifest |
| 审核、漏洞响应与 Marketplace | `review=not-reviewed`、`vulnerability_response=not-configured`、`marketplace=blocked` | Marketplace 始终返回 review 与漏洞响应两个稳定 blocker；它不是当前可用的安装来源 |

因此，`bundled-locked` 只表示“当前仓库锁定身份可由唯一 Host 处理”，不表示已经通过第三方
安全审计，也不表示存在可下载的 Marketplace 制品。要开放 Marketplace，必须先在独立决策中
增加可验证 archive SHA-256、受信签名、SBOM、许可证审查和漏洞响应 authority；不得仅把状态
字段改成已通过，也不得复制或增加第二个 Plugin Runtime。

### 开始前

确认 Module key、数据 owner、依赖 Module、目标客户端和停用行为已经写清楚。若无法回答“谁拥有表、谁能调用、停用后哪些入口必须拒绝”，先停在设计阶段，不要创建 migration。

### 1. 定义数据 owner

Module migration 必须在每张业务表上固定 `tenant_id`，索引和外键也要包含 Tenant 边界。
fixture 的关键约束是：

```sql
UNIQUE KEY uk_fixture_delivery_tenant_ref (tenant_id, reference)
FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id)
```

Repository 不能接收浏览器提交的任意 Tenant ID，而是从可信 `TenantContext` 取值：

```php
$statement->execute([
    'tenant_id' => $context->tenantId,
    'reference' => $reference,
]);
```

### 2. 定义公开合同

其他模块只依赖 `Contracts/DeliveryRecordCommands.php`：

```php
interface DeliveryRecordCommands
{
    public function record(TenantContext $context, string $reference): array;
    public function list(TenantContext $context): array;
}
```

合同使用稳定业务输入和 DTO，不泄漏 PDO、ThinkPHP Model 或私有表名。`ModuleProvider` 负责
装配具体实现；调用方不能自行 new 另一个模块的 Repository。

### 3. 登记权限和菜单

`permissions.json` 声明 `fixture.delivery-record.read/create`；`menus.json` 把页面绑定到
`admin-web`、路由、组件和读取权限。后端命令仍需再次调用统一 ModuleGuard 和授权 Repository，
前端隐藏按钮不是安全边界。root、system actor、异步 worker、外部回调和模块专属文件入口也
不能绕过 Module 状态；当前 fixture 只证明同步成员命令，正式 Module 必须为实际使用的其他
入口补负向测试。

管理端 HTTP 路由目前仍由应用在 `server/route/app.php` 显式登记。新增 API 时必须同时
完成路由、Controller、Module Application Service、权限定义和聚焦测试，不能只增加菜单。

### 4. 登记前端 contribution

`web/src/modules/<slug>/contribution.ts` 声明懒加载页面、`tenantModuleKey` 和
`requiredPermissions`。Host 从 lock 解析 contribution；未安装、租户未开通或权限不足时，
路由和菜单都不能成为绕过入口。

### 5. 安装和开通

源码仓 fixture 的合同命令如下。它们只用于仓库资格示例，生产应用必须换成真实 Plugin artifact 和 lock 身份。

| 命令 | 必填参数 | 作用 | 预期结果 | 风险/停止线 |
| --- | --- | --- | --- | --- |
| `plugin:install` | Plugin key | 预检、执行 Module migration、登记资源并激活 | 首次安装为 active；相同不可变身份重跑为 unchanged | manifest、摘要或 migration 不一致时停止 |
| `plugin:upgrade --dry-run` | Plugin key | 只生成升级计划 | 不修改数据库和 Plugin 状态 | 计划出现越权表或降级动作时停止 |
| `plugin:rollback` | Plugin key | 生成回滚计划 | 不默认删除业务数据 | 不把计划当作已回滚证明 |
| `plugin:uninstall` | Plugin key | 卸载 Plugin Runtime | 所有 TenantModule 停用后执行，默认保留数据 | 任一 Tenant 仍启用时拒绝 |

```bash
cd server
php think plugin:install fixture.delivery-record
php think plugin:upgrade fixture.delivery-record --dry-run
php think plugin:rollback fixture.delivery-record
php think plugin:uninstall fixture.delivery-record
```

安装顺序为预检、`installing`、Module migration、登记资源、`active`。同一 Package 使用数据库
advisory lock 串行执行；重复安装同一不可变身份返回 unchanged，中断后重试会跳过 checksum 相同
且已 applied 的 migration，并继续幂等 catalog 步骤。

MySQL DDL 可能隐式提交。若进程中断后某条 migration 只剩 `applying` 或 `failed`，运行时无法证明
SQL 是“完全没执行”还是“只执行了一部分”，因此绝不自动重放。修复方式是发布更高不可变 Package
版本、保留原文件和 checksum，并追加幂等修复文件。修复文件第一条非空内容必须声明完整前驱 key：

```sql
-- peanut-admin-repairs: fixture.delivery-record:20260814999999_failure_fixture
-- 以下 SQL 必须能从未执行、部分执行和已执行三种状态前滚到同一结果
```

安装器只在该显式后继存在时越过旧失败行；旧行仍保留为 failed，修复行单独记为 applied。已执行
migration 内容变化仍会被拒绝。`rollback` 只生成计划，卸载默认保留数据，并要求所有 TenantModule
已停用。

生产应用不会自带 fixture。应用 owner 必须先提供真实 Plugin artifact 和 lock 身份，再由
PlatformOperator 开通 TenantModule，最后给 TenantMember 分配权限。

安装完成不等于功能可用。最终应看到：Plugin active、目标 TenantModule enabled、成员已获权限，
并且前后端入口都能在 Module 停用后立即拒绝新操作。

## 模块之间如何调用

### 同步命令和查询

需要立即得到结果且共享同一进程事务边界时，调用被依赖模块的公开合同：

```php
// 推荐模式，示例接口由派生应用定义；Peanut 当前没有这些 DCS 类。
$product = $productQueries->requireActiveSku($context, $skuId);
$receipt = $inventoryCommands->receive(
    $context,
    new ReceiveStock($warehouseId, $product->skuId, $quantity, $idempotencyKey)
);
```

调用方可以保存业务快照和返回 ID，但不能查询或更新 Product/Inventory 私有表。跨模块只读
页面由应用查询编排层组合公开 DTO；不要用跨模块 JOIN 形成隐藏合同。

### 领域事件

需要解耦事务或通知多个消费者时，可以定义领域事件，但当前 Peanut Host 没有已验证的
通用 Outbox/Event Bus。派生应用采用事件前必须补齐：

- 事件 schema 和版本；
- Outbox 与业务写入的同事务保证；
- 至少一次投递下的消费者幂等；
- 重试、死信、审计和可观测性；
- 事件中 Tenant、主体和最小数据范围。

在这些条件完成前，优先使用显式同步合同，不要用“发布事件”掩盖可靠性缺口。

## DCS 采用边界

DCS 是脚手架生成的独立应用，不是 Peanut Admin 内建业务域。Peanut 只提供 Account、
Tenant、RBAC、Module/Plugin、审计等通用扩展面。Party、Store、Warehouse、Supplier、
Product、Pricing、Inventory、Procurement 和 Trade 的表、状态机、API、事件与测试必须由
DCS 仓拥有。

Peanut 文档可以说明“如何写一个库存 Module”，但不能宣称 Peanut 已经提供库存业务。
源码仓 `fixture.delivery-record` 也只证明纵向链路，不是 DCS Product 或 Delivery Runtime。

## 最小测试

一个正式 Module 至少拥有：

1. manifest/lock 身份和路径安全测试；
2. migration 所有权、校验和、重复安装和失败恢复测试；
3. 两个 Tenant 的列表、详情和写入隔离测试；
4. TenantModule 停用拒绝测试；
5. 有权限、无权限和伪造 Tenant ID 的 API 测试；
6. 前端 contribution、路由和按钮权限测试；
7. 公开合同兼容测试；使用事件时再增加重复投递和重试测试。

只修改一个 Module 时运行该 Module 的聚焦测试、受影响客户端类型检查和 `git diff --check`；
不要为局部业务切片默认运行所有客户端和完整浏览器矩阵。

## 常见错误

| 现象 | 原因与处理 |
| --- | --- |
| Plugin 已安装但菜单不存在 | 检查 TenantModule、成员权限、client key 和 contribution 是否同时成立 |
| 页面可见但 API 403 | 后端权限未授权，或请求没有可信 TenantContext；不要放宽为前端判断 |
| migration 被拒绝 | 已应用内容或 checksum 改变；新增下一条 migration，不改写历史 |
| Module 能读到另一 Tenant 数据 | Repository 没有从 TenantContext 约束查询；停止发布并补隔离测试 |
| 新应用找不到 fixture | 正常行为；正式 create-app 生成空 Plugin lock，fixture 被 inventory 排除 |
| 想直接调用另一模块 Model | 改为依赖其 `Contracts/` 查询或命令接口 |
