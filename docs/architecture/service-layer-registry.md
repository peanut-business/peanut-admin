# Peanut Admin 服务层登记

机器可读事实源：[resources/service-registry.json](../../resources/service-registry.json)。当前执行 authority、writer、
失败预算和恢复点只在执行规则解析出的唯一私有控制状态中维护；服务登记和本文不派发任务，也不保存执行进度。

## 结论

当前登记 16 个内部复用服务。这里的“服务”是同一应用进程内的稳定责任、数据 owner、依赖和边界，
不是微服务数量，也不是完成状态。`maturity` 只描述当前能力形态；资格、发布、任务和候选状态回到各自事实源。

## 服务分组

### 始终可用的通用服务

Tenant 设置、管理身份与 RBAC、数据权限、字典与参考码、外部渠道绑定、Plugin/Module 治理、幂等命令、
统一审计和生成服务。

这些服务负责跨模块共享的规则、授权、事务边界和数据访问合同。业务代码应调用它们的 Commands/Queries/Runtime，不应直接写其他模块的私有表。

### 可启停的业务服务

通知与验证码、会员账户与 CRM、支付充值退款、OAuth 与微信渠道、Tenant 任务与异步执行、导入导出。

这些能力由官方 Module 承载，可以按租户启用或停用；Module 仍拥有自己的业务表和业务规则，通用服务不替代其领域逻辑。

### 混合边界

统一存储与文件媒体同时提供始终存在的 Driver/Account/Space 装配和可按租户使用的文件能力。生产资源、
凭据和资格状态由资源登记与发布证据负责。

## 使用规则

1. 共享写表只能有一个 owner；跨 Module 只能通过公开合同访问。
2. `current_implementation` 和 `actual_callers` 是登记基线的观察，不是目标结构或完成证据。
3. 目录与 namespace 的目标只引用 Application/Module 代码规范；存量路径随 S2/S3 完整切换。
4. 服务合同、owner、数据归属、依赖或稳定边界变化时更新本登记；实现进度进入现有问题登记，执行状态进入唯一私有控制状态。
5. CodeGraph 是按 worktree 建立的可再生索引；需要调用关系或影响范围分析时，先运行 `scripts/project-codegraph ensure`。

目录纠偏的固定顺序、目标、偏差处理和退出条件见
[现状差距与一次收敛路线](application-module-blueprint/adoption-roadmap.md)。
