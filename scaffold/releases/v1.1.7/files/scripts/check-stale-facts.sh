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
  [[ "$path" == scripts/check-stale-facts.sh ]] && continue
  tracked+=("$path")
done < <(git ls-files)

report_matches \
  'retired development database is present outside explicit historical archives' \
  '192\.168\.192\.2:3306/peanut_admin' \
  "${tracked[@]}"

current_migration_count="$(git ls-files 'server/database/migrations/*.sql' | wc -l | tr -d ' ')"
declared_migration_count="$(sed -nE 's/.*当前数据库入口.*\+ ([0-9]+) migrations.*/\1/p' AGENTS.md)"
if [[ "$declared_migration_count" != "$current_migration_count" ]]; then
  printf 'ERROR: AGENTS.md current migration count is %s, but Git tracks %s migration files\n' \
    "${declared_migration_count:-missing}" "$current_migration_count" >&2
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

if [[ "$failed" -ne 0 ]]; then
  exit 1
fi

echo 'Stale fact check passed.'
