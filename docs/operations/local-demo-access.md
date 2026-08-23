# Local demo access

本地单租户和多租户的登录表统一维护在 [Demo 登录信息](../../docs-site/demo-access.md) 页面；本文件
只记录多租户体验所需的资源、端口和启动方式，不再复制账号表。

## Runtime resources

- API: `http://127.0.0.1:20178/`
- Admin Web: `127.0.0.1:20179`
- Platform Web: `127.0.0.1:20176`
- Database resource: `peanut-admin-mysql84-local-multi-tenant-demo`
- Database: `peanut_admin_development_mtlocal01`
- Database endpoint: `192.168.192.2:20183`
- Resource namespace: `peanut-admin-local-multi-tenant-demo`

Start with `./scripts/local-multi-tenant-demo prepare` and then
`./scripts/local-multi-tenant-demo up`. The active project-resource lease is required before
starting the local database-backed demo.

The local runtime file `.local/mt-demo.env` is generated and ignored by Git. It may contain
database credentials and signing keys; those values are not recorded in this document. The
database credential reference remains the private resource entry in
`resources/project-resources.json`.

When a local demo account, password, hostname, port, or database changes, update the unified Demo
page, this resource section and the corresponding defaults in `scripts/local-multi-tenant-demo` together.
