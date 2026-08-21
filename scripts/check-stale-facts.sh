#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

failed=0

report_matches() {
  local label="$1"
  local pattern="$2"
  shift 2
  local matches
  matches="$(git grep -n -E "$pattern" -- "$@" 2>/dev/null || true)"
  if [[ -n "$matches" ]]; then
    printf 'ERROR: %s\n%s\n' "$label" "$matches" >&2
    failed=1
  fi
}

# Historical raw evidence remains immutable under output/. The audit file is the
# one documentation exception because it must record the retired original fact.
tracked=()
while IFS= read -r path; do
  [[ "$path" == output/* ]] && continue
  [[ "$path" == docs/maintenance/stale-facts-audit.md ]] && continue
  [[ "$path" == docs/development-database-resource.md ]] && continue
  [[ "$path" == docs/architecture/* ]] && continue
  [[ "$path" == docs/product-status/releases/* ]] && continue
  [[ "$path" == scripts/check-stale-facts.sh ]] && continue
  tracked+=("$path")
done < <(git ls-files)

report_matches \
  'retired development database is present outside explicit historical archives' \
  '192\.168\.192\.2:3306/peanut_admin' \
  "${tracked[@]}"

application_migrations="$(find server/database/migrations -maxdepth 1 -type f -name '*.sql' -print 2>/dev/null || true)"
if [[ -n "$application_migrations" ]]; then
  printf 'ERROR: v3.0 fresh-only baseline still contains application migration files:\n%s\n' \
    "$application_migrations" >&2
  failed=1
fi
if [[ -e server/database/migrate.php ]]; then
  printf 'ERROR: v3.0 fresh-only baseline still contains server/database/migrate.php\n' >&2
  failed=1
fi

report_matches \
  'completed MT06 release was restored as the current critical path' \
  '当前关键路径是 MT06' \
  AGENTS.md docs

report_matches \
  'shared legacy administrator password was restored as current guidance' \
  '管理员账号[^[:cntrl:]]*admin123456' \
  AGENTS.md README.md docs docs-site

report_matches \
  'completed multi-tenant phase was restored as a future next step' \
  '下一阶段[^[:cntrl:]]*推进多租户' \
  AGENTS.md docs/plans

report_matches \
  'paused SaaS plan was restored as a current PRE-S01 work pointer' \
  '当前首个可领取项仍是 PRE-S01' \
  docs/plans/saas-enhancement-development-plan.md

# Current operator guidance is intentionally checked separately from immutable historical
# release records. These checks prevent a published contract from silently drifting back to
# the retired demo credential or the removed migration command.
report_matches \
  'retired demo credential appears in current operator guidance' \
  'peanut1234xx' \
  README.md docs/operations/demo-access.md docs/operations/local-demo-access.md docs/operations/release-and-application-lifecycle.md docs/peanut-admin-release-deployment.md docs-site resources/project-resources.json scripts/local-multi-tenant-demo scripts/deploy-release server/app/common/service/DemoAccountPolicy.php server/database/install.php server/database/seed-multi-tenant-demo.php

report_matches \
  'removed deploy-release upgrade flag appears in current docs' \
  'scripts/deploy-release[^[:cntrl:]]*--upgrade' \
  README.md docs/create-application.md docs/scaffold-upgrade.md docs/operations docs/peanut-admin-development-guide.md docs/peanut-admin-release-deployment.md docs-site

report_matches \
  'removed database migration entrypoint appears in current docs' \
  'server/database/migrate\.php|database/migrate\.php' \
  README.md docs/create-application.md docs/scaffold-upgrade.md docs/operations docs/peanut-admin-development-guide.md docs/peanut-admin-release-deployment.md docs-site

report_matches \
  'current public docs still identify the retired v2/v3.0.0 baseline' \
  '当前[^[:cntrl:]]*v3\.0\.0|当前源码[^[:cntrl:]]*2\.0\.0|当前正式源码版本[^[:cntrl:]]*v2\.0\.0' \
  README.md docs-site/getting-started.md docs-site/deployment.md docs-site/releases.md docs/create-application.md docs/peanut-admin-development-guide.md

if [[ "$failed" -ne 0 ]]; then
  exit 1
fi

echo 'Stale fact check passed.'
