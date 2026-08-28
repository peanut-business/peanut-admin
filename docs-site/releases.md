---
title: 版本与发布
description: 区分源码 Release、资格候选、部署与派生应用升级。
---

# 版本与发布

## 前置条件

确认你讨论的是源码 Release、固定资格候选、已部署实例，还是派生应用自己的版本。它们不能互相替代。

## 版本来源

- Git Tag 和 Release 提供不可变源码身份。
- `release-versions.json` 与 Release 元数据约束应用、脚手架和公共包版本关系。
- 派生应用在 `.peanut/application-manifest.json` 记录采用来源和文件所有权。
- Composer 与 npm 公共包拥有独立版本线，应用必须固定接受的版本。

本站不手工维护“当前版本”数字，以免和 Release 漂移。选择版本时查看 GitHub Releases，并核对仓库内不可变元数据。

## 发布不等于部署

源码 Release 证明源码身份；固定资格证明约定 Gate；部署证明某一目标实际运行；线上 smoke 只覆盖执行过的入口。报告时分别写出已完成、已验证、未验证和计划。

## 升级

脚手架升级与数据库/业务升级是不同责任域。执行前阅读[部署与升级](/guide/deployment-upgrade)，固定源版本与目标版本，完成备份、preflight、apply、verify 和 recover 准备。
