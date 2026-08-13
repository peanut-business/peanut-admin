# Peanut Admin 数据库资源

## 权威登记与唯一拓扑

本项目版本化机器可读事实源是 `resources/project-resources.json`；根 `AGENTS.md` 是入口。
CompanyOS 的 `company-os/resources/development-infrastructure.yaml` 仅是 allocation 来源证据，
不能替代项目登记。Compose、`scripts/local-stack.sh`、数据库门禁与环境探针都从项目登记
选择资源，不在脚本中维护另一份地址常量。

Peanut Admin 日常开发和本机生产模式预览只使用公司开发基础设施上的数据库：

- 资源 ID：`peanut-admin-mysql84-development`
- 主机：`mac-14`（`192.168.192.2`）
- 端口：`20183`
- 数据库：`peanut_admin_development`
- MySQL：`8.4.10`
- 凭证事实源：`mac-14:/Users/xing/.config/peanut-admin/development-db.env`
- 宿主机入口：`peanut-admin-mysql84-development-host-direct`，
  `192.168.192.2:20183`
- Docker Desktop 容器入口：`peanut-admin-mysql84-development-docker-direct`，
  `192.168.192.2:20183`
- fallback：`none`

本机只运行 PHP、Nginx、管理端、PC、UniApp H5 和文档服务，不运行 Peanut Admin
MySQL。历史 `192.168.192.2:3306/peanut_admin` 没有迁移账本，不是当前开发资源，禁止
应用连接。公司 allocation 的权威登记位于
`company-os/resources/development-infrastructure.yaml`。

当前容器入口与宿主入口同址是经过 Docker Desktop 真实容器连接验证的登记事实。容器
不会把远端局域网地址解释为宿主 localhost，也不需要 TCP bridge。若网络拓扑未来改变，
必须先更新项目资源登记及其健康检查，不能静默尝试其他地址或 mock。

线上生产使用生产服务器自己的 `peanut-admin-production-bundled-mysql84`，不连接
`192.168.192.2`。`PEANUT_DEPLOYMENT_TARGET`、`PEANUT_DATABASE_RESOURCE_ID` 和
`DB_HOST/DB_PORT/DB_NAME` 必须同时匹配登记合同，否则应用拒绝启动。

## 使用

首次或资源重建由资源 owner 执行：

```bash
./scripts/company-development-database.sh provision
```

普通开发只需：

```bash
./scripts/local-stack.sh dev-up
./scripts/local-stack.sh database-status
```

只检查宿主机直连与迁移账本：

```bash
./scripts/local-stack.sh database-host-status
```

`dev-up` 会用登记的 `/opt/homebrew/bin/php` 启动并跟踪宿主 API，宿主 PHP 直接连接登记
数据库；Web、PC、Mobile 与 Nginx 容器通过 `host.docker.internal:${PHP_PORT}` 访问 API，并显式
清空代理、把宿主 API 与数据库地址放入 `NO_PROXY`。空库使用当前安装器创建完整 schema 和种子，已有库执行
`database/migrate.php`。服务开始监听前还会验证迁移文件集合、SHA-256、唯一 root
管理员、菜单、配置和默认 Tenant bootstrap。任何缺失、额外、失败或被改写的迁移都会
阻止启动。

本机生产模式预览使用：

```bash
./scripts/local-stack.sh prod-up
```

它标记为 `local-production-preview` 并连接同一个公司开发库；真正线上配置必须标记为
`production` 并使用生产数据库资源 ID。生产容器不会自动执行待处理迁移，发布流程必须
先备份、显式运行 `database/migrate.php`，然后应用启动门禁确认数据库与发布代码一致。
Docker PHP 只用于此生产模式预览、生产构建和明确要求容器等价性的 Gate，不是日常
`dev-up` 的后端 Runtime。

`201xx` 是项目登记的本地监听默认值，不是不可覆盖常量。`.local/stack.env`（或
`PEANUT_LOCAL_ENV_FILE`）是单次运行的端口事实源；已有覆盖不会被 `ensure_env` 重写。
生产服务器的实际默认端口仍是 `18092`，本地 production preview 的登记默认才是 `20190`。
