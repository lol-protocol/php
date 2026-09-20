#!/bin/bash
set -e

echo "========================================"
echo "[06] Instalacion de Nginx"
echo "========================================"
echo ""

sudo apt-get install -y nginx

sudo systemctl start nginx
sudo systemctl enable nginx

# Firewall basico
if command -v ufw &> /dev/null; then
    sudo ufw allow 'Nginx Full' || true
fi

echo ""
echo "✓ Nginx instalado"
nginx -v
sudo systemctl status nginx --no-pager | head -5
