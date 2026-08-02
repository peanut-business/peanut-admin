# Peanut Admin 校准后九角色反向复审

> 状态：PASS for new coding approval
>
> 复审日期：2026-07-15
>
> 初始校准基线：company-os commit `6dd15175`
>
> 最终复审输入：本文件同提交中的 37 至 45、47 号文档；复审中发现的问题先修事实源再给出裁决
>
> 结论：校准后的 G-01 至 G-09 可以请求新的 P0-A 编码批准；不表示 Runtime 已实现或可以发布

## 1. 本轮为什么重新复审

46 号文档完成首轮复审后，用户继续确认了会改变实现的业务事实：

1. 一个 Tenant 不只是“多门店”，而是可同时有多个类别、每类多个业务目标。
2. 一个 TenantMember 可以同时管理多个同类别目标。
3. 普通写、多目标读、聚合读和跨目标发布不能使用同一种模糊 Scope。
4. 统一共享主档必须保持一个真相源，不能拆成平台表和 Tenant 表两个 ID 空间。
5. 平台治理、Tenant 业务、代运营和未来跨 Tenant 协作必须继续分开。

47 号文档把这些事实冻结为校准输入；37 至 43 号文档和 45 号计划随后完成增量修改。本轮重新检查修改是否真的闭合，而不是只改状态文字。

## 2. 九角色结论

| 角色 | 结论 | 核心判断 |
| --- | --- | --- |
| 业务/产品 | 通过 | Tenant、成员、多类别、多同类目标和单次操作边界可以用自然语言解释；简单项目不会被迫理解复杂模型 |
| SaaS/租户架构 | 通过 | Tenant 仍是经营组织和隔离根；Store/Warehouse/Supplier 不会被提升为 Tenant；跨 Tenant 默认拒绝 |
| 身份与审计安全 | 通过 | Session 不保存 CurrentSubject；平台治理镜像事件有独立 platform_operator actor，不伪装成 Tenant 成员 |
| 功能与数据权限 | 通过 | TargetSet 单类别、operation 基数、目标候选权限、列表/详情/写入/任务保持同一 Provider 和默认拒绝 |
| 数据库与性能 | 条件通过 | TargetType registry、索引、大目标集合策略和 digest 已冻结；绝对性能仍必须在 Runtime D04 实测 |
| 后端 Module | 通过 | target/reference/work-item 三 Module 形成无环依赖，共享主档和消费者只能通过公开 Contract 协作 |
| 前端/Admin UX | 通过 | 零/单/多目标、归属列、聚合只读、策略发布和统一候选列表有确定行为，TenantContext 不受 UI 选择污染 |
| 开源维护 | 通过 | 示例不含 DCS/Finance 业务，真实依赖继续由 DDR 决定，旧仓和许可证停止线未放宽 |
| 低上下文交付 | 通过 | G-09 明确任务顺序、模型、文件白名单、示例表、测试编号、失败报告和提交停止线 |

“条件通过”只表示需要 Runtime 测量，不是设计缺口。没有角色留下必须由用户先选择的架构分支。

## 3. 本轮发现并已修复的问题

### R2-01 TargetSet 可以混入不同类别

问题：原 Target row 自带 target_resource_key，一个 TargetSet 可以同时塞 Project、Queue、Store 等 ID，执行者很容易把它当万能 Subject 集合。

修正：target_resource_key 上移到 TargetSet；一个 set 只能有一个类别。跨类别请求使用多个 typed sets。

### R2-02 Operation 没有目标基数

问题：`rule_filtered/explicit_targets` 只能说明授权方式，不能说明一次操作允许一个还是多个业务目标。

修正：ResourceOperation 增加 `none/one_required/many_readable/aggregate_read/policy_publish/bulk_write`。P0 普通写使用 one_required，bulk_write 默认禁用。

### R2-03 共享主档可能退化成 global_reference

问题：原 ownership 只有 tenant_owned/global_reference，商品一类有归属和作用范围的统一主档可能被误做成全站只读码表，或者被拆成两张表。

修正：增加 shared_master 和 SharedMasterScopeProvider，明确一个主档和 ID 空间；部署种子、Tenant 自建数据通过 owner/scope 关系共存。

### R2-04 平台治理镜像审计身份不成立

问题：平台创建 Tenant owner 时需要写目标 Tenant 镜像审计，但旧 actor_type 只有 member/tenant_system，会被迫伪装身份。

修正：增加 platform_operator actor 和快照字段，只允许 owner provisioning、Tenant 生命周期、TenantModule 治理等固定 action。它不能记录租户业务操作。

### R2-05 缺少 TargetType 正式目录

问题：Operation 可以引用 `example.project`，但没有地方声明该类型归哪个 Module、使用哪个 Resolver/CatalogProvider。

修正：增加部署级 `pa_target_type`。它只登记类型契约，不保存业务实例；重复 owner、缺 Provider 或 retired 均 fail closed。

### R2-06 虚构示例出现循环依赖

问题：如果 Reference 按 WorkItem Project 做 scope，而 WorkItem 又依赖 Reference，会形成循环。

修正：拆成 `example.target -> example.reference -> example.work-item` 的单向图；WorkItem 同时依赖 target/reference。每个 Module 只写自己的表。

### R2-07 数据权限配置页可能枚举全部目标

问题：只有 `core.role.data-policy.manage` 时，管理员可能借 selector 查询未授权的业务目标。

修正：operation target type 增加 policy_selection_permission。配置模式必须同时通过 core 管理权限和 Module 管理权限；该权限不自动授予业务读写。

### R2-08 默认根 Department 被误当 Kernel 不变量

问题：创建 Tenant 时若强制建立根部门，会把产品初始化偏好写成永久模型。

修正：Kernel 允许没有 Department；ProductProfile 可以显式、幂等创建默认根部门。无部门范围得到空集合，不回退为全部。

### R2-09 旧 G-09 不能证明新模型

问题：旧示例只有模糊 target，低上下文执行者仍需猜表、Provider、目标数量和共享数据来源。

修正：C02 冻结三个示例 Module、七张示例表、字段约束、Project A/B/C 权限差异、Reference 单真相和策略发布事实。

## 4. 关键闭环复核

### 4.1 Tenant 与业务目标

```text
Tenant Alpha
  TenantMember M
  Project A / B / C
  Queue A

M read Project {A,B}
M update Project {A}
```

Project/Queue 由 Module 管理，不进入 Kernel。M 只有一条 TenantMember；读 A/B 和写 A 是不同 ResourceOperation 的授权集合。

### 4.2 请求与会话

TenantSession 只固定 Tenant、Account、TenantMember。页面选择 Project A 后，后端验证 typed target 并生成当前调用链的 AuthorizedOperationContext；下一请求选择 B 时重新生成，不修改 Session。

### 4.3 单目标和多目标

- 列表可按 many_readable 查询 A/B。
- 汇总可按 aggregate_read 读取 A/B，只返回授权范围。
- 普通 update 必须 one_required，只更新 A 或 B 中明确的一个；M 写 B 仍拒绝。
- policy_publish 保存一份策略和逐 Project publication。
- bulk_write 在 P0 没有可用 endpoint、菜单或默认开关。

### 4.4 统一共享主档

ReferenceItem 的部署种子和 Tenant 自建记录使用同一表、同一 ID 类型和同一 candidates API。Reference scope 决定 Tenant/Project 是否 view/use/maintain；WorkItem 只保存稳定 reference_item_id，并通过公开 Contract 校验，不 JOIN Reference 表。

### 4.5 平台与跨 Tenant

PlatformOperator 可以创建、暂停和治理 Tenant/Module，但不能用平台 token 调用 target-candidates、Reference candidates 或 WorkItem API。P0 member/system 操作方 Tenant 必须等于目标 Tenant；未来委托/加盟需要独立 relation/grant 和 Guard。

## 5. 仍保留但不阻塞 P0-A 的风险

| 风险 | 处理任务 | 当前停止线 |
| --- | --- | --- |
| ThinkPHP、迁移、缓存、OpenAPI 工具的精确版本与许可证 | P0-A02 DDR | 未 Accepted 不安装 |
| 10/500/5000 targets 的真实 SQL 和 p95 | P0-B04/B05、P0-D04 | 无 EXPLAIN/基准证据不发布 |
| SharedMasterScopeProvider 在 DCS Product/SKU 的真实映射 | DCS 自己的业务架构与 Module | 不写进 Peanut P0 示例 |
| 代运营、加盟和跨 Tenant 协作 | P1/P2 独立设计 | P0 默认拒绝 |
| 手机号、多凭证、邀请、文件、任务 UI、插件市场 | P1/P2 | 不进入 P0 |
| 商业主控端、授权和远程升级 | 后续独立产品 | 不进入 Kernel |

这些事项都有明确阶段和默认行为，不要求 P0 执行者自行猜测。

## 6. 最终裁决

```text
post-calibration business model: PASS
tenant and platform isolation: PASS
typed target and data authorization: PASS
shared-master single source contract: PASS
module dependency and ownership: PASS
API and Admin UX alignment: PASS
security and performance test coverage: PASS WITH RUNTIME MEASUREMENT
low-context executability: PASS
runtime implementation evidence: NONE YET
next allowed action: request explicit P0-A coding approval
```

新的建议批准语：

```text
批准按 48 号复审结论开始 P0-A 运行时代码；Peanut Admin 顶层许可证采用 Apache-2.0。
```

未收到这句明确语义前，不得创建 `/Users/xing/Documents/Dev/Project/peanut-admin/`，不得创建 GitHub repository，不得执行 P0-A01。
