#!/bin/bash
set -e

echo "========================================"
echo "[07] Instalacion de Certbot (SSL)"
echo "========================================"
echo ""

sudo apt-get install -y certbot python3-certbot-nginx

echo ""
echo "✓ Certbot instalado"
certbot --version
