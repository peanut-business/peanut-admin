# 外部回调可信 Tenant 路由合同

> 状态：实现候选
>
> 测试 owner：`EXTERNAL-CALLBACK-TENANT-ROUTING-001`

## 调用路径矩阵

| 真实入口 | 可信解析身份 | 验签/消费顺序 | Tenant 业务入口 |
| --- | --- | --- | --- |
| 微信支付通知 | 服务端生成 callback binding key → 微信商户号 + AppID | binding 唯一/active → 该 binding 的证书、APIv3 key 验签解密 → TenantContext | `RechargeLogic::settle` |
| 支付宝支付通知 | 服务端生成 callback binding key → AppID + SellerID | binding 唯一/active → 该 binding 的 RSA2 公钥验签 → TenantContext | `RechargeLogic::settle` |
| 公众号服务器验证/明文 XML | 服务端生成 callback binding key → originalId/AppID | binding 唯一/active → 该 binding token 验签 → TenantContext | Tenant-first reply resolver |
| 公众号/开放平台 OAuth callback | 服务器随机 state hash + provider binding | state 唯一/未用/未过期 → provider binding active → TenantContext → 单次消费 → code exchange | OAuth identity state machine |
| 小程序 code login | 唯一 active 小程序 provider binding | 只有一个 active binding → TenantContext → 使用绑定 AppID/secret exchange | OAuth identity state machine |
| OAuth completion | 服务器随机 ticket hash + 签发时固定 binding ID | ticket 唯一/未用/未过期 → binding/Tenant active → TenantContext → 单次消费 | completion state machine |

`tenant_id` query/body/header 永远不参与解析。零匹配、多匹配、停用 Tenant、停用渠道和签名失败都抛出同一个 `EXTERNAL_CALLBACK_REJECTED` 外部形状；业务状态机只接收 resolver 产生的 `TenantSystemContext`。审计仅保存 provider、binding ID、Tenant ID、operation ID 和不可逆 identity fingerprint，不保存 callback key、state、ticket、密钥、证书或原始载荷。

## 持久化与 Standalone

`pa_external_channel_binding` 是应用唯一外部 provider 配置和 Tenant 绑定。`provider + callback_key`、`provider + identity_hash`、`tenant + provider` 均唯一，绑定必须关联 `pa_tenant`。OAuth completion ticket 固定 `binding_id`，公众号回复固定 `tenant_id`。

fresh-only Schema 只为默认 Tenant 写入六条确定性、禁用且空配置的占位绑定，以保证空库生成结果可重复。`callback_key` 是路由标识，不是验签密钥；外部 provider 的签名/密钥验证仍是授权边界。Runtime 为新 Tenant 首次保存渠道配置时按需创建绑定并生成 32 字节随机路由键；默认 Tenant 首次启用时会把确定性占位键替换为随机键。已启用渠道的普通配置保存不轮换该键，避免破坏已登记的回调 URL。

2.0 只支持 fresh install，不从 1.x 采用渠道配置、旧 completion ticket 或默认 Tenant 映射。新 Tenant 没有渠道绑定时，读取配置返回未配置状态，首次保存再在该 Tenant 内创建；停用 Tenant 不允许读取或建立绑定。运行时没有“找不到就 Tenant 1”、任意 active Tenant 或其他 Tenant 的 fallback。

## 聚焦验收

`server/tests/Multitenancy/ExternalCallbackTenantRoutingTest.php` 使用纯内存 repository/audit，不连接数据库或网络。它覆盖两个 Tenant 同类渠道、伪造 `tenant_id`、未知/重复/停用/坏签名同形拒绝、验签先于状态写、重复通知幂等、错误 Tenant 无状态变化，以及审计 fingerprint 不泄密。canonical Schema 的唯一约束和禁用占位绑定由同组静态断言覆盖；Runtime 键生成还会验证 64 位小写十六进制、连续生成不重复、首次启用替换占位键，以及已启用配置保存不轮换。
