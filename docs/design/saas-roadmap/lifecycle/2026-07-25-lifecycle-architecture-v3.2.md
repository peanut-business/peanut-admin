# Peanut Admin 应用生命周期架构 v3.2

## 文档状态

- 状态：`design-revised / ready-for-codex-review`
- 分析基线：Stage C.2（`69c5b2c271413f6ff741de65437f29e04f975300`）
- 前版本：v3.1

---

## 一、总览（普通语言版本）

Peanut Admin 是一个**企业后台脚手架**。用 `peanut new myapp` 创建应用后，应用仓只包含三类内容：

1. **应用自己的业务代码**（Peanut 永不修改）
2. **少量脚手架骨架文件**（由 Peanut 生成，升级时安全更新）
3. **对 Peanut 包的依赖声明**（`composer.json` 和 `package.json` 里的版本约束）

Peanut 的核心能力（认证、权限、菜单、文件、任务等）以**独立版本化包**的形式交付，通过标准 `composer require` / `pnpm add` 安装，不复制源码进应用仓。

升级时：
- `peanut upgrade plan` 展示将要发生的变化
- `peanut upgrade code-apply` 更新包版本 + 处理少量骨架文件变化
- `peanut upgrade db-apply` 在生产部署时执行数据库迁移

---

## 二、PHP 多包发布拓扑

### 现状与问题

当前 Stage C.2 的 monorepo 结构中，`packages/php/*/` 已经是独立包（各有 `composer.json`），`starter/backend/src/Modules/Peanut/*/` 是对应模块的连接层（ModuleProvider、module.json、Resources、Migrations）——这些连接层被复制进了应用仓，这是需要解决的根本问题。

### 目标结构

每个 Peanut PHP 包（`peanut-admin/kernel`、`peanut-admin/settings` 等）的最终形态：

```
packages/php/settings/
  composer.json           ← 包元数据，name: peanut-admin/settings
  src/                    ← 业务逻辑（已有）
  Module/
    ModuleProvider.php    ← 从 starter/backend/src/Modules/Peanut/Settings/ 移入
    module.json           ← 从 starter 移入
    Resources/
      menus.json
      permissions.json
      setting-definitions.json
    Database/
      Migrations/
        *.php             ← 从 starter 移入
```

### 发布方式：Split Repository + Packagist

采用 Symfony Components 的成熟模式：

1. **开发**：在 monorepo（`peanut-opensource/peanut-admin`）中统一开发和 PR
2. **发布**：使用 `symplify/monorepo-builder` 的 `split` 命令，在 CI 中自动为每个 `packages/php/<name>` 生成只读镜像仓库
3. **安装**：消费应用从 Packagist 安装，`composer require peanut-admin/settings:^0.2.0`，不需要自定义 repositories

镜像仓库只分发不接受 PR，命名约定：`peanut-admin/<name>`（GitHub 组织下的只读仓库）。

**`monorepo-builder` 配置示例**（`monorepo-builder.php`）：

```php
return MonorepoBuilderConfig::configure()
    ->withPackageDirectories(['packages/php/*'])
    ->withWorkers([
        SortRequirementsWorker::class,
    ]);
```

Split 和发布流程由 GitHub Actions CI 在打 monorepo tag 时自动触发。

### 版本策略

所有 Peanut PHP 包共享同一版本号（与 `@delon/*` 相同策略），降低兼容矩阵复杂度。例如 `peanut-admin/kernel:0.2.0` 与 `peanut-admin/settings:0.2.0` 永远是同一 release train 的产物。

### 认证

- 公开包：Packagist（无需认证）
- CI：无需特殊配置（public Packagist）
- 未来私有包：Private Packagist 或 Satis，不影响公开核心包

### 消费端示例

```json
{
  "require": {
    "peanut-admin/kernel": "^0.2.0",
    "peanut-admin/settings": "^0.2.0"
  }
}
```

无需自定义 `repositories`，标准 `composer install`。

---

## 三、Web 多包发布拓扑

`packages/web/*/` 各自产出 `@peanut-admin/<name>` npm 包，发布到 npmjs.com（公开项目）。

发布流程参考 `@delon/*`：monorepo 中统一构建，CI 逐包执行 `npm publish`。

**版本策略**：与 PHP 包共享同一版本号。

**认证**：npmjs.com 公开包无需认证安装。CI 发布使用 `NPM_TOKEN` secret。

**消费端示例**：

```json
{
  "dependencies": {
    "@peanut-admin/admin-core": "^0.2.0",
    "@peanut-admin/admin-shell": "^0.2.0"
  }
}
```

标准 `pnpm install`，无需私有 registry 配置。

---

## 四、Module Descriptor：Package-Relative 契约

### 问题

`ModuleRegistryFactory` 当前读取 `backend/config/modules.php` 中的 `roots` 列表，每个 root 是相对于应用仓根的目录路径（如 `backend/src/Modules/Peanut/Settings`）。这要求 Peanut 模块目录存在于应用仓。

### 解决方案

每个 Peanut Composer 包在自己的 `Module/` 目录下包含 Module descriptor：

```
vendor/peanut-admin/settings/
  Module/
    module.json           ← {schema_version, key, version, ...}
    ModuleProvider.php    ← implements PeanutModuleProvider
    Resources/
    Database/Migrations/
```

`ModuleRegistryFactory` 通过 `InstalledVersions::getInstallPath()` 定位每个 Peanut 包的安装路径，从 `<install_path>/Module/module.json` 加载 descriptor，不再依赖 `backend/config/modules.php` 的 `roots` 数组。

**`modules.php` 简化后**：

```php
return [
    // Peanut 包模块由安装包自动发现，不需要手动列 roots
    'application_modules' => [
        // 应用自己的业务模块路径，如 backend/src/Modules/MyApp/
    ],
    'registered_client_keys' => ['admin-web', 'platform-web'],
];
```

`frontend_components` 从 Web 包的 manifest 自动读取，不在 PHP 配置中硬编码。

### Module descriptor schema（`module.json`）

```json
{
  "schema_version": 2,
  "key": "peanut.settings",
  "version": "0.2.0",
  "backend": {
    "provider": "PeanutAdmin\\Settings\\Module\\ModuleProvider",
    "migrations": "Database/Migrations"
  },
  "frontend": {
    "component_key": "peanut.settings.page"
  },
  "resources": {
    "menus": "Resources/menus.json",
    "permissions": "Resources/permissions.json"
  }
}
```

所有路径相对于 `module.json` 所在目录（即包安装目录下的 `Module/`）。

---

## 五、生成文件完整分类（基于 Stage C.2 starter/）

### 分类说明

| 类型 | 说明 |
|---|---|
| `moves-to-package` | 移入 Composer/npm 包，应用仓不再有这些文件 |
| `scaffold-static` | 所有应用同版本内容相同，release manifest 记录 digest，replace-if-pristine |
| `scaffold-rendered` | 包含应用特定值（slug、brand、clients），每应用内容不同，lock 记录 template + render input digest |
| `package-managed` | 由 Composer/pnpm 管理，code-apply 时由包管理器更新，不做文本替换 |
| `seed-once` | 创建时写入，之后完全归应用，升级器只报告模板变化 |
| `demo-module` | 可删除示例代码 |
| `application-owned` | 业务代码，永不触碰 |

### 完整分类表

| 路径/模式 | 分类 | Upgrade Policy | 说明 |
|---|---|---|---|
| `backend/src/Modules/Peanut/*/` | `moves-to-package` | 包安装后删除 | ModuleProvider、module.json、Resources、Migrations 移入各包 |
| `backend/src/Auth/TenantAuthRuntimeFactory.php` | `moves-to-package` | 包安装后删除 | 移入 kernel 包 |
| `backend/src/FileMedia/FileMediaStorageFactory.php` | `moves-to-package` | 包安装后删除 | 移入 file-media 包 |
| `backend/src/Module/ModuleRegistryFactory.php` 等 | `moves-to-package` | 包安装后删除 | 移入 kernel 包，接受 package-relative root |
| `frontend/src/modules/peanut-*.ts` | `moves-to-package` | 包安装后删除 | 移入各 Web 包作为包内 host wiring |
| `backend/route/app.php` | `scaffold-static` | replace-if-pristine | ProjectGenerator 按 features 生成，所有应用此文件内容相同（features 相同时） |
| `backend/app/provider.php` | `scaffold-static` | replace-if-pristine | 只注册 ExceptionHandler，内容固定 |
| `backend/config/modules.php` | `scaffold-rendered` | render-if-pristine | 含 client keys，按 render input 更新 |
| `backend/config/auth.php` | `scaffold-rendered` | render-if-pristine | 含 tenant client 定义 |
| `frontend/src/main.ts` | `scaffold-static` | replace-if-pristine | 应用入口，内容固定 |
| `frontend/src/App.vue` | `scaffold-static` | replace-if-pristine | 当前 starter App.vue 不含应用特定值 |
| `frontend/src/app/modules.ts` | `scaffold-rendered` | render-if-pristine | 含 feature 模块导入列表，按 features 生成 |
| `frontend/src/clients.ts` | `scaffold-rendered` | render-if-pristine | 含 tenant client keys 和 API prefix |
| `backend/config/app.php` 等其他 config | `seed-once` | report-only | 创建后归应用 |
| `backend/public/index.php`、`router.php` | `seed-once` | report-only | ThinkPHP 入口 |
| `frontend/vite.config.ts`、`tsconfig.json` | `seed-once` | report-only | 构建配置 |
| `frontend/index.html` | `scaffold-rendered` | render-if-pristine | 含 `<title>` 应用名 |
| `pnpm-workspace.yaml` | `seed-once` | report-only | workspace 配置 |
| 根 `package.json` | `seed-once` + `package-managed`（Peanut 依赖部分） | scripts 等 seed-once，`@peanut-admin/*` 版本由 code-apply 更新 | 混合所有权 |
| `backend/composer.json` | `seed-once` + `package-managed`（Peanut 依赖部分） | `peanut-admin/*` 版本由 code-apply 更新 | 混合所有权 |
| `backend/composer.lock` | `package-managed` | code-apply 后由 Composer 重新生成 | 不做文本替换 |
| `pnpm-lock.yaml` | `package-managed` | code-apply 后由 pnpm 重新生成 | 不做文本替换 |
| `backend/src/StarterExceptionHandler.php` | `seed-once` | report-only | 应用级异常处理，创建后归应用 |
| `frontend/src/style.css` | `seed-once` | report-only | 应用级样式 |
| `frontend/src/env.d.ts` | `seed-once` | report-only | 类型声明 |
| `backend/tests/*`、`frontend/tests/*` | `scaffold-rendered` | render-if-pristine | 按 features 生成的验证测试 |
| `.env.example` | `seed-once` | report-only | 环境变量样板 |
| `.gitignore`、`README.md` | `seed-once` | report-only | 项目描述文件 |
| `backend/src/Modules/Example/` | `demo-module` | 可删除 | 示例模块 |
| `frontend/src/modules/example-greeting/` | `demo-module` | 可删除 | 示例模块 |
| `extensions/`（创建时骨架） | `application-owned` | 永不修改 | 应用扩展目录 |
| `domain/` | `application-owned` | 永不修改 | 应用业务代码 |

---

## 六、Scaffold Lock 与 Release Manifest

### `scaffold.lock`（应用侧）

```json
{
  "schema_version": 1,
  "peanut_version": "0.1.0",
  "release_manifest_digest": "sha256:...",
  "files": {
    "backend/route/app.php": {
      "policy": "replace-if-pristine",
      "base_digest": "sha256:..."
    },
    "backend/config/modules.php": {
      "policy": "render-if-pristine",
      "template_id": "backend/config/modules.php.tpl",
      "template_version": "0.1.0",
      "render_input_digest": "sha256:...",
      "rendered_base_digest": "sha256:..."
    },
    "frontend/src/clients.ts": {
      "policy": "render-if-pristine",
      "template_id": "frontend/src/clients.ts.tpl",
      "template_version": "0.1.0",
      "render_input_digest": "sha256:...",
      "rendered_base_digest": "sha256:..."
    }
  }
}
```

### Release Manifest（Peanut 侧，随 monorepo tag 生成）

```json
{
  "schema_version": 1,
  "peanut_version": "0.2.0",
  "packages": {
    "php": {
      "peanut-admin/kernel": {"version": "0.2.0", "packagist_dist": "sha256:..."},
      "peanut-admin/settings": {"version": "0.2.0", "packagist_dist": "sha256:..."}
    },
    "web": {
      "@peanut-admin/admin-core": {"version": "0.2.0", "npm_integrity": "sha512-..."},
      "@peanut-admin/admin-shell": {"version": "0.2.0", "npm_integrity": "sha512-..."}
    }
  },
  "scaffold": {
    "artifact_digest": "sha256:...",
    "files": {
      "backend/route/app.php": {"policy": "replace-if-pristine", "content_digest": "sha256:..."},
      "backend/config/modules.php": {"policy": "render-if-pristine", "template_digest": "sha256:..."}
    },
    "renames": [],
    "deletes": []
  },
  "database_migrations": ["peanut.settings:20260724000001_..."],
  "compatibility": {
    "requires_sequential_from": "0.1.0",
    "breaking_changes": []
  }
}
```

Scaffold 文件的**真实内容**从 Packagist/npm 安装的 scaffold package（`peanut-admin/scaffold`）中取得，不塞进 JSON。

---

## 七、Plan / Code-Apply / DB-Apply 身份绑定

### Plan 文件（`.peanut/plans/upgrade-0.2.0.json`）

```json
{
  "schema_version": 1,
  "peanut_version_from": "0.1.0",
  "peanut_version_to": "0.2.0",
  "release_manifest_digest": "sha256:...",
  "application_identity": {
    "scaffold_lock_digest": "sha256:...",
    "composer_lock_digest": "sha256:...",
    "pnpm_lock_digest": "sha256:..."
  },
  "scaffold_actions": [...],
  "package_updates": {...},
  "database_migrations": [...],
  "breaking_changes": [...]
}
```

Plan sidecar：`upgrade-0.2.0.json.sha256`

### Stale plan 检测

`code-apply` 启动时比较 `application_identity.scaffold_lock_digest` 与当前 `scaffold.lock` 文件摘要。不一致则报告 stale plan，要求重新 `plan`。

### DB-Apply 核验

`db-apply` 检查 `scaffold.lock.peanut_version` 与 plan 的 `peanut_version_to` 一致，且 `scaffold.lock.release_manifest_digest` 与 plan 中 `release_manifest_digest` 一致。这证明 code-apply 已完成且使用了相同 release。

---

## 八、Upgrade 命令执行流程

### `peanut upgrade plan --to <version>`

1. 下载目标版本 release manifest（从 Packagist 获取 `peanut-admin/scaffold` 包元数据）
2. 分析 scaffold_actions（对比当前 scaffold.lock 的 base/rendered digest）
3. 预览包版本变化
4. 检查 breaking changes
5. 写出 plan 文件 + sidecar

不修改任何文件，不运行 Composer/pnpm。

### `peanut upgrade code-apply <plan>`（隔离 Git worktree）

1. 核验 plan sidecar digest
2. 检查 stale plan
3. 检查 breaking change resolution records（见第九节）
4. `composer update peanut-admin/*`（从 Packagist 拉新版）
5. `pnpm update "@peanut-admin/*"`
6. 处理 scaffold files：
   - `replace-if-pristine`：digest 相同则替换，不同且无 override 则阻断
   - `render-if-pristine`：rendered digest 相同则重新渲染新模板，不同则阻断
   - override 文件：保留当前，新版写入 `<path>.peanut-new.<version>`
7. 更新 `scaffold.lock`
8. 输出升级报告

### `peanut upgrade db-apply <plan>`（部署环境）

核验 scaffold.lock 状态、备份证据，然后执行现有数据库升级逻辑（MySQL lock + migration ledger）。

---

## 九、Breaking Change Resolution

```json
// .peanut/resolutions/provider-boot-signature.json
{
  "action_id": "provider-boot-signature",
  "plan_digest": "sha256:...",
  "resolved_commit": "abc123def",
  "changed_paths": ["extensions/backend/Providers/AppServiceProvider.php"],
  "note": "更新了 boot() 签名"
}
```

`code-apply` 验证：
1. `plan_digest` 与当前 plan sidecar 一致
2. `resolved_commit` 在当前 Git 图中存在
3. `changed_paths` 中至少一个文件在该 commit 中被修改

对无法机器验证的 breaking change（如"已确认不影响本应用"），允许 `resolved_commit: null` + 必填 `acknowledged_reason`，不允许完全空白。

---

## 十、Adoption（旧应用引导）

针对 Stage C.2 及更早创建的应用：

```bash
peanut upgrade adopt [--accept-override <path>]... [--migrate-snapshots]
```

**步骤**：

1. 从 `peanut-project.json` 读取 `input_commit`，映射到 Peanut release version（通过 `RELEASES.json`）
2. 取得对应版本的 scaffold manifest
3. 分类每个文件：
   - `moves-to-package` 类型且 digest 与 release 一致：标记 pristine，待 code-apply 后由包管理器替代
   - `moves-to-package` 类型但 digest 不同：**阻断**，输出差异，要求 `--accept-override <path>`（声明为 application-owned fork）或完成手工迁移到 `extensions/`
   - scaffold 文件：正常 adopt 流程
4. 写入 `scaffold.lock`

**`--migrate-snapshots` flag**：只生成报告和迁移指导，不自动修改文件。实际迁移由开发者手工完成，完成后通过 resolution record 记录证据。

---

## 十一、任务顺序

### U01：包结构重构与发布（最高优先级）

**输入**：Stage C.2 固定基线

**交付**：
1. 将 `starter/backend/src/Modules/Peanut/*/` 的 `ModuleProvider.php`、`module.json`、`Resources/`、`Database/Migrations/` 移入各 Composer 包的 `Module/` 目录
2. 将 `frontend/src/modules/peanut-*.ts` 移入各 Web 包
3. 建立 split repository CI（`symplify/monorepo-builder` + GitHub Actions）
4. 发布第一个版本到 Packagist + npmjs.com
5. 修改 `ModuleRegistryFactory` 使用 package-relative discovery
6. 固定 module.json schema v2
7. 发布 scaffold package（`peanut-admin/scaffold`，含 scaffold manifest 和模板文件）
8. 固定 `scaffold.lock` schema v1

**完成条件**：`composer require peanut-admin/kernel:^0.2.0` 从 Packagist 安装，不依赖相对路径。

**禁止**：修改应用业务逻辑、运行 aggregate 测试、移动 DCS consumption lock。

### U02：完整应用创建器

**输入**：U01 产出的包版本

**交付**：
- `peanut new <slug>` 生成应用（包从 registry 安装，不复制源码）
- 生成 `scaffold.lock`
- 生成 `extensions/` 骨架
- 实现 `peanut upgrade adopt`

**完成条件**：生成的应用 `composer install` 从 Packagist 安装，vendor 里有 Peanut 模块。

### U03：升级器

**输入**：U01 的 scaffold manifest 格式、U02 的 scaffold.lock

**交付**：
- `peanut upgrade plan`、`peanut upgrade code-apply`
- scaffold replace-if-pristine 和 render-if-pristine 处理
- breaking change resolution record
- plan 身份绑定

**完成条件**：0.1.0 → 0.2.0 升级可执行，scaffold 变化正确处理。

### U04：数据库部署与演示

**交付**：`peanut upgrade db-apply` 绑定 release identity，完整演示。

---

## 十二、发布一致性与部分失败处理

**发布顺序**：先发布无依赖的叶子包（`kernel`、`data-permission`），再发布依赖它们的包。CI 脚本按依赖拓扑排序。

**部分失败**：某包发布失败时，CI 停止，不发布剩余包。已发布的包版本不撤销，但该版本的 release manifest 不生成（没有 manifest 则消费应用不会消费半发布状态）。修复后重新发布失败的包及后续包。

**Release identity**：release manifest 在所有包发布成功后生成，包含每个包的 Packagist dist URL 和 sha256 integrity。

---

## 十三、明确不做的事

| 能力 | 理由 |
|---|---|
| PHP/TypeScript AST structured-merge | 引入 parser 和格式保持成本；通过所有权拆分消除需求 |
| 通用文本三方合并 | 不在 v1；scaffold 文件用 replace/render-if-pristine 已足够 |
| base64 内容塞进 JSON manifest | 文件内容从 scaffold package 取得 |
| 跨进程代码 ledger | Git worktree 是恢复边界 |
| 自制包安装器 | 使用 Composer 和 pnpm |
| 全目录 page glob | extension manifest 显式注册 |
| v1 完全离线支持 | 接受 Packagist/npmjs.com 可达为前提 |
| 独立 IoC 容器 | 包装 ThinkPHP 容器 |
