# 主流后台脚手架源码研究与第一性原理

## 研究方式与证据范围

本轮没有只读官网的目录截图，也没有把框架宣传语当作架构事实。研究过程固定了六个官方源码快照，
分别建立独立 CodeGraph 索引，再结合关键源码阅读追踪 Application 入口、请求链、业务层、数据层、权限、
Module/插件和非 HTTP Runtime。

| 参考项目 | 固定源码身份 | 主要取证路径 |
| --- | --- | --- |
| LikeAdmin PHP | [`79734cb1`](https://github.com/likeadmin-likeshop/likeadmin_php/tree/79734cb1cbf004ced91634ce0bb5f619a515aa3f) | `server/composer.json`、`server/app/adminapi`、`server/app/api` |
| LikeAdmin PHP SaaS | [`2257e5ed`](https://github.com/likeadmin-likeshop/likeadmin_php_saas/tree/2257e5ed5258ad6e70f4ae7b6517077a63a34e59) | `server/app/platformapi`、`tenantapi`、`api`、Tenant middleware |
| MineAdmin 3.2 | [`v3.2.0`](https://github.com/mineadmin/MineAdmin/tree/5cda1790a37e4586a7a3e61ba80153b2da547482) | `app/Http`、`Service`、`Repository`、DataScope AOP、Composer |
| BuildAdmin v2 | [`bdac949e`](https://github.com/build-admin/buildadmin/tree/bdac949e21c1ca9c06a46439445bbc59e6d4ab7b) | `app/admin`、`app/api`、`common/controller`、Module 管理、Composer |
| Hyperf | [`eee2d614`](https://github.com/hyperf/hyperf/tree/eee2d614e86ca98fd567bc2860397a2e5dbba497) | DI、Context、HTTP/WS、Queue、Process 组件 |
| Hyperf Skeleton | [`v3.2.0`](https://github.com/hyperf/hyperf-skeleton/tree/855dd7ef145d9e1e3d0500d958ac55d51cbf6ef0) | `app`、`config`、可选包安装器、Listener 示例 |

CodeGraph 用于找调用关系和重复边界，源码用于确认具体行为。Peanut Admin 自身事实已在
`origin/dev@0cc1b9dd3c4fd0ff12b30f0bdcc138bcee33268a` 复核；Composer 锁定
`peanut-admin/core@0.1.0-alpha.11`（source `fdd58c4873bea79759826ffe92aac52c5414d688`），但当前 worktree
没有该精确 Core 源码，因此 manifest schema 与 multi-app 采用能力仍属于实施前核验。此次没有运行参考项目、
没有做性能 benchmark，也没有评价其商业功能完整度；结论只针对源码组织和运行模型。

## 一眼对比

| 项目 | 用户第一眼看到的组织方式 | 请求主链 | 最值得借鉴 | 不应照搬 |
| --- | --- | --- | --- | --- |
| LikeAdmin | `adminapi` 与 `api` 是 ThinkPHP 多应用；每个应用再分 controller/lists/logic/validate | Application → Controller → Validate/Lists → 静态 Logic → Model | 入口受众分得直观，CRUD 开发门槛低 | 静态 Logic、全局状态、Admin/API 重复业务规则、直接 Model 操作 |
| LikeAdmin SaaS | `platformapi`、`tenantapi`、`api` 三套应用和各自 middleware | 域名/Tenant middleware → 登录 → 权限 → Controller/Logic | 平台、租户管理端、消费端是不同安全边界 | Tenant 从可变 Request 属性流转，身份与业务范围仍容易散落 |
| MineAdmin | `Http/Admin`、`Http/Api` 与全局 Service/Repository/Model | 路由注解 → 多个 middleware → Controller → DI Service → Repository → Model | DI、显式 Service/Repository、Admin/API 协议分区、异步与长期进程能力 | 每个简单 CRUD 都强制全套层级；AOP 数据权限过于隐式；运行依赖较重 |
| BuildAdmin | ThinkPHP `admin`、`api` 多应用；大量能力集中在 Backend 基类和 Trait | Application → 基类 initialize → Controller/Trait → Model | CRUD 生成效率高、应用边界直观、插件安装体验完整 | 大型基类魔法、Controller 直接事务/数据操作、静态单例 Module 管理 |
| Hyperf | Skeleton 很薄，能力由 Composer 组件与注解/配置组合 | Server → Middleware/DI → Handler；Queue/WS/Process 为独立入口 | 常驻进程 Context、DI、事件、队列、WS 和可观测扩展点 | 为当前问题整体迁移框架；协程状态、容器和部署复杂度会提高工资成本 |
| Peanut 当前（`0cc1b9d`） | 有 adminapi/api/platform/tenant/Modules，但由统一根路由和 AppService 串起 | 根路由 → 各类 middleware → 多种 Controller/Application/Module Provider | 已有 Context 生命周期、Module manifest、容器合同、服务登记 | Application 与 Module 的 HTTP owner 混合、actor/Context 过宽、Provider 手工定位、目录语义不稳定 |

## 三项只读推演的共同校准

业务边界、运行时与数据安全、升级扩展与交付治理三项独立只读推演都保留母稿主轴，并共同否决了“共享 Module 就
共享一套 CRUD/Context/DTO”和“先关闭根路由再迁业务域”两种解释。冻结增量是：

1. 同一业务/数据 owner 面向 Admin、Consumer、Platform、Provider、System 提供不同窄端口；
2. 每个顶层执行单元只有一个强类型 Context，Tenant ownership 与对象级 DataScope 分层；
3. provider 只向唯一 composition root 贡献启动期 binding；
4. 完整业务结果的 Module Command 持有最外事务，participant 只 join，强制 Audit 与业务原子；
5. Job 是意图，Attempt 在 claim 时创建；外部 HTTP 不进入数据库事务；
6. 迁移先核验框架/Core，再建 Application 地基并逐域硬切，最后关闭暂态根装载；
7. 本蓝图明确排除 AOP、Event Bus、Outbox、微服务、独立队列和空 WS/Repository/Domain 层；未来只能由新的
   架构决定显式取代本合同。

这些是目标设计校准，不是当前 Runtime 已完成的证明。

## LikeAdmin：为什么易上手，又为什么会遇到上限

LikeAdmin 的低心智成本来自两个简单决定：

1. 使用 `topthink/think-multi-app`，把管理后台放在 `adminapi`，把用户端放在 `api`；
2. 每个应用内部按开发动作分成 Controller、Validate、Lists、Logic，数据模型放 `common/model`。

例如管理端 Article 的 Controller 只做校验和返回，调用静态 `ArticleLogic::add()`；用户端同样有自己的
Article Controller/Logic。开发者很容易知道“管理接口去 adminapi 找，用户接口去 api 找”。这是 Peanut Admin
必须恢复的优点。

它的上限也来自同一做法：两个 Application 各有一份 Article Logic，静态方法直接调用 Model；随着事务、
幂等、Tenant 和外部 Provider 增加，依赖无法通过构造器看见，同一个业务规则也可能在两套 Logic 中漂移。

**第一性原理**：入口身份应分开，业务规则不应复制。Peanut Admin 采用前半句，不采用静态 Logic 和重复规则。

## LikeAdmin SaaS：战略 Application 不是普通 Module

SaaS 版没有把 Platform、Tenant 管理端和用户端塞进一个应用。源码中存在 `platformapi`、`tenantapi`、`api`，
三者拥有不同 Controller、middleware、service 和权限入口。`tenantapi` 登录中间件还会核对登录记录的 Tenant
和当前域名解析出的 Tenant。

这证明用户指出的问题是正确的：`adminapi/api` 的划分首先是安全和受众划分，不是业务领域划分。Article、
Payment 等 Module 会同时服务多个入口，而不能反过来拥有入口的最终权限链。

SaaS 版也提醒了一个风险：把 `tenantId` 放到通用 Request 属性上仍然容易被后续代码误用。Peanut Admin 应让
每个 Application 建立不可变、带 audience 的 Context；ORM scope 只消费它，不再让参数、域名解析和全局 scope
各自成为一套 Tenant 权威。

## MineAdmin：高性能能力来自运行模型，不来自多放目录

MineAdmin 的 Role 请求展示了清晰的依赖链：Controller 由容器注入 `RoleService`，Service 注入 Repository，
Repository 负责查询拼装，权限、操作日志和 Access Token 由 middleware 承担。它同时利用 Hyperf 的 AOP 在
查询执行处应用 DataScope，并原生具备 Queue、Crontab、Process 和长期运行 Server 能力。

值得借鉴的是：

- 依赖可见，业务服务不需要静态定位；
- 横切能力放 middleware/显式 runner，不复制到每个 Controller；
- 长期进程为每个请求/消息维护 Context，不使用进程级可变全局值。

不应照搬的是“每个 CRUD 都必须 Controller → Service → Repository → Model”以及全局 AOP 的隐式魔法。简单查询
没有复用、替换或复杂范围时，多一层 Repository 只增加文件；数据权限若完全隐藏在 AOP 中，开发者看代码时反而
不知道真实 SQL 范围。

**第一性原理**：高性能需要正确的生命周期、少量 IO 和可靠 Context；层数本身不会让系统更快。

## BuildAdmin：极快 CRUD 与大型基类的交换

BuildAdmin 同样登记 `topthink/think-multi-app`，并把 `admin`、`api` 分成独立 Application。它把认证、权限、
数据范围、查询组装和通用 CRUD 大量集中在 `Backend` 基类与 Trait 中，Controller 只需配置属性或覆盖少数方法。
Module 商店则由静态 `Server` 和单例式 `Manage` 执行下载、安装和生命周期管理。

这对小团队很有吸引力：重复 CRUD 代码少，开发速度快。但大型基类会让一次请求的真实行为分散在继承链、Trait
和属性约定中；静态 Module 管理又隐藏外部 HTTP、文件和状态依赖。

**第一性原理**：脚手架应生成无歧义的普通代码，而不是把复杂度藏进万能基类。Peanut Admin 可以保留 CRUD
生成器，但生成的 Controller/Use Case 应显式调用，安全链仍由 Application middleware 固定。

## Hyperf：WS 和常驻进程应该怎样纳入，而不是怎样换框架

Hyperf 把 HTTP、WebSocket、Queue、Crontab 和自定义 Process 都视为独立入口，通过容器、事件和协程 Context
共享业务服务。Skeleton 的可选包安装器也说明：能力按需加入，应用不需要预装所有组件。

Peanut Admin 当前没有必须整体迁移 Hyperf 的证据。迁移会同时改变服务器模型、ORM、容器、部署、测试和第三方
生态，工资和运维成本远高于目录收益。但应直接采用它背后的约束：

- WS/Worker 是独立 Host，不是 Controller 的特殊方法；
- 每条消息、每个 Job 都新建执行作用域，`finally` 清理 Context；
- 进程级单例不得保存当前用户、Tenant、事务或 Request；
- trace、身份和 Tenant 通过消息 envelope 显式传播；
- 只有出现经过验证的长连接/高并发需求，才增加对应 Host 和 Runtime 依赖。

## 从竞品与内部推演抽出的十二条第一性原理

1. **入口先分身份**：不同访问者使用不同 Application，避免一条 middleware 链同时兼容所有人。
2. **业务再分 owner**：相同业务规则只属于一个 Module，不因有多个 API 入口而复制。
3. **安全边界靠链路和类型，不靠自觉**：认证、Tenant、入口权限在 Application 固定组合；对象 DataScope 和不变量
   由 audience 专属 Module 端口继续收窄。
4. **依赖必须可见**：业务服务构造器能说明它依赖谁；运行时静态定位只会把成本推给排错者。
5. **目录数量按复杂度增长**：简单 Module 不强制 Domain/Repository/Event 全家桶，复杂规则出现后再增加。
6. **共享 owner 不等于共享入口**：多个受众可以复用规则、Repository 和表，但不能复用万能 Context、CRUD、DTO
   或结果映射。
7. **数据只有一个 owner**：跨 Module 读取走 Query，写入走 Command，不能为了方便直接共用 Model。
8. **一个进程一份依赖图和一个 root**：版本冲突在构建期拒绝；provider 只贡献 binding，不复制 vendor 或容器。
9. **事务跟随业务结果 owner**：Module Command 持有最外事务，participant 只 join，外部 HTTP 永不进入 DB 事务。
10. **异步事实晚于意图**：提交只建 Job，成功 claim 才建 Attempt；System/Provider/Consumer 都不是 Admin。
11. **横切机制不扩成框架**：本蓝图使用 middleware、container、显式 runner、ORM scope 和现有 Task，并明确排除
    AOP、Event Bus、Outbox 和独立队列；未来只能由新的架构决定显式取代本合同。
12. **性能靠减少重复工作**：Module registry 在启动/构建期编译，Context 每次入口建立，查询显式限界，不在每个请求扫描目录。

## 最终取舍

Peanut Admin 采用“LikeAdmin 的多应用直观性 + MineAdmin 的依赖可见性 + Hyperf 的入口生命周期纪律 + 当前
Peanut Module manifest/Context 生命周期”，不采用任何一个竞品的完整目录复制；本蓝图明确排除其 AOP、Event Bus、
微服务和完整 Repository 层，未来只能由新的架构决定显式取代本合同。

这不是折中拼盘，而是围绕四个成本做的选择：普通 CRUD 文件少、复杂业务能自然扩展、安全链不靠开发者记忆、
非 HTTP 场景出现时无需重写业务规则。
