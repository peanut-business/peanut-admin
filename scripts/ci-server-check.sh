#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

mode="${1:-}"
if [[ "$mode" != '--fast' && "$mode" != '--full' ]]; then
  echo 'ERROR: ci-server-check.sh requires --fast or --full' >&2
  exit 2
fi

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
    server/tests/Productization/WebsiteConfigServiceTest.php
    server/tests/Productization/BrandScaffoldTest.php
    server/tests/Productization/InstallerBootstrapTest.php
    server/tests/Productization/AdminPermissionHostTest.php
    server/tests/Productization/ReferenceCodesHostTest.php
    server/tests/Productization/FileMediaHostTest.php
    server/tests/Productization/OpsHostTest.php
    server/tests/Productization/MemberFinanceHostTest.php
    server/tests/Productization/ContentDecorationHostTest.php
    server/tests/Productization/NotificationHostTest.php
    server/tests/Productization/PaymentHostTest.php
    server/tests/Productization/OAuthChannelHostTest.php
    server/tests/Multitenancy/ArticleTenantIsolationTest.php
    server/tests/Multitenancy/NoticeTenantIsolationTest.php
    server/tests/Multitenancy/TenantCacheLockIsolationTest.php
    server/tests/Multitenancy/ThinkPhpTenantCacheAdapterTest.php
    server/tests/Multitenancy/FileTenantIsolationTest.php
    server/tests/Multitenancy/OperationLogTenantIsolationTest.php
    server/tests/Multitenancy/CrontabTenantIsolationTest.php
    server/tests/Multitenancy/HotSearchTenantIsolationTest.php
    server/tests/Multitenancy/PlatformOperatorBoundaryTest.php
  )
  for test_file in "${tests[@]}"; do
    php "$test_file"
  done
  exit 0
fi

base="${CI_BASE_REF:-}"
if [[ -z "$base" ]]; then
  echo 'ERROR: CI_BASE_REF is required for --fast' >&2
  exit 2
fi

changed_file="$(mktemp "${TMPDIR:-/tmp}/peanut-admin-changed-server.XXXXXX")"
changed_php_file="$(mktemp "${TMPDIR:-/tmp}/peanut-admin-changed-php.XXXXXX")"
selected_file="$(mktemp "${TMPDIR:-/tmp}/peanut-admin-focused-tests.XXXXXX")"
trap 'rm -f -- "$changed_file" "$changed_php_file" "$selected_file"' EXIT
git diff --name-only "$base...HEAD" -- server > "$changed_file"

select_test() {
  local path="$1"
  [[ -f "$path" ]] && printf '%s\n' "$path" >> "$selected_file"
}

while IFS= read -r path; do
  [[ -z "$path" ]] && continue
  [[ "$path" == *.php ]] && printf '%s\n' "$path" >> "$changed_php_file"
  if [[ "$path" == server/tests/*.php || "$path" == server/tests/*/*.php ]]; then
    select_test "$path"
  fi

  case "$path" in
    *article*) select_test server/tests/Multitenancy/ArticleTenantIsolationTest.php ;;
    *notice*|*notification*) select_test server/tests/Multitenancy/NoticeTenantIsolationTest.php ;;
    *crontab*) select_test server/tests/Multitenancy/CrontabTenantIsolationTest.php ;;
    *hot_search*|*HotSearch*) select_test server/tests/Multitenancy/HotSearchTenantIsolationTest.php ;;
    *operation_log*|*/audit/*) select_test server/tests/Multitenancy/OperationLogTenantIsolationTest.php ;;
    *file*|*File*) select_test server/tests/Multitenancy/FileTenantIsolationTest.php ;;
    *cache*|*Cache*|*lock*|*Lock*)
      select_test server/tests/Multitenancy/TenantCacheLockIsolationTest.php
      select_test server/tests/Multitenancy/ThinkPhpTenantCacheAdapterTest.php
      ;;
    *tenant*|*Tenant*|server/app/platform/*)
      select_test server/tests/Multitenancy/AdminTenantContextTest.php
      select_test server/tests/Multitenancy/TenantGovernanceTest.php
      select_test server/tests/Multitenancy/PlatformOperatorBoundaryTest.php
      ;;
    server/app/adminapi/*auth*|server/app/common/*auth*|server/app/common/*permission*)
      select_test server/tests/Productization/AdminPermissionHostTest.php
      select_test server/tests/Productization/AdminRbacCrudTest.php
      ;;
    server/database/install.php|server/database/migrate.php|server/database/migrations/*tenant*)
      select_test server/tests/Productization/DefaultTenantBootstrapTest.php
      ;;
  esac
done < "$changed_file"

while IFS= read -r path; do
  [[ -n "$path" ]] && php -l "$path"
done < "$changed_php_file"

if [[ ! -s "$selected_file" ]]; then
  echo 'Focused server gates: no behavior test mapped; syntax and manifest checks only'
  exit 0
fi

test_count=0
while IFS= read -r test_file; do
  [[ -z "$test_file" ]] && continue
  php "$test_file"
  test_count=$((test_count + 1))
done < <(sort -u "$selected_file")

echo "Focused server gates: ${test_count} test file(s)"
