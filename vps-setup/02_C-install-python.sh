#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

print_header "02_C" "Instalacion de Python 3"

# python3-pip: gestor de paquetes de Python (pip install ...)
# python3-venv: permite crear entornos virtuales aislados (python3 -m venv);
#               obligatorio en Ubuntu 24.04, que bloquea "pip install" a nivel
#               de sistema (PEP 668) para evitar romper paquetes del propio SO.
sudo apt-get install -y python3 python3-pip python3-venv

echo ""
echo "✓ Python instalado"
python3 --version
pip3 --version
