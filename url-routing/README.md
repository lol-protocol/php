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
| `php bin/fusionar.php [grupo] <duplicado> <vigente> [--confirmar]` | Fusiona una persona (o, con `grupo`, un apellido) duplicada en la que queda y deja su id como alias con un 301; sin `--confirmar` solo muestra qué movería. |
| `php bin/persona.php acceso <id>` · `suprimir <id> [--pedido=N] [--confirmar]` · `rectificar <id> <tabla.columna>=<valor> [--suceso=N] [--pedido=N] [--confirmar]` · `rectificacion <id> <tabla.columna> [--pedido=N]` · `reaplicar [--confirmar]` | Responde a quien pide ver, corregir o borrar sus datos: imprime lo que hay de una persona, la suprime (sin `--confirmar` solo muestra qué quitaría), corrige un campo de la ficha diciendo si cambia lo que ve un visitante (sin `--confirmar` solo lo simula), anota qué campo se corrigió a mano (sin los valores) o vuelve a quitar a quien volvió con una copia de seguridad. Ver `docs/ACCESO.md`. |
| `php bin/urls-informe.php [--dias=7] [--maximo=20]` | Resume qué formas de URL dieron 404 o 301 (nunca la URL: ver `docs/URL_STRUCTURES.md`), para encontrar enlaces rotos y saber cuándo quitar una redirección vieja. |
| `php bin/orden.php <id> <estado> [--detalle="Texto"] [--guia=NUMERO]` | Avanza el estado de una orden del sitio POS (`pendiente`, `pagada`, `enviada`, `entregada`, `cancelada`, `devuelta`) y lo anota en su historial; `--guia` guarda el número de seguimiento. No devuelve el stock de una cancelada. |
| `php bin/id.php completar <número> [<ancho>]` · `verificar <id>` | Completa un id con su dígito de verificación o comprueba que lo tiene bien (ver `IDS_DIGITO_VERIFICADOR`). |
| `php bin/copias.php --destino=CARPETA [--sitios=…] [--dias=30] [--enviar='CMD {archivo}']` | La copia diaria de las bases (`pg_dump` o `VACUUM INTO`), comprobada antes de tomar su nombre, con permiso 600; borra las de más de `--dias` días y puede sacarlas del servidor con `--enviar`. Pensado para cron (no imprime nada si todo salió bien). Ver `docs/DESPLIEGUE.md`, sección 11. |
| `php bin/solicitudes.php [--ver=N \| --atender=N \| --rechazar=N \| --depurar]` | Las solicitudes de ver, corregir o borrar los datos de una persona que llegan por `/s/{id}/`: lista las que esperan (sin quién pidió ni qué escribió: pensado para cron), muestra una en tu terminal, la cierra o borra el correo y el texto de las viejas. Ver `docs/DESPLIEGUE.md`, sección 8. |
| `php bin/pedidos-retiro.php [--ocultar [--solo-ocultados]] [--horas=N]` | Lista los pedidos de retiro que esperan al propietario; pensado para cron (no imprime nada si no hay). Con `--ocultar` oculta los que pasaron del plazo; con `--solo-ocultados`, solo imprime en la corrida que ocultó algo. |

La base de datos sale de `DB_DSN_<SITIO>` (o de `.env`), igual que en la aplicación web; sin ella, un SQLite local en `var/`. No existe un `DB_DSN` compartido a propósito: los dos sitios tienen tablas con los mismos nombres (ver [`docs/DATABASE.md`](docs/DATABASE.md)).

## Documentación

| Documento | Contenido |
|---|---|
| [`docs/README_URLS.md`](docs/README_URLS.md) | El esquema de URLs y la estructura de archivos |
| [`docs/URL_STRUCTURES.md`](docs/URL_STRUCTURES.md) | La gramática de formatos, con ejemplos |
| [`docs/RUTAS.md`](docs/RUTAS.md) | Todas las rutas (generado) |
| [`docs/ACCESO.md`](docs/ACCESO.md) | Qué es privado y quién puede verlo (`OWNER_TOKEN`; no hay cuentas de usuario) |
| [`docs/DATABASE.md`](docs/DATABASE.md) | Una base de datos por sitio, SQLite o PostgreSQL |
| [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md) | Cómo ponerlo en un servidor y comprobar que quedó bien |
| [`docs/MANTENIMIENTO.md`](docs/MANTENIMIENTO.md) | Qué comando de `bin/` usar y en qué orden: cron, duplicados, pedidos de datos, despliegues |
