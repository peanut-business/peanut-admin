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

新应用的独立 `application.version` 默认是 `0.1.0`；需要在首次生成时指定其他 SemVer 时，
使用 `--application-version=<semver>`。该值不是 Peanut Admin 产品版本，也不是
`peanut-admin/core` 或 `@peanut-admin/admin` 的依赖版本。生成器以这一值统一写入 release
metadata、changelog、SBOM 根包、后端版本配置与 API fallback、Web/PC/UniApp/Docs 根
package、PC/UniApp 根 lock metadata，以及 UniApp manifest/About；默认 UniApp
`versionName=0.1.0`、`versionCode=10`。

目标必须是不存在或为空的绝对目录。slug 只接受小写 kebab-case；Composer 风格的 package
identity 必须包含 vendor/name。路径穿越、符号链接目标、非空目标、inventory 漂移、未知
变量与未知 transform 均 fail-closed。入口按 inventory 的 `template_version` 选择同版本的
`scaffold/releases/v<version>/scaffold-manifest.json`；release 缺失或版本不符时不会创建目标。

`scaffold/application-template-inventory.json` 对每个可生成的模板源文件给出唯一分类；
`scaffold/**` 中的 source-only inventory、release artifact 与历史证据本身不递归进入应用：

- `managed`：框架与交付基础设施。创建时把逐文件基线复制到
  `.peanut/scaffold-baseline/<template>/files/`；后续升级只能以该基线做显式三方决策。
- `generated-managed`：由创建参数生成或同步的身份/元数据文件；本地修改后也不得被静默覆盖。
- `app-owned`：应用业务、数据库、页面、文档与稳定 Host/override 入口。future scaffold 默认保留。
- `excluded`：Peanut Admin 的历史证据、发布记录、内部设计/治理、缓存/构建/依赖、凭据和本机基础设施。

正式应用会生成合法的空 `plugins.lock`，并默认不启用 Plugin lock。源仓中的
`fixture.delivery-record` manifest、PHP/Web Module 与 lifecycle runner 仅是合同测试证据，
全部保持 `excluded`；应用 owner 安装真实 Plugin 时才写入可解析的不可变身份并显式配置 lock。

生成器在目标目录提交前，会验证 adopted release 的 token、artifact 自摘要、managed 路径
双向全集、逐文件 mode/classification、参数渲染字节和 release managed tree。只有完整等价
才会写入应用：`template` 固定已采用的不可变 release commit/tree/inventory，顶层
`generation_source` 另行记录实际干净生成候选的 commit/tree 与当前 inventory SHA。这样
source-only 或 app-owned 演进无需伪造新的模板身份，而任何 managed 字节变化仍必须先形成
新的不可变 scaffold release。

当前 inventory 采用不可变 scaffold `v1.1.9` release。该 identity 只属于 scaffold 命名空间，
不是 Peanut Admin 产品 Tag/Release；既有 scaffold identity 均保持不变。`v1.1.9`
继承 `v1.1.8` 的双模式 Runtime、application version 与法律 inventory 合同，并把演示数据
实现纳入 managed 升级边界。生产管理端 builder 在执行 Vite 前，
把应用根目录的 `plugins.lock` 精确复制为 `/build/plugins.lock`；Plugin contribution resolver
仍直接读取这份 lock，缺失或无效内容继续 fail-closed，不会回退到默认或忽略 Plugin 状态。
生产镜像同时构建 Standalone 与 multi-tenant 管理端 bundle，并在容器启动时按
`DEPLOYMENT_MODE` 选择；PHP 镜像携带应用自己的 `resources/project-resources.json`，并把受管
`server/database/seed-demo-data.php` 暴露为 `peanut-seed-demo-data`，供数据库环境门禁与显式
演示初始化使用。根 `scripts/seed-demo-data` 仍是 app-owned 兼容入口；登记缺失或与显式部署
目标不一致时仍 fail-closed。

生成的 `.peanut/application-manifest.json` v2 还固定 `application.version`、参数、每个生成文件的 SHA-256、mode、分类、
owner、managed/app-owned 树摘要与 managed baseline 路径。它是
后续 scaffold plan/apply/verify/recovery 的项目锁与三方输入；执行合同和命令见
`docs/scaffold-upgrade.md`。

生成结果没有 `.git`，可直接执行 `git init` 成为独立仓库。连接任何资源前，应用 owner
必须先补全 `resources/project-resources.json`；初始管理员密码仍只允许在空库安装时通过
`ADMIN_INITIAL_PASSWORD` 显式提供。
