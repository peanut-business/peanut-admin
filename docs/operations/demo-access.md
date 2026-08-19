# Demo access handoff

> Internal operator handoff for the disposable Peanut Admin demonstration environments.
> This file is intentionally complete so operators do not need to search deployment hosts.
> Do not copy the Platform, bootstrap, or Standalone administrator credentials into the public
> docs site or README. If any private credential is rotated, update this handoff, the registered
> Keychain/environment reference, and the verification date together.

Verified: 2026-08-20 against the registered `production-candidate` and `production` demo resources.

| Environment | Login URL | Account | Password | Password policy |
| --- | --- | --- | --- | --- |
| Production-candidate Platform | https://pa-platform.007345.xyz/platform/ | `platform@pa-demo.example` | `DemoPlatform2026Pass` | Private demo operator credential; not locked by `DemoAccountPolicy` |
| Production-candidate bootstrap Admin | https://pa-admin.007345.xyz/admin/ | `admin@pa-demo.example` | `DemoAdmin2026Pass` | Private bootstrap credential; not locked by `DemoAccountPolicy` |
| Production-candidate shared Admin | https://pa-admin.007345.xyz/admin/ | `tenant-a@pa-demo.example` | `DemoTenant2026Pass` | Public demo credential; Tenant A/B password changes are server-rejected while demo mode is enabled |
| Production-candidate Tenant A | https://pa-tenant-a.007345.xyz/admin/ | `tenant-a@pa-demo.example` | `DemoTenant2026Pass` | Public demo credential; password changes are server-rejected while demo mode is enabled |
| Production-candidate Tenant B | https://pa-tenant-b.007345.xyz/admin/ | `tenant-b@pa-demo.example` | `DemoTenant2026Pass` | Public demo credential; password changes are server-rejected while demo mode is enabled |
| Production Standalone Admin demo | https://peanut-admin.007345.xyz/admin/ | `admin@peanut-admin.007345.xyz` | `PeanutAdmin2026!` | Private disposable-demo administrator credential; not locked by `DemoAccountPolicy` |

Unauthenticated addresses:

- PC: https://peanut-admin.007345.xyz/pc/
- H5: https://peanut-admin.007345.xyz/mobile/
- Docs: https://peanut-admin-doc.007345.xyz

## Resource references

- Multi-tenant candidate: `peanut-admin-production-candidate-deployment`
- Candidate domains and public Tenant credentials: `peanut-admin-production-candidate-domains`
- Standalone demo: `peanut-admin-production-deployment`
- Standalone password handoff: macOS Keychain service `peanut-admin-production-admin`, account `admin`

The candidate is disposable and uses `PEANUT_DEMO_MODE=enabled`. The public Tenant credentials are
the only credentials intended for the public docs site. Platform, bootstrap, and Standalone Admin
credentials are complete here for internal handoff only.
