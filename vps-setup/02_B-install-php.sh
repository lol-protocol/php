#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

print_header "02_B" "Instalacion de PHP $PHP_VERSION"

# php8.3-fpm: el proceso que realmente ejecuta el codigo PHP (Nginx solo le reenvia
#             las peticiones .php via socket unix, no lo ejecuta el mismo).
# php8.3-cli: permite correr scripts PHP desde la terminal (util para composer, cron, etc).
# php8.3-mysql / php8.3-pgsql: drivers para conectar PHP a cada base de datos
#             (instalamos ambos por si el proyecto usa una u otra). OJO con el nombre:
#             el paquete es "php8.3-pgsql", NO "php8.3-postgresql" (no existe).
# json: ya viene incluido en PHP 8 (php8.3-common); "php8.3-json" no existe como
#             paquete aparte y apt fallaria con "Unable to locate package".
# php8.3-mbstring / php8.3-xml: requeridos por la mayoria de frameworks (Laravel, Symfony)
#             y por Composer para resolver dependencias.
V=$PHP_VERSION   # se fija en lib.sh
sudo apt-get install -y \
    "php$V" "php$V-fpm" "php$V-cli" "php$V-common" \
    "php$V-mysql" "php$V-pgsql" "php$V-gd" \
    "php$V-curl" "php$V-zip" \
    "php$V-mbstring" "php$V-xml" "php$V-bcmath"

service_start_enable "$PHP_FPM_SERVICE"

echo ""
echo "✓ PHP instalado"
php -v
