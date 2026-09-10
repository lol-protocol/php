# Panel de Cobros, Ingresos y Funnel

Pequeño sistema en PHP (sin framework) para analizar:

- **Ingresos** devengados (boletas emitidas al usuario) vs. **cobros** reales (caja).
  Solo se registran pagos que el usuario nos hace a nosotros — no hay módulo de
  costos ni pagos propios del negocio.
- **Cartera** pendiente, con antigüedad de saldo (aging: al día / 1-30 / 31-60 / 61+ días).
- **Pagos**: por mes y por método (transferencia, tarjeta, efectivo).
- **Funnel de conversión**: visitante → registrado → lead → cliente, con tasas por
  etapa, por canal de adquisición y por país/género/rango de edad.
- **Cohortes**: qué % de cada cohorte (mes de primera visita) convirtió a cliente
  dentro de 0, 1, 2 o 3 meses.
- **Segmentación de clientes**: top país, ciudad, idioma, género y rango de edad por facturación.
- **LTV (valor de vida)** promedio por cliente, agrupado por cohorte de alta —
  histórico y sin filtrar por período, para no distorsionar la comparación entre
  cohortes viejas y nuevas.
- **Comparativo automático** de los KPIs del dashboard contra el período anterior
  y contra el mismo período del año pasado.
- **Rango de fechas personalizado**: además del selector de últimos 3/6/12 meses,
  se puede fijar un Desde/Hasta exacto en Dashboard, Cobros, Pagos, Funnel y Cohortes.
- **Multi-moneda**: cada cliente factura y paga en la moneda de su país (catálogo
  de ~200 países/territorios); los totales y gráficos agregados se consolidan a USD.
- **Clientes**: alta manual, buscador y ficha con su historial completo (boletas,
  pagos y su recorrido por el funnel si entró por ahí).
- **Boletas y pagos**: alta, edición y anulación. Anular es un soft-delete (queda
  marcada "Anulada" y se excluye de los agregados) para no perder el rastro.
- **Auditoría**: quién creó, editó o anuló cada boleta, pago o cliente, con fecha
  y el detalle de qué cambió.
- **Paginación** en los listados grandes (boletas, pagos, clientes).
- **Login** con sesión para no dejar el panel abierto a cualquiera, con bloqueo
  temporal tras varios intentos fallidos seguidos (protección de fuerza bruta).
- **Usuarios del sistema**: alta, cambio de contraseña y revocar/reactivar
  acceso (soft-delete, no se borra a nadie), todo auditado.

## Requisitos

- PHP >= 8.1 con `pdo_pgsql`
- PostgreSQL (cualquier versión reciente)
- Composer

## Instalación

```bash
composer install

# Base de datos: por defecto apunta a localhost:5432 / cobros_ingresos_funnels /
# usuario cobros_app. Si tu Postgres es distinto, exportá estas variables antes
# de seedear y de levantar el servidor (o poné las tuyas):
export DB_HOST=127.0.0.1 DB_PORT=5432 DB_NAME=cobros_ingresos_funnels \
       DB_USER=cobros_app DB_PASSWORD=cobros_app_dev

# Crear el rol y la base si todavia no existen:
psql -h $DB_HOST -U postgres -c "CREATE ROLE $DB_USER LOGIN PASSWORD '$DB_PASSWORD';"
psql -h $DB_HOST -U postgres -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"

php database/seed.php      # crea el esquema y carga datos de ejemplo
php -S localhost:8000 -t public   # solo para desarrollo, ver "Despliegue en producción"
```

Abrí `http://localhost:8000` — te va a mandar a `?page=login`. Credenciales de
ejemplo (las crea el seed): **admin@ejemplo.com / admin1234** (también
soporte@ejemplo.com / soporte1234, para probar "Usuarios" con más de una fila).

Volver a correr `php database/seed.php` en cualquier momento reconstruye el esquema
y regenera los datos de ejemplo desde cero (es reproducible: usa una semilla fija).
Las mismas variables `DB_*` tienen que estar exportadas cuando corrés el servidor,
el seed y los tests, para que los tres apunten a la misma base.

## Despliegue en producción

`php -S` (el comando de arriba) es el servidor de desarrollo embebido de PHP.
El propio manual de PHP dice que no está pensado para producción (es
mono-proceso, sin tuning de rendimiento ni manejo serio de concurrencia). Para
correr esto de verdad hace falta **PHP-FPM + un servidor web** (nginx, Caddy o
Apache) delante.

Ejemplo mínimo con nginx (asumiendo php-fpm escuchando en `127.0.0.1:9000`):

```nginx
server {
    listen 443 ssl;
    server_name tu-dominio.com;
    root /ruta/al/proyecto/public;
    index index.php;

    # TLS: certificado real (ej. Let's Encrypt/certbot) va acá.

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        # Las variables DB_* tienen que llegar al proceso de PHP-FPM, no alcanza
        # con exportarlas en la shell: se configuran en el pool de php-fpm
        # (www.conf: env[DB_HOST] = ..., etc.) o vía fastcgi_param acá mismo.
    }
}
server {
    listen 80;
    server_name tu-dominio.com;
    return 301 https://$host$request_uri;   # nginx redirige a HTTPS, no la app
}
```

Con TLS terminado en nginx, la app detecta HTTPS solo si nginx manda
`X-Forwarded-Proto: https` en el `fastcgi_param` (agregalo si no está ya en tu
`fastcgi_params`) — de eso depende que la cookie de sesión salga con `Secure`
y que se mande `Strict-Transport-Security` (ver `App\Http::esSegura()`).

Otros dos puntos que la app ya resuelve por su cuenta pero vale saber:

- **Errores**: `public/index.php` fuerza `display_errors=0` y registra un
  manejador global (`App\ErrorHandler`) que manda el detalle de cualquier
  excepción no capturada a `error_log()` — nunca a la respuesta. Dónde
  termina ese log depende de tu `php.ini`/pool de FPM (`error_log` de PHP);
  configuralo a un archivo real en producción en vez del default.
- **Backups**: no hay nada automatizado acá — es un `pg_dump` de la base
  como cualquier otra base de Postgres. Con `boletas`/`pagos`/`clientes`
  reales adentro, un backup periódico deja de ser opcional.

## Tests

```bash
composer test        # o: ./vendor/bin/phpunit
```

Hay dos suites (`tests/Unit`, sin base de datos, y `tests/Integration`, que corre
contra la base de las variables `DB_*` de arriba — corré el seed primero).

## Estructura

```
public/            front controller (index.php) + CSS
src/
  Controllers/       un controlador por sección (dashboard, cobros, pagos, funnel,
                      cohortes, clientes, auditoria, usuarios, login)
  Repositories/       un repo de CRUD por entidad (Boleta/Pago/Cliente/
                      UsuarioSistema/...) más IngresosRepository (kpis, ingresos
                      y cobros por mes, antigüedad de cartera, por método de
                      pago) y SegmentacionRepository (top país/ciudad/idioma/
                      género/edad, LTV por cohorte) para el reporting, que no es
                      CRUD y crecía por separado. AuditoriaRepository también
                      concentra el `auditarComoUsuarioActual()` que usan todos
                      los controllers en vez de repetirlo cada uno.
  Database.php         conexión PDO a PostgreSQL (singleton, config por env vars)
  Auth.php             login/logout, guard de sesión, bloqueo por fuerza bruta
  EstadoBoleta.php      calculo puro de saldo/estado de una boleta (testeado)
  Paginacion.php        helper de paginación (página/offset/total, testeado)
  Csrf.php              token CSRF por sesión, verificado en Router (testeado)
  Http.php              detecta HTTPS (directo o detras de proxy), testeado
  SecurityHeaders.php   headers de seguridad de cada respuesta, testeado
  ErrorHandler.php      red de seguridad para excepciones no capturadas
                        (loguea el detalle, nunca lo muestra), testeado
  Router.php, View.php, Filtros.php, Config.php, helpers.php
database/
  schema.sql            esquema de la base
  seed.php               generador de datos de ejemplo
  paises_monedas.php      catalogo de ~200 paises y sus monedas (ISO 4217)
views/                  plantillas PHP (una carpeta por sección), con partials
                        compartidos: _filtro_fechas.php, _paginacion.php,
                        _grafico_aging.php y _grafico_serie_mensual.php (los
                        dos graficos de barras que se repetian en dashboard,
                        cobros, pagos y funnel)
tests/
  Unit/                 sin base de datos (calculo de estado, filtros, helpers,
                        paginación, CSRF, router, headers de seguridad, deteccion
                        de HTTPS)
  Integration/           contra la base real (un archivo por repositorio,
                        auditoría, bloqueo de login, usuario revocado)
```

## Modelo de datos

- `monedas` / `paises`: catálogo de referencia (código ISO, nombre, símbolo y
  `tasa_a_usd` — cuánto vale 1 unidad de esa moneda en USD, para consolidar
  reportes). Son tasas estáticas de ejemplo, no un feed en vivo.
- `usuarios_sistema`: quién puede entrar al panel (login), con `activo` para
  revocar el acceso sin borrar al usuario (mismo patrón soft-delete que
  boletas/pagos, por la misma razón: no dejar un `usuario_id` colgado en
  `auditoria`).
- `clientes`: clientes ya convertidos (vía funnel o cartera preexistente), con
  perfil (`pais_codigo`, `ciudad`, `idioma`, `genero`, `fecha_nacimiento`) para la
  segmentación del dashboard y su moneda de facturación.
- `usuarios_funnel`: cada visitante que entra al funnel, con el mismo perfil y la
  fecha en que alcanzó cada etapa (`fecha_visita`, `fecha_registro`, `fecha_lead`,
  `fecha_conversion`) y el canal de adquisición. El perfil se genera una sola vez
  por persona y viaja a `clientes` si convierte.
- `boletas`: ingresos devengados al usuario final (monto, moneda, emisión,
  vencimiento, `anulada`) — no son facturas fiscales.
- `pagos`: cobros reales del usuario, opcionalmente ligados a una boleta
  (`boleta_id` puede ser `NULL` para anticipos/pagos sueltos) y con `anulada`.
- `auditoria`: un registro por cada alta/edición/anulación (quién, cuándo, sobre
  qué entidad y el detalle de qué cambió). Es de solo inserción — no se borra.
- `intentos_login`: contador de intentos fallidos de login por email y hasta
  cuándo queda bloqueado, para la protección de fuerza bruta.

El estado de cada boleta (pagada / parcial / pendiente / vencida / **anulada**) se
calcula dinámicamente (`App\EstadoBoleta`) a partir de sus pagos, la fecha de
vencimiento y si fue anulada — no se guarda en la base, así nunca queda
desincronizado. Una boleta o pago anulado es un **soft-delete**: la fila queda
(con su badge "Anulada" en los listados, para trazabilidad) pero se excluye de
todos los KPIs, gráficos y agregados (`AND NOT anulada` en cada consulta que suma
montos). Los KPIs y gráficos agregados suman `monto * tasa_a_usd` para consolidar
en USD; las tablas de detalle (boletas, pagos, ficha de cliente) muestran el monto
en su moneda original.

## Notas de diseño

- Sin JavaScript ni librerías de gráficos externas: los mini-gráficos son SVG
  inline (barras con `<rect>` redondeado + `<title>` como tooltip nativo),
  pensado para funcionar sin build step ni conexión a internet. El tamaño lo
  sigue resolviendo el layout flexbox existente (el SVG no lleva `viewBox`, así
  que sus coordenadas son los píxeles reales de su caja, igual que un div).
- Sigue una paleta validada para accesibilidad (contraste y daltonismo): colores
  categóricos fijos para series (ingresos/cobros), rampa secuencial para las etapas
  del funnel y las cohortes, y colores de estado reservados para la antigüedad de cartera.
- Modo oscuro automático vía `prefers-color-scheme`.
- Login con sesión de PHP nativa (`password_hash`/`password_verify`), sin roles
  ni permisos — un solo nivel de acceso. Después de loguearse te manda de vuelta
  a la página que habías pedido (`?next=`), validado contra un patrón fijo para
  que nunca sea un open redirect. Tras 5 intentos fallidos seguidos con el mismo
  email, se bloquea 15 minutos (`intentos_login`); el contador se reinicia al
  loguearse bien.
- Paginación de 25 filas por página, con `LIMIT`/`OFFSET` en SQL. En Boletas,
  como el estado (pagada/parcial/pendiente/vencida/anulada) se calcula en PHP
  a partir de los pagos aplicados y no se guarda en la base, filtrar por
  estado no se puede hacer en el `WHERE`: para ese caso puntual se trae el
  rango completo, se calcula el estado de cada fila, se filtra y recién ahí
  se pagina con `array_slice`. Es la única parte del listado que no pagina en
  SQL — el resto (sin filtro de estado, y el resto de las pantallas) sí lo
  hace. A la escala de este sistema (cientos de filas, no millones) esto es
  correcto y suficiente.
- Protección CSRF: `App\Csrf` guarda un token fijo por sesión que cada
  `<form method="post">` incluye oculto, y el `Router` lo valida antes de
  despachar cualquier POST (login incluido) — si falta o no coincide, corta
  con 403 antes de que el controller toque nada.
- Cookie de sesión endurecida: `HttpOnly` (JS no puede leerla — igual no hay
  JS en la app) + `SameSite=Lax` (capa extra contra CSRF, sumada al token) +
  `Secure` cuando el request llega por HTTPS (`App\Http::esSegura()`, que
  tambien mira `X-Forwarded-Proto` si hay un proxy adelante). En HTTP plano
  (dev local) `Secure` queda apagado a proposito, sino el browser descarta
  la cookie y no se podria loguear.
- Headers de seguridad en cada respuesta (`App\SecurityHeaders`):
  `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` y una
  `Content-Security-Policy` que bloquea JavaScript por completo
  (`script-src 'none'` — la app no usa JS en ningun lado) y permite estilos
  inline (`style-src 'unsafe-inline'`, que usan los graficos SVG). Con HTTPS
  se suma `Strict-Transport-Security`.
- Gestión de usuarios sin roles: como es un solo nivel de acceso, cualquier
  usuario logueado puede crear otros usuarios, cambiarle la contraseña a
  cualquiera o revocarles el acceso — excepto revocarse a si mismo, bloqueado
  a proposito (server-side, no solo ocultando el botón) para que siempre
  quede al menos un usuario activo capaz de loguearse.
