#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

DOMAIN=${1:-"initech.fun"}
# Debe coincidir con el APP_PATH de 03-configure-nginx-site.sh (una carpeta
# por dominio) -- si no, esto reescribiria la carpeta equivocada o una que
# Nginx ni siquiera esta sirviendo.
APP_PATH="/var/www/landing-page/$DOMAIN"

print_header "05" "Deploy de Landing Page para $DOMAIN"

if [ ! -d "$APP_PATH" ]; then
    echo "ERROR: no existe $APP_PATH"
    echo "Crea el sitio primero: ./03-configure-nginx-site.sh $DOMAIN"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Vuelve a copiar los archivos de landing-page/ del repo al destino real.
# Util para actualizar la landing page sin tener que reconfigurar Nginx de nuevo
# (03-configure-nginx-site.sh ya hizo esta copia la primera vez).
deploy_files "$SCRIPT_DIR/../landing-page" "$APP_PATH"

sudo systemctl reload nginx

echo ""
echo "✓ Landing page desplegada en $APP_PATH"
ls -la $APP_PATH
