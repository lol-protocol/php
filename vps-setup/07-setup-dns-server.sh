#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
VPS_IP=${2:-"158.69.222.245"}
OVH_SECONDARY=${3:-"sdns2.ovh.ca"}

echo "========================================"
echo "Configurando Servidor DNS (BIND9)"
echo "Dominio: $DOMAIN"
echo "IP VPS: $VPS_IP"
echo "Secundario OVH: $OVH_SECONDARY"
echo "========================================"
echo ""

# Resolve OVH secondary DNS IP for AXFR whitelist
echo "Resolviendo IP del servidor secundario de OVH..."
OVH_SECONDARY_IP=$(dig +short $OVH_SECONDARY | tail -1)

if [ -z "$OVH_SECONDARY_IP" ]; then
    echo "ADVERTENCIA: No se pudo resolver $OVH_SECONDARY. Instala 'dnsutils' primero:"
    echo "  sudo apt-get install -y dnsutils"
    exit 1
fi

echo "IP de $OVH_SECONDARY: $OVH_SECONDARY_IP"
echo ""

# Install BIND9
echo "[1/4] Instalando BIND9..."
sudo apt-get update
sudo apt-get install -y bind9 bind9utils dnsutils

# Create zone file
echo "[2/4] Creando zona DNS para $DOMAIN..."
sudo mkdir -p /etc/bind/zones

SERIAL=$(date +%Y%m%d01)

sudo tee /etc/bind/zones/db.$DOMAIN > /dev/null <<EOF
\$TTL    3600
@       IN      SOA     ns1.$DOMAIN. admin.$DOMAIN. (
                        $SERIAL  ; Serial
                        3600            ; Refresh
                        1800            ; Retry
                        604800          ; Expire
                        3600 )          ; Negative Cache TTL
;
@       IN      NS      ns1.$DOMAIN.
@       IN      NS      $OVH_SECONDARY.

ns1     IN      A       $VPS_IP
@       IN      A       $VPS_IP
www     IN      A       $VPS_IP
EOF

# Configure zone in named.conf.local
echo "[3/4] Registrando zona en BIND..."
sudo tee -a /etc/bind/named.conf.local > /dev/null <<EOF

zone "$DOMAIN" {
    type master;
    file "/etc/bind/zones/db.$DOMAIN";
    allow-transfer { $OVH_SECONDARY_IP; };
    also-notify { $OVH_SECONDARY_IP; };
};
EOF

# Validate configuration
echo "[4/4] Validando configuración..."
sudo named-checkconf
sudo named-checkzone $DOMAIN /etc/bind/zones/db.$DOMAIN

# Restart BIND9
sudo systemctl restart bind9
sudo systemctl enable bind9

# Open firewall for DNS (port 53)
sudo ufw allow 53/tcp
sudo ufw allow 53/udp

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
