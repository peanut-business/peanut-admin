# Peanut Admin 产品化正式基线计划

> 状态：执行中
>
> 更新日期：2026-08-11
>
> 分支策略：功能分支 → `dev`；阶段验收通过后 `dev` → `main`

## 1. 完成定义

产品化正式基线完成时，应同时满足：

1. LikeAdmin 1.9.4 已验收的业务能力、规则、权限语义、状态流转和用户结果保持不变；不重做已封存的 parity 验收。
2. 管理端统一使用 Element Plus；PC 使用 Nuxt 3 + Element Plus；UniApp 保持跨端组件体系。
3. 运行时公开依赖只保留 `peanut-admin/core` 与 `@peanut-admin/admin` 两个包。
4. 产品无关且已获下游采用授权的规则、用例、DTO、安全原语和扩展契约由核心包拥有；Peanut Admin 的会员/余额、内容/装修、支付/OAuth 等产品领域由应用 Module 唯一拥有。两侧均不得保留第二套可运行实现。
5. 应用后端只保留 ThinkPHP HTTP 装配、应用配置、数据库连接、应用专属模块和显式覆盖；应用前端只保留启动、品牌主题、项目路由装配、端适配器和显式覆盖。
6. 覆盖通过稳定 key/interface 和版本约束注册，禁止修改 `vendor/`、`node_modules/`、复制核心源码或增加双字段、双路由、双实现兼容层。
7. 生产 Docker 能连接局域网 MySQL，首次空库安装、已有库前滚升级、三端静态入口、管理端登录和核心业务页通过一次最低充分验收。
8. 独立文档站、开发指南、发布部署、升级说明和用户手册与实际版本一致。

## 2. 核心仓与应用仓边界

`peanut-opensource/peanut-admin` 是可复用基础设施与公开契约的实现和发布源；`peanut-business/peanut-admin` 是产品领域与可运行 Host 的实现源。边界以 `docs/architecture/pb03-ownership-and-migration-gates.md` 为准：

- 核心包拥有：身份/会话、权限、数据权限、设置、文件、任务、通知、导入导出、集成安全和运维等产品无关基础设施、公共契约及已批准的默认技术实现。
- 应用拥有：管理员与 LikeAdmin 兼容语义、客户/会员/余额、内容/装修、支付/OAuth/渠道等产品实体与流程，以及 HTTP 装配、品牌配置、第三方凭据、端特有 UI/导航和覆盖实现。
- 核心源码可以按领域目录组织，但仍只发布一个 Composer 包和一个 npm 包；目录不是独立发布单元。
- 核心仓已有的多租户基础设施继续作为后续 SaaS 底座；单租户正式基线不得伪装成已实现 SaaS。

核心通用能力的迁移采用“先形成获批任务合同和固定候选资格，再发布 registry 版本，最后切换应用消费并删除重复实现”的顺序。应用产品域不向核心迁模型，只收口为应用 Module 的唯一实现并复用核心原语。任何删除前都必须有 registry 消费验证和应用侧最低业务验收。

## 3. 阶段队列

| ID | 阶段 | 状态 | 最低充分门禁 |
|---|---|---|---|
| PB00 | LikeAdmin parity 与文档封存 | 已完成 | `output/playwright/v02/` 独立证据；禁止重复 |
| PB01 | 三端 Docker、生产 MySQL、文档站和域名基线 | 已完成 | 镜像构建、迁移账本、容器健康、发布域名登录/文章页、PC/H5 和文档通过 |
| PB02 | 两包发布、标准覆盖 Host、三端 client、Element Plus | 已完成 | registry 锁定、CI、真实 Chromium 代表域通过 |
| PB03 | 核心/应用所有权图谱与迁移门禁 | 已完成 | `pb03-ownership-and-migration-gates.md` 固定两仓所有权、唯一实现、Host/override、领域顺序和测试 owner |
| PB04 | 系统基础域收口 | 已完成 | 网站设置、权限/RBAC、字典、文件、任务/XLSX、日志/维护均冻结应用唯一 Runtime、核心候选停止线与测试 owner |
| PB05 | 会员与财务域收口 | 已完成 | `user_money` 权威字段、唯一余额/流水 writer、充值回调防重和退款单次扣款由应用 Host 与测试 owner 固定 |
| PB06 | 内容与装修域收口 | 已完成 | 应用 Module 唯一拥有文章、分类、素材引用与移动/PC/Tabbar 装修；四端共用一个读取 DTO |
| PB07 | 通知、渠道、支付与 OAuth 域收口 | 已完成 | 四个验证码 scene、支付状态机、OAuth 身份绑定、固定回跳和外部渠道均由应用唯一 Host 与测试 owner 固定 |
| PB08A | 脚手架产品化与官方网站 | 待开始 | 四端/安装/元数据/文档品牌单一事实源；中性脚手架；官网+文档门户；桌面/移动一次验收 |
| PB08B | 正式候选集成验收 | 待开始 | 空库、升级、覆盖、registry 安装、Docker、真实浏览器和文档一致 |
| PB09 | 发布正式基线 | 待开始 | `dev` 合入并推送 `main`；版本与发布记录完整 |
| SAAS01 | SaaS 多租户实现 | 后续独立阶段 | PB09 后按 `docs/design/saas-roadmap/` 重新冻结执行契约 |

## 4. 领域迁移工作流

每个领域只执行一次以下流程：

1. 用 CodeGraph 或限定范围的静态图谱对比两仓实体、规则、权限、状态与调用链。
2. 先判断 owner：核心通用能力或应用产品 Module；固定 Host 边界、覆盖 key、迁移/升级责任和最小验收。
3. 只有核心 owner 的能力才在核心仓按获批 P0/P1 合同实现、资格审查并发布新的 alpha 候选版本；产品实体/流程不得借迁移进入核心。
4. 应用从公开 registry 安装已获下游采用授权的版本，切换 Host 装配并删除重复实现；应用 owner 的能力则在应用 Module 内收口唯一实现。
5. 只做该领域一次 API/数据库或真实浏览器最低业务验收；不得重复 LikeAdmin 全量对比。
6. 更新本计划、发布状态和对应开发/使用文档。

网站设置首片的真实存储表是 `pa_config`，不是 `pa_system_config`。限定静态枚举证明核心现有 Settings 同时绑定 `pa_setting_*`、revision/ETag、平台操作员和 Tenant/target 语义，不是小型存储端口。本片已按应用 owner 路线以 `WebsiteConfigService` 收口唯一实现，不修改核心 Runtime、不双写两套表；`PB04-SETTINGS-WEBSITE-001` 聚焦测试和一次可恢复数据库验收通过。

管理员/RBAC CRUD 继续由应用唯一拥有，合同见 `docs/architecture/pb04-admin-rbac-crud-contract.md`。本片补齐 `dept/status`、`menu/status` 对编辑权限的固定 alias，并将菜单层级、角色引用和删除边界收进事务；`PB04-AUTH-CRUD-001` 一次可恢复数据库验收通过。它不授权核心 Tenant Runtime 消费，也不重复既有权限 Host 或 LikeAdmin parity 验收。

字典合同见 `docs/architecture/pb04-reference-codes-host-contract.md`。核心 Reference Codes 的 Tenant 三表、不可变 code、版本追加、ETag/幂等 API 与应用 `pa_dict_type/pa_dict_data` 不等价，且没有 Peanut Admin 下游采用授权；本片保留应用唯一 Runtime、不双写。`PB04-REFERENCE-CODES-HOST-001` 只读绑定已封存 T01 行为证据并核对当前唯一链，一次通过，未重复数据库/API/浏览器验收。

文件素材合同见 `docs/architecture/pb04-file-media-host-contract.md`。核心 File And Media 是 Tenant-private、archive/delivery 模型，既不等价于应用公开素材/分类/云 Provider，也没有下游采用授权；本片保留应用唯一 Runtime。`pa_file.storage` 现作为 URL 与删除共同的 Provider provenance，旧云素材不再随默认引擎切换而误拼域名；`PB04-FILE-MEDIA-HOST-001` 一次无外部写入验收通过，并复用封存 M02/S01 证据。

任务与导入导出合同见 `docs/architecture/pb04-task-import-export-host-contract.md`。应用 Crontab 是同步 console 调度、Generator import 是数据库元数据快照、当前业务导出是同步 XLSX；它们与核心 Tenant Task/Job、私有 CSV Import/Export 的 schema、租约/重试和文件语义不等价，且没有下游采用授权。本片保留应用唯一 Runtime，将五个 XLSX 调用方收口到 `XlsxExportService`；`PB04-TASK-OPS-HOST-001` 一次通过任务成功、失败后人工重试、XLSX 生成和零夹具清理。

日志与维护合同见 `docs/architecture/pb04-ops-host-contract.md`。核心 Ops Console 是 platform audience 的结构化运行日志、维护窗口和备份/恢复候选，不等价于应用 `pa_operation_log` 与环境页，且没有下游采用授权。本片以 `OperationLogService` 收口唯一写入和实际密钥/证书/验证码脱敏；清理旧日志必须原子保留清理审计，`system/info` 目录检查改为无文件写入的只读探针并对齐 PHP 8.3。`PB04-OPS-HOST-001` 一次无副作用验收通过，PB04 至此完成。

会员与财务合同见 `docs/architecture/pb05-member-finance-host-contract.md`。核心 Tenant membership 不是客户会员，R01/R02 事务/幂等/审计候选也没有 Peanut Admin 下游采用授权；会员、标签、余额、流水、充值与退款继续由应用 Module 唯一拥有。本片以 `MemberBalanceService` 收口 `user_money` 权威余额、`balance` 兼容镜像、累计充值和分类流水写入，三条变动路径继续在各自领域事务内装配。`PB05-MEMBER-FINANCE-001` 一次无数据库验收绑定封存 S01/F02 业务证据并证明重复回调/退款不重复入账，PB05 至此完成。

内容与装修合同见 `docs/architecture/pb06-content-decoration-host-contract.md`。核心没有产品内容/装修 Runtime，文章、分类、收藏/计数、搜索和移动/PC/Tabbar 装修继续由应用 Module 唯一拥有。本片以 `ProductAssetReferenceService` 保留新资源的 local 相对 URI 或云/CDN 绝对 provenance，以 `DecorationReadService` 删除管理端重复 formatter 并统一 API、PC 与 UniApp/H5 读取 DTO；文章分类存在性与占用删除边界同步固定。`PB06-CONTENT-DECORATION-001` 一次无数据库验收绑定封存 C01/C02/DE01-DE02 证据并证明三端即时消费，PB06 至此完成，下一阶段为 PB07。

通知合同见 `docs/architecture/pb07-notification-host-contract.md`。核心 `NotificationSms` 虽已随 Composer Alpha.2 发布，但没有 Peanut Admin 下游采用授权，且 Tenant message/outbox 语义不等价于产品四个验证码场景；本片不升级依赖、不 deep import、不修改核心。应用 `NoticeChannelService` 唯一拥有阿里云/腾讯云配置、单默认 Provider、原子切换、驱动选择和脱敏回执；验证码只保存慢哈希与 `****` 快照，验证固定最近成功记录且不回退旧码。无消费者的通用模板/SMTP Runtime 已退出，历史 `pa_notice_template` 数据保留。`PB07-NOTIFICATION-HOST-001`、PHP lint 与 Web typecheck 一次通过；通知切片完成，PB07 下一片为支付、OAuth 与外部渠道。

支付合同见 `docs/architecture/pb07-payment-host-contract.md`。核心没有产品支付 Runtime，Integration Security 的 Tenant 机器身份/Webhook/会话能力也不等价且未获应用采用授权；充值订单、商户凭据、预支付、渠道回调、结算和退款继续由应用 Payment/Finance Module 唯一拥有。本片将退款 gateway 纳入 `PaymentServiceFactory` 和可注入 transport，删除旧静态退款签名/HTTP 路径与重复 Web facade；微信预支付/退款响应强制平台证书验签，支付宝退款响应继续 RSA2 验签，持久化仅保留白名单回执。`PB07-PAYMENT-HOST-001` 绑定封存 S01/F02 并以纯内存证书证明响应篡改拒绝；支付切片完成，PB07 下一片为 OAuth 与外部渠道。

OAuth 与渠道合同见 `docs/architecture/pb07-oauth-channel-host-contract.md`。应用 `OAuthLogic → OAuthTransportInterface → WechatOAuthTransport` 是会员 OAuth 与身份表唯一 Runtime，`mnp_setting/oa_setting/open_platform` 是微信渠道唯一配置；核心 Integration Security 不等价且没有采用授权。本片以 `OAuthBrowserCallbackService` 固定 PC 与公众号 API bridge，再分别回到 `/pc/` 和 `/mobile/` 客户端；UniApp completion ticket 改为读后即删的临时端内状态，不进入 URL。旧 Channel CRUD/Web facade、重复微信/QQ 凭据及未实现的公众号 AES 配置入口退出，精确迁移清理敏感旧行。`PB07-OAUTH-CHANNEL-HOST-001` 绑定封存 S01/CH02/CH03，明确真实微信凭据与平台登记未验证；PB07 至此完成，下一阶段为 PB08A。

## 5. PB09 前脚手架与官网门禁

PB03–PB07 完成后先执行 PB08A：

1. 核对管理端、PC、UniApp/H5、后端默认配置、安装种子、包元数据、README 和文档站的 logo、favicon、名称、标题、slogan、默认图片、版权、链接与示例数据。
2. 品牌配置收敛为单一权威入口并可覆盖；fresh clone/空库安装即有完整中性默认品牌，不修改依赖目录或复制核心实现。
3. docs-site 提升为 Peanut Admin 官方网站与文档门户，覆盖产品首页、能力/场景、快速开始、开发指南、部署升级、API/扩展、管理员手册、版本/发布与 GitHub 入口。
4. 调研阶段由 `terra_researcher` 做有来源的成熟开源后台官网精简对比，只吸收信息架构、交付完整性和可维护方式。
5. 只做一次桌面/移动真实浏览器验收：导航、关键 CTA、搜索/链接、404、四端默认品牌和登录页。

已登记的输入包括 UniApp `pages.json`、PC/UniApp fallback 中的小写 `peanut`、固定 `/static/logo.png` 和泛化“感谢使用本产品”文案；PB03 不修改这些文件。PB08A 与 PB08B 都通过并同步用户手册、开发、部署和升级文档后，才能进入 PB09。

## 6. 并行规则

- 只读图谱、互不依赖的前后端契约和文档核对可以并行。
- 核心包公共接口、同一领域迁移、应用装配、锁文件、数据库迁移和发布版本串行处理。
- 子智能体完成后必须把结果、文件、一次验证和限制汇总回主线程；不能只留后台状态。
- 验收达到门禁立即停止，不扩大为全仓重构或重复回归。
