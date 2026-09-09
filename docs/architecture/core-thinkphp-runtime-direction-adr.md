# Core ThinkPHP 8 运行时收敛方向 ADR

Document ID: `pa-docs-architecture-core-thinkphp-runtime-direction-adr`

Status: `current`（方向已接受；本页只登记架构决策，不代表 Runtime 已迁移）

Date: 2026-09-09

Owner: `product-architecture`

## 1. 范围与决策摘要

本 ADR 同时约束 Peanut Admin Application 与 Peanut Admin Core 的 PHP 运行时边界。两仓正式技术栈都是 ThinkPHP 8；Core 不再以“框架中立”作为目标，也不再为不存在的非 ThinkPHP 生产消费者长期维护 PDO Repository、`PdoTransactionManager`、重复 RuntimeFactory、CLI 专用 PDO 实现或大规模手工容器装配。

这是一项方向性文档变更。当前源码仍含 PDO 路径，本 ADR 不授权运行时代码、SQL、迁移、前端、发布或部署变更。历史证据和未接受的计划保留其原有身份；本页只把后续实现的最终方向和停止线固定下来。

正式决定如下：

1. Core 只支持 ThinkPHP 8；Core/Application 使用 ThinkPHP Model、Query、Db 和 Transaction 作为正式数据边界。
2. Core 与 Application 分别拥有自己的 Model 和表。跨 Module 业务合同、Tenant/RBAC/Module 生命周期、执行上下文、外部厂商 SDK/HTTP/Storage Driver 仍保留。
3. HTTP、Worker、CLI、Cron 和安装/迁移/种子流程使用同一套正式 ThinkPHP bootstrap；不再以第二套 PDO bootstrap adapter 解决“框架尚未启动”。
4. 普通 ModuleProvider 采用“接口 => 实现类”；只有 primitive 配置、环境选择、SDK、Console 回调和可变 Worker 等必须延迟/动态装配的对象，才允许闭包工厂和显式 `make()`。
5. 迁移按领域微批次进行。每个批次删除同一领域的 PDO 路径并保持真实行为，不做长期双实现、兼容桥、双写或全库正则替换。

## 2. 当前事实基线

### 2.1 两仓依赖和迁移事实

Peanut Admin Application 的 `server/composer.json` 已直接声明 `topthink/framework`、`topthink/think-orm` 和 ThinkPHP 生态依赖，并以 `peanut-admin/core: 0.1.0-alpha.13` 作为 Core 聚合包。Core 根项目 `composer.json` 当前声明：

| 位置 | 当前事实 |
| --- | --- |
| Core 根项目 | `topthink/framework ^8.1.4`、`topthink/think-orm ^4.0.51`、`topthink/think-migration ^3.1.1` |
| Core `backend/composer.json` | ThinkPHP 8 reference host，声明 `topthink/framework ^8.1.4` |
| Core `starter/backend/composer.json` | ThinkPHP 8.1.4、`topthink/think-migration 3.1.1`，用于内部 starter 验证 |
| Core 发布子包 `packages/php/composer.json` | 声明 `ext-pdo`、PHP 8.3、JSON/OpenSSL/Sodium/Fileinfo；当前包源代码仍有 PDO persistence 实现 |
| Application `server/composer.json` | ThinkPHP framework、ORM、multi-app、filesystem 与 Core Alpha.13；业务运行时由 ThinkPHP 启动 |

因此，“Core 没有任何 ThinkPHP 事实”不是现行仓库事实；更准确的说法是：发布子包当前仍把 PDO 当作公共 persistence 入口，而 Core 根项目和参考宿主已经是 ThinkPHP 8。Core 的 migration/owned-migration 由宿主执行，根项目和 starter 对 `topthink/think-migration` 的声明是该事实的依赖证据。后续应把 migration 执行也收敛到正式 ThinkPHP bootstrap，而不是继续扩展裸 PDO runner。

### 2.2 Application 从 ThinkPHP 连接取得 PDO 的现状

`server/app/AppService.php` 的 `database()` 调用 `Db::connect()->connect()`，确认返回 `PDO`，再以 `PDO::class` 绑定到 ThinkPHP 容器。随后 `AppService` 通过 `$this->app->make(PDO::class)` 构造 `PdoTransactionManager`、Core `Pdo*Repository`、认证/授权服务、StorageRepository 以及多个 RuntimeFactory。CLI 的 `DatabaseContextualCommand` 也把 PDO 作为构造依赖提供给命令。

典型当前调用链是：

```text
ThinkPHP HTTP/CLI bootstrap
  -> AppService::register()
  -> Db::connect()->connect() -> PDO::class binding
  -> ModuleProvider/AppService binding closure
  -> RuntimeFactory 或 Module Runtime
  -> Core PdoRepository/PdoTransactionManager
  -> Core service / Application service
```

这条链路说明当前行为，不是目标 API。迁移完成前必须以同一 ThinkPHP 连接、事务和上下文保持现有 Tenant、事务、并发与双 Edition 语义。

### 2.3 Provider、容器、合同和 Factory 的规模

截至 2026-09-09 的源码静态盘点：

| 范围 | 规模和典型形态 |
| --- | --- |
| Application ModuleProvider | 10 个，均实现 `ModuleBindingContributor`；包含 Article、File、ImportExport、Member、Notification、Oauth、Payment、RichText、Task 和 Fixture DeliveryRecord |
| Application ModuleProvider bindings | 普通绑定与闭包混用；例如 File/Notification/Task 通过闭包调用 `$app->make()`，Task 还装配 Console 回调和可变 worker 参数 |
| Application AppService | `AppService` 是大组合根；`server/app` 内统计到 94 处直接 `$this->app->make()`，共 227 处 `make()` 调用，当前同时注册 Context、Auth、RBAC、Storage、Platform、官方 Module 和 CLI 依赖 |
| Application Commands/Queries | 15 个 Commands 合同文件、8 个 Queries 合同文件；合同只应保留真实跨 Module/Host 消费者需要的稳定业务能力 |
| Application RuntimeFactory | 7 个命名为 `RuntimeFactory` 的文件；典型调用者是 HTTP Runtime、Platform service、安装/升级和 Worker |
| Core ModuleProvider | 13 个 Provider 文件，包含 Kernel/DataPermission 合同以及 Example/Peanut 参考 Module；部分方法仍显式收取 `PDO` |
| Core repository | Core `packages/php` 目前有 47 个 Repository 命名文件，其中 26 个是 `Pdo*Repository`；其中一部分是当前可消费 API，一部分只服务参考宿主或测试 |
| Core RuntimeFactory | Core reference backend/starter 与 package 共有 11 个 `RuntimeFactory` 命名文件/用法，属于当前过渡装配面，不作为长期重复入口 |

典型 ModuleProvider 形态包括：`Interface::class => Concrete::class`；或者 `Contract::class => fn(App $app) => new Service($app->make(...))`。Core Example Module 还存在 `referenceQuery(PDO $pdo, ...)` 与 DataPermission provider 收取 PDO 的调用链。它们是后续批次的输入，不是本 ADR 授权的代码变更。

## 3. ADR 裁定

### 3.1 正式技术栈和数据边界

- Core 只支持 ThinkPHP 8，不对 Symfony、Hyperf、Laravel、裸 PHP 或“任意 PHP 容器”作公共运行时承诺。
- Core/Application 分别拥有自己的 Model 和表；“公共”指跨 Module 的业务合同或产品中立机制，不指共享一套 ORM Model 或互相直接写表。
- 业务 Module 自有表优先使用该仓正式 ThinkPHP Model、Query 和 TenantScope；Application 继续使用本仓原生 `TenantOwnedModel` 与全局 Scope。
- Core 的公共 persistence 应逐步改为 ThinkPHP Model/Query/Db/Transaction；不要把 ThinkPHP 再包装成镜像 Repository 以恢复框架中立。
- PDO 仍可在底层扩展、厂商 SDK 或临时迁移实现中存在，但不得作为 Core 公共运行时 API、Module 业务合同或 Application 容器的长期依赖。

### 3.2 执行上下文、Tenant 和 Module 生命周期

`ExecutionContext`、`CurrentExecutionContext`、`TenantScope`、Module install/enable/disable、RBAC、权限目录和 Module manifest 仍是正式合同。HTTP、Worker、CLI、Cron、Provider callback 和安装流程都必须先由 ThinkPHP bootstrap 建立相应强类型上下文，再进入 Model/Query/Service。缺少可信 Tenant 时 fail-closed；不得以 PDO 参数、请求 payload 或默认租户 fallback 代替上下文。

### 3.3 跨 Module 合同与外部边界

真实跨 Module 消费者仍可保留 Commands/Queries、Port 或其他业务合同。它们表达业务能力，不是为了隐藏唯一 ThinkPHP 实现而创建的 persistence interface。外部厂商 SDK、HTTP transport、Storage Driver、Webhook/Provider callback、文件对象传输和重试/幂等合同继续保留；它们是实际替换边界，不属于本 ADR 要删除的“镜像 Repository”。

## 4. 抽象裁定矩阵

| 类别 | 裁定 | 约束和落点 |
| --- | --- | --- |
| `PDO` 公共类型 | 迁移后删除公共运行时暴露 | Core public service、ModuleProvider、Application container 不再要求 `PDO`; 底层实现可在未迁移批次内暂存 |
| `PdoRepository`/`Pdo*Repository` | 按领域迁移并删除 | 不新增同名替代桥；领域完成后移除实现、导出、构造依赖和专用测试入口 |
| `TransactionManager`/`PdoTransactionManager` | 保留事务语义，迁移到 ThinkPHP Transaction/Db | 保持 savepoint、回滚、并发和外部副作用顺序；不以第二个抽象层长期镜像 ThinkPHP |
| ModuleProvider bindings | 普通情况简化为接口 => 实现类 | 只有 primitive 配置、环境选择、SDK、Console 回调、可变 Worker 才允许闭包工厂/显式 `make()` |
| `make()` | 保留在组合根和允许的工厂位置；业务 Service 内删除 | Application/Core 的 bootstrap、ModuleProvider 工厂可显式解析；业务方法不得 `app()`、容器 `make()`、Facade 或零参数静态工厂定位运行时依赖 |
| Commands/Queries | 保留真实跨 Module/Host 业务合同 | 业务合同不暴露 PDO、ORM Model、私有表名；只有单一实现且无消费者的镜像接口删除 |
| Repository/Port/Adapter | 按语义分流 | 外部系统、Storage Driver、HTTP、SDK、不可替换的跨 Module 业务合同保留；仅包裹 ThinkPHP Model/Query 的 persistence interface 删除 |
| RuntimeFactory | 收敛为构造注入与组合根装配 | 只保留有生命周期/环境/延迟装配价值的 Host factory；删除为裸 PDO、CLI 或重复 service graph 创建的 factory |
| `ExecutionContext` | 保留并统一入口 | HTTP/Worker/CLI/Cron/安装器使用同一 ThinkPHP bootstrap，入口建立、`finally` 清理，业务服务只消费注入的强类型上下文 |
| `TenantScope` | 保留并扩大为唯一租户隔离路径 | Tenant-owned Model 继承正式基类并由全局 Scope fail-closed；禁止手写 `tenant_id`、`forTenant()` 和普通 `withoutGlobalScope()` |
| 厂商 SDK/HTTP | 保留 | SDK 由选择厂商的 Host/Module 组合根装配；窄 HTTP transport 保留，禁止复制第二套完整 HTTP DTO/客户端栈 |
| Storage Driver | 保留 | Core 负责产品中立低层传输合同；Application 负责凭据、用途、授权、对象账本、补偿和产品生命周期 |

## 5. 目标 ModuleProvider 范式

普通 ModuleProvider 只声明稳定合同到实现类的映射：

```php
return [
    NotificationQueries::class => NotificationApplicationService::class,
    NotificationCommands::class => NotificationApplicationService::class,
];
```

以下情况允许闭包工厂和显式 `make()`，并必须把原因写在 Provider/组合根附近：

- primitive 配置值、密钥、超时、数量上限或环境开关需要从配置读取；
- deployment mode、edition 或 provider selection 决定实现；
- 厂商 SDK、HTTP client、Storage Driver 需要具体 SDK 类型或凭据装配；
- Console callback、scheduler/dispatcher 等框架回调必须从 ThinkPHP bootstrap 取得；
- Worker、lease、并发上限或 handler registry 是可变运行时状态，不能用静态 class binding 表达。

除此之外，使用构造函数注入。业务 Service 内禁止 `app()`、`make()`、Facade 和静态服务定位。Provider 不建立第二容器、不扫描请求期目录、不保存当前 Tenant/Request 状态。

## 6. 微批次迁移顺序

迁移不是一次全仓改写，按以下顺序形成可回滚微批次：

1. **ModuleProvider 简化**：先删除不需要闭包的闭包绑定，保留确有配置、SDK、Console callback、可变 Worker 需要的工厂。
2. **Core ThinkPHP 数据边界**：建立 Core 的 Model、Query、Db/Transaction、TenantScope 和 ThinkPHP bootstrap 入口；同时固定 Core/Application 表 owner。
3. **ReferenceCodes**：先迁移读取/写入、definition sync、revision/ETag 和权限合同。
4. **Settings**：迁移 scope、secret、ETag、锁和同事务写入。
5. **ArtifactRevision**：迁移 append/compare、revision 与发布事务。
6. **EntitlementQuota / Workflow**：先完成额度 reservation/decision，再迁移流程实例、transition、assignment 与副作用。
7. **Notification**：迁移站内信、模板、outbox/task 和验证码，厂商发送合同保留。
8. **TaskJob**：迁移 job ledger、claim/lease/retry/cancel 和 worker，统一 ThinkPHP bootstrap。
9. **ImportExport**：在 TaskJob 和 FileMedia 边界完成后迁移导入导出操作、文件和失败补偿。
10. **FileMedia**：先迁移元数据/对象账本的 Model/Scope，再保留并验证外部 Storage Driver。
11. **DataPermission**：迁移 policy/query constraint 与目标解析，保持 fail-closed。
12. **Kernel Identity/Tenant/RBAC**：最后迁移 Account、Credential、TenantMember、Tenant、Module 生命周期和 RBAC，避免在前置批次中复制身份权威。

### 6.1 微批次原子完成规则

每个批次必须在同一领域内完成以下闭包：

1. 使用 ThinkPHP Model/Query/Db/Transaction 和正式 bootstrap；
2. 删除该领域对应的 PDO 路径、公共导出、专用 factory 和无消费者镜像接口；
3. 保持现有真实测试断言，不把测试改成 `PASSED` 占位，不把 skip 当通过；
4. 验证 Tenant 隔离、事务回滚、并发/锁/幂等和 Standalone/Multi-tenant 两 Edition；
5. 同一领域只保留一个实现和一个事实源；禁止长期双实现、兼容桥、双写、镜像表和全库正则替换；
6. 记录精确写集、受影响调用链、失败恢复边界和下一微批次，不把未完成迁移写成已采用能力。

## 7. CLI、安装和后台执行收敛

安装、迁移、种子、Worker、Cron、模块同步、升级和 HTTP 都从同一个 ThinkPHP bootstrap 进入。Bootstrap 负责配置、容器、数据库连接、Model maker、Tenant/ExecutionContext、Module registry 和异常/退出码规则；CLI command 只解析输入并调用已注入的 Use Case。

禁止为了 CLI 未启动完整框架而增加第二套 PDO bootstrap adapter。若某个命令尚未能从 ThinkPHP bootstrap 进入，先补齐该入口的最小正式启动合同，再迁移其领域；不得让 CLI 永久拥有另一套连接、事务、ModuleProvider 或 TenantScope 语义。

## 8. 发布和兼容性建议

删除 Core 公共 PDO API（包括 `PdoRepository`、`PdoTransactionManager`、PDO constructor contract 或同等导出）属于破坏性变化。建议评估 Core `0.2.0-alpha.1`，并同时固定：迁移后的 Composer metadata、Application lock、Core/Application 两端的完整 commit/tree、兼容说明、下游采用者和验证证据。

这是版本和发布建议，不是本任务的发布授权。本任务不改版本、不打 tag、不发布 Composer/npm、不更新 lock、不部署。

## 9. 可量化完成标准

只有以下结果全部满足，才可把 Core ThinkPHP 收敛标记为完成：

- Core 公共运行时 API 不再暴露 `PDO`；
- Application 不再绑定 `PDO::class`；
- Module 自有表都由对应 ThinkPHP Model/TenantScope 管理；
- 普通 ModuleProvider 基本只剩接口映射，闭包都有明确的配置/环境/SDK/Console/Worker 理由；
- 没有真实消费者的镜像接口、Repository 和 RuntimeFactory 已删除；
- HTTP、CLI、Worker、Cron、安装/迁移/种子共用正式 ThinkPHP bootstrap；
- 真实测试仍执行原有断言，未用 skip 或 `PASSED` 占位；
- ReferenceCodes 到 Identity/Tenant/RBAC 的每个微批次都通过 Tenant 隔离、事务、并发和两 Edition 证据；
- Core/Application 表 owner、Core 版本、Application lock、source/tree 和发布证据彼此一致。

## 10. 风险、停止线和回滚边界

### 风险

- 跨领域事务可能被错误拆分，造成部分提交；
- Model/Scope 缺失或 Scope 缓存错误可能造成跨 Tenant 读取；
- Core/Application 表 owner 混淆会产生双写、重复迁移或数据归属漂移；
- CLI/Worker 若没有同一 bootstrap，可能绕过 Module 生命周期、RBAC 或执行上下文；
- 过早删除 PDO API 会使 Alpha.13 下游无法启动，必须先固定版本和采用者矩阵；
- 外部 SDK、HTTP 和 Storage Driver 若被误删，会把真实可替换边界错误地收进 ORM 重构。

### 停止线

出现 Tenant 泄漏、权限绕过、事务/并发断言失败、两 Edition Schema 不一致、真实测试被跳过/占位、Core/Application owner 不明、或无法从正式 ThinkPHP bootstrap 启动时，停止该微批次。不得用兼容桥、静默 fallback、第二连接或弱化断言继续推进。

### 回滚边界

每个微批次可回滚到前一份完整 commit/tree；回滚不删除已应用生产迁移、不重写共享 Git 历史、不回滚用户数据。Schema 变化必须有新 migration 和已登记备份/恢复证据。公共 API 删除只在版本身份冻结、下游确认和兼容说明完成后发生。

### 为什么不进行大爆炸机械重构

当前 PDO 路径同时承载身份、Tenant、RBAC、幂等、Task、外部回调和两 Edition 差异。全库正则替换会把“语法已改”误当作“事务、租户、并发和启动边界已迁移”，并扩大无法定位的失败面。微批次可以让每个领域一次删除旧路径、保留真实断言、固定表 owner 和恢复点；它的代价是短期存在明确登记的过渡代码，但避免长期双实现的规则会在每个批次关闭该代价。

## 11. 与现有文档的关系

- 本 ADR 是 Application/Core ThinkPHP 收敛方向的当前权威解释。
- `docs/architecture/core-application-technical-boundary.md`、Core 的 `docs/guide/module-development.md` 等页面应引用本 ADR，并把当前 PDO 内容标为迁移前事实；不得继续把框架中立写成未来目标。
- PB、P1、资格和发布证据文档保持原有历史/计划身份，不因本 ADR 伪改为已经完成的 Runtime 迁移证据。
- 运行时代码、Composer 版本、lock、SQL、迁移和发布记录必须由后续获得明确授权的微批次分别修改；本页不能替代这些事实源。
