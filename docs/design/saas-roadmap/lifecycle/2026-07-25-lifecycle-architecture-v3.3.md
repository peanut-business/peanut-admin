# Peanut Admin 应用生命周期架构 v3.3

## 文档状态

- 状态：`design-final / ready-for-u01-contract`
- 分析基线：Stage C.2（`69c5b2c271413f6ff741de65437f29e04f975300`）
- 前版本：v3.2
- 本版修订（整合 Codex 对 v3.2 的审核）：
  1. 将 U01 拆分为 U01a/U01b/U01c 三个可独立验收的子阶段
  2. 修正 PHP 包 autoload：Module 类放入 `src/Module/`，不是单独的 `Module/` 目录
  3. 补充混合模块注册（包模块 + 应用模块共存）的发现机制
  4. 修正 App.vue 和 route/app.php 为 scaffold-rendered，不是 scaffold-static
  5. 补充 frontend_components 替代机制（release manifest 声明 PHP/Web contribution pair）
  6. 补充 lockfile 变更守卫（只允许授权的 Peanut 包及其传递依赖变化）
  7. 补充部署制品 release evidence（db-apply 不能只靠 scaffold.lock）
  8. 澄清 monorepo-builder 与 subtree split 工具分工（两件事，不是一个工具）
  9. 修正 adoption fork 共存规则（module key 冲突处理）

---

## 一、总览

Peanut Admin 是企业后台脚手架。用 `peanut new myapp` 创建应用后，应用仓包含：

1. **对 Peanut 包的依赖声明**：`composer.json` / `package.json` 里的版本约束，标准安装
2. **少量脚手架骨架文件**：由 Peanut 生成，按策略升级
3. **应用自己的业务代码**：Peanut 永不修改

Peanut 核心能力以**独立版本化包**交付（`peanut-admin/kernel` 等），不复制源码进应用仓。

---

## 二、PHP 多包发布拓扑

### 两个工具，分工明确

| 工具 | 职责 |
|---|---|
| `splitsh-lite`（或 `git subtree`）| 把 `packages/php/<name>` 的提交历史拆分到只读镜像仓库 |
| `symplify/monorepo-builder` | 版本号同步（所有包同版本）、release 编排 |

两个工具独立工作，不混淆。

### 发布流程（GitHub Actions CI）

```
monorepo tag 触发
  → monorepo-builder 同步版本号
  → splitsh-lite 将 packages/php/<name> split 到 github.com/peanut-admin/<name>
  → 各镜像仓库打对应 tag
  → Packagist 自动同步（已注册 VCS URL）
  → npm 各包 build + publish
  → 所有包发布成功后生成 release manifest
```

镜像仓库只分发，不接受 PR，CI 禁止向镜像仓直接 push 代码（只 push tag）。

### 版本策略

所有 Peanut PHP 包和 Web 包共享版本号，与 `@delon/*` 相同。

### 消费端示例

```json
// composer.json（无需自定义 repositories）
{
  "require": {
    "peanut-admin/kernel": "^0.2.0",
    "peanut-admin/settings": "^0.2.0"
  }
}
```

```json
// package.json
{
  "dependencies": {
    "@peanut-admin/admin-core": "^0.2.0",
    "@peanut-admin/admin-shell": "^0.2.0"
  }
}
```

**注意**：`packages/php/*/composer.json` 中的 `"version"` 字段应删除，由 tag 推导，与 Composer 推荐一致。

---

## 三、Module Descriptor：Package-Relative 契约

### PHP 包内 Module 结构

每个 Peanut Composer 包在 `src/Module/` 下包含 Module descriptor：

```
packages/php/settings/
  src/
    Module/
      ModuleProvider.php    ← namespace PeanutAdmin\Settings\Module
      module.json
      Resources/
        menus.json
        permissions.json
        setting-definitions.json
      Database/
        Migrations/
          *.php
    Application/            ← 原有业务逻辑（不动）
    Definition/
    ...
```

`composer.json` 的 autoload 无需改动（`PeanutAdmin\Settings\` 映射到 `src/`），`Module\ModuleProvider` 自动可加载。

### module.json schema v2

```json
{
  "schema_version": 2,
  "key": "peanut.settings",
  "version": "0.2.0",
  "owner": "package",
  "backend": {
    "provider": "PeanutAdmin\\Settings\\Module\\ModuleProvider",
    "migrations": "Module/Database/Migrations"
  },
  "frontend": {
    "component_key": "peanut.settings.page"
  },
  "resources": {
    "menus": "Module/Resources/menus.json",
    "permissions": "Module/Resources/permissions.json"
  }
}
```

所有路径相对于包安装目录（`vendor/peanut-admin/settings/`）。`owner: package` 区分包模块和应用模块。

### 模块发现机制

通过 Composer `extra` 元数据，不扫描整个 vendor：

```json
// packages/php/settings/composer.json
{
  "extra": {
    "peanut-admin": {
      "module": "src/Module/module.json"
    }
  }
}
```

`ModuleRegistryFactory` 通过 `Composer\InstalledVersions::getInstalledPackagesByType('library')` 遍历，只读取声明了 `extra.peanut-admin.module` 的包。不猜测，不扫描全部 vendor。

### 混合模块注册

应用自己的业务模块继续在 `modules.php` 的 `application_modules` 里声明，按目录加载：

```php
// backend/config/modules.php（scaffold-rendered，精简后）
return [
    'application_modules' => [
        // 应用自己的模块，如 'backend/src/Modules/MyApp/Case'
    ],
    'registered_client_keys' => ['admin-web', 'platform-web'],
];
```

Peanut 包模块由发现机制自动加载，不在此文件中列出。

### frontend_components 替代机制

删除 PHP 配置中的 `frontend_components` 后，后端通过 release manifest 了解前端贡献的组件：

```json
// release manifest 中的 module contributions
{
  "module_contributions": {
    "peanut.settings": {
      "php_package": "peanut-admin/settings",
      "web_package": "@peanut-admin/settings",
      "component_key": "peanut.settings.page"
    }
  }
}
```

后端在编译模块注册表时，从 release manifest 核验每个包模块的 `component_key` 是否存在于 Web 包的声明中（构建时检查，不是运行时猜测）。

---

## 四、生成文件完整分类（修正版）

基于 Stage C.2 `starter/` 实际文件树：

| 路径/模式 | 分类 | 升级策略 | 说明 |
|---|---|---|---|
| `backend/src/Modules/Peanut/*/Module/` 内容 | `moves-to-package` | 包安装后删除 | ModuleProvider、module.json、Resources、Migrations 移入各包的 `src/Module/` |
| `backend/src/Auth/TenantAuthRuntimeFactory.php` | `moves-to-package` | 包安装后删除 | 移入 kernel 包 |
| `backend/src/FileMedia/FileMediaStorageFactory.php` | `moves-to-package` | 包安装后删除 | 移入 file-media 包 |
| `backend/src/Module/ModuleRegistryFactory.php` 等 | `moves-to-package` | 包安装后删除，由薄 host factory 替代 | 移入 kernel 包，但 host factory（应用层）保留 |
| `frontend/src/modules/peanut-*.ts`（transport/runtime 部分） | `moves-to-package` | 移入各 Web 包 | 应用侧 client 注入仍由组合根提供 |
| `backend/route/app.php` | `scaffold-rendered` | render-if-pristine | 按 features 生成，含示例路由，namespace 被替换 |
| `backend/app/provider.php` | `scaffold-rendered` | render-if-pristine | ExceptionHandler namespace 被替换 |
| `backend/config/modules.php` | `scaffold-rendered` | render-if-pristine | 含 client keys |
| `backend/config/auth.php` | `scaffold-rendered` | render-if-pristine | 含 tenant client 定义 |
| `frontend/src/main.ts` | `scaffold-static` | replace-if-pristine | 内容固定，无应用特定值 |
| `frontend/src/App.vue` | `scaffold-rendered` | render-if-pristine | 含 brand、display name、slug |
| `frontend/src/app/modules.ts` | `scaffold-rendered` | render-if-pristine | 含 feature 模块列表 |
| `frontend/src/clients.ts` | `scaffold-rendered` | render-if-pristine | 含 client keys 和 API prefix |
| `frontend/index.html` | `scaffold-rendered` | render-if-pristine | 含 `<title>` |
| `backend/config/app.php` 等其他 config | `seed-once` | report-only | 创建后归应用 |
| `backend/public/index.php`、`router.php` | `seed-once` | report-only | ThinkPHP 入口 |
| `frontend/vite.config.ts`、`tsconfig.json` | `seed-once` | report-only | 构建配置 |
| `pnpm-workspace.yaml`、根 `package.json`（非 Peanut 依赖） | `seed-once` | report-only | workspace 配置 |
| `backend/composer.json`（Peanut 依赖约束） | `package-managed` | 版本约束由 code-apply 更新 | 其余字段 seed-once |
| `frontend/package.json`（Peanut 依赖约束） | `package-managed` | 版本约束由 code-apply 更新 | 其余字段 seed-once |
| `backend/composer.lock` | `package-managed` | Composer 重新生成 | 不做文本替换 |
| `pnpm-lock.yaml` | `package-managed` | pnpm 重新生成 | 不做文本替换 |
| `backend/src/StarterExceptionHandler.php` | `seed-once` | report-only | namespace 被替换，创建后归应用 |
| 测试文件 `backend/tests/*`、`frontend/tests/*` | `scaffold-rendered` | render-if-pristine | 按 features 生成 |
| `.env.example`、`.gitignore`、`README.md` | `seed-once` | report-only | |
| `backend/src/Modules/Example/` | `demo-module` | 可删除 | README 说明删除方式 |
| `frontend/src/modules/example-greeting/` | `demo-module` | 可删除 | |
| `extensions/`（骨架） | `application-owned` | 永不修改 | |
| `domain/` | `application-owned` | 永不修改 | |

---

## 五、Scaffold Manifest 与 Lock

### Scaffold Manifest（Peanut 侧，随 release 发布）

```json
{
  "schema_version": 1,
  "peanut_version": "0.2.0",
  "artifact_digest": "sha256:...",
  "files": {
    "backend/route/app.php": {
      "policy": "replace-if-pristine"
    },
    "backend/config/modules.php": {
      "policy": "render-if-pristine",
      "template_id": "backend/config/modules.php.tpl",
      "render_input_schema": "v1",
      "content_digest": "sha256:..."
    },
    "frontend/src/App.vue": {
      "policy": "render-if-pristine",
      "template_id": "frontend/src/App.vue.tpl",
      "render_input_schema": "v1"
    }
  },
  "renames": [],
  "deletes": []
}
```

文件真实内容从 `peanut-admin/scaffold` 包（U01c 新建）取得，不塞进 JSON。

### render_input_schema v1

每个 rendered 文件消费以下输入（全部来自 `peanut-project.json`）：

```json
{
  "slug": "myapp",
  "display_name": "My App",
  "brand": "My Brand",
  "php_namespace": "MyApp",
  "features": ["settings", "file-media"],
  "tenant_clients": [{"key": "admin-web", "api_prefix": "/api/admin/v1/"}],
  "admin_client_key": "admin-web"
}
```

规范化规则：JSON 字段按字母序排列，UTF-8，LF 换行，末尾换行，无尾随空格。`render_input_digest` = `sha256(canonical_json(inputs))`。

### `scaffold.lock`（应用侧）

```json
{
  "schema_version": 1,
  "peanut_version": "0.1.0",
  "scaffold_artifact_digest": "sha256:...",
  "render_input_digest": "sha256:...",
  "files": {
    "backend/route/app.php": {
      "policy": "replace-if-pristine",
      "base_digest": "sha256:...",
      "app_override": false
    },
    "backend/config/modules.php": {
      "policy": "render-if-pristine",
      "rendered_base_digest": "sha256:...",
      "app_override": false
    }
  }
}
```

---

## 六、前端扩展模型

应用通过显式 manifest 注册，不 glob 页面文件：

```ts
// extensions/frontend/src/extension.ts（application-owned）
export default defineAdminExtension({
  id: 'myapp',
  pages: {
    'myapp.case.list': () => import('./pages/CaseListPage.vue'),
  },
  appearances: {
    'peanut.auth.login.shell': () => import('./appearances/MyLoginShell.vue'),
  },
})
```

组合根（scaffold-rendered）glob manifest：

```ts
// frontend/src/app/modules.ts（scaffold-rendered）
const extensions = import.meta.glob(
  '../../../extensions/frontend/*/extension.ts',
  { eager: true }
)
```

---

## 七、后端 Provider 契约

```php
final class AppServiceProvider implements ApplicationProvider
{
    public function register(ServiceRegistry $registry): void
    {
        $registry->singleton(AppRepository::class, PdoAppRepository::class);
    }

    public function boot(ApplicationContributions $app): void
    {
        $app->routes()->module('myapp.case', new MyCaseRouteProvider());
        $app->middleware()
            ->forModule('myapp.case')
            ->after(CoreMiddleware::PERMISSION, MyCaseScopeMiddleware::class);
    }
}
```

`ServiceRegistry` 包装 ThinkPHP 容器（已由 `architecture-fix-contract.md` 的 B1 补丁引入 `PdoProvider`，后续 U01a 正式化为接口）。

---

## 八、三段升级命令

### `peanut upgrade plan --to <version>`

只读，下载目标版本 scaffold manifest 和 release manifest，输出计划文件。

Plan sidecar：`.peanut/plans/upgrade-0.2.0.json.sha256`

### `peanut upgrade code-apply <plan>`（隔离 Git worktree）

执行顺序：
1. 核验 plan sidecar、stale plan 检测（`render_input_digest` + `scaffold_artifact_digest`）
2. 检查 breaking change resolution records
3. `composer update peanut-admin/*` + lockfile 变更守卫（见下）
4. `pnpm update "@peanut-admin/*"` + lockfile 变更守卫
5. 处理 scaffold files（replace-if-pristine / render-if-pristine）
6. 更新 `scaffold.lock`
7. 生成 `release-evidence.json`（见部署制品身份）

**Lockfile 变更守卫**：解析升级前后的 lockfile，允许：Peanut 包本身的变化 + release manifest 声明的必要传递依赖变化。任何未列入允许集的第三方版本变化立即阻断并展示差异。

### `peanut upgrade db-apply <plan>`（部署环境）

核验 `release-evidence.json`（见下），然后执行现有数据库升级逻辑。

---

## 九、部署制品身份（release-evidence.json）

`code-apply` 成功后生成，随代码 commit 进入 Git，也进入 CI 构建制品：

```json
{
  "schema_version": 1,
  "peanut_version": "0.2.0",
  "release_manifest_digest": "sha256:...",
  "scaffold_artifact_digest": "sha256:...",
  "composer_lock_digest": "sha256:...",
  "pnpm_lock_digest": "sha256:...",
  "peanut_php_versions": {
    "peanut-admin/kernel": "0.2.0",
    "peanut-admin/settings": "0.2.0"
  },
  "peanut_web_versions": {
    "@peanut-admin/admin-core": "0.2.0"
  },
  "migration_inventory_digest": "sha256:..."
}
```

`db-apply` 核验：
- `release-evidence.json` 的 `release_manifest_digest` 与 plan 一致
- `peanut_php_versions` 与 `Composer\InstalledVersions` 实际值一致
- `migration_inventory_digest` 与当前 migration 目录扫描结果一致

生产环境无 Git 时，从 CI 构建制品中取得 `release-evidence.json`，不依赖 worktree。

---

## 十、Adoption（旧应用引导，修正版）

```bash
peanut upgrade adopt [--accept-override <path>]...
```

从 `peanut-project.json` 的 `input_commit` 通过 `RELEASES.json` 映射到版本（`RELEASES.json` 是 U01b 的产物）。

**moves-to-package 文件处理**：

采用者修改过 `backend/src/Modules/Peanut/Settings/ModuleProvider.php` 等包源码快照时，不能直接声明 application-owned fork（会产生 module key 冲突）。只允许以下明确结果之一：

| 选择 | 操作 |
|---|---|
| 接受官方版本，丢弃本地修改 | 标记 pristine，code-apply 后官方包替代 |
| 迁移修改到 extension/Provider | 手工完成，记录在 resolution record，再执行 adopt |
| 替换官方模块（发布覆盖包） | 在 `composer.json` 中用 `replace/conflict` 排除官方包，应用包接管相同 module key |

Adoption 不允许在不解决 module key 冲突的情况下同时保留官方包和本地 fork。

---

## 十一、任务顺序（拆分后）

### U01a：包契约与模块合同

**交付**：
- 各 PHP 包 `src/Module/` 结构（ModuleProvider、module.json、Resources、Migrations 移入）
- module.json schema v2（含 `owner` 字段）
- Composer `extra.peanut-admin.module` 发现机制
- `ModuleRegistryFactory` 支持包模块 + 应用模块混合
- Web 包 transport/runtime 迁移（应用侧 client 注入）
- release manifest 的 `module_contributions` 字段

**完成条件**：本地 path 安装可编译包模块和应用模块，`frontend_components` 从 PHP 配置中删除后功能不退化。

### U01b：发布基础

**交付**：
- `splitsh-lite` + GitHub Actions 自动 split 和 tag 传播
- Packagist 注册（各包的镜像仓 VCS URL）
- npmjs.com 发布 CI
- `RELEASES.json`（commit 到 version 的映射，随 monorepo tag 生成）
- 发布失败时的停止和补发规则

**完成条件**：`composer require peanut-admin/kernel:^0.2.0` 从 Packagist 安装，不依赖 monorepo 相对路径。

### U01c：Scaffold 合同

**交付**：
- `peanut-admin/scaffold` 包（含 scaffold manifest 和模板文件）
- render_input_schema v1（规范化规则）
- `scaffold.lock` schema v1
- `release-evidence.json` schema v1
- deployment identity 核验逻辑

**完成条件**：plan 可以按版本取得源/目标 scaffold artifact，render_input_digest 可重建，db-apply 可核验部署身份。

### U02：完整应用创建器

`peanut new <slug>` 生成应用、安装 Peanut 包（不复制源码）、生成 scaffold.lock 和 release-evidence.json、生成 extensions/ 骨架。实现 `peanut upgrade adopt`。

### U03：升级器

`peanut upgrade plan`、`peanut upgrade code-apply`，含 lockfile 守卫、breaking change resolution record。

### U04：数据库部署与演示

`peanut upgrade db-apply`，完整生命周期演示。

---

## 十二、明确不做的事

| 能力 | 理由 |
|---|---|
| PHP/TS AST structured-merge | 通过所有权拆分消除需求，不引入 parser |
| 通用文本三方合并 | scaffold 文件用 replace/render-if-pristine |
| base64 内容塞进 JSON | 内容从 scaffold 包取得 |
| 跨进程代码 ledger | Git worktree 是代码恢复边界 |
| 自制包安装器 | 使用 Composer 和 pnpm |
| 全目录 page glob | extension manifest 显式注册 |
| v1 完全离线支持 | 接受 Packagist/npmjs.com 可达为前提 |
| 独立 IoC 容器 | 已有 PdoProvider（B1 补丁），U01a 正式化为 ServiceRegistry 接口 |
