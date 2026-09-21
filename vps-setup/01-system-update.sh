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
sudo ufw allow OpenSSH                # Permite SSH (puerto 22) ANTES de activar -- ver advertencia arriba
sudo ufw --force enable               # --force evita que quede esperando confirmacion interactiva "y/n"

echo ""
echo "✓ Sistema actualizado"
echo "  $(lsb_release -d | cut -f2)"
echo ""
echo "✓ Firewall activo (SSH permitido, todo lo demas bloqueado por ahora)"
sudo ufw status
