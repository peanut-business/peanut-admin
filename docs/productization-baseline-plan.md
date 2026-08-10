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
| PB08A | 脚手架产品化与官方网站 | 完成（浏览器证据并入 PB08B） | 四端/安装/元数据/文档品牌单一事实源；中性脚手架；官网+文档门户；静态门禁通过 |
| PB08B | 正式候选集成验收 | 已完成 | 空库、升级、覆盖、registry 安装、Docker、真实浏览器和文档一致均通过 |
| PB09 | 发布正式基线 | 待开始（许可证门禁未决） | 明确许可证/provenance、`LICENSE`、`NOTICE`、第三方清单后，`dev` 合入并推送 `main`；版本与发布记录完整 |
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

PB08A 执行合同见 `docs/architecture/pb08a-brand-scaffold-official-site-contract.md`。当前已冻结：继续扩展应用 `WebsiteConfigService + pa_config(type=website)` 作为唯一可变 Runtime；仓库 bootstrap manifest 只拥有安装前/静态构建默认值；空库安装必须显式提供合格的 `ADMIN_INITIAL_PASSWORD` 且不得回显；品牌 Runtime、安装、四端消费、官网依次串行实施。官网调研已记录 Vben Admin、Ant Design Pro、Arco Design Pro、SoybeanAdmin 与 Pure Admin 的官方来源，只吸收信息架构和交付完整性。PB08A 不另跑浏览器，最终桌面/移动 Chromium 与 PB08B 合并为唯一一次验收。

PB08A 品牌 Runtime、安装安全和四端消费现已完成：bootstrap manifest/源资产与生成检查、16 字段网站配置、用途化默认图片、管理端/PC/UniApp-H5 消费、包元数据和显式初始管理员密码均已落地；旧 ThinkPHP/Uni preset/AUX、固定 logo、固定密码和小写产品 fallback 已退出运行路径。应用根许可证仍缺失，包元数据暂按 `proprietary/UNLICENSED`，PB09 前必须取得 provenance/clean-room、LICENSE/NOTICE 与第三方清单的明确决策。该阶段未提前执行 PB08B 浏览器或 PB09 发布。

PB08A 官网与文档门户现已完成：产品首页、能力/场景、快速开始、开发、部署升级、API/扩展、管理员手册、版本信息、GitHub、搜索和 404 均进入同一 VitePress 站点；站点消费生成的品牌 manifest/资产，公开文档不再固化历史验收域名、服务器 IP、局域网数据库或过期 PHP 版本。用户、开发、部署/升级文档已同步。`PB08A-OFFICIAL-SITE-001` 唯一静态门禁通过品牌生成检查、VitePress 构建/内部链接、搜索索引、关键页面和 404 产物检查；未运行浏览器，桌面/移动唯一一次真实 Chromium 验收仍归 PB08B。PB08A 至此完成，下一阶段仅为 PB08B。

PB08B 执行合同见 `docs/architecture/pb08b-release-candidate-acceptance-contract.md`。当前已冻结：候选从干净提交导出；生产无缓存 Docker 构建同时证明两包 registry 安装和三端产物；实时数据库只做弱密码零写入、当前 28 条迁移空库、从 `bc2e75ac…` 的 24→28 前滚及品牌/管理员保留；领域/覆盖行为绑定封存证据，不重跑；官网、管理端、PC、H5 只执行一次桌面/移动 Chromium。证据只写脱敏摘要并在结束后删除专用容器、网络、volume 与临时目录。

PB08B RC001 的唯一无缓存 registry/生产构建通过，弱密码零写入与 24 条账本基线安装也成功；首版 shell 在哨兵准备阶段因预期失败 trap 和粗粒度 stage 无法定位断言，清理后不足以判定候选。一次只读诊断排除旧库 salt 字段宽度问题。当前 owner 更新为 RC002：继承同一候选 RC01 结果，不重复无缓存构建，只从验证 cache 重建镜像并用逐断言 stage 完成尚未通过的数据库、Docker 与唯一浏览器矩阵。

RC002 精确证明旧 24 条基线不存在 `website/name`，所以使用该新字段作为“已有品牌”哨兵的前提错误；缓存重建、弱密码零写入和基线安装均通过，候选仍未执行升级。当前 owner 更新为 RC003：改用旧基线确定存在的 `website/pc_title` 作为保留哨兵，升级后另由公开配置 API 证明完整规范 DTO；继续禁止重复 RC01 registry 构建。

RC003 已越过弱密码、24 条基线、`pc_title`/管理员哨兵、24→28 前滚、无初始密码跳过安装、幂等与升级保留断言；生产 Compose 的 MySQL/PHP 健康，但 Nginx healthcheck 在运行环境代理注入下把 loopback `wget` 送往 `127.0.0.1:7890`，即使宿主机 `/healthz` 已返回 200 仍判 unhealthy。一次只读诊断后候选 `0459494…` 判失败并清理资源，浏览器未启动。下一步只最小修复 healthcheck 显式直连 loopback，冻结新候选/owner 后继续 PB08B。

候选 `cb214d7…` 已将 Nginx healthcheck 收口为 `wget -Y off` 的 loopback 直连，但 RC004 的 fresh bundled-db 启动暴露第二个生产编排缺陷：PHP 未等待已启用的 MySQL healthy，安装入口在数据库监听前连续退出，虽然 restart 最终恢复，Compose 启动门禁已失败且 Nginx/cron 未启动。一次只读诊断后 RC004 判失败并清理专用容器、网络、volume 与临时候选目录，浏览器未启动。

新候选 `61d9fb7…` 只为 PHP 增加 `mysql` 的可选 `service_healthy` 依赖：启用 `bundled-db` 时等待内置数据库，外部数据库模式仍不要求 MySQL service；两种 Compose 解析均通过，其他 Runtime、锁文件、数据库与四端源码未变。当前 owner 为 RC005，继续绑定先前唯一 registry 构建与已通过升级证据，只重建受影响的生产装配并完成当前空库、HTTP/镜像、静态边界、唯一桌面/移动浏览器和文档一致性。

RC005 已证明 bundled-db 无重启启动，MySQL/PHP/Nginx 全部 healthy，当前空库得到 43 表、28 条 applied/0 异常账本、唯一 root admin、167 菜单、62 配置与 16/16 品牌值；四个 HTTP 入口、品牌 API、镜像静态边界和两包/Host/override 边界均通过。唯一浏览器任务中的官网导航、CTA、搜索与 GitHub href 通过，但未知路径渲染 VitePress 默认英文 404，而不是仓库已有品牌页面；任务立即停止，未执行管理端/PC/H5，RC005 判失败并清理全部专用资源。

新候选 `aa5349a…` 只把品牌 404 注册为 VitePress 主题级 `NotFound`；根级 `404.md` 仍保留正常页面模块，不再被误当作未知路由接管机制。官网静态构建通过，自定义中文标题与文档入口进入主题 bundle；生产 Runtime、Docker、数据库、锁文件和四端源码与 RC005 相同。当前 owner 为 RC006，继承 RC005 空库、Compose、HTTP、镜像和边界结果，只执行一次完整桌面/移动 Chromium 与 RC06 文档一致性。

RC006 的唯一浏览器仍在未知路由看到默认英文 404，并立即停止。一次只读诊断确认 VitePress 1.6 默认 Layout 的 `VPContent` 自行渲染内置 NotFound，顶层 `Theme.NotFound` 已弃用且不会覆盖该 slot；RC006 判失败并清理浏览器、截图、容器、网络、volume 与临时候选目录，管理端/PC/H5 未执行。

新候选 `c93445f…` 只改为包装默认 Layout 并向 `not-found` slot 注入既有品牌组件，这是 VitePress 1.6 类型与默认主题实现指定的接管路径；静态构建和主题 bundle 检查通过。当前 owner 为 RC007，继续继承 RC005 非官网结果，只运行一次完整桌面/移动 Chromium 与 RC06 文档一致性。

RC007 的唯一 Chromium 已完整通过：桌面官网导航/CTA/搜索/GitHub/品牌 404、移动折叠导航/快速开始/文档/品牌 404、管理端随机初始密码登录与文章只读页、PC 资讯页、H5 关于页默认品牌均成立，应用请求全部 200，唯一 404 为合同指定的未知官网路由。随后 RC06 文档一致性发现候选 README 仍记录历史 24 条 migrations，而当前事实为 28；README 与官网快速开始还要求已有库手工逐文件执行 SQL，绕开 `migrate.php` 账本与 SHA-256 校验。一次只读诊断后 RC007 判失败，未生成通过摘要；专用浏览器、文档预览、容器、网络、volume、截图和临时候选目录均已清理。下一步只能先做这两处最小文档修正并冻结 RC008，不重跑 RC007 已通过的浏览器或更早门禁。

新候选 `4442229…` 只把 README 与官网快速开始统一为 28 条 migration 和 `migrate.php` 账本升级路径，并移除 README 的本机绝对路径与未决 clean-room 断言；VitePress 构建通过，Runtime、锁文件、Docker、品牌源和 404 实现未变。当前 owner 为 RC008，只封存 RC06 文档一致性与总摘要，继承 RC007 浏览器和全部更早门禁。

RC008 文档一致性通过，`output/playwright/pb08b/summary.json` 已汇总唯一 registry/无缓存构建、弱凭据零写入、24→28 前滚、当前空库、Compose/HTTP/镜像/Host 边界、RC007 桌面/移动 Chromium 与 RC008 文档门禁；所有专用运行资源已清理，关键截图来自未重跑的 RC007 同一浏览器任务。PB08B 至此完成。应用许可证/provenance、根 `LICENSE`、`NOTICE` 与第三方清单仍是 PB09 前独立决策门禁；未获用户明确决策前不开始 PB09。

## 6. 并行规则

- 只读图谱、互不依赖的前后端契约和文档核对可以并行。
- 核心包公共接口、同一领域迁移、应用装配、锁文件、数据库迁移和发布版本串行处理。
- 子智能体完成后必须把结果、文件、一次验证和限制汇总回主线程；不能只留后台状态。
- 验收达到门禁立即停止，不扩大为全仓重构或重复回归。
