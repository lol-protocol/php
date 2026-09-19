#!/bin/bash
set -e

echo "========================================"
echo "Deploy Landing Page"
echo "========================================"
echo ""

# Variables
APP_PATH="/var/www/landing-page"
REPO_PATH=$(pwd)

echo "Copiando archivos de landing page..."
sudo cp -r $REPO_PATH/../landing-page/* $APP_PATH/ 2>/dev/null || {
    echo "Descargando landing page desde repositorio..."
    cd /tmp
    git clone https://github.com/tu-usuario/landing-page.git 2>/dev/null || true
    sudo cp -r landing-page/* $APP_PATH/
}

# Set permissions
sudo chown -R www-data:www-data $APP_PATH
sudo chmod -R 755 $APP_PATH
sudo find $APP_PATH -type f -exec chmod 644 {} \;
sudo find $APP_PATH -type d -exec chmod 755 {} \;

# Reload Nginx
sudo systemctl reload nginx

echo ""
echo "✓ Landing page desplegada exitosamente"
echo ""
echo "Verificación:"
ls -la $APP_PATH
echo ""
echo "Status Nginx:"
sudo systemctl status nginx --no-pager | head -10
