---
layout: home

hero:
  name: Peanut Admin 开发者文档
  text: 从第一次启动到可靠交付
  tagline: 面向应用开发者、Module 作者与运维人员的任务型指南。
  image:
    src: /brand/logo.svg
    alt: Peanut Admin
  actions:
    - theme: brand
      text: 快速开始
      link: /getting-started
    - theme: alt
      text: 开发文档
      link: /guide/development
    - theme: alt
      text: API 与参考
      link: /reference
    - theme: alt
      text: 在线演示
      link: /demo-access

features:
  - title: 先完成任务
    details: 每条核心路径都给出前置条件、目标、步骤、验证与下一步。
  - title: 理解边界
    details: 区分 Application、Core、Module、Tenant 与部署 Host 的责任。
  - title: 回到事实
    details: 命令、配置与合同指向仓库上游；生成页标明来源和再生成方式。
---

## 这座文档站适合谁

- 第一次搭建 Peanut Admin 开发环境的应用开发者。
- 扩展后端、前端或独立 Module 的维护者。
- 负责测试、排错、打包、部署和升级的工程人员。

如果你在维护 Peanut Admin 本身，需要查看架构状态、内部资源、资格证据或规划，请从仓库的 `docs/README.md` 开始。本站不会发布凭据位置、私有运行地址、内部候选证据或内部能力账本。

## 推荐旅程

1. [在线演示](/demo-access)：体验 Platform、共享 Admin 与 Tenant 绑定入口，确认它不等于你的业务生产部署。
2. [快速开始](/getting-started)：确认工具链、配置文件和最短启动路径。
3. [核心概念](/guide/concepts)：理解身份、Tenant、Module 与 Application/Core 边界。
4. [开发总览](/guide/development)：选择后端、前端或 Module 路径。
5. [创建应用与交付 Module](/guide/application-module-lifecycle)：完成生成、校验、打包、安装、Tenant 开通、更新、卸载和应用升级。
6. [测试与排错](/guide/testing)：用最小验证确认改动。
7. [部署与升级](/guide/deployment-upgrade)：固定身份、备份、迁移、验证与恢复。
8. [支持与问题提交](/support)：收集版本身份、脱敏诊断包和最小复现，并把安全问题送到非公开渠道。

## 事实边界

开发者页面是代码、manifest、Schema、API 和受控技术文档的友好投影。发现不一致时，以页面标注的上游为准并提交同步修复；不要再新建一份清单。完整的公开映射见[文档事实来源](/reference/source-map.generated)。
