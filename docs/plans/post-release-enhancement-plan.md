# Peanut Admin 发布后增强任务计划

> 状态：当前计划；尚未开始实现
>
> 基线：`v3.0.12@fe328a320b7c68b3c2f47512f2aa4afcad43c630`
>
> 决策日期：2026-08-29
>
> 完成事实仍以 `docs/product-status/capability-ledger.json` 为准

## 1. 目标与事实边界

本计划承接 consumer-ready 正式源码发布后的体验修正和可选增强。它不重做已经完成的
PC00—PC70、CR01—CR40，也不把计划、浏览器探索或历史材料写成已实现能力。

当前 `v3.0.12` 的正式源码、annotated tag、GitHub Release、scaffold 和 P0-E 8/8 身份保持
不变。登记 Demo 与文档站已经采用该提交；下表新增的是后续候选，不改写不可变 Release。

本轮 Demo 审计使用登记的 `production-candidate` 部署、MySQL 和四域名资源，固定候选为
`fe328a320b7c68b3c2f47512f2aa4afcad43c630`。证据位于
`output/playwright/demo-audit-v3012/`，仅用于问题定位；密码、Cookie、token 和环境内容不进入
计划或证据。

## 2. Phase 0：ThinkPHP 架构质量全局前置队列

本阶段是 PE01 以后所有产品逻辑、双 Edition、Schema、构建物和升级工作的前置阶段。它不是
“挑一个 Module 做示例”的局部重构，而是一次全仓横切边界替换。安全热修可以进入同一合同，
但不得继续新增手写 Tenant 谓词、逐 Repository Module 门禁、手工分页或新的静态 Logic。

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

### 2.2 当前完整静态基线

基线为 `origin/dev@b51610b49632f2a3a38357c73bebb9186dea43f7`，只读扫描未连接数据库或
运行服务。下列数量是问题队列的起点，不是完成结论；实施候选必须用同一规则重算并归零或进入
审核后的 allowlist。

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

### 2.3 ThinkPHP/ThinkORM 能力覆盖索引

下表是本轮必须逐项检查并关闭的能力全集。它不是“建议关注方向”；每一行必须落到后续明确的
TPQ 任务、逐路径清单和验收结论。发现新问题时先新增唯一 TPQ ID，再实施；禁止把新发现藏进
“继续系统性检查”或某个既有任务的备注。

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
| TPQ00 | 建立版本化问题登记：每个扫描命中记录 `issue_id/category/path/symbol/owner/decision/status/verification`；allowlist 另含理由、风险和到期/复核条件 | 未开始 | Terra medium 生成只读清单；Sol 审批 | INV01—INV16 每个命中可追溯到 TPQ 或 allowlist；不存在“其他类似问题”未登记桶 |
| TPQ01 | 冻结 35 个 Model 与 47 张 Tenant 表的 `tenant/platform/instance/shared/tenant-derived` 所有权登记；禁止以目录或命名猜 owner | 部分完成；静态清单已形成 | Terra medium 盘点；Sol 决策 | 每个 Model/表恰有一个 owner、scope policy 和访问入口；18 张无 Model 表全部有显式边界 |
| TPQ02 | 建立 request/console/callback/worker/scheduled 共用的不可变 `ExecutionContext` 生命周期，替换 Request 动态属性和静态 Runtime 缓存中的 Tenant 状态 | 未开始 | Sol high | 三种 HTTP 身份和三种非 HTTP 执行形态均无上下文串用；缺上下文 fail-closed |
| TPQ03 | 冻结 `MultiTenantDataScopePolicy` 与 `StandaloneDataScopePolicy`；Edition 只在 policy/composition root 选择，业务代码没有 edition `if` | 未开始 | Sol high | 同一业务调用在 Multi-tenant 生成一个 Tenant 谓词，在 Standalone 生成零个 Tenant 谓词 |
| TPQ04 | 实现全局 `TenantOwnedModel`/global scope，并迁移 29 个 Tenant ORM Model；6 个非 Tenant Model 明确不继承 | 未开始 | Sol 建合同；Luna max 按清单迁移 | 任意 Tenant Model 的 select/find/update/delete 自动带 scope；普通代码无法漏调命名 scope |
| TPQ05 | 用 Model write hook/受控 persistence hook 自动写入 Tenant，拒绝请求 payload 覆盖；bulk update/delete 也受 global scope | 未开始 | Sol high | create/save/bulk update/delete 四类写入均不能跨 Tenant；Standalone 不写不存在字段 |
| TPQ06 | 统一 belongsTo/hasMany/eager loading 的 Tenant 规则，删除依赖 `$this->tenant_id` 的关系谓词；验证 global scope 在 alias/relation/with/withLimit 下的真实 SQL | 未开始 | Sol high | 多父记录 eager load 不串 Tenant；relation 查询没有重复或歧义 tenant_id |
| TPQ07 | 建立 `PlatformTenantDataGateway` 和唯一可审计 scope bypass；清点 Platform、安装、迁移、bootstrap、修复和系统查询 | 未开始 | Sol high | `withoutGlobalScope` 只出现在 allowlist gateway；每次跨 Tenant 查询有 actor/operation/audit |
| TPQ08 | 为 18 张无 Model Tenant 表和 Db/PDO 路径建立 `TenantQuery`/领域 gateway；禁止普通业务直接 Db/PDO 查询 Tenant 表 | 未开始 | Sol 定边界；Luna max 机械迁移 | INV02 的 18 张表均有 owner；Db/PDO 混用归零或进入事务 owner allowlist |
| TPQ09 | 拆分 15 个 TenantRepository：保留领域 persistence/transaction，删除手写 Tenant 谓词、create 注入和 Module 门禁 | 未开始 | Sol 处理 Finance/OAuth/Task/File；Luna max 处理低风险 CRUD | Repository 不再重复 global scope 或 Module guard；直接 Model 绕过清单归零 |
| TPQ10 | 修复 JobsValidate、OfficialAccountReplyLogic、Dictionary Provider、支付 callback 等直接绕过点 | 未开始 | Sol high；低风险验证迁移可交 Luna max | 双 Tenant 同名岗位合法；跨 Tenant ID 不可枚举；callback 仍从可信绑定恢复上下文 |
| TPQ11 | 逐关系核对复合 FK、全局 ID 和查询谓词，删除数据库已蕴含的重复 JOIN/WHERE；`ArticleCollectionSummaryService` 在全局 Scope 与现有复合 FK 生效后只按 `article_id` 关联，不再重复比较收藏/文章 tenant_id；先补文件、支付、OAuth、通知等关系缺口 | 未开始 | Sol high | 每个被删谓词有 FK/唯一键证据；Article 收藏汇总只有根 Scope 一个 Tenant 条件且结果不变；缺口先迁移、后删查询条件，不按文本批量删除 |
| TPQ12 | 改造生成器：所有权必填，按 owner 生成 Tenant/Platform/Instance Model、global scope、Application Service、分页和验证；拒绝裸 BaseModel 模板 | 未开始 | Sol 合同；Luna max 模板实现 | 新生成 Tenant CRUD 没有裸 Model 查询、手写 tenant_id、静态 Logic 或手工分页 |
| TPQ13 | 冻结 Model/Query 写入合同：字段白名单、readonly、mass assignment、create/save/saveAll/insertAll/update/delete 的 Tenant 注入、事件覆盖和返回语义 | 未开始 | Sol high | 请求 tenant_id 永不生效；所有写 API 要么自动受 policy 保护，要么被静态禁止并只能走受控 persistence gateway |

#### B. Module、权限与统一执行边界

| ID | 问题与最终交付 | 状态 | 执行 owner | 最低验收 |
| --- | --- | --- | --- | --- |
| TPQ20 | 把现有 `OfficialModuleMiddleware`、`ModuleExecutionGuard` 和各特例收敛为唯一 `ModuleExecutionBoundary`；明确删除 `ArticleTenantRepository::assertAvailable()` 及六个逐查询调用 | 未开始 | Sol high | installed/disabled/failure 保持稳定 40300/50300 envelope；同一次入口只查一次 Module；Article Repository 不再取得 PDO 或检查 Module |
| TPQ21 | Admin、Platform、member、public 路由按身份边界分组，固定认证→Host/Tenant→Module→RBAC→audit 顺序；删除 Article 专用 middleware | 未开始 | Sol 定顺序；Luna max 路由迁移 | URI、method、permission 和响应不变；middleware 顺序有机器检查 |
| TPQ22 | public Article/OAuth、支付回调、external resolver、Worker、Scheduler 采用同一 boundary adapter，不在 Controller/Logic 手写 executionGuard | 未开始 | Sol high | 无 Tenant、错 Tenant、disabled、重放和合法路径矩阵通过 |
| TPQ23 | Repository 只接收已授权 `ExecutionContext`，Module 可用性不再由每个查询方法重复检查 | 未开始 | Sol high | `assertAvailable()` 类方法归零；内部/cross-Module 调用不能绕过 boundary |
| TPQ24 | 使用 ThinkPHP Provider/Container 作为 composition root，替换空 `AppService`、控制器 `new Pdo*` 和请求相关静态 factory 缓存 | 未开始 | Sol high | Controller 注入 application contract；长驻进程不复用上次请求的 Tenant/PDO 状态 |
| TPQ25 | 用不可变 Request/Actor/Edition/Module 子上下文替换 Request 动态属性，规定建立、只读消费、finally 清理和禁止序列化 secret 的生命周期 | 未开始 | Sol high | admin/member/platform/public 四种请求不会互相残留 actor/Tenant；Scope 只能读取已验证上下文 |
| TPQ26 | 为 command/callback/worker/scheduled 建立同构 context factory 与 boundary adapter；逐项决定 event/listener 是否有真实消费者，不为形式引入事件总线 | 未开始 | Sol high | 每个非 HTTP 入口都有 actor、Tenant/instance、module、operation 和清理点；无来源上下文 fail-closed |

#### C. 分页、查询效率与 ORM 高级能力

| ID | 问题与最终交付 | 状态 | 执行 owner | 最低验收 |
| --- | --- | --- | --- | --- |
| TPQ30 | 冻结唯一 `PageRequest`、`PageResult` 与最大页大小规则；清理 `PaginationInput`、`ExportPageInfo` 和各 Logic 自行 clamp 的重复 | 未开始 | Sol 合同；Luna max 迁移 | 请求字段、空页、上限和导出语义只有一个事实源 |
| TPQ31 | 实现 route-group `PaginationResponseMiddleware` 或唯一 response transformer，把 Paginator/PageResult 转为既有 `dataLists` envelope | 未开始 | Sol high | 外部结构精确保持 `code/msg/data.{lists,count,pageNo,pageSize}`；Controller 不再拆数组 |
| TPQ32 | 迁移 INV04 的 27 个 `page()` 调用到 ThinkORM `paginate()`/PageResult；需要扩展统计的列表使用 PageResult metadata | 未开始 | Luna max 分模块迁移；Sol 处理 Finance/Platform | `paginate()` 覆盖全部常规列表；手工 count/page/select 归零或进入有理由 allowlist |
| TPQ33 | 消除全部已知 N+1：Article 分类文章、Generator 表字段、RefundLog handler；再用 SQL query counter 扫描所有列表/循环，新增发现进入本队列 | 未开始 | Luna max 处理 Article；Sol 处理锁与 Finance | N 条数据的 SQL 数为常数级；生成器锁语义和退款审计显示不变 |
| TPQ34 | Article 点击改为带 Tenant/可见性条件的原子 increment；盘点其余读改写计数器 | 未开始 | Luna max 实现；Sol 审查 | 并发请求不丢计数，跨 Tenant/下架文章不更新 |
| TPQ35 | 把 24 处手写事务迁移为 `Db::transaction()` 或明确 transaction owner；核对 Db/PDO 是否同连接，保留锁顺序 | 未开始 | Sol 处理 Finance/Task/Schema；Luna max 处理普通 CRUD | 异常自动回滚；嵌套、锁和返回错误语义与现状一致 |
| TPQ36 | 建立字段 cast/JSON 规则，盘点 21 个 accessor/mutator；纯格式化进入 DTO/presenter，模型 accessor 不发 SQL | 未开始 | Terra 分类；Luna max 机械迁移 | RefundLog handler N+1 消失；无效 JSON 的既有业务决定被显式保留或一次性替换 |
| TPQ37 | 核对 14 个 SoftDelete Model 与 global scope、relation、restore、bulk delete 的组合；冻结 Model event/hook 顺序 | 未开始 | Sol high | Tenant scope 和 soft-delete scope 均不可被普通路径绕过；恢复不跨 Tenant |
| TPQ38 | 逐个登记 relation/with/withLimit/accessor 内查询和循环内查询，选择 eager load、批量映射、withCount/聚合或窗口查询；禁止 accessor 发 SQL | 未开始 | Terra medium 清单；Luna max 迁移低风险读链 | 每个列表查询的 SQL 数量与结果行数无关；关系 owner 与排序/每父项 limit 语义保持 |
| TPQ39 | 逐个登记 Query Builder 的 join/subquery/aggregate/increment/decrement/lock/batch write 用法，替换 PHP 读改写和可合并的重复 round trip | 未开始 | Sol 处理锁/Finance；Luna max 处理无事务计数 | 每个替换有 SQL 与并发语义证据；未改变锁顺序、精度、幂等或错误 envelope |

#### D. HTTP、验证、异常与应用分层

| ID | 问题与最终交付 | 状态 | 执行 owner | 最低验收 |
| --- | --- | --- | --- | --- |
| TPQ40 | 统一 97 处验证调用和 3 个手工实例化 Validate 路径为 tenant-aware `ValidatedInput`；修复 JobsValidate 跨 Tenant 查询 | 未开始 | Sol 定上下文；Luna max 迁移 | scene、字段白名单和 40000 失败结构保持；Validate 不直接裸查 Tenant Model |
| TPQ41 | 建立领域异常→HTTP/API 错误的统一 renderer，替换 88 个分散 `JsonService::fail` 和重复 catch 映射 | 未开始 | Sol high | 认证、验证、Module、权限、业务冲突和系统错误各有稳定 code/status；异常可观察且不泄密 |
| TPQ42 | 逐 Module 收敛 `Controller → Application Service → Repository/Adapter`，退出并行的静态 Logic/Service/Application 三套层次 | 未开始 | Sol 定模块顺序；Luna max 迁移简单 CRUD | 78 个 Controller 不直接组装 PDO/Model；跨 Module 只走 contract |
| TPQ43 | 建立 route name/binding 和 middleware alias/priority 合同；Tenant 实体只有在 global scope 已生效后才能使用 model binding | 未开始 | Sol 定安全顺序；Luna max 机械路由 | 主路由职责缩小；URI 与权限 key 不变；binding 不可枚举跨 Tenant ID |
| TPQ44 | 统一 cache/session/log namespace、生命周期、request/operation trace、secret redaction 和 audit adapter | 未开始 | Sol high | Tenant cache/session 不串空间；日志不含 secret，管理/公开/任务关键写入均可追踪 |
| TPQ45 | 统一 HTTP transport、timeout/retry/trace/redaction、文件系统与 Storage Provider adapter；退出业务代码裸 curl 和随意 new client | 未开始 | Sol 定合同；Luna max 迁移无资金 Provider | Provider 签名和副作用不变；未配置/不可达/超时有稳定错误且不以未知 500 表达正常状态 |
| TPQ46 | 统一 DTO/resource/serializer 与成功/失败 response transformer；Model 不直接承担跨端展示结构，分页和异常共用同一渲染边界 | 未开始 | Sol high | 既有 API 字段和 code/status 精确保持；Controller/Model 不重复拼 envelope 或隐式发查询 |
| TPQ47 | 清点 command/scheduler/worker/callback/event/listener 的注册、重试、幂等和清理责任；只为现有真实消费者使用框架事件/监听器 | 未开始 | Sol high | 所有实际非 HTTP 入口进入 TPQ26 boundary；空 event 配置不被形式化扩张，已有重试/幂等不变 |
| TPQ48 | 在 TPQ12 的 CRUD 模板之外，校准 Module scaffold、示例和开发指南生成入口，并新增 owner/Edition/关系/分页/验证选项的拒绝式校验 | 未开始 | Sol 合同；Luna max 机械模板 | 所有生成入口不重新引入 TPQ01—TPQ47 已退出模式；未声明 owner 的业务表拒绝生成 |

#### E. 门禁、完成定义与模型路由

| ID | 问题与最终交付 | 状态 | 执行 owner | 最低验收 |
| --- | --- | --- | --- | --- |
| TPQ50 | 新增静态架构门禁：Model owner、global scope、禁止手写 tenant_id、禁止直接 bypass、禁止 Logic/Controller 裸 Db/PDO、禁止手工分页、accessor SQL 与特例 Module guard | 未开始 | Luna max 实现；Sol 审批 allowlist | TPQ00 登记的同一规则扫描命中归零或都有 owner/理由/到期条件 |
| TPQ51 | 建立一次开发态聚焦矩阵：SQL query count、global scope SQL、alias/relation、create/bulk write、Module boundary、pagination envelope、异常 renderer | 未开始 | Sol high | 每项真实命中声明分支；不以静态字符串检查替代 Tenant/事务行为 |
| TPQ52 | 使用登记资源完成双 Tenant 对抗和 Standalone 无 Tenant SQL/字段验证；长驻 Worker 验证 context 清理 | 未开始 | Sol high；执行前读取登记并 claim | 两 Tenant CRUD/关联/分页/回调不串数据；Standalone SQL 无 tenant_id；资源零残留 |
| TPQ53 | 全部队列关闭后更新本计划、能力账本（仅稳定能力变化）、开发文档和生成器指南，再恢复 PE01 与双 Edition/发布升级工作 | 未开始 | Sol 主代理 | 本表中 TPQ00—TPQ52 的全部已登记任务关闭；文档、源码、生成器和实际 SQL 事实一致 |

### 2.5 队列关闭规则

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

## 3. 当前 Demo 部署与推荐消费方式

线上 Demo 不是在服务器上克隆移动的 `dev` 或 `main` 分支后直接运行。唯一部署 owner
`scripts/deploy-release` 从 annotated Release tag 生成不可变 `git archive`，校验 SHA-256，
传输到登记的 oracle3 目录，并在部署端构建 Docker Compose 镜像。Multi-tenant Demo 再叠加
带 `base_tag`、`base_commit`、`overlay_commit` 和 SHA-256 的受控 Demo overlay；`v3.0.12`
的 base 与 overlay commit 都是同一个正式 Release commit。overlay 只承担合成 Demo 数据、
Demo 写保护和入口投影，不是另一套生产源码。

推荐入口按用途区分：

- 部署 Peanut Admin：使用不可变 annotated Release/tag 和唯一 `scripts/deploy-release`，不从
  `dev` 部署，也不在服务器上维护一份漂移 clone。
- 创建正式派生应用：从已核验的不可变 Release checkout 运行 `scripts/create-app`；生成后由
  派生应用维护自己的仓库、资源登记和 app-owned 源码。
- 直接 clone Peanut Admin：只用于维护参考应用或取得 Release checkout，不是创建正式下游
  应用的最终入口。

## 4. Demo 审计问题登记

下列结论来自一次真实浏览器批量探索。修复 owner 领取任务时先把相邻现象归并为同一权限、
会话或 UI 边界，再做一次聚焦验证；不得为每条 toast 分别创建修复候选。

| ID | 站点/操作面 | 可见现象与证据 | 当前判定 | 影响 |
| --- | --- | --- | --- | --- |
| DA01 | 共享 Admin 的 Tenant A persona、Tenant A、Tenant B 的可见菜单与按钮 | 共享入口的用户设置、操作日志查询/重置和移动端/Tabbar 装修；Tenant A 的操作日志查询/重置/导出、网站设置、字典、装修与用户设置；Tenant B 的网站设置出现“暂无访问权限” | 高置信；需要按 persona 对齐菜单、按钮和 API permission key | Demo 看起来可用但主要操作失败，直接影响体验可信度 |
| DA02 | Platform → 存储基础设施/路由 | `/api/platform/infrastructure/storage/route` 两次返回 HTTP 500 | 高置信、稳定复现 | Platform 运维页不能可靠表达未配置或错误状态 |
| DA03 | Admin/Tenant → 生产准备清单 | `readiness.items.undefined.title` 在 zh/en-US/en 均缺失 | 高置信、跨共享/Tenant A/Tenant B | 页面出现不完整文案并污染 console |
| DA04 | Admin/Tenant → 装修管理 | `装修管理`、`移动端装修`、`Tabbar 装修`、`PC 装修` 缺少 locale key | 高置信；在共享 Admin/Tenant A 可见 | 导航国际化回退，英文或严格 locale 下体验不完整 |
| DA05 | Platform → 角色/权限复选框 | Element Plus 持续报告 checkbox `label` 作为 value 即将废弃 | 高置信；同一页面重复出现 | 后续 Element Plus 3 升级风险，当前 console 噪声较大 |
| DA06 | Tenant A 长会话中的装修/业务入口 | 后段出现“租户会话不可用”，与前段“暂无访问权限”并存 | 待区分；可能是审计中的会话切换/退出残留，也可能是 Host 会话恢复缺陷 | 未核实前不得作为 Tenant 隔离缺陷或已知 Runtime 故障发布 |
| DA07 | Tenant 审计中的三个无地址快照 | 页面只显示 `404`，现有快照没有 URL/Host，无法归属到 Tenant A/B 或判断是否由刻意访问未知路由产生 | unknown；不作为已确认产品缺陷 | 后续修复验证应记录 URL 与导航来源，无法复现则关闭而不是猜测 |
| DA08 | Platform → 租户与生命周期 → default/Tenant A/Tenant B 详情 | 三个详情动作都伴随“请求失败，请稍后重试”，同时详情对话框仍能展示数据 | 高置信、三 Tenant 一致；具体失败请求 unknown | 成功数据与失败 toast 同时出现，误导 operator 判断操作结果 |

本轮未执行 Tenant 暂停/关闭、清空日志、密码修改、真实 Provider、真实资金、删除和其他不可逆
动作。按钮可见性已纳入检查，但这些动作的成功路径必须在专用可丢弃资源和独立授权下验证。
Tenant B 的登录、文章/分类和文件页面已确认基本可读；共享 Admin 的 bootstrap persona 没有形成
可归属证据，不能用 Tenant A persona 的结果代替。

## 5. 第一阶段：Demo 体验修正

| 顺序 | ID | 任务 | 状态 | 主要交付 | 最低验收 |
| ---: | --- | --- | --- | --- | --- |
| 1 | PE01 | Demo persona 权限与 UI 投影对齐 | 未开始 | 固定 Platform、bootstrap Admin、共享 Admin、Tenant A/B persona 的 menu/button/API permission 矩阵；修正 Demo seed、菜单投影或按钮状态 | 预期可用操作不再出现 DA01；无权限能力隐藏或禁用并解释；Demo 写保护继续由 Demo policy 拒绝破坏性操作，不能靠错误 RBAC 代替 |
| 2 | PE02 | Platform 存储路由与 Tenant 详情错误语义 | 未开始 | 让未配置、配置错误和 Provider 不可达返回稳定可观察状态；消除 Tenant 详情成功数据与失败 toast 并存，不以 500 表达正常未配置 | 同一登记 Demo 上打开并刷新存储页、查看三个 Tenant 详情各一次，无 500 或矛盾 toast，页面状态与后台 readiness 一致 |
| 3 | PE03 | Admin/Platform locale 与组件 API 收敛 | 未开始 | 补齐 readiness/装修导航稳定 key；checkbox 使用 Element Plus 当前 value API | 覆盖本轮对应页面一次，DA03—DA05 不再出现；不顺手升级依赖 |
| 4 | PE04 | Release、Demo 与派生应用入口说明统一 | 未开始 | README、快速开始、部署升级与 Demo handoff 明确 annotated Release、tag archive、Demo overlay、`create-app` 和 clone 的不同用途 | 文档不再把移动分支 clone 写成正式派生应用输入；公开页不泄露密码，交付回复仍提供 owner 授权 Demo 凭据 |
| 5 | PE05 | 修复候选四站点聚焦验收 | 未开始 | 对一个固定修复候选复核 Platform、共享 Admin 两 persona、Tenant A/B 的受影响页面和安全表单 | 受影响路径通过；保留未执行破坏性动作清单。权限/Tenant Runtime 变化按 L2 在正式发布候选只运行一次 P0-E，不在迭代期重复全量 Gate |

PE01 是关键路径。PE02 与 PE03 可在文件 owner 不冲突时并行；PE04 是纯文档独立线。PE05 只在
PE01—PE04 的实际前置满足后执行，不因阶段编号冻结无依赖工作。

## 6. 第二阶段：本仓可继续增强

| 顺序 | ID | 任务 | 状态 | 前置与边界 | 最低结果 |
| ---: | --- | --- | --- | --- | --- |
| 10 | PE10 | 跨 Module 可运行业务示例与新增入口 Guard | 未开始 | CR21 已证明双独立应用签名 Module v1→v2，不重复该资格；本任务只补一个真实跨 Module 业务链，并让后续新增入口消费现有 Module/Tenant/RBAC 合同 | 一个可运行示例证明跨 Module 调用、权限、Tenant 与失败语义；不新建第二套服务层或授权源 |
| 11 | PE11 | 外部回调可信 Tenant 路由 | 未开始 | 先冻结公众号回复等无浏览器 Tenant 会话的可信路由、签名和领域映射；不得从客户端 Host 或未签名字段猜 Tenant | 一个无 Tenant、错 Tenant、重放和合法回调矩阵；合法路径只进入唯一业务 owner |
| 12 | PE12 | T16 部分/多次退款 | 外部阻塞 | 当前 `30+70` 第二笔失败；候选修复是每笔退款独立流水来源号并保留请求级幂等。真实资金不在本任务默认授权内 | 只对登记测试资源重跑失败的 `30+70` 组，两条退款记录和两条余额流水成立；未通过前不宣称支持 |
| 13 | PE13 | 真实 Provider 分项资格 | 外部阻塞 | 邮件、短信、支付、OAuth、微信和 Storage 分别由 Provider owner 提供真实测试资格、凭据引用和副作用授权；PC60 的只读 readiness 不是连通资格 | 每个 Provider 单独记录发送/回调/失败/撤销或清理证据；支付与消息不共用一个笼统“已配置”结论 |
| 14 | PE14 | 第三方业务生产采用 | 外部阻塞 | 需要一个非 Peanut Admin、非合成 Demo 的真实业务 owner、独立资源登记和部署授权 | 从正式 Release 生成的第三方应用完成安装、最小业务、备份/恢复责任和生产 smoke；不把本仓 Demo 冒充该证据 |

## 7. 第三阶段：生态与独立项目

| ID | 事项 | 当前分类 | 恢复条件与归属 |
| --- | --- | --- | --- |
| PE20 | Marketplace | blocked | CR10—CR31 的受控直接分发保持可用；只有 archive SHA-256、受信签名 authority、SBOM、许可证审核、漏洞响应和兼容 authority 完整后另行立项 |
| PE21 | 跨实例运营平台 OP01/OP02 | 本仓范围外；独立项目可立即计划 | 在同级 `peanut-operations-platform` 独立仓、数据库和部署环境推进实例登记、Release、健康、备份证据、签名升级与审计；不得进入 Peanut Admin Runtime/Core |
| PE22 | DCS Product-only 与业务 Module | 本仓范围外；有前置可计划 | 在 DCS 仓先冻结 Tenant 与经营主体映射并取得 D1 业务批准，再实现 Party/Product/Inventory/Procurement 等业务；Peanut Admin 只提供已完成的扩展合同 |
| PE23 | 跨应用身份联邦与通用 Outbox/Event Bus | deferred 设计候选 | 只有两个以上真实消费者提出共同身份或事件需求后冻结协议；当前不为假想消费者扩张 Core |

## 8. 第四阶段：长期 SaaS 与历史暂缓项

| ID | 事项 | 当前分类 | 恢复条件 |
| --- | --- | --- | --- |
| PE30 | 完整 SaaS 商业化：套餐、订阅、试用、续费、配额、计费、支付、发票和收款 | deferred | 至少两个真实应用/实例接入运营平台，完成一次升级、配对备份与恢复/回滚演练，并冻结客户、合同主体、Tenant、套餐和 Entitlement 映射及资金合同 |
| PE31 | 限时 SupportSession/跨租户客服代运营 | deferred | 有真实支持场景和客户授权后，冻结精确能力、到期、撤销和双边审计；PlatformOperator 永远不直接获得租户业务读权限 |
| PE32 | 父子 Tenant、集团权限继承、每 Tenant 独立数据库、客户业务分析 | deferred 设计候选 | 分别出现真实组织、隔离或分析需求后独立立项；不得把它们作为现有多租户 Runtime 的隐式承诺 |
| PE33 | 远程入口/SSH 托管 | out_of_scope | 实例默认主动出站；SSH 不作为公共管理协议。只有独立安全设计和用户授权后才能评估，不并入 OP01/OP02 默认范围 |

## 9. 不恢复的边界与条件性范围外事项

- 自动重构、静默覆盖或双写 app-owned 业务源码永久禁止；确需源码迁移时另建显式、可审阅、
  可恢复的迁移工具。
- 1.x 数据库/scaffold 原地 adopt、长期双 Runtime、长期双字段和兼容镜像不恢复；3.0 保持
  fresh-only。历史 1.x 证据只用于追溯。
- 超级管理员读取全部租户业务、每租户业务表、把 Ops Platform 嵌入 Core/SaaS Host 均不恢复。
- 预构建生产镜像保持范围外；只有另批容器 Registry、签名、SBOM 和供应链发布合同后，才从
  `out_of_scope` 转为 `planned`。当前继续从不可变 tag 在部署端构建。

## 10. 领取、验证与状态同步

1. 每个任务开始前重新核对 `origin/dev`、开放 PR、worktree、租约和文件 owner；不得依赖本计划
   的历史 commit。
2. `PE01—PE05` 可作为一个 Demo 体验批次进入 `feat/* -> dev`；不同安全/Schema/Provider 停止线
   才拆分 PR。后续任务按独立业务结果分批，不以 PR 数量作为目标。
3. 普通任务只运行一次受影响的 lint、静态检查和聚焦验证。Tenant、权限、Schema、支付、部署
   变化增加对应安全 Gate；完整 P0-E 只在一个冻结的 L2 发布候选运行一次。
4. 任务完成时更新本计划；只有稳定能力真实变化时才更新 capability ledger 及生成投影。开放 PR、
   未提交证据或浏览器探索不得提前写成完成。
5. deferred、blocked 或 out-of-scope 项恢复前，必须补齐目标 Release、直接依赖、资源登记、owner、
   副作用授权和最低验收。无法满足时只阻塞该项，不冻结无依赖任务。
