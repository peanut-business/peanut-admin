# Peanut Admin 历史决策、规则与产品闭环方案

Document ID: `pa-convergence-plan-20260910`

Control revision: `PA-USER-STEP-GATES-20260910`

Status: `current`（阶段0–4已回收验收；阶段5首批S5-T01本地实现及有界验证已根验收，正准备精确公开候选；尚未集成或进入产品资格）

Updated: 2026-09-10（Asia/Shanghai）

Owner: 根任务 `01a07fd6-296d-7cf2-8434-77c57925fc14` 持有总Goal、阶段确认及最终回收权；已批准的独立执行任务拥有该步骤的唯一写集。

## 1. 总目标与本次授权

在当前明确批准的产品支持范围内，将 Peanut Admin Application/Core 收敛为真实可用、可交付的同源 Standalone/Multi-tenant 企业消费级脚手架，并把反复错误的原因落实为可核验的防复发措施。

这包含三个成果：有效决定、历史经验和知识被正确采用；当前产品遗留得到实际处置；规则、代码、测试、文档与真实交付一致。不是读完所有聊天、把29项标成完成或写完报告。

阶段3静态审计/排程与阶段4规则/文档原位修正均已由根任务回收验收，不是运行时修复或产品资格。S5-T01已确认、实施并完成有界补正，且本地实现及有界验证已获根验收；当前仅准备精确公开候选和后续 Git 集成决定，不由文档验收推定所有代码迁移、发布或部署获批。

已确认方向不重新表决：Application/Core正式采用ThinkPHP 8，Core不追求框架中立；保留真实跨Module合同、ExecutionContext、Tenant/RBAC/Module生命周期及厂商SDK/Transport/Storage Driver。Application/Core/双Edition产品版本一致；Module与Instance独立，实例另记来源产品版本。

## 2. 阶段与完成标准

阶段0–2不重开、不重新编号；后续把原“规则与修复”分为两个明确阶段，最终验收为阶段6。

| 阶段 | 目标与范围 | 必须交付什么 | 当前状态 |
| --- | --- | --- | --- |
| 0 执行控制 | 保留目标、逐步确认、去除旧调度 | 唯一方案/检查点；不启用Goal自动续作；无定时器 | 已完成，入口持续同步 |
| 1 归并已有材料 | 收集已有聊天分析中的独有问题与纠正 | 原登记与来源去向，不重扫原始聊天 | 已完成 |
| 2 决定、经验与知识 | 分清有效要求、旧方向、原因推断和可用方案 | 已收敛10决定、14经验、11知识、29待核验事项 | 已完成，不等于当前产品通过 |
| 3 查现状、补遗漏、排修复 | 以目标/决定/经验及能力账本核两仓；29项不是上限 | 需求覆盖、当前证据、残留处置、可直接批准的修复批次、依赖与时间估算 | 已补正并经根任务验收；仅静态审计/排程 |
| 4 落实防复发规则 | 将已确认原因转为最小有效约束；复用现有规则/检查 | 旧规则移除，新要求落到实际入口与核验办法；说明已生效、仍待实现和不能硬拦截的边界 | 已完成并经根验收；不代表Runtime或压缩Hook通过 |
| 5 分批实际修复 | 按真实领域和依赖修代码、测试及文档 | 每批实现/调用者/旧路径同步处理，最低充分验证、同步登记及批准的集成交付 | 逐批确认 |
| 6 独立验收与交付 | 验证产品与防复发措施，不接受报告代替运行 | 固定候选、同源双Edition、真实消费路径、支持范围内厂商资格及交付证据 | 前置完成后单独确认 |

每个步骤的方案必须有目标、输入、范围/排除项、owner、模型/推理、写集/资源、依赖、检查、产物和停止点。按领域或交付物划定有意义的步骤，不把单个命令或文件拆成反复请示；批准范围内低风险可逆细节自主处理。批准阶段方向不等于批准所有未知后续步骤。

## 3. 历史知识如何继续收敛

先用已有材料与明确用户决定，按“产品目标/需求—有效决定—历史教训/知识—当前实现—验证”建立去向，写进原问题登记和现有报告，不再建并行账本。

覆盖不仅查现有29项，还需核对架构/数据边界、Tenant身份权限与生命周期、模块/厂商/媒体、各消费入口与用户功能、安装升级和版本、测试/制品/部署，以及文档/规则/任务恢复是否有整类遗漏。具体支持范围以现行批准事实源为准，不把竞品、外部原型或未来商业计划自动纳入。

已有历史中出现独有遗漏，补到原登记；影响具体判断的缺口，才定向回到该决定及后续纠正片段。未读、缺源、密文和摘要限制必须保留，不默认为已吸收或无价值；也不为了“来源齐全”穷尽字段。原历史切点 `2026-09-09T10:55:07.188Z` 保持，本审计自产日志不递归纳入历史全集。

用户最新明确纠正优先；模型建议、压缩摘要、历史“已批准”不能代替原始授权。旧方案按真实消费者和最新目标取用，不整套回放。已失效的现行口径直接移除；原始消息、独有故障/测试证据保留来源，历史结果不冒充当前验证。

阶段1、2完成仅证明批准材料的有界整理，不保证所有聊天知识绝无遗漏。最终完成依据可追溯的已批准目标覆盖和真实产品验收，而非阅读数量。

## 4. 阶段3必须形成可执行结果

每项要求/问题应具备：来源与适用范围；当前结论（已解决、仍存在、失效/不适用或证据不足）；准确仓库/提交/文件或回执；处置与影响范围；owner和依赖；验收办法；需要同步的规则/文档；是否涉及用户选择或外部条件。不能只写“之后再查”。

“证据不足”不是已解决，也不自动升级为缺陷。必须说明缺什么、影响哪项结论、最小解除办法和具体owner。已解决项用当前适用证据退出修复队列，不重复施工。重要新发现经范围和证据裁定后进入同一清单，不无限派发新主题。

阶段3同时给出规则修订项和阶段5修复批次：优先级、关键路径、可并行组、互斥写集/资源、对应已有检查、退出条件及估算区间/依据/置信度。正式修复所需的资源/授权缺口提前列明。阶段3之前不承诺整体完成日期，不用文档整理耗时推算大规模迁移耗时。

阶段3默认读代码/文档/锁及已有回执，允许批准方案内的非变更静态诊断；不修改Runtime/SQL/前端/业务测试，不默认运行完整资格或使用生产资源。读取实际资源状态必须先遵守项目资源登记及该步骤授权。

## 5. 规则落地、修复与独立验收

阶段4优先修正既有AGENTS、执行规则、领域合同、文档入口及现有检查，不为每条教训创建新Skill或脚本。只有可反复复用、现有入口不能承载的方法才提出Skill。每条防复发措施写明触发点、约束、owner、检查方法及覆盖限制；不能把“已记录”称为硬拦截生效。依赖Runtime变更的检查与相应阶段5批次绑定，不声称提前通过。

阶段5按领域原子替换，保留真正业务语义，同时处理调用者/Provider/导出/旧路径及文档。不得全库正则重构、长期双实现/兼容桥/双写、PASSED占位或削弱有效安全/业务断言。错误测试合同按真实语义正式修正，脚本本身不自动判错。只阻塞依赖失败输入的交付，独立且已批准工作可并行。

每批使用与风险匹配的最低充分检查；失败先聚焦诊断，不用全套资格反复调试，不重跑内容未受影响且允许复用的成功组。阶段6再执行固定候选资格；失败退回受影响修复及后续必要组，保留其他有效证据。

阶段6按实际批准合同证明安装/升级/关键业务入口、Tenant/权限/事务/并发、同源双Edition和厂商支持；产品/Core/Edition身份一致，Module/Instance分别记录；源码、制品、部署和下游消费状态分开。已确认阻塞必须闭环；允许保留的窄风险须有依据、owner、期限及影响，不能用于豁免必须满足的安全/隔离条件。最后独立抽核高风险修复及其防复发措施。通过批准交付后才标记总目标完成。

## 6. 执行组织、速度与成本

本对话负责总目标、方案确认、依赖取舍和最终回收；具体阶段采用用户要求的独立执行任务，一个步骤只有一个正式登记writer。本轮新建阶段3独立任务；其后每个新阶段也新建任务，当前阶段必要补正复用原任务。

已完成的阶段2执行任务为 `01a08a72-c3a3-78c2-a07e-3af221c46987`，Luna/low，已停止；不重派其五组提取。后续优先复用已有成果、共享来源只读一次，按不重叠范围并行。子任务状态分待领取、执行中、待验收、需补正、已完成；回收后滚动补位，不等独立整批，也不为填满容量制造工作。上限20且服从运行时实际容量；不自行递归派发、不共同修改账本。

模型与推理分别选最低充分档位：清晰扫描/机械核对优先Luna/low，常规综合按需Luna/medium或Terra/medium，复杂跨模块诊断/关键取舍才升级；升级依据和范围简短登记，不全量逐级重读。主线程不能自改型号时如实说明，不声称已经降档。成本控制不能以遗漏知识或虚报阅读换取。

以已核实/已修复/已验收的具体交付推进，不以任务数、token数、文档页数或反复汇报作为进度。

## 7. Goal、恢复和权限

总目标正文保留于本方案；根任务的本机恢复检查点仍在原位置，但属于未跟踪私有控制，不是公开仓库文档或普通 clone 前置。本次最新正式Goal工具读回null，未清除/重建Goal；不依赖自动Goal启动阶段，不恢复周期监督。Goal表达长期目标；本次授权与十分钟确认仅按§7.1，不独立放行后续动作。根任务恢复时仍须读取该原本机检查点，但不得把其内容或路径投影到公开文档。

续作、压缩或新消息后先读本方案、step_approval、当前owner/结果/恢复点，再核对实际在途状态。只有当前步骤明确获批才继续；未知最后操作先只读查结果，不重复副作用。用户重启、子任务回报、旧消息和Goal续作均不批准下一步。旧撤销侧栏任务和历史字段队列不恢复，不新增监督任务。恢复入口仍为主工作区 `.local/peanut-admin-supervision.json` 的版本化路径引用。

### 7.1 最新阶段确认与十分钟默认确认

用户2026-09-10明确同意开始按阶段推进：每个新阶段使用新独立任务；根任务回收验收，不合格由原任务按原编号补正，通过后先描述下一阶段的完整执行任务。阶段3的§10/§14任务书本轮已明确批准，不等待十分钟。

后续常规阶段/批次可由用户显式确认，或在根任务已送达完整任务书后满十分钟无反对按本次预授权确认。文中“确认/获批”均包含符合本节条件的超时确认，不再沿用“一律无限等待显式确认”的旧口径。默认确认只覆盖已展示的具体范围，不授权尚未写明的下一阶段。

根任务在检查点保存拟启动阶段/批次ID、方案版本及SHA、用户可见消息定位、送达时间、截止时间、前置验收结果、排除项及唯一消费状态。用户要求暂停、修改、取消或提出影响该方案的问题时立即使该窗口失效；修改范围/版本后重新送达并重新计时，不能沿用旧期限。前置失败、必需业务选择未决、资源/候选身份未知，或真实资金、破坏性生产数据、共享Git历史重写等需单独核对授权的动作，不靠静默超时放行。

仅根任务可在完整方案送达时使用正式产品提醒机制设置一次十分钟到期唤醒；到期先只读核验新消息、方案SHA、前置及是否已派发，再消费一次确认。若工具仅支持周期提醒，使用有次数/截止的单次规则并核实成功，完成/失效即移除；不得转成持续监督或恢复旧定时器。工具不可用、客户端离线或无法可靠核对窗口时登记限制，恢复后核对，不宣称准点自动启动。

阶段owner只完成本阶段和批准范围内必要补正。最终保存结果/验收命令/未完项/限制及恢复步骤后，通过任务消息回报根任务，停止等待回收；根任务收到完成消息只进入验收，不跳到下一阶段。根任务回收通过后提出下一任务书并启动上述窗口，不能由子任务自批阶段。不启用Goal自动续作，旧自动续作/提醒/报告/重启不是独立授权。

## 8. 本次交付与持续同步

现有方案、登记、报告、README、document-registry、document-impact-map、current入口及知识笔记原位更新；不新增总计划/报告副本，不改写历史资格或完成证据。公开站点只在公开合同/说明实际变化且对应步骤批准时同步，内部调度与历史私有信息不投影出去。

阶段3已按§10.4回收，当前证据和映射、74个候选路径、29条命令的cwd/输入、批次引用、旧路径退出归属及估算限制已补正验收。阶段4实际规则diff及S4-CORR-01..03补正也已验收；两仓文档检查与无历史入口核对只证明对应文档范围，不证明产品测试或所有工具强制执行。S5-T01及其CORR-01..03的当前公开结果在原登记 `stage5_t01_execution` 维护，已获根验收；本机检查点只保留恢复/授权细节，精确 Git 候选仍须根任务审定，不重派已完成核查。

## 9. 独立任务的执行合同（阶段3–6派发依据）

本节及下文限定阶段的执行合同。阶段0–2只引用已有验收，不重新派发；阶段3本轮已获明确批准，实际executor以检查点为准；阶段4–6的工作方式、产物和停止线在此确定，实际问题ID、文件、资源和命令参数必须由前一阶段结果填实后再批准，不冒称现在已经知道全部补丁。

### 9.1 唯一入口与角色

| 角色 | 责任 | 不得做什么 |
| --- | --- | --- |
| 本根任务 | 持有唯一总Goal；给用户解释方案、取得确认；任命阶段owner；处理跨范围取舍与最终回收 | 不与阶段owner并行写同一登记；不因子任务完成自行启动下一阶段 |
| 独立阶段任务 | 本阶段唯一调度者/正式登记writer；消费批准清单，回收、去重、分析、核验及同步文档 | 不创建另一个总Goal、定时监督或新阶段；不得把“已派发”记为完成 |
| 子智能体 | 处理一个编号和明确范围，返回证据、结论、限制及结果位置 | 不递归委派、不改共同账本、不自行扩大问题或领取后续队列 |
| 复核者 | 在批准范围内独立检查高风险判断或实际修复；给出接受/需补正及依据 | 不全量逐级重读；不直接覆盖实施者文件 |
| 用户 | 确认每个有意义步骤；裁定确实需要业务/支持范围选择的事项 | 不要求用户为每条命令或已授权低风险细节重复确认 |

阶段owner未正式接手前由根任务维护检查点；接手后根任务不并行登记，回收时再交回。独立阶段任务是一个新对话，内部子任务用智能体，不为每个小项再建侧栏任务。所有未来owner角色在获批派发时绑定真实task/agent ID，本表不是已创建任务清单。

### 9.2 派发包必须包含的内容

一个任务无需继承整段聊天，也必须只凭以下信息可执行：

1. 唯一ID、该阶段目标、批准消息定位及停止点；本方案路径及对应章节。
2. 实际仓库/worktree、分支、完整HEAD及相关未提交差异身份；读集、精确写集、排除目录。
3. 原问题/决定/经验/知识ID及必需事实源；来源之间的时间和权威关系、已验收结果及缺口。
4. 具体操作顺序、依赖任务ID、是否可并行、共享文件/运行资源owner。
5. 模型与推理分别选择、升级条件；预计工作量和信息不足时的处理。
6. 每个产物的原文件路径、登记字段、结论需要的证据、既有检查命令和通过条件。
7. 消息插入/压缩后的恢复位置；最后完成动作、未完成编号、待验收结果、下一个安全动作。
8. 明确禁止未授权修复、资源/发布动作及新阶段；完成后回报并停止。

缺字段时只补该字段，不重新制定总方案。任务状态固定为待领取、执行中、待验收、需补正、已完成；证据不足属于问题结论，不等于任务仍在运行。需补正沿用原ID，只补失败范围。

### 9.3 并行、回收与成本

阶段3先由owner完成一次边界/基线登记，再并行独立领域；可滚动回收和补位，但所有领域核查及统一分析完成前不启动阶段4/5。阶段4–6只消费各自批准队列，依赖不满足不派。上限20子智能体并服从实际可用容量；根据有效回收速度、返工和待验收积压增减，不为用满20制造任务。

每个来源/写集指定唯一首读owner，共享结论以文件/符号/证据引用交接，不复制原始日志。相同来源的必要跨领域核对不等于禁止复核，但不得让多个模型整份重读。窄机械项优先Luna/low，普通语义工作Luna/medium或Terra/medium；证据冲突/安全架构难点才聚焦升级Sol或GPT-6，推理单独选择，不默认max。阶段负责人不是自动使用最高型号的理由。

阶段3首轮Terra/medium，综合不足后仅补正范围升级Sol/medium，最终命令输入窄补正降回Luna/medium；这不是下一阶段固定档位。阶段4按§11.1用Luna/medium。实际派发前核对可用模型/推理；角色锁定等级不合适时不用该预设，不全量高价重读。

## 10. 阶段3任务书：查现状、补遗漏、排修复

### 10.1 进入条件、基线和输入

进入条件：用户明确批准本节；检查点绑定独立阶段3owner及批准写集。建议阶段负责人Terra/medium，机械检索用Luna/low，跨模块代码语义用Terra/medium；最终困难裁定交根任务，不为所有报告再开高等级复审。

源码读入口：

- Application主仓：`/Users/xing/Documents/company-projects/peanut-admin`；本次累计整改工作树/文档写入口：`/Users/xing/.codex/worktrees/80f6/peanut-admin`。
- Core主仓：`/Users/xing/Documents/company-projects/peanut-admin-core`。先读该仓AGENTS及适用规则，不能把Application规则替代Core规则。
- 当前文档工作树在本次细化时为`feat/audit-evidence-and-provider-repair@ab96727c8b07da64489fe152b36e483f055dcf0c`，Core本地HEAD读回为`2ed77f38ca26472d685cfeb81674a66ba23eadb4`；这只是定位快照，不是阶段3应盲目采用的最新集成基线。
- S3-00必须分别核对两仓本地/远端dev、当前worktree和未提交差异。批准后允许只读远端引用查询，必要的普通fetch只更新远端跟踪引用；禁止pull、checkout覆盖、merge、reset或清理。远端不可达就标明本地快照限制，不冒称最新。所有并行结论绑定同一份明确源码身份；其他任务导致源码变化时只失效受影响结论，不全库重扫。
- 已有两个PHP脏文件及精确SHA见检查点`handover.preserved_runtime_sha256`；阶段3只分析其diff，不覆盖、不自动提交。

共享输入先由owner按需读取并分发定位：

| 输入 | 原事实源（相对文档工作树） | 用途 |
| --- | --- | --- |
| 问题、决定、原因与知识 | `docs/maintenance/runtime-convergence-issue-register-2026-09-09.json`，含`stage2_analysis` | 29项、10决定、14经验、11知识及既有来源关系 |
| 历史和后续纠正 | `docs/maintenance/conversation-audit-2026-09-09.md`、`docs/maintenance/runtime-convergence-audit-2026-09-09.md`、登记中直接引用的Luna/Gemini报告 | 复用独有结论；矛盾/缺口才定向找原记录，不恢复旧全集队列 |
| 已接受边界 | `docs/architecture/core-thinkphp-runtime-direction-adr.md`、`product-version-identity-adr.md`、`module-publication-contract.md`及其直接合同 | 先确认目标，再核实现；文档里的历史实现状态不自动验真 |
| 产品支持与进度 | `docs/product-status/README.md`、`capability-ledger.json`、适用Release/Deployment快照及消费合同 | 防止只查29项而漏掉整类用户目标；不把历史通过当当前通过 |
| 服务、资源与文档owner | `resources/service-registry.json`、`resources/project-resources.json`、`docs/document-registry.json`、`docs/document-impact-map.json` | 限定数据/模块边界、待运行检查和同步去向；本阶段不连接运行资源 |

需要调用/影响关系时按各仓规则使用已有CodeGraph。Application先`scripts/project-codegraph status`并按登记`ensure`，仅允许本地派生索引；无能力/失败则说明限制，使用精确源码检索，不启动跨仓索引建设任务。

### 10.2 有限队列和逐项工作

以下是提议的任务编号及分工，不是已经获批的待领取队列。S3-00完成后S3-01至09可按明确范围并行；owner依源大小拆分时保留父ID并登记不重叠子范围，不新增主题。S3-10等待上述结果及必要补正。

| ID / 首选执行配置 | 负责什么、从哪里开始 | 必须回答/验收点 |
| --- | --- | --- |
| S3-00 / 阶段owner Terra/medium | 仓库身份、现有规则/登记、已完成任务和读写owner；建立批准目标覆盖条目 | 明确检查的是哪个dev/工作树/差异；共享来源只指派一次；29项及各决定/经验/知识均有负责领域 |
| S3-01 / Luna/low，语义分歧按需升级 | 已有历史提取与产品目标映射；AUDIT-001、HISTORY-001和阶段2来源 | 每项独有用户要求/知识是已映射、失效有依据或缺源；遗漏补原登记，不能把没读来源判为无价值 |
| S3-02 / Terra/medium | Application AppService、ModuleProvider、服务与数据/事务/入口；RUNTIME-APP-001/002/003、BOOTSTRAP-001、MEMBER-001 | 逐调用链判断冗余而非按名称删；列真实PDO/Factory/make路径、可原子替换边界及已有脏修复；CLI/HTTP/Worker/Cron差异明确 |
| S3-03 / Terra/medium | Core生产包、public exports、reference host、bootstrap与事务；RUNTIME-CORE-001/002 | 公共PDO API、Repository/Commands/Queries/Factory逐类保留或迁移依据；真实消费者、数据owner及Application依赖交接，不能用框架中立解释新目标 |
| S3-04 / Terra/medium；关键冲突聚焦升级 | Tenant/身份/RBAC/Module生命周期/ExecutionContext；TENANT-001及02/03提供的敏感链路 | 找出隔离、finally清理、事务/并发、Standalone/Multi-tenant差异与fail-closed风险；列需要动态证明的条件，不凭静态阅读宣称安全通过 |
| S3-05 / Terra/medium；清单核对Luna/low | 模块/资源声明、Rich Text、FileMedia/Storage及厂商；MODULE-001、MEDIA-001、STORAGE-001、PROVIDER-001、RICHTEXT-001 | bundled/package/published各自状态；媒体spike有哪些能力、合入/未合入及实际影响；厂商已有支持与缺失资格分别登记；不顺手启用spike或发布模块 |
| S3-06 / Terra/medium；机械入口检索Luna/low | 现有测试脚本、fixture、CI/资格定义、依赖锁和许可产物；VERIFY-001至005 | 区分测试失真、正常跳过、错误合同和缺资源；每个结论定位断言/退出码/真实调用；既有检查是否覆盖全部适用包；旧文档的版本/场景计数不能替代当前runner/fixture合同 |
| S3-07 / Luna/medium，冲突按需Terra | 版本元数据/锁、Git祖先关系、590e6183、发布和部署回执；VERSION-001、RELEASE-001、BRANCH-001/002、DEPLOYMENT-001 | 解释每个相关修改去向和剩余影响；Application/Core/双Edition与Module/Instance身份分开；本地、已合入、已发布、已部署不混用；不得执行清理或发布 |
| S3-08 / Terra/medium | 能力账本对应后端、web/platform/pc/uniapp入口及已有UI/下游证据；CAP01-001、UX-VERIFY-001、DCS-CONSUMPTION-001 | 工作流诊断、文章/富文本相关消费链和四入口有哪些真功能/缺验证；外部消费合同只查Peanut责任，不读改DCS内部规划；浏览器动态缺口列后续，不启动完整浏览器矩阵 |
| S3-09 / Luna/medium，冲突按需Terra | 现行AGENTS/规则/Skills引用、current文档、拒绝修改处置与知识入口；DOC-001及各域提交的矛盾 | 哪些已接受决定没落地、错误规则仍指向旧架构、修改未成功、文档已过期；逐条给原位修改/移除/合并/必要创建及校验办法，暂不改业务规则 |
| S3-10 / 阶段owner综合，根任务关键裁定 | 去重、当前结论、修复依赖、规则变更与交付范围 | 全部问题/需求有处置；产出可批准批次和估算；决定不一致有明确裁定或用户选择，不再只交“建议继续审计” |

02与03分别负责两仓内部，04只复核敏感语义；05负责模块/存储实现，08负责消费流程；06负责检查是否可信，07负责资格/发布证据是否匹配身份。交叉发现交给已登记owner，不重复扫描。源范围无法确定时先让owner收敛，不将整个仓库交给多个智能体。

### 10.3 原位产物及问题判定

不新建第二套审计报告。阶段3获批后由唯一writer：

- 在原问题登记补`stage3_assessment`，保存基线、目标/知识覆盖关系、当前问题结论、规则修订输入、修复批次及限制；问题沿用ID，独有新问题才新增ID。
- 每项至少记录：来源ID及当前仓库/commit/diff/文件或符号；事实/推断/未知；当前结论及理由；影响范围和风险；处置owner/依赖；已有验证是否适用、仍缺什么；候选写集/文档去向/最小检查及退出条件。
- 问题结论为“当前证据支持已解决”“仍存在”“不适用/旧方向已退出”“证据不足”。静态实现一致与动态资格通过分别记录；没有运行测试不得填“测试通过”。
- 目标/知识覆盖条目可以指向同一个问题或已核实实现，不强行为每条经验造缺陷。所有目标类别有去向或明确适用排除；不能用“29项全填完”替代覆盖检查。
- 在原人类报告更新“当前剩余什么、为什么、按什么顺序解决、需用户决定什么”四部分；在本方案阶段5批次清单填实输入，而不是再建总计划。
- 检查点保存子任务ID、状态、结果定位、精确续读点和未完成项。子任务结果优先直接回传；确有长结果才用现有证据目录的唯一ID文件，正式登记只留摘要/引用。
- README、current入口、registry、impact-map按实际影响同步；历史报告正文不重写为当前资格，公开站与业务规则本阶段不修改。

阶段3写集只包括上述原登记、原报告、本方案、执行检查点及必要文档索引/当前进度入口；知识入口仅保存已确认结论。不得修改PHP、SQL、运行时前端、业务测试/fixture、依赖锁、业务规则或资源配置，不提交整条混有代码的分支。

### 10.4 验收、估算与停止

阶段owner对每份回传做来源定位、范围、状态和关系核对；当前“已解决”需适用证据，高风险/冲突结论做独立聚焦复核。根任务验收综合结果及关键取舍，不再全量重读所有域。

完成条件：

1. 29项全部有当前判定；10决定/14经验/11知识及已批准能力/需求有对应去向，遗漏增补有来源，不扩大外部产品范围。
2. 所有“仍存在”有可执行修复批次；“证据不足”有最小补证动作、owner及仅影响哪项交付；已解决项退出修复队列而不是重复派发。
3. 每个批次列问题ID、目标行为、精确候选写集/数据owner、前置依赖、可并行/互斥资源、拟用模型/推理、已有检查完整命令及输入要求、文档同步和停止点。未知文件/命令由本阶段源码核查后填实，不能留下“跑相关测试”作为可派发版本。
4. 给出按批次的乐观/通常/保守工作时长和依据，区分人工批准/外部资源等待、模型执行与测试耗时；整体时间按关键路径估算，不把并行时长相加，不在信息不足时承诺日期。
5. 原登记引用与ID无悬空，`./scripts/docs-governance check`、`git diff --check`通过；运行时和历史证据未改。静态检查不代替产品资格。

到此回报、停等阶段4方案确认，不开始修复。若有安全/架构判断缺证，只停止对应结论/下游；可完成其他独立只读领域。不得为赶时间豁免必需目标或伪造补证。

## 11. 阶段4任务书：规则与防复发措施落地

进入条件：阶段3统一分析已回收，用户批准具体规则写集；不只凭某个领域先回报就修改全局规则。建议owner Luna/medium处理已经裁定的文档/入口；复杂规则冲突用Terra/medium，关键取舍由根任务裁定。

输入：阶段3规则修订项、有效决定/原因证据、各仓AGENTS/执行规则、对应领域合同、已存在的检查入口，以及计划中的修复批次。无需再读全部历史。

| ID | 实际动作 | 产物与验收 |
| --- | --- | --- |
| S4-01 | 对每条规则核对最新决定、适用仓/层/入口；原位修正错误目标，移除失效现行口径与无价值重复说明 | 原登记的规则处置映射；正确业务/安全语义保留；核心TP8、版本、模块发布、厂商和真实测试原则不再相互冲突 |
| S4-02 | 区分能立即生效的流程检查与依赖代码迁移的检查；复用现有检查和入口，不为每条规则建Skill | 每条都有触发点、owner、可拒绝的错误、检查命令/人工复核方法及限制；依赖阶段5的自动检查挂到具体批次，不提前阻断尚未迁移的全库 |
| S4-03 | 同步AGENTS入口、影响文档、registry/impact-map、相关知识笔记和必要公开说明 | 所有直接受影响入口一致；公开内容无私有历史/资源/凭据；站点源码同步和线上部署分开登记 |
| S4-04 | 用不继承整段聊天的独立只读复核者，从入口和检查点判断允许动作、已完成项及下一步 | 回答与当前授权一致；规则引用可恢复，不能重派阶段0–3或启动未授权修复；文档检查通过后回收 |

写集只能是用户确认的具体规则/合同/文档和既有检查文件。新的Skill、测试、fixture或脚本若确有必要，必须在本阶段方案中单列用途和授权；未经批准不新增。修改检查不会自动授权产品代码迁移。

防复发记录使用“文本已落实 / 人工检查可用 / 自动检查已验证 / 依赖S5批次 / 工具不支持硬拦截”区分，不以“写进AGENTS”声称所有工具或真实上下文压缩已经强制拦截。S4-04只证明新的无历史入口读取可恢复规则；真实压缩Hook没有可验证机制时明确限制，不造Hook或定时器。

退出条件：每个已确认规则缺口有实际改动或绑定具体S5批次的阻塞/依赖，没有互相打架的现行指令；一次最低充分检查通过，更新原登记及计划后停等。阶段5先提交首个修复批次，不自动全库施工。

### 11.1 本次具体派发包：S4-RULES-CORRECTION

Proposal ID：`PA-S4-PROPOSAL-20260910-01`。状态：已由 `01a08b02-5bc1-7851-82a9-aaf1e5fa1c14` 完成规则修正及S4-CORR-01..03补正，并经根任务验收。原确认窗口SHA及送达/消费证据保留于检查点，不重新消费。本节保留已执行任务合同，不是新的派发入口。目标：原位修正已经定位的现行规则，使新任务按入口能正确施工，不进行源码迁移或重新发现整库问题。

**责任及成本**：新建独立任务“阶段4：规则落地与防复发”，Luna/medium为唯一正式writer。现有规则语义出现证据冲突时只将该片段交Terra/medium；不整体升级。可将Application/Core两组窄规则对照交Luna/low只读并行，最终由owner修改；收尾另用不继承聊天的Luna/low做S4-04入口恢复核验。无强制填满并发；子智能体不递归派发、不写共同账本。根任务仅回收，不并行写owner文件。

**输入顺序**：先读本方案§7.1/9/11.1与检查点step_approval，核真实executor/写集；再读两仓AGENTS、适用执行规则和stage3_assessment.rule_change_inputs、S4候选卡、相关stage2决定/经验引用；最后只读已定位冲突句及直接关联当前合同。10决定/14经验/11知识不是要求重做提取；不重开阶段0–3或原始聊天全集。最新直接用户纠正高于旧模型建议；历史审计和固定Release回执不改写成现行通过证据。

**实际顺序与验收**：

1. S4-01核对并修正四组已裁定口径：①Core正式TP8，不再以framework-neutral/repository transaction ports/replacement adapter为长期目标；ModuleProvider稳定依赖用接口到实现类，配置/SDK/回调/可变Worker才用闭包与make；保留ExecutionContext、Tenant/RBAC/Module生命周期及SDK/Transport/Storage。②产品/Core/双Edition同号，Module与Instance独立且实例记录来源；不回填旧Alpha/Release；Rich Text bundled不等于独立发布，媒体spike不等于已支持厂商驱动。③禁止PASSED、为绿灯弱化有效断言或把必需组skip计通过，但错误测试合同允许按已确认业务语义正式修正；脚本生成本身不是错误证据。④当前授权/唯一writer/同任务补正/新阶段新任务/消息与压缩恢复/最低充分模型等级一致，不恢复旧周期监督或全量重读。
2. S4-02为这些规则在既有入口标明触发、owner、检查办法和局限。Core直接运行含Integration的PHPUnit必须保留PEANUT_INTEGRATION=1及登记环境前置；App --fast只覆盖绑定CI_BASE_REF的已提交差异，不证明dirty补丁通过；pattern扫描不等于运行语义验收。现有检查没有覆盖的规则绑定S5-T01或相应C批次，不在本阶段新增检查脚本或把尚未迁移的全库设为已通过。
3. S4-03只修改下列写集中的实际冲突与对应索引；正确内容无需为展示工作而改写。原问题登记增补stage4_rule_application，逐项记录原问题/决定/经验ID、实际文件、检查方式、效果等级和S5依赖；原报告记录可读结论；不另建总账/报告副本。本阶段不改变公开产品合同，无需将内部调度投影公开站点，更不部署站点。
4. S4-04待owner停止规则编辑后，由无历史只读复核者仅从入口/方案/检查点恢复当前阶段、已完成项、允许写集、禁止动作、遇到插入消息后的恢复点及下一步Gate；不实际触发压缩Hook。矛盾只退回原ID补正；通过后保存文档检查和diff回执、停写，用send_message_to_thread向根任务回报结果路径和限制。阶段5必须另行批准首批任务书。

**精确写集及基线**：Application正式文档根仍为`/Users/xing/.codex/worktrees/80f6/peanut-admin`（阶段3基线`feat/audit-evidence-and-provider-repair@ab96727c8b07da64489fe152b36e483f055dcf0c`）；新任务自身cwd只是执行上下文，不另复制事实源。Core写根为`/Users/xing/Documents/company-projects/peanut-admin-core`（阶段3基线`dev@2ed77f38ca26472d685cfeb81674a66ba23eadb4`）。接手先核HEAD/status及允许路径的现有diff，记录任务前差异；新冲突仅停止相关文件，不覆盖他人修改，不创建额外代码工作树。

- Application规则：`AGENTS.md`、`AGENT_EXECUTION_RULES.md`、`docs/governance/current-state.md`、`docs/architecture/core-thinkphp-runtime-direction-adr.md`、`docs/plans/storage-driver-extraction-queue.md`。
- Application同步/回执：本方案、`docs/maintenance/runtime-convergence-issue-register-2026-09-09.json`、`docs/maintenance/runtime-convergence-audit-2026-09-09.md`、`docs/maintenance/conversation-audit-2026-09-09.md`、`docs/README.md`、`docs/document-registry.json`、`docs/document-impact-map.json`及仅由既有生成器更新的`docs/reference/document-catalog.generated.md`。根任务私有检查点不属于公开同步/回执写集。产品版本ADR和模块发布合同已正确，作为只读事实源，不重复改写。
- Core规则：`docs/decisions/dependencies/p0-dependencies.json`只改framework/ORM的现行架构描述，保留版本、许可、安全评审及稳定ID；`docs/decisions/dependencies/p1-cap04-collaboration.md`把旧PDO描述明确为待迁移实现而非长期公共边界，保留CRDT/SDK/Transport合同。其配对JSON已核无同类框架中立要求，保持只读；不把旧依赖评审日期冒充本次升级日期。
- Core同步（仅实际受影响时）：`docs/architecture/index.md`、`docs/README.md`、`docs/content-status.json`、`docs/document-impact-map.json`及仅生成器更新的`docs/reference/document-catalog.generated.md`。
- 跨入口：`/Users/xing/Documents/company-projects/peanut-admin/AGENTS.md`仅“本次闭环任务的逐步确认入口”；其余全局规则不改。知识库仅原`10 项目/Peanut Admin.md`及`30 决策/Peanut Admin Core ThinkPHP 运行时收敛.md`；按使用说明及Obsidian技能原位修正，不增加备份报告。

**禁止项**：不改PHP、SQL、前端Runtime、测试/fixture、依赖锁、资源登记或检查脚本，不新增Skill/Hook；不调用数据库/容器/Provider，不发起资格/浏览器矩阵、代码清理、发布部署。不提交、合并或推送当前累计脏分支；本阶段交付为可核验的本地文档diff，后续只在明确的集成批次处理已验收写集，不能因日常Git默认规则合入夹带的未审代码。两个受保护PHP的SHA必须与检查点一致。

**最低充分验证**：在80f6根执行一次`./scripts/docs-governance check`和`git diff --check`；在Core根执行一次`./scripts/core-docs-governance check`和`git diff --check`。登记/索引确需生成时先运行同工具的generate，再统一check。对修改JSON解析、实际写集/任务前diff及两处PHP SHA做静态读回；知识笔记用Obsidian CLI读回。失败只做一次聚焦诊断与失败组重跑，再失败报告具体阻塞。无需产品测试或运行资源。

**恢复及停止**：消息插入或压缩后只读方案/检查点的当前S4-01..04状态、结果位置和未完成项，不重派完成项；暂停指令先安全保存。完成回报根任务`01a07fd6-296d-7cf2-8434-77c57925fc14`后停写、不自批S5。派发时先绑定真实thread ID/项目/写根/方案SHA，收到接手回执再记执行中，避免“创建了但未交接”。所需工作量尚无AI实做校准，只回报实做进度与阻塞，不承诺根据人力工程日推算的模型运行时。

## 12. 阶段5任务书：按领域实际修复

### 12.1 批次生成与顺序

进入条件：阶段3修复清单及阶段4相关规则已验收，用户确认这一批的实际行为、文件、资源和验证。每批一个实施owner；常规明确补丁Luna/medium，跨模块实现Terra/medium，安全/事务/公共API难点才聚焦升级。不能因为“整个项目重要”让全部机械步骤默认高档。

具体S5批次由S3-10填实；以下是不可丢失的顺序约束，不是提前生成的缺陷清单：

1. 先正式修正会使后续验证失真的测试/fixture/退出码和直接前置；区分正常条件跳过、错误测试合同及假通过，保留有效断言。没有当前问题证据的历史脚本不重复修。
2. TP8主线严格沿ADR：ModuleProvider简化 → Core ThinkPHP数据边界 → ReferenceCodes → Settings → ArtifactRevision → EntitlementQuota/Workflow → Notification → TaskJob → ImportExport → FileMedia → DataPermission → Kernel Identity/Tenant/RBAC。
3. 版本/公开API影响在删除前确定；Core/Application/双Edition同号，不重新采用独立Core `0.2.0-alpha.1`。实际产品号码、预发布和Registry/锁的先后依据当时事实及批准发布动作决定，不复用失败候选或修改旧不可变包。
4. 与迁移无直接依赖的UI、打包、文档和窄缺陷可组成并行批次；涉及同一契约/数据owner/文件或运行资源时串行。媒体spike、厂商支持、Rich Text bundled及独立发布不能混成一项。
5. 公共数据API与消费者必须在批准的跨仓兼容批次中闭合；Core包验证/发布身份与Application采用锁有真实先后，不用伪Registry或长期兼容桥绕过。未发布开发验证与正式已发布状态分开。

### 12.1.1 阶段3候选批次（已通过静态审计与排程验收）

阶段3已把根验收指出的占位、路径/引用、命令输入、领域依赖和无依据工期原位补正并通过根验收；阶段4也已完成并验收。阶段5仍按具体任务书逐批确认，不把本排程或静态文档检查通过当作施工授权。

以下是阶段3基于 Application `origin/dev@ab96727c8b07da64489fe152b36e483f055dcf0c` 和 Core `origin/dev@2ed77f38ca26472d685cfeb81674a66ba23eadb4` 的排程输入，不是对阶段5的预授权。完整逐项映射在 `runtime-convergence-issue-register-2026-09-09.json#stage3_assessment.repair_batches`。

| 顺序/批次 | 目标与精确写集摘要 | 既有检查、输入与停止点 | 未校准人力参考 |
| --- | --- | --- | --- |
| `S4-RULES-CORRECTION` | 先修本仓 AGENTS/执行规则/current-state/Storage 队列/TP8 ADR，以及 Core `p0-dependencies.json`、`p1-cap04-collaboration.md` 的当前冲突 | 两仓根目录执行 docs governance/diff；无运行资源；规则一致即停等根验收 | 1–2日 / 0.25日（未校准人力参考） |
| `S5-T01` | Member Provider、domain-probe、integrity/CI、三份 Ablation；Core WorkflowGraph默认只读与现有Unit核验 | 精确范围/命令/正向probe限制见§12.3；不以全量 `test-unit` 冒充聚焦检查 | 首批实做校准；原2–3日/0.5–1日仅人力参考 |
| `S5-C01` | 九个 Official ModuleProvider 与 `AppService.php`；简化稳定别名，保留配置/运输/worker/payment 真实 factory | Application `server/` 执行 PluginModule/OfficialArticle 现有测试；`--fast` 仅在提交后绑定准确 `CI_BASE_REF` 与 backend env，且只覆盖 `base...HEAD` | 1–2日 / 0.5日（未校准人力参考） |
| `S5-C02` | Core `Persistence/TransactionManager.php`、`PdoTransactionManager.php`、`PdoRepository.php`、`backend/app/command/KernelBootstrapFactory.php`；Application AppService 与 `common/persistence/CoreTenantRepositoryFactory.php` | 只建立TP8数据/事务宿主边界并迁首个原子消费者；不得提前删除C03–C12拥有的领域Repository或Identity/Tenant/RBAC API；执行Kernel事务聚焦测试和Application TP8行为矩阵 | 5–8日 / 1–2日（未校准人力参考） |
| `S5-C03..C05` | ReferenceCodes → Settings → ArtifactRevision；精确 package/backend/starter 路径、消费者、表见主登记 | 各包现有 PHPUnit 目录与对应 backend test；DB测试绑定 `DB_HOST=127.0.0.1`、登记的 `MYSQL_PORT=DB_PORT`/凭据引用；不反复运行全工作区脚本 | 7–10日 / 1.5–3日（未校准人力参考） |
| `S5-C06..C08` | EntitlementQuota/Workflow → Notification → TaskJob；连同 Notification/Task Application Provider | 各包聚焦 PHPUnit；Notification/TaskJob 使用各自现有 feature/MySQL harness 及登记输入，它们不属于 Core `test-integration` 覆盖 | 13–19日 / 3–5日（未校准人力参考） |
| `S5-C09..C10` | ImportExport → FileMedia；替换私有 FileMedia reach-in，保留 `563df8c4` 已采用的 Storage Driver 合同 | ImportExport 使用现有 feature harness，FileMedia 使用包/backend PHPUnit 与 Application FileMediaHost；需登记 DB，且不采用媒体 spike | 7–11日 / 2–3日（未校准人力参考） |
| `S5-C11..C12` | DataPermission → Kernel Identity/Tenant/RBAC；C12在所有消费者退出后才删除剩余 Kernel PDO/重复 bootstrap | DataPermission/Kernel包聚焦 PHPUnit及Application RBAC/Tenant/Edition测试；全组 runner 只留给S6固定候选 | 11–16日 / 3–5日（未校准人力参考） |

完整卡片位于主登记 `stage3_assessment.repair_batches`，逐卡给出文件、消费者、表 owner、旧路径退出、依赖、绝对 cwd、命令、输入、停止点和模型档位。Core 含 Integration 的 PHPUnit 命令必须显式设置 `PEANUT_INTEGRATION=1`，并绑定登记的 DB_HOST/端口/凭据引用；缺少登记环境即阻塞，skip 不计通过；纯 Unit 命令无需该标志。45–68个工程日实现加10–17个工程日聚焦检查只是不经AI实做校准的人力规划参考，不是AI运行时或本任务日历承诺；首个实施批次后必须重估，不再给替代总小时数。外部资源、Provider、下游、发布与部署等待另计。Provider、双 Edition 与消费者质量仍是既定目标，分别进入S6-03/S6-04/S6-05；只有媒体spike采用和独立模块发布保持条件性选择。

### 12.2 每批固定操作

- **准备**：重读获批任务卡，确认两仓基线、dirty写集、调用者和表owner；取得测试资源登记/健康/新鲜度和唯一租约。未登记就停依赖资源的检查，不猜地址。
- **实现**：采用该领域最终设计；同时处理实现、调用者、Provider、公共导出、bootstrap、旧路径退出及必要Schema migration。不全库正则、不双写、不新增镜像Repository，不覆盖其他人的改动。
- **测试合同**：若测试与已确认真实合同冲突，同批正式修正错误断言和入口；若缺少必要测试，先在批次方案中明确，不私自扩充测试体系。禁止skip/PASSED和删有效断言换通过。
- **验证**：在Development mode运行任务卡已列出的最小检查。一般失败只做一次聚焦诊断、一次失败组重跑；再次失败回报真实阻塞或申请修复门禁，不用全套P0-E寻找下一个错误。
- **登记**：原问题登记记录实际diff/提交、检查和未检查项，能力账本/规则/文档随事实更新；“本地完成、已集成、qualified、已发布、已部署”分开。
- **交付**：批准批次包含集成时，按两仓规则把精确变更合入并推送登记dev；稳定分支/发布保留人工Gate。不整条合并带未审定代码的历史分支，不使用整仓暂存。
- **停止**：该批验收完成就回报，下一批未批准不领取。已经批准且无依赖冲突的并行批次可继续。

每批退出须证明：目标行为正确；直接消费者同步；旧实现及导出确实退出；敏感语义保留；检查真实且适用；文档和登记一致。剩余旧路径若属于未来不同领域要有明确归属，不把半迁移的同一领域声明完成。

Schema回滚不等于简单回退Git。批次方案必须写备份/恢复及不可逆影响；发生真实资金、破坏性生产数据或共享历史操作前再次核对具体授权。用户总体“最终结果”不替代保留的逐步确认。

### 12.3 首批具体任务书：S5-T01 测试可信性与两处保留修复

Proposal ID：`PA-S5-T01-PROPOSAL-20260910-01`。状态：已确认，独立任务 `01a08b36-5b92-7e51-a4e2-42741e855a9c` 已完成本地修复及CORR-01..03有界补正，并已获根验收；当前只准备精确公开候选，Git 提交/推送仍须根任务核准。原确认窗口与唯一消费状态仍按本机检查点保留，不重新计时或重开窗口。前置：阶段3、4已根验收，无需重做历史阅读或规则整理。目标是让后续检查不再假通过，并对两处已有PHP差异给出真实、有限的验证；不是全产品修复完成。

**负责人及交接**：批准后新建一个独立阶段5任务，Luna/medium实施；仅在现行断言与业务/框架语义出现明确冲突时，把该片段交Terra/medium复核。根任务负责验收和后续批准。可并行处理T01-A的测试/扫描器、T01-B的两处保留修复及Core只读Unit核验；最多20子智能体、服从实际容量，不为七个文件凑并发，不递归派发/另建侧栏复核任务。正式登记只有阶段owner一位writer。

**根与基线**：代码与正式文档写根均为`/Users/xing/.codex/worktrees/80f6/peanut-admin`，当前本地`dev`与该树HEAD同为`ab96727c8b07da64489fe152b36e483f055dcf0c`；Core只读检查根为`/Users/xing/Documents/company-projects/peanut-admin-core`，HEAD为`2ed77f38ca26472d685cfeb81674a66ba23eadb4`。这是派发输入快照，接手已读回HEAD/status、登记实际差异。新任务自身cwd为`/Users/xing/.codex/worktrees/1bae/peanut-admin`，仅为执行上下文，不复制另一份计划或共用依赖目录。80f6此前文档差异与两处PHP改动都保留；两个PHP的原SHA从检查点读取。真实task ID/项目/写根/方案SHA已绑定并完成START交接，现由该任务接管写权。

**输入顺序**：当前方案§7.1/§9/本节 → step_approval与受保护SHA → 两仓各自AGENTS/适用规则/资源登记 → 原问题登记的S5-T01卡与VERIFY相关证据 → 下列精确实现及直接调用处。复用已验收10决定/14经验/11知识；不重扫源码或聊天。新的未定缺陷只登记其实际影响，不扩大本批。

**代码写集只有七个既有文件**（相对80f6）：

- `server/app/Modules/Official/Member/ModuleProvider.php`
- `server/tests/fixtures/core-upgrade-compatibility/domain-probe.php`
- `scripts/check-test-integrity`
- `scripts/ci-server-check.sh`
- `server/tests/Ablation/DataIsolationAblationTest.php`
- `server/tests/Ablation/LazyDiPerformanceAblationTest.php`
- `server/tests/Ablation/ErgonomicsAblationTest.php`

Core的`packages/php/workflow/src/Definition/WorkflowGraph.php`与`packages/php/workflow/tests/Unit/Definition/WorkflowGraphTest.php`默认均只读：当前“重复声明”历史诊断已被静态证据推翻，先运行现有Unit，不为制造修复而改源。若确实失败，聚焦判定真实合同；需改Core时回报准确失败与所需写集，不顺手扩入Workflow迁移。

**具体实施与判定**：

1. **T01-A测试真实化**：DataIsolation只保留并验证合成模型中tenant-bound join拒绝跨租户grant、ID-only对照会泄漏的事实；不能称其为真实SQL/TenantScope资格。LazyDI去掉stdClass/计时循环推导的DB/中间件/性能结论，改为当前Registry可确定验证的行为检查，不新增计时阈值。Ergonomics移除硬编码legacy层数/行数/百分比和不实“已消除_context”结论，只验证当前Reflection/源码支持的有限结构。三者均须显式断言并能非零失败，且具有对应负向控制；保留十组有效Runtime合同及原安全语义。这是正式修正错误测试合同，不是删除失败检查。
2. **T01-A扫描与接线**：修复scanner的`>1024 bytes`漏扫及其声称覆盖范围；识别明确的无失败路径/占位，不声称静态词法检查能证明一切断言有意义。修正CI fast选择器遗漏`check-test-integrity`的接线；只选择相关聚焦检查，不以`--full`反复调试。本批明确允许在这几个既有检查内补足必要失败/负向控制；不新增永久测试脚本、fixture目录或另一套测试体系。
3. **T01-B保留修复验收**：核实Member新增import指向现有实现合同、autoload/Reflection可用；不重写ModuleProvider。domain-probe无参数必须stderr并退出2；保留有参正常路径，不将缺输入记为skip成功。其正向路径实际需要已生成且Composer已安装的应用根，不直接需要DB；完整调用属于combined-upgrade资格，本批不运行、不宣称已通过。
4. **T01-B Core验证**：仅运行现有WorkflowGraph Unit，一次验证当前类的合法声明及既有行为；通过后沿CAP01原ID回填动态证据，不开始C06或Kernel重构。
5. **T01-C收口**：原登记/报告记录每个实际diff、检查退出码、覆盖边界、未完成正向probe及后续S6去向。移除本批已失效的现行结论，不新建报告；至少区分“静态/内存检查已修”“真实业务动态资格尚未执行”。根任务验收后原任务只做必要补正/本批收口，不自行领取C01。

**资源与最小检查**：本批不使用DB、缓存、端口、容器或Provider，无共享运行资源租约。仅使用App登记`peanut-admin-host-php-development`和Core登记`peanut-admin-core-php83-alpha12-qualification`的宿主PHP及本树既有vendor；按登记确认实际版本/路径。缺依赖或版本不符只停止依赖检查，不擅自升级lock、复制vendor或改环境。下表各项在改完后执行一次；失败一次聚焦诊断及失败组重跑，再失败回报。

| cwd | 命令/检查 | 必须记录的结果和限制 |
| --- | --- | --- |
| 80f6根 | `/opt/homebrew/bin/php -l server/app/Modules/Official/Member/ModuleProvider.php` | 退出0，仅语法 |
| 80f6根 | `/opt/homebrew/bin/php -r 'require "server/vendor/autoload.php"; $c="app\\Modules\\Official\\Member\\Application\\MemberAdministrationService"; $i="app\\Modules\\Official\\Member\\Contracts\\MemberAdministration"; if (!class_exists($c) || !is_a($c,$i,true)) exit(1); (new ReflectionClass($c))->getConstructor() ?: exit(1);'` | 退出0，证明autoload、实现合同和构造签名可反射；不证明容器/DB装配 |
| 80f6根 | `/opt/homebrew/bin/php server/tests/fixtures/core-upgrade-compatibility/domain-probe.php` | 无参数退出**2**且无skip成功；这是预期负向结果，不按shell失败误判 |
| 80f6根 | `/opt/homebrew/bin/php scripts/check-test-integrity` | 退出0只表示有界模式检查；在既有入口内验证明确占位/大于1KB坏样本被拒绝、正常有效检查未误杀，不提交临时样本 |
| 80f6根 | `/opt/homebrew/bin/php server/tests/Ablation/DataIsolationAblationTest.php`；其余两份Ablation各单独调用同一PHP | 各退出0，负向控制必须被断言捕获；不声明DB/性能资格，不用`&&`掩盖各自结果 |
| 80f6根 | `bash -n scripts/ci-server-check.sh`，核CI fast改动选择器及其实际选出的scoped组 | 退出0；`--fast`只在提交后绑定真实`CI_BASE_REF`和登记所需环境时运行，不证明dirty变更已覆盖 |
| Core根 | `/opt/homebrew/opt/php@8.3/bin/php vendor/bin/phpunit packages/php/workflow/tests/Unit/Definition/WorkflowGraphTest.php` | 退出0且实际执行既有Unit；不运行全量test-unit、不需DB/PEANUT_INTEGRATION |
| 80f6根 | `./scripts/docs-governance check`与`git diff --check`，修改JSON解析与方案SHA核对 | 文档闭合；不重复Core已通过且未改动的文档组 |

**文档写集**：原问题登记`stage3_assessment.repair_batches`中的T01及对应issue当前证据/后续处置、原runtime审计报告、当前方案/检查点、`docs/README.md`、`docs/governance/current-state.md`；registry/impact-map/生成catalog仅在实际引用受影响时同步。知识库只更新既有项目与Core TP8笔记。不得把原29项整批置为已解决；VERIFY扫描器/真实Runtime等不同覆盖保留区别。

**Git交付边界**：本批先交真实本地diff、失败可见检查及根验收所需证据，不在根验收前提交、合并或推送累计脏分支。根验收后由同一任务按根任务核实的精确已验收代码/文档写集完成集成收口；这属于本批后半程，不新开一个历史审计阶段。若累计文档尚不能安全分离，明确列出具体集成缺口，不能全分支合并或遗漏必要文档同步。没有集成回执不得称T01已交付完成。禁止重写历史、删除隔离区或清理其他任务工作树。

**排除与停止点**：不开始S5-C01及后续TP8迁移，不删除PDO公共API，不改SQL/前端/依赖锁/版本/资源，不发布、部署、真实Provider、浏览器矩阵或完整combined/P0-E资格。没有当前失败证据不重修旧WorkflowGraph。完成后回报根任务的真实task ID、diff、命令退出码、未检查项和精确恢复点，停止等根验收；仅用户确认或§7.1有效窗口能批准下一批。首批记录实做耗时/返工/等待用于重新估算，不承诺未校准的整体完成时间。

## 13. 阶段6任务书：独立验收与交付

进入条件：已批准必须交付的修复批次完成并集成，阻塞产品缺陷已闭环；用户确认验收候选、支持范围、资源、完整检查矩阵和发布/部署范围。建议独立owner Terra/medium，身份/回执核对Luna/low，身份隔离/安全高风险复核按需升级；复核者不以自己先前修复的总结代替证据。

| ID | 工作 | 必需结果/停止点 |
| --- | --- | --- |
| S6-01 | 对照阶段3目标/知识覆盖、原问题登记和实际修复，独立检查高风险及反复出现的错误；核验文档中的“完成”主张 | 所有必须项有当前证据；不是重扫全量聊天/源码。发现残留退回原ID/S5批次，不重新开历史阅读阶段 |
| S6-02 | 明确候选模式；按现行consumer-ready/P0-E合同核源commit/tree、Core与双Edition版本、依赖/库存/模块锁、制品和环境 | 干净候选、身份一致、无阻塞；旧文档版本/组数若与实际合同冲突，先修正确事实源，不盲目运行旧门禁 |
| S6-03 | 取得登记资源/租约并执行固定候选资格及支持范围内额外必要检查 | 同源双Edition安装/适用升级、Module生命周期、Tenant/权限/事务/并发、四入口关键业务、厂商真实操作均有对应证据；任何既有P0-E未覆盖项不能冒称已覆盖 |
| S6-04 | 将实际修复与防复发措施逐项回扣，核制品许可/依赖风险、文档与消费入口，并回收本任务资源 | 失败/接受风险/外部阻塞分别列明；已释放资源和需保留证据有精确owner/路径，不清理他人的数据 |
| S6-05 | 仅执行本步骤明确批准的发布、文档站同步、目标部署和消费者交付 | 发布的正是通过资格的身份；源码/包/站点/部署分别验证；未授权或未完成部分明确保留，不标总目标完成 |

验收来源为本仓`docs/operations/consumer-ready-control.md`、`docs/release-engineering.md`、`docs/p0e-runtime-qualification.md`、`resources/project-resources.json`、`resources/p0e-runtime-qualification.json`及**当时实际runner/fixture**。Core读取其自身发布/测试入口。阶段3/5须将这些入口中的陈旧说明与真实合同冲突正式解决，不能把当前文件存在等同于适用于新版本。

候选执行使用已存在入口：`scripts/consumer-ready-control preflight`各对应phase、`scripts/p0e-runtime-qualification plan/claim/run`及现有Edition打包/发布脚本。**本计划不填假run_id、地址、候选SHA或签名参数**；S6-02按登记生成并经批准绑定后才可执行，不能复制历史示例值。准备检查ready不等于qualified。

固定候选期间不改Runtime。候选/锁/测试可信性变化就失效旧候选，回Development修受影响问题，再新建身份和资格；未改候选且合同允许的环境恢复才同参数resume。旧成功组仅在资格合同明确允许复用时复用，不能把旧候选资格继承给新代码。按release-candidate-control技能区分调试与资格，不反复全矩阵试错。

总目标完成须同时具备：已批准业务目标的当前覆盖；已确认阻塞闭环；真实产品资格；防复发措施的证据和限制；用户已批准交付实际完成。客户生产、独立模块发布、完整SaaS等若不在批准范围，不自行纳入；若确属必须交付但受外部条件阻塞，则报告部分完成而非修改目标躲过。

## 14. 可直接交给阶段3独立任务的指令

以下指令在用户确认阶段3后由根任务发送。派发前只补真实executor ID/执行工作树及批准定位，不复制整段历史，不创建新Goal。阶段4–6同样使用§9派发包，把前一阶段已填实的具体批次附上；未填实则不可派发。

> 你负责Peanut Admin收敛计划的阶段3：查清当前残留和遗漏，形成可执行修复计划；不负责修复或发布。你是本阶段唯一调度者与正式登记writer，不是新的总目标owner。
>
> 先读取 /Users/xing/.codex/worktrees/80f6/peanut-admin/AGENTS.md、适用AGENT_EXECUTION_RULES.md、docs/plans/history-rules-product-convergence-plan-2026-09-10.md 的§9–10及执行检查点step_approval。核对根任务已经批准S3，executor ID/写集正确；否则只回报缺失授权并停止。不启用Goal自动续作；仅根任务在下一阶段方案送达后管理§7.1确认窗口。
>
> 复用阶段0–2已验收成果及原问题登记中的29项、10决定、14经验、11知识。29项不是范围上限；对照批准产品目标/需求和能力账本核整类遗漏，只对会影响判断的历史缺口定向补证。不得复活旧全集阅读队列，不重复派发已完成S2提取。
>
> 按§10先执行S3-00，核Application主仓、80f6累计工作树及Core仓的真实基线、未提交差异与各自规则，再按S3-01至09做有界并行；源码身份和来源owner要一致。最低充分模型/推理分别选，窄任务Luna/low，复杂语义Terra/medium，困难片段按证据升级。只你可在批准清单内派子智能体，子智能体不得递归派发、改共同账本或扩范围；最多20并服从实际容量。
>
> 所有结果回收到原登记stage3_assessment及原人类报告；事实、推断、未知分开，当前已解决/仍存在/不适用/证据不足必须附来源和影响，不据历史verified声称当前通过。回收可滚动，统一分析未完成不能开始改规则或修代码。
>
> 完成S3-10，交付需求/知识覆盖、当前问题处置、规则修订项、可直接批准的修复批次、依赖/并行和资源边界、精确写集与既有检查命令、工期区间/依据及需用户选择项。按§10.4做最低充分验收，更新原文档及恢复状态；不另建总报告。
>
> 本阶段禁止修改PHP/SQL/运行时前端/测试fixture/依赖锁/业务规则，禁止连接或变更运行资源、完整资格、merge/push/发布/部署及代码清理。CodeGraph仅按项目规则做必要本地索引，源缺口登记局部限制。
>
> 中断或压缩后先读检查点，核实际运行者和最后操作，复用已完成结果、不盲目重派。结尾用人类可读语言说明当前真实残留、处理顺序、估算及需确认项，回报根任务后停止；不得进入阶段4或自动继续。

阶段3已明确批准，按检查点绑定的唯一owner执行；阶段4–6须先回收前置并填实任务书，再按§7.1确认。新任务不得凭总体目标自批下一阶段。
