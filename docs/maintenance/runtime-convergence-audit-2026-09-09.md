# ThinkPHP 8 Runtime 收敛与跨类别历史整改审计

Document ID: `pa-docs-maintenance-runtime-convergence-audit-2026-09-09`

Status: `current`（阶段0–4已回收验收；阶段5首批S5-T01及有界补正已获根验收，正准备精确公开候选，尚不是运行时整改闭环或发布资格证明）

Owner: `product-architecture`

Reviewed at: 2026-09-10（材料归并日期；历史源码/测试事实不刷新验证日期）

## 阶段3当前残留与修复排程（已通过根验收）

2026-09-10根任务已验收逐项当前证据、状态和历史知识映射，并核过补正后的74个候选路径、29条命令cwd/输入、批次引用、C02/C12退出归属、质量目标去向及估算限制。Core直接集成命令已保留PEANUT_INTEGRATION=1及登记DB输入，未将必需组skip当通过。阶段3完成的是静态审计与排程，不是代码已修复；阶段4规则/文档及必要补正也已根验收。阶段5首批任务书见现行方案§12.3，独立任务 `01a08b36-5b92-7e51-a4e2-42741e855a9c` 已完成本地修复及CORR-01..03有界补正，并获根验收；当前只准备精确公开候选。逐项事实和任务卡见主登记 `stage3_assessment`、`stage4_rule_application` 和 `stage5_t01_execution`。未合并推送、发布部署。

## 阶段4规则修正（已通过根验收）

阶段4只修正现行入口中的冲突口径：正式技术栈固定为 ThinkPHP 8，Repository/transaction bridge 不再作为长期框架中立目标；普通 ModuleProvider 优先接口到实现类，配置、SDK、回调和可变 Worker 等动态边界保留闭包理由；产品/Core/双 Edition 同号与 Module/Instance 独立身份保持分离；bundled Rich Text、独立发布、媒体 spike 和真实厂商操作证据不互相替代；扫描器、静态观察、局部 smoke、skip 或 `PASSED` 不得冒充动态资格。阶段4还把唯一 writer、授权、恢复、消息插入/压缩后的只读恢复和 S4→S5 停止线同步到入口。

根任务已复核Core两份依赖文档及断言规则：有效断言须保留，有据证明错误的测试合同须正式修正。原登记区分实际修改/原本正确未改、触发/owner、人工/自动检查范围及S5依赖；文档检查和无历史入口核对不是真实压缩Hook、全工具硬拦截或产品资格。S5-T01具体任务书见现行方案§12.3：七个既有文件加Core只读Unit，Luna/medium实施；scanner词法语义和LazyDI负向控制的有界复核使用Terra/medium。正向domain-probe需已生成且Composer安装的应用根，归固定combined-qualification候选，不是直接DB前置；本批不冒称正向已通过。本地修复、补正和记录已完成并获根验收，Git 集成仍未核准。

## 阶段5首批 S5-T01（本地实现已根验收）

独立任务 `01a08b36-5b92-7e51-a4e2-42741e855a9c` 已完成本地修复。三份 Ablation 是带失败断言和负向控制的有界检查：DataIsolation 仍是内存 join 语义，LazyDI 验证现行 Registry 的认证、公开同一路由负向控制及未注册拒绝，Ergonomics 仅验证 Reflection/当前 hook 结构；均不声称 SQL、容器、性能或完整产品资格。scanner 现以实际 significant-token 索引排除注释/字符串关键词，且控制流/类声明（含引用返回）不再被视为失败能力；bare/empty/整数零与字符串 literal exit 按有界成功语义处理，非零整数为失败，`exit(0+1)` 保持未知。临时非fixture的零退出、空断言声明（含引用返回）和超过1KB空循环样本均以退出1和精确路径拒绝；带 `throw`/实际断言、非零 exit 与 `exit(0+1)` 的正常样本退出0，清理后的树也退出0。该scanner仍不是完整控制流证明。Member import/合同 Reflection、domain-probe 无参数stderr/退出2、三份 Ablation、integrity、CI shell syntax 和 Core WorkflowGraph Unit 均已完成聚焦检查；结果与退出码见主登记 `stage5_t01_execution`。正向 generated-app probe、DB/Provider、完整资格及集成推送仍未执行。

本次只读核查固定在 Application `origin/dev@ab96727c8b07da64489fe152b36e483f055dcf0c`、累计文档工作树同一 HEAD（含既有未提交文档及两处受保护 PHP 差异），以及干净的 Core `origin/dev@2ed77f38ca26472d685cfeb81674a66ba23eadb4`。Application 主 checkout 的 `main@8c8a974…` 比远端 `dev` 前进，但本次没有把它误作集成基线。远端引用已只读核对；没有 fetch、合并、资源连接、产品测试或发布。完整逐项处置、来源和候选写集在主登记 `stage3_assessment`。

当前仍需修复的主线是 TP8 Runtime：Application `AppService.php:127-133` 仍直接绑定 `PDO`、`PdoTransactionManager` 和幂等 PDO factory；Article、Task、ImportExport Provider 仍有 PDO/Core repository 装配；Core `packages/php/*/src/Persistence/Pdo` 与 backend/starter RuntimeFactory 仍是生产路径。它们不能按名称一次删除，因为 `ExecutionContextStore.php:24-46` 的 finally 清理、public/member fail-closed、Module 生命周期与 worker/CLI 路径是要保留的合同。静态接线不能替代异常、长驻进程、事务/并发和双 Edition 的动态资格。

两项既有脏修复保留原SHA：Member Provider 已加入 `MemberAdministrationService` import，聚焦autoload/Reflection检查退出0；升级 domain-probe 缺根参数当前写stderr并退出2，缺参负向检查已按预期执行。正向生成应用probe仍未运行，不能写成完整升级资格通过。`check-test-integrity` 和三份 Ablation 的本批有界测试可信性缺口已修正并获根验收，不代表全测试可信。

版本、模块和部署事实需继续分开：v3.0.14 是已发布的不可变历史、bundled Rich Text 身份有对应发布证据、multi-tenant production-candidate overlay 已验证；持久 Standalone 未升级，独立 Rich Text 发布和真实 Provider 操作均未由这些事实证明。当前 Application lock 仍是 Core `0.1.0-alpha.13`，故未来产品/Core/双 Edition 同号只能在新的冻结候选中实现，不能回填历史。当前 dev 包含 `563df8c4` 的 Core Storage Driver 采用；`590e6183` 是非祖先同主题早期提交，`563df8c4` 与 `64460af8` patch-id 相同；`e915bea7` 只记录隔离媒体试验。当前 Core `WorkflowGraph.php:10-470` 未见历史重复声明，故 CAP01 历史缺陷由当前静态证据支持已解决，但其单元测试未在阶段3执行。

阶段3、4已验收；S5-T01及CORR-01..03已完成本地实现并获根验收，当前只准备精确公开候选，未获 Git 集成核准。阶段5后续须再次按任务书确认，顺序为 ModuleProvider 简化 → Core TP8 数据边界 → ReferenceCodes → Settings → ArtifactRevision → EntitlementQuota/Workflow → Notification → TaskJob → ImportExport → FileMedia → DataPermission → Kernel Identity/Tenant/RBAC。每张卡已列精确文件、消费者、表 owner、旧路径退出、既有命令、输入、停止点和资源边界。Core 含 Integration 的 PHPUnit 命令必须显式设置 `PEANUT_INTEGRATION=1` 并提供登记的 DB_HOST/端口/凭据引用；缺少登记环境应阻塞，测试 skip 不计通过；纯 Unit 命令无需该标志。当前没有领域专用 TP8 检查的地方明确记录为缺口，只能在后续获批卡中新增。

45–68个工程日实现加10–17个工程日聚焦检查，只是依据当前文件、消费者、表 owner 和现有检查规模给出的未校准人力规划参考；它不是实测AI吞吐、AI需要的天数或本任务日历承诺，首个实施批次后必须重估。资源租约、真实 Provider、下游消费、独立模块发布和部署等待单列；不再用另一个总小时数替代已撤回的150小时结论。Provider、双 Edition 和消费者质量仍是后续固定候选的既定验收目标，只有媒体spike采用与独立模块发布属于条件性选择。S5-T01及其有界补正已获根验收，当前仅待审定精确公开候选，不重派阶段3、4。

## 阶段2有效决定与历史经验（2026-09-10）

阶段2已完成对登记草稿的最低充分验收：10项有效决定、14条经验、11条业务知识和29项问题均有来源、证据等级或明确限制、关系与后续去向。决定以直接用户纠正优先，现行ADR/项目规则用于范围约束；经验中的根因推断已与历史观察分开，解决办法只作为阶段3现状/遗漏核查、阶段4规则及阶段5修复输入。

反复出错主要收敛为四类：目标改变后旧PDO/Repository/Factory抽象继续扩张；大范围机械替换与减负误伤Tenant、生命周期、Provider等真实边界；为绿灯使用不失败的ablation、skip+exit(0)或把局部smoke概括成完整验收；以及把源码、制品、部署、模块、实例和下游消费授权拼成一个“完成”。可复用的解决办法是先固定可恢复现场和来源身份，再按真实消费者分领域原子替换；保留真实框架/业务/安全断言；对必需输入失败闭环并让负向控制真正失败；按对象和阶段分别固定commit/tree、锁、制品、部署和授权回执。

上述方案是已确认的方向和后续核查输入，不是本阶段已实施的新硬门禁；阶段2当时没有核验当前源码、依赖锁、资格、部署和厂商支持；阶段3后的现状以本报告前部及原登记为准。旧“逐字段完整性”流程不升级为永久规则，“每次重试都必须新授权”也不作为现行普遍要求；本阶段只保留对批准范围、失败原因和直接副作用的聚焦核对。

## 阶段1归并与验收结果（2026-09-10）

阶段1已完成本次批准的已有材料归并及必要补正。初稿只给原有24项补了分类，仍漏掉独立关注项，且历史 verified 容易误读为当前通过。现保留24个原ID、补入4项，共28项待核验关注项；不是28个已确认现存缺陷。

| 新增ID | 补入内容 | 尚不能断言 |
| --- | --- | --- |
| CAP01-001 | WorkflowGraph 同名声明与类加载/崩溃诊断的历史证据缺口 | 当前仍有致命错误、OOM已找到根因 |
| UX-VERIFY-001 | 四入口/剩余功能、文章选项500、设置保存与视觉验收 | 局部页面smoke等于全功能通过 |
| DCS-CONSUMPTION-001 | Peanut固定候选、锁、资格、独立评审与下游消费授权 | 旧Host模板等于可采用；DCS内部业务属于本轮 |
| DEPLOYMENT-001 | 源码发布、多租户候选部署、旧Standalone生产与认证浏览器资格 | 已发布就等于两个生产环境均完成 |

主登记已移除重复的逐ID分类层，将分类、关联项和检查入口归入唯一问题列表；明确区分 historical_status、historical_ruling 与 current_status。有效要求、来源限制和后续核验项分开；验证命令是待执行条件，不是通过回执。另补充原生框架能力复用、构建/测试回执不可拼接、授权来源与拒绝写操作的后续结果。

归并后的边界如下：Application PDO 绑定、领域持久化、Provider 组合和 Bootstrap 是同一 Runtime 证据簇中的不同问题，不能因相邻而合并；测试完整性、Ablation 假阳性和 fixture fail-closed 是不同失败模式；Storage 采用、媒体隔离试验和分支清理分别是采用事实、能力主张和操作权限问题。真正持续有效的方向仅包括 ThinkPHP 8 同源 Application/Core、保留跨 Module 合同/ExecutionContext/Tenant-RBAC/生命周期/SDK-Transport-Storage Driver，以及产品/ Core /双 Edition 同号、Module/Instance 独立编号。框架中立持久化重构方向已被纠正，不能作为执行授权。

Rich Text独立发布是条件性议题，不预设用户尚未决定，更不能挡住bundled正确性核查。厂商资格须先核对现行资源登记及已有证据，不能从历史缺证直接推断现在缺少账号。Q01/D05失败、测试失真、Runtime残留及历史修复主张仍待后续当前核验。

早期Luna报告的管理员原子命令、Core测试入口、Vite路径、短信预占和Tenant settings等停止线，在后来的Gemini报告§10已有历史完成主张。本轮已关联后续材料，不按早期未完成列表重复派发；历史完成也不代替当前核验。

## 阶段1–2历史交付范围

阶段1交付范围是核对原登记/报告、已有conversation-audit摘要、Luna/Gemini审计、12组拒绝操作处置，以及保存的R09验收摘要；其输入去向登记于 stage1_consolidation.inputs。阶段2在此基础上复用五组既有提取，对 `stage2_analysis.source_receipts` 所列来源完成决定、经验、知识与问题关系核对；没有重读原始聊天或复活PREP10/UNKNOWN-READY旧队列。

没有重扫原始聊天、读取当前业务源码、执行运行时测试或修复代码。历史缺源/密文/投影继续限制对应主张，不为补齐阅读数量扩大任务。完成的是批准材料的有界归并与补正，不宣称全历史或全项目问题已无遗漏、已修复。下一阶段须明确确认。

阶段1验证：既有 docs-governance check、git diff --check 通过；28个ID唯一且关联无悬空；两个既有PHP改动的SHA保持不变。阶段2本次验证：29个问题ID唯一，10项决定、14条经验、11条知识及其引用关系有效；`stage2_analysis.source_receipts` 已覆盖五组既有提取；方案实际SHA与 execution-state.approved_plan.formal_sha256 一致；本轮 docs-governance check、JSON检查和 git diff --check 通过。Luna/low完成阶段2登记语义复核。本次文件仍在本地工作树，未提交、合并、推送或发布。

## 来源与实际读取等级

既有 [任务索引](fact-audit-2026-09-09/source-index.json)记录 1115 个任务、1117 个物理位置，796 relevant、306 excluded、13 trace-only；335 篇文档是路径/分类清单。它们是有效的历史覆盖快照，不是 1115 条聊天或 335 篇文档的逐项事实审计。本轮不否定索引价值，也不提升其证据等级。

以下是此前只读代理读取本地 JSONL 消息投影及相关命令/失败记录的历史范围，不是本次新增原始来源阅读。行号是 rollout 物理行号，不是对话 turn 编号；未把解析、关键词命中或终态摘要称为全源码语义审核。需要追溯具体主张时使用已保存回执。

| 完整任务 ID | 标题/定位 | 实际重点范围 |
| --- | --- | --- |
| `01a056b5-49cd-7c93-bace-2fb40e3fbdc3` | Peanut Admin｜企业级脚手架质量与交付能力总控 | 用户纠正行 3814、3827、3877、3899、6506、6703；终态/交接 3795、3808、6410、6697、6863 |
| `01a05a08-72a9-73b1-b136-e5de7b367cc6` | 恢复 WP0 架构合同与资格盘点 | 消息投影、终态行 12861 |
| `01a05de6-deb6-7313-bf29-9c8db9e54a4d` | 落地应用层架构规范化重构 | 消息投影、终态行 9781 |
| `01a06068-4a8e-7991-be2f-b95c7c5ad0c9` | 执行架构重构报告修复 | 用户行 399、935、1205、1612；失败/修复 1166–1271、1446–1563；终态 1607 |
| `01a0610a-aa47-7fe0-9645-48ac581ee61a` | 分析项目整体骨架 | 失败/修复 13238–13369、13622、13736、13949–14118、14636–14817；终态 14850 |
| `01a062d3-8e78-7da3-a505-51bbcaa3d885` | Rich Text 任务；真实标题待精确回执 | 实质消息/终态投影；行范围待补，不声称全文核验 |
| `01a0776b-15cf-7b63-896d-0b4b7a858f7f` | Storage/Core 边界任务；真实标题待精确回执 | 实质消息/里程碑投影；行范围待补 |
| `01a07fd6-296d-7cf2-8434-77c57925fc14` | 审计 Peanut Admin 核心重构 | 发布/quarantine/拒绝动作相关消息与命令；精确拒绝调用见 rejected-dispositions，不代表整个长任务已语义复核 |
| `01a08410-39e7-70c2-ab9d-6b17b1d13e46` | Core ThinkPHP 原生化方向文档 | 首次终态与本轮只读自审 delta；最初错误提出 Core 0.2 人工选择已被后续同号版本决定替代 |
| `01a0846d-55c1-7643-8f28-fae30ba3404d` | Peanut Admin 全量文档治理与版本体系校准 | 本轮两份覆盖/资格 delta 及 Storage 反证更正；没有再次读取其全部原始历史 |

Gemini handover、用户 pasted audit、历史 quarantine 与 12 组 [拒绝操作处置](fact-audit-2026-09-09/rejected-dispositions.json)只作来源。原操作未执行、超时未知和后来独立成功必须分别保留，不能回填为原调用成功。

## 历史固定统计与已提交修改

此前源码读取切点：Application `ab96727c8b07da64489fe152b36e483f055dcf0c`；Core `2ed77f38ca26472d685cfeb81674a66ba23eadb4`。历史 ADR 切点保留自己的源码身份；表内结果不回填为历史测试证据。

Application 使用 `git ls-tree -r --name-only <commit> server/app` 的 729 个受管 PHP 文件；逐文件读取 `git show <commit>:<path>`，文件命中计一次，调用表达式按出现次数。无测试/vendor/生成快照。对比 `ea9bc3a1` 与 `ab96727c`：

| 指标 | 统计表达式/范围 | 前 / 后 |
| --- | --- | --- |
| PDO 类型/单词文件 | `\bPDO\b` | 87 / 87 |
| PDO 或 Pdo 子串文件 | `PDO\|Pdo` | 97 / 97 |
| 所有实例 make 调用 | `->make\s*\(`，包括业务工厂 | 223 / 213 |
| 显式命名容器 make | 接收者限定 `$app / $this->app / $container / $this->container` | 202 / 192 |
| ModuleProvider make | 仅 10 个 ModuleProvider | 105 / 95 |
| ModuleProvider 绑定 | 55 个；直接类映射 / 闭包 | 5 / 50 → 21 / 34 |

首批 `8c92adc41e173959336f3c62476746864bb610b6` 修改 6 个无参数绑定及 Member 四个辅助方法，**没有减少 make 调用**。第二批 `ab96727c8b07da64489fe152b36e483f055dcf0c` 修改 10 个纯别名绑定，减少 10 次 make。总共涉及 Article、Member、OAuth、Notification、Payment 五个 Provider；不是四个。两批只证明局部源码变化，不证明 Runtime 全量迁移。

Core 生产包 `packages/php/*/src` 中 `PDO` 单词与 `PDO|Pdo` 子串分别为 60 和 82 个文件，二者并不矛盾。宿主生产集合 `backend/app + starter/backend/src` 与整个 `backend + starter`（含测试）也必须分开，不能把后者的 106/89 标为生产统计。此处不提供新的复算结论。

## 既有跨类别发现及证据限制

- Storage 的 `563df8c4` 是 `origin/dev` 和 v3.0.14 祖先，锁文件与 Packagist 记录 Alpha.13 split `61f40dc2412338b4dfdcf7d2cd7514da45ea773a`。采用队列仍写“待合入”是过时投影；另一个自审任务的相同错误已在直接反证后撤回。真实云账号资格仍独立欠缺。
- 高容量媒体 `e915bea7` 是隔离试验，不在主线，不是已发布 Storage Driver 或正式 Rich Text 集成。
- `official.rich-text` 的 bundled 源码、未签名本地包候选、独立 published 状态分开；缺失专项浏览器证据不能因“是否独立发布待选”而一并搁置。
- 此前审计记录 ExecutionContextAccess、Notification join 与 Payment grant 归属问题有当时源码闭环；不是当前全 Tenant/RBAC/事务路径动态资格的证明。
- `check-test-integrity` 排除 fixture、跳过大于 1024 字节文件，使用模式检测。它只拦截已知短 stub，不能证明所有 early exit、skip、伪控制流和 CI 均可信。
- 此前审计发现 `ci-server-check.sh --full` 调用的三个 Ablation 脚本没有失败断言，却输出源码未支持的安全/性能/人体工学结论。另有升级 domain-probe 缺失必需参数时以 skipped+exit(0) 短路；此前工作树已改为 stderr+exit(2)，当时无参数执行得到 2。其有参域检查未改动，未宣称完整升级资格通过。本次保留该未提交修改，不重新执行或验收修复。
- v3.0.14 通过当时 P0-E 合同；Release 快照明确保留前端依赖 advisory 限制，不声称漏洞清零。UniApp 已接受风险、到期复核与真实审计通过分开。
- Core 两次 Q01 供应链失败，具体子命令未知。保留日志的脚本修复不等于根因已定位，更不是第三次资格通过。

## 下一步边界

已确认的ThinkPHP方向不重新表决；历史缺陷是否仍存在、Q01失败原因和厂商资格须依据对应证据核查。已有结论不授权自动修复、重跑资格、合并或发布。当前任务完成后停止，由用户确认下一步明确范围。
