# ThinkPHP 8 Runtime 收敛复核与执行报告

Document ID: `pa-docs-maintenance-runtime-convergence-audit-2026-09-09`

Status: `current`（首个 ModuleProvider 微批次已完成；Runtime 全量迁移未完成）

Owner: `product-architecture`

Reviewed at: 2026-09-09

## 结论先行

两仓正式技术栈已经确定为 ThinkPHP 8，但运行时收敛尚未完成。当前事实不是“框架中立已保留”，也不是“PDO 已移除”：Application 仍把 ThinkPHP 连接降为 PDO 并注入旧 persistence；Core 发布包仍以 PDO persistence 为公共实现。历史 Gemini 大重构及其被短路的测试没有进入当前 `dev`，应作为隔离证据保留，不应整体恢复或合并。

本轮完成了两个不改变业务语义的 Provider 修正批次：Application 四个 ModuleProvider 中 16 个确定无参数或纯别名闭包绑定改为接口到实现类的直接映射，删除了 Member Provider 中仅服务于这些闭包的 4 个辅助工厂方法。两批已合入并推送 `dev`。

## 固定基线与证据范围

| 仓库 | 当前基线 | 事实 |
| --- | --- | --- |
| Application | `ea9bc3a1dfaa844a8481b01d0341aa1ad749faa9` | 干净 detached worktree，来源为 `origin/dev` |
| Core | `61546084e1e07f1c41df8d2383dbbe1d77a83b16` | 干净 `dev`，与 `origin/dev` 对齐 |
| 历史大重构 | Application `1b2b66cd`、Core `90bf92f` | quarantine 取证；不进入当前 Runtime |
| 历史交接报告 | Gemini handover 与用户 pasted audit | 只用于追踪操作和错误模式；当前事实回到 Git、源码、测试和登记 |

## 追踪矩阵

状态词：`resolved` 已由当前证据闭环；`open` 尚未落地；`blocked` 有明确外部/资格停止线；`superseded` 不再是当前方向；`verified` 已核验但不是本轮代码修复。

| issue_id | 来源 | 当前源码/测试/文档证据 | 裁定与影响 | owner | 修正写集 | 验证命令 | 状态 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `RUNTIME-APP-001` | ADR §2.2；Gemini handover | `server/app/AppService.php` 的 `PDO::class`、`PdoTransactionManager`、`IdempotencyRuntimeFactory::forPdo` | ThinkPHP 连接被降级为 PDO；阻碍统一事务与公共 Runtime 收敛 | application-runtime | `AppService.php`、事务/幂等组合根及受影响调用者 | `rg -n -- 'PDO::class|PdoTransactionManager|forPdo' server/app`；对应域真实测试 | `open` |
| `RUNTIME-APP-002` | ADR §2.3；Core/Application 边界审计 | Article、Task、ImportExport Provider 仍构造 `Pdo*` 和 `CoreTenantRepositoryFactory` | 重复 persistence 图；不能通过改名或兼容桥解决 | application-runtime | 按固定顺序逐域替换并删除旧 PDO 路径 | 对应域测试、Tenant/事务/两 Edition 门禁 | `open` |
| `RUNTIME-APP-003` | ADR §2.5；本轮源码盘点 | 现有 7 个 `*RuntimeFactory.php`、213 处生产 `->make(`；本轮后 55 个 Provider binding 中 21 个直接类映射、34 个闭包，Provider 内显式 `make()` 为 95 处 | 仅移除确定无参数或纯别名工厂；其余闭包需按配置、SDK、回调、Worker 语义逐项裁定 | application-composition | `server/app/Modules/**/ModuleProvider.php`，逐批 | `php -l`；Provider 装载探针；现有 Module 合同 | `open` |
| `RUNTIME-CORE-001` | Core `docs/architecture/index.md`；Core Runtime ADR | `packages/php/*/src` 555 个 PHP 文件、82 个涉及 PDO、35 个 `Pdo*` 文件、47 个 Repository 中 26 个 `Pdo*Repository`，ThinkPHP 命中 0 | Core 公共 persistence 仍是 PDO；直接阻塞 3.1.0 Runtime 采用 | core-runtime | Core 按 ReferenceCodes → Identity/Tenant/RBAC 微批次修改 | Core 对应域测试；固定候选资格 | `open` |
| `RUNTIME-CORE-002` | Core host 盘点；ADR §2.4 | `backend`/`starter` 有 106 个 ThinkPHP 文件、89 个涉及 PDO，并保留多处 RuntimeFactory | 宿主已由 ThinkPHP 启动不等于公共包已迁移；需清理重复装配 | core-runtime | Core host composition root、ModuleProvider 与域实现 | Core host 静态检查及真实测试 | `open` |
| `BOOTSTRAP-001` | ADR §2.4、§7 | Application `server/database/environment-guard.php`、`install.php`、`seed-multi-tenant-demo.php` 仍是独立 PDO 入口；Core 安装/升级/健康/Worker 也有独立路径 | CLI、安装、迁移、Worker、Cron 尚未共用一个正式 ThinkPHP bootstrap | bootstrap-owner | 按 bootstrap 合同单独设计并在对应域原子落地 | 安装/迁移/Worker/双 Edition 真实门禁 | `open` |
| `VERIFY-001` | 用户 pasted audit；Gemini §6；执行规则 | 当前 canonical `server/tests` 不含历史 `PASSED + exit(0)` 占位；`scripts/check-test-integrity` 已存在；占位现场在 quarantine | 历史违规已隔离；不得恢复、弱化断言或把 skip 当通过 | test-integrity | 保持现有真实测试与完整性门禁 | `./scripts/check-test-integrity`（若入口要求参数则按脚本帮助执行）；受影响测试 | `resolved` |
| `DOC-001` | 当前 ADR、Core index、current-state | ADR 曾固定 Application `9781ce0d` / Core `6aeeb52`，而当前基线为 `ea9bc3a1` / `6154608`; current-state 也使用旧 dev 起点 | 文档与真实源码身份不一致，会误导后续迁移；本轮同步修正 | product-architecture | ADR、current-state、Core index | `./scripts/docs-governance check`；`./scripts/core-docs-governance check` | `resolved` |
| `MODULE-001` | Module publication contract；Gemini §7 | official Module 动态打包清单、bundled/package/published 分层已登记；`official.rich-text` 未被写成独立发布 | 发布规则已核验；不能把本地 tar、Demo overlay 或候选写成 published | module-governance | 无本轮写集；新 Module 必须走现有合同 | `php scripts/build-application-template-inventory --check`；Module 合同门禁 | `verified` |
| `MEDIA-001` | Gemini §8.1；quarantine `e915bea7` | 高容量媒体 spike 不在当前 Application/Core `dev`；当前产品保留四种 Storage Driver | Spike 只作设计输入，不得误合并或升级为生产能力；厂商支持未删除 | storage-owner | 无本轮写集；未来独立媒体纵向任务 | 分支/tree 关系与 Storage 装配测试 | `verified` |
| `BRANCH-001` | Gemini §2；事实审计 | 两仓远端当前只有 `dev/main`；App quarantine 分支保留，Core quarantine 分支保留 | 日常分支已收敛；隔离证据不能被当作已合入功能 | release-engineering | 本轮不删除 quarantine；完成候选后按规则清理任务分支 | `git branch -r`；`git worktree list --porcelain` | `verified` |
| `RELEASE-001` | current-state；Core 资格审计 | Core 3.1.0 资格曾在供应链组失败；Application 仍消费已发布 3.0.14/Alpha.13 | 新包发布、Application 新锁、双 Edition 和新 Release 均受阻；不改版本数字绕过 | release-qualification | 诊断失败输出、修复门禁可观测性、重新固定候选 | Core 固定 Q01 + D05；Registry 发布证据 | `blocked` |
| `VERSION-001` | 产品版本 ADR；用户已确认决定 | 不采用独立 Core `0.2.0-alpha.1`；新同号产品版本需统一候选 | 这是已确认决策，不再等待用户选择；历史版本保持不可变 | product-architecture | 无额外代码写集 | 版本 ADR、manifests、locks、Release 快照 | `verified` |

## 已执行批次

`RUNTIME-APP-003A/B`：只把以下确定无参数构造或纯别名工厂改成直接类映射：

- Article `ArticleQueries → ArticleQueryService`；
- Member 四个 `*Commands → *ContractService`，并删除未被其他代码调用的四个 Provider 辅助方法；
- OAuth `OAuthCallbackLocator → ThinkPhpOAuthCallbackLocator`；`ExternalTenantBindingRepository`、`ExternalChannelBindingStore` 和 `OAuthQueries` 的纯别名；
- Article `PublicArticleQueries → PublicArticleService`；
- Notification 三个查询/命令合同 → `NotificationApplicationService`；
- Payment `RechargeCommands`、`RechargeQueries`、`RefundReconciliationCommands` 的纯别名。

未改动任何 PDO、事务、Tenant、测试断言、版本、发布或外部资源。带配置、SDK、Console callback、动态 Worker 或仍需 PDO 的闭包保留，避免把“减少闭包”误当作 Runtime 迁移。

## 未完成项和解除条件

Runtime-APP-001/002、Runtime-CORE-001/002 和 Bootstrap-001 仍是实质未完成项。解除条件不是修改文档状态，而是每个领域在同一批次完成 ThinkPHP Model/Query/Db/Transaction、可信 Tenant/ExecutionContext、真实调用者、旧 PDO 删除以及对应 Tenant 隔离、事务/并发/两 Edition 门禁。Core 3.1.0 的发布阻塞还需要定位实际供应链失败、重新固定候选并取得真实 Registry 证据。

本轮没有需要用户产品决策的阻塞。真实云厂商账号、生产部署、正式发布和独立 Rich Text 发布仍按各自登记的人工/资格 Gate 处理，不在本修正批次中越权执行。
