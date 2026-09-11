# Application 与 Module 代码规范

本规范以当前 `ModuleHostLayout`、`module:create` 生成器、现行 Module 源码和
`AGENT_EXECUTION_RULES.md` §6 为准。它约束目标实现，不把未完成的目录迁移写成已落地事实。

## 1. 目录、namespace 与 key

- 后端根目录：`server/app/Modules/<Vendor>/<Module>/`；
- PHP namespace：`app\\Modules\\<Vendor>\\<Module>`，遵循 PSR-4 大小写；
- 前端根目录：`web/src/modules/<module-slug>/`；
- 测试根目录：`server/tests/Modules/<Vendor>/<Module>/`；
- Module 业务服务目录：`server/app/Modules/<Vendor>/<Module>/Services/`；
- Module key 使用小写命名空间，如 `official.rich-text`。

目录只由 key 通过 `ModuleHostLayout` 派生。不要手写第二套 `modules/<slug>/{server,web}` 布局，
也不要把 PHP namespace 全部改为小写；这两种写法都与当前 autoload 和生成器合同不一致。

## 2. ThinkPHP 原生应用层

- Controller 负责 HTTP 映射，Module `Services/` 负责用例与事务，Model/Query/Scope 负责数据访问；
- Application Service 直接使用 ThinkPHP Model、Query 和 Scope 是冻结的正常实现；
- 运行时依赖由组合根构造注入，业务方法内不得使用 `app()`、Facade 或零参数静态工厂定位依赖；
- 不得仅为了隔离框架而新增 Interface、Port、Repository Contract、Persistence Adapter、镜像实现或
  兼容桥；
- `Contracts/` 只在存在真实跨 Module 消费者时定义稳定业务合同，且不能暴露 PDO、ORM Model 或
  Module-owned 表名。
- 新 Module 不生成 `Application/`。该目录名不是迁移现有 Module 的指令；普通 App 服务仍采用
  小写 `services/` 目录。

## 3. Tenant、权限与失败

- Tenant-owned Model 继承 `TenantOwnedModel`，由唯一全局 TenantScope 过滤；
- 禁止手写 `where('tenant_id', ...)`、`forTenant()` 和普通业务代码绕过 Scope；
- 缺少可信 TenantContext 必须 fail-closed；
- 受保护路由同时经过登录、TenantModule 生命周期和 RBAC 权限中间件；
- 业务失败抛出项目既有的稳定异常，由宿主统一映射响应，不返回代表失败的松散数组。

## 4. 路由与前端贡献

`Http/routes.php` 是可执行 ThinkPHP 路由，不是纯数组描述。当前路由可使用点分业务动作，例如
`/adminapi/official.article.list`；路径不要求与 Controller 的物理目录逐段同名。前端由
`contribution.ts` 声明路由和权限，并由 `module.json.frontend.entry` 固定其 key 派生路径。

## 5. 验证与发布

`module:check` 只负责作者静态预检；真实行为、Tenant/RBAC、migration、浏览器和外部 Provider 仍由
对应聚焦测试负责。任何测试跳过必须以明确的 stopped/blocked 证据出现，不能输出 `PASSED` 后提前
退出。Module 交付制品、状态和门禁见
[Module 发布与制品合同](../module-publication-contract.md)。
