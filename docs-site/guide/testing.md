---
title: 测试与排错
description: 按变更路径选择最低充分验证并定位常见失败。
---

# 测试与排错

## 目标

在最新候选上运行一次最低充分验证，失败后只做一次只读诊断并修复受影响组。

## 选择检查

| 变化 | 最低入口 |
| --- | --- |
| 纯文档 | `./scripts/docs-governance check`、站点 `pnpm build`、`git diff --check` |
| 后端局部行为 | PHP 静态检查和受影响功能测试 |
| HTTP 合同 | 上述检查加 `./scripts/check-openapi` |
| 单个客户端 | 对应目录类型检查、构建和已有聚焦测试 |
| Tenant、权限、Schema、部署 | 仓库规则指定的安全/完整性 Gate |

不要为后端局部修改构建所有客户端，也不要用历史成功结果证明新 HEAD。
客户端检查前按对应 lockfile 在当前 worktree 安装依赖；Platform 使用 `npm ci`，Web 使用
`pnpm install --frozen-lockfile`，不得复用其他 worktree 的 `node_modules`。

## 常见排错顺序

1. `git status --short --branch`：确认仓库和写集。
2. `./scripts/local-stack.sh status`：只在已按项目规则选择运行资源后检查本地服务。
3. 对照 `.env.example`、命令 `--help` 和 manifest；不要从旧页面猜配置。
4. 查看第一个真实错误，区分代码、合同、环境与外部服务。
5. 只重跑失败组一次。

## 验证

记录命令、真实结果和未运行项。验证失败不能改写成“基本通过”。

## 下一步

通过聚焦检查后，按[部署与升级](/guide/deployment-upgrade)准备交付。
