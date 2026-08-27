<!-- GENERATED: source=docs/document-registry.json; command=./scripts/docs-governance generate -->
# Documentation source map

This public-safe index shows where developer pages get their facts. It intentionally excludes internal resource addresses, credentials, candidate evidence and the internal capability ledger.

| Document ID | Public page | Upstream | Validation |
| --- | --- | --- | --- |
| `pa-docs-site-404` | `docs-site/404.md` | `docs-site/index.md`<br>`docs-site/.vitepress/config.ts` | `./scripts/docs-governance check` |
| `pa-docs-site-api` | `docs-site/api.md` | `docs/api/openapi.yaml`<br>`server/route/app.php`<br>`server/app/common/service/JsonService.php`<br>`docs/plugin-module-development.md`<br>`server/composer.lock`<br>`web/package.json` | `./scripts/docs-governance check` |
| `pa-docs-site-guide-backend` | `docs-site/guide/backend.md` | `server/route`<br>`server/app`<br>`docs/api/openapi.yaml`<br>`scripts/check-openapi` | `./scripts/docs-governance check` |
| `pa-docs-site-guide-concepts` | `docs-site/guide/concepts.md` | `docs/architecture/clean-native-multitenancy-baseline.md`<br>`docs/architecture/core-application-capability-graph.md`<br>`resources/service-registry.json`<br>`server/app/Modules` | `./scripts/docs-governance check` |
| `pa-docs-site-guide-data-permissions-tenancy` | `docs-site/guide/data-permissions-tenancy.md` | `repo://peanut-admin-core/packages/php/kernel/src/Persistence/Schema/KernelSchema.php`<br>`server/database/init.sql`<br>`server/database/migrations`<br>`server/app/Modules`<br>`docs/architecture/clean-native-multitenancy-baseline.md` | `./scripts/docs-governance check` |
| `pa-docs-site-guide-deployment-upgrade` | `docs-site/guide/deployment-upgrade.md` | `release-versions.json`<br>`scripts/deploy-release`<br>`scripts/scaffold-upgrade`<br>`server/database/migrations`<br>`docs/release-engineering.md` | `./scripts/docs-governance check` |
| `pa-docs-site-guide-development` | `docs-site/guide/development.md` | `resources/service-registry.json`<br>`server/app/Modules`<br>`server/composer.json`<br>`web/package.json`<br>`AGENT_EXECUTION_RULES.md` | `./scripts/docs-governance check` |
| `pa-docs-site-guide-frontend` | `docs-site/guide/frontend.md` | `web/package.json`<br>`platform/package.json`<br>`pc/package.json`<br>`uniapp/package.json` | `./scripts/docs-governance check` |
| `pa-docs-site-guide-index` | `docs-site/guide/index.md` | `docs-site/.vitepress/config.ts`<br>`docs-site/getting-started.md`<br>`docs-site/guide/development.md`<br>`docs-site/reference.md` | `./scripts/docs-governance check` |
| `pa-docs-site-guide-module-development` | `docs-site/guide/module-development.md` | `docs/plugin-module-development.md`<br>`server/app/Modules/Fixture/DeliveryRecord/module.json`<br>`plugins/fixture.delivery-record/plugin.json`<br>`repo://peanut-admin-core/packages/php/kernel/resources/schemas/module-manifest.schema.json` | `./scripts/docs-governance check` |
| `pa-docs-site-guide-testing` | `docs-site/guide/testing.md` | `AGENT_EXECUTION_RULES.md`<br>`scripts/docs-governance`<br>`scripts/check-openapi`<br>`web/package.json`<br>`platform/package.json`<br>`pc/package.json`<br>`uniapp/package.json` | `./scripts/docs-governance check` |
| `pa-docs-site-legal` | `docs-site/legal.md` | `LICENSE`<br>`NOTICE`<br>`THIRD_PARTY_NOTICES.md`<br>`RELEASE_SBOM.spdx.json`<br>`CHANGELOG.md`<br>`RELEASE_METADATA.json` | `./scripts/docs-governance check` |
| `pa-docs-site-reference` | `docs-site/reference.md` | `docs/governance/authoritative-source-map.md`<br>`server/route`<br>`server/.env.example`<br>`server/database/init.sql`<br>`server/app/Modules`<br>`scripts/local-stack.sh` | `./scripts/docs-governance check` |
| `pa-docs-site-releases` | `docs-site/releases.md` | `release-versions.json`<br>`RELEASE_METADATA.json`<br>`docs/release-engineering.md`<br>`scripts/deploy-release`<br>`scripts/scaffold-upgrade` | `./scripts/docs-governance check` |
| `pa-site-getting-started` | `docs-site/getting-started.md` | `scripts/create-app`<br>`scripts/local-stack.sh`<br>`server/.env.example` | `cd docs-site && pnpm build` |
| `pa-site-index` | `docs-site/index.md` | `docs/README.md`<br>`docs/governance/authoritative-source-map.md`<br>`docs-site/.vitepress/config.ts` | `cd docs-site && pnpm build` |
| `pa-site-source-map` | `docs-site/reference/source-map.generated.md` | `docs/document-registry.json` | `./scripts/docs-governance generate --check` |

For maintainer-only facts, use the repository technical documentation and its authoritative source map.
