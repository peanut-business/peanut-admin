---
title: 开发总览
description: Peanut Admin Application、Core、Module 与各客户端的开发边界。
---

# 开发总览

## 前置条件

已完成[快速开始](/getting-started)，并能说明本次修改属于 Application、Core 还是一个 Module。

## 目标

把改动放在唯一 owner 下，更新最小合同和文档投影，并只验证受影响产品。

## 选择代码边界

| 变化 | 首选位置 | 不应放置 |
| --- | --- | --- |
| 可复用身份、权限、Tenant、Module Runtime 合同 | 固定版本的 Core 公共包 | Application 里的并行基础设施 |
| Peanut Admin 产品能力 | `server/app/Modules/` 或 Application Host | Core 的产品中性包 |
| 管理端交互 | `web/`；平台控制面在 `platform/` | 后端 DTO 中的 UI 状态 |
| PC / H5 / 小程序 | `pc/`、`uniapp/` | 复制一套不一致的后端规则 |
| 数据 owner | 对应 Module manifest、Schema/迁移与服务合同 | 跨 Module 直接写对方表 |

Application 只消费公开的 Core 包边界。不要修改 `vendor/` 或 `node_modules/`；需要 Core 变更时，在 Core 仓独立提交并让 Application 固定采用已接受身份。

## 开发闭环

1. 找到 manifest、Schema、route、配置或其他权威上游。
2. 修改最小代码和合同。
3. 按文档影响图选择 `none`、`technical`、`developer-site`、`generated` 或 `architecture-decision`。
4. 只更新受影响的解释和公开投影。
5. 运行受影响产品的静态检查、聚焦测试或构建；不要因为改了后端而构建所有客户端。

## 验证

```bash
./scripts/docs-governance impact --base origin/dev
./scripts/docs-governance check
git diff --check
```

代码检查以仓库执行规则和受影响产品脚本为准。文档命令不会启动数据库或服务。

## 下一步

- [后端开发](/guide/backend)
- [前端开发](/guide/frontend)
- [Module 开发](/guide/module-development)
- [数据、权限与多租户](/guide/data-permissions-tenancy)
