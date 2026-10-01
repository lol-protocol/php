# Phone Directory Parser — estado del proyecto

Estado del módulo `phone-directory/`: qué incluye, cómo se verifica y qué queda pendiente.
La descripción funcional está en [PHONE_DIRECTORY_SUMMARY.md](PHONE_DIRECTORY_SUMMARY.md).

**Lenguajes**: PHP 8.1+ (implementación principal) · Python 3 y Java 11+ (ports simplificados)

---

## Qué incluye

### Implementación PHP (`src/PhoneDirectory/`)
- Parser multi-idioma (es, en, fr, pt, de, it) con detección automática de idioma, registros de una línea o en
  bloques, lectura del archivo línea a línea y errores de parseo por registro en vez de abortar el archivo.
- Clasificación de personas naturales y jurídicas (empresas) con vocabulario por idioma.
- Descomposición de nombres: apellidos según el idioma, partículas, conectores, títulos y viudas.
- Ubicación geográfica: país ISO 3166-1, zona, ciudad y calle.
- Persistencia en SQLite, MySQL/MariaDB y PostgreSQL con migración de esquemas antiguos.
- Búsquedas sin acentos, por teléfono, por sonido del apellido y por tipo de empresa.
- `RecordLinker`: propone qué entradas de distintos directorios son la misma persona.
- Catálogo de 16 directorios históricos (1878–1975, 10 países).
- CLI `bin/phonedir`: `import`, `search`, `stats`, `link`, `catalog list`, `catalog show`.

### Ports (`ports/`)
- `ports/python/phone_directory_parser.py` y `ports/java/PhoneDirectoryParser.java`: parser multi-idioma,
  nombres, ubicación y catálogo con las mismas reglas de nombres y detección de idioma que el PHP.
- Son versiones simplificadas: no clasifican empresas, no tienen claves fonéticas ni `RecordLinker`, y el de Java
  no persiste en base de datos (el de Python usa SQLite).

### Ejemplos y documentación
- `examples/process_phone_directory.php`, `examples/multilingual_example.php`, `examples/sample_phone_directory.txt`.
- `README.md`: guía de uso y API completa.
- `docs/`: este reporte, el resumen funcional, la arquitectura (`ARCHITECTURE.md`), las estimaciones de
  escalabilidad y el análisis de errores.

---

## Métricas

| Métrica | Valor |
|---------|-------|
| Archivos PHP en `src/` | 35 |
| Líneas de código PHP (`src/`) | ~4 000 |
| Líneas de tests PHP (`tests/`) | ~3 200 |
| Tests PHPUnit | 231 |
| Tests de los ports | 13 (Python) + 12 chequeos (Java) |
| Idiomas | 6 |
| Motores de base de datos | SQLite, MySQL/MariaDB, PostgreSQL |
| Directorios en el catálogo | 16 |

---

## Tests

| Archivo (`tests/PhoneDirectory/`) | Tests | Qué cubre |
|-----------------------------------|-------|-----------|
| `PersonNameTest` | 33 | Nombres, apellidos, partículas, títulos |
| `PhoneDirectoryParserTest` | 33 | Parser original |
| `PhoneDirectoryDatabaseTest` | 27 | CRUD, búsquedas, migración, backfill |
| `JuridicalEntityDatabaseTest` | 19 | CRUD y búsquedas de empresas |
| `MultiLanguagePhoneDirectoryParserTest` | 19 | Idiomas, empresas, lectura de archivos, UTF-8 inválido |
| `PhoneDirectoryCatalogTest` | 17 | Catálogo histórico |
| `PotentialErrorsTest` | 14 | Los errores de [ERRORES_ENCONTRADOS.md](ERRORES_ENCONTRADOS.md) |
| `GeoLocationTest` | 13 | Validación de ubicación |
| `PhoneDirectoryManagerTest` | 13 | Manager v1 (obsoleto) |
| `CliTest` | 11 | Comandos de `bin/phonedir` |
| `RecordLinkerTest` | 11 | Enlace de registros entre directorios |
| `CrossDatabaseTest` | 7 | Mismo comportamiento en SQLite, PostgreSQL y MySQL |
| `SurnameKeysTest` | 6 | Soundex y claves fonéticas |
| `PhoneDirectoryManagerV2Test` | 4 | Manager actual |
| `TextFoldingTest` | 2 | Plegado de acentos y validación UTF-8 |
| `ExamplesRunTest` | 2 | Los ejemplos de `examples/` corren sin errores ni avisos |

La cobertura de código no se mide en CI.

### Integración continua (`.github/workflows/tests.yml`)
- `phone-directory / PHP 8.1–8.4`: suite PHPUnit (incluidos los ejemplos) contra SQLite y un PostgreSQL 16 real, y una corrida corta de
  `bin/benchmark.php`.
- `phone-directory / Python and Java ports`: tests de los dos ports.

---

## Uso rápido

```bash
# PHP
cd phone-directory && composer install
php bin/phonedir import examples/sample_phone_directory.txt --dsn=sqlite:demo.sqlite
php bin/phonedir search --name=garcia --dsn=sqlite:demo.sqlite

# Python
cd phone-directory/ports/python && python3 phone_directory_parser.py && python3 -m unittest

# Java
cd phone-directory/ports/java && javac -d build *.java && java -cp build PhoneDirectoryParserTest
```

---

## Pendientes

- `Config/ParserConfig` y `Logger/*` existen pero no están conectados a nada.
- `Manager/PhoneDirectoryManager` (v1) está marcado `@deprecated`; `PhoneDirectoryManagerV2` lo reemplaza.
- `parseFile()` acumula todas las entradas en memoria; para archivos enormes haría falta procesarlas por lotes a
  medida que se leen.
- Los ports no tienen paridad con el PHP (ver arriba).

Las estimaciones de volumen, tiempos y hardware están en
[ESTIMATES_AND_SCALABILITY.md](ESTIMATES_AND_SCALABILITY.md).
