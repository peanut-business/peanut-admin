---
title: 派生应用升级
description: 2.x 脚手架受管文件升级、应用代码所有权和数据库迁移边界。
---

# 派生应用升级

## 5 分钟结论

2.x 派生应用可以在两个不可变 scaffold Release 之间升级。当前已经用真实生成应用验证
`v2.0.0 -> v2.0.1` 的 `preflight -> apply -> verify -> recover` 闭环。

执行器只更新 Release manifest 中标为 `managed` 或 `generated-managed` 的 Peanut 框架文件。
你的 `app-owned` 业务代码、页面、业务配置、业务 Schema 和部署密钥不会被自动覆盖；发现
双方都修改同一受管文件时，计划会停止并要求人工处理。

## 操作顺序

先在应用独立分支和备份可用的前提下生成计划：

```bash
php scripts/scaffold-upgrade preflight \
  --project-root=/absolute/path/to/application \
  --from-manifest=/absolute/path/to/scaffold/releases/v2.0.0/scaffold-manifest.json \
  --to-manifest=/absolute/path/to/scaffold/releases/v2.0.1/scaffold-manifest.json
```

只有输出 `status=ready` 且冲突数为 `0` 时才继续：

```bash
php scripts/scaffold-upgrade apply \
  --project-root=/absolute/path/to/application \
  --plan=/absolute/path/to/application/.peanut/upgrades/plans/<candidate>.json

php scripts/scaffold-upgrade verify \
  --project-root=/absolute/path/to/application \
  --plan=/absolute/path/to/application/.peanut/upgrades/plans/<candidate>.json
```

如果 apply 后验证失败，使用同一个 plan 恢复：

```bash
php scripts/scaffold-upgrade recover \
  --project-root=/absolute/path/to/application \
  --plan=/absolute/path/to/application/.peanut/upgrades/plans/<candidate>.json
```

## 它不负责什么

脚手架执行器不运行 Composer/npm，不执行数据库 migration，也不重启应用服务。部署中的
前端、后端和数据库升级由 `scripts/deploy-release --upgrade --from <当前版本>` 负责，并且
必须使用同一个不可变 Release、显式 transition、配对备份和迁移账本。应用自己的业务 migration
仍由应用 owner 维护；2.0.0 也不接受 1.x 数据库或脚手架原地升级。

## 文件所有权

| 文件类别 | 升级器行为 |
| --- | --- |
| `managed` | 目标 Release 改动时可原子替换；应用也改过则计划阻塞 |
| `generated-managed` | 按应用 manifest 的名称、slug 和包身份重新生成 |
| `app-owned` | 只记录并验证保持不变，永不自动覆盖 |

完整故障恢复和 1.x 历史证据见源仓的 `docs/scaffold-upgrade.md`；它们不改变 2.x 的 fresh-only
安装边界。
