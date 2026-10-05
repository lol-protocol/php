#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

print_header "07_A" "fail2ban + actualizaciones automaticas de seguridad"

# fail2ban: lee los logs de intentos fallidos y banea (via iptables/UFW) a las IP que
#           fallan demasiadas veces. Con login SSH por contrasena es la defensa minima
#           contra fuerza bruta.
# unattended-upgrades: instala solos los parches de seguridad de Ubuntu.
sudo apt-get install -y fail2ban unattended-upgrades

# Solo habilitamos el jail de SSH. Los jails de Nginx que trae fail2ban leen
# /var/log/nginx/error.log, y aqui los logs son por dominio
# (/var/log/nginx/<dominio>/), asi que ese archivo no existe y fail2ban fallaria.
# backend=systemd: en Ubuntu 24.04 SSH loguea en el journal, no en /var/log/auth.log.
# jail.local (no jail.conf) para que una actualizacion del paquete no pise esto.
sudo tee /etc/fail2ban/jail.local > /dev/null <<'EOJAIL'
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
sudo tee /etc/apt/apt.conf.d/20auto-upgrades > /dev/null <<'EOAPT'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOAPT

echo ""
echo "✓ fail2ban y actualizaciones automaticas configurados"
sudo fail2ban-client status sshd
echo ""
echo "Ver IPs baneadas:    sudo fail2ban-client status sshd"
echo "Desbanear una IP:    sudo fail2ban-client set sshd unbanip <IP>"
echo "Reinicio pendiente:  ls /var/run/reboot-required 2>/dev/null"
