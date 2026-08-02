# G-01 Kernel 字段级数据模型

> 状态：Recalibrated and Reviewed（2026-07-15），通过 48 号复审，等待新编码批准
>
> 范围：Peanut Admin P0 的身份、租户、成员、组织、功能权限、模块开通和审计基础表
>
> 本文不是 migration，也不授权编写运行时代码。

## 1. 先用业务语言说明

这一版只保留一条容易解释的主链路：

```text
登录凭证 Credential
  -> 找到全局账号 Account
  -> 选择租户 Tenant
  -> 找到该账号在租户内的成员 TenantMember
  -> 读取成员角色、功能权限和数据权限
  -> 使用该租户已开通的 Module
```

其中：

- Account 只表示“这是哪个登录账号”，不保存部门、租户角色或当前租户。
- Tenant 是经营组织和 SaaS 数据隔离根，不是门店、仓库、供应商或部门。
- 一个 Tenant 可以同时拥有、管理或按业务关系使用多个类别的业务对象，每个类别可以有多个实例；例如多个门店、仓库、供应商和批发商。
- TenantMember 表示“这个账号在这个租户里是谁”，不因成员管理多个门店或仓库而复制多条成员记录。
- Department 只组织租户内人员，并为数据权限提供计算输入；部门上下级不自动继承功能权限。
- PlatformOperator 是 Peanut Admin 平台管理方的独立操作身份，不是任何客户租户里的超级成员。
- Store、Warehouse、Supplier、Product、Inventory 等不是 Kernel 表，由以后各 Module 自己定义；成员具体可管理哪些目标由 G-03 按资源和操作计算。

## 2. 成熟方案参考与本次收窄

本设计保留 LikeAdmin、MineAdmin 等成熟后台常见的账号、部门、角色、权限、状态和日志结构，也保留 ABP 等多租户框架验证过的“Tenant 是隔离根”原则。

与旧 base-framework V4 相比，本次主动收窄：

| 旧设计 | 本次处理 | 原因 |
| --- | --- | --- |
| global/tenant/entry/system 多种 Credential realm | P0 只做全局邮箱密码凭证 | 当前没有租户用户名、设备证书和机器凭证需求 |
| 用 `tenant_id = 0` 表示平台数据 | 禁止 | 平台表和租户表物理分开，避免守卫漏判 |
| 每个请求携带 Application/Entry | 删除 | Client/ProductProfile 不是 P0 授权根 |
| Position、多部门、邀请、租户域名全部进入 P0 | 延后到 P1 | P0 只保留安全登录和管理后台最短闭环 |
| 通用软删除所有核心表 | 改用显式状态和终止时间 | 安全对象需要保留历史，不能通过删除掩盖生命周期 |

## 3. 数据库统一约定

| 项目 | 固定规则 |
| --- | --- |
| 数据库 | MySQL 8.0，`utf8mb4`，默认排序规则在工程初始化时固定 |
| 表前缀 | `pa_`，不得继续使用旧 `bf_` |
| 主键 | `id BIGINT UNSIGNED AUTO_INCREMENT` |
| API ID | JSON 中统一输出十进制字符串，避免 JavaScript 大整数精度丢失 |
| 时间 | `DATETIME(3)`，应用以 UTC 写入，API 按 ISO 8601 输出 |
| 状态 | `VARCHAR(32)`；PHP enum 和数据库 `CHECK` 使用同一固定值集合 |
| 布尔 | `TINYINT UNSIGNED NOT NULL`，只允许 `0/1` |
| JSON | 只保存通过 schema 校验的配置或脱敏 metadata，不保存权限表达式或可执行代码 |
| 修订号 | `revision BIGINT UNSIGNED NOT NULL DEFAULT 1`，更新时使用乐观锁并原子递增 |
| 安全修订号 | `security_revision BIGINT UNSIGNED NOT NULL DEFAULT 1`，需要撤销会话的安全变化必须递增 |
| 授权修订号 | `authorization_revision BIGINT UNSIGNED NOT NULL DEFAULT 1`，权限关系变化只使授权缓存失效 |
| 删除 | 核心根对象不提供物理删除；用 `closed/left/revoked/archived` 等显式状态。当前授权关系行可在写审计后删除 |
| 外键 | 核心表建立真实外键；根对象不使用级联删除 |
| 租户约束 | 所有租户表 `tenant_id NOT NULL`；租户内关联优先使用 `(tenant_id, id)` 复合外键 |

禁止事项：

- 禁止用 `tenant_id = 0`、空字符串或 `NULL` 表示平台管理方。
- 禁止客户端提交并决定 `tenant_id`、`tenant_member_id`、角色或权限。
- 禁止在 Account 上保存 `current_tenant_id` 作为授权事实。
- 禁止把 `deleted_at` 当成核心身份和授权记录的主要生命周期。
- 禁止给 Store、Warehouse 等业务对象预建通用万能表。

## 4. 表所属平面

| 平面 | 表 | 是否有 `tenant_id` | 说明 |
| --- | --- | --- | --- |
| 全局身份 | `pa_account`、`pa_credential` | 否 | 一个账号可以加入多个租户 |
| 平台控制 | `pa_tenant`、`pa_platform_operator`、`pa_platform_role` 及关系表 | 否 | 管理 SaaS 客户和平台自身权限 |
| 部署目录 | `pa_permission` | 否 | Kernel/Module manifest 编译出的权限目录 |
| 租户管理 | `pa_tenant_member`、`pa_department`、`pa_role` 及关系表、`pa_tenant_module` | 是，且非空 | 只能在可信 TenantContext 下访问 |
| 审计 | `pa_platform_audit_event`、`pa_tenant_audit_event` | 分表 | 平台事件无租户字段；租户事件强制租户字段 |

平台表和租户表可以位于同一个数据库，但必须经过不同 Repository 和 Guard。物理同库不等于授权模型相同。

## 5. 全局身份表

### 5.1 `pa_account`

全局账号。租户管理员只能管理对应 TenantMember，不能停用整个 Account。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | 内部主键 |
| `display_name` | VARCHAR(120) | NOT NULL | 全局默认显示名 |
| `avatar_uri` | VARCHAR(512) | NULL | 受控文件 URI，不接受 HTML |
| `status` | VARCHAR(32) | `active` | `active/locked/disabled/closed` |
| `security_revision` | BIGINT UNSIGNED | `1` | 凭证、锁定、停用或会话全量撤销时递增 |
| `locked_until` | DATETIME(3) | NULL | 临时锁定截止时间 |
| `last_login_at` | DATETIME(3) | NULL | 最近成功登录时间 |
| `closed_at` | DATETIME(3) | NULL | `closed` 时必填 |
| `created_at` | DATETIME(3) | NOT NULL | UTC |
| `updated_at` | DATETIME(3) | NOT NULL | UTC |

约束和索引：

- `CHECK status IN ('active','locked','disabled','closed')`。
- `CHECK ((status = 'closed' AND closed_at IS NOT NULL) OR status <> 'closed')`。
- `INDEX idx_account_status (status, id)`。
- 不包含 `tenant_id`、department、role、permission 或业务对象范围。

状态机：

```text
active -> locked -> active
active | locked -> disabled -> active
active | locked | disabled -> closed
closed -> 终态，不允许恢复
```

`locked` 是安全风控状态；`disabled` 是人工管理状态。锁定时间到期只解除 `locked`，不能解除 `disabled`。

### 5.2 `pa_credential`

登录凭证绑定。P0 只实现 `email_password`，但字段允许 P1 增加手机号或第三方登录，不需要另建账号表。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `account_id` | BIGINT UNSIGNED | NOT NULL | FK `pa_account.id` |
| `kind` | VARCHAR(32) | NOT NULL | P0 固定 `email_password` |
| `identifier_type` | VARCHAR(32) | NOT NULL | P0 固定 `email` |
| `identifier_normalized` | VARCHAR(255) | NOT NULL | 小写、去除首尾空白；不擅自移除邮箱 `+tag` |
| `secret_hash` | VARCHAR(255) | NOT NULL | PHP `password_hash` 生成的 Argon2id 哈希 |
| `status` | VARCHAR(32) | `active` | `active/locked/revoked` |
| `failed_attempts` | INT UNSIGNED | `0` | 连续失败次数 |
| `locked_until` | DATETIME(3) | NULL | 凭证级锁定截止 |
| `verified_at` | DATETIME(3) | NOT NULL | P0 邮箱凭证必须已验证或由受信管理员明确创建 |
| `last_used_at` | DATETIME(3) | NULL | 最近成功使用时间 |
| `secret_changed_at` | DATETIME(3) | NOT NULL | 密码最近变更时间 |
| `expires_at` | DATETIME(3) | NULL | NULL 表示不按日期过期 |
| `revision` | BIGINT UNSIGNED | `1` | secret/status 变化递增 |
| `revoked_at` | DATETIME(3) | NULL | `revoked` 时必填 |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_credential_identifier (identifier_type, identifier_normalized)`。
- `INDEX idx_credential_account (account_id, status)`。
- FK `account_id` 使用 `ON DELETE RESTRICT`。
- `CHECK status IN ('active','locked','revoked')`。
- `CHECK ((status = 'revoked' AND revoked_at IS NOT NULL) OR status <> 'revoked')`。
- `secret_hash`、失败次数和锁定时间禁止进入普通 API、审计 diff 和应用日志。

P0 不建立 `realm_type`、`realm_tenant_id`，也不使用 `0` 作为全局 realm。将来如有“租户内员工号登录”的真实需求，必须单独设计兼容唯一性和租户解析的凭证方案。

状态机：

```text
active -> locked -> active
active | locked -> revoked
revoked -> 终态；重新绑定应创建新凭证并审计
```

## 6. 平台控制表

### 6.1 `pa_tenant`

唯一 SaaS 客户和数据隔离根。门店、仓库、部门都不是 Tenant 的同义词。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `code` | VARCHAR(64) | NOT NULL | 小写字母、数字、短横线；创建后不可修改 |
| `name` | VARCHAR(160) | NOT NULL | 管理或签约名称 |
| `display_name` | VARCHAR(160) | NOT NULL | 用户界面名称 |
| `status` | VARCHAR(32) | `provisioning` | `provisioning/active/suspended/closed` |
| `locale` | VARCHAR(16) | `zh-CN` | BCP 47 风格 |
| `timezone` | VARCHAR(64) | `Asia/Shanghai` | IANA timezone |
| `security_revision` | BIGINT UNSIGNED | `1` | 租户停用或全局安全策略变化时递增 |
| `authorization_revision` | BIGINT UNSIGNED | `1` | 角色、数据权限、部门树和 TenantModule 等授权结构变化时递增 |
| `revision` | BIGINT UNSIGNED | `1` | 普通信息乐观锁 |
| `activated_at` | DATETIME(3) | NULL | `active` 后填写 |
| `suspended_at` | DATETIME(3) | NULL | `suspended` 时填写 |
| `closed_at` | DATETIME(3) | NULL | `closed` 时必填 |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_tenant_code (code)`。
- `INDEX idx_tenant_status (status, id)`。
- `CHECK status IN ('provisioning','active','suspended','closed')`。
- `code` 只用于稳定定位，不等于数据库名、域名或许可证编号。

状态机：

```text
provisioning -> active
provisioning -> closed
active -> suspended -> active
active | suspended -> closed
closed -> 终态
```

Tenant 暂停后，所有该租户的交互会话和业务写入立即拒绝。Tenant 关闭不是级联删除，数据保留、导出和销毁属于运维策略。

### 6.2 `pa_platform_operator`

平台操作员是平台控制面的授权主体。它可以和某个 Account 关联，但不能因此自动成为任何 TenantMember。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `account_id` | BIGINT UNSIGNED | NOT NULL | FK `pa_account.id` |
| `display_name` | VARCHAR(120) | NULL | 平台内称呼；NULL 时使用 Account |
| `status` | VARCHAR(32) | `active` | `active/suspended/closed` |
| `security_revision` | BIGINT UNSIGNED | `1` | 角色或状态变化递增 |
| `suspended_at` | DATETIME(3) | NULL | |
| `closed_at` | DATETIME(3) | NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_platform_operator_account (account_id)`。
- `INDEX idx_platform_operator_status (status, id)`。
- 平台会话只接受状态有效的 Account 和 PlatformOperator。
- 平台操作员访问租户业务数据必须使用 P1 显式支持会话；P0 不提供万能跨租户入口。
- Service 必须锁定并拒绝 suspend/close 或撤销最后一个具备平台操作员/角色管理能力的 active PlatformOperator，避免把控制面永久锁死。

### 6.3 `pa_platform_role`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `key` | VARCHAR(96) | NOT NULL | 稳定小写点号 key，如 `platform.tenant-admin` |
| `name` | VARCHAR(120) | NOT NULL | |
| `description` | VARCHAR(500) | NULL | |
| `is_builtin` | TINYINT UNSIGNED | `0` | 内置角色不可删除或改 key |
| `status` | VARCHAR(32) | `active` | `active/disabled/archived` |
| `revision` | BIGINT UNSIGNED | `1` | |
| `archived_at` | DATETIME(3) | NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束：`UNIQUE uk_platform_role_key (key)`，`INDEX idx_platform_role_status (status, id)`。

### 6.4 `pa_platform_operator_role`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `platform_operator_id` | BIGINT UNSIGNED | NOT NULL | FK PlatformOperator |
| `platform_role_id` | BIGINT UNSIGNED | NOT NULL | FK PlatformRole |
| `assigned_by_operator_id` | BIGINT UNSIGNED | NULL | Bootstrap 可为 NULL，其余必须填写 |
| `assigned_at` | DATETIME(3) | NOT NULL | |

约束：

- `UNIQUE uk_platform_operator_role (platform_operator_id, platform_role_id)`。
- 三个 operator FK 均 `ON DELETE RESTRICT`。
- 分配和撤销都必须递增 PlatformOperator `security_revision` 并写平台审计。

## 7. 部署级权限目录

### 7.1 `pa_permission`

权限由 Kernel/Module manifest 注册，租户管理员只能把已注册权限分给角色，不能在数据库里发明 API 权限。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `key` | VARCHAR(160) | NOT NULL | 全局稳定，如 `core.member.read` |
| `module_key` | VARCHAR(96) | NOT NULL | `core` 或 Module key |
| `type` | VARCHAR(32) | NOT NULL | `menu/action/api` |
| `name` | VARCHAR(160) | NOT NULL | |
| `description` | VARCHAR(500) | NULL | |
| `risk_level` | VARCHAR(16) | `normal` | `normal/sensitive/critical` |
| `status` | VARCHAR(32) | `active` | `active/retired` |
| `manifest_version` | VARCHAR(32) | NOT NULL | 最近同步来源版本 |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |
| `retired_at` | DATETIME(3) | NULL | |

约束和索引：

- `UNIQUE uk_permission_key (key)`。
- `INDEX idx_permission_module (module_key, status, type)`。
- `type` 只描述权限用途；最终 API 授权必须由后端 route/handler 显式绑定权限 key。
- Module 卸载或移除权限时先标记 `retired`，不得复用旧 key 表示新含义。

### 7.2 `pa_platform_role_permission`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `platform_role_id` | BIGINT UNSIGNED | NOT NULL | FK PlatformRole |
| `permission_id` | BIGINT UNSIGNED | NOT NULL | FK Permission |
| `granted_at` | DATETIME(3) | NOT NULL | |

约束：`UNIQUE uk_platform_role_permission (platform_role_id, permission_id)`。

平台角色只能获得 `platform.*` 或 manifest 明确标记为平台控制面的权限。不能把租户业务 API 权限直接塞给平台角色。

## 8. 租户管理表

### 8.1 `pa_department`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | FK `pa_tenant.id` |
| `parent_id` | BIGINT UNSIGNED | NULL | 根节点为 NULL；父部门必须同 Tenant |
| `code` | VARCHAR(64) | NOT NULL | 租户内稳定编号 |
| `name` | VARCHAR(120) | NOT NULL | |
| `sort_order` | INT | `0` | |
| `status` | VARCHAR(32) | `active` | `active/disabled/archived` |
| `revision` | BIGINT UNSIGNED | `1` | 树结构、名称或状态变化递增 |
| `archived_at` | DATETIME(3) | NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_department_tenant_id (tenant_id, id)`，供复合外键引用。
- `UNIQUE uk_department_code (tenant_id, code)`。
- `INDEX idx_department_parent (tenant_id, parent_id, status, sort_order)`。
- 复合自外键 `(tenant_id, parent_id) -> pa_department(tenant_id, id)`。
- Service 在事务中拒绝自己作为父级、循环和深度超过 10 层。
- 禁用部门不自动禁用成员；归档前必须处理子部门和在职成员。

部门树只提供“部门及下级”的计算数据。部门关系不得自动产生角色、菜单、操作或数据权限。

Kernel 不要求每个 Tenant 永久存在一个根 Department。ProductProfile 或租户初始化流程可以按配置幂等创建默认根部门；未创建根部门时成员仍可存在，依赖部门范围的数据权限得到空集合而不是回退为全部。

### 8.2 `pa_tenant_member`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | FK Tenant |
| `account_id` | BIGINT UNSIGNED | NOT NULL | FK Account |
| `member_no` | VARCHAR(64) | NULL | 租户内员工/成员编号 |
| `display_name` | VARCHAR(120) | NULL | 租户内称呼；NULL 时使用 Account |
| `member_type` | VARCHAR(32) | `internal` | `internal/external` |
| `primary_department_id` | BIGINT UNSIGNED | NULL | P0 只实现一个主部门，必须同 Tenant |
| `status` | VARCHAR(32) | `pending` | `pending/active/suspended/left` |
| `security_revision` | BIGINT UNSIGNED | `1` | 成员状态等需要撤销会话的安全变化递增 |
| `authorization_revision` | BIGINT UNSIGNED | `1` | 角色、主部门和数据权限等授权变化时递增 |
| `joined_at` | DATETIME(3) | NULL | 激活时填写 |
| `suspended_at` | DATETIME(3) | NULL | |
| `left_at` | DATETIME(3) | NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_tenant_member_tenant_id (tenant_id, id)`。
- `UNIQUE uk_tenant_member_account (tenant_id, account_id)`。
- `UNIQUE uk_tenant_member_no (tenant_id, member_no)`；MySQL 允许多个 NULL。
- `INDEX idx_member_department (tenant_id, primary_department_id, status)`。
- 复合 FK `(tenant_id, primary_department_id) -> pa_department(tenant_id, id)`。
- 成员离职后不删除；重新加入时复用原 row，经显式流程恢复并递增 `security_revision`。

状态机：

```text
pending -> active
pending -> left
active -> suspended -> active
active | suspended -> left
left -> pending -> active    # 重新加入，必须重新确认角色和数据范围
```

管理员直接添加成员也必须分成两步：先建立 `pending` 候选，再执行确认激活；不能在“发出邀请”这个动作里静默成为有效成员。邀请 token、手机号和多部门关系放到 P1，但不改变本状态机。

#### 首个租户负责人的建立

新 Tenant 处于 `provisioning` 时还没有可登录的租户管理员，因此必须由平台控制面完成一次受限的首个负责人建立，但这不等于平台操作员进入租户业务上下文。

固定流程：

1. 创建 Tenant，并在同一事务建立租户内置角色 `core.tenant-owner`。
2. 有 `platform.tenant.provision-owner` 权限的平台操作员按精确规范化邮箱建立 owner candidate。
3. 邮箱已对应 Account 时只能复用该 Account，禁止提交密码或覆盖 Credential。
4. 邮箱尚不存在时必须由预定负责人提供初始密码；平台端只接受一次并立即使用成熟 password hash，禁止生成可回看的默认密码、写日志或在响应回显。
5. 在锁定 Tenant row 的同一事务中确认不存在 active/pending owner，创建或复用唯一的 `pending` TenantMember，并预先分配 `core.tenant-owner`；pending 状态不产生任何访问权。
6. 第二个显式 activate 动作再次锁定 Tenant row，确认 Account、Credential、Member 和 owner role 均有效，再激活成员。
7. Tenant 至少有一个 active owner 后才允许从 `provisioning` 转为 `active`。

`core.tenant-owner` 是 `is_builtin=1` 的租户角色，固定获得 G-05 P0 Tenant core catalog 的全部 Permission，以及 core 资源的 `tenant_all` 数据策略。新 Module 的权限不会自动加入 owner 角色；TenantModule 开通后仍由 owner 显式分配，避免安装 Module 隐式扩大权限。

平台控制面不提供全局 Account 搜索目录。精确邮箱解析只服务于当前 owner candidate 命令，结果不得返回其他 TenantMember、角色或租户信息。一个 provisioning Tenant 同时最多有一个带 `core.tenant-owner` 的 pending candidate；Tenant row lock 和成员/角色唯一约束必须让并发创建只有一个成功。全部步骤写 PlatformAudit；TenantMember 激活同时写目标租户审计，但不创建 PlatformOperator 的 TenantSession。

### 8.3 `pa_role`

租户内功能权限和数据权限的组合容器。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | FK Tenant |
| `key` | VARCHAR(96) | NOT NULL | 租户内稳定 key |
| `name` | VARCHAR(120) | NOT NULL | |
| `description` | VARCHAR(500) | NULL | |
| `is_builtin` | TINYINT UNSIGNED | `0` | 内置角色不能删除或改 key |
| `status` | VARCHAR(32) | `active` | `active/disabled/archived` |
| `authorization_revision` | BIGINT UNSIGNED | `1` | 权限或数据规则变化递增 |
| `archived_at` | DATETIME(3) | NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_role_tenant_id (tenant_id, id)`。
- `UNIQUE uk_role_key (tenant_id, key)`。
- `INDEX idx_role_status (tenant_id, status, id)`。
- 不设置隐藏 `is_super` 免检字段。租户管理员也必须通过显式 Permission 和 DataRule 获权。

### 8.4 `pa_role_permission`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | |
| `role_id` | BIGINT UNSIGNED | NOT NULL | |
| `permission_id` | BIGINT UNSIGNED | NOT NULL | 全局权限目录 FK |
| `granted_by_member_id` | BIGINT UNSIGNED | NULL | Bootstrap 可为 NULL |
| `granted_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_role_permission (tenant_id, role_id, permission_id)`。
- 复合 FK `(tenant_id, role_id) -> pa_role(tenant_id, id)`。
- 复合 FK `(tenant_id, granted_by_member_id) -> pa_tenant_member(tenant_id, id)`。
- FK `permission_id -> pa_permission.id`。
- 授予 Module 权限前必须验证该 TenantModule 有效；模块停用时权限关系保留但不生效。

### 8.5 `pa_member_role`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | |
| `tenant_member_id` | BIGINT UNSIGNED | NOT NULL | |
| `role_id` | BIGINT UNSIGNED | NOT NULL | |
| `assigned_by_member_id` | BIGINT UNSIGNED | NULL | Bootstrap 可为 NULL |
| `assigned_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_member_role (tenant_id, tenant_member_id, role_id)`。
- 复合 FK `(tenant_id, tenant_member_id) -> pa_tenant_member(tenant_id, id)`。
- 复合 FK `(tenant_id, role_id) -> pa_role(tenant_id, id)`。
- 复合 FK `(tenant_id, assigned_by_member_id) -> pa_tenant_member(tenant_id, id)`。
- 分配或撤销后递增成员和租户的 `authorization_revision` 并写租户审计；不强制用户重新登录。

### 8.6 `pa_tenant_module`

表示某个部署中已经存在的 Module 是否向某租户开通。它不是 Module 安装记录。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | FK Tenant |
| `module_key` | VARCHAR(96) | NOT NULL | 必须存在于部署级 Module registry |
| `status` | VARCHAR(32) | `disabled` | `disabled/enabled/expired` |
| `source` | VARCHAR(32) | `manual` | `manual/product_profile/license` |
| `config_json` | JSON | NULL | 只允许通过 Module config schema 的租户配置 |
| `config_revision` | BIGINT UNSIGNED | `1` | 配置变化递增 |
| `authorization_revision` | BIGINT UNSIGNED | `1` | 开通状态、有效期或授权配置变化递增 |
| `effective_at` | DATETIME(3) | NULL | NULL 表示启用后立即生效 |
| `expires_at` | DATETIME(3) | NULL | NULL 表示不按日期过期 |
| `enabled_at` | DATETIME(3) | NULL | |
| `disabled_at` | DATETIME(3) | NULL | |
| `disabled_reason` | VARCHAR(255) | NULL | 稳定原因或安全文本 |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_tenant_module (tenant_id, module_key)`。
- `INDEX idx_tenant_module_status (tenant_id, status, module_key)`。
- `CHECK (expires_at IS NULL OR effective_at IS NULL OR expires_at > effective_at)`。
- 不对 `module_key` 建业务表 FK；G-04 的编译 registry 是事实源，写入时必须验证。
- 模块生效必须同时满足：部署已安装、TenantModule 当前有效、成员功能权限和数据权限均允许。

状态机：

```text
disabled -> enabled -> disabled
enabled -> expired
expired -> enabled    # 重新授权并设置有效期后
```

停用后拒绝新请求和新任务；正在执行的事务按 G-04 规定完成或回滚。停用不删除模块数据、角色权限关系或审计历史。

## 9. 审计表

平台和租户审计使用同一 PHP `AuditWriter` 接口，但写入两个物理表。两表均 append-only，不提供 update/delete Repository。

审计表中的操作方和目标 ID 是历史快照引用，不建立外键，也不级联更新。`AuditWriter` 在写入时校验当前操作上下文，并同时保存稳定 action、target 和脱敏授权摘要；这样账号生命周期或未来归档不会破坏既有审计证据。

### 9.1 `pa_platform_audit_event`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `event_type` | VARCHAR(96) | NOT NULL | 稳定事件类型 |
| `action` | VARCHAR(160) | NOT NULL | 权限或业务动作 key |
| `outcome` | VARCHAR(16) | NOT NULL | `success/denied/error` |
| `reason_code` | VARCHAR(96) | NULL | 稳定拒绝/错误码 |
| `operator_id` | BIGINT UNSIGNED | NULL | 系统任务可为 NULL |
| `account_id` | BIGINT UNSIGNED | NULL | 操作账号快照引用 |
| `target_type` | VARCHAR(96) | NULL | 如 `tenant`、`platform_role` |
| `target_id` | VARCHAR(128) | NULL | 字符串，兼容复合或外部 ID |
| `request_id` | VARCHAR(64) | NOT NULL | |
| `operation_id` | VARCHAR(64) | NULL | 跨步骤关联 |
| `ip_address` | VARCHAR(45) | NULL | |
| `user_agent_hash` | CHAR(64) | NULL | 不保存无限长原文 |
| `before_json` | JSON | NULL | 仅白名单非敏感字段 |
| `after_json` | JSON | NULL | 仅白名单非敏感字段 |
| `metadata_json` | JSON | NULL | schema 校验、脱敏 |
| `occurred_at` | DATETIME(3) | NOT NULL | |

索引：

- `INDEX idx_platform_audit_time (occurred_at, id)`。
- `INDEX idx_platform_audit_operator (operator_id, occurred_at)`。
- `INDEX idx_platform_audit_target (target_type, target_id, occurred_at)`。
- `INDEX idx_platform_audit_request (request_id)`。

### 9.2 `pa_tenant_audit_event`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | 审计分区和目标资源所属 Tenant；P0 也称 target tenant |
| `event_type` | VARCHAR(96) | NOT NULL | |
| `action` | VARCHAR(160) | NOT NULL | |
| `outcome` | VARCHAR(16) | NOT NULL | `success/denied/error` |
| `reason_code` | VARCHAR(96) | NULL | |
| `actor_tenant_id` | BIGINT UNSIGNED | NULL | 实际操作方 Tenant 快照；P0 member/tenant_system 必须等于 tenant_id |
| `actor_tenant_member_id` | BIGINT UNSIGNED | NULL | 实际操作成员快照；租户系统任务可为 NULL |
| `actor_account_id` | BIGINT UNSIGNED | NULL | 实际操作账号快照 |
| `actor_platform_operator_id` | BIGINT UNSIGNED | NULL | 仅平台治理镜像事件保存 PlatformOperator 快照 |
| `actor_type` | VARCHAR(32) | NOT NULL | P0：`member/tenant_system/platform_operator` |
| `target_resource_type` | VARCHAR(160) | NULL | 被操作资源 key，如 `example.work-item` |
| `target_resource_id` | VARCHAR(128) | NULL | 单资源 ID；批量操作可为空 |
| `boundary_target_type` | VARCHAR(160) | NULL | 主要业务边界目标类型，如 Module 声明的 project/store/warehouse key |
| `boundary_target_id` | VARCHAR(128) | NULL | 单目标操作填写；多目标操作可为空 |
| `target_count` | INT UNSIGNED | `0` | 本次已授权目标数量；不等于返回行数 |
| `target_set_digest` | CHAR(64) | NULL | 多目标规范化摘要，避免在审计表复制大量 ID |
| `authorization_basis_json` | JSON | NULL | 命中的 Permission/Role/Policy/condition 和未来 relation/grant ID 摘要，白名单且脱敏 |
| `request_id` | VARCHAR(64) | NOT NULL | |
| `operation_id` | VARCHAR(64) | NULL | |
| `ip_address` | VARCHAR(45) | NULL | |
| `user_agent_hash` | CHAR(64) | NULL | |
| `before_json` | JSON | NULL | 白名单、脱敏 |
| `after_json` | JSON | NULL | 白名单、脱敏 |
| `metadata_json` | JSON | NULL | schema 校验、脱敏 |
| `occurred_at` | DATETIME(3) | NOT NULL | |

索引和约束：

- `INDEX idx_tenant_audit_time (tenant_id, occurred_at, id)`。
- `INDEX idx_tenant_audit_member (tenant_id, actor_tenant_member_id, occurred_at)`。
- `INDEX idx_tenant_audit_actor_tenant (actor_tenant_id, occurred_at, id)`。
- `INDEX idx_tenant_audit_target (tenant_id, target_resource_type, target_resource_id, occurred_at)`。
- `INDEX idx_tenant_audit_boundary_target (tenant_id, boundary_target_type, boundary_target_id, occurred_at)`。
- `INDEX idx_tenant_audit_request (request_id)`。
- 普通租户管理员只能查询本租户允许范围内的审计，不得修改或删除。

P0 固定校验：

- `member`：actor_tenant_id 必须等于 tenant_id，member/account 必填，platform_operator 为空。
- `tenant_system`：actor_tenant_id 必须等于 tenant_id，member/platform_operator 为空，actor key 写入 authorization basis。
- `platform_operator`：只允许 owner provisioning、Tenant 生命周期、TenantModule 治理等 allowlist 镜像事件；actor_tenant_id/member 为空，platform_operator/account 必填。它不能记录商品、库存或其他租户业务 action。

字段从第一版就保留双边语义，防止未来代运营或加盟协作无法还原实际操作方。未来跨 Tenant 成员操作仍须增加明确的 relation/grant ID、有效期和独立 Guard；仅填写审计字段不能产生访问权限。

`target_set_digest` 固定为按 target_resource_key、规范化 target_id 排序后的 canonical JSON 的 SHA-256；空集合为 NULL。审计和应用日志不得保存完整多目标 ID 集。

## 10. 受控跨平面关系

允许的跨平面关系只有以下几类：

| 来源 | 目标 | 为什么允许 | 额外守卫 |
| --- | --- | --- | --- |
| Credential | Account | 凭证解析登录账号 | 凭证和账号状态均有效 |
| TenantMember | Account | 全局账号加入租户 | `(tenant_id, account_id)` 唯一 |
| Tenant-owned table | Tenant | 数据归属根 | TenantContext 与行 `tenant_id` 一致 |
| PlatformOperator | Account | 同一人获得平台操作身份 | 平台 guard，不读取 TenantMember 权限 |
| RolePermission | Permission | 租户角色使用部署级权限目录 | TenantModule 和 permission 状态有效 |

除此之外默认禁止。尤其禁止：

- PlatformRole 直接关联 TenantMember。
- Tenant Role 直接关联 PlatformOperator。
- Department 关联另一个 Tenant 的成员。
- 一个租户的 Role 分配给另一个租户的 Member。
- Module 通过外键依赖另一个 Module 的内部表。

## 11. Migration 固定顺序

低上下文实现 Agent 不得自行调整依赖顺序：

1. `pa_account`
2. `pa_credential`
3. `pa_tenant`
4. `pa_platform_operator`
5. `pa_permission`
6. `pa_platform_role`
7. `pa_platform_role_permission`
8. `pa_platform_operator_role`
9. `pa_department`
10. `pa_tenant_member`
11. 为 `pa_tenant_member.primary_department_id` 增加复合 FK
12. `pa_role`
13. `pa_role_permission`
14. `pa_member_role`
15. `pa_tenant_module`
16. `pa_platform_audit_event`
17. `pa_tenant_audit_event`

`Department -> TenantMember` 不建立负责人 FK，避免循环依赖。岗位、多部门和邀请由 P1 独立 migration 增加。

## 12. G-01 必测数据库约束

1. 同一邮箱大小写不同不能创建两个 Credential。
2. 一个 Account 可以在两个 Tenant 各有一个 TenantMember。
3. 同一 Tenant 不能为同一 Account 创建两个 TenantMember。
4. 成员不能引用另一个 Tenant 的 Department。
5. Department 不能把另一个 Tenant 的 Department 设为父级。
6. MemberRole 不能连接不同 Tenant 的 Member 和 Role。
7. RolePermission 不能连接不存在或已 retired 的 Permission；后者由 Service 拒绝。
8. 平台角色不能被分配给 TenantMember。
9. Tenant Role 不能被分配给 PlatformOperator。
10. TenantModule 不能写入部署 registry 中不存在的 `module_key`。
11. Tenant `suspended/closed` 后不能创建或激活成员。
12. Account `disabled/closed` 后所有租户成员均不能建立新会话。
13. TenantMember `suspended/left` 只影响对应 Tenant，不影响同 Account 的其他 TenantMember。
14. 核心终态记录不能通过普通 CRUD 物理删除。
15. 平台审计表不存在 `tenant_id`；租户审计表不能写 NULL `tenant_id`。
16. 审计 Repository 不暴露 update/delete 方法。
17. 所有 BIGINT ID 在 API schema 中声明为 string。
18. `security_revision` 变化能被 G-02 会话校验识别。
19. P0 member/tenant_system 审计的 `actor_tenant_id` 不等于 `tenant_id` 时写入失败。
20. 单目标审计保存目标类型和 ID；多目标审计保存数量与稳定 digest，不把完整敏感 ID 集写入日志。
21. Tenant 不创建默认根 Department 时仍可激活成员；部门范围授权必须得到空集合。
22. 平台 owner provisioning 可写 actor_type=platform_operator 的目标 Tenant 镜像审计，但平台业务 action 或伪造 actor 字段失败。
23. 相同 typed targets 不同输入顺序生成相同 digest；不同目标集合生成不同 digest。

## 13. 明确延后但不堵死的能力

| 能力 | 阶段 | 扩展方式 |
| --- | --- | --- |
| 手机号、OAuth、Passkey | P1 | 新增 Credential handler 和 kind，不新增账号表 |
| 邀请确认与管理员免邀请激活 | P1 | 增加 Invitation 表，仍落到 TenantMember 状态机 |
| 多部门、岗位、职级 | P1 | 增加 member-department/position 关系，不改变 Tenant 根 |
| 租户域名和入口 | P1 | 域名只解析候选 Tenant，不产生授权 |
| 平台支持/官方代运营 | P1/P2 | 独立限时 SupportSession，双边审计，不共享客户账号 |
| 父子租户、集团 | P2 | 增加显式 TenantRelation，不自动继承权限 |
| 每租户独立数据库 | P2 | Deployment/placement 适配，不改变业务表 `tenant_id` 契约 |
| Store/Warehouse/Supplier/Product/Inventory | 业务 Module | 各 Module 自己拥有表、规则和 Provider；一个 Tenant 可有多类别、每类多个实例 |

## 14. G-01 结论

该模型已经把 P0 的“谁登录、属于哪个租户、在租户里是谁、属于哪个部门、拥有哪些角色、租户开了哪些模块、操作方和目标方如何审计”落到真实表和字段。

Kernel 只保存 TenantMember 一条成员身份，不因成员能管理多个同类业务目标而复制成员记录。门店、仓库、供应商等目标类型和实例由 Module 管理，G-03 再把成员授权映射到一个或多个同类目标。

它没有把 Peanut Admin 变成 DCS，也没有为了未来可能性提前建立集团、代运营、设备、门店、仓库和 Application 等核心表。

下一步 G-02 必须在这些表上定义可撤销会话、租户选择和可信上下文；G-03 再补数据权限规则表和完整计算算法。在 G-02/G-03 完成前，本模型仍不能作为编码放行依据。
