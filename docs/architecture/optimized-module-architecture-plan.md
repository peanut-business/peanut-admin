# Peanut Admin 架构优化方案：基于代码事实的务实决策

> **状态**：已审批，待 Codex (GPT-5.6 Sol) 执行  
> **作者**：Claude Opus 4.6 架构评审  
> **日期**：2025-08-25  
> **分支**：`codex/service-layer-registry`

---

## 一、代码事实：当前状态摘要

### 1.1 已有的模块化体系（已做得不错的部分）

当前项目已经完成了一套相当完整的模块化拆分，有 9 个模块（8 个 Official + 1 个 Fixture）：

```
后端：server/app/Modules/Official/{Article,File,Member,Notification,Oauth,Payment,Task,ImportExport}
      server/app/Modules/Fixture/{DeliveryRecord}
前端：web/src/modules/{official-article,official-file,official-member,...}
```

每个后端模块已经有：
- `module.json` — key、版本、依赖、后端 provider、路由、菜单、权限、自有表、合同
- `ModuleProvider.php` — 模块入口
- `Http/routes.php` — 独立路由定义
- `Resources/menus.json` + `permissions.json` — 菜单和权限声明
- `Contracts/` — 模块间接口约定
- `composer.json` — 包元数据

每个前端模块已经有：
- `contribution.ts` — 贡献路由和菜单
- `package.json` — npm 包元数据

**核心判断：模块的"骨骼"已经到位。这不是一个"从头设计"的问题。**

### 1.2 业务逻辑的存放位置（历史遗留）

业务代码的**实际位置**并没有跟随模块化拆分：

| 内容 | 预期位置 | **实际位置** |
|------|---------|-------------|
| Article Controller | `Modules/Official/Article/` 内 | `server/app/adminapi/controller/article/` |
| Article Logic | `Modules/Official/Article/` 内 | `server/app/adminapi/logic/article/` |
| Article Validate | `Modules/Official/Article/` 内 | `server/app/adminapi/validate/article/` |
| Article Model | `Modules/Official/Article/` 内 | `server/app/common/model/article/` |
| Article Vue 页面 | `web/src/modules/official-article/views/` | `web/src/views/article/` |
| Article API 调用 | `web/src/modules/official-article/api.ts` | `web/src/api/article.ts` |

**现状的根本矛盾**：Module 目录是"声明空壳"（只放了 manifest、routes、provider），而真正的代码还留在老位置。

### 1.3 链条过长的发布流程

```
module.json (后端模块) ──┐
                         ├─→ plugin.json (plugins/ 下的聚合描述)
contribution.ts (前端) ──┘        │
                                  ↓
                         plugins.lock (全局锁文件)
                                  │
                         Vite 插件读取 plugins.lock
                                  ↓
                         virtual:peanut-plugin-contributions
                                  │
                         前端路由注入
```

加上 `server/config/modules.php` 里还有一份手动维护的 `frontend_components` 列表。

---

## 二、痛点分析

### ✅ 不是痛点（不要动）

1. **前后端物理隔离**（`server/` vs `web/`）— 完全正确，IDE/Docker/CI 都受益
2. **`module.json` 的 Schema** — 字段覆盖齐全
3. **`ModuleProvider.php`** — 干净的依赖注入入口
4. **`contribution.ts`** — 前端路由贡献模式设计合理
5. **后端 `Http/routes.php`** — 已经解耦

### ❌ 真正的痛点

| # | 痛点 | 根因 |
|---|------|------|
| P1 | 业务代码没有真正进入模块目录，"模块化名存实亡" | 迁移未完成 |
| P2 | `plugin.json` 是 `module.json` 的冗余复制 | 过度设计 |
| P3 | `plugins.lock` 是人工维护的发布清单 | 应自动生成或消除 |
| P4 | Vite 必须通过 `plugins.lock` 间接发现前端模块 | 可直接 glob 扫描 |
| P5 | `server/config/modules.php` 的 `frontend_components` 列表是第三份前端清单 | 多处维护同一真相 |

---

## 三、执行方案（给 Codex GPT-5.6 Sol）

### 原则：只斩断造成痛苦的链条，不做不必要的改动

---

### 阶段一：消除冗余中间层 (低风险，纯减法)

#### 1A. Vite 插件改为直接文件系统扫描

**改动文件**：`web/config/vite.config.base.ts`

将 `lockedAdminContributions()` 从"读 `plugins.lock`"改为"glob 扫描 `web/src/modules/*/contribution.ts`"：

```typescript
// 改前：从 plugins.lock 读
function lockedAdminContributions(): string[] {
  const lock = JSON.parse(readFileSync(resolve(__dirname, '../../plugins.lock'), 'utf8'));
  return lock.plugins.flatMap(p => p.frontend || [])
    .filter(f => f.client_key === 'admin-web')
    .map(f => f.entry).sort();
}

// 改后：直接 glob
import { globSync } from 'node:fs';
function discoverAdminContributions(): string[] {
  return globSync('src/modules/*/contribution.ts', {
    cwd: resolve(__dirname, '..'),
  }).map(p => `/${p}`).sort();
}
```

**效果**：前端开发者新增模块时，只需建好 `web/src/modules/<name>/contribution.ts`，Vite 自动发现并注入路由。无需触碰 `plugins.lock`。

#### 1B. `plugins/` 目录和 `plugins.lock` 降级为"发布产物"

- `plugins/` 目录不再是日常开发的必需品。如未来需要面向第三方打包分发，由 CI 或发布脚本从各 `module.json` 自动生成。
- `plugins.lock` 同理，生产部署时由自动化脚本生成，开发期不再是必需品。

#### 1C. 消除 `server/config/modules.php` 中的 `frontend_components` 硬编码

**改动文件**：`server/config/modules.php`

这个列表可以从后端 `module.json` 动态导出，或者直接删除（后端不需要知道前端有哪些组件，前端组件的发现是 Vite 的责任）。

---

### 阶段二：将业务代码真正归位到模块目录 (中风险，核心改动)

以 Article 模块为例（其他模块照此模式逐一迁移）：

#### 后端目标结构（`server/` 内部，纯 PHP）

```
server/app/Modules/Official/Article/
├── module.json                          # 清单（已有，保留）
├── composer.json                        # 包元数据（已有，保留）
├── ModuleProvider.php                   # 模块入口（已有，保留）
├── Contracts/                           # 对外接口（已有，保留）
├── Http/
│   ├── routes.php                       # 路由（已有，保留）
│   ├── Controllers/                     # ← 从 adminapi/controller/article/ 搬入
│   │   ├── ArticleController.php
│   │   └── ArticleCateController.php
│   └── Middleware/
│       └── ArticleModuleMiddleware.php  # 已有
├── Logic/                               # ← 从 adminapi/logic/article/ 搬入
│   ├── ArticleLogic.php
│   └── ArticleCateLogic.php
├── Validate/                            # ← 从 adminapi/validate/article/ 搬入
│   ├── ArticleValidate.php
│   └── ArticleCateValidate.php
├── Model/                               # ← 从 common/model/ 中相关文件搬入
├── Infrastructure/                      # 已有
└── Resources/                           # 已有
    ├── menus.json
    └── permissions.json
```

#### 前端目标结构（`web/` 内部，纯 Node）

```
web/src/modules/official-article/
├── contribution.ts                      # 路由贡献（已有，保留）
├── package.json                         # 包元数据（已有，保留）
├── views/                               # ← 从 views/article/ 搬入
│   ├── cate/index.vue
│   └── list/index.vue
└── api.ts                               # ← 从 api/article.ts 搬入
```

#### 搬迁要点

1. **PHP namespace 跟随调整**：`app\adminapi\controller\article\ArticleController` → `app\Modules\Official\Article\Http\Controllers\ArticleController`
2. **路由引用跟随调整**：`Http/routes.php` 中的 `use` 语句指向新 namespace
3. **前端 import 路径跟随调整**：`contribution.ts` 中 `() => import('@/views/article/...')` → `() => import('./views/...')`，实现模块内相对引用
4. **逐模块迁移，每迁移一个做一次全量测试**

#### 需迁移的 9 个模块清单

| 模块 key | 后端路径 | 前端路径 |
|----------|---------|---------|
| official.article | `Modules/Official/Article` | `modules/official-article` |
| official.file | `Modules/Official/File` | `modules/official-file` |
| official.member | `Modules/Official/Member` | `modules/official-member` |
| official.notification | `Modules/Official/Notification` | `modules/official-notification` |
| official.oauth | `Modules/Official/Oauth` | `modules/official-oauth` |
| official.payment | `Modules/Official/Payment` | `modules/official-payment` |
| official.task | `Modules/Official/Task` | `modules/official-task` |
| official.import-export | `Modules/Official/ImportExport` | `modules/official-import-export` |
| fixture.delivery-record | `Modules/Fixture/DeliveryRecord` | `modules/fixture-delivery-record` |

---

### 阶段三：后端 Module 自动发现 (低风险)

当前 `server/config/modules.php` 通过环境变量 `PEANUT_MODULE_ROOTS` 指定模块扫描路径。

**建议增强**：
- 开发模式下，自动 `glob('app/Modules/*/*/module.json')`，不需要环境变量
- 生产模式下，仍从环境变量或编译产物读取，确保安全

---

### 阶段四：按模块生成 TypeScript 类型 (可选增强)

当前已有 `web/src/generated/openapi.d.ts`。完成阶段二后可扩展为按模块生成。优先级低。

---

## 四、不建议做的事（Codex 请注意避坑）

| 不建议 | 原因 |
|--------|------|
| 把前后端代码放到同一个 `modules/<name>/` 目录 | IDE 灾难、Docker 构建污染、Composer 与 npm 冲突 |
| 引入 pnpm workspace 管理 PHP+JS 混合仓 | 过度工程化 |
| 引入新 manifest 格式（`module.backend.json` + `module.frontend.json`） | 现有 `module.json` 已足够 |
| 重命名 Plugin 为 Capability Bundle | 纯术语变化，不解决实际问题 |
| 建立顶层 `contracts/` 目录 | 后端合同在 `Modules/*/Contracts/`，前端用 OpenAPI 生成，不需要第三个位置 |
| 废弃 `pa_plugin_installation` | 这是 SaaS 运营的正当需求 |

---

## 五、执行优先级

| 阶段 | 内容 | 风险 | 价值 | 顺序 |
|------|------|------|------|------|
| 1A | Vite 插件改直接 glob 扫描 | 极低 | 高 | **第 1 步** |
| 1B | plugins/ 和 plugins.lock 降级 | 低 | 中 | 第 2 步 |
| 1C | 消除 frontend_components 硬编码 | 低 | 中 | 第 3 步 |
| 2 | 业务代码归位到模块目录 | **中** | **最高** | 第 4 步，逐模块 |
| 3 | 后端开发模式自动发现 | 低 | 中 | 第 5 步 |
| 4 | 按模块生成 TypeScript 类型 | 低 | 低-中 | 可选 |

---

## 六、验收标准

1. 新增后端模块只需在 `server/app/Modules/` 下建目录并编写 `module.json`，无需编辑 `plugin.json` 或 `plugins.lock`
2. 新增前端模块只需在 `web/src/modules/` 下建目录并导出 `contribution.ts`，Vite 自动发现
3. 每个模块目录包含完整的业务代码（Controller, Logic, Validate, Model, Views, API），不再依赖 `adminapi/` 或 `views/` 下的散落文件
4. 前后端物理隔离保持不变，IDE 和 Docker 构建互不干扰
5. 租户模块开通仍由 Platform 控制面管控写入 `pa_tenant_module`
6. 成员 RBAC 和数据权限边界不改变
