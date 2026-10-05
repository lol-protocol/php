#!/bin/bash
# vps-monitor: chequeo periodico del servidor + alertas. Pensado para correr por cron.
# Uso: vps-monitor [--dry-run] [--test]
#   --dry-run  imprime los problemas encontrados; no envia nada ni toca el estado
#   --test     envia una alerta de prueba por los canales configurados
#
# Configuracion en /etc/vps-monitor.conf (VPS_MONITOR_CONF la reemplaza). Variables:
#   DISK_WARN=85          % de uso de disco por punto de montaje (MOUNTS="/")
#   MEM_WARN=90           % de RAM usada (sin contar cache)
#   LOAD_FACTOR=2         alerta si la carga de 1 min > nucleos x LOAD_FACTOR
#   CERT_WARN_DAYS=14     alerta si un certificado vence en menos de N dias
#   SERVICES="nginx php8.3-fpm postgresql"   (solo se revisan los que estan instalados)
#   ALERT_EMAIL=          requiere el comando "mail" y un MTA/relay configurado
#   WEBHOOK_URL=          Slack/Mattermost/Discord (JSON con "text" y "content")
#   REMIND_SECS=86400     repetir una alerta no resuelta cada N segundos
#
# Solo avisa cuando CAMBIA el conjunto de problemas (y al recuperarse), mas un
# recordatorio diario: asi no llena el correo cada 15 minutos.

CONF=${VPS_MONITOR_CONF:-/etc/vps-monitor.conf}
DISK_WARN=85; MEM_WARN=90; LOAD_FACTOR=2; CERT_WARN_DAYS=14
SERVICES="nginx php8.3-fpm postgresql"; MOUNTS="/"
ALERT_EMAIL=""; WEBHOOK_URL=""; REMIND_SECS=86400
CERT_DIR=${CERT_DIR:-/etc/letsencrypt/live}
LOADAVG_FILE=${LOADAVG_FILE:-/proc/loadavg}
STATE_FILE=${VPS_MONITOR_STATE:-/var/lib/vps-monitor/state}
# shellcheck disable=SC1090
[ -f "$CONF" ] && source "$CONF"

DRY_RUN=0; TEST=0
for a in "$@"; do
    case "$a" in
        --dry-run) DRY_RUN=1 ;;
        --test) TEST=1 ;;
        *) echo "Uso: $0 [--dry-run] [--test]"; exit 2 ;;
    esac
done

HOST=$(hostname)
PROBLEMS=()
KEYS=()
# problem <clave estable> <texto>: la huella de cambio usa SOLO las claves, no los
# numeros del texto (carga, % de disco, dias), que varian en cada corrida y
# provocarian un aviso cada 15 minutos mientras el problema siga.
problem() { KEYS+=("$1"); PROBLEMS+=("$2"); }

check_disk() {
    local m pct
    for m in $MOUNTS; do
        pct=$(df -P "$m" 2>/dev/null | awk 'NR==2{gsub("%","",$5); print $5}')
        [ -z "$pct" ] && continue
        [ "$pct" -ge "$DISK_WARN" ] && problem "disk:$m" "Disco $m al ${pct}% (umbral ${DISK_WARN}%)"
    done
}

check_mem() {
    local pct
    pct=$(free | awk '/^Mem:/{printf "%d", (1 - $7/$2) * 100}')
    [ -n "$pct" ] && [ "$pct" -ge "$MEM_WARN" ] && problem "mem" "RAM al ${pct}% (umbral ${MEM_WARN}%)"
}

check_load() {
    local load cores
    load=$(awk '{print $1}' "$LOADAVG_FILE" 2>/dev/null)
    cores=$(nproc)
    [ -z "$load" ] && return
    if awk -v l="$load" -v c="$cores" -v f="$LOAD_FACTOR" 'BEGIN{exit !(l > c*f)}'; then
        problem "load" "Carga alta: $load con $cores nucleo(s) (umbral ${LOAD_FACTOR}x)"
    fi
}

check_certs() {
    local cert end secs days name
    for cert in "$CERT_DIR"/*/cert.pem; do
        [ -f "$cert" ] || continue
        name=$(basename "$(dirname "$cert")")
        end=$(openssl x509 -enddate -noout -in "$cert" 2>/dev/null | cut -d= -f2)
        [ -z "$end" ] && { problem "cert-read:$name" "No se pudo leer el certificado de $name"; continue; }
        # Segundos (no dias enteros): con division entera, un certificado vencido
        # hace menos de 24 h daria 0 dias y se reportaria como "vence en 0 dias".
        secs=$(( $(date -d "$end" +%s) - $(date +%s) ))
        days=$(( secs / 86400 ))
        if [ "$secs" -lt 0 ]; then problem "cert-expired:$name" "Certificado de $name VENCIDO"
        elif [ "$days" -lt "$CERT_WARN_DAYS" ]; then problem "cert-soon:$name" "Certificado de $name vence en $days dia(s)"; fi
    done
}

check_services() {
    local s
    for s in $SERVICES; do
        systemctl list-unit-files "$s.service" 2>/dev/null | grep -q "$s.service" || continue
        systemctl is-active --quiet "$s" || problem "svc:$s" "Servicio $s NO esta activo"
    done
    local failed
    failed=$(systemctl --failed --no-legend 2>/dev/null | awk '{print $1}' | tr '\n' ' ')
    [ -n "${failed// /}" ] && problem "failed-units" "Unidades systemd fallidas: $failed"
}

# Escapa \ y ", pasa tabs a espacio y elimina CR y demas caracteres de control:
# cualquiera de ellos deja el JSON invalido y el webhook rechazaria la alerta.
json_escape() {
    tr '\t' ' ' | tr -d '\000-\010\013-\037' | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' | awk 'BEGIN{ORS="\\n"}1'
}

send_alert() {   # $1=asunto  $2=cuerpo
    logger -t vps-monitor "$1" 2>/dev/null
    if [ -n "$ALERT_EMAIL" ] && command -v mail &> /dev/null; then
        printf '%s\n' "$2" | mail -s "$1" "$ALERT_EMAIL"
    fi
    if [ -n "$WEBHOOK_URL" ]; then
        local msg
        msg=$(printf '%s\n%s' "$1" "$2" | json_escape)
        curl -s -m 15 -X POST -H 'Content-Type: application/json' \
            -d "{\"text\":\"$msg\",\"content\":\"$msg\"}" "$WEBHOOK_URL" > /dev/null || true
    fi
}

if [ "$TEST" -eq 1 ]; then
    send_alert "[$HOST] Alerta de prueba" "Si lees esto, el canal de alertas funciona."
    # Solo "si"/"no": la URL del webhook es un secreto y no se imprime.
    wh=no; [ -n "$WEBHOOK_URL" ] && wh=si
    echo "Alerta de prueba enviada (email: ${ALERT_EMAIL:-no}, webhook: $wh)"
    exit 0
fi

check_disk; check_mem; check_load; check_certs; check_services

if [ "${#PROBLEMS[@]}" -eq 0 ]; then
    echo "$(date '+%F %T') OK: sin problemas"
else
    echo "$(date '+%F %T') PROBLEMAS (${#PROBLEMS[@]}):"
    printf '  - %s\n' "${PROBLEMS[@]}"
fi
[ "$DRY_RUN" -eq 1 ] && exit 0

# Estado: "<huella> <epoch del ultimo aviso>". Si todo estaba bien la huella se
# guarda como "ok" (NO vacia: "read" descartaria el espacio inicial y tomaria la
# fecha como huella, repitiendo el aviso de "Recuperado" en cada corrida).
FP=""
[ "${#PROBLEMS[@]}" -gt 0 ] && FP=$(printf '%s\n' "${KEYS[@]}" | sort | cksum | awk '{print $1}')
LAST_FP=""; LAST_TS=0
[ -f "$STATE_FILE" ] && read -r LAST_FP LAST_TS < "$STATE_FILE"
[ "$LAST_FP" = "ok" ] && LAST_FP=""
LAST_TS=${LAST_TS:-0}
NOW=$(date +%s)

if [ -n "$FP" ] && { [ "$FP" != "$LAST_FP" ] || [ $((NOW - LAST_TS)) -ge "$REMIND_SECS" ]; }; then
    send_alert "[$HOST] ${#PROBLEMS[@]} problema(s) detectado(s)" "$(printf -- '- %s\n' "${PROBLEMS[@]}")"
    NEW="$FP $NOW"
elif [ -z "$FP" ] && [ -n "$LAST_FP" ]; then
    send_alert "[$HOST] Recuperado" "Los problemas anteriores ya no se detectan."
    NEW="ok $NOW"
else
    NEW="${FP:-ok} ${LAST_TS}"
fi
mkdir -p "$(dirname "$STATE_FILE")" 2>/dev/null
echo "$NEW" > "$STATE_FILE" 2>/dev/null || true
exit 0
