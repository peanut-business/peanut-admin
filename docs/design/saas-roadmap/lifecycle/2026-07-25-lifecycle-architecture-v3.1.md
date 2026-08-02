# Peanut Admin 应用生命周期架构 v3.1

## 文档状态

- 状态：`design-revised / ready-for-external-review`
- 分析基线：Stage C.2（`69c5b2c271413f6ff741de65437f29e04f975300`）
- 前版本：v3.0
- 修订依据：基于 Stage C.2 `starter/` 实际文件树盘点，修正了三处错误：
  1. v3.0 的"三个 recipe-managed 文件"与实际生成树不符，需重新分类
  2. GitHub Packages 不支持 Composer registry，PHP 包发布需改用 VCS/Satis
  3. 用户直接修改生成文件是正常行为，升级机制必须处理，不能假设只走扩展点

---

## 一、核心思路（先讲清楚再讲细节）

### 两个独立问题，必须分开处理

**问题 A：Peanut 模块代码在应用仓里以源码快照存在**

Stage C.2 `starter/backend/src/Modules/Peanut/*/`（约 40 个文件）是 Peanut 功能模块的完整 PHP 源码，被直接复制进了生成的应用。它们本质上是**包源码**，应该在 Composer 包里，不应该在应用仓里。同理，`frontend/src/modules/peanut-*.ts` 是 Peanut Web 包的主机包装，也应属于对应的 Web 包。

**解决方案**：U01 的首要任务是把这些内容移入正式 Composer/pnpm 包并发布，创建器不再复制它们。这解决了 v2.x 所有"受管文件内容取得"的复杂性。

**问题 B：剩余的脚手架骨架文件（~10 个）仍然需要管理**

即使 Peanut 模块代码全进了包，应用仓里仍有少量必须由 Peanut 生成、且随版本演进的组合根文件。这些文件用户可能会修改，升级时需要安全处理。

---

## 二、Stage C.2 生成文件完整分类

基于真实 `starter/` 文件树，按升级行为分类：

### A. 转移到包（U01 完成后消失于应用仓）

这些内容是 Peanut 功能代码，不应复制进应用仓：

| 路径模式 | 当前处理 | 目标 |
|---|---|---|
| `backend/src/Modules/Peanut/*/` | 源码快照复制 | 进入各 Composer 包的 src/ |
| `backend/src/Auth/TenantAuthRuntimeFactory.php` | 复制 | 进入 kernel 包 |
| `backend/src/FileMedia/FileMediaStorageFactory.php` | 复制 | 进入 file-media 包 |
| `backend/src/Module/ModuleRegistryFactory.php` 等 | 复制 | 进入 kernel 包 |
| `frontend/src/modules/peanut-*.ts` | 复制 | 进入各 Web 包 |

### B. 脚手架骨架（scaffold-managed，~10 个文件）

必须存在于应用仓，Peanut 持续管理，升级时按策略处理：

| 文件 | 升级策略 | 说明 |
|---|---|---|
| `backend/route/app.php` | replace-if-pristine | 路由入口，ProjectGenerator 按 features 生成 |
| `backend/app/provider.php` | replace-if-pristine | 仅注册异常处理器，极少改动 |
| `backend/config/modules.php` | structured-merge | 模块列表 JSON，按字段合并（Peanut 模块可自动更新） |
| `backend/config/auth.php` | replace-if-pristine | Tenant client 配置，ProjectGenerator 生成 |
| `frontend/src/main.ts` | replace-if-pristine | App 入口，极简，极少需改 |
| `frontend/src/App.vue` | replace-if-pristine | 应用根组件 |
| `frontend/src/app/modules.ts` | structured-merge | feature 模块组合，ProjectGenerator 生成，按 import 块合并 |
| `frontend/src/clients.ts` | replace-if-pristine | Tenant client 定义，ProjectGenerator 生成 |
| `backend/composer.json`（Peanut 依赖部分） | structured-merge | 只更新 `peanut-admin/*` 版本约束，不改其他 |
| `frontend/package.json`（Peanut 依赖部分） | structured-merge | 只更新 `@peanut-admin/*` 版本约束 |

### C. 种子文件（seed-once，生成后归应用）

创建时写入，之后完全归应用所有，升级器只在报告里提示"模板已更新"：

- `backend/config/app.php`、`cache.php`、`route.php`
- `backend/config/file-media.php`、`notification-sms.php`、`integration-security.php`
- `backend/public/index.php`、`router.php`
- `frontend/vite.config.ts`、`tsconfig.json`、`index.html`
- `pnpm-workspace.yaml`、根 `package.json`（非 Peanut 依赖部分）
- `.env.example`、`.gitignore`、`README.md`
- 前后端 lockfile（`composer.lock`、`pnpm-lock.yaml`）

### D. 示例代码（demo-module，可删除）

- `backend/src/Modules/Example/Greeting/`
- `frontend/src/modules/example-greeting/`

README 说明删除方式。不进入升级管理。

### E. 应用业务代码（application-owned）

升级器永不触碰。

---

## 三、升级策略说明

### replace-if-pristine

```
当前 digest == base_digest → 直接替换为新版
当前 digest != base_digest，app_override: false → 阻断，输出差异报告
当前 digest != base_digest，app_override: true → 保留当前，新版写入 <path>.peanut-new
文件缺失 → 阻断
```

适用于：内容极简、用户几乎没有合理理由修改的组合根文件（`main.ts`、`App.vue`、`provider.php` 等）。

### structured-merge

解析 JSON/PHP 数组/TypeScript import 结构，只修改 Peanut 拥有的字段，保留应用添加的内容。

- `modules.php`：Peanut 的模块列表可更新，应用添加的自定义模块保留
- `modules.ts`：Peanut 功能的 import 和工厂调用可更新，应用添加的 import 保留
- `composer.json` / `package.json`：只更新 `peanut-admin/*` / `@peanut-admin/*` 版本约束

结构化合并不需要三方文本 diff，直接按 AST 或 JSON path 操作即可。

### seed-once

创建时生成，之后完全归应用。升级器不修改，只在报告里附上新版模板 diff 供参考。

### 三方文本合并（不在 v1 范围）

经过以上分类，所有 scaffold-managed 文件要么有结构化格式可解析，要么内容极简适合 replace-if-pristine。v1 不引入通用 `git merge-file`。

如果未来确实出现需要三方合并的场景（说明 replace-if-pristine 或 structured-merge 覆盖不到），届时再加入。

---

## 四、Scaffold Manifest

每个 Peanut release 发布一份机器可读的 `scaffold-manifest.json`：

```json
{
  "schema_version": 1,
  "peanut_version": "0.2.0",
  "files": {
    "backend/route/app.php": {
      "policy": "replace-if-pristine",
      "content_digest": "sha256:...",
      "source": "scaffold/backend/route/app.php"
    },
    "backend/config/modules.php": {
      "policy": "structured-merge",
      "merge_type": "php-array",
      "peanut_keys": ["roots", "frontend_components", "registered_client_keys"],
      "content_digest": "sha256:..."
    },
    "frontend/src/app/modules.ts": {
      "policy": "structured-merge",
      "merge_type": "ts-import-factory",
      "content_digest": "sha256:..."
    }
  },
  "renames": [],
  "deletes": [],
  "seed_once_templates": [
    "backend/config/app.php",
    "frontend/vite.config.ts"
  ],
  "change_summary": "0.1.0 -> 0.2.0: TenantGuard renamed, new OpsConsole module added"
}
```

真实文件内容**不塞进 JSON**。从对应版本的 scaffold package 或 Git tag 获取（见第六节）。

### `scaffold.lock`（应用侧）

```json
{
  "schema_version": 1,
  "peanut_version": "0.1.0",
  "files": {
    "backend/route/app.php": {
      "policy": "replace-if-pristine",
      "base_digest": "sha256:...",
      "app_override": false
    },
    "backend/config/modules.php": {
      "policy": "structured-merge",
      "base_digest": "sha256:..."
    }
  }
}
```

比 v2.x 的 `managed-files.lock` 简单很多：没有 recipe_artifact_digest，没有 base64 内容。

---

## 五、前端扩展模型（修订）

### Layout 的正确语义

WorkspaceLayout 应在 `@peanut-admin/admin-shell` 包中，其**安全行为**（权限检查、菜单加载、Tenant 上下文）在包里且不可替换。视觉外壳可以通过 appearance slot 定制。

应用通过 extension manifest 贡献：
- 业务页面 key（仅用于服务端菜单解析）
- 自定义 layout（用于应用自己的业务路由，不替换 Peanut 核心路由的 layout）
- appearance（替换登录视觉壳、Logo 等纯 UI 部分）

```ts
// extensions/frontend/src/extension.ts（application-owned）
export default defineAdminExtension({
  id: 'myapp',
  pages: {
    'myapp.case.list': () => import('./pages/CaseListPage.vue'),
  },
  // 不注册 layout，核心路由继续使用 peanut.layout.workspace
  appearances: {
    'peanut.auth.login.shell': () => import('./appearances/MyLoginShell.vue'),
  },
})
```

应用**不能**覆盖 `peanut.*` 命名空间的 key（编译期拒绝）。如果应用想完全替换 WorkspaceLayout，那是 seed-once 分支：创建时生成一份 layout 副本，之后完全由应用维护，升级器只提示新版 layout 有变化。

---

## 六、PHP 包发布渠道（修正 v3.0 错误）

GitHub Packages 不支持 Composer registry。v3.0 的 `https://npm.pkg.github.com` 用于 Composer 是技术错误。

**v1 推荐：Composer VCS repository + GitHub private repo + Git tags**

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/peanut-admin/kernel"
    }
  ],
  "require": {
    "peanut-admin/kernel": "^0.2.0"
  }
}
```

Composer 会从 GitHub VCS 读取 tag，根据版本约束解析并安装。认证通过 `auth.json`（不进 Git）或 CI 的 `COMPOSER_AUTH` 环境变量注入。

这是最简单的方案，不需要任何额外基础设施，GitHub 私有仓库天然支持。

**Web 包**：GitHub Packages npm registry（已支持，无需修正）。认证通过 `.npmrc` + `NODE_AUTH_TOKEN`（不进 Git）。

**离线/内网 v1 处理方式**：不要求第一版支持完全离线。接受"需要访问 GitHub"作为前提。真正无法访问的内网部署，依赖运维手动同步，这是合理的 v1 边界。

---

## 七、三段升级命令

### `peanut upgrade plan --to <version>`

读取目标版本的 scaffold-manifest.json 和 ReleaseManifest，输出：

```json
{
  "schema_version": 1,
  "peanut_version_from": "0.1.0",
  "peanut_version_to": "0.2.0",
  "app_state_digest": "sha256:scaffold.lock内容摘要",
  "packages": {
    "php": { "peanut-admin/kernel": "0.1.0 → 0.2.0" },
    "web": { "@peanut-admin/admin-core": "0.1.0 → 0.2.0" }
  },
  "scaffold_files": [
    {
      "path": "backend/route/app.php",
      "policy": "replace-if-pristine",
      "status": "pristine",
      "action": "replace"
    },
    {
      "path": "backend/config/modules.php",
      "policy": "structured-merge",
      "status": "modified",
      "action": "merge",
      "peanut_changes": ["adds peanut.ops-console module"]
    }
  ],
  "seed_once_changes": ["backend/config/app.php template updated (no auto-change)"],
  "database_migrations": ["peanut.kernel:20260724000001_..."],
  "breaking_changes": [
    {
      "id": "provider-boot-signature",
      "description": "ApplicationProvider.boot() 参数类型变更，需手工更新 extensions/backend/Providers/",
      "blocking": true
    }
  ]
}
```

plan 不修改任何文件，不生成 lockfile，不运行 Composer/pnpm。

plan sidecar：`.peanut/plans/upgrade-0.2.0.json.sha256`

### `peanut upgrade code-apply <plan-file>`

在隔离 Git worktree 执行：

1. 核验 plan sidecar digest
2. 比较 `app_state_digest` 与当前 `scaffold.lock` 摘要（stale plan 检测）
3. 若有 `blocking: true` 的 breaking change，检查 plan 生成后是否有对应的 resolution record（见第八节）；无则阻断
4. `composer update peanut-admin/*`（从 VCS repo 安装目标版本）
5. `pnpm update "@peanut-admin/*"`
6. 按 scaffold-manifest 处理每个 scaffold 文件（replace-if-pristine 或 structured-merge）
7. 更新 `scaffold.lock`（`peanut_version` 和各文件 `base_digest`）
8. 输出升级报告（含 seed-once 变化提示）

Git worktree 是代码恢复边界。

### `peanut upgrade db-apply <plan-file>`

核验 `scaffold.lock` 的 `peanut_version` 与 plan 一致（证明 code-apply 已完成），然后执行现有数据库升级逻辑。

---

## 八、Breaking Change 处理

### Resolution record（替代空洞 ack）

开发者处理 breaking change 后，创建：

```json
// .peanut/resolutions/provider-boot-signature.json（进入 Git）
{
  "action_id": "provider-boot-signature",
  "plan_digest_sha256": "...",
  "evidence_commit": "abc123",
  "note": "更新了 extensions/backend/Providers/AppServiceProvider.php 的 boot() 签名"
}
```

`evidence_commit` 必须是当前 Git 图中真实存在的 commit，且该 commit 修改了 `extensions/` 目录下的文件。`code-apply` 用 `git cat-file -t <commit>` 验证 commit 存在，用 `git diff --name-only <commit>~ <commit>` 验证文件范围包含 `extensions/`。

这比空 commit 有意义，但不需要解析 PHP 类型。

---

## 九、旧项目引导（Adoption）

`peanut-project.json` 里的 `input_commit` 可映射到具体的 Peanut release。Peanut 维护一个 commit→version 索引文件（随 release 发布，存于 Git tag 对应的 `RELEASES.json`）。

```bash
peanut upgrade adopt [--accept-override <path>]...
```

1. 从 `peanut-project.json` 读取 `input_commit`
2. 从 Peanut 仓库的 `RELEASES.json` 映射到版本号
3. 取得该版本的 scaffold-manifest.json（从 VCS tag 或本地 Git 缓存）
4. 逐文件对比：digest 一致 → pristine，不同 → 阻断等待 `--accept-override`
5. 写入 `scaffold.lock`

**关于旧版包源码快照的迁移**：

Stage C.2 生成的应用仓里有 `backend/src/Modules/Peanut/*/` 等包源码快照。Adoption 命令同时检测这些路径，输出迁移说明：

```
检测到 Stage C.2 包源码快照：
  backend/src/Modules/Peanut/Settings/（38 个文件）→ 将由 peanut-admin/settings 包替代
  ...
  
这些文件将在 code-apply 后被移除（包源码将从 vendor/ 安装）。
如果你修改过其中任何文件，请先将修改迁移到 extensions/backend/Providers/。
继续请运行：peanut upgrade adopt --migrate-package-snapshots
```

---

## 十、页面边界（U02 生成）

（与 v3.0 一致，已精确对应 Stage C.2 源码，不重复）

A. 固定核心页：对应 `router.ts` 固定路由，全部从包导入

B. 按 `--feature` 启用：Settings、Reference Codes、File/Media、Task/Job、Notification/SMS、Import/Export、Integration Security

C. 可删除示例页：`example.greeting` 等 Demo Module

---

## 十一、任务顺序

### U01：包发布与骨架重构（最关键，其余依赖）

**核心交付**：

1. 将 `starter/backend/src/Modules/Peanut/*/` 的内容移入各 Composer 包并发布到 GitHub VCS（加 Git tag）
2. 将 `frontend/src/modules/peanut-*.ts` 移入各 Web 包并发布到 GitHub Packages npm
3. 修改 `ProjectGenerator.php`：删除 `copyPackageSnapshots()`，改为写入版本约束
4. 固定 extension manifest 契约（`defineAdminExtension`）和 `ApplicationProvider` 接口
5. 发布 scaffold-manifest.json（包含 B 类文件清单和对应 content_digest）
6. 建立 `scaffold.lock` schema

**完成条件**：`composer require peanut-admin/kernel:^0.2.0` 可安装，不依赖 monorepo 相对路径。

### U02：完整应用创建器

`peanut new <slug>` 生成应用（Peanut 包从 registry 安装，不复制源码），生成 `scaffold.lock`，生成 extensions/ 骨架，实现 `peanut upgrade adopt`。

**完成条件**：生成的应用仓不含 Peanut 源码，`composer install` 从 VCS registry 安装。

### U03：升级器

`peanut upgrade plan`、`peanut upgrade code-apply`，scaffold structured-merge 实现，breaking change resolution record。

**完成条件**：从 0.1.0 升至 0.2.0 可执行，scaffold 变化被正确处理。

### U04：数据库部署与完整演示

`peanut upgrade db-apply`，完整生命周期演示。

---

## 十二、明确不做的事

| 能力 | 理由 |
|---|---|
| 通用三方文本合并 | scaffold 文件要么结构化可解析，要么内容极简适合 replace-if-pristine；不引入 |
| Recipe artifact（base64 内容塞进 JSON） | 文件内容从版本化 scaffold package / VCS tag 取得 |
| 独立 IoC 容器 | 包装 ThinkPHP 容器 |
| 代码阶段跨进程 ledger | Git worktree 是代码恢复边界 |
| 全目录 page glob | extension manifest 显式注册 |
| 字符串路径 middleware scope | module/route-group ownership |
| 离线内网完整支持（v1） | 接受 GitHub 可达为前提 |
| FrontendAdapter/BackendAdapter 两套执行器 | 统一命令，内部按文件类型分路，不建设通用框架 |
