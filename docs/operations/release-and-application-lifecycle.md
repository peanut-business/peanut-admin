# 发布、脚手架与独立应用生命周期

这些身份要分开理解：

- **Core** 是独立 Composer/npm 依赖，按自己的仓库和 tag 发布。
- **Peanut Admin 源码 Release** 是唯一人工开发事实源，`main` 上的 tag 固定开发源码和资格身份。
- **Standalone / Multi-tenant 安装包** 是同一个 Release 生成的两种首次安装构建物，不是两套
  人工维护源码，也不是两个官方应用仓库。
- **Standalone / Multi-tenant 升级包** 只升级同一 Edition 的受管文件与 Schema 链，不能互换。
- **用户定制应用** 是用户用固定 Release 的 `create-app` 生成后自行拥有的业务仓库；它不是
  Peanut Admin 的第二个官方源仓，也不会自动跟随 `dev/main`。

## 框架团队的发布顺序

```text
功能分支
  → PR 合入 dev
  → 在 dev 上完成聚焦检查
  → dev 合入 main
  → 从最新 origin/main 固定 candidate
  → 按风险运行资格（登录/密码/租户/依赖/scaffold 变更属于 L2，运行完整 P0-E）
  → 对同一 main commit 创建 annotated vX.Y.Z tag
  → 从同一 commit 生成两个安装包；非首个 Edition 基线再生成两个签名升级包
  → scripts/publish-github-release 一次发布源码和全部 Edition 附件
  → Demo 消费正式 Multi-tenant 安装包并叠加仅含合成数据 seed 的受控 overlay
  → 文档站采用同一版本和下载入口
```

当前仓库没有“push tag 后自动发布”的 GitHub Actions；tag 是不可变身份，发布由
`scripts/publish-github-release` 在资格通过后人工执行。若未来增加自动化，自动任务也必须只消费
已存在的 annotated tag，并再次核对 `RELEASE_METADATA.json`、candidate lock、远端 tag 和
Registry 身份，不能把移动的 `dev` 当作发布输入。

Core 的 tag/Registry 发布是独立流程。Peanut Admin 的应用版本与 Core alpha 版本不必相同；只有
当应用更新 Composer/npm lock 后，才把新 Core 版本作为应用候选的一部分重新资格化。

## 普通用户先下载，定制用户才执行 create-app

普通用户首次安装时，直接在 GitHub Release 选择 Standalone 或 Multi-tenant 安装包，不需要
克隆开发仓库，也不需要先运行 `create-app`。已有应用升级时只下载当前 Edition 对应的升级包；
完整安装包不能覆盖已有目录。

只有需要自定义产品名称、slug、package identity 或继续开发业务代码时，才执行 `create-app`。
它在 Peanut Admin Release 已发布之后运行，而不是在 `dev` 上创建用户应用：

```bash
git clone --branch vX.Y.Z <peanut-admin-repository> peanut-admin-X.Y.Z
cd peanut-admin-X.Y.Z
php scripts/create-app \
  --name="Acme Console" \
  --slug=acme-console \
  --package=acme/acme-console \
  --target=/absolute/path/to/acme-console \
  --edition=standalone
cd /absolute/path/to/acme-console
git init
git add .
git commit -m "chore: create application from Peanut Admin vX.Y.Z"
```

生成物必须包含 `.peanut/application-manifest.json`。其中记录应用自己的版本、采用的
scaffold release、source commit/tree、inventory 摘要、逐文件 SHA-256、mode、classification、
owner 以及 managed/app-owned 树摘要。这是核实生成版本和后续升级的指纹；它不记录密码。
需要多租户产品时把 `--edition` 改为 `multi-tenant`。Edition 会进入应用 manifest、Schema 和
升级兼容边界，不是在部署时随意切换的配置。

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
