---
title: 开箱即用能力与后续路线
description: 区分 Peanut Admin 当前能力、核心默认、可选官方模块、后续产品路线、派生应用业务模块和示例模板。
---

# 开箱即用能力与后续路线

“仓库里已经实现”不等于“所有新应用都应该默认启用”。本页同时回答两个问题：当前
Peanut Admin 已经验证了什么，以及长期产品形态应把这项能力放在哪一层。

每一行都要分两次读：先看“当前仓库事实”，再看“推荐默认”。例如文件素材当前已在 Host
中完成 Tenant 适配，不等于它已经是可安装、可停用的官方 Module。只有完成 Plugin 身份、
TenantModule 生命周期和全部入口 Guard 后，产品形态才算兑现。

## 5 分钟结论

| 层级 | 默认原则 | 典型能力 |
| --- | --- | --- |
| 核心默认 | 所有后台应用都需要，或缺失会破坏安全和生命周期 | 身份、Tenant、RBAC、审计、安装迁移、Module 生命周期、Admin Shell |
| 可选官方模块 | 常见但依赖外部服务、行业语义或较高运维成本 | 文件、通知、OAuth、支付、会员 CRM、任务、导入导出、文章 |
| 派生应用业务模块 | 只属于具体产品，不进入通用脚手架 | DCS Party、Store、Product、Inventory、Procurement 等 |
| 示例模板 | 帮助开发和验证，生产默认不启用 | CRUD、跨模块合同、Tenant 隔离测试、演示数据 |

## 后续产品能力路线图

`v2.0.1` 的源码发布已经完成。下面只列发布之后仍需推进的产品能力，不把 DCS 领域功能、
完整 SaaS 或跨应用运营平台混进 Peanut Admin 的当前交付范围。

| 优先级 | 能力 | 当前真实状态 | 下一步产品形态 | 完成条件 |
| --- | --- | --- | --- | --- |
| P0 | 无人值守发布 | **已随 `v2.0.1` 源码 Release 交付**：脚本绑定固定 tag、登记目标、`fresh`/`upgrade`、数据库迁移、备份和非交互远程执行；线上双部署仍需独立运维资格 | 继续用同一脚本完成登记生产目标的独立部署 | 参数和凭据写入可靠；fresh/upgrade 资格通过；失败不会留下半发布状态 |
| P0 | Standalone 与多租户线上交付 | **源码已发布，正式生产证明未完成**：现有多租户地址是隔离体验候选，不是 `v2.0.1` 正式生产发布 | 从同一个不可变 tag 部署一个 Standalone 实例和一个 Multi-tenant 实例 | 固定 tag、资源、数据库和域名；完成备份/迁移、TLS、健康检查、最小浏览器矩阵和账号交付 |
| P0 | 演示站叠加层 | **基础 seeder 已发布；本地多租户候选已执行叠加验证；线上演示目标尚未执行** | 正常安装后显式叠加可丢弃的 Tenant A/B 数据、账号预填、演示账号保护和 Tenant 标题 | 只能用于登记的演示目标；身份/Host/密码一致；可重复执行或安全失败；不会改变普通生产升级配置 |
| P0 | 文档事实自动收敛 | **长期入口已修正，自动防滞后未完成**：发布页、能力账本、首页、开发指南和能力目录已统一到正式 Release | 发布状态只由能力账本和不可变 Release 快照生成，公共入口不再手工复制旧状态 | 文档构建和状态检查能发现过期版本、候选措辞和能力表冲突 |
| P1 | 2.x 派生应用受控升级 | **已验证一条 2.x 路径**：`v2.0.0 -> v2.0.1` 真实派生应用完成 preflight/apply/verify/recover，app-owned 修改保持；后续 Release 仍需重复同一资格 | 从旧 Release、当前应用和目标 Release 生成三方计划，只更新受管文件与 Peanut 依赖，冲突时停止 | 每个目标 Release 都有不可变 manifest；app-owned 字节保持；依赖、数据库 migration 和恢复步骤显式列出 |
| P1 | 官方可选 Module 产品化 | **Article 专项资格已在当前候选通过**：manifest、权限/菜单目录、管理端 contribution、后端 Guard、Plugin/Module 和 Web 类型检查、真实数据库、Tenant A/B 隔离、停用负向及浏览器页面均有证据；通用任务/worker/回调/模块文件入口合同仍未完成。其他候选仍是 Tenant-first Host 能力 | 每项能力拥有稳定 Plugin/Module manifest，可安装、逐 Tenant 开通和停用 | Plugin 安装、TenantModule、成员 RBAC、数据权限和所有入口 Guard 同时通过 |
| P1 | Module 全入口 Guard | **部分具备**：`ModuleExecutionContext/Guard` 已接入 Fixture 和 Article 的管理/公开入口；通用任务、worker、回调和模块文件入口仍没有统一合同 | HTTP、内部命令、入队、worker、外部回调和模块文件入口使用同一授权链 | 每类入口都有启用正向测试和停用负向测试；前端隐藏不能作为安全证据 |
| P1 | 跨 Module 可运行示例 | **未完成**：当前只有单 Module 的 `fixture.delivery-record` | 两个最小 Module 演示公开命令、只读查询 DTO，以及必要时的版本化事件 | 示例可独立运行；调用方不依赖对方私有表或 Repository；失败场景没有部分写入 |
| P1 | Tenant 隔离测试模板 | **已有大量产品测试，尚未整理成模板** | 新 Module 可复制的 Tenant A/B、伪造 ID、停用 Tenant/Module 和权限撤销测试骨架 | create-app 或开发文档提供模板；示例 Module 使用同一模板通过 |
| P1 | Owner 邀请自动投递 | **人工模式可用；自动 Provider 未形成产品能力** | 邮件 Provider 发送一次性邀请，保留重试、送达状态和安全审计 | 真实 Provider 配置与 smoke 通过；不记录明文 Token；失败不伪装为已发送 |
| P1 | 外部渠道生产资格 | **本地 adapter/合同存在，生产未验证**：短信、OAuth、支付、对象存储均需部署方凭据 | 按 Provider 分别形成配置、回调、轮换、监控和故障处理合同 | 每个渠道使用真实平台完成独立 smoke 后，才在目标部署声明可用 |
| P2 | Standalone/多租户模式转换 | **当前没有转换脚本**；无数据时推荐按目标模式重新安装 | 仅为确需保留数据的实例提供受控转换工具 | 检查 Tenant、成员、PlatformOperator、Host 绑定和业务数据；有备份、预检、终态断言和回滚方案 |
| P2 | 通用 Outbox/Event Bus | **暂未形成通用 Runtime**；当前优先使用同步应用服务和公开合同 | 有两个以上真实异步消费者后，再引入事务 Outbox、重试、死信和观测 | 消费者、失败语义和运维 owner 明确；不能只为示例引入基础设施 |

状态标签：

- **当前已支持**：当前代码和能力账本有证据，括号内会限制真实范围；候选实现与正式验收
  会分开标注。
- **推荐新增**：目标产品形态合理，但当前未形成完整能力。
- **仅迁移需要**：只服务旧应用升级，不应成为新业务抽象。
- **暂不建议**：没有真实消费者前不实现或不放入 Peanut。
- **待核验**：当前证据不足，不能宣称支持。

## 核心默认能力

| 能力 | 当前仓库事实 | 推荐默认与理由 | 依赖 | 维护成本 |
| --- | --- | --- | --- | --- |
| 身份与账号 | **v2.0.0 已验证并正式源码发布**：管理端直接使用 Core Account/Credential/TenantMember；业务会员仍使用独立 `pa_member` 登录 | **核心默认，是**。所有应用需要稳定登录身份；业务档案不得塞进 Account | 密码散列、HMAC、会话、凭据恢复、安全审计 | 中高，安全更新和账号恢复长期存在 |
| Tenant 与 TenantMember | **v2.0.0 已验证并正式源码发布**：默认 Tenant、首 owner、生命周期、三 Tenant 选择/切换和隔离通过原生身份测试与真实浏览器验证；没有通用供应商/客户成员产品 | **核心默认，多租户模式必须，Standalone 内部仍保留默认 Tenant** | Account、TenantContext、成员状态、部署模式 | 高，隔离回归和成员生命周期不可停 |
| RBAC 与数据权限 | **v2.0.0 已验证并正式源码发布**：Admin URI/菜单 RBAC、Core Tenant 权限集合和 Module 数据权限原语通过原生资格检查；每个业务域仍需自己的 target resolver/query 约束 | **核心默认，是**。功能权限和数据权限必须分开；业务模块负责声明受保护对象 | TenantMember、Role、Permission、TenantModule、领域 target provider | 高，新增接口和资源类型都要维护 |
| 审计与操作日志 | **v2.0.0 已验证并正式源码发布**：管理写操作、平台操作和 Tenant 归属已有 owner 与聚焦合同 | **核心默认，是**。安全、排错和合规都依赖可追溯记录 | 可信 actor、request ID、TenantContext、保留策略 | 中高，数据量、脱敏和归档需持续治理 |
| 安装与基线后迁移 | **v2.0.0 已验证并正式源码发布**：fresh 安装得到 87 表、197 菜单和 43 配置；后续只接收 canonical baseline 之后的追加 migration，不提供 1.x 数据库或脚手架原地升级 | **核心默认，是**。没有确定性安装和变更账本就不能稳定交付应用 | 版本身份、数据库锁、checksum、空库重建 | 中高；不维护 1.x 兼容矩阵，但每个新变更仍需安装验证 |
| Module/Plugin 生命周期 | **核心生命周期已验证并正式源码发布；官方 Module 产品化未完成**：安装、重复安装、dry-run、失败 migration、治理、菜单/RBAC 和 fixture 同步命令停用 Guard 已有证据；通用任务、回调和模块专属文件入口仍待逐模块采用 | **核心默认，是；生产默认空 lock**。扩展机制应随脚手架存在，但不自动安装业务 Plugin；未通过完整入口 Guard 前不能称为正式官方模块 | 不可变 artifact、lock、Module manifest、migration ledger、统一 ModuleGuard | 高，制品安全、入口装配和兼容矩阵长期维护 |
| Admin Shell | **v2.0.0 已验证并正式源码发布**：Vue 管理端布局、动态菜单、原生权限会话和平台入口已通过 Platform 登录、三 Tenant 选择并进入 Store Demo 的真实浏览器验证 | **核心默认，是**。后台应用需要统一导航、会话和错误处理，但业务页面不进入 Shell | Web 公共包、路由、菜单、权限、品牌入口 | 中，浏览器兼容和前端依赖升级持续发生 |

## 可选官方模块

这里的“官方模块”是推荐产品形态。当前这些能力多数仍由 Peanut Admin 应用目录直接拥有，
尚未全部包装成可独立安装的 Plugin；“v2.0.0 已验证并发布”只说明源码范围和固定资格通过，
不等于外部 Provider 已在生产环境完成验证。

“可选”只表示可安装、可停用，绝不允许单租户实现。进入官方目录前必须同时满足
[官方模块多租户资格](/architecture/official-module-qualification)：可信 TenantContext、
TenantModule 启停、成员权限、SQL/文件/缓存/任务/回调隔离、审计和停用后拒绝新操作。
缺少任何一项就不能标为官方模块。

| 能力 | 当前仓库事实 | 推荐默认与理由 | 依赖 | 维护成本 |
| --- | --- | --- | --- | --- |
| 文件与素材 | **Tenant 适配已验证，尚非可选 Module**：分类、上传、移动、删除和 local/对象存储 adapter 已按 Tenant 隔离 | **可选官方模块，基础后台配置可默认安装**。多数内容型应用需要，但存储、可见性和归档策略不同 | 存储 Provider、URL/删除语义、配额、病毒扫描策略 | 中高，存储费用和生命周期治理持续存在 |
| 通知 | **Tenant 适配已验证，尚非可选 Module；外部渠道待生产验证**：阿里云/腾讯云短信和验证码 scene 存在；没有通用邮件产品消费者 | **可选官方模块，默认不启用渠道**。只有配置真实 Provider 和模板后才可用 | Provider 凭据、模板审核、频控、验证码安全 | 高，外部平台、费用、重试和送达率都需运维 |
| OAuth | **Tenant 适配已验证，尚非可选 Module；外部平台待生产验证**：OAuth 身份、state、回跳和 PC/公众号 bridge 已通过本地资格检查 | **可选官方模块，否**。外部登录不是所有应用需要，错误配置扩大身份攻击面 | 平台应用、域名白名单、密钥、回调 HTTPS | 高，平台政策、密钥轮换和账号合并复杂 |
| 支付 | **Tenant 适配已验证，尚非可选 Module；不代表真实资金可用**：微信/支付宝预支付、回调和退款边界通过本地资格检查 | **可选官方模块，否**。资金、证书、对账和合规不应进入最小脚手架 | 商户资质、证书、签名、幂等、对账和退款 | 很高，需要生产值守和财务流程 |
| 会员 CRM | **Tenant 适配已验证，尚非可选 Module（会员/财务，不是通用 CRM）**：`pa_member`、标签、单一权威余额、充值退款和独立会员登录存在 | **可选官方模块，否**。客户档案应与 Account/TenantMember 分离；行业字段差异大 | 客户身份关联、隐私、标签、等级/积分、数据留存 | 高，个人信息和营销规则变化频繁 |
| 任务与定时任务 | **Tenant 适配已验证，尚非可选 Module**：显式命令、Cron、状态和 Tenant 边界存在；没有通用队列产品或 module-key task envelope | **可选官方模块或基础设施 adapter，按需**。无后台任务的应用不应承担调度器成本 | scheduler、幂等、锁、重试、监控、ModuleGuard | 中高，重复执行和失败恢复必须可观测 |
| 导入导出 | **Tenant 适配已验证，尚非可选 Module**：管理员/岗位等 XLSX、两阶段导出和 Tenant-first 约束通过资格检查 | **可选官方模块，通常建议安装但默认最小权限**。常见且风险高，不应成为任意表导入器 | 文件、异步任务、字段映射、权限、数据校验 | 高，大数据量、隐私和部分失败难处理 |
| 文章内容 | **Article 已作为可选官方模块随 `v2.0.1` 源码 Release 交付基础合同，当前候选专项资格已通过**：既有文章、分类、收藏和多端读取已通过 Tenant-first 资格；manifest、权限/菜单目录、管理端 contribution、部署安装与 TenantModule Guard、PC/UniApp 静态停用合同、真实数据库、Tenant A/B 隔离、停用负向和页面浏览器证据均已完成；通用 Module 全入口合同仍未完成 | **可选官方模块，否**。不是每个业务应用都需要 CMS | 文件素材、富文本安全、发布状态、客户端展示 | 中，内容安全、跨端能力发现和编辑器升级需要维护 |

## DCS 业务模块

以下能力均不属于 Peanut Admin。当前仓库只保存 DCS 采用边界和 Product-only 条件采用
记录，不能据此宣称 DCS Runtime 已完成。

| 能力 | Peanut 当前事实 | DCS 推荐形态 | 主要依赖 | 维护成本 |
| --- | --- | --- | --- | --- |
| Party | **暂不建议进入 Peanut** | DCS 基础业务模块，通常应有 | Tenant/业务主体显式关联、联系人、状态 | 中高，主体合并和法律身份复杂 |
| Store | **暂不建议进入 Peanut** | DCS 业务模块，零售场景建议 | Party、数据权限 target、客户端入口 | 中，门店生命周期和授权范围 |
| Warehouse | **暂不建议进入 Peanut** | 有库存地点时需要 | Party/Store、地址、库存 target | 中，地点状态会影响全部库存命令 |
| Supplier/Relationship | **推荐新增到 DCS，不进入 Peanut** | 有跨组织采购时必须 | Party、Tenant 关联、合同、参与方授权 | 高，越权、合同有效期和主体变更复杂 |
| Product | **推荐新增到 DCS，不进入 Peanut**；仅有 Product-only 条件采用记录 | DCS 商品主数据模块，通常应有 | 分类、属性、SKU、授权范围 | 高，主数据兼容和批量变更频繁 |
| Pricing | **暂不建议进入 Peanut** | 存在报价/客户价时单独启用 | Product、Relationship、币种、有效期 | 高，优先级和历史价格审计复杂 |
| Inventory | **暂不建议进入 Peanut** | 有实物库存时启用 | Product SKU、Warehouse、幂等、库存流水 | 很高，并发、预占和对账要求严格 |
| Procurement | **暂不建议进入 Peanut** | 有采购流程时启用 | Supplier、Product、Pricing、Inventory 收货合同 | 高，状态机和跨 Tenant 参与方授权复杂 |
| Trade | **暂不建议进入 Peanut** | 有销售交易时启用 | Customer/Party、Product、Pricing、Inventory | 很高，订单、履约和售后边界广 |

DCS 的模块表、事件、状态机和页面必须在 DCS 仓记录。Peanut 只说明 Module、Tenant、
RBAC 和 Host 的通用扩展方法。

## 示例与模板

| 示例 | 当前仓库事实 | 推荐默认与理由 | 依赖 | 维护成本 |
| --- | --- | --- | --- | --- |
| CRUD 纵向示例 | **当前已支持（source-only fixture）**：`fixture.delivery-record` 证明表、合同、权限、菜单和页面 contribution | **示例模板，是，但不进入生产 lock**。用于理解完整路径 | Module/Plugin Host、TenantContext | 低，需随公共合同更新 |
| 跨模块命令/查询示例 | **推荐新增**：现有 fixture 只有单 Module 合同 | **示例模板，是**。防止直接访问其他模块私有表 | 两个最小 Module、公开 DTO、合同测试 | 中，需要保持调用边界真实可运行 |
| 跨模块事件/Outbox 示例 | **推荐新增**：当前没有通用已验证事件 Runtime | **示例模板，采用事件基础设施后再提供** | Outbox、重试、幂等、死信、观测 | 中高，示例必须覆盖失败而非只演示发布 |
| Tenant 隔离测试模板 | **当前已支持（大量产品测试），可复用模板待整理** | **示例模板，是**。所有 Tenant-owned Module 都应复制并替换业务断言 | 两 Tenant fixture、伪造 ID、停用测试 | 低，随测试 API 演进 |
| 演示数据 | **v2.0.x 已发布基础 seeder；本地多租户候选已执行 Tenant A/B 叠加验证**；线上演示目标仍未执行 | **示例模板，开发/演示环境可选，生产默认关闭** | 环境门禁、幂等、清理责任 | 中，必须避免污染真实数据 |

## 如何选择

1. 缺失会破坏身份、安全、隔离或应用生命周期的能力，放入核心默认。
2. 依赖外部 Provider、行业字段或较高运维成本的能力，做可选官方模块。
3. 只服务一个具体产品的领域能力，留在派生应用。
4. 只为教学或 Gate 存在的能力，保持示例身份，不进入正式 Plugin lock。

## 当前产品边界

| 不进入当前 Peanut Admin 的能力 | 原因 | 应放在哪里 |
| --- | --- | --- |
| DCS Party、Store、Warehouse、Supplier、Product、Pricing、Inventory、Procurement、Trade | 它们是具体业务领域，不是通用后台基础设施 | DCS 仓库及其独立 Module |
| legacy Admin/Role/Dept 映射、双写、余额镜像和旧 bootstrap | 只服务旧版本升级，会污染 fresh-only Runtime | 旧 tag/Release 历史证据或独立迁移项目 |
| 跨应用身份联邦、全局组织目录和跨实例 Tenant 映射 | 需要多个真实应用、独立安全边界和稳定协议，复杂度远高于实例内多租户 | 独立联邦/运营平台项目，需求成立后立项 |
| 套餐、订阅、计费、试用、发票和应用市场 | 属于完整 SaaS 商业控制面，当前明确暂缓 | 后续 SaaS 产品，不进入核心脚手架默认能力 |
| 自动改写派生应用业务源码 | 会破坏 app-owned 所有权和升级可控性 | 只升级框架受管文件；业务迁移由应用自己实现 |

继续阅读：[开发与目录](/guide/development)、[Module 开发教程](/guide/module-development)、
[API 与扩展](/api)、[fresh 部署与安装](/deployment)。
