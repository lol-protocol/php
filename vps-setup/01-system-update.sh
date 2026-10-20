#!/bin/bash
set -e   # Si cualquier comando falla, el script se detiene (evita seguir con un sistema a medio actualizar)

source "$(dirname "$0")/lib.sh"

print_header "01" "Actualizacion del Sistema"

# Estos scripts estan hechos para Ubuntu 24.04: instalan php8.3-*, PostgreSQL 16, etc. En otra
# version esos paquetes no existen (Ubuntu 26.04 trae PHP 8.5) y la instalacion fallaria a mitad
# de camino. Se comprueba ANTES de tocar nada. ALLOW_UNSUPPORTED_OS=1 lo salta (bajo tu riesgo).
OS_RELEASE=${OS_RELEASE:-/etc/os-release}
# shellcheck disable=SC1090
OS_ID=$(. "$OS_RELEASE" 2>/dev/null; echo "${ID:-?} ${VERSION_ID:-?}")
if [ "$OS_ID" != "ubuntu 24.04" ] && [ "${ALLOW_UNSUPPORTED_OS:-0}" != 1 ]; then
    echo "ERROR: sistema no soportado ($OS_ID). Estos scripts requieren Ubuntu 24.04 LTS."
    echo "Al crear el VPS elige la imagen Ubuntu 24.04 (o usa ALLOW_UNSUPPORTED_OS=1 bajo tu riesgo)."
    exit 1
fi

# Ajustes de apt para que una instalacion larga (sobre todo por ssh -t, como remote-run.sh) no se
# quede colgada ni a medias:
#   Lock::Timeout   recien arrancado el VPS, unattended-upgrades suele tener el lock de apt unos
#                   minutos; en vez de fallar con "Could not get lock", apt espera hasta 5 min.
#   force-confold   si un paquete trae un archivo de configuracion nuevo y tu ya lo editaste, se
#                   conserva el tuyo sin preguntar (la pregunta interactiva bloquearia el script).
#   needrestart     en Ubuntu 24.04 pregunta que servicios reiniciar tras cada apt y, con un
#                   terminal, espera una tecla; 'a' los reinicia solo.
APT_TUNING_CONF=${APT_TUNING_CONF:-/etc/apt/apt.conf.d/90-vps-setup}
sudo tee "$APT_TUNING_CONF" > /dev/null <<'EOAPT'
DPkg::Lock::Timeout "300";
Dpkg::Options { "--force-confdef"; "--force-confold"; };
EOAPT
NEEDRESTART_DIR=${NEEDRESTART_DIR:-/etc/needrestart/conf.d}
if [ -d "$NEEDRESTART_DIR" ] || [ -d "$(dirname "$NEEDRESTART_DIR")" ]; then
    sudo mkdir -p "$NEEDRESTART_DIR"
    echo "\$nrconf{restart} = 'a';" | sudo tee "$NEEDRESTART_DIR/50-vps-setup.conf" > /dev/null
fi

sudo apt-get update       # Refresca el indice de paquetes disponibles
sudo apt-get upgrade -y   # Instala las actualizaciones pendientes (-y = confirma automaticamente)

# Utilidades base que casi todos los pasos siguientes van a necesitar:
#   curl, wget          -> descargar archivos/instaladores
#   git                 -> clonar este mismo repositorio
#   build-essential     -> compiladores (gcc, make) por si algun paquete necesita compilar algo
#   software-properties-common -> agregar repositorios de terceros (add-apt-repository)
#   dnsutils            -> comandos dig/nslookup, usados para verificar DNS mas adelante
sudo apt-get install -y curl wget git build-essential software-properties-common dnsutils ufw

# Activamos el firewall AQUI, en el primer script, antes de que corra nada
# mas. Los demas scripts (Nginx, Tomcat, Webmin, BIND9) van agregando sus
# propias reglas "ufw allow" a medida que instalan cada servicio -- pero esas
# reglas no sirven de nada si el firewall en si nunca se activa. Sin este
# paso, ufw se queda "inactive" (asi viene por defecto en Ubuntu) y todas
# esas reglas posteriores son letra muerta.
#
# ADVERTENCIA DE SEGURIDAD: activar ufw SIN permitir SSH primero te deja
# fuera de tu propio VPS en la siguiente conexion (sin forma de volver a
# entrar salvo por la consola de rescate del proveedor). Por eso el orden
# aqui es estricto: primero permitir SSH, RECIEN DESPUES activar.
echo "Configurando firewall (UFW)..."
# "limit" en vez de "allow": permite SSH pero bloquea una IP que abre mas de 6 conexiones en 30 s
# (fuerza bruta), antes incluso de que actue fail2ban. Ojo si automatizas muchas conexiones
# seguidas desde tu IP (remote-run.sh abre 2 por ejecucion).
sudo ufw limit OpenSSH                # Permite SSH (puerto 22) ANTES de activar -- ver advertencia arriba
sudo ufw --force enable               # --force evita que quede esperando confirmacion interactiva "y/n"

echo ""
echo "✓ Sistema actualizado"
echo "  $(lsb_release -d | cut -f2)"
echo ""
echo "✓ Firewall activo (SSH permitido, todo lo demas bloqueado por ahora)"
sudo ufw status
