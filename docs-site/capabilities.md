---
title: 产品能力目录
description: Peanut Admin 面向安装、运维和二次开发的稳定能力、作用与边界。
---

# 产品能力目录

Peanut Admin 的目标是让新应用可安装、可诊断、可备份、可恢复、可升级、可扩展，并让
二次开发者能快速找到能力 owner。此页只解释稳定能力和使用边界，不公开内部候选、资源地址
或执行状态；具体版本身份以[版本与发布](/releases)为准。

## 产品闭环

| 能力 | 作用 | 使用入口 | 关键边界 |
|---|---|---|---|
| 安装预检 | 在写数据库前检查 PHP、扩展、目录、配置和部署模式，并给出修复建议 | CLI preflight、安装向导 | 不猜主机、端口或默认凭据；预检不写数据库 |
| 一次性安装向导 | 以同一安装 Host 完成模式、管理员和官方 Module 选择 | `/admin/installation`、automatic CLI | 页面不接收数据库连接、路径或命令；setup token 一次性使用 |
| 首次运行清单 | 说明品牌、通知、存储、备份、Worker、域名/TLS 和账户安全是否具备生产条件 | `/app-setting/readiness` | “已配置”不等于“已连通”或“生产可用” |
| 运行与维护控制台 | 汇总版本、Schema/migration、Module、缓存、存储、任务和维护状态 | Platform `/platform/ops` | Platform-only；关键检查异常时 fail closed |
| 脱敏诊断包 | 导出固定 schema、checksum 和有界时间窗口的安全诊断信息 | Platform 运行与维护 | 不读取任意文件、原始日志、Cookie、凭据、个人或 Tenant 业务记录 |
| 配对备份中心 | 将数据库、持久文件、Runtime 身份、manifest 和 SHA-256 绑定为一个恢复点 | Platform 备份中心、受信 worker | HTTP 不接收 shell/path；备份完成不等于恢复已验证 |
| 隔离恢复验证 | 把配对制品恢复到登记的新目标并验证 Schema、代表数据、文件和零流量 | 受信 restore worker | 默认不覆盖活动目标；生产覆盖恢复需单独授权 |
| 维护窗口 | 按 reason、revision 和时间范围统一阻止后端写入并记录审计 | Platform 运行与维护 | 隐藏页面不能替代后端写门禁 |
| 升级就绪与执行 | 固定 source/target、Module、migration、scaffold 冲突、备份、维护和恢复指针，再按步骤执行 | Platform 升级中心、deployment-control worker | HTTP 不选择路径、命令、Release 或部署目标；跨大版本仍采用 fresh/rebuild |
| Provider 资格 | 区分配置、连通、回调、凭据轮换、失败和证据新鲜度 | Platform Provider 资格视图 | 通用页面只读且不发送短信、不扣款；每个 Provider 独立资格 |

## 身份、权限与多租户

| 能力 | 作用 | 关键边界 |
|---|---|---|
| Account / TenantMember / RBAC | 将管理身份、租户成员关系、角色与权限分开 | 业务会员不是后台账号；TenantContext 只能来自受信会话/Host |
| PlatformOperator | 治理当前实例的 Tenant、入口、Module、存储和运维 | 不复用 Tenant 会话；不成为跨实例超级管理员 |
| Standalone / Multi-tenant | 两种部署形态共用 Tenant-first 数据和授权模型 | Standalone 仍有默认 Tenant；生产配置必须显式选择环境 |
| TenantModule Guard | 在 HTTP、任务、回调和消费者入口检查 Module 资格 | 菜单隐藏不是授权；Module 停用后真实入口必须拒绝 |
| 审计与幂等 | 记录关键状态变化、拒绝、任务和 Provider 证据 | 审计只存安全摘要；领域 payload 留在自己的 Module |

## 官方业务能力

| 领域 | 现有能力 | 主要 owner |
|---|---|---|
| 管理基础 | 管理员、角色、菜单、部门、岗位、字典、系统设置、日志与代码生成 | Application Host |
| 内容与装修 | 文章、分类、收藏/计数、移动端/PC/Tabbar 装修 | Article 与 Application 装修 Host |
| 会员与财务 | 会员、标签、余额、流水、充值入账和退款 | Member / Payment Module |
| 文件与素材 | 素材分类、文件对象、Local/对象存储 Provider provenance | File Module；实例存储由 Platform 配置 |
| 通知 | 通知场景、模板/日志、阿里云和腾讯云短信 | Notification Module |
| OAuth 与公众号 | 小程序、公众号、开放平台配置、OAuth exchange 和回调 Tenant 路由 | OAuth Module |
| 支付 | 微信/支付宝配置、预付、验签、回调、退款和幂等 | Payment Module |
| 任务与导入导出 | Crontab、后台任务、XLSX 数据导入导出、schema 化配置包 | Task / ImportExport Module |
| 多端客户端 | Admin、Platform、PC、H5/UniApp 共用品牌、身份和公开 API | 各客户端 Host；业务权限仍由后端强制 |

## 扩展与二次开发

| 能力 | 作用 | 推荐入口 | 关键边界 |
|---|---|---|---|
| Module manifest 与 Plugin lock | 声明版本、依赖、权限、migration、来源和信任状态 | `module.json`、`plugins.lock` | 当前 Runtime 只执行 bundled/locked 单元；Marketplace 需完整供应链 authority |
| Module 生命周期 | 安装、重复安装、升级计划、回滚计划、停用、retire/purge | Module 命令与 Platform 治理 | 数据处置必须显式，不默认删除 |
| Tenant 安全生成器 | 生成 Commands、append-only migration 指南和 A/B Tenant 安全骨架 | `module:create` | 不新增第二 Plugin Runtime；伪造 ID、撤权、停用和失败 migration 都要测试 |
| 配置转移 | 导出 schema 化配置包，先 dry-run，再按冲突策略和秘密重绑定导入 | ImportExport Module | 与数据库备份分离；不导出密码、token、Cookie 或密钥 |
| Core 公共合同 | 提供身份、Context、RBAC、Module、Audit、Settings 和 Ops 原语 | 公开 PHP/Web 包 | Application 只消费公开导出，不复制包源码或 deep import |
| Host / contribution | 装配页面、路由、Provider、任务和外部渠道 | Application/Module contribution | 数据和 Runtime 仍只有一个 owner；扩展不能直接写其他 Module 的表 |

## 阅读路径

- 首次部署：[快速开始](/getting-started) → [部署与升级](/guide/deployment-upgrade)。
- 二次开发：[核心概念](/guide/concepts) → [开发总览](/guide/development) →
  [Module 开发](/guide/module-development)。
- 权限与隔离：[数据、权限与多租户](/guide/data-permissions-tenancy)。
- 公共包和接口：[参考资料](/reference)与[API 与扩展](/api)。

如果某项能力在当前 Release 中没有对应入口，不要根据此页推断它已经发布；先核对 Release
说明、锁文件和实际部署身份。
