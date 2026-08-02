# Peanut Admin P0-A01 执行记录

> 状态：Complete（2026-07-15）
>
> 对应任务：`45-g09-p0-execution-and-acceptance-plan.md` 的 P0-A01
>
> 结论：干净仓、治理入口、Apache-2.0 和首次公开推送均已完成；P0-A02 尚未开始

## 1. 批准和边界

用户使用以下完整批准语放行执行：

```text
批准按 48 号复审结论开始 P0-A 运行时代码；Peanut Admin 顶层许可证采用 Apache-2.0。
```

同时确认：

- Finance Manager 已基于 LikeAdmin PHP 开发并基本成型，不迁移 Peanut Admin。
- Peanut Admin 是新的公开通用底座，不继承旧 base-framework 的 Git 历史和问题代码。
- 本任务只执行 P0-A01，不创建运行时目录，不继续 P0-A02。

## 2. 仓库结果

| 项目 | 结果 |
| --- | --- |
| GitHub organization | `peanut-opensource` |
| 公开仓库 | `https://github.com/peanut-opensource/peanut-admin` |
| SSH remote | `git@github.com:peanut-opensource/peanut-admin.git` |
| 当前本地路径 | `/Users/xing/Documents/company-os/repositories/peanut-admin/` |
| 默认分支 | `dev` |
| 根提交 | `de68cbc chore: initialize peanut admin repository` |
| 可见性 | Public |
| 顶层许可证 | Apache-2.0 |

远端由已登录的 GitHub 组织页面创建为空仓，再通过已验证的 SSH 身份推送。本地和远端 `dev` 均指向 `de68cbc5034b6845f1ca993cc0f98a106a51169e`。P0-A01 完成后，本地仓按中央代码工作区决策从 `/Users/xing/Documents/Dev/Project/peanut-admin/` 迁移到当前路径，Git 历史和 remote 未改变。

## 3. 初始化文件

根提交严格只包含 P0-A01 白名单中的十个文件：

```text
AGENTS.md
README.md
LICENSE
NOTICE
.gitignore
.editorconfig
docs/README.md
docs/content-status.json
scripts/check
scripts/check-doc-content-status
```

没有创建 `backend/`、`frontend/`、`packages/`、`templates/` 或 `examples/`。公开文件不包含 company-os 私有路径、个人本机路径、Finance Manager、DCS 或旧 base-framework 实现内容。

## 4. 固定门禁

- `LICENSE` 使用 Apache-2.0 标准全文，并由 `scripts/check` 校验固定 SHA-256。
- 文档登记使用 `docs/content-status.json` 和 PHP 标准 JSON parser，不自研 YAML parser。
- `scripts/check-doc-content-status` 检查文档遗漏、重复、失效路径和字段状态。
- `scripts/check` 检查精确文件边界、许可证、文档登记、旧概念/具体业务污染和禁止的运行时目录。
- `AGENTS.md` 禁止读取旧仓实现、恢复旧计划、写入具体业务以及安装未通过 DDR 的依赖。

## 5. 验证证据

以下验证均通过：

```text
./scripts/check
Documentation status: OK (1 documents)
P0-A01 checks: OK

gitleaks git --no-banner --redact --exit-code 1 .
1 commits scanned
no leaks found

git status --short --branch
## dev...origin/dev

git log --oneline --decorate --all
de68cbc (HEAD -> dev, origin/dev) chore: initialize peanut admin repository
```

GitHub public API 复核结果：

- `full_name = peanut-opensource/peanut-admin`
- `visibility = public`
- `default_branch = dev`
- `license.spdx_id = Apache-2.0`

## 6. 下一停止线

P0-A01 已通过验收。下一个且唯一允许的写任务是 P0-A02“P0 依赖 DDR”，最低模型为 `gpt-5.6-sol`。

P0-A02 必须从目标仓最新 `dev` 和 `de68cbc` 开始，按 45 号文件白名单独立执行；不得提前创建 workspace、安装依赖、写后端/前端运行时代码，也不得并行启动 P0-A03 及以后任务。
