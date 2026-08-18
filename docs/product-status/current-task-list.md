# 当前任务清单

> 更新时间：2026-08-18
>
> 本页是当前开发工作的人工阅读入口。能力的机器状态仍以
> [`capability-ledger.json`](./capability-ledger.json) 为准；这里记录下一步要做什么、
> 已经做到哪里，以及哪些旧材料不应再作为工作依据。

## 当前结论

Peanut Admin `v2.0.1` 已完成正式源码发布，脚手架可以被派生应用消费。当前候选仍在
模块资格收口，已通过的 Gate 不重复运行。

当前仍未完成的产品交付线有两条，另有一条模块通用合同仍在推进：

1. 从不可变 `v2.0.1` Tag 完成一套 Standalone 和一套 Multi-tenant 线上部署，并分别完成
   备份、迁移、TLS、健康检查和最小浏览器验收。
2. 把官方 Module 的多租户执行合同补齐到 HTTP、内部命令、任务/worker、外部回调和模块
   文件入口。
3. Article 的真实数据库、Tenant A/B 隔离、停用负向和页面专项资格已在当前候选完成，
   但仍需随代码候选合入后作为已发布能力重新冻结。

## 任务进度

| 编号 | 任务 | 状态 | 当前结果 | 下一步交付物 |
|---|---|---|---|---|
| T01 | 2.0.x fresh-only 脚手架与原生租户身份 | 已完成 | Account、TenantMember、RBAC、TenantContext、双模式 Host 和 canonical Schema 已通过固定资格 | 无 |
| T02 | `v2.0.1` 正式源码发布 | 已完成 | annotated tag、GitHub Release、源码包、发布快照和 P0-E `p0e201r3` 已固定 | 无 |
| T03 | 2.x 派生应用升级 | 已完成 | `v2.0.0 -> v2.0.1` 的 preflight/apply/verify/recover 通过，app-owned 文件保持 | 后续版本各做一次受影响升级资格 |
| T04 | 头像 fallback 与共享浏览器验收 | 已完成 | 空值/加载失败 fallback、用户菜单、共享 Admin/Tenant A/Tenant B 截图人工检查通过 | 无 |
| T05 | 文档与实现事实对照 | 已完成 | 当前版本、部署边界、Module 缺口和历史材料已重新分类 | 本页和公开入口同步 |
| T06 | 文档状态收口 | 已完成 | 当前入口已统一到 v2.0.1；历史证据保留，docs-site 构建通过 | 无 |
| T07 | Module 统一执行授权合同 | 部分完成 | HTTP 管理/公开入口已接入；定时任务要求命令声明 `module_key` 并在执行前复核 Tenant/Module 状态；Core 导入导出 worker 已通过 `ModuleAwareTaskHandler` 在 handler 前复核；支付/公众号 Core 回调已改为验签后执行 `core` Guard；通用业务 worker envelope、业务 Module 回调、模块文件入口仍待迁移 | 将同一合同继续采用到业务 worker、业务 Module 回调和模块专属文件入口，并补齐停用负向 |
| T08 | Article 官方 Module 专项资格 | 已完成（候选） | 当前候选已完成真实数据库安装、Tenant A/B 页面与数据隔离、停用负向和共享 Admin/Tenant A/Tenant B 浏览器截图；证据见 `output/playwright/article-module/b0ef43d/summary.json` | 随候选合入后冻结为发布快照；不把它扩大为通用 Module 全入口合同 |
| T09 | v2.0.1 线上 Standalone 部署 | 未开始 | 发布脚本和资源登记已具备，尚未对线上目标执行 | fresh 部署、备份、TLS、smoke 和访问交付 |
| T10 | v2.0.1 线上 Multi-tenant 部署 | 未开始 | 多租户本地候选已通过，线上目标尚未执行 | fresh 部署、域名/Host、Tenant A/B smoke 和访问交付 |
| T11 | DCS 业务模块 | 范围外 | Peanut 只提供扩展边界；Party、Product、Inventory、Procurement 等不属于本仓 Runtime | 在 DCS 仓库按 Module 合同实现 |
| T12 | 跨应用运营平台 | 独立项目 | 已授权独立立项，不进入 Peanut Admin Runtime | 在独立仓库推进 |
| T13 | 完整 SaaS 商业化 | 暂缓 | 套餐、订阅、计费、应用市场不属于当前交付 | 等运营闭环和真实消费者成立后再立项 |

## 旧信息处理建议

| 材料 | 处理方式 | 原因 |
|---|---|---|
| `docs/product-status/releases/*.json` | 保留 | 这是不可变发布和资格证据，不是当前待办 |
| `output/`、固定候选 summary、P0-E 证据 | 保留但不放公开导航 | 需要追溯验证，不应被当成当前 Runtime |
| `docs-site/` 当前入口中的 v1.x 状态 | 移出当前结论，保留历史链接 | 旧版本可追溯，但会干扰 v2.0.1 使用判断 |
| `docs/design/saas-roadmap/` | 保留为路线资料，明确 roadmap-only | 其中部分设计未实现，不能当作现有能力 |
| `docs/2026-08-05-*` 等审查 prompt/过程稿 | 可移到内部 archive 或删除 | 不是产品使用说明，也不是运行时合同 |
| 旧 v1.x upgrade/adopt/runbook | 不进入当前导航，迁移需求成立时单独恢复 | 当前 2.0.x fresh-only 不支持原地接管 |
| 当前文档中把 `2.0.0` 写成“最新版本”的句子 | 立即修正 | 当前正式源码版本是 `v2.0.1`；`2.0.0` 只作为 fresh 基线和升级起点 |

## 不作为当前阻塞的事项

- GitHub Actions 配额或历史 CI 状态：当前正式资格以固定本地 P0-E 和发布快照为证据。
- DCS 具体业务 Runtime：属于派生应用，不阻塞 Peanut Admin 脚手架。
- 跨应用身份联邦、通用 Outbox/Event Bus 和 SaaS 商业化：均未达到当前消费者条件。
- 1.x 历史迁移结构：新装 Runtime/Schema 不携带，但历史证据不删除。

## 下一阶段顺序

1. 完成本次文档状态收口并构建 docs-site。
2. 将 Article 候选证据随代码候选合入并冻结；继续补齐 Module 非 HTTP 入口合同。
3. 按资源登记执行线上 Standalone 与 Multi-tenant 部署。
4. 用实际部署结果更新本页、能力账本和发布交付说明。
