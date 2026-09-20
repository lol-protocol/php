# VPS Setup Scripts

Scripts automáticos, uno por paso, para configurar un VPS Ubuntu 24 LTS con:
- Java 21, PHP 8.3, Python 3, PostgreSQL, Nginx + SSL/HTTPS

Cada script hace **una sola cosa** y se puede ejecutar de forma independiente. Están numerados en el orden en que deben correr.

## 🚀 Uso Rápido

### Opción 1: Instalación Automática (Recomendado)

```bash
chmod +x *.sh
./install-all.sh initech.fun admin@initech.fun
```

Ejecuta los pasos 01 al 10 en orden, con una pausa de confirmación al inicio.

### Opción 2: Paso a Paso (para ver dónde falla algo)

```bash
chmod +x *.sh

# --- Instalación de software base ---
./01-system-update.sh          # Actualiza el sistema
./02-install-java.sh           # Java 21
./03-install-php.sh            # PHP 8.3 + extensiones
./04-install-python.sh         # Python 3 + pip + venv
./05-install-postgresql.sh     # PostgreSQL
./06-install-nginx.sh          # Servidor web Nginx
./07-install-certbot.sh        # Certbot (para SSL)

# --- Configuración del sitio ---
./08-configure-nginx-site.sh initech.fun   # Virtual host + copia landing page
curl http://initech.fun                     # Prueba SIN SSL primero

./09-setup-ssl.sh initech.fun admin@initech.fun   # Certificado SSL
./10-deploy-landing-page.sh                        # (Re)despliega la landing page
```

## ⚠️ Importante: DNS primero

**Antes del paso 09 (SSL)**, tu dominio debe apuntar a la IP del VPS:

1. En el panel DNS de tu registrador, agrega registros **A**:
   ```
   @   → IP del VPS
   www → IP del VPS
   ```
2. Espera a que propague (15 min - 24h según el registrador)
3. Verifica: `nslookup tudominio.com`

## 📦 Todos los Scripts

| # | Script | Qué hace |
|---|--------|----------|
| 01 | `01-system-update.sh` | Actualiza APT e instala utilidades base (git, curl, build-essential) |
| 02 | `02-install-java.sh` | Instala Java 21 (OpenJDK) |
| 03 | `03-install-php.sh` | Instala PHP 8.3 + FPM + extensiones comunes |
| 04 | `04-install-python.sh` | Instala Python 3 + pip + venv |
| 05 | `05-install-postgresql.sh` | Instala y arranca PostgreSQL |
| 06 | `06-install-nginx.sh` | Instala y arranca Nginx |
| 07 | `07-install-certbot.sh` | Instala Certbot (Let's Encrypt) |
| 08 | `08-configure-nginx-site.sh` | Crea el virtual host de Nginx y copia la landing page |
| 09 | `09-setup-ssl.sh` | Obtiene certificado SSL (verifica DNS antes) |
| 10 | `10-deploy-landing-page.sh` | (Re)copia los archivos de la landing page |
| 11 | `11-setup-php-app.sh` | *(Opcional)* Configura una app PHP adicional |
| 12 | `12-setup-python-app.sh` | *(Opcional)* Configura una app Python (Flask + Gunicorn) |
| 13 | `13-setup-dns-server.sh` | *(Opcional)* Instala BIND9 como servidor DNS propio — solo si tu registrador **no** tiene gestión de registros DNS (A/CNAME/TXT) |
| — | `install-all.sh` | Ejecuta los pasos 01-10 en orden |

## ✅ Verificación Post-Instalación

```bash
# Servicios
sudo systemctl status nginx php8.3-fpm postgresql

# Logs
sudo tail -f /var/log/nginx/tudominio.com/error.log

# SSL
sudo certbot certificates

# Landing page
ls -la /var/www/landing-page/
```

## 🆘 Troubleshooting

**`curl http://tudominio.com` no responde:** revisa `08-configure-nginx-site.sh` (¿corrió sin errores? `sudo nginx -t`)

**El paso 09 (SSL) falla:** el DNS aún no propaga. Espera y vuelve a intentar: `./09-setup-ssl.sh tudominio.com tu@email.com`

**Landing page no se ve:** verifica permisos con `sudo chown -R www-data:www-data /var/www/landing-page`

## 🎯 Próximos Pasos (opcionales)

```bash
./11-setup-php-app.sh mi-app dominio.com      # App PHP
./12-setup-python-app.sh mi-app dominio.com   # App Python
```

Ver también: [ARCHITECTURE.md](../ARCHITECTURE.md), [DOMAINS.md](../DOMAINS.md), [DEPLOYMENT.md](../DEPLOYMENT.md), [TOOLS-AND-UTILITIES.md](../TOOLS-AND-UTILITIES.md)
