# Luna Max 源码登记高等级审计报告（2026-09-08）

Document ID: `pa-docs-plans-luna-max-source-audit-report-2026-09-08`

Status: `current`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Fixed inputs: Peanut Admin Application `e38e45d07752cd6b4834fbe4483bfd2dcaf5a95d`、
预审登记 `2ed4c5bc37f2f0b10b7857b6851b0386e6445358`、Peanut Admin Core
`9358686fee873dd235489c8794abf556fd70ec4f`。

> 本报告复核 Luna Max 登记的 31 项候选，并记录当前修复候选的处置。`candidate-fixed` 只表示
> 修复已经进入隔离分支；验证状态单独列在第 5 节。在合入、固定 L2 候选和完成 P0-E 前，不构成
> Release、生产部署或 Core 下游采用证明。

## 1. 结论

- `confirmed`：19 项。其中 14 项已形成完整候选修复，`LMA-002` 只关闭了先写后校验窗口，
  其跨 Core 多调用原子性仍未关闭；另有 4 项进入专项队列。
- `rejected`：6 项。调用链或现行合同不支持预审中的风险推断，不应继续作为缺陷传播。
- `accepted-risk`：6 项。代码形态存在，但现行本地资格、发布分层或采用边界已经限定其影响；
  暂不以扩大改动换取形式一致。
- 本轮没有修改数据库、依赖 lock、冻结 scaffold inventory、Release 身份或能力账本，没有连接
  运行数据库、启动产品、调用真实 Provider，也没有声明候选已完成 L2 资格。

## 2. 逐项裁定

| ID | 裁定 | 当前处置与理由 |
| --- | --- | --- |
| `LMA-001` | `confirmed` | `candidate-fixed`：新建、重置和修改密码统一使用 Argon2id；成功登录旧 MD5 账号时一次性 rehash，旧格式不再产生。字段长度足以容纳当前 Argon2id 输出。 |
| `LMA-002` | `confirmed` | `partial`：编辑角色现在先校验再更新成员，关闭可直接证明的部分写入；账户、部门、角色、状态仍由多个自行提交的 Core 调用组成，需 Core 提供单事务聚合命令。 |
| `LMA-003` | `confirmed` | `candidate-fixed`：文件按项软删和删除对象，Driver 抛错时恢复当前 `pa_file`，失败项重新可见、可重试。进程崩溃和 Provider 未知结果仍需状态机/reconcile 才能完全覆盖。 |
| `LMA-004` | `confirmed` | `candidate-fixed`：apply 在任何写入前用固定 manifest 重新生成预览并核对候选身份，plan 不能再通过自算 checksum 伪造 path/classification。 |
| `LMA-005` | `confirmed` | `candidate-fixed`（Core）：`ProblemDetailsAdapter` 保留 `AuthException` 的公开错误码与 4xx 状态，不再降级成 500。 |
| `LMA-006` | `accepted-risk` | changed-path 确实遗漏 `plugins.lock`，但现行规则已取消 GitHub Actions 的日常合并和正式资格权威；本地 checker 才是门禁。旧 workflow 可另行删除或简化。 |
| `LMA-007` | `accepted-risk` | workflow 的 v2 路径陈旧属维护债，不是当前资格旁路；同 `LMA-006`。 |
| `LMA-008` | `accepted-risk` | workflow 未监听 `release-versions.json` 属维护债，不是当前资格旁路；同 `LMA-006`。 |
| `LMA-009` | `confirmed` | `candidate-fixed`：UniApp 注册响应改为 `void`，成功后返回登录页，不再写入 `undefined` token/用户身份。 |
| `LMA-010` | `confirmed` | `candidate-fixed`：PC 没有注册页面或已冻结注册合同，移除 `/register` 死链接，不虚构半成品流程。 |
| `LMA-011` | `confirmed` | `candidate-fixed`：OpenAPI server 为 `/`，因此文件接口必须显式包含 `/adminapi`；规范和生成类型已经同步。 |
| `LMA-012` | `confirmed` | `candidate-fixed`：Platform transport 统一管理 token 并发布同页会话变化，UI 立即退出失效会话；mounted 初始化错误也收敛到页面错误状态。 |
| `LMA-013` | `rejected` | record id 只由 `begin()` 返回并在同一内部调用链消费，是内部 capability；没有发现 HTTP/外部调用者可注入其他 Tenant 的数值 id。强加 Tenant 参数会扩大公开合同但不关闭已证攻击面。 |
| `LMA-014` | `rejected` | Core release workflow 是资格完成后的发布器，不是资格执行器；固定候选资格和单独批准是现行发布合同，不能要求在 tag workflow 内重复全矩阵。 |
| `LMA-015` | `confirmed` | `outstanding`：`scripts/test-unit` / `test-integration` 没有覆盖 phpunit.xml 中若干后增包，File Media tests 也不在正式聚合入口。由 Core qualification owner 单独补齐并运行一次受影响组。 |
| `LMA-016` | `confirmed` | `outstanding`：`packages/web` 的唯一 `test` 脚本确实遗漏 `task-job/tests`，而 typecheck 已纳入该包。与 `LMA-015` 合并为一个 Core 测试入口收敛任务。 |
| `LMA-017` | `confirmed` | `candidate-fixed`：文章收藏组在会员认证后追加 Article Module 生命周期中间件；模块停用时写入口与读取入口一致 fail-closed。 |
| `LMA-018` | `rejected` | `createBalanceLog` 的 context 参数由 current execution context 约束；同仓 `tenantId()` helper 只返回 id，并不会额外绑定查询。增加一次无效果调用只会制造安全错觉。 |
| `LMA-019` | `confirmed` | `outstanding`：`web/src/../../...` 可通过字符串前缀并在解析时逃出目标根。修复会改变生产构建输入及 scaffold inventory，必须作为独立 L2 构建边界任务验证 traversal/symlink 后再 reseal。 |
| `LMA-020` | `confirmed` | `candidate-fixed`：验证码发送先提交 `pa_notice_log` 活动 reservation，再跨 Provider 边界；Tenant/手机号唯一活动键关闭并发双发，同请求身份只重放既存结果。调用前持久化 `unknown`，仅明确失败释放窗口，成功与未知结果均保留 60 秒防重放。Schema 只追加 migration，未改 `init.sql`。 |
| `LMA-021` | `confirmed` | `candidate-fixed`：并发首次收藏撞唯一约束后，只在同 Tenant 精确记录已存在时视为成功，否则重抛原异常。 |
| `LMA-022` | `rejected` | unscoped gateway 用于建立 current Tenant 之前的会员主体解析及 scheduler discovery；行为测试明确要求空 execution context 可用，能力只注入有限基础设施服务并记录审计。 |
| `LMA-023` | `confirmed` | `candidate-fixed`（Core）：七牛上传只有在返回 key 与请求 object key 完全一致时成功，防止对象账本分叉。 |
| `LMA-024` | `accepted-risk` | 当前 Storage Driver 合同没有继承历史 PB04 Host 的 atomic rename/symlink 条款，且该 Core 候选尚未被 Application 采用。Local Driver 加固应由既定 Storage P1 owner 和测试合同处理。 |
| `LMA-025` | `rejected` | 三个 convenience transport 不读取或附加 bearer token，使用 Host 注入 fetch、cookie credentials 和服务端 CSRF/origin 边界；没有证据表明它们绕过受保护 transport。 |
| `LMA-026` | `rejected` | `ApiException` 本身就是可公开、已清洗的异常合同；未知异常仍映射为固定通用消息。登记未给出任何把 SQL/path/secret 传入公开异常的可达 caller。 |
| `LMA-027` | `confirmed` | `candidate-fixed`（Core）：七牛当前官方删除 API 指定 `rs.qiniuapi.com`，替换旧 `rs.qiniu.com`。来源：https://developer.qiniu.com/kodo/1257/delete 。 |
| `LMA-028` | `accepted-risk` | `--fast` 只服务旧 GitHub PR 反馈，现行日常合并与正式资格不以该 workflow 为权威；真正代码变更仍须按风险运行本地聚焦检查或 P0-E。 |
| `LMA-029` | `confirmed` | `outstanding`：规则的绝对措辞与双 Edition `ThinkPhpTenantSettingsProvider` 的刻意适配冲突。运行时实现和测试互相一致；治理规则例外需要用户明确授权，不能在源码审计中自行改写长期红线。 |
| `LMA-030` | `accepted-risk` | 单组件过大是实质维护债，但本轮只有认证状态变化需要修改。现在拆分会扩大写集和回归面，待 Platform 页面按业务边界集中重构。 |
| `LMA-031` | `confirmed` | `candidate-fixed`：coding standards 是目标架构文档并已被两处索引引用，补入文档 registry，而不是用 orphan ignore 掩盖。 |

## 3. 本轮候选修复边界

### Application

- 身份：会员密码改为 Argon2id，并保留仅用于成功登录迁移的旧 hash 读取路径。
- 数据一致性：文件删除失败恢复当前软删记录；文章收藏并发首次写入收敛为幂等成功。
- 通知：验证码发送采用数据库 reservation、请求幂等摘要和 `reserved → unknown → success|failed`
  状态机；Provider 异常或不可判定回执保持 unknown，禁止立即重发。新 migration 进入 Edition profile，
  Multi-tenant 保留 Tenant 复合键，Standalone 由既有投影器去除回填分组与索引中的 `tenant_id`。
- 入口与合同：文章收藏补 Module 边界；OpenAPI 文件路径对齐 `/adminapi`。
- 客户端：UniApp 注册、PC 死链接、Platform 会话状态同步。
- 升级安全：scaffold plan 在 apply 前与目标 manifest 重新绑定。
- 文档：登记目标 coding standards 和本审计报告，生成目录随 registry 更新。

### Core

- `ProblemDetailsAdapter` 映射公开 `AuthException`。
- `QiniuStorageDriver` 核对上传返回 key，并采用当前官方删除域名。

Core 修复只在独立分支处理，不合并、不发布、不更新 Application lock；Core 后续采用仍受固定候选资格和
单独批准约束。

## 4. 后续队列与停止线

1. `LMA-002`：由 Core Membership owner 提供“创建/编辑管理员”的单一原子命令；Application 不得用
   外层 PDO 事务包裹会自行 `beginTransaction()` 的多个旧命令。
2. `LMA-015` + `LMA-016`：Core 测试入口一次收敛，精确列出新增包并只运行一次受影响 unit/web 组；
   Integration 使用 Core 资源登记，不能猜端口。
3. `LMA-019`：production Vite contribution 路径做规范化、根边界和 symlink fixture；该任务改变构建和
   scaffold 输入，完成聚焦验证后才允许 inventory/release reseal。
4. `LMA-029`：用户若批准治理规则变更，只登记 `ThinkPhpTenantSettingsProvider` 的精确窄例外，不得扩展到
   普通 Tenant-owned Model。

## 5. 验证结果与限制

已通过：

- 7 个 Application PHP 变更文件与 scaffold runner 的 `php -l`；Argon2id 生成、100 字符字段长度和
  `password_verify` 最小 sanity。
- `OfficialArticleModuleContractTest.php`、`FileMediaHostTest.php`。
- 登记资源 `peanut-admin-host-node24-npm-development`（Node 24.13.0 / npm 11.6.2）下的 Platform
  `npm ci --ignore-scripts` 与 `npm run build`；类型检查、1,682 个模块转换和产物构建通过。
- Core 两个修改文件的 `php -l`；无 vendor 条件下用直接类加载验证 AuthException 映射和七牛返回 key
  一致/不一致分支。
- `./scripts/docs-governance check`、带精确 waiver 的 docs-impact closure、JSON 解析和 `git diff --check`。

受阻或未运行：

- `ScaffoldUpgradeRunnerTest.php` 在进入本轮新增的 apply 重绑定前，就因历史固定 create-app commit
  `1441260` 不含现行 runner 要求的 `release-versions.json` 而报 `SCAFFOLD_VERSION_CONTRACT_INVALID`；
  这是既有 fixture/gate 漂移，本轮按单次失败预算没有改测试或循环重跑。
- `scripts/check-openapi` 缺少 worktree-local `openapi-typescript`；规范与生成 d.ts 的两个 path 已逐项同步，
  但没有把手工比对冒充生成器通过。
- PC、UniApp 没有登记可用于此 worktree 安装依赖的资源，Core worktree 也没有 `vendor/`，因此没有运行
  两个客户端 build/typecheck 或 Core PHPUnit。Platform 安装同时报告现有 lock 中 6 项 npm audit 告警
  （4 moderate、2 high）；本轮没有改依赖 lock 或执行自动升级。
- 未运行真实短信、七牛、浏览器、数据库并发或完整 P0-E。密码、Core、Tenant/Module 边界、scaffold 和
  生产构建属于 L2，正式发布前必须由同一冻结候选完成完整 P0-E。
