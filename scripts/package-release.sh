#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
ROOT_DIR="$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)"
WEB_DIR="${WEB_DIR:-$ROOT_DIR/web}"
SERVER_DIR="${SERVER_DIR:-$ROOT_DIR/server}"
CORE_DIR="${PEANUT_ADMIN_CORE_DIR:-$ROOT_DIR/../peanut-admin-core}"
SKIP_WEB_BUILD="${SKIP_WEB_BUILD:-0}"
OUTPUT_DIR="${1:-$ROOT_DIR/release/peanut-admin}"
WEB_CORE_LINK="$WEB_DIR/node_modules/@peanut-admin/admin"
RESTORE_LOCAL_WEB_CORE=0

if [[ "$OUTPUT_DIR" != /* ]]; then
  OUTPUT_DIR="$ROOT_DIR/$OUTPUT_DIR"
fi

die() {
  printf 'package-release: %s\n' "$*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || die "missing required command: $1"
}

[[ -d "$WEB_DIR" ]] || die "web directory not found: $WEB_DIR"
[[ -d "$SERVER_DIR" ]] || die "server directory not found: $SERVER_DIR"
[[ ! -e "$OUTPUT_DIR" ]] || die "output already exists; choose a new path or remove it explicitly: $OUTPUT_DIR"
[[ ! -e "$OUTPUT_DIR.tar.gz" ]] || die "archive already exists; choose a new path or remove it explicitly: $OUTPUT_DIR.tar.gz"

if [[ -L "$WEB_CORE_LINK" && -d "$CORE_DIR/packages/web" \
  && "$(realpath "$WEB_CORE_LINK")" == "$(CDPATH= cd -- "$CORE_DIR/packages/web" && pwd)" ]]; then
  RESTORE_LOCAL_WEB_CORE=1
fi

restore_local_web_core() {
  if [[ "$RESTORE_LOCAL_WEB_CORE" == 1 ]]; then
    PEANUT_ADMIN_CORE_DIR="$CORE_DIR" "$ROOT_DIR/scripts/local-core-web" link >/dev/null
  fi
}

if [[ "$SKIP_WEB_BUILD" != "1" ]]; then
  require_command node
  require_command pnpm

  node_major="$(node -p 'process.versions.node.split(".")[0]')"
  [[ "$node_major" =~ ^[0-9]+$ ]] && (( node_major >= 20 )) || \
    die "Node.js 20 or newer is required (found $(node --version))"

  printf '%s\n' 'Installing web dependencies with the locked pnpm graph...'
  pnpm --dir "$WEB_DIR" install --frozen-lockfile
  printf '%s\n' 'Building web/dist...'
  pnpm --dir "$WEB_DIR" build
fi

[[ -f "$WEB_DIR/dist/index.html" ]] || die "frontend build output is missing: $WEB_DIR/dist/index.html"
require_command rsync
require_command composer
require_command tar

output_parent="$(dirname -- "$OUTPUT_DIR")"
mkdir -p "$output_parent"
stage_dir="$(mktemp -d "$output_parent/.peanut-admin-release.XXXXXX")"
cleanup() {
  rm -rf -- "$stage_dir"
  restore_local_web_core
}
trap cleanup EXIT

mkdir -p "$stage_dir/server/public/admin"

# Copy PHP code without local runtime logs, secrets, or frontend dependencies.
# vendor/ is installed into the stage from the locked Composer graph below.
rsync -a \
  --exclude='public/' \
  --exclude='runtime/' \
  --exclude='vendor/' \
  --exclude='node_modules/' \
  --include='.env.example' \
  --exclude='.env' \
  --exclude='.env.*' \
  "$SERVER_DIR/" "$stage_dir/server/"

# Start public/ from the backend tree, then place the SPA below /admin/.
# The backend public root is never used as the frontend destination.
rsync -a "$SERVER_DIR/public/" "$stage_dir/server/public/"
for protected_path in index.php router.php .htaccess storage; do
  [[ ! -e "$WEB_DIR/dist/$protected_path" ]] || \
    die "frontend output contains protected backend path: $protected_path"
done
rsync -a \
  --ignore-existing \
  --exclude='storage/' \
  "$WEB_DIR/dist/" "$stage_dir/server/public/admin/"

printf '%s\n' 'Installing production Composer dependencies into the release...'
build_env="$stage_dir/server/.env.package-build"
umask 077
printf '%s\n' \
  'APP_ENV=production' \
  'APP_DEBUG=false' \
  'DEPLOYMENT_MODE=standalone' \
  'DB_HOST=build-only.invalid' \
  'DB_PORT=3306' \
  'DB_NAME=build_only' \
  'DB_USER=build_only' \
  'DB_PASS=build-only' \
  'DB_PREFIX=pa_' > "$build_env"
PEANUT_SERVER_ENV_FILE="$build_env" composer install \
  --working-dir="$stage_dir/server" \
  --no-dev \
  --prefer-dist \
  --no-interaction \
  --no-progress \
  --optimize-autoloader
rm -f -- "$build_env"

[[ -f "$stage_dir/server/public/index.php" ]] || die 'server/public/index.php was not preserved'
[[ -f "$stage_dir/server/public/router.php" ]] || die 'server/public/router.php was not preserved'
[[ -f "$stage_dir/server/public/.htaccess" ]] || die 'server/public/.htaccess was not preserved'
[[ -d "$stage_dir/server/public/storage" ]] || die 'server/public/storage was not preserved'
[[ -f "$stage_dir/server/public/admin/index.html" ]] || die 'server/public/admin/index.html was not built'
[[ -f "$stage_dir/server/vendor/autoload.php" ]] || die 'production Composer dependencies are missing'

if [[ -n "$(find "$stage_dir" -type f -name '.env*' ! -name '.env.example' -print -quit)" ]]; then
  die 'release contains a runtime or temporary environment file'
fi

if [[ -n "$(find "$stage_dir" -type d \( -name node_modules -o -name web \) -print -quit)" ]]; then
  die 'release contains node_modules or a web source directory'
fi

commit="unknown"
if command -v git >/dev/null 2>&1; then
  commit="$(git -C "$ROOT_DIR" rev-parse --short HEAD 2>/dev/null || printf '%s' unknown)"
fi
{
  printf 'product=Peanut Admin\n'
  printf 'commit=%s\n' "$commit"
  printf 'built_at_utc=%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
  printf 'layout=server-public-admin-spa\n'
} > "$stage_dir/release-manifest.txt"

mv -- "$stage_dir" "$OUTPUT_DIR"
restore_local_web_core
trap - EXIT
printf 'release: %s\n' "$OUTPUT_DIR"
tar -C "$(dirname -- "$OUTPUT_DIR")" -czf "$OUTPUT_DIR.tar.gz" "$(basename -- "$OUTPUT_DIR")"
printf 'archive: %s.tar.gz\n' "$OUTPUT_DIR"
