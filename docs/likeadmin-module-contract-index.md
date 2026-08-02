# LikeAdmin 1.9.4 模块契约索引

> 本索引记录产品模块边界和实施优先级。页面存在不等于业务完成；每个模块仍需按页面、API、校验、Logic、Model、DB、权限、错误十项模板验收。

## 1. 模块树对照

| LikeAdmin 业务域 | LikeAdmin 主要能力 | Peanut Admin 当前对应 | 当前判定 |
|---|---|---|---|
| 工作台 | 版本、今日/累计统计、快捷入口、访问与销售趋势 | 仪表盘/工作台欢迎卡 | 大量缺失 |
| 用户管理 | 用户列表、筛选、导出、详情、编辑、余额调整 | 会员列表、会员标签 | 模型语义冲突 |
| 应用管理 | 用户充值、素材中心、文章资讯、消息管理 | 分散在系统/内容/通知/应用设置 | 能力拆分且部分缺失 |
| 财务管理 | 余额明细、充值记录、退款记录及状态操作 | 账户流水、充值、退款 | 查询部分覆盖，退款状态操作缺失 |
| 装修管理 | 移动页、底部导航、系统风格、PC 页面 | 单一页面装修配置 | 数据模型与流程不等价 |
| 渠道设置 | H5、小程序、公众号配置/菜单/回复、开放平台 | 单一渠道配置页 | 严重扁平化 |
| 组织管理 | 部门、岗位 | 系统管理/部门、岗位 | 骨架可复用，状态与约束需对齐 |
| 权限管理 | 菜单、管理员、角色、按钮权限 | 系统管理/菜单、管理员、角色 | CRUD 有骨架，运行时权限闭环缺失 |
| 系统设置 | 网站、用户、支付、存储、热门搜索、维护、开发工具 | 网站/存储/字典/任务/日志等分散入口 | 部分覆盖，多组配置缺失 |
| 模板示例 | 富文本、上传、图标、选择器等 | 无对应模块 | 缺失 |
| 个人设置 | 自身资料与密码 | 个人中心 | 待契约验收 |

LikeAdmin 权威菜单来源：`/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/server/public/install/db/like.sql`。Peanut 静态路由来源：`web/src/router/routes/modules/`。

## 2. P0：认证与权限闭环

LikeAdmin 登录后通过 `mySelf` 同时取得 `user`、动态 `menu` 和按钮 `permissions`，再注入前端路由；页面操作按权限字符控制。

Peanut 当前：

- `menuFromServer=false`，菜单来自编译期静态路由；
- 前端权限只比较 Pinia 中的角色字符串；
- `web/src/views` 尚未接入现有 `v-permission` 指令；
- 后端虽按 `menu.perms` 鉴权，现有权限种子并未覆盖页面所需全部辅助接口；
- 因此前端角色授权页、后端 RBAC 和真实页面/按钮没有形成闭环。

实施契约：

1. 登录和会话完成 A02 对齐；
2. 用户信息接口返回等价的菜单树和按钮权限集合；
3. 前端启用服务端菜单并动态注册路由；
4. 每个页面操作绑定权限字符；
5. root 权限为 `['*']`，普通角色按授权联集；
6. 使用普通角色做一次菜单可见、按钮可见、接口允许/拒绝的端到端验收。

参考前端证据：

- `/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/admin/src/stores/modules/user.ts`；
- `/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/admin/src/router/index.ts`；
- `/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/admin/src/permission.ts`；
- `/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/admin/src/install/directives/perms.ts`。

Peanut 证据：`web/src/config/settings.json`、`web/src/hooks/permission.ts`、`web/src/directive/permission/index.ts`、`web/src/components/menu/use-menu-tree.ts`。

## 3. P0：管理员、角色、菜单

### 管理员

完整契约包含：账号/名称/角色筛选、重置、导出、分页；头像、部门、岗位、角色、登录时间/IP；新增/编辑的密码确认、状态和多端登录；角色/部门/岗位关联事务；禁用、删除或角色变化后的会话失效。

### 角色

完整契约包含：分页、管理员人数、名称唯一、独立授权流程、父子联动/全选/展开、使用中不可删除、授权变化后的权限缓存失效。

### 菜单

完整契约包含：M/C/A 类型、路径、组件、权限字符、选中菜单、路由参数、缓存、显示、状态；子菜单/角色占用删除保护；动态路由和按钮权限的运行时闭环。

## 4. P0：用户与财务

### 用户

LikeAdmin 用户契约：

- `account/nickname/mobile`、注册时间、注册渠道筛选；
- 分页和导出；
- 详情显示注册来源、注册时间、最近登录；
- 编辑账号、真实姓名、性别、手机等信息；
- 余额调整使用 action（增加/扣减）、num、remark。

Peanut 当前 Member 模型使用 `sn/email/birthday/status/balance/points/tags`，提供后台新增/禁用和 signed amount 余额调整，不能通过字段改名视为等价。会员标签可作为扩展保留，但不能替代 LikeAdmin 用户模型。

### 财务

必须补齐：

- 余额变动类型动态字典；
- LikeAdmin 的 account/mobile/change_type/source_sn/left_amount 等业务语义；
- 充值记录发起退款；
- 失败退款重新退款；
- 退款日志查看；
- 相关状态流转、权限字符、关联单据和幂等规则。

## 5. P1：内容、素材与消息

### 文章

两端已有 CRUD 骨架，但参考字段为 `cid/title/desc/abstract/image/author/sort/click_virtual/is_show/content`，Peanut 当前使用 `cate_id/intro/click_num` 等不同语义。需同时对齐列表参数、发布状态和真实点击/虚拟点击口径。

### 素材

分类、列表、上传、删除、移动、重命名较接近。重点验收上传返回、类型过滤、批量操作、权限和引用中素材删除约束。

### 消息

LikeAdmin 是“业务通知场景 + 多载体开关/模板内容 + 短信渠道”的场景驱动模型；Peanut 当前是通用模板 CRUD、发送日志和 SMS/SMTP 渠道。发送日志和 SMTP 可作为扩展保留，但必须实现参考的场景、模板提示、启停和渠道配置契约。

## 6. P1：网站、用户、支付与系统设置

当前已确认缺口：

- 网站信息中的后台/H5/PC 多组 Logo、标题、描述和关键词；
- 备案、服务/隐私协议、站点统计；
- 用户默认头像、登录方式、强绑手机、协议和第三方登录；
- 用户充值开关、最低金额；
- 支付方式与支付渠道配置分离；
- 系统维护的定时任务、日志、缓存、环境等细节；
- 代码生成器及其数据表、预览、生成和下载流程。

Peanut 的客服、交易设置、通知日志等额外能力可以保留，但不能抵消参考缺失项。

S01 已按上述边界完成并封存：网站/用户配置、支付渠道、六终端 11 场景、充值订单与预支付、微信三场景登录和三端消费均通过最低充分验收。当前 canonical 契约及证据索引见 `docs/likeadmin-system-settings-contract.md`。

## 7. P1：渠道

Peanut 当前单页启用/AppID/Secret 配置不足。完整契约至少包括：

- H5 状态和关闭跳转；
- 小程序名称、原始 ID、二维码、AppID/Secret 和服务器域名；
- 公众号名称、二维码、Token、AESKey、加密方式和业务域名；
- 公众号菜单保存/发布；
- 关注回复、关键词回复、默认回复的 CRUD、状态和匹配规则；
- 微信开放平台配置。

## 8. P1：装修

LikeAdmin 装修是组件化页面数据模型，包含移动端页面组件、底部导航、系统风格、PC 页面及详情。Peanut 当前 `banners/notice/notice_show/hot_show/news_show` 的单配置不是等价实现。

完整实现需覆盖：页面实体、组件 schema、组件排序/增删/配置、预览、保存/发布、底部导航、系统风格、PC 页面和所需文章/链接选择接口。

## 9. P2：开发工具与模板

业务域对齐后实施代码生成器和模板示例。代码生成器需先盘点其 10+ API、表元数据读取、字段配置、预览、生成、下载及权限；模板示例按参考的富文本、上传、图标、文件/链接选择器等实际能力逐项验收。

## 10. 逐模块固定盘点模板

每个模块必须填写：

1. 菜单节点和权限字符；
2. METHOD + API + 登录/RBAC 要求；
3. Controller、Validate、Logic/Lists、Model 路径；
4. 请求字段、类型、默认、枚举和跨字段校验；
5. 事务、幂等、缓存、事件和状态副作用；
6. 筛选、排序、分页、导出和派生字段；
7. 表字段、索引、关系和软/硬删除；
8. 具体错误 code/msg 和失败回滚；
9. 参考实现自身的异常或不一致；
10. Peanut 的兼容决策及一次最小 API/浏览器验收。
