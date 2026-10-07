#!/bin/sh
set -e

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${APP_CONFIG_CACHE:-true}" = "true" ]; then
    php artisan config:cache --no-interaction
    php artisan view:cache --no-interaction
fi

exec "$@"
