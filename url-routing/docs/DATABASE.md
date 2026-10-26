# Base de datos

Cada sitio (genealogía y POS) tiene **su propia base de datos**. El código es
el mismo para SQLite y PostgreSQL: todo el SQL del proyecto (migraciones,
consultas, recursión de árboles) es portable y la suite de tests corre
contra ambos motores en CI. Qué motor usa cada sitio es una decisión de
despliegue, no de código.

## Qué motor para cada sitio

| | Genealogía | POS (Contrastocolor) |
|---|---|---|
| Volumen esperado | Muy alto: los ids de persona tienen 10 dígitos (hasta 10.000 millones) | Moderado: catálogo de una tienda (ids de producto de 8 dígitos) |
| Forma de las consultas | Recursivas (ascendencia/descendencia) sobre millones de filas | Lecturas simples de catálogo; escrituras al comprar |
| Escrituras concurrentes | Muchos aportantes a la vez | Pocas: cada checkout es una transacción corta |
| **Recomendado en producción** | **PostgreSQL** | **SQLite** mientras haya un solo servidor; **PostgreSQL** si hay varios servidores de aplicación o mucho checkout simultáneo |
| Desarrollo y tests | SQLite | SQLite |

Razones concretas:

- **SQLite** es un archivo local: cero administración y muy rápido en lectura,
  pero admite **un solo escritor a la vez** (con WAL, que activamos, los
  lectores no se bloquean) y no se comparte entre servidores. Encaja con un
  catálogo que se lee mucho y se escribe poco.
- **PostgreSQL** escala en volumen y concurrencia, planifica bien las consultas
  recursivas sobre tablas grandes y ya está instalado y centralizado en el VPS
  (ver `vps-setup/` y `ARCHITECTURE.md` en la raíz del repositorio).

## Configuración

Cada sitio resuelve su conexión así:

1. `DB_DSN_<SITIO>` (`DB_DSN_GENEALOGY`, `DB_DSN_POS`), con `DB_USER_<SITIO>` y `DB_PASSWORD_<SITIO>`.
2. Si no está definida: `sqlite:var/<sitio>.sqlite` dentro de `url-routing/` (desarrollo).

**No hay una variable compartida a propósito.** Los dos esquemas tienen tablas
con el mismo nombre (`usuarios`, `grupos`, `colecciones`): dos sitios en una
misma base mezclarían sus datos. Si igual ocurre por error, la segunda
migración falla en vez de saltarse en silencio, porque cada migración queda
registrada con el nombre de su sitio (`pos/001_esquema_inicial`). Con
PostgreSQL, usa una base distinta por sitio (pueden vivir en el mismo servidor).

Las variables pueden venir del entorno o de un `.env` (ver `.env.example`);
las del entorno real tienen prioridad sobre el archivo.

```bash
# PostgreSQL
DB_DSN_GENEALOGY=pgsql:host=127.0.0.1;port=5432;dbname=genealogia
DB_USER_GENEALOGY=genealogia_app
DB_PASSWORD_GENEALOGY=...

# SQLite (ruta absoluta, fuera del directorio público)
DB_DSN_POS=sqlite:/var/lib/url-routing/pos.sqlite
```

Con SQLite, el directorio del archivo debe ser escribible por el proceso de
PHP (SQLite crea archivos `-wal` y `-shm` junto a la base).

## Migraciones

```bash
php bin/migrate.php genealogy          # aplica las migraciones pendientes
php bin/migrate.php pos --seed         # aplica y carga datos de demostración (solo desarrollo)
```

- Las migraciones son archivos `database/<sitio>/migrations/NNN_nombre.sql`,
  aplicados en orden y **una sola vez** (se registran en `schema_migrations`).
- Cada archivo corre dentro de una transacción: si una sentencia falla, no
  queda nada a medias (ambos motores soportan DDL transaccional).
- Para cambiar el esquema, agrega un archivo nuevo con el siguiente número;
  nunca edites uno ya aplicado.
- Reglas para que el SQL siga siendo portable: nada de `SERIAL`,
  `AUTOINCREMENT` ni tipos propios de un motor; `BIGINT`, `INTEGER`, `TEXT`,
  `DATE`, `TIMESTAMP`, `BOOLEAN`; una sentencia por bloque terminada en `;` al
  final de línea (y sin `;` dentro de comentarios).

## Decisiones del esquema

- **La llave primaria es el id público de la URL.** Un `CHECK` fija el ancho
  de dígitos de cada tipo (persona: 10, suceso: 9, …): un id que no cabe
  generaría una URL que el router despacharía a otro tipo.
- **Las fechas de una persona salen de sus sucesos.** Nacimiento y defunción
  son sucesos (`sucesos` + `suceso_participantes`), no columnas duplicadas en
  `personas` que podrían contradecirse.
- **Si una persona vive se deduce de esas mismas fechas** (sin defunción y
  nacida hace menos de 110 años) y `personas.viva` (nullable) lo corrige en
  cualquier sentido; se usa para ocultarla a quien no es el propietario
  (ver [`ACCESO.md`](./ACCESO.md)). No hay una columna «vive» calculada: se
  decide al consultar, con las consultas de `Support/Privacidad.php`.
- **Padre y madre viven en `personas`** (`padre_id`, `madre_id`), así la
  ascendencia y la descendencia son una sola consulta `WITH RECURSIVE`, con un
  tope de generaciones que además protege de ciclos en datos mal cargados.
- **Los lugares se identifican por su ruta de códigos** (`mx/jal/gdl`), que es
  exactamente la URL; consultar un lugar incluye todo lo que está debajo.
- **Los listados `/b/…` se leen en el orden de un índice.** Cada página muestra
  50 filas en un orden fijo (personas por apellidos y nombres; sucesos y
  registros por fecha). `004_indices_de_listados.sql` crea un índice en
  exactamente ese orden, para que la base lea en orden y se detenga en las
  primeras 50 filas que cumplen el filtro de privacidad, en vez de ordenar la
  tabla entera. `personas_apellidos_idx` (de `001`) queda como prefijo del
  nuevo y podría quitarse; no se quita para que la migración solo agregue.
- **El dinero se guarda en centavos enteros** (`INTEGER`), nunca en coma flotante.
- **Las líneas de una orden copian nombre y precio** del producto al momento de
  la compra: la orden debe seguir mostrando lo que se pagó aunque el producto
  cambie después.
- **La dirección de envío se copia a la orden y no se borra sola.**
  `ordenes.direccion_envio` guarda la dirección tal como se usó, por la misma
  razón que las líneas copian el precio: la orden, la boleta y una garantía o
  devolución tienen que mostrar adónde se mandó. Se conserva mientras exista la
  orden, sin purga automática: lo decidió la persona a cargo, así que no se
  agrega un borrado por plazo sin consultarlo. La libreta `direcciones` es del
  cliente y dura lo que el cliente quiera.
  Desde el 1 de diciembre de 2026 rige la Ley 21.719 (ver
  `privacy-laws/countries/cl/`): el titular puede pedir acceso o supresión, y la
  política de privacidad del POS tiene que decir que estas direcciones se
  guardan mientras exista la orden y por qué.

## Tests

La suite corre siempre contra SQLite en memoria y, si `TEST_PG_DSN` está
definida, también contra PostgreSQL (cada ejecución usa un esquema propio que
se borra al terminar):

```bash
./vendor/bin/phpunit                                    # solo SQLite

TEST_PG_DSN='pgsql:host=127.0.0.1;dbname=routing_test' \
TEST_PG_USER=routing_test TEST_PG_PASSWORD=routing_test \
./vendor/bin/phpunit                                    # SQLite + PostgreSQL
```

## Limitaciones conocidas

- Un cliente no puede borrar sus direcciones ni su cuenta desde el sitio: la
  vista `/0/4/` sólo las lista. Un pedido de supresión (Ley 21.719) se atiende
  a mano en la base: se borran sus filas de `direcciones` y, si no hay una
  obligación legal de conservarla, la `direccion_envio` de sus órdenes se
  reemplaza por `NULL` (la columna lo admite; la orden y sus líneas quedan).

- La búsqueda ignora mayúsculas y acentos (`Repository::sinAcentos()`): compara
  `TRANSLATE(LOWER(columna), 'áéí…', 'aei…') LIKE ?` contra el texto normalizado
  igual, así que «garcia» encuentra «García» y «ÁLVAREZ» encuentra «Álvarez».
  `TRANSLATE` es de PostgreSQL; en SQLite, `Database` registra una función
  equivalente al conectar, así que el SQL es el mismo en los dos motores.
  Para búsqueda seria en genealogía conviene `pg_trgm` o búsqueda de texto
  completo en PostgreSQL, en una migración específica de ese motor.
- `LIKE '%texto%'` no usa índices; con millones de personas hará falta un
  índice de trigramas (PostgreSQL) o una tabla de búsqueda aparte. Con texto, la
  búsqueda recorre en orden hasta juntar 50 coincidencias: una palabra frecuente
  termina enseguida, una rara las busca en todo el catálogo.
- La búsqueda de sucesos en PostgreSQL no siempre usa el índice de
  `sucesos_fecha_idx`: el plan depende del tamaño de las tablas y de sus
  estadísticas (con 20.000 personas lee la tabla entera; con 80.000 lo usa para
  el visitante). Las listas de un lugar no cambiaron.
- **JIT apagado.** `Database` hace `SET jit = off` al conectar a PostgreSQL,
  diga lo que diga la configuración del servidor. Las expresiones de privacidad
  (`Privacidad`) hacen que las búsquedas y listas de un visitante parezcan caras
  al planificador, y PostgreSQL las compilaba (JIT) en cada petición: con 20.000
  personas, 2,5 s de compilación para una búsqueda de 70 ms. Para consultas
  cortas como estas el JIT nunca compensa.
- **Lo que cuesta una lista del visitante.** Las listas por apellido (ficha,
  red y dispersión) y por lugar eligen primero las filas sobre las tablas reales
  y enmascaran solo esas (como la búsqueda de personas), así que no calculan
  «quién está oculto» para todo el catálogo. Medido con 20.000 personas en
  PostgreSQL, un visitante tardaba entre 37 y 61 ms en esas páginas, y tarda
  entre 2 y 13 ms.
