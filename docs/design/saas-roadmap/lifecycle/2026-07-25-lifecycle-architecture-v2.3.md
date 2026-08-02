# Peanut Admin 应用生命周期架构 v2.3

## 文档状态

- 状态：`design-final / ready-for-u01-contract`
- 分析基线：Stage C.2（`69c5b2c271413f6ff741de65437f29e04f975300`）
- 前版本：v2.2（`2026-07-25-lifecycle-architecture-v2.2.md`）
- 本版修订说明（关闭 ChatGPT v2.2 审核的 6 项必须修订）：
  1. 补充 package manifest/lockfile 的结构化所有权与升级规则
  2. `app_override` 与 `base_digest` 并用，增加 fail-closed 内容检测
  3. plan 同时作为部署制品，定义摘要合同
  4. Adoption 改为确定性分类与逐路径显式确认
  5. 固定 package 分发方式（直接在文档中决策，消灭阻塞）
  6. 修正页面边界，建立 recipe-managed 权威清单规则

---

## 一、问题定义

Peanut Admin 是企业后台脚手架框架。任何用 `peanut new <slug>` 创建的应用，都可以长期执行 `peanut upgrade plan / code-apply / db-apply` 跟随 Peanut 版本演进，不需要手工维护 Peanut 内部文件，不会覆盖应用业务代码。

应用仓内容分类：

| 类型 | 定义 | 升级行为 |
|---|---|---|
| **package-owned** | 只存在于已安装的 Composer / pnpm 包，应用仓不含源码 | 结构化包升级 |
| **recipe-managed** | 极少数必须存在于应用仓的入口文件，由 Peanut 生成 | 按 update_policy 处理 |
| **package-manifest** | `composer.json`、`package.json`、lockfile | 结构化修改，只允许改 Peanut 依赖项 |
| **application-owned** | 应用业务代码、页面、表、数据、配置 | 升级器永不触碰 |
| **deployment-owned** | secrets、生产连接、`.env`、运行数据库 | 不进入 recipe 或 Git 提交 |

`package-manifest` 单独列出，因为它需要 Peanut 和应用共同维护，但不是文本三方合并——通过结构化的包管理器操作处理，只允许升级器修改其中的 Peanut 依赖条目。

---

## 二、Package 分发方式决策（已固定）

**选择：Option A — Release Bundle Tarball**

理由：
- Peanut 当前明确无 npm/Packagist 公开发布流程
- 私有化部署、内网环境是主要场景
- Option B（正式 registry）可在未来版本升级，Option A 是当前唯一现实选择

**固定规格**：

每次发布的 release bundle（`.zip`）包含：
- `peanut-admin-<version>.json`：ReleaseManifest v2
- `recipe/`：recipe artifact（见第四节）
- `packages/php/`：所有 Peanut PHP 包的 tarball
- `packages/web/`：所有 Peanut Web 包的 tarball
- `SHA256SUMS`：所有文件的摘要

**可信来源**：

- 默认：从 Peanut 官方 release 页面下载并核验 `SHA256SUMS`
- 离线：使用已缓存在 `~/.peanut/bundle-cache/<version>/` 的 bundle，digest 不匹配 fail closed

**Composer/pnpm 安装约束**：

- 升级时 Composer 使用 `--prefer-dist --no-scripts`，禁止 lifecycle scripts
- pnpm 使用 `--frozen-lockfile` 的变体，在隔离 worktree 中执行
- 安装完成后核验安装后 digest 与 bundle 中记录一致

---

## 三、所有权模型与 update_policy

### 文件所有权

- `owner: peanut`：由 Peanut 持续维护的入口文件；应用通过扩展点贡献内容，不修改文件本身
- `owner: application`：首次生成后完全归应用，升级器不再管理

### update_policy（两种）

| 策略 | 适用 | 升级行为 |
|---|---|---|
| `replace-if-pristine` | Peanut 持续维护的入口文件 | 见状态矩阵（第三节末） |
| `seed-once` | `.env.example`、CI/Docker 样板 | **不进入 managed-files.lock**；创建时写入，之后完全归应用 |

不提供通用文本三方合并。若某文件需要 Peanut 和应用共同修改，应先修复扩展点设计。

### `replace-if-pristine` 状态矩阵

升级器在 plan 和 code-apply 阶段对每个 `replace-if-pristine` 文件执行以下判断：

| 当前文件 digest | `app_override` | 行为 |
|---|---|---|
| = `base_digest` | `false` | 允许替换（文件 pristine） |
| ≠ `base_digest` | `false` | **阻断**：输出差异报告，要求还原、迁移到扩展点或显式接管 |
| 任意 | `true` | 保留当前文件，将新版本写入 `<path>.peanut-new` 供参考 |
| 文件缺失 | 任意 | **阻断**：要求明确处理（还原或接管） |

`app_override: false` + `base_digest` 共同工作：前者表达"我没有接管它"的意图，后者防止开发者忘记声明而导致修改被静默覆盖。两者不是二选一。

开发者手动将 lock 文件中对应条目的 `app_override` 改为 `true`，表示接管。升级器从不自动更改此字段。

### `managed-files.lock` 结构

```json
{
  "schema_version": 2,
  "recipe_id": "peanut-admin/standard-admin",
  "recipe_version": "0.1.0",
  "recipe_artifact_digest": "sha256:a0b1c2...",
  "files": {
    "apps/frontend/src/main.ts": {
      "owner": "peanut",
      "update_policy": "replace-if-pristine",
      "base_digest": "sha256:d4e5f6...",
      "app_override": false
    },
    "apps/frontend/src/app/page-registry.ts": {
      "owner": "peanut",
      "update_policy": "replace-if-pristine",
      "base_digest": "sha256:e5f6g7...",
      "app_override": false
    },
    "apps/backend/bootstrap/app.php": {
      "owner": "peanut",
      "update_policy": "replace-if-pristine",
      "base_digest": "sha256:f6g7h8...",
      "app_override": false
    }
  }
}
```

上述三个文件是当前示例，**不是完整权威清单**。完整的 recipe-managed 文件列表在 U01 完成后由以下方式确定：
- recipe 源目录中显式列出的每个文件路径
- recipe artifact 自动生成工具从源目录读取，产出机器可读清单
- 每个文件必须有"为什么不能进入 package"的文档说明

### Package Manifest 的结构化修改

`composer.json`、`package.json`、lockfile 属于 `package-manifest` 类型，不进入 `managed-files.lock`，也不用文本替换。升级时：

1. 升级器读取 bundle 中 Peanut 包的目标版本
2. 只修改 `composer.json` 和 `package.json` 中 `require`/`dependencies` 里 Peanut 相关条目
3. 重新运行 Composer/pnpm install 生成新 lockfile
4. 核验安装后 digest

---

## 四、Recipe Artifact

每个 release 随 bundle 发布不可变的 recipe artifact：

```json
{
  "schema_version": 1,
  "recipe_id": "peanut-admin/standard-admin",
  "recipe_version": "0.2.0",
  "artifact_digest": "sha256:a1b2c3...",
  "files": {
    "apps/frontend/src/main.ts": {
      "content_digest": "sha256:d4e5f6...",
      "content_base64": "..."
    },
    "apps/backend/bootstrap/app.php": {
      "content_digest": "sha256:g7h8i9...",
      "content_base64": "..."
    }
  },
  "renames": [],
  "deletes": []
}
```

Recipe artifact **由 release tooling 从 recipe 源目录确定性生成，禁止手工填写**。

旧版 artifact 取得：升级器按 lock 中 `recipe_artifact_digest` 在本地 bundle cache 查找。未命中则从 release bundle 取得。`--offline` 时 cache miss 则 fail closed，同时输出三份文件（old/current/new）和人工处理说明。

---

## 五、前端扩展：Extension Manifest

应用在 `extensions/frontend/` 下放显式 manifest，不 glob 所有 `.vue`：

```ts
// extensions/frontend/src/extension.ts（application-owned）
export default defineAdminExtension({
  id: 'myapp',
  pages: {
    'myapp.case.list':        () => import('./pages/CaseListPage.vue'),
    'myapp.report.dashboard': () => import('./pages/ReportDashboardPage.vue'),
  },
  layouts: {
    'myapp.workspace': () => import('./layouts/MyWorkspaceLayout.vue'),
  },
  appearances: {
    'peanut.auth.login.shell': () => import('./appearances/MyLoginShell.vue'),
  },
})
```

组合根（recipe-managed）glob manifest 文件而非页面文件：

```ts
// apps/frontend/src/app/page-registry.ts（recipe-managed）
import { createAdminPageRegistry } from '@peanut-admin/admin-core'
import { TenantLoginPage, WorkspaceLayout, StatusPage } from '@peanut-admin/admin-shell'

const extensions = import.meta.glob(
  '../../../extensions/frontend/*/extension.ts',
  { eager: true }
)

export const pageRegistry = createAdminPageRegistry({
  sealed: {
    'peanut.layout.workspace':  WorkspaceLayout,
    'peanut.page.tenant.login': TenantLoginPage,
    'peanut.page.status':       StatusPage,
  },
  extensions,
})
```

**编译期拒绝**：重复 key、`peanut.*` 保留命名空间、未知 layout、非法 route name。

**Sealed 分层**：
- 行为 sealed（不可替换）：登录状态机、Token、Tenant 选择、权限判断、返回地址、401 refresh
- 表现开放（appearance 键）：Logo、文案、背景、登录视觉壳、Workspace 外观、状态页

未知 key 本地 fail-closed 到 not-found，记录错误，不执行动态路径。

---

## 六、后端 Provider 与中间件

### ServiceRegistry

薄 contract，默认 adapter 包装 ThinkPHP 容器：

```php
interface ServiceRegistry
{
    public function bind(string $abstract, string|callable $concrete): void;
    public function singleton(string $abstract, string|callable $concrete): void;
    public function get(string $abstract): mixed;
}
```

Service token 分类：`final`（认证、Tenant context、权限、upgrade ledger）/ `decoratable`（日志、通知、存储）/ `replaceable`（短信、对象存储 provider）。

### Provider 契约

```php
final class AppServiceProvider implements ApplicationProvider
{
    public function register(ServiceRegistry $registry): void
    {
        // 只声明 binding，禁止解析服务或访问数据库
        $registry->singleton(AppRepository::class, PdoAppRepository::class);
    }

    public function boot(ApplicationContributions $app): void
    {
        $app->routes()->module('myapp.case', new MyCaseRouteProvider());
        $app->middleware()
            ->forModule('myapp.case')
            ->after(CoreMiddleware::PERMISSION, MyCaseScopeMiddleware::class);
        $app->workers()->register('myapp.recalculate', MyRecalculateHandler::class);
    }
}
```

生命周期规则：所有 Provider 先完成 `register()`，按依赖拓扑进入 `boot()`。编译期拒绝：重复 binding、循环依赖、覆盖 final token。

### 中间件链（固定顺序）

```
[最外层异常 / Problem Details]
[Request ID + Security Headers]
[CORS + 限流]
[TenantGuard / PlatformGuard]          ← CoreMiddleware::AUTH
[ModuleGuard]                           ← CoreMiddleware::MODULE
[PermissionGuard / DataPermission]      ← CoreMiddleware::PERMISSION
[Idempotency]                           ← CoreMiddleware::IDEMPOTENCY
[应用 middleware（forModule + after）]
[Controller / Handler]
[审计 + 指标]
```

Middleware scope 绑定 module/route-group，不用字符串路径。

---

## 七、HTTP Interceptor 阶段（前端，固定顺序）

**请求链**：Core（Request ID / audience / origin）→ Core（Authorization / Tenant context）→ 应用（白名单业务 header，不能覆盖 Authorization/Tenant/Request ID/Idempotency-Key）

**响应链**：Core（401 refresh）→ Core（Problem Details）→ 应用（业务通知/埋点，不能将权限错误改为成功）

---

## 八、三段升级命令

所有三段共享同一 `release_identity`（`release_id` + `bundle_digest`）。

### 段一：`peanut upgrade plan --to <version>`

- Workspace 只读，数据库只读
- 允许下载并缓存 release bundle artifact
- 若 `blocking_manual_actions` 非空，plan 输出明确说明；`code-apply` 拒绝启动直到开发者完成修改并重新运行 plan，兼容性检查确认问题消失

Plan 文件输出：`.peanut/plans/upgrade-<version>.json`

```json
{
  "schema_version": 1,
  "release_identity": {
    "release_id": "0.2.0",
    "bundle_digest": "sha256:..."
  },
  "packages": {
    "php": { "peanut-admin/kernel": "0.2.0" },
    "web": { "@peanut-admin/admin-core": "0.2.0" }
  },
  "managed_files": [
    {
      "path": "apps/frontend/src/main.ts",
      "update_policy": "replace-if-pristine",
      "current_digest": "sha256:d4e5f6...",
      "base_digest": "sha256:d4e5f6...",
      "new_base_digest": "sha256:c4d7e9...",
      "app_override": false,
      "action": "replace"
    }
  ],
  "package_manifest_updates": {
    "php": { "peanut-admin/kernel": "0.1.0 -> 0.2.0" },
    "web": { "@peanut-admin/admin-core": "0.1.0 -> 0.2.0" }
  },
  "source_migrations": [
    {
      "id": "peanut.kernel@0.2.0/rename-tenant-guard",
      "type": "deterministic",
      "description": "TenantGuard 已更名为 PeanutTenantGuard"
    },
    {
      "id": "peanut.kernel@0.2.0/provider-boot-signature",
      "type": "manual",
      "description": "ApplicationProvider.boot() 参数类型变更。请手工更新 extensions/backend/Providers/ 下的实现，然后重新运行 plan。"
    }
  ],
  "database_migrations": ["peanut.kernel:20260724000001_..."],
  "blocking_manual_actions": ["peanut.kernel@0.2.0/provider-boot-signature"],
  "plan_digest": "sha256:plan-content-hash...",
  "cannot_touch": ["domain/", "extensions/"],
  "requires_sequential_from": "0.1.0"
}
```

**Plan 作为部署制品**：

- Plan 文件随代码 commit 进入 Git（审计记录）
- CI 同时将 plan 文件及其 `plan_digest` 放入 release bundle 的 `plans/` 目录
- `db-apply` 可从 Git 取得 plan，也可从部署制品取得，但必须核验 `plan_digest` 与 `release_identity` 一致
- 两个来源内容必须摘要一致；不一致则 fail closed

### 段二：`peanut upgrade code-apply <plan-file>`

在隔离 Git worktree 执行：

1. 核验 plan 文件 `plan_digest`、`bundle_digest`、工作区干净
2. 确认 `blocking_manual_actions` 为空（不为空则拒绝并显示说明）
3. 对每个 `managed_files` 条目执行第三节的状态矩阵检查
4. 结构化升级 `package-manifest`（只改 Peanut 依赖条目）
5. 执行 `deterministic` source migration（只修改 recipe-managed 文件）
6. 执行 managed files 替换（或写 `.peanut-new`）
7. 更新 `managed-files.lock` 中 `base_digest` 和 `recipe_version`
8. 输出代码升级报告

**Git worktree 是代码恢复边界**，不建设跨进程代码 ledger。

### 段三：`peanut upgrade db-apply <plan-file>`

1. 核验 plan `plan_digest`、`release_identity`、`managed-files.lock` 版本与 plan 一致
2. 核验备份证据
3. 获取 MySQL advisory lock
4. 只执行 Peanut-owned migration（遇到非 Peanut 表立即拒绝）
5. 数据库 ledger 逐项记录 `planned / running / applied / failed` + checksum
6. 同步权限、菜单定义

Plan 文件由 `code-apply` commit 随代码进入 Git。部署环境无 Git 时，从 release bundle 的 `plans/` 目录取得并核验 `plan_digest`，两种来源等效。

---

## 九、CLI 命令归属

- `peanut upgrade plan`：新 `UpgradePlanCommand` 类
- `peanut upgrade code-apply`：新 `UpgradeCodeApplyCommand` 类
- `peanut upgrade db-apply`：现有 `UpgradeWorkflow` + `UpgradeCli` 的直接演进
- `peanut upgrade adopt`：新 `UpgradeAdoptCommand` 类
- `scripts/upgrade`（bash 入口）扩展支持新子命令

---

## 十、U02 生成的标准后台页面边界

生成的应用包含以下三类页面：

### A. 固定核心页（必须包含，从包导入）

**Tenant 侧**：登录、租户选择、工作台（Dashboard）、账号信息、成员管理、成员有效访问预览、角色管理、部门管理、权限治理工作台、模块管理、审计日志

**Platform 侧**：平台登录、平台工作台、租户管理、租户详情、平台操作员、平台角色、平台权限治理工作台、平台审计、版本与升级状态、运维控制台

**状态页**：403、404、服务不可用

### B. 按 features 参数启用的 Peanut 功能页

Settings、Reference Codes、File/Media、Task/Job、Notification/SMS、Import/Export、Integration Security 各对应一个页面，由 `--feature` 参数控制是否生成。

### C. 可删除示例页（Demo Module）

对应 Stage C.2 中 `example.greeting`、`example-target`、`example-reference`、`example-work-item` 等示例路由。明确标注为"可删除 Demo Module"，不与固定核心页混同。生成的 `README.md` 说明删除方式。

**对应关系**：A 类来自 `frontend/src/app/router.ts` 的固定路由；B 类来自 `APP_MODULES` 中各模块；C 类对应 `example.*` 路由，均与 Stage C.2 源码对应。

---

## 十一、旧项目引导（Adoption）

Stage C.2 及更早应用没有 `managed-files.lock`，需执行一次性引导：

```bash
peanut upgrade adopt --recipe-version 0.1.0 [--accept-override <path>]...
```

**确定性分类规则**：

1. 升级器取得 `recipe-version` 对应的 recipe artifact
2. 逐文件对比当前内容与 artifact 内容：
   - **完全一致**（content_digest 匹配）：登记为 `app_override: false`（pristine）
   - **内容不同**：默认**阻断**，生成逐文件差异报告
3. 开发者对每个差异文件显式决定：
   - `--accept-override apps/frontend/src/main.ts`：写入 `app_override: true`
   - 不传此参数则保持阻断，不写入 lock
4. 所有文件处理完成后写入 `managed-files.lock`

**Adoption 不自动覆盖任何文件**。缺失文件、未知文件类型或无法证明 recipe 版本时必须人工处理。

---

## 十二、遗漏项处理

| 遗漏项 | 处理任务 | 处理方式 |
|---|---|---|
| Package 分发 | **已在本文固定**（Option A，tarball bundle） | 见第二节 |
| Recipe artifact 生命周期 | U03 | 保留最近三个 major；release policy 随 manifest 发布 |
| 前后端版本一致性 | U01 | release_identity 强制一组包版本一致 |
| 扩展兼容与冲突 | U01 | 编译期校验，重复 key / 循环依赖拒绝 |
| 滚动部署兼容 | U04 | expand/contract 兼容窗口 |
| 旧项目引导 | U02（补充） | `peanut upgrade adopt`（见第十一节） |
| Package manager 副作用 | U03 | 隔离 worktree，`--no-scripts` |
| 配置与 secrets | U01 约束 | 不进入 manifest 或诊断日志 |
| 路径与平台 | U03 | 路径统一 POSIX，git 处理换行 |
| 版本撤回与最低升级路径 | U03 | `requires_sequential_from`，plan 检查 |

---

## 十三、明确不做的事

| 能力 | 理由 |
|---|---|
| 通用文本三方合并 | package-manifest 用结构化操作；recipe 文件用 replace-if-pristine；若需合并说明扩展边界有问题 |
| `ack.json` 续跑机制 | 重新 plan 自动验证即可，修复本身就是证明 |
| `seed-once` 追踪入 lock | 首次生成后归应用，不需要 lock 追踪 |
| 内容哈希单独决定"可替换" | `app_override` + `base_digest` 共同工作 |
| 全目录 page glob | 用 extension manifest |
| 字符串路径 middleware scope | 用 module/route-group ownership |
| 独立 IoC 容器 | 包装 ThinkPHP 容器 |
| 纯 PHP merge fallback | Git 在开发/CI 是合理前置 |
| 代码阶段跨进程 ledger | Git worktree commit 是代码恢复边界 |
| ReleaseManifest 手工填写 | tooling 确定性生成 |

---

## 十四、任务顺序

### U01：应用扩展契约与薄组合根

固定 extension manifest 契约、appearance/slot 分层、HTTP interceptor 阶段、backend Provider 接口（包含 service token 分类、Provider 生命周期）、recipe-managed 文件完整权威清单（逐文件说明不能进包的理由）、managed-files.lock schema v2、recipe artifact 格式。

### U02：完整应用创建器

`peanut new <slug>` 生成完整标准后台（包含第十节 A/B/C 三类页面）、生成 managed-files.lock（含 `recipe_artifact_digest` 和 `base_digest`）、生成 extensions/ 骨架和示例 extension manifest。同时实现 `peanut upgrade adopt`。

### U03：代码升级器与 Release Artifact

`peanut upgrade plan`、`peanut upgrade code-apply`，recipe artifact 自动生成工具，plan 文件摘要合同，ReleaseManifest schema v2，package-manifest 结构化修改。

### U04：数据库部署绑定与完整演示

`peanut upgrade db-apply` 绑定 release_identity，完整生命周期演示（含冲突停止和 manual action 阻断）。
