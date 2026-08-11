# PB07 通知 Host 合同

> 状态：Accepted（通知切片）
>
> 应用前置提交：`70c8b712641e44683e3f4f32d0ed329311173612`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB07-NOTIFICATION-HOST-001`

## 1. 采用决策与唯一所有权

核心 `PeanutAdmin\NotificationSms\` 是单一 Composer 包 `peanut-admin/core` 内的公开模块，不是独立包。它已随 Composer Alpha.2 发布，但核心 OVR05 资格与发布批准均明确没有 Peanut Admin 下游采用授权；其 Tenant/member message center、outbox、Task/Job 重试和六张表也不等价于应用当前四个验证码场景。因此本片不得升级锁、deep import 或修改核心 Runtime。

当前唯一 owner 固定为：

- 应用拥有登录、绑定手机、变更手机、重置密码四个产品验证码 scene、触发条件、`pa_notice_scene/log`、阿里云/腾讯云凭据、管理/API/UI 与同步发送结果。
- `NoticeChannelService` 是短信配置、单默认 Provider、原子切换、驱动选择、错误与回执脱敏的唯一应用 Host。
- `VerificationCodeService` 只拥有验证码频控、生成、发送事实、五分钟有效期、校验次数和单次消费；不得自行解析凭据或实例化 Provider。
- 核心候选仍拥有其产品无关 Tenant 通知/outbox 能力；未来只有新的固定候选资格和显式 Peanut Admin 采用决策才能另立迁移合同。

## 2. 产品状态与数据合同

1. 四个 scene code 固定且唯一，管理员只能修改服务商模板 ID、内容和启停，不能新增、删除或改身份。
2. 同一手机号任一 scene 最近 60 秒已有成功发送时拒绝；失败发送不占成功频控。
3. 每次实际发送尝试先创建一条 pending 事实，再更新为 success/fail；快照、Provider、脱敏回执和错误保留在同一记录。
4. 验证始终锁定同 scene、手机号的最近一条成功记录，再检查其是否已消费；不得在新码已消费后回退接受更旧记录。错误增加次数，过期失败，成功一次性标记已验证。
5. 当前同步 OTP 没有自动重试。失败后由用户发起新请求；在 Provider 结果不确定时自动重试可能重复发送，不得伪装成核心 outbox 语义。

## 3. 敏感数据与输出边界

- 新验证码只保存 `password_hash` 慢哈希；发送内容快照中的验证码固定为 `****`。验证使用 `password_verify`，明文只存在于单次 Provider 调用内。
- 升级迁移将历史未消费验证码失效、脱敏旧内容并把 `verify_code` 原位改名为 `verify_code_hash`，不保留双字段。
- API 日志列表/详情使用字段白名单；哈希和 Provider 原始结果同时由 Model hidden 防御，不能序列化给管理端。
- Provider 回执只保留请求/业务流水、状态 code/message；不保存原始手机号、完整响应体或凭据。错误写入前替换手机号和密钥并截断。
- 渠道详情继续用 `******` 表示既有密钥，未知字段拒绝；启用/默认/停用另一 Provider 在同一 `pa_config` 事务完成。

## 4. 退出 Runtime 的扩展

通用 `NoticeTemplate/NoticeService`、SMTP driver、通用模板 CRUD 和邮件配置没有任何产品触发器，管理端页面也已被固定 scene 页面替代。本片删除其路由和可运行代码、移除无发送链的邮件 UI，并通过迁移撤销旧模板 CRUD 菜单关系。

`pa_notice_template` 仅为历史数据保留，不再有 Model、写路由或 sender；历史通知日志可只读关联名称。本片不破坏性删除用户数据。未来若出现真实邮件/站内信消费者，必须先另立产品 scene、Host、安全与迁移合同，不得恢复无触发器的通用入口。

## 5. 权限、事务与 Provider 边界

- 渠道、scene 和日志管理继续经过 Login/Auth/OperationLog；发送 API 按现有匿名/会员场景先由业务 Logic 校验，再调用验证码服务。
- 渠道配置只允许阿里云、腾讯云字段白名单；启用前完整校验，同时最多一个启用且为默认。
- 日志先落 pending 再调用外部 Provider，确保确定性失败有事实；真实送达仍依赖外部账号、签名、模板、额度和网络，本地通过不能宣称手机已收到。
- 本片不新增队列/outbox、批量通知、站内信、邮件、推送、自动重试、国际号码或营销发送。

## 6. 精确写集与禁改集

Runtime/schema 白名单为通知 service/model/logic/controller、`server/route/app.php`、通知安全迁移，以及删除已列明的通用模板/邮件 Runtime。Web 白名单仅为 `web/src/api/notice.ts`、通知渠道页与其双语 locale。

证据/状态白名单为 `server/tests/Productization/NotificationHostTest.php`、CI、本合同、PB03/能力图/应用发布契约/产品化计划、`AGENTS.md`，以及开发指南、用户手册、部署文档及 docs-site 镜像的通知边界同步。

禁止修改核心仓、Composer/npm 锁、`vendor/`、`node_modules/`、`init.sql`、其他路由/菜单/页面、支付/OAuth/渠道业务、PB08A 品牌输入、封存 M01 证据或 SaaS 设计。

## 7. 测试 owner 与一次最低验收

`PB07-NOTIFICATION-HOST-001` 不连接数据库、不发送短信、不写文件。一次运行证明：

1. 验证码慢哈希正确/错误匹配，源码不保存明文，快照脱敏；迁移原位改名并令旧码失效。
2. 只有 `NoticeChannelService` 解析凭据、实例化两种 SMS driver，配置切换原子且回执/错误脱敏。
3. 日志 API 不选择/序列化验证码哈希和原始回执；无消费者模板/邮件 Runtime 与路由已退出。
4. 只读绑定封存 M01 的 scene、频控、四条验证码流程、过期/次数、日志、Provider 状态与权限/清理证据。
5. 应用通知 owner 不 deep import 核心候选。

固定命令：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/NotificationHostTest.php
```

另运行一次 Runtime/测试 PHP lint、一次 Web typecheck 与最终 `git diff --check`。不重跑 M01、LikeAdmin parity、数据库/API、真实短信、核心候选或浏览器。

## 8. 停止线

通过只表示应用四个 OTP scene、同步 SMS Host、敏感数据和测试 owner 已固定。它不授权核心 NotificationSms 消费，不宣称 Tenant message center、outbox/retry、真实送达、邮件/推送或批量通知，不开始 PB07 支付/OAuth、PB08A 或 SaaS。

## 9. 实施证据

- 应用提交前工作树以 `70c8b712641e44683e3f4f32d0ed329311173612` 为前置；核心只读基线保持 `7fbd445d8fa547830b7782a7ac147d9ed414e0fd`，仅有既存 `.playwright-cli/`，未修改核心或依赖锁。
- `NoticeChannelService` 已成为配置、Provider 选择与脱敏的唯一 Host；验证码明文列原位升级为慢哈希列，历史值失效并脱敏；通用模板/SMTP 的无消费者 Runtime 已退出，历史表保留。
- 2026-08-11 一次最低验收：变更 PHP 文件 lint 通过，`web` 的 `pnpm type:check` 通过，`PB07-NOTIFICATION-HOST-001` 输出 `passed`。封存 M01、数据库/API、真实短信和浏览器未重跑。
- 本合同只接受 PB07 通知切片；支付、OAuth 与外部渠道仍需各自合同和唯一测试 owner，PB07 尚未整体完成。
