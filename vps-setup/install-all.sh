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

# Pasos con letra (02_A..02_F) son independientes entre si: el orden
# dentro del mismo numero no importa, solo que terminen antes del
# siguiente numero.
STEPS=(
    "01-system-update.sh"
    "02_A-install-java.sh"
    "02_B-install-php.sh"
    "02_C-install-python.sh"
    "02_D-install-postgresql.sh"
    "02_E-install-nginx.sh"
    "02_F-install-certbot.sh"
    "03-configure-nginx-site.sh:$DOMAIN"
    "04-setup-ssl.sh:$DOMAIN $EMAIL"
    "05-deploy-landing-page.sh"
)

TOTAL=${#STEPS[@]}
for i in "${!STEPS[@]}"; do
    ENTRY="${STEPS[$i]}"
    STEP="${ENTRY%%:*}"
    if [[ "$ENTRY" == *:* ]]; then
        ARGS="${ENTRY#*:}"
    else
        ARGS=""
    fi

    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    printf "║ [%d/%d] %-52s ║\n" "$((i+1))" "$TOTAL" "$STEP"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    bash "./$STEP" $ARGS
done

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
echo "Pasos opcionales (independientes entre si):"
echo "  - Aplicacion PHP:    bash ./06_A-setup-php-app.sh nombre-app dominio.com"
echo "  - Aplicacion Python: bash ./06_B-setup-python-app.sh nombre-app dominio.com"
echo "  - Servidor DNS propio (solo si tu registrador no tiene DNS Management): bash ./06_C-setup-dns-server.sh dominio.com IP"
echo ""
