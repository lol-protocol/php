#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

APP_NAME=${1:-}
DOMAIN=${2:-}
require_arg "$DOMAIN" "$0 <nombre-app> <dominio>   (ej. $0 tienda tienda.initech.fun)"
require_valid name "$APP_NAME" "El nombre de la app (argumento 1)"
require_valid domain "$DOMAIN" "El dominio (argumento 2)"
APP_PATH="/var/www/$APP_NAME"

print_header "06_A" "Configurando Aplicacion PHP: $APP_NAME (Dominio: $DOMAIN)"

# Carpeta donde vivira esta app (separada de /var/www/landing-page), y su
# propia carpeta de logs -- si no existe, "nginx -t" falla mas abajo porque
# Nginx no crea directorios el solo, solo los archivos de log dentro de ellos.
claim_nginx_vhost "$DOMAIN" "06_A-php $APP_NAME"
setup_app_directories "$APP_PATH" "$DOMAIN"

# Pagina de prueba minima para confirmar que PHP-FPM + Nginx estan sirviendo
# correctamente antes de subir el codigo real de la app. A proposito NO usa
# phpinfo(): esa funcion expone version de PHP, modulos, rutas del servidor
# y variables de entorno a cualquiera que visite la URL por HTTP plano.
echo "Creando página de prueba..."
sudo tee $APP_PATH/index.php > /dev/null <<'EOF'
<?php
echo "PHP-FPM + Nginx funcionando correctamente.";
EOF

sudo chown www-data:www-data $APP_PATH/index.php
sudo chmod 644 $APP_PATH/index.php

# Igual que en 03-configure-nginx-site.sh: un server{} nuevo, con su propio
# dominio y sus propios logs, apuntando a esta carpeta en vez de la landing page
echo "Configurando Nginx para aplicación PHP..."
# El vhost se llama como el DOMINIO (igual que en 03/06_B/06_D), no como la app.
sudo tee "$NGINX_DIR/sites-available/$DOMAIN" > /dev/null <<EOFNGINX
# vps-setup: 06_A-php $APP_NAME
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    root $APP_PATH;
    index index.php index.html;

    access_log /var/log/nginx/$DOMAIN/access.log;
    error_log /var/log/nginx/$DOMAIN/error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # Bloquea el acceso a archivos ocultos tipo .htaccess/.htpasswd
    location ~ /\.ht {
        deny all;
    }
}
EOFNGINX

# Activa el sitio, lo valida con nginx -t (si falla, lo desactiva) y recarga Nginx
enable_nginx_site "$DOMAIN"

echo ""
echo "✓ Aplicación PHP configurada"
echo "Accede a http://$DOMAIN/index.php para probar"
echo ""
echo "Proximos pasos:"
echo "1. Configurar SSL: ./04-setup-ssl.sh $DOMAIN admin@$DOMAIN"
echo "2. Copiar tu código PHP en: $APP_PATH (reemplazando este index.php de prueba)"
echo "3. Ajustar permisos: sudo chown -R www-data:www-data $APP_PATH"
