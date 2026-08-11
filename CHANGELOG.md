# Changelog

All notable Peanut Admin application changes are recorded here. The application
and the two public core packages have independent version histories.

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

[1.0.0]: https://github.com/peanut-business/peanut-admin/releases/tag/v1.0.0
