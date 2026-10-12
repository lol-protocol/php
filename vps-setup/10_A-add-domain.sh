#!/bin/bash
# Agrega un dominio nuevo a un VPS ya instalado, encadenando los scripts existentes.
# Uso: ./10_A-add-domain.sh <dominio> [--type landing|php|python|tomcat] [--name APP]
#                           [--context RUTA] [--port N] [--ssl] [--email EMAIL] [--dry-run]
#   --type     tipo de sitio (por defecto landing): 03 | 06_A | 06_B | 06_D
#   --name     nombre de la app (php/python); por defecto el dominio con "-" en vez de "."
#   --context  context path de Tomcat (solo --type tomcat; vacio = raiz)
#   --port     puerto local de gunicorn (solo --type python; por defecto 8000, uno por app)
#   --ssl      pide el certificado con 04-setup-ssl.sh (el DNS ya debe apuntar al VPS)
#   --email    email para Let's Encrypt (por defecto admin@dominio)
#   --dry-run  muestra los comandos sin ejecutarlos
# Termina con 08-healthcheck.sh (sin fallar el alta si el healthcheck avisa de algo).
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"
source lib.sh

TYPE=landing NAME="" CONTEXT="" PORT="" SSL=0 EMAIL="" DRY_RUN=0 DOMAIN=""
while [ $# -gt 0 ]; do
    case "$1" in
        --type|--name|--context|--port|--email)
            [ $# -ge 2 ] || { echo "Falta el valor de $1"; exit 2; }
            case "$1" in
                --type) TYPE=$2 ;; --name) NAME=$2 ;; --context) CONTEXT=$2 ;; --port) PORT=$2 ;; --email) EMAIL=$2 ;;
            esac
            shift 2 ;;
        --ssl) SSL=1; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
        -*) echo "Opcion desconocida: $1 (usa --help)"; exit 2 ;;
        *) [ -z "$DOMAIN" ] || { echo "Solo se admite un dominio (sobra: $1)"; exit 2; }
           DOMAIN=$1; shift ;;
    esac
done

[ -n "$DOMAIN" ] || { echo "Uso: $0 <dominio> [opciones] (--help)"; exit 2; }
require_valid domain "$DOMAIN" "El dominio"
case "$TYPE" in landing|php|python|tomcat) ;; *) echo "--type debe ser landing, php, python o tomcat (recibido: $TYPE)"; exit 2 ;; esac
[ -n "$NAME" ] || NAME=${DOMAIN//./-}
EMAIL=${EMAIL:-"admin@$DOMAIN"}
if [ "$TYPE" = php ] || [ "$TYPE" = python ]; then require_valid name "$NAME" "--name"; fi
[ "$TYPE" != tomcat ] || require_valid context_path "$CONTEXT" "--context"
if [ -n "$PORT" ]; then
    [ "$TYPE" = python ] || { echo "--port solo aplica a --type python"; exit 2; }
    require_valid port "$PORT" "--port"
fi
require_valid email "$EMAIL" "--email"

case "$TYPE" in
    landing) SITE_CMD=(./03-configure-nginx-site.sh "$DOMAIN") ;;
    php)     SITE_CMD=(./06_A-setup-php-app.sh "$NAME" "$DOMAIN") ;;
    python)  SITE_CMD=(./06_B-setup-python-app.sh "$NAME" "$DOMAIN" ${PORT:+"$PORT"}) ;;
    tomcat)  SITE_CMD=(./06_D-setup-tomcat-app.sh "$DOMAIN" "$CONTEXT") ;;
esac

run() {
    echo "+ $*"
    [ "$DRY_RUN" -eq 1 ] || "$@"
}

print_header "10_A" "Agregar dominio $DOMAIN (tipo: $TYPE)"
[ "$DRY_RUN" -eq 0 ] || echo "[dry-run] no se ejecuta nada"

# El orden importa: primero el vhost HTTP (certbot lo necesita para validar y editar),
# luego el certificado, y los headers de seguridad (07_B) ya son globales en conf.d.
run "${SITE_CMD[@]}"
if [ "$SSL" -eq 1 ]; then
    run ./04-setup-ssl.sh "$DOMAIN" "$EMAIL"
else
    echo "(sin --ssl: cuando el DNS apunte al VPS corre ./04-setup-ssl.sh $DOMAIN $EMAIL)"
fi

echo ""
if [ "$DRY_RUN" -eq 0 ]; then
    ./08-healthcheck.sh "$DOMAIN" || echo "AVISO: el healthcheck reporto problemas (revisa arriba); el dominio quedo configurado."
fi
# El vhost se llama como el dominio; --app hace que 10_B archive tambien la carpeta de la app
# (/var/www/<app>) y su servicio systemd, que no llevan el nombre del dominio.
REMOVE_CMD="./10_B-remove-domain.sh $DOMAIN"
if [ "$TYPE" = php ] || [ "$TYPE" = python ]; then REMOVE_CMD+=" --app $NAME"; fi
echo "✓ Dominio $DOMAIN agregado. Para quitarlo: $REMOVE_CMD"
