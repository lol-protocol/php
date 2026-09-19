#!/bin/bash
set -e

echo "========================================"
echo "VPS Initial Setup - Ubuntu 24 LTS"
echo "========================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Update system
echo -e "${YELLOW}[1/6] Actualizando sistema...${NC}"
sudo apt-get update
sudo apt-get upgrade -y
sudo apt-get install -y curl wget git build-essential

# Install Java
echo -e "${YELLOW}[2/6] Instalando Java...${NC}"
sudo apt-get install -y openjdk-21-jdk
java -version

# Install PHP and extensions
echo -e "${YELLOW}[3/6] Instalando PHP...${NC}"
sudo apt-get install -y php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-mysql php8.3-postgresql php8.3-gd php8.3-curl php8.3-json php8.3-zip
php -v

# Install Python
echo -e "${YELLOW}[4/6] Instalando Python...${NC}"
sudo apt-get install -y python3 python3-pip python3-venv
python3 --version
pip3 --version

# Install PostgreSQL
echo -e "${YELLOW}[5/6] Instalando PostgreSQL...${NC}"
sudo apt-get install -y postgresql postgresql-contrib
sudo systemctl start postgresql
sudo systemctl enable postgresql
psql --version

# Install Nginx
echo -e "${YELLOW}[6/6] Instalando Nginx...${NC}"
sudo apt-get install -y nginx
sudo systemctl start nginx
sudo systemctl enable nginx

# Install Certbot for SSL
echo -e "${YELLOW}[+] Instalando Certbot para SSL...${NC}"
sudo apt-get install -y certbot python3-certbot-nginx

echo -e "${GREEN}========================================"
echo "✓ Setup completado exitosamente"
echo "========================================${NC}"
echo ""
echo "Versiones instaladas:"
echo "- Java: $(java -version 2>&1 | grep version)"
echo "- PHP: $(php -v | grep CLI)"
echo "- Python: $(python3 --version)"
echo "- PostgreSQL: $(psql --version)"
echo "- Nginx: $(nginx -v 2>&1)"
echo ""
echo "Proximos pasos:"
echo "1. Ejecutar: 02-nginx-setup.sh"
echo "2. Ejecutar: 03-ssl-setup.sh"
echo "3. Ejecutar: 04-deploy-landing-page.sh"
