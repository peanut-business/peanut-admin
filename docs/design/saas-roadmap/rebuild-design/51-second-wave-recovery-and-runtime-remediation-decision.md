# 第二波回收与 P0 Runtime 修复裁决

> 状态说明（2026-07-18）：本文记录第二波修复开始时的正式裁决和证据，执行状态已经完成。当前 external-host consumption 资格以真实仓库 `../../repositories/peanut-admin/docs/reviews/external-host-consumption-qualification.md` 为准，DCS 映射以 `I01-dcs-integration-mapping.md` 为准；下文 `not qualified`、R00-R07 `in-progress/candidate/not-started` 和 dirty 修复分支停止线只表示当时状态，不得作为当前任务看板或下游基线。

## 文件用途

本文件把第二波只读审查、用户确认、当前稳定分支和正在进行的修复分支收成一个正式入口，避免继续用旧任务状态解释当前 Peanut Admin。

它不替代代码仓的测试和 Git 事实，也不把修复分支升级为发布基线。

## 一、正式结论（历史状态）

```text
Peanut Admin P0 方向和核心模型：继续有效
旧 D04 f351a21：历史资格基线，不再满足新 Runtime 完成定义
codex/p0-d05-remediation 933dd00：修复候选，不是稳定消费基线
当前未提交全栈 E2E：R06 in progress，不得覆盖或提前宣称完成
P0 Runtime：not qualified（本文形成时）
DCS 可消费 commit：not available（本文形成时）
当前事实：P0 internal-alpha qualified at d26186dfb23af34c62c58b4da94fea77bd63d724
external-host consumption lock：0ab02a9b735ba9f4c23509cb366b9bf04039ebf8
qualification record commit：c63e06e25e35855cfefab890d7ee43c6e0cf839d
当前 DCS：正式 I01 已完成；W0/D0 与生产仍未放行
```

用户已经确认：

1. 在 P0-D01 与 P0-D02 之间插入 `PA-P0-R00` 至 `PA-P0-R07`。
2. P0 Admin Shell 必须最小但真实可用，不能以 fixture 拦截代替后端。
3. P0 交付最小内部 starter；稳定生成器和 CRUD 生成器属于 P1。
4. DCS 正式集成映射 `I01` 必须独立生成，候选报告不能改名冒充正式交付。
5. D02、D04、D05 保留编号，但必须以后置的新 Runtime 证据重新验收。

对应 Accepted Decision：`decisions/2026-07-17-peanut-admin-p0-runtime-remediation.md`。

## 二、证据分层

### 1. 第二波原始审查基线

固定提交：

```text
2444e904007a4584d90ffe5c39810eafb13b427a
```

该提交的审查证明：OpenAPI 和 Admin Shell 外形已经存在，但大量接口由统一不可用处理器承接，ThinkPHP 真实 HTTP 栈和浏览器证据不充分。它只能作为“为什么启动 Runtime 修复”的历史证据。

### 2. 当时稳定协作分支

截至 2026-07-17：

```text
dev = origin/dev = f351a21bff55b4d8a82ef39c12d8bf4d058a3e5c
commit: test: qualify p0 foundation candidate
```

`f351a21` 是旧 D04 标准下的资格提交。新审查提高了 Runtime、真实 HTTP、核心 API、全栈 E2E 和 starter 的完成定义，因此它不得再被描述为“合格 P0 Runtime”或 DCS 可消费 commit。

### 3. 当时修复分支

截至本文件形成时：

```text
branch: codex/p0-d05-remediation
HEAD: 933dd006e02e73c0fd3d083e6d0278e970b598eb
ahead of f351a21: 20 commits
worktree: dirty
```

分支已出现真实路由、授权目录、数据权限、菜单、平台/租户管理、示例 Module 和 OpenAPI 类型等候选实现。当前未提交文件正在增加不拦截 `/api/**` 的全栈浏览器测试、真实后端服务器和 fixture setup。

当时硬规则：

- 这些提交只能由 R00 按新任务验收回收，不能按 commit 标题自动标记任务完成。
- 未提交改动属于正在进行的 R06 工作，其他 Agent 不得覆盖、暂存或提交。
- 修复分支未清洁、未完成 R07、未重新执行 D02-D04 和 D05 前，不得合入 `dev` 或供 DCS 固定依赖。

## 三、R00-R07 回收状态（历史执行看板）

本表只解释第二波修复如何被拆解和回收。当前 R00-R07、D02-D05 和资格状态已经被真实仓库状态文档取代，低上下文 Agent 不得按本表重开任务、覆盖仓库或否认后续已经完成的 DCS 正式 I01。

| 任务 | 当前候选证据 | 当前状态 | 关闭条件 |
| --- | --- | --- | --- |
| `PA-P0-R00` | 本文件、重排后的 G-09、当前分支/operation 分类 | `in-progress` | 75 个历史 operation 和当前 OpenAPI operation 全部有 P0/P1、handler、schema、测试归属；计划与仓库状态一致 |
| `PA-P0-R01` | `b927290` 及后续 route/provider/middleware 提交 | `candidate-evidence` | 干净提交上真实 HTTP 路由、中间件、异常和 request ID 契约测试通过 |
| `PA-P0-R02` | `75dca9f`、`5dd791f`、`50bcf75` 等认证修复 | `candidate-evidence` | Tenant/Platform 登录、选择、切换、刷新、退出、限流和 audience 走真实 HTTP |
| `PA-P0-R03` | `044ebf9`、`99af0e7`、`194fc0e`、`0717d90` 等核心 API | `candidate-evidence` | 所有 P0 核心 operation 有具体 handler、具体 schema、状态码和自动化证据；P1 operation 明确分类 |
| `PA-P0-R04` | `9699e6e` 和此前 Module/DataPermission 修复 | `candidate-evidence` | 七个虚构示例 API 经 ModuleGuard、typed target、DataPermission、shared-master 和真实 HTTP 闭环 |
| `PA-P0-R05` | `933dd00` OpenAPI typed contract | `candidate-evidence` | handler signature、OpenAPI、Problem、generated PHP/TS 类型和 drift gate 一致 |
| `PA-P0-R06` | 当前未提交 `full-stack.e2e.ts`、Playwright 和 `scripts/test-browser` | `work-in-progress-uncommitted` | desktop/mobile 不拦截 `/api/**`，真实 MySQL/后端/前端，G-07 ID 可机器追踪且 0 skip |
| `PA-P0-R07` | 尚无独立 starter 目录、manifest 和 clean temp smoke | `not-started` | 固定内部 starter 在全新临时目录 install/build/start/test；不包含产品业务或稳定生成器承诺 |

## 四、历史执行顺序

```text
历史 D01
-> R00
-> R01
-> R02
-> R03
-> R04
-> R05
-> R06
-> R07
-> 重新执行 D02 文档与示例
-> 重新执行 D03 恢复/干净安装
-> 重新执行 D04 总闸门
-> D05 九角色 Runtime 终审
```

已有实现可以回收，不要求机械重写；但任何任务只有在其新白名单、专项测试和停止线下形成可审查的独立提交后才算完成。

## 五、D02、D04、D05 新边界

### D02

- 只能描述 R00-R07 已证明的真实能力。
- 不得把 `API_OPERATION_UNAVAILABLE` 或仅存在 OpenAPI contract 的 operation 写成可用行为。
- 示例命令必须在内部 starter 或真实 reference host 中执行。

### D04

- 必须在 R00-R07、D02、D03 后重新运行。
- `scripts/check` 和 `docs/content-status.json` 是显式白名单及验收证据。
- 必须覆盖真实 HTTP、全栈 E2E、security evidence、供应链、恢复、性能和 skipped test。

### D05

- 固定重新执行后的 D04 commit，只读九角色审查。
- 所有 P0 finding 阻塞。
- P1 finding 仅在被误标为 P0、破坏冻结边界或被 P0 声明为依赖时阻塞。
- 不自动创建 `main`、tag、Release 或发布 Package。

## 六、P0 Shell、starter 与 P1 generator

### P0 最小真实 Shell

必须使用真实后端、数据库、路由、中间件、认证、功能权限和数据权限，并提供：

- Tenant 登录/选择和 Platform/Tenant 双工作区。
- 成员、部门、角色、Module 和审计的 P0 必需流程。
- typed-target 多目标读、单目标写和 shared-master 示例。
- loading、empty、forbidden、not-found、conflict、unavailable 状态。

### P0 内部 starter

只负责一套固定装配在干净目录的可重复 smoke，不承诺长期模板升级、业务代码生成或外部发布。

### P1 generator

稳定项目模板、变量合同、Module/CRUD 生成、外部 Package 发布、SemVer 兼容、codemod 和长期升级体验全部留在 P1。

## 七、DCS 集成边界

- Peanut Admin 只提供 Account、Credential、Tenant、TenantMember、Department、Role、Permission、DataPermission、Module、typed-target 和公共 Package。
- Store、Warehouse、Supplier、Product、Inventory、Trade、Pricing、Settlement 和 POS 属于 DCS 私有 Module/Client。
- 正式 `I01-dcs-integration-mapping.md` 已在 `0ab02a9b735ba9f4c23509cb366b9bf04039ebf8` 上独立完成；DCS 只能固定该 Runtime commit。
- `c63e06e...` 是资格记录提交，目录外候选报告只能作为输入；两者都不能替代 I01 或 `0ab02a9...` lock。

## 八、历史停止线与当前校准

1. 本文件形成时的 dirty 修复分支和 R06/R07 停止线已经成为历史，不得据此重开已完成的 Runtime 修复任务。
2. company-os 可以校正计划、Decision、README 和 AGENTS，但不能把治理文档当作 Runtime 验收；当前验收事实只来自真实仓库 checks 和资格报告。
3. DCS、Finance Manager、智慧教学和任何客户业务不得进入 Peanut Admin Kernel、通用 Package、内部 starter 或示例 Module。
4. 下游只能固定有资格证据的 40 位 Runtime commit 并生成正式映射；DCS 当前唯一 lock 是 `0ab02a9...`，禁止把 `dev`、`c63e06e...`、未发布 Package、目录外候选报告或历史过程文档当成消费基线。
5. internal-alpha 资格不等于生产就绪、公开稳定版、tag、GitHub Release、Composer/npm Package 或外部客户发布批准。

## 九、完成定义（历史）

本轮“计划校准”完成只表示：

- 正式计划、状态、任务卡、Decision、AGENTS 和 README 一致。
- 旧完成声明被纠正。
- 当前代码候选证据被分配到 R00-R07。

它只是本文形成时的计划校准定义。当前 external-host 资格、DCS 正式 I01 和精确 consumption lock 已另行证明；DCS 实现仍待 W0、D0-D7 和用户批次放行。
