# Peanut Admin 本地功能测试登记

本表用于记录已经真实操作通过的本地功能，避免在代码、数据库和运行环境未影响该项时重复测试。

## 重测规则

- `PASS`：仅当“影响文件、迁移账本、数据库资源或运行环境指纹”之一变化并影响该项时重测。
- `FAIL`：修复后只重测失败项及其直接下游，不重复已通过的无关项。
- `BLOCKED`：解除明确阻塞条件后继续，不以较弱检查替代。
- 浏览器测试必须记录真实新增/编辑/删除或保存动作；只打开页面记为 `VIEW-ONLY`，不能算功能通过。
- 测试数据统一使用 `PA-E2E-YYYYMMDD-<domain>` 标记。可安全清理的夹具在验证后删除；保留供人工体验的数据必须在备注中列明。

## 环境指纹

| 项目 | 当前值 |
|---|---|
| Candidate | `86acc5558690f6a0aae66911619db1019f8117ff`（本轮执行基线） |
| 数据库资源 | `peanut-admin-mysql84-development` |
| 数据库 | `192.168.192.2:20183/peanut_admin_development` |
| 开发网关 | `http://127.0.0.1:18146`（本轮独立租约） |
| 测试日期 | 2026-08-14 |

## 测试结果

| ID | 领域/入口 | 真实动作 | 状态 | 证据/结果 | 重测触发条件 |
|---|---|---|---|---|---|
| ENV-001 | 开发数据库 | 环境门禁与迁移状态 | PASS | 51 migrations；status=migrated；唯一 root 管理员 | DB 合同、迁移或环境变量变化 |
| FILE-001 | 素材上传 | 上传 PNG、访问 URL、删除测试素材 | PASS | 上传成功；`/storage/...` HTTP 200；记录与对象清理成功 | 上传、存储、Nginx/PHP 配置变化 |
| FILE-002 | 图片上限 | 上传 11MB PNG | PASS | 无 413；业务层返回“文件大小超过上限 10MB” | 任一上传上限或代理层变化 |
| DEC-001 | 移动端装修 | 打开并检查实时预览、条目增删控件、背景配置 | PARTIAL | 只确认预览、标题/背景图片、Banner 背景、条目增删/排序入口存在；未形成逐字段保存和消费端观察证据 | 完成 DEC-004 |
| DEC-002 | Tabbar 装修 | 打开并检查预览、排序、图标、链接与新增入口 | PARTIAL | 只确认页面与接口加载；未形成排序、图标、链接、enabled 保存和消费端观察证据 | 完成 DEC-004 |
| DEC-003 | PC 装修 | 打开并检查预览、条目和尺寸配置 | PARTIAL | 只确认页面与接口加载；未形成链接、顺序和 PC 样式保存及消费端观察证据 | 完成 DEC-004 |
| CLIENT-001 | H5 首页 | 真实浏览器加载装修消费结果 | PASS | 首页、资讯、动态 Tabbar 可见 | UniApp 或装修读取 DTO 变化 |
| CLIENT-002 | PC 首页 | 真实浏览器加载装修消费结果 | PASS | 首页及资讯入口可见 | PC 或装修读取 DTO 变化 |
| SYS-ADMIN-001 | 管理员管理 | 新增普通管理员、编辑名称、列表确认、删除 | PASS | `pa_e2e_admin_0814` 全流程成功并清理；名称实际限制 1～16 字符 | 管理员 Controller/Logic/Validate/页面或 Schema 变化 |
| SYS-ROLE-001 | 角色管理 | 新增、分配工作台权限、保存、删除 | PASS | `PAE2ERole0814` 授权成功并清理 | 角色、菜单授权或权限树变化 |
| SYS-DEPT-001 | 部门管理 | 空表创建首个根部门 | PASS | 修复后“顶级部门”默认可选；`PA-E2E-根部门` 创建成功并精确清理 | 部门表单、父级校验或 Schema 变化 |
| SYS-DEPT-002 | 部门管理 | 删除根部门 | BLOCKED | 产品明确禁止删除顶级部门；当前没有把唯一根部门迁移为其他根的流程 | 部门删除策略变化 |
| SYS-JOBS-001 | 岗位管理 | 新增、编辑、删除 | PASS | `pa_e2e_job_0814` 全流程成功并清理 | 岗位 Controller/页面或 Schema 变化 |
| MEMBER-001 | 会员管理 | 新增、详情、禁用/启用 | PASS | 保留 `PA-E2E-会员`（手机号 `13900000814`）供人工体验；页面无删除入口 | 会员 Runtime、页面或 Schema 变化 |
| MEMBER-002 | 会员标签 | 新增、编辑、删除 | PASS | `PA-E2E-标签` 全流程成功并清理 | 标签 Runtime、页面或 Schema 变化 |
| FINANCE-001 | 会员余额/余额明细 | 增加 1.23、等额扣回、查询流水 | PASS | 最终余额 `0.00`；两条可追溯流水保留 | 余额服务、账本或财务页面变化 |
| ARTICLE-001 | 文章/栏目 | 新增栏目和文章、编辑文章、删除并清理 | PASS | `PA-E2E-文章`、`PA-E2E-栏目` 全流程完成；软删除记录不计活动数据 | 文章 Runtime、页面、Schema 或客户端读取变化 |
| DICT-001 | 字典/字典数据 | 两层新增、删除、清理 | PASS | `pa_e2e_0814` 与数据项完成并清理 | 字典 Runtime、页面或 Schema 变化 |
| FILE-003 | 素材分类 | 新增根分类并清理 | PASS | `PA-E2E-素材` 创建成功，确认无关联文件后精确清理 | 素材分类 Runtime 或页面变化 |
| NOTICE-001 | 通知模板 | 修改登录验证码短信内容、保存、恢复 | PASS | 操作日志确认保存请求；数据库精确恢复原值 | 通知场景 Runtime、页面或 Schema 变化 |
| CRON-001 | 定时任务 | 停止并重新启动归档清理任务 | PASS | 操作日志记录 stop/start；最终恢复运行 | 定时任务 Runtime、调度或页面变化 |
| LOG-001 | 操作日志 | 加载并核对本轮写操作 | PASS | 角色、岗位、字典、文章、通知、任务等写操作均可追溯 | 操作日志 Runtime 或页面变化 |
| FINANCE-002 | 充值/退款 | 列表、筛选、详情 | VIEW-ONLY | 未产生真实支付/退款；本轮仅核对入口，不宣称业务通过 | 有安全的本地支付 fixture 后 |
| SETTING-001 | 应用设置 | 读取用户、存储等设置页 | VIEW-ONLY | 设置页可读；Element Plus 隐藏 switch 不作为保存验证证据 | 配置页面或保存 Runtime 变化 |
| SETTING-002 | 旧页面装修/客服设置 | 旧 URL 收敛到唯一 Runtime | PASS | 两个旧 URL 均重定向 `/decoration/mobile`；旧菜单迁移退出；H5 客服页可达 | 装修路由、菜单迁移或客服消费端变化 |
| MAINT-001 | 维护页 | 打开 `/system/maintenance` | BLOCKED | 不是刻意退出：前端路由/页面、`system/info`/`system/clearcache` API 和 `20260802_system_tools_core.sql` 菜单均存在；需在登记 DB 可从独立栈访问后核对菜单行、禁用状态和 root 菜单响应 | 独立容器恢复到登记 DB 的路由后，核对菜单数据并只修复实际缺口 |
| GENERATOR-001 | 生成器 | 打开 `/dev-tools/code` 并执行临时表流程 | BLOCKED | 不是刻意退出：前端路由/页面、完整 Generator API 和 `20260802_code_generator.sql` 菜单均存在；需在登记 DB 可从独立栈访问后核对迁移菜单和 root 菜单响应 | 独立容器恢复到登记 DB 的路由后，用 `PA-E2E-20260814-generator` 临时表完成导入→预览→生成→删除 |
| ADMIN-ALL | 管理后台全部菜单 | 逐菜单真实新增/编辑/删除或保存 | PARTIAL | 核心 CRUD 已覆盖；维护、生成器及真实支付按下方明确停止线未通过 | 按子项结果决定 |

## 2026-08-14 有界闭包执行记录

- 资源租约：`local-functional-closure-20260814`；DB 只选用
  `peanut-admin-mysql84-development@192.168.192.2:20183/peanut_admin_development`；
  网关/PHP/Web/PC/H5/Docs 端口分别为 `18146/18147/15146/13146/15147/14146`。
- 公司 DB 容器为登记镜像 MySQL `8.4.10` 且健康；宿主机到登记地址 TCP 可达。
  独立 Compose 的 PHP 容器连接同一登记地址返回 `SQLSTATE[HY000] [2002] Connection refused`。
  按一次失败、一次只读诊断的预算停止 DB、后端和浏览器动作；未切换 DB、旧 `8080`
  栈、localhost、mock 或其他凭据。
- 首次启动还复现新 worktree 先同步 DB 凭据后缺失 `ADMIN_INITIAL_PASSWORD`，导致 Compose
  插值失败。`scripts/local-stack.sh` 已最小修复为补齐缺失/空的本地管理员 email/password；
  `sh -n` 通过，重跑已越过插值阶段并到达上述 DB 网络停止线。
- Web 构建因本 worktree 没有 `web/node_modules`，在 `vue-tsc` 启动前停止；未安装依赖，
  未复用其他 worktree 缓存，未把该结果写成构建失败或功能失败。
- 本轮没有创建、修改或保留任何 `PA-E2E-20260814-*` 数据，也没有触发支付、退款、
  短信、微信、OAuth 或外部 Provider。聚焦证据见
  `output/playwright/local-functional-closure-20260814/summary.md`。

## 本轮新增待复现线索

| ID | 线索 | 当前核对结论 | 状态 / 解除条件 |
|---|---|---|---|
| NOTICE-002 | 通知日志筛选 | 未能启动独立栈；没有用静态 API 检查替代浏览器筛选 | BLOCKED：恢复登记 DB 容器路由后，用已存在日志按渠道、状态、接收者各筛一次 |
| NOTICE-003 | 渠道空配置 `sms_default/status` 异常 | 当前页面使用 `detail.status?.sms`、可选 `sms_default` 和两套默认表单值，旧异常路径在代码上已防护 | PARTIAL：空配置 fixture 下真实打开、保存并恢复后升级 PASS |
| FILE-004 | 素材重命名/移动 | 未执行 | BLOCKED：恢复登记 DB 容器路由后，上传 `PA-E2E-20260814-file`，重命名、移动、访问并精确删除 |
| SETTING-003 | 应用设置真实保存/恢复 | 未执行 | BLOCKED：恢复登记 DB 容器路由后，选择一个无外部副作用字段保存→重载观察→恢复原值 |
| ARTICLE-002 | 文章显隐 | 页面已有 `article.article/updateStatus` 开关，但未执行真实切换 | BLOCKED：恢复登记 DB 容器路由后，对独立 fixture 隐藏→客户端不可见→显示→可见→清理 |
| FINANCE-003 | 安全充值/退款 fixture | 当前没有证明 Provider 零调用的本地 fixture；禁止使用真实交易替代 | BLOCKED：测试层注入 fake/throwing payment gateway，并以调用计数断言外部 Provider 为 0 后再测充值→退款 |
| DEC-004 | 装修逐字段消费验收 | 未执行；DEC-001～003 的控件存在证据不足以通过 | BLOCKED：恢复独立栈后，对已声明字段、顺序、search、enabled、主题、链接、PC 样式逐项保存→H5/PC 可观察→恢复 |
| UI-001 | 390×844 品牌/筛选/操作列 | 旧报告未在当前候选复现 | BLOCKED：真实浏览器 390×844 聚焦截图和操作可达性检查 |
| UI-002 | 1176px 文章表格 | 当前操作列固定右侧，但未验证横向滚动和压缩 | PARTIAL：真实浏览器 1176px 验证操作列和横向滚动均可达 |
| UI-003 | 自动折叠后子菜单浮层 | 当前使用 Element Plus `ElMenu collapse`，未验证浮层交互 | PARTIAL：窄桌面触发自动折叠后打开至少一个二级菜单并导航 |
| UI-004 | 顶部头像破损 | `userStore.avatar` 可为空，模板仍渲染 `<img>`，旧问题可能仍存在 | PARTIAL：真实浏览器核对网络与可见 fallback；若为空/404，最小补默认头像或首字母 fallback |
| UI-005 | 财务 `icon-fingerprint` 未注册 | 当前 `iconMap` 已映射为 Element Plus `Key` | PARTIAL：菜单真实渲染无控制台错误后升级 PASS |
| A11Y-001 | 纯图标按钮/移动菜单/输入与开关命名、支付页签可访问树 | 折叠按钮已有名称；Navbar 多个图标按钮和移动菜单按钮源码未见 `aria-label`，支付页签仍需可访问树复现 | PARTIAL：先修复并用浏览器 accessibility snapshot 聚焦复测，不扩大为全站审计 |
| BRAND-001 | 登录页旧品牌、重复文案、弱凭据 | 当前登录源码无 `Arco Design Pro`，密码默认空；用户名仍默认 `admin` | PARTIAL：真实浏览器核对可见文案和输入初始值后升级 PASS |
| BUILD-001 | Vue hydration mismatch feature flag 警告 | Vite `define` 当前未显式声明；本轮因依赖缓存缺失未进入构建阶段 | BLOCKED：安装锁文件依赖后仅运行一次 Web build；若警告复现，最小声明对应布尔 feature flag 后只重跑 build |

## 本轮待执行矩阵

| 领域 | 最低真实动作 | 结果 |
|---|---|---|
| 管理员 / 角色 / 部门 / 岗位 | 管理员与角色/岗位完整闭环；空表根部门创建已修复，顶级部门删除为产品限制 | PARTIAL |
| 会员 / 标签 / 余额 | 会员新增/状态、标签 CRUD、余额增减与流水 | PASS |
| 文章 / 分类 | 新增分类和文章、编辑、删除并清理；显隐开关未计入 | PARTIAL |
| 通知 | 登录验证码模板保存并恢复；通知日志仍待筛选验证 | PARTIAL |
| 财务 | 余额明细真实通过；充值/退款不触发真实交易 | PARTIAL |
| 应用设置 | 用户/存储等可读；旧装修/客服双入口已收敛 | PARTIAL |
| 素材 | 上传、业务上限、分类新增已通过；重命名/移动仍待 | PARTIAL |
| 字典 / 定时任务 / 日志 / 维护 | 字典 CRUD、任务启停恢复、操作日志通过；维护页 404 | PARTIAL |
| 生成器 | 根管理员未获得入口，直接路由 404 | BLOCKED |
| 装修 | 深度字段/顺序/搜索/链接/PC 样式代码与合同测试通过；逐字段真实浏览器验收待执行 | PARTIAL |
