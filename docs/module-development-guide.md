# 模块开发指南

## 新增模块

```bash
php think module:create <module.key>
```

该命令会生成与 `fixture.delivery-record` 对齐的前后端骨架，并在写入前校验 module key、
key 派生路径和前端入口。不要手工维护第二套模板或组件清单。

Standalone 开发环境也可以在 `/dev-tools/modules` 点击“创建模块”。Web 和 CLI 都调用同一个
`ModuleScaffoldGenerator` 与同一组模板；两者只负责生成必要目录和文件，不会自动安装、开通
TenantModule 或授予成员权限。

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
