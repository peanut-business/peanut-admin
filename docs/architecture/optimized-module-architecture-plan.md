# Peanut Admin 模块架构优化方案（完整版 v2）

> **状态**：待审批（确认后交由 Codex 分析并执行）
> **作者**：Claude Opus 4.8 架构评审
> **日期**：2026-08-26
> **基准工作区**：本地 `dev`（`037e699`）；上游 codex 文档来源见 §0
> **本版目标**：这是一份可执行规格。它同时是"给人读懂决策"和"给 Codex 照做"的文档。
> 本版基于用户对 v1 的 16 条追问，逐条确认/纠正理解，并落成最终设计与决策。

---

## 0. 基线自检与术语

### 0.1 分支事实

| 项 | 值 |
| --- | --- |
| `dev` HEAD | `037e699` |
| `codex/service-layer-registry` HEAD | `18947bd` |
| 共同祖先 | `4b5cefd` |

codex 分支并不领先 `dev`；两者只是各自在 `4b5cefd` 之上放了一个文档提交。
**以本地 `dev` 为准继续工作。** codex 的原始输入文档
（`.codex/worktrees/e147/.../module-development-release-separation-plan.md`）仅作立意来源。

### 0.2 v1 的三处纠正

1. "codex 已删除 reconcile/profile 命令（约 837 行）"是**错的**。当前树上这些命令文件全在。
2. v1 把"发布模式"误解为"在生产环境发布"。正解见 §1。
3. v1 完全没回答"应用层资源如何生效"。正解见 §3。

### 0.3 术语表（先统一语言，后面不再解释）

| 术语 | 含义 |
| --- | --- |
| **Module（模块）** | 一项业务能力的完整单元：后端子树 + 前端子树 + 一份 `module.json`。开发的最小单位。 |
| **Module key** | 模块的**全局唯一身份**，命名空间化，如 `official.payment`、`acme.article`。既是 ID 也是路径来源。 |
| **Bundle / Plugin（能力包）** | 一个或多个 Module 的可分发打包容器。技术名仍叫 Plugin，业务名叫能力包。 |
| **Contribution（贡献）** | 模块向应用层"贡献"的东西：后端贡献菜单/权限/设置到 DB，前端贡献路由/组件到 SPA。 |
| **DB catalog（目录表）** | 应用运行时读取的**注册表类**数据库表：菜单目录、授权/权限目录、设置定义。**不含业务数据。** |
| **业务数据表** | 模块自有的真实数据表，如 `pa_payment_scene`、`pa_recharge_order`。 |
| **Migration 账本** | `pa_module_migration`：记录每条建表脚本的 checksum 与执行状态，append-only。 |
| **Applier（资源应用器）** | 把"编译后的模块清单"同步进 DB catalog 的统一组件（本方案新抽象，见 §5.2）。 |

---

## 1. 四个平面：发布 ≠ 生产发布（核心认知）

模块生命周期不是"开发 → 生产"两段，而是**四个平面**。关键：**"发布模式"发生在开发/集成环境，不是生产环境。**

```text
① 开发平面 (Development)
   在开发环境写代码：后端 Module 子树 + 前端 Module 子树，实时联调。
   不打包、不写 lock、不碰安装表。

② 打包平面 (Packaging)
   在开发环境，把一个（或几个）Module 打成可分发的包（带指纹的产物）。
   纯产物：不改动任何运行库。

③ 安装平面 (Installation)   ← "发布模式"真正指的是这里
   仍在【开发 / 集成环境】里，把包安装 / 卸载 / 删除。
   服务对象是"拿到包的另一个开发者"。会改动：工作区文件 + DB catalog。
   这不是"上生产"，是"把别人给的包装进我的开发环境并生效"。

④ 生产构建平面 (Production Build)
   package-release.sh 把整个应用【编译】成不可变成品
   （web build → server/public/admin，composer --no-dev）。
   生产运行的就是这份编译产物。生产不安装模块、不跑 install。
```

普通开发者只面对 ①②③ 里各自最简单的一步。**④ 完全由发布工程 / CI 负责。**

---

## 2. 面向普通开发者的最小心智模型

普通开发者**只需要这些动作**，其余全是框架内部机制：

| 动作 | 平面 | 命令（目标态） | 开发者要理解的 |
| --- | --- | --- | --- |
| 建模块 | ① | `php think module:create <vendor.name>` | 只有自己的 `module.json` 和目录 |
| 开发 | ① | 启动脚本（core link + php serve + vite dev） | 只有自己的业务代码和共享合同 |
| 让菜单/权限本地生效 | ① | `php think module:sync`（见 §3.4） | 一条命令 |
| 打单模块包 | ② | `php think module:pack <key>` | 一个命令，产出一个自包含 `.tar` |
| 打多模块能力包 | ② | `php think bundle:pack <key…>` | 一个命令（可选，同样自包含） |
| 安装（收包方） | ③ | `php think module:install-package <pkg>` | 一个命令 |
| 卸载 | ③ | `php think module:uninstall-package <key>` | 一个命令 |

**开发者不应再被要求记忆**（框架 / 发布工程职责）：`plugin.json` 结构与 sha256、`plugins.lock`
生成规则、`pa_plugin_installation`/`pa_module_installation`/`pa_plugin_module` 字段、catalog 同步与
checksum 账本细节、`server/config/modules.php` 的 `frontend_components` 手写清单、`package-release.sh` 的整应用编译。

> **原则**：打包后的复杂度必须是框架内部机制。`module:pack` 内部可以照旧调用 `plugin:make`+`plugin:lock`
> 生成清单和指纹，但这一层对普通开发者不可见。

---

## 3. 应用层资源：贡献机制（回答 Q2/Q5/Q6/Q7/Q8）

> 用户 v1 之问："模块开发会涉及应用层变动吗？如何让它生效？"
> **答案：会。机制是"模块内声明式配置 + 安装期同步覆盖到应用的 DB catalog"。**
> 这套机制**已存在**于 `PluginLifecycleService::registerCatalog()`，但从未文档化，且只在一条路径生效（缺口见 §4）。

### 3.1 一个模块能声明哪些应用层资源

黄金参考：`server/app/Modules/Fixture/DeliveryRecord/`（唯一字段齐全的模块）。

| 资源类别 | 在 `module.json` 声明 | 物理载体 | 生效方式 |
| --- | --- | --- | --- |
| 后端菜单 | `backend.menus` | `Resources/menus.json` | 同步进 DB 菜单目录 |
| 后端权限 | `backend.permissions` | `Resources/permissions.json` | 同步进 DB 授权目录 |
| 设置定义 | `backend.setting_definitions` | `Resources/setting-definitions.json` | 同步进 DB 设置定义表 |
| 数据库表 | `backend.migrations` | `Database/Migrations/*.sql` | 执行建表 + 写 migration 账本 |
| 后端路由/服务 | `backend.provider`/`backend.routes` | `ModuleProvider.php`/`Http/routes.php` | 运行时由 core 加载 |
| 前端页面/路由 | `frontend.entry` | `web/src/modules/<key>/contribution.ts` | 构建期注入 SPA |
| 跨模块合同/事件 | `contracts.exports`/`contracts.events` | `Contracts/*.php` | 见 §3.5 |
| 租户开通行为 | `tenant.*` | manifest 内联 | 控制面开通时读取 |

### 3.2 后端资源如何生效（Q2 确认：是的，会导入数据库）

```text
模块声明（静态文件）
  Resources/menus.json / permissions.json / setting-definitions.json
  Database/Migrations/*.sql
        │
        ▼  安装期同步（PluginLifecycleService::registerCatalog / applyMigrations）
DB catalog（运行时真值）
  ├─ 菜单目录       ← MenuCatalogSynchronizer.synchronize(compiled)
  ├─ 授权/权限目录  ← ModuleAuthorizationCatalogSynchronizer.synchronize(compiled)
  ├─ 设置定义       ← PdoSettingRepository.synchronize(registry)
  └─ 业务表 + 账本  ← applyMigrations()（建表 + pa_module_migration checksum）
        │
        ▼  运行时读取
应用（core kernel）：按 catalog 做菜单/权限 fail-closed 校验
```

代码取证（`server/app/platform/service/plugin/PluginLifecycleService.php`）：`registerCatalog()`（约 363 行）
构建全量已锁模块清单后依次调用三个 Synchronizer 覆盖 catalog；`applyMigrations()`（约 271 行）逐条执行 SQL
并写 checksum 账本，保证 append-only。

**对用户问题的直接回答**：正是"模块内配置 → 应用读取并覆盖"。模块用声明式文件描述要贡献的菜单/权限/设置/表，
安装生命周期把这些声明**同步覆盖**进应用 DB catalog；运行时 core 只读 catalog，不读模块目录。

### 3.3 前端资源如何生效（Q6/Q7 确认）

前端不进 DB catalog，走**构建期注入**（Vite 虚拟模块 + `import.meta.glob`，是 Vite 生态标准做法，非自造轮子）：

- 模块在 `contribution.ts` 声明路由/组件（`PluginFrontendContribution` 类型来自 `@peanut-admin/admin/core`）；
- core 已内置聚合/过滤策略：`collectPluginContributions()`、`routesForTenantModules()`；
- 后端菜单的 `component_key` 与前端组件 key 由**同一 module key** 关联。

**Q7 你的理解正确。** 代码印证（`web/src/store/modules/app/server-menu.ts` 注释）：
"服务端菜单只作为授权与可见性来源；组件始终来自已注册的静态路由。"
即：前端 contribution 注册路由/组件 → 后端 catalog 决定可见性+权限 →
`routesForTenantModules()` 按"租户已开通模块 + 已授予权限"过滤。**前端不做权限真值。**

> **Q6 结论：不引入 Module Federation。** Federation 解决的是"运行时动态加载远程微前端"，
> 与我们"编译期打包进来的模块"是两个问题，引入它是杀鸡用牛刀。保持现有 Vite 虚拟模块方案，
> 仅把 dev 期发现从"读 lock"改为直接 `import.meta.glob`（去掉 lock 依赖）。

### 3.4 module:sync —— 让本地开发库看到菜单/权限（回答 Q1/Q10）

**这不是新概念，是修复缺口 G1 的必然产物。** 现状：catalog 同步只发生在 `plugin:install`，
所以你在本地写了 `menus.json`，本地库里菜单**根本不出现**。`module:sync` 就是"在本地开发库跑一次统一 applier"：

```text
php think module:sync [--module=<key>]
  → 读本地 module.json + Resources/*
  → 调用统一 applier（§5.2）把菜单/权限/设置同步进【本地开发库】
  → 让开发时菜单/权限即时可见
```

**它不会与安装表冲突**（这是 v1 过度设计的误判，已删除"独立 dev 记录"方案）。它写的就是同一批 catalog 表；
是否顺带写 `pa_module_installation` 由 applier 统一决定。**结论：不再引入第二套记录形态。**

#### 同步以"唯一标识"为主键，不是自增 ID（回答 Q-同步主键）

代码事实（当前 core kernel）：所有 catalog 同步都按**字符串唯一标识 upsert**，不靠自增 ID：

- 菜单表 `pa_menu_definition`：主键是 `key`，`ON DUPLICATE KEY UPDATE`（`PdoMenuCatalogRepository::synchronize`）；
- 权限表 `pa_permission`：主键是 `key`，同样 upsert（`PdoAuthorizationCatalogRepository`）。

**因此反复同步不会重复插入**——同一个 `key` 命中就更新，不命中才插入。这正是"应以唯一标识为准"的正确实现。

**标识必须带模块命名空间**（用户 Q 命中的点，已拍板）：

- 菜单 key **已经命名空间化**：`official.payment.settings`、`official.payment.recharge`；
- 权限 key 仍有**历史扁平写法**：`setting/pay/config`、`finance/recharge/lists`（未带模块前缀）。

**代码事实——为什么"不带模块前缀"一定会冲突、且守卫拦不住根因**：

- `pa_permission` 的唯一约束是 `UNIQUE KEY uk_permission_key (`key`)`——**只约束 `key` 单列**，
  不是 `(module_key, key)` 复合唯一（见 `KernelSchema.php:104`）。
- 因此两个模块声明同一个扁平 key（例如两个文章模块都写 `article/lists`）时：
  1. `assertOwner('pa_permission', key, module_key)` 会先 `SELECT` 到已有归属并 `throw DomainException`——**fail-closed**；
  2. 即便绕过守卫，`INSERT` 也会撞 `uk_permission_key` **硬报 UNIQUE 冲突**。
- 结论：守卫只是把冲突**从静默串号变成显式安装失败**。它是**检测**，不是**预防**——
  真正预防冲突的唯一手段是"命名空间化 key"。

**拍板决策（用户本轮明确）：所有权限 key 一律命名空间化，`list` 类也不例外。**

- 项目尚未正式消费，**接受破坏性改名**，目的就是让框架更好用；
- 新旧一致：权限 key 与菜单 key 采用同一命名空间规则（如 `official.payment.recharge.list`、`acme.article.list`）；
- 官方 Payment 现有扁平 key（`finance/recharge/lists`、`setting/pay/config` 及 `recharge.recharge/*` 兼容键）
  **在本次一并改名到命名空间**，而不是靠兼容层长期并存；
- 连带面：`permissions.json`、`menus.json` 的 `required_permission`、`routes.php` 的中间件 key、
  以及任何角色-权限绑定里引用旧 key 的地方，都在同一次改名内同步更新——这是 Codex 必须整体完成、不可半改的一个原子任务。

#### 同步/开通后必须失效缓存（回答 Q-缓存）

catalog 是"DB 为真值、运行时读取"的结构（`activeDefinitions()` 只读 `status='active'`）。因此：

- **`module:sync` 或 Web UI 开通租户模块后，必须失效相关缓存**（菜单/权限/设置），否则前端看不到更新；
- 这是执行约束，Codex 实现同步与开通入口时必须一并处理缓存失效，并覆盖测试。

### 3.5 后端 contracts 是什么（回答 Q5）

`contracts.exports` 登记模块**导出给其他模块依赖的 PHP 接口**（类似 SPI / 公开 API）。
例（fixture）：`DeliveryRecordCommands` 接口由 `ModuleProvider::commands()` 提供实现；
依赖方只依赖接口、不碰对方内部实现或数据表。**作用：模块间解耦通信的唯一合法通道**——
禁止跨模块直接读对方的表或调内部类，一切经 contract。

---

## 4. 当前实现的真实缺口

| # | 缺口 | 证据 | 影响 |
| --- | --- | --- | --- |
| G1 | catalog 同步只在 `plugin:install`；`module:install`/`DeploymentModuleInstaller` 只写 `pa_module_installation` | 全仓仅 `PluginLifecycleService` 引用三个 Synchronizer | 本地装模块菜单/权限不出现 |
| G2 | 卸载不清 catalog | `uninstall()` 只置状态，不重新同步 catalog | 卸载后菜单/权限残留 |
| G3 | 无建模块脚手架 | `scripts/create-app` 是建整个应用 | 建模块要手工拼 8~10 文件 |
| G4 | 无单模块打包/安装闭环 | 只有整应用编译 + 生成清单，无"打成包 + 一步安装" | 用户设想的"打包→分发→直接装"没落地 |
| G5 | 官方 manifest 不完整、业务代码未归位 | 8 个官方 `module.json` 都缺 `migrations`/`setting_definitions`；代码仍在 `adminapi/*`、`views/*` | 与 fixture 参考不一致，无法独立打包 |
| G6 | 开发期强依赖 `plugins.lock` | Vite 只从 lock 读前端贡献 | 改前端也要先跑 lock |
| G7 | 前端组件清单双真值 | `modules.php` 手写 `frontend_components` | 与 `module.json` 重复漂移 |

---

## 5. 目标架构（接受破坏性变更）

### 5.1 单一真值：`module.json` + 黄金参考

不发明新格式。`module.json` 已是统一 manifest。所有官方模块补齐 `migrations`/`setting_definitions`
并把业务代码归位到模块子树（对齐 fixture）。

### 5.2 统一资源应用器 ModuleCatalogApplier（修 G1/G2）

抽出一个**幂等、fail-closed** 的 applier，封装"把编译后的模块清单同步进 DB catalog"，被四条路径共用：

```text
plugin:install / plugin:upgrade   (安装-发布)
module:install                    (安装-部署)     ┐
module:sync                       (开发-本地库)   ├─ 共用同一 applier
bundle 安装                        (安装-多模块)   ┘
```

- 安装/升级：同步菜单/权限/设置 + 执行 migration；
- **卸载/删除：对称地从 catalog 移除该模块贡献**（修 G2）。数据处置见 §5.7。

### 5.3 身份与路径（回答 Q13 —— 关键）

- **身份 = 命名空间化的 module key**，如 `acme.article`、`bright.article`。**它就是全局唯一 ID**，不额外发明。
- 两人都发"文章模块" → key 各为 `acme.article`/`bright.article`，**天然不冲突**，可同时安装。
- **路径从 key 派生**：`acme.article` → `server/app/Modules/Acme/Article` + `web/src/modules/acme-article`。
  **无需人工选路径**；命名空间不同，路径自然不同，不会互相占用。
- **为什么不让自选路径**：PSR-4 自动加载和 Vite alias 依赖"路径 ↔ 命名空间"确定映射，随意路径会破坏加载。
  命名空间 + 派生路径**比自选路径更安全，也同样灵活**——你要的"多个文章模块共存"由 vendor 前缀解决，不靠改路径。

### 5.4 开发平面（修 G6/G7）

```text
module:create <vendor.name>
  → 生成后端子树 Modules/<Vendor>/<Name>/（fixture 同款骨架 + module.json）
  → 生成前端子树 web/src/modules/<vendor-name>/（package.json + contribution.ts）

启动开发
  → scripts/core-dev link → php serve → vite dev
  → php think module:sync  （让本地库看到菜单/权限，见 §3.4）
```

配套：Vite dev 按 `web/src/modules/*/contribution.ts` 直接发现（不读 lock）；生产构建仍按 `plugins.lock`（确定性）。
删除 `modules.php` 的 `frontend_components` 手写清单，改由 `module.json` 派生。

#### 系统应具备"开发工具能力"，不必都塞进命令行（回答本轮 Q-开发工具）

现状：**没有模块级代码生成器**——只有 `scripts/create-app`（生成整个应用）。`module:create` 就是要补上的
**代码生成器/脚手架**，属于"开发工具"这一类能力，价值很高。用户意见采纳：

- `module:create` 是一个**能力**，不只是一条命令。它先落地为 CLI（Codex 易实现、可脚本化、可进 CI），
  后续可以在开发环境的 Web UI 里包一层（表单填 key/name → 调同一生成器），二者共用同一套模板与校验；
- 生成器以 **fixture 模块为模板**，产出对齐黄金参考的完整骨架（`module.json` + 后端分层目录 + 前端 contribution）；
- 同类"开发工具能力"还可包括：`module:check`（校验 manifest 字段齐全、前后端 key 镜像一致、
  权限 key 是否命名空间化）。这类工具都遵循"CLI 优先、UI 可选"的原则。

#### 开发工具 UI 放在哪里：复用现有 dev-tools 平面，仅开发环境可见（回答本轮 Q-UI 放置）

用户本轮拍板方向：**不新开一个独立端点**，也**不塞进租户管理/平台管理**（那些是交付给用户看的），
而是"做进一个只在开发环境可见、生产不含、构建时尽量不打包"的面。**代码事实：这个面已经存在，直接复用即可**：

- 前端已有 `/dev-tools` 路由（`web/src/router/routes/modules/dev-tools.ts`），下面已挂一个**代码生成器**页面
  （`web/src/views/dev-tools/code/index.vue`），meta 标了 `instanceTool: true`；
- 这个 `instanceTool` 标记正是"部署形态门控"机制：`routes/index.ts` 用
  `allowsInstanceTools(VITE_DEPLOYMENT_MODE)` + `routesForDeployment(..., instanceToolsAllowed)`
  决定**这类工具路由是否被注册**——非目标形态下**整段不注册**，不是仅隐藏；
- 另有 `controlPlane: 'platform' | 'tenant-selection'` 的平面级门控（`router/typings.d.ts`），
  Standalone 下相应平面直接隐藏并不注册。

**因此结论明确**：

1. **模块管理 / 生成器 / manifest 校验 等开发工具，全部挂到 `/dev-tools` 平面下**，与现有代码生成器同级；
2. 用**已有的 `instanceTool` 门控**控制可见性——**开发/单机形态注册，交付给终端用户的形态不注册**，
   天然满足"用户看不到、生产不含"；
3. "构建时尽量不打包"：因为路由是 `import.meta.glob('./modules/*.ts')` + `routesForDeployment` 过滤，
   目标形态下这些路由不进入路由表；后续可进一步用构建期 `define`/环境变量把 `dev-tools/*` 页面组件
   从 chunk 中摇树剔除（执行细节留给 Codex，约束是"生产产物不含开发工具代码"）；
4. **不需要新造"面"**，也不要混进 `tenant`/`platform` 业务平面——`dev-tools` 就是那个"单独的面"，且现成。

### 5.5 打包平面：单模块 + 多模块（回答 Q3/Q9）

**两种打包，产物结构一致，均自包含**：

```text
php think module:pack <key>          # 单模块（Q9 采纳：自包含 .tar）
php think bundle:pack <key> <key> …  # 多模块能力包（Q3：底层 plugin.schema modules 已支持无上限）
  → 读 module.json
  → 内部调 plugin:make + plugin:lock 片段（对开发者不可见）
  → 产出 <name>-<version>.tar，内含：
      server module 子树 / web module 子树 / plugin.json 身份片段（sha256/integrity）
  → 打印：包路径 + digest
```

### 5.6 安装平面：一步安装/卸载（修 G4）

```text
php think module:install-package <pkg.tar>
  → 校验 sha256/身份
  → 落地文件：Modules/<Vendor>/<Name>/ 与 web/src/modules/<key>/
  → 合并 plugins.lock
  → 调统一 applier：catalog 同步 + migration
  → 收包方一步完成，无需理解中间环节

php think module:uninstall-package <key> [--purge]
  → 见 §5.7
```

多模块包安装时，applier 逐模块处理并做依赖校验（`dependencies` 字段）。

### 5.7 卸载语义：文件与数据分离（回答 Q4/Q8/Q11）

**把"删文件"和"删数据"拆成两个独立决定**：

| 对象 | 默认 | 说明 |
| --- | --- | --- |
| **DB catalog**（菜单/权限/设置） | **总是移除** | 只是注册表，无用户数据。这就是修 G2。 |
| **代码文件** | **默认删除** | 手里有 `.tar` 随时能重装，留着污染代码树（Q11 采纳你的直觉）。 |
| **业务数据表 + migration 账本** | **默认保留（retire）** | 卸载代码不该毁掉租户真实数据（订单/会员）。 |
| 业务数据表 + 账本（`--purge`） | 显式删除 | 表 + 数据 + **账本一起清**。 |

**留数据后重装到底是"覆盖"还是"跳过"？（回答本轮 Q）**

用代码事实回答（`applyMigrations()` 逐条按 `(module_key, migration_key)` 查 `pa_module_migration`）：

| 卸载方式 | 账本状态 | 重装时迁移行为 | 数据结果 |
| --- | --- | --- | --- |
| **retire（默认）** | 账本 `applied` + checksum 匹配 | **跳过（skip）**，建表 SQL 根本不执行 | 旧数据原样复用，**不覆盖** |
| **`--purge`** | 账本已清空 | **重新执行**建表 SQL | 干净空表重来 |
| ⚠️ 错误做法（删表留账本） | 账本 `applied` 但表已不存在 | 被 checksum 命中而**跳过** | **应用直接坏掉** |

所以"留数据后重装"的准确答案是：**跳过，不覆盖**。migration 账本让已执行过的建表脚本被 checksum 命中而跳过，
旧业务数据原样保留、直接复用——这正是 retire 既安全又快的原因。

> **Q8 你命中的坑（务必写进执行约束）**：若只删表却不清 migration 账本（上表第三行），下次安装会看到
> "迁移已执行"但表不存在 → 直接坏掉。因此这是一条硬不变式：
> - `retire`（默认）：表和账本都留 → 重装跳过迁移，复用旧数据；
> - `--purge`：表 + 数据 + 账本**必须原子性一起清** → 重装干净重跑迁移。
>
> "保留备份下次会不会出问题"的答案：保留就走 retire（复用，不重跑），要干净重来就走 purge（全清后重跑）。
> 两条路径都不会落入"账本与表不一致"的坏状态。

### 5.8 应用内在线模块管理 + 租户开通（回答 Q4 与本轮"租户开通归 UI"）

平台控制面新增"模块管理"页，覆盖两类操作：

**(a) 模块生命周期**：查看已安装模块、依赖关系、状态；在开发/集成环境执行装/卸/停用。

**(b) 租户模块开通**（本轮明确）：把"哪个租户开通哪个模块"做成 Web UI，而**不是命令行**。
用户判断正确——在命令行管理租户不友好。代码现状支持这样做：运行时**已有 Web 入口**
`/api/platform/tenants/modules/{enable,disable}` + platform 控制面（`PlatformTenantModuleService`），
只写 `pa_tenant_module`。因此：

- 租户开通/关闭统一走这个 Web UI；
- 旧的命令行 `tenant-module:apply-profile`（批量套用 standalone/demo profile）**降级**：
  能力归 Web UI，CLI 版保留为可选运营脚本或直接废弃（见 §6）。

**安全边界**：

- 在线**安装**（写代码文件）只面向开发/集成环境，属高风险写操作；
- **生产环境模块管理页只读**（生产运行编译成品，不做运行时安装，§1 的 ④）；
- 租户**开通**（只写 `pa_tenant_module`，不写代码）在各环境都可用；开通后**必须失效缓存**（§3.4）。

### 5.9 生产构建平面：不变（回答 Q14）

`package-release.sh` 保持不变。**它编译的是当前代码树里已存在的模块集合。**
与"从外部拿包安装"**无直接关系**：`install-package` 把文件落地到代码树，之后某次生产构建才会把它编进成品。
两者是独立命令，只通过"代码树文件"间接相连。

---

## 6. 命令体系收敛：谁对普通开发者可见

| 命令 | 平面 | 对开发者 | 处置 |
| --- | --- | --- | --- |
| `module:create` | ① | **可见** | 新增脚手架 |
| dev 启动脚本 + `module:sync` | ① | **可见** | 新增/修 G1 |
| `module:pack` / `bundle:pack` | ② | **可见** | 新增，内部封装 make/lock |
| `module:install-package` / `module:uninstall-package` | ③ | **可见** | 新增 |
| 在线模块管理页 | ③ | **可见（dev 环境）** | 新增 |
| `plugin:make` / `plugin:lock` | ②/④ | 隐藏 | 保留，降为内部/CI |
| `plugin:install`/`upgrade`/`rollback`/`uninstall` | ③ | 隐藏 | 保留，被 `*-package`/CI 调用 |
| `module:install` | ③ | 隐藏 | 保留，改经统一 applier（修 G1）|
| `plugin:reconcile` | ③ | — | **命令移除**：其"照 lock 批量对齐（缺补/旧升/一致跳）"能力成为统一 applier 的固有幂等性质，不再单独暴露（Q12）|
| `tenant-module:apply-profile` | 开通 | — | **能力归 Web UI**（§5.8b）；CLI 版降为可选运营脚本或废弃（Q12：租户开通用 UI 更友好）|
| `package-release.sh` | ④ | 隐藏 | 不变 |

> **Q12 澄清（两个命令是两回事）**：
> - `plugin:reconcile` = "照 lock 清单把当前环境对齐到应有状态"（缺的装、旧的升、一致的跳）。这是**幂等对齐**，
>   会被统一 applier 天然吸收（重复跑 = 对齐），**原命令移除，能力不丢**。
> - `tenant-module:apply-profile` = "把某租户批量配成 standalone/demo 该开的模块"，只动 `pa_tenant_module`，
>   与模块开发/打包/安装**无关**。**归 Web UI**，命令行版可弃。

---

## 7. 分阶段实施（回答 Q16 的节奏）

| 阶段 | 目标 | 内容 | 风险 |
| --- | --- | --- | --- |
| A | manifest 补齐 + 业务代码归位 | 以 `official.article` 为 pilot 补 `migrations`/`setting_definitions`，把控制器/逻辑/视图/api 迁进模块子树，对齐 fixture | 中 |
| B | 统一资源应用器 | 抽 `ModuleCatalogApplier`，install/uninstall 对称 catalog 同步（修 G1/G2）| 中 |
| C | 开发期解耦 lock | Vite dev 按 `module.json` 发现；新增 `module:sync`；删 `frontend_components`（修 G6/G7）| 低 |
| D | 单/多模块打包安装闭环 | `module:create`/`module:pack`/`bundle:pack`/`install-package`/`uninstall-package`（修 G3/G4）| 中 |
| E | 在线模块管理 + 命令收敛 + 文档 | 控制面模块管理页；开发文档只写 §2 那几条；plugin:* 与 release 移入"发布工程"文档 | 中 |

> **落地节奏（Q16 采纳）**：先用 `official.article` 打通"建 → 开发 → 打包 → 安装 → 卸载"完整闭环并**演示**。
> **闭环完成后通知用户**；用户确认后，再把其余模块散落在 `adminapi/*`、`views/*` 等处的文件**逐模块系统化归位**。
> 迁移顺序（按依赖）：article → file → task → notification → member → payment → oauth → import-export → fixture。

---

## 8. 必须保持不变的安全边界

- **装包 ≠ TenantModule 开通 ≠ 成员 RBAC 授权**（三层各自独立）；
- migration **append-only + checksum**，已执行不可改写；`--purge` 时表与账本必须一起清（§5.7）；
- 生产环境**不运行时安装**、不扫描 lock 之外模块；`module:sync` 仅作用于本地开发库；
- 前端菜单**不能代替**后端授权，后端始终按 catalog fail-closed 校验；
- 卸载移除 catalog，但业务数据默认保留（retire），删除数据是显式可选操作（purge）；
- TenantModule 开通仍由 Platform 控制面写 `pa_tenant_module`。

---

## 9. 已确认的决策（原 v1 待拍板项，现已定）

1. **分发形态**：单模块用**自包含 `.tar`**（Q9）。多模块能力包同样自包含（Q3）。不做 composer/npm 双通道。
2. **module:sync 写哪里**：直接写本地开发库的同一批 catalog 表，**不引入第二套记录**（Q1/Q10）。
3. **卸载语义**：文件默认删除；数据默认保留（retire），`--purge` 才连表带账本一起删；retire 重装是**跳过迁移复用旧数据**，purge 重装是干净重跑（Q8/Q11 + 本轮）。
4. **reconcile**：**命令移除**，能力并入统一 applier 的固有幂等性质（Q12）。
5. **apply-profile / 租户开通**：**归 Web UI**（复用已有 `/api/platform/tenants/modules/*`），命令行版可弃（Q12 + 本轮）。
6. **前端加载**：保持 Vite 虚拟模块方案，不引入 Module Federation（Q6）。
7. **身份**：module key 即唯一 ID，路径由 key 派生，不允许自选路径（Q13）。
8. **同步主键**：catalog 按**字符串唯一标识 key upsert**，反复同步不重复（`uk_permission_key` 只约束 `key` 单列，故不命名空间化必冲突）。
9. **权限 key 命名空间化（本轮拍板，破坏性）**：**所有**权限 key 一律加模块命名空间，`list` 类不例外；官方 Payment 现有扁平/兼容 key **一并改名**，不靠兼容层长期并存；连带 `menus.json`/`routes.php`/角色绑定在同一原子任务内同步更新。
10. **缓存**：同步与租户开通后**必须失效缓存**（本轮）。
11. **开发工具能力 + UI 放置（本轮拍板）**：`module:create`/`module:check` 是"开发工具能力"，CLI 优先、UI 可选，共用同一生成器与模板；UI **复用已有 `/dev-tools` 平面**，用现成 `instanceTool` 门控实现"仅开发/单机形态注册、交付形态不注册、生产产物尽量不打包"，**不新造面、不混入 tenant/platform 业务平面**。

---

## 10. 验收标准（区分"人可见"与"机器内部"，回答 Q15）

**面向普通开发者（人类可见层）**：

1. 新增模块只需 `module:create <key>`，即生成对齐 fixture 的完整前后端骨架；
2. 开发期**无需**执行 `plugin:make`/`plugin:lock`/`plugin:install`，改前端不需先跑 lock；
3. 模块声明的菜单/权限/设置，**开发库与安装后都一致生效**（G1 判定点）；
4. `module:pack <key>` 产出自包含包；`module:install-package <pkg>` 一步装进另一环境并生效；
5. 卸载后菜单/权限**从 catalog 干净移除**；文件默认删除；业务数据默认保留，`--purge` 可全清（G2/§5.7 判定点）；
6. 控制面可查看已安装模块、依赖，并在开发环境执行装/卸/停用。

**框架内部（机器可读、对开发者透明，Q15 确认）**：

7. `plugins.lock`、`plugin.json`、sha256/digest、migration 账本全部机器可读、开发者不可见/不需手工维护；
8. `module.json` 是唯一真值：无第二套前端组件清单、无第二套模块状态、无第二套权限真值；
9. 生产构建仍由 `package-release.sh` 产出编译成品，生产不执行任何逐模块安装（§5.9 判定点）；
10. 官方模块 manifest 字段与 fixture 参考一致，业务代码全部归位到模块子树。

**闭环判定**：以 `official.article` 完成一次"建 → 开发 → 打包 → 安装 → 卸载"闭环演示，通过后通知用户。

---

## 11. 交给 Codex 执行的边界与协作方式

**当前文档能否直接执行？分色判定：**

- 🟢 **可直接执行（A/B/C 阶段）**：manifest 补齐、业务代码归位、`ModuleCatalogApplier` 抽取与对称同步、
  Vite dev 解耦 lock、`module:sync`、删 `frontend_components`。这些有明确代码落点与判定点，Codex 可照做。
- 🟢 **本轮已从"红"转"绿"**：权限 key 命名空间化——决策已拍板（§9.9），范围已圈定（`permissions.json`/
  `menus.json`/`routes.php`/角色绑定同一原子任务），Codex 可执行，但**必须整体完成、不可半改**。
- 🟡 **需先出细化设计再执行（D/E 阶段）**：
  1. 自包含 `.tar` 的**内部结构**（目录布局、manifest 位置、校验/签名字段）——文档给了约束，未给字节级规范；
  2. `--purge` 的**原子性**（表 + 数据 + migration 账本一起清）跨 DDL 边界如何保证；
  3. 在线模块管理页与 `/dev-tools` 平面的**具体页面/接口契约**；
  4. "生产产物摇树剔除 dev-tools 代码"的**构建期实现**。

**关于"让 Codex 先做细化版、我再审"——建议这样分工：**

- ✅ **可行，且推荐**。把本文档交给 Codex，让它只针对上面 4 个 🟡 项**产出细化设计（不写实现代码）**：
  `.tar` 结构规范、purge 原子性方案、在线管理页接口契约、构建摇树方案。
- 审查锚点（你或我核对时对照）：
  1. 是否**沿用本文档已定的决策**（§9 十一条），不擅自改回兼容层、不新造面、不引入 Module Federation；
  2. 是否**尊重安全边界**（§8）：三层解耦、migration append-only、生产不运行时安装；
  3. 是否**保持单一真值**（`module.json`），不制造第二套清单/状态/权限源；
  4. 细化项是否给出**可判定的验收点**，而非泛泛描述。
- 交付顺序建议：Codex 先交 🟡 项的**设计增补**（作为本文档的 §D-detail 附录）→ 我审 → 通过后再让 Codex 进入
  A→B→C→D→E 的实现。**不要让 Codex 一次性从设计到实现全包**，分段交付、分段审最可控。
