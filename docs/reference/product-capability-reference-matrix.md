# Peanut Admin 产品能力与同类产品参考矩阵

Document ID: `pa-docs-reference-product-capability-reference-matrix`

Status: `current`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Upstream: 官方产品文档、官方仓库、Peanut Admin 产品能力账本、产品闭环执行队列，以及
Core/Application 所有权决定。

> 调研日期：2026-08-27。外部事实只采用官方文档或官方仓库；产品宣传中没有公开合同支撑的
> 安装原子性、回滚、签名、沙箱和兼容承诺均保持“未知”，不按记忆补全。

## 1. 这份矩阵解决什么问题

这份文档把三个问题放在同一张事实图里：市场上哪些产品已经证明某类能力有用户价值，
Peanut Admin 当前由哪一层拥有相邻能力，以及下一步应该补产品闭环还是抽取 Core 合同。
它不是第二份完成状态账本；Peanut Admin 的完成度仍以
[`capability-ledger.json`](../product-status/capability-ledger.json) 为唯一机器事实源。

## 2. 同类产品已核实参考点

| 产品 | 已核实能力 | 作用 | 官方来源 | Peanut Admin 可参考点 |
|---|---|---|---|---|
| LikeAdmin SaaS | 可视化安装检查、数据库与管理员配置；平台/租户后台；RBAC、素材、对象存储和代码生成 | 降低首次部署与常规 CRUD 开发成本 | [安装](https://doc.likeadmin.cn/php-saas/deployment/bt.html)、[官方仓库](https://github.com/likeadmin-likeshop/likeadmin_php_saas) | 安装向导必须与唯一安装 Host 共用检查；生成器输出权限与 Tenant 安全骨架 |
| MineAdmin | Casbin 权限、登录/操作/异常日志、监控、CRUD 生成；插件包包含 migration 与安装/卸载入口 | 让后台可审计，并形成可安装扩展单元 | [能力介绍](https://en.doc.mineadmin.com/guide/introduce/mineadmin.html)、[插件结构](https://en.doc.mineadmin.com/plugin/develop.html) | Module manifest 应显式记录版本、依赖、权限、迁移、启停和数据处置 |
| RuoYi-Vue-Plus | SQL/服务监控、在线日志、操作/异常日志、定时任务、动态数据源代码生成 | 统一日常诊断、任务观察和开发提效 | [官方文档](https://plus-doc.dromara.org/)、[官方仓库](https://github.com/dromara/RuoYi-Vue-Plus) | 健康、日志、任务和修复建议分开呈现；生成器绑定实际数据源和授权边界 |
| Directus | 数据库驱动 API、认证/权限、活动与修订、Flows、UI/API/hook/operation 扩展 | 把数据后台、自动化和多类扩展点组合为产品 | [官方仓库](https://github.com/directus/directus)、[扩展](https://docs.directus.io/extensions/layouts.html)、[Flows](https://docs.directus.io/guides/headless-cms/trigger-static-builds/index.html) | “业务 Module”与 UI、API、事件、任务等扩展类型分开登记；审计事件与自动化解耦 |
| Strapi | 管理员 RBAC、npm/Marketplace 插件、扩展加载顺序、CLI import/export/transfer 与 checksum | 支持受控环境转移和插件扩展 | [插件扩展](https://docs.strapi.io/cms/plugins-development/plugins-extension)、[RBAC](https://docs.strapi.io/cms/features/rbac)、[数据传输](https://docs.strapi.io/cms/data-management/transfer) | 配置包应有 schema、checksum、dry-run、冲突策略和秘密重绑定；不能假设插件升级无破坏 |
| Payload | 文档级访问控制、配置型插件、具事务和 up/down 的 migration、持久化 Task/Workflow/Job/Queue | 将权限、扩展、迁移和后台任务纳入代码合同 | [插件](https://payloadcms.com/docs/plugins/overview)、[访问控制](https://payloadcms.com/docs/access-control/overview)、[迁移](https://payloadcms.com/docs/database/migrations)、[任务队列](https://payloadcms.com/docs/jobs-queue/overview) | 任务需可查询、幂等、可重试且有失败点；migration 可逆性必须逐项声明 |
| Budibase | 工作区/应用角色、自动化、应用导入导出、备份、发布备份、恢复前保护点和审计下载 | 支持低代码应用交接和可恢复运营 | [角色](https://docs.budibase.com/docs/user-roles)、[导入导出](https://docs.budibase.com/docs/export-and-import-apps)、[备份](https://docs.budibase.com/docs/backups-1)、[审计](https://docs.budibase.com/docs/audit-logs) | 配置迁移与数据备份必须分开；恢复前产生保护点；导出不能携带可复用凭据 |
| WordPress | Site Health 检查更新、PHP、配置、数据库、目录和插件/主题问题，并给出可复制信息 | 让非开发者理解故障和下一动作 | [Site Health](https://wordpress.org/documentation/site-health/)、[健康页面](https://wordpress.org/documentation/article/site-health-screen/) | 健康项同时显示状态、影响、原因、修复入口和安全诊断信息，不只显示在线/离线 |
| Nextcloud | 安装向导、依赖检查、维护模式、配置/数据/应用/数据库备份恢复、内建升级器、日志与指标 | 形成安装、升级、保护和恢复的完整运维闭环 | [安装](https://docs.nextcloud.com/server/stable/admin_manual/installation/source_installation.html)、[备份](https://docs.nextcloud.com/server/stable/admin_manual/maintenance/backup.html)、[升级](https://docs.nextcloud.com/server/stable/admin_manual/maintenance/upgrade.html)、[日志](https://docs.nextcloud.com/server/stable/admin_manual/configuration_server/logging_configuration.html) | 固定“兼容检查 → 新鲜备份 → 维护 → 升级 → smoke → 恢复指针”；回退采用重建与恢复，不做危险降级 |

## 3. 可参考能力及其作用

| 参考能力 | 用户价值 | Peanut Admin 当前事实来源 | 推荐所有者 | 当前采用决定 |
|---|---|---|---|---|
| 安装预检与一次性向导 | 从环境问题直接到可执行修复，避免手工拼接安装步骤 | PC10/PC11、唯一安装 Host | Application；Core 只保留通用检查 DTO | 已采用；页面不接收数据库地址、密码或命令 |
| 首次运行准备清单 | 告诉管理员哪些能力只是配置、哪些已真实验证 | PC12 readiness Host | Application 聚合，Module 提供 contributor | 已采用；“configured”不能显示为“production ready” |
| 健康与脱敏诊断 | 降低远程排障成本而不泄露秘密和业务数据 | PC20/PC21 Ops Console | Core 稳定状态/任务合同；Application 安全投影 | 已采用；原始日志和任意文件不进入诊断包 |
| 配对备份与隔离恢复 | 证明数据库和持久文件属于同一恢复点，避免覆盖活动目标 | PC30—PC32 | Core 任务合同 + Application 账本 + Deployment worker | 已采用；生产覆盖恢复保持独立授权 |
| 维护和升级闭环 | 在真实停写边界中串联备份、迁移、smoke 与恢复指针 | PC40—PC42 | Core 维护/任务合同；Application 编排；Deployment 执行动作 | 采用单一任务状态机；HTTP 不接受路径、命令或目标地址 |
| Module 信任与兼容 | 在安装前解释版本、依赖、权限、migration 和来源风险 | PC50、`plugins.lock`、Module manifest | Core 稳定 manifest 必需字段；Application 治理视图 | locked/bundled 已采用；Marketplace 等签名、SBOM、许可证和漏洞响应 authority |
| 配置包与环境转移 | 搬迁可公开设置而不把整库备份或秘密当作配置 | PC51 ImportExport Module | Application Module | schema、checksum、dry-run、冲突和秘密重绑定；不导出密码/token/Cookie/密钥 |
| Tenant 安全开发模板 | 降低二次开发中的串租户、撤权失效和 migration 误用 | PC52 生成器 | Scaffold/Application | 生成 A/B Tenant、伪造 ID、停用、撤权和 migration 失败骨架 |
| Provider 生产资格 | 区分已配置、已连通、回调已验证和证据已过期 | PC60 与各 Official Module | Application 安全聚合；Module/Deployment 写证据 | 通用面板只读且零外呼；支付、短信、OAuth、Storage 分别资格 |
| 审计与任务执行记录 | 让失败点、重试、幂等和责任人可追溯 | Core Audit/Ops Task 与应用审计 Host | 可复用稳定合同适合 Core | 已采用；业务 payload 和个人数据保持在领域 owner 内 |

## 4. 哪些能力适合 Core，是否现在迁入

| 候选 | 适合 Core 的原因 | 现在是否迁入 | 决定 |
|---|---|---:|---|
| Tenant/Platform Context、RBAC、Module Guard | 所有业务入口都必须使用的安全上下文 | 已有公共合同则直接采用 | 保持 Core 稳定合同，不复制 Runtime |
| Audit、Ops Task、Maintenance、状态 DTO | 多个运维切片已经复用，语义与业务模型无关 | 是，仅补真实缺口 | PC20/31/40 已证明的最小缺口可进入 Core |
| 通用设置 revision/secret 引用接口 | 多 Module 需要并发控制和秘密不出库 | 需要两个以上真实消费者再抽 | 当前配置转移仍由 Application 适配现有 owner |
| Provider 资格 evidence/安全 DTO | 支付、通知、OAuth、Storage 都需要相同新鲜度语义 | 先留 Application | PC60 先验证字段稳定性；出现第二应用消费者后再提 Core |
| 安装向导页面、备份中心、升级中心 | 强依赖 Peanut 的路由、资源登记、部署脚本和产品 UX | 否 | 保持 Application；Core 只提供检查、任务和维护原语 |
| 配置集合、官方 Module contributor、Provider probe | 与具体表、Provider 和产品策略绑定 | 否 | 保持对应 Module/Application owner |
| `deployment-control worker` | 绑定登记目标和唯一 `deploy-release` 入口 | 否 | 属 Deployment，不进入 Core 或 HTTP Runtime |

现在不进行“大搬家”。完成产品闭环并不要求等所有 Core 抽取结束；相反，闭环实现会提供
真实调用路径，只有被多个切片反复证明稳定的最小合同才进入 Core。这样不会让当前开发长期
依赖两套实现，也不会在产品尚未验证前把应用策略固化成公共包 API。

## 5. 仍保持未知或暂缓的能力

| 能力 | 当前结论 | 恢复条件 |
|---|---|---|
| 第三方 Marketplace 自动下载/安装 | 暂不开放 | 受信签名、archive digest、SBOM、许可证审核、漏洞响应和兼容 authority 完整 |
| 任意插件卸载自动删数据 | 不提供默认行为 | 每个 Module 明确 preserve/retire/purge 与可恢复证据 |
| 自动降级或覆盖 app-owned 业务源码 | 范围外 | 不恢复；使用固定版本重建、受控 scaffold upgrade 和配对恢复 |
| 跨实例 Release/授权/升级/备份控制面 | 独立产品范围 | 在独立运营平台登记资源、身份、权限和审计后实施 |
| 邮件 Provider 生产资格 | 尚未实现 | 先形成真实配置 owner、transport、回调/失败证据和凭据轮换合同 |
| Provider 安全 probe 的统一实现 | 不假设所有平台支持无副作用 probe | 各 Provider owner 明确官方安全探测方法、TTL 和授权目标后逐项接入 |

## 6. 证据边界

- 市场产品的公开功能只证明“这种能力存在并有产品价值”，不证明其安装、回滚、隔离或安全
  语义适合 Peanut Admin。
- 外部文档可能随版本和商业计划变化；采用前必须锁定目标版本和许可证。
- Peanut Admin 的开放 PR、未提交 worktree 和运行中 Gate 不算完成；只有能力账本与正式
  Release 快照能提供完成/发布身份。
