# Changelog

All notable Peanut Admin application changes are recorded here. The application
and the two public core packages have independent version histories.

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

[1.1.0]: https://github.com/peanut-business/peanut-admin/releases/tag/v1.1.0
[1.0.0]: https://github.com/peanut-business/peanut-admin/releases/tag/v1.0.0
