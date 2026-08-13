#!/bin/sh

set -eu

repo_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
state_dir="$repo_dir/.local"
env_file="$state_dir/stack.env"
dev_compose="$repo_dir/deploy/docker-compose.dev.yml"
prod_compose="$repo_dir/deploy/docker-compose.prod.yml"
resource_registry="$repo_dir/scripts/project-resource-registry"

make_secret() {
    openssl rand -hex "$1"
}

set_env_value() {
    name=$1
    value=$2
    temporary=$(mktemp "$state_dir/stack.env.XXXXXX")
    awk -F= -v name="$name" '$1 != name { print }' "$env_file" > "$temporary"
    printf '%s=%s\n' "$name" "$value" >> "$temporary"
    chmod 600 "$temporary"
    mv "$temporary" "$env_file"
}

ensure_env() {
    umask 077
    mkdir -p "$state_dir"
    if [ ! -f "$env_file" ]; then
        {
            printf '%s\n' 'APP_DEBUG=true'
            printf '%s\n' 'DEPLOYMENT_MODE=standalone'
            printf '%s\n' 'ADMIN_INITIAL_EMAIL=admin@example.com'
        } > "$env_file"
        chmod 600 "$env_file"
        printf 'Created local environment in %s\n' "$env_file"
    fi

    grep -q '^DEPLOYMENT_MODE=' "$env_file" || printf '%s\n' 'DEPLOYMENT_MODE=standalone' >> "$env_file"
    grep -q '^JWT_SECRET=..' "$env_file" || set_env_value JWT_SECRET "$(make_secret 32)"
    grep -q '^TENANT_IDENTIFIER_HMAC_KEY=' "$env_file" || printf 'TENANT_IDENTIFIER_HMAC_KEY=%s\n' "$(make_secret 32)" >> "$env_file"
    grep -q '^PLATFORM_IDENTIFIER_HMAC_KEY=' "$env_file" || printf 'PLATFORM_IDENTIFIER_HMAC_KEY=%s\n' "$(make_secret 32)" >> "$env_file"
    grep -q '^ADMIN_INITIAL_EMAIL=..' "$env_file" || set_env_value ADMIN_INITIAL_EMAIL admin@example.com
    grep -q '^ADMIN_INITIAL_PASSWORD=..' "$env_file" || set_env_value ADMIN_INITIAL_PASSWORD "Local$(make_secret 8)9"
    "$resource_registry" local-stack-env --deployment-target local-development |
        while IFS='=' read -r name value; do
            set_env_value "$name" "$value"
        done
    "$resource_registry" local-stack-env --deployment-target local-production-preview |
        while IFS='=' read -r name value; do
            set_env_value "$name" "$value"
        done
    if ! grep -q '^DB_USER=peanut_admin_development$' "$env_file" ||
        ! grep -q '^DB_PASS=..' "$env_file"; then
        "$repo_dir/scripts/company-development-database.sh" sync-credentials
    fi
}

load_database_endpoint() {
    consumer=$1
    endpoint_env=$(mktemp "$state_dir/database-endpoint.XXXXXX")
    "$resource_registry" database-env \
        --deployment-target local-development \
        --consumer "$consumer" > "$endpoint_env"
    set -a
    # shellcheck disable=SC1090 -- generated from the validated project registry.
    . "$endpoint_env"
    set +a
    rm -f "$endpoint_env"
}

compose_dev() {
    docker compose --env-file "$env_file" -f "$dev_compose" "$@"
}

compose_prod() {
    PEANUT_DEPLOYMENT_TARGET=local-production-preview \
        docker compose --env-file "$env_file" -f "$prod_compose" "$@"
}

show_urls() {
    development_port=$(awk -F= '$1 == "DEV_HTTP_PORT" { print $2 }' "$env_file")
    docs_port=$(awk -F= '$1 == "DOCS_PORT" { print $2 }' "$env_file")
    production_port=$(awk -F= '$1 == "HTTP_PORT" { print $2 }' "$env_file")
    printf '%s\n' \
        "Development: http://127.0.0.1:$development_port/admin/" \
        "PC:          http://127.0.0.1:$development_port/pc/" \
        "Mobile:      http://127.0.0.1:$development_port/mobile/" \
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
        compose_dev up -d --remove-orphans
        show_urls
        ;;
    dev-build)
        ensure_env
        compose_dev up -d --build --remove-orphans
        show_urls
        ;;
    dev-down)
        ensure_env
        compose_dev down --remove-orphans
        ;;
    prod-up)
        ensure_env
        compose_prod up -d
        show_urls
        ;;
    prod-build)
        ensure_env
        compose_prod up -d --build
        show_urls
        ;;
    prod-down)
        ensure_env
        compose_prod down
        ;;
    status)
        ensure_env
        compose_dev ps
        compose_prod ps
        show_urls
        ;;
    credentials)
        ensure_env
        awk -F= '/^(ADMIN_INITIAL_EMAIL|ADMIN_INITIAL_PASSWORD)=/ {print $1 "=" $2}' "$env_file"
        ;;
    database-status)
        ensure_env
        compose_dev run --rm --no-deps php php database/environment-guard.php --current
        ;;
    database-host-status)
        ensure_env
        set -a
        # shellcheck disable=SC1090 -- local credentials are stored outside Git.
        . "$env_file"
        set +a
        load_database_endpoint host
        APP_ENV=development PEANUT_DEPLOYMENT_TARGET=local-development \
            php "$repo_dir/server/database/environment-guard.php" --current
        ;;
    logs)
        ensure_env
        mkdir -p "$repo_dir/output/local-diagnostics"
        log_file="$repo_dir/output/local-diagnostics/backend-live.log"
        printf 'Backend log: %s\n' "$log_file"
        compose_dev logs --no-color --since "${LOG_SINCE:-10m}" -f php nginx | tee -a "$log_file"
        ;;
    *)
        printf 'Usage: %s {dev-up|dev-build|dev-down|prod-up|prod-build|prod-down|status|credentials|database-host-status|database-status|logs}\n' "$0" >&2
        exit 2
        ;;
esac
