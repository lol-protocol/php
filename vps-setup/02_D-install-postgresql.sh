#!/bin/bash
set -e

echo "========================================"
echo "[02_D] Instalacion de PostgreSQL"
echo "========================================"
echo ""

# postgresql: el motor de base de datos en si
# postgresql-contrib: extensiones adicionales oficiales (ej. funciones estadisticas,
#                     UUID, etc.) que muchos proyectos PHP/Python terminan necesitando
sudo apt-get install -y postgresql postgresql-contrib

sudo systemctl start postgresql
sudo systemctl enable postgresql   # Arranca automaticamente si el VPS se reinicia

echo ""
echo "✓ PostgreSQL instalado"
psql --version
sudo systemctl status postgresql --no-pager | head -5
