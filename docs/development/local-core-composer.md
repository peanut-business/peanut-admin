# 本地核心库即时开发

应用的正式 Composer 清单保持固定版本和远程包来源；发布脚本只读取
`server/composer.json` 与 `server/composer.lock`，不会依赖本地核心库路径。

本地开发可以通过 path repository 将 `peanut-admin/core` 指向相邻的
`peanut-admin-core` 工作树。该配置写入被 `.gitignore` 忽略的 `.local/`，不会改变正式
清单、线上安装或发布包身份。

## 首次启用

默认查找应用仓库同级目录的 `peanut-admin-core`：

```bash
scripts/local-core-composer install
```

核心库不在默认位置时显式指定：

```bash
PEANUT_ADMIN_CORE_DIR=/absolute/path/to/peanut-admin-core \
  scripts/local-core-composer install
```

脚本会验证应用要求版本与核心 package manifest 版本一致；当前两者都应为
`0.1.0-alpha.5`。安装成功后，`server/vendor/peanut-admin/core` 必须是指向本地
`packages/php` 的软链接，核心源码修改会即时被应用读取。

## 日常使用

核心源码已经通过软链接安装后，不需要每次修改 PHP 都重新执行 Composer。只有核心包的
manifest、版本或依赖发生变化时，才执行：

```bash
scripts/local-core-composer update
```

## 发布边界

发布或线上安装继续使用普通命令：

```bash
composer install --working-dir=server --no-dev --prefer-dist
```

发布流程不读取 `.local/composer-core/`，不接受本地 path repository，也不把软链接带入
发布制品。正式消费必须先发布固定 tag，再更新应用的 Composer 版本和 lock 文件。

## 前端包

应用的 `web/package.json` 与 lock 文件同样固定 `@peanut-admin/admin` 的正式版本。开发阶段可将
当前安装目录中的该包替换为指向相邻核心工作树 `packages/web` 的软链接：

```bash
scripts/local-core-web link
```

核心源码、应用版本要求和软链接目标均由脚本校验；它还会在核心包被忽略的 `node_modules`
目录中把 Vue、Router、Pinia 与 Element Plus 指向应用已安装的实例，以避免
本地核心工作树的开发依赖覆盖应用的 peer 依赖。链接完成后核心 Web 源码的修改会立即被
Vite 和 TypeScript 读取，不修改发布清单或 lock 文件。核心库不在默认同级路径时，使用
`PEANUT_ADMIN_CORE_DIR=/absolute/path/to/peanut-admin-core` 指定。发布或线上安装必须重新用
锁文件从 Registry 安装，而不能带入这个本地 `node_modules` 软链接：

```bash
pnpm --dir web install --frozen-lockfile
```
