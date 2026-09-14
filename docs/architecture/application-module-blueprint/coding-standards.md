# Application 与 Module 代码规范

本文是 Application 与 Module 目录和代码职责的统一规范入口，遵循最新有效用户决定及
`AGENT_EXECUTION_RULES.md` §6 的适用架构要求。`ModuleHostLayout`、`module:create` 生成器和
现行源码用于核对实现情况；存量实现不能反向改变已确认目标，也不能把未完成的迁移写成已落地事实。

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
- 新 Module 不生成 `Application/`。Module 业务服务统一进入大写复数 `Services/`；普通 App 的业务服务
  统一进入小写复数 `services/`。非业务类按合同、值对象、策略等真实职责归属，保持所属 App 或 Module
  的目录及 namespace 大小写约定。既有业务服务中的 `Application/application` 属于已确认整改范围，按原 owner
  认领的完整业务切片退出；每一切片同步更新真实调用、namespace、装配、生成器及受影响路径检查，不做
  全量盲改，也不新增兼容桥。这里保留 Application Service 作为语义术语。

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

## 5. 存量退出、验证与发布

本规范的完成条件是规则、生产调用、新生成入口、相关路径检查与开发说明一致；按业务切片实施不缩小既有服务目录统一目标，
只改新生成器或只完成某个切片，都不能关闭整体目录整改；已确认范围的存量业务服务与调用未全部退出旧路径前，整体保持未完成。
生成器改好不等于存量迁移完成，旧业务目录剩余项沿用现有问题登记，不建立第二套总账。Peanut 本仓开发期间只做直接必要的语法/类型/构建/启动与实际操作；
自动测试须用户明确授权后运行，未授权不等于普通开发 blocked。发布资格测试和人工 Gate 仍保留。

`module:check` 只负责作者静态预检；真实行为、Tenant/RBAC、migration、浏览器和外部 Provider 仍由
对应聚焦测试负责。开发期自动测试仅在用户明确授权后运行；已存在或已授权的测试不得用输出 `PASSED`
后提前退出的占位实现冒充通过。Module 交付制品、状态和门禁见
[Module 发布与制品合同](../module-publication-contract.md)。
