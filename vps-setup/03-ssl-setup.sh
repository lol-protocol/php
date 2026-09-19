#!/bin/bash
set -e

DOMAIN=${1:-"initech.cl"}
EMAIL=${2:-"admin@$DOMAIN"}

echo "========================================"
echo "SSL/HTTPS Setup with Let's Encrypt"
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "========================================"
echo ""

# Check if Nginx is running
echo "Verificando que Nginx esté corriendo..."
sudo systemctl status nginx > /dev/null || sudo systemctl start nginx

# Wait for DNS propagation
echo "Esperando propagación de DNS (10 segundos)..."
sleep 10

# Obtain SSL certificate
echo "Obteniendo certificado SSL de Let's Encrypt..."
sudo certbot certify --nginx \
    --agree-tos \
    --no-eff-email \
    --email $EMAIL \
    -d $DOMAIN \
    -d www.$DOMAIN

# Enable auto-renewal
echo "Configurando renovación automática..."
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer

# Test renewal
echo "Probando renovación automática..."
sudo certbot renew --dry-run

# Force HTTPS redirect
echo "Configurando redirección a HTTPS..."
sudo tee /etc/nginx/sites-available/$DOMAIN > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name DOMAIN_PLACEHOLDER www.DOMAIN_PLACEHOLDER;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name DOMAIN_PLACEHOLDER www.DOMAIN_PLACEHOLDER;

    root /var/www/landing-page;
    index index.html;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/privkey.pem;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Logs
    access_log /var/log/nginx/DOMAIN_PLACEHOLDER/access.log;
    error_log /var/log/nginx/DOMAIN_PLACEHOLDER/error.log;

    # Static files
    location / {
        try_files $uri $uri/ =404;
    }

    # Denegar acceso a archivos sensibles
    location ~ /\.ht {
        deny all;
    }
}
EOF

# Replace placeholder
sudo sed -i "s/DOMAIN_PLACEHOLDER/$DOMAIN/g" /etc/nginx/sites-available/$DOMAIN

# Test config
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx

echo ""
echo "✓ SSL/HTTPS configurado exitosamente"
echo ""
echo "Detalles del certificado:"
sudo certbot certificates -d $DOMAIN
echo ""
echo "✓ Accede a https://$DOMAIN"
echo "✓ El certificado se renovará automáticamente"
echo ""
echo "Proximos pasos:"
echo "1. Ejecutar: 04-deploy-landing-page.sh"
echo "2. (Opcional) Ejecutar: 05-setup-php-app.sh si necesitas una app PHP"
