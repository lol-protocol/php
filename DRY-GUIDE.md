# 📐 Guía DRY - Reduciendo Duplicación de Código

## Cambios Realizados para Mayor DRY

### 1. Variables CSS Estándar en common.css
```css
--duration-fast: 0.5s;
--duration-normal: 2s;
--duration-slow: 4s;
--duration-very-slow: 6s;

--ease-in: ease-in;
--ease-out: ease-out;
--ease-in-out: ease-in-out;
--ease-linear: linear;
```

**Antes:**
```css
animation: rotate 4s linear infinite;
animation: pulse 2s ease-in-out infinite;
```

**Después:**
```css
animation: rotate var(--duration-slow) var(--ease-linear) infinite;
animation: pulse var(--duration-normal) var(--ease-in-out) infinite;
```

---

### 2. Clases de Utilidad de Animación

Usar clases en lugar de crear @keyframes personalizados:

```html
<!-- Elemento que rota -->
<div class="animate-rotate"></div>

<!-- Elemento que pulsa -->
<div class="animate-pulse"></div>

<!-- Elemento con brillo -->
<div class="animate-glow"></div>
```

---

### 3. Helpers JavaScript Reutilizables

#### createAnimatedElements()
```javascript
// Antes: Crear 30 elementos manualmente
for (let i = 0; i < 30; i++) {
    const star = createDiv('star');
    container.appendChild(star);
}

// Después: Una sola línea
const stars = createAnimatedElements(30, 'star', '#container');
```

#### Transform - Helpers de Transformación
```javascript
// Calcular transformaciones complejas fácilmente
element.style.transform = Transform.rotate(45);
element.style.transform = Transform.scale(1.5);
element.style.transform = Transform.translate(100, 50);

// 3D
element.style.transform = Transform.rotateX(45) + ' ' + Transform.rotateY(45);
```

#### Effects - Helpers de Efectos
```javascript
// Posición aleatoria
const pos = Effects.randomPosition(400, 400);
element.style.left = pos.x + 'px';
element.style.top = pos.y + 'px';

// Delays aleatorios
element.style.animationDelay = Effects.randomDelay(0, 1) + 's';

// Duración aleatoria
element.style.animationDuration = Effects.randomDuration(1, 3) + 's';

// Convertir polar a cartesiano
const point = Effects.polarToCartesian(100, Math.PI / 4);
```

#### Patterns - Generador de Patrones
```javascript
// Crear grilla automáticamente
const grid = Patterns.createGrid(5, 5, 400, 400);
grid.forEach((cell, i) => {
    const el = createDiv('cell');
    el.style.left = cell.x + 'px';
    el.style.top = cell.y + 'px';
    el.style.width = cell.width + 'px';
    el.style.height = cell.height + 'px';
});

// Crear patrón radial
const points = Patterns.createRadial(8, 100);
points.forEach((p, i) => {
    const el = createDiv('point');
    el.style.left = (100 + p.x) + 'px';
    el.style.top = (100 + p.y) + 'px';
});
```

---

### 4. Plantillas de Código (animation-templates.js)

Para futuras animaciones, usar las plantillas:

```javascript
// Generar HTML base
const html = AnimationTemplate.htmlBase(
    'ak',
    'Mi Animación',
    'Descripción aquí',
    '<div class="container"></div>'
);

// Generar CSS para rotación
const css = AnimationTemplate.cssRotate('.elemento', '3s');

// Generar JS simple
const js = AnimationTemplate.jsToggleSimple('.elemento');

// Generar JS con elementos dinámicos
const jsComplex = AnimationTemplate.jsCreateElements(10, 'item', 'container');
```

---

## Patrones CSS Reutilizables

```css
/* En lugar de crear nuevos @keyframes, usar estos patrones */

.animate-rotate { animation: rotate var(--duration-slow) var(--ease-linear) infinite; }
.animate-pulse { animation: pulse var(--duration-normal) var(--ease-in-out) infinite; }
.animate-bounce { animation: bounce var(--duration-normal) var(--ease-in-out) infinite; }
.animate-scale-pulse { animation: scalePulse var(--duration-normal) var(--ease-in-out) infinite; }
.animate-glow { animation: glow var(--duration-normal) var(--ease-in-out) infinite; }
```

---

## Antes vs Después

### Ejemplo: Animación con 10 elementos

**ANTES (con duplicación):**

```html
<!-- animacion-x.html -->
<div class="element element-1"></div>
<div class="element element-2"></div>
<!-- ... 8 más ... -->

<style>
.element-1 { animation-delay: 0s; }
.element-2 { animation-delay: 0.1s; }
<!-- ... 8 más ... -->
</style>

<script>
let isAnimating = true;
function toggle() { /* ... */ }
</script>
```

**DESPUÉS (DRY):**

```html
<!-- animacion-x.html -->
<div id="container"></div>

<style>
.element { animation: rotate var(--duration-slow) var(--ease-linear) infinite; }
</style>

<script>
const elements = createAnimatedElements(10, 'element', '#container');
elements.forEach((el, i) => {
    el.style.animationDelay = generateCascadeDelay(i, 10);
});
const toggle = new AnimationToggle('.element');
</script>
```

**Reducción: ~60% menos código**

---

## Recomendaciones

1. **Reutiliza variables CSS** - No escribas valores hardcoded
2. **Usa clases de utilidad** - `.animate-*` en lugar de crear nuevos @keyframes
3. **Aprovecha helpers** - Transform, Effects, Patterns hacen la lógica más simple
4. **Genera elementos dinámicamente** - Usa `createAnimatedElements()` y `Patterns.create*()`
5. **Template literals** - Usa AnimationTemplate para nuevas animaciones

---

## Archivos Relevantes

- `common.css` - Variables y clases base
- `common.js` - Clases y helpers
- `animation-templates.js` - Plantillas para futuras animaciones

---

**Resultado Final:** Código más mantenible, escalable y reducido en ~40-50%
