# Module 执行上下文合同

## 结论

Module 不重新认证账号，也不从请求参数推断租户。应用入口先建立可信的 Tenant、会员或系统
上下文，Module 再使用统一执行合同检查：

```text
上游身份/Host 边界
  -> ModuleExecutionContext
  -> ModuleInstallation
  -> TenantModule
  -> 成员 RBAC（需要管理成员时）
  -> 领域数据权限与业务规则
```

`tenant_id` 不接受前端提交值覆盖；`ExecutionContextStore` 是运行时唯一 Tenant
权威，不同 Tenant 上下文不得嵌套。Repository 和 ThinkORM global scope 只从当前
`CurrentExecutionContext` 写入或添加租户条件；旧合同中保留的显式 context 只能作为
身份凭据，与当前执行 Tenant 不一致时必须拒绝。

Platform 控制面需要跨 Tenant 发现数据时，只能走会留下操作记录的
`PlatformTenantDataGateway`；这个显式 gateway 不改变普通 Module/ORM 的 Tenant scope。

## 应用执行上下文

应用入口按受众建立且只建立一种上下文；这些类型不是可互换的 DTO：

| 类型 | 适用入口 | Tenant |
|---|---|---|
| `AdminExecutionContext` | 已选择 Tenant 的管理端请求 | 必须有 |
| `ConsumerExecutionContext` | 会员、站点公开访问或明确的匿名访问 | 可有 |
| `PlatformExecutionContext` | 平台运营控制面 | 无；目标 Tenant 只是命令参数 |
| `SystemExecutionContext` | 已验签回调、worker、定时任务 | 必须有 |
| `InstallationExecutionContext` | 一次性安装流程 | 无 |
| `InstanceExecutionContext` | 实例级 CLI 和维护任务 | 无 |

`ExecutionContextStore` 只负责在入口建立上下文，并在 `finally` 中恢复；未明确允许的受众切换
或 Tenant 不匹配时 fail-closed。业务代码通过 `CurrentExecutionContext` 取得强类型上下文，类型
不匹配立即失败。只需要读取身份的基础设施依赖 `ExecutionContextAccess`，避免获得建立或切换
上下文的能力。新增子目录或别名不会强化这个边界，因此这些类保持同目录，由上述职责区分。

## 四种入口

| 入口 | 上下文工厂 | 是否检查成员 RBAC |
|---|---|---|
| Tenant Admin HTTP | `ModuleExecutionContext::admin()` | 是 |
| 业务会员/公开 API | `businessMember()` 或 `system()` | 业务会员使用自己的业务权限；公开读取不使用管理 RBAC |
| 定时任务/worker | `scheduled()` | 使用任务提交时的授权和执行前状态复核 |
| 外部回调 | `system()` | 先完成 Provider 验签和 Tenant 绑定，再检查 Module |

每种上下文都必须带 Module key、Tenant、操作名和操作 ID。模块停用后，新操作必须被拒绝。

## 当前代码采用情况

- `ModuleExecutionContext` 和 `ModuleExecutionGuard` 已加入应用 Runtime。
- 新 Tenant 的 Module 自有默认值由 bootstrap 建立 Tenant system execution context 后调用
  数据 owner 的 Commands；bootstrap 不直接写入其他 Module 的表。
- Fixture `delivery-record` 已使用统一的管理成员执行合同。
- Article 管理入口和公开入口已使用统一的安装/Tenant 开通检查；Repository 的原有 Tenant
  条件仍保留，作为数据层防线。
- 当前随仓库交付的 Module 没有业务专属 worker、外部回调或模块文件入口：Article 和
  Fixture 的实际入口均已覆盖，Core 的任务、回调、素材和导出仍由应用 Host 自己负责。
  因此当前没有漏迁移的业务入口；以后某个 Module 新增这些入口时，必须在自己的
  provider/handler/controller 装配处采用同一合同，并补启用正向与停用负向证据。

## Module 与业务能力的关系

共享 `TenantContext` 不会抹平模块能力。模块是否安装、某个 Tenant 是否开通、成员能做什么、
业务对象属于谁，仍是四个独立判断。DCS 的 Product、Pricing、Inventory、Procurement 和
Trade 必须各自声明依赖、权限、自有表和数据范围；跨 Tenant 协作通过显式 participant grant、
合同或投影，不通过切换 TenantContext 获取对方数据权限。
