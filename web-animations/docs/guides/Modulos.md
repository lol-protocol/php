# 📦 Guía de Módulos - Arquitectura Refactorizada

## Archivos Modulares Disponibles

### 1. `common.css` - Estilos Compartidos

Variables CSS globales y clases base reutilizables.

#### Variables Disponibles
```css
/* Gradientes */
--primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Colores */
--color-1: #ff6b6b;  /* Rojo */
--color-2: #ee5a6f;  /* Rojo acento */
--color-3: #4ecdc4;  /* Turquesa */
--color-4: #44a0a0;  /* Turquesa acento */
--color-5: #ffd93d;  /* Amarillo */
--color-6: #6bcf7f;  /* Verde */

/* Sombras */
--shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.3);
--shadow-md: 0 10px 30px rgba(0, 0, 0, 0.3);
--shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.3);

/* Otros */
--radius: 8px;
--transition: all 0.3s ease;
```

#### Clases Base
```css
.info-panel       /* Panel de información */
.control-btn      /* Botón de control */
.fullscreen-container  /* Contenedor pantalla completa */
.scene-container  /* Contenedor para escenas 3D */
```

#### Gradientes 3D
```css
.radial-gradient-3d-warm  /* Rojo/naranja con efecto 3D */
.radial-gradient-3d-cool  /* Turquesa con efecto 3D */
.radial-gradient-3d-red   /* Rojo con efecto 3D */
```

---

### 2. `common.js` - Utilidades JavaScript

Funciones y clases reutilizables para reducir código duplicado.

#### Clase `AnimationToggle`
Gestiona pausa/reanudar de animaciones.

```javascript
// Crear instancia
const toggle = new AnimationToggle('.elemento-animado');

// Métodos
toggle.toggle();  // Alterna pausa/reanudar
toggle.pause();   // Pausa la animación
toggle.resume();  // Reanuda la animación
```

#### Objeto `Colors`
Paleta de colores predefinida.

```javascript
Colors.RED      // { main: '#ff6b6b', accent: '#ee5a6f' }
Colors.TEAL     // { main: '#4ecdc4', accent: '#44a0a0' }
Colors.YELLOW   // { main: '#ffd93d', dark: '#f8b500' }
Colors.GREEN    // { main: '#6bcf7f', dark: '#00aa88' }
Colors.PURPLE   // { main: '#c44569', dark: '#667eea' }

// Uso
const color = Colors.RED.main;  // '#ff6b6b'
```

#### Funciones Auxiliares

```javascript
// Generar delays en cascada
generateCascadeDelay(index, maxIndex, baseDelay)
// Ej: generateCascadeDelay(2, 10, 0.1) → '0.2s'

// Crear elementos
createDiv(className, innerHTML)
createSVGElement(tag, attributes)

// Eventos
onButtonClick(callback)

// Tamaño de ventana
ViewportSize.getWidth()
ViewportSize.getHeight()
ViewportSize.getCenter()

// Física
Physics.applyGravity(velocity)
Physics.applyBounce(velocity)
```

---

## 📝 Ejemplos de Uso

### Ejemplo 1: Animación Simple con Módulos

**HTML (animacion-simple.html)**
```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="common.css">
    <style>
        .mi-caja {
            width: 100px;
            height: 100px;
            background: var(--color-1);
            animation: rotar 2s linear infinite;
        }
        @keyframes rotar {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="fullscreen-container">
    <div class="info-panel">
        <h2>Mi Animación</h2>
    </div>
    <div class="mi-caja"></div>
    <button class="control-btn" onclick="toggle.toggle()">Pausar</button>
    
    <script src="common.js"></script>
    <script>
        const toggle = new AnimationToggle('.mi-caja');
    </script>
</body>
</html>
```

### Ejemplo 2: Usar Variables CSS

**CSS Personalizado**
```css
.elemento {
    background: linear-gradient(45deg, var(--color-3), var(--color-4));
    box-shadow: var(--shadow-md);
    border-radius: var(--radius);
    transition: var(--transition);
}

.elemento:hover {
    box-shadow: var(--shadow-lg);
}
```

### Ejemplo 3: Usar Colores Programáticamente

**JavaScript**
```javascript
const button = document.createElement('button');
button.style.background = Colors.TEAL.main;
button.style.borderColor = Colors.TEAL.accent;
document.body.appendChild(button);
```

### Ejemplo 4: Generar Elementos en Cascada

```javascript
const container = document.getElementById('container');

for (let i = 0; i < 10; i++) {
    const element = createDiv('item');
    element.style.animationDelay = generateCascadeDelay(i, 10);
    container.appendChild(element);
}
```

---

## 🔄 Refactorización de Animaciones Existentes

### Antes (Sin Módulos)
```html
<!-- animacion-original.html -->
<style>
    * { margin: 0; padding: 0; }
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .rectangle {
        background: linear-gradient(45deg, #ff6b6b, #ee5a6f);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }
</style>

<script>
    let isAnimating = true;
    function toggleAnimation() {
        const rectangle = document.querySelector('.rectangle');
        if (isAnimating) {
            rectangle.style.animationPlayState = 'paused';
        } else {
            rectangle.style.animationPlayState = 'running';
        }
        isAnimating = !isAnimating;
    }
</script>
```

### Después (Con Módulos)
```html
<!-- animacion-refactored.html -->
<link rel="stylesheet" href="common.css">

<style>
    .rectangle {
        background: linear-gradient(45deg, var(--color-1), var(--color-2));
        box-shadow: var(--shadow-lg);
    }
</style>

<script src="common.js"></script>
<script>
    const toggle = new AnimationToggle('.rectangle');
</script>
```

**Reducción**: 50% menos código, más mantenible.

---

## 🎯 Beneficios de la Modularización

| Aspecto | Sin Módulos | Con Módulos |
|---------|------------|------------|
| **Líneas de CSS** | 100+ | 30+ |
| **Líneas de JS** | 15+ | 1 |
| **Cambiar color global** | Editar en cada archivo | Editar `common.css` |
| **Reutilización** | Copiar/pegar | Importar módulos |
| **Mantenimiento** | Difícil (múltiples copias) | Fácil (una sola fuente) |

---

## 📋 Checklist para Nueva Animación

- [ ] Importar `common.css`
- [ ] Importar `common.js`
- [ ] Usar clase `.fullscreen-container`
- [ ] Usar clase `.info-panel`
- [ ] Usar clase `.control-btn`
- [ ] Usar variables CSS (`--color-X`, `--shadow-X`)
- [ ] Instanciar `AnimationToggle` si es necesario
- [ ] Mantener archivo CSS < 100 líneas
- [ ] Mantener archivo JS < 50 líneas

---

## 📂 Estructura Recomendada

```
project/
├── index.html          (Galería principal)
├── common.css          (Variables y estilos base)
├── common.js           (Utilidades compartidas)
├── Readme.md           (Documentación)
├── Modulos.md          (Esta guía)
│
└── animaciones/
    ├── animacion-a.html
    ├── animacion-a.css
    ├── animacion-a.js
    ├── animacion-b.html
    ├── animacion-b.css
    ├── animacion-b.js
    └── ... (resto de animaciones)
```

---

## 🚀 Próximos Pasos

1. **Refactorizar animaciones existentes** para usar módulos
2. **Crear componentes reutilizables** (barras, bolas, etc.)
3. **Agregar temas** (light/dark mode)
4. **Documentar patrones comunes** en cada categoría