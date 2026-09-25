# Phone Directory Parser for Genealogical Records

Un módulo para parsear guías telefónicas históricas en varios idiomas, extraer personas naturales y jurídicas con sus datos geográficos, y almacenarlas en una base de datos relacional (SQLite, MySQL/MariaDB o PostgreSQL) con fines genealógicos.

## Características

- **Dos parsers**: `PhoneDirectoryParser` (inglés, un solo formato) y `MultiLanguagePhoneDirectoryParser` (detecta el idioma y separa personas de empresas)
- **Dos formatos de entrada**: un campo por línea (formato clásico) y una entrada por línea (formato más común en guías digitalizadas), detectados automáticamente
- **Nombres**: separa nombre, apellido(s) y tratamiento (Mr., Mrs., Dr., Vda. de...); reconoce partículas (de, van, von...) y el número de apellidos según el idioma
- **Geografía**: país (ISO 3166-1), zona, ciudad y calle en columnas separadas
- **Multi-idioma**: español, inglés, francés, portugués, alemán, italiano
- **Base de datos relacional**: SQLite, MySQL/MariaDB o PostgreSQL vía PDO, con migración automática de tablas existentes
- **Búsqueda**: por nombre, calle o teléfono, insensible a mayúsculas y acentos en las tres bases de datos
- **Búsqueda fonética**: encuentra variantes de apellidos (Smith/Smyth, Valdez/Baldez)
- **Enlace de registros**: propone qué entradas de distintas ediciones son la misma persona
- **Catálogo histórico**: 16 guías telefónicas de referencia de 10 países (1878-1975)
- **Manejo de errores**: registra errores de parsing sin detener el proceso

## Componentes

### PhoneDirectoryEntry
Modelo de una persona natural.

```php
use PhoneDirectory\Entity\PhoneDirectoryEntry;

$entry = new PhoneDirectoryEntry(
    fullName: 'SMITH, John',
    countryCode: 'US',       // obligatorio, ISO 3166-1 alpha-2
    street: '123 Main Street',
    phoneNumber: '555-123-4567',
    zone: 'New York',        // opcional
    city: 'New York',        // opcional
    language: 'en',          // opcional; afecta cómo se separan nombre/apellidos
);

$entry->getFormattedName();  // "Smith, John"
$entry->getFullName();       // "John Smith"
$entry->getRawName();        // "SMITH, John" (texto original, tal como se transcribió)
$entry->getLastNames();      // ["Smith"]
$entry->getTitle();          // null (o "Mrs.", "Vda. de", etc. si el nombre traía un tratamiento)
$entry->getCountryCode();    // "US"
```

`countryCode` es el único parámetro posicional obligatorio además de `fullName` y `street`; el resto son opcionales. El nombre se vuelve a interpretar desde `getRawName()` cada vez que se reconstruye el objeto (por ejemplo al leerlo de la base de datos), así que el texto original nunca se pierde.

### PersonName
Separa un nombre completo en nombre(s), apellido(s) y tratamiento. Lo usa internamente `PhoneDirectoryEntry`, pero también se puede usar solo:

```php
use PhoneDirectory\Entity\PersonName;

$name = new PersonName('GARCÍA LÓPEZ, José', 'es');
$name->getFirstName();     // "José"
$name->getLastNames();     // ["García", "López"]  (español y portugués usan 2 apellidos)

$name = new PersonName('SMITH, Mrs. John');
$name->getFirstName();     // "John"
$name->getTitle();         // "Mrs."

$name = new PersonName('María del Carmen García López', 'es');
$name->getFirstNames();    // ["María", "del", "Carmen"]
$name->getLastNames();     // ["García", "López"]
```

Reconoce partículas (de, del, van, von, di, le, la...), conectores según idioma (y en español, e en portugués/italiano), y tratamientos/frases de viudez (Mr., Mrs., Dr., Sr., Sra., Herr, Frau, Sig., Vda. de, Wid. of, Veuve de...). El tratamiento se separa del nombre en vez de leerse como si fuera parte de él.

### GeoLocation
Ubicación geográfica de una entrada: país, zona, ciudad y calle.

```php
use PhoneDirectory\Entity\GeoLocation;

$location = new GeoLocation('ES', 'Calle Mayor 12', 'Madrid', 'Madrid');
$location->getFullAddress();  // "Calle Mayor 12, Madrid, Madrid, ES"
```

### PhoneDirectoryParser
Parser en inglés para un solo idioma/país por instancia.

```php
use PhoneDirectory\Parser\PhoneDirectoryParser;

$parser = new PhoneDirectoryParser('US', 'us_1950_comprehensive'); // país y directorio de origen (opcionales)
$entries = $parser->parseFile('directory.txt');

// O construir el parser a partir de un directorio del catálogo (toma el país automáticamente)
$parser = PhoneDirectoryParser::forCatalogDirectory('uk_1880_london');

// O parsear contenido directamente
$entries = $parser->parseContent($textContent);
```

**Formatos de entrada reconocidos** (se detectan automáticamente, línea por línea):

- **Un campo por línea** (formato clásico):
  ```
  SMITH, John
  123 Main Street
  555-123-4567
  ```
- **Una entrada por línea** (el formato más común en guías digitalizadas), con o sin separadores explícitos:
  ```
  SMITH John 12 Oak St ........ 555-111-2222
  BROWN, Alfred, 12 Fleet Street, BUtterfield 8-4521
  ```
  Sin coma, el nombre se interpreta apellido-primero ("SMITH John" → "Smith, John"), la misma convención que usa el resto del proyecto.

**Teléfonos reconocidos:** formatos numéricos estándar (`555-123-4567`, `5551234567`, `555.123.4567`, `555 123 4567`) y el formato de central telefónica con nombre, común en Estados Unidos y el Reino Unido hasta mediados del siglo XX (`BUtterfield 8-4521`).

**Calles:** se detectan por palabra clave (Street, Avenue, Road, Drive, Lane...) o, si no hay ninguna reconocida, por un número seguido de texto. Una línea que es solo un número de teléfono nunca se toma como calle.

### MultiLanguagePhoneDirectoryParser
Detecta el idioma del contenido (o lo recibe explícito) y separa personas naturales de empresas.

```php
use PhoneDirectory\Parser\MultiLanguagePhoneDirectoryParser;

$parser = new MultiLanguagePhoneDirectoryParser('ES', 'es_1930_madrid');
$entries = $parser->parseContent($content); // idioma detectado automáticamente
// o
$entries = $parser->parseContent($content, 'es'); // idioma explícito

$parser->getDetectedLanguage(); // 'es'

foreach ($entries as $item) {
    if ($item['type'] === 'natural') {
        $item['entity']; // PhoneDirectoryEntry
    } else {
        $item['entity']; // JuridicalEntity
    }
}

// Filtros de conveniencia
$parser->getNaturalPeople();
$parser->getJuridicalEntities();
```

Idiomas soportados: español, inglés, francés, portugués, alemán, italiano. La detección de idioma usa solo palabras claramente distintivas de cada idioma (evita palabras como "plaza" o "avenue" que también son inglesas comunes); sin ninguna palabra reconocida, o en caso de empate, el idioma por defecto es inglés. En alemán reconoce compuestos donde el tipo de calle va pegado al nombre (`Hauptstraße`).

### JuridicalEntity / JuridicalEntityPDODatabase
Modelo y base de datos para empresas, con la misma estructura de país/procedencia que `PhoneDirectoryEntry`.

```php
use PhoneDirectory\Entity\JuridicalEntity;

$entity = new JuridicalEntity(
    businessName: 'Farmacia Central',
    street: 'Calle Luna 3',
    businessType: 'farmacia',
    phoneNumber: '555-444-5555',
    countryCode: 'ES',
);
```

### PhoneDirectoryPDODatabase
Implementación con PDO, soporta SQLite, MySQL/MariaDB y PostgreSQL a través de `SqlDialect`.

```php
use PhoneDirectory\PhoneDirectoryPDODatabase;

// SQLite (en memoria o archivo)
$db = new PhoneDirectoryPDODatabase('sqlite::memory:');
$db = new PhoneDirectoryPDODatabase('sqlite:/path/to/database.db');

// MySQL / MariaDB
$db = new PhoneDirectoryPDODatabase('mysql:host=localhost;dbname=genealogy;charset=utf8mb4', 'usuario', 'contraseña');

// PostgreSQL
$db = new PhoneDirectoryPDODatabase('pgsql:host=localhost;dbname=genealogy', 'usuario', 'contraseña');

$db->connect();
$db->createTable(); // crea la tabla si no existe, y agrega columnas nuevas a una tabla ya existente
```

`createTable()` es segura de llamar repetidamente: en una base ya creada con una versión anterior de este módulo, agrega las columnas que falten (país, zona, ciudad, claves de búsqueda fonética, columnas de búsqueda sin acentos...) y recalcula esos valores para las filas existentes.

```php
$db->findByName('garcia');        // encuentra "García", insensible a mayúsculas y acentos
$db->findByStreet('Oak Avenue');
$db->findByPhone('555-123-4567');
$db->findBySurnameSound('Valdez', 'es'); // encuentra "Baldez" también (variante fonética)
$db->findBySourceDirectory('es_1930_madrid'); // todas las entradas de un directorio, en orden
$db->search(['name' => 'Smith', 'street' => 'Oak']);
```

Las búsquedas por nombre y calle son insensibles a mayúsculas y a acentos, de manera consistente en las tres bases de datos soportadas (antes esto solo era cierto en MySQL, por su collation por defecto).

### PersonName / SurnameKeys — búsqueda fonética
Cada entrada guarda, junto con su apellido, dos claves fonéticas calculadas al insertarla: un código Soundex (sirve para cualquier idioma, encuentra variantes como Smith/Smyth) y una clave por idioma, basada en las reglas fonéticas específicas de cada uno, que encuentra confusiones que Soundex no ve (Valdez/Baldez en español). `findBySurnameSound()` busca por cualquiera de las dos.

### RecordLinker
Propone qué entradas de distintas ediciones de un directorio son probablemente la misma persona.

```php
use PhoneDirectory\RecordLinker;

$entries = array_merge(
    $db->findBySourceDirectory('es_1930_madrid'),
    $db->findBySourceDirectory('es_1975_national')
);

$links = (new RecordLinker())->link($entries);

foreach ($links as $link) {
    $link->earlier;   // PhoneDirectoryEntry de la edición más antigua
    $link->later;     // PhoneDirectoryEntry de la edición más reciente
    $link->score;      // 0.0 a 1.0
    $link->evidence;   // ["same given name", "same surname", "same address", ...]
}
```

Los candidatos comparten apellido (por sonido), país, y vienen de directorios distintos; un nombre de pila distinto (no solo una inicial) descarta el enlace. Una dirección distinta baja la puntuación pero no lo impide, ya que las familias se mudaban entre ediciones. El orden `earlier`/`later` usa el año del catálogo cuando el directorio está en él, o el año que aparezca en el propio id del directorio (`"custom_1990_x"`) cuando no lo está.

### PhoneDirectoryCatalog
Catálogo de referencia con 16 guías telefónicas históricas de 10 países (1878-1975).

```php
use PhoneDirectory\PhoneDirectoryCatalog;

$catalog = new PhoneDirectoryCatalog();
$catalog->get('es_1930_madrid');       // datos de un directorio concreto
$catalog->getByCountry('ES');
$catalog->getByYearRange(1900, 1950);
$catalog->getStatistics();
```

### PhoneDirectoryManagerV2
Coordinador de alto nivel que integra `MultiLanguagePhoneDirectoryParser` con dos bases de datos: una para personas naturales y otra para empresas.

`PhoneDirectoryManager` (v1, solo inglés y solo personas naturales) sigue existiendo por compatibilidad, pero está marcado `@deprecated`.

## Uso Básico

### 1. Instalación
```bash
composer install   # enlaza también ../defamatory-content-review como dependencia
```

### 2. Crear base de datos e insertar datos
```php
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\Manager\PhoneDirectoryManagerV2;
use PhoneDirectory\PhoneDirectoryPDODatabase;

$dsn = 'sqlite:genealogy.db';
$manager = new PhoneDirectoryManagerV2(
    naturalDatabase: new PhoneDirectoryPDODatabase($dsn),
    juridicalDatabase: new JuridicalEntityPDODatabase($dsn)
);

$result = $manager->processFile('sample_directory.txt');   // idioma detectado automáticamente

echo "Insertadas: {$result['totalInserted']} entradas\n";
echo "Errores: {$result['errorCount']}\n";
```

### 3. Buscar información
```php
$people = $manager->findNaturalPeopleByName('ANDERSON');
$people = $manager->findNaturalPeopleBySurnameSound('Anderson'); // también encuentra variantes fonéticas
$byStreet = $manager->findByStreet('Main Street');   // ['natural' => [...], 'juridical' => [...]]
$byPhone = $manager->findByPhone('555-123-4567');    // ['natural' => ?entrada, 'juridical' => ?empresa]
$shops = $manager->findJuridicalEntitiesByType('farmacia');

$results = $manager->searchNaturalPeople([
    'name' => 'SMITH',
    'street' => 'Avenue'
]);
```

### 4. Gestionar datos
```php
use PhoneDirectory\Entity\PhoneDirectoryEntry;

$entry = new PhoneDirectoryEntry(
    fullName: 'WILSON, Charles',
    countryCode: 'US',
    street: '913 Oak Street',
    phoneNumber: '555-456-7890'
);
$id = $manager->addNaturalPerson($entry);

$entry->setId($id);
$manager->updateNaturalPerson($entry);

$manager->deleteNaturalPerson($id);

echo "Total: {$manager->getTotalCount()}";
print_r($manager->getStatistics());
```

Hay ejemplos completos y ejecutables en `examples/` (los ejecuta `ExamplesRunTest`).

## Herramienta de línea de comandos

`bin/phonedir` cubre los usos más comunes (importar, buscar, ver estadísticas, enlazar registros entre ediciones, consultar el catálogo) sin escribir código PHP. Detecta personas y empresas automáticamente y funciona con SQLite, MySQL/MariaDB o PostgreSQL.

```bash
php bin/phonedir import directorio.txt --catalog=es_1930_madrid --dsn=sqlite:madrid.sqlite
php bin/phonedir search --name=garcia --dsn=sqlite:madrid.sqlite
php bin/phonedir search --surname-sound=valdez --language=es --dsn=sqlite:madrid.sqlite
php bin/phonedir stats --dsn=sqlite:madrid.sqlite
php bin/phonedir link --sources=es_1930_madrid,es_1975_national --dsn=sqlite:madrid.sqlite
php bin/phonedir catalog list --country=ES
php bin/phonedir help
```

`--catalog=<id>` toma el país y ayuda a fijar el idioma de un directorio ya listado en el catálogo; para un archivo propio, se usan `--country` y `--source` en su lugar. Ver `php bin/phonedir help` para todas las opciones.

## Formato de Archivo TXT

El parser acepta dos formatos, línea por línea, sin necesidad de indicarlo:

**Un campo por línea:**
```
NOMBRE, Apellido
Dirección (calle)
Número de teléfono (opcional)

NOMBRE2, Apellido2
Dirección 2
Número de teléfono (opcional)

------- (separador opcional: líneas de -, =, _ o *)

NOMBRE3, Apellido3
Dirección 3
```

**Una entrada por línea** (con línea de puntos, comas, tabulaciones o espacios como separador):
```
SMITH John 12 Oak St ........ 555-111-2222
JONES, Mary, 40 Elm Road, 555-333-4444
```

**Reglas:**
- Nombre y calle son obligatorios; el teléfono es opcional
- Líneas en blanco, o de puntuación repetida, separan entradas en el formato de varias líneas
- Los números telefónicos y las calles se detectan automáticamente

## Estructura de Base de Datos

La tabla `phone_directory` contiene:

```
id                    INTEGER PRIMARY KEY
full_name             TEXT NOT NULL       -- nombre normalizado (para mostrar)
raw_name              TEXT                -- texto original, tal como se transcribió
language              TEXT                -- idioma usado para separar nombre/apellidos
country_code          TEXT NOT NULL       -- ISO 3166-1 alpha-2
zone                  TEXT
city                  TEXT
street                TEXT NOT NULL
phone_number          TEXT
source_directory_id   TEXT                -- id del directorio de origen (catálogo o propio)
source_line           INTEGER             -- línea del archivo donde empieza la entrada
surname_soundex       TEXT                -- clave fonética (búsqueda de variantes)
surname_phonetic      TEXT                -- clave fonética por idioma
full_name_folded      TEXT                -- copia en minúsculas y sin acentos, para buscar
street_folded         TEXT                -- copia en minúsculas y sin acentos, para buscar
record_date           DATETIME
created_at            DATETIME
updated_at            DATETIME
```

`juridical_entities` tiene la misma estructura de procedencia geográfica (`country_code`, `source_directory_id`, `source_line`) y de búsqueda sin acentos (`business_name_folded`, `street_folded`), además de `business_name`, `legal_name`, `business_type`.

Las columnas `raw_name`/`surname_soundex`/`full_name_folded` (y sus equivalentes) se agregan automáticamente, junto con sus índices, la primera vez que `createTable()` se ejecuta sobre una tabla creada con una versión anterior de este módulo.

## Pruebas

```bash
./vendor/bin/phpunit
```

Las pruebas cruzadas contra MySQL/MariaDB y PostgreSQL (`CrossDatabaseTest`) se saltan si no hay servidor configurado; para incluirlas:

```bash
PHONEDIR_PGSQL_DSN="pgsql:host=127.0.0.1;dbname=phonedir_test;user=postgres" \
PHONEDIR_MYSQL_DSN="mysql:host=127.0.0.1;dbname=phonedir_test;charset=utf8mb4" \
PHONEDIR_MYSQL_USER=usuario PHONEDIR_MYSQL_PASSWORD=contraseña \
./vendor/bin/phpunit
```

Los ports a Python y Java (`ports/`) tienen sus propios tests; ver `docs/PROJECT_COMPLETION_REPORT.md`.

## API Completa

### PhoneDirectoryParser / MultiLanguagePhoneDirectoryParser

- `__construct(string $countryCode = 'US', ?string $sourceDirectoryId = null)`
- `static forCatalogDirectory(string $directoryId): self` — toma el país del catálogo
- `parseFile(string $filePath): array`
- `parseContent(string $content): array` (`MultiLanguagePhoneDirectoryParser` recibe además `?string $language`)
- `getEntries(): array`, `getErrors(): array`
- `getEntriesCount(): int`, `getErrorsCount(): int` (solo `PhoneDirectoryParser`)
- `getDetectedLanguage(): string`, `getNaturalPeople(): array`, `getJuridicalEntities(): array` (solo `MultiLanguagePhoneDirectoryParser`)
- `reset(): void`

### PhoneDirectoryPDODatabase

- `__construct(string $dsn = 'sqlite::memory:', ?string $username = null, ?string $password = null)`
- `connect(): void`, `disconnect(): void`, `isConnected(): bool`
- `createTable(): void` — crea o migra la tabla, y recalcula columnas derivadas para filas existentes
- `insert(PhoneDirectoryEntry): int`, `insertBatch(array): int`
- `findById(int): ?PhoneDirectoryEntry`
- `findByName(string): array`, `findByStreet(string): array`, `findByPhone(string): ?PhoneDirectoryEntry`
- `findBySurnameSound(string $surname, ?string $language = null): array`
- `findBySourceDirectory(string): array`
- `getAll(): array`
- `update(PhoneDirectoryEntry): bool`, `delete(int): bool`
- `count(): int`
- `search(array): array`
- `clear(): bool`

### PhoneDirectoryManager (obsoleto, usar `PhoneDirectoryManagerV2`)

Interfaz de alto nivel que combina el parser en inglés y la base de datos de personas naturales.

- `processFile(string, bool): array`
- `addEntry(PhoneDirectoryEntry): int`
- `getEntry(int): ?PhoneDirectoryEntry`
- `findByName(string): array`, `findByStreet(string): array`, `findByPhone(string): ?PhoneDirectoryEntry`
- `findBySurnameSound(string, ?string): array`
- `getAllEntries(): array`
- `search(array): array`
- `updateEntry(PhoneDirectoryEntry): bool`, `deleteEntry(int): bool`
- `getTotalCount(): int`

## Consideraciones de Rendimiento

`php bin/benchmark.php [entradas]` mide parseo, inserción y búsquedas; los resultados y proyecciones están en `docs/ESTIMATES_AND_SCALABILITY.md`.

Para directorios telefónicos grandes:
- `parseFile()` lee el archivo línea a línea, pero devuelve todas las entradas juntas (~1,8 KB de memoria por entrada): partir los archivos de millones de entradas antes de importarlos
- Usar base de datos en archivo (o MySQL/PostgreSQL) en lugar de SQLite en memoria
- Usar `insertBatch()` para inserciones masivas (una transacción y una sentencia preparada para todo el lote)
- `RecordLinker` agrupa internamente por apellido, país e inicial del nombre antes de comparar pares, para que enlazar apellidos muy comunes siga siendo rápido
- Considerar particionamiento de datos por período o región

## Limitaciones conocidas

- Solo se extrae el número de calle de la dirección completa; no hay descomposición de piso/apartamento
- No se valida que un número de teléfono sea real, solo que tenga un formato reconocido (por fines genealógicos)
- El formato de una entrada por línea asume, sin coma, que el apellido va primero (la convención de este proyecto); un formato de nombre-primero sin coma no se reconoce
- Un tratamiento (Mrs., Dr...) insertado en medio de un nombre sin coma, en el formato de una entrada por línea, no se separa correctamente
- Con "viuda de" o "widow of", solo se guarda el tratamiento; el nombre del marido que sigue no se vuelve a separar en nombre/apellido
- La detección automática de idioma es una heurística por palabras clave; con muy poco texto, o en un idioma no soportado, puede fallar (siempre se puede indicar el idioma explícitamente)

## Licencia

MIT
