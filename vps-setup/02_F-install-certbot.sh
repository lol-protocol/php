#!/bin/bash
set -e

echo "========================================"
echo "[02_F] Instalacion de Certbot (SSL)"
echo "========================================"
echo ""

# certbot: cliente oficial de Let's Encrypt, pide y renueva certificados SSL gratis.
# python3-certbot-nginx: el "plugin" que le permite a Certbot editar la configuracion
#                        de Nginx automaticamente (agregar el bloque HTTPS, redirigir
#                        HTTP->HTTPS, etc.) en vez de tener que hacerlo a mano.
sudo apt-get install -y certbot python3-certbot-nginx

echo ""
echo "✓ Certbot instalado"
certbot --version
