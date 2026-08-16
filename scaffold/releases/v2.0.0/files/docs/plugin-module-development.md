# Module development

Place an application Module under `server/app/Modules/<Vendor>/<Module>/` with Domain, Application, Contracts, Infrastructure, Database, Resources and Tests. Put the matching management contribution in `web/src/modules/<module>/`.

Expose commands and read-only DTOs from `Contracts`; callers must not join or mutate another Module's private tables. Plugin install, TenantModule enablement and member RBAC are separate gates. Document the Module's owner Tenant, migrations, menu/permission keys and cross-Tenant denial cases.
