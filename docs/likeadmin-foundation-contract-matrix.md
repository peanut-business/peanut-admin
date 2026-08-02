# LikeAdmin 基础权限域契约矩阵

> 覆盖范围：登录/会话/权限、管理员、角色、菜单、部门、岗位  
> 状态：P01 盘点中；本文件是后续 A02、A03、O01—O05 的权威实施输入。

## 1. Peanut Admin 后端实现约束

后续复刻必须沿用 Peanut Admin 的 ThinkPHP 8 方向：

```text
route/app.php
  → adminapi/controller 薄控制器
  → adminapi/validate 场景验证
  → adminapi/logic 业务逻辑与事务
  → common/model 数据模型
  → common/service 跨领域能力
```

统一约束：

- 管理 API 使用 `/api/admin` 前缀，并依次经过登录、权限、操作日志中间件；
- 成功/失败保持 `{code,msg,data}`，分页保持 `{lists,count,pageNo,pageSize}`；
- 输入规则写入 Validate 场景，不在控制器堆叠业务判断；
- 多表变更必须在 Logic 中使用事务；
- 模型继承现有 `BaseModel`，时间字段保持整型时间戳；
- 业务扩展优先新增服务、模型和关系，不照搬参考项目的框架细节；
- 密码、盐、令牌继续由操作日志脱敏；
- 参考依据：`server/route/app.php`、`server/app/BaseController.php`、`server/app/common/controller/BaseLikeAdminController.php`、`server/app/common/service/JsonService.php`、`server/app/common/logic/BaseLogic.php`。

## 2. 认证与会话

| 能力 | LikeAdmin 1.9.4 目标 | Peanut Admin 当前 | 判定/任务 |
|---|---|---|---|
| 登录字段 | 账号、密码、终端；完整校验 | 仅 username/password | A02 扩展请求契约及兼容映射 |
| 账号状态 | 不存在、禁用、密码错分支明确 | 已具备基本分支 | 核对错误文案、code 和失败计数 |
| 登录安全 | 登录错误次数限制和锁定 | 缺失 | A02 新增安全策略 |
| 登录审计 | 更新登录时间和 IP | 缺失 | A02 新增字段及写入逻辑 |
| 会话记录 | 持久化管理员会话 | 无状态 JWT，无会话表 | A02 新增 `admin_session` 模型/表/服务 |
| 终端与多端 | 按 terminal 和 multiple_login 控制 | 缺失 | A02 对齐终端互斥和多端规则 |
| 退出登录 | 单点模式使 token 失效；多点模式按参考现状仅清客户端 token | 不区分模式，只返回成功 | A02 对齐分支语义 |
| 账号变化 | 禁用、删除等使相关会话失效 | 仅每次解析后检查账号状态 | A02/O02 建立统一会话失效入口 |
| 前端跳转 | 一次成功提交进入目标或默认首页 | 已修复并验收 | A01 已完成 |

Peanut 现有实现证据：

- 登录与退出：`server/app/adminapi/controller/auth/LoginController.php`；
- JWT：`server/app/adminapi/service/AdminTokenService.php`、`server/config/jwt.php`；
- 登录中间件：`server/app/adminapi/http/middleware/LoginMiddleware.php`；
- 前端登录：`web/src/views/login/components/login-form.vue`。

参考目标证据：

- `/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/server/app/adminapi/logic/LoginLogic.php`；
- `/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/server/app/adminapi/validate/LoginValidate.php`。

参考实现的精确会话契约：

- 登录接口为 `login/account`，`account/password/terminal` 必填，terminal 仅允许 1=PC、2=Mobile；
- 同一 IP 连错 5 次锁定 30 分钟；
- 登录成功更新管理员登录时间和 IP；
- 会话按 `(admin_id,terminal)` 唯一，令牌有效 8 小时，剩余 1 小时续期；
- 令牌首次使用时绑定 IP，后续 IP 变化返回登录失效；
- `multipoint_login=0` 时同终端新登录替换旧会话；参考实现允许多处登录时 logout 只清客户端令牌，不主动让服务端令牌过期；
- 参考统一响应为 HTTP 200 的 `{code,show,msg,data}`，Peanut 实现保留自身 `{code,msg,data}` envelope，只对齐业务语义。

## 3. 权限基础设施

| 能力 | LikeAdmin 目标 | Peanut Admin 当前 | 判定/任务 |
|---|---|---|---|
| 超管权限 | 超级管理员拥有全部能力 | root=1 全放行 | 可复用，字段语义后续兼容 |
| 菜单权限 | 角色关联菜单及按钮权限 | role→role_menu→menu.perms | 骨架可复用 |
| API 权限 | 页面所需主/辅助接口均有授权闭环 | 精确 path 匹配，但权限种子不全 | A03 补齐并验证普通角色 |
| 菜单加载 | 按管理员角色返回启用菜单树 | 已按 root/角色过滤 M/C | 核对隐藏、禁用、选中与参数行为 |
| 无权限错误 | 明确的未登录/无权限业务响应 | 40100/40300 | 核对 HTTP 与业务 code 语义 |
| 操作审计 | 写操作记录且敏感字段脱敏 | POST 已记录并脱敏 | 可复用，核对参考字段 |

已确认风险：`/api/user/info`、`/api/user/menu` 只校验登录；`/api/admin/login/info` 位于完整 RBAC 组，而现有普通角色权限种子缺少多项列表辅助、详情和状态接口，可能出现“菜单可见但页面接口 403”。

参考系统的关键现状是“只有 URI 已登记在任意 `system_menu.perms` 时才进行角色授权判断；未登记 URI 直接放行”。该语义必须作为 A03 的显式兼容决策和测试项，不能默认推断为 deny-by-default。

前端闭环差异：LikeAdmin 的 `mySelf` 返回 `{user,menu,permissions}` 并动态注入路由，A 类型权限字符控制按钮；Peanut 当前 `menuFromServer=false`、静态路由和角色字符串鉴权，且业务页面未使用 `v-permission`。A03 必须同时完成服务端菜单、动态路由、按钮权限和后端接口授权闭环。

A03 当前实施状态：

- 后端 `info` 已返回同源 `menu/permissions`，root 为 `['*']`；普通管理员按角色联集取得 M/C 菜单和 A 按钮权限；
- 后端已对齐“未登记 URI 放行、已登记才校验角色”的参考现状；
- 前端已启用服务端菜单，并以静态路由为唯一组件来源进行安全映射；服务端菜单决定显示和直达权限；
- `hasPermission` 与 `v-permission` 已改为权限字符判断并支持 `*`，登录白名单和 logout 清理已对齐；
- 各业务页面尚需随模块实施接入具体按钮权限字符，因此 A03 仍为进行中。

## 4. 管理员契约

| 子项 | LikeAdmin 目标 | Peanut Admin 当前 | 差异 |
|---|---|---|---|
| 列表 | 分页；账号/名称/角色筛选；导出 | 全量返回，无筛选/分页/导出 | O01 |
| 列表字段 | 头像、账号、名称、部门、角色、状态、登录时间/IP 等 | username、nickname、roles、root、disable、创建时间 | O01 |
| 新增 | 账号、头像、名称、部门、岗位、角色、密码确认、状态、多端登录 | username、password、nickname、avatar、root、disable、role_ids | O02 |
| 编辑 | 同步角色、部门、岗位和账号策略 | 仅同步角色，可直接接收 root | O02；禁止普通管理员提升 root |
| 状态 | 参考状态语义及相关会话失效 | disable 布尔状态 | O02/A02 |
| 删除 | 保护自身/超管，并处理关系和会话 | 保护自身/root；关系处理不完整 | O02 |
| 个人资料 | 头像、名称、密码修改规则 | 已有旧密码/确认密码路径 | 核对字段与错误语义 |

字段迁移方向：

| Peanut 当前 | LikeAdmin 契约 | 处理原则 |
|---|---|---|
| `username` | `account` | 以 LikeAdmin API 语义为目标，数据库采用兼容迁移 |
| `nickname` | `name` | 同上 |
| `role_ids` | `role_id` | 输出/输入对齐参考契约，内部可保持多对多 |
| 无 | `dept_id`、`jobs_id` | 新增管理员—部门/岗位关系表 |
| `disable` | `status` | 明确枚举和相反布尔语义，避免直接取反散落各层 |
| 无 | `login_time`、`login_ip`、`multipoint_login` | 新增字段及业务写入 |

## 5. 角色契约

| 子项 | LikeAdmin 目标 | Peanut Admin 当前 | 任务 |
|---|---|---|---|
| 列表 | 分页并返回使用人数 `num` | lists/all 同为无分页全量 | O03 |
| 授权字段 | `menu_id` | `menu_ids` | O03 对齐 API 契约 |
| 名称规则 | 名称唯一 | 仅必填和长度 | O03 补校验 |
| 菜单规则 | 数组、菜单存在且授权回显一致 | 未校验数组/存在性 | O03 |
| 删除 | 角色被管理员使用时拒绝 | 直接删除角色和 role_menu | O03 补占用约束和错误 |

O03 当前实施状态：

- 后端已实现分页列表、管理员人数 `num`、权威授权字段 `menu_id`、名称唯一、菜单存在性、占用删除保护、角色软删除和角色—菜单事务；
- 为现有页面临时兼容 `menu_ids` 请求及详情别名，新的页面和文档只使用 `menu_id`；
- 保留 LikeAdmin 1.9.4 的现状：编辑时空 `menu_id` 表示不修改原授权，而不是清空授权；
- 角色增量迁移已执行；前端已完成分页、列表字段、基本信息独立编辑和授权弹窗，空授权在前端明确拦截；
- 前端类型检查及运行中角色列表接口契约已通过一次最小验证；待解除 A03 前端 lint 阻塞并完成双系统真实业务路径后标记为已验收。

## 6. 菜单契约

Peanut 当前字段为 `pid,type,name,icon,sort,perms,paths,component,is_cache,is_show,is_disable`，参考系统还包含 `selected`、`params` 及更完整的目录/组件/权限规则。

必须补齐：

- 类型、路径、组件、权限标识、缓存、显示和状态的场景校验；
- 同层/目录名称约束；
- 上级存在，且上级不能是自身或自身后代；
- 存在子菜单或角色授权时的删除约束；
- `selected`、`params` 的数据表、接口与前端路由行为；
- 页面所用辅助接口和按钮操作的完整权限种子。

## 7. 部门与岗位契约

| 子项 | LikeAdmin 目标 | Peanut Admin 当前 | 任务 |
|---|---|---|---|
| 部门列表 | name/status 筛选、层级、status_desc | 全量部门树 | O04 |
| 部门字段 | status、sort、update_time 等 | leader、mobile、sort、is_disable | O04 以参考语义为准，保留兼容扩展 |
| 部门约束 | 名称唯一、上级存在、顶级保护、关联管理员保护 | 仅防自身为上级、仅防直接子部门 | O04 |
| 岗位列表 | 参考 status 契约及管理员关联 | 当前已有分页、搜索、code 唯一和软删 | O05 复用现有能力并迁移状态语义 |
| 管理员关联 | 管理员可关联部门与岗位 | 无关联表 | O02/O05 新增关系 |

## 8. 数据表变更方向

当前核心表：`pa_admin`、`pa_system_role`、`pa_admin_role`、`pa_system_menu`、`pa_system_role_menu`、`pa_dept`、`pa_jobs`，定义位于 `server/database/init.sql`。

基础权限域预计至少新增或扩展：

- `pa_admin_session`；
- `pa_admin_dept`；
- `pa_admin_jobs`；
- `pa_admin.login_time/login_ip/multipoint_login` 等字段；
- `pa_system_menu.selected/params`；
- 部门/岗位 `status` 兼容迁移。

数据库实施规则：

1. 每个模块先提供独立、可回滚的增量迁移，避免多人同时修改 `init.sql`；
2. 由主线在模块验收后统一同步全新安装脚本；
3. 现有数据采用新增字段、回填和兼容读取的渐进迁移，避免直接重命名破坏运行系统；
4. 外键是否落库遵循项目既有方向，但唯一索引和高频查询索引必须显式定义；
5. API 对外契约以 LikeAdmin 业务语义为准，内部命名保持 Peanut Admin 规范。

## 9. 实施拆分与文件冲突

| 包 | 独立文件所有权 | 与其他包的交叉点 |
|---|---|---|
| A02 认证会话 | token/service/middleware/session model 与迁移 | Admin 字段由主线统一合入 |
| A03 权限 | AuthMiddleware、权限服务、权限种子增量迁移 | Menu perms 与 O03/O04 协调 |
| O01/O02 管理员 | Admin controller/logic/validate/model/关系模型 | 统一拥有 Admin 表扩展和关系事务 |
| O03 角色 | Role controller/logic/validate/model | 会话失效入口由 A02 提供 |
| O04 部门 | Dept controller/logic/validate/model | 管理员占用检查依赖 O02 关系 |
| O05 岗位 | Jobs controller/logic/validate/model | 管理员占用检查依赖 O02 关系 |
| 菜单 | Menu controller/logic/validate/model | 权限种子由 A03 统一审核 |

`server/database/init.sql`、`server/route/app.php` 和公共响应/权限封装由主线统一修改，不分配给多个并行任务。
