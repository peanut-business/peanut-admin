#!/bin/sh

set -eu

cd /workspace/server
test -f vendor/autoload.php || composer install --no-interaction --prefer-dist

php database/environment-guard.php --wait=60
if ! php database/install.php --skip-if-installed; then
    php database/migrate.php
fi
php database/migrate.php
php database/environment-guard.php --current

exec php think run --host=0.0.0.0 --port=8000
