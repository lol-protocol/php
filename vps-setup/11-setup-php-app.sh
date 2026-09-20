#!/bin/bash
set -e

APP_NAME=${1:-"php-app"}
DOMAIN=${2:-"app.initech.cl"}
APP_PATH="/var/www/$APP_NAME"

echo "========================================"
echo "Configurando Aplicación PHP"
echo "Nombre: $APP_NAME"
echo "Dominio: $DOMAIN"
echo "========================================"
echo ""

# Create app directory
sudo mkdir -p $APP_PATH
sudo chown -R www-data:www-data $APP_PATH

# Create a basic PHP info page for testing
echo "Creando página de prueba..."
sudo tee $APP_PATH/index.php > /dev/null <<'EOF'
<?php
phpinfo();
?>
EOF

sudo chown www-data:www-data $APP_PATH/index.php
sudo chmod 644 $APP_PATH/index.php

# Configure Nginx for PHP app
echo "Configurando Nginx para aplicación PHP..."
sudo tee /etc/nginx/sites-available/$APP_NAME > /dev/null <<EOFNGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    root $APP_PATH;
    index index.php index.html;

    # Logs
    access_log /var/log/nginx/$DOMAIN/access.log;
    error_log /var/log/nginx/$DOMAIN/error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOFNGINX

# Enable site
sudo ln -sf /etc/nginx/sites-available/$APP_NAME /etc/nginx/sites-enabled/$APP_NAME

# Test and reload
echo "Validando configuración..."
sudo nginx -t
sudo systemctl reload nginx

echo ""
echo "✓ Aplicación PHP configurada"
echo "Accede a http://$DOMAIN/index.php para probar"
echo ""
echo "Proximos pasos:"
echo "1. Configurar SSL: certbot certify --nginx -d $DOMAIN"
echo "2. Copiar tu código PHP en: $APP_PATH"
echo "3. Ajustar permisos: sudo chown -R www-data:www-data $APP_PATH"
