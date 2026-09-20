# VPS Setup Scripts

Scripts automáticos, uno por paso, para configurar un VPS Ubuntu 24 LTS con:
- **CloudPanel** (panel de control web, alternativa gratuita a cPanel) — administra Nginx, PHP, MariaDB/MySQL y SSL
- Extras opcionales: Java, PostgreSQL, Apache Tomcat, Python + Whisper, servidor DNS propio

## 🎛️ ¿Por qué CloudPanel y no cPanel/Virtualmin?

- **cPanel** requiere licencia paga.
- **Virtualmin** quiere controlar todo el servidor (Apache/Nginx, correo, DNS, base de datos) desde su propio instalador — no convive bien con configuraciones hechas a mano y complica más de lo necesario para este caso.
- **CloudPanel** es gratis (Community Edition), usa **Nginx de forma nativa** (no Apache), tiene interfaz web moderna, SSL con un clic (Let's Encrypt integrado) y soporta sitios PHP, Python, Node.js, estáticos y reverse proxy — todo administrable también por línea de comandos (`clpctl`), que es lo que usan estos scripts.

⚠️ **Requisito importante:** CloudPanel necesita un servidor **limpio**, sin Nginx/Apache ya instalados. Por eso esta versión de los scripts ya no instala Nginx/PHP/Certbot/MariaDB a mano — todo eso lo hace CloudPanel por su cuenta.

## 📐 Convención de nombres

Los archivos se numeran `NN` o `NN_LETRA`:

- **El número (`01`, `02`, `03`...) es secuencial** — indica el orden en que deben ejecutarse los grupos de pasos.
- **La letra (`_A`, `_B`, `_C`...) NO es secuencial** — agrupa pasos que comparten el mismo número porque son **independientes entre sí**, salvo que el script diga explícitamente "requiere X" (ahí sí hay una dependencia real de software, no de orden arbitrario).

## 🚀 Uso Rápido

### Opción 1: Instalación Automática (Recomendado)

```bash
chmod +x *.sh
./install-all.sh initech.fun
```

Instala CloudPanel, crea el sitio para tu dominio y despliega la landing page. El SSL se hace aparte (paso 04_D) porque necesita que el DNS ya haya propagado.

### Opción 2: Paso a Paso

```bash
chmod +x *.sh

# --- 01: prerequisito único ---
./01-system-update.sh

# --- 02: panel de control (instala Nginx+PHP+MariaDB+SSL internamente) ---
./02-install-cloudpanel.sh
# Entra a https://TU_IP:8443 y crea el usuario admin de inmediato

# --- 04_A: crear el sitio para tu dominio ---
./04_A-add-site-php.sh initech.fun

# --- 05: subir la landing page al sitio recien creado ---
./05-deploy-landing-page.sh initech.fun
curl http://initech.fun                     # Prueba SIN SSL primero

# --- 04_D: SSL (necesita DNS ya propagado) ---
./04_D-install-ssl-cloudpanel.sh initech.fun
```

## ⚠️ Importante: DNS primero (para el paso 04_D)

Antes de pedir el certificado SSL, tu dominio debe apuntar a la IP del VPS:

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
| 01 | `01-system-update.sh` | Actualiza APT e instala utilidades base (git, curl, cron, build-essential) |
| 02 | `02-install-cloudpanel.sh` | Instala CloudPanel (Nginx + PHP + MariaDB/MySQL + Let's Encrypt), todo administrado por el panel |
| 03_A | `03_A-install-java.sh` | *(Opcional)* Instala Java 21 (OpenJDK) |
| 03_B | `03_B-install-python.sh` | *(Opcional)* Instala Python 3 + pip + venv (fuera de CloudPanel, para scripts/uso general) |
| 03_C | `03_C-install-postgresql.sh` | *(Opcional)* Instala PostgreSQL — CloudPanel solo trae MySQL/MariaDB, así que esto es aparte |
| 03_D | `03_D-install-tomcat.sh` | *(Opcional)* Instala Apache Tomcat — requiere `03_A` (Java) |
| 03_E | `03_E-install-python-whisper.sh` | *(Opcional)* Instala Whisper + ffmpeg + librerías Python en un venv — requiere `03_B` (Python) |
| 04_A | `04_A-add-site-php.sh` | Crea un sitio PHP en CloudPanel (`clpctl site:add:php`) — úsalo para la landing page o cualquier app PHP |
| 04_B | `04_B-add-site-python.sh` | *(Opcional)* Crea un sitio Python en CloudPanel (`clpctl site:add:python`) |
| 04_C | `04_C-add-site-reverse-proxy.sh` | *(Opcional)* Crea un sitio reverse-proxy en CloudPanel — para exponer Tomcat u otra app que escuche en un puerto local |
| 04_D | `04_D-install-ssl-cloudpanel.sh` | Pide certificado SSL vía CloudPanel (verifica DNS antes) |
| 05 | `05-deploy-landing-page.sh` | (Re)copia los archivos de la landing page al sitio creado con `04_A` |
| 06_A | `06_A-setup-dns-server.sh` | *(Opcional)* Instala BIND9 como servidor DNS propio — solo si tu registrador **no** tiene gestión de registros DNS (A/CNAME/TXT) |
| — | `install-all.sh` | Ejecuta 01 → 02 → 04_A → 05 en orden (el stack mínimo para tener la landing page respondiendo por HTTP) |

Todo lo marcado *(Opcional)* se corre a mano, solo si lo necesitas, en cualquier orden entre sí (salvo las dependencias de software indicadas).

## ✅ Verificación Post-Instalación

```bash
# CloudPanel
sudo clpctl site:list

# Panel web
https://TU_IP:8443

# Landing page
curl http://tudominio.com
ls -la /home/TU_SITE_USER/htdocs/tudominio.com/public/
```

## 🆘 Troubleshooting

**`02-install-cloudpanel.sh` falla con "ya hay Nginx/Apache instalado":** el VPS ya no está limpio. Reinstala el VPS desde el panel de tu proveedor (Ubuntu 24.04) y vuelve a empezar desde `01`.

**`curl http://tudominio.com` no responde:** revisa que el sitio exista con `sudo clpctl site:list` y que `05-deploy-landing-page.sh` haya corrido sin errores.

**El paso `04_D` (SSL) falla:** el DNS aún no propaga. Espera y vuelve a intentar.

## 🎯 Próximos Pasos (opcionales)

```bash
./04_B-add-site-python.sh miapp.dominio.com          # Sitio Python (Flask/FastAPI)
./03_D-install-tomcat.sh                              # Java app server
./04_C-add-site-reverse-proxy.sh miapp.dominio.com http://127.0.0.1:8080   # Exponer Tomcat con su propio dominio
./03_C-install-postgresql.sh                          # Si necesitas Postgres además de MariaDB
./03_E-install-python-whisper.sh                      # Transcripción de audio
```

Ver también: [ARCHITECTURE.md](../ARCHITECTURE.md), [DOMAINS.md](../DOMAINS.md), [DEPLOYMENT.md](../DEPLOYMENT.md), [TOOLS-AND-UTILITIES.md](../TOOLS-AND-UTILITIES.md)
