# Gemini 大重构收敛审计与执行报告（2026-09-09）

Document ID: `pa-docs-plans-gemini-refactor-convergence-audit-2026-09-09`

Status: `current`

Owner: `product-architecture`

Audience: `maintainer, architect, operator, ai`

Fixed inputs: Peanut Admin `dev@e38e45d07752cd6b4834fbe4483bfd2dcaf5a95d`、Application
quarantine commit `5fbcf043ff45ba85290ceac1d3b52c4440c79e3e`、Peanut Admin Core
`dev@9358686fee873dd235489c8794abf556fd70ec4f`、Core quarantine commit
`9d5f03e79c4790c652106b9376f87a5cb9fd89ac`、Luna remediation candidates
`190d47352adcd996384fcf83cd58524204fd1844` / `22f6a6cc5ae5bb56aefd8b625c86f9cdcf630aea`。

> 本报告把 Gemini 的机械处理视为未完成迁移工件，而不是动机错误或可继承的通过证据。目标是吸收有
> 价值的设计与行为，正式重做缺失实现和测试，再清理隔离现场。用户已授权后续发布、生产与精确破坏性
> 清理；授权不替代候选身份、备份、资格和线上 smoke。

## 1. 总结裁定

1. 两个 `dev` 仍是干净、可追溯的 canonical；两个 quarantine 提交及其脏工作区都没有进入 `dev`。
2. Gemini 大迁移不是应当整体撤销的“错误想法”。Module 物理聚合、减少应用层重复抽象、采用 ThinkPHP
   原生 Model/Query/Scope 都是可继续的目标；但当前候选混合了互斥架构、残留引用、未跟踪 Runtime、机械
   测试占位和文档先行声明，不能直接合入。
3. Core 必须保持产品中立和 caller-owned transaction。把 `think\\facade\\Db` 扩散到 Core package
   不是应用层精简，而是改变公共包消费合同；当前没有批准或资格支持该方向。
4. 现行应用层锁定为 ThinkPHP 原生 Model/Query/Scope + 构造函数注入。不得仅为隔离框架新增 Repository/
   Port/Adapter，也不得在业务方法内用 `app()` 定位依赖；跨 Module 公开合同和真实外部 Provider adapter
   仍可保留。
5. 测试占位是迁移期间的显式临时工件，可以用来解开机械编辑顺序，但绝不能参与通过判断。正式处理是从
   canonical 的真实测试重放每个微批次，并让新门禁拒绝占位，不是在脏候选中继续修补“通过字符串”。

## 2. 仓库与分支事实

| 仓库/产物 | 身份 | 状态 | 裁定 |
| --- | --- | --- | --- |
| Application canonical | `dev@e38e45d0` | 与 `origin/dev` 对齐 | 后续收敛基线 |
| Application quarantine | commit `5fbcf043` + 440 tracked/148 untracked | 647 文件提交；当前现场扩大到 814 个差异文件 | 保留证据，禁止整体合入 |
| Core canonical | `dev@9358686` | 与 `origin/dev` 对齐 | Core 修复与发布基线 |
| Core quarantine | commit `9d5f03e` + 444 tracked/182 untracked | 154 文件提交；当前现场含未登记模块树 | 保留证据，禁止整体合入 |
| Application Luna | `190d4735` | 基于当前 Application dev，2 commits ahead | 可继续收敛，未完成全部停止线 |
| Core Luna | `22f6a6c` | 基于当前 Core dev，1 commit ahead | 可继续收敛，未发布 |
| Storage adoption | `64460af8` | 旧 `590e6183` 已重放到当前 dev，1 commit ahead | 依赖版本阻塞，禁止现在合入 |
| High-capacity media spike | `quarantine/high-capacity-media-storage-spike-20260831@e915bea7` | 已恢复显式引用，未进入 dev/main | 设计输入，不是产品能力 |

Application quarantine 的 148 个未跟踪文件含 139 个 Python 和 9 个 PHP；已提交根目录还含 22 个
迁移/修复脚本。Core 实际未跟踪文件是 182 个，不是早先报告中的 28 个，其中包括 18 个 `module.json`
和 14 个无法解析的 ModuleProvider。清理前必须以包含未跟踪清单的恢复证据重新固定一次。

## 3. 已合入 Gemini 变更

### 3.1 `687da34a`：Controller Context 可选注入

该提交让三个 Base Controller 在未显式收到 `CurrentExecutionContext` 时从 ThinkPHP `App` 容器解析，
并新增真实的 `ControllerContextOptionalInjectionTest`。它没有删除 Context，也没有把 facade 引入业务
Service；其目的是兼容框架 Controller 构造行为。

当前裁定为 **保留但受限**：只允许 Base Controller 构造阶段的框架适配，不得扩散到业务方法或 Module
Application Service。后续 composition root 若能稳定显式注入，可在对应纵向切片中删除 fallback；在此之前
不能把它与 quarantine 中的全局 Context 剥离混为一谈。

### 3.2 `3b4e5e56`—`e38e45d0`：Module 架构文档

这组五个提交先后把目标写成 `modules/<slug>/{server,web}`、全小写 PHP namespace、纯数组 route、删除
`Application`/`ModuleProvider`，并在开发指南中表述为已经完成。当前 `ModuleHostLayout`、生成器、autoload、
真实 Module 和路由均仍使用 `server/app/Modules/<Vendor>/<Module>`、PSR-4 PascalCase、`Http/routes.php`
和 `ModuleProvider`，因此原指南是已合入 `dev` 的事实冲突，而不只是未来建议。

本批已正式修正现行 `module-development-guide.md`、`plugin-module-development.md` 和 coding standards：
当前结构与目标结构分开；跨 Module 合同只在有真实消费者时保留；应用服务直接使用 ThinkPHP
Model/Query/Scope；路由由宿主中间件保护。更大的 Application/Module blueprint 继续只作为 target/roadmap，
不能被用作 Runtime 已迁移证据。执行它时按单 Module 纵向微批次更新生成器、autoload、route、Plugin lock、
测试与文档，禁止再次全库替换。

## 4. Application quarantine 审计

- 提交相对基线涉及 647 个文件（+3,832/-4,676），包含 323 次 rename 和 100 create/delete；当前脏现场
  扩大到 814 个差异文件（+4,780/-26,116）。
- 10 个 Module 被移动到 `modules/*/{server,web}`，Plugin roots 和宿主 route 转发同步修改。这一物理
  聚合目标可以保留，但必须在生成器、autoload、ModuleHostLayout、打包和真实测试同一微批次中重做。
- 候选同时保留/新增 §6.1 禁止的 Repository/Adapter 镜像，并在多个业务 Service 中引入 `app()`；它既
  没完成“去抽象”，也没有遵守唯一 composition root。
- 存在手写 `where('tenant_id', ...)`、`forTenant()` 等 Tenant 红线命中；ImportExport 还引用已经删除的
  `PdoModuleGovernanceProvider`，形成确定的 autoload 断链。
- 当前 PHP lint 未发现其余现存差异文件语法错误，但 lint 不验证容器 binding、类存在、Tenant、事务或行为。
- 79 个差异测试中 74 个被改成短 `PASSED + exit(0)`。canonical 测试正文仍在 `dev`，所以不从脏文件
  “恢复断言”；每个迁移微批次以 dev 的真实测试为基础吸收对应源码差异。
- 未发现运维平台、DCS、采购或库存等另一项目业务渗入。

## 5. Core quarantine 审计

- 提交相对基线涉及 154 个文件（100 runtime、54 tests，+5,378/-6,392）；当前现场为 444 tracked
  差异（+2,730/-11,987）和 182 untracked。
- `think\\facade\\Db` 已扩散到 81 个 package PHP 文件，同时删除 26 个 persistence 接口/实现，却留下
  596 条新增/变化的旧 PDO/新 ThinkPHP 符号引用。
- 14 个未跟踪 ModuleProvider 的 namespace 含连字符，PHP parse 必然失败；对应 manifest/provider 路径也
  不在当前 Composer/Module layout 的扫描范围。
- `WorkflowRuntime`、`EntitlementQuotaService`、`AtomicOperationAdapter`、`ArtifactRevisionService`
  分别存在未定义变量、已删除类型或未导入类的确定运行时断裂。
- `TenantColumnScope` 把按 PDO 对象隔离的 `WeakMap` 缓存改成仅表名缓存；同一进程切换连接后可能错误跳过
  Schema/Edition 校验。这是迁移新增的 Tenant 安全风险，不能吸收。
- Core 的 25 个文件/38 个 `markTestSkipped` 不是本候选新增；它们是由专用 MySQL/脚本 Gate 接管的测试
  入口，不应简单删除，也不能在正式资格中把 skip 计作通过。

Core 的合理精简只能发生在参考 Host/Application composition：Host 可以用 ThinkPHP 装配 Core contract，
Core package 本身继续通过显式 PDO/transaction handle 保持产品中立和同事务可消费性。

## 6. 测试脚本的正式处置

本批新增 `scripts/check-test-integrity` 并接入 `scripts/ci-server-check.sh` 的 fast/full 入口。它拒绝非
fixture 测试中短小、无控制/断言、无条件输出 pass 后 `exit(0)` 的占位形态；canonical 当前检查通过。

后续恢复规则：

1. 不复制 quarantine 的 74 个测试文件；以 `dev` 原测试作为断言源；
2. 每个 Module/域只重放它的 Runtime 差异，并运行该域真实测试；
3. 数据库、浏览器、Provider 资源不可用时返回明确 blocked/stopped，不能改成 pass；
4. Core 现有 `markTestSkipped` 由对应专用 Gate 执行，本轮同时收敛聚合入口覆盖遗漏包；
5. 最终固定候选运行完整资格时，任何必须场景未执行都阻塞相应 qualified/release 状态。

## 7. Module 发布规则与 Rich Text

本批新增 [Module 发布与制品合同](../architecture/module-publication-contract.md)，明确 authored、
author-ready、bundled-locked、package-candidate、qualified、published 六种状态，并明确自包含 tar 的内容。
`composer.json`/前端 `package.json` 是 tar 内组件身份，不代表已发布到 Composer/npm Registry；当前前端
package 保持 `private: true`，Marketplace 仍 blocked。

`AllModulesPackagingTest` 不再硬编码 8 个 Module，而是从 `plugins.lock` 自动发现全部 `official.*`
Module。`official.rich-text` 因而进入打包资格，未来新增 official Module 也不能静默漏测。

Rich Text 当前只达到 **bundled-locked**：源码随 v3.0.13 source Release 冻结；archive、签名、SBOM
未签发，review/漏洞响应未建立，线上 Demo 仍是 v3.0.12。Tiptap/ProseMirror/Yjs 客户端已实现，仓库没有
Hocuspocus 服务端；原专用浏览器会话未完成。它不能被描述为独立 published 或生产协同编辑已可用。

## 8. 媒体与厂商支持

### 8.1 高容量媒体 Spike

`e915bea7` 是 `e3c940ea → a5b2f604 → e915bea7` 三个连续实验提交，不是多个已合产品分支的合并结果。
它验证 SeaweedFS S3 thin adapter、multipart、checksum/range、Tenant/ACL/key、quota reservation、扫描/
隔离、derivative、retention/legal hold/deletion 和成本模型。现有证据仅是约 5 MB 单节点样本和 11 项检查，
没有证明并发、GB/TB、HA、Object Lock/KMS、生产安全或正式 FileMedia/Rich Text 集成。

当前影响为零 Runtime 影响：它从未进入 dev/main。已建立 quarantine branch 防止 reflog 丢失；后续只把
协议和测试场景作为新纵向媒体任务输入，不直接合并或部署实验目录。

### 8.2 Storage Driver 与厂商

Core `9358686` 和 Application adoption `64460af8` 均保留 Local、Aliyun OSS、Tencent COS、Qiniu 四种
driver。Luna Core `22f6a6c` 修正七牛上传返回 key 一致性和删除 endpoint。厂商支持不会因为提取而删除。

“保留源码”不等于“可用”：Application 仍锁定不含新 Driver 的 Core alpha.12。必须先合入/资格 Core 修复、
发布新的不可变 Core 包、更新 Application Composer lock，再采用 `64460af8`，并分别验证四 provider 的装配、
凭据隔离、上传/下载/删除和失败补偿。LocalDriver 还需补路径/symlink、原子写入和失败恢复合同。

## 9. `590e6183` 的准确解释

`590e6183` 原本是基于旧 Application dev 的“消费 Core Storage Driver”单提交分支，落后当前 dev 21 个提交。
本批已把它重放到 `e38e45d0`，解决 `AppService` 和 `StorageRepository` 冲突，保留最新 Edition-aware
storage ledger/tenant ownership，生成新提交 `64460af8`。分支现在相对 dev 为 0 behind/1 ahead，旧 SHA
只保留为历史来源，不能再与新提交叠加。

聚焦 PHP lint 和现有 `FileMediaHostTest` 通过，但该测试主要是源码字符串合同，且工作树没有安装 vendor；
应用 `server/composer.json` 仍锁 `peanut-admin/core 0.1.0-alpha.12`，该版本不含被 import 的 Storage 类。
所以这次“拉平”只解决 Git/源码冲突，没有解决依赖可加载性。现在合入会制造运行时 class-not-found，故保持
阻塞，等待新的 Core 发布身份。

## 10. Luna 修复与剩余停止线

Application `190d4735` 可从当前 dev 快进；Core `22f6a6c` 可从当前 Core dev 快进，但两者都只是修复
候选。已完成项包括 Argon2id 平滑迁移、文件删除失败恢复、scaffold plan 重绑定、AuthException 映射、
客户端状态修复、文章 Module guard/并发幂等和七牛修复。

仍需正式关闭：

- 管理员创建/编辑跨多个 Core command 的单事务原子性；
- Core PHP/Web 测试入口遗漏；
- Vite contribution 路径规范化、根边界和 symlink；
- 短信发送 reservation、幂等窗口和未知 Provider 结果；
- `ThinkPhpTenantSettingsProvider` 与 Tenant 红线的精确架构裁定；
- scaffold 固定 fixture 缺 `release-versions.json` 的门禁漂移；
- Storage adoption 后重新打开的 LocalDriver 安全合同。

本轮已经开始收敛 Core 测试入口和 Vite 路径边界；其结果合入本报告的后续提交。其余项目按安全/事务
影响排序，不因已有 Luna 分支而标记完成。

## 11. 集成、发布、生产与清理顺序

1. 在干净 Application/Core dev 分支完成 Luna 候选与本报告列出的真实停止线；
2. 每个微批次运行最低充分聚焦验证，恢复/保留真实断言；
3. Core 先完成固定候选资格并发布含 Storage contract 的不可变版本；
4. Application 更新 lock 后采用 `64460af8` 的唯一有效差异，完成四 provider 与 FileMedia 验证；
5. 所有实现和依赖冻结后 seal Application 候选，运行一次 L2 P0-E，检查零残留；
6. 合入 `dev`，再按 `dev → main` 人工审核形成正式 Release；
7. 生产只消费该 Release，在已登记目标上完成 migration dry-run、可恢复备份、部署和线上 smoke；
8. 只有独有价值均已吸收或明确弃用、没有活跃 owner/租约，且未跟踪文件清单已有恢复证据，才精确删除
   quarantine worktree、临时脚本和失效分支。不得用广域 reset/clean 删除未知内容或共享数据。

源码发布与生产部署继续是两个状态。用户授权允许执行到生产，但任何中间 Gate 失败只阻塞其直接下游，
不能通过缩减测试、沿用旧资格或把 Spike 证据升级命名来绕过。

## 12. 当前执行状态

| 工作项 | 状态 | 当前证据/缺口 |
| --- | --- | --- |
| 两仓大迁移只读审计 | 已完成 | Terra/Luna/GPT-6 交叉复核；已定位确定解析、类、Tenant、事务和文档问题 |
| Module 发布合同 | 已完成（开发候选） | 文档登记、公开投影、动态 official Module 打包清单 |
| 测试占位防回归 | 已完成（开发候选） | `TEST-INTEGRITY-001` 通过；尚待合入 dev |
| Rich Text 独立发布 | 部分完成 | bundled-locked；浏览器、协同服务、签名/SBOM/review/渠道未完成 |
| `590e6183` 拉平 | 已完成 | 新身份 `64460af8`；依赖发布阻塞采用 |
| 厂商 Storage 可用性 | 部分完成 | 四 provider 源码保留；Core 发布/应用 lock/真实 provider Gate 未完成 |
| Luna 修复收敛 | 进行中 | 13 项候选已在分支；剩余停止线正在分批处理 |
| High-capacity media | 已审计/隔离 | 不进入 Runtime；后续独立能力任务 |
| 正式发布与生产 | 未开始 | 依赖实现、资格、Release 与登记生产目标尚未闭合 |
| quarantine 破坏性清理 | 未开始 | 等独有差异吸收、恢复证据和 owner/租约核验 |
