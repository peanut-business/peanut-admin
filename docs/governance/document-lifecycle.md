# Document lifecycle and authoring rules

Document ID: `pa-docs-lifecycle`

## Lifecycle

| Status | Meaning | May be used as current fact? | Required disposition |
| --- | --- | --- | --- |
| `authoritative` | Stable index or documentation governance authority | yes, for its declared domain | update in the same change as its contract |
| `current` | Explanation of already accepted implementation or procedure | yes, after checking its upstream | keep references narrow |
| `planned` | Proposal, roadmap or unaccepted design | no | state prerequisites and stop line |
| `deprecated` | Still referenced during a bounded replacement | no for new work | set `replaced_by` and remove new links |
| `archived` | Historical evidence or superseded record | no | keep immutable context; exclude from current navigation |
| `generated` | Deterministic projection | only as a view of its source | never edit by hand; regenerate |

Content type and status are separate: an evidence document is normally archived after its qualification; an architecture document can be planned or current.

## Minimum metadata

Every Markdown file records its own stable ID, title, audience, content type, upstream sources, owner, status, scope, related domains, site projections, replacement and validation. The registry has no collection fallback: a new file is registered before it is linked or committed, and moves retain the file's stable ID.

Use [the technical document template](templates/document-template.md). Public task pages additionally state prerequisites, goal, steps, verification and next step when those sections apply.

## Placement and migration

- Put machine facts in their existing ledger, manifest, schema or configuration source—not in a new README table.
- Put present boundaries in `architecture/`, procedures in the task directory, decisions and proposals in `design/` or `plans/`, evidence in the existing status/evidence location, and public projections in `docs-site/`.
- Preserve Git history with a direct move when content remains valid. When it does not, add the replacement, update inbound links, set `replaced_by`, and archive or remove the old file in the same bounded change.
- A document is orphaned when it is not explicitly registered. A current document is navigation-orphaned when no stable index reaches it; the generated catalog is the minimum technical index.
- Broken links block the affected documentation change. Historical raw text may name removed paths, but must not link to a nonexistent current target.

## Application, Core and comments

Application owns product Modules, deployment and product status. Core owns reusable package contracts and product-neutral Runtime guidance. Cross-repository pages link to the owner and explain only the local adoption boundary.

Large-scale code-comment upgrades are intentionally deferred until Application capabilities have completed their approved Core convergence. Until then, add only comments required to explain a changed local invariant; do not use documentation synchronization to freeze a provisional boundary in code comments.
