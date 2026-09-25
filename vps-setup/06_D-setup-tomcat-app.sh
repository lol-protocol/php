#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

DOMAIN=${1:-"app.initech.fun"}
CONTEXT_PATH=${2:-""}

print_header "06_D" "Configurando Nginx -> Tomcat (Dominio: $DOMAIN, Context path: /${CONTEXT_PATH})"

if ! systemctl is-active --quiet tomcat10; then
    echo "ERROR: Tomcat no esta corriendo. Corre primero: ./02_H-install-tomcat.sh"
    exit 1
fi

setup_nginx_domain_logs "$DOMAIN"

# Cuando 'proxy_pass' incluye una URI (aunque sea "/"), Nginx reemplaza el
# prefijo de la location con esa URI y le PEGA el resto de la peticion tal
# cual, sin agregar ninguna barra. Sin barra final aqui, una peticion a
# /pagina1 llegaria a Tomcat como "/miapppagina1" (pegado, sin separador)
# en vez de "/miapp/pagina1". Por eso forzamos que termine en "/" siempre.
PROXY_TARGET="http://127.0.0.1:8080/${CONTEXT_PATH}"
[[ "$PROXY_TARGET" != */ ]] && PROXY_TARGET="${PROXY_TARGET}/"

# Nginx aqui actua puramente como reverse proxy: no sirve archivos propios,
# solo reenvia todo el trafico del dominio publico hacia Tomcat (puerto 8080
# local). El $CONTEXT_PATH es la subcarpeta bajo la que Tomcat publica tu
# app (el nombre del .war sin la extension, ver el aviso final del script).
sudo tee /etc/nginx/sites-available/$DOMAIN > /dev/null <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    access_log /var/log/nginx/$DOMAIN/access.log;
    error_log /var/log/nginx/$DOMAIN/error.log;

    location / {
        proxy_pass $PROXY_TARGET;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF

# El enlace en sites-enabled es lo que realmente activa el sitio
sudo ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/$DOMAIN

sudo nginx -t              # valida ANTES de recargar, para no tumbar los sitios que ya funcionan
sudo systemctl reload nginx

# Si no se paso context path, la app va en la raiz de Tomcat, cuyo archivo
# se llama ROOT.war (nunca ".war" a secas -- ese nombre no es valido)
WAR_NAME="${CONTEXT_PATH:-ROOT}.war"

echo ""
echo "✓ Nginx configurado como reverse proxy hacia Tomcat"
echo ""
echo "Despliega tu .war en: /var/lib/tomcat10/webapps/${WAR_NAME}"
echo "(Tomcat lo despliega automaticamente al detectarlo)"
echo ""
echo "Prueba con: curl http://$DOMAIN"
echo ""
echo "Para SSL, usa: ./04-setup-ssl.sh $DOMAIN admin@$DOMAIN"
