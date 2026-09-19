#!/bin/bash
set -e

APP_NAME=${1:-"python-app"}
DOMAIN=${2:-"py.initech.cl"}
APP_PATH="/var/www/$APP_NAME"
VENV_PATH="$APP_PATH/venv"

echo "========================================"
echo "Configurando Aplicación Python"
echo "Nombre: $APP_NAME"
echo "Dominio: $DOMAIN"
echo "========================================"
echo ""

# Create directories
echo "Creando directorios..."
sudo mkdir -p $APP_PATH
sudo chown -R www-data:www-data $APP_PATH

# Create virtual environment
echo "Creando entorno virtual Python..."
sudo -u www-data python3 -m venv $VENV_PATH

# Create a simple Flask example
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

# Install Flask
echo "Instalando Flask..."
sudo -u www-data $VENV_PATH/bin/pip install flask gunicorn

# Create systemd service
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
ExecStart=$VENV_PATH/bin/gunicorn --workers 4 --bind 127.0.0.1:8000 app:app
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOFSERVICE

# Start service
echo "Iniciando servicio..."
sudo systemctl daemon-reload
sudo systemctl start $APP_NAME
sudo systemctl enable $APP_NAME

# Create Nginx config
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

# Enable site
sudo ln -sf /etc/nginx/sites-available/$APP_NAME /etc/nginx/sites-enabled/$APP_NAME

# Test and reload
sudo nginx -t
sudo systemctl reload nginx

echo ""
echo "✓ Aplicación Python configurada"
echo "Status: $(sudo systemctl is-active $APP_NAME)"
echo ""
echo "Accede a http://$DOMAIN"
echo ""
echo "Proximos pasos:"
echo "1. Configurar SSL: certbot certify --nginx -d $DOMAIN"
echo "2. Copiar tu código Python en: $APP_PATH"
echo "3. Instalar dependencias: source $VENV_PATH/bin/activate && pip install -r requirements.txt"
echo "4. Reiniciar servicio: sudo systemctl restart $APP_NAME"
