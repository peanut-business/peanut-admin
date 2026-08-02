# LikeAdmin 渠道与装修业务契约

> 基线：LikeAdmin 标准版 1.9.4  
> 目标：冻结 CH01、CH02、CH03、DE01、DE02 的业务契约，供 Peanut Admin 分批实现与验收。  
> 原则：复刻业务能力和可观察逻辑；Peanut 后端继续使用现有 ThinkPHP 分层、`code=20000` 响应封装和 camelCase 分页字段，不照搬 LikeAdmin 的响应壳或已确认缺陷。

## 1. 公共接口边界

LikeAdmin 成功响应为 `{code,show,msg,data}`，成功码为 `1`；列表 `data` 为 `{lists,count,page_no,page_size,extend}`，管理端请求拦截器直接解包 `data`。

Peanut 成功响应保持 `{code,msg,data}`，成功码为 `20000`；列表保持 `{lists,count,pageNo,pageSize}`。业务字段、规则和状态须一致，响应 envelope 与命名风格沿用 Peanut 现有规范。

## 2. CH01：H5 渠道配置

### 2.1 页面与接口

- 读取：`GET channel.web_page_setting/getConfig`
- 保存：`POST channel.web_page_setting/setConfig`
- 权限字符分别为上述读取、保存接口字符。

### 2.2 数据契约

| 字段 | 类型/取值 | 默认 | 行为 |
|---|---|---|---|
| `status` | `0/1` | `1` | H5 渠道总开关 |
| `page_status` | `0/1` | `0` | 关闭后 `0` 空白页、`1` 跳自定义链接 |
| `page_url` | string | `''` | 自定义跳转地址 |
| `url` | string，只读 | 当前域名 + `/mobile` | H5 访问地址 |

配置保存到 `web_page/status`、`web_page/page_status`、`web_page/page_url`。移动端启动时读取配置：启用则正常进入；关闭且 `page_status=1` 时跳转 `page_url`，否则进入空白页。

LikeAdmin 验证器只校验 `status|required|in:0,1`，但逻辑会无条件读取另外两项。Peanut 应补齐稳定的请求结构，但不得改变上述开关语义。

### 2.3 Peanut 实现与验收状态

CH01 已完成。Peanut 在既有 `/app-setting/channel` 页面提供 H5 配置，后端按 `Controller → Validate → Logic → ConfigService` 分层实现读取与事务保存；公开配置免登录提供 `web_page`，H5 每次启动消费渠道状态。关闭后支持空白页和自定义 `http/https` 跳转，访问地址由当前域名派生且不持久化。

Peanut 对 `status/page_status` 执行严格枚举校验，并在自定义跳转模式下要求有效绝对 URL；非法输入不会部分更新三项配置。这些可靠性约束不改变参考业务结果，也不复制 LikeAdmin 的无事务、非法 URL 和请求失败绕过缺陷。

双端真实浏览器已核对字段、默认值、策略显隐、保存入口，以及 Peanut H5 的启用、空白页和跳转三种结果。API/数据库批量验收覆盖公开/管理配置一致、普通角色默认拒绝、授权、撤权、非法输入不变量和精确清理。证据见 `output/playwright/ch01/`。

## 3. CH02：小程序配置

### 3.1 页面与接口

- 读取：`GET channel.mnp_settings/getConfig`
- 保存：`POST channel.mnp_settings/setConfig`
- 权限字符分别为上述读取、保存接口字符。

### 3.2 编辑与派生字段

编辑字段：

- `name`
- `original_id`
- `qr_code`
- `app_id`
- `app_secret`

只读派生域名：

- `request_domain`
- `socket_domain`
- `upload_file_domain`
- `download_file_domain`
- `udp_domain`
- `business_domain`

保存时二维码绝对 URL 转为文件 URI。LikeAdmin 仅强制 `app_id/app_secret`；微信 SDK 实际也只消费这两项。Peanut 可保留现有 `wechat_mini_status` 作为扩展，但不能用它替代名称、原始 ID、二维码和域名展示。

参考实现用 `SERVER_NAME` 并硬编码 `https/wss/udp`，可能丢端口；Peanut 应保持展示语义，不复制该部署缺陷。参考前端声明但未实际使用 `tcpDomain`，不纳入契约。

### 3.3 Peanut 实现与验收状态

CH02 已完成。Peanut 使用单一 `mnp_setting` 配置模型维护名称、原始 ID、二维码、AppID 和 AppSecret；二维码保存相对 URI、读取绝对 URL，五项配置在同一事务内保存。旧 `channel/wechat_mini_*` 三字段已退出业务模型，不建立双字段兼容层。

管理页复用 Peanut 素材选择器，并展示 request、socket、uploadFile、downloadFile、UDP 和业务域名；派生值保留当前主机端口，避免参考实现丢端口。AppID/AppSecret 去空白后必填，非法输入不会部分写入。

双端真实浏览器已核对全部字段、域名、素材入口、复制动作和保存权限。Peanut API/数据库验收覆盖二维码 URI/URL 转换、六类派生域名、严格校验与原子不变量、查看/保存权限的拒绝→授权→撤权、单一配置模型和精确清理。证据见 `output/playwright/ch02/`。

## 4. CH03：公众号与开放平台

### 4.1 公众号基础配置

- 读取：`GET channel.official_account_setting/getConfig`
- 保存：`POST channel.official_account_setting/setConfig`

字段：

`name,original_id,qr_code,app_id,app_secret,url,token,encoding_aes_key,encryption_type,business_domain,js_secure_domain,web_auth_domain`

`encryption_type`：`1` 明文、`2` 兼容、`3` 安全；验证为必填且只能取 `1/2/3`。`app_id/app_secret` 必填。配置写入 `oa_setting/*`；微信 SDK 当前只消费 `app_id/app_secret/token`。

参考页面展示 Token 默认值 `likeshop`，逻辑并未提供该默认值；`encoding_aes_key/encryption_type` 虽保存但未注入 SDK。Peanut 不应伪造其已生效。

### 4.2 自定义菜单

接口：

- `detail`
- `save`
- `saveAndPublish`

节点 Schema：

```text
{
  name,
  has_menu,
  type: click | view | miniprogram,
  url,
  key,
  appid,
  pagepath,
  sub_button: []
}
```

规则：一级最多 3 个、名称最多 4 字；二级最多 5 个、名称最多 8 字。`click` 必须有 `key`，`view` 必须有 `url`，`miniprogram` 必须有 `url/appid/pagepath`。

`save` 只保存 `oa_setting/menu`；`saveAndPublish` 先调用微信创建菜单，成功后才写本地配置。参考前端只提供 `view/miniprogram`，虽然后端支持 `click`；Peanut 实施时以服务端能力为契约，并避免前后端长度规则不一致。

### 4.3 自动回复状态机

`reply_type`：

- `1` 关注回复
- `2` 关键词回复
- `3` 默认回复

`matching_type`：`1` 全匹配、`2` 模糊匹配。

管理接口覆盖 `lists/add/detail/edit/delete/status`；通用请求字段为 `reply_type,name,content_type=1,content,status`，关键词回复另有 `keyword,matching_type,sort,reply_num=1`。列表按 `reply_type` 筛选，排序为 `sort desc,id desc`。

新增或编辑启用的关注/默认回复时，先禁用其他同类型记录；关键词允许多条启用。微信回调状态机：

1. `subscribe` 事件返回已启用关注回复；
2. 文本消息按 `sort asc` 遍历启用关键词，全匹配或 `stripos` 模糊匹配，首条命中即返回；
3. 未命中返回已启用默认回复；
4. 仍无内容则交给后续处理器。

数据表字段：`id,name,keyword,reply_type,matching_type,content_type,content,status,sort,create_time,update_time,delete_time`。

参考异常需显式保留在验收清单，不能无意复制：

- `reply_num` 出现在 API/校验/UI，但数据表无列；
- `sort` 缺少整数/非负验证；
- 独立 `status` 接口可绕过关注/默认“唯一启用”规则；
- 批量禁用再写入没有事务；
- 回复动作的权限种子和页面按钮权限不完整。

微信回调 `index` 免登录是业务必要路径，不视为权限漏洞。

### 4.4 开放平台

- 读取：`GET channel.open_setting/getConfig`
- 保存：`POST channel.open_setting/setConfig`
- 字段仅 `app_id/app_secret`，均必填；保存到 `open_platform` 配置组。

### 4.5 Peanut 实施状态

CH03 已完成。Peanut 使用 `oa_setting/open_platform` 唯一配置模型，形成配置保存与掩码回读、菜单本地保存与发布入口、关注/关键词/默认回复管理、明文 Webhook、开放平台和权限闭环；旧 `channel/wechat_oa_*`、`channel/wechat_open_*` 已退出运行时，不建立兼容层。`encryption_type/encoding_aes_key` 只作为配置资料保留，管理端明确提示当前 `callback_mode=plaintext`，不宣称 AES 安全模式已经生效。

双系统真实浏览器已核对等价字段和操作结果；受限角色只拥有配置查看权限时保存按钮隐藏，未授权请求明确拒绝。真实微信发布未调用，远端成功后才落本地的顺序由已封存后端假传输验收证明。配置、菜单、临时管理员、角色、会话和操作日志已精确恢复清理，证据见 `output/playwright/ch03/{backend-summary,frontend-summary}.json`。

## 5. DE01：移动端装修

### 5.1 页面实体

`decorate_page`：

| 字段 | 说明 |
|---|---|
| `id` | 主键 |
| `type` | `1` 首页、`2` 个人中心、`3` 客服、`4` PC、实际另有 `5` 系统风格 |
| `name` | 页面名称 |
| `data` | 组件 JSON |
| `meta` | 页面元数据 JSON |
| `create_time/update_time` | 时间 |

接口：

- `GET decorate.page/detail?id`
- `POST decorate.page/save`
- `GET decorate.data/article?limit` 作为文章选择器数据源

保存请求为 `id,type,data,meta?`，直接覆盖目标行。LikeAdmin 没有草稿、独立发布、版本或回滚；保存后移动端立即读取并生效。Peanut 不得臆造“先保存草稿再发布”的状态流转。

### 5.2 编辑器与组件信封

统一组件结构：

```text
{ id, title, name, disabled?, content, styles }
```

首页包含 `page-meta/search/banner/nav/news`；个人中心包含 `page-meta/user-info/my-service/user-banner`；客服页包含 `customer-service`。非 `disabled` 组件可上移、下移和启停；固定组件不能选择；不能新增或删除整个组件。

关键组件 Schema：

- `banner.content`：`enabled,style(1|2),bg_style,data[{is_show,image,bg,name,link}]`
- `middle-banner.content`：`enabled,data[{is_show,image,name,link}]`
- `nav.content`：`enabled,style,per_line,show_line,data[{image,name,link}]`
- `my-service.content`：`style,title,data[{image,name,link}]`
- `user-banner.content`：`enabled,data[{is_show,image,name,link}]`
- `customer-service.content`：`title,time,mobile,qrcode,remark`
- `page-meta.content`：`title_type,title,title_img,bg_type,bg_color,bg_image,text_color`
- `search/news/user-info`：固定组件，`disabled=1,content={}`

图片集合通常限制 1～5 条，PC Banner 为 1～10 条。

Peanut 冻结的业务链接值对象为 `{target_type,target,query?}`，不绑定 LikeAdmin 路由字面值。`target_type` 支持 `shop/article/custom/mini_program`；文章 `target` 为存在且可见的文章 ID；自定义目标必须是 http/https 绝对地址；小程序目标包含页面路径，`query` 包含 `app_id/query/env_version`。

### 5.3 Tabbar

- `GET decorate.tabbar/detail`
- `POST decorate.tabbar/save`

数据：

```text
{
  style: { default_color, selected_color },
  list: [{ id?, name, selected, unselected, link, is_show }]
}
```

规则：总数 2～5 项；首项不可删除、移动、隐藏或修改链接。样式保存到 `tabbar/style`；列表保存时参考实现先全删再批量写入。

参考实现没有后端验证和事务，且“2～5 项/首项固定”只在前端限制。Peanut 必须保留可观察规则，同时用现有验证与事务规范保证原子性。

### 5.4 系统风格

使用 `decorate_page` 的 `type=5`，字段为：

`themeColorId,topTextColor,navigationBarColor,themeColor1,themeColor2,buttonColor`

共 7 套主题，第 7 套为自定义主题；复用页面详情/保存接口。

### 5.5 Peanut 实施状态

DE01 已完成。Peanut 使用 `decorate_page/decorate_tabbar`、五个标准页面、递归 Schema 校验、相对资源 URI、Tabbar 原子保存、移动权限域和匿名消费 DTO；旧 Config `decorate` 五键已退出运行时。管理端固定组件编辑、移动首页/个人中心/客服渲染、七套主题和业务链接执行均已接入。动态 Tabbar 使用 Peanut 自定义组件完整渲染 2～5 个可见业务链接，不依赖原生 `pages.json` 的静态路由槽位。

双系统真实浏览器确认移动 Banner 名称保存后立即出现、主题色实际变为管理端保存值、Tabbar 名称立即更新；LikeAdmin 页面装修、底部导航和系统风格入口及可见规则已核对。H5 开发请求通过同源 Vite 代理访问本地后端，装修数据及 Tabbar 主键/时间戳已精确恢复，证据见 `output/playwright/de01-de02/{backend-summary,frontend-summary}.json`。

参考异常：页面验证器不约束 `id/type/schema`，错误 JSON 可让前端崩溃；详情缺 ID 返回空对象；保存即线上。Peanut 应对输入做可靠验证，但对外保持“保存后立即生效”。

## 6. DE02：PC 装修

- 列表入口：`/decoration/pc`，展示最近更新时间和当前域名 `/pc` 地址；数据接口为 `decorate.data/pc`。
- 编辑入口：`/decoration/pc_details`，编辑 `decorate_page` 的 `type=4`，保存复用 `decorate.page/save`。
- 编辑器在 iframe 上方绝对定位组件，可选择、编辑内容和启停，不支持拖动或增删整个组件。
- 默认只包含 `pc-banner`：

```text
{
  content: { enabled, data: [{ image, name, link }] },
  styles: { position: 'absolute', left, top, width, height }
}
```

- Banner 限制 1～10 张；PC 客户端即时读取 `type=4`，没有发布步骤。

DE02 已完成。Peanut 使用 `type=4` PC 页面实体、严格 `pc-banner` Schema、独立权限域、匿名消费接口、单一 Banner 编辑器和 PC 首页即时渲染。Nuxt SSR 通过私有后端 origin 读取聚合数据，浏览器端继续使用同源代理；真实浏览器确认 Banner 保存后立即出现在 PC 首页，并与 LikeAdmin PC 装修入口完成能力核对，证据见 `output/playwright/de01-de02/frontend-summary.json`。

参考 iframe URL 完全来自查询参数，可加载任意地址；该安全缺陷不得复制。参考权限种子和按钮权限字符也不一致，Peanut 应统一使用实际菜单/API 权限字符。

## 7. 实施批次与文件冲突边界

1. 后端基础：CH01、CH02 可按独立配置逻辑并行；CH03 由单一负责人完成公众号配置、菜单、回复表与状态机；DE01/DE02 共用页面/Tabbar 模型和迁移，由同一后端负责人统一落库。
2. 前端页面：H5、小程序、公众号可按页面目录并行；装修编辑器骨架和预览由单一负责人持有，其他任务只补独立 widget。
3. 消费端：H5 开关、移动装修消费和 PC `type=4` 消费可并行，但必须等 API/Schema 冻结。
4. 公共路由、菜单权限种子、初始化 SQL 和共享响应封装由主线统一修改。

## 8. 最小验收路径

- CH01：读取配置 → 保存开关/关闭页策略 → 重新读取 → 移动端按策略进入或跳转。
- CH02：读取 → 保存资料/二维码/App 凭证 → 重新读取 → 核对派生域名。
- CH03 配置：读取 → 保存 → 重新读取；菜单：本地保存 → 发布微信成功后落库；回复：分别执行关注、全匹配/模糊关键词、默认回退状态机；开放平台：读取 → 保存 → 重新读取。
- DE01：页面详情 → 修改组件 → 保存 → 移动端立即消费；Tabbar 同样验证 2～5 项和首项约束；系统风格保存后立即消费。
- DE02：PC 详情 → 修改 Banner/位置 → 保存 → PC 客户端立即消费。
