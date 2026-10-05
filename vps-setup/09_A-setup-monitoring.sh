#!/bin/bash
# Instala el monitoreo con alertas (cada 15 min por cron).
# Uso: ./09_A-setup-monitoring.sh [webhook-url] [email]
# Ambos son opcionales: tambien puedes editar /etc/vps-monitor.conf despues.
# El email requiere un MTA/relay SMTP configurado en el servidor (no se instala
# aqui); el webhook (Slack/Discord/Mattermost) funciona sin nada mas.
set -e

source "$(dirname "$0")/lib.sh"

print_header "09_A" "Monitoreo y alertas"

WEBHOOK_URL_ARG=${1:-""}
EMAIL_ARG=${2:-""}

# Rutas reemplazables por variables de entorno (se usan en los tests).
MONITOR_BIN=${MONITOR_BIN:-/usr/local/bin/vps-monitor}
MONITOR_CONF=${MONITOR_CONF:-/etc/vps-monitor.conf}
MONITOR_CRON=${MONITOR_CRON:-/etc/cron.d/vps-monitor}
MONITOR_LOGROTATE=${MONITOR_LOGROTATE:-/etc/logrotate.d/vps-monitor}
SRC="$(dirname "$0")/monitoring/vps-monitor.sh"

sudo install -m 755 "$SRC" "$MONITOR_BIN"

# La config guarda la URL del webhook (equivale a una contrasena): solo root la lee.
# Si ya existe NO se pisa, para no perder lo que hayas ajustado.
if [ -f "$MONITOR_CONF" ]; then
    echo "  Se conserva la configuracion existente: $MONITOR_CONF"
else
    sudo tee "$MONITOR_CONF" > /dev/null <<EOCONF
# Configuracion de vps-monitor (ver el encabezado de $MONITOR_BIN)
DISK_WARN=85
MEM_WARN=90
LOAD_FACTOR=2
CERT_WARN_DAYS=14
ALERT_EMAIL="$EMAIL_ARG"
WEBHOOK_URL="$WEBHOOK_URL_ARG"
EOCONF
    sudo chmod 600 "$MONITOR_CONF"
fi

sudo tee "$MONITOR_CRON" > /dev/null <<EOCRON
*/15 * * * * root $MONITOR_BIN >> /var/log/vps-monitor.log 2>&1
EOCRON
sudo chmod 644 "$MONITOR_CRON"

sudo tee "$MONITOR_LOGROTATE" > /dev/null <<'EOLOG'
/var/log/vps-monitor.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
}
EOLOG

echo ""
echo "✓ Monitoreo instalado (cada 15 min)"
echo "Probar el canal de alertas:  sudo $MONITOR_BIN --test"
echo "Ver el estado ahora:         sudo $MONITOR_BIN --dry-run"
echo "Configuracion:               sudo nano $MONITOR_CONF"
