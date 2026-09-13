# Core 与应用技术边界

> 状态：current；本页解释 Core/Application 的 owner 和 Storage Driver 边界。ThinkPHP 8 运行时收敛方向以 [Core ThinkPHP 8 运行时收敛方向 ADR](./core-thinkphp-runtime-direction-adr.md) 为准；Storage Driver 的采用、正式应用 Release 与真实 Provider 资格仍分开判断。
>
> 适用范围：Peanut Admin 应用仓与 `peanut-admin-core` 的共享技术边界。

## 1. 仓库与 Edition 原则

应用产品源码唯一在 `peanut-admin`；业务无关复用包在 `peanut-admin-core`。Standalone 与 Multi-tenant 两 Edition 必须由同一份应用冻结源码确定性生成。禁止建立独立单租户人工源码仓，也不以构建产物、路径仓或复制源码制造第二条业务实现。

Core 拥有产品无关机制及明示技术状态，但正式 PHP 运行时只支持 ThinkPHP 8；Core 的 persistence 逐步使用 ThinkPHP Model/Query/Db/Transaction，不把 PDO Repository 作为长期公共 API。应用拥有业务规则、业务表、业务 UI 和宿主装配。每张表、每个状态机和每项业务规则只有一个 owner；是否属于 Core 由语义和真实调用决定，不由“所有 UI/数据库归应用”或同名目录一刀切。

## 2. 五域职责原则

| 域 | Core | 应用 | 本轮状态 |
| --- | --- | --- | --- |
| Settings | typed、secret、scope、产品无关 Settings Schema 与服务合同；当前 Alpha.13 仍有 PDO persistence 过渡实现 | setting definition 的 key/value schema/default、业务设置含义和 ThinkPHP 宿主装配 | 按 ADR 微批次迁移，不保留长期 PDO 双实现 |
| Storage | `StorageDriver`、对象 key 规则和低层文件传输机制 | Provider SDK 装配、凭据解密、用途、授权、对象账本、补偿和产品生命周期 | Core Alpha.13 已发布；应用收敛候选已采用，待应用固定候选资格与 Release |
| Crontab | 产品无关执行机制与 `TaskJob` | Cron 业务规则、授权和宿主装配 | 共用 `TaskJob`，不建第二套队列 |
| ImportExport | 产品无关导入导出机制与 `TaskJob` | 格式、业务授权和宿主装配 | 共用 `TaskJob`，不建第二套队列 |
| Logs | 诊断机制及明示技术状态 | 业务审计、业务用途和可信 Tenant；两类日志分开 | 不扩展本轮 Runtime |

应用层沿用 ThinkPHP 原生 Model/Scope 构造注入；Core/Application 的正式数据边界同样收敛到 ThinkPHP，不为隔离 ThinkPHP 增加 Repository 或洋葱式包装。本轮必要的 Core 技术接口及应用宿主 adapter 属于跨仓合同装配，不是应用 persistence 镜像层。可信 Tenant 必须由宿主 HTTP 请求或 Worker 上下文建立，缺失时 fail-closed。

## 3. 存储驱动候选合同与仓库事实

Core 在 `FileMedia\Storage` 提供低层 `StorageDriver`，只保留以下四个操作：

```text
put(objectKey, sourcePath)
delete(objectKey)
downloadTo(objectKey, destinationPath)
localPath(objectKey)
```

`StorageObjectKey` 只校验技术 key，不承载 Tenant、用途、授权或业务生命周期。Core 迁移四个 Driver：local、Aliyun、Qcloud、Qiniu。Aliyun/Qcloud/Qiniu 的具体 SDK 实例由应用组合根装配并注入具体 SDK 类型；Core aggregate 不强制三个厂商 SDK。可选 provider 只在 accepted dependency decision record、Composer `suggest` 与下游资格全部成立后采用；不换 Flysystem，不拆新包。

应用每次操作都解析完整配置快照；Core 不读取 Settings 或当前 Tenant 的全局可变值，也不缓存可变 client。对象按应用账本的 space 定位，prefix 不能代替授权；本边界不新增 fallback。

七牛需要的 HTTP 能力只通过窄 `StorageHttpTransport` 契约提供。应用适配器映射现有 `OutboundHttpTransport` 的 timeout、retrySafe、sink、multipart，不复制完整 HTTP DTO 或建立第二套传输栈。

`ObservedStorageDriver`、账户空间路由、凭据解密、用途、授权、对象账本和补偿均归应用。Core 不引用应用仓、不启用现有另一条 FileMedia 生命周期/Schema、不改表。整文件 HTTP 下载属于后续建议，不进入本执行队列。

历史 Alpha.13 固定资格和发布身份保持不变。新统一产品 Core 固定 source candidate
`985ee420b486a97325cdd0bb87412a76346aeb93` 已通过 3.1.0 的 Q01/D05；Composer split
`047f8e035c4ae14ab5a75581fdbb2a51be126787` 与候选 `packages/php` 投影相同。`v3.1.0` 已发布到
GitHub、npm 与 Packagist，当前 Application 3.1.0 开发线已用不可变 Registry 身份锁定，不依赖
branch、path repository 或复制 vendor；Application 完整资格与发布仍是后续 Gate。

旧应用 commit `590e61830d0e62c0bf25425dfe43d69ae894b726` 是基于过时 `dev` 的单提交采用
实验，不是 merge。它先重放为 `64460af8`，再以 `563df8c4` 进入统一收敛分支；冲突处理保留了最新
Edition-aware ledger、Tenant ownership、凭据解析和 Host adapter，只删除应用重复的四个低层 Driver。
同一收敛线现已把 PHP/npm manifest 与 lock 更新到 Core 3.1.0，并实际构造 Local、Aliyun OSS、
Tencent COS、Qiniu 四种 Driver。它在合入应用 `dev/main` 和通过应用固定候选资格前仍是开发候选，不能
描述为应用正式 Release 或真实云账号生产可用。

完整 Core 能力、应用实际调用和版本限制见[Core 能力与独立应用采用全景](../reference/core-capabilities-and-application-adoption.md)；脚手架职责样本见[后台脚手架的 Core、公共模块与生成应用边界](../reference/scaffold-core-boundary-comparison.md)。

## 4. 历史合同的处理

历史 PB04 决策和证据不再作为现行迁移合同；本页对存储边界的冻结设计已经替代其未来方向。任何“已完成迁移”仍须以新 Runtime 代码、依赖锁和固定资格证据为准。

## 5. 注释规则

新增或实质修改的类和方法使用简洁职责注释；复杂方法说明 Tenant/授权前置、副作用、异常以及流或临时文件 owner。清晰继承接口文档可直接承载该职责，不复述原生类型。标准 CRUD、访问器和仅注入构造函数可豁免方法注释，但类职责仍保留。Tenant 权限、事务、幂等、软删和外部副作用不得豁免。变量只解释不显然的单位、不变量或安全缘由。Core 使用英文注释，应用沿用局部语言。
