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

PC30 固定 Provider 与配对制品合同。PC31 在 Application 内直接采用 Core
`OpsTaskService`，并提供 Platform 备份提交、任务状态、最近任务和最新已验证配对备份视图；
受信 Deployment worker 只消费固定 handler，Web 不能提交命令或路径。PC32 把逻辑目标
`isolated-new-target` 绑定到登记的 `production-restore-verification` 隔离资源：成功后只清理
该新目标，失败时保留精确资源并停止。

## 2. 固定 Provider

| 字段 | 固定值 | 作用 |
|---|---|---|
| Provider | `peanut.paired-db-files` | 唯一的 DB + 文件配对备份能力 |
| backup handler | `peanut.backup.create` | 只供服务器注册的任务执行器解析 |
| restore handler | `peanut.restore.verify` | 只允许恢复到新目标并验证 |
| restore target | `isolated-new-target` | 逻辑目标；客户端不能提交主机、库名或路径 |
| maximum attempts | `1` | 备份 reference 由 task key 确定；失败或进程中断不自动重复副作用 |
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
| PC30 描述与 manifest | 无 HTTP 写入口 | 已实现 Provider 和 manifest 合同 |
| PC31 创建备份 | `platform.ops.backup.manage`；`platform.ops.backup.submitted/succeeded/failed` | Application Host、账本、受信 worker 与备份中心已形成 |
| PC32 恢复验证 | `platform.ops.restore.manage`；`platform.ops.restore.submitted/succeeded/failed` | 已形成提交 Host、恢复 evidence、受信 worker 和固定隔离资源合同 |

Core 任务服务只接受 Provider key、opaque backup reference、registered target 和
idempotency key；handler、命令、参数、路径、重试次数和凭据永远不来自客户端。审计只记录
稳定 key 与摘要，不记录制品路径、Provider 回执或失败原文。

## 6. 下游停止线

- PC31 复用 Core `OpsTaskService`、`OpsTaskDispatcher`、权限与原子审计；任务只写
  Core-owned `pa_ops_task`，Application-owned `pa_ops_backup_evidence` 只保存验证后的安全 manifest 投影；
- Platform 只提交固定 Provider 和 Core 生成的幂等 key。Deployment worker 从登记的
  production 资源执行固定 DB/files 步骤，使用 task key 派生的 opaque backup reference，
  不接受客户端 host、数据库、路径、命令、凭据或重试次数；
- worker claim 返回只属于本次执行的 task revision；运行期间按固定间隔续写心跳，成功和失败
  只能用同一 revision 收口。两小时无心跳的任务会被标记失败并递增 revision，旧 worker 随即
  被 fencing，不能再写 evidence 或覆盖新执行；
- `running` 任务、隔离的不完整目录和 `dead` 任务都不能成为已验证备份证据；
- PC32 固定使用 `peanut-admin-production-restore-verification-*` 三项资源；活动、现有、生产、
  candidate 或摘要损坏目标一律拒绝，目标不得发布端口或加入活动网络；
- worker 恢复同一对 DB/files 制品，验证 migration、六张关键表、Account、Tenant、
  TenantMember 和文件统计；成功前还必须证明 production 与 production-candidate 的容器、
  镜像、运行状态和卷身份未变化；
- “备份文件存在”不等于“恢复已验证”；readiness 与升级状态只能引用真实新目标恢复证据；
- 生产恢复、覆盖活动数据和自动清理旧备份继续需要独立明确授权。

## 7. 验证 owner

PC31 运行 PHP/shell 语法、Platform typecheck、任务/幂等/并发/原子审计与 backup evidence
数据库合同。PC32 在同一登记资源链上验证真实配对制品、新目标恢复、restore evidence、零监听、
活动目标不变和成功清理；最终浏览器与 released-scaffold 组合资格归 PC70。

当固定 PC32 候选尚未部署到在线 Release 时，资格 Gate 不改写 Release 源码目录，也不把 `dev`
冒充已部署版本。它从精确 Git archive 构建登记的临时 PHP CLI image，使用
`org.opencontainers.image.revision`、完整 candidate 和 12 位 tag 三方核对后，仅替代 Compose
one-off task CLI 的 image；运行中的 PHP/Nginx/cron/MySQL 不重建。主机 worker 和 source backup
仍来自登记的 production deployment。若在线 Compose 早于 `.env.source` 合同，资格路径只把该
部署实际使用且登记的根 `.env` 只读挂载到 one-off CLI；容器启动时移除四个禁止持久化的安装
身份键并显式写入登记 schema 的 `DB_PREFIX=pa_`，生成 mode 0600 的容器内临时加载文件，再通过 direct `docker run` 只加入登记的
`peanut-admin_default` 网络，避免旧 Compose 把未在文件中声明的默认值注入候选进程。临时文件随
`--rm` 消失；one-off 保持 stdin 以让 manifest/evidence 进入受信 CLI，配置不复制进 image 或主机新文件，也不改变运行容器。成功后删除精确 worker image 和资格 registry 文件；失败时
保留其身份与隔离恢复资源。Release 展开目录没有 `.git` 时，备份 source commit/tree 来自同一
部署的 `RELEASE_METADATA.json.technical_qualification`，不能猜测分支或工作树身份。
