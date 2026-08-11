---
title: 版本与发布
description: Peanut Admin 应用与两个公开核心包的当前发布事实。
---

# 版本与发布

## 当前结论

Peanut Admin 应用 `1.0.0` 已正式发布。annotated tag `v1.0.0` 指向 `main` 提交 `0d3c848b8e2bb622a868924145ce810a8946f173`，同 tag GitHub Release 于 2026-08-11 发布；既有应用与官网域名的正式部署和一次最低 smoke 仍在执行。

## 公开运行依赖

| 生态 | 包 | 应用当前锁定事实 |
|---|---|---|
| Composer | `peanut-admin/core` | `0.1.0-alpha.2` |
| npm | `@peanut-admin/admin` | 管理端/PC 为 Alpha.3，UniApp 为 Alpha.4 |

内部领域目录不是独立包。核心本地 `dev` 带 `v0.1.0-alpha.4` tag，UniApp lock 已解析 npm Alpha.4；核心状态文档与发布事实仍有待独立核心任务收敛的差异，因此这里不推断新的采用授权。

## 已验证范围

- LikeAdmin 1.9.4 业务 parity、空库安装、路由回归和迁移演练已有封存证据，不在当前阶段重复。
- 管理端 Element Plus、三端 Docker、公开包 registry 安装和生产代表路径已有基线证据。
- PB03–PB07 已固定核心/应用 owner、唯一 Host、测试 owner 和外部渠道停止线。
- PB08A 已完成品牌单一 Runtime、显式初始管理员密码、四端默认品牌、包元数据和官网/文档门户静态门禁；真实浏览器证据并入 PB08B。
- PB08B 已通过公开 registry 构建、弱凭据零写入、24→28 前滚、当前空库、生产 Compose/路由、Host 边界、桌面/移动 Chromium 与文档一致性门禁；脱敏总摘要见 `output/playwright/pb08b/summary.json`。

## 正式发布身份

- [Peanut Admin v1.0.0 GitHub Release](https://github.com/peanut-business/peanut-admin/releases/tag/v1.0.0)
- 规范源码附件 `peanut-admin-1.0.0.tar.gz` SHA-256：`069a34f98db1d604ddc64a342a10e17a81db450094d303db455a8b32ae114847`
- 外部 `RELEASE_MANIFEST.json` SHA-256：`616fcd7dfd2edcebe8773f6860493c4fdfb912cc3cdfb4373c39f85972419989`
- 应用暂时采用专有 / All Rights Reserved，版权主体显示为“花生科技”；Release 同时附带根许可证、NOTICE、第三方清单和 SPDX SBOM。
- 功能分支 PR #10 的五组 CI 一次通过；`dev` → `main` 阶段 PR #11 的分支保护检查也全部通过。
- 真实短信、支付、微信/OAuth 凭据和平台登记只在对应部署 smoke 后才能宣称生产可用。
- SaaS/多租户是产品化正式基线后的独立阶段，本 release 不包含。

## 获取源码与变更

- [GitHub 仓库](https://github.com/peanut-business/peanut-admin)
- [部署与升级](/deployment)
- [开发指南](/guide/development)
- [许可证与第三方告知](/legal)

[Changelog](/legal/CHANGELOG.txt)、部署升级文档与 GitHub Release 均指向正式 `1.0.0`；完整附件哈希以 Release 的 `RELEASE_MANIFEST.json` 为准。
