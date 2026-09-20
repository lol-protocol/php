#!/bin/bash
set -e

echo "========================================"
echo "[03] Instalacion de PHP 8.3"
echo "========================================"
echo ""

sudo apt-get install -y \
    php8.3 php8.3-fpm php8.3-cli php8.3-common \
    php8.3-mysql php8.3-postgresql php8.3-gd \
    php8.3-curl php8.3-json php8.3-zip \
    php8.3-mbstring php8.3-xml php8.3-bcmath

sudo systemctl enable php8.3-fpm
sudo systemctl start php8.3-fpm

echo ""
echo "✓ PHP instalado"
php -v
