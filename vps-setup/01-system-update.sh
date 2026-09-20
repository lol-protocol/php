#!/bin/bash
set -e   # Si cualquier comando falla, el script se detiene (evita seguir con un sistema a medio actualizar)

echo "========================================"
echo "[01] Actualizacion del Sistema"
echo "========================================"
echo ""

sudo apt-get update       # Refresca el indice de paquetes disponibles
sudo apt-get upgrade -y   # Instala las actualizaciones pendientes (-y = confirma automaticamente)

# Utilidades base que casi todos los pasos siguientes van a necesitar:
#   curl, wget          -> descargar archivos/instaladores
#   git                 -> clonar este mismo repositorio
#   build-essential     -> compiladores (gcc, make) por si algun paquete necesita compilar algo
#   software-properties-common -> agregar repositorios de terceros (add-apt-repository)
#   dnsutils            -> comandos dig/nslookup, usados para verificar DNS mas adelante
sudo apt-get install -y curl wget git build-essential software-properties-common dnsutils

echo ""
echo "✓ Sistema actualizado"
echo "  $(lsb_release -d | cut -f2)"
