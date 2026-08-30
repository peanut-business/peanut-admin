# PB07 OAuth 与外部渠道 Host 合同

> 状态：Accepted Target
>
> 日期：2026-08-11
>
> 应用测试 owner：`PB07-OAUTH-CHANNEL-HOST-001`

## 1. 所有权决定

微信 OAuth、公众号、小程序、开放平台及其 Peanut Admin 会员绑定、首次资料补全和端回跳属于应用 Channel/OAuth Module。核心 `integration-security` 的 Tenant 机器身份、Webhook 与会话能力不是产品会员 OAuth，当前固定核心提交 `7fbd445d8fa547830b7782a7ac147d9ed414e0fd` 也没有 Peanut Admin 下游采用授权。本片不修改核心、不升级依赖、不 deep import，且不把产品表、场景或路由迁入核心。

应用唯一 OAuth 生产链为：`OAuthController → OAuthApplicationService → OAuthTransportInterface → WechatOAuthTransport`。`OAuthApplicationService` 是 `pa_oauth_attempt`、`pa_oauth_principal`、`pa_oauth_identity`、`pa_oauth_completion_ticket` 及会员身份关联的唯一 writer；管理端 `MiniProgramApplicationService`、`OfficialAccountApplicationService`、`OpenPlatformApplicationService` 分别拥有 `mnp_setting`、`oa_setting`、`open_platform` 凭据。设置聚合页继续保留，H5、小程序、公众号、公众号菜单/回复和开放平台页面不得因旧 Channel CRUD 退出而删除。

## 2. 浏览器回跳合同

微信平台登记的 canonical callback 是应用 API 的固定桥接入口，而不是静态客户端的部署根路径：

| 场景 | 微信 redirect URI | 桥接后的客户端路径 |
|---|---|---|
| `open_pc` | `/api/oauth/wechat/redirect/pc` | `/pc/oauth/callback` |
| `oa` | `/api/oauth/wechat/redirect/official-account` | `/mobile/#/pages/oauth/callback?scene=oa` |

`OAuthBrowserCallbackService` 是唯一映射点。桥接只允许转发 `code`、`state`、`error`、`error_description`，不接受目标路径或 completion ticket，因而不形成开放重定向。API `callback` 仍负责消费 state、调用唯一 transport 和产生登录结果；桥接本身不消费 state、不写身份表。反向代理必须把外部 HTTPS scheme 与 Host 正确传给 PHP，否则生成的登记 URL 不可信。

state 和 completion ticket 均由 32 字节随机值生成，只保存 SHA-256，600 秒过期并在行锁事务内单次消费。UniApp/H5 的 completion ticket 只暂存在 session/端内 storage，进入补全页即删除，不再进入 URL、历史记录或常规访问日志；它仍不是会员 token。

## 3. 唯一实现与升级责任

无消费者的 `ChannelController`、`ChannelLogic`、`setting/channel/config|save` API 及 Web `ChannelConfig` facade 退出。`oa_setting/open_platform/mnp_setting` 是微信渠道唯一配置模型；旧 `channel/wechat_*` 和 QQ 字段不得被重新创建。公众号 Runtime 当前只支持签名后的明文 XML，未实现 AES 解密，因此管理端、API 和校验器删除 `encoding_aes_key/encryption_type` 写入口，并明确要求微信平台选择明文模式。

迁移 `20260811-oauth-channel-host.sql` 只删除 `pa_config` 中以下已退出且可能包含敏感值的精确行：旧 `channel` 微信/QQ 九字段及 `oa_setting` 的 AES 两字段。它不删除公众号、小程序、开放平台配置、菜单、回复、OAuth 身份、会员或封存验收数据；部署前仍应按常规数据库备份。

## 4. 白名单与停止线

Runtime 白名单为 OAuth controller/logic/transport/model、三个现有渠道 Setting Logic、公众号明文回调、PC/UniApp OAuth 页面与 API、两条 callback bridge route，以及本片精确迁移。测试/状态白名单为 `server/tests/Productization/OAuthChannelHostTest.php`、CI、本合同、产品化计划、能力图、发布契约、`AGENTS.md`、README，以及用户/开发/部署文档及 docs-site 镜像。

停止线：不实现公众号 AES、QQ、移动 App OAuth、解绑、自动账号合并、核心采用、真实微信调用或 SaaS；不修改 `vendor/`、`node_modules/`、`init.sql`、核心仓和封存 S01/CH02/CH03 证据。真实 AppID/AppSecret、微信后台登记域名、回调白名单及低风险账号 smoke 属部署验收，缺失时必须标注“未验证”，不能宣称生产 OAuth 可用。

## 5. 最低验收

`PB07-OAUTH-CHANNEL-HOST-001` 不连接数据库、不访问网络、不写文件，一次证明：

- 两个 canonical callback 与固定客户端目标、查询白名单；
- state/ticket 的随机、哈希、TTL、行锁和唯一 transport 静态不变量；
- 旧 Channel Runtime/Web facade 与 AES 写入口已经退出；
- 清理迁移只命中精确退役字段；
- 绑定封存 S01 OAuth、CH02 小程序与 CH03 渠道证据，并保留其“未调用真实微信”的范围声明。

命令：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/OAuthChannelHostTest.php
```

PHP lint、Web/PC/UniApp typecheck 只覆盖本片变更的静态完整性；不重跑数据库、浏览器、S01、CH02、CH03 或真实微信。

## 6. 验收记录

- 2026-08-11：变更 PHP 文件 lint、`web` 的 `pnpm type:check`、`uniapp` 的 `npm run type-check` 与 `PB07-OAUTH-CHANNEL-HOST-001` 一次通过。
- PC 源码未变更，未重复执行 PC typecheck；数据库迁移、S01/CH02/CH03、真实浏览器和真实微信均未重跑。
