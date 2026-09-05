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
php -S localhost:8000 -t public
```

Abrí `http://localhost:8000` — te va a mandar a `?page=login`. Credenciales de
ejemplo (las crea el seed): **admin@ejemplo.com / admin1234**.

Volver a correr `php database/seed.php` en cualquier momento reconstruye el esquema
y regenera los datos de ejemplo desde cero (es reproducible: usa una semilla fija).
Las mismas variables `DB_*` tienen que estar exportadas cuando corrés el servidor,
el seed y los tests, para que los tres apunten a la misma base.

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
                      cohortes, clientes, auditoria, login)
  Repositories/       consultas y agregaciones SQL por entidad (incluye
                      AuditoriaRepository e IntentoLoginRepository)
  Database.php         conexión PDO a PostgreSQL (singleton, config por env vars)
  Auth.php             login/logout, guard de sesión, bloqueo por fuerza bruta
  EstadoBoleta.php      calculo puro de saldo/estado de una boleta (testeado)
  Paginacion.php        helper de paginación (página/offset/total, testeado)
  Router.php, View.php, Filtros.php, Config.php, helpers.php
database/
  schema.sql            esquema de la base
  seed.php               generador de datos de ejemplo
  paises_monedas.php      catalogo de ~200 paises y sus monedas (ISO 4217)
views/                  plantillas PHP (una carpeta por sección), con partials
                        compartidos _filtro_fechas.php y _paginacion.php
tests/
  Unit/                 sin base de datos (calculo de estado, filtros, helpers,
                        paginación)
  Integration/           contra la base real (repositorios, auditoría, bloqueo
                        de login)
```

## Modelo de datos

- `monedas` / `paises`: catálogo de referencia (código ISO, nombre, símbolo y
  `tasa_a_usd` — cuánto vale 1 unidad de esa moneda en USD, para consolidar
  reportes). Son tasas estáticas de ejemplo, no un feed en vivo.
- `usuarios_sistema`: quién puede entrar al panel (login).
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
- Paginación de 25 filas por página. En Pagos y Clientes es `LIMIT`/`OFFSET` en
  SQL (el filtro es puro SQL). En Boletas el estado se calcula en PHP a partir de
  los pagos aplicados, así que ese filtro no existe en SQL: se trae el rango
  filtrado por fecha/cliente completo, se calcula el estado de cada fila, se
  filtra por estado si corresponde y recién ahí se pagina con `array_slice`. Es
  correcto y suficiente a la escala de este sistema (cientos de filas, no
  millones); con un volumen mucho mayor convendría guardar el estado o paginar
  distinto.
