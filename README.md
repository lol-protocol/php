# Módulo de Revisión de Contenido Difamatorio

Un módulo PHP robusto para detectar y validar contenido difamatorio, insultos y palabras inapropiadas en nombres de personas para plataformas genealógicas.

## Características

✓ **Detección de Contenido Difamatorio**: Identifica insultos, palabras soeces y términos ofensivos  
✓ **Análisis Sensible al Contexto**: Diferencia entre severidad alta, media y baja  
✓ **Normalización Inteligente**: Maneja mayúsculas, acentos y espacios  
✓ **Validación por Lotes**: Procesa múltiples nombres eficientemente  
✓ **Reportes Detallados**: Proporciona recomendaciones y categorización  
✓ **Fácil Integración**: API simple y clara  

## Estructura del Proyecto

```
src/
  DefamatoryContentReview/
    DefamatoryContentReviewer.php    # Clase principal de validación
    WordList.php                      # Gestor de lista de palabras
    ValidationResult.php              # Resultado de validación
config/
  defamatory-words.php               # Lista de palabras inapropiadas
tests/
  DefamatoryContentReviewTest.php    # Tests unitarios
examples/
  usage.php                          # Ejemplos de uso
```

## Instalación

### Requisitos

- PHP >= 8.0
- Composer (opcional, para dependencias)

### Setup

```bash
composer install
```

## Uso Rápido

### Validación Simple de Nombre Completo

```php
require_once __DIR__ . '/vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\WordList;

$config = require __DIR__ . '/config/defamatory-words.php';
$wordList = new WordList($config);
$reviewer = new DefamatoryContentReviewer($wordList);

$result = $reviewer->validateFullName('Zoila', 'Cerda');

if ($result->isValid()) {
    echo "Nombre válido";
} else {
    echo "Nombre contiene contenido inapropiado: " . $result->getSeverity();
}
```

### Validación de Nombre Único

```php
$result = $reviewer->validateName('Juan');

if ($result->isValid()) {
    echo "Nombre válido";
}
```

### Validación por Lotes

```php
$names = [
    'Juan Pérez',
    'Zoila Cerda',
    'María González',
];

$results = $reviewer->batchValidateFullNames($names);

foreach ($results as $result) {
    echo $result->getFullName() . ": " . ($result->isValid() ? "OK" : "PROBLEMA") . "\n";
}
```

### Obtener Reporte Detallado

```php
$result = $reviewer->validateFullName('Zoila', 'Cerda');
$report = $reviewer->getDetailedReport($result);

echo "Nombre: " . $report['name'] . "\n";
echo "Válido: " . ($report['valid'] ? 'Sí' : 'No') . "\n";
echo "Severidad: " . $report['severity'] . "\n";
echo "Recomendación: " . $report['recommendation'] . "\n";

foreach ($report['flaggedTerms'] as $term) {
    echo "- Término: {$term['found']} (Categoría: {$term['category']})\n";
}
```

## API Detallada

### DefamatoryContentReviewer

#### `validateFullName(string $firstName, string $lastName): ValidationResult`

Valida un nombre completo (nombre y apellido).

**Parámetros:**
- `$firstName`: Nombre de pila
- `$lastName`: Apellido

**Retorna:** `ValidationResult`

#### `validateName(string $name): ValidationResult`

Valida un nombre único.

**Parámetros:**
- `$name`: Nombre a validar

**Retorna:** `ValidationResult`

#### `batchValidateNames(array $names): array`

Valida múltiples nombres.

**Parámetros:**
- `$names`: Array de nombres a validar

**Retorna:** Array de `ValidationResult`

#### `batchValidateFullNames(array $fullNames): array`

Valida múltiples nombres completos (nombre apellido).

**Parámetros:**
- `$fullNames`: Array de nombres completos

**Retorna:** Array de `ValidationResult`

#### `getDetailedReport(ValidationResult $result): array`

Obtiene un reporte detallado de validación.

**Parámetros:**
- `$result`: Resultado de validación

**Retorna:** Array con detalles de validación

### ValidationResult

#### `isValid(): bool`

Indica si el nombre es válido.

#### `getSeverity(): string`

Retorna el nivel de severidad: `none`, `low`, `medium`, `high`.

#### `getFlaggedTerms(): array`

Retorna array de términos problemáticos encontrados.

#### `getFlaggedCategories(): array`

Retorna array de categorías de problemas detectadas.

#### `toArray(): array`

Convierte el resultado a array.

### WordList

#### `normalize(string $word): string`

Normaliza una palabra (minúsculas, sin acentos, sin espacios).

#### `search(string $word): ?array`

Busca una palabra en la lista.

#### `findInText(string $text): array`

Busca todos los términos problemáticos en un texto.

## Categorías de Palabras

El módulo organiza las palabras en las siguientes categorías:

- **insultos_personales**: Insultos generales (idiota, bobo, tonto, etc.)
- **insultos_corporales**: Insultos sobre aspectos físicos discapacitantes
- **insultos_morales**: Insultos sobre moralidad (bastardo, canalla, etc.)
- **insultos_apariencia**: Insultos sobre apariencia física
- **insultos_capacidad**: Insultos sobre habilidades (zurdo, etc.)
- **burlas_ridiculas**: Burlas sobre edad, madurez, etc.
- **palabras_soeces**: Palabras vulgares y obscenas
- **insultos_inteligencia**: Insultos sobre inteligencia (analfabeto, ignorante, etc.)
- **insultos_comportamiento**: Insultos sobre comportamiento (mentiroso, corrupto, etc.)

## Niveles de Severidad

| Severidad | Descripción |
|-----------|-------------|
| `none` | Sin problemas detectados |
| `low` | Contiene palabras que podrían ser consideradas inapropiadas |
| `medium` | Contiene términos potencialmente ofensivos |
| `high` | Contiene insultos graves o palabras inapropiadas |

## Ejemplos Reales

### Ejemplo 1: Insulto Clásico

```
Entrada: "Zoila" + "Cerda"
Resultado: NO VÁLIDO
Severidad: medium
Términos: [cerda → insultos_personales]
```

### Ejemplo 2: Ridiculización

```
Entrada: "Zurdo" + "Diestro"
Resultado: NO VÁLIDO
Severidad: medium
Términos: [zurdo → insultos_capacidad, diestro → insultos_capacidad]
```

### Ejemplo 3: Nombre Limpio

```
Entrada: "Juan" + "Pérez"
Resultado: VÁLIDO
Severidad: none
```

## Tests

Ejecutar tests unitarios:

```bash
./vendor/bin/phpunit tests/
```

El módulo incluye 15+ tests que cubren:

- ✓ Validación de nombres limpios
- ✓ Detección de insultos en nombre y apellido
- ✓ Insensibilidad a mayúsculas/minúsculas
- ✓ Insensibilidad a acentos
- ✓ Detección de múltiples insultos
- ✓ Clasificación de severidad
- ✓ Validación por lotes
- ✓ Generación de reportes
- ✓ Normalización de palabras
- ✓ Y más...

## Personalización

### Cambiar Categorías de Severidad

```php
$reviewer->setHighSeverityCategories([
    'insultos_morales',
    'palabras_soeces',
    'insultos_corporales', // Agregado como severidad alta
]);

$reviewer->setMediumSeverityCategories([
    'insultos_personales',
    'insultos_comportamiento',
]);
```

### Agregar Palabras Personalizadas

```php
$customConfig = require 'config/defamatory-words.php';
$customConfig['insultos_personales'][] = 'palabra_personalizada';

$wordList = new WordList($customConfig);
$reviewer = new DefamatoryContentReviewer($wordList);
```

## Consideraciones Importantes

⚠️ **Limitaciones:**

1. La detección es a nivel de palabra completa, no detecta palabras dentro de palabras
2. El módulo está optimizado para español, pero funciona con otros idiomas
3. La lista de palabras es extensible pero no exhaustiva
4. Algunos contextos pueden requerir revisión manual

⚠️ **Recomendaciones:**

1. Usar en combinación con revisión manual para nombres con ambigüedad
2. Permitir apelación de usuarios si un nombre es rechazado incorrectamente
3. Actualizar regularmente la lista de palabras basado en feedback
4. Considerar el contexto cultural y regional

## Rendimiento

- Normalización: O(1) por palabra
- Búsqueda: O(1) con hash interno
- Validación de nombre: O(n) donde n = número de palabras en el nombre
- Validación por lotes: O(m*n) donde m = número de nombres, n = palabras por nombre

Para 1000 nombres con promedio de 2 palabras cada uno: < 100ms

## Seguridad

- No almacena datos de usuarios
- No realiza queries externas
- Normalización segura de caracteres Unicode
- Función de búsqueda de texto sin inyección

## Licencia

MIT

## Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/mi-feature`)
3. Commit tus cambios (`git commit -am 'Add new feature'`)
4. Push a la rama (`git push origin feature/mi-feature`)
5. Abre un Pull Request

## Soporte

Para reportar bugs o sugerir mejoras, abre un issue en el repositorio.
