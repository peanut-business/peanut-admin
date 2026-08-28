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
| `php think module:check <module.key>` | 作者与自动化共用的只读 preflight；检查 manifest、版本、依赖、权限、菜单、migration、frontend 和 package，不连接数据库 |
| `php think ops-module:request preview/prepare ...` | deployment owner 在登记的受限 inbox 中固定受信包、target 与 retire/Purge 确认计划；不接收 URL 或远程命令 |
| `scripts/ops-module-worker --once` | 从 opaque task 领取一次交付操作，串联配对备份、隔离恢复、维护、操作、smoke 和 recovery pointer；失败保持维护 |

## 验证

命令、路径或配置被页面引用时，`./scripts/docs-governance check` 会验证关键路径与公共边界；站点构建负责导航和链接。
