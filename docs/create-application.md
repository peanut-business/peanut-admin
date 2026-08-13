# 创建独立应用

正式入口使用仓库自带的 PHP CLI；它只读取固定 inventory 并写入目标目录，不连接数据库、
服务、端口或容器。运行需要 PHP 8.3 与 Git，且源 checkout 必须位于一个干净提交上：

```bash
php scripts/create-app \
  --name="Acme Console" \
  --slug=acme-console \
  --package=acme/acme-console \
  --target=/absolute/path/to/acme-console
```

目标必须是不存在或为空的绝对目录。slug 只接受小写 kebab-case；Composer 风格的 package
identity 必须包含 vendor/name。路径穿越、符号链接目标、非空目标、inventory 漂移、未知
变量与未知 transform 均 fail-closed。

`scaffold/application-template-inventory.json` 对模板 checkout 的每个 Git 跟踪文件给出唯一
分类：

- `managed`：框架与交付基础设施。创建时把逐文件基线复制到
  `.peanut/scaffold-baseline/<template>/files/`；后续升级只能以该基线做显式三方决策。
- `generated-managed`：由创建参数生成或同步的身份/元数据文件；本地修改后也不得被静默覆盖。
- `app-owned`：应用业务、数据库、页面、文档与稳定 Host/override 入口。future scaffold 默认保留。
- `excluded`：Peanut Admin 的历史证据、发布记录、内部设计/治理、缓存/构建/依赖、凭据和本机基础设施。

生成的 `.peanut/application-manifest.json` 固定模板版本、源 commit/tree、参数、每个生成文件
的 SHA-256、分类、owner、managed/app-owned 树摘要与 managed baseline 路径。它是后续
scaffold apply/三方合并工作的输入；本入口不实现 apply、recovery 或跨版本兼容性证明。

生成结果没有 `.git`，可直接执行 `git init` 成为独立仓库。连接任何资源前，应用 owner
必须先补全 `resources/project-resources.json`；初始管理员密码仍只允许在空库安装时通过
`ADMIN_INITIAL_PASSWORD` 显式提供。
