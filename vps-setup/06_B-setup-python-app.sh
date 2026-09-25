#!/bin/bash
set -e

APP_NAME=${1:-"python-app"}
DOMAIN=${2:-"py.initech.cl"}
APP_PATH="/var/www/$APP_NAME"
VENV_PATH="$APP_PATH/venv"

echo "========================================"
echo "[06_B] Configurando Aplicacion Python: $APP_NAME"
echo "Dominio: $DOMAIN"
echo "========================================"
echo ""

echo "Creando directorios..."
sudo mkdir -p $APP_PATH
# Carpeta de logs propia de este dominio -- si no existe, "nginx -t" falla mas
# abajo porque Nginx no crea directorios el solo, solo los archivos dentro.
sudo mkdir -p /var/log/nginx/$DOMAIN
sudo chown -R www-data:www-data $APP_PATH   # www-data va a ejecutar la app (via systemd, ver abajo)

# Entorno virtual propio de esta app: aisla sus dependencias (Flask, etc.) del
# resto del sistema. Obligatorio en Ubuntu 24.04 (PEP 668 bloquea pip a nivel de sistema).
echo "Creando entorno virtual Python..."
sudo -u www-data python3 -m venv $VENV_PATH

# App Flask de ejemplo, solo para confirmar que el reverse proxy y el servicio
# systemd funcionan antes de subir el codigo real
echo "Creando aplicación ejemplo (Flask)..."
sudo tee $APP_PATH/app.py > /dev/null <<'EOF'
from flask import Flask
app = Flask(__name__)

@app.route('/')
def hello():
    return 'Hello from Python App!'

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=8000)
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
sudo tee /etc/systemd/system/$APP_NAME.service > /dev/null <<EOFSERVICE
[Unit]
Description=$APP_NAME Python Application
After=network.target

[Service]
Type=notify
User=www-data
WorkingDirectory=$APP_PATH
Environment="PATH=$VENV_PATH/bin"
ExecStart=$VENV_PATH/bin/gunicorn --workers $WORKERS --bind 127.0.0.1:8000 app:app
Restart=always
RestartSec=10
StartLimitInterval=60
StartLimitBurst=3

[Install]
WantedBy=multi-user.target
EOFSERVICE

echo "Iniciando servicio..."
sudo systemctl daemon-reload   # necesario cada vez que se crea/modifica un archivo .service
sudo systemctl start $APP_NAME
sudo systemctl enable $APP_NAME   # arranca automaticamente si el VPS se reinicia

# Nginx no ejecuta Python: solo reenvia ("proxy_pass") las peticiones del
# dominio publico hacia el puerto local 8000 donde escucha gunicorn
echo "Configurando Nginx (proxy)..."
sudo tee /etc/nginx/sites-available/$APP_NAME > /dev/null <<EOFNGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    access_log /var/log/nginx/$DOMAIN/access.log;
    error_log /var/log/nginx/$DOMAIN/error.log;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOFNGINX

# El enlace en sites-enabled es lo que realmente activa el sitio
sudo ln -sf /etc/nginx/sites-available/$APP_NAME /etc/nginx/sites-enabled/$APP_NAME

sudo nginx -t              # valida ANTES de recargar, para no tumbar los sitios que ya funcionan
sudo systemctl reload nginx

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
