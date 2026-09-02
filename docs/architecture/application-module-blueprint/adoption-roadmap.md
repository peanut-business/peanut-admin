# 现状差距与一次收敛路线

## 1. 路线状态与用词

本文是目标架构的实施合同，不是 Runtime 完成报告。事实基线为
`origin/dev@0cc1b9dd3c4fd0ff12b30f0bdcc138bcee33268a`，2026-09-01。

- **最终目标**：五份母稿冻结的 Application × Module 结构、安全、事务、升级和交付合同；
- **暂态迁移**：只描述尚未迁移的现行入口如何继续存活，不是兼容承诺或第二套目标；
- **实施前核验**：必须用框架、精确 Core identity 或源码证明，未通过前不得开始依赖它的 Runtime 修改；
- **完成证据**：固定 diff/commit/tree 与当前候选验证；计划、开放 PR、旧候选和文档本身都不是实现证据。

正式可消费源码、Tag、GitHub Release 和登记 Demo 仍是 `v3.0.12`。`v3.0.13` 只有 pending 元数据：未产生最终
candidate commit/tree、未通过 P0-E、未 Tag、未 Release、未部署，也未被 Demo/文档站采用。架构实施不能继承或
提前改写这两个状态。

## 2. 当前基础与必须退出项

### 2.1 可以校准后复用

| 现有能力 | 处理 | 边界 |
| --- | --- | --- |
| `adminapi`、`api`、`platform` 目录和战略受众 | 升格为真正 Application | 不复制 Module 业务规则 |
| `ExecutionContextStore` 的 scope/`finally` 机制 | 保留生命周期机制 | 退出 union Context、任意 attributes 和 actor 混用 |
| Module `module.json`、registry、lock、权限/菜单和迁移声明 | 保留事实源 | manifest 最终删除 `backend.routes`；migration 只改 `owned_tables` |
| Module `Application/Contracts/Model/Infrastructure` | 按需保留 | 不强制空 Domain/Repository/Event 层 |
| 容器 binding 与构造器注入 | 收口到唯一 composition root | provider 只贡献启动期 binding |
| Core Task Job/Attempt/lease/fencing、幂等、Audit、Outbound HTTP 基础 | 复用并校准 | 不把基础机制当成共享受众语义 |
| 服务登记、资源登记、文档治理和发布控制 | 保留 | 不替代 Module、Schema、Release 或 Runtime 事实源 |

应用已锁定 `peanut-admin/core@0.1.0-alpha.11`，source reference 为
`fdd58c4873bea79759826ffe92aac52c5414d688`。这只证明 Application 采用身份；当前 worktree 没有该精确 Core 源码，
不能据此宣称 Alpha.11 已支持删除 `backend.routes` 或目标 multi-app 装配。

### 2.2 最终必须退出

| 当前事实 | 目标动作 |
| --- | --- |
| `server/route/app.php` 统一 require Admin/API/Platform/Tenant/Module route | 每个 endpoint 纵向硬切；最后一个域完成后关闭根业务装载 |
| Official/fixture Module 含 `Http/Controller`、`Http/routes.php`、`Validation` | 入口移到消费 Application；Module 只留 owner 能力 |
| manifest `backend.routes` | 对应 Module 迁移时删除；最终 schema 与结构门禁拒绝 |
| 请求期 `new *ModuleProvider()`、业务 `app()`/Facade、Runtime factory | 唯一 root 启动期装配，业务构造器注入 |
| 根 `AppService` 同时绑定基础设施、Host 和 Module 业务 | 根只留真共享基础，各 owner provider 贡献 binding |
| `tenant` 像独立 Application 但只承担管理会话 | 合并进 `adminapi` 认证子域 |
| Consumer/Provider/Worker 借用 System 或 Admin actor | 分别使用强类型 Consumer/Provider/System Context |
| 通用 Context discriminator、union scope、任意 attributes | 每个顶层执行单元恰好一个 audience/Host 专属强类型 Context；tenant-scoped 端口恰好一个 current Tenant，认证、instance-public 与 Platform 明确 tenantless |
| Application 或其他 Module 直写 owner Model/表 | 只调受众明确的公开 Query/Command |
| 生成器继续产出 `Http/routes/Validation` | Article 样板后一次替换生成器、stub、作者检查和位置型断言 |

## 3. 唯一迁移规则

先建立 Application 装载地基，再按业务域纵向硬切。地基完成时，暂态根业务 loader 只承载**尚未迁移**的 endpoint；
它不是 fallback，也不得与新 Application 同时注册同一个 method/path。每个域的 route、Controller、validation、窄端口、
Context、授权、Tenant/DataScope、事务、审计和客户端/OpenAPI 在同一切片切换，旧入口同时退出。

只有最后一个域迁完后，才关闭根业务 route、独立 `tenant` 入口和剩余 Module HTTP。`integrationapi` 不在地基阶段预建；
它与首个真实 Payment/OAuth callback 同一切片创建。

## 4. 五个实施工作包

### WP0：合同与框架资格

**目标**：在改 Runtime 前证明目标能由 ThinkPHP/Core/现有发布合同承载。

**必须关闭的核验**：

1. `topthink/think-multi-app` 与 ThinkPHP 8、CLI、安装入口、现有 Module lifecycle 的唯一装载方式；
2. Alpha.11 精确 Core manifest schema 是否允许删除 `backend.routes`；若不允许，先发布最小 Core 合同变更，
   Application 只采用已发布固定 identity，不保留空字段兼容；
3. Application/Module provider 向唯一 composition root 贡献 binding 的框架 API 与生命周期；
4. `Db::transaction()`、注入 PDO 和跨 Module participant 是否能证明使用同一连接/事务 handle；
5. manifest `owned_tables` 对 migration touched tables 的可执行校验方式；
6. 现有 endpoint、客户端和 OpenAPI 的 method/path owner 清单，以及首批受众端口/DataScope 清单。

**依赖与并行**：无 Runtime 前置。multi-app/Core/事务核验必须串行冻结；route inventory、端口表和 migration inventory
可只读并行，但由一个架构 owner 收口。

**最低验证**：固定版本/commit 的框架或 Core 源码证据、最小无业务副作用装载 spike、CLI/installation 入口解析、
同一 transaction handle 的可证明结果，以及 manifest/migration fixture 的一正一负。不得连接未登记资源。

**停止线**：任一项不能证明时，只阻塞依赖该合同的 WP1/WP2；不自行开发第二 loader、第二 root、兼容字段或事务桥。

### WP1：Application 与 composition 地基

**目标**：让路径开始真实表达入口身份，但不提前关闭未迁移业务。

**范围**：

1. 采用 WP0 证明的 multi-app 装载；建立 `adminapi`、`api`、`platform` 和已有真实 `installation` 入口；
2. 唯一 composition root 接受 Application/Module provider 的启动期贡献；
3. 建立 Admin、Consumer、Platform、Installation 强类型 Context、稳定错误 classifier 与 Host renderer；
4. 固定每个 Application 的 route/provider/middleware 入口和安全顺序；
5. 将 `tenant` 会话并入 `adminapi`，但只在相关 endpoint 同次硬切后删除旧入口；
6. 建立“新 Application route + 暂态未迁移 root route”的唯一 endpoint inventory，禁止重复注册；
7. 不创建空 `integrationapi`、WS、Event Bus、Outbox、Repository 基类或事务 interceptor。

**最低验证**：每个真实 Application 一个正向 route、错误 audience token 负向、Admin 未选 Tenant 负向、Platform target
不变成当前 Tenant、Consumer 无法构造 Admin 端口、异常后 Context/事务为空；inventory 证明已迁 endpoint 只注册一次，
未迁 endpoint 仍可达。

**停止线**：出现单 endpoint 双路由、根 loader 被提前关闭、第二容器/Service Locator、union Context、Platform→Admin
或 Consumer/System→Admin 转换时停止 WP1 及直接下游。route inventory 和受众 DTO 设计仍可继续。

### WP2：Article 纵向样板与新生成合同

**目标**：用同时服务 Admin/Consumer 的 Article 证明“一个 owner、不同窄端口”。

**范围**：

1. Admin route/controller/validate 调 `ArticleAdministration`；
2. 匿名目录与会员收藏统一调用 `PublicArticleQueries`；
3. 三端口分别接收强类型 Context/actor、scope、输入/输出 DTO，不接收 `actorType` 或任意 filters；
4. Article owner 独占规则、Repository、`pa_article*` 表与事务；收藏显式声明 Member 依赖；
5. 同切片删除 Article `Http/Validation`、`backend.routes`、手工 Provider locator 和旧位置断言；
6. 样板通过后，一次替换 Module 生成器、stub、作者检查和新 Module 结构门禁，不生成旧目录或兼容开关；
7. 其余未迁移 Module 继续由显式暂态 inventory 承载，只减不增。

**最低验证**：Admin CRUD 权限 + Tenant ownership + 对象 DataScope；匿名目录固定 published/not-deleted 谓词；会员只能
修改自己的收藏；TenantModule disabled 拒绝；Context 类型错配不能调用；Article migration touched tables 是
`owned_tables` 子集；新生成 Module 不含 `Http/routes/Validation/backend.routes`。

**停止线**：Consumer 能传隐藏过滤、Application 直引 Article Model、同一规则有两份实现、收藏绕过 Member/Module
资格、旧 Article endpoint 仍可达或新生成器仍产旧结构时，阻塞后续 Module 模板采用。

### WP3：按 owner 的业务域纵向硬切

**目标**：复用 WP2 模板迁完全部业务与非 HTTP 入口，不把横切合同留到最后补。

**串并行关系**：

- `Member + OAuth`：先冻结业务 Member 与 `TenantMember` 类型；OAuth 只消费 Member/ExternalChannel 合同；
- `Task`：WP2 模板稳定后可与 Member 并行；Job 是意图，claim 时创建 Attempt，System 不冒充 Admin；
- `File + ImportExport`：共享对象交付边界，按 Storage owner 合同迁移；
- 其余简单管理域：文件 owner 不冲突时并行，但每域仍是完整纵向切片；
- `Payment + Notification`：Payment 依赖 Member/ExternalChannel 合同；首个真实 callback 在该切片创建
  `integrationapi`，Provider receipt、资金事务和 unknown-result recovery 必须串行收口；
- `plugins.lock`、Core lock、根 route inventory、共享 generator 和 catalog 始终只有一个 owner。

**每域必须同时完成**：Application route/controller/validate、受众窄端口/DTO、每个顶层单元恰好一个强类型 Context、
tenant-scoped 端口恰好一个 current Tenant、tenantless 端口显式声明、入口授权、对象 DataScope、owner
transaction/participant、幂等、强制 Audit、错误映射、旧入口/locator 删除、manifest/migration owner。

**最低验证**：每个 Module 一个正向、错误 audience、Tenant/owner 拒绝、Module disabled 拒绝；身份/余额/支付/退款
增加重复请求与原子回滚；Provider 增加验签/replay/binding/receipt/ACK；Task 增加 envelope 篡改、claim/Attempt、
lease/fencing/retry/dead 和异常清理；外部 HTTP 明确位于 DB 事务外。只运行受影响域的登记资源聚焦 Gate。

**停止线**：跨 owner 表写入、payload Tenant 覆盖 Context、participant commit、外部 HTTP 位于事务内、强制 Audit
不能与业务原子、Provider/System/Consumer 冒充 Admin、Context 跨 Attempt 泄漏或同 endpoint 双路由，阻塞该域及直接
下游；无依赖、文件 owner 不冲突的其他域继续。

### WP4：结构关闭、资格与消费

**目标**：证明全仓只剩目标结构，并按现有交付链生成新的可消费证据。

**结构关闭**：

1. 最后一个域迁完后删除根业务 route require、独立 `tenant` Application 和剩余 Module `Http/Validation`；
2. 所有 manifest 删除 `backend.routes`，所有 Module migration 只修改 `owned_tables`；
3. 删除请求期 Provider accessor、业务 Service Locator、第二 root、静态 Runtime factory、union Context/attributes；
4. 删除业务回流 `common`、跨 Module Model/Infrastructure import、宽泛万能 CRUD 和旧生成器路径；
5. 保留有真实消费者的 ExecutionContext 生命周期、Module guard、Tenant ownership、Audit、幂等、Task 和 Outbound
   HTTP 原语，不新增 AOP/Event Bus/Outbox/微服务/独立队列/空 WS/Repository/Domain 层。

**交付顺序**：聚焦验证全部通过 → 固定功能冻结 commit/tree → 从同一 tree 生成 inventory/scaffold/双 Edition 制品
与 fixture → Development mode 资格就绪 → 对固定候选运行一次 L2 P0-E 八组 → 零残留/租约释放 → 才能决定
Tag/Release → 独立消费 scaffold/Edition → 最后才是 Demo/文档站/生产采用。任一步失败只阻塞自身和直接下游，不能
继承 `v3.0.12` 或任何旧候选的资格。

**最低验证**：结构门禁、manifest/migration/generator 严格检查、受影响客户端/OpenAPI、Standalone/Multi-tenant
fresh 与同 Edition 升级、Plugin/Module lifecycle、consumer lifecycle、Compose 和两个 browser gate，最终按 P0-E
登记矩阵一次执行。阶段候选变化后旧 Gate 结果不冒充新候选。

**停止线**：任何旧入口、双路由、第二权威、跨 owner 写入或资格残留都阻塞完成声明。仅完成源码迁移不得写成
qualified、released、consumer-adopted 或 deployed。

## 5. 依赖图

```text
WP0 合同与框架资格
          │
          ▼
WP1 Application/composition 地基
          │
          ▼
WP2 Article 样板 + 新生成合同
          │
          ├──────────────┬──────────────┬──────────────┐
          ▼              ▼              ▼              ▼
 Member/OAuth          Task       File/ImportExport   简单管理域
          │              │              │              │
          ▼              │              │              │
 Payment/Notification    │              │              │
 + 首个 integrationapi   │              │              │
 callback                │              │              │
          └──────────────┴──────────────┴──────────────┘
                                 │
                                 ▼
        WP4 结构关闭 → 固定候选 → P0-E → Release → 消费/部署
```

阶段编号只表达依赖，不冻结无依赖工作。任何停止线都按安全、Schema、公共合同和文件 owner 计算最小阻塞闭包。

## 6. 交付边界

| 层 | 唯一 owner/产物 | 可以包含 | 禁止冒充 |
| --- | --- | --- | --- |
| Core | 独立 Core 仓的版本化通用合同 | 无产品业务语义的 Context/事务/Task/manifest 原语 | Application Runtime、官方 Module、未发布本地源码 |
| Application | 本仓唯一 composition root 与受众 Host | route、Context、授权、Host renderer、Module 采用 | Core 发布、Module 数据 owner、Release 资格 |
| Module | manifest/lock 标识的业务 owner | 窄端口、规则、Repository/Model、owned tables/migrations | 最终 HTTP、安全链、其他 owner 表 |
| Scaffold | 固定 source tree 生成的 managed 制品 | Application/Module 目标结构、Edition profile、升级 inventory | 独立业务真值、手工漂移、旧 Runtime fallback |
| 双 Edition | 同一 tenant-first source 的 Standalone/Multi-tenant 构建物 | 显式 Edition 配置与同 Edition 升级 | 两套业务实现、隐式互升、`null/0` Tenant |
| P0-E | 同一冻结 commit/tree/locks/scaffold 的八组资格 | 生成应用、双 Edition、lifecycle、Compose/browser、零残留 | 迭代调试、旧候选结果、Release 本身 |
| Release | qualified identity 的 Tag/附件/manifest | 不可变源码和消费身份 | 自动部署、Demo/生产采用 |
| 消费采用 | 独立应用、Demo、文档站或生产的固定采用证据 | 明确 source/Edition/升级与 smoke | 用计划、PR 或 Release 存在代替实际采用 |

## 7. 最终结构门禁

门禁至少拒绝：

- Module 下 `Http/Controller`、`Http/routes.php`、`Validation` 或 manifest `backend.routes`；
- Module migration touched table 不属于自身 `owned_tables`；
- 根路由 require 业务 route，或相同 method/path 在新旧入口重复注册；
- 业务代码 `new *ModuleProvider()`、`app()`/Facade、静态 Runtime factory 或第二 composition root；
- Application/Module 直接引用其他 owner 的 Model、表、Infrastructure 或内部 Application 类；
- `actorType` flag、union Context、任意 attributes、Provider/System/Consumer→Admin 转换；
- participant commit、Controller/middleware 拥有业务事务、事务内外部 HTTP；
- 写 Command 缺少同事务强制 Audit，或入口日志/诊断束被当作业务真值；
- 新增通用 AOP、Event Bus、Outbox、微服务、独立队列或空 WS/Repository/Domain 层；
- `common` 新增有明确业务 owner 的 Service，或生成器重新产出旧结构。

## 8. 实施前仍须回答

1. Alpha.11 精确 Core manifest schema、provider lifecycle 和删除 `backend.routes` 的采用顺序；
2. ThinkPHP multi-app 对当前 CLI、installation、异常 renderer 和 package lifecycle 的唯一装载行为；
3. 注入 transaction handle 与 ORM/PDO 实际连接的一致性；
4. 每个 Admin/Consumer operation 的对象级 DataScope；现有 Tenant scope 不能冒充已完成；
5. Provider receipt 的接收身份 owner 与业务结果 owner、各 Provider event-id/digest、ACK、过期 processing reconcile；
6. Task 长任务 lease renew/fencing、Crontab publish 原子性与稳定失败分类；
7. Article 隐藏分类谓词、collection 对 Member 的安装依赖；
8. 未来是否真实采用 Fiber/Swoole/RoadRunner/WS；若采用，Context store 先通过 request/Fiber-local 隔离门禁；
9. 目标架构的发布版本、破坏性 HTTP 切换窗口和下游迁移说明；在资格前不预填版本或 Release 状态。

这些未知不推翻目标，只决定对应实现切片何时可以开始或宣称完成。

## 9. 明确不做

- 不迁移 Hyperf/Swoole，不拆微服务；
- 不增加旧 URL、旧 Controller、旧 Service、旧 manifest 字段的兼容代理；
- 不建立双写、镜像、备用 Runtime 或隐式 fallback；
- 不把所有 Service 重命名，不为目录对称创建空层；
- 不顺手改变业务字段、表、产品流程、Core/Module release policy 或前端架构；
- 不为本设计冻结运行数据库、服务、Compose、浏览器或 P0-E。

## 10. 完成定义与文档影响

开发者打开任意入口时，应能在一分钟内回答 audience/协议、唯一强类型 Context、该端口是 tenantless 还是由哪个
current Tenant 权威约束、入口授权、对象 DataScope、owner Module/端口/DTO、事务/participant、幂等/Audit、错误映射
和最终清理。所有现有域都能回答且结构门禁拒绝旧写法，
才是 Runtime 架构统一完成；之后仍要分别证明 qualified、released、consumer-adopted 和 deployed。

本蓝图只冻结目标和实施边界，不修改当前 Runtime、公开 API、资源、能力账本、Release 或部署状态。后续每个代码
切片按 `docs/document-impact-map.json` 更新最小现行技术文档和公开投影；相应 Runtime 真正合入并验证前，公开站点
继续描述已实现行为，不把本蓝图投影成已交付功能。
