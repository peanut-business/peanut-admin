# PB03 核心/应用所有权与迁移门禁

> 状态：Accepted
>
> 冻结日期：2026-08-11
>
> 应用发现基线：`bc2e75ac6217d7defc44cd2b8e0c9e85a7cefc62`
>
> 核心发现基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`

## 1. 决策

PB03 采用以下边界，替代“所有标准业务都进入核心默认实现”的笼统表述：

1. 核心仓拥有产品无关、可由不同 Host 复用的基础设施、接口、DTO、错误、安全规则、扩展协议和默认技术实现。
2. 应用仓拥有 Peanut Admin 的产品实体、表结构、运营规则、页面结果、业务状态机和第三方项目配置；这些能力以应用 Module 为唯一实现，不迁入核心仓。
3. 核心的 `Membership` 表示 Tenant 管理成员，不等于应用的客户、标签、余额与充值会员域。名称相近不能作为迁移依据。
4. 核心当前没有 Peanut Admin 内容、装修、财务、支付、OAuth 或渠道产品域默认实现。上述领域复用核心原语，但仍由应用拥有。
5. Settings、Reference Codes、File Media、Task Job、Notification SMS、Import Export、Integration Security 和 Ops Console 是核心 P1 候选能力；在固定提交聚合资格与独立下游采用决策完成前，应用不得把它们当成已授权替换基线。
6. 公开安装边界始终只有 Composer `peanut-admin/core` 与 npm `@peanut-admin/admin`。内部领域目录不是独立包，不新增第三个运行时包。

因此，“唯一实现”不等于“所有实现都在核心”。每项能力必须只有一个明确 owner；应用专属能力留在应用，核心通用能力通过包消费。

## 2. 当前消费事实

| 面 | 当前 registry 版本 | 已实际消费 | 未消费 |
|---|---|---|---|
| PHP | `peanut-admin/core@0.1.0-alpha.2` | `EffectivePermissionSet`、PHP override registry | 核心 Settings、Reference Codes、File Media、Task Job、Notification SMS、Import Export、Integration Security、Ops Console 的业务 Runtime |
| 管理端 | `@peanut-admin/admin@0.1.0-alpha.3` | 权限 helper、Web override registry | Shell 与全部领域页面/用例 |
| PC | `@peanut-admin/admin@0.1.0-alpha.3` | `client`、`client/nuxt` 请求/会话/错误适配 | 管理端 UI 与领域页面 |
| UniApp | `@peanut-admin/admin@0.1.0-alpha.4` | `client`、`client/uniapp` 请求/会话/错误适配 | 管理端 UI 与领域页面 |

PHP 唯一 slot 是 `authorization.permission.service.policy@1.0.0`；应用默认实现仍是 `RegisteredAdminPermissionPolicy`。Web 唯一 slot 是 `authorization.permission.service.evaluator@1.0.0`，默认值是核心 `hasPermission`。`server/config/peanut.php` 与 `web/src/peanut.overrides.ts` 当前都没有项目覆盖项。

Alpha.4 的 npm tarball 已被 `uniapp/package-lock.json` 从 registry 解析，本地核心 `dev` 也有不可变 tag `v0.1.0-alpha.4`；但核心 `docs/status/index.md` 仍将其描述为未发布候选。这个文档冲突必须由独立核心状态任务补齐发布证据、摘要和消费记录；PB03 不据此扩大 Runtime 下游授权。

## 3. 所有权矩阵

| 能力 | 核心唯一拥有 | 应用唯一拥有 | 迁移判断 |
|---|---|---|---|
| 身份、会话、RBAC、数据权限 | 通用身份/会话、权限集合、Tenant RBAC、数据权限与 fail-closed 原语 | 单租户管理员、岗位、菜单表、LikeAdmin URI 放行语义、ThinkPHP HTTP | 当前仅消费权限原语；不得宣称认证/权限领域已迁完 |
| 设置 | 类型定义、Schema 校验、作用域解析、密钥保护与通用存储能力 | 网站/登录/渠道等 key、默认值、产品规则、`pa_config` 兼容与 HTTP/UI | 核心 P1 尚未获下游采用；先固定 Host 合同，不直接换表 |
| 字典 | Reference Codes 的通用编码不变量 | `pa_dict_type`/`pa_dict_data` 兼容、产品字典定义与页面结果 | 等价性和升级契约未证明前保留应用唯一实现 |
| 文件与素材 | 存储 provider、私有交付、安全与元数据生命周期原语 | 产品分类、现有表兼容、公开 URL 语义、云厂商凭据与 ThinkPHP 装配 | 先证明存储/URL/迁移等价，再切换 |
| 任务、导入导出、运维 | 任务状态、租约/重试、导入导出、脱敏诊断原语 | Crontab/Generator 业务语义、环境探针、应用日志与维护命令 | 分切片迁移，禁止一次替换整个系统域 |
| 通知 | 模板/发送/Outbox/任务与 provider 端口原语 | 通知 scene、业务触发条件、项目模板、渠道凭据 | 核心候选获准后才迁基础设施；产品 scene 留应用 |
| 会员与财务 | 通用 Tenant membership、事务/幂等/审计原语 | 客户、标签、余额权威字段、流水、充值退款与运营状态 | 应用 Module 唯一实现，不向核心迁业务模型 |
| 内容与装修 | 通用设置、文件、任务与 Web Host 原语 | 文章、分类、收藏/计数、搜索、移动/PC/Tabbar 装修 | 应用 Module 唯一实现 |
| 支付、OAuth、渠道 | 密钥、签名、幂等、审计与 provider 契约原语 | 订单、金额、回调结算、身份绑定、微信/支付宝流程和渠道配置 | 应用 Module 唯一实现；核心不得直接改产品余额/订单 |

## 4. 唯一实现与 Host/override 门禁

一个切片只有同时满足下列条件才可标记完成：

1. owner、非目标、数据/迁移 owner 和公开契约已写明；不能用同名目录推断等价。
2. 若使用核心能力，必须固定获准的 40 字符核心提交和已发布 registry 版本；branch、path repository、工作区映射都不是锁。
3. 核心只通过 `peanut-admin/core` 或 `@peanut-admin/admin` 的公开入口暴露 interface/DTO/error/event；应用不得 deep import。
4. PHP 覆盖只进入 `server/config/peanut.php` → `CoreServiceOverrides`；Web 覆盖只进入 `web/src/peanut.overrides.ts` → `web/src/core/runtime.ts`。新增 slot 必须有稳定 key、契约版本、类型校验、默认实现和重复/未知 key fail-closed 行为。
5. ThinkPHP Controller、请求/响应映射、数据库连接、项目配置、凭据和端适配器留在应用；核心不得依赖应用路由、表名或品牌。
6. 应用内第二条可运行规则路径必须删除；旧路由/字段只在明确升级契约中保留，不建立无限期双实现兼容层。
7. 数据迁移只由表 owner 执行。应用 DB 变更新建 `server/database/migrations/YYYYMMDD-*.sql`；不得直接修改 `init.sql`。
8. 每个验收组只跑一次；失败后按项目停止预算只做一次只读诊断。通过后立即更新计划、契约和状态。

## 5. 测试所有权

核心 Runtime ledger 的 owner 只证明核心候选行为，不替代应用 Host 验收。应用当前没有领域聚焦测试，只有 PHP 语法和 Web build，因此每个迁移切片必须先建立对应应用 owner。

| 阶段/领域 | 核心已有 owner（仅在实际消费时适用） | 应用 Host owner | 一次最低验收 |
|---|---|---|---|
| PB04 认证/权限 | `RUNTIME-TENANT-AUTH-001`、`RUNTIME-TENANT-ADMIN-001`、`RUNTIME-DATA-AUTHORIZATION-001` | `PB04-AUTH-HOST-001`（Host 策略已通过；账户 CRUD 待后续切片） | non-root 已登记拒绝/未登记放行；root；菜单/按钮一致 |
| PB04 网站设置 | `RUNTIME-SETTINGS-001` | `PB04-SETTINGS-WEBSITE-001` | 读取、合法保存、非法输入不写、恢复原值 |
| PB04 字典 | `RUNTIME-REFERENCE-CODES-001` | `PB04-REFERENCE-CODES-HOST-001` | 列表、创建/编辑约束、被引用或状态边界 |
| PB04 文件 | `RUNTIME-FILE-MEDIA-001` | `PB04-FILE-MEDIA-HOST-001` | 上传、私有/公开 URL 结果、删除/归档边界 |
| PB04 任务/导入导出 | `RUNTIME-TASK-JOB-001`、`RUNTIME-IMPORT-EXPORT-001` | `PB04-TASK-OPS-HOST-001` | 一条任务成功、一条失败/重试、一次导出 |
| PB04 日志/维护 | `RUNTIME-OPS-CONSOLE-001` | `PB04-OPS-HOST-001` | 权限拒绝、脱敏日志、一个只读维护探针 |
| PB05 会员/财务 | 核心事务/幂等/审计原语的任务 owner；不得借用 Tenant membership owner | `PB05-MEMBER-FINANCE-001` | 余额权威字段、流水原子性、重复入账拒绝 |
| PB06 内容/装修 | 无产品域 core owner | `PB06-CONTENT-DECORATION-001` | 发布/下架、分类、三端一个装修结果 |
| PB07 通知 | `RUNTIME-NOTIFICATION-SMS-001` | `PB07-NOTIFICATION-HOST-001` | 模板快照、发送状态、失败/重试与脱敏 |
| PB07 支付/OAuth/渠道 | `RUNTIME-INTEGRATION-SECURITY-001` 加通用原语 owner | `PB07-PAYMENT-OAUTH-001` | 回调验签、金额/订单一致、幂等、绑定冲突 |

应用 owner 的证据路径和执行命令必须在每个实施契约中创建并固定；PB03 只分配责任，不伪造尚不存在的通过证据。

## 6. 逐领域任务队列

每项 Runtime 工作开始前必须有独立契约，包含：精确前置提交、目标/非目标、精确文件白名单与禁改集、schema/migration owner、API 与 audience、权限/数据策略、安全/审计/脱敏、事务/并发/幂等、错误契约、核心和应用测试 owner、一次聚焦验收、延期里程碑验收、单提交和资格停止线。

| 顺序 | 任务 | 所有权结果 | 启动条件 | 停止线 |
|---|---|---|---|---|
| PB04-01 | 认证/权限 Host 收口 | 核心原语 + 应用管理员模型 | 固定现有 URI/菜单语义与覆盖 slot | 不迁 Tenant schema，不重做 parity |
| PB04-02 | 网站设置 | 应用唯一服务 + `pa_config` adapter | PB03 应用 owner 决策 | 不改核心 Runtime；不迁支付/渠道设置 |
| PB04-03 | 字典 | 核心编码不变量 + 应用兼容/定义 | 表/状态/引用语义映射完成 | 不并行修改内容分类 |
| PB04-04 | 文件与素材 | 核心存储/交付原语 + 应用分类/Provider | URL、元数据、存储升级合同完成 | 不顺带迁装修素材 |
| PB04-05 | 任务/导入导出 | 核心任务原语 + 应用执行器 | 任务状态/租约/重试映射完成 | 不改会员/通知触发器 |
| PB04-06 | 日志/维护 | 核心脱敏/运维原语 + 应用探针 | 权限与脱敏合同完成 | 只读探针先行，不扩大为运维平台重构 |
| PB05 | 会员与财务 | 应用 Module | PB04 通过；余额与事务 owner 冻结 | 不向核心迁客户/余额模型 |
| PB06 | 内容与装修 | 应用 Module | PB04 设置/文件通过 | 不复制三端业务状态机 |
| PB07-01 | 通知基础设施 | 核心候选 + 应用 scene/provider | 核心固定候选获准 | scene/触发条件不进核心 |
| PB07-02 | 支付/OAuth/渠道 | 应用 Module + 核心安全原语 | PB05 余额、PB07-01 通知稳定 | 核心不得写产品订单/余额 |

## 7. PB04-02 网站设置首片冻结合同

PB04 从网站基础设置开始；该首片已按本节更新后的应用 owner 路线完成，其他 PB04 切片尚未开始。

### 7.1 现有路径与数据 owner

- HTTP Host：`server/app/adminapi/controller/config/ConfigController.php` 和 `server/route/app.php`。
- 产品校验/映射：`WebsiteValidate` 与 `ConfigLogic::getWebsite/saveWebsite`。
- 现有存储：`ConfigService`、`Config` model、真实表 `pa_config`，唯一键为 `(type, name)`；网站组 `type=website`。
- 文件 URL 映射继续通过应用 `FileService`，不属于通用 Settings 存储。
- 管理页、路由和品牌字段仍由应用拥有。

### 7.2 核心差异与已选路线

核心 `SettingAdminService`、`SettingResolver` 和 `TargetSettingWriter` 直接依赖 `final PdoSettingRepository`，其 schema 是 `pa_setting_*`、Tenant/target/revision/secret 模型；它不是 `pa_config` 的可替换存储端口。核心 P1-B03 也明确应用拥有 key/schema/default，且当前没有下游采用授权。

限定静态枚举证明这不是一个可由小型 repository interface 解决的差异：核心服务共同依赖定义同步、revision/ETag、平台操作员、Tenant/target 和 P1 audience。为了单租户网站设置抽象该边界会同时改变核心安全、并发和受众语义。

PB04 已选择应用 owner 路线：不改核心 Runtime、不双写 `pa_setting_*`，在应用内以 `WebsiteConfigService` + `WebsiteConfigStore` + `PaConfigWebsiteStore` 收口唯一实现。未来若出现完整等价 schema 和独立下游授权，再以新的 P1 合同评估核心 Settings 消费。

### 7.3 首片最小验收与恢复

实施合同已固定网站字段白名单、空值/长度/图片 URL 规则和原子写错误；该端口属于应用内部，不建立核心 override key。应用侧已执行一次：读取当前值，合法保存一组临时值，证明一次非法输入没有写入，再恢复并核对原值。未同时修改支付、渠道、登录、版权、协议或默认头像设置，也未运行 LikeAdmin 全量回归。

## 8. 后续产品化收尾门禁

PB03–PB07 完成后、PB09 前必须执行独立的脚手架产品化与官方网站门禁。输入包括当前已发现的 UniApp `pages.json`、PC/UniApp fallback 小写 `peanut`、固定 `/static/logo.png` 和“感谢使用本产品”等泛化文案；本阶段只登记，不修改。

该门禁须先用 `terra_researcher` 做有来源的成熟开源后台官网精简对比，再统一四端、安装种子、包元数据、README 与文档站的品牌事实源；将 docs-site 建成官网与文档分区门户；最后只做一次桌面/移动真实浏览器验收。完成并同步用户手册、开发、部署与升级文档后，PB09 才可开始。
