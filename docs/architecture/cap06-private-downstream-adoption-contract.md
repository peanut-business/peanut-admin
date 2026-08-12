# CAP06 私有下游采用合同

> 任务：`P1-CROSS-PRODUCT-DOWNSTREAM-001`
>
> 状态：已完成
>
> 下游 owner：Peanut Admin 应用仓

## 1. 固定输入

CAP06 只允许消费已经通过 CAP05 的两个投影，不使用移动分支、未固定的
Registry 版本或本地目录：

| 身份 | 固定值 |
| --- | --- |
| Core 源仓 | `peanut-opensource/peanut-admin` |
| Source commit | `0f3c0a530f2b6369bf5883b2508f40a79501ed98` |
| Source tree | `691cf4812d08dc4a3927a78331be3267aa1e9c77` |
| 资格与修复记录 | CAP05 `3ca731804eb8291408e03c0ae18299d2b7db1cb7`；Core PR #18–#23；rollover PR #22 |
| Composer 候选 | `peanut-admin/core@0.1.0-alpha.5` |
| Composer 投影 | 694 files / 14 PSR-4 roots / SHA-256 `8779231b00f8bd634635c246d569e896e36183f0d0ece8807584a8aa2632dcbd` |
| npm 候选 | `@peanut-admin/admin@0.1.0-alpha.5` |
| npm 投影 | 72 files / 15 exports / SHA-256 `5d01076276a4599682b65fcfde812f5fe201c3e597f2fab38b8ef23cbabe8c80` |
| 下游基线 | `peanut-business/peanut-admin@09eeb747c3fbe4f261da4fa6900d777796ab717f` |

CAP05 资格记录、两项 CAP06 MySQL 8.4 修复和 Collaboration revision guard 的固定记录共同构成本合同
前置。CAP06 不重新运行 CAP01–CAP05、未受影响的 npm 投影或已通过的仓库门禁。

## 2. 不可变消费方式

Composer 投影发布到生成型 split 仓
`peanut-opensource/peanut-admin-core` 的非默认候选分支
`candidate-alpha5-cap06`。该提交必须由上述固定 `packages/php/` 投影机械生成，
不得直接开发；生成后记录 40 位 split commit 和 tree。应用通过 VCS repository
锁定 `dev-candidate-alpha5-cap06#<split-commit> as 0.1.0-alpha.5`。

npm 直接使用 GitHub 的固定 source commit 和子目录：
`github:peanut-opensource/peanut-admin#db348c783ff8620fd77615294c946a36bca25a49&path:packages/web`。
`pnpm-lock.yaml` 必须记录同一 commit 的 codeload tarball 与 `packages/web` 路径。

以上来源只服务于 CAP06 私有采用。它们不创建 tag、Release、Registry 版本、
dist-tag 或稳定兼容承诺。公共 Alpha.5 发布后，应用必须切换为 Registry 精确版本，
删除候选 VCS 别名，但不得改变已记录的 CAP06 结果。

## 3. 唯一产品采用流

CAP06 是**单默认 Tenant 下的 Alpha.5 私有真实顺序消费证明**。应用以既有
Article 作为产品资源，只增加 Host adapter，不复制 Core Runtime：

1. 由可信 `TenantContext`、现有管理权限策略和 Article typed-target 可见性共同形成
   `AuthorizedOperationContext`；任一缺失均 fail closed。
2. 为 Article 创建不可变 `ArtifactRevision`，开启 Collaboration 会话并发布冻结版本。
3. 在同一 Article target 上执行 EntitlementQuota reserve/commit。
4. Workflow 只引用已发布 revision 的 key/digest，完成一次最小审批流。
5. 没有权限、Article 不存在或 target 不可见的调用必须返回同一非枚举拒绝形状；
   拒绝必须发生在任何 Core 写入前，不得产生 revision、collaboration、quota、
   workflow、audit 或 idempotency 写入。

该采用流是产品 Host 证据，不新增公开路由、页面、业务 migration、通用 Runtime、
双字段或兼容层。MT02 才负责把完整应用迁移到默认 Tenant；CAP06 不提前实施 MT02。

Article 当前没有 `tenant_id`，因此 CAP06 不证明真实跨 Tenant Article 隔离。Article
租户所有权、Tenant-first 查询、跨 Tenant Article 拒绝以及相应复合唯一约束和索引
由 MT02 实施并验收；MT02 以前不得声称这些隔离已完成。

Revision、Collaboration、Quota 和 Workflow 各自在自身公共 API 的事务边界内提交。
CAP06 只证明按该顺序真实消费，不宣称四个 Runtime 组成一个全局原子事务。后序失败
不得伪报成功；对 Core 已公开支持的可撤销状态（例如 pending quota reservation）
执行确定性补偿。若未来需要全局 Saga 或事务协调器，必须另立合同，CAP06 不实现。

## 4. 精确写集

合同提交只允许修改本文和多租户权威计划的恢复指针。

实现提交只允许修改：

- `server/composer.json`、`server/composer.lock`；
- `web/package.json`、`web/pnpm-lock.yaml`；
- `server/app/common/service/capability/ArticleCapabilityAuthorization.php`；
- `server/app/common/service/capability/CrossProductAdoptionHost.php`；
- `server/tests/Productization/CrossProductDownstreamAdoptionTest.php`；
- 本合同的结果区和多租户计划的 CAP06 状态；
- Core 仓新增一份 CAP06 adoption record 及其内容登记、状态链接。

PC、UniApp、路由、Controller、现有业务表、安装器、生产部署、核心包源码和既有
验收证据均不在本任务写集内。若上述白名单不足，先停止并修订合同，不扩写。

## 5. 最低充分验收

1. 静态核对两个 lock 的 source commit、split commit、版本、路径与 rollover Composer / retained npm digest。
2. 在独立 MySQL 数据库只执行一次
   `server/tests/Productization/CrossProductDownstreamAdoptionTest.php`，覆盖一条单默认
   Tenant 正向顺序流，以及权限缺失和 Article 不存在/不可见的同形前置拒绝；拒绝
   场景必须证明任何 Core 写入均未发生，不得以权限开关冒充跨 Tenant Article 隔离。
3. PR 自动 CI 负责既有 PHP lint 与 Web 类型/构建兼容；本地不重复同组验证。
4. 通过后在 Core adoption record 中固定应用 commit/tree、两个 lock 来源、split
   commit/tree、命令和结果。

失败后只做一次只读诊断并停止；不得降低授权、隔离、原子性或非枚举断言，
不得回退 Alpha.2/Alpha.3/Alpha.4 Runtime 伪造通过。

## 6. 停止线

CAP06 通过只证明 Peanut Admin 在单默认 Tenant 下可以通过公共 API 真实顺序消费该
精确 Core 候选。它不证明跨 Tenant Article 隔离或四 Runtime 全局原子事务，不发布
包，不形成 `PA-DCS-ADOPT-01`，不宣称 MT01、MT02、多租户或 SaaS 已完成。下一阶段
先单独批准 Alpha.5 公共发布，再以 Registry 版本和正式 Generator 执行 MT01 Gate。

## 7. 完成记录

- Core source/tree 固定为上表值；generated Composer split 固定为 commit
  `ef06da45c9e77ae4b194bfc1f859ec007aa0e022` / tree
  `e7beef2fe583ec6778e92b0d88702b1065fdb419`；npm 保持同 source commit 的
  `packages/web` 子目录。
- 应用实现 commit `d27e4b0ca2a17d5c0758bf743a6aead796276fdc` 通过唯一一次真实
  MySQL 聚焦 Gate；最终 `dev` 为 `bafdf5b5aeb34d63e3b6c21a29817e688783ed21`
  / tree `8193d219f2109f8d7b7ea0366a575cc2956715e4`。
- 应用 PR #23 的 PHP 8.3 Composer strict validation 失败，不作为最终 CI
  证据；最小 follow-up PR #24 在合入前 PHP 8.3、Web、PC、UniApp、Docs
  site 五组全部通过，且没有重复 MySQL Gate。
- Core adoption record 由 Core PR #24 合入 `dev`，merge
  `76fa36e461ca73cb9a4e8367cbcc3d71e4672ba7`；其六组现有 CI 全部通过。
- 结论仅为单默认 Tenant 的私有顺序消费证明；跨 Tenant Article 隔离仍归
  MT02，四 Runtime 全局事务仍不在本合同内。下一 Gate 是独立 Alpha.5
  公共发布决策。
