# 用 Module 开发独立业务

Peanut Admin 把四件事分开：

- **Module** 是业务代码的家。控制器、领域逻辑、数据表迁移、权限、菜单、设置和前端页面都放在同一个 Module 目录中。
- **Plugin** 是安装包。一个 Plugin 可以携带一个或多个 Module，但它不拥有租户授权。
- **Host** 是脚手架本身。它只负责校验安装包、执行迁移、登记资源，并在构建时汇总前端入口。
- **TenantModule** 是某个租户的开通状态。安装 Plugin 不会替任何租户开通业务，也不会给成员授权。

这意味着安装不会把 Controller、Model、Vue 页面复制到公共目录。PHP 代码保留在
`server/app/Modules/<Module>/`，前端代码保留在 `web/src/modules/<module-slug>/`；Host
只读取不可变的 `plugins.lock` 和静态 contribution。以后即使提供“应用市场”，市场也只是
下载和分发 Plugin 的渠道，不会改变 Module 的业务边界和 Host 的安全规则。

## 最小目录

```text
plugins.lock
plugins/<plugin-key>/plugin.json
server/app/Modules/<Vendor>/<Module>/
  module.json
  ModuleProvider.php
  Database/Migrations/*.sql
  Resources/permissions.json
  Resources/menus.json
  Resources/settings.json
web/src/modules/<module-slug>/
  contribution.ts
  views/*.vue
```

`plugin.json` 描述安装包及 Composer、npm、前端和 Module 身份；`module.json` 描述业务
能力、依赖、数据所有权、权限和菜单。`plugins.lock` 固定实际部署的版本、来源和 SHA-256。
Host 只接受 lock 中的内容，文件被改写、依赖缺失或前后端身份不一致都会在迁移前拒绝。
兼容期内，已有 `PEANUT_MODULE_ROOTS` 会和 lock 中的 Module roots 合并编译；旧模块不要求
随本功能顺手重构，后续可逐个包装成 Plugin 制品。

## 日常操作

```bash
php think plugin:install fixture.delivery-record
php think plugin:upgrade fixture.delivery-record --dry-run
php think plugin:rollback fixture.delivery-record
php think plugin:uninstall fixture.delivery-record
```

安装顺序是：预检 → 标记 installing → 执行 Module 自有的追加式迁移 → 登记权限、菜单和
设置 → 全部成功后 active。任何一步失败都不会部分激活。重复安装同一不可变身份是幂等的；
已执行迁移的内容或校验和发生变化会被拒绝。

升级命令带 `--dry-run` 时只输出计划；不带该选项才会执行升级。回滚只输出可执行计划，因为追加式迁移和业务数据不能靠猜测
倒放。卸载默认保留数据，并且只在没有租户仍开通该 Module 时退役代码；删除业务表不是
默认卸载动作。

## 租户开通和成员授权

Plugin 安装完成后，平台管理员仍需显式启用 TenantModule。成员还必须拥有 Module 声明的
权限，前端路由才可见。三种状态互不替代：

1. Plugin active：这台部署有这份代码。
2. TenantModule enabled：这个租户购买或开通了业务。
3. Member authorized：当前成员可以使用对应操作。

fixture `fixture.delivery-record` 仅用于证明这条纵向链路，不代表 DCS Product Module 已获
采用批准，也不会启动或修改 DCS。
