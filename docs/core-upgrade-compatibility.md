# 核心包升级兼容矩阵

本 Gate 使用两个诚实且相互独立的旧起点，不把它们伪称为统一平台版本：

- PHP `legacy-pre-alpha5`：`scripts/select-legacy-pre-alpha5-fixture` 从应用 Git 第一父历史机器筛选最新合格 tree。固定结果是 commit `4808a82f408f10945de1be8348ebc2ea05bc4fb9`、tree `35e7c827ee72feeecdff5e42e34cdfcf945527df`；它原生锁定公开 Packagist `peanut-admin/core@0.1.0-alpha.2`，只使用该版 10 个公开 PSR-4 root，并包含真实 app-owned `CoreServiceOverrides`/Host 测试。该输入不是 create-app 生成物，overlay 为空；机器证据分别记录 Git archive、应用源码、Host 文件和空 overlay 摘要。
- Web/current boundary：正式 create-app `1.1.2` 从当前 Gate 的精确 candidate 运行，但
  application manifest 的 `template` 必须逐字段采用同版本不可变 release，
  `generation_source` 则必须逐字段绑定 candidate commit/tree 与 candidate 当前 inventory。
  release manifest 自身记录完整 source commit/tree、inventory、managed digest 与 274 files；
  fixture 只固定非递归的 release 内容身份和稳定生成摘要，不再把 release provenance 与移动
  candidate 混成一个身份。它生成当前应用，只承担生成 manifest/source 边界、完整 Web runtime
  与全客户端公开入口扫描。Web 从公开 npm `@peanut-admin/admin@0.1.0-alpha.4` 升到
  `0.1.0-alpha.5`。

在这两条包级路径之外，`scripts/combined-upgrade-qualification` 增加唯一组合资格路径：
它从正式 `v1.0.0` create-app commit 生成同一个旧下游应用，并在 app-owned Host 与网站设置
业务域分别加入确定性定制。随后按不可跳代的
`1.0.0 → 1.1.0 → 1.1.1 → 1.1.2` release manifest 链执行 preflight/apply/verify，
最后按目标 Composer、Web、PC 与 UniApp manifest/lock 做全新依赖安装。每一代 release 的
manifest SHA、source commit/tree、inventory、managed tree 和文件数都由 fixture 固定；目标
application manifest 必须采用同一个 `v1.1.2` release identity。

PHP 在历史应用的干净 archive 中先按原生 manifest/lock 安装 Alpha.2、运行原生 `AdminPermissionHostTest.php`，随后只替换 `server/composer.json` 和 `server/composer.lock` 为固定 Alpha.5，再运行同一 Host 测试。Web 在 current create-app 中先按固定 Alpha.4 lock 安装、typecheck/build/consumer，随后只替换 `web/package.json` 和 `web/pnpm-lock.yaml` 为固定 Alpha.5 并重复同一组检查。两侧业务源码和 app-owned 摘要必须逐字节不变。

公共入口 Gate 从真实安装包解析 Composer PSR-4 与 npm `exports`，拒绝 `vendor`/`node_modules` 内部路径、包内 `src` deep import 和相对跨包源码引用；Alpha.5 不得移除旧应用实际依赖的公开 Composer root 或 Alpha.4 已发布 npm export。包级路径仍只扫描 current create-app 的 PC/UniApp import；组合路径则在目标应用中对 Web 执行 frozen pnpm install、typecheck/build，对 PC 和 UniApp 执行 frozen npm install、typecheck 与最低 production/H5 build。

组合路径在每一跳比较 application manifest 内全部受跟踪文件的前后状态，实际差异必须精确
等于该次 plan 的 `create/delete/replace/regenerate` managed action；任何额外源码变化都会拒绝。
两处真实下游定制、全部 app-owned 业务源码及 Host 的聚合摘要在旧依赖测试、每一代 scaffold
升级、目标干净安装和客户端构建后都必须逐字节不变。同一份
`AdminPermissionHostTest.php` 与代表网站设置域测试会在升级前后各运行一次。

## 兼容承诺边界

本 Gate 只承诺：**在稳定公开 API 范围内，合格升级无需 app-owned 重构**。它不承诺未公开、
未文档化或包内 deep-import 接口的兼容性，也不覆盖数据库、服务、端口、容器、浏览器或发布。

`combined-upgrade.json` 必须枚举仓库中的完整 scaffold release 链和每个相邻 transition。稳定
transition 使用 `stable-public-api` 且不得夹带迁移动作；breaking transition 必须声明至少一个
机器可读 `migration`、`codemod` 或 `manual-action`，并固定 action id、仓库内 artifact 和 SHA-256。
缺失 action、未知 action 类型、artifact 越界/缺失或 digest 漂移都会在依赖安装前 fail-closed。
新增 release manifest 而未同步 transition policy 也会拒绝。

## 失败证据与继续策略

历史 run `31754046399`、`31755497847` 和 `31755618378` 保持失败事实，不改写为成功。最后一次运行固定在 candidate `440297f74b6f6fd26b7c88117bbb499b25dc1730`，已通过 Alpha.2 安装/autoload/原生 Host 和 Alpha.4 frozen install，随后因 GitHub Ubuntu 未提供 `rg` 而停止；它没有上传可移植的依赖状态 checkpoint。因此修复后的新 candidate 必须从空 cache/output 完整运行矩阵，执行模式明确记录为 `new_candidate_full_matrix`，不得声称跳过或复用上述成功组。

每次运行会从开始即写出 `checkpoint.json`，绑定完整 candidate commit/tree、legacy commit/archive、Alpha.2/Alpha.5 Composer lock 与 Alpha.4/Alpha.5 pnpm lock 四个摘要、历史 run 证据和逐阶段通过摘要。workflow 在失败时也上传该 checkpoint。候选或任一输入变化时，checkpoint 只能作为审计证据，不得作为缓存复用依据。

current create-app 身份 Gate 会分别拒绝错误 release、错误 candidate 和错误 inventory；随后
精确比较 application manifest 的 managed 路径/mode/classification 集合、managed/app-owned
摘要和文件总数。整份 manifest 的 SHA 作为该次 candidate 证据动态记录；业务源码摘要仍覆盖
全部生成源码，只排除原本就不属于源码 Gate 的 `.peanut` provenance/baseline 元数据，不能用
provenance 变化掩盖源码变化。deep-import、Composer PSR-4、npm exports 与 Registry Gate 保持不变。

## 已知 PHP 发布身份完整性缺陷

核心 monorepo `v0.1.0-alpha.4` 固定到 commit `7fbd445d8fa547830b7782a7ac147d9ed414e0fd`，但 tag 内 `packages/php/composer.json` 仍声明 `0.1.0-alpha.2`，Packagist 也没有 `peanut-admin/core@0.1.0-alpha.4`。机器 Gate 必须真实观察到这个缺陷，并禁止把它当作可消费 PHP 身份。未来 PHP 发布必须保证 monorepo tag、内嵌 package metadata、Composer split tag 和 Packagist version 一致；本 Gate 不修改核心仓、tag 或 Registry。

## 本地执行

按项目租约规则为固定 candidate claim 唯一 cache/output。包级矩阵运行：

```bash
scripts/core-upgrade-compatibility \
  --candidate <full-candidate-commit> \
  --cache /private/tmp/peanut-admin-core-upgrade-cache-<candidate> \
  --output /private/tmp/peanut-admin-core-upgrade-output-<candidate>
```

组合路径运行：

```bash
scripts/combined-upgrade-qualification \
  --candidate <full-candidate-commit> \
  --cache /private/tmp/peanut-admin-combined-upgrade-cache-<candidate> \
  --output /private/tmp/peanut-admin-combined-upgrade-output-<candidate>
```

两条 runner 的输入都会强制规范化到物理绝对路径，并拒绝 symlink、非空、嵌套或相同
cache/output。组合路径只连接项目登记的 Packagist、npm Registry 和 GitHub repository；它不
连接数据库、端口、服务、浏览器或容器，也不提交 vendor、node_modules、dist、缓存或原始安装日志。
