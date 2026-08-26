# Documentation impact policy

Document ID: `pa-docs-impact-policy`

The machine-maintained impact graph is `docs/document-impact-map.json`. It maps changed paths to upstream facts, the smallest technical explanations, public projections and generated outputs.

## Classification

| Classification | Use when | Required action |
| --- | --- | --- |
| `none` | Refactor changes no behavior, contract, owner, command, configuration or architecture | record that reason; update no pages |
| `technical` | A maintainer-facing contract or procedure changed | update the owning fact first, then only named technical docs |
| `developer-site` | A public task, concept, API or command changed | update the upstream and its named public projection |
| `generated` | A checked-in projection consumes the changed source | run the generator; do not hand edit output |
| `architecture-decision` | Ownership, trust boundary, data owner or Application/Core boundary changed | accept or update the decision/contract before explanations |

Multiple classifications can apply. `none` is valid only when no more-specific rule matches and its reason is written in the task or PR.

## Change loop

1. Change the authoritative fact source.
2. Run `./scripts/docs-governance impact --base <base-ref>` or provide paths explicitly.
3. Record the selected docs-impact classification and reason.
4. Update only the technical documents named by the matching rule.
5. Update or regenerate only the named public projections.
6. Run `./scripts/docs-governance check`, then the affected site build.
7. Review the exact diff and merge the fact and its minimum documentation closure together.

The tool reports candidates; the author remains responsible for semantic judgment. A generic controller match does not require public documentation if the implementation is demonstrably internal and no specific public-contract rule matches.

## Commands

```bash
./scripts/docs-governance impact --base origin/dev
./scripts/docs-governance impact --classification none --reason "private rename; behavior and contracts unchanged" --paths server/app/example.php
./scripts/docs-governance generate
./scripts/docs-governance check
./scripts/docs-governance scenarios
```

Generated content contains its source and regeneration command. `check` rejects drift, duplicate IDs/canonical keys, missing registered files or sources, uncovered Markdown, missing projections, broken links in current governed pages, invalid public navigation and forbidden internal material in public pages.
