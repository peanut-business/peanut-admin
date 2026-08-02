# 真实代码前的详细设计闸门

> 状态：Design Complete after Recalibration（已通过 48 号九角色复审，等待用户新编码批准）
>
> 目的：防止“架构方向确认”被低上下文 Agent 误解成“可以自行猜字段和开始编码”。

## 1. 两次放行

Peanut Admin 使用两次人工放行：

1. 方向放行：确认 `32-decision-confirmation-list.md`，允许继续详细设计。
2. 编码放行：G-01 至 G-09 全部完成、复审并确认后，才允许初始化新仓和编写运行时代码。

## 2. 必须完成的设计

当前进度：

| 闸门 | 状态 | 产出 |
| --- | --- | --- |
| G-01 Kernel 数据模型 | Recalibrated and Reviewed | `37-g01-kernel-data-model.md` |
| G-02 登录、会话和上下文 | Recalibrated and Reviewed | `38-g02-auth-session-context.md` |
| G-03 RBAC 和数据权限 | Recalibrated and Reviewed | `39-g03-authorization-data-permission.md` |
| G-04 Module runtime | Recalibrated and Reviewed | `40-g04-module-runtime-contract.md` |
| G-05 API 和错误 | Recalibrated and Reviewed | `41-g05-api-error-contract.md` |
| G-06 Admin Shell | Recalibrated and Reviewed | `42-g06-admin-shell-contract.md` |
| G-07 安全与租户隔离测试矩阵 | Recalibrated and Reviewed | `43-g07-security-isolation-test-matrix.md` |
| G-08 旧资产处置和许可证清单 | Reviewed，校准不影响 | `44-g08-legacy-assets-license-disposition.md` |
| G-09 P0 执行计划和验收包 | Recalibrated and Reviewed | `45-g09-p0-execution-and-acceptance-plan.md`、`48-post-calibration-nine-role-review.md` |

47 号文档是本轮增量校准输入，48 号文档是复审裁决，49 号文档是当前自然语言放行预览。46 号只保留首轮历史复审，不再作为当前批准依据。

### G-01 Kernel 数据模型

必须输出字段、类型、非空、默认值、索引、唯一约束、外键、软删除策略和状态机：

- Account
- Credential
- Tenant
- TenantMember
- PlatformOperator / PlatformRole
- Department
- Role / Permission / MemberRole
- TenantModule
- AuditEvent

同时说明全局表、平台表、租户表及其受控关联。不得使用抽象对象名代替真实字段。

### G-02 登录、会话和上下文协议

必须覆盖：

- 邮箱凭证如何解析 Account。
- 如何列出和选择 TenantMember。
- 租户切换如何签发新会话。
- 平台会话与租户会话的 audience/guard 区分。
- Tenant、Account、TenantMember 状态变化如何立即失效。
- HTTP、CLI、队列和计划任务如何建立可信上下文。
- 缺少上下文、伪造 tenant_id 和平台凭证访问租户 API 的错误码与审计。

### G-03 RBAC 和数据权限契约

必须输出：

- 角色、菜单、动作、API 权限的存储和计算方式。
- 数据权限规则、条件组、指定目标的字段级表结构。
- 基础范围：本人、本部门、部门及下级、全部、指定部门。
- Module 如何声明资源和提供业务对象范围。
- Provider 的查询谓词与单对象动作接口。
- 创建、详情、更新、删除、批量、导入导出和后台任务的执行顺序。
- 多角色/多规则合并算法、缓存键、失效方式和默认拒绝。
- 至少 20 个越权/组合测试用例。

### G-04 Kernel、Module 和 TenantModule 契约

必须输出：

- 不可关闭 Kernel subsystem 清单。
- Module manifest 的字段和版本规则。
- 部署安装、租户开通、成员授权三层守卫。
- TenantModule 的状态、有效期、配置和禁用行为。
- 迁移、种子、菜单、权限、配置和前端贡献的加载顺序。
- 模块依赖、循环依赖、停用、升级失败和回滚规则。
- 跨模块命令、查询和读模型接口；禁止直接读写/JOIN 的检查办法。

### G-05 API 和错误契约

必须输出真实请求/响应示例：

- 登录、租户选择、上下文查询和退出。
- 租户、成员、角色、权限、部门和 TenantModule。
- 数据权限列表、详情和越权错误。
- 幂等键、分页、排序、批量、关联 ID 和错误码。
- OpenAPI/schema 的事实源和前端类型生成方式。

### G-06 前端 Admin Shell 契约

必须输出：

- 登录、租户选择、平台端和租户端的路由边界。
- 菜单、按钮和 API 权限的职责分工。
- Module 页面和路由贡献方式。
- `frontend/` 与 `packages/web/` 的 public API。
- 无权限、模块未开通、租户停用和会话失效状态。
- 桌面端/移动端基础响应式要求；POS、小程序仍作为独立 Client 后续实现。

### G-07 安全与租户隔离测试矩阵

必须覆盖：

- ORM 与原生 SQL 的读写隔离。
- 跨租户 ID、复合外键和唯一约束。
- 缓存、锁、幂等、审计和异步上下文。
- 平台凭证、租户凭证和支持会话。
- 文件、导出、队列等 P1 能力接入时必须复用的测试契约。
- 密码、会话、CSRF/CORS、限流、日志脱敏和依赖安全。

### G-08 旧资产处置和许可证清单

对旧 `base-framework` 每项资产标记：

- KEEP：可直接迁移，说明来源和许可证。
- REWRITE：概念有用但实现/命名需要重写。
- DROP：过度设计、问题代码或内部业务内容。

在旧仓建立冻结点，扫描历史中的密钥、私有域名、客户信息和不可公开许可证。未完成前不得把旧历史推到 GitHub。

### G-09 P0 执行计划和验收包

必须把 P0-A 至 P0-D 拆为低上下文 Agent 可执行任务，每项写明：

- 只读事实源和禁止读取的历史资料。
- 允许修改的文件和不可扩大范围。
- 前置任务、输入、输出和停止线。
- 单元、集成、浏览器和跨租户安全测试。
- 独立提交范围、提交信息和回滚方式。
- 失败时必须报告的信息，禁止用跳过检查换取通过。

## 3. 编码放行标准

只有同时满足以下条件，才能创建新运行时仓并开始 P0-A：

1. D 项方向已确认。
2. G-01 至 G-09 均有完成 47 号校准并通过 48 号复审的正式文档，不是聊天结论。
3. 业务、租户安全、权限、数据库、开源维护和初级开发者视角重新复审通过。
4. 所有不确定项有明确负责人、阶段和默认拒绝行为。
5. 旧运行时计划和历史文档已从当前导航隔离。
6. 用户明确回复 48、49 号文档给出的新编码批准语。

在此之前，可以继续研究、写文档、做原型测试和检查第三方库，但不得生成正式业务表、API 或前端管理页面。
