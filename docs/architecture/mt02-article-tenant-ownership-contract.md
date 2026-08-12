# MT02 Article Tenant 所有权与 Tenant-first Runtime 合同

> 任务：`MT02-ARTICLE-TENANT-001`
>
> 状态：合同冻结，最终 MT02 Gate 仍等待真实依赖
>
> 应用 owner：Peanut Admin Content Module

## 1. 目标与边界

本切片把既有 Article 产品 Runtime 改为单代码线的 Tenant-first Runtime，不复制
Standalone/SaaS Service，也不借用 CAP06 的单默认 Tenant 顺序消费证据：

- `pa_article` 与 `pa_article_cate` 是 `tenant-owned`；
- `pa_article_collect` 由会员与 Article 关系产生，是 `tenant-derived`；
- 三表均保存 `tenant_id BIGINT UNSIGNED NOT NULL`，不使用 `0`、`NULL` 或客户端字段
  表示平台、默认 Tenant 或通配 Tenant；
- 管理端、公开 API、登录会员 API、PC/首页聚合、收藏计数和装修 Article 引用必须
  通过同一个 Article Tenant adapter/repository 访问，不允许直接无 Tenant 查询；
- 本切片不创建 Tenant/Account/TenantMember，不修改安装器、管理员/RBAC 映射、Core、
  Generator、Generated Host、Admin Web、发布工作流、manifest/lock 或其他 MT 阶段。

阶段编号不冻结本独立切片。依赖分级并行由应用 PR #32 / 决策提交
`62d2a1232afdef878f48c0da8f0678f263cf4537` 授权；本合同和实现可独立进入 `dev`，
但不得据此声明 MT02 完成或执行最终跨阶段 Gate。

## 2. Schema owner、回填与约束

唯一 schema owner 是本切片新增 migration；不得修改 `server/database/init.sql` 或
`server/database/install.php`。

迁移采用一次 expand/backfill/verify/contract：

1. 前置要求 `pa_tenant` 已存在，且全库恰有一个 `status='active'` 的 Standalone
   Tenant；否则在任何 Article 行变更前失败关闭。不得按最小 ID、固定 `1` 或请求参数猜测。
2. 三张既有表先增加 nullable `tenant_id`，统一回填该唯一 Tenant，再验证无 NULL、0
   和跨表 tenant 不一致，最后收紧为 `NOT NULL`。
3. `pa_article` 建立 `UNIQUE (tenant_id, id)`，并建立覆盖公开列表/状态/分类读取的
   `(tenant_id, is_show, cid, sort, id)` 索引。
4. `pa_article_cate` 建立 `UNIQUE (tenant_id, id)`，并建立
   `(tenant_id, is_show, sort, id)` 索引。
5. `pa_article_collect` 的原 `UNIQUE (member_id, article_id)` 改为
   `UNIQUE (tenant_id, member_id, article_id)`，并建立
   `(tenant_id, member_id, status, id)` 索引。
6. 分类和收藏到 Article 的关系必须同时比较 `tenant_id`；若本切片无法在不抢占
   Tenant/Member schema owner 的情况下安全增加复合外键，以迁移内一致性验证和受控
   repository 强制同 Tenant，外键收紧留给对应 schema owner，不伪造已完成。

迁移不提供长期 nullable、双读或双写兼容层。回滚方式是迁移前数据库备份；已写入多
Tenant 数据后不得机械删除 `tenant_id`。

## 3. 可信上下文与拒绝形状

登录态 Article Runtime 只接受上游通过 Core 会话校验后注入的
`PeanutAdmin\Kernel\Auth\TenantContext`。既有匿名公开 Article 读取接受 Core
`TenantSystemContext`，但只能由 allowlisted public-read actor、服务端固定 Tenant code 和
`SystemContextFactory` 解析 active Tenant 产生；客户端 host/header/参数不能直接成为该
Context。Article controller/logic/repository 不从以下输入建立或改写 Tenant：请求
参数/header、Article/Category/Collect 行、管理员 ID、旧会员 ID、最小 Tenant ID 或
“库里只有一个 Tenant”的运行时猜测。

请求入口通过 Article 专属 context adapter 取得上下文；对象缺失、类型错误，登录态
`tenantId/accountId/memberId/requestId` 不可信，或匿名 system actor/operation/operationId
不在固定 allowlist 时默认拒绝。受保护入口不得退化为 TenantSystemContext；公开读取也
不得制造伪 TenantMember 会话。

按 ID 聚焦的 Article 详情、编辑、删除、状态、收藏、装修链接验证与 CAP06 typed-target
授权，在“目标不存在、已软删/不可见、属于其他 Tenant”时使用同一不可枚举拒绝形状。
跨 Tenant fixture 必须保持权限策略为允许，并由 repository 的
`tenant_id = context.tenantId` 可见性失败产生拒绝；禁止用 `permitted=false` 冒充隔离。

## 4. Tenant-first 路径清单

以下既有 Article 读取或写入全部在范围内，不能只改主列表：

| 路径 | 必须成立的不变量 |
| --- | --- |
| 管理 Article 列表/详情/新增/编辑/删除/状态 | 查询先绑定 Tenant；写入 tenant 只取 Context；ID 变更按 Tenant 聚焦 |
| 管理 ArticleCate 列表/详情/新增/编辑/删除/状态 | 分类同样 tenant-owned；占用检查与批量 Article 查询同时比较 Tenant |
| 校验器 Article/Category 存在性 | 只验证当前 Tenant 行，异租户与不存在同形 |
| API Article 列表/详情/浏览计数 | 公开状态与 tenant 条件同一查询；点击更新不能越 Tenant |
| 收藏新增/取消/判断/列表 | collection、Article join 和唯一键均含 Tenant；会员参数不能改变 Tenant |
| PC info center/detail/前后篇/最新/热门 | 分类、文章与邻接查询保持当前 Tenant |
| 首页 Article、用户收藏计数 | 聚合和 count 同时限定 collection/article Tenant |
| 装修 Article options 与 schema link | 只允许引用当前 Tenant 的可见 Article |
| CAP06 Article typed-target adapter | 权限允许时仍由当前 Tenant Article 可见性决定，异租户与缺失同形 |

Article/Cate/Collect model 不增加“无上下文返回全部”的全局 scope。所有调用者显式传递
可信 Context，受控 repository 负责构造 tenant predicate、tenant 写入值和同租户 join。

## 5. 实现白名单

合同提交只修改本文件。实现提交仅允许：

- `server/database/migrations/*article_tenant_ownership.sql`；
- `server/app/common/model/article/{Article,ArticleCate,ArticleCollect}.php`；
- `server/app/common/service/article/*`；
- `server/app/common/service/capability/ArticleCapabilityAuthorization.php`；
- `server/app/adminapi/controller/article/*`、`server/app/adminapi/logic/article/*`、
  `server/app/adminapi/validate/article/*`；
- 直接承载上述清单的既有 API/首页/PC/用户/装修 controller、logic 与
  `DecorationSchemaService.php`；
- `server/tests/Multitenancy/ArticleTenantIsolationTest.php`；
- `.github/workflows/ci.yml` 中登记该唯一测试的最小行；
- 本合同的结果区。

若实现必须修改安装器、默认 Tenant/Account/TenantMember、管理员/RBAC、Core、
`init.sql`、manifest/lock、发布 workflow 或其他阶段 owner 文件，停在精确冲突，不抢文件。

## 6. 测试 owner 与最低验收

`MT02-ARTICLE-TENANT-001` 的唯一行为 owner 是
`server/tests/Multitenancy/ArticleTenantIsolationTest.php`。测试使用独立 MySQL 8.4
临时库，显式加载现有基线 schema、测试所需最小 Tenant fixture 与本 migration，并在
`finally` 删除自己的临时库。它一次证明：

1. 旧 Article/Category/Collect 行全部回填唯一 active Tenant，三列为 NOT NULL，必要
   复合唯一和索引存在；第二次执行受迁移账本保护，不产生第二套 schema。
2. Tenant B 的 Article `id=X,is_show=1` 与 Tenant A 的可见 Article 保持相同发布状态；
   Tenant A 的可信 Context 以同一个 `id=X` 聚焦详情、写入、收藏、装修引用和 CAP06
   typed-target 时均与缺失对象同形拒绝（共享表全局主键不伪造重复物理 ID）。
3. 跨 Tenant 场景保持 permission policy 为允许，拒绝来源是 Article Tenant-first
   repository；拒绝前后异租户 Article、点击数和收藏行不变。
4. 当前 Tenant 正向 CRUD、可见列表/详情/点击、分类占用、收藏唯一/取消、首页/PC 聚合
   与装修选择仍返回既有业务形状。
5. 缺失或伪造 Context 在任何 SQL 读写前拒绝；payload 中 `tenant_id` 不能覆盖 Context。

只执行一次最低验证，PHP 必须显式使用 8.3：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Multitenancy/ArticleTenantIsolationTest.php
```

同次运行可包含白名单 PHP lint 与 migration/schema 断言；不重复 CAP01-CAP06、CAP06
MySQL Gate、全量浏览器或完整多租户 Gate。若最低验证失败，只做一次只读诊断后停止。

## 7. 完成声明停止线

本切片只有在合同与实现 PR 各自全部声明检查 `COMPLETED/SUCCESS` 且人工合入 `dev`
后，才可声明“Article tenant ownership 与 Tenant-first Runtime 实现已合入”。它仍不等于：

- MT02 整体完成；
- 默认 Tenant/Account/TenantMember/owner bootstrap 或管理员/RBAC 映射完成；
- 所有业务表、缓存、文件、任务、审计或前端完成多租户隔离；
- MT01 最终候选、Registry 身份或 MT02 最终集成 Gate 已通过。

最终 MT02 Gate 与阶段完成声明交由主控，必须使用当时真实依赖与整体验收证据。
