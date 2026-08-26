---
title: 核心概念
description: Peanut Admin 的身份、Tenant、Module、Host 与 Core/Application 边界。
---

# 核心概念

## 目标

在写代码前建立共同词汇，避免把部署、身份或业务所有权混成同一层。

## Application 与 Core

Core 提供产品中性的 Runtime 和两个公开包边界；Peanut Admin Application 组合这些能力，并拥有官方产品 Module、部署配置和产品状态。应用能力不会因为“通用起来可能有用”就自动成为 Core 能力。

## Account、TenantMember 与业务会员

Account 是管理身份；TenantMember 把 Account 加入 Tenant 并承载租户内角色。业务会员是产品领域对象，不是后台登录身份。PlatformOperator 也独立于 TenantMember，不能复用 Tenant 会话进入平台控制面。

## Tenant 与部署形态

Standalone 仍保留默认 Tenant，以保持数据和授权模型一致；多租户模式增加平台治理、Tenant 选择和 Host 绑定。TenantContext 必须来自受信任会话或 Host 装配，不能从普通请求参数直接相信 `tenant_id`。

## Module

Module 是能力、权限、数据 owner、生命周期和扩展点的边界。`module.json` 是模块身份和声明的上游；菜单隐藏不是授权，Module 停用必须在实际入口拒绝新操作。

## Host 与扩展

Host 负责把 Core、Application 与外部提供商装配起来。扩展使用公开合同、Module contribution 或明确 override；不要复制包源码、改依赖目录或直接写其他 Module 的表。

## 验证理解

开始修改前，你应能回答：谁拥有数据、谁建立 TenantContext、哪个 manifest 或合同是上游、停用/拒绝时在哪个入口失败。

下一步阅读[数据、权限与多租户](/guide/data-permissions-tenancy)或[Module 开发](/guide/module-development)。
