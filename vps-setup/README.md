# VPS Setup Scripts

Scripts automáticos, uno por paso, para configurar un VPS Ubuntu 24 LTS con:
- Java 21, PHP 8.3, Python 3, PostgreSQL, Nginx + SSL/HTTPS
- **Webmin** (panel de administración web, opcional)
- Extras opcionales: MariaDB, Apache Tomcat, Python + Whisper, servidor DNS propio

## 📚 Mapa de la documentación

| Documento | Para qué sirve |
|-----------|----------------|
| `README.md` (este) | Qué hace cada script, cómo usarlos, tests/CI, seguridad y troubleshooting |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Cómo está armado el servidor: stack, directorios, flujo de peticiones, seguridad, mantenimiento |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Despliegue completo de punta a punta (VPS nuevo → dominios → apps → base de datos) |
| [DOMAINS.md](DOMAINS.md) | Configuración por dominio: Nginx, DNS, SSL y checklists |
| [TOOLS-AND-UTILITIES.md](TOOLS-AND-UTILITIES.md) | Referencia de cada herramienta instalada (Nginx, PHP, Python, BD, Certbot, UFW...) |

`_Garbage/` guarda, sin borrarlo, lo que quedó obsoleto (ver su README).

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

## 🧰 `lib.sh`

Todos los scripts (excepto `01-system-update.sh`, que activa UFW antes de que
exista un firewall al que aplicarle reglas) sourcean `lib.sh`, que centraliza
patrones repetidos: `print_header`, `service_start_enable`, `ufw_allow`
(agrega una regla solo si UFW está realmente activo), `check_dependency`,
`setup_app_directories`, `deploy_files` (copia + permisos correctos),
`get_public_ip` (cacheada por sesión) y `verify_dns_resolution` (un solo
`dig` para dominio + www). Si corres un script suelto, `lib.sh` debe estar
en la misma carpeta.

## 🚀 Uso Rápido

### Opción 1: Instalación Automática (Recomendado)

```bash
chmod +x *.sh
./install-all.sh initech.fun admin@initech.fun
```

Instala el stack base (Java, PHP, Python, PostgreSQL, Nginx, Certbot, **Webmin**), configura el sitio, pide SSL, despliega la landing page, activa fail2ban + parches automáticos, headers de seguridad y rotación de logs, y termina con el healthcheck.

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

# --- 07: endurecimiento y operación (independientes entre sí) ---
./07_A-install-fail2ban-autoupdates.sh
./07_B-nginx-security-headers.sh
./07_D-setup-logrotate.sh

# --- 08: verificación (solo lectura) ---
./08-healthcheck.sh initech.fun

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
2. Espera a que propague (15-60 min, hasta 24-48h según el registrador)
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
| 01 | `01-system-update.sh` | Actualiza APT, instala utilidades base (git, curl, build-essential) y **activa UFW** (con SSH permitido antes de encenderlo) |
| 02_A | `02_A-install-java.sh` | Instala Java 21 (OpenJDK) |
| 02_B | `02_B-install-php.sh` | Instala PHP 8.3 + FPM + extensiones comunes |
| 02_C | `02_C-install-python.sh` | Instala Python 3 + pip + venv |
| 02_D | `02_D-install-postgresql.sh` | Instala y arranca PostgreSQL |
| 02_E | `02_E-install-nginx.sh` | Instala y arranca Nginx |
| 02_F | `02_F-install-certbot.sh` | Instala Certbot (Let's Encrypt) |
| 02_G | `02_G-install-mariadb.sh` | *(Opcional)* Instala MariaDB |
| 02_H | `02_H-install-tomcat.sh` | *(Opcional)* Instala Apache Tomcat — requiere que `02_A` (Java) ya haya corrido |
| 02_I | `02_I-install-python-whisper.sh` | *(Opcional)* Instala Whisper (OpenAI, transcripción de audio) + ffmpeg + librerías Python básicas en un venv en `/opt/venvs/whisper` — requiere que `02_C` (Python) ya haya corrido |
| 02_J | `02_J-install-webmin.sh` | Instala Webmin (panel de administración web, opcional pero incluido por defecto en `install-all.sh`). Con `WEBMIN_ALLOW_FROM=<tu IP>` el puerto 10000 solo se abre a esa IP; sin ella queda abierto a todo internet y el script lo avisa |
| 03 | `03-configure-nginx-site.sh` | Crea el virtual host de Nginx y copia la landing page |
| 04 | `04-setup-ssl.sh` | Obtiene certificado SSL (verifica DNS antes) |
| 05 | `05-deploy-landing-page.sh` | (Re)copia los archivos de la landing page |
| 07_A | `07_A-install-fail2ban-autoupdates.sh` | Instala fail2ban (jail de SSH) y actualizaciones automáticas de seguridad (sin reinicio automático) |
| 07_B | `07_B-nginx-security-headers.sh` | Agrega HSTS, X-Frame-Options, X-Content-Type-Options y Referrer-Policy a todos los dominios, oculta la versión de Nginx (`server_tokens off`) y define un vhost por defecto que corta el tráfico por IP o con un Host ajeno (archivos en `conf.d/`) |
| 07_C | `07_C-harden-ssh.sh` | *(Opcional, requiere tu llave pública)* Autoriza tu llave y desactiva login por contraseña y de root. Exige confirmar que ya probaste la llave; `--revert` lo deshace |
| 07_D | `07_D-setup-logrotate.sh` | Rota los logs de Nginx por dominio (`/var/log/nginx/<dominio>/*.log`), que el logrotate del paquete no cubre |
| 08 | `08-healthcheck.sh` | Solo lectura: revisa servicios, UFW, puertos, DNS, HTTPS, certificado y headers. Sale con código 1 si algo falla. Uso: `./08-healthcheck.sh tudominio.com` |
| 09_A | `09_A-setup-monitoring.sh` | *(Opcional)* Monitoreo cada 15 min (disco, RAM, carga, certificados, servicios) con alertas a webhook (Slack/Discord/Mattermost) y/o correo. Solo avisa cuando cambia el estado |
| 06_A | `06_A-setup-php-app.sh` | *(Opcional)* Configura una app PHP adicional |
| 06_B | `06_B-setup-python-app.sh` | *(Opcional)* Configura una app Python (Flask + Gunicorn) |
| 06_C | `06_C-setup-dns-server.sh` | *(Opcional)* Instala BIND9 como servidor DNS propio, solo autoritativo (`recursion no`) — solo si tu registrador **no** tiene gestión de registros DNS (A/CNAME/TXT) |
| 06_D | `06_D-setup-tomcat-app.sh` | *(Opcional)* Configura Nginx como reverse proxy hacia Tomcat para un dominio (con la app en la raíz, `/manager` y `/host-manager` devuelven 404) — requiere `02_H` ya hecho |
| 10_A | `10_A-add-domain.sh` | Agrega un dominio nuevo (landing/php/python/tomcat, `--ssl` opcional) encadenando los scripts anteriores |
| 10_B | `10_B-remove-domain.sh` | Quita un dominio **archivando** (no borrando) su vhost, sitio y logs |
| — | `remote-run.sh` | Desde TU equipo: sube `vps-setup/` por SSH (solo llave) y ejecuta un script en el VPS. Config en `vps.env` (ver `vps.env.example`) |
| — | `install-all.sh` | Ejecuta 01 → 02_A..F+J → 03 → 04 → 05 → 07_A → 07_B → 07_D en orden y termina corriendo 08. Admite `--dry-run`, `--resume` y `--yes` |

Los pasos `02_G`/`02_H`/`02_I` y todos los `06_*` son opcionales e independientes entre sí — instala solo los que necesites. Las notas "requiere X ya hecho" son las únicas excepciones a "cualquier orden": son dependencias reales de software, no de orden de ejecución arbitrario.

## 🔁 `install-all.sh`: dry-run y reanudación

```bash
./install-all.sh initech.fun admin@initech.fun --dry-run   # muestra el plan, no ejecuta nada
./install-all.sh initech.fun admin@initech.fun --yes       # sin confirmación
./install-all.sh initech.fun admin@initech.fun --resume    # tras un fallo: salta lo ya hecho
```

El avance se guarda por dominio en `~/.local/state/vps-setup/install-all.state`. Si un paso falla, el script
imprime el comando exacto para retomar. Sin `--resume`, una corrida nueva empieza de cero.

## 🔑 Cuando el VPS exista (checklist)

1. Genera una llave en TU equipo: `ssh-keygen -t ed25519` (la privada nunca sale de tu equipo ni va al repo).
2. Entra una vez con la contraseña inicial que da el proveedor y autoriza la llave (`ssh-copy-id`), o pásala a `07_C`.
3. Copia `vps.env.example` a `vps.env` y completa `VPS_HOST`/`VPS_USER`/`VPS_KEY` (`vps.env` está en `.gitignore`).
4. `./remote-run.sh install-all.sh tudominio.com tu@email.com --dry-run`, y luego sin `--dry-run`.
5. `./remote-run.sh 07_C-harden-ssh.sh "$(cat ~/.ssh/id_ed25519.pub)"` **después** de comprobar el login con llave desde otra terminal.
6. `./remote-run.sh 09_A-setup-monitoring.sh <webhook-url>` y `./remote-run.sh 08-healthcheck.sh tudominio.com`.

Nunca pegues contraseñas ni llaves privadas en el chat, en commits ni en `vps.env`: usa llave SSH. Si se comparte
una contraseña por error, hay que rotarla.

## 🧪 Tests y CI

```bash
bats vps-setup/tests                                  # la suite completa (usa stubs: no tocan el sistema real)
sudo -E REAL_TESTS=1 bats vps-setup/tests/real        # con nginx/logrotate/sshd REALES (escribe en /etc: solo CI o maquina desechable)
shellcheck -S warning vps-setup/*.sh vps-setup/monitoring/*.sh
```

Los tests cubren `lib.sh`, el orquestador (`--dry-run`/`--resume`/fallos/IP heredada), el healthcheck, el monitor y
los scripts 07_*/09_A, con `sudo`, `systemctl`, `curl`, etc. simulados. El workflow `.github/workflows/vps-setup.yml`
corre `bash -n`, ShellCheck y bats en cada PR que toque `vps-setup/`. Lo que **no** cubren: comportamiento real de
apt, systemd, certbot, fail2ban ni ufw — eso solo se valida en un VPS. Las pruebas de `tests/real/` sí ejecutan el Nginx, logrotate y sshd reales (vhosts con `nginx -t` y sirviendo tráfico, rotación forzada de logs, `sshd -t`/`sshd -T`), sin Docker, en el runner de CI.

## 🌐 Agregar / quitar dominios

```bash
./10_A-add-domain.sh nuevo.com --ssl --email yo@nuevo.com            # landing + HTTPS + healthcheck
./10_A-add-domain.sh app.nuevo.com --type php --name tienda          # tambien: python, tomcat (--context RUTA)
./10_A-add-domain.sh nuevo.com --dry-run                             # muestra los pasos sin ejecutar
./10_B-remove-domain.sh nuevo.com [--app tienda] [--yes] [--dry-run]
```

`10_A` encadena los scripts 03/06_A/06_B/06_D, opcionalmente 04 (`--ssl`, el DNS ya debe apuntar al VPS) y 08.
`10_B` **no borra nada**: mueve el vhost, el sitio, los logs (y con `--app` la app y su unidad systemd) a
`/var/backups/vps-setup/removed/<dominio>-<fecha>/`, comprueba `nginx -t` antes de archivar (si falla, restaura el
sitio) y al final indica cómo quitar el certificado y la zona DNS, que no toca.

## 🔒 Seguridad

Lo que los scripts endurecen: UFW activo con SSH permitido antes de encenderlo (01), fail2ban en SSH y parches
automáticos (07_A), headers de seguridad, `server_tokens off` y vhost por defecto (07_B), SSH solo con llave y sin
root (07_C, opcional), logs rotados (07_D), Tomcat solo en loopback y sin `/manager` público (02_H, 06_D), BIND solo
autoritativo (06_C), unidades systemd de apps Python con sandbox básico (06_B), descargas a directorios temporales
privados (02_J), entradas validadas con listas blancas y `remote-run.sh` solo por llave.

Límites conocidos (decisiones de diseño, no bugs):
- Todas las apps PHP/Python corren como `www-data`: una app comprometida puede leer los archivos de las demás. Para
  aislarlas hace falta un usuario y un pool de PHP-FPM por app (no incluido).
- `remote-run.sh` usa `StrictHostKeyChecking=accept-new`: confía en la huella del VPS en la primera conexión. Verifícala
  con la consola del proveedor si te preocupa un MITM en esa primera vez.
- Webmin (02_J) y el instalador `setup-repos.sh` se descargan de `raw.githubusercontent.com/.../master` sin verificar
  firma ni checksum (es el método oficial de Webmin); las librerías de `02_I`/`06_B` se instalan con `pip` sin fijar versiones.
- Los certificados y la zona DNS no se eliminan al quitar un dominio con `10_B` (se indican los comandos).

## 🛡️ Validación de entradas

Los scripts que reciben dominio, email, nombre de app, IP, context path o URL de webhook los validan con listas
blancas (`validate_*` / `require_valid` en `lib.sh`) **antes de tocar el sistema**: un valor con espacios, `;`,
`$(...)` o saltos de línea terminaría dentro de archivos de Nginx/systemd/cron o de comandos con `sudo`. Un valor
inválido sale con código 2 y un mensaje que explica el formato esperado. `remote-run.sh` valida host, usuario,
puerto y directorio remoto (un host que empiece con `-` se leería como opción de ssh).

## ✅ Verificación Post-Instalación

Lo más rápido: `./08-healthcheck.sh tudominio.com` hace todo lo de abajo (y más) de una vez.
`install-all.sh` ya lo corre al final. A mano:

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

Más documentación: ver el [mapa de la documentación](#-mapa-de-la-documentación) al inicio.
