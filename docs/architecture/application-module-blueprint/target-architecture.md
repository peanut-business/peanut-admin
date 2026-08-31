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

当前 `tenant` 目录只负责管理端 Tenant session 的登录、选择、切换和刷新。它没有独立受众，也没有独立
业务 owner，因此目标态合并进 `adminapi` 的认证子域，不继续作为第六个 HTTP Application。

### 2.2 非 HTTP Host

| Host | 入口 | Context 来源 | 规则 |
| --- | --- | --- | --- |
| Console | CLI 参数与已登记环境 | 明确 system actor、目标 Tenant、operation id | 命令只适配输入并调用 Use Case |
| Worker/Crontab | 已持久化 Job/Attempt envelope | 提交时快照 + 执行前复核 | 每次 Attempt 新作用域，失败可诊断、可重试 |
| Callback consumer | Queue/Provider event | 签名和绑定后的 Provider Context | 与 HTTP callback 使用同一 Module Command |
| WebSocket | connection handshake + 每条 message | 连接身份不是永久业务授权；每条消息重验操作范围 | 独立进程 Host，每条消息结束清 Context |

当前没有必要为了“以后可能有 WS”创建空目录或切换到 Hyperf。真实 WS 功能出现时创建独立 Host，复用现有
Module Application；这样增加协议不会搬动 Article/Payment 等业务规则。

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
├── integrationapi/           # ThinkPHP Application：外部 Provider 入口
├── installation/             # ThinkPHP Application：一次性安装入口
├── command/                  # Console/Worker adapter
├── Modules/                  # 业务 owner，结构见 module-conventions.md
└── common/                   # 真正跨入口且无业务 owner 的少量共享能力
```

目录不要求为空也提前创建。目标规则是“允许的归属固定”，当某个 Application 首次需要 `application/` 或
`validate/` 时再建立；已经存在的目录按最终命名收敛。

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

任何一层都不能替代下一层。`TenantContext` 是唯一权威，ORM scope 是它的消费者，不是第二权威。

## 6. 消费端请求流程

消费端按 endpoint 明确分三种，不设置“所有 API 都假定同一种 Tenant”的全局开关。

### 6.1 匿名且不区分 Tenant

```text
/api/public-capability
  -> Trace / Problem / RateLimit
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

Consumer Context 与 Admin Context 是不同类型。它们可以共享 `TenantId` 值对象，但不能共享“管理员 RBAC 已通过”
之类的语义。消费端业务权限由对应 Module 定义，例如“只能修改自己的收藏”，不是套用管理菜单权限。

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

Platform 操作者不是任意 Tenant 的管理员。跨 Tenant 操作必须调用有审计的 owner Command/Gateway；禁止通过修改
全局 Tenant Context 或关闭 ORM scope 获得数据。这样平台控制面和普通管理请求不会共享“万能 Tenant”。

## 8. Provider 回调流程

```text
/integrationapi/payment/wechat/notify
  -> 原始 body 长度/格式限制
  -> Provider signature + timestamp/replay 校验
  -> 回调标识解析到可信 Tenant/订单
  -> 幂等 receipt 预留
  -> Payment Command（一个事务边界）
  -> 提交业务结果与 receipt
  -> Provider 协议响应
```

Provider 回调不经过浏览器登录或 RBAC。它的安全边界是签名、可信绑定、幂等和业务状态机。HTTP Controller 不
直接更新订单，Module 也不从全局 Request 读取签名或 Tenant。

## 9. Job、Crontab 和异步任务流程

```text
提交请求
  -> 已授权 Use Case
  -> 创建 Job + Attempt 初始状态
  -> 保存 tenant/audience/actor/operation/trace/idempotency envelope
  -> 事务提交

Worker
  -> 领取 Attempt
  -> 新建 Execution Scope
  -> 复核 actor、Tenant、Module 和当前授权/状态
  -> 调用同一个 Module Command
  -> 记录 attempt outcome/reason/trace
  -> finally 清理 Context 与连接状态
```

不能把提交时的 PHP 对象、Request 或全局 Context带进 Worker。是否重试由稳定错误分类决定；一次性 OAuth code、
支付 receipt 等不可重放输入必须明确标成不可重试或换取新的输入。

## 10. WebSocket 流程

WS Host 只在真实功能需要时加入，但合同现在固定：

1. handshake 建立连接身份，不把它当成永久业务授权；
2. 每条 message 都有 message id、trace、operation 和有限 payload；
3. message handler 新建作用域，重新检查 Session revision、Tenant/业务目标和权限；
4. 调用与 HTTP 相同的 Module Query/Command；
5. 发送响应或事件后在 `finally` 清理 Context、事务和临时订阅；
6. 进程级单例只保存不可变配置、连接池和 registry，不保存当前用户或 Tenant。

## 11. Module 怎样加载

目标加载分为两个时点：

### 构建/启动期

1. 从受信 `plugins.lock` 和每个 `module.json` 编译唯一 Module registry；
2. 校验 key、依赖图、版本、Provider、权限/菜单/设置和自有表声明；
3. 由容器注册 Provider 的公开能力；
4. 缓存不可变 registry，生产请求不扫描目录；
5. Application 只加载自己的 route/middleware/provider。

### 请求/消息期

1. Application 路由已经知道 module key 与 operation；
2. guard 查询编译 registry、安装状态和 TenantModule 状态；
3. Controller 从容器取得公开 Use Case/Contract；
4. 业务服务不再临时 `new ModuleProvider()`。

Provider 是“启动期装配说明”，不是跨 Module 的 Service Locator。它可以绑定对象和公开能力，但不拥有最终 HTTP
路由，也不保存请求状态。

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
| Trace/请求 ID | 最外层 middleware；消息 envelope 传播 | 每个 Host 入口 |
| 统一错误 | Application Problem middleware + 稳定业务异常映射 | Application |
| 身份 | audience 专属 middleware/factory | Application/Host |
| Tenant | audience 专属 Context factory；ORM scope 只消费 | Application + persistence |
| RBAC | adminapi/platform 专属 middleware | 对应 Application |
| 业务数据权限 | 已授权 Context + 显式 scope policy | Module Query/persistence |
| 事务 | 写 Use Case 最外层 Application Service | Module/Application 用例 |
| 审计 | middleware 记录入口 outcome，owner Command 记录关键业务结果 | Application + Module |
| Outbound HTTP | 注入的统一 transport，记录每次 attempt | common infrastructure |
| 缓存/锁 namespace | 统一 namespace builder，调用方提供 owner key | common infrastructure |

不同时使用“显式 Tenant 参数”和“全局 ORM scope”作为两套可选择权威；不同时使用 middleware、Controller 基类和
Service 手写三套认证；不让每个 Module 自建 trace、锁或 HTTP client。

## 14. 性能原则

1. 路由、Module 依赖和 Provider 在启动/构建期解析一次，不在每个请求扫描文件。
2. Context 是轻量不可变对象，数据库过滤在 SQL 生成阶段完成，不先加载全量数据再 PHP 过滤。
3. 简单查询允许 Use Case 直接使用自有 Model；只有复杂查询复用时才增加 Repository。
4. 列表固定分页和字段白名单；大文件流式输出；外部 HTTP 设置有界 timeout。
5. 同一写 Use Case 使用一个外层事务；跨 Module 同步 Command 必须参与调用方事务。
6. 观察真实慢点后再加缓存，不为“可能高性能”创建第二套读模型或消息总线。

高性能是更少的重复解析、IO 和对象泄漏，不是更多抽象名称。

## 15. 可观测性最低合同

每次入口至少能关联：

- `trace_id`、`operation_id`、Application/Host、route/command；
- audience 与脱敏 actor id；
- Tenant id（仅适用时）和 module key；
- permission/operation、idempotency key（适用时）；
- outcome、稳定 reason/error_code、耗时；
- Job/Attempt 或 Provider receipt 身份；
- outbound HTTP 每次 attempt，而不只记录最终异常。

日志不得记录 token、密码、Provider secret、完整支付报文或未脱敏个人数据。诊断束聚合同一 trace 的 HTTP、Job、
审计和外部调用投影，但不成为新的业务真值。
