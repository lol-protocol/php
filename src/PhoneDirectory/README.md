# Phone Directory Parser for Genealogical Records

Un módulo robusto para parsear guías telefónicas en formato TXT, extraer nombres y direcciones (solo la calle), y almacenarlos en una base de datos relacional con fines genealógicos.

## Características

- **Parser inteligente**: Extrae nombres, direcciones (solo calle) y números telefónicos de archivos TXT
- **Validación**: Valida que cada entrada tenga nombre y dirección
- **Base de datos relacional**: Almacena datos en SQLite u otro RDBMS soportado por PDO
- **Búsqueda flexible**: Busca por nombre, calle, teléfono o criterios combinados
- **Manejo de errores**: Registra errores de parsing sin detener el proceso
- **Genealogía**: Diseñado específicamente para aplicaciones genealógicas

## Componentes

### PhoneDirectoryEntry
Modelo que representa una entrada del directorio telefónico.

```php
$entry = new PhoneDirectoryEntry(
    fullName: 'SMITH, John',
    street: '123 Main Street',
    phoneNumber: '555-123-4567'
);
```

### PhoneDirectoryParser
Parser que procesa archivos TXT de directorio telefónico.

```php
$parser = new PhoneDirectoryParser();
$entries = $parser->parseFile('directory.txt');

// O parsear contenido directamente
$entries = $parser->parseContent($textContent);
```

**Patrones soportados:**
- Números telefónicos: `555-123-4567`, `5551234567`, `555.123.4567`
- Calles: Detecta automáticamente direcciones con palabras clave como Street, Avenue, Road, Drive, Lane, etc.
- Separadores: Líneas con guiones o signos igual separadores

### PhoneDirectoryDatabaseInterface
Interfaz para operaciones de base de datos.

### PhoneDirectoryPDODatabase
Implementación concreta usando PDO.

```php
// SQLite (en memoria o archivo)
$db = new PhoneDirectoryPDODatabase('sqlite::memory:');

// SQLite en archivo
$db = new PhoneDirectoryPDODatabase('sqlite:/path/to/database.db');

// MySQL
$db = new PhoneDirectoryPDODatabase('mysql:host=localhost;dbname=genealogy');

$db->connect();
$db->createTable();
```

### PhoneDirectoryManager
Coordinador que integra parser y base de datos.

```php
$manager = new PhoneDirectoryManager();

// Procesar archivo completo
$result = $manager->processFile('directory.txt');

// Operaciones individuales
$id = $manager->addEntry($entry);
$entry = $manager->getEntry($id);
$entries = $manager->findByName('SMITH');
$entries = $manager->findByStreet('Main Street');
$entry = $manager->findByPhone('555-123-4567');

// Búsqueda avanzada
$results = $manager->search([
    'name' => 'GARCIA',
    'street' => 'Street'
]);
```

## Uso Básico

### 1. Instalación
```bash
composer install
```

### 2. Crear base de datos e insertar datos
```php
use PhoneDirectory\PhoneDirectoryManager;
use PhoneDirectory\PhoneDirectoryPDODatabase;

$db = new PhoneDirectoryPDODatabase('sqlite:genealogy.db');
$manager = new PhoneDirectoryManager(database: $db);

// Procesar archivo
$result = $manager->processFile('sample_directory.txt');

echo "Insertadas: {$result['insertedCount']} entradas\n";
echo "Errores: {$result['errorCount']}\n";
```

### 3. Buscar información
```php
// Por nombre
$entries = $manager->findByName('ANDERSON');

// Por calle
$entries = $manager->findByStreet('Main Street');

// Por teléfono
$entry = $manager->findByPhone('555-123-4567');

// Búsqueda combinada
$results = $manager->search([
    'name' => 'SMITH',
    'street' => 'Avenue'
]);
```

### 4. Gestionar datos
```php
// Agregar entrada
$entry = new PhoneDirectoryEntry(
    'WILSON, Charles',
    '913 Oak Street',
    '555-456-7890'
);
$id = $manager->addEntry($entry);

// Actualizar
$entry->setId($id);
$manager->updateEntry($entry);

// Eliminar
$manager->deleteEntry($id);

// Total de entradas
echo "Total: {$manager->getTotalCount()}";

// Obtener todas
$all = $manager->getAllEntries();
```

## Formato de Archivo TXT

El archivo debe tener el siguiente formato (flexible):

```
NOMBRE, Apellido
Dirección (calle)
Número de teléfono (opcional)

NOMBRE2, Apellido2
Dirección 2
Número de teléfono (opcional)

------- (separador opcional)

NOMBRE3, Apellido3
Dirección 3
```

**Reglas:**
- Nombre y calle son obligatorios
- Teléfono es opcional
- Líneas en blanco separan entradas
- Pueden usarse líneas de guiones o signos igual para separar grupos
- Los números telefónicos se detectan automáticamente

## Estructura de Base de Datos

La tabla `phone_directory` contiene:

```
id                INTEGER PRIMARY KEY
full_name         TEXT NOT NULL
street            TEXT NOT NULL
phone_number      TEXT
record_date       DATETIME
created_at        DATETIME
updated_at        DATETIME
```

Índices:
- `idx_full_name` - Para búsquedas por nombre
- `idx_street` - Para búsquedas por dirección
- `idx_phone_number` - Para búsquedas por teléfono

## Pruebas

Ejecutar pruebas unitarias:

```bash
phpunit tests/PhoneDirectory/
```

Las pruebas cubren:
- Parsing de archivos y contenido
- Inserción y recuperación de datos
- Búsquedas por criterios
- Validación de datos
- Manejo de errores

## Ejemplo Completo

Ver `examples/process_phone_directory.php`

```bash
php examples/process_phone_directory.php
```

## Opciones de Base de Datos Soportadas

### SQLite (Recomendado para genealogía local)
```php
// En memoria
$db = new PhoneDirectoryPDODatabase('sqlite::memory:');

// En archivo
$db = new PhoneDirectoryPDODatabase('sqlite:/path/to/file.db');
```

### MySQL
```php
$db = new PhoneDirectoryPDODatabase(
    'mysql:host=localhost;dbname=genealogy;charset=utf8mb4'
);
```

### PostgreSQL
```php
$db = new PhoneDirectoryPDODatabase(
    'pgsql:host=localhost;dbname=genealogy'
);
```

## API Completa

### PhoneDirectoryParser

- `parseFile(string $filePath): array` - Parsear archivo TXT
- `parseContent(string $content): array` - Parsear contenido de texto
- `getEntries(): array` - Obtener entradas parseadas
- `getErrors(): array` - Obtener errores de parsing
- `getEntriesCount(): int` - Contar entradas válidas
- `getErrorsCount(): int` - Contar errores
- `reset(): void` - Limpiar estado

### PhoneDirectoryPDODatabase

- `connect(): void` - Conectar a base de datos
- `disconnect(): void` - Desconectar
- `createTable(): void` - Crear tabla y índices
- `insert(PhoneDirectoryEntry): int` - Insertar entrada
- `insertBatch(array): int` - Insertar múltiples
- `findById(int): ?PhoneDirectoryEntry`
- `findByName(string): array`
- `findByStreet(string): array`
- `findByPhone(string): ?PhoneDirectoryEntry`
- `getAll(): array`
- `update(PhoneDirectoryEntry): bool`
- `delete(int): bool`
- `count(): int`
- `search(array): array`
- `clear(): bool`

### PhoneDirectoryManager

Interfaz de alto nivel que combina parser y base de datos.

- `processFile(string, bool): array`
- `addEntry(PhoneDirectoryEntry): int`
- `getEntry(int): ?PhoneDirectoryEntry`
- `findByName(string): array`
- `findByStreet(string): array`
- `findByPhone(string): ?PhoneDirectoryEntry`
- `getAllEntries(): array`
- `search(array): array`
- `updateEntry(PhoneDirectoryEntry): bool`
- `deleteEntry(int): bool`
- `getTotalCount(): int`

## Consideraciones de Rendimiento

Para directorios telefónicos grandes:
- Usar base de datos en archivo en lugar de en memoria
- Usar `insertBatch()` para inserciones masivas
- Crear índices adicionales según criterios de búsqueda
- Considerar particionamiento de datos por período o región

## Limitaciones

- Solo se extrae el número de calle de la dirección completa
- No se valida el formato del teléfono (se detecta por patrón)
- No maneja direcciones internacionales complejas
- No valida números de teléfono reales (por fines genealógicos)

## Licencia

MIT
