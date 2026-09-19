#!/bin/bash
set -e

DOMAIN=${1:-"initech.cl"}
APP_PATH="/var/www/landing-page"

echo "========================================"
echo "Nginx Configuration Setup"
echo "Domain: $DOMAIN"
echo "========================================"
echo ""

# Create directories
echo "Creando directorios..."
sudo mkdir -p $APP_PATH
sudo mkdir -p /var/log/nginx/$DOMAIN

# Copy landing page
echo "Copiando landing page..."
sudo cp -r ../landing-page/* $APP_PATH/
sudo chown -R www-data:www-data $APP_PATH
sudo chmod -R 755 $APP_PATH

# Create Nginx configuration
echo "Configurando Nginx..."
sudo tee /etc/nginx/sites-available/$DOMAIN > /dev/null <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    root $APP_PATH;
    index index.html;

    # Logs
    access_log /var/log/nginx/$DOMAIN/access.log;
    error_log /var/log/nginx/$DOMAIN/error.log;

    # Static files
    location / {
        try_files \$uri \$uri/ =404;
    }

    # PHP (si necesitas en el futuro)
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    # Denegar acceso a archivos sensibles
    location ~ /\.ht {
        deny all;
    }
}
EOF

# Enable site
echo "Habilitando sitio..."
sudo ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/$DOMAIN
sudo rm -f /etc/nginx/sites-enabled/default

# Test nginx config
echo "Validando configuración de Nginx..."
sudo nginx -t

# Reload nginx
echo "Recargando Nginx..."
sudo systemctl reload nginx

echo ""
echo "✓ Nginx configurado exitosamente"
echo "Accede a http://$DOMAIN para ver la landing page"
echo ""
echo "Proximos pasos:"
echo "1. Esperar 2-3 minutos para que se propague el DNS"
echo "2. Ejecutar: 03-ssl-setup.sh"
