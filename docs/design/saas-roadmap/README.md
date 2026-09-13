# SaaS Roadmap — 设计资料归档

> 当前权威摘要：[`../saas-enhancement-blueprint.md`](../saas-enhancement-blueprint.md)
> 当前实施顺序：[`../../plans/multi-tenancy-platform-management-plan.md`](../../plans/multi-tenancy-platform-management-plan.md)
> 完整 SaaS 未来规划：[`../../plans/saas-enhancement-development-plan.md`](../../plans/saas-enhancement-development-plan.md)

本目录只保留尚有独有未来设计价值的 Peanut Admin **多租户 SaaS 底座**材料。它们不是当前基础版的一部分，而是下一阶段的设计输入。

> 背景:当前产品仓 = 对标 LikeAdmin 标准版的管理端底座(克隆安装即可部署一个应用)。
> 长期方向 = 在此基础上支持 SaaS/多租户模型,并作为 DCS 等下游项目的开发底座。
> 这些设计已统一归档到本目录并跟随产品仓演进；它们只记录未来方向，不代表当前产品能力。

> 包边界说明：本目录中的历史方案可能仍引用 `kernel`、`admin-core`、`admin-shell` 等多包名称。当前有效目标以 Application/Core 的现有依赖 manifest、[版本身份 ADR](../../architecture/product-version-identity-adr.md) 和 [Core / Application 技术边界](../../architecture/core-application-technical-boundary.md) 为准：应用只直接安装一个 Composer 核心包和一个 npm 管理端核心包，领域仅作为包内模块存在。

## 目录结构

- `dcs-integration/` — DCS↔Peanut 的正式集成映射（`I01`）
- `kernel-contracts/` — 早期产品实现仓(`peanut-opensource/peanut-admin`)中的租户内核契约:核心概念、`kernel-schema`(`pa_tenant`/`pa_tenant_member`/`pa_department`/`pa_tenant_module`)、架构、shared-master、typed-targets

## 来源与可回溯

- `dcs-integration/`:归档来源 commit `be4a6a7`
- `kernel-contracts/`:来自 `peanut-opensource/peanut-admin` 分支 `feature/new-backend-tp8`(commit `90da97b`)

> 注意:这些是**设计资料**,不代表当前基础版已实现租户能力。当前 `server/` 里没有 `pa_tenant` 表、没有平台/租户分层。实现 SaaS 是独立的后续目标。

> 运营平台边界：跨应用实例管理 Release、版本、授权、升级、健康和备份的运营平台是**独立应用**，不属于核心包，也不属于某个 SaaS Host 的平台后台。SaaS Host 内的 `PlatformOperator` 只治理本实例 Tenant，不能成为跨实例或跨租户超级管理员。
