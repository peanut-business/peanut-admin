# PB09 正式基线发布合同

> 状态：Authorized for branch backup；法律文件门禁实施中
>
> 目标应用版本：`1.0.0`
>
> 目标 Git tag：`v1.0.0`
>
> 日期：2026-08-11

## 1. 目的与授权边界

PB09 把已经通过 PB03–PB08B 的技术候选发布为 Peanut Admin 应用正式基线。它只负责应用仓版本身份、法律文件、分支合入、tag、release manifest、发布记录和官网版本状态，不重新迁移领域能力、不修改核心 Runtime、不发布新核心包，也不开始 SaaS。

`docs/architecture/pb09-license-provenance-gate.md` 是硬前置。当前必须先提交并推送功能分支及合格的 PB09 准备文档，避免 35 个本地提交丢失；根 `LICENSE`、`NOTICE`、`THIRD_PARTY_NOTICES.md` 尚未通过前，仍禁止合入 `dev/main`、打 tag、创建 GitHub Release、发布镜像、正式部署或声明正式基线完成。

## 2. 当前发布事实

| 项目 | 2026-08-11 当前事实 | PB09 含义 |
|---|---|---|
| 应用基线 | `origin/dev=bc2e75ac6217d7defc44cd2b8e0c9e85a7cefc62` | PB03–PB08B 工作以它为起点，不改写历史 |
| 当前工作分支 | `feat/pb04-permission-host`，`HEAD=b3b468f17b0c481410e25bfea6f6e821ad5b486b`，领先 `origin/dev` 35 个提交，另有未提交的 PB09 前置文档 | 必须先按功能分支 → `dev` 合入，不能直接从功能分支发布 |
| 集成/稳定分支 | `origin/main=114acb3ea7b23e486a54007b79258e52a1fd0a0e` 是 `origin/dev` 祖先；`origin/dev` 是当前 HEAD 祖先 | 没有反向分叉；仍须走 `dev` → `main` 的阶段合入 |
| 应用 tag | 当前没有应用 tag | `v1.0.0` 必须只在最终 `main` 发布提交创建一次 |
| manifest 版本 | Web、PC、UniApp、docs-site 均为 `1.0.0`；Composer 应用包不内嵌 version | `1.0.0` 是已存在但未发布的应用版本身份，不再引入第二个版本号 |
| 公开状态 | README 与官网明确写“应用 1.0.0 尚未发布” | 只有 Git tag、GitHub Release 和版本文档均完成后才能删除该声明 |
| 核心依赖 | Composer `peanut-admin/core@0.1.0-alpha.2`；Web/PC `@peanut-admin/admin@0.1.0-alpha.3`；UniApp `@peanut-admin/admin@0.1.0-alpha.4` | PB09 原样发布已验收锁，不顺带统一或升级核心版本 |
| 技术候选 | PB08B 总证据绑定候选谱系 `4442229…`，当前后续提交只同步 PB08B 文档 | 法律/发布元数据变化可继承技术证据；任何 Runtime/依赖/迁移变化都使继承失效并停止 |

## 3. 正式发布对象

### 3.1 唯一正式应用 release

PB09 的规范 release 是 GitHub 仓库 `peanut-business/peanut-admin` 最终 `main` 提交上的 annotated tag `v1.0.0` 与同 tag GitHub Release。源码 tag 是版本事实源，必须包含完整应用仓、四端源码、生产 Compose、迁移、官网源文件、根法律文件和 release metadata；完整 release manifest 在 tag 后生成并作为附件发布。

生产部署继续使用既有文档的“检出 source release → `docker compose up -d --build`”方式。PB09 默认不发布预构建 PHP/Nginx 镜像，因此不新增容器 registry、签名密钥或镜像发布权限；如果以后要发布镜像，必须先完成许可证门禁要求的基础镜像/OS SBOM、digest、签名和回滚合同。

### 3.2 非规范制品

`scripts/package-release.sh` 只生成管理端 + PHP 的无 Docker 备选包，明确不包含 PC/H5，不能命名、附加或宣传为完整 Peanut Admin 1.0.0 正式制品。若 PB09 保留它，只允许作为带有 `layout=server-public-admin-spa` 的次级 artifact，并且必须携带根法律文件、完整 commit/tag/version、锁摘要与 archive SHA-256；否则不附加到 GitHub Release。

docs-site 是同仓官网/文档门户。最终候选只写“拟发布 1.0.0”及确定的发布合同；只有 tag 与 GitHub Release 完成后，才由发布状态提交把版本页、README、用户/开发/部署文档改为“已发布 1.0.0”并部署站点。站点部署记录必须指向正式 tag 目标或其后仅含发布状态封存的提交，不使用独立产品版本。

## 4. Release metadata 与 manifest

包含自身完整 commit SHA 的文件不能进入同一个 commit，否则会形成哈希自引用。PB09 因此使用两层制品：

1. 最终候选提交根目录包含机器可读 `RELEASE_METADATA.json`，固定产品、版本、预期 tag、许可证、运行时范围、锁文件/法律文件/PB08B 摘要的 SHA-256，但不填写自身 commit 或最终源码 archive SHA-256；
2. `v1.0.0` 创建后，从不可变 tag 生成确定性的 `peanut-admin-1.0.0.tar.gz` 和 `RELEASE_MANIFEST.json` 并作为 GitHub Release 附件。该 manifest 才记录完整 commit、源码 archive 和其他附加制品摘要，不回写 tag；manifest 自身 SHA-256 记录在 GitHub Release notes，不写回 manifest。

两层合计至少固定：

- `product`、`version=1.0.0`、`tag=v1.0.0`、完整 40 位 Git commit；
- 发布日期、应用仓 URL、许可证 SPDX 或专有标识、版权主体；
- PHP/Node/pnpm/Composer/MySQL/Nginx 支持范围；
- Composer、Web、PC、UniApp、docs-site 五个锁文件 SHA-256；
- 两个公开核心包的实际锁定版本，不把内部领域目录列成包；
- 28 条 migration 的有序清单摘要；
- `LICENSE`、`NOTICE`、`THIRD_PARTY_NOTICES.md` SHA-256；
- PB08B `summary.json` SHA-256 与技术候选 commit；
- 正式源码 archive 的 SHA-256；如附加次级原生包，再单独列其 layout 与 SHA-256。

release manifest 只能由最终不可变 tag 的内容生成；commit/tag 或任一输入变化都必须停止并创建新候选，禁止回写/移动既有 tag 或沿用旧候选摘要冒充同一制品。

## 5. 串行发布顺序

1. **通过许可证门禁**：原样记录用户三项决定，生成根法律文件、第三方清单和实际发布物告知入口；只做合同规定的一次静态一致性检查。
2. **形成最终候选提交**：新增 `CHANGELOG.md` 的 `1.0.0` 条目与无自引用的 `RELEASE_METADATA.json`，同步 README、官网版本页、用户/开发/部署升级文档为拟发布状态；不得改业务 Runtime、依赖版本、迁移或核心仓。
3. **功能分支 → `dev`**：从干净工作树提交并 push 当前功能分支，创建 PR 到 `dev`。五组既有 CI 是本候选唯一代码门禁；不在本地重复 PB03–PB08B 业务、数据库、Docker 或浏览器验收。
4. **冻结 `dev`**：PR 合入后记录确切 `origin/dev` commit。若合入产生的内容不等于已审候选加预期 merge metadata，停止；不得在 `dev` 上追加顺手修复。
5. **`dev` → `main`**：创建阶段 PR。只允许同一冻结 `dev` 内容进入 `main`；分支保护要求的重复 CI 属远端合入规则，不作为第二次业务验收，也不触发本地复跑。
6. **创建版本身份**：确认最终 `origin/main` 内容、release metadata 和冻结 `dev` 一致后，在该 `main` 提交创建 annotated tag `v1.0.0` 并 push。禁止把 tag 打在功能分支、`dev` 或未合并提交上。
7. **创建 GitHub Release**：从不可变 tag 用固定前缀和无 gzip 时间戳的 `git archive` 生成 `peanut-admin-1.0.0.tar.gz`，生成列出该 archive 与其他附件 SHA-256 的 `RELEASE_MANIFEST.json`，再让 release title、notes、CHANGELOG、升级停止线、许可证、已知限制和 provenance 指向同一 tag；release notes 单独记录 manifest SHA-256，避免循环引用。不得发布核心包、预构建镜像或 SaaS 声明。
8. **发布官网版本状态**：部署同 tag 的 docs-site，确认版本页和 GitHub 入口指向真实 release。只做一次公开版本页/链接 smoke，不重复 PB08B 官网全矩阵。
9. **封存里程碑**：把本合同、许可证合同、产品化计划和根 AGENTS 状态更新为 PB09 完成，记录 main/tag/release/docs commit 与最低证据；随后才允许为 SaaS 建立新的独立合同。

## 6. 一次性最低验收

| Gate | 唯一证据 | 禁止重复/扩张 |
|---|---|---|
| 法律一致性 | 根三文件、五 manifest/lock、README/官网/发布文档、源码/次级制品法律文件落点一次静态断言 | 不重新审计所有普通 MIT 依赖；非标准项沿许可证合同处理 |
| 候选代码 | 功能分支 → `dev` PR 的五组现有 CI | 不重跑 LikeAdmin parity、领域矩阵、实时数据库、Compose 或浏览器 |
| Release identity | `main commit = tag target = attached manifest commit`；仓库 metadata 与锁、法律文件、PB08B 摘要匹配；tag 源码 archive SHA-256 由外部 manifest 固定 | 不以短 SHA、分支名或可移动 URL 代替，不创建哈希自引用 |
| 发布入口 | GitHub Release 可访问；官网版本页、GitHub CTA、CHANGELOG/部署升级链接一次 smoke | 不重跑官网导航、搜索、404、管理端/PC/H5 品牌矩阵 |
| 停止线 | 无新核心版本、无预构建镜像、无 SaaS/多租户已实现声明 | 不访问真实支付、短信、微信或生产数据库 |

任何 gate 失败后最多做一次只读诊断，然后停止发布；不得删除或移动已 push 的 tag 来掩盖失败。若 tag 已公开但 release 需要撤回，保留 tag 和审计记录，发布更正/撤回说明，后续修复使用新 patch 版本。

## 7. 当前停止线

本合同已获用户授权连续执行，但法律文件门禁尚在实施：

- 用户已决定应用暂时专有 / All Rights Reserved，版权主体显示为“花生科技”，并确认仓库、贡献身份与 AI 辅助成果的发布和再许可权；
- 根 `LICENSE`、`NOTICE`、`THIRD_PARTY_NOTICES.md`、`CHANGELOG.md`、`RELEASE_METADATA.json` 尚不存在；tag 后的 `RELEASE_MANIFEST.json` 也尚未生成；
- 当前 PB09 前置文档尚未提交，功能分支也未 push；必须先完成该远端备份；
- 备份完成后继续生成法律文件并执行一次静态门禁；门禁通过前不得合入 `dev/main`、tag、release 或正式部署，也不得把 manifest 中已有的 `1.0.0` 当成已经发布。
