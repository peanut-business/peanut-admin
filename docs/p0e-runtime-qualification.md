# P0-E Runtime 资格 Gate

`scripts/p0e-runtime-qualification` 是固定候选唯一全量入口。它把 create-app、依赖冻结安装、
四端最低构建、安装/升级/恢复、Plugin lifecycle、生产 Compose/HTTP 和两种部署模式的真实
Chromium smoke 绑定到同一个 commit/tree、worktree、run_id 与项目资源租约。日常 PR 不运行
这个 Gate；内容与依赖全部冻结后只运行一次。

## 固定资源

日常/应用资源登记仍是 `resources/project-resources.json`；仅源仓 Gate 使用的远端管理工具及
它与 CompanyOS allocation 的绑定登记在 source-only
`resources/p0e-runtime-qualification.json`，该文件不会进入 create-app 生成应用。

- Database resource：`peanut-admin-p0e-mysql84-gate`
- Environment：`development`
- Host/container endpoint：`192.168.192.2:20183`
- Database namespace：`peanut_admin_development_p0e_<run_id>_`
- Database administration：`peanut-admin-mysql84-remote-admin-cli`，通过 `ssh mac-14` 在已登记
  的 `peanut-admin-mysql84-development` 容器内运行 MySQL 8.4.10 CLI
- Production-mode HTTP：`127.0.0.1:20190`
- Docs preview：`127.0.0.1:20186`
- Fallback：无

`peanut_admin_development` 是持久开发库，明确禁止进入 P0-E claim、连接、迁移或清理。runner
只创建登记中的九个 scenario 数据库，并且只删除与本次精确 run_id 相符的这九个名字。
建库、删库、状态查询、dump 和 restore 不依赖主工作站的 `mysql`/`mysqldump`。runner 在
active lease 下先核验远端容器的精确 image、running/healthy 状态、CLI 绝对路径与 8.4.10
版本，再通过 SSH + 远端 `docker exec` 执行；root 凭据只在容器环境内使用，不传回主工作站。
PHP 安装器和应用 Runtime 仍按登记 endpoint 使用项目数据库账号连接局域网 MySQL。

## 候选与租约

候选必须是当前干净 worktree 的完整 40 位 `HEAD`。run_id 只接受 1–11 位小写字母或数字。
先运行无资源 plan，再原子 claim；claim 的 resource set 必须精确包含：

- resource id、development environment、local-production-preview target、host/container consumer、
  固定 endpoint、run_id、candidate tree；
- 九个精确 database name、standalone/multi-tenant 两种 deployment mode；
- 通用端口冲突键 `port=20190`/`port=20186` 与语义键 `http-port`/`docs-port`；
- worktree、Gate、compose project、browser session、绝对 output/backup/cache path；
- Git common-dir 中的 active lease proof directory。

生产 PHP 容器没有 Git common-dir。runner 生成一个不提交的 Compose overlay，把真实 lease
目录只读挂载到 `/run/peanut-admin/resource-lease`，只传
`PEANUT_RESOURCE_LEASE_PROOF` 指向该目录。entrypoint/guard 从 `metadata.tsv` 与
`resources.tsv` 独立解析 candidate、tree、run_id、scenario、resource 和 expiry，并与当前
`DB_NAME`、`DEPLOYMENT_MODE`、endpoint 交叉核对；不使用额外 proof 环境变量自证。lease
过期会被 epoch 检查拒绝，release 会删除 proof 文件；runner 只在容器停止和残留归零后
release。

## 无资源 plan

以下路径只是示例；真实调用必须让三个 basename 都包含同一个 exact run_id：

```bash
candidate="$(git rev-parse HEAD)"
run_id="p0e0814a"
lease="p0e-runtime-${run_id}"

scripts/p0e-runtime-qualification plan \
  --candidate "$candidate" --run-id "$run_id" --lease "$lease" \
  --http-port 20190 --docs-port 20186 \
  --output-dir "$PWD/output/p0e-${run_id}" \
  --backup-dir "/Users/xing/.local/state/peanut-admin/p0e-backup-${run_id}" \
  --cache-dir "/Users/xing/.cache/peanut-admin/p0e-${run_id}"
```

plan 只验证候选、登记、54 条当前 migration、`v1.0.0`/`v1.1.0` tag、数据库名、路径、端口
和完整租约集合；不创建目录、不连接数据库，也不启动端口、容器或浏览器。

## Claim 与运行

凭据引用仍是
`mac-14:/Users/xing/.config/peanut-admin/development-db.env`。操作者在本机凭据提供器中加载值，
再映射到下列环境变量；不得把值写入命令、输出、租约或仓库：

```text
P0E_DB_USER
P0E_DB_PASSWORD
P0E_ADMIN_INITIAL_EMAIL
P0E_ADMIN_INITIAL_PASSWORD
P0E_PLATFORM_INITIAL_EMAIL
P0E_PLATFORM_INITIAL_PASSWORD
P0E_PLAYWRIGHT_CLI
```

`P0E_DB_USER`/`P0E_DB_PASSWORD` 仅供 PHP 安装器和应用 Runtime 使用；数据库管理操作使用
已登记远端容器内的管理身份。`P0E_PLAYWRIGHT_CLI` 必须是显式可执行的 Playwright CLI wrapper。Chromium 阶段使用独立
session、先 snapshot，再运行固定 smoke fixture；管理员密码只从进程环境读取。

```bash
common=(
  --candidate "$candidate" --run-id "$run_id" --lease "$lease"
  --owner "<owner>" --thread "<thread>" --ttl 43200
  --http-port 20190 --docs-port 20186
  --output-dir "$PWD/output/p0e-${run_id}"
  --backup-dir "/Users/xing/.local/state/peanut-admin/p0e-backup-${run_id}"
  --cache-dir "/Users/xing/.cache/peanut-admin/p0e-${run_id}"
)

scripts/p0e-runtime-qualification claim "${common[@]}"
scripts/project-resource-lease show --lease "$lease"
scripts/p0e-runtime-qualification run "${common[@]}"
```

## Gate 场景

1. 真实 `scripts/create-app`，生成应用与候选 Server 分别 frozen Composer install/autoload；
   生成应用 Web、PC、UniApp H5、Docs frozen install 与最低 build。
2. Standalone fresh 与 multi-tenant fresh：install、一次幂等 migrate、54-current 精确 checksum
   ledger 与 Tenant/bootstrap invariants。
3. `v1.0.0` 与 `v1.1.0` 固定 tag 安装后向当前候选前滚，再验证 54-current。
4. Migration fault source 在备份后注入失败 ledger；migrate 必须拒绝。备份恢复到独立 restore
   database 后重新做完整 ledger/invariant 检查，并记录备份 SHA-256。
5. Plugin fixture 覆盖 install、重复安装、upgrade dry-run、rollback plan、TenantModule/权限、
   preserve-data uninstall 和失败 migration；fixture 自行恢复目录数据，runner 再验证数据库。
6. 候选生产镜像只 build 一次；Standalone 与 multi-tenant 分别复用镜像、命中各自 P0-E
   browser database。每种模式真实 Chromium 覆盖管理端、PC、H5、Docs；multi-tenant 另覆盖
   Tenant 管理员选择和 Instance Platform 登录。

## 失败恢复与完成

每个 group 通过后立即 checkpoint。失败时 runner 不删除数据库、backup、cache、output 或
Compose 取证资源，写入 `recovery.json` 并 renew lease。完成一次只读归因及最小修复后，用
完全相同参数运行：

```bash
scripts/p0e-runtime-qualification resume "${common[@]}"
```

resume 跳过已经通过的 group，只重跑失败/未完成组。成功终态停止 Docs listener、删除本
run_id 的九个数据库、Compose containers/volumes/local images、backup/cache 和 runner 创建的
候选 vendor，核验数据库残留为 0，保留脱敏 output evidence，最后 release lease。任何失败
终态都不会自动 release；未经 named owner 的明确恢复动作，不清理失败资源。
