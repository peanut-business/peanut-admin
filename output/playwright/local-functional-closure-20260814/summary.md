# Local functional closure — 2026-08-14

## Result

No browser acceptance result was produced. The registered database remained reachable from the
host, but the leased isolated PHP container received `SQLSTATE[HY000] [2002] Connection refused`
for the same registered address. The run stopped after one reproduction and one read-only
diagnostic, as required by the project validation budget.

## Fixed before the stop line

The first gate exposed a deterministic local bootstrap defect: when `sync-credentials` creates a
new `.local/stack.env`, `ensure_env` previously skipped generating the required local admin email
and password because the file already existed. `scripts/local-stack.sh` now fills only missing or
empty values. `sh -n scripts/local-stack.sh` passed, and the next gate crossed Compose interpolation
and reached the database connection stage.

## Resource identity

- Candidate: `86acc5558690f6a0aae66911619db1019f8117ff`
- Lease: `local-functional-closure-20260814`
- Database: `peanut-admin-mysql84-development`, development,
  `192.168.192.2:20183/peanut_admin_development`
- Ports: gateway `18146`, PHP `18147`, Web `15146`, PC `13146`, H5 `15147`, Docs `14146`
- Browser session: `pa-lfc-20260814`
- Test prefix: `PA-E2E-20260814`

## Safety and cleanup

No fixture row or file was created. No payment, refund, SMS, WeChat, OAuth, production write, or
external Provider call was attempted. There is therefore no test data cleanup or retained fixture.

## Exact unblock condition

Restore container-to-registered-database routing for the leased isolated Compose project, while
keeping the database identity and address unchanged. Then claim a new candidate lease and execute
only the still-PARTIAL/VIEW-ONLY/BLOCKED rows recorded in
`docs/testing/local-functional-test-register.md`. The Web cache must be populated from
`web/pnpm-lock.yaml` before the single focused Web build; another worktree's cache is not evidence.
