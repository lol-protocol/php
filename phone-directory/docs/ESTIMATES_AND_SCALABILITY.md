# Phone Directory Parser — estimaciones y escalabilidad

Este documento separa lo **medido** (con `bin/benchmark.php`, reproducible) de lo **estimado** (proyecciones
a partir de esas mediciones y de supuestos explícitos sobre volumen de datos). Las estimaciones son órdenes de
magnitud, no compromisos.

---

## 1. Rendimiento medido

Medido con `php bin/benchmark.php 100000` y `php bin/benchmark.php 300000`: directorio sintético en inglés
(nombre, calle y teléfono en bloques de 3 líneas), un solo proceso, SQLite en archivo, PHP 8.4, máquina de 4 núcleos.

| Operación | 100 000 entradas (4 MB) | 300 000 entradas (12 MB) |
|-----------|-------------------------|--------------------------|
| Parseo | ~39 000 entradas/s | ~29 000 entradas/s |
| Memoria pico tras parsear | 180 MB | 546 MB |
| Inserción por lotes (`insertBatch`) | ~34 000 filas/s | ~24 000 filas/s |
| Tamaño en base de datos, con índices | ~336 bytes/fila | ~337 bytes/fila |

Consultas sobre 100 000 filas:

| Consulta | Tiempo | Resultado |
|----------|--------|-----------|
| `findById` | ~0,3 ms | 1 fila |
| `findByPhone` | ~0,1 ms | 1 fila |
| `findBySurnameSound` | ~155 ms | 12 500 filas |
| `findByStreet` (subcadena) | ~330 ms | 20 000 filas |
| `findByName` (subcadena) | ~430 ms | 12 500 filas |

**Cómo leerlo:**
- Las búsquedas por ID y teléfono usan índices y no dependen del tamaño de la tabla. Las búsquedas por subcadena
  (`LIKE '%texto%'`) recorren la tabla, así que su tiempo crece linealmente con el número de filas; en estas
  mediciones, además, devuelven miles de filas, y construir esos objetos es parte del costo.
- **La memoria crece ~1,8 KB por entrada**, porque `parseFile()` lee el archivo línea a línea pero devuelve todas las
  entradas en un array. Un directorio nacional del catálogo (3–5 millones de entradas) necesitaría 5–9 GB para
  parsearse de una vez. Para archivos así hay que partirlos antes de importarlos (ver sección 4).
- Datos reales (OCR, formatos mixtos, más errores) serán más lentos que este directorio sintético y regular.

---

## 2. Volumen de datos: supuestos

- El catálogo incluido (`PhoneDirectoryCatalog`) tiene 16 directorios de 10 países entre 1878 y 1975, con
  ~17,9 millones de entradas estimadas en total. Un directorio nacional grande tiene entre 2 y 5 millones de entradas.
- Una entrada ocupa ~40–60 bytes de texto y ~340 bytes en la base de datos con sus índices (medido).
- A nivel mundial, el máximo histórico de líneas fijas fue del orden de mil millones. Contando varias ediciones por
  línea a lo largo de las décadas, el total de entradas de todos los directorios existentes es del orden de
  **10 mil millones**. Es una cota gruesa, no una medición.

---

## 3. Escenarios

Tiempos para un solo proceso con los rendimientos medidos (~16 000 entradas/s de extremo a extremo: parseo +
inserción). RAM = memoria para parsear el archivo más grande del escenario de una vez.

| Escenario | Entradas | Texto | Base de datos | Tiempo (1 proceso) | RAM para parsear |
|-----------|----------|-------|---------------|--------------------|------------------|
| Pequeño: una ciudad, un año | 100 mil | ~5 MB | ~35 MB | ~6 s | ~180 MB |
| Mediano: un país, 50 años (~10 ediciones de 3 M) | 30 M | ~1,5 GB | ~10 GB | ~30 min | ~5,5 GB por edición* |
| Grande: 10 países, 100 años | 600 M | ~30 GB | ~200 GB | ~10 h | ~5,5 GB por edición* |
| Máximo: todos los directorios conocidos | ~10 000 M | ~0,5 TB | ~3,5 TB | ~7 días | ~5,5 GB por edición* |

\* Solo si se parsea cada edición completa de una vez. Partiendo los archivos en trozos de ~100 000 entradas, la
memoria queda en ~200 MB por proceso.

---

## 4. Cómo escalar

- **Partir los archivos grandes** en trozos antes de importarlos (`split -l`, cortando entre registros, en una
  línea vacía). Es la forma de mantener acotada la memoria mientras `parseFile()` devuelva todas las entradas juntas.
- **Paralelizar por archivo.** El parseo no comparte estado entre archivos, así que N procesos parsean ~N veces más
  rápido. La inserción no escala igual: SQLite admite un solo escritor a la vez. Para cargas en paralelo conviene
  PostgreSQL o MySQL, ambos soportados.
- **Búsquedas por subcadena en tablas grandes.** Con cientos de millones de filas, `LIKE '%texto%'` es lento aun con
  índices. Las opciones son buscar por prefijo, por teléfono o por sonido del apellido (que usan índices), o agregar
  índices de texto propios del motor (por ejemplo `pg_trgm` en PostgreSQL).

### Hardware orientativo

| Escenario | CPU | RAM | Disco | Base de datos |
|-----------|-----|-----|-------|---------------|
| Pequeño | 2 núcleos | 2 GB | 10 GB | SQLite |
| Mediano | 4–8 núcleos | 8–16 GB | 50 GB SSD | SQLite o PostgreSQL |
| Grande | 16–32 núcleos | 32–64 GB | 500 GB–1 TB SSD | PostgreSQL |
| Máximo | Varios servidores | 256 GB+ en total | 5–10 TB SSD | PostgreSQL particionado o distribuido |

---

## 5. Calidad de los datos

Factores que afectan los resultados reales y que el benchmark sintético no refleja:
- **OCR**: los directorios escaneados tienen errores de reconocimiento que producen nombres o calles incorrectos.
  Los parsers los reportan como errores de parseo cuando falta un campo obligatorio.
- **Codificación**: archivos en Latin-1 u otras codificaciones. Las líneas con UTF-8 inválido se reportan como
  errores de parseo; conviene convertir los archivos a UTF-8 antes (`iconv -f latin1 -t utf-8`).
- **Duplicados**: la misma persona aparece en varias ediciones; `RecordLinker` ayuda a vincularlas.
- **Cobertura**: no todos tenían teléfono, y la proporción varía mucho según el país y la década.
