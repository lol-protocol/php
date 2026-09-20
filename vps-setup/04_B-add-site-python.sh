#!/bin/bash
set -e

DOMAIN=${1:-"app.initech.fun"}
SITE_USER=${2:-"${DOMAIN%%.*}"}
SITE_PASSWORD=${3:-"$(openssl rand -base64 18)"}
APP_PORT=${4:-"8000"}
PYTHON_VERSION=${5:-"3.12"}

echo "========================================"
echo "[04_B] Nuevo sitio Python en CloudPanel"
echo "Dominio: $DOMAIN"
echo "Usuario del sitio: $SITE_USER"
echo "Puerto de la app: $APP_PORT"
echo "========================================"
echo ""

if ! command -v clpctl &> /dev/null; then
    echo "ERROR: CloudPanel no esta instalado. Corre primero: ./02-install-cloudpanel.sh"
    exit 1
fi

clpctl site:add:python \
    --domainName="$DOMAIN" \
    --pythonVersion="$PYTHON_VERSION" \
    --appPort="$APP_PORT" \
    --siteUser="$SITE_USER" \
    --siteUserPassword="$SITE_PASSWORD"

echo ""
echo "✓ Sitio creado"
echo ""
echo "Usuario del sitio:   $SITE_USER"
echo "Contraseña:          $SITE_PASSWORD"
echo ""
echo "CloudPanel espera que tu app (Flask/FastAPI/Django) escuche en el"
echo "puerto $APP_PORT dentro de: /home/$SITE_USER/htdocs/$DOMAIN/"
echo ""
echo "Proximos pasos:"
echo "  1. Sube tu codigo por SFTP/SSH con el usuario $SITE_USER"
echo "  2. Configura el proceso de la app (CloudPanel te da el detalle en su UI)"
echo "  3. Obtener SSL: ./04_D-install-ssl-cloudpanel.sh $DOMAIN"
