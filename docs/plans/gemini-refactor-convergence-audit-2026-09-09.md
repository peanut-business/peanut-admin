# Gemini 大重构收敛审计与执行报告（2026-09-09）

Document ID: `pa-docs-plans-gemini-refactor-convergence-audit-2026-09-09`

Status: `current`

Owner: `product-architecture`

Audience: `maintainer, architect, operator, ai`

Fixed inputs: Peanut Admin clean baseline `dev@e38e45d07752cd6b4834fbe4483bfd2dcaf5a95d`、Application
quarantine snapshot `1b2b66cd`、Peanut Admin Core Alpha.13 source candidate
`a949a77728f2940153c6cfd76b104d5d8bb183e3`、Core quarantine snapshot `90bf92f`、
Application convergence `main@f61d41ffb7137447e7be0b8ae2f7f9aaf4e27f7f`（PR #434；Storage adoption
`563df8c4`）、资格测试修复 `dev@fd35bc67e60d1a3c0d1055ee85fa175286cbee26`，以及合入 PR #435 后的
第二个正式候选 `main@f023760caf7c253aa2282282d5700336fec783d3`。

> 本报告把 Gemini 的机械处理视为未完成迁移工件，而不是动机错误或可继承的通过证据。目标是吸收有
> 价值的设计与行为，正式重做缺失实现和测试，再清理隔离现场。用户已授权后续发布、生产与精确破坏性
> 清理；授权不替代候选身份、备份、资格和线上 smoke。

## 1. 总结裁定

1. 两个 `dev` 仍是干净、可追溯的 canonical；两个 quarantine 提交及其脏工作区都没有进入 `dev`。
2. Gemini 大迁移不是应当整体撤销的“错误想法”。Module 物理聚合、减少应用层重复抽象、采用 ThinkPHP
   原生 Model/Query/Scope 都是可继续的目标；但当前候选混合了互斥架构、残留引用、未跟踪 Runtime、机械
   测试占位和文档先行声明，不能直接合入。
3. 本报告的“Core 保持框架中立和 caller-owned PDO transaction”是本 ADR 生效前的审计基线，现已由
   [Core ThinkPHP 8 运行时收敛方向 ADR](../architecture/core-thinkphp-runtime-direction-adr.md) supersede：
   Core 只支持 ThinkPHP 8，迁移须按领域微批次完成，不能把 `think\\facade\\Db` 的机械扩散误当作迁移完成。
4. 现行应用层锁定为 ThinkPHP 原生 Model/Query/Scope + 构造函数注入。不得仅为隔离框架新增 Repository/
   Port/Adapter，也不得在业务方法内用 `app()` 定位依赖；跨 Module 公开合同和真实外部 Provider adapter
   仍可保留。
5. 测试占位是迁移期间的显式临时工件，可以用来解开机械编辑顺序，但绝不能参与通过判断。正式处理是从
   canonical 的真实测试重放每个微批次，并让新门禁拒绝占位，不是在脏候选中继续修补“通过字符串”。

## 2. 仓库与分支事实

| 仓库/产物 | 身份 | 状态 | 裁定 |
| --- | --- | --- | --- |
| Application canonical | `dev@e38e45d0` | 与 `origin/dev` 对齐 | 后续收敛基线 |
| Application quarantine | `1b2b66cd` | tracked 与临时脚本现场已完整固化到 quarantine branch | 保留取证，禁止整体合入 |
| Core canonical | source candidate `a949a777`；release evidence `9e630548` | Alpha.13 已经 Q01/D05、tag、GitHub Release、npm、Packagist | 新应用依赖基线 |
| Core quarantine | `90bf92f` | tracked/untracked 现场已完整固化到 quarantine branch | 保留取证，禁止整体合入 |
| Application Luna | `190d4735` 已进入统一收敛分支 | 后续停止线以独立提交继续修复 | 不再单独合并旧分支 |
| Core Luna | 修复已吸收到 Alpha.13 固定候选 | 已发布 | 不再保留为未发布候选 |
| Storage adoption | `563df8c4`（由 `590e6183 → 64460af8` 收敛） | 已锁 Core Alpha.13 并通过当前装配检查 | 进入应用收敛候选，待应用 Release |
| High-capacity media spike | `quarantine/high-capacity-media-storage-spike-20260831@e915bea7` | 已恢复显式引用，未进入 dev/main | 设计输入，不是产品能力 |
| Application convergence | `main@f61d41ff`（PR #434） | 大重构收敛主体已进入 main；首次最终 P0-E 暴露资格测试边界缺口 | 资格修复进入 dev 后重新形成 main 候选 |

Application quarantine 的 148 个未跟踪文件含 139 个 Python 和 9 个 PHP；已提交根目录还含 22 个
迁移/修复脚本。Core 未跟踪现场实际是 182 个文件，其中包括 18 个 `module.json` 和 14 个无法解析的
ModuleProvider。两边现已分别用 `1b2b66cd`、`90bf92f` 固定，不再依赖易失工作树；是否删除 branch 仍须
等独有价值吸收/弃用登记完成。

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

Core 的合理精简仍应发生在清晰的 composition root 和领域微批次内，但长期目标不再是显式 PDO/transaction
handle 的框架中立。Core/Application 共同收敛到 ThinkPHP 8；跨 Module 业务合同、Tenant/RBAC/Module
生命周期、ExecutionContext 和外部 Provider/Storage Driver 仍保留。

## 6. 测试脚本的正式处置

本批新增 `scripts/check-test-integrity` 并接入 `scripts/ci-server-check.sh` 的 fast/full 入口。它拒绝非
fixture 测试中短小、无控制/断言、无条件输出 pass 后 `exit(0)` 的占位形态；canonical 当前检查通过。
全官方 Module 打包证据生成器也已固定 `CI=1` 与 `HUSKY=0`：非交互执行不得在 pnpm 的
`node_modules` 重建提示处以 exit 0 提前结束、随后再让生产构建因缺少 `vue-tsc` 失败。

首次最终候选 `f61d41ff` 的 P0-E `v3014a` 真实通过 generated-application、standalone-fresh 和
multi-tenant-fresh，随后在 plugin-lifecycle 暴露 `MemberUploadTenantWiringTest` 仍是机械迁移半成品：
它把宿主前缀 `api` 写进 Module 相对 route 断言、用非 `default` Tenant 执行依赖 default seed 的
`init.sql`、把 `UploadedFile` 对象误传给 ThinkPHP `Request::withFiles()`、缺少合法 Account/TenantMember
外键 fixture，并且 ThinkPHP CLI 异常处理可能打印错误后返回 0。P0-E Host 环境本身也没有把持久化的
随机 JWT/Tenant/Platform HMAC 密钥传入各阶段，导致真实 StorageService 拒绝构造。

这些问题已作为同一资格边界在 `fd35bc67` 正式修复：测试使用 Module 相对 route、canonical default
Tenant、原生 `$_FILES` 数组、合法 TenantMember，并在框架初始化后安装 fail-closed exception handler；
P0-E 则把三项随机密钥写入仅存于 lease cache 的 `0600` 恢复文件，Host 与 Compose 阶段复用，成功清理
时随 cache 删除。开发聚焦重跑 `v3014f` 显式输出 `MT03-MEMBER-UPLOAD-TENANT-WIRING-001 passed`，数据库、
cache、output 与 lease 均为零残留。首次失败候选的 evidence 保留，数据库、Compose、监听、cache 和 lease
已按精确 run ID 清理，未沿用为新候选通过证据。

第二个正式候选 `f023760c` 的 P0-E `v3014g` 已真实通过生成应用、双 Edition 空库、Plugin lifecycle
和消费方 Module 正式采用五组；`production-compose` 的三端构建及 PHP/Nginx 镜像也成功。随后 PHP
健康门禁发现容器无法连接数据库：宿主访问登记的 `192.168.192.2:20183` 正常，而 Docker Desktop
容器访问同址超时。根因是资源登记把宿主专用网卡的直连地址错误声明为容器可达，不是产品数据库或镜像
构建错误。聚焦取证已证明 `host.docker.internal` 经仅绑定 `127.0.0.1` 的 SSH local forward 可达同一
登记 MySQL。当前修复把容器端点、`20189` 监听、隧道工具、lease 资源、启动/健康/失败与成功清理统一
纳入版本化合同；因为资格基础设施和生成应用中的环境门禁均改变，`f023760c/v3014g` 只保留失败证据，
不得 resume 或继承为新候选资格。

新合同的首轮完整运行 `dd3db2d8/v3014h` 随后在 `standalone-fresh` 揭示同一边界的另一半：宿主 PHP
安装仍沿用历史 `container` consumer。旧地址相同时这一错误被掩盖，端点分离后即按预期 fail-closed。
处置不再增加例外，而是把 Host 与 Container 两个 endpoint 同时纳入 lease：所有宿主安装/Schema/
Module 测试显式消费 Host endpoint，生产 Compose 显式消费隧道 endpoint，环境门禁分别核验登记地址。
这会再次形成新候选；`v3014h` 只保留生成应用组通过和边界失败证据。

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

Rich Text 的 bundled 通道达到 **bundled-locked**：源码随 v3.0.13 完整应用 Release（源码与两种
Edition 安装包）冻结；线上 Demo 仍是 v3.0.12。固定提交 `882182e7` 的 `module:check` 八项全部通过，
并重新生成/复验未签名本地 tar（SHA-256 `9240e9ca17f18f009117a775fa9c0a19eaa6511e7a563fc100316aab9647caa8`）；
同一提交的动态清单资格已打包 9 个官方 Module 并完成生产 Web 构建，
所以独立包通道达到 **package-candidate（local/unsigned）**。签名、SBOM、review/漏洞响应未建立；
Tiptap/ProseMirror/Yjs 客户端已实现，仓库没有 Hocuspocus 服务端，原专用浏览器会话未完成。它不能被
描述为 independently published 或生产协同编辑已可用。

## 8. 媒体与厂商支持

### 8.1 高容量媒体 Spike

`e915bea7` 是 `e3c940ea → a5b2f604 → e915bea7` 三个连续实验提交，不是多个已合产品分支的合并结果。
它验证 SeaweedFS S3 thin adapter、multipart、checksum/range、Tenant/ACL/key、quota reservation、扫描/
隔离、derivative、retention/legal hold/deletion 和成本模型。现有证据仅是约 5 MB 单节点样本和 11 项检查，
没有证明并发、GB/TB、HA、Object Lock/KMS、生产安全或正式 FileMedia/Rich Text 集成。

当前影响为零 Runtime 影响：它从未进入 dev/main。已建立 quarantine branch 防止 reflog 丢失；后续只把
协议和测试场景作为新纵向媒体任务输入，不直接合并或部署实验目录。

### 8.2 Storage Driver 与厂商

Core Alpha.13 与 Application adoption `563df8c4` 均保留 Local、Aliyun OSS、Tencent COS、Qiniu 四种
driver；Alpha.13 包含七牛上传返回 key 一致性和删除 endpoint 修复。厂商支持没有因提取而删除。

Application PHP/npm manifest 与 lock 已升级为 Alpha.13；Composer autoload、四 Provider 的应用 Factory
实际构造、Local 免凭据和三种云端按次凭据解析均通过，关闭了此前 class-not-found 阻断。Core 固定资格
已覆盖低层 Driver 行为。真实厂商账号的上传/下载/删除、补偿和轮换仍是 Provider-specific 后置资格：它只
阻塞对应生产可用声明，不应通过删除 Provider 或继续使用应用重复 Driver 来规避。

## 9. `590e6183` 的准确解释

`590e6183` 原本是基于旧 Application dev 的“消费 Core Storage Driver”单提交分支，不是多个媒体分支的
merge。它先被重放到 `e38e45d0` 成为 `64460af8`，再作为 `563df8c4` 进入统一收敛分支；冲突处理保留
最新 Edition-aware storage ledger、Tenant ownership、凭据解析与应用观测，旧 SHA 只保留为历史来源，
不能再与新提交叠加。

此前拉平只解决 Git/源码冲突，确实会因 Alpha.12 缺类而阻断。现在 Core Alpha.13 已发布，应用锁与 vendor
均指向 Composer split `61f40dc2`；改进后的 `FileMediaHostTest` 不再只检查字符串，还会实际构造四种
Provider Driver 并检查按次凭据解析。因此 class-not-found 与应用构造合同停止线已关闭，真实云账号资格
仍按厂商分别保留。

## 10. Luna 修复与剩余停止线

Application `190d4735` 及其后续停止线修复已经收敛到统一分支；Core 修复已进入 Alpha.13。已完成项包括
Argon2id 平滑迁移、文件删除失败恢复、scaffold plan 重绑定、AuthException 映射、客户端状态修复、
文章 Module guard/并发幂等、七牛修复、管理员聚合原子命令、Core 聚合测试入口、Vite 路径边界、短信发送
reservation、Tenant settings 窄例外登记，以及旧测试脚本的正式替换。

仍需正式关闭：

- 全官方 Module 打包证据必须在最终应用源码身份上重新生成；
- Rich Text 独立发布仍缺浏览器、协同服务、签名、SBOM、review/漏洞响应 owner 与明确渠道；
- 资格测试修复合入最终 `main` 后，应用固定候选必须重新运行 L2 P0-E；旧候选仅保留失败取证；
- 真实云 Provider 和生产部署按各自登记资源与资格执行。

## 11. 集成、发布、生产与清理顺序

1. 完成当前应用收敛分支的聚焦验证、官方 Module 打包证据和文档同步；
2. 合入并推送 `dev`，再按 `dev → main` 人工审核固定正式应用 source commit/tree；
3. 在该最终 `origin/main` 身份运行一次 L2 P0-E，不继承旧候选或旧媒体证据；
4. 资格通过后签发应用 Release；生产只消费该 Release，在登记目标完成 migration dry-run、可恢复备份、部署和线上 smoke；
5. 只有独有价值均已吸收或明确弃用、没有活跃 owner/租约，且未跟踪文件清单已有恢复证据，才精确删除
   quarantine worktree、临时脚本和失效分支。不得用广域 reset/clean 删除未知内容或共享数据。

源码发布与生产部署继续是两个状态。用户授权允许执行到生产，但任何中间 Gate 失败只阻塞其直接下游，
不能通过缩减测试、沿用旧资格或把 Spike 证据升级命名来绕过。

## 12. 当前执行状态

| 工作项 | 状态 | 当前证据/缺口 |
| --- | --- | --- |
| 两仓大迁移只读审计 | 已完成 | Terra/Luna/GPT-6 交叉复核；已定位确定解析、类、Tenant、事务和文档问题 |
| Module 发布合同 | 已完成（开发候选） | 文档登记、公开投影、动态 official Module 打包清单 |
| 测试占位防回归 | 已完成并进入 main | `TEST-INTEGRITY-001` 通过；Module 打包非交互假阳性已关闭 |
| Rich Text 独立发布 | 部分完成 | bundled-locked + local unsigned package-candidate；浏览器、协同服务、签名/SBOM/review/渠道未完成 |
| `590e6183` 拉平 | 已完成并吸收 | `590e6183 → 64460af8 → 563df8c4`；不是 merge，Core Alpha.13 依赖已锁定 |
| 厂商 Storage 可用性 | 源码与装配完成、真实资格后置 | 四 provider 源码/依赖/Factory 保留且构造通过；真实账号生产资格未冒充完成 |
| Luna/Gemini 收敛 | 主体与首轮资格修复进入 main；容器端点修复在 dev | PR #434/#435 已合并；`fd35bc67` 关闭测试边界，`f023760c/v3014g` 暴露并定位 Docker Desktop 端点事实错误 |
| High-capacity media | 已审计/隔离 | 不进入 Runtime；后续独立能力任务 |
| Core 正式发布 | 已完成 | Alpha.13 source/split/tag/GitHub Release/npm/Packagist 已一致 |
| 应用正式发布与生产 | 未完成 | 待容器端点合同修复、重新 seal、最终应用 P0-E、Release 与登记生产 smoke |
| quarantine 破坏性清理 | 可恢复证据已固定、暂不删 branch | `1b2b66cd` / `90bf92f`；待独有价值最终登记后精确清理 |
