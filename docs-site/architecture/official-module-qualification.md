---
title: 官方模块多租户资格
description: 官方可选 Module 必须满足的 Tenant 隔离、权限、生命周期和审计门禁。
---

# 官方模块多租户资格

## 结论

“可选模块”只表示可以安装或关闭，不表示可以用单租户实现。任何进入 Peanut Admin 官方模块目录的
Module 都必须通过同一套 Tenant 资格；不满足就只能留在派生应用或示例模板。

## 必须满足的门禁

每个 `module.json` 必须声明：

- `tenant.enableable: true`，允许逐 Tenant 开通和停用；
- `tenant.disable_behavior: reject_new_operations`，停用后拒绝新操作；
- `tenant.requires` 依赖列表，即使为空也必须显式存在；
- 后端 provider、权限目录和自有表清单；
- 所有菜单、API、任务和回调的 Tenant audience 与权限；
- 私有表、文件、缓存、锁、导入导出和异步任务的 Tenant owner/namespace。

运行时还必须同时命中“部署已安装”与“当前 Tenant 已启用”两个检查。只在菜单上隐藏、只在前端传
`tenant_id`、或只给表增加一个可空字段，都不算适配。

## 能力逐项要求

| 能力 | 强制要求 |
| --- | --- |
| 身份/成员/RBAC | 使用可信 TenantContext；Permission 与数据条件分开；不能复用 PlatformRole |
| 文件/素材 | 对象键带 Tenant namespace；下载、删除和异步导出都复核 Tenant |
| 通知/OAuth/支付 | Provider binding 必须关联 Tenant；回调先验签再解析业务 Tenant；幂等和审计不跨租户 |
| 任务/定时任务 | 调度记录、锁、运行上下文和重试都带 Tenant；执行前重新检查 Tenant 状态 |
| 导入/导出 | 文件、字段映射、任务和结果只属于发起 Tenant；批量操作不能跳过权限 |
| 文章/CRM | SQL 所有权、状态、搜索和缓存都按 Tenant 隔离；客户档案不等于 Account |
| 跨模块调用 | 通过公开命令、查询 DTO 或版本化事件；禁止读写对方私有表 |

## 当前仓库状态

- **当前已支持**：Core 身份、TenantMember、RBAC、审计、Module 生命周期及现有文件、通知、OAuth、支付、会员、任务、导入导出和文章 Runtime 的 Tenant 资格检查。
- **推荐新增**：把已经验证的应用 Runtime 按稳定 manifest 拆成官方可选模块，并为每个模块建立独立 Provider、版本、安装/停用和浏览器验收。
- **示例模板**：`fixture.delivery-record` 用于演示表、合同、权限、菜单和前端 contribution，不进入生产默认 lock。
- **暂不建议**：DCS 的 Party、Store、Warehouse、Supplier Relationship、Product、Pricing、Inventory、Procurement、Trade；这些属于派生应用。
- **仅迁移需要**：legacy Admin/Role/Dept 映射、双写、镜像和旧 bootstrap；不允许出现在新装 Runtime。

仓库资格测试会扫描随产品交付的 `module.json`，并拒绝缺失 Tenant 声明的模块。具体领域模块仍需在
DCS 或其他派生应用中重复这套门禁。
