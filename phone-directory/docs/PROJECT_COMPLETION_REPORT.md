# Phone Directory Parser — estado del proyecto

Estado del módulo `phone-directory/`: qué incluye, cómo se verifica y qué queda pendiente.
La descripción funcional y el uso están en el [README](../README.md); los diagramas, en [ARCHITECTURE.md](ARCHITECTURE.md).

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
- El parser de los dos sigue al de PHP: pasan el mismo conjunto de evaluación (`tests/fixtures/parser-evaluation.json`),
  con el formato de una entrada por línea y las empresas como entradas `juridical`.
- En lo demás son versiones simplificadas: no tienen claves fonéticas, `RecordLinker` ni la vista de privacidad, el de
  Java no persiste en base de datos y el de Python (SQLite) solo guarda las personas.

### Ejemplos y documentación
- `examples/process_phone_directory.php`, `examples/multilingual_example.php`, `examples/sample_phone_directory.txt`.
- `README.md`: guía de uso y API completa.
- `docs/`: este reporte, la arquitectura (`ARCHITECTURE.md`), las estimaciones de escalabilidad y la lista de errores
  de la revisión estática (`ERRORES_ENCONTRADOS.md`).

---

## Cómo se verifica

Una prueba por clase o tema en `tests/PhoneDirectory/` (la lista de archivos se queda al día sola: `ls tests/PhoneDirectory`),
más el conjunto de evaluación del parser (`tests/fixtures/parser-evaluation.json`) que también corren los ports. Cómo
correrlas está en la sección «Pruebas» del [README](../README.md). La cobertura de código no se mide en CI.

### Integración continua (`.github/workflows/pruebas-phone-directory.yml`)
- `phone-directory / PHP 8.1–8.4`: suite PHPUnit (incluidos los ejemplos) contra SQLite y un PostgreSQL 16 real,
  análisis estático con PHPStan, una corrida corta de `bin/benchmark.php` y el conjunto de evaluación del parser.
- `phone-directory / Python and Java ports`: tests de los dos ports.

---

## Pendientes

- `Config/ParserConfig` no está conectada a nada: está marcada `@deprecated` y se conserva como referencia (por la
  regla del repositorio de no borrar código sin uso).
- Los ports resuelven los mismos casos que el PHP, pero no tienen claves fonéticas, `RecordLinker` ni la vista de
  privacidad (ver arriba).

Resuelto: los archivos grandes se leen y guardan por lotes (`parseFileInBatches()`, `phonedir import --lote`), sin
que la memoria crezca con el archivo.

Las estimaciones de volumen, tiempos y hardware están en
[ESTIMATES_AND_SCALABILITY.md](ESTIMATES_AND_SCALABILITY.md).
