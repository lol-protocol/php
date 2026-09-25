# 🧪 Guía de Testing - Animaciones Web

Documentación completa de la suite de tests automáticos para validación de calidad.

## 📋 Descripción General

El proyecto incluye una suite de tests automáticos que valida:
- ✅ Carga correcta de todas las 36 animaciones
- ✅ Estructura de DOM válida
- ✅ Importación correcta de helpers
- ✅ Validación de CSS
- ✅ Accesibilidad (A11y)
- ✅ Disponibilidad de documentación

## 🚀 Cómo Ejecutar los Tests

### Opción 1: Interfaz Web (Recomendado)

La forma más fácil es abrir la página de tests en el navegador:

```bash
# Navega a la carpeta tests
cd tests/

# Abre index.html en tu navegador
# Puedes usar cualquier servidor local:
python -m http.server 8000
# o
php -S localhost:8000
```

Luego abre `http://localhost:8000/` en tu navegador.

### Opción 2: Consola del Navegador

Si tienes los archivos cargados localmente:

```javascript
// En la consola del navegador (F12 → Console)
const suite = new AnimationTestSuite();
suite.runAll().then(results => {
  console.log('Tests completados:', results);
});
```

### Opción 3: Node.js (Futuro)

Aunque actualmente los tests están diseñados para el navegador, pueden adaptarse para Node.js:

```javascript
const AnimationTestSuite = require('./animation-validation.js');
const suite = new AnimationTestSuite();
suite.runAll();
```

## 📊 Clases de Tests

### AnimationTestSuite

Suite principal que valida:

#### Tests Implementados

1. **Load Test** - Verifica que todas las 36 animaciones cargan sin errores
   - Chequea HTTP 200 para cada archivo `animacion-[a-aj].html`
   - Reporta cualquier archivo inaccesible

2. **DOM Test** - Valida estructura de elementos
   - Verifica que cada animación tiene contenedores
   - Valida que existen elementos interactivos

3. **Import Test** - Validación de importes
   - Verifica que cada HTML importa `common.css`
   - Verifica que cada HTML importa su JS correspondiente

4. **Index Test** - Validación de página de galería
   - Verifica que `index.html` es accesible
   - Valida que contiene información de las 36 animaciones
   - Verifica que tiene filtros y búsqueda

5. **Resources Test** - Validación de archivos
   - Verifica existencia de `common.css`
   - Verifica existencia de `common.js`
   - Verifica existencia de `animation-templates.js`

6. **CSS Test** - Validación de CSS
   - Verifica que NO hay valores con 3 decimales
   - Cumple con restricción de precisión decimal

7. **Data Test** - Validación de datos
   - Verifica que `data.js` contiene 36 animaciones
   - Valida estructura de datos

8. **Documentation Test** - Validación de documentos
   - Verifica existencia de guías DRY
   - Verifica existencia de análisis de errores

### AccessibilityTestSuite

Suite de accesibilidad que valida:

1. **prefers-reduced-motion** - Soporte para usuarios con sensibilidad a movimiento
   - Verifica que existen media queries para `prefers-reduced-motion`

2. **ARIA Labels** - Etiquetas accesibles
   - Valida que los botones tienen `aria-label`

3. **Color Contrast** - Contraste WCAG AA
   - Verifica ratios de contraste mínimo 4.5:1
   - Validación manual de colores principales

4. **Viewport Meta** - Configuración responsive
   - Verifica presencia de `<meta name="viewport">`

5. **Language Attribute** - Atributo de idioma
   - Valida `lang="es"` en elemento `<html>`

6. **Animation Pausable** - Animaciones pausables
   - Verifica que todas las animaciones pueden pausarse
   - Usa clase `AnimationToggle`

7. **Focus States** - Estados de enfoque
   - Valida que existen estilos `:focus` y `:focus-visible`

8. **Semantic HTML** - HTML semántico
   - Verifica uso de etiquetas como `<header>`, `<nav>`, `<section>`

## 📈 Interpretación de Resultados

### Estados de Test

- **✅ PASS** - Test pasó correctamente
- **❌ FAIL** - Test falló, requiere atención
- **⚠️ WARN** - Test pasó pero con advertencias

### Métricas

- **Tasa de Éxito**: Porcentaje de tests pasados
- **Esperado**: ≥ 90% para calidad producción

### Ejemplo de Salida

```
====================================================================
📊 RESULTADOS DE TESTS
====================================================================

✅ Pasaron: 8
❌ Fallaron: 0
⚠️ Advertencias: 2
📈 Total: 10 tests

DETALLES:
--------------------------------------------------------------------
✅ [Load] Load Test: Todas las animaciones cargan correctamente
✅ [DOM] DOM Test: Estructura de elementos requerida
✅ [Import] Import Test: Archivos common.css y common.js importados
✅ [Index] Index Test: Página de galería válida
✅ [Resources] Resources Test: Archivos CSS y JS existen
✅ [CSS] CSS Test: Sin valores de 3 decimales
✅ [Data] Data Test: Archivo data.js contiene 36 animaciones
✅ [Documentation] Documentation Test: Archivos de documentación existen

====================================================================
🎯 Tasa de éxito: 100%

✨ ¡Todos los tests pasaron! 🎉
```

## 🛠️ Personalizar Tests

### Agregar un Nuevo Test

Para agregar un test personalizado:

```javascript
class MyCustomTestSuite extends AnimationTestSuite {
  async testMyFeature() {
    const testName = 'My Custom Test: descripción';
    try {
      // Tu lógica aquí
      const result = await myValidation();
      
      if (result.isValid) {
        this.passed('Custom', testName);
      } else {
        this.failed('Custom', testName, result.error);
      }
    } catch (err) {
      this.warning('Custom', testName, err.message);
    }
  }

  async runAll() {
    await super.runAll();
    await this.testMyFeature();
    this.printResults();
  }
}
```

### Ejecutar Tests Específicos

```javascript
const suite = new AnimationTestSuite();

// Solo Load Test
await suite.testAnimationLoad();

// Múltiples tests
await suite.testAnimationLoad();
await suite.testDOMStructure();
await suite.testHelperImports();
```

## 📊 Exportar Resultados

### Formato JSON

```javascript
const suite = new AnimationTestSuite();
const results = await suite.runAll();
const json = suite.exportJSON();
// Guarda en archivo o envía al servidor
```

### Formato HTML

```javascript
const html = suite.exportHTML();
// Descarga automáticamente o guarda en archivo
```

## 🐛 Debugging

### Ver Detalles Completos

```javascript
const suite = new AnimationTestSuite();
const results = await suite.runAll();
console.table(results.tests);
```

### Verificar Animación Específica

```javascript
// Chequear si una animación carga
fetch('../src/animations/animacion-a.html')
  .then(r => r.status === 200 ? 'OK' : 'ERROR')
  .then(status => console.log('animacion-a:', status));
```

### Verificar Common.js

```javascript
// En consola del navegador
console.log(typeof AnimationToggle);  // Debe ser 'function'
console.log(typeof PhysicsObject);     // Debe ser 'function'
console.log(typeof Effects);           // Debe ser 'object'
```

## 📝 Checklist de Calidad

Antes de hacer push a producción:

- [ ] Todos los tests PASS (tasa de éxito 100%)
- [ ] No hay tests FAIL
- [ ] Advertencias revisadas y documentadas
- [ ] Accesibilidad: ≥ 80% de tests pasados
- [ ] Documentación actualizada
- [ ] Manual QA completado (ver animaciones en navegador)

## 🔄 CI/CD Integration

Los tests pueden integrarse con CI/CD:

### GitHub Actions

```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run Tests
        run: |
          cd tests
          # Ejecutar tests (requiere navegador)
```

### Pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit
cd tests
node animation-validation.js || exit 1
```

## 📚 Recursos Adicionales

- [DRY-GUIDE.md](../guides/DRY-GUIDE.md) - Patrones de código DRY
- [MODULOS.md](../guides/MODULOS.md) - Documentación de módulos
- [ACCESSIBILITY.md](./ACCESSIBILITY.md) - Guía de accesibilidad
- [README.md](../../README.md) - Documentación general

## 💡 Mejoras Futuras

- [ ] Tests de performance (FPS, memory)
- [ ] Tests E2E con Playwright
- [ ] Tests visuales (screenshot comparison)
- [ ] Automatización en CI/CD
- [ ] Dashboard de métricas
- [ ] Cobertura de código

---

**Última actualización**: 2026-09-24  
**Versión**: 1.0  
**Status**: ✅ Producción-ready
