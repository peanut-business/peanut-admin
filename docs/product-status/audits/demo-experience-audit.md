# Demo 体验审计（v3.0.12）

> 审计日期：2026-08-28（Asia/Shanghai）
> 记录状态：v3.0.12 历史观察已归档；候选 `836d8a9…` 的 PE05 聚焦复验已通过，
> 但它不是新的 P0-E 资格或正式 Release 结论。

这份报告把 v3.0.12 production-candidate Demo 的真实浏览器审计收敛为长期可读记录。它保留
用户实际看到的页面、操作、预期和结果，并明确哪些现象已经足够确认、哪些仍需复现。报告不
记录账号、密码、Cookie、token、环境内容或私有凭据引用。

## 审计身份与范围

| 项目 | 值 |
| --- | --- |
| Release | `v3.0.12`，`fe328a320b7c68b3c2f47512f2aa4afcad43c630`，tree `b5be33c5bd180e6b89f00d49002cd4fa96aeb523` |
| 环境 | `production-candidate` |
| 运行资源 | `peanut-admin-production-candidate-deployment`；域名资源 `peanut-admin-production-candidate-domains` |
| 入口 | Platform、共享 Admin、Tenant A、Tenant B 四个已登记 HTTPS Host |
| 采集方式 | 已有真实浏览器页面快照、console 记录和截图；原始采集目录共 202 个文件 |
| 长期证据 | [机器问题清单](demo-experience-audit.json)、[证据与截图索引](demo-experience-evidence.json) |

入口与 Host 对照如下：

| 入口 | Host | URL |
| --- | --- | --- |
| Platform | `pa-platform.007345.xyz` | <https://pa-platform.007345.xyz/platform/> |
| 共享 Admin | `pa-admin.007345.xyz` | <https://pa-admin.007345.xyz/admin/> |
| Tenant A | `pa-tenant-a.007345.xyz` | <https://pa-tenant-a.007345.xyz/admin/> |
| Tenant B | `pa-tenant-b.007345.xyz` | <https://pa-tenant-b.007345.xyz/admin/> |

“共享 Admin persona”“Tenant A admin”“Tenant B admin”和“Platform operator”是本报告的角色
标签，不包含任何登录身份值。审计记录的是当时的 Demo 行为；后续修复、重新部署或新的
浏览器资格必须使用新的候选和新的证据，不能覆盖本报告的历史观察。

## 已确认或待核实的问题

| ID | 站点 / 角色 | 页面与操作 | 预期 | 实际观察 | 判定 | 修复任务 |
| --- | --- | --- | --- | --- | --- | --- |
| DA01 | 共享 Admin persona、Tenant A、Tenant B | 进入用户设置、操作日志、网站设置、字典、装修等菜单并使用可见按钮；共享 Admin 还覆盖移动端/Tabbar 装修，Tenant A 覆盖操作日志查询/重置/导出，Tenant B 覆盖网站设置 | 有权限的操作应完成；无权限能力应隐藏/禁用并给出明确说明 | 菜单或按钮仍可见，点击后多次出现“暂无访问权限”；精确失败 API 未在这轮证据中归属到单一操作 | 高置信 | `PE01` |
| DA02 | Platform operator | 打开并刷新“存储基础设施” | 未配置或不可达应显示稳定的状态、原因和 readiness，不应把可解释状态渲染为 500 | `/api/platform/infrastructure/storage/route` 重复返回 HTTP 500 | 高置信、稳定复现 | `PE02` |
| DA03 | 共享 Admin、Tenant A、Tenant B | 查看生产准备清单 | 每个 readiness 项应有稳定标题 | 页面/console 出现 `readiness.items.undefined.title`；zh、en-US、en 均找不到对应 key | 高置信、三站点一致 | `PE03` |
| DA04 | 共享 Admin、Tenant A | 展开装修管理并查看移动端、Tabbar、PC 入口 | 导航标题应使用稳定 locale key，在严格 locale 或切换语言时保持可翻译 | `装修管理`、`移动端装修`、`Tabbar 装修`、`PC 装修` 的 locale key 缺失并触发回退警告 | 高置信 | `PE03` |
| DA05 | Platform operator | 打开平台角色与权限复选框 | 组件应使用当前 Element Plus value API，不持续输出弃用警告 | console 重复报告 `[el-checkbox] [API] label act as value is about to be deprecated` | 高置信 | `PE03` |
| DA06 | Tenant A admin | 长会话中继续访问装修和业务入口 | 同一可信 Tenant 会话应保持可用；若会话失效，应统一提示并停止后续请求 | 前段出现“暂无访问权限”，后段又出现“租户会话不可用”；页面快照仍能显示装修菜单和空状态 | 待核实；不作为已确认 Runtime 缺陷 | `PE01`（仅用于聚焦复现与归因） |
| DA07 | Tenant A/B 操作面（无法归属） | 三个 404 页面快照 | 404 证据应包含 URL、Host 和导航来源，才能判断是未知路由还是产品入口错误 | 快照只显示 `404 / not found`，无 URL/Host，无法归属到 Tenant A 或 Tenant B | unknown；不能关闭，也不能当作产品缺陷 | `PE05`（复验时记录归属；不预设修复） |
| DA08 | Platform operator | 依次查看 default、Tenant A、Tenant B 的详情 | 详情读取成功时只显示成功结果；失败时不应同时展示成功数据 | 三个详情动作均出现“请求失败，请稍后重试”，同时对话框仍展示对应 Tenant 详情数据 | 高置信、三 Tenant 一致；失败请求的具体来源仍 unknown | `PE02` |

DA01 的“可见但操作失败”说明的是 Demo persona 与 UI/API permission 投影不一致，不等于
Tenant 隔离已经失效。DA06 和 DA07 在历史采集时保持不确定；下面的独立复验保留了新的
Host、URL、请求和干净登录状态，未用旧截图反推结论。

## PE05 修复候选复验（2026-08-30）

复验使用产品 Runtime 候选 `836d8a95ddb097dc6299f33bd43dd636c7edd593`
（tree `1a4e1b08ad088609bb52feeef8ff9387bf6f59df`）和登记的
`local-multi-tenant-demo` 数据库、PHP、Platform、Admin 与四个 Host。复验只覆盖 DA01—DA08
直接相关路径；本机代理最初把 `.test` Host 送到代理端口而产生 503，按登记 Host 加入本次
`NO_PROXY` 后同一 URL 返回 200，未修改产品配置或切换线上环境。

| 问题 | 复验结果 | 可核对结果 |
| --- | --- | --- |
| DA01 | 已通过 | 共享 owner 和第二 persona 的权限、网站、字典、装修与日志可见投影一致；查询/重置返回 200；owner 可打开导出计划，第二 persona 不显示无权导出按钮 |
| DA02 | 已通过 | Platform 存储列表 `GET /api/platform/infrastructure/storage` 返回 200，页面稳定显示本地 account/space/route |
| DA03 | 已通过 | 共享 Admin、Tenant A、Tenant B 和第二 persona readiness 页面无 `undefined.title`，聚焦 console 为 0 error/0 warning |
| DA04 | 已通过 | 共享 Admin、Tenant A/B 和第二 persona 的移动端、Tabbar、PC 装修页面均加载，直接 API 为 200，无 locale warning |
| DA05 | 已通过 | Platform 角色权限页可操作，聚焦 console 为 0 error/0 warning，不再出现 checkbox label-as-value 警告 |
| DA06 | 已关闭为旧会话证据 | seed 前旧 token 会准确 fail-closed；清除旧 session 并用版本化 Demo persona 重新登录后，跨 readiness、装修、日志与 reload 未再出现 Tenant session unavailable |
| DA07 | 已关闭为可归属的预期 404 | `tenant-a.peanut-admin.test/.../pe05-attributed-404` 与 Tenant B 同路径均稳定进入通用 404；Host、URL 和主动导航来源已保留，不存在未知产品入口 |
| DA08 | 已通过 | default、Tenant A、Tenant B 详情请求分别返回 200，只显示对应详情，无失败 toast |

第二 persona 的最后一次干净登录同时证明：三种装修读取和操作日志查询/重置均为 200、
console 0 error/0 warning；“清空日志”未点击，未产生破坏性写入。PE01—PE05 因此可以在本候选
标记完成；正式发布身份仍由后续唯一 L2 P0-E 决定。

## 证据导航

每条问题的原始来源、Host/URL 归属、行号和保留截图均在
[demo-experience-evidence.json](demo-experience-evidence.json) 中。原始 console/YAML 位于
审计运行产生的隐藏 `.playwright-cli` 目录，仅作为过程来源；长期交付不要求用户自行寻找该
目录。可直接查看的历史问题截图与修复候选截图为：

- [DA06：Tenant A 装修页面与会话提示](screenshots/da06-tenant-a-decoration-session.png)
- [DA07：无法归属的 404 页面](screenshots/da07-unattributed-404.png)
- [DA08：Platform Tenant 详情与失败提示并存](screenshots/da08-platform-tenant-detail-error.png)
- [PE05：Platform 角色与权限](screenshots/pe05-platform-roles.png)
- [PE05：Platform 存储基础设施](screenshots/pe05-platform-storage.png)
- [PE05：共享 owner 生产准备清单](screenshots/pe05-shared-owner-readiness.png)
- [PE05：第二 persona 操作日志与权限投影](screenshots/pe05-shared-persona-operation-log.png)

截图只保留 Demo 合成数据和页面状态；没有密码、Cookie、token、环境变量或凭据字段。截图
SHA-256 与源文件相对路径见证据索引。

## 问题与修复任务的双向关系

本报告从问题指向计划任务；计划中的 PE01—PE05 应在同步时反向列出对应 DA ID，并以新的
聚焦验收更新状态。当前对应关系如下：

| 计划任务 | 关联问题 | 目的 |
| --- | --- | --- |
| `PE01` Demo persona 权限与 UI 投影对齐 | `DA01`、`DA06` | 已完成；权限投影与干净会话复验通过 |
| `PE02` Platform 存储路由与 Tenant 详情错误语义 | `DA02`、`DA08` | 已完成；存储和三个详情动作均为 200 且反馈一致 |
| `PE03` Admin/Platform locale 与组件 API 收敛 | `DA03`、`DA04`、`DA05` | 已完成；聚焦页面无原问题或对应 warning |
| `PE04` Release、Demo 与派生应用入口说明统一 | 本审计的长期证据边界 | 已完成；文档固定 Demo overlay 与正式包身份关系 |
| `PE05` 修复候选四站点聚焦验收 | `DA01`—`DA08` | 已完成；同一产品 Runtime 候选保留 Host、URL、请求与脱敏截图 |

## 未执行范围（DL04）

以下动作在本轮明确没有执行，因而没有“通过”或“失败”结论：

- Tenant 暂停、关闭、删除或其他不可逆生命周期操作；
- 清空日志、修改密码以及任何会改变 Demo 数据的破坏性写操作；
- 真实 Storage、支付、消息、OAuth 或其他外部 Provider 的连接、发送或扣款；
- 真实资金、订单、退款和第三方业务数据流程；
- 第二 persona 在本次聚焦路径之外的全产品对照矩阵；
- 公开 PC/H5 页面和 callback 流程的完整端到端链路；
- 模拟长时间自然过期的会话时钟；本轮只验证了 seed 前旧 token fail-closed 与干净重登录。

这些范围需要新的固定候选、已登记资源和专门授权；不能借用本轮浏览器观察或截图替代。
