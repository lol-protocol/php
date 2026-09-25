// Test Suite para Validación de Animaciones
class AnimationTestSuite {
  constructor() {
    this.results = {
      passed: 0,
      failed: 0,
      warnings: 0,
      tests: []
    };
    this.animations = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'aa', 'ab', 'ac', 'ad', 'ae', 'af', 'ag', 'ah', 'ai', 'aj'];
  }

  // Test 1: Verificar que todas las animaciones cargan sin errores
  async testAnimationLoad() {
    const testName = 'Load Test: Todas las animaciones cargan correctamente';
    let failedLoads = [];

    for (const anim of this.animations) {
      try {
        const response = await fetch(`../src/animations/animacion-${anim}.html`);
        if (!response.ok) {
          failedLoads.push(anim);
        }
      } catch (err) {
        failedLoads.push(anim);
      }
    }

    if (failedLoads.length === 0) {
      this.passed('Load', testName);
    } else {
      this.failed('Load', testName, `Animaciones que no cargan: ${failedLoads.join(', ')}`);
    }
  }

  // Test 2: Verificar que cada animación tiene el DOM necesario
  async testDOMStructure() {
    const testName = 'DOM Test: Estructura de elementos requerida';
    let domErrors = [];

    for (const anim of this.animations) {
      try {
        const response = await fetch(`../src/animations/animacion-${anim}.html`);
        const html = await response.text();

        // Verificar elementos clave
        if (!html.includes('fullscreen-container') && !html.includes('class="') && !html.includes('id="')) {
          domErrors.push(`${anim}: sin estructura de contenedor`);
        }
      } catch (err) {
        domErrors.push(`${anim}: error al leer HTML`);
      }
    }

    if (domErrors.length === 0) {
      this.passed('DOM', testName);
    } else {
      this.warning('DOM', testName, `Potenciales problemas de DOM: ${domErrors.length} animaciones`);
    }
  }

  // Test 3: Verificar imports de helpers
  async testHelperImports() {
    const testName = 'Import Test: Archivos common.css y common.js importados';
    let missingImports = [];

    for (const anim of this.animations) {
      try {
        const response = await fetch(`../src/animations/animacion-${anim}.html`);
        const html = await response.text();

        const hasCommonCss = html.includes('common.css');
        const hasCommonJs = html.includes('common.js') || html.includes('animacion-' + anim + '.js');

        if (!hasCommonCss || !hasCommonJs) {
          missingImports.push(anim);
        }
      } catch (err) {
        missingImports.push(anim);
      }
    }

    if (missingImports.length === 0) {
      this.passed('Import', testName);
    } else {
      this.failed('Import', testName, `Animaciones sin imports correcto: ${missingImports.join(', ')}`);
    }
  }

  // Test 4: Verificar que index.html existe y es válido
  async testIndexPage() {
    const testName = 'Index Test: Página de galería válida';
    try {
      const response = await fetch('../src/animations/index.html');
      if (!response.ok) {
        this.failed('Index', testName, 'index.html no accesible');
        return;
      }

      const html = await response.text();
      const hasAnimationCount = html.includes('36') || html.includes('animación');
      const hasFilters = html.includes('filter') || html.includes('search');

      if (hasAnimationCount && hasFilters) {
        this.passed('Index', testName);
      } else {
        this.warning('Index', testName, 'Posible información desactualizada en index.html');
      }
    } catch (err) {
      this.failed('Index', testName, 'Error al acceder a index.html');
    }
  }

  // Test 5: Verificar existencia de archivos CSS y JS
  async testResourceFiles() {
    const testName = 'Resources Test: Archivos CSS y JS existen';
    let missingResources = [];

    const requiredFiles = [
      '../src/helpers/common.css',
      '../src/helpers/common.js',
      '../src/helpers/animation-templates.js'
    ];

    for (const file of requiredFiles) {
      try {
        const response = await fetch(file);
        if (!response.ok) {
          missingResources.push(file);
        }
      } catch (err) {
        missingResources.push(file);
      }
    }

    if (missingResources.length === 0) {
      this.passed('Resources', testName);
    } else {
      this.failed('Resources', testName, `Archivos faltantes: ${missingResources.join(', ')}`);
    }
  }

  // Test 6: Validación de CSS (sin 3 decimales)
  async testCSSValidation() {
    const testName = 'CSS Test: Sin valores de 3 decimales';
    let decimalErrors = [];

    try {
      const response = await fetch('../src/helpers/common.css');
      const css = await response.text();

      // Regex para encontrar valores con 3 decimales (0.xyz)
      const threeDecimalPattern = /[\s:]\d+\.\d{3}(?:\s|;|px|em|rem|%|vh|vw)/g;
      const matches = css.match(threeDecimalPattern);

      if (matches && matches.length > 0) {
        this.warning('CSS', testName, `Se encontraron ${matches.length} valores con 3 decimales`);
      } else {
        this.passed('CSS', testName);
      }
    } catch (err) {
      this.warning('CSS', testName, 'No se pudo validar CSS');
    }
  }

  // Test 7: Verificar data.js
  async testDataFile() {
    const testName = 'Data Test: Archivo data.js contiene 36 animaciones';
    try {
      const response = await fetch('../src/animations/data.js');
      if (!response.ok) {
        this.failed('Data', testName, 'data.js no accesible');
        return;
      }

      const js = await response.text();
      const initialCount = (js.match(/id: '/g) || []).length;

      if (initialCount >= 36) {
        this.passed('Data', testName);
      } else {
        this.failed('Data', testName, `Solo se encontraron ${initialCount} animaciones en data.js`);
      }
    } catch (err) {
      this.failed('Data', testName, 'Error al leer data.js');
    }
  }

  // Test 8: Verificar que documentación existe
  async testDocumentation() {
    const testName = 'Documentation Test: Archivos de documentación existen';
    let missingDocs = [];

    const docs = [
      '../docs/guides/DRY-GUIDE.md',
      '../docs/guides/MODULOS.md',
      '../docs/analysis/ERRORS-FOUND.md'
    ];

    for (const doc of docs) {
      try {
        const response = await fetch(doc);
        if (!response.ok) {
          missingDocs.push(doc);
        }
      } catch (err) {
        missingDocs.push(doc);
      }
    }

    if (missingDocs.length === 0) {
      this.passed('Documentation', testName);
    } else {
      this.warning('Documentation', testName, `Documentos no encontrados: ${missingDocs.length}`);
    }
  }

  // Métodos helper
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
    console.log('🧪 Iniciando Test Suite de Animaciones...\n');

    await this.testAnimationLoad();
    await this.testDOMStructure();
    await this.testHelperImports();
    await this.testIndexPage();
    await this.testResourceFiles();
    await this.testCSSValidation();
    await this.testDataFile();
    await this.testDocumentation();

    this.printResults();
    return this.results;
  }

  // Imprimir resultados
  printResults() {
    console.log('\n' + '='.repeat(70));
    console.log('📊 RESULTADOS DE TESTS');
    console.log('='.repeat(70));

    console.log(`\n✅ Pasaron: ${this.results.passed}`);
    console.log(`❌ Fallaron: ${this.results.failed}`);
    console.log(`⚠️ Advertencias: ${this.results.warnings}`);
    console.log(`📈 Total: ${this.results.tests.length} tests\n`);

    console.log('DETALLES:');
    console.log('-'.repeat(70));

    this.results.tests.forEach(test => {
      const status = test.status === 'PASS' ? '✅' : test.status === 'FAIL' ? '❌' : '⚠️';
      console.log(`${status} [${test.category}] ${test.test}`);
      if (test.message && test.message !== '✅') {
        console.log(`   ${test.message}`);
      }
    });

    console.log('\n' + '='.repeat(70));

    const successRate = ((this.results.passed / this.results.tests.length) * 100).toFixed(1);
    console.log(`🎯 Tasa de éxito: ${successRate}%\n`);

    if (this.results.failed === 0) {
      console.log('✨ ¡Todos los tests pasaron! 🎉\n');
    } else {
      console.log(`⚠️ Se encontraron ${this.results.failed} problemas que revisar.\n`);
    }
  }

  // Exportar resultados como JSON
  exportJSON() {
    return JSON.stringify(this.results, null, 2);
  }

  // Exportar resultados como HTML
  exportHTML() {
    const statusMap = { PASS: '✅', FAIL: '❌', WARN: '⚠️' };
    const colorMap = { PASS: '#4CAF50', FAIL: '#f44336', WARN: '#ff9800' };

    let html = `
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Test Results - Animaciones Web</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 30px; }
            .container { max-width: 1000px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #333; margin-bottom: 10px; }
            .subtitle { color: #666; margin-bottom: 30px; }
            .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
            .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
            .stat-value { font-size: 2.5em; font-weight: bold; }
            .stat-label { font-size: 0.9em; opacity: 0.9; margin-top: 5px; }
            .tests-list { margin-top: 40px; }
            .test-item { display: flex; align-items: center; padding: 12px; border-left: 4px solid #ddd; margin-bottom: 10px; border-radius: 4px; }
            .test-item.pass { background: #f0f9ff; border-left-color: #4CAF50; }
            .test-item.fail { background: #fff0f0; border-left-color: #f44336; }
            .test-item.warn { background: #fff9f0; border-left-color: #ff9800; }
            .test-icon { font-size: 1.5em; margin-right: 15px; min-width: 30px; }
            .test-content { flex-grow: 1; }
            .test-category { color: #667eea; font-weight: 600; font-size: 0.85em; display: inline-block; background: rgba(102, 126, 234, 0.1); padding: 4px 8px; border-radius: 4px; margin-right: 10px; }
            .test-name { color: #333; margin-bottom: 4px; }
            .test-message { color: #666; font-size: 0.9em; margin-top: 6px; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #999; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🧪 Resultados de Test Suite</h1>
            <p class="subtitle">Validación de Animaciones Web - ${new Date().toLocaleString('es-ES')}</p>

            <div class="stats">
                <div class="stat-card" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);">
                    <div class="stat-value">${this.results.passed}</div>
                    <div class="stat-label">Pasaron</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #f44336 0%, #da190b 100%);">
                    <div class="stat-value">${this.results.failed}</div>
                    <div class="stat-label">Fallaron</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #ff9800 0%, #e65100 100%);">
                    <div class="stat-value">${this.results.warnings}</div>
                    <div class="stat-label">Advertencias</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #2196F3 0%, #0b7dda 100%);">
                    <div class="stat-value">${((this.results.passed / this.results.tests.length) * 100).toFixed(0)}%</div>
                    <div class="stat-label">Éxito</div>
                </div>
            </div>

            <div class="tests-list">
                <h2>Detalles de Tests</h2>
                ${this.results.tests.map(test => {
                    const statusClass = test.status === 'PASS' ? 'pass' : test.status === 'FAIL' ? 'fail' : 'warn';
                    const icon = statusMap[test.status];
                    return `
                    <div class="test-item ${statusClass}">
                        <div class="test-icon">${icon}</div>
                        <div class="test-content">
                            <span class="test-category">${test.category}</span>
                            <div class="test-name">${test.test}</div>
                            ${test.message !== '✅' ? `<div class="test-message">${test.message}</div>` : ''}
                        </div>
                    </div>
                    `;
                }).join('')}
            </div>

            <div class="footer">
                <p>✨ Test Suite para Animaciones Web | Generado automáticamente</p>
            </div>
        </div>
    </body>
    </html>
    `;
    return html;
  }
}

// Exportar para Node.js o navegador
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AnimationTestSuite;
}
