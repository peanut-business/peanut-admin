# 产品能力与交付状态

本目录是 Peanut Admin 的**内部交付事实账本**。它回答“当前实际上能做什么、还缺什么、
哪些范围明确暂缓”，不替代架构设计、开发计划、PR 或原始测试证据。

本目录默认不进入 `docs-site` 首页、导航或公开构建。将来是否公开，应由独立的信息披露
决定授权；公开站点不得通过复制本页形成第二份状态事实源。

当前产品闭环的执行顺序见
[`../plans/product-closure-execution-queue.md`](../plans/product-closure-execution-queue.md)，
人工进度、固定候选、验证和剩余 Gate 见
[`product-closure-observability.md`](product-closure-observability.md)。两者不替代本目录的
能力账本；稳定能力状态仍只在 `capability-ledger.json` 中维护。

## 使用规则

- `capability-ledger.json` 是唯一状态事实源；本页状态区由脚本生成，不手工编辑。
- `verified` 表示验收条件已有固定证据，不等于整个产品已经 release-ready。
- 开放 PR、未提交 worktree 和正在运行的 Gate 只能记为 `in_progress`、`implemented` 或
  `blocked`，不能提前记为 `verified`。
- 设计和计划只链接能力 ID；详细设计仍留在 `docs/architecture/`、`docs/plans/` 和
  `docs/design/`。
- CI 日志、截图和报告保留在 Actions、`output/` 或原合同位置，本目录只保存稳定引用。

修改账本后运行：

```bash
php scripts/check-product-capability-ledger --write
php scripts/check-product-capability-ledger
```

## 当前状态

<!-- CAPABILITY_STATUS_GENERATED_START -->
> 总体状态：**进行中**。产品闭环 PC00—PC70 已在固定 dev 候选 f6378f255241cbde25f374a8a0218fda4616c1ce（tree 184033c89425a0aa08f5591ce7f6a82735d47ad4）完成且 pc70q14 P0-E 7/7 通过；在 dev@8ce916792829f08a1a17aa935667c06d55928a10 复核后，下一阶段转入可消费交付：补齐 active Module package 更新、交付环境安全入口、Tenant 停用全局门禁、双独立应用二开链、历史资料清理及正式 main/Tag/Release。产品闭环资格仍有效，但尚不能把该 dev 结果冒充新的正式可消费 Release。
>
> 事实基线：`dev@8ce916792829f08a1a17aa935667c06d55928a10`，复核日期：`2026-08-28`。

### 已验证可用

| ID | 能力 | 状态 | 当前事实 |
|---|---|---|---|
| `PA-NATIVE-001` | 2.0.0 原生身份与干净安装基线 | 已验证 | 原生管理身份、独立业务会员、canonical fresh Schema、官方能力强制 Tenant 资格、头像 fallback、本地域名显式白名单和 fresh-only P0-E Runtime 已实现。固定候选 78e9667 的最终 P0-E 七组全部通过，并已作为 v2.0.0 正式源码 Release 发布。 |
| `PA-FOUNDATION-001` | 1.x LikeAdmin 标准版基础能力对齐（历史） | 已验证 | 基础后台能力、空库安装、迁移账本和代表路由已有独立验证。 |
| `PA-INSTALL-001` | 统一只读安装预检 | 已验证 | PC10 已在 dev 形成 CLI、未来 Web 与自动化共用的唯一只读安装预检 Host；稳定返回状态、代码、原因和修复建议，不连接数据库、不猜测地址或凭据，并裁剪资源秘密。PC70 pc70q14 已在固定派生应用候选完成 P0-E 7/7 组合资格。 |
| `PA-INSTALL-002` | 一次性引导安装 | 已验证 | PC11 已由 PR #279 合入 dev：guided/automatic 共用唯一执行 Host、一次性 setup token、安装态 fail-closed、官方 Module 选择和 Admin Web 向导已形成；Standalone/Multi-tenant 登记空库资格与 Web 生产构建通过。PC70 pc70q14 已在固定派生应用候选完成 P0-E 7/7 组合资格。 |
| `PA-READINESS-001` | 首次运行生产准备清单 | 已验证 | PC12 已由 PR #281 合入 dev：Tenant 安全的只读 Host 与 Admin 页面统一展示品牌、通知、存储、备份、Worker、当前域名/TLS 和账户安全的状态、影响、入口与生产阻塞性；状态严格区分本地配置、当前请求观察、未验证和尚未实施。PC70 pc70q14 已在固定派生应用候选完成 P0-E 7/7 组合资格。 |
| `PA-OPS-002` | 单实例运行与维护控制台 | 已验证 | PC20/PC40 已完成：Platform 控制面采用 Core Ops PHP/Web 状态与维护公共合同，展示数据库、迁移、Module、缓存、存储和版本，并可按 reason/revision/时间范围计划或关闭维护窗口。全局后端写门禁已在登记 development 数据库上真实返回 MAINTENANCE_WRITE_BLOCKED 并记录 denied 审计；PC70 pc70q14 已完成固定派生应用 P0-E 7/7 组合资格。 |
| `PA-DIAGNOSTICS-001` | 可下载脱敏诊断包 | 已验证 | PC21 已由 PR #285 合入 dev：Platform Operator 可下载固定 schema 的有界 JSON 诊断制品；运行状态、非秘密配置、Module、失败任务和 Platform 审计事件均为聚合或安全投影，服务端与浏览器共同验证 SHA-256。PC70 pc70q14 已完成固定派生应用 P0-E 7/7 组合资格。 |
| `PA-BACKUP-001` | 受信配对备份、恢复验证与应用备份中心 | 已验证 | PC30—PC32 已完成：单一 DB/文件 Provider、schema 1 manifest、Core 任务提交/查询、受信 backup/restore worker、Application evidence 和 Platform 备份中心已形成。最终 dev@af7b1c9 的真实 Gate 将已验证配对制品恢复到登记新目标，得到 97 表、6 migration、Account/Tenant/TenantMember 各 1、零发布端口、受保护 Runtime 不变和成功零残留；PC70 pc70q14 已完成固定派生应用 P0-E 7/7 组合资格。 |
| `PA-UPGRADE-002` | 应用升级就绪、执行与恢复停止点 | 已验证 | PC41/PC42 已由 PR #307/#310 合入 dev：Platform 先以固定 source/target、Release、migration、Module、scaffold、备份/恢复 evidence、维护窗口和 recovery pointer 判定就绪，再由持久化任务状态机与独立登记 worker 串联 preflight、配对备份、维护、唯一 deploy-release、迁移、smoke 和恢复指引。HTTP 不接受路径、URL、命令、Release、镜像或凭据；PC70 pc70q14 已完成固定派生应用 P0-E 7/7 组合资格。 |
| `PA-PROVIDER-001` | 外部 Provider 生产资格可见性 | 已验证 | PC60 已由 PR #313 合入 dev：通知、支付、OAuth 和 Storage contributor 以 Platform-only 只读安全投影区分 configured、connectivity、callback、credential rotation、recent failure 和 evidence freshness；通用面板不外呼、不发送消息、不扣款，真实平台资格仍由各 Provider owner 按授权目标独立执行。PC70 pc70q14 已完成固定派生应用 P0-E 7/7 组合资格。 |
| `PA-CONFIG-001` | Tenant 配置包与环境转移 | 已验证 | PC51 已由 PR #315 合入 dev：ImportExport Module 以 Tenant-only schema 1 包导出逻辑配置，使用 canonical checksum、dry-run、abort/overwrite/skip 冲突策略、秘密引用重绑定、原子写入与同事务审计；包、响应和审计均不包含密码、token、Cookie、callback key 或密钥。PC70 pc70q14 已完成固定派生应用 P0-E 7/7 组合资格。 |
| `PA-TENANCY-001` | 1.x 多租户隔离与平台租户治理（历史） | 已验证 | 1.x MT00 至 MT06 已完成并封存；2.0 以原生身份、fresh Schema 和当前 P0-E 重新验证，不依赖 1.x 兼容 Runtime。 |
| `PA-PRODUCT-001` | 1.x 产品化部署与发布基线（历史） | 已验证 | 生产 Compose、最低 CI、正式部署、法律制品和 v1.1.5 发布链已经封存。 |
| `PA-OWNERSHIP-001` | 核心包与应用唯一实现边界 | 已验证 | 权限、管理员、字典、文件、任务、会员财务、内容装修、通知、支付和 OAuth 已固定应用 Runtime 与核心边界。 |
| `PA-BRAND-001` | 中性品牌与安全安装默认值 | 已验证 | 四端品牌消费、中性安装、显式初始密码和文档门户合同已完成。 |
| `PA-PLUGIN-001` | Plugin/Module 生命周期与锁定信任矩阵 | 已验证 | 既有安装、重复安装、升级计划、回滚、TenantModule 授权、失败迁移、禁用和卸载已通过真实数据库 fixture；PC50 进一步在 dev@ed5ee19 固定 lock/manifest 的版本、依赖、权限摘要、migration 指纹、来源与信任状态，并让 install/reconcile/upgrade 共用同一资格解释。唯一 Runtime 只接受 bundled-locked，Marketplace 在签名、SBOM、许可证和漏洞响应 authority 完成前保持 blocked。 |
| `PA-SCAFFOLD-001` | 创建独立应用 | 已验证 | create-app 已具备确定性生成、路径安全、品牌参数化、文件归属和不可变 Release 采用。 |
| `PA-SCAFFOLD-003` | 新应用 Plugin 空锁合同 | 已验证 | 正式生成应用使用有效空 plugins.lock，不再引用仅供源仓测试的 fixture。 |
| `PA-SCAFFOLD-004` | 2.x 派生应用受控升级 | 已验证 | 2.0.0 生成应用已经记录不可变模板身份、逐文件所有权和 managed baseline；2.x Release 转换策略与 preflight/apply/verify/recover 执行器已通过一次真实 v2.0.0 -> v2.0.1 派生应用资格，app-owned 字节保持且恢复闭环通过。 |
| `PA-COMPAT-001` | 1.x 公开核心包升级兼容（历史） | 已验证 | 公开 PHP Alpha.2 到 Alpha.5 与 Web Alpha.4 到 Alpha.5 的真实安装、构建、入口和 app-owned 摘要矩阵已通过。 |
| `PA-P0E-001` | P0-E 隔离资源登记 | 已验证 | 项目自有资源登记、原子租约、精确候选绑定和清理释放已在 P0-E 实跑中通过；PC70 pc70q14 固定候选 f6378f255241cbde25f374a8a0218fda4616c1ce 完成七组资格，数据库、Compose、容器、卷、网络、镜像、监听、cache 和 lease 均零残留或已释放。 |
| `PA-P0E-002` | 1.x 最终生成应用运行时验收（历史） | 已验证 | 固定候选 8fa274b 的 scaffold v1.1.9 生成全新应用后，干净依赖安装、双模式空库与服务、生产 Compose 和真实浏览器验收均已通过。 |
| `PA-P0E-003` | 1.x 最终升级应用运行时验收（历史） | 已验证 | 固定旧应用完成十段 scaffold preflight/apply/verify、故障恢复、五套依赖安装构建、729 个 app-owned 文件逐字节保持，并通过升级后 Plugin、Compose 与双模式真实浏览器复验。 |
| `PA-DELIVERY-002` | 1.x 正式发布与生产证明（历史） | 已验证 | v1.1.5 已完成 P0-E 16/16、dev/main、annotated tag、GitHub Release、配对生产备份、54 条迁移和最低登录/API/核心页/TLS/demo smoke，达到 production-demonstrated。 |
| `PA-DELIVERY-003` | 2.0.0 正式源码发布 | 已验证 | v2.0.0 fresh-only 固定候选完成 P0-E 7/7 后，已由 PR #148/#149 合入 dev/main，并创建 annotated tag、GitHub Release、确定性源码包和法律附件；生产部署明确留给独立工作流。 |
| `PA-DELIVERY-004` | 2.0 发布后部署与演示闭环 | 已验证 | v2.1.5 已在固定 tag aefc5779c97db1cd17442269e50156baeaa4ba0c 上完成 P0-E 7/7、生产单租户升级、多租户 Demo fresh 部署、无秘密 post-deployment 快照和受控访问交付；v3.0.0 候选仍需独立重新资格。 |
| `PA-MODULE-002` | 官方可选 Module 产品化 | 已验证 | 文件、通知、OAuth、支付、会员、任务和导入导出已拆出独立 manifest、Plugin、Provider、HTTP 路由、菜单/权限目录、前端 contribution 和 TenantModule Guard；v2.1.4 正式候选已完成真实数据库安装、Plugin 生命周期、Standalone/Multi-tenant 运行、Tenant A/B 浏览器矩阵和停用负向资格；v2.1.5 将复用同一合同在最终 origin/main 候选上验证。跨 Module 可运行示例不属于本次 Release 阻塞项。 |
| `PA-MODULE-003` | Module 开发与 Tenant 安全脚手架 | 已验证 | PC52 已由 PR #306 合入 dev：唯一 module:create 生成公开 Commands 合同、append-only migration 指南和 Plugin 制品外 Tenant 安全测试骨架。CR20 已由 PR #342 合入唯一只读 module:check Host/CLI，复用现有 manifest schema、版本约束、package preflight 与 archive validator，稳定报告八项检查且不访问数据库。PC70 pc70q14 已完成固定派生应用 P0-E 7/7 组合资格。 |

### 已实现或正在验收

| ID | 能力 | 状态 | 当前事实 |
|---|---|---|---|
| `PA-MODULE-004` | 可消费 Module Package 全生命周期 | 进行中 | CR10 已冻结显式 Package 生命周期合同；CR11 已由 PR #339 合入 development/debug/Standalone 的 module:update-package。CR12 已由 PR #340 合入 deployment-owned opaque request/worker，串联登记 target、配对备份、隔离恢复、维护、update/retire/Purge、审计、smoke 和 recovery pointer；完整双应用消费者纵向资格仍由 CR21 提供。 |

### 计划中或受阻

| ID | 能力 | 状态 | 当前事实 |
|---|---|---|---|
| `PA-TENANCY-002` | Tenant 停用全局 Fail-Closed | 计划中 | 管理/API 身份链已有 Tenant 状态门禁，但 Tenant 文件直出和未统一挂载状态检查的公共内容入口仍可能在停用后可读。CR13 将统一公共 API、PC/H5、文件与静态交付边界。 |
| `PA-DELIVERY-005` | 正式可消费源码交付 | 计划中 | PC70 已证明产品闭环 dev 候选，但尚未形成包含直接 Module package 更新、双独立应用二开参考链和 Tenant 停用全局门禁的新 main/Tag/GitHub Release。CR30—CR40 将只对一个冻结候选完成资格和正式发布。 |

### 暂缓或范围外

| ID | 能力 | 状态 | 当前事实 |
|---|---|---|---|
| `PA-SCAFFOLD-002` | 1.x 脚手架跨版本升级（历史） | 已退出 | 1.x preflight/apply/verify/recover 证据已封存；2.0 当前 inventory 和生成应用不再携带该 Runtime。 |
| `PA-GOVERNANCE-001` | 外部 CompanyOS 治理流程 | 已退出 | Peanut Admin 已退出 CompanyOS 资源与治理依赖；项目资源、GitHub PR 和验收事实均由本仓库维护。 |
| `PA-SAAS-001` | 完整 SaaS 商业化 | 暂缓 | 商业套餐、计费、完整运营与商业控制面不属于当前独立应用交付目标。 |
| `PA-OPS-001` | 跨应用实例运营平台 | 范围外 | 跨实例 Release、授权、升级、健康与备份平台应作为独立应用，不进入 Peanut Admin 核心包或 SaaS Host。 |
| `PA-UPGRADE-001` | 自动重构或静默覆盖应用业务源码 | 范围外 | 升级允许替换已登记的框架管理文件和依赖，但不得自动改写 app-owned 业务代码。 |
| `PA-RELEASE-001` | 发布预构建生产镜像 | 范围外 | 当前生产策略从不可变源码 Tag 在部署端构建，不发布预构建运行镜像。 |
<!-- CAPABILITY_STATUS_GENERATED_END -->

## 文件职责

| 文件 | 职责 |
|---|---|
| `capability-ledger.json` | 唯一机器可读事实源 |
| `schema/capability-ledger.schema.json` | 编辑器和外部工具可用的结构合同 |
| `acceptance-gates.md` | 状态含义和完成判定 |
| `evidence-guide.md` | 有效证据、身份和新鲜度规则 |
| `deferred-scope.md` | 暂缓、范围外和恢复规则 |
| `product-closure-observability.md` | 产品闭环任务的人工进度、候选、验证、阻塞和下一交付物 |
| `releases/` | 正式产品发布时冻结的不可变能力快照 |
