# P0-E Runtime 资格 Gate

`scripts/p0e-runtime-qualification` 是 2.0 fresh-only 固定候选的全量发布资格入口。它把
create-app、冻结依赖安装、Standalone/Multi-tenant 空库安装、Plugin lifecycle、消费者 Module
v1→v2 生命周期、生产 Compose 和两种部署模式的最小 Chromium smoke 绑定到同一个
commit/tree、worktree、run_id 与项目资源租约。
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
- Browser CLI：登记资源 `peanut-admin-p0e-playwright-cli`，固定 package
  `@playwright/cli@0.1.18`，固定候选 worktree 路径
  `.local/p0e-browser-cli-0.1.18/playwright-cli`
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

claim 必须精确绑定固定 resource/environment/endpoint、五个数据库、两种 deployment mode、
两个端口、worktree、candidate tree、compose project、browser session、output/cache 路径和
lease proof 目录。`plan` 会在 claim 前检查固定 Browser CLI 的可执行性和版本；缺项或多项都拒绝运行，
不会启动数据库、容器或生成应用。准备本地工具：

```bash
scripts/p0e-browser-tooling install
scripts/p0e-browser-tooling check
```

P0-E 不扫描其他 worktree 的临时 `pwcli-cache`，也不使用系统 Chrome、用户会话或未登记 fallback。

## Claim 与运行

凭据引用为 `mac-14:/Users/xing/.config/peanut-admin/development-db.env`。runner 会先调用项目
登记的凭据同步脚本，再从本机受限 `server/.env` 读取数据库账户；每个 run 生成独立的
`server/.env.p0e-<run-id>`，PDO、Think ORM 和 Compose 后台进程共同读取该文件。Tenant Owner
与 PlatformOperator 测试账号会随机生成，并只写入该 run 的 0600 cache，以供失败后的同参数
resume 使用。浏览器工具优先使用显式受控路径，否则使用本机已缓存的 Playwright CLI。以上秘密
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

resume 跳过已通过 group，只重跑失败或未完成组。八组全部通过后，runner 停止 Docs listener，
删除本 run_id 的六个数据库、Compose containers/volumes/local images 和 cache，核验所有残留为
零，保留脱敏 output evidence，最后 release lease。失败终态不会自动 release 或清理取证资源。

如果修复需要修改产品 Runtime、Schema、依赖、生成物、fixture、资格脚本、lock 或其他会改变
资格可信性的内容，旧候选和旧 run 只能作为诊断证据，不得 `resume`。执行者必须先按
`AGENT_EXECUTION_RULES.md` 回到 Development mode，在实际失败路径上完成聚焦验证；清理旧 run
的精确资源后，再以新 candidate 和新 run_id 进入资格。同一 group 第二次失败时，必须先完成
边界矩阵和边界级修复，不得继续用完整 P0-E 逐项发现相邻问题。
