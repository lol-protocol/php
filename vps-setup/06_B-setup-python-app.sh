#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

APP_NAME=${1:-}
DOMAIN=${2:-}
# Puerto local de gunicorn: cada app Python necesita el suyo (dos apps en el mismo puerto
# = la segunda no arranca y su dominio termina mostrando la primera).
PORT=${3:-8000}
require_arg "$DOMAIN" "$0 <nombre-app> <dominio> [puerto]   (ej. $0 blog blog.initech.fun 8001)"
require_valid name "$APP_NAME" "El nombre de la app (argumento 1)"
require_valid domain "$DOMAIN" "El dominio (argumento 2)"
require_valid port "$PORT" "El puerto (argumento 3)"
SYSTEMD_DIR=${SYSTEMD_DIR:-/etc/systemd/system}
APP_PATH="/var/www/$APP_NAME"
VENV_PATH="$APP_PATH/venv"

print_header "06_B" "Configurando Aplicacion Python: $APP_NAME (Dominio: $DOMAIN, puerto $PORT)"

claim_nginx_vhost "$DOMAIN" "06_B-python $APP_NAME"
# ¿Otra app (otra unidad systemd) ya usa este puerto? Re-correr para la MISMA app si vale.
OTHER=$(grep -lF -- "--bind 127.0.0.1:$PORT " "$SYSTEMD_DIR"/*.service 2>/dev/null | grep -vF "/$APP_NAME.service" || true)
if [ -n "$OTHER" ]; then
    echo "ERROR: el puerto $PORT ya lo usa $(basename "$OTHER" .service). Elige otro: $0 $APP_NAME $DOMAIN <puerto>" >&2
    exit 1
fi
if [ ! -f "$SYSTEMD_DIR/$APP_NAME.service" ] && ss -ltn "( sport = :$PORT )" 2>/dev/null | grep -q LISTEN; then
    echo "ERROR: algo ya escucha en 127.0.0.1:$PORT. Elige otro puerto (argumento 3)." >&2
    exit 1
fi

echo "Creando directorios..."
# Carpeta de logs propia de este dominio -- si no existe, "nginx -t" falla mas
# abajo porque Nginx no crea directorios el solo, solo los archivos dentro.
setup_app_directories "$APP_PATH" "$DOMAIN"

# Entorno virtual propio de esta app: aisla sus dependencias (Flask, etc.) del
# resto del sistema. Obligatorio en Ubuntu 24.04 (PEP 668 bloquea pip a nivel de sistema).
echo "Creando entorno virtual Python..."
sudo -u www-data python3 -m venv $VENV_PATH

# App Flask de ejemplo, solo para confirmar que el reverse proxy y el servicio
# systemd funcionan antes de subir el codigo real
echo "Creando aplicación ejemplo (Flask)..."
sudo tee $APP_PATH/app.py > /dev/null <<EOF   # sin comillas: se sustituye $PORT (el resto no tiene $)
from flask import Flask
app = Flask(__name__)

@app.route('/')
def hello():
    return 'Hello from Python App!'

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=$PORT)
EOF

# gunicorn: servidor WSGI de produccion para apps Flask/Django -- el
# "app.run()" de arriba es solo para desarrollo, no se usa en produccion
echo "Instalando Flask..."
sudo -u www-data $VENV_PATH/bin/pip install flask gunicorn

# systemd mantiene la app corriendo en segundo plano y la reinicia sola si se
# cae (Restart=always) o si el servidor reinicia (systemctl enable)
# Calcular workers dinamicamente: (CPU cores * 2) + 1 (recomendado por Gunicorn)
# En lugar de hardcoded a 4, que desperdicia recursos en VPS pequenos o
# subutiliza los grandes. Ej: 2 cores -> 5 workers, 4 cores -> 9 workers
WORKERS=$(($(nproc) * 2 + 1))

echo "Creando servicio systemd..."
sudo tee "$SYSTEMD_DIR/$APP_NAME.service" > /dev/null <<EOFSERVICE
[Unit]
Description=$APP_NAME Python Application
After=network.target

[Service]
Type=notify
User=www-data
WorkingDirectory=$APP_PATH
Environment="PATH=$VENV_PATH/bin"
ExecStart=$VENV_PATH/bin/gunicorn --workers $WORKERS --bind 127.0.0.1:$PORT app:app
Restart=always
RestartSec=10
# Sandbox basico: la app corre como www-data y es la parte expuesta a internet.
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
ProtectSystem=full
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true
StartLimitInterval=60
StartLimitBurst=3

[Install]
WantedBy=multi-user.target
EOFSERVICE

echo "Iniciando servicio..."
sudo systemctl daemon-reload   # necesario cada vez que se crea/modifica un archivo .service
service_start_enable "$APP_NAME"

# Nginx no ejecuta Python: solo reenvia ("proxy_pass") las peticiones del
# dominio publico hacia el puerto local $PORT donde escucha gunicorn
echo "Configurando Nginx (proxy)..."
# El vhost se llama como el DOMINIO (igual que en 03/06_A/06_D), no como la app.
sudo tee "$NGINX_DIR/sites-available/$DOMAIN" > /dev/null <<EOFNGINX
# vps-setup: 06_B-python $APP_NAME
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    access_log /var/log/nginx/$DOMAIN/access.log;
    error_log /var/log/nginx/$DOMAIN/error.log;

    location / {
        proxy_pass http://127.0.0.1:$PORT;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOFNGINX

# Activa el sitio, lo valida con nginx -t (si falla, lo desactiva) y recarga Nginx
enable_nginx_site "$DOMAIN"

echo ""
echo "✓ Aplicación Python configurada"
echo "Status: $(sudo systemctl is-active $APP_NAME)"
echo ""
echo "Accede a http://$DOMAIN"
echo ""
echo "Proximos pasos:"
echo "1. Configurar SSL: ./04-setup-ssl.sh $DOMAIN admin@$DOMAIN"
echo "2. Copiar tu código Python en: $APP_PATH (reemplazando este app.py de prueba)"
echo "3. Instalar dependencias: source $VENV_PATH/bin/activate && pip install -r requirements.txt"
echo "4. Reiniciar servicio: sudo systemctl restart $APP_NAME"
