# PB04-06 日志与维护 Host 合同

> 状态：Accepted
>
> 应用前置提交：`58358b4b249517eb2923ddc76bedec122d507f4e`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB04-OPS-HOST-001`

## 1. 决策与唯一所有权

PB04-06 保留 Peanut Admin 应用操作日志与环境维护 Host，不接入核心 Ops Console：

- 应用拥有 `pa_operation_log`、管理端写请求留痕、日志列表/同步 XLSX、清理语义、`system/info` 环境页、应用缓存清理和 ThinkPHP HTTP/UI。
- `OperationLogService` 是应用日志脱敏、序列化和写库的唯一入口；Middleware 只决定何时留痕，Logic 只拥有查询、导出与清理用例。
- 核心 Ops Console 是 platform audience 的健康/版本/迁移证据、结构化运行日志、维护窗口及可信备份/恢复任务候选。它不是应用管理员操作日志，也不读取应用 `pa_operation_log`。
- 核心候选仍是 development-only，当前没有固定提交聚合资格与 Peanut Admin 下游采用授权。本片不 deep import、不新增 override、不创建核心 schema 或双写。

## 2. 不等价事实

| 维度 | Peanut Admin 应用 | 核心 Ops Console 候选 |
|---|---|---|
| audience | 单租户产品管理员，沿用菜单/RBAC | 平台 operator，独立 platform 权限 |
| 日志 | 管理端写请求的操作者、IP、URI、方法和脱敏参数 | 注册 Provider 的结构化 runtime event；丢弃原始 message/metadata |
| 状态 | PHP/服务器摘要与两个应用目录的只读可用性 | 健康、版本、迁移、升级、备份证据的一致快照 |
| 维护 | 显式应用缓存清理 | 有 revision/幂等/审计的维护窗口 |
| 恢复 | 无备份/恢复 API | 注册 Provider + Task/Job 的 restore-to-new-target-and-verify |

应用不存在核心所需的平台身份、结构化日志 Provider、维护 repository 或可信备份/恢复 dispatcher。名称相似不能作为替换依据。

## 3. 操作日志合同

唯一写入链：

```text
Login -> Auth -> OperationLogMiddleware -> OperationLogService -> pa_operation_log
log/clear -> OperationLogLogic transaction -> OperationLogService -> pa_operation_log
```

固定规则：

1. POST、PUT、PATCH、DELETE 都尝试留痕；GET 等只读请求不写。写请求抛异常时 finally 仍尝试记录，日志失败不得改变原业务结果。
2. 嵌套字段名中的 password/token/secret/private key/API key/access key/AES key、支付 key、证书、authorization/cookie/ticket 和精确验证码字段统一写为 `******`。
3. `code` 只做精确匹配，`jobs_code` 等业务编码不得误脱敏；public key 不是秘密，不按 private key 处理。
4. 日志参数使用 UTF-8 替换式 JSON，最大 60,000 bytes；编码失败、过深或超限只保存 `payload_unavailable`，不截断成非法 JSON。
5. `log/clear` 继续由独立权限保护。清理在一个数据库事务中删除旧记录并写入本次操作者、IP 与清理数量；审计写入失败时删除整体回滚。Middleware 跳过该 URI，避免重复记录。
6. 列表、筛选和同步 XLSX 上限维持 25,000 行；XLSX 继续使用 PB04-05 已固定的应用 `XlsxExportService`。

## 4. 维护探针与缓存边界

- `system/info` 只返回操作系统、Web Server 摘要、PHP 版本、上传上限、PHP 8.3 要求与 `runtime`/`public/storage` 是否为可读写目录。
- 目录状态只使用 `is_dir`、`is_readable` 和 `is_writable`；探针不得创建、写入、触碰或删除文件，不得返回绝对路径、环境变量、DSN、凭据或原始日志。
- PHP 要求固定为 8.3，与 `server/composer.json` 一致。
- `system/clearCache` 是单独的显式写操作，只清理当前 ThinkPHP cache store 与应用 `runtime/file`；本片不执行该破坏性路径，也不扩大为 runtime/log、storage、数据库或远程缓存清理。
- 本片不新增备份、恢复、维护窗口、运行日志读取、命令执行、任意路径输入或系统信息下载。

## 5. API、权限与错误

- `log/lists`、`log/clear`、`system/info`、`system/clearcache` 必须继续登记在启用菜单权限中，并经 `LoginMiddleware -> AuthMiddleware -> OperationLogMiddleware`。
- 非 root 管理员未获对应菜单权限时 fail-closed；前端按钮权限不替代服务端授权。
- `log/clear` 自身的审计失败必须使清理失败；普通业务操作的旁路日志失败不得使原请求失败。
- 日志列表/导出异常继续进入现有失败 envelope；服务端绝对路径、栈和凭据不得进入日志参数或维护响应。

## 6. 精确写集与禁改集

Runtime 白名单：

- `server/app/adminapi/service/OperationLogService.php`；
- `server/app/adminapi/http/middleware/OperationLogMiddleware.php`；
- `server/app/adminapi/logic/log/OperationLogLogic.php`；
- `server/app/adminapi/controller/log/OperationLogController.php`；
- `server/app/adminapi/logic/system/SystemLogic.php`。

证据与状态白名单：

- `server/tests/Productization/OpsHostTest.php`；
- `.github/workflows/ci.yml`，仅登记无数据库聚焦测试；
- 本合同、`docs/architecture/pb03-ownership-and-migration-gates.md`、`docs/architecture/core-application-capability-graph.md`、`docs/architecture/application-package-and-release-contract.md`、`docs/productization-baseline-plan.md`、`AGENTS.md`；
- `docs/peanut-admin-development-guide.md`、`docs/peanut-admin-user-manual.md` 及其 `docs-site/guide/` 镜像，只同步本片已经形成的审计与维护边界。

禁止修改核心仓、`vendor/`、`node_modules/`、数据库 schema/seed、路由/菜单、前端日志/维护页、缓存配置、应用业务域、PB08A 品牌输入及 SaaS 设计。

## 7. 测试 owner 与一次最低验收

`PB04-OPS-HOST-001` 由 `server/tests/Productization/OpsHostTest.php` 拥有，且不写数据库、缓存或文件。一次运行证明：

1. 四个已登记操作对非 root、零授权集合全部拒绝；
2. 实际支付 key、证书、验证码、authorization 及嵌套字段脱敏，安全字段/业务 code 不误伤，超限 payload fail-closed；
3. `system/info` 返回固定 shape、PHP 8.3 要求和布尔目录状态，不暴露绝对路径；探针源码不包含文件变更或 cache 清理调用；
4. `OperationLogService` 是唯一 writer，日志清理使用事务并保留 `log/clear` 审计；
5. 应用没有 deep import 核心 Ops Console。

执行命令固定为：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/OpsHostTest.php
```

实现 owner 另运行一次白名单 PHP lint 和一次最终 `git diff --check`。不执行 log/clear、system/clearCache、核心候选测试、LikeAdmin parity、全量数据库/API、Web build 或浏览器。

## 8. 停止线

通过只表示应用操作日志脱敏/审计、只读环境探针、权限绑定和测试 owner 已固定。它不批准核心 Ops Console 消费，不宣称平台健康、结构化运行日志、备份/恢复、维护窗口或生产运维就绪，也不开始 PB05。

## 9. 实施证据

- CodeGraph 限定图谱确认应用只有 OperationLog Middleware/Logic/Model 与 System Logic 一套可运行 Host；核心只读合同证明 Ops Console 是 platform audience 的 development-only 候选且没有下游采用授权。
- PHP 8.3 下五个 Runtime 文件和新增测试的一次聚焦 lint 全部通过。
- `PB04-OPS-HOST-001` 一次通过：四个登记权限对零授权非 root 拒绝；支付 key/证书/验证码/authorization 递归脱敏且业务 code/public key 不误伤；超限 payload fail-closed。
- 同次验收证明 `system/info` shape、PHP 8.3 要求、只读目录状态和绝对路径不暴露；唯一日志 writer、清理事务及审计 tombstone 的静态 wiring 成立。
- 测试没有写数据库、缓存或文件；未执行 log/clear、system/clearCache、核心候选、LikeAdmin parity、全量 API/Web 或浏览器验收。
- 核心仓和既有 `.playwright-cli/` 未触碰；应用 schema、路由/菜单、前端页面、业务域与 PB08A 品牌输入未修改。
