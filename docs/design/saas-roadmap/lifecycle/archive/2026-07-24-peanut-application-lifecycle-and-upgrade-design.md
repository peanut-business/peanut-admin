# Peanut Admin 应用创建、扩展与升级设计

## 文档状态

- 状态：`design-complete / implementation-pending`
- 分析基线：Peanut Admin [`v0.1.0-stage-c.2`](https://github.com/peanut-opensource/peanut-admin/releases/tag/v0.1.0-stage-c.2)，commit `69c5b2c271413f6ff741de65437f29e04f975300`，tree `96fa1ea5ff0db2bb3801869d071f4bedda98b6b2`
- 目的：定义从“创建 DCS”到“长期跟随 Peanut Admin 升级”的完整生命周期，并把应用扩展、中间件、拦截器、数据库与文件所有权一次讲清楚。
- 边界：本文是设计与实施顺序，不表示这些能力已经全部实现。现有 `create-project` 和 `scripts/upgrade` 的真实能力在“当前差距”一节单独说明。

## 一句话结论

DCS 不应该是一份以后只能手工维护的 Peanut Admin 源码副本。正确模型是：Peanut Admin 提供可升级的前后端核心包、一个很薄的应用壳、明确的扩展接口和少量受管文件；DCS 的业务代码、业务表和应用配置放在独立区域。升级时，工具升级 Peanut 包、Peanut 自己的数据库表和少量受管文件，绝不重生成整个 DCS，也绝不覆盖 DCS 的业务代码与业务数据。

## 普通用户实际会经历什么

### 第一步：用脚手架创建 DCS

用户应该只需要执行一次类似命令：

```bash
peanut new dcs --profile standard-admin
```

命令生成的应当是可以直接继续开发的完整项目，而不是只有欢迎页和示例接口的演示壳。产物至少包括：

- 与 Peanut Admin 标准后台相同的登录、租户选择、布局、菜单、路由、权限和状态页。
- 已接好的后端认证、Tenant 上下文、权限、中间件、统一错误和模块系统。
- DCS 自己的业务扩展目录，后续业务代码只放在这里。
- Peanut 项目清单，记录是由哪个 Peanut 版本、哪些功能和哪些 recipe 创建的。
- 受管文件锁，记录每个受管文件的旧基线、内容 hash、所有者和可否被应用修改。

创建完成后，开发者不应该去复制或修改 Peanut 核心包。需要改品牌、布局、登录页或 404 页面时，应在 DCS 的映射或扩展配置中登记自己的实现。

### 第二步：开发 DCS

DCS 日常开发只碰三类内容：

1. DCS 业务模块、页面、接口、任务和数据库表。
2. DCS 对 Peanut 扩展点的注册，例如页面映射、布局映射、后端 Service Provider、中间件贡献和 Worker 注册。
3. DCS 自己的环境配置与 secrets。

菜单、角色、权限、组织、团队、岗位等运行数据继续存数据库并由接口动态读取。它们不是升级器需要覆盖的模板文件。

### 第三步：Peanut Admin 发布新版本

每个 Peanut 版本应发布一份不可变的 release manifest，里面同时固定：

- PHP、Web 和工具包的精确版本与摘要。
- generator/recipe 的版本与受管文件清单。
- Peanut 数据库 migration 清单及 checksum。
- 允许的来源版本、必须经过的中间版本和扩展兼容范围。
- 需要人工处理的 breaking change，不把它们只藏在 changelog 里。

### 第四步：先看升级会做什么

升级必须先生成计划，不能一上来就修改项目：

```bash
peanut upgrade plan --to 1.1.0
```

计划用人能看懂的方式列出：

- 哪些前端和后端包会升级。
- 哪些 Peanut 数据库 migration 会运行。
- 哪些受管文件可以自动合并，哪些发生冲突。
- 哪些 DCS 扩展与目标版本不兼容。
- 是否必须先备份、是否必须逐个 major 版本升级。
- 明确列出不会触碰的 DCS 业务目录和业务表。

`plan` 只产生计划文件，不改 `package.json`、`composer.json`、锁文件、源码或数据库。

### 第五步：在隔离分支执行升级

确认计划后执行：

```bash
peanut upgrade apply .peanut/plans/upgrade-1.1.0.json
```

工具在独立 Git 分支或临时 worktree 中完成：

1. 核验当前版本、工作区、release manifest、包摘要和备份证据。
2. 升级 Peanut 前后端包。
3. 按版本顺序运行 Peanut 提供的源码 migration。
4. 对少量受管文件做三方合并。
5. 在部署阶段只运行 Peanut 所有的数据库 migration。
6. 所有步骤成功后才更新项目版本锁和受管文件锁。

如果有冲突，升级分支保留给开发者处理；原开发分支和 DCS 业务数据库不应被自动回滚、重置或覆盖。

### 第六步：部署

部署阶段先备份，再由单一升级执行者持锁运行数据库变更。Peanut migration 只允许修改 Peanut 声明拥有的表；DCS migration 仍由 DCS 自己管理。升级失败必须有明确的最后成功步骤、失败步骤和恢复说明，不能只返回一个 500 或让用户猜数据库改到哪里。

## 什么会升级，什么绝对不会升级

| 内容 | 所有者 | 升级器行为 |
|---|---|---|
| Peanut PHP/Web 核心包 | Peanut | 按 release manifest 升级精确版本 |
| Peanut 自己的数据库表 | Peanut | 按带 checksum 的 migration 顺序升级 |
| 少量应用组合根和配置样板 | Peanut recipe 与应用共享 | 按旧基线、新基线、应用修改做三方合并 |
| 登录页、布局、404 等应用外观 | DCS 映射或扩展 | 不覆盖；升级后继续解析到 DCS 注册的实现 |
| 菜单、角色、权限、组织、团队、岗位数据 | 运行数据库 | 不覆盖；由 API 和后台管理继续维护 |
| DCS 业务源码、页面、接口、任务 | DCS | 永不修改 |
| DCS 业务表和业务数据 | DCS | 永不修改 |
| 环境 secrets、生产地址、密钥 | 部署环境 | 永不写入仓库或升级包 |

## 当前 Peanut Admin 的真实能力与差距

### 创建应用：已有命令，但产物还不是成熟脚手架

当前 `scripts/create-project` 会调用 `ProjectGenerator`，复制整个 `starter/`，再复制约 20 个 PHP/Web package 的源码快照，并按 feature 参数删掉未选择的 Settings、Reference Codes、File/Media、Task/Job、Notification/SMS、Import/Export 和 Integration Security。

它会生成 `peanut-project.json`，但目前只记录 Peanut 输入 commit/tree、项目名称、namespace、品牌、profile、Tenant client 和 features。它没有记录：

- 哪些文件归 Peanut recipe 管理。
- 每个文件生成时的原始 hash。
- recipe 版本。
- 应用 override、文件重命名和删除记录。

更重要的是，当前生成的 `starter/frontend/src/App.vue` 仍是简单的 `AdminShell + example greeting`，`starter/backend/route/app.php` 也只有 health 和 example greeting。正式后台已有的登录、动态菜单、权限、Host API 和完整中间件链没有原样成为生成项目的应用壳。

所以，当前命令能生成内部开发样例，但还不能被描述为“完整标准后台脚手架”。

相关代码：

- `tools/project-generator/src/ProjectGenerator.php`
- `starter/frontend/src/App.vue`
- `starter/backend/route/app.php`
- `scripts/create-project`

### 当前升级命令：主要升级数据库，不会升级应用骨架

当前 `scripts/upgrade` 已有值得保留的安全基础：

- 固定 source/target commit 与 tree。
- 核验 release manifest、backup manifest、工作区状态和目标 migration inventory。
- 对数据库升级加 MySQL advisory lock。
- 顺序执行 Kernel、Data Permission 和模块 migration。
- 同步权限、菜单、Settings 和 Reference Codes 定义。

但它目前不负责：

- 升级 Composer/NPM 包及 lock。
- 迁移前后端源码 API。
- 合并 generator 或 starter 文件。
- 处理 DCS 对旧脚手架文件的修改。
- 记录受管文件的 rename、delete、shared ownership。
- 提供真正只读的 package/file `plan` 与可续跑的源码 migration ledger。

因此，现有命令是“有严格证据的数据库升级执行器”，还不是完整的“DCS 应用升级器”。

相关代码：

- `scripts/upgrade`
- `scripts/upgrade-preflight`
- `backend/app/command/UpgradeCli.php`
- `backend/app/command/UpgradeWorkflow.php`
- `backend/app/upgrade/ReleaseManifest.php`

### 前端：已有可信模块映射，但应用组合根仍写死

当前已有 `AdminModuleContribution`，能贡献 routes、stores、locales 和 shell slots；菜单也只把服务端 route name 解析到本地注册表，不会执行服务端传来的任意 import。HTTP client 已处理 audience path、origin、Authorization、Request ID、401 refresh，以及只对安全请求或带 Idempotency-Key 的请求自动重放。

这些基础是正确的。但以下内容仍直接写在正式后台源码中：

- `main.ts` 的 Provider 和组件装配。
- 登录、租户选择和平台登录页面。
- Workspace layout、Dashboard、403、404、service unavailable。
- 核心 route、router guard 和 APP_MODULES 列表。
- Element Plus 注册和部分 HTTP 行为。

这意味着 DCS 想换登录视觉、布局或状态页，仍容易直接修改脚手架文件，之后升级就会冲突。

### 后端：已有模块清单，但缺少成熟的 Provider/DI/中间件扩展层

正式后台当前全局处理 Request ID、安全响应头和 Problem Details；生成的 OpenAPI 路由再加入 Tenant/Platform Guard、Module Guard、Permission Guard 和 Idempotency Middleware。这些都是脚手架核心能力，不能让 DCS 随意绕过。

问题在于当前 Host 组合方式很硬：

- 多个 Runtime factory 自己读环境变量、创建 PDO、实例化 repository/evaluator/service。
- 模块 Provider 最终通过 `new $providerClass()` 动态构造。
- Provider 没有统一依赖注入，也没有清晰的 `register/boot/shutdown` 生命周期。
- 没有正式的 middleware、route、event listener、CLI command、Worker handler 贡献 API。
- 生成 starter 没有复制正式后台中间件栈，异常处理器只返回简单 500。

所以“后端映射”不能只是 `module.json` 里写一个类名。真正需要的是稳定服务 token、容器、Provider 生命周期和受限的贡献注册表。

## 参考项目给出的真实答案

本次使用 CodeGraph 对 12 个固定源码快照做了调用关系分析，并用官方资料核对公开升级承诺。各项目不能整套照搬，但每个项目解决了不同的一块问题。

| 项目 | 值得采用的部分 | 不应照搬的部分 |
|---|---|---|
| ng-alain `fa4b023` | 核心能力放在 `@delon/*` 包中，应用 `app.config.ts` 很薄；HTTP interceptor 顺序和启动期加载用户/ACL/菜单清晰 | 布局、登录和业务路由仍较多留在应用源码；不是完整骨架自动升级方案 |
| Vben Admin `dcdbfe5` | 服务端只返回字符串组件 key，前端只从本地可信 page/layout map 解析；核心登录/404 与动态路由分开 | 默认 glob 暴露整个 views 表过宽；缺少严格扩展 manifest 和通用 slot/provider 协议 |
| LikeAdmin PHP `79734cb` | 数据库菜单、角色菜单和动态路由贴近国内后台使用方式 | 动态路由校验弱；发现清空角色菜单时旧授权残留的实现缺陷，不能照搬 |
| Filament `1d424bd` | 应用侧 `PanelProvider`、middleware/authMiddleware、资源发现和命名 render hooks 是成熟的“不改框架”扩展面 | Livewire/PHP UI 模型不能直接替代 Peanut 的 Vue SPA，只能借鉴契约 |
| JHipster `0d0ab14` | 重建旧生成树和新生成树，再利用 Git 形成三方合并祖先 | 全目录清理、固定 magic branch、自动 commit、`checkout -f`、`reset --hard` 风险过高 |
| Symfony Flex `4a6d98e` | 用 lock 固定旧 recipe ref，只修改 recipe 拥有的文件，并使用三方 apply | 冲突未解决就前移 lock、删除文件直接 `git rm`、rename/binary 支持不足 |
| Rails `b6cb9ea` | 只更新 config/bin/public 等有限骨架区域，保持范围克制 | 没有旧基线和所有权 lock，主要靠人工逐文件处理 |
| Laravel `2eb4577` | 框架包和应用 skeleton 清楚分离，核心包升级不会覆盖业务代码 | 没有应用骨架升级器，新 skeleton 变化主要靠人工迁移 |
| Nx `37552cc` | `plan/apply` 分离、版本化 migration、requires/incompatibleWith、跨 major 分段升级 | 没有耐久逐项完成账本，失败主要依赖 Git 人工恢复，不处理数据库 |
| Backstage `10dbfd2` | release manifest 统一一组包版本；生成后端入口非常薄，只组合插件 | 官方明确 create-app 模板不会自动更新，仍要看 changelog/Upgrade Helper |
| ABP `3039947` | 模块生命周期、DI、授权/审计/UoW 拦截器和 middleware pipeline 是后端扩展参考 | `abp update` 主要统一 NuGet/NPM 版本，不迁移应用骨架，也没有通用 plan/apply |
| Directus `86abefc` | 扩展来源隔离、hook/endpoint/operation 注册、reload 生命周期和数据库 migration ledger | migration 缺少完整事务/锁/failed 状态；扩展兼容门禁不够强 |

参考官方资料：

- [ng-alain 升级指南](https://ng-alain.com/docs/upgrade/en)
- [JHipster 应用升级](https://www.jhipster.tech/upgrading-an-application/)
- [Symfony Flex](https://symfony.com/doc/current/setup/flex.html)
- [Nx 自动迁移](https://nx.dev/features/automate-updating-dependencies)
- [Backstage 更新指南](https://backstage.io/docs/getting-started/keeping-backstage-updated/)
- [ABP CLI](https://abp.io/docs/latest/CLI)
- [Filament Render Hooks](https://filamentphp.com/docs/4.x/advanced/render-hooks)
- [Vben 路由与菜单](https://doc.vben.pro/en/guide/essentials/route.html)
- [Rails 升级指南](https://guides.rubyonrails.org/upgrading_ruby_on_rails.html)

## 推荐的整体结构

### 1. 大部分能力放入可版本化包

前端参考 ng-alain、Vben 和 Backstage，把登录流程、路由运行时、权限、菜单解析、HTTP client、布局框架和状态页基类放入 `@peanut-admin/*` 包。后端参考 ABP、Filament 和 Backstage，把认证、Tenant context、权限、module runtime、middleware pipeline、异常协议、任务运行时放入 Composer 包。

应用仓只保留很薄的组合根。包升级是正常的 Composer/pnpm 版本升级，不需要智能合并大量源码。

### 2. 把项目内容分成三种所有权

#### Peanut package-owned

只存在于安装包中，DCS 不直接修改。升级时直接替换包版本。

#### Peanut recipe-managed

必须出现在应用仓，但数量应很少，例如入口文件、部署样板、框架启动配置。每个文件在 `.peanut/managed-files.lock` 中记录：

- recipe ID 与版本。
- 路径和 ownership：`exclusive/shared/advisory`。
- 旧基线 blob/hash。
- 目标基线 blob/hash。
- rename/delete/binary 策略。
- 应用是否声明 override。

升级时才有条件做可靠三方合并。

#### Application-owned

DCS 的业务代码、业务配置、页面、表和 extension 永远不归升级器管理。

### 3. 建议的应用目录边界

```text
.peanut/
  project.json
  managed-files.lock
  extensions.lock
  plans/
apps/
  admin-frontend/          # 很薄的前端组合根
  admin-backend/           # 很薄的后端组合根
extensions/
  frontend/                # DCS 页面、布局、slot、前端拦截器贡献
  backend/                 # DCS providers、中间件、事件、commands、workers
domain/
  dcs/                     # DCS 业务代码，Peanut 永不修改
packages/                  # 仅 DCS 自有 package，不复制 Peanut 核心源码
```

路径只是建议，核心是所有权分离；实现时可结合现有仓库结构调整，但不能再把 Peanut 核心源码快照和 DCS 业务源码混成同一所有权。

## 前端映射到底怎么映射

“映射”不是让服务端发送一个文件路径给浏览器动态执行。正确模型是：DCS 在编译时注册可信 key，服务端菜单只能选择这些 key。

```ts
defineAdminExtension({
  id: 'dcs.admin',
  pages: {
    'dcs.case.list': () => import('./pages/CaseListPage.vue'),
  },
  layouts: {
    'dcs.workspace': () => import('./layouts/DcsWorkspaceLayout.vue'),
  },
  appearances: {
    'auth.login': () => import('./pages/DcsLoginAppearance.vue'),
    'error.not-found': () => import('./pages/DcsNotFoundAppearance.vue'),
  },
})
```

服务端菜单只返回 `component_key=dcs.case.list`、`layout_key=dcs.workspace`。前端先验证 key、route name、permission key 和保留路径，再从本地注册表解析。未知 key 必须 fail closed 到诊断页，不能尝试任意 `import()`。

核心安全页面和行为分两层：

- 不可替换：Token 管理、Tenant 选择规则、权限判断、返回地址校验、401 refresh/replay 安全、保留路由。
- 可映射表现：品牌、Logo、登录视觉壳、布局外观、403/404/服务不可用页面、Dashboard 和命名 shell slot。

这样 DCS 可以换外观而不复制认证逻辑，也不会因为升级登录安全流程而长期卡在冲突里。

## 前端拦截器和路由守卫

ng-alain 证明 interceptor 是应用扩展的重要入口，但 Peanut 不能允许 DCS 任意重排或替换安全链。建议使用固定阶段：

### 请求链

1. Core 生成 Request ID、解析 API audience 和限制 origin。
2. Core 加入 Authorization、Tenant/Platform context。
3. DCS 的 `request.decorate` 只能添加白名单业务 header 或观测信息，不能覆盖 Authorization、Tenant、Request ID、Idempotency-Key。
4. 发送请求。

### 响应链

1. Core 处理 401 refresh 和安全重放条件。
2. Core 把错误统一为 Problem Details。
3. DCS 的 `response.observe` 可做业务通知、埋点或特定错误提示，但不能把权限错误改成成功。

### 路由守卫

固定先执行 Core session、Tenant、module、permission 和可信组件解析；DCS 业务 guard 只能在这些检查之后执行。应用可以阻止进入自己的业务页，不能绕过 Core 权限进入受保护页面。

每个扩展点都必须有稳定 ID、顺序、作用域、超时/异常隔离和卸载生命周期，不能让一个扩展失败导致整个后台白屏。

## 后端映射、Provider 和中间件

### 后端映射不是文件替换

后端通过稳定 service token 和 provider 注册实现：

```php
final class DcsServiceProvider implements ApplicationProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->bind(DcsCaseRepository::class, PdoDcsCaseRepository::class);
    }

    public function boot(ApplicationContributions $app): void
    {
        $app->routes()->add(new DcsRouteProvider());
        $app->middleware()->after('peanut.permission', DcsCaseScopeMiddleware::class);
        $app->workers()->handler('dcs.case.recalculate', DcsCaseRecalculateHandler::class);
    }
}
```

Peanut 自己的安全关键 token 要分为：

- `final`：认证、Tenant context、权限执行、upgrade ledger 等不可被应用替换。
- `decoratable`：日志、通知、存储、观测等可包裹但保留核心约束。
- `replaceable`：明确允许应用选择的 adapter，例如短信 provider、对象存储 provider。

### 后端中间件链

建议由 Core 固定主干，只开放命名锚点：

1. 最外层统一异常/Problem Details 边界。
2. Request ID、追踪和安全响应头。
3. CORS、限流等入口策略。
4. 认证并建立 Tenant 或 Platform context。
5. Module 是否部署与 Tenant 是否启用。
6. Permission/Data Permission。
7. Idempotency 和请求体约束。
8. DCS route-scope middleware。
9. Controller/handler。
10. 审计、指标和响应观察。

DCS 不能上传一个完整 middleware 数组替换 Core，也不能把自己的 middleware 插到认证或权限之前。它只能声明 `before/after` 某个允许的稳定锚点，并限定到自己的 route namespace。

### 还必须进入扩展模型的后端能力

- 统一 DI 与对象生命周期，避免每个 Runtime factory 自己创建 PDO。
- `register/boot/shutdown` Provider 生命周期。
- route、event listener、exception mapper、CLI command、scheduler、Worker handler 注册。
- Worker handler 的 Tenant context、幂等、重试、超时和审计边界。
- 可替换 adapter 的配置和 secret 注入，不允许模块直接读散落的环境变量。
- 扩展兼容范围与 capability negotiation，升级前就阻断不兼容扩展。

## 后端如何升级

后端升级分成三层，不能混为一个动作：

### 1. 核心包升级

Composer 只升级 release manifest 固定的 `peanut-admin/*` 包。像 ABP 和 Backstage 一样保持一组官方包版本一致，但不能像它们现有 CLI 那样改完清单就直接 install；Peanut 必须先 plan，再 apply。

### 2. 应用源码 migration

当稳定 API 发生变化时，包随版本携带类似 Nx 的 migration：

- 精确适用版本范围。
- `requires`、`incompatibleWith` 和 checksum。
- 是否幂等、是否需要人工动作。
- 每一步的 `planned/running/applied/failed` 账本。

源码 migration 只允许修改 Peanut recipe-managed 文件或明确授权的扩展配置，不能扫描并重写整个 DCS 业务目录。

### 3. 数据库 migration

现有 Peanut migration inventory、checksum 和 MySQL lock 应保留，并补充：

- 每项 `planned/running/applied/failed` 状态与 attempt。
- 非事务 DDL 的备份和补偿说明。
- 多实例部署的 fencing/单写者语义。
- Peanut 表所有权清单；遇到 DCS 表立即拒绝。

不要把 `down()` 当成通用自动回滚。恢复应以已验证备份和明确恢复计划为主。

## 受管文件如何避免覆盖 DCS 修改

升级器对每个受管文件保留三份内容：

1. 旧 Peanut 版本生成的原始内容。
2. 新 Peanut 版本期望的内容。
3. DCS 当前实际内容。

然后使用 Git 三方合并：

- DCS 没改、Peanut 改了：自动更新。
- DCS 改了、Peanut 没改：保留 DCS。
- 双方改了不同位置：自动合并并展示 diff。
- 双方改了同一位置：留下结构化冲突，不前移 lock。
- Peanut 删除但 DCS 修改：modify/delete 冲突，不能直接删除。
- Peanut 重命名：使用 release 中明确的 rename map，不只靠相似度猜测。
- 二进制：按 `replace/keep/manual` 明确处理，不做文本 patch。

应用明确 override 的页面应退出 recipe 管理，改由映射注册；这比让升级器每次合并一份复制的登录页更可靠。

## 失败、并发和安全边界

以下问题与前后端功能同等重要，必须进入实现合同：

- 工作区同时检查 tracked、untracked、submodule 和正在进行的 merge/rebase。
- plan 与 apply 都固定 source/target、制品 digest、migration checksum 和扩展清单。
- 一次只能有一个 upgrade writer；下载和只读分析可以并行。
- lock 只在全部冲突解决和步骤成功后更新。
- 数据库步骤前必须有可验证备份证据。
- 中断后可从 ledger 判断最后成功步骤，不能盲目从头重跑。
- 默认逐 major 升级；只有 release graph 明确允许时才能跨 major。
- 离线/私有源是一等模式，不能静默回退到公共 registry。
- 路径、symlink、归档展开和 extension package 都必须限制在声明边界内。
- OpenAPI/generated client 与服务端版本属于同一 release，不能各自漂移。
- secrets 永远不进入 generator baseline、upgrade plan 或诊断日志。

## 同等级遗漏项清单

此前只讨论“核心包还是脚手架文件”是不完整的。成熟脚手架还必须同时回答：

- 应用如何第一次创建，生成的是不是完整标准后台。
- 配置和 secrets 从哪里注入，谁拥有它们。
- 前端页面、layout、登录、错误页和 shell slot 如何映射。
- HTTP interceptor 和 router guard 的安全顺序如何固定。
- 后端 DI、service replacement、Provider 生命周期如何工作。
- middleware、route、event、exception mapper 如何贡献和排序。
- CLI、Worker、scheduler 如何注册、卸载和升级。
- 动态菜单如何只引用本地可信组件，权限如何以后端为最终判定。
- OpenAPI、generated client、Runtime ledger 如何随同一 release 升级。
- package、recipe、extension 的兼容范围和弃用周期如何表达。
- 数据库表和 migration 的所有权如何防止误改 DCS。
- 文件删除、重命名、二进制、共享所有权和用户主动删除如何处理。
- 升级中断、并发执行、部分成功、备份和恢复如何处理。
- 观测、审计、Request ID 和 Problem Details 如何跨扩展保持一致。

## 推荐实施 TODO

实施不再拆成大量小任务；只保留四个有明确用户结果的纵向任务。

- [ ] `PA-SV1-U01-application-foundation`：把 `create-project` 升级为真实应用创建器。生成完整标准后台应用壳，建立 `.peanut/project.json`、受管文件锁、三种所有权和 DCS 独立扩展目录。
- [ ] `PA-SV1-U02-extension-composition`：把前端页面/layout/appearance/slot/interceptor/guard 与后端 DI/Provider/middleware/route/event/worker/exception mapper 做成稳定扩展契约；把当前硬编码 Host 收敛到薄组合根。
- [ ] `PA-SV1-U03-upgrader-plan-apply`：在现有数据库升级安全基础上实现只读 plan、包升级、版本化源码 migration、受管文件三方合并、兼容门禁、步骤 ledger 和失败恢复说明。
- [ ] `PA-SV1-U04-release-and-dcs-handoff`：发布统一 package/recipe/migration release manifest，使用新创建器生成一个 DCS 输入，并交付 DCS 的创建、升级、冲突处理和部署说明。

实现顺序必须是 `U01 → U02 → U03 → U04`。虽然目标是先完成升级器，但 U01/U02 是升级器能区分“Peanut 内容”和“DCS 内容”的必要基础，不是额外功能开发。U01/U02 应严格只做升级所需的应用边界，不顺手重写现有业务模块。

## 接纳标准

本设计真正实现后，应能用一个可审计演示回答以下问题：

1. 用一个命令创建完整 DCS 应用。
2. DCS 只通过映射换登录、布局和 404，不修改 Peanut 受管源码。
3. DCS 注册自己的后端 Provider、中间件和 Worker，不修改 Peanut Host。
4. Peanut 发布新版本后，`plan` 明确列出包、文件和数据库变化。
5. `apply` 升级 Peanut 内容并保留 DCS 业务代码、业务表和映射。
6. 人为制造一个受管文件冲突时，工具停止并保留原分支，不前移 lock。
7. Peanut migration 只修改 Peanut 表；DCS 数据不动。
8. 同一版本重复执行能够安全 no-op，并能从 ledger 解释当前状态。

在这些条件完成前，`v0.1.0-stage-c.2` 仍只能视为功能开发包，不能把现有 `create-project` 和 `scripts/upgrade` 宣称为成熟的应用创建与长期升级方案。
