#!/bin/bash
set -e

APP_NAME=${1:-"php-app"}
DOMAIN=${2:-"app.initech.cl"}
APP_PATH="/var/www/$APP_NAME"

echo "========================================"
echo "[06_A] Configurando Aplicacion PHP: $APP_NAME"
echo "Dominio: $DOMAIN"
echo "========================================"
echo ""

# Carpeta donde vivira esta app (separada de /var/www/landing-page)
sudo mkdir -p $APP_PATH
sudo chown -R www-data:www-data $APP_PATH   # www-data es el usuario con el que corre Nginx/PHP-FPM

# Pagina de prueba minima para confirmar que PHP-FPM + Nginx estan sirviendo
# correctamente antes de subir el codigo real de la app
echo "Creando página de prueba..."
sudo tee $APP_PATH/index.php > /dev/null <<'EOF'
<?php
phpinfo();
?>
EOF

sudo chown www-data:www-data $APP_PATH/index.php
sudo chmod 644 $APP_PATH/index.php

# Igual que en 03-configure-nginx-site.sh: un server{} nuevo, con su propio
# dominio y sus propios logs, apuntando a esta carpeta en vez de la landing page
echo "Configurando Nginx para aplicación PHP..."
sudo tee /etc/nginx/sites-available/$APP_NAME > /dev/null <<EOFNGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    root $APP_PATH;
    index index.php index.html;

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

    # Bloquea el acceso a archivos ocultos tipo .htaccess/.htpasswd
    location ~ /\.ht {
        deny all;
    }
}
EOFNGINX

# El enlace en sites-enabled es lo que realmente activa el sitio
sudo ln -sf /etc/nginx/sites-available/$APP_NAME /etc/nginx/sites-enabled/$APP_NAME

echo "Validando configuración..."
sudo nginx -t              # valida ANTES de recargar, para no tumbar los sitios que ya funcionan
sudo systemctl reload nginx

echo ""
echo "✓ Aplicación PHP configurada"
echo "Accede a http://$DOMAIN/index.php para probar"
echo ""
echo "Proximos pasos:"
echo "1. Configurar SSL: ./04-setup-ssl.sh $DOMAIN admin@$DOMAIN"
echo "2. Copiar tu código PHP en: $APP_PATH (reemplazando este index.php de prueba)"
echo "3. Ajustar permisos: sudo chown -R www-data:www-data $APP_PATH"
