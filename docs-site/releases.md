---
title: 版本与发布
description: Peanut Admin 应用与两个公开核心包的当前发布事实。
---

# 版本与发布

## 当前结论

Peanut Admin `2.0.0` 与 `2.0.1` 建立历史 fresh-only 正式源码基线。当前 `3.0.0` 是统一版本合同下的 fresh-only 发布候选，仍需完成固定资格后才可称为正式版本。版本使用原生
Account/TenantMember/RBAC、canonical Schema 和空库安装；不提供 1.x 数据库或脚手架原地升级。

annotated tag [`v2.0.0`](https://github.com/peanut-business/peanut-admin/releases/tag/v2.0.0)
指向 `main@ec4ac732a498afe5e02eafb4a4855ec1f52aa68d`，固定候选 P0-E 7/7 通过。GitHub Release
包含确定性源码包、外部 manifest、许可证、NOTICE、第三方告知和 SPDX SBOM。隔离的
`production-candidate` 体验环境仍不是正式生产部署；2.0.0 线上部署与 smoke 留给独立工作流。

annotated tag [`v2.0.1`](https://github.com/peanut-business/peanut-admin/releases/tag/v2.0.1)
指向 `main@1c8aff4f1f19069bfe4480a8b29d59414d5c6816`，固定候选 `9ffffed3…` 的 P0-E 7/7
通过，且 `v2.0.0 -> v2.0.1` 派生应用升级资格通过。线上 Standalone/Multi-tenant 部署和
Article 页面专项资格已由独立工作流验证。

annotated tag [`v2.1.0`](https://github.com/peanut-business/peanut-admin/releases/tag/v2.1.0)
交付 7 个官方可选 Module、统一执行 Guard、Module-owned 路由和权限所有权追加迁移。
该源码版本误改了 canonical `init.sql` 校验值，因此不能从 `v2.0.1` 原地升级；不可变
Release 保留为历史证据，不覆盖或重写。

`v2.1.4` 保持 2.0.1 的 canonical 基线，并通过追加 migration 更新 Module 权限归属。
`v2.1.5` 在同一 fresh-only 线上完成 P0-E 7/7、`v2.1.4 -> v2.1.5` 配对备份升级、
多租户 Demo fresh 部署和 post-deployment 快照；不可变事实见
`docs/product-status/releases/v2.1.5.json`。当前 `v3.0.0` 候选不会改写上述历史身份。

## 公开运行依赖

| 生态 | 包 | 应用当前锁定事实 |
|---|---|---|
| Composer | `peanut-admin/core` | `0.1.0-alpha.5`（Packagist） |
| npm | `@peanut-admin/admin` | `0.1.0-alpha.5`（Web/PC/UniApp，npm Registry） |

内部领域目录不是独立包。应用只消费上述两个公开总包；Core/Generator 的公司级固定身份与 DCS Product-only 条件采用边界独立记录，不因应用版本发布而扩大授权。

## 2.x 发布范围

- **已发布且已验证**：fresh 安装得到 87 表、197 菜单、43 配置；原生 Platform/Tenant 登录、三 Tenant 选择和 Store Demo 真实浏览器通过；管理身份、RBAC、业务会员独立身份与官方能力 Tenant 资格检查通过。
- **不再交付**：legacy Admin/Role/Dept 映射、default bootstrap 状态、兼容余额镜像和 1.x adopt/upgrade Runtime。
- **已完成的生成边界**：create-app 2.0 inventory 与 `scaffold/releases/v2.0.0` 已按 fresh-only
  Runtime 重封，生成应用身份由确定性检查验证。
- **发布制品**：规范源码包 SHA-256 为 `af9fc2f17d403faf8a3c461f7eb759f76e10260bfd1e0a8c4a9e1983eb53aa3c`；外部 `RELEASE_MANIFEST.json` SHA-256 为 `508fc3781242cc17fe63cca3ecbeffa9b67ebe67e0b225965515848c762d5844`。
- **CI 例外**：PR #148/#149 的 GitHub Actions 因账户配额/付款限制未执行实际任务；用户明确批准以固定候选 P0-E 7/7 结果绕过。该事实不是 CI 通过。
- **独立执行**：从 `v2.1.4` 正式 Release 的 Standalone/Multi-tenant 部署、备份、迁移和线上 smoke 已完成；它们不属于源码 Release 本身，由 `docs/product-status/deployments/v2.1.4-online-experience.json` 快照证明。
- **明确不做**：1.x 升级矩阵、DCS 领域 Runtime、跨应用联邦、完整 SaaS 商业化和真实外部渠道验证。

## 1.x 历史发布身份

- [Peanut Admin v1.1.5 GitHub Release](https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.5)
- `main` commit：`c231528a6d30691c40807c6a660f5dbdefdcbd5e`；annotated tag object：`0be47d4cadc51dbc643e88f5d4a574d9e1534ef0`。
- 规范源码附件 SHA-256：`3a0158b35796e74f8d7513af4246d8495ade59fbded71459d0f6cd34c1d67cfd`；外部 `RELEASE_MANIFEST.json` SHA-256：`e715a357afa5d95e702763346728e20d905520b5b02f189dc4fa9a52dc0ffabc`。
- P0-E `p0ev119a1` 在固定候选 `8fa274b2f91f5f482a361ee137302fa3438b8a11` 上通过 16/16 组，脚手架身份为 `v1.1.9`。
- 生产最低 smoke 已验证管理员登录、`1.1.5` 版本显示、文档链接、健康页和 demo 数据。

- [Peanut Admin v1.1.0 GitHub Release](https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.0)（历史稳定版本）
- `main/dev` commit：`c6a165fbc223bcca1332235d3a31c9d2ede55a06`；annotated tag object：`0f4fffd731cbcb632f9fb6b293e31671857410a5`。
- 规范源码附件 SHA-256：`73398b2504ad7b41f759f5593efd32a91df56fd4e2ed06d1ffa4af9c84a36334`；外部 `RELEASE_MANIFEST.json` SHA-256：`7edd5b2e3baaae06d657fc45856633b8f27ad97fe5669fd2c9642587313fa0a9`。
- 发布后一次干净验证从规范归档精确安装 Composer/npm Alpha.5、加载 Core，并确认 `1.1.0`/50 migrations 源码身份。
- 应用暂时采用专有 / All Rights Reserved，版权主体显示为“花生科技”；Release 同时附带根许可证、NOTICE、第三方清单和 SPDX SBOM。
- 功能分支 PR #10 的五组 CI 一次通过；`dev` → `main` 阶段 PR #11 的分支保护检查也全部通过。
- 应用仓当前保持 private；GitHub Release 链接对已获授权的 GitHub 身份可见，匿名访客会得到 404。官网、应用和法律资产本身不依赖该登录态。
- 真实短信、支付、微信/OAuth 凭据和平台登记只在对应部署 smoke 后才能宣称生产可用。
- 本 release 包含稳定多租户脚手架，但不包含订阅计费或跨实例运营平台。

## 获取源码与变更

- [GitHub 仓库](https://github.com/peanut-business/peanut-admin)
- [部署与安装](/deployment)
- [开发指南](/guide/development)
- [许可证与第三方告知](/legal)

[Changelog](/legal/CHANGELOG.txt) 同时记录 2.x 与 1.x 历史。发布快照见仓库内
`docs/product-status/releases/`。
