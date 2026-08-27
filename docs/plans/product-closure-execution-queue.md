# Peanut Admin 产品闭环执行任务队列

Document ID: `pa-docs-plans-product-closure-execution-queue`

Status: `planned`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Upstream: 产品能力账本、Module manifest、服务登记、当前应用/Core 锁版本，以及
[`产品闭环所有权与 Core 采用决定`](../architecture/product-closure-ownership-and-adoption.md)。

> - 队列执行状态：**执行中**
> - 建立日期：2026-08-27
> - Application 输入基线：`f289c69a620f1eaffb0ba5a8cc39d089759259ab`
> - Core 输入基线：`8608dafe30467c442000ce408b106d8750ffd766`
> - 进度入口：[`../product-status/product-closure-observability.md`](../product-status/product-closure-observability.md)
> - 产品能力唯一事实源：[`../product-status/capability-ledger.json`](../product-status/capability-ledger.json)

## 1. 目标与范围

本队列把 Peanut Admin 从完整工程底座推进为可安装、可诊断、可备份、可恢复、可升级、
可扩展且可理解的产品。它不建设完整 SaaS 商业化，不实现跨实例运营平台，不把应用业务
Module 迁入 Core，也不自动改写派生应用的 app-owned 业务源码。

正确顺序不是“产品完成后再抽 Core”，也不是“先重构全部 Core”，而是：

```text
事实与文档可见
  → 冻结 Core / Application / Module / Deployment owner
  → 固定可采用的 Core 版本和导出
  → 实现最小纵向产品切片
  → 只把真实切片证明缺失的通用合同补入 Core
  → 固定候选资格与发布投影
```

## 2. 执行规则

- 状态只使用：`未开始 / 进行中 / 部分完成 / 已完成 / 外部阻塞 / 暂缓 / 范围外`。
- 同一时刻保持一条关键路径，最多增加两条 owner、文件和资源互不冲突的工作线。
- 每项开始前重新核对远端 `dev`、开放 PR、worktree、资源和文件 owner。
- 普通可逆切片在一个 PR 内完成必要合同、实现、文档和最低验证。
- Tenant、身份、权限、Schema、恢复、部署、公共 API 或 Core 包变化设置独立安全停止线。
- 任务只有在固定提交、最低验证和证据引用完整后才标记“已完成”。
- 过程状态写入可观测面板；稳定产品能力完成后才更新能力账本。

## 3. 总体队列

| 顺序 | ID | 任务 | 当前状态 | 直接前置 | 作用 | 主要归属 |
|---:|---|---|---|---|---|---|
| 0 | `PC00` | 文档能力目录与事实源对照 | 已完成 | 无 | 让能力、边界和状态可发现 | Docs/Application |
| 1 | `PC01` | 产品闭环所有权与采用决定 | 已完成 | 当前事实盘点 | 避免重复 Runtime 和事后抽 Core | Architecture |
| 2 | `PC02` | Core/Application 兼容与版本基线 | 已完成 | PC01 | 固定可采用导出和不可变来源 | Core + Application |
| 3 | `PC10` | 统一安装预检 Host | 已完成 | PC01、PC02 | 为 CLI、Web 和自动化提供同一检查 | Application Host |
| 4 | `PC11` | 一次性安装向导 | 已完成 | PC10 | 完成首次安装产品流程 | Application |
| 5 | `PC12` | 首次运行配置清单 | 未开始 | PC11 | 展示生产准备度和下一动作 | Application |
| 6 | `PC20` | Core Ops Console 最小采用 | 未开始 | PC01、PC02 | 展示健康、版本、迁移和维护状态 | Core + Application |
| 7 | `PC21` | 可下载脱敏诊断包 | 未开始 | PC20 | 降低反馈和远程排错成本 | Application |
| 8 | `PC30` | 备份 Provider 与配对制品合同 | 未开始 | PC01、PC02 | 固定 DB、文件、manifest、checksum 和审计 | Core + Deployment |
| 9 | `PC31` | 应用内备份中心 | 未开始 | PC20、PC30 | 提交和观察备份任务 | Application |
| 10 | `PC32` | 恢复到新目标并验证 | 未开始 | PC31 | 证明备份可恢复且不覆盖活动库 | Core + Deployment |
| 11 | `PC40` | 维护窗口与写入门禁 | 未开始 | PC20 | 为升级和恢复提供统一停写边界 | Core + Application |
| 12 | `PC41` | 升级就绪与 Module 兼容检查 | 未开始 | PC02、PC30、PC40 | 提前发现版本、迁移和备份风险 | Core + Application |
| 13 | `PC42` | 应用升级中心纵向闭环 | 未开始 | PC32、PC41 | 串联备份、维护、迁移和恢复指针 | Application |
| 14 | `PC50` | Module 制品信任与兼容矩阵 | 未开始 | PC02 | 建立官方目录/市场的安全前提 | Core + Module Host |
| 15 | `PC51` | 配置导入、导出与环境转移 | 未开始 | PC20 | 将配置迁移与数据备份分离 | Application Module |
| 16 | `PC52` | Module 开发与 Tenant 测试模板 | 未开始 | PC02 | 降低二次开发和越权风险 | Scaffold |
| 17 | `PC60` | 外部 Provider 生产资格面板 | 未开始 | PC20 | 区分已配置、已连通和生产可用 | Official Modules |
| 18 | `PC70` | 固定候选组合资格与公开文档 | 未开始 | 关键路径任务 | 形成可发布、可恢复、可理解的闭环 | Integration owner |

## 4. 任务合同

| ID | 最小交付物 | 最低验收 | 停止线 |
|---|---|---|---|
| PC00 | 文档登记、内部导航、公开能力展示方案 | 登记、链接、生成投影和公开边界检查通过 | 不把内部能力账本或候选证据直接公开 |
| PC01 | 唯一所有权矩阵、Core 可采用清单、首个下游切片 | 每项只有一个 Runtime owner，无双写/deep import | Core 合同不足时只登记真实最小缺口 |
| PC02 | PHP/Web/PC/UniApp 版本兼容矩阵 | 导出、dist/reference/integrity 和测试 owner 可追溯 | 不为版本整齐盲升依赖 |
| PC10 | 结构化安装检查和唯一 Host | 状态、code、原因、修复建议稳定且不泄密 | 不猜 localhost、默认端口或默认凭据 |
| PC11 | guided/automatic 共用唯一 Host；环境→数据库→模式→管理员→Module→安装→健康 | 一次性 setup token、安装锁、重复访问、失败重试、双模式通过 | 不写 `.env`，不支持未知旧库自动 adopt |
| PC12 | 品牌、渠道、存储、备份、worker、域名/TLS、安全清单 | 每项说明影响、入口和生产阻塞性 | 模拟 Provider 不显示为生产可用 |
| PC20 | 只读健康/版本/迁移/维护 Host 与页面 | 关键失败 unhealthy，非权威缓存可 degraded，状态异常 fail closed | 静态环境信息不冒充 Runtime 健康 |
| PC21 | 固定 schema 的脱敏诊断包 | 大小/时间上限、SHA-256、秘密和个人数据拒绝/脱敏 | 不读取任意文件或私有路径 |
| PC30 | 受信 Provider、DB+文件制品和 manifest 合同 | 一致性窗口、容量、checksum、失败清理、审计明确 | Web/Core 不接收任意 shell/path |
| PC31 | 备份提交、进度、结果和新鲜度视图 | 幂等、任务记录、失败原因和审计可见 | 不成为命令执行器或文件浏览器 |
| PC32 | 登记新目标恢复与代表验证 | 活动目标、损坏摘要、未验证结果均拒绝 | 覆盖生产库需独立授权 |
| PC40 | reason/revision/时间窗口和全写入口门禁 | 后端写入口真实拒绝并留审计 | 页面隐藏不能替代门禁 |
| PC41 | source/target、备份、迁移、Module、app-owned 冲突投影 | ready/blocked 与稳定原因可重复 | 备份存在不等于恢复已验证 |
| PC42 | preflight→备份→维护→部署/迁移→smoke→恢复指针 | 每步不可变输入、状态和失败停止点明确 | 跨大版本仍 fresh/rebuild；跨实例归独立平台 |
| PC50 | 版本、依赖、权限、migration 可逆性、SHA、签名/SBOM/许可证矩阵 | 安装和升级能解释兼容/阻塞原因 | 没有审核和漏洞响应前不开放 Marketplace |
| PC51 | schema 化配置包、dry-run、冲突和秘密重绑定 | checksum、作用域和审计完整 | 默认不导出密码、token、Cookie 或密钥 |
| PC52 | Module manifest/合同/迁移及 Tenant 安全测试骨架 | A/B Tenant、伪造 ID、停用、撤权、迁移失败可复制 | 不新增第二插件 Runtime |
| PC60 | 每 Provider 配置、连通、回调、轮换、最近失败视图 | 真实平台逐项独立资格 | 不在通用 Gate 中发生真实资金或未授权消息 |
| PC70 | 同一冻结候选的最小组合资格和公开投影 | 能力账本、Release、手册和站点引用同一身份 | 纯文档变化不触发 Runtime reseal |

## 5. 并行边界与阶段完成

| 阶段 | 任务 | 完成条件 | 可并行项 |
|---|---|---|---|
| A 边界与可见性 | PC00—PC02 | 能力可发现，owner 与采用版本固定 | PC00/PC01 可并行；PC02 等 PC01 决定 |
| B 可安装、可诊断 | PC10—PC21 | 引导安装、健康解释和诊断包形成 | PC10 与 PC20 可在 owner 不重叠时并行 |
| C 可备份、可恢复 | PC30—PC32 | 配对备份可提交、校验并隔离恢复 | PC30 可与 PC10/PC20 并行准备合同 |
| D 可升级 | PC40—PC42 | 维护、就绪、执行和恢复指针闭环 | PC40 可在 PC30 进行时独立推进 |
| E 可扩展、可运营 | PC50—PC60 | 兼容/信任、配置转移、模板和 Provider 状态可见 | PC50/PC52 独立于备份 Runtime |
| F 固定资格与发布 | PC70 | 同一候选通过最低组合 Gate，文档与事实一致 | 无；冻结后不并入新功能 |

## 6. 当前执行点

- PC00/PC01：已由 PR #275 合入 `dev@6967f270dadcd1cb69c4606ad42c198c78db5b5b`。
- PC02：已由 PR #276 合入 `dev@9af96499e22e2080e8e4e3aa7562f9cea3f9b402`；四端锁版本、公共入口、不可变来源、例外和验证 owner 已固定，未盲升依赖。
- PC10：已由 PR #277 合入 `dev@f289c69a620f1eaffb0ba5a8cc39d089759259ab`；唯一只读 Host、`install.php --preflight`、聚焦合同测试和 app-owned scaffold 投影已完成。
- PC11：PR #279 已合入 `dev@d80337b6d7b800558131968e65f8039cb8781912`；固定源码候选 `7684a5fcb4bd23cdd966ab760d16a8130ba41ced` 完成唯一执行 Host、安装态门禁、部署 transport 和 Web 向导，`pc11e1` 在登记资源上通过 Standalone/Multi-tenant 空库、8 个官方 Module、无效 token、重复执行与零残留资格，Web 生产构建通过。完整 released-scaffold P0-E 仍归 PC70。
- PC12：成为当前下一关键路径；领取后先冻结生产准备项、状态来源和入口，不把模拟 Provider 标为生产可用。
- PC20/PC30：前置已满足，可在不与 PC12 共享文件 owner 时领取；当前主线按队列先推进 PC12。
- 当前没有数据库、服务、容器、浏览器或生产资源 owner。

## 7. 验证

本计划变更运行 `./scripts/docs-governance generate`、`./scripts/docs-governance check` 和
`git diff --check`。具体 Runtime 任务按各自风险运行一次最低充分验证。
