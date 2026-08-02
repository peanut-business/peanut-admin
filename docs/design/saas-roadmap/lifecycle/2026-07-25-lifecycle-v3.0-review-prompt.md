# ChatGPT 审核任务：Peanut Admin 生命周期架构 v3.0

## 背景

这是第三轮重写。此前的 v2.x 系列存在一个根本性错误假设：**假设 Peanut 包源码会被复制进生成的应用仓**。这导致了 recipe artifact（含 base64 文件内容）、bundle tarball 离线安装协议、旧版本内容取回等大量不必要的复杂设计。

v3.0 的核心前提修正为：**Peanut 包以版本化制品发布（GitHub Packages），下游应用通过标准 `composer require` / `pnpm add` 安装**。应用仓里没有 Peanut 包源码，升级时用标准包管理器更新，不需要自定义合并逻辑。

这一个改变让整个设计文档从约 500 行缩减为约 250 行，删除了大量复杂机制。

---

## 你的任务

审核 `docs/plans/2026-07-25-lifecycle-architecture-v3.0.md`，判断它能否真实实施出安全、可维护的长期升级机制。

**本轮特别关注：简化是否引入了新的遗漏，或者遗漏了真正必要的能力。**

---

## 审核维度

### 1. 包版本化前提是否成立

v3.0 依赖 Peanut PHP/Web 包发布到 GitHub Packages。

- 私有 GitHub Packages Composer registry 需要 `composer config http-basic`，下游开发者如何配置认证？是否有非交互式的标准化方式？
- pnpm 访问私有 npm registry 需要 `.npmrc` 中的认证 token，如何在不泄露 secrets 的情况下分发给下游？
- CI 环境如何处理认证（通常用 secrets）？开发者本地如何处理？
- 如果 GitHub Packages 不可达（内网离线环境），这个方案是否还有 fallback？或者应该接受"需要访问 GitHub Packages"作为前提？

### 2. `managed-files.lock` 的精简是否足够安全

v3.0 把 `recipe_artifact_digest`、`recipe_id` 等字段全部删除，只保留 `peanut_version` 和每个文件的 `base_digest`。

- 如果 Peanut 0.1.0 的某个 recipe 文件和 0.2.0 的内容恰好相同（base_digest 相同），升级器能否正确识别"此文件无需更新"？
- 新版 recipe 文件从哪里取得内容用于替换？是从 GitHub Packages 安装的包里读取，还是需要另外的机制？
- `peanut_version` 是否足以定位"这个版本对应的 recipe 文件内容"，还是需要精确的 commit/tree？

### 3. Breaking change 处理是否可靠

v3.0 的 breaking change 检测方式是：检查 plan 生成后是否有修改 `extensions/` 目录的 commit。

- 这个检测能否被绕过（比如 commit 存在但改的是无关文件）？
- 如果开发者在另一个 worktree 工作，`git log` 可能看不到那些变更，如何处理？
- "空 commit 跳过"这个机制是否过于宽松，容易被误用？
- 是否有更可靠但同样简单的方案？

### 4. `app_override: true` 时的升级行为

v3.0 说：`app_override: true` 时，保留当前文件，将新版本写入 `<path>.peanut-new`。

- 新版本内容从哪里来？从包里提取吗？具体是哪个包里的哪个路径？
- 如果连续两次升级都没有处理 `.peanut-new`，第三次升级时会覆盖还是追加？
- `base_digest` 此时应更新到新版本的 digest 还是保持不变？

### 5. Adoption 来源证明的可靠性

v3.0 用 `peanut-project.json` 里的 `input_commit` 映射到 release version。

- GitHub release 元数据中是否一定有 `input_commit` 到 version 的映射？还是需要 Peanut 维护一个额外的 commit→version 索引文件？
- 如果 `input_commit` 对应的是一个开发中的 WIP commit（如 Stage C.2 的 `838f881...`），而不是正式发布的 release commit，如何处理？
- Adoption 下载对应版本的三个 recipe 文件，具体从哪里下载？包里？release assets？

### 6. `stale plan` 检测的精确性

v3.0 用 `application_managed_files_digest` 检测 stale plan：code-apply 启动时比较当前 `managed-files.lock` 的摘要是否与 plan 记录一致。

- `managed-files.lock` 本身是否稳定（比如字段顺序是否确定）？还是需要 canonical JSON 序列化？
- 如果开发者在运行 plan 之后、code-apply 之前只改了业务代码（没改 lock），stale plan 检测会通过——这是否合理？
- 是否还需要记录 `composer.json` 或 `package.json` 的 digest，以检测依赖版本约束的改变？

### 7. 简化后的 `plan` 文件是否包含足够信息供 `db-apply` 核验

`db-apply` 需要确认：正在部署的数据库版本与 `code-apply` 的代码版本一致。

- v3.0 的 plan 里有 `application_managed_files_digest`，但这是 `managed-files.lock` 的摘要，不是应用代码的摘要。
- 如果生产环境没有 Git，`db-apply` 如何证明代码已经升级到正确版本？仅靠 `managed-files.lock` 的 `peanut_version` 字段是否足够？

### 8. 三个 recipe 文件的正确性

v3.0 声称 recipe-managed 文件的**完整清单**是这三个文件：

1. `apps/frontend/src/main.ts`
2. `apps/frontend/src/app/extension-registry.ts`（v3.0 新命名，原 `page-registry.ts`）
3. `apps/backend/bootstrap/app.php`

- Stage C.2 生成的应用目前有 `apps/` 目录结构吗？还是这是 v3.0 重构后才会有的结构？
- 这三个文件是否真的足够？有没有遗漏（比如 `apps/backend/route/app.php`、`pnpm-workspace.yaml`、根 `package.json`）？
- `apps/backend/bootstrap/app.php` 目前在 Stage C.2 中存在吗？

### 9. U01 的可实施性

v3.0 把"将 Peanut 包发布到 GitHub Packages"作为 U01 的第一步，完成条件是 `composer require peanut-admin/kernel:^0.2.0` 可以安装。

- 这需要先发布新版本（0.2.0），但当前最新版是 0.1.0（Stage C.2）。U01 是否应该先以 0.1.0 为基础发布到 GitHub Packages，还是等到内容完善后再发布？
- 修改 `ProjectGenerator.php` 删除 `copyPackageSnapshots()` 是一个破坏性变更——基于 Stage C.2 生成的旧应用仓将无法再使用新版 `create-project` 补充包。这与 Adoption 路径的兼容性如何？

### 10. 整体评估

- v3.0 相比 v2.3，是否删除了某些真正必要的能力（而不只是简化了实现）？
- 文档中是否还有不一致或自相矛盾的地方？
- 是否可以直接进入 U01 合同起草，还是需要先回答某些悬而未决的问题？

---

## 输出格式

### Part 1：总体结论

选择一项：可直接进入 U01 / 修订后可进入 / 仍有根本问题需先解决

### Part 2：逐项审核

每项：立场 + 理由 + 建议

### Part 3：v3.1 必须修改清单

只列阻止实施的项，每项标注应修改的 v3.0 章节和推荐表述。

---

## 审核原则

- 以简洁为优先：不要为了完整性重新引入 v2.x 删除的复杂机制。
- 区分"设计方向正确"和"实施细节待定"：方向正确但细节可以在合同中补充的，视为可进入 U01。
- 只有真正阻止实施的问题才列入 Part 3。

本任务仅做设计审核，不修改文件，不运行测试。
