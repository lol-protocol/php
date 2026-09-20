# 🏗️ Arquitectura del Proyecto - Initech VPS

## 📋 Resumen Ejecutivo

Sistema de infraestructura multi-dominio alojado en VPS con Ubuntu 24 LTS en OVHCloud. Soporta múltiples sitios web, aplicaciones PHP y Python, con base de datos PostgreSQL centralizada y SSL/HTTPS automático.

**VPS Principal:**
- **Proveedor:** OVHCloud (Beauharnois, Canadá)
- **IP:** 158.69.222.245
- **SO:** Ubuntu 24.04 LTS
- **Estado:** Activo

---

## 🌐 Dominios Configurados

| Dominio | Tipo | Propósito | Status |
|---------|------|----------|--------|
| **conce.com** | Landing Page | Sitio principal (Conce) | 🔄 Pendiente |
| **initech.cl** | Landing Page | Sitio corporativo (Initech) | 🔄 Pendiente |
| **contrastocolor.ink** | Aplicación | Portal de colores/diseño | 🔄 Pendiente |
| **wikipedia.cl** | Aplicación | Wiki local | 🔄 Pendiente |

---

## 🛠️ Stack Tecnológico

### Panel de Administración
- **Webmin** (gratis, opcional)
  - GUI que **edita los archivos de configuración nativos** (Nginx, cron, usuarios, UFW) — no tiene base de datos propia ni convención de carpetas propietaria
  - Si se desinstala, nada dejar de funcionar: todo sigue siendo Nginx/Certbot/systemd estándar
  - Interfaz web (`:10000`), mismo usuario/contraseña que SSH
  - Alternativa a cPanel (de pago), Virtualmin y CloudPanel (que administran el servidor con su propio CLI/base de datos y no resisten bien no tener panel)

### Servidor Web
- **Nginx 1.26+** (instalado con `apt`, configurado a mano, un `sites-available/<dominio>` por sitio)
  - Reverse proxy
  - Compresión GZIP
  - Cache HTTP

### Lenguajes & Frameworks
- **PHP 8.3** - Backend web (PHP-FPM, instalación única para todo el servidor)
- **Python 3.12** - Scripts, apps Flask/FastAPI y Whisper
- **Java 21** - Apps empresariales, vía Apache Tomcat detrás de un reverse-proxy de Nginx

### Base de Datos
- **PostgreSQL 16+** - Base de datos principal
- **MariaDB** - Opcional, para software que exija específicamente ese motor

### SSL/TLS
- **Let's Encrypt** - Vía Certbot (`certbot certify --nginx`)
- Renovación automática con `certbot.timer`

### Monitoreo & Logging
- **Nginx Logs** - Access & error logs por dominio, también visibles desde Webmin
- **Syslog** - Sistema centralizado (futuro)
- **Prometheus** - Métricas (futuro)

---

## 📁 Estructura de Directorios

```
/var/www/
├── landing-page/
│   ├── conce.com/
│   │   └── index.html
│   └── initech.cl/
│       └── index.html
├── contrastocolor.ink/
│   ├── venv/
│   └── app.py
└── wikipedia.cl/
    └── public/

/etc/nginx/
├── sites-available/
│   ├── conce.com
│   ├── initech.cl
│   ├── contrastocolor.ink
│   └── wikipedia.cl
└── sites-enabled/
    └── (enlaces simbólicos)

/etc/letsencrypt/live/
├── conce.com/
├── initech.cl/
├── contrastocolor.ink/
└── wikipedia.cl/

/var/log/nginx/
├── conce.com/
├── initech.cl/
├── contrastocolor.ink/
└── wikipedia.cl/

/var/lib/postgresql/
└── (datos de PostgreSQL)

/opt/venvs/whisper/
└── (entorno virtual de Python + Whisper, si se instaló)

/etc/webmin/
└── (configuración de Webmin, si se instaló — no interfiere con lo anterior)

/home/backups/
├── daily/
├── weekly/
└── monthly/
```

---

## 🔄 Flujo de Solicitudes

```
Usuario (Internet)
        ↓
   [Firewall]
        ↓
  158.69.222.245:80/443
        ↓
  [Nginx - Reverse Proxy, un vhost por sitio en /etc/nginx/sites-available/]
        ↓
   ┌─────────────────────────────────────┐
   │                                     │
   v                                     v
HTML/CSS/JS                         [PHP-FPM 8.3]
Landing Pages                       o [Python Apps] o [Tomcat via reverse-proxy]
   │                                     │
   ↓                                     ↓
/var/www/landing-page/          /var/www/{app}/
   │                                     │
   └─────────────────────────────────────┘
           ↓
    [PostgreSQL 16] o [MariaDB, opcional]
```

Webmin (`:10000`, opcional) es una capa de administración paralela sobre esta misma configuración — no está en el camino de las peticiones de los usuarios finales.

---

## 🔐 Seguridad

### SSL/TLS
- ✅ Certificados Let's Encrypt (gratis)
- ✅ Renovación automática (Certbot)
- ✅ HTTPS obligatorio (redirección automática)
- ✅ Headers de seguridad
  - `Strict-Transport-Security`
  - `X-Frame-Options`
  - `X-Content-Type-Options`
  - `X-XSS-Protection`

### Firewall
- ✅ UFW habilitado
- ✅ Puertos abiertos: 22 (SSH), 80 (HTTP), 443 (HTTPS)
- ✅ SSH con key-based auth

### Base de Datos
- ✅ PostgreSQL sin acceso externo
- ✅ Autenticación local (UNIX socket)
- ✅ Usuarios con permisos limitados

---

## 📊 Diagrama de Arquitectura

```
┌─────────────────────────────────────────────────┐
│           Internet / DNS (Cloudflare)           │
└─────────────────────┬───────────────────────────┘
                      │
         ┌────────────┼────────────┐
         │            │            │
   conce.com   initech.cl   contrastocolor.ink
         │            │            │
         └────────────┼────────────┘
                      │ A Record
         ┌────────────▼─────────────┐
         │  158.69.222.245 (VPS)    │
         │   OVHCloud - Canadá      │
         └────────────┬─────────────┘
                      │
         ┌────────────▼─────────────┐
         │  Nginx (Reverse Proxy)   │
         │  + Webmin :10000 (admin) │
         └────────────┬─────────────┘
                      │
        ┌─────────────┼─────────────┐
        │             │             │
   ┌────▼────┐  ┌────▼────┐  ┌─────▼──┐
   │ Landing │  │ PHP-FPM │  │ Python │
   │ Pages   │  │  8.3    │  │  3.12  │
   │ (HTML)  │  │         │  │        │
   └─────────┘  └────┬────┘  └────┬───┘
                     │            │
                 ┌───▼────────────▼───┐
                 │ PostgreSQL 16      │
                 │ + MariaDB          │
                 │ (opcional)         │
                 └────────────────────┘
```

---

## 🚀 Fases de Implementación

### **Fase 1: Setup Base** (ACTUAL)
- [x] Instalación de software base
- [x] Configuración de Nginx
- [x] Webmin instalado (panel de administración opcional)
- [ ] Landing pages (conce.com, initech.cl)
- [ ] SSL/HTTPS (Let's Encrypt vía Certbot)
- [ ] Configuración DNS

### **Fase 2: Aplicaciones Web**
- [ ] contrastocolor.ink (Aplicación Python)
- [ ] wikipedia.cl (Aplicación PHP)
- [ ] Base de datos PostgreSQL

### **Fase 3: Optimización**
- [ ] Cache HTTP (Varnish)
- [ ] CDN (Cloudflare)
- [ ] Compresión (Brotli)
- [ ] HTTP/2 optimizado

### **Fase 4: Monitoreo & Backups**
- [ ] Logs centralizados
- [ ] Monitoreo de performance
- [ ] Backups automáticos
- [ ] Alertas

---

## 📊 Requisitos de Recursos

| Recurso | Especificación | Uso Estimado |
|---------|----------------|--------------|
| **CPU** | 2-4 cores | 20-40% (en reposo) |
| **RAM** | 4-8 GB | 40-60% (normal) |
| **Disco** | 50+ GB | 30% (con margen) |
| **Ancho de banda** | Ilimitado (OVHCloud) | 100 GB/mes (est.) |

---

## 🔍 Monitoreo

### Métricas a Rastrear
- **Uptime:** > 99.5%
- **Latencia:** < 200ms (P95)
- **Errores HTTP:** < 1% (5xx)
- **CPU:** < 80%
- **RAM:** < 80%
- **Disco:** < 85%

### Logs
- **Access Log:** `/var/log/nginx/[dominio]/access.log`
- **Error Log:** `/var/log/nginx/[dominio]/error.log`
- También visibles desde Webmin (módulo Nginx Webserver), si está instalado
- **Syslog:** `/var/log/syslog`

---

## 🔄 Mantenimiento

### Diario
- Monitorear logs de error
- Verificar espacio en disco
- Verificar uptime

### Semanal
- Revisar uso de recursos (CPU, RAM)
- Validar backups
- Revisar seguridad (fail2ban)

### Mensual
- Actualizar sistema (`apt update && apt upgrade`)
- Revisar certificados SSL
- Auditoría de seguridad
- Optimizar bases de datos

---

## 📞 Contacto & Soporte

- **Email:** admin@initech.cl
- **Proveedor VPS:** OVHCloud Support
- **Monitoreo:** Alertas automáticas

---

**Última actualización:** 2026-09-20
**Versión:** 2.0.0
