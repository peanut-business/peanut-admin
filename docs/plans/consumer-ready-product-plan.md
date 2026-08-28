# Peanut Admin 可消费交付推进计划

Document ID: `pa-docs-plans-consumer-ready-product-plan`

Status: `planned`

Owner: `product-architecture`

Audience: `maintainer, architect, ai`

Upstream: [`产品闭环执行任务队列`](product-closure-execution-queue.md)、
[`产品闭环可观测面板`](../product-status/product-closure-observability.md)、
[`产品能力账本`](../product-status/capability-ledger.json)、当前 Module/Plugin Runtime、
scaffold Release、文档登记和固定资格证据。

> - 建立日期：2026-08-28
> - Application 事实基线：`8ce916792829f08a1a17aa935667c06d55928a10`
> - 已通过产品闭环资格的 Runtime：`f6378f255241cbde25f374a8a0218fda4616c1ce`
>   （tree `184033c89425a0aa08f5591ce7f6a82735d47ad4`）
> - Core 输入基线：`8608dafe30467c442000ce408b106d8750ffd766`
> - 计划状态：**执行中；CR01/CR02/CR10—CR12/CR20 已完成，下一关键路径为 CR13 与 CR21**
> - 规模：**5 个阶段、13 个任务、1 次固定候选组合资格、1 次正式源码发布**

“可消费”是本计划的验收标签，不是新的产品名、版本后缀或长期兼容层。完成本计划后，外部
应用 owner 应能从正式 Release 创建独立应用，并在明确的开发与交付边界内创建、校验、打包、
安装、更新、停用、恢复、退役和按确认计划 Purge Module；二次开发代码、Tenant 数据和
app-owned 文件不会被框架升级静默覆盖。

## 1. 当前结论

产品闭环 PC00—PC70 已经完成，本计划不重新实施安装向导、健康诊断、备份、恢复、维护、
应用升级、配置转移、基础 Plugin 生命周期或 Module 生成器。当前真正的差距是“已有内部能力”
尚未全部形成“外部消费者可独立、可重复、可恢复地操作的正式交付面”。

| 目标能力 | 当前事实 | 可消费结论 | 本计划处理 |
|---|---|---|---|
| 从正式版本创建独立应用 | `create-app`、应用 manifest、文件 owner、fresh install 和 3.x scaffold upgrade 已验证 | Runtime 已具备；PC70 结果仍是 `dev` 候选，不是新的 main/Tag/Release | CR30、CR31、CR40 |
| 创建和开发 Module | `module:create`、development `module:sync`、Tenant 安全骨架已验证 | 已可用，但缺少一个面向作者的统一只读检查和完整交付示例 | CR20、CR21 |
| 打包 Module | `module:pack` / `bundle:pack` 生成确定性 tar、SHA-256，可选 Ed25519 签名 | 已具备直接分发基础，不等于 Marketplace 已开放 | CR20、CR21 |
| 安装 Module 包 | `module:install-package` 与 dev-tools HTTP/UI 已实现，service/真实数据库合同已覆盖 | 仅允许 development + debug + Standalone，且当前没有直接执行该 CLI、HTTP 请求或 UI 操作的端到端证据；不能冒充正式部署安装面 | CR10—CR12、CR21 |
| 更新已安装 Module | PR #339 已合入显式 `module:update-package`；PR #340 已合入 deployment-owned opaque request/worker | development/debug/Standalone 更新与交付编排均已通过登记 MySQL 聚焦验证；完整消费者纵向证据仍由 CR21 提供 | CR10—CR12、CR21 |
| 停用、恢复、卸载 | dev-tools/CLI 已有 disable、同制品 reactivation、retire 与双确认 Purge；PR #340 把 update/retire/Purge 接入登记 target、配对备份、隔离恢复、维护、审计、smoke 和 recovery pointer | 开发工具与交付编排已形成；完整消费者纵向证据仍由 CR21 提供 | CR10—CR12、CR21 |
| Tenant 开通与成员授权 | TenantModule enable/disable 与 RBAC 已验证 | 保持与 Package 安装分离；安装不得自动给 Tenant 或成员授权 | 全程不改变 |
| Tenant 停用安全边界 | API/管理入口已有门禁 | 直出 Tenant 文件和部分公共入口仍存在已登记 fail-closed 缺口 | CR13 |
| 文档与资料可发现性 | 文档治理与公开站已建立 | 建立本计划前登记有 72 项 archived、57 项 planned；`output/` 有 318 个受控文件，历史材料仍混在现行树 | CR01、CR02、CR22、CR23 |
| 正式消费者支持 | 有脱敏诊断包、版本和 Release 证据 | 缺统一问题提交物、兼容矩阵、失败恢复路径和消费者入口 | CR22、CR40 |

## 2. 可消费完成定义

只有同时满足下列条件，才能把目标 Release 标为 `consumer-ready`：

1. 从同一个 annotated tag / GitHub Release 创建全新独立应用，manifest、scaffold、源码 commit/tree
   和依赖身份一致；Standalone 与 Multi-tenant fresh install 均通过。
2. Module 作者能在独立应用运行 `module:create`、只读检查、Tenant 安全聚焦验证、确定性打包和签名；
   无需修改 Peanut Admin 源仓私有目录或手工维护第二套模板。
3. 应用 owner 能用受信 package 完成 install、v1→v2 update、disable、reactivate、retire 和显式
   Purge；依赖、版本、签名、checksum、migration、备份和维护失败均在破坏性步骤前停止。
4. Package、ModuleInstallation、TenantModule 与成员 RBAC 四层状态保持分离。安装/更新包不会自动
   开通 TenantModule 或授予角色；Tenant 开通不会改写 Package、migration 或代码树。
5. 交付环境使用 deployment-owned CLI/worker 和登记资源执行维护、配对备份、变更、smoke 与恢复
   指针；生产 HTTP 仍不接收任意包、路径、命令或目标地址。
6. 框架升级只替换 managed / generated-managed 文件，第三方 Module 与业务源码按 app-owned
   边界保存；不能以自动改写业务代码换取升级成功。
7. 公开文档从“创建应用”能导航到“创建 Module → 校验 → 打包 → 安装/更新 → Tenant 开通 →
   卸载/恢复 → 应用升级”，并给出版本、诊断包和安全问题提交规范。
8. 最终固定候选只运行一次消费组合资格，随后由同一 commit 创建 main、tag、Release 和不可变
   scaffold 身份；开放 PR、`dev` 候选或旧 P0-E 结果不能替代正式交付身份。

## 3. 范围与暂缓项

本计划实现受控的直接 Module 分发，不建设第三方 Marketplace。Marketplace 的下载目录、商业授权、
签名 authority、SBOM/许可证审核和漏洞响应只有另行立项后才恢复。下列事项不阻塞本计划，但新执行
会话最终必须重新列出，供用户另开任务：

| 项目 | 处理 | 原因或恢复条件 |
|---|---|---|
| 充值部分/多次退款 T16 | 暂不执行，最终重新报告 | 业务财务能力，与可消费 Module/二开链无直接依赖；需要独立资金与数据库资格 |
| 完整 SaaS 商业化 | 保持 deferred | 等真实消费者、运营和商业边界另行批准 |
| 跨实例运营平台 | 保持独立项目 | 不进入 Peanut Admin Runtime 或 Core |
| Marketplace | 保持 blocked | 等 archive、签名、SBOM、许可证、漏洞响应和兼容 authority 完整 |
| 真实邮件/短信/支付/OAuth/Storage Provider 资格 | 按 Provider owner 独立执行 | 通用 Gate 不发生真实资金或未授权消息 |
| 自动降级或覆盖 app-owned 源码 | 永久范围外 | 使用受控升级、重建和配对恢复 |

## 4. 执行任务队列

状态只使用 `未开始 / 进行中 / 部分完成 / 已完成 / 外部阻塞`。每项完成后由唯一 integration
owner 在同一 PR 或紧随的纯文档 PR 更新本表；稳定产品能力同时更新能力账本及其生成投影。

| 顺序 | ID | 任务 | 状态 | 直接依赖 | 主要交付物 | 推荐执行能力 |
|---:|---|---|---|---|---|---|
| 1 | CR01 | 历史资料与证据保留清单 | 已完成 | 无 | PR #336 合入路径级登记；保留、归档、删除和 unknown 决定均有 owner、引用、体积、风险与恢复方式 | Terra 只读扫描；主代理决策 |
| 2 | CR02 | 安全清理第一批 | 已完成 | CR01 | PR #336 删除 23 个零引用过期文件；不可变 Release、当前资格、scaffold 和 archived docs 保持 | Luna 机械执行；主代理复核 |
| 3 | CR10 | 可消费 Module 生命周期合同 | 已完成 | 无 | PR #338 合入唯一 Package 状态机、四层状态分离、维护/备份/审计/恢复边界与生产 HTTP 禁区 | Sol xhigh；公共 API/破坏性边界 |
| 4 | CR11 | Module package 更新实现 | 已完成 | CR10 | PR #339 合入 `module:update-package` 与共享 update service；登记 MySQL 8.4.10 聚焦生命周期验证通过且零数据库残留 | Sol high；聚焦实现 |
| 5 | CR12 | 交付环境 Module 操作入口 | 已完成 | CR10、CR11 | PR #340 合入 `dev=4844ef3…`（tree `c4b6971…`）；opaque request→备份→隔离恢复→维护→update→smoke→recovery pointer 全链通过且一次性数据库已清理 | Sol xhigh；部署与数据安全 |
| 6 | CR13 | Tenant 停用全局 Fail-Closed | 未开始 | 无 | 公共 API、文件和静态交付统一拒绝停用 Tenant；缓存/代理边界与恢复语义明确 | Sol xhigh；Tenant 安全停止线 |
| 7 | CR20 | Module 作者只读检查与版本规则 | 已完成 | CR10 | PR #342 合入 `dev=5797e28…`（tree `953ff07…`）；真实 CLI 正/负输出、八项稳定检查和零数据库访问验证通过 | Terra 定位 + Sol/Worker 实现 |
| 8 | CR21 | 双独立应用二开参考链 | 未开始 | CR11、CR12、CR20 | 从 Release 生成应用 A/B；第三方 Module v1→v2 完成 create/check/pack/install/update/disable/reactivate/retire/Purge 与 Tenant A/B | Sol high；真实纵向 fixture |
| 9 | CR22 | 消费者文档与支持入口 | 未开始 | CR11、CR12、CR20 | 任务导航、唯一命令索引、错误修复、版本兼容、诊断包/Issue 提交规范；不公开内部资源和证据 | Luna 文档；主代理事实复核 |
| 10 | CR23 | 历史文档与证据最终收敛 | 未开始 | CR02、CR22 | 处理被新指南替代的 planned/archived 文件，重建登记与导航，输出保留证据清单和删除报告 | Luna 机械执行；主代理批准删除 |
| 11 | CR30 | 封存前消费资格就绪 | 未开始 | CR11—CR13、CR20—CR23 | 用实际生成应用和第三方包形态验证所有入口、身份、目录、driver、阶段隔离与失败恢复；不产生 qualified | Sol high；Development mode |
| 12 | CR31 | 固定候选消费组合资格 | 未开始 | CR30 | 同一候选一次完成 create-app、双模式 fresh、Module v1→v2 全生命周期、应用升级、生产 Compose/浏览器与零残留 | Sol xhigh；唯一资格 owner |
| 13 | CR40 | 正式可消费源码发布 | 未开始 | CR31 | `dev→main`、annotated tag、GitHub Release、scaffold/manifest/能力快照和一次最低发布后核验 | Sol high；发布 owner |

## 5. 任务合同与最低验收

| ID | 最低验收 | 停止线 | 可并行项 |
|---|---|---|---|
| CR01 | 对每个候选给出精确路径、Git 状态、登记状态、入站引用、不可变证据价值、体积与决定；未知不删除 | 当前 PC70、正式 Release/部署快照、当前资源登记和用户修改不得列为直接删除 | 可与 CR10、CR13、CR20 只读准备并行 |
| CR02 | 删除前再次确认零引用和 Git 可恢复；文档登记、链接、生成目录和构建通过；报告删除内容与恢复方式 | 不以目录名或年龄批量删除；不移动仍被外部/代码引用的 canonical 路径 | 与 CR10/CR13 不同文件 owner 时并行 |
| CR10 | 固定同包高版本、失败修复版本、降级、不可逆 migration、依赖者、TenantModule active、protected Module、Purge 中断的语义 | 合同 PR 合入后下一周期必须由 CR11 产出实现；不先造 Marketplace/兼容层 | CR13、CR20 可并行 |
| CR11 | v1→v2 dry-run 零写入；成功时 package/文件/lock/DB 同一身份；每个失败点无越权部分状态且有恢复指针 | 不复用 install 隐式猜 update；不覆盖 app-owned；不自动降级或重放未知 DDL | CR13、CR20 可并行 |
| CR12 | 正式部署入口只能消费登记 target 和受信包；维护、备份、执行、smoke、退出维护的每一步可观察且可重试 | 生产 HTTP 上传/路径/命令继续 fail-closed；覆盖生产数据需独立授权 | CR20、CR22 准备可并行 |
| CR13 | Tenant 停用后管理、API、PC/H5、公开内容和 Tenant 文件均拒绝；恢复后只恢复合法访问 | 任一绕过属于安全阻塞，必须先修复再进入固定候选 | 与 Module 生命周期独立推进 |
| CR20 | 同一检查 Host 由 CLI/自动化消费；输出稳定 code/reason/remediation，零数据库写入 | 不维护第二套 manifest/schema/template | CR11、CR13 可并行 |
| CR21 | 两个全新生成应用之间完成第三方 Module v1→v2 交付，Tenant/Package/RBAC 四层断言和 app-owned 摘要通过 | Fixture 不能调用源仓私有路径或使用未登记 fallback | 等 CR11/12/20 后领取 |
| CR22 | 文档命令与真实 `--help`、路由、支持边界一致；公共站无凭据引用、内部候选或资源地址 | 未实现行为不能提前写成支持能力 | 可在实现冻结后与 CR21 结果整理并行 |
| CR23 | archived/planned/current 分类与文件树一致；历史不可变摘要可追溯；仓库和本地 cache 清理有精确报告 | 删除 material 前必须确认恢复方式；不压缩/重写 Git 历史 | CR22 完成后执行 |
| CR30 | 直接命中 CR31 将验收的生成物、包、驱动配置和阶段交接；全部聚焦检查通过后才 seal | 不用完整 P0-E 调试；同边界第二次失败先做边界矩阵 | 无非阻塞 Runtime 修改并行进入 |
| CR31 | 固定 candidate/tree、登记资源、一次 7+消费组资格、每组证据和零残留完整 | 候选变化后旧通过组不得冒充新资格 | 只允许资格直接证据 owner |
| CR40 | main/tag/Release/scaffold/source archive/账本快照引用同一身份，公开指南不再写旧示例为当前版本 | 不把 `dev` 资格、tag 或 GitHub Release 任一单项冒充完整发布 | 无 |

## 6. 实际执行方式

1. 新会话开始时先固定 `origin/dev`、开放 PR、所有 worktree、资源租约和文档 owner；计划中的
   `未开始` 只表示已获授权，不能替代代码与验证事实。
2. 主代理负责问题定义、公共合同、Tenant/数据/发布取舍和最终综合。大型只读文档/历史扫描优先
   `terra_researcher`；路径明确的登记、链接、生成和安全删除批次优先 `luna_worker`；关键 Runtime、
   安全、部署、资格和发布由 Sol 主代理或同等级 worker 完成。
3. 同一文件只有一个 owner。子代理不修改本计划、能力账本和可观测入口，只回传精确结果；integration
   owner 在远端事实稳定后一次同步，避免并行状态冲突。
4. 每个普通任务把合同、实现、必要 fixture、聚焦验证和 docs-impact 放在一个可审查 PR；CR10 因公共
   API、Purge 和生产边界可以独立冻结，但必须立即交给 CR11，不允许连续输出纯分析。
5. 每项只做一次最低充分验证。CR30 以前不运行完整 P0-E；CR31 只对一个冻结候选运行一次。
   GitHub Actions 状态不是合并或资格证据。
6. 涉及数据库、端口、服务、容器、缓存、浏览器或 Gate 时，先读取资源登记、声明实际 resource ID/
   环境/地址并 claim 租约；纯文档和静态清理不连接运行资源。
7. 每项合入后立即更新本计划状态；稳定能力变化同时更新 `capability-ledger.json` 并运行
   `php scripts/check-product-capability-ledger --write`。生成文档只走治理命令，不手改生成区。
8. PR 合入后释放资源并删除已合并临时 worktree、本地和远端分支。每个阶段结束、没有待定关键决定时，
   提醒 `/compact`，减少高价主线程上下文。

## 7. 文档与清理原则

- `docs/product-status/releases/`、`deployments/`、固定 release manifest 和当前 PC70 summary 属不可变
  证据，默认保留；截图、重复失败候选和过程日志只有在 CR01 证明无独立追溯价值后才能移出当前树。
- `archived` 表示不参与当前事实，不自动意味着必须保留物理文件。零引用、无外部稳定链接、Git 可恢复且
  已有替代入口的文件可以删除并保留 registry tombstone；仍有引用时先 redirect 或更新引用。
- `planned` 文档必须逐篇核对“尚未实施 / 已被实现吸收 / 仍为未来路线”。已经完成的目标不能继续以计划
  语气误导执行者；有效的未完成合同可以被 CR10/CR22 吸收后再归档原稿。
- `docs-site/node_modules`、构建目录、cache、临时日志和浏览器临时状态是本地生成物；确认无活跃进程和
  worktree owner 后按项目脚本清理，不提交、也不计入产品证据。
- `docs/design/saas-roadmap/` 只按引用和仍有效决定分批处理，不能整目录按“历史”删除。

### 7.1 CR01 初始清理盘点

下表是建立计划时的只读初筛，不是删除授权。CR01 必须在实际执行基线上重新核验入站引用、
Git 可恢复性、当前 owner 和不可变证据价值；CR02 只处理复核后仍属于“优先候选”的精确路径。

| 路径 | 初筛事实 | 初始处理 | 作用或风险 |
|---|---|---|---|
| `output/p0e-p0e55c3309/` | 10 个文件，约 1.36 MiB；旧 passed 候选 ID 只在自身 summary 出现，未发现 Release/账本引用 | CR01 复核后优先清理 | 减少重复旧资格证据；Git 历史可恢复 |
| `output/p0e-p0e8221e27/` | 10 个文件，约 1.36 MiB；旧 passed 候选 ID 只在自身 summary 出现，未发现 Release/账本引用 | CR01 复核后优先清理 | 同上，不得用目录年龄替代引用核验 |
| `output/playwright/mt05/` | 3 个文件，约 36 KiB；标记为 prepared/not-yet-executed，后续 PC70 证据已替代 | CR01 复核后优先清理 | 避免未执行 harness 被误读为当前证据 |
| `server/database/import.php` | 无已知调用者，但仍进入 application template inventory | 条件性候选 | 删除会改变 scaffold；必须同步 inventory 并验证 create-app |
| `scripts/scaffold-doctor` | 无已知日常调用者，但被 application inventory 与 v3.0.x scaffold manifest 导出 | 条件性候选 | 不能单文件删除，需先决定正式 scaffold 合同 |
| `docs-site/deployment.md`、`platform.md`、`product-status.md`、`troubleshooting.md` 及四个旧 `guide/` 页面 | VitePress 已 `srcExclude`，但仍在模板 inventory | 条件性候选 | 需先决定是否退出模板，再同步 registry、inventory 与生成物 |
| `output/p0e-p0e78e9667/`、`p0e-p0e215b/`、`p0e-pc11e1/`、`p0e-p0e210a1/`、`p0e-p0e211b2/`、`p0e-pc70q14/` | 被正式 Release、能力账本或当前 PC70 资格引用 | 保留 | 维持不可变发布、Module 与当前组合资格的追溯链 |

### 7.2 CR01/CR02 完成记录

- 固定盘点基线：`origin/dev=8735a669a3669d0628a1d02db2d1cbf02e3b823c`
  （tree `5204bae49cb60a36dee974dce68d20e38425035b`）。
- 路径级决定与恢复方式登记在
  `docs/maintenance/consumer-ready-evidence-retention.md`；PR #336 已合入
  `dev=e9548d925a03c2e0285eaaa1e4bcc1e33a922fbd`（tree
  `58b98ca4b81b41aa2820a91f2f5000637f4a1562`），删除两个无有效入站引用的旧 P0-E 输出和
  一个未执行 MT05 harness，共 23 个 tracked 文件、2,824 KiB。
- `server/database/import.php`、`scripts/scaffold-doctor` 和八个 archived docs-site 页面仍被
  scaffold/inventory、生成器、测试或账本消费，本批未删除；其退出决定后置到对应 scaffold
  owner 或 CR23，不影响 CR01/CR02 完成。

## 8. 状态同步与最终报告

新执行会话的最终报告必须包含：13 个任务状态、合入 PR/commit/tree、实际资源与一次最低验证、删除/
归档清单及恢复方式、未运行检查、剩余风险，以及第 3 节所有暂缓/范围外事项的重新输出。只有 CR31 和
CR40 均完成时才可报告 `consumer-ready`；若非阻塞项临时跳过，应标记为 `部分完成` 或 `外部阻塞`，
并说明它不影响哪些验收、为什么允许后置和下一独立任务提示词。

## 9. 本计划的最低验证

本计划是内部技术与执行事实，不投影内部候选、资源地址或删除清单到公开站。创建或更新时运行：

```bash
php scripts/check-product-capability-ledger --write
php scripts/check-product-capability-ledger
./scripts/docs-governance generate
./scripts/docs-governance check
./scripts/docs-governance impact --base origin/dev \
  --classification technical --classification developer-site --classification generated \
  --waive-target docs-site/index.md \
  --waive-target docs-site/reference/source-map.generated.md \
  --reason "internal plan and capability status are intentionally excluded from the public site"
git diff --check
```

纯计划变化不触发 Runtime reseal、数据库、Web/PC/UniApp build、浏览器或 P0-E。
