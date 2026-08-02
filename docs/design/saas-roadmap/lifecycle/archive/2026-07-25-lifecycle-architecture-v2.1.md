# Peanut Admin 应用生命周期架构 v2.1

## 文档状态

- 状态：`design-revised / ready-for-contract-drafting`
- 分析基线：Stage C.2（`69c5b2c271413f6ff741de65437f29e04f975300`）
- 参考源码：Symfony Flex `RecipePatcher.php`、Nx `migrate.ts`、Vben `generate-routes-backend.ts`、Filament `PanelProvider.php`
- 前版本：[v2](2026-07-25-lifecycle-architecture-v2.md)
- 外部评审：[ChatGPT 评审](2026-07-25-lifecycle-architecture-v2-review.md)
- 修订说明：关闭评审中的四个根本问题，整合补出的遗漏项，调整任务顺序。

---

## 一、问题定义（不变）

Peanut Admin 是企业后台脚手架框架。用 `peanut new <slug>` 创建下游应用后，应用仓里存在三类内容：

| 类型 | 定义 | 升级行为 |
|---|---|---|
| **package-owned** | 只存在于已安装的 Composer / pnpm 包中，应用仓不含源码 | 更换包版本即可 |
| **recipe-managed** | 必须存在于应用仓的极少数入口和配置文件，由 Peanut 在创建时生成 | 三方合并，保留应用改动 |
| **application-owned** | 下游应用的业务代码、业务页面、数据库表、数据和配置 | 升级器永不触碰 |

额外边界（不进入 `managed-files.lock`，但必须在架构中单列）：

- **deployment-owned**：生产环境 secrets、数据库连接、基础设施配置、真实 `.env`、运行数据库。不由升级器管理，不进入 recipe 或 Git 提交。

---

## 二、修订 1：所有权模型精确化

### 删除 `advisory`，改用独立 `update_policy`

`ownership` 只描述谁拥有文件（`peanut` 或 `application`）；`update_policy` 描述升级器如何处理：

| update_policy | 说明 | 典型文件 |
|---|---|---|
| `replace-if-pristine` | Peanut 拥有；未被修改则直接替换，被修改则停止并建议迁移到扩展点 | 组合根入口（main.ts、bootstrap/app.php） |
| `three-way` | Peanut 与应用均可修改；用三方合并 | 极少数需要双方共同维护的配置 |
| `seed-once` | 首次生成后永久归应用；升级报告中提示模板变化，但不合并 | `.env.example`、CI 样板、Docker 样板 |

### `managed-files.lock` 修订结构

```json
{
  "schema_version": 2,
  "recipe_id": "peanut-admin/standard-admin",
  "recipe_version": "0.1.0",
  "recipe_artifact_digest": "sha256:a1b2c3...",
  "files": {
    "apps/frontend/src/main.ts": {
      "owner": "peanut",
      "update_policy": "replace-if-pristine",
      "base_digest": "sha256:d4e5f6..."
    },
    "apps/backend/bootstrap/app.php": {
      "owner": "peanut",
      "update_policy": "replace-if-pristine",
      "base_digest": "sha256:g7h8i9..."
    },
    ".env.example": {
      "owner": "peanut",
      "update_policy": "seed-once",
      "base_digest": "sha256:j0k1l2..."
    }
  }
}
```

`base_digest` 是 `sha256(<file-content>)`，与 Git blob hash 独立，不依赖 Git 历史。`recipe_artifact_digest` 指向发布时随 release 一起发布的不可变 recipe artifact。

### 受管文件数量约束

受管文件数量不在设计阶段预先固定。原则是：**能移入包的必须移入包**，`managed-files.lock` 里只保留必须存在于应用仓且需要长期跟随版本变化的文件。预计在 U01 完成扩展边界后逐文件盘点，并记录每个文件"必须在应用仓"的理由。

---

## 三、修订 2：Recipe Artifact 发布与旧内容获取

这是 v2 的根本缺口，也是整个三方合并机制能否工作的前提。

### 问题

Git blob hash 只是内容身份证明，不携带内容。仅凭 `base_digest` 无法重建三方合并的公共祖先，除非能取得旧版本文件的真实内容。Symfony Flex 能工作，是因为它同时持有旧 recipe 内容和新 recipe 内容，而不是只有一个 hash。

### 方案：不可变 Recipe Artifact

每个 Peanut release 必须随 release 一起发布 **recipe artifact**，包含：

```json
{
  "schema_version": 1,
  "recipe_id": "peanut-admin/standard-admin",
  "recipe_version": "0.2.0",
  "artifact_digest": "sha256:...",
  "files": {
    "apps/frontend/src/main.ts": {
      "content_digest": "sha256:...",
      "content_encoding": "base64",
      "content": "..."
    },
    "apps/backend/bootstrap/app.php": {
      "content_digest": "sha256:...",
      "content_encoding": "base64",
      "content": "..."
    }
  },
  "renames": [],
  "deletes": []
}
```

artifact 本身有不可变摘要。下游 `managed-files.lock` 记录 `recipe_artifact_digest`，升级器通过这个摘要从 release bundle 取得对应旧版本 artifact，作为三方合并祖先。

### 旧内容获取策略

- 升级器先检查本地 content cache（`~/.peanut/recipe-cache/`），缓存以 `recipe_id + recipe_version + artifact_digest` 为索引。
- 未命中则从 release bundle 内嵌的 artifact 取得，或从 Peanut release 服务下载。
- `--offline` 模式只允许使用已缓存且 digest 匹配的 artifact，digest 不匹配时 fail closed。
- 旧 recipe 的保留周期由 release policy 决定（建议至少保留最近三个 major 版本），与 release 服务一起发布。

### 三方合并实现（修订）

采用 `git merge-file`（三文件合并），而不是把 blob 写入目标项目对象库：

```bash
# 隔离临时目录执行
git merge-file current.txt old-base.txt new-base.txt
```

- `old-base.txt`：从旧 recipe artifact 取得（公共祖先）
- `new-base.txt`：从新 recipe artifact 取得（新版本期望内容）
- `current.txt`：应用仓的当前文件

冲突时文件保留冲突标记，升级器停止，不前移 lock，输出 old/current/new 三份文件供人工处理。

Git 在开发机和 CI 是合理前置（下游应用是源码项目）。Git 不可用时 fail closed，输出三份文件并说明人工处理步骤。不实现纯 PHP 合并引擎。

---

## 四、修订 3：前端 Extension Manifest，替代全目录 glob

### 问题

直接 `import.meta.glob('.../pages/**/*.vue')` 会把调试页、未完成页、内部导航页全部暴露进可解析表。服务端如果错误配置或被攻击，可以指向任何已打包页面。文件路径作为 `component_key` 也会因重命名破坏服务端菜单契约。

### 方案：glob extension manifest，不 glob 页面文件

每个下游扩展在 `extensions/frontend/` 下放一个显式 manifest 文件：

```ts
// extensions/frontend/src/extension.ts（application-owned）
export default defineAdminExtension({
  id: 'myapp',
  pages: {
    'myapp.case.list': () => import('./pages/CaseListPage.vue'),
    'myapp.report.dashboard': () => import('./pages/ReportDashboardPage.vue'),
  },
  layouts: {
    'myapp.workspace': () => import('./layouts/MyWorkspaceLayout.vue'),
  },
  appearances: {
    'peanut.auth.login': () => import('./appearances/MyLoginShell.vue'),
  },
})
```

组合根（recipe-managed）glob extension manifest：

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
    'peanut.layout.workspace': WorkspaceLayout,
    'peanut.page.tenant.login': TenantLoginPage,
    'peanut.page.status': StatusPage,
  },
  extensions,
})
```

`createAdminPageRegistry` 编译时拒绝：重复 key、`peanut.*` 保留命名空间、未知 layout、非法 route name。应用新增一个页面只需在 manifest 里加一行，不需要改 `page-registry.ts`（recipe-managed 文件不需要改动）。

### sealed 与 appearance 的分层

- **行为 sealed**（不可替换）：登录状态机、Token 管理、Tenant 选择规则、权限判断、返回地址校验、401 refresh/replay、保留路由。
- **表现开放**（通过 appearance / named slot）：Logo、文案、背景、登录视觉壳、WorkspaceLayout 外观、状态页内容、Dashboard 内容。
- 应用可以通过 `appearances` 替换登录页的视觉壳，但不能替换认证逻辑本身。

未知 key 使用本地诊断型 not-found，记录错误，不执行任何动态导入路径。

---

## 五、修订 4：后端 middleware scope 与 ServiceContainer

### Middleware scope：绑定 module/route-group，不用字符串路径

`after('peanut.permission', ..., '/app/*')` 的字符串路径前缀容易写错、重叠，不能作为安全边界。改为绑定到已编译的 route group / module key：

```php
$app->routes()->module('myapp.case', new MyCaseRouteProvider());
$app->middleware()
    ->forModule('myapp.case')
    ->after(CoreMiddleware::PERMISSION, MyCaseScopeMiddleware::class);
```

编译期核对：module owner、audience、route namespace 和 middleware 锚点均来自系统生成，不由应用填写字符串。应用 middleware 只作用于自己拥有的 route group。

### ServiceContainer：包装 ThinkPHP 容器，不独立实现

不维护第二套 IoC 实现。定义一个薄的 `ServiceRegistry` contract，默认 adapter 包装 ThinkPHP 容器：

```php
interface ServiceRegistry
{
    public function bind(string $abstract, string|callable $concrete): void;
    public function singleton(string $abstract, string|callable $concrete): void;
    public function get(string $abstract): mixed;
}
```

默认实现将 `bind/singleton/get` 委托给 ThinkPHP 的 `App` 容器，保持与框架一致的解析规则。

### Provider 生命周期语义

```
register(): 只声明 binding，禁止解析服务或访问数据库。
            所有 Provider 完成 register 后，按依赖拓扑进入 boot。
boot():     注册 routes、middleware、workers 和事件贡献。
```

编译阶段拒绝：重复 binding、循环依赖、覆盖 final service token。

**Service token 分类**：
- `final`：认证、Tenant context、权限执行、upgrade ledger——不可被应用替换。
- `decoratable`：日志、通知、存储、观测——可包裹但保留核心约束。
- `replaceable`：短信 provider、对象存储 provider——明确允许应用选择的 adapter。

---

## 六、修订 5：代码升级与数据库部署分段

### 问题

v2 将代码升级和数据库升级合并在一次 `apply` 中，但两者发生在不同环境（开发机 / CI vs 部署环境），有不同的回滚方式，无法形成真实原子性。

### 三段命令

所有三段共享同一 `release_identity`（release_id + release_artifact_digest），确保一致性：

#### 段一：`peanut upgrade plan --to <version>`（开发机）

只读（workspace/DB read-only）。允许从 release 服务取得并缓存 artifact。输出 `.peanut/plans/upgrade-<version>.json`：

```json
{
  "schema_version": 1,
  "release_identity": { "release_id": "0.2.0", "artifact_digest": "sha256:..." },
  "packages": {
    "php": { "peanut-admin/kernel": "0.2.0" },
    "web": { "@peanut-admin/admin-core": "0.2.0" }
  },
  "managed_files": [
    {
      "path": "apps/frontend/src/main.ts",
      "update_policy": "replace-if-pristine",
      "new_base_digest": "sha256:c4d7e9f2..."
    }
  ],
  "source_migrations": [
    {
      "id": "peanut.kernel@0.2.0/rename-tenant-guard",
      "type": "deterministic",
      "description": "TenantGuard 类名变更",
      "applies_to": ["apps/backend/bootstrap/app.php"]
    },
    {
      "id": "peanut.kernel@0.2.0/provider-boot-signature",
      "type": "manual",
      "description": "ApplicationProvider.boot() 签名变更，需手工更新 extensions/backend/Providers/ 下的实现。不会自动修改 application-owned 文件。"
    }
  ],
  "database_migrations": ["peanut.kernel:20260724000001_add_..."],
  "blocking_manual_actions": ["peanut.kernel@0.2.0/provider-boot-signature"],
  "cannot_touch": ["domain/", "extensions/"],
  "requires_sequential_from": "0.1.0"
}
```

若 `blocking_manual_actions` 非空，plan 明确说明哪些人工动作必须在 code apply 前完成；code apply 开始前再次检查，未解决则拒绝执行。

#### 段二：`peanut upgrade code-apply <plan>`（开发机 / CI）

在隔离 Git worktree 执行：

1. 核验 plan 文件 digest、release artifact digest、工作区干净
2. 确认所有 `blocking_manual_actions` 已 ack（写入 `.peanut/plans/upgrade-<version>.ack.json`）
3. 升级 Peanut packages（Composer + pnpm，隔离 worktree，不污染主分支）
4. 执行 `deterministic` source migration（只修改 recipe-managed 文件）
5. 对 `replace-if-pristine` 文件：比较 `base_digest`，未修改则直接替换，已修改则提示迁移到扩展点
6. 对 `three-way` 文件：执行 `git merge-file`，冲突时停止
7. 输出代码升级报告

**Git worktree 就是代码恢复边界**。成功后正常 commit；失败则丢弃该 worktree。不为代码阶段建设数据库式跨进程 ledger。

`managed-files.lock` 中 `base_digest` 随代码升级 commit 一起更新。

#### 段三：`peanut upgrade db-apply <plan>`（部署环境）

基于当前 `scripts/upgrade` 已有能力，绑定到同一 `release_identity`：

1. 核验部署 artifact、target release identity、当前数据库 migration ledger 和备份证据
2. 获取 MySQL advisory lock
3. 只执行 Peanut-owned migration（按 ownership 清单，遇到非 Peanut 表立即拒绝）
4. 数据库 ledger 逐项记录 `planned / running / applied / failed` + checksum
5. 同步权限、菜单、Settings 和 Reference Codes 定义

数据库 migration 采用 expand/contract 兼容策略，保证旧代码与迁移中 schema 的明确兼容窗口。

---

## 七、任务顺序调整

评审指出 v2 的 U01 → U02 顺序存在循环依赖：U01 要生成完整标准后台，但 U02 才提供页面映射和 Provider 机制。调整后：

### U01：应用扩展契约与薄组合根

**用户可观测结果**：现有标准后台使用同一套组合模型运行；`.peanut/project.json`、recipe artifact 和 managed lock schema 固定。

包含：
- 固定 frontend extension manifest 契约（`defineAdminExtension`）、appearance/slot 分层
- 固定 HTTP interceptor 阶段顺序
- 固定 backend `ApplicationProvider`、module/route-group、service token 分类
- 固定 `managed-files.lock` schema v2
- 固定 recipe artifact 格式
- **决策**：package 分发方式（release bundle 内嵌 tarball 还是 Composer/NPM registry）——必须在本任务中做出并记录，这是后续一切的前提

不包含：创建器、升级器、合并引擎

---

### U02：完整应用创建器

**用户可观测结果**：`peanut new <slug>` 生成基于 U01 契约的完整标准后台应用，能直接启动。

包含：
- 生成真实 login、router、layout、权限（从包导入，不是 stub）
- 修复 `TRUSTED_MENU_ROUTE_CONTRACTS` clientKeys 硬编码，改为 audience 匹配
- 生成 `.peanut/project.json` 和 `managed-files.lock`（含 `recipe_artifact_digest` 和 `base_digest`）
- 生成 `extensions/` 目录骨架和示例 extension manifest
- 生成 `extensions/backend/Providers/AppServiceProvider.php` 骨架

不包含：三方合并、升级命令

---

### U03：代码升级器与 Release Artifact

**用户可观测结果**：`peanut upgrade plan` 列出所有代码层变更（包版本、受管文件、source migration）；`peanut upgrade code-apply` 执行后业务代码和扩展不变；冲突时停止并给出清晰说明。

包含：
- `peanut upgrade plan --to <version>` 输出可读计划文件
- `peanut upgrade code-apply <plan>` 执行包升级 + source migration + 受管文件处理（replace-if-pristine / three-way）
- `git merge-file` 三方合并，冲突时输出三份文件，停止并说明
- 扩展 `ReleaseManifest` schema 至 v2（加包摘要、recipe artifact、source migrations、compatibility）
- Recipe artifact 自动生成工具（不手写）
- `managed-files.lock` 随代码 commit 更新

不包含：数据库部署（已有 db-apply 能力）

---

### U04：数据库部署绑定与完整生命周期演示

**用户可观测结果**：一次可审计的完整演示：用 U02 创建应用，U03 升级代码，U04 部署数据库，业务数据、扩展注册、应用代码全部保持不变；冲突和 manual action 的停止行为可被演示。

包含：
- 将现有 `upgrade db-apply` 绑定到同一 `release_identity`
- expand/contract 兼容性说明
- 用真实应用（如 DCS 或新建测试应用）演示完整创建→代码升级→数据库部署流程
- 发布完整 release manifest v2

---

## 八、遗漏项处理

以下是 ChatGPT 评审补出的原设计未充分处理的问题，每项标注在哪个任务中处理：

| 遗漏项 | 处理任务 | 处理方式 |
|---|---|---|
| **Package 分发**：Composer/NPM 包从哪里取得、如何验签、如何离线缓存 | U01（必须先决策） | 在 U01 合同中强制做出发布方式决策；默认 release bundle 内嵌 tarball，`--offline` 只用本地缓存 |
| **Recipe artifact 生命周期**：旧内容保留多久 | U03 | release policy 随 ReleaseManifest 发布，建议保留最近三个 major |
| **前后端版本一致性**：PHP/Web/OpenAPI 防漂移 | U01 | recipe artifact 包含一组包版本，release_identity 强制一致 |
| **扩展兼容与冲突**：extension apiVersion、重复 key、循环依赖 | U01 | `defineAdminExtension` 和 Provider 编译期校验 |
| **滚动部署兼容**：expand/contract 兼容窗口 | U04 | db-apply 之前须有兼容性证明 |
| **旧项目引导**：Stage C.2 及更早应用没有 managed lock | U02 补充 | 提供一次性 adoption 命令，重建可信 baseline，不假装由 U01 新创建 |
| **Package manager 副作用**：Composer plugins、npm lifecycle scripts | U03 | 所有包操作在隔离 worktree 执行，不影响主分支 |
| **配置与 secrets**：诊断输出脱敏 | U01（约束） | secrets 不进入 project manifest 或诊断日志，`.env` 永远 seed-once |
| **路径与平台**：Windows 路径分隔符、换行、可执行位 | U03 | 路径统一用 POSIX 表示，`git merge-file` 处理换行规范化 |
| **版本撤回与最低升级路径** | U03 | `requires_sequential_from` 字段，plan 检查并拒绝跳版 |

---

## 九、明确不做的事

| 能力 | 理由 |
|---|---|
| 完整独立 IoC 容器 | 包装 ThinkPHP 容器即可；不维护第二套 singleton、循环依赖和作用域规则 |
| 纯 PHP merge fallback | Git 在开发/CI 是合理前置；不实现备用合并引擎 |
| 代码阶段跨进程 ledger | Git worktree commit 是代码恢复边界，不需要数据库式账本 |
| Nx agentic migration | recipe-managed 文件极少，三方合并 + 人工说明已足够 |
| 自动 DDL 回滚 `down()` | 恢复依赖已验证备份；现有设计正确 |
| ReleaseManifest 手工填写 | 必须由 release tooling 确定性生成并校验；禁止维护者手写 |
| 前端全目录 page glob | 扩大暴露面；改用 extension manifest glob |
| 字符串路径 middleware scope | 容易写错；改用 module/route-group ownership |
| 大范围 recipe-managed 文件（>10 个） | 能进包的必须进包；managed files 越少越好 |
