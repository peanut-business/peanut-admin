# G-06 前端 Admin Shell 契约

> 状态：Recalibrated and Reviewed（2026-07-15），通过 48 号复审，等待新编码批准
>
> 技术主线：Vue 3 + Vite + Element Plus + Pinia + Vue Router
>
> 依赖：G-02 会话、G-04 Module/menu、G-05 API/OpenAPI

## 1. 先用业务语言说明

P0 是一个可运行的 Admin Web，但里面有两个严格分开的工作区：

- 租户工作区：租户成员管理本租户的部门、成员、角色和已开通 Module。
- 平台工作区：平台操作员管理 Tenant 和 TenantModule，不自动进入客户业务数据。

两者可以由同一个 Vite 项目构建和部署，但使用不同路由树、不同 auth store、不同 refresh cookie、不同 API client 和不同 Shell guard。

门店管理、仓储管理等不是新的登录权限体系。它们可以通过 ProductProfile 和菜单呈现不同页面组合，最终仍调用相同 Module API 和 G-03 权限。

## 2. P0 路由树

```text
公开租户路由
  /login
  /select-tenant

租户路由
  /app
  /app/account
  /app/members
  /app/departments
  /app/roles
  /app/modules
  /app/audit
  /app/<module-routes>

公开平台路由
  /platform/login

平台路由
  /platform
  /platform/tenants
  /platform/tenants/:tenant_id
  /platform/operators
  /platform/roles
  /platform/audit

通用状态路由
  /403
  /404
  /service-unavailable
```

固定规则：

- `/app/**` 只接受 TenantSession 和 TenantContext。
- `/platform/**` 只接受 PlatformSession 和 PlatformContext。
- `/app` URL 不包含 tenant_id；切租户必须按 G-02 换 Session。
- `return_to` 只能是同 audience 的相对 allowlist 路径，禁止开放重定向。
- 平台路由里的 `:tenant_id` 是管理目标，不会创建 TenantContext。

## 3. 启动与路由 Guard 顺序

页面进入受保护路由时：

1. 根据 route namespace 选择 TenantAuth 或 PlatformAuth。
2. 内存没有 access token 时，只尝试对应 audience 的 refresh cookie 一次。
3. 调用对应 `/auth/context`，校验 audience。
4. 建立不可混用的 Context store。
5. Tenant 端加载有效 module keys、permission keys 和 authorization revision。
6. 调用 `/menus` 取得后端已过滤菜单。
7. 将菜单 route_name 与 build-time route/component registry 对照。
8. 验证当前 route 的 Module 和前端显示 Permission。
9. 完成后才挂载页面，不先闪现受保护内容。

任何一步失败按第 10 节进入明确状态。不能捕获错误后默认放行。

## 4. 菜单、路由、按钮和 API 各自负责什么

| 层 | 责任 | 是否是安全裁决 |
| --- | --- | --- |
| Menu | 告诉用户从哪里进入页面 | 否 |
| Vue Route Guard | 避免进入明显不可用页面，给出正确状态 | 否，只是前端 UX |
| Button/access directive | 隐藏或禁用无权操作 | 否 |
| Backend Permission | 判断能否执行动作 | 是 |
| Backend DataPermission | 判断能操作哪些目标 | 是 |
| Module business rule | 判断业务状态是否允许 | 是 |

用户即使手工输入 URL、修改 Pinia、显示隐藏按钮或直接发 HTTP 请求，后端结果也不能变化。

## 5. 菜单唯一事实源

G-04 Module 的 `Resources/menus.json` 是菜单定义事实源。后端同步到 `pa_menu_definition`，再结合 Client、ModuleInstallation、TenantModule 和 Permission 返回最终树。

`GET /api/v1/menus`：

```json
{
  "data": [
    {
      "key": "core.organization",
      "type": "group",
      "name": "组织权限",
      "icon": "users",
      "children": [
        {
          "key": "core.member.list",
          "type": "page",
          "name": "成员管理",
          "route_name": "tenant.members.list",
          "route_path": "/app/members",
          "icon": "user-round",
          "children": []
        }
      ]
    }
  ],
  "meta": {
    "request_id": "req_01K...",
    "authorization_revision": "18"
  }
}
```

前端不能执行后端返回的 component path。它只用 `route_name` 查找构建时已注册的 Vue component。未知 route_name 进入诊断并隐藏菜单，CI 必须提前阻止这种漂移。

Platform 使用 `/api/platform/v1/menus` 和独立 menu scope。

## 6. Build-time Module 前端贡献

P0 不加载远程 JavaScript，不 `eval` 数据库代码，也不在浏览器安装 Plugin。

Module 在前端导出类型化贡献：

```ts
export default defineAdminModule({
  key: 'example.work-item',
  routes: [
    {
      name: 'example.work-item.list',
      path: '/app/work-items',
      component: () => import('./pages/WorkItemListPage.vue'),
      access: {
        moduleKey: 'example.work-item',
        permissionKeys: ['example.work-item.read'],
      },
    },
  ],
  disposeOnTenantChange: true,
})
```

固定规则：

- Module key 必须与后端 manifest 一致。
- route name 全局唯一，path 位于正确 audience namespace。
- component 必须是 build-time import，不能来自 API 字符串。
- route permission 只用于 UX，并通过 CI 与 menus/OpenAPI 引用校验；后端仍独立校验。
- Module 只能返回 routes、stores、locales 和受控 shell slots，不能任意修改根 Router/Pinia。
- Tenant switch/logout 时调用 Module dispose，清除上一个租户的列表、筛选、选中对象和请求缓存。
- 未开通 Module 的 chunk 可以保持懒加载，不能在首页预取敏感数据。

## 7. `frontend/` 与 `packages/web/`

### 7.1 `frontend/` 应用所有内容

```text
frontend/src/
  app/                  # root、providers、最终 router
  pages/auth/           # 登录、租户选择
  pages/platform/       # 平台控制面页面
  pages/tenant/         # Kernel 管理页面
  modules/              # 应用内可选 Module 页面
  branding/             # Peanut Admin 默认品牌和项目覆盖点
  generated/            # build-time module/route registry
```

`frontend/` 拥有最终产品装配、登录页、租户选择、平台/租户页面、品牌和产品级路由。它是可运行应用，不是 npm library。

### 7.2 `@peanut-admin/admin-core`

允许导出的 public API：

```text
createTenantApiClient
createPlatformApiClient
useTenantAuth
usePlatformAuth
useTenantContext
usePlatformContext
useAccess
hasPermission
hasAllPermissions
parseProblemDetails
isProblemCode
defineAdminModule
AdminModuleContribution types
generated OpenAPI types re-export
TypedTargetSet / TargetCandidate types
useOperationTargets
```

它负责：

- access token 只存内存。
- single-flight refresh，避免并发 401 触发多次 rotation。
- audience 分离。
- request ID、Problem Details 和 401/412/429/503 通用处理。
- Tenant switch 时统一清理缓存和 stores。

它不包含成员、部门、角色、商品、库存等业务页面。

### 7.3 `@peanut-admin/admin-shell`

允许导出的 public API：

```text
AdminShell
PlatformShell
ShellHeader
ShellSidebar
ShellBreadcrumb
ShellTabs
PageHeader
PageToolbar
PageContent
TargetSelector
TargetScopeSummary
EmptyState
ForbiddenState
ModuleUnavailableState
SessionExpiredState
shell slots and theme tokens
```

Shell 包只负责布局、导航容器和稳定状态组件。具体菜单、命令、业务表格和项目品牌由应用/Module 提供。

### 7.4 `@peanut-admin/web-testing`

提供：

- mock Tenant/Platform context。
- mock Problem Details。
- permission/module state builders。
- route guard test harness。
- tenant switch store-leak assertions。

## 8. 前端状态隔离

P0 至少有：

| Store | 内容 | 持久化 |
| --- | --- | --- |
| TenantAuthStore | 租户 access token、refresh 状态 | 仅内存 |
| PlatformAuthStore | 平台 access token、refresh 状态 | 仅内存 |
| TenantContextStore | account/tenant/member/module/permission/revision | 仅内存 |
| PlatformContextStore | account/operator/platform permissions | 仅内存 |
| MenuStore | 当前 audience 的菜单树 | 仅内存 |
| ModuleTargetStore | 当前 Module 页面按 resource+operation 保存 typed target 选择 | 仅内存；不是全局 CurrentSubject |
| ShellPreferenceStore | 侧栏折叠、主题、语言 | 可持久化，不含业务/租户数据 |
| Module stores | 页面查询和临时选择 | 默认内存，Tenant switch 必须 dispose |

禁止持久化：

- access/refresh token。
- TenantContext/PlatformContext。
- 当前 tenant_id/member_id。
- Permission keys、Module keys 和菜单授权结果。
- 门店、仓库、客户、订单等业务数据。

浏览器可以同时持有两个 audience 的 HttpOnly refresh cookie，但一个标签页只保留当前 route namespace 对应的 access token 和 context。标签页从 `/app` 切到 `/platform` 时清空 TenantAuth/TenantContext 内存，反向同理；需要返回时再使用对应 refresh cookie 建立上下文。

## 9. 租户切换和业务对象选择

### 9.1 租户切换

成功切租户后按顺序：

1. 停止旧租户未完成请求。
2. 清空旧 TenantContext/Menu/Permission/Module stores。
3. 清空 tabs、breadcrumb cache、query cache 和 selected targets。
4. 写入新 access token 和 Context。
5. 重新加载菜单和 Module contribution 可见性。
6. 跳转新租户默认首页，不恢复旧租户详情 URL。

不能只替换页头上的租户名称。

### 9.2 门店、仓库等业务对象

Auth/TenantContext 不保存全局 `current_store_id` 或 `current_warehouse_id`。

- 页面按 `resource_key + operation + target_resource_key` 调用 G-05 target-candidates，不能先下载全 Tenant 对象再前端过滤。
- 候选值必须保存为 `{target_resource_key, target_id}`，不同类别 ID 即使字符串相同也不能混用。
- 门店型页面可以在对应 ModuleTargetStore 或 route query 中保留门店候选；仓储型页面可以独立保留仓库候选；两者不能覆盖彼此。
- 候选在每次 API 请求中作为显式业务目标，后端仍按 G-03 验证类别、基数、归属和当前授权。
- ResourceOperation 变化时重新验证现有选择；Tenant switch、Module disable 和 logout 必须清空相关候选。

这样既支持“门店模块内持续针对某门店工作”，也支持“运营页面按参数管理多个门店”，无需把门店塞进全局租户会话。

### 9.3 零、一个和多个目标

| 当前 operation 的可用目标 | 页面行为 |
| --- | --- |
| 0 | 显示无可用目标状态并禁用命令；不能提供手填 ID 的旁路 |
| 1 | 可自动选择；紧凑页面可隐藏 selector 和归属列，但请求仍显式携带 typed target |
| 多个 | 显示可搜索/分页 selector；列表和详情必须在必要位置显示归属目标 |

`many_readable` 可以选择一个或多个同类目标；“全部已授权”是一个明确选项，不等于全 Tenant。`aggregate_read` 页面必须显示当前汇总覆盖的目标数量和范围摘要，默认只读。

普通 `one_required` 写命令提交前必须固定一个 primary target。即使成员有权管理三个门店，也不能在普通编辑表单里无提示地同时改三个门店。`policy_publish` 使用独立发布页面，展示目标数量、执行状态和失败项；P0 不展示通用 `bulk_write` 控件。

### 9.4 统一共享主档选择

Module 使用 shared_master 时，选择器从一个 candidates endpoint 获取统一列表。部署种子和 Tenant 自建记录共享同一种 ID、搜索和分页；页面不能拼接两个请求或显示两个互相独立的“平台商品/自采商品池”。

当归属会影响用户判断时，可以显示 owner/visibility 摘要或状态标识；这些只是解释信息，真正能否引用仍由后端 SharedMasterScopeProvider 决定。

## 10. 必须实现的状态

### Session invalid/expired

- 401 时同 audience 只进行一次 single-flight refresh。
- 刷新失败则清除内存状态，跳转对应 login。
- 保留 allowlist 的 `return_to`，但不保留敏感表单内容。

### Tenant suspended/closed

- 显示租户不可用阻塞页。
- 允许退出或切换其他 Tenant。
- 不显示旧业务页面和缓存数据。

### Member suspended/left

- 清空租户会话并回到租户登录。
- 不影响 PlatformAuth 或该 Account 的其他 Tenant。

### Permission denied

- 前端 route/button 根据最新 Permission 隐藏或显示 403。
- API 403 始终以服务端为准；刷新 context/menu 后仍拒绝则停留在 403。
- 单资源 404 不提示“你没有权限”，避免泄露存在性。

### Module unavailable

- `MODULE_TENANT_DISABLED/NOT_EFFECTIVE` 显示 ModuleUnavailableState。
- 清除该 Module store 和 tabs，重新加载菜单。
- Module maintenance/failed 显示 503 状态并允许重试，不清除登录状态。

### Revision conflict

- 412 显示“数据已变化”，提供重新加载；不能自动覆盖。
- 用户确认后重新获取资源和 ETag。

### Network/429/503

- 网络错误提供重试，不误判为退出登录。
- 429 尊重 Retry-After。
- 503 显示服务暂不可用和 request_id。

## 11. 响应式和可访问性

Admin Shell 是工作界面，不做营销式首页。

- Desktop：固定侧栏、顶部上下文/账号区、面包屑/可选 tabs、紧凑内容区。
- 低宽桌面：侧栏可折叠为图标，按钮保留 tooltip。
- Mobile：侧栏变抽屉，操作栏允许换行；表格使用横向滚动或 Module 明确提供移动列表视图。
- 不能仅靠缩小字体塞入手机；字体不随 viewport 宽度连续缩放。
- 固定工具栏、分页和 icon button 尺寸，加载/权限状态不能造成布局跳动。
- 表单 label、错误和焦点顺序可键盘访问；图标按钮有 aria-label/tooltip。
- 使用现有 Lucide/Element Plus 图标，不手画重复 SVG。
- 颜色不能是单一紫色、深蓝或米色主题；默认主题保持中性工作界面并满足对比度。

P0 只保证 Admin Web 在桌面和移动浏览器的基础管理可用。POS、小程序和专用移动端是后续独立 Client，不能把收银体验硬塞进响应式后台。

## 12. 性能边界

- Vite 按 Module route lazy split。
- 首屏只加载 Shell、auth/context 和当前首页所需 chunk。
- Permission keys 在内存 Set 中判断，不在模板中反复线性扫描。
- 菜单以 authorization revision 为缓存版本；revision 变化重新获取。
- 同一时间只允许一个 refresh 请求，其余请求等待结果。
- Tenant switch 主动取消旧请求，响应回到时再次核对 context generation，避免旧租户数据写入新 store。
- 大列表使用服务端分页、筛选和排序；前端不获取全量后再过滤。
- 多目标候选使用远程搜索、分页和去抖；已选 ID 通过批量摘要 endpoint 回填，不为每个目标单独请求。

## 13. ProductProfile 和其他 Client

- ProductProfile 可以决定 Admin build 默认包含哪些 Module contributions 和初始首页。
- 运行时最终可见性仍由 TenantModule + Permission 决定。
- 一个部署可以包含门店和仓储页面，只向有权租户/成员显示相应菜单。
- 需要品牌、路由和发布节奏独立时，可以基于 admin-core/admin-shell 构建另一个 frontend Client。
- POS/mini-app/mobile 可复用 OpenAPI types、auth client 的适用部分，但拥有自己的页面、路由和设备/交互契约。

Client 不是 Module：Client 使用 Module API，Module 不拥有某个唯一 Client。

## 14. G-06 必测场景

1. `/app/**` 使用 Platform token 被拒绝。
2. `/platform/**` 使用 Tenant token 被拒绝。
3. Tenant route 不从 URL/localStorage读取 tenant_id 建 Context。
4. 登录后多 Tenant 正确进入选择页。
5. 只有一个 Tenant 时可以自动进入。
6. 切 Tenant 创建新 Session 并清空旧 stores/tabs/requests。
7. 旧请求晚返回不能写进新 Tenant store。
8. access token 不进入 localStorage/sessionStorage。
9. refresh token 不可被 JavaScript 读取。
10. 平台/租户 refresh cookie 不互相覆盖。
11. Menu 未返回时不闪现完整导航。
12. 未知 backend route_name 触发诊断且不执行任意 component。
13. Module 未开通时菜单、route 和 chunk 均不可用。
14. 有菜单但后端 Permission 撤销时 API 仍拒绝。
15. 手工显示隐藏按钮不能绕过 API。
16. authorization revision 变化后菜单和权限刷新。
17. Module disable 清空该 Module store 和 tab。
18. Tenant suspended 清空业务视图但可切换 Tenant。
19. Member suspended 不影响 PlatformAuth store。
20. 401 只触发一次并发 refresh。
21. Refresh reuse/失败清空正确 audience，不误清另一 audience。
22. 412 不自动覆盖服务器数据。
23. 429 尊重 Retry-After。
24. 单对象 404 不显示资源是否真实存在。
25. 当前门店候选不能进入 TenantContext。
26. 运营筛选只能收窄后端数据权限。
27. Mobile 无导航、按钮或表格文本重叠。
28. 键盘可到达主要导航、表单和命令。
29. Module route 均 lazy load 且 build registry 无冲突。
30. frontend 不深层导入其他 Module 内部文件。
31. generated OpenAPI types 无手工修改。
32. POS/mini-app 不作为 Admin Shell 的隐藏 route 实现。
33. 零可用目标时页面不能通过手填 ID 或保留旧选择发起命令。
34. 一个可用目标可自动选择并隐藏 selector，但 API 请求仍包含 typed target。
35. 多个可用 Project 时显示 selector 和归属列，切换筛选不会扩大后端授权集合。
36. Project 与 Queue 的选择分别保存在 operation-scoped store，不能因 ID 相同互相覆盖。
37. `one_required` 表单不能同时提交两个 primary target。
38. `aggregate_read` 页面显示范围摘要且没有编辑入口。
39. `policy_publish` 显示逐目标结果；通用 `bulk_write` 控件在 P0 不存在。
40. shared_master 选择器只显示一个统一候选列表和一套 ID，不在前端 UNION 两个数据源。
41. Tenant/Module/operation 切换后旧 target selection 和候选缓存全部失效。
42. 多目标候选分页加载，不产生 N+1 摘要请求或一次下载全量目标。

## 15. G-06 结论

P0 的 Admin Shell 是一套可直接运行的工作后台，同时把平台控制面与租户工作面明确分开。菜单和前端访问控制负责体验，真正安全裁决始终在后端。

门店、仓库等业务对象可以在具体 operation 页面中形成 typed selection，也可以作为多目标读取条件，但不再污染全局 TenantContext。零、单、多目标只改变页面交互，不改变后端权限算法；后续不同产品可以复用 admin-core/admin-shell 和相同 Module API，而不复制身份、权限和库存等底层能力。
