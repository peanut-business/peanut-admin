# 创建独立应用

正式入口使用仓库自带的 PHP CLI；它只读取固定 inventory 并写入目标目录，不连接数据库、
服务、端口或容器。运行需要 PHP 8.3 与 Git，且源 checkout 必须位于一个干净提交上：

```bash
php scripts/create-app \
  --name="Acme Console" \
  --slug=acme-console \
  --package=acme/acme-console \
  --target=/absolute/path/to/acme-console \
  --edition=standalone \
  --profile=full
```

`--edition` 必须明确选择 `standalone` 或 `multi-tenant`。这不是运行时开关：生成器会从同一份
Peanut Admin Release 投影出所选 Edition 的前端构建输入、Schema、索引、Tenant/Platform 能力和
升级身份。生成后的应用只有一个 Edition；另一个 Edition 的安装包或升级包不能覆盖它。当前正式
当前正式发布清单采用 full 配置，示例显式传入 `--profile=full`；省略 `--profile` 时仍采用
`standard`，需要完整应用能力时必须显式选择 `--profile=full`。

四种版本身份各自拥有事实源：

| 版本轴 | 事实源 | 用途 |
| --- | --- | --- |
| 应用发布 | `release-versions.json.product_release` | 应用 owner 的版本、tag 与完整应用 Release |
| Scaffold 采用 | `scaffold_template` 与不可变 scaffold manifest | 受管技术基线、渲染快照与 Peanut migration 目标 |
| Core 依赖 | Composer/npm 精确包版本与 lock | 应用实际安装的后端/前端 Core 身份 |
| Module | Module manifest、archive SHA-256/签名与安装账本 | 可独立版本化的业务源码 contribution 及其依赖和 migration |

这四种身份不会因为其中一项变化而自动同步。下一次正式发布起，scaffold 与 PHP/Web Core 使用
同一个基础发行号（包括同步的预发布后缀），但相同号码不替代各自的 manifest、lock、不可变包
引用或兼容证据；应用版本继续独立，共同号码也不能把 alpha 自动说成稳定版。当前 `3.0.13`
scaffold 与 `0.1.0-alpha.12` Core 是历史真实身份，不能回写成已对齐。Module 可独立开发和分发，
继续使用自己的版本与 archive SHA-256，并由应用仓采用、固定依赖、构建和验收，最后随应用自己的
完整 Release 部署；Module archive 不是生产实例部署单位。

`--application-version=<semver>` 可选，默认 `0.1.0`。该值不是 Peanut Admin 产品版本，也不是
`peanut-admin/core` 或 `@peanut-admin/admin` 的依赖版本。生成器以这一值统一写入 release
metadata、changelog、SBOM 根包、后端版本配置与 API fallback、Web/PC/UniApp/Docs 根
package、PC/UniApp 根 lock metadata，以及 UniApp manifest/About；默认 UniApp
`versionName=0.1.0`、`versionCode=10`。

目标必须是不存在或为空的绝对目录。slug 只接受小写 kebab-case；Composer 风格的 package
identity 必须包含 vendor/name。路径穿越、符号链接目标、非空目标、inventory 漂移、未知
变量与未知 transform 均 fail-closed。入口按 inventory 的 `template_version` 选择同版本的
`scaffold/releases/v<version>/scaffold-manifest.json`；release 缺失或版本不符时不会创建目标。

`scaffold/application-template-inventory.json` 对每个可生成的模板源文件给出唯一分类；
`scaffold/**` 中的 source-only inventory、release artifact 与历史证据本身不递归进入应用：

- `managed`：框架与交付基础设施。创建时把逐文件基线复制到
  `.peanut/scaffold-baseline/<template>/files/`，用于核对模板来源；2.0 不提供 1.x 原地升级。
- `generated-managed`：由创建参数生成或同步的身份/元数据文件；本地修改后也不得被静默覆盖。
- `app-owned`：应用业务、数据库、页面、文档与稳定 Host/override 入口。future scaffold 默认保留。
- `excluded`：Peanut Admin 的历史证据、发布记录、内部设计/治理、缓存/构建/依赖、凭据和本机基础设施。

正式应用会生成合法的空 `plugins.lock`，并默认不启用 Plugin lock。源仓中的
`fixture.delivery-record` manifest、PHP/Web Module 与 lifecycle runner 仅是合同测试证据，
全部保持 `excluded`；应用 owner 安装真实 Plugin 时才写入可解析的不可变身份并显式配置 lock。

生成器在目标目录提交前，会验证 adopted release 的 token、artifact 自摘要、managed 路径
双向全集、逐文件 mode/classification、参数渲染字节和 release managed tree。只有完整等价
才会写入应用：`template` 固定已采用的不可变 release commit/tree/inventory，顶层
`generation_source` 另行记录实际干净生成候选的 commit/tree 与当前 inventory SHA。这样
source-only 或 app-owned 演进无需伪造新的模板身份，而任何 managed 字节变化仍必须先形成
新的不可变 scaffold release。

当前 inventory 采用 `release-versions.json` 声明的 fresh-only scaffold release；具体版本和
source commit/tree 以生成结果的 `.peanut/application-manifest.json` 为准。该 identity 属于
scaffold 命名空间，不是用户自己的应用版本。生成物使用原生 Account/TenantMember/RBAC、空库
安装入口和所选 Edition 的确定性投影，不携带 legacy 映射、bootstrap 或兼容镜像。

`create-app` 只运行一次以建立应用仓和基线。已有应用后续必须在自己的开发分支使用同 Edition
签名 scaffold 包做三方比较，并单独更新 Core/Module 依赖；禁止重新生成到现有目录来覆盖业务
代码、app-owned Schema 或秘密。

生产管理端 builder 在执行 Vite 前，把应用根目录的 `plugins.lock` 精确复制为
`/build/plugins.lock`；Plugin contribution resolver 直接读取这份 lock，缺失或无效内容继续
fail-closed。安装包/生成器已在构建前固定 Edition，不再在一个正式构建物中同时携带两套管理端
并依赖运行时切换。PHP 镜像携带应用自己的 `resources/project-resources.json`，并把受管
`server/database/seed-demo-data.php` 暴露为 `peanut-seed-demo-data`，供数据库环境门禁与显式
演示初始化使用。根 `scripts/seed-demo-data` 仍是 app-owned 命令入口；登记缺失或与显式部署
目标不一致时仍 fail-closed。3.0.0 不提供 1.x scaffold preflight/apply/verify 或数据库
`--adopt-existing` 路径；需要保留旧应用时继续运行旧版本实例并为新应用准备独立空库。

生成的 `.peanut/application-manifest.json` v2 还固定 `application.version`、参数、每个生成文件的 SHA-256、mode、分类、
owner、managed/app-owned 树摘要，以及 managed baseline 路径和独立 `baseline_sha256`。应用当前
文件摘要与上游比较基线分开记录后，后续升级可以保留用户对 managed 文件的单边定制，同时仍
识别上游是否变化。其中 manifest 的 `application.version` 是最近一次 scaffold 采用/渲染快照；应用
建立自己的后续 Release 时，以 `release-versions.json.product_release` 记录当前应用版本。升级器
用前者重现旧 baseline、用后者渲染目标受管文件，并把两组参数与版本合同摘要冻结进 plan，避免
scaffold 升级把应用从当前版本退回初始版本；成功后再把 manifest 快照推进到本次目标渲染版本，
同时保留原 `generation_source`。升级器只处理受管文件，不执行数据库迁移。

## 后续升级边界

从正式 Release 创建派生应用不代表它会自动跟随 Peanut Admin 的 `dev/main`。应用只按同
Edition 的正式升级包和自己的发布流程采用后续版本：

| 变化类型 | 当前处理方式 | 所有权与停止线 |
| --- | --- | --- |
| `peanut-admin/core`、`@peanut-admin/admin` 依赖 | 应用在独立分支人工更新版本和 lock，并运行自己的兼容测试 | 包管理器只能修改 Peanut 依赖；失败时回退应用分支 |
| `managed` / `generated-managed` 脚手架文件 | 人工比较新 Release 与应用 manifest/baseline，选择性采用 | 应用改过且目标也变化时停止，不静默覆盖 |
| `app-owned` 业务代码、页面和配置 | 应用自行维护 | 脚手架升级永远不自动改写 |
| Module package | Standalone 可用 development/debug archive 命令在应用仓采用并固定依赖；app-owned 路由装配显式引入 private Module 自有 routes，完成安装、构建和验收后随完整应用 Release 部署 | Multi-tenant 尚无受支持 archive 采用命令；安装不自动注册 private 路由；缺失、回退或同版本换内容阻断；身份不变且 Module 均为正常 maintenance 禁用态时保留，failed、retire/purge 进行中或未知状态阻断；显式移除先停用/retire 并由 owner 去除路由接线 |
| 应用数据库 | 首次安装必须为空；安装器先建立 Core/Application 基线，再自动执行当前 scaffold 所需的 Peanut 追加 migration，以及当前源码中没有 `peanut-release` 标记的应用自有 migration | 不复制 Peanut 新安装基线覆盖已有数据库；应用自有 SQL 不由脚手架版本筛掉 |
| Peanut canonical migration | 同一 scaffold 大版本通过 `install.php --migrate --target-version=<scaffold-template>` 执行并写入 `pa_schema_migration`；目标来自已采用的 `release-versions.json.scaffold_template` | 绑定 scaffold 身份和 migration checksum；scaffold 跨大版本必须 fresh/rebuild，应用自身的 `product_release` 不触发这条停止线 |

升级器提供 `preflight -> apply -> verify -> recover`。首个正确双 Edition Release 只建立安装
基线，因为此前没有合格的旧 Edition 可作为升级来源；下一补丁版本才会以该基线为最老受支持
版本，分别发布 Standalone 与 Multi-tenant 签名升级包。派生应用解压与自身 Edition 相同的包后，
使用包内升级器的 `--package` 入口生成计划，不再自行拼接新旧 manifest。完整部署仍由应用 owner 安装锁定依赖、
执行数据库迁移、构建、重启和 smoke；scaffold 跨大版本必须按发布策略 `--fresh`。
历史 `v2.0.0 -> v2.0.1` 已有真实派生
应用资格，`v2.1.0` 沿用相同所有权和恢复合同。它只管理已登记的框架文件，不会把业务代码变成脚手架所有，也不会替
应用决定业务数据迁移。升级数据库和 Peanut 依赖时，必须同时参考对应 Release 的发布计划。

签名升级包只是开发源码采用输入。应用完成 scaffold、Core、Module 与业务变更后，必须先固定
自己的 `product_release`、commit/tree、tag、依赖 lock 和完整应用 Release；运行中实例只消费这
个完整 Release，不能先修改代码或数据库再补 tag，也不能在生产 Runtime 直接执行 scaffold 合并。

生成结果没有 `.git`，可直接执行 `git init` 成为独立仓库。连接任何资源前，应用 owner
必须先补全 `resources/project-resources.json`；初始管理员密码仍只允许在空库安装时通过
`ADMIN_INITIAL_PASSWORD` 显式提供。

## 检查 Core 依赖身份

生成应用可直接从自己的 manifest 与 lock 检查实际锁定的 PHP/Web Core 身份，不需要在相邻
目录保留 Peanut Admin Core 源码，也不要求先安装 `vendor` 或 `node_modules`：

```bash
scripts/scaffold-doctor \
  --resource-id=<应用资源登记中的稳定 ID> \
  --path=/absolute/path/to/application
```

默认输出 `scope=php,web mode=released-package`，随后分别报告 `peanut-admin/core` 的 Composer
constraint、锁定版本、source URL/reference，以及 `@peanut-admin/admin` 的直接依赖 specifier、
pnpm 锁定版本和 integrity。doctor 的职责只到分别验证两个生态的锁身份，不据版本字符串推断组合
兼容。当前历史消费组合允许 PHP/Web Core 与已采用 scaffold 使用不同版本；从下一正式发行列车
起，发布资格另行要求三者使用同一基础发行号。该联动规则不由 doctor 静默补齐，跨版本或同号组合
仍须由发布兼容信息和应用既有检查确认。当前支持面要求两个 Core manifest 都直接声明精确版本，不解析 SemVer
range；doctor 核对每个 manifest 与自己的 lock 是否形成唯一、完整且一致的不可变依赖身份，并
拒绝可变 PHP source reference、path dist 与带认证信息的 source URL。它不安装依赖、不访问网络、
不连接登记资源，也不读取凭据。该结果证明的是锁文件身份，不是依赖已经成功安装、应用能够运行
或候选具备发布资格。

只有联合调试本地 Core 源码时，才显式选择源码 checkout：

```bash
scripts/scaffold-doctor \
  --resource-id=<应用资源登记中的稳定 ID> \
  --path=/absolute/path/to/application \
  --core-dir=/absolute/path/to/peanut-admin-core
```

也可用 `PEANUT_ADMIN_CORE_DIR` 指定同一路径。此时输出
`scope=php,web mode=local-core-development`，并额外核对 checkout 的 PHP/Web package 版本和
现有本地依赖软链接是否都指向所选源码。该模式只服务本地联合开发，不代表 Registry 发布包或
生产运行已经验证。
