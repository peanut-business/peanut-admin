# Peanut Admin 服务执行状态与业务回报

机器可读事实源：[resources/service-registry.json](../../resources/service-registry.json)。状态以固定提交、最低验证、数据 owner 和 `dev` 合入事实为准，不以对话 idle 代替。

## 当前总览

| 数量 | 状态 |
| ---: | --- |
| 14 | 已登记服务 |
| 13 | 已完成并合入 `dev` |
| 1 | 待验收：统一存储 |
| 0 | 明确产品决策阻塞 |

## 当前队列

| 服务 | 状态 | 当前结果 | 下一步 |
| --- | --- | --- | --- |
| Tenant 配置与应用设置 | complete | TenantSettings Query/Commands 和 bootstrap 合同已合入 `dev` | 新业务继续调用设置合同 |
| 管理身份与 RBAC 授权 | complete | Tenant、Module、路由登记和 root 隔离已验收并合入 `dev` | 继续观察授权语义回归，不新增绕过 |
| 字典与参考码 | complete | 系统参考码与租户字典读取合同已统一 | 新业务优先调用字典合同 |
| 外部渠道绑定 | complete | `pa_external_channel_binding` 唯一 owner 已固定 | 通知、支付、OAuth 只通过合同访问 |
| Plugin/Module 治理 | complete | canonical/manifest digest 和 Module 执行身份已固定 | 不重复修改共享 `plugins.lock` |
| Tenant 幂等命令 | complete | 余额、退款等命令已使用统一幂等执行器 | 异步业务继续复用 |
| 统一审计事件 | complete | AuditEvent、actor、resource、trace 和脱敏规则已统一 | Task、导入导出继续接入 |
| 统一存储 | awaiting_acceptance | PR #232 候选 `8011271…` / tree `e3360e7…` 已完成密文凭据、路由、对象账本和静态验证；采用全 COS 权限 | 完成真实 COS 生命周期与迁移备份恢复后合入 `dev` |
| 通知与验证码 | complete | development 固定 `1234`；其他环境走真实 Provider；验证码哈希写入 `pa_notice_log` | 真实短信 Provider 验收属于后置，不阻塞本轮 |
| 会员账户与 CRM | complete | 会员身份、资料、标签和 Tenant 隔离已合入 `dev` | 存储合入后回归头像/文件路径 |
| 支付、充值与退款 | complete | 租户渠道授权、订单快照、撤销和退款账本已合入 `dev` | 真实支付 Provider 验收属于后置 |
| OAuth 与微信渠道 | complete | state/ticket locator、Provider 绑定和 Tenant 隔离已合入 `dev` | 存储合入后回归头像/文件路径 |
| Tenant 任务与异步执行 | complete | Task Runtime、幂等、停用负向和结果下载隔离已通过 | 导入导出继续消费 |
| 导入导出 | complete | CSV 异步合同与既有 XLSX 并存，Tenant-private 结果已验收 | 存储合入后回归对象交付 |

## 统一存储当前停止线

- PR：[统一存储 #232](https://github.com/peanut-business/peanut-admin/pull/232)
- 候选：`80112717124659006a09a6fb7276662704428d29`，tree `e3360e71…`
- 已完成：应用密文保存 SecretId/SecretKey、部署主密钥外置、掩码返回、解密失败拒绝调用、Tenant 对象账本、PHP lint、diff-check 和密文往返。
- 尚缺：COS 上传/下载/删除/read-after-delete；破坏性迁移前的备份恢复与账本核对。

## 短信配置事实

development 只用于本地和内部验收，验证码固定为 `1234`，发送回执为模拟成功。非 development 生成随机验证码并调用租户选择的真实 Provider。验证码只保存哈希；Provider 配置目前由通知服务写入 `pa_external_channel_binding.config_json`，接口返回时掩码。真实凭据录入、Provider 回执和安全语义属于后置验收。

## 完成标准

只有当统一存储完成上述两类证据并合入 `dev`，主控完成依赖服务的受影响路径回归，且本登记与状态文件提交到 `dev` 后，本轮“全部服务收口（不含后置业务）”才算完成。
