#!/bin/sh

set -eu

repo_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
remote=mac-14
remote_dir=/Users/xing/.config/peanut-admin
remote_env="$remote_dir/development-db.env"
remote_compose="$remote_dir/docker-compose.remote-development.yml"
local_state="$repo_dir/.local"
local_env=${PEANUT_LOCAL_ENV_FILE:-"$local_state/stack.env"}
local_state=$(dirname "$local_env")

case "${1:-}" in
    provision)
        ssh "$remote" "umask 077; mkdir -p '$remote_dir'; if [ ! -f '$remote_env' ]; then { printf '%s\n' 'DB_NAME=peanut_admin_development' 'DB_USER=peanut_admin_development'; printf 'DB_PASS=%s\n' \"\$(openssl rand -hex 24)\"; printf 'MYSQL_ROOT_PASSWORD=%s\n' \"\$(openssl rand -hex 24)\"; } > '$remote_env'; chmod 600 '$remote_env'; fi"
        scp -q "$repo_dir/deploy/docker-compose.remote-development.yml" "$remote:$remote_compose"
        ssh "$remote" "/usr/local/bin/docker compose --env-file '$remote_env' -f '$remote_compose' up -d --wait"
        "$0" sync-credentials
        ;;
    sync-credentials)
        mkdir -p "$local_state"
        umask 077
        [ -f "$local_env" ] || : > "$local_env"
        temporary=$(mktemp "$local_state/company-db.XXXXXX")
        ssh "$remote" "awk -F= '\$1 == \"DB_NAME\" || \$1 == \"DB_USER\" || \$1 == \"DB_PASS\" { print }' '$remote_env'" > "$temporary"
        for name in DB_NAME DB_USER DB_PASS; do
            value=$(awk -F= -v name="$name" '$1 == name { sub(/^[^=]*=/, ""); print; exit }' "$temporary")
            merged=$(mktemp "$local_state/stack.env.XXXXXX")
            awk -F= -v name="$name" '$1 != name { print }' "$local_env" > "$merged"
            printf '%s=%s\n' "$name" "$value" >> "$merged"
            chmod 600 "$merged"
            mv "$merged" "$local_env"
        done
        rm -f "$temporary"
        printf '%s\n' 'Company development database credentials synchronized to .local/stack.env'
        ;;
    status)
        ssh "$remote" "/usr/local/bin/docker compose --env-file '$remote_env' -f '$remote_compose' ps && /usr/local/bin/docker inspect peanut-admin-mysql84-development --format 'image={{.Config.Image}} status={{.State.Status}} health={{.State.Health.Status}}'"
        ;;
    *)
        printf 'Usage: %s {provision|sync-credentials|status}\n' "$0" >&2
        exit 2
        ;;
esac
