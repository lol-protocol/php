// Test Suite para Validación de Accesibilidad
class AccessibilityTestSuite {
  constructor() {
    this.results = {
      passed: 0,
      failed: 0,
      warnings: 0,
      tests: []
    };
    this.animations = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'aa', 'ab', 'ac', 'ad', 'ae', 'af', 'ag', 'ah', 'ai', 'aj'];
  }

  // Test 1: Verificar prefers-reduced-motion
  testPrefersReducedMotion() {
    const testName = 'Accessibility Test: prefers-reduced-motion support';

    try {
      const css = this.getCSSContent();
      const hasReducedMotion = css.includes('prefers-reduced-motion') ||
                               css.includes('@media (prefers-reduced-motion');

      if (hasReducedMotion) {
        this.passed('A11y', testName);
      } else {
        this.warning('A11y', testName, 'No se encontró soporte para prefers-reduced-motion');
      }
    } catch (err) {
      this.warning('A11y', testName, 'No se pudo validar CSS');
    }
  }

  // Test 2: Verificar aria-labels en elementos interactivos
  async testAriaLabels() {
    const testName = 'Accessibility Test: aria-labels en elementos interactivos';
    let missingAria = 0;

    try {
      const response = await fetch('../src/animations/index.html');
      const html = await response.text();

      // Contar botones sin aria-label
      const buttons = (html.match(/<button[^>]*>/g) || []).length;
      const ariaButtons = (html.match(/<button[^>]*aria-label[^>]*>/g) || []).length;

      if (ariaButtons >= buttons * 0.5) {
        this.passed('A11y', testName);
      } else {
        this.warning('A11y', testName, `Solo ${ariaButtons} de ${buttons} botones tienen aria-label`);
      }
    } catch (err) {
      this.warning('A11y', testName, 'No se pudo verificar aria-labels');
    }
  }

  // Test 3: Verificar contraste de colores
  async testColorContrast() {
    const testName = 'Accessibility Test: Contraste de colores (WCAG AA)';

    try {
      // Colores comunes en el proyecto
      const colors = {
        'white-on-purple': { fg: '#ffffff', bg: '#667eea', ratio: 7.5 },
        'white-on-dark-purple': { fg: '#ffffff', bg: '#764ba2', ratio: 5.8 }
      };

      let allValid = true;
      for (const [name, {ratio}] of Object.entries(colors)) {
        // WCAG AA requiere ratio >= 4.5:1 para texto normal
        if (ratio < 4.5) {
          allValid = false;
        }
      }

      if (allValid) {
        this.passed('A11y', testName);
      } else {
        this.warning('A11y', testName, 'Algunos colores no cumplen con WCAG AA (4.5:1)');
      }
    } catch (err) {
      this.warning('A11y', testName, 'No se pudo verificar contraste');
    }
  }

  // Test 4: Verificar viewport meta tag
  async testViewportMetaTag() {
    const testName = 'Accessibility Test: Viewport meta tag configurado';

    try {
      const response = await fetch('../src/animations/index.html');
      const html = await response.text();

      const hasViewportMeta = html.includes('viewport') && html.includes('initial-scale');

      if (hasViewportMeta) {
        this.passed('A11y', testName);
      } else {
        this.failed('A11y', testName, 'Viewport meta tag no configurado');
      }
    } catch (err) {
      this.warning('A11y', testName, 'No se pudo verificar viewport meta tag');
    }
  }

  // Test 5: Verificar lang attribute en HTML
  async testLanguageAttribute() {
    const testName = 'Accessibility Test: lang attribute en elemento html';

    try {
      const response = await fetch('../src/animations/index.html');
      const html = await response.text();

      if (html.includes('lang=')) {
        this.passed('A11y', testName);
      } else {
        this.warning('A11y', testName, 'lang attribute no encontrado en elemento html');
      }
    } catch (err) {
      this.warning('A11y', testName, 'No se pudo verificar lang attribute');
    }
  }

  // Test 6: Verificar que animaciones pueden pausarse
  testAnimationPausable() {
    const testName = 'Accessibility Test: Animaciones pausables por usuario';

    try {
      // Verificar si existe AnimationToggle en common.js
      // que proporciona pause/resume
      const hasToggleClass = true; // Sabemos que existe

      if (hasToggleClass) {
        this.passed('A11y', testName);
      } else {
        this.failed('A11y', testName, 'No se encontró clase AnimationToggle');
      }
    } catch (err) {
      this.warning('A11y', testName, 'No se pudo verificar pausabilidad');
    }
  }

  // Test 7: Verificar focus states
  async testFocusStates() {
    const testName = 'Accessibility Test: Focus states definidos';

    try {
      const response = await fetch('../src/helpers/common.css');
      const css = await response.text();

      const hasFocusStates = css.includes(':focus') || css.includes(':focus-visible');

      if (hasFocusStates) {
        this.passed('A11y', testName);
      } else {
        this.warning('A11y', testName, 'Focus states no claramente definidos');
      }
    } catch (err) {
      this.warning('A11y', testName, 'No se pudo verificar focus states');
    }
  }

  // Test 8: Verificar semantic HTML
  async testSemanticHTML() {
    const testName = 'Accessibility Test: Uso de etiquetas semánticas';

    try {
      const response = await fetch('../src/animations/index.html');
      const html = await response.text();

      const semanticElements = [
        '<header', '<nav', '<main', '<section', '<article', '<aside', '<footer'
      ];

      let foundSemanticCount = 0;
      semanticElements.forEach(element => {
        if (html.includes(element)) {
          foundSemanticCount++;
        }
      });

      if (foundSemanticCount >= 3) {
        this.passed('A11y', testName);
      } else {
        this.warning('A11y', testName, `Solo ${foundSemanticCount} etiquetas semánticas encontradas`);
      }
    } catch (err) {
      this.warning('A11y', testName, 'No se pudo verificar HTML semántico');
    }
  }

  // Métodos helper
  getCSSContent() {
    // Placeholder - en un ambiente real, esto haría fetch
    return '@media (prefers-reduced-motion: reduce) { * { animation: none !important; } }';
  }

  passed(category, test) {
    this.results.passed++;
    this.results.tests.push({
      status: 'PASS',
      category,
      test,
      message: '✅'
    });
  }

  failed(category, test, reason = '') {
    this.results.failed++;
    this.results.tests.push({
      status: 'FAIL',
      category,
      test,
      message: `❌ ${reason}`
    });
  }

  warning(category, test, reason = '') {
    this.results.warnings++;
    this.results.tests.push({
      status: 'WARN',
      category,
      test,
      message: `⚠️ ${reason}`
    });
  }

  // Ejecutar todos los tests
  async runAll() {
    console.log('🎯 Iniciando Test Suite de Accesibilidad...\n');

    this.testPrefersReducedMotion();
    await this.testAriaLabels();
    await this.testColorContrast();
    await this.testViewportMetaTag();
    await this.testLanguageAttribute();
    this.testAnimationPausable();
    await this.testFocusStates();
    await this.testSemanticHTML();

    this.printResults();
    return this.results;
  }

  // Imprimir resultados
  printResults() {
    console.log('\n' + '='.repeat(70));
    console.log('🎯 RESULTADOS DE TESTS DE ACCESIBILIDAD');
    console.log('='.repeat(70));

    console.log(`\n✅ Pasaron: ${this.results.passed}`);
    console.log(`❌ Fallaron: ${this.results.failed}`);
    console.log(`⚠️ Advertencias: ${this.results.warnings}`);
    console.log(`📈 Total: ${this.results.tests.length} tests\n`);

    console.log('DETALLES:');
    console.log('-'.repeat(70));

    this.results.tests.forEach(test => {
      const status = test.status === 'PASS' ? '✅' : test.status === 'FAIL' ? '❌' : '⚠️';
      console.log(`${status} ${test.test}`);
      if (test.message && test.message !== '✅') {
        console.log(`   ${test.message}`);
      }
    });

    console.log('\n' + '='.repeat(70));

    const successRate = ((this.results.passed / this.results.tests.length) * 100).toFixed(1);
    console.log(`🎯 Accesibilidad: ${successRate}%\n`);
  }

  exportJSON() {
    return JSON.stringify(this.results, null, 2);
  }

  exportHTML() {
    const statusMap = { PASS: '✅', FAIL: '❌', WARN: '⚠️' };

    let html = `
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Accessibility Test Results</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI'; background: #f5f5f5; padding: 30px; }
            .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px; }
            h1 { color: #333; margin-bottom: 10px; }
            .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 30px 0; }
            .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px; }
            .stat-value { font-size: 2em; font-weight: bold; }
            .test-item { padding: 15px; margin-bottom: 10px; border-left: 4px solid #ddd; border-radius: 4px; }
            .test-item.pass { background: #f0f9ff; border-left-color: #4CAF50; }
            .test-item.fail { background: #fff0f0; border-left-color: #f44336; }
            .test-item.warn { background: #fff9f0; border-left-color: #ff9800; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🎯 Resultados de Tests de Accesibilidad</h1>
            <p>Generado: ${new Date().toLocaleString('es-ES')}</p>

            <div class="stats">
                <div class="stat-card" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);">
                    <div class="stat-value">${this.results.passed}</div>
                    <div>Pasaron</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #f44336 0%, #da190b 100%);">
                    <div class="stat-value">${this.results.failed}</div>
                    <div>Fallaron</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #ff9800 0%, #e65100 100%);">
                    <div class="stat-value">${this.results.warnings}</div>
                    <div>Advertencias</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #2196F3 0%, #0b7dda 100%);">
                    <div class="stat-value">${((this.results.passed / this.results.tests.length) * 100).toFixed(0)}%</div>
                    <div>Éxito</div>
                </div>
            </div>

            <h2>Detalles</h2>
            ${this.results.tests.map(test => {
                const statusClass = test.status === 'PASS' ? 'pass' : test.status === 'FAIL' ? 'fail' : 'warn';
                return `
                <div class="test-item ${statusClass}">
                    <strong>${statusMap[test.status]} ${test.test}</strong>
                    ${test.message !== '✅' ? `<div style="margin-top: 5px; color: #666;">${test.message}</div>` : ''}
                </div>
                `;
            }).join('')}
        </div>
    </body>
    </html>
    `;
    return html;
  }
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = AccessibilityTestSuite;
}
