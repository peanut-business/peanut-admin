# 目标架构与完整执行流程

## 1. 两张互相垂直的地图

Peanut Admin 后端不能只用一棵目录树表达所有关系。它实际有两张互相垂直的地图：

- **入口地图**：管理员、会员、匿名访问者、平台运营者、外部 Provider、安装器、Job 和 WS 消息；
- **业务地图**：Article、Member、Payment、Notification、File、Task、ImportExport 等能力。

Application 是入口地图上的边界，Module 是业务地图上的边界。一个管理端 Article 请求同时经过
`adminapi Application` 和 `Article Module`；一个公开 Article 请求经过 `api Application` 和同一个
`Article Module`。这不是重复分层，而是分别回答“谁能进来”和“进来后要做什么”。

## 2. Application 固定清单

### 2.1 HTTP Application

| Application | 稳定 URL 前缀 | 访问者 | 身份与范围 | 不允许承担 |
| --- | --- | --- | --- | --- |
| `adminapi` | `/adminapi/*` | 管理员 | 管理账号、Tenant 选择后的 Admin Context、RBAC、DataScope | 会员登录、Platform 身份、Module 业务表直写 |
| `api` | `/api/*` | 匿名用户、业务会员 | 可选 Member Session；需要时建立 Consumer Tenant Context | 管理 RBAC、从管理 Session 推断 Tenant |
| `platform` | `/platformapi/*` | 平台运营者 | Platform Session 与 Platform Permission；目标 Tenant 是命令目标 | 切换成 Tenant 管理员、绕过 Module owner 直写 |
| `integrationapi` | `/integrationapi/*` | 支付/OAuth/短信等 Provider | Provider 验签、重放防护、幂等 receipt、可信 Tenant 绑定 | 浏览器 Session、管理端 RBAC |
| `installation` | `/installapi/*` | 一次性安装操作者 | 安装 token、环境和生命周期门禁 | 安装完成后的日常业务接口 |

前缀是目标合同，不表示当前 API 已经迁移。实施时应一次切换对应后端、OpenAPI 和客户端，不保留
`/api/admin` 与 `/adminapi` 两套永久入口。

`integrationapi` 是首个真实 Payment/OAuth callback 的目标归属，不是地基阶段要预建的空 Application。目录、
provider 和路由与首个真实 callback 同一纵向切片创建；在此之前，本表只冻结名称、前缀和安全合同。

当前 `tenant` 目录只负责管理端 Tenant session 的登录、选择、切换和刷新。它没有独立受众，也没有独立
业务 owner，因此目标态合并进 `adminapi` 的认证子域，不继续作为第六个 HTTP Application。

### 2.2 非 HTTP Host

| Host | 入口 | Context 来源 | 规则 |
| --- | --- | --- | --- |
| Console | CLI 参数与已登记环境 | 明确 system actor、目标 Tenant、operation id | 命令只适配输入并调用 Use Case |
| Worker/Crontab | 已签名的 Job envelope；claim 后的 Attempt/lease | System actor + 执行前复核 | Job 是意图；成功 claim 才创建 Attempt；提交者只作 causation，不冒充 Admin |
| WebSocket | connection handshake + 每条 message | 连接身份不是永久业务授权；每条消息重验操作范围 | 独立进程 Host，每条消息结束清 Context |

当前没有必要为了“以后可能有 WS”创建空目录或切换到 Hyperf。真实 WS 功能出现时创建独立 Host 和 Message
专属窄端口，只复用同一 owner 的领域规则、Repository 和表；这样增加协议不会搬动 Article/Payment 等业务规则。

## 3. 目标目录

```text
server/app/
├── adminapi/                 # ThinkPHP Application：管理后台
│   ├── controller/           # HTTP 输入/输出适配，按业务名分组
│   ├── application/          # 仅跨 Module 或管理会话编排
│   ├── middleware/           # 管理身份、Admin Tenant、RBAC、审计
│   ├── validate/             # 管理端请求白名单与格式
│   ├── route/                # 只登记 adminapi 路由
│   ├── config/
│   ├── provider.php
│   └── middleware.php
├── api/                      # ThinkPHP Application：会员/匿名消费端
│   ├── controller/
│   ├── application/
│   ├── middleware/
│   ├── validate/
│   ├── route/
│   ├── config/
│   ├── provider.php
│   └── middleware.php
├── platform/                 # ThinkPHP Application：平台控制面
├── integrationapi/           # 首个真实 callback 到来时创建：外部 Provider 入口
├── installation/             # ThinkPHP Application：一次性安装入口
├── command/                  # Console/Worker adapter
├── Modules/                  # 业务 owner，结构见 module-conventions.md
└── common/                   # 真正跨入口且无业务 owner 的少量共享能力
```

目录不要求为空也提前创建。目标规则是“允许的归属固定”，当某个 Application 首次需要 `application/` 或
`validate/` 时再建立；已经存在的目录按最终命名收敛。

### 3.1 唯一 composition root 与顶层执行单元

一个进程只有一个 composition root。根启动器读取唯一 Composer lock、Application 配置、可信 Module registry，
然后一次性构建容器；Application provider 与 Module provider 只贡献启动期 binding，不得各自建立第二容器，
也不得在请求期扫描目录、`new ModuleProvider()`、调用业务 `app()`/Facade 或静态 `production()` factory。

顶层执行单元是一次 HTTP 请求、CLI 操作、Worker Attempt、Provider callback 或未来一条 WS message。每个顶层单元：

1. 先验真并建立唯一强类型 Context；
2. 再进入 Module guard、授权、DataScope 和业务端口；
3. 正常或异常都在 `finally` 清空 Context、事务、锁和 lease；
4. 内部调用不得切换 actor、audience 或当前 Tenant，也不得再建立嵌套万能 Context。

其中 tenant-scoped 端口恰好有一个 current Tenant；认证、instance-public 与 Platform Context 明确 tenantless。
Platform 的 `TargetTenantId` 只是授权后的 Command 目标，不是第二个或 ambient Tenant。

### 3.2 同一 owner 的受众隔离

同一 Module 可拥有多个公开端口，但端口必须体现受众和业务语义，例如 Article 的
`ArticleAdministration`、`PublicArticleQueries`、`ArticleQueries` 与 `ArticleModuleAccess`。它们可以复用同一领域规则、
Repository 和表，但必须使用不同强类型 Context/actor、授权 policy、DataScope、输入 DTO、输出 DTO 和 Host 映射。
未来 WS 若被新的架构决定采用，也必须调用 Message 专属窄端口，只复用同一 owner 的规则、Repository 和表。

禁止 `save(array $data)`、`list(array $filters)`、`execute($actorType, $context, $attributes)` 等万能入口，也禁止让
Consumer、Platform、Provider 或 System 端口接收 Admin Context。Module 不读取 Request、Session 或 route 猜受众；
它通过端口类型接收已经裁决的最小 actor、tenant-scoped 时唯一的 current Tenant、scope、revision、trace 和幂等证据。

## 4. 当前请求为什么看不懂

当前流程实际是：

```text
public/index.php
  -> 根 ThinkPHP App
  -> server/route/app.php
       -> require platform.php / tenant.php / admin.php / public_api.php
       -> require 所有 official Module route
  -> 路由各自拼 middleware
  -> adminapi/api/platform/Module Controller
  -> Application Service / common Service / ModuleProvider
  -> Model/PDO
```

`server/app/AppService.php` 不是某个业务的 App Service。它是整个根应用的容器启动服务，集中绑定 Context、
审计、授权、Module Contracts、组织 Runtime、外部 HTTP 等大量对象。与此同时，很多调用点又绕过容器，临时
`new MemberModuleProvider()`、`new ArticleModuleProvider()`。Module 自己还拥有 `Http/routes.php` 和
Controller。

因此路径无法回答：当前究竟属于哪个 ThinkPHP Application，Provider 是否是单例，Tenant Context 从哪来，
同一业务为何既有 API Application Service 又有 Module Application。目标架构不会再用一份根路由和一份大型
AppService 隐式解释这些关系。

## 5. 管理端请求流程

### 5.1 登录和 Tenant 选择前

```text
/adminapi/session/login 或 /adminapi/session/select
  -> adminapi 路由
  -> Trace + Problem middleware
  -> 登录限流 / challenge 校验
  -> 建立仅用于本次认证流程、明确无业务 Tenant 的 Admin authentication Context
  -> Admin identity use case
  -> 选择 Tenant 时验证 TenantMember 与状态
  -> 生成带 audience=adminapi 的 Admin Session
  -> 响应
```

此阶段还没有业务 Tenant Context。客户端传来的 `tenant_id` 只能作为“申请选择的目标”，经过服务端成员关系
验证后写入新 Session，不能直接成为当前执行 Tenant。

### 5.2 已登录管理请求

```text
/adminapi/article/list
  -> Trace / 统一异常与 Problem
  -> AdminSessionMiddleware
  -> AdminTenantContextMiddleware
  -> ModuleEnabledMiddleware(official.article)
  -> PermissionMiddleware(official.article.list)
  -> Article Controller + Article request validation
  -> ArticleAdministration query
  -> DataScope 消费同一个可信 Admin Context
  -> Article 自有 Model/表
  -> Audit outcome + response
  -> finally 清理 Context
```

固定责任如下：

- Session middleware 只证明“是谁”；
- Tenant middleware 只建立“这次代表哪个 Tenant”；
- Module guard 只证明“应用已安装且 Tenant 已开通”；
- Permission middleware 只证明“此成员能执行哪个操作”；
- DataScope 只收窄“能看到哪些业务对象”；
- Module Use Case 仍检查状态机、余额、库存、revision 等业务不变量；
- 审计同时记录成功和失败的明确 outcome/reason。

任何一层都不能替代下一层。每个顶层执行单元的 audience Context 是唯一权威，ORM scope 是它的消费者，不是
第二权威。Tenant 相关限制进一步拆成两层：

- `TenantOwnershipScope`：保证读写只落在当前逻辑 Tenant，Standalone 也使用真实 TenantId；
- `ObjectScopePolicy`：表达部门、本人、公开资源、会员 ownership 等对象级业务范围。

两层都必须 fail-closed 并进入最终查询；前者不能冒充完整 DataScope，后者也不能覆盖或重新推导 Tenant。客户端
payload 的 `tenant_id`、订单字段、显式 Repository 参数和 `withoutGlobalScope()` 都不得成为替代权威。

## 6. 消费端请求流程

消费端按 endpoint 明确分三种，不设置“所有 API 都假定同一种 Tenant”的全局开关。instance-public 端口明确
tenantless；其余 tenant-scoped 端口必须从可信 Host/member binding 得到恰好一个 current Tenant。

### 6.1 匿名且不区分 Tenant

```text
/api/public-capability
  -> Trace / Problem / RateLimit
  -> Anonymous ConsumerExecutionContext（显式 instance-public、无 Tenant）
  -> Controller
  -> Module public Query
```

### 6.2 匿名但按站点/Tenant 提供内容

```text
/api/article/list
  -> Trace / Problem / RateLimit
  -> ConsumerTenantResolver（可信 host/binding，不读任意 tenant_id）
  -> ModuleEnabled(official.article)
  -> Controller
  -> Article public Query + ConsumerTenantContext
```

### 6.3 登录会员

```text
/api/article/collect
  -> Trace / Problem
  -> MemberSessionMiddleware
  -> 可选 ConsumerTenantResolver
  -> Member 与 Tenant 绑定检查（若该能力需要）
  -> ModuleEnabled + Controller
  -> Article collection Command
```

Consumer Context 与 Admin Context 是不同类型。它们可以共享 `TenantId` 值对象和 Context 生命周期机制，但不能共享
Session、RBAC、对象范围、DTO 或“管理员 RBAC 已通过”之类的语义。消费端业务权限由对应 Module 定义，例如
“只能修改自己的收藏”，不是套用管理菜单权限；匿名访问也不得包装成 System actor。

## 7. Platform 请求流程

```text
/platformapi/tenants/{id}/modules
  -> Trace / Problem
  -> PlatformSessionMiddleware
  -> PlatformPermissionMiddleware
  -> Platform Controller
  -> Platform orchestration service
  -> 目标 Tenant 作为 command target
  -> TenantModule owner Command / 显式 Gateway
  -> 双边审计
```

Platform 操作者不是任意 Tenant 的管理员。Platform Context 自身没有当前 Tenant；经过 Platform permission 裁决的
`TargetTenantId` 只是特定 Command 的目标。跨 Tenant 操作必须调用有审计的 owner Command/Gateway；禁止修改全局
Tenant Context、关闭 ORM scope，或把 Platform actor 转成 Tenant Admin。

## 8. Provider 回调流程

```text
/integrationapi/payment/wechat/notify
  -> 原始 body 长度/格式限制
  -> Provider signature + timestamp/replay 校验
  -> Provider binding 解析出唯一可信 Tenant
  -> ProviderExecutionContext
  -> Payment Provider 专属 Command（一个事务边界）
  -> 以 provider/binding/event-id 或 canonical digest 原子预留 receipt
  -> 原子提交业务结果、receipt 与强制 AuditEvent
  -> Provider 协议响应
  -> finally 清理 Context、事务和锁
```

Provider 回调不经过浏览器登录或 RBAC。它的安全边界是签名、可信绑定、幂等和业务状态机。HTTP Controller 不
直接更新订单，Module 也不从全局 Request、payload 或订单字段重新推导 Tenant。相同 receipt key 与相同请求摘要
返回已记录结果；相同 key 与不同摘要必须冲突。成功、拒绝和暂态失败各由 Provider Host 映射稳定 ACK，不能用
`catch (Throwable)` 吞掉异常后统一返回成功。

## 9. Job、Crontab 和异步任务流程

```text
提交请求
  -> 已授权 Use Case
  -> 创建 Job 意图
  -> 保存 schema/job/owner/operation/tenant/payload-digest/system-policy/causation/
     authorization-revision/trace/issued-at/signature envelope
  -> 原子提交 Job、业务引用和强制 AuditEvent

Worker
  -> 原子 claim Job + 创建 Attempt/fencing/lease
  -> 验证 envelope 签名与 payload digest
  -> 新建 Execution Scope
  -> 建立 SystemExecutionContext
  -> 复核 Tenant、Module、handler 和当前授权/状态
  -> 调用 handler owner 的 System 窄端口/Command
  -> 记录 succeeded/retryable/pending-unknown/dead、稳定 reason 和 trace
  -> finally 清理 Context、连接、锁和 lease
```

Job 是待执行意图，Attempt 是一次真实 claim/执行事实；提交时不得创建“初始 Attempt”。不能把 PHP 对象、Request、
全局 Context 或“当前 Admin”带进 Worker，提交者只作为 causation，不是执行 actor。是否重试由稳定错误分类决定；
一次性 OAuth code、支付 receipt 等不可重放输入必须明确标成不可重试或换取新的输入。长任务必须续租并使用 fencing，
过期 Attempt 不能提交新结果。

## 10. WebSocket 流程

WS Host 只在真实功能需要时加入，但合同现在固定：

1. handshake 建立连接身份，不把它当成永久业务授权；
2. 每条 message 都有 message id、trace、operation 和有限 payload；
3. message handler 新建作用域，重新检查 Session revision、Tenant/业务目标和权限；
4. 调用 Message 专属窄端口，只复用同一 owner 的领域规则、Repository 和表；
5. 发送 frame/message 后在 `finally` 清理 Context、事务和临时订阅；
6. 进程级单例只保存不可变配置、连接池和 registry，不保存当前用户或 Tenant。

## 11. Module 怎样加载

目标加载分为两个时点：

### 构建/启动期

1. 从受信 `plugins.lock` 和每个 `module.json` 编译唯一 Module registry；
2. 校验 key、依赖图、版本、Provider、权限/菜单/设置、公开 Contract 和 `owned_tables`；最终 manifest 不含
   `backend.routes`；
3. 唯一 composition root 接受 Application/Module provider 的 binding 贡献并构建容器；
4. 缓存不可变 registry，生产请求不扫描目录；
5. Application 只加载自己的 route/middleware/provider。

### 请求/消息期

1. Application 路由已经知道 module key 与 operation；
2. guard 查询编译 registry、安装状态和 TenantModule 状态；
3. Controller 从容器取得与其 audience 匹配的公开窄端口；
4. 业务服务不再临时 `new ModuleProvider()`。

Provider 是“启动期注册贡献者”，不是 composition root、跨 Module Service Locator 或 Runtime factory。它只声明
binding，不构建第二容器、不拥有最终 HTTP 路由、不保存请求状态，也不暴露请求期 accessor。

## 12. `AppService` 的目标职责

根 `AppService` 最终只保留所有 Application 真正共享的启动能力，例如数据库连接装配、不可变 Module registry、
基础 trace/outbound HTTP transport 和少量 Core binding。下列内容移出：

- Admin 会话、RBAC、DataScope → `adminapi/provider.php`；
- Member 会话和 Consumer Tenant → `api/provider.php`；
- Platform operator 和控制面 → `platform/provider.php`；
- Provider 签名/回调绑定 → `integrationapi/provider.php`；
- Module 自有 Commands/Queries → 对应 ModuleProvider，由 registry 注册。

这样查看一个 Application 的 `provider.php + middleware.php + route/` 就能知道它完整的运行链，不需要先理解全局
AppService 的所有绑定。

## 13. 横切能力放在哪里

| 能力 | 唯一机制 | 可见位置 |
| --- | --- | --- |
| Trace/请求 ID | 最外层 middleware；Job/消息 envelope 传播 | 每个 Host 入口 |
| 稳定错误 | 一个 classifier；各 Host 分别映射 Problem/exit/ACK/Attempt/frame | shared mechanism + Host renderer |
| 身份 | audience 专属 middleware/factory，输出强类型 Context | Application/Host |
| Tenant ownership | Context 是唯一权威；ORM/non-ORM scope 只消费 | Application + persistence |
| RBAC/入口授权 | adminapi/platform 专属 middleware/policy | 对应 Application |
| 对象级 DataScope | 已授权 Context + audience 专属 scope policy | Module Query/persistence |
| 事务 | 拥有完整业务结果的最外层 Module Command | owner Module |
| 幂等 receipt | owner、scope、operation、key、request hash 的唯一事实 | owner Module |
| 强制审计 | 与业务结果同事务的 canonical `AuditEvent` | owner Module + transaction participant |
| 入口日志/诊断束 | 脱敏、限界、可重建投影；失败不回滚业务 | Host/observability projection |
| Outbound HTTP | 注入的统一 transport，记录每次网络 attempt | common infrastructure + owner mapping |
| 缓存/锁 namespace | 确定性 builder；调用方提供结构化 owner/scope/business key | common infrastructure |

不同时使用“显式 Tenant 参数”和“全局 ORM scope”作为两套可选择权威；不同时使用 middleware、Controller 基类和
Service 手写三套认证；不让每个 Module 自建 trace、锁、错误分类或 HTTP client。

## 14. 事务、幂等、审计与外部副作用

1. 拥有完整业务结果的最外层 Module Command 打开并提交事务；Controller、middleware、Host 编排、Model observer
   和跨 Module participant 都不能提交。
2. 同进程跨 Module Command 必须接收同一 connection/transaction handle 并只参与；无法证明连接相同时停止实施，
   不能用嵌套 `Db::transaction()` 假装原子。
3. 关键领域数据、命令/Provider receipt 和强制 `AuditEvent` 在同一事务中成功或回滚。普通入口日志、metrics 和
   诊断束不是业务真值，写入失败不得冒充业务失败或成功。
4. 外部 HTTP 不进入数据库事务。owner 先用短事务固定本地状态与幂等身份，事务外调用 Provider，再用新短事务
   写入确定结果；结果未知时进入显式 `pending-unknown/reconcile`，不能声称本地 rollback 回滚了外部世界。
5. 只有幂等且被稳定错误分类标记为 retryable 的执行才开启新事务重试。相同幂等 key 与不同 request hash 必须拒绝。
6. 本蓝图明确排除通用 Outbox 和 Event Bus，同步业务决定直接调用 Command；未来可靠异步需求只能通过新的架构
   决定显式取代本合同，不能在本蓝图实施中顺带加入。

## 15. 锁、错误与清理

- advisory lock key 由固定 builder 接收 runtime、owner、scope type 和业务 key，生成确定性、Tenant 隔离且不超过
  存储限制的 namespace；固定格式为 `pa:v1:<runtime12>:<owner8>:<scope8>:<business24>`，各段使用稳定小写摘要，
  原始 owner/scope/business 只进入脱敏日志。业务代码不能提交任意字符串锁名。锁只串行化竞争，不替代事务、
  revision 或唯一约束。
- timeout、冲突、拒绝、暂态基础设施失败和未知异常进入同一个稳定分类器；HTTP、CLI、Worker、Provider 和未来
  WS 只共享 code/retryability，不共享响应协议。未知异常必须脱敏并带 trace，禁止返回原始 message/stack。
- 每个顶层执行单元无论成功、失败或重试，都在 `finally` 清空 Context、活动事务、锁、lease、临时流和文件；
  长进程不得把当前 actor、Tenant、Request 或 transaction 保存在进程级单例中。

## 16. 性能原则

1. 路由、Module 依赖和 Provider 在启动/构建期解析一次，不在每个请求扫描文件。
2. Context 是轻量不可变对象，数据库过滤在 SQL 生成阶段完成，不先加载全量数据再 PHP 过滤。
3. 简单查询允许 Use Case 直接使用自有 Model；只有复杂查询复用时才增加 Repository。
4. 列表固定分页和字段白名单；大文件流式输出；外部 HTTP 设置有界 timeout。
5. 同一写用例只使用 owner Command 的一个外层事务；跨 Module 同步 Command 只参与该事务。
6. 观察真实慢点后再加缓存，不为“可能高性能”创建第二套读模型或消息总线。

高性能是更少的重复解析、IO 和对象泄漏，不是更多抽象名称。

## 17. 可观测性最低合同

每次入口至少能关联：

- `trace_id`、`operation_id`、Application/Host、route/command；
- audience 与脱敏 actor id；
- Tenant id（仅适用时）和 module key；
- permission/operation、idempotency key（适用时）；
- outcome、稳定 reason/error_code、retryability、耗时；
- Job/Attempt 或 Provider receipt 身份；
- outbound HTTP 每次 attempt，而不只记录最终异常。

日志不得记录 token、密码、Provider secret、完整支付报文或未脱敏个人数据。强制 AuditEvent 至少记录脱敏 actor、
operation、resource、outcome、reason 和 trace，并与关键业务原子提交。诊断束聚合同一 trace 的 HTTP、Job、审计和
外部调用投影，但不成为新的业务真值，也不能包含原始 callback、凭据引用值或个人绝对路径。
