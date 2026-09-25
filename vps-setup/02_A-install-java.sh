#!/bin/bash
set -e

echo "========================================"
echo "[02_A] Instalacion de Java"
echo "========================================"
echo ""

# OpenJDK 21 es la version LTS (soporte largo) mas reciente al momento de escribir esto.
# "jdk" (no solo "jre") incluye el compilador javac, necesario para compilar
# aplicaciones Java propias (ej. el microservicio de sistema-nuevo/servicio-estadisticas-java).
sudo apt-get install -y openjdk-21-jdk

echo ""
echo "✓ Java instalado"
java -version
