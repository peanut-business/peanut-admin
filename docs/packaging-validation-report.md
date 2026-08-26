# Module 打包验收报告

## 运行信息

- 时间：2026-08-26T17:51:02Z
- candidate：`2d8c1d2f4fb91870d3a31ae206e0bf4130945417`
- 证据来源：`/tmp/module-packages-test/packaging-results.json`

## Module 包结果

| Module key | 包大小（字节） | SHA-256 | 状态 |
| --- | ---: | --- | --- |
| `official.article` | 122880 | `6eaf27e0e10cee037dc78a7ca12b47060deaf409b8a9f209b2e0b8bfd94d5c1f` | 通过（exit 0） |
| `official.file` | 79872 | `ad2990bb22cd3090247b36d1ea998f13e4e6f560f744e94462976d35bdd7adfc` | 通过（exit 0） |
| `official.task` | 75264 | `c89ea80195c67971455fbd9ce02f0915022ef89384287ca90baf3854c8a757f5` | 通过（exit 0） |
| `official.notification` | 99840 | `659993b27742517974be8a8f7980dfedb074ecb3b57351ba7bc670307d193300` | 通过（exit 0） |
| `official.member` | 183808 | `47b9590aeb1460670c728afbf6cc703096afaee431361c703282c1553870692b` | 通过（exit 0） |
| `official.payment` | 171008 | `103d24097a0c1bde7d7f94c5c9e30e7929b34519e147737a9bf0c5b315ef004e` | 通过（exit 0） |
| `official.oauth` | 181248 | `694c667b9e0d0b8da841d62c994e411f9c91aaf338502ace59c60946206d85f2` | 通过（exit 0） |
| `official.import-export` | 52224 | `cdb6bd2055319a350c92a743ca57c2424d33f466d295af68e3b7b6f8b3ae4cbd` | 通过（exit 0） |

## 生产构建与 tree-shake 检查

- 实际构建命令：`npm run build`
- 构建结果：exit 0
- 检查产物文件数：56
- 产物目录：`web/dist`
- 文件名中的 `dev-tools` 命中数：0
- 8 个符号哨兵命中数均为 0：

  | 哨兵 | 命中数 |
  | --- | ---: |
  | `dev-tools` | 0 |
  | `instance-tools/modules` | 0 |
  | `MODULE_RUNTIME_MUTATION_DISABLED` | 0 |
  | `platform.module.read` | 0 |
  | `platform.module.install` | 0 |
  | `platform.module.uninstall` | 0 |
  | `platform.module.disable` | 0 |
  | `platform.module.sync` | 0 |

## 已知问题与范围

- 已知问题：无（本次聚焦打包与 tree-shake 验收范围）。
- 本次未运行数据库生命周期验证。
- 本次未运行完整 P0-E。
- 本报告仅记录 JSON 证据；未据此臆造 Standalone/Multi-tenant 双构建结果。
