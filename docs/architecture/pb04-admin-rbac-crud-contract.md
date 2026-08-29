# PB04-01 管理员与 RBAC CRUD 收口合同

> 状态：Accepted
>
> 应用前置提交：`c0861382e48bcdb0b83ab584d8cd13e5ae208707`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB04-AUTH-CRUD-001`

## 1. 决策与唯一所有权

本切片冻结 Peanut Admin 单租户管理员、角色、部门、岗位、菜单 CRUD。它们是应用产品 Host 的唯一实现，不迁入核心 Tenant Runtime：

- 应用拥有 ThinkPHP Controller/Validate/Logic、`pa_admin`、`pa_system_role`、`pa_system_menu`、`pa_dept`、`pa_jobs`、关系表、会话失效和 LikeAdmin 兼容字段。
- 核心只提供已经消费的精确权限集合原语；本切片不新增核心 interface、DTO、override slot 或发布版本。
- 管理端页面与 `web/src/api/system/{admin,role,menu,dept,jobs}.ts` 继续由应用拥有；不存在第二套领域 Runtime。
- 数据与迁移 owner 是应用仓。本切片不改变 schema；若后续增加唯一键、外键或并发约束，只能新增 `server/database/migrations/YYYYMMDD-*.sql`。

## 2. API、权限与 audience

全部写接口仅面向已登录管理端管理员，并经过 `LoginMiddleware → AuthMiddleware → OperationLogMiddleware`。权限字符沿用已封存契约：

- 管理员：`admin/add|edit|delete`，`admin/status` 复用 `admin/edit`；
- 角色：`role/add|edit|delete`；
- 部门：`dept/add|edit|delete`，`dept/status` 复用 `dept/edit`；
- 岗位：`jobs/add|edit|delete`，`jobs/status` 复用 `jobs/edit`；
- 菜单：`menu/add|edit|delete`，`menu/status` 复用 `menu/edit`。

`dept/status` 与 `menu/status` 必须进入固定 alias，不能因“未登记 URI 放行”成为权限旁路。前端按钮只改善交互，服务端 API 授权仍是安全边界。

## 3. 业务不变量

| 聚合 | 必须保持的不变量 |
|---|---|
| 管理员 | 活跃账号/名称唯一；非 root 至少一个有效角色；角色/部门/岗位必须存在；禁止删除或禁用 root；禁止当前管理员删除或禁用自己；角色或禁用状态变化使全部会话失效 |
| 角色 | 活跃名称唯一；授权菜单必须存在；被管理员使用时不可删除；删除角色与其菜单关系原子完成；编辑时空 `menu_id` 继续表示“不修改授权” |
| 部门 | 活跃名称唯一；上级存在且启用；禁止自身/后代成环；顶级、有下级或被管理员使用时不可删除；`status/is_disable` 同步 |
| 岗位 | 活跃名称和编码唯一；被管理员使用时不可删除；`status/is_disable` 同步；软删后名称/编码可复用 |
| 菜单 | 上级存在且按钮不能成为上级；禁止自身/后代成环；不存在、仍有下级或仍被角色授权时不可删除；详情/删除/状态必须校验 ID；状态接口复用编辑权限 |

名称/账号等唯一性继续按当前未删除记录范围由应用校验；本切片不伪造数据库级串行化保证。发现需要并发唯一键或外键时必须另立 schema 合同，不能在本合同内修改 `init.sql`。

## 4. 事务、错误与审计

- 管理员新增/编辑/删除/状态：管理员行、三类关系和会话失效保持现有事务边界；编辑、删除与状态锁定目标管理员。
- 角色新增/编辑/删除：角色与菜单关系同事务；删除时在事务内重新检查管理员占用并锁定角色。
- 部门和岗位写操作：保持现有事务；删除在事务内检查层级/管理员引用，岗位编辑/删除/状态锁定目标行。
- 菜单新增/编辑/删除/状态：全部进入事务；编辑/删除/状态锁定目标行；删除不再级联抹掉授权，而是在有子菜单或角色引用时返回明确业务错误。
- 失败必须回滚并通过现有 `40000` envelope 返回 Logic 错误；不存在的详情、删除和状态不再伪成功。
- 成功写请求仍由 `OperationLogMiddleware` 记录并沿用其敏感字段脱敏；本切片不扩大日志模型。

## 5. 精确写集与禁改集

Runtime 白名单：

- `server/app/adminapi/service/AdminPermissionService.php`；
- `server/app/adminapi/controller/auth/MenuController.php`；
- `server/app/adminapi/application/auth/MenuApplicationService.php`、`RoleApplicationService.php`；
- `server/app/adminapi/validate/auth/MenuValidate.php`。

证据与状态白名单：

- `server/tests/Productization/AdminRbacCrudTest.php`；
- 本合同、`docs/architecture/pb03-ownership-and-migration-gates.md`、`docs/architecture/pb04-permission-host-contract.md`、`docs/productization-baseline-plan.md`、`AGENTS.md`。

禁止修改核心仓、`vendor/`、`node_modules/`、数据库 schema/seed、登录/JWT/密码算法、现有权限字符、路由 envelope、前端页面，以及会员、内容、支付、通知等其他领域。

## 6. 测试 owner 与一次最低验收

`PB04-AUTH-CRUD-001` 由 `server/tests/Productization/AdminRbacCrudTest.php` 拥有，使用应用当前数据库连接创建唯一前缀临时记录，并在 `finally` 中按主键物理清理。一次运行只证明：

1. 部门、岗位、菜单、角色、管理员的关系可建立；
2. 自删/自禁、root、角色/部门/岗位引用、菜单子级/角色引用删除边界成立；
3. 菜单自身/后代父级被拒绝；
4. 四个轻量状态 URI 都固定复用对应编辑权限；
5. 清理后临时主表、关系表和会话计数均为零。

执行命令固定为：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/AdminRbacCrudTest.php
```

实现 owner 另运行一次变更 PHP lint 和一次最终 `git diff --check`。不重跑 LikeAdmin parity、全量浏览器、多角色矩阵、PB04 权限 Host 或网站设置验收。

## 7. 停止线

通过只表示应用管理员/RBAC CRUD 的 owner、关键写不变量、事务边界和测试 owner 已固定。它不表示核心 Tenant 身份/管理员 Runtime 已被下游采用，不授权修改核心，不引入 SaaS、数据权限或新 schema，也不开始 PB04 字典、文件、任务和运维切片。

## 8. 实施证据

- CodeGraph 与两组独立只读枚举确认五组 CRUD 只有应用 Logic/Model/API/Page 一套实现；核心只在权限集合原语处被消费。
- PHP 8.3 对白名单 Runtime 与测试文件的一次聚焦 lint 全部通过。
- `PB04-AUTH-CRUD-001` 一次通过：临时五域关系创建、root/自操作/引用/层级拒绝、状态 alias 和逆序清理均成立。
- 测试 `finally` 物理清理临时主表、关系表和会话；按随机账号、角色、部门、岗位编码、菜单权限字符复核均为零。
- 未重跑 `PB04-AUTH-HOST-001`、LikeAdmin parity、全量浏览器或其他 PB04 领域验收；未修改核心仓或数据库 schema。
