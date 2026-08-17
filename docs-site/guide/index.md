---
title: 文档门户
description: Peanut Admin 快速开始、架构身份、模块开发、fresh 部署、能力目录与管理员文档入口。
---

# 文档门户

按你的工作目标选择入口。所有文档以当前仓库和已发布依赖为事实源；示例域名、账号和密钥都必须按目标环境替换。

<div class="doc-entry-grid doc-entry-grid--two">
  <a class="doc-entry" href="/getting-started">
    <strong>快速开始</strong>
    <span>准备 PHP、MySQL、Node，完成空库安装与首次登录。</span>
  </a>
  <a class="doc-entry" href="/guide/reading-guide">
    <strong>文档阅读与命令参考</strong>
    <span>先看参数表、前置条件、预期结果和停止线，再复制命令。</span>
  </a>
  <a class="doc-entry" href="/guide/development">
    <strong>架构、目录与身份</strong>
    <span>Core/Host/应用/Module、部署实例、三类身份、Tenant 映射和 DCS 边界。</span>
  </a>
  <a class="doc-entry" href="/platform">
    <strong>实例 Platform</strong>
    <span>中文控制面、Tenant 生命周期、Owner 邀请、入口域名、Module 和平台审计。</span>
  </a>
  <a class="doc-entry" href="/architecture/identity-and-tenancy">
    <strong>身份与 Tenant 边界</strong>
    <span>Account、PlatformOperator、TenantMember、业务会员、RBAC 和三类映射。</span>
  </a>
  <a class="doc-entry" href="/api#module-plugin-与-host">
    <strong>Module 与跨模块调用</strong>
    <span>真实纵向目录、Plugin/TenantModule Gate、命令、查询、事件和失败处理。</span>
  </a>
  <a class="doc-entry" href="/guide/module-development">
    <strong>首个 Module 教程</strong>
    <span>从目录、表、合同、权限和菜单到跨 Module 调用与最低测试。</span>
  </a>
  <a class="doc-entry" href="/capabilities">
    <strong>开箱即用能力与后续路线</strong>
    <span>逐项区分当前能力、下一步产品形态、核心默认、可选官方模块、DCS 业务和示例。</span>
  </a>
  <a class="doc-entry" href="/architecture/official-module-qualification">
    <strong>官方模块资格</strong>
    <span>所有官方可选模块必须满足的多租户、权限、文件、任务、回调和停用门禁。</span>
  </a>
  <a class="doc-entry" href="/deployment">
    <strong>部署与安装</strong>
    <span>应用实例、2.0.0 fresh baseline、空库安装、备份与回滚。</span>
  </a>
  <a class="doc-entry" href="/troubleshooting">
    <strong>故障处理</strong>
    <span>安装、启动、登录、Tenant 入口和 Module Gate 的最小诊断路径。</span>
  </a>
  <a class="doc-entry" href="/api">
    <strong>API 与扩展</strong>
    <span>响应、认证、权限、回调，以及 Composer/npm 公共入口。</span>
  </a>
  <a class="doc-entry" href="/guide/user-manual">
    <strong>管理员手册</strong>
    <span>管理端各模块操作、角色权限和安全清单。</span>
  </a>
  <a class="doc-entry" href="/releases">
    <strong>版本与发布</strong>
    <span>当前应用阶段、核心包锁定版本、已验证与未验证边界。</span>
  </a>
</div>

## 推荐阅读顺序

1. 新安装者：文档阅读与命令参考 → 快速开始 → 架构、目录与身份 → 部署与安装 → 故障处理 → 管理员手册。
2. Module 开发者：架构、目录与身份 → 首个 Module 教程 → API 与扩展 → 开箱即用能力与后续路线。
3. 架构讨论：身份与 Tenant 边界 → 开箱即用能力与后续路线 → 官方模块资格 → fresh baseline。
4. 发布负责人：版本与发布 → 部署与安装 → 产品状态。

## 当前讨论入口

需要继续模块、身份、租户或脚手架清理讨论时，从以下五页开始，不再依赖聊天摘要：

- [架构、目录、三类身份与租户映射](/guide/development#身份与业务主体)
- [Module、Plugin、Host 与跨模块调用](/api#module-plugin-与-host)
- [首个 Module 纵向教程](/guide/module-development)
- [2.0.0 空库安装与 fresh baseline](/deployment#fresh-only-基线)
- [安装、启动、身份与 Module Gate 故障处理](/troubleshooting)
- [开箱即用能力逐项决策](/capabilities)
- [独立中文 Platform 操作手册](/platform)
- [官方可选模块强制多租户资格](/architecture/official-module-qualification)

这些页面会明确标注“当前已支持、推荐新增、仅迁移需要、暂不建议、待核验”。设计建议
不会被写成已经存在的 Runtime；DCS 具体领域文档仍由 DCS 仓库维护。

## 当前文档覆盖状态

下表描述的是“文档是否已经把问题讲清楚”，不是把计划能力写成已实现 Runtime。

| 主题 | 当前文档状态 | 长期入口 |
| --- | --- | --- |
| 5 分钟速读 | 已补齐 | 门户、开始使用、部署、身份、Module 和命令页均先给结论 |
| 脚本参数表 | 已补齐 | [文档阅读与命令参考](/guide/reading-guide)列必填项、默认值、作用、风险和示例 |
| 前置条件、预期结果和失败处理 | 已补齐 | 开始使用、部署和故障页统一使用停止线，不把 HTTP 200 当业务验收 |
| 目录结构与模块职责 | 已补齐 | [开发指南](/guide/development)和[Module 教程](/guide/module-development)提供真实目录与所有权 |
| 派生应用创建与升级边界 | 已说明当前支持面；自动升级待交付 | [开始使用](/getting-started)说明 create-app 参数、人工采用边界和 2.x 当前仍无自动升级器 |
| 三类身份、Tenant 选择与域名 | 已补齐 | [身份与 Tenant 边界](/architecture/identity-and-tenancy)和[实例 Platform](/platform) |
| Module、Plugin、Host 和跨模块调用 | 已补齐 | [API 与扩展](/api#module-plugin-与-host)说明四道 Gate、DTO、命令和失败边界 |
| 开箱即用能力和后续路线 | 已补齐 | [能力目录](/capabilities)分开记录当前事实、推荐产品形态和完成条件 |
| 官方可选模块多租户 | 已补齐 | [官方模块资格](/architecture/official-module-qualification)明确所有官方 Module 必须多租户 |
| 部署与迁移 | 已补齐当前事实 | `v2.0.0` 是 fresh-only；无人值守脚本未合入前不写成正式能力 |
| 管理员操作 | 已覆盖当前页面 | [管理员手册](/guide/user-manual)按页面说明操作结果和权限边界 |
| 故障处理 | 已补齐最小路径 | [故障处理](/troubleshooting)按资源、安装、身份、Host 和 Module Gate 排查 |
| DCS 边界 | 已明确 | Peanut 只说明扩展机制；DCS 领域表、事件和业务流程留在 DCS 仓库 |

## 未决问题

以下问题已明确不进入本次 Peanut Admin Runtime，留给对应派生应用或后续部署任务：

1. DCS 中 Business Subject 与 Tenant 是一对一、可多对多，还是只对部分供应商建立关联。
2. 采购单采用单一权威记录加 participant policy，还是在什么条件下升级为双方投影。
3. `pa_member` 业务客户登录是否长期独立，还是未来只关联而不合并到 Account/TenantMember。
4. 文件、文章、导入导出等已适配 Runtime 中，哪一个先包装成真正可安装的官方 Plugin。
5. 是否已有两个真实消费者足以批准通用 Outbox/Event Bus；没有消费者前保持同步合同。
6. 正式生产邮件 Provider、DNS、TLS 和入口域名由线上部署任务提供，本地只使用一次性 Token 与 hosts。
