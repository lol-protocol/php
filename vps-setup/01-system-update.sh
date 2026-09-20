#!/bin/bash
set -e

echo "========================================"
echo "[01] Actualizacion del Sistema"
echo "========================================"
echo ""

sudo apt-get update
sudo apt-get upgrade -y
sudo apt-get install -y curl wget git build-essential software-properties-common dnsutils sudo cron

echo ""
echo "✓ Sistema actualizado"
echo "  $(lsb_release -d | cut -f2)"
