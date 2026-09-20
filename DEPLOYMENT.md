# 🚀 Guía de Despliegue - VPS Initech

Guía paso a paso para desplegar todo el sistema en el VPS.

---

## 📋 Pre-requisitos

- ✅ VPS Ubuntu 24.04 LTS activo (158.69.222.245), **limpio** (sin Nginx/Apache instalados) — requisito de CloudPanel
- ✅ Acceso SSH al VPS (usuario: Ubuntu, contraseña: DmSdQKUQZQrp)
- ✅ Dominios registrados (conce.com, initech.cl, contrastocolor.ink, wikipedia.cl)
- ✅ Acceso a panel DNS de cada dominio

---

## 🎛️ Panel de Control: CloudPanel

Usamos **CloudPanel** (gratis, Community Edition) en vez de cPanel (de pago) o Virtualmin (que exige controlar todo el servidor y no convive bien con configuraciones hechas a mano). CloudPanel administra Nginx, PHP, MariaDB/MySQL y SSL (Let's Encrypt) — todo también scripteable vía su CLI `clpctl`, que es lo que usan los scripts de `vps-setup/`.

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
./install-all.sh conce.com
```

**Opción B: Manual (Más Control)**
```bash
chmod +x *.sh

# 01: prerequisito único
./01-system-update.sh

# 02: panel de control (instala Nginx+PHP+MariaDB+SSL internamente)
./02-install-cloudpanel.sh
# Entra de inmediato a https://158.69.222.245:8443 y crea el usuario admin

# 04_A: crear el sitio para el dominio
./04_A-add-site-php.sh conce.com

# 05: desplegar landing page
./05-deploy-landing-page.sh conce.com

# 04_D: SSL (necesita DNS ya propagado)
./04_D-install-ssl-cloudpanel.sh conce.com
```

### Paso 4: Verificar Instalación

```bash
# Sitios en CloudPanel
sudo clpctl site:list

# Panel web
https://158.69.222.245:8443

# Software opcional que hayas instalado
java -version 2>/dev/null
python3 --version
psql --version 2>/dev/null
```

---

## 🌐 Fase 2: Configurar Dominios

### Paso 1: Configurar DNS para cada dominio

**En tu registrador de dominio (GoDaddy, Namecheap, Network Solutions, ISP Chile, etc.):**

Para cada dominio (conce.com, initech.cl, etc.), agrega registros **A**:

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

Guarda los cambios y **espera 15-60 minutos** para que se propague (algunos registradores tardan hasta 24h).

### Paso 2: Verificar propagación DNS

```bash
nslookup conce.com
dig conce.com

# Salida esperada:
# conce.com.  3600  IN  A  158.69.222.245
```

### Paso 3: Crear el sitio en CloudPanel para cada dominio

En el VPS, para cada dominio adicional:

```bash
./04_A-add-site-php.sh initech.cl
./05-deploy-landing-page.sh initech.cl
```

Esto crea el vhost de Nginx, el usuario del sitio y la estructura `/home/<usuario>/htdocs/<dominio>/public/` — todo gestionado por CloudPanel, sin editar Nginx a mano.

### Paso 4: Obtener Certificados SSL

```bash
./04_D-install-ssl-cloudpanel.sh conce.com
./04_D-install-ssl-cloudpanel.sh initech.cl
```

Cada script verifica que el DNS ya resuelva a este VPS antes de pedir el certificado.

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
# Python base (si no esta instalado)
./03_B-install-python.sh

# Crear el sitio Python en CloudPanel (puerto 8000 para la app)
./04_B-add-site-python.sh contrastocolor.ink contrastocolor 8000
```

Esto crea `/home/contrastocolor/htdocs/contrastocolor.ink/` y deja el sitio esperando una app que escuche en el puerto 8000. Sube tu código (Flask/FastAPI, ver `DOMAINS.md` para un ejemplo de `app.py`) por SFTP/SSH con el usuario del sitio, instala dependencias en un venv, y arráncalo con `gunicorn` (systemd es la forma recomendada de mantenerlo corriendo — CloudPanel documenta esto en su panel para sitios Python).

```bash
./04_D-install-ssl-cloudpanel.sh contrastocolor.ink
```

### 3.2: Aplicación PHP (wikipedia.cl)

En el VPS:

```bash
./04_A-add-site-php.sh wikipedia.cl
```

Sube o instala tu código dentro de `/home/wikipedia/htdocs/wikipedia.cl/public/` (por SFTP/SSH con el usuario del sitio, o vía Composer si tienes acceso a esa carpeta):

```bash
cd /home/wikipedia/htdocs/wikipedia.cl
composer install   # o: composer create-project laravel/laravel . --prefer-dist
```

```bash
./04_D-install-ssl-cloudpanel.sh wikipedia.cl
```

---

## 🗄️ Fase 4: Configurar Base de Datos

CloudPanel trae MySQL/MariaDB integrado — para crear una base de datos ligada a un sitio:

```bash
sudo clpctl db:add \
    --domainName=wikipedia.cl \
    --databaseName=wikipedia \
    --databaseUserName=wikipedia_app \
    --databaseUserPassword='contraseña_segura'
```

Si necesitas **PostgreSQL** además (como usan otros proyectos de este repo, p. ej. `cobros-ingresos-funnels`):

```bash
./03_C-install-postgresql.sh

sudo -u postgres psql
CREATE DATABASE mi_base_datos;
CREATE USER mi_usuario WITH PASSWORD 'contraseña_segura';
GRANT ALL PRIVILEGES ON DATABASE mi_base_datos TO mi_usuario;
\q
```

Desde aplicaciones PHP/Python:

```php
// PHP + PostgreSQL
$dbconn = pg_connect("host=localhost user=mi_usuario password=contraseña_segura dbname=mi_base_datos");
```

```python
# Python + PostgreSQL
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
# 1. Sitios y estado general
sudo clpctl site:list

# 2. Archivos en su lugar
ls -la /home/conce/htdocs/conce.com/public/
ls -la /home/wikipedia/htdocs/wikipedia.cl/public/

# 3. Software opcional
sudo systemctl status postgresql 2>/dev/null

# 4. Conectividad a BD (si usas Postgres)
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

Todos deben cargar correctamente y mostrar HTTPS con certificado válido.

---

## 🔄 Mantenimiento Diario

### Monitoreo de Logs

Desde el panel (`https://158.69.222.245:8443` → sitio → "Logs") o, si prefieres CLI, revisa `sudo clpctl site:list` para confirmar estado; CloudPanel centraliza logs de Nginx y PHP-FPM por sitio en su interfaz.

### Chequeos de Salud

```bash
# Uso de recursos
htop

# Espacio en disco
df -h

# Conectividad bases de datos (si usas Postgres)
sudo -u postgres psql -l
```

---

## 🔄 Mantenimiento Semanal

### Actualizar Sistema

```bash
sudo apt-get update
sudo apt-get upgrade -y
```

⚠️ No actualices Nginx/PHP/MariaDB por fuera de CloudPanel (`apt-get upgrade` normal no los toca porque vienen de los repos propios de CloudPanel, pero evita instalar/reinstalar esos paquetes a mano).

### Renovar Certificados SSL

CloudPanel renueva los certificados Let's Encrypt automáticamente. Verifica su estado desde el panel, sección SSL/TLS de cada sitio.

### Backup de Base de Datos

```bash
# PostgreSQL (si lo usas)
sudo -u postgres pg_dumpall > ~/backups/all_databases_$(date +%Y%m%d).sql

# MySQL/MariaDB (via CloudPanel)
mysqldump -u usuario -p nombre_base > ~/backups/nombre_base_$(date +%Y%m%d).sql
```

CloudPanel también incluye su propio módulo de backups programados en el panel.

---

## 🆘 Troubleshooting

### Landing page no carga

```bash
# 1. Verificar que el sitio existe en CloudPanel
sudo clpctl site:list

# 2. Verificar archivos
ls -la /home/conce/htdocs/conce.com/public/

# 3. Ver logs desde el panel: https://158.69.222.245:8443 -> sitio -> Logs
```

### App Python no responde

```bash
# 1. Ver logs del servicio (si lo corres via systemd)
sudo journalctl -u contrastocolor -n 50

# 2. Reiniciar
sudo systemctl restart contrastocolor

# 3. Probar conexión directa al puerto de la app
curl http://127.0.0.1:8000
```

### Base de datos no responde

```bash
# PostgreSQL
sudo systemctl status postgresql
sudo -u postgres psql
\l

# MySQL/MariaDB (CloudPanel)
sudo clpctl db:list 2>/dev/null || sudo mysql -e "SHOW DATABASES;"
```

### SSL no funciona

```bash
# Reintentar (verifica DNS automaticamente antes)
./04_D-install-ssl-cloudpanel.sh tudominio.com
```

### `02-install-cloudpanel.sh` falla con "ya hay Nginx/Apache instalado"

El VPS ya no está limpio (quizás corriste una versión anterior de estos scripts que instalaba Nginx a mano). Reinstala el VPS desde OVHCloud (Ubuntu 24.04 limpio) y empieza de nuevo desde el Paso 1.

---

## 📞 Contacto & Soporte

- **Proveedor VPS:** OVHCloud (https://www.ovhcloud.com)
- **Panel de control:** CloudPanel — `https://158.69.222.245:8443`
- **Documentación:** Ver ARCHITECTURE.md, TOOLS-AND-UTILITIES.md, DOMAINS.md, vps-setup/README.md

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
**Versión:** 2.0.0
