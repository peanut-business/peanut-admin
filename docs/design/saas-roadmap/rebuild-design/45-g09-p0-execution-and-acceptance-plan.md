# G-09 P0 低上下文执行计划与验收包

> 状态说明（2026-07-18）：本文是已执行的 P0/R00-R07 任务与验收历史。P0 Runtime 已在 `d26186d...` 固定资格，`2963f70...` 后批准私有下游按精确 commit 验证；不得重新执行本文已完成任务或把旧“修复中”文字当作当前状态。

> 状态：Approved and In Runtime Remediation（2026-07-17）；旧 D04 已完成但新 Runtime 资格未通过
>
> 目标仓：`/Users/xing/Documents/company-os/repositories/peanut-admin/`
>
> 远程：`https://github.com/peanut-opensource/peanut-admin`
>
> 本文是执行说明；目标仓已经按 P0-A01 创建，后续任务必须继续遵守本文白名单和停止线。

> 47 号文档加入了多类别业务目标、多目标授权和统一共享主档的新确认。本文已按新契约重排并通过 48 号复审，用户于 2026-07-15 使用 49 号完整批准语放行 P0-A，并确认 Apache-2.0。

P0-A01 已创建公开仓库 `peanut-opensource/peanut-admin`，默认分支为 `dev`，根提交为 `de68cbc`。执行证据见 50 号文档。

2026-07-17 第二波审查发现旧任务把 contract/fixture 证据与真实 Runtime 混在一起。用户已批准在 D01 与 D02 之间插入 `PA-P0-R00` 至 `PA-P0-R07`。当前状态、代码回收表和停止线以 [51-第二波回收与 P0 Runtime 修复裁决](./51-second-wave-recovery-and-runtime-remediation-decision.md) 为准。

## 1. 第二次放行前什么都不能开始

本节保留批准门槛原文作为审计证据。该批准已于 2026-07-15 收到并用于启动 P0-A01；它不允许跳过串行任务或绕过任务白名单。

只有用户明确回复以下语义，才允许执行 P0-A01：

```text
批准按 48 号复审结论开始 P0-A 运行时代码；Peanut Admin 顶层许可证采用 Apache-2.0。
```

如果用户选择其他许可证，必须先修正 G-08 和本计划。只回复“继续”“确认方案”“四项同意”或批准文档，不等于编码放行。

## 2. 执行者唯一事实源

每个任务必须先读目标仓当时的 `AGENTS.md` 和本任务直接相关代码，再按顺序读取：

1. `platform/peanut-admin/28-peanut-admin-candidate-baseline.md`
2. `platform/peanut-admin/30-function-requirement-matrix.md`
3. `platform/peanut-admin/31-repository-and-package-boundaries.md`
4. `platform/peanut-admin/32-decision-confirmation-list.md`
5. `platform/peanut-admin/33-pre-code-design-gates.md`
6. `platform/peanut-admin/37-g01-kernel-data-model.md`
7. `platform/peanut-admin/38-g02-auth-session-context.md`
8. `platform/peanut-admin/39-g03-authorization-data-permission.md`
9. `platform/peanut-admin/40-g04-module-runtime-contract.md`
10. `platform/peanut-admin/41-g05-api-error-contract.md`
11. `platform/peanut-admin/42-g06-admin-shell-contract.md`
12. `platform/peanut-admin/43-g07-security-isolation-test-matrix.md`
13. `platform/peanut-admin/44-g08-legacy-assets-license-disposition.md`
14. `platform/peanut-admin/47-post-review-unified-calibration.md`
15. 本文
16. `platform/peanut-admin/48-post-calibration-nine-role-review.md`
17. `platform/peanut-admin/49-post-calibration-coding-approval-preview.md`
18. `platform/peanut-admin/51-second-wave-recovery-and-runtime-remediation-decision.md`
19. `decisions/2026-07-17-peanut-admin-p0-runtime-remediation.md`

这些路径相对于 `/Users/xing/Documents/company-os/`。

禁止读取并据此实现：

- `platform/peanut-admin/01-*` 至 `27-*`。
- `/Users/xing/Documents/Dev/Project/base-framework/` 中的文档、代码、commit 或 tag。
- 旧 V2/V3/V4、R-00 至 R-18、Application/Entry/Portal/SystemInstance 实现计划。
- DCS、Finance Manager、门店、仓储、运营平台的代码和业务表。

发现当前事实源相互冲突时，停止当前代码任务，在 company-os 提交架构修正；不得选择自己喜欢的一份继续。

## 3. 固定实现边界

P0 不得改写以下事实：

- 技术栈：PHP 8.3 + ThinkPHP 8；Vue 3 + TypeScript + Vite + Element Plus。
- 形态：一个公开 monorepo，模块化单体，共享 MySQL 8 数据库和共享租户表。
- 租户链：`Credential -> Account -> Tenant -> TenantMember`。
- 平台身份：PlatformOperator 使用独立 Session、Guard、API 和 RBAC。
- 数据隔离：所有租户表 `tenant_id NOT NULL`，不使用 `0/null` 表示平台。
- 授权：功能权限和数据权限分离，默认拒绝，列表和单对象动作都必须走 Provider。
- 目标：一个 Tenant 可有多类别、每类多个业务目标；一个成员可管理一个或多个同类目标，TargetSet 不得混合类别。
- 操作：普通写为 `one_required`；多目标读、聚合读、策略发布分别声明；`bulk_write` P0 默认禁用。
- 共享主档：一个真相源和 ID 空间，通过 Module ownership/scope Provider 隔离，不拆平台表/租户表双池。
- 组合：Kernel 不可关闭；Module 可按 TenantModule 开通；Plugin P1；Package 是普通依赖。
- ProductProfile 是版本控制中的静态文件，不建 P0 运行时表。
- P0 不建 Application、Entry、Portal、SystemInstance、Position、Invitation、TenantGroup 或 Delegation。
- P0 只实现邮箱密码；手机号、多凭证、找回、MFA 和 SSO 属于 P1。
- P0 只实现 Admin Web；POS、移动端、小程序是以后独立 Client。
- 不包含任何真实商品、库存、门店、仓储、财务、交易或客户业务。

## 4. 模型使用规则

| 工作类型 | 最低模型 | 说明 |
| --- | --- | --- |
| 仓库、文档、确定性工程脚本 | `gpt-5.5-sol` | 默认执行模型 |
| Schema、认证、授权、数据隔离、Module runtime | `gpt-5.6-sol` | 安全和跨文件推理不可降级 |
| 前端按冻结契约实现 | `gpt-5.5-sol` | 最终浏览器安全复审用 5.6 |
| 依赖/许可证决策和最终反向复审 | `gpt-5.6-sol` | 必须能拒绝不合适依赖 |
| 只读清单、格式核对、结果归档 | `gpt-5.4-mini` 或 `gpt-5.4-sol` | 不允许拥有核心代码写任务 |

本计划不根据 `terra/luna` 名称猜测能力。没有经公司配置文档证明其职责前，核心写任务统一选 `sol`；不能只因为模型可用就降低本文最低版本。

## 5. 并行和分支规则

- 下表所有任务都是写任务，`可并行 = 否`。
- 同一时间只能有一个写任务修改目标仓。
- 可以并行启动最多三个只读复审任务，但必须固定同一个 commit hash，不得修改文件、提交或创建迁移。
- 写任务使用 `codex/<task-id>-<short-name>` 分支，从最新 `dev` 创建。
- 完成后由控制任务检查 staged diff、验证结果和提交，再合并回 `dev`。
- 后续任务只能从已合并的 `dev` 开始，不能从另一个未合并任务分支继续。
- P0-D05 前不创建 `main` 发布分支、不打 release tag、不发布 Composer/npm package。
- Runtime 修复期间允许 R00 从 `codex/p0-d05-remediation` 回收已有候选提交，但必须逐项重验；当前分支有未提交工作时，其他写任务不得修改或暂存该仓。
- R00-R07 全部关闭并重新执行 D02-D04 前，`dev`、旧 D04 和修复分支都不能作为 DCS 固定消费基线。

## 6. 所有任务共同执行合同

开始前：

1. 确认仓库、分支和前置 commit。
2. 执行 `git status --short`；非空则判断是否完全属于本任务，不能覆盖他人修改。
3. 读取事实源和任务白名单。
4. 先添加能因缺少本任务能力而失败的检查或测试，并确认失败原因正确。

实施中：

- 只修改任务白名单；需要扩大范围时停止报告。
- 成熟库优先；没有 Accepted DDR 的依赖不得安装。
- 不使用兼容 shim、静默 fallback、宽松 tenant fallback 或测试后门。
- 不修改已执行 migration；修正必须新增 migration。
- 不使用 `git reset --hard`、`git checkout --`、`git clean`、`--no-verify`。
- 不通过跳过测试、吞退出码、删除断言或降低静态分析级别取得通过。

提交前：

```text
./scripts/check
git diff --check
git status --short
git diff --stat
git diff
```

任务有专项命令时必须追加运行。只暂存白名单，检查 `git diff --cached` 后使用任务指定提交信息。

失败报告必须包含：任务 ID、当前 commit、修改文件、最先失败的测试、最后失败命令、错误摘要、是否可能影响租户安全、需要哪个事实源裁决。不得输出密码、token、cookie、密钥或未脱敏用户数据。

回滚只能通过停止合并或 `git revert <task-commit>`；不得改写共享历史。

## 7. 任务总表

| ID | 产出 | 前置 | 模型 | 可并行 | 提交信息 |
| --- | --- | --- | --- | --- | --- |
| P0-A01 | 干净仓、治理、许可证、冻结门禁 | 第二次放行 | 5.5-sol | 否 | `chore: initialize peanut admin repository` |
| P0-A02 | 依赖和工具 DDR | A01 | 5.6-sol | 否 | `docs: approve p0 dependency decisions` |
| P0-A03 | 最小开发文档站和 GitHub Pages CI | A02 | 5.5-sol | 否 | `docs: establish developer documentation site` |
| P0-A04 | monorepo、Package、宿主和检查空壳 | A03 | 5.5-sol | 否 | `build: establish p0 workspace skeleton` |
| P0-A05 | G-01/G-02 核心 schema | A04 | 5.6-sol | 否 | `feat(kernel): add identity tenant schema` |
| P0-A06 | 核心状态机、Repository 和 bootstrap | A05 | 5.6-sol | 否 | `feat(kernel): implement identity tenant domain` |
| P0-A07 | Tenant 登录、选择、切换和会话 | A06 | 5.6-sol | 否 | `feat(auth): implement tenant sessions` |
| P0-A08 | Platform 登录和可信异步上下文 | A07 | 5.6-sol | 否 | `feat(auth): implement platform and system contexts` |
| P0-B01 | 授权、TargetType/TargetSet schema、Permission catalog 和 revision | A08 | 5.6-sol | 否 | `feat(authz): add authorization persistence` |
| P0-B02 | Tenant/Platform RBAC | B01 | 5.6-sol | 否 | `feat(authz): implement functional authorization` |
| P0-B03 | 成员、部门、角色管理 API | B02 | 5.5-sol | 否 | `feat(admin): implement organization access api` |
| P0-B04 | typed target、DataPermission、共享主档 scope Provider | B03 | 5.6-sol | 否 | `feat(authz): implement data permission engine` |
| P0-B05 | 全路径、多目标、共享主档与跨租户安全套件 | B04 | 5.6-sol | 否 | `test(authz): enforce resource authorization parity` |
| P0-C01 | Module manifest、TargetType registry、安装、迁移、菜单和 TenantModule | B05 | 5.6-sol | 否 | `feat(module): implement module runtime` |
| P0-C02 | 虚构 target/unified-reference/multi-target-work-item 示例 Module | C01 | 5.6-sol | 否 | `feat(example): add module contract examples` |
| P0-C03 | G-05 typed target API、幂等、OpenAPI 和 TS 类型 | C02 | 5.6-sol | 否 | `feat(api): publish p0 openapi contract` |
| P0-C04 | admin-core/admin-shell/web-testing 包 | C03 | 5.5-sol | 否 | `feat(web): implement admin foundation packages` |
| P0-C05 | Tenant/Platform Admin Shell | C04 | 5.5-sol | 否 | `feat(web): implement reference admin shell` |
| P0-C06 | 浏览器、Module 和 audience 全链路验收 | C05 | 5.6-sol | 否 | `test(web): verify admin shell isolation` |
| P0-D01 | CLI 安装、ProductProfile、升级和健康检查 | C06 | 5.5-sol | 否 | `feat(ops): implement install and upgrade workflow` |
| PA-P0-R00 | 校正状态、operation 分类和 Runtime 回收台账 | D01 | 5.6-sol | 否 | `docs(runtime): classify p0 remediation coverage` |
| PA-P0-R01 | 真实 ThinkPHP 路由、Provider、中间件和异常映射 | R00 | 5.6-sol | 否 | `fix(http): activate p0 runtime stack` |
| PA-P0-R02 | 真实认证、Cookie、限流、Session 和可信 Context | R01 | 5.6-sol | 否 | `fix(auth): qualify http session runtime` |
| PA-P0-R03 | P0 核心 API 具体 handler | R02 | 5.6-sol | 否 | `feat(api): implement p0 core operations` |
| PA-P0-R04 | 七个示例 API 和授权链 | R03 | 5.6-sol | 否 | `feat(example): qualify authorized module flow` |
| PA-P0-R05 | 具体 OpenAPI schema 和 handler conformance | R04 | 5.6-sol | 否 | `feat(api): enforce runtime contract conformance` |
| PA-P0-R06 | 真实全栈 E2E 和 G-07 可执行证据 | R05 | 5.6-sol | 否 | `test(web): verify real full stack runtime` |
| PA-P0-R07 | 最小内部 starter | R06 | 5.5-sol | 否 | `feat(starter): add internal p0 starter` |
| P0-D02 | P0 开发手册正文和示例验证 | R07 | 5.5-sol | 否 | `docs: complete p0 developer guide` |
| P0-D03 | 备份恢复、干净安装和升级演练 | D02 | 5.5-sol | 否 | `test(ops): verify recovery and clean install` |
| P0-D04 | 安全、性能、供应链和许可证总闸门 | D03 | 5.6-sol | 否 | `test: qualify p0 foundation candidate` |
| P0-D05 | 九角色 Runtime 复审和放行报告 | D04 | 5.6-sol | 否 | `docs: record p0 runtime qualification` |

## 8. P0-A 任务卡

### P0-A01 干净仓初始化

目标：创建全新 Git 历史和不会误导 Agent 的治理入口，不创建 backend/frontend 运行时。

允许创建：

```text
AGENTS.md
README.md
LICENSE
NOTICE
.gitignore
.editorconfig
docs/README.md
docs/content-status.json
scripts/check
scripts/check-doc-content-status
```

固定要求：

- `git init -b dev`，不得复制旧 `.git`。
- `LICENSE` 使用用户第二次确认的 Apache-2.0 标准全文。
- `NOTICE` 只声明 Peanut Admin 自研部分；尚未引入的上游不提前冒领版权。
- 文档状态使用 JSON 和标准 JSON parser，不手写 YAML parser。
- `AGENTS.md` 明确禁止旧仓、旧计划、具体业务和未批准依赖。
- 公开文件不得写入 `/Users/xing/...`、company-os 私有路径、个人账号、私有产品仓或内部工作流细节；本计划中的本机路径只用于执行控制面。
- `scripts/check` 此时只检查文件边界、文档登记、许可证和旧词/具体业务污染。
- 本地通过后，使用用户已登录 GitHub 创建公开 `peanut-opensource/peanut-admin`；没有组织权限时停止，不创建个人名下替代仓。
- 首次 push 前对新历史运行 Gitleaks。

验收：空历史、单一初始化提交、remote 精确、工作区 clean；不出现 backend/frontend/packages/templates/examples。

### P0-A02 P0 依赖 DDR

只允许修改：

```text
docs/decisions/dependencies/**
docs/standards/dependency-policy.md
docs/content-status.json
scripts/check-dependency-decisions
scripts/check
```

必须用官方 package metadata、官方文档和许可证逐项决定，记录精确版本、版本约束、直接依赖、用途、替代项、adapter、退出方案和安全状态。至少覆盖：

- ThinkPHP 8、ORM、migration。
- PHPUnit、PHPStan、Deptrac、代码格式工具。
- JSON Schema validator；禁止自研 manifest schema parser。
- Vue、Vite、TypeScript、Element Plus、Pinia、Vue Router。
- Vitest、Playwright、ESLint、Vue typecheck。
- OpenAPI 3.1 校验、openapi-typescript、openapi-fetch。
- VitePress。
- MySQL 8 和 P0 cache adapter 的开发镜像。
- Gitleaks 和依赖/许可证扫描工具的 CI 安装方式。

明确 DEFER：Flysystem、队列管理 UI、Excel、通知、Plugin、MFA、OIDC。没有 P0 使用路径就不安装。

验收：所有采用项都是 `accepted`，无占位版本；不创建 composer/package lock，不安装依赖。

### P0-A03 最小开发文档站

允许修改：

```text
package.json
pnpm-workspace.yaml
pnpm-lock.yaml
.npmrc
docs/**
.github/workflows/docs.yml
scripts/check-docs
scripts/check
```

实施：

- 只安装 A02 Accepted 的 VitePress/docs 依赖。
- 建立可构建首页、核心概念、架构、开发规范、任务状态和 API 占位导航。
- 公开正文必须根据 G-01 至 G-09 重新写，不复制旧仓 V4 文档。
- 站点必须自包含，不能要求公开使用者访问 company-os 或本机绝对路径。
- `canonical/draft/superseded/generated` 状态继续由 `content-status.json` 控制。
- superseded 不进入导航和搜索；generated 区禁止手改。
- GitHub Pages 使用官方 Actions；没有 remote 时 workflow 仍必须可本地构建。

验收：`pnpm docs:build`、链接检查、content status 和 `./scripts/check` 通过；无 Runtime 代码。

### P0-A04 Workspace 空壳

允许修改：

```text
backend/**
frontend/**
packages/php/{kernel,data-permission,testing}/**
packages/web/{admin-core,admin-shell,testing}/**
docker/**
compose.yaml
composer.json
composer.lock
package.json
pnpm-workspace.yaml
pnpm-lock.yaml
phpunit.xml
phpstan.neon
deptrac.yaml
eslint.config.*
tsconfig*.json
scripts/**
.github/workflows/ci.yml
docs/decisions/dependencies/** lock evidence only
```

实施：

- 从官方 ThinkPHP 8/Vue/Vite 创建最小宿主，不从旧仓复制。
- PHP 包名固定 `peanut-admin/kernel`、`peanut-admin/data-permission`、`peanut-admin/testing`；PSR-4 分别为 `PeanutAdmin\Kernel\`、`PeanutAdmin\DataPermission\`、`PeanutAdmin\Testing\`。
- backend host 固定 `PeanutAdmin\App\` -> `backend/app/`；Module key 到目录/namespace 必须使用 G-04 唯一转换规则。
- Web 包名固定 `@peanut-admin/admin-core`、`@peanut-admin/admin-shell`、`@peanut-admin/web-testing`。
- 每包有独立 manifest、Apache-2.0、public export 和最小 smoke test。
- backend/frontend 只消费 Package public API，不深层引用。
- compose 只提供 P0 需要的 MySQL/cache 和应用开发服务；无 Adminer、邮件、对象存储、队列 UI。
- `scripts/check` 只调用当前阶段已经实现且必须通过的 gate；未来 gate 记录为进度清单但不伪造成功。每个后续任务把自己的 gate 接入，D04 时所有 G-07 P0 gate 必须纳入。

验收：安装、autoload、typecheck、空构建、Package smoke、Deptrac 和依赖审计通过；没有业务表/API/管理页面。

### P0-A05 核心 Schema

只允许修改：

```text
packages/php/kernel/database/migrations/**
packages/php/kernel/src/Persistence/**
packages/php/kernel/tests/Integration/Schema/**
backend/config/database.php
scripts/test-integration
scripts/check
docs/reference/schema/** generated only
```

实现 G-01 全部表和 G-02 challenge/session/token/auth-security-event 表。字段、索引、复合外键、状态、双边审计语义和 `pa_` 前缀必须逐项一致；迁移同时维护可重复安装和真实 MySQL 8 测试。不得顺手增加 Position、Invitation、support session、业务对象或通用 polymorphic 表。

验收：G-01 数据库约束测试、TEN-009/010/020、空库 up、升级副本 up、受控 down 策略通过。

### P0-A06 Domain、Repository 和 Bootstrap

只允许修改：

```text
packages/php/kernel/src/{Identity,Tenancy,Membership,Platform,Audit,Persistence}/**
packages/php/kernel/tests/{Unit,Integration}/**
backend/app/command/** bootstrap commands only
backend/config/** bootstrap wiring only
scripts/test-unit
scripts/test-integration
scripts/check
```

实现 Account/Credential/Tenant/TenantMember/PlatformOperator 状态机、显式 Repository、密码哈希、revision 和事务服务。Bootstrap 只能创建第一个 Account、PlatformOperator、PlatformRole 和可选首个 Tenant owner；平台首个 owner 和租户管理员直接添加成员都必须先 pending，再由第二动作 activate。精确邮箱已存在时不得覆盖 Credential，新邮箱的初始密码只哈希不回显。Kernel 不强制创建根 Department；只有 ProductProfile 明确配置时才由后续幂等步骤创建。

验收：所有非法状态转换、全局邮箱唯一、Account 多 TenantMember、平台/租户关联不产生权限、密码不回显和 bootstrap 重跑保护通过。

### P0-A07 Tenant Auth/Session

只允许修改：

```text
packages/php/kernel/src/Auth/**
packages/php/kernel/src/Http/** tenant auth only
packages/php/kernel/tests/** auth/session only
backend/route/** tenant auth routes only
backend/app/middleware/** tenant guard only
backend/config/** auth only
scripts/test-security
scripts/check
```

实现 G-02/G-05 的登录、challenge、租户选择、refresh rotation、logout、logout-all、切租户和 `/api/v1/auth/context`。Token opaque、数据库只存 hash、access 15 分钟、refresh 14 天、cookie 和错误码固定。不得使用 JWT、localStorage 或客户端 tenant_id 建 Context。

验收：AUTH-001 至 AUTH-013、AUTH-015/016/020，TEN-017/018/019，refresh 并发和 reuse 通过。

### P0-A08 Platform 与可信系统上下文

只允许修改：

```text
packages/php/kernel/src/{Auth,Platform,Context,Cache,Async}/**
packages/php/kernel/tests/** corresponding tests
backend/route/** platform auth/context routes only
backend/app/middleware/** platform/system guards only
backend/config/** context/cache only
scripts/test-security
scripts/check
```

实现独立 PlatformSession/Guard/API prefix/cookie；实现 HTTP、CLI、Queue、Schedule 的 Context factory，以及只能由授权服务创建的请求级 AuthorizedOperationContext。TenantContext/Session 不得包含 CurrentSubject/CurrentTarget。P0 Queue 只提供可信 envelope、typed requested_targets、handler adapter 和测试 transport，不提供任务管理 UI、通用任务表或真实业务 job。Cache key builder 强制 audience/tenant/revision namespace。

验收：AUTH-014、AUTH-017 至 AUTH-023，SYS-007 至 SYS-009、SYS-017，Tenant/Platform audience 和伪造 current target 全部反向测试通过。

## 9. P0-B 任务卡

### P0-B01 授权持久化

只允许修改：

```text
packages/php/kernel/database/migrations/**
packages/php/kernel/src/Authorization/Persistence/**
packages/php/kernel/tests/Integration/Schema/**
packages/php/data-permission/database/migrations/**
packages/php/data-permission/src/Persistence/**
packages/php/data-permission/tests/Integration/Schema/**
docs/reference/schema/** generated only
scripts/test-integration
scripts/check
```

实现 G-03 DataPermission 表、Permission catalog、ProtectedResource/ResourceOperation、ResourceOperationTargetType、Tenant/Platform Role 关系和 authorization revision。TargetSet 在 set 层保存唯一 target_resource_key，Target row 只保存规范化 string ID；ResourceOperationTargetType 保存 policy_selection_permission。MySQL nullable unique 使用 G-03 指定 generated key，由对应 TargetResolver 解析，不建立伪 polymorphic FK。

验收：迁移约束、重复规则、TargetSet 混合类别、未注册 target type、跨 Tenant target/department、状态和时间边界失败测试通过。

### P0-B02 功能 RBAC

只允许修改：

```text
packages/php/kernel/src/Authorization/**
packages/php/kernel/src/Platform/Authorization/**
packages/php/kernel/src/Http/PermissionMiddleware.php
packages/php/kernel/tests/{Unit,Integration}/Authorization/**
backend/app/middleware/PermissionGuard.php
backend/config/permission.php
scripts/test-unit
scripts/test-integration
scripts/test-security
scripts/check
```

实现 Tenant/Platform 两套 RBAC evaluator、G-05 固定 catalog、route operation 绑定、catalog 同步和 revision cache。平台角色只允许 `platform.*`；Tenant 角色只分配当前可用 core/Module Permission。`core.tenant-owner` 获得全部 Tenant core catalog，但新 Module Permission 不自动加入。P0 无 super flag、deny、角色继承或任意表达式。

验收：PERM-001/002/003/012/013/022/024、AUTH-012、平台角色越界测试通过。

### P0-B03 成员、部门和角色 API

只允许修改：

```text
packages/php/kernel/src/{Membership,Organization,Authorization,Platform}/Application/**
packages/php/kernel/tests/{Unit,Integration}/{Membership,Organization,Authorization,Platform}/**
backend/app/controller/api/v1/{Member,Department,Role}*.php
backend/app/controller/api/platform/v1/TenantOwner*.php
backend/route/tenant-admin.php
backend/route/platform-admin.php owner routes only
docs/api/paths/{members,departments,roles,platform-tenant-owners}.yaml
docs/api/schemas/{member,department,role,tenant-owner}.yaml
scripts/test-integration
scripts/test-security
scripts/check
```

实现 G-05 的成员 pending/activate/suspend、单主部门、部门树、角色和角色分配；同时实现仅限 provisioning Tenant 的平台 owner-candidate 两步流程和 `core.tenant-owner` 固定权限。使用 ETag/If-Match、分页和 Problem Details。禁止 Position、多部门、Invitation 和全局 Account 目录。

验收：跨 Tenant ID、部门循环/深度、停用成员即时失效、批量角色分配、乐观锁和审计通过。

### P0-B04 DataPermission Engine

只允许修改：

```text
packages/php/data-permission/src/**
packages/php/data-permission/tests/{Unit,Integration}/**
packages/php/kernel/src/Authorization/DataPermissionAdapter.php
packages/php/kernel/tests/Integration/Authorization/DataPermissionAdapterTest.php
deptrac.yaml
docs/reference/packages/data-permission.md
scripts/check-architecture
scripts/test-unit
scripts/test-integration
scripts/check
```

实现六种 scope、组内 AND、组间/角色间 OR、SQL Constraint、单对象 Decision、TypedResourceTargetSet/Collection、TargetResolver、TargetCatalogProvider、ConditionProvider、SharedMasterScopeProvider 和缓存。先校验 operation target cardinality，再解析目标；公开接口不得接受 raw SQL；超过 500 target 使用 EXISTS/read model。

验收：PERM-004 至 PERM-013、PERM-021 至 PERM-039 单元和真实 MySQL 查询测试通过；10/500/5000 目标结果一致且大集合不生成无界 IN。

### P0-B05 全路径授权验收

只允许修改：

```text
packages/php/testing/**
packages/php/kernel/tests/Security/**
packages/php/data-permission/tests/Security/**
backend/tests/Security/**
scripts/test-security
scripts/test-integration
scripts/check
```

测试暴露实现缺陷时，只允许修改 `packages/php/kernel/src/Authorization/**`、`packages/php/data-permission/src/**` 或对应 Repository；需要其他路径则停止并拆独立修复任务。

建立 Alpha/Beta 固定 fixture、Project A/B/C、Queue A 和虚构 ResourceProvider，覆盖 list/detail/create/update/delete/batch/import/export-contract/job-contract。必须证明同一成员可读 Project A/B、只写 A；相同字符串 ID 不能跨 Project/Queue 解释；普通 batch 不能跨 primary target。P0 不实现真实导入导出，但必须证明同一个 Provider contract 可被这些 adapter 调用。

验收：TEN-001 至 TEN-020、PERM-001 至 PERM-039、SYS-013/014/019 全通过；SQL 日志证明授权进入查询而非 PHP 后过滤。

## 10. P0-C 任务卡

### P0-C01 Module Runtime

只允许修改：

```text
packages/php/kernel/src/{Module,Menu,Migration}/**
packages/php/kernel/database/migrations/** new G-04 migrations only
packages/php/kernel/resources/schemas/module-manifest.schema.json
packages/php/kernel/tests/{Unit,Integration}/{Module,Menu,Migration}/**
backend/app/module/**
backend/config/modules.php
backend/tests/Architecture/**
deptrac.yaml
scripts/check-architecture
scripts/check-module-manifests
scripts/test-integration
scripts/check
docs/reference/module-runtime/** generated only
```

实现 G-04 manifest、ModuleInstallation、MigrationRecord、MenuDefinition、TargetType/operation cardinality registry、依赖图、三层守卫、启停、迁移 checksum、build registry 和 public Contracts。manifest compiler 必须校验 TargetResolver、TargetCatalogProvider 和 shared_master 所需 SharedMasterScopeProvider。P0 只加载仓库内受信 Module，不上传 Plugin、不卸载数据。

验收：G-04 的 44 个场景和 SYS-001 至 SYS-006、SYS-020 至 SYS-022 通过；循环依赖、重复 target type、缺 Provider、跨表访问和未知前端 route 构建失败。

### P0-C02 虚构示例 Module

白名单：

```text
backend/app/Modules/Example/Reference/**
backend/app/Modules/Example/Target/**
backend/app/Modules/Example/WorkItem/**
frontend/src/modules/example-reference/**
frontend/src/modules/example-target/**
frontend/src/modules/example-work-item/**
examples/module-contract/**
```

实现 `example.target`、依赖它的 `example.reference`，以及同时依赖前两者的 `example.work-item`。只使用以下冻结的虚构对象，不得自行换成真实业务：

- Target Module 拥有 tenant-owned `Project`、`Queue` 及其 TargetResolver/TargetCatalogProvider；Project/Queue 故意存在相同字符串 ID fixture。Target Module 不依赖 Reference 或 WorkItem。
- Reference Module 拥有 `ReferenceItem` 统一 shared_master，以及 Module 自己的 ownership/scope 关系。`pa_example_reference_item` 至少保存 owner_type=`deployment/tenant`、owner_tenant_id、code、name、status；`pa_example_reference_scope` 使用明确 scope_kind=`all_tenants/tenant/typed_target`、target_tenant_id、target_resource_key、target_id、capability=`view/use/maintain`。NULL 只按 scope_kind 的 schema 约束使用，不能自行表示“全局”。部署种子和 Tenant 自建 ReferenceItem 使用同一张主档和同一 ID 空间。
- WorkItem Module 只拥有 business_target_owned `WorkItem` 和策略/发布事实。一个 Tenant fixture 由 Target Module 建 Project A/B/C 和 Queue A。
- WorkItem 的 primary target 是 Project，optional related target 是 Queue；保存 `tenant_id`、`project_id`、可选 `queue_id`、`owner_member_id`、`department_id`、`reference_item_id` 和业务无关状态/标题。跨 Module 的 reference_item_id 不建数据库 FK，不直接 JOIN Reference 表。
- `list` 使用 `many_readable`，`create/update` 使用 `one_required`，`aggregate` 使用 `aggregate_read`。另建虚构 `WorkItemViewPolicy` 和逐 Project 的 `WorkItemViewPolicyPublication`，真实演示 `policy_publish`：策略只保存一次，publication 保存 target、状态、错误和 revision；`bulk_write` 保持禁用。
- 成员 fixture 对 Project A/B 有 read、只对 A 有 update、对 C 无权；Reference private fixture 初始只允许 Project A 使用。

示例表不得由执行者自行改名或合并：

| 表 | 最少字段与约束 |
| --- | --- |
| `pa_example_project` | `id, tenant_id, code, name, status, revision, created_at, updated_at`；`UNIQUE(tenant_id,id)`、`UNIQUE(tenant_id,code)` |
| `pa_example_queue` | 与 Project 同结构和租户约束，独立 ID 空间 |
| `pa_example_reference_item` | `id, owner_type, owner_tenant_id, code, name, status, revision, created_at, updated_at`；CHECK 保证 deployment owner 无 tenant、tenant owner 必有 tenant |
| `pa_example_reference_scope` | `id, reference_item_id, scope_kind, target_tenant_id, target_resource_key, target_id, capability, status, revision`；CHECK 按 all_tenants/tenant/typed_target 要求字段，索引覆盖候选查询 |
| `pa_example_work_item` | `id, tenant_id, project_id, queue_id, reference_item_id, owner_member_id, department_id, title, status, revision, created_by_member_id, created_at, updated_at`；Project/Queue/Core 关系使用 tenant 复合 FK，reference_item_id 不建跨 Module FK |
| `pa_example_work_item_view_policy` | `id, tenant_id, name, config_json, status, revision, created_by_member_id, created_at, updated_at`；策略只保存一次 |
| `pa_example_work_item_policy_publication` | `id, tenant_id, policy_id, project_id, status, error_code, policy_revision, published_at, updated_at`；`UNIQUE(tenant_id,policy_id,project_id)` |

所有 Tenant/业务目标表 `tenant_id NOT NULL`。Reference 两表是 shared_master 所有者自己的明确模型，不得拿 NULL owner/scope 字段代替 owner_type/scope_kind，也不得增加第二张 TenantReferenceItem 主档。

必须证明本人/部门/指定目标、多同类目标、类别混淆拒绝、policy selection permission、统一 shared_master、公开 Query/Event 和跨 Module Contract。不得出现门店、仓库、商品、库存、DCS、Finance。

验收：三个 Module 形成 target -> reference -> work-item 的无环依赖，TenantModule、菜单、Permission、typed Provider、统一 Reference candidates、Project A/B/C 授权、公开 Query/Event 和跨模块禁止直表/JOIN 测试通过。

### P0-C03 API、幂等和 OpenAPI

只允许修改：

```text
packages/php/kernel/src/{Api,Idempotency}/**
packages/php/kernel/database/migrations/** idempotency only
packages/php/kernel/tests/{Unit,Integration}/{Api,Idempotency}/**
backend/app/controller/api/**
backend/app/middleware/{RequestId,ProblemDetails,Idempotency}*.php
backend/route/** missing G-05 routes only
backend/tests/Contract/**
docs/api/**
packages/web/admin-core/src/generated/**
scripts/check-openapi
scripts/test-integration
scripts/check
docs/api/** generated only
```

完成 G-05 Tenant/Platform 目录，包括 operators/roles/menus/audit、typed target input、target-candidates、multiple target list scope 和 unified Reference candidates；使用 OpenAPI 3.1.2、Problem Details、字符串 ID、分页、ETag、Idempotency-Key 和明确 filter allowlist。OpenAPI 是事实源，生成 TypeScript 不手改。

验收：G-05 41 个场景、SYS-010/011/019/021、WEB-008/009/011 和 contract drift 通过。

### P0-C04 Web Package

只允许修改：

```text
packages/web/admin-core/**
packages/web/admin-shell/**
packages/web/testing/**
package.json
pnpm-lock.yaml
tsconfig*.json
eslint.config.*
scripts/test-unit
scripts/check-architecture
scripts/check
docs/reference/packages/web-*.md
```

实现 G-06 固定 public API：两个 audience client/store、single-flight refresh、tenant switch dispose、permission hint、menu route registry、TypedTargetSet/TargetCandidate、operation-scoped target store、TargetSelector/TargetScopeSummary、Shell 状态组件和测试 harness。Package 不包含最终业务页面和品牌主题。

验收：Package export、类型、单元测试、循环依赖、深层 import 和 generated type no-edit 通过。

### P0-C05 Reference Admin Shell

白名单：`frontend/**`、Module frontend contribution、OpenAPI generated consumer；不得改后端契约。

实现 G-06 路由和页面：tenant login/select、members/departments/roles/modules/audit/sample；platform login、tenants/operators/roles/audit。Sample 必须实现 Project 零/单/多目标状态、multiple 列表归属列、aggregate 只读范围摘要和统一 Reference 选择器。布局面向重复工作，使用 Element Plus/Lucide 或已选图标库，不做营销首页；响应式无重叠。任何 typed target 候选不得进入 TenantContext。

验收：typecheck、unit、build、零/单/多目标、统一 shared-master candidates、无权限/失效/Module unavailable/412/429/503 状态和手工 URL 反向测试通过。

### P0-C06 浏览器全链路

只允许修改：

```text
frontend/tests/e2e/**
frontend/tests/fixtures/**
playwright.config.*
scripts/test-browser
scripts/check
```

测试暴露缺陷时只允许修改 `frontend/src/**`、`packages/web/{admin-core,admin-shell}/src/**` 或对应 backend auth/guard；每个修复必须在报告列明，超出这些路径则拆独立任务。

使用 Playwright 在桌面和移动视口验证真实构建：登录、租户选择/切换、平台工作区、角色变化、Project A/B 切换、单目标写、多目标读、归属列、统一 Reference 选择、Module 停用、旧请求晚返回、refresh 并发、403/404/412/429/503。必须做截图和控制台错误检查。

验收：WEB-001 至 WEB-012、G-06 42 场景、AUTH audience 测试通过，无文本/按钮/菜单重叠。

## 11. P0-D 任务卡

### P0-D01 安装、升级和健康

只允许修改：

```text
backend/app/command/{Install,Upgrade,Health}*.php
backend/app/controller/api/{v1,platform/v1}/HealthController.php
backend/config/health.php
profiles/**
schemas/product-profile.schema.json
scripts/{install,upgrade,health-check}
backend/tests/{Install,Upgrade,Health}/**
docs/guide/{install,upgrade,health}.md
scripts/check
```

实现环境检查、首次安装、幂等 bootstrap、静态 ProductProfile 应用、Module migration 顺序、升级前检查、失败停止和数据库/cache/app 健康。Profile 可以显式选择是否幂等创建默认根 Department；未选择时 Kernel 不补建。不得实现远程自动升级、许可服务器、Plugin 上传或数据库 Web 管理器。

验收：空环境安装、重复安装拒绝/幂等、旧 schema 升级、checksum drift、依赖失败、Profile 非授权性和健康降级通过。

### PA-P0-R00 Runtime 状态和 operation 分类

目标：纠正旧资格声明，把已有修复提交逐项分配到 R01-R07，并建立可机器检查的 operation 覆盖台账。本任务不写 Runtime 功能。

只允许修改：

```text
README.md
AGENTS.md
docs/status/**
docs/content-status.json
docs/api/openapi.yaml（只读输入，不改 operation 语义）
scripts/check-runtime-coverage
scripts/check
```

必须产出：

- 记录旧审查 `2444e90`、旧 D04 `f351a21`、当前修复 HEAD 和 worktree 状态。
- 对历史 75 个 operation 和当前 OpenAPI operation 逐项记录 `p0/p1`、具体 handler、响应 schema、测试 ID 和当前证据。
- 将已有提交标记为 `candidate-evidence`，只有重新通过对应任务才转 `complete`。
- 明确当前 `dev`、修复分支和未提交 R06 均不是 DCS 可消费基线。

验收：coverage 台账无未分类 operation；脚本在缺 handler/schema/test owner 时失败；文档状态与 Git 事实一致。

### PA-P0-R01 真实 ThinkPHP HTTP 栈

目标：所有 P0 路径经真实 ThinkPHP 路由、Provider、中间件、异常映射和 request ID 链路，不能只调用 Controller 方法或 fixture。

只允许修改：

```text
backend/{route,public,config}/**
backend/app/{provider.php,middleware.php}
backend/app/http/**
backend/app/middleware/{RequestIdMiddleware,PlatformGuard,TenantGuard,ModuleGuard}.php
backend/tests/{Http,Contract}/**
scripts/test-integration
scripts/check
```

验收：真实 HTTP 进程可启动；P0 路径均已注册；未认证、错误 audience、缺 Tenant、缺 Module、异常和 404 返回正确 Problem；没有测试专用生产 bypass。

### PA-P0-R02 认证、会话和可信 Context

目标：Tenant/Platform 登录、选择、切换、刷新、退出、Cookie、限流和会话失效经真实 HTTP 证明，客户端输入不能建立 Tenant 权限。

只允许修改：

```text
backend/app/controller/api/{v1,platform/v1}/*Auth*.php
backend/app/middleware/{TenantGuard,PlatformGuard}.php
packages/php/kernel/src/Auth/**
packages/php/kernel/src/Context/**
packages/php/kernel/tests/{Unit,Integration}/Auth/**
packages/php/kernel/tests/{Unit,Integration}/Context/**
backend/tests/Http/*Auth*.php
docs/api/schemas/{auth,platform}.yaml
scripts/test-security
scripts/check
```

验收：Tenant/Platform audience 分离；identifier、refresh family、并发刷新、退出、禁用、revision 失效、限流和安全 Cookie 全部通过真实 HTTP；日志无 credential/token。

### PA-P0-R03 P0 核心 API

目标：关闭所有 P0 核心不可用 handler。P1 operation 可保留不可用，但必须在 R00 台账显式分类。

只允许修改：

```text
backend/app/controller/api/{v1,platform/v1}/**
packages/php/kernel/src/{Membership,Department,Authorization,Platform,Tenancy,Module,Menu,Audit}/**
packages/php/data-permission/src/Application/**
backend/tests/{Http,Integration}/**
packages/php/{kernel,data-permission}/tests/{Unit,Integration,Security}/**
docs/api/schemas/**
scripts/test-integration
scripts/test-security
scripts/check
```

必须覆盖：Tenant/Platform workspace、租户生命周期、成员/部门/角色、功能权限、数据策略、Module 开通、菜单和审计 P0 操作。不得将缺失 P0 operation 改判为 P1 以通过验收。

验收：每个 P0 operation 有具体 handler、具体响应 schema、授权要求、错误和自动化测试；同一 handler 不用魔法分支跨 audience。

### PA-P0-R04 示例 Module 与授权链

目标：七个虚构示例 API 通过真实 ModuleGuard、功能权限、typed target、DataPermission、shared-master scope 和审计闭环。

只允许修改：

```text
backend/app/Modules/Example/**
packages/php/data-permission/src/**
packages/php/testing/src/Authorization/**
backend/tests/{Http,Integration}/Example*.php
packages/php/{data-permission,testing}/tests/**
docs/api/schemas/example.yaml
scripts/test-integration
scripts/test-security
scripts/check
```

验收：多目标读、单目标写、类别混淆拒绝、policy publish、shared-master 选择、Module 停用和跨租户拒绝均经真实 HTTP；query 与 object action 授权一致。

### PA-P0-R05 OpenAPI 和 handler conformance

目标：OpenAPI、路由、handler signature、Problem、PHP DTO 和 TypeScript 类型保持同一事实源。

只允许修改：

```text
docs/api/**
backend/route/openapi-generated.php
packages/php/kernel/src/Api/**
packages/web/admin-core/src/generated/**
backend/tests/Contract/OpenApi*.php
packages/php/kernel/tests/Unit/Api/**
scripts/check-openapi
scripts/check
```

验收：P0 响应不使用 `unknown` 或无约束 object；handler/operation/status/header/schema 一致；生成物无手工漂移；不可用 operation 只剩 R00 明确的 P1 项。

### PA-P0-R06 真实全栈 E2E 和 G-07 证据

目标：浏览器测试连接真实 MySQL、ThinkPHP 和前端，不拦截 `/api/**`，并把 G-07 P0 ID 绑定到可机器执行证据。

只允许修改：

```text
frontend/tests/e2e/**
frontend/tests/fixtures/full-stack*
frontend/src/**（仅测试暴露的 P0 缺陷）
packages/web/{admin-core,admin-shell}/src/**（仅测试暴露的 P0 缺陷）
backend/tests/Http/**
backend/app/controller/api/**（仅测试暴露且属于 R01-R05 白名单的缺陷）
playwright.config.ts
scripts/test-browser
tests/security/g07-evidence.json
scripts/check
```

开始前必须确认 worktree 只包含本任务当前未提交工作；其他 Agent 不得接管或覆盖。

验收：desktop/mobile 真实登录、租户选择/切换、Platform/Tenant 工作区、成员/角色/Module、typed target、shared-master 和错误状态通过；测试文件无 `page.route`、`context.route`、HAR 或 API fixture 拦截；0 skip；G-07 每个 P0 ID 可追踪到命令和结果。

### PA-P0-R07 最小内部 starter

目标：证明一个固定内部 starter 可在全新临时目录消费 Peanut Admin Package 并运行，不建设稳定生成器。

只允许修改：

```text
starter/**
scripts/create-internal-starter
scripts/verify-internal-starter
tests/starter/**
.github/workflows/starter.yml
docs/guide/internal-starter.md
docs/content-status.json
scripts/check
```

固定边界：

- 使用当前 monorepo workspace/path Package 和锁定版本。
- 输出最小 backend host、Admin Web、配置样例和 fictional Module 挂点。
- 在全新临时目录执行 install、build、start smoke 和 test。
- 不包含 DCS/Finance/门店/仓库/商品/库存等业务代码。
- 不承诺模板变量、CRUD 生成、外部包发布、自动升级或长期源码覆盖；这些属于 P1。

验收：两次从空目录创建结果可复现；无私有绝对路径和密钥；starter 使用 public Package API，不 deep import host internals。

### P0-D02 P0 开发手册

前置：`PA-P0-R00` 至 `PA-P0-R07` 全部完成。旧 D02 内容允许保留，但所有可用性声明和示例必须按新 Runtime 重新验证。

只允许修改：

```text
docs/**
scripts/check-docs
scripts/verify-doc-examples
.github/workflows/docs.yml
package.json docs scripts only
pnpm-lock.yaml docs dependency change only
```

完成安装、核心概念、表/状态、认证、typed target、操作基数、数据权限、共享主档 scope Provider、Module、前端零/单/多目标贡献、测试、升级、备份、安全和故障排查。教程必须解释一个 Tenant 多类别/多实例、成员多同类目标、单目标写和统一 shared-master 示例。每个命令在干净环境执行；generated 区由 CI 对比，正文不复制旧仓。

验收：docs build、链接、代码片段、content status、示例安装和 Module 教程验证通过。

### P0-D03 备份恢复和干净环境

前置：重新执行后的 D02。恢复和 clean-install 必须同时验证 R07 内部 starter 与 reference host 的当前 schema/Runtime。

只允许修改：

```text
scripts/backup-*
scripts/restore-*
scripts/verify-recovery
scripts/verify-clean-install
scripts/test-recovery
docker/recovery/**
.github/workflows/recovery.yml
tests/recovery/**
docs/operations/backup-and-recovery.md
scripts/check
```

实现开发/参考部署的 MySQL 备份、校验、恢复到全新临时数据库、密钥/上传分离说明和定期演练流程。脚本必须拒绝覆盖当前 active 数据库。生产云厂商只写 adapter/runbook，不自研数据库备份引擎。

验收：从含 Alpha/Beta fixture 的备份恢复，schema/version/行数/hash/登录/隔离测试全部通过；记录 RPO/RTO 测量值而非承诺值。

### P0-D04 总闸门

只允许修改：

```text
tests/{security,performance,supply-chain}/**
scripts/{check,test}-*
.github/workflows/{ci,security,performance}.yml
phpstan.neon
deptrac.yaml
playwright.config.*
scripts/check
docs/content-status.json
docs/security/asvs-p0-map.md
docs/reference/third-party-licenses.generated.md
docs/performance/p0-baseline.md
```

前置：R00-R07、重新执行的 D02 和 D03 全部完成。必要缺陷修复只能进入已有 P0 source/test 路径，必须在 staged diff 单列；发现需要新功能、schema 或 API 时停止并拆任务。

执行 G-07 全矩阵、ASVS 映射、Gitleaks 新历史扫描、Composer/pnpm audit、license inventory、Deptrac、PHPStan、OpenAPI drift、浏览器、并发、性能、恢复和 clean install。必须包含 10/500/5000 typed targets、shared-master scope、类别混淆和双边审计。建立固定基准环境；p95 相对基线退化超过 20% 阻塞。

验收：`./scripts/check` 调用全部 P0 required gates且全绿；0 skipped security test；0 未处置 secret/high-risk/license finding。

### P0-D05 Runtime 九角色复审

只允许先做只读审查，固定重新执行后的 D04 commit。九角色使用第 14 节同一职责，分别输出 findings。任何 P0 finding、串租户风险、API/schema 漂移、错误许可证或文档无法复现都必须创建独立修复任务，D05 保持未完成。P1 finding 只有在被错标为 P0、破坏 P0 冻结边界或被 P0 声明为依赖时才阻塞。

全部 P0 问题关闭后，允许新增：

```text
docs/reviews/p0-runtime-qualification.md
docs/releases/p0-candidate.md
```

不得在 D05 自动创建 main、tag、GitHub Release 或发布 package。最终向用户报告 commit、测试、性能、恢复、许可证和遗留 P1，再等待独立发布批准。

## 12. 稳定检查入口

A04 之后必须逐步兑现，D04 时全部存在并由 `./scripts/check` 调用：

```text
./scripts/check-docs
./scripts/check-dependency-decisions
./scripts/check-architecture
./scripts/check-openapi
./scripts/check-supply-chain
./scripts/test-unit
./scripts/test-integration
./scripts/test-security
./scripts/test-browser
./scripts/test-recovery
./scripts/test-performance
./scripts/check
```

不存在、空实现、永远成功或只打印提示的 required gate 等同失败。

## 13. P0 完成定义

P0 只有同时满足以下条件才算完成：

1. A01 至 D01、R00 至 R07、D02 至 D05 连续完成，没有跳号。
2. G-01 至 G-07 的 P0 字段、状态、API、权限和测试都有实现证据。
3. Alpha/Beta 跨租户矩阵、Platform/Tenant audience 和数据权限全路径通过。
4. 示例 Module 证明同一 Module 可由不同页面/Client 使用，不复制数据所有权。
5. 核心 PHP/Web Package 被 reference host 通过 path/workspace 实际消费。
6. 文档站能从干净 clone 构建，安装和 Module 教程可自动执行。
7. 安装、升级、备份恢复、性能、安全、供应链和许可证总闸门通过。
8. 九角色 Runtime 复审没有未关闭 P0 finding。
9. 示例证明一个 Tenant 有 Project/Queue 多类别和 Project A/B/C 多实例，成员可读多个同类目标但普通写只命中一个。
10. Reference shared_master 只有一个主档和 ID 空间，部署种子与 Tenant 自建记录通过作用范围隔离。
11. 真实全栈 E2E 不拦截 `/api/**`，desktop/mobile 和 G-07 P0 证据 0 skip。
12. 最小内部 starter 可从全新临时目录安装、构建、启动和测试。

P0 完成仍不代表 LikeAdmin 级公开稳定版。手机号、邀请、岗位、配置、字典、文件、任务管理、导入导出、Plugin、代码生成器、完整 API 手册和商业控制面仍属于 P1/P2。

## 14. 九角色复审职责

1. 业务/产品角色：普通 SaaS 用户能否理解 Tenant、Member、Module 和管理流程。
2. SaaS/租户架构角色：隔离根、跨租户、未来集团/委托是否留下安全扩展点。
3. 身份安全角色：Credential、Session、audience、失效、限流和日志是否安全。
4. 权限角色：功能权限、数据权限、所有操作路径和默认拒绝是否闭合。
5. 数据库/性能角色：复合约束、索引、并发、缓存、迁移和查询成本是否可接受。
6. 后端模块角色：Kernel/Module/Package、所有权、Contract 和未来拆分是否真实可做。
7. 前端/Admin UX 角色：双工作区、状态、菜单、切租户和移动视口是否可用。
8. 开源维护角色：仓库、许可证、上游归属、依赖、版本和文档是否可公开维护。
9. 低上下文交付角色：任务是否可以不猜字段、不越范围并稳定复现。

每个角色必须给出 `阻塞/高/中/低/通过`，列文件和条款，不得只写“整体合理”。

## 15. 可复制给侧边任务的基础提示词

```text
你正在执行 Peanut Admin 的一个受控任务。

目标仓：/Users/xing/Documents/company-os/repositories/peanut-admin
事实仓：/Users/xing/Documents/company-os
目标分支：由控制任务显式指定；R00-R07 必须从上一项验收通过的干净提交串行继续，不得重新从旧 dev 起步

本次只执行：<粘贴一个任务卡全文>

先读取目标仓 AGENTS.md，再按 G-09 第 2 节读取事实源。禁止读取旧 base-framework 和 company-os 中 01-27 号历史资料。

严格遵守任务白名单、先失败测试、成熟依赖 DDR、租户安全和独立提交。发现冲突或需要扩大文件范围时停止报告，不得自行猜测。开始前核对指定起点和 worktree；完成后运行任务专项验证、./scripts/check、git diff --check，检查 staged diff，只提交本任务文件，然后停止。

最终报告：修改文件、先失败测试、验证命令和结果、租户隔离证据、遗留风险、commit hash。不得继续下一个任务。
```

控制任务必须把任务卡全文粘贴进去，不能只写“执行 A05”。

## 16. G-09 结论

本计划已经把 47 号校准后的架构和第二波 Runtime 修复裁决变成可串行验收的 32 个工程任务。原 24 个任务继续保留编号，R00-R07 插入 D01 与 D02 之间。执行仍保留三道停止线：既有 P0-A 编码放行、每任务白名单和重新执行后的 P0 Runtime 发布复审。

低上下文 Agent 不需要决定 Tenant 是什么、是否有 Application、数据权限怎么合并、旧代码能否复用或文件放在哪里；这些已经被显式写死。执行者只负责在一个小任务内实现、验证和提交，架构变更继续由高智能控制任务裁决。
