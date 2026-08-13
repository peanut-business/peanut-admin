---
title: 版本与发布
description: Peanut Admin 应用与两个公开核心包的当前发布事实。
---

# 版本与发布

## 当前结论

Peanut Admin 应用 `1.1.0` 是当前稳定多租户基线。它以同一 release 支持 Standalone 与 `multi-tenant`，数据库迁移账本为 50 条；MT05 已完成 Standalone 空库、`v1.0.0` 前滚、多租户空库和平台到租户的真实浏览器闭环。正式身份为 annotated `v1.1.0` 与同 tag GitHub Release。

## 公开运行依赖

| 生态 | 包 | 应用当前锁定事实 |
|---|---|---|
| Composer | `peanut-admin/core` | `0.1.0-alpha.5`（Packagist） |
| npm | `@peanut-admin/admin` | `0.1.0-alpha.5`（Web/PC/UniApp，npm Registry） |

内部领域目录不是独立包。应用只消费上述两个公开总包；Core/Generator 的公司级固定身份与 DCS Product-only 条件采用边界独立记录，不因应用版本发布而扩大授权。

## 已验证范围

- LikeAdmin 1.9.4 业务 parity、空库安装、路由回归和迁移演练已有封存证据，不在当前阶段重复。
- 管理端 Element Plus、三端 Docker、公开包 registry 安装和生产代表路径已有基线证据。
- PB03–PB07 已固定核心/应用 owner、唯一 Host、测试 owner 和外部渠道停止线。
- PB08A 已完成品牌单一 Runtime、显式初始管理员密码、四端默认品牌、包元数据和官网/文档门户静态门禁；真实浏览器证据并入 PB08B。
- PB08B 已通过公开 registry 构建、弱凭据零写入、24→28 前滚、当前空库、生产 Compose/路由、Host 边界、桌面/移动 Chromium 与文档一致性门禁；脱敏总摘要见 `output/playwright/pb08b/summary.json`。

## 正式发布身份

- [Peanut Admin v1.1.0 GitHub Release](https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.0)
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
- [部署与升级](/deployment)
- [开发指南](/guide/development)
- [许可证与第三方告知](/legal)

[Changelog](/legal/CHANGELOG.txt)、部署升级文档与 GitHub Release 均指向正式 `1.1.0`；完整附件哈希以 Release 的 `RELEASE_MANIFEST.json` 为准。
