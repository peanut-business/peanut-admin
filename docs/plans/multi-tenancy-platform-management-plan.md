# Peanut Admin 多租户与平台管理计划

> 状态：当前权威执行计划  
> 日期：2026-08-13
> 架构基线：`docs/design/saas-enhancement-blueprint.md`

## 1. 当前目标

当前不建设完整 SaaS 商业产品，先交付三项能力：

1. **多租户基础**：统一 Account、Tenant、TenantMember、可信 TenantContext、权限、模块和全链路隔离。
2. **实例内平台管理**：使用独立 PlatformOperator 管理本应用实例的 Tenant 生命周期、首个 owner、TenantModule 和平台审计。
3. **独立运营平台边界**：为跨应用实例的 Release、版本、升级、健康和备份冻结管理协议，并在独立项目中实现。

当前明确延后：套餐销售、订阅、计费、试用、续费、发票、自动收款、商业配额、自助购买、自定义域名商业流程和应用市场。

## 2. 三类能力不能混用

| 能力 | 所属位置 | 当前是否需要 |
| --- | --- | --- |
| Tenant 隔离、成员、权限、租户切换 | Peanut Core + 应用 Host | 需要 |
| Tenant 创建/暂停、首个 owner、模块开通 | 单个应用实例的平台管理面 | 需要 |
| Release、实例、升级、健康、备份 | 独立运营平台应用 | 需要，单独立项 |
| 套餐、订阅、计费、试用和续费 | 未来 SaaS 商业控制面 | 暂不需要 |

PlatformOperator 只治理本实例 Tenant，不拥有租户业务数据权限。独立运营平台的运维账号是另一套身份，不复用 PlatformOperator 或 TenantMember。

## 3. 当前事实

- Peanut Admin Core 已有 Tenant、TenantMember、PlatformOperator、认证、RBAC、typed-target 数据权限、TenantModule、审计和 Host 组合实现。
- 已发布的 Core Alpha 包和固定资格仅证明精确版本能力，不等于当前移动分支可直接作为所有产品生产基线。
- Peanut Admin 应用仓已接入默认 Tenant、可信管理端 TenantContext、Article
  Tenant-first Runtime、首批非 SQL Tenant 边界和实例内 Tenant 治理；这不表示
  MT02–MT04 已整体完成。
- 核心项目生成器已经固定公司级 MT01 承接版本；Generator 只创建新项目，禁止
  覆盖更新已有项目，后续升级仍需独立追加式升级合同。
- DCS 正式主项目必须从批准的 Peanut 模板新建；旧 DCS/POS Runtime 只作业务规则、契约、样本、迁移和验收参考。
- DCS D1 不需要等待完整 SaaS，只需要通过 Peanut 最小承接 Gate。

## 4. 执行顺序

下表规定最终集成和完成声明顺序，不是阶段级串行锁。Gate 只阻塞直接依赖其
缺失输入的交付物；文件 owner 不重叠、可独立回滚且不消费该输入的合同、迁移、
Runtime 和 fixture 必须继续并行。每个阻塞项必须记录具体缺失输入、受影响交付物
和解除条件，并同时列出仍可推进项。

| 阶段 | 目标 | 状态 |
| --- | --- | --- |
| MT00 | 关闭在途核心能力和包/文档事实冲突 | 已完成 |
| MT01 | 冻结公司级 Core/Generator 承接基线 | 已完成；DCS Product-only 条件采用 |
| MT02 | Peanut Admin 采用单默认 Tenant | 开发完成；固定候选待 MT05 集中验收 |
| MT03 | 完成 SQL、缓存、文件、任务和审计隔离 | 开发完成；固定候选待 MT05 集中验收 |
| PM01 | 完成本实例 Tenant 平台管理 | 开发完成；固定候选待 MT05 浏览器联调 |
| MT04 | 完成多租户前端和应用 Host 闭环 | 开发完成；固定候选待 MT05 浏览器联调 |
| MT05 | 双模式安装、升级、下游和浏览器验收 | 进行中（候选已固定，两个 harness 尚未执行） |
| MT06 | 发布多租户稳定基线 | 未开始 |
| OP01 | 独立运营平台协议和项目立项 | 未开始 |
| OP02 | 独立运营平台首个实例管理闭环 | 未开始 |
| SAAS-FUTURE | 套餐、订阅、计费等完整 SaaS | 暂缓 |

### 当前恢复指针

更新时间：2026-08-13 14:15 CST。MT05 唯一代码候选固定为
`fafb1ae095e78ff6b12be660fc50b2987a331144`，tree
`9ded9f5ee80aa8d864a35175cecb965bf4f0cb49`。后续纯文档 merge 不改变本轮
qualification 输入；两个 harness 必须在该精确 detached commit 上各执行一次：

| 项目 | 状态 | 固定证据 |
| --- | --- | --- |
| CAP01–CAP04 Core Runtime | 已完成并合入 Core `dev` | Core PR #7、#9、#10、#14；禁止重复验收 |
| CAP05 产品中性 fixture 修复 | 已完成并合入 Core `dev` | [Core PR #16](https://github.com/peanut-opensource/peanut-admin/pull/16)；source `14010993e47f5e3082ab8f0b53456f282b71f086`；tree `3fa7e79730ec9ed8f0349dc1c0d24fa72cfda54f` |
| CAP05 双投影资格 | 已通过并合入 Core `dev` | Composer `ca30576a…e5c0e`；npm `5d010762…8c80`；Core PR #17 merge `3ca731804eb8291408e03c0ae18299d2b7db1cb7` |
| CAP06 MySQL 8.4 / Collaboration 修复 | 已完成并固定 | Core source `0f3c0a530f2b6369bf5883b2508f40a79501ed98` / tree `691cf4812d08dc4a3927a78331be3267aa1e9c77`；Core PR #18–#23 |
| CAP06 Peanut Admin 私有采用 | 已完成 | 应用 PR #23 实现、PR #24 最终五组 CI；`dev` `bafdf5b5aeb34d63e3b6c21a29817e688783ed21`；Core adoption record PR #24 merge `76fa36e461ca73cb9a4e8367cbcc3d71e4672ba7`；不宣称跨 Tenant Article 隔离或全局事务 |
| Alpha.5 Composer 公共发布 | 已完成 | `peanut-admin/core@0.1.0-alpha.5` 已由 Packagist 固定到 split `ef06da45c9e77ae4b194bfc1f859ec007aa0e022`；2026-08-13 全新临时 consumer 精确安装并加载 `PeanutAdmin\Kernel\Package` 通过 |
| Alpha.5 npm provenance 公共发布 | 已完成 | `@peanut-admin/admin@0.1.0-alpha.5` 已公开，`alpha=0.1.0-alpha.5`、`latest=0.1.0-alpha.2`；Registry integrity `sha512-brHwkDH1Ym1EHFEBJDu+L956Wq3rwtxTaeaIvwPL7mMk8KKur82nqRnp/yk7RSnmScl/XeXMaj2HrTeQqTiOIQ==`，SLSA attestation 绑定 GitHub Actions OIDC。2026-08-13 全新 consumer 安装、31 signatures、20 attestations 和 15 exports 验证通过；发布 run `31595501585` 的 publish 步骤成功，紧随其后的即时可见性 E404 是 Registry 传播竞态，不是重新发布依据 |
| MT01 固定 Core/Generator 身份 | 已完成 | Core `dev`/merge `cc9595e4a685ba5376b374d06084b71928f7f38c`，tree `3ae3abea248571e93dda54eb1564c0f8b954a250`；Generator `sha256-git-blob-manifest-v1`，683 files，digest `d30b740be7160864ac8128a43e7b160f45e46dffad3cd120c05e74bc3428afc6`；最终 reseal Core PR #59 六项最新-head checks 全绿后合入 |
| MT01 Generator 与安装 | 已完成 | 参数化/确定性/移除 example/只创建不覆盖由 Core PR #29/#36 固定；新生成项目空库 install/migrate/start 由 Core PR #50 一次聚焦组证明 |
| MT01 Generated Host | 已完成 | Core PR #49 的 MySQL 8.4 聚焦组证明外部 `fixture.record` Module、可信 Tenant/Client、同形跨 Tenant 拒绝、Module/permission/Data Provider fail-closed、幂等及领域/audit/outbox 原子失败注入；禁止重复运行 |
| MT01 Admin Web | 已完成 | Core PR #57 一次桌面 Chromium smoke 证明登录、双 Tenant 选择、外部 Module 页面及跨 Tenant/未知资源同形 404；六项最新-head checks 全绿后合入，禁止重复运行 |
| PA-DCS-ADOPT-01 nomination | 已完成 | DCS PR #2 readiness harness；PR #3 merge `b29495df90db97763ad9abd322e718401af9c6c6` 注入唯一 source/tree、Generator、Composer/npm 和 canonical parameters，结果仅为 `CANDIDATE_INPUT_READY_NOT_ADOPTION_PASS` |
| PA-DCS-ADOPT-01 decision | `CONDITIONAL`，Product-only 可消费 | DCS PR #4 merge `a2e10655f451a26bbd9b82b817bd7f31c88a2337` 完成 A–D 正式裁决；允许另行批准后创建首个 D1 Product-only Host，不批准 D1 业务代码、Pricing/Inventory/Trade/POS/设备/支付/生产或完整 SaaS |
| MT02–MT04 / PM01 应用主链 | 部分完成，已进入收尾 | 应用 PR #39/#37/#53/#55/#56 完成默认 Tenant、Article、字典、装修页、会员；PR #38/#40/#42–#45/#62/#64/#66/#68/#69/#71/#72/#76 完成缓存、文件、任务、日志、通知、OAuth、导出、设置、财务、RBAC 和 Tabbar 等隔离；PR #41/#46/#47/#48/#54/#57–#61/#63/#70/#73–#75 完成 PlatformOperator、Tenant 生命周期/首 owner/TenantModule、Tenant session 与平台 Web/HTTP 主链 |
| MT05 集中验收准备 | 进行中，未执行固定候选 | 浏览器 harness PR #78 merge `a44d25ab583152f646d55fecd9d8ab4c74117020`；安装/升级 harness PR #77 merge `84e78aed5b5738755fbfd14d3af86cfd75f1e9c0`。两者只证明 harness 可用，不等于 MT05 Gate 通过 |
| MT03 后台 diagnostics | 已完成 | 应用 PR #79 merge `72d14679356bced34dd291e0d4cb0588f78a72cd`；退款对账和定时演示日志携带可信 Tenant 与稳定 correlation，未改变业务状态机 |
| MT05 候选前安全收口 | 已完成 | PR #81 实例工具边界、#82 Article collect/member 复合 FK、#83 同步 XLSX Tenant namespace、#85 会员上传可信上下文、#86 Admin/Role/Dept/Jobs Tenant-first CRUD 均在最新 head 快速 CI 通过后合入 |
| MT05 固定代码候选 | 已固定，尚未 qualification | commit `fafb1ae095e78ff6b12be660fc50b2987a331144`；tree `9ded9f5ee80aa8d864a35175cecb965bf4f0cb49`；不得用后续移动 `dev` 或文档 merge 替代 |

中断后恢复步骤：

1. 读取两个仓库根 `AGENTS.md`，确认工作目录仍为 `peanut-admin` 和 `peanut-admin-core`。
2. CAP06 已完成；不得重复其真实 MySQL Gate、CAP01–CAP05 或已通过 CI。
3. Alpha.5、Generator、Generated Host、空库、Admin Web 和 PA-DCS nomination/
   decision 已完成；不得重复 publish、dispatch 或运行这些 Gate。
4. DCS 后续只消费 PR #4 的 `CONDITIONAL` 边界：先单独批准 D1 Product-only，
   再从固定参数创建新 Host 并冻结实际 Module/manifest/migration/API/permission
   写集；不得复制旧 Runtime，也不得把 adoption 当作 D1 业务实现 PASS。
5. Peanut Admin 当前关键路径是 checkout 精确候选 `fafb1ae095e78ff6b12be660fc50b2987a331144`，
   只运行一次安装/升级和浏览器集中验收。不得改用移动 `dev`，也不得重复
   CAP01–CAP06、MT01、Tabbar 或已通过业务聚焦 Gate。
6. 性能与 Recovery 作为阶段末后续项登记，不阻塞当前业务稳定候选；只有发现 Tenant
   隔离、安全、Schema/数据完整性或核心业务失败时才阻塞对应候选。

### 当前未完成闭包

以下矩阵只记录阶段完成条件的缺口，不把已合入切片或 PR 数量当成阶段完成。每项的
“下一个交付物”是恢复后优先领取的最小可合入闭包；普通切片不再先拆独立合同 PR。

| 阶段 | 已完成事实 | 尚缺验收 | 下一个可合入交付物 |
| --- | --- | --- | --- |
| MT00 | Alpha.5 Composer/npm、Core/Generator 身份和 DCS 条件采用已固定 | 无；禁止重复发布、资格和 clean-consumer Gate | 无 |
| MT01 | Generator、空库、Generated Host、Admin Web 和唯一候选身份已固定 | 无；Generator 仍只创建新项目，已有项目升级归 MT05 | 无 |
| MT02 | 默认 Tenant/Account/TenantMember/owner、旧管理员/RBAC/部门/岗位映射、Admin/Role/Dept/Jobs Tenant-first CRUD，以及 Article、字典、装修、会员等代表 SQL 域已合入 | 代码开发闭合；外部公众号 reply 因缺可信 Tenant routing 保留为非代表业务架构缺口，不伪造隔离 | 在固定候选的 MT05 安装/升级与浏览器矩阵中共同验收，不再拆普通功能 PR |
| MT03 | cache/lock、文件、任务、日志/diagnostics、OAuth、导入导出、同步 XLSX、会员上传和实例工具边界均已有 Tenant 隔离实现 | 代码开发闭合；集中候选尚未共同验证 | 在固定候选上执行两个 MT05 harness 各一次，不扩大到 Performance/Recovery |
| PM01 | PlatformOperator 独立认证/RBAC/session、Tenant lifecycle、首 owner、TenantModule service/HTTP/Web 与平台 mutation Host 已合入 | 需要在 MT05 同一浏览器候选验证完整业务链 | 使用固定候选执行浏览器 harness 一次 |
| MT04 | 可信 TenantContext、Tenant 选择/切换/撤销、Admin bridge、Web session、Standalone UI、平台/Tenant 导航和实例工具 guard 已合入 | 需要验证旧 token、上下文清理和双模式入口的真实浏览器闭环 | 使用固定候选执行浏览器 harness 一次 |
| MT05 | 两个 harness 已合入；代码候选 `fafb1ae095e78ff6b12be660fc50b2987a331144` 已固定 | Standalone 空库、v1.0.0 前滚、多租户空库和完整平台→Tenant 浏览器矩阵尚未执行 | detached checkout 固定候选，依次运行两个 harness 各一次 |
| MT06 | 未开始 | 稳定候选、版本一致性、发布 manifest、应用 lock、tag/Release 和最低发布验证均缺 | 只在 MT05 通过后固定一个候选并走单入口发布 |
| OP01 | 未开始 | 独立仓库、协议、身份、签名任务、数据边界和项目立项均缺 | MT06 前可并行冻结独立运营平台协议与仓库边界，不写业务实例 Runtime |
| OP02 | 未开始 | Release/实例/升级/健康/备份首个闭环均缺 | OP01 合入后在独立仓库实现一个签名升级任务纵向切片 |

旧应用 PR #49 已在 PR #50 的新权威指针合入后关闭。Core PR #53 已在全部声明检查
成功后合入为 `5e105f15b58ef8e271905283bfa07c34ce6d8b7c`，current-schema
`installSql()` 可作为 MT05 clean-install 的已合入输入；不得再按旧状态等待或重复其 Gate。

## 5. MT00：关闭当前在途工作

- 完成核心媒体/工作流候选的资格、文档事实收口、发布与独立下游采用。
- 统一 PR、Registry、Git tag、Release、应用 lock 和状态文档。
- 不允许当前移动 HEAD 或未资格 Alpha 候选成为新的产品承接基线。

完成条件：存在唯一、精确、可回溯的 Core 和 Generator 候选输入；没有冲突的发布/资格表述。

## 6. MT01：最小承接 Gate

公司级承接基线必须固定精确 source commit/tree、generator digest、Composer/npm 版本和不可变来源，禁止只写分支名。

最低门禁：

1. 相同参数两次生成得到确定性结果，并记录完整生成身份。
2. 新项目可空库安装、迁移、启动，不依赖旧项目目录、vendor 或数据库。
3. 产品自有 namespace、Tenant Clients、API prefix、Module 和迁移所有权可配置。
4. fictional/example Module 可完整移除。
5. Tenant/Client 隔离、跨 Tenant 拒绝、Module disable、权限/Data Provider 缺失均 fail closed。
6. 一个虚构 Host command 的领域写、幂等、审计和 Outbox 在失败注入下无部分提交。
7. Admin Web 完成登录、Tenant 选择、拒绝访问和一个外部 Module 页面最低 smoke。
8. 明确生成器不覆盖更新已有项目，并记录后续升级方案。

Gate 失败时只允许继续合同、数据和 fixture 准备，不允许复制旧 Runtime 绕过。

本 Gate 的未完成项只阻塞最终公司级承接身份、`PA-DCS-ADOPT-01` 提名以及依赖
这些证据的 MT02 集成验收；不阻塞 MT02 中已有稳定 Core 公共 API 可支持的默认
Tenant bootstrap、管理员/RBAC 映射、Article 所有权迁移和 Tenant-first Runtime。

### PA-DCS-ADOPT-01 承接状态

当前状态：`CONDITIONAL`，仅允许首个 DCS Product-only 纵向切片进入单独 D1
批准。唯一 Peanut 承接 owner 已完成候选提名与 A–D 裁决；DCS 不另行选择或拼装
候选。下列身份不得使用分支名、移动 HEAD 或推测值代替：

| 候选身份 | 当前值 |
| --- | --- |
| 源仓库 | `https://github.com/peanut-opensource/peanut-admin` |
| 40 位 source commit | `cc9595e4a685ba5376b374d06084b71928f7f38c` |
| 40 位 source tree | `3ae3abea248571e93dda54eb1564c0f8b954a250` |
| Generator digest | `d30b740be7160864ac8128a43e7b160f45e46dffad3cd120c05e74bc3428afc6` |
| Composer 版本与不可变来源 | `peanut-admin/core@0.1.0-alpha.5` / split `ef06da45c9e77ae4b194bfc1f859ec007aa0e022` |
| npm 版本与不可变来源 | `@peanut-admin/admin@0.1.0-alpha.5` / Registry integrity 见恢复指针 |
| 完整生成参数 | DCS PR #3 `readiness/pa-dcs-adopt-01/candidate-lock.json`，canonical SHA-256 `228631df989fe3ddc6d05441ad404878d6b309bdc324cb3f71fd1a3e179c7429` |

Peanut owner 已只提名一个候选并给出：

- Module 扩展和产品 namespace/API prefix/migration owner 的生成证据；
- Tenant、Client、权限、数据、缓存、文件和任务隔离证据；
- 空库安装、确定性生成、版本升级、失败停止和回滚/恢复证据；
- DCS 专用最小 Host、Module 删除能力和浏览器 smoke 结果。

正式裁决固定于 DCS PR #4。它允许 DCS 在**另行批准 D1 后**创建新 Host 并实现
Product-only 纵向切片；不批准 D1 业务代码本身，也不批准其他领域、生产或完整
SaaS。候选身份变化或触发安全停止线时，由 Peanut 重新提名并裁决。DCS 不得回退、
复制或继续扩展旧 Runtime。

## 7. MT02：Standalone 采用默认 Tenant

并行边界：本阶段可以在 MT01 最终 Gate 前以独立 PR 实现不依赖最终包/Registry
身份的切片。最终集成候选、跨阶段整体验收和“MT02 完成”声明仍需等待 MT01 所需
公共 API 与固定身份可用。任何新发现的阻塞必须精确到缺失 API、schema owner、
文件冲突或验收输入，不得重新升级为阶段级冻结。

- 安装器创建默认 Tenant、Account、TenantMember 和首个 owner。
- 现有管理员、角色、部门和岗位映射到租户模型。
- 为业务表按所有权账本增加并回填 `tenant_id`，再收紧非空和复合唯一约束。
- Article 必须正式增加并回填 `tenant_id`，所有读取和写入改为 Tenant-first 查询；
  验收同 ID/可见状态下的跨 Tenant Article 拒绝，并为租户所有权建立必要的复合唯一
  约束和索引。CAP06 的单默认 Tenant 采用证据不得替代本项验收。
- Standalone 默认隐藏 Tenant 选择和平台入口，保持现有业务结果不变。
- 禁止维护单租户/多租户两套业务 Service 或长期双字段兼容。

## 8. MT03：全链路隔离

隔离必须覆盖：

- SQL 查询、关联、聚合和唯一约束；
- Redis key、缓存失效和锁；
- 文件对象、临时下载和配额；
- 队列、定时任务、导入导出和异步上下文；
- 搜索、事件、审计、日志和诊断标签。

两个 Tenant 的相同业务对象 ID、缓存 key、文件名和任务不得互相可见。无可信 TenantContext 默认拒绝。

## 9. PM01：实例内平台管理

当前只实现多租户运行必需的治理：

- PlatformOperator 独立登录、角色、权限和审计；
- Tenant provision/activate/suspend/close 状态机；
- 首个 Tenant owner 安全建立；
- TenantModule 开通和配置验证；
- Tenant 暂停后拒绝新会话和业务写入；
- 平台与租户会话、Repository、权限目录和导航完全分离。

PM01 不包含套餐价格、订阅、计费、试用、续费、商业配额或跨实例管理。

## 10. MT04–MT06：应用闭环与发布

- 完成登录后 Tenant 选择、可信切换、撤销和旧上下文清理。
- Standalone 和多租户模式使用同一 Release，只由部署配置决定界面和能力组合。
- 真实浏览器验证：平台建 Tenant/owner → owner 登录 → 配角色/模块 → 成员使用代表业务 → Tenant 暂停。
- 验证 Standalone 空库、现有 v1.0.0 前滚、多租户空库和至少一个真实下游采用。
- 发布时 Core、Generator、Registry、Git tag、Release manifest、应用 lock 和文档版本一致。

完成 MT06 后，Peanut 可以作为公司产品的稳定多租户脚手架；这不宣称已完成完整 SaaS。

## 11. DCS 承接规则

- DCS D1 的领域合同、数据清洗、fixture、迁移映射和验收矩阵现在可以继续。
- D1 正式 Runtime 必须取得 MT01 中 DCS 专用 `PA-DCS-ADOPT-01` 的 `PASS` 或
  明确覆盖 Product-only 的 `CONDITIONAL`，并另行取得 D1 业务实现批准。
- D1 不等待 PM01 全部能力，更不等待 SAAS-FUTURE。
- 完整 SaaS 商业化不是 D1 前置；D1 只等待上述最小可消费脚手架承接 Gate。
- DCS 新主项目从批准模板生成；旧 Runtime 不作为正式实现基线。
- DCS 的 Tenant 与经营主体关系必须在正式表结构前单独冻结。
- Peanut Admin 产品化主任务已完成唯一候选和 `CONDITIONAL` 裁决；DCS 不得自行
  选择替代版本，后续身份变化必须重新提名和裁决。

## 12. OP01–OP02：独立运营平台

运营平台必须在独立仓库、独立数据库和独立部署中实现，首期管理：

- Application、Release、Artifact 和兼容范围；
- DeploymentInstance、环境、版本和实例凭据；
- 升级计划、执行结果、回滚和备份证明；
- 健康心跳、告警和最小脱敏诊断；
- 运维身份、角色和不可变审计。

业务实例应主动出站拉取签名任务；运营平台不得直连业务数据库、保存客户管理员密码、自动成为 PlatformOperator/TenantMember，或汇总租户业务数据。运营平台离线时业务实例继续运行。

## 13. SAAS-FUTURE 停止线

未经新产品决策，不实现：

- 套餐、订阅、试用、续费和计费；
- 支付、发票和商业结算；
- 商业配额与超额计费；
- 客户自助购买和应用市场；
- 跨租户代运营；
- 以 SaaS 名义建立超级管理员或绕过 Tenant Guard。

未来启动时，以 `docs/plans/saas-enhancement-development-plan.md` 为历史输入重新冻结执行合同，不直接照旧计划编码。
