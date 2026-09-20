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

## 🎛️ Panel de Administración

### Webmin *(opcional)*
**Propósito:** GUI web para administrar el servidor sin dejar de usar comandos Unix nativos

**Por qué Webmin y no cPanel/Virtualmin/CloudPanel:**
- cPanel: requiere licencia paga.
- Virtualmin/CloudPanel: administran el servidor con su propia base de datos de sitios/usuarios y su propio CLI — si el panel se cae o se desinstala, hay que reconstruir a mano su estructura propietaria.
- Webmin: **edita los archivos de configuración nativos** (`/etc/nginx/`, `/etc/letsencrypt/`, crontab, `/etc/passwd`, reglas de `ufw`). Todo lo que haces desde la UI es exactamente lo mismo que harías por SSH con `nginx`, `certbot`, `systemctl`. Si se desinstala, nada deja de funcionar.

**Instalación:**
```bash
./02_J-install-webmin.sh
```

**Panel web:** `https://IP_DEL_VPS:10000` (mismo usuario/contraseña que SSH)

**Módulos más útiles:**
- **Nginx Webserver** — edita server blocks, SSL, proxy, gzip sobre `/etc/nginx/` directamente
- **Users and Groups** — administra cuentas del sistema
- **Scheduled Cron Jobs** — cron visual
- **Firewall (UFW)** — reglas de puertos
- **Software Package Updates** — `apt update/upgrade` desde la web
- **File Manager** — navega y edita archivos del servidor

**Comandos útiles:**
```bash
sudo systemctl status webmin
sudo systemctl restart webmin
```

---

## 🌐 Servidor Web

### Nginx 1.26+
**Propósito:** Servidor web, reverse proxy, balanceo de carga

**Instalación:**
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

**Archivos de configuración creados:**
- `/etc/nginx/sites-available/conce.com`
- `/etc/nginx/sites-available/initech.cl`
- `/etc/nginx/sites-available/contrastocolor.ink`
- `/etc/nginx/sites-available/wikipedia.cl`

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
./06_D-setup-tomcat-app.sh tudominio.com mi-app
```
Esto configura Nginx como reverse proxy hacia `http://127.0.0.1:8080`.

---

## 🐘 PHP 8.3

**Propósito:** Backend web dinámico

**Instalación:**
```bash
sudo apt-get install php8.3 php8.3-fpm php8.3-cli
```

**Extensiones instaladas:**
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
./02_I-install-python-whisper.sh
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

### MariaDB *(opcional)*

**Propósito:** Base de datos relacional compatible con MySQL — para software que exija específicamente ese motor

**Instalación:**
```bash
sudo apt-get install mariadb-server mariadb-client
```

**Endurecer la instalación (recomendado, correr una sola vez):**
```bash
sudo mysql_secure_installation
```

**Comandos útiles:**
```bash
sudo systemctl start mariadb
sudo systemctl enable mariadb
sudo systemctl status mariadb

# Conectar
sudo mysql
mysql -u usuario -p nombre_base
```

**Comandos SQL básicos:**
```sql
CREATE DATABASE mi_base;
CREATE USER 'mi_usuario'@'localhost' IDENTIFIED BY 'contraseña_segura';
GRANT ALL PRIVILEGES ON mi_base.* TO 'mi_usuario'@'localhost';
FLUSH PRIVILEGES;

SHOW DATABASES;
USE mi_base;
SHOW TABLES;
```

**Backup & Restore:**
```bash
mysqldump -u usuario -p mi_base > backup.sql
mysql -u usuario -p mi_base < backup.sql
```

**Extensión PHP:** ya incluida en `02_B-install-php.sh` (`php8.3-mysql`) — no requiere pasos adicionales para que PHP se conecte.

---

## 🔒 SSL/TLS

### Certbot + Let's Encrypt

**Propósito:** Certificados SSL/HTTPS automáticos y gratuitos

**Instalación:**
```bash
sudo apt-get install certbot python3-certbot-nginx
```

**Obtener certificado:**
```bash
sudo certbot certify --nginx \
  -d dominio.com \
  -d www.dominio.com \
  --email admin@dominio.com
```

**Renovación automática:**
```bash
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer

# Probar renovación (sin aplicar)
sudo certbot renew --dry-run
```

**Comandos útiles:**
```bash
sudo certbot certificates               # Ver certificados
sudo certbot renew                      # Renovar todos
sudo certbot delete --cert-name dominio # Eliminar certificado
```

**Ubicación de certificados:**
```
/etc/letsencrypt/live/dominio.com/
├── fullchain.pem    # Certificado completo
├── privkey.pem      # Clave privada
├── cert.pem         # Solo certificado
└── chain.pem        # Cadena intermedia
```

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

## 🚀 Instalación Rápida (One-Liner)

**Instalar todo de una vez:**
```bash
sudo apt-get update && \
sudo apt-get upgrade -y && \
sudo apt-get install -y \
  build-essential curl wget git htop nano vim openssh-server \
  openjdk-21-jdk \
  nginx \
  php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-mysql \
  php8.3-postgresql php8.3-gd php8.3-curl php8.3-json php8.3-zip \
  python3 python3-pip python3-venv \
  postgresql postgresql-contrib \
  certbot python3-certbot-nginx \
  ufw ntp
```

---

## 📋 Checklist de Instalación

- [ ] APT actualizado
- [ ] Nginx instalado y corriendo
- [ ] PHP 8.3 con extensiones
- [ ] Python 3 con pip
- [ ] PostgreSQL instalado y corriendo
- [ ] Certbot y Let's Encrypt
- [ ] SSH configurado
- [ ] UFW habilitado
- [ ] Certificados SSL generados
- [ ] Dominios configurados
- [ ] Webmin instalado (opcional)

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
