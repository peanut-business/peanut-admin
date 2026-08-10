---
title: 版本与发布
description: Peanut Admin 应用与两个公开核心包的当前发布事实。
---

# 版本与发布

## 当前结论

Peanut Admin 正在完成产品化正式基线，尚未发布应用 `1.0.0`。PB03–PB07 领域收口、PB08A 品牌/脚手架/官网门户以及 PB08B 正式候选集成验收已经完成；许可证/provenance 门禁与 PB09 发布仍未执行。

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

## 尚未完成

- PB09：先完成应用许可证/provenance、根 `LICENSE`、`NOTICE` 与第三方清单的明确决策，再执行 `dev` 合入/推送 `main` 和版本发布记录。
- 真实短信、支付、微信/OAuth 凭据和平台登记只在对应部署 smoke 后才能宣称生产可用。
- SaaS/多租户是 PB09 后的独立阶段。

## 获取源码与变更

- [GitHub 仓库](https://github.com/peanut-business/peanut-admin)
- [部署与升级](/deployment)
- [开发指南](/guide/development)

正式 release/changelog 入口将在 PB09 创建；在此之前不使用虚构版本号或发布日期。
