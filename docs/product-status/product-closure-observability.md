# 产品闭环执行可观测面板

Document ID: `pa-docs-product-status-product-closure-observability`

Status: `current`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Upstream: [`产品闭环执行任务队列`](../plans/product-closure-execution-queue.md)、产品能力账本、
固定提交、PR 和已完成最低验证。

> - 更新时间：2026-08-28
> - 执行队列：[`../plans/product-closure-execution-queue.md`](../plans/product-closure-execution-queue.md)
> - 所有权决定：[`../architecture/product-closure-ownership-and-adoption.md`](../architecture/product-closure-ownership-and-adoption.md)
> - 产品能力唯一事实源：[`capability-ledger.json`](capability-ledger.json)

## 1. 作用和边界

本页回答当前执行到哪里、形成了什么固定交付物、实际验证了什么、还缺什么 Gate、下一项
可领取什么。它不是第二份产品能力事实源：稳定能力完成后才更新 `capability-ledger.json`，
正式 Release 时再冻结 `releases/*.json`。

## 2. 当前事实快照

| 项目 | 当前事实 | 对闭环的含义 |
|---|---|---|
| Application | 固定资格候选 `f6378f255241cbde25f374a8a0218fda4616c1ce`（tree `184033c89425a0aa08f5591ce7f6a82735d47ad4`）；后续纯文档治理不改变该 Runtime 身份 | PC00—PC70 全部完成；`pc70q14` P0-E 7/7 通过，当前没有未完成的产品闭环任务 |
| Core | `origin/dev@8608dafe30467c442000ce408b106d8750ffd766` | 文档治理已合入；Runtime 最近发布身份仍由 PC02 核验 |
| 安装 | CLI 空库安装、一次性 Web 向导、首次运行准备清单、3.x migration 链、只读健康和脱敏诊断入口存在 | 阶段 B 已完成，固定派生应用的双模式 fresh、Compose 与浏览器组合资格通过 |
| 运维 | Platform 已采用 Core Ops 状态/任务与维护合同，提供诊断、备份、固定隔离恢复、维护计划/关闭、全局写门禁、升级就绪与持久化升级执行中心 | PC42 已把固定输入、停止点和 recovery pointer 串联为单实例升级纵向闭环 |
| 备份 | 生产登记有 DB + `php-storage` 配对门禁；应用已有单一受信 Provider、schema 1 manifest、任务/证据账本、受信 worker 和 Platform 备份中心 | `backup_010108…` 已通过真实新目标恢复，不再把合同 smoke 冒充可恢复证据 |
| 恢复 | PC32 已把真实配对制品恢复到无监听隔离目标，验证 Schema、代表身份/Tenant 数据、文件卷、受保护 Runtime 不变与成功零残留 | released-scaffold 组合资格已通过；正式生产覆盖恢复仍需独立明确授权 |
| 升级 | scaffold、migration、唯一 deploy-release、固定 target/source/Module/备份/恢复/维护检查，以及登记 worker 驱动的升级任务状态机均已形成 | released-scaffold 组合资格已通过；跨实例升级仍归独立运营平台 |
| Provider | 通知、支付、OAuth 与 Storage contributor 由 Platform 只读聚合，分别展示配置、连通、回调、凭据轮换、最近失败和 evidence 新鲜度 | 通用面板零外呼；真实平台资格按 Provider owner 和授权目标逐项执行 |
| 文档 | 新 registry、impact map 和治理检查已合入 | 本队列必须登记并通过生成/公开边界检查 |
| 能力账本 | PC10—PC60 的新增稳定切片已在 `pc70q14` 后更新为 verified，并引用各自固定证据与最终资格候选 | 正式 Release 快照仍只在未来明确发布时写入 `releases/*.json` |

## 3. 总体进度

| 指标 | 当前值 | 说明 |
|---|---:|---|
| 队列任务 | 19 | PC00—PC70 |
| 已完成 | 19 | PC00—PC70 全部队列任务 |
| 进行中 | 0 | — |
| 部分完成 | 0 | — |
| 外部阻塞 | 0 | — |
| 未开始 | 0 | — |
| 当前关键路径 | 无 | 产品闭环队列已完成；发布、生产部署或跨实例运营平台均需独立授权和任务 |
| 可并行工作线 | 0 | 本队列没有剩余任务 |

## 4. 阶段观察

| 阶段 | 状态 | 已有输入 | 尚缺验收 | 下一交付物 |
|---|---|---|---|---|
| A 边界与可见性 | 已完成 | PC00/PC01 由 PR #275 合入；PC02 由 PR #276 合入 | 无 | 保持唯一 owner 与锁版本，不为编号整齐盲升依赖 |
| B 可安装、可诊断 | 已完成 | PC10—PC21 已合入；CLI、guided/automatic installer、Web 向导、首次运行清单、只读 Ops Console 和脱敏诊断包存在 | 无 | 保持 schema 与权限边界 |
| C 可备份、可恢复 | 已完成 | PC30 Provider/manifest；PC31 任务、备份中心与 evidence；PC32 真实配对制品隔离恢复与零残留 | 无 | 保持生产覆盖恢复为独立授权动作 |
| D 可升级 | 已完成 | scaffold upgrade、migration、deploy-release；PC40 维护门禁、PC41 只读升级就绪与 PC42 持久化升级执行中心已完成 | 无 | 保持跨实例升级为独立运营平台职责 |
| E 可扩展、可运营 | 已完成 | PC50 locked trust/compatibility matrix、PC51 配置转移、PC52 Module/Tenant 安全模板与 PC60 Provider 资格面板已完成 | 无 | 保持 Marketplace authority 与真实 Provider 资格边界 |
| F 固定资格与发布 | 已完成 | 公开能力目录、v3.0.9 scaffold、固定候选与 P0-E 机制 | `pc70q14` 在候选 `f6378f2…` 上完成七组并零残留 | 后续发布另立任务，不把 dev 资格写成已发布 |

## 5. 任务观察表

| ID | 状态 | 固定候选/PR | 当前结果 | 尚缺 Gate | 下一动作 |
|---|---|---|---|---|---|
| PC00 | 已完成 | `dev@6967f270dadcd1cb69c4606ad42c198c78db5b5b` / PR #275 | 内部文档登记、导航和公开边界已形成 | 无 | 保持能力账本为唯一完成事实源 |
| PC01 | 已完成 | `dev@6967f270dadcd1cb69c4606ad42c198c78db5b5b` / PR #275 | 唯一 owner、Core 采用规则和下游切片已冻结 | 无 | 由 PC02 固定可消费身份 |
| PC02 | 已完成 | `dev@9af96499e22e2080e8e4e3aa7562f9cea3f9b402` / PR #276 | 四端 lock/导出/来源矩阵已固定；历史 Collaboration 例外已登记 | 无 | 按真实公共导出推进 PC10/PC20/PC30 |
| PC10 | 已完成 | `dev@f289c69a620f1eaffb0ba5a8cc39d089759259ab` / PR #277 | 唯一只读 Host、CLI 入口、秘密裁剪、聚焦合同测试和 app-owned scaffold 投影已合入 | `pc70q14` 组合资格已通过；无 | 作为 PC11 唯一预检输入 |
| PC11 | 已完成 | `dev@d80337b6d7b800558131968e65f8039cb8781912` / PR #279；资格源码 `7684a5fcb4bd23cdd966ab760d16a8130ba41ced` | 唯一 Host、guided/automatic transport、安装态门禁和 Web 向导已合入；`pc11e1` 的 Standalone/Multi-tenant 均为 91 表、8 个官方 Module，invalid token、重复执行、零残留和 Web 生产构建通过 | `pc70q14` 组合资格已通过；无 | 作为 PC12 首次运行清单的安装完成输入 |
| PC12 | 已完成 | `dev@c8347692133921114d9ae535f7a893bb8699744c` / PR #281 | Tenant 安全只读 Host 与双语 Admin 页面展示 7 类准备项；本地配置、当前请求观察、未验证与尚未实施严格区分，备份中心、Worker 心跳、邮件 Provider 和全部域名资格保持真实缺口 | `pc70q14` 组合资格已通过；无 | 作为 PC20 健康与 PC60 Provider 资格的现状入口 |
| PC20 | 已完成 | Runtime `50751666b2cb3e41bfd54a7bfed5f99a2176f8ca`；`dev@187bf95f65a98c5d373c96e4e341a96d65c99b33` / PR #283 | Platform-only Host 采用 Core Ops PHP/Web 公共合同，展示数据库、应用迁移、Module、缓存、Runtime 存储、版本身份和维护窗口；关键失败 unhealthy、缓存失败 degraded、异常 fail closed | `pc70q14` 组合资格已通过；无 | PC21 复用固定状态 schema 生成脱敏诊断包；PC30/PC40 分别拥有备份和维护写入 |
| PC21 | 已完成 | Runtime `39815105aba103b16ca1b98243659fd6df8e599d`；`dev@8873a92b1628dc8e12b5f7c2d1e1dae90a2387e9` / PR #285 | 固定 schema JSON 包含状态、非秘密配置、Module、失败任务聚合和 Platform 审计事件聚合；双权限、固定窗口、1 MiB、SHA-256、浏览器复验和审计已形成，未读取原始日志或任意文件 | `pc70q14` 组合资格已通过；无 | PC30 建立受信备份 Provider 与配对制品合同 |
| PC30 | 已完成 | Runtime `2380593b51680de48962eb360514530bdb356fe5`；`dev@dd6a877d1d31a583d0221c0a7b6a3ca325da8e77` / PR #287 | 单一受信 DB/文件 Provider、逻辑新目标、一次尝试和 schema 1 manifest 已形成；合同固定容量、停写窗口、资源/镜像身份、配对 artifact、SHA-256、保留与清理责任，拒绝路径/命令/凭据输入 | `pc70q14` 组合资格已通过；无 | 保持 Provider/manifest 唯一合同 |
| PC31 | 已完成 | Runtime `994f8e7961af9be8834bc9c362f268546eeff6f4`；`dev@9f33eb9fac44858d5d0575e1a760eec0d3af0694` / PR #289 | Core 任务、权限、幂等、并发拒绝和原子审计已由 Application 采用；受信 worker 具 60 秒心跳/revision fencing，Platform 展示最近任务、失败码和最新已验证 evidence，Web 不接收或返回 handler/path/command/credential | `pc70q14` 组合资格已通过；无 | 保持任务与 evidence 合同稳定 |
| PC32 | 已完成 | Runtime/资格 `af7b1c961bca314d9eba5c506aa6eca19fc1cf9b`；PR #291、#292—#302 | `job_e5f0…` 成功恢复 `backup_010108…`：97 表、6 migration、6 关键表、Account/Tenant/TenantMember 各 1、文件卷 0/4096 bytes、零端口、保护身份一致、evidence `61fd2027…`、成功零残留 | `pc70q14` 组合资格已通过；生产覆盖恢复需独立授权 | PC42 可在 PC41 完成后消费固定恢复指针 |
| PC40 | 已完成 | Runtime `675de48cce762bf4b268e4d31e07de9de2520b5d`；`dev@468dc3b0ae09d351deaac264b34e110fe4a893d4` / PR #297 | Platform 可计划/关闭维护窗口；全局写门禁的真实 POST 返回 `50300 / MAINTENANCE_WRITE_BLOCKED`，唯一 denied 审计内容正确，DB fixture、端口和租约零残留 | `pc70q14` 组合资格已通过；无 | PC41 消费维护状态形成升级就绪检查 |
| PC41 | 已完成 | Runtime `f77f48cbc1feb36c35597f263ff21fd17185809e`（tree `716a39d47ac9ead0efa44bf82e94a9024c0abca2`）；`dev@f1e6393a3f1c88fe620cc04e8147fb9c66199a6c` / PR #307 | 固定 target/source Release、migration、目标 Module source/kernel、zero-write scaffold preview、配对 backup/restore evidence、planned-upgrade 维护和 recovery pointer 均形成稳定 ready/blocked 投影；HTTP 无可选路径/URL/命令/Release/镜像/凭据 | PC42 停止点编排和 `pc70q14` 组合资格均已通过；无 | PC42 消费固定 descriptor、checks 和 recovery pointer |
| PC42 | 已完成 | Runtime `991d48712f75435e0016baca85d376effd575a91`（tree `0e1882a142e7a74ec9f32e924a78c1c7f92eb704`）；`dev@4294fca1b1afcc6e8f1f0c0b76e4e628721d4f7b` / PR #310 | 持久化状态机与登记 worker 串联固定 preflight、配对备份、维护、唯一 deploy-release、迁移、smoke 和 recovery pointer；每个停止点、revision fencing、恢复指引和 Platform 观察/操作入口均已形成 | `pc70q14` 组合资格已通过；真实生产升级仍需独立授权 | 队列完成，保持跨实例平台边界 |
| PC50 | 已完成 | Runtime `d46b5d2e2c88d784c2e209f70c7c60015ed8ce48`；`dev@ed5ee1952dd01b8448adf1fb6e7c9ad4bc21be48` / PR #296 | lock/manifest 固定依赖、权限/migration 摘要、来源和未签发 trust 字段；install/reconcile/upgrade 解释同一 blocker，仅 bundled-locked 可执行 | Marketplace 的签名/SBOM/许可证/漏洞响应 authority 未签发，按设计稳定 blocked，不阻塞 PC50 合同完成 | PC51/PC52 消费稳定 manifest 与 lock 身份 |
| PC51 | 已完成 | Runtime `eb6241d002a15f42614086a3d294f29a75e79530`（tree `033e59a0dcf7fcad158b90ed1a7c65ea7c81ee7f`）；`dev@869fea3fc9966d499e7712dd4967cd42e18ef823` / PR #315 | Tenant-only schema 1 包、checksum、dry-run、冲突策略、秘密重绑定、原子应用/审计和 Admin UI 已形成；缺失 Module 提前阻塞，外部绑定并发令牌覆盖完整状态 | `pc70q14` 组合资格已通过；无 | 保持配置转移与配对备份为两类能力 |
| PC52 | 已完成 | Runtime `3b96cc0b1b58c64fd451fb04aca5d41907cb3126`；`dev@eaa1c754b1c214f342a0b4ca94e620a16370d3a3` / PR #306 | 唯一生成器已输出 Commands 合同、append-only migration 指南和 Plugin 制品外 Tenant 安全骨架；A/B Tenant、伪造 payload/resource ID、撤权、停用、migration 失败和禁止无修复重放场景固定，未新增第二 Runtime | `pc70q14` 组合资格已通过；无 | 保持模板、文档与 inventory 同步 |
| PC60 | 已完成 | Runtime `9aa20dcb395e563aa73a1cbfd248880696e55395`（tree `09987594fe7aa44d38b4cb00a660935c9fea489b`）；`dev@4dbda351d590c35f4c2ffe4735057db54c240956` / PR #313 | 通知、支付、OAuth、Storage contributor 和 append-only 安全 evidence 由 Platform 只读聚合，区分 configured、connectivity、callback、credential rotation、recent failure 与 freshness；FreshSchema、fake 合同、OpenAPI、Platform build 等聚焦检查通过 | `pc70q14` 组合资格已通过；真实 Provider probe、消息或资金操作仍需独立授权 | 队列完成，保持 Provider owner 边界 |
| PC70 | 已完成 | `f6378f255241cbde25f374a8a0218fda4616c1ce`（tree `184033c89425a0aa08f5591ce7f6a82735d47ad4`）；PR #321—#333 | `pc70q14` 的 generated-application、双模式 fresh、Plugin lifecycle、production-compose、双模式浏览器 7/7 通过；数据库、Compose、容器、卷、网络、镜像、监听、cache 零残留且 lease released | 无 | 队列结束；发布、生产部署和跨实例平台均需独立任务 |

## 6. 状态更新必填字段

任务状态变化必须同时记录：状态、唯一 owner、精确前置、实际写集、完整候选身份、一次最低
验证、固定证据、剩余 Gate、仍可并行项和安全停止线。不能用聊天、开放 PR、GitHub Actions
颜色或“前一阶段未完成”代替这些字段。

示例格式：

```text
PC20 | 部分完成
candidate: <40-char commit>
result: 只读健康/版本/迁移页面已形成
verification: <一次聚焦检查及结果>
remaining gate: 缺登记 backup provider，备份动作保持不可用
parallel work: PC21 可继续；PC31 被 PC30 的 Provider 合同阻塞
```

## 7. 展示层

| 展示层 | 内容 | 事实来源 |
|---|---|---|
| 公开开发者站 | 稳定能力边界、任务指南和安全公共来源地图 | 已验证 Runtime、公开合同和安全投影 |
| 管理员手册 | 安装、首次运行、健康、备份、恢复、升级和排错 | 已验证应用行为 |
| 内部执行面板 | owner、候选、Gate、阻塞、恢复指针和未发布能力 | 本页与固定证据 |

内部能力账本、资源地址、候选 ID 和原始资格证据不得直接投影到公开文档站。PC00 只建立
可发现性和登记；公开能力目录的内容更新必须由安全投影和实际稳定能力驱动。

## 8. 验证

本页随队列运行 `./scripts/docs-governance generate`、`./scripts/docs-governance check` 和
`git diff --check`。Runtime 状态只在对应任务自己的最低验证完成后更新。
