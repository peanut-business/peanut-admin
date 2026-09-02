---
title: API 与扩展
description: Peanut Admin API、公开包与稳定 Host 覆盖约定。
---

# API 与扩展

Peanut Admin 的 HTTP API、两个公开运行包和 Host 覆盖共同构成扩展面。内部领域目录不是可单独安装的包；应用扩展不能通过修改依赖目录或复制核心源码完成。

## 5 分钟速读

- 普通业务 API 仍按路由、Controller、Application/Logic、Repository 分层。
- 新的独立业务域优先做 Module；Plugin 负责分发，TenantModule 负责租户开通。
- 其他 Module 只能调用 `Contracts/` 中公开的命令或查询，不能直接使用私有 Model/表。
- Plugin lifecycle、Module migration、菜单、权限、设置和前端 contribution 已通过当前 fresh
  基线的组合资格与官方能力强制 Tenant 检查。
- 当前没有通用、已验证的 Outbox/Event Bus；采用异步事件前要由派生应用补齐可靠性合同。

## 响应格式

所有接口通过统一 JSON 响应服务返回：

```json
{
  "code": 20000,
  "msg": "success",
  "data": {}
}
```

列表接口的 `data` 使用 `lists`、`count`、`pageNo`、`pageSize` 字段；详情和配置接口返回 `data` 对象。业务错误为 `40000`，未登录为 `40100`，无权限为 `40300`。

| `code` | 人话解释 | 调用方应该做什么 |
| --- | --- | --- |
| `20000` | 请求成功 | 使用 `data`，不要再按 HTTP body 猜结果 |
| `40000` | 输入或业务状态不允许 | 显示 `msg`，修正输入或刷新业务状态 |
| `40100` | 会话不存在、过期或身份链错误 | 回到对应 Platform/Tenant/member 登录入口 |
| `40300` | 已登录但无权限或数据范围不允许 | 不重试、不隐藏错误；核对 Role、TenantModule 和 Data Scope |

不要为了让前端“继续运行”而把 `40100/40300` 转成成功响应；前端隐藏按钮也不能替代后端拒绝。

## 路由前缀

- `/platformapi/*`：实例内 PlatformOperator 会话与 Tenant/Module 治理，使用独立 audience。
- `/adminapi/tenant/session/*`：Core Tenant 会话登录、选择、切换和退出。
- `/adminapi/*`：管理端接口，统一经过登录、权限和操作日志中间件。
- `/installapi/*`：一次性安装状态与执行入口，安装完成后不承载日常业务。
- `/api/*`：会员端公开接口和需要会员令牌的接口。
- `/api/payment/notify/*`、`/api/wechat/official-account/callback`：第三方回调入口，进入后仍需按业务规则验签。

登录路由不挂鉴权。各客户端直接使用对应 audience 前缀，不再把管理、Platform 或安装入口嵌在 `/api` 下。

## 认证

管理端请求使用：

```http
Authorization: Bearer <token>
```

平台、Tenant 管理端和业务会员的令牌不能混用。管理端只接受原生 `pa_tat_` Tenant
access token，并从已验证会话建立 Account/TenantMember/TenantContext；会话会检查
audience、过期时间、账号、成员和 Tenant 状态。任一主体被停用后，新请求必须被拒绝。

Tenant 文件无论业务上标记为 public 还是 private，都只通过短期签名的
`GET /api/storage/delivery` 读取。服务器在每次请求中同时要求对象仍为 `ready`、签名未过期且
Tenant 仍为 `active`，随后代理 local 或私有 Provider 内容，并返回 `Cache-Control: no-store`。
`/storage/`、Provider 公开 URL 或 CDN 不是支持的交付入口；Tenant 停用后不能等待旧 token、
URL 或缓存自然过期。

## 权限标识

管理端权限标识由请求路径去掉 `adminapi/` 得到。例如：

```text
adminapi/menu/lists -> menu/lists
```

新增管理接口时，应同时登记路由、菜单和按钮/API 权限，并确认角色获得最小必要授权。权限不足时接口返回 `40300`，前端会隐藏没有权限的按钮。

### 首次运行准备清单

`GET /adminapi/readiness/checklist` 是安装后生产准备清单的只读事实入口，对应权限
`readiness/checklist`。它只返回品牌、通知、存储、备份、Worker、当前管理入口域名/TLS
和账户安全的状态、影响、责任方与阻塞性，不返回主机、数据库地址或凭据。

其中 `configured` 只说明本地配置结构完整，`observed` 只说明当前请求看到了对应现象；
两者都不能替代短信真实送达、云存储连通、备份恢复、Worker 心跳或全部域名证书资格。
备份项现在读取应用内 `pa_ops_backup_evidence` 的安全投影：没有已验证 pair 时返回待处理，
存在 pair 但尚未完成新目标恢复验证时仍返回未验证。它不会返回主机、路径、命令或凭据，也
不会把备份文件存在冒充恢复成功。

实例平台的 `GET /platformapi/v1/ops/backups` 返回唯一 Provider、最近 20 个备份任务和最新
已验证 manifest 的安全摘要。`POST /platformapi/v1/ops/tasks/backup` 只接受固定
`provider_key=peanut.paired-db-files` 与 `Idempotency-Key`；任务状态通过
`GET /platformapi/v1/ops/tasks/{task_key}` 读取。三者均要求 Platform 会话及对应 Ops 权限。

### Platform 升级就绪

`GET /platformapi/v1/ops/upgrade-readiness` 返回只读的 source/target Release、migration、
Module、scaffold ownership/conflict、备份、恢复 evidence 和维护窗口检查。接口没有请求参数；
目标只能来自 Deployment 放入固定 `.peanut/upgrade-target/` 的已验证 bundle，不能由浏览器
提供路径、URL、命令、Release key、镜像或凭据。

`preflight.state=ready` 说明静态兼容检查通过；顶层 `state=ready` 还要求匹配当前 Runtime 的
已验证备份、引用同一 backup reference 的恢复能力 evidence 和 active `planned-upgrade`
维护窗口。跨大版本固定返回
`UPGRADE_FRESH_REBUILD_REQUIRED`，不会尝试原地升级。App-owned 文件只投影数量和摘要并保持
不变，冲突原因不返回绝对路径或文件内容。

具有 `platform.ops.upgrade.manage` 的 PlatformOperator 可以向
`POST /platformapi/v1/ops/tasks/upgrade` 提交空 JSON 对象和 `Idempotency-Key`。服务器只消费
当前 Runtime 与 Deployment 已固定的目标 descriptor，按静态预检、新备份、同一备份的隔离
恢复验证、维护、部署、smoke 和恢复指针顺序执行；请求不能提供路径、URL、命令、Release、
镜像、凭据或部署目标。`GET /platformapi/v1/ops/upgrades` 返回最近十个任务的安全步骤投影和
稳定停止码，不返回命令输出或环境细节。

### Platform Module 交付操作

Deployment owner 先在服务器侧受限 inbox 中准备受信 Module archive，并用
`ops-module:request preview/prepare` 固定登记 target、Package 身份和 retire/Purge 确认计划。
具有 `platform.ops.module.manage` 的 PlatformOperator 只能向
`POST /platformapi/v1/ops/tasks/module` 提交返回的 `modreq_*` opaque key 和
`Idempotency-Key`；HTTP 不接受 archive、路径、URL、命令、host、数据库、凭据、确认计划或
目标地址。

登记 worker 通过 `scripts/ops-module-worker --once` 串联新配对备份、隔离恢复验证、维护、
update/retire/Purge、smoke、审计和 recovery pointer。`GET /platformapi/v1/ops/modules`
返回最近十个任务的安全投影，单项继续从 `GET /platformapi/v1/ops/tasks/{task_key}` 读取。
任务失败时维护保持 active，应用 owner 必须先按 recovery pointer 恢复或确认安全退出；浏览器
不能直接执行包、路径或远程命令。

### Platform Provider 生产资格

`GET /platformapi/v1/ops/providers` 要求独立 Platform 会话和 `platform.ops.read`，返回 Payment、
Notification、OAuth 与 Storage 的只读生产资格投影。`configured` 只表示本地配置完整，
`connected`、`callback_verified` 和 `qualified` 必须来自尚未过期且仍匹配当前配置的 evidence。

该 GET 不运行测试连接，不发送消息，也不发起资金动作；真实平台资格必须逐 Provider 独立完成。
配置变化或 evidence 到期会撤销资格。响应仅含 opaque scope key、稳定状态码和安全失败摘要，不含
Tenant ID、配置摘要、秘密、收件人、订单/交易号或原始平台错误。邮件 Provider 尚未实现，固定
返回 `NOT_IMPLEMENTED`。

### Platform 维护窗口

`GET /platformapi/v1/ops/maintenance` 读取当前维护窗口；具有
`platform.ops.maintenance.manage` 的 PlatformOperator 通过 `PUT` 计划窗口，并以
`POST /platformapi/v1/ops/maintenance/{maintenance_key}/close` 关闭窗口。两个写接口均要求
`If-Match: "rev-<revision>"` 和 `Idempotency-Key`。

窗口处于生效时间范围时，后端会拒绝其他 HTTP 写请求并返回
`MAINTENANCE_WRITE_BLOCKED`；只有这两个受 Platform 权限保护的维护控制接口可继续写入。
客户端隐藏按钮不能绕过此规则。

### 新接口完成清单

| 项目 | 完成条件 |
| --- | --- |
| 路由 | HTTP 方法、URI、认证中间件明确 |
| Controller/Validate | 只做输入、场景校验和响应转换 |
| Application/Logic | 事务、状态和失败语义集中 |
| Tenant | 只从可信 TenantContext 取 Tenant，不接受浏览器伪造 |
| Permission | `perms` 与去掉 `adminapi/` 后的 URI 一致 |
| Menu/Button | 只是展示和操作入口，不承担安全边界 |
| 测试 | 成功、无权限、错误 Tenant 和停用状态至少各一条 |

## 敏感字段

操作日志会对 `password`、`token`、`secret`、证书和私钥等敏感字段脱敏。不要在请求、备注、日志导出或工单中提交真实密钥。

## 两个公开运行包

| 生态 | 包与入口 | 使用范围 |
|---|---|---|
| Composer | `peanut-admin/core` | 已采用的认证、权限等产品无关 PHP 契约和原语 |
| npm | `@peanut-admin/admin` | 管理端公共入口，以及 `./client`、`./client/nuxt`、`./client/uniapp` 无 UI 客户端入口 |

应用只通过 registry 与公开入口安装这两个包。核心仓的 Settings、File、Task、Notification 等目录是包内模块，不是可独立安装的 package；会员/财务、内容/装修、支付/OAuth/渠道继续由应用 Module 拥有。

## Host 与覆盖

- PHP 覆盖通过公开 interface、应用实现和显式 Provider 装配；启动时校验类型、重复 key 与版本约束。
- Web 覆盖通过稳定 key 和 `peanut.overrides.ts` 注册；Vite alias 不能代替业务覆盖协议。
- PC 与 UniApp 从同一无 UI client 注入 transport、token storage、导航和反馈适配器；页面与平台 SDK 留在对应端。
- 禁止修改 `vendor/`、`node_modules/`，禁止复制核心类或增加双路由、双字段、双实现兼容层。

新增覆盖点属于公共 API，需要明确 owner、版本约束、默认实现、错误边界和最小测试；没有第二消费者和稳定发布节奏时，不拆第三个公开包。

## Module、Plugin 与 Host

```text
plugins.lock 固定不可变 Plugin
  -> Host 预检并安装 Module migration/资源
      -> PlatformOperator 为 Tenant 开通 TenantModule
          -> TenantMember 获得功能与数据权限
              -> 前后端 contribution 才可执行
```

这四层状态互不替代。Plugin active 不会自动给所有 Tenant 开通功能，菜单可见也不会替代
后端权限和数据范围检查。

### 当前真实纵向样例

源码仓 `fixture.delivery-record` 证明以下路径可工作，但它被 scaffold inventory 排除，正式
create-app 生成的是空 `plugins.lock`：

```text
plugins/fixture.delivery-record/plugin.json
server/app/Modules/Fixture/DeliveryRecord/
  module.json
  ModuleProvider.php
  Contracts/DeliveryRecordCommands.php
  Application/DeliveryRecordService.php
  Infrastructure/{Authorization,Persistence}/
  Database/Migrations/
  Resources/{permissions,menus,setting-definitions}.json
web/src/modules/fixture-delivery-record/
  contribution.ts
  views/index.vue
```

`module.json` 固定 Module key、依赖、owned tables、公开合同和停用行为；`plugin.json` 与
`plugins.lock` 固定制品来源和前后端摘要。Module migration 只拥有声明的表，Repository
使用可信 `TenantContext` 写入和查询 `tenant_id`。

当前管理端 HTTP 路由仍由 `server/route/app.php` 显式登记。Module 菜单或前端路由不会
自动生成一个后端 API；新增接口必须同时登记路由、权限、Controller/Application Service
和测试。

### 同步命令与查询

跨 Module 的稳定依赖放在被调用方 `Contracts/`：

```php
interface InventoryCommands
{
    public function receive(TenantContext $context, ReceiveStock $command): StockReceipt;
}
```

上例是派生应用的推荐形态，不是 Peanut 当前内建 Inventory 类。调用方只传业务 DTO 和
可信 TenantContext；被调用方拥有事务、表和失败语义。调用方可以保存必要快照，但不能
直接读写 Inventory 私有表。跨 Module 列表由应用查询编排层组合公开 DTO，不建立隐藏的
跨表 JOIN 合同。

| 选择 | 什么时候用 | 调用方能保存什么 | 禁止做什么 |
| --- | --- | --- | --- |
| 同步命令 | 本次操作必须立即成功或失败 | 返回 ID、业务快照、幂等键 | 直接更新被调用 Module 的表 |
| 只读查询 | 页面或校验需要当前业务事实 | 公开 DTO | 返回 ORM Model、PDO 或私有字段 |
| 领域事件 | 允许最终一致且已有可靠投递 Runtime | 事件 ID、版本和最小快照 | 把内存事件当作可靠消息 |

### 事件与失败处理

当前 fixture 的 `contracts.events` 为空，Peanut Host 没有已验证的通用事件投递 Runtime。
派生应用采用事件前必须明确：

- 事件 schema/version 和最小数据；
- Outbox 与业务写入同事务；
- 至少一次投递下的消费者幂等；
- 重试、死信、审计和告警；
- owner Tenant、participant Tenant 和授权语义。

缺少这些条件时，使用显式同步合同，不要把数据库轮询或内存事件包装成可靠领域事件。

### DCS 采用边界

DCS 如获批采用 Peanut Admin，应作为由脚手架派生、独立维护的应用。Peanut 只提供
Module、Tenant、权限、审计和 Host 扩展合同；Party、Store、Warehouse、Supplier、
Product、Pricing、Inventory、Procurement 与 Trade 的接口、表和事件，必须在 DCS 仓
独立冻结并另行获批后实现。Peanut 中的 Product-only `CONDITIONAL` 记录只是采用边界，
不是 DCS 项目或 Runtime 已实现的证明。

详细目录、安装命令、最小测试和常见错误见
[Module 开发教程](/guide/module-development)。

## 外部回调停止线

支付、公众号和 OAuth 回调必须先验签/校验 state，再进入产品状态机。仓库测试只能证明签名、幂等和回跳合同；真实商户、微信平台域名/白名单、证书轮换和资金到账必须在部署环境完成低风险 smoke 后才能宣称可用。
