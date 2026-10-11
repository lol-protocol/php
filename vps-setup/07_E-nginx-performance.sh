#!/bin/bash
# Compresion y cache de archivos en Nginx para TODOS los dominios (un solo archivo en conf.d/).
set -e

source "$(dirname "$0")/lib.sh"

print_header "07_E" "Rendimiento de Nginx (gzip + cache de archivos)"

# Ruta reemplazable por variable de entorno (se usa en los tests).
PERF_CONF=${PERF_CONF:-/etc/nginx/conf.d/performance.conf}

# El nginx.conf de Ubuntu ya trae "gzip on;" pero deja COMENTADOS estos ajustes, asi que
# por defecto solo se comprime text/html. Aqui se completan (sin repetir "gzip on;", que
# nginx rechaza como directiva duplicada):
#   gzip_types       CSS, JS, JSON, XML y SVG tambien se comprimen (suelen reducirse 60-80%).
#   gzip_vary        evita que un proxy/CDN sirva la version comprimida a quien no la acepta.
#   gzip_comp_level  5: casi toda la ganancia del nivel 9 con una fraccion del CPU.
#   gzip_min_length  no comprime respuestas diminutas (el ahorro no compensa el CPU).
#   gzip_proxied     tambien comprime las respuestas de apps detras de proxy_pass (Python/Tomcat).
#   open_file_cache  recuerda descriptores y metadatos de archivos estaticos: menos syscalls por peticion.
sudo tee "$PERF_CONF" > /dev/null <<'EONGINX'
gzip_vary on;
gzip_proxied any;
gzip_comp_level 5;
gzip_min_length 256;
gzip_types text/plain text/css text/xml text/javascript application/javascript application/json application/xml application/xml+rss image/svg+xml;

open_file_cache max=1000 inactive=20s;
open_file_cache_valid 30s;
open_file_cache_min_uses 2;
open_file_cache_errors on;
EONGINX

# Si el nginx.conf del servidor ya define alguna de estas directivas (alguien las descomento),
# nginx -t falla por duplicado: se quita lo nuestro y se deja todo como estaba.
if ! sudo nginx -t; then
    sudo rm -f "$PERF_CONF"
    echo "ERROR: nginx rechazo la configuracion (¿directivas gzip ya definidas en nginx.conf?); se quito $PERF_CONF."
    exit 1
fi
sudo systemctl reload nginx

echo ""
echo "✓ Compresion gzip ampliada y cache de archivos activos para todos los dominios"
echo "Verifica con: curl -sI -H 'Accept-Encoding: gzip' https://tudominio.com/ | grep -i content-encoding"
