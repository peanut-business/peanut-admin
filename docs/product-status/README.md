# 产品能力与交付状态

本目录是 Peanut Admin 的**内部交付事实账本**。它回答“当前实际上能做什么、还缺什么、
哪些范围明确暂缓”，不替代架构设计、开发计划、PR 或原始测试证据。

本目录默认不进入 `docs-site` 首页、导航或公开构建。将来是否公开，应由独立的信息披露
决定授权；公开站点不得通过复制本页形成第二份状态事实源。

## 使用规则

- `capability-ledger.json` 是唯一状态事实源；本页状态区由脚本生成，不手工编辑。
- `verified` 表示验收条件已有固定证据，不等于整个产品已经 release-ready。
- 开放 PR、未提交 worktree 和正在运行的 Gate 只能记为 `in_progress`、`implemented` 或
  `blocked`，不能提前记为 `verified`。
- 设计和计划只链接能力 ID；详细设计仍留在 `docs/architecture/`、`docs/plans/` 和
  `docs/design/`。
- CI 日志、截图和报告保留在 Actions、`output/` 或原合同位置，本目录只保存稳定引用。

修改账本后运行：

```bash
php scripts/check-product-capability-ledger --write
php scripts/check-product-capability-ledger
```

## 当前状态

<!-- CAPABILITY_STATUS_GENERATED_START -->
> 总体状态：**进行中**。Peanut Admin 2.0.0 fresh-only 开发实现与必要本地资格已完成；正式双模式 P0-E、tag、GitHub Release 和生产部署尚未执行。
>
> 事实基线：`feat/module-identity-tenancy-docs@60e49921f0081bed2bf426a35f023cd52e235020`，复核日期：`2026-08-16`。

### 已验证可用

| ID | 能力 | 状态 | 当前事实 |
|---|---|---|---|
| `PA-FOUNDATION-001` | 1.x LikeAdmin 标准版基础能力对齐（历史） | 已验证 | 基础后台能力、空库安装、迁移账本和代表路由已有独立验证。 |
| `PA-TENANCY-001` | 1.x 多租户隔离与平台租户治理（历史） | 已验证 | 1.x MT00 至 MT06 已完成并封存；2.0 复用其设计，但原生身份与 fresh Schema 组合仍需当前候选 Gate。 |
| `PA-PRODUCT-001` | 1.x 产品化部署与发布基线（历史） | 已验证 | 生产 Compose、最低 CI、正式部署、法律制品和 v1.1.5 发布链已经封存。 |
| `PA-OWNERSHIP-001` | 核心包与应用唯一实现边界 | 已验证 | 权限、管理员、字典、文件、任务、会员财务、内容装修、通知、支付和 OAuth 已固定应用 Runtime 与核心边界。 |
| `PA-BRAND-001` | 中性品牌与安全安装默认值 | 已验证 | 四端品牌消费、中性安装、显式初始密码和文档门户合同已完成。 |
| `PA-PLUGIN-001` | Plugin 与 Module 生命周期 | 已验证 | 安装、重复安装、升级计划、回滚、TenantModule 授权、失败迁移、禁用和卸载已通过真实数据库 fixture。 |
| `PA-SCAFFOLD-001` | 创建独立应用 | 已验证 | create-app 已具备确定性生成、路径安全、品牌参数化、文件归属和不可变 Release 采用。 |
| `PA-SCAFFOLD-003` | 新应用 Plugin 空锁合同 | 已验证 | 正式生成应用使用有效空 plugins.lock，不再引用仅供源仓测试的 fixture。 |
| `PA-COMPAT-001` | 1.x 公开核心包升级兼容（历史） | 已验证 | 公开 PHP Alpha.2 到 Alpha.5 与 Web Alpha.4 到 Alpha.5 的真实安装、构建、入口和 app-owned 摘要矩阵已通过。 |
| `PA-P0E-001` | P0-E 隔离资源登记 | 已验证 | 项目自有资源登记、原子租约、精确候选绑定和清理释放已在 P0-E 实跑中通过，最终零资源残留且 lease released。 |
| `PA-P0E-002` | 1.x 最终生成应用运行时验收（历史） | 已验证 | 固定候选 8fa274b 的 scaffold v1.1.9 生成全新应用后，干净依赖安装、双模式空库与服务、生产 Compose 和真实浏览器验收均已通过。 |
| `PA-P0E-003` | 1.x 最终升级应用运行时验收（历史） | 已验证 | 固定旧应用完成十段 scaffold preflight/apply/verify、故障恢复、五套依赖安装构建、729 个 app-owned 文件逐字节保持，并通过升级后 Plugin、Compose 与双模式真实浏览器复验。 |
| `PA-DELIVERY-002` | 1.x 正式发布与生产证明（历史） | 已验证 | v1.1.5 已完成 P0-E 16/16、dev/main、annotated tag、GitHub Release、配对生产备份、54 条迁移和最低登录/API/核心页/TLS/demo smoke，达到 production-demonstrated。 |

### 已实现或正在验收

| ID | 能力 | 状态 | 当前事实 |
|---|---|---|---|
| `PA-NATIVE-001` | 2.0.0 原生身份与干净安装基线 | 已实现，待验收 | 原生管理身份、独立业务会员、canonical fresh Schema、官方能力 Tenant 资格和 create-app 2.0 生成身份已完成；登记多租户空库与真实浏览器通过，正式双模式发布 Gate 未执行。 |

### 暂缓或范围外

| ID | 能力 | 状态 | 当前事实 |
|---|---|---|---|
| `PA-SCAFFOLD-002` | 1.x 脚手架跨版本升级（历史） | 已退出 | 1.x preflight/apply/verify/recover 证据已封存；2.0 当前 inventory 和生成应用不再携带该 Runtime。 |
| `PA-GOVERNANCE-001` | 外部 CompanyOS 治理流程 | 已退出 | Peanut Admin 已退出 CompanyOS 资源与治理依赖；项目资源、GitHub PR 和验收事实均由本仓库维护。 |
| `PA-SAAS-001` | 完整 SaaS 商业化 | 暂缓 | 商业套餐、计费、完整运营与商业控制面不属于当前独立应用交付目标。 |
| `PA-OPS-001` | 跨应用实例运营平台 | 范围外 | 跨实例 Release、授权、升级、健康与备份平台应作为独立应用，不进入 Peanut Admin 核心包或 SaaS Host。 |
| `PA-UPGRADE-001` | 自动重构或静默覆盖应用业务源码 | 范围外 | 升级允许替换已登记的框架管理文件和依赖，但不得自动改写 app-owned 业务代码。 |
| `PA-RELEASE-001` | 发布预构建生产镜像 | 范围外 | 当前生产策略从不可变源码 Tag 在部署端构建，不发布预构建运行镜像。 |
<!-- CAPABILITY_STATUS_GENERATED_END -->

## 文件职责

| 文件 | 职责 |
|---|---|
| `capability-ledger.json` | 唯一机器可读事实源 |
| `schema/capability-ledger.schema.json` | 编辑器和外部工具可用的结构合同 |
| `acceptance-gates.md` | 状态含义和完成判定 |
| `evidence-guide.md` | 有效证据、身份和新鲜度规则 |
| `deferred-scope.md` | 暂缓、范围外和恢复规则 |
| `releases/` | 正式产品发布时冻结的不可变能力快照 |
