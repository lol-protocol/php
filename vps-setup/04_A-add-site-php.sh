#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
SITE_USER=${2:-"${DOMAIN%%.*}"}
SITE_PASSWORD=${3:-"$(openssl rand -base64 18)"}
PHP_VERSION=${4:-"8.3"}

echo "========================================"
echo "[04_A] Nuevo sitio PHP en CloudPanel"
echo "Dominio: $DOMAIN"
echo "Usuario del sitio: $SITE_USER"
echo "PHP: $PHP_VERSION"
echo "========================================"
echo ""

if ! command -v clpctl &> /dev/null; then
    echo "ERROR: CloudPanel no esta instalado. Corre primero: ./02-install-cloudpanel.sh"
    exit 1
fi

clpctl site:add:php \
    --domainName="$DOMAIN" \
    --phpVersion="$PHP_VERSION" \
    --vhostTemplate="Generic" \
    --siteUser="$SITE_USER" \
    --siteUserPassword="$SITE_PASSWORD"

echo ""
echo "✓ Sitio creado"
echo ""
echo "Usuario del sitio:   $SITE_USER"
echo "Contraseña:          $SITE_PASSWORD"
echo "  (guardala; es la que usas para SFTP/SSH de este sitio)"
echo ""
echo "Raiz del sitio: /home/$SITE_USER/htdocs/$DOMAIN/public"
echo ""
echo "Proximos pasos:"
echo "  1. Copiar archivos:  ./05-deploy-landing-page.sh $DOMAIN $SITE_USER"
echo "  2. Obtener SSL:      ./04_D-install-ssl-cloudpanel.sh $DOMAIN"
echo "  (opcional) Base de datos: clpctl db:add --domainName=$DOMAIN --databaseName=... --databaseUserName=... --databaseUserPassword=..."
