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

历史审计起点：Application `origin/dev@ea9bc3a1dfaa844a8481b01d0341aa1ad749faa9`、Core `origin/dev@61546084e1e07f1c41df8d2383dbbe1d77a83b16`、Application `origin/main@8c8a974642450842100b9a9a323d447c7b409b4f`。阶段3静态核查基线另为 Application `origin/dev@ab96727c8b07da64489fe152b36e483f055dcf0c`、Core `origin/dev@2ed77f38ca26472d685cfeb81674a66ba23eadb4`；两组身份不可互相替代。当前正式 Application 发布基线仍是 v3.0.14 与历史 Core Alpha.13；Core PHP/Web 3.1.0 已按新资格发布，Application 3.1.0 仅处于消费锁与双 Edition 输入准备阶段，尚未完成固定候选资格或发布。

**Non-blocking checkpoint（2026-09-09）：**版本对象、同号规则、历史不可变性和发布依赖顺序已经固定，临时审计等待解除。独立业务开发、公开历史纠正和不依赖新包的工作可以继续；文件与运行资源仍按唯一 owner 规则处理。

3.1.0 的真实发布前置仍逐项生效。Core 固定候选 `985ee420b486a97325cdd0bb87412a76346aeb93` 已完成完整 Q01 与同候选九视角 D05；source tag、Composer split、npm、Packagist 和 GitHub Release 均已发布并由干净 Registry 消费者验证。Application 当前已把 Server/Web/Platform/PC/UniApp manifests 与 locks 指向真实 3.1.0 包，并采用产品/实例分字段的 V2 身份；双 Edition 生成输入、Creator 聚焦检查与 CR02-U 显式归属采用均已复核接受（交付 c5fea410）。完整 L2/P0-E、Application tag/Release、Edition 正式制品、客户 Instance 采用和部署仍未执行，归后续固定候选 Gate；不能把 Core 发布或当前开发锁当成 Consumer RC 或线上升级通过。

公开事实投影已发布并通过 HTTPS 正文核验；[部署回执](../maintenance/fact-audit-2026-09-09/docs-deployment-receipt.json)记录固定来源。主审计已交付明确覆盖范围、冲突裁定与历史拒绝操作处置，新产品发布保持单独的受阻状态。

上述历史交付不代表当前残留全部修复。阶段0–4、S5-T01、CQ及C01已验收；CR01开发修复`2278d9e1`与fixture补正`68812f1`也已验收：Module样板使用Services，25条Host精确纳管，客户所有权保护及真实升级合同通过。CR02已解除V1/Alpha.13前置并通过整应用生成检查，旧实例显式归属采用已通过双Edition聚焦升级验证；交付c5fea410已复核接受并集成dev。当前按[方案§12.7](../plans/history-rules-product-convergence-plan-2026-09-10.md)准备CR03固定候选、资源与独立安装升级资格，随后CR04交付；根核技术前置并安排新独立任务，不逐小步重复确认。C02–C12非消费阻塞项后置，保留原ID和ThinkPHP方向；不能用内部标签、版本数字或历史通过代替验证。已有安全/租户/数据和厂商支持目标不减，24–48小时仅为冲刺目标。详见原问题登记`consumer_delivery`；新候选资格、发布与部署尚未完成。不创建Goal或定时器，本机恢复控制继续保持未跟踪。
