#!/bin/bash
set -e

echo "========================================"
echo "[10] Deploy de Landing Page"
echo "========================================"
echo ""

APP_PATH="/var/www/landing-page"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

sudo cp -r $SCRIPT_DIR/../landing-page/* $APP_PATH/

sudo chown -R www-data:www-data $APP_PATH
sudo find $APP_PATH -type f -exec chmod 644 {} \;
sudo find $APP_PATH -type d -exec chmod 755 {} \;

sudo systemctl reload nginx

echo ""
echo "✓ Landing page desplegada en $APP_PATH"
ls -la $APP_PATH
