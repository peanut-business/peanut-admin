# SaaS Roadmap — 设计资料归档

本目录收录 Peanut Admin **未来升级为多租户 SaaS 底座**的设计资料。这些文档不是当前基础版(对标 LikeAdmin 1.9.4)的一部分,而是下一阶段方向的设计输入。

> 背景:当前产品仓 = 对标 LikeAdmin 标准版的管理端底座(克隆安装即可部署一个应用)。
> 长期方向 = 在此基础上支持 SaaS/多租户模型,并作为 DCS 等下游项目的开发底座。
> 这些设计已统一归档到本目录并跟随产品仓演进；它们只记录未来方向，不代表当前产品能力。

> 包边界说明：本目录中的历史方案可能仍引用 `kernel`、`admin-core`、`admin-shell` 等多包名称。当前有效目标以 `docs/architecture/application-package-and-release-contract.md` 为准：应用只直接安装一个 Composer 核心包和一个 npm 管理端核心包，领域仅作为包内模块存在。

## 目录结构

- `rebuild-design/` — 重构设计全集(文件 28–51)。核心:
  - `29-market-framework-comparison.md` — LikeAdmin SaaS / NiuCloud / ABP 租户模型对比与选型
  - `36-market-feature-comparison-matrix.md` — 含"SaaS 运营和商业控制面"的特性矩阵
  - `37-g01-kernel-data-model.md` — 内核数据模型:Tenant 为 SaaS 隔离根;`pa_tenant`、`pa_platform_operator`、`pa_platform_role`
  - `40-g04-module-runtime-contract.md` — `kernel.tenancy`(Tenant / TenantMember / Tenant Guard)
  - `38/39/43` — 认证会话上下文、授权数据权限、租户隔离安全测试矩阵
  - `46/47/48` — 九角色对抗评审与统一校准(Tenant 作为唯一隔离根)
- `lifecycle/` — 应用生命周期与升级架构迭代(v2.3 / v3.0–v3.3),`archive/` 存早期 v2–v2.2 及 DCS 生命周期原始设计(`2026-07-24-peanut-application-lifecycle-and-upgrade-design.md`),含"克隆→跟随 Peanut Admin 升级"的独立部署模型
- `dcs-integration/` — DCS↔Peanut 集成映射(`I01`)与架构回流校准(`19`)
- `kernel-contracts/` — 早期产品实现仓(`peanut-opensource/peanut-admin`)中的租户内核契约:核心概念、`kernel-schema`(`pa_tenant`/`pa_tenant_member`/`pa_department`/`pa_tenant_module`)、架构、shared-master、typed-targets

## 来源与可回溯

- `rebuild-design/`、`dcs-integration/`、`lifecycle/`:归档来源 commit `be4a6a7`
- `kernel-contracts/`:来自 `peanut-opensource/peanut-admin` 分支 `feature/new-backend-tp8`(commit `90da97b`)

> 注意:这些是**设计资料**,不代表当前基础版已实现租户能力。当前 `server/` 里没有 `pa_tenant` 表、没有平台/租户分层。实现 SaaS 是独立的后续目标。
