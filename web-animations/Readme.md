# 🎬 Animaciones Web - Galería Completa

Colección de **22 animaciones web** modularizadas con HTML, CSS y JavaScript separados.

## 📁 Estructura de Archivos

### Archivos Comunes (Módulos Reutilizables)

```
common.css      → Variables CSS, estilos base, animaciones compartidas
common.js       → Utilidades, clases reutilizables, helpers
index.html      → Página de inicio con galería de enlaces
```

### Animaciones (A-V)

Cada animación está compuesta por 3 archivos:
```
animacion-X.html  → Estructura HTML
animacion-X.css   → Estilos específicos
animacion-X.js    → Lógica/interactividad
```

## 🎨 Arquitectura de Módulos

### `common.css`
Define variables CSS globales y clases reutilizables:
- **Variables**: colores, sombras, gradientes, transiciones
- **Clases Base**: `.info-panel`, `.control-btn`, `.fullscreen-container`
- **Gradientes 3D**: presets para efectos visuales
- **Animaciones**: keyframes reutilizables

### `common.js`
Proporciona utilidades y clases para reducir duplicación:

```javascript
// Clase para pausar/reanudar animaciones
const toggle = new AnimationToggle('.elemento-animado');
toggle.toggle(); // Alterna pausa/reanudar

// Colores predefinidos
Colors.RED.main      // '#ff6b6b'
Colors.TEAL.accent   // '#44a0a0'

// Helpers
createDiv(className, innerHTML)
generateCascadeDelay(index, maxIndex)
ViewportSize.getWidth()
```

## 🚀 Cómo Usar

### Opción 1: Abrir una Animación Directamente
```bash
# Abre cualquier animación
animacion-a.html
animacion-k.html
```

### Opción 2: Galería Interactiva
```bash
# Abre la página de inicio
index.html
```

### Opción 3: Crear Nueva Animación Usando Módulos

```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="common.css">
</head>
<body>
    <div class="info-panel">
        <h2>Mi Animación</h2>
    </div>
    <div class="mi-animacion"></div>
    <button onclick="toggle.toggle()">Pausar/Reanudar</button>
    
    <script src="common.js"></script>
    <script>
        const toggle = new AnimationToggle('.mi-animacion');
    </script>
</body>
</html>
```

## 📊 Categorías de Animaciones

| Categoría | Animaciones | Descripción |
|-----------|-------------|-------------|
| 🎲 Geometría & 3D | A, C, D, E, F | Transformaciones 3D, formas, cubos |
| 💫 Movimiento & Física | B, G, H, I, J | Física realista, rebotes, caídas |
| 〰️ Patrones & Ondas | K, L, M, N | Ondas, grillas, patrones |
| ✨ Efectos Especiales | O, P, Q, R | Tinta, tela, aurora, matriz |
| ⚙️ Mecánicos | S, T, U, V | Dominó, engranajes, reloj, brújula |

## 🎯 Ventajas de la Modularización

✅ **DRY (Don't Repeat Yourself)**
- Variables CSS compartidas
- Clases reutilizables
- Funciones comunes

✅ **Mantenimiento Fácil**
- Cambiar un color: editar `common.css`
- Actualizar lógica común: editar `common.js`

✅ **Escalabilidad**
- Agregar nuevas animaciones rápidamente
- Reutilizar componentes

✅ **Legibilidad**
- Código más limpio
- Menos repetición
- Mejor organización

## 🔧 Variables CSS Disponibles

```css
--primary-gradient   /* Gradiente de fondo */
--color-1 a --color-6 /* Paleta de colores */
--shadow-sm/md/lg    /* Sombras predefinidas */
--radius             /* Radio de bordes */
--transition         /* Transiciones suave */
```

## 📦 Reutilización en Tus Proyectos

```html
<!-- Importa los módulos comunes -->
<link rel="stylesheet" href="common.css">
<script src="common.js"></script>

<!-- Usa las clases y utilidades -->
<div class="fullscreen-container">
    <div class="info-panel">...</div>
    <button class="control-btn">...</button>
</div>
```

## 📝 Notas Técnicas

- **Decimales**: Nunca usa 3 decimales (1, 2, 4 o 5)
- **Gradientes Radiales**: Para efecto 3D en círculos
- **Animaciones CSS**: Prefer CSS animations over JavaScript when possible
- **Responsive**: Designs adapt to mobile screens
- **Navegadores**: Requiere soporte para CSS 3D y animaciones

## 🎬 Lista Completa de Animaciones

1. **A** - Vuelo 3D
2. **B** - DVD Bouncing
3. **C** - Página Volteándose
4. **D** - Cubo 3D
5. **E** - Pirámide
6. **F** - Polígono Morphing
7. **G** - Lluvia Partículas
8. **H** - Hoja Cayendo
9. **I** - Péndulo
10. **J** - Bola en Laberinto
11. **K** - Onda Sinusoidal
12. **L** - Círculos Concéntricos
13. **M** - Grid Deformándose
14. **N** - Espiral Hipnótica
15. **O** - Tinta Derramándose
16. **P** - Tela Ondeando
17. **Q** - Aurora Boreal
18. **R** - Efecto Matriz
19. **S** - Dominó Cayendo
20. **T** - Engranajes
21. **U** - Reloj Analógico
22. **V** - Brújula

---

**Total**: 22 Animaciones | 66 Archivos de Animación | 3 Archivos Comunes | 1 Página Index