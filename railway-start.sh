#!/usr/bin/env bash
set -euo pipefail

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

# Map Railway MySQL plugin variables when present
if [[ -n "${MYSQLHOST:-}" ]]; then
  export DB_CONNECTION="${DB_CONNECTION:-mysql}"
  export DB_HOST="${DB_HOST:-$MYSQLHOST}"
  export DB_PORT="${DB_PORT:-${MYSQLPORT:-3306}}"
  export DB_DATABASE="${DB_DATABASE:-${MYSQLDATABASE:-railway}}"
  export DB_USERNAME="${DB_USERNAME:-${MYSQLUSER:-root}}"
  export DB_PASSWORD="${DB_PASSWORD:-${MYSQLPASSWORD:-}}"
fi

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
