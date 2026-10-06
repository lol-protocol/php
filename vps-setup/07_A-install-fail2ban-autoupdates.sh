#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

print_header "07_A" "fail2ban + actualizaciones automaticas de seguridad"

# Rutas reemplazables por variables de entorno (se usan en los tests).
FAIL2BAN_JAIL=${FAIL2BAN_JAIL:-/etc/fail2ban/jail.local}
APT_AUTO_CONF=${APT_AUTO_CONF:-/etc/apt/apt.conf.d/20auto-upgrades}

# fail2ban: lee los logs de intentos fallidos y banea (via iptables/UFW) a las IP que
#           fallan demasiadas veces. Con login SSH por contrasena es la defensa minima
#           contra fuerza bruta.
# unattended-upgrades: instala solos los parches de seguridad de Ubuntu.
# python3-systemd: necesario para "backend = systemd" (leer SSH desde el journal).
sudo apt-get install -y fail2ban unattended-upgrades python3-systemd

# Solo habilitamos el jail de SSH. Los jails de Nginx que trae fail2ban leen
# /var/log/nginx/error.log, y aqui los logs son por dominio
# (/var/log/nginx/<dominio>/), asi que ese archivo no existe y fail2ban fallaria.
# backend=systemd: en Ubuntu 24.04 SSH loguea en el journal, no en /var/log/auth.log.
# jail.local (no jail.conf) para que una actualizacion del paquete no pise esto.
sudo tee "$FAIL2BAN_JAIL" > /dev/null <<'EOJAIL'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
backend = systemd
EOJAIL

service_start_enable fail2ban
sudo systemctl restart fail2ban   # aplica jail.local si el servicio ya estaba corriendo

# 20auto-upgrades: activa la actualizacion diaria de lista de paquetes y la
# instalacion automatica. El origen "solo seguridad" ya viene configurado por
# defecto en 50unattended-upgrades. NO se reinicia el servidor solo
# (Automatic-Reboot esta en "false" por defecto): si un parche del kernel lo
# pide, el reinicio lo decides tu.
sudo tee "$APT_AUTO_CONF" > /dev/null <<'EOAPT'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOAPT

echo ""
echo "✓ fail2ban y actualizaciones automaticas configurados"
# Tras el restart, fail2ban tarda unos segundos en abrir su socket; consultarlo de
# inmediato falla y, con "set -e", abortaria el script (y todo install-all) justo
# al final, aunque todo lo anterior haya salido bien.
for _ in $(seq 1 15); do
    sudo fail2ban-client ping > /dev/null 2>&1 && break
    sleep 1
done
sudo fail2ban-client status sshd || echo "AVISO: fail2ban aun no responde; revisa con: sudo systemctl status fail2ban"
echo ""
echo "Ver IPs baneadas:    sudo fail2ban-client status sshd"
echo "Desbanear una IP:    sudo fail2ban-client set sshd unbanip <IP>"
echo "Reinicio pendiente:  ls /var/run/reboot-required 2>/dev/null"
