# 模块架构改造完成报告

## 执行摘要

- 事实基线：`origin/dev@7c117404b9a37c54d39ca1ea771f9dd5406dff68` =
  `origin/main@7c117404b9a37c54d39ca1ea771f9dd5406dff68`
- 开始时间：`2026-08-26T08:45:30Z`（任务 0 最早功能提交）
- 实现与验收合入时间：`2026-08-26T21:18:57Z`（审查后治理 PR #268 合入）
- 实现 PR：7 个（PR #261—#266、#268，不含本报告 PR #267）
- 功能提交：22 个（不计 PR merge commit、报告提交和同步 `dev/main` 的 merge commit）
- 范围：架构方案 A—E、任务 0—5、完成报告任务 6，以及审查后生命周期治理收口

本报告只把已经合入上述 `dev` 基线的代码和已经记录的本地验证写成完成事实。GitHub Actions
不作为日常合并门禁，也不在此冒充通过证据。

## 任务完成状态

- [x] 任务 0：其余官方模块归位、权限命名空间化、实例工具体验与开发文档
  （[PR #261](https://github.com/peanut-business/peanut-admin/pull/261)，13 个功能提交）
- [x] 任务 1：`module:create` 模块骨架命令
  （[PR #262](https://github.com/peanut-business/peanut-admin/pull/262)，1 个功能提交）
- [x] 任务 2：8 个官方模块打包与生产 tree-shake 验收
  （[PR #263](https://github.com/peanut-business/peanut-admin/pull/263)，1 个功能提交）
- [x] 任务 3：Bundle 安装、整体退役、Purge 安全阻塞与中断续跑
  （[PR #265](https://github.com/peanut-business/peanut-admin/pull/265)，3 个功能提交）
- [x] 任务 4：权限回归断言与 Web 真实测试入口
  （[PR #264](https://github.com/peanut-business/peanut-admin/pull/264)，1 个功能提交）
- [x] 任务 5：Fixture 真实 package lifecycle 对齐
  （[PR #266](https://github.com/peanut-business/peanut-admin/pull/266)，1 个功能提交）
- [x] 任务 6：基于全部实现 PR 合入后的 `origin/dev` 生成本报告
- [x] 审查后治理：Bundle 整体停用、核心 Module 生命周期保护、安装中断前滚，以及 Web/CLI
  共用 Module 脚手架（[PR #268](https://github.com/peanut-business/peanut-admin/pull/268)，2 个功能提交）

## A—E 架构闭环

| 阶段 | 最终结果 | 状态 |
| --- | --- | --- |
| A | `official.article` pilot 与其余 7 个官方模块完成 manifest、后端、前端和权限归位 | 完成 |
| B | install、sync、retire、purge 统一复用 `ModuleCatalogApplier` | 完成 |
| C | Vite development 直接发现 `module.json`；`module:sync` 不依赖开发期 lock；无 `frontend_components` | 完成 |
| D | 单模块/Bundle 自包含包、完整性校验、可恢复安装/retire/purge、Platform 实例通道和生产 tree-shake | 完成 |
| E | `/dev-tools/modules`、统一脚手架创建、依赖/被依赖展示、安装、同步、整体停用/卸载确认和开发/发布文档 | 完成 |

## Module 清单

任务 2 的 8 个官方包来自固定候选 `2d8c1d2f4fb91870d3a31ae206e0bf4130945417`。
权限数量从当前 `Resources/permissions.json` 读取；前端入口均与 module key 派生路径一致。

| Module key | 权限数量 | 前端入口 | 打包 SHA-256 | 状态 |
| --- | ---: | --- | --- | --- |
| `official.article` | 13 | `web/src/modules/official-article/contribution.ts` | `6eaf27e0e10cee037dc78a7ca12b47060deaf409b8a9f209b2e0b8bfd94d5c1f` | 通过 |
| `official.file` | 11 | `web/src/modules/official-file/contribution.ts` | `ad2990bb22cd3090247b36d1ea998f13e4e6f560f744e94462976d35bdd7adfc` | 通过 |
| `official.task` | 7 | `web/src/modules/official-task/contribution.ts` | `c89ea80195c67971455fbd9ce02f0915022ef89384287ca90baf3854c8a757f5` | 通过 |
| `official.notification` | 7 | `web/src/modules/official-notification/contribution.ts` | `659993b27742517974be8a8f7980dfedb074ecb3b57351ba7bc670307d193300` | 通过 |
| `official.member` | 12 | `web/src/modules/official-member/contribution.ts` | `47b9590aeb1460670c728afbf6cc703096afaee431361c703282c1553870692b` | 通过 |
| `official.payment` | 10 | `web/src/modules/official-payment/contribution.ts` | `103d24097a0c1bde7d7f94c5c9e30e7929b34519e147737a9bf0c5b315ef004e` | 通过 |
| `official.oauth` | 17 | `web/src/modules/official-oauth/contribution.ts` | `694c667b9e0d0b8da841d62c994e411f9c91aaf338502ace59c60946206d85f2` | 通过 |
| `official.import-export` | 3 | `web/src/modules/official-import-export/contribution.ts` | `cdb6bd2055319a350c92a743ca57c2424d33f466d295af68e3b7b6f8b3ae4cbd` | 通过 |
| `fixture.delivery-record` | 2 | `web/src/modules/fixture-delivery-record/contribution.ts` | `149fb768030aa1239ec030cf43c26be44367b881abae612b94ef52c1035334de`* | 生命周期通过 |

\* Fixture SHA-256 是 PR #266 隔离生命周期合同生成的确定性测试包；该合同为中断恢复增加了
测试专用 owned table，不把该摘要冒充任务 2 官方包候选的一部分。

## 新增和收敛后的命令

- `php think module:create <module.key>`：生成 key 派生的前后端模块骨架。
- `php think module:sync [--module=<module.key>]`：把本地 manifest catalog 同步到开发库。
- `php think module:pack <module.key>`：生成单模块自包含 `.tar`。
- `php think bundle:pack <bundle.key> <version> <module.key...>`：生成不可拆装的多模块 Bundle。
- `php think module:install-package <tar> --sha256=<hash>`：在允许的实例工具环境校验并安装包。
- `php think module:uninstall-package <module-or-package-key>`：预览并确认 retire 或 purge。

`plugin:*` 只保留为发布工程或既有内部入口；普通模块开发工作流不依赖这些命令，也不提供长期
兼容承诺。

## Bundle 安装与卸载语义

- 单模块包的 package key 与唯一成员 module key 相同，安装、retire 和 purge 只处理该模块。
- Bundle 的 package key 独立于成员 key；安装一次登记一条 package installation，并登记全部
  `pa_plugin_module` 成员归属。
- 使用任一成员 module key 发起停用时，会解析并停用同一个 package 的全部成员，不保留部分 active
  的 Bundle 状态。
- 使用 package key 或任一成员 module key 发起卸载，都会解析到同一个 package，预览中的
  `affected_modules` 必须包含全部成员。确认计划不能删减成员，否则 digest/计划复核 fail-closed。
- 默认 retire 会移出 live 源码路径、软退役全部成员 catalog，同时保留 owned tables、业务数据、
  migration 账本和 RBAC 绑定。
- purge 才会物理清除全部成员的 owned tables、migration 账本、catalog 和预览确认的 RBAC 绑定；
  package/module 归属历史仍作为审计证据保留。
- 任一成员声明 `lifecycle.protected=true` 时，整个 package 禁止停用、retire 和 purge；当前
  `official.file` 是受保护的实例基础能力。
- enabled TenantModule 或仍处于 active 的显式 `module.json.dependencies` 业务依赖会阻止整个
  package 停用/卸载；普通业务记录引用不属于业务依赖。purge 另外检查外部 FK 和 owned-table FK 环，
  这些是物理删除的数据完整性停止线，不会阻止只保留数据的 retire。

## 实例工具能力

`/dev-tools/modules` 当前提供：独立 Platform 登录、模块与 package 状态、依赖/被依赖关系、已开通
Tenant 数量、与 CLI 共用生成器的 Module 创建、`.tar` 安装、单模块或全量 catalog sync、整体停用、
retire/purge preview、完整 Bundle 范围确认、处理中反馈和常见错误的人类可读说明。

运行时变更只允许 development + debug + Standalone，并经过 Platform 登录、精确
`platform.module.*` 权限和 InstanceTool 环境门控。TenantModule 开通继续由既有 Platform Tenant
接口处理，不混入装包动作；成员 RBAC 仍由 Tenant Admin 授权链处理。

## 验收证据摘要

- 8 个官方模块 `module:pack` 全部 exit 0；完整摘要见
  [`docs/packaging-validation-report.md`](packaging-validation-report.md)。
- Web 生产构建 exit 0；56 个产物文件中，dev-tools 文件名命中 0，8 个实例工具符号哨兵均命中 0。
- Bundle 合同验证：安装、重复安装 unchanged、完整成员预览、整体 retire、Purge 外部 FK 阻塞、
  跨成员中断、同计划 roll-forward 和重复 purge unchanged。
- 审查后治理合同验证：Bundle 整体停用及重复调用 unchanged、受保护 Module fail-closed、只把显式
  manifest dependency 视为业务依赖；安装失败后同一不可变包续跑，以及追加 repair migration 的
  更高版本前滚。
- Fixture 合同验证：真实 package install、重复安装、TenantModule 不被隐式开通、retire 数据保留、
  RBAC 删除预览、中断恢复和 purge 全清。
- 权限回归使用仓库真实后端检查入口和 Vite SSR Web 合同入口；没有继续使用不存在的
  `php think test` 或 `npm run test`。
- 所有生命周期验收使用登记的隔离数据库，PR 证据记录测试数据库清理为 0；本报告没有重新运行
  已通过且输入未变化的数据库 Gate。

## 现行边界与已解决事项

1. `official.file` 已声明为受保护的实例基础能力；因此包含它的 package/Bundle 不允许停用、retire 或
   purge。客服二维码对 `pa_file` 的引用仍由 purge 外键检查保护，但它只是数据完整性关系，不会被误判为
   `module.json.dependencies` 业务依赖。
2. Bundle 的 install、disable、retire 和 purge 均已收敛为 package 级操作；不存在只停用或只卸载一个
   成员后留下残缺 Bundle 的受支持路径。
3. 安装中断已采用 durable 状态前滚：同一不可变包可以续跑幂等步骤；DDL 结果不确定时不猜测重放，必须
   通过更高不可变版本追加带前驱声明的 repair migration 后继续，旧失败账本保留为证据。
4. 页面仍只提供请求级处理状态，没有后端逐迁移/逐表事件流或百分比进度。这是已接受的体验边界，不影响
   生命周期正确性；只有大型 Bundle 出现实际可观测性需求时才评估异步作业与进度事件合同。

本轮计划内 A—E、任务 0—6 和审查后治理均已完成，并已拉平到 `dev/main`。
