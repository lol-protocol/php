#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
# Subcarpeta POR DOMINIO -- sin el "/$DOMAIN" al final, dos dominios distintos
# (ej. conce.com e initech.fun) terminarian compartiendo la misma carpeta y
# sirviendo el mismo contenido, porque ambos usarian literalmente el mismo path.
APP_PATH="/var/www/landing-page/$DOMAIN"

echo "========================================"
echo "[03] Configuracion de Nginx para $DOMAIN"
echo "========================================"
echo ""

# Carpeta donde vivira el sitio y carpeta de logs propia para ese dominio
# (separar los logs por dominio hace mucho mas facil depurar cuando hay varios sitios)
sudo mkdir -p $APP_PATH
sudo mkdir -p /var/log/nginx/$DOMAIN

# Copiamos la landing page del repo (carpeta ../landing-page/) al destino real que servira Nginx
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
sudo cp -r $SCRIPT_DIR/../landing-page/* $APP_PATH/
sudo chown -R www-data:www-data $APP_PATH   # www-data es el usuario con el que corre Nginx/PHP-FPM
sudo chmod -R 755 $APP_PATH

# 'tee' con heredoc escribe el archivo de configuracion completo de una vez.
# Los '\$' (con backslash) evitan que bash reemplace esas variables de Nginx
# (que se resuelven en tiempo de peticion, no ahora al crear el archivo) --
# a diferencia de $DOMAIN, que si queremos que bash reemplace ahora mismo.
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

    # Por si en el futuro se agregan paginas .php a este mismo sitio
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    # Bloquea el acceso a archivos ocultos tipo .htaccess/.htpasswd
    location ~ /\.ht {
        deny all;
    }
}
EOF

# 'sites-available' guarda la config; el enlace en 'sites-enabled' es lo que
# realmente activa el sitio (Nginx solo lee lo que hay en sites-enabled)
sudo ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/$DOMAIN
sudo rm -f /etc/nginx/sites-enabled/default   # quita la pagina de bienvenida por defecto de Nginx

sudo nginx -t              # valida la sintaxis ANTES de recargar (si falla, no rompe el Nginx que ya esta corriendo)
sudo systemctl reload nginx

echo ""
echo "✓ Nginx configurado para $DOMAIN"
echo "  Prueba ahora: curl http://$DOMAIN"
