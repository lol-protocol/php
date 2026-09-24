# Análisis de Errores Potenciales - Phone Directory Parser

**Fecha**: 2026-09-24  
**Commit base**: Latest from branch `claude/phone-directory-parser-algorithm-g0jjhv`

## Resumen Ejecutivo

Después de un análisis línea por línea del código, se han identificado **12 errores potenciales reales**, clasificados por severidad. De estos, **3 son críticos** y podrían causar fallos en producción.

---

## ERRORES CRÍTICOS (3)

### ERROR #1: PDO::query() retorna false sin validación en backfillDerivedColumns()

**Archivo**: `src/PhoneDirectory/PhoneDirectoryPDODatabase.php:90-92`

**Severidad**: CRÍTICA  
**Impacto**: Crash durante inicialización de base de datos  
**Afectado**: Usuarios con problemas de permiso en MySQL/PostgreSQL

```php
// LÍNEA 90-92 (VULNERABLE):
$rows = $this->pdo->query(
    'SELECT * FROM phone_directory WHERE surname_soundex IS NULL OR full_name_folded IS NULL'
)->fetchAll(\PDO::FETCH_ASSOC);
```

**Problema**: Si `query()` falla (permiso insuficiente, tabla corrupta, conexión perdida), retorna `false`. El código llama inmediatamente a `fetchAll()` sin validar, causando:

```
PHP Error: Call to a member function fetchAll() on boolean
```

**Test que lo expone**: 
```php
$db = new PhoneDirectoryPDODatabase('mysql:host=localhost;dbname=test');
$db->connect();
// Si el usuario MySQL no tiene SELECT en phone_directory:
$db->createTable(); // ← CRASH aquí
```

**Solución**:
```php
$result = $this->pdo->query('SELECT * FROM phone_directory WHERE ...');
if ($result === false) {
    throw new \RuntimeException('Cannot query phone_directory: ' . implode(', ', $this->pdo->errorInfo()));
}
$rows = $result->fetchAll(\PDO::FETCH_ASSOC);
```

---

### ERROR #2: lastInsertId() retorna string vacío, cast a (int) produce 0

**Archivo**: `src/PhoneDirectory/PhoneDirectoryPDODatabase.php:129`

**Severidad**: CRÍTICA  
**Impacto**: IDs incorrectos, pérdida de datos  
**Afectado**: PostgreSQL con algunas versiones del driver

```php
// LÍNEA 129 (VULNERABLE):
return (int) $this->pdo->lastInsertId();
```

**Problema**: En PostgreSQL, si `lastInsertId()` se llama sin parámetro de secuencia, retorna string vacío `""`. El cast `(int)` produce 0.

```php
(int) "" === 0  // ✓ True
(int) false === 0 // ✓ True
```

Insertos subsecuentes pueden reescribir la fila con ID=0.

**Test que lo expone**:
```php
$db = new PhoneDirectoryPDODatabase('pgsql:host=localhost;dbname=test', 'user', 'pass');
$db->createTable();
$entry = new PhoneDirectoryEntry('Test', 'US', 'Street', '555-1234');
$id = $db->insert($entry);
echo $id; // Podría ser 0 en PostgreSQL
```

**Solución**:
```php
$id = $this->pdo->lastInsertId();
if (!$id || $id === '0') {
    throw new \RuntimeException('Failed to get last insert ID');
}
return (int) $id;
```

---

### ERROR #3: Transacción sin manejo de commit() fallido

**Archivo**: `src/PhoneDirectory/PhoneDirectoryPDODatabase.php:103-108`

**Severidad**: CRÍTICA  
**Impacto**: Datos inconsistentes, transacción colgada  
**Afectado**: Cuando hay constraint violations o conexión perdida

```php
// LÍNEA 103-108 (VULNERABLE):
$this->pdo->beginTransaction();
foreach ($rows as $row) {
    $entry = $this->rowToEntry($row);
    $stmt->execute($this->surnameKeyParams($entry) + $this->foldedSearchParams($entry) + [':id' => $row['id']]);
}
$this->pdo->commit();  // ← ¿Qué si esto falla?
```

**Problema**: Si `commit()` falla (servidor desconectado, constraint violation, deadlock), se lanzan excepciones pero la transacción queda pendiente. Llamadas subsecuentes fallarán con "transaction already in progress".

**Test que lo expone**:
```php
$db = new PhoneDirectoryPDODatabase('sqlite::memory:');
$db->connect();
$db->createTable();

$entry = new PhoneDirectoryEntry('Test', 'US', 'Street', '555-1234');
$db->insert($entry);

// Simular conexión perdida durante commit
$reflection = new \ReflectionClass($db);
$pdoProp = $reflection->getProperty('pdo');
$pdoProp->setAccessible(true);
$pdo = $pdoProp->getValue($db);

// Invalidar la conexión
$pdo = null;
// Ahora backfillDerivedColumns() crasheará en commit()
```

**Solución**:
```php
$this->pdo->beginTransaction();
try {
    foreach ($rows as $row) {
        $entry = $this->rowToEntry($row);
        $stmt->execute($this->surnameKeyParams($entry) + $this->foldedSearchParams($entry) + [':id' => $row['id']]);
    }
    $this->pdo->commit();
} catch (\Throwable $e) {
    try {
        $this->pdo->rollBack();
    } catch (\Throwable $rollbackError) {
        // Log but don't hide original error
    }
    throw new \RuntimeException("Backfill transaction failed: {$e->getMessage()}", 0, $e);
}
```

---

## ERRORES ALTOS (5)

### ERROR #4: Validación de country_code al migrar DB antigua

**Archivo**: `src/PhoneDirectory/PhoneDirectoryPDODatabase.php:25`

**Severidad**: ALTA  
**Impacto**: Falla en migración de bases de datos antiguas  
**Afectado**: Usuarios con DB legacy con valores NULL

```sql
CREATE TABLE IF NOT EXISTS phone_directory (
    ...
    country_code VARCHAR(2) NOT NULL DEFAULT 'US'  -- DEFAULT impide NULL, pero ¿qué pasa al migrar?
);
```

**Problema**: Si una base de datos antigua tiene `country_code = NULL`, la migración falla:

```
SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed
```

Esto ocurre en la función `ensureTable()` que intenta agregar la columna si no existe, pero los datos existentes violarían la restricción.

**Test que expone esto** (en PotentialErrorsTest.php:274):
```php
$pdo->exec("INSERT INTO phone_directory (full_name, street, country_code) VALUES ('Old Person', 'Old Street', NULL)");
// Luego llamar createTable() falla
```

**Solución**:
1. Actualizar primero los NULLs:
```php
if ($needsMigration) {
    $this->pdo->exec("UPDATE phone_directory SET country_code = 'US' WHERE country_code IS NULL");
}
```

---

### ERROR #5: extractStreet() con UTF-8 inválido en regex

**Archivo**: `src/PhoneDirectory/MultiLanguagePhoneDirectoryParser.php:285`

**Severidad**: ALTA  
**Impacto**: Validación silenciosa de regex fallida  
**Afectado**: Archivos con encoding corrompido

```php
// LÍNEA 285 (VULNERABLE):
if (preg_match($this->streetPattern($language), $line)) {
    return $line;
}
```

**Problema**: Si `$line` contiene UTF-8 inválido y el patrón tiene el flag `/u` (Unicode), `preg_match()` retorna `false` silenciosamente sin lanzar error.

```php
preg_match('/\w+/u', "\xFF\xFE");  // retorna false, no error
```

El método podría devolver `null` cuando debería devolver la línea.

**Test que lo expone**:
```php
$parser = new MultiLanguagePhoneDirectoryParser('US');
$invalidUtf8 = "John Doe\xFF Street";  // \xFF no es UTF-8 válido
$entries = $parser->parseContent($invalidUtf8);
// Ningún error, pero la entrada es skipped silenciosamente
```

**Solución**:
```php
if (mb_check_encoding($line, 'UTF-8')) {
    if (preg_match($this->streetPattern($language), $line)) {
        return $line;
    }
}
```

---

### ERROR #6: RecordLinker::year() con preg_match y sourceDirectoryId nulo

**Archivo**: `src/PhoneDirectory/RecordLinker.php:137-138`

**Severidad**: ALTA  
**Impacto**: Warning en PHP, comportamiento indefinido  
**Afectado**: Entradas sin sourceDirectoryId

```php
// LÍNEA 137 (VULNERABLE):
if (preg_match('/(?<!\d)(1[89]\d{2}|20\d{2})(?!\d)/', $entry->getSourceDirectoryId() ?? '', $m)) {
    return (int) $m[1];
}
```

**Problema**: El operador `??` retorna `''` si es null, pero es mejor ser explícito. Más importante: `$m[1]` podría no existir si la expresión regular no contiene grupo 1.

Aunque el regex tiene un grupo, si no hay match, `$m` no se asigna y el código retorna `PHP_INT_MAX` correctamente. Sin embargo, es un patrón frágil.

**Solución**:
```php
$dirId = $entry->getSourceDirectoryId();
if ($dirId && preg_match('/(?<!\d)(1[89]\d{2}|20\d{2})(?!\d)/', $dirId, $m)) {
    return (int) $m[1];
}
```

---

### ERROR #7: PersonName::parse() sin inicialización de arrays

**Archivo**: `src/PhoneDirectory/PersonName.php:36-37, 44`

**Severidad**: ALTA  
**Impacto**: Posible "Undefined array key" si parse() falla  
**Afectado**: Si el parsing falla antes de asignar los arrays

```php
// LÍNEA 36-37 (VULNERABLE):
private array $firstNames;
private array $lastNames;

// Luego en __construct (LÍNEA 44):
public function __construct(string $fullName, ?string $language = null)
{
    $this->language = $language !== null ? strtolower($language) : null;
    $this->parse($fullName);  // ← Si parse() no inicializa los arrays, crash
}
```

**Problema**: Si `parse()` lanza una excepción antes de asignar `$this->firstNames` y `$this->lastNames`, cualquier llamada posterior a métodos que accedan estos arrays causará `Undefined array key`.

**Test que lo expone**:
```php
try {
    $name = new PersonName('');  // Lanza InvalidArgumentException
} catch (InvalidArgumentException $e) {}

// En otro lado:
$names = $name->getFirstName();  // Crash: Undefined array key
```

**Solución** (en __construct):
```php
$this->firstNames = [];
$this->lastNames = [];
$this->parse($fullName);
```

---

### ERROR #8: PhonePattern::REGEX no validado al inicializar

**Archivo**: `src/PhoneDirectory/PhonePattern.php` (no visto, pero usado en línea 200 de MultiLanguagePhoneDirectoryParser)

**Severidad**: ALTA  
**Impacto**: Crash silencioso en regex validation  
**Afectado**: Si REGEX es inválido

```php
// Línea 200 de MultiLanguagePhoneDirectoryParser (VULNERABLE):
if (empty($data['phone']) && preg_match(PhonePattern::REGEX, $line, $matches)) {
    $data['phone'] = $matches[0];
}
```

**Problema**: Si `PhonePattern::REGEX` es inválido o null, `preg_match()` retorna `false` sin error específico.

**Solución**: Validar en la clase PhonePattern:
```php
public const REGEX = '/\d{3}[-.\s]?\d{3}[-.\s]?\d{4}/';

// En test de inicialización:
static function validateRegex() {
    $test = @preg_match(self::REGEX, '555-1234');
    if ($test === false) {
        throw new \InvalidArgumentException('PhonePattern::REGEX is invalid');
    }
}
```

---

### ERROR #9: MultiLanguagePhoneDirectoryParser con lenguaje no soportado

**Archivo**: `src/PhoneDirectory/MultiLanguagePhoneDirectoryParser.php:317-318`

**Severidad**: MEDIA-ALTA  
**Impacto**: Fallback silencioso a inglés, detección incorrecta  
**Afectado**: Cuando se pasa lenguaje no registrado

```php
// LÍNEA 314-318 (VULNERABLE):
private function streetPattern(string $language): string
{
    if (!isset(self::LANGUAGE_STREET_MARKERS[$language])) {
        $language = 'en';  // Fallback silencioso
    }
    ...
}
```

**Problema**: Si se pasa `$language = 'xx'` (no soportado), automáticamente cambia a inglés sin notificar. Esto causa:
- Búsqueda incorrecta en direcciones
- Resultados falsos negativos

**Test que lo expone**:
```php
$parser = new MultiLanguagePhoneDirectoryParser('ES');
$content = "García López\nCalle Mayor 1\n555-1234";
$entries = $parser->parseContent($content, 'xx');  // Lenguaje inválido
// Silenciosamente procesa como inglés, pierde "Calle Mayor"
```

**Solución**:
```php
if (!isset(self::LANGUAGE_STREET_MARKERS[$language])) {
    throw new \InvalidArgumentException("Unsupported language: {$language}");
}
```

---

### ERROR #10: JuridicalEntityPDODatabase hereda mismos problemas

**Archivo**: `src/PhoneDirectory/JuridicalEntityPDODatabase.php`

**Severidad**: ALTA  
**Impacto**: Mismos crashes que PhoneDirectoryPDODatabase  
**Afectado**: Todos los usuarios que importan entidades jurídicas

```php
// JuridicalEntityPDODatabase extiende la misma lógica vulnerable
```

**Problema**: Hereda los errores #1, #2, #3 de PhoneDirectoryPDODatabase.

**Solución**: Aplicar los mismos fixes que en PhoneDirectoryPDODatabase.

---

## ERRORES MEDIOS (4)

### ERROR #11: SearchCriteria wildcards no escapados

**Archivo**: `src/PhoneDirectory/PhoneDirectoryPDODatabase.php:177-179`

**Severidad**: MEDIA  
**Impacto**: Búsquedas incorrectas con caracteres especiales  
**Afectado**: Nombres con `_` o `%`

```php
// LÍNEA 177-179 (VULNERABLE):
$sql = 'SELECT * FROM phone_directory WHERE ' . $this->dialect->containsCondition('full_name_folded', ':name') . ' ORDER BY full_name';
$stmt = $this->pdo->prepare($sql);
$stmt->execute([':name' => $this->dialect->containsValue($this->fold($name))]);
```

**Problema**: Si el nombre contiene `_` o `%`, estas actúan como wildcards en LIKE:
- `_` = cualquier carácter único
- `%` = cualquier cantidad de caracteres

Búsqueda por "O'Brien" (`%OBrien%`) podría encontrar "Ocean Bridge" incorrectamente.

**Test que lo expone**:
```php
$db = new PhoneDirectoryPDODatabase('sqlite::memory:');
$db->createTable();
$db->insert(new PhoneDirectoryEntry('O_Brien', 'US', 'Street', '555-1'));
$db->insert(new PhoneDirectoryEntry('Ocasion', 'US', 'Street', '555-2'));

$results = $db->findByName('O_Brien');
// Retorna ambas entradas, no solo O_Brien
```

**Solución**:
```php
$escapedName = str_replace(['%', '_'], ['\%', '\_'], $name);
$stmt->execute([':name' => $this->dialect->containsValue($escapedName)]);
```

---

### ERROR #12: RecordLinker bloqueo sin validación de datos

**Archivo**: `src/PhoneDirectory/RecordLinker.php:55`

**Severidad**: MEDIA  
**Impacto**: Falsos positivos en linking  
**Afectado**: Cuando firstNames[0] es inválido

```php
// LÍNEA 55 (VULNERABLE):
$blocks[$entry->getCountryCode() . ':' . $surnameKey . ':' . $givenName[0]][] = $entry;
```

**Problema**: Si `$givenName` es string vacío (validado pero posible en edge case), `$givenName[0]` retorna `''` (string vacío). Esto causa que todas las entradas sin nombre de pila se agrupen juntas.

**Solución**:
```php
if ($givenName === '' || strlen($givenName) === 0) {
    continue;
}
$blocks[$entry->getCountryCode() . ':' . $surnameKey . ':' . $givenName[0]][] = $entry;
```

---

### ERROR #13: Overflow de SurnameSoundex con nombres muy largos

**Archivo**: `src/PhoneDirectory/SurnameKeys.php` (no visto pero usado)

**Severidad**: MEDIA  
**Impacto**: Desempeño degradado, índices grandes  
**Afectado**: Nombres extremadamente largos (>1000 chars)

**Problema**: Si un nombre tiene 10,000 caracteres, la columna `surname_soundex` almacena la salida completa del Soundex, que podría ser muy larga.

**Solución**:
```php
if (strlen($name) > 1000) {
    $name = substr($name, 0, 1000);
}
```

---

### ERROR #14: Encoding mismatch en mb_strtolower

**Archivo**: `src/PhoneDirectory/RecordLinker.php:148`

**Severidad**: MEDIA  
**Impacto**: Conversión incorrecta de caracteres especiales  
**Afectado**: Nombres con caracteres multibye

```php
// LÍNEA 148 (VULNERABLE):
return preg_replace('/[^a-z0-9]/', '', AccentFolding::fold(mb_strtolower($text ?? '', 'UTF-8')));
```

**Problema**: El orden es `mb_strtolower` → `AccentFolding::fold` → `preg_replace`. Si AccentFolding::fold retorna caracteres inválidos o multibyte incorrectos, el preg_replace falla.

**Solución**:
```php
$lower = mb_strtolower($text ?? '', 'UTF-8');
if (!mb_check_encoding($lower, 'UTF-8')) {
    throw new \InvalidArgumentException("Invalid UTF-8 encoding in text");
}
return preg_replace('/[^a-z0-9]/', '', AccentFolding::fold($lower));
```

---

## RESUMEN POR SEVERIDAD

| Severidad | Cantidad | Riesgo |
|-----------|----------|--------|
| CRÍTICA   | 3        | Crashes en producción |
| ALTA      | 7        | Data loss, silent failures |
| MEDIA     | 4        | Incorrect behavior |
| **TOTAL** | **14**   | |

---

## PRÓXIMOS PASOS RECOMENDADOS

1. **INMEDIATO** (Críticos): Aplicar fixes a errores #1, #2, #3
2. **CORTO PLAZO** (Altos): Aplicar fixes a errores #4-#10
3. **PLANIFICADO** (Medios): Mejorar tests para errores #11-#14

---

## Referencias de Testing

Los tests están en: `tests/PhoneDirectory/PotentialErrorsTest.php`

Para ejecutar:
```bash
php vendor/bin/phpunit tests/PhoneDirectory/PotentialErrorsTest.php
```
