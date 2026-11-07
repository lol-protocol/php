#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

APP_NAME=${1:-}
DOMAIN=${2:-}
require_arg "$DOMAIN" "$0 <nombre-app> <dominio>   (ej. $0 tienda tienda.initech.fun)"
require_valid name "$APP_NAME" "El nombre de la app (argumento 1)"
require_valid domain "$DOMAIN" "El dominio (argumento 2)"
require_valid size "$MAX_UPLOAD" "MAX_UPLOAD"
require_valid appuser "$APP_NAME" "El nombre de la app (argumento 1)"
APP_USER=$(app_user_name "$APP_NAME")
WWW_DIR=${WWW_DIR:-/var/www}   # reemplazable en los tests (igual que en 10_B)
APP_PATH="$WWW_DIR/$APP_NAME"

print_header "06_A" "Configurando Aplicacion PHP: $APP_NAME (Dominio: $DOMAIN)"

# Carpeta donde vivira esta app (separada de /var/www/landing-page), y su
# propia carpeta de logs -- si no existe, "nginx -t" falla mas abajo porque
# Nginx no crea directorios el solo, solo los archivos de log dentro de ellos.
claim_nginx_vhost "$DOMAIN" "06_A-php $APP_NAME"
# Aislamiento: la app corre con SU usuario en SU pool de PHP-FPM, no como www-data (que comparten Nginx y
# las demas apps). La carpeta es del usuario y del grupo www-data, modo 750: Nginx lee lo estatico y
# las demas apps no entran. El .env (modo 600) solo lo lee el usuario de la app.
create_app_user "$APP_USER" "$APP_PATH"
sudo mkdir -p "$APP_PATH"
own_app_dir "$APP_PATH" "$APP_USER" www-data
setup_nginx_domain_logs "$DOMAIN"
setup_php_pool "$APP_USER"

# Pagina de prueba minima para confirmar que PHP-FPM + Nginx estan sirviendo
# correctamente antes de subir el codigo real de la app. A proposito NO usa
# phpinfo(): esa funcion expone version de PHP, modulos, rutas del servidor
# y variables de entorno a cualquiera que visite la URL por HTTP plano.
if app_has_code "$APP_PATH"; then
    # Re-correr este script (cambiar el limite de subida, el dominio...) NO debe reemplazar tu codigo.
    echo "$APP_PATH ya tiene archivos: se conserva tu codigo (no se crea la pagina de prueba)."
else
    echo "Creando página de prueba..."
    sudo tee "$APP_PATH/index.php" > /dev/null <<'EOF'
<?php
echo "PHP-FPM + Nginx funcionando correctamente.";
EOF
    sudo chown "$APP_USER:www-data" "$APP_PATH/index.php"
    sudo chmod 644 "$APP_PATH/index.php"
fi

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
    client_max_body_size $MAX_UPLOAD;   # subidas (MAX_UPLOAD, 20M por defecto); sin esto, 1 MB

    access_log /var/log/nginx/$DOMAIN/access.log;
    error_log /var/log/nginx/$DOMAIN/error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/$APP_USER.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # Bloquea el acceso a archivos ocultos tipo .htaccess/.htpasswd
    location ~ /\.ht {
        deny all;
    }
}
EOFNGINX

# PHP tiene sus propios limites aparte de los de Nginx (ver lib.sh).
raise_php_upload_limit "$MAX_UPLOAD"

# Base de datos propia (PostgreSQL, si esta instalado): ver setup_app_database en lib.sh. Va despues de la
# pagina de prueba porque el .env cuenta como "codigo" para app_has_code.
setup_app_database "$APP_NAME" "$APP_PATH/.env" "$APP_USER"

# Activa el sitio, lo valida con nginx -t (si falla, lo desactiva) y recarga Nginx
enable_nginx_site "$DOMAIN"

echo ""
echo "✓ Aplicación PHP configurada"
echo "Accede a http://$DOMAIN/index.php para probar"
echo ""
echo "Proximos pasos:"
echo "1. Configurar SSL: ./04-setup-ssl.sh $DOMAIN admin@$DOMAIN"
echo "2. Copiar tu código PHP en: $APP_PATH (reemplazando este index.php de prueba)"
echo "3. Los archivos de la app deben ser de $APP_USER (grupo www-data): sudo chown -R $APP_USER:www-data $APP_PATH"
echo "   La conexion a la base de datos (si hay PostgreSQL) esta en $APP_PATH/.env (DATABASE_URL), legible solo por $APP_USER."
