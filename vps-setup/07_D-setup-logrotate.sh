#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

# Variable: JOURNAL_MAX_USE (200M por defecto) = tope del journal de systemd (los logs de los servicios: gunicorn,
# PHP-FPM, fail2ban...). Sin tope, journald usa hasta el 10% del disco y en un VPS chico es mucho.
JOURNAL_MAX_USE=${JOURNAL_MAX_USE:-200M}
JOURNALD_CONF_DIR=${JOURNALD_CONF_DIR:-/etc/systemd/journald.conf.d}   # reemplazable en los tests
require_valid size "$JOURNAL_MAX_USE" "JOURNAL_MAX_USE"

print_header "07_D" "Rotacion de logs: Nginx por dominio y journal de systemd"

# El logrotate que trae el paquete nginx solo cubre /var/log/nginx/*.log. Los logs
# de estos scripts viven en /var/log/nginx/<dominio>/*.log, que NO coincide con ese
# patron: sin esto crecen para siempre hasta llenar el disco.
LOGROTATE_CONF=${LOGROTATE_CONF:-/etc/logrotate.d/nginx-domains}

# Mismo create/postrotate que el paquete nginx de Ubuntu: "invoke-rc.d nginx
# rotate" hace que Nginx reabra los archivos de log tras la rotacion.
sudo tee "$LOGROTATE_CONF" > /dev/null <<'EOLOG'
/var/log/nginx/*/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        invoke-rc.d nginx rotate >/dev/null 2>&1
    endscript
}
EOLOG

# -d = simulacion: valida la sintaxis sin rotar nada.
if command -v logrotate &> /dev/null; then
    sudo logrotate -d "$LOGROTATE_CONF" > /dev/null
    echo "✓ Configuracion validada con logrotate -d"
fi

# Tope del journal de systemd (drop-in propio: no se toca /etc/systemd/journald.conf). El tope solo actua al rotar el
# journal, asi que se reinicia journald y se recorta de una vez lo que ya pase del limite (journalctl --vacuum-size no
# hace nada si el journal ya cabe).
sudo mkdir -p "$JOURNALD_CONF_DIR"
printf '# vps-setup: tope del journal de systemd (lo regenera 07_D)\n[Journal]\nSystemMaxUse=%s\n' "$JOURNAL_MAX_USE" \
    | sudo tee "$JOURNALD_CONF_DIR/vps-setup.conf" > /dev/null
sudo systemctl restart systemd-journald
sudo journalctl --vacuum-size="$JOURNAL_MAX_USE" > /dev/null 2>&1 || true

echo ""
echo "✓ Rotacion configurada: diaria, 14 archivos, comprimidos"
echo "✓ Journal de systemd limitado a $JOURNAL_MAX_USE (JOURNAL_MAX_USE lo cambia; ver con: journalctl --disk-usage)"
echo "Fuerza una rotacion de prueba con: sudo logrotate -f $LOGROTATE_CONF"
