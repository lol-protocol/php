#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

print_header "02_H" "Instalacion de Apache Tomcat"
echo "NOTA: requiere Java ya instalado (script 02_A-install-java.sh)."
echo ""

check_dependency java "./02_A-install-java.sh"

# tomcat10: servidor de aplicaciones Java -- ejecuta archivos .war (servlets/webapps).
#           Nginx no puede correr Java directamente, por eso Tomcat corre aparte
#           (puerto 8080) y luego Nginx lo expone como reverse proxy (ver 06_D).
# tomcat10-admin: paneles web de administracion (manager/host-manager) de Tomcat
sudo apt-get install -y tomcat10 tomcat10-admin

service_start_enable tomcat10

# NO abrimos el 8080 en UFW: el trafico de loopback (127.0.0.1) no pasa por
# el firewall, asi que "curl http://127.0.0.1:8080" funciona igual sin esta
# regla. Abrir 8080/tcp aqui expondria Tomcat (incluidos /manager y
# /host-manager) directo a internet, saltandose Nginx y cualquier HTTPS o
# header de seguridad. El trafico real debe entrar siempre por Nginx
# (80/443) via reverse proxy -- ver 06_D-setup-tomcat-app.sh.

echo ""
echo "✓ Apache Tomcat instalado y corriendo en el puerto 8080"
sudo systemctl status tomcat10 --no-pager | head -5
echo ""
echo "Verifica con: curl http://127.0.0.1:8080"
echo ""
echo "Directorios importantes:"
echo "  Webapps:  /var/lib/tomcat10/webapps/"
echo "  Config:   /etc/tomcat10/"
echo "  Logs:     /var/log/tomcat10/"
echo ""
echo "Para exponerlo bajo un dominio con Nginx como reverse proxy,"
echo "usa: ./06_D-setup-tomcat-app.sh tudominio.com"
