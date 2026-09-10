# Peanut Admin 当前事实入口

Document ID: `pa-docs-current-state`

Status: `current`

Owner: `product-architecture`

本页是导航与恢复入口，不维护第二份能力账本。先确定对象、状态与证据范围，再进入对应事实源。

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
| 本次闭环任务到哪里、下一步是否获准 | [历史决定与问题证据审计](../maintenance/conversation-audit-2026-09-09.md)、[source index](../maintenance/fact-audit-2026-09-09/source-index.json) 与[问题主登记](../maintenance/runtime-convergence-issue-register-2026-09-09.json)；公开材料只保留可发布的结论。根任务的本机恢复/coverage 控制未跟踪且不构成普通 clone 前置。 |
| 历史整改是否全部闭环 | [跨类别整改审计](../maintenance/runtime-convergence-audit-2026-09-09.md)与[问题主登记](../maintenance/runtime-convergence-issue-register-2026-09-09.json)；当前仍在内容复核和整改，历史索引计数不等于逐项语义验证 |

## 本轮事实收敛 checkpoint

历史审计起点：Application `origin/dev@ea9bc3a1dfaa844a8481b01d0341aa1ad749faa9`、Core `origin/dev@61546084e1e07f1c41df8d2383dbbe1d77a83b16`、Application `origin/main@8c8a974642450842100b9a9a323d447c7b409b4f`。阶段3当前静态核查基线另为 Application `origin/dev@ab96727c8b07da64489fe152b36e483f055dcf0c`、Core `origin/dev@2ed77f38ca26472d685cfeb81674a66ba23eadb4`；两组身份不可互相替代。发布基线仍是 Application v3.0.14 与历史 Core Alpha.13；新统一产品目标 3.1.0 尚无发布结论。

**Non-blocking checkpoint（2026-09-09）：**版本对象、同号规则、历史不可变性和发布依赖顺序已经固定，临时审计等待解除。独立业务开发、公开历史纠正和不依赖新包的工作可以继续；文件与运行资源仍按唯一 owner 规则处理。

3.1.0 的真实发布前置仍逐项生效：Core 必须完成新固定资格与 Registry 发布，Application 才能采用新锁；双 Edition 和客户 Instance 采用继续依赖各自候选与部署证据。Core 在两次固定资格尝试中均停于供应链审计，具体失败子命令未知；九角色复核通过不能替代 Q01。达到重试停止线后，仅修复失败报告保留机制并完成新资源清理，未启动第三轮资格。解除条件是重新定位真实审计失败、完成必要修复并取得新候选 Q01 和真实 Registry 发布。Application 版本消费者实现与事实文档已准备，根合同及实际锁仍保持已发布的 3.0.14/Alpha.13；新 3.1.0 采用、完整 L2、Edition 制品和 Release 尚未执行。不能把本 checkpoint 当成新 Release 或线上升级通过。

公开事实投影已发布并通过 HTTPS 正文核验；[部署回执](../maintenance/fact-audit-2026-09-09/docs-deployment-receipt.json)记录固定来源。主审计已交付明确覆盖范围、冲突裁定与历史拒绝操作处置，新产品发布保持单独的受阻状态。

上述历史交付不代表当前残留全部修复。阶段0–4、S5-T01、CQ及C01已验收；CR01开发修复`2278d9e1`与fixture补正`68812f1`也已验收：Module样板使用Services，25条Host精确纳管，客户所有权保护及真实升级合同通过。整应用生成仍受V1/Alpha.13前置阻塞，尚未通过。当前按[方案§12.7](../plans/history-rules-product-convergence-plan-2026-09-10.md)进入CR02协调发行，随后CR03独立安装升级资格与CR04交付；根核技术前置并安排新独立任务，不逐小步重复确认。C02–C12非消费阻塞项后置，保留原ID和ThinkPHP方向；不能用内部标签、版本数字或历史通过代替验证。已有安全/租户/数据和厂商支持目标不减，24–48小时仅为冲刺目标。详见原问题登记`consumer_delivery`；新候选资格、发布与部署尚未完成。不创建Goal或定时器，本机恢复控制继续保持未跟踪。
