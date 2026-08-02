# Codex 审核任务：Peanut Admin 生命周期架构 v3.2

## 任务目标

以 Stage C.2 固定源码为准，审核 v3.2 设计文档，给出"可进入 U01 / 修订后可进入 / 仍有根本问题"的明确结论。

**只审核，不修改文件，不运行测试。**

---

## 必读材料

1. `docs/plans/2026-07-25-lifecycle-architecture-v3.2.md`（主设计）
2. 固定基线 `commit: 69c5b2c271413f6ff741de65437f29e04f975300`

核对以下文件（不得用当前工作区替代固定基线）：

- `packages/php/*/composer.json`（确认包边界）
- `packages/php/settings/src/`（确认包内已有内容）
- `starter/backend/src/Modules/Peanut/Settings/`（确认待移入内容）
- `starter/backend/src/Module/ModuleRegistryFactory.php`（确认当前加载机制）
- `starter/backend/config/modules.php`（确认当前配置结构）
- `starter/backend/route/app.php`、`app/provider.php`（确认文件内容）
- `starter/frontend/src/App.vue`、`clients.ts`（确认是否含应用特定值）
- `starter/frontend/src/app/modules.ts`（确认 feature 组合方式）
- `tools/project-generator/src/ProjectGenerator.php`（确认生成逻辑）

---

## 审核重点（10 项）

### 1. Split Repository 方案可行性

v3.2 选择 `symplify/monorepo-builder` 做 PHP 包 split。

- 当前 `symplify/monorepo-builder` 最新版本是否仍在维护？是否支持 PHP 8.3？
- Split 出来的只读仓库，Packagist 能直接从 GitHub 仓库 URL 注册并索引吗？
- 每个 split 包的 `composer.json` 是否已经有正确的 `name`、`version`、`autoload`？核对 `packages/php/*/composer.json` 的实际内容。
- `packages/php/kernel` 的 `composer.json` 里没有 `version` 字段（v3.2 没有说明）——Packagist 是从 Git tag 读取版本还是从 `composer.json` 里的 `version` 字段？

### 2. Module 目录结构移入包的技术可行性

v3.2 要求把 `starter/backend/src/Modules/Peanut/Settings/` 移入 `packages/php/settings/Module/`。

- `ModuleRegistryFactory` 当前通过 `$this->root . '/' . ltrim($path, '/')` 加载 module.json——移入包后如何改为 `InstalledVersions::getInstallPath('peanut-admin/settings') . '/Module/module.json'`？
- 当前 `module.json` 里的路径（如 migrations）是相对于什么目录的？移入包后相对路径基准是否变化？
- `ModuleHostLayout` 构造参数（`backend/src/Modules`、`ExampleHost\\App\\Modules`、`frontend/src/modules`）是面向应用仓的路径约定——移入包后这套 layout 是否还有意义？
- `ReflectionContractInspector` 等需要反射检查 PHP 类——包安装后类名 namespace 是 `PeanutAdmin\Settings\Module\ModuleProvider`，但 `module.json` 里 provider 字段格式是什么？源码中实际例子是否存在？

### 3. frontend/src/modules/peanut-*.ts 是否真的是 package-owned

v3.2 说这些文件"移入各 Web 包"。

- 实际读取 `starter/frontend/src/modules/peanut-settings.ts` 等文件——它们是纯包 re-export，还是包含应用特定 wiring（如 API baseUrl、client key）？
- 如果含有应用特定配置，它们不能整体移入包，需要重新分类。

### 4. scaffold-rendered 类文件的内容取得

v3.2 说 scaffold 文件真实内容从 `peanut-admin/scaffold` 包取得。

- 这个包在 Stage C.2 源码中存在吗？如果不存在，它是 U01 需要新建的产物还是已有基础？
- `render-if-pristine` 策略需要对比"当前渲染输出" vs "基于旧版本模板+相同 render input 的渲染结果"——旧版本模板如何取得（scaffold 包不同版本？）
- `render_input_digest` 是什么内容的摘要？是 `peanut-project.json` 里的 `project` 字段？

### 5. `modules.php` 的所有权问题

v3.2 把 `modules.php` 归为 `scaffold-rendered`，说 Peanut 模块由包自动发现后，`modules.php` 只保留 `application_modules` 和 `registered_client_keys`。

- 但当前 `ModuleRegistryFactory` 读取 `modules.php` 里的 `roots` 和 `frontend_components`——如果 Peanut 模块改为自动发现，这两个字段被删除后，现有 ModuleRegistryFactory 是否需要重写？
- 这个重写是 U01 的写集，还是超出 U01 范围？

### 6. 文件分类的准确性核验

请逐项验证以下分类是否正确：

- `backend/src/StarterExceptionHandler.php`：v3.2 归为 seed-once。但它的命名空间是 `PeanutAdmin\InternalStarter`，不是应用命名空间。ProjectGenerator 会将其 namespace 替换为应用 namespace 吗？（参考 `ProjectGenerator.php` 的 `replaceNamespaces` 方法）
- `frontend/src/App.vue`：v3.2 归为 scaffold-static。但 `writeFrontendApp()` 方法会生成含 brand/name/slug 的 App.vue——这是 rendered，不是 static。
- `frontend/index.html`：v3.2 归为 scaffold-rendered。核对 `writeFrontendIndex()` 的实际行为。

### 7. lockfile 的处理方式

v3.2 把 `composer.lock` 和 `pnpm-lock.yaml` 归为 `package-managed`，说"code-apply 后由 Composer/pnpm 重新生成"。

- `composer update peanut-admin/*` 是否只更新 Peanut 包的 lockfile 条目，还是可能更新其他第三方传递依赖的版本？
- 如果传递依赖版本被意外更改，如何检测和阻断？

### 8. 身份绑定的充分性

v3.2 的 `db-apply` 核验：`scaffold.lock.peanut_version` 与 plan 的 `peanut_version_to` 一致。

- 这只证明 scaffold.lock 已更新到目标版本，不能证明 Composer 包确实已安装了对应版本。是否还需要核验 `composer.lock` 里 Peanut 包的版本？
- 生产环境通常没有 `scaffold.lock`，因为应用以构建 artifact 部署——db-apply 如何在无 Git worktree 的环境中核验代码版本？

### 9. Adoption 流程完整性

- `RELEASES.json` 在 Stage C.2 中是否存在？如果不存在，它是 U01 的产物还是 U02 的产物？
- `moves-to-package` 类型且被用户修改过的文件（如修改了 `starter/backend/src/Modules/Peanut/Settings/ModuleProvider.php`）——v3.2 说阻断并要求 `--accept-override`，声明为 application-owned fork。但这意味着应用仓里永久保留一份 Peanut 模块的 fork——这个 fork 如何与后续 Peanut 包版本共存（namespace 冲突？）？

### 10. U01 写集边界

v3.2 的 U01 包含大量工作：移动约 50 个文件、重写 ModuleRegistryFactory、建立 split CI、发布到 Packagist、修改 ProjectGenerator。

- 这是否超出了"U01 只承担后续不可缺少的契约与发布基础"的约定？
- 是否应该进一步拆分：先做包结构重构（U01a），再做发布 CI（U01b），再做 ProjectGenerator 修改（U02 前置）？
- 没有 split CI 就没法从 Packagist 安装，但 split CI 本身是一个相当大的工程任务——这是否是 U01 的真正阻塞？

---

## 输出格式

### Part 1：总体结论

明确选择：
- 可直接进入 U01 合同起草
- 修订后可进入（列出必须修订的项）
- 仍有根本问题（说明根本问题是什么）

### Part 2：逐项审核（10 项）

每项：立场 + 理由 + 修订建议（如需要）

### Part 3：U01 写集建议

给出 U01 的合理范围界定，防止范围蔓延。

---

## 审核原则

- 以固定基线源码为准，不以文档自我声明为证据
- 方向正确但可在合同细化的问题，不列为阻塞
- 只有真正阻止实施的问题才升级为根本问题
