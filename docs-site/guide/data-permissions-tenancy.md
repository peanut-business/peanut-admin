---
title: 数据、权限与多租户
description: 数据 owner、TenantContext、RBAC 与安全边界。
---

# 数据、权限与多租户

## 前置条件

明确目标部署形态、身份类型、Module 和数据 owner。

## 不可混用的边界

- 功能权限决定“能否执行操作”；数据权限决定“可操作哪些对象”。两者都必须通过。
- TenantContext 来自受信任装配；请求中的 Tenant 标识只能作为待验证输入。
- Module 拥有自己的表和写路径；跨 Module 使用服务合同或公开 DTO。
- PlatformOperator、Tenant 管理身份与业务会员属于不同身份域。
- Tenant 状态是每次请求的连续边界；停用后管理/API/PC/H5、公开内容和文件交付都必须拒绝，
  不能等待 token、签名 URL 或缓存自然过期。

Tenant 文件只使用短期签名的 `/api/storage/delivery`。应用在每次读取时同时检查对象仍为 `ready`
且 Tenant 仍为 `active`，并返回 `no-store` 内容；`/storage/`、云公开 bucket/CDN URL 和客户端缓存
不能作为旁路。恢复 Tenant 只重新允许仍通过 Host、身份、Module、RBAC 和对象状态检查的访问。

## Schema 和 migration

先修改当前基线规定的权威 Schema 来源，再添加允许的增量 migration。已发布 migration 不回写；变更同时记录 owner、前滚条件和恢复策略。应用 Schema 与 Core KernelSchema 各由自己的仓库拥有。

## 验证

至少覆盖：正确 Tenant 的正向路径、错误 Tenant 或无上下文的拒绝、权限不足的拒绝、Module 停用后的拒绝，以及数据库约束或事务结果。只有页面隐藏或 HTTP 200 不构成隔离证据。

## 下一步

实现独立能力时进入[Module 开发](/guide/module-development)；交付数据变化时进入[部署与升级](/guide/deployment-upgrade)。
