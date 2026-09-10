# 用 Module 开发独立业务

> 本文描述当前源码与生成器真实支持的 Module 结构。发布状态和交付门禁见
> 公开使用路径见 [Application 与 Module 生命周期](/guide/application-module-lifecycle)；维护者的完整状态机与制品门禁见仓库内 `docs/architecture/module-publication-contract.md`。

## 1. Module、Plugin 与租户授权

Peanut Admin 把四层事实分开：

- **Module** 拥有业务代码、数据表、权限、菜单、路由和前端贡献；
- **Plugin Package** 是一个或多个 Module 的不可变交付包；
- **TenantModule** 决定租户是否开通 Module；
- **RBAC** 决定租户成员是否可以执行具体能力。

安装 Package 不会自动开通 TenantModule，也不会授予 RBAC。受保护 HTTP 路由必须经过宿主登录、
Module 生命周期和权限中间件，不能把 `module.json` 中的权限声明当作运行时鉴权。

## 2. 当前真实目录

`module:create` 使用 `ModuleHostLayout` 生成三个 key 派生根目录。以 `official.article` 为例：

```text
server/app/Modules/Official/Article/
├── module.json
├── ModuleProvider.php
├── Contracts/
├── Http/routes.php
├── Http/Controller/
├── Services/
├── Infrastructure/Persistence/
├── Model/
├── Resources/
├── Database/Migrations/
└── composer.json

web/src/modules/official-article/
├── contribution.ts
├── api.ts
├── views/
└── package.json

server/tests/Modules/<Vendor>/<Module>/
├── TenantSecurityDriver.php
└── TenantSecurityTest.php
```

Plugin 身份另存于 `plugins/<module.key>/plugin.json`，bundled 部署身份由根目录 `plugins.lock`
固定。当前 Runtime 不使用 `modules/<slug>/{server,web}` 布局，也不使用全小写 PHP namespace。

## 3. 实现边界

- Controller 只负责 HTTP 输入输出；Module 的 `Services/` 承载用例和事务边界；Model 继承适用的
  `TenantOwnedModel` 并依赖全局 TenantScope。
- 应用服务直接使用 ThinkPHP Model、Query 和 Scope，并通过组合根完成构造函数注入。不得仅为了
  隔离 ThinkPHP 而新增 Repository、Port、Persistence Adapter 或兼容桥。
- 新 Module 只使用复数 `Services/`；不要由生成器创建 `Application/`，也不要手工以 PDO 或
  Factory 作为业务装配替代品。既有 Module 的目录不因本规则被批量迁移。
- `Contracts/` 只用于 Module 对外公开且确有跨 Module 消费者的稳定命令/查询合同；不得把每个内部
  Service 镜像成 Interface。
- `Infrastructure/` 只容纳确有必要的外部系统或技术适配。Module 自有表仍由本 Module 的 Model/Scope
  管理，不通过通用 Repository 包装。
- 业务代码不得手写 `where('tenant_id', ...)`、`forTenant()` 或绕过全局 Scope；可信 Tenant 上下文
  缺失时必须 fail-closed。
- `Http/routes.php` 是可执行的 ThinkPHP 路由文件，并负责挂载宿主要求的中间件。当前管理端接口形如
  `/adminapi/official.article.list`；URL 不要求与物理目录逐段同名。

## 4. 开发工作流

```bash
cd server
php think module:create <module.key> [--vendor=<Vendor>]
php think module:check <module.key>
php think module:sync --module=<module.key>
```

`module:check` 是只读作者预检，覆盖 manifest、版本、依赖、权限、菜单、migration、前端入口和
Package。它不是业务行为、Tenant 隔离、浏览器或厂商集成测试的替代品。

实现完成后，运行该 Module 的真实聚焦测试；不得把测试正文替换成输出 `PASSED` 后 `exit(0)` 的
占位脚本。需要暂缓的 Gate 应记录为明确停止线，不能伪装成通过。

## 5. 打包与交付

```bash
cd server
php think module:pack <module.key> --output=<absolute-path>/<module>-<version>.tar
```

用于跨环境交付时应同时提供 Ed25519 签名选项，并通过可信渠道传递 archive SHA-256、公钥身份和
兼容性信息。`composer.json` 与前端 `package.json` 是 Package 内的组件身份，不代表已经分别发布到
Composer/npm Registry。完整状态、资格和 Rich Text 当前结论见发布合同。
