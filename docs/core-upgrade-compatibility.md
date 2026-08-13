# 核心包升级兼容矩阵

本 Gate 固定由正式 `scripts/create-app` 模板 `1.0.0` 生成的旧应用输入，并证明核心包升级只改变四个依赖文件：

- PHP：公开 Packagist 身份 `peanut-admin/core` 从 `0.1.0-alpha.2` 升级到 `0.1.0-alpha.5`；
- Web：公开 npm 身份 `@peanut-admin/admin` 从 `0.1.0-alpha.4` 升级到 `0.1.0-alpha.5`。

这两个旧起点不同，不构成统一的“平台 alpha.4”。`server/tests/fixtures/core-upgrade-compatibility/fixture.json`
固定 create-app 源 commit/tree、两个 Registry 身份、Composer split commit/tree、npm integrity、允许变化文件和
app-owned 探针。两个 lockfile 版本也作为 fixture 提交，CI 不解析 `latest`、移动分支或 path repository。

`scripts/core-upgrade-compatibility` 在干净临时目录重建应用，然后依次执行旧版干净安装、公开 Host/consumer
探针、Web typecheck/build、只替换约束和 lock 的升级，以及完全相同的新版检查。业务/app-owned 摘要和排除
四个依赖文件后的应用源码摘要在 before/after 必须逐字节相同。公共入口 Gate 从真实 Composer PSR-4 与 npm
`exports` 元数据解析允许集合，并拒绝 `vendor`/`node_modules` 内部路径、`src` deep import、跨包相对源码引用。
PC 和 UniApp 当前通过 `./client`、`./client/nuxt`、`./client/uniapp` 正式 exports 消费 UI core；它们只由同一
公共入口 Gate 覆盖，不虚构额外的独立升级矩阵。

## 已知 PHP 发布身份完整性缺陷

核心 monorepo `v0.1.0-alpha.4` 固定到 commit
`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`，但该 tag 内 `packages/php/composer.json` 仍声明
`0.1.0-alpha.2`，Packagist 也没有 `peanut-admin/core@0.1.0-alpha.4`。机器 Gate 必须真实观察到这个缺陷，
并禁止把它当作可消费 PHP 身份。未来 PHP 发布必须保证 monorepo release tag、内嵌 package metadata、生成的
Composer split tag 和 Packagist version 一致；本应用 Gate 不修改核心仓，也不发布或重打 tag。

## 本地执行

先按项目租约规则 claim 唯一 cache/output，再运行：

```bash
scripts/core-upgrade-compatibility \
  --cache /private/tmp/peanut-admin-core-upgrade-cache-<run-id> \
  --output /private/tmp/peanut-admin-core-upgrade-output-<run-id>
```

本 Gate 不连接数据库、端口、服务、浏览器或容器。输出仅包含可再生的临时 consumer 与 `summary.json`；
不得把 vendor、node_modules、dist、缓存或原始安装日志提交到仓库。
