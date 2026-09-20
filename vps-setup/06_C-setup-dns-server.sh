#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
VPS_IP=${2:-"158.69.222.245"}
OVH_SECONDARY=${3:-"sdns2.ovh.ca"}

echo "========================================"
echo "[06_C] Configurando Servidor DNS (BIND9)"
echo "Dominio: $DOMAIN"
echo "IP VPS: $VPS_IP"
echo "Secundario OVH: $OVH_SECONDARY"
echo "========================================"
echo ""
echo "NOTA: solo necesitas este script si tu registrador de dominio NO te"
echo "deja gestionar registros DNS (A/CNAME/TXT) directamente -- la mayoria"
echo "de registradores si tienen esa opcion; revisa antes de usar esto."
echo ""

# AXFR (transferencia de zona) es el mecanismo con el que OVH copia
# automaticamente nuestra zona DNS como respaldo. Necesitamos la IP real de
# su servidor secundario para autorizarla explicitamente mas abajo
# (allow-transfer) -- sin esto, OVH no podria sincronizar los registros.
echo "Resolviendo IP del servidor secundario de OVH..."
OVH_SECONDARY_IP=$(dig +short $OVH_SECONDARY | tail -1)

if [ -z "$OVH_SECONDARY_IP" ]; then
    echo "ADVERTENCIA: No se pudo resolver $OVH_SECONDARY. Instala 'dnsutils' primero:"
    echo "  sudo apt-get install -y dnsutils"
    exit 1
fi

echo "IP de $OVH_SECONDARY: $OVH_SECONDARY_IP"
echo ""

# bind9: el servidor DNS en si (el mismo que usan la mayoria de registradores
#        por detras). bind9utils trae named-checkconf/named-checkzone, que
#        usamos mas abajo para validar que no haya errores de sintaxis.
echo "[1/4] Instalando BIND9..."
sudo apt-get update
sudo apt-get install -y bind9 bind9utils dnsutils

echo "[2/4] Creando zona DNS para $DOMAIN..."
sudo mkdir -p /etc/bind/zones

# El "Serial" de la zona debe subir cada vez que la editas para que otros
# servidores DNS (como el secundario de OVH) sepan que hay cambios que
# sincronizar. Usar la fecha (YYYYMMDDnn) es la convencion estandar.
SERIAL=$(date +%Y%m%d01)

# \$TTL con backslash: queremos que BIND lea "$TTL" literal (es sintaxis
# propia de los archivos de zona), no que bash intente sustituir una
# variable de shell llamada TTL que no existe.
sudo tee /etc/bind/zones/db.$DOMAIN > /dev/null <<EOF
\$TTL    3600
@       IN      SOA     ns1.$DOMAIN. admin.$DOMAIN. (
                        $SERIAL  ; Serial   (sube en cada cambio de esta zona)
                        3600            ; Refresh (cada cuanto el secundario revisa cambios)
                        1800            ; Retry   (si el refresh falla, cuanto esperar para reintentar)
                        604800          ; Expire  (tras cuanto el secundario deja de responder si no logra sincronizar)
                        3600 )          ; Negative Cache TTL
;
@       IN      NS      ns1.$DOMAIN.
@       IN      NS      $OVH_SECONDARY.

ns1     IN      A       $VPS_IP
@       IN      A       $VPS_IP
www     IN      A       $VPS_IP
EOF

# Registra la zona en la configuracion principal de BIND. 'type master' indica
# que ESTE servidor es la fuente de verdad; allow-transfer/also-notify le dan
# permiso explicito a OVH (y le avisan) para copiar la zona como respaldo.
#
# 'tee -a' agrega al final del archivo -- si este script ya se corrio antes
# para el mismo dominio, agregar el bloque de nuevo dejaria DOS zonas con el
# mismo nombre y "named-checkconf" fallaria por zona duplicada. Por eso
# primero revisamos si la zona ya esta registrada.
echo "[3/4] Registrando zona en BIND..."
if sudo grep -q "zone \"$DOMAIN\"" /etc/bind/named.conf.local 2>/dev/null; then
    echo "  La zona '$DOMAIN' ya estaba registrada en named.conf.local, no se duplica."
else
    sudo tee -a /etc/bind/named.conf.local > /dev/null <<EOF

zone "$DOMAIN" {
    type master;
    file "/etc/bind/zones/db.$DOMAIN";
    allow-transfer { $OVH_SECONDARY_IP; };
    also-notify { $OVH_SECONDARY_IP; };
};
EOF
fi

# Valida la sintaxis ANTES de reiniciar -- un error aqui tumbaria la
# resolucion DNS de TODOS los dominios que dependan de este servidor
echo "[4/4] Validando configuración..."
sudo named-checkconf
sudo named-checkzone $DOMAIN /etc/bind/zones/db.$DOMAIN

sudo systemctl restart bind9
sudo systemctl enable bind9   # arranca automaticamente si el VPS se reinicia

# El puerto 53 (DNS) usa tanto TCP como UDP -- UDP para consultas normales,
# TCP para respuestas grandes y para las transferencias de zona (AXFR).
# El "|| true" evita que el script aborte si UFW no esta instalado/activo
# (BIND9 ya quedo funcionando arriba; esto es solo abrir el firewall).
if command -v ufw &> /dev/null; then
    sudo ufw allow 53/tcp || true
    sudo ufw allow 53/udp || true
fi

echo ""
echo "✓ Servidor DNS configurado exitosamente"
echo ""
echo "Verificación local:"
dig @127.0.0.1 $DOMAIN
echo ""
echo "Proximos pasos:"
echo "1. En Network Solutions, crea un 'Custom Nameserver':"
echo "   Hostname: ns1.$DOMAIN"
echo "   IP: $VPS_IP"
echo ""
echo "2. Configura los nameservers del dominio como:"
echo "   - ns1.$DOMAIN"
echo "   - $OVH_SECONDARY"
echo ""
echo "3. Espera propagación (15-60 min) y verifica con:"
echo "   dig $DOMAIN"
echo "   dig NS $DOMAIN"
