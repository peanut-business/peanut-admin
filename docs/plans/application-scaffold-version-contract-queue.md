# 独立应用与脚手架版本合同执行队列

> 目标：应用按自己的 `product_release` 发布，脚手架按不可变 manifest 与
> `scaffold_template` 升级，Core 按 Composer/npm lock 身份消费；升级不得覆盖 app-owned
> 业务代码、密钥或业务数据。本文是内部执行队列，可以登记 Development 完成证据，但不代表
> 正式 Release、候选锁或生产资格证据。

## 固定边界

- `release-versions.json.product_release` 是当前应用版本；`.peanut/application-manifest.json`
  的 `application.version` 是最近一次脚手架采用的渲染快照。
- Peanut SQL 的 `peanut-release` 标记属于 scaffold/Schema 版本轴；没有该标记的应用 SQL
  属于当前应用源码。Module 继续使用自己的迁移账本，Core Schema 继续跟随锁定包。
- 开发期先采用 scaffold，再由应用形成自己的不可变 release，最后部署完整应用 release。
  Platform 升级入口不得把上游 scaffold 全源码当成独立应用发布物。
- 当前发布清单采用 full profile，创建示例必须显式传 `--profile=full`；默认 standard profile 与
  当前 full managed 清单的机制缺口留作独立后续问题。
- 本轮是 Development mode；不创建正式候选锁，不改不可变历史 Release，不连接生产资源。

## 可执行任务

| ID | 任务与依赖 | 精确规则与写集 | Owner / 模型 | 最低验收证据 | 状态 |
| --- | --- | --- | --- | --- | --- |
| V1 | 修正 Runner 的应用版本事实源；依赖现有 v2 manifest、`release-versions.json` 和三方比较 | 只改 `scripts/scaffold-runtime/ScaffoldUpgradeRunner.php` 及直接文档；preflight 冻结当前应用版本、采用快照、版本合同全文与摘要，apply 只读 plan；只识别版本 token 投影，不增加通用 JSON 合并或兼容 fallback | Sol/high 实现；Terra/medium 只读核对；根代理方向与审计 | 当前 3.x 样本连续 `0.1.0 → 2.7.0 → 2.8.0` 采用两个真实 builder 产物；快照推进且生成默认值不漂；合同漂移拒绝 | 代码已二审；Development 聚焦组通过 |
| V2 | 分离安装、迁移与部署版本轴；依赖 V1 合同和现有不可变 SQL | 改 `server/database/install.php`、`InstallationExecutionHost`、运行状态筛选、`scripts/deploy-release`、demo overlay builder、直接文档与错误断言；fresh 返回前执行合资格迁移，`--target-version` 只表示 scaffold SQL 目标；app tag 只做发布顺序，Core/Module 账本不混用 | Sol/high 实现；Terra/medium 核对历史 DDL；根代理审计 | Standalone `product_release=0.1.0` 与 Multi-tenant `2.7.0` 从空库完成同一 scaffold 迁移；必要表/列存在，业务保留行未变，再次 dry-run 无 pending；跨 scaffold major 停止 | 代码已二审；Development 双 Edition fresh/迁移组通过 |
| V3 | 分离 Platform 的完整应用 release 与 scaffold provenance；依赖 V1/V2 的三轴合同 | `PlatformUpgradeTarget` 分别固定应用 release commit/tree/tag 与 scaffold manifest 来源并重算完整应用 Git tree；worker 用 task 固定 commit/tree 部署，不删除资格、权限、备份、恢复、维护或清理门禁 | Sol/high 实现；Terra/medium 只读链路核对；根代理冻结设计与二审 | 独立应用发布身份与 scaffold 来源分别篡改均 fail closed；same-scaffold 应用发布可升级；worker 部署完整应用 release，不在 Runtime 合并上游 scaffold | 代码已二审；Development 静态合同与聚焦组通过 |
| V4 | 运行聚焦闭环；Runner 组依赖 V1，DB 组依赖 V2，Platform 聚焦组依赖 V3 | 用现有 builder 生成固定 Development scaffold 目标与双 Edition 应用；每个样本独立 Composer vendor，不借其他项目；Runner 组验 managed/冲突/recover，DB 组验 fresh/保留/幂等；Platform 组验完整 Git tree、两条版本轴、task 部署身份与 UI 静态合同 | Sol/high 执行复杂组；验证子步骤按复杂度使用 5.6 模型；根代理审计证据 | 每组独立记录固定 commit/tree/inventory、命令、资源 ID/租约、输出摘要和清理；运行时与数据库证据来自样本自身代码/vendor | V1 Runner、V2 DB 与 V3 Platform 静态/聚焦组通过；完整 Runtime 矩阵未运行 |
| V5 | 文档、注释和一次 Git 交付；每个独立合同批次依赖其代码与证据冻结 | 同步本页、创建/升级/部署文档、登记与生成 catalog；受改复杂方法写职责/异常原因；只暂存本批目标文件，执行一次 docs check、inventory 与 cached diff check，再由根代理完成最终 Git 审核 | Sol/high 写作与交付；根代理终审 | docs governance、inventory、diff check、commit/tree 与 clean worktree；明确 Development 完成证据和未验证范围 | V1/V2 已合入 `dev`；V3 代码、Development 证据及根终审完成，随本提交交付 |

## 验证资源

动态迁移只使用两个固定 development/ephemeral 逻辑资源：
`peanut-admin-application-version-fresh010-development` 覆盖 Standalone，
`peanut-admin-application-version-fresh270-development` 覆盖 Multi-tenant。数据保留与幂等复用这
两个精确命名的库。连接前必须公告登记 ID、development 环境和 `192.168.192.2:20183`，核验
服务、固定选择器、独占租约和数据库缺失状态；无 fallback。

正式发布、P0E 全资格、浏览器矩阵、生产部署与依赖升级不在 V4 的 Development 验证中，仍按
`AGENT_EXECUTION_RULES.md` 的适用候选、资源和发布规则执行。

## V1/V2 Development 验证证据（2026-09-07）

- Runner 执行器固定为 `03815114a3691107dc5089eff8e94a8d9b35012d` / tree
  `f8841bbb398a90be657b135d7e670881e613292b`。连续采用使用 full/Standalone Edition manifest：
  `3.0.14` source `9498b201fb0b1718f8170ca3b054c170357a91ae` / tree
  `1065fa982d422eec9e05e8511051afcb15975c21` / manifest
  `b55578e438f9c6c961181b015fa358b3ba3bf7df2110e40e9268141c6bef359a`，`3.0.15` source
  `8dc74f3c31a3f759c6f66f6ca3d297fce26bcaf4` / tree
  `8104296dfdd5d78de0bb46e960951872f1947744` / manifest
  `e33135b9ba4b322f51a80f51b83c4d33310957d8d07f10c260b76f27e02a25e2`，`3.0.16` source
  `b2164deface561f3ca5d770c703ee15111d6ef66` / tree
  `1a7c70faa854f612d6dc9f6dbdb420469d6fdd46` / manifest
  `1a2efef766303bfe927c3f3c88fb834d36b45764ea17fb0ffb697804a65ea383`。这些都是 Development 输入，
  未改变已发布版本，也未证明旧 artifact 内尚未收录的新版执行器。
- `preflight/apply/verify` 已覆盖 `product_release 2.7.0 → scaffold 3.0.15`，再覆盖
  `product_release 2.8.0 → scaffold 3.0.16`；采用快照分别推进至 2.7.0/2.8.0，原始
  `generation_source`、`generated_application_default=0.1.0`、`.env`、app-owned 文件和本地
  Composer 定制保持。普通 `jq` 键序/空白变化通过精确七键语义比较；Core 同文件双边修改、
  版本合同 TOCTOU、上游删除时 mode 改动均 fail closed；故障注入后 `recover` 恢复原摘要。
- fresh 安装使用同一个 full/generic adoption artifact：source `9e8b8094c021bdfece3deb49cc2fb13643223361`
  / tree `7f7ffb894a7050da3dfe5a0178f7ce7c5e973dc4` / inventory
  `00b2e8d055a9d5716abb794430641d10606e5d2f64cb40031dff14be49c258bd` / manifest
  `dc9847a2a9387ba2a0749c1e921df09f870b16a03650ca708096cc0564f27d67`。Standalone `0.1.0`
  与 Multi-tenant `2.7.0` 分别在上述两个登记库
  完成安装，10 条不可变应用迁移均记为 `applied`；技术 migration 记 scaffold release，3 条
  无 marker SQL 分别记应用版本。Edition 必需表/列、Article owner 与 readiness seed 存在；
  再次 `install.php --migrate --target-version=3.0.14 --dry-run` 均为 `up_to_date`，插入的
  app-owned Article 分类 sentinel 保留。
- 首次 full 生成准备曾因父目录/Edition manifest 选择错误失败；改用真实 generic adoption
  manifest 后生成通过。首次 fresh 暴露 installer/runtime 引用未进入生成 inventory 的 reader；
  修正为 managed `ApplicationReleaseVersions` 后，按同一固定源码重建样本并通过。临时探针两次
  因误用临时表及未读取 Tenant 列失败，不计产品 Gate，后续按实际 Schema 核对。
- 实际入口包括 `scripts/scaffold-upgrade preflight|apply|verify|recover` 与
  `php server/database/install.php [--migrate --target-version=3.0.14 --dry-run]`；动态日志在租约
  `appver-v1v2-038151` 的 output 中完成核对；两个精确数据库已删除、租约已释放，临时 output/cache
  已按 owner 责任清理。

`ApplicationReleaseVersions` 是 managed 运行时合同；`InstallationExecutionHost` 与
`ApplicationRuntimeStatusProvider` 仍为 app-owned。既有独立应用须由自身 owner 审阅并采用这两处
修正，scaffold 不会自动覆盖其稳定 Host。当前证据未运行正式候选资格、生产部署、浏览器或完整
运行时矩阵。

## V3 Development 验证证据（2026-09-07）

- 根二审通过的实现 checkpoint 为 `74e57f8497f3a9868e61a43f3d8a5b13ea502e43` / tree
  `b701bce9b9c4e03dbbecd9233dc1821bd350465c`；其补丁逐字重放到 V1/V2 最终基线
  `7fdc25ca3f4fd6fda9213aefd7f6656cb1c1c998` 后为 `de30c66a`。staged `release/` 按 Git 的
  内容、目录/文件排序和 executable mode 重算完整 tree；应用 release 身份与 from/to scaffold
  manifest 独立绑定。应用 `product_release` 严格递增，而 from/to scaffold 相同且 manifest 摘要
  相同时允许纯应用升级。
- 受改 PHP lint、两个运维脚本 `bash -n`、`PlatformUpgradeTargetModuleTest.php`、
  `DemoSitePatchContractTest.php`、Platform `npm run type:check` 与 `git diff --check` 均通过。
  Composer 使用本 worktree 独立 vendor；首次安装已解包依赖，但因未配置 `server/.env` 在
  post-autoload discover 停止，聚焦使用 `composer install --no-scripts` 完成后运行上述测试。
- 根审计发现并修正三处真实缺口：首次 maintenance→deploy 响应遗漏 task commit/tree；Git mode
  曾错误按任一执行位判断，现与 Git 一致只取 owner execute 位；目标 application manifest 的采用
  快照曾拒绝预发布 SemVer，现允许预发布表面且不要求等于当前 `product_release`。
- 这些是静态合同与 Development 聚焦验证。未执行生产部署、正式 tag/Release、P0-E、完整 Runtime
  状态机、恢复演练或浏览器矩阵；既有 canonical Peanut 固定生产 worker 也不证明独立应用具备通用
  生产部署能力。独立应用须由 owner 提供自己的不可变 release、资源登记与执行器配置。
