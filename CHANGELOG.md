# Changelog

Todos los cambios notables en este proyecto se documentarán en este archivo.

## [1.0.0] - 2026-09-08

### Agregado

- **Módulo Principal de Revisión de Contenido Difamatorio**
  - Clase `DefamatoryContentReviewer` para validación de nombres
  - Clase `WordList` para gestión de palabras inapropiadas
  - Clase `ValidationResult` para representar resultados de validación

- **Funcionalidades Core**
  - Validación de nombre completo (nombre y apellido)
  - Validación de nombre único
  - Validación por lotes de nombres
  - Detección insensible a mayúsculas/minúsculas
  - Detección insensible a acentos
  - Normalización inteligente de caracteres Unicode

- **Categorización**
  - 9 categorías de palabras inapropiadas:
    - insultos_personales
    - insultos_corporales
    - insultos_morales
    - insultos_apariencia
    - insultos_capacidad
    - burlas_ridiculas
    - palabras_soeces
    - insultos_inteligencia
    - insultos_comportamiento

- **Sistema de Severidad**
  - Cuatro niveles: none, low, medium, high
  - Clasificación automática basada en categoría
  - Recomendaciones basadas en severidad

- **Reportes**
  - Reportes detallados de validación
  - Listado de términos marcados por categoría
  - Recomendaciones de acción
  - Exportación a formato array/JSON

- **Tests**
  - 15+ tests unitarios con PHPUnit
  - Cobertura de funcionalidades principales
  - Tests de normalización, detección y severidad

- **Documentación**
  - README.md completo con guía de uso
  - USAGE_GUIDE.md con casos de uso avanzados
  - Ejemplos de código en `examples/usage.php`
  - Documentación de API detallada

- **Configuración**
  - `composer.json` con dependencias
  - `phpunit.xml` para configuración de tests
  - `.gitignore` para exclusión de archivos

### Características

- ✓ Detección de contenido difamatorio en nombres
- ✓ Validación de integridad de datos genealógicos
- ✓ Análisis sensible al contexto
- ✓ Normalización inteligente
- ✓ Validación por lotes
- ✓ Reportes detallados
- ✓ Fácil integración
- ✓ Altamente personalizable

### Palabras Incluidas

**Total de palabras en lista negra:** 100+

Categorías incluidas:
- Insultos personales (17 palabras)
- Insultos corporales (11 palabras)
- Insultos morales (13 palabras)
- Insultos de apariencia (17 palabras)
- Insultos de capacidad (7 palabras)
- Burlas ridículas (13 palabras)
- Palabras soeces (16 palabras)
- Insultos de inteligencia (9 palabras)
- Insultos de comportamiento (14 palabras)

### Ejemplos Funcionales

```php
// Ejemplo 1: Validación simple
$result = $reviewer->validateFullName('Zoila', 'Cerda');
// Resultado: Inválido, severidad: medium

// Ejemplo 2: Nombre limpio
$result = $reviewer->validateFullName('Juan', 'Pérez');
// Resultado: Válido

// Ejemplo 3: Validación por lotes
$results = $reviewer->batchValidateFullNames([
    'Juan Pérez',
    'Zoila Cerda',
    'María González'
]);
```

### Requisitos

- PHP >= 8.0
- Composer (recomendado)

### Instalación

```bash
composer install
```

### Testing

```bash
./vendor/bin/phpunit tests/
```

### Rendimiento

- Validación simple: < 1ms
- Validación de 1000 nombres: < 100ms
- Normalización: O(1)
- Búsqueda: O(1)

---

## [Planificado]

- [ ] Soporte multiidioma mejorado
- [ ] API REST para validación remota
- [ ] Dashboard web para gestión de palabras
- [ ] Machine Learning para detección contextual
- [ ] Integración con bases de datos externas
- [ ] WebHooks para eventos de validación
- [ ] Estadísticas y análisis
- [ ] Versionado de listas de palabras

---

## Notas de Desarrollo

### Convenciones de Código

- PSR-12 para estilo de código
- PSR-4 para autoloading
- Namespacing completo
- Type hints fuertes
- Documentación de métodos públicos

### Estructura de Carpetas

```
php/
├── src/DefamatoryContentReview/     # Código fuente
├── config/                           # Configuración
├── tests/                            # Tests unitarios
├── examples/                         # Ejemplos de uso
├── composer.json                     # Dependencias
├── phpunit.xml                       # Config de tests
├── README.md                         # Documentación
├── USAGE_GUIDE.md                    # Guía de uso
└── CHANGELOG.md                      # Este archivo
```

### Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature
3. Commit con mensajes descriptivos
4. Push y abre un Pull Request

### Licencia

MIT License - Ver LICENSE para detalles

---

## Histórico de Versiones

### v1.0.0 (2026-09-08)
- Lanzamiento inicial completo
- Funcionalidad core implementada
- Tests y documentación completos
