# Consumer-ready 资料与证据保留登记

Document ID: `pa-docs-maintenance-consumer-ready-evidence-retention`

Status: `current`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Upstream: [`Consumer-ready product plan`](../plans/consumer-ready-product-plan.md)、
[`Document registry`](../document-registry.json)、[`Product capability ledger`](../product-status/capability-ledger.json)
和 Git tree `5204bae49cb60a36dee974dce68d20e38425035b`。

## 1. 适用范围

本登记记录 CR01 的路径级保留决定和 CR02 的第一批安全清理。它不替代 Release、能力账本、
scaffold manifest 或 Git 历史，也不授权按目录年龄批量删除。`unknown` 表示仍有合同或外部引用
事实未冻结，必须保留到对应 owner 给出新的路径级决定。

盘点基线为 `origin/dev=8735a669a3669d0628a1d02db2d1cbf02e3b823c`；当时 `output/`
共有 318 个 tracked 文件，约 16.36 MiB。下表候选在该基线上均为 tracked，未发现同路径的
ignored 或 untracked 文件，并可从可达 Git 历史恢复。

## 2. 路径级决定

| 路径 | Git / 登记状态与 owner | 入站引用和证据价值 | 体积 | 决定与风险 |
|---|---|---|---:|---|
| `output/p0e-p0e55c3309/` | tracked；无文档登记 | 除本计划外无入站引用；旧候选 `55c330…` 的 7/7 结果未被 Release、能力账本或 PC70 消费 | 10 文件 / 1,396 KiB | `delete`；CR02 删除，Git 可恢复 |
| `output/p0e-p0e8221e27/` | tracked；无文档登记 | 除本计划外无入站引用；旧候选 `8221e…` 的 7/7 结果未被 Release、能力账本或 PC70 消费 | 10 文件 / 1,392 KiB | `delete`；CR02 删除，Git 可恢复 |
| `output/playwright/mt05/` | tracked；无文档登记 | 仅为 `prepared, not-yet-executed` harness，不是通过证据；当前 PC70 资格已替代其用途 | 3 文件 / 36 KiB | `delete`；CR02 删除，Git 可恢复 |
| `server/database/import.php` | tracked；无文档登记 | 无仓内 Runtime 调用，但仍由 application template inventory 导出 | 1 文件 / 1.7 KiB | `unknown`；删除会改变 scaffold，等待 scaffold owner 决定 |
| `scripts/scaffold-doctor` | tracked；无文档登记 | inventory 与 v3.0.0—v3.0.9 immutable scaffold manifest/制品均导出 | 1 文件 / 4.0 KiB | `keep`；保持既有 scaffold 追溯链 |
| `docs-site/deployment.md`、`platform.md`、`product-status.md`、`troubleshooting.md` | registry `archived`；owner `application-maintainers` | VitePress 已排除，但 inventory、registry、生成器、能力账本或测试仍有入站引用 | 4 文件 / 约 11.7 KiB | `unknown`；保持物理文件，不得单删 |
| `docs-site/guide/reading-guide.md`、`release-and-application-lifecycle.md`、`scaffold-upgrade.md` | registry `archived`；owner `application-maintainers` | inventory 与 registry 仍引用；reading guide 含 `docs.example.com` 占位链接 | 3 文件 / 约 10.5 KiB | `archive`；保持物理文件，CR23 统一决定是否退出 template |
| `docs-site/guide/user-manual.md` | registry `archived`；owner `application-maintainers` | inventory、registry 与 `ApplicationCreator` 仍引用 | 1 文件 / 31.7 KiB | `unknown`；等待消费者指南替代和生成器合同收敛 |
| `output/p0e-p0e78e9667/`、`p0e-p0e215b/`、`p0e-pc11e1/`、`p0e-p0e210a1/`、`p0e-p0e211b2/`、`p0e-pc70q14/` | tracked；Release/能力状态上游 | 正式 Release、能力账本或当前 PC70 资格存在入站引用 | 不作为清理收益计算 | `keep`；不可变发布与当前资格追溯链 |

未发现可追加为第一批清理的其他高置信零引用 `output/` 或 archived 文档。仓内扫描无法证明
GitHub、外部文档站或第三方收藏不存在深链，因此 CR02 只删除不承担公共文档入口的三个 output
路径；文档和 scaffold 路径全部保留。

## 3. CR02 第一批删除报告

本批删除上述三个 `delete` 路径，共 23 个 tracked 文件、2,824 KiB 基线体积。没有删除
Release/部署快照、当前 PC70 summary、资源登记、用户修改、scaffold 文件或现行文档。

合入后如需恢复，使用删除前固定基线，而不是移动分支名：

```bash
git restore --source=8735a669a3669d0628a1d02db2d1cbf02e3b823c -- \
  output/p0e-p0e55c3309 \
  output/p0e-p0e8221e27 \
  output/playwright/mt05
```

恢复只重建 Git 中的历史文件，不恢复当时 summary 记录的本机临时目录、容器、数据库或租约；
这些资源不属于本批删除对象。

## 4. 后续停止线

- `server/database/import.php` 只能在 scaffold 正式合同决定和 create-app 聚焦验证中处理。
- archived docs 只能在 CR22 已形成替代消费者入口后，由 CR23 同步 registry、inventory、生成器、
  测试和已知链接；不得逐文件删除。
- Git 历史不压缩、不重写；Marketplace、真实 Provider 资格和跨实例运营平台不因本清理扩大范围。
