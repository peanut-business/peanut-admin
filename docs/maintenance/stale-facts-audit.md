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
