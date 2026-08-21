---
title: 开发与部署指南
description: Peanut Admin 的架构、开发约定、客户端命令和生产部署边界。
---

# Peanut Admin 独立开发与部署指南

本文只描述当前 Peanut Admin 仓库中的代码、命令和运行边界，不依赖其他项目。示例中的域名、仓库地址、数据库账号、密码、证书和云服务参数均需按环境配置；不要把真实密钥提交到 Git。

## 5 分钟速读

```text
Peanut Core（通用身份、Tenant、权限和扩展合同）
  -> Peanut Admin Host/脚手架（应用入口、安装、管理端和产品能力）
      -> DCS 等独立派生应用（自己的业务 Module、版本和数据库）
          -> 一个或多个部署实例
              -> 每个实例包含 Tenant、客户端和已安装 Module
```

- 默认一套部署对应一个应用实例；一个实例可以服务多个 Tenant、多个客户端和多个 Module。
- 多个应用通常意味着多个独立部署和数据边界。它们可以共用云账号或集群，但不能共享私有表。
- Account 是登录身份，TenantMember 是租户成员，`pa_member` 是当前应用的业务会员；三者不是同一模型。
- 新业务优先放在独立 Module，通过公开命令、查询 DTO 或已验证的事件合同协作，不直接访问其他 Module 私有表。
- Plugin 安装、TenantModule 开通和成员授权是三道不同 Gate。
- DCS 是脚手架派生的独立应用；商品、库存、采购等领域能力不属于 Peanut Admin。

需要直接动手时，先读[API 与 Module 扩展](/api)；需要判断默认安装哪些能力时，读
[开箱即用能力目录](/capabilities)；fresh 安装和 1.x 退出边界见[部署与安装](/deployment)。

| 当前任务 | 先读 | 完成标志 |
| --- | --- | --- |
| 新增普通管理功能 | ThinkPHP 分层、认证权限、数据库迁移 | 路由、权限、菜单、后端拒绝和聚焦测试同时成立 |
| 新增独立业务域 | [Module 开发教程](/guide/module-development) | 表/代码/权限/菜单/测试有独立 owner，跨模块只依赖公开合同 |
| 新增可选官方能力 | [官方模块资格](/architecture/official-module-qualification) | Plugin + TenantModule + RBAC/Data Scope 四层全部通过 |
| 派生 DCS 应用 | create-app 与 Module 边界 | DCS 领域代码和文档留在 DCS 仓库 |
| 启动/安装/部署 | [命令参数参考](/guide/reading-guide) | 实际结果与文档的预期结果一致 |

## 1. 架构与目录

Peanut Admin 是 ThinkPHP 8 + Vue 3 客户端的前后端分离项目。HTTP 入口是
`server/public/index.php`，路由集中在 `server/route/app.php`。

~~~text
peanut-admin/
├── server/
│   ├── app/adminapi/        管理端 API：controller、logic、validate、service、http/middleware
│   ├── app/api/             会员/公开 API：controller、logic、validate、service
│   ├── app/platform/        实例内 PlatformOperator、Tenant 生命周期与平台 RBAC
│   ├── app/tenant/          Core Tenant 会话登录、选择、切换和退出 Host
│   ├── app/common/          公共 controller、logic、model、service、enum、validate
│   ├── app/Modules/         Plugin Module 后端；源码仓 fixture 仅用于资格验证
│   ├── config/              ThinkPHP、数据库、JWT、控制台等配置
│   ├── route/app.php        管理端和用户端路由
│   ├── database/init.sql    新环境的一次性全量表结构与种子
│   ├── database/migrations/ 版本化增量 SQL（由 database/install.php --migrate 执行）
│   ├── public/index.php     PHP-FPM/内置服务器入口
│   ├── public/storage/      本地存储和导出文件的公开目录
│   └── runtime/             日志、缓存和运行时文件（需可写）
├── web/                     Vue 3 + Element Plus 管理端
│   ├── src/{api,router,views,store,components,...}
│   └── src/modules/         Module 前端 contribution；fixture 不进入生成应用
├── pc/                      Nuxt 3 PC 会员端
│   ├── pages/、api/、components/、layouts/
│   ├── composables/、stores/、middleware/
│   └── nuxt.config.ts
├── uniapp/                  uni-app 多端会员端（H5/小程序等）
│   ├── src/{pages,packages,api,store,utils}
│   ├── src/pages.json、src/manifest.json
│   └── vite.config.ts
├── plugins/                 源码仓 Plugin artifact；正式应用由自己的 lock 决定
├── plugins.lock             当前部署允许加载的不可变 Plugin 身份
├── resources/               项目资源登记；连接、启动、迁移前的唯一事实源
├── scripts/                 create-app、scaffold release、资源租约和聚焦 Gate
├── deploy/                  development/production Compose 与 Nginx 配置
├── docs-site/               面向使用者和开发者的现行文档站
└── docs/                    架构合同、能力账本、计划和内部证据
~~~

### Core、Host、应用和 Module 的所有权

| 层 | 当前 owner | 可以拥有 | 不应拥有 |
| --- | --- | --- | --- |
| Core | `peanut-admin/core`、`@peanut-admin/admin` | Account、Tenant、RBAC 原语、公开 Host/override 合同 | Peanut 品牌、应用业务表、DCS 商品库存 |
| Peanut Host | 本仓 `server/app/*`、`web/`、安装和部署入口 | 应用路由、原生管理身份 Host/菜单适配、产品设置和已采用业务 Runtime | 跨应用运营平台、下游应用业务 |
| Module | `server/app/Modules/<Vendor>/<Module>` 与前端 contribution | 自有表、用例、权限、菜单、设置和公开合同 | 其他 Module 私有表和 Core schema |
| Plugin | `plugin.json` 与 `plugins.lock` | 一个或多个 Module 的制品身份和依赖 | Tenant 开通状态、成员权限和业务数据 |
| 派生应用 | create-app 生成的独立仓库 | 品牌、业务 Module、数据库、域名、发布周期 | 修改已安装 Core 包内部实现 |

### 应用、部署和客户端

“应用”是可独立发布的代码产品，“应用实例”是这套产品在某个环境的一次部署。默认
一套部署等于一个实例，拥有自己的数据库、密钥、文件空间和生命周期。一个实例可以同时
提供管理端、PC、H5、小程序等客户端，也可以在 `multi-tenant` 模式服务多个 Tenant。

同一 Kubernetes 集群、Docker 主机或云账号可以编排多个实例，但它们仍是多个应用部署。
每个实例只能通过公开 API/事件交换最小必要数据，不能让两个应用共享私有数据库表或复用
对方管理员账号。

只有法律隔离、地区合规、安全/故障域、独立发布回滚、团队 owner 或产品生命周期确实
不同，才优先拆成多个应用。菜单、角色、品牌或客户端差异通常由 TenantModule、权限和
客户端组合解决，不构成拆应用理由。

### 身份与业务主体

当前必须区分三条身份链：

| 身份 | 当前已支持程度 | 不能混写的边界 |
| --- | --- | --- |
| 平台/系统操作身份 | **v2.0.0 已验证并正式源码发布**：独立 PlatformOperator 会话、平台角色和权限；同一 Account 可以另有关联 TenantMember，但两套身份不互推 | 只治理本实例 Tenant/Module，不读取任意租户业务数据 |
| Tenant 管理成员 | **v2.0.0 已验证并正式源码发布**：管理端认证、会话、角色和权限直接使用 Core Account/Credential、TenantMember 与 RBAC；真实浏览器已通过三 Tenant 选择并进入 Store Demo | TenantMember 是组织成员，不自动成为客户、供应商联系人或门店员工档案 |
| 业务客户/会员 | **v2.0.0 已验证并正式源码发布**：`pa_member` 有独立注册、登录、OAuth、标签和单一权威余额，并按 Tenant 隔离 | 当前没有与 Core Account/TenantMember 的通用一对一映射；不要假定两种 token 可互换 |

Account/TenantMember/RBAC 足以作为客户组织员工或供应商组织成员的通用登录与授权基础，
但当前仓库没有 Supplier、业务主体关联、供应商邀请/成员治理或供应商专用客户端。因此
“供应商可建成 Tenant、其用户可成为 TenantMember”是**推荐新增的派生应用模型**，不是
Peanut Admin 当前开箱即用业务能力。

客户业务档案应继续与登录身份分离：Account 说明“谁登录”，Customer/Member 说明“业务上
是谁”。需要关联时使用显式、可选、可审计的 link，而不是把等级、积分、供应商资质等字段
放进 Account。

### 三类租户映射

| 类型 | 用途 | 当前状态与时机 |
| --- | --- | --- |
| legacy Admin/Role/Dept 到 Core 身份 | 旧单租户应用升级 | **仅迁移需要，当前版本不提供**。2.0.x 只接受空库安装，旧映射表、bootstrap 和 Runtime bridge 已退出 |
| 同应用业务主体到 Tenant/Member | 让 Supplier、Store、Customer 等主体由明确组织和成员操作 | **推荐新增到具体业务应用**；有真实跨组织流程时设计，不属于 legacy |
| 跨应用 global subject 到 local tenant | 两个独立实例识别同一外部组织 | **暂不建议**；只有两个真实实例稳定协作后再设计联邦和生命周期 |

同应用关联通常是 `business_subject <-> tenant` 和 `person/contact <-> account/member`，并附
角色、有效期和来源。它不能改变 TenantContext，也不能自动授予关联 Tenant 的全部数据。

### 门店与供应商在同一应用中协作

Peanut Admin 只提供通用安全边界，具体表和状态机由 DCS 等派生应用冻结。推荐起点是
“单一权威记录 + 明确 owner Tenant + 参与方操作授权”：

1. 采购单由采购方 Tenant 拥有，保存供应商业务主体、合作关系和下单快照。
2. Supplier 业务主体可以显式关联供应商 Tenant；其 TenantMember 只能通过采购模块的
   participant policy 查看、确认或发货，不获得采购方其他数据。
3. 商品授权和报价先校验双方 Relationship/Contract 的状态、有效期和 SKU 范围；采购单
   不能只凭另一个 Tenant ID 建立。
4. 供应商确认/发货只允许推进声明的状态和写入供应方字段；不能编辑采购方审批、仓库或
   成本字段。
5. 买方确认收货后，Procurement 调用 Inventory 的入库命令；供应商不能直接写买方库存表。
6. 查询 Repository 必须同时约束 owner Tenant 或明确 participant grant。业务关系存在不等于
   获得对方 Tenant 的通用读权限。

只有双方必须保留独立数据副本、自治状态机或不同保留策略时，才引入双方投影和可靠事件
同步；否则不要为“独立”制造重复订单真值。DCS 的 Product、Pricing、Inventory、
Procurement 和 Trade 详细文档归 DCS 仓，不进入 Peanut 默认模块。

三个客户端都以 `/api` 为后端前缀：`web/config/vite.config.dev.ts`、`pc/nuxt.config.ts` 和 `uniapp/vite.config.ts` 的纯本机开发代理均读取 `PHP_PORT`（登记默认 `20180`）。生产多阶段镜像将 web 管理端放在 `/admin/`、uniapp H5 放在 `/mobile/`、Nuxt PC 放在 `/pc/`；API 统一走 `/api/`。

后端路由大致分为：

- api/user/login、admin/login/login：管理员登录/退出；
- api/platform/*：独立 PlatformOperator 会话与实例内 Tenant/Module 治理；
- api/tenant/session/*：Core Tenant 会话登录、选择、切换和退出；
- api/admin/*：完整管理端 API，统一挂载登录、权限和操作日志中间件；
- api/*：会员公开接口与需会员令牌的接口；
- api/payment/notify/*、api/wechat/official-account/callback：第三方平台回调，按业务要求匿名进入后再验签。

## 2. ThinkPHP 分层规范

新增业务时按“路由 → controller → validate → logic → model/service”的方向组织，避免把 SQL、第三方 HTTP 调用和权限判断堆在控制器里。

### Controller

管理端控制器继承 server/app/adminapi/controller/BaseAdminController.php，会员端控制器继承对应的 BaseApiController。控制器只做参数获取、场景校验、调用逻辑和响应转换：

~~~php
$this->validate($this->request->post(), DemoValidate::class . '.add');
$result = DemoLogic::add($this->request->post());
return $result ? $this->success('操作成功') : $this->fail(DemoLogic::getError());
~~~

列表接口返回 dataLists() 结构；详情或配置返回 data()。路由必须明确声明 HTTP 方法和中间件，不依赖未登记的隐式路由。

### Logic

业务逻辑放在 `server/app/adminapi/logic/<module>/` 或 `server/app/api/logic/`，继承 `app\common\logic\BaseLogic`。逻辑层负责查询组合、事务、状态机、领域规则和错误信息；失败时 `setError()` 并返回 false，不要在控制器中复制业务规则。涉及多表写入时使用 `think\facade\Db::startTrans()`、`commit()`、`rollback()`。

### Validate

验证器放在与逻辑同名的 `validate/<module>/` 目录，使用场景（例如 `.add`、`.edit`、`.detail`）限制字段集合。跨字段、数据库唯一性或余额等规则写成验证器方法；逻辑层仍需对权限、状态和并发条件再次检查，不能把验证器当作授权层。

### Model

公共模型放在 server/app/common/model/，继承 BaseModel。BaseModel 已开启整型 create_time/update_time 自动时间戳；需要软删除的模型使用 ThinkPHP SoftDelete 并声明 delete_time。通过模型的 $name 使用 pa_ 前缀后的表名（例如 Member::$name = 'member' 对应 pa_member），关系、访问器和敏感字段隐藏也集中在模型中。不要在控制器直接拼接表名；字段变更先写迁移。

### Service、Enum 与公共层

跨模块能力放在 server/app/common/service/（例如 JsonService、FileService、支付、OAuth、通知、定时任务），常量和终端/状态映射放在 server/app/common/enum/。第三方 SDK 应通过 service/contract 边界接入，使 logic 可以注入替身进行测试。server/app/adminapi/service/ 只放管理端的令牌、权限、代码生成等横切能力。

## 3. 统一响应、认证、权限和数据范围

### 响应协议

server/app/common/service/JsonService.php 统一输出 JSON：

~~~json
{"code":20000,"msg":"success","data":{}}
~~~

JsonService::dataLists() 的 data 包含 lists、count、pageNo、pageSize；业务错误默认 40000，未登录 40100，无权限 40300。前端 axios/useRequest/uni-app request 会按 code 判断成功，不要返回裸数组或自定义另一套状态码。

### 管理员认证

server/route/app.php 中，登录路由不挂鉴权；管理端 api/admin/* 统一按 LoginMiddleware → AuthMiddleware → OperationLogMiddleware 执行：

1. LoginMiddleware 从 `Authorization: Bearer <token>` 读取原生 `pa_tat_` Tenant access token，验证 Account、TenantMember、Tenant 和会话状态，再注入可信 TenantContext 与管理成员主体。
2. AuthMiddleware 根据请求路径去掉 api/admin/ 得到权限标识，例如 api/admin/menu/lists 对应 menu/lists。
3. OperationLogMiddleware 仅记录成功的 POST 写操作，并对 password、token、secret、证书等字段打码。

管理端登录账号由安装时显式提供的 `ADMIN_INITIAL_EMAIL` 创建。内建
`core.tenant-owner` Role 表达首 owner 权限，不再使用旧 `pa_admin.root` 隐式身份；初始密码
必须通过 `ADMIN_INITIAL_PASSWORD` 提供，仓库没有可复用的默认密码。

会员端需登录的路由挂 server/app/api/middleware/CheckTokenMiddleware.php，公开路由不挂该中间件。会员令牌与管理员令牌不要混用。

### 菜单和权限

`pa_system_menu` 的 type 为 M（目录）、C（菜单）或 A（按钮/API 权限），继续作为应用菜单
展示清单；可授权的 `perms` 对应 Core `pa_permission.key`，角色通过
`pa_role_permission` 获权，成员通过 `pa_member_role` 关联角色。AdminPermissionService 组合
原生权限和应用菜单树；前端动态路由以服务端返回为准。新增管理接口应同时：

- 在 server/route/app.php 写明路由；
- 在 init.sql 或对应迁移中写入 perms 与菜单节点；
- 在角色中授予 A 权限，确认 perms 与去掉 api/admin/ 后的 URI 完全一致。

当前实现对未登记、已停用或跨 Tenant 的 URI **默认拒绝**，而且先检查登记再判断 root；
因此 root 也不能绕过未登记路径。新增接口必须在 migration/菜单资源中登记准确 `perms`，
再由角色授予。不要依据旧 PB04 合同中封存的“未登记放行”历史语义开发新接口。

### 数据范围（Data Scope）

仓库有 Core `pa_department`、应用岗位字典 `pa_jobs` 和原生成员组织关系；Core 也提供 Tenant 权限集合和 typed-target
数据权限原语。但现有应用业务表不会因为管理员拥有某个部门或按钮权限就自动获得行级过滤。
Module 必须为自己的 Store/Warehouse 等资源提供 target resolver，并在列表、详情和写命令中
使用可信 TenantContext 与授权目标约束查询。不要把“有功能权限”误认为“拥有全部数据”。

## 4. 数据库与迁移

- 数据库默认 MySQL，编码 utf8mb4，表前缀由 DB_PREFIX 控制，默认 pa_；配置来源是 server/config/database.php。
- 新环境先创建空数据库并配置根 `.env`；生产由 Compose 注入环境，本地维护环境由资源登记和启动器注入。再执行 `php server/database/install.php`。安装器创建 Core 原生 Schema，执行 canonical `server/database/init.sql` 和基线之后的追加式 migration，并校验表、菜单、配置、默认 Tenant 与首 owner；目标库已有任何表时拒绝运行。
- `server/database/init.sql` 是 2.0.0 的完整应用基线，`server/database/migrations/` 只接收该基线之后的追加变更。安装结果把 `init.sql` 和后续 migration 的名称、SHA-256、批次与状态写入 `pa_schema_migration`。
- `pa_schema_migration` 属于 canonical `init.sql`。`php server/database/install.php --migrate --current` 校验基线身份和全部已登记文件；普通 `install.php --migrate` 只执行缺失的基线后追加 migration。
- 2.0.0 不支持接管 1.x 历史安装。需要保留旧环境时继续运行旧版本实例，为 2.0.0 准备独立空库，并把业务数据迁移作为独立、显式、可验收的项目处理。
- server/database/import.php 是读取 .env 后通过 PDO 执行 init.sql 的一次性脚本，文件注释明确提示“用完即删”；优先使用可审计的 mysql 导入命令，除非环境确实需要该脚本。
- 金额、状态和软删除字段应沿用已有表的类型/命名；涉及旧数据兼容时在迁移中写幂等的 information_schema 检查，并先发布迁移再发布依赖字段的 PHP 代码。

## 5. 配置与密钥

复制根 `.env.example` 为 `.env`，至少填写：

~~~dotenv
APP_ENV=production
APP_DEBUG=false
PEANUT_DEPLOYMENT_TARGET=production
PEANUT_DATABASE_RESOURCE_ID=<已登记的生产数据库资源 ID>
DB_HOST=mysql
DB_PORT=3306
DB_NAME=<empty-database>
DB_USER=peanut_admin
DB_PASS=按环境配置
DB_PREFIX=pa_
JWT_SECRET=至少32位随机字符串
JWT_EXPIRE=7200
DEPLOYMENT_MODE=standalone
TENANT_IDENTIFIER_HMAC_KEY=至少32字节稳定随机值
PLATFORM_IDENTIFIER_HMAC_KEY=另一项至少32字节稳定随机值
ADMIN_INITIAL_EMAIL=owner@example.com
ADMIN_INITIAL_PASSWORD=仅空库首次安装使用的强密码
~~~

| 配置组 | 必填场景 | 作用 | 注意 |
| --- | --- | --- | --- |
| `APP_ENV` / `APP_DEBUG` | 所有环境 | 控制运行模式和调试输出 | 生产固定 `production/false` |
| `PEANUT_DEPLOYMENT_TARGET` / `PEANUT_DATABASE_RESOURCE_ID` | 所有连接数据库的操作 | 绑定登记环境和数据库资源 | 不静默回退到 localhost |
| `DB_*` | 所有数据库环境 | 应用数据库连接 | 首次安装必须确认空库 |
| `JWT_SECRET` | 所有运行环境 | 会话签名 | 至少 32 位，禁止提交 |
| `DEPLOYMENT_MODE` | 所有部署 | 单租户或多租户 | 只接受两个合法枚举 |
| 两项 `*_HMAC_KEY` | 管理身份运行时 | 稳定身份索引 | 必须独立、稳定且至少 32 字节 |
| `ADMIN_INITIAL_*` | 空库首次安装 | 创建首个 Tenant owner | 安装后不作为日常凭据配置 |
| `PLATFORM_INITIAL_*` | 多租户空库首次安装 | 创建独立 PlatformOperator | 不能与 Tenant owner 邮箱相同 |

人工只维护这些普通键。生产 Compose 和本地启动器会派生 ThinkPHP 内部使用的 `PHP_*`
进程变量；不要另建一份 `server/.env`。DB_TYPE、DB_DRIVER、DB_CHARSET 可按
server/config/database.php 的默认值或环境需要设置。管理端锁定参数可通过 ADMIN_TOKEN_EXPIRE、ADMIN_TOKEN_RENEW_BEFORE、ADMIN_PASSWORD_ERROR_TIMES、ADMIN_LOGIN_LOCK_MINUTES 覆盖 server/config/admin_auth.php 的默认值。

网站、支付、存储、渠道、充值和 OAuth 等业务配置主要保存在 pa_config，由管理端设置页面维护；不要把支付私钥、微信 AppSecret、短信密钥、对象存储 Secret、证书内容或 .env 提交到仓库。生产环境使用独立的密钥注入/文件权限方案，并限制 PHP-FPM 用户读取证书目录。配置修改后如遇旧值，使用管理端系统维护页面清理缓存或按发布流程重启 PHP-FPM。

品牌默认值的唯一安装前入口是 `server/config/brand.json`，源资产在 `server/public/brand/`。克隆后、首次安装前可修改这两处，再运行 `node scripts/sync-brand-assets.mjs` 生成 Web、PC、UniApp/H5 与官网的静态 fallback；CI 使用 `node scripts/sync-brand-assets.mjs --check` 防止漂移。安装完成后，品牌只通过管理端“应用设置 → 网站设置”写入 `pa_config(type=website)`，不要手改生成 JSON、复制核心实现或修改 `vendor/`、`node_modules/`。仓库默认 `official_url` 为空，目标部署应显式填写正式官网地址。

通知验证码由 `NoticeChannelService` 统一读取 `pa_config` 的阿里云/腾讯云凭据并选择唯一启用 Provider，`VerificationCodeService` 不得自行解析配置或实例化驱动。验证码只保存 `password_hash`，内容快照固定为 `****`，Provider 回执只保存白名单字段；当前同步发送不自动重试。通用通知模板、邮件/SMTP 没有产品消费者，不得绕过固定 scene 恢复入口。完整边界见应用仓 `docs/architecture/pb07-notification-host-contract.md`。

## 6. 从 clone 到可登录开发环境

以下步骤使用仓库当前 README 与脚本中的路径。`<仓库地址>`、数据库账号和密码是占位符，按环境替换。

~~~bash
git clone <仓库地址> && cd peanut-admin

# 根环境配置与后端依赖
cp .env.example .env
# 编辑根 .env；直接执行安装器时由受控 shell/资源选择器导出相同普通键
cd server && composer install && cd ..

# 创建空数据库（数据库用户的创建和授权按环境配置）
mysql -u root -p -e "CREATE DATABASE peanut_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 安装基础结构、全部迁移和种子；非空数据库会被拒绝
php server/database/install.php
~~~

安装器会创建默认 Tenant、原生 Account/Credential/TenantMember 和内建
`core.tenant-owner` Role。空库安装必须显式提供有效 `ADMIN_INITIAL_EMAIL`，以及至少 6 位的
`ADMIN_INITIAL_PASSWORD`；安装器不会回显密码。日常开发统一使用登记的宿主 PHP 8.3.24 与 Composer 2.8.10：

~~~bash
./scripts/local-stack.sh dev-up
./scripts/local-stack.sh status
~~~

默认打开 `http://127.0.0.1:20187/admin/`，使用安装时提供的邮箱和密码登录。
宿主 API 登记默认端口为 `20180`；Web/PC/Mobile/Docs/Nginx 容器通过
`host.docker.internal:${PHP_PORT}` 绕过代理访问它。本地监听统一来自 `.local/stack.env`
（或 `PEANUT_LOCAL_ENV_FILE`），可按 clone/worktree 覆盖，示例见
`deploy/local-stack.env.example`。停止使用 `./scripts/local-stack.sh dev-down`。

安装后可执行 `php server/database/install.php --migrate --current` 校验当前数据库与源码基线。后续发布
只执行普通 `install.php --migrate` 应用 3.0 之后的追加 migration；失败时保持旧版本运行，核对实际
DDL 后编写前滚修复，不得删除账本记录或改写已登记 SQL。

## 7. 客户端开发与构建命令

### web/ 管理端

~~~bash
cd web
pnpm install
pnpm dev                 # 纯本机模式读取 VITE_PORT/PHP_PORT；登记默认 20181/20180
pnpm run type:check
pnpm build               # vue-tsc + Vite 生产构建，产物 dist/
pnpm preview             # 构建后本地预览
~~~

接口基址可通过 VITE_API_BASE_URL 覆盖；未设置时使用同源 /api。登录和按钮权限分别由 web/src/store/modules/user/、web/src/router/guard/ 与 web/src/directive/permission/ 协作完成。

### pc/ Nuxt 3 会员端

~~~bash
cd pc
npm install
npm run dev              # 读取 PC_PORT/PHP_PORT；登记默认 20185/20180
npm run typecheck
npm run build
npm run preview
~~~

pc/nuxt.config.ts 的 runtimeConfig.public.apiBase 控制浏览器端 API 基址，开发时 `/api` 代理到当前 `PHP_PORT`。生产使用 `npm run generate` 生成静态 SPA，由 Nginx 挂载在 `/pc/`。

### uniapp/ 多端会员端

~~~bash
cd uniapp
npm install
npm run dev:h5
npm run type-check
npm run build:h5

# 微信小程序（需要在 src/manifest.json 填写对应 appid）
npm run dev:mp-weixin
npm run build:mp-weixin
~~~

uniapp/src/utils/request.ts 读取 `VITE_APP_BASE_URL`；H5 开发和同源生产默认留空，分别使用开发代理或同源 `/api`。生产 H5 产物位于 `dist/build/h5`，Docker 将其复制到 Nginx 镜像的 `/mobile/` 子目录。小程序仍需按平台配置 appid、合法域名和证书。

## 8. 开发环境与生产部署

### 生产应用仓与多阶段 Compose

生产服务器针对已经存在的应用仓执行发布，不在部署时创建新应用。服务器只安装 Git 和 Docker Compose，复制根目录 `.env.example` 为受保护的 `.env` 并填写外部 MySQL 地址后，直接执行 `docker compose up -d --build`；根目录 `compose.yaml` 会引用生产配置，宿主机不需要 Node.js、pnpm、PHP 或 Composer。日常开发的 `deploy/docker-compose.dev.yml` 不含 PHP 服务，API 由宿主 PHP 托管；不要与生产 Compose 混用。完整命令见[部署清单](/deployment)。

生产镜像在 Docker 多阶段构建中同时处理三个客户端：web 管理端写入 `server/public/admin/`，uniapp H5 写入 `server/public/mobile/`，Nuxt PC 写入 `server/public/pc/`。Nginx 将 `/admin/`、`/mobile/`、`/pc/` 和 `/api/` 分别路由到对应目录或 PHP。默认服务包括 PHP-FPM、Nginx 和后端 scheduler；生产连接 `.env` 指定且可从容器路由的 MySQL，单机部署可启用 `bundled-db`，Redis 只通过 `redis` profile 显式启用。PHP 容器入口会自动执行可跳过已安装数据库的安装器。

无论采用 Docker 还是原生发布包，都必须确保运行用户可写 `server/runtime/` 和 `server/public/storage/`。生产环境使用随机 `JWT_SECRET`、正确数据库凭据和 `APP_DEBUG=false`。

### Nginx

Nginx 根目录固定为发布制品的 `server/public/`。根路径 `/` 重定向到 `/admin/`；管理端、H5 和 PC 静态文件分别位于 `server/public/admin/`、`server/public/mobile/` 和 `server/public/pc/`；`/api/` 和管理登录 `/admin/login/*` 进入 ThinkPHP。

~~~nginx
server {
    listen 80;
    server_name admin.example.com;
    root /var/www/peanut-admin/server/public;

    location = / {
        return 302 /admin/;
    }

    location = /admin {
        return 302 /admin/;
    }

    location = /mobile {
        return 302 /mobile/;
    }

    location = /pc {
        return 302 /pc/;
    }

    location ~ ^/admin/login/(?:login|logout)/?$ {
        try_files $uri /index.php?$query_string;
    }

    location ^~ /api/ {
        try_files $uri /index.php?$query_string;
    }

    location /admin/ {
        try_files $uri $uri/ /admin/index.html;
    }

    location /mobile/ {
        try_files $uri $uri/ /mobile/index.html;
    }

    location /pc/ {
        try_files $uri $uri/ /pc/index.html;
    }

    location / {
        try_files $uri $uri/ /admin/index.html;
    }

    location ^~ /storage/ {
        try_files $uri =404;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        fastcgi_pass php:9000;
    }
}
~~~

宝塔面板的反向代理目标为 `http://127.0.0.1:18092`；Cloudflare 对应 DNS 记录开启代理。宝塔站点的 443 必须安装覆盖应用域名的 Cloudflare Origin CA 或 Let's Encrypt 证书，Cloudflare 使用 `Full (strict)`。PHP-FPM 和 MySQL 不直接暴露到公网。

### PC 与 uni-app 产物

管理端 `web/dist/` 进入 `/admin/`，uniapp H5 构建产物进入 `/mobile/`，PC 使用 `npm run generate` 生成 `.output/public/` 并进入 `/pc/`。三端都是独立静态目录，不覆盖后端 public 根文件。

### Cron、命令与“队列”边界

仓库没有队列驱动、队列 worker 或 Supervisor 配置；异步/周期工作通过 ThinkPHP Console + 系统 cron 实现。server/config/console.php 当前注册了 crontab、crontab:demo、refund:reconcile、generator:cleanup 四个命令。调度器源码要求每分钟执行一次：

~~~cron
* * * * * cd /var/www/peanut-admin/server && /usr/bin/php think crontab >> /var/log/peanut-crontab.log 2>&1
~~~

pa_crontab 的 status=1 任务按自己的 Cron 表达式触发；init.sql 默认启用 refund:reconcile。调度器使用 MySQL GET_LOCK 防止多实例重复派发，并只允许 config/console.php 中显式注册且不是 crontab 自身的命令。若未来接入真正消息队列，应新增独立 service/worker 和部署清单，不要把队列调用塞进 Crontab 调度器。

可手动验证已注册命令：

~~~bash
cd server
php think crontab:demo
php think refund:reconcile
php think generator:cleanup
~~~

## 9. 存储、支付和微信 OAuth 的扩展边界

### 文件存储

server/app/common/service/storage/Driver.php 通过 storage.default 选择 local、qiniu、aliyun 或 qcloud 引擎，配置保存在 pa_config(type=storage)。本地文件写入 server/public/storage/，URI 以 storage/ 开头；云存储 URI 是对象 key，由 FileService 拼接配置的 domain。七牛/阿里云配置 bucket/access_key/secret_key/domain，腾讯 COS 还需要 region。切换默认引擎不会搬迁旧文件，旧云域名配置必须继续有效。新增存储厂商时实现 storage/engine/Server.php 约定、在 Driver 注册，并补充后台验证；不要在 controller 直接调用 SDK。

### 内容与装修

文章、分类、收藏/计数、搜索和移动/PC/Tabbar 装修是应用产品 Module，不迁入核心包。`ProductAssetReferenceService` 是文章与装修资源引用的唯一边界：同源 local `/storage/` 地址保存为相对 URI，云/CDN/外部地址保留绝对 provenance；历史无 provenance 相对资源仍依赖原 Provider 配置，不得猜测来源或批量改写。`DecorationSchemaService` 唯一拥有组件与链接规则，`DecorationReadService` 唯一生成管理端、API、PC 与 UniApp/H5 的读取 DTO；客户端只渲染结果，不复制 Schema 或草稿/发布状态机。对应数据库增量为 `20260811-content-asset-reference.sql`，已有环境应先备份并通过迁移账本执行，再发布依赖扩容字段的代码。

### 支付

支付入口由 server/app/common/service/payment/PaymentServiceFactory.php 统一选择 wechat 或 alipay。预支付、回调 parser 和退款 gateway 都只能由该 Factory 装配，并共用可注入 `PaymentTransportInterface` 与 `PaymentCrypto`；gateway 不得另建 cURL/签名路径。回调先验签和标准化，再交给充值 logic 更新订单、余额和流水；微信商户响应还必须用平台证书校验 timestamp、nonce、serial 和签名，支付宝退款响应必须按原始节点验 RSA2。会员余额只保留 `user_money` 权威字段，流水只保留 `left_amount` 作为变更后余额；后台调账、可信充值回调和首次充值退款必须在各自领域事务内调用 `MemberBalanceService`，不得直接写余额或 `AccountLogLogic`。回调路由是 /api/payment/notify/wechat 与 /api/payment/notify/alipay，必须保持公网 HTTPS、时间戳/证书/公钥配置正确。完整边界见应用仓 `docs/architecture/pb07-payment-host-contract.md`。

### 微信 OAuth

OAuth 场景和配置边界在 server/app/api/logic/OAuthLogic.php：mnp 使用 mnp_setting，公众号 oa 使用 oa_setting，PC 开放平台 open_pc 使用 open_platform。身份表、一次性 state 和补全票据属于 canonical `init.sql`；浏览器流程通过 /api/oauth/wechat/begin、callback，小程序通过 mini-program，已登录绑定通过 bind。`OAuthBrowserCallbackService` 是浏览器回跳唯一映射点：微信分别登记 `/api/oauth/wechat/redirect/pc` 与 `/api/oauth/wechat/redirect/official-account`，再固定桥接到 `/pc/oauth/callback` 与 `/mobile/#/pages/oauth/callback`，禁止客户端或控制器另拼根路径。state、completion ticket 均为 32 字节随机值、SHA-256 存储、600 秒和行锁单次消费；UniApp completion ticket 暂存后读即删，不进入 URL，补全接口不能当作会员 token。当前只支持明文公众号回调。新增 OAuth 提供商应实现 OAuthTransportInterface，保留 provider/client/subject 隔离和一次性票据语义，不要在控制器直接写身份表。完整边界见应用仓 `docs/architecture/pb07-oauth-channel-host-contract.md`。

## 10. 增加一个业务模块

新增独立业务优先采用 `server/app/Modules/<Vendor>/<Module>/` 和
`web/src/modules/<module-slug>/`，而不是继续把所有代码堆入共享 `common/` 和 `views/`。

最小纵向顺序：

1. 在 `module.json` 固定 Module key、依赖、owned tables、公开合同和停用行为。
2. 在 Module 自有 `Database/Migrations/` 增加追加式 migration；不修改历史 migration。
3. 在 `Contracts/` 定义其他模块可用的命令/查询 DTO，在 `Application/` 实现用例和事务。
4. Repository 只从可信 TenantContext 取 `tenant_id`，并在所有读写路径约束它。
5. 在 `Resources/permissions.json`、`menus.json` 和设置定义中登记扩展面。
6. 在 `ModuleProvider.php` 装配合同与基础设施；HTTP 路由仍在应用 Host 显式登记。
7. 前端通过 `contribution.ts` 注册页面、Module key 和 required permissions。
8. 固定 Plugin artifact/lock，安装后再分别开通 TenantModule 和成员权限。
9. 最低验证覆盖安装幂等、两个 Tenant 隔离、停用拒绝、无权限、前端 contribution 和公开合同。

当前可执行的完整 fixture、目录说明和跨模块调用边界见
[Module 开发教程](/guide/module-development)。生成器只能生成起点，不能替代 owner、权限、Tenant
隔离和失败恢复审查。

## 11. 最小验证与常见故障

发布前只做与本次改动直接相关的最小检查：

~~~bash
# 后端 PHP 语法（按改动文件执行）
php -l server/app/<changed-file>.php

# 前端类型/构建（按改动客户端执行）
cd web && pnpm run type:check
# 或 cd pc && npm run typecheck
# 或 cd uniapp && npm run type-check
~~~

常见现象和处理方向：

| 现象 | 直接检查 |
| --- | --- |
| 40100 | 请求是否带 `Authorization: Bearer pa_tat_...`；Tenant access token 是否过期；Account、TenantMember 或 Tenant 是否已停用。业务会员令牌不能用于管理端。 |
| 40300 或菜单为空 | 检查 `pa_system_menu.is_disable`、`pa_permission`、`pa_role_permission`、`pa_member_role` 和 perms 是否与实际 `/api/admin/...` 路径一致；当前未登记 URI 对 owner 和普通成员都默认拒绝。 |
| 数据库连接失败/表不存在 | .env 的 DB_*、DB_PREFIX 与 MySQL 授权；全新库确认 `php server/database/install.php` 成功，已有库确认目标迁移已执行。 |
| 前端请求 404/CORS | 开发代理是否指向当前 `PHP_PORT`；生产是否将 `/api/` 送到 ThinkPHP，并确认 `/admin/`、`/mobile/`、`/pc/` 分别命中对应客户端；检查各客户端 API base 配置。 |
| 上传/导出失败或文件 404 | PHP-FPM 对 server/runtime/、server/public/storage/ 的写权限；Nginx /storage/ alias；ZipArchive 是否安装。 |
| 支付/OAuth 失败 | 先确认 pa_config 中对应开关、AppID、证书/公钥、回调 HTTPS 和平台白名单；查看 server/runtime/log/，不要关闭验签。 |
| 定时任务不执行 | 系统 cron 是否每分钟调用 php think crontab；pa_crontab.status、表达式、error、数据库 GET_LOCK；命令是否在 server/config/console.php 注册。 |
| PHP 报缺少 cURL/OpenSSL/mbstring | 按本节扩展要求安装到实际 PHP-FPM/CLI 两套运行时，并确认 CLI 与 FPM 版本一致。 |

## 12. 术语表

| 术语 | 含义 |
| --- | --- |
| Core | 可由多个应用消费的通用 PHP/Web 契约和原语，不是完整业务应用 |
| Host | 把 Core 接入具体框架、路由、配置、数据库和客户端的应用层 |
| Application | 有独立产品边界、版本、Module 组合和数据所有权的代码产品 |
| Application Instance | Application 在一个环境中的一次部署，拥有自己的数据库和密钥 |
| Tenant | 一个实例内的数据隔离和成员授权根，不是门店/仓库的通用别名 |
| Account/Credential | 可登录身份及其凭据，不直接保存业务客户或供应商资料 |
| TenantMember | Account 在某个 Tenant 内的成员身份和状态 |
| PlatformOperator | 只治理本实例平台能力的独立身份，不是 TenantMember |
| Business Subject | 客户、供应商或经营公司等业务主体，可选择显式关联 Tenant |
| Module | 业务代码、表、权限、菜单、测试和公开合同的唯一 owner |
| Plugin | 携带一个或多个 Module 的不可变安装制品 |
| TenantModule | 某个 Tenant 对已安装 Module 的开通状态 |
