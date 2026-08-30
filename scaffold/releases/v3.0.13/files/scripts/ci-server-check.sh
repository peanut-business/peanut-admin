#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
BACKEND_ENV="${PEANUT_SERVER_ENV_FILE:-$ROOT/server/.env}"
[[ -f "$BACKEND_ENV" ]] || { echo "ERROR: backend environment is missing: $BACKEND_ENV" >&2; exit 2; }
export PEANUT_SERVER_ENV_FILE="$BACKEND_ENV"

run_php_test() {
  php -r 'require $argv[1]; require $argv[2];' \
    "$ROOT/server/bootstrap/environment.php" "$ROOT/$1"
}

mode="${1:-}"
if [[ "$mode" != '--fast' && "$mode" != '--full' ]]; then
  echo 'ERROR: ci-server-check.sh requires --fast or --full' >&2
  exit 2
fi

php scripts/check-admin-api-permissions.php

lint_php() {
  local path
  for path in "$@"; do
    php -l "$path"
  done
}

if [[ "$mode" == '--full' ]]; then
  php_files=()
  while IFS= read -r -d '' path; do
    php_files+=("$path")
  done < <(find server/app server/config server/database server/route -type f -name '*.php' -print0)
  lint_php "${php_files[@]}"

  tests=(
    server/tests/Productization/FreshSchemaBaselineTest.php
    server/tests/Multitenancy/NativeAdminIdentityRuntimeContractTest.php
    server/tests/Multitenancy/OfficialCapabilityTenantQualificationTest.php
    server/tests/Productization/MemberFinanceHostTest.php
    server/tests/Productization/PluginArtifactContractTest.php
    server/tests/Productization/PluginModuleContractTest.php
    server/tests/Productization/OfficialArticleModuleContractTest.php
    server/tests/Productization/PluginLifecycleMigrationContractTest.php
  )
  for test_file in "${tests[@]}"; do
    run_php_test "$test_file"
  done
  exit 0
fi

base="${CI_BASE_REF:-}"
if [[ -z "$base" ]]; then
  echo 'ERROR: CI_BASE_REF is required for --fast' >&2
  exit 2
fi

# A dev-to-main promotion is an integration pointer movement, not a feature
# slice. Its behavior groups have already passed on their individual dev PRs
# and in the fixed-candidate qualification; repeating every historical matcher
# here both violates gate ownership and can select obsolete baseline tests.
promotion=0
if [[ "${CI_BASE_BRANCH:-}" == main && "${CI_HEAD_BRANCH:-}" == dev ]]; then
  promotion=1
fi

changed_file="$(mktemp "${TMPDIR:-/tmp}/peanut-admin-changed-server.XXXXXX")"
changed_php_file="$(mktemp "${TMPDIR:-/tmp}/peanut-admin-changed-php.XXXXXX")"
selected_file="$(mktemp "${TMPDIR:-/tmp}/peanut-admin-focused-tests.XXXXXX")"
trap 'rm -f -- "$changed_file" "$changed_php_file" "$selected_file"' EXIT
git diff --name-only "$base...HEAD" -- server > "$changed_file"

select_test() {
  local path="$1"
  if [[ -f "$path" ]]; then
    printf '%s\n' "$path" >> "$selected_file"
  fi
}

while IFS= read -r path; do
  [[ -z "$path" ]] && continue
  # Deleted PHP files are valid convergence changes, but cannot be linted.
  if [[ "$path" == *.php && -f "$path" ]]; then
    printf '%s\n' "$path" >> "$changed_php_file"
  fi
  if [[ "$path" == server/tests/*.php || "$path" == server/tests/*/*.php ]]; then
    select_test "$path"
  fi

  case "$path" in
    server/app/platform/service/plugin/*|server/app/command/Plugin*.php|server/app/Modules/Fixture/DeliveryRecord/*|server/app/Modules/Official/*|server/route/official_*.php|plugins/*|plugins.lock|server/config/modules.php|server/resources/schemas/plugin.schema.json)
      select_test server/tests/Productization/PluginArtifactContractTest.php
      select_test server/tests/Productization/PluginModuleContractTest.php
      select_test server/tests/Productization/PluginLifecycleMigrationContractTest.php
      select_test server/tests/Productization/OfficialArticleModuleContractTest.php
      select_test server/tests/Multitenancy/OfficialCapabilityTenantQualificationTest.php
      ;;
    server/app/platform/controller/PlatformTenantController.php|server/app/platform/service/PlatformTenantQueryService.php|server/tests/Multitenancy/PlatformTenantReadApiTest.php)
      select_test server/tests/Multitenancy/PlatformTenantReadApiTest.php
      ;;
    server/app/platform/service/PlatformRuntimeFactory.php)
      select_test server/tests/Multitenancy/PlatformTenantModuleHttpWiringTest.php
      select_test server/tests/Multitenancy/PlatformTenantReadApiTest.php
      select_test server/tests/Multitenancy/PlatformOperatorBoundaryTest.php
      ;;
    server/app/common/service/external/*|server/app/api/controller/PaymentNotifyController.php|server/app/api/controller/OfficialAccountController.php|server/app/api/controller/OAuthController.php)
      select_test server/tests/Multitenancy/ExternalCallbackTenantRoutingTest.php
      ;;
    *member*|*Member*|*account_log*|*AccountLog*)
      select_test server/tests/Productization/MemberFinanceHostTest.php
      select_test server/tests/Multitenancy/OfficialCapabilityTenantQualificationTest.php
      ;;
    *dict*|*Dict*|*article*|*decoration*|*Decoration*|*notice*|*notification*|*crontab*|*hot_search*|*HotSearch*|*operation_log*|*/audit/*|*file*|*File*|*cache*|*Cache*|*lock*|*Lock*)
      select_test server/tests/Multitenancy/OfficialCapabilityTenantQualificationTest.php
      ;;
    *tenant*|*Tenant*|server/app/platform/*)
      select_test server/tests/Multitenancy/NativeAdminIdentityRuntimeContractTest.php
      select_test server/tests/Multitenancy/OfficialCapabilityTenantQualificationTest.php
      select_test server/tests/Multitenancy/TenantGovernanceTest.php
      select_test server/tests/Multitenancy/PlatformOperatorBoundaryTest.php
      ;;
    server/app/adminapi/*auth*|server/app/common/*auth*|server/app/common/*permission*)
      select_test server/tests/Productization/AdminPermissionHostTest.php
      select_test server/tests/Multitenancy/NativeAdminIdentityRuntimeContractTest.php
      ;;
    server/config/admin_api_access.php|server/route/*.php|scripts/check-admin-api-permissions.php)
      select_test server/tests/Productization/AdminPermissionHostTest.php
      select_test server/tests/Multitenancy/NativeAdminIdentityRuntimeContractTest.php
      ;;
    server/database/install.php|server/database/environment-guard.php|server/database/init.sql)
      select_test server/tests/Productization/FreshSchemaBaselineTest.php
      select_test server/tests/Multitenancy/NativeAdminIdentityRuntimeContractTest.php
      select_test server/tests/Multitenancy/OfficialCapabilityTenantQualificationTest.php
      ;;
  esac
done < "$changed_file"

while IFS= read -r path; do
  [[ -n "$path" ]] && php -l "$path"
done < "$changed_php_file"

if [[ "$promotion" == 1 ]]; then
  echo 'Focused server gates: dev-to-main promotion; PHP lint only'
  exit 0
fi

if [[ ! -s "$selected_file" ]]; then
  echo 'Focused server gates: no behavior test mapped; syntax and manifest checks only'
  exit 0
fi

test_count=0
while IFS= read -r test_file; do
  [[ -z "$test_file" ]] && continue
  if [[ "$test_file" == 'server/tests/Multitenancy/TenantGovernanceTest.php' ]]; then
    php "$test_file"
  else
    run_php_test "$test_file"
  fi
  test_count=$((test_count + 1))
done < <(sort -u "$selected_file")

echo "Focused server gates: ${test_count} test file(s)"
