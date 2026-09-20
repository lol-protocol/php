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
./install-all.sh initech.cl
```

Esto instala **CloudPanel** (panel de control gratuito, alternativa a cPanel — ver más abajo), crea el sitio para tu dominio y despliega la landing page. El SSL se hace en un paso aparte porque necesita que el DNS ya haya propagado.

**¡Listo!** El script hará todo automáticamente.

---

## 🎛️ Panel de Control: CloudPanel

Usamos **CloudPanel** (gratis, Community Edition) en vez de cPanel (de pago) o Virtualmin (que exige controlar todo el servidor — correo, DNS, base de datos — y no convive bien con configuraciones hechas a mano). CloudPanel usa Nginx de forma nativa, tiene UI web moderna y SSL con un clic.

⚠️ Requiere un **servidor limpio** (sin Nginx/Apache ya instalados) — por eso no instalamos Nginx/PHP/Certbot a mano, todo eso lo hace CloudPanel.

Tras instalarlo, entra de inmediato a `https://IP_DEL_VPS:8443` para crear el usuario admin.

## 🔧 Instalación Manual (Paso a Paso)

Si prefieres más control, ejecuta cada paso manualmente. Ver la referencia completa y actualizada de todos los scripts (incluidos los opcionales: Java, PostgreSQL, Tomcat, Whisper, DNS propio) en [`vps-setup/README.md`](vps-setup/README.md).

### Paso 1️⃣: Prerequisito único

```bash
bash 01-system-update.sh       # Actualiza el sistema
```

### Paso 2️⃣: Panel de control

```bash
bash 02-install-cloudpanel.sh
```

Instala CloudPanel completo (Nginx + PHP + MariaDB + Let's Encrypt). Al terminar, entra a `https://IP_DEL_VPS:8443` y crea el usuario admin.

### Paso 3️⃣: Crear el sitio para tu dominio

```bash
bash 04_A-add-site-php.sh initech.cl
```

Esto crea el vhost en CloudPanel (`clpctl site:add:php`) con su propio usuario de sitio y contraseña (se muestran al final — guárdalas).

**Verificación:**
```bash
sudo clpctl site:list
curl http://initech.cl
```

### Paso 4️⃣: Desplegar la landing page

```bash
bash 05-deploy-landing-page.sh initech.cl
```

Copia los archivos a `/home/<usuario-del-sitio>/htdocs/initech.cl/public/`.

### Paso 5️⃣: Configurar SSL/HTTPS

```bash
bash 04_D-install-ssl-cloudpanel.sh initech.cl
```

⚠️ **IMPORTANTE:** Tu dominio debe estar apuntando a la IP del VPS antes de este paso. El script verifica el DNS automáticamente antes de continuar.

**Verificación:**
```bash
sudo clpctl site:list
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
# Sitios en CloudPanel
sudo clpctl site:list

# Panel web
https://IP_DEL_VPS:8443
```

### Verificar landing page

```bash
# Ver archivos (reemplaza SITE_USER y el dominio)
ls -la /home/SITE_USER/htdocs/initech.cl/public/
```

### Verificar SSL

Desde el panel web (`https://IP_DEL_VPS:8443` → tu sitio → SSL/TLS) o:
```bash
sudo clpctl site:list
```

### Problemas Comunes

**Problema:** "Connection refused" en https://dominio.com
- **Solución:** Verifica que el DNS apunte a la IP correcta
- **Verificar:** `nslookup dominio.com`

**Problema:** "404 Not Found"
- **Solución:** Verifica que los archivos estén en `/home/SITE_USER/htdocs/dominio.com/public/`

**Problema:** SSL no se genera
- **Solución:** Asegúrate que DNS esté propagado (5-15 min)
- **Verificar:** `curl http://dominio.com` (sin S)

**Problema:** `02-install-cloudpanel.sh` falla con "ya hay Nginx/Apache instalado"
- **Solución:** el VPS ya no está limpio. Reinstala el VPS (Ubuntu 24.04) desde tu proveedor y empieza de nuevo desde el paso 1.

---

## 🎯 Pasos Siguientes

### Si necesitas otro sitio PHP o una app adicional:

```bash
bash 04_A-add-site-php.sh otro-dominio.com
bash 05-deploy-landing-page.sh otro-dominio.com
```

### Si necesitas Aplicación Python:

```bash
bash 03_B-install-python.sh              # si aun no esta instalado
bash 04_B-add-site-python.sh miapp.dominio.com
```

### Si necesitas Java + Tomcat:

```bash
bash 03_A-install-java.sh
bash 03_D-install-tomcat.sh
bash 04_C-add-site-reverse-proxy.sh miapp.dominio.com http://127.0.0.1:8080
```

### Gestión de Base de Datos PostgreSQL

CloudPanel solo trae MySQL/MariaDB. Si necesitas PostgreSQL además (como usan otros proyectos de este repo):

```bash
bash 03_C-install-postgresql.sh
```

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

1. **Revisa los logs:** entra al panel (`https://IP_DEL_VPS:8443`) → tu sitio → pestaña "Logs" (Nginx/PHP-FPM en un solo lugar, sin recordar rutas)

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
/home/SITE_USER/htdocs/initech.cl/
└── public/
    ├── index.html      # Landing page principal
    ├── assets/         # Imágenes, CSS, JS (si lo agregas)
    └── ...
```

La configuración de Nginx, el certificado SSL y los logs los administra CloudPanel internamente — no hace falta tocarlos a mano; se ven y editan desde `https://IP_DEL_VPS:8443`.

---

**✓ ¡Listo! Tu VPS está configurado y tu landing page está en vivo.** 🎉

Próximos pasos opcionales:
- Agregar más dominios
- Configurar aplicaciones PHP/Python
- Configurar base de datos
- Automatizar backups
