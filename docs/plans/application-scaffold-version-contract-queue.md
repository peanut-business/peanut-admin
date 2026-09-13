# 独立应用与脚手架版本合同执行队列

> 2026-09-09 版本语义补正：本文历史 V1–V4/U1–U3 中“独立应用”指客户 Instance，实例发布和源产品采用仍分开。Peanut Admin 产品 Application、Core 与双 Edition 必须同号，Module 独立；现行决定由[版本身份 ADR](../architecture/product-version-identity-adr.md)拥有。U4 的旧“产品应用继续独立编号”解释已失效，旧证据中的实际版本值不回写。

> 历史 V1 目标：客户实例曾按自己的 `product_release` 发布，脚手架按不可变 manifest 与
> `scaffold_template` 升级，Core 按 Composer/npm lock 身份消费；升级不得覆盖 app-owned
> 业务代码、密钥或业务数据。本文是内部执行队列，可以登记 Development 完成证据，但不代表
> 正式 Release、候选锁或生产资格证据。

## 固定边界

- V2 合同的 `source_product_version` 是源产品版本，`instance_version` 是客户实例版本；历史 V1 的 `product_release` 依当时发布对象解释。`.peanut/application-manifest.json` 的 `application.version` 是最近一次脚手架采用的渲染快照。
- Peanut SQL 的 `peanut-release` 标记属于 scaffold/Schema 版本轴；没有该标记的应用 SQL
  属于当前应用源码。Module 继续使用自己的迁移账本，Core Schema 继续跟随锁定包。
- Module 版本由 manifest、archive 摘要/签名和安装账本固定；默认先进入应用仓采用、锁依赖、
  构建和验收，再随完整应用 Release 部署，不能把低层源码/数据库操作称为生产热更新。
- 开发期先采用 scaffold，再由应用形成自己的不可变 release，最后部署完整应用 release。
  Platform 升级入口不得把上游 scaffold 全源码当成独立应用发布物。
- 当前发布清单采用 full profile，创建示例必须显式传 `--profile=full`；默认 standard profile 与
  当前 full managed 清单的机制缺口留作独立后续问题。
- 本轮是 Development mode；不创建正式候选锁，不改不可变历史 Release，不连接生产资源。
- 后续正式发行列车要求 Peanut 产品 Application、`scaffold_template`、PHP Core 与 Web Core 同号并同步预发布后缀；客户 Instance 与 Module 版本分别独立。当前旧合同把实例版本存入 `product_release`，属于正在整改的字段语义，不是允许产品/Core 不同号的规则。历史 `3.0.13` / `0.1.0-alpha.12`、`3.0.14` / `0.1.0-alpha.13` 身份不回写。

## 当前生命周期缺口队列

| ID | 任务与依赖 | 精确规则与写集 | Owner / 模型 | 最低验收证据 | 状态 |
| --- | --- | --- | --- | --- | --- |
| U0 | 核对生成、scaffold、Core、Module、应用发布与部署真实入口 | 只读核对现行命令、manifest/lock/账本、已有 Development 证据；不重复 V1–V3 聚焦组，不把局部证据外推为运行升级 | Terra/medium 只读；根代理取舍/审计 | 四种身份及“签名 scaffold 包用于开发采用、完整应用 Release 用于实例部署”形成源码可追溯结论 | 已完成，后续执行跳过 |
| U1 | 完整应用部署与 Package composition guard；依赖当前运行库 lock、目标 Release lock 和活动 Package catalog 均可读取 | `release_identity` Sol/high owner 保留 Platform 对 active Module 缺失/降级的既有检查；目标根运行 `plugin:release-composition --current-root=<current>`，在清代码根或 DB 动作前补齐直接 deploy、Plugin artifact 和 private Package 全状态的三方身份；缺失、回退、同版本换内容及 failed、retire/purge 进行中或未知状态阻断；身份不变且 Module 均为正常 maintenance 禁用态时保留并由部署后 `plugin:reconcile --release-locked` 跳过；higher private Package 的完整成员集、migration/catalog 成功路径保留；隔离 staging 先锁定安装/构建，再切换完整应用制品 | `release_identity` Sol/high 实现与验证；Terra/medium 只读核对；根代理二审 | 旧应用 Release 在登记隔离资源上升级到新 Release，业务文件/数据/Package 保留，build/migration/restart/HTTP 与配对备份校验、可用恢复坐标有证据；本次不跑 Platform worker 全状态机或真实恢复演练，生产资格仍单列 | Development 联合序列通过；正式生产、Platform worker 全状态机与真实恢复演练未验证 |
| U2 | 真实 Module 升级；依赖已固定 v1/v2 archive、独立 author DB、U1 guard、应用 B 构建/冻结及运行库 A 的配对备份/停写 | Module 作为源码 contribution 先在应用仓采用；private routes 由应用 owner 在 app-owned 装配中显式接入原认证/Module/权限 middleware；验证应用 A `0.1/package v1` → 应用 B `0.2/package v2`，再随 B 的完整 Release 部署；全新空库若已含锁定 private 源码，由授权 owner 从当前 lock 执行 `plugin:install <private-key>` 初始化，既有安装使用 release-locked reconcile；与 U1 共用一次真实升级序列，不新增动态 loader、后台上传、Marketplace、热更新或通用生产 worker | `release_identity` Sol/high 执行联合动态组；Terra/medium 只读核对；根代理二审 | author DB 采用 v2 并完成 build/freeze；运行库 A 完整切换、migration、restart 后合格非 root HTTP 呈现 v2、未认证请求拒绝且旧业务数据保留；记录应用/Package 双重身份 | Development/Standalone A `0.1.4` → B `0.2.0`、Package `1.0.0` → `2.0.0` 通过；前置 A `0.1.1`–`0.1.3` 是失败准备样本 |
| U3 | 生命周期文档审计与本批 Git 交付；依赖 U0 结论和 U1/U2 的真实状态 | 只改本页、生命周期/创建/升级说明、公开投影及必要登记/generated；修正日常 Git、full profile、四身份、一次生成和部署顺序；一次 docs governance 与受影响 docs-site build | 本任务 Sol/high 文档 owner；根代理终审 | 文档不把静态/局部证据写成运行完成，不承诺未实现生产能力；检查、diff、commit/tree 和 clean worktree 可复核 | 文档治理、docs-site 构建、差异检查与根终审已完成；随本批交付 |
| U4 | 下一次正式统一基础发行号；依赖 Core PHP/Web 同号包的正式发布资格、Registry 权限、应用消费检查及 U1 未完成负例 | 不新增别名；先发布并验证同号 PHP/Web Core 包，再由应用精确锁定并验证消费，最后完成同号 scaffold 资格与发布；预发布后缀同步，任一步失败时整套不标记 ready；内容未变的 Core 也发布新身份；正式部署资格前用合规 target 动态验证同版本不同内容拒绝，并运行既有 `ModuleBundleLifecycleTest` 受影响断言 | Core 与 Application release owner 各用 Sol/high 实施；Terra/medium 只读核对身份；根代理冻结正式发布范围与审计 | scaffold manifest、两个 Registry 包及应用两份 lock 使用同一目标发行号且各自不可变身份可追溯；兼容检查通过；客户 `instance_version` 和 Module 版本不被宿主发布自动改写；同版本不同内容动态拒绝与既有静态断言均有候选证据 | 部分完成：Core PHP/Web 3.1.0 已通过固定资格并发布，Application 的 Composer/pnpm lock 已采用真实 3.1.0 包，V2 身份及同源双 Edition 生成输入已集成。Application 最终双 Edition 安装、真实旧实例升级/恢复资格及正式 Release 尚未完成；依据为能力账本及问题主登记 consumer_delivery.cr02/cr03，不重复发布已完成的 Core 包 |
| U5 | Multi-tenant private Package 开发采用入口及 Standalone private TenantModule 开通入口；依赖签名 archive、应用仓源码/lock 所有权及现有 Package 校验合同 | 设计从受信 archive 到应用仓源码与 `plugins.lock` 的显式采用步骤，保留签名、SHA、成员集、路由装配与依赖检查；为授权 Standalone 部署 owner 提供当前 `ProductTenantModuleProfileService::applyInstallationSelection()` 的受控用户入口，不创建 Platform 身份；不放宽 Runtime mutation Edition 门禁，不用 `plugin:install` 冒充采用，不引入在线 loader | 后续 Application Sol/high 实现；Terra/medium 只读核对两 Edition；根代理冻结写集与审计 | 同一签名 private Package 在隔离 Multi-tenant 派生应用中进入源码/lock、完成构建和 Tenant A/B 授权隔离检查，Runtime 不接收 archive；Standalone owner 不登录 Platform 即可显式开通已锁定 private TenantModule，并保留成员 RBAC 独立 Gate | 缺口已确认并列入后续队列；本批真实 A→B 证据仅由受控部署步骤直接调用服务完成 |

## U1/U2 Development 验证证据（2026-09-07）

- 实现源码固定为 `a1354f66e14064f3567e4f592e4ba9b1cb7caaa6` / tree
  `30941c2b8a505df9fccb1681062ef1aa05ce4d00`。A `0.1.4` 为
  `74c3032a88375491be9df26f974ea224fcbdf47d` / tree
  `12b2e36b262e9632275e06a4487231ef48498556` / source archive
  `24030a36663f3f2f871aa4f2d602428bbd1eca214f3bb272151b67049405360a`；B `0.2.0` 为
  `bc0f099ae8937c1f24757fdc5025449fce80e9e3` / tree
  `340c1afd1a190106f48023924c49a878b2a7ff99` / source archive
  `a616ccc4f21c07ffc8af2b6b3c9cb1bd9e2648e5409d0e0d662b70566922d952`。两者 Composer lock
  摘要均为 `aca5b00f064591bf522adb49fb496d5ac181406ba07c85814634b79832508529`，scaffold
  `3.0.13` 和 Core PHP/Web `0.1.0-alpha.12` 未变。
- private Package 1.0 archive/artifact 分别为
  `2586786f2a3f6557ad2e57c96754002279c2e243b539ff9d05e2344bc9179a7e` /
  `9bed1d1ca13d1c0a209101ab3e0899552d24c472a82e4cc9e63865acb753a246`；Package 2.0 为
  `09c382a24747876f2c890ed259a02c527ee9063705a1278a29d1517d630ed314` /
  `fe68f2743a6b8079f22bfaa0de7f4cab4adc0593e7f261752b2e398d48a2ff71`，B `plugins.lock`
  摘要为 `32a032f0b7fd0e62a208f59c2f8e2a7740d6aaa90bc4c8a44f296c91b77a5142`。
  v1 表保存 `proof-business-001` 与原 note；v2 仅追加 `detail VARCHAR(255) NOT NULL DEFAULT ''`，
  migration `proof.upgrade-note:20260907010202_add_upgrade_note_detail` 以 checksum
  `0b3be8d1355c44e92e265e459ea91dd58a0b907f31b3368017732c844d45ed74` 记为 applied，旧行原值保留。
- 实际序列是 full/Standalone 创建与开发采用、各样本独立锁定依赖和前端 build、固定 A/B、从目标
  B 执行 `plugin:release-composition --current-root=<A-root>`、停写与配对备份、完整构建物切换、
  `server/database/install.php --migrate --target-version=3.0.13`、
  `plugin:reconcile --release-locked`、重启和 HTTP 核验。private route 由 app-owned
  `server/route/proof_upgrade_note.php` 引用 Package 自身 `Http/routes.php`，再进入应用 route 清单。
- composition 正例为 ready（9 个 official 保留、private 1.0→2.0）；缺失与降级分别拒绝
  `PLUGIN_RELEASE_PACKAGE_REMOVED` / `PLUGIN_RELEASE_PACKAGE_DOWNGRADE`，正常 maintenance 禁用为
  `preserve-disabled`，恢复 opening 后继续可用。同版本不同内容的动态目标没有形成合规 manifest/lock，
  guard 未产生 allow 结果；`ModuleBundleLifecycleTest` 本批未运行，二者均不记为动态通过。
- 配对备份的数据库 gzip 为
  `b0070a82a4fb9de4676164999b039880f63cc56ad7978ceeb1ee5587685972dc`，storage tar 为
  `b145b6eda0b13c9fe4d6e547bae4f327831c139c0773046b7dfd35caf95eac3a`，格式与摘要通过校验。
  B 的 health/release、同成员 private、root `role/add` 与 `admin/add` 均为 200；未认证为 401，
  未授权为 403。业务行、Tenant opening/revision、RBAC 和主文件对象保持；同一 file key 的新签名
  下载摘要仍为 `132fb1421e16917ed23bc93985c74110665cce65a0a2564c23383f819b1f9200`。
- 前端静态产物审计确认 A 的 `plugins.lock` / contribution 对应
  `assets/index.0c7043ef.js`（SHA
  `196910abb7f2fc1d70cb975812a46fcfd352f5a8211767761bcce90aa774a824`，Basic），B 对应
  `assets/index.d43af571.js`（SHA
  `18b2bf953ea0c833d287594e9cc860149bf8070b837e621dd44483756c8395be`，Detailed）；这是只读
  源码/lock/chunk 一致性审计，没有运行浏览器交互。
- A `0.1.1`–`0.1.3` 曾依次暴露 route 装配、授权注册和 Standalone Storage owner 投影缺口，均为
  失败准备样本；A `0.1.4` 才是升级基线。当前授权实现另以登记的隔离 Development DB 运行既有
  `AdminRbacTenantIsolationTest`，结果 `NATIVE-ADMIN-RBAC-TENANT-ISOLATION-001 passed`，安全日志
  SHA 为 `7131f2c7057e4f174715bd6906c25e83fd2fbf64d9bd6db3255993485ea11b20`。
- 使用登记资源 `peanut-admin-generated-application-upgrade-build-development`、author/runtime MySQL
  Development 资源和 `127.0.0.1:20283` PHP 入口。author/runtime 两库及 schema ACL、20283 服务、
  build/secret/archive/evidence 目录和 `appupgrade-dev02` 租约均已清理或释放。以上未覆盖 Platform
  worker 全状态机、Nginx、正式发布、生产资格、浏览器交互或真实恢复演练；配对备份只证明当次
  恢复坐标的格式与摘要，临时文件已清理，不是当前可用恢复入口。

## 已完成的版本合同切片（不重复执行）

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
