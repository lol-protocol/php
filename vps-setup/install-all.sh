#!/bin/bash
set -e

DOMAIN=${1:-"initech.cl"}
EMAIL=${2:-"admin@$DOMAIN"}

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║   VPS Setup Completo - Ubuntu 24 LTS                      ║"
echo "║   Dominio: $DOMAIN"
echo "║   Email: $EMAIL"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
read -p "¿Continuar? (s/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Ss]$ ]]; then
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Make all scripts executable
chmod +x *.sh

# Step 1: Initial setup
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║ [PASO 1/4] Instalando software base...                   ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
bash ./01-initial-setup.sh
sleep 2

# Step 2: Nginx setup
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║ [PASO 2/4] Configurando Nginx...                          ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
bash ./02-nginx-setup.sh "$DOMAIN"
sleep 2

# Step 3: SSL setup
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║ [PASO 3/4] Configurando SSL/HTTPS...                      ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
bash ./03-ssl-setup.sh "$DOMAIN" "$EMAIL"
sleep 2

# Step 4: Deploy landing page
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║ [PASO 4/4] Desplegando Landing Page...                    ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
bash ./04-deploy-landing-page.sh

# Final summary
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  ✓ SETUP COMPLETADO EXITOSAMENTE                          ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "Tu landing page está disponible en:"
echo "  🔗 https://$DOMAIN"
echo "  🔗 https://www.$DOMAIN"
echo ""
echo "Certificado SSL:"
sudo certbot certificates -d $DOMAIN 2>/dev/null || echo "Verificando certificado..."
echo ""
echo "Logs de Nginx:"
echo "  Access: /var/log/nginx/$DOMAIN/access.log"
echo "  Error:  /var/log/nginx/$DOMAIN/error.log"
echo ""
echo "Siguientes pasos opcionales:"
echo "  - Aplicación PHP: bash ./05-setup-php-app.sh nombre-app dominio.com"
echo "  - Aplicación Python: bash ./06-setup-python-app.sh nombre-app dominio.com"
echo ""
