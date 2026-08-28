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
4. 让部署入口在刚构建的候选 PHP 镜像内完成只读安装预检和 Plugin lock 校验；未通过时不得替换旧服务或删除卷。
5. fresh 部署选择 automatic（CI/托管）或 guided（人工页面）入口；两者共用同一安装 Host。
6. 运行 migration、official Plugin reconcile、账本校验、服务健康检查和受影响入口 smoke。
7. 失败时停止流量变更，按配对备份和已验证恢复流程处理；不要跳过 checksum 或手改账本。

脚手架文件升级使用 `scripts/scaffold-upgrade` 的 `preflight/apply/verify/recover` 合同。它只管理 manifest 声明的文件，不替代业务 migration 或依赖升级。

实例内 Platform 的应用升级中心只提交固定目标：浏览器不能选择路径、命令、Release、镜像、
凭据或部署目标。登记的单次控制 worker 会先创建新备份并用同一备份完成隔离恢复验证，再进入
维护、调用唯一部署更新入口、执行 Runtime smoke 并形成恢复指针。任一步失败都会保留已完成
步骤和稳定停止点；生产执行仍须由部署 owner 取得具体授权并核验自己的资源登记。

初始 Admin/Platform 身份不得写入 `server/.env`。automatic 只把它们注入安装命令进程；
guided 使用高熵一次性 setup token，并且只开放 `/admin/installation` 和固定安装 API。数据库
连接与部署模式始终来自登记配置，页面不接受任意地址、端口、路径或命令。安装失败若留下
非空数据库，停止并由资源 owner 重建目标，不自动 adopt 或覆盖。

## 验证

至少确认：固定版本身份、Schema/migration 账本、服务健康、登录与受影响 API/页面。正式发布资格按仓库风险等级执行，不把源码 Release、候选环境和生产部署混为同一状态。

## 下一步

记录真实已完成与未验证项；版本模型见[版本与发布](/releases)。
