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

### Servidor Web
- **Nginx 1.26+**
  - Reverse proxy
  - Load balancing
  - Compresión GZIP
  - Cache HTTP

### Lenguajes & Frameworks
- **PHP 8.3** - Backend web
- **Python 3.12** - Scripts y aplicaciones
- **Java 21** - Aplicaciones empresariales (futuro)

### Base de Datos
- **PostgreSQL 16+**
  - Replicación (futuro)
  - Backups automáticos
  - Connection pooling (PgBouncer)

### SSL/TLS
- **Let's Encrypt** - Certificados gratuitos
- **Certbot** - Renovación automática
- **HTTPS Everywhere**

### Monitoreo & Logging
- **Nginx Logs** - Access & error logs
- **Syslog** - Sistema centralizado (futuro)
- **Prometheus** - Métricas (futuro)

---

## 📁 Estructura de Directorios

```
/var/www/
├── landing-page/
│   ├── conce.com/
│   │   └── index.html
│   ├── initech.cl/
│   │   └── index.html
│   └── shared/
│       ├── css/
│       ├── js/
│       └── images/
│
├── contrastocolor.ink/
│   ├── public/
│   ├── src/
│   ├── config/
│   └── requirements.txt
│
└── wikipedia.cl/
    ├── public/
    ├── app/
    ├── config/
    └── composer.json

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
└── (datos de base de datos)

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
  [Nginx - Reverse Proxy]
        ↓
   ┌─────────────────────────────────────┐
   │                                     │
   v                                     v
HTML/CSS/JS                         [PHP-FPM 8.3]
Landing Pages                       o [Python Apps]
   │                                     │
   ↓                                     ↓
/var/www/landing-page/          /var/www/{app}/
   │                                     │
   └─────────────────────────────────────┘
           ↓
    [PostgreSQL 16]
         (datos)
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
         │    Nginx (Reverse Proxy) │
         │   (Virtual Hosts)        │
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
                 │ (Centralizada)     │
                 └────────────────────┘
```

---

## 🚀 Fases de Implementación

### **Fase 1: Setup Base** (ACTUAL)
- [x] Instalación de software base
- [x] Configuración de Nginx
- [x] Landing pages (conce.com, initech.cl)
- [ ] SSL/HTTPS (Let's Encrypt)
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
**Versión:** 1.0.0
