# I01 DCS And Peanut Admin Integration Mapping

> **2026-07-19 downstream qualification status**: this file mirrors the
> repository-local I01 content used by DCS evidence. DCS subsequently qualified
> W0 at `5c30d1c53d77862a8428ba5545c1d88f445c0e2a`, the D0 baseline at
> `7422d119521050e4cb7349d483c1067dc1aae18a`, and D0 remediation at
> `c0963dd50c949b92893362dd449d6743a7955721`; the qualified host is
> `backend/app/Modules` with `Dcs\\App\\Modules`. The original I01 acceptance
> boundary below remains historical provenance: it did not itself approve D0.
> D0 remediation does not add business modules: D0 still contains only
> `dcs.support` and `dcs.store`; D1 Product, production,
> stable, Tag, Release, and public Package status remain unapproved.

## 1. Status And Purpose

This is the first formal DCS integration mapping generated from a qualified,
fixed Peanut Admin commit. It replaces no historical report by renaming; it was
written against the fixed source and current DCS decisions.

Status: accepted for DCS W0 private validation.

It answers four implementation questions:

1. which general capabilities DCS consumes from Peanut Admin;
2. which business capabilities remain owned by DCS;
3. how the separate repository hosts Modules and Tenant Clients;
4. which stop lines a low-context implementation Agent must enforce.

This mapping does not approve production use, a public package, D0 business
Runtime, POS integration, or any later DCS phase.

## 2. Fixed Upstream Evidence

| Evidence | Fixed value |
| --- | --- |
| Repository | `https://github.com/peanut-opensource/peanut-admin.git` |
| Provenance branch | `dev` |
| Qualified consumption commit | `0ab02a9b735ba9f4c23509cb366b9bf04039ebf8` |
| Qualified tree | `12fdd00c1d506ca860b76dcc9e2dd796d56b723f` |
| Qualification record commit | `c63e06e25e35855cfefab890d7ee43c6e0cf839d` |
| Qualification record | `docs/reviews/external-host-consumption-qualification.md` |
| Module manifest schema SHA-256 | `332e9d8d17c2952194e26f377673da20abc0f2f55835f1416ea1920b4814a030` |
| Composer lock SHA-256 | `066005bb9d58b059f433e90bb752755e4cc01c92729c05cdb2f465ccc1b44264` |
| pnpm lock SHA-256 | `0c895209bf3cf54a3e9a55a9dff4ad2691ec3c28eca652b64b56e2f05d5aa5bf` |
| Aggregate gate | `./scripts/check` passed on the qualified consumption commit |

The DCS machine-readable copy is `evidence/upstream-lock.json`. DCS consumes the
qualified consumption commit, not the qualification-record commit and not the
moving `dev` branch.

## 3. Ownership Matrix

| Capability or object | Owner | DCS consumption rule |
| --- | --- | --- |
| Account and Credential | Peanut Kernel | Reuse; do not create DCS account or credential tables |
| Tenant and TenantMember | Peanut Kernel | Tenant is the DCS operating-organization isolation root |
| Department | Peanut Kernel | Optional organization tree for member and data rules; not a Store/Warehouse tree |
| Role and functional Permission | Peanut Kernel | Reuse one authorization path for all DCS Clients |
| DataPermission and typed-target contracts | Peanut packages | DCS Modules provide target resolvers, catalogs, and resource policy providers |
| Tenant and platform Session/audience | Peanut Kernel | Keep tenant and platform authority separate and fail closed |
| Audit, idempotency, Problem contracts | Peanut Kernel/reference host | Reuse contracts; DCS adds domain details without bypassing core evidence |
| Module manifest, migration ownership, TenantModule | Peanut Kernel | DCS hosts manifests and DCS-owned migrations in this repository |
| Admin Web protected transport | Peanut Web package | DCS owns its OpenAPI paths, API prefix, routes, and generated types |
| Store, Warehouse, Supplier, Party | DCS Modules | Never add to Peanut Kernel or starter |
| Product, Pricing, Inventory, Trade | DCS Modules | One server-side business truth in the DCS backend |
| Settlement, Costing, Delivery, Integration | DCS Modules | Later approved phases only |
| POS edge execution and devices | POS repository | Never create in DCS or Peanut repositories |

Access identity and business identity remain different. A Party, Person,
Supplier contact, customer, or Store operator record does not automatically
become an Account or TenantMember. Any link is explicit, optional, and audited.

## 4. Tenant And Business Targets

Tenant means the company, team, or operating organization that uses DCS. It is
not a Store, Warehouse, Supplier, or configurable current target.

One Tenant may manage several categories and several instances in each category:

```text
Tenant
  TenantMembers and Departments
  Stores A, B, C
  Warehouses W1, W2
  later DCS-owned business objects
```

A TenantMember keeps one membership in the Tenant. Different DCS resource
operations determine which Store or Warehouse targets that member may read or
change. Do not add `StoreMembership`, `PortfolioGrant`, a copied Role table, or
a second generic authorization system.

The server derives Tenant from validated Peanut authority. Business target IDs
are explicit operation inputs or derived by the declared provider; they never
replace Tenant and never mutate Session or TenantContext.

## 5. Initial Tenant Clients

W0 registers two DCS Tenant Clients:

| Client key | Purpose | Target behavior |
| --- | --- | --- |
| `dcs-store-web` | Direct Store management workspace | A normal business operation resolves one explicit authorized `dcs.store` target |
| `dcs-multi-store-web` | Multi-store management and analysis workspace | Lists and aggregates use the authorized Store target set; commands use their own declared operation and target cardinality |

These Clients are separate browser authority boundaries over the same backend.
Each has its own login challenge binding, session family, access token, refresh
token, and `__Host-` refresh cookie. One Client cannot reuse another Client's
challenge, token, session, or cookie. Refresh in one Client does not revoke an
independent Client session.

The Client key is fixed by trusted server routing/configuration. The browser
cannot submit an arbitrary Client key as a privilege switch.

The multi-store Client does not simulate login as a Store employee. It uses the
same Peanut functional and data-permission engine with different DCS operations:

- `many_readable` or `aggregate_read` for authorized Store-set queries;
- `one_required` for an ordinary command affecting one Store;
- `policy_publish` only for a separately designed multi-target publication;
- no generic `bulk_write` endpoint in the initial phase.

Future Clients such as warehouse or mobile workspaces require a separate design,
registration, menu mapping, authentication test, and evidence update. W0 must
not create them speculatively.

## 6. External Module Host Layout

DCS uses Peanut's `ModuleHostLayout` with these fixed W0 inputs:

```php
new ModuleHostLayout(
    'backend/app/Modules',
    'Dcs\\App\\Modules',
    'frontend/packages/modules/src',
);
```

The module key remains globally namespaced. Therefore the exact mapping is:

| Module key | Backend path | Backend namespace prefix | Frontend contribution path |
| --- | --- | --- | --- |
| `dcs.support` | `backend/app/Modules/Dcs/Support/` | `Dcs\\App\\Modules\\Dcs\\Support\\` | `frontend/packages/modules/src/dcs-support/` |
| `dcs.store` | `backend/app/Modules/Dcs/Store/` | `Dcs\\App\\Modules\\Dcs\\Store\\` | `frontend/packages/modules/src/dcs-store/` |

The repeated `Dcs` segment is intentional: the PHP host namespace is DCS-owned,
while the first Module-key segment is the global Module owner prefix. An Agent
must not strip the segment or rename Module keys to make paths look shorter.

The compiler receives registered menu Client keys:

```text
dcs-store-web
dcs-multi-store-web
platform-web
```

Only the first two are DCS Tenant Client applications in W0. `platform-web` is
the separate Peanut platform audience and is not a Tenant Client or business
authority.

The boundary checker receives managed table prefixes `pa_` and `pa_dcs_`.
Kernel, authorization, Module, idempotency, and data-permission table lists are
registered as reserved tables. A DCS Module may own declared `pa_dcs_*` tables
but cannot claim a reserved Peanut table or another Module's table.

The menu-component Client whitelist and `TenantClientRegistry` are separate
inputs. `platform-web` is allowed only where a platform menu contribution needs
it; the DCS Tenant Client registry contains exactly the two Tenant Clients.

## 7. Package Consumption Before Publication

Peanut packages are not published in P0. W0 uses an exact-commit sibling
checkout, verified before dependency installation.

Composer path repositories consume:

```text
packages/php/kernel
packages/php/data-permission
packages/php/testing       only when a W0 test requires it
```

pnpm link/workspace dependencies consume:

```text
packages/web/admin-core
packages/web/admin-shell   only for shared shell composition
packages/web/testing       test-only
```

The DCS lock and check script must reject:

- no upstream checkout;
- a dirty upstream checkout;
- a HEAD other than the 40-character locked consumption commit;
- mismatched manifest schema, Composer lock, or pnpm lock hash;
- dependency resolution from an unapproved moving branch or registry version.

Local filesystem location is an environment input and is never stored as an
absolute machine path in the lock. CI must check out both repositories at the
required commits before installation.

## 8. Authentication And Session Mapping

The login chain is:

```text
server-selected Tenant Client
-> Credential
-> Account
-> Tenant choice when required
-> active TenantMember
-> Client-bound TenantSession
-> validated TenantContext
-> authorized DCS operation and typed targets
```

Session is the server-side authentication record represented by access and
refresh tokens. DCS must not create a local-storage Session authority, share a
Session ID between Clients, or add `current_store_id` to Peanut Session.

Cross-Client convenience login is deferred. If later required, it must exchange
a short-lived one-time authorization code and create a new target-Client Session;
it must not copy tokens or cookies.

## 9. Functional And Data Authorization Mapping

Every protected DCS operation follows this order:

```text
validated Client and Tenant Session
-> active Tenant and TenantMember
-> enabled TenantModule
-> functional permissions
-> declared protected-resource operation
-> DCS DataPermission provider and typed-target validation
-> repository query or command constrained by trusted tenant_id and policy
-> audit and idempotency where declared
```

Missing Client, Tenant, Module, permission, target resolver, provider, policy,
or operation declaration denies the request. A request `tenant_id` never creates
trusted Tenant context.

For the initial Store model:

- single-store list/detail/write operations validate `dcs.store` according to
  their declared cardinality;
- multi-store lists compile the member's authorized Store set into the query;
- aggregates are read-only and return only authorized Stores;
- UI visibility does not grant backend authority;
- zero authorized Stores produces an empty/denied state, never tenant-wide
  fallback;
- one authorized Store may be auto-selected by the UI but remains explicit in
  the operation;
- several authorized Stores show ownership in list and aggregate results.

## 10. Module And Database Boundaries

DCS is one modular monolith. One backend may serve many concurrent Tenants and
Stores and write the same physical table set; row constraints and transactions
provide isolation. The prohibition is against multiple independent services
owning and writing the same business tables without one owner, not against
normal concurrent use of one DCS backend.

Each DCS Module owns:

- its manifest and provider;
- its schema and migrations;
- its repositories and business rules;
- its API operations and permissions;
- its exported Commands, Queries, and Events;
- its frontend contributions.

Another Module may import only an exported `Contracts` type from a declared
dependency or consume a versioned event. Direct reads, writes, joins, repository
imports, or foreign internal service imports are prohibited. The current
boundary checker detects PHP namespace and literal table references; dynamic
table construction and non-PHP SQL require additional repository checks. A PHP
file under `Database/` may contain a literal `REFERENCES` foreign-key clause,
but that syntactic exception is not a dependency or access grant. Runtime access
still uses the public Contract.

Peanut migrations remain upstream-owned and unmodified. DCS host installation
must run the locked Kernel and data-permission migrations, then DCS Module-owned
migrations in dependency order. No DCS migration may alter an upstream table.

## 11. API, Web, Error, Audit, And Idempotency

DCS owns its OpenAPI document, generated PHP/TypeScript artifacts, API prefix,
route bindings, and operation tests. It does not reuse Peanut's fixed 75
reference-host operation paths as DCS business API types.

The Web apps consume Peanut's generic protected transport with a DCS-generated
`paths` type. The DCS wrapper validates both the configured API origin and the
Client-specific path prefix because the underlying transport validates paths but
does not itself treat `baseUrl` as an origin allowlist. Refresh coordination is
keyed by Client and audience. API calls must keep Problem Details and request
correlation visible; a refresh failure cannot silently retry as another Client.

Commands use the Peanut idempotency contract where declared and add DCS domain
keys without replacing core request evidence. Audit must distinguish the
validated actor, actor Tenant, Client, target Tenant, operation, target resource,
request ID, result, and reason where required. Future delegated operation may
add operator and target-organization evidence, but it cannot bypass the current
same-Tenant default denial.

## 12. Platform Governance Boundary

Peanut platform authority can provision, suspend, close, and govern Tenant and
TenantModule state through the separate platform audience. It does not grant
access to DCS Store, Product, Inventory, Trade, or other tenant business facts.

Official operations or future managed-service behavior must be a separate
delegation capability with explicit relationship, scope, expiry, revocation,
dual-party audit, and threat review. W0 and D0 do not implement it.

## 13. Upgrade Rule

An upstream upgrade is a controlled change:

1. choose a new qualified 40-character Peanut commit;
2. review source, qualification record, dependency hashes, manifest schema, and
   migrations;
3. update I01 only for real contract changes;
4. regenerate `evidence/upstream-lock.json` and its I01 hash;
5. install from clean checkouts;
6. run DCS package, architecture, authentication, migration, build, and business
   regression gates;
7. commit the lock and compatibility changes independently.

Do not overwrite a generated project from a newer starter. The starter is a
consumption proof, not an in-place project upgrade mechanism.

## 14. W0 Allowed Deliverables

W0 may create only the infrastructure needed to prove consumption:

- Composer and pnpm workspace manifests with exact path/link consumption;
- an upstream-lock verifier;
- ThinkPHP host bootstrap and external `ModuleHostLayout` configuration;
- an installer that locates upstream migration resources through Composer
  `InstalledVersions` and executes the complete locked migration chains without
  copying or modifying them;
- `dcs-store-web` and `dcs-multi-store-web` minimal application shells;
- server-fixed Tenant Client configuration;
- installation, build, authentication, package, architecture, and smoke tests;
- repository check scripts and evidence updates.

W0 must not create Store/Support or any other DCS business Module, business
migration, domain table, controller, repository, or business page. It must not
create a POS directory.

## 15. W0 Acceptance And Next Gate

W0 passes only when a clean checkout can prove:

1. the upstream checkout is clean and exactly matches the lock;
2. Composer and pnpm install the intended Peanut packages without a moving
   branch or registry fallback;
3. the DCS external namespace and Module layout compile against the locked
   manifest schema;
4. both Tenant Clients build and cannot cross-use challenges, tokens, Sessions,
   refresh cookies, or refresh coordination;
5. the host installs Kernel/data-permission migrations and starts against a
   temporary MySQL database;
6. repository checks reject a Peanut Kernel business model, undeclared DCS
   Module table, cross-Module internal import, global current target, and POS
   source;
7. `git diff --check` passes and the worktree is clean after verification.

After W0 passes, stop. D0 Store/Support Runtime requires separate approval and
must use this mapping, the lock, and the D0-specific design inputs. Product,
Pricing, Inventory, Trade, Costing, POS, and later Clients remain outside D0.
