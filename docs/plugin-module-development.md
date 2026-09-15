# 用 Module 开发独立业务

> 本文描述当前统一的 Module 结构。源码、生成器、autoload、manifest、加载与打包工具已经按同一规则切换。发布状态和交付门禁见
> 公开使用路径见 [Application 与 Module 生命周期](/guide/application-module-lifecycle)；维护者的完整状态机与制品门禁见仓库内 `docs/architecture/module-publication-contract.md`。

## 1. Module、Plugin 与租户授权

Peanut Admin 把四层事实分开：

- **Module** 拥有业务代码、数据表、权限、菜单、路由和前端贡献；
- **Plugin Package** 是一个或多个 Module 的不可变交付包；
- **TenantModule** 决定租户是否开通 Module；
- **RBAC** 决定租户成员是否可以执行具体能力。

安装 Package 不会自动开通 TenantModule，也不会授予 RBAC。受保护 HTTP 路由必须经过宿主登录、
Module 生命周期和权限中间件，不能把 `module.json` 中的权限声明当作运行时鉴权。

## 2. 目标目录

Module 的三个根目录必须由同一个 key 派生。以 `official.article` 为例：

```text
server/app/modules/official/article/
├── module.json
├── ModuleProvider.php
├── route/app.php
├── controller/
├── services/
├── validate/
├── contracts/                 # 有真实跨 Module 消费者时
├── infrastructure/            # 有外部系统适配时
├── model/
├── resources/
├── database/migrations/
└── composer.json

web/src/modules/official-article/
├── contribution.ts
├── api.ts
├── views/
└── package.json

```

Plugin 身份另存于 `plugins/<module.key>/plugin.json`，bundled 部署身份由根目录 `plugins.lock`
固定。PHP namespace 为 `app\\modules\\official\\article`，逐段匹配小写目录；多词 PHP 目录使用
`snake_case`，类名和类文件名仍按 PHP 类命名规则。`module:create`、`module:check`、
`module:pack` 与 Runtime 使用这套目录和 namespace 规则。

## 3. 实现边界

- Controller 只负责 HTTP 输入输出；Module 的 `services/` 承载用例和事务边界；Model 继承适用的
  `TenantOwnedModel` 并依赖全局 TenantScope。
- 应用服务直接使用 ThinkPHP Model、Query 和 Scope，并通过组合根完成构造函数注入。不得仅为了
  隔离 ThinkPHP 而新增 Repository、Port、Persistence Adapter 或兼容桥。
- 新 Module 只使用复数 `services/`；不要由生成器创建 `application/`，也不要手工以 PDO 或
  Factory 作为业务装配替代品。Peanut Admin 源仓业务服务已经按职责迁入统一目录，完成条件见
  `docs/architecture/application-module-blueprint/coding-standards.md`。官方、应用私有和第三方 Module 使用同一目录与职责规则。
- `contracts/` 只用于 Module 对外公开且确有跨 Module 消费者的稳定命令/查询合同；不得把每个内部
  Service 镜像成 Interface。
- `infrastructure/` 只容纳确有必要的外部系统或技术适配。Module 自有表仍由本 Module 的 Model/Scope
  管理，不通过通用 Repository 包装。
- 业务代码不得手写 `where('tenant_id', ...)`、`forTenant()` 或绕过全局 Scope；可信 Tenant 上下文
  缺失时必须 fail-closed。
- `route/app.php` 是可执行 ThinkPHP 路由文件，并负责挂载宿主要求的中间件。当前管理端接口形如
  `/adminapi/official.article.list`；URL 不要求与物理目录逐段同名。

## 4. 开发工作流

```bash
cd server
php think module:create <module.key> [--vendor=<Vendor>]
php think module:check <module.key>
php think module:sync --module=<module.key>
```

这组命令使用本节统一目录合同。不要建立第二棵 Module 根或用兼容 autoload 同时加载两套路径。

`module:check` 是只读作者预检，覆盖 manifest、版本、依赖、权限、菜单、migration、前端入口和
Package。它不是业务行为、Tenant 隔离、浏览器或厂商集成测试的替代品。

实现完成后按仓库内 `docs/architecture/application-module-blueprint/coding-standards.md` 的开发检查策略核对；
发布前的实际质量要求仍须满足。不得把测试正文替换成输出 `PASSED` 后 `exit(0)` 的占位脚本。

## 5. 打包与交付

```bash
cd server
php think module:pack <module.key> --output=<absolute-path>/<module>-<version>.tar
```

用于跨环境交付时应同时提供 Ed25519 签名选项，并通过可信渠道传递 archive SHA-256、公钥身份和
兼容性信息。`composer.json` 与前端 `package.json` 是 Package 内的组件身份，不代表已经分别发布到
Composer/npm Registry。完整状态、资格和 Rich Text 当前结论见发布合同。
