#!/bin/sh

set -eu

cd /var/www/peanut-admin

backend_source=${PEANUT_SERVER_ENV_FILE:-/var/www/peanut-admin/server/.env.source}
backend_runtime=/var/www/peanut-admin/server/.env.container

[ -f "$backend_source" ] || {
    printf 'backend environment source is missing\n' >&2
    exit 1
}
[ ! -L "$backend_source" ] || {
    printf 'backend environment source must not be a symlink\n' >&2
    exit 1
}
install -o www-data -g www-data -m 600 "$backend_source" "$backend_runtime"
export PEANUT_SERVER_ENV_FILE="$backend_runtime"

if [ "${1:-}" = cron ]; then
    if [ "${PEANUT_INSTALLATION_MODE:-automatic}" = guided ]; then
        until php server/database/install.php --status >/dev/null 2>&1; do
            sleep 10
        done
    fi
    exec sh -c 'while :; do php server/think crontab; sleep 60; done'
fi

php server/database/environment-guard.php --wait=60
case "${PEANUT_INSTALLATION_MODE:-automatic}" in
    automatic)
        php server/database/install.php --skip-if-installed
        php server/database/environment-guard.php --current
        ;;
    guided)
        php server/database/install.php --preflight
        ;;
    *)
        printf 'PEANUT_INSTALLATION_MODE must be automatic or guided\n' >&2
        exit 1
        ;;
esac
touch /tmp/peanut-ready
exec php-fpm -F
