#!/bin/sh

set -eu

cd /workspace/server
test -f vendor/autoload.php || composer install --no-interaction --prefer-dist

php database/environment-guard.php --wait=60
php database/install.php --skip-if-installed
php database/environment-guard.php --current

exec php think run --host=0.0.0.0 --port=8000
