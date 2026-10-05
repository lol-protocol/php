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
  marcada "Anulada" y se excluye de los agregados) para no perder el rastro. Un
  doble clic en "Guardar" no crea un segundo pago ni una segunda boleta.
- **Auditoría**: historial de cada alta, edición y anulación de boletas, pagos y
  clientes, con fecha y el detalle de qué cambió. Sin login (ver más abajo), el
  usuario de toda entrada nueva es "Sistema"; las de antes de sacarlo conservan
  el suyo.
- **Paginación** en los listados grandes (boletas, pagos, clientes).

> El panel no tiene login: es de acceso libre, sin cuentas de usuario. Había una
> pantalla de login con sesión, bloqueo por fuerza bruta y gestión de usuarios del
> sistema (alta, cambio de contraseña, revocar acceso); se sacó de la app y quedó
> en `_Garbage/` (código completo, no se borró) por si hace falta reactivarla —
> ver `_Garbage/README.md` para el detalle de qué se movió y qué se tocó en el
> resto de la app para poder sacarla. Para publicarlo en internet, leé antes el
> aviso de acceso en "Despliegue en producción".

## Requisitos

- PHP >= 8.2 con `pdo_pgsql` (lo exige PHPUnit 11; la CI corre en 8.2 y 8.4)
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

# Desarrollo: permite correr el seed y completa con valores por defecto las
# DB_* que falten. Sin APP_ENV=dev (o sea, en produccion) las credenciales son
# obligatorias y la app no arranca si falta alguna.
export APP_ENV=dev

# Crear el rol y la base si todavia no existen:
psql -h $DB_HOST -U postgres -c "CREATE ROLE $DB_USER LOGIN PASSWORD '$DB_PASSWORD';"
psql -h $DB_HOST -U postgres -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"

php database/seed.php      # arma el esquema con las migraciones y carga datos de ejemplo
php -S localhost:8000 -t public   # solo para desarrollo, ver "Despliegue en producción"
```

Abrí `http://localhost:8000` — te va a mostrar el Dashboard directo, sin login.

Volver a correr `php database/seed.php` en cualquier momento borra la base, la
reconstruye con las migraciones de `database/migraciones/` y regenera los datos de
ejemplo desde cero (es reproducible: usa una semilla fija). Por eso solo corre con
`APP_ENV=dev`. Las mismas variables tienen que estar exportadas cuando corrés el
servidor, el seed y los tests, para que los tres apunten a la misma base.

## Despliegue en producción

`php -S` (el comando de arriba) es el servidor de desarrollo embebido de PHP.
El propio manual de PHP dice que no está pensado para producción (es
mono-proceso, sin tuning de rendimiento ni manejo serio de concurrencia). Para
correr esto de verdad hace falta **PHP-FPM + un servidor web** (nginx, Caddy o
Apache) delante.

> **Antes de exponerlo: el panel no tiene login.** Quien llegue a la URL puede ver
> todos los datos —incluidos el email, la ciudad y la edad de cada cliente— y crear,
> editar y anular boletas, pagos y clientes. Ponelo detrás de algún control de acceso
> que resuelva el servidor web o la red: autenticación básica en nginx (en el
> ejemplo de abajo), una lista de IPs permitidas o una VPN. Por qué no tiene login y
> cómo reactivarlo: `_Garbage/README.md`.

Ejemplo mínimo con nginx (asumiendo php-fpm escuchando en `127.0.0.1:9000`):

```nginx
server {
    listen 443 ssl;
    server_name tu-dominio.com;
    root /ruta/al/proyecto/public;
    index index.php;

    # TLS: certificado real (ej. Let's Encrypt/certbot) va acá.

    # Control de acceso: la app no tiene login propio, así que sin esto cualquiera
    # con la URL lee y modifica todo. Autenticación básica como mínimo (el archivo
    # se crea con `htpasswd -c /etc/nginx/.htpasswd usuario`, de apache2-utils).
    auth_basic "Panel de cobros";
    auth_basic_user_file /etc/nginx/.htpasswd;

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

### Configuración

Todo se lee de variables de entorno (con PHP-FPM, `env[...]` en el pool):

| Variable | Obligatoria | Para qué |
|---|---|---|
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | sí | La conexión a Postgres. Si falta alguna, la app no arranca y el log de errores nombra todas las que faltan. |
| `DB_HOST`, `DB_PORT` | no (`127.0.0.1`, `5432`) | |
| `APP_TIMEZONE` | no (`UTC`) | La zona horaria del negocio, en formato IANA (ej. `America/Argentina/Buenos_Aires`). Define qué día es "hoy" para vencimientos, rangos de fechas y notas de crédito, y se aplica a PHP y a Postgres por igual. |
| `APP_ENV` | no | Solo `dev`, en desarrollo. En producción no se define. |

### Esquema de la base

En cada despliegue: `php database/migrar.php` (o `composer migrar`). Aplica en orden
las migraciones de `database/migraciones/` que la base todavía no tenga (quedan
anotadas en `migraciones_aplicadas`) y no hace nada si ya está al día. Nunca borra
datos. `seed.php`, en cambio, es solo para desarrollo: borra todo, y sin
`APP_ENV=dev` se niega a correr.

Si la base se creó antes de que existieran las migraciones (con el viejo
`database/schema.sql`), la primera vez corré `php database/migrar.php --baseline`:
marca la migración inicial como aplicada sin ejecutarla (esas tablas ya existen) y
aplica las demás.

Un cambio de esquema nuevo va en un archivo nuevo con el número siguiente
(`004_descripcion.sql`). Una migración que ya corrió en alguna base no se edita.

### Otros puntos

Dos cosas que la app ya resuelve por su cuenta pero vale saber:

- **Errores**: `public/index.php` fuerza `display_errors=0` y registra un
  manejador global (`App\ErrorHandler`) que manda el detalle de cualquier
  excepción no capturada a `error_log()` — nunca a la respuesta. Dónde
  termina ese log depende de tu `php.ini`/pool de FPM (`error_log` de PHP);
  configuralo a un archivo real en producción en vez del default.
- **Backups**: no hay nada automatizado acá — es un `pg_dump` de la base
  como cualquier otra base de Postgres. Con `boletas`/`pagos`/`clientes`
  reales adentro, un backup periódico deja de ser opcional.

## Tests y análisis estático

```bash
composer test        # o: ./vendor/bin/phpunit
composer analyse     # PHPStan, nivel 8
```

Hay tres suites:

- `tests/Unit`: sin base de datos.
- `tests/Integration`: contra la base de las variables de arriba (corré el seed
  primero). La mayoría corre dentro de una transacción que se deshace al terminar,
  así no deja datos ni ve los de otro test. Tres no pueden: `AnulableTest` necesita
  una segunda conexión, que no vería lo que no se commiteó; `ZonaHorariaTest` abre
  conexiones propias y cambia la zona horaria del proceso; y `DatabaseTransaccionTest`
  prueba el commit y el rollback de verdad. Los tres dejan todo como estaba al terminar.
- `tests/Http`: levanta la app con `php -S` y la recorre por HTTP como un navegador
  (CSRF, formularios, redirecciones). Cubre lo que los otros no alcanzan: el
  cableado de `public/index.php` y los controllers. Lo que crea se borra al terminar.

PHPStan corre en nivel 8, el que revisa los nulos (por ejemplo, el resultado de un
`porId()` usado sin chequear que la entidad exista). Las excepciones están
explicadas en `phpstan.neon`.

La CI (`.github/workflows/pruebas-cobros-ingresos-funnels.yml`, en la raíz del
repositorio) corre en los pull requests y en `master` cada vez que cambia algo de
este proyecto: sintaxis, PHPStan, las migraciones sobre una base vacía, el seed y
las tres suites, contra un Postgres 16, en PHP 8.2 (el mínimo) y en 8.4.

## Estructura

```
public/            front controller (index.php) + CSS
src/
  Controllers/       un controlador por sección (dashboard, cobros, pagos, funnel,
                      cohortes, clientes, auditoria). Todas las páginas son
                      públicas, no hay login (ver `_Garbage/README.md`).
  Repositories/       un repo de CRUD por entidad (Boleta/Pago/Cliente/...) más
                      IngresosRepository (kpis, ingresos y cobros por mes,
                      antigüedad de cartera, por método de pago) y
                      SegmentacionRepository (top país/ciudad/idioma/género/edad,
                      LTV por cohorte) para el reporting, que no es CRUD y crecía
                      por separado. AuditoriaRepository también concentra el
                      `auditarComoUsuarioActual()` que usan todos los controllers
                      en vez de repetirlo cada uno.
  Database.php         conexión PDO a PostgreSQL (config por env vars, misma zona
                        horaria para PHP y Postgres) y transaccion(), anidable
  Config.php           variables de entorno: obligatorias fuera de desarrollo,
                        zona horaria IANA validada, testeado
  Migrador.php          aplica database/migraciones/ y recuerda cuales corrieron
  EnvioUnico.php        token de un solo uso de los formularios de alta: un doble
                        clic no crea dos pagos ni dos boletas, testeado
  EstadoBoleta.php      calculo puro de saldo/estado de una boleta (testeado)
  Etiquetas.php         traduce estado de boleta/metodo de pago/canal a su
                        etiqueta en español, en un solo lugar para no repetir
                        el mismo array en cada vista que los muestra, testeado
  Paginacion.php        helper de paginación (página/offset/total, testeado)
  Csrf.php              token CSRF por sesión propia (no depende de ningún login),
                        verificado en Router (testeado)
  Http.php              detecta HTTPS (directo o detras de proxy), testeado
  SecurityHeaders.php   headers de seguridad de cada respuesta, testeado
  ErrorHandler.php      red de seguridad para excepciones no capturadas
                        (loguea el detalle, nunca lo muestra), testeado
  Peticion.php           guard clauses de los controllers: id de la ruta,
                        404 si no existe, 409 si hay conflicto (ej. anulado),
                        testeado
  Validacion.php         chequeos repetidos entre formularios: campos
                        obligatorios vacios y mensaje de email duplicado
  Repositories/Anulable.php  trait con el soft-delete que comparten
                        BoletaRepository y PagoRepository: un unico
                        UPDATE ... SET anulada = TRUE WHERE id = :id AND NOT
                        anulada, que devuelve si fue esa llamada la que anulo
                        (chequear antes en PHP dejaba pasar dos anulaciones
                        simultaneas), testeado
  Repositories/NotaCreditoRepository.php  devoluciones emitidas al anular
                        una boleta ya cobrada, y su total por rango/mes en
                        USD para netear los cobros de los reportes
  Repositories/RangoEdad.php  el tramo de edad (Menor de 18, 18-24, 25-34, ...)
                        como expresion SQL, con age(); lo comparten
                        segmentacion y funnel. Una fecha de nacimiento
                        posterior a hoy sale como "Fecha inválida"
  Filtros.php            el periodo y el rango Desde/Hasta de las pantallas con
                        filtro de fechas: una sola lista de periodos, y lo que
                        se pidio por URL y no se pudo respetar (un periodo que
                        no existe, un rango al reves) se avisa, testeado
  Avisos.php             los mensajes cortos de arriba de una pantalla
                        (views/_avisos.php), testeado
  Router.php, View.php, helpers.php
database/
  migraciones/          el esquema, en cambios numerados (001 = esquema inicial)
  migrar.php             aplica las migraciones pendientes (en cada despliegue)
  seed.php               SOLO desarrollo: rearma la base y carga datos de ejemplo
  paises_monedas.php      catalogo de ~200 paises y sus monedas (ISO 4217)
views/                  plantillas PHP (una carpeta por sección), con partials
                        compartidos: _filtro_fechas.php (el período y el rango
                        Desde/Hasta de las cinco pantallas con filtro),
                        _avisos.php (los avisos de arriba de la pantalla),
                        _paginacion.php,
                        _error.php (el aviso de error de los formularios),
                        _accion_confirmar.php (el pie de las pantallas de
                        confirmar anulación), _grafico_aging.php y
                        _grafico_serie_mensual.php (los dos graficos de barras
                        que se repetian en dashboard, cobros, pagos y funnel);
                        el funnel tiene además el suyo, funnel/_tabla_dimension.php
tests/
  Unit/                 sin base de datos (calculo de estado, filtros, helpers,
                        paginación, CSRF, router, headers de seguridad, deteccion
                        de HTTPS)
  Integration/           contra la base real, casi todos en una transaccion que
                        se deshace (un archivo por repositorio, migraciones,
                        auditoría, zona horaria, y que los datos de ejemplo
                        del seed cumplan las reglas de la app)
  Http/                  la app levantada con php -S, recorrida por HTTP
phpstan.neon            configuracion del analisis estatico
```

## Modelo de datos

- `monedas` / `paises`: catálogo de referencia (código ISO, nombre, símbolo y
  `tasa_a_usd` — cuánto vale 1 unidad de esa moneda en USD, para consolidar
  reportes). Son tasas estáticas de ejemplo, no un feed en vivo.
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
- `notas_credito`: devoluciones. Al anular una boleta que ya tenía pagos, los
  pagos **no** se tocan (la plata entró de verdad y tiene que seguir en el
  historial de caja): se emite una nota de crédito por lo cobrado, que queda
  como devolución pendiente con el cliente. Los reportes de cobros restan estas
  notas para mostrar el neto, así anular una boleta cobrada no deja plata
  contando como ingreso sin respaldo. La nota se emite una sola vez y no se
  recalcula, así que una vez anulada la boleta sus pagos quedan congelados:
  anularlos o editarlos descuadraría la devolución, y la app lo rechaza con un
  409 (la regla simétrica de no poder cargar un pago nuevo contra una boleta
  anulada).
- `boletas_con_saldo` (vista): cada boleta con `pagado` (la suma de sus pagos no
  anulados), `saldo` y `primer_pago`. Es la única definición de "cuánto se pagó":
  la usan todas las consultas en vez de repetir la subconsulta.
- `auditoria`: un registro por cada alta/edición/anulación (cuándo, sobre qué
  entidad, el detalle de qué cambió y el usuario, que sin login es "Sistema"). Es
  de solo inserción — no se borra, y cada entrada se escribe en la misma
  transacción que el cambio que describe.
- `envios_formulario`: los tokens de un solo uso de los formularios de alta. El
  primer envío registra su token junto con el cambio; un reenvío del mismo
  formulario lo encuentra y recibe la misma redirección, sin crear nada. Se
  purgan a los 7 días.
- `migraciones_aplicadas`: qué migraciones de `database/migraciones/` ya corrieron
  en esta base.

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
  despachar cualquier POST — si falta o no coincide, corta con 403 antes de
  que el controller toque nada.
- Cookie de sesión endurecida: `HttpOnly` (JS no puede leerla — igual no hay
  JS en la app) + `SameSite=Lax` (capa extra contra CSRF, sumada al token) +
  `Secure` cuando el request llega por HTTPS (`App\Http::esSegura()`, que
  tambien mira `X-Forwarded-Proto` si hay un proxy adelante). En HTTP plano
  (dev local) `Secure` queda apagado a proposito, sino el browser descarta
  la cookie y se pierde el token CSRF.
- Headers de seguridad en cada respuesta (`App\SecurityHeaders`):
  `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` y una
  `Content-Security-Policy` que bloquea JavaScript por completo
  (`script-src 'none'` — la app no usa JS en ningun lado) y permite estilos
  inline (`style-src 'unsafe-inline'`, que usan los graficos SVG). Con HTTPS
  se suma `Strict-Transport-Security`.
