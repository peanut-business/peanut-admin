# 用 Module 开发独立业务

> 本文是 Peanut Admin 模块开发的官方指南。本文已经过彻底重构，去除了历史中的 DDD 与代码分裂包袱。请仔细阅读并遵循本文所描述的“全栈自闭环”与“实用主义”心智模型。

## 5 分钟速读

Peanut Admin 把四件事分开：

- **Module** 是业务代码、前端资产、路由和数据的**全栈自闭环** owner。
- **Plugin** 是一个或多个 Module 的不可变安装包（通常表现为 `modules/<plugin-name>` 目录）。
- **Host**（宿主应用如 `adminapi`）负责校验 Plugin、执行 Module migration、并在运行时**强行接管鉴权**。
- **TenantModule** 表示某个 Tenant 是否开通 Module；成员权限是另一层判断。

一个功能真正可用，需要同时满足：

```text
Plugin active
  -> TenantModule enabled
      -> TenantMember 拥有功能权限和数据权限
          -> Host 中间件拦截并组装 TenantContext
              -> 放行到 Module Controller
```

## 真实目录与所有权（全栈自闭环）

一个标准的业务模块（如 `official-article`）物理布局如下：

```text
modules/official-article/
├── module.json                 # 唯一身份、依赖、资源声明
├── server/                     # 后端自闭环代码
│   ├── routes.php              # 声明式路由映射（纯数组，不含鉴权拦截代码）
│   ├── controller/             # 按宿主端口划分，如 adminapi/, api/
│   ├── service/                # 业务逻辑、用例、事务边界
│   ├── model/                  # 继承 TenantOwnedModel 等基类的模型
│   ├── validate/               # 请求白名单与格式验证
│   └── database/migrations/    # 模块自有表的结构变更
└── web/                        # 前端独立页面与组件
    ├── package.json
    └── src/
        ├── api/                # 本模块专有的前端 API 定义
        └── views/              # Vue 页面组件
```

> **绝对禁止**在模块中创建 `Application/`、`Domain/`、`Infrastructure/` 这类带有深重 DDD 历史包袱的目录。统一回归 `controller -> service -> model` 的极简心智。

## 开发与心智规范

为了保证所有模块的开发体验一致，所有开发者必须遵循《[核心代码规范与认知模型统一指南](architecture/application-module-blueprint/coding-standards.md)》中的黄金法则：

1. **命名空间与物理目录全小写**：
   - 文件目录：`controller/adminapi/`
   - 命名空间：`namespace modules\official_article\controller\adminapi;`
2. **鉴权分离**：
   - `routes.php` 只是声明 `'permission' => 'official.article.list'`，绝不在此写 `LoginMiddleware`。
   - 宿主在启动时会自动读取并包裹拦截器。
3. **数据隔离透明化**：
   - 模块的模型直接继承 `app\common\model\TenantOwnedModel`。
   - 模块开发者无需在代码中手动拼接 `WHERE tenant_id = ?`，底层 Global Scope 会全自动执行“Fail-Closed”拦截。
4. **服务异常阻断**：
   - 业务校验失败时，直接在 `service/` 层抛出：`throw new BusinessException('库存不足');`。
   - 绝不返回错误数组让 Controller 去 `if-else`。
5. **URL 绝对映射**：
   - 接口路径 `/[端]/[模块名]/[业务名词]/[动作]` 必须精确映射物理目录。
   - 例：`/adminapi/official_article/article/add` -> `modules/official-article/server/controller/adminapi/ArticleController.php@add`。

## 安装与打包规则

打包与分发命令仍然使用标准的 CLI 工具：
```bash
php think module:pack <module.key>
```
安装包不会自动开通 `TenantModule`，也不会给租户成员授予 RBAC 权限。这一切都由宿主和租户管理员在运行时决定。
