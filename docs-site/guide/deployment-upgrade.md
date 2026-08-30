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

## 先选对升级包

Standalone 和 Multi-tenant 是同一套开发源码生成的两种正式构建物，但升级包不能混用。正式
Release 页面会分别提供两个安装包和两个升级包；已有应用只下载与自己 Edition 相同、且源版本
落在支持范围内的升级包。完整安装包不能覆盖已有应用，Edition 转换也不是普通升级。

下载后先核对 Release 页面给出的 SHA-256，解压升级包，再使用包内升级器生成只读计划。受信
Ed25519 公钥必须来自包外的正式维护者入口，不能相信升级包自己携带的公钥：

```bash
# 在线取得时固定 tag 和 Edition；离线环境把同一组文件人工带入即可。
gh release download vX.Y.Z \
  --pattern 'peanut-admin-X.Y.Z-standalone-upgrade.tar.gz' \
  --pattern 'peanut-admin-X.Y.Z-standalone-upgrade.tar.gz.manifest.json' \
  --pattern 'SHA256SUMS.upgrades'
shasum -a 256 -c SHA256SUMS.upgrades
tar -xzf peanut-admin-X.Y.Z-standalone-upgrade.tar.gz

export PEANUT_UPGRADE_TRUSTED_KEYS_JSON='{"<official-key-id>":"<base64-ed25519-public-key>"}'

php /path/to/extracted-upgrade/upgrader/scripts/scaffold-upgrade preflight \
  --project-root=/path/to/application \
  --package=/path/to/extracted-upgrade \
  --signature-key-id=<official-key-id>
```

只有 `status=ready` 且冲突为 0 才继续。未知签名、checksum 漂移、错 Edition、降级、缺失迁移或
不受支持版本都会在写入前停止；网络失败不会改用移动分支或其他版本。

## 步骤

1. 核对 `release-versions.json`、Release 元数据、锁文件和部署目标。
2. 备份数据库与用户持久文件，并记录它们对应的应用版本。
3. 使用正式升级包的 preflight 核对会替换、保留和冲突的文件；部署 owner 再使用
   `scripts/deploy-release --help` 确认当前参数，不从历史文档复制已删除选项。
4. 让部署入口在刚构建的候选 PHP 镜像内完成只读安装预检和 Plugin lock 校验；未通过时不得替换旧服务或删除卷。
5. fresh 部署选择 automatic（CI/托管）或 guided（人工页面）入口；两者共用同一安装 Host。
6. 运行 migration、official Plugin reconcile、账本校验、服务健康检查和受影响入口 smoke。
7. 失败时停止流量变更，按配对备份和已验证恢复流程处理；不要跳过 checksum 或手改账本。

## 每个停止点怎么处理

| 停止点 | 当前状态 | 下一步 |
| --- | --- | --- |
| 下载或 SHA/签名失败 | 应用未变化 | 停止；重新核对固定 tag、Edition、Release 摘要和包外公钥，不换用其他版本 |
| preflight 显示冲突 | 应用未变化 | 逐项决定保留用户修改还是采用上游；不要编辑 plan 绕过冲突 |
| apply 失败或文件 verify 失败 | 数据库尚未迁移时只涉及受管文件 | 使用同一 plan 的 `recover` 恢复文件，再修正唯一失败原因 |
| migration 失败 | 文件可能已更新，数据库可能部分前滚 | 停止服务切换；按 migration 账本和配对数据库备份处理，不能只运行文件 `recover` 就宣称恢复 |
| 构建、启动或 smoke 失败 | 新版本未完成采用 | 保留日志和恢复指针；回到配对文件/数据库备份，或修复后从明确停止点继续 |

`recover` 只恢复本次 scaffold 管理文件，不能逆转 Composer/npm、数据库、对象存储或外部 Provider
副作用。因此数据库迁移前必须先做配对备份，完整升级不能压缩成一条无停止点命令。

脚手架文件升级使用升级包内 `scripts/scaffold-upgrade` 的 `preflight/apply/verify/recover` 合同。
它只管理 manifest 声明的文件，不替代业务 migration、依赖安装、构建、服务重启或 smoke。

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
