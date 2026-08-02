# Peanut Admin 应用生命周期架构 v2 外部评审

## 评审结论摘要

- 结论：`需要重大修订后再进入 U01-U04 实施`
- 方向判断：三类所有权、薄应用壳、可信前端映射、后端 Provider、`plan/apply` 和三方合并的总体方向正确。
- 主要问题：当前方案没有可靠说明旧 baseline 内容从哪里来；把开发机代码升级与部署环境数据库升级放进同一次 `apply`，无法形成真实原子性；U01 与 U02 存在先后循环；全目录 page glob 扩大了页面暴露面；独立薄容器和字符串路径 middleware scope 都不够稳妥。
- 当前最先需要固定的不是更多接口，而是四个基础事实：Peanut package 如何发布、recipe 内容如何随 release 分发、代码升级与数据库部署如何分段、应用扩展 manifest 如何声明兼容性和贡献范围。

本评审基于 Stage C.2 固定快照 `69c5b2c271413f6ff741de65437f29e04f975300`，并使用已有 CodeGraph 索引核对 Peanut、Symfony Flex、Nx、Vben 和 Filament 的真实实现。未修改业务代码或运行测试。

## 1. 三种所有权划分

### 总体立场：认可方向，但模型需要拆开“所有权”和“更新策略”

`package-owned / recipe-managed / application-owned` 足以描述仓库内代码文件的主要所有权，不建议为了 CI、Docker 和环境配置再增加一个模糊的第四种文件所有权。真正遗漏的是仓库外的 `deployment-owned` 状态：secrets、生产连接、基础设施分配、部署参数和运行数据库。它不进入 `managed-files.lock`，但必须在架构中单列。

当前 `exclusive/shared/advisory` 混合了两件事：谁拥有文件，以及升级器怎么处理文件。`advisory` 不是所有权，而是“一次生成后交给应用”的更新策略。建议改为：

```text
owner: peanut | application
update_policy: replace-if-pristine | three-way | seed-once
```

- `replace-if-pristine`：Peanut 拥有；文件未被修改时可替换，被修改则停止并建议迁移到扩展点。
- `three-way`：确实需要 Peanut 与应用共同修改时才使用。
- `seed-once`：首次生成后永久归应用；以后只在升级报告中提示模板变化。

CI、Docker、反向代理样板和 `.env.example` 通常应为 `seed-once`，真实 `.env` 与 secrets 属于 deployment-owned。不要把它们纳入持续三方合并。

“约 6 个 recipe-managed 文件”可以作为目标上限，不能作为预先承诺。应在扩展契约完成后逐文件盘点；能进入 package 的必须进入 package。预计合理范围是：前端组合入口、后端 bootstrap/provider 清单、模块/扩展清单，以及可能的 package workspace 清单。部署文件不应因为“脚手架生成过”就自动成为长期受管文件。

### 修改建议

1. 保留三类代码所有权，新增独立的 deployment-owned 状态边界。
2. 从 `ownership` 中删除 `advisory`，改成独立 `update_policy`。
3. 不在设计阶段固定 6 个文件；U01 完成扩展边界后列出精确清单和每个文件必须留在应用仓的理由。

## 2. `managed-files.lock` 与 `baseline_blob`

### 总体立场：当前方案有根本缺口

Git blob hash 只证明内容身份，不携带内容。升级器只有 `baseline_blob` 时，除非旧 blob 仍存在于目标仓 `.git/objects`、旧 release Git 历史或旧 package 中，否则无法重建三方合并祖先。Symfony Flex 能工作，是因为它同时取得旧 recipe 和新 recipe 的真实内容，再临时构造 blob；不是因为 lock 中有一个 hash 就足够。

因此，旧 baseline 不能依赖下游项目 Git 历史。每个 release 必须发布不可变 recipe artifact，至少包含：

```text
recipe id + recipe version
受管路径
每个路径的真实文件内容
内容 digest / git blob id
rename / delete / binary policy
整个 artifact 的 digest 或签名
```

下游 lock 应记录 `recipe_id`、`recipe_version`、`base_digest` 和 `artifact_digest`。升级器按 lock 取得旧 recipe artifact，再取得目标 recipe artifact。这样即使 DCS Git 历史已经压缩或迁移，公共祖先仍可重建。

不建议照搬 Flex 把临时 blob 写入目标仓 `.git/objects`。对于极少量受管文本文件，优先在隔离临时目录运行：

```text
git merge-file current old-base new-base
```

rename、delete 和 binary 由 release manifest 的显式动作处理。若最终仍采用 `git apply -3`，对象也应放在隔离 object directory，不污染目标项目对象库。

代码升级器可以明确要求 Git。DCS 是源码项目，开发机和 CI 安装 Git 是合理前置；没有必要为 v1 再实现一套纯 PHP 合并引擎。Docker 生产容器是否有 Git 与代码升级无关，因为生产环境只应执行已构建 release 的数据库部署步骤。

Git 不可用时应 fail closed，并输出 old/current/new 三份文件和人工处理说明，不能静默退化为双向 patch。

### 修改建议

1. 把 recipe artifact 的分发和保留周期加入 ReleaseManifest 设计。
2. lock 同时记录 recipe 身份和 digest，不只记录 blob hash。
3. v1 明确要求 Git；不实现纯 PHP merge fallback。
4. 代码升级只在开发/CI 工作区执行，生产容器不运行 managed-file merge。

## 3. 前端 pageRegistry

### 总体立场：可信本地映射正确，全页面 glob 需要修改

Vben 的核心价值是“服务端只能选择前端已经打包的组件”，不是“所有 views 文件都应暴露”。直接 glob `extensions/frontend/pages/**/*.vue` 会把内部调试页、未完成页和只允许本地导航的页面一并放入可解析表。服务端一旦错误配置或被攻陷，就能选择任何已打包页面。

不应使用文件路径作为永久 `component_key`。文件移动或重命名会无意造成服务端菜单契约破坏。建议 glob 的对象是 extension manifest，而不是所有页面：

```ts
// extensions/frontend/dcs/extension.ts
export default defineAdminExtension({
  id: 'dcs',
  pages: {
    'dcs.case.list': () => import('./pages/CaseListPage.vue'),
  },
  layouts: {
    'dcs.workspace': () => import('./layouts/DcsWorkspaceLayout.vue'),
  },
})
```

组合根可以 `import.meta.glob('.../extension.ts', { eager: true })` 自动发现扩展，因此仍不需要维护中央注册文件；但每个扩展显式声明真正公开的页面 key。

key 规则应是稳定的业务命名空间，而不是相对路径：`<extension-id>.<resource>.<view>`。编译时必须拒绝重复 key、`peanut.*` 保留命名空间、未知 layout、非法 route name 和 capability 不匹配。服务端只返回 key，不返回 import 路径。

`sealed` 也应拆成“行为”和“表现”：

- 登录状态机、Token、Tenant 选择、返回地址、权限和错误协议保持 sealed。
- Logo、文案、背景、表单外壳、Workspace 视觉和状态页表现通过 typed appearance/slot 提供。
- 不允许应用用一个完整 LoginPage 替换核心认证流程。

### 修改建议

1. glob extension manifest，不 glob 全部 `.vue` 页面。
2. `component_key` 使用显式稳定 ID，不从文件路径推导。
3. sealed 核心行为，开放受限 appearance 和 slot。
4. 未知 key 使用本地诊断型 not-found，并记录错误；绝不执行远端路径。

## 4. 后端 Provider 与中间件链

### 总体立场：Provider 方向正确，容器和 scope 设计需要修改

`after('peanut.permission', Middleware::class, '/app/*')` 的字符串前缀不足以作为安全边界。前缀可能写错、重叠、遗漏版本段，甚至匹配到其他模块。middleware 应绑定到已编译的 route group 或 module key，由系统从模块 manifest 生成实际路径和 audience，而不是由应用重复填写字符串。

推荐形式：

```php
$app->routes()->module('dcs.case', new DcsRouteProvider());
$app->middleware()->forModule('dcs.case')
    ->after(CoreMiddleware::PERMISSION, DcsCaseScopeMiddleware::class);
```

编译期核对 module owner、audience、route namespace 和 middleware 锚点；应用 middleware 只能作用于自己拥有的 route group。

不建议完全独立实现 `ServiceContainer`。Peanut 运行在 ThinkPHP Host 内，应定义一个很薄的 Peanut `ServiceRegistry` contract，但默认 adapter 包装 ThinkPHP 容器。这样 Peanut 不把业务 API 绑定死到 ThinkPHP，同时也不维护第二套 singleton、循环依赖、构造函数解析和作用域规则。

两个 Provider 阶段对 v1 足够，但必须给出严格语义：

- `register`：只声明 binding，不允许解析服务或访问数据库。
- 所有 Provider 完成 register 后，按依赖拓扑进入 `boot`。
- `boot`：注册 routes、middleware、workers 和事件贡献。
- 重复 binding、循环依赖、未知依赖和覆盖 final service 必须在启动编译阶段失败。

不需要照搬 ABP 五阶段。长驻 Worker 的资源释放应由独立 `WorkerLifecycle` 或 adapter 处理，不为普通 PHP 请求增加通用 shutdown 阶段。

### 修改建议

1. middleware scope 从路径字符串改为 module/route-group ownership。
2. 用 ThinkPHP 容器 adapter，不建设第二套 IoC 实现。
3. 保留 register/boot 两阶段，补充依赖拓扑、禁止早解析和冲突规则。
4. 明确 final/decoratable/replaceable service token，认证、Tenant、权限和升级账本必须 final。

## 5. 两阶段升级器

### 总体立场：plan/apply 正确，但当前事务边界不可实施

`plan` 可以下载目标 release 和 package/recipe artifact。这里的“只读”应定义为“不修改项目工作区和数据库”，不应承诺零网络或零缓存写入。提供：

- 默认从可信源取得 artifact 并写入仓库外 content cache。
- `--offline` 只使用已缓存且 digest 匹配的 artifact。
- plan 固定当前 project lock、目标 release digest、旧/新 recipe digest 和包摘要。

manual migration 不应在 apply 中途暂停。正确流程是：plan 若含未解决 manual action，则自动 apply 不得开始。开发者修改 application-owned extension 后重新运行 plan，由兼容性检查确认问题消失；只有无法机器验证的说明性动作才允许显式 `ack`，并把 ack 写入独立 resolution 文件和最终报告。

更重要的是，代码升级和生产数据库升级必须拆开：

### Code prepare/apply（开发机或 CI）

1. 在隔离 worktree 中升级 Peanut packages。
2. 运行确定性 source migration。
3. 合并 recipe-managed 文件。
4. 生成新的 project/managed lock 和代码升级报告。
5. 人工解决冲突并形成普通 Git commit。

任何失败都可以丢弃该 worktree；不需要为 6 个文件建设复杂的跨进程代码步骤 ledger，Git commit 就是代码恢复边界。

### Database preflight/apply（部署环境）

1. 核验部署 artifact、目标 release、当前数据库 migration ledger 和备份证据。
2. 获取数据库升级锁。
3. 只执行 Peanut-owned migration。
4. 记录数据库逐项状态和 checksum。

当前设计将两者放在一个 apply 中，并要求数据库成功后才更新文件 lock。现实中代码构建、部署和数据库通常不在同一机器，也不共享同一个 Git worktree，因此这个原子性无法成立。代码 lock 应随升级代码 commit 更新；数据库状态由数据库 ledger 独立记录。

这也要求 release 的数据库 migration 采用 expand/contract 兼容策略，至少保证旧代码与迁移中的 schema 有明确兼容窗口。不能用“失败后不更新 managed lock”替代数据库恢复设计。

### 修改建议

1. 把命令拆为 `upgrade plan`、`upgrade code apply` 和 `upgrade database apply`，共享同一 release identity。
2. plan 的只读定义为 workspace/DB read-only，允许显式 artifact fetch/cache。
3. manual action 在任何写入前阻断，不在 apply 中途等待。
4. 代码使用 Git 作为回滚边界；数据库继续使用 backup、lock 和 migration ledger。

## 6. U01 → U02 → U03 → U04 顺序

### 总体立场：当前顺序存在循环依赖，必须调整

U01 声称生成“完整可直接使用的标准后台”，但 U02 才提供页面映射、Provider、middleware 和 Worker 扩展。没有 U02，U01 只能生成另一份即将被改写的临时 Host；这会增加受管文件并制造第二轮迁移。

建议调整为：

### U01：应用契约与薄组合根

- 固定 frontend extension manifest、appearance、HTTP 阶段。
- 固定 backend Provider、route group、middleware scope 和 service token。
- 先让现有标准后台使用同一组合模型，确保“标准后台=脚手架运行时”。
- 固定 `.peanut/project.json`、recipe artifact 和 managed lock schema。

### U02：完整创建器

- `peanut new` 生成基于 U01 契约的完整应用。
- 生成真实登录、菜单、权限和 Host 组合根。
- 只生成精确受管文件与 application-owned extension 骨架。

### U03：代码升级器与发布工件

- 自动生成 release manifest 和 recipe artifact。
- 实现 plan、package upgrade、source migration 和三方合并。
- 不包含生产数据库执行。

### U04：数据库部署与 DCS handoff

- 将现有数据库升级器绑定到同一 release identity。
- 用真实 DCS 演示创建、代码升级、数据库升级和冲突停止。

U03 的 recipe merge engine和 release schema可以在 U01 schema 固定后并行实现，但最终接入必须等待 U02 产物稳定。不要等 U02 所有界面细节完成才开始纯工具工作，也不要在 schema 未固定前写完整升级器。

## 7. 过度设计评估

### 总体立场：三方合并本身不过度，当前 ledger 和通用迁移模型有过度风险

如果“下游可以长期跟随 Peanut 升级”是产品承诺，即使只有 6 个受管文件，可靠三方合并也有价值。人工复制升级的成本会在每个 DCS、每次 release 中重复发生；实现一次基于 Git 的受限合并比长期维护人工指南更经济。

但必须控制边界：

- 不实现纯 PHP Git 合并替代品。
- 不实现完整通用 IoC 容器。
- 不为代码阶段建设数据库式跨进程逐步 ledger；Git worktree/commit 已足够。
- source migration 只能修改 recipe-managed 文件，不能扫描或改写 `extensions/`、`domain/`。
- manual migration 不是可执行脚本，只是兼容性阻断和人工说明。
- 不建立任意位置、任意顺序的通用 hook 系统，只提供产品已经需要的命名贡献点。

ReleaseManifest 绝不能手工填写。它必须由 release tooling 从以下固定输入确定性生成：

- Composer/pnpm release lock 或 package artifacts。
- recipe artifact 清单与 digest。
- source migration metadata。
- 数据库 migration inventory。
- compatibility policy。

当前项目状态明确记录“没有 npm/Packagist 发布流程”。这不是文档细节，而是 package-owned 模型的实施前置。U01 必须先决定近期使用签名 release bundle 内的本地 package tarball，还是建立正式 Composer/NPM registry 发布；没有不可变 package artifact，就不能宣称升级器能升级 package。

### 修改建议

1. 保留 Git 三方合并，但删除纯 PHP fallback 和通用代码 ledger。
2. ReleaseManifest 全自动生成并做 schema 校验，禁止维护者手写。
3. 把 package artifact 分发方案提升为 U01 的硬决策。

## 8. 整体结构评估

### 总体立场：目标正确，但不能直接作为实现合同

最关键、最容易出错的决策是“发布工件和所有权是否真的闭合”。如果旧 recipe 内容不可取得、Peanut package 没有不可变发布物、extension contract 仍会迫使 DCS 修改 Host，那么再完善的 plan/apply 都只是在自动化一个不稳定边界。

第二个关键错误是把代码升级和数据库部署想象成一个事务。它们发生在不同环境、有不同回滚方式，必须共享 release identity，但不能共享虚假的单次原子提交。

### 文档还遗漏的事项

1. **Package 分发**：Composer/NPM 包从哪里取得、如何验签、如何离线缓存和保留旧版本。
2. **Recipe artifact 生命周期**：旧 recipe 内容保留多久、release 被撤回后是否仍可升级。
3. **前后端版本一致性**：PHP、Web、OpenAPI/generated client 和 Runtime ledger 如何作为一个 release group 防漂移。
4. **扩展兼容与冲突**：extension 的 `apiVersion/frameworkRange/capabilities/dependencies`，重复 key、Provider 顺序和循环依赖如何拒绝。
5. **滚动部署兼容**：数据库 expand/contract、旧实例与新 schema 的兼容窗口。
6. **旧项目引导**：Stage C.2 或更早创建的应用没有 managed lock，如何通过一次受控 adoption 建立可信 baseline，不能假装它们由 U01 新创建。
7. **Package manager 副作用**：Composer plugins、npm lifecycle scripts 和 lockfile 变更如何限制在隔离 worktree。
8. **配置与 secrets**：哪些配置进入 project manifest，哪些只能由部署环境提供，诊断输出如何脱敏。
9. **路径与平台**：Windows 大小写、分隔符、symlink、换行和可执行位如何规范化。
10. **版本撤回与最低升级路径**：目标 release 被撤回或必须逐 major 时，plan 如何选择合法路径。

## 总体结论

这份 v2 文档可以作为方向性设计输入，但不能原样转成 U01-U04 实现合同。建议原作者先完成一次 v2.1 修订，至少关闭以下四项：

1. 定义不可变 package/recipe artifact 的发布、摘要、缓存和旧版本获取方式。
2. 把代码升级与数据库部署拆成两个执行阶段，只共享 release identity。
3. 将前端全页面 glob 改成显式 extension manifest，并将 middleware scope 改成 module/route-group ownership。
4. 调整任务顺序为“应用契约 → 创建器 → 代码升级器 → 数据库部署与 DCS handoff”。

完成这些修订后，方案可以进入任务合同起草。此前不建议直接实现当前 `managed-files.lock`、独立 `ServiceContainer` 或一体化 `apply`，否则后续必然返工。
