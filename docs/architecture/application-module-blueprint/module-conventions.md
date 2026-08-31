# 目录、调用与依赖规范

## 1. Module 的最低心智模型

Module 只回答三个问题：

1. 这项业务能做什么；
2. 哪些数据和规则由它拥有；
3. 它允许其他 Module 或 Host 调用什么。

它不回答“当前请求来自管理端还是会员端”，也不决定 HTTP 登录、响应 envelope 或最终路由。这些属于
Application。

## 2. 标准 Module 目录

```text
server/app/Modules/Official/Article/
├── module.json                 # 唯一身份、依赖、资源和数据 owner 声明
├── ModuleProvider.php          # 启动期容器装配；无请求状态、无路由
├── Application/               # 可执行用例：Command、Query、事务边界
├── Contracts/                 # 只放真正对外公开的 PHP 能力/DTO/事件
├── Model/                     # Module 自有 ThinkORM Model
├── Database/
│   └── Migrations/            # Module 自有 append-only migration
├── Resources/                 # permissions、menus、settings 等声明
├── Domain/                    # 可选：复杂状态机、值对象、策略
└── Infrastructure/            # 可选：外部 Provider、复杂 persistence adapter
```

目标态中不再出现：

```text
Http/Controller/
Http/routes.php
Validation/
```

Controller、route 和请求验证移动到消费该能力的 Application。例如 Article 的管理接口放
`app/adminapi/controller/article`，公开接口放 `app/api/controller/article`，两者调用同一个 Article Module。

## 3. 哪些目录必须有，哪些按需增加

| 目录/文件 | 默认 | 放什么 | 不放什么 |
| --- | --- | --- | --- |
| `module.json` | 必须 | key、version、依赖、权限资源、自有表 | Runtime 状态、重复路由表 |
| `ModuleProvider.php` | 必须 | 容器 binding、公开能力装配 | `new` 当前用户/Tenant、业务流程、HTTP route |
| `Application/` | 必须 | 有业务意义的 Query/Command，用例级事务 | Request/Response、万能 helper |
| `Model/` | 有表时必须 | 只映射本 Module 自有表 | 跨 Module relation 自动写入 |
| `Database/` | 有 Schema 时必须 | 自有 migration | 修改其他 Module 的表 |
| `Resources/` | 有贡献时必须 | 权限、菜单、设置声明 | 第二份 module identity |
| `Contracts/` | 有外部调用者时增加 | 最小公开类型和能力 | 对内部类做镜像接口 |
| `Domain/` | 有复杂不变量时增加 | 状态机、实体、值对象、策略 | 简单 CRUD 的空壳实体 |
| `Infrastructure/` | 有外部 SDK/复杂 adapter 时增加 | Provider client、Repository 实现 | 业务规则 |

空目录不提交。简单字典 CRUD 通常只需 `Application + Model + Database + Resources`；支付、退款、任务这类复杂
业务再增加 Domain 和 Infrastructure。所有 Module 使用同一允许清单，因此心智模型稳定，但不为目录对称制造
空代码。

## 4. Application 内部目录

以 `adminapi` 为例：

```text
adminapi/
├── controller/
│   ├── auth/
│   ├── article/
│   └── payment/
├── application/
│   ├── auth/                  # 管理登录、Tenant 选择等 Host 用例
│   └── workbench/             # 确实需要跨 Module 聚合时才存在
├── middleware/
├── validate/
├── route/
├── config/
├── provider.php
└── middleware.php
```

同一业务名在 Controller 下出现不是业务复制。例如：

- `adminapi/controller/article/ArticleController` 负责管理端输入、权限元数据和输出；
- `api/controller/article/ArticleController` 负责公开/会员端输入和输出；
- 两者都调用 `Modules/Official/Article/Application/*`，业务规则只有一份。

`adminapi/application/` 不是所有 Controller 的强制中转层。只有登录/Tenant session 属于 Host 本身，或工作台需要
协调多个 Module 时才增加 Host Application Service；单一 Article CRUD 可以由 Controller 直接调用 Module Query/
Command，避免无意义代理。

## 5. Controller、Application Service、Domain、Model 的职责

| 位置 | 自然语言职责 | 可以做 | 不可以做 |
| --- | --- | --- | --- |
| Controller | 把协议变成用例调用 | 取已验证输入、调用服务、映射响应 | 开事务、拼业务 SQL、推断 Tenant、捕获所有异常 |
| Host Application Service | 协调一个入口专属流程 | 组合多个 Module、管理 Host session | 拥有 Module 表、复制业务状态机 |
| Module Application | 完成一个完整业务用例 | 校验业务规则、开最外层事务、调用自有 Model/公开合同 | 读全局 Request、返回 HTTP Response |
| Domain | 表达复杂且可独立理解的规则 | 状态转换、金额/库存规则、值对象 | 数据库/HTTP/框架调用 |
| Model/Repository | 持久化 Module 自有数据 | 查询、锁行、保存、应用 DataScope | 代替权限 middleware、跨 owner 写表 |
| Infrastructure | 对接技术细节 | SDK、外部 HTTP、文件、复杂 repository | 决定业务成功状态 |

一句话判断：Controller 说“请求长什么样”，Application 说“一次业务要完整完成什么”，Domain 说“什么状态才合法”，
Model/Infrastructure 说“数据和外部系统怎样读写”。

## 6. 跨 Module 调用

### 6.1 四种允许方式

| 需求 | 方式 | 示例 | 事务语义 |
| --- | --- | --- | --- |
| 立即读取对方权威数据 | 公开 Query | Payment 查询 Member balance snapshot | 不修改对方数据 |
| 立即要求对方修改数据 | 公开 Command | Refund 调 Member balance command | 必须参与调用方外层事务或明确远程边界 |
| 通知已发生的事实 | 提交后 Event | ArticlePublished 触发 Notification | 不用于返回关键业务决定 |
| 高成本跨域查询 | 明确 Projection | Workbench 读取汇总投影 | 投影标注新鲜度，不反写 owner |

禁止方式：

- `use OtherModule\Model\...`；
- 直接 join/update 对方私有表；
- 调用对方 `Infrastructure` 或内部 Application 类；
- 通过全局 facade、字符串类名或 `new OtherModuleProvider()` 定位服务；
- 用事件询问“是否允许付款”或期待同步返回值。

### 6.2 公开什么

Module 公开面越小越好：

```text
Contracts/
├── Dto/
│   └── MemberBalanceSnapshot.php
├── MemberQueries.php
├── MemberBalanceCommands.php
└── Events/
    └── MemberBalanceAdjusted.php
```

公开 DTO 使用标量、值对象和稳定枚举，不暴露 ThinkORM Model、PDO、Request、Response 或内部表字段。公开能力的
方法名表达业务操作，例如 `reserveBalance()`，而不是通用 `save(array $data)`。

### 6.3 何时使用接口

不是每个 Service 都需要“一接口一实现”。按以下规则：

- 同一 Module 内部调用 final class，直接构造器注入；
- 强绑定且必装的官方 Module 之间，可以注入对方 `Contracts` 命名空间下的 final Query/Command facade；
- 可选 Module、可替换 Provider、Core/Application 跨仓边界，使用接口并由容器绑定；
- 测试需要替身本身不是创建接口的理由，真实替换边界才是。

`Contracts` 表示稳定公开面，不等于每个文件必须是 PHP interface。

### 6.4 防止循环依赖

Module 依赖图必须有向无环。发现 `Payment -> Member -> Payment` 时不新增双方 facade 继续互调，应重新判断 owner：

- 只是通知：一方改成提交后事件；
- 是一个完整业务事务：由更高层 Host/Use Case 协调两个 Module；
- 是共同基础概念：提取最小值对象/合同到 Core 或有明确 owner 的基础 Module；
- 两边都需要同一张数据：该数据的 owner 尚未定义，先定义 owner。

## 7. 事务规则

1. 写操作由最外层 Module/Host Application Service 开一个事务。
2. 同进程跨 Module Command 使用同一 PDO/transaction participant，不在内部擅自提交。
3. Controller、middleware 和 Model observer 不拥有完整业务事务。
4. 外部 HTTP 不可能参加数据库事务；先固定本地状态与幂等身份，再用明确状态机/Outbox 完成外部副作用。
5. Event 默认在 commit 后发布；确需同事务的内部同步调用，直接调用 Command，不伪装成事件。
6. 并发写使用 `expected_revision`/If-Match 或业务幂等键，并从 Controller 一直传到 owner Command。

## 8. 静态方法、public、private 怎样选择

### 8.1 决策顺序

1. 方法是否只依赖入参、无 IO、无配置、无当前用户/Tenant、结果完全确定？可以是 static pure function。
2. 方法是否是值对象的命名构造，例如 `Money::fromMinor()`？可以 static。
3. 方法是否只在启动期声明不可变路由/metadata？可以 static factory 或常量。
4. 其他情况默认实例方法，通过构造器注入依赖。
5. 只有其他对象确实需要调用才是 `public`；用例内部步骤保持 `private`。
6. 子类扩展不是已确认需求时不用 `protected`，优先 final class + private。

### 8.2 一眼判断表

| 场景 | 推荐 | 原因 |
| --- | --- | --- |
| 金额格式化、module key 路径转换 | `public static` 纯函数 | 无运行状态，调用方便且安全 |
| `TenantId::fromString()` | `public static` 命名构造 | 表达值对象创建语义 |
| Article 创建/退款/发短信 | 注入的实例 `public` 方法 | 有事务、Provider、Tenant 或审计依赖 |
| Service 内校验/组装步骤 | 实例 `private` | 不是公共合同 |
| 可复用但只在同 Module 使用 | final helper 的实例或纯 static | 先看是否有状态/依赖，不因“方便”全 static |
| 跨 Module 请求 | 注入公开 Query/Command | 依赖可见、能控制事务和可观测性 |
| `app()`/Facade 定位业务服务 | 禁止 | 隐藏依赖，常驻进程更容易串状态 |

LikeAdmin 的静态 Logic 看起来调用短，但它把数据库、配置、Request 和错误状态藏在全局环境里。Peanut Admin 只保留
真正纯静态能力，不用 static 作为 Service Locator 的语法糖。

## 9. 第三方包与版本冲突

### 9.1 一个进程一份依赖图

PHP 在同一进程不能安全加载同一个包的两个不兼容版本。永久规则是：

- Module 的包元数据声明依赖范围；
- 应用根 `server/composer.json` 和唯一 `composer.lock` 解析并固定最终版本；
- 前端同理由 workspace/package metadata 声明，应用唯一 lock 固定版本；
- Module package 不携带第二份 `vendor/` 或 `node_modules/`；
- 安装/升级在写文件和数据库前完成依赖求解，冲突直接拒绝。

### 9.2 两个 Module 要求不同版本怎么办

按成本从低到高处理：

1. 把两个约束升级到有交集的受支持版本；
2. 在 owner Module 的 Infrastructure 中用一个很薄的 adapter 隔离第三方 API 差异；
3. 更换其中一个包，优先标准库或项目已安装依赖；
4. 只有不可兼容且业务价值足够高时，把其中一个能力隔离到独立进程/服务，用版本化协议通信。

禁止 Composer alias、复制 namespace 后魔改 vendor、运行时切换 autoloader 或长期维护两个 SDK 分支。这些做法把一次
安装冲突变成每次排错成本。

### 9.3 包配置和客户端 owner

- Secret 和 endpoint 由项目资源/配置事实源登记，不写进 Module 代码；
- Module 的 Infrastructure 拥有该业务的 Provider adapter；
- 多个 Module 共用同一技术 transport 时复用 `OutboundHttpTransport`，但各自保留业务 request/response mapping；
- 全局包版本由应用发布 owner 管，Module owner 只声明最低/最高兼容范围；
- PHP extension、常驻进程、端口、队列、对象存储等运行资源同样必须进入项目资源登记。

## 10. 权限和数据范围

Module manifest 声明业务 permission、resource、operation 和菜单要求；Application 路由把 endpoint 绑定到其中一个
operation。两边必须逐字符一致，未知或缺失绑定在构建/启动期失败。

权限分四层：

1. Application audience：管理员、会员、Platform、Provider 或 system actor；
2. Module availability：Package 安装、Module active、TenantModule enabled；
3. operation permission：RBAC/业务主体是否能执行动作；
4. object scope + invariant：能作用哪些对象、对象当前状态是否允许。

管理端 DataScope 消费 Admin Context；消费端“只能看自己的订单”是 Member 业务范围；Platform target 是被管理目标。
不能用一套 `tenant_id` global scope 把四者混成同一种权限。

## 11. CRUD、复杂业务和特殊场景的三个开发模板

### 11.1 简单 CRUD

```text
adminapi Controller -> Module Command/Query -> Module Model
```

有验证、权限、分页和 Tenant scope，但不强制 Domain、Repository、Event。

### 11.2 复杂状态机

```text
Application Command -> Domain state machine -> Repository/Model
                    -> 公开跨 Module Command
                    -> commit 后 Event
```

用于退款、支付、余额、任务等；事务、幂等、revision 和失败恢复在一个用例中可见。

### 11.3 外部 Provider/大文件/长任务

```text
Host adapter -> Application Command -> Infrastructure port/adapter
             -> receipt/job/object ledger -> async continuation
```

上传使用流式输入和对象账本，下载流式输出；外部 HTTP 记录每次 attempt；长任务有 Job/Attempt/Retry，不能在
Controller 中阻塞完成。

## 12. 命名规则

- Application 目录使用稳定小写身份：`adminapi`、`api`、`platform`、`integrationapi`、`installation`；
- Module 路径使用 `Modules/<Vendor>/<Name>`，key 使用 `<vendor>.<name>`；
- 用例类名使用业务动词和对象：`CreateArticle`、`RefundRechargeOrder`、`ListMembers`；若保留现有 Service 风格，
  使用 `ArticleAdministrationService`，禁止 `CommonService`、`DataService`、`UtilsService`；
- Query 不产生业务写入，Command 不以返回任意数组作为公共合同；
- Context 名称带 audience：`AdminExecutionContext`、`ConsumerExecutionContext`、`PlatformExecutionContext`、
  `ProviderExecutionContext`；
- DTO、Event 和 error code 使用稳定业务语言，不泄露表名和框架类名。

## 13. 生成器应该生成什么

Module 生成器只生成最低骨架：manifest、Provider、一个示例 Use Case、Model/migration/resource 的按需选项。Application
Controller 生成器根据 audience 选择固定 middleware 链和请求模板。

生成器不自动创建 Repository interface、Domain entity、Event、Factory、Presenter 或测试全家桶。真实需求出现后再
增加；目录允许清单和静态检查负责防止代码回到旧位置。
