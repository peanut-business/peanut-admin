# Peanut Admin 产品闭环执行任务队列

Document ID: `pa-docs-plans-product-closure-execution-queue`

Status: `planned`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Upstream: 产品能力账本、Module manifest、服务登记、当前应用/Core 锁版本，以及
[`产品闭环所有权与 Core 采用决定`](../architecture/product-closure-ownership-and-adoption.md)。

> - 队列执行状态：**执行中**
> - 建立日期：2026-08-27
> - Application 当前基线：`f1e6393a3f1c88fe620cc04e8147fb9c66199a6c`
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
| 5 | `PC12` | 首次运行配置清单 | 已完成 | PC11 | 展示生产准备度和下一动作 | Application |
| 6 | `PC20` | Core Ops Console 最小采用 | 已完成 | PC01、PC02 | 展示健康、版本、迁移和维护状态 | Core + Application |
| 7 | `PC21` | 可下载脱敏诊断包 | 已完成 | PC20 | 降低反馈和远程排错成本 | Application |
| 8 | `PC30` | 备份 Provider 与配对制品合同 | 已完成 | PC01、PC02 | 固定 DB、文件、manifest、checksum 和审计 | Core + Deployment |
| 9 | `PC31` | 应用内备份中心 | 已完成 | PC20、PC30 | 提交和观察备份任务 | Application |
| 10 | `PC32` | 恢复到新目标并验证 | 已完成 | PC31 | 证明备份可恢复且不覆盖活动库 | Core + Deployment |
| 11 | `PC40` | 维护窗口与写入门禁 | 已完成 | PC20 | 为升级和恢复提供统一停写边界 | Core + Application |
| 12 | `PC41` | 升级就绪与 Module 兼容检查 | 已完成 | PC02、PC30、PC40 | 提前发现版本、迁移和备份风险 | Core + Application |
| 13 | `PC42` | 应用升级中心纵向闭环 | 未开始 | PC32、PC41 | 串联备份、维护、迁移和恢复指针 | Application |
| 14 | `PC50` | Module 制品信任与兼容矩阵 | 已完成 | PC02 | 建立官方目录/市场的安全前提 | Core + Module Host |
| 15 | `PC51` | 配置导入、导出与环境转移 | 进行中 | PC20 | 将配置迁移与数据备份分离 | Application Module |
| 16 | `PC52` | Module 开发与 Tenant 测试模板 | 已完成 | PC02 | 降低二次开发和越权风险 | Scaffold |
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
- PC12：PR #281 已合入 `dev@c8347692133921114d9ae535f7a893bb8699744c`；只读 readiness Host、精确权限 migration、双语 Admin 页面、OpenAPI、用户手册和 scaffold inventory 已形成。PHP 语法、OpenAPI、Web 生产构建、inventory、能力账本和文档治理检查通过；数据库/浏览器/released-scaffold 组合资格保留到 PC70，不阻塞 PC12 实现完成。
- PC20：PR #283 已合入 `dev@187bf95f65a98c5d373c96e4e341a96d65c99b33`，固定 Runtime 提交为 `50751666b2cb3e41bfd54a7bfed5f99a2176f8ca`；Platform-only Host 与 Core Ops Web 公共入口已展示数据库、应用迁移、Module 目录、缓存、Runtime 存储、版本身份和维护窗口。关键检查失败为 `unhealthy`，非权威缓存失败为 `degraded`，证据异常 fail closed；备份、恢复、维护写入和日志仍保持禁用。
- PC21：PR #285 已合入 `dev@8873a92b1628dc8e12b5f7c2d1e1dae90a2387e9`，固定 Runtime 提交为 `39815105aba103b16ca1b98243659fd6df8e599d`；Platform 可下载固定 schema JSON 诊断包，数据源限定为 PC20 状态、非秘密配置、Module、失败任务聚合和 Platform 审计事件聚合。双权限、1 MiB 上限、固定时间窗口、服务端/浏览器 SHA-256 校验和下载审计已形成；原始日志、任意文件、私有路径、请求头、Cookie、凭据、个人及 Tenant 业务记录被排除。
- PC30：PR #287 已合入 `dev@dd6a877d1d31a583d0221c0a7b6a3ca325da8e77`，固定 Runtime 提交为 `2380593b51680de48962eb360514530bdb356fe5`；应用直接采用 Core Ops Provider Registry，固定单一配对 Provider、逻辑新目标、一次尝试和 schema 1 manifest。容量、停写窗口、资源/镜像身份、固定 DB/文件制品与 SHA-256 已形成 path-free 合同；Platform 提交和实际 Deployment 执行仍保持禁用。
- PC31：PR #289 已合入 `dev@9f33eb9fac44858d5d0575e1a760eec0d3af0694`，固定 Runtime 提交为 `994f8e7961af9be8834bc9c362f268546eeff6f4`；Platform 已采用 Core 任务提交/查询，形成备份中心、Application-owned evidence、受信 worker、60 秒心跳和 revision fencing。登记 development 数据库上的权限、幂等、并发拒绝、claim/succeed、最小公开投影、evidence、审计与零残留合同通过；真实配对制品和新目标恢复归 PC32。
- PC32：PR #291 形成恢复任务、受信 worker、隔离资源与 evidence；PR #292—#302 收敛固定候选资格适配和实际 Gate 缺陷，最终 `dev@af7b1c961bca314d9eba5c506aa6eca19fc1cf9b` 成功把已验证配对备份 `backup_01010866ca8254b52ff5b127ed4e402d` 恢复到登记新目标。任务 `job_e5f0b76aea9fb68e96776f67c9a2ca77` 与 evidence `61fd2027fb4b8f950bfaf270dd5aa4bb6ca7d3a5f1e6c4af643831892d73e800` 记录 97 张表、6 条应用迁移、6 张 canonical 关键表、Account/Tenant/TenantMember 各 1、文件卷 0 文件/4096 bytes、零发布端口、受保护 Runtime 身份前后一致和成功零残留；PC32 已完成。
- PC40：PR #297 已合入 `dev@468dc3b0ae09d351deaac264b34e110fe4a893d4`，固定 Runtime 为 `675de48cce762bf4b268e4d31e07de9de2520b5d`；应用已采用 Core `MaintenanceService`/`MaintenanceWindowStore`，Platform 可按 reason/revision/时间范围计划和关闭窗口，全局写门禁对真实 `POST /api/login/logout` 返回 `50300 / MAINTENANCE_WRITE_BLOCKED` 并记录唯一 denied Platform 审计。登记 development 数据库的窗口与审计 fixture 清理为 0，端口和租约零残留；PC40 已完成。
- PC50：PR #296 已合入 `dev@ed5ee1952dd01b8448adf1fb6e7c9ad4bc21be48`，固定 Runtime 为 `d46b5d2e2c88d784c2e209f70c7c60015ed8ce48`；`plugins.lock` 与九个 manifest 已固定版本、依赖、权限摘要、migration 指纹、来源、archive/签名/SBOM/许可证/资格状态和 verified-backup 要求，install/reconcile/upgrade 共用同一资格解释。现有唯一 Runtime 只接受 `bundled-locked`；Marketplace 在 archive SHA-256、受信签名、SBOM、许可证审核和漏洞响应 authority 签发前保持稳定 blocked，PC50 已完成。
- PC52：PR #306 已合入 `dev@eaa1c754b1c214f342a0b4ca94e620a16370d3a3`，固定 Runtime 为 `3b96cc0b1b58c64fd451fb04aca5d41907cb3126`；`module:create` 现在生成唯一公开 Commands 合同、append-only migration 指南和 Plugin 制品外 Tenant 安全测试骨架，固定 A/B Tenant、伪造 payload/resource ID、撤权、TenantModule 停用及 migration 失败/禁止无修复重放场景，未引入第二 Plugin Runtime。聚焦生成器、文档治理、6 项 inventory SHA-256 和差异检查通过；正式 released-scaffold 组合资格归 PC70。
- PC41：PR #307 已合入 `dev@f1e6393a3f1c88fe620cc04e8147fb9c66199a6c`，固定 Runtime 为 `f77f48cbc1feb36c35597f263ff21fd17185809e`（tree `716a39d47ac9ead0efa44bf82e94a9024c0abca2`）；Platform 只读升级就绪固定 source/target、Release 资格、migration、目标 Module/source/kernel、zero-write scaffold preview、配对备份/恢复 evidence、维护窗口与 recovery pointer。目标只能来自 `.peanut/upgrade-target/release/`，HTTP 不能选择路径、URL、命令、Release、镜像或凭据；跨大版本稳定要求 fresh/rebuild。PHP lint、目标/current Module 差异和 target kernel fixture、preview/target fail-closed、Platform 构建、OpenAPI、文档与 inventory 检查通过；PC42 现在可以消费固定 descriptor 和恢复指针。
- PC51 正在独立功能分支形成候选；开放 PR 或固定提交形成前不得写成完成。当前关键路径转为 PC42。
- 当前没有数据库、服务、容器、浏览器或生产资源 owner。

## 7. 验证

本计划变更运行 `./scripts/docs-governance generate`、`./scripts/docs-governance check` 和
`git diff --check`。具体 Runtime 任务按各自风险运行一次最低充分验证。
