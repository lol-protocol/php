#!/bin/bash
set -e

echo "========================================"
echo "[02_H] Instalacion de Apache Tomcat"
echo "========================================"
echo ""
echo "NOTA: requiere Java ya instalado (script 02_A-install-java.sh)."
echo ""

if ! command -v java &> /dev/null; then
    echo "ERROR: Java no esta instalado. Corre primero: ./02_A-install-java.sh"
    exit 1
fi

# tomcat10: servidor de aplicaciones Java -- ejecuta archivos .war (servlets/webapps).
#           Nginx no puede correr Java directamente, por eso Tomcat corre aparte
#           (puerto 8080) y luego Nginx lo expone como reverse proxy (ver 06_D).
# tomcat10-admin: paneles web de administracion (manager/host-manager) de Tomcat
sudo apt-get install -y tomcat10 tomcat10-admin

sudo systemctl start tomcat10
sudo systemctl enable tomcat10   # Arranca automaticamente si el VPS se reinicia

# Abrimos el 8080 solo para pruebas locales/diagnostico; en produccion el trafico
# real deberia entrar por Nginx (80/443), no directo a este puerto.
if command -v ufw &> /dev/null; then
    sudo ufw allow 8080/tcp || true
fi

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
