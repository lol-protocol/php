# 🌐 Configuración de Dominios - VPS Initech

Guía completa para configurar y gestionar los múltiples dominios en el VPS, usando **CloudPanel** (ver `ARCHITECTURE.md` y `vps-setup/README.md` para por qué CloudPanel y no cPanel/Virtualmin).

Todos los pasos de Nginx, vhosts y SSL de cada dominio se hacen a través de CloudPanel (`clpctl` o el panel web en `:8443`) — ya no se edita `/etc/nginx/` a mano.

---

## 📊 Dominios Principales

### 1️⃣ **conce.com**
**Tipo:** Landing Page
**Propósito:** Sitio principal de Conce
**Tecnología:** HTML/CSS/SVG estático
**Estado:** 🔄 Configuración pendiente

**Archivos:** `/home/conce/htdocs/conce.com/public/`

**Pasos de configuración:**
```bash
cd vps-setup
./04_A-add-site-php.sh conce.com conce
./05-deploy-landing-page.sh conce.com conce
# (después de que el DNS propague)
./04_D-install-ssl-cloudpanel.sh conce.com
```

---

### 2️⃣ **initech.cl / initech.fun**
**Tipo:** Landing Page
**Propósito:** Sitio corporativo de Initech
**Tecnología:** HTML/CSS/SVG estático
**Estado:** 🔄 Configuración pendiente

**Archivos:** `/home/initech/htdocs/initech.fun/public/`

**Pasos de configuración:**
```bash
./04_A-add-site-php.sh initech.fun initech
./05-deploy-landing-page.sh initech.fun initech
./04_D-install-ssl-cloudpanel.sh initech.fun
```

---

### 3️⃣ **contrastocolor.ink**
**Tipo:** Aplicación Web (Python)
**Propósito:** Portal de colores y contrastes
**Tecnología:** Flask + PostgreSQL
**Estado:** 🔄 Desarrollo

**Archivos:** `/home/contrastocolor/htdocs/contrastocolor.ink/`

**Crear el sitio (CloudPanel):**
```bash
./03_B-install-python.sh   # si aun no esta instalado
./04_B-add-site-python.sh contrastocolor.ink contrastocolor 8000
```

**Subir la app** (por SFTP/SSH con el usuario `contrastocolor`) dentro de `/home/contrastocolor/htdocs/contrastocolor.ink/`:

```python
# app.py
from flask import Flask, render_template, jsonify

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
```

**Entorno virtual y dependencias:**
```bash
cd /home/contrastocolor/htdocs/contrastocolor.ink
python3 -m venv venv
source venv/bin/activate
pip install flask flask-cors psycopg2-binary python-dotenv gunicorn
pip freeze > requirements.txt
deactivate
```

**Mantenerla corriendo (systemd, apuntando al puerto 8000 que ya espera CloudPanel):**
```bash
sudo tee /etc/systemd/system/contrastocolor.service > /dev/null <<'EOF'
[Unit]
Description=Contrasto Color Flask App
After=network.target

[Service]
Type=notify
User=contrastocolor
WorkingDirectory=/home/contrastocolor/htdocs/contrastocolor.ink
Environment="PATH=/home/contrastocolor/htdocs/contrastocolor.ink/venv/bin"
ExecStart=/home/contrastocolor/htdocs/contrastocolor.ink/venv/bin/gunicorn \
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

```bash
./04_D-install-ssl-cloudpanel.sh contrastocolor.ink
```

---

### 4️⃣ **wikipedia.cl**
**Tipo:** Aplicación Web (PHP)
**Propósito:** Wiki colaborativa local
**Tecnología:** PHP 8.3 + PostgreSQL
**Estado:** 🔄 Desarrollo

**Archivos:** `/home/wikipedia/htdocs/wikipedia.cl/public/`

**Crear el sitio (CloudPanel):**
```bash
./04_A-add-site-php.sh wikipedia.cl wikipedia
```

**Instalar el código** (por SFTP/SSH con el usuario `wikipedia`):
```bash
cd /home/wikipedia/htdocs/wikipedia.cl
composer create-project laravel/laravel . --prefer-dist
# o: composer install   (si ya tienes el proyecto)
```

**Base de datos** (MariaDB/MySQL vía CloudPanel, o PostgreSQL aparte — ver más abajo):
```bash
sudo clpctl db:add \
    --domainName=wikipedia.cl \
    --databaseName=wikipedia \
    --databaseUserName=wikipedia_app \
    --databaseUserPassword='contraseña_segura'
```

```bash
./04_D-install-ssl-cloudpanel.sh wikipedia.cl
```

---

## 🌍 Configuración DNS

### Registrador de Dominio

Para cada dominio, agrega estos registros **A** (o cambia los nameservers, según lo que te permita el registrador — ver `VPS-SETUP-GUIDE.md` para el caso de Network Solutions):

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

### Verificar Propagación DNS

```bash
nslookup conce.com
dig conce.com
```

**Salida esperada:**
```
conce.com    A    158.69.222.245
```

---

## 🔐 SSL/HTTPS — Certificados (vía CloudPanel)

**Para cada dominio, una vez que el DNS ya resuelve:**
```bash
./04_D-install-ssl-cloudpanel.sh conce.com
./04_D-install-ssl-cloudpanel.sh initech.fun
./04_D-install-ssl-cloudpanel.sh contrastocolor.ink
./04_D-install-ssl-cloudpanel.sh wikipedia.cl
```

Cada script verifica el DNS antes de pedir el certificado y usa `clpctl lets-encrypt:install:certificate` internamente. La renovación es automática (la gestiona CloudPanel).

**Ver certificados:** panel web (`https://IP_DEL_VPS:8443`) → sitio → SSL/TLS, o `sudo clpctl site:list`.

---

## 📋 Checklist por Dominio

### conce.com / initech.fun (landing pages)
- [ ] Agregar registros A en DNS
- [ ] Esperar propagación DNS
- [ ] `04_A-add-site-php.sh` (crea el sitio en CloudPanel)
- [ ] `05-deploy-landing-page.sh` (copia el HTML)
- [ ] `04_D-install-ssl-cloudpanel.sh`
- [ ] Probar acceso a https://el-dominio

### contrastocolor.ink
- [ ] Agregar registros A en DNS
- [ ] `03_B-install-python.sh`
- [ ] `04_B-add-site-python.sh`
- [ ] Subir la app Flask + crear el servicio systemd
- [ ] `04_D-install-ssl-cloudpanel.sh`
- [ ] Probar acceso a https://contrastocolor.ink

### wikipedia.cl
- [ ] Agregar registros A en DNS
- [ ] `04_A-add-site-php.sh`
- [ ] Instalar código (Composer)
- [ ] Crear base de datos (`clpctl db:add`)
- [ ] `04_D-install-ssl-cloudpanel.sh`
- [ ] Probar acceso a https://wikipedia.cl

---

## 🔄 Cambiar Configuración de Dominio

### Cambiar servidor de un dominio

```bash
# 1. Actualizar DNS en registrador (cambiar el registro A a la nueva IP)
# 2. En el nuevo servidor, crear el sitio con los scripts 04_*
# 3. Esperar propagación (15 min - 24h)
```

### Cambiar propietario de dominio

Contacta al registrador para transferencia de dominio.

---

## 📊 Status de Dominios

| Dominio | DNS | Sitio CloudPanel | SSL | Estado |
|---------|-----|-------------------|-----|--------|
| conce.com | ⚠️ Pendiente | ⚠️ Pendiente | ⚠️ Pendiente | Setup |
| initech.fun | ⚠️ Pendiente | ⚠️ Pendiente | ⚠️ Pendiente | Setup |
| contrastocolor.ink | ⚠️ Pendiente | ⚠️ Pendiente | ⚠️ Pendiente | Dev |
| wikipedia.cl | ⚠️ Pendiente | ⚠️ Pendiente | ⚠️ Pendiente | Dev |

---

## 🆘 Troubleshooting

### El certificado SSL falla
**Causa:** DNS no está propagado aún
**Solución:** Esperar y reintentar `./04_D-install-ssl-cloudpanel.sh dominio.com`

### "Connection refused" al acceder al dominio
**Causa:** DNS no está configurado correctamente
**Solución:** Verificar registros A en el registrador (`nslookup dominio.com`)

### "403 Forbidden" / "404 Not Found"
**Causa:** Archivos no están en la carpeta correcta o permisos incorrectos
**Solución:** Verificar `/home/SITE_USER/htdocs/dominio.com/public/` y sus permisos (el usuario dueño debe ser `SITE_USER`, no `www-data`)

---

**Última actualización:** 2026-09-20
**Versión:** 2.0.0
