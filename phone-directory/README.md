# Phone Directory Parser for Genealogical Records

Un módulo para parsear guías telefónicas históricas en varios idiomas, extraer personas naturales y jurídicas con sus datos geográficos, y almacenarlas en una base de datos relacional (SQLite, MySQL/MariaDB o PostgreSQL) con fines genealógicos.

> **Privacidad.** El catálogo llega hasta 1975: muchas de las personas de esas
> guías viven, con su nombre, dirección y teléfono. **Por omisión, la base y el
> enlazador solo devuelven entradas de guías de hace más de 92 años** (ver
> [Privacidad: personas que podrían estar vivas](#privacidad-personas-que-podrían-estar-vivas));
> para leer todo lo que hay guardado hay que pedirlo (`Privacy::forOwner()`).
> Una persona de la que se sabe que murió se puede marcar entrada por entrada
> (`markDeceased()`, con su comprobante) y entonces sí se muestra.
> El criterio general está en [`../PRIVACIDAD-Y-HONRA.md`](../PRIVACIDAD-Y-HONRA.md).

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

El cuarto parámetro del constructor es quién pregunta (`Privacy`; ver más abajo). Sin él, la base se comporta como para un visitante: **no devuelve** las entradas que podrían ser de personas vivas. Para leer todo lo guardado: `new PhoneDirectoryPDODatabase($dsn, privacy: Privacy::forOwner())`.

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

`search()` acepta `name`, `street` y `phone` en la base de personas, y `businessName`, `street`, `phone` y `businessType` en la de empresas. Una clave que no conoce lanza `InvalidArgumentException` (con la lista de las válidas) en vez de ignorarla y devolver toda la tabla; un valor vacío (`''` o `null`) cuenta como «sin criterio», y `'0'` como un valor más.

### Privacidad: personas que podrían estar vivas

Una entrada de guía no trae fecha de nacimiento, pero sí la fecha de la guía, y quien figura en una guía de un año *Y* era adulto entonces: nació, como mucho, en *Y* − 18. Se presume viva a una persona durante 110 años desde su nacimiento (la misma línea que `url-routing/`), así que una entrada solo se puede mostrar cuando *Y* − 18 + 110 ya pasó. En 2026 eso es todo hasta la guía de 1933: **las guías de 1934 a 1975, la mitad reciente del catálogo, quedan ocultas.**

```php
use PhoneDirectory\Privacy;

$visitante = Privacy::forVisitors();   // lo que se puede publicar (por omisión en todas partes)
$propietario = Privacy::forOwner();    // todo lo guardado: para quien tiene la base

$visitante->latestPublishableYear();   // 1933 (en 2026)
$visitante->mayBeAlive($entry);        // true para una entrada de es_1975_national
$visitante->filter($entries);          // solo las que se pueden mostrar, en su orden
```

Dónde se aplica:

- **`PhoneDirectoryPDODatabase`**: `findById`, `findByName`, `findByStreet`, `findByPhone`, `findBySurnameSound`, `findBySourceDirectory`, `search` y `getAll`. Un número de teléfono reusado en una edición posterior sigue encontrando la entrada antigua. `count()` cuenta todo lo guardado, y `update()`, `delete()` y `clear()` no cambian: ocultar es sobre lo que se muestra, no sobre lo que se guarda.
- **`PhoneDirectoryManagerV2`**: contesta con lo que su base puede mostrar.
- **`RecordLinker`**: `new RecordLinker(privacy: …)`. Descarta antes de enlazar toda entrada que pueda ser de una persona viva, porque unir a una persona de dos ediciones arma un perfil (dónde vivía, y cuándo) que ninguna guía tenía por sí sola.
- **`bin/phonedir`** usa la vista del propietario: quien corre el comando tiene la base. `--publico` muestra lo que vería un visitante (y lo dice al final), y `--hoy=AAAA-MM-DD` fija la fecha de referencia.

#### Una persona que murió

Si se sabe que la persona de una entrada murió, ya no es alguien que «podría estar viva» y un visitante puede ver su entrada aunque la guía sea reciente. Se marca entrada por entrada, y **una marca equivocada publica la dirección y el teléfono de alguien que vive**, así que la base pide lo que otra persona necesita para comprobarla:

```php
$propietario = new PhoneDirectoryPDODatabase($dsn, privacy: Privacy::forOwner());

$propietario->markDeceased($id, 1988, 'Esquela, La Vanguardia, 12/03/1988');   // año y comprobante
$propietario->getDeceasedEvidence($id);                                         // solo el propietario lo lee
$propietario->clearDeceased($id);                                               // la entrada vuelve a seguir el año de su guía
```

- **Solo con la vista del propietario.** Una base abierta para visitantes (también la que se abre sin decir nada) lanza `LogicException` al marcar, quitar la marca o leer el comprobante.
- **El comprobante es obligatorio** (hasta 1000 caracteres: dónde consta, no el documento) y **el año tiene que ser verosímil**: entre 1800 y el año en curso, y no más de un año antes de la guía de la entrada (una defunción anterior sería otra persona con el mismo nombre). Si no, `InvalidArgumentException` y no queda ninguna marca.
- **El comprobante no forma parte de la entrada**: puede nombrar a parientes. El visitante ve solo el año (`getDeceasedYear()`, y `deceasedYear` en `toArray()`).
- **`update()` no toca la marca, e `insert()` no guarda una que traiga la entrada** (`withDeceasedYear()` solo sirve para razonar en memoria): la única forma de registrar una defunción es `markDeceased()`, con su comprobante.
- **También levanta el límite del enlazador**: las ediciones de una persona que murió se pueden enlazar, porque no arman el perfil de alguien vivo.
- Las bases creadas antes reciben las dos columnas (`deceased_year`, `deceased_evidence`) la próxima vez que se llama a `createTable()`.

```bash
php bin/phonedir search --name=garcia --dsn=sqlite:madrid.sqlite            # [persona #42] García Ruiz, José | …
php bin/phonedir deceased 42 --year=1988 --evidence="Esquela, La Vanguardia, 12/03/1988" --dsn=sqlite:madrid.sqlite
php bin/phonedir deceased 42 --clear --dsn=sqlite:madrid.sqlite
```

Qué ve entonces un visitante: nombre, dirección y teléfono de esa persona, con `† 1988`. **Lo que esta marca no resuelve:** los parientes que siguen viviendo en esa dirección o con ese teléfono. La guía de una persona recién fallecida puede ser, hoy, la de una familia viva; el criterio de cuándo mostrarla es de quien publica.

Límites, a propósito:

- **Una guía sin año conocido se oculta.** Sin fuente, o con un id fuera del catálogo que no trae un año de cuatro cifras (como `custom_1990_x`): no se sabe cuántos años tiene esa gente, y un error publica el teléfono de alguien que vive. `url-routing/` hace lo contrario con las personas sin fechas, porque ahí los datos son de antepasados.
- **La edad mínima de 18 años es un supuesto.** Un abonado más joven (raro) quedaría fuera de lo que esta regla cubre.
- **Las empresas (`JuridicalEntity`) no se filtran:** no son personas. Una razón social que lleva un nombre propio sí podría delatar a alguien.

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

El `PhoneDirectoryManager` anterior (v1, solo inglés y solo personas naturales) ya no existe: `PhoneDirectoryManagerV2` hace lo mismo para las personas naturales y además maneja empresas.

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

`search` muestra el id de cada persona (`[persona #42]`), que es el que usa `deceased`. `search` y `link` muestran todo lo guardado. Con `--publico` muestran solo lo que vería un visitante (sin las guías que pueden incluir personas vivas) y lo dicen al final:

```bash
php bin/phonedir search --name=garcia --publico --dsn=sqlite:madrid.sqlite
php bin/phonedir link --sources=es_1930_madrid,es_1975_national --publico --dsn=sqlite:madrid.sqlite   # 0 enlaces: 1975 está oculta
```

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
deceased_year         INTEGER             -- año en que murió, si alguien lo comprobó (markDeceased)
deceased_evidence     TEXT                -- dónde consta; solo lo lee el propietario
record_date           DATETIME
created_at            DATETIME
updated_at            DATETIME
```

`juridical_entities` tiene la misma estructura de procedencia geográfica (`country_code`, `source_directory_id`, `source_line`) y de búsqueda sin acentos (`business_name_folded`, `street_folded`), además de `business_name`, `legal_name`, `business_type`.

Las columnas `raw_name`/`surname_soundex`/`full_name_folded`/`deceased_year` (y sus equivalentes) se agregan automáticamente, junto con sus índices, la primera vez que `createTable()` se ejecuta sobre una tabla creada con una versión anterior de este módulo.

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

El análisis estático (PHPStan, nivel 6; configuración en `phpstan.neon.dist`) también lo corre la CI, en PHP 8.1 a 8.4:

```bash
phpstan analyse
```

PHPStan no es una dependencia del proyecto: se instala aparte (por ejemplo `composer global require phpstan/phpstan:^2.1`) y la CI lo trae con `shivammathur/setup-php`. Revisa `src/`, `bin/` (incluido `bin/phonedir`) y `examples/`, no los tests.

Los ports a Python y Java (`ports/`) tienen sus propios tests; ver `docs/PROJECT_COMPLETION_REPORT.md`.

### Cuánto acierta el parser

`tests/fixtures/parser-evaluation.json` es un conjunto fijo de guías cortas en los seis idiomas, cada una con lo que una persona leería en ella (nombre, calle, teléfono, persona o empresa). `bin/evaluate-parser.php` pasa `MultiLanguagePhoneDirectoryParser` por esos casos y dice cuántos salen bien, cuántas entradas encuentra y cuántas de ellas son correctas, y qué sigue fallando, agrupado por causa:

```bash
php bin/evaluate-parser.php            # resumen
php bin/evaluate-parser.php --detail   # además, lo esperado y lo obtenido en cada caso que falla
```

Los casos que el parser todavía resuelve mal están marcados con `falla_conocida` y su motivo. `ParserEvaluationTest` (y el script, que corre también en la CI) exige que los demás salgan exactamente como dice el conjunto y que los marcados sigan fallando: un cambio que arregla uno tiene que quitarle la marca, y uno que rompe otro no pasa. Para medir una mejora, agrega primero los casos que la muestran.

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
- Ver «Privacidad: personas que podrían estar vivas» para lo que la base no devuelve por omisión y por qué
- La detección automática de idioma es una heurística por palabras clave; con muy poco texto, o en un idioma no soportado, puede fallar (siempre se puede indicar el idioma explícitamente)
- Los casos de `tests/fixtures/parser-evaluation.json` marcados con `falla_conocida` documentan lo que el parser multilingüe todavía no resuelve; entre ellos, que el teléfono solo se reconoce con formatos de EE. UU. (no `01 42 68 53 00`, `030 123456`, `2-345-678`…), que una calle sin palabra de calle y con el número al final («Los Aromos 123») no se reconoce, y que dos registros seguidos sin línea en blanco se leen como uno

## Licencia

MIT
