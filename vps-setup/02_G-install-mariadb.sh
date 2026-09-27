#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

print_header "02_G" "Instalacion de MariaDB"

# mariadb-server: el motor de base de datos (fork de MySQL, compatible casi 1:1)
# mariadb-client: el comando 'mysql'/'mariadb' para conectarte desde la terminal
sudo apt-get install -y mariadb-server mariadb-client

service_start_enable mariadb

echo ""
echo "✓ MariaDB instalado"
mariadb --version
sudo systemctl status mariadb --no-pager | head -5

echo ""
echo "IMPORTANTE: corre esto manualmente para endurecer la instalacion"
echo "(pone contraseña de root, quita usuarios anonimos, quita BD de prueba):"
echo ""
echo "  sudo mysql_secure_installation"
