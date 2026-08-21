# Peanut Admin 产品发布、运营平台与 SaaS 路线

> 状态：当前跨项目执行路线
> 决策日期：2026-08-15
> Peanut Admin 产品状态事实源：`docs/product-status/capability-ledger.json`
> 应用内多租户计划：`docs/plans/multi-tenancy-platform-management-plan.md`
>
> 当前状态（2026-08-20）：`v2.1.5` 已完成固定 tag、P0-E 7/7、dev 合入、GitHub Release、
> Standalone 升级、多租户 Demo 部署和不可变发布快照；见
> `docs/product-status/releases/v2.1.5.json`。下文 R0/R1 的 `v1.1.x` 记录是历史路线证据，
> 不应重新领取或当作当前 2.x 未发布。
> R1 状态：`v1.1.3` 已于 2026-08-15 达到 `production-demonstrated`；OP02 的 Release
> 前置已解除，但独立运营平台实现仍未开始。

## 1. 目标与顺序

当前目标不是停在 P0-E 或“发布就绪”，而是完成一条可由真实下游消费的正式发布链：

1. 固化 Peanut Admin 的唯一发布身份和不可变 scaffold；
2. 完成 `dev -> main -> annotated tag -> GitHub Release -> 演示生产升级 -> smoke`；
3. 允许其他项目只从该正式 Release 生成独立应用并开始业务开发；
4. 在同级独立项目 `peanut-operations-platform` 建设跨应用运营平台；
5. 让已生成应用通过显式升级和实例协议接入运营平台；
6. 在运营闭环稳定后，再启动套餐、订阅、计费等 SaaS 商业化。

运营平台设计可以与 Peanut Admin 发布并行，但其 Runtime 不进入 Peanut Admin 仓库；依赖
正式 scaffold 的生成、接入和升级实现，必须等待下文的“可消费”Gate。

## 2. 完成层级

| 层级 | 判定 | 是否可供下游使用 |
| --- | --- | --- |
| `qualified` | 固定候选的 P0-E/升级/浏览器闭环通过 | 否，只证明候选质量 |
| `release-ready` | 版本、代码、scaffold、账本、Release metadata 一致 | 否，尚无正式不可变发布 |
| `released` | `main`、annotated tag、GitHub Release 和附件一致 | 有条件，仍需一次正式消费验证 |
| `consumable` | 从正式 Release 生成新应用，完成依赖、安装、启动和最低浏览器验证 | 是 |
| `production-demonstrated` | 登记的演示生产环境升级并通过最低 smoke | 是，且具有线上运行证据 |

只有 `consumable` 或更高状态可以作为其他项目的生成输入。移动分支、开放 PR、本地提交、
候选 digest 或仅通过 P0-E 的制品都不能成为下游基线。

## 3. 当前并行工作线

### R0：候选资格与发布准备

已完成。P0-E 最终 run `p0er815b1` 为 16/16 通过且零资源残留，发布准备进入
`v1.1.3@f0b2b81acd792b05404e0e4897ec61e85c131041`。

### R1：最终发布负责人

已完成。正式 Release、下游消费、配对生产备份、50→54 迁移和最低生产 smoke 已封存到
`docs/product-status/releases/v1.1.3.json`；该快照是本轮 R1 的不可变完成证据。

R1 的串行职责：

1. 接收并核验 R0 的固定候选、commit/tree、run、PR、lease 和残留资源；
2. 解决目标版本、scaffold 版本、根 metadata 与 Release metadata 的唯一身份；
3. 合入 `dev` 并确认最新 head 的 required checks 全绿；
4. 只运行版本、inventory、diff、构建和发布合同所需的最低最终 Gate；
5. 合入 `main`，创建 annotated tag 和 GitHub Release；
6. 从正式 Release 做一次新应用消费验证；
7. 按登记的备份策略升级 `oracle3:/www/docker/peanut-admin`，不重建宝塔/Nginx 反代；
8. 对 `peanut-admin.007345.xyz:443` 做登录、API、核心页面、数据库迁移和 TLS 最低 smoke；
9. 更新能力账本和不可变发布快照，释放 lease 并清理本次临时资源。

### OP01：应用运维平台（Ops Platform）产品与架构设计 — [可执行 / 无阻塞]

应用运维平台（Peanut Application Ops Platform，专注于已部署实例的健康监测、备份存证、授权维护与无感远程平滑升级）即日起作为独立立项启动，无前置阻塞：

- **架构定位与边界**：
  - 区别于系统内部的租户平台管理（Platform 端：前端独立、后端共享同一个 API Host 服务）；
  - **应用运维平台是一个完全独立的、由 Peanut Admin 脚手架（`create-app`）派生构建的独立顶层应用**（拥有独立仓库、独立数据库、独立部署环境与独立的业务生命周期）；
- **核心运维模型**：
  - Application、Release、Artifact、DeploymentInstance（生产/预发常驻实例）、Environment、Entitlement；
  - UpgradePlan/Run、BackupEvidence、HealthSnapshot、OperationsAuditEvent；
  - 实例出站 Agent 协议、非侵入式签名任务下发、幂等执行、离线高可用策略；
- **环境管控分层原则**：
  - **生产/预发常驻环境**：接入运维平台，享受自动备份证明、签名版本分发与平滑远程升级服务；
  - **本地开发环境**：保留本地 Git 分支合并与 CLI 工具（`scaffold-upgrade`）自主升级，不进行远程强推管控。

### OP02：首个应用登记与远程平滑升级闭环 — [可执行 / 无阻塞]

基于已达到 `consumable` 的正式 Release 脚手架生成运维平台自身工程，并闭环实现：

1. **实例服务登记**：安全登记已部署的 Peanut Admin 生产/演示实例（由实例主动出站握手，不保存用户敏感密码，不直连业务库）；
2. **状态与健康巡检**：查看实例当前运行版本、服务健康快照及最近备份证明；
3. **签名远程升级服务**：在运维平台创建并签名目标版本升级任务；
4. **实例端幂等执行**：实例端 Agent 校验签名后，在本地安全调用 `scripts/scaffold-upgrade` 与部署升级链路；
5. **双向审计与零损回滚**：回传升级执行结果、自动化 Smoke 验证及审计日志；若失败自动回滚；
6. **离线自治**：运维平台离线或网络波动时，业务实例核心服务不受任何影响。

## 4. 发布交接合同

每个 R0 owner 只交接一次，至少包含：

- 仓库、分支、最终 40 位 commit 和 tree；
- candidate/scaffold identity 与目标版本；
- 已完成 Gate、run ID、证据位置和未运行项；
- PR/head/checks 状态；
- lease owner、数据库、容器、端口和 output 生命周期；
- 精确剩余 blocker 与允许继续推进的工作。

R1 接收后先比对远端 `dev`、开放 PR、Git worktree 和 lease。候选字节不变时只恢复失败
checkpoint 或运行尚缺 Gate；纯文档、账本、Release 说明和不改变 scaffold inventory 的
metadata 不触发完整 P0-E。只有产品代码、managed scaffold 字节、依赖锁、迁移或运行配置
改变时，才按影响范围重新冻结候选并重跑受影响 Gate。

## 5. 正式发布完成条件

以下条件缺一不可：

- 版本、package/runtime、scaffold、根 metadata、tag 和 GitHub Release 身份唯一；
- 最新 `dev`/`main` 和 tag 指向已记录 commit/tree；
- 正式 Release 生成的新应用可安装、启动、登录并进入业务开发；
- 旧应用升级的 managed/app-owned 边界和 recover 合同有固定证据；
- 演示生产已备份或明确登记为可重建，并完成升级与 smoke；
- 能力账本和 `docs/product-status/releases/` 快照与实际发布一致；
- 无本次任务遗留的 lease、测试数据库、容器、监听端口或临时输出 owner。

生产确认为无保留价值的演示数据时，可以选择从正式 Release 重建，而不是设计复杂迁移；
但该决定必须先登记“可重建”、执行最低备份/快照并保留恢复责任，不能仅凭聊天判断。

## 6. 独立运营平台边界

运营平台是与 Peanut Admin 同级的独立应用、仓库、数据库和部署单元。它管理应用群的
Release 和运行状态，不经营客户业务，也不成为业务实例的超级管理员。

禁止：

- 把运营平台 Runtime 写入 Peanut Admin/core；
- 直连业务实例数据库或保存客户管理员密码；
- 复用 PlatformOperator、TenantMember 或业务 Account；
- 汇总租户业务数据作为默认能力；
- 让运营平台在线状态成为业务实例可用性的前置条件；
- 以 SSH 直连作为公共实例管理协议。

Peanut Admin 只提供最小实例接入协议：实例身份、Release manifest、健康、备份证明、任务
签名校验、升级状态和审计回执。实例默认主动出站连接运营平台。

## 7. SaaS 商业化启动条件

运营平台 MVP 完成不等于完整 SaaS。启动 SaaS-FUTURE 前至少应有：

- 两个真实生成应用或部署实例完成接入；
- 一次实际升级、备份和恢复/回滚演练；
- 稳定的实例、Release、授权和审计模型；
- 明确的 Tenant、客户、合同主体、套餐和 Entitlement 映射；
- 套餐、订阅、试用、续费、停用、计费和支付的独立业务合同；
- 数据隔离、安全、隐私、支持和故障责任边界。

达到这些条件后，SaaS 阶段才建设面向加盟商或客户的软件租用能力。完整商业计划仍以
`docs/plans/saas-enhancement-development-plan.md` 为未来范围，不因 OP01 立项自动启动。

## 8. 需要用户参与的停止线

普通实现、CI、合入、发布和登记目标内的非破坏性生产升级由 owner 连续完成。只有下列
事项需要用户介入：

- 无法由现有事实消解的最终产品版本选择；
- 会删除或不可逆迁移有价值生产数据；
- 缺少目标仓库、Registry、GitHub 或生产环境权限/凭据；
- 真实付费、合同、许可证或商业套餐决策。
