# Peanut Admin 当前事实入口

Document ID: `pa-docs-current-state`

Status: `current`

Owner: `product-architecture`

本页是公开事实导航，不维护第二份能力账本。先确定对象、状态与证据范围，再进入对应事实源。

| 要回答的问题 | 唯一事实入口 |
| --- | --- |
| 产品是什么、谁拥有哪些职责 | [Core/Application 技术边界](../architecture/core-application-technical-boundary.md)、[原生多租户基线](../architecture/clean-native-multitenancy-baseline.md) |
| Application 与 Core 的正式 PHP Runtime 往哪里收敛 | [Core ThinkPHP 8 运行时收敛方向 ADR](../architecture/core-thinkphp-runtime-direction-adr.md)；当前 PDO 路径是迁移前事实，不是已完成状态 |
| 产品、Core、Edition、Module、客户实例如何编号 | [版本身份 ADR](../architecture/product-version-identity-adr.md)；当前实物仍由 manifests、locks、Release 固定 |
| 做到哪里、完成的证据是什么 | [能力账本](../product-status/capability-ledger.json)及其[生成视图](../product-status/README.md) |
| 正式发布了什么 | [Release 快照目录](../product-status/releases/)与 GitHub 不可变 Release |
| 线上跑的是什么 | [Deployment 快照目录](../product-status/deployments/)与[资源登记](../../resources/project-resources.json)；使用前重新核验健康与新鲜度 |
| 为什么作出当前决定 | [架构目录](../architecture/)、[主审计及替代关系](../maintenance/fact-convergence-audit-2026-09-09.md) |
| 如何区分事实、决定、计划、风险和失效结论 | [跨领域事实登记](fact-register.json)；领域数据仍回到各自唯一入口 |
| 下一步与哪些缺口有关 | [版本合同队列](../plans/application-scaffold-version-contract-queue.md)、[Storage 队列](../plans/storage-driver-extraction-queue.md)、[跨项目发布路线](../plans/product-release-operations-saas-roadmap.md) |
| 如何开发、验证、发布和维护文档 | [执行规则](../../AGENT_EXECUTION_RULES.md)、[发布控制器](../operations/consumer-ready-control.md)、[事实来源地图](authoritative-source-map.md) |
| 历史任务可信到什么程度 | 主审计的覆盖清单、来源任务、直接证据与已失效说明；聊天终态不替代代码或运行证明 |
| 历史闭环证据在哪里 | [历史决定与问题证据审计](../maintenance/conversation-audit-2026-09-09.md)、[source index](../maintenance/fact-audit-2026-09-09/source-index.json) 与[问题主登记](../maintenance/runtime-convergence-issue-register-2026-09-09.json)；公开材料只保留可发布的结论。根任务的本机恢复/coverage 控制未跟踪且不构成普通 clone 前置。 |
| 历史整改是否全部闭环 | [跨类别整改审计](../maintenance/runtime-convergence-audit-2026-09-09.md)与[问题主登记](../maintenance/runtime-convergence-issue-register-2026-09-09.json)；历史索引计数不等于逐项语义验证 |

## 当前执行与公开事实分域

本页不保存当前owner、writer、授权、失败预算、lease或恢复队列。受控任务从[执行规范](../../AGENT_EXECUTION_RULES.md#current-control-state)定位唯一私有状态；普通clone不依赖本机恢复文件。
[产品收敛计划](../plans/history-rules-product-convergence-plan-2026-09-10.md)定义已接受范围，完成情况仍由领域账本和固定证据证明。
Core包、Application Release与Instance部署是不同事实；协调发布中间态不得宣称完整产品发布。当前版本与状态读取上表直接来源，不在导航页复制易过时的版本号、candidate或步骤批准。
