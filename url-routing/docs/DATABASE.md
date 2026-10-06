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
- **Padre y madre viven en `personas`** (`padre_id`, `madre_id`), así la
  ascendencia y la descendencia son una sola consulta `WITH RECURSIVE`, con un
  tope de generaciones que además protege de ciclos en datos mal cargados.
- **Los lugares se identifican por su ruta de códigos** (`mx/jal/gdl`), que es
  exactamente la URL; consultar un lugar incluye todo lo que está debajo.
- **El dinero se guarda en centavos enteros** (`INTEGER`), nunca en coma flotante.
- **Las líneas de una orden copian nombre y precio** del producto al momento de
  la compra: la orden debe seguir mostrando lo que se pagó aunque el producto
  cambie después.

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

- La búsqueda usa `LOWER(...) LIKE`: en SQLite, `LOWER` solo pasa a minúsculas
  ASCII, así que buscar «álvarez» no encuentra «Álvarez» (en PostgreSQL sí).
  Para búsqueda seria en genealogía conviene `pg_trgm` o búsqueda de texto
  completo en PostgreSQL, en una migración específica de ese motor.
- `LIKE '%texto%'` no usa índices; con millones de personas hará falta un
  índice de trigramas (PostgreSQL) o una tabla de búsqueda aparte.
