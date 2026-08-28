# 产品闭环维护窗口与写入门禁

Document ID: `pa-docs-architecture-product-closure-maintenance-gate`

Status: `current`

Owner: `product-operations`

Audience: `maintainer, architect, ai`

Upstream: `peanut-admin/core@0.1.0-alpha.9` 的公开 Ops Console 维护合同、
`server/app/platform/` Host 与 `pa_ops_maintenance_window` KernelSchema。

## 决定

`PC40` 采用 Core 的 `MaintenanceService` 和 `MaintenanceWindowStore` 公共边界。
Application 只拥有 PDO Store、ThinkPHP transport、Platform 页面装配，以及 HTTP 写入门禁；
不依赖 Core `backend/` 参考 Host，也不复制前端包页面。

维护窗口以允许的 `reason_key`、UTC `starts_at` / `ends_at`、revision 和 idempotency key
创建。一个未关闭的窗口阻止再次计划；关闭和计划都在同一 Store 事务内完成持久化和
Platform 审计。`If-Match: "rev-<revision>"` 实现 revision fencing，Core 合同校验时间范围
最多 24 小时。

## 后端门禁

全局 `MaintenanceWriteGateMiddleware` 在每一个 HTTP `POST`、`PUT`、`PATCH`、`DELETE`
之前查询当前 UTC 时间内生效的窗口。窗口生效时，所有这类请求统一返回
`503 MAINTENANCE_WRITE_BLOCKED`，并将窗口 key、原因、方法和路径作为 denied Platform
审计事件记录。

唯一的精确例外是：

- `PUT /api/platform/v1/ops/maintenance`
- `POST /api/platform/v1/ops/maintenance/{maintenance_key}/close`

这两个路由仍须通过 Platform host、独立会话和
`platform.ops.maintenance.manage` 权限。没有按前缀、菜单、客户端或身份设置额外旁路；
页面隐藏不构成门禁。维护状态或审计写入不可用时，门禁以
`503 MAINTENANCE_GATE_UNAVAILABLE` 失败关闭。

## 数据和迁移

本切片没有应用自有 migration。`pa_ops_maintenance_window` 和
`platform.ops.maintenance.manage` 已由锁定 Core KernelSchema / Permission Catalog 拥有，
PC20 已经把前者作为只读状态输入。PC40 仅将既有表从只读投影升级为公共 Store 的应用
Adapter，不增加第二份状态、镜像或兼容表。

## 验证责任

PC40 的 Runtime 验收应覆盖：计划、idempotent replay、revision conflict、关闭、活跃时间窗
内 Admin、API、Module 与 Platform 非控制写路由均被拒绝，且每次拒绝留下 Platform 审计。
该候选只做静态审查、精确写集与差异检查；运行时验收属于后续集成 owner。
