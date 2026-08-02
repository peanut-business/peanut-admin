# Peanut Admin 应用生命周期架构 v3.0

## 文档状态

- 状态：`design-revised / ready-for-external-review`
- 分析基线：Stage C.2（`69c5b2c271413f6ff741de65437f29e04f975300`）
- 前版本：v2.3（已归档）
- 核心改变：移除了 v2.3 中约三分之二的复杂设计，根本原因是发现了一个更底层的实现决策。

---

## 一、根本前提修正

### v2.3 及之前的隐藏错误假设

Stage C.2 的 `ProjectGenerator.php` 调用 `copyPackageSnapshots()`，将 `packages/php/kernel/src/` 等包的**源码**复制进生成的应用。生成的 `composer.json` 使用 `"type": "path"` 本地仓库，指向 `../packages/php/kernel` 这样依赖相对路径的结构。

这个实现决策导致了 v2.3 中所有关于 recipe artifact（含 base64 文件内容）、bundle tarball 离线安装协议、"只更新 Peanut 依赖条目"等复杂设计——因为需要追踪包源码的版本变化和内容差异。

### v3.0 的正确前提

**Peanut 包以版本化制品发布，下游应用通过标准包管理器安装。**

生成的应用 `composer.json` 写版本约束：

```json
{
  "require": {
    "peanut-admin/kernel": "^0.2.0",
    "peanut-admin/settings": "^0.2.0"
  },
  "repositories": [
    { "type": "composer", "url": "https://npm.pkg.github.com" }
  ]
}
```

应用仓里**没有 Peanut 包源码**。升级时标准 `composer update peanut-admin/*`，不需要任何自定义合并逻辑。

### Peanut 内部开发不受影响

Peanut 自己的 monorepo 继续用 path 仓库开发和测试。标准后台本身就是最好的集成测试——包开发完全可以通过标准后台验证，不依赖下游应用（DCS 等）参与 Peanut 的开发环节。

### 包发布渠道

- **PHP 包**：GitHub Packages Composer registry（私有，认证后可 `composer require`）
- **Web 包**：GitHub Packages npm registry（私有，`@peanut-admin/*` scope）
- **v1 无需 Satis 或公开 registry**，GitHub Packages 已足够支持私有团队使用

---

## 二、应用内容分类（简化后）

| 类型 | 定义 | 升级行为 |
|---|---|---|
| **package-owned** | Peanut 包，在 vendor / node_modules 中 | `composer update peanut-admin/*` / `pnpm update @peanut-admin/*` |
| **recipe-managed** | 极少数必须在应用仓存在的组合根文件（≤3 个） | 未修改则直接替换；已修改则阻断 |
| **application-owned** | 业务代码、页面、扩展、配置 | 升级器永不触碰 |
| **deployment-owned** | secrets、`.env`、生产数据库 | 不进 Git，不进升级流程 |

`package-manifest`（`composer.json`、`package.json`、lockfile）不是独立类型——它们是应用仓的一部分，升级时被标准包管理器正常更新。

---

## 三、Recipe-managed 文件（精确清单）

只有以下文件需要 Peanut 长期维护，且必须存在于应用仓：

| 文件 | 原因不能进包 | 策略 |
|---|---|---|
| `apps/frontend/src/main.ts` | 需要在应用编译时组合扩展，Vite 不支持从包动态加载 | replace-if-pristine |
| `apps/frontend/src/app/extension-registry.ts` | glob `extensions/frontend/*/extension.ts`，路径相对于应用仓 | replace-if-pristine |
| `apps/backend/bootstrap/app.php` | ThinkPHP 入口路径相对于应用仓，Provider 列表需在此组合 | replace-if-pristine |

以上三个文件是**完整清单**，不是示例。每次 Peanut 发布前验证这个清单仍然准确。其他文件若需进入此清单，必须先证明确实无法放入包。

### `managed-files.lock`（精简结构）

```json
{
  "schema_version": 1,
  "peanut_version": "0.1.0",
  "files": {
    "apps/frontend/src/main.ts": {
      "base_digest": "sha256:d4e5f6...",
      "app_override": false
    },
    "apps/frontend/src/app/extension-registry.ts": {
      "base_digest": "sha256:e5f6g7...",
      "app_override": false
    },
    "apps/backend/bootstrap/app.php": {
      "base_digest": "sha256:f6g7h8...",
      "app_override": false
    }
  }
}
```

`base_digest` = `sha256(file_contents)`，不依赖 Git。无 recipe artifact，无 bundle_digest，无 recipe_artifact_digest。

### 状态矩阵（replace-if-pristine）

| 当前文件 digest | `app_override` | 行为 |
|---|---|---|
| = `base_digest` | false | 直接替换 |
| ≠ `base_digest` | false | 阻断，要求还原或声明 override |
| 任意 | true | 保留当前，新版写入 `<path>.peanut-new` |
| 文件缺失 | 任意 | 阻断 |

---

## 四、前端扩展：Extension Manifest

应用通过显式 manifest 注册页面，不 glob 所有 `.vue` 文件：

```ts
// extensions/frontend/src/extension.ts（application-owned）
export default defineAdminExtension({
  id: 'myapp',
  pages: {
    'myapp.case.list': () => import('./pages/CaseListPage.vue'),
  },
  layouts: {
    'myapp.workspace': () => import('./layouts/DcsWorkspaceLayout.vue'),
  },
  appearances: {
    'peanut.auth.login.shell': () => import('./appearances/MyLoginShell.vue'),
  },
})
```

组合根（recipe-managed）glob manifest 文件：

```ts
// apps/frontend/src/app/extension-registry.ts（recipe-managed）
import { createAdminPageRegistry } from '@peanut-admin/admin-core'
import { TenantLoginPage, WorkspaceLayout, StatusPage } from '@peanut-admin/admin-shell'

const extensions = import.meta.glob(
  '../../../extensions/frontend/*/extension.ts',
  { eager: true }
)

export const pageRegistry = createAdminPageRegistry({
  sealed: {
    'peanut.layout.workspace': WorkspaceLayout,
    'peanut.page.tenant.login': TenantLoginPage,
    'peanut.page.status': StatusPage,
  },
  extensions,
})
```

---

## 五、后端扩展：Provider

应用注册自己的服务、路由、中间件，不修改 Peanut 包文件：

```php
// extensions/backend/Providers/AppServiceProvider.php（application-owned）
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

`ServiceRegistry` 包装 ThinkPHP 容器，不独立实现 IoC。中间件绑定到 module/route-group，不用字符串路径。

---

## 六、升级流程（三段命令）

### `peanut upgrade plan --to <version>`（开发机，只读）

读取目标版本的 ReleaseManifest，检查：
- 目标版本的 Peanut 包是否可达（registry 有此版本）
- 三个 recipe 文件的 `base_digest` 是否与当前文件一致（预判哪些会阻断）
- 有哪些数据库 migration 会执行
- 有哪些 breaking change 需要人工处理

输出 `.peanut/plans/upgrade-<version>.json`：

```json
{
  "schema_version": 1,
  "peanut_release_id": "0.2.0",
  "peanut_version_from": "0.1.0",
  "application_managed_files_digest": "sha256:...",
  "packages": {
    "php": { "peanut-admin/kernel": "0.1.0 → 0.2.0" },
    "web": { "@peanut-admin/admin-core": "0.1.0 → 0.2.0" }
  },
  "recipe_files": [
    {
      "path": "apps/backend/bootstrap/app.php",
      "action": "replace",
      "app_override": false
    }
  ],
  "database_migrations": ["peanut.kernel:20260724000001_..."],
  "breaking_changes": [
    {
      "id": "provider-boot-signature",
      "description": "ApplicationProvider.boot() 参数类型变更，需手工更新 extensions/backend/Providers/ 下的实现。"
    }
  ],
  "requires_sequential_from": "0.1.0"
}
```

Plan 文件随代码 commit 进入 Git。sidecar `upgrade-<version>.json.sha256` 记录文件摘要，与 plan 内容分离。

### `peanut upgrade code-apply <plan-file>`（开发机/CI，隔离 worktree）

1. 核验 plan sidecar digest、`application_managed_files_digest` 与当前 `managed-files.lock` 一致（stale plan 检测）
2. 确认所有 breaking changes 已有对应 Git commit（或显式 resolution record）
3. `composer update peanut-admin/*` + `pnpm update "@peanut-admin/*"`
4. 对三个 recipe 文件执行状态矩阵检查和替换
5. 更新 `managed-files.lock` 中的 `peanut_version` 和各文件 `base_digest`
6. 输出报告

Git worktree 是恢复边界，不建设代码 ledger。

### `peanut upgrade db-apply <plan-file>`（部署环境）

1. 核验 plan sidecar digest、`managed-files.lock` 的 `peanut_version` 与 plan 一致（确保 code-apply 已完成）
2. 核验备份证据
3. MySQL advisory lock
4. 执行 Peanut-owned migration（遇非 Peanut 表立即拒绝）
5. 逐项 ledger 记录状态和 checksum
6. 同步权限、菜单定义

现有 `UpgradeWorkflow` 逻辑基本保留，绑定 `peanut_release_id` 核验。

---

## 七、Breaking change 处理

plan 输出的 `breaking_changes` 仅供参考，不自动阻断 code-apply。阻断条件是开发者**尚未处理**：

检测方式：code-apply 检查当前 worktree 中是否有任何在 plan 生成后修改了 `extensions/` 下文件的 commit（`git log --after=<plan_generated_at> extensions/`）。有则认为已处理，无则提示并建议处理后再 apply。

这不是机器验证，但比空洞的 ack 更有意义。对于确实无需修改的 breaking change，开发者提交一个空 commit（`git commit --allow-empty -m "reviewed: provider-boot-signature N/A"`）即可跳过。

---

## 八、旧项目引导（Adoption）

Stage C.2 的应用没有 `managed-files.lock`。`peanut-project.json` 中记录了 `input_commit`，可直接映射到对应的 Peanut release：

```bash
peanut upgrade adopt [--accept-override <path>]...
```

1. 从 `peanut-project.json` 读取 `input_commit`，从 GitHub release 元数据映射到 release version
2. 下载对应版本的三个 recipe 文件（从 GitHub Packages 或本地缓存）
3. 逐文件对比 digest：
   - 一致：登记 `app_override: false`
   - 不同：阻断，等待 `--accept-override <path>`
4. 写入 `managed-files.lock`

不自动覆盖文件。artifact 不可达时输出当前文件 digest 和人工说明，不声称有 old-baseline。

---

## 九、生成的应用页面边界（U02）

### A. 固定核心页（从包导入）

**Tenant**：登录、租户选择、工作台、账号信息、成员管理、成员有效访问预览、角色管理、部门管理、权限治理工作台、模块管理、审计日志

**Platform**：平台登录、平台工作台、租户管理、租户详情、平台操作员、平台角色、平台权限治理工作台、平台审计、版本与升级状态、运维控制台

**状态页**：403、404、服务不可用

（精确对应 `frontend/src/app/router.ts` 固定路由，不含 example routes）

### B. 按 `--feature` 参数启用的 Peanut 功能页

Settings、Reference Codes、File/Media、Task/Job、Notification/SMS、Import/Export、Integration Security（各一页）

### C. 可删除示例页（Demo Module）

`example.greeting`、`example-target`、`example-reference`、`example-work-item`，README 说明删除方式。

---

## 十、明确不做的事

| 能力 | 理由 |
|---|---|
| 复制 Peanut 包源码到应用仓 | 包版本化后不再需要 |
| Recipe artifact（含 base64 内容） | 包版本化后 recipe 文件由版本化包间接决定，artifact 概念消失 |
| Bundle tarball 离线安装协议 | 标准 GitHub Packages 已支持，无需自定义 bundle |
| `bundle_digest` / `recipe_artifact_digest` | 概念消失 |
| 通用文本三方合并 | recipe 文件用 replace-if-pristine，不合并 |
| 代码阶段跨进程 ledger | Git worktree 是恢复边界 |
| 独立 IoC 容器 | 包装 ThinkPHP 容器 |
| ReleaseManifest 手工填写 | tooling 生成 |
| 全目录 page glob | extension manifest |
| 字符串路径 middleware scope | module/route-group ownership |

---

## 十一、任务顺序

### U01：包发布与扩展契约

**最重要的一步，其余所有任务依赖它。**

包含：
- 将 Peanut PHP/Web 包发布到 GitHub Packages（版本化，可 `composer require`）
- 修改 `ProjectGenerator.php`：删除 `copyPackageSnapshots()`，改为写入版本约束
- 固定 extension manifest 契约（`defineAdminExtension`）
- 固定 `ApplicationProvider` 接口和中间件链
- 固定 `managed-files.lock` schema v1（精简版）
- 确认三个 recipe 文件的精确清单

完成条件：`composer require peanut-admin/kernel:^0.2.0` 可以从 GitHub Packages 安装。

### U02：完整应用创建器

`peanut new <slug>` 生成完整标准后台（九节三类页面）、安装 Peanut 包（不复制源码）、生成 `managed-files.lock`、生成 extensions/ 骨架。同时实现 `peanut upgrade adopt`。

完成条件：生成的应用不含 Peanut 源码，`composer install` 从 registry 安装。

### U03：升级器

`peanut upgrade plan`、`peanut upgrade code-apply`，更新 ReleaseManifest schema。

完成条件：从 0.1.0 升到 0.2.0 可执行，recipe 文件变化被正确处理，breaking change 有提示。

### U04：数据库部署与演示

`peanut upgrade db-apply` 绑定 release_identity，完整生命周期演示。

完成条件：完整走通创建→代码升级→数据库部署，扩展和业务数据不变。
