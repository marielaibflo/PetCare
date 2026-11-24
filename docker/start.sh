#!/bin/sh
# Arranque en el hosting (Render): crea .env y la clave, prepara la base y levanta PetCare.
# Con DB_CONNECTION=pgsql (Supabase) los datos viven en la nube; si no, usa SQLite dentro del contenedor.
set -e
cd /app
[ -f .env ] || cp .env.example .env
if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64' .env; then php artisan key:generate --force; fi
if [ "$DB_CONNECTION" = "pgsql" ]; then
  php artisan migrate --force
elif [ ! -f database/database.sqlite ]; then
  touch database/database.sqlite
  php artisan migrate --force --seed
else
  php artisan migrate --force
fi
php artisan config:clear
php artisan view:cache >/dev/null 2>&1 || true
export PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-4}
# Se usa el servidor de PHP directo (no "artisan serve"), para que reciba las variables de Render (Supabase, etc.)
cd public
exec php -S 0.0.0.0:"${PORT:-10000}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
