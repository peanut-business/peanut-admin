---
title: 故障处理
description: Peanut Admin 安装、启动、登录、租户入口与 Module 开发的最小诊断路径。
---

# 故障处理

本页只帮助定位当前环境；不通过更换数据库地址、重置共享库或关闭 Tenant/RBAC 检查来绕过问题。
先确认当前 checkout、部署目标和资源登记一致，再按下面的现象处理。

## 5 分钟速读

1. 安装、迁移、服务或浏览器检查前，先选择已登记资源，并确认当前 candidate 的 lease。
2. 新的 2.0.0 安装只能指向空库。已有表、1.x 数据库或共享 development 数据库被拒绝是保护机制，不是可忽略的安装错误。
3. Platform、Tenant Admin 和业务会员是三条独立身份链。错误入口或互换 token 时，应修正登录链路，不能放宽认证。
4. Plugin 已安装、TenantModule 已开通和成员有权限是三道独立 Gate；任一缺失都应拒绝访问。

## 一次诊断怎么做

| 顺序 | 记录什么 | 目的 |
| --- | --- | --- |
| 1 | 当前 commit、部署模式、资源 ID 和访问 Host | 确认没有在错误环境排查 |
| 2 | 失败命令或页面、一次完整错误码/日志摘要 | 区分产品缺陷、配置错误和测试脚本错误 |
| 3 | 只读检查对应资源和状态 | 不启动替代数据库、不换随机端口 |
| 4 | 修正一个最可能原因并重跑失败组一次 | 已通过的 API、Schema 或浏览器组不重复运行 |

同一失败组仍未通过时，保留证据并停止扩大验证范围。安全、Tenant 隔离、权限或数据完整性
失败会阻塞受影响发布；文档链接或格式失败只阻塞文档交付。

## 安装与数据库

| 现象 | 最小检查与处理 |
| --- | --- |
| `install.php` 拒绝目标库 | 确认目标是登记的空库。2.0.0 不接管 1.x 或已有应用 Schema；为新实例登记独立空库，不要清空共享 development 数据。 |
| 数据库连接失败 | 从资源登记导出非秘密连接参数，再按登记 health check 核验目标。凭据只从 credential reference 注入，不尝试 `localhost`、默认端口或默认账号。 |
| `migrate.php --current` 校验失败 | 核对当前 checkout 与 `init.sql`、基线后 migration 的账本身份。不要修改已应用 SQL 或删除 `pa_schema_migration` 记录；需要变更时新增 migration。 |
| 需要保留 1.x 环境 | 继续运行旧实例，并把数据迁移作为独立、可验收项目。不要对 2.0.0 使用 adopt、兼容 bootstrap 或旧映射表。 |

完整安装顺序与回滚停止线见[部署与安装](/deployment#fresh-only-基线)。

## 启动与入口

| 现象 | 最小检查与处理 |
| --- | --- |
| 本地栈无法启动 | 使用 `./scripts/local-stack.sh status` 查看登记的宿主 PHP 和各容器状态。端口来自 `.local/stack.env` 或 `PEANUT_LOCAL_ENV_FILE`，不要改用未登记的随机端口。 |
| 管理端请求失败 | 确认开发网关、宿主 API 和管理端端口来自同一份 local stack env；详细端口和启动顺序见[开始使用](/getting-started#启动服务)。 |
| Tenant 测试域名访问异常 | 确认 hosts 解析和系统代理绕过均已设置。`/etc/hosts` 只提供解析，代理仍可能截获 `*.peanut-admin.test` 请求。 |
| 生产入口异常 | 核对 `DEPLOYMENT_MODE`、`PLATFORM_HOSTS` 和 `TENANT_ADMIN_HOSTS`。未知 Host 必须 fail closed；不要为通过请求把所有 Host 设为可信。 |

## 身份、租户与 Module

| 现象 | 最小检查与处理 |
| --- | --- |
| Platform 或 Tenant Admin 登录失败 | 使用对应的初始身份登录。PlatformOperator、Account/TenantMember 与 `pa_member` 的 session/token 不能互换。 |
| 绑定 Tenant 入口拒绝切换 | 这是预期的 Host 边界。绑定入口只能建立和使用该 Tenant 的管理会话；切换只能从已登记的公共入口进行。 |
| Plugin 已安装但没有菜单或 API 403 | 依次检查不可变 lock、Plugin active、TenantModule enabled、成员权限和前端 contribution。菜单可见不构成后端授权。 |
| Module 读到另一 Tenant 数据 | 停止发布，检查 Repository 是否只从可信 `TenantContext` 取 Tenant，并补双 Tenant 的列表、详情和写入负向测试。 |

身份和入口规则见[身份与 Tenant 边界](/architecture/identity-and-tenancy)，Module 生命周期与最低测试见[Module 开发教程](/guide/module-development)。

## 仍无法定位

记录当前 candidate、部署目标、资源 ID、执行命令和不含凭据的错误摘要。先运行受影响路径的最小检查；不要因为文档或局部前端问题扩大为全量数据库、浏览器或多端 Gate。

报告时使用以下最小模板：

```text
环境/资源 ID：
当前 commit：
入口或命令：
预期结果：
实际结果和错误码：
已完成的一次只读检查：
最可能原因：
下一步（只列一个最小动作）：
```
