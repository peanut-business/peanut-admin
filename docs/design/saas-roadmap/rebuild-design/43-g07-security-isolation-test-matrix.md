# G-07 安全与租户隔离测试矩阵

> 状态：Recalibrated and Reviewed（2026-07-15），通过 48 号复审，等待新编码批准
>
> 依赖：G-01 至 G-06
>
> 本文定义必须自动执行的安全验收契约，不授权编写运行时代码。

## 1. 先用业务语言说明

Peanut Admin 的安全不能只靠“每张表记得加 `tenant_id`”。真正需要证明的是：无论数据从页面、API、后台任务、缓存、导出、文件还是原生 SQL 进入和离开系统，都不能绕开租户、成员、功能权限和数据权限。

P0 至少要持续证明四件事：

1. 租户 A 的账号、请求、ID、缓存和任务不能读取或修改租户 B 的数据。
2. 同一租户内，成员只能执行角色允许的功能，并且只能处理数据权限允许的对象。
3. 平台操作员不是租户超级管理员，平台凭证不能直接调用租户业务 API。
4. 新 Module 或以后接入的文件、导出、队列等能力，必须继承同一组安全契约，不能另起一套简化规则。

任何一项失败都阻塞发布。前端隐藏菜单、测试环境绕过 Guard、只验证列表不验证详情等，都不能算通过。

## 2. 标准依据与采用范围

本矩阵以以下成熟标准作为检查来源：

- [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/)：作为 Web 安全控制验证框架。
- [OWASP ASVS 5.0.x 索引](https://cheatsheetseries.owasp.org/IndexASVS)：重点采用文件、认证、会话、授权、日志和错误处理章节。
- [OWASP API1:2023 Broken Object Level Authorization](https://owasp.org/API-Security/editions/2023/en/0xa1-broken-object-level-authorization/)：要求所有接收对象 ID 的接口执行对象级授权。
- [OWASP API Security Top 10 2023](https://owasp.org/API-Security/editions/2023/en/0x11-t10/)：补充认证、资源消耗、服务端请求和 API 清单风险。

Peanut Admin P0 以 ASVS Level 2 的工程目标作为基线，但不在文档阶段声称获得认证。正式发布时必须把采用的具体 ASVS 版本和控制项映射保存到仓库，版本升级由独立安全 DDR 处理。

## 3. 测试夹具

所有租户安全集成测试必须使用固定、可读的夹具命名，不得只生成难以审查的随机 ID。

| 夹具 | 内容 | 用途 |
| --- | --- | --- |
| `tenant_alpha` | active 租户 | 当前请求租户 |
| `tenant_beta` | active 租户 | 跨租户攻击目标 |
| `tenant_suspended` | suspended 租户 | 状态失效测试 |
| `account_multi` | 同时加入 Alpha/Beta | 证明一个 Account 的两个成员身份不能混用 |
| `member_alpha_admin` | Alpha 管理员 | 租户内完整管理权限 |
| `member_alpha_dept` | Alpha 部门范围成员 | 数据权限边界 |
| `member_alpha_self` | Alpha 本人范围成员 | 本人范围边界 |
| `member_beta_admin` | Beta 管理员 | 跨租户对照 |
| `member_suspended` | 已停用成员 | 即时失效测试 |
| `platform_operator` | 平台操作员 | 平台/租户 audience 隔离 |
| `department_alpha_root` | Alpha 根部门 | 部门树测试 |
| `department_alpha_child` | Alpha 子部门 | 下级范围测试 |
| `department_alpha_peer` | Alpha 同级部门 | 越权对照 |
| `object_alpha_allowed` | Alpha 且在允许范围 | 正向对象级授权 |
| `object_alpha_denied` | Alpha 但不在允许范围 | 同租户数据权限攻击 |
| `object_beta` | Beta 业务对象 | 跨租户 ID 攻击 |
| `project_alpha_a/b/c` | Alpha 同一类别的三个业务目标 | 同类多目标授权、单目标写和归属列 |
| `project_beta_a` | Beta Project，故意与 Alpha 使用相同业务编号 | 跨 Tenant typed target 攻击 |
| `queue_alpha_a` | Alpha 的另一目标类别，ID 字符串与 Project A 相同 | 目标类别混淆攻击 |
| `reference_shared` | 部署种子 ReferenceItem | shared_master 全局可见但按目标可用 |
| `reference_alpha_private` | Alpha 创建且初始只对 Project A 可用 | 统一主档作用范围 |
| `module_enabled` | 部署安装、Alpha 开通、成员有权 | 正向 Module |
| `module_disabled` | 部署安装但 Alpha 未开通或停用 | Module 三层守卫 |

夹具规则：

- Alpha 与 Beta 必须故意产生部分相同的业务编号、名称和排序值，防止测试只靠自然差异通过。
- `account_multi` 的两个 TenantMember 必须拥有不同角色和部门。
- `member_alpha_dept` 对 Project A/B 有 read、只对 Project A 有 update；Project C 完全无权。
- TargetSet 必须分别保存 Project 和 Queue，不能把相同字符串 ID 当成同一类别。
- 跨租户对象 ID 必须既测试“真实存在的其他租户 ID”，也测试不存在的 ID。
- 失败响应按 G-05 返回统一 Problem Details；对不可见单对象默认返回 404，不泄露存在性。

## 4. 测试层和运行环境

| 层级 | 必须验证 | 允许替身 |
| --- | --- | --- |
| 单元测试 | 状态机、范围合并、Provider、token 哈希、manifest 校验 | 可替换外部网络和时钟 |
| MySQL 集成测试 | 外键、唯一约束、事务、锁、真实 SQL、Repository 守卫 | 禁止 SQLite 代替 MySQL 8 |
| HTTP 集成测试 | Guard、Context、权限、数据权限、错误码、幂等 | 使用真实路由和中间件栈 |
| Worker/CLI 集成测试 | 可信上下文、任务重验、审计、失败隔离 | 可使用同步 transport，但必须经过正式 handler |
| 浏览器测试 | cookie、CSRF、CORS、切租户、旧请求、菜单与状态清理 | 使用真实构建产物和 API |
| 静态/供应链检查 | 依赖边界、类型、依赖漏洞、密钥、生成文件漂移 | 不得以人工目测替代 |

P0 CI 必须在 MySQL 8 真实服务上执行。使用 SQLite 跑快测可以作为补充，但不能替代租户约束和并发验收。

## 5. 租户与数据库隔离矩阵

| ID | 场景 | 操作 | 必须结果 |
| --- | --- | --- | --- |
| TEN-001 | ORM 列表读取 | Alpha Session 查询含 Alpha/Beta 数据的表 | 只返回 Alpha 且满足数据权限的数据 |
| TEN-002 | ORM 详情读取 | Alpha Session 请求 `object_beta` ID | 404，写拒绝审计，不泄露对象属于 Beta |
| TEN-003 | ORM 新增 | 请求体伪造 `tenant_id=beta` | 忽略或拒绝客户端字段，实际只可写 Alpha |
| TEN-004 | ORM 更新 | Alpha Session 更新 `object_beta` | 404，Beta 数据不变化 |
| TEN-005 | ORM 删除 | Alpha Session 删除 `object_beta` | 404，Beta 数据不变化 |
| TEN-006 | 原生 SQL 读取 | Module 使用受控 SQL 接口查询 | 缺少 TenantConstraint 时测试和静态检查失败 |
| TEN-007 | 原生 SQL 写入 | Module 使用受控 SQL 接口更新 | tenant predicate 与对象级校验缺一即拒绝 |
| TEN-008 | Repository 误用 | 租户代码调用平台 Repository 或反向调用 | 依赖边界检查失败，运行时 Guard 也拒绝 |
| TEN-009 | 跨租户复合外键 | Alpha 行引用 Beta 部门/角色/成员 | 数据库约束失败 |
| TEN-010 | 租户内唯一约束 | Alpha/Beta 使用相同业务编码 | 可并存；同一租户重复失败 |
| TEN-011 | 批量 ID | Alpha 批量请求混入 `object_beta` | 整批原子拒绝或按 API 明示逐项失败，绝不部分越权 |
| TEN-012 | 关联 ID | Alpha 创建对象时引用 Beta 关联对象 | 404/422，不能写入跨租户关系 |
| TEN-013 | 搜索/排序 | 利用过滤器、排序字段或聚合查询 | 结果和总数都不能包含 Beta 信息 |
| TEN-014 | 统计 | Alpha 请求 count/sum/group | 聚合前先应用 TenantConstraint 和 DataConstraint |
| TEN-015 | 导入 | 文件行内伪造 Beta ID | 行级失败且不写 Beta；错误文件不泄露 Beta 数据 |
| TEN-016 | 导出 | Alpha 导出任务 | 任务创建与执行两次校验，文件只含当前有效范围 |
| TEN-017 | 软状态 | Tenant suspended 后继续请求 | 立即拒绝，不依赖 access token 到期 |
| TEN-018 | 多租户账号 | `account_multi` 用 Alpha Session 访问 Beta | 不能因 Account 同一而跨越 TenantMember |
| TEN-019 | 默认拒绝 | 请求未建立 TenantContext | 租户 Repository、cache、lock、audit 全部拒绝 |
| TEN-020 | 哨兵值 | 传 `tenant_id=0/null/empty` 访问平台或租户数据 | 不产生平台权限或通配租户语义 |

`TEN-006` 和 `TEN-007` 不能只靠代码评审。运行时数据访问 API 必须要求不可空的 `TenantContext` 或 `TenantConstraint` 类型；静态边界检查负责阻止 Module 绕过正式接口。

## 6. 认证、会话和上下文矩阵

| ID | 场景 | 必须结果 |
| --- | --- | --- |
| AUTH-001 | 正确邮箱密码登录 | 只返回 Account 和可选择租户，不直接信任请求 tenant_id |
| AUTH-002 | 错误密码/未知邮箱 | 外部错误一致，内部审计可区分，防止账号枚举 |
| AUTH-003 | 连续失败 | 账号/IP 组合限流与临时锁定生效，不记录明文密码 |
| AUTH-004 | refresh rotation | 旧 refresh token 立即失效，新 token 只签发一次 |
| AUTH-005 | refresh reuse | 撤销 token family/session 并记录安全事件 |
| AUTH-006 | 并发 refresh | 只能一个成功，其余得到可处理失败，不产生多个有效后代 |
| AUTH-007 | 退出 | access/refresh 后续都被拒绝，cookie 正确清除 |
| AUTH-008 | Account 禁用 | 已签发 Session 下一请求立即失效 |
| AUTH-009 | Tenant 停用 | 该 Tenant Session 下一请求立即失效，其他 Tenant 不受影响 |
| AUTH-010 | Member 停用 | 该成员 Session 下一请求立即失效 |
| AUTH-011 | Credential 修改 | `security_revision` 变化撤销相关 Session |
| AUTH-012 | 角色变化 | Session 不强制退出，`authorization_revision` 使权限缓存立即失效 |
| AUTH-013 | 租户切换 | 创建新 Tenant Session、撤销旧 Session、清空旧前端状态 |
| AUTH-014 | audience 混用 | Tenant token 访问 platform API、Platform token 访问 tenant API 均拒绝 |
| AUTH-015 | cookie 属性 | refresh cookie 为 Secure、HttpOnly、正确 SameSite、Path 不冲突 |
| AUTH-016 | token 存储 | access token 不进入 localStorage/sessionStorage/URL/log |
| AUTH-017 | CLI/计划任务 | 只有 manifest 声明的 system actor/operation 可建立可信 Context |
| AUTH-018 | 队列伪造上下文 | 消息体 tenant_id/member_id 不能直接变成可信 Context |
| AUTH-019 | 任务执行时状态变化 | Worker 重新加载 Tenant/Member/Permission/DataPermission 后再执行 |
| AUTH-020 | 时钟边界 | access/refresh/有效期在到期毫秒边界按服务器 UTC 正确拒绝 |
| AUTH-021 | 伪造当前业务目标 | header/body 中的 current_subject/current_target 不能进入 TenantContext |
| AUTH-022 | 请求级授权上下文 | 同一 Session 先后操作 Project A/B，AuthorizedOperationContext 分开且不回写 Session |
| AUTH-023 | Worker typed targets | 入队后目标撤权、换类别或基数不符时执行拒绝 |

## 7. 功能权限与数据权限矩阵

| ID | 场景 | 必须结果 |
| --- | --- | --- |
| PERM-001 | 无 Permission 调 API | 403，前端是否显示按钮不影响结果 |
| PERM-002 | 有菜单无动作权限 | 可见页面不等于可写，动作 API 拒绝 |
| PERM-003 | 有动作无数据范围 | 默认拒绝任何业务对象 |
| PERM-004 | `tenant_all` | 只覆盖当前 Tenant，不覆盖所有 Tenant |
| PERM-005 | `self` | 只覆盖 Provider 定义的本人资源，不按请求参数认定本人 |
| PERM-006 | `own_department` | 只覆盖成员主部门，不含下级和同级 |
| PERM-007 | `department_tree` | 覆盖当前部门及真实下级，不含同级或其他树 |
| PERM-008 | `specified_departments` | 只覆盖规则内有效且同 Tenant 的部门 |
| PERM-009 | `specified_objects` | 只覆盖 TargetResolver 可解析的同 Tenant 对象 |
| PERM-010 | 单组条件 | 条件按 AND 合并，不能任意放宽 |
| PERM-011 | 多组条件 | 条件组按 OR 合并，每组内部仍为 AND |
| PERM-012 | 多角色 | 有效允许范围按 OR 合并，但每条规则仍受 TenantConstraint 限制 |
| PERM-013 | 过期规则 | 到期毫秒后不再参与授权，缓存同时失效 |
| PERM-014 | 列表 | 查询谓词先进入 SQL，不得取全量后在 PHP 过滤 |
| PERM-015 | 详情 | 使用 Provider 的单对象授权，不能只按 ID 查表 |
| PERM-016 | 创建 | 创建目标部门/对象范围在写入前校验 |
| PERM-017 | 更新/删除 | 读取旧对象并校验动作与数据范围后再写 |
| PERM-018 | 批量 | 每个对象都校验，不能只校验第一个 ID |
| PERM-019 | 导入/导出 | 每行或查询范围沿用相同 Provider/Constraint |
| PERM-020 | 后台任务 | 创建时与执行时都校验，权限收窄后不能按旧快照继续 |
| PERM-021 | 搜索条件 | 用户筛选只能收窄授权范围，不能扩大 Provider 范围 |
| PERM-022 | 缓存失效 | 角色/规则/部门/Module 变化使对应授权缓存立即换 revision |
| PERM-023 | 目标过多 | 超过阈值使用 EXISTS/read model，不生成无界 IN 列表 |
| PERM-024 | 未注册资源 | Module 未声明 Resource/Operation/Provider 时默认拒绝 |
| PERM-025 | 同类多目标读 | Project A/B read 返回两者，Project C 不返回，total/aggregate 一致 |
| PERM-026 | 同类单目标写 | update Project A 成功，update Project B/C 拒绝，即使列表能看 B |
| PERM-027 | 目标类别混淆 | Queue A ID 冒充 Project A 时拒绝，不能只比较字符串 ID |
| PERM-028 | TargetSet 单类别 | 一个 set 同时提交 Project/Queue schema 和 Service 均拒绝 |
| PERM-029 | `one_required` | 0 个或 2 个 primary target 在业务规则前拒绝 |
| PERM-030 | `many_readable` | 请求 targets 只能取授权集合子集，不传时仍只覆盖 A/B |
| PERM-031 | `aggregate_read` | 只聚合 A/B，返回范围摘要，无写入口 |
| PERM-032 | 普通 batch | 资源跨 Project A/B 时拒绝；同 Project A 内批量仍逐资源校验 |
| PERM-033 | `bulk_write` | P0 manifest/endpoint 默认不可用，伪造 operation 拒绝 |
| PERM-034 | `policy_publish` | 只写一份策略并逐目标记录结果，不直接覆盖 Project 真相表 |
| PERM-035 | target candidates | 零/单/多结果都只包含当前 operation 可授权目标，分页且无全量泄露 |
| PERM-036 | shared_master visibility | shared/private Reference 同池查询，但 private 只对 Project A 可见/可用 |
| PERM-037 | shared_master 使用 | 已知 private Reference ID 在 Project B 引用仍拒绝 |
| PERM-038 | shared scope fail closed | ScopeProvider 缺失、异常或关系不明时不回退为 global_reference |
| PERM-039 | 策略候选委派 | 只有 data-policy.manage 但无 policy_selection_permission 时不能枚举/写入 Module TargetSet |

## 8. Module、缓存、锁、幂等和审计矩阵

| ID | 场景 | 必须结果 |
| --- | --- | --- |
| SYS-001 | Module 未部署 | 路由不加载，TenantModule 不能伪造开通 |
| SYS-002 | Module 未为 Tenant 开通 | API、菜单、任务和事件消费者都不可用 |
| SYS-003 | Member 无 Permission | Module 已开通也不能执行对应操作 |
| SYS-004 | Module 停用并发请求 | 新请求拒绝；已开始事务按声明完成或回滚并审计 |
| SYS-005 | 跨 Module 内部表访问 | Deptrac/所有权检查失败，代码不能合并 |
| SYS-006 | Module 契约查询 | 调用方仍受自己的 Context 与被调用方 Provider 约束 |
| SYS-007 | 缓存键 | 租户缓存键必须包含 tenant_id、资源和 revision |
| SYS-008 | 缓存穿租户 | Alpha/Beta 相同业务 key 返回各自值，不共用缓存对象 |
| SYS-009 | 锁键 | 租户业务锁包含 tenant_id，Alpha 锁不阻塞或覆盖 Beta 同业务号 |
| SYS-010 | 幂等键 | 同 key 在不同 Tenant 可独立；同 Tenant 同 operation 只产生一次副作用 |
| SYS-011 | 幂等并发 | 并发相同请求只提交一次事务并复用确定结果 |
| SYS-012 | 审计平面 | Tenant 事件只进 tenant audit，Platform 事件只进 platform audit |
| SYS-013 | 审计身份 | 记录 audience、actor account/member/tenant、target tenant、action、target、result、request/trace id |
| SYS-014 | 审计目标组织 | P0 member/system 的 actor_tenant 必须等于 target tenant；平台治理镜像明确 platform_operator 且无业务 action；未来受控协作记录双方和 relation/grant |
| SYS-015 | 日志脱敏 | 密码、token、cookie、密钥、完整身份证/手机号等不进入日志 |
| SYS-016 | 错误脱敏 | 生产错误不暴露 SQL、表名、堆栈、内部路径和 token 片段 |
| SYS-017 | correlation | HTTP 到 queue/worker 的 trace 可关联，但不能借 trace 建授权 Context |
| SYS-018 | 事务回滚 | 业务失败不留下数据副作用；拒绝/失败安全事件仍按策略可审计 |
| SYS-019 | 多目标审计 | 单目标记录 typed target；多目标记录数量和稳定 digest，不记录完整敏感 ID 列表 |
| SYS-020 | Module 多实例 | 同一 Module 服务 Project A/B/C，不复制安装、迁移或数据所有权 |
| SYS-021 | shared master 单真相 | 部署种子和 Tenant 自建 Reference 共用表/ID contract，前端/API 不拼双池 |
| SYS-022 | 平台治理边界 | PlatformOperator 无 TenantSession 时不能查询 Tenant shared-master 业务候选 |

## 9. 浏览器与 API 边界矩阵

| ID | 场景 | 必须结果 |
| --- | --- | --- |
| WEB-001 | CSRF | 使用 cookie 的变更请求必须通过框架级 CSRF/SameSite 设计验证 |
| WEB-002 | CORS | 只允许配置的 origin/method/header，禁止凭证请求配 `*` |
| WEB-003 | 路由伪造 | 手工输入无权 route 不加载受保护数据，API 仍拒绝 |
| WEB-004 | 按钮伪造 | DevTools 显示隐藏按钮不能绕过后端 Permission |
| WEB-005 | 切租户旧响应 | 旧 Tenant 请求晚返回不能写入新 Tenant store |
| WEB-006 | 平台/租户 cookie | 两种 refresh cookie 名称和 Path 不冲突 |
| WEB-007 | 安全响应头 | CSP、frame、content type、referrer 等由统一中间件验证 |
| WEB-008 | 限流响应 | 返回 429 和 Retry-After，不能以 200 包装失败 |
| WEB-009 | OpenAPI 漂移 | 实际路由/响应与 schema 不一致时 CI 失败 |
| WEB-010 | 未知前端贡献 | 未注册 route/component/menu contribution 默认拒绝并诊断 |
| WEB-011 | 大整数 ID | API 以字符串传输，不发生 JavaScript 精度截断后越权 |
| WEB-012 | 内容注入 | 显示名、菜单和错误消息按上下文编码，不执行用户 HTML/脚本 |

## 10. P1 能力必须继承的合同测试

P1 能力尚不在 P0 实现范围，但一旦进入产品，必须先通过以下合同测试。不得以“这是新模块”为由另写简化安全模型。

| 合同 | 必须复用的 P0 测试 | 追加要求 |
| --- | --- | --- |
| `FileSecurityContract` | TEN、PERM、AUTH、SYS | 文件 metadata 强制 tenant_id；下载再次鉴权；对象存储 key 不可枚举；类型、大小、恶意内容和压缩炸弹检查 |
| `ExportSecurityContract` | TEN-014/016、PERM-014/019/020、AUTH-019 | 创建和执行双重校验；短期签名下载；文件到期销毁；CSV/表格公式注入防护 |
| `QueueSecurityContract` | AUTH-017/018/019、SYS-017/018 | 消息仅保存上下文候选和不可变请求快照；Worker 重新建立可信 Context；失败和重试幂等 |
| `SupportSessionContract` | AUTH-014、PERM 全部、SYS-012/013/014 | P0 不实现；以后必须显式授权、限时、说明原因、全量审计、禁止静默冒充 |
| `PluginSecurityContract` | SYS-001 至 SYS-006 | P0 不远程安装；以后校验来源、签名、兼容范围、迁移、权限增量和卸载副作用 |

## 11. 静态与供应链闸门

P0 执行计划必须选定并锁定成熟工具版本。工具名称可在初始化任务根据 ThinkPHP 8 和 Vue 3 兼容性形成 DDR，但以下能力不可删除：

| 能力 | 推荐工具方向 | 失败处理 |
| --- | --- | --- |
| PHP 静态分析 | PHPStan | 阻塞合并，不得全局 baseline 掩盖新增错误 |
| PHP 架构边界 | Deptrac | Module 深层依赖或越层即失败 |
| PHP 依赖漏洞 | Composer audit | 高风险漏洞阻塞，例外需有到期 DDR |
| 前端类型/规范 | TypeScript、ESLint | 类型或规则失败阻塞 |
| 前端依赖漏洞 | pnpm audit + 上游公告 | 高风险漏洞阻塞，不能只看一个数据库 |
| 密钥历史扫描 | Gitleaks | 工作树与 Git 历史都必须无未处置发现 |
| OpenAPI | 规范校验 + generated types diff | schema 无效或生成文件漂移即失败 |
| Module 边界 | manifest/schema/所有权检查 | 未声明资源、重复 key、循环依赖即失败 |

依赖扫描结果不是绝对安全证明。锁文件、上游安全公告、许可证和实际可达性必须一起审查。

## 12. 并发与性能验收

安全不能靠关闭并发获得。P0 必须验证：

1. 两个并发 refresh 只有一个 token rotation 成功。
2. 两个相同 Idempotency-Key 请求只产生一次业务副作用。
3. 角色/规则变更与业务请求并发时，旧授权缓存不会在 revision 变化后继续使用。
4. TenantModule disable 与业务请求并发时，新事务不能越过三层守卫。
5. 跨租户同业务号的锁、缓存和幂等记录互不污染。
6. Department 调整后，后续查询不再使用旧部门树范围。
7. 10、500、5000 个同类授权目标分别验证等值、IN 和 EXISTS/read-model 路径，结果完全一致。
8. 多目标列表和 aggregate 不产生逐目标 N+1 SQL；target candidates 必须分页。
9. TargetSet、target type registry 或 shared scope revision 变化后旧授权缓存不可达。

在基准硬件和真实数据规模尚未固定前，不虚构绝对 QPS。P0-A 必须建立版本化基准环境和数据集；后续同环境下安全关键链路 p95 退化超过 20% 时阻塞合并，除非有说明原因、期限和复测结果的 DDR。

同时记录：

- 登录、刷新、Context、授权和典型列表 API 的 p50/p95/p99。
- SQL 数量、最慢 SQL、是否使用 tenant/data scope 索引。
- 授权缓存命中率和 revision 失效延迟。
- 任务吞吐、重试次数和重复副作用计数。

## 13. CI 套件与命令契约

真实命令由 G-09 和 P0-A 固定，但必须提供以下稳定入口：

```text
./scripts/check                 # 全量必过入口
./scripts/test-unit             # 前后端单元测试
./scripts/test-integration      # MySQL、HTTP、Worker 集成测试
./scripts/test-security         # 本文编号矩阵
./scripts/test-browser          # Admin Shell 浏览器测试
./scripts/check-architecture    # Deptrac、Module/包边界
./scripts/check-supply-chain    # 依赖、密钥、许可证
./scripts/check-openapi         # schema 和生成类型漂移
```

规则：

- `scripts/check` 必须调用所有 P0 必需入口，不能把安全测试藏在可选命令。
- 每个失败输出测试 ID、当前 audience/tenant fixture、入口和原因，但不得输出密钥或 token。
- CI 与本地使用同一脚本；CI 不另写一套只有云端能运行的逻辑。
- 禁止 `--skip-security`、生产配置中的 test bypass、吞掉退出码或删除失败测试。
- 测试发现缺陷后先修代码；只有事实源错误时才修改测试，并在提交中说明设计依据。

## 14. 发布阻断条件

以下任一条件成立，P0 不得发布：

1. 本文任何 P0 测试失败、被跳过或没有对应实现。
2. 租户表缺少 `tenant_id NOT NULL`、复合约束或受控 Repository。
3. 存在可从 Module 绕过 Guard/Provider 的公开数据库入口。
4. 平台和租户凭证可以混用，或用 `tenant_id=0/null` 产生平台语义。
5. 列表、详情、写入、批量、导入、任务中任一条路径没有数据权限校验。
6. 密钥扫描有未处置发现，或依赖/许可证风险没有带到期时间的批准记录。
7. 审计或日志泄露凭证、token、cookie、SQL、客户隐私数据。
8. 安全测试只能在开发者个人环境运行，CI 无法复现。
9. operation 缺少 target cardinality/target type，或可把不同类别 ID 混入同一 TargetSet。
10. shared_master 缺少 ScopeProvider、通过 `tenant_id NULL/0` 共享，或 API/前端维护两套真相池。
11. 普通写/批量接口可以跨多个 primary target 修改，或 `bulk_write` 未经专项授权默认开放。
12. 租户审计不能区分 actor tenant、target tenant 和 primary boundary target。

## 15. G-07 结论

Peanut Admin 的租户安全是一条贯穿数据库、API、权限、Module、缓存、任务和前端的合同，不是某个中间件的单点功能。

P0 必须先用这些测试证明最小内核不会串租户、越权、混淆平台身份或混淆业务目标类别，并证明同类多目标、单目标写和统一共享主档不会形成旁路。P1 的文件、导出、队列、支持会话和 Plugin 只有继承合同测试后才允许启用。G-09 必须把本文测试编号分配到具体实现任务和 CI 入口，低上下文 Agent 不得自行删减。
