# 产品闭环 Core / Application 兼容基线

Document ID: `pa-docs-architecture-product-closure-core-application-compatibility`

Status: `current`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Upstream: Application manifest/lock、实际公共入口 import、Core 已标记 package manifest，以及
现有包升级和 create-app 验证 owner。

> - PC02 核查日期：2026-08-27
> - Application 输入：`6967f270dadcd1cb69c4606ad42c198c78db5b5b`
> - Core 输入：`8608dafe30467c442000ce408b106d8750ffd766`
> - 本文是采用基线，不是新的 Registry 发布、依赖升级或 Runtime 资格声明。

## 1. 结论

当前版本不需要为了统一编号而升级：Server、Admin Web、Platform Web、PC 和 UniApp 是五个独立消费面，
只要各自锁定的制品、实际使用的公共入口和聚焦验证形成闭包，版本不同不是缺陷。

| 决定 | 作用 | 结果 |
|---|---|---|
| 各消费面独立锁定 | 避免无产品收益的依赖升级和组合回归 | PC02 不移动既有 lock；PC20 只为 Platform Web 增加已批准的 Alpha.7 消费 |
| PC20 直接采用现有 Ops 合同 | 让诊断主线不等待新的 Core 发布 | PHP Alpha.9 有 `PeanutAdmin\OpsConsole\`；Web Alpha.7 有 `./ops-console` |
| PC30 直接采用现有任务/Provider 合同 | 让备份适配只实现 Application/Deployment owner | PHP Alpha.9 已包含 Ops task、Provider、permission 和 audit 合同 |
| PC/UniApp 暂不承载产品闭环 UI | 避免把管理员运维能力错误扩散到消费端 | 继续只消费 Alpha.5 的 UI-neutral client 子路径 |
| 每个消费面独立升级 | 保持最小影响和真实兼容证据 | 禁止用 Core `dev`、移动 dist-tag 或“版本相同”代替固定 lock |

因此 `PC10`、`PC20` 和 `PC30` 的版本前置已经明确；真正的 Runtime 验收仍由各任务自己的
Host、权限、Tenant 和失败语义测试负责。

## 2. 当前消费与不可变身份

| 消费面 | manifest / lock | 锁定身份 | 制品证据 | 当前使用的公共入口 | PC02 判断 |
|---|---|---|---|---|---|
| Server | `server/composer.json`、`server/composer.lock` | `peanut-admin/core@0.1.0-alpha.9` | source 与 dist reference 都是 `e42aa7fb4758002ad4ca235c3f1230fafa9b7ed4`；dist 指向固定 GitHub zipball；`shasum` 为空 | lock 声明 13 个 PSR-4 root；应用 import 其中 12 个，另有一条例外见第 5 节；闭环直接需要 `PeanutAdmin\OpsConsole\` | 可供 PC20/PC30 采用；强制只走 Composer autoload |
| Admin Web | `web/package.json`、`web/pnpm-lock.yaml` | `@peanut-admin/admin@0.1.0-alpha.7` | lock integrity `sha512-fSnisYiQ/NbECK4ZOpFml5RyWyuxXU2XwWyAeEkcChHIQW3hX1tLxP9JfZIIAW6/LHMwYQne8JpBhU6kgW8hVA==` | 当前只 import `./core`、`./shell`；标记 package 另有 `./ops-console` | PC20 可增加 `./ops-console` 公共 import，不需要 lock move |
| Platform Web | `platform/package.json`、`platform/package-lock.json` | `@peanut-admin/admin@0.1.0-alpha.7` | Registry URL 与 integrity 和 Admin Web 相同；Pinia peer 固定为 `2.0.23` | PC20 只 import `./ops-console`，并通过公共 Runtime/InjectionKey 装配只读 Platform transport | 不复制 Core 页面；Platform 生产构建是该消费面的日常验证 owner |
| PC | `pc/package.json`、`pc/package-lock.json` | `@peanut-admin/admin@0.1.0-alpha.5` | npm tarball URL 固定到 Alpha.5；lock integrity `sha512-brHwkDH1Ym1EHFEBJDu+L956Wq3rwtxTaeaIvwPL7mMk8KKur82nqRnp/yk7RSnmScl/XeXMaj2HrTeQqTiOIQ==` | `./client`、`./client/nuxt` | 当前闭环无 PC 管理入口；保持不变 |
| UniApp | `uniapp/package.json`、`uniapp/package-lock.json` | `@peanut-admin/admin@0.1.0-alpha.5` | npm tarball URL 和 integrity 与 PC 相同 | `./client`、`./client/uniapp` | 当前闭环无移动端管理入口；保持不变 |

Composer lock 的空 `dist.shasum` 不是“已校验 SHA-256”。当前身份仍可由相同 source/dist
reference、严格版本和既有发布来源追溯，但更强的 archive digest、签名、SBOM 和 attestation
属于 `PC50` 的制品信任范围，不能由 PC02 虚构为已具备。

## 3. Core 标记来源与公共表面

| 包身份 | Core 来源证据 | 公共表面 | 与当前应用的关系 |
|---|---|---|---|
| PHP Alpha.9 | monorepo annotated tag object `13f4967ad63ea4cd225e7009ae4f053276b37364`；commit `3d9d20fa496086891a364a08e30614b40d511c77`；tree `865fd4909f9622ab99148e66bb5fa792498112cc` | `packages/php/composer.json` 声明 13 个 PSR-4 root，其中包含 Ops Console | Application lock 使用同版本的 Composer split reference；消费身份以 lock reference 为准 |
| Admin Alpha.7 | annotated tag object `840672f81309f155d85f4b925a854e29938e1ee6`；commit `8d972eebeb0dd481c4713879a13fe113de57c3e3`；tree `da53b65a23d6c872afd3c00555f3ca31790e9e08` | 14 个 export，包括 `./core`、`./shell`、`./ops-console` 和三个 client 路径 | Admin Web 的已用入口和 PC20 计划入口均存在 |
| Admin Alpha.5 | Core Alpha.5 package source commit `aeeff105df4960db6a70da7ee5597da9a85abdaa`；tree `b5b18d8d84397fb0bc0c9c48bedcb986e340aa3e` | 15 个 export；PC/UniApp 使用的三个 client 路径存在 | PC/UniApp lock 的 registry integrity 是最终消费证据 |
| Core `dev` | `8608dafe30467c442000ce408b106d8750ffd766` | PHP/Web manifest 均为 Alpha.9 | 只用于核对当前源码方向，不是 Application 可消费身份 |

公共包只有 Composer `peanut-admin/core` 与 npm `@peanut-admin/admin` 两个安装边界。包内目录
是所有权边界，不得改写成多个 path package，也不得通过 `vendor/.../src`、
`node_modules/.../src` 或 Core 参考 Host deep import。

## 4. 版本差异不是无条件兼容

Alpha.5 的 npm package 有 15 个 export，Alpha.7/Alpha.9 有 14 个；后者不再导出
`./collaboration`。Alpha.7 到 Alpha.9 的 export/files 集合相同，但包内仍有类型实现变化；
当前四端源码都没有 import `./collaboration`，因此当前 lock 闭包不受影响。这证明“更高版本”
和“导出数量相同”都不能自动当作消费兼容证据。

| 检查 | 已确认事实 | 边界 |
|---|---|---|
| Admin/Platform Web peer | 两个 frozen lock 实际解析 Vue 3.5、Vue Router 4、Pinia 2 和 Element Plus 2.14，满足 Alpha.7 peer 范围；Platform 编译目标为 ES2022 | `web/package.json` 的 Vue 声明下限早于 Core peer 下限；兼容承诺只绑定 frozen lock，不覆盖任意重新解 lock |
| PC/UniApp peer | 两端声明的 Vue 范围满足 Alpha.5 的 Vue peer；Element Plus/Pinia 为可选 peer | 不把 Admin UI peer 注入无 UI client |
| 公共入口 | 四端当前 import 都能在各自标记 package manifest 中找到 | 新入口或版本移动必须重新做消费面检查 |
| Deep import | 当前前端使用包 subpath；PHP 使用 Composer namespace | Core 参考 Host、monorepo 路径和内部 `src` 不可作为生产依赖 |

## 5. 已发现的不兼容例外

`server/app/common/service/capability/CrossProductAdoptionHost.php` 与其历史测试仍 import
`PeanutAdmin\Collaboration\...`。当前 Alpha.9 Composer manifest/lock 已不再声明该 namespace；
仓内调用检索只找到该历史测试，没有 HTTP route、Module 或日常 Runtime 入口。

处理决定：

- 它不能作为当前 Alpha.9 下游采用成功的证据，也不能被 PC20/PC30 复用；
- 它不阻塞只依赖 `PeanutAdmin\OpsConsole\` 的产品闭环主线；
- 在任何真实调用重新采用前，必须由独立最小任务删除这条历史 Host/test，或用重新获准且
  当前包真实导出的合同一次性替换；不得增加兼容 autoload、复制 Collaboration 或回退 Alpha.5；
- PC02 不修改 Runtime，也不把未执行的历史测试写成通过。

## 6. 验证 owner

| 关注点 | 日常事实/验证 owner | 本次是否运行 | 后续触发条件 |
|---|---|---|---|
| 版本与制品身份 | 四端 manifest/lock；Core 标记 package manifest | 静态核对 | 任一 manifest/lock/version/integrity/reference 变化 |
| create-app 固定版本 | `server/tests/Productization/CreateApplicationTest.php` | 否；PC02 纯文档 | scaffold 或客户端版本变化 |
| 公共入口和 deep import scanner | `scripts/test-core-upgrade-public-entry-gate`；固定版本矩阵由 `scripts/core-upgrade-compatibility` / `scripts/combined-upgrade-qualification` 拥有 | 否；历史矩阵只覆盖其固定版本，不能冒充当前 Alpha.7/Alpha.9 资格 | package 公共表面或升级策略变化 |
| PC20 Ops Host | PC20 的 PHP Provider/HTTP/permission 聚焦检查与 Platform Web 生产构建 | Platform Web build 已在 PC20 候选通过；固定实现身份由合入后状态文档记录 | Ops 公共入口、Platform transport、Provider 或权限路由变化 |
| PC30 备份 Provider | PC30 的 Provider、任务、幂等、失败清理和安全输入聚焦测试 | 尚未开始 | PC30 Runtime diff 形成后一次执行 |
| 最终组合 | PC70 固定候选 | 尚未开始 | 关键路径完成并冻结后一次执行 |

PC02 的最低验证是文档登记、链接、生成目录、静态身份对照与 `git diff --check`；它不安装
依赖、不访问 Registry、不启动数据库/服务/容器/浏览器，也不重跑历史升级矩阵。

## 7. 下游领取条件

| 下游 | 版本结论 | 仍需自己的 Gate |
|---|---|---|
| PC10 安装预检 | 不依赖新增 Core；可领取 | 检查 code/reason/remediation、安全资源选择和 CLI/Web 共用 Host |
| PC20 Ops Console | Alpha.9 PHP + Alpha.7 Web 已提供所需公共表面；可领取 | Application Provider、路由权限、Tenant/Platform audience、异常 fail-closed 和页面验证 |
| PC30 备份 Provider | Alpha.9 PHP 已提供通用 Provider/任务合同；可准备 | Deployment Adapter、登记资源、制品 manifest/checksum、幂等和失败清理 |
| PC50 Module 信任 | 不能由当前 lock 自动满足 | archive digest、签名/SBOM/许可证、兼容解释和漏洞响应 |

若下游证明现有公共合同真实不足，只新增能被该切片立即消费的最小 Core 合同，并采用新的
不可变包身份；不得先复制 Core 参考 Host 或建立第二 Runtime。
