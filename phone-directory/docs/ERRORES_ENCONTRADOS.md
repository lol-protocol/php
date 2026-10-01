# Lista de errores encontrados y su estado

Revisión estática original de 14 errores potenciales, verificada de nuevo contra el código actual.
**Los 14 están corregidos o no aplican.** Cada uno indica dónde se resolvió y qué test lo cubre.

Rutas relativas a `phone-directory/`. Para correr los tests de esta lista:

```bash
cd phone-directory
./vendor/bin/phpunit tests/PhoneDirectory/PotentialErrorsTest.php
```

| # | Error | Severidad original | Estado |
|---|-------|--------------------|--------|
| 1 | `PDO::query()` devuelve `false` sin validar | 🔴 Crítica | No aplica |
| 2 | `lastInsertId()` vacío se convierte en ID 0 | 🔴 Crítica | ✅ Corregido |
| 3 | `commit()` fallido deja la transacción abierta | 🔴 Crítica | ✅ Corregido |
| 4 | Migración de BD antigua con `country_code` NULL | 🟠 Alta | ✅ Corregido |
| 5 | UTF-8 inválido hace fallar `preg_match` | 🟠 Alta | ✅ Corregido |
| 6 | `RecordLinker::year()` con `sourceDirectoryId` nulo | 🟠 Alta | No aplica |
| 7 | `PersonName` con arrays sin inicializar | 🟠 Alta | No aplica |
| 8 | `PhonePattern::REGEX` sin validar | 🟠 Alta | ✅ Corregido |
| 9 | Idioma no soportado cae en silencio a inglés | 🟠 Alta | ✅ Corregido |
| 10 | `JuridicalEntityPDODatabase` hereda #1–#3 | 🟠 Alta | ✅ Corregido |
| 11 | Comodines `%` y `_` sin escapar en búsquedas `LIKE` | 🟡 Media | ✅ Corregido |
| 12 | `RecordLinker` agrupa entradas sin nombre de pila | 🟡 Media | ✅ Corregido |
| 13 | Soundex con apellidos muy largos | 🟡 Media | ✅ Corregido |
| 14 | Codificación inválida tras `mb_strtolower` + `AccentFolding` | 🟡 Media | ✅ Corregido |

---

### 1. `PDO::query()` devuelve `false` sin validar — no aplica
- **Hallazgo original**: `query()` puede devolver `false` y el código llama `fetchAll()` sobre ese valor.
- **Por qué no aplica**: `SqlDialect::connect()` abre toda conexión con `PDO::ERRMODE_EXCEPTION`, así que PDO lanza
  `PDOException` en lugar de devolver `false`. El único lugar que comprueba `false` a propósito es
  `EntityPDODatabase::executeWithBackfill()`, que lanza `DatabaseException`.

### 2. `lastInsertId()` vacío se convierte en ID 0 — corregido
- **Hallazgo original**: con un `lastInsertId()` vacío, `(int) ""` da `0` y se devuelve un ID inválido.
- **Corrección**: `EntityPDODatabase::validateInsertId()` lanza `DatabaseException` si el ID es vacío o `0`.
- **Cobertura**: `CrossDatabaseTest` inserta y relee registros contra PostgreSQL en CI (`PHONEDIR_PGSQL_DSN`).

### 3. `commit()` fallido deja la transacción abierta — corregido
- **Hallazgo original**: si algo falla dentro del lote, no se hace rollback y la conexión queda inutilizable.
- **Corrección**: `EntityPDODatabase::insertManyWithTransaction()` y `executeWithBackfill()` envuelven el lote
  completo, incluido `commit()`, en `try/catch`; ante cualquier error hacen `rollBack()` y lanzan `DatabaseException`.
- **Cobertura**: `PotentialErrorsTest::testFailedBatchInsertRollsBackEarlierRows` (falla en la segunda fila:
  la primera se revierte y la conexión sigue usable).

### 4. Migración de BD antigua con `country_code` NULL — corregido
- **Hallazgo original**: la restricción `NOT NULL DEFAULT 'US'` rechaza filas antiguas con `country_code` NULL.
- **Corrección**: `createTable()` agrega la columna con su valor por defecto y ejecuta
  `DatabaseConstants::MIGRATION_NULL_COUNTRY_CODE` para rellenar los NULL existentes.
- **Cobertura**: `PhoneDirectoryDatabaseTest::testCreateTableAddsMissingColumnsToOldDatabase`.
  `PotentialErrorsTest::testError12_LegacyDatabaseWithNullCountryCodeFailsMigration` confirma que un INSERT
  *nuevo* con NULL sigue rechazándose, que es lo esperado.

### 5. UTF-8 inválido hace fallar `preg_match` — corregido
- **Hallazgo original**: con archivos mal codificados, las regex `/u` devuelven `false` y las líneas se pierden.
  En la práctica era peor: una sola línea inválida abortaba el parseo del archivo entero con un `TypeError`.
- **Corrección**: los dos parsers registran esos registros como error de parseo (`Invalid UTF-8 encoding`) y siguen;
  `PersonName` y `TextFolding::fold()` lanzan `InvalidEncodingException` en vez de fallar con un `TypeError`.
- **Cobertura**: `MultiLanguagePhoneDirectoryParserTest::testInvalidUtf8LineIsAParseErrorNotAnEntry`,
  `PotentialErrorsTest::testInvalidUtf8NameIsRejectedClearly`, `TextFoldingTest`.

### 6. `RecordLinker::year()` con `sourceDirectoryId` nulo — no aplica
- **Hallazgo original**: acceso a `$m[1]` sin comprobar que la regex coincidió.
- **Por qué no aplica**: `$m[1]` solo se lee dentro de `if ($dirId && preg_match(...))`; si no hay año, devuelve
  `PHP_INT_MAX`.
- **Cobertura**: `RecordLinkerTest::testUncatalogedDirectoriesWithNoYearFallBackToInputOrder`.

### 7. `PersonName` con arrays sin inicializar — no aplica
- **Hallazgo original**: si `parse()` lanza una excepción, `$firstNames` y `$lastNames` quedan sin inicializar.
- **Por qué no aplica**: si el constructor lanza, el objeto no llega a existir. Además ambos arrays se inicializan
  antes de llamar a `parse()`.
- **Cobertura**: `PotentialErrorsTest::testError13_PersonNameRejectsWhitespaceOnlyName`.

### 8. `PhonePattern::REGEX` sin validar — corregido
- **Corrección**: `PhonePattern::getValidatedRegex()` compila la regex una vez y lanza `InvalidArgumentException`
  si es inválida. Los dos parsers la obtienen por ese método.
- **Cobertura**: `PotentialErrorsTest::testPhoneNumberPatternIsValid`.

### 9. Idioma no soportado cae en silencio a inglés — corregido
- **Corrección**: `MultiLanguagePhoneDirectoryParser` lanza `InvalidLanguageException` con la lista de idiomas
  soportados.
- **Cobertura**: `PotentialErrorsTest::testUnsupportedLanguageIsRejected`.

### 10. `JuridicalEntityPDODatabase` hereda #1–#3 — corregido
- **Corrección**: las dos clases de base de datos heredan de `EntityPDODatabase`, así que comparten las correcciones
  de #2 y #3.
- **Cobertura**: `JuridicalEntityDatabaseTest`.

### 11. Comodines `%` y `_` sin escapar en búsquedas `LIKE` — corregido
- **Corrección**: `SqlDialect::containsValue()` escapa `%`, `_` y el carácter de escape `!`.
- **Cobertura**: `PhoneDirectoryDatabaseTest::testSearchTreatsWildcardCharactersLiterally`.

### 12. `RecordLinker` agrupa entradas sin nombre de pila — corregido
- **Corrección**: `RecordLinker::link()` descarta las entradas con nombre de pila vacío antes de agruparlas por inicial.
- **Cobertura**: `RecordLinkerTest`.

### 13. Soundex con apellidos muy largos — corregido
- **Corrección**: `SurnameKeys::soundex()` recorta el apellido a `MAX_SURNAME_LENGTH` y Soundex siempre produce
  4 caracteres.
- **Cobertura**: `PotentialErrorsTest::testVeryLongSurnameGivesFixedSizeSoundex`.

### 14. Codificación inválida tras `mb_strtolower` + `AccentFolding` — corregido
- **Hallazgo original**: la validación ocurría *después* de `mb_strtolower()`, que ya había reemplazado los bytes
  inválidos por `?`, así que nunca detectaba nada.
- **Corrección**: `TextFolding::fold()`, compartido por la capa de base de datos y `RecordLinker`, valida la entrada
  antes de transformarla.
- **Cobertura**: `TextFoldingTest`.
