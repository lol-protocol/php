# VPS Setup Scripts

Scripts automáticos para configurar un VPS Ubuntu 24 LTS con:
- Java 21
- PHP 8.3
- Python 3
- PostgreSQL
- Nginx + SSL/HTTPS

## 🚀 Uso Rápido

### Opción 1: Instalación Automática (Recomendado)

```bash
chmod +x install-all.sh
./install-all.sh initech.cl admin@initech.cl
```

### Opción 2: Instalación Manual

Ejecuta los scripts en orden:

```bash
chmod +x *.sh

# 1. Instalar software base
./01-initial-setup.sh

# 2. Configurar Nginx
./02-nginx-setup.sh initech.cl

# 3. Configurar SSL
./03-ssl-setup.sh initech.cl admin@initech.cl

# 4. Desplegar landing page
./04-deploy-landing-page.sh

# (Opcional) Instalar apps adicionales
./05-setup-php-app.sh              # Aplicación PHP
./06-setup-python-app.sh           # Aplicación Python
```

## ⚠️ Importante

**Antes de ejecutar el paso 3 (SSL), configura tu DNS:**

1. Apunta tu dominio a la IP del VPS
2. Espera 5-15 minutos para que se propague
3. Verifica: `nslookup initech.cl`

## 📖 Documentación Completa

Ver: [VPS-SETUP-GUIDE.md](../VPS-SETUP-GUIDE.md) (en la raíz del proyecto)

## 📦 Archivos

| Script | Función |
|--------|---------|
| `01-initial-setup.sh` | Instala Java, PHP, Python, PostgreSQL, Nginx |
| `02-nginx-setup.sh` | Configura Nginx para landing page |
| `03-ssl-setup.sh` | Obtiene certificado Let's Encrypt |
| `04-deploy-landing-page.sh` | Despliega landing page |
| `05-setup-php-app.sh` | Configura app PHP (opcional) |
| `06-setup-python-app.sh` | Configura app Python (opcional) |
| `install-all.sh` | Ejecuta todos los pasos |

## ✅ Verificación Post-Instalación

```bash
# Verificar Nginx
sudo systemctl status nginx

# Ver logs
sudo tail -f /var/log/nginx/initech.cl/error.log

# Verificar SSL
sudo certbot certificates

# Ver archivos
ls -la /var/www/landing-page/
```

## 🆘 Troubleshooting

**Error de conexión:** Verifica DNS con `nslookup dominio.com`

**Landing page no carga:** Verifica `/var/www/landing-page/` tiene archivos

**SSL no funciona:** Espera 10-15 minutos, el DNS podría aún estar propagándose

## 🎯 Próximos Pasos

- Agregar aplicaciones PHP o Python
- Configurar PostgreSQL
- Configurar backups automáticos
- Agregar más dominios

---

**¿Necesitas ayuda?** Ver documentación completa en VPS-SETUP-GUIDE.md
