#!/bin/bash
set -e

echo "========================================"
echo "[05] Deploy de Landing Page"
echo "========================================"
echo ""

APP_PATH="/var/www/landing-page"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Vuelve a copiar los archivos de landing-page/ del repo al destino real.
# Util para actualizar la landing page sin tener que reconfigurar Nginx de nuevo
# (03-configure-nginx-site.sh ya hizo esta copia la primera vez).
sudo cp -r $SCRIPT_DIR/../landing-page/* $APP_PATH/

sudo chown -R www-data:www-data $APP_PATH   # www-data es el usuario con el que corre Nginx
sudo find $APP_PATH -type f -exec chmod 644 {} \;   # archivos: lectura para todos, escritura solo dueño
sudo find $APP_PATH -type d -exec chmod 755 {} \;   # carpetas: necesitan permiso de "entrar" (ejecucion) ademas de lectura

sudo systemctl reload nginx

echo ""
echo "✓ Landing page desplegada en $APP_PATH"
ls -la $APP_PATH
