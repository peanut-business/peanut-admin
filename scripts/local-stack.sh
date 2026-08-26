#!/bin/sh

set -eu

repo_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
state_dir="$repo_dir/.local"
orchestration_env=${PEANUT_LOCAL_ENV_FILE:-"$state_dir/stack.env"}
backend_env=${PEANUT_SERVER_ENV_FILE:-"$repo_dir/server/.env"}
preview_backend_env=${PEANUT_PREVIEW_SERVER_ENV_FILE:-"$repo_dir/server/.env.local-production-preview"}
env_dir=$(dirname "$orchestration_env")
dev_compose="$repo_dir/deploy/docker-compose.dev.yml"
prod_compose="$repo_dir/deploy/docker-compose.prod.yml"
resource_registry="$repo_dir/scripts/project-resource-registry"

die() {
    printf 'local-stack: %s\n' "$*" >&2
    exit 1
}

make_secret() {
    openssl rand -hex "$1"
}

set_env_value() (
    target=$1
    name=$2
    value=$3
    target_dir=$(dirname "$target")
    temporary=$(mktemp "$target_dir/environment.XXXXXX")
    awk -F= -v name="$name" '$1 != name { print }' "$target" > "$temporary"
    printf '%s=%s\n' "$name" "$value" >> "$temporary"
    chmod 600 "$temporary"
    mv "$temporary" "$target"
)

clear_env_value() (
    target=$1
    name=$2
    target_dir=$(dirname "$target")
    temporary=$(mktemp "$target_dir/environment.XXXXXX")
    awk -F= -v name="$name" '$1 != name { print }' "$target" > "$temporary"
    printf '%s=\n' "$name" >> "$temporary"
    chmod 600 "$temporary"
    mv "$temporary" "$target"
)

set_env_default() (
    target=$1
    name=$2
    value=$3
    grep -q "^${name}=." "$target" || set_env_value "$target" "$name" "$value"
)

ensure_env() {
    umask 077
    mkdir -p "$state_dir" "$env_dir" "$(dirname "$backend_env")"
    if [ ! -f "$orchestration_env" ]; then
        : > "$orchestration_env"
        chmod 600 "$orchestration_env"
        printf 'Created local orchestration environment in %s\n' "$orchestration_env"
    fi
    if grep -Eq '^(PHP_(ENV_NAME|APP_|DB_|JWT_|DEPLOYMENT_MODE|PUBLIC_DEFAULT|PLATFORM_|TENANT_|ADMIN_|OWNER_|PEANUT_)|APP_|DB_|JWT_|DEPLOYMENT_MODE=|PUBLIC_DEFAULT_TENANT_FALLBACK=|PLATFORM_(HOSTS|IDENTIFIER_HMAC_KEY|INITIAL_EMAIL|INITIAL_PASSWORD)=|TENANT_|ADMIN_|OWNER_INVITATION_|PEANUT_(DEPLOYMENT_TARGET|DATABASE_|RESOURCE_LEASE_PROOF|STORAGE_|DEMO_|MODULE_))' "$orchestration_env"; then
        die "orchestration environment contains backend configuration: $orchestration_env"
    fi
    if [ ! -f "$backend_env" ]; then
        {
            printf '%s\n' 'APP_ENV=development'
            printf '%s\n' 'APP_DEBUG=true'
            printf '%s\n' 'DEPLOYMENT_MODE=standalone'
            printf '%s\n' 'ADMIN_INITIAL_EMAIL=admin@example.com'
        } > "$backend_env"
        chmod 600 "$backend_env"
        printf 'Created backend environment in %s\n' "$backend_env"
    fi
    [ ! -L "$backend_env" ] || die "backend environment must not be a symlink: $backend_env"
    chmod 600 "$orchestration_env" "$backend_env"

    set_env_value "$backend_env" APP_ENV development
    set_env_value "$backend_env" APP_DEBUG true
    set_env_value "$backend_env" PEANUT_DEPLOYMENT_TARGET local-development
    set_env_value "$backend_env" DEPLOYMENT_MODE standalone
    clear_env_value "$backend_env" DB_ROOT_PASS
    grep -q '^JWT_SECRET=..' "$backend_env" || set_env_value "$backend_env" JWT_SECRET "$(make_secret 32)"
    grep -q '^TENANT_IDENTIFIER_HMAC_KEY=..' "$backend_env" || set_env_value "$backend_env" TENANT_IDENTIFIER_HMAC_KEY "$(make_secret 32)"
    grep -q '^PLATFORM_IDENTIFIER_HMAC_KEY=..' "$backend_env" || set_env_value "$backend_env" PLATFORM_IDENTIFIER_HMAC_KEY "$(make_secret 32)"
    grep -q '^ADMIN_INITIAL_EMAIL=..' "$backend_env" || set_env_value "$backend_env" ADMIN_INITIAL_EMAIL admin@example.com
    grep -q '^ADMIN_INITIAL_PASSWORD=..' "$backend_env" || set_env_value "$backend_env" ADMIN_INITIAL_PASSWORD "Local$(make_secret 8)9"
    set_env_default "$backend_env" PEANUT_PLUGIN_LOCK ../plugins.lock
    set_env_default "$backend_env" PEANUT_MODULE_KERNEL_VERSION 1.0.0
    set_env_default "$backend_env" PEANUT_MODULE_TRUSTED_KEYS_JSON '{}'
    "$resource_registry" local-stack-env --deployment-target local-development |
        while IFS='=' read -r name value; do
            set_env_default "$orchestration_env" "$name" "$value"
        done
    "$resource_registry" local-stack-env --deployment-target local-production-preview |
        while IFS='=' read -r name value; do
            set_env_default "$orchestration_env" "$name" "$value"
        done
    # Daily development uses the registered host endpoint and host PHP runtime.
    "$resource_registry" database-env --deployment-target local-development --consumer host |
        while IFS='=' read -r name value; do set_env_value "$backend_env" "$name" "$value"; done
    if ! grep -q '^DB_USER=peanut_admin_development$' "$backend_env" ||
        ! grep -q '^DB_PASS=..' "$backend_env"; then
        PEANUT_SERVER_ENV_FILE="$backend_env" "$repo_dir/scripts/project-development-database.sh" sync-credentials
    fi
}

prepare_preview_backend_env() {
    [ -f "$backend_env" ] || die "backend environment is missing: $backend_env"
    [ ! -L "$backend_env" ] || die "backend environment must not be a symlink: $backend_env"
    umask 077
    temporary=$(mktemp "$repo_dir/server/.env.preview.XXXXXX")
    cp "$backend_env" "$temporary"
    chmod 600 "$temporary"
    set_env_value "$temporary" APP_ENV production
    set_env_value "$temporary" APP_DEBUG false
    set_env_value "$temporary" PEANUT_DEPLOYMENT_TARGET local-production-preview
    "$resource_registry" database-env --deployment-target local-production-preview --consumer container |
        while IFS='=' read -r name value; do set_env_value "$temporary" "$name" "$value"; done
    mv "$temporary" "$preview_backend_env"
    chmod 600 "$preview_backend_env"
}

compose_dev() {
    docker compose --env-file "$orchestration_env" -f "$dev_compose" "$@"
}

compose_prod() {
    PEANUT_SERVER_ENV_FILE="$preview_backend_env" \
        docker compose --env-file "$orchestration_env" --env-file "$preview_backend_env" -f "$prod_compose" "$@"
}

show_urls() {
    development_port=$(awk -F= '$1 == "DEV_HTTP_PORT" { print $2 }' "$orchestration_env")
    php_port=$(awk -F= '$1 == "PHP_PORT" { print $2 }' "$orchestration_env")
    admin_port=$(awk -F= '$1 == "VITE_PORT" { print $2 }' "$orchestration_env")
    platform_port=$(awk -F= '$1 == "PLATFORM_PORT" { print $2 }' "$orchestration_env")
    pc_port=$(awk -F= '$1 == "PC_PORT" { print $2 }' "$orchestration_env")
    mobile_port=$(awk -F= '$1 == "MOBILE_PORT" { print $2 }' "$orchestration_env")
    docs_port=$(awk -F= '$1 == "DOCS_PORT" { print $2 }' "$orchestration_env")
    production_port=$(awk -F= '$1 == "HTTP_PORT" { print $2 }' "$orchestration_env")
    printf '%s\n' \
        "Development: http://127.0.0.1:$development_port/admin/" \
        "API direct:  http://127.0.0.1:$php_port/" \
        "Admin direct:http://127.0.0.1:$admin_port/admin/" \
        "Platform direct:http://127.0.0.1:$platform_port/platform/" \
        "PC direct:   http://127.0.0.1:$pc_port/pc/" \
        "Mobile direct:http://127.0.0.1:$mobile_port/mobile/" \
        "Docs:        http://127.0.0.1:$docs_port/" \
        "Production:  http://127.0.0.1:$production_port/admin/"
    database_summary=$("$resource_registry" database-env --deployment-target local-development --consumer container)
    database_id=$(printf '%s\n' "$database_summary" | awk -F= '$1 == "PEANUT_DATABASE_RESOURCE_ID" { print $2 }')
    database_host=$(printf '%s\n' "$database_summary" | awk -F= '$1 == "DB_HOST" { print $2 }')
    database_port=$(printf '%s\n' "$database_summary" | awk -F= '$1 == "DB_PORT" { print $2 }')
    database_name=$(printf '%s\n' "$database_summary" | awk -F= '$1 == "DB_NAME" { print $2 }')
    printf 'Database:    %s @ %s:%s/%s\n' "$database_id" "$database_host" "$database_port" "$database_name"
}

case "${1:-}" in
    dev-up)
        ensure_env
        PEANUT_SERVER_ENV_FILE="$backend_env" "$repo_dir/scripts/local-php-runtime" start
        if ! compose_dev up -d --remove-orphans; then
            PEANUT_SERVER_ENV_FILE="$backend_env" "$repo_dir/scripts/local-php-runtime" stop
            exit 1
        fi
        show_urls
        ;;
    dev-build)
        ensure_env
        PEANUT_SERVER_ENV_FILE="$backend_env" "$repo_dir/scripts/local-php-runtime" start
        if ! compose_dev up -d --build --remove-orphans; then
            PEANUT_SERVER_ENV_FILE="$backend_env" "$repo_dir/scripts/local-php-runtime" stop
            exit 1
        fi
        show_urls
        ;;
    dev-down)
        ensure_env
        compose_status=0
        compose_dev down --remove-orphans || compose_status=$?
        PEANUT_SERVER_ENV_FILE="$backend_env" "$repo_dir/scripts/local-php-runtime" stop
        exit "$compose_status"
        ;;
    prod-up)
        ensure_env
        prepare_preview_backend_env
        compose_prod up -d
        show_urls
        ;;
    prod-build)
        ensure_env
        prepare_preview_backend_env
        compose_prod up -d --build
        show_urls
        ;;
    prod-down)
        ensure_env
        if [ -f "$preview_backend_env" ]; then
            compose_prod down
        fi
        ;;
    status)
        ensure_env
        PEANUT_SERVER_ENV_FILE="$backend_env" "$repo_dir/scripts/local-php-runtime" status
        compose_dev ps
        if [ -f "$preview_backend_env" ]; then
            compose_prod ps
        fi
        show_urls
        ;;
    credentials)
        ensure_env
        awk -F= '/^(ADMIN_INITIAL_EMAIL|ADMIN_INITIAL_PASSWORD)=/ {print $1 "=" $2}' "$backend_env"
        ;;
    database-status)
        ensure_env
        PEANUT_SERVER_ENV_FILE="$backend_env" "$repo_dir/scripts/local-php-runtime" status
        "$0" database-host-status
        ;;
    database-host-status)
        ensure_env
        PEANUT_SERVER_ENV_FILE="$backend_env" php "$repo_dir/server/database/environment-guard.php" --current
        ;;
    logs)
        ensure_env
        mkdir -p "$repo_dir/output/local-diagnostics"
        log_file="$repo_dir/output/local-diagnostics/backend-live.log"
        printf 'Backend log: %s\n' "$log_file"
        PEANUT_SERVER_ENV_FILE="$backend_env" "$repo_dir/scripts/local-php-runtime" logs
        compose_dev logs --no-color --since "${LOG_SINCE:-10m}" -f nginx web platform pc mobile docs | tee -a "$log_file"
        ;;
    urls)
        ensure_env
        show_urls
        ;;
    *)
        printf 'Usage: %s {dev-up|dev-build|dev-down|prod-up|prod-build|prod-down|status|credentials|database-host-status|database-status|logs|urls}\n' "$0" >&2
        exit 2
        ;;
esac
