#!/bin/sh
set -eu

mkdir -p /var/www/html/var
chown -R www-data:www-data /var/www/html/var

php /var/www/html/bin/migrate.php
chown -R www-data:www-data /var/www/html/var

exec "$@"