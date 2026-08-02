# G-02 登录、会话和可信上下文协议

> 状态：Recalibrated and Reviewed（2026-07-15），通过 48 号复审，等待新编码批准
>
> 依赖：`37-g01-kernel-data-model.md`
>
> 本文冻结 P0 的邮箱密码登录、租户选择、平台/租户会话隔离，以及 HTTP、CLI、队列和计划任务如何建立可信上下文。

## 1. 先用业务语言说明

用户输入邮箱和密码后，系统只确认“这是哪个全局账号”。系统随后查找这个账号在哪些租户中拥有有效成员身份：

```text
邮箱密码
  -> Account
  -> 可用 TenantMember 列表
  -> 用户选择 Tenant
  -> 创建绑定该 TenantMember 的新 Session
  -> 后端从 Session 建立 TenantContext
```

用户选择租户不是在浏览器里修改一个 `tenant_id` 变量。每次选择或切换租户，后端都重新验证 Account、Tenant 和 TenantMember，并签发新的租户会话。

平台管理方使用另一套登录地址、Session 表和 Guard。即使同一个 Account 同时关联 PlatformOperator 和 TenantMember，平台会话也不能调用租户业务 API。

## 2. P0 固定取舍

| 问题 | P0 决策 | 原因 |
| --- | --- | --- |
| 会话模式 | 服务端 Session + 随机 opaque token | 可撤销，不把授权结论永久塞进 token |
| JWT | 不使用 | 当前没有无状态跨服务需求；JWT 不自动解决撤销和权限变化 |
| Access token | 256-bit 随机值，15 分钟 | 只存在前端内存，通过 Bearer header 发送 |
| Refresh token | 256-bit 随机值，14 天绝对期限 | HttpOnly/Secure/SameSite cookie，使用一次即轮换 |
| Session idle | 8 小时 | 每 5 分钟最多落库一次 `last_seen_at` |
| 租户选择 challenge | 5 分钟、单次使用 | 登录成功后还不能直接成为任意租户成员 |
| 状态即时失效 | 每个请求查询当前安全状态和 revision | P0 优先正确性；后续缓存必须保持同等失效语义 |
| 平台/租户会话 | 分表、分 token 表、分 Guard | 杜绝 `audience` 漏判变成跨平面授权 |
| 默认 Web 传输 | Access 存内存；Refresh 存 HttpOnly cookie | 不把长期 token 写入 localStorage |

密码使用 PHP `password_hash`/`password_verify`/`password_needs_rehash` 和 Argon2id。随机 token 使用 `random_bytes(32)`。不得自研加密算法或 token 签名格式。

## 3. 新增认证支持表

这些表属于 G-02，不改变 G-01 的业务对象含义。

### 3.1 `pa_login_challenge`

凭证验证成功、租户会话创建之前的短期单次凭据。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `challenge_key` | CHAR(26) | NOT NULL | ULID，仅作为公开追踪标识 |
| `token_hash` | CHAR(64) | NOT NULL | SHA-256(raw challenge token) |
| `account_id` | BIGINT UNSIGNED | NOT NULL | FK Account |
| `purpose` | VARCHAR(32) | NOT NULL | `tenant_login/tenant_switch` |
| `status` | VARCHAR(16) | `active` | `active/used/revoked/expired` |
| `source_session_key` | CHAR(26) | NULL | `tenant_switch` 时记录原 Session key，不作为授权依据 |
| `ip_address` | VARCHAR(45) | NULL | |
| `user_agent_hash` | CHAR(64) | NULL | |
| `expires_at` | DATETIME(3) | NOT NULL | 签发后 5 分钟 |
| `used_at` | DATETIME(3) | NULL | |
| `revoked_at` | DATETIME(3) | NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_login_challenge_key (challenge_key)`。
- `UNIQUE uk_login_challenge_token (token_hash)`。
- `INDEX idx_login_challenge_account (account_id, status, expires_at)`。
- Challenge 不保存候选 Tenant 列表；选择时重新查询当前有效 TenantMember。
- Token 只能消费一次，消费使用事务和行锁。

### 3.2 `pa_tenant_session`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `session_key` | CHAR(26) | NOT NULL | 对外稳定 Session 标识 |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL | 复合归属根 |
| `account_id` | BIGINT UNSIGNED | NOT NULL | FK Account |
| `tenant_member_id` | BIGINT UNSIGNED | NOT NULL | 必须同 Tenant |
| `client_key` | VARCHAR(64) | NOT NULL | P0 固定 `admin-web` |
| `status` | VARCHAR(16) | `active` | `active/revoked/expired` |
| `account_security_revision` | BIGINT UNSIGNED | NOT NULL | 创建时快照 |
| `tenant_security_revision` | BIGINT UNSIGNED | NOT NULL | 创建时快照 |
| `member_security_revision` | BIGINT UNSIGNED | NOT NULL | 创建时快照 |
| `issued_at` | DATETIME(3) | NOT NULL | |
| `last_seen_at` | DATETIME(3) | NOT NULL | |
| `idle_expires_at` | DATETIME(3) | NOT NULL | 最后活动后 8 小时 |
| `absolute_expires_at` | DATETIME(3) | NOT NULL | 创建后最多 14 天 |
| `ip_address` | VARCHAR(45) | NULL | 最近签发/刷新来源 |
| `user_agent_hash` | CHAR(64) | NULL | |
| `revoked_at` | DATETIME(3) | NULL | |
| `revoke_reason` | VARCHAR(64) | NULL | 稳定原因码 |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_tenant_session_key (session_key)`。
- `INDEX idx_tenant_session_member (tenant_id, tenant_member_id, status, absolute_expires_at)`。
- `INDEX idx_tenant_session_account (account_id, status, absolute_expires_at)`。
- 复合 FK `(tenant_id, tenant_member_id) -> pa_tenant_member(tenant_id, id)`。
- Session 中没有可变的“当前门店/当前仓库”。业务对象范围由每个请求和 G-03 数据权限校验决定。

### 3.3 `pa_tenant_session_token`

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `session_id` | BIGINT UNSIGNED | NOT NULL | FK TenantSession |
| `token_type` | VARCHAR(16) | NOT NULL | `access/refresh` |
| `token_hash` | CHAR(64) | NOT NULL | SHA-256(raw token) |
| `status` | VARCHAR(16) | `active` | `active/used/revoked/expired` |
| `parent_token_id` | BIGINT UNSIGNED | NULL | 轮换来源 |
| `replaced_by_token_id` | BIGINT UNSIGNED | NULL | 轮换后新 token |
| `expires_at` | DATETIME(3) | NOT NULL | |
| `used_at` | DATETIME(3) | NULL | Refresh 消费时间 |
| `revoked_at` | DATETIME(3) | NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |

约束和索引：

- `UNIQUE uk_tenant_session_token_hash (token_hash)`。
- `INDEX idx_tenant_session_token_active (session_id, token_type, status, expires_at)`。
- 同一 Session 同一时刻最多一个 active access 和一个 active refresh，由事务、Session 行锁和 Service 测试保证。
- Refresh 重放时撤销整个 Session 及全部 token。

### 3.4 `pa_platform_session`

字段与 TenantSession 的共同部分相同，但不包含 `tenant_id` 和 `tenant_member_id`：

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `session_key` | CHAR(26) | NOT NULL | Unique |
| `account_id` | BIGINT UNSIGNED | NOT NULL | FK Account |
| `platform_operator_id` | BIGINT UNSIGNED | NOT NULL | FK PlatformOperator |
| `client_key` | VARCHAR(64) | NOT NULL | P0 固定 `platform-web` |
| `status` | VARCHAR(16) | `active` | `active/revoked/expired` |
| `account_security_revision` | BIGINT UNSIGNED | NOT NULL | |
| `operator_security_revision` | BIGINT UNSIGNED | NOT NULL | |
| `issued_at` | DATETIME(3) | NOT NULL | |
| `last_seen_at` | DATETIME(3) | NOT NULL | |
| `idle_expires_at` | DATETIME(3) | NOT NULL | |
| `absolute_expires_at` | DATETIME(3) | NOT NULL | |
| `ip_address` | VARCHAR(45) | NULL | |
| `user_agent_hash` | CHAR(64) | NULL | |
| `revoked_at` | DATETIME(3) | NULL | |
| `revoke_reason` | VARCHAR(64) | NULL | |
| `created_at` | DATETIME(3) | NOT NULL | |
| `updated_at` | DATETIME(3) | NOT NULL | |

索引：

- `UNIQUE uk_platform_session_key (session_key)`。
- `INDEX idx_platform_session_operator (platform_operator_id, status, absolute_expires_at)`。
- `INDEX idx_platform_session_account (account_id, status, absolute_expires_at)`。

### 3.5 `pa_platform_session_token`

字段和 TenantSessionToken 相同，`session_id` 指向 PlatformSession，索引及 refresh rotation 规则完全相同。物理分表用于保证 Tenant Guard 永远不会加载平台 token。

### 3.6 `pa_auth_security_event`

记录租户尚未确定时的全局认证安全事件。它不是平台业务审计，也不向普通租户管理员开放。

| 字段 | 类型 | Null/默认 | 规则 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | |
| `audience` | VARCHAR(16) | NOT NULL | `tenant/platform` |
| `event_type` | VARCHAR(64) | NOT NULL | `login_succeeded/login_failed/challenge_issued/token_refreshed/token_reused/session_revoked` 等 |
| `outcome` | VARCHAR(16) | NOT NULL | `success/denied/error` |
| `reason_code` | VARCHAR(96) | NULL | 稳定安全原因 |
| `account_id` | BIGINT UNSIGNED | NULL | 无法识别账号时 NULL |
| `credential_id` | BIGINT UNSIGNED | NULL | |
| `session_key` | CHAR(26) | NULL | |
| `identifier_hmac` | CHAR(64) | NULL | 规范化 identifier 的 HMAC-SHA-256，不存明文 |
| `request_id` | VARCHAR(64) | NOT NULL | |
| `ip_address` | VARCHAR(45) | NULL | |
| `user_agent_hash` | CHAR(64) | NULL | |
| `metadata_json` | JSON | NULL | 固定 schema、脱敏 |
| `occurred_at` | DATETIME(3) | NOT NULL | |

索引：

- `INDEX idx_auth_event_account (account_id, occurred_at)`。
- `INDEX idx_auth_event_identifier (identifier_hmac, occurred_at)`。
- `INDEX idx_auth_event_request (request_id)`。
- `INDEX idx_auth_event_time (occurred_at, id)`。

成功选择 Tenant 后，同时写 `pa_tenant_audit_event`。因此租户能查询本租户登录日志，而租户确定前的密码探测不会泄露给任何客户。

## 4. 租户登录流程

### 4.1 请求输入

P0 租户登录只接受：

```json
{
  "email": "user@example.com",
  "password": "<not logged>",
  "tenant_code": "optional-candidate"
}
```

`tenant_code` 只是缩小候选范围，不产生授权。P0 不接受裸 `tenant_id` 直接创建会话。

### 4.2 固定执行顺序

1. 规范化邮箱，并计算日志用 `identifier_hmac`。
2. 执行 IP + identifier 双维度限流。
3. 通过唯一索引查找 Credential；账号不存在和密码错误对外都返回 `AUTH_INVALID_CREDENTIALS`。
4. 检查 Credential、Account 的状态和锁定时间。
5. 使用 `password_verify`；成功后按需 rehash，失败则原子增加 `failed_attempts`。
6. 连续失败 5 次锁定该 Credential 15 分钟；成功后清零。
7. 查询 Account 的有效 TenantMember，并联查 Tenant 状态。
8. 如果提供 `tenant_code`，只保留匹配且有效的成员；无匹配返回统一不可用错误。
9. 没有可用成员：返回 `AUTH_NO_AVAILABLE_TENANT`。
10. 只有一个可用成员且产品配置允许自动进入：直接创建 TenantSession。
11. 有多个成员：创建 LoginChallenge，返回可选择的租户摘要。
12. 写 AuthSecurityEvent；成功创建租户会话后再写该租户的 TenantAuditEvent。

登录响应不能泄露成员数量、租户名称或账号状态，除非密码已经验证成功。

### 4.3 租户选择

客户端提交一次性 challenge token 和所选 `tenant_id`。这里的 ID 仍只是候选值。

服务端在同一事务中：

1. 以 token hash 锁定 LoginChallenge。
2. 验证 challenge active、未过期、purpose 正确、IP/UA 风险可接受。
3. 重新查询该 Account 对应的 TenantMember 和 Tenant。
4. 验证 Tenant `active`、Member `active`、Account `active`。
5. 将 challenge 标记 `used`。
6. 创建 TenantSession 和首对 access/refresh token。
7. 提交事务后写认证事件和租户审计。

不能使用 challenge 选择不属于该 Account 的 Tenant，也不能重放已经使用的 challenge。

## 5. 平台登录流程

平台端固定使用独立路由前缀 `/api/platform/v1/auth/*`：

1. 校验同一套全局 Credential 和 Account。
2. 查询 `pa_platform_operator`，必须为 active。
3. 不查询、不选择 TenantMember。
4. 创建 PlatformSession 和 PlatformSessionToken。
5. 写 AuthSecurityEvent 和 PlatformAuditEvent。

租户登录接口不会因为 Account 同时是 PlatformOperator 而显示“平台入口”。平台入口地址和权限域必须显式分开。

## 6. 租户切换

租户切换不是修改当前 Session 的 `tenant_id`：

1. 当前 TenantSession 先通过完整认证校验。
2. 后端为当前 Account 创建 purpose=`tenant_switch` 的 5 分钟 LoginChallenge。
3. 返回该 Account 当前可用的其他 TenantMember 摘要。
4. 用户选择后，服务端按租户选择流程创建全新的 TenantSession。
5. 新 Session 和 token 创建成功后，撤销旧 Session，reason=`tenant_switched`。
6. 前端原子替换 access token；失败时不得留下半切换状态。

默认不要求再次输入密码；敏感项目可以通过配置要求近期认证。该配置不能关闭 Account/Tenant/Member 的重新校验。

## 7. Token 和 Session 生命周期

### 7.1 Access token

- 租户原始值格式：`pa_tat_` + base64url(random 32 bytes)；平台格式：`pa_pat_` + base64url(random 32 bytes)。前缀只用于 Guard 快速拒绝，不是授权证明。
- 数据库只保存完整原始值的 SHA-256。
- 前端只保存在内存，不进入 URL、localStorage、日志、错误报告或埋点。
- 每个 API 请求使用 `Authorization: Bearer <token>`。
- 15 分钟过期；刷新时旧 access 立即撤销。

### 7.2 Refresh token

- 租户原始值格式：`pa_trt_` + base64url(random 32 bytes)；平台格式：`pa_prt_` + base64url(random 32 bytes)。
- Tenant Admin 只通过 `Set-Cookie` 写入 `__Host-pa_tenant_refresh`；Platform Admin 使用独立的 `__Host-pa_platform_refresh`，两者不能覆盖。
- Cookie 固定 `Secure; HttpOnly; SameSite=Lax; Path=/`，不设置 Domain。
- 刷新接口要求可信 Origin，并限制 CORS；不能只依赖 cookie 名称。
- 每次刷新在事务和行锁中把旧 refresh 标记 `used`，签发新 access + refresh。
- 已使用 refresh 再次出现视为重放，撤销整个 Session，reason=`refresh_reused`。

### 7.3 Session 过期与撤销

任一条件满足即拒绝：

- Session 不是 `active`。
- access token 不是 `active` 或已过期。
- 当前时间超过 idle 或 absolute expiry。
- Account、Tenant、TenantMember/PlatformOperator 状态不再有效。
- 当前安全修订号与 Session 快照不同。
- token 类型、表或路由 Guard 不匹配。

`last_seen_at` 最多每 5 分钟更新一次，以降低写放大；更新同时把 idle expiry 推迟到 8 小时，但不得超过 absolute expiry。

## 8. 即时失效规则

P0 每个请求使用一次索引查询联查 Session、Token、Account 及对应 Principal：

### Tenant Guard

```text
TenantSessionToken
  JOIN TenantSession
  JOIN Account
  JOIN TenantMember ON same tenant
  JOIN Tenant
```

必须同时验证：

- Account `active` 且 revision 匹配。
- Tenant `active` 且 revision 匹配。
- TenantMember `active` 且 revision 匹配。
- TenantSession 的三组 ID 关系仍一致。

### Platform Guard

```text
PlatformSessionToken
  JOIN PlatformSession
  JOIN Account
  JOIN PlatformOperator
```

必须同时验证两方 active 和 revision 匹配。

状态或 revision 变化后，下一次请求立即失败并撤销 Session。P0 不使用有延迟的正向身份缓存。未来引入 Redis 时，必须先证明失效事件、缓存 miss、网络分区和回源行为仍满足同一语义。

角色和数据权限不永久缓存在 Session 中。G-03 使用独立 `authorization_revision` 和版本化授权缓存；角色、权限或规则变化必须使对应授权缓存立即失效，而不是撤销 Session 或依赖重新登录。

## 9. HTTP 可信上下文

### 9.1 `TenantContext`

Tenant Guard 校验成功后创建不可变对象：

```text
TenantContext
  tenant_id
  account_id
  tenant_member_id
  session_key
  client_key
  request_id
  issued_at
```

规则：

- 只能由 `TenantContextFactory::fromValidatedSession()` 创建。
- Controller、Service 或 Module 不能手工 new、set 或覆盖 tenant_id。
- Repository 从 ContextAccessor 获取 tenant_id，不接受普通请求 DTO 覆盖。
- 业务请求里的 Store/Warehouse 等 ID 是待校验目标，不进入 TenantContext。
- TenantContext 不包含通用 `current_subject_id`、`current_target_id` 或目标集合；页面选择值不能成为会话授权事实。
- 请求结束必须清理 ContextAccessor，常驻进程测试必须证明不会串请求。

### 9.2 `AuthorizedOperationContext`

G-03 在完成 ResourceOperation、Permission、DataPermission、目标归属和目标基数校验后，可以为当前用例创建短生命周期不可变对象：

```text
AuthorizedOperationContext
  tenant_context
  resource_key
  operation
  target_cardinality
  typed_target_sets[]
  authorization_decision_id
```

固定规则：

- 它只能由授权服务根据已验证结果创建，Controller 不能把请求 DTO 直接转换为该对象。
- 每个 typed target set 只包含一个 `target_resource_key` 和该类别下的一个或多个规范化 ID。
- 它只在当前 command/query 调用链中有效，不写入 Session、Account、TenantMember 或全局 ContextAccessor。
- 列表查询可以有多个已授权目标；普通写命令按 G-03 的 `one_required` 规则只允许一个主要边界目标。
- 业务需要来源地、目的地等关联目标时，由 operation schema 分别命名并逐个授权，不能用一个无类型 ID 数组代替。

### 9.3 `PlatformContext`

独立对象：

```text
PlatformContext
  account_id
  platform_operator_id
  session_key
  client_key
  request_id
  issued_at
```

它没有 tenant_id，也不能转换成 TenantContext。Tenant route 注入 PlatformContext 或 Platform route 注入 TenantContext 都应在路由 Guard 层失败。

## 10. CLI、队列和计划任务上下文

### 10.1 CLI

租户级 CLI 命令必须显式声明：

```text
command class -> required context: tenant
--tenant=<tenant-code>
system_actor_key=<manifest 中注册的固定 key>
```

执行顺序：

1. 根据 code 查询 active Tenant。
2. 验证命令允许使用该 system actor。
3. 创建 `TenantSystemContext`，只包含 tenant_id、actor key、operation_id。
4. 按 G-03 校验 system actor 被声明的固定能力。
5. 每个 Tenant 单独事务、日志和审计。

禁止使用可选 `--tenant` 后在缺失时退化为全租户权限。平台 CLI 使用独立 `PlatformSystemContext` 和命令类型。

### 10.2 队列消息

每条租户任务固定 envelope：

```json
{
  "context_version": 1,
  "tenant_id": "123",
  "actor_type": "member",
  "account_id": "45",
  "tenant_member_id": "67",
  "source_session_key": "01...",
  "required_action": "inventory.export",
  "requested_targets": [
    {
      "target_resource_key": "example.project",
      "target_ids": ["9001", "9002"]
    }
  ],
  "request_id": "req_...",
  "operation_id": "op_...",
  "enqueued_at": "2026-07-15T10:00:00.000Z"
}
```

Worker 不信任消息中的授权结论。执行前必须：

1. 校验 schema 和消息来源。
2. 重查 Tenant、Account、TenantMember 当前状态。
3. 重算 `required_action` 的功能和数据权限。
4. 为本次任务建立新的 TenantJobContext。
5. 按 ResourceOperation 重新校验消息中的每个 typed target set、目标基数、目标归属和当前数据权限。
6. 写执行结果和拒绝审计。

如果任务是系统任务，`actor_type=tenant_system` 且使用 manifest 注册的 system actor key；不得伪造 TenantMember。

### 10.3 计划任务

租户计划任务必须先取得明确 Tenant 列表，再逐租户建立 TenantSystemContext。一个租户失败不能把 Context 泄漏到下一个租户。

跨租户汇总只能由平台侧专用读模型或 ETL 执行，不能让普通 Module 关闭 Tenant Guard 后扫描全部业务表。

## 11. 错误码和 HTTP 状态

| 错误码 | HTTP | 对外含义 | 审计要求 |
| --- | --- | --- | --- |
| `AUTH_INVALID_CREDENTIALS` | 401 | 邮箱或密码不正确 | 全局认证失败，不能区分账号不存在/密码错误 |
| `AUTH_RATE_LIMITED` | 429 | 尝试过于频繁 | 记录 IP、identifier HMAC 和窗口 |
| `AUTH_NO_AVAILABLE_TENANT` | 403 | 当前账号没有可进入的租户 | 密码已验证后可返回 |
| `AUTH_TENANT_SELECTION_REQUIRED` | 200 业务状态 | 需要选择租户 | 返回 challenge 和候选摘要 |
| `AUTH_CHALLENGE_INVALID` | 401 | 选择凭据无效 | 记录 denied |
| `AUTH_CHALLENGE_EXPIRED` | 401 | 选择凭据过期 | 记录 denied |
| `AUTH_CHALLENGE_USED` | 401 | 选择凭据已使用 | 记录重放风险 |
| `AUTH_TOKEN_INVALID` | 401 | token 无效 | 不泄露在哪一层失败 |
| `AUTH_SESSION_EXPIRED` | 401 | 会话已过期 | 撤销残留 token |
| `AUTH_SESSION_REVOKED` | 401 | 会话已撤销 | 返回稳定通用消息 |
| `AUTH_REFRESH_REUSED` | 401 | 检测到 refresh 重放 | 撤销整个 Session，提升安全审计等级 |
| `AUTH_AUDIENCE_MISMATCH` | 401 | 凭证不能用于当前入口 | 同时记录来源 Guard 和目标 Guard |
| `AUTH_ACCOUNT_UNAVAILABLE` | 401 | 账号当前不可用 | 登录阶段不暴露具体状态 |
| `AUTH_TENANT_UNAVAILABLE` | 403 | 租户当前不可用 | 已认证后可返回 |
| `AUTH_MEMBER_UNAVAILABLE` | 403 | 成员当前不可用 | 已认证后可返回 |
| `CONTEXT_TENANT_REQUIRED` | 403 | 租户上下文缺失 | 记录 route、handler、request ID |
| `CONTEXT_TENANT_MISMATCH` | 403 | 资源与上下文租户不一致 | 高优先级越权审计 |
| `CONTEXT_SYSTEM_ACTOR_INVALID` | 403 | 系统任务身份不被允许 | 记录命令/任务 key |

G-05 负责把这些错误装入统一 API envelope；错误消息不得包含表名、SQL、token 片段或账号状态细节。

## 12. 必须审计的认证动作

- 登录成功和失败。
- 凭证锁定、解锁、密码修改和撤销。
- LoginChallenge 签发、消费、过期和重放。
- 租户选择和租户切换。
- Session 刷新、单会话退出、全部会话退出。
- Refresh token 重放。
- Account/Tenant/Member/Operator 状态导致的 Session 失效。
- Platform token 访问 Tenant route，或反向访问。
- CLI、Job、Schedule 缺少或伪造 TenantContext。

任何日志都不得保存密码、原始 token、Authorization header、Cookie、完整 identifier 或 `secret_hash`。

## 13. G-02 必测场景

1. 正确邮箱密码且只有一个有效 TenantMember 时可自动进入。
2. 正确邮箱密码且有两个有效 TenantMember 时只能得到 Challenge，不能直接得到租户 Session。
3. Challenge 不能选择第三个不属于 Account 的 Tenant。
4. Challenge 只能使用一次，过期后不可用。
5. 可选 `tenant_code` 只能缩小候选，不能绕过成员关系。
6. Account 锁定后所有 TenantSession 和 PlatformSession 下一请求失败。
7. Tenant 暂停后仅该 Tenant 的 Session 失败，不影响 Account 的其他 Tenant。
8. TenantMember 暂停后只影响该成员对应 Tenant。
9. PlatformOperator 暂停后 PlatformSession 失败，不影响其 TenantMember。
10. Platform access token 调用 Tenant API 返回 `AUTH_AUDIENCE_MISMATCH`。
11. Tenant access token 调用 Platform API同样失败。
12. 前端修改请求体或 header 中的 tenant_id 不能改变 TenantContext。
13. Access token 过期但 refresh 有效时可以轮换。
14. Refresh 一次使用后再次使用会撤销整个 Session。
15. 退出登录后 access 和 refresh 均不可再用。
16. 租户切换创建新 Session 并撤销旧 Session。
17. ContextAccessor 在两个连续常驻进程请求之间不会残留。
18. CLI 缺少 `--tenant` 时拒绝，不会运行全租户任务。
19. Queue 消息伪造 tenant_id 或 member_id 组合时拒绝。
20. Queue 入队后成员权限被撤销，执行时必须重新拒绝。
21. Scheduler 处理租户 A 失败后，租户 B 不继承 A 的 Context。
22. 登录失败日志只能看到 identifier HMAC，不能看到邮箱和密码。
23. localStorage、错误日志和埋点中不存在 access/refresh token。
24. 安全 revision 更新后，下一请求立即撤销旧 Session。
25. HTTP 请求伪造 `current_subject_id/current_target_id` 不能改变 TenantContext。
26. 同一成员先后对 Project A/B 操作时只产生两个独立 AuthorizedOperationContext，不修改 Session。
27. 一个 typed target set 混入两种 `target_resource_key` 时 schema 拒绝。
28. Worker 收到已撤权目标或不符合 cardinality 的 requested_targets 时拒绝并审计。

## 14. G-02 结论

P0 的 Session 不是“浏览器记住 tenant_id”，而是服务端绑定 Account、TenantMember 和 Tenant 的可撤销安全记录。平台身份和租户身份即使共享 Account，也不会共享会话表、token 表或 Guard。

同样，业务目标也不是 Session 的全局开关。一个成员可以在不同请求中管理多个门店、仓库或其他同类目标，但每次都由 G-03 根据资源、操作和 typed target sets 重新裁决。

HTTP、CLI、队列和计划任务都只能通过各自受控 Factory 建立上下文；缺少上下文时默认拒绝，不存在回退成平台超级权限的路径。

下一步 G-03 必须定义功能权限、数据权限、Provider、合并算法和缓存失效。只有认证通过不代表可以读取或修改任何业务数据。
