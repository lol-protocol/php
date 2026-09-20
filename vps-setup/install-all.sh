#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
SITE_USER=${2:-"${DOMAIN%%.*}"}

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║   VPS Setup Completo - Ubuntu 24 LTS + CloudPanel          ║"
echo "║   Dominio: $DOMAIN"
echo "║   Usuario del sitio: $SITE_USER"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "Esto instala CloudPanel (panel de control, requiere servidor limpio),"
echo "luego crea el sitio para tu dominio y despliega la landing page."
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
    "02-install-cloudpanel.sh"
    "04_A-add-site-php.sh:$DOMAIN $SITE_USER"
    "05-deploy-landing-page.sh:$DOMAIN $SITE_USER"
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
echo "║  ✓ SETUP BASE COMPLETADO                                   ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "Tu landing page deberia responder en:"
echo "  🔗 http://$DOMAIN   (aun sin SSL)"
echo ""
echo "Pasos siguientes:"
echo "  1. Verifica que el DNS de $DOMAIN ya apunte a este VPS:"
echo "     nslookup $DOMAIN"
echo ""
echo "  2. Una vez propagado, instala SSL:"
echo "     ./04_D-install-ssl-cloudpanel.sh $DOMAIN"
echo ""
echo "  3. Si aun no creaste el usuario admin de CloudPanel, entra a:"
echo "     https://$(curl -s ifconfig.me):8443"
echo ""
echo "Extras opcionales (independientes entre si):"
echo "  - Base de datos MySQL/MariaDB: ya viene con CloudPanel (usa su UI o 'clpctl db:add')"
echo "  - PostgreSQL:        bash ./03_C-install-postgresql.sh"
echo "  - Java:               bash ./03_A-install-java.sh"
echo "  - Apache Tomcat:      bash ./03_D-install-tomcat.sh   (requiere 03_A)"
echo "  - Python + Whisper:   bash ./03_E-install-python-whisper.sh   (requiere 03_B)"
echo "  - Otro sitio Python:  bash ./04_B-add-site-python.sh dominio.com"
echo "  - Reverse proxy (ej. a Tomcat): bash ./04_C-add-site-reverse-proxy.sh dominio.com http://127.0.0.1:8080"
echo "  - Servidor DNS propio (solo si tu registrador no gestiona DNS): bash ./06_A-setup-dns-server.sh dominio.com IP"
echo ""
