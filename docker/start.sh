#!/bin/sh
# Arranque en el hosting: crea .env, la clave, la base SQLite con los datos iniciales y levanta PetCare.
set -e
cd /app
[ -f .env ] || cp .env.example .env
if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64' .env; then php artisan key:generate --force; fi
if [ ! -f database/database.sqlite ]; then
  touch database/database.sqlite
  php artisan migrate --force --seed
else
  php artisan migrate --force
fi
php artisan config:clear
export PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-4}
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
