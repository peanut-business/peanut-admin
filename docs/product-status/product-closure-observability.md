# 产品闭环执行可观测面板

Document ID: `pa-docs-product-status-product-closure-observability`

Status: `current`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Upstream: [`产品闭环执行任务队列`](../plans/product-closure-execution-queue.md)、产品能力账本、
固定提交、PR 和已完成最低验证。

> - 更新时间：2026-08-27
> - 执行队列：[`../plans/product-closure-execution-queue.md`](../plans/product-closure-execution-queue.md)
> - 所有权决定：[`../architecture/product-closure-ownership-and-adoption.md`](../architecture/product-closure-ownership-and-adoption.md)
> - 产品能力唯一事实源：[`capability-ledger.json`](capability-ledger.json)

## 1. 作用和边界

本页回答当前执行到哪里、形成了什么固定交付物、实际验证了什么、还缺什么 Gate、下一项
可领取什么。它不是第二份产品能力事实源：稳定能力完成后才更新 `capability-ledger.json`，
正式 Release 时再冻结 `releases/*.json`。

## 2. 当前事实快照

| 项目 | 当前事实 | 对闭环的含义 |
|---|---|---|
| Application | `origin/dev@9af96499e22e2080e8e4e3aa7562f9cea3f9b402` | PC00—PC02 已合入；8 Module/Bundle 生命周期是规划输入 |
| Core | `origin/dev@8608dafe30467c442000ce408b106d8750ffd766` | 文档治理已合入；Runtime 最近发布身份仍由 PC02 核验 |
| 安装 | CLI 空库安装和 3.x migration 链存在 | 缺一次性向导和首次运行清单 |
| 运维 | 维护页只有环境/目录/清缓存；Core Ops 合同存在 | 缺应用 Host 和产品入口 |
| 备份 | 生产登记有 DB + `php-storage` 配对备份门禁 | 属发布工程能力，不是应用内产品 |
| 恢复 | 登记策略与 Core restore-to-new-target 合同存在 | 应用尚未采用和验证 |
| 升级 | scaffold、migration、deploy-release 分别存在 | 缺统一就绪和执行工作台 |
| 文档 | 新 registry、impact map 和治理检查已合入 | 本队列必须登记并通过生成/公开边界检查 |
| 能力账本 | 基线早于当前 Module/文档提交 | PC00 只建立对照机制，不提前改写 verified 状态 |

## 3. 总体进度

| 指标 | 当前值 | 说明 |
|---|---:|---|
| 队列任务 | 19 | PC00—PC70 |
| 已完成 | 3 | PC00、PC01、PC02 |
| 进行中 | 1 | PC10 |
| 部分完成 | 0 | — |
| 外部阻塞 | 0 | — |
| 未开始 | 15 | PC11 起，另含 PC20—PC70 |
| 当前关键路径 | PC10 | 统一安装预检 Host |
| 可并行工作线 | 0 | 当前共享文档候选由一个集成 owner 收口 |

## 4. 阶段观察

| 阶段 | 状态 | 已有输入 | 尚缺验收 | 下一交付物 |
|---|---|---|---|---|
| A 边界与可见性 | 已完成 | PC00/PC01 由 PR #275 合入；PC02 由 PR #276 合入 | 无 | 保持唯一 owner 与锁版本，不为编号整齐盲升依赖 |
| B 可安装、可诊断 | 进行中 | CLI installer、Core Ops、维护页；PC10 当前候选已形成只读 Host | PC10 合入；PC11/PC12、PC20/PC21 仍未实施 | PC10 安装预检候选 |
| C 可备份、可恢复 | 未开始 | 生产配对备份登记、Core Ops 任务合同 | Provider、任务 UI、隔离恢复和代表验证 | PC30 Provider 合同 |
| D 可升级 | 未开始 | scaffold upgrade、migration、deploy-release | 维护门禁、兼容检查、统一状态和恢复指针 | PC40 维护窗口切片 |
| E 可扩展、可运营 | 未开始 | 8 Module、Bundle、任务和 Provider Runtime | 信任、兼容、配置转移、模板和资格视图 | PC50 信任矩阵 |
| F 固定资格与发布 | 未开始 | 现有 P0-E/Release 机制 | 同一最终 tree 的最小组合资格和文档同步 | PC70 固定候选 |

## 5. 任务观察表

| ID | 状态 | 固定候选/PR | 当前结果 | 尚缺 Gate | 下一动作 |
|---|---|---|---|---|---|
| PC00 | 已完成 | `dev@6967f270dadcd1cb69c4606ad42c198c78db5b5b` / PR #275 | 内部文档登记、导航和公开边界已形成 | 无 | 保持能力账本为唯一完成事实源 |
| PC01 | 已完成 | `dev@6967f270dadcd1cb69c4606ad42c198c78db5b5b` / PR #275 | 唯一 owner、Core 采用规则和下游切片已冻结 | 无 | 由 PC02 固定可消费身份 |
| PC02 | 已完成 | `dev@9af96499e22e2080e8e4e3aa7562f9cea3f9b402` / PR #276 | 四端 lock/导出/来源矩阵已固定；历史 Collaboration 例外已登记 | 无 | 按真实公共导出推进 PC10/PC20/PC30 |
| PC10 | 进行中 | `feat/product-closure-install-preflight` | 唯一只读 Host、`install.php --preflight`、聚焦合同测试和 app-owned scaffold 投影已形成 | 差异/文档治理、提交、PR、合入 | 收口 PC10 后进入 PC11 |
| PC11 | 未开始 | — | — | PC10 | 一次性安装向导 |
| PC12 | 未开始 | — | — | PC11 | 首次运行清单 |
| PC20 | 未开始 | — | — | PC01、PC02 | 采用只读 Ops Console |
| PC21 | 未开始 | — | — | PC20 | 脱敏诊断包 |
| PC30 | 未开始 | — | — | PC01、PC02 | 备份 Provider/制品合同 |
| PC31 | 未开始 | — | — | PC20、PC30 | 应用内备份中心 |
| PC32 | 未开始 | — | — | PC31 | 恢复到新目标并验证 |
| PC40 | 未开始 | — | — | PC20 | 维护窗口和写门禁 |
| PC41 | 未开始 | — | — | PC02、PC30、PC40 | 升级就绪检查 |
| PC42 | 未开始 | — | — | PC32、PC41 | 应用升级纵向闭环 |
| PC50 | 未开始 | — | — | PC02 | Module 信任/兼容矩阵 |
| PC51 | 未开始 | — | — | PC20 | 配置转移包 |
| PC52 | 未开始 | — | — | PC02 | Module/Tenant 测试模板 |
| PC60 | 未开始 | — | — | PC20 | Provider 资格面板 |
| PC70 | 未开始 | — | — | 关键路径任务 | 冻结候选并执行一次组合资格 |

## 6. 状态更新必填字段

任务状态变化必须同时记录：状态、唯一 owner、精确前置、实际写集、完整候选身份、一次最低
验证、固定证据、剩余 Gate、仍可并行项和安全停止线。不能用聊天、开放 PR、GitHub Actions
颜色或“前一阶段未完成”代替这些字段。

示例格式：

```text
PC20 | 部分完成
candidate: <40-char commit>
result: 只读健康/版本/迁移页面已形成
verification: <一次聚焦检查及结果>
remaining gate: 缺登记 backup provider，备份动作保持不可用
parallel work: PC21 可继续；PC31 被 PC30 的 Provider 合同阻塞
```

## 7. 展示层

| 展示层 | 内容 | 事实来源 |
|---|---|---|
| 公开开发者站 | 稳定能力边界、任务指南和安全公共来源地图 | 已验证 Runtime、公开合同和安全投影 |
| 管理员手册 | 安装、首次运行、健康、备份、恢复、升级和排错 | 已验证应用行为 |
| 内部执行面板 | owner、候选、Gate、阻塞、恢复指针和未发布能力 | 本页与固定证据 |

内部能力账本、资源地址、候选 ID 和原始资格证据不得直接投影到公开文档站。PC00 只建立
可发现性和登记；公开能力目录的内容更新必须由安全投影和实际稳定能力驱动。

## 8. 验证

本页随队列运行 `./scripts/docs-governance generate`、`./scripts/docs-governance check` 和
`git diff --check`。Runtime 状态只在对应任务自己的最低验证完成后更新。
