#!/bin/bash
# Quita un dominio del servidor SIN borrar nada: archiva (mueve) su vhost, su sitio y sus logs.
# Uso: ./10_B-remove-domain.sh <dominio> [--app NOMBRE] [--yes] [--dry-run]
#   --app      tambien archiva /var/www/NOMBRE y la unidad systemd NOMBRE (apps php/python)
#   --yes      no pide confirmacion
#   --dry-run  muestra que se archivaria sin tocar nada
# Todo va a $REMOVED_DIR/<dominio>-<fecha>/ (por defecto /var/backups/vps-setup/removed).
# NO toca el certificado de Let's Encrypt ni la zona DNS: al final se indican los comandos.
# Rutas reemplazables para pruebas: NGINX_DIR, WWW_DIR, NGINX_LOG_DIR, SYSTEMD_DIR, REMOVED_DIR.
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib.sh"

NGINX_DIR=${NGINX_DIR:-/etc/nginx}
WWW_DIR=${WWW_DIR:-/var/www}
NGINX_LOG_DIR=${NGINX_LOG_DIR:-/var/log/nginx}
SYSTEMD_DIR=${SYSTEMD_DIR:-/etc/systemd/system}
REMOVED_DIR=${REMOVED_DIR:-/var/backups/vps-setup/removed}

DOMAIN="" APP="" YES=0 DRY_RUN=0
while [ $# -gt 0 ]; do
    case "$1" in
        --app) [ $# -ge 2 ] || { echo "Falta el valor de --app"; exit 2; }; APP=$2; shift 2 ;;
        --yes|-y) YES=1; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        -h|--help) sed -n '2,9p' "$0"; exit 0 ;;
        -*) echo "Opcion desconocida: $1 (usa --help)"; exit 2 ;;
        *) [ -z "$DOMAIN" ] || { echo "Solo se admite un dominio (sobra: $1)"; exit 2; }
           DOMAIN=$1; shift ;;
    esac
done
[ -n "$DOMAIN" ] || { echo "Uso: $0 <dominio> [--app NOMBRE] [--yes] [--dry-run]"; exit 2; }
require_valid domain "$DOMAIN" "El dominio"
[ -z "$APP" ] || require_valid name "$APP" "--app"

DEST="$REMOVED_DIR/$DOMAIN-$(date +%Y%m%d-%H%M%S)"
VHOST="$NGINX_DIR/sites-available/$DOMAIN"
LINK="$NGINX_DIR/sites-enabled/$DOMAIN"
# 06_A y 06_B nombran el vhost como la APP (no como el dominio): con --app hay que
# desactivar tambien ese, o el sitio seguiria publicado.
APP_LINK=""; [ -z "$APP" ] || APP_LINK="$NGINX_DIR/sites-enabled/$APP"

# Lista de lo que existe y se archivara: "origen|nombre dentro de DEST"
ITEMS=()
add_item() { if [ -e "$1" ] || [ -L "$1" ]; then ITEMS+=("$1|$2"); fi; }
add_item "$VHOST" "sites-available/$DOMAIN"
add_item "$WWW_DIR/landing-page/$DOMAIN" "www/landing-page/$DOMAIN"
add_item "$NGINX_LOG_DIR/$DOMAIN" "logs/$DOMAIN"
if [ -n "$APP" ]; then
    add_item "$NGINX_DIR/sites-available/$APP" "sites-available/$APP"
    add_item "$WWW_DIR/$APP" "www/$APP"
    add_item "$SYSTEMD_DIR/$APP.service" "systemd/$APP.service"
fi

print_header "10_B" "Quitar dominio $DOMAIN (archivar, no borrar)"
if [ ${#ITEMS[@]} -eq 0 ] && [ ! -L "$LINK" ] && [ ! -L "$APP_LINK" ]; then
    echo "No hay nada de $DOMAIN en este servidor."
    exit 0
fi
echo "Se archivara en: $DEST"
[ ! -L "$LINK" ] || echo "  - desactivar $LINK"
[ -z "$APP_LINK" ] || [ ! -L "$APP_LINK" ] || echo "  - desactivar $APP_LINK"
for it in "${ITEMS[@]}"; do echo "  - ${it%%|*}"; done
[ -z "$APP" ] || echo "  - detener y deshabilitar el servicio $APP (si existe)"

if [ "$DRY_RUN" -eq 1 ]; then echo "[dry-run] no se toco nada"; exit 0; fi
if [ "$YES" -ne 1 ]; then
    read -r -p "¿Continuar? [s/N] " ans
    [[ "$ans" =~ ^[sSyY]$ ]] || { echo "Cancelado."; exit 1; }
fi

# 1) Desactivar el sitio y comprobar que nginx sigue sano ANTES de mover nada.
declare -A SAVED_LINKS=()
for l in "$LINK" "$APP_LINK"; do
    if [ -n "$l" ] && [ -L "$l" ]; then
        SAVED_LINKS["$l"]=$(readlink "$l")
        sudo rm -f "$l"
    fi
done
restore_links() { for l in "${!SAVED_LINKS[@]}"; do sudo ln -s "${SAVED_LINKS[$l]}" "$l"; done; }
if ! sudo nginx -t; then
    echo "ERROR: nginx -t falla sin $DOMAIN; se restauran los sitios y no se archiva nada."
    restore_links
    exit 1
fi
# nginx -t pasa aunque un sitio que sigue activo use algo que se va a archivar, porque las rutas
# todavia existen; despues de moverlas nginx ya no arrancaria (en el proximo reinicio caen TODOS
# los sitios). Caso tipico: una app php/python quitada sin --app, cuyo vhost se llama como la app
# pero escribe sus logs en /var/log/nginx/<dominio>/.
for it in "${ITEMS[@]}"; do
    src=${it%%|*}
    users=$(sudo grep -RlF -e "$src/" -e "$src;" "$NGINX_DIR/sites-enabled/" 2>/dev/null || true)
    if [ -n "$users" ]; then
        echo "ERROR: $src lo sigue usando un sitio activo:"
        echo "$users" | sed 's/^/  - /'
        echo "Si es una app php/python, repite con --app <nombre de la app>. No se archivo nada."
        restore_links
        exit 1
    fi
done

# 2) Servicio de la app (si hay)
if [ -n "$APP" ]; then
    sudo systemctl disable --now "$APP" 2>/dev/null || true
fi

# 3) Archivar
for it in "${ITEMS[@]}"; do
    src=${it%%|*}; rel=${it##*|}
    sudo mkdir -p "$DEST/$(dirname "$rel")"
    sudo mv "$src" "$DEST/$rel"
done
[ -z "$APP" ] || sudo systemctl daemon-reload
sudo systemctl reload nginx

echo ""
echo "✓ $DOMAIN archivado en $DEST (para restaurarlo, mueve las carpetas de vuelta y reactiva el enlace en sites-enabled)."
echo "Quedan a mano (no se tocaron):"
echo "  - Certificado:  sudo certbot delete --cert-name $DOMAIN"
echo "  - Zona DNS:     si usas BIND (06_C), quita la zona de /etc/bind/named.conf.local; si no, borra los registros en tu registrador"
