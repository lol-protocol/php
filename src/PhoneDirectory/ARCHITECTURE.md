# Arquitectura del Módulo Phone Directory Parser

Módulo para parsear guías telefónicas en múltiples idiomas, extraer datos genealógicos de personas naturales y jurídicas, y almacenarlos en una base de datos relacional.

## Diagrama General del Sistema

```mermaid
graph TD
    A[Archivo TXT<br/>Phone Directory] -->|File I/O| B[PhoneDirectoryParser<br/>o<br/>MultiLanguagePhoneDirectoryParser]
    
    B -->|Parsea contenido| C{Detección de<br/>Tipo de Entidad}
    
    C -->|Persona Natural| D[PhoneDirectoryEntry]
    C -->|Persona Jurídica| E[JuridicalEntity]
    
    D -->|Almacena| F[PhoneDirectoryPDODatabase<br/>phone_directory table]
    E -->|Almacena| G[JuridicalEntityPDODatabase<br/>juridical_entities table]
    
    F -->|Consultas| H[PhoneDirectoryManager]
    G -->|Consultas| H
    
    H -->|API de Alto Nivel| I[Aplicación Genealógica]
```

## Flujo de Procesamiento

```mermaid
sequenceDiagram
    participant User as Usuario
    participant Manager as PhoneDirectoryManager
    participant Parser as MultiLanguagePhoneDirectoryParser
    participant DB1 as PhoneDirectoryPDODatabase
    participant DB2 as JuridicalEntityPDODatabase
    
    User->>Manager: processFile(filename)
    Manager->>Parser: parseFile(filename)
    Parser->>Parser: detectLanguage()
    Parser->>Parser: parseContent()
    
    loop Para cada línea del archivo
        Parser->>Parser: extractName()
        Parser->>Parser: extractStreet()
        Parser->>Parser: extractPhone()
        Parser->>Parser: isJuridical()
    end
    
    Parser-->>Manager: array[PhoneDirectoryEntry, JuridicalEntity]
    
    Manager->>DB1: insertBatch(natural_people)
    Manager->>DB2: insertBatch(juridical_entities)
    
    DB1-->>Manager: inserted_count
    DB2-->>Manager: inserted_count
    
    Manager-->>User: result{totalParsed, inserted, errors}
```

## Estructura de Clases - Personas Naturales

```mermaid
classDiagram
    class PhoneDirectoryEntry {
        -int id
        -string fullName
        -string street
        -string phoneNumber
        -DateTime recordDate
        
        +getId(): int
        +getFullName(): string
        +getStreet(): string
        +getPhoneNumber(): ?string
        +getRecordDate(): DateTime
        +toArray(): array
    }
    
    class PhoneDirectoryParser {
        -array entries
        -array parseErrors
        
        +parseFile(string): array
        +parseContent(string): array
        +getEntries(): array
        +getErrors(): array
        +reset(): void
    }
    
    class PhoneDirectoryDatabaseInterface {
        <<interface>>
        +connect(): void
        +insert(PhoneDirectoryEntry): int
        +findById(int): ?PhoneDirectoryEntry
        +findByName(string): array
        +search(array): array
    }
    
    class PhoneDirectoryPDODatabase {
        -PDO pdo
        -string dsn
        
        +insert(PhoneDirectoryEntry): int
        +insertBatch(array): int
        +findById(int): ?PhoneDirectoryEntry
        +findByName(string): array
        +findByStreet(string): array
        +findByPhone(string): ?PhoneDirectoryEntry
    }
    
    PhoneDirectoryDatabaseInterface <|.. PhoneDirectoryPDODatabase
    PhoneDirectoryParser -->|crea| PhoneDirectoryEntry
    PhoneDirectoryPDODatabase -->|almacena| PhoneDirectoryEntry
```

## Estructura de Clases - Personas Jurídicas

```mermaid
classDiagram
    class JuridicalEntity {
        -int id
        -string businessName
        -string legalName
        -string street
        -string phoneNumber
        -string businessType
        -DateTime recordDate
        
        +getId(): int
        +getBusinessName(): string
        +getLegalName(): ?string
        +getStreet(): string
        +getPhoneNumber(): ?string
        +getBusinessType(): ?string
    }
    
    class JuridicalEntityDatabaseInterface {
        <<interface>>
        +connect(): void
        +insert(JuridicalEntity): int
        +findById(int): ?JuridicalEntity
        +findByBusinessName(string): array
        +findByBusinessType(string): array
        +search(array): array
    }
    
    class JuridicalEntityPDODatabase {
        -PDO pdo
        -string dsn
        
        +insert(JuridicalEntity): int
        +insertBatch(array): int
        +findById(int): ?JuridicalEntity
        +findByBusinessName(string): array
        +findByStreet(string): array
        +findByPhone(string): ?JuridicalEntity
        +findByBusinessType(string): array
    }
    
    JuridicalEntityDatabaseInterface <|.. JuridicalEntityPDODatabase
    JuridicalEntityPDODatabase -->|almacena| JuridicalEntity
```

## Arquitectura del Parser Multiidioma

```mermaid
graph LR
    A[Contenido TXT] -->|Entrada| B[MultiLanguagePhoneDirectoryParser]
    
    B -->|Paso 1| C[Detectar Idioma]
    C -->|Analiza marcadores| D{Idioma Detectado}
    
    D -->|ES| E["Marcadores Españoles<br/>calle, avenida, av..."]
    D -->|EN| F["Marcadores Ingleses<br/>street, avenue, road..."]
    D -->|FR| G["Marcadores Franceses<br/>rue, avenue, place..."]
    D -->|PT| H["Marcadores Portugueses<br/>rua, avenida, praça..."]
    D -->|DE| I["Marcadores Alemanes<br/>straße, allee, platz..."]
    D -->|IT| J["Marcadores Italianos<br/>via, viale, piazza..."]
    
    E --> K[Extraer Datos]
    F --> K
    G --> K
    H --> K
    I --> K
    J --> K
    
    K -->|Paso 2| L[Clasificar Tipo de Entidad]
    
    L -->|Detecta Tipo| M{Persona Natural<br/>o Jurídica?}
    
    M -->|Natural| N[PhoneDirectoryEntry]
    M -->|Jurídica| O[JuridicalEntity]
    
    N --> P[Salida Clasificada]
    O --> P
```

## Esquema de Base de Datos

```mermaid
erDiagram
    PHONE_DIRECTORY ||--o{ SEARCH_HISTORY : has
    JURIDICAL_ENTITIES ||--o{ SEARCH_HISTORY : has
    
    PHONE_DIRECTORY {
        int id PK
        string full_name
        string street
        string phone_number
        datetime record_date
        datetime created_at
        datetime updated_at
    }
    
    JURIDICAL_ENTITIES {
        int id PK
        string business_name
        string legal_name
        string street
        string phone_number
        string business_type
        datetime record_date
        datetime created_at
        datetime updated_at
    }
    
    SEARCH_HISTORY {
        int id PK
        string entity_type
        int entity_id FK
        string search_criteria
        datetime search_date
    }
```

## Ciclo de Vida del Procesamiento

```mermaid
stateDiagram-v2
    [*] --> ReadFile
    
    ReadFile --> DetectLanguage
    DetectLanguage --> ParseLines
    
    ParseLines --> ExtractData
    ExtractData --> ValidateEntry
    
    ValidateEntry --> CheckType: Válida
    ValidateEntry --> LogError: Inválida
    
    LogError --> ParseLines
    
    CheckType --> ClassifyNatural: Es Natural
    CheckType --> ClassifyJuridical: Es Jurídica
    
    ClassifyNatural --> InsertNatural
    ClassifyJuridical --> InsertJuridical
    
    InsertNatural --> CheckMore
    InsertJuridical --> CheckMore
    
    CheckMore --> ParseLines: Más líneas
    CheckMore --> GenerateReport: Fin del archivo
    
    GenerateReport --> [*]
```

## Integración con Manager

```mermaid
graph TD
    A[PhoneDirectoryManager] -->|Coordina| B[Parser]
    A -->|Coordina| C[DatabaseInterface]
    
    B -->|Procesa| D[Archivo TXT]
    B -->|Retorna| E[Entidades Clasificadas]
    
    E -->|Filtra| F[Personas Naturales]
    E -->|Filtra| G[Personas Jurídicas]
    
    F -->|Inserta| H[PhoneDirectoryPDODatabase]
    G -->|Inserta| I[JuridicalEntityPDODatabase]
    
    H -->|Consultas| J[Búsqueda de Personas]
    I -->|Consultas| K[Búsqueda de Negocios]
    
    J -->|Retorna| L[Resultados Genealógicos]
    K -->|Retorna| L
    
    L -->|Disponible para| M[Aplicación]
```

## Patrones de Búsqueda Soportados

```mermaid
graph LR
    A[Búsqueda] -->|Por Nombre| B["findByName<br/>Personas: García, García López<br/>Negocios: García S.A."]
    A -->|Por Dirección| C["findByStreet<br/>Cualquier palabra de la calle"]
    A -->|Por Teléfono| D["findByPhone<br/>Exacto: 555-123-4567"]
    A -->|Por Tipo Negocio| E["findByBusinessType<br/>SPA, SRL, Restaurante..."]
    A -->|Búsqueda Combinada| F["search<br/>Múltiples criterios AND"]
    A -->|Obtener Todos| G["getAll<br/>Todas las entidades"]
```

## Características Principales

### 1. **Detección Automática de Idioma**
- Analiza marcadores específicos del idioma
- Soporta: Español, Inglés, Francés, Portugués, Alemán, Italiano

### 2. **Clasificación Automática de Entidades**
- Distingue entre personas naturales y jurídicas
- Detecta tipo de negocio cuando corresponde

### 3. **Almacenamiento Separado**
- Tabla `phone_directory` para personas naturales
- Tabla `juridical_entities` para entidades comerciales

### 4. **Búsqueda Flexible**
- Búsqueda por nombre (parcial)
- Búsqueda por dirección (parcial)
- Búsqueda por teléfono (exacto)
- Búsqueda combinada con múltiples criterios

### 5. **Manejo Robusto de Errores**
- Registra errores sin detener el procesamiento
- Proporciona información de línea y contexto

## Índices de Base de Datos

Para optimizar búsquedas en grandes volúmenes:

```sql
-- Personas Naturales
CREATE INDEX idx_full_name ON phone_directory(full_name);
CREATE INDEX idx_street ON phone_directory(street);
CREATE INDEX idx_phone_number ON phone_directory(phone_number);

-- Personas Jurídicas
CREATE INDEX idx_business_name ON juridical_entities(business_name);
CREATE INDEX idx_street ON juridical_entities(street);
CREATE INDEX idx_phone_number ON juridical_entities(phone_number);
CREATE INDEX idx_business_type ON juridical_entities(business_type);
```

## Casos de Uso Genealógicos

### 1. **Búsqueda de Antepasados**
```php
$results = $manager->findByName('García');
foreach ($results as $entry) {
    echo $entry->getFullName() . ', ' . $entry->getStreet();
}
```

### 2. **Análisis por Localidad**
```php
$results = $manager->findByStreet('Main Street');
// Personas que vivían en esa calle
```

### 3. **Búsqueda de Negocios Familiares**
```php
$businesses = $manager->getJuridicalEntities();
$results = $manager->search(['businessName' => 'García']);
```

### 4. **Importación Masiva de Directorios**
```php
$result = $manager->processFile('historical_directory_1950.txt');
echo "Agregadas: {$result['insertedCount']} entradas";
```

## Extensibilidad

El diseño modular permite:
- Agregar nuevos idiomas en `MultiLanguagePhoneDirectoryParser`
- Implementar nuevas bases de datos heredando de interfaces
- Personalizar parsing según formato específico
- Agregar búsquedas avanzadas en el Manager

## Licencia

MIT
