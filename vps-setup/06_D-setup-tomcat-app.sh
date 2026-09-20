#!/bin/bash
set -e

DOMAIN=${1:-"app.initech.fun"}
CONTEXT_PATH=${2:-""}

echo "========================================"
echo "[06_D] Configurando Nginx -> Tomcat"
echo "Dominio: $DOMAIN"
echo "Context path: /${CONTEXT_PATH}"
echo "========================================"
echo ""

if ! systemctl is-active --quiet tomcat10; then
    echo "ERROR: Tomcat no esta corriendo. Corre primero: ./02_H-install-tomcat.sh"
    exit 1
fi

sudo mkdir -p /var/log/nginx/$DOMAIN

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
        proxy_pass http://127.0.0.1:8080/${CONTEXT_PATH};
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

echo ""
echo "✓ Nginx configurado como reverse proxy hacia Tomcat"
echo ""
echo "Despliega tu .war en: /var/lib/tomcat10/webapps/${CONTEXT_PATH}.war"
echo "(Tomcat lo despliega automaticamente al detectarlo)"
echo ""
echo "Prueba con: curl http://$DOMAIN"
echo ""
echo "Para SSL, usa: ./04-setup-ssl.sh $DOMAIN admin@$DOMAIN"
