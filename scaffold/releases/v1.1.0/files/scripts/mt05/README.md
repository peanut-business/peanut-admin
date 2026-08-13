# MT05 install/upgrade harness

## Commands

```bash
scripts/mt05/run-install-upgrade --help
scripts/mt05/run-install-upgrade --candidate <40-hex-commit> --run-id <unique-id> --dry-run
scripts/mt05/run-install-upgrade --candidate <40-hex-commit> --run-id <unique-id>
```

## Inputs

- The checked-out clean `HEAD` must equal `--candidate`, and tag `v1.0.0` must exist locally.
- `server/vendor/autoload.php`, PHP with PDO MySQL, the MySQL client, and `tar` are required for a real run.
- Database connection: `MT05_DB_HOST`, `MT05_DB_PORT`, `MT05_DB_USER`, `MT05_DB_PASSWORD`.
- Tenant owner/Standalone compatibility identity: `MT05_ADMIN_INITIAL_PASSWORD`,
  `MT05_ADMIN_INITIAL_EMAIL`.
- Multi-tenant platform identity: `MT05_PLATFORM_INITIAL_PASSWORD`,
  `MT05_PLATFORM_INITIAL_EMAIL`. The runner requires these for a real matrix run
  but exports them as `PLATFORM_INITIAL_*` only for `multitenant-empty`; neither
  Standalone mode receives them.
- `--output-dir` or `MT05_OUTPUT_ROOT` may select a non-default evidence location.
- `--keep-failed-resources` retains only this run's isolated database and temporary directory after failure.

## Outputs

- Default evidence root: `output/mt05/<run-id>/`.
- `run.env` records immutable candidate/baseline identities and fixture digests without credentials.
- Each matrix mode writes command logs, `inspect.log`, and `result.json` containing the migration ledger, table count, default Tenant, owner, and Module baseline.
- `multitenant-empty` additionally proves there is exactly one active
  PlatformOperator, its Account differs from the default Tenant owner Account,
  and that platform Account has no TenantMember. Standalone empty install and
  v1.0.0 forward retain the existing compatibility assertions.
- `summary.txt` exists only after all three modes pass; `failure.txt` and exact step logs remain after a failure.

## Current MT05 stop line

The first frozen candidate exposed two blockers: multi-tenant fresh install
projected PlatformOperator and default Tenant owner onto the same Account, and
the browser harness had no deployed TenantModule installation command/config to
consume. Their Runtime fixes are in progress. This harness wiring does not make
MT05 pass: merge both Runtime fixes first, freeze a new exact candidate, then
run the install/upgrade and browser gates once each against that same candidate.
