# 发布工程

## Consumer-ready 准备检查

在版本准备、scaffold seal、固定资格或正式发布前，先运行内部只读入口
`scripts/consumer-ready-control preflight --phase <prepare|seal|qualify|release>`。它集中检查候选、
版本、scaffold/fixture、资源命名、工具和资格身份，但不 claim 资源或执行 Gate。功能、边界和升级路线见
[`Consumer-ready 最小交付控制器`](operations/consumer-ready-control.md)。

## 生产构建

```bash
scripts/package-release.sh
```

该脚本编译当前源码树中的后端和管理端生产成品。生产运行时不执行逐模块安装，也不发现
`plugins.lock` 之外的模块；`/dev-tools` 页面和开发工具代码由生产构建门禁剔除。

## 双 Edition 安装包与升级包

固定候选完成版本、inventory、依赖锁、Module lock 和 Edition profile 收口后，核心维护者从
同一个完整 commit 生成两套安装包与两套升级包：

```bash
php scripts/build-edition-installers \
  --version=X.Y.Z \
  --source-commit=<full-candidate-commit> \
  --output=/absolute/path/to/release-artifacts

php scripts/build-edition-upgrades \
  --version=X.Y.Z \
  --minimum-source-version=<oldest-supported-X.Y.Z> \
  --source-commit=<same-full-candidate-commit> \
  --output=/absolute/path/to/release-artifacts \
  --signing-key-id=<official-release-key-id> \
  --signing-secret-key-file=/credential-store/release-ed25519-secret.base64
```

私钥路径只来自发布环境的凭据引用，不写入仓库、计划、日志或 Release 附件。安装/升级 archive、
外部 manifest、SHA-256、Edition、source commit/tree 和 inventory 必须一致；升级包内只包含自带
升级器、目标受管文件与完整 migration 列表。Standalone 与 Multi-tenant 分别生成，不能用一个
包在运行时切换 Edition。

开发分支生成物只能用于聚焦验证。正式附件必须来自后续唯一冻结、通过 L2 资格并合入 `main`
的候选；不得把开发 key、移动分支或本地 output 目录发布给用户。

## 内部 `plugin:*` 命令

普通模块开发者使用 `module:*` / `bundle:*` 入口。以下命令属于发布工程或既有内部入口，不是第二套
模块状态、依赖图或权限源，也不应进入新项目的日常开发流程：

- `plugin:make`：生成机器可读 Plugin manifest；对开发者由 `module:pack` 封装。
- `plugin:lock`：生成或核验生产部署的 `plugins.lock`；开发期无需执行。
- `plugin:install`、`plugin:upgrade`、`plugin:rollback`、`plugin:uninstall`：安装平面的内部命令；普通
  开发者使用 `module:install-package` / `module:uninstall-package`。
- `plugin:reconcile`：由仓库唯一 `scripts/deploy-release` 在 migration 后以
  `--official-locked` 调用，用于让部署数据库与已校验的 official lock 幂等对齐。普通 Module
  自动化不得直接依赖该内部命令，也不提供长期兼容承诺。

`plugins.lock` 和 `plugin.json` 是发布、部署身份的机器可读证据；模块业务声明仍只来自
`module.json` 及其引用的 Resources 文件。

部署脚本在替换现有目标或执行破坏性 fresh 之前，必须先从刚构建的候选 PHP 镜像运行只读
安装预检和 `plugin:lock --check`。源码静态检查、宿主 vendor 或旧容器都不能替代这一步；
候选镜像若缺少发布元数据、Plugin lock/schema 或安装基线，部署在目标变更前停止。

## 固定应用升级执行

Platform 应用升级中心只创建服务器固定 source/target 身份的 `ops.upgrade.execute` 任务。
生产部署 owner 在取得具体动作授权、资源 lease 并完成登记健康检查后，才可从登记 checkout
运行 `scripts/ops-upgrade-worker --once`。该 worker 复用现有备份/隔离恢复 worker、维护 store
和唯一 `scripts/deploy-release <tag> --target production --update --apply` 入口；浏览器和任务
payload 均不能覆盖路径、命令、目标、主机或凭据。详细状态机与失败停止语义见
[`应用升级执行合同`](architecture/product-closure-upgrade-execution.md)。
