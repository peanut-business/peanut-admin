# 核心包升级兼容矩阵

本 Gate 使用两个诚实且相互独立的旧起点，不把它们伪称为统一平台版本：

- PHP `legacy-pre-alpha5`：`scripts/select-legacy-pre-alpha5-fixture` 从应用 Git 第一父历史机器筛选最新合格 tree。固定结果是 commit `4808a82f408f10945de1be8348ebc2ea05bc4fb9`、tree `35e7c827ee72feeecdff5e42e34cdfcf945527df`；它原生锁定公开 Packagist `peanut-admin/core@0.1.0-alpha.2`，只使用该版 10 个公开 PSR-4 root，并包含真实 app-owned `CoreServiceOverrides`/Host 测试。该输入不是 create-app 生成物，overlay 为空；机器证据分别记录 Git archive、应用源码、Host 文件和空 overlay 摘要。
- Web/current boundary：正式 create-app `1.1.1` 固定 source seal `5c33c218bd48e9a428d7a3f23211934e7b3d9303`（tree `01c66f34e8ac7832c54fcbb467a5318d9096f1c6`），release managed digest 为 `02b3204053e137b818dab4315734aa83b143f472e90d4792d6ee525bef748c0d`（274 files）。它生成当前应用，只承担生成 manifest/source 边界、完整 Web runtime 与全客户端公开入口扫描。Web 从公开 npm `@peanut-admin/admin@0.1.0-alpha.4` 升到 `0.1.0-alpha.5`。

PHP 在历史应用的干净 archive 中先按原生 manifest/lock 安装 Alpha.2、运行原生 `AdminPermissionHostTest.php`，随后只替换 `server/composer.json` 和 `server/composer.lock` 为固定 Alpha.5，再运行同一 Host 测试。Web 在 current create-app 中先按固定 Alpha.4 lock 安装、typecheck/build/consumer，随后只替换 `web/package.json` 和 `web/pnpm-lock.yaml` 为固定 Alpha.5 并重复同一组检查。两侧业务源码和 app-owned 摘要必须逐字节不变。

公共入口 Gate 从真实安装包解析 Composer PSR-4 与 npm `exports`，拒绝 `vendor`/`node_modules` 内部路径、包内 `src` deep import 和相对跨包源码引用；Alpha.5 不得移除旧应用实际依赖的公开 Composer root 或 Alpha.4 已发布 npm export。PC 与 UniApp 不另造升级矩阵，只纳入 current create-app 的真实 import 扫描。

## 失败证据与继续策略

历史 run `31754046399`、`31755497847` 和 `31755618378` 保持失败事实，不改写为成功。最后一次运行固定在 candidate `440297f74b6f6fd26b7c88117bbb499b25dc1730`，已通过 Alpha.2 安装/autoload/原生 Host 和 Alpha.4 frozen install，随后因 GitHub Ubuntu 未提供 `rg` 而停止；它没有上传可移植的依赖状态 checkpoint。因此修复后的新 candidate 必须从空 cache/output 完整运行矩阵，执行模式明确记录为 `new_candidate_full_matrix`，不得声称跳过或复用上述成功组。

每次运行会从开始即写出 `checkpoint.json`，绑定完整 candidate commit/tree、legacy commit/archive、Alpha.2/Alpha.5 Composer lock 与 Alpha.4/Alpha.5 pnpm lock 四个摘要、历史 run 证据和逐阶段通过摘要。workflow 在失败时也上传该 checkpoint。候选或任一输入变化时，checkpoint 只能作为审计证据，不得作为缓存复用依据。

## 已知 PHP 发布身份完整性缺陷

核心 monorepo `v0.1.0-alpha.4` 固定到 commit `7fbd445d8fa547830b7782a7ac147d9ed414e0fd`，但 tag 内 `packages/php/composer.json` 仍声明 `0.1.0-alpha.2`，Packagist 也没有 `peanut-admin/core@0.1.0-alpha.4`。机器 Gate 必须真实观察到这个缺陷，并禁止把它当作可消费 PHP 身份。未来 PHP 发布必须保证 monorepo tag、内嵌 package metadata、Composer split tag 和 Packagist version 一致；本 Gate 不修改核心仓、tag 或 Registry。

## 本地执行

按项目租约规则为固定 candidate claim 唯一 cache/output，再运行：

```bash
scripts/core-upgrade-compatibility \
  --candidate <full-candidate-commit> \
  --cache /private/tmp/peanut-admin-core-upgrade-cache-<candidate> \
  --output /private/tmp/peanut-admin-core-upgrade-output-<candidate>
```

输入会强制规范化到物理绝对路径，并拒绝 symlink、非空、嵌套或相同 cache/output。本 Gate 不连接数据库、端口、服务、浏览器或容器，也不提交 vendor、node_modules、dist、缓存或原始安装日志。
