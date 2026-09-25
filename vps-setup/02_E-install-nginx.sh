#!/bin/bash
set -e

echo "========================================"
echo "[02_E] Instalacion de Nginx"
echo "========================================"
echo ""

sudo apt-get install -y nginx

sudo systemctl start nginx
sudo systemctl enable nginx   # Arranca automaticamente si el VPS se reinicia

# 'Nginx Full' es un perfil de UFW que abre los puertos 80 (HTTP) y 443 (HTTPS)
# a la vez -- sin esto, aunque Nginx funcione, el firewall bloquearia el trafico externo.
# El "|| true" evita que el script aborte si UFW no esta instalado o ya tiene la regla.
if command -v ufw &> /dev/null; then
    sudo ufw allow 'Nginx Full' || true
fi

echo ""
echo "✓ Nginx instalado"
nginx -v
sudo systemctl status nginx --no-pager | head -5
