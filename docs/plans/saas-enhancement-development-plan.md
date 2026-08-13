# Peanut Admin 完整 SaaS 增强开发计划（暂缓）

> 状态：未来规划，当前不执行
> 前置基线：Peanut Admin v1.0.0 产品化基线完成  
> 权威架构：`docs/design/saas-enhancement-blueprint.md`

> 2026-08-11 决策：当前只推进多租户能力和必要的平台管理，不推进套餐、订阅、计费、试用、续费等完整 SaaS 商业化。本文件保留未来完整 SaaS 路线；当前唯一执行顺序改由 `docs/plans/multi-tenancy-platform-management-plan.md` 管理。

## 1. 执行原则

- 未来获得完整 SaaS 商业化授权后，先确认当前核心能力、发布与下游采用基线，再冻结新的 SaaS 执行合同。
- 先冻结业务知识图谱、数据归属和不变量，再写 migration/Runtime/UI。
- Core 只保留跨产品通用实现；应用持有产品业务；独立运营平台单独立项。
- Standalone 和 SaaS Host 共用一个内核、一套业务 Service 和一条 Release 线。
- 每项门禁只做一次最低充分验证；失败后按项目范围规则停止或单独获批修复。
- 每个阶段从独立功能分支进入 `dev`；阶段验收后再由 `dev` 进入 `main`。

## 2. 总体顺序

| 阶段 | 目标 | 当前状态 | 主要交付 |
| --- | --- | --- | --- |
| PRE-S01 | 关闭在途核心媒体能力与包事实冲突 | 历史阶段，已由 MT00/MT01 完成 | 固定候选、下游采用、Registry 发布 |
| S01 | 冻结 SaaS 业务知识图谱和迁移分类 | 历史编号；对应多租户范围已完成 | 图谱、ADR、表归属账本、迁移合同 |
| S02 | 让 Standalone 运行在单 Tenant 内核上 | 历史编号；对应 MT02 已完成 | 默认 Tenant、身份迁移、业务表租户化 |
| S03 | 完成可信租户上下文和全链路隔离 | 历史编号；对应 MT03 已完成 | Guard、缓存/文件/任务隔离、安全矩阵 |
| S04 | 完成 SaaS Host 租户控制面 | 历史编号；对应 PM01 已完成 | PlatformOperator、Tenant 生命周期、TenantModule |
| S05 | 完成多租户前端和 Host 产品闭环 | 历史编号；对应 MT04 已完成 | 登录选租户、切换、平台端/租户端边界 |
| S06 | 升级、空库、下游和真实浏览器验收 | 历史编号；对应 MT05 已完成 | 双模式安装升级、跨租户对抗、Release 候选 |
| S07 | 发布 SaaS 稳定基线 | 历史编号；对应 MT06 `v1.1.0` 已完成 | Registry 锁、应用 Release、文档 |
| OP01 | 独立运营平台立项和协议冻结 | 未开始 | 独立仓库、领域模型、管理协议 |
| OP02+ | 实现实例运营闭环 | 未开始 | 登记、Release、健康、升级、备份、审计 |

OP01 可以在 S03 后进行只读设计和协议评审；其 Runtime 不进入 Peanut Admin 仓，也不阻塞 S01–S07 的应用内 SaaS 能力。

## 3. PRE-S01：关闭当前在途工作

### 范围

- 完成核心媒体通用能力的固定候选资格、文档事实收口、发布与独立下游采用。
- 处理核心 PR #5 与 workflow 分支的状态文档冲突。
- 将项目生成器从包快照复制切换到标准 Registry 依赖，或形成有时限的迁移门禁。

### 停止线

- 不在本阶段增加 Tenant Host 代码。
- 未得到固定版本和消费证据前，不修改 Peanut Admin 的 SaaS Runtime。

### 完成条件

- 核心发布身份、Registry 版本、应用锁和下游采用证据一致；
- `dev` 无未合并业务实现或过期状态文档；
- SaaS 阶段有唯一可消费的核心版本起点。

## 4. S01：知识图谱与迁移合同

### 工作包

1. 分别构建核心、Peanut Admin 和首个下游应用的能力图谱。
2. 为每张表标注 `global`、`platform`、`tenant-owned`、`tenant-derived` 或 `instance-local`。
3. 为缓存、文件、队列、定时任务、导出、日志建立同样的归属账本。
4. 冻结 Account、Tenant、TenantMember、PlatformOperator、BusinessSubject、BusinessTarget 关系。
5. 冻结 Standalone 管理员迁移、默认 Tenant 和回滚策略。
6. DCS 单独冻结 Tenant 与经营主体的关系；未经冻结不得作为首个采用方写表。

### 交付

- `docs/architecture/saas-capability-graph.md`
- `docs/architecture/saas-data-ownership-ledger.md`
- `docs/architecture/saas-standalone-migration-contract.md`
- 对应核心/应用 ADR 与最低验收矩阵

### 门禁

- 每个租户拥有的数据都能定位唯一写入者、读取 Guard 和测试 owner；
- 没有用 Tenant 替代公司、门店、仓库或部门；
- 不存在未分类业务表或“无上下文返回全部”的路径。

## 5. S02：Standalone 单 Tenant 内核采用

### 工作包

1. Peanut Admin 通过标准 Composer/npm 依赖消费固定核心版本。
2. 安装器创建默认 Tenant、Account、TenantMember 和首个 owner。
3. 将现有管理员/角色/部门/岗位映射到核心身份和租户权限模型。
4. 按账本分批为业务表增加并回填 `tenant_id`，再收紧非空和复合唯一约束。
5. 保持 Standalone UI 和业务结果不变；平台控制面保持关闭。

### 迁移策略

- `expand`：新增 nullable 字段、索引和映射表；
- `backfill`：在实例锁下按唯一默认 Tenant 回填；
- `verify`：校验孤儿记录、唯一性和读写结果；
- `contract`：改为 `NOT NULL` 并移除临时兼容读取。

禁止长期双写旧字段和新字段。

### 门禁

- 空库安装和 v1.0.0 前滚各通过一次；
- 用户可见业务结果与 v1.0.0 一致；
- 所有请求均产生可信 TenantContext；
- 没有启用多租户 UI 时仍可正常部署和使用。

## 6. S03：可信上下文与全链路隔离

### 工作包

- 接入 TenantAuthService、TenantContext 和核心授权/数据权限适配器；
- SQL Repository 强制 Tenant 条件与租户内唯一约束；
- 缓存、锁、文件、队列、异步任务、导入导出和审计携带 Tenant；
- 后台任务使用明确 TenantSystemContext，不从全局隐式继承；
- 建立两个 Tenant 的对抗 fixture 和跨面安全矩阵。

### 门禁

- 错租户 ID、篡改客户端 Tenant、缓存碰撞、文件越权、队列串租和导出越权全部失败；
- 无 TenantContext 的租户资源访问默认拒绝；
- 租户停用后新会话和写入立即拒绝；
- PlatformOperator 不能调用租户业务 Service。

## 7. S04：SaaS Host 租户控制面

### 工作包

- PlatformOperator 登录、角色和权限；
- Tenant provision/activate/suspend/close 状态机；
- 首个 Tenant owner 安全建立；
- TenantModule 开通、配置验证和模块可用性 Guard；
- 平台审计和允许的租户治理镜像审计；
- 自定义域名仅作为候选 Tenant 解析，不产生授权。

### P0 不包含

- 计费收款、自动续费、插件市场；
- 跨租户代运营；
- 父子租户和集团权限继承；
- 每租户独立数据库；
- 独立运营平台 Runtime。

### 门禁

- 平台与租户会话不能混用；
- Tenant 生命周期、首个 owner 和 TenantModule 并发操作幂等；
- 平台没有租户业务读权限；
- 平台/租户审计能还原实际操作方和目标方。

## 8. S05：多租户产品闭环

### 工作包

- 登录后 Tenant 选择、可信切换和会话撤销；
- Standalone 隐藏多租户入口，SaaS Host 显示租户控制面；
- 租户管理员管理成员、角色、部门和已开通模块；
- PlatformOperator 与 TenantMember 使用不同导航、路由守卫和权限目录；
- PC/UniApp 等消费端使用同一租户会话契约，不各自实现 Tenant 规则。

### 门禁

- 真实浏览器完成 PlatformOperator 建租户 → 建 owner → owner 登录 → 配角色/模块 → 普通成员使用业务 → 租户暂停的最低业务链；
- 使用两个 Tenant 交叉尝试相同业务对象 ID，用户可见结果符合隔离规则；
- 前端隐藏不是唯一授权措施，所有关键动作均有后端拒绝证据。

## 9. S06：安装、升级和下游验收

### 验收矩阵

| 场景 | 最低验收 |
| --- | --- |
| Standalone 空库 | 安装、登录、代表业务 CRUD、重启 |
| Standalone v1.0.0 前滚 | 数据回填、角色权限、代表业务结果不变 |
| SaaS Host 空库 | 平台 bootstrap、两个 Tenant、隔离与状态流转 |
| SaaS Host 升级 | migration ledger、备份证明、失败停止线 |
| 核心包 | Composer/npm 固定版本、无 deep import、Host/override 合同 |
| 真实下游 | 至少一个非 Peanut Admin 产品消费 Tenant/权限/模块能力 |
| 浏览器 | 桌面管理端最低充分主链；不做无关视觉对标 |

### 门禁

- API、数据库和真实浏览器证据相互一致；
- 失败 fixture 精确清理；
- 不重复 LikeAdmin parity 和 PB09 已完成验收；
- 文档、安装器、Release manifest 与实际包版本一致。

## 10. S07：稳定发布

### 交付

- 一个稳定 Composer 核心包和一个稳定 npm 管理端核心包；
- 支持 Standalone/SaaS Host 的 Peanut Admin Release；
- 空库安装、v1.0.0 升级、备份和回滚文档；
- 开发文档、SaaS Host 管理手册、租户管理员手册；
- 下游应用采用与升级示例。

### 完成条件

- `dev` 阶段证据闭合后合入 `main` 并打不可变 tag；
- Registry、Git tag、Release、应用 lock 和文档版本一致；
- 脚手架生成的新应用不依赖仓库内包快照即可安装；
- 当前长期目标中的“单应用 SaaS 多租户能力”完成。

## 11. OP01：独立运营平台立项

OP01 是新应用，不在 Peanut Admin 仓中实现。

### 前置输入

- S03 已冻结实例身份、Release manifest 和管理协议最小数据；
- 至少两个真实应用/实例提出共同运营需求；
- 明确版权、许可证、部署和商业边界。

### 首期领域

- Application、Release、Artifact、CompatibilityRule、Entitlement；
- DeploymentInstance、Environment、InstanceCredential；
- OperationPlan、UpgradeRun、BackupEvidence、HealthSnapshot；
- OperationsAccount、OperationsRole、OperationsAuditEvent。

这些实体都属于运营平台，不能复用业务应用内的 Account、TenantMember 或 PlatformOperator 表。

### 首期用户结果

- 登记一个已经部署的应用实例；
- 查看其版本、健康和最后备份；
- 为一组兼容实例生成升级计划；
- 实例 Agent 校验签名、备份并幂等执行；
- 查看成功/失败/回滚结果和完整审计；
- 运营平台断开后实例业务仍正常运行。

### 明确延后

- 自动计费、发票和收款；
- 应用市场；
- 客户业务数据分析；
- 远程 SSH 托管；
- 跨租户客服代运营。

## 12. 风险与控制

| 风险 | 控制 |
| --- | --- |
| 现有表租户归属错误 | S01 逐表账本，未分类不迁移 |
| 只有 SQL 隔离、其他面串租 | S03 全链路矩阵 |
| Standalone/SaaS 双 Runtime | 单代码线、模式只影响 Host 组合 |
| 平台身份越权 | 物理分表、独立 Guard、负向测试 |
| 运营平台变成超级后台 | 独立应用、协议最小化、禁止业务数据与 DB 直连 |
| 核心/应用同时大幅变化 | PRE-S01 固定核心，随后分阶段采用 |
| DCS 概念被 Tenant 覆盖 | 接入前单独冻结经营主体映射 |
| 发布和包身份漂移 | Release manifest + Registry/tag/lock 一致性门禁 |

## 13. 未来重新启动规则

PRE-S01 与本文件 S01–S07 中对应的多租户脚手架范围已由现行 MT00–MT06 完成；本节
不再提供当前领取指针。若未来获得完整 SaaS 商业化授权，必须先以
`docs/plans/multi-tenancy-platform-management-plan.md`、最新 Release 和实际代码重建
差异，再冻结新的阶段编号与执行合同，不能把本文件的“未开始”或旧顺序直接恢复为
当前任务。独立运营平台仍须进入独立项目，不得写入 Peanut Admin/core。
