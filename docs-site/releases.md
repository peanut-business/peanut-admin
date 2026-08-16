---
title: 版本与发布
description: Peanut Admin 应用与两个公开核心包的当前发布事实。
---

# 版本与发布

## 当前结论

当前源码是 Peanut Admin `2.0.0` fresh-only 开发候选。它从已验证的多租户产品能力继续
演进，但切换为原生 Account/TenantMember/RBAC、canonical Schema 和空库安装；不提供 1.x
数据库或脚手架原地升级。

2.0.0 尚未创建 annotated tag、GitHub Release 或生产部署证明。因此当前状态是“已验证的
开发候选”，不能把它写成正式发布或生产可用版本。

## 公开运行依赖

| 生态 | 包 | 应用当前锁定事实 |
|---|---|---|
| Composer | `peanut-admin/core` | `0.1.0-alpha.5`（Packagist） |
| npm | `@peanut-admin/admin` | `0.1.0-alpha.5`（Web/PC/UniApp，npm Registry） |

内部领域目录不是独立包。应用只消费上述两个公开总包；Core/Generator 的公司级固定身份与 DCS Product-only 条件采用边界独立记录，不因应用版本发布而扩大授权。

## 2.0.0 当前范围

- **已验证的开发候选**：fresh 安装得到 85 表、197 菜单、43 配置；原生 Platform/Tenant 登录、三 Tenant 选择和 Store Demo 真实浏览器通过；管理身份、RBAC、业务会员独立身份与官方能力 Tenant 资格检查通过。
- **不再交付**：legacy Admin/Role/Dept 映射、default bootstrap 状态、兼容余额镜像和 1.x adopt/upgrade Runtime。
- **仍待完成**：create-app 2.0 最终 reseal 与生成应用资格；正式版本元数据、tag、GitHub Release 和生产部署。
- **明确不做**：1.x 升级矩阵、DCS 领域 Runtime、跨应用联邦、完整 SaaS 商业化、生产部署和真实外部渠道验证。

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

[Changelog](/legal/CHANGELOG.txt) 保留 1.x 历史。2.0.0 只有在 create-app 最终 reseal、版本元数据、
tag 与 Release 全部完成后，才能新增正式发布身份。
