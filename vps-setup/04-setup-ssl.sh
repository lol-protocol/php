#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
EMAIL=${2:-"admin@$DOMAIN"}

echo "========================================"
echo "[09] SSL/HTTPS con Let's Encrypt"
echo "Dominio: $DOMAIN"
echo "========================================"
echo ""

echo "Verificando que el DNS ya resuelve a este servidor..."
RESOLVED_IP=$(dig +short $DOMAIN | tail -1)
MY_IP=$(curl -s ifconfig.me)

echo "  DNS de $DOMAIN resuelve a: $RESOLVED_IP"
echo "  IP de este VPS: $MY_IP"

if [ "$RESOLVED_IP" != "$MY_IP" ]; then
    echo ""
    echo "ADVERTENCIA: el DNS todavia no apunta a este VPS."
    echo "Espera a que propague antes de continuar (puede tardar hasta 24-48h)."
    read -p "¿Continuar de todas formas? (s/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Ss]$ ]]; then
        exit 1
    fi
fi

sudo certbot certify --nginx \
    --agree-tos \
    --no-eff-email \
    --email $EMAIL \
    -d $DOMAIN \
    -d www.$DOMAIN

sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
sudo certbot renew --dry-run

sudo nginx -t
sudo systemctl reload nginx

echo ""
echo "✓ SSL configurado exitosamente"
sudo certbot certificates -d $DOMAIN
echo ""
echo "Accede a: https://$DOMAIN"
