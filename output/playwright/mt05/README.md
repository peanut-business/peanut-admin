# MT05 concentrated browser acceptance harness

This directory contains the prepared, not-yet-executed browser business matrix for MT05. Preparing or statically checking the harness is not evidence that MT05 passed. Run the full matrix exactly once after the main owner freezes the candidate and supplies a separately configured multi-tenant Web deployment, Standalone Web deployment, API, PlatformOperator credential, and deployed TenantModule contract.

The real run covers:

1. PlatformOperator browser login.
2. Primary Tenant plus first owner provisioning, activation, and TenantModule configuration.
3. A second active Tenant for the same owner Account so Tenant selection/switch is exercised.
4. Owner login (the first owner is an active `TenantMember`), Tenant selection, representative role/Article permission creation, and Tenant-first Article write/read.
5. Tenant switch, atomic rejection of the old access token, and representative Article use in the target Tenant.
6. Target Tenant suspension, rejection of a tenant-targeted fresh login, and rejection of a business write through the previously active session.
7. Standalone verification that login has no Tenant selector and `/platform/login` does not expose the platform entry.

The final fixture Tenant intentionally remains suspended. The script does not delete data, start services, install packages, or mutate application source.

Both Web URLs must include the repository's fixed `/admin/` public base. When
running the multi-tenant and Standalone Vite servers concurrently from one
checkout, give each server a distinct `cacheDir`; sharing `node_modules/.vite`
lets the two optimizers race and can leave the SPA blank with dependency 504s.

Before starting the API, provide independent high-entropy
`PLATFORM_IDENTIFIER_HMAC_KEY` and `TENANT_IDENTIFIER_HMAC_KEY` values (at least
32 bytes each). Both authentication paths fail closed when their key is absent
or short; the public login response intentionally remains a generic credential
denial. Verify these deployment inputs before spending the one browser run.

Each candidate environment must also hold a project resource lease covering its
database, API/Web ports, Vite cache directories, and evidence output directory.
Use `scripts/project-resource-lease` before creating any mutable resource.

## Preparation checks (no browser)

```bash
node --check output/playwright/mt05/run-multitenant-acceptance.cjs
node output/playwright/mt05/run-multitenant-acceptance.cjs --help
node output/playwright/mt05/run-multitenant-acceptance.cjs --contract-check
node output/playwright/mt05/run-multitenant-acceptance.cjs --dry-run --run-id mt05-candidate
```

`--contract-check` checks the static API and selector anchors listed in `fixture.json`. It does not prove Runtime behavior.

## One authorized candidate run

Keep credentials outside shell history where possible. The harness accepts `MT05_OPERATOR_EMAIL`, `MT05_OPERATOR_PASSWORD`, and `MT05_OWNER_PASSWORD` environment variables; equivalent CLI flags exist for isolated CI runners. `--module-key` must name a Module actually deployed through `PEANUT_MODULE_ROOTS`; `--module-config` must satisfy that Module's schema.

```bash
MT05_OPERATOR_EMAIL='<operator email>' \
MT05_OPERATOR_PASSWORD='<operator password>' \
MT05_OWNER_PASSWORD='<unique fixture password>' \
node output/playwright/mt05/run-multitenant-acceptance.cjs \
  --base-url http://127.0.0.1:5173/admin/ \
  --standalone-base-url http://127.0.0.1:5174/admin/ \
  --api-base-url http://127.0.0.1:8000/ \
  --module-key vendor.module-key \
  --module-config '{"required_property":"candidate-value"}' \
  --run-id mt05-<frozen-candidate> \
  --output-dir output/playwright/mt05/runs/mt05-<frozen-candidate>
```

The output directory is required to be new. A completed run writes `summary.json`, a trace, and minimum screenshots. A failed run writes the failure into the same summary and must not be reported as passed.
