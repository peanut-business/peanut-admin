# Peanut Admin — Agent Context

本仓是现行 Peanut Admin 产品代码仓，位于 `/Users/xing/Documents/company-projects/peanut-admin/`。
集成分支为 `dev`，稳定分支为 `main`，仓库为 `git@github.com:peanut-business/peanut-admin.git`。
产品名称不带版本后缀，PC package name 为 `peanut-admin-pc`。更新日期：2026-09-07。

## 按任务读取

先确定当前任务与相关文件。写任务读取 `AGENT_EXECUTION_RULES.md` 中适用的授权、Git 和技术边界；候选封存、发布、历史证据仅在对应任务中读取。

| 当前任务 | 事实源与入口 |
| --- | --- |
| 项目运行资源、连接、启动、迁移、测试、部署 | `resources/project-resources.json`；源仓 P0-E 专用资格工具另见 `resources/p0e-runtime-qualification.json` |
| 服务层或跨 Module 调用改造 | `resources/service-registry.json`、`docs/architecture/service-layer-registry.md`；Module 自有表以对应 `module.json` 为准 |
| 服务执行状态或交接 | `docs/architecture/service-execution-status.md` |
| 能力、完成度、源码 Release、演示部署 | `docs/product-status/README.md`、`docs/product-status/capability-ledger.json` 与对应 `releases/`、`deployments/` 快照 |
| 文档变更 | `docs/README.md`、`docs/document-registry.json`、`docs/document-impact-map.json` |
| SaaS 边界或后续路线 | `docs/design/saas-enhancement-blueprint.md`、`docs/plans/multi-tenancy-platform-management-plan.md`；按具体问题选择 `docs/design/saas-roadmap/` 下文件 |
| 跨项目发布、运营平台与 SaaS 交接 | `docs/plans/product-release-operations-saas-roadmap.md` |

运行资源必须显式选择已登记 ID、环境、地址，并按登记核验健康及新鲜度；不得猜测或替换目标。共享运行资源的租约与清理规则见执行规则 §5。日常开发与生产、固定资格环境分别隔离。

需要调用关系、影响范围、服务层、Module 边界或架构事实的代码任务，先运行 `scripts/project-codegraph ensure`；纯文档、简单机械修改和已明确单文件局部查看不启用。规则见 `resources/codegraph-registry.json`。当前 worktree 无索引时先用 `scripts/project-codegraph status` 核对其他 worktree，再仅初始化当前 worktree；索引不得复制或共享。本条仅适用于本项目。

## 当前产品边界

- 当前正式源码与演示身份分别见 `docs/product-status/releases/v3.0.13.json` 和 `docs/product-status/deployments/v3.0.12-online-experience.json`；源码发布不代表生产部署完成。
- 当前管理身份为原生 Account/Credential/TenantMember/RBAC，业务会员 `pa_member` 独立。1.x Admin/Role/Department 映射、默认 Tenant bootstrap、旧数据库或 scaffold 原地兼容升级不属于当前支持面。
- Standalone 与 Multi-tenant 必须由同一冻结源码确定性生成。跨实例运营平台是独立应用，不进入本仓或 Core Runtime；完整 SaaS 商业化仍暂缓。
- 完成判断以能力账本、现行源码和相应固定证据为准。历史 PR、迁移/菜单计数和旧任务日志不作为当前基线；追溯时读取对应 Release 快照或 Git 历史。
- Marketplace、部分/多次退款、真实 Provider 资格、完整 SaaS 商业化、预构建生产镜像及第三方业务生产部署的范围，以当前能力账本和任务授权为准，不从历史计划自行领取。

## 开发入口

- `server/`：ThinkPHP 8；`web/`：Vue 3 + Element Plus 管理端；`platform/`：平台管理端；`pc/`：Nuxt 3；`uniapp/`：小程序/H5。
- 空库初始化使用 `server/database/init.sql` 与 Core KernelSchema；Schema 变更新建 `server/database/migrations/YYYYMMDD-<描述>.sql`，不直接改 `init.sql`。当前迁移集合以目录和账本为准，不在本文件维护计数。
- 初始管理员邮箱和密码由安装时的 `ADMIN_INITIAL_EMAIL`、`ADMIN_INITIAL_PASSWORD` 显式提供；历史演示账号不是当前环境的默认凭据。
- 日常开发从 `scripts/local-stack.sh dev-up` 启动。API 与 Web 的登记默认入口分别为 `127.0.0.1:20180`、`127.0.0.1:20181`；实际监听从 `.local/stack.env` 或 `PEANUT_LOCAL_ENV_FILE` 读取并保留已有覆盖。
- 日常 API 使用登记宿主 PHP/Composer；development Compose 不含 PHP。Docker PHP 只用于生产模式预览、生产构建或明确要求的容器等价检查。
- 日常开发数据库仅使用登记的 `peanut-admin-mysql84-development`（development）；地址与凭据引用从项目资源登记读取。本机不运行替代 MySQL；旧 `3306/peanut_admin` 未登记组合不得连接。生产只使用生产登记资源，不能继承开发数据库。
- Platform 安装/静态检查使用 `peanut-admin-host-node24-npm-development` 和 `platform/package-lock.json`；每个 worktree 安装自己的依赖，不复用其他 worktree 的 `node_modules`。
- API 前缀为 `admin/*`（管理端）和 `api/*`（消费端）。文档最低静态检查为 `./scripts/docs-governance check`。

## 命名与交付

LikeAdmin 到 Peanut Admin 的刻意改名不是缺失：`user → member`、`dev_crontab → crontab`、`generate_* → generator_*`、`notice_record → notice_log`、`notice_setting → notice_scene + notice_template`、`user_auth → oauth_*`。

功能分支使用 `feat/<描述>`；完成最低充分验证后直接合入并推送 `dev`，清理本任务分支与 worktree。PR 仅用于 `dev → main`、正式发布或明确要求的评审。不要创建带产品版本后缀的产品名称、目录或文件名。

阶段编号不冻结独立工作。阻塞须写明具体交付物、缺失输入和解除条件；未满足真实前置的候选不得声称完成或进入依赖它的集成/资格。与受阻输入无依赖、写集和资源 owner 不冲突的工作可继续。
