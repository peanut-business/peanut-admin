# 发布能力快照

本目录只在**正式产品发布**时新增不可变快照，不按脚手架 release、普通 PR 或开发分支生成。
当前最新快照为 [`v3.0.14.json`](v3.0.14.json)，包含最终固定候选的八组 P0-E、annotated tag、GitHub Release、源码制品、双 Edition 安装包与首组同 Edition 升级包证明。登记的多租户 Demo 已采用 v3.0.14 基础源码加可追溯 overlay，独立证据位于 `docs/product-status/deployments/`；既有 Standalone 演示仍是独立旧部署，两者都不冒充第三方业务生产部署。

快照应由发布流程从当时的 `capability-ledger.json` 生成，并记录：

- 产品版本、annotated tag、commit 和 tree；
- ledger schema 版本与完整 SHA-256；
- 各能力 ID、状态、验收证据和明确未完成项；
- 发布、部署、恢复与最低 smoke 证据。

已经提交的快照禁止原位修改。勘误应新增带原因的补充记录，后续能力变化进入新的发布快照。
