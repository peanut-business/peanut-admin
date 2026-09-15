# Application 与 Module 代码规范

本文是 Application 与 Module 目录和代码职责的唯一规范入口，遵循最新有效用户决定及
`AGENT_EXECUTION_RULES.md` §6 的适用架构要求。本文先冻结目标；现行 `ModuleHostLayout`、
`module:create`、源码、manifest、autoload、加载与打包工具仍反映旧结构，须在 S2 完成影响映射后由
S3 同批切换，不能把未完成迁移写成已落地事实。

## 1. 目录、namespace 与 key

- 后端根目录：`server/app/modules/<vendor>/<module>/`；
- PHP namespace：`app\\modules\\<vendor>\\<module>`，逐段与小写目录一致；
- 前端根目录：`web/src/modules/<module-slug>/`；
- Module 业务服务目录：`server/app/modules/<vendor>/<module>/services/`；
- Module key 使用小写命名空间，如 `official.rich-text`。

PHP 业务目录统一小写，多词目录使用 `snake_case`；类文件名仍按类名大小写。Module key 必须经唯一规范实现
派生目录、namespace 与前端 slug。S3 必须同时更新 Core 与 App 的派生、autoload、manifest、发现、加载、
预检、打包和路径拒绝规则，不保留 `Modules/` 与 `modules/` 双根，也不新增第二套 App 路径算法。
`web/src/modules/<module-slug>/` 与插件 `<key>` 继续遵循各自现有语言和身份规则。

## 2. ThinkPHP 原生应用层

- Controller 负责 HTTP 映射，Module `services/` 负责用例与事务，Model/Query/Scope 负责数据访问；
- Application Service 直接使用 ThinkPHP Model、Query 和 Scope 是冻结的正常实现；
- 运行时依赖由组合根构造注入，业务方法内不得使用 `app()`、Facade 或零参数静态工厂定位依赖；
- 不得仅为了隔离框架而新增 Interface、Port、Repository Contract、Persistence Adapter、镜像实现或
  兼容桥；
- `contracts/` 只在存在真实跨 Module 消费者时定义稳定业务合同，且不能暴露 PDO、ORM Model 或
  Module-owned 表名。
- 新 Module 不生成 `application/`。Application 与 Module 的业务服务统一进入小写复数 `services/`；
  `controller`、`validate`、`contracts`、`model`、`infrastructure`、`database/migrations` 和 `resources`
  同样使用小写目录。非业务类按合同、值对象、策略等真实职责归属。既有业务服务中的
  `Application/application` 属于已确认整改范围，按原 owner
  认领的完整业务切片退出；每一切片同步更新真实调用、namespace、装配、生成器及受影响路径检查，不做
  全量盲改，也不新增兼容桥。这里保留 Application Service 作为语义术语。

## 3. Tenant、权限与失败

- Tenant-owned Model 继承 `TenantOwnedModel`，由唯一全局 TenantScope 过滤；
- 禁止手写 `where('tenant_id', ...)`、`forTenant()` 和普通业务代码绕过 Scope；
- 缺少可信 TenantContext 必须 fail-closed；
- 受保护路由同时经过登录、TenantModule 生命周期和 RBAC 权限中间件；
- 业务失败抛出项目既有的稳定异常，由宿主统一映射响应，不返回代表失败的松散数组。

## 4. 路由与前端贡献

Module 的目标路由入口是 `route/app.php`，它是可执行 ThinkPHP 路由，不是纯数组描述。当前源码仍使用
`Http/routes.php` 和 `Http/Controller/`；这属于 S2 映射、S3 同批切换范围，不是目标结构的例外。
路由可使用点分业务动作，例如 `/adminapi/official.article.list`；路径不要求与 Controller 的物理目录逐段同名。
受保护路由仍须经过登录、TenantModule 生命周期和 RBAC 链。前端由
`contribution.ts` 声明路由和权限，并由 `module.json.frontend.entry` 固定其 key 派生路径。

## 5. 存量退出、验证与发布

本规范的完成条件是规则、生产调用、新生成入口、相关路径检查与开发说明一致；按业务切片实施不缩小既有服务目录统一目标，
只改新生成器或只完成某个切片，都不能关闭整体目录整改；已确认范围的存量业务服务与调用未全部退出旧路径前，整体保持未完成。
生成器改好不等于存量迁移完成，旧业务目录剩余项沿用现有问题登记，不建立第二套总账。Peanut 本仓开发期间只做直接必要的语法/类型/构建/启动与实际操作；
自动测试须用户明确授权后运行，未授权不等于普通开发 blocked。发布资格测试和人工 Gate 仍保留。

当前 `module:check` 仍按旧目录实现，S3 应随生成、加载与打包合同切换；它只负责作者静态预检。真实行为、Tenant/RBAC、migration、浏览器和外部 Provider 仍由
对应聚焦测试负责。开发期自动测试仅在用户明确授权后运行；已存在或已授权的测试不得用输出 `PASSED`
后提前退出的占位实现冒充通过。Module 交付制品、状态和门禁见
[Module 发布与制品合同](../module-publication-contract.md)。
