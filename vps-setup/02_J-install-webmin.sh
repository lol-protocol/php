#!/bin/bash
set -e

echo "========================================"
echo "[02_J] Instalacion de Webmin"
echo "========================================"
echo ""
echo "Webmin es una GUI que edita los archivos de configuracion nativos"
echo "de Linux (Nginx, cron, usuarios, firewall, etc). No reemplaza ni"
echo "controla nada por su cuenta: si lo desinstalas, todo sigue"
echo "funcionando exactamente igual porque solo edito archivos estandar."
echo ""

echo "[1/3] Agregando el repositorio oficial de Webmin..."
# -f: si la URL devuelve error HTTP (ej. 404), curl falla claramente en vez
#     de guardar la pagina de error como si fuera el script y luego intentar
#     "ejecutarla" con bash (que fallaria despues, de forma mucho mas confusa).
# -sS: sin barra de progreso, pero SI muestra el error si -f lo dispara.
curl -fsS -o /tmp/setup-repos.sh https://raw.githubusercontent.com/webmin/webmin/master/setup-repos.sh
sudo bash /tmp/setup-repos.sh

echo "[2/3] Instalando Webmin..."
sudo apt-get install -y --install-recommends webmin

echo "[3/3] Abriendo el puerto 10000 en el firewall..."
if command -v ufw &> /dev/null; then
    sudo ufw allow 10000/tcp || true
fi

IP=$(curl -s --max-time 5 ifconfig.me || echo "TU_IP_PUBLICA")   # --max-time evita colgarse si el servicio no responde

echo ""
echo "✓ Webmin instalado"
sudo systemctl status webmin --no-pager | head -5
echo ""
echo "Accede con el mismo usuario/contraseña que usas por SSH (root o tu usuario sudo):"
echo "  https://$IP:10000"
echo ""
echo "Modulos utiles ya incluidos: Nginx Webserver, Usuarios y Grupos,"
echo "Tareas Cron, Firewall (UFW), Actualizacion de Paquetes, Gestor de Archivos."
echo ""
echo "Nada de esto es obligatorio para que el resto de los scripts funcione:"
echo "Webmin es 100% opcional y solo una capa visual sobre los mismos"
echo "comandos (nginx, certbot, systemctl, etc.) que ya usan estos scripts."
