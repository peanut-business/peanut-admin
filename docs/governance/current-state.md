# Peanut Admin 当前事实入口

Document ID: `pa-docs-current-state`

Status: `current`

Owner: `product-architecture`

本页是导航与恢复入口，不维护第二份能力账本。先确定对象、状态与证据范围，再进入对应事实源。

| 要回答的问题 | 唯一事实入口 |
| --- | --- |
| 产品是什么、谁拥有哪些职责 | [Core/Application 技术边界](../architecture/core-application-technical-boundary.md)、[原生多租户基线](../architecture/clean-native-multitenancy-baseline.md) |
| 产品、Core、Edition、Module、客户实例如何编号 | [版本身份 ADR](../architecture/product-version-identity-adr.md)；当前实物仍由 manifests、locks、Release 固定 |
| 做到哪里、完成的证据是什么 | [能力账本](../product-status/capability-ledger.json)及其[生成视图](../product-status/README.md) |
| 正式发布了什么 | [Release 快照目录](../product-status/releases/)与 GitHub 不可变 Release |
| 线上跑的是什么 | [Deployment 快照目录](../product-status/deployments/)与[资源登记](../../resources/project-resources.json)；使用前重新核验健康与新鲜度 |
| 为什么作出当前决定 | [架构目录](../architecture/)、[主审计及替代关系](../maintenance/fact-convergence-audit-2026-09-09.md) |
| 如何区分事实、决定、计划、风险和失效结论 | [跨领域事实登记](fact-register.json)；领域数据仍回到各自唯一入口 |
| 下一步与哪些缺口有关 | [版本合同队列](../plans/application-scaffold-version-contract-queue.md)、[Storage 队列](../plans/storage-driver-extraction-queue.md)、[跨项目发布路线](../plans/product-release-operations-saas-roadmap.md) |
| 如何开发、验证、发布和维护文档 | [执行规则](../../AGENT_EXECUTION_RULES.md)、[发布控制器](../operations/consumer-ready-control.md)、[事实来源地图](authoritative-source-map.md) |
| 历史任务可信到什么程度 | 主审计的覆盖清单、来源任务、直接证据与已失效说明；聊天终态不替代代码或运行证明 |

## 本轮事实收敛 checkpoint

审计起点：Application `origin/dev@fc6796c75eeea2c71592bf9a4534d5941426cbf9`、`origin/main@8c8a974642450842100b9a9a323d447c7b409b4f`。发布基线仍是 Application v3.0.14 与历史 Core Alpha.13；新统一产品目标 3.1.0 尚无发布结论。

**Non-blocking checkpoint（2026-09-09）：**版本对象、同号规则、历史不可变性和发布依赖顺序已经固定，临时审计等待解除。独立业务开发、公开历史纠正和不依赖新包的工作可以继续；文件与运行资源仍按唯一 owner 规则处理。

3.1.0 的真实发布前置仍逐项生效：Core 必须完成新固定资格与 Registry 发布，Application 才能采用新锁；双 Edition 和客户 Instance 采用继续依赖各自候选与部署证据。当前仅完成版本消费者的聚焦升级检查，不能把本 checkpoint 当成新 Release、完整 L2 或线上升级通过。
