#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

print_header "07_D" "Rotacion de logs de Nginx por dominio"

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

echo ""
echo "✓ Rotacion configurada: diaria, 14 archivos, comprimidos"
echo "Fuerza una rotacion de prueba con: sudo logrotate -f $LOGROTATE_CONF"
