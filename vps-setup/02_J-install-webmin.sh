#!/bin/bash
set -e

# Source shared functions
source "$(dirname "$0")/lib.sh"

print_header "02_J" "Instalacion de Webmin"

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
# mktemp -d (0700, nombre aleatorio) en vez de /tmp/setup-repos.sh: una ruta fija en /tmp
# permite a otro usuario local dejar ahi un archivo o symlink y que root lo ejecute.
TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT
curl -fsS -o "$TMP_DIR/setup-repos.sh" https://raw.githubusercontent.com/webmin/webmin/master/setup-repos.sh
sudo bash "$TMP_DIR/setup-repos.sh"

echo "[2/3] Instalando Webmin..."
sudo apt-get install -y --install-recommends webmin

# Webmin acepta el login de root/sudo del sistema: abierto a todo internet es un blanco de
# fuerza bruta (fail2ban solo vigila SSH). Con WEBMIN_ALLOW_FROM=<tu IP> (o CIDR) solo
# esa direccion llega al puerto; sin ella queda abierto y se avisa al final.
echo "[3/3] Abriendo el puerto 10000 en el firewall..."
WEBMIN_ALLOW_FROM=${WEBMIN_ALLOW_FROM:-}
if [ -n "$WEBMIN_ALLOW_FROM" ]; then
    [[ "$WEBMIN_ALLOW_FROM" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}(/[0-9]{1,2})?$ ]] \
        || { echo "ERROR: WEBMIN_ALLOW_FROM debe ser una IPv4 o CIDR (ej. 203.0.113.5 o 203.0.113.0/24)"; exit 2; }
    if sudo ufw status | grep -q "Status: active"; then
        sudo ufw allow from "$WEBMIN_ALLOW_FROM" to any port 10000 proto tcp
    else
        echo "AVISO: ufw no esta activo; la regla no se agrego (corre 01-system-update.sh)"
    fi
else
    ufw_allow "10000/tcp"
    WEBMIN_OPEN=1
fi

# Si install-all.sh llamo a este script, hereda su CACHED_PUBLIC_IP (variable
# exportada) y no vuelve a consultar ifconfig.me; si este script corre solo,
# get_public_ip() lo consulta normalmente.
IP=$(get_public_ip)

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
if [ -n "$WEBMIN_OPEN" ]; then
    echo "AVISO DE SEGURIDAD: el puerto 10000 esta abierto a todo internet. Restringelo a tu IP:"
    echo "  sudo ufw delete allow 10000/tcp && sudo ufw allow from <TU_IP> to any port 10000 proto tcp"
    echo ""
fi
echo "Nada de esto es obligatorio para que el resto de los scripts funcione:"
echo "Webmin es 100% opcional y solo una capa visual sobre los mismos"
echo "comandos (nginx, certbot, systemctl, etc.) que ya usan estos scripts."
