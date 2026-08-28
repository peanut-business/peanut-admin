# Documentation change scenarios

Document ID: `pa-docs-change-scenarios`

These eight scenarios are executable contracts in `docs/document-impact-map.json`; run `./scripts/docs-governance scenarios` after changing the map.

| Scenario | Upstream fact | Technical update | Public projection | Explicitly not updated | Omission detection / acceptance |
| --- | --- | --- | --- | --- | --- |
| Internal refactor | changed implementation | none when behavior and ownership stay identical | none | API, guides, navigation | `none` plus written reason; scenario rejects `developer-site` |
| HTTP API | routes/controllers and OpenAPI | API contract and affected development reference | `/api` and affected example | unrelated module, deployment and release pages | impact contains technical + site + generated; OpenAPI and site build pass |
| CLI command | executable and `--help` | only guide/reference naming the command | quick start or command reference that invokes it | architecture and unrelated commands | impact contains technical + site; referenced command/path exists |
| Schema, migration or data owner | KernelSchema/init/migration/module owner | data ownership and upgrade contract | tenancy/data and upgrade task pages | UI guides and release history | impact contains decision + technical + site; source paths and links pass |
| `module.json` or Module directory | Module manifest/bootstrap | Module guide and execution-context contract | Module tutorial | other Modules and global capability tables | impact contains technical + site; manifest path and projection exist |
| Resource, port or environment | resource registry | internal resource/operator guide | only public-safe localhost instructions | credentials, private host, internal candidate evidence and unrelated generated pages | impact contains technical + site, excludes generated content without a real consumer; forbidden-public scan passes |
| Product capability status | capability ledger or immutable snapshot | internal product-status index | none by default | public developer site and unrelated generated pages | impact contains technical and explicitly excludes site/generated output |
| Architecture or Application/Core boundary | accepted contract, registries, fixed Core identity | capability graph and ownership contract | development/Module boundary explanation | unrelated task pages and historical evidence | impact contains decision + technical + site; cross-repo owner remains explicit |
