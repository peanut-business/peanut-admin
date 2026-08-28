# 模块开发指南

## 新增模块

```bash
php think module:create <module.key>
```

该命令会生成与 `fixture.delivery-record` 对齐的前后端骨架、公开 Commands 合同、append-only
migration 说明，以及位于 `server/tests/Modules/<Vendor>/<Module>/` 的 Tenant 安全测试骨架；写入前
会校验 module key、key 派生路径和前端入口。不要手工维护第二套模板或组件清单。

Standalone 开发环境也可以在 `/dev-tools/modules` 点击“创建模块”。Web 和 CLI 都调用同一个
`ModuleScaffoldGenerator` 与同一组模板；两者只负责生成必要目录和文件，不会自动安装、开通
TenantModule 或授予成员权限。

测试骨架不会伪造已通过结果。先实现同目录的 `TenantSecurityDriver.php`，把它接到真实
Application Service、ModuleGuard、权限 Repository 与隔离 migration fixture，然后运行
`php server/tests/Modules/<Vendor>/<Module>/TenantSecurityTest.php`。固定场景包括 A/B Tenant、
payload/resource 伪造 ID、TenantModule 停用、成员撤权，以及 migration 失败后不得 active 或无修复重放。

## 开发期工作流

1. 修改模块的 `module.json`、`Resources/permissions.json`、`Resources/menus.json` 和设置定义。
2. 在 development、debug、Standalone 环境运行：

   ```bash
   php think module:sync
   # 或只同步一个模块
   php think module:sync --module=<module.key>
   ```

3. 菜单、权限和设置会由同一个 catalog applier 同步到开发库并失效相关缓存，无需生成或更新
   `plugins.lock`。

`module.json` 是模块 key、依赖、资源路径和前端入口的唯一真值。开发期不执行
`plugin:make`、`plugin:lock` 或 `plugin:install`。

## 打包与分发

```bash
# 单模块包
php think module:pack <module.key>

# 多模块能力包
php think bundle:pack <bundle.key> <version> <module.key1> <module.key2>
```

命令会输出自包含 `.tar` 的路径和 SHA-256。多模块 Bundle 的成员共享同一个包身份，安装、停用、
退役和 Purge 都按整个 Bundle 处理，不能拆装单个成员。

使用任一成员 module key 卸载时，CLI 的首次调用只返回预览；其中 `confirm_plan.package_key` 与
`affected_modules` 就是必须人工确认的完整 Bundle 范围。确认后把原样的 `confirm_plan` 写入文件，
并同时传回预览给出的 `plan_digest`。不得删减 `affected_modules` 后执行。

Purge 预览会按完整 Bundle 检查 owned table 的外部外键；任一成员仍被 Bundle 外表引用时，整个
Purge 以 `MODULE_OWNED_TABLE_EXTERNAL_REFERENCE` 拒绝，但仍可先执行不删业务表和账本的 retire。
这类外键属于物理删除的数据完整性停止线，不是业务依赖。只有 `module.json.dependencies` 中的显式
关系会以 `MODULE_DEPENDENT_INSTALLED` 阻止停用或退役。

## 安装到目标环境

运行时安装只允许 development、debug、Standalone 实例工具环境：

```bash
php think module:install-package <path-to-tar> --sha256=<expected-hash>
```

交付或生产环境由发布脚本编译当前代码树，不执行运行时安装，也不扫描 `plugins.lock` 之外的模块。
安装包不会自动开通 `TenantModule`，也不会给租户成员授予 RBAC 权限。

安装过程使用同一 Package 的互斥锁和 durable 状态前滚：已完成 migration 不会重做，catalog 等
幂等步骤可在原包重试时继续。若 MySQL DDL 中断后账本只能确认 `applying/failed`，系统不会猜测
或重放原 SQL；发布更高不可变版本，保留原 migration 并追加带
`-- peanut-admin-repairs: <完整旧 migration key>` 头的幂等修复 migration，安装器会从该修复点
继续并保留旧失败账本作为证据。

## 显式更新已安装 Package

更新不会复用 install 猜测意图。先用同一个受信 archive 做 dry-run，再显式执行：

```bash
php think module:update-package <path-to-v2.tar> \
  --sha256=<expected-hash> --dry-run

php think module:update-package <path-to-v2.tar> \
  --sha256=<expected-hash>
```

该入口与安装一样，只允许 development、debug、Standalone 实例工具环境。dry-run 会验证 archive、
签名、manifest、依赖、版本、Bundle 成员和目标身份，返回 source/target plan；它不会改写 managed
文件、`plugins.lock`、Plugin/Module installation、migration ledger、TenantModule 或 RBAC。

更新只接受同一 Package key 和同一 Bundle 成员集合。同版本不同内容以
`PACKAGE_VERSION_IDENTITY_CONFLICT` 拒绝，低版本以 `PLUGIN_DOWNGRADE_REJECTED` 拒绝。执行期间
managed 文件、lock、migration 与 catalog 使用同一个 Package lock；migration 已进入不可逆失败
状态时返回 `PACKAGE_UPDATE_RECOVERY_REQUIRED` 和 opaque recovery pointer，禁止自动降级或猜测
回放旧 DDL。

这不是生产操作入口。交付环境的维护、配对备份、隔离恢复验证、审计、smoke 和恢复指针由后续
deployment-owned CLI/worker 编排；生产 HTTP 不接受 archive、路径、URL、命令或目标资源。

## 卸载

```bash
# 默认 retire：软退役 catalog，保留业务数据
php think module:uninstall-package <module.key>

# Purge 第一步：取得 preview 中的 confirm_plan 和 plan_digest
php think module:uninstall-package <module.key> --purge

# Purge 第二步：用未改动的计划和摘要确认执行
php think module:uninstall-package <module.key> --purge \
  --confirm-plan-file=<preview-confirm-plan.json> \
  --confirm-plan-digest=<preview-plan-digest>
```

Purge 会显式预览业务表、migration 账本、catalog 和 RBAC 绑定的删除范围。确认计划变化时命令会
fail-closed，必须重新预览。

实例基础能力可在 `module.json` 声明 `"lifecycle": {"protected": true}`。受保护 Module 所在的
整个 Package/Bundle 都不允许停用、退役或 Purge。该产品保护策略与业务依赖、租户开通是三套
独立规则；当前首先保护 `official.file`。

## 权限命名规范

所有权限 key（包括 list、read、detail 等读取权限）都必须以完整 module key 为前缀：

- ✅ `official.article.list`
- ✅ `acme.crm.customer.detail`
- ❌ `article.list`

权限声明只维护在模块的 `Resources/permissions.json`，菜单和后端路由逐字符引用同一个 key；前端
只消费后端授权结果，不是授权真值。

## 前端入口

`module.json.frontend.entry` 必须逐字符等于从 module key 派生的路径。例如：

- module key：`official.article`
- frontend entry：`web/src/modules/official-article/contribution.ts`

打包和安装 preflight 都会校验该等价关系，不一致时拒绝继续。
