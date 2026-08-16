# Peanut Admin — Agent Context

> **Read this before touching any file.** This file is the authoritative project state record.
> Last updated: 2026-08-16

执行任何写任务前，同时读取根目录 `AGENT_EXECUTION_RULES.md`。本文件记录产品事实和
路线，执行规则由该独立文档维护。

本项目数据库、缓存、队列、对象存储、外部服务、域名、容器消费入口与本地固定端口的
版本化机器可读日常资源登记为 `resources/project-resources.json`；仅源仓 P0-E 资格工具及其
远程管理绑定登记为 `resources/p0e-runtime-qualification.json`。连接、启动、迁移、测试或
部署前必须先读取对应文件并显式选择资源 ID、环境和登记地址；本项目登记是唯一事实源，
使用前还必须按登记的健康检查核验真实资源。

---

## 1. 项目身份

| 项目 | 值 |
|------|----|
| 产品名称 | **Peanut Admin**（不使用任何版本后缀） |
| 本地目录 | `/Users/xing/Documents/company-projects/peanut-admin/` |
| GitHub 仓库 | `git@github.com:peanut-business/peanut-admin.git` |
| PC package name | `peanut-admin-pc` |
| 集成分支 | `dev` |
| 稳定分支 | `main` |

此前的独立编排工作区已经删除，内容已归档。当前目录是现行产品代码仓，不是旧编排工作区；后续任务只以本文件记录的目录、仓库和主分支为当前事实源。

---

## 2. 当前状态（2026-08-16）

### 2.0 2.0.0 fresh-only 候选 — 开发实现与必要本地资格完成

- 当前开发分支已切换为原生 `Account/Credential/TenantMember/RBAC` 管理身份；业务会员
  `pa_member` 保持独立。
- fresh install 使用 `server/database/init.sql` 与 Core KernelSchema 建立 canonical
  Schema；`server/database/migrations/` 当前没有基线后追加 SQL。
- 1.x Admin/Role/Department 映射、默认 Tenant bootstrap、兼容余额镜像和 1.x 数据库/
  scaffold 原地升级不属于 2.0.0 支持面。
- 登记多租户空库安装得到 85 张表、197 个菜单和 43 项配置；原生 Platform/Tenant 登录、
  三 Tenant 选择、Store Demo 工作台和官方能力强制 Tenant 资格检查已通过。
- `scaffold/releases/v2.0.0` 已从当前 fresh-only 源码重新封存；本地多租户体验使用登记资源
  `peanut-admin-mysql84-local-multi-tenant-demo`、API `127.0.0.1:20178` 和管理端
  `127.0.0.1:20179`，不得复用为生产资源。
- 当前源码尚未形成正式 `v2.0.0` tag、GitHub Release 或生产部署证明；下面的 1.x 发布、
  P0-E 和生产记录均为不可变历史证据，不能代替 2.0.0 正式发布资格。

### 2.1 LikeAdmin 1.9.4 标准版 Parity — ✅ 完成并独立验证

- 9 个 parity commits 已合并并推送到 `main`；已完成使命的功能分支不再作为后续工作基线
- 44 controllers、72 actions（≥ LikeAdmin 标准版 45/68）
- 1.x 数据库入口历史为 `install.php` + `migrate.php` + `init.sql` + 54 migrations；2.0
  当前数据库入口为 `init.sql` + `KernelSchema` + 0 migrations；下表中的
  24 条账本是 parity 时点证据，`v1.1.0` 发布制品固定为 50 条。该计数不是 2.0 当前 Schema。

**独立验证结果（非 Codex 自报）：**

| 验证项 | 方法 | 结果 |
|--------|------|------|
| Fresh-clone install | `git clone` → 空 MySQL → `php install.php` | 42 tables / 170 menus / 59 configs / 1 admin ✓ |
| 前端路由回归 | Playwright 1.62 headless Chromium 真实浏览器 | 30/30 routes pass ✓ |
| 升级演练 | fresh + legacy adopt + idempotent rerun | 43 tables / 24 migration ledger ✓ |

证据文件：
- `output/playwright/v01/clone-install-summary.json` — Codex 原始报告（参考）
- `output/playwright/v02/summary.json` — **本会话独立验证，可信**
- `output/playwright/v02/*.png` — 9 组截图 + 登录截图

### 2.2 多租户与平台管理 — ✅ MT00–MT06 已完成

设计文档位于 `docs/design/saas-roadmap/`（50 个文件）。默认 Tenant、可信管理端
TenantContext、代表 SQL/非 SQL Tenant 隔离、实例内 Tenant 治理和双模式 Host 已完成；
完成身份与验收范围以本节下方的 `v1.1.0` 事实和当前权威计划为准。判断后续范围前仍须
核对远端 `dev`，不得继续沿用“完全未实现”或“MT02–MT04 尚未整体完成”的旧判断。

截至 MT05 最终代码候选 `074fce5f4b1eb2dd2c89b8ddf0e2c3d7a74819a8`
（tree `1a2df02e97414b5c236a842adf17804fb33e4699`）：

- MT00、MT01 已完成，Core/Generator 与 DCS Product-only 条件采用身份已经固定；
- MT02 已完成默认 Tenant、旧管理员/RBAC/组织映射，以及 Article、字典、装修、
  会员/标签/余额等首批 Tenant-first SQL 域；
- MT03 已完成缓存/锁端口、文件、Crontab、操作日志与后台 diagnostics、通知、OAuth、
  导入导出、热搜、客服、交易设置、充值退款、Tabbar、同步 XLSX、会员上传和实例工具
  边界等独立隔离切片；
- PM01 已形成 PlatformOperator、Tenant 生命周期、首 owner、TenantModule 和平台端
  HTTP/Web 主链；MT04 已形成 Tenant 选择/切换/撤销、Admin Host bridge 和 Standalone UI；
- 管理员、角色、部门和岗位 CRUD 已 Tenant-first，并由数据库复合 Tenant 外键保护；
- MT05 浏览器候选 `2def481…` 的真实浏览器矩阵已通过，证据为
  `/private/tmp/pa-mt05-evidence/final-browser-2def481/summary.json`；旧候选
  `5bd3e78…` 的三模式安装/升级证据早于两条新增 migration，不能覆盖最终候选。
  PR #99 修正合法部署枚举 `multi-tenant` 后，最终候选的 Standalone 空库、`v1.0.0`
  前滚和多租户空库均以 50 条 migration/81 张表通过；MT02–MT05 已完成。MT06
  `v1.1.0` 已正式发布：`main/dev@c6a165f…`、annotated tag object `0f4fffd…`、
  [GitHub Release](https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.0)
  与一次干净 Composer/npm/source 安装验证均完成；MT06 已完成。

- 当前权威架构摘要：`docs/design/saas-enhancement-blueprint.md`
- 当前开发顺序：`docs/plans/multi-tenancy-platform-management-plan.md`；产品正式发布、独立运营平台与后续 SaaS 的跨项目交接顺序见 `docs/plans/product-release-operations-saas-roadmap.md`；完整 SaaS 商业化暂缓，未来规划保留在 `docs/plans/saas-enhancement-development-plan.md`
- 跨应用实例管理 Release、授权、升级、健康和备份的运营平台已明确为独立应用；它不属于核心包，也不是 SaaS Host 内的租户控制面

### 2.3 产品化正式基线 — ✅ 完成

- `v1.1.3` 已达到 `production-demonstrated`：`main/dev/tag@f0b2b81…`
  （tree `a9051b1…`）、annotated tag object `42e7e86…` 和正式 GitHub Release 身份一致；
  从规范 Release 附件生成的独立应用完成五套锁定依赖、生产模式启动和真实浏览器登录，
  生产 `oracle3:/www/docker/peanut-admin` 在配对备份后从 50 前滚到 54 条迁移，登录、API、
  工作台、PC、H5 与 TLS 最低 smoke 通过。不可变证据见
  `docs/product-status/releases/v1.1.3.json`。
- 执行计划：`docs/productization-baseline-plan.md`；能力图：`docs/architecture/core-application-capability-graph.md`
- 已完成：生产 Compose、迁移账本、三端 Docker、产品最低 CI、核心包公开发布、核心仓文档 CI、管理端 Element Plus、标准覆盖 Host、PC/UniApp 无 UI client 消费
- 生产发布：`dev` 已部署到 `peanut-admin.007345.xyz`；登录、文章页、PC、H5 与文档真实 Chromium smoke 通过，证据见 `output/playwright/production-baseline/final-summary.json`
- 日常开发和本机生产模式预览只使用 Peanut Admin 项目登记的
  `peanut-admin-mysql84-development`（development）：
  `192.168.192.2:20183/peanut_admin_development`，MySQL `8.4.10`。凭据引用为
  `mac-14:/Users/xing/.config/peanut-admin/development-db.env`，不得把密钥写入仓库。
  机器可读事实源为 `resources/project-resources.json`；P0-E 专用远程管理工具登记为
  `resources/p0e-runtime-qualification.json`。使用前仍须按登记要求核验目标实况。旧开发
  端口 `3306` 与旧库名 `peanut_admin` 的组合没有迁移账本，不得连接或作为现行指引。
  本机不运行 Peanut Admin MySQL。当前生产服务器使用自身 `bundled-db` MySQL profile，
  不能路由开发局域网地址；两类资源由 `PEANUT_DEPLOYMENT_TARGET` 和
  `PEANUT_DATABASE_RESOURCE_ID` 门禁隔离。
- 已完成 PB03：`docs/architecture/pb03-ownership-and-migration-gates.md` 已冻结核心通用基础设施、应用产品 Module、唯一实现、Host/override、测试 owner 与逐领域停止线
- PB04 已完成：网站设置、权限 Host、管理员/RBAC CRUD、字典、文件素材、任务/导入导出与日志/维护均形成应用唯一实现、核心候选停止线及测试 owner
- PB05 已完成：会员/标签、权威余额、兼容镜像、分类流水、充值入账与退款形成应用唯一 Runtime；核心 Tenant membership 与 R01/R02 候选未经采用授权
- PB06 已完成：文章/分类/收藏/计数与移动/PC/Tabbar 装修形成应用唯一 Runtime；产品资源保留 Provider provenance，四端共用唯一装修读取 DTO
- PB07 已完成：通知、支付、OAuth 与外部渠道均固定应用唯一 Host；旧 Channel CRUD、重复凭据和未实现的公众号 AES 配置入口已退出，PC/公众号通过固定 API bridge 回跳；核心相邻候选未获下游采用授权
- PB08A 实现已完成：品牌单一 Runtime、中性安装默认、四端消费、包元数据、官网与文档门户均已落地；静态官网门禁通过，唯一桌面/移动 Chromium 验收归 PB08B
- PB08B 已完成：候选 `4442229…` 通过 registry 构建、弱凭据/24→28/空库、Compose/HTTP/镜像/Host、唯一桌面/移动 Chromium 与文档一致性；总摘要见 `output/playwright/pb08b/summary.json`
- PB09 已完成：法律门禁、PR #10/#11、`dev/main` 合入、annotated `v1.0.0`、GitHub Release、既有应用与官网部署、24→28 前滚和一次最低线上 smoke 均已封存；生产运行镜像由不可变 tag 源码在部署端构建，不发布预构建镜像
- Element Plus 证据：`output/playwright/element-plus-baseline/summary.json`，真实 Chromium 登录及 7 个代表业务域全部通过
- 产品化正式基线与多租户稳定脚手架均已进入 `main`；当前产品固化发布仍以能力账本和跨项目路线的完成条件为准。独立运营平台已获准在同级独立项目立项并先行设计，不进入本仓 Runtime；完整 SaaS 商业化仍暂缓

### 2.4 产品能力与交付状态事实源

当前能力的“已验证、实现待验收、计划/受阻、暂缓/范围外”详细矩阵，以
`docs/product-status/capability-ledger.json` 为唯一机器可读事实源；对应人类可读入口为
`docs/product-status/README.md`。本节只保留高层产品事实，不再复制能力表。开放 PR、运行中的
Gate 和未提交 worktree 不得提前写成完成；正式产品发布时才在
`docs/product-status/releases/` 冻结不可变能力快照。该内部目录默认不进入 docs-site 首页或
公开导航。

---

## 3. 目录结构

```
peanut-admin/
├── server/          # ThinkPHP 8 后端
│   ├── app/adminapi/    # 管理端 API（44 controllers）
│   ├── app/api/         # 前端/小程序 API
│   ├── database/
│   │   ├── install.php  # 一键安装（空库 → 全量初始化）
│   │   ├── init.sql     # 基础表 + 种子数据
│   │   └── migrations/  # 2.0 基线后追加迁移；当前仅 .gitkeep
│   └── .env             # DB/JWT 配置（不提交）
├── web/             # 管理端前端（Vue3 + Element Plus）
├── pc/              # PC 消费端（Nuxt3）
├── uniapp/          # 小程序/H5 客户端
├── docs/
│   ├── design/saas-roadmap/   # SaaS 路线图（roadmap only）
│   └── peanut-admin-*.md      # 开发指南、用户手册
└── output/playwright/         # 验证证据
    ├── v01/  # Codex 自报
    ├── v02/  # 独立验证（可信）
    └── element-plus-baseline/  # Element Plus 迁移真实浏览器证据
```

---

## 4. 开发入口与安装身份

- 初始管理员邮箱必须在空库安装时通过 `ADMIN_INITIAL_EMAIL` 提供，密码通过
  `ADMIN_INITIAL_PASSWORD` 显式提供；仓库没有可供当前环境复用的共享默认账号或密码。
  历史 parity 证据中的 `admin` 用户名和旧密码仅用于追溯，不是现行登录指引。
- 管理端 API 登记默认值：`http://127.0.0.1:20180/`（由 `scripts/local-stack.sh dev-up` 托管）
- 前端 Dev 登记默认值：`http://127.0.0.1:20181`（`pnpm dev`，位于 `web/`）
- API 前缀：`admin/*`（管理端），`api/*`（前端/小程序）
- 日常开发统一从 `scripts/local-stack.sh dev-up` 启动：API 使用项目登记的宿主
  `/opt/homebrew/bin/php` 8.3.24 与 `/usr/local/bin/composer` 2.8.10；development Compose
  不含 PHP 服务，容器通过 `host.docker.internal:${PHP_PORT}` 访问宿主 API。所有本地
  监听从 `.local/stack.env` 或 `PEANUT_LOCAL_ENV_FILE` 读取，`201xx` 只是项目登记默认值。
  Docker PHP 只用于 local-production-preview、生产构建和明确要求的容器等价 Gate。

---

## 5. 命名规范（重要）

LikeAdmin 到 Peanut Admin 的刻意改名，**不是缺失**：

| LikeAdmin 原名 | Peanut Admin 名称 |
|----------------|-------------------|
| `user` | `member` |
| `dev_crontab` | `crontab` |
| `generate_*` | `generator_*` |
| `notice_record` | `notice_log` |
| `notice_setting` | `notice_scene` + `notice_template` |
| `user_auth` | `oauth_*` |

---

## 6. 给 Codex 的工作约定

1. 所有任务在 `/Users/xing/Documents/company-projects/peanut-admin/` 下执行
2. 功能分支命名：`feat/<描述>`，完成后 PR → `dev`；阶段验收通过后 `dev` → `main`
3. 不要创建带产品版本后缀的名称、路径或文件名
4. SaaS 相关实现需求 → 先查阅 `docs/design/saas-roadmap/` 再动手
5. DB 变更：新建 `server/database/migrations/YYYYMMDD-<描述>.sql`，不要直接修改 `init.sql`

## 7. Gate 依赖与并行推进

- 阶段编号只规定最终完成声明和集成验收的顺序，不自动禁止后续阶段开始。
- 任何“阻塞”必须写明被阻塞的具体交付物、缺失输入和解除条件；不得只用“前一阶段未完成”冻结整个后续阶段。
- 不依赖缺失输入、文件 owner 不重叠且可独立回滚的合同、迁移、Runtime、fixture 和测试准备应并行推进。
- 外部发布、Registry 身份或单个 CI Gate 只阻塞直接消费该身份或证据的候选填值、最终验收和完成声明，不冻结无依赖实现。
- 后续阶段可以形成独立 PR，但在其真实前置尚未满足时不得合入共享集成候选、执行最终 Gate 或声称阶段完成。
- 每次设置停止线时同时列出“仍可并行项”；若没有可并行项，必须记录具体代码或数据依赖，而不是沿用阶段序号推断。
