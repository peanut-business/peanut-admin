# Changelog

All notable Peanut Admin application changes are recorded here. The application
and the two public core packages have independent version histories.

## [1.1.5] - 2026-08-15

### Fixed

- The production PHP image now includes the executable `scripts/seed-demo-data`
  command at the application root.
- Demo seeding uses unique native PDO placeholders for every prepared statement,
  including separate insert and update timestamps and balance mirrors.

### Changed

- Scaffold `v1.1.8` reseals the managed production Dockerfile while preserving
  the demo seeder as app-owned application code.
- The release keeps 54 migrations and the existing public Core package versions;
  no prebuilt production image or new public package is published.

## [1.1.4] - 2026-08-15

### Added

- Production demo instances can opt into deterministic, tenant-scoped sample
  categories, articles, member tags and synthetic members with an idempotent
  seed command.
- Workbench support cards show and link the registered documentation and
  support addresses.

### Fixed

- The login page no longer repeats the product name beside the logo.
- Production resource registration now records the documentation domain and
  the Keychain-backed administrator credential reference without storing a
  secret.

## [1.1.3] - 2026-08-15

### Added

- Deterministic application creation now adopts immutable scaffold releases,
  records generation identity and preserves managed/app-owned boundaries.
- Scaffold upgrades provide preflight, apply, verify and recover workflows with
  conflict detection, application-version preservation and append-only evidence.
- Plugin and Module lifecycle support validates immutable locks, module-owned
  migrations, permissions, menus, settings and TenantModule enablement.
- Production deployment, database, application-container and paired-backup
  resources are explicitly registered with fail-closed selectors.

### Security

- Tenant Admin API routes fail closed and inherited role grants remain bounded
  by capabilities already authorized to the acting principal.
- Payment, OAuth and official-account callbacks resolve explicit Tenant-owned
  external bindings instead of falling back to a default Tenant.

### Changed

- The application migration ledger advances from 50 to 54 migrations.
- Generated applications use an independent initial version `0.1.0`; Peanut
  Admin product, scaffold and downstream application versions are distinct.
- Scaffold `v1.1.6` preserves the dual-mode administration bundles from
  `v1.1.5` and aligns the generated legal inventory with all five final locks.
- Scaffold `v1.1.7` reseals the current managed release inventory without
  changing the `v1.1.6` runtime behavior.
- The public runtime dependencies remain fixed at
  `peanut-admin/core@0.1.0-alpha.5` and `@peanut-admin/admin@0.1.0-alpha.5`.

### Upgrade notes

- Require `PEANUT_DEPLOYMENT_TARGET=production` and the registered database
  resource ID before backup, migration or deployment.
- Capture and verify bundled MySQL and `php-storage` as one pair before moving
  the migration ledger from 50 to 54 entries.
- After a new migration begins, image-only rollback is unsafe; recovery requires
  the exact paired backup under an explicit production recovery gate.

### Release boundaries

- The normative artifact is the deterministic source archive attached to the
  annotated `v1.1.3` GitHub Release.
- No prebuilt production image, new public Core package, subscription billing,
  marketplace or cross-instance operations platform is included.

## [1.1.0] - 2026-08-13

### Added

- One release now supports both `standalone` and `multi-tenant` deployment modes.
- Multi-tenant mode includes an isolated PlatformOperator control plane for
  Tenant provision, activation, suspension, first-owner creation and
  TenantModule configuration.
- Tenant sessions support explicit selection and switching, revoke the previous
  context after a switch, and reject new login and existing-session writes after
  suspension.
- Representative SQL, cache/lock, file, task, import/export, audit and diagnostic
  paths now consume trusted Tenant context and fail closed when it is absent.

### Changed

- The public runtime dependencies are uniformly locked to
  `peanut-admin/core@0.1.0-alpha.5` and
  `@peanut-admin/admin@0.1.0-alpha.5` from their public registries.
- Existing `v1.0.0` installations move forward through the migration ledger from
  28 to 50 migrations; fresh standalone and multi-tenant installs use the same
  release and schema history.
- Legacy administrators, RBAC, departments and jobs are mapped into the default
  Tenant, while Article and representative business domains use Tenant-first
  reads, writes and integrity constraints.

### Upgrade notes

- Back up the database and `php-storage` together, build the new images, run
  `php server/database/migrate.php`, and only then switch application traffic.
- Set `DEPLOYMENT_MODE` explicitly to `standalone` or `multi-tenant`. Multi-tenant
  deployments must also provide the documented platform/Tenant identifier HMAC
  keys and initial PlatformOperator inputs.

### Release boundaries

- The normative artifact is the source release at annotated tag `v1.1.0`.
- This release is a stable multi-tenant application scaffold, not a complete
  SaaS commercial control plane: subscriptions, billing, trials, invoices,
  marketplace flows and cross-instance operations remain out of scope.
- No prebuilt PHP/Nginx image or new public core-package version is part of this
  application release.

## [1.0.0] - 2026-08-11

### Added

- A fresh-clone installer with explicit initial administrator credentials and a
  SHA-256 migration ledger covering 28 ordered migrations.
- Production Docker Compose builds for the ThinkPHP backend, Vue management
  client, Nuxt PC client and UniApp H5 client.
- A single configurable Peanut Admin brand source consumed by the backend,
  management client, PC client, UniApp/H5 and the official documentation portal.
- Root proprietary license, third-party notices and an SPDX 2.3 release SBOM.

### Changed

- The management client uses Element Plus and consumes the public
  `@peanut-admin/admin` package without copying core implementation.
- Permission evaluation, transport clients and override registration use the
  two public packages `peanut-admin/core` and `@peanut-admin/admin`; internal
  domain directories are not independent packages.
- Product domains remain application-owned single implementations, including
  member/finance, content/decoration, notification, payment and OAuth/channel
  behavior.

### Release boundaries

- The normative artifact is the source release at annotated tag `v1.0.0`.
- No prebuilt PHP/Nginx image, new core-package version or SaaS implementation is
  part of this release.
- Real SMS, payment and WeChat/OAuth production availability still depends on
  deployment-specific credentials, platform registration and a low-risk smoke.

[1.1.5]: https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.5
[1.1.4]: https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.4
[1.1.3]: https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.3
[1.1.0]: https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.0
[1.0.0]: https://github.com/peanut-business/peanut-admin/releases/tag/v1.0.0
