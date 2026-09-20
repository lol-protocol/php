#!/bin/bash
set -e

echo "========================================"
echo "[02_G] Instalacion de MariaDB"
echo "========================================"
echo ""

# mariadb-server: el motor de base de datos (fork de MySQL, compatible casi 1:1)
# mariadb-client: el comando 'mysql'/'mariadb' para conectarte desde la terminal
sudo apt-get install -y mariadb-server mariadb-client

sudo systemctl start mariadb
sudo systemctl enable mariadb   # Arranca automaticamente si el VPS se reinicia

echo ""
echo "✓ MariaDB instalado"
mariadb --version
sudo systemctl status mariadb --no-pager | head -5

echo ""
echo "IMPORTANTE: corre esto manualmente para endurecer la instalacion"
echo "(pone contraseña de root, quita usuarios anonimos, quita BD de prueba):"
echo ""
echo "  sudo mysql_secure_installation"
