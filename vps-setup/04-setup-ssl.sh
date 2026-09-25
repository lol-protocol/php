#!/bin/bash
set -e

# Source shared functions
source "$(dirname "$0")/lib.sh"

DOMAIN=${1:-"initech.fun"}
EMAIL=${2:-"admin@$DOMAIN"}

print_header "04" "SSL/HTTPS con Let's Encrypt (Dominio: $DOMAIN)"

# Let's Encrypt verifica que el dominio realmente apunte a este servidor antes
# de emitir el certificado -- si el DNS no propago aun, Certbot fallara.
# Este chequeo previo evita gastar un intento (Let's Encrypt limita cuantas
# veces puedes pedir certificado para el mismo dominio por semana).
#
# Revisamos TANTO $DOMAIN como www.$DOMAIN porque el comando de Certbot de
# abajo pide el certificado para los dos a la vez (-d $DOMAIN -d www.$DOMAIN):
# si a cualquiera de los dos le falta el registro A, Certbot rechaza TODO el
# certificado (no emite uno parcial), no solo el subdominio que falla.
echo "Verificando que el DNS ya resuelve a este servidor..."
MY_IP=$(get_public_ip)
echo "IP de este VPS: $MY_IP"
echo ""

# Usar verify_dns_resolution (batched DNS lookup) en lugar de dos dig calls
if ! verify_dns_resolution "$DOMAIN" "$MY_IP"; then
    echo ""
    echo "ADVERTENCIA: el DNS de $DOMAIN y/o www.$DOMAIN todavia no apunta a este VPS."
    echo "Espera a que propague antes de continuar (puede tardar hasta 24-48h)."

    # Solo prompt si es interactivo
    if [ -t 0 ]; then
        read -p "¿Continuar de todas formas? (s/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Ss]$ ]]; then
            exit 1
        fi
    else
        echo "Modo no-interactivo: asumiendo que el DNS ya propago..."
    fi
fi

# 'run' obtiene el certificado Y lo instala (agrega el bloque 443 y la
# redirección HTTP->HTTPS a la config de Nginx automaticamente). NOTA: el
# subcomando se llama 'run' (o 'certonly' si solo quisieras el certificado
# sin tocar Nginx) -- "certify" no existe en Certbot, es un error comun.
# --agree-tos / --no-eff-email / --non-interactive: evita que el comando se
#          detenga pidiendo confirmacion interactiva (necesario para automatizacion)
sudo certbot run --nginx \
    --agree-tos \
    --no-eff-email \
    --non-interactive \
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
