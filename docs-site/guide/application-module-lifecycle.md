---
title: 创建应用与交付 Module
description: 从全新派生应用到 Module 创建、校验、打包、安装、Tenant 开通、更新、停用、卸载和应用升级的完整任务路径。
---

# 创建应用与交付 Module

这条路径面向派生应用 owner 和 Module 作者。所有命令都从同一个不可变 Peanut Admin
Release 创建的应用中运行；不要从移动分支、另一份源码仓或历史候选复制模板、fixture 或制品。

## 1. 创建独立应用

先下载并核对目标 Release，再从 Release checkout 运行唯一创建入口：

```bash
php scripts/create-app \
  --name='Acme Console' \
  --slug=acme-console \
  --package=acme/console \
  --target=/absolute/path/acme-console \
  --edition=standalone \
  --profile=standard
```

`--edition` 必须选择 `standalone` 或 `multi-tenant`，并在后续升级中保持不变；`--target` 必须是
新的绝对路径。保存输出中的 template/application version、source commit、
managed tree 和 app-owned tree 摘要；它们是后续升级和问题提交的身份。生成后在派生应用自己的
资源登记中配置数据库、端口、服务和凭据，不继承 Peanut Admin 源仓环境。

## 2. 创建并检查 Module

在生成应用的 `server/` 目录运行：

```bash
php think module:create acme.inventory --vendor=Acme
php think module:check acme.inventory
```

完成生成的 backend、frontend、manifest、权限、菜单、migration 和 Tenant 安全骨架后，重复
`module:check`。只有结果为 `status=ready` 且八项检查全部通过才能打包。检查只读且不连接数据库；
详细结构见 [Module 开发教程](/guide/module-development)。

## 3. 打包并固定信任身份

```bash
php think module:pack acme.inventory \
  --output=/absolute/path/acme.inventory-1.0.0.tar
```

生产或跨团队交付应使用受信 Ed25519 key：

```bash
php think module:pack acme.inventory \
  --output=/absolute/path/acme.inventory-1.0.0.tar \
  --signing-key-id=acme-release-1 \
  --signing-secret-key-file=/secure/path/signing-key.base64
```

通过独立受信渠道交付 archive SHA-256、签名 key ID 和公钥配置；不要提交私钥或把它放入诊断包。
直接分发受信 archive 是当前支持面。Marketplace、自动下载、在线上传和远程命令不在支持面。

## 4. 安装 Package

开发态的 Standalone 派生应用可以直接验证完整生命周期：

```bash
php think module:install-package /absolute/path/acme.inventory-1.0.0.tar \
  --sha256=<64-hex> \
  --signature-key-id=acme-release-1
```

该命令要求 development、debug 和 Standalone 实例工具边界。正式交付环境不能从 HTTP 接收
archive 路径、URL 或命令。当前 deployment-owned `ops-module:request` / 单次 worker 只覆盖
update、retire 和 Purge，并从登记的受限 inbox 消费受信制品；它不提供初始 install、disable 或
reactivate 的生产入口。需要这些生产操作时必须停在部署授权边界，不能用开发 CLI 或 HTTP 绕过。

Package 安装只改变 Package/ModuleInstallation 层，不会自动开通任何 TenantModule，也不会给
成员授予权限。

## 5. 开通 Tenant 与成员权限

由 PlatformOperator 在当前实例的 Tenant Module 治理入口为目标 Tenant 开通 Module，再由
Tenant 管理员把 Module 权限授予具体角色和成员。至少验证两个 Tenant：

- 未开通的 Tenant 始终得到 Module 停用拒绝；
- 已开通但未授权的成员仍得到权限拒绝；
- 授权只影响该 Tenant 和成员，不改写 Package、migration 或源码树；
- Tenant 或 TenantModule 停用后，HTTP、任务、回调和专属文件入口都必须 fail closed。

## 6. 更新 v1 → v2

始终先固定新 archive 的 SHA-256 和签名身份，再运行只读计划：

```bash
php think module:update-package /absolute/path/acme.inventory-2.0.0.tar \
  --sha256=<64-hex> \
  --signature-key-id=acme-release-1 \
  --dry-run
```

确认版本更高、Package key/成员范围不变、依赖和 Kernel 约束满足、migration 有配对已验证备份、
app-owned 文件不在写集后，去掉 `--dry-run` 执行。降级、相同版本不同内容、未知或不可逆 migration、
依赖冲突、签名/checksum 不符都会在破坏性步骤前停止。

## 7. 停用、重新激活、retire 与 Purge

先停用所有 TenantModule，再停用 Package：

```bash
php think module:disable-package acme.inventory
```

重新激活没有第二条命令：对**同一个不可变 archive**再次运行 `module:install-package`。Host 会按
既有身份恢复 Package active，但不会替你重新开通 TenantModule 或授予成员权限。

无数据删除的 retire 先预览，再用预览返回的完整 `confirm_plan` 文件和摘要确认：

```bash
php think module:uninstall-package acme.inventory
php think module:uninstall-package acme.inventory \
  --confirm-plan-file=/absolute/path/retire-plan.json \
  --confirm-plan-digest=<64-hex>
```

Purge 额外增加 `--purge`，会删除 Module 声明拥有的表、migration ledger、catalog 和显式 RBAC
绑定，因此同样必须先预览、配对备份并完成双确认。计划变化、活跃 TenantModule、依赖者、受保护
Module 或生命周期任务占用时必须停止；不要编辑预览文件来绕过检查。

## 8. 升级派生应用

应用框架升级使用 Release 中的 `scripts/scaffold-upgrade`：

```bash
php scripts/scaffold-upgrade preflight \
  --project-root=/absolute/path/acme-console \
  --from-manifest=/absolute/path/from-manifest.json \
  --to-manifest=/absolute/path/to-manifest.json

php scripts/scaffold-upgrade apply --project-root=/absolute/path/acme-console --plan=/absolute/path/plan.json
php scripts/scaffold-upgrade verify --project-root=/absolute/path/acme-console --plan=/absolute/path/plan.json
```

它只替换 manifest 声明的 managed/generated-managed 文件，保留第三方 Module 和业务 app-owned
源码。失败时使用同一 plan 的 `recover` 回到配对状态；它不替代数据库 migration、Package update
或生产恢复授权。

## 兼容矩阵

| 消费关系 | 支持条件 | 不支持或必须停止 |
| --- | --- | --- |
| Peanut Admin Release → 新应用 | annotated tag、Release、scaffold manifest 与 source commit/tree 同一身份 | 移动分支、混用不同 Release 的 manifest 或 scaffold |
| 应用 → Module package | manifest、Kernel constraint、依赖、权限、菜单、migration、frontend 与 archive 校验全部通过 | 手工维护第二套 schema/template、绕过 `module:check` |
| 已安装 v1 → v2 | 同一 Package key/成员范围，严格更高版本，不可变内容身份，依赖满足 | 降级、同版本换内容、静默改变 Bundle 成员 |
| Package → Tenant | Package active 后由 PlatformOperator 显式开通 TenantModule | 安装/更新自动开通 Tenant 或授权成员 |
| Module → 成员 | TenantModule enabled 且角色同时拥有权限/数据权限 | 菜单可见性代替后端授权 |
| 应用 scaffold 升级 | 只改 managed/generated-managed；app-owned 摘要保持 | 自动覆盖第三方 Module 或业务源码 |

遇到失败先按[参考入口的错误字段](/reference#错误输出与恢复)处理；需要提交问题时只附
[脱敏诊断包与最小复现](/support)，不要附原始日志或凭据。
