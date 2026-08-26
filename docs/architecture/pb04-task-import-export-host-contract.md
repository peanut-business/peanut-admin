# PB04-05 任务与导入导出 Host 合同

> 状态：Accepted
>
> 应用前置提交：`0a61361a3b977bc0d3db6bc9c5d60542ae1a5ca3`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB04-TASK-OPS-HOST-001`

## 1. 决策与唯一所有权

PB04-05 不把名称相近的应用能力强行映射到核心候选：

- 应用拥有 `pa_crontab`、ThinkPHP console 调度、命令白名单、管理 API/UI 和应用命令的业务语义。
- 应用 Generator 拥有数据库元数据快照、代码模板、管理员隔离的临时归档和一次性下载令牌。“导入表”只表示读取应用数据库元数据，不是业务数据导入。
- 应用同步表格导出继续拥有查询、列定义、分页上限、公开下载 URL 和 `storage/exports` 生命周期；XLSX 字节写入唯一进入 `XlsxExportService`。
- 核心 Task/Job 和 Import/Export 仍只是 Composer `peanut-admin/core` 内部 P1 候选能力。当前没有固定提交聚合资格与 Peanut Admin 下游采用决策，本片不 deep import、不新增 override、不双写核心表。

应用 Crontab、Generator 和同步 XLSX 是当前唯一生产 Runtime。核心候选的存在不构成第二套已启用实现。

## 2. 不等价事实

| 维度 | Peanut Admin 应用 | 核心候选 |
|---|---|---|
| 任务模型 | 单租户 `pa_crontab`；cron 表达式驱动已注册 console 命令 | Tenant `pa_task_job*` 账本；可信 provider/handler、attempt、lease token、审计事件 |
| 失败与重试 | 失败进入 `ERROR` 并保存最近错误；管理员显式 start 清错后重试 | bounded backoff、lease fencing、expired recovery、dead job revision retry |
| 并发 | MySQL advisory scheduler lock；到期窗口以 `status + last_time` CAS 认领 | `FOR UPDATE SKIP LOCKED`、Tenant claim、续租与 stale-attempt fencing |
| “导入” | Generator 读取数据库 schema，生成应用代码归档 | Tenant-private CSV 业务数据导入、Provider schema/mapping、逐行幂等 |
| 导出 | HTTP 请求内同步查询并写 XLSX，最多 25,000 行，公开 URL | Tenant-private CSV ledger，Task/Job 异步执行、取消、留存与 File/Media key |

因此不存在可保持当前 API/schema/可见性的原位 adapter。本片不能用核心三表替换 `pa_crontab`，也不能把同步 XLSX 伪装成核心 CSV operation。

## 3. 应用任务合同

唯一调度链：

```text
system cron -> php think crontab -> app\command\Crontab
  -> pa_crontab due scan -> status/last_time CAS -> Console::call
```

固定规则：

1. 只有 `server/config/console.php` 显式登记且不是 `crontab` 本身的命令可被保存或执行；请求不得传 PHP 类名、SQL、文件路径或任意 shell。
2. 调度器使用 `peanut:crontab:scheduler` advisory lock 防止并发扫描；每个到期窗口在执行前以状态和旧 `last_time` 做 CAS 认领。
3. 成功清空最近错误并记录本次/最大耗时；失败记录错误并进入 `ERROR`，不自动循环重试。
4. 管理员显式 `start` 是应用唯一重试入口：清空旧错误、恢复 `START`，下一次调度或聚焦 Host 调用才重新执行。
5. CAS 在执行前推进 `last_time`，所以进程在命令执行中崩溃可能丢失该次窗口；应用不宣称核心 lease/attempt 或 exactly-once 语义。需要可靠异步副作用时必须另立核心采用合同。
6. `params` 和 `error` 对管理端可见，不得放置密钥或个人敏感数据；日志脱敏与维护探针属于 PB04-06。

## 4. Generator 与同步导出合同

- Generator 仅允许当前管理员访问自己的 `pa_generator_*` 配置与下载令牌；归档路径必须位于 `runtime/generator/<admin>/<random>/`，下载后或到期后按所属随机目录清理。
- Generator ZIP 是代码工件，不是 XLSX/CSV 业务数据导出；它继续由 `GeneratorArchiveService` 单独拥有。
- 管理员、岗位、会员、充值和操作日志的查询/列映射留在各自应用 Logic；它们只把标题和标量行交给 `XlsxExportService`。
- `XlsxExportService` 是 XLSX 容器、XML 转义、文件名清洗和 `storage/exports` 写入的唯一实现；公式样式字符串按 inline text 写入，不执行公式。
- 同步导出上限继续是 25,000 行。文件 URL 通过应用 `FileService` 返回；本片不引入队列、核心 File/Media key、导出 ledger、取消或 retention policy。

## 5. API、权限、错误与数据 owner

- Crontab 和 Generator API 只面向登录后的管理端，继续经过 `LoginMiddleware -> AuthMiddleware -> OperationLogMiddleware`；现有菜单权限字符和 response envelope 不变。
- `pa_crontab` 与 `pa_generator_*` 的 schema/migration owner 是应用仓；本片不改 schema 或种子。
- 调度命令异常只转换为应用任务错误状态，不穿透为调度器崩溃。导出失败沿现有 Logic 错误 envelope 返回；不向响应暴露服务器绝对路径。
- 本片不修改会员/通知触发器、退款状态机、生成模板、前端页面、核心 Runtime、依赖或公开包版本。

## 6. 精确写集与禁改集

Runtime 白名单：

- `server/app/common/service/XlsxExportService.php`；
- `server/app/adminapi/logic/auth/AdminLogic.php`；
- `server/app/adminapi/logic/dept/JobsLogic.php`；
- `server/app/Modules/Official/Member/Service/MemberLogic.php`；
- `server/app/Modules/Official/Payment/Service/RechargeLogic.php`。

证据与状态白名单：

- `server/tests/Productization/TaskImportExportHostTest.php`；
- 本合同、`docs/architecture/pb03-ownership-and-migration-gates.md`、`docs/architecture/core-application-capability-graph.md`、`docs/productization-baseline-plan.md`、`AGENTS.md`。

禁止修改核心仓、`vendor/`、`node_modules/`、数据库、路由/菜单、Crontab/Generator API 与页面、命令业务实现、会员/通知触发器和品牌文件。PB08A 输入保持登记状态。

## 7. 测试 owner 与一次最低验收

`PB04-TASK-OPS-HOST-001` 由 `server/tests/Productization/TaskImportExportHostTest.php` 拥有。它使用当前应用数据库创建一个随机临时 Crontab，并在 `finally` 中物理删除；同时创建一份临时 XLSX 并删除。一次运行证明：

1. 白名单 demo 命令执行成功；
2. 非白名单命令 fail-closed 并进入 `ERROR`；
3. 管理员显式 start 清错，恢复白名单命令后重试成功；
4. XLSX 包含工作簿和预期 inline 文本/数值，临时文件已清理；
5. 五个同步导出调用方只使用 `XlsxExportService`，没有第二个 XLSX ZIP/XML writer；应用没有导入核心 TaskJob/ImportExport 内部命名空间。

执行命令固定为：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/TaskImportExportHostTest.php
```

实现 owner 另运行一次白名单 PHP lint 和一次最终 `git diff --check`。不运行核心候选测试、LikeAdmin parity、全量数据库/API、Web build 或浏览器。

## 8. 停止线

通过只表示应用 Crontab/Generator/同步 XLSX 的 owner、失败后人工重试、唯一 XLSX writer 和测试 owner 已固定。它不批准核心 Task/Job 或 Import/Export 消费，不宣称自动重试、lease/exactly-once、业务数据导入、异步导出或私有文件留存，也不开始 PB04-06 日志/维护。

## 9. 实施证据

- CodeGraph 限定图谱与核心只读合同确认：应用 Crontab 是同步 console 调度，Generator import 是数据库元数据快照，XLSX 是同步公开导出；三者与核心 Tenant Task/Job、私有 CSV Import/Export 不等价，且没有下游采用授权。
- PHP 8.3 下五个 Runtime 文件和新增测试的一次聚焦 lint 全部通过。
- `PB04-TASK-OPS-HOST-001` 一次通过：白名单任务成功、非白名单任务进入 ERROR、管理员 start 后重试成功，以及临时 XLSX 的 ZIP/文本/数值语义均成立。
- 临时 Crontab 与 XLSX 在测试结束后清理为零；没有运行核心候选、LikeAdmin parity、全量数据库/API、Web build 或浏览器验收。
- 核心仓和既有 `.playwright-cli/` 未触碰；应用 schema、路由、菜单、Generator、会员/通知触发器与 PB08A 品牌输入均未修改。
