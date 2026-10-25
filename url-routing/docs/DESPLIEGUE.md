# Despliegue

Cómo poner `url-routing` en un servidor y comprobar que quedó bien. Cubre los dos sitios (genealogía y POS), que son la misma aplicación: el `Host` de cada petición decide cuál se sirve.

**Qué está probado y qué no.** Todos los pasos se ejecutaron en un servidor real: **nginx 1.24 con PHP-FPM 8.3 (como `www-data`)**, PostgreSQL 16 y SQLite, también con HTTPS (ver «Cómo se probó» al final). Esa prueba ya es un script que se repite en cada cambio: [`deploy/smoke-test.sh`](../deploy/smoke-test.sh). **No** se probaron: Apache y su `.htaccess`, un certificado emitido por una autoridad (certbot), las unidades de systemd ni PHP 8.1 o 8.2. Están marcados donde aparecen.

## 0. Antes de empezar

| Hace falta | Detalle |
|---|---|
| PHP 8.1 o más | Con las extensiones `pdo`, `pdo_pgsql` (y `pdo_sqlite` si algún sitio usa SQLite), `mbstring` y `ctype`. `php -m` las lista |
| Composer | Solo para instalar las dependencias |
| PostgreSQL | Recomendado para genealogía. SQLite basta para el POS de una tienda (ver [`DATABASE.md`](./DATABASE.md)) |
| Nginx o Apache | La raíz web es `public/`, **no** la carpeta del proyecto: ver paso 5 |
| Un certificado HTTPS | El token del propietario viaja en la cabecera `Authorization`. Sin HTTPS lo ve cualquiera en la red |
| Un secreto para el propietario | `openssl rand -hex 32` |

Si el servidor lo armó [`vps-setup/`](../../vps-setup/README.md), ya tiene nginx, PHP-FPM, PostgreSQL y el certificado (`04-setup-ssl.sh`).

## 1. El código

```bash
cd /var/www
git clone https://github.com/lol-protocol/php.git
cd php/url-routing
composer install --no-dev --optimize-autoloader
```

`--no-dev` deja fuera PHPUnit: en producción no hace falta.

## 2. Las bases de datos

Cada sitio tiene la suya (sus tablas comparten nombres). Con PostgreSQL:

```bash
sudo -u postgres psql -c "CREATE ROLE genealogia_app LOGIN PASSWORD 'una-clave-larga'"
sudo -u postgres psql -c "CREATE DATABASE genealogia OWNER genealogia_app"
```

Con SQLite, el directorio del archivo debe ser escribible por el usuario de PHP-FPM (SQLite crea además `-wal` y `-shm` junto a la base) y no puede estar dentro de `public/`:

```bash
sudo install -d -o www-data -g www-data -m 750 /var/lib/url-routing
```

## 3. El archivo `.env`

```bash
cp .env.example .env
chmod 640 .env && sudo chown root:www-data .env     # lo lee PHP-FPM; nadie más
```

Qué cambiar (el resto tiene un valor razonable; cada variable está explicada en `.env.example`):

| Variable | Qué poner |
|---|---|
| `OWNER_TOKEN` | El secreto de `openssl rand -hex 32`. Mínimo 20 caracteres. **Sin él, o más corto, todo lo privado queda cerrado para todos, también para ti** |
| `SITE_NAME_GENEALOGY` | El nombre que sale en la cabecera del sitio de genealogía. Vacío: «Genealogía» |
| `POS_DOMAINS` | Los dominios que sirven el POS, separados por comas. Cualquier otro `Host` sirve genealogía |
| `DB_DSN_GENEALOGY`, `DB_USER_GENEALOGY`, `DB_PASSWORD_GENEALOGY` | Por ejemplo `pgsql:host=127.0.0.1;port=5432;dbname=genealogia` |
| `DB_DSN_POS` | Por ejemplo `sqlite:/var/lib/url-routing/pos.sqlite` |
| `LOG_FILE` | Una ruta que PHP-FPM pueda escribir, por ejemplo `/var/log/app/app.log` (el directorio hay que crearlo) |
| `TRUSTED_PROXIES` | Solo si hay un proxy inverso delante: sus IP, para que el limitador de peticiones vea la IP real |
| `DEBUG` | `false` |

Las variables del entorno real tienen prioridad sobre `.env`.

## 4. Las migraciones

```bash
sudo -u www-data php bin/migrate.php genealogy
sudo -u www-data php bin/migrate.php pos
```

**Como el usuario de PHP-FPM (`www-data`), no como root.** SQLite crea `pos.sqlite` con el dueño de quien corre el comando: si es root, PHP-FPM puede leer la base pero no escribir, y la primera escritura falla con «attempt to write a readonly database». PostgreSQL no tiene ese problema, pero conviene hacer lo mismo en los dos. `www-data` lee el `.env` (paso 3), así que no hace falta nada más. Si ya la creaste como root: `sudo chown www-data:www-data /var/lib/url-routing/pos.sqlite*`.

**Sin `--seed`**: esa opción carga datos de demostración y es solo para desarrollo. El comando aplica las migraciones pendientes y dice cuáles:

```
genealogy (pgsql): aplicadas genealogy/001_esquema_inicial, genealogy/002_personas_viva, genealogy/003_persona_alias
```

Es seguro repetirlo: lo ya aplicado no se vuelve a aplicar (`sin migraciones pendientes`). Cada migración corre en una transacción.

### El usuario del propietario

Las páginas de cuenta (`/0/`) actúan sobre una cuenta fija: el **usuario 1** de la base de cada sitio. Una base nueva no lo tiene, y sin él `/0/` responde 404 aunque la clave sea la correcta. Créalo en cada sitio:

```bash
psql "dbname=genealogia user=genealogia_app host=127.0.0.1" \
  -c "INSERT INTO usuarios (id, email, nombre) VALUES (1, 'tu@correo.com', 'Tu nombre')"

sudo -u www-data sqlite3 /var/lib/url-routing/pos.sqlite \
  "INSERT INTO usuarios (id, email, nombre) VALUES (1, 'tu@correo.com', 'Tu nombre')"
```

Ese usuario es también el autor de las colecciones y de los aportes que veas en `/0/`.

## 5. El servidor web

La raíz del sitio es **`public/`**. Si apuntara a la carpeta del proyecto, cualquiera podría bajar `.env` y las bases SQLite, y ejecutar `bin/migrate.php`.

**Nginx** (probado con nginx 1.24 y PHP-FPM 8.3): copia [`deploy/nginx.conf.example`](../deploy/nginx.conf.example) a `sites-available`, ajusta `server_name`, la raíz y el socket de PHP-FPM, y **crea la carpeta de logs**: nginx crea los archivos de log, no la carpeta, y sin ella `nginx -t` falla.

```bash
sudo mkdir -p /var/log/nginx/url-routing
```

Después activa HTTPS (con certbot: `sudo certbot --nginx -d tudominio.com`). **Certbot no se probó**; sí HTTPS con un certificado autofirmado puesto en el mismo bloque `server`, que es donde certbot lo agrega. Nginx pasa la cabecera `Authorization` a PHP-FPM por sí solo; no agregues un `fastcgi_param` que la borre.

**Apache** (no probado aquí): `AllowOverride All`, y los módulos `rewrite` y `headers`. El `.htaccess` de `public/` trae la regla que entrega `Authorization` a PHP; si el paso 6 da 401 con la clave correcta, empieza por ahí.

**La hora de la sesión del propietario** (ver [`ACCESO.md`](./ACCESO.md)): en Debian y Ubuntu un cron borra las sesiones inactivas según el `php.ini`, que trae 24 minutos. Sube `session.gc_maxlifetime = 3600` en `/etc/php/8.3/fpm/php.ini`; sin eso la sesión se pierde antes de la hora que dice el paso 7.

Después de cambiar la configuración, recarga el servicio (`sudo nginx -t && sudo systemctl reload nginx`) y PHP-FPM (`sudo systemctl reload php8.3-fpm`).

## 6. Comprobar que quedó bien

Con `$D` = tu dominio y `$T` = el valor de `OWNER_TOKEN`:

```bash
curl -s https://$D/health                        # {"status":"ok",...}
curl -s https://$D/ready                         # {"status":"ok","db":"ok","migrations":"applied",...}
curl -s -o /dev/null -w '%{http_code}\n' https://$D/            # 200
curl -s -o /dev/null -w '%{http_code}\n' https://$D/0/          # 401: pide la clave
curl -s -o /dev/null -w '%{http_code}\n' -u x:$T https://$D/0/  # 200 (404 si falta el usuario 1: ver paso 4)
curl -sI -u x:$T https://$D/0/ | grep -i -E 'cache-control|vary|set-cookie|strict-transport'
```

Debe verse `Cache-Control: private, no-store` y `Vary: Authorization`: lo que depende de quién pregunta no se guarda en cachés compartidas. Por HTTPS, la cookie de sesión lleva `secure; HttpOnly; SameSite=Lax` y llega `Strict-Transport-Security`; por HTTP plano, la cookie sale sin `secure`.

Son dos preguntas distintas. **`/health`**: ¿responde PHP? No toca la base, así que sigue en `ok` aunque la base esté caída (es lo que quiere una sonda de «está vivo»). **`/ready`**: ¿puede servir este sitio? Pregunta a la base del sitio que elige el `Host` y si todas las migraciones están aplicadas; si algo falla da **503** y dice qué (`"db":"down"` o `"migrations":"pending"`), sin más detalle: la razón queda en `LOG_FILE`. Es lo que atrapa un despliegue que olvidó `bin/migrate.php`. Apunta ahí tu monitor, una vez por dominio. Cuenta para el limitador y no abre sesión.

Y que lo que no debe servirse, no se sirve:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://$D/.env             # 403 (nginx niega todo lo que empieza por punto)
curl -s -o /dev/null -w '%{http_code}\n' https://$D/bin/migrate.php  # 404
curl -s -o /dev/null -w '%{http_code}\n' https://$D/composer.json    # 404
```

| Si ves… | Probablemente |
|---|---|
| `/0/` da **404** | Con la clave correcta y el mensaje «No existe el usuario 1»: falta crear el usuario (paso 4). Sin ese mensaje: `OWNER_TOKEN` falta o tiene menos de 20 caracteres (el log lo avisa con un *warning*) |
| `/0/` da **401** con la clave correcta | El servidor no entrega la cabecera `Authorization` a PHP (regla de Apache, o un proxy que la quita). Con la clave equivocada también da 401: comprueba que no tiene espacios ni saltos de línea |
| Un error de base de datos | El DSN, el usuario o los permisos del directorio de SQLite. El visitante ve solo «500 - Error interno del servidor»; el detalle (sin la clave) queda en `LOG_FILE`. Al volver la base, el sitio se recupera solo |
| `/ready` da **503** | Con `"db":"down"`: la base no responde (DSN, usuario, el servicio parado). Con `"migrations":"pending"`: faltan migraciones, `sudo -u www-data php bin/migrate.php <sitio>` (paso 4) |
| `attempt to write a readonly database` en el log | La base SQLite la creó otro usuario, normalmente root: paso 4 |
| **429** | El limitador: 100 peticiones por minuto y por IP (`/health` no cuenta). Tú también, desde esa IP. La respuesta trae `Retry-After` con los segundos que faltan. Cada IP deja un archivo en el directorio temporal, y el propio limitador borra los de más de una hora (se revisa cada diez minutos). Detrás de un proxy inverso sin `TRUSTED_PROXIES`, todos los visitantes comparten la IP del proxy (se deduce del código; no se probó con un proxy) |
| Un `.env` descargable | La raíz web no es `public/` |

## 7. Usarlo como propietario

- **Navegador:** abre `https://$D/0/`; pide usuario y clave (el usuario da igual, la clave es `OWNER_TOKEN`) y abre una sesión. La sesión caduca a la hora sin actividad (cuenta cualquier página que abras, pública o privada) y a las 12 horas.
- **Marcar a quien vive:** en la ficha de una persona aparece **Privacidad**, solo para ti. Sirve para quien vive pero no tiene fechas, o quien murió sin defunción registrada. Ver [`ACCESO.md`](./ACCESO.md).
- **`curl` o scripts:** `curl -u x:$T …` o `Authorization: Bearer $T`.

## 8. Vigilar los pedidos de retiro

Cualquiera puede pedir el retiro de una página (`/r/{id}/`) y el pedido espera en `/0/3/` hasta que lo decidas: **recibirlo no oculta nada**. Para que no se quede esperando sin que lo veas, hay un script hecho para cron, que manda por correo lo que imprime y no imprime nada si no hay pedidos:

```
# /etc/cron.d/pedidos-retiro   (cron necesita un servidor de correo para mandar el aviso)
MAILTO=tu@correo.com
0 9 * * *  www-data  cd /var/www/php/url-routing && php bin/pedidos-retiro.php
```

Los pedidos de retiro se marcan con el filtro de difamación si el servidor tiene la carpeta `defamatory-content-review/` junto a `url-routing/` (o `FILTRO_DIFAMACION_DIR` apunta a ella); si no, se guardan sin marcas y el log lo dice. Los topes contra el abuso se cambian con `RETIRO_MAX_POR_HORA` y `RETIRO_MAX_PENDIENTES` en `.env`.

Lista número, página, motivo y cuánto lleva cada pedido, y marca `VENCIDO` el que pasó del plazo (`RETIRO_PLAZO_HORAS` en `.env`, 24 horas por omisión). No dice quién lo pidió ni qué escribió: el correo de cron viaja sin cifrar.

**Ocultar automáticamente es opcional y es una decisión tuya.** Con `--ocultar`, el pedido que sigue pendiente pasado el plazo deja su página en 410 para los visitantes, como si hubieras elegido «ocultar mientras revisa», y el registro anota que fue automático. Nunca toca uno que ya decidiste, y rechazarlo la vuelve a mostrar. El costo: quien pida el retiro de una página puede bajarla si no contestas a tiempo. Si lo quieres, que corra más seguido que el aviso:

```
*/30 * * * *  www-data  cd /var/www/php/url-routing && php bin/pedidos-retiro.php --ocultar
```

Código de salida: 0 si nada está vencido, 1 si hay un pedido vencido sin ocultar, 2 si falla (base sin migrar, opción desconocida). Se probó con la base de SQLite y como proceso aparte (`tests/App/Integration/PedidosRetiroCliTest.php`); **no se probó con cron ni con un servidor de correo reales**.

## 9. Actualizar

```bash
cd /var/www/php && git pull
cd url-routing
composer install --no-dev --optimize-autoloader
sudo -u www-data php bin/migrate.php genealogy && sudo -u www-data php bin/migrate.php pos
sudo systemctl reload php8.3-fpm        # vacía el opcache
curl -s https://$D/ready                # debe dar "migrations":"applied"
```

## 10. Rotar o quitar el token

Cambia `OWNER_TOKEN` en `.env` y recarga PHP-FPM. **Cierra todas las sesiones abiertas**, incluida la tuya. Si lo quitas, vuelve a quedar todo lo privado cerrado para todos.

## 11. Copias de seguridad

- PostgreSQL: `pg_dump -Fc genealogia > genealogia-$(date +%F).dump`
- SQLite: `sqlite3 /var/lib/url-routing/pos.sqlite ".backup '/ruta/pos-$(date +%F).sqlite'"`. No copies el archivo suelto con `cp`: con WAL puede quedar incompleto.

Los dos comandos se probaron: la copia de SQLite abre y pasa `integrity_check`, y el volcado de PostgreSQL se restauró en otra base (`pg_restore -d otra_base --no-owner`) con los mismos recuentos de filas. Lo que falta es una copia **automática** y ensayar la restauración en el servidor real, antes de abrir la plataforma al público (ver [`PRIVACIDAD-Y-HONRA.md`](../../PRIVACIDAD-Y-HONRA.md)).

## Cómo se probó

Con el código de `master`, en un directorio aparte (`git archive` + `composer install --no-dev --optimize-autoloader`) y una base **vacía**, sin la demostración (salvo donde se dice):

1. Un `.env` con los valores del paso 3, con los permisos de la guía (`640`, `root:www-data`; otro usuario no lo lee): PostgreSQL 16 para genealogía y SQLite para el POS.
2. Las migraciones de ambos sitios, otra vez para ver `sin migraciones pendientes`, y sobre una base que solo tenía la 001. Primero como root y luego como `www-data`: de ahí sale el aviso del paso 4.
3. El `INSERT` del usuario 1 con los comandos del paso 4, `psql` y `sqlite3`.
4. **nginx 1.24** con el ejemplo del repositorio (solo cambiaron el puerto, la raíz, el socket y la carpeta de logs) y **PHP-FPM 8.3.6** corriendo como `www-data`, con solo las extensiones de la tabla del paso 0. Los `curl` del paso 6 con y sin clave, en genealogía y en el POS, con `Authorization: Basic` y `Bearer`, y una sesión con cookie.
5. HTTPS con un certificado autofirmado en el mismo bloque `server`: la cookie sale `secure`, llega `Strict-Transport-Security` y las redirecciones son relativas.
6. Con la demostración cargada, desde el navegador del propietario: marcar a una persona como viva (sin el token CSRF da 403, con él 303) y ver que un visitante pasa de ver su nombre a «Persona viva» y vuelve; «Persona privada» para quien solo está en una colección privada.
7. Fallos: la base de PostgreSQL parada (500 sin detalle en la respuesta; la clave de la base no aparece ni en la respuesta ni en el log; y el sitio se recupera al volver la base), `LOG_FILE` en una carpeta que no existe (el sitio sigue sirviendo) y `OWNER_TOKEN` de menos de 20 caracteres (`/0/` da 404 y el log avisa).
8. El limitador (100 peticiones y luego 429 con `Retry-After`), `/ready` (200 con la base al día; 503 con una migración nueva sin aplicar, y vuelve a 200 al migrar), y la recarga de PHP-FPM con la señal `USR2` (la que envía `systemctl reload`) y de nginx, con OPcache activo.
9. Los comandos de copia de seguridad del paso 11, y restaurar el volcado en una base nueva.

De ahí salieron, además de las dos correcciones de la prueba anterior (sin el usuario 1, `/0/` daba 404 con la clave correcta; y `composer install --optimize-autoloader` imprimía avisos de PSR-4 por `tests/`), tres cambios en esta guía: las migraciones y el `INSERT` de SQLite se corren como `www-data` (una base creada por root no se podía escribir), la carpeta de logs de nginx hay que crearla (sin ella `nginx -t` falla), y `/.env` da 403, no 404.

### Repetirla

```bash
cd url-routing && ./deploy/smoke-test.sh
```

Levanta nginx y PHP-FPM en puertos altos de `127.0.0.1` dentro de un directorio temporal (que borra al terminar), instala el código como en el paso 1 y recorre los pasos 2 a 6 con el ejemplo de nginx del repositorio. Después recorre el ciclo de un pedido de retiro de punta a punta: el formulario, la base, y `bin/pedidos-retiro.php` (que avisa, sale con 1 cuando vence y con `--ocultar` deja la página en 410). Necesita `nginx`, `php-fpm` de la misma versión que `php`, `composer`, `curl` y `openssl`; con `SMOKE_PG_DSN` (y `SMOKE_PG_USER`, `SMOKE_PG_PASSWORD`) usa una base PostgreSQL vacía para genealogía. Las variables están explicadas en el encabezado del script. Lo corre el job `despliegue` de `.github/workflows/pruebas-url-routing.yml`. Se probó que **falla** cuando algo está mal: con un nginx que quita la cabecera `Authorization` fallan los seis chequeos de `/0/`, y sin las reglas del ejemplo que niegan los archivos ocultos falla `/.env`.

**Lo que sigue sin probarse:** Apache y su `.htaccess` (ver [`ACCESO.md`](./ACCESO.md)), un certificado de verdad (certbot), las unidades de systemd (la recarga se probó con la señal, no con `systemctl`), PHP 8.1 y 8.2, y un proxy inverso delante con `TRUSTED_PROXIES`.
