# Demo access handoff

> Internal operator handoff for the disposable Peanut Admin demonstration environments.
> This file is intentionally complete so operators do not need to search deployment hosts.
> All credentials in this file are owner-authorized disposable-demo credentials and may be copied
> into the public docs site and README. If any credential is rotated, update this handoff, the
> resource registry, public tables, and verification date together.

Verified: 2026-08-20 by real browser login against every account below on the registered
`production-candidate` and `production` demo resources.

| Environment | Login URL | Account | Password | Password policy |
| --- | --- | --- | --- | --- |
| Production-candidate Platform | https://pa-platform.007345.xyz/platform/ | `platform@pa-demo.example` | `peanut1234xx` | Demo credential; Platform mutations are server-rejected while demo mode is enabled |
| Production-candidate bootstrap Admin | https://pa-admin.007345.xyz/admin/ | `admin@pa-demo.example` | `peanut1234xx` | Demo credential; critical mutations are server-rejected while demo mode is enabled |
| Production-candidate shared Admin | https://pa-admin.007345.xyz/admin/ | `tenant-a@pa-demo.example` | `peanut1234xx` | Public demo credential; critical mutations are server-rejected while demo mode is enabled |
| Production-candidate Tenant A | https://pa-tenant-a.007345.xyz/admin/ | `tenant-a@pa-demo.example` | `peanut1234xx` | Public demo credential; critical mutations are server-rejected while demo mode is enabled |
| Production-candidate Tenant B | https://pa-tenant-b.007345.xyz/admin/ | `tenant-b@pa-demo.example` | `peanut1234xx` | Public demo credential; critical mutations are server-rejected while demo mode is enabled |
| Production Standalone Admin demo | https://peanut-admin.007345.xyz/admin/ | `admin@peanut-admin.007345.xyz` | `peanut1234xx` | Demo credential; critical mutations are server-rejected while demo mode is enabled |

Unauthenticated addresses:

- PC: https://peanut-admin.007345.xyz/pc/
- H5: https://peanut-admin.007345.xyz/mobile/
- Docs: https://peanut-admin-doc.007345.xyz

## Resource references

- Multi-tenant candidate: `peanut-admin-production-candidate-deployment`
- Candidate domains and public Tenant credentials: `peanut-admin-production-candidate-domains`
- Standalone demo: `peanut-admin-production-deployment`
- Standalone legacy handoff reference: macOS Keychain service `peanut-admin-production-admin`,
  account `admin` (secondary only; it may be stale and is not the current-login authority)

All accounts in this table are owner-authorized disposable-demo credentials and may be shown in the
README and public deployment guide. The candidate uses `PEANUT_DEMO_MODE=enabled`, and protected
demo mutations remain server-rejected.

The application database is authoritative for current login success. Values such as
`ADMIN_INITIAL_PASSWORD`, `PLATFORM_INITIAL_PASSWORD`, and `PEANUT_DEMO_SHARED_PASSWORD` in a
deployment `.env` are installation inputs and may remain unchanged after a database password
rotation. Keychain entries are handoff references and can likewise become stale. After any rotation,
verify one real browser login per distinct account and update this file, the public tables, and the
resource registry together.
