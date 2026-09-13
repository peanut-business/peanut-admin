# Peanut Admin 产品收敛计划

Document ID: `pa-convergence-plan-20260910`

Status: `current`

Owner: `product-architecture`

本计划只定义已接受的目标、剩余交付范围及依赖，不保存当前执行owner、授权副本、失败预算或恢复命令。
最新用户决定及当前执行状态按[执行规范](../../AGENT_EXECUTION_RULES.md#rule-priority)加载；完成度回到[能力账本](../product-status/capability-ledger.json)和[问题主登记](../maintenance/runtime-convergence-issue-register-2026-09-09.json)。
失效阶段任务书与逐批确认文本已移除，原始决定及独有证据由Git历史或私有不可变证据索引追溯，不恢复执行。

## 1. 已接受目标

在当前支持范围内，将Application/Core收敛为同源Standalone/Multi-tenant消费级脚手架：有效决定落实、已确认缺陷闭环、规则与源码/资格/交付一致。
Application/Core采用ThinkPHP 8目标，应用使用原生Model/Scope构造注入；保留真实跨Module合同、ExecutionContext、Tenant/RBAC/Module生命周期及厂商SDK/Transport/Storage Driver。
产品/Application/Core PHP/Web/双Edition必须同号，Module/Instance独立；具体身份以[版本ADR](../architecture/product-version-identity-adr.md)及实际版本输入为准。
不重开已验收历史审计，不把29项或任何旧计数当范围上限。只对影响当前决定的缺口定向补证，禁止重新穷尽全部聊天。
消费前必须项优先于内部架构队列；C02–C12的非消费阻塞部分保持原问题ID后置，不标完成、不自动扩大范围。

## 2. 范围与真实停止条件

既定完整计划的有效授权只从唯一私有状态及其决定来源读取；已授权范围内的正常实施及预算内低风险补正无需重复逐批确认，批次仍由唯一控制owner回收并报告。
计划授权、运行就绪、失败预算和人工Gate独立；本计划不解除已经触发的停止线，不创建Goal、确认唤醒、定时器或周期监督。
范围以已接受任务合同与问题主登记为准。未填实的写集、输入或资源不得直接派发；技术合同补齐不另建材料批次。
候选身份、资格可信性、安全/Tenant/权限、数据或客户ownership、资源越界、dev→main PR及最终发布/不可逆操作保留人工Gate。
规则整理不批准产品重跑或发布；具体恢复条件只从唯一私有状态取得。

## 3. 剩余交付与依赖

| 交付 | 合同与完成条件 |
| --- | --- |
| Development输入/adapter与环境收口 | 按`consumer_delivery`及相关ENV问题ID补齐实际生成物、参数、受控环境、锁摘要及调用边界；同一owner实施到最低充分验证。输入启动失败不冒称产品断言失败，已耗预算不因换批次重置 |
| CR03固定候选 | 阻塞闭环；head/tree、manifests/locks、inventory/scaffold及同号Core/双Edition一致；依[消费控制](../operations/consumer-ready-control.md)与[发布工程](../release-engineering.md)取得具体固定身份批准 |
| CR03固定P0-E | 同一冻结身份执行[八组fresh资格](../p0e-runtime-qualification.md)；旧候选成功保留为旧证据，变更后按原资格合同判定失效和必需重跑，不继承旧qualified |
| CR03真实旧实例升级与数据恢复 | 两Edition正式旧Release安装包及固定archive/manifest摘要建立独立合成实例；review adoption plan→精确摘要/共享Host路径确认→metadata-only adopt→签名包preflight/apply/verify→真实依赖及迁移→业务数据读回→失败恢复 |
| CR03四端与厂商 | 下表逐端保留实际页面/API、写后重读及截图/trace；真实厂商操作单列，不以配置成功冒称外部资格 |
| CR03收口 | 回扣TENANT-001/PROVIDER-001/UX-VERIFY-001/DCS-CONSUMPTION-001/VERIFY-004及已确认范围；脱敏固定证据、剩余风险、实际清理/保留责任明确；独立验收后才交CR04 |
| CR04正式交付 | 仅发布通过相应资格并获批准的确切源码/包/站点/部署身份；App Release与两Edition附件一致，Core先发布不代表产品完成；未获批准或未完成项保持明确状态 |

正式旧源的版本、commit和四资产摘要从`consumer_delivery.cr02.handoff.formal_v3014_edition_assets`及不可变Release取得；不能用当前create-app或另一升级区间冒充。
目标包从同一最终Application候选生成，使用登记的`peanut-admin-edition-upgrade-release-signing-key`；不复制临时key、不公开秘密、不替换已验证字节。
旧实例/四端使用`peanut-admin-consumer-upgrade-mysql84-gate`独立资源；P0-E只使用`peanut-admin-p0e-mysql84-gate`及专项绑定。
地址、工具、凭据引用、namespace、健康/新鲜度和fallback只从执行树资源登记解析；本计划不复制旧run_id、租约、host/port或清理路径。
按[旧实例runbook](../p0e-runtime-qualification.md#旧实例升级-runbook)执行真实旧包、adoption、迁移、读回及恢复，fresh八组不能替代它。候选与资源操作仍遵守原批准、租约、签名及资格可信性门禁。

## 4. 四端与厂商验收范围

| 入口 | 必须覆盖 | 边界 |
| --- | --- | --- |
| Web管理端 | 原生登录；文章/分类options；设置保存重开；菜单、Module停用、权限拒绝与Tenant A/B隔离；Local上传/读取/删除 | 独立合成实例、实际候选路由、登记对象前缀及清理owner，不借其他任务数据 |
| Platform | 独立PlatformOperator；Tenant启用/停用/授权；Storage/Provider配置与错误状态；秘密不回显 | 不连接旧演示，不以configured冒充云操作通过 |
| PC与UniApp H5 | 登录/会话失效；文章列表/详情/分类；已有收藏或资料写后读回；Tabbar路径、错误反馈、基本布局 | 只覆盖已有支持功能，每端单列，不自动加入原生小程序或未知业务 |
| Local/OSS/COS/七牛 | 选择、配置、错误合同；真实上传/读取/删除、失败补偿、凭据轮换逐厂商记录 | 登记测试资源/凭据缺失只阻塞对应资格；不造mock资格、不缩支持面 |
| 支付/短信/OAuth | 模块、配置、授权、秘密投影；真实支付/发送/回调/轮换另列 | 真实资金或消息动作须精确授权；不借生产/其他项目凭据 |

## 5. 验收与交付

只复用身份、输入和合同均未失效的证据；每组一次最低充分验证，失败依执行规则的唯一family预算处理。
P0-E、真实旧实例升级/恢复、四端业务与厂商资格分别记录范围，不能相互替代。固定候选失效后回Development，不在冻结树点修继续宣称qualified。
问题主登记维护问题处置，能力账本维护能力，唯一私有状态维护执行；不在本计划另建队列/总账或追加流水。
规则/合同/投影变更使用文档治理与对应本地静态检查；产品候选和资源变更仍按真实Gate验证。
总目标完成须已批准目标覆盖、阻塞闭环、真实产品资格、防复发机制及实际交付证据；计划、版本字符串、历史PR、局部通过都不能代替。
