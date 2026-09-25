# 🧪 Test Suite - Animaciones Web

Suite completa de tests automáticos para validación de calidad del proyecto.

## 📂 Estructura

```
tests/
├── index.html                  # Interfaz web para ejecutar tests
├── animation-validation.js     # Suite de validación de animaciones
├── accessibility-tests.js      # Suite de tests de accesibilidad
├── test-results.html          # Resultados generados
├── test-results.json          # Resultados en JSON
└── Readme.md                  # Este archivo
```

## 🚀 Inicio Rápido

### 1. Abrir Tests en el Navegador

```bash
cd tests/
# Usar cualquier servidor local:
python -m http.server 8000
# o
php -S localhost:8000
```

Luego abre `http://localhost:8000/` en tu navegador.

### 2. Ejecutar Tests

1. Haz clic en **"▶️ Ejecutar Tests"**
2. Espera a que se completen (10-15 segundos)
3. Ver resultados detallados

### 3. Exportar Resultados

- **📄 Exportar HTML**: Descarga reporte visual
- **📋 Exportar JSON**: Descarga datos estructurados

## 📊 Suites de Testing

### AnimationTestSuite

Valida los 36 archivos de animaciones:

| Test | Descripción | Status |
|------|-------------|--------|
| Load Test | Carga de archivos HTML | ✅ |
| DOM Test | Estructura de elementos | ✅ |
| Import Test | Importación de helpers | ✅ |
| Index Test | Página de galería | ✅ |
| Resources Test | Archivos CSS/JS | ✅ |
| CSS Test | Validación de CSS | ✅ |
| Data Test | Archivo data.js | ✅ |
| Documentation Test | Documentos | ✅ |

**Archivo**: `animation-validation.js`  
**Ejecutar**: `new AnimationTestSuite().runAll()`

---

### AccessibilityTestSuite

Valida accesibilidad (WCAG 2.1 AA):

| Test | Descripción | Status |
|------|-------------|--------|
| prefers-reduced-motion | Respeto a preferencias de movimiento | ⚠️ |
| ARIA Labels | Etiquetas accesibles | ✅ |
| Color Contrast | WCAG AA ratio | ✅ |
| Viewport Meta | Responsive meta tag | ✅ |
| Language Attribute | Atributo lang | ✅ |
| Animation Pausable | Animaciones pausables | ✅ |
| Focus States | Estados de enfoque | ⚠️ |
| Semantic HTML | Etiquetas semánticas | ✅ |

**Archivo**: `accessibility-tests.js`  
**Ejecutar**: `new AccessibilityTestSuite().runAll()`

## 📈 Métricas de Éxito

### Target de Calidad

- **Tasa de Éxito**: ≥ 90% de tests pasados
- **Cero Fallos**: No se aceptan tests con status FAIL
- **Advertencias**: Documentar y mitigar

### Interpretación

```
✅ PASS     → Test cumple completamente
❌ FAIL     → Problema crítico (revisar)
⚠️ WARN     → Advertencia (mejora recomendada)

Tasa de Éxito = Passed / Total × 100
```

**Ejemplo**:
```
Tests: 16 totales
- Passed: 14 ✅
- Failed: 0 ❌
- Warnings: 2 ⚠️
Tasa: 87.5% (≥ 90% recomendado)
```

## 🔍 Verificación Manual

### Checklist de Calidad

```
❏ Todos los tests PASS o WARN
❏ Cero tests FAIL
❏ Tasa de éxito ≥ 90%
❏ Accesibilidad ≥ 80%
❏ Documentación actualizada
❏ Manual testing en navegador
```

### Testing Manual en Navegador

1. **Navega a cada animación**
   ```
   src/animations/
   - animacion-a.html hasta animacion-aj.html
   ```

2. **Verifica**:
   - ✅ Animación se ejecuta
   - ✅ Botón pause/resume funciona
   - ✅ Sin errores en consola (F12)
   - ✅ Responsive en móvil

3. **Accesibilidad**:
   - ✅ Puedo navegar con TAB
   - ✅ Puedo pausar animación
   - ✅ Contraste visible

## 🛠️ Uso Programático

### En el Navegador

```javascript
// Ejecutar suite completa
const suite = new AnimationTestSuite();
const results = await suite.runAll();

// Acceder a resultados
console.log(results.passed);      // Número de tests pasados
console.log(results.failed);      // Número de tests fallidos
console.log(results.tests);       // Array de todos los tests

// Exportar
const html = suite.exportHTML();
const json = suite.exportJSON();
```

### Tests Específicos

```javascript
const suite = new AnimationTestSuite();

// Ejecutar un test específico
await suite.testAnimationLoad();

// Ver resultados
suite.printResults();

// Exportar solamente
console.log(suite.exportJSON());
```

## 📋 Documentación Relacionada

- **[TestGuide.md](../docs/testing/TestGuide.md)** - Guía completa de testing
- **[Accessibility.md](../docs/testing/Accessibility.md)** - Estándares de accesibilidad
- **[DryGuide.md](../docs/guides/DryGuide.md)** - Patrones DRY
- **[Readme.md](../Readme.md)** - Documentación general

## 🚨 Troubleshooting

### Los tests no cargan

**Problema**: "AnimationTestSuite is not defined"

**Solución**:
```html
<!-- Verifica que animation-validation.js está cargado -->
<script src="animation-validation.js"></script>
```

### Errores de CORS

**Problema**: "CORS policy: No 'Access-Control-Allow-Origin'"

**Solución**:
- Usa un servidor local (no abrir directamente con `file://`)
- `python -m http.server 8000`
- O `php -S localhost:8000`

### Tests lentos

**Problema**: Tests tardan > 30 segundos

**Causa**: Múltiples fetch requests

**Solución**: Normal, cargando 36 animaciones en paralelo

## 🔄 Integración CI/CD

### GitHub Actions (Ejemplo)

```yaml
name: Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Tests
        run: |
          cd tests
          # Aquí irían los tests
```

## 📊 Histórico de Ejecución

```
2026-09-24 16:30 | ✅ 100% | 8/8 PASS | Initial testing
2026-09-24 16:45 | ✅ 100% | 8/8 PASS | Después de fixes
```

## 🎯 Roadmap de Mejoras

- [ ] Tests de performance (FPS, memory)
- [ ] Tests E2E con Playwright
- [ ] Tests visuales (screenshot comparison)
- [ ] Dashboard en tiempo real
- [ ] Integración CI/CD completa
- [ ] Cobertura de código

## 💡 Tips y Trucos

### Ejecutar en Headless

```javascript
// Ideal para CI/CD
const suite = new AnimationTestSuite();
await suite.runAll();
process.exit(suite.results.failed > 0 ? 1 : 0);
```

### Monitorear Cambios

```bash
# Watch para cambios
watch -n 2 'curl http://localhost:8000/test-results.json | jq'
```

### Debug Individual

```javascript
// Pausar en un test
const suite = new AnimationTestSuite();
await suite.testAnimationLoad();
console.log(suite.results);
debugger;  // Pausa aquí
```

## 📞 Soporte

Para problemas o sugerencias:
1. Revisa la documentación de testing
2. Verifica los logs en consola (F12)
3. Intenta en otro navegador
4. Revisa issues en GitHub

---

**Última actualización**: 2026-09-24  
**Versión**: 1.0  
**Status**: ✅ Producción-ready
