---
title: Demo 登录信息
description: Peanut Admin 当前本地与线上演示环境的登录入口和公开演示账号。
---

# Demo 登录信息

本页是本地与线上演示环境登录信息的唯一文档事实源。表中的账号和密码都是 owner 明确批准的
可丢弃演示凭据，不得用于正式业务数据或生产管理员账号。普通应用安装仍必须显式设置自己的
强密码；`peanut1234` 只在演示模式（`PEANUT_DEMO_MODE=enabled`）中使用。

## 本地

本地单租户使用登记资源 `peanut-admin-local-production-preview-gateway`，当前运行模式为
`standalone`，入口是生产模式预览网关。多租户体验使用独立的本地 demo 资源和租约。

| 项目 | 登录地址 | 账号 | 密码 |
| --- | --- | --- | --- |
| 单租户管理端 | `http://127.0.0.1:20190/admin/` | `admin@example.com` | `peanut1234` |
| 多租户 Platform | `http://platform.peanut-admin.test:20176/platform/` | `platform@local.test` | `peanut1234` |
| 多租户公共管理端 | `http://admin.peanut-admin.test:20179/admin/` | `owner@local.test` | `peanut1234` |
| 多租户 Tenant A | `http://tenant-a.peanut-admin.test:20179/admin/` | `tenant-a@local.test` | `peanut1234` |
| 多租户 Tenant B | `http://tenant-b.peanut-admin.test:20179/admin/` | `tenant-b@local.test` | `peanut1234` |

单租户入口最近一次健康检查和 API 登录验证：2026-08-22。多租户入口及其域名、端口、数据库和
租约要求见仓库内的[本地 Demo 运行说明](https://github.com/peanut-business/peanut-admin/blob/dev/docs/operations/local-demo-access.md)。

## 线上

线上表包含一个多租户 production-candidate 和一个 Standalone 演示部署。多租户入口的关键
写操作在 Demo 模式下由服务端拒绝；Standalone 同样是可丢弃演示实例。

| 项目 | 登录地址 | 账号 | 密码 |
| --- | --- | --- | --- |
| 多租户 Platform | `https://pa-platform.007345.xyz/platform/` | `platform@pa-demo.example` | `peanut1234` |
| 多租户 bootstrap 管理端 | `https://pa-admin.007345.xyz/admin/` | `admin@pa-demo.example` | `peanut1234` |
| 多租户公共管理端 | `https://pa-admin.007345.xyz/admin/` | `tenant-a@pa-demo.example` | `peanut1234` |
| 多租户 Tenant A | `https://pa-tenant-a.007345.xyz/admin/` | `tenant-a@pa-demo.example` | `peanut1234` |
| 多租户 Tenant B | `https://pa-tenant-b.007345.xyz/admin/` | `tenant-b@pa-demo.example` | `peanut1234` |
| Standalone 管理端 | `https://peanut-admin.007345.xyz/admin/` | `admin@peanut-admin.007345.xyz` | `peanut1234` |

线上账号最近一次真实浏览器登录验证：2026-08-20。PC、H5 和文档站不需要管理端登录：

- PC：<https://peanut-admin.007345.xyz/pc/>
- H5：<https://peanut-admin.007345.xyz/mobile/>
- 文档站：<https://peanut-admin-doc.007345.xyz>

资源登记仍保留同一组公开演示账号的机器可读条目，用于部署健康检查和资格工具；密码轮换时，
必须同时更新资源登记和本页，并重新完成对应环境的登录验证。
