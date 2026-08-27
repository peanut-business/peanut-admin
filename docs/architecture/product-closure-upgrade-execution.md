# 产品闭环应用升级执行合同

Document ID: `pa-docs-architecture-product-closure-upgrade-execution`

Status: `current`

Owner: `product-operations`

Audience: `maintainer, architect, operator, ai`

Upstream: [`升级就绪合同`](../peanut-admin-development-guide.md)、
[`配对备份 Provider 合同`](product-closure-backup-provider-contract.md)、
[`维护门禁`](product-closure-maintenance-gate.md)、
[`项目资源登记`](../../resources/project-resources.json)。

## 1. 唯一执行边界

PC42 只新增 Application-owned `ops.upgrade.execute` handler 和状态机。任务、状态、幂等、
并发、revision fencing 与审计继续使用 `pa_ops_task`、`PdoOpsTaskDispatcher` 和 Platform
audit；配对备份/隔离恢复继续由 PC31/PC32 worker 执行，维护窗口继续由 PC40 store 管理，
目标 descriptor/readiness/recovery pointer 继续消费 PC41 合同。

实际部署只有一个 owner：`scripts/deploy-release <tag> --target production --update --apply`。
登记资源 `peanut-admin-production-upgrade-control-worker` 从固定控制源码 checkout 调用它，
再通过固定 SSH alias `oracle3` 操作登记的 `peanut-admin-production-deployment`。应用容器内
不复制 Release 打包、SSH、Compose 更新、迁移或 smoke 逻辑。

## 2. 无参数 HTTP 合同

`POST /api/platform/v1/ops/tasks/upgrade` 的 JSON 对象必须为空，只接受 Platform session、
`platform.ops.read`、`platform.ops.upgrade.manage` 和 `Idempotency-Key`。source、target Release、
descriptor SHA-256、commit/tree 与 handler 都由服务器从当前 Runtime 和固定
`.peanut/upgrade-target/` 解析并写入任务 payload。HTTP 不能选择或覆盖路径、URL、命令、
Release、镜像、凭据、重试次数、SSH 主机或部署目标。

`GET /api/platform/v1/ops/upgrades` 和通用任务查询只返回安全投影：source/target 身份、当前
步骤、每步输入/输出摘要、稳定失败码、opaque backup/maintenance key 和最终恢复指针摘要；
不返回任务 payload、文件路径、命令输出、环境或凭据。

## 3. 固定状态机

| 顺序 | 步骤 | 成功输入/输出 | 失败停止语义 |
|---:|---|---|---|
| 1 | `preflight` | PC41 静态预检、source/target/descriptor 摘要一致 | 不创建备份、不进入维护 |
| 2 | `backup` | 新建 `ops.backup.create`；输出 manifest SHA-256 | 不复用历史备份 |
| 3 | `restore_verification` | 新建 `ops.restore.verify`；必须引用刚创建的 backup | 保留登记隔离资源和失败证据 |
| 4 | `maintenance` | 通过 PC40 store 建立 `planned-upgrade` active window；完整 PC41 readiness 变为 ready | 停止部署，维护状态按恢复需要保留 |
| 5 | `deployment` | deployment-control worker 调用唯一 `deploy-release` update owner | 不运行第二套本地更新逻辑 |
| 6 | `smoke` | 新 Runtime commit/tree/Release、健康、迁移和仓库身份一致 | 维护保持 active，按恢复指针处理 |
| 7 | `recovery_pointer` | 固定新 backup、restore evidence、source/target 与摘要；随后关闭维护 | 指针或关闭失败时不冒充完成 |

每步在 `pa_ops_upgrade_step` 保存固定 input SHA-256、状态、output SHA-256、时间和可选稳定失败
码。主执行投影在 `pa_ops_upgrade_execution` 保存唯一子任务和 pointer 引用。任何失败只把当前
步骤和主任务收口为 `failed/dead`；已成功步骤不被重写，旧 revision 不能继续提交结果。

恢复验证成功会先在独立事务中持久化 evidence 摘要、完成 `restore_verification` 并把当前步骤
移到 `maintenance`。维护窗口建立和完整 readiness 检查随后在第二个事务执行；因此维护失败只
回滚维护窗口，不会把已完成的恢复验证退回，稳定停止点也始终是 `maintenance`。

## 4. worker 与操作顺序

执行前先按项目规则核验登记资源：

- worker：`peanut-admin-production-upgrade-control-worker`，环境 `production`，固定 checkout
  `/Users/xing/Documents/company-projects/peanut-admin`；
- 目标：`peanut-admin-production-deployment`，环境 `production`，`oracle3:/www/docker/peanut-admin`；
- 数据保护：`peanut-admin-production-backups` 和
  `peanut-admin-production-restore-verification-deployment`。

在确认上述登记与健康检查后，由部署 owner 从固定 checkout 运行：

```bash
scripts/ops-upgrade-worker --once
```

worker 只接受 `--once`。它从任务账本领取 opaque task 和固定 target tag；调用备份/恢复 worker
时不传资源选择，调用 `deploy-release` 时 target 永远是登记的 `production`。失败时报告 task、
当前步骤和稳定码；不得静默改用 production-candidate、其他 checkout、其他主机或应用目录。

## 5. 停止线

- 跨大版本继续 `fresh/rebuild`，不能通过此状态机原地升级；
- 跨实例 Fleet 编排属于独立运营平台，不进入本仓；
- 正式 released-scaffold 组合资格属于 PC70；PC42 的静态/构建验证不冒充生产升级成功；
- 生产执行会创建备份、隔离恢复、维护窗口并部署 Release，必须另行取得具体生产动作授权和
  资源 lease；本实现任务不连接或修改生产资源。
