# MT02 默认 Tenant bootstrap 与既有管理员/RBAC 映射合同

> 状态：Accepted for implementation
>
> 应用前置提交：`ab741cb3267f48a0cbdac30e9708c5a2d1a2146f`
>
> Core 公共 API 固定输入：Composer split
> `ef06da45c9e77ae4b194bfc1f859ec007aa0e022`
>
> 测试 owner：`MT02-BOOTSTRAP-001`

## 1. 目标与停止线

本切片只负责 Standalone 安装和既有单租户实例前滚时的默认 Tenant、Account、
TenantMember、首个 owner，以及既有管理员、角色、部门、岗位的确定性租户映射。
它消费 Core 的唯一 Tenant Runtime，不复制 Tenant/Account/TenantMember/RBAC Service。

本切片不改变现有管理端登录、CRUD、权限字符、页面或可见业务结果。旧管理端继续
作为兼容 Host，映射结果为后续统一认证和 Tenant-aware CRUD 提供单一迁移事实源。
Article、其他业务表租户化、TenantContext 中间件、缓存/文件/任务隔离、平台管理面、
管理端 Tenant UI、Generator、Generated Host、依赖 manifest/lock 和发布流程均不在
本切片内。通过本合同及实现不构成 MT02 Gate 或阶段完成声明；最终 Gate 仍等待真实
MT01 固定候选和其余 MT02 owner 的集成证据。

## 2. Core 公共合同与 schema owner

只消费锁定 Composer split 已提供的公共类：

- `PeanutAdmin\Kernel\Persistence\Schema\KernelSchema`：按其公开顺序创建 Core
  所有表，并追加公开的 TenantMember→Department 外键；应用不得复制建表 SQL；
- `PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService`：创建 Platform bootstrap
  owner、默认 Tenant、首个 owner candidate，并依次激活 owner 和 Tenant；
- Core 的 PDO Repository、`PasswordHasher`、`TransactionManager`：复用 Account、
  TenantMember、Role、Tenant、Audit 的状态机和事务；
- Core `RoleAdminService`、`DepartmentAdminService`、`MemberAdminService` 可用于其
  公共 API 能完整表达的写入；批量迁移适配器只能补充旧模型映射与状态投影，不能
  建立第二套业务 Service。

Core 拥有 `pa_account`、`pa_credential`、`pa_tenant`、`pa_tenant_member`、
`pa_department`、`pa_role`、`pa_member_role`、平台身份和审计等 Kernel schema。
应用拥有旧 `pa_admin`、`pa_system_role`、`pa_dept`、`pa_jobs` 及关系表、其
`tenant_id` 扩展、三个 legacy→Core 映射表和 bootstrap 状态记录。Core 当前没有
Position/Job 公共模型；岗位不得伪装成 Role 或 Department，继续由应用表拥有。

## 3. 确定性映射

默认 Tenant 固定为 `code=default`、`name/display_name=Peanut Admin`、
`locale=zh-CN`、`timezone=Asia/Shanghai`。既有实例必须恰有一个未删除且启用的
`root=1` 管理员；按 `id` 升序取得唯一结果作为首个 owner。零个或多个候选均拒绝。

| 旧事实 | Core/应用投影 |
| --- | --- |
| 首个 root 管理员 | Standalone 空库和 v1.0.0 前滚的兼容投影中，同一个 Account 同时得到 PlatformOperator 和默认 Tenant 的 active owner TenantMember；平台会话仍不隐式提供 Tenant 权限 |
| 其他管理员 | 每个旧 `pa_admin.id` 一个 Account 和一个 TenantMember；`disable=0` 且未删除映射 active，否则映射 suspended/left；不创建可猜测凭据 |
| 角色 | 每个旧角色映射一个 `pa_role`，key 固定为 `legacy.role.<旧 id>`；名称、描述和删除状态确定性投影 |
| 管理员角色 | 映射到同 Tenant 的 `pa_member_role`；root owner 额外持有唯一内置 `core.tenant-owner` |
| 部门 | 每个旧部门映射一个 `pa_department`，code 固定为 `legacy.dept.<旧 id>`；先父后子保持树、排序和启停/删除状态 |
| 管理员部门 | 旧多部门关系完整保留在应用关系表；最小有效 `dept_id` 成为 Core `primary_department_id` |
| 岗位 | `pa_jobs` 与 `pa_admin_jobs` 增加并回填非空 `tenant_id`；关系不映射成 Core 授权对象 |

三个映射表以 `(tenant_id, legacy_id)` 为唯一旧端身份，并分别唯一约束 Core
`account_id/member_id`、`role_id`、`department_id`。旧主表及关系表均增加非空
`tenant_id` 和必要的 `(tenant_id, id/关联键)` 索引；不得使用 `tenant_id=0/NULL`
表达平台或未知归属。

MT05 集中验收发现，把上述同 Account 兼容投影继续用于 `multi-tenant` 空库安装会让
实例平台身份同时成为默认 Tenant owner，违反 PlatformOperator 不成为 TenantMember
的边界。该行为已作为 MT05 blocker 修正合同：多租户空库必须使用独立的
`PLATFORM_INITIAL_EMAIL`/`PLATFORM_INITIAL_PASSWORD` 创建唯一 active
PlatformOperator；其 Account 必须不同于 `ADMIN_INITIAL_*` 创建的默认 Tenant owner，
且不得有任何 TenantMember。Standalone 空库和 v1.0.0 前滚继续保持本节原有兼容行为。

## 4. 凭据与失败关闭

安装/升级必须显式提供 `ADMIN_INITIAL_EMAIL` 与既有 `ADMIN_INITIAL_PASSWORD`。
adapter 先按旧 `pa_admin.password/salt` 算法验证密码确实属于首个 root，再把同一明文
交给 Core `PasswordHasher`/bootstrap 创建 email credential；旧 hash 不复制进 Core。
邮箱无效、密码不匹配、输入缺失、旧凭据歧义或已有 Core credential 冲突均拒绝，
错误不得回显 hash、盐、密码或其他账号存在性。

`DEPLOYMENT_MODE=multi-tenant` 的空库安装还必须显式提供
`PLATFORM_INITIAL_EMAIL` 与 `PLATFORM_INITIAL_PASSWORD`，不得回退复用
`ADMIN_INITIAL_*`。平台输入只创建平台 Account/Credential/PlatformOperator；Tenant
owner 输入只创建默认 Tenant owner Account/Credential/TenantMember。Standalone 与
v1.0.0 前滚不消费平台初始凭据，保持现有兼容路径。

非 owner 管理员只创建无凭据 Account 和 TenantMember，仍走旧登录链；不得把 username
猜成邮箱、生成共享密码或建立 `@invalid` 凭据。统一登录切换归后续明确合同。

以下任一情况必须在映射写入前失败：Core schema 与锁定 `KernelSchema` 不一致；已有
非默认 Tenant/PlatformOperator；默认 Tenant/owner 状态不完整；旧关系存在孤儿；
部门缺父、成环或超过 Core 深度；同一旧对象出现不一致映射；既有 bootstrap 状态的
Core source/schema digest 与当前输入不同。

## 5. 回填、事务、幂等与恢复

Core schema 创建使用数据库安装/迁移锁并逐表核对：目标表不存在时使用公共
`KernelSchema` 创建，存在时必须与记录的 schema identity 一致，禁止静默采用相似表。
应用 SQL migration 只负责应用拥有的列、索引、外键和映射/状态表。

空库安装顺序固定为：确认空库和输入 → 创建 Core schema → 执行应用 SQL → Core
bootstrap 默认 Tenant/owner → 映射旧管理员/RBAC → 品牌 seed → 记录 migration 和
bootstrap identity → 完整性断言。既有实例顺序固定为：账本/输入/旧数据预检 → 创建或
核对 Core schema → 应用本切片 migration → bootstrap 与映射 → 完整性断言。

bootstrap 与映射在一个应用事务内执行；Core 的嵌套事务必须使用其公开 savepoint
行为。SQL migration 的 DDL 可能隐式提交，因此实现必须在 DDL 前完成全部可读预检，
DDL 后任一 bootstrap 失败都保持 migration ledger 为 failed，禁止报告成功；重试只可
在相同输入和相同 schema identity 下继续。成功重跑必须只验证完整映射并返回
`already_bootstrapped`，不得创建第二个 Tenant、Account、Member、Role 或 Department。

完整性断言至少包含：恰好一个 active 默认 Tenant、恰好一个 active owner、每个旧
管理员/角色/部门各一条确定映射、岗位及旧关系 `tenant_id` 全部等于默认 Tenant、
Core member role/primary department 与旧关系一致、无跨 Tenant 或孤儿引用。

## 6. 精确白名单

合同提交只允许新增本文。

实现提交只允许修改：

- `server/database/migrations/20260812-default-tenant-bootstrap.sql`；
- `server/app/common/service/tenant/DefaultTenantBootstrap.php`；
- `server/database/install.php`、`server/database/migrate.php`；
- `server/tests/fixtures/mt02/default-tenant-upgrade.sql`；
- `server/tests/Productization/DefaultTenantBootstrapTest.php`；
- `.github/workflows/ci.yml`，仅登记 `MT02-BOOTSTRAP-001` 聚焦组；
- 本文的实施证据段。

禁止修改 `server/database/init.sql`、Article 任一 model/logic/controller/validator/schema、
`docs/likeadmin-parity-report.md`、Core 仓、Generator、Generated Host、Admin Web、
manifest/lock、发布 workflow、MT03、PM01、MT04 和 CAP01–CAP06 Gate 文件。白名单不足
或与其他 owner 重叠时先停止并修订合同，不得扩写。

## 7. 唯一最低验证

`MT02-BOOTSTRAP-001` 由一个 PHP 8.3 命令拥有，使用唯一 MySQL 8.4 数据库并覆盖：

1. 空库安装得到一个 default Tenant、Account、active owner 和完整 root 映射；
2. 带额外管理员、角色、两级部门、岗位及关系的既有 fixture 确定性前滚；
3. 成功后重跑为 `already_bootstrapped` 且所有计数不变；
4. 缺 email、旧密码不匹配、第二 root、孤儿关系和部门环分别在任何 Core 映射写前
   fail closed；日志和输出不含密码/hash/salt；
5. 应用 migration ledger、Core schema identity、外键、非空 tenant ownership 和映射
   完整性均成立，Standalone 旧管理员/RBAC 数据与关系计数不变。

固定命令为：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/DefaultTenantBootstrapTest.php
```

该组只运行一次；PR 的既有 PHP 8.3 静态/合同组和其他声明检查负责普通兼容性，不重跑
CAP、Article、全量浏览器、生产、Generator 或 MT02 整体验收。失败后只做一次只读诊断
并按根执行规则停止。

## 8. 资格声明

实现 PR 全部声明检查成功并合入 `dev` 后，只能声明本独立切片
development-complete。MT01 固定候选、Article owner、Tenant-aware Runtime、真实升级矩阵
及最终 MT02 Gate 未共同完成前，不得声明 MT02 complete、qualified 或 release-ready。

## 9. 实施证据

- 实现分支基线为合同合入后的 `dev` `1107c10351a9d57fb34c0f135dd3f979efb5a79e`；
  Core 公共 API 保持锁定 Composer split `ef06da45c9e77ae4b194bfc1f859ec007aa0e022`，
  未修改 manifest/lock 或 Core 仓。
- `20260812-default-tenant-bootstrap.sql` 只拥有应用旧管理员/RBAC/岗位的 Tenant
  ownership、映射表和 bootstrap identity；Core 表全部由公共 `KernelSchema` 建立。
- `DefaultTenantBootstrap` 复用 Core `BootstrapService`、PDO Repository、
  `PasswordHasher` 与嵌套 transaction/savepoint；未知/部分 Core 状态、旧关系孤儿、
  部门环、root 歧义和不可信 owner credential 均 fail closed。
- `MT02-BOOTSTRAP-001` 使用 `/opt/homebrew/opt/php@8.3/bin/php` 和 MySQL 8.4
  唯一运行一次并通过：真实空库 installer、三管理员/两角色/两级部门/岗位 fixture
  前滚、幂等重放，以及缺 email、错密码、第二 root、孤儿关系和部门环拒绝。
- 未运行 Article、CAP01–CAP06、全量浏览器、生产、Generator 或 MT02 整体验收；
  本结果仅为独立 bootstrap/RBAC mapping 切片 development-complete 候选。
