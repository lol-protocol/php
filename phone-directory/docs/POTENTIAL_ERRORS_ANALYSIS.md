# Análisis de errores potenciales — Phone Directory Parser

Análisis detallado de los 14 errores potenciales encontrados en la revisión estática original, con lo que hace
hoy el código en cada caso. El resumen con el estado de cada uno está en [ERRORES_ENCONTRADOS.md](ERRORES_ENCONTRADOS.md).

Rutas relativas a `phone-directory/src/PhoneDirectory/` salvo que se indique otra cosa.

**Resultado: los 14 están corregidos o no aplican.** En dos casos (#7 y #13) el análisis original estaba equivocado;
se explica abajo.

---

## Críticos

### #1 — `PDO::query()` devuelve `false` sin validar (no aplica)

**Análisis original.** Si `query()` fallaba por permisos, tabla corrupta o conexión perdida, devolvería `false` y
`->fetchAll()` fallaría con `Call to a member function fetchAll() on bool`.

**Por qué no aplica.** `SqlDialect::connect()` crea toda conexión con `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`,
así que un fallo de `query()` lanza `PDOException` y nunca devuelve `false`. Aun así, el backfill lo comprueba
explícitamente (`Database/EntityPDODatabase.php`):

```php
$result = $this->pdo->query($selectQuery);
if ($result === false) {
    throw new DatabaseException(sprintf(
        DatabaseConstants::SQL_BACKFILL_NOT_NULL_ERROR,
        $this->getTableName(),
        implode(', ', $this->pdo->errorInfo())
    ));
}
```

### #2 — `lastInsertId()` vacío se convierte en ID 0 (corregido)

**Análisis original.** `return (int) $this->pdo->lastInsertId();` convertía un `""` en `0` sin avisar.

**Hoy.** Los dos `insert()` pasan el valor por `EntityPDODatabase::validateInsertId()`:

```php
protected function validateInsertId($id): int
{
    if (!$id || $id === '0' || $id === 0) {
        throw new DatabaseException(DatabaseConstants::ERROR_NO_LAST_INSERT_ID);
    }
    return (int) $id;
}
```

Con columnas `SERIAL`, el driver de PostgreSQL devuelve el ID correcto sin nombre de secuencia; `CrossDatabaseTest`
lo comprueba en CI contra un PostgreSQL real.

### #3 — `commit()` fallido deja la transacción abierta (corregido)

**Análisis original.** El backfill hacía `beginTransaction()` / `commit()` sin `try/catch`; un error a mitad dejaba la
transacción abierta y las llamadas siguientes fallaban con "There is already an active transaction".

**Hoy.** Las dos rutas transaccionales, `insertManyWithTransaction()` (inserción por lotes) y `executeWithBackfill()`,
envuelven el bucle y el `commit()` en `try/catch`, hacen `rollBack()` y relanzan como `DatabaseException` conservando
la excepción original como `previous`.

**Test.** `tests/PhoneDirectory/PotentialErrorsTest.php::testFailedBatchInsertRollsBackEarlierRows`: la segunda fila
del lote falla, la primera se revierte (`count() === 0`) y la misma conexión puede insertar después.

---

## Altos

### #4 — Migración de BD antigua con `country_code` NULL (corregido)

**Análisis original.** Al agregar `country_code NOT NULL DEFAULT 'US'` a una tabla antigua, las filas con NULL
violarían la restricción.

**Hoy.** `createTable()` agrega las columnas que faltan y rellena los NULL antes del backfill:

```php
$this->dialect->ensureTable(self::TABLE_NAME, self::BASE_COLUMNS, self::ADDED_COLUMNS, self::INDEXED_COLUMNS);
$this->pdo->exec(sprintf(DatabaseConstants::MIGRATION_NULL_COUNTRY_CODE, self::TABLE_NAME, $this->getDefaultCountryCode()));
$this->backfillDerivedColumns();
```

**Tests.** `PhoneDirectoryDatabaseTest::testCreateTableAddsMissingColumnsToOldDatabase` migra una tabla antigua real.
`PotentialErrorsTest::testError12_LegacyDatabaseWithNullCountryCodeFailsMigration` confirma que un INSERT *nuevo*
con `country_code` NULL se sigue rechazando, que es el comportamiento buscado.

### #5 — UTF-8 inválido hace fallar las regex `/u` (corregido)

**Análisis original.** Con UTF-8 inválido, `preg_match(..., /u)` devuelve `false` y la línea se descarta en silencio.

**Lo que realmente pasaba era peor.** `SingleLineEntrySplitter` pasaba el resultado de `preg_split()` (que es `false`)
a `array_map()`, así que una sola línea en Latin-1 abortaba el parseo del archivo entero con un `TypeError`.
Construir un `PersonName` con UTF-8 inválido fallaba de la misma forma.

**Hoy.**
- Los dos parsers no llaman al splitter con líneas inválidas; el registro llega a `finalizeEntry()`, que lo
  registra como error de parseo (`Invalid UTF-8 encoding`), y el resto del archivo se procesa normal.
- `Entity/PersonName` lanza `InvalidEncodingException` si el nombre no es UTF-8 válido.
- `TextFolding::fold()` valida la codificación antes de transformar el texto (ver #14).

**Tests.** `MultiLanguagePhoneDirectoryParserTest::testInvalidUtf8LineIsAParseErrorNotAnEntry`,
`PotentialErrorsTest::testInvalidUtf8NameIsRejectedClearly`, `TextFoldingTest`.

### #6 — `RecordLinker::year()` con `sourceDirectoryId` nulo (no aplica)

**Análisis original.** Se leía `$m[1]` con un patrón frágil basado en `?? ''`.

**Hoy.** `$m[1]` solo se lee si hay ID y la regex coincidió; si no, se devuelve `PHP_INT_MAX` (orden de entrada):

```php
if ($dirId && preg_match('/(?<!\d)(1[89]\d{2}|20\d{2})(?!\d)/', $dirId, $m)) {
    return (int) $m[1];
}
return PHP_INT_MAX;
```

**Test.** `RecordLinkerTest::testUncatalogedDirectoriesWithNoYearFallBackToInputOrder`.

### #7 — `PersonName` con arrays sin inicializar (no aplica; el análisis era incorrecto)

**Análisis original.** Si `parse()` lanzaba una excepción, un llamado posterior a `getFirstName()` fallaría con
"Undefined array key".

**Por qué era incorrecto.** Si el constructor lanza, `new PersonName(...)` no produce ningún objeto, así que no hay
nada sobre qué llamar `getFirstName()`. De todos modos, el constructor inicializa `$firstNames` y `$lastNames` a `[]`
antes de llamar a `parse()`.

**Test.** `PotentialErrorsTest::testError13_PersonNameRejectsWhitespaceOnlyName`.

### #8 — `PhonePattern::REGEX` sin validar (corregido)

**Hoy.** Los parsers obtienen la regex con `Parser/PhonePattern::getValidatedRegex()`, que la compila una vez y lanza
`InvalidArgumentException` si es inválida, en lugar de dejar que cada `preg_match()` devuelva `false`.

**Test.** `PotentialErrorsTest::testPhoneNumberPatternIsValid`.

### #9 — Idioma no soportado cae en silencio a inglés (corregido)

**Análisis original.** `parseContent($content, 'xx')` procesaba el archivo como inglés sin avisar.

**Hoy.** `Parser/MultiLanguagePhoneDirectoryParser` lanza `InvalidLanguageException` (subclase de
`InvalidArgumentException`) con la lista de idiomas soportados, tanto al parsear como al construir patrones de calle.

**Test.** `PotentialErrorsTest::testUnsupportedLanguageIsRejected`.

### #10 — `JuridicalEntityPDODatabase` hereda #1–#3 (corregido)

**Hoy.** `PhoneDirectoryPDODatabase` y `JuridicalEntityPDODatabase` heredan de `Database/EntityPDODatabase`, que
contiene la inserción por lotes, la validación de IDs y el backfill; las correcciones de #2 y #3 aplican a ambas.

**Tests.** `JuridicalEntityDatabaseTest` cubre la clase jurídica por separado.

---

## Medios

### #11 — Comodines `%` y `_` sin escapar en búsquedas `LIKE` (corregido)

**Hoy.** Todas las búsquedas por texto pasan el valor por `SqlDialect::containsValue()`, que escapa `%`, `_` y el
propio carácter de escape (`!`):

```php
return '%' . strtr(mb_strtolower($text, 'UTF-8'), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
```

**Test.** `PhoneDirectoryDatabaseTest::testSearchTreatsWildcardCharactersLiterally`.

### #12 — `RecordLinker` agrupa entradas sin nombre de pila (corregido)

**Hoy.** `RecordLinker::link()` descarta las entradas sin apellido, sin nombre de pila o sin directorio de origen
antes de agrupar por inicial, así que `$givenName[0]` nunca se lee sobre una cadena vacía.

**Tests.** `RecordLinkerTest`.

### #13 — Soundex con apellidos muy largos (corregido; el análisis era incorrecto)

**Análisis original.** Un apellido de 10 000 caracteres produciría un `surname_soundex` enorme.

**Por qué era incorrecto.** Soundex siempre produce 4 caracteres. El único costo real era procesar la cadena
completa, y `SurnameKeys::soundex()` ahora la recorta a `MAX_SURNAME_LENGTH` (1000) antes de calcular la clave.

**Test.** `PotentialErrorsTest::testVeryLongSurnameGivesFixedSizeSoundex`.

### #14 — Codificación inválida tras `mb_strtolower` + `AccentFolding` (corregido)

**Análisis original.** Proponía comprobar `mb_check_encoding()` *después* de `mb_strtolower()`.

**Por qué esa solución no bastaba.** `mb_strtolower()` reemplaza los bytes inválidos por `?`, así que la comprobación
posterior nunca detecta nada (por ejemplo, "Muñoz" en Latin-1 pasaba como `mu?oz`).

**Hoy.** `TextFolding::fold()`, usado por la capa de base de datos y por `RecordLinker`, valida primero:

```php
if (!mb_check_encoding($text, 'UTF-8')) {
    throw new InvalidEncodingException('Invalid UTF-8 encoding in text');
}
$folded = AccentFolding::fold(mb_strtolower($text, 'UTF-8'));
```

**Test.** `TextFoldingTest::testRejectsInvalidUtf8BeforeLowercasingHidesIt`.

---

## Cómo verificarlo

```bash
cd phone-directory
./vendor/bin/phpunit tests/PhoneDirectory/PotentialErrorsTest.php
./vendor/bin/phpunit            # suite completa
```
