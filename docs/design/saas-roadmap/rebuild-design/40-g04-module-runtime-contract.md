# G-04 Kernel、Module 与 TenantModule 契约

> 状态：Recalibrated and Reviewed（2026-07-15），通过 48 号复审，等待新编码批准
>
> 依赖：G-01 至 G-03
>
> 本文冻结 P0 的 Kernel 边界、Module manifest、部署安装、租户开通、成员授权、迁移、菜单、前端贡献、依赖图和跨模块契约。

## 1. 先用业务语言说明

Peanut Admin 中一项能力真正可用，需要通过三道门：

```text
部署级：这套代码里已经安装并成功加载 Module
AND
租户级：TenantModule 表示这个租户当前已开通
AND
成员级：当前 TenantMember 有对应 Permission 和 DataPermission
```

例如部署中包含库存 Module，不等于所有租户都能用；租户开通库存，也不等于所有员工都能调整库存。

Kernel 不走 TenantModule。登录、租户隔离、成员、权限、数据权限守卫、审计和 Module 注册是系统成立的前提，不能被某个租户关闭。

## 2. 五个概念最终边界

| 概念 | 真实含义 | P0 处理 |
| --- | --- | --- |
| Kernel | 所有受保护请求依赖的安全和运行内核 | 随系统安装，不可关闭 |
| Module | 拥有一组数据、规则、API、权限、迁移和界面贡献的能力单元 | 支持本地安装和 TenantModule 开通 |
| Plugin | 可安装、升级、卸载并携带一个或多个 Module 的交付制品 | P1；P0 不接收远程上传制品 |
| Package | Composer/npm 的代码复用单位 | P0 使用 workspace/path dependency |
| Client | Admin Web、POS、移动端、小程序等实际构建产物 | P0 只实现 Admin Web |

ProductProfile 是静态装配模板，不是第六种运行时权限根。P0 仍不建立 Application、Entry、Portal、SystemInstance 表。

Module 可以由一个 Composer/npm Package 交付，也可以先位于应用 Module 目录；但“能被包管理器安装”不自动等于“拥有业务数据和 TenantModule 生命周期”。

## 3. 不可关闭的 Kernel subsystem

| subsystem key | 责任 | 为什么不可关闭 |
| --- | --- | --- |
| `kernel.context` | HTTP/CLI/Job 可信上下文 | 缺失会导致租户串数据 |
| `kernel.identity` | Account、Credential | 没有它无法确认登录账号 |
| `kernel.tenancy` | Tenant、TenantMember、Tenant Guard | SaaS 隔离根 |
| `kernel.session` | Challenge、Session、Token、撤销 | 认证安全基线 |
| `kernel.authorization` | Role、Permission、后端动作检查 | 功能授权基线 |
| `kernel.data-permission` | ProtectedResource、Provider、查询/目标授权 | 数据范围基线 |
| `kernel.audit` | 平台和租户审计 Writer | 安全可追溯性 |
| `kernel.module-runtime` | manifest、依赖、安装和三层守卫 | 可选能力不会绕过安全内核 |
| `kernel.health` | 安装状态、registry 一致性和启动诊断 | 失败时必须 fail closed |

它们可以拆成内部类或 Composer Package，但不出现在 `pa_tenant_module`，也不能通过后台开关关闭。

## 4. Module 目录与代码归属

P0 继续遵守已确认的 monorepo：

```text
backend/app/Modules/<PascalSegments>/
  Contracts/           # 允许其他 Module 依赖的接口、DTO、事件
  Application/         # 本 Module 用例编排
  Domain/              # 业务规则
  Infrastructure/      # Repository、ORM、Provider 实现
  Http/                # Controller、Request/Response 映射
  Database/Migrations/
  Database/Seeds/
  Resources/
  module.json

frontend/src/modules/<kebab-key>/
  pages/
  routes.ts
  locales/
  index.ts
```

这里的后端 `Application/` 只是软件分层中的“用例编排层”目录，不是旧架构里的运行时 Application 对象，不产生 application_id，也不参与请求上下文。

目录和 namespace 使用唯一机械规则：Module key 按 `.` 分段，每段再把短横线单词转 PascalCase。`example.work-item` 对应：

```text
backend/app/Modules/Example/WorkItem/
PeanutAdmin\App\Modules\Example\WorkItem\
frontend/src/modules/example-work-item/
```

backend Composer 固定映射 `PeanutAdmin\App\` 到 `backend/app/`。manifest 的 provider 必须落在由 module key 推导出的 namespace 下；禁止声明任意宿主类。前端目录只把 `.` 转为 `-` 并保持小写 key。Generator 和 CI 使用同一转换函数，不能各自实现一份。

稳定、至少有两个真实消费者的 Module 才迁移为独立 Package 或 Plugin。P0 不因为“未来可能复用”把每个 Module 先拆成 Git 仓库。

Kernel 公共能力仍位于：

```text
packages/php/kernel
packages/php/data-permission
packages/php/testing
packages/web/admin-core
packages/web/admin-shell
packages/web/testing
```

## 5. Module manifest

### 5.1 完整 P0 示例

```json
{
  "schema_version": 1,
  "key": "example.work-item",
  "name": "Example Work Item",
  "description": "Fictional module used only for contract validation",
  "version": "0.1.0",
  "kernel_constraint": "^1.0",
  "license": "Apache-2.0",
  "php_package": null,
  "web_package": null,
  "dependencies": [
    { "module_key": "example.target", "version": "^1.0" },
    { "module_key": "example.reference", "version": "^1.0" }
  ],
  "backend": {
    "provider": "PeanutAdmin\\App\\Modules\\Example\\WorkItem\\ModuleProvider",
    "routes": "Resources/routes.php",
    "migrations": "Database/Migrations",
    "seeds": "Database/Seeds",
    "menus": "Resources/menus.json",
    "permissions": "Resources/permissions.json",
    "protected_resources": "Resources/protected-resources.json",
    "target_types": "Resources/target-types.json",
    "data_conditions": "Resources/data-conditions.json",
    "config_schema": "Resources/tenant-config.schema.json",
    "system_actors": "Resources/system-actors.json"
  },
  "frontend": {
    "entry": "frontend/src/modules/example-work-item/index.ts",
    "routes": "frontend/src/modules/example-work-item/routes.ts",
    "locales": "frontend/src/modules/example-work-item/locales"
  },
  "database": {
    "owned_tables": ["pa_example_work_item", "pa_example_work_item_event"]
  },
  "contracts": {
    "exports": [
      "PeanutAdmin\\App\\Modules\\Example\\WorkItem\\Contracts\\WorkItemQuery"
    ],
    "events": ["example.work-item.changed.v1"]
  },
  "tenant": {
    "enableable": true,
    "disable_behavior": "reject_new_operations",
    "requires": ["example.reference"]
  }
}
```

该示例只用于验证契约，不能进入正式产品功能或默认生产数据。

### 5.2 固定字段规则

| 字段 | 必填 | 规则 |
| --- | --- | --- |
| `schema_version` | 是 | manifest schema 整数版本；未知 major 拒绝 |
| `key` | 是 | 小写点号/短横线，发布后不可改名 |
| `name/description` | 是 | 展示信息，不参与授权 |
| `version` | 是 | SemVer |
| `kernel_constraint` | 是 | 不满足时安装/启动失败 |
| `license` | 是 | 开源仓必须可核查 |
| `php_package/web_package` | 否 | Package 名；应用内 Module 可为 NULL |
| `dependencies` | 否 | 仅必需 Module 和 SemVer constraint；P0 不做 optional dependency |
| `backend.provider` | 是 | 受信本地类，必须实现固定 ModuleProvider 接口 |
| `menus/permissions/resources/target_types/conditions` | 按能力 | 语言无关目录文件，均通过 JSON Schema 校验；目标类别、operation 基数和 selector 必须可交叉验证 |
| `config_schema` | 否 | TenantModule.config_json 的唯一 schema |
| `system_actors` | 否 | G-02 CLI/Job 允许的固定系统身份和 operation |
| `owned_tables` | 有数据库时 | 精确表名，不接受 `*` 作为所有权 |
| `contracts.exports/events` | 否 | 唯一可跨 Module 使用的 public API；TargetResolver 和 SharedMasterScopeProvider 也必须通过受控 registry 暴露 |
| `tenant.enableable` | 是 | false 只允许 Kernel/部署级工具类 Module |
| `tenant.requires` | 否 | 开通该 Module 时必须同时有效的 TenantModule |

manifest 是受版本控制的事实源。数据库、菜单或管理后台不能反向发明 Module key、Permission、Provider 类或表所有权。

## 6. 版本规则

- Module 使用 SemVer；破坏 public contract、权限语义或数据格式必须升 major。
- `schema_version` 只表示 manifest 格式，不等于 Module version。
- Module key、Permission key、ProtectedResource key 和事件名发布后不得复用为新语义。
- 已执行 migration 不能改内容；修正只能新增 migration。
- migration checksum 与历史不一致时启动/升级失败。
- P0 同仓 Module 与整仓 release 一起测试；Package 发布后仍记录独立 SemVer。
- `kernel_constraint`、Module dependencies 和 PHP/npm Package constraints 必须在安装前一次性解析。

## 7. 部署级安装记录

### 7.1 `pa_module_installation`

平台表，无 `tenant_id`。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `module_key` | VARCHAR(96) | NOT NULL | manifest key |
| `installed_version` | VARCHAR(32) | NOT NULL | 当前代码版本 |
| `manifest_schema_version` | INT UNSIGNED | NOT NULL | |
| `manifest_digest` | CHAR(64) | NOT NULL | canonical manifest digest |
| `status` | VARCHAR(24) | NOT NULL | `installing/active/upgrading/maintenance/failed` |
| `revision` | BIGINT UNSIGNED | `1` | 状态或版本变化递增 |
| `installed_at` | DATETIME(3) | NULL | |
| `activated_at` | DATETIME(3) | NULL | |
| `upgraded_at` | DATETIME(3) | NULL | |
| `last_error_code` | VARCHAR(96) | NULL | 稳定错误码，不存堆栈/密钥 |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束：

- `UNIQUE uk_module_installation_key (module_key)`。
- `INDEX idx_module_installation_status (status, module_key)`。
- 运行时只有 `active` 视为部署已安装可用。

状态机：

```text
installing -> active
installing -> failed -> installing
active -> upgrading -> active
upgrading -> failed -> upgrading
active -> maintenance -> active
```

P0 不提供生产卸载状态和后台“删除 Module”按钮。

### 7.2 `pa_module_migration`

该表是 ThinkPHP/Phinx migration 的校验和与 Module 所有权账本，不重写 migration runner。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `module_key` | VARCHAR(96) | NOT NULL | |
| `migration_key` | VARCHAR(160) | NOT NULL | `<module-key>:<timestamp-name>` |
| `module_version` | VARCHAR(32) | NOT NULL | 首次发布版本 |
| `checksum` | CHAR(64) | NOT NULL | migration 文件 digest |
| `batch_no` | BIGINT UNSIGNED | NOT NULL | |
| `status` | VARCHAR(24) | NOT NULL | `applying/applied/rolled_back/failed` |
| `started_at` | DATETIME(3) | NOT NULL | |
| `finished_at` | DATETIME(3) | NULL | |
| `error_code` | VARCHAR(96) | NULL | |

约束：`UNIQUE uk_module_migration (module_key, migration_key)`。

所有 schema migration 每个部署只执行一次，绝不按 Tenant 重复建表。Tenant enable hook 只能写带 tenant_id 的种子/配置数据，不能执行 DDL。

## 8. 菜单定义

### 8.1 `pa_menu_definition`

菜单是部署级 manifest 投影，不拥有业务权限。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `key` | VARCHAR(160) | NOT NULL | 全局稳定 |
| `module_key` | VARCHAR(96) | NOT NULL | `core` 或 Module key |
| `scope` | VARCHAR(16) | NOT NULL | `platform/tenant` |
| `parent_key` | VARCHAR(160) | NULL | 同 scope；无循环 |
| `type` | VARCHAR(16) | NOT NULL | `group/page/link` |
| `name` | VARCHAR(160) | NOT NULL | |
| `route_name` | VARCHAR(160) | NULL | page 时必填 |
| `route_path` | VARCHAR(255) | NULL | 固定相对路径 |
| `component_key` | VARCHAR(160) | NULL | build-time registry key，不是任意文件路径 |
| `icon` | VARCHAR(64) | NULL | Lucide/现有图标库 key |
| `sort_order` | INT | `0` | |
| `required_permission_id` | BIGINT UNSIGNED | NULL | FK Permission |
| `client_keys_json` | JSON | NOT NULL | P0 至少 `admin-web` 或 `platform-web` |
| `status` | VARCHAR(16) | `active` | `active/retired` |
| `manifest_digest` | CHAR(64) | NOT NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束：

- `UNIQUE uk_menu_definition_key (key)`。
- `UNIQUE uk_menu_route_name (scope, route_name)`；NULL 可重复。
- 页面菜单必须有 component registry key 和 Permission；纯 group 可以无 Permission，但只在至少一个子项可见时展示。
- 外链必须经过协议和域名 allowlist，不能执行 `javascript:` 或任意 HTML。

最终菜单是以下条件的交集：

```text
Client 匹配
AND ModuleInstallation active（core 除外）
AND TenantModule 有效（tenant scope 的可选 Module）
AND required Permission 有效
AND 父级可达
```

P0 不提供租户自定义菜单路由和组件路径。排序、别名、隐藏偏好可在 P1 增加 TenantMenuPreference，但不能改变后端 Permission。

## 9. 固定加载顺序

### 9.1 部署安装/启动

1. Composer/pnpm 先验证 Package lock 和平台版本。
2. 从显式 module registry 读取 manifest；不递归执行任意目录里的 PHP 文件。
3. 使用 JSON Schema 校验 manifest 和所有引用目录文件。
4. 检查 key、Permission、Resource、Condition、Menu、route name 全局唯一。
5. 解析 kernel constraint 和 Module dependency DAG。
6. 检测缺失依赖、版本不兼容和循环依赖；失败时不启动受保护业务路由。
7. 按拓扑顺序执行部署 migration，并核对 checksum。
8. 同步 Permission、ProtectedResource、ResourceOperation、TargetType、Condition 和 Menu registry。
9. 校验 operation target cardinality、TargetResolver、SharedMasterScopeProvider、其他 Contract、system actor 和 frontend component registry 都存在。
10. 注册 backend provider、routes、commands、queries 和 after-commit events。
11. 生成/验证前端静态 route/component registry 并构建 Client。
12. 通过 health checks 后将 ModuleInstallation 标记 active。

Catalog 同步和路由加载失败时，Module 进入 failed；不能只隐藏菜单后继续开放 API。

### 9.2 租户开通

1. 校验 Tenant active。
2. 校验 ModuleInstallation active。
3. 校验依赖 Module 对该 Tenant 均有效。
4. 校验 config_json 通过 Module schema。
5. 锁定 `(tenant_id, module_key)` TenantModule。
6. 在 TenantContext 中执行幂等 enable hook，只写该 Tenant 的默认配置/角色模板。
7. 将 TenantModule 标记 enabled，设置有效期和 authorization revision。
8. 递增 Tenant authorization revision，提交事务。
9. 清理菜单、权限和 Module guard 缓存，写租户审计。

ProductProfile 只是批量调用这套开通流程；不能直接插表跳过 hook、依赖和审计。

### 9.3 成员请求

顺序固定：

```text
G-02 Session/TenantContext
-> ModuleInstallation active
-> TenantModule 实时有效
-> G-03 Permission
-> G-03 DataPermission
-> Module business rule
```

任何 Controller 都不能自行调整这个顺序。

## 10. TenantModule 实时有效性

G-01 的 TenantModule 只有同时满足以下条件才有效：

```text
status = enabled
AND (effective_at IS NULL OR effective_at <= now)
AND (expires_at IS NULL OR now < expires_at)
AND ModuleInstallation.status = active
```

到达 expires_at 后，即使后台定时任务尚未把 status 写成 `expired`，Guard 也必须立即拒绝。定时任务只负责展示状态归一化和审计，不承担正确性。

Module guard 缓存 TTL 不得超过下一次 effective/expiry 时间。

## 11. 停用行为

租户停用 Module：

- 新 HTTP 请求立即返回 `MODULE_TENANT_DISABLED`。
- 菜单和页面贡献不再返回。
- Permission/DataPolicy 关系和 Module 数据保留，不删除。
- 新队列任务不得入队；已入队任务执行前重新检查并拒绝。
- 正在执行的普通短事务按开始时授权完成或回滚；长任务必须在关键写入前再次检查。
- enable/disable hook 不得删除业务数据或绕过审计。
- 如果仍有 enabled 的依赖方 Module，停用被阻止并列出依赖方；P0 不自动级联停用。

部署 Module 进入 maintenance/failed 时，所有租户均不可用；Kernel 继续提供登录、诊断和平台修复入口。

## 12. 依赖和循环

- Module dependencies 只允许形成 DAG。
- 安装、migration、registry、enable 和 health check 均使用同一拓扑顺序。
- 循环依赖在 CI、安装和启动三个阶段都失败。
- Module A 可以依赖 Module B 的 `Contracts`，不能依赖 B 的 Domain/Infrastructure/ORM Model。
- P0 不支持 optional dependency；可选集成使用 after-commit event 或显式 adapter，真实需求出现后再冻结。
- 租户开通 A 时，A 的所有 `tenant.requires` 必须对同一 Tenant 有效。
- 依赖版本不兼容时不能通过“先运行看看”跳过。

## 13. 跨 Module 命令、查询和读模型

### 13.1 写命令

写另一个 Module 的数据只能调用其 public command service：

```php
interface InventoryCommand
{
    public function decrease(DecreaseStockCommand $command): StockChangeResult;
}
```

Command DTO 至少携带 G-03 允许的 typed target descriptors、数量、业务单据引用和 idempotency key；TenantContext 由运行时注入，不允许调用方伪造。普通写 command 必须明确一个 primary target，其他来源/目的地/关联目标按 operation schema 分别命名并逐个授权。

同一进程、同一数据库时可以共享应用事务协调器，但只有数据所有者 Repository 写自己的表。跨进程后再换 API/event，不改变 Contract 语义。

### 13.2 查询契约

跨 Module 查询返回不可变 DTO 或分页结果，不返回 ORM Model、Query Builder 或可写 Entity：

```php
interface ProductQuery
{
    public function getSku(SkuId $id): ?SkuView;
}
```

查询契约必须执行自己的 Tenant/DataPermission 或声明为受控内部查询；调用方不能要求“返回未过滤 Query Builder”。

调用共享主档 Query 时，提供方必须同时应用统一主档的 ownership/visibility/usage scope 和调用者的数据权限。调用方得到同一个稳定资源 ID，不能要求提供方分别返回“平台表 ID”和“租户表 ID”再自行 UNION。

### 13.3 事件

- 事件只在数据库事务提交后发布。
- 事件名带版本，如 `inventory.stock.changed.v1`。
- Payload 使用稳定 ID 和必要快照，不带 ORM 对象或 secret。
- 消费者幂等，不能假设只投递一次。
- 事件适合通知和读模型，不用于要求立即原子成功的核心写链路。

### 13.4 读模型

跨 Module 报表使用明确 owner 的投影/读模型：

- 写入由订阅者或批处理维护。
- 标记来源 Module、事件版本、延迟和重建方式。
- 读模型不能反向修改源 Module 真相表。
- 跨 Tenant 汇总只能属于明确授权的 Collaboration/Reporting 能力或脱敏平台治理指标。PlatformOperator 不因平台身份自动获得租户业务明细，任何 Module 都不能关闭 Tenant Guard 后扫描全站业务表。

### 13.5 共享主档与业务目标契约

Module 可以声明三类不同事实，但不得混为“是否带 tenant_id”：

| 事实 | manifest ownership | 约束 |
| --- | --- | --- |
| Tenant 私有事实 | `tenant_owned` | `tenant_id NOT NULL`，只在当前 Tenant |
| 业务目标事实 | `business_target_owned` | `tenant_id NOT NULL`，同时有 Module 定义的 typed boundary target |
| 统一共享主档 | `shared_master` | 一个真相源和 ID 空间，Module 显式表达创建者、归属者、维护者、可见和可使用范围 |

`shared_master` 不等于 `global_reference`。前者有业务归属和作用范围，必须注册 SharedMasterScopeProvider；后者仅用于部署级只读码表。共享主档表如何存 ownership/scope 关系由业务 Module 决定，Kernel 不预建 Product/SKU 等表，也不允许在 tenant-owned 表里用 `tenant_id NULL/0` 模拟共享。

P0 虚构示例使用单向依赖图：`example.target` 拥有 Project/Queue target type；`example.reference` 依赖 target，并证明部署种子和 Tenant 自建 ReferenceItem 存在于同一主档/ID 空间、通过作用范围决定 Project 能否查看和引用；`example.work-item` 同时依赖 target/reference，只能调用它们的公开 Query/Scope Contract，不能直接 JOIN 内部表。

## 14. 禁止直接访问的执行检查

### 14.1 PHP 依赖

采用成熟 [Deptrac](https://github.com/deptrac/deptrac) 定义 Module layers：

- 外部 Module 只允许依赖目标 Module 的 `Contracts`。
- Domain 不依赖 Http/Infrastructure。
- Module 不能依赖 backend app 的具体 Controller/Model。
- CI 中 Deptrac violation 返回非零，不建立长期 skip list 掩盖新问题。

### 14.2 数据表所有权

Deptrac 不能识别 SQL 表所有权，因此另设结构化检查：

- 每张 Module 表必须列在该 Module manifest `owned_tables`。
- migration 文件由 migration API 声明 owner 和表名；检查器使用 AST/结构化 migration metadata，不用脆弱正则解析 PHP。
- Repository 只能通过 `OwnedTableRegistry` 获取本 Module 表名。
- 原生 SQL 只允许在受审 adapter 中使用，并通过 SQL parser 提取表引用与 registry 对照。
- 跨 Module DB view、trigger、foreign key 和 JOIN 默认禁止；例外必须形成正式 ADR 和公开 Contract。
- CI 同时扫描 migration ownership、Repository table registry、raw SQL adapter 和数据库 schema 快照。

### 14.3 前端依赖

- Module 只从对方 public `index.ts`/package exports 导入。
- 禁止 `../../other-module/internal-file` 深层导入。
- ESLint import boundary 和 TypeScript project references 在 CI 强制。
- 前端可共享稳定 UI/状态能力，但业务状态所有权仍属于对应 Module。

## 15. ProductProfile

P0 Profile 是版本控制的静态 JSON/YAML：

```json
{
  "schema_version": 1,
  "key": "example-basic-admin",
  "name": "Example Basic Admin",
  "modules": [
    { "module_key": "example.target", "config": {} },
    { "module_key": "example.reference", "config": {} },
    { "module_key": "example.work-item", "config": {} }
  ],
  "role_templates": ["tenant-owner", "tenant-operator"]
}
```

Profile 只用于新租户初始化或人工预览变更：

- 不作为每个请求上下文。
- 不覆盖 TenantModule 的当前事实。
- 不自动删除 Profile 新版本中移除的 Module。
- 应用前展示将启用的依赖、配置、角色和菜单。
- 不包含门店、仓库、商品等真实业务数据。
- 可以按 profile 配置幂等创建一个默认根 Department，但 Kernel 和 Tenant 本身不要求根部门永久存在。

## 16. 升级和失败恢复

### 16.1 P0 本地受控升级

1. 校验当前版本、目标版本、Package lock、manifest 和依赖图。
2. 生成 migration/registry/config 变化预览。
3. 要求数据库备份和恢复验证点。
4. 将目标 ModuleInstallation 设为 upgrading/maintenance。
5. 按拓扑顺序执行 migration。
6. 同步 Permission/Resource/Condition/Menu registry。
7. 运行 contract、tenant isolation、health 和 smoke tests。
8. 成功后标记 active，更新 version/digest/revision。
9. 清理 registry、Module guard、菜单和 authorization 缓存。
10. 写平台审计和升级报告。

P0 不做远程自动升级、商业授权服务器和插件市场。

### 16.2 失败

- migration 前失败：恢复旧代码和 active 状态。
- 可逆 migration 失败：由 migration runner 回滚当前 batch，再恢复旧代码。
- 不可逆 migration 已提交：不能假装自动回滚；进入 maintenance/failed，按已验证备份恢复。
- registry 同步失败：Module 不回到 active，API fail closed。
- 前端构建与后端 manifest 不一致：整次 release 失败，不能只部署一边。

每个 migration 必须声明是否 reversible；没有可靠 down 逻辑时明确标记 irreversible。

## 17. Plugin 和卸载停止线

P0 只加载仓库内、构建时受信的 Module。以下延后到 P1：

- 上传 ZIP/Composer package 动态安装。
- 数字签名和来源信任。
- Plugin 可同时携带多个 Module 和前端制品。
- 可视化升级、禁用和卸载。
- 第三方 hook/extension point 市场。

生产卸载还必须先解决：所有 Tenant 已停用、依赖方为零、数据导出/保留、migration rollback、菜单/权限退役和恢复演练。P0 不提供一个看似简单但会丢数据的卸载按钮。

## 18. 错误码

| 错误码 | 含义 |
| --- | --- |
| `MODULE_NOT_INSTALLED` | 部署不存在该 Module |
| `MODULE_INSTALLATION_FAILED` | 安装/registry/health 失败 |
| `MODULE_VERSION_INCOMPATIBLE` | Kernel/Module/Package 版本不满足 |
| `MODULE_DEPENDENCY_MISSING` | 依赖缺失 |
| `MODULE_DEPENDENCY_CYCLE` | 依赖图成环 |
| `MODULE_TENANT_DISABLED` | TenantModule 未启用 |
| `MODULE_TENANT_NOT_EFFECTIVE` | 尚未生效或已过期 |
| `MODULE_DEPENDENT_ACTIVE` | 仍有启用依赖方，不能停用 |
| `MODULE_CONFIG_INVALID` | Tenant 配置不符合 schema |
| `MODULE_MIGRATION_CHECKSUM_MISMATCH` | 已执行 migration 被修改 |
| `MODULE_CONTRACT_MISSING` | manifest 声明的 Contract/Provider 不存在 |
| `MODULE_REGISTRY_CONFLICT` | key/route/menu/permission/resource 冲突 |

G-05 冻结 HTTP 映射。开发配置错误在生产环境也必须拒绝，不得降级成“忽略该 Module”。

## 19. G-04 必测场景

1. Kernel subsystem 不出现在 TenantModule 管理页。
2. 部署没有安装 Module 时，伪造 TenantModule 也不可用。
3. Tenant 未开通时，有 Permission 也不可用。
4. Tenant 已开通但成员无 Permission 时不可用。
5. 三层都满足时才进入 Module 业务规则。
6. effective_at 未到时拒绝。
7. expires_at 到达后无需等待定时任务即拒绝。
8. ModuleInstallation maintenance/failed 时所有租户拒绝。
9. 缺失 dependency 时安装失败。
10. dependency 版本不匹配时安装失败。
11. 循环依赖在 CI 和启动时均失败。
12. migration 只执行一次，不按 Tenant 建表。
13. 已执行 migration 内容改变时 checksum 检查失败。
14. Tenant enable hook 只能在目标 TenantContext 写入。
15. enable hook 重试不会重复种子。
16. 配置不符合 JSON Schema 时不开通。
17. 有 active dependent 时不能停用依赖 Module。
18. Module 停用后数据、RolePermission 和审计保留。
19. 已入队任务在 Module 停用后执行时拒绝。
20. Menu 不能因为存在于数据库就绕过 Module/TenantModule/Permission。
21. component_key 不存在时前端构建失败。
22. 外链 `javascript:` 被拒绝。
23. Module A 深层 import Module B 内部类时 Deptrac/CI 失败。
24. Module A Repository 访问 B 的 owned table 时 CI 失败。
25. Raw SQL 引用未登记表时 CI 失败。
26. 跨 Module Query 不返回 ORM Model。
27. 跨 Module Command 由数据所有者 Repository 写表。
28. Event 在事务回滚时不发布。
29. 重复 Event 消费不会重复写业务结果。
30. 读模型可以重建且不能反写源表。
31. Profile 应用仍逐个执行 Tenant enable 流程。
32. Profile 更新不会静默停用租户现有 Module。
33. registry 同步失败后 Module 不恢复 active。
34. 不可逆 migration 失败时进入 maintenance 并要求备份恢复。
35. Platform token 不能借 Module API 进入 Tenant Context。
36. TenantModule 缓存 TTL 不超过下一次有效期变化。
37. manifest 未声明 operation target cardinality 或 target type 时 registry 编译失败。
38. 同一 target type key 被两个 Module 声明所有权时安装失败。
39. 一个 Module 可让同一 Tenant 的多个 Project 实例使用同一套服务，不复制 Module 或迁移。
40. `one_required` command 没有 primary target 或带多个 primary target 时在进入业务规则前拒绝。
41. shared_master 缺少 SharedMasterScopeProvider 时 Module 不能 active。
42. shared_master 查询只返回当前成员和 typed target 可见/可用的统一候选集，不暴露第二套 ID。
43. WorkItem Module 不能 JOIN Reference 表，必须调用公开 Query/Scope Contract。
44. 平台操作员没有租户业务会话时不能调用 shared_master 的租户业务查询。

## 20. G-04 结论

Module 不是菜单目录，也不是在一个“门店 Module”里复制商品、库存子模块。一个 Tenant 下的多门店、多仓库等实例共享同一套 Module 代码；门店管理 Client 和仓储管理 Client 可以装配、展示和调用相同的商品/库存 Module，Module 的数据、规则、迁移和公开契约始终只有一个所有者。

P0 保持模块化单体和同库事务，避免先造微服务；但通过 manifest、Contracts、Deptrac、表所有权检查和读模型边界，保证未来需要分开部署时有明确改造入口，而不是依赖跨表调用形成无法拆分的系统。
