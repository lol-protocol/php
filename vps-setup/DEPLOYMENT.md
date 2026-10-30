# 🚀 Despliegue de punta a punta

Cómo pasar de un VPS recién creado a los sitios funcionando, usando los scripts de esta carpeta.
Qué hace cada script está en el [README](README.md); aquí está el **orden** y lo que haces tú.

> La versión anterior de este documento (pasos manuales con `nano`/`ln -sf`/`certbot`) está en
> [`_Garbage/DEPLOYMENT-manual.md`](_Garbage/DEPLOYMENT-manual.md). No la sigas: los vhosts creados
> a mano no llevan la marca `# vps-setup:` y después los scripts se niegan a tocarlos.

En los comandos, `<IP_DEL_VPS>`, `tudominio.com` y `tu@email.com` son tuyos.

---

## 1. Antes de crear nada

- **VPS con Ubuntu 24.04 LTS.** Es la única versión soportada: `01` se niega a correr en otra
  (Ubuntu 26.04 trae PHP 8.5 y los paquetes `php8.3-*` no existen allí).
- **Una llave SSH en tu equipo:** `ssh-keygen -t ed25519`. La privada nunca sale de tu equipo ni va al repo.
- **El dominio** y acceso al panel DNS de tu registrador.
- **Un email** para Let's Encrypt (avisos de caducidad).

## 2. DNS: apunta el dominio al VPS

En el panel de tu registrador, para cada dominio:

| Tipo | Nombre | Valor | TTL |
|------|--------|-------|-----|
| A | `@` | `<IP_DEL_VPS>` | 3600 |
| A | `www` | `<IP_DEL_VPS>` | 3600 |
| CAA *(recomendado)* | `@` | `0 issue "letsencrypt.org"` | 3600 |

El registro CAA hace que solo Let's Encrypt pueda emitir certificados para tu dominio. Si tu
registrador no permite editar registros, `06_C` monta un DNS propio con BIND (ver [DOMAINS.md](DOMAINS.md)).

La propagación suele tardar de 15 a 60 minutos (hasta 24–48 h según el registrador). Compruébala
desde tu equipo, **los dos nombres**: certbot pide el certificado para ambos y rechaza todo si uno falla.

```bash
dig +short tudominio.com A
dig +short www.tudominio.com A      # los dos deben devolver <IP_DEL_VPS>
```

No hace falta esperar a que propague para instalar: usa `--skip-ssl` (paso 4) y pide el certificado después.

## 3. Acceso: desde tu equipo con `remote-run.sh`

```bash
cd vps-setup
cp vps.env.example vps.env          # VPS_HOST=<IP_DEL_VPS>, VPS_USER, VPS_KEY (vps.env está en .gitignore)
ssh-copy-id -i ~/.ssh/id_ed25519.pub ubuntu@<IP_DEL_VPS>   # una vez, con la contraseña inicial del proveedor
```

`remote-run.sh <script> [args]` sube `vps-setup/` y `landing-page/` por SSH (solo llave) y ejecuta
el script en el VPS con terminal, así que los prompts funcionan. También puedes clonar el repo en el
VPS y correr los scripts allí: los comandos son los mismos, sin el `./remote-run.sh` delante.

## 4. Instalación

```bash
./remote-run.sh install-all.sh tudominio.com tu@email.com --dry-run     # el plan, sin ejecutar nada
./remote-run.sh install-all.sh tudominio.com tu@email.com               # de verdad
```

Opciones útiles: `--skip-ssl` (el DNS aún no apunta), `--with-webmin` (panel web, solo por túnel SSH),
`--yes` (sin confirmación) y `--resume` (tras un fallo, salta lo ya hecho).

Cada corrida deja un log en `~/.local/state/vps-setup/install-all-<fecha>.log` del VPS. Si un paso
falla, el mensaje dice cuál, dónde está el log y cómo retomar con `--resume`. Al final corre el
healthcheck: si dice **"COMPLETADO CON FALLAS EN EL HEALTHCHECK"**, revisa las líneas `[FALLA]`.
Con el DNS sin propagar, las de DNS y HTTPS son esperables.

Si usaste `--skip-ssl`, cuando el DNS apunte al VPS:

```bash
./remote-run.sh 04-setup-ssl.sh tudominio.com tu@email.com
```

`04` comprueba antes el DNS y, sin terminal y con el DNS mal, **aborta** en vez de gastar un intento
de Let's Encrypt (hay un límite semanal). `--staging` pide un certificado de prueba sin ese límite.

## 5. Endurecer y vigilar (recomendado)

```bash
# SSH solo con llave y sin root. ANTES comprueba en otra terminal que entras con la llave.
./remote-run.sh 07_C-harden-ssh.sh "$(cat ~/.ssh/id_ed25519.pub)" --yes

# Alertas cada 15 min (disco, RAM, carga, certificados, servicios caídos) a un webhook y/o email
./remote-run.sh 09_A-setup-monitoring.sh 'https://hooks.slack.com/...' tu@email.com
```

Si instalaste Webmin, **no hay puerto abierto**: escucha solo en el localhost del VPS. Desde tu equipo:

```bash
ssh -L 10000:localhost:10000 ubuntu@<IP_DEL_VPS>     # deja esa terminal abierta
# navegador: https://localhost:10000  (certificado autofirmado: acepta el aviso)
```

## 6. Más dominios y aplicaciones

Todo con `10_A-add-domain.sh` (detalle en [DOMAINS.md](DOMAINS.md)):

```bash
./remote-run.sh 10_A-add-domain.sh otro.com --ssl --email tu@email.com                   # otra landing
./remote-run.sh 10_A-add-domain.sh tienda.otro.com --type php --name tienda --ssl         # app PHP
./remote-run.sh 10_A-add-domain.sh api.otro.com --type python --name api --port 8001 --ssl # app Python
./remote-run.sh 10_A-add-domain.sh java.otro.com --type tomcat --context miapp --ssl      # app Java (requiere 02_A + 02_H)
```

Extras que no instala `install-all`: MariaDB (`02_G`), Tomcat (`02_H`, requiere `02_A`) y
Whisper (`02_I`, descarga varios GB).

## 7. Base de datos de una app

Las **apps Python** (`06_B`) ya traen la suya: el script crea, si PostgreSQL está instalado, el rol y la base
`app_<nombre>` con una contraseña aleatoria, y deja la conexión en `/var/www/<app>/.env` (modo 600, solo la lee el
usuario de esa app) como `DATABASE_URL=postgresql://app_<nombre>:<clave>@localhost:5432/app_<nombre>`. El servicio
la recibe como variable de entorno. La clave nunca se imprime ni queda en el historial, y re-correr el script no la
cambia. Cada rol solo puede entrar a su base (se revoca `CONNECT` a los demás). `APP_DB=0` omite todo esto.

Para una app **PHP** (hasta que tenga su propio usuario) o una base extra, a mano:

```bash
sudo -u postgres createuser --pwprompt tienda      # pide la contraseña: no la escribas en el historial
sudo -u postgres createdb --owner=tienda tienda
```

PostgreSQL solo escucha en `localhost`: la app se conecta a `host=localhost dbname=tienda user=tienda`.
Guarda la contraseña en la configuración de la app (por ejemplo un `.env` fuera de la carpeta
pública y legible solo por el usuario de la app), nunca en el repo.

## 8. Verificar

```bash
./remote-run.sh 08-healthcheck.sh tudominio.com
```

Revisa servicios, UFW, puertos, DNS (`dominio` y `www`), HTTPS, la redirección HTTP→HTTPS, la
caducidad del certificado y los headers de seguridad. Sale con código 1 si algo falla.

## 9. Mantenimiento

**Automático** (lo dejan configurado los scripts):

- Parches de seguridad diarios (`unattended-upgrades`, `07_A`). No reinicia solo.
- Renovación de certificados (`certbot.timer`, dos veces al día).
- Rotación diaria de los logs de cada dominio, 14 días (`07_D`).
- Monitoreo cada 15 minutos, si corriste `09_A`.

**A mano:**

- Reinicio cuando un parche lo pida: `ls /var/run/reboot-required` (si existe, reinicia en un momento tranquilo).
- Actualizar la landing tras cambiar `landing-page/`: `./remote-run.sh 05-deploy-landing-page.sh tudominio.com`.
- Quitar un dominio: `10_B-remove-domain.sh` (archiva, no borra; ver [DOMAINS.md](DOMAINS.md)).

## 10. Si algo falla

| Síntoma | Qué mirar |
|---------|-----------|
| `install-all` se detuvo | El mensaje indica el paso y el log; corrige y repite con `--resume` |
| Un sitio no carga | `sudo nginx -t`, `sudo tail /var/log/nginx/<dominio>/error.log` |
| App Python caída | `sudo systemctl status <app>`, `sudo journalctl -u <app> -n 50` |
| Certificado no se emite | DNS de `dominio` **y** `www`: `dig +short`; prueba el flujo con `04 ... --staging` |
| Te quedaste fuera por SSH | Consola de rescate del proveedor; `./07_C-harden-ssh.sh --revert` |

Más casos en el [README](README.md#-troubleshooting).
