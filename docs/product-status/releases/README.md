# 发布能力快照

本目录只在**正式产品发布**时新增不可变快照，不按脚手架 release、普通 PR 或开发分支生成。
当前最新快照为 [`v3.0.5.json`](v3.0.5.json)，状态是 `production-demonstrated`，并包含固定候选的 P0-E 与双部署证明。

快照应由发布流程从当时的 `capability-ledger.json` 生成，并记录：

- 产品版本、annotated tag、commit 和 tree；
- ledger schema 版本与完整 SHA-256；
- 各能力 ID、状态、验收证据和明确未完成项；
- 发布、部署、恢复与最低 smoke 证据。

已经提交的快照禁止原位修改。勘误应新增带原因的补充记录，后续能力变化进入新的发布快照。
