# PB04-01 管理端权限 Host 收口合同

> 状态：Implemented，待提交
>
> 应用前置提交：`7d27a48938fe464ad89082d2652cc3acfdd84a60`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB04-AUTH-HOST-001`

## 1. 目标与所有权

本切片固定现有单租户管理端权限 Host，不迁核心 Tenant schema：

- 应用拥有管理员、角色、菜单表、LikeAdmin URI 登记/别名语义和 ThinkPHP middleware。
- 核心 `EffectivePermissionSet` 与 Web `hasPermission` 只提供精确权限集合原语。
- PHP override slot 固定 `authorization.permission.service.policy@1.0.0`；Web slot 固定 `authorization.permission.service.evaluator@1.0.0`。
- API 唯一链是 `AuthMiddleware → AdminPermissionService::canAccess → CoreServiceOverrides → RegisteredAdminPermissionPolicy`。
- 按钮唯一链是 `hasPermission → permissionEvaluator`；指令和页面不得复制判断规则。

目标是为两条链建立应用侧可执行测试 owner，并把前端 any-of、root wildcard 与空要求规则提取为可测试的纯函数。后端现有单一策略无需重写。

## 2. 固定语义

后端：

1. `root=1` 放行。
2. URI 去除首尾 `/` 并小写，再应用固定 alias。
3. 只有启用菜单中已登记的非空 `perms` 参与 RBAC；未登记 URI 按已封存 LikeAdmin 1.9.4 语义放行。
4. 已登记 URI 只有在角色拥有的启用菜单权限集合中才放行。
5. 缺少登录上下文返回现有 `40100`；权限拒绝返回现有 `40300`。

前端：

1. 空权限要求放行。
2. 登录响应的 `permissions=['*']` 放行任意非空要求。
3. 多个要求是 any-of，不是 all-of。
4. 普通集合通过核心 evaluator 做精确匹配；请求 `*` 本身不成为绕过。
5. 服务端菜单仍决定路由可见性；前端按钮判断不是安全边界。

## 3. 非目标

- 不修改登录、JWT、session、密码、管理员/角色/部门/岗位 CRUD。
- 不修改菜单 seed、权限字符、alias 列表、路由或响应 envelope。
- 不引入 Tenant、数据权限、默认拒绝或超级管理员新字段。
- 不修改核心仓、vendor、node_modules、包版本、锁文件或依赖。
- 不重复 parity、全量浏览器或多角色矩阵。

## 4. 精确写集

- `server/tests/Productization/AdminPermissionHostTest.php`；
- `web/src/core/permission-policy.ts`；
- `web/src/hooks/permission.ts`，仅委托纯策略；
- `web/tests/Productization/permission-policy.test.ts`；
- `.github/workflows/ci.yml`，仅登记两项聚焦测试；
- 本合同、`docs/architecture/pb03-ownership-and-migration-gates.md`、`docs/architecture/core-application-capability-graph.md`、`docs/productization-baseline-plan.md`、`AGENTS.md`，仅更新状态。

不得修改 `AdminPermissionService`、`RegisteredAdminPermissionPolicy`、`CoreServiceOverrides`、middleware、Controller、Model 或数据库。发现现有语义错误时停止并另立 Runtime 合同。

## 5. 测试所有权与最低验收

PHP 聚焦测试必须证明 root、未登记放行、登记未授权拒绝、登记已授权放行、alias、大小写/斜杠标准化，以及默认 PHP override resolution 的 key、版本、实现和来源。

Web 聚焦测试必须证明空要求、root wildcard、单项、多项 any-of、拒绝和请求 `*` 不绕过。测试只编译纯函数与测试文件到临时目录，不写仓库产物、不新增 runner 依赖。

实现 owner 各运行一次 PHP/Web 聚焦测试、一次变更 PHP lint/TypeScript no-emit 和一次最终 `git diff --check`。随后执行一次只读数据库 Host 探针：无角色非 root 对一个已登记权限拒绝、对一个未登记 URI 放行、root 放行、真实 root 菜单非空且按钮权限为 `['*']`。

## 6. 停止线

通过只证明管理端权限 Host 与前端按钮策略有唯一调用链和应用测试 owner。它不证明登录/账户全域迁移，不改变安全模型，不授权核心多租户 Runtime 消费，不部署、不发布，也不进入 PB05。

## 7. 实施证据

- CodeGraph 限定图谱确认 API 与按钮各只有一条运行链，未发现第二个应用权限 evaluator。
- PHP 8.3 下 `PB04-AUTH-HOST-001` 通过：兼容语义、alias 和默认 override resolution 均有覆盖。
- Web 纯策略临时编译/行为测试通过：空要求、root wildcard、any-of、拒绝和请求 wildcard 边界均有覆盖。
- 新增 PHP 文件 lint 通过；Web 测试使用仓内 TypeScript compiler，不新增依赖或产物。
- 只读数据库 Host 探针一次通过：non-root 已登记拒绝/未登记放行、root 放行、真实 root 菜单非空且按钮权限为 `['*']`。
- 本机默认 PHP 8.1 无法解析核心包的 `readonly class`；最终 PHP 证据来自项目要求的 Homebrew PHP 8.3，未修改 vendor 或测试规避。
