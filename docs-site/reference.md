---
title: 参考入口
description: Peanut Admin API、命令、配置、manifest 和扩展点的事实位置。
---

# 参考入口

## 前置条件

先确认你在修改 Application、Core 还是派生应用。

## 上游位置

| 要查什么 | 从哪里开始 |
| --- | --- |
| HTTP API | `server/route/`、对应 controller、`docs/api/openapi.yaml` |
| CLI 命令 | `scripts/` 或后端 command，运行 `--help` |
| 后端配置 | `server/.env.example` 和配置加载器 |
| 本地编排 | `.env.example`、`scripts/local-stack.sh` |
| Module | 对应 `module.json`、bootstrap 与公开合同 |
| Schema / migration | Core KernelSchema、`server/database/init.sql`、当前增量 migration |
| 资源与端口（仓库维护） | 项目资源登记；不从公开页面猜实际地址 |
| 能力与发布状态（仓库维护） | 内部机器账本与不可变快照；不从营销页推断 |
| 应用升级执行 | Platform Ops API、`scripts/ops-upgrade-worker --once` 与部署方自己的资源登记 |

## 公开参考

- [API 与扩展](/api)
- [Module 开发](/guide/module-development)
- [数据、权限与多租户](/guide/data-permissions-tenancy)
- [文档事实来源](/reference/source-map.generated)

## Module Package 命令

| 命令 | 当前边界 |
| --- | --- |
| `php think module:update-package <path> --sha256=<hash> --dry-run` | development/debug/Standalone 中验证并计划同一 Package 的显式更新，产品状态零写入 |
| `php think module:update-package <path> --sha256=<hash>` | development/debug/Standalone 中应用更高不可变版本；不是生产 HTTP 上传入口 |

## 验证

命令、路径或配置被页面引用时，`./scripts/docs-governance check` 会验证关键路径与公共边界；站点构建负责导航和链接。
