# PB07 支付 Host 合同

> 状态：Accepted（支付 Host、Tenant Channel Grant、部分/多次退款幂等）
>
> 应用前置提交：`4e67b9cff2bae5dc743394b9f97905ec66219e39`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB07-PAYMENT-HOST-001`、`PAYMENT-DYNAMIC-PAY240DYN-001`

## 1. 采用决策与所有权

核心公开包没有支付订单、商户预支付、回调验签、余额结算或退款 Runtime。`IntegrationSecurity` 是 Tenant 机器身份、出站 Webhook 和会话设备控制候选，既不等价于商户支付，也没有 Peanut Admin 下游采用授权；本片不升级依赖、不 deep import、不修改核心。

应用 Payment/Finance Module 唯一拥有 `pa_config(type=pay/recharge)`、六终端支付场景、充值订单、微信/支付宝预支付、匿名渠道回调、充值结算、退款与对账。`PaymentServiceFactory` 是唯一 Payment Host：只由它装配预支付、回调 parser、退款 gateway 与生产/测试 transport。`RechargeApplicationService` 继续拥有产品订单与余额状态机，不把产品状态写入核心。

## 2. 收款与结算合同

1. 管理端支付配置字段白名单、完整性和密钥掩码由 `PayConfigApplicationService` 唯一维护；`******` 不覆盖原密钥，整组配置原子保存。管理端以 `web/src/modules/official-payment/api.ts` 为唯一支付 facade。
2. 用户只能为当前会员创建/读取本人充值单；终端、开关、金额上下限、启用渠道和默认渠道全部由服务端复核。
3. 预支付锁定本人未支付订单并生成唯一请求号；微信/支付宝参数只从服务端订单和配置生成，客户端不能提交通知结果或结算金额。
4. 微信回调校验时间窗口、平台证书序列号、RSA 签名、AES-GCM、商户号和 AppID；支付宝回调校验 RSA2、AppID 和 SellerID。只有标准化可信 `PaymentEvent` 能进入结算。
5. 结算在订单行锁内校验 CNY、金额、渠道和第三方交易号；相同已支付订单/同流水精确重放幂等，不同流水冲突；唯一索引阻止交易号跨订单复用。余额和流水仍由 `MemberBalanceService` 在同一事务写入一次。

## 3. Tenant 渠道复用合同

1. 每个 Tenant 仍拥有自己的支付场景、订单、退款记录和结算归属；共享外部渠道账户不改变订单和账目的 Tenant 归属。
2. `External Channel` 是渠道账户与凭据引用的唯一 owner，真实密钥只存在于 `credential_ref` 对应的密钥系统；支付场景和订单只保存被授权的渠道引用与授权快照。
3. `Tenant Channel Grant` 是唯一复用入口，显式记录 Tenant、渠道账户、允许操作、有效期和撤销状态；没有有效 grant 时，预支付、回调和退款均 fail closed。
4. 预支付按订单 Tenant 读取场景并校验 grant；回调按渠道账户和外部订单定位候选后，仍须核对订单 Tenant、场景、金额和签名，不能把结果写入其他 Tenant。
5. 撤销 grant 只拒绝新的收款和退款动作，不删除或改写历史订单、退款记录和账目；多个 Tenant 共享渠道不能通过复制配置或全局默认值绕过显式授权。

## 4. 退款与外部结果合同

- 首次退款在本地事务内锁订单、单次扣余额并建立主 record/log，提交后才调用外部渠道；明确失败进入 ERROR，可能已受理但结果未知时保持 ING，不能伪造成功。
- 部分/多次退款由 Finance Host 独立拥有；每次退款先生成独立 `RefundRecord.sn` 并作为余额流水 source，充值入账继续使用充值订单号。`PAYMENT-DYNAMIC-PAY240DYN-001` 已验证部分退款、重复请求与失败重试不会重复扣款，也不会与充值流水的唯一约束冲突。
- 首次请求、失败重试和 `refund:reconcile` 的 Provider refund/query 幂等键统一使用稳定的 `RefundRecord.sn`；`RefundLog.sn` 只标识本地 attempt。失败重试复用同一 record，只新增 attempt log，不再次扣余额；MySQL 命名锁覆盖外部请求周期。
- Provider 调用保持在数据库事务外；调用返回后短事务锁定 record/log，把本地业务结果与现有幂等 receipt finalize 一并提交或回滚。`refund:reconcile` 只收敛当前 ING record 的最近 ING log。
- 退款 gateway 与预支付/回调共用 `PaymentServiceFactory`、`PaymentTransportInterface` 和 `PaymentCrypto`；旧静态 `RefundGatewayService` 退出，不保留第二条签名/HTTP 路径。
- 微信预支付、退款请求和退款查询都必须用配置的平台证书验证响应时间戳、nonce、序列号和 RSA 签名。支付宝退款/查询必须验证响应节点原文的 RSA2 签名。
- 数据库只保存 Provider 回执白名单和截断后的标量，不保存完整响应、证书、私钥、APIv3 密钥、请求 Authorization 或个人收款账户。

## 5. Host/override 与停止线

生产默认由 `PaymentServiceFactory` 从 `pa_config` 装配 `CurlPaymentTransport`；聚焦验收可构造 Factory 并注入内存 transport，不允许 gateway 自建模拟成功分支。当前没有获批核心支付 override key，因此不得把应用支付包装为虚假的核心消费，也不得修改 `vendor/`、Composer/npm 锁或核心仓。

本片不新增支付渠道、订单类型、分账、提现、自动重试、对账 UI 或真实商户配置；不修改 OAuth、公众号、通知、PB08A 品牌输入、`init.sql` 或 SaaS。真实预支付、真实回调、证书轮换、商户后台审核与资金到账只属于部署 smoke，不能由本地测试宣称完成。

## 6. 精确写集

Runtime 白名单为 `server/app/common/service/payment/**`、退款调用方 `server/app/Modules/Official/Payment/Application/RechargeAdministrationService.php`、`server/app/command/RefundReconcile.php`，以及删除旧 `server/app/common/service/RefundGatewayService.php`。Web 只删除 `web/src/api/app.ts` 的未消费支付 facade。

证据/状态白名单为 `server/tests/Productization/PaymentHostTest.php`、CI、本合同、产品化计划、能力图、应用发布契约、`AGENTS.md` 及支付相关用户/开发/部署文档。禁止修改订单/余额 schema、路由、页面、核心仓、依赖目录、封存 S01/F02 证据或其他领域。

## 7. 测试 owner 与一次最低验收

`PB07-PAYMENT-HOST-001` 不连接数据库、不访问网络、不写文件。一次运行证明：

1. 内存 RSA 证书下微信平台响应和支付宝响应验签成功，篡改内容失败。
2. Factory 是预支付、回调和退款的唯一装配点；旧退款服务和重复 Web facade 已退出。
3. 微信预支付/退款都强制响应验签；回调和结算源码保留身份、金额、币种、渠道、流水、行锁与唯一索引边界。
4. 退款只保存安全回执，调用方不持久化原始 Provider 响应；应用支付 owner 不 deep import 核心。
5. 只读绑定封存 S01 单次入账/重复回调和 F02 首次单退款/单扣款证据，并保留 `real_merchant_called=false` 的外部停止线。

数据库动态 owner `PAYMENT-DYNAMIC-PAY240DYN-001` 另行固定两个 Tenant 共享同一渠道、跨 Tenant 回调拒绝、撤销 grant 后拒绝新请求，以及部分退款/重复请求幂等；`MT03-RECHARGE-REFUND-TENANT-001` 在登记隔离库用假 Provider 覆盖已受理后异常、失败重试、reconcile、receipt finalize 回滚、重复请求重放与 Tenant 隔离。真实 Provider 仍不在本地合同范围。

固定命令：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/PaymentHostTest.php
```

另运行一次变更 PHP lint、一次 Web typecheck 与最终 `git diff --check`。不重跑 S01/F02、数据库/API、真实商户、浏览器、核心候选或已完成 PB05/PB07 通知测试。

## 8. 实施证据

- 应用前置提交为 `4e67b9cff2bae5dc743394b9f97905ec66219e39`；核心只读基线保持 `7fbd445d8fa547830b7782a7ac147d9ed414e0fd`，核心及既有 `.playwright-cli/` 未触碰。
- `PaymentServiceFactory` 已统一装配预支付、回调和退款；旧 `RefundGatewayService` 与重复 Web 支付 facade 已退出。微信商户响应补齐平台证书验签，退款回执改为字段白名单。
- 2026-08-11 一次最低验收：变更 PHP 文件 lint 通过，`web` 的 `pnpm type:check` 通过；`PB07-PAYMENT-HOST-001` 首次因测试 marker 多余反斜杠失败，机械修正后唯一允许重跑输出 `passed`。PHP lint 与 Web typecheck 未重跑。
- PR #240（merge `d1d9e474f7c80c1199c1c556c1f1f2baba525879`）落地 `pa_payment_tenant_channel_grant`、Tenant-owned 授权快照、跨 Tenant 回调拒绝和独立退款流水身份；`PAYMENT-DYNAMIC-PAY240DYN-001` 已通过，隔离验收库已按登记清理。
- S01/F02、真实商户和浏览器没有因本文收口重复运行；真实 Provider 交易仍是后置部署 Gate。
