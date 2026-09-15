# 本地核心库即时开发

应用的正式 Composer 清单保持固定版本和远程包来源；发布脚本只读取
`server/composer.json` 与 `server/composer.lock`，不会依赖本地核心库路径。

本地开发可以通过 path repository 将 `peanut-admin/core` 指向相邻的
`peanut-admin-core` 工作树。该配置写入被 `.gitignore` 忽略的 `.local/`，不会改变正式
清单、线上安装或发布包身份。

## 首次启用

每个工作树先准备仓库锁定的 Composer；脚本将 PHAR 保存在忽略提交的相对路径
`.local/toolchain/`，并在每次执行时校验版本与 SHA-256，不读取全局 Composer：

```bash
scripts/project-composer prepare
```

每次显式选择 Core checkout：

```bash
scripts/local-core-composer install \
  --core-dir /absolute/path/to/peanut-admin-core \
  --backend-env /absolute/path/to/peanut-admin/server/.env.development
```

脚本会动态验证应用要求版本与所选 Core 工作树的 package manifest 版本完全一致；
开发指南不固定某个历史版本号。安装成功后，`server/vendor/peanut-admin/core` 必须是
指向该工作树 `packages/php` 的软链接，核心源码修改会即时被应用读取。

## 日常使用

核心源码已经通过软链接安装后，不需要每次修改 PHP 都重新执行 Composer。只有核心包的
manifest、版本或依赖发生变化时，才执行：

```bash
scripts/local-core-composer update \
  --core-dir /absolute/path/to/peanut-admin-core \
  --backend-env /absolute/path/to/peanut-admin/server/.env.development
```

## 发布边界

发布或线上安装继续使用仓库入口：

```bash
scripts/project-composer install --working-dir=server --no-dev --prefer-dist
```

发布流程不读取 `.local/composer-core/`，不接受本地 path repository，也不把软链接带入
发布制品。正式消费必须先发布固定 tag，再更新应用的 Composer 版本和 lock 文件。

## 前端包

应用的 `web/package.json` 与 lock 文件同样固定 `@peanut-admin/admin` 的正式版本。开发阶段可将
当前安装目录中的该包替换为指向相邻核心工作树 `packages/web` 的软链接：

```bash
scripts/local-core-web link --core-dir /absolute/path/to/peanut-admin-core
```

核心源码、应用版本要求和软链接目标均由脚本校验；它还会在核心包被忽略的 `node_modules`
目录中把 Vue、Router、Pinia 与 Element Plus 指向应用已安装的实例，以避免
本地核心工作树的开发依赖覆盖应用的 peer 依赖。链接完成后核心 Web 源码的修改会立即被
Vite 和 TypeScript 读取，不修改发布清单或 lock 文件。发布或线上安装必须重新用
锁文件从 Registry 安装，而不能带入这个本地 `node_modules` 软链接：

```bash
pnpm --dir web install --frozen-lockfile
```
