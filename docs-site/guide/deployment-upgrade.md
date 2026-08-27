---
title: 部署与升级
description: 从不可变版本到备份、迁移、验证和恢复的交付闭环。
---

# 部署与升级

## 前置条件

- 使用不可变 Tag/Release 或固定完整提交，不从移动分支部署。
- 目标环境、数据库、域名、端口和凭据由部署方登记并完成健康检查。
- 备份、恢复位置和责任人已确认。

## 目标

部署一个可追溯版本，应用所需 migration，验证关键入口，并能回到配对备份。

## 步骤

1. 核对 `release-versions.json`、Release 元数据、锁文件和部署目标。
2. 备份数据库与用户持久文件，并记录它们对应的应用版本。
3. 使用 `scripts/deploy-release --help` 确认当前参数；不要从历史文档复制已删除选项。
4. 运行部署脚本的 preflight，再执行适合目标的 fresh 或 upgrade 流程。
5. 运行 migration/账本校验、服务健康检查和受影响入口 smoke。
6. 失败时停止流量变更，按配对备份和已验证恢复流程处理；不要跳过 checksum 或手改账本。

脚手架文件升级使用 `scripts/scaffold-upgrade` 的 `preflight/apply/verify/recover` 合同。它只管理 manifest 声明的文件，不替代业务 migration 或依赖升级。

## 验证

至少确认：固定版本身份、Schema/migration 账本、服务健康、登录与受影响 API/页面。正式发布资格按仓库风险等级执行，不把源码 Release、候选环境和生产部署混为同一状态。

## 下一步

记录真实已完成与未验证项；版本模型见[版本与发布](/releases)。
