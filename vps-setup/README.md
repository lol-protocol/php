# VPS Setup Scripts

Scripts automáticos, uno por paso, para configurar un VPS Ubuntu 24 LTS con:
- Java 21, PHP 8.3, Python 3, PostgreSQL, Nginx + SSL/HTTPS
- **Webmin** (panel de administración web, opcional)
- Extras opcionales: MariaDB, Apache Tomcat, Python + Whisper, servidor DNS propio

## 🎛️ ¿Por qué Webmin y no CloudPanel/cPanel/Virtualmin?

- **cPanel**: requiere licencia paga.
- **Virtualmin / CloudPanel**: administran el servidor con su propia base de datos de sitios/usuarios y su propia convención de carpetas y CLI. Si el panel se cae o se desinstala, para cambiar cualquier cosa hay que reconstruir a mano su estructura propietaria — no hay equivalente directo en comandos estándar.
- **Webmin**: es una GUI delgada que **lee y edita los archivos de configuración nativos** (`/etc/nginx/`, `/etc/letsencrypt/`, crontab, `/etc/passwd`, reglas de `ufw`, etc.). Todo lo que haces desde Webmin es exactamente lo mismo que harías por SSH con `nginx`, `certbot`, `systemctl`, `useradd`. Si Webmin se cae o lo desinstalas, **nada deja de funcionar** — sigues administrando todo con los mismos comandos de siempre.

Por eso esta versión de los scripts **no depende de ningún panel**: instala el stack estándar (Nginx, PHP-FPM, Certbot, MariaDB/PostgreSQL) con comandos nativos, y Webmin se agrega **encima**, como capa visual opcional — nunca como requisito.

## 📐 Convención de nombres

Los archivos se numeran `NN` o `NN_LETRA`:

- **El número (`01`, `02`, `03`...) es secuencial** — indica el orden en que deben ejecutarse los grupos de pasos.
- **La letra (`_A`, `_B`, `_C`...) NO es secuencial** — agrupa pasos que comparten el mismo número porque son **independientes entre sí**, salvo que el script diga explícitamente "requiere X" (ahí sí hay una dependencia real de software, no de orden arbitrario).

Ejemplo: `02_A-install-java.sh` y `02_B-install-php.sh` pueden ejecutarse en cualquier orden entre sí, pero ambos deben completarse antes de `03-configure-nginx-site.sh`.

## 🚀 Uso Rápido

### Opción 1: Instalación Automática (Recomendado)

```bash
chmod +x *.sh
./install-all.sh initech.fun admin@initech.fun
```

Instala el stack base (Java, PHP, Python, PostgreSQL, Nginx, Certbot, **Webmin**), configura el sitio, pide SSL y despliega la landing page.

### Opción 2: Paso a Paso (para ver dónde falla algo)

```bash
chmod +x *.sh

# --- 01: prerequisito único ---
./01-system-update.sh

# --- 02: instalaciones independientes entre sí (cualquier orden) ---
./02_A-install-java.sh
./02_B-install-php.sh
./02_C-install-python.sh
./02_D-install-postgresql.sh
./02_E-install-nginx.sh
./02_F-install-certbot.sh
./02_J-install-webmin.sh          # Panel de administración (opcional pero recomendado)

# --- 03: configuración del sitio (necesita que 02 haya terminado) ---
./03-configure-nginx-site.sh initech.fun
curl http://initech.fun                     # Prueba SIN SSL primero

# --- 04: SSL (necesita DNS ya propagado) ---
./04-setup-ssl.sh initech.fun admin@initech.fun

# --- 05: (re)despliegue de la landing page ---
./05-deploy-landing-page.sh initech.fun

# --- extras opcionales del grupo 02 (corre solo los que necesites) ---
./02_G-install-mariadb.sh          # MariaDB
./02_H-install-tomcat.sh           # Apache Tomcat (requiere 02_A ya hecho)
./02_I-install-python-whisper.sh   # Whisper + libs Python (requiere 02_C ya hecho)
```

## ⚠️ Importante: DNS primero

**Antes del paso 04 (SSL)**, tu dominio debe apuntar a la IP del VPS:

1. En el panel DNS de tu registrador, agrega registros **A**:
   ```
   @   → IP del VPS
   www → IP del VPS
   ```
2. Espera a que propague (15 min - 24h según el registrador)
3. Verifica: `nslookup tudominio.com`

## 🖥️ Webmin — acceso y uso

```
https://IP_DEL_VPS:10000
```

Inicia sesión con el mismo usuario/contraseña que usas por SSH (root o tu usuario con sudo).

**Módulos más útiles ya incluidos:**
- **Nginx Webserver** — edita server blocks, SSL, proxy, gzip directamente sobre `/etc/nginx/`
- **Users and Groups** — administra cuentas del sistema
- **Scheduled Cron Jobs** — cron visual
- **Firewall (UFW)** — reglas de puertos
- **Software Package Updates** — `apt update/upgrade` desde la web
- **File Manager** — navega y edita archivos del servidor

Todo lo que hagas ahí se refleja en los mismos archivos que tocan estos scripts — no hay dos fuentes de verdad.

## 📦 Todos los Scripts

| Paso | Script | Qué hace |
|------|--------|----------|
| 01 | `01-system-update.sh` | Actualiza APT e instala utilidades base (git, curl, build-essential) |
| 02_A | `02_A-install-java.sh` | Instala Java 21 (OpenJDK) |
| 02_B | `02_B-install-php.sh` | Instala PHP 8.3 + FPM + extensiones comunes |
| 02_C | `02_C-install-python.sh` | Instala Python 3 + pip + venv |
| 02_D | `02_D-install-postgresql.sh` | Instala y arranca PostgreSQL |
| 02_E | `02_E-install-nginx.sh` | Instala y arranca Nginx |
| 02_F | `02_F-install-certbot.sh` | Instala Certbot (Let's Encrypt) |
| 02_G | `02_G-install-mariadb.sh` | *(Opcional)* Instala MariaDB |
| 02_H | `02_H-install-tomcat.sh` | *(Opcional)* Instala Apache Tomcat — requiere que `02_A` (Java) ya haya corrido |
| 02_I | `02_I-install-python-whisper.sh` | *(Opcional)* Instala Whisper (OpenAI, transcripción de audio) + ffmpeg + librerías Python básicas en un venv en `/opt/venvs/whisper` — requiere que `02_C` (Python) ya haya corrido |
| 02_J | `02_J-install-webmin.sh` | Instala Webmin (panel de administración web, opcional pero incluido por defecto en `install-all.sh`) |
| 03 | `03-configure-nginx-site.sh` | Crea el virtual host de Nginx y copia la landing page |
| 04 | `04-setup-ssl.sh` | Obtiene certificado SSL (verifica DNS antes) |
| 05 | `05-deploy-landing-page.sh` | (Re)copia los archivos de la landing page |
| 06_A | `06_A-setup-php-app.sh` | *(Opcional)* Configura una app PHP adicional |
| 06_B | `06_B-setup-python-app.sh` | *(Opcional)* Configura una app Python (Flask + Gunicorn) |
| 06_C | `06_C-setup-dns-server.sh` | *(Opcional)* Instala BIND9 como servidor DNS propio — solo si tu registrador **no** tiene gestión de registros DNS (A/CNAME/TXT) |
| 06_D | `06_D-setup-tomcat-app.sh` | *(Opcional)* Configura Nginx como reverse proxy hacia Tomcat para un dominio — requiere `02_H` ya hecho |
| — | `install-all.sh` | Ejecuta 01 → 02_A..F+J → 03 → 04 → 05 en orden |

Los pasos `02_G`/`02_H`/`02_I` y todos los `06_*` son opcionales e independientes entre sí — instala solo los que necesites. Las notas "requiere X ya hecho" son las únicas excepciones a "cualquier orden": son dependencias reales de software, no de orden de ejecución arbitrario.

## ✅ Verificación Post-Instalación

```bash
# Servicios
sudo systemctl status nginx php8.3-fpm postgresql webmin

# Logs
sudo tail -f /var/log/nginx/tudominio.com/error.log

# SSL
sudo certbot certificates

# Landing page (una carpeta por dominio)
ls -la /var/www/landing-page/tudominio.com/
```

## 🆘 Troubleshooting

**`curl http://tudominio.com` no responde:** revisa `03-configure-nginx-site.sh` (¿corrió sin errores? `sudo nginx -t`)

**El paso 04 (SSL) falla:** el DNS aún no propaga. Espera y vuelve a intentar: `./04-setup-ssl.sh tudominio.com tu@email.com`

**Landing page no se ve:** verifica permisos con `sudo chown -R www-data:www-data /var/www/landing-page/tudominio.com`

**No puedo entrar a Webmin:** verifica que el puerto 10000 esté abierto (`sudo ufw status`) y que el servicio esté corriendo (`sudo systemctl status webmin`)

## 🎯 Próximos Pasos (opcionales)

```bash
./06_A-setup-php-app.sh mi-app dominio.com      # App PHP
./06_B-setup-python-app.sh mi-app dominio.com   # App Python
./06_D-setup-tomcat-app.sh dominio.com mi-app   # App Java (Tomcat) detrás de Nginx
```

Ver también: [ARCHITECTURE.md](../ARCHITECTURE.md), [DOMAINS.md](../DOMAINS.md), [DEPLOYMENT.md](../DEPLOYMENT.md), [TOOLS-AND-UTILITIES.md](../TOOLS-AND-UTILITIES.md)
