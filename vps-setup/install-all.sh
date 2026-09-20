#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
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
chmod +x *.sh

STEPS=(
    "01-system-update.sh"
    "02-install-java.sh"
    "03-install-php.sh"
    "04-install-python.sh"
    "05-install-postgresql.sh"
    "06-install-nginx.sh"
    "07-install-certbot.sh"
)

for i in "${!STEPS[@]}"; do
    STEP="${STEPS[$i]}"
    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    printf "║ [%d/%d] %-52s ║\n" "$((i+1))" "$((${#STEPS[@]}+3))" "$STEP"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    bash "./$STEP"
done

N=$((${#STEPS[@]}+1))
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
printf "║ [%d/%d] %-52s ║\n" "$N" "$((${#STEPS[@]}+3))" "08-configure-nginx-site.sh"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
bash ./08-configure-nginx-site.sh "$DOMAIN"

N=$((N+1))
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
printf "║ [%d/%d] %-52s ║\n" "$N" "$((${#STEPS[@]}+3))" "09-setup-ssl.sh"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
bash ./09-setup-ssl.sh "$DOMAIN" "$EMAIL"

N=$((N+1))
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
printf "║ [%d/%d] %-52s ║\n" "$N" "$((${#STEPS[@]}+3))" "10-deploy-landing-page.sh"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
bash ./10-deploy-landing-page.sh

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  ✓ SETUP COMPLETADO EXITOSAMENTE                          ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "Tu landing page esta disponible en:"
echo "  🔗 https://$DOMAIN"
echo "  🔗 https://www.$DOMAIN"
echo ""
sudo certbot certificates -d $DOMAIN 2>/dev/null || echo "Verificando certificado..."
echo ""
echo "Logs de Nginx:"
echo "  Access: /var/log/nginx/$DOMAIN/access.log"
echo "  Error:  /var/log/nginx/$DOMAIN/error.log"
echo ""
echo "Pasos opcionales:"
echo "  - Aplicacion PHP:    bash ./11-setup-php-app.sh nombre-app dominio.com"
echo "  - Aplicacion Python: bash ./12-setup-python-app.sh nombre-app dominio.com"
echo "  - Servidor DNS propio (solo si tu registrador no tiene DNS Management): bash ./13-setup-dns-server.sh dominio.com IP"
echo ""
