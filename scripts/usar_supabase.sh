#!/usr/bin/env bash
# Conecta el Codespace de PetCare a la base de datos de Supabase y deja el enlace público.
# Uso (en la terminal del Codespace):  bash scripts/usar_supabase.sh
set -e
cd "$(dirname "$0")/.."

echo "1/5  Trayendo la última versión de GitHub..."
git pull --no-rebase --no-edit || true

echo "2/5  Instalando el conector de PostgreSQL (solo la primera vez)..."
if ! php -m | grep -qi pdo_pgsql; then
  sudo apt-get update -y >/dev/null
  sudo apt-get install -y libpq-dev >/dev/null
  sudo PHP_INI_DIR="${PHP_INI_DIR:-/usr/local/etc/php}" docker-php-ext-install pdo_pgsql pgsql >/dev/null
fi
php -m | grep -qi pdo_pgsql && echo "     conector listo"

echo "3/5  Datos de Supabase"
read -rsp "     Escribe la contraseña de la base de Supabase y pulsa Enter: " CLAVE; echo
[ -z "$CLAVE" ] && { echo "No escribiste la contraseña."; exit 1; }
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force >/dev/null
# quita la configuración de base de datos anterior y pone la de Supabase
sed -i '/^#\? *DB_/d' .env
cat >> .env <<DATOS
DB_CONNECTION=pgsql
DB_HOST=aws-1-us-east-2.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.uqyuhffuklcvjmlhshmk
DB_PASSWORD='${CLAVE}'
DB_SSLMODE=require
DATOS
sed -i 's#^APP_URL=.*#APP_URL=https://'"${CODESPACE_NAME}"'-8000.'"${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"'#' .env

echo "4/5  Comprobando la conexión con Supabase..."
php artisan config:clear >/dev/null
php artisan migrate --force

echo "5/5  Encendiendo PetCare y abriendo el enlace a todos..."
pkill -f "artisan serve" 2>/dev/null || true
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &
sleep 3
gh codespace ports visibility 8000:public -c "$CODESPACE_NAME" >/dev/null 2>&1 || echo "     (Pon el puerto 8000 en Public desde la pestaña Ports)"

echo
echo "LISTO. Enlace de PetCare conectado a Supabase:"
echo "https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
