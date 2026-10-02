#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

print_header "02_E" "Instalacion de Nginx"

sudo apt-get install -y nginx

service_start_enable nginx

# 'Nginx Full' es un perfil de UFW que abre los puertos 80 (HTTP) y 443 (HTTPS)
# a la vez -- sin esto, aunque Nginx funcione, el firewall bloquearia el trafico externo.
ufw_allow "Nginx Full"

echo ""
echo "✓ Nginx instalado"
nginx -v
sudo systemctl status nginx --no-pager | head -5
