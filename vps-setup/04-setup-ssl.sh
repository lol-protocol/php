#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
EMAIL=${2:-"admin@$DOMAIN"}

echo "========================================"
echo "[04] SSL/HTTPS con Let's Encrypt"
echo "Dominio: $DOMAIN"
echo "========================================"
echo ""

# Let's Encrypt verifica que el dominio realmente apunte a este servidor antes
# de emitir el certificado -- si el DNS no propago aun, Certbot fallara.
# Este chequeo previo evita gastar un intento (Let's Encrypt limita cuantas
# veces puedes pedir certificado para el mismo dominio por semana).
echo "Verificando que el DNS ya resuelve a este servidor..."
RESOLVED_IP=$(dig +short $DOMAIN | tail -1)
# -4 fuerza IPv4: "dig +short" (sin mas flags) consulta el registro A (IPv4).
# Sin -4, si el VPS tiene conectividad IPv6, curl podria devolver una IPv6
# aqui y la comparacion de abajo daria un falso "el DNS no apunta a este VPS"
# aunque el registro A si sea correcto.
MY_IP=$(curl -4 -s --max-time 5 ifconfig.me)   # --max-time evita que el script se cuelgue si el servicio no responde

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

# 'run' obtiene el certificado Y lo instala (agrega el bloque 443 y la
# redirección HTTP->HTTPS a la config de Nginx automaticamente). NOTA: el
# subcomando se llama 'run' (o 'certonly' si solo quisieras el certificado
# sin tocar Nginx) -- "certify" no existe en Certbot, es un error comun.
# --agree-tos / --no-eff-email: evita que el comando se detenga pidiendo
#          confirmacion interactiva (necesario para correrlo desde un script)
sudo certbot run --nginx \
    --agree-tos \
    --no-eff-email \
    --email $EMAIL \
    -d $DOMAIN \
    -d www.$DOMAIN

# certbot.timer revisa automaticamente (2 veces al dia) si algun certificado
# esta por vencer y lo renueva solo -- los certificados de Let's Encrypt duran
# 90 dias, asi que sin esto habria que renovarlos a mano cada 3 meses
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
sudo certbot renew --dry-run   # simulacro de renovacion, sin emitir nada de verdad, solo para confirmar que funcionaria

sudo nginx -t
sudo systemctl reload nginx

echo ""
echo "✓ SSL configurado exitosamente"
# "|| true": esto es solo informativo -- si fallara, el certificado ya fue
# emitido e instalado arriba, no queremos que el script termine en error
# por un problema al solo LISTAR el resultado.
sudo certbot certificates -d $DOMAIN || true
echo ""
echo "Accede a: https://$DOMAIN"
