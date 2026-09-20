#!/bin/bash
set -e

DOMAIN=${1:-"app.initech.fun"}
PROXY_URL=${2:-"http://127.0.0.1:8080"}
SITE_USER=${3:-"${DOMAIN%%.*}"}
SITE_PASSWORD=${4:-"$(openssl rand -base64 18)"}

echo "========================================"
echo "[04_C] Nuevo sitio Reverse Proxy en CloudPanel"
echo "Dominio: $DOMAIN"
echo "Apunta a: $PROXY_URL"
echo "========================================"
echo ""
echo "Uso tipico: exponer Apache Tomcat (puerto 8080) bajo un dominio."
echo "Ej: ./04_C-add-site-reverse-proxy.sh miapp.initech.fun http://127.0.0.1:8080"
echo ""

if ! command -v clpctl &> /dev/null; then
    echo "ERROR: CloudPanel no esta instalado. Corre primero: ./02-install-cloudpanel.sh"
    exit 1
fi

clpctl site:add:reverse-proxy \
    --domainName="$DOMAIN" \
    --reverseProxyUrl="$PROXY_URL" \
    --siteUser="$SITE_USER" \
    --siteUserPassword="$SITE_PASSWORD"

echo ""
echo "✓ Sitio creado"
echo ""
echo "Usuario del sitio:   $SITE_USER"
echo "Contraseña:          $SITE_PASSWORD"
echo ""
echo "Prueba con: curl http://$DOMAIN"
echo "Obtener SSL: ./04_D-install-ssl-cloudpanel.sh $DOMAIN"
