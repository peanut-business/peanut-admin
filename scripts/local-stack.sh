#!/bin/sh

set -eu

repo_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
state_dir="$repo_dir/.local"
env_file=${PEANUT_LOCAL_ENV_FILE:-"$state_dir/stack.env"}
env_dir=$(dirname "$env_file")
dev_compose="$repo_dir/deploy/docker-compose.dev.yml"
prod_compose="$repo_dir/deploy/docker-compose.prod.yml"
resource_registry="$repo_dir/scripts/project-resource-registry"

make_secret() {
    openssl rand -hex "$1"
}

set_env_value() {
    name=$1
    value=$2
    temporary=$(mktemp "$env_dir/stack.env.XXXXXX")
    awk -F= -v name="$name" '$1 != name { print }' "$env_file" > "$temporary"
    printf '%s=%s\n' "$name" "$value" >> "$temporary"
    chmod 600 "$temporary"
    mv "$temporary" "$env_file"
}

set_env_default() {
    name=$1
    value=$2
    grep -q "^${name}=." "$env_file" || set_env_value "$name" "$value"
}

ensure_env() {
    umask 077
    mkdir -p "$state_dir" "$env_dir"
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
            set_env_default "$name" "$value"
        done
    "$resource_registry" local-stack-env --deployment-target local-production-preview |
        while IFS='=' read -r name value; do
            set_env_default "$name" "$value"
        done
    # Daily development uses the registered host endpoint and host PHP runtime.
    "$resource_registry" database-env --deployment-target local-development --consumer host |
        while IFS='=' read -r name value; do set_env_value "$name" "$value"; done
    if ! grep -q '^DB_USER=peanut_admin_development$' "$env_file" ||
        ! grep -q '^DB_PASS=..' "$env_file"; then
        "$repo_dir/scripts/project-development-database.sh" sync-credentials
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
    php_port=$(awk -F= '$1 == "PHP_PORT" { print $2 }' "$env_file")
    admin_port=$(awk -F= '$1 == "VITE_PORT" { print $2 }' "$env_file")
    platform_port=$(awk -F= '$1 == "PLATFORM_PORT" { print $2 }' "$env_file")
    pc_port=$(awk -F= '$1 == "PC_PORT" { print $2 }' "$env_file")
    mobile_port=$(awk -F= '$1 == "MOBILE_PORT" { print $2 }' "$env_file")
    docs_port=$(awk -F= '$1 == "DOCS_PORT" { print $2 }' "$env_file")
    production_port=$(awk -F= '$1 == "HTTP_PORT" { print $2 }' "$env_file")
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
        "$repo_dir/scripts/local-php-runtime" start
        if ! compose_dev up -d --remove-orphans; then
            "$repo_dir/scripts/local-php-runtime" stop
            exit 1
        fi
        show_urls
        ;;
    dev-build)
        ensure_env
        "$repo_dir/scripts/local-php-runtime" start
        if ! compose_dev up -d --build --remove-orphans; then
            "$repo_dir/scripts/local-php-runtime" stop
            exit 1
        fi
        show_urls
        ;;
    dev-down)
        ensure_env
        compose_status=0
        compose_dev down --remove-orphans || compose_status=$?
        "$repo_dir/scripts/local-php-runtime" stop
        exit "$compose_status"
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
        "$repo_dir/scripts/local-php-runtime" status
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
        "$repo_dir/scripts/local-php-runtime" status
        "$0" database-host-status
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
        "$repo_dir/scripts/local-php-runtime" logs
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
