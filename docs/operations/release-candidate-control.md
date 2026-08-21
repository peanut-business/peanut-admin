# 发布候选控制：给人和智能体看的简单说明

本文解释为什么开发过程中可以反复修改，但最终验收不能一边修改一边继续跑，以及 Peanut
Admin 应如何把两种工作分开。它不是新的业务功能，也不改变 P0-E、Schema 或部署范围。

## 一句话理解

开发阶段是在“修车”；候选验收阶段是在“给一辆已经封存的车做年检”。年检发现问题时，不能
在检测线上直接拆车修理，而要把这辆候选标记为作废，回到开发阶段修好，再封存一辆新候选。

## 两个阶段

### 开发阶段：允许发现和修改

这一阶段可以：

- 修改产品代码、Schema、测试和资格脚本；
- 运行局部测试、本地浏览器 smoke 和针对性检查；
- 根据失败结果修复问题，再重复局部检查。

这一阶段不应启动最终 P0-E，也不应把临时结果写成“已发布”或“已通过”。

### 候选验收阶段：只验证固定版本

进入这一阶段前必须完成：

- 所有已知阻塞问题已经修复；
- 局部检查已经通过；
- 工作树干净；
- 已记录精确源码 commit/tree；
- inventory、scaffold manifest、依赖锁和资源身份已经对应同一候选。

之后只允许读取候选并验证。若发现阻塞问题，候选立即作废，不在候选上继续改代码。

## 为什么不能“边测试边修”

如果测试使用的是会继续变化的工作树，会出现三个问题：

1. 不知道测试结果对应哪一版代码；
2. inventory、scaffold、数据库和截图可能来自不同版本；
3. 同一个问题会在不同版本上反复出现，主会话看起来像一直在重试。

所以候选必须有一个固定身份。可以把它理解为快递单号：源码、生成应用、数据库、截图和测试
结果都必须属于同一个单号。

## 变更后需要做什么

| 变更 | 是否需要重新封存候选 | 最低检查 |
|---|---:|---|
| 纯文档、状态说明 | 否 | Markdown/链接检查 |
| 测试 fixture | 通常否 | 直接相关测试 |
| 资格脚本 | 视影响而定 | 受影响 Gate；改变资格可信性时重新资格 |
| Server/Web/Platform/PC/UniApp 产品代码 | 是 | 受影响模块检查，最终候选重新资格 |
| Schema、迁移、身份、权限、租户隔离 | 是 | 数据/安全合同，最终候选重新资格 |
| inventory、scaffold、依赖锁 | 必须严格核对 | 身份检查后才能资格或发布 |

不是每次修改都要跑全量测试；只有修改了候选实际包含的产品或发布身份，才会使候选失效。

### 分级资格：小修复不默认跑完整 P0-E

完整 P0-E 是七组运行时资格，不是所有补丁的默认检查。发布前先按变更风险分级：

| 级别 | 典型变更 | 发布前最低要求 |
|---|---|---|
| L0 | 文档、状态、纯说明、非运行时元数据 | 文档检查和差异检查 |
| L1 | 不涉及身份、权限、租户、Schema、依赖或部署的局部业务修复 | 受影响模块的聚焦测试和一次直接 smoke；不跑完整 P0-E |
| L2 | 登录/密码、Core 包、权限、租户隔离、Schema/迁移、依赖 lock、scaffold、构建或部署变更 | 聚焦检查后，运行完整 P0-E |

“代码改动很少”不等于风险低。密码策略这次虽然实现可能很小，但它同时影响 Core、登录、
安装、修改密码和依赖版本，属于 L2，不能按 L1 省略完整 P0-E。

L0/L1 的补丁仍必须有固定 commit/tree、版本一致性检查和可追溯的聚焦验证记录；只是不用
重复七组全量运行时场景。若一个补丁同时包含多个级别，按最高级别执行。

## Peanut Admin 中的对应关系

- `scripts/build-application-template-inventory --check`：检查应用模板清单是否仍与源码一致；
- `scripts/build-scaffold-release --check`：检查 scaffold 制品是否与固定源码身份一致；
- `scripts/check-release-consistency --tag vX.Y.Z --qualification <summary.json> --remote origin`：在发布前强制检查 tag、
  Release metadata、资格提交/tree、scaffold 版本、能力账本基线和远端 tag 是否属于同一
  个不可变候选；任一身份不一致都必须停止，不能靠修改发布说明掩盖。
- 预发布时使用 `scripts/check-release-consistency --candidate <commit> --qualification
  <summary.json>`；创建 tag 后由 `scripts/publish-github-release --qualification
  <summary.json>` 生成外部 `RELEASE_CANDIDATE_LOCK.json`。候选锁保存精确 commit/tree 和
  资格摘要，不写回候选自身，避免自引用导致重复封存。
- `scripts/p0e-runtime-qualification`：运行固定候选的 P0-E 资格 Gate；
- `resources/project-resources.json` 和 `resources/p0e-runtime-qualification.json`：规定可用
  的数据库、端口、浏览器、缓存和租约资源；
- `AGENT_EXECUTION_RULES.md`：规定冻结、失败、重跑和停止线；
- 资格失败后：保留失败证据，回到开发阶段，修复后生成新候选，不能复用旧候选资源或证据。

## 正式发布的固定顺序

正式发布必须先把所有发布相关修改合入远端 `main`。不能先在功能分支上做最终 P0-E，之后
再把提交复制、cherry-pick 或合并到 `main` 后直接发布；那会产生“检查过的提交”和“实际
发布的提交”不是同一个的问题。

固定顺序如下：

```text
开发阶段集中修复
→ 合入远端 main
→ 从最新 origin/main 取最终 commit/tree
→ 按变更级别运行最终资格（L2 为完整 P0-E）
→ 对同一 commit 创建 annotated tag
→ 生成外部 RELEASE_CANDIDATE_LOCK.json
→ 发布 GitHub Release
→ 使用同一 tag 部署单租户和多租户 Demo
```

最终资格前使用 `--require-main` 做一次身份检查：

```bash
scripts/check-release-consistency \
  --candidate "$(git rev-parse origin/main^{commit})" \
  --require-main
```

如果 `origin/main` 在资格完成后再次前进，候选立即失效，必须重新从新的 `origin/main`
建立候选；不得把旧资格摘要绑定到新提交。

## 主会话执行顺序

主会话遇到发布或 P0-E 任务时按以下顺序：

1. 先读本文件、`AGENT_EXECUTION_RULES.md` 和资源登记；
2. 处于开发阶段时，只做局部修复和局部验证；
3. 所有阻塞项清零后，先合入最新 `origin/main`；
4. 在 `origin/main` 的精确提交上建立功能冻结、inventory、scaffold 和候选身份；
5. 从该 `origin/main` 固定候选按变更级别运行最终资格；L2 必须运行完整 P0-E；
6. 资格通过后只对同一 commit 创建 tag、生成候选锁并发布；
7. 若失败，只允许一次定向诊断、一次修复和一次新候选重跑。

### 发布门禁的实际含义

正式发布脚本、生产部署脚本和生产升级脚本现在都会先执行一致性检查。它会拒绝以下情况：
资格摘要与冻结 commit/tree 不同、P0-E fixture 与 scaffold 不同、能力账本没有描述当前版本、
版本 metadata 与 tag 不同，或本地与远端 tag 身份不一致。这样可以把“提交后才发现状态互相
矛盾”的问题提前到发布或部署前，并且不会自动移动、覆盖或删除已有 tag。

这套流程的目标不是少做必要测试，而是避免测试结果和正在修改的代码互相污染。
