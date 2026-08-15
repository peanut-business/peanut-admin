# Peanut Admin 2.0 开发指南

> 本文件随 create-app 进入派生应用。详细、可导航的版本位于
> `docs-site/guide/development.md` 与 `docs-site/guide/module-development.md`。

## 5 分钟速读

- Peanut Core 提供 Account、Tenant、RBAC 和扩展合同；本仓 Host 拥有应用路由、页面、
  安装和产品能力。
- 默认一套部署对应一个应用实例。一个实例可包含多个 Tenant、客户端和 Module；不同
  实例不能共享私有表。
- 管理登录唯一使用 `Account/Credential`，租户成员唯一使用 `TenantMember`；客户侧业务
  会员使用独立 `pa_member`。登录身份、组织成员和业务档案不得混写。
- 2.0 只支持空库安装。`server/database/init.sql` 是 canonical Schema，
  `server/database/migrations/` 只接收基线后的追加式变更。
- 新业务放入独立 Module，通过公开命令、查询 DTO 或已验证的事件合同协作，不直接访问
  其他 Module 私有表。
- DCS 是派生应用，不是 Peanut Admin 内建模块；商品、库存、采购等领域文档由 DCS 仓库拥有。

## 目录和 owner

```text
server/app/adminapi/       管理 API Host
server/app/api/            业务会员和公开 API Host
server/app/platform/       PlatformOperator 与实例内 Tenant 治理
server/app/tenant/         Tenant 管理会话 Host
server/app/common/         应用公共模型、服务和横切适配
server/app/Modules/        应用 Module 后端
server/database/           canonical Schema、安装器和追加 migration
server/route/app.php       HTTP 路由入口
web/                       Vue 管理端
pc/                        Nuxt PC 客户端
uniapp/                    H5/小程序客户端
plugins/ + plugins.lock    Plugin 制品与当前部署锁
docs-site/                 当前公开教程、参考与故障处理
resources/                 项目资源事实源
scripts/                   创建应用、资源门禁和维护命令
```

Core 只拥有通用身份、Tenant、权限和公开 Host 合同；应用拥有业务表、HTTP 装配、菜单、
页面与产品配置；Module 只拥有自己的表、用例、权限和公开合同；Plugin 是一个或多个
Module 的不可变交付制品，不等于 Tenant 开通或成员授权。

## 开发最小路径

1. 在 `server/app/Modules/<Vendor>/<Module>/` 定义 `Domain`、`Application`、`Contracts`、
   `Infrastructure`、`Database/Migrations`、`Resources` 和 `Tests`。
2. Module 表必须有明确 Tenant owner；SQL、唯一键、关联、缓存、文件和任务都保留 Tenant
   维度。请求参数不得覆盖可信 TenantContext。
3. 对外只公开命令接口和只读 DTO。调用方依赖 `Contracts`，由 Host/Provider 绑定实现。
4. 管理端 contribution 放在 `web/src/modules/<module>/`，菜单和权限由 Module Resources
   声明；Plugin 安装、TenantModule 开通、成员 RBAC 是三道独立 Gate。
5. 最低测试覆盖 Tenant A 正常读写、Tenant B 读取/写入同一 ID 被拒绝、Tenant 暂停、
   Module 未开通和伪造资源 ID。

完整纵向示例、目录树、跨 Module 商品入库流程和常见错误见
`docs-site/guide/module-development.md`。API 响应、认证、权限和 Host 入口见
`docs-site/api.md`。

## 资源与运行

Peanut Admin 源仓维护者必须先读 `resources/project-resources.json`，显式选择登记的资源
ID、环境和地址，再连接数据库、启动服务或执行迁移。派生应用生成后必须把该文件替换为
自身版本化资源登记；不得沿用 Peanut Admin 的开发主机、凭据引用或端口。

空库安装的最低输入：

```bash
export DEPLOYMENT_MODE=standalone
export ADMIN_INITIAL_EMAIL='owner@example.com'
export ADMIN_INITIAL_PASSWORD='<至少 12 位且同时包含字母和数字>'
export TENANT_IDENTIFIER_HMAC_KEY='<至少 32 字节稳定随机值>'
export PLATFORM_IDENTIFIER_HMAC_KEY='<另一项至少 32 字节稳定随机值>'

php server/database/install.php
php server/database/migrate.php --current
```

`multi-tenant` 模式另需独立的 `PLATFORM_INITIAL_EMAIL` 与
`PLATFORM_INITIAL_PASSWORD`。2.0 不接受 1.x 数据库、`--adopt-existing`、legacy map 或
scaffold 原地升级。

## 进一步阅读

- 架构、身份、Tenant 与部署：`docs-site/guide/development.md`
- Module 纵向教程：`docs-site/guide/module-development.md`
- API 与扩展参考：`docs-site/api.md`
- 开箱即用能力目录：`docs-site/capabilities.md`
- 部署与故障停止线：`docs-site/deployment.md`
- 管理员操作：`docs/peanut-admin-user-manual.md`
