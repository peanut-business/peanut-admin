# 当前任务清单

> 更新时间：2026-08-21
>
> 本页是当前开发工作的人工阅读入口。能力的机器状态仍以
> [`capability-ledger.json`](./capability-ledger.json) 为准；这里记录下一步要做什么、
> 已经做到哪里，以及哪些旧材料不应再作为工作依据。

## 当前结论

Peanut Admin `v2.1.5` 的正式源码发布和部署证据已封存为历史基线；当前正式消费版本为
`v3.0.4`。下一补丁必须绑定最新 `origin/main`，再创建 tag、GitHub Release 和演示部署。
此前 `v2.1.4` 已完成正式源码发布和部署收口：P0-E 7/7 通过，Standalone 从
`v2.0.1` 升级，多租户候选从空库 fresh 安装并叠加可丢弃演示层，平台/共享 Admin/Tenant A/
Tenant B 四个公网 Host 已完成真实浏览器验收。无秘密 post-deployment 快照已封存；后续
工作不再阻塞当前 Release。

后续仍可推进的产品工作是跨 Module 可运行示例、新入口的逐项 Module Guard 采用，以及
独立 DCS/运营平台项目；它们不应被写成 Peanut Admin `v2.1.4` 已内建能力。

## 任务进度

| 编号 | 任务 | 状态 | 当前结果 | 下一步交付物 |
|---|---|---|---|---|
| T01 | 2.0.x fresh-only 脚手架与原生租户身份 | 已完成 | Account、TenantMember、RBAC、TenantContext、双模式 Host 和 canonical Schema 已通过固定资格 | 无 |
| T02 | `v2.0.1` 正式源码发布 | 已完成 | annotated tag、GitHub Release、源码包、发布快照和 P0-E `p0e201r3` 已固定 | 无 |
| T03 | 2.x 派生应用升级 | 已完成 | `v2.0.0 -> v2.0.1` 的 preflight/apply/verify/recover 通过，app-owned 文件保持 | 后续版本各做一次受影响升级资格 |
| T04 | 头像 fallback 与共享浏览器验收 | 已完成 | 空值/加载失败 fallback、用户菜单、共享 Admin/Tenant A/Tenant B 截图人工检查通过 | 无 |
| T05 | 文档与实现事实对照 | 已完成 | 当前版本、部署边界、Module 缺口和历史材料已重新分类 | 本页和公开入口同步 |
| T06 | 文档状态收口 | 已完成 | 当前入口已统一到 v2.0.1；历史证据保留，docs-site 构建通过 | 无 |
| T07 | Module 统一执行授权合同 | 已完成（v2.1.4） | Article、Fixture 与 7 个官方 Module 的 HTTP/公开入口已接入 Module/Tenant/RBAC 合同；任务、导入导出 worker、支付/OAuth/公众号回调均有对应 Module key；固定候选 P0-E 的真实数据库、Tenant A/B、停用负向和浏览器资格通过 | 跨 Module 可运行示例另立后续任务；新增入口继续按同一合同接入 |
| T08 | Article 官方 Module 专项资格 | 已完成（候选） | 当前候选已完成真实数据库安装、Tenant A/B 页面与数据隔离、停用负向和共享 Admin/Tenant A/Tenant B 浏览器截图；证据见 `output/playwright/article-module/b0ef43d/summary.json` | 保持 Article 证据；不把它扩大为其他 Module 的资格替代 |
| T09 | v2.1.4 线上 Standalone 部署 | 已完成 | 已按登记的 production 资源从 `v2.0.1` 升级到 `v2.1.4`；迁移、备份、Compose 健康和入口已验证，备份 `20260818T131425Z-1c8aff4f1f19` | 后续版本按同一脚本做受影响升级资格 |
| T10 | v2.1.4 线上 Multi-tenant 部署 | 已完成 | 已按登记的 production-candidate 资源 fresh 部署并叠加演示层；平台、共享 Admin、Tenant A、Tenant B 四个 Host、标题、头像和页面矩阵通过；证据见 `docs/product-status/deployments/v2.1.4-online-experience.json` | 后续版本按同一脚本做受影响升级资格 |
| T14 | v2.1.5 正式发布与 post-deployment 快照 | 已完成 | `v2.1.5` 已完成 P0-E 7/7、同提交 annotated tag、GitHub Release、生产单租户升级、多租户 Demo fresh 部署和无秘密快照；资格证据见 `output/p0e-p0e215b/summary.json`，部署证据见 `docs/product-status/deployments/v2.1.5-online-experience.json` | 后续版本继续遵守 main-first、固定候选和一次资格规则 |
| T11 | DCS 业务模块 | 范围外 | Peanut 只提供扩展边界；Party、Product、Inventory、Procurement 等不属于本仓 Runtime | 在 DCS 仓库按 Module 合同实现 |
| T12 | 应用运维平台（远程升级与实例运维） | 下一步可执行（无阻塞） | 已解除前置阻塞；明确为基于当前脚手架（create-app）派生的完全独立顶层应用，专注于已部署生产实例的登记、健康巡检、备份存证与签名远程平滑升级 | 在独立工程推进 MVP 切片 |
| T13 | 完整 SaaS 商业化 | 暂缓 | 套餐、订阅、计费、应用市场不属于当前交付 | 等运营闭环和真实消费者成立后再立项 |
| T15 | 租户停用全局 Fail-Closed | 暂缓（已识别） | API/管理入口已有 Tenant 状态门禁，但 Nginx 直出 `/uploads/tenant_{id}/` 和未统一挂载状态中间件的公共内容入口仍可能在租户停用后继续可读 | 后续先盘点静态资源交付与公共路由，再决定签名 URL、反向代理鉴权或停用时撤销发布的统一方案 |
| T16 | 充值退款部分/多次退款扩展 | 部分完成（数据库资格阻塞，暂后置） | 历史 F02/PB05 单次全额退款基线保留；`audit20b` 的 `30+70` 验收在第二笔失败，预期两条退款记录和两条余额流水未成立，当前最可能受余额流水 `(tenant_id, source_sn)` 唯一约束影响 | 为每笔退款生成独立流水来源号并保持请求级幂等；只重跑失败的 `30+70` 数据库资格组，未通过前不得在手册或发布能力中宣称部分/多次退款 |

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

1. 推进双 Module 可运行示例和后续真实 Module 的入口采用；不为没有消费者的脚手架预先扩张 Runtime。
2. 继续维护发布后的能力账本与部署快照；不重封存已通过的 scaffold/P0-E。
3. DCS 业务模块与跨应用运营平台在各自独立仓库推进。
