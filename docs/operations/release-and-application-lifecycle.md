# 发布、脚手架与独立应用生命周期

Peanut Admin 提供可复用后台底座，独立应用拥有自己的业务源码、数据和发布节奏。一次升级会涉及
四种身份；它们可以有关联，但不能互相代替：

| 身份 | 事实源 | 决定什么 |
| --- | --- | --- |
| 应用版本 | `release-versions.json.product_release`、应用 tag、完整应用 Release | 业务应用本次发布和实例要部署的完整代码 |
| Scaffold 来源 | `scaffold_template`、签名升级包及不可变 manifest | 应用已采用的受管文件基线、渲染快照与 Peanut migration 目标 |
| Core 依赖 | Composer/npm manifest 与 lock 中的精确包身份 | 应用实际安装的 PHP/Web 公共底座版本 |
| Module 版本 | Module manifest、不可变 archive SHA-256 与签名、安装账本 | 独立业务模块的内容、依赖、migration 与生命周期 |

表中 `product_release` 是已发布 v1 合同对客户实例版本的旧字段名。现行[版本身份 ADR](../architecture/product-version-identity-adr.md)
明确 Peanut 产品 Application、Core PHP/Web、scaffold 与双 Edition 共用产品版本；客户 Instance
使用独立 `instance_version` 并另记 `source_product_version`。Module 也独立编号。
Core 即使没有运行时改动也必须发布同号的新不可变包身份并完成资格，不能只修改号码或重新标记旧包。

当前正式应用/scaffold 是 `3.0.14`，并采用已经独立资格、发布且完成消费验证的 Core
`0.1.0-alpha.13`。发行列车必须先验证 Core 公共身份可消费，再更新应用 lock、生成同号 scaffold，
最后在应用固定候选完成资格与发布。任一步失败时只阻塞依赖它的下游状态，不创建不对应真实包版本的
别名。此前本页“撤销 Core/scaffold 同号”的决定已由新 ADR supersede；历史不同号事实保留，不能据此继续发布不同号的新产品。

Standalone 与 Multi-tenant 安装包来自同一个 Peanut Admin Release，是两种确定性 Edition 构建物，
不是两套人工源码。对应的签名升级包只提供同 Edition 的 scaffold 采用输入，也不是完整应用
Release。`create-app` 生成的用户应用则是独立仓库，不会自动跟随 Peanut Admin 的 `dev/main`。

## Peanut Admin 团队的发布顺序

```text
功能分支
  → 完成聚焦检查并直接合入 dev（日常开发不建 PR）
  → dev → main 走稳定分支评审
  → 从最新 origin/main 固定 candidate 并运行适用资格
  → 对同一 main commit 创建 annotated vX.Y.Z tag
  → 从同一 commit 生成两个安装包；有合格旧基线时再生成两个签名升级包
  → scripts/publish-github-release 一次发布源码和 Edition 附件
```

正式发布或 `dev → main` 使用 PR；日常功能分支进入 `dev` 不使用 PR。当前仓库没有“push tag 后
自动发布”的 GitHub Actions，发布工具只能消费已经通过资格且身份一致的 annotated tag。Core 的
tag/Registry 发布仍是独立执行步骤，但下一正式发行列车须与 scaffold 使用同一版本号；应用只有
更新自身 Composer/npm lock 并通过消费检查后，才采用这些 Core 包。

## 独立应用只生成一次

需要自定义名称、slug、package identity 或继续开发业务代码时，从固定 Peanut Admin Release
checkout 执行一次 `create-app`：

```bash
php scripts/create-app \
  --name="Acme Console" \
  --slug=acme-console \
  --package=acme/acme-console \
  --target=/absolute/path/to/acme-console \
  --edition=standalone \
  --profile=full
```

当前正式清单采用 `full` profile；`--edition` 必须选择 `standalone` 或 `multi-tenant`，生成后不能
当作运行开关切换。目标中的 `.peanut/application-manifest.json` 固定 scaffold 来源、受管基线、
生成参数和 managed/app-owned 摘要。应用 owner 随后初始化自己的 Git 仓库并登记自己的数据库、
端口、域名、外部服务和凭据引用。

`create-app` 是首次创建入口。已经存在的应用禁止再次运行生成器覆盖目录；重新生成会绕开三方比较，
无法证明业务代码、app-owned Schema 和秘密得到保留。普通用户首次安装可直接使用对应 Edition 的
完整安装包，也不需要先执行 `create-app`。

## 已有应用在开发分支采用公共更新

已有应用的后续工作始终从应用自己的开发分支开始：

1. 下载并验证与当前 Edition 相同的签名 scaffold 升级包，用包内
   `scaffold-upgrade preflight/apply/verify` 更新受管源码；发生冲突时解决或 `recover`，不能重生成。
2. 按兼容信息更新应用自己的 Composer/npm manifest 与 lock，并用锁定安装验证 Core 依赖。
3. Module 如需升级，先在应用仓采用它自己的签名 archive、固定版本与依赖，再安装依赖、构建并
   验收。private Module 的 `Http/routes.php` 不会由安装命令自动注册；应用 owner 在 app-owned 路由
   装配中显式引入它，并沿用认证、Module 与权限 middleware。卸载时由同一 owner 去除接线，不复制
   业务 handler，也不增加在线动态路由 loader。Module 版本不随 `product_release`、
   `scaffold_template` 或 Core lock 自动变化。
4. 在隔离开发/候选环境运行应用自己的数据库 migration 和业务检查。Peanut migration 以
   `scaffold_template` 为目标，应用 migration 和 Module migration 仍由各自账本负责。
5. 确认完整源码、所有已安装 Package 身份和依赖后递增 `product_release`，形成应用自己的不可变
   commit/tree、tag、`RELEASE_METADATA.json` 和完整应用 Release。

现有 `module:install-package` / `module:update-package` 的签名 archive 入口只允许 development、debug
且 Standalone；Multi-tenant 派生应用尚无从 archive 到应用仓与 lock 的受支持采用入口。不能在
Multi-tenant 中绕过门禁手工运行该命令，也不能把 `plugin:install` 当成替代，因为后者只消费已经
进入应用源码和 `plugins.lock` 的 Package。这个工具缺口不改变“先在应用仓采用并验收，再随完整
应用 Release 部署”的边界。

Module 包是可独立开发、版本化和分发的源码 contribution，不是生产实例单独部署单位。当前 Module
安装/更新命令不执行 Composer/npm 安装、前端构建或服务重启；应用必须先采用并验收，再让 Module
随完整应用 Release 部署。应用 Release 必须包含所有已安装 Package 的确切身份；缺失、版本回退或
同版本换内容必须在部署前阻断。Package 身份不变且其 Module installation 均处于正常 maintenance
禁用态时保留 Package 并跳过 reconcile；failed、retire/purge 进行中或未知过渡状态阻断。需要移除
Package 时先走停用/retire 流程，不能靠完整代码替换删除。

Package 安装/版本更新与 TenantModule 开通/停用是两件事。前者改变应用 Release 中已采用的源码、
lock、migration 和 catalog；后者只让某个 Tenant 使用或停止使用已经随应用部署的 Module，不需要
重新下载前端。Standalone 首次安装选择和 `tenant-module:apply-profile standalone` 只处理应用固定
的 official 集；本次 private Module 由授权部署 owner 在受控应用安装/部署步骤中调用
`ProductTenantModuleProfileService::applyInstallationSelection()`，为 default Tenant 显式开通已经锁定
的 Module，再由 Tenant 管理员分配角色权限。Standalone 安装不创建 Platform 初始身份，当前也没有
private 选择的完整用户 CLI/UI 包装；源仓存在的 Platform API 不能当成 fresh Standalone 登录入口。
Multi-tenant 则由 PlatformOperator 对每个目标 Tenant 开通/停用，成员授权仍由各 Tenant 管理员负责。

现有 Platform readiness 已检查 active `pa_module_installation` 在目标 lock 中缺失或降级，必须继续
保留。本批在完整部署入口增加了 Package composition guard：目标 Release 自身在清理代码根或执行
数据库动作前，对照当前根、目标 lock 和 `pa_plugin_installation`，检查 artifact 身份、正常禁用及
异常状态的 private Package；部署后的 release-locked reconcile 再执行既有 Package 的 migration 与
catalog 对齐。它补齐直接 `deploy-release` 的组合边界，不替代 Platform 既有检查，也不扩成新的远程
部署平台。

当前 Web 构建从 `plugins.lock` 生成指向本仓 `web/src` 的静态 import，路由消费同一次构建的
chunk，因此 Module 前端不支持运行时在线加载。Vite 的
[`import.meta.glob`](https://vite.dev/guide/features#glob-import) 仍要求构建时可发现源码；
[Module Federation Vite](https://module-federation.io/integrations/build-tool/vite) 可以加载 remote，
但还要建立共享 Vue/router/Pinia、CSS 生命周期、签名
[remote manifest](https://module-federation.io/configure/manifest.html)、CDN、宿主 API、组合身份和回滚
合同。按当前独立应用整体发布的目标，暂不引入线上独立安装，默认继续在开发期采用 Module 并
整体部署应用；若出现第三方 Module 跨多个在线产品独立高频交付需求，再单独评估动态前端。届时
必须由应用 build ID 固定
映射 Module version、artifact SHA、入口和 backend API contract，不能解析 `latest`；Module
Federation manifest 本身也不提供签名信任，仍需独立验证来源与内容。

签名 scaffold 包的作用域到第 1 步为止：它是开发源码采用输入。实例部署的输入是第 5 步形成的
完整应用 Release。生产 Runtime 不执行 scaffold 三方合并，也不能把 Peanut Admin 的完整安装包或
上游源码树直接覆盖独立应用。

## 运行中实例只部署已存在的应用 Release

部署动作开始前，应用 tag、commit/tree、版本 metadata、依赖 lock、Package 集合和部署制品必须
已经固定。禁止先修改运行中代码、执行 migration 或重启服务，再补 tag/Release；这种顺序没有
可回滚的完整来源身份，也会让数据库变更领先于应用发布。

实例部署器应先验证应用 Release 身份及登记资源，在隔离 staging 中完成锁定依赖安装和前端构建，
再按应用自己的部署合同执行备份、维护模式、完整构建物切换、以已采用 scaffold 为目标的 Peanut
migration、应用/Module migration、重启和 smoke。失败时按同一应用 Release 的恢复合同处理。
全新空库的完整 Release 已含锁定 private Package 源码时，授权部署 owner 可在标准安装后从当前
`plugins.lock` 执行 `php server/think plugin:install <private-key>` 初始化该 Package 的表与 catalog；
该命令不接收或解压 archive，也没有通用生产 worker 包装。已安装 private Package 随后使用
`plugin:reconcile --release-locked` 对齐 Release 锁定状态。默认不在线接收 tar，并不等于部署阶段
无需执行 Module 初始化或 migration。
Peanut Admin 当前登记的生产 worker 只
服务本仓固定资源，不代表生成的独立应用已经自动获得通用远程部署能力；独立应用 owner 必须提供
自己的资源登记、受限执行器和资格证据。

## Scaffold 采用命令

正式 Release 提供对应升级附件时，应用 owner 从正式入口取得升级包和受信公钥，在开发分支执行：

```bash
export PEANUT_UPGRADE_TRUSTED_KEYS_JSON='{"<official-key-id>":"<base64-ed25519-public-key>"}'
php /path/to/extracted-upgrade/upgrader/scripts/scaffold-upgrade preflight \
  --project-root=/path/to/app \
  --package=/path/to/extracted-upgrade \
  --signature-key-id=<official-key-id>
php /path/to/extracted-upgrade/upgrader/scripts/scaffold-upgrade apply \
  --project-root=/path/to/app --plan=/path/to/app/.peanut/upgrades/plans/<candidate>.json
php /path/to/extracted-upgrade/upgrader/scripts/scaffold-upgrade verify \
  --project-root=/path/to/app --plan=/path/to/app/.peanut/upgrades/plans/<candidate>.json
```

升级器只处理 `managed` / `generated-managed` 文件并保存恢复材料；它不安装依赖、不执行数据库或
Module migration，也不重启服务。显式 `--from-manifest/--to-manifest` 只供维护者诊断，不是普通
用户的发布输入。

## 当前证据边界

本批在隔离 Development/Standalone 环境完成了一次真实完整应用切换：应用 A `0.1.4`、private
Package `1.0.0` 经配对数据库与 storage 备份，升级为应用 B `0.2.0`、Package `2.0.0`。目标
composition 检查通过，scaffold `3.0.13` 与 Core PHP/Web `0.1.0-alpha.12` 锁保持不变；部署后
release-locked reconcile 执行了追加 `detail` 列的 Module migration。HTTP 证明同一成员从
`basic-note` 读到 `detailed-note`，未认证请求为 401、无权限请求为 403；旧业务行、Tenant opening
及 revision、RBAC、主文件对象和签名下载内容摘要均保持一致，新增角色与管理员入口也返回 200。
前端只读产物审计确认 A/B 的 `plugins.lock`、源码入口和静态 chunk 分别固定 Package 1.0/2.0 文案；
这不是浏览器交互验证。

动态负例已覆盖目标缺少 private Package、版本回退和正常 maintenance 禁用保留。同版本不同内容的
动态目标准备未形成合规 manifest/lock，guard 没有产生 allow 结果；本批也没有运行包含相关静态断言
的 `ModuleBundleLifecycleTest`，因此不把该分支记为动态通过。A `0.1.1`、`0.1.2`、`0.1.3` 分别暴露
private route 未装配、授权注册遗漏和 Standalone 存储 owner 投影错误，只是失败准备样本；修正后固定
的 A `0.1.4` 才是成功基线。

以上是 Development 证据，不是 Platform 完整状态机、Nginx 或生产资格，也未执行真实恢复演练；
配对备份只证明当次切换前形成了经格式和摘要校验的恢复坐标，临时备份已随验证资源清理，不能当作
当前恢复入口。现有 ops-module 低层入口和 worker 仍只覆盖其明确的
源码/数据库职责，不能代替完整应用部署。生产后台上传、Marketplace 与通用独立应用生产 worker 仍
未闭合；本批整体部署也不应表述为生产热更新。bundled `rich-text` 1.0.0 archive 尚未签发。

3.0 是 scaffold fresh-only 大版本：旧 scaffold 大版本数据库不能直接交给当前迁移器。3.x 内的
patch/minor 使用 append-only migration；应用自身版本可以独立递增，不会触发 scaffold major
停止线。
