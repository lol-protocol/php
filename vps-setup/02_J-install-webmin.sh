#!/bin/bash
set -e

# Source shared functions
source "$(dirname "$0")/lib.sh"

print_header "02_J" "Instalacion de Webmin"

# Como se accede a Webmin (por defecto, SOLO por tunel SSH):
#   (nada)                 escucha solo en 127.0.0.1: no hay puerto 10000 abierto a internet ni regla de UFW.
#                          Entras con:  ssh -L 10000:localhost:10000 usuario@IP_DEL_VPS  y abres
#                          https://localhost:10000. Funciona con cualquier IP (fija o dinamica).
#   WEBMIN_ALLOW_FROM=IP   escucha en todas las interfaces, pero UFW solo deja pasar a esa IP/CIDR.
#                          Solo tiene sentido con una IP fija.
#   WEBMIN_PUBLIC=1        abierto a todo internet (login de root/sudo expuesto a fuerza bruta: NO recomendado).
# Se valida ANTES de descargar/instalar nada (antes se validaba al final, con Webmin ya instalado).
WEBMIN_ALLOW_FROM=${WEBMIN_ALLOW_FROM:-}
WEBMIN_PUBLIC=${WEBMIN_PUBLIC:-0}
WEBMIN_CONF=${WEBMIN_CONF:-/etc/webmin/miniserv.conf}
if [ -n "$WEBMIN_ALLOW_FROM" ] && ! [[ "$WEBMIN_ALLOW_FROM" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}(/[0-9]{1,2})?$ ]]; then
    echo "ERROR: WEBMIN_ALLOW_FROM debe ser una IPv4 o CIDR (ej. 203.0.113.5 o 203.0.113.0/24)"
    exit 2
fi
if [ -n "$WEBMIN_ALLOW_FROM" ] && [ "$WEBMIN_PUBLIC" = 1 ]; then
    echo "ERROR: WEBMIN_ALLOW_FROM y WEBMIN_PUBLIC=1 se contradicen: elige uno."
    exit 2
fi

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
# --force: el instalador PREGUNTA "Setup Webmin stable repository? (y/N)". Sin terminal
# toma la "N" (y no agrega nada); por ssh -t (remote-run.sh) se queda esperando una tecla.
# OJO: setup-repos.sh es un envoltorio que descarga webmin-setup-repo.sh y sale SIEMPRE con 0,
# aunque falle (verificado: con la descarga de la llave bloqueada devolvio 0). Por eso se
# comprueba a continuacion que el paquete quedo disponible, en vez de confiar en su codigo.
sudo sh "$TMP_DIR/setup-repos.sh" --force < /dev/null
if ! apt-cache show webmin > /dev/null 2>&1; then
    echo "ERROR: el repositorio de Webmin no quedo configurado (el paquete 'webmin' no aparece en apt)." >&2
    echo "Revisa la salida de arriba (descarga de la llave en download.webmin.com, conexion)." >&2
    exit 1
fi

echo "[2/3] Instalando Webmin..."
sudo apt-get install -y --install-recommends webmin

# Webmin acepta el login de root/sudo del sistema: abierto a todo internet es un blanco de
# fuerza bruta (fail2ban solo vigila SSH). Por eso, por defecto, no se abre al exterior.
echo "[3/3] Configurando el acceso a Webmin..."
if [ -n "$WEBMIN_ALLOW_FROM" ] || [ "$WEBMIN_PUBLIC" = 1 ]; then
    # Escucha en todas las interfaces (sin "bind=").
    sudo sed -i '/^bind=/d' "$WEBMIN_CONF"
    if [ -n "$WEBMIN_ALLOW_FROM" ]; then
        if sudo ufw status | grep -q "Status: active"; then
            sudo ufw allow from "$WEBMIN_ALLOW_FROM" to any port 10000 proto tcp
        else
            echo "AVISO: ufw no esta activo; la regla no se agrego (corre 01-system-update.sh)"
        fi
        WEBMIN_MODE=ip
    else
        ufw_allow "10000/tcp"
        WEBMIN_MODE=publico
    fi
else
    # Solo localhost: sin regla de UFW (el trafico de loopback no pasa por el firewall) y se
    # quita cualquier regla abierta que una instalacion anterior haya dejado.
    if sudo grep -q '^bind=' "$WEBMIN_CONF"; then
        sudo sed -i 's/^bind=.*/bind=127.0.0.1/' "$WEBMIN_CONF"
    else
        echo 'bind=127.0.0.1' | sudo tee -a "$WEBMIN_CONF" > /dev/null
    fi
    if sudo ufw status | grep -q "Status: active" && sudo ufw status | grep -qE '^10000/tcp +ALLOW'; then
        sudo ufw delete allow 10000/tcp > /dev/null 2>&1 || true
        echo "  Se quito la regla antigua de UFW que abria el 10000 a todo internet."
    fi
    WEBMIN_MODE=tunel
fi
# miniserv.conf solo se relee al reiniciar
sudo systemctl restart webmin

# Si install-all.sh llamo a este script, hereda su CACHED_PUBLIC_IP (variable
# exportada) y no vuelve a consultar ifconfig.me; si este script corre solo,
# get_public_ip() lo consulta normalmente.
IP=$(get_public_ip)

echo ""
echo "✓ Webmin instalado"
sudo systemctl status webmin --no-pager | head -5
echo ""
echo "Usuario y contraseña: los mismos que por SSH (root o tu usuario sudo)."
case "$WEBMIN_MODE" in
    tunel)
        echo "Webmin escucha SOLO en 127.0.0.1 (no hay puerto 10000 abierto a internet). Para entrar,"
        echo "desde TU equipo abre un tunel SSH y deja esa terminal abierta:"
        echo "  ssh -L 10000:localhost:10000 usuario@$IP"
        echo "y en el navegador:  https://localhost:10000   (el certificado es autofirmado: acepta el aviso)"
        ;;
    ip)
        echo "Acceso directo, solo desde $WEBMIN_ALLOW_FROM:  https://$IP:10000"
        ;;
    publico)
        echo "  https://$IP:10000"
        echo ""
        echo "AVISO DE SEGURIDAD: el puerto 10000 esta abierto a todo internet. Cierralo y usa el tunel SSH:"
        echo "  sudo ufw delete allow 10000/tcp; ./02_J-install-webmin.sh   (sin WEBMIN_PUBLIC)"
        ;;
esac
echo ""
echo "Modulos utiles ya incluidos: Nginx Webserver, Usuarios y Grupos,"
echo "Tareas Cron, Firewall (UFW), Actualizacion de Paquetes, Gestor de Archivos."
echo ""
echo "Nada de esto es obligatorio para que el resto de los scripts funcione:"
echo "Webmin es 100% opcional y solo una capa visual sobre los mismos"
echo "comandos (nginx, certbot, systemctl, etc.) que ya usan estos scripts."
