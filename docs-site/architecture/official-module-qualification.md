---
title: 官方模块多租户资格
description: 官方可选 Module 必须满足的 Tenant 隔离、权限、生命周期和审计门禁。
---

# 官方模块多租户资格

## 结论

“可选模块”只表示可以安装或关闭，不表示可以用单租户实现。任何进入 Peanut Admin 官方模块目录的
Module 都必须通过同一套 Tenant 资格；不满足就只能留在派生应用或示例模板。

## 5 分钟判断

一个能力只有同时回答“是”，才能叫官方可选 Module：

| 问题 | 必须答案 |
| --- | --- |
| 部署是否以不可变 Plugin 身份安装？ | 是 |
| 是否能逐 Tenant 开通和停用？ | 是 |
| 后端每个入口是否检查 TenantModule、成员权限和数据范围？ | 是 |
| 表、文件、缓存、锁、任务和回调是否都有 Tenant owner？ | 是 |
| 停用后，HTTP、内部命令、worker 和回调是否都拒绝新操作？ | 是 |
| 是否有两个 Tenant 的正向与越权测试？ | 是 |

只有“表里有 `tenant_id`”或“前端隐藏了菜单”，答案仍然是否。

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

| 能力 | 强制要求 | 最低验收示例 |
| --- | --- | --- |
| 身份/成员/RBAC | 使用可信 TenantContext；Permission 与数据条件分开；不能复用 PlatformRole | 同一 Account 在 Tenant A/B 得到不同权限，平台 Token 调租户 API 被拒绝 |
| 文件/素材 | 对象键带 Tenant namespace；下载、删除和异步导出都复核 Tenant | Tenant A 猜测 Tenant B 文件 ID 仍返回拒绝/不存在 |
| 通知/OAuth/支付 | Provider binding 关联 Tenant；回调先验签再解析业务 Tenant；幂等和审计不跨租户 | 相同外部单号不能串到另一 Tenant，伪造签名不进入状态机 |
| 任务/定时任务 | 调度记录、锁、运行上下文和重试都带 Tenant；执行前重新检查 Tenant/Module 状态 | Module 停用后已排队任务不会继续执行写操作 |
| 导入/导出 | 文件、字段映射、任务和结果只属于发起 Tenant；批量操作不能跳过权限 | Tenant B 无法下载 Tenant A 的导出结果或复用任务 ID |
| 文章/CRM | SQL 所有权、状态、搜索和缓存都按 Tenant 隔离；客户档案不等于 Account | 相同业务 ID 在两个 Tenant 中查询互不污染 |
| 跨模块调用 | 通过公开命令、查询 DTO 或版本化事件；禁止读写对方私有表 | 调用方测试只依赖 `Contracts/`，扫描或测试拒绝私有 Repository 依赖 |

## 当前仓库状态

- **当前已支持**：Core 身份、TenantMember、RBAC、审计、Plugin 安装与 TenantModule 治理；
  原生菜单/RBAC 会交集 ModuleInstallation、TenantModule 和成员权限；源码 fixture 的同步业务命令
  也会在授权后再次检查 ModuleGuard。
- **当前只是 Tenant 适配，不是可选 Module**：文件、通知、OAuth、支付、会员、任务、导入导出
  Runtime 已按 Tenant 隔离，但仍由应用 Host 直接拥有，不能从“已隔离”推导为“已可安装/
  可停用”。共享文件基础设施也不应因某个业务 Module 停用而整体关闭。
- **进行中的首个候选**：Article 已形成固定功能提交，加入 manifest、权限/菜单目录、管理端
  contribution、ModuleInstallation/TenantModule Guard、Host 绑定解析和会员收藏公开查询合同；
  PC/UniApp 静态停用合同、Plugin/Module 和 Web 类型检查已通过。它仍缺真实数据库、浏览器、
  因此只能写成“随 v2.0.1 交付基础合同、专项资格仍在进行”的官方 Module，不能把 Article 页面资格写成已完成。
- **推荐新增**：把候选能力按稳定 manifest 拆成官方可选 Module，并逐一补齐 HTTP/内部命令、
  入队和 worker 执行、外部回调、模块专属文件入口的统一 Guard，以及独立 Provider、版本和浏览器验收。
- **示例模板**：`fixture.delivery-record` 用于演示表、合同、权限、菜单和前端 contribution，不进入生产默认 lock。
- **暂不建议**：DCS 的 Party、Store、Warehouse、Supplier Relationship、Product、Pricing、Inventory、Procurement、Trade；这些属于派生应用。
- **仅迁移需要**：legacy Admin/Role/Dept 映射、双写、镜像和旧 bootstrap；不允许出现在新装 Runtime。

仓库资格测试会扫描随产品交付的 `module.json`，并拒绝缺失 Tenant 声明的模块。具体领域模块仍需在
DCS 或其他派生应用中重复这套门禁。

## 会阻断后续模块的未完成链

| 缺口 | 已经能做什么 | 为什么阻断下游 | 完成条件 |
| --- | --- | --- | --- |
| Module HTTP/内部命令统一入口 | fixture 同步命令已使用 ModuleGuard | root、system actor 或无权限型命令不能只依赖菜单/RBAC | 正式 Module 的每个入口命中同一 Guard，并有停用负向测试 |
| 模块任务与定时任务 | 共享调度器已有 TenantContext 和 Tenant active 检查 | 任务 envelope 没有通用可信 `module_key`，停用后执行前无法统一复核 | 提交与执行前都复核 ModuleInstallation/TenantModule |
| 模块外部回调 | 现有支付/OAuth 等 Host 已做 Tenant 路由 | 这些不是可停用 Module，不能证明模块回调会在停用后拒绝 | 可信绑定携带 module key，验签后、处理前 Guard |
| 模块专属文件入口 | 共享上传/素材已有 Tenant namespace | 共享基础设施不能整体关闭，模块下载/上传仍需自己的入口 Guard | 模块 Controller 在调用共享文件服务前 Guard |
| 两 Module 可运行示例 | Contracts 目录和装配模式存在 | 不能证明真实跨模块 DTO/事务/失败合同 | 两个最小 Module 的命令、查询和禁止直表测试 |

这些缺口不会阻塞 Core、Platform 或现有应用 Host 的 Tenant 隔离，但会阻塞任何能力被宣称为
“正式、可停用的官方可选 Module”。
