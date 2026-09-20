# 🛠️ Herramientas y Utilidades - VPS Initech

Documento completo de todas las herramientas, dependencias y utilitarios instalados en el VPS.

---

## 📦 Gestor de Paquetes

### APT (Debian/Ubuntu)
```bash
apt-get update         # Actualizar índice de paquetes
apt-get upgrade        # Actualizar paquetes instalados
apt-get install <pkg>  # Instalar paquete
apt-get remove <pkg>   # Desinstalar paquete
apt-cache search <pkg> # Buscar paquete
```

**Configuración:**
- `/etc/apt/sources.list` - Repositorios
- `/etc/apt/sources.list.d/` - Repositorios adicionales

---

## 🎛️ Panel de Control

### CloudPanel
**Propósito:** Panel de control web gratuito (alternativa a cPanel, que requiere licencia paga) — administra Nginx, PHP, MariaDB/MySQL y SSL desde una interfaz web y por CLI (`clpctl`)

**Por qué CloudPanel y no cPanel/Virtualmin:**
- cPanel: requiere licencia paga.
- Virtualmin: quiere controlar todo el servidor (correo, DNS, base de datos) desde su propio instalador — no convive bien con configuraciones hechas a mano.
- CloudPanel: gratis (Community Edition), usa Nginx nativamente, SSL con un clic, soporta sitios PHP/Python/Node/estáticos/reverse-proxy.

⚠️ Requiere un servidor **limpio**, sin Nginx/Apache preinstalados.

**Instalación:**
```bash
./02-install-cloudpanel.sh
```

**Panel web:** `https://IP_DEL_VPS:8443` (crear el usuario admin inmediatamente después de instalar)

**CLI (`clpctl`) — comandos más usados:**
```bash
clpctl site:list                                    # Ver todos los sitios
clpctl site:add:php --domainName=... --phpVersion=8.3 --vhostTemplate=Generic --siteUser=... --siteUserPassword=...
clpctl site:add:python --domainName=... --pythonVersion=3.12 --appPort=8000 --siteUser=... --siteUserPassword=...
clpctl site:add:reverse-proxy --domainName=... --reverseProxyUrl=http://127.0.0.1:8080 --siteUser=... --siteUserPassword=...
clpctl lets-encrypt:install:certificate --domainName=... --subjectAlternativeName=www...
clpctl db:add --domainName=... --databaseName=... --databaseUserName=... --databaseUserPassword=...
```

**Estructura de sitios:**
```
/home/<siteUser>/htdocs/<dominio>/public/   # Raíz del sitio (donde van tus archivos)
```

Estos comandos ya están envueltos en los scripts `04_A`, `04_B`, `04_C` y `04_D` de `vps-setup/`.

---

## 🌐 Servidor Web

### Nginx 1.26+ *(administrado por CloudPanel)*
**Propósito:** Servidor web, reverse proxy, balanceo de carga

CloudPanel instala y gestiona Nginx internamente — no se instala aparte a mano. Estos comandos son de referencia general (para diagnóstico), no para configurarlo manualmente:

**Instalación (referencia — no ejecutar en un VPS con CloudPanel):**
```bash
sudo apt-get install nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

**Directorios importantes:**
```
/etc/nginx/
├── nginx.conf           # Configuración principal
├── sites-available/     # Configuraciones de sitios
├── sites-enabled/       # Enlaces simbólicos activos
├── conf.d/              # Configuraciones adicionales
└── snippets/            # Fragmentos reutilizables

/var/www/               # Raíz de archivos
/var/log/nginx/         # Logs
```

**Comandos útiles:**
```bash
sudo nginx -t           # Validar configuración
sudo systemctl reload nginx    # Recargar sin pausar
sudo systemctl restart nginx   # Reiniciar completamente
sudo systemctl status nginx    # Ver estado
```

**Nota:** las rutas de arriba (`/etc/nginx/sites-available/`, `/var/www/`) son las de una instalación manual de Nginx. Con CloudPanel, cada sitio vive en `/home/<siteUser>/htdocs/<dominio>/` y su configuración de Nginx se administra desde el panel — no hace falta editar `sites-available` a mano.

### Apache Tomcat 10 *(opcional)*
**Propósito:** Servidor de aplicaciones para Java (servlets, WAR) — Nginx no ejecuta Java, así que para apps Java se usa Tomcat detrás de Nginx como reverse proxy

**Instalación:**
```bash
sudo apt-get install tomcat10 tomcat10-admin
sudo systemctl start tomcat10
sudo systemctl enable tomcat10
```

**Directorios importantes:**
```
/var/lib/tomcat10/webapps/   # Despliega tus .war aquí
/etc/tomcat10/               # Configuración
/var/log/tomcat10/           # Logs
```

**Comandos útiles:**
```bash
sudo systemctl status tomcat10
sudo systemctl restart tomcat10
curl http://127.0.0.1:8080          # Prueba local (puerto por defecto)
```

**Exponerlo con Nginx bajo un dominio:**
```bash
./04_C-add-site-reverse-proxy.sh tudominio.com http://127.0.0.1:8080
```
Esto configura Nginx como reverse proxy hacia `http://127.0.0.1:8080`.

---

## 🐘 PHP 8.3 *(instalado por CloudPanel, por sitio)*

**Propósito:** Backend web dinámico

CloudPanel instala PHP-FPM automáticamente al crear un sitio PHP (`04_A-add-site-php.sh` / `clpctl site:add:php --phpVersion=8.3 ...`), y permite tener varias versiones de PHP conviviendo (una por sitio). No se instala con `apt-get` por separado.

**Extensiones instaladas por defecto (referencia):**
```bash
php8.3-common       # Librerías comunes
php8.3-fpm          # FastCGI Process Manager
php8.3-cli          # Interfaz de línea de comandos
php8.3-mysql        # Soporte MySQL
php8.3-postgresql   # Soporte PostgreSQL ⭐
php8.3-gd           # Procesamiento de imágenes
php8.3-curl         # Cliente HTTP
php8.3-json         # Soporte JSON
php8.3-zip          # Compresión ZIP
php8.3-mbstring     # Cadenas multibyte
php8.3-xml          # Procesamiento XML
php8.3-bcmath       # Aritmética de precisión arbitraria
```

**Configuración:**
```
/etc/php/8.3/
├── fpm/
│   ├── php.ini           # Configuración de PHP
│   └── pool.d/www.conf   # Pool de procesos
└── cli/
    └── php.ini           # Configuración CLI
```

**Comandos útiles:**
```bash
php -v                          # Versión de PHP
php -i                          # Información de PHP
php -r 'phpinfo();'            # Info alternativa
sudo systemctl restart php8.3-fpm    # Reiniciar FPM
```

---

## 🐍 Python 3.12

**Propósito:** Scripts y aplicaciones web

**Instalación:**
```bash
sudo apt-get install python3 python3-pip python3-venv
```

**Herramientas:**
- **pip3:** Gestor de paquetes de Python
- **venv:** Entornos virtuales

**Paquetes comunes a instalar:**

⚠️ Ubuntu 24.04 bloquea `pip install` global ("externally-managed-environment", PEP 668). Usa siempre un entorno virtual:
```bash
python3 -m venv /var/www/myapp/venv
source /var/www/myapp/venv/bin/activate

pip install flask          # Framework web
pip install fastapi        # Framework moderno
pip install django         # Framework robusto
pip install gunicorn       # WSGI HTTP Server
pip install psycopg2       # Driver PostgreSQL
pip install requests       # Cliente HTTP
pip install python-dotenv  # Variables de entorno
pip install pytest         # Framework de testing
```

**Crear entorno virtual:**
```bash
python3 -m venv /var/www/myapp/venv
source /var/www/myapp/venv/bin/activate
pip install -r requirements.txt
```

### Whisper (OpenAI) *(opcional)*
**Propósito:** Transcripción y traducción de audio a texto

**Instalación:**
```bash
./03_E-install-python-whisper.sh
```

Este script:
- Instala `ffmpeg` (requerido para decodificar audio/video)
- Crea un entorno virtual compartido en `/opt/venvs/whisper`
- Instala `openai-whisper` + librerías básicas (`requests`, `numpy`, `pandas`, `flask`, `fastapi`, `uvicorn`, `python-dotenv`, `gunicorn`)
- Deja el comando `whisper` disponible globalmente (symlink a `/usr/local/bin/whisper`)

**Uso:**
```bash
whisper audio.mp3 --model base --language Spanish
whisper video.mp4 --model small --output_format srt
```

**Modelos** (de menor a mayor precisión y tamaño — se descargan la primera vez que se usan):
```
tiny, base, small, medium, large
```

**Usar las librerías Python del venv en tus propios scripts:**
```bash
source /opt/venvs/whisper/bin/activate
python3 mi_script.py
```

**Nota de recursos:** Whisper corre en CPU en un VPS típico (sin GPU), por lo que modelos grandes (`medium`, `large`) pueden ser lentos. Para uso ligero, `base` o `small` suelen ser suficientes.

---

## 🗄️ Base de Datos

### PostgreSQL 16

**Propósito:** Base de datos relacional centralizada

**Instalación:**
```bash
sudo apt-get install postgresql postgresql-contrib
```

**Comandos útiles:**
```bash
sudo systemctl start postgresql
sudo systemctl enable postgresql
sudo systemctl status postgresql

# Conectar a PostgreSQL
sudo -u postgres psql

# Desde el cliente:
psql -U usuario -d nombre_base
```

**Comandos SQL básicos:**
```sql
-- Crear base de datos
CREATE DATABASE mi_base;

-- Crear usuario
CREATE USER mi_usuario WITH PASSWORD 'contraseña_segura';

-- Dar permisos
GRANT ALL PRIVILEGES ON DATABASE mi_base TO mi_usuario;

-- Listar bases de datos
\l

-- Conectar a una base
\c mi_base

-- Ver tablas
\dt
```

**Configuración:**
```
/etc/postgresql/16/main/
├── postgresql.conf      # Configuración principal
└── pg_hba.conf          # Configuración de acceso
```

**Backup & Restore:**
```bash
# Backup completo
sudo -u postgres pg_dump mi_base > backup.sql

# Restore
sudo -u postgres psql mi_base < backup.sql

# Backup con compresión
sudo -u postgres pg_dump -Fc mi_base > backup.dump
```

### MariaDB/MySQL *(incluido en CloudPanel)*

**Propósito:** Base de datos relacional compatible con MySQL — para software que exija específicamente ese motor

Ya no se instala aparte: CloudPanel lo instala como parte de `02-install-cloudpanel.sh` (eliges MySQL 8.0, MariaDB 11.4 o MariaDB 10.11 durante ese instalador).

**Crear una base de datos para un sitio (CLI):**
```bash
sudo clpctl db:add \
    --domainName=tudominio.com \
    --databaseName=mi_base \
    --databaseUserName=mi_usuario \
    --databaseUserPassword='contraseña_segura'
```

O desde el panel web: tu sitio → pestaña "Databases".

**Comandos útiles:**
```bash
sudo mysql                          # Conectar como root
mysql -u usuario -p nombre_base     # Conectar como usuario normal
```

**Comandos SQL básicos:**
```sql
SHOW DATABASES;
USE mi_base;
SHOW TABLES;
```

**Backup & Restore:**
```bash
mysqldump -u usuario -p mi_base > backup.sql
mysql -u usuario -p mi_base < backup.sql
```

**Extensión PHP:** cada sitio PHP creado con CloudPanel ya incluye soporte MySQL — no requiere pasos adicionales.

---

## 🔒 SSL/TLS

### Let's Encrypt (integrado en CloudPanel)

**Propósito:** Certificados SSL/HTTPS automáticos y gratuitos

CloudPanel trae Let's Encrypt integrado — no se instala Certbot aparte. Se pide desde el panel web o por CLI:

```bash
./04_D-install-ssl-cloudpanel.sh dominio.com
```

Equivalente directo con `clpctl`:
```bash
sudo clpctl lets-encrypt:install:certificate \
    --domainName=dominio.com \
    --subjectAlternativeName=www.dominio.com
```

**Renovación:** automática, gestionada por CloudPanel — se verifica desde el panel, sección SSL/TLS de cada sitio.

---

## 🔧 Utilidades del Sistema

### Build Essentials
```bash
sudo apt-get install build-essential
```
Incluye: gcc, g++, make, etc. Para compilar software desde código.

### cURL & wget
```bash
sudo apt-get install curl wget
```
Descarga archivos y hace peticiones HTTP.

### Git
```bash
sudo apt-get install git
```
Control de versiones. Comandos básicos:
```bash
git clone <repo>
git add .
git commit -m "mensaje"
git push origin <rama>
```

### htop
```bash
sudo apt-get install htop
```
Monitor de procesos interactivo (mejor que `top`).

### nano / vim
```bash
sudo apt-get install nano vim
```
Editores de texto para el terminal.

### openssh-server
```bash
sudo apt-get install openssh-server
```
Acceso SSH al servidor.

### net-tools
```bash
sudo apt-get install net-tools
```
Herramientas de red: `ifconfig`, `netstat`, etc.

### ntp (Network Time Protocol)
```bash
sudo apt-get install ntp
```
Sincroniza la hora del sistema.

---

## 🔐 Seguridad

### UFW (Uncomplicated Firewall)
```bash
sudo ufw enable
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Abrir puertos
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS

# Ver estado
sudo ufw status
```

### Fail2Ban (futuro)
```bash
sudo apt-get install fail2ban
```
Protege contra intentos de acceso no autorizados.

### SSH Key-Based Auth
```bash
# Generar llave (en cliente)
ssh-keygen -t rsa -b 4096 -f ~/.ssh/initech

# Copiar a servidor
ssh-copy-id -i ~/.ssh/initech.pub usuario@servidor
```

---

## 📊 Monitoreo & Logging

### Syslog (predeterminado)
```bash
# Ver logs del sistema
tail -f /var/log/syslog
```

### Nginx Logs
```bash
# Access log
tail -f /var/log/nginx/dominio.com/access.log

# Error log
tail -f /var/log/nginx/dominio.com/error.log
```

### Journalctl
```bash
# Ver logs de un servicio
journalctl -u nginx -f
journalctl -u php8.3-fpm -f
journalctl -u postgresql -f

# Últimas líneas
journalctl -n 100
```

---

## 📦 Composer (PHP)

**Propósito:** Gestor de dependencias de PHP

**Instalación:**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Uso:**
```bash
composer install        # Instalar dependencias
composer update         # Actualizar paquetes
composer require <pkg>  # Agregar paquete
```

---

## 🗂️ Gestor de Archivos

### Comandos básicos
```bash
ls -la                  # Listar archivos
cd /directorio          # Cambiar directorio
pwd                     # Ubicación actual
mkdir nombre            # Crear directorio
rm archivo              # Eliminar archivo
rm -rf directorio       # Eliminar directorio
cp -r origen destino    # Copiar
mv origen destino       # Mover/renombrar
find / -name "archivo"  # Buscar archivo
```

### Permisos
```bash
chmod 755 archivo           # rwxr-xr-x (ejecutable)
chmod 644 archivo           # rw-r--r-- (archivo)
chown usuario:grupo archivo # Cambiar propietario
```

---

## 🚀 Instalación Rápida (script único)

⚠️ Nginx, PHP y Certbot **no se instalan con `apt-get` directamente** — CloudPanel los necesita instalar él mismo en un servidor limpio. El equivalente al "one-liner" es:

```bash
cd vps-setup
chmod +x *.sh
./install-all.sh tudominio.com
```

Esto corre `01-system-update.sh` (utilidades base) → `02-install-cloudpanel.sh` (Nginx+PHP+MariaDB+SSL) → `04_A-add-site-php.sh` → `05-deploy-landing-page.sh`.

**Extras opcionales, uno por uno:**
```bash
./03_A-install-java.sh
./03_B-install-python.sh
./03_C-install-postgresql.sh
./03_D-install-tomcat.sh            # requiere 03_A
./03_E-install-python-whisper.sh    # requiere 03_B
```

MySQL/MariaDB ya no se instala por separado — viene incluido con CloudPanel (`02-install-cloudpanel.sh`).

---

## 📋 Checklist de Instalación

- [ ] APT actualizado (`01-system-update.sh`)
- [ ] CloudPanel instalado y usuario admin creado (`02-install-cloudpanel.sh`)
- [ ] Sitio creado para el dominio (`04_A-add-site-php.sh`)
- [ ] Landing page desplegada (`05-deploy-landing-page.sh`)
- [ ] DNS del dominio apuntando al VPS
- [ ] Certificado SSL generado (`04_D-install-ssl-cloudpanel.sh`)
- [ ] (Opcional) Java, PostgreSQL, Tomcat, Whisper instalados según necesidad

---

## 📚 Documentación Oficial

- **Nginx:** https://nginx.org/en/docs/
- **PHP:** https://www.php.net/docs.php
- **Python:** https://docs.python.org/3/
- **PostgreSQL:** https://www.postgresql.org/docs/
- **Let's Encrypt:** https://letsencrypt.org/docs/
- **Ubuntu:** https://ubuntu.com/server/docs

---

**Última actualización:** 2026-09-20
**Versión:** 1.0.0
