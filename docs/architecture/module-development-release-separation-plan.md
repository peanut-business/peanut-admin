# Module 独立开发与能力包发布分离方案（评审稿）

> 状态：评审稿，未实施
>
> 目的：解决前端项目、后端 ThinkPHP 项目独立开发时，开发人员被迫理解
> `plugin.json`、`plugins.lock`、中央路由、部署登记和 TenantModule 的问题。
>
> 本文只提出结构和迁移方案，不修改运行时代码、Schema、数据库或发布流程。

## 1. 结论摘要

Peanut 当前把两类问题混在了一起：

1. 前端和后端如何独立开发、实时联调；
2. 多个 Module 如何组成发布包、安装到实例并为 Tenant 开通。

这两类问题应分成不同平面：

```text
开发平面
  前端项目 + 后端项目
  只维护各自 Module 代码和共享业务合同

集成平面
  校验前后端 Module key、API、权限和版本是否匹配

发布平面
  自动生成能力包（当前技术名仍为 Plugin）和 plugins.lock

运行平面
  安装能力包，写入部署状态，再由 Platform 控制面为 Tenant 开通 Module
```

目标不是删除 Module/Plugin 生命周期，而是让普通开发人员不需要直接理解和维护发布层文件。

推荐中文术语：

| 当前技术名 | 业务中文名 | 责任 |
| --- | --- | --- |
| Module | 业务模块 | 一项业务能力及其代码、数据、规则、权限和公开合同 |
| Plugin | 能力包 | 一个或多个 Module 的不可变发布和安装容器 |
| TenantModule | 租户模块开通 | 某个 Tenant 是否启用某个已部署 Module |
| Provider/Driver | 渠道适配器 | 对接微信、支付宝、短信、COS 等外部系统的实现 |

当前版本不直接重命名 `plugin_key`、`plugins.lock`、`pa_plugin_*` 或 `plugin:*` 命令；先在业务文档和平台 UI 中使用“能力包（Plugin）”。

## 2. 当前代码事实

### 2.1 `plugin.json` 不是全平台模块总表

一个 `plugins/<plugin-key>/plugin.json` 描述一个能力包。Schema 要求其 `modules` 为至少一个元素的数组，因此一个能力包可以包含多个 Module：

- [plugin.schema.json](/Users/xing/.codex/worktrees/e147/peanut-admin/server/resources/schemas/plugin.schema.json:63)
- [PluginArtifactWriter.php](/Users/xing/.codex/worktrees/e147/peanut-admin/server/app/platform/service/plugin/PluginArtifactWriter.php:41)

当前官方能力多数采用一对一关系，例如：

```text
plugins/official.payment/plugin.json
  plugin key: official.payment
  module key: official.payment
```

这只是当前交付方式，不代表 Plugin key 和 Module key 必须相同。未来可以是：

```text
plugins/acme.trade/plugin.json
  plugin key: acme.trade
  modules:
    acme.product
    acme.inventory
    acme.trade
```

### 2.2 `plugins.lock` 是部署清单

`plugin:lock --write` 会扫描 `plugins/*/plugin.json`，把当前代码树允许发布的全部能力包写入根目录 `plugins.lock`。它是部署输入，不是 Tenant 开通状态。

### 2.3 数据库才是运行时状态事实源

运行时分别记录：

| 表 | 事实 |
| --- | --- |
| `pa_plugin_installation` | 当前实例安装了哪些能力包 |
| `pa_module_installation` | 当前实例安装并激活了哪些 Module |
| `pa_plugin_module` | 能力包和 Module 的归属关系 |
| `pa_module_migration` | Module migration 的执行和校验和账本 |
| `pa_tenant_module` | 哪个 Tenant 开通了哪个 Module |

Plugin 安装不会自动开通所有 Tenant。平台控制面通过 `/platform/` 和
`/api/platform/tenants/modules/{enable,disable}` 管理 `pa_tenant_module`：

- [server/route/app.php](/Users/xing/.codex/worktrees/e147/peanut-admin/server/route/app.php:103)
- [platform/src/App.vue](/Users/xing/.codex/worktrees/e147/peanut-admin/platform/src/App.vue:24)
- [PlatformTenantModuleService.php](/Users/xing/.codex/worktrees/e147/peanut-admin/server/app/platform/service/module/PlatformTenantModuleService.php:25)

### 2.4 当前 Module 描述仍然混合了发布信息

当前后端 `module.json` 同时声明 backend provider、frontend entry、Composer/npm 包和 Module 数据边界，例如：

- [server/app/Modules/Official/Payment/module.json](/Users/xing/.codex/worktrees/e147/peanut-admin/server/app/Modules/Official/Payment/module.json:2)

这在单仓库交付时可用，但不适合前端和后端分属不同项目、各自独立实时开发的工作方式。

## 3. 目标和非目标

### 3.1 目标

1. 前端和后端可以在不同项目中独立开发、启动和实时联调。
2. 开发人员只需要理解自己的 Module 代码和共享合同。
3. `plugin.json`、`plugins.lock`、路由索引和部署 registry 成为发布阶段生成物。
4. 前端不拥有租户开通真值；后端不要求前端维护数据库或部署信息。
5. 保留现有安装、升级、TenantModule、RBAC 和数据隔离安全边界。
6. 能够验证前后端 Module key、API、权限和版本兼容性。

### 3.2 非目标

- 本方案不把前端和后端合并成一个代码项目。
- 本方案不把支付渠道、短信渠道或 COS Driver 变成 Peanut 能力包。
- 本方案不改变 Tenant、Account、RBAC、Schema 或存储合同。
- 本方案不引入运行时任意目录扫描。
- 本方案不在当前版本直接重命名数据库表、命令或 lock 文件。
- 本方案不把每个前端组件、Controller 或 Gateway 单独变成 Module。

## 4. 目标文件组织

### 4.1 后端项目

后端项目只维护后端业务实现：

```text
server/app/Modules/Acme/Inventory/
├── module.backend.json
├── composer.json
├── ModuleProvider.php
├── Application/
├── Contracts/
├── Domain/
├── Infrastructure/
├── Http/
├── Database/Migrations/
└── Resources/
```

后端 manifest 只描述：

- Module key 和版本；
- Provider、HTTP 路由和 PHP 合同；
- 自有表和 migration；
- 后端权限、资源和停用行为；
- Tenant 隔离规则；
- 依赖的其他 Module。

### 4.2 前端项目

前端项目只维护前端业务实现：

```text
src/modules/acme-inventory/
├── module.frontend.json
├── package.json
├── contribution.ts
├── api/
├── views/
└── components/
```

前端 manifest 只描述：

- Module key 和兼容版本；
- 页面、路由和客户端 key；
- 前端 API client；
- 页面所需权限；
- 前端资源版本。

### 4.3 共享合同

前后端之间只共享稳定合同，不共享实现：

```text
module.contract.json
```

合同至少包含：

- `module_key`；
- contract version；
- API/DTO 版本；
- permission keys；
- client keys；
- 兼容约束。

后端是 API、权限和数据规则的权威 owner；前端只能消费公开 API 和权限合同，不能复制后端数据库规则。

### 4.4 发布阶段生成物

发布装配器读取后端 manifest、前端 manifest 和共享合同，生成：

```text
release/
├── plugins/<plugin-key>/plugin.json
├── plugins.lock
├── module-registry.generated.php
├── route-registry.generated.php
└── frontend-registry.generated.ts
```

这些文件应被标记为 generated/managed，普通业务开发人员不直接编辑。

## 5. 三种工作流

### 5.1 后端独立开发

```text
后端开发
  → 启动 ThinkPHP
  → 自动读取本地 Module roots
  → 连接开发数据库
  → 通过本地 API 验证
```

开发期间不要求执行 `plugin:make`、`plugin:lock` 或生产安装。

本地开发启动器可以生成未提交的 `.local/dev-module-registry.json`，仅用于当前工作区，不进入发布事实源。

### 5.2 前端独立开发

```text
前端开发
  → 启动 Vite
  → 读取本地 frontend manifest
  → 通过代理访问 ThinkPHP API
  → 实时查看页面和交互效果
```

前端只需知道 Module key、API 地址和权限合同，不需要读取 `plugins.lock` 或连接生产数据库。

### 5.3 发布和安装

```text
前端项目 + 后端项目 + 共享合同
  → 集成校验
  → 生成能力包 manifest
  → 生成 plugins.lock
  → plugin:install / plugin:upgrade
  → 写入 pa_plugin_installation、pa_module_installation
  → Platform 控制面开通 TenantModule
```

正式安装和租户开通继续使用现有生命周期，不把开发流程和发布流程混在一起。

## 6. 开发人员不应再记忆的内容

普通前后端开发人员不应被要求记住：

- `plugins/<plugin-key>/plugin.json` 的结构；
- `plugins.lock` 的生成规则；
- 中央路由文件中的 Module 列表；
- `server/config/modules.php` 中的前端组件总表；
- `pa_plugin_installation` 和 `pa_module_installation` 的字段；
- 如何手工同步发布摘要。

这些内容应由以下角色或工具负责：

| 内容 | 责任者 |
| --- | --- |
| 后端 Module manifest | 后端 Module owner |
| 前端 Module manifest | 前端 Module owner |
| 共享 API/权限合同 | 前后端协作的合同 owner |
| 能力包和 lock | 发布装配器/发布 owner |
| 实例安装状态 | 部署流程 |
| TenantModule 开通 | PlatformOperator |
| 成员 RBAC | Tenant 管理员 |

## 7. 建议的实施阶段

### 阶段 A：先建立合同分离（低风险）

目标：不改变运行时路径，先把前后端合同独立出来。

- 定义 `module.contract.json` 格式；
- 明确 backend/frontend manifest 的字段边界；
- 为一个低风险官方 Module 做样例；
- 增加前后端 key、权限和版本一致性检查；
- `plugin.json` 继续由现有工具生成。

### 阶段 B：开发模式自动 registry（中风险）

目标：本地开发不再执行发布安装命令。

- 开发启动器根据本地 Module manifest 生成临时 registry；
- 后端自动加载本地 Module roots；
- 前端自动加载本地 contribution；
- 开发 registry 不提交、不进入正式发布身份；
- 本地数据库只使用项目登记的 development 资源。

### 阶段 C：发布装配器收口（中风险）

目标：把发布层文件变成生成物。

- 从前后端 manifest 生成 `plugin.json`；
- 生成 `plugins.lock`；
- 生成路由和前端 registry；
- 对生成文件执行 digest、路径和依赖校验；
- 发布前固定能力包和 Module 的完整 commit/tree。

### 阶段 D：目录迁移（高风险，可选）

目标：如果评审认为必要，再把前后端开发源统一为一个 Module workspace 视图。

```text
modules/<module-key>/
├── backend/
├── frontend/
└── contract/
```

运行时目录由构建器投影生成。该阶段会影响 IDE、Composer/npm、源码路径、测试和发布工具，不能和阶段 A/B 同时实施。

## 8. 风险和停止线

### 必须保持不变的安全边界

- 能力包安装不等于 TenantModule 开通；
- TenantModule 开通不等于成员拥有 RBAC 权限；
- Module 仍然是自有表和 migration 的唯一 owner；
- 前端菜单不能代替后端授权；
- 已执行 migration 不允许改写；
- 不允许运行时加载 lock 之外的任意 Module。

### 需要 Claude 重点评审的风险

1. 共享合同应该放在独立 package、独立 contracts 仓库，还是暂时放在集成仓库？
2. 前后端 manifest 是否应该拆成两个文件，还是使用一个只包含合同字段的公共 manifest？
3. 本地开发 registry 是否会产生与正式 `pa_module_installation` 不一致的误导？
4. 生成路由索引是否能保持现有权限、TenantModule Guard 和 fail-closed 行为？
5. 是否真的需要阶段 D 的统一 `modules/<module-key>/` workspace，还是阶段 A-C 已足够降低认知成本？
6. 能力包是否继续保留多 Module 能力，还是官方 Module 默认采用一个 Module 一个能力包？
7. 何时、以什么大版本门槛正式把 Plugin 更名为 Capability Bundle？

## 9. 验收标准

方案实施后，至少应满足：

1. 后端开发者不需要编辑 `plugin.json` 或 `plugins.lock`；
2. 前端开发者不需要理解数据库安装表；
3. 前后端可以在不同项目中独立启动和实时联调；
4. 发布系统可以从前后端 manifest 生成能力包和 lock；
5. 生成物与源码不一致时，发布校验失败；
6. 正式安装仍写入部署状态表；
7. Tenant 开通仍由 Platform 控制面写入 `pa_tenant_module`；
8. 成员 RBAC 和数据权限边界不改变；
9. 不增加第二套 Module 状态或第二套权限真值源；
10. 能够通过一个低风险 Module 完成从独立开发到发布安装的最小闭环。

## 10. 本次评审请求

请重点评审以下决策，而不是先评审命令名称：

1. 是否认可“开发平面与发布平面彻底分离”；
2. 是否认可“Module 是唯一开发单元，能力包是发布产物”；
3. 是否认可前后端各自维护 manifest、共享稳定合同；
4. 是否认可 `plugin.json` 和 `plugins.lock` 不再作为普通开发文件；
5. 是否需要统一 `modules/<module-key>/` workspace，还是先采用不搬目录的生成 registry 方案；
6. 是否认可当前版本只改中文术语，不直接重命名 Plugin 数据库和命令。
