#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
SITE_USER=${2:-"${DOMAIN%%.*}"}

echo "========================================"
echo "[05] Deploy de Landing Page (CloudPanel)"
echo "Dominio: $DOMAIN"
echo "Usuario del sitio: $SITE_USER"
echo "========================================"
echo ""

SITE_ROOT="/home/$SITE_USER/htdocs/$DOMAIN"
PUBLIC_DIR="$SITE_ROOT/public"

if [ ! -d "$SITE_ROOT" ]; then
    echo "ERROR: no existe $SITE_ROOT"
    echo "Crea el sitio primero: ./04_A-add-site-php.sh $DOMAIN $SITE_USER"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# El vhost 'Generic' de CloudPanel sirve desde .../$DOMAIN/public
sudo mkdir -p "$PUBLIC_DIR"
sudo cp -r "$SCRIPT_DIR/../landing-page/"* "$PUBLIC_DIR/"

sudo chown -R "$SITE_USER:$SITE_USER" "$SITE_ROOT"
sudo find "$PUBLIC_DIR" -type f -exec chmod 644 {} \;
sudo find "$PUBLIC_DIR" -type d -exec chmod 755 {} \;

echo ""
echo "✓ Landing page desplegada en $PUBLIC_DIR"
ls -la "$PUBLIC_DIR"
echo ""
echo "Prueba con: curl http://$DOMAIN"
