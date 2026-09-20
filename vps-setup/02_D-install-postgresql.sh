#!/bin/bash
set -e

echo "========================================"
echo "[05] Instalacion de PostgreSQL"
echo "========================================"
echo ""

sudo apt-get install -y postgresql postgresql-contrib

sudo systemctl start postgresql
sudo systemctl enable postgresql

echo ""
echo "✓ PostgreSQL instalado"
psql --version
sudo systemctl status postgresql --no-pager | head -5
