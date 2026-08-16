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

客户档案、供应商档案和联系人应作为业务对象，与 Account 登录载体分开。供应商在派生应用中可以
作为 Tenant，但其成员关系、合同、商品授权和采购参与权限必须由 DCS 等应用显式建模。

## Tenant 生命周期

1. 平台操作员填写 Tenant 编码、名称和 Owner 邮箱创建 Tenant。
2. 系统校验邮箱格式并创建一次性邀请；邮箱对应的已有 Account 不会被覆盖密码，新账号必须在接受时设置密码。
3. 接受邀请后建立 `Account -> TenantMember`、激活成员并授予 `core.tenant-owner`。
4. 平台操作员将 Tenant 从 `provisioning` 激活。暂停或关闭会阻止新的 Tenant 操作。
5. 已有 active Owner 的 Tenant 可以继续邀请其他 Owner；Core 会拒绝移除或暂停最后一个 active Owner。

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

Platform API 只接受 `PLATFORM_HOSTS` 中的域名；Tenant 域或公共 Admin 域不能调用。Platform
和 Tenant 浏览器会话分别使用独立 refresh Cookie 自动续期，任何一端的 Token 都不能投到
另一端使用。

详细 API 入口见 [API 与扩展](/api)，空库与本地体验见 [快速开始](/getting-started) 和 [部署与安装](/deployment)。
