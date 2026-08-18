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

当前 Fixture、Article 以及 7 个官方能力 Module 的 HTTP 入口已采用统一的安装/Tenant
开通合同；Article 已有真实数据库、Tenant A/B 隔离、停用负向和页面浏览器专项，7 个新增
官方 Module 目前只完成源码和静态合同，真实租户资格仍待运行。定时任务、导入导出 worker、
支付/OAuth/公众号回调都必须在实际业务 Module key 下复核状态。未来 Module 若声明新的
worker、回调或专属文件入口，必须在自己的装配处采用同一合同，并补启用正向与停用负向证据。

共享 TenantContext 不会抹平 DCS 模块的业务边界。Product、Pricing、Inventory、Procurement
和 Trade 仍需各自声明依赖、自有表、权限和数据范围；跨 Tenant 协作使用显式参与方授权或
投影，不能通过切换 TenantContext 读取对方数据。

## 当前入口盘点

这张表说明“已接入”与“当前没有消费者”的区别，避免把未来扩展条件误读成现有缺陷：

| 当前随仓 Module/能力 | HTTP/公开入口 | 定时任务/worker | 外部回调 | 专属文件入口 |
|---|---|---|---|---|
| `official.article` | 已接入 | 无 | 无 | 无 |
| `official.file` | 已接入 | 无 | 无 | 共享文件服务由 Tenant namespace 保护 |
| `official.notification` | 已接入 | 无 | 验证码发送由 Tenant context 保护 | 无 |
| `official.oauth` | 已接入 | 无 | OAuth/公众号回调由 resolver + Module guard 保护 | 无 |
| `official.payment` | 已接入 | `refund:reconcile` 在执行前复核 Module | 支付回调验签后复核 Module | 无 |
| `official.member` | 已接入 | 无 | 无 | 无 |
| `official.task` | 已接入 | 调度器在 Tenant scope 内复核 Module | 无 | 无 |
| `official.import-export` | 已接入 | 导出 worker 在 handler 前复核 Module | 无 | 导出文件使用 Tenant namespace |
| `fixture.delivery-record` | 已接入 | 无 | 无 | 无 |

当新 Module 增加后三列中的任一入口时，入口本身才成为该 Module 的必做资格项；不能因为
脚手架目前没有这类消费者，就预先关闭或复制一套全局基础设施。
