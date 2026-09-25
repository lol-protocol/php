#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
# Debe coincidir con el APP_PATH de 03-configure-nginx-site.sh (una carpeta
# por dominio) -- si no, esto reescribiria la carpeta equivocada o una que
# Nginx ni siquiera esta sirviendo.
APP_PATH="/var/www/landing-page/$DOMAIN"

echo "========================================"
echo "[05] Deploy de Landing Page para $DOMAIN"
echo "========================================"
echo ""

if [ ! -d "$APP_PATH" ]; then
    echo "ERROR: no existe $APP_PATH"
    echo "Crea el sitio primero: ./03-configure-nginx-site.sh $DOMAIN"
    exit 1
fi

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
