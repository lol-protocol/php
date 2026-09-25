#!/bin/bash
set -e

echo "========================================"
echo "[02_B] Instalacion de PHP 8.3"
echo "========================================"
echo ""

# php8.3-fpm: el proceso que realmente ejecuta el codigo PHP (Nginx solo le reenvia
#             las peticiones .php via socket unix, no lo ejecuta el mismo).
# php8.3-cli: permite correr scripts PHP desde la terminal (util para composer, cron, etc).
# php8.3-mysql / php8.3-postgresql: drivers para conectar PHP a cada base de datos
#             (instalamos ambos por si el proyecto usa una u otra).
# php8.3-mbstring / php8.3-xml: requeridos por la mayoria de frameworks (Laravel, Symfony)
#             y por Composer para resolver dependencias.
sudo apt-get install -y \
    php8.3 php8.3-fpm php8.3-cli php8.3-common \
    php8.3-mysql php8.3-postgresql php8.3-gd \
    php8.3-curl php8.3-json php8.3-zip \
    php8.3-mbstring php8.3-xml php8.3-bcmath

sudo systemctl enable php8.3-fpm   # Arranca automaticamente si el VPS se reinicia
sudo systemctl start php8.3-fpm

echo ""
echo "✓ PHP instalado"
php -v
