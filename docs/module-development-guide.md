# 模块开发指南

本指南是 Module 作者的短入口；当前真实布局、架构纪律和完整工作流见
[用 Module 开发独立业务](plugin-module-development.md)，交付状态见
[Module 发布与制品合同](architecture/module-publication-contract.md)。

## 新建与检查

在 `server/` 目录执行：

```bash
php think module:create <module.key> [--vendor=<Vendor>]
php think module:check <module.key>
```

生成器会创建：

- `server/app/Modules/<Vendor>/<Module>/` 后端、manifest 和 migration 骨架；
- `web/src/modules/<module-slug>/` 前端贡献；
- `server/tests/Modules/<Vendor>/<Module>/` Tenant 安全测试骨架。

它不会创建 `modules/<slug>/{server,web}`，也不会生成全小写 PHP namespace。后端模块使用
`app\\Modules\\<Vendor>\\<Module>` PSR-4 namespace。

## 开发约束

- `module.json` 是 Module 业务身份、依赖、资源和 owned tables 的事实源；
- `Http/routes.php` 是 ThinkPHP 路由并必须挂载宿主要求的认证、Module 和权限中间件；
- Tenant-owned Model 依赖全局 TenantScope，业务代码禁止手写 tenant 过滤或绕过 Scope；
- Application Service 直接使用 ThinkPHP Model/Query/Scope 和构造函数注入，不新增仅用于隔离框架
  的 Repository/Port/Adapter；
- `Contracts/` 只承载真实的跨 Module 公共合同。

## 同步、测试与打包

```bash
php think module:sync --module=<module.key>
php think module:check <module.key>
php think module:pack <module.key> --output=<absolute-path>/<module>-<version>.tar
```

`module:check` 只是作者静态预检。发布前仍需运行该 Module 的真实行为、Tenant/RBAC、migration、
前端或浏览器测试，并按交付通道满足签名、摘要和资格要求。测试占位脚本不能作为通过证据。
