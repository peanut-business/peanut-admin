# 产品闭环配对备份 Provider 合同

Document ID: `pa-docs-architecture-product-closure-backup-provider-contract`

Status: `current`

Owner: `product-operations`

Audience: `maintainer, architect, ai`

Upstream: Core Ops Console Alpha.9 公共合同、
[`产品闭环所有权与 Core 采用决定`](product-closure-ownership-and-adoption.md)、
[`项目资源登记`](../../resources/project-resources.json)。

## 1. 结论

PC30 直接采用 Core 的 `BackupRestoreProvider` 与 Registry，不修改 Core，也不复制 Core
参考 Host。Application 只登记一个 `peanut.paired-db-files` Provider；数据库和
`php-storage` 的实际动作仍由受信 Deployment Adapter 拥有。

PC30 固定 Provider 与配对制品合同，不开放 Platform 提交入口、不运行生产备份或恢复。
PC31 才能接入幂等任务提交、账本和备份中心；PC32 才能把逻辑目标
`isolated-new-target` 绑定到已登记的隔离资源并执行恢复验证。

## 2. 固定 Provider

| 字段 | 固定值 | 作用 |
|---|---|---|
| Provider | `peanut.paired-db-files` | 唯一的 DB + 文件配对备份能力 |
| backup handler | `peanut.backup.create` | 只供服务器注册的任务执行器解析 |
| restore handler | `peanut.restore.verify` | 只允许恢复到新目标并验证 |
| restore target | `isolated-new-target` | 逻辑目标；客户端不能提交主机、库名或路径 |
| maximum attempts | `1` | PC31/PC32 完成副作用幂等收据前不自动重试 |
| manifest schema | `1` | 固定制品字段和校验语义 |

Provider 代码只描述受信能力，不执行命令。Core Registry 继续负责 provider/handler/target
格式、重复注册和危险目标名检查；Application 不新增第二套 Registry。

## 3. 配对制品

一个可被任务账本引用的备份必须同时包含：

| 文件 | 内容 | 最低校验 |
|---|---|---|
| `database.sql.gz` | 当前登记数据库的逻辑 dump | 非空、SHA-256、`gzip -t` |
| `php-storage.tar.gz` | 同一停写窗口内的文件卷 | 非空、SHA-256、`tar -tzf` |
| `manifest.json` | `PairedBackupManifest` schema 1 的规范 JSON | 字段、顺序、资源和摘要合同通过 |
| `SHA256SUMS` | 上述三个文件的摘要 | 从精确备份目录执行 `sha256sum -c` |

`manifest.json` 只包含安全的制品投影：opaque backup reference、固定 Provider、源码
commit/tree/可选 Release、四个稳定资源 ID、Compose 与镜像身份、停写窗口、容量预检、
两个固定 artifact 的字节数和 SHA-256，以及保留/清理/恢复责任 key。它不包含主机路径、
命令、DSN、账号、密码、环境变量或任意客户端 payload。

## 4. 创建顺序与失败语义

Deployment Adapter 必须按以下顺序执行，不能由 Web 调整：

1. 核对实际环境和 `peanut-admin-production-backups` 登记，完成工具、容量和 inode 预检；
2. 在登记目录创建 mode `0700` 的新 backup ID，不能复用既有目录；
3. 停止并确认全部应用写入者，只保留登记数据库运行；
4. 在同一停写窗口生成数据库 dump 和 `php-storage` archive；
5. 生成规范 manifest 与 `SHA256SUMS`，验证摘要和两个压缩制品；
6. 无论成功或失败都先恢复 PHP、cron 和 Nginx，再离开 Gate；
7. 只有完整验证的 pair 才能产生 opaque backup reference；不完整目录保持隔离，不进入可恢复列表。

容量合同固定为：`required_bytes = source_bytes × 2 + 1 GiB`，且实际 available bytes 必须
严格大于 required bytes、available inodes 必须为正数。失败不能静默改用其他目录、主机、
数据库、对象存储或未登记 fallback。

## 5. 权限、审计与可见性

| 阶段 | 权限/审计 | 当前状态 |
|---|---|---|
| PC30 描述与 manifest | 无 HTTP 写入口 | 已实现合同，不能提交任务 |
| PC31 创建备份 | `platform.ops.backup.manage`；`platform.ops.backup.submitted` | 尚未实施 |
| PC32 恢复验证 | `platform.ops.restore.manage`；`platform.ops.restore.submitted` | 尚未实施 |

Core 任务服务只接受 Provider key、opaque backup reference、registered target 和
idempotency key；handler、命令、参数、路径、重试次数和凭据永远不来自客户端。审计只记录
稳定 key 与摘要，不记录制品路径、Provider 回执或失败原文。

## 6. 下游停止线

- PC31 必须复用 Core `OpsTaskService`、`OpsTaskDispatcher`、权限与原子审计，不另建任务表；
- PC31 在 Deployment Adapter 和副作用幂等收据形成前不得把按钮标成可用；
- PC32 必须先登记真实隔离恢复资源，活动、现有、生产或摘要损坏目标一律拒绝；
- “备份文件存在”不等于“恢复已验证”；readiness 与升级状态只能引用真实新目标恢复证据；
- 生产恢复、覆盖活动数据和自动清理旧备份继续需要独立明确授权。

## 7. 验证 owner

PC30 只运行 PHP 语法、Provider/manifest 一次聚焦合同 smoke、资源 JSON、文档治理、能力账本
和 `git diff --check`。真实数据库、文件、容器、生产备份、恢复和浏览器不属于本任务；它们
分别由 PC31、PC32 和 PC70 的固定候选 Gate 拥有。
