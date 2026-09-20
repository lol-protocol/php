#!/bin/bash
set -e

echo "========================================"
echo "[02] Instalacion de CloudPanel"
echo "========================================"
echo ""
echo "CloudPanel instala y administra por si mismo: Nginx, PHP (varias"
echo "versiones), MariaDB/MySQL y Let's Encrypt (SSL). Reemplaza los pasos"
echo "manuales de Nginx/PHP/Certbot que usabamos antes."
echo ""
echo "REQUISITO: servidor limpio, SIN Apache/Nginx ya instalados."
echo ""

if command -v nginx &> /dev/null || command -v apache2 &> /dev/null; then
    echo "ERROR: Ya hay Nginx o Apache instalado en este VPS."
    echo "CloudPanel requiere un servidor limpio. Si instalaste el stack"
    echo "manual de una version anterior de estos scripts, no continues:"
    echo "reinstala el VPS desde OVHCloud primero (Ubuntu 24.04 limpio)."
    exit 1
fi

if [ "$(id -u)" -ne 0 ] && ! sudo -n true 2>/dev/null; then
    echo "Este script necesita sudo. Se te pedira la contraseña."
fi

cd /tmp

echo "[1/2] Descargando instalador oficial y verificando checksum..."
curl -sS https://installer.cloudpanel.io/ce/v2/install.sh -o install.sh
if ! echo "a3ba69a8102345127b4ae0e28cfe89daca675cbc63cd39225133cdd2fa02ad36 install.sh" | sha256sum -c -; then
    echo ""
    echo "ERROR: el checksum no coincide (CloudPanel actualizo su instalador)."
    echo "Revisa el comando actual en: https://www.cloudpanel.io/docs/v2/getting-started/installation/"
    exit 1
fi

echo ""
echo "[2/2] Ejecutando instalador (elige MariaDB cuando pregunte, salvo que tengas otra razon)..."
sudo bash install.sh

# Firewall
if command -v ufw &> /dev/null; then
    sudo ufw allow 80/tcp || true
    sudo ufw allow 443/tcp || true
    sudo ufw allow 8443/tcp || true
fi

IP=$(curl -s ifconfig.me)

echo ""
echo "✓ CloudPanel instalado"
echo ""
echo "Entra AHORA MISMO a crear el usuario admin (queda expuesto hasta que lo hagas):"
echo "  https://$IP:8443"
echo ""
echo "Despues, usa los scripts 04_* para agregar sitios (dominios) via su CLI (clpctl)."
