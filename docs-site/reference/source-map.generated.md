<!-- GENERATED: source=docs/document-registry.json; command=./scripts/docs-governance generate -->
# Documentation source map

This public-safe index shows where developer pages get their facts. It intentionally excludes internal resource addresses, credentials, candidate evidence and the internal capability ledger.

| Document ID | Public page | Upstream | Validation |
| --- | --- | --- | --- |
| `pa-docs-change-scenarios` | `docs/governance/change-scenarios.md` | `docs/document-impact-map.json` | `./scripts/docs-governance scenarios` |
| `pa-docs-impact-policy` | `docs/governance/docs-impact.md` | `docs/document-impact-map.json` | `./scripts/docs-governance scenarios` |
| `pa-docs-lifecycle` | `docs/governance/document-lifecycle.md` | `docs/document-registry.json` | `./scripts/docs-governance check` |
| `pa-docs-pr-checklist` | `docs/governance/templates/docs-impact-checklist.md` | `docs/governance/docs-impact.md` | `manual: include in task or PR description` |
| `pa-docs-source-map` | `docs/governance/authoritative-source-map.md` | `docs/document-registry.json`<br>`docs/document-impact-map.json` | `./scripts/docs-governance check` |
| `pa-site-getting-started` | `docs-site/getting-started.md` | `scripts/create-app`<br>`scripts/local-stack.sh`<br>`server/.env.example` | `cd docs-site && pnpm build` |
| `pa-site-index` | `docs-site/index.md` | `docs/README.md`<br>`docs/governance/authoritative-source-map.md` | `cd docs-site && pnpm build` |
| `pa-site-source-map` | `docs-site/reference/source-map.generated.md` | `docs/document-registry.json` | `./scripts/docs-governance generate --check` |

For maintainer-only facts, use the repository technical documentation and its authoritative source map.
