# Peanut Admin Application 与 Module 永久架构蓝图

> 文档状态：目标架构决定，尚未等同于 Runtime 已完成。
>
> 适用范围：Peanut Admin 后端源码组织、请求入口、Module、跨模块调用、依赖、非 HTTP Host 和迁移顺序。
>
> 基线：`origin/dev@dfaceb2c044db14818f43b44253feb257c9b7566`，2026-08-31。

## 一句话结论

Peanut Admin 应同时保留两种边界，但不能再把它们混为一谈：

- **Application 按“谁在访问、通过什么协议访问”划分**，负责入口、身份、Tenant 获取方式、权限链和响应协议；
- **Module 按“哪项业务能力、谁拥有数据和规则”划分**，负责用例、业务不变量、自有数据和公开能力。

因此，`adminapi` 和 `api` 不是两个普通目录，更不是 Article、Payment 这类业务模块。它们是两套战略级
ThinkPHP Application：管理端必须经过管理身份、Tenant、RBAC 和数据范围；消费端可以匿名或使用会员身份，
即使需要 Tenant，也不能借用管理端会话和权限链。

## 这套蓝图解决什么问题

现在打开后端代码容易迷路，并不是文件数量本身造成的，而是同一项业务可能同时出现在：

- 根路由、Application 路由和 Module 路由；
- `controller`、`application`、`service`、`common/service` 和 Module `Application`；
- 全局 `AppService` 的容器绑定和各处临时 `new ModuleProvider()`；
- `adminapi`、`api` 与 Module 自己的 `Http/Controller`。

开发者无法只凭路径判断“这段代码服务谁、由谁授权、谁拥有事务、谁能改数据”。蓝图的目标不是增加更多层，
而是让路径本身回答这四个问题。

## 永久规则

1. `server/app/adminapi`、`api`、`platform` 是独立 Application，不是 Module。
2. Application 拥有最终路由、Controller、输入验证、身份链、Tenant 获取规则、权限链和协议错误。
3. Module 拥有业务用例、业务规则、自有表、迁移、菜单/权限声明和跨模块公开能力。
4. Module 不拥有最终 HTTP Controller 或路由；同一 Module 可以被多个 Application、CLI、Job 或 WS Host 调用。
5. `adminapi` 与 `api` 不共享会话类型、RBAC 或 Tenant 获取规则；最多共享无身份含义的值对象和基础设施。
6. 跨 Module 不直接访问对方 Model、表或内部 Service；调用对方公开 Query/Command，或消费提交后的事件。
7. 一个部署进程只有一份 Composer/npm 依赖图和 lock；版本冲突在安装或构建期拒绝，不在运行时装两份 vendor。
8. 业务服务使用构造器注入；静态方法只允许纯函数、值对象命名构造器和启动期声明。
9. HTTP、CLI、Job、回调和 WS 都建立自己的执行上下文，再调用同一 Module 用例；Module 不读取全局 Request 猜身份。
10. 迁移可以分批完成，但最终验收必须清除旧路由装载、Module HTTP 和手工 Provider 定位，不保留永久双路径。

## 目标知识图谱

```text
访问者 / 事件
├─ 管理员请求 ─────> adminapi Application ─┐
├─ 会员/匿名请求 ──> api Application ──────┤
├─ 平台运营请求 ──> platform Application ─┤
├─ 安装请求 ──────> installation Host ────┤
├─ CLI / Job ─────> console/worker Host ──┤
├─ Provider 回调 ─> callback Host ────────┤
└─ WS 消息 ───────> websocket Host ───────┘
                                              │ 可信 Context + 已授权操作
                                              ▼
                                       Module Application
                                              │
                         ┌────────────────────┼────────────────────┐
                         ▼                    ▼                    ▼
                    Domain 规则          自有 Model/表        公开 Contracts
                                                                  │
                                                        其他 Module/Host

Core：只在所有 Application/Module 都能复用且不含产品业务语义时提供底层合同。
```

## 打开目录时应该怎样理解

| 看到的路径 | 先问的问题 | 它应该包含什么 |
| --- | --- | --- |
| `app/adminapi` | 管理员怎样安全访问系统？ | 管理会话、Tenant 选择、RBAC、数据范围、管理端 Controller |
| `app/api` | 会员或匿名用户怎样访问业务？ | 会员会话、公开/会员 API、消费端 Tenant 解析、限流 |
| `app/platform` | 平台运营者怎样管理实例和 Tenant？ | Platform 身份、平台权限、跨 Tenant 控制面操作 |
| `app/Modules/.../Article` | Article 业务本身怎样工作？ | 文章用例、规则、自有表、迁移、公开查询/命令 |
| `app/common` | 是否真的是所有入口都通用？ | 无业务 owner 的底层适配、值对象和少量共享合同 |
| `app/command` / worker | 非 HTTP 事件怎样进入业务？ | 解析消息、建立 Context、调用 Module，不复制业务规则 |

## 文档导航

1. [主流脚手架源码研究](reference-research.md)：LikeAdmin、LikeAdmin SaaS、MineAdmin、BuildAdmin、Hyperf 的真实组织方式、优点和代价。
2. [目标架构与完整执行流程](target-architecture.md)：Application、Module、HTTP、回调、Job、WS、权限和可观测性如何协作。
3. [目录、调用与依赖规范](module-conventions.md)：每个目录放什么、跨 Module 怎么调、何时用 static/public/private、第三方包冲突怎么办。
4. [现状差距与一次收敛路线](adoption-roadmap.md)：哪些现状可复用、哪些必须退出、推荐实施顺序和完成定义。

## 当前事实与目标的边界

当前 Runtime 已具备可复用基础：管理端、消费端、Platform 目录已经存在；可信执行 Context、Module manifest、
权限/菜单声明、部分 Application Service 和容器绑定也已存在。这些不应推倒重写。

但目标架构**尚未整体落地**：当前 Composer 未登记 `topthink/think-multi-app`，`server/route/app.php` 仍统一加载
Admin、API、Platform、Tenant 和 Module 路由，Module 仍包含 HTTP Controller/route，运行时也仍有多处手工
`new ModuleProvider()`。因此本文只能作为后续实现的唯一目标，不得据此宣称架构改造已经完成。

旧的 `optimized-module-architecture-plan.md` 和 `module-architecture-refinement-appendix.md` 把 Module 同时当成
业务边界和 HTTP Host，已经不能继续作为源码组织目标；其中包完整性、生命周期和 catalog 的有效设计仍由
`consumer-module-lifecycle-contract.md` 及现行命令事实承接。

## 判断新代码放在哪里

只按下面的顺序判断，不需要背更多概念：

1. 代码是否只负责某种入口的身份、协议或响应？放对应 Application。
2. 代码是否表达某项业务规则或修改某个业务 owner 的数据？放对应 Module。
3. 代码是否协调多个 Module 完成一个入口用例？放调用方 Application 的 `application/`。
4. 代码是否被多个 Module 复用但仍有产品业务含义？明确一个 owner Module，其他模块调用其公开能力。
5. 只有完全不含产品语义、确实被多处复用的能力，才进入 `common` 或 Core。

这五问无法给出唯一答案时，说明 owner 还没有定义清楚，不应先创建新目录或抽象。
