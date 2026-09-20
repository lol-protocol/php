# VPS Setup Scripts

Scripts automáticos, uno por paso, para configurar un VPS Ubuntu 24 LTS con:
- Java 21, PHP 8.3, Python 3, PostgreSQL, Nginx + SSL/HTTPS

## 📐 Convención de nombres

Los archivos se numeran `NN` o `NN_LETRA`:

- **El número (`01`, `02`, `03`...) es secuencial** — indica el orden en que deben ejecutarse los grupos de pasos.
- **La letra (`_A`, `_B`, `_C`...) NO es secuencial** — agrupa pasos que comparten el mismo número porque son **independientes entre sí** (no importa en qué orden los corras, mientras todos terminen antes de pasar al siguiente número).

Ejemplo: `02_A-install-java.sh` y `02_B-install-php.sh` pueden ejecutarse en cualquier orden entre sí, pero ambos deben completarse antes de `03-configure-nginx-site.sh`.

## 🚀 Uso Rápido

### Opción 1: Instalación Automática (Recomendado)

```bash
chmod +x *.sh
./install-all.sh initech.fun admin@initech.fun
```

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

# --- 03: configuración del sitio (necesita que 02 haya terminado) ---
./03-configure-nginx-site.sh initech.fun
curl http://initech.fun                     # Prueba SIN SSL primero

# --- 04: SSL (necesita DNS ya propagado) ---
./04-setup-ssl.sh initech.fun admin@initech.fun

# --- 05: (re)despliegue de la landing page ---
./05-deploy-landing-page.sh
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
| 03 | `03-configure-nginx-site.sh` | Crea el virtual host de Nginx y copia la landing page |
| 04 | `04-setup-ssl.sh` | Obtiene certificado SSL (verifica DNS antes) |
| 05 | `05-deploy-landing-page.sh` | (Re)copia los archivos de la landing page |
| 06_A | `06_A-setup-php-app.sh` | *(Opcional)* Configura una app PHP adicional |
| 06_B | `06_B-setup-python-app.sh` | *(Opcional)* Configura una app Python (Flask + Gunicorn) |
| 06_C | `06_C-setup-dns-server.sh` | *(Opcional)* Instala BIND9 como servidor DNS propio — solo si tu registrador **no** tiene gestión de registros DNS (A/CNAME/TXT) |
| — | `install-all.sh` | Ejecuta 01 → 02_A..F → 03 → 04 → 05 en orden |

Los pasos `06_*` son opcionales e independientes entre sí — instala solo los que necesites, en cualquier orden.

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

**`curl http://tudominio.com` no responde:** revisa `03-configure-nginx-site.sh` (¿corrió sin errores? `sudo nginx -t`)

**El paso 04 (SSL) falla:** el DNS aún no propaga. Espera y vuelve a intentar: `./04-setup-ssl.sh tudominio.com tu@email.com`

**Landing page no se ve:** verifica permisos con `sudo chown -R www-data:www-data /var/www/landing-page`

## 🎯 Próximos Pasos (opcionales)

```bash
./06_A-setup-php-app.sh mi-app dominio.com      # App PHP
./06_B-setup-python-app.sh mi-app dominio.com   # App Python
```

Ver también: [ARCHITECTURE.md](../ARCHITECTURE.md), [DOMAINS.md](../DOMAINS.md), [DEPLOYMENT.md](../DEPLOYMENT.md), [TOOLS-AND-UTILITIES.md](../TOOLS-AND-UTILITIES.md)
