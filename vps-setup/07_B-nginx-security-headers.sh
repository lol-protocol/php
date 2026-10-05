#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

print_header "07_B" "Headers de seguridad en Nginx"

# Un solo archivo en conf.d/ se carga dentro del bloque http{}, asi que aplica a
# TODOS los dominios (presentes y futuros) sin editar cada vhost ni pelear con
# las lineas que Certbot agrega a cada uno. Es idempotente: re-correr el script
# solo reescribe el mismo archivo.
#
# Nota de Nginx: si un server/location define sus propios add_header, estos
# headers NO se heredan en ese bloque (hay que repetirlos ahi).
#
#   Strict-Transport-Security: obliga al navegador a usar HTTPS (180 dias). Sin
#       includeSubDomains ni preload a proposito: son dificiles de revertir.
#       Los navegadores ignoran este header si llega por HTTP.
#   X-Frame-Options: evita que otro sitio te meta en un <iframe> (clickjacking).
#   X-Content-Type-Options: evita que el navegador "adivine" el tipo de archivo.
#   Referrer-Policy: no filtra rutas completas a otros sitios.
# No se agrega X-XSS-Protection (obsoleto, los navegadores modernos lo ignoran) ni
# Content-Security-Policy (requiere ajustarse a cada sitio; una CSP generica
# romperia el CSS inline de la landing page).
sudo tee /etc/nginx/conf.d/security-headers.conf > /dev/null <<'EONGINX'
add_header Strict-Transport-Security "max-age=15552000" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
EONGINX

sudo nginx -t              # valida ANTES de recargar, para no tumbar los sitios que ya funcionan
sudo systemctl reload nginx

echo ""
echo "✓ Headers de seguridad activos para todos los dominios"
echo "Verifica con: curl -sI https://tudominio.com | grep -iE 'strict|x-frame|x-content|referrer'"
