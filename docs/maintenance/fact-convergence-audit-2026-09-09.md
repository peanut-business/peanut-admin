# Peanut Admin 全量事实收敛审计

Document ID: `pa-docs-fact-convergence-audit-2026-09-09`

Status: `current`（进行中，未声明全量收口）

Owner: `product-architecture`

## 目的与证据等级

本审计统一业务、产品、架构、版本、需求、能力、计划、进度、风险、验证、运维、外部依赖和历史替代关系。入口为[当前事实入口](../governance/current-state.md)，上游归属为[事实来源地图](../governance/authoritative-source-map.md)。已有 capability、service、resource、Module 和 Release 事实源继续分别拥有其领域，不把聊天或本文变成第二套状态账本。

结论分类为：`verified-fact`（直接来源已核验且限定范围）、`current-decision`、`planned`、`blocked`、`accepted-risk`、`superseded`、`trace-only`。计划和历史通过不得升级为当前实现或新候选资格；同一数字、PR 标题和任务“已完成”不构成同一产物的证据。

## 覆盖与方法

2026-09-09 先通过 Codex 当前/归档任务工具枚举可见页面；当前列表最多返回 50 条且无翻页参数，不能据此声称全量。随后只读查询本机任务索引，按 Peanut cwd/title/Git origin 提取 1,115 条候选记录，全部有可读取 rollout，其中 554 条归档。物理目录补扫发现两个额外位置，但 ID 已在索引内：合计 1,117 个相关物理位置、1,115 个唯一任务，不能重复计数。逐项定位见[来源登记](fact-audit-2026-09-09/source-index.json)。

三个互不重叠批次进行解析和语义筛选，机械首轮误纳无关项目已补做。来源登记中的相关/排除/trace 分类用于导航，不等于每条历史说法均已由源码验证；通用系统提示、审批上下文、旧命令、重复子任务与仅有任务终态的内容不升级为当前事实。实质裁定只采用本报告附带的直接证据。范围限本机可访问索引及 rollout，不能覆盖未同步设备或已删除记录。

不复制全量聊天或敏感值到仓库/知识库。历史材料中的授权、AGENTS 和提示词仅用于追溯，不作为本轮执行指令。当前事实回到两仓分支、源码、Schema、实际 manifests/locks、GitHub Release、发布/部署回执和公开站点核对。

两仓 Markdown 路径覆盖快照见[文档覆盖登记](fact-audit-2026-09-09/document-coverage.json)：Application 210 个路径（204 个登记文档及 6 个根入口），Core 125 个路径（123 个登记文档及 2 个根入口）。Core 文档继承 group 的 owner、来源和公开范围；根 Markdown 由本审计按实际入口用途分类，不虚构其已进入文档 registry。Application 的空 collections 合法，其文档已逐项登记。另有 1,671 份 scaffold 历史 Markdown 副本仅计数并保留不可变性。JSON/YAML 的 Release、资格、资源、API 和能力事实仍由各自机器账本拥有，覆盖快照不复制它们。

## 两份补充材料

用户给定的应用主工作树 `docs/plans/session-operations-and-handover-audit-2026-09-09.md` 当前不存在；其完整文件保存在 quarantine 提交 `1b2b66cd` 同路径。通过 `git show` 恢复后，与 Gemini `session_handover_report.md` 逐字比较一致。因此两份材料是同一份历史报告的两个入口，不是两项独立证据。保留原件，不在当前 plans 恢复过期执行指令。

报告对机械替换、语法修补、测试短路、隔离取证的叙述可用于定位；其自述数量、动机和“完全依赖本报告”均不构成事实证明。现行补正记录见[Gemini 收敛审计](../plans/gemini-refactor-convergence-audit-2026-09-09.md)。

## 已确认冲突与裁定

| 冲突 | 裁定与状态 | 直接证据与范围 |
| --- | --- | --- |
| App 3.0.14 与 Core Alpha.13 不同号 | 历史实物事实；新产品规则统一，客户版本改称 Instance，不伪造旧一致性 | `release-versions.json`、Composer/npm manifests、两个已发布 GitHub Release；[版本 ADR](../architecture/product-version-identity-adr.md) |
| Gemini 建议把五项停止线直接改待办并解除发布阻断 | `superseded`；必须逐项修复/验证或显式接受有 owner 的窄风险，不能靠改状态通过 | 后续收敛提交与 v3.0.14 固定 P0-E，见 Gemini 审计 §10 |
| Gemini 建议删除 Context 并完全依赖请求容器 | `superseded`；ExecutionContext、TenantScope、可信 CLI/Worker 上下文继续保留 | [Core ThinkPHP ADR](../architecture/core-thinkphp-runtime-direction-adr.md)、应用执行规则 §6 |
| 保持所有旧测试断言不变 vs 修复过时路径/合同 | 保护业务、安全、事务语义；真实错误断言随经核验合同修正。禁止占位与短路参与通过判断 | 执行规则 §1、`scripts/check-test-integrity` 与现行真实测试 |
| Gemini 报告仍将主工作树写为 quarantine 脏现场 | `superseded`；现场已固定为提交，当前主工作树在 main，不能恢复历史脏状态 | `git worktree list`、quarantine branch、后续清理提交 `fc6796c7` |
| Core Alpha.13 指南仍称 pending/unpublished | 当前说明过期；已发布身份及资格记录为准，保留候选合同的历史范围 | Core qualification JSON `status=pass`、GitHub Release 2026-09-08T22:06:51Z |
| Core Module 指南把未来 ThinkPHP 事务写成当前 External Host 实现 | 当前实现仍传递同一 PDO；未来方向明确标为未实施，版本对齐不等于迁移 | Core `ExternalOperationHost::command()`、`AtomicOperationAdapter` 与修正后的 Module 指南 |
| 高容量媒体 spike 被混作 Storage 产品化 | `trace-only`；spike 不在 dev/main，既有四 Provider 装配与真实厂商资格分开 | `e915bea7`、Storage adoption `563df8c4` 及其 main 祖先关系 |
| Rich Text 本地打包被混作独立发布 | bundled/local package 与 published 分层；缺渠道、签名、SBOM、审核和实际运行资格时不得称独立发布 | [Module 发布合同](../architecture/module-publication-contract.md)、Gemini 审计 §7/§12 |
| v3.0.14 Release、Demo overlay 与后续 dev 依赖修复混用 | 三种不可变身份分别保留；不回写 v3.0.14 的依赖清零结论 | Release/Deployment 快照、`1c836651`、`2803cdc6`、UniApp 有期限风险裁定 |
| 文档站 deployment URL 多个不同 | Release 时点回执是历史快照；当前站点指针由资源登记拥有。后续同步另建回执，不改历史部署证据 | `resources/project-resources.json`、v3.0.14 deployment documentation 字段 |

## 被拒、部分写入和后续补做

对源任务及 2026-09-08/09 子任务的 43 个 rollout，按 call ID 对齐调用与返回；wait 沿 cell ID 回溯实际执行，不把轮询当动作。12 组安全摘要、1-based 行号和后续完整 commit 见[操作处置登记](fact-audit-2026-09-09/rejected-dispositions.json)。不保存原始命令、口令或 token。

| 组 | 原动作及结果 | 后续裁定 |
| --- | --- | --- |
| 01 | 两仓全量 stash 在执行前被拒 | 后续用 quarantine 分支封存；stash 本身未执行 |
| 02 | 跨三端依赖安装因资源授权不完整被拒 | 后续正式资格有独立记录；不能据此改写原调用结果 |
| 03 | Module publication worktree 创建审批超时 | 后续治理提交 `57a4712b` 已进入主线；原调用没有绑定成功输出 |
| 04 | 临时清理 SQL FileChange 已产生 | 只证明文件写入，不能证明数据库执行 |
| 05 | 六个 v3014h 合成库清理调用无成功返回 | 后续审计有按 run ID 清理回执；本轮未连接数据库复核，不作为当前健康证明 |
| 06 | 资格、文档及测试完整性检查超时 | 后续聚焦检查记录通过，原调用不计为通过 |
| 07 | endpoint 边界修复提交调用无成功返回 | 后续 `4b24af96` 已存在并进入主线 |
| 08 | 带凭据的公网 demo 登录请求被拒 | 未执行；部署快照明确 authenticated browser 未重跑 |
| 09 | 一次删除十个分支因吸收证明不足被拒 | 后续 `fc6796c7` 记录较窄范围清理；保留 quarantine，原批量删除未执行 |
| 10 | private adoption worktree 命令退出失败 | 属于命令/Git 权限失败，不是自动审批否决；创建结果未确认 |
| 11 | 缺失 cwd 导致读取流程启动失败 | 基础设施失败，没有业务修改 |
| 12 | 子任务在线 advisory 查询被拒 | 后续 `1c836651`、`2803cdc6` 独立完成依赖整改；UniApp 风险仍按期限保留 |

上述历史未确认不阻塞不相关文档或源码；需要使用对应生产资源时重新按登记核验，不能为填补审计表重复执行历史破坏性动作。

## 本轮执行与证据边界

版本合同的现有 `ScaffoldUpgradeRunnerTest`、`PlatformUpgradeTargetModuleTest` 和历史 `scripts/check-release-consistency --tag v3.0.14` 已通过。它们分别证明升级合同、Platform 目标边界和旧 Release 一致性，不证明新 Core 发布、双 Edition 完整资格或客户上线。

安装迁移消费者进一步拆分来源产品、实例发布序列和 scaffold 筛选目标：demo overlay 绑定来源产品，未标记客户 SQL 记录实例序列，Peanut SQL 按 scaffold 版本筛选。既有无数据库 `FreshSchemaBaselineTest` 与相关 PHP 语法检查通过；合成动态探针连续两次在环境文件门禁处停止，未加载到目标分支、未连接数据库，因此不计动态行为通过，后续生成物资格仍须覆盖。

Core 首次固定候选 `ff3a58088d93ba08a3382dfdc941a92b22ba02ce` 在供应链组的 Composer audit 非零退出；首次临时输出被既有脚本清理，不能追认具体网络原因。同候选、同锁与工具的单次诊断退出 0，advisories 与 abandoned 均为空，仅证明诊断时未发现对应问题。独立 D05 同时发现 ArtifactRevision/EntitlementQuota 的公开 `Package::VERSION` 仍为 Alpha.13：历史发布 diff 证明它们属于聚合 PHP 包身份，而非 Module manifest 版本；主控此前的独立 Module 判断已撤回。修复形成新候选，首次失败与已通过组的身份继续保留，不能直接升级为新候选资格。

本轮曾出现一次资源租约流程失误：共享工具租约冲突后，复合命令未及时停止，当前隔离 worktree 的 Composer 安装仍执行完成；锁文件未改变。已改用遇错即停并获得正确租约，此安装不计为合规固定候选资格。Core 准备阶段另一次锁比较失败后的继续写入已撤回，最终仅保留第一方版本与锁内容摘要变化；第三方锁对象比较通过。两个事件均保留真实范围，不用后续通过抹除。

新 Core 资格预检发现旧 Alpha.13 cache 仍监听其登记端口，故保留该资源，并显式登记新的 3.1.0 独立端口组。旧固定输出共 24 个文件在确认无活动租约、无打开进程后保全到 Core 的 `peanut-admin-core-historical-evidence-preserved-20260909` 资源，移动前后逐文件摘要一致。恢复报告 SHA-256 `65a3e43741e99c61aae8c43146a620ad3904edb3dacae5e0d8ff4f6a592386fa` 与旧 Q01 证据相同；旧证据自身也明确不证明资源清理。不得把此次保全称作新资格通过或旧资源全清理。

公开投影使用 Application 已登记的 Cloudflare Pages 文档站。Core 的源码与编辑链接已修正到实际 `peanut-opensource/peanut-admin-core` 仓库；其 GitHub Pages 配置查询返回 404，缺少可确认的现行公开托管入口，因此不猜新域名或把旧 VitePress base/sitemap 当在线证明。Core 文档本身继续由版本化源码与本地构建验证。

公开投影已从固定 Application 提交 `25e96a3b5e05cc5f17618782acf9072490c34d3e` 发布到 [Cloudflare 不可变部署](https://1f6d0a93.peanut-admin-docs.pages.dev)，并核验自定义域名的版本说明和 Release 页面内容摘要一致。102 个构建文件与四项 HTTPS 核验见[独立文档部署回执](fact-audit-2026-09-09/docs-deployment-receipt.json)。首次带 `.html` 请求得到预期的 clean-URL 308 空响应，改用规范地址后核验通过，未重新部署。页面继续以 v3.0.14 为已发布事实；此回执不代表 3.1.0 源码或实例发布。

版本语义与依赖顺序已经稳定，[当前事实入口](../governance/current-state.md)已给出 non-blocking checkpoint，解除临时审计等待。新包、应用采用及双 Edition 发布各自的真实 Gate 继续生效。

## 后续审计收口

仍需完成：两仓文档覆盖的最终快照、版本字段消费者及固定候选资格、公开投影构建和部署回执、知识库与 Git 收口。以上是本轮剩余交付，不是新增业务队列。真实发布 Gate 失败只阻塞该发布与直接下游；不能把尚未执行写成外部阻塞或已完成。
