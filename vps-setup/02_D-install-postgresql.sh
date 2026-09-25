#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

print_header "02_D" "Instalacion de PostgreSQL"

# postgresql: el motor de base de datos en si
# postgresql-contrib: extensiones adicionales oficiales (ej. funciones estadisticas,
#                     UUID, etc.) que muchos proyectos PHP/Python terminan necesitando
sudo apt-get install -y postgresql postgresql-contrib

service_start_enable postgresql

echo ""
echo "✓ PostgreSQL instalado"
psql --version
sudo systemctl status postgresql --no-pager | head -5
