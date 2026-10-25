# url-routing

Routing de URLs para los sitios de **genealogía** y **POS** (Contrastocolor), que son la misma aplicación PHP: el `Host` de cada petición decide cuál se sirve. El tipo de recurso se infiere de la **forma** del primer segmento de la URL (cantidad de dígitos, letras), no de palabras.

Este archivo solo orienta. La documentación vive en [`docs/`](docs/); el esquema de URLs, que es lo que más se consulta, está en [`docs/README_URLS.md`](docs/README_URLS.md).

## Qué carpeta se publica

**Solo `public/`.** El servidor web apunta ahí (`root /var/www/url-routing/public`, ver `deploy/nginx.conf.example`); el resto del proyecto, incluido este archivo, no se sirve. No pongas nada en `public/` que no deba ser público.

## Requisitos

PHP 8.1 o más, con `pdo`, `pdo_pgsql` (y `pdo_sqlite` si algún sitio usa SQLite), `mbstring` y `ctype`. Composer, solo para instalar las dependencias.

## Pruebas

```bash
composer install
./vendor/bin/phpunit
```

La suite corre siempre contra SQLite en memoria y, si defines `TEST_PG_DSN`, de nuevo contra PostgreSQL (así lo hace la CI). Detalle en [`docs/DATABASE.md`](docs/DATABASE.md).

## Scripts de `bin/`

| Script | Para qué |
|---|---|
| `php bin/migrate.php <sitio> [--seed]` | Aplica las migraciones de un sitio (`genealogy` o `pos`); `--seed` carga datos de demostración (solo desarrollo). |
| `php bin/rutas.php > docs/RUTAS.md` | Regenera la lista de rutas desde `config/routes/`. No edites `RUTAS.md` a mano. |
| `php bin/fusionar.php <duplicado> <vigente> [--confirmar]` | Fusiona una persona duplicada en la que queda; sin `--confirmar` solo muestra qué movería. |
| `php bin/pedidos-retiro.php [--ocultar] [--horas=N]` | Lista los pedidos de retiro que esperan al propietario; pensado para cron (no imprime nada si no hay). |

La base de datos sale de `DB_DSN_<SITIO>` / `DB_DSN` (o de `.env`), igual que en la aplicación web.

## Documentación

| Documento | Contenido |
|---|---|
| [`docs/README_URLS.md`](docs/README_URLS.md) | El esquema de URLs y la estructura de archivos |
| [`docs/URL_STRUCTURES.md`](docs/URL_STRUCTURES.md) | La gramática de formatos, con ejemplos |
| [`docs/RUTAS.md`](docs/RUTAS.md) | Todas las rutas (generado) |
| [`docs/ACCESO.md`](docs/ACCESO.md) | Qué es privado y quién puede verlo (`OWNER_TOKEN`; no hay cuentas de usuario) |
| [`docs/DATABASE.md`](docs/DATABASE.md) | Una base de datos por sitio, SQLite o PostgreSQL |
| [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md) | Cómo ponerlo en un servidor y comprobar que quedó bien |
