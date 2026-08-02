# ChatGPT 审核任务：Peanut Admin 生命周期架构 v3.1

## 背景与本轮核心变化

v3.0 做了正确的方向修正（包版本化），但犯了两个错误：

1. 声称 recipe-managed 只有三个文件——与 Stage C.2 实际生成树（约 50 个文件）不符
2. 把 GitHub Packages 当作 Composer registry 使用——GitHub Packages 不支持 Composer

v3.1 基于真实 `starter/` 文件树，重新分类了所有生成文件，并修正了发布渠道：

- **A. 转移到包**：Peanut 模块源码快照（约 40 个文件）移入 Composer/pnpm 包，应用仓不再有这些内容
- **B. scaffold-managed（约 10 个文件）**：骨架组合根文件，分 replace-if-pristine 和 structured-merge 两种策略
- **C. seed-once**：创建时写入，之后完全归应用，升级器只提示变化
- **D. demo-module**：可删除示例代码
- **E. application-owned**：业务代码，永不触碰

PHP 包发布改为 **Composer VCS repository + GitHub private repo + Git tags**，不依赖不存在的 GitHub Packages Composer registry。

---

## 请审核的文档

`docs/plans/2026-07-25-lifecycle-architecture-v3.1.md`

---

## 审核重点

### 1. 文件分类是否准确

v3.1 把 `backend/src/Modules/Peanut/*/`（约 40 个文件）归为"转移到包"。

- 这些文件目前作为包源码快照存在于 starter，移入包后，Composer 安装时它们在 vendor/ 里。这对 Module manifest（`module.json`）、migration 文件和 Resources（menus、permissions）是否都适用？这些文件通常需要在应用启动时可被路径引用——包安装后路径会变化，`ModuleRegistryFactory` 等是否需要适配？
- `backend/src/StarterExceptionHandler.php` 归入哪类？它是应用级的自定义异常处理，应该是 seed-once 还是 scaffold-managed？
- `frontend/src/clients.ts` 和 `frontend/src/App.vue` 中包含应用特定内容（brand、client keys），归为 replace-if-pristine 是否合理？升级时是否会覆盖用户设置的 brand？

### 2. structured-merge 的可实施性

v3.1 对 `backend/config/modules.php`（PHP 数组）和 `frontend/src/app/modules.ts`（TypeScript import）使用 structured-merge。

- PHP 数组结构化合并需要一个 PHP 解析器或正则方案。对于 `modules.php` 的 `roots`、`frontend_components`、`registered_client_keys` 三个数组，是否真的需要解析，还是可以用更简单的方案（比如约定应用自定义项通过配置注入，而不是直接写在这个文件里）？
- `modules.ts` 的 TypeScript import 结构合并比 JSON 复杂得多。能否通过约定简化（比如应用模块通过 glob 自动发现，不直接写在 `modules.ts` 里），从而让 `modules.ts` 退化为 replace-if-pristine？
- 如果这两个文件改成 replace-if-pristine，应用扩展如何添加自己的模块？

### 3. Composer VCS repository 的实际行为

v3.1 推荐 Composer VCS + GitHub private repo + Git tags。

- Composer 从 VCS repo 解析 tag 时，会克隆整个 Git 仓库到本地缓存（`~/.composer/cache/vcs/`）。对于多个 Peanut 包，首次 `composer install` 可能非常慢（需要克隆 10 个私有仓库）。这是否可接受，或者是否有缓解方案？
- `composer.lock` 会记录每个包的 `source.reference`（commit hash）和 `dist.url`（GitHub archive URL）。升级时 `composer update peanut-admin/*` 是否能可靠地只更新 Peanut 包而不影响其他依赖的 dist URL？
- 如果将来迁移到 Private Packagist 或 Satis，`composer.json` 的 `repositories` 配置需要改变——这会影响现有应用。v3.1 是否应该建议一个可平滑迁移的抽象层？

### 4. scaffold-manifest.json 的旧版本内容取得

v3.1 说"真实文件内容不塞进 JSON，从对应版本的 scaffold package 或 Git tag 获取"。

- 升级从 0.1.0 到 0.2.0 时，`replace-if-pristine` 需要对比当前内容与 0.1.0 版本的文件。0.1.0 的文件内容从哪里取？
  - 选项 A：从 `scaffold.lock` 的 `base_digest` 与 0.1.0 的 scaffold-manifest.json 的 `content_digest` 对比，相同则 pristine，不同则已修改。不需要实际文件内容。
  - 选项 B：需要 0.1.0 的实际文件内容来生成差异报告
  - v3.1 似乎默认 A，但没有明确说明。请确认是否只需要 digest 对比，不需要旧版文件内容。

### 5. Breaking change resolution record 的完整性

v3.1 的 resolution record 要求 `evidence_commit` 指向修改了 `extensions/` 目录的 commit。

- 如果 breaking change 的修改不在 `extensions/` 目录（比如需要修改 `scaffold.lock` 中某个文件的 `app_override` 为 true），这个验证规则是否覆盖所有情况？
- 如果 breaking change 实际上"无需修改"（比如只是 API 更名但应用没有使用该 API），是否允许 `evidence_commit: null` 并附带说明？
- resolution record 文件本身进入 Git 是合理的，但 `.peanut/resolutions/` 目录需要在文档中明确声明其生命周期（是永久保留还是 code-apply 后可清理）。

### 6. Adoption 的包源码快照迁移

v3.1 的 Adoption 命令会检测旧 package snapshot，提示用户迁移到扩展点。

- 如果用户确实修改了 `backend/src/Modules/Peanut/Settings/ModuleProvider.php`（在旧模式下是可能的），迁移建议是什么？这个修改如何转移到 `extensions/backend/Providers/` 下？
- `--migrate-package-snapshots` 这个 flag 只是提示还是真的执行迁移？如果执行，写集边界是什么？如果只是提示，文档应该说清楚。

### 7. 整体评估

- v3.1 相比 v3.0，是否已经解决了核心问题（文件分类、发布渠道）？
- 是否还有阻止 U01 实施的根本问题？
- 文档是否自洽，没有互相矛盾的地方？

---

## 输出格式

### Part 1：总体结论

明确：可直接进入 U01 合同起草 / 修订后可进入 / 仍有根本问题

### Part 2：逐项审核（7 项）

每项：立场 + 理由 + 建议

### Part 3：必须修订清单

只列阻止实施的项。

---

## 审核原则

- 以"能否真实实施"为标准，不以"理论是否完备"为标准
- 方向正确但细节可在合同中补充的，不列为阻塞
- 不要重新引入 v2.x 已删除的复杂机制

本任务仅审核，不修改文件，不运行测试。
