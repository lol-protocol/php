# Guía Completa de Uso - Módulo de Revisión de Contenido Difamatorio

## Tabla de Contenidos

1. [Instalación y Configuración](#instalación-y-configuración)
2. [Casos de Uso Básicos](#casos-de-uso-básicos)
3. [Casos de Uso Avanzados](#casos-de-uso-avanzados)
4. [Integración con Base de Datos](#integración-con-base-de-datos)
5. [Personalización de Palabras](#personalización-de-palabras)
6. [Mejores Prácticas](#mejores-prácticas)
7. [FAQ](#faq)

---

## Instalación y Configuración

### 1. Instalación Inicial

```bash
# Clonar el repositorio
git clone https://github.com/lol-protocol/php.git
cd php

# Instalar dependencias
composer install
```

### 2. Configuración Básica

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\WordList;

// Cargar la configuración de palabras
$config = require __DIR__ . '/config/defamatory-words.php';

// Crear instancia de WordList
$wordList = new WordList($config);

// Crear instancia del revisor
$reviewer = new DefamatoryContentReviewer($wordList);

// ¡Listo para usar!
```

---

## Casos de Uso Básicos

### Caso 1: Validar un Nombre Individual

```php
$result = $reviewer->validateName('Juan');

if ($result->isValid()) {
    echo "✓ El nombre es válido";
} else {
    echo "✗ El nombre contiene contenido inapropiado";
    echo "Severidad: " . $result->getSeverity();
}
```

**Salida esperada:**
```
✓ El nombre es válido
```

### Caso 2: Validar Nombre y Apellido Separados

```php
$firstName = 'Juan';
$lastName = 'Pérez';

$result = $reviewer->validateFullName($firstName, $lastName);

echo "Resultado: " . ($result->isValid() ? 'Válido' : 'Inválido');
```

### Caso 3: Validar Nombre con Problema

```php
$result = $reviewer->validateName('Idiota');

if (!$result->isValid()) {
    echo "Términos marcados:\n";
    foreach ($result->getFlaggedTerms() as $term) {
        echo "  - '{$term['found']}' categoría: {$term['category']}\n";
    }
}
```

**Salida esperada:**
```
Términos marcados:
  - 'idiota' categoría: insultos_personales
```

### Caso 4: Validar Combinación Clásica

```php
// El famoso ejemplo: "Zoila Cerda"
$result = $reviewer->validateFullName('Zoila', 'Cerda');

$report = $reviewer->getDetailedReport($result);

echo "Nombre: " . $report['name'] . "\n";
echo "Válido: " . ($report['valid'] ? 'Sí' : 'No') . "\n";
echo "Severidad: " . $report['severity'] . "\n";
echo "Recomendación: " . $report['recommendation'] . "\n";
```

**Salida esperada:**
```
Nombre: Zoila Cerda
Válido: No
Severidad: medium
Recomendación: Revisar: Contiene términos potencialmente ofensivos.
```

---

## Casos de Uso Avanzados

### Caso 5: Validación por Lotes

```php
$names = [
    'Juan Pérez',
    'María González',
    'Zoila Cerda',
    'Carlos López',
    'Idiota García',
];

$results = $reviewer->batchValidateFullNames($names);

echo "Resumen de Validación\n";
echo str_repeat("-", 50) . "\n";

$valid = 0;
$invalid = 0;

foreach ($results as $result) {
    $status = $result->isValid() ? '✓' : '✗';
    $severity = $result->isValid() ? '-' : $result->getSeverity();
    
    printf("%s %-25s [%s]\n", $status, $result->getFullName(), $severity);
    
    if ($result->isValid()) {
        $valid++;
    } else {
        $invalid++;
    }
}

echo str_repeat("-", 50) . "\n";
printf("Total: %d válidos, %d inválidos\n", $valid, $invalid);
```

**Salida esperada:**
```
Resumen de Validación
--------------------------------------------------
✓ Juan Pérez                  [-]
✓ María González              [-]
✗ Zoila Cerda                 [medium]
✓ Carlos López                [-]
✗ Idiota García               [medium]
--------------------------------------------------
Total: 3 válidos, 2 inválidos
```

### Caso 6: Generar Reporte Detallado para Múltiples Nombres

```php
$names = [
    'Zoila Cerda',
    'Bastardo Mendez',
    'Juan Pérez',
];

$results = $reviewer->batchValidateFullNames($names);

foreach ($results as $result) {
    if (!$result->isValid()) {
        $report = $reviewer->getDetailedReport($result);
        
        echo "=== REPORTE: {$report['name']} ===\n";
        echo "Válido: " . ($report['valid'] ? 'Sí' : 'No') . "\n";
        echo "Severidad: {$report['severity']}\n";
        echo "Términos Marcados: {$report['flaggedTermsCount']}\n";
        echo "Categorías: " . implode(', ', $report['flaggedCategories']) . "\n";
        echo "Recomendación: {$report['recommendation']}\n\n";
    }
}
```

### Caso 7: Filtrado por Severidad

```php
$names = [
    'Zoila Cerda',
    'Bastardo García',
    'María González',
    'Hijo de Puta López',
];

$results = $reviewer->batchValidateFullNames($names);

// Separar por severidad
$byServerity = [
    'high' => [],
    'medium' => [],
    'low' => [],
    'valid' => [],
];

foreach ($results as $result) {
    if ($result->isValid()) {
        $byServerity['valid'][] = $result;
    } else {
        $byServerity[$result->getSeverity()][] = $result;
    }
}

// Procesar según severidad
if (count($byServerity['high']) > 0) {
    echo "⚠️  CRÍTICO - Rechazar inmediatamente:\n";
    foreach ($byServerity['high'] as $result) {
        echo "   - {$result->getFullName()}\n";
    }
}

if (count($byServerity['medium']) > 0) {
    echo "\n⚠️  REVISAR - Requiere revisión manual:\n";
    foreach ($byServerity['medium'] as $result) {
        echo "   - {$result->getFullName()}\n";
    }
}

echo "\n✓ VÁLIDOS:\n";
foreach ($byServerity['valid'] as $result) {
    echo "   - {$result->getFullName()}\n";
}
```

---

## Integración con Base de Datos

### Validar al Registrar un Usuario

```php
// Simulación de registro de usuario
class UserRegistry {
    private DefamatoryContentReviewer $reviewer;
    
    public function __construct(DefamatoryContentReviewer $reviewer)
    {
        $this->reviewer = $reviewer;
    }
    
    public function register(string $firstName, string $lastName): bool
    {
        // Validar nombre
        $result = $this->reviewer->validateFullName($firstName, $lastName);
        
        if (!$result->isValid()) {
            $report = $this->reviewer->getDetailedReport($result);
            
            switch ($report['severity']) {
                case 'high':
                    throw new \Exception("Nombre rechazado: " . $report['recommendation']);
                case 'medium':
                    \log_warning("Nombre potencialmente ofensivo registrado: {$report['name']}");
                    break;
                case 'low':
                    \log_info("Nombre con advertencia registrado: {$report['name']}");
                    break;
            }
        }
        
        // Proceder con registro
        return $this->saveToDatabase($firstName, $lastName);
    }
    
    private function saveToDatabase(string $firstName, string $lastName): bool
    {
        // Guardar en BD
        return true;
    }
}

// Uso
$registry = new UserRegistry($reviewer);

try {
    $registry->register('Juan', 'Pérez');  // ✓ Funciona
    $registry->register('Zoila', 'Cerda'); // Advertencia
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Validar Registros Existentes en BD

```php
// Buscar registros problemáticos
class AuditManager {
    private DefamatoryContentReviewer $reviewer;
    private \PDO $db;
    
    public function __construct(DefamatoryContentReviewer $reviewer, \PDO $db)
    {
        $this->reviewer = $reviewer;
        $this->db = $db;
    }
    
    public function auditAllUsers(): array
    {
        $stmt = $this->db->query("SELECT id, first_name, last_name FROM users");
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $problematicUsers = [];
        
        foreach ($users as $user) {
            $result = $this->reviewer->validateFullName(
                $user['first_name'],
                $user['last_name']
            );
            
            if (!$result->isValid()) {
                $problematicUsers[] = [
                    'id' => $user['id'],
                    'fullName' => "{$user['first_name']} {$user['last_name']}",
                    'severity' => $result->getSeverity(),
                    'flaggedTerms' => $result->getFlaggedTerms(),
                ];
            }
        }
        
        return $problematicUsers;
    }
    
    public function generateAuditReport(): void
    {
        $problematic = $this->auditAllUsers();
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'totalUsers' => $this->getTotalUsers(),
            'problematicUsers' => count($problematic),
            'bySeverity' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
            'details' => $problematic,
        ];
        
        foreach ($problematic as $user) {
            $report['bySeverity'][$user['severity']]++;
        }
        
        // Guardar reporte
        file_put_contents(
            "audit_" . date('Y-m-d_H-i-s') . ".json",
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        echo "Reporte generado\n";
        echo "Total usuarios problemáticos: " . count($problematic) . "\n";
    }
    
    private function getTotalUsers(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }
}
```

---

## Personalización de Palabras

### Agregar Nuevas Palabras

```php
// 1. Modificar directamente la configuración
$config = require 'config/defamatory-words.php';

$config['insultos_personales'][] = 'palabra_nueva';
$config['insultos_personales'][] = 'otra_palabra';

$wordList = new WordList($config);
$reviewer = new DefamatoryContentReviewer($wordList);
```

### Crear Archivo de Extensión

```php
// config/defamatory-words-extended.php
<?php

$baseConfig = require __DIR__ . '/defamatory-words.php';

$extended = array_merge_recursive($baseConfig, [
    'insultos_personales' => [
        'insulto_regional_1',
        'insulto_regional_2',
    ],
    'burlas_personalizadas' => [
        'broma_local',
    ],
]);

return $extended;
```

### Cambiar Severidad de Palabras

```php
$config = require 'config/defamatory-words.php';

$wordList = new WordList($config);
$reviewer = new DefamatoryContentReviewer($wordList);

// Considerar ciertos insultos como de severidad alta
$reviewer->setHighSeverityCategories([
    'insultos_morales',
    'palabras_soeces',
    'insultos_personales', // Agregado
]);

$result = $reviewer->validateName('idiota');
// Ahora será 'high' en lugar de 'medium'
```

---

## Mejores Prácticas

### 1. Siempre Usar Try-Catch

```php
try {
    $result = $reviewer->validateFullName($firstName, $lastName);
    // Procesar resultado
} catch (\Exception $e) {
    \log_error("Error en validación: " . $e->getMessage());
}
```

### 2. Cachear Resultados

```php
class CachedReviewer {
    private DefamatoryContentReviewer $reviewer;
    private array $cache = [];
    
    public function validateFullName(string $firstName, string $lastName)
    {
        $key = strtolower($firstName . ' ' . $lastName);
        
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        $result = $this->reviewer->validateFullName($firstName, $lastName);
        $this->cache[$key] = $result;
        
        return $result;
    }
}
```

### 3. Registrar Validaciones

```php
class LoggingReviewer {
    private DefamatoryContentReviewer $reviewer;
    
    public function validateFullName(string $firstName, string $lastName)
    {
        $result = $this->reviewer->validateFullName($firstName, $lastName);
        
        \log_debug("Validación: {$firstName} {$lastName} - " . 
                   ($result->isValid() ? 'Válido' : $result->getSeverity()));
        
        return $result;
    }
}
```

### 4. Permitir Apelaciones

```php
class AppealableReviewer {
    private DefamatoryContentReviewer $reviewer;
    private array $approved = [];
    
    public function isApproved(string $firstName, string $lastName): bool
    {
        $key = strtolower($firstName . ' ' . $lastName);
        return isset($this->approved[$key]);
    }
    
    public function approve(string $firstName, string $lastName): void
    {
        $key = strtolower($firstName . ' ' . $lastName);
        $this->approved[$key] = true;
        \log_info("Nombre aprobado manualmente: {$firstName} {$lastName}");
    }
    
    public function validateFullName(string $firstName, string $lastName)
    {
        if ($this->isApproved($firstName, $lastName)) {
            $result = new \DefamatoryContentReview\ValidationResult(
                "{$firstName} {$lastName}",
                true
            );
            return $result;
        }
        
        return $this->reviewer->validateFullName($firstName, $lastName);
    }
}
```

---

## FAQ

### ¿Qué pasa con nombres históricos que ahora son ofensivos?

El módulo está diseñado para ser flexible. Puedes usar el sistema de apelaciones para permitir nombres históricos que se consideren válidos en contexto genealógico.

### ¿Funciona con otros idiomas además de español?

Funciona parcialmente. La normalización de acentos está optimizada para español, pero la lógica de detección funciona con cualquier idioma si modificas la lista de palabras.

### ¿Cómo manejo nombres con guiones?

El módulo maneja automáticamente nombres con guiones (ej: "María-José"). La normalización divide por espacios, guiones y puntos.

### ¿Puedo agregar palabras dinámicamente?

Sí, crea un nuevo WordList con la configuración extendida:

```php
$config['nuevaCategoria'] = ['palabra1', 'palabra2'];
$newWordList = new WordList($config);
$newReviewer = new DefamatoryContentReviewer($newWordList);
```

### ¿Es case-sensitive la búsqueda?

No, la búsqueda es case-insensitive y también trata accents de forma insensible.

### ¿Qué tan rápido es?

Para validaciones únicas: < 1ms  
Para validación de 1000 nombres: < 100ms

### ¿Puedo exportar los resultados?

Sí, usa el método `toArray()`:

```php
$result = $reviewer->validateFullName('Zoila', 'Cerda');
$json = json_encode($result->toArray(), JSON_UNESCAPED_UNICODE);
```

---

## Soporte

Para más información, consulta:
- `README.md` - Documentación general
- `examples/usage.php` - Ejemplos de código
- `tests/` - Tests para entender el comportamiento
