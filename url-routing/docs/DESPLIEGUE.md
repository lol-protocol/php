# Despliegue

Cómo poner `url-routing` en un servidor y comprobar que quedó bien. Cubre los dos sitios (genealogía y POS), que son la misma aplicación: el `Host` de cada petición decide cuál se sirve.

**Qué está probado y qué no.** Los pasos 1 a 4 y las comprobaciones del paso 6 se ejecutaron contra el servidor integrado de PHP, con bases SQLite y PostgreSQL (ver «Cómo se probó» al final). La configuración de nginx, la de Apache y HTTPS **no** se probaron aquí: están marcadas donde aparecen.

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
php bin/migrate.php genealogy
php bin/migrate.php pos
```

**Sin `--seed`**: esa opción carga datos de demostración y es solo para desarrollo. El comando aplica las migraciones pendientes y dice cuáles:

```
genealogy (pgsql): aplicadas genealogy/001_esquema_inicial, genealogy/002_personas_viva
```

Es seguro repetirlo: lo ya aplicado no se vuelve a aplicar (`sin migraciones pendientes`). Cada migración corre en una transacción.

### El usuario del propietario

Las páginas de cuenta (`/0/`) actúan sobre una cuenta fija: el **usuario 1** de la base de cada sitio. Una base nueva no lo tiene, y sin él `/0/` responde 404 aunque la clave sea la correcta. Créalo en cada sitio:

```bash
psql "dbname=genealogia user=genealogia_app host=127.0.0.1" \
  -c "INSERT INTO usuarios (id, email, nombre) VALUES (1, 'tu@correo.com', 'Tu nombre')"

sqlite3 /var/lib/url-routing/pos.sqlite \
  "INSERT INTO usuarios (id, email, nombre) VALUES (1, 'tu@correo.com', 'Tu nombre')"
```

Ese usuario es también el autor de las colecciones y de los aportes que veas en `/0/`.

## 5. El servidor web

La raíz del sitio es **`public/`**. Si apuntara a la carpeta del proyecto, cualquiera podría bajar `.env` y las bases SQLite, y ejecutar `bin/migrate.php`.

**Nginx** (no probado aquí): copia [`deploy/nginx.conf.example`](../deploy/nginx.conf.example), ajusta `server_name` y el socket de PHP-FPM, y activa HTTPS (con certbot: `sudo certbot --nginx -d tudominio.com`). Nginx pasa la cabecera `Authorization` a PHP-FPM por sí solo; no agregues un `fastcgi_param` que la borre.

**Apache** (no probado aquí): `AllowOverride All`, y los módulos `rewrite` y `headers`. El `.htaccess` de `public/` trae la regla que entrega `Authorization` a PHP; si el paso 6 da 401 con la clave correcta, empieza por ahí.

Después de cambiar la configuración, recarga el servicio (`sudo nginx -t && sudo systemctl reload nginx`) y PHP-FPM (`sudo systemctl reload php8.3-fpm`).

## 6. Comprobar que quedó bien

Con `$D` = tu dominio y `$T` = el valor de `OWNER_TOKEN`:

```bash
curl -s https://$D/health                        # {"status":"ok",...}
curl -s -o /dev/null -w '%{http_code}\n' https://$D/            # 200
curl -s -o /dev/null -w '%{http_code}\n' https://$D/0/          # 401: pide la clave
curl -s -o /dev/null -w '%{http_code}\n' -u x:$T https://$D/0/  # 200 (404 si falta el usuario 1: ver paso 4)
curl -sI -u x:$T https://$D/0/ | grep -i -E 'cache-control|vary'
```

Debe verse `Cache-Control: private, no-store` y `Vary: Authorization`: lo que depende de quién pregunta no se guarda en cachés compartidas.

Y que lo que no debe servirse, no se sirve:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://$D/.env             # 404
curl -s -o /dev/null -w '%{http_code}\n' https://$D/bin/migrate.php  # 404
curl -s -o /dev/null -w '%{http_code}\n' https://$D/composer.json    # 404
```

| Si ves… | Probablemente |
|---|---|
| `/0/` da **404** | Con la clave correcta y el mensaje «No existe el usuario 1»: falta crear el usuario (paso 4). Sin ese mensaje: `OWNER_TOKEN` falta o tiene menos de 20 caracteres (el log lo avisa con un *warning*) |
| `/0/` da **401** con la clave correcta | El servidor no entrega la cabecera `Authorization` a PHP (regla de Apache, o un proxy que la quita). Con la clave equivocada también da 401: comprueba que no tiene espacios ni saltos de línea |
| Un error de base de datos | El DSN, el usuario o los permisos del directorio de SQLite |
| Un `.env` descargable | La raíz web no es `public/` |

## 7. Usarlo como propietario

- **Navegador:** abre `https://$D/0/`; pide usuario y clave (el usuario da igual, la clave es `OWNER_TOKEN`) y abre una sesión. La sesión caduca a la hora sin actividad y a las 12 horas.
- **Marcar a quien vive:** en la ficha de una persona aparece **Privacidad**, solo para ti. Sirve para quien vive pero no tiene fechas, o quien murió sin defunción registrada. Ver [`ACCESO.md`](./ACCESO.md).
- **`curl` o scripts:** `curl -u x:$T …` o `Authorization: Bearer $T`.

## 8. Actualizar

```bash
cd /var/www/php && git pull
cd url-routing
composer install --no-dev --optimize-autoloader
php bin/migrate.php genealogy && php bin/migrate.php pos
sudo systemctl reload php8.3-fpm        # vacía el opcache
```

## 9. Rotar o quitar el token

Cambia `OWNER_TOKEN` en `.env` y recarga PHP-FPM. **Cierra todas las sesiones abiertas**, incluida la tuya. Si lo quitas, vuelve a quedar todo lo privado cerrado para todos.

## 10. Copias de seguridad

- PostgreSQL: `pg_dump -Fc genealogia > genealogia-$(date +%F).dump`
- SQLite: `sqlite3 /var/lib/url-routing/pos.sqlite ".backup '/ruta/pos-$(date +%F).sqlite'"`. No copies el archivo suelto con `cp`: con WAL puede quedar incompleto.

Aquí no hay copia automática ni se probó la restauración: es parte de lo que falta antes de abrir la plataforma al público (ver [`PRIVACIDAD-Y-HONRA.md`](../../PRIVACIDAD-Y-HONRA.md)).

## Cómo se probó

Con el código de esta rama, en un directorio aparte (`git archive` + `composer install --no-dev --optimize-autoloader`) y una base **vacía**, sin la demostración:

1. Un `.env` con los valores del paso 3: PostgreSQL para genealogía y SQLite para el POS.
2. `php bin/migrate.php` de ambos sitios; otra vez para ver `sin migraciones pendientes`; y sobre una base que solo tenía la migración 001, para ver que aplica la 002.
3. El `INSERT` del usuario 1: el de `psql` tal como está escrito (con el puerto de la prueba); el de SQLite por PDO, porque el cliente `sqlite3` no estaba instalado en el entorno de prueba y **ese comando no se ejecutó**.
4. El servidor integrado de PHP (`php -S … -t public public/index.php`) y los `curl` del paso 6, con y sin clave, en genealogía y en el POS, más una sesión con cookie.

De ahí salieron dos correcciones que ya están en la guía: sin el usuario 1, `/0/` daba 404 con la clave correcta (ahora dice qué falta, y el paso 4 lo crea), y `composer install --optimize-autoloader` imprimía avisos de PSR-4 por las clases de `tests/` (ahora se excluyen del mapa de clases).

Lo que ese entorno no puede probar: nginx, Apache, HTTPS y PHP-FPM.
