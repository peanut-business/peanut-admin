# Claude 修订任务：将 Peanut Admin 生命周期架构 v3.1 收敛为可实施的 v3.2

## 任务目标

请阅读当前设计、真实源码和本提示词，审核并重写 Peanut Admin 应用生命周期架构，形成一份可以进入 U01 实施合同起草的 v3.2 主设计文档。

本轮不是只输出聊天建议。你必须把结论写入项目文件，并额外生成下一轮交给 Codex 审核的提示词文件。

需要阅读：

1. `docs/plans/2026-07-25-lifecycle-architecture-v3.1.md`
2. `docs/plans/2026-07-25-lifecycle-v3.1-review-prompt.md`
3. 本提示词
4. 固定源码基线 `69c5b2c271413f6ff741de65437f29e04f975300`，tree `96fa1ea5ff0db2bb3801869d071f4bedda98b6b2`

源码仓由项目根目录 `repos.yaml` 定位。审核前必须核对固定基线中的真实目录、Composer manifest、npm package manifest、生成器、starter 和 Module 加载逻辑。不要以当前工作区 HEAD 或聊天概述代替固定基线事实。

---

## 一、先纠正术语和基本模型

用户期望的基本模型一直是：

- 前端通过 npm-compatible registry 安装 `@peanut-admin/*` 包，实际命令可由 pnpm 执行；
- 后端通过 Composer 安装 `peanut-admin/*` 核心库或功能包；
- 应用仓只保留应用自己的代码、扩展代码，以及少量确实必须复制并持续升级的脚手架文件；
- 数据库迁移由同一升级计划约束，但在独立部署阶段执行。

`Composer VCS repository` 不是一种额外的包，也不是 npm/Composer 之外的第三套升级机制。它只是 Composer 获取某个 PHP 包元数据和源码的 repository 类型：Composer 读取 Git 仓库根目录的 `composer.json` 和 tag，把它当作一个 Composer package。

v3.1 中以下表述容易造成误解，必须修正：

- “Composer VCS registry”不是准确说法。VCS repository 是源码仓来源，不是 Composer registry。
- 消费端仍然执行 `composer require`、`composer install`、`composer update`；VCS 只影响包从哪里解析和下载。
- 当前 Peanut Admin 是 monorepo，多个 PHP 包位于 `packages/php/*/composer.json`。Composer 不会因为应用只配置了 monorepo 根 VCS URL，就递归发现这些子目录里的多个包。
- monorepo 根 tag 也不会自动把每个子目录变成独立 Composer package。

请在 v3.2 开头用普通用户能理解的语言明确这一点，不要让人误以为后端不再通过 Composer 安装核心库。

### 已核验的 ng-alain / `@delon` 事实

不要只把 ng-alain 当作抽象类比。以下事实已经过 GitHub 源码和 npm registry 核验，v3.2 应吸收其正确做法：

- `@delon/*` 的源代码集中在 [`ng-alain/delon`](https://github.com/ng-alain/delon) monorepo，而不是每个包独立开发；
- 当前仓库中 `packages/abc`、`acl`、`auth`、`cache`、`chart`、`form`、`mock`、`testing`、`theme`、`util` 各自有独立 `package.json`；
- npm 上的 `@delon/abc`、`@delon/acl`、`@delon/auth` 等是分别安装和分别发布的包，当前主系列共享 `21.3.0` 版本；
- [`scripts/publish/publish.sh`](https://github.com/ng-alain/delon/blob/master/scripts/publish/publish.sh) 会遍历构建产物 `@delon/<package>` 并逐包执行 `npm publish`；
- [`scripts/ci/build-artifacts.sh`](https://github.com/ng-alain/delon/blob/master/scripts/ci/build-artifacts.sh) 先形成版本化构建产物，再由发布脚本逐包发布；
- 这证明“monorepo 统一开发、同一 release train、多个可独立安装的 registry package”是成熟且符合本项目目标的做法。

Peanut Admin 固定基线同样已经存在明确多包边界：

- 11 个 PHP package manifest：`packages/php/*/composer.json`；
- 11 个 Web package manifest：`packages/web/*/package.json`；
- 根 `composer.json` 通过 `type: path` 只解决 monorepo 本地开发，不是对外发布渠道；
- `pnpm-workspace.yaml` 只解决 Web monorepo workspace，不代替 npm registry 发布。

因此，本轮不得再把“把所有 PHP 能力合成一个包”作为默认方向。除非逐包源码核验能证明某个目录不是独立包边界，否则固定原则是：**每个现有独立 PHP/Web package 都应产生自己的可安装发布包**。可额外提供一个 convenience meta-package，但它只能依赖这些独立包，不能取代它们。

---

## 二、必须落实 PHP monorepo 的多包发布拓扑

v3.1 直接假设“每个 Peanut Composer 包对应一个 GitHub private repo + Git tags”，但当前真实源码是 public monorepo。问题不在于“要不要每个包发布”，而在于“怎样从一个开发 monorepo，可靠地产生多个 Composer 可安装包”。

v3.2 必须固定以下产品方向：

- PHP 和 Web 都保留多个独立 package；
- monorepo 是唯一开发和接受 PR 的源码仓；
- 所有第一方包由同一 Peanut release identity 和兼容性矩阵编排；
- 应用可以只安装启用功能所需的包；
- 可以有一个可选 meta-package 提供“安装全部标准能力”的便利，但不能把模块重新合并为一个源码包。

### v1 默认：只读 split package repositories + Packagist

PHP 侧采用 Symfony Components 类的成熟模式，并把具体实现写实：

- 开发仍在 monorepo；
- 发布流水线按 `packages/php/<name>` 自动生成对应的只读 split repository；
- 每个 split repository 根目录都有自己的 `composer.json`，Composer/Packagist 可以把它识别为独立 package；
- split repository 只作分发镜像，不接受人工提交和 PR；
- monorepo release tag 驱动各包 split、依赖顺序发布和 tag；
- public `peanut-admin/*` 包登记到 Packagist，消费应用不需要枚举一组 VCS repository URL；
- 若未来出现私有商业包，再使用 Private Packagist/Satis 或独立私有 Composer repository，不反向污染公开核心包的默认安装路径。

可选择 `splitsh-lite`、`symplify/monorepo-builder` 相关 split 能力、`franzliedke/studio` 配套流程或其他仍维护的成熟工具，但必须核验其当前可用性后固定一个。不能手写一个不可靠的 Git 历史拆分器。

参考事实：

- Composer 官方说明 VCS package 的 `composer.json` 通常位于仓库根目录，普通 root VCS 配置不会递归发现 monorepo 子包：<https://getcomposer.org/doc/05-repositories.md>
- Packagist 官方解释了 monorepo 多包的安装限制；Private Packagist 可直接支持 multipackage，但公开 Packagist 的常见做法仍是 split repositories：<https://blog.packagist.com/installing-composer-packages-from-monorepos/>
- Symfony 长期使用“开发 monorepo + 自动只读 subtree split repositories + 独立 Composer packages”：<https://symfony.com/blog/symfony2-components-as-standalone-packages>
- Symfony 后续仍明确保留各 component 独立安装，以减少无用代码并按依赖启用功能：<https://symfony.com/blog/symfony-packages-are-not-tagged-anymore-when-nothing-changes-between-versions>

### Web v1 默认：npm registry 多包发布

- `packages/web/*` 各自产出 `@peanut-admin/<name>` package；
- 使用 npm-compatible registry 逐包发布，优先评估公开 npmjs.com，因为当前项目是 public open-source；
- 共享一个 release train 和 release manifest，但每个包保留自己的 name、dependency graph、tarball integrity 和可安装边界；
- 发布流程应像 `@delon/*` 一样从 monorepo 构建产物逐包发布，而不是把 workspace 目录直接复制到应用；
- GitHub Packages 只能作为有明确私有分发需求时的选择，不能因为源码托管在 GitHub 就默认采用。

### 发布一致性必须说明

registry 的多包发布不是事务操作。v3.2 必须说明：

- 如何按依赖拓扑发布；
- 同一 release train 是否让全部包共享版本号；v1 默认优先采用 `@delon/*` 式同步版本，降低兼容矩阵复杂度；
- 中途部分包发布成功、部分失败时如何停止、补发或撤销 release candidate；
- 何时发布最终 release manifest，使应用升级器不会消费半发布状态；
- Composer Packagist metadata、npm tarball integrity、Git tag 和源 monorepo commit 如何进入同一 release identity。

必须给出最小可执行示例，包括发布侧和消费侧，但不要在设计文档中写死真实凭证。至少包含：

- PHP split repository 与 Web npm tarball 的结构；
- tag/version 映射；
- 应用 `composer.json` 的 `require` 示例；公开 Packagist 默认不应要求自定义 `repositories`；
- 应用 `package.json` 的 `dependencies` 示例；
- `composer.lock` 中应绑定的 source/dist reference；
- `pnpm-lock.yaml` 中应绑定的 version/integrity；
- CI 认证方式；
- split 发布和 registry 发布失败时的恢复步骤。

同时保留简单结论：Web 包继续由 npm-compatible registry 分发，后端包继续由 Composer/Packagist 安装。前后端使用各自原生包管理器，不要设计自制的通用包安装器。

---

## 三、必须吸收上一轮审核发现的六项修订

### 1. Module descriptor 必须 package-relative

当前 `ModuleRegistryFactory`、`module.json`、namespace 和 frontend path 以应用目录为基准。不能只是把现有 `backend/src/Modules/Peanut/*` 移到 `vendor/` 就宣称完成。

v3.2 必须定义：

- Composer package 内 Module descriptor 的固定路径和 schema；
- descriptor 中 PHP namespace、provider、migration、resources、frontend component key 的表达方式；
- 如何通过 Composer installed packages 或显式 package registry 发现 descriptor；
- 禁止扫描整个 `vendor/` 的边界；
- migration、menus、permissions 等资源如何从 package-relative path 加载；
- 应用自有 Module descriptor 如何与 package descriptor 共存；
- 当前 Settings 等包缺少 ModuleProvider/manifest/resources/migrations 时，U01 的明确迁移写集。

### 2. 区分通用脚手架与逐应用渲染文件

`App.vue`、`backend/config/auth.php`、`frontend/src/clients.ts` 等包含 brand、slug、client key、API prefix 等应用特定值。它们不可能共享一个 release-wide `content_digest`。

请把文件至少分成：

- `scaffold-managed-static`：所有应用同版本内容相同，可使用 release manifest digest；
- `rendered-managed`：由版本化模板 + 规范化输入渲染，每个应用内容不同；lock 必须记录 template identity、render input digest 和 rendered base digest；
- `seed-once/application-owned`：创建后归应用，升级器只报告模板变化；
- `deployment-owned`：环境/部署配置，升级器不得静默覆盖。

必须逐项重新分类固定基线生成树，不能只列几个示例后声称“完整”。至少明确处理此前遗漏或误分的：

- `backend/src/StarterExceptionHandler.php`
- `backend/config/auth.php`
- `frontend/src/App.vue`
- `frontend/src/clients.ts`
- `frontend/src/env.d.ts`
- `frontend/src/style.css`
- `composer.lock`
- `pnpm-lock.yaml`
- 测试与 starter verification 文件

lockfile 不能继续归为 seed-once：代码升级更新包版本时，Composer/pnpm 必须更新并提交对应 lockfile。请把 package manifest 与 lockfile 作为包管理器拥有的独立处理类。

### 3. 不要对 PHP/TypeScript 源码做通用 structured-merge

v3.1 计划解析 `backend/config/modules.php` 和 `frontend/src/app/modules.ts`。这会引入 PHP/TypeScript AST 改写、格式保持和未来语法兼容成本。

优先把它们拆成两个注册面：

- Peanut/package registry：由已安装包及其 descriptor 自动或显式构建，应用不手改；
- application extension registry：应用拥有，升级器不改。

需要一个很薄的稳定组合根把两者合并。只有 JSON 等真正结构化数据使用结构化 API。若仍保留任何 PHP/TypeScript structured-merge，必须逐文件证明没有更简单的所有权拆分，并给出所选成熟 parser、格式保持策略和失败行为；否则从 v1 删除。

### 4. 修复 Composer monorepo 发布问题

必须落实本提示词第二节的发布拓扑。不能再写“给 monorepo URL 加 tag，Composer 就能安装所有 `packages/php/*` 子包”。

### 5. 加强 plan、代码、部署和数据库身份绑定

仅用 `scaffold.lock.peanut_version` 不能证明当前部署代码就是 code-apply 的结果。

v3.2 必须给出一个精简但足够的身份模型，至少绑定：

- release identity / release manifest digest；
- PHP lockfile digest 和已解析 Peanut package versions/references；
- Web lockfile digest 和已解析 Peanut package versions/integrity；
- scaffold lock digest；
- upgrade plan digest；
- code-apply 结果 commit 或 deployment artifact digest；
- db-apply 实际运行的部署制品身份。

说明本地开发使用 Git commit、正式部署使用不可变 artifact digest 时如何统一核验。不要恢复 v2.x 的跨进程复杂 ledger；只保留阻止“计划 A、代码 B、数据库 C”错配所必需的字段和 fail-closed 检查。

### 6. Adoption 必须显式且 fail closed

旧应用可能修改了将要移入包的源码快照，例如 `backend/src/Modules/Peanut/Settings/ModuleProvider.php`。自动删除这些目录会丢失用户修改。

v3.2 必须定义确定性的 Adoption 状态与动作：

- 与固定旧 release 内容一致：可标记 pristine package snapshot，待 code-apply 安全删除；
- 内容不同：默认阻断；
- 逐路径接受保留：明确该路径转为 application-owned/fork，不能随后被删除；
- 手工迁移到 extension：记录迁移证据和旧路径处理结果；
- 无法映射固定旧 release：阻断并要求显式基线，不允许猜测。

`--migrate-package-snapshots` 必须明确是只生成报告、执行确定性搬迁，还是删除已证实 pristine 的快照。每种写操作都要有清晰边界。不要声称任意修改过的 ModuleProvider 都能机械转换成 extension provider。

---

## 四、脚手架升级仍是独立且必须解决的问题

不要因为包发布问题变简单，就忽略 ng-alain 类脚手架复制文件的现实：

- 一部分基础文件在创建应用时被复制到应用仓；
- 用户有权直接修改，而且有时这是最快、最合理的做法；
- 新版本可能改变这些文件；
- 升级器必须知道旧基线、当前内容和目标版本，并决定自动替换、包管理器更新、报告或人工处理。

v3.2 应保留“版本化文件清单”的核心，但将其收敛为能实现的策略：

1. `replace-if-pristine`：当前内容等于记录的 rendered/base digest 时自动替换；
2. `render-if-pristine`：逐应用渲染文件用同一输入重新渲染，当前内容未偏离旧 rendered base 时替换；
3. `package-managed`：由 Composer/pnpm 更新 manifest 与 lockfile，不走文本替换；
4. `report-only`：seed-once 模板有变化时只输出 old/current/new 差异与迁移说明；
5. `manual`：用户已修改且没有安全的确定性合并器时阻断或保留 `.peanut-new`；
6. 只有经过白名单证明的格式才允许 structured merge，不做通用文本或源码 AST 合并。

必须说明每条记录从哪里取得旧版和新版内容。digest 足以判断 pristine，但生成可读差异需要旧/新实际内容；设计应指定版本化 scaffold artifact、release bundle 或其他唯一来源，并绑定 artifact digest，不能含糊写“从 tag 或 package 取”。

---

## 五、前后端是一套升级还是两套升级

请明确采用：

- 对用户是一份 release identity、一份 upgrade plan、一次兼容性判断；
- 内部是三个有边界的处理域：PHP/Composer、Web/npm-compatible package manager、scaffold；
- 数据库仍是单独的 deployment phase；
- 各域可以失败并报告自己的原因，不能在一个域失败后假装整体升级完成；
- 不创建一个试图统一 Composer 与 pnpm 语义的通用执行器框架。

请明确部分失败时的恢复边界。代码升级应在隔离 worktree/branch 中执行，只有全部代码域成功并形成结果身份后才允许进入部署和 `db-apply`。

---

## 六、请核对而不是照抄的现有分类

v3.1 的以下判断不能原样继承，必须以源码证明或修正：

- `backend/src/Modules/Peanut/*` 能否整体进入现有各 Composer 包；
- `frontend/src/modules/peanut-*.ts` 是 package-owned 实现，还是仍包含 host-specific wiring；
- `backend/src/Auth/TenantAuthRuntimeFactory.php`、`FileMediaStorageFactory.php`、`ModuleRegistryFactory.php` 的真实所有权；
- `backend/route/app.php` 是否是通用静态文件还是按 feature/application inputs 渲染；
- `backend/app/provider.php` 和 `StarterExceptionHandler.php` 的配对所有权；
- `App.vue`、`auth.php`、`clients.ts` 是否应为 rendered-managed 或 seed-once；
- 测试、verification、workspace 配置、style/env declarations 的生命周期；
- package manifests 与 lockfiles 的更新责任。

输出一张权威分类表，覆盖固定基线 `starter/` 的所有文件或所有可证明同策略的路径组。表格至少包含：

- path/pattern；
- owner；
- creation source；
- per-app rendered inputs（如有）；
- upgrade policy；
- old/new content source；
- conflict behavior；
- U01/U02/U03/U04 implementation owner。

---

## 七、控制复杂度

本轮目标是可实施，不是理论完备。请遵守：

- 不恢复 v2.x 的 base64 recipe、通用三方合并、跨进程代码 ledger 或全目录 glob；
- 不自制 Composer/npm 的替代品；
- 只为固定基线中已经存在的独立 Composer package 自动生成只读 split repositories；不把这些镜像仓当作人工维护的新源码仓，也不为尚不存在的假想包提前建仓；
- 不假设用户永远通过理想扩展点修改；
- 不静默覆盖或删除无法证明 pristine 的应用文件；
- 不把无法自动解决的冲突包装成自动升级成功；
- U01 只承担后续不可缺少的契约与发布基础，不把整个升级器都塞进 U01。

架构中每个机制都要回答：谁生成、谁拥有、版本身份是什么、升级时怎么判断、失败后怎么恢复。

---

## 八、必须产出的文件

请直接创建或重写以下两个文件：

### 1. v3.2 主设计文档

`docs/plans/2026-07-25-lifecycle-architecture-v3.2.md`

它必须是自包含的唯一候选主设计，不要求读者先读 v3.1 才能实施。至少包含：

- 普通语言总览；
- 前端 npm/pnpm、后端 Composer、scaffold、database 的关系；
- PHP 多包 split + Packagist、Web npm 多包发布拓扑及版本编排；
- package-relative Module 契约；
- 完整文件所有权/升级分类；
- scaffold artifact 与 lock schema；
- plan/code-apply/db-apply 身份模型；
- Adoption 与旧源码快照迁移；
- 失败恢复和人工处理流程；
- U01-U04 重新划分、写集、依赖和可观察完成条件；
- 明确不做事项。

关键 schema 和命令必须给出最小示例，字段命名保持一致，不要同时保留互相冲突的备选模型。

### 2. 交给 Codex 的下一轮审核提示词

`docs/plans/2026-07-25-lifecycle-v3.2-review-prompt.md`

该文件应让 Codex：

- 以固定源码基线核验 v3.2；
- 重点验证 Composer monorepo 发布拓扑是否真的可用；
- 核对每个 Module descriptor/path/namespace 与源码；
- 核对完整 starter 文件分类；
- 检查 rendered-managed、lockfile、Adoption 和身份绑定；
- 给出“可进入 U01 / 修订后可进入 / 仍有根本问题”的明确结论；
- 只审核，不修改文件。

审核提示词中不得预设 v3.2 正确，应要求基于证据反驳错误假设。

---

## 九、完成时的回复格式

完成后只需简洁说明：

1. 如何落实 PHP/Web monorepo 多包发布，以及一句话理由；
2. v3.2 解决了哪些阻塞；
3. 尚未解决或需要实施期验证的风险；
4. 明确列出两个产出文件路径。

不要删除或归档 v3.1 及之前文档，不要修改 `PLAN.md`、`STATUS.md`、`HISTORY.md`，不要实现业务代码，也不要运行测试。本轮只形成 v3.2 设计和下一轮审核提示词。
