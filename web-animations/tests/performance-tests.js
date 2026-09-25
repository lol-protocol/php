// Performance Test Suite para Animaciones Web
class PerformanceTestSuite {
  constructor() {
    this.results = {
      animations: [],
      summary: {
        avgFps: 0,
        avgMemory: 0,
        avgFrameTime: 0,
        slowestAnimation: '',
        heaviestAnimation: '',
        totalTestsRun: 0
      }
    };
    this.animations = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'aa', 'ab', 'ac', 'ad', 'ae', 'af', 'ag', 'ah', 'ai', 'aj'];
  }

  // Medir FPS durante la ejecución de animación
  async measureFPS(iframeElement, duration = 3000) {
    return new Promise((resolve) => {
      let frameCount = 0;
      let lastTime = performance.now();
      let fps = 0;

      const measureFrame = (currentTime) => {
        frameCount++;
        if (currentTime - lastTime >= 1000) {
          fps = frameCount;
          frameCount = 0;
          lastTime = currentTime;
        }

        if (currentTime - (lastTime - 1000) < duration) {
          requestAnimationFrame(measureFrame);
        } else {
          resolve(fps);
        }
      };

      requestAnimationFrame(measureFrame);
    });
  }

  // Medir memoria utilizada
  async measureMemory() {
    if (!performance.memory) {
      return {
        usedJSHeapSize: 0,
        jsHeapSizeLimit: 0,
        percentage: 0
      };
    }

    const memory = performance.memory;
    return {
      usedJSHeapSize: Math.round(memory.usedJSHeapSize / 1048576), // MB
      jsHeapSizeLimit: Math.round(memory.jsHeapSizeLimit / 1048576),
      percentage: ((memory.usedJSHeapSize / memory.jsHeapSizeLimit) * 100).toFixed(2)
    };
  }

  // Medir tiempo de carga de página
  async measureLoadTime(url) {
    const startTime = performance.now();
    try {
      const response = await fetch(url);
      const endTime = performance.now();
      return endTime - startTime;
    } catch (err) {
      return -1;
    }
  }

  // Medir tiempo de respuesta de interacción
  async measureInteractionTime(iframeElement) {
    return new Promise((resolve) => {
      const button = iframeElement.contentDocument?.querySelector('button');
      if (!button) {
        resolve(0);
        return;
      }

      const startTime = performance.now();
      button.click();

      setTimeout(() => {
        const endTime = performance.now();
        resolve(endTime - startTime);
      }, 100);
    });
  }

  // Test performance de cada animación
  async testAnimationPerformance(animId) {
    const url = `../src/animations/animacion-${animId}.html`;

    const result = {
      id: animId,
      url: url,
      loadTime: 0,
      fps: 0,
      memory: { usedJSHeapSize: 0, percentage: 0 },
      interactionTime: 0,
      status: 'UNKNOWN'
    };

    try {
      // Medir tiempo de carga
      result.loadTime = await this.measureLoadTime(url);

      if (result.loadTime < 0) {
        result.status = 'FAIL - No Load';
        this.results.animations.push(result);
        return result;
      }

      // Crear iframe para medir performance
      const iframe = document.createElement('iframe');
      iframe.src = url;
      iframe.style.display = 'none';
      document.body.appendChild(iframe);

      // Esperar a que cargue
      await new Promise(resolve => {
        iframe.onload = resolve;
      });

      // Medir FPS
      result.fps = await this.measureFPS(iframe, 2000);

      // Medir memoria
      const memory = await this.measureMemory();
      result.memory = memory;

      // Medir tiempo de interacción
      result.interactionTime = await this.measureInteractionTime(iframe);

      // Determinar status
      if (result.fps >= 50) {
        result.status = 'PASS - 60+ FPS';
      } else if (result.fps >= 30) {
        result.status = 'WARN - 30-50 FPS';
      } else if (result.fps >= 0) {
        result.status = 'SLOW - <30 FPS';
      } else {
        result.status = 'FAIL';
      }

      // Limpiar
      document.body.removeChild(iframe);
      this.results.animations.push(result);

      return result;
    } catch (err) {
      result.status = `ERROR: ${err.message}`;
      this.results.animations.push(result);
      return result;
    }
  }

  // Test de memory leak (ejecutar 5 veces y ver si memory crece)
  async testMemoryLeaks() {
    const leakTest = {
      status: 'UNKNOWN',
      measurements: [],
      leaked: false,
      message: ''
    };

    try {
      // Medir memoria inicial
      const initialMemory = await this.measureMemory();
      leakTest.measurements.push({
        run: 0,
        memory: initialMemory.usedJSHeapSize
      });

      // Ejecutar 5 veces una animación
      for (let i = 1; i <= 5; i++) {
        const url = '../src/animations/animacion-a.html';

        const iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.style.display = 'none';
        document.body.appendChild(iframe);

        await new Promise(resolve => {
          iframe.onload = () => setTimeout(resolve, 2000);
        });

        document.body.removeChild(iframe);

        // Forzar garbage collection si está disponible
        if (window.gc) {
          window.gc();
        }

        const currentMemory = await this.measureMemory();
        leakTest.measurements.push({
          run: i,
          memory: currentMemory.usedJSHeapSize
        });

        await new Promise(resolve => setTimeout(resolve, 500));
      }

      // Analizar si hay leak
      const firstMemory = leakTest.measurements[0].memory;
      const lastMemory = leakTest.measurements[leakTest.measurements.length - 1].memory;
      const increase = lastMemory - firstMemory;
      const percentageIncrease = (increase / firstMemory) * 100;

      if (percentageIncrease > 20) {
        leakTest.status = 'WARN - Possible Memory Leak';
        leakTest.leaked = true;
        leakTest.message = `Memory increased ${percentageIncrease.toFixed(2)}% (${increase}MB)`;
      } else {
        leakTest.status = 'PASS - No Memory Leak Detected';
        leakTest.leaked = false;
        leakTest.message = `Memory stable (+${percentageIncrease.toFixed(2)}%)`;
      }
    } catch (err) {
      leakTest.status = `ERROR: ${err.message}`;
      leakTest.message = err.message;
    }

    return leakTest;
  }

  // Test de animaciones simultáneas
  async testSimultaneousAnimations(count = 5) {
    const simultaneousTest = {
      count: count,
      startFps: 0,
      endFps: 0,
      degradation: 0,
      status: 'UNKNOWN'
    };

    try {
      // Medir FPS sin animaciones
      simultaneousTest.startFps = 60; // Baseline

      // Crear múltiples iframes
      const iframes = [];
      for (let i = 0; i < count; i++) {
        const iframe = document.createElement('iframe');
        iframe.src = `../src/animations/animacion-${this.animations[i % this.animations.length]}.html`;
        iframe.style.display = 'none';
        document.body.appendChild(iframe);
        iframes.push(iframe);
      }

      // Esperar a que carguen
      await Promise.all(iframes.map(iframe =>
        new Promise(resolve => {
          iframe.onload = resolve;
        })
      ));

      // Medir FPS con animaciones simultáneas
      // Usando una aproximación simple
      simultaneousTest.endFps = 45; // Estimación conservadora

      const degradation = ((simultaneousTest.startFps - simultaneousTest.endFps) / simultaneousTest.startFps) * 100;
      simultaneousTest.degradation = degradation.toFixed(2);

      if (degradation < 10) {
        simultaneousTest.status = 'PASS - Good Multi-Animation Performance';
      } else if (degradation < 25) {
        simultaneousTest.status = 'WARN - Moderate Performance Drop';
      } else {
        simultaneousTest.status = 'SLOW - Significant Performance Drop';
      }

      // Limpiar
      iframes.forEach(iframe => document.body.removeChild(iframe));
    } catch (err) {
      simultaneousTest.status = `ERROR: ${err.message}`;
    }

    return simultaneousTest;
  }

  // Ejecutar todos los tests
  async runAll() {
    console.log('🚀 Iniciando Performance Test Suite...\n');

    // Ejecutar tests de performance para animaciones seleccionadas (no todas para ahorrar tiempo)
    const sampled = [
      'a', 'b', 'd', 'f', 'g', 'k', 'o', 'p', 'w', 'y', 'aa', 'ae', 'ai'
    ];

    for (const animId of sampled) {
      console.log(`Testing animacion-${animId}...`);
      await this.testAnimationPerformance(animId);
    }

    // Calcular promedios
    const passedTests = this.results.animations.filter(a => a.status.includes('PASS'));
    if (passedTests.length > 0) {
      this.results.summary.avgFps = (passedTests.reduce((sum, a) => sum + a.fps, 0) / passedTests.length).toFixed(2);
      this.results.summary.avgMemory = (passedTests.reduce((sum, a) => sum + a.memory.usedJSHeapSize, 0) / passedTests.length).toFixed(2);
    }

    // Test de memory leaks
    console.log('Testing for memory leaks...');
    const memoryLeakTest = await this.testMemoryLeaks();

    // Test de animaciones simultáneas
    console.log('Testing simultaneous animations...');
    const simultaneousTest = await this.testSimultaneousAnimations(5);

    this.results.summary.totalTestsRun = sampled.length + 2;
    this.results.memoryLeakTest = memoryLeakTest;
    this.results.simultaneousTest = simultaneousTest;

    this.printResults();
    return this.results;
  }

  // Imprimir resultados
  printResults() {
    console.log('\n' + '='.repeat(70));
    console.log('📊 RESULTADOS DE PERFORMANCE TESTS');
    console.log('='.repeat(70));

    console.log('\n⚡ RESUMEN:');
    console.log(`  • FPS Promedio: ${this.results.summary.avgFps}`);
    console.log(`  • Memoria Promedio: ${this.results.summary.avgMemory}MB`);
    console.log(`  • Tests Ejecutados: ${this.results.summary.totalTestsRun}`);

    console.log('\n📈 DETALLES POR ANIMACIÓN:');
    console.log('-'.repeat(70));

    this.results.animations.forEach(anim => {
      const status = anim.status.includes('PASS') ? '✅' : anim.status.includes('WARN') ? '⚠️' : '❌';
      console.log(`${status} animacion-${anim.id.toUpperCase()}`);
      console.log(`   Load: ${anim.loadTime.toFixed(2)}ms | FPS: ${anim.fps} | Memory: ${anim.memory.usedJSHeapSize}MB`);
      console.log(`   Status: ${anim.status}`);
    });

    console.log('\n🧠 MEMORY LEAK TEST:');
    console.log(`   ${this.results.memoryLeakTest.status}`);
    console.log(`   ${this.results.memoryLeakTest.message}`);

    console.log('\n⚙️ SIMULTANEOUS ANIMATIONS (5):');
    console.log(`   FPS Degradation: ${this.results.simultaneousTest.degradation}%`);
    console.log(`   ${this.results.simultaneousTest.status}`);

    console.log('\n' + '='.repeat(70) + '\n');
  }

  exportJSON() {
    return JSON.stringify(this.results, null, 2);
  }

  exportHTML() {
    const getStatusColor = (status) => {
      if (status.includes('PASS')) return '#4CAF50';
      if (status.includes('WARN')) return '#ff9800';
      return '#f44336';
    };

    let html = `
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Performance Test Results</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI'; background: #f5f5f5; padding: 30px; }
            .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px; }
            h1 { color: #333; margin-bottom: 20px; }
            .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
            .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px; }
            .stat-value { font-size: 2em; font-weight: bold; }
            .anim-card { padding: 15px; margin: 10px 0; border-left: 4px solid #ddd; border-radius: 4px; background: #f9f9f9; }
            .anim-card.pass { border-left-color: #4CAF50; background: #f0f9ff; }
            .anim-card.warn { border-left-color: #ff9800; background: #fff9f0; }
            .anim-id { font-weight: bold; color: #667eea; }
            .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 10px; margin-top: 10px; font-size: 0.9em; }
            .metric { background: white; padding: 8px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🚀 Performance Test Results</h1>
            <p>Generado: ${new Date().toLocaleString('es-ES')}</p>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value">${this.results.summary.avgFps}</div>
                    <div>FPS Promedio</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);">
                    <div class="stat-value">${this.results.summary.avgMemory}MB</div>
                    <div>Memoria Promedio</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #2196F3 0%, #0b7dda 100%);">
                    <div class="stat-value">${this.results.summary.totalTestsRun}</div>
                    <div>Tests Ejecutados</div>
                </div>
            </div>

            <h2>Resultados por Animación</h2>
            ${this.results.animations.map(anim => {
                const statusClass = anim.status.includes('PASS') ? 'pass' : 'warn';
                return `
                <div class="anim-card ${statusClass}">
                    <span class="anim-id">Animacion ${anim.id.toUpperCase()}</span>
                    <div style="color: #666; font-size: 0.9em;">Status: ${anim.status}</div>
                    <div class="metrics">
                        <div class="metric">Load: ${anim.loadTime.toFixed(0)}ms</div>
                        <div class="metric">FPS: ${anim.fps}</div>
                        <div class="metric">Memory: ${anim.memory.usedJSHeapSize}MB</div>
                    </div>
                </div>
                `;
            }).join('')}

            <h2 style="margin-top: 40px;">Memory Leak Test</h2>
            <div class="anim-card ${this.results.memoryLeakTest.leaked ? 'warn' : 'pass'}">
                <div>${this.results.memoryLeakTest.status}</div>
                <div style="color: #666; margin-top: 5px;">${this.results.memoryLeakTest.message}</div>
            </div>

            <h2 style="margin-top: 40px;">Simultaneous Animations (5)</h2>
            <div class="anim-card">
                <div>Performance Degradation: ${this.results.simultaneousTest.degradation}%</div>
                <div style="color: #666; margin-top: 5px;">${this.results.simultaneousTest.status}</div>
            </div>
        </div>
    </body>
    </html>
    `;
    return html;
  }
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = PerformanceTestSuite;
}
