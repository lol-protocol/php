# Panel de Cobros, Ingresos y Funnel

Pequeño sistema en PHP (sin framework) para analizar:

- **Ingresos** devengados (boletas emitidas al usuario) vs. **cobros** reales (caja).
  Solo se registran pagos que el usuario nos hace a nosotros — no hay módulo de
  costos ni pagos propios del negocio.
- **Cartera** pendiente, con antigüedad de saldo (al día / 1-30 / 31-60 / 61+ días).
- **Cobranza** (`?page=cobranza`): la misma cartera, cliente por cliente: quién debe, cuánto
  (saldo total y saldo ya vencido, en USD a la tasa de hoy), cuántas boletas y desde cuándo (los
  días de la boleta que lleva más tiempo vencida), con su email para reclamar y un acceso directo a
  registrar su pago. Se ve por defecto a los que tienen saldo vencido, o todo el saldo abierto, y se
  ordena por monto vencido, antigüedad, saldo o nombre. Sus totales son los del Dashboard: las dos
  pantallas salen de la misma consulta (`IngresosYCobrosRepository::saldosAbiertosSql()`).
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
  pagos y su recorrido por el funnel si entró por ahí). Desde la ficha se carga una
  boleta o un pago con el cliente ya elegido. Los desplegables de "Nueva boleta" y
  "Nuevo pago" muestran los primeros 500 clientes por nombre y avisan si hay más.
  El alta valida país, email, fecha de nacimiento y el largo de cada texto; el
  idioma es libre, pero se guarda normalizado ("INGLES" → "Ingles") y el
  formulario sugiere los que ya tienen clientes, para que el dashboard no los
  parta en filas distintas.
- **Boletas y pagos**: alta, edición y anulación. Anular es un soft-delete (queda
  marcada "Anulada" y se excluye de los agregados) para no perder el rastro. Un
  doble clic en "Guardar" no crea un segundo pago ni una segunda boleta.
- **Notas de crédito (devoluciones)**: anular una boleta que ya tenía pagos emite una
  nota por lo cobrado. La pantalla «Notas de crédito» (`?page=notas`) lista las que
  siguen pendientes de devolver, las ya devueltas o todas, y desde ahí se anota el día
  en que se le devolvió la plata al cliente (queda en la auditoría). El Dashboard
  muestra cuánto falta devolver y la ficha del cliente, el estado de cada nota.
- **Auditoría**: historial de cada alta, edición y anulación de boletas, pagos y
  clientes, con fecha y el detalle de qué cambió, de la más reciente a la más
  antigua (se navega con «Más antiguas» y «Más recientes»). Sin login (ver más
  abajo), el usuario de toda entrada nueva es "Sistema"; las de antes de sacarlo
  conservan el suyo.
- **Paginación** en los listados grandes (boletas, pagos, clientes).
- **Exportar a CSV** las tres listas de trabajo: Cobros e ingresos (`?page=cobros-csv`),
  Pagos (`?page=pagos-csv`) y Cobranza (`?page=cobranza-csv`). Cada una tiene su enlace
  «Exportar CSV» y baja **todas** las filas que la pantalla mostraría con sus filtros
  actuales (período, estado, cliente, alcance, orden), no solo la página que se ve. Es un
  CSV estándar (UTF-8 con marca de orden para que Excel muestre bien las tildes, comas,
  decimales con punto, fechas `AAAA-MM-DD`). Un texto que empiece con `=`, `+`, `-` o `@`
  lleva un apóstrofe delante, para que Excel o LibreOffice no lo ejecuten como fórmula (un
  cliente llamado `=HYPERLINK(...)`); los números no se tocan. Se exporta de a mil filas,
  así que una base grande no agota la memoria de PHP. Llevan datos personales (nombre y
  email de los clientes): valen las mismas precauciones de acceso que el resto de la app
  (ver «Antes de exponerlo»), y se mandan con `Cache-Control: no-store`.

> El panel no tiene login: es de acceso libre, sin cuentas de usuario. Había una
> pantalla de login con sesión, bloqueo por fuerza bruta y gestión de usuarios del
> sistema (alta, cambio de contraseña, revocar acceso); se sacó de la app y quedó
> en `_Garbage/` (código completo, no se borró) por si hace falta reactivarla —
> ver `_Garbage/README.md` para el detalle de qué se movió y qué se tocó en el
> resto de la app para poder sacarla. Para publicarlo en internet, leé antes el
> aviso de acceso en "Despliegue en producción".

## Requisitos

- PHP >= 8.3 con `pdo_pgsql` (la CI corre en 8.3, el mínimo, y en 8.4; PHP 8.2
  deja de recibir parches de seguridad el 31 de diciembre de 2026)
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

php bin/recrear_con_datos_de_ejemplo.php      # arma el esquema con las migraciones y carga datos de ejemplo
php -S localhost:8000 -t public   # solo para desarrollo, ver "Despliegue en producción"
```

Abrí `http://localhost:8000` — te va a mostrar el Dashboard directo, sin login.

Volver a correr `php bin/recrear_con_datos_de_ejemplo.php` en cualquier momento borra la base, la
reconstruye con las migraciones de `database/migraciones/` y regenera los datos de
ejemplo desde cero (es reproducible: usa una semilla fija, así que el mismo día da
siempre los mismos datos; las fechas son relativas a hoy). Por eso solo corre con
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

En vez de la contraseña (o además), se puede limitar por red: dentro del `server`,
`allow 203.0.113.10; allow 10.8.0.0/24; deny all;` deja entrar solo a esas direcciones
(la oficina, la VPN). Cualquiera de las dos cosas tiene que estar puesta **en todo el
`server`**, no solo en `location /`: así cubre también `/assets/` y cualquier ruta nueva.

**Comprobar que quedó bien puesto**, desde afuera y sin credenciales:

```bash
php bin/comprobar_acceso.php https://tu-dominio.com     # o: composer acceso -- https://tu-dominio.com
```

Pide siete pantallas con datos sin mandar ninguna credencial. Termina con `0` y dice
`PROTEGIDO` si todas contestan 401 o 403; con `1` y `ABIERTO` si alguna contesta (hay
que corregir el servidor antes de usar el panel con datos reales); con `2` si no puede
afirmar nada (una redirección —probá con la dirección a la que redirige, casi siempre
la https—, un 404, un 500, o que no pueda conectar). Si el acceso se limita por IP, hay
que correrlo desde una dirección que **no** esté permitida: desde una permitida el
panel contesta y parece abierto. Conviene repetirlo después de cada cambio en la
configuración del servidor web. No necesita la base de datos.

Con TLS terminado en nginx, la app detecta HTTPS solo si nginx manda
`X-Forwarded-Proto: https` en el `fastcgi_param` (agregalo si no está ya en tu
`fastcgi_params`) — de eso depende que la cookie de sesión salga con `Secure`
y que se mande `Strict-Transport-Security` (ver `App\ConexionSegura::esHttps()`).

### Configuración

Todo se lee de variables de entorno (con PHP-FPM, `env[...]` en el pool):

| Variable | Obligatoria | Para qué |
|---|---|---|
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | sí | La conexión a Postgres. Si falta alguna, la app no arranca y el log de errores nombra todas las que faltan. |
| `DB_HOST`, `DB_PORT` | no (`127.0.0.1`, `5432`) | |
| `DB_PERSISTENT` | no (`1` en la web) | Si la conexión a Postgres se reutiliza entre peticiones (ahorra entre 10 y 20 ms por petición; en CLI nunca es persistente). Poné `0` detrás de un pooler como PgBouncer, o si hay más procesos de PHP-FPM que `max_connections` en Postgres: cada proceso guarda su conexión abierta, así que `pm.max_children` no debería superarlo. |
| `APP_TIMEZONE` | no (`UTC`) | La zona horaria del negocio, en formato IANA (ej. `America/Argentina/Buenos_Aires`). Define qué día es "hoy" para vencimientos, rangos de fechas y notas de crédito, y se aplica a PHP y a Postgres por igual. |
| `TASAS_URL` | no (`https://open.er-api.com/v6/latest/USD`) | Solo para `bin/actualizar_tasas.php`: de dónde baja las tasas de cambio (tiene que ser https). Ver «Tasas de cambio». |
| `TASAS_FUENTE` | no (el host de `TASAS_URL`) | Solo para ese comando: cómo se llama la fuente en las pantallas y en la auditoría. |
| `APP_ENV` | no | Solo `dev`, en desarrollo. En producción no se define. |

### Tasas de cambio

Los totales en USD de Dashboard, Cobros, Pagos y Cohortes convierten cada monto con
una tasa de `monedas.tasa_a_usd` (cuál, en «La tasa de cada día», más abajo). La
migración `005` las carga con valores de ejemplo, y mientras sigan así esas cuatro
pantallas lo avisan arriba. Para cargar las reales:

```bash
php bin/actualizar_tasas.php             # baja las tasas y las guarda
php bin/actualizar_tasas.php --simular   # dice qué cambiaría, sin guardar nada
```

**Primera puesta en marcha, en el servidor:**

1. `php bin/migrar.php` (las migraciones no borran datos).
2. `php bin/actualizar_tasas.php --simular`. Lee lo que traería la fuente y no guarda
   nada: dice cuántas monedas actualizaría, las que la fuente no trae, las que rechaza y,
   en la primera carga, cuántas boletas, pagos y notas de crédito pasarían de la tasa de
   ejemplo a la real (por moneda).
3. Si eso es razonable, la misma orden sin `--simular`, y después abrí el Dashboard: el
   aviso de tasas de ejemplo tiene que haber desaparecido (queda la fecha y la fuente).
4. Agregá el cron (más abajo). Si algo falla, el comando termina con error sin escribir
   nada, y el mensaje dice qué (la dirección que muestra es solo el servidor, nunca la
   clave).

Si el servidor no tiene salida a internet, o la fuente no es la de siempre,
`--archivo=tasas.json` lee el mismo formato de un archivo. La descarga usa el
`allow_url_fopen` de PHP (viene activado por defecto) y la verificación de certificados
del sistema; si falla por eso, el mensaje lo dice.

Baja las tasas de `TASAS_URL` (por defecto `https://open.er-api.com/v6/latest/USD`, de
ExchangeRate-API: gratis, sin clave, unas 160 monedas, una actualización por día;
revisá sus condiciones de uso, que piden nombrar la fuente: la app la nombra en cada
pantalla), valida lo que llegó y lo guarda en una transacción, con una entrada en la
auditoría. Está pensado para cron, una vez por día:

```
15 6 * * *  cd /ruta/al/proyecto && php bin/actualizar_tasas.php >/dev/null
```

(`>/dev/null` deja pasar solo la salida de errores, que es la que cron manda por mail:
un error, o una tasa que pide atención.) También está como `composer tasas`.

Como de estas tasas dependen todos los totales, escribe solo lo que se puede creer:

- La fuente tiene que traer al menos la mitad de las monedas del catálogo y datos de
  menos de 7 días; si no, no escribe nada y termina con error.
- Una tasa con un valor imposible (cero, texto, o fuera de lo que entra en la base) se
  deja como estaba y se informa.
- Una tasa ya real que salta más de 50% de una corrida a otra se deja como estaba y se
  informa (puede ser una devaluación de verdad o un error de la fuente): `--forzar` la
  acepta. La primera carga, sobre las de ejemplo, acepta cualquier diferencia.
- Una moneda que la fuente no trae queda como estaba, con la fecha que tenía.
- Las pantallas avisan cuando una moneda con boletas o pagos tiene una tasa de ejemplo
  o de hace más de 7 días, y nombran cuál.

Con otro servicio, se pone su dirección en `TASAS_URL` (algunos piden una clave en la
URL; los mensajes de error muestran solo el servidor, nunca la clave, porque cron los
manda por mail). Sirve cualquiera que conteste `{"base": "USD", "rates": {"EUR": 0.86, ...}}`
(o `base_code`): ExchangeRate-API, Open Exchange Rates, Frankfurter. Y
`--archivo=tasas.json` lee el mismo formato de un archivo, para tasas que vengan de
otra parte (el banco central, el contador).

**La tasa de cada día.** Cada boleta, pago y nota de crédito guarda la tasa de su
moneda cuando se crea (`tasa_a_usd`, migración `011`), y los ingresos, los cobros, las
devoluciones, la segmentación y el LTV suman `monto × tasa` de cada fila. Un mes ya
cerrado da siempre la misma cifra, aunque la tasa se mueva después: actualizar las tasas
no toca lo que ya está cargado. Lo único que se valúa a la tasa de hoy es la **cartera
pendiente** (lo que todavía se debe), que es plata por cobrar. Cómo se llena:

- Un trigger de la base graba la tasa de `monedas` en cada alta que venga sin una, así
  que lo mismo da una boleta cargada en la app que un `INSERT` a mano o el seed. Una
  tasa que se pase explícita se respeta (una importación con su propia cotización).
- Editar un monto o una fecha no vuelve a cotizar: la moneda no se puede cambiar.
- La nota de crédito toma el promedio, ponderado por monto, de las tasas de los pagos
  que devuelve, para que el cobro y la devolución se cancelen en USD aunque la tasa se
  haya movido; sin pagos, la del día.
- La primera vez que una moneda recibe una tasa real, las boletas, pagos y notas que se
  habían grabado con la de ejemplo pasan a esa tasa real (`--simular` dice cuántas
  filas son). Desde ahí ya no se mueven.
- **Límite:** la base no guarda un historial de cotizaciones. Una boleta cargada hoy
  con fecha de hace tres meses queda con la tasa de hoy, no con la de esa fecha, y lo
  mismo vale para lo que ya existía cuando se aplicó la migración. Si hace falta la
  tasa exacta de cada fecha, falta una tabla de cotizaciones por día (y una importación
  que las use: las filas aceptan una tasa explícita).

### Respaldos

Con `boletas`, `pagos` y clientes reales adentro, la base no se puede perder. El respaldo es
un comando pensado para cron, una vez por día:

```bash
php bin/respaldar.php                           # o: composer respaldar
php bin/respaldar.php --carpeta=/var/backups/cobros --dias=30
```

```
30 3 * * *  cd /ruta/al/proyecto && php bin/respaldar.php >/dev/null
```

(`>/dev/null` deja pasar solo la salida de errores, que es la que cron manda por mail.) Hace un
`pg_dump -Fc` (el formato propio de Postgres, comprimido) en `RESPALDOS_CARPETA` (por defecto,
`respaldos/` del proyecto, que git ignora; conviene una carpeta fuera del proyecto) con el
nombre `base-AAAAMMDD-HHMMSS.dump` (hora UTC). Usa las mismas `DB_*` que la app, y necesita el
cliente de PostgreSQL (`pg_dump` y `pg_restore`, de una versión igual o mayor que la del
servidor; si no están en el `PATH`, `PG_DUMP` y `PG_RESTORE` dicen dónde).

Se cuida de lo que sale mal en silencio:

- El archivo se escribe como `.parcial` y recién se renombra cuando `pg_restore --list` lo
  puede leer y trae datos de tablas. Un fallo no deja un archivo a medias que parezca un
  respaldo; el comando termina con error (y cron manda el mail).
- Los respaldos viejos (más de `--dias`, 14 por defecto) se borran **solo después de un
  respaldo nuevo bueno**: si `pg_dump` falla durante semanas, no se van comiendo los anteriores.
  Solo se borran archivos con este nombre y de esta misma base, nunca un enlace ni algo de otra
  base.
- Si el respaldo nuevo pesa menos de la mitad que el anterior, el comando lo avisa por la
  salida de errores: puede ser una base vacía o dañada que se está «respaldando» bien.
- La carpeta queda en `0700` y los archivos en `0600` (tienen todos los datos), y la clave viaja
  en el entorno de `pg_dump`, no en su línea de comandos ni en ningún mensaje.

**Esto no alcanza solo:** un respaldo en el mismo servidor no sirve si el servidor se pierde. Hay
que copiar la carpeta a **otra máquina o a otro disco**, por ejemplo con otro cron que corra un
poco después (`rsync -a --ignore-existing /var/backups/cobros/ usuario@otra-maquina:/copias/cobros/`)
o con el almacenamiento externo que ya uses. Y un respaldo que nunca se restauró no es un respaldo:
probalo de vez en cuando.

**Restaurar** (probado: en una base nueva quedan los mismos datos, las migraciones anotadas, los
triggers, `migrar.php` dice «La base ya estaba al día» y las pantallas cargan):

```bash
# como superusuario de Postgres:
createdb -O cobros_app cobros_restaurada
# con el usuario de la app:
pg_restore --no-owner --host=127.0.0.1 --username=cobros_app --dbname=cobros_restaurada ARCHIVO.dump
```

Después se apunta la app a esa base (`DB_NAME`) o se la renombra por la original, con la app parada.
El respaldo no lleva dueños ni permisos (`--no-owner --no-privileges`): los objetos quedan a nombre
de quien restaura.

### Esquema de la base

En cada despliegue: `php bin/migrar.php` (o `composer migrar`). Aplica en orden
las migraciones de `database/migraciones/` que la base todavía no tenga (quedan
anotadas en `migraciones_aplicadas`) y no hace nada si ya está al día. Nunca borra
datos. `recrear_con_datos_de_ejemplo.php`, en cambio, es solo para desarrollo: borra todo, y sin
`APP_ENV=dev` se niega a correr.

Si la base se creó antes de que existieran las migraciones (con el viejo
`database/schema.sql`), la primera vez corré `php bin/migrar.php --baseline`:
marca la migración inicial como aplicada sin ejecutarla (esas tablas ya existen) y
aplica las demás.

**Catálogo de países y monedas.** Lo carga la migración `005` junto con el resto del
esquema (198 países y 146 monedas), así que una base nueva queda lista para dar de
alta clientes sin ningún paso aparte. Una base migrada antes de la `005`, que quedó
sin catálogo, lo recibe en la siguiente corrida de `migrar.php`; y aplicarla sobre
una base que ya lo tiene no pisa nada. Las `tasa_a_usd` que carga son de ejemplo:
antes de confiar en los reportes consolidados en USD hay que cargar las reales con
`php bin/actualizar_tasas.php` (ver «Tasas de cambio»).

**Mayoría de edad.** La base rechaza a un cliente que no haya cumplido la edad de
mayoría de **su país**, tanto al crearlo como al cambiarle la fecha de nacimiento o
el país (la app ya lo valida antes y dice cuál es la edad; el trigger de las
migraciones `007` y `010` cubre cualquier otro camino: un script, una carga directa).
La edad es `paises.mayoria_de_edad` (migración `010`): 18 en casi todos, 19 en
Canadá, Corea del Sur y Argelia, 20 en Tailandia, 21 en Singapur, Egipto, Emiratos
Árabes Unidos, Kuwait, Bahréin y Honduras. **Esa lista no es asesoría legal**: hay
países donde la edad depende del acto o de la región, y quedaron en 18, con dudas,
Estados Unidos (19 en Alabama y Nebraska, 21 en Mississippi), Nueva Zelanda,
Indonesia, Túnez, Camerún, Nicaragua y Bolivia. Antes de atender clientes de esos
países hay que revisarlos con quien responda por lo legal; corregirlo es una fila,
sin tocar código: `UPDATE paises SET mayoria_de_edad = 21 WHERE codigo = 'US';`
(la base acepta de 16 a 25). El alta nombra al país en el rechazo («en Tailandia,
20 años cumplidos») y el formulario lista los países que no son de 18; el selector
de fecha topa en la edad más baja, porque no sabe todavía el país elegido.

Ni esta migración ni cambiar la edad de un país revisan, cambian o borran a los
clientes que ya existían, y mientras nadie les toque la fecha de nacimiento ni el
país siguen pudiendo editarse. Para encontrar a los que hoy no llegan a la edad de
su país:
`SELECT c.id, c.nombre, c.fecha_nacimiento, p.mayoria_de_edad FROM clientes c JOIN paises p ON p.codigo = c.pais_codigo WHERE c.fecha_nacimiento > CURRENT_DATE - make_interval(years => p.mayoria_de_edad);`
(el Dashboard también muestra a los menores de 18 en el tramo «Menor de 18» de la
segmentación por edad). Los visitantes del funnel pueden ser menores: un lead no es
un cliente, pero no puede convertirse.

Un cambio de esquema nuevo —o un país o una moneda nuevos— va en un archivo nuevo
con el número siguiente (`NNN_descripcion.sql`). Una migración que ya corrió en
alguna base no se edita.

### Otros puntos

Dos cosas que la app ya resuelve por su cuenta pero vale saber:

- **Errores**: `public/index.php` fuerza `display_errors=0` y registra un
  manejador global (`App\ManejadorDeErrores`) que manda el detalle de cualquier
  excepción no capturada a `error_log()` — nunca a la respuesta. Dónde
  termina ese log depende de tu `php.ini`/pool de FPM (`error_log` de PHP);
  configuralo a un archivo real en producción en vez del default.
- **Backups**: `bin/respaldar.php`, con cron, rotación y comprobación del archivo
  (ver «Respaldos»). Falta, y es cosa del servidor, copiarlos a otra máquina.

## Tests y análisis estático

```bash
composer test        # o: ./vendor/bin/phpunit
composer analyse     # PHPStan, nivel 8
```

Hay tres suites:

- `tests/Unit`: sin base de datos.
- `tests/Integration`: contra la base de las variables de arriba (corré el seed
  primero). La mayoría corre dentro de una transacción que se deshace al terminar,
  así no deja datos ni ve los de otro test. Cuatro no pueden: `AnulableTest` necesita
  una segunda conexión, que no vería lo que no se commiteó; `ZonaHorariaTest` abre
  conexiones propias y cambia la zona horaria del proceso; `DatabasePersistenteTest`
  maneja sus propias conexiones persistentes (comprueba que una transacción abierta
  no se le filtra a la petición siguiente); y `DatabaseTransaccionTest` prueba el
  commit y el rollback de verdad. Los cuatro dejan todo como estaba al terminar.
- `tests/Http`: levanta la app con `php -S` y la recorre por HTTP como un navegador
  (CSRF, formularios, redirecciones). Cubre lo que los otros no alcanzan: el
  cableado de `public/index.php` y los controllers. Lo que crea se borra al terminar.

PHPStan corre en nivel 8, el que revisa los nulos (por ejemplo, el resultado de un
`porId()` usado sin chequear que la entidad exista). Las excepciones están
explicadas en `phpstan.neon`.

La CI (`.github/workflows/pruebas-cobros-ingresos-funnels.yml`, en la raíz del
repositorio) corre en los pull requests y en `master` cada vez que cambia algo de
este proyecto: sintaxis, PHPStan, las migraciones sobre una base vacía, el seed y
las tres suites, contra un Postgres 16, en PHP 8.3 (el mínimo) y en 8.4.

## Estructura

```
public/            front controller (index.php) + CSS
src/
  Controllers/       un controlador por sección (dashboard, boletas —la pantalla
                      «Cobros e ingresos», `?page=cobros`—, cobranza, pagos, notas de
                      crédito, funnel, cohortes, clientes, auditoria). Todas las páginas son públicas, no hay
                      login (ver `_Garbage/README.md`).
  Repositories/       un repo de CRUD por entidad (Boleta/Pago/Cliente/...) más
                      IngresosYCobrosRepository (kpis, ingresos y cobros por mes,
                      antigüedad de cartera, por método de pago; su
                      `saldosAbiertosSql()` es la única definición de cartera),
                      CobranzaRepository (esa cartera por cliente) y
                      SegmentacionRepository (top país/ciudad/idioma/género/edad,
                      LTV por cohorte) para el reporting, que no es CRUD y crecía
                      por separado. AuditoriaRepository también concentra el
                      `auditar()` que usan todos los controllers
                      en vez de repetirlo cada uno.
  Database.php         conexión PDO a PostgreSQL (config por env vars, misma zona
                        horaria para PHP y Postgres) y transaccion(), anidable
  Config.php           variables de entorno: obligatorias fuera de desarrollo,
                        zona horaria IANA validada, testeado
  Migrador.php          aplica database/migraciones/ y recuerda cuales corrieron
  Respaldo/             RespaldoDeLaBase: el pg_dump con comprobación y rotación
                        (bin/respaldar.php), testeado con pg_dump de verdad
  Acceso/               ComprobadorDeAcceso: pide el panel sin credenciales y dice si
                        el servidor web lo protege (bin/comprobar_acceso.php),
                        testeado con un php -S que hace de panel
  Tasas/                las tasas de cambio reales: RespuestaDeTasas lee el JSON
                        de una fuente, DescargaDeTasas lo baja (solo https, sin
                        redirecciones) y ActualizadorDeTasas valida y guarda;
                        todo testeado sin salir a la red
  EnvioUnico.php        token de un solo uso de los formularios de alta: un doble
                        clic no crea dos pagos ni dos boletas, testeado
  EstadoBoleta.php      calculo puro de saldo/estado de una boleta (testeado)
  Dinero.php            montos en centavos enteros, para compararlos sin el ruido de
                        la coma flotante (testeado)
  MayoriaDeEdad.php     la regla de que no se admiten clientes que no hayan cumplido
                        la edad de mayoría de su país (años cumplidos, como age()
                        de Postgres) y el tope del campo de fecha de nacimiento del
                        alta; la base la repite en un trigger (migraciones 007 y
                        010), testeado
  Etiquetas.php         traduce estado de boleta/metodo de pago/canal a su
                        etiqueta en español, en un solo lugar para no repetir
                        el mismo array en cada vista que los muestra, testeado
  Paginacion.php        helper de paginación (página/offset/total, testeado) y
                        consultar(), el esqueleto único de los listados paginados
                        (contar, acotar la página, traer solo esa), y recorrer(), lo
                        mismo sin paginar: todas las filas de la consulta, de a mil
                        con un cursor de PostgreSQL, para exportar; testeado
  EscritorCsv.php       escribe filas en CSV (RFC 4180) y neutraliza las celdas que una
                        planilla tomaría por fórmula, testeado
  Descarga.php          la respuesta de una exportación (headers de archivo adjunto y
                        sin caché, marca UTF-8, y las filas de a una)
  Csrf.php              token CSRF por sesión propia (no depende de ningún login),
                        verificado en Router (testeado)
  ConexionSegura.php    detecta HTTPS (directo o detras de proxy), testeado
  CabecerasDeSeguridad.php   headers de seguridad de cada respuesta, testeado
  ManejadorDeErrores.php      red de seguridad para excepciones no capturadas
                        (loguea el detalle, nunca lo muestra), testeado
  Peticion.php           guard clauses de los controllers: id de la ruta,
                        404 si no existe, 409 si hay conflicto (ej. anulado),
                        testeado
  Validacion.php         chequeos repetidos entre formularios: campos
                        obligatorios vacios, largos maximos de los textos,
                        formato de email, fechas que existen y mensaje de
                        email duplicado, testeado
  Repositories/Anulable.php  trait con el soft-delete que comparten
                        BoletaRepository y PagoRepository: un unico
                        UPDATE ... SET anulada = TRUE WHERE id = :id AND NOT
                        anulada, que devuelve si fue esa llamada la que anulo
                        (chequear antes en PHP dejaba pasar dos anulaciones
                        simultaneas), testeado
  Repositories/NotaCreditoRepository.php  devoluciones emitidas al anular
                        una boleta ya cobrada, y su total por rango/mes en
                        USD para netear los cobros de los reportes; el listado
                        por estado (pendientes/devueltas), el registro de la
                        devolución (un UPDATE que solo pasa si seguía pendiente,
                        como la anulación) y lo que falta devolver
  FechaDeDevolucion.php el día en que se devolvió la plata: devuelve la fecha
                        ya validada o lanza una excepción con el mensaje para
                        la pantalla (entre la nota y hoy), como Dinero::centavos()
  RangoEdad.php         el tramo de edad (Menor de 18, 18-24, 25-34, ...)
                        como expresion SQL, con age(); lo comparten
                        segmentacion y funnel. Una fecha de nacimiento
                        posterior a hoy sale como "Fecha inválida"
  FiltroDePeriodo.php    el periodo y el rango Desde/Hasta de las pantallas con
                        filtro de fechas: una sola lista de periodos, y lo que
                        se pidio por URL y no se pudo respetar (un periodo que
                        no existe, un rango al reves) se avisa, testeado
  Avisos.php             los mensajes cortos de arriba de una pantalla
                        (views/_avisos.php): los avisos de filtro y la
                        confirmación de lo que acaba de hacerse, que llega por
                        la redirección (?creada=ID, ?creado=ID...), testeado
  Router.php, View.php, funciones_de_vista.php   (los helpers que usan las vistas)
database/
  migraciones/          el esquema, el catálogo de ~200 países y sus monedas
                        (ISO 4217), los índices y la regla de mayoría de edad,
                        en cambios numerados (001 = esquema inicial, 005 =
                        catálogo, 006 y 008 = índices, 007 = mayoría de edad,
                        009 = origen de las tasas de cambio, 010 = mayoría de
                        edad de cada país, 011 = la tasa de cambio de cada día,
                        012 = la fecha de devolución de cada nota de crédito)
bin/                    los comandos, todos con `php bin/<comando>.php`:
  migrar.php             aplica las migraciones pendientes (en cada despliegue)
  respaldar.php          respalda la base con pg_dump y borra los viejos (cron, una vez
                        por día; ver «Respaldos»)
  comprobar_acceso.php   comprueba desde afuera que el panel pide credenciales
                        (ver «Antes de exponerlo»)
  actualizar_tasas.php   baja las tasas de cambio reales y las guarda (cron, una
                        vez por día)
  recrear_con_datos_de_ejemplo.php   SOLO desarrollo: rearma la base y carga datos de ejemplo
views/                  plantillas PHP: una carpeta por sección o entidad (dashboard,
                        boletas, cobranza, pagos, notas, clientes, funnel, cohortes, auditoria) y en
                        ella una por pantalla, con el mismo nombre para lo mismo:
                        listado, alta, edicion y anulacion (boletas y pagos; los
                        clientes tienen listado, alta y ficha; las notas, listado
                        y devolucion). Con partials
                        compartidos: _filtro_fechas.php (el período y el rango
                        Desde/Hasta de las cinco pantallas con filtro),
                        _avisos.php (los avisos de arriba de la pantalla),
                        _paginacion.php (Anterior/Siguiente con número de
                        página) y _paginacion_por_cursor.php (Más recientes/Más
                        antiguas, la de Auditoría),
                        _error.php (el aviso de error de los formularios),
                        _accion_confirmar.php (el pie de las pantallas de
                        confirmar anulación), _grafico_antiguedad_de_cartera.php y
                        _grafico_serie_mensual.php (los dos graficos de barras
                        que se repetian en dashboard, cobros, pagos y funnel);
                        el funnel tiene además el suyo, funnel/_tabla_dimension.php
tests/
  Unit/                 sin base de datos (calculo de estado, filtros, funciones de vista,
                        paginación, CSRF, router, headers de seguridad, deteccion
                        de HTTPS, el comprobador de acceso)
  Integration/           contra la base real, casi todos en una transaccion que
                        se deshace (un archivo por repositorio, migraciones,
                        auditoría, zona horaria, conexión persistente, que una
                        base solo migrada traiga el catálogo de países y monedas,
                        que el estado de una boleta en SQL coincida con el de
                        PHP, que las pantallas pesadas no traigan miles de filas
                        a PHP, que las consultas de clientes y del funnel usen
                        sus índices, que la app, el trigger y el tramo de edad
                        coincidan en quién es mayor de edad en cada país, que las
                        tasas de cambio se actualicen con sus reglas de seguridad
                        (y el comando que corre cron), que cada fila guarde la tasa
                        de su día y los totales la usen, que el respaldo con
                        pg_dump salga, se pueda leer y rote bien, y que los datos
                        de ejemplo del seed cumplan las reglas de la app)
  Http/                  la app levantada con php -S, recorrida por HTTP
phpstan.neon            configuracion del analisis estatico
```

## Modelo de datos

- `monedas` / `paises`: catálogo de referencia (código ISO, nombre, símbolo y
  `tasa_a_usd` — cuánto vale 1 unidad de esa moneda en USD, para consolidar
  reportes). La migración `005` carga el catálogo (198 países y 146 monedas) con
  tasas de ejemplo, no el seed: una base de producción lo tiene apenas se migra (ver
  "Esquema de la base"). `bin/actualizar_tasas.php` las reemplaza por las reales
  y deja en `tasa_actualizada_en` y `tasa_fuente` desde cuándo y de dónde (`NULL` es
  una tasa de ejemplo; ver «Tasas de cambio»). Cada país trae también
  `mayoria_de_edad` (migración `010`, ver «Mayoría de edad»).
- `clientes`: clientes ya convertidos (vía funnel o cartera preexistente), con
  perfil (`pais_codigo`, `ciudad`, `idioma`, `genero`, `fecha_nacimiento`) para la
  segmentación del dashboard y su moneda de facturación. Solo se admiten mayores de
  edad, con la edad de su país (18 casi siempre): el alta lo valida con un mensaje
  claro y la base lo exige con un trigger (migraciones `007` y `010`, ver "Esquema
  de la base"). Que la persona no esté privada de libertad ni interdicta no es un
  dato que la app tenga, así que no se puede validar: el alta exige marcar una
  casilla que lo confirma (sin ella no hay cliente) y la entrada de auditoría del
  cliente deja asentado que se confirmó. Es una declaración de quien da el alta, no
  una verificación.
- `usuarios_funnel`: cada visitante que entra al funnel, con el mismo perfil y la
  fecha en que alcanzó cada etapa (`fecha_visita`, `fecha_registro`, `fecha_lead`,
  `fecha_conversion`) y el canal de adquisición. El perfil se genera una sola vez
  por persona y viaja a `clientes` si convierte.
- `boletas`: ingresos devengados al usuario final (monto, moneda, emisión,
  vencimiento, `anulada`) — no son facturas fiscales. Guarda `tasa_a_usd`, la tasa de
  su moneda al crearse (ver «La tasa de cada día»).
- `pagos`: cobros reales del usuario, opcionalmente ligados a una boleta
  (`boleta_id` puede ser `NULL` para anticipos/pagos sueltos) y con `anulada`. También
  guarda su `tasa_a_usd`.
  `pagos.metodo`, `clientes.genero` y `clientes.segmento` solo admiten las listas
  que ofrecen los formularios: la app las valida en el servidor y la base las
  restringe con `CHECK` (migración 004, `NOT VALID`: rigen para filas nuevas o
  modificadas y no revisan las que ya existían; el comentario de la migración
  explica cómo encontrarlas y validarlas).
- `usuarios_funnel`: cada etapa (`fecha_registro`, `fecha_lead`, `fecha_conversion`)
  solo existe si existe la anterior y ninguna es anterior a la etapa de antes ni a
  `fecha_visita`: sin eso, el funnel contaba tasas de lead a cliente mayores que 100 %
  y tiempos de conversión negativos. Es un `CHECK` `NOT VALID` (migración 013), igual
  que el anterior: no revisa las filas que ya existían.
- `notas_credito`: devoluciones. Al anular una boleta que ya tenía pagos, los
  pagos **no** se tocan (la plata entró de verdad y tiene que seguir en el
  historial de caja): se emite una nota de crédito por lo cobrado, que queda
  como devolución pendiente con el cliente. Los reportes de cobros restan estas
  notas para mostrar el neto, así anular una boleta cobrada no deja plata
  contando como ingreso sin respaldo. La nota se emite una sola vez y no se
  recalcula, así que una vez anulada la boleta sus pagos quedan congelados:
  anularlos o editarlos descuadraría la devolución, y la app lo rechaza con un
  409 (la regla simétrica de no poder cargar un pago nuevo contra una boleta
  anulada). Su `tasa_a_usd` es el promedio de la de los pagos que devuelve.
  `fecha_devolucion` (migración `012`) es `NULL` mientras la plata sigue pendiente de
  devolver y el día en que se devolvió cuando se registra desde la pantalla Notas de
  crédito: «pendiente» es `fecha_devolucion IS NULL`, sin un estado aparte que pueda
  contradecirse. Esa fecha no puede ser anterior a la nota (lo exige la base) ni
  futura (lo valida la app). Registrarla **no cambia los reportes de cobros**: siguen
  restando la nota el día en que se emitió, no el día en que se devolvió. Lo que falta
  devolver (Dashboard y Notas de crédito) se valúa a la tasa de hoy, como la cartera
  pendiente: es plata por pagar hoy en moneda extranjera. Las notas que ya existían al
  aplicar la migración quedan pendientes, porque la base no sabe si se devolvieron.
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
en USD, con la tasa que cada fila guardó al crearse (la cartera pendiente, con la de
hoy; ver «La tasa de cada día»); las tablas de detalle (boletas, pagos, ficha de
cliente) muestran el monto en su moneda original.

**Dinero.** La base guarda los montos como `NUMERIC(14, 2)`, exactos. En PHP viajan
como `float`, que arrastra ruido (`0.1 + 0.2` da `0.30000000000000004`), así que las
validaciones que comparan montos de una boleta o de un pago
(`BoletasController::montoCubreLoYaCobrado` y `PagosController::montoNoSuperaElSaldo`,
con su versión al editar un pago) los pasan a centavos enteros con
`App\Dinero::centavos()` y comparan ahí, sin margen: un pago no puede pasarse del saldo
ni por un centavo, y el monto de una boleta no puede quedar un centavo por debajo de lo
ya cobrado.

Otra cosa es la regla deliberada de que **un centavo o menos cuenta como cero**, para que
un residuo de redondeo no deje una boleta pendiente para siempre: una boleta con saldo de
0,01 o menos es `pagada` (`EstadoBoleta`, y en SQL `BoletaRepository::ESTADO_SQL`), no se
ofrece en el selector del formulario de pagos, la cartera por antigüedad no cuenta saldos
de 0,01 USD o menos, y anular una boleta con 0,01 o menos cobrado no emite nota de crédito.
Ese 0,01 está escrito en un solo lugar, `Dinero::UN_CENTAVO` (y `Dinero::cuentaComoCero()` para
compararlo): lo leen el PHP, las consultas SQL y las vistas. Es también el monto mínimo que
acepta `Validacion::montoEnRango()`, y el HTML de los cuatro formularios de monto (alta y
edición de boletas y de pagos) sale de `campo_monto()`, que le pone `min`, `max` y `step` desde
`Dinero::UN_CENTAVO` y `Validacion::MONTO_MAXIMO`: el navegador rechaza un monto fuera de rango
antes de enviarlo, y el servidor lo vuelve a validar.

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
- Paginación de 25 filas por página, con `LIMIT`/`OFFSET` en SQL, también en
  Boletas con filtro de estado. Boletas, pagos y clientes la arman con
  `Paginacion::consultar()`: cada repositorio dice las columnas, las tablas y los filtros,
  y la cuenta, la página acotada y el `LIMIT`/`OFFSET` salen de ahí. Auditoría es la excepción: pagina por cursor
  (`?antes=`/`?despues=` con el `creado_en` y el `id` de la fila donde quedó), porque
  es la única tabla que solo crece y `OFFSET` más `COUNT(*)` crecían en línea recta
  con ella. Con 2 millones de entradas la página del fondo pasó de 1,7 s a 2 ms, y
  la de cualquier profundidad tarda lo mismo que la primera (índice de la migración
  `008`). A cambio no hay «página 7 de 40» ni un total: se va hacia las más
  recientes o hacia las más antiguas. El estado (pagada/parcial/pendiente/vencida/anulada)
  no se guarda en la base: el que muestra cada fila lo calcula `App\EstadoBoleta`
  a partir de los pagos aplicados, y para filtrar y paginar en la base la misma
  regla está escrita en SQL (`BoletaRepository::ESTADO_SQL`). Son dos copias de
  una regla, y `EstadoBoletaSqlTest` las compara con todos los bordes: cambiar una
  sin la otra rompe ese test. (Antes el filtro traía todo el rango a PHP: con unas
  120 mil boletas y el límite de memoria estándar de 128 MB, la pantalla salía en
  blanco con un 500.)
- Las pantallas pesadas calculan en SQL y no traen filas a PHP. La antigüedad de
  cartera se suma en la base y devuelve 4 filas; las cinco segmentaciones del
  Dashboard salen de una sola consulta que calcula una vez lo que facturó cada
  cliente; el LTV por cohorte suma pagos y notas una vez por cliente.
  `RendimientoAEscalaTest` vigila que no vuelvan a leer miles de boletas en PHP.
  Medido con 30 mil clientes, 120 mil boletas, 100 mil pagos y 150 mil visitantes
  del funnel (la base y el servidor en la misma máquina), el Dashboard pasó de
  1,7 s a 0,4 s y Cobros de 0,65 s a 0,19 s.
- La conexión a Postgres es persistente en la web (`DB_PERSISTENT`, ver
  «Configuración»): abrirla cuesta entre 10 y 20 ms, más que lo que tarda una
  pantalla liviana en todo lo demás. Si la conexión guardada murió (se reinició
  Postgres), `Database::conectar()` la reemplaza en la misma petición. En CLI
  (migraciones, seed, tests) nunca es persistente.
- Dos índices (migración 006) para cuando las tablas crecen: `usuarios_funnel(cliente_id)`,
  que usa la ficha de un cliente para buscar su recorrido, y `clientes(nombre, id)`,
  por el que ordenan el listado y el selector de los formularios. Con 30 mil
  clientes y 150 mil visitantes del funnel bajan la ficha de 24 a 0,08 ms y el
  listado de 32 a 0,2 ms (la página 600, de 37 a 9 ms); con los datos del seed no
  se nota. El buscador de texto (`ILIKE '%…%'`, con el `%` y el `_` que escriba la persona escapados: `App\Busqueda`) queda sin índice a propósito:
  necesitaría `pg_trgm`, una extensión que pide permisos que la base de producción
  puede no darle a la app, y tarda unos 40 ms con 30 mil clientes. `IndicesTest`
  vigila que esas consultas sigan pudiendo usar su índice.
- Protección CSRF: `App\Csrf` guarda un token fijo por sesión que cada
  `<form method="post">` incluye oculto, y el `Router` lo valida antes de
  despachar cualquier POST — si falta o no coincide, corta con 403 antes de
  que el controller toque nada.
- Cookie de sesión endurecida: `HttpOnly` (JS no puede leerla — igual no hay
  JS en la app) + `SameSite=Lax` (capa extra contra CSRF, sumada al token) +
  `Secure` cuando el request llega por HTTPS (`App\ConexionSegura::esHttps()`, que
  tambien mira `X-Forwarded-Proto` si hay un proxy adelante). En HTTP plano
  (dev local) `Secure` queda apagado a proposito, sino el browser descarta
  la cookie y se pierde el token CSRF.
- Headers de seguridad en cada respuesta (`App\CabecerasDeSeguridad`):
  `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` y una
  `Content-Security-Policy` que bloquea JavaScript por completo
  (`script-src 'none'` — la app no usa JS en ningun lado) y permite estilos
  inline (`style-src 'unsafe-inline'`, que usan los graficos SVG). Con HTTPS
  se suma `Strict-Transport-Security`.
