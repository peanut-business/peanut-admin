# P0-E Runtime 资格 Gate

`scripts/p0e-runtime-qualification` 是 2.0 fresh-only 固定候选的全量发布资格入口。它把
create-app、冻结依赖安装、Standalone/Multi-tenant 空库安装、Plugin lifecycle、生产 Compose
和两种部署模式的最小 Chromium smoke 绑定到同一个 commit/tree、worktree、run_id 与项目资源租约。
1.x 数据采用、原地前滚、migration fault recovery 和 scaffold upgrade 不属于 2.0.0 支持面，
也不进入本 Gate。

日常 PR 不运行这个 Gate；2.0 内容、scaffold identity 与依赖全部冻结后只运行一次。

## 固定资源

本 Gate 只允许使用以下已登记资源：

- Resource ID：`peanut-admin-p0e-mysql84-gate`
- Environment：`development`
- Host/container endpoint：`192.168.192.2:20183`
- Database namespace：`peanut_admin_development_p0e_<run_id>_`
- Database administration：`peanut-admin-mysql84-remote-admin-cli`，通过 `ssh mac-14` 在
  `peanut-admin-mysql84-development` 容器内运行 MySQL 8.4.10 CLI
- Production-mode HTTP：`127.0.0.1:20190`
- Docs preview：`127.0.0.1:20186`
- Fallback：无

项目日常资源登记为 `resources/project-resources.json`；源仓 Gate 专用的远端管理绑定登记为
`resources/p0e-runtime-qualification.json`。`peanut_admin_development` 是持久开发库，禁止进入
P0-E claim、连接、迁移或清理。runner 只创建本次 run_id 的五个 scenario 数据库；所有建库、
删库和状态查询均通过已登记的远端容器 CLI 完成，不使用主工作站 MySQL CLI。

生产 Compose Gate 通过 lease overlay 把候选的项目资源登记和环境门禁只读挂载到 PHP/cron，
并把 Git common-dir 中的原子 lease proof 只读挂载到
`/run/peanut-admin/resource-lease`。容器从 proof 文件独立核验 candidate、tree、run_id、scenario、
resource、endpoint 和 expiry；应用制品字节不因 Gate 改写。

## 候选与租约

候选必须是当前干净 worktree 的完整 40 位 `HEAD`。run_id 只接受 1-11 位小写字母或数字。
无资源 plan 验证候选、3 条 2.0 基线后 migration、`v2.0.0` scaffold identity、数据库名、路径、
端口和完整租约集合；不创建目录、不连接数据库，也不启动端口、容器或浏览器。

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

claim 必须精确绑定固定 resource/environment/endpoint、五个数据库、两种 deployment mode、
两个端口、worktree、candidate tree、compose project、browser session、output/cache 路径和
lease proof 目录。缺项或多项都拒绝运行。

## Claim 与运行

凭据引用为 `mac-14:/Users/xing/.config/peanut-admin/development-db.env`。操作者通过本机凭据
提供器映射以下变量；不得把值写入命令、输出、租约或仓库：

```text
P0E_DB_USER
P0E_DB_PASSWORD
P0E_ADMIN_INITIAL_EMAIL
P0E_ADMIN_INITIAL_PASSWORD
P0E_PLATFORM_INITIAL_EMAIL
P0E_PLATFORM_INITIAL_PASSWORD
P0E_PLAYWRIGHT_CLI
```

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

1. `generated-application`：真实 `scripts/create-app` 生成 2.0.0 应用；Server、Web、PC、UniApp
   H5 和 Docs 使用锁文件安装并完成最低构建，随后核对 application/scaffold identity。
2. `standalone-fresh`：在空库执行 Standalone install、幂等 migrate、3-current ledger 与
   fresh-only invariants。
3. `multi-tenant-fresh`：在空库执行 Multi-tenant install、幂等 migrate、3-current ledger 与
   Tenant bootstrap invariants。
4. `plugin-lifecycle`：向生成应用临时铺设 source-only fixture，覆盖 install、重复安装、upgrade
   dry-run、rollback plan、TenantModule/权限、preserve-data uninstall 和失败 migration；结束后
   恢复空 `plugins.lock`、移除 fixture，并核对 app-owned 字节不变。
5. `production-compose`：从生成应用构建一次生产镜像，使用 Standalone fresh 数据库通过
   Compose 和 `/healthz`。
6. `standalone-browser`：复用 Standalone Compose，最小 Chromium smoke 覆盖管理端、PC、H5、Docs。
7. `multi-tenant-browser`：复用同一镜像切换到 Multi-tenant fresh 数据库，最小 Chromium smoke
   另覆盖 Tenant 管理员选择和 Instance Platform 登录。

## 失败恢复与完成

每个 group 通过后立即 checkpoint。失败时 runner 保留数据库、cache、output 和 Compose 取证
资源，写入 `recovery.json` 并 renew lease。完成一次只读归因及最小修复后，用完全相同参数运行：

```bash
scripts/p0e-runtime-qualification resume "${common[@]}"
```

resume 跳过已通过 group，只重跑失败或未完成组。七组全部通过后，runner 停止 Docs listener，
删除本 run_id 的五个数据库、Compose containers/volumes/local images 和 cache，核验所有残留为
零，保留脱敏 output evidence，最后 release lease。失败终态不会自动 release 或清理取证资源。
