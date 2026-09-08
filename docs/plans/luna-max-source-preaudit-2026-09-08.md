# Luna Max 源码预审登记（2026-09-08）

Document ID: `pa-docs-plans-luna-max-source-preaudit-2026-09-08`

Status: `planned`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Upstream: Peanut Admin Application `e38e45d07752cd6b4834fbe4483bfd2dcaf5a95d`、
Peanut Admin Core `9358686fee873dd235489c8794abf556fd70ec4f`、当前仓库执行规则、CI 与
文档合同。

> 本文是供后续高等级模型复核的候选问题登记，不是缺陷定案、修复授权、能力账本或 Release
> 资格证据。优先级只表示复核顺序；在完成调用链、运行时和合同语义验证前，不据此直接改代码。

## 1. 阅读范围与方法

本轮固定读取 Application `dev` 与 Core `dev` 的上述 commit，不读取现有隔离分支中的未提交
改动。Application 在独立 worktree 完成 CodeGraph 建索引，覆盖 `7,325` 个文件、`63,286` 个
节点和 `138,202` 条边；三个 Luna/Max 只读任务分别检查：

| 方向 | 文件盘点 | 实际重点阅读 | 范围 |
| --- | ---: | ---: | --- |
| Application 后端 | 801 | 约 120 | `server/app`、Modules、路由、数据库、跨域服务 |
| 客户端与门禁 | 581 | 约 120 | `web`、`platform`、`pc`、`uniapp`、scripts、tests、workflows |
| Core 与应用边界 | 1,597 | 约 110 | Core PHP/Web、backend、scripts、workflows，以及应用采用点 |

“文件盘点”不是逐文件审阅数；重点阅读数存在少量交叉，不能相加为唯一文件数。本轮没有连接
数据库、启动产品、调用真实 Provider 或运行完整测试矩阵。除一条纯 PHP 异常映射 sanity check
外，所有结论均来自静态源码、合同和调用关系，后续复核不得把“已阅读”写成“已动态复现”。

优先级口径：`P1` 优先确认数据、安全、认证或发布正确性；`P2` 确认用户流程、契约漂移和日常
门禁缺口；`P3` 评估维护成本。置信度分为“源码直接可证”“高风险假设”“规则/设计冲突”。

## 2. 源码直接可证的候选

“源码直接可证”仅表示所述代码形态、路径缺口或合同差异可复核，不等于业务危害已经动态证明。

| ID | 优先级 | 候选问题与直接证据 | 高等级复核问题 |
| --- | --- | --- | --- |
| `LMA-001` | P1 | 业务会员口令使用 `md5(md5(password) . salt) : salt`；创建 salt 还包含时间截断路径。证据：`server/app/Modules/Official/Member/Application/MemberIdentityContractService.php:15-48,79-90,165-180`，注册入口只校验非空，重置入口最低长度为 6。 | 固定迁移目标算法、旧 hash 一次性升级策略和登录/重置失败语义；禁止新增长期双写。 |
| `LMA-002` | P1 | 管理员新增/编辑把账户、部门、角色、激活拆成多个独立 Core 调用；编辑先更新成员，再验证角色非空。证据：`server/app/adminapi/application/auth/AdminApplicationService.php:101-190`。中段失败会留下部分状态。 | 用实际 Core 事务边界验证可否由一个原子用例承载；明确失败补偿与审计。 |
| `LMA-003` | P1 | 文件删除先软删 `pa_file`，再删存储；Driver 失败时 StorageService 只恢复对象记录，调用方不恢复文件记录。证据：`server/app/Modules/Official/File/Application/FileAdministrationService.php:117-149,212-245`、`server/app/common/service/storage/StorageService.php:149-164`。重试查询可能看不到已软删文件。 | 构造 Driver 删除失败，核对文件、对象、分类和重试可达状态；决定事务/补偿 owner。 |
| `LMA-004` | P1 | scaffold `apply` 校验 plan hash、状态、目标文件 hash 与 manifest 新鲜度，但未重新把 plan action 的 path/classification 绑定到目标 manifest；非 delete action 可被记为 scaffold-owned。证据：`scripts/scaffold-runtime/ScaffoldUpgradeRunner.php:93-147,561-597,648-670`、`scripts/scaffold-runtime/ScaffoldManifest.php:141-147`、`scripts/scaffold-upgrade:15-64`。 | 制造 checksum 自洽但把 app-owned 路径伪装为 managed 的外部 plan，验证是否能覆写；与 `docs/scaffold-upgrade.md:66-73,192-205` 对照。 |
| `LMA-005` | P1 | Core `ProblemDetailsAdapter` 未映射 `AuthException`，会落入 `INTERNAL_ERROR`/500；`AuthException` 本身携带认证语义。证据：Core `packages/php/kernel/src/Host/ProblemDetailsAdapter.php:29-52`、`packages/php/kernel/src/Auth/AuthException.php:9-15`。本轮最小 PHP 调用得到 500。 | 沿 Application 实际 Host 入口确认可达性和期望的 401/403 映射，补齐同类异常矩阵。 |
| `LMA-006` | P1 | Application CI changed-path 分类未包含根 `plugins.lock` 与 Plugin/Module 关键目录，但 server checker 会消费这些输入。证据：`.github/workflows/ci.yml:56-63`、`scripts/ci-server-check.sh:77,97-103`。仅改 lock 可能跳过产品 jobs。 | 用仅改 `plugins.lock` 的测试 commit 验证 job selection；枚举所有 checker 输入与分类。 |
| `LMA-007` | P1 | CI 的 scaffold 路径只监听 `scaffold/releases/v2.0.0/`，当前 inventory/template 已为 v3.0.13。证据：`.github/workflows/ci.yml:63,65-81`、`scaffold/application-template-inventory.json:3`、`scripts/create-app:33-47`。 | 验证当前 scaffold 模板变更是否触发 create/upgrade jobs，并改为版本无关事实源。 |
| `LMA-008` | P1 | `release-versions.json` 被 create-app/upgrade 消费，但未进入 workflow changed-path 分类。证据：`.github/workflows/ci.yml:56-81`、`scripts/create-app:33-47`。 | 单独修改版本事实源，确认所有必须资格是否会运行。 |
| `LMA-009` | P1 | UniApp 注册把成功响应当作 `LoginResult` 并立即写入登录态；后端注册成功返回消息与空 data。证据：`uniapp/src/api/account.ts:3-7,23-30`、`uniapp/src/pages/register/register.vue:40-53`、`server/app/api/controller/LoginController.php:20-34`、`uniapp/src/store/user.ts:33-42`。token/id 会是 undefined。 | 固定产品语义为“注册后登录”或“注册后跳登录”，对齐 API 类型和页面跳转。 |
| `LMA-010` | P2 | PC 登录页展示 `/register` 链接，但当前 `pc/pages/` 没有对应页面。证据：`pc/pages/login.vue:35-38`。 | 以 Nuxt route inventory 和浏览器 404 复现确认；决定隐藏入口或补完整流程。 |
| `LMA-011` | P2 | OpenAPI 管理文件接口写作 `/official.file.*`，实际 Web 请求使用 `/adminapi/official.file.*`，路由由 adminapi 应用加载。证据：`docs/api/openapi.yaml:71-87,125-134`、`server/app/Modules/Official/File/Http/routes.php:15-31`、`web/src/modules/official-file/api.ts:24-98`。 | 用生成 client 对固定候选发起请求，确认 base path 是否在 OpenAPI server/baseUrl 层注入；若无则更正唯一契约源。 |
| `LMA-012` | P2 | Platform API 401/403 时移除 token，但 `App.vue` 的 `authenticated` 状态不会同步回 false，mounted 初始请求也缺少局部失败收敛。证据：`platform/src/api/platform.ts:318-350,365-370`、`platform/src/App.vue:44-46,115`。 | 让 token 过期后执行刷新和页面请求，检查 UI、重复请求及错误反馈。 |
| `LMA-013` | P1 | Core `PdoIdempotencyRepository::complete/fail` 仅以数值 record id 与 processing 状态更新，没有 Tenant/operator 条件；`TenantColumnScope` 只确认存储行含 `tenant_id`。证据：Core `packages/php/kernel/src/Idempotency/PdoIdempotencyRepository.php:75-137,277-285,338-352`、`packages/php/kernel/src/Persistence/Tenancy/TenantColumnScope.php:111-115`。 | 证明 record id 是否具备不可伪造 capability 语义；跨 Tenant/操作者构造错误 id，验证更新是否 fail-closed。 |
| `LMA-014` | P1 | Core release workflow 在版本/split 检查后即可发 tag，未在同一 workflow 运行 `./scripts/check`、PHP tests、typecheck 或 build；完整资格只在手动 CI workflow。证据：Core `.github/workflows/release.yml:3-15,38-87`、`.github/workflows/ci.yml:49-124`。 | 查明是否有 branch protection/外部候选证明；若没有，发布动作必须绑定不可变 commit 的资格证据。 |
| `LMA-015` | P2 | Core focused PHP test scripts 只列出部分包，未覆盖多处包目录及新 StorageDriver 专项。证据：Core `scripts/test-unit:7-17`、`scripts/test-integration:72-79`、`packages/php/file-media/tests/`。 | 对比 full PHPUnit discovery 与 focused 清单，区分日常反馈缺口和正式资格缺口。 |
| `LMA-016` | P2 | Core Web `test` 脚本未包含 `task-job/tests`，而 typecheck 已包含 task-job。证据：Core `packages/web/package.json:136`。 | 确认这些测试是否由其他 runner 收集；若否，纳入唯一测试入口。 |
| `LMA-017` | P1 | 文章收藏/取消收藏路由只挂 `CheckTokenMiddleware`，相邻文章查询路由还挂 `PublicTenantModuleMiddleware`。证据：`server/route/public_api.php:63-70`、`server/app/Modules/Official/Article/Http/Controller/ArticleController.php:63-92`、`server/app/Modules/Official/Article/Application/PublicArticleService.php:90-155`。停用 Module 后写入口仍可能可达。 | 在 Module disabled 状态动态请求查询与收藏，确定生命周期中间件应覆盖的 route group。 |
| `LMA-018` | P2 | `MemberTenantRepository::createBalanceLog` 接收 context 却没有像同类方法一样把它绑定到写入作用域，依赖全局 current context。证据：`server/app/Modules/Official/Member/Infrastructure/Persistence/MemberTenantRepository.php:19-47,70-93`。 | 从异步/平台调用者传入与全局不同的 context，确认记录 Tenant 是否可能错位。 |

## 3. 高风险假设，必须先复现再定案

| ID | 优先级 | 假设与证据 | 最小复核 |
| --- | --- | --- | --- |
| `LMA-019` | P1 | 生产 Vite plugin lock 只检查字符串以 `web/src/` 开头，不做规范化；开发配置与 PHP resolver 有更严格的 realpath/边界检查。证据：`web/config/vite.config.base.ts:14-32,46-52`、`web/config/vite.config.dev.ts:79-95`、`server/app/platform/service/plugin/PluginLockResolver.php:293-306,324-335,481-488`。`web/src/../../...` 可能导入边界外代码。 | 在隔离 fixture 放入 traversal path，运行实际 production build；同时测试 symlink 与 encoded path。 |
| `LMA-020` | P1 | 验证码发送采用“查最近记录 → 调 Provider → 写日志”的 check-then-act，没有预占或窗口唯一约束；并发请求可能重复发送。证据：`server/app/Modules/Official/Notification/Application/VerificationCodeService.php:56-58,75-117,182-190`、`server/database/init.sql:342-369`。 | 用 fake provider barrier 并发两个同手机号请求，统计发送次数和日志。 |
| `LMA-021` | P2 | 文章收藏采用“先查 → 再 insert”，Schema 有 `(tenant_id, member_id, article_id)` 唯一约束；并发请求可能抛数据库异常而非幂等成功。证据：`server/app/Modules/Official/Article/Application/PublicArticleService.php:90-110`、`server/database/init.sql:424-442`。 | 并发执行两次收藏，核对 HTTP 语义、异常映射和最终行数。 |
| `LMA-022` | P1 | `PlatformTenantDataGateway` 是显式无 Tenant Scope 入口，但方法本身未断言当前 context 为 Platform/System，只记录审计后返回 unscoped query；支付、调度和成员主体查询会使用它。证据：`server/app/common/tenancy/PlatformTenantDataGateway.php:12-36` 及其 callers。 | 画完整 caller 图，分别从 Tenant request、CLI worker、Platform request 进入，验证 capability 是否能被普通服务间接取得。 |
| `LMA-023` | P2 | Core Qiniu `put` 只检查 Provider 返回 key 非空，不检查与请求的 `$objectKey` 相等。证据：Core `packages/php/file-media/src/Storage/Driver/QiniuStorageDriver.php:31-61`。 | fake client 返回另一合法 key，确认账本与实际对象是否分叉；对照其他 Provider。 |
| `LMA-024` | P1 | Core LocalStorageDriver 直接 copy 到最终路径且未显式拒绝 symlink，与 Core starter file-media 合同中的临时文件、flush、atomic rename、symlink reject 要求不一致。证据：Core `packages/php/file-media/src/Storage/Driver/LocalStorageDriver.php:27-54,62-67`、`docs/status/starter-v1-c02-file-media-contract.md:94-105`。 | 在同一 filesystem 与跨 filesystem、目标/父目录 symlink、并发读写场景验证；先判定该合同是否确实约束低层 Driver。 |
| `LMA-025` | P1 | Core Web 的 integration-security、notification-sms、ops-console convenience transport 默认使用 raw global `fetch` 与任意 baseUrl，而管理端指南要求受保护 transport。证据：Core `packages/web/integration-security/src/contracts.ts:150-170`、`packages/web/notification-sms/src/contracts.ts:165-193`、`packages/web/ops-console/src/contracts.ts:145-163`、`docs/guide/admin-web.md:8-25`。 | 盘点实际导出与 Application caller；验证默认 transport 能否绕过 CSRF/auth/error normalization。 |
| `LMA-026` | P1 | Core `ProblemDetails` 会直接输出 `ApiException` 的 message/errors；公共异常构造允许调用者传入内部细节。证据：Core `packages/php/kernel/src/Api/ProblemDetails.php:21-39`、`packages/php/kernel/src/Api/ApiException.php:9-18`、`packages/php/kernel/src/Host/ProblemDetailsAdapter.php:31-33`。 | 枚举所有 ApiException caller，注入 SQL/path/provider detail，检查生产响应是否泄漏。 |
| `LMA-027` | P2 | Core Qiniu upload endpoint 可配置，但 delete endpoint 固定为 `https://rs.qiniu.com/delete/`。证据：Core `packages/php/file-media/src/Storage/Driver/QiniuStorageDriver.php:18-27,65-76`。 | 对照 Qiniu 当前官方区域/域名规则并执行登记 Provider 资格；未验证前不改 endpoint。 |
| `LMA-028` | P2 | Application PR CI 默认只运行 `ci-server-check.sh --fast`，大量 adminapi controller 只做语法检查；full 组为手动入口。证据：`.github/workflows/ci.yml`、`scripts/ci-server-check.sh:96-165`。 | 从 checker inventory 生成 controller→测试映射，确认哪些行为改变可在 PR 中无测试通过。 |

## 4. 规则与维护性冲突

| ID | 优先级 | 冲突与证据 | 高等级复核问题 |
| --- | --- | --- | --- |
| `LMA-029` | P2 | 执行规则 §6.2 明文禁止手写 `where('tenant_id', ...)`，但 `server/app/common/service/tenant/ThinkPhpTenantSettingsProvider.php:21,39,59` 使用该形态，`server/tests/Multitenancy/RechargeTenantSettingContractTest.php:19-22` 又主动要求字符串存在。 | 先决定这是被批准的 narrow exception、测试陈旧还是实现违规，再统一规则与断言；不要机械替换查询。 |
| `LMA-030` | P3 | `platform/src/App.vue` 把主要 control-plane 路由、状态、请求和页面渲染集中在单组件，存在约 6,705 字符单行及多处超长逻辑块；认证状态问题也在此放大。 | 用变更历史、组件依赖和测试覆盖评估真实维护成本；若拆分，按业务边界一次收敛，避免只搬代码。 |
| `LMA-031` | P2 | 当前 `origin/dev` 已包含并由 `docs/README.md` 引用 `docs/architecture/application-module-blueprint/coding-standards.md`，但 `docs/document-registry.json` 没有登记；`./scripts/docs-governance check` 因 orphan markdown 失败。 | 确认该规范的 authority、status、owner 和 canonical sources 后单独登记；不要用忽略规则掩盖 orphan。 |

## 5. 已排除或需要保持的边界

以下现象不登记为缺陷，后续审计不要重复领取：

1. Core `dev` 已含 StorageDriver 源码，而已发布 Core/Application lock 仍为 `0.1.0-alpha.12`，是
   当前“Core 候选已实现、Application 采用与发布暂停”的已登记边界，不是自动版本漂移。事实源见
   [Storage Driver 提取决策与后续队列](storage-driver-extraction-queue.md)。
2. Core `ExternalOperationHost` 的 PDO transaction wrapper 经调用链复核会适配到
   `AtomicOperationAdapter`；早期“事务适配失效”怀疑不成立。
3. 本轮未把历史分支、旧 PR、旧迁移计数或 Demo 证据当作当前源码完成度；所有候选都锚定本文顶部
   两个 commit。

## 6. 高等级模型建议复核顺序

1. 先处理 `LMA-001`—`LMA-005`：密码、跨系统原子性、文件补偿、scaffold 所有权和认证异常映射。
2. 再处理 `LMA-006`—`LMA-008`、`LMA-014`：建立 changed-path 与不可变候选资格的真实闭环。
3. 对 `LMA-013`、`LMA-017`、`LMA-022`、`LMA-024`—`LMA-026` 做 trust-boundary 和实际 caller 审计。
4. 用最小动态 fixture 验证 `LMA-009`—`LMA-012`、`LMA-019`—`LMA-021`、`LMA-023`、`LMA-027`。
5. 最后决定日常测试覆盖、规则冲突和 Platform 组件收敛；这些不得阻塞无依赖的 P1 确认。

每条复核的产出必须标记为 `confirmed / rejected / accepted-risk / blocked`，并记录固定 commit、
最小复现、影响面和后续 owner。只有 `confirmed` 且获得实现授权的条目才进入修复分支；不得把这份
预审表整体转成开发队列。

## 7. 文档影响与本轮验证

- docs-impact 映射：`technical + generated + developer-site`。新增内部 maintainer 预审登记和文档注册；
  `docs/governance/authoritative-source-map.md`、`docs-site/index.md` 与
  `docs-site/reference/source-map.generated.md` 精确 waiver，理由是候选表不改变权威事实、Runtime、
  公共 API、命令、资源、能力账本或公共导航，且登记的 `site_projections` 明确为空。
- 本轮只生成并检查文档目录；不运行产品测试、数据库迁移、Provider、浏览器或 Release 资格。
- 后续若候选被确认并改变技术/公共合同，必须从实际源码事实重新运行 impact，本文不能替代对应
  architecture、API、docs-site 或 capability ledger 更新。
