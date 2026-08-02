# Claude 深度审核任务：Peanut Admin 生命周期架构 v2.3

## 任务目标

请基于 Peanut Admin 的真实源码和现有设计文档，深入审核
`docs/plans/2026-07-25-lifecycle-architecture-v2.3.md`，判断它能否真实实施出安全、可恢复、可离线运行的长期升级机制。

本轮不是文字润色，也不是要求认可现有结论。请独立验证设计，指出仍然存在的根本问题，并给出可以直接整合进 v2.4 的具体方案。

最终目标是让以下生命周期真实闭环：

```text
Peanut 发布不可变版本
  -> 下游应用生成升级计划
  -> 在隔离工作区升级代码和依赖
  -> 形成应用部署制品
  -> 部署环境核验同一版本身份并升级数据库
```

整个过程必须保证：

- 不静默覆盖应用业务代码或应用扩展。
- 不把开发机代码升级和生产数据库升级伪装成一个事务。
- 离线环境能够从固定制品完成包解析、安装和核验。
- plan、代码、包、recipe、部署制品和数据库 migration 绑定到明确且不可混淆的身份。
- 输入漂移、摘要不一致、兼容性未知和部分失败时全部 fail closed。

---

## 必读材料

### 当前主设计

1. `docs/plans/2026-07-25-lifecycle-architecture-v2.3.md`

### 历史设计与评审

2. `docs/plans/archive/2026-07-25-lifecycle-architecture-v2.2.md`
3. `docs/plans/archive/2026-07-25-lifecycle-architecture-v2-review.md`
4. `docs/plans/archive/2026-07-25-lifecycle-architecture-v2.1.md`

### 项目事实

5. `PLAN.md`
6. `STATUS.md`
7. `HISTORY.md`
8. `repos.yaml`

### Stage C.2 固定源码

代码仓：`repositories/peanut-admin/`

固定基线：

```text
commit: 69c5b2c271413f6ff741de65437f29e04f975300
tree:   96fa1ea5ff0db2bb3801869d071f4bedda98b6b2
```

至少核对以下实现，不要只依据设计文档推断：

- `frontend/src/app/router.ts`
- `frontend/src/app/routes.ts`
- `tools/project-generator/src/ProjectGenerator.php`
- `backend/app/command/UpgradeCli.php`
- `backend/app/command/UpgradeWorkflow.php`
- `backend/app/upgrade/ReleaseManifest.php`
- `scripts/create-project`
- `scripts/upgrade`
- 根目录及 workspace 中的 `composer.json`、`package.json`、Composer/pnpm lockfile

读取历史版本源码时，请以以上固定 commit 为准，不要用当前工作区内容替代固定事实。

---

## v2.3 已完成的有效改进

以下方向已经比 v2.2 更清晰，但仍需核对其实现闭环：

1. `app_override` 表达开发者意图，`base_digest` 防止漏声明，内容漂移默认阻断。
2. Adoption 改为确定性分类，差异文件必须逐路径显式接受 override。
3. 页面分为固定核心页、按 feature 启用的 Peanut 页面和可删除 Demo 页面。
4. recipe-managed 完整清单由 recipe 源目录和确定性 artifact 生成工具提供。
5. package manifest/lockfile 不再作为普通文本受管文件处理。
6. 代码升级和数据库部署使用不同命令，只共享发布身份。
7. 当前 package 分发方向选择 release bundle，而不是依赖尚不存在的公开 registry。

请保留真正成立的简化，不要为了形式重新引入通用文本三方合并、第二套 IoC 容器或代码阶段数据库式 ledger。

---

## 必须重点审核的问题

### 1. Peanut release bundle 与应用部署制品是否被混淆

v2.3 一方面把 release bundle 定义为 Peanut 官方不可变发布物，另一方面又要求下游 CI 把每个应用自己的 plan 放入 bundle 的 `plans/` 目录。

请判断：

- 应用专属 plan 是否可能写回同一个 Peanut 官方 bundle？
- 写入 plan 后，原 `bundle_digest` 是否必然变化？
- `db-apply` 应该消费 Peanut bundle，还是 code-apply 后形成的应用部署制品？
- 是否应明确拆分以下身份：
  - `peanut_release_identity`
  - `application_source_identity`
  - `deployment_artifact_identity`
- 各身份应该包含哪些字段，分别由谁生成、在哪一步核验？

请给出修订后的制品流转图和最小 schema。

### 2. Release bundle 的真实性与摘要根

v2.3 目前依赖“官方 release 页面 + SHA256SUMS”。从同一位置同时下载 bundle 和 checksum，只能发现传输损坏，不能证明发布者身份，也不能防止两者被一起替换。

请给出当前阶段可实施的最小可信根方案，例如：

- 固定 Git tag/commit 与 root manifest digest；
- GitHub 平台提供的 immutable asset digest；
- GPG、Sigstore 或项目固定公钥签名；
- 哪个摘要作为 release identity 的稳定根，避免 zip 自身或 manifest 自引用。

不要求过度建设 PKI，但必须能解释升级器为什么可以信任将要执行的代码和 migration。

### 3. Option A 是否真的能离线安装 Composer 和 pnpm 包

v2.3 当前只说 bundle 内含 PHP/Web tarball，然后使用 Composer `--prefer-dist --no-scripts` 和 pnpm frozen lock。

请核对并解决：

- Composer 如何发现本地 tarball：artifact repository、临时 repository config，还是其他方式？
- `--no-scripts` 是否足以禁止 Composer plugin 副作用，是否还需 `--no-plugins` 或 allowlist？
- pnpm 如何从本地 tarball 或本地 registry metadata 解析 semver，而不访问公共 registry？
- 修改 `package.json` 后如何先生成新 lock，再执行 frozen/offline 复核？
- 最终 lockfile 是否会引用不可移植的本机绝对路径或临时 `file:` 路径？
- Composer/pnpm 重算 lock 时，Peanut 包的第三方传递依赖如何处理？
- 如何证明没有无关的应用直接依赖被升级？
- “安装后 digest”具体核验 archive、lock integrity、已安装包内容还是三者中的哪一个？

请给出一套可执行的离线解析和安装顺序，不能只写原则。

### 4. Plan 摘要是否可计算，是否绑定了完整输入

v2.3 的 plan JSON 内部包含 `plan_digest`，但没有定义摘要计算时该字段是否排除、置空或使用 sidecar。

同时，plan 只记录部分文件 digest，没有完整绑定生成计划时的应用状态。

请明确：

- 使用 canonical JSON payload + envelope，还是 `.sha256` sidecar；
- digest 的精确覆盖范围；
- plan 是否必须绑定以下输入：
  - 应用 commit/tree；
  - `.peanut/project.json` 和 `managed-files.lock`；
  - `composer.json`、`package.json` 和所有 lockfile；
  - extension manifest/provider inventory；
  - 当前 Peanut package/release identity；
- code-apply 如何检测 stale plan；
- db-apply 如何证明正在部署的代码和生成 plan/code-apply 的代码是同一份。

请给出推荐的 plan envelope 示例。

### 5. package-manifest 的所有权和允许变化范围

把 package manifest 单列是合理方向，但“只修改 Peanut 依赖条目”不足以描述 lockfile 变化。依赖解析器可能更新共享的第三方传递依赖。

请定义：

- direct dependency 允许写集；
- transitive dependency closure 的计算方式；
- lockfile 中允许变化和必须阻断的内容；
- package manager plugin、script、网络访问和 repository 配置边界；
- package 更新失败后如何利用隔离 worktree 恢复；
- plan 如何提前展示 resolver 实际将产生的变化，而不是 code-apply 时才第一次求解。

### 6. deterministic source migration 是否仍然有必要

v2.3 规定 deterministic source migration 只能修改 recipe-managed 文件，但 code-apply 随后又用新版 recipe 替换这些文件。这可能覆盖 migration 结果。

请判断：

- 如果目标 recipe 已经提供新文件，独立 source migration 是否重复？
- rename/delete 是否应直接进入 recipe artifact 动作？
- deterministic migration 是否只应保留给结构化 application metadata？
- 如果保留，它的精确写集、执行顺序、前置摘要、幂等性和结果核验是什么？
- 如果没有不可替代的使用场景，是否应从 v1 升级机制删除此能力？

请给出明确结论，不要同时保留互相覆盖的两套机制。

### 7. Manual action 如何被可靠验证

v2.3 删除 `ack.json`，认为开发者修复后重新运行 plan，兼容性检查会自动确认问题消失。但当前 extension manifest 和 backend provider 契约并未定义足以执行该判断的版本与能力元数据。

请审核：

- frontend extension 是否需要 `contract_version`、`framework_range`、capabilities、dependencies；
- backend provider 是否需要对应的 contract/api version；
- ReleaseManifest 中每个 breaking/manual action 是否必须携带 `check_id` 或机器可执行 verifier；
- plan 如何在尚未安装目标 package 的情况下运行目标版本兼容检查；
- 对无法机器验证的动作，是禁止发布，还是保留绑定 plan digest 的显式 resolution record；
- resolution record 如何避免退化成无意义的“我已阅读”按钮。

目标是让 blocker 的消失有证据，而不是依赖开发者口头声明。

### 8. Adoption 如何证明旧项目的 recipe 版本

`--recipe-version 0.1.0` 只是用户输入，不等于来源证明。

请定义：

- 如何利用旧项目已有的 `peanut-project.json`、source commit/tree、profile 和 features 映射到 recipe artifact；
- 当前内容完全一致、内容不同、文件缺失和来源无法证明时分别如何处理；
- 来源无法证明但项目所有者仍决定接管时，需要什么显式记录；
- old artifact 离线缺失时实际能输出哪些文件，不能声称输出无法取得的 old baseline；
- Adoption 是否需要记录逐文件 current digest，便于审计首次接管。

### 9. Recipe-managed 与 override 的长期演进

请核对以下后续版本行为：

- `app_override: true` 时，升级后 `base_digest` 应更新到哪个版本；
- `.peanut-new` 如何命名、覆盖、清理和进入 Git；
- Peanut rename/delete 一个 override 文件时如何处理；
- 用户主动删除 recipe-managed 文件时是缺失、接管还是拒绝；
- recipe artifact 保留“三个 major”是否足够，旧版本缺失时升级路径如何 fail closed；
- sequential upgrade 是否按 release graph 计算，而不是只有单个 `requires_sequential_from` 字符串。

### 10. 页面边界与项目文档一致性

请重新核对 Stage C.2 的 `frontend/src/app/router.ts`、`routes.ts` 和 `APP_MODULES`：

- 固定核心页是否完整；
-七个 Peanut feature 页面是否精确；
- 前端四条 example module 路由与 backend `example.greeting` 是否被错误混为同一类；
- U02 默认 profile 到底生成哪些 feature 和 Demo Module。

同时检查项目管理文档中的现实冲突：

- `PLAN.md` 是否仍指向已归档的 v2.1；
- `PLAN.md` 是否仍要求 v2.3 已删除的 `git merge-file`；
- `PLAN.md` 是否仍把已经固定的 package 分发方式写成 U01 待决策；
- v2.3 的“前版本”链接是否已指向 archive；
- `STATUS.md` 是否过早声明“可直接进入 U01”。

---

## 需要你给出的优化方案

请在指出问题后，给出一套内部一致的推荐架构。至少包括：

1. Peanut 官方 release bundle 的目录结构、可信根与身份字段。
2. 应用 upgrade plan 的 canonical schema 和输入身份。
3. code-apply 后应用部署制品的目录结构和身份字段。
4. `plan -> code-apply -> build/package -> db-apply` 的严格状态流。
5. Composer 与 pnpm 在 Option A 下的离线解析和安装协议。
6. managed file、package manifest、application extension 和 deployment state 的写集边界。
7. extension compatibility 与 manual action verifier 的最小契约。
8. Adoption 的确定性来源证明和接管记录。
9. 失败、中断、重复执行和版本跳跃时的 fail-closed 行为。
10. U01-U04 各自必须固定的合同和可观察完成条件。

设计应保持克制：只有在解决真实问题时才引入新 schema、ledger 或制品。不要重新建设通用框架、通用 patch engine、第二套容器或生产代码合并器。

---

## 输出格式

### Part 1：总体结论

明确选择一项：

- 可直接进入 U01 合同起草；
- 修订少量合同后可进入；
- 仍有根本问题，必须先形成 v2.4。

说明最关键的判断依据。

### Part 2：逐项审核

按上述 10 个问题逐项输出：

```text
立场：认可 / 需修改 / 有根本问题
理由：
必须修改：
推荐方案：
```

不要只复述问题。每项必须给出可以实施的结论。

### Part 3：推荐的最终架构

给出：

- 身份与制品关系图；
- 核心 schema 示例；
- 离线包升级执行顺序；
- plan/code/deployment/database 状态流；
- 每个阶段的失败和恢复边界。

### Part 4：v2.4 必须修改清单

只列阻止真实实施的项目，不列锦上添花的建议。每项标注应修改的 v2.3 章节和推荐的新表述。

### Part 5：U01-U04 任务边界

判断现有顺序是否仍正确，并为每个任务给出：

- 输入事实；
- 必须交付；
- 禁止范围；
- 可观察完成条件；
- 后续任务依赖。

---

## 审核原则

- 以固定源码和实际包管理器行为为准，不以文档自我声明为证据。
- 不因为方案复杂就接受模糊占位；也不为了完备而设计没有现实消费者的通用机制。
- 区分“方向正确”“schema 已定义”“命令可执行”“已经实现并验证”四种状态。
- 任何摘要都必须说明覆盖范围和 canonicalization 规则。
- 任何自动修改都必须有明确写集、输入身份和失败恢复边界。
- 任何离线承诺都必须解释解析器怎样找到包及其元数据。
- 任何 manual action 都必须说明其完成证据。
- 输出应能直接交给原作者整合为 v2.4，而不是留下新的开放口号。

本任务仅做设计与源码审核，不修改仓库、不运行实现型测试、不发布版本。
