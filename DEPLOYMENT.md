# 🚀 Guía de Despliegue - VPS Initech

Guía paso a paso para desplegar todo el sistema en el VPS.

---

## 📋 Pre-requisitos

- ✅ VPS Ubuntu 24.04 LTS activo (158.69.222.245)
- ✅ Acceso SSH al VPS (usuario: Ubuntu, contraseña: DmSdQKUQZQrp)
- ✅ Dominios registrados (conce.com, initech.cl, contrastocolor.ink, wikipedia.cl)
- ✅ Acceso a panel DNS de cada dominio
- ✅ Email para certificados SSL (ej: admin@domain.com)

---

## 🔧 Fase 1: Setup Inicial del VPS

### Paso 1: Conectarse al VPS

```bash
ssh ubuntu@158.69.222.245
# Contraseña: DmSdQKUQZQrp
```

### Paso 2: Descargar Scripts de Instalación

```bash
cd /tmp
git clone https://github.com/lol-protocol/php.git
cd php/vps-setup
```

### Paso 3: Ejecutar Instalación Base

**Opción A: Automática (Recomendado)**
```bash
chmod +x install-all.sh
./install-all.sh conce.com admin@conce.com
```

**Opción B: Manual (Más Control)**
```bash
chmod +x *.sh

# 01: prerequisito único
./01-system-update.sh

# 02_A..02_F, 02_J: instalaciones independientes entre sí (cualquier orden)
./02_A-install-java.sh
./02_B-install-php.sh
./02_C-install-python.sh
./02_D-install-postgresql.sh
./02_E-install-nginx.sh
./02_F-install-certbot.sh
./02_J-install-webmin.sh   # Panel de administración (opcional): https://IP:10000

# 03: configurar Nginx (cambiar dominio según necesidad)
./03-configure-nginx-site.sh conce.com

# Esperar propagación DNS antes de siguiente paso
# ...

# 04: configurar SSL
./04-setup-ssl.sh conce.com admin@conce.com

# 05: desplegar landing page
./05-deploy-landing-page.sh
```

### Paso 4: Verificar Instalación

```bash
# Verificar servicios
sudo systemctl status nginx
sudo systemctl status php8.3-fpm
sudo systemctl status postgresql
sudo systemctl status webmin 2>/dev/null   # si se instaló

# Verificar software instalado
java -version
php -v
python3 --version
psql --version
nginx -v
```

---

## 🌐 Fase 2: Configurar Dominios

### Paso 1: Configurar DNS para cada dominio

**En tu registrador de dominio (GoDaddy, Namecheap, ISP Chile, etc.):**

Para cada dominio (conce.com, initech.cl, etc.):

1. Accede al panel de control
2. Busca "DNS" o "Nameservers"
3. Agrega registros **A**:

```
Tipo: A
Nombre: @ (o déjalo en blanco)
Valor: 158.69.222.245
TTL: 3600 (o la que sugiera el registrador)

---

Tipo: A
Nombre: www
Valor: 158.69.222.245
TTL: 3600
```

4. Guarda los cambios
5. **ESPERA 5-15 MINUTOS** para que se propague

### Paso 2: Verificar propagación DNS

```bash
# En tu computadora (no en el VPS)
nslookup conce.com
dig conce.com

# Salida esperada:
# conce.com.  3600  IN  A  158.69.222.245
```

### Paso 3: Configurar sitios en Nginx

En el VPS, para cada dominio:

```bash
# Landing page - conce.com
sudo nano /etc/nginx/sites-available/conce.com
# Configurar según DOMAINS.md

sudo ln -sf /etc/nginx/sites-available/conce.com /etc/nginx/sites-enabled/conce.com

# Landing page - initech.cl
sudo nano /etc/nginx/sites-available/initech.cl
sudo ln -sf /etc/nginx/sites-available/initech.cl /etc/nginx/sites-enabled/initech.cl

# Validar
sudo nginx -t
sudo systemctl reload nginx
```

### Paso 4: Obtener Certificados SSL

```bash
# Para conce.com
sudo certbot certify --nginx \
    -d conce.com -d www.conce.com \
    --email admin@conce.com \
    --agree-tos \
    --non-interactive

# Para initech.cl
sudo certbot certify --nginx \
    -d initech.cl -d www.initech.cl \
    --email admin@initech.cl \
    --agree-tos \
    --non-interactive

# Verificar
sudo certbot certificates
```

### Paso 5: Probar acceso

Desde tu navegador:
```
✅ https://conce.com
✅ https://www.conce.com
✅ https://initech.cl
✅ https://www.initech.cl
```

---

## 💻 Fase 3: Configurar Aplicaciones

### 3.1: Aplicación Python (contrastocolor.ink)

En el VPS:

```bash
# Crear directorio
sudo mkdir -p /var/www/contrastocolor.ink
cd /var/www/contrastocolor.ink

# Crear entorno virtual
python3 -m venv venv
source venv/bin/activate

# Instalar dependencias
pip install flask flask-cors psycopg2-binary python-dotenv gunicorn

# Crear app.py (ver DOMAINS.md para contenido)
nano app.py

# Crear requirements.txt
pip freeze > requirements.txt

# Desactivar venv
deactivate

# Cambiar permisos
sudo chown -R www-data:www-data /var/www/contrastocolor.ink
```

Configurar Nginx (ver DOMAINS.md) y SSL:

```bash
sudo nano /etc/nginx/sites-available/contrastocolor.ink
sudo ln -sf /etc/nginx/sites-available/contrastocolor.ink /etc/nginx/sites-enabled/contrastocolor.ink
sudo nginx -t
sudo systemctl reload nginx

# Después que DNS propague:
sudo certbot certify --nginx -d contrastocolor.ink
```

Crear servicio systemd:

```bash
sudo nano /etc/systemd/system/contrastocolor.service
# (Copiar contenido de DOMAINS.md)

sudo systemctl daemon-reload
sudo systemctl enable contrastocolor
sudo systemctl start contrastocolor
sudo systemctl status contrastocolor
```

### 3.2: Aplicación PHP (wikipedia.cl)

En el VPS:

```bash
# Crear directorio
sudo mkdir -p /var/www/wikipedia.cl/public
cd /var/www/wikipedia.cl

# Instalar Composer (si no está)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Crear proyecto (o copiar código existente)
composer create-project laravel/laravel . --prefer-dist

# O instalar dependencias si tienes un proyecto:
composer install

# Permisos
sudo chown -R www-data:www-data /var/www/wikipedia.cl
sudo chmod -R 755 /var/www/wikipedia.cl
```

Configurar Nginx (ver DOMAINS.md) y SSL:

```bash
sudo nano /etc/nginx/sites-available/wikipedia.cl
sudo ln -sf /etc/nginx/sites-available/wikipedia.cl /etc/nginx/sites-enabled/wikipedia.cl
sudo nginx -t
sudo systemctl reload nginx

# Después que DNS propague:
sudo certbot certify --nginx -d wikipedia.cl
```

---

## 🗄️ Fase 4: Configurar Base de Datos

En el VPS:

```bash
# Conectar como usuario postgres
sudo -u postgres psql

# En PostgreSQL:
CREATE DATABASE mi_base_datos;
CREATE USER mi_usuario WITH PASSWORD 'contraseña_segura';
GRANT ALL PRIVILEGES ON DATABASE mi_base_datos TO mi_usuario;

\q
```

Desde aplicaciones PHP/Python:

```php
// PHP
$dbconn = pg_connect("host=localhost user=mi_usuario password=contraseña_segura dbname=mi_base_datos");
```

```python
# Python
import psycopg2
conn = psycopg2.connect(
    host="localhost",
    user="mi_usuario",
    password="contraseña_segura",
    database="mi_base_datos"
)
```

---

## 📊 Fase 5: Verificación Final

### Checklist de Verificación

```bash
# 1. Servicios corriendo
sudo systemctl status nginx
sudo systemctl status php8.3-fpm
sudo systemctl status postgresql
sudo systemctl status contrastocolor

# 2. Certificados SSL
sudo certbot certificates

# 3. Archivos en su lugar
ls -la /var/www/landing-page/
ls -la /var/www/contrastocolor.ink/
ls -la /var/www/wikipedia.cl/

# 4. Permisos correctos
sudo ls -la /var/www/ | grep www-data

# 5. Logs sin errores
sudo tail -20 /var/log/nginx/conce.com/error.log
sudo tail -20 /var/log/nginx/contrastocolor.ink/error.log

# 6. Conectividad a BD
sudo -u postgres psql -l
```

### Pruebas en el Navegador

Desde tu computadora:

```
✅ https://conce.com (landing page)
✅ https://initech.cl (landing page)
✅ https://contrastocolor.ink (app Python)
✅ https://wikipedia.cl (app PHP)
```

Todos deben:
- Cargar correctamente
- Mostrar HTTPS (candado verde)
- Tener certificado válido

---

## 🔄 Mantenimiento Diario

### Monitoreo de Logs

```bash
# Ver logs en tiempo real
sudo tail -f /var/log/nginx/conce.com/access.log
sudo tail -f /var/log/nginx/conce.com/error.log

# Buscar errores
sudo grep -i error /var/log/nginx/*/error.log | head -20
```

### Chequeos de Salud

```bash
# Estado de servicios
sudo systemctl status nginx php8.3-fpm postgresql

# Uso de recursos
htop

# Espacio en disco
df -h

# Conectividad bases de datos
sudo -u postgres psql -l
```

### Limpiar Logs (opcional)

```bash
# Rotar logs manualmente
sudo logrotate -f /etc/logrotate.conf

# O limpiar logs viejos (cuidado!)
sudo find /var/log/nginx -name "*.log" -mtime +30 -delete
```

---

## 🔄 Mantenimiento Semanal

### Actualizar Sistema

```bash
sudo apt-get update
sudo apt-get upgrade -y
```

### Renovar Certificados SSL

```bash
# Probar renovación
sudo certbot renew --dry-run

# Renovar (generalmente automático)
sudo certbot renew
```

### Backup de Base de Datos

```bash
# Backup completo
sudo -u postgres pg_dump -Fc mi_base_datos > ~/backups/mi_base_datos_$(date +%Y%m%d).dump

# O todo a la vez
sudo -u postgres pg_dumpall > ~/backups/all_databases_$(date +%Y%m%d).sql
```

---

## 🆘 Troubleshooting

### Landing page no carga

```bash
# 1. Verificar que Nginx esté corriendo
sudo systemctl status nginx

# 2. Verificar archivos
ls -la /var/www/landing-page/conce.com/

# 3. Chequear configuración
sudo nginx -t

# 4. Ver error log
sudo tail /var/log/nginx/conce.com/error.log
```

### App Python no responde

```bash
# 1. Verificar servicio
sudo systemctl status contrastocolor

# 2. Ver logs del servicio
sudo journalctl -u contrastocolor -n 50

# 3. Reiniciar
sudo systemctl restart contrastocolor

# 4. Probar conexión
curl http://127.0.0.1:8000
```

### Base de datos no responde

```bash
# 1. Verificar PostgreSQL
sudo systemctl status postgresql

# 2. Conectar
sudo -u postgres psql

# 3. Ver bases de datos
\l

# 4. Si hay problemas, reiniciar
sudo systemctl restart postgresql
```

### SSL no funciona

```bash
# 1. Verificar certificado
sudo certbot certificates

# 2. Forzar renovación
sudo certbot renew --force-renewal

# 3. Recargar Nginx
sudo systemctl reload nginx
```

---

## 📞 Contacto & Soporte

- **Email:** admin@conce.com
- **Proveedor VPS:** OVHCloud (https://www.ovhcloud.com)
- **Panel de administración:** Webmin — `https://158.69.222.245:10000` (opcional, mismo login que SSH)
- **Documentación:** Ver ARCHITECTURE.md, TOOLS-AND-UTILITIES.md, DOMAINS.md

---

## 📅 Próximos Pasos

- [ ] Configurar monitoreo (Prometheus, Grafana)
- [ ] Implementar CDN (Cloudflare)
- [ ] Configurar backups automáticos (S3)
- [ ] Agregar certificados wildcard
- [ ] Implementar rate limiting
- [ ] Agregar más aplicaciones
- [ ] Escalar horizontalmente

---

**Última actualización:** 2026-09-20
**Versión:** 1.0.0
