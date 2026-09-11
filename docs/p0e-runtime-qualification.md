# P0-E Runtime 资格 Gate

`scripts/p0e-runtime-qualification` 是 3.1.x fixed-candidate 的 eight-group fresh-only qualification gate。它把
create-app、冻结依赖安装、Standalone/Multi-tenant 空库安装、Plugin lifecycle、消费者 Module
v1→v2 生命周期、生产 Compose 和两种部署模式的最小 Chromium smoke 绑定到同一个
commit/tree、worktree、run_id 与项目资源租约。
正式旧实例的 3.0.14→3.1.0 signed same-Edition upgrade、adoption、数据读回与失败恢复不属于 fresh
场景；它们只使用独立的 `consumer-upgrade-qualification` CR03 resource contract，不能借用本 Gate。

日常 PR 不运行这个 Gate；candidate、scaffold identity 与依赖全部冻结后才运行一次。

## 固定资源

本 Gate 只允许使用以下已登记资源：

- Resource ID：`peanut-admin-p0e-mysql84-gate`
- Environment：`development`
- Host endpoint：`192.168.192.2:20183`
- Container endpoint：`host.docker.internal:20189`，只在 Gate 运行期间由
  `peanut-admin-p0e-mysql84-container-tunnel` 提供回环绑定的 SSH 转发；Docker Desktop 不再被
  假定能够直接路由到宿主机的专用网卡地址
- Database namespace：`peanut_admin_development_p0e_<run_id>_`
- Database administration：`peanut-admin-mysql84-remote-admin-cli`，通过 `ssh mac-14` 在
  `peanut-admin-mysql84-development` 容器内运行 MySQL 8.4.10 CLI
- Production-mode HTTP：`127.0.0.1:20190`
- Docs preview：`127.0.0.1:20186`
- Browser CLI：登记资源 `peanut-admin-p0e-playwright-cli`，固定 package
  `@playwright/cli@0.1.18`，固定候选 worktree 路径
  `.local/p0e-browser-cli-0.1.18/playwright-cli`
- Fallback：无

项目日常资源登记为 `resources/project-resources.json`；源仓 Gate 专用的远端管理绑定登记为
`resources/p0e-runtime-qualification.json`。`peanut_admin_development` 是持久开发库，禁止进入
P0-E claim、连接、迁移或清理。runner 只创建本次 run_id 的六个 registered fresh-scenario databases；所有建库、
删库和状态查询均通过已登记的远端容器 CLI 完成，不使用主工作站 MySQL CLI。

生产 Compose Gate 通过 lease overlay 把候选的项目资源登记和环境门禁只读挂载到 PHP/cron，
并把 Git common-dir 中的原子 lease proof 只读挂载到
`/run/peanut-admin/resource-lease`。容器从 proof 文件独立核验 candidate、tree、run_id、scenario、
resource、endpoint 和 expiry；应用制品字节不因 Gate 改写。

## 候选与租约

候选必须是当前干净 worktree 的完整 40 位 `HEAD`。run_id 只接受 1-11 位小写字母或数字。
无资源 plan 验证候选、资格矩阵声明的当前 application migration identities、`target_release`
scaffold identity、数据库名、路径、端口和完整租约集合；不创建目录、不连接数据库，也不启动
端口、容器或浏览器。具体版本和身份只以
`server/tests/fixtures/p0e-runtime-qualification/matrix.json` 为准，本说明不复制易漂移的值。

```bash
candidate="$(git rev-parse HEAD)"
run_id="p0e0816a"
lease="p0e-runtime-${run_id}"

scripts/p0e-runtime-qualification plan \
  --candidate "$candidate" --run-id "$run_id" --lease "$lease" \
  --http-port 20190 --docs-port 20186 \
  --output-dir "$PWD/output/p0e-${run_id}" \
  --cache-dir "/Users/xing/.cache/peanut-admin/p0e-${run_id}"
```

claim 必须精确绑定固定 resource/environment、Host 与 container 两个 endpoint、六个数据库、两种 deployment mode、
HTTP/Docs/数据库隧道三个端口、隧道身份、worktree、candidate tree、compose project、browser session、output/cache 路径和
lease proof 目录。`plan` 会在 claim 前检查固定 Browser CLI 的可执行性和版本；缺项或多项都拒绝运行，
不会启动数据库、容器或生成应用。准备本地工具：

```bash
scripts/p0e-browser-tooling install
scripts/p0e-browser-tooling check
```

P0-E 不扫描其他 worktree 的临时 `pwcli-cache`，也不使用系统 Chrome、用户会话或未登记 fallback。

## CR03 旧实例增补（不属于 P0-E）

`peanut-admin-consumer-upgrade-mysql84-gate` 只承载两个可丢弃的正式 `v3.0.14`
安装实例：`standalone_upgrade` 与 `multi_tenant_upgrade`。它们使用
`peanut_admin_development_cr03_<run_id>_<scenario>`，绝不复用 P0-E fresh 库、持久开发库、旧
演示或生产数据。每一场景必须保留：官方安装包和 manifest SHA-256、正式 key 的外部可信绑定、
25 条 Host ownership adoption 的逐条确认、preflight/apply/verify、业务数据读回，以及 paired
database dump + instance archive 的恢复证据；秘密、Module 和 app-owned bytes 不得进入升级写集。

CR03-01 只能先生成 structural plan。该命令要求一个干净、固定候选，但不 claim、下载、签名、连接
数据库或创建任何实例；它不是可运行或 qualified 的结论。最终资格 claim 仅能在根冻结 main 候选后进行：

```bash
candidate="$(git rev-parse HEAD)"
run_id="<new-lease-bound-run-id>"
scripts/consumer-upgrade-qualification plan \
  --candidate "$candidate" --run-id "$run_id" --lease "consumer-upgrade-${run_id}" \
  --output-dir "$PWD/output/cr03-upgrade-${run_id}" \
  --cache-dir "/Users/xing/.cache/peanut-admin/cr03-upgrade-${run_id}"
```

失败时只保留该 lease 的两个精确 schema、实例、备份、cache 与 output，供一次定向诊断和
recover；成功保存脱敏证据后由 active lease owner 清理其精确资源。无正式 signing resource、
旧 Release asset、外部 trusted key 或 main candidate 时停止，不能回退到 CR02 临时 key 或任何
fresh-only 场景。

### 旧实例升级 runbook

这是 CR03-04 的人工最小执行集，不是第二个执行框架。先以 `gh release download v3.0.14` 下载两个
installer、两个 manifest 和 `UPGRADE_TRUSTED_KEYS.json` 到 active lease cache；`shasum -a 256` 必须逐字
匹配 `server/tests/fixtures/consumer-upgrade-qualification/matrix.json`，再检查 manifest 的 source
commit/tree、Edition 和 deployment mode。下载不匹配、key id 不匹配或可信公钥不在包外入口时停止。

旧安装包内的 v3.0.14 `environment-guard.php` 只能读取该独立实例自己的**固定**资源登记，不能识别
本仓 templated CR03 resource；它是旧实例 bootstrap 的 consumer guard。每个 disposable instance 必须先保留
archive 原始摘要，再在解包实例自己的 `resources/project-resources.json` 把默认未分配 CI 项替换为一个
固定 Host database registration，随后才设置其0600 `server/.env.<run-id>` 并运行
`php server/database/install.php`。该固定记录至少逐字绑定 `stable_resource_id`、`environments=[development]`、
`service_type=mysql`、精确 `database`、Host-only `upstream_endpoint.endpoint_id/host/port/consumers`、
`credential_ref`、`health_check`、`fallback=none` 与 cleanup owner；`.env` 的 resource/endpoint/consumer、
DB host/port/name 和 Edition mode 必须与之相同。这项实例局部注册不改 archive 字节、不能替代本仓资源
lease，也不能冒充 P0-E fresh 场景。

生成实例中的 `server/database/environment-guard.php` 是 `ApplicationCreator` 渲染的固定实例 guard，inventory
把它标为 app-owned；正式升级不会把它替换成本仓 source guard。目标 3.1.0 采用、升级、依赖和 migration
继续由该实例的固定登记/guard保护；本仓 `peanut-admin-consumer-upgrade-mysql84-gate` 和 source guard 只在
外层运维边界核 active lease 的 candidate/tree、两个精确 database/instance/backup roots、HTTP 和 Local
object prefix。二者任一缺失都停止，不复制 source guard 到旧实例绕过 ownership。

在 active lease 后，按每个 Edition 串行执行以下具体步骤，并把所有 stdout/stderr、JSON plan、SHA 和
screenshot/trace 写入该 run 的 output：

1. 将 release assets 下载到 `cache/instances/<scenario>/assets/`，以 fixture 的四个 SHA-256 做逐个比对，
   从 package 外的 trusted-key source 读取 `peanut-admin-release-2026-01` 的 public key，再核对
   `UPGRADE_TRUSTED_KEYS.json` 的 key id 和 public key。解包 installer 前后都记录 archive SHA；解析 manifest
   必须得到 fixture 中的 source commit、tree、Edition 和 deployment mode。任一输入不一致立即停止。
2. 解包旧 installer 到 `cache/instances/<scenario>/v3.0.14/`，按上面的固定字段写实例 registry 和0600环境；
   `server/database/install.php` 的 stdout/exit code、guard读回的 resource/endpoint/database/mode 与旧 application
   manifest一并保存。Standalone创建一个Tenant内管理员；Multi-tenant另创建PlatformOperator、Tenant A/B、
   Tenant Owner与有/无目标权限的两组角色成员，账号标识带本 run ID且秘密只进入0600文件。
3. 通过现有接口固定创建：`POST /adminapi/official.article.category.add`分类、
   `POST /adminapi/official.article.add`文章、`POST /adminapi/config/website/save`设置、一个已启用官方Module state，
   以及app-owned customization文件；Multi-tenant还通过
   `POST /platformapi/tenants/provision`、`POST /platformapi/roles/create`、
   `POST /platformapi/roles/permissions/replace`与Tenant Module enable建立可读回的
   Tenant/RBAC差异。然后只用登记的CR03 MySQL admin/dump工具对精确DB做dump，并在
   `cache/backups/<scenario>/` 创建实例archive；两者SHA-256、业务主键、Tenant/RBAC/Module/customization摘要和
   secret non-echo断言构成恢复前基线。
4. 令 `UPGRADER=<extracted-3.1.0-package>/upgrader/scripts/scaffold-upgrade`；所有采用和普通升级命令只调用这个
   已签名包自带入口。运行 `php "$UPGRADER" adoption-plan --project-root=<instance> --package=<extracted-package>
   --signature-key-id=peanut-admin-release-2026-01`，把stdout复制到output作证据，但从其中读取真实 `plan_path`，即
   instance内 `.peanut/upgrades/plans/<ownership-candidate>.json`。人工逐条确认恰好25个ordered paths和
   `plan_sha256`后，才用该instance内绝对plan路径运行 `adoption-apply`。采用plan与下一步普通升级plan不得混用，
   不接受手工重排、缺项、额外项或archive未声明写集。
5. 仍用 `UPGRADER` 运行 package `preflight` 并从stdout读取新普通升级 `plan_path`，再以此不同的instance内plan
   运行 `apply`、`verify`。按目标package locks安装Composer/npm依赖，然后严格运行
   `php server/database/install.php --migrate --target-version=3.1.0 --dry-run`；确认pending集合后以相同
   `--target-version=3.1.0`执行apply。记录application/instance/generation_source versions、migration ledger、
   定制/Module/secret摘要、Tenant/RBAC与步骤3业务主键读回。
6. 采用恢复单独在metadata写入故障后用 `UPGRADER adoption-recover --plan=<adoption-plan>`，证明manifest/baseline
   回到采用前；普通升级恢复则在`apply`成功、`verify`前注入一次中断，用 `UPGRADER recover --plan=<upgrade-plan>`
   恢复受管文件，再从步骤3校验过的DB dump和instance archive成对restore。恢复后必须读回相同主键、Tenant/RBAC、
   Module与customization摘要；任何差异都停止，不能通过重建合成数据伪造恢复成功。
7. HTTP `127.0.0.1:20190` 和 `cr03/<run-id>/<scenario>/` Local object prefix 均须出现在独立CR03 active lease
   资源中，由同一owner独占且四端串行；Host-only数据库不默认获得P0-E Compose或浏览器资源。Web路由 `/login`：登录后验证文章/分类、
   `/app-setting/website` 保存并重开、菜单/Module/权限、Tenant A→B 拒绝和 Local upload/read/delete。Platform
   路由 `/platform/`：用该 run 的 PlatformOperator 登录，验证 Tenant enable/disable/authorization 与 secret non-echo。
   PC 路由 `/login`、`/information`、`/information/detail/<id>`：验证登录/失效、文章列表/详情/分类及允许保存后重进。
   UniApp H5 路由 `/pages/login/login`、`/pages/news/news`、`/pages/news_detail/news_detail`、`/pages/user/user`：
   验证登录、内容、返回、Tabbar/错误布局。账号只来自该 run 的 0600 setup output；每端把 trace 或 screenshot
   保存到 `output/cr03-upgrade-<run-id>/clients/<surface>/`。无注册 HTTP owner、路由、账号或 Local object prefix 时
   surface 未就绪，不能以页面配置代替。
8. 成功后仅清理 active lease 的两 schema、instance/backup roots、temporary accounts/listener/object prefix；
   失败则保留这些精确坐标和 lease 给一次诊断。OSS/COS/七牛、支付、短信、OAuth 的真实外部动作仍各自依赖
   注册测试账户与授权，不由本 runbook 假称完成。

## Claim 与运行

凭据引用为 `mac-14:/Users/xing/.config/peanut-admin/development-db.env`。runner 会先调用项目
登记的凭据同步脚本，再从本机受限 `server/.env` 读取数据库账户；每个 run 生成独立的
`server/.env.p0e-<run-id>`，PDO、Think ORM 和 Compose 后台进程共同读取该文件。Tenant Owner
与 PlatformOperator 测试账号会随机生成，并只写入该 run 的 0600 cache，以供失败后的同参数
resume 使用。浏览器工具只使用显式受控的 fixed wrapper，不扫描其他工作树或采用任意 cache fallback。以上秘密
均不得写入命令、输出、租约或仓库。

```bash
common=(
  --candidate "$candidate" --run-id "$run_id" --lease "$lease"
  --owner "<owner>" --thread "<thread>" --ttl 43200
  --http-port 20190 --docs-port 20186
  --output-dir "$PWD/output/p0e-${run_id}"
  --cache-dir "/Users/xing/.cache/peanut-admin/p0e-${run_id}"
)

scripts/p0e-runtime-qualification claim "${common[@]}"
scripts/project-resource-lease show --lease "$lease"
scripts/p0e-runtime-qualification run "${common[@]}"
```

## Gate 场景

1. `generated-application`：真实 `scripts/create-app` 从资格矩阵固定的 `target_release` 生成应用；
   Server、Web、PC、UniApp H5 和 Docs 使用锁文件安装并完成最低构建，随后核对
   application/scaffold identity。
2. `standalone-fresh`：在空库执行 Standalone install、幂等 migrate、目标版本适用的完整 migration ledger 与
   fresh-only invariants。
3. `multi-tenant-fresh`：在空库执行 Multi-tenant install、幂等 migrate、目标版本适用的完整 migration ledger 与
   Tenant bootstrap invariants。
4. `plugin-lifecycle`：向生成应用临时铺设 source-only fixture，覆盖 install、重复安装、upgrade
   dry-run、rollback plan、TenantModule/权限、preserve-data uninstall 和失败 migration；结束后
   恢复空 `plugins.lock`、移除 fixture，并核对 app-owned 字节不变。
5. `consumer-module-lifecycle`：从同一正式 scaffold 生成作者与消费者两个独立应用，完成签名
   Module v1→v2 create/check/pack/install/update、Tenant/Package/RBAC 分层、disable/reactivate、
   retire/Purge 与 app-owned 摘要；使用独立数据库，失败时随本 run 保留恢复坐标。
6. `production-compose`：从生成应用构建一次生产镜像，使用 Standalone fresh 数据库通过
   Compose 和 `/healthz`。
7. `standalone-browser`：复用 Standalone Compose，最小 Chromium smoke 覆盖管理端、PC、H5、Docs。
8. `multi-tenant-browser`：复用同一镜像切换到 Multi-tenant fresh 数据库，最小 Chromium smoke
   另覆盖 Tenant 管理员选择和 Instance Platform 登录。该组使用两个不同的 RFC 6761
   `.localhost` Host：`admin.p0e.localhost:20190` 是共享 Tenant Admin 入口，
   `platform.p0e.localhost:20190` 是独立 PlatformOperator 入口；不得把同一个 Host 同时
   配置成两种身份边界。

## 失败恢复与完成

每个 group 通过后立即 checkpoint。失败时 runner 保留数据库、cache、output 和 Compose 取证
资源，写入 `recovery.json` 并 renew lease。先完成一次只读归因；只有候选内容与资格可信性均
未变化、且资格合同明确允许续跑时，才用完全相同参数运行：

```bash
scripts/p0e-runtime-qualification resume "${common[@]}"
```

Host 上执行的安装、Schema、Module 与服务测试只消费 Host endpoint；生产 Compose 只消费 container
endpoint。resume 跳过已通过 group，只重跑失败或未完成组。需要生产 Compose 时，runner 先启动自己持有的
回环 SSH 隧道并验证端口可达；失败与成功终态都会终止该精确子进程。八组全部通过后，runner 停止 Docs listener，
删除本 run_id 的六个数据库、Compose containers/volumes/local images 和 cache，核验所有残留为
零，保留脱敏 output evidence，最后 release lease。失败终态不会自动 release 或清理取证资源。

如果修复需要修改产品 Runtime、Schema、依赖、生成物、fixture、资格脚本、lock 或其他会改变
资格可信性的内容，旧候选和旧 run 只能作为诊断证据，不得 `resume`。执行者必须先按
`AGENT_EXECUTION_RULES.md` 回到 Development mode，在实际失败路径上完成聚焦验证；清理旧 run
的精确资源后，再以新 candidate 和新 run_id 进入资格。同一 group 第二次失败时，必须先完成
边界矩阵和边界级修复，不得继续用完整 P0-E 逐项发现相邻问题。
