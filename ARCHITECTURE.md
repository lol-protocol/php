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

### Panel de Control
- **CloudPanel** (Community Edition, gratis)
  - Administra Nginx, PHP-FPM, MariaDB/MySQL y Let's Encrypt
  - Interfaz web (`:8443`) + CLI (`clpctl`)
  - Alternativa a cPanel (de pago) y Virtualmin (quiere controlar todo el servidor)

### Servidor Web
- **Nginx** (instalado y administrado por CloudPanel)
  - Reverse proxy
  - Compresión GZIP
  - Cache HTTP
  - Un vhost por sitio, con SSL propio

### Lenguajes & Frameworks
- **PHP 8.3** - Backend web (una versión por sitio, vía CloudPanel)
- **Python 3.12** - Scripts, apps Flask/FastAPI (sitios Python vía CloudPanel) y Whisper
- **Java 21** - Apps empresariales, vía Apache Tomcat detrás de un sitio reverse-proxy de CloudPanel

### Base de Datos
- **MariaDB/MySQL** - Incluida en CloudPanel, una base por sitio (`clpctl db:add`)
- **PostgreSQL 16+** - Instalación aparte (`03_C-install-postgresql.sh`), para proyectos que la requieran específicamente

### SSL/TLS
- **Let's Encrypt** - Integrado en CloudPanel, un clic o `clpctl lets-encrypt:install:certificate`
- Renovación automática gestionada por el panel

### Monitoreo & Logging
- **Logs por sitio** - Vistos desde el panel de CloudPanel (Nginx + PHP-FPM)
- **Syslog** - Sistema centralizado (futuro)
- **Prometheus** - Métricas (futuro)

---

## 📁 Estructura de Directorios

```
/home/
├── conce/htdocs/conce.com/public/              # Landing page (conce.com)
├── initech/htdocs/initech.cl/public/           # Landing page (initech.cl)
├── contrastocolor/htdocs/contrastocolor.ink/   # App Python (Flask)
└── wikipedia/htdocs/wikipedia.cl/public/       # App PHP

# Cada carpeta /home/<siteUser>/ pertenece a un sitio creado con
# CloudPanel (04_A-add-site-php.sh / 04_B-add-site-python.sh); Nginx,
# PHP-FPM y SSL de cada uno se administran desde el panel, no a mano.

/var/lib/postgresql/
└── (datos de PostgreSQL, si se instaló aparte)

/opt/venvs/whisper/
└── (entorno virtual de Python + Whisper, si se instaló)

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
  [Nginx - administrado por CloudPanel, un vhost por sitio]
        ↓
   ┌─────────────────────────────────────┐
   │                                     │
   v                                     v
HTML/CSS/JS                         [PHP-FPM 8.3]
Landing Pages                       o [Python Apps] o [Tomcat via reverse-proxy]
   │                                     │
   ↓                                     ↓
/home/<siteUser>/htdocs/<dominio>/  /home/<siteUser>/htdocs/<dominio>/
   │                                     │
   └─────────────────────────────────────┘
           ↓
  [MariaDB/MySQL (CloudPanel)] o [PostgreSQL 16 (aparte)]
```

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
         │   CloudPanel :8443       │
         │  (Nginx + PHP + MariaDB) │
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
                 │ MariaDB/MySQL      │
                 │ (por CloudPanel)   │
                 │ + PostgreSQL 16    │
                 │ (aparte, opcional) │
                 └────────────────────┘
```

---

## 🚀 Fases de Implementación

### **Fase 1: Setup Base** (ACTUAL)
- [x] Instalación de software base
- [x] CloudPanel instalado (Nginx + PHP + MariaDB + SSL)
- [ ] Landing pages (conce.com, initech.cl)
- [ ] SSL/HTTPS (Let's Encrypt vía CloudPanel)
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
- **Access/Error Log:** panel de CloudPanel → sitio → "Logs" (Nginx + PHP-FPM por sitio)
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
