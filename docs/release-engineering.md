# 发布工程

## 生产构建

```bash
scripts/package-release.sh
```

该脚本编译当前源码树中的后端和管理端生产成品。生产运行时不执行逐模块安装，也不发现
`plugins.lock` 之外的模块；`/dev-tools` 页面和开发工具代码由生产构建门禁剔除。

## 内部 `plugin:*` 命令

普通模块开发者使用 `module:*` / `bundle:*` 入口。以下命令属于发布工程或既有内部入口，不是第二套
模块状态、依赖图或权限源，也不应进入新项目的日常开发流程：

- `plugin:make`：生成机器可读 Plugin manifest；对开发者由 `module:pack` 封装。
- `plugin:lock`：生成或核验生产部署的 `plugins.lock`；开发期无需执行。
- `plugin:install`、`plugin:upgrade`、`plugin:rollback`、`plugin:uninstall`：安装平面的内部命令；普通
  开发者使用 `module:install-package` / `module:uninstall-package`。
- `plugin:reconcile`：当前仍有历史注册入口，但目标架构已将其幂等对齐能力并入统一 applier；不要在新
  自动化中依赖该命令，也不提供长期兼容承诺。

`plugins.lock` 和 `plugin.json` 是发布、部署身份的机器可读证据；模块业务声明仍只来自
`module.json` 及其引用的 Resources 文件。

## 固定应用升级执行

Platform 应用升级中心只创建服务器固定 source/target 身份的 `ops.upgrade.execute` 任务。
生产部署 owner 在取得具体动作授权、资源 lease 并完成登记健康检查后，才可从登记 checkout
运行 `scripts/ops-upgrade-worker --once`。该 worker 复用现有备份/隔离恢复 worker、维护 store
和唯一 `scripts/deploy-release <tag> --target production --update --apply` 入口；浏览器和任务
payload 均不能覆盖路径、命令、目标、主机或凭据。详细状态机与失败停止语义见
[`应用升级执行合同`](architecture/product-closure-upgrade-execution.md)。
