# Module 发布与制品合同

Document ID: `pa-docs-architecture-module-publication-contract`

Status: `current`

Owner: `release-engineering`

Audience: `maintainer, architect, developer, operator, ai`

## 1. 决定

Module 的“在仓库中可用”“随应用源码发布”“已生成可安装包”和“已对外发布”是四个不同状态。
状态只能由对应的不可变制品和验证证据推进，不能由分支名、`composer.json`/`package.json` 的 version、
一次 `module:pack` 输出或文档描述推断。

当前支持两种交付通道：

1. **Bundled source**：Module 后端、前端和 `plugin.json` 随同一 Peanut Admin Release 冻结，
   `plugins.lock` 记录 `channel=bundled` 和 canonical contents identity；
2. **Self-contained Package**：`module:pack`/`bundle:pack` 生成确定性 tar，包含后端、前端、生成的
   Plugin manifest、`META-INF/files.sha256` 以及可选 Ed25519 签名。

Composer 与 npm Registry、Marketplace 不是当前已开放的独立交付通道。Module 内的 `composer.json`
和前端 `package.json` 是 Package 组件身份与 autoload/build 输入；除非另有已批准的发布合同、版本、
依赖和资格流水线，否则不得单独发布。前端 manifest 的 `private: true` 必须保留。

## 2. 权威输入与可发布内容

| 内容 | 权威路径 | 是否进入单 Module tar | 规则 |
| --- | --- | --- | --- |
| Module 身份、依赖、owned tables、资源入口 | `server/app/Modules/<Vendor>/<Module>/module.json` | 是 | key、version、Kernel 约束和派生路径必须通过 schema/preflight |
| 后端实现、migration、权限、菜单 | 同一后端 Module 根目录 | 是 | 不得包含环境密钥；migration identity/checksum 不可漂移 |
| 前端贡献与组件 | `web/src/modules/<slug>/` | 有 `frontend.entry` 时是 | `contribution.ts` 路径由 Module key 派生 |
| PHP/npm 组件身份 | Module 内 `composer.json`、前端 `package.json` | 是 | 只描述包内组件，不表示 Registry 已发布 |
| Plugin 交付身份 | `plugins/<package.key>/plugin.json` | 打包时重新生成并纳入 inventory | bundled 身份另由根 `plugins.lock` 固定 |
| 测试、临时脚本、工作区证据 | `server/tests/`、临时目录 | 否 | 测试和证据不进入交付 tar；不得把临时脚本提交为 Runtime |

单 Module Package 的 package key 必须等于唯一 Module key。Bundle 必须包含至少两个 Module，package
key 不得冒充成员；安装、停用、退役和 Purge 均以完整 Package 为原子边界。

## 3. 状态机与最低门禁

| 状态 | 必须证据 | 尚不能声称 |
| --- | --- | --- |
| `authored` | canonical source、`module.json`、前后端组件身份 | 可安装、已发布 |
| `author-ready` | `module:check` 八项全部通过 | 业务行为、浏览器、Provider 已验证 |
| `bundled-locked` | `plugin.json` 与 `plugins.lock` 摘要一致，进入固定应用 Release tree | 独立 Package 已签发或 Marketplace 可用 |
| `package-candidate` | 固定 source commit/tree 上生成 tar、SHA-256；跨环境交付还需受信签名 | qualified、published |
| `qualified` | 同一 tar 身份完成 archive verify、Module 行为、Tenant/RBAC、migration 和受影响客户端/Provider Gate | 已上传到任何外部渠道 |
| `published` | owner 批准的目标渠道存在同一不可变 version/digest/signature，并完成一次可见性验证 | 其他渠道也已发布 |

外部交付还必须提供 SBOM、license、review 和漏洞响应 owner。当前 `plugin.schema.json` 与
`PluginLockResolver` 只接受 bundled、archive/signature/SBOM `not-issued`、Marketplace `blocked`；
因此根 `plugins.lock` 不能记录外部 published 状态。未来开放外部渠道时，应先扩展单独的发布登记与
消费者验签合同，不得篡改 bundled lock 来伪装发布。

所有 Gate 必须运行真实断言。输出 `PASSED` 后无条件 `exit(0)` 的测试占位文件、注释掉的断言或未
命中声明路径的较弱测试均不是证据。暂时不能运行的数据库、浏览器或 Provider Gate 应登记为具体
停止线，只阻塞依赖它的状态推进。

## 4. 发布流程

1. 固定 source commit/tree，核对 Module key、版本、依赖和目标通道；
2. 运行 `php think module:check <module.key>` 和该 Module 的真实聚焦测试；
3. 运行 `module:pack` 生成到空的候选输出目录。跨环境交付使用 Ed25519 签名参数；
4. 使用输出 SHA-256 和受信公钥重新 verify 同一 tar，并完成 Tenant/RBAC、migration、前端/浏览器及
   受影响 Provider 验证；
5. 仅在候选内容和依赖冻结后 seal 一次。记录 source/tree、package version、archive SHA-256、签名
   key ID、SBOM digest、兼容矩阵和 Gate 结果；
6. 经发布 owner 授权后上传目标渠道；确认同一 digest 可见后才登记 `published`。

`module:pack` 默认输出到 `.local/module-packages/`，该目录是开发产物，不提交。命令不会自动修改
`plugins.lock`、开通 TenantModule、授予 RBAC 或发布到外部服务。

## 5. `official.rich-text` 当前裁定（2026-09-09）

- 源码、Module manifest、前端贡献和 bundled Plugin identity 已进入 Peanut Admin v3.0.13 完整应用
  Release（源码与两种 Edition 安装包），故 bundled 状态为 **bundled-locked**；在线 Demo 仍是 v3.0.12，
  不能用来证明该 Module 已部署；
- `plugins.lock` 明确记录 archive/signature/SBOM `not-issued`、review `not-reviewed`、漏洞响应
  `not-configured`、Marketplace `blocked`；因此它不是 independently published Package；
- 前端 `@peanut-admin/official-rich-text` 保持 `private: true`，PHP `peanut-business/official-rich-text`
  也没有仓内证据证明已发布到 Composer Registry；两者只作为自包含 Module Package 的组件身份；
- Rich Text 已提供 Tiptap/ProseMirror 文档、版本、媒体引用、批注锚点和可选 Yjs/Hocuspocus 客户端适配；
  仓库不提供 Hocuspocus 服务端，也未完成原计划的专用真实浏览器验收。因此协同编辑只能视为需外部
  服务与后续资格的能力，不能标记为可发布完成；
- 当前全官方 Module 打包合同改为从 `plugins.lock` 自动发现 official keys，Rich Text 以及未来新增的
  bundled official Module 不再能被硬编码清单漏掉。

本次在固定源码提交 `23c6c31ff4468fb2a5c389f38092adb3ffd0ed24` 上运行 `module:check`，八项检查
全部通过；随后生成未签名本地候选 `/private/tmp/official.rich-text-1.0.0-unsigned.tar`，SHA-256 为
`9240e9ca17f18f009117a775fa9c0a19eaa6511e7a563fc100316aab9647caa8`。这使独立包通道达到
**package-candidate（local/unsigned）**，不改变 bundled 通道状态，也不满足 qualified 或 published。

下一次要推进 Rich Text 独立发布，最低缺口是：专用浏览器行为、协同服务认证/隔离、signed tar
verify、SBOM、review、漏洞响应 owner 和明确目标渠道。完成前维持 bundled-only。
