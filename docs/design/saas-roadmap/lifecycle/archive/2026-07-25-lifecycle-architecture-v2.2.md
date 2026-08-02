# Peanut Admin 应用生命周期架构 v2.2

## 文档状态

- 状态：`design-final / ready-for-u01-contract`
- 分析基线：Stage C.2（`69c5b2c271413f6ff741de65437f29e04f975300`）
- 前版本：v2.1（`2026-07-25-lifecycle-architecture-v2.1.md`）
- 本版修订说明：
  - 删除 `ack.json` 续跑机制，改为"修复本身就是证明"
  - 删除 `three-way` update_policy，update_policy 简化为两种
  - 删除 `seed-once` 在 managed-files.lock 里的追踪，仅文档说明
  - 用显式 `app_override` 字段替代内容哈希检测"是否已修改"
  - 从 U01 里剥离"package 分发决策"，作为独立前置决策项
  - 补充：标准后台生成页面边界、plan 文件流转方式、CLI 命令归属

---

## 一、问题定义（不变）

Peanut Admin 是企业后台脚手架框架。任何用 `peanut new <slug>` 创建的应用，都可以长期执行 `peanut upgrade plan / code-apply / db-apply` 跟随 Peanut 版本演进，不需要手工维护 Peanut 内部文件，不会覆盖应用业务代码。

应用仓内容分三类：

| 类型 | 定义 | 升级行为 |
|---|---|---|
| **package-owned** | 只存在于已安装的 Composer / pnpm 包，应用仓不含源码 | 更换包版本 |
| **recipe-managed** | 极少数必须存在于应用仓的入口文件，由 Peanut 生成 | 按 update_policy 处理 |
| **application-owned** | 应用业务代码、页面、表、数据、配置 | 升级器永不触碰 |

额外边界：**deployment-owned**（secrets、生产连接、`.env`、运行数据库）——不进入任何 recipe 或 Git 提交。

---

## 二、前置决策项（U01 启动前必须完成）

**Package 分发方式**是后续一切机制的基础。它决定：
- recipe artifact 如何打包和分发
- `peanut upgrade plan` 如何取得旧版和新版包内容
- 离线场景如何工作

**两个选项：**

| 选项 | 描述 | 适用场景 |
|---|---|---|
| **A：Release bundle tarball** | 每次发布时打包一个包含所有 Peanut package + recipe artifact 的 zip/tarball，下游用本地路径安装 | 当前"无 npm/Packagist 发布流程"的现状，内网/私有化部署 |
| **B：Composer/NPM registry** | 发布到正式 registry，`composer require` / `pnpm add` 正常安装 | 公开发布，标准工作流 |

**必须在 U01 合同起草前以文档形式记录决策结论。** 本文档其余部分以选项 A 为默认描述，选项 B 只影响 artifact 取得方式，其他机制不变。

---

## 三、所有权模型

### 文件所有权（只有两类）

- `owner: peanut`：Peanut 负责维护，应用不应修改，而应通过扩展点贡献内容
- `owner: application`：首次生成后完全归应用，Peanut 不再管理

### update_policy（只有两种）

| 策略 | 适用 | 升级行为 |
|---|---|---|
| `replace-if-pristine` | 由 Peanut 持续维护的入口文件 | 未声明 override 时直接替换；已声明 override 时保留并输出新版本供参考 |
| `seed-once` | 首次生成后归应用（`.env.example`、CI 样板等） | **不进入 managed-files.lock**，仅在创建时写入，升级器忽略 |

`three-way` 策略不存在。如果某文件需要 Peanut 和应用共同修改，说明扩展边界设计有问题，应先修复扩展点，而不是依赖合并。

### `app_override`：显式接管声明

不依赖内容哈希检测"文件是否被修改"（换行符、格式化工具等会造成误判）。改为显式声明：

```json
{
  "files": {
    "apps/frontend/src/main.ts": {
      "owner": "peanut",
      "update_policy": "replace-if-pristine",
      "base_digest": "sha256:d4e5f6...",
      "app_override": false
    }
  }
}
```

开发者主动声明 `"app_override": true` 时，升级器尊重该声明，跳过替换并将新版本内容写入旁路文件（如 `apps/frontend/src/main.ts.peanut-new`）供参考。

升级器从不自动将 `app_override` 从 false 改为 true。开发者手动编辑 lock 文件来声明接管。

---

## 四、Recipe Artifact

每个 Peanut release 随 release bundle 发布不可变的 **recipe artifact**：

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

**旧版 artifact 取得方式**：升级器按 `managed-files.lock` 里的 `recipe_artifact_digest` 在本地 content cache（`~/.peanut/recipe-cache/`）中查找。未命中则从 release bundle 取得（选项 A）或从 registry 取得（选项 B）。`--offline` 模式下 cache miss 则 fail closed。

**Recipe artifact 不手工填写**，由 release tooling 从 recipe 源目录确定性生成。

---

## 五、`managed-files.lock` 结构

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

`.env.example`、CI 样板、Docker 文件**不在此 lock 中**，仅在创建时写入，之后归应用。

---

## 六、前端扩展：Extension Manifest

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
    // 替换登录页视觉壳（不替换认证逻辑）
    'peanut.auth.login.shell': () => import('./appearances/MyLoginShell.vue'),
  },
})
```

组合根（recipe-managed）glob 这个 manifest 文件：

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
    'peanut.layout.workspace':    WorkspaceLayout,
    'peanut.page.tenant.login':   TenantLoginPage,
    'peanut.page.status':         StatusPage,
  },
  extensions,
})
```

**编译期拒绝**：重复 key、`peanut.*` 保留命名空间、未知 layout、非法 route name。

**Sealed 分层**：
- 行为 sealed（不可替换）：登录状态机、Token、Tenant 选择、权限判断、返回地址校验、401 refresh
- 表现开放（通过 appearance 键注册）：Logo、文案、背景、登录视觉壳、Workspace 外观、状态页

未知 key 本地 fail-closed 到 not-found，记录错误，不执行动态路径。

---

## 七、后端 Provider 与中间件

### Provider 契约

```php
// extensions/backend/Providers/AppServiceProvider.php（application-owned）
final class AppServiceProvider implements ApplicationProvider
{
    public function register(ServiceRegistry $registry): void
    {
        // 只声明 binding，禁止解析服务或访问数据库
        $registry->singleton(AppRepository::class, PdoAppRepository::class);
    }

    public function boot(ApplicationContributions $app): void
    {
        // 注册路由、middleware、worker
        $app->routes()->module('myapp.case', new MyCaseRouteProvider());
        $app->middleware()
            ->forModule('myapp.case')
            ->after(CoreMiddleware::PERMISSION, MyCaseScopeMiddleware::class);
        $app->workers()->register('myapp.recalculate', MyRecalculateHandler::class);
    }
}
```

### ServiceRegistry

不独立实现 IoC 容器。定义薄 `ServiceRegistry` contract，默认 adapter 包装 ThinkPHP 容器：

```php
interface ServiceRegistry
{
    public function bind(string $abstract, string|callable $concrete): void;
    public function singleton(string $abstract, string|callable $concrete): void;
    public function get(string $abstract): mixed;
}
```

### Service token 分类

- `final`：认证、Tenant context、权限执行、upgrade ledger——不可替换
- `decoratable`：日志、通知、存储——可包裹但保留核心约束
- `replaceable`：短信 provider、对象存储 provider——明确允许替换

### Provider 生命周期规则

1. 所有 Provider 先完成 `register()`，期间禁止解析服务
2. 按依赖拓扑进入 `boot()`
3. 编译期拒绝：重复 binding、循环依赖、覆盖 final token

### 中间件链（固定顺序）

```
[最外层异常 / Problem Details]
[Request ID + Security Headers]
[CORS + 限流]
[TenantGuard / PlatformGuard]                 ← CoreMiddleware::AUTH
[ModuleGuard]                                  ← CoreMiddleware::MODULE
[PermissionGuard / DataPermission]             ← CoreMiddleware::PERMISSION
[Idempotency]                                  ← CoreMiddleware::IDEMPOTENCY
[应用 middleware（forModule + after 锚点）]
[Controller / Handler]
[审计 + 指标]
```

Middleware scope 绑定到 module/route-group，编译期核对 owner 与 audience，不用字符串路径。

---

## 八、HTTP Interceptor 阶段（前端）

**请求链**（固定，不可重排）：
1. Core：Request ID、API audience 限制、Origin 校验
2. Core：Authorization、Tenant/Platform context
3. 应用：添加白名单业务 header（不能覆盖 Authorization / Tenant / Request ID / Idempotency-Key）

**响应链**：
1. Core：401 refresh + 安全重放
2. Core：统一 Problem Details
3. 应用：业务通知、埋点（不能将权限错误改为成功）

---

## 九、三段升级命令

所有三段共享同一 `release_identity`（release_id + artifact_digest）。

### 段一：`peanut upgrade plan --to <version>`（开发机）

- workspace 只读，数据库只读
- 允许从 release 服务取得并缓存 artifact
- 若含 source_migrations 类型为 `manual`，plan 输出明确说明，`code-apply` 拒绝启动，直到开发者完成修改后重新运行 plan，兼容性检查确认问题消失

输出 `.peanut/plans/upgrade-<version>.json`：

```json
{
  "schema_version": 1,
  "release_identity": {
    "release_id": "0.2.0",
    "artifact_digest": "sha256:..."
  },
  "packages": {
    "php": { "peanut-admin/kernel": "0.2.0" },
    "web": { "@peanut-admin/admin-core": "0.2.0" }
  },
  "managed_files": [
    {
      "path": "apps/frontend/src/main.ts",
      "update_policy": "replace-if-pristine",
      "app_override": false,
      "new_base_digest": "sha256:c4d7..."
    }
  ],
  "source_migrations": [
    {
      "id": "peanut.kernel@0.2.0/rename-tenant-guard",
      "type": "deterministic",
      "description": "TenantGuard 已更名为 PeanutTenantGuard，bootstrap 引用需更新"
    },
    {
      "id": "peanut.kernel@0.2.0/provider-boot-signature",
      "type": "manual",
      "description": "ApplicationProvider.boot() 参数类型变更。请手工更新 extensions/backend/Providers/ 下的实现，然后重新运行 plan。"
    }
  ],
  "database_migrations": ["peanut.kernel:20260724000001_..."],
  "blocking_manual_actions": ["peanut.kernel@0.2.0/provider-boot-signature"],
  "cannot_touch": ["domain/", "extensions/"],
  "requires_sequential_from": "0.1.0"
}
```

### 段二：`peanut upgrade code-apply <plan-file>`（开发机 / CI）

在隔离 Git worktree 执行：

1. 核验 plan 文件 digest、release artifact digest、工作区干净
2. 确认 `blocking_manual_actions` 为空（不为空则拒绝，提示重新运行 plan）
3. 升级 Peanut packages（隔离 worktree，仅 Peanut 包）
4. 执行 `deterministic` source migration（只修改 recipe-managed 文件）
5. 处理 managed_files：
   - `app_override: true`：跳过替换，将新版本写入 `<path>.peanut-new` 供参考
   - `app_override: false`：直接替换
6. 输出代码升级报告，`managed-files.lock` 随此 commit 更新

**Git worktree 是代码恢复边界**，不为代码阶段建设跨进程 ledger。

### 段三：`peanut upgrade db-apply <plan-file>`（部署环境）

基于现有 `scripts/upgrade` 能力，绑定到同一 `release_identity`：

1. 核验部署 artifact、release identity 与 `managed-files.lock` 版本一致性
2. 核验备份证据
3. 获取 MySQL advisory lock
4. 只执行 Peanut-owned migration（遇到非 Peanut 表立即拒绝）
5. 数据库 ledger 逐项记录 `planned / running / applied / failed` + checksum
6. 同步权限、菜单定义

**Plan 文件流转**：`code-apply` 完成后，plan 文件随代码 commit 进入 Git。部署时从代码仓取得，不需要额外传输机制。

---

## 十、CLI 命令归属

新命令统一挂载在 `backend/app/command/UpgradeCli.php`（现有类）的子命令分支下，或拆分为独立类继承公共基类，由 U03 合同决定。`scripts/upgrade`（bash 入口）扩展支持新子命令名。

具体：
- `peanut upgrade plan`：新 `UpgradePlanCommand` 类
- `peanut upgrade code-apply`：新 `UpgradeCodeApplyCommand` 类
- `peanut upgrade db-apply`：现有 `UpgradeWorkflow` + `UpgradeCli` 的直接演进

---

## 十一、U02 生成的标准后台页面边界

`peanut new <slug>` 生成的应用包含以下固定页面（全部从包导入，不是 stub）：

**Tenant 侧**（`/app/*`）：
- 登录、租户选择
- 工作台（Dashboard）
- 账号信息
- 成员管理、角色管理、部门管理
- 权限治理工作台
- 模块管理、审计日志

**Platform 侧**（`/platform/*`）：
- 平台登录
- 平台工作台
- 租户管理、平台操作员、平台角色
- 平台审计、版本与升级状态、运维控制台

**状态页**：403、404、服务不可用

以上页面对应 Stage C.2 正式后台已有的路由（`frontend/src/app/router.ts`），全部包含，不裁减。

应用通过 extension manifest 贡献自己的业务页面，不复制上述任何页面。

---

## 十二、旧项目引导（Adoption）

Stage C.2 及更早创建的应用没有 `managed-files.lock`。升级前需执行一次性引导命令（U02 补充）：

```bash
peanut upgrade adopt --recipe-version 0.1.0
```

该命令：
1. 扫描应用仓中已知的 recipe-managed 文件路径
2. 取得 `recipe-version` 对应的 artifact
3. 对比当前文件内容与 artifact 内容
4. 差异较小（只有应用合理改动）时写入 lock，设 `app_override: true`
5. 差异过大时输出说明，要求人工确认
6. 生成 `managed-files.lock`

引导完成后可正常运行 `upgrade plan`。

---

## 十三、遗漏项处理表

| 遗漏项 | 处理任务 | 处理方式 |
|---|---|---|
| Package 分发 | **前置决策**（U01 前） | 记录决策文档；本文以 tarball bundle 为默认 |
| Recipe artifact 生命周期 | U03 | release policy 随 manifest 发布，建议保留最近三个 major |
| 前后端版本一致性 | U01 | release_identity 强制一组包版本一致 |
| 扩展兼容与冲突 | U01 | `defineAdminExtension` 和 Provider 编译期校验 |
| 滚动部署兼容 | U04 | expand/contract 兼容窗口说明 |
| 旧项目引导 | U02（补充） | `peanut upgrade adopt` 命令 |
| Package manager 副作用 | U03 | 所有包操作在隔离 worktree 执行 |
| 配置与 secrets | U01（约束） | secrets 不进入 manifest 或诊断日志 |
| 路径与平台 | U03 | 路径统一 POSIX，git 处理换行规范化 |
| 版本撤回与最低升级路径 | U03 | `requires_sequential_from`，plan 检查并拒绝跳版 |

---

## 十四、明确不做的事

| 能力 | 理由 |
|---|---|
| `ack.json` 续跑机制 | 修复本身就是证明；重新 plan 自动验证，不需要额外文件 |
| `three-way` 合并策略 | 若需要三方合并，说明扩展边界设计有问题，应修扩展点而非依赖合并 |
| `seed-once` 追踪入 lock | `.env.example` 等文件首次生成后归应用，不需要 lock 追踪 |
| 内容哈希自动检测"已修改" | 换行符等格式变化会误判；改用显式 `app_override` 声明 |
| 全目录 page glob | 扩大暴露面；用 extension manifest |
| 字符串路径 middleware scope | 容易写错；用 module/route-group ownership |
| 独立 IoC 容器 | 包装 ThinkPHP 容器，不维护第二套实现 |
| 纯 PHP merge fallback | Git 在开发/CI 是合理前置；不实现备用引擎 |
| 代码阶段跨进程 ledger | Git worktree commit 是代码恢复边界 |
| ReleaseManifest 手工填写 | 必须由 tooling 确定性生成 |

---

## 十五、任务顺序

### 前置决策（U01 启动前）

选定 package 分发方式，记录决策文档。

### U01：应用扩展契约与薄组合根

**交付**：extension manifest 契约、appearance/slot 分层、HTTP interceptor 阶段、backend Provider 接口、service token 分类、managed-files.lock schema v2、recipe artifact 格式。现有标准后台使用同一组合模型验证契约可行。

### U02：完整应用创建器

**交付**：`peanut new <slug>` 生成完整标准后台（十一节所列全部页面），生成 managed-files.lock，生成 extensions/ 骨架，`peanut upgrade adopt` 命令。

### U03：代码升级器与 Release Artifact

**交付**：`peanut upgrade plan`、`peanut upgrade code-apply`，recipe artifact 自动生成工具，ReleaseManifest schema v2。

### U04：数据库部署绑定与完整演示

**交付**：`peanut upgrade db-apply` 绑定 release_identity，完整生命周期演示（含冲突停止和 manual action 阻断）。
