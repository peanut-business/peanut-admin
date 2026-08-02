# 原始目标覆盖审计与剩余确认

> 状态说明（2026-07-18）：本文的目标覆盖和架构审计继续有效，但 Runtime 进度段落已经被真实仓库 P0 internal-alpha 资格取代。当前实现状态见 `../../repositories/peanut-admin/docs/status/index.md`，不得使用下文旧修复状态判断能否固定消费。

> 审计日期：2026-07-17
>
> 结论：方向、仓库和原 A01-D04 已执行；第二波 Runtime 审查已由用户确认，当前进入 `PA-P0-R00` 至 `PA-P0-R07` 修复序列。旧 D04、当前修复分支和未提交 R06 都不是合格 Runtime，详见 51 号。

## 1. 原始目标逐项审计

| 原始要求 | 当前结论或证据 | 状态 |
| --- | --- | --- |
| 名称改为 Peanut Admin | company-os 目录已改为 `platform/peanut-admin`，品牌和 GitHub 组织记录为 `peanut-opensource` | 已完成 |
| 本地真实代码目录改为 `peanut-admin` | 已迁移到 `/Users/xing/Documents/company-os/repositories/peanut-admin`，保持独立 Git 历史 | 已完成 |
| 判断迁移旧仓还是从零开始 | 新建干净 Git 历史；旧仓冻结、做资产清单后保留为 legacy | 已确认 |
| Peanut Admin 在 GitHub 开源 | 公开仓已建立为 `peanut-opensource/peanut-admin`；私有产品继续使用私有仓 | 已完成 |
| 分清前端、后端、前端核心包、后端核心包 | 一个 monorepo 内使用 `frontend/`、`backend/`、`packages/web/`、`packages/php/` 四个边界 | 已确认 |
| 核心包可被其他项目复用 | 包从第一天有独立 manifest/public API；P0 用 workspace/path，P1 发布 Composer/npm | 已形成方案 |
| 代码是否放在 company-os | company-os 父仓不提交业务代码；独立仓统一检出到被父仓忽略的 `repositories/`，由 `repos.md` 映射 | 已按中央工作区决策更新 |
| 解释 Application、Entry、Module、Plugin、Package | P0 不建立 Application/Entry；区分 Kernel、Module、Plugin、Package、Client、ProductProfile | 已确认 |
| 参考主流框架 | 已对照 LikeAdmin PHP/SaaS、NiuCloud、MineAdmin、ng-alain、Vben、ThinkPHP、ABP | 已完成 |
| 完整功能目录和取舍 | 已形成 P0 安全内核、P1 市场可用后台、P2 生态/运营和不纳入项 | 已确认 |
| 支持多租户和租户内多类别、多对象 | Tenant 是隔离根；一个 Tenant 可有多个门店、仓库、供应商等；成员可按资源和操作管理一个或多个同类目标 | 已校准并复审 |
| 门店系统和仓储系统相对独立 | 作为不同 ProductProfile/Client，可分别部署界面，复用商品/库存 Module | 已确认 |
| 数据共用同一数据库 | P0 共享库共享表并强制 `tenant_id`；模块禁止直接跨边界读写/JOIN | 已确认 |
| 未确认业务逻辑前不编码 | AGENTS、README、32、33 均设停止线；旧运行时计划已标历史 | 已完成并持续生效 |
| 建立最终脚手架方案目录 | `platform/peanut-admin/28` 至 `51` 形成架构、执行和 Runtime 修复事实链 | 已完成并持续校准 |

## 2. 已确认的四组方向

虽然 `32` 有 16 个决策项，实际可以按四组理解：

### A. 旧仓与新仓

接受 D-01、D-13：旧仓不直接改名公开；先冻结和审计，新建干净 Peanut Admin 仓。

### B. 项目与包组织

接受 D-02、D-10、D-11、D-14：第一阶段一个公开 monorepo，四个项目边界；私有业务不进入开源组织；Plugin 后置。

### C. 租户、模块和业务对象

接受 D-03 至 D-09、D-15、D-16：唯一 Tenant 根，一个租户管理多个对象；Module 是业务能力所有者；ProductProfile 负责装配；共享库强隔离；平台操作员和租户成员分开。

### D. 第一版推进范围

接受 D-12：P0 只证明安全内核和端到端扩展链路，P1 再补成 LikeAdmin 级可用后台；方向确认后仍先完成 G-01 至 G-09，不能直接编码。

## 3. 当前真实执行顺序

1. 47-50 的方向、放行和 A01：已完成。
2. 原 G-09 A02 至旧 D04：已形成提交，`dev/origin-dev` 当前为 `f351a21`。
3. 第二波 Runtime 审查与五项用户确认：已完成。
4. 用本 Patch、51 号和新 Decision 执行 R00 正式校准：进行中。
5. 在当前修复分支依次关闭 R01-R07；已有提交只能按新验收回收。
6. R07 后重新执行 D02、D03、D04。
7. 固定新 D04 commit，执行 D05 九角色 Runtime 终审。
8. D05 通过后，才可选择一个固定 commit 作为 DCS 集成映射和消费候选。

## 4. 当前不能声称完成的事项

- 不能声称 `f351a21` 是合格 P0 Runtime 或 DCS 可消费 commit。
- 不能声称修复分支或未提交 R06 工作已经完成、可合并或可发布。
- 不能声称所有 OpenAPI operation 都有 P0 可用 handler；必须服从 R00 分类和 R03/R05 证据。
- 不能声称 fixture 浏览器测试等于真实全栈 E2E。
- 不能声称内部 starter、稳定生成器或公开 Package 已交付。
- 不能声称正式 `I01-dcs-integration-mapping.md` 已存在。

当前已有大量可回收实现，但新 Runtime 资格仍未完成。仓库检查和干净提交是完成证据，文档或 commit 标题不能单独升级状态。
