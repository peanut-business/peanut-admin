# 产品闭环所有权与 Core 采用决定

Document ID: `pa-docs-architecture-product-closure-ownership-adoption`

Status: `current`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Upstream: `server/composer.lock`、`web/pnpm-lock.yaml`、`pc/package.json`、
`uniapp/package.json`、`resources/service-registry.json`、官方 Module manifest，以及固定 Core
包合同。

## 1. 范围

本文冻结 Peanut Admin 单实例产品闭环中安装、诊断、备份、恢复、维护、升级、配置转移和
扩展治理的唯一 owner，并确定哪些现有 Core 合同可以被应用采用。

本文不证明这些产品页面或 Adapter 已实现，不改变产品能力账本，不授权生产恢复、破坏性
fresh、真实资金或外部消息，也不把跨实例运营平台放回本仓。

## 2. 决定

产品闭环采用“先冻结 owner 和可用合同，再实现最小纵向切片”的顺序：

1. Core 拥有跨应用稳定的状态、权限、审计、任务和安全约束。
2. Application Host 拥有产品流程、ThinkPHP/前端装配、资源登记和具体部署编排。
3. Official Module 拥有领域配置、业务状态和外部 Provider 资格。
4. Deployment Adapter 拥有 MySQL、文件卷、对象存储、Compose 或云平台的实际动作。
5. 独立运营平台只在多个已部署实例之间编排，不成为本应用的第二 Runtime。

闭环实现中若发现公共包缺少真实必需合同，只允许增加能被当前纵向切片立即消费的最小
Core 变更；不得先复制参考 Host，再事后收敛，也不得建立双写、deep import、镜像或兼容桥。

## 3. 当前事实

| 领域 | 应用当前事实 | Core 当前事实 | 当前缺口 |
|---|---|---|---|
| 安装 | `server/database/install.php`、环境门禁、部署脚本和 CLI 文档已形成 | Core 参考 Host 有安装检查/流程，但公共包没有安装向导合同 | 没有一次性安装向导和首次运行清单 |
| 诊断 | 系统维护页只展示环境/目录并清缓存；应用已复用 Core Tenant 诊断属性 | 公共 `ops-console` 有健康、版本、迁移和日志合同 | Ops Console 主链尚未采用 |
| 备份 | 生产资源登记和 `deploy-release` 有 DB + 文件配对备份门禁 | 公共 `BackupRestoreProvider`、Registry、任务和权限合同已存在 | 没有应用内 Provider Adapter、任务视图和备份制品账本 |
| 恢复 | 发布/资源登记定义恢复责任，常规应用无恢复入口 | 公共合同只允许受信 Provider 和登记目标；参考 Host 证明恢复到新目标 | 应用尚未采用和验证隔离恢复 |
| 维护 | 没有统一维护窗口或全写入口门禁 | 公共 `MaintenanceService` 有 reason、revision、幂等和审计 | 缺应用 Store、HTTP Host 和写请求执行门禁 |
| 升级 | scaffold 和数据库迁移、`deploy-release`、恢复指针独立存在 | 公共状态 DTO 可表达 upgrade ready/blocked；参考 Host 的升级执行不属于公共包 | 缺统一就绪投影和应用升级编排 |
| 扩展 | Module/Bundle manifest、包 SHA-256、依赖、安装/退役/Purge 已完成 | Kernel 有 manifest compiler、版本约束和 TenantModule 合同 | 缺签名/SBOM/许可证/兼容证据的产品视图 |
| 配置转移 | 导入导出 Module 面向业务数据；没有应用配置包 | Core Settings/ImportExport 是通用原语，不拥有产品配置集合 | 缺独立于数据备份的版本化配置转移 |
| Provider 资格 | 短信、支付、OAuth、存储有产品 Runtime，真实平台验收按环境后置 | Core 只提供安全/任务/通知等通用合同 | 缺统一的“已配置/已连通/已获生产资格”状态 |

应用当前锁定 `peanut-admin/core@0.1.0-alpha.9`；Admin Web、PC、UniApp 分别锁定
`@peanut-admin/admin@0.1.0-alpha.7`、`alpha.5`、`alpha.5`。版本不同本身不构成失败，
但任何新采用必须先由 `PC02` 固定对应导出、不可变来源和兼容证据。

## 4. 唯一所有权矩阵

| 能力 | Core owner | Application Host owner | Module / Deployment owner | 首次实施决定 |
|---|---|---|---|---|
| 安装预检 | 暂不新增公共 Runtime；只复用既有身份、密码和 Module 原语 | 结构化检查、CLI/Web 复用、安装锁 | Deployment 提供环境/资源探针 | `PC10` 先在应用形成唯一 Host；出现第二个真实消费者再评估抽 Core |
| 安装向导 | 无 | 一次性 token、步骤、品牌、部署模式、管理员、Module 组合 | Deployment 执行空库安装 | `PC11` 只实现 Application UI/API，不开放任意命令或路径 |
| 运行状态 | `OpsStatusService`、`OpsStatusSnapshot`、权限和错误合同 | ThinkPHP Provider、HTTP 路由、Platform 装配和页面入口 | Deployment 提供版本/迁移/健康数据 | `PC20` 直接采用公共合同，不重建第二套状态 DTO |
| 结构化日志 | 日志 DTO、Registry、脱敏消息目录和查询合同 | 日志来源 Adapter、权限、分页和诊断包 | Module 只提供安全 source/attribute | `PC20/PC21` 采用；不公开原始秘密或任意文件日志 |
| 备份任务 | Provider descriptor、任务提交、权限、幂等和审计 | Provider 注册、任务投影、备份制品账本和 UI | Deployment 执行 DB/文件/对象存储动作 | `PC30/PC31` 采用公共任务合同；Web 不接收 shell/path |
| 恢复验证 | 受信 Provider、opaque backup key、登记 target 和任务合同 | 恢复申请、结果投影和代表验证编排 | Deployment 只恢复到登记新目标 | `PC32` 禁止覆盖活动库；生产恢复另需明确授权 |
| 维护窗口 | `MaintenanceService`、reason、revision、幂等和审计 | Store、HTTP Host、状态投影和全写入口门禁 | Module 写入口消费统一门禁 | `PC40` 采用公共合同；隐藏前端不算门禁 |
| 升级就绪 | `OpsStatusSnapshot` 的版本/迁移/备份/证据状态 | 读取当前应用、目标 Release、Module 和 scaffold 计划 | Deployment 验证实际目标 | `PC41` 先形成只读投影；Core 参考 Host 不被复制或 deep import |
| 升级执行 | 只保留通用任务/状态合同 | 串联 preflight、备份、维护、部署、迁移、smoke 和恢复指针 | Deployment 执行登记动作 | `PC42` 由 Application 拥有；跨实例执行仍归独立运营平台 |
| Module 信任 | manifest、版本约束、TenantModule 和通用安全字段 | 包来源、lock、签名/SBOM/许可证/资格视图 | Module 发布者提供制品和声明 | `PC50` 先扩应用治理；只有公共 manifest 必需字段才进入 Core |
| 配置转移 | 可复用设置、文件和任务原语 | 定义配置集合、schema、dry-run、冲突和审计 | Module 导出自己的可公开配置，秘密只留引用 | `PC51` 保持应用 Module；不把它伪装成数据库备份 |
| Provider 资格 | 通用安全、任务、通知和审计合同 | 聚合状态和 Platform 可见性 | 各 Official Module 拥有真实 Provider probe 与证据 | `PC60` 不在通用 Gate 中发送外部消息或发生真实资金动作 |

## 5. 允许采用的 Core 合同

`PC02` 固定版本和导出后，以下公共边界可进入应用：

- `PeanutAdmin\OpsConsole\Status\OpsStatusService`
- `PeanutAdmin\OpsConsole\Status\OpsStatusSnapshot`
- `PeanutAdmin\OpsConsole\Status\RuntimeStatusProvider`
- `PeanutAdmin\OpsConsole\Task\BackupRestoreProvider`
- `PeanutAdmin\OpsConsole\Task\BackupRestoreProviderRegistry`
- `PeanutAdmin\OpsConsole\Task\OpsTaskService`
- `PeanutAdmin\OpsConsole\Task\OpsTaskDispatcher`
- `PeanutAdmin\OpsConsole\Maintenance\MaintenanceService`
- `PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindowStore`
- `PeanutAdmin\OpsConsole\Logs\RuntimeLogProviderRegistry`
- `PeanutAdmin\OpsConsole\Logs\RuntimeLogService`

应用已采用的 `PeanutAdmin\OpsConsole\Logs\TenantDiagnosticAttributes` 保持原 owner，不借本轮
改名或重构。Core `backend/app/command/*`、`backend/app/upgrade/*` 和 `backend/app/ops/*` 是
参考 Host，不是应用可依赖的公共 namespace。

Web `OpsConsolePage` 和 transport 只有在 `PC02` 证明应用当前或目标 npm 版本真实导出、类型和
宿主约束兼容后才可采用；未证明前不 deep import 包内部目录，也不复制页面作为临时实现。

## 6. 明确不进入本轮 Core 的内容

- 产品品牌、部署模式选择、官方 Module 默认组合和首次运行清单；
- `.env` 写入、资源登记路径、Compose/SSH/云 Provider 命令；
- 备份保留策略的产品默认、对象存储复制策略和生产恢复授权；
- scaffold 的 app-owned 合并策略和 Peanut Admin Release 编排；
- 文章、会员、余额、支付、OAuth、通知场景和外部渠道业务模型；
- 应用市场、套餐、订阅、计费和跨实例 Fleet 管理；
- 无真实消费者的通用工作流、事件总线或第二套插件 Runtime。

## 7. 采用停止线

1. 公共包采用必须固定完整版本、dist/reference 或 Registry integrity，并记录测试 owner。
2. Application Adapter 不得引用 Core `backend/`、测试 fixture、未导出类或源码工作树路径。
3. Core 合同不直接执行任意 shell、任意文件路径或任意数据库目标。
4. 备份成功不等于可恢复；只有恢复到新目标并完成验证才形成恢复证据。
5. 维护模式必须命中后端实际写入口；页面隐藏、菜单权限和代理静态页不能替代门禁。
6. 升级不自动改写 app-owned 业务源码；未知部分状态停止并保留恢复指针。
7. Module trust 没有签名、审核、漏洞响应和下架机制前，不开放公共 Marketplace。
8. 真实资金、生产恢复、破坏性 fresh 和未经批准的外部消息保持独立人工授权。

## 8. 直接下游

| 下游任务 | 唯一 owner | 第一可合入写集 | 不依赖项 |
|---|---|---|---|
| `PC02` 版本兼容基线 | Application integration owner | 兼容矩阵、锁文件只读证据、Core 导出核验；默认不改 lock | 不依赖数据库或服务 |
| `PC10` 安装预检 Host | Application installer owner | 安装预检合同、Host 和聚焦静态/单元验证 | 不依赖 Ops Console UI |
| `PC20` Ops 最小采用 | Application ops owner | PHP Provider/Host、只读路由和最小 Web 入口 | 不依赖备份 Provider |
| `PC30` 备份 Provider | Deployment/recovery owner | Provider/manifest 合同和登记适配；不执行生产备份 | 不依赖升级 UI |

`PC02` 是 `PC20` 采用 Core 的直接前置；`PC10` 可在 owner 不重叠时准备，但最终组合声明仍需
兼容基线。后续任务的状态、候选和剩余 Gate 只写入产品闭环可观测面板，稳定能力完成后再
更新产品能力账本。

## 9. 验证

- 运行 `./scripts/docs-governance impact` 确认架构决定的最小文档闭包；
- 运行 `./scripts/docs-governance generate` 更新文档目录；
- 运行 `./scripts/docs-governance check` 验证登记、链接、投影、生成内容和公开边界；
- 本文不运行数据库、服务、浏览器、Recovery 或 P0-E。
