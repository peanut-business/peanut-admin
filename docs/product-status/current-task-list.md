# 当前任务清单

> 更新时间：2026-08-18
>
> 本页是当前开发工作的人工阅读入口。能力的机器状态仍以
> [`capability-ledger.json`](./capability-ledger.json) 为准；这里记录下一步要做什么、
> 已经做到哪里，以及哪些旧材料不应再作为工作依据。

## 当前结论

Peanut Admin `v2.1.0` 的固定候选已完成 P0-E 7/7，7 个官方可选 Module 已通过真实数据库、
Tenant A/B、停用负向和浏览器资格；脚手架 `scaffold/releases/v2.1.0` 已封存。当前只剩正式
Release 身份、两套登记部署升级和 post-deployment 快照三个收口动作。跨 Module 可运行示例
保留为后续产品化任务，不阻塞本次发布。

当前仍未完成的产品交付线有一条正式发布收口线：

1. 创建 `v2.1.0` annotated tag 和 GitHub Release。
2. 从 `v2.1.0` 对登记的 Standalone 与 Multi-tenant 目标执行升级，并保留最低部署证据。
3. 以同一 Release、部署目标和无秘密证据封存 post-deployment 快照。
4. 跨 Module 可运行示例和未来新增 worker/回调/专属文件入口按 Module 合同继续推进，
   不阻塞当前 Release。

## 任务进度

| 编号 | 任务 | 状态 | 当前结果 | 下一步交付物 |
|---|---|---|---|---|
| T01 | 2.0.x fresh-only 脚手架与原生租户身份 | 已完成 | Account、TenantMember、RBAC、TenantContext、双模式 Host 和 canonical Schema 已通过固定资格 | 无 |
| T02 | `v2.0.1` 正式源码发布 | 已完成 | annotated tag、GitHub Release、源码包、发布快照和 P0-E `p0e201r3` 已固定 | 无 |
| T03 | 2.x 派生应用升级 | 已完成 | `v2.0.0 -> v2.0.1` 的 preflight/apply/verify/recover 通过，app-owned 文件保持 | 后续版本各做一次受影响升级资格 |
| T04 | 头像 fallback 与共享浏览器验收 | 已完成 | 空值/加载失败 fallback、用户菜单、共享 Admin/Tenant A/Tenant B 截图人工检查通过 | 无 |
| T05 | 文档与实现事实对照 | 已完成 | 当前版本、部署边界、Module 缺口和历史材料已重新分类 | 本页和公开入口同步 |
| T06 | 文档状态收口 | 已完成 | 当前入口已统一到 v2.0.1；历史证据保留，docs-site 构建通过 | 无 |
| T07 | Module 统一执行授权合同 | 已完成（v2.1.0 候选） | Article、Fixture 与 7 个官方 Module 的 HTTP/公开入口已接入 Module/Tenant/RBAC 合同；任务、导入导出 worker、支付/OAuth/公众号回调均有对应 Module key；固定候选 P0-E `p0e210a1` 的真实数据库、Tenant A/B、停用负向和浏览器资格通过 | 跨 Module 可运行示例另立后续任务；新增入口继续按同一合同接入 |
| T08 | Article 官方 Module 专项资格 | 已完成（候选） | 当前候选已完成真实数据库安装、Tenant A/B 页面与数据隔离、停用负向和共享 Admin/Tenant A/Tenant B 浏览器截图；证据见 `output/playwright/article-module/b0ef43d/summary.json` | 保持 Article 证据；不把它扩大为其他 Module 的资格替代 |
| T09 | v2.0.1 线上 Standalone 部署 | 已完成 | 已按登记的 production 资源 fresh 部署；服务、TLS、健康检查和访问入口已验证 | 无；后续版本按同一脚本做受影响升级资格 |
| T10 | v2.0.1 线上 Multi-tenant 部署 | 已完成 | 已按登记的 production-candidate 资源 fresh 部署并叠加演示层；四域名 Host 绑定、Tenant A/B 数据、标题、头像和共享 Admin/Tenant A/Tenant B 浏览器矩阵通过；证据见 `output/deployment-v201/online-browser-matrix/cli-result.json` | 无；后续版本按同一脚本做受影响升级资格 |
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

1. 完成 `v2.1.0` Release、两套登记部署升级和 post-deployment 快照。
2. 推进双 Module 可运行示例和后续真实 Module 的入口采用；不为没有消费者的脚手架预先扩张 Runtime。
3. 后续文档同步继续以能力账本和部署快照为准；不重封存已通过的 scaffold/P0-E。
