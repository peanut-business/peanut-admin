# Peanut Admin Core 能力与独立应用采用全景

> 本页回答两个问题：Core 现在能做什么，以及独立 `peanut-admin` 应用实际上用了什么。它是静态源码审计，不是生产运行、完整资格或发布完成证明。

## 先看四条真实调用链

Core 既不是几个工具函数，也不是安装后自动得到完整后台的成品应用。它提供后端服务、合同、Schema、PDO 实现和前端运行时；独立应用仍负责 ThinkPHP/Vue 宿主、路由、可信身份、业务数据和产品生命周期。当前最容易理解的采用例子是：

1. **登录与权限。** 应用组合根把 Core 的 `TenantAuthService`、`PdoTenantAuthRepository`、`TenantAuthEndpoint`、事务管理和授权服务装入 ThinkPHP 容器；请求中间件建立可信 Tenant 上下文后，应用业务继续使用自己的 Model、Scope 和权限入口。见 [`AppService::registerAuthentication()` 与 `registerAuthorization()`](../../server/app/AppService.php#L156)。
2. **导入导出。** 官方 ImportExport Module 组合 Core Settings、TaskJob 和 ImportExport 机制，再注入应用的文件媒体网关、配置迁移规则与操作日志。Core 管通用任务和 CSV 流程，应用拥有具体格式、业务权限、文件账本及 Module 数据。见 [`ImportExport\ModuleProvider`](../../server/app/Modules/Official/ImportExport/ModuleProvider.php) 和 [`CoreSettingsConfigurationAdapter`](../../server/app/Modules/Official/ImportExport/Infrastructure/Configuration/CoreSettingsConfigurationAdapter.php#L20)。
3. **平台运维控制台。** 后端由应用的 [`PlatformOpsRuntimeFactory`](../../server/app/platform/service/ops/PlatformOpsRuntimeFactory.php) 提供备份、恢复、日志、权限和状态适配；平台前端通过 [`@peanut-admin/admin/ops-console`](../../platform/src/App.vue#L37) 建立运行时。Core 给出控制台合同和 UI 机制，真正的运维动作与数据仍由应用实现。
4. **PC 与 UniApp 客户端。** PC 用 [`@peanut-admin/admin/client/nuxt`](../../pc/composables/useRequest.ts#L1)，UniApp 用 [`@peanut-admin/admin/client/uniapp`](../../uniapp/src/utils/request.ts#L1)。Core 统一请求、会话和错误形状，两个宿主各自注入 base URL、平台 transport、session、decoder 与 hooks。

这些链路说明 Core 的主要价值是稳定的公共机制和组合接口；“应用采用”必须以真实组合根、路由、运行时或调用者为证据，不能只看依赖声明、同名目录或测试文件。

## Core 的组成和发布载体

本页把三个角色严格分开：

- **可分发 Core**：PHP 聚合包 `peanut-admin/core` 和 Web 聚合包 `@peanut-admin/admin`。它们是独立应用能锁定并消费的两个发布载体。
- **Core 内部能力域**：聚合包内部按 namespace 或 export subpath 划分的服务、合同、Schema、PDO 实现和 UI 运行时。内部有多个域，不等于每个域都是独立发布包。
- **Core 参考宿主**：Core 仓的 `backend/`、`frontend/`、`starter/`、`examples/` 和工程脚本。它们展示如何装配、验证和生成，不能当作独立应用已经获得的路由、页面或业务数据。

Core 当前开发基线是 [`9358686fee873dd235489c8794abf556fd70ec4f`](https://github.com/peanut-opensource/peanut-admin-core/commit/9358686fee873dd235489c8794abf556fd70ec4f)。PHP 聚合包要求 PHP 8.3、PDO、JSON、OpenSSL、Sodium、Fileinfo 与 `opis/json-schema`；宿主仍需提供框架容器、HTTP 路由、数据库连接、provider/secret、Module 启用状态和环境配置。Web 聚合包同样要求宿主提供 transport、router、Pinia 和实际 API。

### PHP Kernel 的公共后台底座

Kernel 是公共后台运行底座，不只是基础 DTO。当前开发基线在 `packages/php/kernel/src` 有 22 个一级子域：

| 中文用途 | Core 入口和能力 | 宿主责任 |
| --- | --- | --- |
| API 合同 | Problem Details、request id、过滤白名单、typed target、OpenAPI handler contract | 把框架 request/response 与路由映射到合同 |
| 认证 | Tenant/平台登录、token/refresh、Tenant Client 注册与选择 | token secret、cookie、session store、PDO 仓储和 client 登记 |
| HTTP 边界 | 认证 endpoint/response、refresh cookie、权限 middleware | ThinkPHP 等框架的 route/middleware 适配 |
| 执行上下文 | Tenant、平台、系统和授权操作上下文 | 从认证态建立可信上下文，拒绝客户端任意 Tenant id |
| 租户运行 | availability、scope、workspace query、cache/lock namespace、计划任务上下文 | 入口绑定、部署模式和实际 cache/lock backend |
| 持久化 | PDO 事务/仓储、Tenant column scope、Kernel Schema | PDO 配置、迁移和显式持久化模式 |
| 功能权限 | RBAC、角色管理、权限目录同步、revision cache、数据权限桥 | 装配目录和仓储，并在业务动作前执行授权 |
| 审计 | 按 audience 写入和查询 audit event | 业务 use case 决定何时写，宿主映射 HTTP |
| 身份 | Account/Credential、邮箱、密码 hash、自助服务 | 密码政策、credential repository 和 endpoint |
| 成员与组织 | TenantMember 生命周期、部门 CRUD | 组合 Tenant、权限、ETag、审计与 HTTP |
| 平台控制面 | Tenant、operator、平台 RBAC、workspace、owner bootstrap | 平台认证、operator repository 和安装输入 |
| Module 治理 | manifest 校验、依赖/版本、边界、provider bindings、Tenant 启停/配置/迁移状态 | Module roots、namespace、容器、实际迁移和业务表 owner |
| 迁移治理 | owned migration/table registry、Module schema/ledger | CLI/升级流程执行迁移并保存运维证据 |
| 外部操作宿主 | trusted context→Module→权限→target→事务/outbox/problem | 提供全部 adapter、operation 定义、事务与 HTTP handler |
| 幂等 | 请求 hash/key/record、PDO repository、Schema | use case 选择 key 并纳入自己的事务 |
| 异步信封 | 签名 job envelope、handler adapter、同步 transport、授权复核合同 | queue/worker/handler 和复核实现 |
| 字典 | system/Tenant 字典 provider 合同与查询服务 | 提供真实字典数据，不越界写其他 Module 表 |
| 菜单 | Core/Module 菜单目录、同步、可见性解释 | Module 声明、前端 route registry 与 HTTP 装配 |
| 缓存与调度辅助 | Tenant-safe cache/lock key builder、时间窗口 | 真正 cache/lock 和 scheduler |
| 服务覆盖点 | 有类型的 override slot、registry、resolution | 只在合法组合根注册，不能绕过安全合同 |

代表入口可见 Core 的 [`TenantAuthService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/kernel/src/Auth/TenantAuthService.php)、[`ModuleRegistryCompiler`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/kernel/src/Module/ModuleRegistryCompiler.php) 和 [`ExternalOperationHost`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/kernel/src/Host/ExternalOperationHost.php)。Kernel 没有通用出站 HTTP/云厂商 client，也没有随 PHP 包自动安装的完整后台路由。

### 其余 PHP 能力域

PHP 聚合包当前开发基线公开 13 个运行 namespace；`Testing` 只在 dev autoload。下表中的“数据 owner”指业务运行数据或状态由谁负责，不表示 Core 不能提供产品无关 Schema。

| 能力域 | 实际用途和代表公开入口 | 数据与宿主边界 | Core 自身验证状态 |
| --- | --- | --- | --- |
| Kernel | 上述认证、Tenant、RBAC、Module、审计、幂等等后台底座；[`TenantAuthService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/kernel/src/Auth/TenantAuthService.php) | Core 可拥有中立 Schema；宿主拥有连接、路由、业务调用和部署选择 | 有广泛 runtime/coverage 账本 |
| Settings | typed 定义、deployment/Tenant/target 优先级、revision/ETag、secret 加密；[`SettingResolver`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/settings/src/Application/SettingResolver.php) | Core 提供中立 Settings Schema；应用/Module 定义 key、默认值、业务含义和 secret 来源 | 有 runtime 与参考宿主装配 |
| DataPermission | 把 RBAC 后的数据策略编译为查询约束，含 Schema、repository、provider；[`DataPermissionEngine`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/data-permission/src/Engine/DataPermissionEngine.php) | 应用提供资源 target、policy provider 和实际 query 接入 | Core 有 runtime coverage；不代表宿主自动启用 |
| ReferenceCodes | 版本化的 Tenant code set/entry；[`ReferenceCodeAdminService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/reference-codes/src/Application/ReferenceCodeAdminService.php) | 宿主提供代码集用途、管理入口和数据生命周期 | 源码与参考宿主存在 |
| FileMedia | 私有文件元数据、上传/下载/archive/token、图片 variant、provider abstraction；[`FileService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/file-media/src/Application/FileService.php) | Core 提供中立 FileMedia Schema；宿主装配 provider、交付 HTTP、扫描和产品文件用途 | 不凭源码推断生产 storage/扫描资格 |
| TaskJob | Tenant job ledger、lease、retry、cancel、本地 worker；[`TaskJobService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/task-job/src/Application/TaskJobService.php) | 宿主提供可信 Tenant、任务 descriptor、worker 和业务 handler | 有 runtime 与参考 worker |
| NotificationSms | 站内信、模板、outbox/task/provider 和验证码机制；[`NotificationService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/notification-sms/src/Application/NotificationService.php) | 宿主拥有厂商配置、发送 provider、模板业务和收件人 | disabled/dev provider 不是生产短信资格 |
| ImportExport | Tenant CSV submit/list/cancel 与 runner，组合 FileMedia 和 TaskJob；[`ImportExportService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/import-export/src/Application/ImportExportService.php) | 应用拥有格式、校验、授权、文件用途和业务落库 | 有 runtime 与参考宿主装配 |
| IntegrationSecurity | machine identity、credential、webhook、OAuth、外部 Tenant/session device；[`MachineIdentityService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/integration-security/src/Application/MachineIdentityService.php) | 宿主提供外部 provider、secret、回调 HTTP 和业务绑定 | 有合同和参考适配 |
| OpsConsole | 状态/health、maintenance、可信任务描述、脱敏日志；[`OpsTaskService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/ops-console/src/Task/OpsTaskService.php) | 宿主提供备份、恢复、日志、权限和状态实现 | 有合同、UI 与参考宿主；不自动执行运维 |
| Workflow | 版本化流程图、实例、待办、事件与 adapter；[`WorkflowRuntime`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/workflow/src/Application/WorkflowRuntime.php) | 产品 Module 定义流程、target、授权和业务副作用 | 当前源码候选；本盘点未证明完整生产编辑器资格 |
| ArtifactRevision | append/compare 版本及 Workflow subject adapter；[`ArtifactRevisionService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/artifact-revision/src/Application/ArtifactRevisionService.php) | 产品拥有 artifact 语义、内容和发布动作 | 源码存在；无独立 current coverage 条目 |
| EntitlementQuota | grant、policy、usage、reservation、meter、decision；[`EntitlementQuotaService`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/entitlement-quota/src/Application/EntitlementQuotaService.php) | 产品拥有套餐、计量事件和商业规则 | 源码存在；无独立 current coverage 条目 |

**Collaboration 不在上述 13 项中。** 当前 Core `packages/php/composer.json` 没有 `PeanutAdmin\Collaboration\` PSR-4 映射，也没有 `packages/php/collaboration/` 或 `CollaborationService` 源码；仓内 CAP04/CAP06 文档只是计划合同，不能作为现有运行能力。

### Web 能力与客户端

`@peanut-admin/admin` 的 manifest 有 14 个 export subpath，其中 `./testing` 是 development conditional export，不能把“14 个 export”说成 14 个生产功能。

| export | 用途 | 宿主必须提供 |
| --- | --- | --- |
| `./core` | OpenAPI client/refresh/problem、Tenant/平台 auth store、permission、Module contribution、route guard、override | config、fetch/session、router/store 和真实 API |
| `./shell` | Admin/Platform shell、layout、状态、target selector、部署 routes/tabs/theme | route contribution、鉴权状态、页面和产品导航 |
| `./settings`、`./reference-codes`、`./file-media`、`./task-job`、`./notification-sms`、`./import-export`、`./integration-security`、`./ops-console` | 各域的 contracts、runtime、Module contribution、权限/路由/store 常量及页面或组件 | 对应后端 Module、transport、router、Pinia 和产品配置 |
| `./client` | 受保护通用客户端、session 与响应/错误处理 | base URL、transport、session、decoder 和 hooks |
| `./client/nuxt`、`./client/uniapp` | Nuxt `$fetch` 与 UniApp request transport | 平台原生请求实现和会话存储 |
| `./testing` | mock context/problem/route harness | 只用于开发和测试 |

完整导出见 Core 固定开发提交的 [`packages/web/package.json`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/web/package.json)。

### 参考宿主、生成入口与工程工具

Core `backend/` 和 `frontend/` 把上述能力装配成 ThinkPHP route/controller/runtime factory 和 Vue 页面；它们是**参考宿主**，不是第三个发布包，也不是独立应用仓。`starter/` 是固定的 package-consumption proof，README 明示不是公开项目生成器；`examples/` 是虚构 Module 的合同验证。

`scripts/create-project` 是生成器 wrapper；install、upgrade、health 和 `peanut:task-worker` 的实际命令实现位于参考宿主。其余安全、供应链、恢复、浏览器和 workspace 检查属于工程资格工具，不是产品 Runtime API。独立应用要采用这些能力，仍需明确选择生成产物 owner、升级覆盖范围和宿主入口。

## 独立应用实际采用矩阵

### 固定基线与扫描口径

应用审计固定在 `peanut-admin` commit `72fcf7b9bfbae62aa5329f99c49ec1356435e633`。后端 `server/composer.lock` 锁定 `peanut-admin/core` `0.1.0-alpha.12`，Composer split reference 为 `9017212da0da63f445d693be94d533f681c6dc92`；四个前端 lock 都锁定 `@peanut-admin/admin` `0.1.0-alpha.12`。Core 文档登记的 alpha.12 source commit 是 `9089516a18f19e19a048683594087e0b4ffc5455`。

本次在后端 `server/app`、`server/config`、`server/route`、`server/database` 以及 Web、Platform、PC、UniApp 四端 manifest/源码中扫描完整 namespace、export、实例化和调用，再读代表链路。下面的数字只是**静态引用口径**，包含 import、签名、实例化和调用；271 个文件、629 处引用与 119 个不同符号不等于 119 个已运行功能。测试目录另行分类，CLI/build 入口也不与生产调用混算。

| PHP 域 | 生产/安装目录文件数 | 静态引用数 | 不同符号数 | 采用判断 |
| --- | ---: | ---: | ---: | --- |
| Kernel | 271 | 629 | 119 | 广泛直接采用并由组合根装配 |
| Settings | 7 | 17 | 11 | 直接采用并有应用 adapter |
| TaskJob | 7 | 20 | 11 | 官方 Module 运行采用 |
| ImportExport | 9 | 20 | 13 | 官方 Module 运行采用 |
| OpsConsole | 23 | 53 | 24 | 后端宿主适配；平台前端直接采用 |
| IntegrationSecurity | 11 | 26 | 9 | 合同与宿主适配采用 |
| NotificationSms | 3 | 4 | 2 | 官方验证码/发送桥接采用 |
| FileMedia | 1 | 1 | 1 | 只采用 Tenant 对象 namespace；高层 FileMedia 未接入 |
| DataPermission | 2 | 5 | 5 | 只采用 Schema/合同反射治理，未找到生产 policy engine 接入 |
| ArtifactRevision | 1 | 1 | 1 | 有桥接源码，未发现生产入口 |
| EntitlementQuota | 1 | 1 | 1 | 有桥接源码，未发现生产入口 |
| Workflow | 1 | 1 | 1 | 有桥接源码，未发现生产入口 |
| ReferenceCodes | 0 | 0 | 0 | 未找到生产静态调用；应用用 Kernel Dictionary 与自有字典数据 |

### 采用状态、数据 owner 与下一步

| 域 | 当前应用接法与证据 | 数据 owner | 结论 | 最小后续任务与验收 |
| --- | --- | --- | --- | --- |
| Kernel | [`AppService`](../../server/app/AppService.php#L118) 绑定执行上下文、PDO 事务、认证和授权；业务 service/middleware 直接调用 Core | Core 中立 Kernel/Auth/RBAC/Module Schema；应用业务表、ThinkPHP Model/Scope 与请求生命周期 | **已有能力复用** | 保留；变更时以真实认证、Tenant、权限和 Module 既有 Gate 验收 |
| Settings | ImportExport 的 [`CoreSettingsConfigurationAdapter`](../../server/app/Modules/Official/ImportExport/Infrastructure/Configuration/CoreSettingsConfigurationAdapter.php#L53) 调 `SettingAdminService`/PDO repository；Module manifest 提供 definition | 应用安装基线承载 `pa_setting_*` 表并用 Core PDO repository 读写；各 Module 拥有 key、默认值和业务含义，应用装配 protector | **已有能力复用，宿主适配** | 把剩余设置逐项按语义比对，不按目录名迁移；验收 scope/secret/ETag 与现有调用 |
| TaskJob | [`PdoTaskJobRuntime`](../../server/app/Modules/Official/Task/Infrastructure/Runtime/PdoTaskJobRuntime.php) 把 Core job service/worker 接入官方 Task Module | 应用 `official.task` manifest 明确拥有 `pa_task_job*` 与 `pa_crontab`；Core repository/service 定义通用 job 行为 | **已有能力复用** | 保持单任务机制；新增 handler 以 lease/retry/cancel 和 Tenant 复核验收 |
| ImportExport | ModuleProvider 组合 Core runtime、Settings、TaskJob，并注入应用 FileMedia/operation log adapter | 应用 `official.import-export` manifest 拥有 operation/row-error 表、模板、字段和业务写入；Core service/repository 执行通用状态流 | **已有能力复用，宿主适配** | 对具体导入导出只补业务 handler；验收文件、任务、失败补偿和权限链 |
| OpsConsole | [`PlatformOpsRuntimeFactory`](../../server/app/platform/service/ops/PlatformOpsRuntimeFactory.php) 提供后端 adapter；[`platform/src/App.vue`](../../platform/src/App.vue#L37) 直接创建 Core UI runtime | Core 控制台合同/页面状态；应用拥有备份、恢复、日志和权限数据 | **已有能力复用，宿主适配** | 逐个运维动作保留可信 descriptor 和脱敏；验收实际 provider 与授权 |
| IntegrationSecurity | 应用实现 external resolver/binding/audit 及 OAuth/Wechat 宿主合同 | 当前接入只证明窄合同；应用保存 external binding，`official.oauth` manifest 拥有 `pa_oauth_*` 等表及 provider config、secret、绑定和回调。未发现应用采用 Core machine/webhook repository 的证据 | **部分复用，需要适配** | 按 provider 真实回调链逐项补证，验收签名、Tenant 绑定、重放和审计 |
| NotificationSms | Official Notification 的验证码 secret/sender bridge 调 Core 窄合同 | 当前接入只证明 `VerificationCodeSecret`/`NoticeSmsSender`；`official.notification` manifest 拥有 `pa_notice_*` 表、厂商、模板、接收人和用途。未发现应用采用 Core notification/outbox repository 的证据 | **部分复用，需要适配** | 真实 provider 另做资格；验收发送、频控、过期、Tenant 与失败状态 |
| FileMedia | [`FileObjectNamespace`](../../server/app/common/service/file/FileObjectNamespace.php#L11) 只调用 Core `TenantObjectNamespace`；应用仍有自己的 StorageService/账本/四厂商驱动 | 应用拥有凭据、账户/space 路由、用途、授权、对象账本、补偿与生命周期 | **部分复用；Storage Driver 为候选抽取** | 先复审本页和脚手架比较，再决定是否采用已在 Core dev 的窄 driver；验收不可变包、精确 lock、真实宿主装配和既有文件安全 Gate |
| DataPermission | [`ModuleDefinitionRegistryFactory`](../../server/app/platform/service/plugin/ModuleDefinitionRegistryFactory.php#L94) 引入 Core Schema 表名；[`ReflectionContractInspector`](../../server/app/platform/service/module/ReflectionContractInspector.php#L12) 反射四个 provider 合同 | 当前只证明 Schema allowlist 与合同治理；未找到应用实际 DataPermission repository、policy provider 或 query constraint 数据流 | **治理合同部分复用，生产 engine 接入未找到** | 若启用，先选一个真实资源完成 provider→query constraint 链并跑 Tenant/权限 Gate。应用现有 RBAC 与 `TenantOwnedModel`/TenantScope 继续保护访问；未采用 Core data-policy engine 不等于没有权限或 Tenant 隔离 |
| ArtifactRevision / EntitlementQuota / Workflow | [`CrossProductAdoptionHost`](../../server/app/common/service/capability/CrossProductAdoptionHost.php#L15) 含真实类型签名和方法调用，但全仓只找到测试实例化；没有生产 route/binding/caller | 尚无可证明的产品数据 owner/生产入口 | **有桥接源码，未发现生产入口** | 先由具体业务用例确定 owner 和入口，再验收真实调用、权限、事务/补偿；静态未找到不宣称绝对未使用 |
| Collaboration | 同一 bridge 直接声明并调用 `CollaborationService`，不是字符串占位；但锁定 alpha.12 vendor 与 Core dev 均没有该 namespace/class，只有测试 caller | 当前无 Core 运行数据 owner | **计划兼容源码引用，Core 能力缺失** | 先决定删除过时桥或重新建立公共能力；在此之前不能计入采用，也不执行 runtime 修复 |
| ReferenceCodes | 生产目录未找到专用 namespace 调用；应用采用 Kernel Dictionary 合同和自有字典数据 | 应用字典业务与数据 | **替代实现/路径已存在** | 只有出现版本化 code-set 用例时再做语义差距审计；验收不能只看同名 CRUD |

### 四个前端的采用

| 端 | 当前实际入口 | 判断 |
| --- | --- | --- |
| Web 管理端 | [`web/src/core/runtime.ts`](../../web/src/core/runtime.ts#L1) 使用 `./core` 的 override registry；认证、权限、Tenant session、Module contribution、routes/tabs 也直接采用 `./core` 与 `./shell` | 核心运行时直接采用；未找到各业务域 Web subpath 的直接 import |
| Platform | [`platform/src/api/platform.ts`](../../platform/src/api/platform.ts#L1) 和 `App.vue` 使用 `./ops-console` | 运维控制台直接采用并由平台宿主注入 API |
| PC | [`pc/composables/useRequest.ts`](../../pc/composables/useRequest.ts#L1) 使用 `./client` 与 `./client/nuxt` | 客户端协议直接采用，产品 API/页面留在 PC |
| UniApp | [`uniapp/src/utils/request.ts`](../../uniapp/src/utils/request.ts#L1) 使用 `./client` 与 `./client/uniapp` | 客户端协议直接采用，平台 request/session 由 UniApp 注入 |

四端 manifest 都声明 aggregate `0.1.0-alpha.12`；这属于工具/构建依赖身份，只证明包可解析，不证明每个 subpath 在 Runtime 被调用。

### 发布和升级成本

当前两个 aggregate 是已发生的分发边界，不是永久最优结论。13 个 PHP 域不应因为内部目录存在就自动拆成 13 个包；同样，PHP 与 Web 统一 alpha 版本、应用 Composer/npm lock 和现有联动发布流程，会让一个窄域修改也需要新的聚合包身份、兼容核对和下游锁更新。移动源码本身不会消除升级冲突。

后续可以单独评估“继续两个 aggregate”与“少数独立发布单元”的维护成本，至少产出候选依赖图、PHP/Web 兼容矩阵、版本/发布失败原子性和下游升级步骤，再决定是否改 workflow、版本或 package manifest。本轮不改这些发布事实。Collaboration 失效桥应作为独立清理/重建决策项核对，不能随其他域的发布被误认为现有功能。

## Storage Driver 当前事实

Core dev `9358686` 已加入低层 `StorageDriver` 四操作、对象 key 校验、HTTP transport 和 Local/Aliyun/Qcloud/Qiniu driver；具体 SDK client/transport 由宿主注入。见 [`StorageDriver`](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/packages/php/file-media/src/Storage/StorageDriver.php) 和 [边界说明](https://github.com/peanut-opensource/peanut-admin-core/blob/9358686fee873dd235489c8794abf556fd70ec4f/docs/architecture/storage-driver-boundary.md)。这批源码晚于 alpha.12，尚无新的不可变 split/应用 lock，因此不能说独立应用已经采用。

在应用 canonical `72fcf7b9` 中，[`AppService::registerStorage()`](../../server/app/AppService.php#L242) 仍装配应用自己的 `StorageDriverFactory`、`StorageService`、账本、凭据 resolver 和四个 driver。应用候选 commit `590e61830d0e62c0bf25425dfe43d69ae894b726` 已实现指向 Core dev 边界的源码改造，但它保留在独立候选 worktree，未合入 canonical `dev`、未改正式 lock，也未形成下游采用事实。

因此当前合理顺序是：先用本页和[脚手架边界比较](scaffold-core-boundary-comparison.md)确认定位，再决定保留应用实现、采用 Core driver 或调整公共边界。若决定采用，最小验收是新的不可变 PHP split 身份、应用精确 lock、Host 的 provider SDK 装配，以及文件用途、授权、对象账本、补偿、Tenant 和既有 FileMedia Gate；不要求同时迁移 Core 高层 FileMedia Schema。

## 证据范围与结论使用方式

本轮执行了当前 worktree 的 `scripts/project-codegraph ensure`，索引对应 `72fcf7b9`；随后用 `codegraph explore` 查询独立应用调用路径，并以 `rg` 全 namespace/export 扫描、manifest/lock 和上述源码逐项交叉核对。未运行 PHP/数据库/浏览器/构建测试，也没有安装或修改 vendor。

结论中的“未找到静态调用”只表示在声明扫描范围和固定 commit 中没有发现入口；动态类名、外部插件和未登记运行注入仍需专门运行证据。“已有 Core 能力”也只表示 Core 固定源码存在，不自动表示应用采用、生产资格或公开发布完成。

产品解释与同类产品比较另见[产品能力与同类产品参考矩阵](product-capability-reference-matrix.md)；真正的完成状态由 [`docs/product-status/capability-ledger.json`](../product-status/capability-ledger.json) 记录，两者都不替代本页的 Core 采用审计。
