# Peanut Admin 应用生命周期架构 v2

## 文档状态

- 状态：`design-draft / pending-external-review`
- 分析基线：Stage C.2（`69c5b2c271413f6ff741de65437f29e04f975300`）
- 参考源码：Symfony Flex `RecipePatcher.php`、Nx `migrate.ts`、Vben `generate-routes-backend.ts`、Filament `PanelProvider.php`
- 上一版本：[2026-07-24 版设计文档](2026-07-24-peanut-application-lifecycle-and-upgrade-design.md)
- 本文目的：在上一版基础上，根据参考项目实际源码给出更具体、可落地的架构决策，供外部评审优化。

---

## 一、问题定义

Peanut Admin 是企业后台脚手架框架。用 `peanut new <slug>` 创建下游应用后，应用仓里存在三类内容，它们对应三种升级行为：

| 类型 | 定义 | 升级行为 |
|---|---|---|
| **package-owned** | 只存在于已安装的 Composer / pnpm 包中，应用仓不含源码 | 更换包版本即可 |
| **recipe-managed** | 必须存在于应用仓的极少数入口和配置文件，由 Peanut 在创建时生成 | 用旧基线→新基线→应用当前内容三方合并，保留应用改动 |
| **application-owned** | 下游应用的业务代码、业务页面、数据库表、数据和配置 | 升级器永不触碰 |

**最终目标**：任何用 `peanut new` 创建的应用，都能长期执行 `peanut upgrade plan / apply` 跟随 Peanut 版本演进，不需要手工维护 Peanut 内部文件，不会意外覆盖应用业务代码。

---

## 二、当前事实与差距

### 创建器差距（`tools/project-generator/src/ProjectGenerator.php`）

- `writeFrontendApp()` 生成的 App.vue 只有 `AdminShell + PageContent + project-slug`，没有 router-view、登录页、WorkspaceLayout，不是完整标准后台。
- `starter/backend/route/app.php` 只有 health 和 greeting 两条路由。
- `peanut-project.json` 没有 `managed-files.lock`，没有 baseline blob hash，无法支撑三方合并。
- `TRUSTED_MENU_ROUTE_CONTRACTS`（`frontend/src/app/routes.ts:52`）把所有租户侧菜单的 clientKeys 硬编码为 `['admin-web']`，生成的下游应用如果使用自定义 client key，菜单解析将失效。

### 升级器差距（`backend/app/command/UpgradeCli.php`）

- 没有只读 `plan` 子命令，没有产生中间计划文件的能力。
- `ReleaseManifest`（`backend/app/upgrade/ReleaseManifest.php`）只有 source/target commit+tree 和 migration 清单，缺少包版本摘要、受管文件 baseline blob、source migration 列表。
- `UpgradeWorkflow.connectFromEnvironment()` 直接读 `getenv()`，没有统一 DI 层。
- 不升级 Composer / pnpm 包，不合并受管文件，不执行源码 migration。

### 前后端扩展差距

- `frontend/src/app/router.ts` 中 login 页、WorkspaceLayout、StatusPage 全部硬导入，下游应用要换外观必须修改这些文件，升级会冲突。
- 后端没有 `ApplicationProvider` 接口，没有 `ServiceContainer`，模块 Provider 通过 `new $providerClass()` 动态构造，每个 Runtime factory 自己创建 PDO。

---

## 三、参考项目的关键源码发现

### 3.1 Symfony Flex：三方合并的具体实现

`flex/src/Update/RecipePatcher.php` 的 `generatePatch()` + `applyPatch()` 流程：

1. 在 `sys_get_temp_dir()` 创建临时 git 仓库
2. 写入**旧 recipe 版本**的文件，`git commit`
3. 覆盖写入**新 recipe 版本**的文件，`git diff --cached` 生成 patch（patch 里含 blob hash）
4. 将 blob 内容写入**目标项目**的 `.git/objects/`（`addMissingBlobs()`）
5. 在项目根执行 `git apply <patch> -3`，利用 blob 找到三方合并祖先

冲突时返回 `false`，不抛异常，patch 和 blob 在 finally 中清理。`symfony.lock` 为每个 recipe 记录 `version` 和 `files`，相当于 Peanut 的 `managed-files.lock`。

**直接结论**：Peanut 无需自己写 diff 算法，复用 `git apply -3` 即可。每个受管文件在 lock 里只需存一个字段：`baseline_blob`（`git hash-object` 的结果）。

### 3.2 Nx migrate：两阶段分离

`nx migrate` 更新 `package.json`，生成 `migrations.json`（中间产物，可手工编辑）；`nx migrate --run-migrations` 才真正执行。

`migration-shape.ts` 的每个 entry：
```ts
{ implementation?: string; prompt?: string; factory?: string }
```
`implementation` 是确定性脚本，`prompt` 是需要人工/AI 处理的变更描述。Peanut 对应 `deterministic` / `manual` 两种 source migration 类型，`manual` 类型在 plan 文件中以醒目说明呈现，不自动执行。

### 3.3 Vben Admin：pageMap 是 glob，不是显式注册

`apps/web-antd/src/router/access.ts:18`：
```ts
const pageMap: ComponentRecordType = import.meta.glob('../views/**/*.vue')
```

`generate-routes-backend.ts:78-89` 的回退逻辑：未知 key 回退到 `/_core/fallback/not-found.vue`，fail-closed，不抛异常。

路径规范化函数 `normalizeViewPath()` 耦合了 `views/` 目录结构（去除 `/views` 前缀）。Peanut 需要类似的约定，但以 `extensions/frontend/pages/` 为基准。

**直接结论**：下游应用只需把业务页面放进 `extensions/frontend/pages/`，glob 自动发现，不需要维护注册文件。Peanut 包导出固定的 sealed 组件（login、layout、status），两者合并进同一 pageMap。

### 3.4 Filament：Provider 应保持极简

`filament/packages/panels/src/PanelProvider.php` 全文 17 行：
```php
abstract class PanelProvider extends ServiceProvider
{
    abstract public function panel(Panel $panel): Panel;
    public function register(): void
    {
        Filament::registerPanel(fn(): Panel => $this->panel(Panel::make()));
    }
}
```

Provider 只做一件事：注册一个 fluent builder。所有配置（middleware、routes、plugins、render hooks）都在 `Panel` 对象的链式调用上。`HasRenderHooks` trait 的存储结构只是 `array<string, array<string, list<Closure>>>`。

**直接结论**：`ApplicationProvider` 接口应同样克制。`ServiceContainer` 只需 `bind/singleton/get`，目的是统一 PDO 来源，不引入完整 IoC 容器。

---

## 四、推荐架构

### 4.1 应用目录结构

```
.peanut/
  project.json          ← 创建版本、slug、profile、features
  managed-files.lock    ← 受管文件清单，每项含 baseline_blob
  plans/                ← upgrade plan 文件存放处
apps/
  frontend/             ← 薄前端组合根（recipe-managed，约 3 个文件）
  backend/              ← 薄后端组合根（recipe-managed，约 3 个文件）
extensions/
  frontend/
    pages/              ← 应用业务页面（glob 自动发现，无需注册）
    layouts/            ← 应用自定义布局
  backend/
    Providers/          ← 应用 ApplicationProvider 实现
domain/
  <app>/                ← 应用业务代码，升级器永不修改
```

### 4.2 `managed-files.lock` 结构

参考 `symfony.lock` 的键值结构：

```json
{
  "schema_version": 1,
  "recipe_version": "0.1.0",
  "files": {
    "apps/frontend/src/main.ts": {
      "ownership": "exclusive",
      "baseline_blob": "a3f4e2c1..."
    },
    "apps/frontend/src/app/page-registry.ts": {
      "ownership": "exclusive",
      "baseline_blob": "d9e1f3b0..."
    },
    "apps/backend/bootstrap/app.php": {
      "ownership": "exclusive",
      "baseline_blob": "b7c9d1f0..."
    }
  }
}
```

`baseline_blob` = 生成时 `git hash-object <file>` 的结果。升级时用来重建三方合并的公共祖先。

`ownership` 取值：
- `exclusive`：Peanut 独占，应用不应修改（但可以通过扩展点贡献内容）
- `shared`：Peanut 和应用都可能修改，必须三方合并
- `advisory`：Peanut 提供初始内容，之后由应用完全接管，升级时只提示不合并

### 4.3 前端页面注册

```ts
// apps/frontend/src/app/page-registry.ts（recipe-managed）
import { createAdminPageRegistry } from '@peanut-admin/admin-core'
import { TenantLoginPage, WorkspaceLayout, StatusPage } from '@peanut-admin/admin-shell'

// 应用业务页面：glob 自动发现，新增页面不需要改注册文件
const appPages   = import.meta.glob('../../../extensions/frontend/pages/**/*.vue')
const appLayouts = import.meta.glob('../../../extensions/frontend/layouts/**/*.vue')

export const pageRegistry = createAdminPageRegistry({
  // 不可替换：由包固定，应用不能覆盖
  sealed: {
    'peanut.layout.workspace': WorkspaceLayout,
    'peanut.page.tenant.login': TenantLoginPage,
    'peanut.page.status': StatusPage,
  },
  // 应用贡献，key = 相对 extensions/frontend/pages/ 的路径去 .vue 后缀
  pages: appPages,
  layouts: appLayouts,
})
```

服务端菜单返回 `component_key`，runtime 从 `pageRegistry` 解析，未知 key fail-closed 到 not-found（参考 Vben 的 `route.component = pageMap['/_core/fallback/not-found.vue']`）。

`TRUSTED_MENU_ROUTE_CONTRACTS` 的 clientKeys 硬编码问题：改为以 `audience`（tenant / platform）作为约束，由 runtime 在解析时按当前登录 client 的 audience 动态匹配，不再比较具体 client key 字符串。

### 4.4 HTTP interceptor 顺序

固定分阶段，应用不能重排：

**请求链**：
1. Core：Request ID、API audience 限制、Origin 校验
2. Core：Authorization、Tenant/Platform context
3. 应用：可添加白名单业务 header，不能覆盖 Authorization / Tenant / Request ID / Idempotency-Key

**响应链**：
1. Core：401 refresh + 安全重放
2. Core：统一 Problem Details 格式
3. 应用：业务通知、埋点，不能把权限错误改为成功

### 4.5 后端 Provider 契约

```php
// extensions/backend/Providers/AppServiceProvider.php（application-owned）
final class AppServiceProvider implements ApplicationProvider
{
    public function register(ServiceContainer $container): void
    {
        // 统一注入，不直接读 getenv()
        $container->singleton(AppRepository::class, PdoAppRepository::class);
    }

    public function boot(ApplicationContributions $app): void
    {
        $app->routes()->group('/app', new AppRouteGroup());
        $app->middleware()->after('peanut.permission', AppScopeMiddleware::class, '/app/*');
        $app->workers()->register('app.recalculate', AppRecalculateHandler::class);
    }
}
```

`ServiceContainer` 接口只有三个方法：`bind()`、`singleton()`、`get()`。目的是统一 PDO 来源，解决当前 `UpgradeWorkflow::connectFromEnvironment()` 等各处自己读 `getenv()` 的问题，不引入完整 IoC 容器。

### 4.6 后端中间件链固定顺序

```
[最外层异常 / Problem Details]
[Request ID + Security Headers]
[CORS + 限流]
[TenantGuard / PlatformGuard]            ← 命名锚点 peanut.auth
[ModuleGuard]                             ← 命名锚点 peanut.module
[PermissionGuard / DataPermission]        ← 命名锚点 peanut.permission
[Idempotency]                             ← 命名锚点 peanut.idempotency
[应用 middleware（after peanut.permission，限定路径前缀）]
[Controller / Handler]
[审计 + 指标]
```

应用不能替换整个 middleware 数组，只能在命名锚点后声明 `after`，并限定到自己的 route prefix。

### 4.7 升级流程：两阶段 plan / apply

**阶段一：`peanut upgrade plan --to <version>`**

只读，不改任何文件，输出 `.peanut/plans/upgrade-<version>.json`：

```json
{
  "schema_version": 1,
  "from": "0.1.0",
  "to": "0.2.0",
  "packages": {
    "php": { "peanut-admin/kernel": "0.2.0" },
    "web": { "@peanut-admin/admin-core": "0.2.0" }
  },
  "managed_files": [
    {
      "path": "apps/frontend/src/main.ts",
      "action": "merge",
      "new_baseline_blob": "c4d7e9f2..."
    }
  ],
  "source_migrations": [
    {
      "id": "peanut.kernel@0.2.0/rename-tenant-guard",
      "type": "deterministic",
      "description": "TenantGuard 类名变更，需更新 bootstrap 引用",
      "applies_to": ["apps/backend/bootstrap/app.php"]
    },
    {
      "id": "peanut.kernel@0.2.0/provider-signature-change",
      "type": "manual",
      "description": "ApplicationProvider.boot() 签名变更，需手工更新 extensions/backend/Providers/ 下的实现",
      "applies_to": []
    }
  ],
  "database_migrations": ["module:peanut.settings:20260724000001_..."],
  "cannot_touch": ["domain/", "extensions/"],
  "breaking_changes": ["ApplicationProvider.boot() 签名变更"],
  "requires_sequential_from": "0.1.0"
}
```

**阶段二：`peanut upgrade apply .peanut/plans/upgrade-<version>.json`**

在隔离 Git branch 执行，按以下步骤顺序进行：

1. 核验 plan 文件签名、工作区干净、备份证据
2. 仅升级 Peanut 包（Composer + pnpm）
3. 执行 `deterministic` source migration（修改 recipe-managed 文件）
4. 对 `managed_files` 中每个文件执行三方合并：
   - 取 `baseline_blob` 内容（旧 recipe 版本，作为三方合并祖先）
   - 取新版本包中对应文件内容（新 recipe 版本）
   - 临时 git 仓库生成 patch，写入 blob，`git apply -3`
   - 冲突时停止，不前移 lock
5. 数据库 migration（已有能力，保留 advisory lock + checksum）
6. 全部成功后更新 `managed-files.lock` 中各文件的 `baseline_blob`
7. 输出升级 ledger，记录每步 `planned / running / applied / failed` 状态

`manual` 类型 source migration 不自动执行，在 apply 输出中列出，要求开发者手工处理后再次运行 apply。

### 4.8 `ReleaseManifest` schema 扩展

当前 schema_version=1 缺失字段，需扩展至 schema_version=2：

```json
{
  "schema_version": 2,
  "release_id": "0.2.0",
  "source": { "commit": "...", "tree": "..." },
  "target": { "commit": "...", "tree": "..." },
  "packages": {
    "php": { "peanut-admin/kernel": { "version": "0.2.0", "sha256": "..." } },
    "web": { "@peanut-admin/admin-core": { "version": "0.2.0", "sha256": "..." } }
  },
  "managed_files": {
    "apps/frontend/src/main.ts": {
      "new_baseline_blob": "c4d7e9f2...",
      "action": "merge"
    }
  },
  "source_migrations": [
    {
      "id": "peanut.kernel@0.2.0/rename-tenant-guard",
      "type": "deterministic",
      "from_version": ">=0.1.0 <0.2.0"
    }
  ],
  "migrations": { "source": [...], "target": [...] },
  "compatibility": {
    "requires_sequential_from": "0.1.0",
    "breaking_changes": ["ApplicationProvider.boot() 签名变更"]
  }
}
```

---

## 五、明确不做的事

| 能力 | 理由 |
|---|---|
| 完整 Symfony DI 容器 | ServiceContainer 只需 bind/singleton/get，目的是统一 PDO，不是重写 IoC |
| Filament 细粒度 render hooks 清单 | PHP Blade 渲染模型，不适合 Vue SPA；Vue named slot 已够用 |
| Nx agentic migration（AI 辅助） | recipe-managed 文件极少（≈6 个），三方合并 + 人工处理冲突已足够 |
| automatic `down()` DDL 回滚 | 恢复依赖已验证备份，不是自动 DDL 回滚；现有设计已正确 |
| 生成后二次生成覆盖 | 生成器已有目标目录非空时拒绝的逻辑，保持 |
| 大范围 recipe-managed 文件（>10个） | managed files 越少，维护成本越低；所有能移入包的内容都应移入包 |

---

## 六、任务计划

### U01：完整应用创建器

**用户可观测结果**：`peanut new <slug>` 生成的项目能直接启动，登录、菜单、权限、路由均正常工作。

**包含**：
- 前端组合根从 stub 升级为真实的 router-view + pageRegistry 入口
- 后端路由从 health/greeting 升级为完整的认证 + 权限中间件链
- 生成 `.peanut/project.json` 和 `.peanut/managed-files.lock`（含 baseline blob hash）
- 生成 `extensions/` 目录骨架
- 修复 `TRUSTED_MENU_ROUTE_CONTRACTS` clientKeys 硬编码问题

**不包含**：Provider DI 接口、三方合并、升级命令

---

### U02：前后端稳定扩展契约

**用户可观测结果**：应用开发者只在 `extensions/` 目录工作，不需要修改任何 `apps/` 入口文件，即可完成页面、布局、后端服务、中间件、Worker 的注册。

**包含**：
- 前端 `createAdminPageRegistry`（sealed 固定组件 + 应用 glob 合并）
- 前端 HTTP interceptor 分阶段固定顺序，应用只能在 Core 阶段之后挂载
- 后端 `ApplicationProvider` 接口 + 薄 `ServiceContainer`
- 后端 `ApplicationContributions` builder（routes、middleware、workers）
- 后端中间件链命名锚点，应用只能 `after` 插入且限定路径前缀

**不包含**：三方合并、升级命令、source migration 执行

---

### U03：完整升级器

**用户可观测结果**：`peanut upgrade plan` 列出所有将要发生的变更（包版本、受管文件、数据库），`apply` 执行后业务代码和数据保持不变，冲突时停止并给出清晰说明。

**包含**：
- `peanut upgrade plan --to <version>` 输出可读计划文件（不改任何文件）
- `peanut upgrade apply <plan>` 执行包升级 + source migration + 三方合并 + 数据库 migration
- 三方合并基于 `git apply -3`（参考 Flex RecipePatcher）
- `managed-files.lock` 仅在全部步骤成功后更新 baseline_blob
- ledger 记录每步状态，中断后可续跑
- `ReleaseManifest` schema 扩展至 v2

---

### U04：统一发布与生命周期演示

**用户可观测结果**：一次可审计的完整演示，从创建新应用到跟随 Peanut 发布升级，并展示冲突停止行为。

**包含**：
- 用 U01 创建器生成一个完整测试应用
- 演示 plan 列出包、文件、数据库三类变更
- 演示 apply 后测试应用业务代码、数据、扩展注册保持不变
- 演示人为制造受管文件冲突时工具停止且不前移 lock
- 发布包含 v2 schema 的 release manifest

---

## 七、待外部评审的开放问题

1. **recipe-managed 文件范围**：预计约 6 个文件（frontend 3 + backend 3）。范围是否足够小？有没有遗漏必须在应用仓存在但应受管理的文件类型？

2. **`baseline_blob` 的可访问性**：升级器需要旧版本包的文件内容来重建 blob。是在包里附带旧基线快照，还是依赖 git history？前者可靠，后者依赖 git 可达性。

3. **无 git 环境降级**：`git apply -3` 要求目标项目是 Git 仓库。如果部署环境没有 git 或在 Windows 上，如何降级（unified diff + patch 命令，或纯 PHP 实现）？

4. **U01/U02 边界**：U01 生成完整标准后台壳，但此时还没有 U02 的 Provider 机制。生成的应用如果要扩展后端，还不能用 Provider 方式。应该如何处理这个间隙？是 U01 生成目录骨架和占位注释，等 U02 后再实际接入？

5. **`manual` source migration 的用户体验**：Provider 接口签名变更时，工具如何让开发者知道需要改哪些文件，同时又不自动修改 `extensions/` 目录？

6. **过度设计风险评估**：为 6 个受管文件建设完整三方合并机制，成本是否过高？有没有更轻量的替代方案（例如只用 unified diff + PHP patch 库，不依赖 git）？
