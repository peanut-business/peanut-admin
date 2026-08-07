#!/bin/sh

set -eu

cd /var/www/peanut-admin
php server/database/install.php --skip-if-installed
touch /tmp/peanut-ready
exec php-fpm -F
