# Peanut Admin 过期事实收敛登记

> 审计日期：2026-08-14
>
> 最终基线：`origin/dev@c82d42468d858db7c00f95f637d6eb015618725b`（含前置 PR #107）
>
> 用途：记录本次处理决定和历史豁免边界；当前操作事实仍以根 `AGENTS.md`、权威计划、
> Release metadata 与 Peanut Admin 项目资源登记为准。

## 事实矩阵

| 范围 | 原值或失真表述 | 当前事实与证据 | 处理 |
| --- | --- | --- | --- |
| 开发数据库 | 旧 3306 端口与 `peanut_admin` 库被称为权威数据库 | 项目 `resources/project-resources.json` 登记 `peanut-admin-mysql84-development`：development、MySQL 8.4.10、`192.168.192.2:20183/peanut_admin_development` | 更新 parity 报告和根事实源；旧值只允许出现在本登记及 `output/` 历史证据 |
| 迁移数量 | 根目录结构仍写 24 个 migration | `RELEASE_METADATA.json` 和 `v1.1.0` Release 固定 50 个 migration；发布后 `dev` 增加 `20260814_legacy_decoration_entry_convergence.sql`，当前仓库为 51 个 | 根事实源改为当前 51，并把 parity 24 条、`v1.1.0` 50 条分别标为历史验收与不可变发布时点 |
| 多租户完成度 | 当前事实仍说 MT02–MT04 未整体完成 | 权威计划、`RELEASE_METADATA.json` 和 `v1.1.0` Release 证明 MT02–MT06 已完成 | 更新根事实源和权威计划摘要 |
| 恢复指针 | 权威计划仍把 MT06 发布写成当前关键路径 | annotated tag object `0f4fffd731cbcb632f9fb6b293e31671857410a5` 指向 release commit `c6a165fbc223bcca1332235d3a31c9d2ede55a06`，GitHub Release `v1.1.0` 已发布 | 改为已完成并按当前授权停止 |
| 暂缓 SaaS 计划 | “PRE-S01 仍是首个可领取项”，且 S01–S07 全部标成未开始 | 当前权威计划已完成对应 MT00–MT06；完整 SaaS 商业化仍未获授权 | 将旧编号标为历史映射，删除当前领取语义，保留未来设计输入 |
| 开发登录 | 根事实源仍列出共享 `admin123456` | `server/database/install.php` 要求空库安装显式提供合格的 `ADMIN_INITIAL_PASSWORD` | 只保留初始用户名 `admin` 和安装期密码规则；旧密码仅存在于历史证据/种子兼容实现 |
| 下一阶段 | 产品化段落仍说下一阶段推进多租户 | MT00–MT06 和 `v1.1.0` 已完成；完整 SaaS 暂缓，运营平台须独立立项 | 删除旧下一阶段指针 |
| 生产入口 | 旧 PB09 文档记录 `v1.0.0` 上线现场 | `docs-site/releases.md` 与部署说明记录官方环境已运行 `v1.1.0`、50 条账本 | 保留 PB09 为明确日期和版本的发布历史，不改写旧验收结果 |
| 本地安装示例 | 公共指南使用 `127.0.0.1`、`localhost` 和示例库 `peanut_admin` | 这些是外部克隆的中性自有环境示例；项目维护环境只使用根 `AGENTS.md` 和项目资源登记 | 保留中性模板；禁止把退出的旧数据库地址写回当前指引 |

## 历史档案边界

- `output/` 保存固定日期、固定候选的原始验收脚本、JSON、TSV 和截图索引。改写其中
  的地址会破坏证据可追溯性，因此保留原值；这些文件不得作为可执行的当前环境说明。
- `docs/architecture/pb*.md` 与 `docs/productization-baseline-plan.md` 保存 PB 阶段的合同、
  失败记录和 `v1.0.0` 发布事实。明确绑定候选、日期或历史阶段的数值不改写。
- `docs/likeadmin-parity-report.md` 仍保留 parity 时点的表数、菜单数、配置数和迁移结果，
  但入口已增加历史声明及当前资源指针。
- 发布身份以 annotated tag、GitHub Release、`RELEASE_METADATA.json` 和
  `CHANGELOG.md` 交叉核对；移动分支或过程计划不能覆盖不可变发布事实。

## 防回归

`scripts/check-stale-facts.sh` 扫描 Git 跟踪文件：退出数据库的完整连接串只能留在
`output/`、本审计登记和明确写有“禁止连接”的当前资源合同；根事实源的当前迁移数量
必须等于 Git 跟踪的 migration 文件数，且不得恢复“MT06 是当前关键路径”；暂缓计划
不得再次把 PRE-S01 写成当前首个可领取项。CI 对每个 PR 运行该检查。

## 模块、身份与租户文档审计（2026-08-16）

本节以 2.0.0 fresh-only 候选代码、canonical Schema、当前 inventory 和 1.x 不可变发布
快照交叉核验。第 1 至 40 行是 2026-08-14 的 1.x 历史审计，不是 2.0 当前操作入口。

### 原始问题覆盖矩阵

“文档结构已覆盖”只说明正式入口已经明确回答问题；“开发候选已验证”与“正式发布”仍
按发布身份分开。DCS 领域实现始终由 DCS 仓库负责。

| 原始问题 | 审计前覆盖 | 当前文档覆盖 | 当前实现状态 | 正式入口与未决项 |
| --- | --- | --- | --- | --- |
| 现有文档审计和双层阅读结构 | 只部分回答 | **文档结构已覆盖** | 当前文档入口与语义检查已覆盖；正式发布仍待版本身份 | `docs-site/guide/index.md`；managed 指南同步纳入 inventory |
| Peanut Admin 架构、真实目录与所有权 | 只部分回答 | **文档结构已覆盖** | 当前目录已核验 | `docs-site/guide/development.md` |
| Module、Plugin、Host 和最小纵向切片 | 只部分回答 | **文档结构已覆盖** | 安装、治理、菜单/RBAC 和 fixture 同步命令 Guard 已支持；任务/回调/专属文件 Guard 与双 Module 示例待新增 | `docs-site/guide/module-development.md`、`docs-site/architecture/official-module-qualification.md` |
| 前后端、路由、菜单、权限和测试路径 | 部分且有冲突 | **文档结构已覆盖** | 原生身份、官方能力 Tenant 资格和真实浏览器验证已通过 | `docs-site/guide/development.md`、`docs-site/api.md` |
| DCS 与 Peanut Admin 的边界 | 只部分回答 | **Peanut 采用边界已覆盖** | DCS Runtime 不在本仓 | `docs-site/guide/development.md`；详细领域文档归 DCS |
| DCS owner 与商品、采购、库存数据流 | 只部分回答 | **推荐合同已覆盖** | 本仓不实现 | `docs-site/guide/module-development.md`；DCS 冻结表/API/事件/状态机 |
| 平台、Tenant 管理成员与业务客户身份 | 尚未回答 | **文档结构已覆盖** | 原生管理身份与独立 `pa_member` 已通过开发候选资格 | `docs-site/guide/development.md`；客户/供应商关联仍待产品决策 |
| 三类租户映射 | 只部分回答 | **文档结构已覆盖** | legacy 映射已退出；同应用关联和跨应用联邦未实现 | `docs-site/guide/development.md`、`docs-site/deployment.md` |
| 门店与供应商同应用协作和越权边界 | 只部分回答 | **推荐模型已覆盖** | DCS participant policy 未实现 | `docs-site/guide/development.md` |
| 一部署、一实例、多 Tenant/客户端/Module | 只部分回答 | **文档结构已覆盖** | 当前部署合同支持 | `docs-site/guide/development.md`、`docs-site/deployment.md` |
| Platform 是否独立、是否存在“当前租户” | 尚未回答 | **已完整回答** | 独立 `/platform/` 前端、会话/RBAC/审计；只显式选择治理目标 Tenant | `docs-site/platform.md` |
| 域名解析与租户切换是否冲突 | 尚未回答 | **已完整回答** | Host 绑定是持续边界；绑定入口禁切换，未绑定公共入口按 TenantMember 切换 | `docs-site/architecture/identity-and-tenancy.md`、`docs-site/platform.md` |
| `.env` 与 `PHP_*` 配置关系 | 尚未回答 | **已完整回答** | 人工只维护根 `.env` 普通键；启动器/Compose 派生 ThinkPHP 内部 alias | `docs-site/getting-started.md`、`docs-site/guide/development.md` |
| 兼容历史与干净脚手架 | 只部分回答 | **当前边界已覆盖** | fresh Schema 与 create-app 2.0 release 已验证重封 | `docs-site/deployment.md` |
| 开箱即用能力逐项建议 | 尚未回答 | **逐项目录已覆盖** | 开发候选实现与推荐层级已分栏；外部渠道生产验证另列 | `docs-site/capabilities.md` |
| 文档与 Runtime/Schema 清理关系 | 只部分回答 | **已明确** | 本轮已由独立提交实施后再同步文档 | 本审计与 `docs/architecture/clean-native-multitenancy-baseline.md` |

### 现有文档判定

| 文档范围 | 审计结论 | 当前处理 |
| --- | --- | --- |
| `README.md`、`docs-site/getting-started.md` | 1.x 快速入口已过时 | 改为 2.0 空库安装和候选状态 |
| `docs-site/guide/development.md` | 信息存在但难读，身份与兼容描述冲突 | 增加五分钟层、真实目录、三类身份/映射和部署边界 |
| `docs/plugin-module-development.md` | 信息存在但难读且站点不可达 | 扩为纵向教程，并通过 `docs-site/guide/module-development.md` 进入导航 |
| `docs-site/capabilities.md` | 完全缺失逐项产品建议 | 新增核心、官方可选、DCS、示例四层目录和维护成本 |
| `docs-site/deployment.md` | 1.x upgrade 与当前安装混写 | 改为 2.0 fresh-only；1.x 只作历史事实 |
| `docs/peanut-admin-development-guide.md` | 生成应用携带的旧身份/升级说明 | 收敛为 2.0 当前入口，避免传播旧 Runtime |
| `docs/peanut-admin-release-deployment.md` | 生成应用携带的 1.x 生产升级手册 | 收敛为 fresh deploy；历史生产记录不作命令入口 |
| `docs/architecture/*.md`、`docs/design/saas-roadmap/` | 仅内部合同或路线输入 | 不作为新手入口；固定历史内容不冒充当前 Runtime |
| `docs/product-status/releases/*` | 不可变 1.x 发布快照 | 保留追溯，不作为 2.0 完成证据 |

### 2.0 当前事实分类

- **开发候选已验证**：fresh 安装得到 87 表、197 菜单、43 配置；原生 Platform/Tenant
  登录、三 Tenant 选择和 Store Demo 真实浏览器通过；原生管理身份、独立业务会员、
  canonical fresh Schema、应用 Host 的 Tenant 隔离和单一权威会员余额字段均通过。
- **Module 当前部分完成**：Plugin 安装、TenantModule 治理、菜单/RBAC 和 fixture 同步命令
  Guard 已有证据；现有文件、通知、支付等只是 Tenant 适配的应用 Host，并非已交付官方可选
  Module。任务、回调与模块专属文件入口的统一 Guard 仍是正式模块的采用条件。
- **已部署候选体验**：固定候选 `d3d5900` 的隔离 `production-candidate` 已更新为头像
  fallback 候选；部署源码关键文件摘要、`d3d5900` 镜像、容器健康和 origin health 已核对。
  本地共享 Admin、Tenant A、Tenant B 的当前候选头像与菜单矩阵通过；线上共享 Admin 也已
  确认默认头像资源完整、无 broken image/加载残留且菜单可点击。线上 Tenant A/B 的旧截图
  生成于 `d3d5900` 之前且明确仍为破图，当前候选重拍受 Browser 控制超时阻塞，不能冒充
  通过。该候选体验不等于正式双模式 P0-E 或 2.0.0 发布证明。
- **仍待完成**：候选 `6eb06a4` 的 P0-E 已通过生成应用、双模式空库、Plugin 生命周期、
  生产 Compose 与 Standalone 浏览器六组；Multi-tenant 浏览器登录仍停在登录页，尚不能
  声明 Gate 通过。H5 默认装修在空内容时仍有无语义占位区域，资讯标题的主题色对比度也
  待修复和重拍。之后仍需 tag、GitHub Release 和正式发布部署证明；Core Alpha.5 的
  KernelSchema 字段遗漏由独立 Core 工作流修正。
- **仅迁移需要且已退出 2.0**：legacy Admin/Role/Dept map、默认 Tenant bootstrap、1.x
  adopt、余额双写和旧 scaffold upgrade Runtime；它们不得进入生成应用。
- **推荐新增**：供应商/门店/客户业务主体关联、participant policy、双 Module 合同示例。
- **暂不建议**：跨应用主体联邦、通用 Outbox/Event Bus、DCS 领域 Runtime 和跨实例运营平台。
