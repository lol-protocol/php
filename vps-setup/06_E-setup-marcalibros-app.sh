#!/bin/bash
# Instala la aplicacion Marcalibros (marcalibros-plataforma-pos-brand-identity/app) en este VPS.
# Uso: ./06_E-setup-marcalibros-app.sh <dominio> [--png] [--repo DIR]
#   --png    genera tambien los PNG del catalogo (generator/exportar_png.py; necesita python3,
#            Pillow y numpy). Sin esto la app funciona igual, sin la descarga en PNG.
#   --repo   carpeta del monorepo con el codigo (por defecto, la que contiene a vps-setup/)
# Variables: MAX_UPLOAD (6M), MARCALIBROS_RETENTION_DAYS (dias que se conserva un marcalibro; el
#            de la app, 30, si no se da), MARCALIBROS_PNG=1 (igual que --png).
# Re-correrlo actualiza el codigo y conserva los marcalibros creados y los PNG ya generados.
set -e

MAX_UPLOAD=${MAX_UPLOAD:-6M}   # la app acepta fotos de hasta 5 MB; Nginx necesita un poco mas
source "$(dirname "$0")/lib.sh"

WWW_DIR=${WWW_DIR:-/var/www}
DATA_DIR=${DATA_DIR:-/var/lib}
CRON_DIR=${CRON_DIR:-/etc/cron.d}

DOMAIN="" PNG=${MARCALIBROS_PNG:-0} REPO=""
while [ $# -gt 0 ]; do
    case "$1" in
        --png) PNG=1; shift ;;
        --repo) [ $# -ge 2 ] || { echo "Falta el valor de $1"; exit 2; }; REPO=$2; shift 2 ;;
        -h|--help) sed -n '2,10p' "$0"; exit 0 ;;
        -*) echo "Opcion desconocida: $1 (usa --help)"; exit 2 ;;
        *) [ -z "$DOMAIN" ] || { echo "Solo se admite un dominio (sobra: $1)"; exit 2; }
           DOMAIN=$1; shift ;;
    esac
done
require_arg "$DOMAIN" "$0 <dominio> [--png] [--repo DIR]   (ej. $0 marcalibros.ejemplo.com)"
require_valid domain "$DOMAIN" "El dominio"
require_valid size "$MAX_UPLOAD" "MAX_UPLOAD"
RETENTION=${MARCALIBROS_RETENTION_DAYS:-}
if [ -n "$RETENTION" ] && ! [[ "$RETENTION" =~ ^[1-9][0-9]{0,3}$ ]]; then
    echo "MARCALIBROS_RETENTION_DAYS debe ser un numero de dias (1 a 9999); recibido: $RETENTION"
    exit 2
fi

REPO=${REPO:-$(cd "$(dirname "$0")/.." && pwd)}
PROJECT=marcalibros-plataforma-pos-brand-identity
for need in "$PROJECT/app/public/index.php" "$PROJECT/php/src" "$PROJECT/catalog/manifest.json" \
            "$PROJECT/images" "$PROJECT/svg" defamatory-content-review/src defamatory-content-review/config; do
    if [ ! -e "$REPO/$need" ]; then
        echo "ERROR: en $REPO falta $need (¿es la carpeta del monorepo? usa --repo)." >&2
        exit 1
    fi
done

APP_PATH="$WWW_DIR/marcalibros"
STORAGE="$DATA_DIR/marcalibros"
PUBLIC_DIR="$APP_PATH/$PROJECT/app/public"
MARK="06_E-marcalibros marcalibros"

print_header "06_E" "Instalando Marcalibros (Dominio: $DOMAIN)"

check_dependency nginx "./02_E-install-nginx.sh"
check_dependency php "./02_B-install-php.sh"
for ext in gd mbstring fileinfo exif zlib; do
    php -m | grep -qix "$ext" || { echo "ERROR: a PHP le falta la extension $ext (corre ./02_B-install-php.sh)." >&2; exit 1; }
done
php -r 'exit(function_exists("imagewebp") ? 0 : 1);' || { echo "ERROR: la extension gd de PHP no tiene soporte WebP." >&2; exit 1; }
if [ "$PNG" = 1 ]; then
    python3 -c 'import PIL, numpy' 2>/dev/null || { echo "ERROR: --png necesita python3 con Pillow y numpy (apt install python3-pil python3-numpy)." >&2; exit 1; }
fi

# La app vive en una sola carpeta: un segundo dominio con Marcalibros se pisaria con el primero.
for other in "$NGINX_DIR"/sites-available/*; do
    [ -f "$other" ] || continue
    if [ "$(basename "$other")" != "$DOMAIN" ] && grep -qxF "# vps-setup: $MARK" "$other"; then
        echo "ERROR: Marcalibros ya esta instalado para $(basename "$other"). Quitalo primero: ./10_B-remove-domain.sh $(basename "$other")" >&2
        exit 1
    fi
done
claim_nginx_vhost "$DOMAIN" "$MARK"

# --- Codigo: se arma aparte y se cambia de golpe, para que nunca se sirva a medio copiar ---
# Se conserva la disposicion del monorepo porque app/bootstrap.php busca a sus vecinos con rutas
# relativas (../php/src, ../../defamatory-content-review/src).
echo "Copiando el codigo a $APP_PATH..."
STAGE="$APP_PATH.nuevo"
sudo rm -rf "$STAGE"
sudo mkdir -p "$STAGE"
tar -C "$REPO" -cf - \
    --exclude="$PROJECT/app/vendor" --exclude="$PROJECT/app/tests" --exclude="$PROJECT/app/e2e" \
    --exclude="$PROJECT/app/storage" --exclude="$PROJECT/app/.phpunit.cache" --exclude="$PROJECT/catalog/png" \
    "$PROJECT/app" "$PROJECT/php/src" "$PROJECT/catalog" "$PROJECT/images" "$PROJECT/svg" \
    defamatory-content-review/src defamatory-content-review/config \
    | sudo tar -C "$STAGE" --no-same-owner -xf -
sudo find "$STAGE" -type d -exec chmod 755 {} \;
sudo find "$STAGE" -type f -exec chmod 644 {} \;
# El codigo es de root: si la app se ve comprometida, no puede reescribirse a si misma.
sudo chown -R root:root "$STAGE"
if [ -d "$APP_PATH/$PROJECT/catalog/png" ]; then
    sudo mv "$APP_PATH/$PROJECT/catalog/png" "$STAGE/$PROJECT/catalog/png"
fi
sudo rm -rf "$APP_PATH.anterior"
[ ! -e "$APP_PATH" ] || sudo mv "$APP_PATH" "$APP_PATH.anterior"
sudo mv "$STAGE" "$APP_PATH"
sudo rm -rf "$APP_PATH.anterior"

if [ "$PNG" = 1 ]; then
    echo "Generando los PNG del catalogo (puede tardar unos minutos)..."
    sudo mkdir -p "$APP_PATH/$PROJECT/catalog/png"
    sudo python3 "$REPO/$PROJECT/generator/exportar_png.py" --faltantes --salida "$APP_PATH/$PROJECT/catalog/png"
    sudo find "$APP_PATH/$PROJECT/catalog/png" -type d -exec chmod 755 {} \;
    sudo find "$APP_PATH/$PROJECT/catalog/png" -type f -exec chmod 644 {} \;
fi

# --- Datos: fuera de la carpeta web, solo para www-data ---
sudo mkdir -p "$STORAGE"
sudo chown www-data:www-data "$STORAGE"
sudo chmod 750 "$STORAGE"
setup_nginx_domain_logs "$DOMAIN"

# --- Nginx: todo lo que no sea /assets/ lo atiende public/index.php ---
echo "Configurando Nginx..."
FASTCGI_RETENTION=""
[ -z "$RETENTION" ] || FASTCGI_RETENTION="fastcgi_param MARCALIBROS_RETENTION_DAYS $RETENTION;"
sudo tee "$NGINX_DIR/sites-available/$DOMAIN" > /dev/null <<EOFNGINX
# vps-setup: $MARK
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    root $PUBLIC_DIR;
    client_max_body_size $MAX_UPLOAD;   # fotos (MAX_UPLOAD, 6M por defecto); la app rechaza las de mas de 5 MB

    access_log /var/log/nginx/$DOMAIN/access.log;
    error_log /var/log/nginx/$DOMAIN/error.log;

    location /assets/ {
        try_files \$uri =404;
        expires 7d;
    }

    location / {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $PUBLIC_DIR/index.php;
        fastcgi_param MARCALIBROS_STORAGE $STORAGE;
        fastcgi_param MARCALIBROS_PUBLIC_URL https://$DOMAIN;
        $FASTCGI_RETENTION
        fastcgi_pass unix:/run/php/${PHP_FPM_SERVICE}.sock;
    }
}
EOFNGINX
raise_php_upload_limit "$MAX_UPLOAD"

# --- Limpieza diaria de los marcalibros vencidos (no hay copias de seguridad, a proposito) ---
CRON_ENV="MARCALIBROS_STORAGE=$STORAGE"
[ -z "$RETENTION" ] || CRON_ENV+=$'\n'"MARCALIBROS_RETENTION_DAYS=$RETENTION"
sudo tee "$CRON_DIR/marcalibros" > /dev/null <<EOFCRON
# vps-setup: $MARK
$CRON_ENV
17 3 * * * www-data /usr/bin/php $APP_PATH/$PROJECT/app/bin/limpiar.php
EOFCRON
sudo chmod 644 "$CRON_DIR/marcalibros"

enable_nginx_site "$DOMAIN"

echo ""
echo "✓ Marcalibros instalado"
echo "Accede a http://$DOMAIN/ para probar"
echo ""
echo "Proximos pasos:"
echo "1. Configurar SSL: ./04-setup-ssl.sh $DOMAIN admin@$DOMAIN   (las etiquetas Open Graph apuntan a https://$DOMAIN)"
[ "$PNG" = 1 ] || echo "2. (Opcional) PNG del catalogo: vuelve a correr este script con --png"
echo "3. Comprobar: ./08-healthcheck.sh $DOMAIN"
echo "Quitarlo: ./10_B-remove-domain.sh $DOMAIN   (los marcalibros creados quedan en $STORAGE)"
