#!/bin/bash
set -e

echo "========================================"
echo "[04] Instalacion de Python 3"
echo "========================================"
echo ""

sudo apt-get install -y python3 python3-pip python3-venv

echo ""
echo "✓ Python instalado"
python3 --version
pip3 --version
