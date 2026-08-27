# 外部 Provider 生产资格合同

Document ID: `pa-docs-architecture-product-closure-provider-qualification`

Status: `current`

Owner: `product-operations`

Audience: `maintainer, architect, ai`

Upstream: `server/app/platform/service/provider/`、
`server/database/migrations/20260828-provider-qualification-evidence.sql`、官方 Payment、Notification、
OAuth Module 与应用统一 Storage 服务。

## 1. 目标与所有权

PC60 把“本地已配置”“外部已连通”“回调已验证”和“已获生产资格”拆成独立事实。Application
Host 拥有 Platform-only 安全聚合与 evidence 账本；Payment、Notification、OAuth 和 Storage
contributor 只读取各自权威配置；真实 Provider probe 及其成功业务操作仍由对应 Module 或受控
资格适配器拥有。

本合同不增加通用测试连接接口，不允许读取页面时外呼，也不授权真实支付、退款、消息发送或
回调伪造。邮件 Provider 当前不存在，固定投影为 `NOT_IMPLEMENTED`。

## 2. Evidence 合同

`pa_provider_qualification_evidence` 是 append-only 安全证据账本。内部记录包含 Provider、scope、
Tenant ID、配置 HMAC digest、证据类型、通过/失败、稳定原因码、Request ID、观察和失效时间；
它不接受秘密、收件人、订单/交易号或原始错误。

Application 内部 `ProviderQualificationRecorder` 是唯一通用写边界。各业务适配器只在真实成功
操作、已验证回调或显式受控资格步骤之后写入 evidence；浏览器没有对应 POST。配置发生变化时
当前 contributor 的 digest 必然变化，旧 evidence 即刻失效；超过 `expires_at` 同样失效，不能
以历史成功冒充当前生产资格。

## 3. 公开投影

`GET /api/platform/v1/ops/providers` 只允许具备 `platform.ops.read` 的 PlatformOperator 调用，
返回 `schema_version`、`generated_at` 和 `providers[]`。每项字段固定为：

- `provider_key`、`category`；
- `scope.type` 和 keyed-HMAC 派生的 opaque `scope.key`；
- `configured`、`connected`、`callback_verified`、`qualified`；
- `credential_rotated_at`、`observed_at`、`expires_at`；
- `status_code`、安全的 `recent_failure` 和 `evidence_digest`。

公开响应不得出现 Tenant ID、配置 digest、凭据、PII、业务交易标识或原始错误。Tenant 之间的
相同 Provider 由内部 Tenant ID 与 scope reference 分隔；公开 scope key 只能用于稳定区分，
不能反推 Tenant 身份。

## 4. 资格判定

生产资格必须同时满足：当前权威配置完整、最新同配置 evidence 尚未过期、connectivity 通过、
production 通过，以及需要回调的 Provider 的 callback evidence 通过。最近同配置失败投影稳定
失败码，但不公开原始平台错误。配置缺失、digest 不匹配或 TTL 过期均为 unqualified。

Platform 页面只呈现这份投影，并明确刷新不会执行 probe。真实生产平台的资格仍逐 Provider
独立执行、授权和留证；通用 Gate 不发送消息，也不发生资金动作。

## 5. 验证边界

最低合同验证使用纯 fake contributor、permission checker 和 evidence repository，证明：

- 配置 digest 变化和 TTL 过期会撤销资格；
- Tenant A evidence 不会提升 Tenant B；
- 无 `platform.ops.read` 时先拒绝，contributor 不执行；
- 公开 DTO 不含内部 digest、Tenant ID 或敏感业务字段；
- 路由只有 GET，四类 contributor 只读且不含外呼/资金调用。

真实数据库 migration、Platform 浏览器和 released-scaffold 组合资格归后续正式产品 Gate；它们
不改变本合同的权限和无副作用边界。
