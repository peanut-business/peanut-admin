# Luna 执行交接：CH03、DE01、DE02、S01、T01

> 交接目标：完成已冻结后端契约的机械前端、客户端消费和一次最低充分浏览器验收。不得重新设计业务 Schema。

## 1. 已完成且禁止重复

- CH01、CH02 全部实现和验收，禁止重复。
- CH03 后端 5 组验收已通过：`output/playwright/ch03/backend-summary.json`。
- DE01/DE02 后端 5 组验收已通过：`output/playwright/de01-de02/backend-summary.json`。
- S01 配置、充值支付、微信 OAuth 后端验收已分别通过：`output/playwright/s01/core-summary.json`、`output/playwright/s01/recharge-payment-summary.json`、`output/playwright/s01/wechat-oauth-summary.json`。
- T01 生成器及系统工具后端验收已分别通过：`output/playwright/t01/generator-summary.json`、`output/playwright/t01/system-tools-core-summary.json`。
- 禁止重复 PHP 状态机、迁移、数据库约束、API/DB 批量验收；只在前端接入失败时读取对应后端错误。

## 2. 不得修改的冻结边界

- 不修改 `server/database/migrations/20260802_official_account_channel.sql`、`20260802_decoration_parity.sql`。
- 不修改公众号回复不变量、菜单发布顺序、微信传输服务、装修递归 Schema、链接值对象、Tabbar 事务、权限字符或公共 DTO。
- 不添加 LikeAdmin 路由/参数/响应兼容层，不恢复旧 `channel/wechat_oa_*`、`channel/wechat_open_*` 或 `decorate/*` 五键。
- 业务链接固定为 `{target_type,target,query?}`，不是 LikeAdmin 的 `{path,type,...}`。
- AES 只展示为配置资料；页面必须明确当前回调能力为“明文”，不得写成安全模式已经生效。

若现有 DTO 无法表达用户结果、需要新增组件/链接类型、要改字段/迁移/权限或发现状态机问题，立即停止该项并回交 Sol。S01/T01 同样禁止新增兼容字段、兼容路由、OAuth 场景、支付状态或调度命令。

## 3. CH03 机械任务

在 Peanut 现有 Arco/Vue 规范内扩展渠道管理，不追求 LikeAdmin UI 字面一致：

- 增加 API 类型与调用：
  - `setting/official-account/config|save`
  - `setting/official-account/menu|menu/save|menu/publish`
  - `setting/official-account/reply/lists|detail|add|edit|delete|status`
  - `setting/open-platform/config|save`
- 在 `web/src/views/app-setting/channel/` 内提供公众号资料、菜单、自动回复和开放平台四个业务入口。
- 菜单编辑支持两级、3/5 数量、名称长度和 click/view/miniprogram 所需字段；前端规则与后端一致。
- 回复管理支持关注/关键词/默认分类、全/模糊匹配、文本内容、状态和非负排序；不发送或保存 `reply_num`。
- 按现有权限指令分别控制查看、保存、发布、增删改和状态操作。
- 只做一次管理端核心浏览器流程，并在 LikeAdmin 真实页面核对等价字段/操作结果；不比较视觉。

## 4. DE01/DE02 机械任务

管理端新增：

- `web/src/api/decoration.ts`（或项目同规范等价文件）。
- `web/src/views/decoration/mobile/index.vue`：移动首页、个人中心、客服、系统风格。
- `web/src/views/decoration/tabbar/index.vue`：样式与 2～5 项完整保存。
- `web/src/views/decoration/pc/index.vue`：单一 PC Banner 编辑。
- 组件只能排序/启停；不能新增或删除整个标准组件。固定组件不可选；首个 Tabbar 不可移动、隐藏、删除或改链接。
- 图片复用现有素材选择器，后台只提交 API 返回/选择器返回的 URL；相对 URI 转换由后端完成。

消费端新增：

- `uniapp/src/pages/index/index.vue` 消费 `api/index/index.data.decorate`。
- `uniapp/src/pages/user/user.vue`、`customer_service/customer_service.vue` 分别消费 `api/decoration/mobile?type=2|3`。
- `uniapp` 启动/全局状态消费 `api/index/config.data.theme` 与 `tabbar`；按可见项和 position 渲染，并实现冻结的业务链接执行器。
- `pc/pages/index.vue` 消费 `api/pc/index.data.decorate` 或 `api/decoration/pc` 并渲染 PC Banner。
- 不把服务端路径当路由字符串直接执行；`shop/article/custom/mini_program` 必须映射到 Peanut 自身导航能力。

## 5. S01 机械任务

管理端按 Peanut 现有 Arco/Vue 规范接入，不改后端字段：

- 支付配置补 `wx_pay_platform_cert_path`、`ali_pay_seller_id`；所有敏感字段回显 `******` 时原样提交，禁止用掩码覆盖真实值。
- 新增充值配置页，调用 `setting/recharge/config|save`，只使用 canonical `status/min_amount/max_amount/scenes`，完整表达六终端 11 场景矩阵，不增加别名字段。
- 登录配置页补 `third_auth`、`wechat_auth`；小程序、公众号、开放平台配置页接入 `app_secret` 与 `app_secret_configured` 掩码语义。
- 用户端充值接入 `api/recharge/config|create|prepay|detail|lists`；客户端不得提交或覆盖订单金额、用户、终端归属，也不模拟第三方支付回调入账。
- 微信 OAuth 客户端只做 PC/公众号 `begin → 微信 → callback`、小程序 code 登录、completion ticket 资料/短信补全，以及登录态 mnp/oa 绑定；PC 固定回调页面为 `/oauth/callback`。不添加 QQ、移动 App OAuth、解绑或账号合并。

## 6. T01 机械任务

- 完成生成器管理页面：source tables、import、lists、detail、sync、update、delete、models、preview、generate、download；下载只能消费一次性 token。
- 现有字典、定时任务、操作日志和维护页面只接入已冻结字段与权限按钮；操作日志补 IP、时间筛选和两阶段 XLSX。
- 不修改 Cron CAS 认领、命令注册表、字典事务、递归脱敏、导出上限、清缓存语义、生成器模板/路径/迁移或后端状态机。

## 7. 最低充分验证与停止条件

正常只输出精简 summary JSON，失败时才读取 DOM、网络或日志：

1. Web、uniapp、pc 各运行一次现有类型检查；只在实际改动覆盖对应工程时运行，不跑全量无关测试。
2. CH03 浏览器：配置读取/保存、菜单本地保存、回复三分类入口、开放平台、权限隐藏/拒绝各一次。菜单真实发布不调用第三方，后端顺序已验收。
3. DE01：后台修改一个 Banner 名称/图片并保存，移动首页立即出现；修改主题后移动端变量改变；Tabbar 改一项后立即出现。
4. DE02：后台修改一个 PC Banner 名称/图片并保存，PC 首页立即出现。
5. LikeAdmin 每域只核对一次同等用户能力，不制造数据库夹具，不重复已冻结状态机。
6. S01 浏览器只验证：支付/充值配置可读写且掩码不覆盖；一个用户端充值创建/详情/预支付消费链；三个微信 OAuth 客户端入口及 completion 页面字段。不得触发真实支付回调，真实凭据不存在时只验收本地入口、参数与错误结果。
7. T01 浏览器只验证：生成器一次导入→配置→预览→生成→一次性下载；字典一次改名/占用提示；任务一次预览/启停；日志一次 IP/时间筛选与 XLSX；维护一次精确清缓存。后端状态机和数据库不变量不得重验。
8. 每域正常只保存精简 summary JSON；仅失败时读取相关 DOM、网络、日志或 trace。首次成功后立即封存，不换工具重复验证。
9. 更新 `docs/likeadmin-parity-plan.md`、`likeadmin-parity-report.md` 和对应契约；只有页面、客户端消费与浏览器结果均通过后才能把对应任务标记 `[x]`。

完成后停止并交回 Sol 核查，不领取 V01、DOC01 或 DOC02。若失败，只记录最短复现、请求/响应和涉及文件，不擅自修改冻结后端。
