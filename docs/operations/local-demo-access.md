# Local demo access

> Internal handoff for the disposable local multi-tenant demonstration. This file is the
> local counterpart to `docs/operations/demo-access.md`; do not mix local and remote accounts.

Verified: 2026-08-20 against the versioned `scripts/local-multi-tenant-demo` defaults.

All local demo accounts use the shared demo password `peanut1234xx`. Password changes are locked
when `PEANUT_DEMO_MODE=enabled`.

| Environment | Login URL | Account | Password |
| --- | --- | --- | --- |
| Local Platform | http://platform.peanut-admin.test:20176/platform/ | `platform@local.test` | `peanut1234xx` |
| Local shared Admin | http://admin.peanut-admin.test:20179/admin/ | `owner@local.test` | `peanut1234xx` |
| Local Tenant A | http://tenant-a.peanut-admin.test:20179/admin/ | `tenant-a@local.test` | `peanut1234xx` |
| Local Tenant B | http://tenant-b.peanut-admin.test:20179/admin/ | `tenant-b@local.test` | `peanut1234xx` |

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

When a local demo account, password, hostname, port, or database changes, update this file and
the corresponding defaults in `scripts/local-multi-tenant-demo` together.
