#!/bin/bash
# Instalacion completa del VPS.
# Uso: ./install-all.sh <dominio> [email] [--dry-run] [--resume] [--yes]
#   --dry-run  muestra el plan (y que pasos ya estan hechos) sin ejecutar nada
#   --resume   salta los pasos ya completados para este dominio (usalo tras un fallo)
#   --yes      no pide confirmacion
# El avance se guarda en ~/.local/state/vps-setup/install-all.state
# (INSTALL_STATE_FILE lo reemplaza).
set -e

DRY_RUN=0
RESUME=0
ASSUME_YES=0
POSITIONAL=()
for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=1 ;;
        --resume) RESUME=1 ;;
        --yes|-y) ASSUME_YES=1 ;;
        -h|--help) sed -n '2,8p' "$0"; exit 0 ;;
        --*) echo "Opcion desconocida: $arg (usa --help)"; exit 2 ;;
        *) POSITIONAL+=("$arg") ;;
    esac
done

DOMAIN=${POSITIONAL[0]:-}
EMAIL=${POSITIONAL[1]:-"admin@$DOMAIN"}
STATE_FILE=${INSTALL_STATE_FILE:-"${XDG_STATE_HOME:-$HOME/.local/state}/vps-setup/install-all.state"}

# Source shared functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"
source lib.sh

require_arg "$DOMAIN" "$0 <dominio> [email] [--dry-run] [--resume] [--yes]"
require_valid domain "$DOMAIN" "El dominio (argumento 1)"
require_valid email "$EMAIL" "El email (argumento 2)"

step_done() { grep -qxF "$DOMAIN"$'\t'"$1" "$STATE_FILE" 2>/dev/null; }
mark_done() {
    mkdir -p "$(dirname "$STATE_FILE")"
    printf '%s\t%s\n' "$DOMAIN" "$1" >> "$STATE_FILE"
}

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║   VPS Setup Completo - Ubuntu 24 LTS                      ║"
echo "║   Dominio: $DOMAIN"
echo "║   Email: $EMAIL"
[ "$DRY_RUN" -eq 1 ] && echo "║   MODO DRY-RUN: no se ejecuta nada"
[ "$RESUME" -eq 1 ] && echo "║   MODO RESUME: se omiten los pasos ya completados"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Only prompt if stdin is a TTY (interactive mode)
if [ "$DRY_RUN" -eq 0 ] && [ "$ASSUME_YES" -eq 0 ]; then
    if [ -t 0 ]; then
        read -p "¿Continuar? (s/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Ss]$ ]]; then
            exit 1
        fi
    else
        # Non-interactive: assume yes
        echo "Modo no-interactivo detectado. Continuando sin confirmacion..."
    fi
fi

if [ "$DRY_RUN" -eq 0 ]; then
    # Todo lo que sigue tambien queda en un log: si la sesion SSH se corta o el error se
    # pierde entre cientos de lineas de apt, se puede revisar despues.
    LOG_FILE="$(dirname "$STATE_FILE")/install-all-$(date +%Y%m%d-%H%M%S).log"
    mkdir -p "$(dirname "$LOG_FILE")"
    exec > >(tee -a "$LOG_FILE") 2>&1
    echo "Log de esta corrida: $LOG_FILE"
    chmod +x ./*.sh
    # Una corrida nueva (sin --resume) empieza de cero para este dominio.
    if [ "$RESUME" -eq 0 ] && [ -f "$STATE_FILE" ]; then
        # Coincidencia EXACTA del primer campo: con grep por subcadena, borrar el
        # estado de initech.fun tambien borraria el de www.initech.fun.
        awk -F'\t' -v d="$DOMAIN" '$1 != d' "$STATE_FILE" > "$STATE_FILE.tmp" || true
        mv "$STATE_FILE.tmp" "$STATE_FILE"
    fi
fi

# Pasos con letra (02_A..02_J) son independientes entre si: el orden
# dentro del mismo numero no importa, solo que terminen antes del
# siguiente numero. Instala el stack COMPLETO de lenguajes/servicios
# soportados (Java, PHP, Python, PostgreSQL, Nginx, Certbot, Webmin), no
# solo lo que usa la landing page estatica -- si solo necesitas la landing
# page por HTTPS, corre a mano 01, 02_E, 02_F, 03, 04 y 05. Los extras
# realmente opcionales (MariaDB, Tomcat, Whisper, apps adicionales, DNS
# propio, endurecimiento de SSH 07_C, monitoreo 09_A) se corren aparte
# cuando se necesiten (ver el mensaje final de este script).
STEPS=(
    "01-system-update.sh"
    "02_A-install-java.sh"
    "02_B-install-php.sh"
    "02_C-install-python.sh"
    "02_D-install-postgresql.sh"
    "02_E-install-nginx.sh"
    "02_F-install-certbot.sh"
    "02_J-install-webmin.sh"
    "03-configure-nginx-site.sh:$DOMAIN"
    "04-setup-ssl.sh:$DOMAIN $EMAIL"
    "05-deploy-landing-page.sh:$DOMAIN"
    "07_A-install-fail2ban-autoupdates.sh"
    "07_B-nginx-security-headers.sh"
    "07_D-setup-logrotate.sh"
    "07_E-nginx-performance.sh"
)
TOTAL=${#STEPS[@]}

# Cada entrada de STEPS es "script.sh" o "script.sh:argumentos". El ":" separa
# el nombre del script de los argumentos que necesita (ej. el dominio y el
# email para 03/04). Aqui los separamos para poder llamar cada script con
# sus propios argumentos:
#   step="${entry%%:*}"  -> todo ANTES del primer ":"  (el nombre del script)
#   args="${entry#*:}"   -> todo DESPUES del primer ":" (sus argumentos)
# Devuelve distinto de 0 si el paso falla (tras imprimir como retomar).
run_step() {
    local i=$1
    local entry=$2
    local step="${entry%%:*}"
    local args=""
    if [[ "$entry" == *:* ]]; then
        args="${entry#*:}"
    fi

    # Contador y nombre se combinan en un solo campo de ancho fijo (58): con el
    # numero y el nombre en printfs separados, "[10/11]" desalinea el recuadro.
    local label="[$((i+1))/$TOTAL] $step"
    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    printf "║ %-58s ║\n" "$label"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""

    if [ "$RESUME" -eq 1 ] && step_done "$step"; then
        echo "Ya completado en una corrida anterior: se omite."
        return 0
    fi
    if [ "$DRY_RUN" -eq 1 ]; then
        echo "[dry-run] bash ./$step $args"
        return 0
    fi
    # shellcheck disable=SC2086
    if bash "./$step" $args; then
        mark_done "$step"
    else
        echo ""
        echo "ERROR: fallo $step. Corrige el problema y retoma desde aqui con:"
        echo "    ./install-all.sh $DOMAIN $EMAIL --resume"
        echo "Log completo: $LOG_FILE"
        return 1
    fi
}

# 01 corre SIEMPRE primero y por separado del resto del loop, porque instala
# curl entre otras cosas -- y get_public_ip() (usada justo despues) depende
# de curl. Si el fetch de la IP corriera antes de este paso en un VPS recien
# creado sin curl preinstalado, cachearia el placeholder "TU_IP_PUBLICA" y
# ese valor malo quedaria exportado (y por lo tanto "atascado") para el
# resto de esta ejecucion, incluido el mensaje final de Webmin.
run_step 0 "${STEPS[0]}" || exit 1

if [ "$DRY_RUN" -eq 0 ]; then
    # Obtener y exportar la IP publica UNA sola vez, ahora que 01 ya garantizo
    # curl instalado. get_public_ip() exporta CACHED_PUBLIC_IP, y una variable
    # exportada SI la heredan los procesos "bash ./script.sh" del resto del
    # loop (a diferencia de una variable sin exportar). Asi
    # 02_J-install-webmin.sh reusa esta misma IP en vez de volver a consultar
    # ifconfig.me.
    #
    # OJO: se llama SIN "$(...)": una sustitucion de comandos corre en un subshell
    # y el "export" de get_public_ip se perderia ahi, asi que los pasos hijos no
    # heredarian nada y cada uno volveria a consultar ifconfig.me.
    get_public_ip > /dev/null
    IP=${CACHED_PUBLIC_IP:-TU_IP_PUBLICA}
fi

for i in "${!STEPS[@]}"; do
    [ "$i" -eq 0 ] && continue   # 01 ya corrio arriba
    run_step "$i" "${STEPS[$i]}" || exit 1
done

if [ "$DRY_RUN" -eq 1 ]; then
    echo ""
    echo "Dry-run completo: no se ejecuto nada. Quita --dry-run para instalar."
    exit 0
fi

# El healthcheck NO esta en STEPS: sale con error si algo falla, y con "set -e"
# eso abortaria antes del resumen final. Aqui un fallo solo se avisa (por
# ejemplo, el DNS puede seguir propagando aunque la instalacion este bien).
bash ./08-healthcheck.sh "$DOMAIN" || echo "AVISO: el healthcheck reporto fallas (ver arriba); la instalacion termino pero revisalas."

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  ✓ SETUP COMPLETADO EXITOSAMENTE                          ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "Tu landing page esta disponible en:"
echo "  🔗 https://$DOMAIN"
echo "  🔗 https://www.$DOMAIN"
echo ""
sudo certbot certificates -d "$DOMAIN" 2>/dev/null || echo "Verificando certificado..."
echo ""
echo "Logs de Nginx:"
echo "  Access: /var/log/nginx/$DOMAIN/access.log"
echo "  Error:  /var/log/nginx/$DOMAIN/error.log"
echo ""
echo "Panel de administracion (Webmin):"
echo "  🔗 https://$IP:10000  (usuario/contraseña: los mismos que por SSH)"
echo ""
echo "Log de la instalacion: $LOG_FILE"
echo ""
echo "Pasos opcionales (independientes entre si):"
echo "  - Endurecer SSH (solo llave): bash ./07_C-harden-ssh.sh 'ssh-ed25519 AAAA...'   (lee la advertencia del script antes)"
echo "  - Monitoreo y alertas:        bash ./09_A-setup-monitoring.sh [webhook-url] [email]"
echo "  - MariaDB:            bash ./02_G-install-mariadb.sh"
echo "  - Java + Tomcat:      bash ./02_A-install-java.sh && bash ./02_H-install-tomcat.sh"
echo "  - Whisper (audio):    bash ./02_I-install-python-whisper.sh"
echo "  - Aplicacion PHP:     bash ./06_A-setup-php-app.sh nombre-app dominio.com"
echo "  - Aplicacion Python:  bash ./06_B-setup-python-app.sh nombre-app dominio.com [puerto]"
echo "  - Servidor DNS propio (solo si tu registrador no tiene DNS Management): bash ./06_C-setup-dns-server.sh dominio.com IP"
echo "  - App Java (Tomcat) con su propio dominio: bash ./06_D-setup-tomcat-app.sh dominio.com mi-app"
echo ""
