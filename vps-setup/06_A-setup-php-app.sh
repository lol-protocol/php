#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

# Uso: ./06_A-setup-php-app.sh <nombre-app> <dominio> [--docroot SUBCARPETA]
#   --docroot  subcarpeta de la app que Nginx sirve como raiz web (ej. public, para Laravel/Symfony). Sin ella, la raiz
#              es la carpeta de la app ("--docroot ." vuelve a ella). Al re-correr sin la opcion se conserva el docroot
#              que ya tenia el vhost.
APP_NAME="" DOMAIN="" DOCROOT=${PHP_DOCROOT:-} DOCROOT_GIVEN=0
[ -z "$DOCROOT" ] || DOCROOT_GIVEN=1
while [ $# -gt 0 ]; do
    case "$1" in
        --docroot) [ $# -ge 2 ] || { echo "Falta el valor de --docroot" >&2; exit 2; }; DOCROOT=$2; DOCROOT_GIVEN=1; shift 2 ;;
        -h|--help) sed -n '2,6p' "$0"; exit 0 ;;
        -*) echo "Opcion desconocida: $1 (usa --help)" >&2; exit 2 ;;
        *) if [ -z "$APP_NAME" ]; then APP_NAME=$1
           elif [ -z "$DOMAIN" ]; then DOMAIN=$1
           else echo "Sobra un argumento: $1" >&2; exit 2; fi
           shift ;;
    esac
done
require_arg "$DOMAIN" "$0 <nombre-app> <dominio> [--docroot public]   (ej. $0 tienda tienda.initech.fun)"
require_valid name "$APP_NAME" "El nombre de la app (argumento 1)"
require_valid domain "$DOMAIN" "El dominio (argumento 2)"
[ "$DOCROOT" != . ] || DOCROOT=""
[ -z "$DOCROOT" ] || require_valid docroot "$DOCROOT" "--docroot"
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

# Sin --docroot, re-correr conserva el que ya tenia el vhost: si no, cambiar solo el limite de subida volveria a
# publicar la carpeta entera de la app (con su .git, su vendor/...).
VHOST_FILE="$NGINX_DIR/sites-available/$DOMAIN"
if [ "$DOCROOT_GIVEN" -eq 0 ] && [ -f "$VHOST_FILE" ]; then
    CUR_ROOT=$(grep -m1 '^[[:space:]]*root ' "$VHOST_FILE" || true)
    CUR_ROOT=${CUR_ROOT##*root }; CUR_ROOT=${CUR_ROOT%;}
    case "$CUR_ROOT" in "$APP_PATH"/?*) DOCROOT=${CUR_ROOT#"$APP_PATH"/}; echo "Se conserva el docroot del vhost: $DOCROOT" ;; esac
fi
WEB_ROOT="$APP_PATH${DOCROOT:+/$DOCROOT}"

# Pagina de prueba minima para confirmar que PHP-FPM + Nginx estan sirviendo
# correctamente antes de subir el codigo real de la app. A proposito NO usa
# phpinfo(): esa funcion expone version de PHP, modulos, rutas del servidor
# y variables de entorno a cualquiera que visite la URL por HTTP plano.
if app_has_code "$APP_PATH"; then
    # Re-correr este script (cambiar el limite de subida, el dominio...) NO debe reemplazar tu codigo.
    echo "$APP_PATH ya tiene archivos: se conserva tu codigo (no se crea la pagina de prueba)."
else
    echo "Creando página de prueba..."
    sudo mkdir -p "$WEB_ROOT"
    sudo chown "$APP_USER:www-data" "$WEB_ROOT"
    sudo chmod 750 "$WEB_ROOT"
    sudo tee "$WEB_ROOT/index.php" > /dev/null <<'EOF'
<?php
echo "PHP-FPM + Nginx funcionando correctamente.";
EOF
    sudo chown "$APP_USER:www-data" "$WEB_ROOT/index.php"
    sudo chmod 644 "$WEB_ROOT/index.php"
fi

# Igual que en 03-configure-nginx-site.sh: un server{} nuevo, con su propio
# dominio y sus propios logs, apuntando a esta carpeta en vez de la landing page
echo "Configurando Nginx para aplicación PHP..."
# El vhost se llama como el DOMINIO (igual que en 03/06_B/06_D), no como la app.
sudo tee "$NGINX_DIR/sites-available/$DOMAIN" > /dev/null <<EOFNGINX
# vps-setup: 06_A-php $APP_NAME
server {
$(nginx_server_head "$DOMAIN")

    root $WEB_ROOT;
    index index.php index.html;
    client_max_body_size $MAX_UPLOAD;   # subidas (MAX_UPLOAD, 20M por defecto); sin esto, 1 MB

$(nginx_deny_hidden)
$(nginx_deny_app_internals)

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;   # ya incluye fastcgi.conf (SCRIPT_FILENAME y demas parametros)
        fastcgi_pass unix:/run/php/$APP_USER.sock;
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
echo "2. Copiar tu código PHP en: $APP_PATH (reemplazando este index.php de prueba)${DOCROOT:+; Nginx sirve solo $WEB_ROOT}"
echo "3. Los archivos de la app deben ser de $APP_USER (grupo www-data): sudo chown -R $APP_USER:www-data $APP_PATH"
echo "   La conexion a la base de datos (si hay PostgreSQL) esta en $APP_PATH/.env (DATABASE_URL), legible solo por $APP_USER."
