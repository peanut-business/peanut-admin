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

`tenant_id` 不接受前端提交值覆盖；Repository 必须从执行上下文写入或添加租户条件。

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
