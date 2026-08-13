#!/bin/sh

set -eu

repo_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
state_dir="$repo_dir/.local"
env_file="$state_dir/stack.env"
dev_compose="$repo_dir/deploy/docker-compose.dev.yml"
prod_compose="$repo_dir/deploy/docker-compose.prod.yml"

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
        db_pass=$(make_secret 24)
        root_pass=$(make_secret 24)
        jwt_secret=$(make_secret 32)
        admin_password="Local$(make_secret 8)9"
        {
            printf '%s\n' 'APP_DEBUG=true'
            printf '%s\n' 'DEV_HTTP_PORT=8080'
            printf '%s\n' 'HTTP_PORT=18092'
            printf '%s\n' 'PHP_PORT=8000'
            printf '%s\n' 'VITE_PORT=5173'
            printf '%s\n' 'PC_PORT=3100'
            printf '%s\n' 'MOBILE_PORT=5174'
            printf '%s\n' 'DOCS_PORT=4173'
            printf '%s\n' 'PEANUT_DATABASE_RESOURCE_ID=peanut-admin-mysql84-development'
            printf '%s\n' 'DB_HOST=192.168.192.2'
            printf '%s\n' 'DB_PORT=20183'
            printf '%s\n' 'DB_NAME=peanut_admin_development'
            printf '%s\n' 'DB_USER=peanut_admin'
            printf 'DB_PASS=%s\n' "$db_pass"
            printf 'MYSQL_ROOT_PASSWORD=%s\n' "$root_pass"
            printf 'JWT_SECRET=%s\n' "$jwt_secret"
            printf '%s\n' 'DEPLOYMENT_MODE=standalone'
            printf 'TENANT_IDENTIFIER_HMAC_KEY=%s\n' "$(make_secret 32)"
            printf 'PLATFORM_IDENTIFIER_HMAC_KEY=%s\n' "$(make_secret 32)"
            printf '%s\n' 'ADMIN_INITIAL_EMAIL=admin@example.com'
            printf 'ADMIN_INITIAL_PASSWORD=%s\n' "$admin_password"
        } > "$env_file"
        printf 'Created local credentials in %s\n' "$env_file"
    fi

    grep -q '^DEV_HTTP_PORT=' "$env_file" || printf '%s\n' 'DEV_HTTP_PORT=8080' >> "$env_file"
    grep -q '^DEPLOYMENT_MODE=' "$env_file" || printf '%s\n' 'DEPLOYMENT_MODE=standalone' >> "$env_file"
    grep -q '^TENANT_IDENTIFIER_HMAC_KEY=' "$env_file" || printf 'TENANT_IDENTIFIER_HMAC_KEY=%s\n' "$(make_secret 32)" >> "$env_file"
    grep -q '^PLATFORM_IDENTIFIER_HMAC_KEY=' "$env_file" || printf 'PLATFORM_IDENTIFIER_HMAC_KEY=%s\n' "$(make_secret 32)" >> "$env_file"
    grep -q '^ADMIN_INITIAL_EMAIL=..' "$env_file" || set_env_value ADMIN_INITIAL_EMAIL admin@example.com
    grep -q '^ADMIN_INITIAL_PASSWORD=..' "$env_file" || set_env_value ADMIN_INITIAL_PASSWORD "Local$(make_secret 8)9"
    set_env_value PEANUT_DATABASE_RESOURCE_ID peanut-admin-mysql84-development
    set_env_value DB_HOST 192.168.192.2
    set_env_value DB_PORT 20183
    set_env_value DB_NAME peanut_admin_development
    if ! grep -q '^DB_USER=peanut_admin_development$' "$env_file"; then
        "$repo_dir/scripts/company-development-database.sh" sync-credentials
    fi
}

compose_dev() {
    docker compose --env-file "$env_file" -f "$dev_compose" "$@"
}

compose_prod() {
    PEANUT_DEPLOYMENT_TARGET=local-production-preview \
        docker compose --env-file "$env_file" -f "$prod_compose" "$@"
}

show_urls() {
    printf '%s\n' \
        'Development: http://127.0.0.1:8080/admin/' \
        'PC:          http://127.0.0.1:8080/pc/' \
        'Mobile:      http://127.0.0.1:8080/mobile/' \
        'Docs:        http://127.0.0.1:4173/' \
        'Production:  http://127.0.0.1:18092/admin/'
    printf '%s\n' 'Database:    peanut-admin-mysql84-development @ 192.168.192.2:20183/peanut_admin_development'
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
    logs)
        ensure_env
        mkdir -p "$repo_dir/output/local-diagnostics"
        log_file="$repo_dir/output/local-diagnostics/backend-live.log"
        printf 'Backend log: %s\n' "$log_file"
        compose_dev logs --no-color --since "${LOG_SINCE:-10m}" -f php nginx | tee -a "$log_file"
        ;;
    *)
        printf 'Usage: %s {dev-up|dev-build|dev-down|prod-up|prod-build|prod-down|status|credentials|database-status|logs}\n' "$0" >&2
        exit 2
        ;;
esac
