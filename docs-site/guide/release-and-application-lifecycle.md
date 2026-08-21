---
title: 发布与独立应用生命周期
description: 从 Peanut Admin tag 到 create-app、应用发布和脚手架升级的完整流程。
---

# 发布与独立应用生命周期

框架团队先把功能分支合入 `dev`，再把 `dev` 合入 `main`。最终资格必须绑定最新
`origin/main` 的固定 commit/tree；通过后才对同一 commit 创建 annotated tag，并人工运行
`scripts/publish-github-release` 发布 GitHub Release。当前仓库没有 tag push 自动发布 workflow，
所以“打 tag”是发布身份，不等于 GitHub 已经自动发布。

Core 的 Composer/npm 包有自己的仓库、版本和 tag；应用只有在更新 lock 并重新资格后才消费
新 Core。应用版本与 Core alpha 版本互不相同。

## 从发布版创建应用

`create-app` 在 Peanut Admin 已发布 tag 上执行：

```bash
git clone --branch v3.0.4 <peanut-admin-repository> peanut-admin-3.0.4
cd peanut-admin-3.0.4
php scripts/create-app --name="Acme Console" --slug=acme-console \
  --package=acme/acme-console --target=/absolute/path/to/acme-console
cd /absolute/path/to/acme-console
git init && git add . && git commit -m "chore: create app from Peanut Admin v3.0.4"
```

生成目录是新的应用仓库，包含 `.peanut/application-manifest.json` 和 managed baseline。manifest
记录采用的 scaffold release、source commit/tree、inventory 摘要、逐文件 SHA-256、文件归属
和应用版本，是升级与版本核实的指纹。它不会让应用自动跟随 Peanut Admin 源仓。

## 应用自己的发布和升级

应用有自己的 `dev`、`main`、tag 和 Release。Peanut Admin 新版可用时，应用 owner 先更新
Composer/npm lock 并做兼容检查，再运行 `scaffold-upgrade preflight/apply/verify`。升级器只
处理 `managed`/`generated-managed` 文件，保留 `app-owned` 业务代码；它不安装依赖、不执行
数据库 migration、不安装 Plugin，也不重启服务。

同一 3.x 版本线的数据库升级由应用发布步骤执行：

```bash
php server/database/install.php --migrate --target-version=X.Y.Z
```

执行前备份并检查 migration ledger，执行后做 health、登录和关键页面 smoke。3.0 是 fresh-only
大版本，旧大版本必须 fresh/rebuild；3.x 内 patch/minor 仍使用 append-only migration。
