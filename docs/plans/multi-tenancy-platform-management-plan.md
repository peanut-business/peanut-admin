# Peanut Admin 多租户与平台管理计划

> 状态：当前权威执行计划  
> 日期：2026-08-11  
> 架构基线：`docs/design/saas-enhancement-blueprint.md`

## 1. 当前目标

当前不建设完整 SaaS 商业产品，先交付三项能力：

1. **多租户基础**：统一 Account、Tenant、TenantMember、可信 TenantContext、权限、模块和全链路隔离。
2. **实例内平台管理**：使用独立 PlatformOperator 管理本应用实例的 Tenant 生命周期、首个 owner、TenantModule 和平台审计。
3. **独立运营平台边界**：为跨应用实例的 Release、版本、升级、健康和备份冻结管理协议，并在独立项目中实现。

当前明确延后：套餐销售、订阅、计费、试用、续费、发票、自动收款、商业配额、自助购买、自定义域名商业流程和应用市场。

## 2. 三类能力不能混用

| 能力 | 所属位置 | 当前是否需要 |
| --- | --- | --- |
| Tenant 隔离、成员、权限、租户切换 | Peanut Core + 应用 Host | 需要 |
| Tenant 创建/暂停、首个 owner、模块开通 | 单个应用实例的平台管理面 | 需要 |
| Release、实例、升级、健康、备份 | 独立运营平台应用 | 需要，单独立项 |
| 套餐、订阅、计费、试用和续费 | 未来 SaaS 商业控制面 | 暂不需要 |

PlatformOperator 只治理本实例 Tenant，不拥有租户业务数据权限。独立运营平台的运维账号是另一套身份，不复用 PlatformOperator 或 TenantMember。

## 3. 当前事实

- Peanut Admin Core 已有 Tenant、TenantMember、PlatformOperator、认证、RBAC、typed-target 数据权限、TenantModule、审计和 Host 组合实现。
- 已发布的 Core Alpha 包和固定资格仅证明精确版本能力，不等于当前移动分支可直接作为所有产品生产基线。
- Peanut Admin 应用仓仍未接入 Tenant Runtime。
- 核心项目生成器已经存在，但当前模板仍带固定包快照；尚未冻结公司级正式承接版本。
- DCS 正式主项目必须从批准的 Peanut 模板新建；旧 DCS/POS Runtime 只作业务规则、契约、样本、迁移和验收参考。
- DCS D1 不需要等待完整 SaaS，只需要通过 Peanut 最小承接 Gate。

## 4. 执行顺序

| 阶段 | 目标 | 状态 |
| --- | --- | --- |
| MT00 | 关闭在途核心能力和包/文档事实冲突 | 进行中 |
| MT01 | 冻结公司级 Core/Generator 承接基线 | 未开始 |
| MT02 | Peanut Admin 采用单默认 Tenant | 未开始 |
| MT03 | 完成 SQL、缓存、文件、任务和审计隔离 | 未开始 |
| PM01 | 完成本实例 Tenant 平台管理 | 未开始 |
| MT04 | 完成多租户前端和应用 Host 闭环 | 未开始 |
| MT05 | 双模式安装、升级、下游和浏览器验收 | 未开始 |
| MT06 | 发布多租户稳定基线 | 未开始 |
| OP01 | 独立运营平台协议和项目立项 | 未开始 |
| OP02 | 独立运营平台首个实例管理闭环 | 未开始 |
| SAAS-FUTURE | 套餐、订阅、计费等完整 SaaS | 暂缓 |

## 5. MT00：关闭当前在途工作

- 完成核心媒体/工作流候选的资格、文档事实收口、发布与独立下游采用。
- 统一 PR、Registry、Git tag、Release、应用 lock 和状态文档。
- 不允许当前移动 HEAD 或未资格 Alpha 候选成为新的产品承接基线。

完成条件：存在唯一、精确、可回溯的 Core 和 Generator 候选输入；没有冲突的发布/资格表述。

## 6. MT01：最小承接 Gate

公司级承接基线必须固定精确 source commit/tree、generator digest、Composer/npm 版本和不可变来源，禁止只写分支名。

最低门禁：

1. 相同参数两次生成得到确定性结果，并记录完整生成身份。
2. 新项目可空库安装、迁移、启动，不依赖旧项目目录、vendor 或数据库。
3. 产品自有 namespace、Tenant Clients、API prefix、Module 和迁移所有权可配置。
4. fictional/example Module 可完整移除。
5. Tenant/Client 隔离、跨 Tenant 拒绝、Module disable、权限/Data Provider 缺失均 fail closed。
6. 一个虚构 Host command 的领域写、幂等、审计和 Outbox 在失败注入下无部分提交。
7. Admin Web 完成登录、Tenant 选择、拒绝访问和一个外部 Module 页面最低 smoke。
8. 明确生成器不覆盖更新已有项目，并记录后续升级方案。

Gate 失败时只允许继续合同、数据和 fixture 准备，不允许复制旧 Runtime 绕过。

## 7. MT02：Standalone 采用默认 Tenant

- 安装器创建默认 Tenant、Account、TenantMember 和首个 owner。
- 现有管理员、角色、部门和岗位映射到租户模型。
- 为业务表按所有权账本增加并回填 `tenant_id`，再收紧非空和复合唯一约束。
- Standalone 默认隐藏 Tenant 选择和平台入口，保持现有业务结果不变。
- 禁止维护单租户/多租户两套业务 Service 或长期双字段兼容。

## 8. MT03：全链路隔离

隔离必须覆盖：

- SQL 查询、关联、聚合和唯一约束；
- Redis key、缓存失效和锁；
- 文件对象、临时下载和配额；
- 队列、定时任务、导入导出和异步上下文；
- 搜索、事件、审计、日志和诊断标签。

两个 Tenant 的相同业务对象 ID、缓存 key、文件名和任务不得互相可见。无可信 TenantContext 默认拒绝。

## 9. PM01：实例内平台管理

当前只实现多租户运行必需的治理：

- PlatformOperator 独立登录、角色、权限和审计；
- Tenant provision/activate/suspend/close 状态机；
- 首个 Tenant owner 安全建立；
- TenantModule 开通和配置验证；
- Tenant 暂停后拒绝新会话和业务写入；
- 平台与租户会话、Repository、权限目录和导航完全分离。

PM01 不包含套餐价格、订阅、计费、试用、续费、商业配额或跨实例管理。

## 10. MT04–MT06：应用闭环与发布

- 完成登录后 Tenant 选择、可信切换、撤销和旧上下文清理。
- Standalone 和多租户模式使用同一 Release，只由部署配置决定界面和能力组合。
- 真实浏览器验证：平台建 Tenant/owner → owner 登录 → 配角色/模块 → 成员使用代表业务 → Tenant 暂停。
- 验证 Standalone 空库、现有 v1.0.0 前滚、多租户空库和至少一个真实下游采用。
- 发布时 Core、Generator、Registry、Git tag、Release manifest、应用 lock 和文档版本一致。

完成 MT06 后，Peanut 可以作为公司产品的稳定多租户脚手架；这不宣称已完成完整 SaaS。

## 11. DCS 承接规则

- DCS D1 的领域合同、数据清洗、fixture、迁移映射和验收矩阵现在可以继续。
- D1 正式 Runtime 必须等待 MT01 中的 DCS 专用 `PA-DCS-ADOPT-01` 通过。
- D1 不等待 PM01 全部能力，更不等待 SAAS-FUTURE。
- DCS 新主项目从批准模板生成；旧 Runtime 不作为正式实现基线。
- DCS 的 Tenant 与经营主体关系必须在正式表结构前单独冻结。

## 12. OP01–OP02：独立运营平台

运营平台必须在独立仓库、独立数据库和独立部署中实现，首期管理：

- Application、Release、Artifact 和兼容范围；
- DeploymentInstance、环境、版本和实例凭据；
- 升级计划、执行结果、回滚和备份证明；
- 健康心跳、告警和最小脱敏诊断；
- 运维身份、角色和不可变审计。

业务实例应主动出站拉取签名任务；运营平台不得直连业务数据库、保存客户管理员密码、自动成为 PlatformOperator/TenantMember，或汇总租户业务数据。运营平台离线时业务实例继续运行。

## 13. SAAS-FUTURE 停止线

未经新产品决策，不实现：

- 套餐、订阅、试用、续费和计费；
- 支付、发票和商业结算；
- 商业配额与超额计费；
- 客户自助购买和应用市场；
- 跨租户代运营；
- 以 SaaS 名义建立超级管理员或绕过 Tenant Guard。

未来启动时，以 `docs/plans/saas-enhancement-development-plan.md` 为历史输入重新冻结执行合同，不直接照旧计划编码。
