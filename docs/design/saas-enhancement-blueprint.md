# Peanut Admin SaaS 增强蓝图

> 状态：架构基线 v1（实现事实同步）
> 日期：2026-08-13
> 适用范围：Peanut Admin 核心包、脚手架生成的业务应用、应用内 SaaS Host 模式，以及未来独立运营平台

> 当前实施优先级：先完成多租户能力、应用实例内的最小 Tenant 平台管理，以及独立运营平台所需的实例管理边界；套餐、订阅、计费、试用、续费等完整 SaaS 商业化能力暂不实施。当前执行计划见 `docs/plans/multi-tenancy-platform-management-plan.md`，本文其余 SaaS 内容作为未来架构预留。

## 1. 结论

Peanut Admin 后续采用同一套租户感知内核，支持两种应用运行模式：

- **Standalone**：一个独立部署实例服务一个客户，初始化一个默认 Tenant，对最终用户隐藏 SaaS 平台管理能力。
- **SaaS Host**：一个独立部署实例服务多个 Tenant，启用该实例自己的租户控制面。

跨多个已部署应用实例进行版本、授权、升级、健康、备份和发布治理的**运营平台，是另一个独立应用**。它不属于 Peanut Admin 核心包，不嵌入任何业务应用，也不是 SaaS Host 内的平台后台。

这三个层次不得合并：

1. Tenant 是一个应用实例内部的数据隔离根。
2. SaaS Host 控制面只管理本实例的 Tenant 和本实例能力开通。
3. 独立运营平台只管理应用、Release 和部署实例，不天然拥有任何实例内的租户业务数据权限。

## 2. 目标与非目标

### 2.1 目标

- 一个脚手架可以生成普通单租户应用，也可以将同一应用升级为多租户 SaaS Host。
- Standalone 与 SaaS Host 共享核心业务实现、权限引擎和数据模型约束，不维护两套核心。
- Tenant、账号、成员、组织、业务主体和业务目标有稳定且不混淆的语义。
- 租户隔离覆盖数据库、缓存、文件、队列、导入导出、日志和异步任务。
- 平台身份、租户身份和运营平台身份完全分离。
- 为独立运营平台预留稳定的实例管理协议，但不让运营平台侵入业务运行时。
- 现有 Peanut Admin v1.0.0 应用可以渐进升级，升级前后用户可见业务结果保持一致。

### 2.2 非目标

- 不复制 LikeAdmin SaaS 的每租户一组业务表方案。
- 不把门店、仓库、公司、团队、渠道或部门统一叫作 Tenant。
- 不实现跨租户超级管理员或静默绕过 Tenant Guard。
- 不让运营平台直连每个实例数据库或复用实例管理员账号。
- 不把插件市场、计费、CRM、工单、支付结算一次性塞入 SaaS P0。
- 不为了 SaaS 重写现有业务 API、UI 路由或前端框架。
- 不把 DCS 的经营运营平台等同于 Peanut 的实例运营平台。

## 3. 统一概念模型

| 概念 | 定义 | 明确不是什么 |
| --- | --- | --- |
| Core | `peanut-admin/core` 与 `@peanut-admin/admin` 对外提供的通用内核 | 不是完整业务应用，也不包含独立运营平台 |
| Application Definition | 一个可发布应用的产品定义、模块组合和兼容范围 | 不是一次具体部署 |
| Application Instance | 某个应用在一个环境中的独立部署，拥有自己的数据库、配置和密钥 | 不是 Tenant |
| Tenant | 一个 SaaS Host 实例内的客户/使用组织和数据隔离根 | 不是公司、门店、仓库、部门或渠道的通用别名 |
| Account | 可跨 Tenant 复用的全局登录身份 | 不直接保存租户角色和部门 |
| TenantMember | Account 在某个 Tenant 内的成员身份、状态、角色和权限 | 不是全局 Account，也不是业务主体 |
| PlatformOperator | 某个 SaaS Host 实例内管理 Tenant 生命周期的独立平台身份 | 不是 TenantMember，不是独立运营平台账号 |
| Business Subject | 能独立承责、结算、形成业务关系的经营主体 | 不由 Tenant 概念自动替代 |
| Business Target | 门店、仓库、项目、供应商等由业务 Module 拥有的授权目标 | 不是子 Tenant |
| Operations Platform | 治理多个 Application Instance 的独立应用 | 不是核心模块，不是跨租户超级后台 |

### 3.1 DCS 的额外约束

DCS 中必须继续区分：

- Tenant：隔离根和使用 DCS 的客户边界；
- 经营主体：承担业务、结算或法律责任的主体；
- Store、Warehouse、Supplier 等：DCS Module 拥有的业务对象；
- Account/TenantMember：登录凭证与租户内成员身份。

Tenant 与 DCS 经营主体的具体对应关系尚未冻结。DCS 正式承接多租户能力前必须单独确定是一对一、可关联，还是完全分离；在此之前不能通过字段命名或外键暗中做决定。

DCS 是从 Peanut Admin 脚手架生成的独立应用，不是本仓内建业务 Module。Peanut 只冻结
以下采用边界，具体目录、表、状态机、API 和事件由 DCS 仓维护：

- Product 拥有 SPU/SKU 和商品主数据；Inventory 只能引用 SKU，不能反向修改商品。
- Procurement 拥有采购单和收货流程；创建采购单时通过 Product 查询合同校验并保存必要
  快照，确认收货后调用 Inventory 入库命令。
- Inventory 拥有余额、预占和流水；入库使用可信 TenantContext、Warehouse、SKU、数量和
  幂等键，并在同一事务写余额与流水。
- Module 之间只通过公开命令、查询 DTO 或具备 Outbox/重试/幂等的事件协作，不直接读写
  其他 Module 私有表。
- 当前 Peanut Host 没有已验证的通用 Outbox/Event Bus；在 DCS 批准事件基础设施前，优先
  使用显式同步合同。

同一应用中的门店、供应商和客户是 DCS Business Subject/Target，不是固定 `tenant_type`。
需要供应商成员独立登录时，可以把供应商主体显式关联一个 Tenant，其账号使用通用
Account/TenantMember/RBAC；但当前 Peanut 仓没有 Supplier、Relationship、Contract、
ProductGrant 或供应商客户端 Runtime，这一模型仍是**推荐新增到 DCS**而非当前能力。

## 4. 四层产品边界

```text
Peanut Core（通用依赖）
  └─ 脚手架生成的独立业务应用
       ├─ Standalone：一个默认 Tenant
       └─ SaaS Host：多个 Tenant + 本实例租户控制面

独立运营平台（独立仓库、独立部署、独立数据库）
  └─ 通过受控管理协议治理多个业务应用实例
```

### 4.1 Core

Core 负责稳定且跨产品可复用的能力：

- Account、Credential、Tenant、TenantMember；
- TenantContext、Tenant Guard、会话与租户切换；
- RBAC、数据权限、TenantModule、审计；
- 通用 Host/override、迁移和测试契约；
- 前端租户上下文、权限和模块消费能力。

Core 不负责应用品牌、产品业务表、业务页面、客户计费规则、应用 Release 编排或跨实例运维。

### 4.2 独立业务应用

脚手架生成的应用是可独立开发、部署和发布的真实产品。它拥有：

- 产品特定 Module、业务模型和业务规则；
- 自己的数据库、域名、文件和密钥；
- 对核心稳定扩展点的标准化 Host/override；
- 自己的发布节奏和可回滚迁移。

应用必须能够在与运营平台断开连接时继续提供核心业务服务。

### 4.3 应用内 SaaS Host

SaaS Host 是独立业务应用的一种运行模式，不是另一套脚手架。它增加：

- Tenant 创建、暂停、关闭和首个负责人建立；
- TenantModule 开通和租户级配置；
- 租户域名/入口解析；
- 本实例 PlatformOperator、平台角色和平台审计；
- 租户容量、配额和应用内模块状态的最小管理；不负责跨实例部署健康。

PlatformOperator 只拥有明确的 `platform.*` 权限。它不能读取商品、订单、库存等租户业务事实。未来如需技术支持或代运营，必须使用独立的、限时的 SupportSession，包含客户授权、精确能力范围、到期时间和双边审计。

### 4.4 独立运营平台

运营平台是单独立项、单独仓库、单独数据库、单独部署的应用。其目标是管理由 Peanut Admin 创建并部署的应用群，而不是经营任何一个客户的业务。

首期边界：

- 应用定义与所有者；
- Release、制品、校验和、依赖及兼容范围；
- 部署实例登记、环境、区域、版本和状态；
- 授权/Entitlement 状态，但不决定应用内 TenantMember 权限；
- 升级计划、备份证明、执行结果和回滚状态；
- 健康心跳、最低诊断信息和告警；
- 运维操作员、最小权限和不可变审计。

明确禁止：

- 直连实例数据库；
- 持有或回显客户数据库密码、业务管理员密码；
- 自动成为任一实例的 PlatformOperator 或 TenantMember；
- 以“平台维护”为由绕过租户隔离；
- 将多个实例的客户业务数据汇总到运营平台。

## 5. 两种应用模式

### 5.1 Standalone

- 安装时创建唯一默认 Tenant 和首个 Tenant owner。
- 业务表仍遵守 `tenant_id NOT NULL`，避免以后从单租户升级 SaaS 时重写业务层。
- UI 默认不显示租户选择和平台控制面。
- 不要求运营平台在线，也不要求实例登记后才能运行。
- 后续启用 SaaS Host 时通过显式迁移和配置切换，不复制数据库或业务代码。

### 5.2 SaaS Host

- 同一实例可承载多个 Tenant。
- 登录先认证 Account，再由可信服务解析 Tenant 和有效 TenantMember。
- 客户提交的 `tenant_id` 只能作为候选，不能建立授权。
- 所有租户资源必须在可信 TenantContext 下访问。
- 平台表与租户表使用不同 Repository/Guard；同库部署不代表权限相同。
- 租户暂停后拒绝新会话和业务写入，但不级联删除业务数据。

### 5.3 单代码线原则

模式差异通过启动配置、能力开关和 Host 组合表达。禁止：

- `standalone_*` 与 `saas_*` 两套业务 Service；
- 为兼容旧应用长期维护无 `tenant_id` 的平行业务表；
- 在查询层用“没有 TenantContext 就返回全部”兜底；
- 通过前端隐藏代替后端授权。

## 6. 数据、权限与状态不变量

### 6.1 数据归属

| 数据 | 权威所有者 | 运营平台是否持有 |
| --- | --- | --- |
| Account/Credential | 业务应用实例 | 否 |
| Tenant/TenantMember | SaaS Host 实例 | 仅实例级数量/状态摘要，不持有成员明细 |
| 商品、订单、库存等业务数据 | 业务应用 Module | 否 |
| Application/Release/Instance | 独立运营平台 | 是 |
| 升级任务、备份证明、健康状态 | 独立运营平台 | 是 |
| 应用内平台审计 | SaaS Host 实例 | 只接收必要运维结果摘要 |
| 跨实例运维审计 | 独立运营平台 | 是 |

### 6.2 隔离覆盖面

每个租户拥有的资源都必须同时覆盖：

- SQL 查询、唯一约束、关联和聚合；
- Redis key、缓存失效和锁；
- 文件对象 key、临时下载和配额；
- 队列 payload、消费者上下文和重试；
- 定时任务、导入导出和批处理；
- 搜索索引、统计物化数据和事件；
- 审计、错误日志和可观测性标签。

任何一个面未携带可信 TenantContext，都视为 SaaS 发布阻塞。

### 6.3 权限不变量

- 平台角色只能获得平台权限；租户角色只能获得租户权限。
- TenantMember 不能被分配 PlatformRole。
- PlatformOperator 不隐式成为 TenantMember。
- Tenant owner 也通过显式 Permission/DataRule 获权，不使用隐藏 `is_super` 绕过。
- 跨 Tenant 操作必须有明确关系或一次性授权对象，不能只靠审计字段“事后解释”。

## 7. 独立运营平台管理协议

为避免未来重构，业务应用在 SaaS 阶段只预留稳定的“实例管理协议”，不内置运营平台实现。

### 7.1 实例身份

每个部署实例拥有不可变 `instance_id`、`application_id` 和环境标识。实例首次登记通过一次性 enrollment token 换取实例专属凭据；凭据可轮换和吊销。

### 7.2 推荐通信方式

默认由实例侧 Agent/管理进程主动连接运营平台：

1. 实例定期上报最小健康、当前 Release 和迁移账本摘要；
2. 运营平台排队签名的计划任务；
3. Agent 拉取并验证目标实例、Release、有效期和签名；
4. Agent 本地执行 plan → backup → apply → verify；
5. 结果以幂等 operation id 回报。

主动出站模式更适合位于 NAT、内网或客户网络中的实例，也避免运营平台保存 SSH 密钥。确需远程入口时另行设计，不把 SSH 作为公共协议。

### 7.3 协议数据最小化

允许上报：版本、迁移编号、服务健康、容量摘要、备份时间、错误码和脱敏诊断。默认禁止上报客户业务记录、TenantMember、凭证、请求正文和数据库备份内容。

### 7.4 失败边界

- 运营平台离线：实例业务继续运行；待执行任务留在队列。
- Agent 离线：实例标记失联，不自动重装或回滚。
- 升级失败：实例按本地预案停止、回滚或等待人工处置；运营平台不得越权修改数据库。
- 签名、版本、备份或兼容门禁失败：拒绝执行并记录审计。

## 8. 发布、升级与兼容

- Core 使用标准 Composer/npm Registry 依赖，应用不长期复制包快照。
- 应用 Release 固定核心版本、应用版本、数据库迁移范围、前端制品摘要和兼容矩阵。
- Standalone 与 SaaS Host 使用同一 Release；运行模式是部署配置，不是分叉版本。
- 数据迁移先扩展、回填、双读验证，再收紧约束；不建立永久双字段兼容层。
- 运营平台只能编排已由应用 Release 声明支持的升级路径。
- 每个实例保持本地 release manifest、迁移账本、备份证明和最后一次执行结果，即使脱离运营平台也可审计。

## 9. 当前实现事实

截至 2026-08-16，Peanut Admin `v1.1.5` 的 `production-demonstrated` 结论仍是历史证据；
不可变事实以 `docs/product-status/releases/v1.1.5.json` 记录。当前源码正在收敛为
`2.0.0` fresh-only 候选，2.0 的最终运行资格不能由 1.x 证据替代：

- 应用仓已接入默认 Tenant、可信
  TenantContext、Tenant 选择/切换/撤销、PlatformOperator、Tenant 生命周期、首 owner
  和 TenantModule，不再是“完全未接入 Tenant Runtime”的单租户 Host。
- 默认 Tenant/RBAC/组织映射和 Article、字典、装修、会员、文件、通知、OAuth、任务、
  日志等多批应用 Runtime 已按 Tenant-first 或显式实例 owner 边界落地；Admin/Role/
  Dept/Jobs CRUD、同步 XLSX、会员上传和实例工具也已收紧到可信 Tenant 或部署模式边界。
  这些独立切片已经由 MT05 固定候选集中验收，不再以单个 PR 数量代替阶段证据。
- Core/Generator 公司级 MT01 基线和 Composer/npm Alpha.5 已固定；DCS 作为独立派生
  应用已获得
  Product-only `CONDITIONAL` 采用结论；Generator 仍只创建新项目，不覆盖更新已有项目。
- 1.x 的 MT02–MT06 已完成并随 `v1.1.0` 发布；`v1.1.5` 的 54 条 migration、双模式生成/
  升级、生产 Compose 和真实浏览器资格均只属于历史版本。2.0 改用 canonical Schema、
  空库安装和原生身份，PlatformOperator 与默认 Tenant owner Account 仍保持分离。
- 公众号回复等需要外部回调可信 Tenant 路由的领域尚未形成完整闭包；不得仅添加
  `tenant_id` 伪装完成隔离，也不以该非代表域阻塞当前 MT05 代表业务闭环。
- 独立运营平台尚未立项和实现，本蓝图只冻结边界与管理协议方向；它不属于当前
  稳定多租户脚手架的业务实现范围。
- SaaS roadmap 目录中的 LikeAdmin/Likeshop、旧多包和超级管理员方案均为历史输入，
  不是现行实施合同。

## 10. 历史方案取舍

| 历史输入 | 处理 |
| --- | --- |
| LikeAdmin SaaS 平台端/租户端体验 | 借鉴产品能力，不复制表结构和接口 |
| 每租户一组业务表 | 废弃为默认方案；P0 使用共享表 + 强制 `tenant_id` |
| `tenant_id=0/NULL` 表示平台 | 禁止；平台表和租户表分离 |
| 超级管理员访问全部租户业务 | 禁止；未来只允许显式 SupportSession |
| DCS `tenant` 等同组织/门店 | 废弃；按 Tenant、经营主体、成员和业务目标分层 |
| 运营平台嵌入核心 `ops-console` | 废弃；运营平台是独立应用 |
| 应用长期复制核心包快照 | 过渡状态；正式版改用 Registry 依赖 |

## 11. 决策门禁

以下决策在相应实施前必须冻结，但不阻塞本蓝图成立：

1. DCS Tenant 与经营主体的对应关系。
2. Standalone 现有管理员到 Account/TenantMember 的迁移规则。
3. 现有 42+ 张业务表的 Tenant 所有权分类和回填顺序。
4. Tenant 自定义域名、配额、计费和许可证的 P1/P2 范围。
5. 独立运营平台的产品名、仓库、商业授权模式和首批接入应用。
6. 实例 Agent 的语言/交付形态及签名协议细节。

## 12. 验收定义

SaaS 增强只有在以下结果同时成立时才算完成：

- 同一 Release 可安装为 Standalone 或 SaaS Host；
- 现有单租户业务升级后结果不变；
- 两个 Tenant 的数据、缓存、文件、任务、审计和导出互不可见；
- PlatformOperator 无法直接读取租户业务事实；
- 禁用 Tenant 后所有新会话和写入被拒绝；
- 核心包、应用 Host 和至少一个真实下游应用通过消费验收；
- 独立运营平台离线不会中断任何已部署实例的核心业务；
- 运营平台只能通过签名、幂等、可审计的管理协议操作实例；
- 不存在双 Runtime、双业务表或长期兼容字段。

## 13. 事实源

- `docs/design/saas-roadmap/`：历史路线图和详细合同输入；
- 独立 Peanut Admin Core 仓的 `docs/core-concepts/index.md`：核心现行概念；
- 独立 DCS 仓的 `docs/00-当前推进/04-主体、组织、成员、账号体系专题.md`：DCS 主体语义；
- 本文：SaaS 增强及独立运营平台边界的当前权威摘要；
- `docs/plans/saas-enhancement-development-plan.md`：本文冻结后的实施顺序与门禁。
