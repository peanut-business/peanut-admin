# 独立应用与脚手架版本合同执行队列

> 目标：应用按自己的 `product_release` 发布，脚手架按不可变 manifest 与
> `scaffold_template` 升级，Core 按 Composer/npm lock 身份消费；升级不得覆盖 app-owned
> 业务代码、密钥或业务数据。本文是内部执行队列，不是完成或发布证据。

## 固定边界

- `release-versions.json.product_release` 是当前应用版本；`.peanut/application-manifest.json`
  的 `application.version` 是最近一次脚手架采用的渲染快照。
- Peanut SQL 的 `peanut-release` 标记属于 scaffold/Schema 版本轴；没有该标记的应用 SQL
  属于当前应用源码。Module 继续使用自己的迁移账本，Core Schema 继续跟随锁定包。
- 开发期先采用 scaffold，再由应用形成自己的不可变 release，最后部署完整应用 release。
  Platform 升级入口不得把上游 scaffold 全源码当成独立应用发布物。
- 本轮是 Development mode；不创建正式候选锁，不改不可变历史 Release，不连接生产资源。

## 可执行任务

| ID | 任务与依赖 | 精确规则与写集 | Owner / 模型 | 最低验收证据 | 状态 |
| --- | --- | --- | --- | --- | --- |
| V1 | 修正 Runner 的应用版本事实源；依赖现有 v2 manifest、`release-versions.json` 和三方比较 | 只改 `scripts/scaffold-runtime/ScaffoldUpgradeRunner.php` 及直接文档；preflight 冻结当前应用版本、采用快照、版本合同全文与摘要，apply 只读 plan；只识别版本 token 投影，不增加通用 JSON 合并或兼容 fallback | Sol/high 实现；Terra/medium 只读核对；根代理方向与审计 | 当前 3.x 样本连续 `0.1.0 → 2.7.0 → 2.8.0` 采用两个真实 builder 产物；快照推进且生成默认值不漂；合同漂移拒绝 | 代码已二审，尚未验证 |
| V2 | 分离安装、迁移与部署版本轴；依赖 V1 合同和现有不可变 SQL | 改 `server/database/install.php`、`InstallationExecutionHost`、运行状态筛选、`scripts/deploy-release`、demo overlay builder、直接文档与错误断言；fresh 返回前执行合资格迁移，`--target-version` 只表示 scaffold SQL 目标；app tag 只做发布顺序，Core/Module 账本不混用 | Sol/high 实现；Terra/medium 核对历史 DDL；根代理审计 | Standalone `product_release=0.1.0` 与 Multi-tenant `2.7.0` 从空库完成同一 scaffold 迁移；必要表/列存在，业务保留行未变，再次 dry-run 无 pending；跨 scaffold major 停止 | 实现进行中，尚未审阅或验证 |
| V3 | 分离 Platform 的完整应用 release 与 scaffold provenance；依赖 V1/V2 的三轴合同 | 待审 `PlatformUpgradeTarget`、worker、描述符和直接文档；分别固定应用 release commit/tree/tag 与 scaffold manifest 来源，部署只能使用应用自己的不可变制品；不删除现有资格、备份或清理门禁 | Sol/high 实现；Terra/medium 只读链路核对；根代理冻结设计与审计 | 独立应用发布身份与 scaffold 来源分别篡改均 fail closed；worker 调用完整应用 release，未把上游 scaffold 源树覆盖业务文件 | 未实现 |
| V4 | 运行聚焦闭环；Runner 组依赖 V1，DB 组依赖 V2，Platform 组只依赖 V3 | 用现有 builder 生成固定 Development scaffold 目标与双 Edition 应用；每个样本独立 Composer vendor，不装 Node、不借其他项目；Runner 组验 managed/冲突/recover，DB 组验 fresh/保留/幂等；本批不运行尚未实现的 Platform 组 | Sol/high 执行复杂组；验证子步骤按复杂度使用 5.6 模型；根代理审计证据 | 每组独立记录固定 commit/tree/inventory、命令、资源 ID/租约、输出摘要和清理；运行时与数据库证据来自样本自身代码/vendor | V1/V2 组待执行；Platform 组等待 V3 |
| V5 | 文档、注释和一次 Git 交付；每个独立合同批次依赖其代码与证据冻结 | 同步本页、创建/升级/部署文档、登记与生成 catalog；受改复杂方法写职责/异常原因；只暂存本批目标文件，执行一次 docs check、cached diff check，再按项目规则合入并推送 `dev`；整目标在 V3/Platform 组完成前仍保持未完成 | Sol/high 写作与交付；根代理终审 | docs governance、diff check、commit/tree、push、clean worktree；明确本批完成、后续 V3 和未验证范围 | 本批等待 V1/V2 组；整目标仍有 V3 |

## 验证资源

动态迁移只使用两个固定 development/ephemeral 逻辑资源：
`peanut-admin-application-version-fresh010-development` 覆盖 Standalone，
`peanut-admin-application-version-fresh270-development` 覆盖 Multi-tenant。数据保留与幂等复用这
两个精确命名的库。连接前必须公告登记 ID、development 环境和 `192.168.192.2:20183`，核验
服务、固定选择器、独占租约和数据库缺失状态；无 fallback。

正式发布、P0E 全资格、浏览器矩阵、生产部署与依赖升级不在 V4 的 Development 验证中，仍按
`AGENT_EXECUTION_RULES.md` 的适用候选、资源和发布规则执行。
