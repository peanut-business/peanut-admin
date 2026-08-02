# G-05 API 与错误契约

> 状态：Recalibrated and Reviewed（2026-07-15），通过 48 号复审，等待新编码批准
>
> 依赖：G-01 至 G-04
>
> 标准：OpenAPI 3.1.2；错误响应遵循 RFC 9457 Problem Details

## 1. 基本原则

- Tenant API 固定前缀 `/api/v1`，从 TenantSession 建立 TenantContext。
- Platform API 固定前缀 `/api/platform/v1`，只接受 PlatformSession。
- Tenant API URL 不再重复携带 tenant_id；平台管理某租户时才在平台 URL 使用 tenant_id。
- 业务目标使用显式 `target_resource_key + target_id/target_ids`；禁止裸 `subject_id`、无类型 ID 数组或客户端声明“已授权”。
- 普通写 endpoint 必须符合 G-03 `one_required`；多目标读、聚合读、策略发布和批量写必须分别使用独立 operation/schema。
- JSON 字段使用 `snake_case`。
- 所有 BIGINT ID 输出和输入均为十进制字符串。
- 时间使用 UTC ISO 8601，例如 `2026-07-15T08:30:12.123Z`。
- 成功响应使用 `{data, meta?, links?}`；`204` 无响应体。
- 错误响应不套成功 envelope，使用 `application/problem+json`。
- API 不返回数据库表名、SQL、堆栈、文件路径、token、密码哈希或内部异常消息。

## 2. HTTP 和通用 Header

### 请求

| Header | 规则 |
| --- | --- |
| `Authorization` | `Bearer <access-token>`；登录/刷新例外 |
| `Accept` | `application/json`，错误可接受 `application/problem+json` |
| `Content-Type` | JSON 写请求固定 `application/json` |
| `X-Request-Id` | 可选；符合格式时沿用，否则服务端生成 |
| `Idempotency-Key` | 指定写操作必填，见第 12 节 |
| `If-Match` | 更新/删除资源时携带 ETag revision |
| `Accept-Language` | 可选，默认 `zh-CN` |

### 响应

| Header | 规则 |
| --- | --- |
| `X-Request-Id` | 所有响应必有 |
| `ETag` | 可修改单资源响应返回，如 `"rev-3"` |
| `Location` | 201 创建成功时返回资源 URL |
| `Retry-After` | 429/503 可返回 |
| `Cache-Control` | 含账号、权限和租户数据默认 `no-store` |

## 3. 成功响应

### 单资源

```json
{
  "data": {
    "id": "42",
    "name": "运营部",
    "status": "active",
    "revision": "3"
  },
  "meta": {
    "request_id": "req_01K..."
  }
}
```

### 列表

```json
{
  "data": [],
  "meta": {
    "request_id": "req_01K...",
    "page": 1,
    "page_size": 20,
    "total": 0,
    "total_pages": 0
  },
  "links": {
    "self": "/api/v1/members?page=1&page_size=20",
    "next": null,
    "prev": null
  }
}
```

P0 通用后台采用 page pagination；`page >= 1`，`1 <= page_size <= 100`，默认 20。高吞吐审计/流水端点可以在自己的 OpenAPI operation 明确使用 cursor，但不能同一端点混用两种协议。

## 4. Problem Details

错误 Content-Type 固定 `application/problem+json`：

```json
{
  "type": "/docs/problems/authz-data-denied",
  "title": "Resource not found",
  "status": 404,
  "detail": "The requested resource does not exist or is not accessible.",
  "instance": "urn:request:req_01K...",
  "code": "AUTHZ_DATA_DENIED",
  "request_id": "req_01K..."
}
```

字段：

| 字段 | 规则 |
| --- | --- |
| `type` | 可解析到当前文档站问题页的稳定 URI reference |
| `title` | 稳定短标题，可本地化 |
| `status` | 与实际 HTTP status 一致 |
| `detail` | 本次问题的安全说明，不含内部实现 |
| `instance` | `urn:request:<request_id>` |
| `code` | Peanut Admin 稳定机器错误码扩展 |
| `request_id` | 支持定位日志和审计 |
| `errors` | 仅验证错误使用，见下一节 |

前端以 `code` 决定行为，不解析 `detail` 文本。`type` 对应开发手册中的长期说明页。

## 5. 验证错误

```json
{
  "type": "/docs/problems/validation-failed",
  "title": "Validation failed",
  "status": 422,
  "detail": "One or more fields are invalid.",
  "instance": "urn:request:req_01K...",
  "code": "VALIDATION_FAILED",
  "request_id": "req_01K...",
  "errors": [
    {
      "pointer": "/email",
      "code": "EMAIL_INVALID",
      "message": "邮箱格式不正确"
    }
  ]
}
```

`pointer` 使用 JSON Pointer；查询参数错误使用 `/query/page_size`。前端只能把明确 pointer 对应到表单字段，未知错误显示页面级提示。

## 6. HTTP 状态固定语义

| HTTP | 使用场景 |
| --- | --- |
| 200 | 查询、更新、业务状态响应 |
| 201 | 创建成功 |
| 202 | 已接受异步任务，返回 operation/job ID |
| 204 | 删除当前关系、退出等无响应体操作 |
| 400 | JSON/协议无法解析 |
| 401 | 未认证、token/session/challenge 无效或 audience 错误 |
| 403 | 已认证但功能、租户状态或模块状态不允许 |
| 404 | 资源不存在或单对象数据权限拒绝 |
| 409 | 状态冲突、重复关系、幂等键 payload 冲突 |
| 412 | `If-Match` revision 不匹配 |
| 422 | 字段或可解释业务输入验证失败 |
| 428 | 更新端点缺少 `If-Match` |
| 429 | 限流 |
| 500 | 未声明 operation、Provider 缺失等开发错误，生产仍 fail closed |
| 503 | Module maintenance、依赖服务暂不可用 |

不得所有错误都返回 HTTP 200，也不得只返回 `code=0/1`。

## 7. 认证 API

### 7.1 登录

`POST /api/v1/auth/login`

```json
{
  "email": "user@example.com",
  "password": "secret-not-logged",
  "tenant_code": null
}
```

多个租户时：

```json
{
  "data": {
    "state": "tenant_selection_required",
    "challenge_token": "pa_lc_...",
    "expires_at": "2026-07-15T08:35:00.000Z",
    "tenants": [
      {
        "tenant_id": "101",
        "tenant_code": "alpha-company",
        "tenant_name": "甲公司",
        "tenant_member_id": "501",
        "member_display_name": "张三"
      }
    ]
  },
  "meta": { "request_id": "req_01K..." }
}
```

只有一个租户并允许自动进入时：

```json
{
  "data": {
    "state": "authenticated",
    "access_token": "pa_tat_...",
    "token_type": "Bearer",
    "expires_in": 900,
    "context": {
      "audience": "tenant",
      "account_id": "12",
      "tenant_id": "101",
      "tenant_member_id": "501"
    }
  },
  "meta": { "request_id": "req_01K..." }
}
```

Refresh token 只在 `Set-Cookie: __Host-pa_tenant_refresh=...` 返回，不出现在 JSON。

### 7.2 选择租户

`POST /api/v1/auth/tenants/select`

```json
{
  "challenge_token": "pa_lc_...",
  "tenant_id": "101"
}
```

响应与 authenticated 登录响应相同。tenant_id 是候选，后端必须按 G-02 重新验证 Account/TenantMember/Tenant。

### 7.3 刷新

`POST /api/v1/auth/refresh`

- 从 HttpOnly cookie 读取 refresh token。
- 要求可信 Origin。
- 成功返回新 access token，并轮换 refresh cookie。
- 重放返回 `AUTH_REFRESH_REUSED` 401 并撤销整个 Session。

### 7.4 当前上下文

`GET /api/v1/auth/context`

```json
{
  "data": {
    "audience": "tenant",
    "account": {
      "id": "12",
      "display_name": "张三",
      "avatar_uri": null
    },
    "tenant": {
      "id": "101",
      "code": "alpha-company",
      "display_name": "甲公司",
      "timezone": "Asia/Shanghai"
    },
    "member": {
      "id": "501",
      "display_name": "张三",
      "primary_department_id": "9",
      "role_ids": ["20"]
    },
    "module_keys": ["core", "example.work-item"],
    "permission_keys": ["core.member.read", "example.work-item.read"],
    "authorization_revision": "18"
  },
  "meta": { "request_id": "req_01K..." }
}
```

该响应可用于前端展示，不是后续 API 的永久授权证明。每个 API 仍由后端重新校验。

### 7.5 租户切换

- `POST /api/v1/auth/tenant-switch/challenge`
- `POST /api/v1/auth/tenants/select`

第二步成功创建新 Session 并撤销旧 Session。

### 7.6 退出

`POST /api/v1/auth/logout` -> 204，并清除 refresh cookie。

`POST /api/v1/auth/logout-all` -> 204，撤销当前 Account 的全部 TenantSession；需要近期认证或敏感操作确认。

平台认证使用 `/api/platform/v1/auth/*`，响应 audience 为 `platform`，cookie 名为 `__Host-pa_platform_refresh`。

## 8. P0 固定 Core Permission catalog

以下 key 由 Kernel manifest 注册，发布后不能改义。OpenAPI operation 必须逐一绑定，不得让 Controller 根据路径字符串动态拼 key。

### 8.1 Tenant core

```text
core.member.read
core.member.create
core.member.update
core.member.role.assign
core.member.suspend
core.member.activate
core.member.leave
core.department.read
core.department.create
core.department.update
core.department.move
core.department.archive
core.role.read
core.role.create
core.role.update
core.role.archive
core.role.permission.assign
core.role.data-policy.read
core.role.data-policy.manage
core.permission.read
core.module.read
core.module.configure
core.audit.read
```

`core.tenant-owner` 固定获得以上全部 Tenant core Permission，并对 core ProtectedResource 获得 `tenant_all`。它不自动获得任何 `example.*` 或其他 Module Permission。

`GET /tenant`、`GET /auth/context` 和 `GET /menus` 只要求当前 TenantSession/TenantMember/Tenant 有效；menus 按实际 Permission 过滤。这样没有业务权限的 active 成员仍能建立空工作区，不需要为了加载菜单额外授予一个伪权限。

### 8.2 Platform core

```text
platform.tenant.read
platform.tenant.create
platform.tenant.update
platform.tenant.lifecycle
platform.tenant.provision-owner
platform.tenant.module.manage
platform.operator.read
platform.operator.create
platform.operator.update
platform.operator.lifecycle
platform.operator.role.assign
platform.role.read
platform.role.create
platform.role.update
platform.role.archive
platform.role.permission.assign
platform.permission.read
platform.audit.read
```

`GET /api/platform/v1/auth/context` 和 `/menus` 同样只要求有效 PlatformSession，再按 Platform Permission 过滤。平台 bootstrap 角色的精确 key 和 Permission 集必须由版本控制 seed 注册，不使用 `is_super`。

## 9. P0 Tenant API 目录

| Method | Path | Permission/用途 |
| --- | --- | --- |
| GET | `/tenant` | 有效 TenantContext；当前 Tenant 摘要 |
| GET | `/members` | `core.member.read` |
| POST | `/members` | `core.member.create` |
| GET | `/members/{member_id}` | `core.member.read` |
| PATCH | `/members/{member_id}` | `core.member.update` |
| PUT | `/members/{member_id}/roles` | `core.member.role.assign` |
| POST | `/members/{member_id}/suspend` | `core.member.suspend` |
| POST | `/members/{member_id}/activate` | `core.member.activate` |
| POST | `/members/{member_id}/leave` | `core.member.leave`；最后一个 active owner 不可离开 |
| GET | `/departments` | `core.department.read` |
| POST | `/departments` | `core.department.create` |
| GET | `/departments/{department_id}` | `core.department.read` |
| PATCH | `/departments/{department_id}` | `core.department.update` |
| POST | `/departments/{department_id}/move` | `core.department.move` |
| POST | `/departments/{department_id}/archive` | `core.department.archive` |
| GET | `/roles` | `core.role.read` |
| POST | `/roles` | `core.role.create` |
| GET | `/roles/{role_id}` | `core.role.read` |
| PATCH | `/roles/{role_id}` | `core.role.update` |
| POST | `/roles/{role_id}/archive` | `core.role.archive` |
| PUT | `/roles/{role_id}/permissions` | `core.role.permission.assign` |
| GET | `/permissions` | `core.permission.read`；当前可分配目录 |
| GET | `/roles/{role_id}/data-policies/{resource_key}/{operation}` | `core.role.data-policy.read` |
| PUT | `/roles/{role_id}/data-policies/{resource_key}/{operation}` | `core.role.data-policy.manage` |
| GET | `/authorization/target-candidates` | 按请求的 ResourceOperation 授权；policy-config 模式另需 `core.role.data-policy.manage` |
| GET | `/modules` | `core.module.read` |
| PATCH | `/modules/{module_key}/config` | `core.module.configure` |
| GET | `/audit-events` | `core.audit.read` |
| GET | `/menus` | 有效 TenantContext；按实际权限过滤 |

完整 operation、请求 schema 和错误必须写入 OpenAPI，表格不能代替 schema。

## 10. Platform Tenant API

| Method | Path | Permission/用途 |
| --- | --- | --- |
| GET | `/api/platform/v1/tenants` | `platform.tenant.read` |
| POST | `/api/platform/v1/tenants` | `platform.tenant.create` |
| GET | `/api/platform/v1/tenants/{tenant_id}` | `platform.tenant.read` |
| PATCH | `/api/platform/v1/tenants/{tenant_id}` | `platform.tenant.update` |
| POST | `/api/platform/v1/tenants/{tenant_id}/owner-candidates` | `platform.tenant.provision-owner` |
| POST | `/api/platform/v1/tenants/{tenant_id}/owner-candidates/{member_id}/activate` | `platform.tenant.provision-owner` |
| POST | `/api/platform/v1/tenants/{tenant_id}/activate` | `platform.tenant.lifecycle` |
| POST | `/api/platform/v1/tenants/{tenant_id}/suspend` | `platform.tenant.lifecycle` |
| POST | `/api/platform/v1/tenants/{tenant_id}/close` | `platform.tenant.lifecycle` |
| PUT | `/api/platform/v1/tenants/{tenant_id}/modules/{module_key}` | `platform.tenant.module.manage` |
| DELETE | `/api/platform/v1/tenants/{tenant_id}/modules/{module_key}` | `platform.tenant.module.manage` |
| GET | `/api/platform/v1/operators` | `platform.operator.read` |
| POST | `/api/platform/v1/operators` | `platform.operator.create` |
| GET | `/api/platform/v1/operators/{operator_id}` | `platform.operator.read` |
| PATCH | `/api/platform/v1/operators/{operator_id}` | `platform.operator.update` |
| POST | `/api/platform/v1/operators/{operator_id}/suspend` | `platform.operator.lifecycle` |
| POST | `/api/platform/v1/operators/{operator_id}/activate` | `platform.operator.lifecycle` |
| POST | `/api/platform/v1/operators/{operator_id}/close` | `platform.operator.lifecycle` |
| PUT | `/api/platform/v1/operators/{operator_id}/roles` | `platform.operator.role.assign` |
| GET | `/api/platform/v1/roles` | `platform.role.read` |
| POST | `/api/platform/v1/roles` | `platform.role.create` |
| GET | `/api/platform/v1/roles/{role_id}` | `platform.role.read` |
| PATCH | `/api/platform/v1/roles/{role_id}` | `platform.role.update` |
| POST | `/api/platform/v1/roles/{role_id}/archive` | `platform.role.archive` |
| PUT | `/api/platform/v1/roles/{role_id}/permissions` | `platform.role.permission.assign` |
| GET | `/api/platform/v1/permissions` | `platform.permission.read` |
| GET | `/api/platform/v1/audit-events` | `platform.audit.read` |
| GET | `/api/platform/v1/menus` | 有效 PlatformContext；按实际权限过滤 |

平台操作员和角色端点必须绑定独立 `platform.operator.*`、`platform.role.*` 和 `platform.audit.read` 权限。Owner candidate 使用独立高风险权限 `platform.tenant.provision-owner`。创建或分配操作不能把租户业务 Permission 赋给 PlatformRole，也不能接受客户端指定 security revision。

### 创建 Tenant

```json
{
  "code": "alpha-company",
  "name": "甲公司",
  "display_name": "甲公司",
  "locale": "zh-CN",
  "timezone": "Asia/Shanghai"
}
```

返回 201、Location 和 `status=provisioning`。创建不自动生成万能管理员账号，也不强制创建根 Department；Owner Account/TenantMember 由后续显式步骤建立并审计，ProductProfile 可在独立幂等步骤按配置创建默认根部门。

### 建立首个 Owner candidate

`POST /api/platform/v1/tenants/101/owner-candidates`

新 Account：

```json
{
  "email": "owner@example.test",
  "display_name": "租户负责人",
  "initial_password": "<request-only-secret>"
}
```

邮箱已存在时禁止提交 `initial_password`，只允许复用精确匹配的 Account：

```json
{
  "email": "existing-owner@example.test",
  "display_name": "租户负责人"
}
```

该端点只在 Tenant 为 `provisioning` 且还没有 active owner 时可用，返回 201 和 `member.status=pending`。密码不得进入响应、日志、审计 payload 或幂等响应缓存。邮箱冲突不得修改已有 Credential，也不得返回该 Account 的其他租户关系。

创建 candidate 时必须锁定 Tenant row，并在同一事务创建 pending TenantMember 和内置 `core.tenant-owner` 关系；pending 状态不产生登录或授权。并发创建第二个 pending/active owner candidate 返回 409。

`POST /api/platform/v1/tenants/101/owner-candidates/501/activate` 必须携带 Idempotency-Key、`change_reason` 和候选 ETag。它按 G-01 重新验证 owner role 后返回 active TenantMember。Tenant 的 activate 端点必须再次验证至少一个 active owner，不能把 owner candidate 创建和 Tenant 激活合并成一个动作。

### 开通 TenantModule

`PUT /api/platform/v1/tenants/101/modules/example.work-item`

```json
{
  "status": "enabled",
  "source": "manual",
  "config": {
    "allow_archive": true
  },
  "effective_at": null,
  "expires_at": null
}
```

必须携带 Idempotency-Key；后端按 G-04 执行依赖、schema、enable hook 和审计，不能直接 update 表。

## 11. 成员、部门和角色示例

### 创建 Department

`POST /api/v1/departments`

```json
{
  "code": "operations",
  "name": "运营部",
  "parent_id": null,
  "sort_order": 10
}
```

`tenant_id` 不在请求体；后端从 TenantContext 写入。

### 更新成员

`PATCH /api/v1/members/501`

```http
If-Match: "rev-4"
```

```json
{
  "display_name": "张三",
  "primary_department_id": "9"
}
```

跨租户 department 返回 404 并记录 `AUTHZ_TARGET_TENANT_MISMATCH`。

### 分配角色

`PUT /api/v1/members/501/roles`

```json
{
  "role_ids": ["20", "21"]
}
```

该操作是“把当前角色集合替换为给定集合”，必须校验全部 Role 同 Tenant，递增 authorization revision 并返回最新角色集合。

### 角色权限

`PUT /api/v1/roles/20/permissions`

```json
{
  "permission_keys": [
    "core.member.read",
    "example.work-item.read"
  ]
}
```

未开通 Module 的 Permission、retired Permission 和 platform scope Permission 均返回 422/403。

## 12. 数据权限 API 示例

`PUT /api/v1/roles/20/data-policies/example.work-item/list`

```json
{
  "status": "active",
  "reason": "运营人员查看本部门及指定协作项目",
  "groups": [
    {
      "name": "本部门及下级",
      "conditions": [
        { "condition_key": "core.department_tree" }
      ]
    },
    {
      "name": "指定协作对象",
      "conditions": [
        {
          "condition_key": "core.specified_objects",
          "target_set": {
            "name": "重点项目",
            "target_resource_key": "example.project",
            "targets": [
              { "target_id": "9001" },
              { "target_id": "9002" }
            ]
          }
        }
      ]
    }
  ]
}
```

后端必须验证 condition/selector 由 manifest 允许、TargetSet 只有一个类别、全部目标存在且当前 Tenant 可引用，并原子写入 Policy/Group/Condition/TargetSet/Target。

### Typed target 输入

普通写操作使用单个主要边界目标：

```json
{
  "target": {
    "target_resource_key": "example.project",
    "target_id": "9001"
  },
  "title": "检查合同"
}
```

`one_required` 不接受缺少 target、`target_ids` 数组或两个 primary target。多目标读使用按类别分组的结构：

```json
{
  "targets": [
    {
      "target_resource_key": "example.project",
      "target_ids": ["9001", "9002"]
    }
  ]
}
```

一个 set 不能同时包含 Project 和 Queue；operation 需要多个类别时使用多个 typed set。HTTP GET 可把上述结构编码为 OpenAPI 明确的 repeated query parameters，但语义和验证结果必须相同。

### 可授权目标候选

`GET /api/v1/authorization/target-candidates?resource_key=example.work-item&operation=list&target_resource_key=example.project&q=重点&page=1&page_size=20`

运行时模式不使用独立“看全租户目标”权限。后端先校验当前 `resource_key + operation` 的 Module、Permission 和 DataPermission，再委托对应 TargetCatalogProvider 返回最小摘要：

```json
{
  "data": [
    { "target_resource_key": "example.project", "target_id": "9001", "label": "项目甲" },
    { "target_resource_key": "example.project", "target_id": "9002", "label": "项目乙" }
  ],
  "meta": {
    "request_id": "req_01K...",
    "target_cardinality": "many_readable",
    "available_count": 2,
    "total": 2
  }
}
```

配置 Role DataPolicy 时使用 `mode=policy-config`，必须同时具有 `core.role.data-policy.manage` 和 operation target type 声明的 `policy_selection_permission`。这项权限只允许查询可委派候选，不授予对应业务读写；候选查询始终分页，不能先返回全部目标给前端过滤。

### 受数据权限过滤的列表

`GET /api/v1/example/work-items?page=1&page_size=20&status=active&sort=-created_at&target_resource_key=example.project&target_id=9001&target_id=9002`

服务端先应用 Tenant/DataPermission，再应用用户筛选和排序。`sort` 只能使用 OpenAPI operation 明确 allowlist 的字段。

当授权集合只有一个 Project 时，响应 `meta.target_scope.mode=single`；可读取多个 Project 时返回 `mode=multiple`、目标数量和 stable digest。列表行在 multiple 模式下必须返回 `boundary_target` 最小摘要，避免用户不知道数据归属；摘要仍受授权过滤。

### 统一共享主档候选

`GET /api/v1/example/reference-items/candidates?target_resource_key=example.project&target_id=9001&q=标准`

Reference Module 在一个 ReferenceItem 主档中同时查询部署种子和 Tenant 自建记录，再按 ownership/visibility/usage scope 返回一个候选列表和一套稳定 ID。API 不暴露来源表，不返回两组列表，也不让前端执行 UNION。创建 Tenant 自有 ReferenceItem 后，其初始作用范围可以只覆盖 Project 9001。

### 越权详情

`GET /api/v1/example/work-items/9002`

```json
{
  "type": "/docs/problems/resource-not-found",
  "title": "Resource not found",
  "status": 404,
  "detail": "The requested resource does not exist or is not accessible.",
  "instance": "urn:request:req_01K...",
  "code": "AUTHZ_DATA_DENIED",
  "request_id": "req_01K..."
}
```

真实原因只进入审计。

## 13. 幂等契约

以下操作必须携带 `Idempotency-Key`：

- POST create 和明确业务 command。
- 批量、导入、导出和异步任务创建。
- Tenant/Module enable、disable、upgrade 等平台动作。
- 任何支付、库存等外部业务 Module 的有副作用命令。

规则：

- key 为 16-128 个安全 ASCII 字符，推荐 UUID/ULID。
- 租户作用域：`tenant_id + member_id + method + route_operation_id + key`。
- 平台作用域：`operator_id + method + route_operation_id + key`。
- 保存包含 typed target descriptors 的 canonical request hash。
- 同 key、同 hash：processing 返回 409/Retry-After，completed 重放原 status/body。
- 同 key、不同 hash：返回 `IDEMPOTENCY_KEY_REUSED` 409。
- 默认保留 24 小时；业务 Module 可声明更长但不能更短于其重试窗口。
- 登录、refresh 和返回 secret/token 的响应不得进入通用幂等响应存储。

### `pa_tenant_idempotency_record`

| 字段 | 类型 | 规则 |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL |
| `tenant_member_id` | BIGINT UNSIGNED | NOT NULL，同 Tenant |
| `operation_key` | VARCHAR(160) | OpenAPI operationId |
| `idempotency_key_hash` | CHAR(64) | key 的 SHA-256；不保存或记录原始 key |
| `request_hash` | CHAR(64) | canonical method/path/body hash |
| `status` | VARCHAR(16) | `processing/completed/failed` |
| `response_status` | SMALLINT UNSIGNED | NULL until completed |
| `response_body_json` | JSON | NULL；禁止 secret/大文件 |
| `resource_type/resource_id` | VARCHAR | 可选结果引用 |
| `expires_at` | DATETIME(3) | NOT NULL |
| `created_at/updated_at` | DATETIME(3) | NOT NULL |

唯一约束：`(tenant_id, tenant_member_id, operation_key, idempotency_key_hash)`。

平台使用独立 `pa_platform_idempotency_record`，不使用 nullable tenant_id 模拟平台记录。

## 14. 排序、筛选和关联

- `sort=-created_at,name`；`-` 表示降序。
- 每个 operation 在 OpenAPI 列出允许排序字段；未知字段返回 422。
- 筛选参数使用明确名称，如 `status`、`department_id`，不提供任意 `field/operator/value` DSL。
- 前端搜索条件只能收窄 G-03 数据范围。
- 目标筛选必须同时提供 target_resource_key；重复 target_id 只表示该类别内的子集，不能改变 operation cardinality。
- 关联默认返回 `department_id`、`role_ids` 等稳定 ID；需要摘要时使用明确 `include=department` allowlist。
- 不允许客户端通过 include 请求任意 ORM 关系。

## 15. Batch

P0 通用批量请求最多 100 个目标：

```json
{
  "ids": ["1", "2", "3"],
  "action": "archive"
}
```

- 必须有独立 batch Permission/operation 和 Idempotency-Key。
- 普通 batch 中全部资源必须解析到同一个 primary boundary target；跨目标写必须使用独立 `bulk_write` operation，P0 默认拒绝。
- 默认 all-or-nothing；任一目标无权限整批拒绝。
- 返回 200 和统一结果，或 202 异步 operation。
- 部分成功必须定义独立 endpoint/schema，逐项响应不能泄露越权目标。

## 16. OpenAPI 事实源和类型生成

P0 固定目录：

```text
docs/api/openapi.yaml              # OpenAPI 3.1.2 根事实源
docs/api/paths/*.yaml              # 按 Kernel/Module 拆分
docs/api/schemas/*.yaml            # DTO/schema
docs/api/problems/*.md             # Problem type 人类说明
packages/web/admin-core/src/generated/api.d.ts
```

规则：

- OpenAPI 3.1.2 是当前选定版本；3.2.0 虽已发布，但要等生成/校验工具链完整支持后单独升级。
- `docs/api/openapi.yaml` 及其 `$ref` 文件是 API schema 事实源。
- 使用成熟 `openapi-typescript` 生成 TypeScript types，使用 `openapi-fetch` 作为 typed client 基础。
- `generated/api.d.ts` 禁止手工修改，CI 重新生成后必须无 diff。
- `packages/web/admin-core` 在 generated client 之外只增加 auth refresh、request ID、Problem Details 和业务无关拦截器。
- Backend route/DTO/validator 必须有 contract test 对照 operationId、method、path、request/response schema。
- 文档站从同一 OpenAPI 渲染，不另写一份会漂移的接口手册。
- Module API 在自己的 paths/schemas 文件中声明，由 root 显式引用；禁止运行时扫描任意 YAML。

## 17. G-05 验收场景

1. Tenant token 不能访问 Platform path，反向同样失败。
2. Tenant API 请求体里的 tenant_id 被拒绝或忽略，不能改变 Context。
3. 所有 BIGINT ID schema 为 string。
4. 所有错误 Content-Type 为 application/problem+json。
5. 验证错误有 JSON Pointer。
6. 401 不泄露账号不存在、锁定或密码错误的差异。
7. 单对象无权限与不存在均返回相同 404 外观。
8. 更新缺少 If-Match 返回 428。
9. revision 冲突返回 412，不覆盖新数据。
10. page_size 超过 100 返回 422。
11. 未 allowlist 的排序/筛选字段返回 422。
12. include 不能展开任意 ORM 关系。
13. Idempotency 同 key 同 payload 返回原结果。
14. Idempotency 同 key 不同 payload 返回 409。
15. Auth token 响应不进入 idempotency store。
16. Batch 超过 100 拒绝。
17. Batch 任一越权默认整批拒绝。
18. TenantModule enable 经过 G-04，不直接 update 表。
19. DataPolicy payload 不能提交 SQL/字段名/未知 condition。
20. Data list 的用户筛选不能扩大授权范围。
21. OpenAPI 生成 types 后工作区无 diff。
22. Backend route 和 OpenAPI operationId 一一对应。
23. owner candidate 新邮箱密码只被哈希，不进入日志、审计、响应或幂等 response body。
24. owner candidate 已有邮箱不允许覆盖 Credential，也不返回其他租户关系。
25. 并发建立首个 owner 只有一个成功，Tenant 激活前必须存在 active owner。
26. 最后一个 active owner 不能 leave、suspend 或丢失 `core.tenant-owner`。
27. Tenant close 和 Operator close 使用终态、撤销会话并保留审计，不执行级联删除；最后一个有效平台管理员不能被停用或关闭。
28. Problem type URI 在文档站可访问。
29. request_id 在响应、日志和审计中一致。
30. `one_required` 写请求缺目标、目标类型错误或携带多个 primary target 时返回 422。
31. 多目标读只接受 operation 声明的 target type，且请求集合只能收窄当前授权集合。
32. TargetSet payload 把 Project 和 Queue 混在一组时返回 `AUTHZ_TARGET_TYPE_MISMATCH`。
33. target-candidates 只返回当前 operation 可选目标，零/单/多结果均分页且不泄露未授权名称。
34. multiple target list 每行返回 boundary_target 摘要；single 模式可以省略展示但后端响应仍可追溯。
35. aggregate endpoint 只有读 schema，没有复用普通 update/batch endpoint。
36. 普通 batch 混入两个 primary target 时拒绝，不因每个资源单独有权就放行。
37. shared_master candidates 将部署种子和 Tenant 自建记录返回为一个 ID 空间，不暴露表来源或双列表。
38. shared_master 当前目标不可用时返回统一 404/403，不允许靠已知 ID 引用。
39. 平台 token 不能调用 target-candidates 或 shared_master 租户业务 endpoint。
40. 审计记录 actor tenant、target tenant、primary boundary target 或多目标 digest。
41. 没有 policy_selection_permission 的角色管理员不能通过 policy-config 候选接口枚举 Module 业务目标。

## 18. G-05 结论

Peanut Admin API 不再继承“所有结果 HTTP 200 + 自定义 code”的旧后台习惯。成功响应保持简单，错误采用 RFC 9457，接口 schema、文档和前端类型共用同一份 OpenAPI 3.1.2 事实源。

租户隔离仍由 Session/TenantContext 决定；URL 中出现一个 ID、筛选条件或关联对象，只是待验证输入，不能改变授权边界。目标类别、目标数量和目标集合由 ResourceOperation/OpenAPI 明确表达，再由 Provider 重新授权。

共享主档 API 对外只提供一个候选集合和稳定 ID。部署共享记录、Tenant 自建记录以及未来其他归属记录的差异由 Module 的 ownership/scope contract 表达，不通过两套 endpoint、两张前端列表或客户端 UNION 表达。
