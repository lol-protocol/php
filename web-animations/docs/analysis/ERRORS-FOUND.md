# Errores Potenciales Encontrados - Refactorización DRY Phase 2

## Errores Críticos (Alto Riesgo)

### 1. **ParticleEmitterToggle - Lifecycle Inconsistency**
**Archivo**: `common.js`
**Problema**: 
- `pause()` heredado NO detiene el intervalo del emisor
- Solo `toggle()` detiene el intervalo
- Llamar directamente a `pause()` mantiene el intervalo activo
- Causa: memory leak de intervalos y comportamiento inconsistente

**Ejemplo Problemático**:
```javascript
const toggle = new ParticleEmitterToggle(createFallingStar, 200);
toggle.pause(); // Pausa CSS pero NO detiene emisor - FUGAS DE MEMORIA
```

**Impacto**: Memory leak, creación continua de elementos pausados

---

### 2. **animacion-b.js - Container puede ser NULL**
**Línea**: 38
**Problema**:
```javascript
const bounds = { width: container.offsetWidth, height: container.offsetHeight };
// Si container == null, ERROR INMEDIATO
```

**Impacto**: Crash si el elemento HTML `#bouncingContainer` no existe

---

### 3. **animacion-ah.js - Container puede ser NULL**
**Línea**: 8
**Problema**:
```javascript
container.appendChild(star); // Si container == null, ERROR
```

**Impacto**: Crash si el elemento HTML `#starRainContainer` no existe

---

### 4. **Ball.update() - Falta Gravedad**
**Archivo**: `animacion-b.js`, Línea 30-35
**Problema**:
```javascript
class Ball extends PhysicsObject {
    update() {
        this.x += this.vx;
        this.y += this.vy;
        // FALTA: this.vy += Physics.gravity;
        this.checkBounds();
        this.render();
    }
}
```

**Impacto**: Las pelotas NO caen por gravedad (comportamiento incorrecto)
**Esperado**: Comportamiento similar al DVD bouncing

---

### 5. **AnimationManager - Múltiples Loops Posibles**
**Archivo**: `common.js`, Línea 241-248
**Problema**:
```javascript
start() {
    const loop = () => {
        // ...
        this.animationId = requestAnimationFrame(loop);
    };
    loop(); // Si se llama start() 2 veces, hay 2 loops simultáneos
}
```

**Impacto**: Si se llama `start()` múltiples veces, múltiples loops corren en paralelo

---

## Errores Potenciales (Medio Riesgo)

### 6. **createAnimatedElements - Sin validación**
**Archivo**: `common.js`, Línea 70-79
**Problema**:
```javascript
const container = document.querySelector(parentSelector);
const elements = [];
for (let i = 0; i < count; i++) {
    const el = createDiv(className);
    if (position) el.style.position = position;
    container.appendChild(el); // ERROR si container == null
```

**Impacto**: Si el selector no existe, crash

---

### 7. **SVGPatterns.createRadialWeb - Sin validación**
**Archivo**: `common.js`, Línea 268-300
**Problema**:
```javascript
const SVGPatterns = {
    createRadialWeb: (container, centerX, centerY, layers, pointsPerLayer) => {
        const svg = container;
        // Sin validación si svg == null
        for (let layer = 1; layer <= layers; layer++) {
            // ...
            svg.appendChild(line); // ERROR si svg == null
```

**Impacto**: Si se pasa null como container, crash

---

### 8. **ParticleEmitterToggle - Intervalo no limpiado en resume()**
**Archivo**: `common.js`, Línea 44-51
**Problema**:
```javascript
toggle() {
    super.toggle();
    if (this.isAnimating) {
        this.startEmitting(); // Crea nuevo intervalo
    } else {
        clearInterval(this.emitterIntervalId); // Limpia intervalo
    }
}
```

Pero si se llama a `resume()` (heredado) sin pasar por `toggle()`, no se reinicia el intervalo:
```javascript
toggle.pause();   // Limpia intervalo
toggle.resume();  // NO reinicia intervalo - INCOMPLETO
```

---

### 9. **Particle clase en animacion-g.js - Constructor order**
**Archivo**: `animacion-g.js`
**Problema**: 
```javascript
class Particle extends PhysicsObject {
    constructor() {
        const size = Math.random() * 20 + 5;
        const el = createDiv('particle');
        // ... Modifica 'el'
        super(el, { /* config */ });
        // PROBLEMA: Se modifica 'el' ANTES de pasarlo a super()
```

Mejor práctica: pasar el elemento ya configurado OR configurar después

---

## Resumen de Riesgos

| Crítico | Potencial | Menor |
|---------|-----------|-------|
| 5 | 4 | 0 |

**Severidad Total**: 🔴 ALTA

Los problemas de NULL checking y lifecycle del ParticleEmitterToggle deben corregirse antes de usar en producción.
