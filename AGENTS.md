# Peanut Admin — Bootstrap

Repository: `peanut-business/peanut-admin`. Integration: `dev`; stable: `main`.
Product name: Peanut Admin; PC package: `peanut-admin-pc`.

## Load only the current task inputs

1. Follow system/developer instructions, then the latest applicable user decision. User decisions override older defaults only within their scope; silence, compaction and receipts grant no permission.
2. Resolve `git rev-parse --path-format=absolute --git-common-dir`. Its parent is the local control root; the sole private control file is `.local/peanut-admin-supervision.json` there. It is optional for ordinary clones, required for an assigned controlled task.
3. When that state exists, resolve its `policy_ref` to the pinned rule source before reading or running a checker. A pointer does not change the current script's checkout. Missing/mismatched policy input stops only dependent work; never recover a previous checkpoint or backup.
4. Read `AGENT_EXECUTION_RULES.md` §0 and only the sections routed below. `docs/document-registry.json` owns rule metadata and document lifecycle, not product or execution facts.
5. For a controlled task, use the selected current state fields and required immutable evidence entries. Do not load whole plans, historical chats, takeover copies or knowledge projections to recover authority.

| Task | Canonical inputs |
| --- | --- |
| Scope, permission, failure budget, writer or recovery | Execution rules §0–5; sole private state and its exact evidence references |
| Version identity and two Editions | `docs/architecture/product-version-identity-adr.md`; actual manifests/locks and immutable Release evidence |
| Runtime resources, configuration, tests or deployment | `resources/project-resources.json`; relevant P0-E/consumer binding; execution rules §5/§7.2 |
| Service/Module ownership or architecture | `resources/service-registry.json`, relevant `module.json`, `docs/architecture/core-application-technical-boundary.md` |
| Capability, Release or deployment facts | `docs/governance/current-state.md`; `docs/product-status/capability-ledger.json` and the corresponding immutable snapshots |
| Existing convergence-plan scope | `docs/plans/history-rules-product-convergence-plan-2026-09-10.md`; issue register only for the assigned IDs |
| Documentation | `docs/README.md`, document registry and impact map; `scripts/docs-governance check` |
| Seal, qualify or publish | Execution rules §7.2, `docs/operations/consumer-ready-control.md`, `docs/release-engineering.md`, applicable fixed qualification contract |

Application, Core PHP/Web and Standalone/Multi-tenant share the product version; Module and Instance versions are independent. Same version is not the same artifact identity or proof of qualification.
Use registered resource IDs, environments and addresses; verify health, freshness and exclusive leases before resource use. Do not inherit development configuration into production or invent a fallback.
Use `scripts/project-codegraph ensure` only for needed call/impact/architecture analysis; first check `status` when this worktree has no index. Never copy/share indexes. Pure documentation and narrow mechanical changes skip it.
Application uses ThinkPHP native Model/Scope constructor injection. Core/Application and Storage ownership follow their existing architecture contracts; do not introduce compatibility layers or new Runtime scope.
Choose model and reasoning independently at the lowest sufficient level; report actual configuration separately. Hashing, JSON/path checks, extraction, generation and fixed command orchestration run locally without a model. Upgrade only the uncertain semantic subtask.
Use `feat/<description>`. Complete minimum relevant checks, integrate and push `dev`, then clean only this task's branch/worktree. `dev → main` uses a PR; fixed candidates, signature, security/data/resource boundaries and final release retain their human Gates.
