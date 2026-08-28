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
| 消费者支持 | [支持与问题提交](/support)、仓库 `SECURITY.md` |

## 公开参考

- [API 与扩展](/api)
- [Module 开发](/guide/module-development)
- [创建应用与交付 Module](/guide/application-module-lifecycle)
- [数据、权限与多租户](/guide/data-permissions-tenancy)
- [支持与问题提交](/support)
- [文档事实来源](/reference/source-map.generated)

## 应用与 Module 命令索引

这是消费者日常使用的唯一索引。ThinkPHP 命令可运行 `php think <command> --help` 查看当前参数；
三个独立脚本按下表固定 usage，不保证实现通用 `--help` 选项。

| 命令 | 用途与当前边界 |
| --- | --- |
| `php scripts/create-app --name=<name> --slug=<slug> --package=<vendor/name> --target=<absolute-path> [--application-version=<semver>] [--profile=minimal\|standard\|full]` | 从当前不可变 scaffold 创建全新应用；目标必须是新的绝对路径 |
| `php think module:create <module.key> [--vendor=<Vendor>]` | 按 Module key 生成唯一 backend/frontend/Tenant 安全骨架；已存在目标时不覆盖 |
| `php think module:check <module.key> [--kernel-version=<semver>] [--package=<tar>] [--sha256=<hash>]` | 作者与自动化共用的八项只读 preflight；不连接数据库 |
| `php think module:pack <module.key> [--output=<tar>] [--signing-key-id=<id> --signing-secret-key-file=<file>]` | 生成确定性自包含 tar 和 SHA-256；可选 Ed25519 签名，私钥不得进入仓库 |
| `php think module:install-package <tar> [--sha256=<hash>] [--signature-key-id=<id>]` | development/debug/Standalone 中验证并安装；对同一不可变制品重复执行是 reactivation，不开通 Tenant 或授权成员 |
| `php think module:update-package <tar> [--sha256=<hash>] [--signature-key-id=<id>] --dry-run` | development/debug/Standalone 中验证并计划同一 Package 的显式更新，产品状态零写入 |
| `php think module:update-package <tar> [--sha256=<hash>] [--signature-key-id=<id>]` | 应用严格更高的不可变版本；不是生产 HTTP 上传入口 |
| `php think module:disable-package <module-or-package-key>` | 保留制品和数据地停用 Package；必须先停用 TenantModule 和依赖者 |
| `php think module:uninstall-package MODULE_OR_PACKAGE_KEY [--purge] [--confirm-plan-file=PLAN_JSON --confirm-plan-digest=PLAN_SHA256]` | 无确认参数时只预览；retire/Purge 执行必须提交同一完整 plan 和摘要 |
| `php think ops-module:request preview\|prepare --delivery-resource-id=<id> --target-resource-id=<id> --operation=update\|retire\|purge --package-key=<key> ...` | deployment owner 在登记受限 inbox 中固定受信包、target 与 retire/Purge 计划；不接收 URL、任意路径或远程命令 |
| `scripts/ops-module-worker --once` | 只接受 `--once`；从 opaque task 领取一次生产交付，串联配对备份、隔离恢复、维护、操作、smoke 和 recovery pointer |
| `php scripts/scaffold-upgrade preflight --project-root=<path> --from-manifest=<path> --to-manifest=<path>` | 生成 managed/generated-managed 升级计划，不覆盖 app-owned 源码 |
| `php scripts/scaffold-upgrade apply\|verify\|recover --project-root=<path> --plan=<path>` | 应用、核验或恢复同一 scaffold 计划；不替代数据库或 Module migration |

生产 HTTP 不能上传 Package、选择本机路径、URL、命令、Release、凭据或部署目标。生产操作只消费
部署 owner 已登记的 target、受限 inbox 和 opaque task；直接 `module:*` Runtime mutation 命令不是
生产控制面。

## 错误输出与恢复

| 入口 | 稳定输出 | 处理 |
| --- | --- | --- |
| `module:check`、`module:update-package`、`module:disable-package` | `code`、`reason`、`remediation`；检查另含 `status/checks` | 修复同一个 code 指向的前置条件；update 必须重新 dry-run |
| `module:create`、`module:install-package`、`module:uninstall-package` | 失败 JSON 的 `error` | 保持当前状态，按 error code 检查目标、信任身份或确认计划 |
| `module:pack` | Package 命令的结构化成功/失败结果 | 不发布失败或摘要不匹配的 archive |
| `scripts/create-app`、`scripts/scaffold-upgrade` | JSON `status`；无效 usage 退出 64 | 不猜参数；按脚本打印的固定 usage 重试 |
| `ops-module:request` | `ok/result` 或 `ok=false,error_code` | 修复登记、inbox、target 或双确认，不改写 opaque task |
| `scripts/ops-module-worker --once` | 单次任务结果写入受控任务状态；stderr 只给安全摘要 | 失败保持维护和恢复指针，由 deployment owner 检查受限日志 |

命令成功退出 0；命令失败非 0。生命周期命令失败后不要直接删除文件、表、lock 或 migration
ledger。先保留 Package/维护状态和恢复指针，再按稳定错误码修复一次并重跑对应 dry-run 或 preview。
完整流程与兼容矩阵见[创建应用与交付 Module](/guide/application-module-lifecycle)。

## 验证

命令、路径或配置被页面引用时，`./scripts/docs-governance check` 会验证关键路径与公共边界；站点构建负责导航和链接。
