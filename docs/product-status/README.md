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
> 总体状态：**进行中**。Peanut Admin v1.1.5 生产镜像与演示数据命令修复已进入发布准备；scaffold v1.1.8 不可变身份、最终 P0-E、dev/main、Tag、GitHub Release 与生产验证尚未完成。
>
> 事实基线：`feat/v1.1.5-production-seeder@b6495f90b713f2483de32006d529c168ca656d0f`，复核日期：`2026-08-15`。

### 已验证可用

| ID | 能力 | 状态 | 当前事实 |
|---|---|---|---|
| `PA-FOUNDATION-001` | LikeAdmin 标准版基础能力对齐 | 已验证 | 基础后台能力、空库安装、迁移账本和代表路由已有独立验证。 |
| `PA-TENANCY-001` | 多租户隔离与平台租户治理 | 已验证 | MT00 至 MT06 已完成，覆盖 TenantContext、数据隔离、平台 operator、TenantModule 和双部署模式。 |
| `PA-PRODUCT-001` | 产品化部署与发布基线 | 已验证 | 生产 Compose、最低 CI、正式部署、法律制品和 v1.0.0 发布链已经封存。 |
| `PA-OWNERSHIP-001` | 核心包与应用唯一实现边界 | 已验证 | 权限、管理员、字典、文件、任务、会员财务、内容装修、通知、支付和 OAuth 已固定应用 Runtime 与核心边界。 |
| `PA-BRAND-001` | 中性品牌与安全安装默认值 | 已验证 | 四端品牌消费、中性安装、显式初始密码和文档门户合同已完成。 |
| `PA-PLUGIN-001` | Plugin 与 Module 生命周期 | 已验证 | 安装、重复安装、升级计划、回滚、TenantModule 授权、失败迁移、禁用和卸载已通过真实数据库 fixture。 |
| `PA-SCAFFOLD-001` | 创建独立应用 | 已验证 | create-app 已具备确定性生成、路径安全、品牌参数化、文件归属和不可变 Release 采用。 |
| `PA-SCAFFOLD-002` | 安全的脚手架跨版本升级 | 已验证 | preflight、apply、verify、recover/rollback 已形成可执行闭环，默认保留 app-owned 文件。 |
| `PA-SCAFFOLD-003` | 新应用 Plugin 空锁合同 | 已验证 | 正式生成应用使用有效空 plugins.lock，不再引用仅供源仓测试的 fixture。 |
| `PA-COMPAT-001` | 公开核心包升级不要求应用源码重构 | 已验证 | 公开 PHP Alpha.2 到 Alpha.5 与 Web Alpha.4 到 Alpha.5 的真实安装、构建、入口和 app-owned 摘要矩阵已通过。 |
| `PA-P0E-001` | P0-E 隔离资源登记 | 已验证 | 项目自有资源登记、原子租约、精确候选绑定和清理释放已在 P0-E 实跑中通过，最终零资源残留且 lease released。 |
| `PA-P0E-002` | 最终生成应用运行时验收 | 已验证 | 固定候选生成全新应用后，干净依赖安装、双模式空库与服务、生产 Compose 和真实浏览器验收均已通过。 |
| `PA-P0E-003` | 最终升级应用运行时验收 | 已验证 | 固定旧应用完成六段 scaffold preflight/apply/verify、故障恢复、五套依赖安装构建、729 个 app-owned 文件逐字节保持，并通过升级后 Plugin、Compose 与双模式真实浏览器复验。 |

### 已实现或正在验收

| ID | 能力 | 状态 | 当前事实 |
|---|---|---|---|
| `PA-DELIVERY-002` | 当前能力进入正式发布分支 | 进行中 | v1.1.5 已完成生产镜像与 seeder 源码修复，正在准备 scaffold v1.1.8 和发布元数据；最终 P0-E、合入、Tag/Release 与生产 smoke 尚未执行。 |

### 暂缓或范围外

| ID | 能力 | 状态 | 当前事实 |
|---|---|---|---|
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
