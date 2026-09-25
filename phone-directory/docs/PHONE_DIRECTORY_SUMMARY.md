# Phone Directory Parser para registros genealógicos

Módulo PHP que parsea guías telefónicas históricas en texto plano, separa personas naturales de empresas,
descompone nombres y direcciones, y guarda todo en una base de datos relacional preparada para búsquedas
genealógicas: por nombre sin acentos, por calle, por teléfono, por cómo suena el apellido y por la misma
persona a lo largo de distintos directorios.

Vive en `phone-directory/` y usa la librería `defamatory-content-review/` para el plegado de acentos y las claves
fonéticas de apellidos.

---

## Flujo de procesamiento

```
archivo .txt
   │  LineReader: lee línea a línea (memoria constante respecto al tamaño del archivo)
   ▼
MultiLanguagePhoneDirectoryParser
   │  1. detecta el idioma (es, en, fr, pt, de, it) por vocabulario de calles
   │  2. agrupa líneas en registros: una entrada por línea o bloques separados por líneas vacías / ----
   │  3. extrae nombre, calle y teléfono
   │  4. clasifica: persona natural o persona jurídica (empresa)
   │  5. registra como error de parseo lo que no se puede leer (campos faltantes, UTF-8 inválido)
   ▼
PhoneDirectoryEntry / JuridicalEntity
   │  PersonName: apellidos y nombres según las reglas del idioma
   │  GeoLocation: país, zona, ciudad y calle
   ▼
PhoneDirectoryPDODatabase / JuridicalEntityPDODatabase
   │  inserción por lotes en una transacción (sentencia preparada una sola vez)
   │  columnas derivadas: nombre y calle sin acentos, Soundex y clave fonética del apellido
   ▼
búsquedas · estadísticas · RecordLinker (misma persona en distintos directorios)
```

---

## Estructura de clases

Todas bajo el namespace `PhoneDirectory\` en `src/PhoneDirectory/`.

| Carpeta / clase | Responsabilidad |
|-----------------|-----------------|
| `Parser/MultiLanguagePhoneDirectoryParser` | Parser principal: idioma, registros, personas vs. empresas |
| `Parser/PhoneDirectoryParser` | Parser original, solo inglés y personas naturales |
| `Parser/SingleLineEntrySplitter` | Separa "APELLIDO, Nombre ..... 12 Main St ... 555-1234" en campos |
| `Parser/PhonePattern` | Regex de teléfonos, validada una vez |
| `Parser/LineReader` | Lectura de archivos línea a línea |
| `Entity/PhoneDirectoryEntry` | Persona natural |
| `Entity/JuridicalEntity` | Empresa / persona jurídica |
| `Entity/PersonName` | Nombres, apellidos, partículas ("de la", "van der"), títulos y viudas ("Vda. de") |
| `Entity/GeoLocation` | País (ISO 3166-1), zona, ciudad, calle |
| `PhoneDirectoryPDODatabase`, `JuridicalEntityPDODatabase` | Persistencia de cada tipo de entidad |
| `Database/EntityPDODatabase` | Base común: CRUD, lotes transaccionales, búsquedas, backfill |
| `SqlDialect` | Diferencias entre SQLite, MySQL/MariaDB y PostgreSQL |
| `Manager/PhoneDirectoryManagerV2` | Fachada para procesar archivos y consultar ambos tipos de entidad |
| `Manager/PhoneDirectoryManager` | Versión anterior, solo personas naturales (`@deprecated`) |
| `SurnameKeys` | Soundex y clave fonética por idioma del apellido |
| `RecordLinker`, `RecordLink` | Propone qué entradas de distintos directorios son la misma persona |
| `PhoneDirectoryCatalog` | Catálogo de 16 directorios históricos (1878–1975, 10 países) |
| `TextFolding` | Minúsculas y sin acentos, validando UTF-8 antes de transformar |
| `Validation/InputValidator` | Validación compartida de código de país y campos obligatorios |
| `Exception/*` | `DatabaseException`, `InvalidCountryCodeException`, `InvalidEncodingException`, `InvalidLanguageException` |

`Config/ParserConfig` y `Logger/*` existen, pero hoy nada los usa.

---

## Idiomas soportados

| Idioma | Código | Calles (ejemplos) | Empresas (ejemplos) |
|--------|--------|-------------------|---------------------|
| Español | `es` | calle, avenida, pasaje, camino, carrera | sa, ltda, farmacia, hotel, banco |
| Inglés | `en` | street, avenue, road, drive, lane, blvd | inc, ltd, llc, company, hotel, bank |
| Francés | `fr` | rue, avenue, allée, place, boulevard | sarl, sas, pharmacie, hôtel, banque |
| Portugués | `pt` | rua, avenida, praça, alameda, estrada | ltda, lda, farmácia, padaria, banco |
| Alemán | `de` | …straße, …allee, …weg, …platz (sufijos) | gmbh, ag, kg, apotheke, gasthaus |
| Italiano | `it` | via, viale, corso, piazza, strada | srl, spa, trattoria, farmacia, banca |

El idioma se detecta solo con vocabulario que no es también inglés ("plaza" o "avenue" no cuentan), así que un
directorio en inglés con una "Plaza" no se confunde con español. El español y el portugués toman dos apellidos.

---

## Búsquedas

| Búsqueda | Cómo funciona |
|----------|---------------|
| Por nombre o calle | Subcadena sin distinguir mayúsculas ni acentos ("garcia" encuentra "GARCÍA"); `%` y `_` se tratan literalmente |
| Por teléfono | Coincidencia exacta |
| Por sonido del apellido | Soundex, más una clave fonética propia del idioma ("Valdez" ↔ "Baldez" en español) |
| Por tipo de empresa | Palabra que clasificó a la empresa ("farmacia", "hotel"…) |
| Enlace de registros | `RecordLinker` puntúa pares de entradas de distintos directorios con mismo sonido de apellido, país y nombre compatible |

---

## Uso

### Desde PHP

```php
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\Manager\PhoneDirectoryManagerV2;
use PhoneDirectory\PhoneDirectoryPDODatabase;

$dsn = 'sqlite:genealogy.sqlite';
$manager = new PhoneDirectoryManagerV2(
    naturalDatabase: new PhoneDirectoryPDODatabase($dsn),
    juridicalDatabase: new JuridicalEntityPDODatabase($dsn)
);

$result = $manager->processFile('directorio_1950.txt');   // idioma detectado automáticamente
echo "{$result['totalInserted']} insertados, {$result['errorCount']} errores\n";

$manager->findNaturalPeopleByName('garcia');
$manager->findNaturalPeopleBySurnameSound('Valdez', 'es');
$manager->findByStreet('Calle Mayor');          // ['natural' => [...], 'juridical' => [...]]
$manager->findJuridicalEntitiesByType('farmacia');
```

Hay ejemplos completos en `examples/process_phone_directory.php` y `examples/multilingual_example.php`.

### Desde la línea de comandos

```bash
php bin/phonedir import directorio_1930.txt --catalog=es_1930_madrid --dsn=sqlite:madrid.sqlite
php bin/phonedir search --name=garcia --dsn=sqlite:madrid.sqlite
php bin/phonedir search --surname-sound=valdez --language=es --dsn=sqlite:madrid.sqlite
php bin/phonedir link --sources=es_1930_madrid,es_1975_national --dsn=sqlite:madrid.sqlite
php bin/phonedir stats --dsn=sqlite:madrid.sqlite
php bin/phonedir catalog list --country=ES
```

`php bin/phonedir help` muestra todas las opciones.

---

## Esquema de base de datos

`createTable()` crea las tablas y, en bases existentes, agrega las columnas que falten y rellena las derivadas.
Los tipos se adaptan a cada motor (`SqlDialect`).

### `phone_directory`

| Columna | Contenido |
|---------|-----------|
| `id` | Clave primaria autoincremental |
| `full_name`, `raw_name` | Nombre normalizado y tal como venía en el directorio |
| `language` | Idioma con el que se parseó el nombre |
| `country_code` | ISO 3166-1 alfa-2, `NOT NULL DEFAULT 'US'` |
| `zone`, `city`, `street` | Ubicación (`street` obligatoria) |
| `phone_number` | Teléfono |
| `source_directory_id`, `source_line` | Directorio y línea de origen |
| `surname_soundex`, `surname_phonetic` | Claves para buscar por sonido del apellido |
| `full_name_folded`, `street_folded` | Nombre y calle sin acentos ni mayúsculas, para búsquedas |
| `record_date`, `created_at`, `updated_at` | Fechas |

### `juridical_entities`

| Columna | Contenido |
|---------|-----------|
| `id` | Clave primaria autoincremental |
| `business_name`, `legal_name` | Nombre comercial y razón social |
| `business_type` | Tipo detectado ("farmacia", "hotel"…) |
| `country_code`, `street`, `phone_number` | Ubicación y contacto |
| `source_directory_id`, `source_line` | Directorio y línea de origen |
| `business_name_folded`, `street_folded` | Versiones sin acentos para búsquedas |
| `record_date`, `created_at`, `updated_at` | Fechas |

Las columnas de búsqueda llevan índice. El catálogo histórico no está en la base de datos: vive en
`PhoneDirectoryCatalog`.

---

## Tests

231 tests PHPUnit (incluida la ejecución de los ejemplos), que CI ejecuta en PHP 8.1–8.4 contra SQLite y PostgreSQL (MySQL si se define
`PHONEDIR_MYSQL_DSN`). Los ports a Python y Java tienen sus propios tests. El detalle por archivo está en
[PROJECT_COMPLETION_REPORT.md](PROJECT_COMPLETION_REPORT.md).

```bash
cd phone-directory
composer install
./vendor/bin/phpunit
```

---

## Casos de uso genealógicos

1. **Buscar antepasados**: por nombre sin preocuparse por acentos, o por sonido del apellido para cubrir
   variantes ortográficas ("Smith", "Smyth", "Smithe").
2. **Seguir a una familia en el tiempo**: `phonedir link` propone qué entradas de directorios de distintos años son
   la misma persona, aunque se haya mudado.
3. **Analizar una calle o barrio**: quién vivía y qué negocios había en una misma calle.
4. **Negocios familiares**: buscar empresas por nombre o tipo.
5. **Importación masiva**: el archivo se lee línea a línea y se inserta por lotes en una transacción.

---

## Limitaciones conocidas

- `parseFile()` devuelve todas las entradas en un array: la lectura es constante en memoria, pero las entradas
  parseadas se acumulan.
- La clasificación de empresas se basa en una lista de palabras por idioma; un nombre de persona que coincida
  con una de ellas se clasificará como empresa.
- `Config/ParserConfig` y `Logger/*` no están conectados a nada todavía.

## Requisitos

- PHP 8.1 o superior con `mbstring` y `pdo_sqlite` (o `pdo_mysql` / `pdo_pgsql`)
- Composer
- La carpeta hermana `defamatory-content-review/`: `composer install` la instala como dependencia desde un repositorio `path`
