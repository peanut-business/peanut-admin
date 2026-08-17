---
title: 实例平台管理
description: Peanut Admin 实例内 PlatformOperator、Tenant、Owner、入口绑定和模块治理。
---

# 实例平台管理

## 5 分钟速读

Platform 是当前应用实例的控制面，不是跨应用运营平台，也不是 Tenant 业务后台。它使用独立的
PlatformOperator 会话、平台角色和平台审计；普通 Tenant 管理员使用另一套 TenantMember 会话。

- 地址：`/platform/`；API：`/api/platform/*`。
- 一套部署默认对应一个应用实例；一个实例可以有多个 Tenant、客户端和 Module。
- 创建 Tenant 后先处于 `provisioning`，必须通过 Owner 邀请接受建立首个 TenantMember，之后才能激活。
- 同一账号能切换几个 Tenant，取决于它在这些 Tenant 中各有一条 active `TenantMember` 关系；平台身份本身不提供切换权。
- 域名绑定由平台配置 `host + client_key -> tenant`。它从登录持续约束到后续管理 API；绑定入口不允许切换到其他 Tenant。
- Owner 是 Tenant 内置 RBAC 角色，不是“全实例超级管理员”。平台 Operator 不能因平台身份读取租户业务数据。

## 登录与身份

| 身份 | 登录入口 | 能做什么 | 不能推导出的权限 |
| --- | --- | --- | --- |
| PlatformOperator | `/platform/` | 管理本实例 Tenant 生命周期、Owner 邀请、入口绑定、TenantModule、平台角色和审计 | 不自动成为任何 TenantMember，不自动读取商品、订单、会员等业务数据 |
| TenantMember | `/admin/` | 在当前 Tenant 内按 Tenant Role/RBAC 使用管理端 | 不能查看其他 Tenant；切换前必须已经加入目标 Tenant |
| 业务会员 `pa_member` | 业务客户端登录 | 客户侧业务身份、标签、余额等 | 不是 Account/TenantMember，不能拿会员 Token 调用管理端 |

客户档案、供应商档案和联系人应作为业务对象，与 Account 登录载体分开。将供应商关联到
Tenant 只是派生应用可评估的建模建议；采用前必须独立冻结业务主体、成员关系、合同、
商品授权和采购参与权限。这不是 Peanut Admin 当前能力，也不是已批准的 DCS Runtime。

## Tenant 生命周期

| 步骤 | 操作 | 系统结果 | 失败时怎么处理 |
| --- | --- | --- | --- |
| 1 | 填写 Tenant 编码、名称和 Owner 邮箱 | 创建 `provisioning` Tenant 和一次性邀请 | 邮箱格式、编码冲突或权限不足时修正输入，不手工插表 |
| 2 | 交付邀请链接 | `auto` 交给真实 Provider；`manual` 只显示一次 | 没有 Provider 时显式使用人工模式，不伪造“已发送”状态 |
| 3 | Owner 接受邀请 | 已有 Account 直接关联；新账号在接受时设置密码 | 不覆盖已有账号密码；Token 过期后重新签发邀请 |
| 4 | 系统建立成员关系 | 创建 active TenantMember 并授予 `core.tenant-owner` | 邀请未完成前不要激活 Tenant |
| 5 | PlatformOperator 激活 Tenant | Tenant 可以建立正常管理会话 | 暂停/关闭会阻止新操作，但不会把平台身份变成租户身份 |

新建 Tenant 时填写邮箱，并不表示系统已找到一个有效登录账号，也不会立即创建明文密码。
邮箱先作为 Owner 邀请目标；只有邀请被接受，才会得到可登录的 Account/TenantMember 和 Tenant 内 owner 角色。

邀请 Token 只保存 SHA-256，接受成功后立即失效。`auto` 模式在生产/预发布要求真实投递
Provider；显式 `manual` 模式允许已认证且具备权限的 PlatformOperator 取得只显示一次的
邀请链接并人工交付。两种模式都不记录明文 Token，人工模式也不会把投递状态伪装成已发送。

## 入口绑定

运行时以 `(host, client_key)` 解析唯一有效映射，当前客户端键为 `admin-web` 和 `member-api`。
数据库保留 Tenant 所有权和绑定状态，服务层拒绝同一 Host/client 指向不同 active Tenant。服务端会：

- 规范化大小写、端口和末尾点；
- 拒绝无效 Host、重复 active 绑定、暂停/关闭 Tenant；
- 绑定存在时忽略或拒绝冲突的显式 Tenant 编码；
- challenge 选择、已有管理 Token 和后续 API 必须继续匹配绑定 Tenant；
- 绑定入口禁止生成租户切换 challenge，前端同时隐藏切换入口；
- 绑定停用后 fail closed，不回退到另一个 Tenant；
- 没有绑定时，只有 `.env` 中显式声明的公共 Admin Host 可以进入成员选择；未知 Host 拒绝。

未绑定公共入口与绑定入口可以同时存在：公共入口允许账号在自己的 active TenantMember 列表中
选择或切换，绑定入口只允许固定 Tenant。Alpha Tenant 的 Token 在 Beta 绑定域名使用会被后端
拒绝；不能通过修改前端请求或手工附带 `tenant_code` 绕过。

反向代理必须保留真实 `Host`，TLS 证书和 DNS 由部署方负责。多个应用实例不共享这张绑定表；跨实例映射属于
另一个独立的联邦能力，当前不实现。

## 页面和常用操作

Platform 页面按中文菜单提供：概览、Tenant 生命周期、Owner 邀请、入口域名与客户端、Module 目录、
PlatformOperator、平台角色与权限、平台审计。所有写操作都需要变更原因，失败会显示服务端错误码，
权限变更和生命周期变更会增加安全版本并写审计。

平台页面不维护“当前租户”。Owner、入口和 Module 页面中的 Tenant 是操作员显式选择的治理目标，
不会转换 PlatformOperator 的身份，也不会让平台会话进入该 Tenant 的业务数据。

| 页面 | 当前功能 | 操作后的预期结果 |
| --- | --- | --- |
| Tenant 生命周期 | 创建、激活、暂停、关闭 Tenant | 生命周期状态变化并写平台审计 |
| Owner 邀请 | 创建、重新签发、查看投递状态 | 接受后建立 TenantMember + owner RBAC |
| 入口域名与客户端 | 绑定/停用 `host + client_key` | 登录和后续请求持续受同一 Tenant 约束 |
| Module 目录 | 查看安装状态、为 Tenant 开通/停用 | 只改变 TenantModule，不自动给成员授权 |
| PlatformOperator | 管理平台操作员 | 只影响平台控制面，不授予租户业务权限 |
| 平台角色与权限 | 分配平台治理权限 | 不能复用 Tenant Role，也不能读取租户私有表 |
| 平台审计 | 查询平台写操作 | 用于追溯 Tenant、Owner、入口和 Module 变更 |

Platform API 只接受 `PLATFORM_HOSTS` 中的域名；Tenant 域或公共 Admin 域不能调用。Platform
和 Tenant 浏览器会话分别使用独立 refresh Cookie 自动续期，任何一端的 Token 都不能投到
另一端使用。

详细 API 入口见 [API 与扩展](/api)，空库与本地体验见 [快速开始](/getting-started) 和 [部署与安装](/deployment)。
