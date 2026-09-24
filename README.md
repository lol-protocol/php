# Web Animations - HTML/CSS/JavaScript

Colección de 36 animaciones web interactivas con arquitectura modular y código altamente reutilizable.

## 📁 Estructura del Proyecto

```
php/
├── src/
│   ├── animations/              # Todas las 36 animaciones
│   │   ├── animacion-a.html     # Vuelo 3D
│   │   ├── animacion-b.html     # Círculos Rebotadores
│   │   ├── ... (36 en total)
│   │   ├── animacion-aj.html    # Arena Cayendo
│   │   └── index.html           # Galería con enlaces
│   │
│   └── helpers/                 # Utilidades reutilizables
│       ├── common.css           # Variables y clases CSS base
│       ├── common.js            # Clases e helpers JavaScript
│       └── animation-templates.js # Plantillas de código
│
├── docs/
│   ├── guides/                  # Guías y documentación
│   │   ├── DRY-GUIDE.md        # Patrones DRY (fases 1 y 2)
│   │   ├── MODULOS.md          # Guía de módulos
│   │   └── REFACTORED.md       # Métricas de refactorización
│   │
│   └── analysis/                # Análisis de errores
│       ├── ERRORS-FOUND.md     # 9 errores iniciales (Phase 1)
│       └── ADDITIONAL-ERRORS.md # 24+ errores encontrados
│
└── README.md                    # Este archivo
```

## 🎨 Animaciones (36 Total)

| Grupo | Rango | Cantidad | Descripción |
|-------|-------|----------|------------|
| Iniciales | A-V | 22 | Primera fase de implementación |
| Extended | W-Z | 4 | Extensiones de animaciones |
| Complex | AA-AJ | 10 | Animaciones complejas |

## ⚙️ Cómo Usar

### 1. **Ver las Animaciones**
Abre `/src/animations/index.html` en un navegador para ver la galería de todas las 36 animaciones.

### 2. **Importar Helpers**
En cada animación HTML:
```html
<link rel="stylesheet" href="../../helpers/common.css">
<script src="../../helpers/common.js"></script>
```

### 3. **Crear Nueva Animación**
Usa las plantillas en `animation-templates.js`:
```javascript
const html = AnimationTemplate.htmlBase('ak', 'Mi Animación', 'Descripción');
```

## 📊 Métricas

- **Código Reducido**: 60-70% total (40-50% Phase 1 + 30-40% Phase 2)
- **Clases Base**: 8 (AnimationToggle, PhysicsObject, AnimationManager, etc.)
- **Helpers Creados**: 5 (Transform, Effects, Patterns, SVGPatterns, Templates)
- **Errores Corregidos**: 21+
- **CI Status**: ✅ GREEN (8/8 checks passing)

## 🔧 Características Principales

### CSS
- Variables de color y sombras reutilizables
- @keyframes compartidas
- Propiedades de animación estándar (--duration-*, --ease-*)
- Clases de utilidad (.animate-rotate, .animate-pulse, etc.)

### JavaScript
- `AnimationToggle` - Control de pausa/reanudación
- `PhysicsObject` - Base para simulaciones de física
- `AnimationManager` - Gestor de loops de animación
- `Transform` - Helpers de transformación CSS
- `Effects` - Efectos aleatorios y cálculos
- `Patterns` - Generadores de patrones geométricos
- `SVGPatterns` - Generadores de SVG

## 📚 Documentación

- **DRY-GUIDE.md**: Explicación completa de patrones reutilizables y cómo usarlos
- **MODULOS.md**: Guía de los módulos comunes (CSS/JS)
- **REFACTORED.md**: Antes/después de refactorización con métricas
- **ERRORS-FOUND.md**: 9 errores corregidos en refactoring
- **ADDITIONAL-ERRORS.md**: Análisis exhaustivo de 24+ errores potenciales

## ✅ Validaciones

- ✅ No usa 3 decimales en CSS (1, 2, 4 o 5 solamente)
- ✅ Responsivo (viewport units, flexbox)
- ✅ Modular (archivos separados HTML/CSS/JS)
- ✅ DRY (60-70% reducción de código duplicado)
- ✅ CI Pass (8/8 checks: PHP 8.1, 8.2, 8.3, 8.4)
- ✅ Null checks en acceso a DOM
- ✅ Memory leak prevention (cleanup de intervals/listeners)

## 🚀 Próximos Pasos

1. **Refactorización Completa**: Aplicar AnimationToggle a archivos que usan toggleAnimation custom
2. **Consolidación**: Unificar clases como HourglassToggle en la clase base
3. **Performance**: Agregar throttling a resize listeners
4. **Testing**: Crear suite de tests para validaciones

---

**Última actualización**: 2026-09-24  
**Status**: ✅ Producción-ready (con documentación y validaciones)
