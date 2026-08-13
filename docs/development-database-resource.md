# Peanut Admin 数据库资源

## 唯一拓扑

Peanut Admin 日常开发和本机生产模式预览只使用公司开发基础设施上的数据库：

- 资源 ID：`peanut-admin-mysql84-development`
- 主机：`mac-14`（`192.168.192.2`）
- 端口：`20183`
- 数据库：`peanut_admin_development`
- MySQL：`8.4.10`
- 凭证事实源：`mac-14:/Users/xing/.config/peanut-admin/development-db.env`

本机只运行 PHP、Nginx、管理端、PC、UniApp H5 和文档服务，不运行 Peanut Admin
MySQL。历史 `192.168.192.2:3306/peanut_admin` 没有迁移账本，不是当前开发资源，禁止
应用连接。公司 allocation 的权威登记位于
`company-os/resources/development-infrastructure.yaml`。

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

`dev-up` 会连接登记资源；空库使用当前安装器创建完整 schema 和种子，已有库执行
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
