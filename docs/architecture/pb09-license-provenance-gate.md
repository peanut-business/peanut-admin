# PB09 许可证与来源门禁

> 状态：Passed；发布候选 `f3e6834…`
>
> 日期：2026-08-11
>
> 性质：发布工程门禁，不替代法律意见

## 1. 目的与范围

PB03–PB08B 已证明当前候选具备产品边界、可安装升级、公开 registry 依赖、生产 Compose、四端品牌和官网文档一致性。本门禁只决定应用仓 `peanut-business/peanut-admin` 以什么权利和告知条件发布，不重新验收业务、安装、浏览器或核心 Runtime。

在本门禁通过前：

- 必须先把当前功能分支及合格的 PB09 准备文档提交并推送到 origin 备份；该备份不代表发布授权完成；
- 不得把功能分支合入 `dev/main`、打 tag、创建 release、发布镜像、部署正式版本或宣布产品化正式基线完成；
- 不修改核心仓、`vendor/`、任一 `node_modules/`、`.playwright-cli/`、业务 Runtime、数据库或迁移；
- 不开始 SaaS，也不因上游组件为 MIT/Apache-2.0 就推断整个应用的许可证；
- 不宣称整个应用为 clean-room 实现。

## 2. 当前可证明事实

### 2.1 应用专有策略与根法律文件

1. 仓库根 `LICENSE`、`NOTICE`、`THIRD_PARTY_NOTICES.md` 与 `RELEASE_SBOM.spdx.json` 已形成；应用专有条款不限制第三方许可证已经授予的权利。
2. 用户已决定应用暂时采用专有 / All Rights Reserved；`server/composer.json` 使用 `proprietary`，`web`、`pc`、`uniapp`、`docs-site` 使用 `UNLICENSED`，不得改写为 Apache-2.0。
3. `server/LICENSE.txt` 只陈述 ThinkPHP 的 Apache-2.0 条件和 ThinkPHP 权利人，不能被当作整个 Peanut Admin 应用的许可证。
4. 两个公开核心包属于独立核心仓并声明 Apache-2.0；应用消费它们不把应用自身自动变成 Apache-2.0。

### 2.2 可确认的代码来源

| 来源 | 本地事实 | 当前结论 |
|---|---|---|
| 应用首个提交 | `737da7d213c41cc13594a3b2729360a907894b6c` 的提交说明明确为 ThinkPHP 8 后端加 Arco Design Pro Vue 前端 | 不能把仓库描述为完全从空白独立创作 |
| Arco Design Pro Vue | 上游项目为 MIT；首个提交包含 235 个 `web/` 路径，当前仍存在 107 个，其中 55 个与首个提交字节级一致 | 发布告知必须保留适用的 MIT 版权/许可文本；是否继续同一文件不以当前是否还安装 Arco 依赖为唯一判断 |
| LikeAdmin 1.9.4 | 既有 parity 工作以官方 tag `aae6d28…` 为行为参考；该 tag 为 MIT | 行为一致性验收不等于 clean-room 证明；若存在复制或改编内容，必须保留适用的 MIT 告知 |
| ThinkPHP | `server/LICENSE.txt` 明确 ThinkPHP/Apache-2.0 来源 | 只覆盖框架来源，不覆盖应用产品代码 |
| Peanut Admin 核心包 | 核心仓 `dev` 当前 `7fbd445…`、tag `v0.1.0-alpha.4`，Composer/npm 公开包声明 Apache-2.0 | 作为独立第三方/关联项目依赖登记，不改变应用许可证选择 |

上表只冻结来源和最低告知责任，不判定某一具体应用文件的最终著作权归属，也不构成兼容性法律意见。

### 2.3 贡献与授权链

- Git 历史只有两个提交身份：`xing.gu <905853860@qq.com>` 127 个提交、`xingkoo <xingkoo@gmail.com>` 9 个提交。
- 历史中有 25 个大小写形式为 `Co-Authored-By: Claude Opus 4.8 ...` 的 trailer；没有 CLA、DCO 或 `Signed-off-by` trailer。
- 提交身份、AI co-author trailer 和仓库控制权都不能单独证明某个法定主体拥有全部发布权。用户已确认拥有或已取得仓库代码、文档、品牌资产、`xing.gu`、`xingkoo` 及 AI 辅助成果的发布和再许可权。

因此，应用许可证固定为专有 / All Rights Reserved，版权主体显示严格使用用户提供的“花生科技”；这不是对主体法定全称的自行扩写。核心仓及两个公开核心包继续 Apache-2.0，应用消费核心包不改变应用许可证。

## 3. 锁文件依赖库存

本次只读库存覆盖五个生态的现有锁文件；直接依赖按根 manifest 统计，传递依赖按其余锁条目统计，未安装任何包，也未改锁文件。

| 生态 | 直接 | 传递 | 总计 | 当前风险摘要 |
|---|---:|---:|---:|---|
| Composer `server` | 11 | 26 | 37 | MIT 24、Apache-2.0 12、BSD-3-Clause 1；lock 与现有 vendor 元数据一致，无未知 |
| pnpm `web` | 57 | 984 | 1041 | 1 个 manifest 缺 license 但本地 README 证明 MIT；48 个本地未知已由官方来源补证；含 CC、BlueOak、Python-2.0 等需告知项 |
| npm `pc` | 10 | 921 | 931 | 1 个 manifest 缺 license 但本地 README 证明 MIT；12 个 `lightningcss` 锁条目为 MPL-2.0；`node-forge@1.4.0` 为 `BSD-3-Clause OR GPL-2.0` |
| npm `uniapp` | 30 | 978 | 1008 | 1 个 manifest 缺 license 但本地 `LICENSE.md` 证明 MIT；无未知；含 CC-BY、BlueOak、Zlib 等需告知项 |
| pnpm `docs-site` | 1 | 173 | 174 | 47 个本地未知已由官方来源补证；其余含 MIT/ISC/BSD/CC0 |

“未知”表示当前机器没有对应 package metadata，不能据此断言缺少许可证或存在 copyleft。三项 manifest 缺字段分别为 `trim@0.0.1`、`only@0.0.2`、`exif-parser@0.1.12`；本地包内文本均证明为 MIT，但后续清单仍须引用实际版权和许可文本。

初始本地未知的 95 个锁条目现已全部取得官方 SPDX 证据。为避免把“本地未安装数”误写成“完整平台族数”，两个口径同时冻结：

- `web` 初始未知 48 个；加上当前 macOS 已安装并已由本地 metadata 证明的 2 个，同族实际为 50 个：`@emnapi/*` 3、`@esbuild/*@0.15.18` 2、`@napi-rs/wasm-runtime` 1、`@tybys/wasm-util` 1、`@unrs/resolver-binding-*` 22、旧 `esbuild-*` 20、`tslib` 1。官方结论为 MIT 49、0BSD 1（`tslib@2.8.1`）；
- `docs-site` 初始未知 47 个；加上当前平台已安装的 2 个，同族实际为 49 个：`@esbuild/*` 23、`@napi-rs/lzma-linux-x64-gnu` 1、`@rollup/rollup-*` 25，官方结论全部为 MIT。

许可证已知不等于 NOTICE 署名可以猜测。`@tybys/wasm-util@0.10.3` 的官方包元数据只给出 author `toyobayashi`、MIT 和仓库，源码/包归档没有版权行或独立 LICENSE；`@napi-rs/lzma-linux-x64-gnu@1.5.1` 的平台归档和对应 tag 同样没有可引用的版权行。第三方清单应如实记录“上游未提供单独版权/NOTICE”，引用 package、version、SPDX、author/repository 与官方元数据，不自行创造版权声明。

官方补证来源：npm registry 的精确 version metadata，以及 [emnapi](https://github.com/toyobayashi/emnapi/blob/v1.10.0/LICENSE)、[esbuild 0.15.18](https://github.com/evanw/esbuild/blob/v0.15.18/LICENSE.md)、[UnRS 1.12.2](https://github.com/unrs/unrs-resolver/blob/v1.12.2/LICENSE)、[tslib 2.8.1](https://github.com/microsoft/tslib/blob/d72d6f70b36286bc3f94a3dda1e64dcb568b1370/LICENSE.txt)、[esbuild 0.21.5](https://github.com/evanw/esbuild/blob/v0.21.5/LICENSE.md)、[Rollup 4.62.4](https://github.com/rollup/rollup/blob/v4.62.4/LICENSE.md) 与 [napi-rs/lzma 1.5.1](https://github.com/Brooooooklyn/lzma/tree/v1.5.1)。

PB09 前的正式第三方清单必须区分“会进入最终分发物的生产依赖”和“只用于构建/测试/文档的开发依赖”，为每个实际分发项记录 package、version、license、copyright/source、notice 要求与证据来源。不能只复制 package manifest 的 license 字段充当完整 NOTICE。

### 3.1 最终分发边界

1. `deploy/docker/production.Dockerfile` 的 Node/Composer 阶段都是 builder。最终 Nginx 镜像只复制管理端、PC 与 H5 静态产物，不复制任一 `node_modules`；最终 PHP 镜像复制 `composer install --no-dev` 得到的 vendor，当前锁图为 33 个生产包。
2. PHP vendor 内多数包自带 LICENSE/NOTICE，但这不能替代应用根许可证和汇总第三方告知。当前 Web/PC/H5/docs-site 静态产物没有完整的根 `LICENSE`、`NOTICE` 或 `THIRD_PARTY_NOTICES.md`；`web/dist/assets/notice.*.js` 是通知业务 API 模块，不是法律 NOTICE。
3. `lightningcss` MPL-2.0 平台包、`node-forge` 复合许可证、caniuse 数据和上述平台二进制当前只沿 Nuxt/Vite/UniApp/VitePress 构建链出现。生产 Dockerfile 不分发这些包本身；不能仅凭 lock 的 `dev=false` 推断它们进入浏览器静态产物，也不能仅凭“builder only”省略已经被 bundle 的代码、数据或资源。正式清单以锁图、构建配置、最终产物三者共同判定。
4. 决策后，源码 release、PHP/Nginx 镜像、静态站点等每一种实际发布物都必须可取得适用的根许可证和第三方告知。若 PB09 发布容器镜像，还须为 `php:8.3-fpm-bookworm`、`nginx:1.28.0-alpine` 及安装的 OS 包提供独立 SBOM/许可证索引；Node/Composer builder 未进入最终镜像，但仍登记为构建来源。

## 4. 用户决定（2026-08-11）

以下三项相互关联但不能互相替代的决定已经明确：

1. **应用许可证策略**：暂时专有 / All Rights Reserved；Composer 使用 `proprietary`，npm manifests 使用 `UNLICENSED`，不写 Apache-2.0。
2. **版权主体显示**：`花生科技`，严格按用户提供文字记录，不自行扩写为未提供的法定全称。
3. **贡献授权确认**：用户确认拥有或已取得仓库代码、文档、品牌资产、`xing.gu`、`xingkoo` 及 AI 辅助成果的发布和再许可权。

这三项决定解除法律策略选择阻塞；仍须完成根法律文件、第三方告知和一次静态一致性门禁，才能合入 `dev/main` 并执行正式发布。

## 5. 决策后的写入白名单

取得明确决定后，许可证门禁只允许写入下列范围；候选 `f3e6834…` 的实际写集符合该范围：

- 根 `LICENSE`、`NOTICE`、`THIRD_PARTY_NOTICES.md`；
- 根 `CHANGELOG.md`、无自引用的 `RELEASE_METADATA.json`，以及 tag 后作为 GitHub Release 附件生成的 `RELEASE_MANIFEST.json`；
- `server/composer.json`、`web/package.json`、`pc/package.json`、`uniapp/package.json`、`docs-site/package.json` 及其对应锁文件中仅与根包许可证元数据同步所必需的字段；
- README、官网版本/发布页、用户手册、开发、部署/升级文档、PB09 发布合同和本计划；
- `deploy/docker/production.Dockerfile`、发布工作流和 docs-site 法律信息入口中，仅为把已经批准的许可证/告知/SBOM 放入实际发布物所需的复制或链接；不得改变 Runtime、基础镜像、依赖或路由语义；
- 如需可重复生成第三方清单，可新增只读分析脚本和最小 CI 门禁，但不得改依赖版本或生产 Runtime。

所有其他文件默认在白名单外。特别禁止修改核心仓、`server/LICENSE.txt`、业务代码、数据库、迁移、依赖安装目录、已封存证据或 PB08B 候选内容来“配合”许可证结论。

## 6. 通过条件与停止线

门禁只有同时满足以下条件才可标记通过：

1. 第 4 节三项决定被原样记录，许可证文本与 package metadata 一致；
2. Arco Design Pro Vue、LikeAdmin、ThinkPHP、两个核心包及实际分发第三方依赖的版权/许可/NOTICE 要求完整；
3. 初始 95 个本地未知锁条目的 SPDX 已由官方 registry/上游许可证补证；对上游没有提供版权行或独立 NOTICE 的两项如实记录来源，不创造署名；
4. MPL-2.0、复合 SPDX、CC、BlueOak、Python-2.0、Zlib 等非默认项已按实际分发方式逐项决定处理，不用“多数为 MIT”代替；
5. 源码 release 与每个实际发布的镜像/静态站点都能取得适用的根许可证和第三方告知；发布容器镜像时另有基础镜像/OS SBOM 或等价许可证索引；
6. 一次静态一致性检查确认根文件、五个 manifest/lock、README/官网/发布文档没有互相矛盾；只对法律文件落点做一次产物级静态检查，不重跑 PB08B 的安装、业务或浏览器验收；
7. 本计划和应用发布合同更新为“许可证门禁通过、PB09 可开始”。

一次静态门禁结果：五个 manifest 保持 `proprietary/UNLICENSED` 且作者显示为“花生科技”；五锁图 3,191 个依赖条目进入 SPDX 2.3 SBOM，许可证结论无 `NOASSERTION`；根法律文件与 `RELEASE_METADATA.json` 的 SHA-256、28 条 migration 清单、Docker `/legal/` 和官网法律入口一致。VitePress 首次因两个 `.md` 下载链接被识别为站内路由而失败，按合同只修正下载扩展名，失败组一次重跑通过；未运行 PB08B 的业务、数据库、Compose 或浏览器验收。

本门禁已通过，只授权继续 PB09 的功能分支 PR → `dev` → `main` → tag/Release/部署顺序；它不代表应用已经发布或产品化正式基线已经完成。

## 7. 证据索引

- 应用 Git：首个提交 `737da7d…`、当前候选谱系 `4442229…`、当前工作分支 HEAD `b3b468f…`、应用基线 `origin/dev=bc2e75ac…`；
- 核心 Git：`dev/origin/dev=7fbd445…`、tag `v0.1.0-alpha.4`；既有未跟踪 `.playwright-cli/` 保持不动；
- 应用 manifests：`server/composer.json`、`web/package.json`、`pc/package.json`、`uniapp/package.json`、`docs-site/package.json`；
- 框架声明：`server/LICENSE.txt`；
- 上游来源：[Arco Design Pro Vue](https://github.com/arco-design/arco-design-pro-vue)、[LikeAdmin PHP](https://github.com/likeadmin-likeshop/likeadmin_php)、[ThinkPHP](https://github.com/top-think/framework)、[Peanut Admin 核心](https://github.com/peanut-opensource/peanut-admin)；
- 补证来源：精确 npm version metadata 与第 3 节列出的七个上游许可证/tag；
- 技术候选证据：`output/playwright/pb08b/summary.json`；它不承担许可证结论。
