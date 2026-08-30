# Peanut Admin 发布后增强任务计划

> 状态：当前执行中；Phase 0、审计、文档、PE01—PE05、REL01 与 REL02 已完成，v3.0.13
> 双 Edition 候选待单一 PR 合入、唯一 L2 资格和发布采用
>
> 正式源码基线：`v3.0.12@fe328a320b7c68b3c2f47512f2aa4afcad43c630`
>
> 计划事实基线：`origin/dev@3193314e24d8204b65218a2c6de5d162b32de82c`；`e5ef575…` 与
> `82fd612…` 的 PE05 复验依次发现共享 Vue 构建标志、Platform favicon 和历史 `2.0.1` Runtime
> 版本回退，两个候选均已失效并返回 Development mode。产品 Runtime 候选 `836d8a9…` 已完成
> DA01—DA08 与第二 persona 聚焦复验；资格工具提交 `d6784ff…` 已改为直接消费两个正式安装包。
> 功能冻结提交 `818c337…` 的双安装包确定性构建、升级/Demo/资格合同与文档构建均已通过；
> 最终 scaffold 已从该功能冻结提交重封，P0-E fixture 已同步到同一 manifest、inventory 和
> managed tree 身份；当前尚未把本分支或 scaffold 冒充已合入、已资格或已发布
>
> 决策日期：2026-08-30
>
> 完成事实仍以 `docs/product-status/capability-ledger.json` 为准

## 1. 用业务语言说明当前状态

Peanut Admin 已经有一个经过完整资格验证、不会再变化的正式源码版本 `v3.0.12`。这个版本能
创建新应用、完成空库安装、启动、登录并部署 Demo，开发文档站也已经上线。CR01—CR40 和
PC00—PC70 保持完成，本计划不重做这些工作。

但“正式源码已经发布”还不等于“普通开发者能方便地取得、安装和升级产品”。当前仍有两个
产品体验缺口：

1. **Demo 的已知可见问题已在发布候选修复。** Platform、共享 Admin 两个 persona、Tenant A/B
   的 DA01—DA08 聚焦路径已通过；正式 Demo 仍要等同一 Release 包发布后重新部署。
2. **产品有正式源码，但没有清楚的双 Edition 交付入口。** 同一套开发源码可以运行于
   Standalone 和 Multi-tenant 两种模式，但目前还没有从同一冻结 Release 生成的两套正式安装包、
   对应升级包和跨受支持版本升级说明。用户不应从另一个人工维护的“应用源码仓库”取得产品。

因此当前准确结论是：Peanut Admin 已完成 consumer-ready 正式源码交付；双 Edition 安装包和
Demo 可见质量已在候选实现并通过聚焦复验，但正式附件、资格、Demo/文档采用尚未完成。
跨版本升级体验必须等 v3.0.13 成为首个合格来源后由下一补丁完成，不能伪造旧来源。

## 2. Phase 0：ThinkPHP 架构质量全局前置队列

本阶段已经由 PR #380 合入 `dev`，是 PE01 以后所有产品逻辑、双 Edition、Schema、构建物和
升级工作的共同前置。它不是“挑一个 Module 做示例”的局部重构，而是一次全仓横切边界替换；
后续代码不得重新引入手写 Tenant 谓词、逐 Repository Module 门禁、手工分页或新的静态 Logic。

### 2.1 已冻结的最终方向

1. **全局 Tenant Scope**：全部 Tenant-owned ORM 模型继承唯一 `TenantOwnedModel`，由 ThinkORM
   global scope 自动应用 `DataScopePolicy`。普通业务代码不得调用命名 `forTenant()`，也不得手写
   `where('tenant_id', ...)`。Multi-tenant policy 注入 Tenant 条件；Standalone policy 不产生该
   条件，也不写入 Standalone Schema 不存在的 Tenant 字段。ThinkORM 内部用于注册 global scope
   的 scope method 名称只是框架元数据，不是供业务代码调用的 named scope；应用调用方始终自动
   生效，不能选择“记得调用”或“忘记调用”。
2. **可信执行上下文**：认证、Host、回调签名、Worker 或 Scheduler 在入口建立不可变
   `ExecutionContext`；模型 Scope、权限、Module 和审计只消费该上下文。缺少可信上下文时
   Tenant-owned ORM fail-closed，禁止回退默认 Tenant。
3. **统一 Module 执行边界**：HTTP、public/member、callback、worker 和 scheduled 入口统一通过
   `ModuleExecutionBoundary` 检查部署、Tenant 开通和 operation。Repository 不再连接数据库重复
   检查 Module；跨 Module 调用只使用已授权 application contract。当前
   `ArticleTenantRepository::articles/categories/collections/create*` 六个入口每次调用
   `assertAvailable()` 并重新取得 PDO 的做法全部退出；不是把这六次检查搬到六个 Model。
4. **受控跨 Tenant 查询**：Platform 只能通过专用 `PlatformTenantDataGateway` 关闭 Tenant global
   scope；普通 Controller、Logic、Model 和 Repository 禁止直接 `withoutGlobalScope()`。
5. **统一分页输出**：列表统一使用 `PageRequest -> ThinkORM Paginator/PageResult`。路由组响应
   middleware 或唯一 response transformer 把结果转换为既有
   `code/msg/data.{lists,count,pageNo,pageSize}`，不要求每个 Logic 重组数组。
6. **非 ORM 显式边界**：Db/PDO、安装、迁移、修复、Platform、callback 定位和批处理不能冒充已被
   Model Scope 保护；它们统一经过 `TenantQuery`/专用 gateway，或进入有 owner 和理由的 allowlist。

### 2.2 实施前完整静态基线（历史）

基线为 `origin/dev@b51610b49632f2a3a38357c73bebb9186dea43f7`，只读扫描未连接数据库或
运行服务。下列数量是问题队列的历史起点，不再代表现行实现；最终关闭结果见 2.5。

| 清单 ID | 当前事实 | 可复现范围 |
| --- | --- | --- |
| INV01 | 35 个具体 ORM Model：29 个映射 Tenant-owned 表，6 个映射 Platform/Instance/共享表；global/named Tenant Scope 为 0 | `extends BaseModel/Model` 与最终 Schema 的 `tenant_id` 交叉盘点 |
| INV02 | 47 张应用 Tenant 表；其中 18 张没有 ORM Model，主要由 Db/PDO 访问 | `init.sql`、应用 migration、Module migration 的建表/加列语句 |
| INV03 | 15 个名称带 Tenant 的 Repository；Tenant 过滤、可信上下文、Module 门禁和 create 注入职责混在一起 | `rg 'class\s+\w*Tenant\w*Repository' server/app` |
| INV04 | 生产代码 `paginate()` 为 0；`page()` 27 处、分布 21 个文件，分页键与 clamp 规则不一致 | `rg -- '->page\(|->paginate\(' server/app` |
| INV05 | 14 个 SoftDelete Model；21 个 accessor/mutator、分布 9 个文件；没有应用 Model event/hook | Model concern、`get*Attr/set*Attr`、event/hook 盘点 |
| INV06 | 手写 `startTrans/commit/rollback` 24 处、分布 11 个文件 | `rg 'startTrans\(' server/app` |
| INV07 | Db facade 出现在 78 个文件，PDO/query/prepare 出现在 119 个文件，约 44 个文件混用两种访问层 | Db 与 PDO 两套规则交集 |
| INV08 | Module 门禁相关命中 22 处、13 个文件；通用 `OfficialModuleMiddleware` 已存在，但 Article、OAuth/public 和部分业务仍有特例；Article Repository 的 6 个查询/创建入口逐次重复可用性检查 | `assertAvailable`、`executionGuard`、`assertTenant` 清单 |
| INV09 | 78 个 Controller；约 323 个 Logic/Service/Application 文件，静态 Logic 与 Module Application Service 并存 | controller/logic/service/Application 目录清单 |
| INV10 | `JsonService::fail` 88 处、32 个文件；`catch` 398 处，异常到 API code 的映射分散 | `JsonService::fail` 与 `catch (` 清单 |
| INV11 | 主路由文件 488 行，route group 只在局部使用；未使用 route model binding/name，middleware alias/priority 未形成应用合同 | `server/route/app.php`、Module routes、`config/middleware.php` |
| INV12 | 两个确定列表 N+1、一个模型 accessor N+1、一个生成器 N+1；文章点击为读改写而非原子 increment | Article info center、RefundLog handler、Generator snapshot、Article detail |
| INV13 | 生产 Model 关系只有 `Article::cate()` 与 `GeneratorTable::columns()` 两条；生成器仍会扩散裸 `with()`/查询模板，关系 owner 与 Edition 规则未冻结 | `belongsTo/hasOne/hasMany/with/withLimit` 和生成器模板清单 |
| INV14 | 验证调用约 97 处，至少 3 条路径直接实例化 Tenant-aware Validate；应用 Model event/hook 为 0，写入归属仍由各 Repository 手工注入 | validation scene、`new *Validate`、Model event/hook 清单 |
| INV15 | `AppService` 注册/启动为空，provider 只含最低绑定；请求相关 RuntimeFactory 使用静态缓存，Controller/Application 内仍直接 `new Pdo*` | provider/container、静态 factory、`new Pdo` 清单 |
| INV16 | cache/session/log 尚无统一 Tenant namespace；Provider 同时存在裸 curl 与 Guzzle；操作审计只覆盖部分 Admin 写路由 | cache/session/log 配置、HTTP client、audit 调用清单 |

### 2.3 ThinkPHP/ThinkORM 能力覆盖索引（历史问题映射）

下表记录本轮已经逐项关闭的能力全集。每一行均已落到明确 TPQ、逐路径清单和验收结论；后续
扫描发现新问题时仍须新增唯一问题 ID，禁止把新发现藏进“继续系统性检查”或既有任务备注。

| 能力面 | 当前已确认的重复/风险 | 问题队列 |
| --- | --- | --- |
| Model 所有权与基类 | Tenant、Platform、Instance、Shared Model 仍共用宽泛 BaseModel，不能从类型判断隔离策略 | TPQ01、TPQ04 |
| ThinkORM global scope | Tenant 查询依赖调用方手写谓词，普通 Model 查询可绕过；业务代码不得改成 named `forTenant()` | TPQ02—TPQ07 |
| 写入归属、字段白名单与批量写 | create 手工注入 tenant_id；payload 覆盖、save、批量 update/delete/insert 的边界不统一 | TPQ05、TPQ13 |
| 关联、预加载与聚合 | 关系定义稀少且 owner 规则不完整；存在循环查询、raw join 和 accessor 发 SQL | TPQ06、TPQ11、TPQ33、TPQ38 |
| SoftDelete、restore 与 scope 组合 | 14 个 SoftDelete Model 尚未验证 global scope、relation、批量删除和恢复顺序 | TPQ37 |
| cast、JSON、accessor/mutator 与序列化 | 21 个 accessor/mutator 混合数据转换、容错和数据库读取，DTO/Model 职责未分开 | TPQ36、TPQ46 |
| Model event/write hook | 应用 hook 为 0，创建归属和审计依赖散落调用方；同时 query-level 批量写可能绕过 Model event | TPQ05、TPQ13、TPQ37 |
| Query Builder、Db、PDO 与领域 Gateway | 78 个文件用 Db、119 个文件用 PDO/query/prepare，约 44 个文件混用，事务 owner 不明确 | TPQ07—TPQ10、TPQ35、TPQ39 |
| 原子更新、批处理、锁与 N+1 | 已确认 4 条 N+1/线性查询和文章点击丢更新风险；其余循环内 SQL、读改写与锁顺序尚需逐路径登记 | TPQ33、TPQ34、TPQ38、TPQ39 |
| 事务 | 24 处手工 start/commit/rollback；闭包事务、嵌套事务、Db/PDO 同连接和锁失败语义未统一 | TPQ35 |
| Paginator 与列表响应 | 27 个 page() 调用、paginate() 为 0；分页解析、上限、metadata 和 envelope 在各 Logic 重复 | TPQ30—TPQ32 |
| Validator/scene | 97 处调用存在 BaseController helper 与直接 new Validate 两种入口，Validate 内可能裸查 Tenant Model | TPQ40 |
| Request/Execution Context | Tenant/admin/member 等信息通过 Request 动态属性和静态 factory 传递，长驻生命周期不明确 | TPQ02、TPQ25 |
| Route group、middleware alias/priority、model binding | 身份分组与中间件顺序未冻结；Tenant model binding 在 scope 前会产生枚举风险 | TPQ21、TPQ43 |
| Provider、Container 与依赖注入 | composition root 基本为空，Controller/Service 手工 new PDO adapter，request state 可能静态复用 | TPQ24 |
| Module/权限执行边界 | Article `collections()` 等 Repository 方法逐次查 Module；HTTP/public/callback/worker/scheduled 使用多套 guard | TPQ20—TPQ26 |
| 异常与响应渲染 | 88 个 JsonService::fail 和 398 个 catch 分散映射错误，正常未配置也可能表现为 500 | TPQ41、TPQ46 |
| Controller/Logic/Application 分层 | 78 个 Controller 与约 323 个 Logic/Service/Application 并存，静态 Logic 和新 Application contract 并行 | TPQ42 |
| Command、Scheduler、Worker、Callback、event/listener | 非 HTTP 上下文和 Module guard 入口不统一；event listener 当前为空，是否需要事件必须由真实消费者决定 | TPQ22、TPQ26、TPQ47 |
| Cache、Session、Log 与 Audit | namespace、request/operation trace、secret redaction 和跨 Tenant 清理合同未统一 | TPQ44 |
| HTTP client、文件系统与 Provider adapter | curl/Guzzle 并行，timeout/retry/trace/redaction 不一致；Storage 失败语义已在 Demo 暴露 | TPQ45 |
| 生成器/脚手架 | 生成器继续产出裸 BaseModel、手写分页、静态 Logic 和未声明 owner 的关系 | TPQ12、TPQ48 |
| 架构扫描与 allowlist | 当前只有一次性 rg 数量；没有逐项 owner、理由、到期条件和归零结论 | TPQ00、TPQ50—TPQ53 |

### 2.4 可逐项关闭的问题队列

#### A. Tenant、Edition 与 ORM 根边界

| ID | 问题与最终交付 | 状态 | 执行 owner | 最低验收 |
| --- | --- | --- | --- | --- |
| TPQ00 | 建立版本化问题登记：每个扫描命中记录 `issue_id/category/path/symbol/owner/decision/status/verification`；allowlist 另含理由、风险和到期/复核条件 | 已完成 | Terra medium 生成只读清单；Sol 审批 | INV01—INV16 每个命中可追溯到 TPQ 或 allowlist；不存在“其他类似问题”未登记桶 |
| TPQ01 | 冻结 35 个 Model 与 47 张 Tenant 表的 `tenant/platform/instance/shared/tenant-derived` 所有权登记；禁止以目录或命名猜 owner | 已完成 | Terra medium 盘点；Sol 决策 | 每个 Model/表恰有一个 owner、scope policy 和访问入口；18 张无 Model 表全部有显式边界 |
| TPQ02 | 建立 request/console/callback/worker/scheduled 共用的不可变 `ExecutionContext` 生命周期，替换 Request 动态属性和静态 Runtime 缓存中的 Tenant 状态 | 已完成 | Sol high | 三种 HTTP 身份和三种非 HTTP 执行形态均无上下文串用；缺上下文 fail-closed |
| TPQ03 | 冻结 `MultiTenantDataScopePolicy` 与 `StandaloneDataScopePolicy`；Edition 只在 policy/composition root 选择，业务代码没有 edition `if` | 已完成 | Sol high | 同一业务调用在 Multi-tenant 生成一个 Tenant 谓词，在 Standalone 生成零个 Tenant 谓词 |
| TPQ04 | 实现全局 `TenantOwnedModel`/global scope，并迁移 29 个 Tenant ORM Model；6 个非 Tenant Model 明确不继承 | 已完成 | Sol 建合同；Luna max 按清单迁移 | 任意 Tenant Model 的 select/find/update/delete 自动带 scope；普通代码无法漏调命名 scope |
| TPQ05 | 用 Model write hook/受控 persistence hook 自动写入 Tenant，拒绝请求 payload 覆盖；bulk update/delete 也受 global scope | 已完成 | Sol high | create/save/bulk update/delete 四类写入均不能跨 Tenant；Standalone 不写不存在字段 |
| TPQ06 | 统一 belongsTo/hasMany/eager loading 的 Tenant 规则，删除依赖 `$this->tenant_id` 的关系谓词；验证 global scope 在 alias/relation/with/withLimit 下的真实 SQL | 已完成 | Sol high | 多父记录 eager load 不串 Tenant；relation 查询没有重复或歧义 tenant_id |
| TPQ07 | 建立 `PlatformTenantDataGateway` 和唯一可审计 scope bypass；清点 Platform、安装、迁移、bootstrap、修复和系统查询 | 已完成 | Sol high | `withoutGlobalScope` 只出现在 allowlist gateway；每次跨 Tenant 查询有 actor/operation/audit |
| TPQ08 | 为 18 张无 Model Tenant 表和 Db/PDO 路径建立 `TenantQuery`/领域 gateway；禁止普通业务直接 Db/PDO 查询 Tenant 表 | 已完成 | Sol 定边界；Luna max 机械迁移 | INV02 的 18 张表均有 owner；Db/PDO 混用归零或进入事务 owner allowlist |
| TPQ09 | 拆分 15 个 TenantRepository：保留领域 persistence/transaction，删除手写 Tenant 谓词、create 注入和 Module 门禁 | 已完成 | Sol 处理 Finance/OAuth/Task/File；Luna max 处理低风险 CRUD | Repository 不再重复 global scope 或 Module guard；直接 Model 绕过清单归零 |
| TPQ10 | 修复 JobsValidate、OfficialAccountReplyApplicationService、Dictionary Provider、支付 callback 等直接绕过点 | 已完成 | Sol high；低风险验证迁移可交 Luna max | 双 Tenant 同名岗位合法；跨 Tenant ID 不可枚举；callback 仍从可信绑定恢复上下文 |
| TPQ11 | 逐关系核对复合 FK、全局 ID 和查询谓词，删除数据库已蕴含的重复 JOIN/WHERE；`ArticleCollectionSummaryService` 在全局 Scope 与现有复合 FK 生效后只按 `article_id` 关联，不再重复比较收藏/文章 tenant_id；先补文件、支付、OAuth、通知等关系缺口 | 已完成 | Sol high | 每个被删谓词有 FK/唯一键证据；Article 收藏汇总只有根 Scope 一个 Tenant 条件且结果不变；缺口先迁移、后删查询条件，不按文本批量删除 |
| TPQ12 | 改造生成器：所有权必填，按 owner 生成 Tenant/Platform/Instance Model、global scope、Application Service、分页和验证；拒绝裸 BaseModel 模板 | 已完成 | Sol 合同；Luna max 模板实现 | 新生成 Tenant CRUD 没有裸 Model 查询、手写 tenant_id、静态 Logic 或手工分页 |
| TPQ13 | 冻结 Model/Query 写入合同：字段白名单、readonly、mass assignment、create/save/saveAll/insertAll/update/delete 的 Tenant 注入、事件覆盖和返回语义 | 已完成 | Sol high | 请求 tenant_id 永不生效；所有写 API 要么自动受 policy 保护，要么被静态禁止并只能走受控 persistence gateway |

#### B. Module、权限与统一执行边界

| ID | 问题与最终交付 | 状态 | 执行 owner | 最低验收 |
| --- | --- | --- | --- | --- |
| TPQ20 | 把现有 `OfficialModuleMiddleware`、`ModuleExecutionGuard` 和各特例收敛为唯一 `ModuleExecutionBoundary`；明确删除 `ArticleTenantRepository::assertAvailable()` 及六个逐查询调用 | 已完成 | Sol high | installed/disabled/failure 保持稳定 40300/50300 envelope；同一次入口只查一次 Module；Article Repository 不再取得 PDO 或检查 Module |
| TPQ21 | Admin、Platform、member、public 路由按身份边界分组，固定认证→Host/Tenant→Module→RBAC→audit 顺序；删除 Article 专用 middleware | 已完成 | Sol 定顺序；Luna max 路由迁移 | URI、method、permission 和响应不变；middleware 顺序有机器检查 |
| TPQ22 | public Article/OAuth、支付回调、external resolver、Worker、Scheduler 采用同一 boundary adapter，不在 Controller/Logic 手写 executionGuard | 已完成 | Sol high | 无 Tenant、错 Tenant、disabled、重放和合法路径矩阵通过 |
| TPQ23 | Repository 只接收已授权 `ExecutionContext`，Module 可用性不再由每个查询方法重复检查 | 已完成 | Sol high | `assertAvailable()` 类方法归零；内部/cross-Module 调用不能绕过 boundary |
| TPQ24 | 使用 ThinkPHP Provider/Container 作为 composition root，替换空 `AppService`、控制器 `new Pdo*` 和请求相关静态 factory 缓存 | 已完成 | Sol high | Controller 注入 application contract；长驻进程不复用上次请求的 Tenant/PDO 状态 |
| TPQ25 | 用不可变 Request/Actor/Edition/Module 子上下文替换 Request 动态属性，规定建立、只读消费、finally 清理和禁止序列化 secret 的生命周期 | 已完成 | Sol high | admin/member/platform/public 四种请求不会互相残留 actor/Tenant；Scope 只能读取已验证上下文 |
| TPQ26 | 为 command/callback/worker/scheduled 建立同构 context factory 与 boundary adapter；逐项决定 event/listener 是否有真实消费者，不为形式引入事件总线 | 已完成 | Sol high | 每个非 HTTP 入口都有 actor、Tenant/instance、module、operation 和清理点；无来源上下文 fail-closed |

#### C. 分页、查询效率与 ORM 高级能力

| ID | 问题与最终交付 | 状态 | 执行 owner | 最低验收 |
| --- | --- | --- | --- | --- |
| TPQ30 | 冻结唯一 `PageRequest`、`PageResult` 与最大页大小规则；清理 `PaginationInput`、`ExportPageInfo` 和各 Logic 自行 clamp 的重复 | 已完成 | Sol 合同；Luna max 迁移 | 请求字段、空页、上限和导出语义只有一个事实源 |
| TPQ31 | 实现 route-group `PaginationResponseMiddleware` 或唯一 response transformer，把 Paginator/PageResult 转为既有 `dataLists` envelope | 已完成 | Sol high | 外部结构精确保持 `code/msg/data.{lists,count,pageNo,pageSize}`；Controller 不再拆数组 |
| TPQ32 | 迁移 INV04 的 27 个 `page()` 调用到 ThinkORM `paginate()`/PageResult；需要扩展统计的列表使用 PageResult metadata | 已完成 | Luna max 分模块迁移；Sol 处理 Finance/Platform | `paginate()` 覆盖全部常规列表；手工 count/page/select 归零或进入有理由 allowlist |
| TPQ33 | 消除全部已知 N+1：Article 分类文章、Generator 表字段、RefundLog handler；再用 SQL query counter 扫描所有列表/循环，新增发现进入本队列 | 已完成 | Luna max 处理 Article；Sol 处理锁与 Finance | N 条数据的 SQL 数为常数级；生成器锁语义和退款审计显示不变 |
| TPQ34 | Article 点击改为带 Tenant/可见性条件的原子 increment；盘点其余读改写计数器 | 已完成 | Luna max 实现；Sol 审查 | 并发请求不丢计数，跨 Tenant/下架文章不更新 |
| TPQ35 | 把 24 处手写事务迁移为 `Db::transaction()` 或明确 transaction owner；核对 Db/PDO 是否同连接，保留锁顺序 | 已完成 | Sol 处理 Finance/Task/Schema；Luna max 处理普通 CRUD | 异常自动回滚；嵌套、锁和返回错误语义与现状一致 |
| TPQ36 | 建立字段 cast/JSON 规则，盘点 21 个 accessor/mutator；纯格式化进入 DTO/presenter，模型 accessor 不发 SQL | 已完成 | Terra 分类；Luna max 机械迁移 | RefundLog handler N+1 消失；无效 JSON 的既有业务决定被显式保留或一次性替换 |
| TPQ37 | 核对 14 个 SoftDelete Model 与 global scope、relation、restore、bulk delete 的组合；冻结 Model event/hook 顺序 | 已完成 | Sol high | Tenant scope 和 soft-delete scope 均不可被普通路径绕过；恢复不跨 Tenant |
| TPQ38 | 逐个登记 relation/with/withLimit/accessor 内查询和循环内查询，选择 eager load、批量映射、withCount/聚合或窗口查询；禁止 accessor 发 SQL | 已完成 | Terra medium 清单；Luna max 迁移低风险读链 | 每个列表查询的 SQL 数量与结果行数无关；关系 owner 与排序/每父项 limit 语义保持 |
| TPQ39 | 逐个登记 Query Builder 的 join/subquery/aggregate/increment/decrement/lock/batch write 用法，替换 PHP 读改写和可合并的重复 round trip | 已完成 | Sol 处理锁/Finance；Luna max 处理无事务计数 | 每个替换有 SQL 与并发语义证据；未改变锁顺序、精度、幂等或错误 envelope |

#### D. HTTP、验证、异常与应用分层

| ID | 问题与最终交付 | 状态 | 执行 owner | 最低验收 |
| --- | --- | --- | --- | --- |
| TPQ40 | 统一 97 处验证调用和 3 个手工实例化 Validate 路径为 tenant-aware `ValidatedInput`；修复 JobsValidate 跨 Tenant 查询 | 已完成 | Sol 定上下文；Luna max 迁移 | scene、字段白名单和 40000 失败结构保持；Validate 不直接裸查 Tenant Model |
| TPQ41 | 建立领域异常→HTTP/API 错误的统一 renderer，替换 88 个分散 `JsonService::fail` 和重复 catch 映射 | 已完成 | Sol high | 认证、验证、Module、权限、业务冲突和系统错误各有稳定 code/status；异常可观察且不泄密 |
| TPQ42 | 逐 Module 收敛 `Controller → Application Service → Repository/Adapter`，退出并行的静态 Logic/Service/Application 三套层次 | 已完成 | Sol 定模块顺序；Luna max 迁移简单 CRUD | 78 个 Controller 不直接组装 PDO/Model；跨 Module 只走 contract |
| TPQ43 | 建立 route name/binding 和 middleware alias/priority 合同；Tenant 实体只有在 global scope 已生效后才能使用 model binding | 已完成 | Sol 定安全顺序；Luna max 机械路由 | 主路由职责缩小；URI 与权限 key 不变；binding 不可枚举跨 Tenant ID |
| TPQ44 | 统一 cache/session/log namespace、生命周期、request/operation trace、secret redaction 和 audit adapter | 已完成 | Sol high | Tenant cache/session 不串空间；日志不含 secret，管理/公开/任务关键写入均可追踪 |
| TPQ45 | 统一 HTTP transport、timeout/retry/trace/redaction、文件系统与 Storage Provider adapter；退出业务代码裸 curl 和随意 new client | 已完成 | Sol 定合同；Luna max 迁移无资金 Provider | Provider 签名和副作用不变；未配置/不可达/超时有稳定错误且不以未知 500 表达正常状态 |
| TPQ46 | 统一 DTO/resource/serializer 与成功/失败 response transformer；Model 不直接承担跨端展示结构，分页和异常共用同一渲染边界 | 已完成 | Sol high | 既有 API 字段和 code/status 精确保持；Controller/Model 不重复拼 envelope 或隐式发查询 |
| TPQ47 | 清点 command/scheduler/worker/callback/event/listener 的注册、重试、幂等和清理责任；只为现有真实消费者使用框架事件/监听器 | 已完成 | Sol high | 所有实际非 HTTP 入口进入 TPQ26 boundary；空 event 配置不被形式化扩张，已有重试/幂等不变 |
| TPQ48 | 在 TPQ12 的 CRUD 模板之外，校准 Module scaffold、示例和开发指南生成入口，并新增 owner/Edition/关系/分页/验证选项的拒绝式校验 | 已完成 | Sol 合同；Luna max 机械模板 | 所有生成入口不重新引入 TPQ01—TPQ47 已退出模式；未声明 owner 的业务表拒绝生成 |

#### E. 门禁、完成定义与模型路由

| ID | 问题与最终交付 | 状态 | 执行 owner | 最低验收 |
| --- | --- | --- | --- | --- |
| TPQ50 | 新增静态架构门禁：Model owner、global scope、禁止手写 tenant_id、禁止直接 bypass、禁止 Logic/Controller 裸 Db/PDO、禁止手工分页、accessor SQL 与特例 Module guard | 已完成 | Luna max 实现；Sol 审批 allowlist | TPQ00 登记的同一规则扫描命中归零或都有 owner/理由/到期条件 |
| TPQ51 | 建立一次开发态聚焦矩阵：SQL query count、global scope SQL、alias/relation、create/bulk write、Module boundary、pagination envelope、异常 renderer | 已完成 | Sol high | 每项真实命中声明分支；不以静态字符串检查替代 Tenant/事务行为 |
| TPQ52 | 使用登记资源完成双 Tenant 对抗和 Standalone 无 Tenant SQL/字段验证；长驻 Worker 验证 context 清理 | 已完成 | Sol high；执行前读取登记并 claim | 两 Tenant CRUD/关联/分页/回调不串数据；Standalone SQL 无 tenant_id；资源零残留 |
| TPQ53 | 全部队列关闭后更新本计划、能力账本（仅稳定能力变化）、开发文档和生成器指南，再恢复 PE01 与双 Edition/发布升级工作 | 已完成 | Sol 主代理 | 本表中 TPQ00—TPQ52 的全部已登记任务关闭；文档、源码、生成器和实际 SQL 事实一致 |

### 2.5 最终关闭证据

- PR #380 已合入 `dev@fc6fcb803240effdb584c0442dcfdb5650d3e913`；本阶段不再是开放 PR 或
  未提交 worktree 状态。
- `tpq-data-ownership.json` 固定 35 个具体 Model（29 个 Tenant、6 个非 Tenant）和 47 张 Tenant
  表；18 张无 ORM Model 的表全部登记到显式 Tenant gateway，不再被误写成“缺少 Model”。
- `tpq-issue-register.json` 的 637 条历史精确命中全部关闭。现行严格扫描只保留 17 条已审阅
  allowlist，均有理由、风险 owner 和 `2027-02-28` 复核日期；未登记与未解决数量均为 0。
- TPQ51 真实 ThinkORM 行为矩阵通过；TPQ52 Multi-tenant 双租户对抗与 Standalone 无 Tenant
  Schema/SQL 均通过。Standalone 结果为 0 个 Tenant 列、0 个 Tenant 索引，CRUD、关联和分页通过。
- TPQ52 使用登记的 development MySQL 8.4.10 资源并持有唯一租约；精确测试数据库、临时环境文件
  和租约均已清理，未触碰持久开发库或生产数据。
- 完整 P0-E 没有在本阶段重复运行。它仍只属于后续唯一冻结的 L2 双 Edition 发布候选，不能用
  TPQ51/TPQ52 的开发态证据冒充正式发布资格。

### 2.6 队列关闭规则

- 任何任务只能用“完整清单归零/allowlist + 最低行为验收”关闭，不能用代表 Module 或单个 PR
  冒充全局完成。
- 每个发现必须在 TPQ00 登记中有唯一记录；扫描产生的新问题必须先分配新 TPQ ID。不得用“顺手
  修复”“其他类似问题”或一个总括审计任务代替可追溯队列。
- Terra 只负责只读清单、调用图和迁移后差异；Luna max 只领取合同冻结后的机械迁移；Tenant、
  Module、事务、DI、异常、Edition 和最终合入由 Sol 主代理负责。
- 子智能体不修改本计划、能力账本、服务登记或发布事实；主代理在一个逻辑批次收口后单次同步。
- TPQ00—TPQ13、TPQ20—TPQ26 是关键安全路径；TPQ30—TPQ39 可在上下文和 global scope 合同
  冻结后按文件 owner 并行。TPQ50 可与迁移同时准备，但最终 allowlist 只能在全部调用方盘点后冻结。
- 本阶段迭代只做 lint、静态门禁和受影响聚焦验证；TPQ52 才领取数据库/服务资源。最终 L2 候选
  只运行一次完整 P0-E，不在每个机械迁移后重复。

## 3. 当前发布流程与双 Edition 最终方向

### 3.1 核心团队怎样发布 Peanut Admin

核心团队在 Peanut Admin 源码仓库开发。功能先进入 `dev`，固定候选通过资格后进入 `main`，
然后对同一提交创建 annotated tag 和 GitHub Release。Release 里的规范 `tar.gz` 是整个 Peanut
Admin 源码仓库的确定性归档，并附带 manifest、SBOM、许可证和资格身份；它不是由
`create-app` 生成的 Edition 安装包。

### 3.2 普通开发者当前怎样创建应用

普通开发者先取得一个固定 Release checkout，再运行 `scripts/create-app`。创建器会生成独立
应用目录、应用 manifest、框架基线和文件 owner，并把用户业务文件标为 `app-owned`。生成后
开发者还需要配置资源、安装并发布；是否为自己的业务应用建立 Git 仓库由开发者决定。

这条技术路径已经验证，但当前创建器没有把 Standalone 与 Multi-tenant 固化为两个正式、可下载、
身份可核对的 Edition 安装包。独立应用仓库不会成为产品来源，也不会形成第二套产品源码。

### 3.3 Demo 当前怎样部署

线上 Demo 不是从移动的 `dev`/`main` 分支部署，也不是先运行 `create-app` 再部署。唯一部署
owner `scripts/deploy-release` 从正式 annotated tag 生成不可变源码归档，校验 SHA-256 后上传
到登记服务器，在服务器构建 Docker 镜像，再叠加受控 Demo 数据、写保护和入口配置。

这能证明 Peanut Admin 正式源码本身可以部署，但还不能证明普通用户拿到的两个 Edition 安装包
都能独立安装，并使用对应升级包完成受支持的跨版本升级。

### 3.4 当前怎样升级

现有 `scripts/scaffold-upgrade` 已有安全的 `preflight → apply → verify → recover` 闭环。它只
替换 Peanut Admin 管理的框架文件，遇到用户也改过的文件会停止，不静默覆盖 `app-owned`
业务代码。数据库迁移、依赖安装、备份、服务重启和 smoke 仍由应用自己的发布流程执行。

当前不足不是“完全没有升级能力”，而是升级输入和操作体验没有产品化：开发者需要自己准备
新旧 scaffold manifest 和对应文件，没有独立升级包、稳定下载入口、统一版本选择、完整冲突
说明和一份普通人能按步骤执行的升级手册。

### 3.5 已确认的最终交付方式

只保留一个人工开发事实源，从同一冻结 Release 生成五类正式制品：

| 交付物 | 面向谁 | 用户得到什么 | 维护原则 |
| --- | --- | --- | --- |
| Peanut Admin 源码仓库 | 核心开发者 | 开发、修复、资格和发布来源 | 唯一人工开发事实源 |
| Standalone 安装包 | 单组织、自托管用户 | 不启用 Tenant 过滤和 Tenant 字段/索引的独立版构建物 | 从固定 Release 生成，不人工维护第二份源码 |
| Multi-tenant 安装包 | 多组织、平台化用户 | 保留完整 Tenant 隔离和 Platform 能力的多租户构建物 | 从同一固定 Release 生成，不复制开发源码 |
| Standalone 升级包 | 已部署独立版用户 | 在 Standalone Edition 内跨受支持版本升级 | 只更新受管文件和对应 Schema，不覆盖用户业务与秘密 |
| Multi-tenant 升级包 | 已部署多租户版用户 | 在 Multi-tenant Edition 内跨受支持版本升级 | 保留 Tenant 数据/索引/隔离合同，不覆盖用户业务与秘密 |

推荐默认入口如下：

- 普通用户首次使用：先选择 Standalone 或 Multi-tenant，再下载该 Edition 的正式安装包。
- 需要自定义名称、slug 或 package identity 的用户：用固定 Peanut Admin Release 的创建器生成
  同一 Edition 的派生应用；生成结果可以进入用户自己的业务仓库，但不是新的官方产品源。
- 已有应用升级：只能使用当前 Edition 对应的升级包，不能用另一 Edition 或完整安装包覆盖。
- 跨版本升级：升级包声明支持的源版本范围、目标版本、Edition、迁移链和恢复边界；允许跳过版本
  时必须执行完整迁移链，不能只替换最终文件。Edition 转换不是普通升级，另行立项。
- 面向用户的官方 Demo：Multi-tenant Demo 从正式 Multi-tenant 安装包部署，再叠加只含合成数据、
  写保护和入口配置的 Demo overlay；Standalone 另做最小安装/登录验收，不伪装成多租户 Demo。
- 核心团队发布：源码 Release、两个安装包和两个升级包来自同一 source commit/tree，逐制品记录
  Edition、版本、生成器版本、Schema 身份和 SHA-256。

完整安装包不能直接当升级包，因为它无法安全区分用户的订单、库存等业务代码、第三方 Module、
环境配置和 Peanut Admin 管理文件。Edition 升级包沿用现有 manifest 文件 owner 和三方比较能力，
只把升级所需的受管文件、checksum、兼容范围、恢复信息和说明封装成可下载制品。

## 4. Demo 审计问题与证据现状

本轮真实浏览器审计曾在 `output/playwright/demo-audit-v3012/` 生成 202 份快照、截图和 console
记录，但文件都位于各站点的隐藏目录 `.playwright-cli/`，没有提交为长期证据，也没有形成
人类可读报告。Finder 或普通文件浏览器会让该目录看起来是空的；这不符合可交付审计要求。

当前候选已经把问题同时登记在本计划的 `DA01—DA08` 与
[`Demo 体验审计`](../product-status/audits/demo-experience-audit.md)；修正工作是下方
`PE01—PE05`。机器问题清单、历史证据索引、4 张修复候选截图与聚焦请求/console 哈希均已
形成，待本批次合入后成为长期事实；原始隐藏目录只作为过程材料，不再要求用户自行寻找。

| ID | 站点/操作面 | 用户看到的现象 | 当前判定 | 业务影响 |
| --- | --- | --- | --- | --- |
| DA01 | 共享 Admin、Tenant A、Tenant B 的菜单和按钮 | 多个看得见的操作点击后才提示“暂无访问权限” | 高置信 | 用户认为产品功能损坏，而不是账号能力受限 |
| DA02 | Platform → 存储 | 默认存储路由接口重复返回 HTTP 500 | 高置信、稳定复现 | 运维人员无法判断是未配置还是系统故障 |
| DA03 | 生产准备清单 | 页面出现 `readiness.items.undefined.title` | 高置信、三站点出现 | 页面文案不完整，降低产品可信度 |
| DA04 | 装修管理 | 中文菜单缺少稳定翻译 key | 高置信 | 切换语言或严格 locale 时显示异常 |
| DA05 | Platform 角色权限 | 浏览器持续提示 checkbox API 将废弃 | 高置信 | 当前是噪声，未来组件升级会成为兼容问题 |
| DA06 | Tenant A 长会话 | 后段出现“租户会话不可用”，与无权限提示并存 | 待核实 | 可能是审计会话残留，也可能是真实会话恢复问题 |
| DA07 | 三张 404 快照 | 没有记录 URL 和 Host，无法归属站点 | unknown | 不能作为已确认缺陷，也不能直接关闭 |
| DA08 | Platform → Tenant 详情 | 详情能显示，同时弹出“请求失败” | 高置信 | operator 无法判断操作到底成功还是失败 |

候选 `836d8a9…` 已完成 DA01—DA08、第二 persona 装修与日志的干净重登录复验；原不明 404
已用 Tenant A/B 明确 Host 与 URL 归属为主动未知路由。仍未执行 Tenant 暂停/关闭、清空日志、
密码修改、真实 Provider、真实资金、删除和其他不可逆动作，也未运行公开 PC/H5/callback 全链。

## 5. 第一阶段：补齐审计事实

| ID | 任务 | 状态 | 交付结果 | 最低验收 |
| --- | --- | --- | --- | --- |
| DL01 | 建立长期 Demo 审计报告 | 已完成（本分支） | [`demo-experience-audit.md`](../product-status/audits/demo-experience-audit.md) 按站点、角色、页面、操作、预期、历史结果和修复复验记录 | 用户无需打开隐藏目录即可找到问题与关闭结论 |
| DL02 | 建立机器可读问题与证据索引 | 已完成（本分支） | 同目录问题 JSON、历史证据索引、7 张脱敏截图和 PE05 过程哈希 | 每个高置信问题有可归属证据；秘密、Cookie、token 未进入审计目录 |
| DL03 | 建立计划与问题的双向链接 | 已完成（本分支） | DA01—DA08 与 PE01—PE05 双向关联并记录关闭状态 | 从问题可找到修正任务，从修正任务可回到原始现象和复验 |
| DL04 | 明确未执行范围 | 已完成（本分支） | 报告单列破坏性、资金、Provider、公开端与自然长会话未执行流程 | 未覆盖项未被描述为通过或失败 |

## 6. 第二阶段：冻结双 Edition 分发与升级决定

| ID | 任务 | 状态 | 需要决定或产出 | 最低验收 |
| --- | --- | --- | --- | --- |
| RD01 | 冻结唯一开发事实源 | 已确认 | Peanut Admin 源码仓库是唯一人工开发来源；不建立官方应用源码仓库 | Standalone 与 Multi-tenant 不形成两套人工维护源码 |
| RD02 | 冻结双 Edition 构建物 | 已确认 | 同一固定 Release 生成 Standalone 与 Multi-tenant 安装包 | 两包来源相同，Edition 和 Schema 差异显式可查 |
| RD03 | 冻结双 Edition 升级物 | 已确认 | 每个 Edition 有独立升级包；Edition 之间不互相覆盖升级 | 升级包声明 Edition、源版本范围、目标版本和迁移链 |
| RD04 | 冻结普通用户主入口 | 已确认 | 首次安装下载所选 Edition 安装包；定制应用使用固定 Release 的创建器 | 快速开始先选择 Edition，不要求用户理解内部发布工程 |
| RD05 | 冻结统一来源身份 | 已确认 | 源码、两安装包和两升级包都记录 source commit/tree、生成器/Schema 版本与 checksum | 任一制品可追溯到唯一正式 Release |
| RD06 | 冻结跨版本与转换边界 | 已确认 | 同 Edition 可按兼容矩阵跳版本并执行完整迁移链；跨 Edition 转换不属于普通升级 | 漂移、降级、错 Edition、缺迁移或篡改均 fail-closed |

## 7. 第三阶段：建立双 Edition 安装包

| ID | 任务 | 状态 | 交付结果 | 最低验收 |
| --- | --- | --- | --- | --- |
| AR01 | 冻结 Edition 构建输入 | 已完成（功能冻结候选） | `818c337…` 固定 commit/tree、Edition profile、依赖锁、Module lock、Schema profile 与最终 inventory 已进入同一生成链 | 尚不是正式 Release，最终 scaffold 与资格身份仍须从该提交收敛 |
| AR02 | 生成 Standalone 安装包 | 部分完成（聚焦制品通过） | `818c337…` 已生成并确定性复核 Standalone `tar.gz` 和外部 manifest | 仍缺正式发布附件与独立空库消费 |
| AR03 | 生成 Multi-tenant 安装包 | 部分完成（聚焦制品通过） | 同一提交已生成并确定性复核 Multi-tenant `tar.gz`，保留 Tenant/Platform profile | 仍缺正式发布附件与独立空库消费 |
| AR04 | 发布双包与 checksum | 部分完成（未发布） | 开发制品已有区分 Edition 的文件名、外部 manifest 与 SHA-256 | SBOM/许可证附件、正式 Release 下载入口尚缺 |
| AR05 | 校验双包身份与差异 | 部分完成（聚焦合同通过） | 构建器与 `CreateApplicationTest` 已核对来源一致、Edition 投影和 Schema 差异 | 固定 Release 上的最终机器差异报告尚缺 |
| AR06 | 双 Edition 首次安装验收 | 未开始 | 两个正式包分别完成一次最低充分空库安装 | 不依赖开发 worktree，均能配置、安装、启动和登录 |

### 7.1 AR02 的 gateway/runtime 前置合同

Standalone 不能只对 ORM 表删除 `tenant_id`。现行数据归属登记中另有 18 张正式
`tenant-gateway` 表；它们由 PDO、Db gateway 或 Core package repository 访问。AR02 只有在下列
闭包全部完成后才可标记完成：

1. Edition profile 对 18 张表逐表登记 `strip_tenant_column` 或 `exclude_platform`，并同时声明
   唯一键、索引、外键和 seed/migration 的投影规则；`pa_tenant_entry_binding` 与
   `pa_tenant_owner_invitation` 在 Standalone 整表排除，其余表不得保留仅用于过滤的 Tenant 字段。
2. 应用内非 ORM gateway 统一消费一个由 composition root 选择的 Tenant-column scope；业务服务不
   读取 Edition，不散布 `if (standalone)`，Multi-tenant 默认行为和 fail-closed Tenant 语义不变。
3. Core 的 Idempotency、Task/Job 与 Import/Export repository/schema 先提供产品中性的
   `tenant-scoped` / `instance-scoped` 持久化合同并发布固定版本；应用不得复制 Core SQL、修改
   `vendor/` 或依赖移动分支。
4. `init.sql`、受影响 migration、运行时 repository、安装 manifest 和升级 manifest 必须来自同一
   Edition profile。只证明 29 张 ORM 表去字段，或只生成可解压 archive，均不算 AR02 完成。

Core 上游任务固定为 `P1-ED01 Edition persistence scope`；其实现默认值必须保持
`tenant-scoped`，只有 Peanut Admin Standalone composition root 可以显式选择
`instance-scoped`。Core 固定版本未发布前，只允许本地路径联调，不得封存正式安装包。

## 8. 第四阶段：建立双 Edition 升级包和跨版本升级流程

| ID | 任务 | 状态 | 交付结果 | 最低验收 |
| --- | --- | --- | --- | --- |
| UP01 | 冻结升级包格式 | 部分完成（本地候选 `8d3a4f0…`） | v1 manifest 已包含 Edition、源版本下界/目标、完整 migration 列表、受管文件、来源、恢复和签名身份 | 正式版本采用与不可变 Release 附件尚缺 |
| UP02 | 冻结文件 owner | 部分完成（聚焦合同通过） | 包只承载 `managed`/`generated-managed`；`app-owned`、第三方 Module 和秘密显式保留 | 仍需 UP10 的第三方 Module/秘密代表 fixture |
| UP03 | 生成两个最小升级包 | 实现完成；正式采用后置到首个升级版本 | 固定提交 `8d3a4f0…` 已生成 Standalone/Multi-tenant 两个签名开发包 | v3.0.13 是首个正确 Edition 基线，不伪造旧来源；下一补丁从 v3.0.13 生成正式包 |
| UP04 | 让升级器消费升级包 | 部分完成（本地路径） | 包内自带升级器；解压后用 `--package` 与 `--signature-key-id` 进入既有 preflight/apply/verify/recover | 正式下载入口尚缺 |
| UP05 | 支持在线与离线两种取得方式 | 部分完成（仅离线） | 本地解压包进入唯一校验链 | 固定版本下载和有界网络失败说明尚缺，不会静默 fallback |
| UP06 | 增加身份与兼容检查 | 部分完成（聚焦合同通过） | Ed25519 包外 trust、inventory/checksum、Edition、同大版本范围、完整 migration 列表均 fail-closed | 正式 key authority、真实跳版本演练与大版本发布策略证据尚缺 |
| UP07 | 显示冲突与修改计划 | 部分完成（候选待合入） | plan 新增 `impact`，逐文件列出 will-change、will-preserve、must-resolve 和所有权说明；篡改说明会被拒绝 | 仍需在 UP09 正式双 Edition 演练中核对真实输出可读性 |
| UP08 | 串联完整应用升级步骤 | 部分完成（文档候选） | 普通指南已串联固定下载/离线包、验签、备份、文件计划、依赖、migration、构建、启动、smoke 和分层恢复 | 正式版本号、下载入口和最终演练结果待 REL04/REL06 填入 |
| UP09 | 双 Edition 跨版本演练 | 后置到首个升级版本 | Standalone 与 Multi-tenant 各从首个正式 Edition 基线升级到下一补丁 | v3.0.13 发布前没有可诚实使用的旧 Edition；下一补丁执行 preflight/apply/verify、完整 migration chain、登录和关键页面 |
| UP10 | 证明用户内容不被覆盖 | 部分完成（候选待合入） | 聚焦合同已证明代表 `app-owned`、`server/.env` 秘密和第三方 Module 在 apply/verify/recover 中字节不变；冲突先生成零写入停止计划 | 尚缺 UP09 固定候选上的双 Edition 真实演练采用 |

## 9. 第五阶段：重写普通开发者与核心开发者文档

| ID | 任务 | 状态 | 主要文档 | 最低验收 |
| --- | --- | --- | --- | --- |
| DOC01 | 重写普通开发者快速开始 | 已完成（候选待合入） | `docs-site/getting-started.md` | 首屏先帮助用户选择 Standalone 或 Multi-tenant，不要求理解核心发布工程 |
| DOC02 | 编写双 Edition 安装指南 | 已完成（候选待合入） | 快速开始与安装页 | 从下载对应安装包到登录的步骤、输入、结果和常见停止点完整 |
| DOC03 | 编写 `create-app` 定制指南 | 已完成（候选待合入） | `docs/create-application.md` 及公开投影 | 清楚说明何时下载正式包、何时定制生成，以及生成后如何进入用户自己的业务仓库 |
| DOC04 | 编写普通用户升级指南 | 已完成（候选待合入） | `docs-site/guide/deployment-upgrade.md` | 用业务语言说明预检查、冲突、备份、升级、验证和恢复；首个 Edition 基线明确无升级包 |
| DOC05 | 重写核心发布手册 | 已完成（候选待合入） | `docs/release-engineering.md`、operations 指南 | 核心开发者能从唯一候选发布全部制品、Demo 和文档站 |
| DOC06 | 编写制品身份对应说明 | 已完成（候选待合入） | Release、安装/升级包、派生应用身份说明 | 用户能查明各制品来自哪个源码 Release，并核对 Edition/Schema/checksum |
| DOC07 | 更新文档站导航与 Release 页面 | 已完成（候选待合入） | docs-site 导航、下载与升级入口 | 新用户最多两次导航可到安装或升级主流程 |

## 10. 第六阶段：修复 Demo 可见体验

| 顺序 | ID | 任务 | 状态 | 主要交付 | 最低验收 |
| ---: | --- | --- | --- | --- | --- |
| 1 | PE01 | Demo 账号权限与页面投影对齐 | 已完成（候选） | 固定 Platform、bootstrap Admin、共享 Admin、Tenant A/B 角色可见的菜单、按钮和 API 权限；修正 Demo seed、菜单或按钮状态 | 两个共享 persona 与 Tenant A/B 聚焦路径通过；无权导出按钮隐藏，写保护独立保留 |
| 2 | PE02 | 修复 Platform 存储和 Tenant 详情状态 | 已完成（候选） | 未配置、配置错误和 Provider 不可达显示稳定状态；消除详情成功与失败提示并存 | 存储页与 default/Tenant A/Tenant B 详情请求均为 200，无矛盾提示 |
| 3 | PE03 | 补齐页面文案和组件兼容 | 已完成（候选） | 修复 readiness、装修导航翻译、失效图标和 checkbox 当前 API | DA03—DA05 页面不再复现原问题，聚焦 console 为 0 error/0 warning |
| 4 | PE04 | 统一 Release、Demo 和应用入口说明 | 已完成（DOC01—DOC07） | 清楚说明源码 Release、双 Edition 安装包、Demo overlay、`create-app` 和用户自有业务仓库 | 文档不再把移动分支 clone 或另一官方应用仓库写成正式安装输入，也不泄露密码 |
| 5 | PE05 | 四站点聚焦复验 | 已完成（`836d8a9…`） | 固定一个修复候选复核 Platform、共享 Admin 两角色、Tenant A/B 和安全表单 | DA01—DA08 均通过或以可归属证据关闭；旧 session 与本机代理不计入产品通过；破坏性动作保持未执行 |

PE01 是 Demo 关键路径。PE02 与 PE03 可在文件 owner 不冲突时并行；文档先写权威上游再投影。
PE05 只在直接前置满足后运行一次。权限或 Tenant Runtime 变化属于 L2，完整 P0-E 只在最终
冻结候选运行一次，不在迭代修复阶段重复。

## 11. 第七阶段：冻结候选并形成一次完整交付

| ID | 任务 | 状态 | 最低结果 |
| --- | --- | --- | --- |
| REL01 | 聚焦验证 | 已完成（`818c337…`） | 最终 inventory 与双安装包 build/check、升级包合同、Demo overlay 合同、资格入口合同、账本、文档治理和 VitePress 构建通过；未运行完整 P0-E |
| REL02 | 冻结唯一候选 | 已完成（source `818c337…`） | 最终 inventory、scaffold manifest/files 与 P0-E fixture 已锁定同一 source commit/tree、manifest、inventory 和 managed tree；封存后未修改产品 Runtime |
| REL03 | 正式资格 | 未开始 | 只对冻结 L2 候选运行一次 P0-E；失败候选返回 Development mode，不边跑边修 |
| REL04 | 发布全部正式制品 | 未开始 | v3.0.13 发布源码与 Standalone/Multi-tenant 安装包；首个升级版本再发布两个同 Edition 升级包，不能伪造来源 |
| REL05 | 双 Edition 独立消费 | 未开始 | 从两个正式安装包分别完成一次独立安装，不依赖开发 worktree |
| REL06 | 双 Edition 升级消费 | 后置到首个升级版本 | v3.0.13 先成为合格来源；下一补丁分别使用正式升级包完成一次受支持的跨版本升级和恢复边界验证 |
| REL07 | 更新 Demo 和文档站 | 未开始 | Demo 从正式 Multi-tenant 安装包部署并叠加受控 overlay；公开文档给出 Edition 选择、安装、升级、Demo 和下载入口 |
| REL08 | 冻结统一发布快照 | 未开始 | Release、两个安装包、两个升级包、Demo 与文档版本/摘要可互相核对 |

v3.0.13 完成 REL01—REL05、REL07 和 REL08 后，只能报告“首个双 Edition 安装分发基线已完成”。
只有下一补丁进一步完成 UP09、UP10 正式采用与 REL06，才能报告“跨版本升级体验已完成”。这不
改写 `v3.0.12` 已经完成的正式源码交付事实，也不允许用旧错误生成物缩短发布顺序。

## 12. 后续仍可计划的产品增强

| ID | 任务 | 当前状态 | 前置与业务结果 |
| --- | --- | --- | --- |
| PE10 | 跨 Module 可运行业务示例与新增入口 Guard | 未开始 | 补一个真实跨 Module 业务链，证明权限、Tenant 和失败语义；不重做 CR21 的 Module v1→v2 资格 |
| PE11 | 外部回调可信 Tenant 路由 | 未开始 | 公众号等没有浏览器会话的回调必须通过签名和可信业务映射找 Tenant，不从 Host 或客户端字段猜测 |
| PE12 | T16 部分/多次退款 | 外部阻塞 | 每笔退款需独立流水来源号并保持请求幂等；真实资金仍需另行授权，只对登记测试资源复核失败组 |
| PE13 | 真实 Provider 分项资格 | 外部阻塞 | 邮件、短信、支付、OAuth、微信、Storage 分别取得 owner、凭据引用和副作用授权后逐项验证 |
| PE14 | 第三方业务生产采用 | 外部阻塞 | 需要非 Peanut Admin、非合成 Demo 的真实业务 owner 和独立资源/部署授权 |

## 13. 生态、独立项目和历史暂缓项

| ID | 事项 | 当前分类 | 恢复条件与归属 |
| --- | --- | --- | --- |
| PE20 | Marketplace | blocked | 需要包摘要、受信签名、SBOM、许可证审核、漏洞响应和兼容 authority；当前受控直接分发继续可用 |
| PE21 | 跨实例运营平台 | 本仓范围外；独立项目可计划 | 在独立仓库、数据库和部署环境实现实例登记、健康、备份证据、签名升级和审计，不进入 Peanut Admin Runtime/Core |
| PE22 | DCS 业务 Module | 本仓范围外；有前置可计划 | 在 DCS 仓先冻结 Tenant 与经营主体映射并取得业务批准，再推进 Party/Product/Inventory/Procurement |
| PE23 | 跨应用身份联邦、Outbox/Event Bus | deferred 设计候选 | 至少两个真实消费者提出共同身份或事件需求后再冻结协议 |
| PE24 | 预构建生产镜像 | out_of_scope | 另批容器 Registry、签名、SBOM 和供应链发布合同后才能计划 |
| PE30 | 完整 SaaS 商业化 | deferred | 至少两个真实实例接入运营平台并完成升级、备份恢复演练，再冻结客户、Tenant、套餐、Entitlement 和资金合同 |
| PE31 | SupportSession/跨租户客服代运营 | deferred | 有真实支持场景和客户授权后，冻结能力、到期、撤销和双边审计；PlatformOperator 不直接读取租户业务 |
| PE32 | 父子 Tenant、集团权限继承、每 Tenant 独立数据库、客户业务分析 | deferred 设计候选 | 分别出现真实组织、隔离或分析需求后独立立项 |
| PE33 | 远程入口/SSH 托管 | out_of_scope | 实例默认主动出站；只有独立安全设计和用户授权后才能评估 |

## 14. 永久不恢复的边界

- 自动重构、静默覆盖或双写 `app-owned` 业务源码永久禁止；确需源码迁移时另建显式、可审阅、
  可恢复的迁移工具。
- 1.x 数据库/scaffold 原地 adopt、长期双 Runtime、长期双字段和兼容镜像不恢复；3.0 保持
  fresh-only，历史证据只用于追溯。
- 超级管理员读取全部租户业务、每租户业务表、把运营平台嵌入 Core/SaaS Host 均不恢复。
- 完整安装包直接覆盖升级永久禁止；升级只能按相同 Edition 的受管文件、Schema 与迁移链合同执行。
- Standalone 与 Multi-tenant 之间的转换永久不得伪装成普通版本升级；如有真实需求，必须单独设计
  数据归属、Schema 重建、回滚和停机合同。

## 15. 执行顺序与停止线

1. Phase 0 的 `TPQ00—TPQ53` 已完成；后续从 `AR01` 开始进入双 Edition 构建、Schema 和升级实现。
2. `DL01—DL04` 可作为不修改 Runtime 的独立文档线，让问题、证据、未知和修复任务可见可追踪。
3. `RD01—RD06` 已按用户决定冻结，不创建官方应用源码仓库。`AR01—AR06` 与 `UP01—UP10` 是
   Phase 0 后的一条交付关键路径；只有文件 owner 明确不冲突的文档或
   Demo 修复才并行。最多保持一条关键路径和两条独立线。
4. `DOC01—DOC07` 先更新权威文档，再更新公开 docs-site；内部证据、资源地址和凭据引用不进入
   公共投影。
5. `PE01—PE05` 完成后只做一次受影响站点聚焦复验。迭代期不运行完整 P0-E。
6. `REL01—REL08` 只对一个冻结候选执行。所有数据库、端口、服务、容器、缓存、浏览器或 Gate
   必须先读取资源登记、声明实际 resource ID/环境/地址并成功 claim 租约。
7. 每个逻辑批次使用 `feat/* → dev` 的单一可审查 PR；完成后同步本计划。只有稳定能力真实变化
   时才更新 capability ledger 及生成投影。
8. Marketplace、T16 真实资金、Provider 真实外呼、第三方生产采用、跨实例运营平台和完整 SaaS
   均保持各自授权与范围边界，不因本计划自动获得执行授权。

当前关键路径是把本封存批次通过单一 PR 合入 `dev`，再只对一个冻结 L2 候选执行
v3.0.13 的 REL03—REL05、REL07、REL08。v3.0.13 发布后才具备 UP09/REL06
需要的合格来源；该升级采用进入下一补丁，不创建官方应用源码仓库，也不在迭代期运行完整资格。
