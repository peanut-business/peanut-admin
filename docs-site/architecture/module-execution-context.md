---
title: Module 执行上下文合同
description: Module 如何承接 Tenant 身份、模块开通、成员权限和各类入口边界。
---

# Module 执行上下文合同

Module 不重新认证账号，也不从请求参数推断租户。应用入口先建立可信的 Tenant、会员或系统
上下文，Module 再依次检查安装、Tenant 开通、成员权限和领域数据范围。

```text
身份/Host 边界 -> ModuleExecutionContext -> ModuleInstallation -> TenantModule
                -> 成员 RBAC（需要时） -> 领域数据权限与业务规则
```

`tenant_id` 不接受前端提交值覆盖；Repository 必须从执行上下文写入或添加租户条件。

| 入口 | 上下文 | 说明 |
|---|---|---|
| Tenant Admin HTTP | `admin()` | TenantMember 身份和 RBAC 必须同时成立 |
| 业务会员/公开 API | `businessMember()` / `system()` | 公开读取不使用管理 RBAC，但仍必须绑定 Tenant 和 Module |
| 定时任务/worker | `scheduled()` | 提交与执行前都要复核 Tenant 和 Module 状态 |
| 外部回调 | `system()` | 先验签、解析 Tenant，再检查 Module 是否可用；当前支付/公众号 Core 回调已采用此顺序 |

当前 Fixture Delivery Record、Article 管理入口和 Article 公开入口已采用统一的安装/Tenant
开通合同；Article 的真实数据库、Tenant A/B 隔离、停用负向和页面浏览器专项也已在当前候选
通过。定时任务已要求命令声明 `module_key` 并在执行前复核状态；Core 导入导出 worker
已在 handler 前复核 Tenant 状态。Core TaskJob 的通用任务 envelope、业务模块 worker、
外部回调的可复用 `verifiedModuleCallback()` 已用于支付/公众号 Core Host；业务 Module 的
绑定字段、停用负向和模块文件入口仍在后续资格范围内。

共享 TenantContext 不会抹平 DCS 模块的业务边界。Product、Pricing、Inventory、Procurement
和 Trade 仍需各自声明依赖、自有表、权限和数据范围；跨 Tenant 协作使用显式参与方授权或
投影，不能通过切换 TenantContext 读取对方数据。
