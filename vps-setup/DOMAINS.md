# 🌐 Dominios: cómo se organizan, agregan y quitan

Un VPS sirve varios dominios a la vez, cada uno con su sitio, sus logs y su certificado. Este
documento explica cómo quedan organizados y cómo gestionarlos con los scripts. El despliegue
inicial está en [DEPLOYMENT.md](DEPLOYMENT.md).

> La versión anterior (configuraciones de Nginx y systemd para pegar a mano) está en
> [`_Garbage/DOMAINS-manual.md`](_Garbage/DOMAINS-manual.md). No la sigas: los scripts no
> reconocen un vhost hecho a mano y se niegan a tocarlo.

---

## Cómo queda cada dominio en el servidor

**Un dominio, un sitio.** Cada dominio tiene un solo vhost en `/etc/nginx/sites-available/<dominio>`,
con `server_name <dominio> www.<dominio>`. Su primera línea es una marca que dice qué script lo creó:

```nginx
# vps-setup: 06_A-php tienda
```

Re-correr el mismo script para el mismo dominio vale (es idempotente). En cambio, crear otro tipo
de sitio (u otra app) sobre un dominio que ya tiene uno falla con un mensaje que dice cómo quitar el
anterior; lo mismo si otro sitio activo ya declara ese `server_name`. También falla si el vhost
existe pero se hizo a mano (sin marca).

| Tipo | Script | Archivos | Proceso |
|------|--------|----------|---------|
| Landing estática | `03` | `/var/www/landing-page/<dominio>/` (copia de `landing-page/` del repo) | Nginx; `.php` responde 404 |
| App PHP | `06_A <app> <dominio>` | `/var/www/<app>/` | PHP-FPM 8.3 (`/run/php/php8.3-fpm.sock`) |
| App Python | `06_B <app> <dominio> [puerto]` | `/var/www/<app>/` con su `venv/` | servicio systemd `<app>` (gunicorn en `127.0.0.1:<puerto>`) |
| App Java | `06_D <dominio> [context]` | `.war` en `/var/lib/tomcat10/webapps/` | Tomcat en `127.0.0.1:8080`; `/manager` da 404 si la app va en la raíz |

Los logs de cada dominio van en `/var/log/nginx/<dominio>/{access,error}.log` y rotan a diario
(`07_D`). Los headers de seguridad, gzip y el vhost por defecto (que corta peticiones por IP o con un
Host ajeno) son globales, en `/etc/nginx/conf.d/` (`07_B`, `07_E`): valen para todos los dominios.

Cada app Python necesita **su propio puerto** (por defecto 8000). `06_B` rechaza uno que ya use otra
app o en el que algo ya escuche.

## Agregar un dominio: `10_A-add-domain.sh`

```bash
./10_A-add-domain.sh nuevo.com --ssl --email tu@email.com                    # landing + HTTPS
./10_A-add-domain.sh tienda.nuevo.com --type php --name tienda --ssl         # app PHP
./10_A-add-domain.sh api.nuevo.com --type python --name api --port 8001      # app Python, SSL después
./10_A-add-domain.sh java.nuevo.com --type tomcat --context miapp            # app Java
./10_A-add-domain.sh nuevo.com --dry-run                                     # muestra los pasos sin ejecutarlos
```

Encadena el script del tipo (`03`, `06_A`, `06_B` o `06_D`), luego `04` si pasas `--ssl` y por último
el healthcheck (`08`). Sin `--name`, la app se llama como el dominio con `-` en vez de `.`.
**Antes de `--ssl`, el DNS ya debe apuntar al VPS** (ver abajo); si no, deja el `--ssl` y corre `04` después.

## Quitar un dominio: `10_B-remove-domain.sh`

```bash
./10_B-remove-domain.sh nuevo.com --dry-run      # qué se archivaría
./10_B-remove-domain.sh nuevo.com --yes
```

**No borra nada**: mueve el vhost, el sitio y los logs a
`/var/backups/vps-setup/removed/<dominio>-<fecha>/`. En una app PHP o Python también archiva
`/var/www/<app>` y detiene y archiva su servicio; la app la detecta por la marca del vhost (o la
indicas con `--app`). Antes de mover nada comprueba `nginx -t`: si falla, restaura el sitio y no
archiva. Para restaurar un dominio, mueve las carpetas de vuelta y reactiva el enlace en `sites-enabled`.

`10_B` **no toca** el certificado ni el DNS. Al terminar indica los comandos:

```bash
sudo certbot delete --cert-name nuevo.com
```

## DNS

### En tu registrador (lo normal)

Para cada dominio: registros **A** de `@` y `www` hacia la IP del VPS y, recomendado, un **CAA**
`0 issue "letsencrypt.org"` (solo Let's Encrypt puede emitir certificados para el dominio). La
tabla y cómo verificar la propagación están en [DEPLOYMENT.md](DEPLOYMENT.md#2-dns-apunta-el-dominio-al-vps).

### Servidor DNS propio: `06_C-setup-dns-server.sh`

Solo si tu registrador **no** permite editar registros. Monta BIND en el VPS como servidor
autoritativo del dominio, con OVH como secundario:

```bash
./06_C-setup-dns-server.sh nuevo.com [<IP_DEL_VPS>] [sdns2.ovh.ca]
VPS_IP6=2001:db8::5 ./06_C-setup-dns-server.sh nuevo.com     # añade también registros AAAA
```

Sin IP usa la pública detectada. Crea la zona (`ns1`, `@` y `www` hacia el VPS, CAA de Let's
Encrypt, AAAA si das `VPS_IP6`) con un serial que siempre sube, la valida con `named-checkzone`,
solo permite la transferencia de zona al secundario de OVH y deja BIND **sin recursión** (no sirve
como resolvedor abierto). Al final indica qué registrar en el panel del dominio: el glue record de
`ns1.<dominio>` y los dos nameservers.

## SSL

`04-setup-ssl.sh <dominio> [email]` pide un certificado para `<dominio>` y `www.<dominio>` y deja la
redirección HTTP→HTTPS. Antes comprueba que **los dos** nombres apunten al VPS: certbot rechaza el
certificado entero si uno falla. Sin terminal y con el DNS mal, aborta en vez de gastar un intento
(Let's Encrypt limita los intentos fallidos por semana). Opciones: `--force` sigue igualmente y
`--staging` pide un certificado de prueba (no válido en navegadores) sin ese límite.

La renovación es automática (`certbot.timer`). Para comprobarla: `sudo certbot renew --dry-run`; el
healthcheck y el monitor avisan si un certificado está por vencer.

## Mover un dominio a otro servidor

1. En el servidor nuevo: `10_A-add-domain.sh <dominio>` (sin `--ssl`) y copia los datos de la app.
2. Baja el TTL de los registros A unas horas antes y luego cámbialos a la IP nueva.
3. Cuando el DNS apunte al servidor nuevo: `04-setup-ssl.sh <dominio>` allí.
4. En el servidor viejo: `10_B-remove-domain.sh <dominio>` y `certbot delete --cert-name <dominio>`.

## Si algo falla

| Síntoma | Causa probable | Qué hacer |
|---------|----------------|-----------|
| `ya tiene un sitio (...)` | El dominio ya tiene otro tipo de sitio u otra app | `10_B-remove-domain.sh <dominio>` y vuelve a crearlo |
| `ya tiene un sitio (creado a mano)` | El vhost no lleva la marca `# vps-setup:` | Revisa ese archivo; muévelo fuera de `sites-available` si sobra |
| `el puerto N ya lo usa <app>` | Dos apps Python en el mismo puerto | Pasa otro puerto (`06_B` arg. 3, `10_A --port`) |
| `04` aborta por el DNS | `dominio` o `www` todavía no apuntan al VPS | `dig +short <dominio>` y `www.<dominio>`; espera y repite |
| Un dominio quitado responde 404 un momento | El `reload` de Nginx es asíncrono | Espera unos segundos: después corta la conexión como cualquier Host ajeno |
| 502 en una app | El proceso de la app no responde | `systemctl status <app>` (Python) o `systemctl status tomcat10` (Java) |
