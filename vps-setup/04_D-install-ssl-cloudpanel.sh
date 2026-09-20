#!/bin/bash
set -e

DOMAIN=${1:-"initech.fun"}
WWW_DOMAIN="www.$DOMAIN"

echo "========================================"
echo "[04_D] SSL (Let's Encrypt) via CloudPanel"
echo "Dominio: $DOMAIN"
echo "========================================"
echo ""

if ! command -v clpctl &> /dev/null; then
    echo "ERROR: CloudPanel no esta instalado. Corre primero: ./02-install-cloudpanel.sh"
    exit 1
fi

echo "Verificando que el DNS ya resuelve a este servidor..."
RESOLVED_IP=$(dig +short $DOMAIN | tail -1)
MY_IP=$(curl -s ifconfig.me)

echo "  DNS de $DOMAIN resuelve a: $RESOLVED_IP"
echo "  IP de este VPS: $MY_IP"

if [ "$RESOLVED_IP" != "$MY_IP" ]; then
    echo ""
    echo "ADVERTENCIA: el DNS todavia no apunta a este VPS."
    read -p "¿Continuar de todas formas? (s/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Ss]$ ]]; then
        exit 1
    fi
fi

clpctl lets-encrypt:install:certificate \
    --domainName="$DOMAIN" \
    --subjectAlternativeName="$WWW_DOMAIN"

echo ""
echo "✓ Certificado SSL instalado"
echo "Accede a: https://$DOMAIN"
