'use strict';

// Accesibilidad y seguridad visual de la aplicación, en Chromium (Playwright):
//
//   1. axe-core (WCAG 2.0, 2.1 y 2.2 AA y buenas prácticas) en tres estados: la bienvenida, la
//      música sonando y el panel de ajustes abierto. Cero violaciones; lo único que axe puede
//      dejar «por revisar» es el contraste de textos sobre el lienzo (que no puede calcular), y
//      eso lo cubre pruebas/contraste.test.js en el peor caso.
//   2. Teclado: la bienvenida tiene el foco en «Empezar», la aplicación detrás está inerte (el
//      Tab no entra), y al empezar el Tab llega a los controles.
//   3. La pantallita no es una región aria-live (se actualiza con cada bloque de música y un
//      lector de pantalla la leería sin parar).
//   4. Destellos: cada una de las seis visualizaciones, con música de ritmo marcado (pop y
//      chiptune, con batería), se mide cuadro a cuadro y no puede pasar de 3 destellos por
//      segundo (WCAG 2.3.1; ver pruebas/ayudas/destellos.js).
//   5. «Menos movimiento»: se enciende solo con la preferencia del sistema, se puede cambiar y
//      la MilkDrop se mueve claramente menos.
//
//   node pruebas/accesibilidad.js
//
// Necesita los paquetes playwright y axe-core (en la CI se instalan; en las sesiones en la nube,
// con NODE_PATH apuntando a donde estén) y Chromium.

const path = require('node:path');
const assert = require('node:assert/strict');
const D = require('./ayudas/destellos.js');

let playwright;
let axeRuta;
try {
  playwright = require('playwright');
  axeRuta = require.resolve('axe-core/axe.min.js');
} catch (e) {
  console.error('Faltan los paquetes playwright y axe-core: npm i --no-save playwright axe-core');
  process.exit(2);
}

const URL_APP = 'file://' + path.resolve(__dirname, '..', 'index.html');
const ETIQUETAS_AXE = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];
const MODOS = ['milkdrop', 'espectro', 'notas', 'osciloscopio', 'particulas', 'lissajous3d'];
const paso = function (t) { console.log('· ' + t); };

/** Mide cuadro a cuadro lo que se ve en el lienzo: una rejilla de 16 x 9 de luminancias relativas. */
function muestrear(pagina, segundos) {
  return pagina.evaluate(async function (seg) {
    const vis = window.MusicaInfinita.estado.vis;
    const original = vis.dibujar;
    const chica = document.createElement('canvas');
    chica.width = 320;
    chica.height = 180;
    const c = chica.getContext('2d', { willReadFrequently: true });
    c.imageSmoothingEnabled = true;
    c.imageSmoothingQuality = 'high';
    const frames = [];
    vis.dibujar = function () {
      original.call(vis);
      // lo que ve la persona: el lienzo sobre el fondo de la página, y la luminancia PROMEDIO de cada
      // celda de la rejilla de 16 x 9 (una línea fina y brillante no cambia la luminancia de una
      // celda de 80 x 80 píxeles: muestrear un solo píxel por celda daría saltos que no existen)
      c.fillStyle = '#05060a';
      c.fillRect(0, 0, 320, 180);
      c.drawImage(vis.canvas, 0, 0, 320, 180);
      const d = c.getImageData(0, 0, 320, 180).data;
      const lum = new Array(144).fill(0);
      for (let y = 0; y < 180; y++) {
        for (let x = 0; x < 320; x++) {
          const i = (y * 320 + x) * 4;
          lum[Math.floor(y / 20) * 16 + Math.floor(x / 20)] += window.Destellos.luminancia(d[i], d[i + 1], d[i + 2]) / 400;
        }
      }
      frames.push({ t: performance.now() / 1000, lum: lum });
    };
    await new Promise(function (ok) { setTimeout(ok, seg * 1000); });
    vis.dibujar = original;
    return frames;
  }, segundos);
}

function movimiento(frames) {
  let suma = 0;
  for (let i = 1; i < frames.length; i++) {
    let s = 0;
    for (let k = 0; k < frames[i].lum.length; k++) s += Math.abs(frames[i].lum[k] - frames[i - 1].lum[k]);
    suma += s / frames[i].lum.length;
  }
  return suma / Math.max(1, frames.length - 1);
}

async function axe(pagina, nombre) {
  const r = await pagina.evaluate(function (etiquetas) {
    return window.axe.run(document, { runOnly: { type: 'tag', values: etiquetas }, resultTypes: ['violations', 'incomplete'] });
  }, ETIQUETAS_AXE);
  const detalle = function (v) { return v.id + ' (' + v.impact + '): ' + v.nodes.slice(0, 3).map(function (n) { return n.target.join(' '); }).join(' | ') + ' — ' + v.help; };
  assert.equal(r.violations.length, 0, nombre + ': axe encontró violaciones:\n  ' + r.violations.map(detalle).join('\n  '));
  const otras = r.incomplete.filter(function (v) { return v.id !== 'color-contrast'; });
  assert.equal(otras.length, 0, nombre + ': axe dejó por revisar algo que no es contraste: ' + otras.map(detalle).join('; '));
  return r.incomplete.length;
}

async function principal() {
  const opciones = { args: ['--autoplay-policy=no-user-gesture-required'] };
  if (process.env.CHROMIUM) opciones.executablePath = process.env.CHROMIUM;
  const navegador = await playwright.chromium.launch(opciones);
  const errores = [];
  const inicial = "localStorage.setItem('musica-infinita:ajustes', JSON.stringify({ semilla: 'quieta', fidelidad: 0.7, estilo: 'pop', autoVisual: false }));";

  // ================================================================ contexto normal
  const ctx = await navegador.newContext({ viewport: { width: 1280, height: 720 } });
  await ctx.addInitScript(inicial);
  const pagina = await ctx.newPage();
  pagina.on('pageerror', function (e) { errores.push(e.message); });
  // axe intenta leer las hojas de estilo por XHR y bajo file:// Chromium lo bloquea (CORS): es ruido de axe, no de la aplicación
  pagina.on('console', function (m) { if (m.type() === 'error' && m.text().indexOf('estilo.css') < 0 && m.location().url.indexOf('estilo.css') < 0) errores.push(m.text() + ' @ ' + m.location().url); });
  await pagina.goto(URL_APP);
  await pagina.addScriptTag({ path: axeRuta });
  await pagina.addScriptTag({ path: path.resolve(__dirname, 'ayudas', 'destellos.js') });

  // ---- 1 y 2: la bienvenida
  const sinDecidir = [];
  sinDecidir.push(await axe(pagina, 'bienvenida'));
  assert.equal(await pagina.evaluate(function () { return document.activeElement.id; }), 'empezar', 'el foco debe empezar en «Empezar»');
  assert.equal(await pagina.evaluate(function () { return document.getElementById('aplicacion').inert; }), true, 'la aplicación debe estar inerte tras la bienvenida');
  for (let i = 0; i < 12; i++) {
    await pagina.keyboard.press('Tab');
    const dentro = await pagina.evaluate(function () { return !!document.activeElement.closest('#aplicacion'); });
    assert.equal(dentro, false, 'el Tab entró en la aplicación (que está detrás de la bienvenida) en la pulsación ' + (i + 1));
  }
  paso('la bienvenida: sin violaciones de axe, foco en «Empezar» y el Tab no se escapa detrás');

  // ---- empezar con el teclado
  await pagina.focus('#empezar');
  await pagina.keyboard.press('Enter');
  await pagina.waitForTimeout(1500);
  assert.equal(await pagina.evaluate(function () { return document.getElementById('aplicacion').inert; }), false);
  assert.equal(await pagina.evaluate(function () { return document.getElementById('inicio').hidden; }), true);
  await pagina.evaluate(function () { document.activeElement.blur(); });
  await pagina.keyboard.press('Tab');
  assert.equal(await pagina.evaluate(function () { return document.activeElement.id; }), 'play', 'el primer Tab debe llegar al botón de reproducir');
  // el espacio pausa y reanuda desde el teclado (atajo de la aplicación), sin que un botón con foco lo duplique
  paso('empezar con Enter: la aplicación deja de estar inerte y el Tab llega al primer control');

  // ---- 3: sin aria-live en la pantallita
  assert.equal(await pagina.evaluate(function () { return document.getElementById('pantallita').hasAttribute('aria-live'); }), false);
  assert.equal(await pagina.evaluate(function () { return document.getElementById('aviso').getAttribute('role'); }), 'status');
  assert.equal(await pagina.evaluate(function () { return document.getElementById('lienzo').getAttribute('role'); }), 'img');

  // ---- 1: sonando y con el panel abierto
  sinDecidir.push(await axe(pagina, 'sonando'));
  await pagina.evaluate(function () {
    document.getElementById('ajustes').hidden = false;
    window.MusicaInfinita.estado.panelFijo = true;
    for (const d of document.querySelectorAll('details')) d.open = true;
  });
  await pagina.waitForTimeout(500);
  sinDecidir.push(await axe(pagina, 'panel abierto'));
  paso('axe (WCAG 2.2 AA y buenas prácticas): cero violaciones en la bienvenida, sonando y con el panel abierto; por revisar (contraste sobre el lienzo): ' + sinDecidir.join(', '));
  await pagina.evaluate(function () { document.getElementById('ajustes').hidden = true; });

  // ---- 4: destellos
  const resumen = [];
  for (const estilo of ['pop', 'chip']) {
    await pagina.evaluate(function (e) { window.MusicaInfinita.estado.ajustes.estilo = e; window.MusicaInfinita.estado.gen.configurar({ estilo: e }); window.MusicaInfinita.siguienteSeccion(); }, estilo);
    await pagina.waitForTimeout(800);
    for (const modo of MODOS) {
      await pagina.evaluate(function (m) { window.MusicaInfinita.estado.vis.ponerModo(m); }, modo);
      await pagina.waitForTimeout(700);
      const frames = await muestrear(pagina, 6);
      const r = D.analizar(frames);
      const fps = frames.length / (frames[frames.length - 1].t - frames[0].t);
      resumen.push(estilo + '/' + modo + ': ' + r.maxPorSegundo + ' (' + fps.toFixed(0) + ' c/s)');
      assert.ok(frames.length > 40, estilo + '/' + modo + ': solo ' + frames.length + ' cuadros muestreados');
      assert.ok(r.maxPorSegundo <= 3, estilo + '/' + modo + ': ' + r.maxPorSegundo + ' destellos por segundo a partir de ' + r.en.toFixed(2) + ' s (el tope de la WCAG 2.3.1 es 3)');
    }
  }
  paso('destellos por segundo (máximo permitido: 3): ' + resumen.join(', '));

  // control positivo: el mismo método tiene que detectar un estroboscopio de verdad (blanco y negro a 10 Hz
  // dibujado en el mismo lienzo); si no, un «0» de arriba no querría decir nada
  await pagina.evaluate(function () {
    const vis = window.MusicaInfinita.estado.vis;
    vis.ponerModo('osciloscopio');
    window.__dibujarReal = vis.dibujar;
    vis.dibujar = function () {
      if (!vis.c2d) vis.c2d = vis.canvas.getContext('2d');
      vis.c2d.fillStyle = Math.floor(performance.now() / 50) % 2 ? '#fff' : '#000';
      vis.c2d.fillRect(0, 0, vis.canvas.width, vis.canvas.height);
    };
  });
  await pagina.waitForTimeout(300);
  const estrobo = D.analizar(await muestrear(pagina, 3));
  await pagina.evaluate(function () { window.MusicaInfinita.estado.vis.dibujar = window.__dibujarReal; });
  assert.ok(estrobo.maxPorSegundo >= 8, 'el control positivo (estroboscopio a 10 Hz) dio solo ' + estrobo.maxPorSegundo + ' destellos: la medición no sirve');
  paso('control positivo: un estroboscopio de 10 Hz en el mismo lienzo se mide como ' + estrobo.maxPorSegundo + ' destellos por segundo');
  const normal = await (async function () {
    await pagina.evaluate(function () { window.MusicaInfinita.estado.vis.ponerModo('milkdrop'); window.MusicaInfinita.siguienteSeccion(); });
    await pagina.waitForTimeout(1500);
    return movimiento(await muestrear(pagina, 6));
  })();
  assert.equal(await pagina.evaluate(function () { return window.MusicaInfinita.estado.vis.movimientoReducido; }), false);
  assert.equal(await pagina.evaluate(function () { return document.getElementById('movimiento-reducido').checked; }), false);
  await ctx.close();

  // ================================================================ 5: el sistema pide menos movimiento
  const ctxR = await navegador.newContext({ viewport: { width: 1280, height: 720 }, reducedMotion: 'reduce' });
  await ctxR.addInitScript(inicial);
  const pr = await ctxR.newPage();
  pr.on('pageerror', function (e) { errores.push(e.message); });
  await pr.goto(URL_APP);
  await pr.addScriptTag({ path: path.resolve(__dirname, 'ayudas', 'destellos.js') });
  assert.equal(await pr.evaluate(function () { return document.getElementById('movimiento-reducido').checked; }), true, 'con la preferencia del sistema, «menos movimiento» debe venir encendido');
  await pr.click('#empezar');
  await pr.waitForTimeout(1500);
  assert.equal(await pr.evaluate(function () { return window.MusicaInfinita.estado.vis.movimientoReducido; }), true);
  await pr.evaluate(function () { window.MusicaInfinita.estado.vis.ponerModo('milkdrop'); window.MusicaInfinita.siguienteSeccion(); });
  await pr.waitForTimeout(1500);
  const reducido = movimiento(await muestrear(pr, 6));
  assert.ok(reducido < normal * 0.8, 'con menos movimiento la MilkDrop cambia ' + reducido.toFixed(4) + ' por cuadro y sin él ' + normal.toFixed(4) + ' (debería ser menos del 80 %)');
  // la persona puede decidir otra cosa, y se recuerda
  await pr.evaluate(function () { window.MusicaInfinita.estado.panelFijo = true; });
  await pr.mouse.move(300, 300);
  await pr.uncheck('#movimiento-reducido');
  assert.equal(await pr.evaluate(function () { return window.MusicaInfinita.estado.vis.movimientoReducido; }), false);
  assert.equal(await pr.evaluate(function () { return JSON.parse(localStorage.getItem('musica-infinita:ajustes')).movimientoReducido; }), false);
  // sin transiciones en los paneles (la regla CSS de prefers-reduced-motion)
  assert.equal(await pr.evaluate(function () { return getComputedStyle(document.getElementById('panel')).transitionDuration; }), '0s');
  paso('menos movimiento: se enciende con la preferencia del sistema, la MilkDrop se mueve ' + (100 * reducido / normal).toFixed(0) + ' % de lo normal, se puede cambiar y se recuerda');
  await ctxR.close();

  await navegador.close();
  assert.deepEqual(errores, [], 'errores en la página');
  console.log('Todo bien.');
}

principal().catch(function (e) {
  console.error(e);
  process.exit(1);
});
