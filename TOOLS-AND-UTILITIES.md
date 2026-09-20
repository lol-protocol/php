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
```bash
pip3 install flask          # Framework web
pip3 install fastapi        # Framework moderno
pip3 install django         # Framework robusto
pip3 install gunicorn       # WSGI HTTP Server
pip3 install psycopg2       # Driver PostgreSQL
pip3 install requests       # Cliente HTTP
pip3 install python-dotenv  # Variables de entorno
pip3 install pytest         # Framework de testing
```

**Crear entorno virtual:**
```bash
python3 -m venv /var/www/myapp/venv
source /var/www/myapp/venv/bin/activate
pip install -r requirements.txt
```

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
