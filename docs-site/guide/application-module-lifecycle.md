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
  --profile=full
```

`--edition` 必须选择 `standalone` 或 `multi-tenant`，并在后续升级中保持不变；`--target` 必须是
新的绝对路径。保存输出中的 template/application version、source commit、
managed tree 和 app-owned tree 摘要；它们是后续升级和问题提交的身份。生成后在派生应用自己的
资源登记中配置数据库、端口、服务和凭据，不继承 Peanut Admin 源仓环境。当前正式创建清单采用
`full` profile。`create-app` 只用于首次生成；已有应用不能重新生成并覆盖，而应在自己的开发分支
通过签名 scaffold 包升级受管文件。

应用版本、scaffold 来源、Core lock 与 Module 版本是四种独立身份：应用版本对应完整应用
Release；scaffold manifest 对应受管源码基线；Composer/npm lock 对应实际安装的 Core；Module
manifest、archive 摘要和签名对应业务模块。升级其中一项不会自动升级其余三项。

下一次正式发布起，Peanut Admin 的 scaffold、PHP Core 和 Web Core 采用同一个基础发行号，
包括同步的预发布后缀；应用版本仍按自身节奏递增。这项规则不改变四种身份：相同版本号不能代替
manifest、lock、不可变包引用或兼容验证，共同版本号也不能把 alpha 自动说成稳定版。第三方或
私有 Module 继续使用自己的版本和 archive SHA-256。当前 `3.0.14` scaffold 已采用正式发布的
`0.1.0-alpha.13` Core；两者仍是独立版本轴，后续不能靠改 manifest 假装对齐，必须依次完成 Core 包
发布与验证、应用锁定消费、同号 scaffold 资格；任一步失败时，整套基础发行不能标记为 ready。

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

`module:pack` 只生成 package candidate，不会发布 archive、修改 `plugins.lock`、开通 TenantModule，
也不能证明 PHP/npm 组件名已存在于外部 Registry。bundled Module、独立 qualified Package 和外部
published Package 是三个不同状态。

## 4. 安装 Package

开发态的 Standalone 派生应用可以直接验证源码和数据库生命周期：

```bash
php think module:install-package /absolute/path/acme.inventory-1.0.0.tar \
  --sha256=<64-hex> \
  --signature-key-id=acme-release-1
```

该命令要求 development、debug 和 Standalone 实例工具边界。正式交付环境不能从 HTTP 接收
archive 路径、URL 或命令。现有受限 worker 只证明 Module 源码和数据库生命周期，不构成生产
在线更新闭环；生产后台上传、Marketplace 和热更新均未实现。Module archive 应先在应用仓采用、
锁定依赖、构建和验收，再随应用自己的完整 Release 部署。

Package 安装只改变 Package/ModuleInstallation 层，不会自动开通任何 TenantModule，也不会给
成员授予权限。private Package 内可以包含自己的 `Http/routes.php`，但安装命令不会自动注册它；
应用 owner 必须在 app-owned 路由装配中显式引入该文件，并沿用认证、Module 与权限 middleware。
卸载时由同一 owner 去除接线；不要复制业务 handler 或增加在线动态路由 loader。

这个 archive 入口只支持 development、debug 且 Standalone。Multi-tenant 派生应用当前没有从
签名 archive 到应用仓源码与 `plugins.lock` 的受支持采用命令；不要绕过 Edition 门禁，也不要用
只消费现有锁定源码的 `plugin:install` 冒充采用入口。Multi-tenant 私有 Package 采用工具仍待补齐。

## 5. 开通 Tenant 与成员权限

两种 Edition 都保留“安装、TenantModule、成员 RBAC”三层。Standalone 标准安装会按
`official_modules` 安装配置把所选 official Module 应用到 default Tenant，当前完整部署还会执行
应用拥有的 `tenant-module:apply-profile standalone` 固定官方产品 profile；这些入口不会自动包含
后来采用的 private Module。本批验证由授权部署 owner 在受控应用安装/部署步骤中调用
`ProductTenantModuleProfileService::applyInstallationSelection()`，为 default Tenant 显式开通已经锁定
的 private Module，再由 Tenant 管理员在角色授权入口把 Module permission 分配给角色和成员。
Standalone 安装明确不创建 Platform 初始身份，源仓存在的 Platform Tenant Module API 不是 fresh
Standalone 的可登录入口；当前也没有 private 选择的完整用户 CLI/UI 包装。

Multi-tenant 同样由 PlatformOperator 为每个目标 Tenant 显式开通，再由各 Tenant 管理员授权；
Package 安装或路由接线不能自动完成这两步。至少验证两个 Tenant：

- 未开通的 Tenant 始终得到 Module 停用拒绝；
- 已开通但未授权的成员仍得到权限拒绝；
- 授权只影响该 Tenant 和成员，不改写 Package、migration 或源码树；
- Tenant 或 TenantModule 停用后，HTTP、任务、回调和专属文件入口都必须 fail closed。

## 6. 更新 v1 → v2

本节命令与第 4 节相同，只适用于 development/debug Standalone；当前真实联合升级证据也限定为
Standalone。Multi-tenant 的 private archive 采用入口尚未闭合。

始终先固定新 archive 的 SHA-256 和签名身份，再运行只读计划：

```bash
php think module:update-package /absolute/path/acme.inventory-2.0.0.tar \
  --sha256=<64-hex> \
  --signature-key-id=acme-release-1 \
  --dry-run
```

确认版本更高、Package key/成员范围不变、依赖和 Kernel 约束满足、migration 有配对已验证备份、
app-owned 文件不在写集后，去掉 `--dry-run` 执行。降级、相同版本不同内容、未知或不可逆 migration、
依赖冲突、签名/checksum 不符都会在破坏性步骤前停止。当前命令不会替应用运行 Composer/npm、
前端构建或服务重启，因此 CLI/数据库成功不能单独证明 Module 已可部署。

版本升级后还要用应用自己的路由装配和 middleware 实际验证 private HTTP 入口：未认证请求应拒绝，
合格非 root 身份只能访问获授权的 Module 行为。只看到 archive 中存在 `Http/routes.php` 或 CLI/DB
升级成功，不代表该入口已经接入应用。

当前管理端也不是运行时动态加载 Module 前端：构建配置从 `plugins.lock` 生成指向本仓
`web/src` 的静态 import，路由消费的是同一次构建产生的 chunk。Vite 的
[`import.meta.glob`](https://vite.dev/guide/features#glob-import) 仍要求构建时已知文件；改用
[Module Federation Vite](https://module-federation.io/integrations/build-tool/vite) remote 虽可实现
远程加载，但还必须新增 Vue/router/Pinia
[共享依赖协议](https://module-federation.io/configure/shared.html)、CSS 生命周期、签名
[remote manifest](https://module-federation.io/configure/manifest.html)、CDN 可用性、宿主 API 与模块
制品组合身份及回滚合同。按当前独立应用整体发布的目标，暂不引入线上独立安装，维持“应用开发期
采用 Module、随完整应用 Release 部署”。若出现第三方 Module 跨多个在线产品独立高频交付需求，
再重新评估动态前端。
届时应用 build ID 必须固定映射 Module version、artifact SHA、入口与 backend API contract，不能
读取 `latest`；Module Federation manifest 本身也不提供签名信任，仍需独立验证来源与内容。

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

应用框架升级使用同 Edition 的签名 scaffold 包，并且只在应用自己的开发分支执行：

```bash
export PEANUT_UPGRADE_TRUSTED_KEYS_JSON='{"<official-key-id>":"<base64-ed25519-public-key>"}'
php /path/to/extracted-upgrade/upgrader/scripts/scaffold-upgrade preflight \
  --project-root=/absolute/path/acme-console \
  --package=/absolute/path/to/extracted-upgrade \
  --signature-key-id=<official-key-id>

php /path/to/extracted-upgrade/upgrader/scripts/scaffold-upgrade apply \
  --project-root=/absolute/path/acme-console --plan=/absolute/path/plan.json
php /path/to/extracted-upgrade/upgrader/scripts/scaffold-upgrade verify \
  --project-root=/absolute/path/acme-console --plan=/absolute/path/plan.json
```

它只替换 manifest 声明的 managed/generated-managed 文件，保留第三方 Module 和业务 app-owned
源码。失败时使用同一 plan 的 `recover` 回到配对状态；它不替代数据库 migration、Package update
或生产恢复授权。采用 scaffold、更新 Core/Module 依赖并完成应用验收后，先形成应用自己的不可变
commit/tree、tag 和完整 Release，运行中实例再部署该完整应用 Release。禁止先修改运行实例、执行
migration 或重启，再补应用 tag；也不能把签名 scaffold 包直接当成实例部署包。

完整应用 Release 必须固定所有已安装 Package 的确切身份。Package 缺失、回退或同版本换内容时
部署必须停止；Package 身份不变且其 Module installation 均处于正常 maintenance 禁用态时保留
Package 并跳过 reconcile，failed、retire/purge 进行中或未知过渡状态阻断。显式移除先走
停用/retire，而不是靠整树替换删除。本批只在隔离 Standalone Development 环境完成了应用
`0.1.4` / private Package
`1.0.0` 到应用 `0.2.0` / Package `2.0.0` 的完整切换，覆盖 build、配对备份、migration、重启、
HTTP 与旧业务数据/文件保留。它没有运行 Platform 完整状态机、浏览器交互或真实恢复演练，不能据此
宣称生产可用。

全新空库的完整 Release 已包含锁定 private Package 源码时，授权部署 owner 可在标准安装后执行
`php server/think plugin:install <private-key>`，从当前 `plugins.lock` 初始化表与 catalog。这个命令
不接收或解压 archive，当前也没有通用生产 worker 包装；已安装 private Package 后续由
`plugin:reconcile --release-locked` 对齐 Release 锁定状态。因此“不在线接收 tar”不等于部署时
不执行 Module 初始化或 migration。

## 兼容矩阵

| 消费关系 | 支持条件 | 不支持或必须停止 |
| --- | --- | --- |
| Peanut Admin Release → 新应用 | annotated tag、Release、scaffold manifest 与 source commit/tree 同一身份 | 移动分支、混用不同 Release 的 manifest 或 scaffold |
| 应用 → Module package | manifest、Kernel constraint、依赖、权限、菜单、migration、frontend 与 archive 校验全部通过 | 手工维护第二套 schema/template、绕过 `module:check` |
| 已安装 v1 → v2 | 同一 Package key/成员范围，严格更高版本，不可变内容身份，依赖满足 | 降级、同版本换内容、静默改变 Bundle 成员 |
| Package → Tenant | Standalone 安装选择/官方 profile 只处理固定 official 集；private Module 当前由授权部署 owner 调用 `ProductTenantModuleProfileService::applyInstallationSelection()`，尚无完整用户 CLI/UI；Multi-tenant 由 PlatformOperator 显式开通 TenantModule | 安装/更新或路由接线自动开通 Tenant 或授权成员；把 Platform API 当成 fresh Standalone 登录入口 |
| Module → 成员 | TenantModule enabled 且角色同时拥有权限/数据权限 | 菜单可见性代替后端授权 |
| 应用 scaffold 升级 | 开发分支用签名包只改 managed/generated-managed；app-owned 摘要保持 | 重新生成覆盖、直接在生产 Runtime 合并 scaffold |
| 完整应用 Release → 实例 | tag/commit/tree、依赖 lock 与全部 Package 身份固定后整体部署 | 先改实例或迁移再补 tag；缺失/回退/同版本换内容的 Package |

遇到失败先按[参考入口的错误字段](/reference#错误输出与恢复)处理；需要提交问题时只附
[脱敏诊断包与最小复现](/support)，不要附原始日志或凭据。
