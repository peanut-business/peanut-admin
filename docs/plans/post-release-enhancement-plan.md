# Peanut Admin 发布后增强任务计划

> 状态：当前计划；尚未开始实现
>
> 基线：`v3.0.12@fe328a320b7c68b3c2f47512f2aa4afcad43c630`
>
> 决策日期：2026-08-28
>
> 完成事实仍以 `docs/product-status/capability-ledger.json` 为准

## 1. 目标与事实边界

本计划承接 consumer-ready 正式源码发布后的体验修正和可选增强。它不重做已经完成的
PC00—PC70、CR01—CR40，也不把计划、浏览器探索或历史材料写成已实现能力。

当前 `v3.0.12` 的正式源码、annotated tag、GitHub Release、scaffold 和 P0-E 8/8 身份保持
不变。登记 Demo 与文档站已经采用该提交；下表新增的是后续候选，不改写不可变 Release。

本轮 Demo 审计使用登记的 `production-candidate` 部署、MySQL 和四域名资源，固定候选为
`fe328a320b7c68b3c2f47512f2aa4afcad43c630`。证据位于
`output/playwright/demo-audit-v3012/`，仅用于问题定位；密码、Cookie、token 和环境内容不进入
计划或证据。

## 2. 当前 Demo 部署与推荐消费方式

线上 Demo 不是在服务器上克隆移动的 `dev` 或 `main` 分支后直接运行。唯一部署 owner
`scripts/deploy-release` 从 annotated Release tag 生成不可变 `git archive`，校验 SHA-256，
传输到登记的 oracle3 目录，并在部署端构建 Docker Compose 镜像。Multi-tenant Demo 再叠加
带 `base_tag`、`base_commit`、`overlay_commit` 和 SHA-256 的受控 Demo overlay；`v3.0.12`
的 base 与 overlay commit 都是同一个正式 Release commit。overlay 只承担合成 Demo 数据、
Demo 写保护和入口投影，不是另一套生产源码。

推荐入口按用途区分：

- 部署 Peanut Admin：使用不可变 annotated Release/tag 和唯一 `scripts/deploy-release`，不从
  `dev` 部署，也不在服务器上维护一份漂移 clone。
- 创建正式派生应用：从已核验的不可变 Release checkout 运行 `scripts/create-app`；生成后由
  派生应用维护自己的仓库、资源登记和 app-owned 源码。
- 直接 clone Peanut Admin：只用于维护参考应用或取得 Release checkout，不是创建正式下游
  应用的最终入口。

## 3. Demo 审计问题登记

下列结论来自一次真实浏览器批量探索。修复 owner 领取任务时先把相邻现象归并为同一权限、
会话或 UI 边界，再做一次聚焦验证；不得为每条 toast 分别创建修复候选。

| ID | 站点/操作面 | 可见现象与证据 | 当前判定 | 影响 |
| --- | --- | --- | --- | --- |
| DA01 | 共享 Admin 的 Tenant A persona、Tenant A、Tenant B 的可见菜单与按钮 | 共享入口的用户设置/操作日志/装修、Tenant A 多个业务入口及 Tenant B 网站设置出现“暂无访问权限”；三站 console 均有对应错误 | 高置信；需要按 persona 对齐菜单、按钮和 API permission key | Demo 看起来可用但主要操作失败，直接影响体验可信度 |
| DA02 | Platform → 存储基础设施/路由 | `/api/platform/infrastructure/storage/route` 两次返回 HTTP 500 | 高置信、稳定复现 | Platform 运维页不能可靠表达未配置或错误状态 |
| DA03 | Admin/Tenant → 生产准备清单 | `readiness.items.undefined.title` 在 zh/en-US/en 均缺失 | 高置信、跨共享/Tenant A/Tenant B | 页面出现不完整文案并污染 console |
| DA04 | Admin/Tenant → 装修管理 | `装修管理`、`移动端装修`、`Tabbar 装修`、`PC 装修` 缺少 locale key | 高置信；在共享 Admin/Tenant A 可见 | 导航国际化回退，英文或严格 locale 下体验不完整 |
| DA05 | Platform → 角色/权限复选框 | Element Plus 持续报告 checkbox `label` 作为 value 即将废弃 | 高置信；同一页面重复出现 | 后续 Element Plus 3 升级风险，当前 console 噪声较大 |
| DA06 | Tenant A 长会话中的装修/业务入口 | 后段出现“租户会话不可用”，与前段“暂无访问权限”并存 | 待区分；可能是审计中的会话切换/退出残留，也可能是 Host 会话恢复缺陷 | 未核实前不得作为 Tenant 隔离缺陷或已知 Runtime 故障发布 |
| DA07 | Tenant 审计中的两个无地址快照 | 页面只显示 `404`，现有快照没有 URL/Host，无法归属到 Tenant A/B 或判断是否由刻意访问未知路由产生 | unknown；不作为已确认产品缺陷 | 后续修复验证应记录 URL 与导航来源，无法复现则关闭而不是猜测 |

本轮未执行 Tenant 暂停/关闭、清空日志、密码修改、真实 Provider、真实资金、删除和其他不可逆
动作。按钮可见性已纳入检查，但这些动作的成功路径必须在专用可丢弃资源和独立授权下验证。
Tenant B 的登录、文章/分类和文件页面已确认基本可读；共享 Admin 的 bootstrap persona 没有形成
可归属证据，不能用 Tenant A persona 的结果代替。

## 4. 第一阶段：Demo 体验修正

| 顺序 | ID | 任务 | 状态 | 主要交付 | 最低验收 |
| ---: | --- | --- | --- | --- | --- |
| 1 | PE01 | Demo persona 权限与 UI 投影对齐 | 未开始 | 固定 Platform、bootstrap Admin、共享 Admin、Tenant A/B persona 的 menu/button/API permission 矩阵；修正 Demo seed、菜单投影或按钮状态 | 预期可用操作不再出现 DA01；无权限能力隐藏或禁用并解释；Demo 写保护继续由 Demo policy 拒绝破坏性操作，不能靠错误 RBAC 代替 |
| 2 | PE02 | Platform 存储路由错误语义 | 未开始 | 让未配置、配置错误和 Provider 不可达返回稳定可观察状态，不以 500 表达正常未配置 | 同一登记 Demo 上打开并刷新存储页一次，无 500，页面状态与后台 readiness 一致 |
| 3 | PE03 | Admin/Platform locale 与组件 API 收敛 | 未开始 | 补齐 readiness/装修导航稳定 key；checkbox 使用 Element Plus 当前 value API | 覆盖本轮对应页面一次，DA03—DA05 不再出现；不顺手升级依赖 |
| 4 | PE04 | Release、Demo 与派生应用入口说明统一 | 未开始 | README、快速开始、部署升级与 Demo handoff 明确 annotated Release、tag archive、Demo overlay、`create-app` 和 clone 的不同用途 | 文档不再把移动分支 clone 写成正式派生应用输入；公开页不泄露密码，交付回复仍提供 owner 授权 Demo 凭据 |
| 5 | PE05 | 修复候选四站点聚焦验收 | 未开始 | 对一个固定修复候选复核 Platform、共享 Admin 两 persona、Tenant A/B 的受影响页面和安全表单 | 受影响路径通过；保留未执行破坏性动作清单。权限/Tenant Runtime 变化按 L2 在正式发布候选只运行一次 P0-E，不在迭代期重复全量 Gate |

PE01 是关键路径。PE02 与 PE03 可在文件 owner 不冲突时并行；PE04 是纯文档独立线。PE05 只在
PE01—PE04 的实际前置满足后执行，不因阶段编号冻结无依赖工作。

## 5. 第二阶段：本仓可继续增强

| 顺序 | ID | 任务 | 状态 | 前置与边界 | 最低结果 |
| ---: | --- | --- | --- | --- | --- |
| 10 | PE10 | 跨 Module 可运行业务示例与新增入口 Guard | 未开始 | CR21 已证明双独立应用签名 Module v1→v2，不重复该资格；本任务只补一个真实跨 Module 业务链，并让后续新增入口消费现有 Module/Tenant/RBAC 合同 | 一个可运行示例证明跨 Module 调用、权限、Tenant 与失败语义；不新建第二套服务层或授权源 |
| 11 | PE11 | 外部回调可信 Tenant 路由 | 未开始 | 先冻结公众号回复等无浏览器 Tenant 会话的可信路由、签名和领域映射；不得从客户端 Host 或未签名字段猜 Tenant | 一个无 Tenant、错 Tenant、重放和合法回调矩阵；合法路径只进入唯一业务 owner |
| 12 | PE12 | T16 部分/多次退款 | 外部阻塞 | 当前 `30+70` 第二笔失败；候选修复是每笔退款独立流水来源号并保留请求级幂等。真实资金不在本任务默认授权内 | 只对登记测试资源重跑失败的 `30+70` 组，两条退款记录和两条余额流水成立；未通过前不宣称支持 |
| 13 | PE13 | 真实 Provider 分项资格 | 外部阻塞 | 邮件、短信、支付、OAuth、微信和 Storage 分别由 Provider owner 提供真实测试资格、凭据引用和副作用授权；PC60 的只读 readiness 不是连通资格 | 每个 Provider 单独记录发送/回调/失败/撤销或清理证据；支付与消息不共用一个笼统“已配置”结论 |
| 14 | PE14 | 第三方业务生产采用 | 外部阻塞 | 需要一个非 Peanut Admin、非合成 Demo 的真实业务 owner、独立资源登记和部署授权 | 从正式 Release 生成的第三方应用完成安装、最小业务、备份/恢复责任和生产 smoke；不把本仓 Demo 冒充该证据 |

## 6. 第三阶段：生态与独立项目

| ID | 事项 | 当前分类 | 恢复条件与归属 |
| --- | --- | --- | --- |
| PE20 | Marketplace | blocked | CR10—CR31 的受控直接分发保持可用；只有 archive SHA-256、受信签名 authority、SBOM、许可证审核、漏洞响应和兼容 authority 完整后另行立项 |
| PE21 | 跨实例运营平台 OP01/OP02 | 本仓范围外；独立项目可立即计划 | 在同级 `peanut-operations-platform` 独立仓、数据库和部署环境推进实例登记、Release、健康、备份证据、签名升级与审计；不得进入 Peanut Admin Runtime/Core |
| PE22 | DCS Product-only 与业务 Module | 本仓范围外；有前置可计划 | 在 DCS 仓先冻结 Tenant 与经营主体映射并取得 D1 业务批准，再实现 Party/Product/Inventory/Procurement 等业务；Peanut Admin 只提供已完成的扩展合同 |
| PE23 | 跨应用身份联邦与通用 Outbox/Event Bus | deferred 设计候选 | 只有两个以上真实消费者提出共同身份或事件需求后冻结协议；当前不为假想消费者扩张 Core |

## 7. 第四阶段：长期 SaaS 与历史暂缓项

| ID | 事项 | 当前分类 | 恢复条件 |
| --- | --- | --- | --- |
| PE30 | 完整 SaaS 商业化：套餐、订阅、试用、续费、配额、计费、支付、发票和收款 | deferred | 至少两个真实应用/实例接入运营平台，完成一次升级、配对备份与恢复/回滚演练，并冻结客户、合同主体、Tenant、套餐和 Entitlement 映射及资金合同 |
| PE31 | 限时 SupportSession/跨租户客服代运营 | deferred | 有真实支持场景和客户授权后，冻结精确能力、到期、撤销和双边审计；PlatformOperator 永远不直接获得租户业务读权限 |
| PE32 | 父子 Tenant、集团权限继承、每 Tenant 独立数据库、客户业务分析 | deferred 设计候选 | 分别出现真实组织、隔离或分析需求后独立立项；不得把它们作为现有多租户 Runtime 的隐式承诺 |
| PE33 | 远程入口/SSH 托管 | out_of_scope | 实例默认主动出站；SSH 不作为公共管理协议。只有独立安全设计和用户授权后才能评估，不并入 OP01/OP02 默认范围 |

## 8. 不恢复的边界与条件性范围外事项

- 自动重构、静默覆盖或双写 app-owned 业务源码永久禁止；确需源码迁移时另建显式、可审阅、
  可恢复的迁移工具。
- 1.x 数据库/scaffold 原地 adopt、长期双 Runtime、长期双字段和兼容镜像不恢复；3.0 保持
  fresh-only。历史 1.x 证据只用于追溯。
- 超级管理员读取全部租户业务、每租户业务表、把 Ops Platform 嵌入 Core/SaaS Host 均不恢复。
- 预构建生产镜像保持范围外；只有另批容器 Registry、签名、SBOM 和供应链发布合同后，才从
  `out_of_scope` 转为 `planned`。当前继续从不可变 tag 在部署端构建。

## 9. 领取、验证与状态同步

1. 每个任务开始前重新核对 `origin/dev`、开放 PR、worktree、租约和文件 owner；不得依赖本计划
   的历史 commit。
2. `PE01—PE05` 可作为一个 Demo 体验批次进入 `feat/* -> dev`；不同安全/Schema/Provider 停止线
   才拆分 PR。后续任务按独立业务结果分批，不以 PR 数量作为目标。
3. 普通任务只运行一次受影响的 lint、静态检查和聚焦验证。Tenant、权限、Schema、支付、部署
   变化增加对应安全 Gate；完整 P0-E 只在一个冻结的 L2 发布候选运行一次。
4. 任务完成时更新本计划；只有稳定能力真实变化时才更新 capability ledger 及生成投影。开放 PR、
   未提交证据或浏览器探索不得提前写成完成。
5. deferred、blocked 或 out-of-scope 项恢复前，必须补齐目标 Release、直接依赖、资源登记、owner、
   副作用授权和最低验收。无法满足时只阻塞该项，不冻结无依赖任务。
