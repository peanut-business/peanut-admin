# Claude 深度审核任务：Peanut Admin 脚手架中间层与长期升级机制

## 一、任务背景

请重新审核 Peanut Admin 的应用创建与长期升级架构，并给出比当前 v3.0 更可实施的方案。

当前主设计：

```text
docs/plans/2026-07-25-lifecycle-architecture-v3.0.md
```

v3.0 修正了一个重要前提：Peanut 的 PHP/Web 核心包不应继续把源码快照复制进下游应用，而应作为版本化 package 安装。这一方向正确，但它可能把问题简化过度了。

真正需要解决的不只有核心 package。类似 ng-alain 等成熟脚手架，创建应用时仍会复制大量应用底座文件，例如：

- 前端入口、`App.vue`、router、layout、页面壳、主题和应用配置；
- Vite、TypeScript、workspace、CI、Docker 和环境配置样板；
- 后端 bootstrap、route、Provider、middleware、exception handler、command、worker 和应用配置；
- 应用对框架默认组件、布局、页面和服务的选择或覆盖文件。

这些文件复制到应用仓后，框架无法阻止用户修改。用户有时会因为业务需要或为了节省时间，直接编辑脚手架生成的文件，而不是先建立理想的 extension。

因此真实问题是：

> 当 Peanut 发布新版本时，如何同时升级版本化核心包和已经被复制到应用仓、且可能被用户修改过的脚手架中间层文件；能够自动处理的自动处理，无法安全处理的给出准确差异、建议和人工解决流程，并且绝不静默覆盖应用修改。

本轮请不要假设“只要扩展点设计得好，应用就永远不会修改生成文件”。扩展点应减少冲突，但升级器仍必须安全处理现实中的直接修改。

---

## 二、审核目标

请完成三项工作：

1. 审核 v3.0 删除 recipe artifact、文件版本历史和合并能力是否过度。
2. 设计一套克制但真实可用的“版本化 package + scaffold 文件升级”机制。
3. 判断前端和后端升级应完全分开、完全统一，还是采用统一协议下的两个执行器。

最终方案至少要回答：

```text
旧 Peanut 脚手架基线
        +
应用当前实际文件
        +
新 Peanut 脚手架基线
        |
        v
自动替换 / 结构化修改 / 三方合并 / 保留应用版本 / 人工解决
```

---

## 三、必读材料

### 项目管理仓

1. `docs/plans/2026-07-25-lifecycle-architecture-v3.0.md`
2. `docs/plans/2026-07-25-lifecycle-v3.0-review-prompt.md`
3. `docs/plans/archive/2026-07-25-lifecycle-architecture-v2.3.md`
4. `docs/plans/archive/2026-07-25-lifecycle-architecture-v2-review.md`
5. `PLAN.md`
6. `STATUS.md`
7. `HISTORY.md`
8. `repos.yaml`

### 固定代码事实

代码仓：

```text
repositories/peanut-admin/
```

Stage C.2 固定基线：

```text
commit: 69c5b2c271413f6ff741de65437f29e04f975300
tree:   96fa1ea5ff0db2bb3801869d071f4bedda98b6b2
```

至少核对以下内容：

- `tools/project-generator/src/ProjectGenerator.php`
- `tools/project-generator/source-baseline.json`
- `starter/` 完整生成树
- `starter/frontend/src/main.ts`
- `starter/frontend/src/App.vue`
- `starter/frontend/src/app/modules.ts`
- `starter/frontend/package.json`
- `starter/backend/route/app.php`
- `starter/backend/config/modules.php`
- `starter/backend/src/StarterExceptionHandler.php`
- `starter/backend/composer.json`
- `starter/pnpm-workspace.yaml`
- `frontend/src/app/router.ts`
- `frontend/src/app/routes.ts`
- `backend/app/upgrade/ReleaseManifest.php`
- `backend/app/command/UpgradeCli.php`
- `backend/app/command/UpgradeWorkflow.php`
- `scripts/create-project`
- `scripts/upgrade`

读取源码时必须使用上述固定 commit。不要用当前工作区内容替代 Stage C.2 事实。

---

## 四、已经确认的事实与问题

### 1. 核心 package 和脚手架文件不是同一类问题

核心 package 可以通过 Composer/pnpm 更新，只要发布渠道真实可用。应用仓不需要包含这些 package 的源码。

但创建器仍然必须生成应用自身的目录、入口、组合根、配置和可定制页面。这些文件不可能全部进入 vendor/node_modules，也不能假设用户不会修改。

### 2. 当前 Stage C.2 实际生成树不是 v3.0 描述的三文件结构

Stage C.2 当前复制的是 `starter/`，主要路径为：

```text
frontend/src/main.ts
frontend/src/App.vue
frontend/src/app/modules.ts
frontend/src/modules/**
backend/route/app.php
backend/config/modules.php
backend/config/**
backend/src/StarterExceptionHandler.php
backend/src/Modules/**
backend/composer.json
frontend/package.json
package.json
pnpm-workspace.yaml
...
```

当前不存在：

```text
apps/frontend/src/app/extension-registry.ts
apps/backend/bootstrap/app.php
```

所以 v3.0 的三个 recipe-managed 文件只能是未来重构目标，不能当作已经由 Stage C.2 证明的完整事实。

### 3. 用户可能直接修改生成文件

以下行为都必须被视为正常现实，而不是违规输入：

- 直接修改生成的 Layout、App、router 或后端 route；
- 为了接业务快速修改生成配置；
- 替换页面组件但没有使用理想的 extension API；
- 删除不需要的 Demo Module；
- 调整 CI、Docker、workspace 和环境样板；
- 修改 Provider、middleware 或 exception handler。

升级器不能阻止这些修改，只能检测、保护和帮助迁移。

### 4. 前端 layout manifest 的语义仍不完整

当前示例：

```ts
layouts: {
  'myapp.workspace': () => import('./layouts/DcsWorkspaceLayout.vue'),
}
```

它只注册了一个 layout key，没有回答：

- 核心路由如何从 `peanut.layout.workspace` 切换到 `myapp.workspace`；
- 是否允许覆盖保留的 `peanut.*` key；
- 替换的是完整布局行为还是视觉外壳；
- Tenant、权限、菜单、错误边界等安全行为是否仍由 Peanut 控制；
- 默认 Layout 是 package-owned，还是创建时复制到应用中的 scaffold 文件；
- 用户直接修改生成 Layout 时，升级器如何处理。

### 5. PHP package 发布渠道仍有硬错误

GitHub Packages 支持 npm registry，但官方不提供 Composer registry。v3.0 中把 `https://npm.pkg.github.com` 当作 Composer repository 的方案不可实施。

Claude 需要重新选择 PHP 发布渠道，例如 Packagist、Private Packagist、Satis、Composer artifact repository 或基于 Git tag/VCS 的可行方案，并与 Web package 发布方式分别处理。

---

## 五、核心审核问题

### 1. 是否应该恢复“每个版本的脚手架文件清单”

用户提出的核心建议是：Peanut 每个版本都保存一份脚手架文件对比清单。升级时根据旧版清单、应用当前内容和新版清单决定处理方式。

请判断这个方向是否正确，并设计最小可用结构。至少考虑：

- 每个版本是否需要一个 `scaffold-manifest.json`；
- 清单是否包含文件 path、owner、policy、content digest、source location、text/binary 类型；
- 是否需要保存旧版和新版文件真实内容；
- 真实内容应该存在版本化 generator/scaffold package、Git tag、release asset，还是 manifest 内；
- 是否可以避免 v2.x 把文件 base64 塞进 JSON 的复杂方案；
- 如何保证旧版本基线长期可取得；
- 是否需要 rename/delete map；
- 版本跨越时按逐版本变化执行，还是直接 old-base 到 new-base。

请给出推荐 schema 示例。

### 2. 每个生成文件应该有哪些升级策略

请审核并优化以下候选策略，不要机械全部保留：

```text
package-owned
  只存在于 Composer/pnpm package，标准包升级

replace-if-pristine
  当前内容等于旧基线时直接替换为新基线

three-way
  Peanut 与应用都可能合理修改的文本文件，使用 old/current/new 合并

structured-merge
  JSON/YAML/package manifest/router registry 等使用解析器按字段修改

seed-once
  创建时复制，之后完全归应用；升级器只提示模板发生变化

application-owned
  业务和扩展文件，升级器不修改

manual
  无法可靠自动处理，只提供 old/current/new、变更原因和迁移说明

deployment-owned
  secrets、真实环境配置和生产数据库，不进入代码升级
```

请说明：

- ownership 与 update policy 是否应分开建模；
- 哪些策略真正需要进入 v1；
- 哪些文件类型适合三方合并，哪些必须结构化修改；
- 自动合并冲突时如何停止且不前移基线；
- 用户主动删除文件、Peanut rename/delete 文件、二进制文件如何处理；
- 何时只列出人工方案，而不尝试自动修改。

### 3. 三方合并是否应该重新引入

v2.x 曾引入通用三方合并，之后又因为复杂而删除。现在需要重新判断，但不要为了理论完备恢复一个通用 patch 平台。

请回答：

- 对已经复制给应用且双方都可能修改的少量文本文件，`git merge-file` 是否是合理工具；
- 是否应只对白名单 `three-way` 文件启用，而不是所有 scaffold 文件；
- old/current/new 三份内容如何取得；
- 冲突标记是直接写入工作文件，还是写入隔离 worktree/report 目录；
- 合并成功后如何展示 diff 并等待正常 Git review；
- Git 不可用时是否直接 fail closed；
- 是否需要 AST merge，还是结构化 JSON/YAML merge + 普通文本 three-way 已足够。

请给出克制的推荐，不要重新实现 Git。

### 4. Layout、页面和路由的默认值与覆盖模型

请为前端给出一套完整语义，而不只是注册 key。

至少回答：

- `WorkspaceLayout` 的安全行为和视觉外壳如何拆分；
- 默认 Layout 应在 package 中，还是创建时生成 application-owned wrapper；
- 应用如何显式选择 `myapp.workspace` 作为某类 route 的 layout；
- 是否需要 `bindings`、`aliases`、profile config 或 typed appearance override；
- 是否禁止应用覆盖 `peanut.*` key；
- 用户直接编辑生成 Layout 时如何升级；
- 登录页、状态页、Dashboard、router guard 和 shell slot 是否采用相同模型；
- 如何避免应用替换 UI 时顺便绕过认证、Tenant 和权限行为。

请给出 manifest 和生成目录示例。

### 5. 后端有哪些同等级脚手架中间层

不要只讨论前端。请盘点后端可能需要生成并由应用修改的中间层，例如：

- bootstrap / entry；
- route registration；
- application provider list；
- middleware contribution；
- exception mapper；
- command / worker registration；
- module manifest；
- config files；
- database migration entry；
- deployment and runtime config sample。

请判断每项应该：

- 移入 Peanut package；
- 作为薄 recipe-managed composition root；
- 创建时 seed-once；
- 由 application-owned Provider/manifest 接管；
- 使用 structured merge；
- 或只能 manual。

目标是减少冲突，但不能通过假装这些文件不存在来简化设计。

### 6. 完整生成树的所有权盘点

请对 Stage C.2 `starter/` 逐类盘点，并设计 U01 必须产出的 inventory。建议每个文件至少记录：

```text
source_template_path
generated_path
layer: frontend | backend | shared | deployment
owner_after_generation
update_policy
customization_method
versioned_baseline_source
compatibility_check
reason_cannot_live_in_package
```

请判断 inventory 应是设计文档、机器可读 manifest，还是二者都要。

不要预先限制 recipe-managed 为三个文件；应先盘点，再让结果证明最终数量。

### 7. 前端和后端升级应该分成一套还是两套

请独立评估以下推荐方向：

> 对用户提供统一 plan 和 release identity；内部使用 frontend adapter 与 backend adapter 两个执行器；数据库升级作为后端部署阶段单独执行。

候选流程：

```text
peanut upgrade plan --to <release>

peanut upgrade code-apply <plan>
peanut upgrade code-apply <plan> --scope frontend
peanut upgrade code-apply <plan> --scope backend

peanut upgrade db-apply <plan>
```

请判断：

- 一个 plan 是否应该同时包含 frontend/backend/shared 三个 section；
- Composer 与 pnpm 是否必须由不同 adapter 处理；
- 前后端 package 是否允许独立版本，还是由 release group 固定兼容集合；
- shared scaffold 文件由哪个 adapter 拥有；
- 只升级一侧后能否部署，如何表达 partial state；
- database migration 如何只绑定 backend release；
- 最终部署制品如何证明前端、后端和数据库目标一致；
- 是否有比“一套协议、两套执行器”更简单可靠的设计。

### 8. Package 升级与 scaffold 升级如何协同

请给出严格顺序：

- 先解析目标 package 版本，还是先分析 scaffold；
- scaffold 新基线是否来自目标版本 generator/scaffold package；
- package API breaking change 与 scaffold 文件变更如何在同一个 plan 中关联；
- package 更新失败、scaffold 合并冲突、前端成功后端失败分别如何恢复；
- managed/scaffold lock 何时更新；
- 是否只有全部 code-apply 成功后才形成 application release manifest。

### 9. 版本对比列表应该记录什么

用户希望每个版本都有可比较的文件变化列表。请判断是否需要同时提供：

1. 机器可读 scaffold manifest；
2. 从上一版本到当前版本的 change manifest；
3. 面向开发者的迁移说明。

请考虑以下动作：

```text
add
replace
rename
delete
structured-update
manual
deprecated-extension-point
required-package-change
```

说明直接从 0.1.0 升 0.4.0 时，是合成逐版本 change，还是只用 0.1.0 base 与 0.4.0 target；哪些动作必须顺序执行。

### 10. 旧应用 Adoption 如何建立可信基线

Stage C.2 旧应用已经包含大量源码快照和生成文件，不只是三个 recipe 文件。

请重新设计 Adoption：

- 如何根据 `peanut-project.json.input_commit/input_tree/profile/features` 找到旧 scaffold baseline；
- 如何区分复制的 Peanut package snapshot 与真正应用代码；
- 如何将旧 package snapshot 迁移为 Composer/pnpm package 依赖；
- 用户修改过旧 package snapshot 时如何检测和处理；
- 旧生成文件如何逐文件 classified/adopt；
- 无法证明来源时如何进入 unverified/manual 模式；
- Adoption 是否应该成为独立 U02 能力，还是必须在 U01 先固定合同。

### 11. Breaking change 和人工解决方案

不要使用“plan 后有任意 extension commit”或空 commit作为完成证据。

请设计：

- package contract version / framework range；
- extension/provider capability metadata；
- machine verifier；
- 无法机器验证时绑定 plan digest、change id、处理说明和责任人的 resolution record；
- 手工解决 scaffold 冲突后如何证明 old/current/new 已正确收口；
- 哪些 finding 阻断 code-apply，哪些只进入报告。

### 12. 发布渠道和离线边界

请修正 v3.0 的渠道假设：

- Web package 可以使用 GitHub Packages npm registry；
- PHP package 不能使用不存在的 GitHub Packages Composer registry；
- 请在 Packagist、Private Packagist、Satis、Composer artifact repository、VCS tag 等方案中做出当前阶段推荐；
- token、`auth.json`、`.npmrc` 和 CI secrets 如何注入且不进 Git；
- 内网或完全离线是否属于 v1 支持范围；
- 如果支持，使用镜像 registry、离线 package cache 还是 release bundle；
- 不要再次建立一个没有真实消费者的通用分发平台。

---

## 六、候选架构，仅供批判

以下不是要求照抄，而是根据当前讨论形成的候选，请验证、修改或否定。

### 1. 内容模型

```text
package-owned
  Peanut 核心能力，Composer/pnpm 更新

scaffold-managed
  少量仍需随 Peanut 演进的应用底座文件
  每个文件声明 replace-if-pristine / three-way / structured-merge / manual

seed-once application scaffold
  创建时复制，之后完全归应用；升级器只提供新版参考和迁移说明

application extension/business
  应用拥有；只做兼容性验证，不自动修改

deployment-owned
  secrets、真实环境和数据库
```

### 2. 版本化 scaffold source

不把文件 base64 塞入 JSON。每个 Peanut release 发布一个普通目录或 package：

```text
scaffold/
  manifest.json
  frontend/...
  backend/...
  migrations/
```

manifest 保存路径、digest、policy、rename/delete/change metadata；真实 old/new 内容从对应版本的 scaffold package 或 Git release 取得。

### 3. 升级决策

```text
current == old base
  -> 自动更新到 new base

current != old base && policy == three-way
  -> 在隔离 worktree 合并 old/current/new

current != old base && policy == replace-if-pristine
  -> 阻断，保留 current，输出 new

policy == structured-merge
  -> 解析后只修改受框架管理字段

policy == seed-once
  -> 不修改，只报告新版模板变化

无法证明 old base / binary / 高风险语义冲突
  -> manual
```

### 4. 执行模型

```text
统一 UpgradePlan
  frontend section -> FrontendUpgradeAdapter
  backend section  -> BackendUpgradeAdapter
  shared section   -> ScaffoldUpgradeEngine

成功后生成 ApplicationReleaseManifest
  -> 部署
  -> db-apply 核验 backend/database identity
```

请重点判断这套模型是否在保持可实施性的同时，避免了 v2.x 的过度复杂。

---

## 七、需要 Claude 输出的最终方案

请形成一套可以直接写成下一版主设计的方案，至少包含：

1. 内容所有权与 update policy 模型。
2. Stage C.2 完整生成树盘点方法。
3. versioned scaffold manifest / change manifest schema。
4. old/current/new 内容的取得与摘要规则。
5. 自动替换、结构化修改、三方合并和 manual 的选择矩阵。
6. Layout/页面/router 的默认与覆盖语义。
7. 后端 bootstrap/route/provider/config 的对应模型。
8. 统一 plan + frontend/backend adapters 的命令和状态流。
9. package 发布、认证和可选离线方案。
10. Adoption 从 package snapshot 迁移到版本化 package 的流程。
11. breaking change verifier 和人工 resolution record。
12. U01-U04 重新划分后的输入、交付、禁止范围和完成条件。

方案必须明确区分：

- 创建时复制；
- 创建后应用拥有；
- Peanut 后续自动管理；
- Peanut 只提供参考；
- Peanut 永远不触碰。

---

## 八、输出格式

### Part 1：总体结论

明确回答：

- v3.0 的简化是否过度；
- 是否需要恢复版本化 scaffold baseline 和有限合并能力；
- 推荐形成 v3.1、v4.0，还是继续 v3.0；
- 是否已经可以起草 U01 合同。

### Part 2：对用户建议的判断

直接评价“每个版本保存文件对比列表，能自动升级的升级，不能安全替换的合并或交给人工”这一建议：

- 哪些部分正确；
- 哪些部分会导致过度设计；
- 推荐的最小实现是什么。

### Part 3：逐项审核

按第五节 12 个问题逐项输出：

```text
立场：认可 / 需修改 / 有根本问题
理由：
推荐方案：
必须进入下一版设计的内容：
```

### Part 4：推荐架构

给出：

- 内容分层图；
- package/scaffold/application/deployment 关系；
- frontend/backend/shared 三执行域；
- plan/code-apply/deployment/db-apply 状态流；
- 关键 schema 示例。

### Part 5：下一版主设计大纲

给出可直接用于撰写下一版设计的章节结构，并在每章列出必须固定的决定。

### Part 6：阻止实施的最小问题清单

只列真正阻止 U01 或后续升级闭环的问题。不要把可在任务合同中细化的普通实现细节升级成架构阻塞。

---

## 九、审核原则

- 以 Stage C.2 固定源码和真实 Composer/pnpm 能力为准。
- 不假设用户永远遵守理想扩展方式。
- 不静默覆盖应用修改。
- 自动处理必须有 old/current/new 基线、精确写集和恢复边界。
- 三方合并只用于白名单文本文件，不建设通用 patch 平台。
- JSON/YAML/package manifest 优先结构化解析，不做文本替换。
- 核心安全行为优先留在 package，应用只替换受控 UI/adapter。
- 前后端允许复杂度不同，但不能失去统一 release compatibility。
- 方向正确但可在合同细化的问题，不应扩大成无意义的架构机制。
- 输出必须能直接交给原作者形成更可行的下一版设计，而不是留下新的口号。

本任务仅做设计与源码审核，不修改仓库、不运行实现型测试、不发布 package 或 release。
