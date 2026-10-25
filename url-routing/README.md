# url-routing

Routing de URLs para dos sitios con el mismo código: el de genealogía y el POS (Contrastocolor). El tipo de recurso se infiere de la **forma** del primer segmento de la URL (cantidad de dígitos; 2 letras para un país, 3 para un idioma, 1 para una función del sistema), no de palabras: `/6128473190/` es una persona porque tiene 10 dígitos. PHP 8.1 o más, sin framework; SQLite o PostgreSQL por sitio.

Este archivo es solo la puerta de entrada: el detalle está en `docs/`.

## Empezar

```bash
composer install
php bin/migrate.php genealogy --seed     # var/genealogy.sqlite con datos de demostración
php bin/migrate.php pos --seed           # var/pos.sqlite
php -S spa.tudominio.local:8000 -t public public/index.php
php -S spa.contrastocolor.local:8001 -t public public/index.php
```

Sin `.env`, cada sitio usa un SQLite local en `var/`. Las variables están explicadas en [`.env.example`](.env.example).

## Pruebas

```bash
./vendor/bin/phpunit                     # contra SQLite
TEST_PG_DSN='pgsql:host=127.0.0.1;dbname=routing_test' \
TEST_PG_USER=routing_test TEST_PG_PASSWORD=routing_test \
./vendor/bin/phpunit                     # además contra PostgreSQL
```

La CI (`.github/workflows/pruebas-url-routing.yml`) las corre en PHP 8.1 a 8.4, contra SQLite y PostgreSQL, y además la prueba de humo del despliegue con nginx y PHP-FPM (`deploy/smoke-test.sh`).

## Documentación

| Archivo | Qué tiene |
|---|---|
| [`docs/README_URLS.md`](docs/README_URLS.md) | El esquema de URLs, la estructura de carpetas y ejemplos |
| [`docs/URL_STRUCTURES.md`](docs/URL_STRUCTURES.md) | La gramática de los formatos, los sitemaps y lo que ven los buscadores |
| [`docs/RUTAS.md`](docs/RUTAS.md) | Todas las rutas, generado desde la configuración (`php bin/rutas.php`) |
| [`docs/ACCESO.md`](docs/ACCESO.md) | Quién ve qué: el propietario, las personas vivas, los pedidos de retiro |
| [`docs/DATABASE.md`](docs/DATABASE.md) | SQLite o PostgreSQL en cada sitio, y las migraciones |
| [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md) | Instalarlo en un servidor con nginx y PHP-FPM, paso a paso |

## Scripts

- `bin/migrate.php`: aplica las migraciones de un sitio (y los datos de demostración con `--seed`).
- `bin/rutas.php`: regenera `docs/RUTAS.md`.
- `bin/fusionar.php`: fusiona dos fichas de la misma persona.
- `bin/pedidos-retiro.php`: avisa de los pedidos de retiro que esperan, para cron.
