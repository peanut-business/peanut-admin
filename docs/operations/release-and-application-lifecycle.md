# 发布、脚手架与独立应用生命周期

这三个身份要分开理解：

- **Core** 是独立 Composer/npm 依赖，按自己的仓库和 tag 发布。
- **Peanut Admin** 是框架/脚手架源仓，`main` 上的应用版本 tag 发布源码和 scaffold release。
- **独立应用** 是用户从已发布 Peanut Admin tag 生成后拥有的另一个 Git 仓库；它不是源仓的
  工作树，也不会自动跟随源仓。

## 框架团队的发布顺序

```text
功能分支
  → PR 合入 dev
  → 在 dev 上完成聚焦检查
  → dev 合入 main
  → 从最新 origin/main 固定 candidate
  → 按风险运行资格（登录/密码/租户/依赖/scaffold 变更属于 L2，运行完整 P0-E）
  → 对同一 main commit 创建 annotated vX.Y.Z tag
  → scripts/publish-github-release 发布 GitHub Release
  → 使用同一 tag 部署单租户或演示环境
```

当前仓库没有“push tag 后自动发布”的 GitHub Actions；tag 是不可变身份，发布由
`scripts/publish-github-release` 在资格通过后人工执行。若未来增加自动化，自动任务也必须只消费
已存在的 annotated tag，并再次核对 `RELEASE_METADATA.json`、candidate lock、远端 tag 和
Registry 身份，不能把移动的 `dev` 当作发布输入。

Core 的 tag/Registry 发布是独立流程。Peanut Admin 的应用版本与 Core alpha 版本不必相同；只有
当应用更新 Composer/npm lock 后，才把新 Core 版本作为应用候选的一部分重新资格化。

## 什么时候执行 create-app

`create-app` 在 Peanut Admin Release 已发布之后执行，而不是在 `dev` 上创建用户应用：

```bash
git clone --branch v3.0.4 <peanut-admin-repository> peanut-admin-3.0.4
cd peanut-admin-3.0.4
php scripts/create-app \
  --name="Acme Console" \
  --slug=acme-console \
  --package=acme/acme-console \
  --target=/absolute/path/to/acme-console
cd /absolute/path/to/acme-console
git init
git add .
git commit -m "chore: create application from Peanut Admin v3.0.4"
```

生成物必须包含 `.peanut/application-manifest.json`。其中记录应用自己的版本、采用的
scaffold release、source commit/tree、inventory 摘要、逐文件 SHA-256、mode、classification、
owner 以及 managed/app-owned 树摘要。这是核实生成版本和后续升级的指纹；它不记录密码。

应用 owner 随后为自己的仓库补充 `resources/project-resources.json`、域名、端口、数据库、外部
服务和凭据引用。应用的业务代码、Host/override、页面和业务 migration 属于 `app-owned`，与
Peanut Admin 源仓的 `dev/main` 没有 Git 跟随关系。

## 独立应用自己的发布

独立应用有自己的 `dev`/`main`、PR、tag 和 Release。用户在该仓库开发并发布的是应用版本，
不是再次发布 Peanut Admin 源仓。应用发布前仍需按自己的资源登记执行安装、构建、迁移和 smoke。

## Peanut Admin 后续升级

当新的正式 Edition 升级包可用时，应用 owner 下载与当前 Edition 相同的包、从正式发布入口取得
受信公钥并解压，然后先生成只读计划：

```bash
export PEANUT_UPGRADE_TRUSTED_KEYS_JSON='{"<official-key-id>":"<base64-ed25519-public-key>"}'
php /path/to/extracted-upgrade/upgrader/scripts/scaffold-upgrade preflight \
  --project-root=/path/to/app \
  --package=/path/to/extracted-upgrade \
  --signature-key-id=<official-key-id>
php /path/to/extracted-upgrade/upgrader/scripts/scaffold-upgrade apply \
  --project-root=/path/to/app --plan=/path/to/app/.peanut/upgrades/plans/<candidate>.json
php /path/to/extracted-upgrade/upgrader/scripts/scaffold-upgrade verify \
  --project-root=/path/to/app --plan=/path/to/app/.peanut/upgrades/plans/<candidate>.json
```

升级器只处理 manifest 标记为 `managed` 或 `generated-managed` 的文件；`app-owned` 文件被
保留并在计划中记录摘要。旧的显式 `--from-manifest/--to-manifest` 入口继续用于维护者诊断；普通
用户不再自行拼装升级输入。`recover` 可以恢复本次文件替换，但它不执行 Composer/npm 安装、
Plugin 安装、数据库 migration 或服务重启。

因此一次完整的应用升级必须拆成可核验的步骤：

1. 在应用分支更新 Peanut Admin/Core 版本和 Composer/npm lock，执行锁定安装与兼容检查。
2. 对同一应用版本执行 `scaffold-upgrade preflight/apply/verify`，必要时 `recover`。
3. 备份后执行 `php server/database/install.php --migrate --target-version=X.Y.Z`，检查
   `pa_schema_migration` 账本和数据验证。
4. 构建并重启应用，运行 health、登录和关键页面 smoke，再合入应用自己的 `main` 并打 tag。

3.0 是 fresh-only 大版本：旧大版本数据库不能直接交给迁移器；必须按发布计划 fresh/rebuild，
另行设计数据导出/导入。3.x 内的 patch/minor 仍使用标准 append-only migration，不会因为
“fresh-only 大版本”而禁用 migration 功能。
