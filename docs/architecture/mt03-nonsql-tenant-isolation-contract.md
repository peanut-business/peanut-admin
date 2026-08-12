# MT03 非 SQL-Article Tenant 隔离合同

> 任务：`MT03-NONSQL-ISOLATION-001`
>
> 状态：Accepted for incremental implementation
>
> 应用基线：`1107c10351a9d57fb34c0f135dd3f979efb5a79e`
>
> 首个实现测试 owner：`MT03-CACHE-LOCK-001`

## 1. 目标、边界与完成口径

本合同冻结 Peanut Admin 应用仓 MT03 的非 SQL-Article 隔离边界，并允许按独立、可回滚
切片依次实现。范围只包括：Tenant namespace 的缓存 key/失效/锁，Tenant-owned 文件对象、
临时下载和容量计量，队列/定时任务/导入导出/异步 TenantContext，以及审计、日志和诊断
标签的 Tenant attribution。

本合同不实现 Tenant、Account、TenantMember、owner bootstrap、管理员/RBAC、Article 或
装修 Article 引用；不修改 Core、Generator、Generated Host、Admin Web、安装器、共享权威
计划或根 `AGENTS.md`。SQL 业务表的通用 ownership 只登记分类和后续 owner，不在本线修改。

阶段编号只约束 MT03 最终集成和完成声明，不冻结本合同或不依赖 MT01/MT02 最终身份的显式
adapter、fixture 和 Runtime。单个切片合入后只能声明该切片 development-complete；缓存、文件、
任务和审计全部真实接入并通过跨 Tenant Gate 前，不得声明 MT03 complete、qualified 或
release-ready。

## 2. 当前实现事实，不把 roadmap 当 Runtime

截至应用基线提交：

- `server/config/cache.php` 默认使用无 prefix 的 ThinkPHP file store；应用没有 Redis store
  配置。实际 `Cache` 调用仅有管理端登录失败计数、存储设置的两个固定 key 失效，以及系统
  级 `Cache::clear()`。现有调用都没有 Tenant namespace。
- 应用命名互斥锁是 MySQL advisory lock，不是 Redis lock。Crontab 使用固定全局
  `peanut:crontab:scheduler`；OAuth 和充值退款按业务 seed 构造 lock name，均未带 Tenant。
- 文件唯一生产链是 `UploadService -> storage Driver/engine -> pa_file`，分类由
  `pa_file_cate` 拥有。对象 key 为 `uploads/<type>/<generated-name>`，local 对外 URI 加
  `storage/`；列表、分类子树、移动、重命名、删除和对象清理均无 Tenant predicate。
- 应用没有私有临时下载 grant 或 Tenant 配额 ledger。素材 URL 和
  `storage/exports/*.xlsx` 当前是可直接访问 URI；XLSX 由 `XlsxExportService` 同步写入公共
  目录，没有 Tenant 目录、owner metadata、留存或聚焦清理。
- `pa_crontab`、管理 API 和 `app\command\Crontab` 是唯一任务 Runtime。调度器全表扫描，
  以 `status + last_time` CAS 认领，再用 `Console::call(command, params)` 同步派发；任务行、
  params、命令调用和错误记录都没有 TenantContext。应用没有 queue worker、job payload
  envelope、lease 或异步 context 恢复实现。
- Generator 的 “import” 是读取数据库元数据，不是 Tenant 业务数据导入；其归档和下载令牌
  属于主会话独占的 Generated Host/Generator owner，本线不修改。
- 管理端写请求的唯一审计链是 `OperationLogMiddleware -> OperationLogService ->
  pa_operation_log`；查询、XLSX 导出和 `log/clear` 都是全局范围，表和记录没有 tenant
  attribution。普通 ThinkPHP `Log` 和诊断输出也没有统一 tenant label。

因此，蓝图声明的 Redis、文件、任务和审计隔离是待实现验收目标，不是当前应用能力。

## 3. 可信 TenantContext 与统一拒绝规则

本线只接受上游 Core 会话/系统工厂验证后显式注入的可信 Context 或应用 adapter 从该对象
生成的不可变 Tenant scope。请求参数、header、cookie、路径、文件名、对象记录、任务 params、
queue payload 的任意业务字段、管理员/会员 ID、最小 Tenant ID、默认值或“数据库只有一个
Tenant”都不能建立、恢复或改写 Tenant 身份。

在应用尚未接入最终 Core TenantContext 前，切片可以提供显式注入 port：调用者只能交入已由
可信边界解析的正整数 Tenant ID 与不可变 context identity；port 本身不读取 HTTP request，
不提供 `tenant_id=0/NULL/default` 回退，并在缺失、零/负数、类型错误或空 identity 时于任何
cache、lock、文件、任务或日志副作用前 fail closed。最终 Host 接入必须把 Core Context
adapter 作为唯一生产 provider，测试构造器不得成为请求入口。

统一不变量：

1. 无可信 TenantContext 默认拒绝，不返回全局数据，也不执行全局失效或清理。
2. Tenant A 与 Tenant B 使用相同 logical cache key、filename/object name、export name、
   job ID、operation ID 或 lock seed 时，物理 namespace 必须不同且不可互见。
3. 异步 producer 把可信 context identity 写入受控 envelope；consumer 只能通过可信
   verifier/resolver 恢复 Context。payload 内叫作 `tenant_id`、`tenant`、`context` 或任意
   嵌套字段的值都不能自行建立授权；缺 verifier、签名/绑定失败、Tenant 无效或上下文与受控
   envelope 不一致时，在 handler 前拒绝。
4. invalidate/delete/clear/retry/cancel/cleanup/retention 只能作用于当前 Tenant 的精确
   namespace 或 owner row；禁止以空 context 扩大为全部 Tenant。
5. 跨 Tenant 目标与不存在目标使用同一不可枚举拒绝形状；测试必须保持外围权限为允许，证明
   拒绝来自 Tenant ownership，而不是用 `permitted=false` 冒充隔离。

## 4. 资源 namespace 与 ownership ledger

| 资源/现有 owner | MT03 分类 | 必须形成的 Tenant 不变量 | 本线写 owner |
| --- | --- | --- | --- |
| Tenant 业务 cache、tag、失效 | tenant-owned | 物理 key/tag 含不可碰撞 Tenant namespace；精确失效不能触及其他 Tenant | 本线 cache adapter |
| Tenant 业务 advisory/Redis lock | tenant-owned | lock seed 先经同一 Tenant namespace；相同 seed 可被两个 Tenant 独立持有 | 本线 lock adapter；业务调用方按后续白名单接入 |
| 管理员登录失败计数 | instance/account security | Tenant 未可信解析前不得伪造 Tenant；当前仍为实例级 IP 安全计数，后续统一认证 owner 决定是否改为 account/tenant 复合键 | MT02/MT04 owner，非首片 |
| storage provider 配置 cache | instance configuration | `storage.default` 是实例配置时保持 instance namespace；若未来变为 Tenant 配置，必须另立 schema/config 合同 | 配置 owner，非本线首片 |
| `system/clearCache` | instance maintenance | Platform 维护能力；Tenant 请求不得调用全局 clear。未来 Tenant clear 只能清 Tenant prefix | PM01/Ops owner；本线只提供 Tenant 精确失效 port |
| `pa_file` / `pa_file_cate` 与对象 key | tenant-owned | row、分类关系、对象 key、上传/列表/移动/重命名/删除均绑定当前 Tenant；相同 filename 不互见 | 本线文件切片 |
| 临时下载与容量/配额 | tenant-owned | grant 绑定 Tenant/object/expiry/single-use policy；用量按 Tenant 聚合，跨 Tenant token/对象拒绝 | 本线文件切片；商业套餐计费不在范围 |
| `storage/exports` XLSX | tenant-owned artifact | 路径与 metadata 绑定 Tenant；下载和 retention cleanup 只作用当前 Tenant | 本线导出切片 |
| `pa_crontab` 与调度认领 | tenant-owned schedule | 行、扫描、CAS、错误和命令 dispatch 绑定 Tenant；scheduler 可用实例级扫描锁，但每个执行恢复可信 TenantContext | 本线任务切片 |
| queue/job payload（当前不存在） | tenant-owned async envelope | producer/consumer 只通过受控 envelope 与 verifier 传递 Context；任意 payload 字段不能伪造 | 采用队列时由本线合同约束 |
| Generator schema import/archive | admin-owned code artifact | 不是 Tenant 业务导入导出；保持管理员隔离，不由本线修改 | 主会话 Generator owner |
| `pa_operation_log` | tenant audit | tenant audience 的写入、列表、导出和 clear 带 Tenant；平台审计与 Tenant 审计不能混表冒充 | 本线 audit 切片；平台审计归 PM01 |
| ThinkPHP runtime log/诊断 | mixed audience | Tenant 请求/任务记录稳定 `tenant_id`、request/operation identity；未知 Tenant 明确标成 unavailable 且不得继续 Tenant 副作用 | 本线 diagnostics adapter |
| Article/Cate/Collect | tenant-owned SQL | 已由 MT02 Article owner 独占；只登记、不修改 | MT02 Article owner |
| 默认 Tenant、管理员/RBAC、岗位 | tenant-owned/bootstrap | 已由 MT02 bootstrap owner 独占；本线不得修改其 schema、安装或映射 | MT02 bootstrap owner |
| 其他 SQL 业务表 | 待逐域分类 | tenant-owned 表最终必须 `tenant_id NOT NULL`、Tenant-first 查询和复合约束；实例/平台配置必须显式登记，不能用 NULL/0 混用 | 各领域后续合同 owner |

## 5. 首个实现切片：Tenant cache key 与 lock namespace

合同合入后立即实现一个无数据库、无 HTTP 推导的最小 port：

- `TenantScope` 持有经可信边界显式注入的正整数 Tenant ID 与非空 context identity，构造失败
  即 fail closed；它不依赖最终 MT01 包身份，也不从 payload/request 恢复 Context。
- `TenantNamespace` 对 cache key、cache tag 和 lock seed 使用固定版本化格式及长度前缀或摘要，
  避免简单字符串拼接歧义；logical key 为空、含控制字符或超过合同上限时拒绝。
- `TenantCache` 只通过注入的最小 cache store port 执行 get/set/delete；不得暴露 raw store、
  全局 clear 或接收调用方自带 tenant prefix。删除只删除当前 scope 的物理 key。
- `TenantLockNamespace` 只产生 Tenant-scoped lock name；不抢占 OAuth、充值、Crontab 或 Article
  文件 owner，不在首片直接改它们的调用链。
- fixture 使用两个可信 scope 对同一个 logical key/lock seed 操作，证明物理 namespace 不同、
  A 删除不影响 B、无/伪造 scope 在 store/lock 观察到任何调用前拒绝。

首片是 adapter/port 和 fail-closed fixture，不声称现有 Cache/lock 调用已经全部接入。后续每个
真实调用方必须在其 owner 白名单内逐个采用，不能以 adapter 存在替代隔离验收。

## 6. 精确白名单与并发禁改集

合同提交只允许新增本文。

首个 cache/lock 实现提交只允许：

- `server/app/common/service/tenant/TenantScope.php`；
- `server/app/common/service/tenant/TenantNamespace.php`；
- `server/app/common/service/tenant/TenantCacheStore.php`；
- `server/app/common/service/tenant/TenantCache.php`；
- `server/app/common/service/tenant/TenantLockNamespace.php`；
- `server/tests/Multitenancy/TenantCacheLockIsolationTest.php`；
- `.github/workflows/ci.yml`，仅登记 `MT03-CACHE-LOCK-001` 聚焦组；
- 本文的首片实施证据段。

禁止修改：

- `server/database/install.php`、默认 Tenant/Account/TenantMember/owner、管理员/RBAC 的任一
  migration/model/service/test；
- 所有 Article model/logic/controller/validator、Article migration/test 和装修 Article 引用；
- Core 仓、Generator、Generated Host、Admin Web、manifest/lock、发布 workflow；
- 根 `AGENTS.md`、共享权威计划、`server/database/init.sql`；
- `docs/likeadmin-parity-report.md`。

文件、任务、导出和 audit 后续切片必须先在本文追加各自精确白名单，并以独立 commit/PR
进入 `dev`。若必须碰其他 owner 文件或白名单不足，只停止该文件并继续无冲突切片；不得越界。

## 7. 首片唯一最低验证

`MT03-CACHE-LOCK-001` 使用内存 fake store，不写数据库、真实 cache、锁或文件。一次证明：

1. 两个 Tenant 对相同 logical cache key/tag 和 lock seed 得到不同、稳定、无歧义物理名；
2. A set/get/delete 不能读取或删除 B 的相同 logical key，B 的值保持不变；
3. 缺失/零/负数 Tenant、空 context identity、空/非法 logical name 全部在 store 调用前拒绝；
4. 调用方伪造的 `tenant_id` payload 不被任何 API 接受，adapter 不读取 request/payload；
5. 没有 raw clear、raw key 或跨 Tenant cleanup 入口。

固定命令：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Multitenancy/TenantCacheLockIsolationTest.php
```

同组只运行一次；另做白名单 PHP 8.3 lint、`git diff --check` 和白名单 diff 检查。不运行 CAP、
MT02、MySQL、全量测试、浏览器或其他 owner 测试。失败后最多一次只读诊断并停止。

## 8. PR、合入与停止线

合同必须先以独立 commit/PR 指向 `dev`；只有最新 head 的全部声明检查均为
`COMPLETED/SUCCESS` 后人工合入，禁止 auto-merge。合同合入后从最新 `dev` 创建首片分支。
首片同样以独立 commit/PR、全绿后人工合入；旧 head 或其他 PR 的检查不能借用。

合同 PR 合入只表示边界冻结。首片 PR 合入只表示显式注入的 Tenant cache/lock namespace port
可供后续 Runtime 采用。现有应用 Cache、文件、Crontab、导出、日志调用链未逐项采用并通过
两个 Tenant 的真实聚焦验收前，仍不构成 MT03 全链路隔离完成证据。

## 9. 首片实施证据

- 合同 PR #35 的 PHP 8.3、Web、PC、UniApp、Docs site 五项声明检查全部成功，已人工
  合入 `dev`；merge commit `f86b283962888296aa2394563fc7b5866f788c2e`。
- 首片新增显式可信 scope、版本化 cache key/tag、受 MySQL 64-byte 限制约束的 lock name、
  无全局 clear/raw store 的最小 cache port，以及内存隔离 fixture；尚未宣称既有业务调用方接入。
- 本地依赖严格从既有 `server/composer.lock` 恢复，未修改 Composer manifest/lock；PHP 8.3.24
  下 `MT03-CACHE-LOCK-001` 唯一行为组一次通过。
- 同一组证明两个 Tenant 的相同 logical key/tag/lock seed 物理名不同且稳定，Tenant A 删除不
  影响 Tenant B；无效 scope、伪造 payload 形状和非法 logical name 在 store 调用前 fail closed。
- 白名单 PHP 文件使用 PHP 8.3 聚焦 lint；最终精确写集和 `git diff --check` 通过。未运行 CAP、
  MT02、MySQL、全量测试或浏览器，也未修改 Article、bootstrap/install 或其他 owner 文件。
