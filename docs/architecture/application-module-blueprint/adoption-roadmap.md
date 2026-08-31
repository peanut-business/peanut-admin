# 现状差距与一次收敛路线

## 1. 原则：完整统一，但不做不可验收的大爆炸

“不只改新业务”与“一次把整个仓库同时搬完”不是同一件事。目标是所有后端业务最终遵守同一架构，不留下永久
双路径；实施则按可验收的纵向切片推进，每个切片同时搬入口、用例、数据 owner、权限和验证，避免数周后才知道
请求已经断裂。

本计划不提供兼容层、双路由、旧 Service facade 或长期 feature flag。每个切片合入时，其旧路径同时退出；最后用
结构门禁证明全仓收敛。

## 2. 当前可以保留的基础

| 现有能力 | 处理 | 原因 |
| --- | --- | --- |
| `adminapi`、`api`、`platform` 目录和战略受众 | 保留并升格为真正 Application | 业务方向正确，缺的是框架装配边界 |
| `ModuleExecutionContext`、ExecutionContextStore | 校准后复用 | 已建立可信 Context 基础，但需要 audience 类型和唯一权威 |
| Module `module.json`、权限/菜单/迁移声明 | 保留 | 已是业务身份和资源 owner 事实源 |
| Module `Application/Contracts/Model/Infrastructure` | 保留并简化规范 | 已接近目标业务边界 |
| 容器 binding 与构造器注入 | 保留并下放 owner | 正确方向，只是目前过度集中在根 AppService |
| HTTP Problem、错误码、审计 outcome | 保留 | 已形成跨端一致基础，应进入 Application 横切链 |
| 服务登记和文档治理 | 保留 | 用于 owner/成熟度，不替代源码或 Module manifest |

## 3. 必须退出的现状

以下事实已在固定基线源码和 CodeGraph 中确认：

| 当前事实 | 业务/开发影响 | 目标动作 |
| --- | --- | --- |
| `server/composer.json` 没有 `topthink/think-multi-app` | 目录名像 Application，实际仍是一个根应用 | 登记多应用依赖并采用框架原生加载 |
| `server/route/app.php` require Admin/API/Platform/Tenant/Module route | 任何入口都要先理解全局路由；安全链容易错挂 | 路由归每个 Application，根路由退出业务装载 |
| Official Module 有 `Http/Controller` 和 `Http/routes.php` | Module 同时拥有业务和入口安全链 | Controller/route/validate 移到消费 Application |
| 多处 `new *ModuleProvider()` | 依赖与生命周期不可见，常驻进程有状态风险 | registry 启动期注册，业务只构造器注入公开能力 |
| 根 `AppService` 绑定大量 Host 与 Module 对象 | 改一个 Application 也要理解全局装配 | root 只留真共享基础，各 owner provider 自己绑定 |
| `tenant` 只承担管理 session，却像独立应用 | 管理登录链被拆成两个难理解入口 | 合并进 `adminapi/application/auth` |
| Payment/OAuth 回调与普通 API/Module HTTP 混放 | Provider 身份、验签和幂等链不突出 | 建立 `integrationapi` Application |
| `common` 同时含合同、业务 Runtime、外部适配和工具 | 容易继续成为默认垃圾桶 | 按 owner 回迁，只保留无业务 owner 的共享基础 |

## 4. 优先优化空间

用户要求先检查“中间件、拦截器、scope 等高级特性是否还有空间”。结论是有，但应优先解决重复边界，不增加另一套
框架：

1. **原生多应用加载**：让每个 Application 自动加载自己的 route/config/provider/middleware；这是最大认知收益。
2. **固定安全 middleware 链**：Admin、Consumer、Platform、Provider 各一套，不在每条路由手工拼不同顺序。
3. **容器注入代替 Provider 定位**：删除手工 `new ModuleProvider()`，让依赖和生命周期可见。
4. **唯一 Context + ORM scope**：Context 决定 Tenant，scope 只执行过滤，删除显式参数/全局 scope 双权威。
5. **启动期 Module registry**：构建/启动时校验并缓存，生产请求不扫描目录、不创建 Provider 查 key。
6. **事务 interceptor 只包装标记的写 Use Case**：先统一外层事务参与合同，不为每个方法隐式开事务。
7. **统一 observability middleware/transport**：HTTP、Job、Provider、outbound attempt 使用同一 trace 语义。

不建议优先引入：全局 AOP 自动包住所有 Service、通用 Event Bus、Repository 全家桶、Module Federation、微服务、
整体 Hyperf 迁移。它们当前没有比上述七项更直接的收益证据。

## 5. 推荐实施顺序

### 阶段 A：Application 地基

目标：先让路径真实代表入口身份。

允许写集：`server/composer.*`、`server/config/app.php`、根/各 Application route/config/provider/middleware、
`server/app/AppService.php`，以及直接受影响的 HTTP 合同与最小文档。

工作内容：

1. 登记并采用 `topthink/think-multi-app`；
2. 固定 `adminapi/api/platform/integrationapi/installation` 映射和 URL 前缀；
3. 把 `tenant` session 合并进 adminapi；
4. 建立每个 Application 的 route/provider/middleware 入口；
5. 根 AppService 只保留真共享基础，但暂不搬业务 Module 内部；
6. 一次同步 OpenAPI 与五个 Host 的错误边界。

最低验证：每个 Application 各一个正向 route、错误 audience token 负向、Admin 未选 Tenant 负向、Platform 不获得
Tenant Context、Provider route 不接受浏览器 Session。

完成定义：框架可独立加载五个 HTTP Application，根 route 不再 require 业务 route，外部前缀一次切换完毕。

### 阶段 B：Article 纵向样板

目标：用当前同时服务 Admin/API 的 Article 证明 Application × Module 交叉模型。

允许写集：adminapi/api Article controller/validate/route、Article Module、Module registry/container、Article 前端 API
调用与直接合同。

工作内容：

1. 将 Article Module 的 HTTP Controller/route/validation 移到 adminapi；
2. 保留/校准 api Article Controller，使两端调用同一 Module Query/Command；
3. ModuleProvider 只做容器装配；
4. 删除 Article 的所有手工 Provider 定位；
5. 固定 Admin/Consumer 两种 Context、权限和 Tenant scope；
6. 形成可复制的生成器模板和结构检查。

最低验证：Admin CRUD 权限/Tenant 隔离、匿名列表/详情、会员收藏、TenantModule 停用、同一业务规则没有两份实现。

完成定义：Article Module 无 `Http`/`Validation`，Admin/API 请求都命中同一 owner，用例与数据表未复制。

### 阶段 C：全量 Module 收敛

目标：按相互独立的 Module 组并行搬迁，但最终一次关闭旧结构。

可并行组：

- Member + OAuth（身份和绑定关系紧密，作为一组）；
- Payment + Notification（Provider/资金链分别有 owner，文件不冲突时可并行）；
- File + ImportExport（对象交付关系紧密，作为一组）；
- Task（独立 Worker/Attempt Host）；
- 其余简单管理域按 adminapi vertical slice。

每组必须同时完成 Controller/route/validate 归位、容器注入、跨 Module Contract、事务和最低权限/Tenant 验证。不能
只移动文件后把旧 facade 留给下一轮。

最低验证：每个 Module 一个代表性正向、一个错误 audience、一个 Tenant/owner 负向、一个停用负向；支付/退款、
身份、Storage、Task 额外运行其高风险聚焦 Gate。

完成定义：全仓 Module 无最终 HTTP owner、无手工 ModuleProvider 定位、无跨 Module Model/表写入。

### 阶段 D：非 HTTP 与横切收口

目标：让 Job、Crontab、Provider callback 和现有命令复用同一 Use Case 与 Context 规则。

工作内容：

1. Task Job/Attempt/Retry 统一入口；
2. Provider callback 迁入 integrationapi；
3. trace、audit、outbound attempt、锁/缓存 namespace 统一；
4. CLI/Worker 每次执行建立并清理 Context；
5. 仅在真实 WS 功能获批时创建 WebSocket Host。

最低验证：HTTP 与 Worker 调用同一 Command、Attempt trace 可关联、失败 outcome 明确、Context 不跨 Attempt 泄漏。

完成定义：非 HTTP adapter 不复制业务状态机，所有入口都能回答 actor/Tenant/module/operation/trace/outcome。

### 阶段 E：全仓结构关闭门禁

目标：证明“完整统一”而不是“新代码看起来更好”。

门禁至少拒绝：

- Module 下的 `Http/Controller`、`Http/routes.php`、`Validation`；
- 根路由 require 业务 route；
- 业务代码 `new *ModuleProvider()` 或用 `app()`/Facade 定位 Module Service；
- Application 直接引用其他 Module 的 Model/Infrastructure；
- 未登记的 Application、未知 middleware 组合和 route permission；
- `common` 中新增有明确业务 owner 的 Service；
- 可变 static 保存用户、Tenant、Request、事务或 Provider 状态。

完成后更新 Module 生成器、后端开发指南和公开开发者文档；在此之前，当前公开指南继续描述已实现 Runtime，不能把
本蓝图投影成已交付功能。

## 6. 串行与并行关系

```text
阶段 A Application 地基
          │
          ▼
阶段 B Article 样板
          │
          ├──────────────┬──────────────┬──────────────┐
          ▼              ▼              ▼              ▼
Member/OAuth       Payment/Notice   File/ImportExport     Task
          └──────────────┴──────────────┴──────────────┘
                                  │
                                  ▼
                       阶段 D 横切/非 HTTP 收口
                                  │
                                  ▼
                       阶段 E 全仓结构门禁
```

阶段 A 必须串行，因为它决定所有入口装载。阶段 B 必须随后完成，因为它验证模板。阶段 C 的 Module 组在文件 owner、
Schema 和资源不冲突时并行；阶段 E 必须等全部组完成。

## 7. 不应顺手做的事情

- 不迁移到 Hyperf/Swoole；
- 不拆微服务；
- 不重写前端架构或 Module 包生命周期；
- 不因为移动目录改业务字段、表或产品流程；
- 不创建旧 URL、旧 Controller 或旧 Service 的兼容代理；
- 不把所有现有 Service 重命名成新术语；只有 owner/职责错误时才移动或改名；
- 不为目标态预建空 WS、Domain、Repository、Event 目录。

## 8. 全部完成的业务化定义

开发者打开任意请求时，可以在一分钟内回答：

1. 它属于哪个访问者入口；
2. 哪条固定安全链建立身份和 Tenant；
3. 哪个 Controller 只负责协议；
4. 哪个 Module 拥有业务规则和数据；
5. 哪个 Use Case 拥有事务；
6. 跨 Module 调了哪个公开能力；
7. 失败会留下什么 trace、audit 和稳定 reason；
8. 同一业务通过 HTTP、Job 或 WS 进入时是否复用同一规则。

只有所有现有业务域都能回答，且结构门禁拒绝旧写法，才能宣称架构统一完成。`dev` 中完成目录迁移也不等于正式
版本已经发布或部署；发布身份、资格和消费仍走项目既有发布流程。

## 9. 文档影响

本蓝图是架构决定和实施输入，不修改当前 Runtime、公开 API、资源、能力账本、发布身份或部署状态。后续每个代码
切片按 `docs/document-impact-map.json` 只更新命中的最小文档；公开站点只能在相应 Runtime 真正合入并验证后投影。
