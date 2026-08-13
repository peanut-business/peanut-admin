# 脚手架升级预检

新应用由 `scripts/create-app` 创建，并在 `.peanut/application-manifest.json` 记录完整
`managed` / `generated-managed` / `app-owned` 分类和逐文件生成基线。该创建 manifest 是
未来跨版本 apply/三方合并的输入身份；当前 preflight 仍不执行 apply 或 recovery。

首个纵向切片只处理应用仓中的脚手架和 Host 文件。它不安装 Plugin、不运行 Module 或数据库迁移、不连接生产，也不修改项目业务文件。

每个 release 在 `scaffold/releases/<version>/scaffold-manifest.json` 固定受管文件路径、该 release 的基线 SHA-256、策略和执行 owner。策略为：

- `managed`：项目未修改且上游变化时计划自动替换；
- `merge`：仅项目修改时进入后续执行器的人工合并队列；
- `preserve`：项目修改或策略要求时保留；
- `generated`：未修改时计划重新生成，项目修改时要求人工确认；
- `deprecated`：只报告迁移提示，不静默删除；
- `manual`：始终交给人工步骤。

运行 dry-run：

```bash
php scripts/scaffold-upgrade preflight \
  --project-root=/absolute/path/to/application \
  --from-manifest=/absolute/path/to/old/scaffold-manifest.json \
  --to-manifest=/absolute/path/to/new/scaffold-manifest.json
```

命令以旧 release 基线、项目当前文件和新 release 基线做三方判定。双方修改、受管文件缺失、rename 目标已存在或路径类型冲突会令计划状态变为 `blocked`，退出码为 `2`；路径越界、符号链接或 manifest 错误退出 `1`。它不会覆盖项目文件。

输出位于项目的 `.peanut/upgrades/`：

- `plans/<candidate>.json`：稳定的跨 Host/backend/frontend 执行协议；
- `backups/<candidate>/files/` 和 `recovery.json`：受管文件可恢复副本与恢复清单；
- `ledger.ndjson`：append-only 候选账本。同一输入重复 dry-run 使用同一 candidate，不追加重复记录。

当前仍需人工处理：所有双方修改冲突、`merge` 队列、deprecated 迁移提示，以及未来 apply 执行器落地前的实际文件替换。
