#!/bin/sh

set -eu

case "${DEPLOYMENT_MODE:-}" in
    standalone|multi-tenant) ;;
    *)
        printf 'nginx-select-admin: DEPLOYMENT_MODE must be standalone or multi-tenant\n' >&2
        exit 1
        ;;
esac

source_dir="/opt/peanut-admin/admin/$DEPLOYMENT_MODE"
target="/var/www/peanut-admin/server/public/admin"

if [ ! -f "$source_dir/index.html" ]; then
    printf 'nginx-select-admin: selected admin bundle is unavailable: %s\n' "$DEPLOYMENT_MODE" >&2
    exit 1
fi
if [ -e "$target" ] && [ ! -L "$target" ]; then
    printf 'nginx-select-admin: admin target is not a symbolic link\n' >&2
    exit 1
fi

ln -sfn "$source_dir" "$target"
