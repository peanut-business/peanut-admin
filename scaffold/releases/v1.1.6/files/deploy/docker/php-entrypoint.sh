#!/bin/sh

set -eu

cd /var/www/peanut-admin
php server/database/environment-guard.php --wait=60
php server/database/install.php --skip-if-installed
php server/database/environment-guard.php --current
touch /tmp/peanut-ready
exec php-fpm -F
