# 🌐 Configuración de Dominios - VPS Initech

Guía completa para configurar y gestionar los múltiples dominios en el VPS.

---

## 📊 Dominios Principales

### 1️⃣ **conce.com**
**Tipo:** Landing Page  
**Propósito:** Sitio principal de Conce  
**Tecnología:** HTML/CSS/SVG estático  
**Estado:** 🔄 Configuración pendiente

**Archivos:**
```
/var/www/landing-page/conce.com/
└── index.html
```

**Configuración Nginx:**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name conce.com www.conce.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name conce.com www.conce.com;

    root /var/www/landing-page/conce.com;
    index index.html;

    # SSL
    ssl_certificate /etc/letsencrypt/live/conce.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/conce.com/privkey.pem;

    # Logs
    access_log /var/log/nginx/conce.com/access.log;
    error_log /var/log/nginx/conce.com/error.log;

    location / {
        try_files $uri $uri/ =404;
    }
}
```

**Pasos de configuración:**
```bash
# 1. Crear directorio
sudo mkdir -p /var/www/landing-page/conce.com
sudo cp landing-page/index.html /var/www/landing-page/conce.com/

# 2. Configurar Nginx
sudo nano /etc/nginx/sites-available/conce.com
# (Pegar configuración arriba)

# 3. Habilitar sitio
sudo ln -sf /etc/nginx/sites-available/conce.com /etc/nginx/sites-enabled/conce.com

# 4. Validar
sudo nginx -t

# 5. Recargar
sudo systemctl reload nginx

# 6. Obtener SSL
sudo certbot certify --nginx -d conce.com -d www.conce.com
```

---

### 2️⃣ **initech.cl**
**Tipo:** Landing Page  
**Propósito:** Sitio corporativo de Initech  
**Tecnología:** HTML/CSS/SVG estático  
**Estado:** 🔄 Configuración pendiente

**Archivos:**
```
/var/www/landing-page/initech.cl/
└── index.html
```

**Configuración Nginx:**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name initech.cl www.initech.cl;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name initech.cl www.initech.cl;

    root /var/www/landing-page/initech.cl;
    index index.html;

    # SSL
    ssl_certificate /etc/letsencrypt/live/initech.cl/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/initech.cl/privkey.pem;

    # Logs
    access_log /var/log/nginx/initech.cl/access.log;
    error_log /var/log/nginx/initech.cl/error.log;

    location / {
        try_files $uri $uri/ =404;
    }
}
```

**Pasos de configuración:**
```bash
sudo mkdir -p /var/www/landing-page/initech.cl
sudo cp landing-page/index.html /var/www/landing-page/initech.cl/
sudo nano /etc/nginx/sites-available/initech.cl
sudo ln -sf /etc/nginx/sites-available/initech.cl /etc/nginx/sites-enabled/initech.cl
sudo nginx -t
sudo systemctl reload nginx
sudo certbot certify --nginx -d initech.cl -d www.initech.cl
```

---

### 3️⃣ **contrastocolor.ink**
**Tipo:** Aplicación Web (Python)  
**Propósito:** Portal de colores y contrastes  
**Tecnología:** Flask + PostgreSQL  
**Estado:** 🔄 Desarrollo

**Archivos:**
```
/var/www/contrastocolor.ink/
├── app.py                  # Aplicación principal
├── config.py               # Configuración
├── requirements.txt        # Dependencias
├── static/
│   ├── css/
│   ├── js/
│   └── images/
├── templates/
│   ├── base.html
│   ├── index.html
│   └── ...
└── venv/                   # Entorno virtual
```

**Instalación:**
```bash
# 1. Crear directorio
sudo mkdir -p /var/www/contrastocolor.ink
cd /var/www/contrastocolor.ink

# 2. Entorno virtual
python3 -m venv venv
source venv/bin/activate

# 3. Instalar dependencias
pip install flask flask-cors psycopg2-binary python-dotenv gunicorn

# 4. Crear app.py
cat > app.py << 'EOF'
from flask import Flask, render_template, jsonify
import os

app = Flask(__name__)

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/contrast/<color1>/<color2>')
def calculate_contrast(color1, color2):
    # Lógica de contraste
    return jsonify({'contrast': 'resultado'})

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=8000)
EOF

# 5. Crear requirements.txt
pip freeze > requirements.txt
```

**Configuración Nginx:**
```nginx
upstream contrastocolor {
    server 127.0.0.1:8000;
}

server {
    listen 80;
    listen [::]:80;
    server_name contrastocolor.ink www.contrastocolor.ink;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name contrastocolor.ink www.contrastocolor.ink;

    # SSL
    ssl_certificate /etc/letsencrypt/live/contrastocolor.ink/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/contrastocolor.ink/privkey.pem;

    # Logs
    access_log /var/log/nginx/contrastocolor.ink/access.log;
    error_log /var/log/nginx/contrastocolor.ink/error.log;

    location / {
        proxy_pass http://contrastocolor;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /static/ {
        alias /var/www/contrastocolor.ink/static/;
    }
}
```

**Systemd Service:**
```bash
sudo tee /etc/systemd/system/contrastocolor.service > /dev/null <<'EOF'
[Unit]
Description=Contrasto Color Flask App
After=network.target

[Service]
Type=notify
User=www-data
WorkingDirectory=/var/www/contrastocolor.ink
Environment="PATH=/var/www/contrastocolor.ink/venv/bin"
ExecStart=/var/www/contrastocolor.ink/venv/bin/gunicorn \
    --workers 4 \
    --bind 127.0.0.1:8000 \
    app:app
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable contrastocolor
sudo systemctl start contrastocolor
```

---

### 4️⃣ **wikipedia.cl**
**Tipo:** Aplicación Web (PHP)  
**Propósito:** Wiki colaborativa local  
**Tecnología:** PHP 8.3 + PostgreSQL  
**Estado:** 🔄 Desarrollo

**Archivos:**
```
/var/www/wikipedia.cl/
├── public/
│   ├── index.php           # Punto de entrada
│   ├── css/
│   └── js/
├── src/
│   ├── Controller/
│   ├── Model/
│   └── View/
├── config/
│   ├── database.php
│   └── config.php
├── composer.json
└── .htaccess
```

**Configuración Nginx:**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name wikipedia.cl www.wikipedia.cl;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name wikipedia.cl www.wikipedia.cl;

    root /var/www/wikipedia.cl/public;
    index index.php;

    # SSL
    ssl_certificate /etc/letsencrypt/live/wikipedia.cl/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/wikipedia.cl/privkey.pem;

    # Logs
    access_log /var/log/nginx/wikipedia.cl/access.log;
    error_log /var/log/nginx/wikipedia.cl/error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

**Instalación:**
```bash
sudo mkdir -p /var/www/wikipedia.cl/public
cd /var/www/wikipedia.cl

# Instalar Composer (si no está)
composer create-project laravel/laravel . --prefer-dist

# O instalar paquetes existentes
composer install

# Permisos
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
```

---

## 🌍 Configuración DNS

### Registrador de Dominio

Para cada dominio, agrega estos registros **A**:

```
Tipo: A
Nombre: @
Valor: 158.69.222.245
TTL: 3600

---

Tipo: A
Nombre: www
Valor: 158.69.222.245
TTL: 3600
```

### Proveedores Específicos

#### **conce.com** (Si está en GoDaddy/Namecheap/ISP Chile)
1. Ingresa a tu panel de control
2. Busca "DNS Records" o "Manage DNS"
3. Añade registros A como arriba
4. Guarda cambios
5. Espera 5-15 minutos para propagación

#### **initech.cl** (Si está en ISP Chile)
Mismo procedimiento que conce.com

#### **contrastocolor.ink** (Si está en Namecheap/Google Domains)
1. Panel de control del registrador
2. Editar DNS
3. Añadir registros A
4. Guardar

#### **wikipedia.cl** (Si está en ISP Chile)
Mismo procedimiento general

### Verificar Propagación DNS

```bash
# Desde tu computadora
nslookup conce.com
dig conce.com

# En el VPS
nslookup conce.com 8.8.8.8
```

**Salida esperada:**
```
conce.com    A    158.69.222.245
```

---

## 🔐 SSL/HTTPS - Certificados

### Obtener Certificados

**Para cada dominio:**
```bash
sudo certbot certify --nginx \
    -d conce.com \
    -d www.conce.com \
    --email admin@conce.com \
    --agree-tos \
    --non-interactive

sudo certbot certify --nginx \
    -d initech.cl \
    -d www.initech.cl \
    --email admin@initech.cl \
    --agree-tos \
    --non-interactive

sudo certbot certify --nginx \
    -d contrastocolor.ink \
    -d www.contrastocolor.ink \
    --email admin@contrastocolor.ink \
    --agree-tos \
    --non-interactive

sudo certbot certify --nginx \
    -d wikipedia.cl \
    -d www.wikipedia.cl \
    --email admin@wikipedia.cl \
    --agree-tos \
    --non-interactive
```

### Renovación Automática

```bash
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
sudo certbot renew --dry-run
```

### Ver Certificados

```bash
sudo certbot certificates
```

---

## 📋 Checklist por Dominio

### conce.com
- [ ] Agregar registros A en DNS
- [ ] Esperar propagación DNS
- [ ] Crear directorio `/var/www/landing-page/conce.com/`
- [ ] Copiar index.html
- [ ] Configurar Nginx
- [ ] Obtener SSL con Certbot
- [ ] Probar acceso a https://conce.com

### initech.cl
- [ ] Agregar registros A en DNS
- [ ] Esperar propagación DNS
- [ ] Crear directorio `/var/www/landing-page/initech.cl/`
- [ ] Copiar index.html
- [ ] Configurar Nginx
- [ ] Obtener SSL con Certbot
- [ ] Probar acceso a https://initech.cl

### contrastocolor.ink
- [ ] Agregar registros A en DNS
- [ ] Crear aplicación Flask
- [ ] Instalar dependencias
- [ ] Configurar Nginx (upstream)
- [ ] Crear systemd service
- [ ] Obtener SSL con Certbot
- [ ] Probar acceso a https://contrastocolor.ink

### wikipedia.cl
- [ ] Agregar registros A en DNS
- [ ] Crear estructura PHP
- [ ] Instalar Composer
- [ ] Configurar base de datos
- [ ] Configurar Nginx (PHP-FPM)
- [ ] Obtener SSL con Certbot
- [ ] Probar acceso a https://wikipedia.cl

---

## 🔄 Cambiar Configuración de Dominio

### Cambiar servidor de un dominio

```bash
# 1. Actualizar DNS en registrador
# Cambiar A record a nueva IP

# 2. En el nuevo servidor, configurar sitio
# (Repetir los pasos de configuración de Nginx/SSL)

# 3. Esperar propagación (5-15 minutos)
```

### Cambiar propietario de dominio

Contacta al registrador para transferencia de dominio.

---

## 📊 Status de Dominios

| Dominio | DNS | Nginx | SSL | Estado |
|---------|-----|-------|-----|--------|
| conce.com | ⚠️ Pendiente | ⚠️ Pendiente | ⚠️ Pendiente | Setup |
| initech.cl | ⚠️ Pendiente | ⚠️ Pendiente | ⚠️ Pendiente | Setup |
| contrastocolor.ink | ⚠️ Pendiente | ⚠️ Pendiente | ⚠️ Pendiente | Dev |
| wikipedia.cl | ⚠️ Pendiente | ⚠️ Pendiente | ⚠️ Pendiente | Dev |

---

## 🆘 Troubleshooting

### "Domain name invalid" en Certbot
**Causa:** DNS no está propagado aún  
**Solución:** Esperar 5-15 minutos y reintentar

### "Connection refused" al acceder al dominio
**Causa:** DNS no está configurado correctamente  
**Solución:** Verificar registros A en el registrador

### "403 Forbidden"
**Causa:** Permisos incorrectos en archivos  
**Solución:** `sudo chown -R www-data:www-data /var/www/dominio`

---

**Última actualización:** 2026-09-20
**Versión:** 1.0.0
