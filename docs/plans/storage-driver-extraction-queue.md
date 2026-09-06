# Storage Driver 提取决策与后续队列

> 当前状态：Core 源码候选已实现，独立应用仍使用原实现；发布和 Runtime 迁移暂停在全景审计后的方案决定之前。
>
> 本次“先审计、后决定”是用户调整的当前工作顺序，不是永久人工 Gate。本文记录候选和恢复条件，不把未合 worktree、未发布源码或测试结果写成正式采用。

## 为什么先暂停采用

用户当前要先理解 Core 是技术工具集还是公共后台底座、独立应用到底用了哪些能力，以及主流后台脚手架如何划分公共机制与生成应用，然后再决定 Storage Driver 是否正式提取。现有判断依据集中在：

- [Core 能力与独立应用采用全景](../reference/core-capabilities-and-application-adoption.md)：两个 aggregate、内部能力域、Core 参考宿主、独立应用真实调用和数据 owner。
- [后台脚手架的 Core、公共模块与生成应用边界](../reference/scaffold-core-boundary-comparison.md)：LikeAdmin、FastAdmin、MineAdmin、RuoYi-Vue-Plus 的固定源码机制链与受限启示。
- [Core 与应用技术边界](../architecture/core-application-technical-boundary.md)：若采用 Storage Driver 时必须保留的低层合同和应用职责。

暂停只影响新的 package 发布、应用 lock 和 Runtime 迁移。Core 已合入的 Storage Driver 源码不回滚；应用旧候选继续保留供复审，本轮不合入。

## 当前仓库事实

| 产物 | 固定身份 | 当前状态 | 能证明什么 |
| --- | --- | --- | --- |
| Core Storage Driver 源码 | `peanut-admin-core` `9358686fee873dd235489c8794abf556fd70ec4f` | 已在 Core `dev` | 四操作合同、对象 key、HTTP transport 和四个 Driver 已实现；不证明已发布或被独立应用采用 |
| 独立应用 canonical | `peanut-admin` `72fcf7b9bfbae62aa5329f99c49ec1356435e633` | 当前 `dev` | 仍装配应用自己的 StorageDriver/四 Driver，锁定 Core/Web `0.1.0-alpha.12` |
| 独立应用采用候选 | `peanut-admin` `590e61830d0e62c0bf25425dfe43d69ae894b726` | 独立 worktree 保留 | 曾完成指向 Core 新边界的源码改造和聚焦静态检查；未合 canonical、未更新 lock、未形成正式消费 |
| 已发布 Core | source `9089516a18f19e19a048683594087e0b4ffc5455`；Composer split `9017212da0da63f445d693be94d533f681c6dc92` | `0.1.0-alpha.12` | 是当前应用锁身份；不含 `9358686` 新增的 Storage Driver |

## 候选边界

若后续决定采用，Core 低层 `StorageDriver` 只保留：

```text
put(objectKey, sourcePath)
delete(objectKey)
downloadTo(objectKey, destinationPath)
localPath(objectKey)
```

Core 的 `StorageObjectKey` 只做技术 key 校验。Local、Aliyun、Qcloud、Qiniu Driver 接受宿主装配的必要根目录、SDK client 或 HTTP transport。Core 不读取当前 Tenant 的全局可变配置，不缓存 Tenant/account 可变 client，不取得应用文件生命周期或高层 FileMedia Schema 的 owner。

独立应用继续拥有 provider SDK 依赖与装配、账户/space 路由、凭据解密、用途、授权、对象账本、`ObservedStorageDriver`、补偿和产品生命周期。对象 prefix 不代替授权，不新增 fallback；整文件 HTTP 下载仍是独立优化议题。

## 决定后的最小队列

| 顺序 | 任务 | 写集/禁止项 | 验收 | 规则、owner 与模型 | 当前状态 |
| --- | --- | --- | --- | --- | --- |
| D0a | 盘点 Core、应用采用和脚手架边界，形成建议 | 只改本轮登记文档；不改 Runtime | 每域说明用途、入口、宿主责任、data owner、采用与证据限制 | 文档/CodeGraph 规则；Terra/medium 只读研究，Sol/high 合成，根代理审计 | 盘点与建议已完成 |
| D0b | 形成有依据的采用建议和具体写集 | 只做决定和必要文档；不自动改 Runtime | 建议对应 D0a 的真实调用、data owner、宿主责任与维护成本；保持现有窄 Driver 候选，不扩大高层 FileMedia | 根代理负责方向/审计；未授权范围变化或正式发布按适用规则确认，无独立执行模型 | 采用建议已形成；正式推进待发布范围与依赖条件 |
| D1 | 若采用或调整，冻结最终公共合同与发布粒度 | Core Storage 文件、必要 dependency decision；不扩展高层 FileMedia/Schema | API、可选 SDK、兼容关系和 owner 明确 | 公共合同与依赖规则；Sol/high | 等待 D0b |
| D2 | 生成新的不可变 PHP split 身份 | Core 版本/发布元数据按正式发布流程；不得用 branch、path repository 或 vendor 复制替代 | 固定 source/tree/split、包可见性和 Composer metadata 一致，并完成 Core 正式发布所要求的固定候选资格与授权 | 发布规则；Sol/high，根代理审计 | 等待 D1 |
| D3 | 在独立应用基于最新 `dev` 重放最小采用 diff，并更新 Composer lock | `AppService`、`common/service/storage`、现有 FileMedia Host gate 与精确 lock；不新建兼容桥/第二生命周期 | lock 指向 D2；provider 装配、对象 key、观测、账本/授权/补偿语义保持 | 应用边界与不可变依赖规则；Sol/high | 等待 D2 |
| D4 | 完成应用日常开发验证并合入 | 运行受影响 PHP lint、现有 FileMedia Host/直接 Tenant 安全组和文档检查；不主动扩大到无关开发组 | 同一应用候选上通过，失败按项目一次诊断/一次重跑规则 | 日常开发 §7.1；安全/合同组 Sol/high，机械静态组 Luna/max，根代理终审 | 等待 D3 |

如果决定保留应用实现，D1-D4 不执行，只把 Core driver 标为未采用公共候选，并另外决定 Core 是否继续保留它。这个分支不要求删除已完成研究或伪造迁移证据。

D4 只描述应用采用阶段的日常开发检查，不豁免正式依赖采用或发布资格。D2 的 Core 发布以及应用正式锁定新 Core 的资格仍遵循 `AGENT_EXECUTION_RULES.md` §7.1/§7.2 对固定候选、不可变身份和授权的要求；本轮文档审计不运行或降级这些 Gate。

## 发布与升级成本

Core 当前只有 `peanut-admin/core` 与 `@peanut-admin/admin` 两个 aggregate，独立应用分别用 Composer/npm lock 固定。当前联动发布意味着窄 PHP 改动仍会触及聚合版本、兼容关系和下游升级；移动四个类本身不能消除这些维护成本。

是否把少数域改成独立发布单元属于后续架构评估。评估至少比较依赖图、PHP/Web 兼容矩阵、版本联动、发布失败原子性、应用 lock 更新和升级文档；没有证据时既不自动拆成 13 个包，也不把现有两个 aggregate 写成永久最优。

## 不属于本轮完成状态

本轮不修改 Core/Application Runtime、Composer/npm manifest 或 lock，不发布包、不运行数据库/云 provider/浏览器资格，也不修 `CrossProductAdoptionHost` 的 Collaboration 失效引用。后者已在全景文档登记为“桥接源码存在、仅测试 caller、当前 Core 无该能力”，应由独立的删除或能力重建决定处理。
