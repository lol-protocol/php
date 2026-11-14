# Arquitectura del Módulo Phone Directory Parser

Módulo para parsear guías telefónicas históricas en múltiples idiomas, extraer datos genealógicos de personas naturales y jurídicas, y almacenarlos en una base de datos relacional (SQLite, MySQL/MariaDB o PostgreSQL).

## Diagrama General del Sistema

```mermaid
graph TD
    A[Archivo TXT<br/>Phone Directory] -->|File I/O| B[PhoneDirectoryParser<br/>o<br/>MultiLanguagePhoneDirectoryParser]

    B -->|una entrada por línea| S[SingleLineEntrySplitter]
    B -->|reconoce teléfonos| PP[PhonePattern]
    S --> B

    B -->|Parsea contenido| C{Detección de<br/>Tipo de Entidad}

    C -->|Persona Natural| D[PhoneDirectoryEntry]
    C -->|Persona Jurídica| E[JuridicalEntity]

    D -->|compone| PN[PersonName]
    D -->|compone| GL[GeoLocation]

    D -->|Almacena vía| SD[SqlDialect]
    E -->|Almacena vía| SD
    SD -->|SQL específico del motor| F[PhoneDirectoryPDODatabase<br/>phone_directory table]
    SD -->|SQL específico del motor| G[JuridicalEntityPDODatabase<br/>juridical_entities table]

    F -->|Consultas| H[PhoneDirectoryManagerV2]
    G -->|Consultas| H

    F -.->|claves fonéticas| SK[SurnameKeys]
    F -.->|entradas de varios directorios| RL[RecordLinker]
    RL -.->|años de referencia| CAT[PhoneDirectoryCatalog]

    H -->|API de Alto Nivel| I[Aplicación Genealógica]
```

## Flujo de Procesamiento

```mermaid
sequenceDiagram
    participant User as Usuario
    participant Manager as PhoneDirectoryManagerV2
    participant Parser as MultiLanguagePhoneDirectoryParser
    participant Split as SingleLineEntrySplitter
    participant DB1 as PhoneDirectoryPDODatabase
    participant DB2 as JuridicalEntityPDODatabase

    User->>Manager: processFile(filename, language?)
    Manager->>Parser: parseFile(filename, language)
    Parser->>Parser: detectLanguage() (si no se indicó)

    loop Para cada línea del archivo
        Parser->>Split: split(línea)
        alt la línea es una entrada completa
            Split-->>Parser: {name, street, phone}
        else no (formato de varias líneas)
            Parser->>Parser: acumula en el bloque actual
        end
        Parser->>Parser: extractStreet() / isJuridicalEntity()
    end

    Parser-->>Manager: array[{type, entity: PhoneDirectoryEntry|JuridicalEntity}]

    Manager->>DB1: insertBatch(natural_people)
    Manager->>DB2: insertBatch(juridical_entities)

    DB1-->>Manager: inserted_count
    DB2-->>Manager: inserted_count

    Manager-->>User: result{totalParsed, naturalInserted, juridicalInserted, errors}
```

## Estructura de Clases - Personas Naturales

```mermaid
classDiagram
    class PhoneDirectoryEntry {
        -int id
        -PersonName personName
        -GeoLocation geoLocation
        -string rawName
        -string phoneNumber
        -string sourceDirectoryId
        -int sourceLine
        -DateTime recordDate

        +getId(): int
        +getFullName(): string
        +getFormattedName(): string
        +getRawName(): string
        +getTitle(): ?string
        +getFirstName(): string
        +getLastNames(): array
        +getCountryCode(): string
        +getZone(): ?string
        +getCity(): ?string
        +getStreet(): string
        +getPhoneNumber(): ?string
        +getLanguage(): ?string
        +getSourceDirectoryId(): ?string
        +getSourceLine(): ?int
        +getRecordDate(): DateTime
        +toArray(): array
    }

    class PersonName {
        -array firstNames
        -array lastNames
        -string title
        -string language

        +getFirstNames(): array
        +getLastNames(): array
        +getSurnameRoot(): ?string
        +getTitle(): ?string
        +getFormattedName(): string
    }

    class GeoLocation {
        -string countryCode
        -string zone
        -string city
        -string street

        +getFullAddress(): string
    }

    class PhoneDirectoryParser {
        -string countryCode
        -string sourceDirectoryId
        -array entries
        -array parseErrors

        +static forCatalogDirectory(string): self
        +parseFile(string): array
        +parseContent(string): array
        +getEntries(): array
        +getErrors(): array
        +reset(): void
    }

    class DirectoryDatabaseInterface {
        <<interface>>
        +connect(): void
        +disconnect(): void
        +isConnected(): bool
        +createTable(): void
        +insertBatch(array): int
        +findById(int): ?TEntity
        +findByStreet(string): array
        +findByPhone(string): ?TEntity
        +getAll(): array
        +delete(int): bool
        +count(): int
        +clear(): bool
    }

    class PhoneDirectoryDatabaseInterface {
        <<interface>>
        +insert(PhoneDirectoryEntry): int
        +update(PhoneDirectoryEntry): bool
        +findByName(string): array
        +findBySurnameSound(string, ?string): array
        +findBySourceDirectory(string): array
        +search(array): array
    }

    class PhoneDirectoryPDODatabase {
        -PDO pdo
        -SqlDialect dialect
        -string dsn

        +createTable(): void
        +insert(PhoneDirectoryEntry): int
        +insertBatch(array): int
        +findById(int): ?PhoneDirectoryEntry
        +findByName(string): array
        +findByStreet(string): array
        +findByPhone(string): ?PhoneDirectoryEntry
        +findBySurnameSound(string, ?string): array
        +findBySourceDirectory(string): array
    }

    PhoneDirectoryEntry *-- PersonName
    PhoneDirectoryEntry *-- GeoLocation
    DirectoryDatabaseInterface <|-- PhoneDirectoryDatabaseInterface : TEntity = PhoneDirectoryEntry
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
        -string countryCode
        -string sourceDirectoryId
        -int sourceLine
        -DateTime recordDate

        +getId(): int
        +getBusinessName(): string
        +getLegalName(): ?string
        +getStreet(): string
        +getPhoneNumber(): ?string
        +getBusinessType(): ?string
        +getCountryCode(): string
        +getSourceDirectoryId(): ?string
        +getSourceLine(): ?int
    }

    class DirectoryDatabaseInterface {
        <<interface>>
    }

    class JuridicalEntityDatabaseInterface {
        <<interface>>
        +insert(JuridicalEntity): int
        +update(JuridicalEntity): bool
        +findByBusinessName(string): array
        +findByBusinessType(string): array
        +search(array): array
    }

    class JuridicalEntityPDODatabase {
        -PDO pdo
        -SqlDialect dialect
        -string dsn

        +createTable(): void
        +insert(JuridicalEntity): int
        +insertBatch(array): int
        +findById(int): ?JuridicalEntity
        +findByBusinessName(string): array
        +findByStreet(string): array
        +findByPhone(string): ?JuridicalEntity
        +findByBusinessType(string): array
    }

    DirectoryDatabaseInterface <|-- JuridicalEntityDatabaseInterface : TEntity = JuridicalEntity
    JuridicalEntityDatabaseInterface <|.. JuridicalEntityPDODatabase
    JuridicalEntityPDODatabase -->|almacena| JuridicalEntity
```

## Arquitectura del Parser Multiidioma

```mermaid
graph LR
    A[Contenido TXT] -->|Entrada| B[MultiLanguagePhoneDirectoryParser]

    B -->|Paso 1, si no se indica el idioma| C[Detectar Idioma]
    C -->|marcadores distintivos<br/>por idioma, sin ambigüedad<br/>con palabras inglesas| D{Idioma Detectado}

    D -->|ES| E["calle, avenida,<br/>pasaje, camino..."]
    D -->|EN| F["street, avenue,<br/>road, drive... (por defecto)"]
    D -->|FR| G["rue, allée, cours..."]
    D -->|PT| H["rua, avenida,<br/>praça, alameda..."]
    D -->|DE| I["-straße, -allee,<br/>-weg, -platz (sufijos)"]
    D -->|IT| J["viale, corso,<br/>piazza, strada..."]

    E --> K["Paso 2: por línea,<br/>SingleLineEntrySplitter<br/>o bloque de varias líneas"]
    F --> K
    G --> K
    H --> K
    I --> K
    J --> K

    K -->|Paso 3| L[Clasificar Tipo de Entidad]

    L -->|marcadores de negocio<br/>por idioma| M{Persona Natural<br/>o Jurídica?}

    M -->|Natural| N[PhoneDirectoryEntry]
    M -->|Jurídica| O[JuridicalEntity]

    N --> P[Salida Clasificada]
    O --> P
```

Sin ningún marcador reconocido, o en caso de empate, el idioma detectado es siempre inglés (el idioma base del proyecto), nunca el primero de la lista por casualidad.

## Formatos de Entrada

```mermaid
graph TD
    A[Línea de texto] --> B{SingleLineEntrySplitter}

    B -->|tiene delimitadores fuertes<br/>comas, línea de puntos, tabs| C["División por campos<br/>(el campo-calle puede ir<br/>primero o último)"]
    B -->|campos separados por<br/>un solo espacio| D["División por el primer dígito<br/>(una vez quitado el teléfono)"]

    C --> E{¿Se pudo identificar<br/>nombre + calle?}
    D --> E

    E -->|Sí| F[Entrada de una sola línea]
    E -->|No| G[Se acumula como parte de<br/>un bloque de varias líneas]

    G --> H["Bloque completo<br/>(nombre + calle + teléfono<br/>en líneas separadas)"]
```

## Esquema de Base de Datos

Las columnas de `phone_directory` y de `juridical_entities` están en la sección «Estructura de Base de Datos» del
[README](../README.md); los índices, más abajo. Las dos tablas son independientes entre sí (no hay relación
declarada); cada una se consulta por separado desde `PhoneDirectoryManagerV2`. `createTable()` agrega
automáticamente, con sus índices, cualquier columna que falte en una tabla creada por una versión anterior del
módulo, y recalcula su valor para las filas existentes.

## Ciclo de Vida del Procesamiento

```mermaid
stateDiagram-v2
    [*] --> ReadFile

    ReadFile --> DetectLanguage: si no se indicó el idioma
    ReadFile --> ParseLines: si se indicó

    DetectLanguage --> ParseLines

    ParseLines --> TrySingleLine
    TrySingleLine --> ExtractData: la línea es una entrada completa
    TrySingleLine --> AccumulateBlock: no lo es

    AccumulateBlock --> ParseLines: quedan líneas del bloque
    AccumulateBlock --> ExtractData: línea en blanco o separador

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
    A[PhoneDirectoryManagerV2] -->|Coordina| B[MultiLanguagePhoneDirectoryParser]
    A -->|Coordina| C[PhoneDirectoryPDODatabase]
    A -->|Coordina| D[JuridicalEntityPDODatabase]

    B -->|Procesa| E[Archivo TXT]
    B -->|Retorna| F[Entidades Clasificadas]

    F -->|Filtra| G[Personas Naturales]
    F -->|Filtra| H[Personas Jurídicas]

    G -->|Inserta| C
    H -->|Inserta| D

    C -->|Consultas, incluida<br/>búsqueda fonética| I[Búsqueda de Personas]
    D -->|Consultas| J[Búsqueda de Negocios]

    I -->|Retorna| K[Resultados Genealógicos]
    J -->|Retorna| K

    K -->|Disponible para| L[Aplicación]
```

## Enlace de Registros Entre Directorios

```mermaid
graph LR
    A[PhoneDirectoryPDODatabase] -->|findBySourceDirectory<br/>por cada edición| B[Entradas de varias<br/>ediciones de un directorio]

    B --> C[RecordLinker.link]

    C -->|agrupa por| D["país + sonido del apellido<br/>+ inicial del nombre<br/>(SurnameKeys)"]

    D -->|compara pares<br/>dentro de cada grupo| E{¿Nombres de pila<br/>compatibles?}

    E -->|No| F[Sin enlace]
    E -->|Sí| G["Puntúa: mismo apellido,<br/>misma dirección, mismo<br/>teléfono..."]

    G --> H["RecordLink<br/>{earlier, later, score, evidence}"]

    H -->|orden por año del<br/>catálogo, o del propio<br/>id del directorio| I[PhoneDirectoryCatalog]
```

Agrupar por país, sonido de apellido e inicial del nombre reduce cuánto hay que comparar sin descartar ningún enlace válido: el propio criterio de comparación ya exige que los nombres de pila compartan esa inicial.

## Patrones de Búsqueda Soportados

```mermaid
graph LR
    A[Búsqueda] -->|Por Nombre| B["findByName<br/>insensible a mayúsculas y acentos"]
    A -->|Por Dirección| C["findByStreet<br/>insensible a mayúsculas y acentos"]
    A -->|Por Teléfono| D["findByPhone<br/>Exacto"]
    A -->|Por Sonido del Apellido| E["findBySurnameSound<br/>Smith/Smyth, Valdez/Baldez..."]
    A -->|Por Directorio de Origen| F["findBySourceDirectory<br/>una edición completa, en orden"]
    A -->|Por Tipo de Negocio| G["findByBusinessType<br/>SPA, SRL, Restaurante..."]
    A -->|Búsqueda Combinada| H["search<br/>Múltiples criterios AND"]
    A -->|Obtener Todos| I["getAll"]
```

## Índices de Base de Datos

Los nombres de índice incluyen la tabla (`idx_phone_directory_street`, no `idx_street`), porque SQLite y PostgreSQL comparten el espacio de nombres de índices entre tablas de la misma base de datos.

```sql
-- Personas Naturales
CREATE INDEX idx_phone_directory_full_name ON phone_directory(full_name);
CREATE INDEX idx_phone_directory_country_code ON phone_directory(country_code);
CREATE INDEX idx_phone_directory_street ON phone_directory(street);
CREATE INDEX idx_phone_directory_phone_number ON phone_directory(phone_number);
CREATE INDEX idx_phone_directory_source_directory_id ON phone_directory(source_directory_id);
CREATE INDEX idx_phone_directory_surname_soundex ON phone_directory(surname_soundex);
CREATE INDEX idx_phone_directory_surname_phonetic ON phone_directory(surname_phonetic);
CREATE INDEX idx_phone_directory_full_name_folded ON phone_directory(full_name_folded);
CREATE INDEX idx_phone_directory_street_folded ON phone_directory(street_folded);

-- Personas Jurídicas
CREATE INDEX idx_juridical_entities_business_name ON juridical_entities(business_name);
CREATE INDEX idx_juridical_entities_street ON juridical_entities(street);
CREATE INDEX idx_juridical_entities_phone_number ON juridical_entities(phone_number);
CREATE INDEX idx_juridical_entities_business_type ON juridical_entities(business_type);
CREATE INDEX idx_juridical_entities_country_code ON juridical_entities(country_code);
CREATE INDEX idx_juridical_entities_source_directory_id ON juridical_entities(source_directory_id);
CREATE INDEX idx_juridical_entities_business_name_folded ON juridical_entities(business_name_folded);
CREATE INDEX idx_juridical_entities_street_folded ON juridical_entities(street_folded);
```

## Extensibilidad

El diseño modular permite:
- Agregar nuevos idiomas en `MultiLanguagePhoneDirectoryParser` (marcadores de calle, tipo de entidad y conteo de apellidos)
- Implementar nuevas bases de datos heredando de las interfaces, o agregando un nuevo motor a `SqlDialect`
- Personalizar el parsing según formato específico
- Agregar búsquedas avanzadas en el Manager

## Licencia

MIT
