#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
APP_PATH="/var/www/landing-page"

echo "========================================"
echo "[08] Configuracion de Nginx para $DOMAIN"
echo "========================================"
echo ""

sudo mkdir -p $APP_PATH
sudo mkdir -p /var/log/nginx/$DOMAIN

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
sudo cp -r $SCRIPT_DIR/../landing-page/* $APP_PATH/
sudo chown -R www-data:www-data $APP_PATH
sudo chmod -R 755 $APP_PATH

sudo tee /etc/nginx/sites-available/$DOMAIN > /dev/null <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    root $APP_PATH;
    index index.html;

    access_log /var/log/nginx/$DOMAIN/access.log;
    error_log /var/log/nginx/$DOMAIN/error.log;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/$DOMAIN
sudo rm -f /etc/nginx/sites-enabled/default

sudo nginx -t
sudo systemctl reload nginx

echo ""
echo "✓ Nginx configurado para $DOMAIN"
echo "  Prueba ahora: curl http://$DOMAIN"
