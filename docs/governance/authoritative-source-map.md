# Authoritative source map

Document ID: `pa-docs-source-map`

Status: `authoritative`

Owner: `documentation-architecture`

This page answers “where must this fact be changed first?”. Explanations and public pages point upstream; they are not parallel truth stores.

| Fact domain | Authoritative upstream | Explanation / projection owner |
| --- | --- | --- |
| Project identity, branches and execution rules | `AGENTS.md`, `AGENT_EXECUTION_RULES.md` | root indexes only |
| Runtime resources, environments and fixed ports | `resources/project-resources.json`; P0-E-only bindings in `resources/p0e-runtime-qualification.json` | internal operations docs; public pages receive only safe local instructions |
| Capability and completion status | `docs/product-status/capability-ledger.json`; immutable release/deployment snapshots in its sibling directories | `docs/product-status/README.md`; never imported wholesale into the public site |
| Public demo entry and account handoff | owner-authorized `public_demo_access` in `resources/project-resources.json`, plus the exact release/deployment snapshots | `docs/operations/demo-access.md` internally and `docs-site/demo-access.md` publicly; passwords, tokens, cookies and private credential references are excluded from the public projection |
| Product-closure and consumer-ready delivery ownership, package compatibility, execution state and evidence-retention decisions | accepted ownership decision, package manifests/locks, fixed commits, PRs, completed minimum verification and Git-traceable cleanup baseline | `docs/architecture/product-closure-ownership-and-adoption.md`, `docs/architecture/product-closure-core-application-compatibility.md`, `docs/plans/product-closure-execution-queue.md`, `docs/product-status/product-closure-observability.md`, `docs/plans/consumer-ready-product-plan.md` and `docs/maintenance/consumer-ready-evidence-retention.md`; stable capability completion still returns to the capability ledger |
| Consumer-ready delivery preflight policy | `resources/consumer-ready-control.json` and `scripts/consumer-ready-control` | `docs/operations/consumer-ready-control.md`; this internal control has no public-site projection |
| Service ownership and maturity | `resources/service-registry.json` | `docs/architecture/service-layer-registry.md` |
| Module identity, permissions and data owner | each Module's `module.json` | Module and architecture guides |
| Consumer Package lifecycle and delivery operation boundary | Package/Module manifests and locks, lifecycle services, maintenance/backup contracts and accepted consumer lifecycle decision | `docs/architecture/consumer-module-lifecycle-contract.md`; implementation status remains in `docs/plans/consumer-ready-product-plan.md` |
| Schema and data shape | Core `KernelSchema`, `server/database/init.sql`, and additive migrations permitted by the current baseline | data and upgrade guides |
| HTTP contract | route definitions and `docs/api/openapi.yaml` where covered | API reference and examples |
| Commands, compatibility and configuration | executable `--help`, checked scripts, lifecycle services, `.env.example` files and configuration loaders | public consumer task, command index and support guides that invoke them |
| Package and scaffold identity | package manifests, lock files, application/scaffold manifests and immutable release snapshots | release and upgrade guides |
| Application/Core boundary | fixed Core dependency identity, module manifests, service registry and accepted architecture contracts | capability graph and developer projections |
| Documentation identity and lifecycle | `docs/document-registry.json` | this page and the generated catalog |
| Documentation impact | `docs/document-impact-map.json` | `docs/governance/docs-impact.md` |

## Cross-repository references

Application documents may reference Core with `repo://peanut-admin-core/<path>` or a public repository URL. They must not copy Core package inventories, Runtime status or dependency graphs. Core documents use the same rule in the opposite direction and must not claim an Application capability as a Core capability.

When a boundary changes, update the owning repository first, fix the consuming repository to an immutable accepted identity, then update the explanation on each side. Two repositories remain two commits and two review lines.

## Public projection boundary

The developer site may publish product-neutral concepts, commands, localhost examples, stable public APIs, released package identities, and owner-authorized public demo hosts/account names. It must not publish passwords, tokens, cookies, private credential references, private hosts, internal candidate IDs, raw qualification evidence or the internal capability ledger. Generated public pages declare their upstream and regeneration command.

Consumer support projects only the fixed diagnostic schema and redaction boundary. Ordinary reports point to
public Issues; security reports follow the repository `SECURITY.md` and never publish vulnerability details.
