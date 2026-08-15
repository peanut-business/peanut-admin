---
title: 能力与场景
description: Peanut Admin 当前已交付能力、适用场景和明确边界。
---

# 能力与场景

Peanut Admin 面向需要“管理后台 + 用户 PC/H5/小程序 + 可部署后端”的应用团队。它提供可运行基线和扩展边界，不把演示数据、第三方凭据或未实现路线图伪装成开箱即用的生产能力。

## 产品入口

| 入口 | 技术与职责 | 当前能力 |
|---|---|---|
| 管理端 | Vue 3 + Element Plus | 工作台、管理员/RBAC、会员、内容、通知、财务、装修、渠道与系统工具 |
| PC | Nuxt 3 + Element Plus | 公开内容、会员登录、资料/安全、收藏、充值和 OAuth 回跳 |
| UniApp/H5 | Vue 3 + UniApp | H5/小程序入口、装修、会员、充值、协议与 OAuth 补全 |
| 后端 | ThinkPHP 8 + MySQL 8 | 管理/会员 API、安装迁移、权限审计、支付通知与渠道回调 Host |

## 典型场景

- 内容与会员型应用：文章栏目、内容发布、会员资料、标签、收藏与访问端装修。
- 带余额与充值的业务：权威余额、分类流水、充值订单、支付回调、退款与对账命令。
- 多入口业务：同一后端服务管理端、PC、H5 与 UniApp，并为各端保留导航和 SDK 适配。
- 二次开发脚手架：克隆后通过单一品牌清单和网站配置改名，再在应用 Module 内增加产品领域。
- 自托管交付：Docker Compose 或原生 Nginx/PHP-FPM，支持空库安装与已有库前滚升级。
- 多租户应用：同一 release 支持 Standalone 与多租户部署；多租户模式提供独立 PlatformOperator、Tenant 生命周期、首 owner、模块开通、租户选择/切换/撤销和代表业务隔离。

## 应用与核心边界

运行时公开依赖只有两个：

- Composer `peanut-admin/core`：认证、权限等已采用的产品无关 PHP 原语与公共契约。
- npm `@peanut-admin/admin`：管理端公共入口，以及 PC/UniApp 使用的无 UI client/transport 子路径。

会员/财务、内容/装修、支付/OAuth/渠道等产品实体和流程由应用 Module 唯一拥有。核心仓内部领域目录只是源码组织，不是可单独安装的包。扩展时应使用稳定 interface/key 和 Host 装配，不修改 `vendor/`、`node_modules/`，也不复制核心实现。

## 明确不包含

- 完整 SaaS 商业控制面尚未实现：套餐、订阅、计费、试用、发票、市场和跨实例运营平台不在当前应用内。
- 仓库不附带真实短信、支付、微信、对象存储或 OAuth 凭据；生产可用必须完成平台登记和低风险 smoke。
- 当前稳定多租户应用版本为 `1.1.3`。版本、依赖和验收事实见[版本与发布](/releases)。
- 应用暂时采用专有 / All Rights Reserved，版权主体显示为“花生科技”；包元数据为 `proprietary/UNLICENSED`，第三方组件按各自许可证告知。

## 下一步

- [快速开始](/getting-started)：建立本地可登录环境。
- [开发指南](/guide/development)：理解分层、迁移和覆盖规则。
- [API 与扩展](/api)：接入接口与两个公开包。
- [部署与升级](/deployment)：准备生产环境与前滚流程。
