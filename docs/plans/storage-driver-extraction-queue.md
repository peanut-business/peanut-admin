# Storage Driver 提取决策与后续队列

> 当前状态（2026-09-09 复核）：窄 Storage Driver 已由 Core `0.1.0-alpha.13` 发布并随 Application v3.0.14 正式采用。应用合入与该版本资格见 [v3.0.14 不可变快照](../product-status/releases/v3.0.14.json)，不再是待合入候选；真实云账号资格仍未完成。
>
> “先审计、后决定”是当时的工作顺序，不是永久人工 Gate。本文保留采用来源与剩余资格边界，不重复领取已发布采用，也不以源码支持代替真实厂商资格。

## 采用决定

全景审计已经确认 Storage Driver 属于产品无关低层技术机制，而账户/空间、凭据、Tenant、用途、授权、
对象账本、补偿和业务生命周期继续由应用拥有。因此采用窄 Core Driver，且不把高层 FileMedia、Schema
或 Provider SDK 装配移入 Core。判断依据集中在：

- [Core 能力与独立应用采用全景](../reference/core-capabilities-and-application-adoption.md)：两个 aggregate、内部能力域、Core 参考宿主、独立应用真实调用和数据 owner。
- [后台脚手架的 Core、公共模块与生成应用边界](../reference/scaffold-core-boundary-comparison.md)：LikeAdmin、FastAdmin、MineAdmin、RuoYi-Vue-Plus 的固定源码机制链与受限启示。
- [Core 与应用技术边界](../architecture/core-application-technical-boundary.md)：若采用 Storage Driver 时必须保留的低层合同和应用职责。

旧暂停决定已经解除。Core 先完成固定候选资格与 Alpha.13 发布，应用随后锁定公开包并吸收拉平后的
单提交采用差异；该顺序避免了 class-not-found 和 path repository 假集成。

## 当前仓库事实

| 产物 | 固定身份 | 当前状态 | 能证明什么 |
| --- | --- | --- | --- |
| Core Storage Driver Release | source `a949a77728f2940153c6cfd76b104d5d8bb183e3`；Composer split `61f40dc2412338b4dfdcf7d2cd7514da45ea773a` | `0.1.0-alpha.13` 已发布到 GitHub/npm/Packagist | 四操作合同、对象 key、HTTP transport 与四个 Driver 的不可变发布身份 |
| 独立应用采用前历史基线 | `e38e45d07752cd6b4834fbe4483bfd2dcaf5a95d` | 历史证据，不是当前 dev | 旧应用 Driver 与 Core Alpha.12，仅用于比较采用差异 |
| 独立应用正式采用 | Storage 提交 `563df8c4`；正式身份见 v3.0.14 Release 快照 | 已合入并随 v3.0.14 发布，锁定 Core PHP/Web Alpha.13，应用低层重复 Driver 已删除 | 四 Provider 构造与同版本固定资格有历史证据；不证明真实云账号资格 |

## 候选边界

已采用的 Core 低层 `StorageDriver` 只保留：

```text
put(objectKey, sourcePath)
delete(objectKey)
downloadTo(objectKey, destinationPath)
localPath(objectKey)
```

Core 的 `StorageObjectKey` 只做技术 key 校验。Local、Aliyun、Qcloud、Qiniu Driver 接受宿主装配的必要根目录、SDK client 或 HTTP transport。Core 不读取当前 Tenant 的全局可变配置，不缓存 Tenant/account 可变 client，不取得应用文件生命周期或高层 FileMedia Schema 的 owner。

独立应用继续拥有 provider SDK 依赖与装配、账户/space 路由、凭据解密、用途、授权、对象账本、`ObservedStorageDriver`、补偿和产品生命周期。对象 prefix 不代替授权，不新增 fallback；整文件 HTTP 下载仍是独立优化议题。

当前 `StorageDriverFactory` 仍保留 Local、Aliyun OSS、Tencent COS 与 Qiniu 四种 provider；提取的目标是
移动低层驱动所有权，不是减少厂商支持。Alpha.13 已包含七牛返回 key 一致性与删除 endpoint 修复。
应用同一候选上的 Composer autoload、四 Provider 构造、Host adapter 和按次凭据解析已经验证；Core 的
Driver 行为合同已在 Alpha.13 固定资格中执行。真实厂商账号的上传、下载、删除、补偿与凭据轮换尚未在
本应用候选重新资格，因此只阻塞对应厂商的生产可用声明，不撤销源码支持或 Local 开发能力。

采用 Core `LocalStorageDriver` 时必须重新审定路径与符号链接边界、原子写入和失败补偿。此前在“应用尚未采用
Core LocalDriver”前提下接受的风险不能自动继承给当前 `563df8c4` 采用结果。

## 高容量媒体 Spike 的来源与处置

高容量实验已固定在本地分支 `quarantine/high-capacity-media-storage-spike-20260831`，head
`e915bea74cba1d6d646212d51014474a94f5e1fe`。它不是当前 `dev`/`main` 的祖先，也不是多个产品功能分支
合并后的 Runtime；Git 谱系是基于旧应用提交 `4e047485` 的三个连续实验提交：`e3c940ea`、`a5b2f604`、
`e915bea7`。

实验目录验证了 SeaweedFS 4.44 S3 thin adapter、multipart intent/part/complete、checksum/range、Tenant/ACL/
object key、quota reservation、scan/quarantine、derivative、retention/legal hold/deletion 以及容量/成本模型。
现有证据只覆盖约 5 MB 单节点样本和 11 项实验检查；没有证明并发配额、GB/TB 性能、HA、Object Lock/KMS、
生产安全或与 `official.file`/Rich Text 的正式生命周期集成。

因此当前处置是：保留 quarantine 分支作为设计与测试输入，不合入产品 Runtime，不部署实验 SeaweedFS，
也不把它写成厂商存储支持的一部分。后续若领取大媒体能力，应从当前 `dev` 新建纵向微批次，复用已经验证的
协议语义，并重新接入 FileMedia/Storage owner、Tenant/RBAC、配额、扫描、补偿和真实容量资格。

## 决定后的最小队列

| 顺序 | 任务 | 写集/禁止项 | 验收 | 规则、owner 与模型 | 当前状态 |
| --- | --- | --- | --- | --- | --- |
| D0a | 盘点 Core、应用采用和脚手架边界，形成建议 | 只改本轮登记文档；不改 Runtime | 每域说明用途、入口、宿主责任、data owner、采用与证据限制 | 文档/CodeGraph 规则；Terra/medium 只读研究，Sol/high 合成，根代理审计 | 盘点与建议已完成 |
| D0b | 形成有依据的采用建议和具体写集 | 只做决定和必要文档；不自动改 Runtime | 建议对应 D0a 的真实调用、data owner、宿主责任与维护成本；保持现有窄 Driver 候选，不扩大高层 FileMedia | 根代理负责方向/审计 | 已完成：采用窄低层合同 |
| D1 | 冻结最终公共合同与发布粒度 | Core Storage 文件、必要 dependency decision；不扩展高层 FileMedia/Schema | API、可选 SDK、兼容关系和 owner 明确 | 公共合同与依赖规则；高能力模型复核 | 已完成并进入 Alpha.13 |
| D2 | 生成新的不可变 PHP split 身份 | Core 版本/发布元数据按正式发布流程；不得用 branch、path repository 或 vendor 复制替代 | 固定 source/tree/split、包可见性和 Composer metadata 一致，并完成 Core 正式发布所要求的固定候选资格 | 发布规则；根代理终审 | 已完成：source/split/tag/Registry 均固定 |
| D3 | 在独立应用基于最新 `dev` 重放最小采用 diff，并更新 Composer lock | `AppService`、`common/service/storage`、现有 FileMedia Host gate 与精确 lock；不新建兼容桥/第二生命周期 | lock 指向 D2；provider 装配、对象 key、观测、账本/授权/补偿语义保持 | 应用边界与不可变依赖规则 | 已完成并随 v3.0.14 发布 |
| D4 | 完成应用日常开发验证并合入 | 运行受影响 PHP lint、现有 FileMedia Host/直接 Tenant 安全组和文档检查；不主动扩大到无关开发组 | 同一应用候选上通过，失败按项目一次诊断/一次重跑规则 | 日常开发 §7.1；根代理终审 | 已完成；v3.0.14 快照记录同版本资格，不继承为后续版本资格 |

此前“保留应用实现、不采用 Core”的备选已被正式采用决定替代，不是当前待选项。

D4 只描述应用采用阶段的日常开发检查，不豁免正式依赖采用或发布资格。D2 的 Core 发布以及应用正式锁定新 Core 的资格仍遵循 `AGENT_EXECUTION_RULES.md` §7.1/§7.2 对固定候选、不可变身份和授权的要求；本轮文档审计不运行或降级这些 Gate。

## 发布与升级成本

Core 当前只有 `peanut-admin/core` 与 `@peanut-admin/admin` 两个 aggregate，独立应用分别用 Composer/npm lock 固定。当前联动发布意味着窄 PHP 改动仍会触及聚合版本、兼容关系和下游升级；移动四个类本身不能消除这些维护成本。

是否把少数域改成独立发布单元属于后续架构评估。评估至少比较依赖图、PHP/Web 兼容矩阵、版本联动、发布失败原子性、应用 lock 更新和升级文档；没有证据时既不自动拆成 13 个包，也不把现有两个 aggregate 写成永久最优。

## 不属于本轮完成状态

本页不把尚未执行的真实云 Provider、新版本资格或生产部署标记为完成；已执行的 v3.0.14 资格与 Release 保留其历史身份。高容量媒体
Spike 继续是隔离设计输入；`CrossProductAdoptionHost` 的 Collaboration 失效引用仍由独立删除或能力
重建决定处理，不借 Storage 采用扩大范围。
