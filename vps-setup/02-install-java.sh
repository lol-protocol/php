#!/bin/bash
set -e

echo "========================================"
echo "[02] Instalacion de Java"
echo "========================================"
echo ""

sudo apt-get install -y openjdk-21-jdk

echo ""
echo "✓ Java instalado"
java -version
