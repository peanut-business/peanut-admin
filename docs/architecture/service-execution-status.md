# Peanut Admin 服务执行状态与业务回报

机器可读事实源：[resources/service-registry.json](../../resources/service-registry.json)。状态以固定提交、最低验证、数据 owner 和 `dev` 合入事实为准，不以对话 idle 代替。

## 当前总览

| 数量 | 状态 |
| ---: | --- |
| 14 | 已登记服务 |
| 14 | 已完成并合入 `dev` |
| 0 | 待验收 |
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
| 统一存储 | complete | PR #232 已合入 `dev`（merge `c63994e…`，tree 与候选一致）；真实 COS 生命周期、密文往返和迁移备份恢复均通过 | 下游继续通过统一存储合同消费；真实生产对象存储仍按后置发布流程验收 |
| 通知与验证码 | complete | development 固定 `1234`；其他环境走真实 Provider；验证码哈希写入 `pa_notice_log` | 真实短信 Provider 验收属于后置，不阻塞本轮 |
| 会员账户与 CRM | complete | 会员身份、资料、标签和 Tenant 隔离已合入 `dev`；文件媒体 Host 已确认头像上传继续经过 `StorageService` 和 Tenant 对象账本 | 仓库内旧 `MemberUploadTenantWiringTest` 仍检查已退出的旧路由、`uri/storage` 字段并硬编码 root；这是待维护夹具，不是当前业务阻塞 |
| 支付、充值与退款 | complete | 租户渠道授权、订单快照、撤销和退款账本已合入 `dev` | 真实支付 Provider 验收属于后置 |
| OAuth 与微信渠道 | complete | state/ticket locator、Provider 绑定和 Tenant 隔离已合入 `dev`；最新候选上的 OAuth Tenant 隔离回归通过 | OAuth 头像补全继续通过统一文件合同；真实微信凭据仍属后置验收 |
| Tenant 任务与异步执行 | complete | Task Runtime、幂等、停用负向和结果下载隔离已通过 | 导入导出继续消费 |
| 导入导出 | complete | CSV 异步合同与既有 XLSX 并存；最新候选上的 CSV 对象写入 `pa_file_object=ready`、私有下载和跨 Tenant 拒绝均通过 | 既有 XLSX 继续由 `XlsxExportService` 负责；真实生产对象存储仍属后置验收 |

## 统一存储当前完成证据

- PR：[统一存储 #232](https://github.com/peanut-business/peanut-admin/pull/232)
- 候选：`b38f8dd1517ff0e4dc5e11c551f34162b5d83969`；PR #232 已合入 `dev`，merge `c63994e9284f2c335b669bd40147c3ad7cd9aba2`。
- 已完成：应用密文保存 SecretId/SecretKey、部署主密钥外置、掩码返回、解密失败拒绝调用、Tenant 对象账本、PHP lint、diff-check 和密文往返。
- 真实 COS：登记桶 `peanut-admin-storage-acceptance-20260824-1252029442` / `ap-guangzhou` 的 HeadBucket、上传、账本 ready、下载内容一致、删除和 read-after-delete 失败均通过；过程中修复 Qcloud 上传流重复关闭的 PHP TypeError。
- 备份恢复：隔离基线 91 张表已 gzip/SHA-256 校验，恢复到第二个登记 P0-E 隔离库后核心 Tenant/Account/TenantMember 计数一致，再应用存储迁移并核对 `pa_schema_migration=applied`。

## 存储合入后的受影响路径回归

- `PB04-FILE-MEDIA-HOST-001`：通过；上传、删除和 public/private 用途仍由统一 `StorageService`、`StorageRepository` 和对象账本负责。
- `PB07-OAUTH-CHANNEL-HOST-001` 与 `OAuthTenantIsolationTest.php`：通过；state/ticket、Provider 绑定和 Tenant 隔离未因存储合入改变。
- `TaskImportExportTenantIsolationTest.php`：通过；在登记 P0-E 隔离库上应用存储迁移并设置测试 JWT 密钥后，CSV 结果对象进入 `ready`，私有 URL 可生成，跨 Tenant 下载被拒绝。登记账号无 `SUPER` 时原子失败注入按规则记录为 skipped。
- `MemberUploadTenantWiringTest.php`：未作为当前业务失败计入；该历史夹具仍断言已退出的 `route/app.php` 路由、`pa_file.uri/storage` 字段并尝试使用未登记 root 账号。当前代码路径已由文件媒体 Host 合同静态检查覆盖，夹具维护另列为低风险测试清理项。

## 短信配置事实

development 只用于本地和内部验收，验证码固定为 `1234`，发送回执为模拟成功。非 development 生成随机验证码并调用租户选择的真实 Provider。验证码只保存哈希；Provider 配置目前由通知服务写入 `pa_external_channel_binding.config_json`，接口返回时掩码。真实凭据录入、Provider 回执和安全语义属于后置验收。

## 完成标准

统一存储已完成上述两类证据并合入 `dev`；本登记与状态文件同步合入后，本轮“全部服务收口（不含后置业务）”完成。真实短信 Provider、真实支付 Provider 和生产对象存储凭据属于后置验收，不改变本轮完成结论。
