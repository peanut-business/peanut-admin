# Peanut Admin 独立开发与部署指南

本文只描述当前 Peanut Admin 仓库中的代码、命令和运行边界，不依赖其他项目。示例中的域名、仓库地址、数据库账号、密码、证书和云服务参数均需按环境配置；不要把真实密钥提交到 Git。

## 1. 架构与目录

Peanut 是 ThinkPHP 8 + Vue 3 客户端的前后端分离项目。HTTP 入口是 server/public/index.php，路由集中在 server/route/app.php。

~~~text
peanut-admin/
├── server/
│   ├── app/adminapi/        管理端 API：controller、logic、validate、service、http/middleware
│   ├── app/api/             会员/公开 API：controller、logic、validate、service
│   ├── app/common/          公共 controller、logic、model、service、enum、validate
│   ├── config/              ThinkPHP、数据库、JWT、控制台等配置
│   ├── route/app.php        管理端和用户端路由
│   ├── database/init.sql    新环境的一次性全量表结构与种子
│   ├── database/migrations/ 已有环境的增量 SQL（无自动迁移命令）
│   ├── public/index.php     PHP-FPM/内置服务器入口
│   ├── public/storage/      本地存储和导出文件的公开目录
│   └── runtime/             日志、缓存和运行时文件（需可写）
├── web/                     Arco Design Vue 管理端
│   └── src/{api,router,views,store,components,...}
├── pc/                      Nuxt 3 PC 会员端
│   ├── pages/、api/、components/、layouts/
│   ├── composables/、stores/、middleware/
│   └── nuxt.config.ts
├── uniapp/                  uni-app 多端会员端（H5/小程序等）
│   ├── src/{pages,packages,api,store,utils}
│   ├── src/pages.json、src/manifest.json
│   └── vite.config.ts
└── docs/                    项目文档
~~~

三个客户端都以 /api 为后端前缀：web/config/vite.config.dev.ts、pc/nuxt.config.ts 和 uniapp/vite.config.ts 的开发代理均指向 http://127.0.0.1:8000。生产环境建议由同一域名的 Nginx 将 /api 转给 PHP-FPM，减少跨域配置。

后端路由大致分为：

- api/user/login、admin/login/login：管理员登录/退出；
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

业务逻辑放在 server/app/adminapi/logic/<module>/ 或 server/app/api/logic/，继承 app\common\logic\BaseLogic。逻辑层负责查询组合、事务、状态机、领域规则和错误信息；失败时 setError() 并返回 false，不要在控制器中复制业务规则。涉及多表写入时使用 think\facade\Db::startTrans()、commit()、rollback()。

### Validate

验证器放在与逻辑同名的 validate/<module>/ 目录，使用场景（例如 .add、.edit、.detail）限制字段集合。跨字段、数据库唯一性或余额等规则写成验证器方法；逻辑层仍需对权限、状态和并发条件再次检查，不能把验证器当作授权层。

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

1. LoginMiddleware 从 Authorization: Bearer <token> 读取 pa_admin_session，检查过期、登录 IP、账号状态，并把 adminInfo/adminId 注入请求。
2. AuthMiddleware 根据请求路径去掉 api/admin/ 得到权限标识，例如 api/admin/menu/lists 对应 menu/lists。
3. OperationLogMiddleware 仅记录成功的 POST 写操作，并对 password、token、secret、证书等字段打码。

管理员 JWT 配置见 server/config/jwt.php；管理端会话时长和登录锁定参数见 server/config/admin_auth.php。超级管理员是 pa_admin.root=1，当前种子账号为 admin，首次登录后必须改密。

会员端需登录的路由挂 server/app/api/middleware/CheckTokenMiddleware.php，公开路由不挂该中间件。会员令牌与管理员令牌不要混用。

### 菜单和权限

pa_system_menu 的 type 为 M（目录）、C（菜单）或 A（按钮/API 权限）；角色通过 pa_system_role_menu 授权，管理员通过 pa_admin_role 关联角色。AdminPermissionService 为非 root 管理员计算菜单树和按钮权限；菜单接口由 MenuController 提供，前端动态路由以服务端返回为准。新增管理接口应同时：

- 在 server/route/app.php 写明路由；
- 在 init.sql 或对应迁移中写入 perms 与菜单节点；
- 在角色中授予 A 权限，确认 perms 与去掉 api/admin/ 后的 URI 完全一致。

当前实现对“未登记在启用菜单中的 URI”默认放行；因此敏感接口不能漏写 perms，应在菜单种子和权限验证中一并覆盖。root=1 直接放行全部已启用菜单。

### 数据范围（Data Scope）

仓库有 pa_dept、pa_jobs、pa_admin_dept、pa_admin_jobs 关系和管理员维护页面，但没有通用的角色数据范围字段或统一 DataScope 查询服务；AdminPermissionService 只处理菜单/按钮 URI。部门/岗位关联本身不会自动限制业务列表。若模块需要行级隔离，必须在该模块的 logic/query 中显式加入管理员部门或业务归属条件，并在 detail/edit/delete 再次校验；不要把“有角色权限”误认为“拥有全部数据”。

## 4. 数据库与迁移

- 数据库默认 MySQL，编码 utf8mb4，表前缀由 DB_PREFIX 控制，默认 pa_；配置来源是 server/config/database.php。
- 新环境先创建空数据库并配置 server/.env，再执行 `php server/database/install.php`。安装器按顺序执行 server/database/init.sql 和全部 server/database/migrations/*.sql，并校验预期表、菜单、配置及默认管理员；目标库已有任何表时会拒绝运行。
- server/database/init.sql 只保存基础结构和基础种子，不单独代表完整的当前版本。后续业务表和增量字段以 migrations/ 为准，由首次安装器统一收口。
- 既有环境的增量文件位于 server/database/migrations/，文件名按时间排序。仓库没有迁移记录表或 php think migrate 命令；发布时先备份，再由 DBA 按文件名逐个执行尚未应用的 SQL，并记录版本。
- server/database/import.php 是读取 .env 后通过 PDO 执行 init.sql 的一次性脚本，文件注释明确提示“用完即删”；优先使用可审计的 mysql 导入命令，除非环境确实需要该脚本。
- 金额、状态和软删除字段应沿用已有表的类型/命名；涉及旧数据兼容时在迁移中写幂等的 information_schema 检查，并先发布迁移再发布依赖字段的 PHP 代码。

## 5. 配置与密钥

复制 server/.env.example 为 server/.env，至少填写：

~~~dotenv
APP_ENV=development
APP_DEBUG=false
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=peanut_admin
DB_USER=peanut_admin
DB_PASS=按环境配置
DB_PREFIX=pa_
JWT_SECRET=至少32位随机字符串
JWT_EXPIRE=7200
~~~

DB_TYPE、DB_DRIVER、DB_CHARSET 可按 server/config/database.php 的默认值或环境需要设置。管理端锁定参数可通过 ADMIN_TOKEN_EXPIRE、ADMIN_TOKEN_RENEW_BEFORE、ADMIN_PASSWORD_ERROR_TIMES、ADMIN_LOGIN_LOCK_MINUTES 覆盖 server/config/admin_auth.php 的默认值。

网站、支付、存储、渠道、充值和 OAuth 等业务配置主要保存在 pa_config，由管理端设置页面维护；不要把支付私钥、微信 AppSecret、短信密钥、对象存储 Secret、证书内容或 .env 提交到仓库。生产环境使用独立的密钥注入/文件权限方案，并限制 PHP-FPM 用户读取证书目录。配置修改后如遇旧值，使用管理端系统维护页面清理缓存或按发布流程重启 PHP-FPM。

## 6. 从 clone 到可登录开发环境

以下步骤使用仓库当前 README 与脚本中的路径。<仓库地址>、数据库账号和密码是占位符，按环境替换。

~~~bash
git clone <仓库地址> && cd peanut-admin

# 后端配置与依赖
cd server
cp .env.example .env
# 编辑 .env，至少填写 DB_* 和 JWT_SECRET
composer install
cd ..

# 创建空数据库（数据库用户的创建和授权按环境配置）
mysql -u root -p -e "CREATE DATABASE peanut_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 安装基础结构、全部迁移和种子；非空数据库会被拒绝
php server/database/install.php
~~~

安装器会写入 pa_admin 的默认超级管理员：用户名 admin，初始密码 admin123456（密码哈希和盐由 SQL 固定生成）。安装完成后启动后端和管理端：

~~~bash
# 终端 A：ThinkPHP 开发服务器（server 目录）
cd server
php think run --host 0.0.0.0 --port 8000

# 终端 B：web 管理端
cd web
pnpm install
pnpm dev
~~~

打开 http://localhost:5173，登录后立即修改默认密码。PC 会员端和 uni-app H5 可另开终端（命令见下一节）；它们都通过开发代理访问 8000 端口。

已有数据库升级时，不要运行首次安装器，也不要再次把 init.sql 当迁移工具；先备份，再按 server/database/migrations/ 文件名顺序执行所需 SQL。例如：

~~~bash
mysql -u <db-user> -p <db-name> < server/database/migrations/20260802_system_tools_core.sql
~~~

上例文件名仅示范格式，实际应按该环境的迁移记录选择，不要重复执行不兼容的业务变更。

## 7. 客户端开发与构建命令

### web/ 管理端

~~~bash
cd web
pnpm install
pnpm dev                 # Vite，开发代理 /api → 127.0.0.1:8000
pnpm run type:check
pnpm build               # vue-tsc + Vite 生产构建，产物 dist/
pnpm preview             # 构建后本地预览
~~~

接口基址可通过 VITE_API_BASE_URL 覆盖；未设置时使用同源 /api。登录和按钮权限分别由 web/src/store/modules/user/、web/src/router/guard/ 与 web/src/directive/permission/ 协作完成。

### pc/ Nuxt 3 会员端

~~~bash
cd pc
npm install
npm run dev              # Nuxt 开发服务器，脚本固定 --port 3100
npm run typecheck
npm run build
npm run preview
~~~

pc/nuxt.config.ts 的 runtimeConfig.public.apiBase 控制浏览器端 API 基址；开发时 `/api` 代理到 8000。生产使用 `npm run generate` 生成静态 SPA，由 Nginx 挂载在 `/pc/`。

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

### 生产应用仓与容器

生产服务器部署已经存在的应用 release，不在服务器用模板创建新应用。宿主机只安装 Git、Docker Engine 和 Compose；拉取代码、配置 `deploy/production.env` 后执行一次 `docker compose ... up -d --build`。完整首次部署、宝塔反代和 Cloudflare 设置见 `docs/peanut-admin-release-deployment.md`。

生产 Compose 内部运行 MySQL、PHP-FPM、Nginx 和定时任务；Redis 为可选 profile。管理端、PC、H5 和 API 共用一个 Nginx 入口，分别位于 `/admin/`、`/pc/`、`/mobile/` 和 `/api/`。宿主机不安装 Node.js、PHP 或 Composer。

PHP 运行用户必须能写 `server/runtime/` 和 `server/public/storage/`。生产环境使用随机 `JWT_SECRET`、独立数据库密码和 `APP_DEBUG=false`。首次空库由容器入口初始化；已有库升级必须先备份并执行该 release 尚未应用的迁移。

### PC 与 uni-app 产物

管理端 `web/dist/`、UniApp H5 和 Nuxt PC 分别进入 Nginx 镜像的 `server/public/admin/`、`server/public/mobile/`、`server/public/pc/`，不会覆盖后端 public 根文件。小程序产物仍按对应平台单独上传。

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

### 支付

支付入口由 server/app/common/service/payment/PaymentServiceFactory.php 统一选择 wechat 或 alipay。预支付由 PrepayGatewayInterface 实现，回调由 CallbackParserInterface 先验签和标准化，再交给业务 logic（充值结算）更新订单、余额和流水。回调路由是 /api/payment/notify/wechat 与 /api/payment/notify/alipay，必须保持公网 HTTPS、时间戳/证书/公钥配置正确。当前实现只覆盖微信支付和支付宝；新增渠道应增加 gateway、callback parser、配置校验和终端场景，不要绕过工厂直接修改余额。

### 微信 OAuth

OAuth 场景和配置边界在 server/app/api/logic/OAuthLogic.php：mnp 使用 mnp_setting，公众号 oa 使用 oa_setting，PC 开放平台 open_pc 使用 open_platform。身份表、一次性 state 和补全票据由 server/database/migrations/20260802_wechat_oauth.sql 创建；浏览器流程通过 /api/oauth/wechat/begin、callback，小程序通过 mini-program，已登录绑定通过 bind。state、completion ticket 都是一次性并有过期时间，补全接口不能当作会员 token。新增 OAuth 提供商应实现 OAuthTransportInterface，保留 provider/client/subject 隔离和一次性票据语义，不要在控制器直接写身份表。

## 10. 增加一个业务模块

以管理端模块为例，建议按以下顺序提交，且每一步都保持可回滚：

1. **数据设计**：新增 server/database/migrations/YYYYMMDD_<module>.sql，使用 pa_<module> 表名、索引、时间戳和软删除字段；首次安装器会按文件名顺序纳入迁移，不要把同一增量结构重复写进 init.sql。
2. **Model/Enum**：在 server/app/common/model/<module>/ 增加继承 BaseModel 的模型；状态/类型常量放 common/enum，关系和访问器放模型。
3. **Validate/Logic/Service**：分别创建 adminapi/validate/<module>/、adminapi/logic/<module>/；跨模块/外部服务才增加 service。列表分页统一 page_no/page_size 和 dataLists，写操作使用事务，失败通过 BaseLogic::setError() 返回。
4. **Controller 与路由**：创建 adminapi/controller/<module>/ 控制器，继承 BaseAdminController；在 server/route/app.php 的 api/admin 组声明 GET/POST 路由。确认权限标识等于去掉 api/admin/ 后的路径（必要时增加显式 alias）。
5. **菜单与角色**：在迁移/种子中写入 M/C/A 菜单、paths、component 和 perms；登录 root 账号检查动态菜单，普通角色授予最小 A 权限。
6. **客户端**：在 web/src/api/ 增加请求封装，在 web/src/router/routes/modules/ 注册页面并在 web/src/views/ 实现；PC/uni-app 只有确实提供该业务入口时才分别增加 pc/api、pc/pages 或 uniapp/src/api、uniapp/src/pages。
7. **数据范围和审计**：若有部门/租户/所有者隔离，在 logic 的列表、详情和写操作都加显式范围条件；敏感写操作依赖管理端操作日志中间件，并检查日志脱敏字段。

最小验收是：迁移在空库/目标升级库各执行一次；登录后访问列表、详情、新增、编辑、删除和无权限场景；确认统一响应、菜单过滤、runtime 日志和上传文件路径均符合约定。不要用生成器产物替代人工审查路由、权限和数据范围。

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
| 40100 | 请求是否带 Authorization: Bearer；pa_admin_session/会员令牌是否过期；登录 IP 是否变化。 |
| 40300 或菜单为空 | pa_system_menu.is_disable、角色关联和 perms 是否与实际 /api/admin/... 路径一致；非 root 未登记 URI 当前会放行，不能据此判断权限已配置。 |
| 数据库连接失败/表不存在 | .env 的 DB_*、DB_PREFIX 与 MySQL 授权；全新库确认 `php server/database/install.php` 成功，已有库确认目标迁移已执行。 |
| 前端请求 404/CORS | 开发代理是否指向 8000；生产 Nginx 是否把 /api 送到 server/public/index.php；检查各客户端 API base 配置。 |
| 上传/导出失败或文件 404 | PHP-FPM 对 server/runtime/、server/public/storage/ 的写权限；Nginx /storage/ alias；ZipArchive 是否安装。 |
| 支付/OAuth 失败 | 先确认 pa_config 中对应开关、AppID、证书/公钥、回调 HTTPS 和平台白名单；查看 server/runtime/log/，不要关闭验签。 |
| 定时任务不执行 | 系统 cron 是否每分钟调用 php think crontab；pa_crontab.status、表达式、error、数据库 GET_LOCK；命令是否在 server/config/console.php 注册。 |
| PHP 报缺少 cURL/OpenSSL/mbstring | 按本节扩展要求安装到实际 PHP-FPM/CLI 两套运行时，并确认 CLI 与 FPM 版本一致。 |
