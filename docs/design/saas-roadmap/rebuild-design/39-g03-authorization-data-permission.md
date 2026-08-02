# G-03 RBAC 与数据权限契约

> 状态：Recalibrated and Reviewed（2026-07-15），通过 48 号复审，等待新编码批准
>
> 依赖：`37-g01-kernel-data-model.md`、`38-g02-auth-session-context.md`
>
> 本文冻结 P0 的功能权限、数据权限表、条件组合、Module Provider、执行顺序、缓存失效和默认拒绝行为。

## 1. 先用业务语言说明

权限分成两道独立检查：

1. 功能权限回答“这个成员能不能执行库存查询、库存调整、成员编辑等动作”。
2. 数据权限回答“即使能执行库存查询，他到底能看全部库存、自己负责的库存、本部门库存，还是指定门店/仓库的库存”。

一个 Tenant 可以同时有多个门店、仓库、供应商等类别，一个成员也可以同时管理同一类别中的多个目标。授权不能只保存一个“当前门店”，而要按资源和操作得到带类别的目标集合：

```text
商品查看 -> Store {A, B, C}
库存查看 -> Warehouse {W1, W2}
库存调整 -> Warehouse {W1}
```

这些集合属于本次授权计算，不写入 TenantSession。普通写命令仍明确一个主要边界目标；多目标读、聚合和策略发布由 operation 自己声明。

一个菜单可见不代表 API 可调用；一个 API 可调用也不代表可以操作任意 ID。最终允许公式固定为：

```text
会话和 TenantContext 有效
AND 部署已安装 Module
AND TenantModule 当前有效
AND 成员拥有后端动作 Permission
AND 数据权限允许全部目标
AND Module 业务规则允许
```

任何一项缺失都拒绝。授权通过只说明“可以尝试执行”，不表示库存数量、订单状态或财务规则一定允许业务操作。

## 2. P0 取舍

| 项目 | P0 决策 |
| --- | --- |
| 功能权限 | TenantMember 多 Role，Role 多 Permission，取允许并集 |
| 数据权限 | 配置在 Role 上；特殊成员通过专用 Role 解决 |
| 规则效果 | 只支持 allow，不实现显式 deny |
| 角色继承 | 不实现 |
| 无规则 | 默认拒绝，只有资源 operation 显式 `tenant_wide` 可不配置规则 |
| Tenant 全部 | 只表示当前 Tenant 内全部，永不表示平台全部租户 |
| 条件组合 | 组内 AND，组间 OR，多角色之间 OR |
| 自定义条件 | 只能由 Module manifest 注册并由 Provider 实现 |
| 任意表达式 | 禁止 SQL、字段名、脚本、PHP 类名和租户自定义策略 DSL |
| 列表与单对象 | 使用同一 ProtectedResource/operation 和 Provider |
| 平台权限 | PlatformRole 单独计算，不复用 Tenant Role/DataPolicy |
| 目标集合 | 每个 TargetSet 只能有一个目标类别；一个 operation 可以组合多个 typed TargetSet |
| 操作基数 | 每个 operation 显式声明单目标写、多目标读、聚合读、策略发布或受控批量写 |
| 共享主档 | 使用同一资源真相和 Module scope Provider；不把 `tenant_id NULL` 当共享，也不拆两套资源表 |

不建立 `is_super`、`ignore_scope`、`without_tenant` 等普通业务可调用的绕过开关。租户所有者也是一个显式 Role。

## 3. 功能权限

### 3.1 Permission key

Permission 使用稳定代码：

```text
<module>.<resource-or-use-case>.<action>

core.member.read
core.member.update
core.role.assign
inventory.stock.read
inventory.stock.adjust
inventory.stock.export
```

固定规则：

- 后端 command/query/API handler 必须声明一个或多个 Permission key。
- read、create、update、delete、export、approve、cancel、credential、security-config 等动作不得共用模糊权限。
- 菜单和按钮只引用 Permission 决定显示；它们不能代替后端校验。
- Permission 从 Kernel/Module manifest 编译到 G-01 的 `pa_permission`，租户管理员不能自建。
- `retired` Permission 不生效，也不能重新分配；旧 key 不得换成新含义。

### 3.2 多角色合并

功能权限计算：

```text
effective_permissions = UNION(
  每个 active Role 当前存在的 RolePermission
)
INTERSECT 当前部署和 TenantModule 中可用的 Module 权限
```

P0 不支持 deny，因此没有“拒绝优先”歧义。若业务需要减少权限，应移除角色或权限；不能依赖另一个角色抵消。

平台端使用 PlatformOperator -> PlatformRole -> PlatformRolePermission 独立链路。平台和租户权限集合绝不合并。

## 4. ProtectedResource 与 operation 目录

数据权限不是对整个 Module 粗暴加一个 Scope。每个 Module 对需要保护的资源及其 operation 逐项声明。

### 4.1 `pa_protected_resource`

部署级目录，只由 manifest compiler 写入。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `key` | VARCHAR(160) | NOT NULL | 全局稳定，如 `inventory.stock` |
| `module_key` | VARCHAR(96) | NOT NULL | 数据和规则所有者 |
| `name` | VARCHAR(160) | NOT NULL | |
| `ownership` | VARCHAR(32) | NOT NULL | `tenant_owned/business_target_owned/shared_master/global_reference/platform_internal` |
| `provider_key` | VARCHAR(160) | NOT NULL | ResourceAccessProvider registry key |
| `status` | VARCHAR(16) | `active` | `active/retired` |
| `manifest_version` | VARCHAR(32) | NOT NULL | |
| `manifest_digest` | CHAR(64) | NOT NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |
| `retired_at` | DATETIME(3) | NULL | |

约束：

- `UNIQUE uk_protected_resource_key (key)`。
- `INDEX idx_protected_resource_module (module_key, status)`。
- 未登记、retired 或 Provider 缺失的资源默认拒绝。
- `shared_master` 表示同一业务主档可由不同 Tenant/业务目标创建、归属、维护或使用；它仍必须由 Module Provider 校验作用范围，不能退化成全站可写。
- `global_reference` 只用于国家地区码、系统字典等部署级只读参考，不得用来承载商品一类有归属和经营范围的共享业务主档。

### 4.2 `pa_target_type`

部署级业务目标类型目录，只由 Module manifest compiler 写入。它登记“Project/Store/Warehouse 这一类目标怎样解析和查询”，不保存任何目标实例。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `key` | VARCHAR(160) | NOT NULL | 全局稳定，如 `example.project` |
| `module_key` | VARCHAR(96) | NOT NULL | 类型和 Resolver 所有者 |
| `name` | VARCHAR(160) | NOT NULL | 展示名 |
| `resolver_key` | VARCHAR(160) | NOT NULL | ResourceTargetResolver registry key |
| `catalog_provider_key` | VARCHAR(160) | NOT NULL | ResourceTargetCatalogProvider registry key |
| `id_format` | VARCHAR(16) | NOT NULL | `decimal/uuid/ulid/string` |
| `status` | VARCHAR(16) | `active` | `active/retired` |
| `manifest_version` | VARCHAR(32) | NOT NULL | |
| `manifest_digest` | CHAR(64) | NOT NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束：

- `UNIQUE uk_target_type_key (key)`；一个 key 只能有一个 Module 所有者。
- Resolver/CatalogProvider 必须位于所有者 Module 的受控 namespace 或 public Contract registry。
- retired、Resolver 缺失或 CatalogProvider 缺失时，所有引用该类型的 operation 和 TargetSet fail closed。
- Kernel 不建立 `pa_subject`、`pa_business_object` 等实例表；实例继续保存在所有者 Module 中。

### 4.3 `pa_resource_operation`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `protected_resource_id` | BIGINT UNSIGNED | NOT NULL | FK ProtectedResource |
| `operation` | VARCHAR(64) | NOT NULL | `list/detail/create/update/delete/export/...` 或稳定业务动作 |
| `access_mode` | VARCHAR(32) | NOT NULL | 见下表 |
| `target_cardinality` | VARCHAR(32) | NOT NULL | 见目标基数表；必须由 manifest 显式声明 |
| `permission_match` | VARCHAR(8) | `all` | `all/any` |
| `audit_level` | VARCHAR(32) | `deny_and_write` | `deny/write/deny_and_write/all` |
| `status` | VARCHAR(16) | `active` | `active/retired` |
| `manifest_digest` | CHAR(64) | NOT NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束：

- `UNIQUE uk_resource_operation (protected_resource_id, operation)`。
- 未声明的 operation 默认拒绝。

Access mode：

| 值 | 含义 | 示例 |
| --- | --- | --- |
| `tenant_wide` | Permission 通过后可访问当前 Tenant 内全部该类资源 | 租户自己的低敏配置 |
| `rule_filtered` | 列表/统计必须编译数据权限谓词 | 成员、订单、库存列表 |
| `explicit_targets` | 详情、修改、删除、审批等必须验证全部已知目标 | 调整库存、删除成员 |
| `global_reference_read` | manifest 明确声明的全局参考数据只读 | 国家地区码表 |
| `system_internal` | 只允许注册的系统任务 | migration/maintenance |

同一资源的 list、detail、update、export 可以使用不同 access mode，不能只给整个 Module 配一个模式。

目标基数与 access mode 是两个维度：access mode 决定怎样做授权，target cardinality 决定一次操作可以携带多少主要业务边界目标。

| 值 | 含义 | 固定限制 |
| --- | --- | --- |
| `none` | operation 没有业务边界目标 | 仍按 Tenant/Permission/Resource 保护 |
| `one_required` | 必须解析出一个主要目标 | 普通 create/update/delete/业务 command 默认使用；不能用目标数组替代 |
| `many_readable` | 可在授权范围中读取一个或多个同类目标 | 请求筛选只能收窄；不传时表示授权集合而非全 Tenant |
| `aggregate_read` | 可读取多个目标的聚合结果 | 只读；结果必须带范围摘要，不能反向修改源数据 |
| `policy_publish` | 将一份策略发布到多个目标 | 写一次策略并建立发布任务/结果，不循环伪装成多次普通写 |
| `bulk_write` | 一次修改多个目标的业务事实 | P0 默认禁用；必须有独立 Permission、endpoint、幂等、限额、逐目标审计和专项复审 |

`one_required` 约束主要归属目标。调拨等业务若还有来源、目的地或关联目标，operation schema 必须分别命名，并让 Provider 全部校验；不能因此把普通写操作改成无边界 `bulk_write`。

### 4.4 `pa_resource_operation_target_type`

部署级目录，声明 operation 允许哪些 Module 业务目标类别。它只保存稳定类型 key，不保存门店、仓库等实例。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `resource_operation_id` | BIGINT UNSIGNED | NOT NULL | FK ResourceOperation |
| `target_type_id` | BIGINT UNSIGNED | NOT NULL | FK pa_target_type；API 使用对应稳定 key |
| `target_role` | VARCHAR(64) | `primary` | `primary` 或 operation schema 声明的 `source/destination/related/...` |
| `input_mode` | VARCHAR(16) | `explicit` | `explicit/derived/either`；derived 也必须由 Provider 从已授权资源解析 |
| `policy_selection_permission_id` | BIGINT UNSIGNED | NULL | 配置指定目标策略时额外要求的 Module 管理 Permission；可配置 selector 时必填 |
| `status` | VARCHAR(16) | `active` | `active/retired` |

约束：

- `UNIQUE uk_resource_operation_target_type (resource_operation_id, target_role, target_type_id)`。
- `target_cardinality != none` 时至少有一个 active target type；`none` 时不得声明 primary target。
- 每个请求按 `target_resource_key` 分组为 typed TargetSet；同一 TargetSet 不能混入多个类别。
- Operation 可以允许多个类别，但普通 `one_required` 命令只能从允许类别中解析一个 primary target。
- 运行时 target candidates 使用当前 ResourceOperation 的功能/数据权限；策略配置 candidates 同时要求 `core.role.data-policy.manage` 和 policy_selection_permission，不能借配置页枚举全部业务目标。

### 4.5 `pa_resource_operation_permission`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `resource_operation_id` | BIGINT UNSIGNED | NOT NULL | FK ResourceOperation |
| `permission_id` | BIGINT UNSIGNED | NOT NULL | FK Permission |
| `sort_order` | INT | `0` | |

约束：`UNIQUE uk_resource_operation_permission (resource_operation_id, permission_id)`。

API handler 不自行猜 Permission。它引用 ResourceOperation，授权服务读取已编译关系并按 `all/any` 判断。

## 5. 数据权限条件定义

### 5.1 P0 内置六类范围

| condition key | 中文 | 真实含义 |
| --- | --- | --- |
| `core.tenant_all` | 全部 | 当前 Tenant 内该资源全部记录 |
| `core.self` | 本人 | Provider 明确定义的创建人、负责人或被分配人 |
| `core.own_department` | 本部门 | 当前成员主部门映射到的资源 |
| `core.department_tree` | 本部门及下级 | 当前主部门和所有后代部门映射到的资源 |
| `core.specified_departments` | 指定部门 | 管理员明确选择的一个或多个部门，不自动包含下级 |
| `core.specified_objects` | 指定业务对象 | Module 允许选择的门店、仓库、库存地点等对象 |

没有主部门时，`own_department` 和 `department_tree` 产生空范围，不回退为全部。

`self` 不等于固定字段 `created_by`。例如订单可以按负责人，任务可以按 assignee；Module Provider 必须声明真实关系。

### 5.2 `pa_data_condition_definition`

部署级目录，只由 Kernel/Module manifest 写入。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `key` | VARCHAR(160) | NOT NULL | core 或 Module 稳定 key |
| `module_key` | VARCHAR(96) | NOT NULL | `core` 或定义者 Module |
| `category` | VARCHAR(32) | NOT NULL | `tenant/self/department/selected/relation` |
| `target_mode` | VARCHAR(32) | NOT NULL | `none/department/resource` |
| `config_schema_json` | JSON | NULL | 代码控制的 JSON Schema；无配置时 NULL |
| `status` | VARCHAR(16) | `active` | `active/retired` |
| `manifest_version` | VARCHAR(32) | NOT NULL | |
| `manifest_digest` | CHAR(64) | NOT NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束：`UNIQUE uk_data_condition_key (key)`。

### 5.3 `pa_resource_operation_condition`

声明某个 operation 允许配置哪些 condition，并限制可选择的业务对象类型。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `resource_operation_id` | BIGINT UNSIGNED | NOT NULL | FK ResourceOperation |
| `condition_definition_id` | BIGINT UNSIGNED | NOT NULL | FK ConditionDefinition |
| `selector_resource_key` | VARCHAR(160) | NULL | `specified_objects` 时填写允许选择的对象类型 |
| `selector_resource_key_norm` | VARCHAR(160) | GENERATED | `COALESCE(selector_resource_key, '')`，只用于唯一约束 |
| `status` | VARCHAR(16) | `active` | `active/retired` |

约束：

- `UNIQUE uk_resource_operation_condition (resource_operation_id, condition_definition_id, selector_resource_key_norm)`，避免 MySQL 允许多个 NULL 造成重复定义。
- 租户管理员不能为 operation 使用 manifest 未允许的 condition 或 selector。

## 6. 租户数据权限配置表

P0 不提供成员级 DataPermission 表。某个成员需要特殊范围时，为他创建可命名、可审计的专用 Role。这样避免角色规则和成员例外互相覆盖。真实出现大量临时个人例外后，再在 P1 增加 MemberDataPermission，合并语义仍为 allow 并集。

### 6.1 `pa_data_permission_policy`

一个 Role 对一个 ProtectedResource operation 的数据权限配置容器。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | FK Tenant |
| `role_id` | BIGINT UNSIGNED | NOT NULL | 同 Tenant Role |
| `protected_resource_id` | BIGINT UNSIGNED | NOT NULL | 部署目录 FK |
| `resource_operation_id` | BIGINT UNSIGNED | NOT NULL | 必须属于同 Resource |
| `status` | VARCHAR(16) | `active` | `active/disabled/archived` |
| `valid_from` | DATETIME(3) | NULL | NULL 表示立即 |
| `valid_until` | DATETIME(3) | NULL | NULL 表示无日期过期 |
| `revision` | BIGINT UNSIGNED | `1` | |
| `reason` | VARCHAR(300) | NULL | 敏感/临时范围必填 |
| `created_by_member_id` | BIGINT UNSIGNED | NOT NULL | 同 Tenant |
| `updated_by_member_id` | BIGINT UNSIGNED | NOT NULL | 同 Tenant |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |
| `archived_at` | DATETIME(3) | NULL | |

约束和索引：

- `UNIQUE uk_data_policy (tenant_id, role_id, resource_operation_id)`。
- `UNIQUE uk_data_policy_tenant_id (tenant_id, id)`。
- `INDEX idx_data_policy_active (tenant_id, role_id, status, valid_until)`。
- 复合 FK `(tenant_id, role_id) -> pa_role(tenant_id, id)`。
- `protected_resource_id` 必须与 `resource_operation_id` 的父资源一致，由 Service 和 compiler test 双重验证。

### 6.2 `pa_data_permission_group`

同一 Policy 下多个组按 OR 合并；每个组内的 Condition 按 AND 合并。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | |
| `data_permission_policy_id` | BIGINT UNSIGNED | NOT NULL | 同 Tenant Policy |
| `name` | VARCHAR(120) | NOT NULL | 便于后台解释来源 |
| `match_mode` | VARCHAR(8) | `all` | P0 只允许 `all` |
| `sort_order` | INT | `0` | 仅展示，不改变逻辑 |
| `status` | VARCHAR(16) | `active` | `active/disabled` |
| `revision` | BIGINT UNSIGNED | `1` | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束：

- `UNIQUE uk_data_group_tenant_id (tenant_id, id)`。
- `UNIQUE uk_data_group_name (tenant_id, data_permission_policy_id, name)`。
- 复合 FK `(tenant_id, data_permission_policy_id) -> pa_data_permission_policy(tenant_id, id)`。

P0 常规后台每个组只配置一个基础范围。AND 组合只用于 Module 明确支持的场景，例如“本人负责 AND 指定仓库”；UI 不默认引导复杂组合。

### 6.3 `pa_data_permission_condition`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | |
| `data_permission_group_id` | BIGINT UNSIGNED | NOT NULL | 同 Tenant Group |
| `condition_definition_id` | BIGINT UNSIGNED | NOT NULL | 部署目录 FK |
| `target_set_id` | BIGINT UNSIGNED | NULL | condition 需要目标时必填 |
| `target_set_key` | BIGINT UNSIGNED | GENERATED | `COALESCE(target_set_id, 0)`，只用于唯一约束 |
| `config_json` | JSON | NULL | 必须通过 definition schema |
| `status` | VARCHAR(16) | `active` | `active/disabled` |
| `revision` | BIGINT UNSIGNED | `1` | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_data_condition (tenant_id, data_permission_group_id, condition_definition_id, target_set_key)`，避免 NULL 绕过唯一约束。
- 复合 FK `(tenant_id, data_permission_group_id) -> pa_data_permission_group(tenant_id, id)`。
- 复合 FK `(tenant_id, target_set_id) -> pa_data_permission_target_set(tenant_id, id)`；NULL 时不检查。
- `tenant_all/self/own_department/department_tree` 的 `target_set_id` 必须 NULL。
- `specified_departments/specified_objects` 的 `target_set_id` 必须非空。
- `specified_objects` 引用的 TargetSet.target_resource_key 必须与 `pa_resource_operation_condition.selector_resource_key` 一致。
- `config_json` 不能包含 SQL、列名、PHP 类名、脚本或远程 `$ref`。

### 6.4 `pa_data_permission_target_set`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | |
| `name` | VARCHAR(120) | NOT NULL | |
| `target_mode` | VARCHAR(32) | NOT NULL | `department/resource` |
| `target_resource_key` | VARCHAR(160) | NOT NULL | `core.department` 或一个 Module 注册的业务目标类别 |
| `status` | VARCHAR(16) | `active` | `active/disabled/archived` |
| `revision` | BIGINT UNSIGNED | `1` | 目标变化递增 |
| `created_by_member_id` | BIGINT UNSIGNED | NOT NULL | 同 Tenant |
| `updated_by_member_id` | BIGINT UNSIGNED | NOT NULL | 同 Tenant |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |
| `archived_at` | DATETIME(3) | NULL | |

约束：

- `UNIQUE uk_data_target_set_tenant_id (tenant_id, id)`。
- `INDEX idx_data_target_set_status (tenant_id, target_resource_key, status, id)`。
- `target_mode=department` 时 target_resource_key 固定为 `core.department`；`resource` 时必须存在于 active `pa_target_type.key`。
- 一个 TargetSet 只允许一个 target_resource_key；跨类别授权使用多个 TargetSet，不能混成一个无类型 ID 列表。

### 6.5 `pa_data_permission_target`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | |
| `target_set_id` | BIGINT UNSIGNED | NOT NULL | 同 Tenant TargetSet |
| `target_id` | VARCHAR(128) | NOT NULL | 规范化十进制/ULID/UUID 字符串 |
| `status` | VARCHAR(16) | `active` | `active/removed` |
| `added_by_member_id` | BIGINT UNSIGNED | NOT NULL | |
| `removed_by_member_id` | BIGINT UNSIGNED | NULL | |
| `added_at` | DATETIME(3) | NOT NULL | |
| `removed_at` | DATETIME(3) | NULL | |

约束和索引：

- `UNIQUE uk_data_target (tenant_id, target_set_id, target_id)`。
- `INDEX idx_data_target_active (tenant_id, target_set_id, status, target_id)`。
- 复合 FK `(tenant_id, target_set_id) -> pa_data_permission_target_set(tenant_id, id)`。
- `target_id` 是通用字符串；写入 Service 根据 TargetSet.target_resource_key 调用唯一 TargetResolver，验证真实存在、当前 Tenant 可引用且当前管理员有权选择，不建立伪多态外键。
- Removed target 再次添加时复用原 row并恢复 active，不重复插入。

## 7. 条件组合算法

### 7.1 单个 Policy

```text
PolicyResult = Group1 OR Group2 OR ...
GroupResult  = Condition1 AND Condition2 AND ...
```

空 Policy、没有 active Group、或 Group 没有 active Condition 都得到 `DENY`，不能把空条件解释为全部。

`core.tenant_all` 必须单独成组。它与任何条件 AND 都没有必要，UI 和 compiler 应拒绝这种配置。

### 7.2 多角色

```text
DataResult = RoleA.PolicyResult OR RoleB.PolicyResult OR ...
FinalQuery = tenant_id = TenantContext.tenant_id AND DataResult
```

多角色扩大允许范围，这是成熟 RBAC 容易理解的语义。P0 不加入显式 deny，避免角色组合出现无法向业务人员解释的优先级。

### 7.3 与功能权限、Module 的关系

即使 DataResult 允许，以下任一失败仍拒绝：

- 没有 ResourceOperation 所需 Permission。
- Module 未安装或 TenantModule 不有效。
- Permission/Resource/Condition definition 已 retired。
- Provider 或 TargetResolver 缺失。
- 目标无法证明属于当前 Tenant。

数据规则只收窄数据，不授予功能权限。

## 8. ResourceAccessProvider

底座提供授权编排、合并、缓存、默认拒绝和审计；Module Provider 负责把抽象范围翻译成真实业务关系。

建议冻结四个窄接口，而不是一个万能 Provider：

```php
interface ResourceQueryPolicyProvider
{
    public function compilePredicate(
        AuthorizationContext $context,
        ResourceOperation $operation,
        EffectiveConditionGroups $groups
    ): QueryConstraint;
}

interface ResourceTargetPolicyProvider
{
    public function assertTargetsAllowed(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets,
        EffectiveConditionGroups $groups
    ): AuthorizationDecision;
}

interface ResourceCreatePolicyProvider
{
    public function assertCreateAllowed(
        AuthorizationContext $context,
        ResourceOperation $operation,
        CreateTargetDescriptor $targets,
        EffectiveConditionGroups $groups
    ): AuthorizationDecision;
}

interface ResourceTargetResolver
{
    public function resolveAndValidate(
        TenantContext $context,
        TypedResourceTargetSet $targets
    ): ResolvedResourceTargets;
}

interface ResourceTargetCatalogProvider
{
    public function searchAllowedTargets(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TargetCatalogQuery $query
    ): TargetOptionPage;
}

interface SharedMasterScopeProvider
{
    public function compileVisiblePredicate(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets
    ): QueryConstraint;

    public function assertUsageAllowed(
        AuthorizationContext $context,
        ResourceOperation $operation,
        ResourceRecordRef $resource,
        TypedResourceTargetCollection $targets
    ): AuthorizationDecision;
}
```

类名可在实现计划中微调，但六类责任不得合并丢失：列表谓词、已知目标检查、创建目标检查、目标归属解析、目标候选查询、共享主档可见/使用范围。普通 tenant-owned 资源不需要实现 SharedMasterScopeProvider；`ownership=shared_master` 时缺失它必须 fail closed。

`TypedResourceTargetSet` 固定包含一个 `target_resource_key` 和该类别的规范化 `target_ids`；`TypedResourceTargetCollection` 可以包含多个不同类别的 set。授权服务在调用 Provider 前先按 operation.target_cardinality 和允许的 target type registry 校验结构。

### 8.1 QueryConstraint

Provider 返回结构化约束，不返回字符串 SQL：

```text
TenantEquals
ColumnEquals
ColumnIn
AndConstraint
OrConstraint
ExistsByContract
AlwaysFalse
```

Repository adapter 把约束应用到 ThinkPHP Query Builder。`RawSqlConstraint` 不属于 public API。

### 8.2 标准 Provider 与自定义 Provider

底座可以提供标准 Provider：

- Tenant-owned 标准表。
- 有稳定 `created_by_member_id` 的本人数据。
- 有稳定 `department_id` 的部门数据。
- 明确业务对象外键的 selected-object 数据。

列映射只能写在版本控制的 Module manifest 或 PHP provider 配置中，不能由租户管理员填写。

复杂关系如“库存记录属于库存地点，库存地点关联门店或仓库”由 Inventory Module Provider 处理。若需要 Store/Warehouse 的目标信息，应调用对方公开 TargetResolver/Query contract，禁止直接 JOIN 对方内部表。

### 8.3 Provider 强制行为

- 每次先强制当前 `tenant_id`。
- 只接受 manifest 声明支持的 operation 和 condition。
- 对列表、count、aggregate、dashboard 生成同等谓词。
- 对详情、更新、删除、审批验证所有目标，不只验证第一个。
- 先校验 target cardinality，再解析目标；`one_required` 不得接受 0 个或多个 primary target。
- 每个 typed TargetSet 的类别必须在 operation registry 中，且不得把同一个 ID 按另一类别解释。
- `shared_master` 资源先应用成员数据权限，再应用 Module 的 ownership/visibility/usage scope；两者取交集。
- TargetCatalogProvider 只返回当前 operation 下允许选择的目标，分页、搜索且默认最小摘要；不能先列全 Tenant 目标再由前端过滤。
- 不存在与无权限默认返回相同 404，防止 ID 枚举；审计内部保留真实原因。
- 不产生业务写副作用。
- 返回命中的 Role、Policy、Group、Condition 和 target 摘要供审计解释。
- 无法解析归属、条件配置非法或 Provider 异常时 fail closed。

### 8.4 查询性能边界

- 所有 tenant-owned 查询先命中包含 `tenant_id` 的索引，再应用数据谓词。
- 指定目标很少时可使用参数化 `IN`；目标超过 500 个时 Provider 必须改用受控关系表 `EXISTS`、临时 ID 表或 Module 读模型，不能生成无限长 SQL。
- Department tree 使用 MySQL 8 recursive CTE 或按 `tenant.authorization_revision` 缓存的后代 ID 集；最大深度仍为 10。
- Provider 必须为 list/count/aggregate 提供可检查的 Query Builder 结果，并在测试中执行 `EXPLAIN`，禁止先读取全租户数据再用 PHP 过滤。
- Resource target、Permission 或 Policy 全部不得写入 Session token；范围扩大不会造成 token 体积和请求 header 膨胀。

## 9. 各操作固定执行顺序

### 9.1 所有操作共同前置

```text
1. G-02 Guard 建立可信 Context
2. G-04 检查 Module installed + TenantModule enabled
3. 解析 ProtectedResource + operation
4. 检查后端 Permission
5. 编译或读取有效 DataPermission
6. Provider 应用列表谓词或验证全部目标
7. Module 执行业务规则
8. 事务写入/返回结果
9. 按 audit_level 写审计
```

### 9.2 List/search/count/dashboard

- Repository 起始查询必须已经包含 Tenant predicate。
- Provider 追加 DataResult，不能在查询完成后用 PHP 过滤。
- count、aggregate 和 dashboard 与 list 使用同一 QueryConstraint。
- 用户筛选条件只能在授权谓词之上继续收窄，不能覆盖授权谓词。
- `many_readable` 请求可选择授权集合中的一个或多个同类目标；不传目标时使用完整授权集合，不表示全 Tenant。
- `aggregate_read` 必须返回目标数量、范围摘要和数据更新时间，且不能暴露调用者无权查看的目标名称。

### 9.3 Detail

- 使用 `id AND tenant predicate AND data predicate` 一次查询，或先由 TargetProvider 验证后读取。
- 禁止 `find(id)` 后只检查功能 Permission。
- 不存在和无权限对外统一 404；跨租户尝试额外写高优先级审计。

### 9.4 Create

- `tenant_id`、`created_by_member_id` 由 Context 写入，忽略客户端同名字段。
- 在记录尚不存在时，Provider 校验父资源、department、store、warehouse、location 等初始目标。
- 创建后不得允许客户端更改 `tenant_id`。
- operation 为 `one_required` 时必须在写入前解析一个 primary target；目标只存在但成员无权仍拒绝。

### 9.5 Update/delete/approve/cancel

- 先验证当前记录及所有关联目标。
- 再检查业务状态、乐观锁和可变字段。
- 更新中若要更换 department 或业务对象范围，旧目标和新目标都必须验证。

### 9.6 Batch

- 解析全部 ID，验证每个目标。
- 默认 all-or-nothing；任一无权则整批拒绝，不暴露具体越权 ID。
- 需要部分成功时必须定义独立 operation/Permission 和逐项结果契约，不能偷偷跳过。
- 普通 batch 只表示同一 primary target 内的多个资源 ID，不等于跨多个业务目标写入。
- 跨目标写必须声明 `bulk_write`；P0 默认拒绝。策略类需求优先使用 `policy_publish`，保存一份策略并记录每个目标的发布结果。

### 9.7 Import

- 上传、解析和字段校验不能产生业务写入。
- 每一行按 create/update 对应 Provider 检查。
- Import 使用独立 Permission；不能因为普通 create 权限就默认可批量导入。
- 失败报告必须脱敏且只包含当前成员有权查看的目标摘要。

### 9.8 Export

- Export 使用独立 Permission。
- 查询使用与 list 相同或更严格的 DataPermission。
- 必须限制行数、字段、文件有效期和下载者。
- 异步导出在 Worker 执行时按 G-02 重新验证成员、Permission 和 DataPermission。

### 9.9 Queue/schedule/system job

- 用户任务按当前成员重新授权，不能复用入队时的 allow 结论。
- TenantSystem actor 只能使用 manifest 注册的 system operation 和固定范围。
- 不存在通用 `withoutDataPermission()`。

## 10. 数据权限管理页面所需能力

租户管理员配置 Role 时，后台必须能显示：

- Resource 和 operation 的中文名。
- 当前 operation 所需功能 Permission。
- 可配置的六类基础条件和 Module 自定义条件。
- 指定部门树选择器。
- Module 提供的指定业务对象选择器。
- 每个组是 AND、组间和多角色是 OR 的自然语言预览。
- 最终权限来源：来自哪些 Role、Policy 和目标集合。
- Operation 的 target cardinality、允许的目标类别，以及零/单/多目标时的自然语言效果。
- shared_master 的归属、可见和使用范围由哪个 Module Provider 决定。
- Module 未开通、definition retired 或 Provider 缺失的诊断。

目标选择器本身也必须受 Permission 和 DataPermission 保护，不能为了配置权限先列出全租户敏感业务对象。

## 11. 缓存和失效

### 11.1 修订号

为避免每次请求重复装载完整关系，同时保证权限变化立即生效：

- `pa_tenant.authorization_revision`：Tenant 内 Role、RolePermission、DataPolicy、Department tree、TenantModule 等任一授权结构变化后递增。
- `pa_tenant_member.authorization_revision`：成员角色、主部门等成员专属关系变化后递增。
- `pa_role.authorization_revision`：该 Role 的 Permission 或 DataPolicy 变化后递增。
- `pa_tenant_module.authorization_revision`：模块状态、有效期或授权配置变化后递增。
- 部署级 registry 使用 `manifest_digest` 聚合为 `registry_revision`。

这些 revision 只让授权缓存失效，不撤销 G-02 Session。Account/Tenant/Member 状态和凭证安全变化才使用 `security_revision` 撤销会话。

### 11.2 缓存键

```text
pa:authz:v1:
tenant:{tenant_id}:{tenant_authz_revision}:
member:{member_id}:{member_authz_revision}:
registry:{registry_revision}:
module:{module_key}:{tenant_module_authz_revision}:
resource:{resource_key}:
operation:{operation}:
cardinality:{target_cardinality}:
target-registry:{target_registry_revision}
```

缓存值包含：

- 有效 Permission keys。
- 有效 Policy/Group/Condition 的编译结果。
- 来源 Role IDs 和 role revisions。
- 编译时间，以及所有 `valid_from/valid_until/TenantModule expires_at` 中最近一次未来状态转换时间。

### 11.3 失效方式

1. 授权写操作在数据库事务中更新关系和 revision。
2. 事务提交后发布 cache invalidation 事件并主动删除当前键。
3. 即使删除事件失败，新 revision 也使旧键不可达。
4. Cache miss 回源数据库；缓存故障不得回退为允许。
5. 有未来生效/到期时间时，缓存 TTL 不得超过最近一次状态转换；TenantModule Guard 每次仍检查当前有效期。
6. 其余 TTL 最长 5 分钟只负责清理孤儿键，不承担正确性。

## 12. 错误与审计原因

| 错误码 | 对外 HTTP | 含义 |
| --- | --- | --- |
| `AUTHZ_PERMISSION_DENIED` | 403 | 功能权限不足 |
| `AUTHZ_DATA_DENIED` | 404/403 | 单对象默认 404，非枚举操作可 403 |
| `AUTHZ_RESOURCE_UNDECLARED` | 500 | 开发配置缺失，生产默认拒绝 |
| `AUTHZ_OPERATION_UNDECLARED` | 500 | 未登记 operation |
| `AUTHZ_PROVIDER_MISSING` | 500 | Provider 未注册 |
| `AUTHZ_CONDITION_UNSUPPORTED` | 422/500 | 管理配置非法或制品不兼容 |
| `AUTHZ_TARGET_TENANT_MISMATCH` | 404 | 目标不属于当前 Tenant，高优先级审计 |
| `AUTHZ_TARGET_TYPE_MISMATCH` | 422/404 | 目标类别未声明或 ID 被当成错误类别解释 |
| `AUTHZ_TARGET_CARDINALITY_INVALID` | 422 | 目标数量不符合 operation 声明 |
| `AUTHZ_SHARED_SCOPE_DENIED` | 404/403 | 共享主档对当前目标不可见或不可使用 |
| `AUTHZ_MODULE_UNAVAILABLE` | 403 | Module 未安装/未开通/已过期 |
| `AUTHZ_SYSTEM_ACTOR_DENIED` | 403 | 系统任务未声明能力 |

审计内部必须区分“记录不存在”和“存在但越权”，但对外不得用差异响应帮助枚举。

## 13. G-03 必测场景

1. 菜单可见但 API Permission 被移除时，后端仍拒绝。
2. 手工调用隐藏菜单 API 不能绕过 Permission。
3. 两个 Role 的 Permission 取并集。
4. Disabled/archived Role 不参与计算。
5. Retired Permission 不参与计算。
6. 租户管理员角色也没有 `is_super` 绕过。
7. `tenant_all` 只能看到当前 Tenant。
8. 无 Policy 的 `rule_filtered` operation 默认拒绝。
9. `tenant_wide` operation 仍强制 tenant predicate。
10. `self` 由 Provider 的真实负责人关系计算，不机械使用任意 `created_by`。
11. 无主部门成员使用 `own_department` 得到空范围。
12. `department_tree` 只包含当前部门和后代，不包含父级或兄弟部门。
13. `specified_departments` 不自动包含下级。
14. 指定另一个 Tenant 的 Department target 写入失败。
15. 指定另一个 Tenant 的 Store/Warehouse target 写入失败。
16. 同组两个条件按 AND，任一不满足即拒绝。
17. 两个组按 OR，任一满足即可。
18. 多角色数据范围按 OR 合并。
19. 空 Group、空 Policy 和无 Provider 均默认拒绝。
20. 租户管理员不能写 SQL、列名或脚本条件。
21. List 只返回授权范围内记录。
22. Count、aggregate、dashboard 与 List 数量范围一致。
23. Detail 使用裸 ID 访问范围外记录返回 404。
24. Update/delete 不能只靠 List 页面隐藏。
25. Create 不能伪造 tenant_id。
26. Create 的父部门/门店/仓库目标必须在授权范围内。
27. Update 更换业务对象时旧目标和新目标均校验。
28. Batch 中一个越权 ID 导致默认整批拒绝。
29. Import 每行重新授权，不能通过批量入口绕过。
30. Export 有独立 Permission，并复用数据谓词。
31. 异步 Export 执行前权限被撤销时拒绝生成。
32. Queue 消息伪造 target ID 时 Provider 拒绝。
33. TenantModule 停用后旧 RolePermission 关系保留但不生效。
34. Role Permission 改动后 authorization revision 变化，旧缓存不可达。
35. Department tree 改动后相关授权缓存失效。
36. Cache 服务不可用时回源或拒绝，不能放行全部。
37. Provider 返回未知 Constraint 类型时拒绝并记录开发错误。
38. Inventory Provider 需要 Store 目标时只调用公开 Resolver，不直接 JOIN Store 内部表。
39. PlatformRole 权限不能进入 TenantMember 有效权限集合。
40. Tenant Role/DataPolicy 不能进入 PlatformOperator 权限集合。
41. 一个成员可以对 Project A/B 有 read，对 Project A 有 update；列表返回 A/B，更新 B 被拒绝。
42. 同一个 TargetSet 混入 Project 和 Queue 类别时写入失败。
43. Operation 未声明目标类别、target cardinality 或对应 Resolver 时默认拒绝。
44. `one_required` 对 0 个或 2 个 primary target 均返回 `AUTHZ_TARGET_CARDINALITY_INVALID`。
45. `many_readable` 的目标筛选只能从授权集合中取子集，不能靠参数加入未授权目标。
46. `aggregate_read` 只返回授权目标汇总，且没有写入口。
47. 普通 batch 不能借 ID 数组跨多个 primary target 修改；`bulk_write` 在 P0 默认拒绝。
48. `policy_publish` 保存一份策略并逐目标记录结果，不直接循环改写各目标真相表。
49. shared_master 同一记录可被多个目标引用，但 owner、maintainer、visible、usable 范围分别校验。
50. shared_master Provider 缺失或作用范围无法解析时默认拒绝，不回退为 global_reference。
51. `tenant_id NULL/0` 不能让 tenant-owned 记录变成共享记录。
52. 同一 Account 在两个 Tenant 的目标授权、缓存和 TargetSet 完全隔离。
53. 没有 policy_selection_permission 的数据权限管理员不能枚举或写入 Module 业务 TargetSet。

## 14. 明确延后

- 显式 deny 和 deny 优先级。
- 角色继承。
- 成员级临时 DataPermission。
- 跨租户 Delegation 和官方代运营范围上限。
- ABAC 通用表达式和策略脚本。
- 行级安全由数据库原生 RLS 承担的部署方案。
- 可视化任意条件构造器。

这些能力未来若进入，也必须保留 Tenant 硬边界、Provider 类型化、列表/详情/写入一致和默认拒绝。

## 15. G-03 结论

Peanut Admin 的数据权限不等于“给查询加一个 department_id 条件”，也不等于“每个 Module 都必须固定一个门店上下文”。

运营类页面可以通过显式搜索条件在授权范围内查看多个门店；门店类 Client 可以默认携带当前门店候选，但 Provider 仍验证目标类别、目标归属、operation 基数和成员权限。两者使用同一 ResourceOperation 和 DataPermission，不需要复制库存或商品 Module。

共享商品一类业务主档不使用两张资源表或两个 ID 空间。Module 在一个 shared_master 真相源上通过 ownership 和 scope Provider 表达创建者、归属者、维护者、可见范围和可使用范围；Peanut Admin 只提供契约，不实现商品业务。

下一步 G-04 必须定义 Module manifest 如何发布 Permission、ProtectedResource、condition、菜单、迁移和 Provider，以及部署安装、租户开通、成员授权三层如何共同生效。
