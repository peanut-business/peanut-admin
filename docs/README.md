# Peanut Admin technical documentation

This directory is the maintainer-facing documentation layer for the Peanut Admin application. It explains boundaries and operating contracts; it does not replace the machine-readable facts listed in the [authoritative source map](governance/authoritative-source-map.md). The public developer projection lives in `docs-site/`.

## Start here

| Need | Entry | Lifecycle |
| --- | --- | --- |
| Find the owner of a fact | [Authoritative source map](governance/authoritative-source-map.md) | authoritative index |
| Understand the system boundary | [Clean native multitenancy baseline](architecture/clean-native-multitenancy-baseline.md) | current technical explanation |
| Understand the target backend organization | [Application and Module architecture blueprint](architecture/application-module-blueprint/README.md) | accepted target and migration input; explicitly not proof that the Runtime has migrated |
| Understand Module/Host boundary | [Module execution context](architecture/module-execution-context-contract.md), [consumer lifecycle contract](architecture/consumer-module-lifecycle-contract.md) and [service registry](architecture/service-layer-registry.md) | current technical contracts |
| Understand Module development | [Module development guide](module-development-guide.md) | current guide |
| Follow the public consumer path | [Create applications and deliver Modules](../docs-site/guide/application-module-lifecycle.md), [command reference](../docs-site/reference.md) and [support guide](../docs-site/support.md) | public task projection; runtime and release facts remain upstream |
| Understand identity and tenancy | [Clean native multitenancy baseline](architecture/clean-native-multitenancy-baseline.md) | current architecture |
| Inspect product state | [Product status](product-status/README.md) | internal machine facts and evidence |
| Inspect the current formal release and registered demo | [v3.0.12 release snapshot](product-status/releases/v3.0.12.json), [online-experience snapshot](product-status/deployments/v3.0.12-online-experience.json) and [demo handoff](operations/demo-access.md) | immutable release identity plus current no-secret deployment evidence |
| Inspect known Demo experience issues | [Demo experience audit](product-status/audits/demo-experience-audit.md) | v3.0.12 historical browser evidence, issue/evidence index and explicit untested scope; not a repaired-state claim |
| Follow the product-closure work | [Execution queue](plans/product-closure-execution-queue.md), [compatibility baseline](architecture/product-closure-core-application-compatibility.md) and [observability panel](product-status/product-closure-observability.md) | internal decisions and execution evidence; not capability completion proof |
| Follow consumer-ready delivery | [Consumer-ready product plan](plans/consumer-ready-product-plan.md), [post-release enhancement plan](plans/post-release-enhancement-plan.md) and [evidence retention register](maintenance/consumer-ready-evidence-retention.md) | completed delivery baseline, current enhancement queue and path-level keep/archive/delete decisions |
| Prevent avoidable delivery retries | [Consumer-ready minimal controller](operations/consumer-ready-control.md) | current read-only preflight contract and upgrade boundary |
| Compare product capabilities | [Product capability reference matrix](reference/product-capability-reference-matrix.md) | researched reference and Core/Application adoption decisions; not completion proof |
| Plan future work | `docs/plans/` and `docs/design/` | planned, never implementation proof |
| Operate or release | `docs/operations/`, [release engineering](release-engineering.md) and [public demo projection](../docs-site/demo-access.md) | current procedures; public projection contains only owner-authorized entry URLs and account names |
| Change documentation safely | [Document lifecycle](governance/document-lifecycle.md) and [docs-impact](governance/docs-impact.md) | authoritative governance |

The generated [document catalog](reference/document-catalog.generated.md) lists every individually registered entry.

## Stable directory boundaries

- `architecture/`: boundaries and accepted contracts; each document's lifecycle is determined by the registry, not by this directory name.
- `development/`, `operations/`, `testing/`: task procedures for maintainers.
- `design/`, `plans/`: proposals and sequencing; not completed-state evidence.
- `product-status/`: internal capability, release and deployment facts; not a public site source.
- `governance/`: documentation ownership, lifecycle, impact and templates.
- `reference/`: stable references and generated indexes.
- `docs-site/` (repository root): public, task-oriented projection for developers and users.

Existing root-level contracts remain discoverable during the bounded migration. Do not create a second synonym for them: register a replacement, move references, then mark or archive the old document in one change.

## AI reading order

1. Read `AGENTS.md` and `AGENT_EXECUTION_RULES.md`.
2. Read `docs/document-registry.json` and this index.
3. Open only the authoritative machine source for the fact being changed.
4. Use `docs/document-impact-map.json` to select the smallest affected explanations and projections.
5. Read plans or evidence only when the task concerns their exact decision or qualification.
6. Run `./scripts/docs-governance check` and the affected documentation build once.

Search by stable document ID first (`rg '<document-id>' docs/document-registry.json`), then by exact module, service, capability or resource ID. A path match is not proof that a document is current; confirm its registry status.

`impact` is a closure check, not only a routing report: every required target must be changed in the same diff or named with an exact `--waive-target` and one non-empty reason. Record the classifications, closure and any waiver in the task or PR checklist.
