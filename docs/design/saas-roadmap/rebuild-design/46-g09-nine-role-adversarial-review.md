# G-09 九角色综合反向复审

> 状态：Historical PASS（47 号校准后已重新打开，不再授权第二次放行）
>
> 复审日期：2026-07-15
>
> 固定审查点：company-os commit `9a72a655`
>
> 结论只表示当时输入下的详细设计曾足以请求第二次编码放行，不表示 Runtime 已实现或 Peanut Admin 已可发布。复审后新增事实以 47 号文档为准。

## 1. 复审方法

本轮分别站在九种职责上对 G-01 至 G-09 进行反向审查。每个角色都必须主动寻找：业务死结、安全绕过、P0/P1 偷换、表/API/页面不一致、旧架构污染和低上下文执行歧义。

审查输入：

- 已确认方向：28 至 36。
- 字段级设计：37 至 42。
- 安全、旧资产和执行：43 至 45。
- 旧仓冻结提交：`45702df` 和标签 `legacy-freeze-2026-07-15`。
- GitHub 当前事实：`peanut-opensource` 组织存在，`peanut-admin` 仓尚未创建。

审查中发现问题先修当前事实源，再重新核对。历史上已经修复的问题仍保留在报告中，避免以后重复引入。

## 2. 九角色结论

| 角色 | 结论 | 核心判断 |
| --- | --- | --- |
| 业务/产品 | 通过 | 登录、选租户、成员、部门、角色、Module 和首个 owner 已有完整闭环；P0 不冒充成熟公开版 |
| SaaS/租户架构 | 通过 | Tenant 是唯一隔离根；门店/仓库等留给 Module；集团、委托不提前建表且不自动继承权限 |
| 身份安全 | 通过 | Tenant/Platform audience、opaque token、rotation、即时失效和首个 owner 密码处理有明确边界 |
| 功能/数据权限 | 通过 | 固定 Permission catalog、RBAC、Provider、全操作路径、last-owner/last-operator 保护闭合 |
| 数据库/性能 | 通过并保留 Runtime 验证 | 复合租户约束、行锁、revision、真实 MySQL 测试明确；绝对性能必须由 D04 实测 |
| 后端模块化 | 通过 | Kernel/Module/Package 三层清楚，Module namespace 可机械转换，禁止跨表并有 Deptrac gate |
| 前端/Admin UX | 通过 | 两个工作区有对应 API、store、cookie 和路由；切租户、失效和移动端状态有验收 |
| 开源维护 | 通过并等待许可证确认 | 旧历史不公开、新仓干净、上游重新生成、组织已确认；Apache-2.0 仍需用户随第二次放行确认 |
| 低上下文交付 | 通过 | 24 个串行任务有模型、依赖、文件白名单、检查、提交和停止线；核心写任务不降到低模型 |

## 3. 发现与处置

### R-BIZ-01 首个租户负责人死结

- 严重度：阻塞。
- 问题：平台能创建 provisioning Tenant，但租户内还没有管理员；租户 API 又要求管理员才能添加成员，形成“先有管理员还是先有成员”的死结。
- 处置：G-01/G-05/G-09 增加平台控制面 owner-candidate 两步流程。只允许精确邮箱、pending 后再 activate，不创建平台 TenantSession。
- 复验：Tenant activate 必须已有 active owner；并发只能建立一个 pending/active owner。

### R-BIZ-02 P0 被误解为完整 LikeAdmin 版本

- 严重度：高。
- 问题：如果 P0 同时承担文件、配置、任务、插件、生成器和完整文档，会再次形成过大底座。
- 处置：P0 只证明安全内核和一条 Module/Admin 端到端链路；市场可用后台明确属于 P1。
- 复验：G-09 没有 P1 Runtime 任务；D05 也不得发布稳定包。

### R-SAAS-01 Position 和 ProductProfile 状态冲突

- 严重度：高。
- 问题：28 号文档曾把 Position 写为第一版建模，ProductProfile 仍写候选，与 G-01/G-04 冲突。
- 处置：Position 固定 P1；ProductProfile 固定 P0 静态文件且无运行时表。
- 复验：28、30、31、37、40、45 一致。

### R-SAAS-02 多组织/集团被偷偷变成 Tenant 层级

- 严重度：高。
- 问题：如果为未来集团预建父子 Tenant 或用 Department 代替，会产生错误权限继承。
- 处置：P0 不建 TenantGroup/Delegation；Department 只在 Tenant 内组织人员；未来关系必须显式授权且不自动继承。
- 复验：G-03/G-07 已要求操作方和目标归属审计，未加入跨 Tenant 万能上下文。

### R-ID-01 Platform API prefix 不一致

- 严重度：高。
- 问题：G-02 曾写 `/api/platform/auth/*`，G-05/G-06 使用 `/api/platform/v1/*`。
- 处置：统一 `/api/platform/v1/auth/*`。
- 复验：当前事实源没有旧 prefix。

### R-ID-02 Owner 初始密码、并发和信息泄露

- 严重度：阻塞。
- 问题：平台直接建 Account 容易覆盖已有 Credential、记录明文密码、并发创建多个 owner 或泄露其他租户关系。
- 处置：已有邮箱禁止密码和覆盖；新邮箱密码只做 request-only hash；Tenant row lock；pending owner role 不产生访问；日志/审计/响应/幂等 response 禁止密码。
- 复验：G-05 新增 23 至 27 验收；G-09 B03/D04 必须实现。

### R-AUTHZ-01 平台页面没有对应 API 和 Permission

- 严重度：高。
- 问题：G-06 已有 operators/roles 页面，G-05 原先只有 Tenant API；Permission key 也只是零散示例。
- 处置：补全 PlatformOperator/PlatformRole/audit/menu endpoint，并冻结 Tenant/Platform core Permission catalog。
- 复验：`core.tenant-owner` 只自动获得 Tenant core，不自动获得 Module Permission。

### R-AUTHZ-02 最后一个 owner/operator 可被移除

- 严重度：高。
- 问题：普通 suspend/leave/role update 可能使租户或平台再无管理员。
- 处置：最后一个 active tenant owner 不得 leave/suspend/失去 owner role；最后一个有效平台管理员不得 suspend/close/失去管理能力。
- 复验：G-05 验收 26/27，G-09 B03/B05。

### R-DB-01 Owner 唯一性只有业务口头约定

- 严重度：高。
- 问题：并发 owner candidate 可能在“检查后写入”之间穿透。
- 处置：锁定 Tenant row，在同一事务查询 pending/active owner，并依赖成员/角色关系唯一约束提交。
- 复验：真实 MySQL 并发测试，不允许用 SQLite 替代。

### R-DB-02 每请求状态查询和授权成本未知

- 严重度：中，非设计阻塞。
- 问题：P0 为即时撤销每请求读取状态/revision，实际 p95 和热点 Tenant 成本尚无实现数据。
- 处置：先保证正确性；A08 建 namespace/revision cache；D04 固定环境建立基线，回归超过 20% 阻塞。
- 未假装完成：当前没有 Runtime，因此不声明 QPS 或延迟值。

### R-MOD-01 Module key、目录和 PHP namespace 无转换规则

- 严重度：高。
- 问题：带点号 key 不能直接成为 PSR-4 目录，低上下文 Agent 会各自取名。
- 处置：点号分段、短横线单词转 PascalCase；`example.work-item` 固定映射 `Modules/Example/WorkItem` 和对应 namespace，前端转 kebab-case。
- 复验：G-04/C01 共用一个转换函数并由 CI 检查。

### R-MOD-02 Queue、文件、导出 P0/P1 混淆

- 严重度：高。
- 问题：早期矩阵同时把任务/文件放 P1，又要求 P0 测队列上下文，容易让执行者提前做任务中心。
- 处置：P0 只做 Cache namespace、Queue/CLI/Schedule 可信 envelope 和 test transport；任务管理、导出、文件均 P1。
- 复验：A08 白名单禁止任务 UI/通用任务表。

### R-FE-01 Platform routes 与后端功能不对齐

- 严重度：高。
- 问题：前端有 `/platform/operators`、`/platform/roles`，后端原先没有管理端点。
- 处置：G-05 补全 API 和精确 Permission；C03 必须先完成契约，C05 才实现页面。
- 复验：OpenAPI route/operationId drift gate 和 Playwright 端到端测试。

### R-FE-02 开发文档再次被拖到最后

- 严重度：中。
- 问题：用户已经确认文档应先行，但功能矩阵曾把“文档站”整体放 P1。
- 处置：A03 在 Runtime schema 前建立最小 VitePress 站和 GitHub Pages CI；P1 只补搜索、版本和完整 API 体验。
- 复验：A03 前没有业务 Runtime 任务。

### R-OSS-01 旧仓仍授权 R-02 且许可证不闭合

- 严重度：阻塞。
- 问题：旧根 README/AGENTS 会继续指挥 Agent 开发；旧仓无顶层 LICENSE，JS packages 无 license 字段。
- 处置：旧仓冻结提交/标签、检查门禁、Gitleaks 全历史扫描；新仓 KEEP=0、干净 Git 历史、上游重新生成。
- 复验：旧仓 `./scripts/check` 通过并明确阻塞历史任务，remote 为空。

### R-OSS-02 品牌远程状态未经验证

- 严重度：中。
- 问题：计划名称如果只在聊天中确认，A01 可能再次创建到错误组织。
- 处置：GitHub API 已确认 `peanut-opensource` 存在且 `peanut-admin` 未创建；A01 仍必须验证当前登录权限，不能降级到个人仓。

### R-EXEC-01 任务白名单和模型等级不够确定

- 严重度：高。
- 问题：若只写“对应测试/相关文件”，低上下文 Agent 会扩大范围；D02 一度低于文档安全内容需要的模型等级。
- 处置：G-09 将关键任务展开到目录/文件模式；所有写任务最低 5.5-sol，schema/auth/authz/module/review 为 5.6-sol。
- 复验：24 个 task row 和 24 个 task heading 一一对应。

### R-EXEC-02 未来 gate 在 A04 就返回非零

- 严重度：高。
- 问题：若未实现的未来 gate 一开始就纳入 `scripts/check` 并失败，所有后续任务都无法提交；若直接成功又是假门禁。
- 处置：每个任务只接入当前已实现且必须通过的 gate，进度表记录未来项；D04 才要求全部 P0 gate 进入总检查。

## 4. 自动一致性检查

在复审修正后执行的文档检查包括：

- G-09 task table 24 行，task card 24 个。
- G-05 章节编号连续，Tenant/Platform API 使用精确 Permission。
- 当前事实源无 `peanut-software`、`@company/*`、旧 `bf_` 实现前缀、`packages/js`、旧 platform auth prefix。
- P0 只以否定或延后方式提及 Application/Entry/Portal/SystemInstance。
- Module 后端/前端目录转换和 OpenAPI `docs/api/**` 路径一致。
- G-01 至 G-09 文档 `git diff --check` 通过。

这些检查只证明文档一致，不代替未来 Runtime 单元、集成、安全和浏览器测试。

## 5. 尚未闭合但有明确停止线的事项

| 事项 | 当前状态 | 为什么不由低上下文 Agent 决定 |
| --- | --- | --- |
| 顶层许可证 | 推荐 Apache-2.0 | 用户必须随第二次编码放行明确确认 |
| 精确依赖版本 | A02 执行时按官方 metadata/许可证形成 DDR | 版本会变化，不能在架构文档里伪造永久版本 |
| GitHub 建仓权限 | 组织/名称已验证，登录权限未在本轮写操作验证 | A01 需要用户当前登录态，失败就停止 |
| 绝对性能、RPO/RTO | 没有 Runtime 数据 | D03/D04 实测，只记录基线和测量值 |
| 管理员设置初始密码 | P0 受控两步直建，P1 增加邀请/找回/强制改密 | 当前用户已确认允许管理员直建，但安全体验需 P1 强化 |
| 集团、委托运营、支持会话 | P1/P2 延后 | 必须先有真实合同、授权和审计需求，不能预建万能关系 |
| `terra/luna` 模型角色 | 未使用 | 没有已确认职责说明，核心任务统一使用可确认的 `sol` 等级 |

上述事项都不会让 P0 执行者猜测：要么在 A02/D03/D04 形成证据，要么保持禁止状态，要么遇到条件不满足立即停止。

## 6. 当时裁决与当前效力

以下代码块保留 2026-07-15 首轮复审的历史裁决，不再代表当前状态：

```text
G-01..G-09 design completeness: PASS
business onboarding closure: PASS
tenant isolation design: PASS
functional and data authorization: PASS
module/package boundary: PASS
frontend/backend contract alignment: PASS
legacy contamination and license gate: PASS WITH USER LICENSE CONFIRMATION
low-context executability: PASS
runtime implementation evidence: NONE YET
next allowed action: ask user for second approval
```

47 号文档已经改变 G-01 至 G-07、G-09 的输入。完成增量校准和再次复审前，不得创建 `/Users/xing/Documents/Dev/Project/peanut-admin/`，不得创建 GitHub repository，不得执行 P0-A01。

以下建议批准语已经暂停，不得按其启动编码：

```text
批准开始 P0-A 运行时代码；Peanut Admin 顶层许可证采用 Apache-2.0。
```
