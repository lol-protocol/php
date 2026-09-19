# 🚀 Guía de Configuración VPS - Initech

Esta guía te llevará a través de todos los pasos para configurar tu VPS (Ubuntu 24 LTS) con una landing page, SSL/HTTPS y todos los componentes necesarios.

## 📋 Contenido

1. [Requisitos Previos](#requisitos-previos)
2. [Acceso al VPS](#acceso-al-vps)
3. [Instalación Rápida (Automática)](#instalación-rápida-automática)
4. [Instalación Manual (Paso a Paso)](#instalación-manual-paso-a-paso)
5. [Configuración de DNS](#configuración-de-dns)
6. [Verificación y Troubleshooting](#verificación-y-troubleshooting)
7. [Pasos Siguientes](#pasos-siguientes)

---

## 📦 Requisitos Previos

- **VPS con Ubuntu 24 LTS** (nuevo/limpio recomendado)
- **Dominio** registrado y acceso a sus DNS (initech.cl o conce.com)
- **Acceso SSH** al VPS (con usuario root o con sudo)
- **Email** para certificado SSL (ej: admin@initech.cl)

---

## 🔐 Acceso al VPS

Desde tu terminal local:

```bash
ssh root@<IP_DEL_VPS>
# O si tienes usuario específico:
ssh usuario@<IP_DEL_VPS>
```

Una vez dentro del VPS:

```bash
# Verificar que estamos en Ubuntu 24 LTS
lsb_release -a

# Verificar conexión a internet
curl https://www.google.com
```

---

## ⚡ Instalación Rápida (Automática)

Esta es la forma más rápida. El script ejecuta todos los pasos automáticamente.

### Paso 1: Descargar los scripts

```bash
# En el VPS, ejecuta:
cd /tmp
git clone https://github.com/tu-usuario/php.git
cd php/vps-setup
```

### Paso 2: Ejecutar instalación completa

```bash
# Con tu dominio (example: initech.cl)
chmod +x install-all.sh
./install-all.sh initech.cl admin@initech.cl
```

**¡Listo!** El script hará todo automáticamente.

---

## 🔧 Instalación Manual (Paso a Paso)

Si prefieres más control, ejecuta cada paso manualmente.

### Paso 1️⃣: Instalación Base

```bash
bash 01-initial-setup.sh
```

Esto instala:
- Java 21
- PHP 8.3 con extensiones
- Python 3
- PostgreSQL
- Nginx
- Certbot (para SSL)

**Salida esperada:**
```
✓ Setup completado exitosamente
- Java: 21.0.x
- PHP: 8.3.x
- Python: 3.12.x
- PostgreSQL: 16.x
- Nginx: 1.26.x
```

### Paso 2️⃣: Configurar Nginx

```bash
bash 02-nginx-setup.sh initech.cl
```

Esto:
- Crea `/var/www/landing-page`
- Configura Nginx para servir tu landing page
- Establece permisos correctos

**Verificación:**
```bash
sudo nginx -t
sudo systemctl status nginx
```

### Paso 3️⃣: Configurar SSL/HTTPS

```bash
bash 03-ssl-setup.sh initech.cl admin@initech.cl
```

⚠️ **IMPORTANTE:** Tu dominio debe estar apuntando a la IP del VPS antes de este paso.

Esto:
- Obtiene certificado SSL de Let's Encrypt
- Configura renovación automática
- Redirige todo a HTTPS

**Verificación:**
```bash
sudo certbot certificates -d initech.cl
```

### Paso 4️⃣: Desplegar Landing Page

```bash
bash 04-deploy-landing-page.sh
```

Copia los archivos de la landing page a:
```
/var/www/landing-page/
```

---

## 🌐 Configuración de DNS

Antes del paso 3 (SSL), necesitas apuntar tu dominio a la IP del VPS.

### Para **initech.cl** (si está en ISP Chile):

1. Ingresa a tu panel de control del dominio
2. Busca la sección "DNS" o "Name Servers"
3. Agrega un registro **A**:
   ```
   Tipo: A
   Nombre: @ (o dejar en blanco)
   Valor: <IP_DEL_VPS>
   TTL: 3600
   ```
4. Si necesitas www: agrega otro A:
   ```
   Tipo: A
   Nombre: www
   Valor: <IP_DEL_VPS>
   TTL: 3600
   ```

### Para **conce.com**:

El proceso es similar. Busca la opción para editar DNS en tu registrador.

**Verificar DNS propagado:**
```bash
# En tu computadora:
nslookup initech.cl
# O con dig:
dig initech.cl
```

Espera 5-15 minutos para que se propague.

---

## ✅ Verificación y Troubleshooting

### Verificar estado general

```bash
# Estado de Nginx
sudo systemctl status nginx

# Estado de PHP-FPM
sudo systemctl status php8.3-fpm

# Ver logs de Nginx
sudo tail -f /var/log/nginx/initech.cl/access.log
sudo tail -f /var/log/nginx/initech.cl/error.log
```

### Verificar landing page

```bash
# Ver archivos
ls -la /var/www/landing-page/

# Verificar permisos
sudo chown -R www-data:www-data /var/www/landing-page
sudo chmod -R 755 /var/www/landing-page
```

### Verificar SSL

```bash
# Ver certificado
sudo certbot certificates

# Verificar fecha de renovación
sudo certbot renew --dry-run
```

### Problemas Comunes

**Problema:** "Connection refused" en https://dominio.com
- **Solución:** Verifica que el DNS apunte a la IP correcta
- **Verificar:** `nslookup dominio.com`

**Problema:** "404 Not Found"
- **Solución:** Verifica que los archivos estén en `/var/www/landing-page/`
- **Verificar:** `ls -la /var/www/landing-page/`

**Problema:** SSL no se genera
- **Solución:** Asegúrate que DNS esté propagado (5-15 min)
- **Verificar:** `curl http://dominio.com` (sin S)

**Problema:** PHP no funciona
- **Solución:** Verifica que PHP-FPM esté corriendo
- **Verificar:** `sudo systemctl status php8.3-fpm`

---

## 🎯 Pasos Siguientes

### Si necesitas Aplicación PHP:

```bash
bash 05-setup-php-app.sh mi-app dominio.com
```

Luego:
1. Copia tu código PHP en `/var/www/mi-app/`
2. Ejecuta SSL con Certbot
3. Reinicia Nginx

### Si necesitas Aplicación Python:

```bash
bash 06-setup-python-app.sh mi-app dominio.com
```

Luego:
1. Copia tu código Python en `/var/www/mi-app/`
2. Instala dependencias: `pip install -r requirements.txt`
3. Ejecuta SSL con Certbot

### Gestión de Base de Datos PostgreSQL:

```bash
# Conectar a PostgreSQL
sudo -u postgres psql

# Crear base de datos
CREATE DATABASE mi_base_de_datos;

# Crear usuario
CREATE USER mi_usuario WITH PASSWORD 'contraseña_segura';

# Dar permisos
GRANT ALL PRIVILEGES ON DATABASE mi_base_de_datos TO mi_usuario;

# Salir
\q
```

### Backup Automático:

```bash
# Crea un script en /usr/local/bin/backup-landing-page.sh
#!/bin/bash
tar -czf /backups/landing-page-$(date +%Y%m%d).tar.gz /var/www/landing-page/

# Agrega a crontab (cada día a las 2 AM):
0 2 * * * /usr/local/bin/backup-landing-page.sh
```

---

## 📞 Soporte

Si tienes problemas:

1. **Revisa los logs:**
   ```bash
   sudo tail -50 /var/log/nginx/initech.cl/error.log
   ```

2. **Verifica DNS:**
   ```bash
   nslookup initech.cl
   ```

3. **Prueba conectividad:**
   ```bash
   curl -I https://initech.cl
   ```

---

## 📊 Estructura de Archivos

```
/var/www/landing-page/
├── index.html          # Landing page principal
├── assets/            # Imágenes, CSS, JS (si lo agregas)
└── ...

/etc/nginx/sites-available/
├── initech.cl         # Configuración de Nginx

/etc/letsencrypt/live/initech.cl/
├── fullchain.pem      # Certificado SSL
└── privkey.pem        # Clave privada

/var/log/nginx/initech.cl/
├── access.log         # Logs de acceso
└── error.log          # Logs de error
```

---

**✓ ¡Listo! Tu VPS está configurado y tu landing page está en vivo.** 🎉

Próximos pasos opcionales:
- Agregar más dominios
- Configurar aplicaciones PHP/Python
- Configurar base de datos
- Automatizar backups
