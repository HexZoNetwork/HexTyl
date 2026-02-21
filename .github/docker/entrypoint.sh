#!/bin/ash
set -e

cd /app

mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R nginx:nginx storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

if [ -f artisan ]; then
  php artisan config:clear >/dev/null 2>&1 || true
  php artisan route:clear >/dev/null 2>&1 || true
  php artisan view:clear >/dev/null 2>&1 || true
fi

exec "$@"
