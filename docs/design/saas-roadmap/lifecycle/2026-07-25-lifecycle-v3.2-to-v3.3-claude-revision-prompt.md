# Claude 修订任务：将生命周期架构 v3.2 收敛为可实施的 v3.3

## 一、任务目标

请基于固定源码证据修订 Peanut Admin 应用生命周期架构，直接形成 v3.3 主设计文档，并生成下一轮交给 Codex 的审核提示词。

本轮不是重新讨论技术方向。PHP 和 Web 多包独立发布已经确定；请集中关闭 v3.2 中会阻止 U01 实施的合同缺口和事实错误。

必须先完整阅读：

1. `docs/plans/chatgpt-engineering-context.md`
2. `docs/plans/2026-07-25-lifecycle-architecture-v3.2.md`
3. `docs/plans/2026-07-25-lifecycle-v3.2-review-prompt.md`
4. 本提示词

源码事实必须固定在：

- commit：`69c5b2c271413f6ff741de65437f29e04f975300`
- tree：`96fa1ea5ff0db2bb3801869d071f4bedda98b6b2`
- 代码仓路径由项目根目录 `repos.yaml` 确定

不得使用当前工作区 HEAD 代替固定基线。先读取本提示词点名的源码，再修改设计文档。

---

## 二、不可重新讨论的既定方向

以下是用户已经确定的设计前提：

1. `packages/php/*` 中每个现有 Composer package 独立发布和安装。
2. `packages/web/*` 中每个 `@peanut-admin/*` package 独立发布和安装。
3. monorepo 是统一开发源码仓；发布流程为每个包产生独立可安装版本。
4. 后端使用 Composer，前端使用 npm-compatible registry + pnpm。
5. 不把所有能力合并成单个 PHP 或 Web 源码包。
6. 可以存在 convenience meta-package，但它只能依赖独立包，不能代替独立包。
7. GitHub Packages 不提供 Composer registry；不要再写这个不存在的能力。
8. 不做通用三方文本合并、PHP/TypeScript AST merge、自制包管理器、base64 recipe、代码阶段跨进程 ledger 或 v1 完全离线支持。

不要把篇幅用在重新比较这些方向。只解决如何可靠实施。

---

## 三、Codex 审核结论

v3.2 的总体方向正确，但结论为：**修订后可进入 U01 合同起草**。

必须关闭六组问题：

1. 修正 PHP split 工具和 Composer VCS 版本策略；
2. 补全 package module 的发现、autoload、编译、边界校验和菜单 client 绑定；
3. 修正 scaffold 文件分类，并定义历史 artifact 与逐文件渲染输入；
4. 定义 package manifest 更新、限定 lock 更新和无关依赖漂移阻断；
5. 用 deployment identity 绑定 plan、代码制品和 db-apply；
6. 补齐旧应用的 Module 连接层、本地 PHP package snapshots、本地 Web package snapshots 和 fork 共存规则。

以下各节给出固定基线证据和必须落入 v3.3 的修订要求。

---

## 四、修正 PHP 多包发布的实现描述

### 4.1 工具事实

`symplify/monorepo-builder` 当前仍维护：

- 当前版本系列支持 PHP `>=8.2`，因此兼容 PHP 8.3；
- 但它早已删除内置 `split` 命令；
- 当前官方 README 的 Package Splitting 部分要求使用独立的 monorepo split GitHub Action；
- 当前仓库实际为 `danharrin/monorepo-split-github-action`，审核时最新版为 `v2.4.5`。

因此，v3.2 中“使用 `symplify/monorepo-builder` 的 `split` 命令”以及相应 PHP 配置示例不能直接实施。

v3.3 必须：

- 保留已经确定的“monorepo 开发 + 每个 PHP package 独立发布”方向；
- 用真实可用的 GitHub Action matrix 描述 `packages/php/<local>` 到只读 repository 的映射；
- 固定 Action 的版本，不使用浮动 `main/master`；
- 说明推送多个只读仓所需的 GitHub App token 或细粒度 PAT、最小权限和 secret 边界；
- 说明 Packagist 首次登记与后续 webhook/update 流程；
- 明确只读 split repository 不接受人工提交或 PR，monorepo 是唯一源码事实源。

如果仍使用 `monorepo-builder`，只保留它真实承担的版本同步、依赖约束整理等能力，不得再声称它执行 split。

### 4.2 Composer `version` 字段

固定基线的 11 个 `packages/php/*/composer.json` 都含硬编码：

```json
"version": "0.1.0"
```

Composer/Packagist 对 VCS package 应从 Git tag 推导版本。硬编码 version 与未来 `0.2.0` tag 不一致时，Packagist 会忽略该 tag。

v3.3 必须明确：

- U01 删除所有待发布 PHP library manifest 的硬编码 `version`；
- 版本由 split repository 的不可变 tag 推导；
- monorepo tag、split tag、package metadata 和 release manifest 必须建立确定性映射；
- 已发布 tag 不允许重写；发布失败只能补齐同一未完成发布，或使用新版本重新发布。

---

## 五、补全 Package-Relative Module 契约

### 5.1 当前源码约束

固定基线中的真实行为：

- `starter/backend/src/Module/ModuleRegistryFactory.php` 从 `backend/config/modules.php` 读取 `roots`；
- 每个 root 以应用根目录为基准加载 `module.json`；
- `ModuleRegistryCompiler` 使用一个 `ModuleHostLayout` 校验所有 provider namespace；
- 当前 layout 是 `backend/src/Modules` + `ExampleHost\\App\\Modules` + `frontend/src/modules`；
- 当前 `module.json` provider 是应用 namespace，例如 `ExampleHost\\App\\Modules\\Peanut\\Settings\\ModuleProvider`；
- 当前 compiler 还要求 provider 必须位于该 layout 推导出的模块 namespace 下；
- `ManifestLoader` 已经把 menus、permissions 等相对路径按 module root 安全解析，这部分可以保留。

所以不能只把目录搬到 `vendor/` 并调用 `InstalledVersions::getInstallPath()`。

### 5.2 正确的包目录与 Composer autoload

v3.2 示例把 provider 放在包根 `Module/ModuleProvider.php`，但当前 Settings 的 PSR-4 只有：

```json
"PeanutAdmin\\Settings\\": "src/"
```

该类不会被自动加载。

v3.3 必须固定一种一致结构，推荐：

```text
packages/php/settings/
  composer.json
  src/
    Module/
      ModuleProvider.php
  resources/
    module/module.json
    module/menus.json
    module/permissions.json
  database/
    migrations/*.php
```

也可选择其他结构，但必须与现有 PSR-4、dist artifact 和相对路径完全一致。代码必须位于 autoload 可达路径；纯资源不必放在 `src/`。

### 5.3 受控发现机制

不得扫描整个 `vendor/`。v3.3 必须定义 Composer package 如何声明 Module descriptor，例如在各 package `composer.json` 中加入：

```json
{
  "extra": {
    "peanut-admin": {
      "module": "resources/module/module.json"
    }
  }
}
```

运行时从 Composer `InstalledVersions`/installed metadata 中只读取带该声明的已安装 package，再以安装根解析 descriptor。必须说明：

- package name、install path、descriptor path 的校验；
- descriptor 不存在、越界、重复 module key 或重复 package 声明时 fail closed；
- 只加载应用实际安装的包，不以 release 全包清单强制启用全部功能；
- application modules 通过应用配置显式列出，不与 package discovery 混为一个路径规则。

### 5.4 两类 Module 边界校验

当前单一 `ModuleHostLayout` 无法同时校验：

- package provider：`PeanutAdmin\\Settings\\Module\\ModuleProvider`
- application provider：`MyApp\\Modules\\CaseManagement\\ModuleProvider`

v3.3 必须把来源建模为 package module 与 application module，并分别校验：

- package module 的 provider namespace 必须匹配该 package 的受控 namespace；
- application module 继续匹配应用 module root/namespace；
- resources/migrations 必须位于各自 descriptor root 或明确的 package root 内；
- 两类来源共同进入依赖拓扑、权限、菜单、owned tables 和重复 key 检查；
- `ModuleBoundaryChecker` 不得继续假设所有模块都位于同一应用目录布局。

不要只写“修改 ModuleRegistryFactory”。必须列出 `ModuleRegistryCompiler`、`ModuleHostLayout`/其替代模型、`ModuleBoundaryChecker`、schema validator 和相关 Host 接线的职责变化。

### 5.5 前端 component inventory

PHP 运行时不能凭空从应用的 npm package manifest 自动读取前端组件。

v3.3 必须固定一个可实施来源，例如创建/升级时由 Web package metadata 和 release manifest生成应用侧 component inventory，再由后端读取该确定性产物。需要说明：

- 谁生成；
- 存放在哪里；
- 如何绑定 `pnpm-lock.yaml` 或已解析 package identity；
- 删除/新增 feature package 后如何更新；
- 后端编译 Module registry 时如何取得并校验。

---

## 六、解决包内菜单与应用 Client 的冲突

这是 v3.2 完全遗漏的实施阻塞。

固定基线的 `ProjectGenerator::adaptModuleMenus()` 会逐个修改 Peanut Module 的 `Resources/menus.json`，把 `client_keys` 改成应用请求中的 `admin_client_key`。例如模板中 Settings 默认是 `operations-web`，其他模块还出现 `admin-web`；创建不同应用时这些值会被重写。

模块资源移入只读 Composer package 后，不能再修改 `vendor/peanut-admin/*` 中的菜单文件。

v3.3 必须定义通用包声明与应用 Client 绑定的分层契约。推荐模型：

- package menu 不存应用具体 client key，而声明符号 audience/slot，例如 `tenant-admin`、`platform-admin`；
- 应用的 rendered 配置将 audience 映射到真实 client key；
- registry compiler 在加载 package catalog 后应用映射，再验证结果只引用 `registered_client_keys`；
- 应用可以隐藏 package menu，但不能静默改变权限、scope 或 module ownership；
- 同一 package 在两个应用中可以绑定不同 client key，而无需修改 vendor 文件。

必须给出 schema 示例、应用配置示例、缺少映射/重复映射/未知 client 时的 fail-closed 行为，并删除“创建器继续改写 package menus”的旧假设。

---

## 七、修正 Web Host Adapter 迁移描述

固定基线的 `starter/frontend/src/modules/peanut-*.ts`：

- 不包含固定 brand 或 client key；
- 通过 `baseUrl`、`fetch` 和权限回调接收宿主能力；
- 一部分实现 transport adapter；
- `peanut-ops-console.ts` 还在宿主层构造 Vue page/module contribution。

所以这些文件可以演进为 package-owned host adapter，但不是简单移动文件。

v3.3 必须说明 U01 对每个 Web 包执行：

- 将可复用 adapter 纳入包源码和 package exports；
- 补齐 package dependencies/peerDependencies；
- 保持 `baseUrl`、fetch、权限和 provider options 由应用注入；
- 将应用 `frontend/src/app/modules.ts` 改为从 `@peanut-admin/*` 导入；
- 区分通用 package adapter 与真正 application-owned wiring；
- 禁止包内硬编码应用 client、环境 URL 或权限决定。

---

## 八、修正 Starter 文件分类

v3.3 必须重新核对固定基线完整 `starter/` 文件树，并修正至少以下错误/遗漏。

### 8.1 明确需要修正的分类

- `frontend/src/App.vue`：是 `scaffold-rendered`，因为 `writeFrontendApp()` 写入 `brand`、`display_name`、`slug`；不能归为 static。
- `backend/route/app.php`：会经过 `replaceNamespaces()`，含应用 namespace，应为 rendered 或拆成 package-owned + application-owned 路由入口。
- `backend/app/provider.php`：会经过 namespace 替换，引用渲染后的 `StarterExceptionHandler`，不是 release-wide static。
- `backend/src/StarterExceptionHandler.php`：创建时会被替换 namespace；可以继续 seed-once，但必须记录其 initial render identity，不能说是未渲染静态文件。
- `frontend/index.html`：`writeFrontendIndex()` 写入 display name，归 rendered 正确。
- `backend/src/Auth/TenantAuthRuntimeFactory.php`：读取应用 `backend/config/auth.php`，不可原样声称全部移入 kernel；应拆分 package service/factory contract 与薄应用 adapter。
- `backend/src/FileMedia/FileMediaStorageFactory.php`：读取应用 storage config 并实例化本地 adapter，不可原样全部移入功能包。
- `backend/src/FileMedia/LocalPrivateStorageProvider.php`：v3.2 遗漏，必须明确是 package-provided adapter、seed-once adapter 还是 application-owned implementation。
- `frontend/package.json`：v3.2 表格只写“根 package.json”，必须单独定义前端 manifest 的混合所有权。
- `frontend/verification/*`：必须明确 classification；不要只用含糊的 `frontend/tests/*` 覆盖。

### 8.2 完整表格要求

v3.3 的权威分类表必须覆盖固定基线 `starter/` 的所有文件或可证明同策略的路径组，并包含：

- path/pattern；
- owner；
- creation source；
- render input fields；
- upgrade policy；
- old/new content source；
- conflict behavior；
- implementation phase。

不能再次遗漏 `LocalPrivateStorageProvider.php`、两个层级的 `package.json`、verification 文件或按 feature 生成/删除的测试。

---

## 九、定义 Scaffold Artifact 与渲染身份

`peanut-admin/scaffold` 和 `RELEASES.json` 在 Stage C.2 固定基线中都不存在。v3.3 必须明确它们是新产物，不得写成当前已有事实。

### 9.1 历史 Adoption 基线

只发布 `0.2.0` scaffold artifact 不足以升级 Stage C.2 应用。U01 必须同时从固定 commit/tree 生成一个不可变的 legacy baseline artifact，至少包含：

- Stage C.2 generator/source identity；
- 完整 legacy 文件 manifest；
- 旧 static/template 内容；
- `moves-to-package` 快照摘要；
- feature/profile/render input 到输出的确定性映射；
- artifact digest。

`RELEASES.json` 或同等索引必须把 `input_commit/input_tree` 精确映射到该 baseline，不允许只按版本字符串猜测。

### 9.2 每文件 render inputs

`render_input_digest` 不能笼统定义为整个 `peanut-project.json.project`。每个模板需要声明它实际消费的规范化字段。例如：

- `App.vue`：`brand`、`display_name`、`slug`；
- `clients.ts`：`tenant_clients`；
- `modules.ts`：`features`；
- `modules.php`：`features`、client audience mapping；
- `auth.php`：`admin_client_key`、`tenant_clients`；
- `provider.php`/`route/app.php`：`php_namespace` 以及真实影响字段；
- `index.html`：`display_name`。

必须定义稳定 JSON canonicalization、字段缺失、未知字段、模板版本和 renderer version。旧/新实际内容必须从 digest 已核验的 scaffold artifact 取得，digest 只用于判定 pristine，不能代替生成可读 diff 所需的内容。

---

## 十、修正 Package Manifest 与 Lockfile 升级流程

v3.2 的流程直接执行：

```text
composer update peanut-admin/*
pnpm update "@peanut-admin/*"
```

但从 `^0.1.0` 升到 `0.2.0` 前，必须先修改应用 manifest 的目标约束，否则包管理器不会跨越现有约束。

v3.3 必须规定：

1. `plan` 记录每个启用 package 的 old constraint、target constraint 和目标 package identity；
2. `code-apply` 使用 JSON API 修改 `backend/composer.json`、`frontend/package.json`，不做字符串替换；
3. 然后运行限定范围的 Composer/pnpm 更新；
4. 解析更新后的 lockfile，逐包核对 version、source/dist reference 或 integrity；
5. 输出更新前后的完整 lock graph diff；
6. Peanut 包及 release manifest 声明的必要传递依赖变化允许；
7. 无关 root dependency 或无法解释的 lock entry 变化默认阻断；
8. 合法的第三方传递依赖变化必须出现在 plan/report 中，不能静默处理。

不要要求传递依赖“一个都不能变化”，那会使正常 Composer/npm 求解无法工作；要限制的是未被目标包依赖图解释的变化。

---

## 十一、补全 Plan、代码制品与 DB-Apply 身份绑定

v3.2 的 plan 虽记录三个 lock digest，但 stale plan 只检查 scaffold lock；db-apply 也只检查 `peanut_version` 和 release manifest digest。这不能证明部署中的实际 PHP/Web 包就是 code-apply 结果。

v3.3 必须定义：

### 11.1 Code-Apply 输入核验

启动时同时核验：

- plan sidecar digest；
- scaffold lock digest；
- Composer manifest + lock digest；
- Web/root manifest + pnpm lock digest；
- 当前 release/source identity。

任何输入变化均判定 stale plan。

### 11.2 Deployment Identity

code-apply 成功后生成可随部署 artifact 携带的机器文件，例如 `.peanut/deployment-identity.json`，至少包含：

- schema version；
- plan digest；
- release manifest digest；
- scaffold lock digest；
- Composer manifest/lock digest；
- 已解析 `peanut-admin/*` 的 package version 与 source/dist reference；
- Web/root manifest 与 pnpm lock digest；
- 已解析 `@peanut-admin/*` 的 version/integrity；
- code commit，或不可变 deployment artifact digest；
- 生成时间只能作信息，不能参与身份判断。

生产部署不应依赖 Git worktree 或单独存在的 `scaffold.lock`。deployment identity 必须进入最终部署制品，并由制品签名/digest 覆盖。

### 11.3 DB-Apply

`db-apply` 必须核验：

- 当前 plan digest；
- release manifest digest；
- 当前运行制品/commit identity；
- 实际 PHP installed packages 与 deployment identity；
- 数据库 migration inventory 与 release manifest。

Web package identity可以在部署 artifact 构建阶段核验并写入 identity；db-apply 不需要在生产重新运行 pnpm。

固定基线已经存在数据库升级使用的 `backend/app/upgrade/ReleaseManifest.php` schema v1。v3.3 必须说明是扩展该 schema、引入明确不同名称的 lifecycle release manifest，还是做版本化统一；不能同时保留两个含义不同却都叫 ReleaseManifest schema v1 的格式。

保持身份模型精简，不恢复代码阶段跨进程 ledger。

---

## 十二、补全 Adoption 与旧源码快照迁移

### 12.1 当前真实快照不止 Module 连接层

固定基线 `ProjectGenerator::copyPackageSnapshots()` 还会把以下内容复制到生成应用：

- `packages/php/*` 的 manifest、LICENSE、src、database、resources；
- `packages/web/*` 的 package manifest、LICENSE 和 src；
- `starter/backend/src/Modules/Peanut/*` 的 ModuleProvider、descriptor、resources 和 migrations。

v3.2 只处理最后一类，遗漏了会与 registry package 直接冲突的本地 PHP/Web path packages。

v3.3 必须分别处理：

- legacy PHP package snapshot；
- legacy Web package snapshot；
- legacy application-side Peanut Module connector；
- 其他 scaffold-managed/rendered 文件。

### 12.2 Adoption 状态机

每个 package/module unit 至少支持：

- `pristine-snapshot`：内容与 legacy artifact 一致，可在切换 registry package后删除；
- `modified-snapshot`：默认阻断；
- `migrated-to-extension`：用户已把应用差异迁入 extension，证据可核验，可删除旧快照；
- `local-fork`：完整 package/module unit 明确保留为应用 fork；
- `unresolved`：无法映射历史基线或证据不足，阻断。

### 12.3 Fork 共存规则

`--accept-override <path>` 不能允许零散单文件静默留下。必须按完整 package/module unit处理并规定：

- 保留 `packages/php/<name>` local fork 时继续使用 path repository，不能同时安装同名 Packagist package；
- 保留 Web local fork 时继续使用 workspace/link source，不能同时解析同名 registry package；
- 保留旧 application-side Module connector 时必须禁用对应 package descriptor，避免 duplicate module key/provider namespace 冲突；
- local fork 不自动获得新 package migrations/resources；升级 plan 必须将其标记为人工维护且默认阻断跨 breaking release；
- 只有迁移到 extension 或明确维护完整 fork 后才能删除旧文件。

`--migrate-snapshots` 可以继续只生成报告，但名称和帮助文本必须明确它不执行搬迁或删除。真正删除只允许 code-apply 对已证明 pristine 或已完成迁移的完整 unit 执行。

`RELEASES.json` 和 Stage C.2 legacy baseline由 U01 产生；Adoption 命令本身由 U02 实现。

---

## 十三、U01 边界必须重新划分

U01 作为一个里程碑可以保留，但不能再作为一个巨大实现合同。请在 v3.3 中拆为三个连续合同，并给出各自输入、写集、禁止范围和可观察完成条件。

### U01a：Package Module Contract

范围：

- module schema v2；
- Composer `extra` discovery；
- package/application 双来源模型；
- provider namespace/autoload/resource path 校验；
- menu audience → application client mapping；
- frontend component inventory；
- registry compiler/boundary checker 必要重构。

先以 Settings 作为贯穿样例验证契约，但合同必须面向全部 package。

### U01b：Package Migration

范围：

- 迁移全部 PHP Module connector/resources/migrations；
- 拆分并迁移 `TenantAuthRuntimeFactory`、File/Media factory/adapter 的正确所有权；
- 迁移 Web host adapters并更新 exports/dependencies；
- 更新内部标准后台和 starter 组合根以使用新包接口；
- 暂时保留 legacy generator 快照兼容，避免 U02 前生成器立即失效。

### U01c：Release Distribution

范围：

- 正确的 split GitHub Action matrix；
- 只读 package repositories；
- 删除 PHP manifest 硬编码 version；
- Packagist/npm 发布；
- 依赖拓扑和同步 release train；
- package integrity/source reference；
- scaffold artifact、Stage C.2 legacy artifact、release index和 lifecycle release manifest；
- 部分发布失败时不发布最终 release manifest。

### U02 继续负责

- `ProjectGenerator` 切换到 registry packages；
- 删除 `copyPackageSnapshots()`；
- 生成完整应用和 `scaffold.lock`；
- 生成 application client/component registries；
- 实现 Adoption。

### U03 / U04

- U03：plan、manifest约束更新、限定 lock 更新、scaffold apply、deployment identity；
- U04：部署制品核验、db-apply和完整生命周期演示。

不要把 U02 的生成器切换重新塞回 U01。

---

## 十四、必须产出的文件

请直接创建以下两个文件：

### 14.1 v3.3 唯一候选主设计

`docs/plans/2026-07-25-lifecycle-architecture-v3.3.md`

它必须自包含，不要求读者先读 v3.2。至少包含：

- 普通语言总览；
- PHP/Web 多包发布与版本编排；
- 正确 split Action 和 Composer tag/version规则；
- package/application Module discovery与边界；
- menu audience/client mapping；
- Web host adapter所有权；
- 完整 starter 文件分类；
- scaffold + legacy artifacts和逐文件 render inputs；
- package manifest/lock更新算法；
- plan/deployment/db identity；
- 完整 Adoption状态机与 local fork规则；
- U01a/U01b/U01c/U02/U03/U04边界；
- 明确不做事项。

所有 JSON/schema/命令示例必须字段一致。新机制必须明确：谁生成、谁拥有、内容从哪里取得、身份如何核验、失败后如何恢复。

### 14.2 下一轮 Codex 审核提示词

`docs/plans/2026-07-25-lifecycle-v3.3-review-prompt.md`

要求 Codex：

- 先读 `docs/plans/chatgpt-engineering-context.md`；
- 只使用固定 Stage C.2 commit/tree核验；
- 核对 split Action 的真实用法和 PHP manifest版本策略；
- 核对 Composer discovery/autoload与 Module compiler边界；
- 核对 menu client映射和 Web component inventory；
- 核对完整 starter分类和 legacy snapshots；
- 核对 manifest/lock更新、deployment identity与 Adoption fork；
- 给出“可进入 U01a / 修订后可进入 / 仍有根本问题”的明确结论；
- 只审核，不修改文件、不运行测试。

审核提示词不得预设 v3.3 正确。

---

## 十五、完成限制与回复格式

本轮只修改或创建：

- `docs/plans/2026-07-25-lifecycle-architecture-v3.3.md`
- `docs/plans/2026-07-25-lifecycle-v3.3-review-prompt.md`

不要修改 `PLAN.md`、`STATUS.md`、`HISTORY.md`，不要归档或删除旧设计，不要修改代码仓，不要发布包，不要运行测试。

完成后简洁回复：

1. v3.3 关闭了哪些必须修订项；
2. 哪些风险明确留给实现合同或资格测试；
3. 两个产出文件的完整路径。
