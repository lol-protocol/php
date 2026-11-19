'use strict';

// Prueba de la aplicación en Chromium (Playwright): que arranque y suene, que las cuatro
// visualizaciones dibujen sin errores, que cargue un MIDI y una SoundFont, que toque un
// MIDI tal cual y que exporte un FLAC válido.
//
//   node pruebas/navegador.js
//
// Necesita el paquete playwright (en la CI se instala; en las sesiones en la nube ya está)
// y Chromium. Si la variable CHROMIUM apunta a un ejecutable, se usa ese.

const path = require('node:path');
const { spawnSync } = require('node:child_process');
const assert = require('node:assert/strict');
const M = require('../js/midi.js');
const D = require('./ayudas/flac-decodificador.js');
const minima = require('./ayudas/sf2-minimo.js');

let playwright;
try {
  playwright = require('playwright');
} catch (e) {
  console.error('Falta el paquete playwright: npm i --no-save playwright');
  process.exit(2);
}

async function principal() {
  const opciones = { args: ['--autoplay-policy=no-user-gesture-required'] };
  if (process.env.CHROMIUM) opciones.executablePath = process.env.CHROMIUM;
  const navegador = await playwright.chromium.launch(opciones);
  const pagina = await navegador.newPage({ viewport: { width: 1100, height: 700 }, acceptDownloads: true });
  const errores = [];
  pagina.on('pageerror', function (e) { errores.push(e.message); });
  pagina.on('console', function (m) {
    if (m.type() === 'error') errores.push(m.text());
    // WebGL avisa de errores de uso (INVALID_OPERATION...) como "warning"
    if (m.type() === 'warning' && /INVALID_|WebGL: /.test(m.text())) errores.push(m.text());
  });
  const paso = function (t) { console.log('· ' + t); };

  await pagina.goto('file://' + path.resolve(__dirname, '..', 'index.html'));
  await pagina.click('#empezar');
  await pagina.waitForTimeout(4000);
  const e1 = await pagina.evaluate(function () {
    const s = window.MusicaInfinita.estado;
    return { estado: s.ctx.state, t: s.ctx.currentTime, voces: s.motor.voces.length, titulo: document.getElementById('titulo').textContent, canciones: s.canciones.length };
  });
  assert.equal(e1.estado, 'running');
  assert.ok(e1.t > 2, 'el audio no avanza');
  assert.ok(e1.voces > 0, 'no suena nada');
  assert.ok(e1.titulo.indexOf('♪') === 0, 'la pantallita no dice qué suena');
  assert.equal(e1.canciones, 7);
  paso('arranca y suena: ' + e1.titulo);

  for (const modo of ['espectro', 'notas', 'osciloscopio', 'milkdrop']) {
    await pagina.mouse.move(200, 300);
    await pagina.selectOption('#modo-visual', modo);
    await pagina.waitForTimeout(800);
    const actual = await pagina.evaluate(function () { return window.MusicaInfinita.estado.vis.modo; });
    assert.equal(actual, modo);
  }
  paso('las cuatro visualizaciones');

  // cargar un MIDI y la SoundFont mínima como si se arrastraran
  const midi = M.escribir({
    bpm: 100,
    pistas: [{ nombre: 'Melody', canal: 0, programa: 0, notas: [60, 62, 64, 65, 67, 65, 64, 62, 60, 64, 67, 72].map(function (n, i) { return { nota: n, inicio: i, dur: 0.9 }; }) }],
  });
  const sf2 = minima.crear();
  await pagina.evaluate(async function (datos) {
    const archivos = [new File([new Uint8Array(datos.midi)], 'mi-cancion.mid'), new File([new Uint8Array(datos.sf2)], 'prueba.sf2')];
    await window.MusicaInfinita.cargarArchivos(archivos);
  }, { midi: Array.from(midi), sf2: Array.from(sf2) });
  await pagina.waitForTimeout(500);
  const e2 = await pagina.evaluate(function () {
    const s = window.MusicaInfinita.estado;
    const c = s.canciones.find(function (x) { return x.nombre === 'mi-cancion'; });
    return { hay: !!c, analizada: !!(c && c.analisis), sf: !!s.motor.sf, filas: document.querySelectorAll('#lista li').length, estadoSf: document.getElementById('estado-sf').textContent };
  });
  assert.ok(e2.hay && e2.analizada, 'el MIDI cargado no se analizó');
  assert.ok(e2.sf, 'la SoundFont no quedó en el motor');
  assert.equal(e2.filas, 8);
  paso('carga un MIDI y una SoundFont: ' + e2.estadoSf);

  // tocar el MIDI tal cual, con la SoundFont
  await pagina.evaluate(function () {
    document.getElementById('ajustes').hidden = false;
  });
  await pagina.mouse.move(300, 300);
  const fila = pagina.locator('#lista li', { hasText: 'mi-cancion' });
  await fila.locator('button', { hasText: 'tal cual' }).click();
  await pagina.waitForTimeout(1500);
  const e3 = await pagina.evaluate(function () {
    const s = window.MusicaInfinita.estado;
    return { modo: s.modo, voces: s.motor.voces.length, titulo: document.getElementById('titulo').textContent };
  });
  assert.equal(e3.modo, 'midi');
  assert.ok(e3.voces > 0);
  paso('toca el MIDI tal cual: ' + e3.titulo);
  await pagina.keyboard.press('n'); // vuelve a la música infinita
  await pagina.waitForTimeout(800);
  assert.equal(await pagina.evaluate(function () { return window.MusicaInfinita.estado.modo; }), 'infinito');

  // el motor de SoundFont: vibrato, LFO, envolvente de modulación y moduladores, sin errores y sonando
  const modulado = await pagina.evaluate(async function (datos) {
    const SF = window.SoundFont;
    const sf = SF.leer(new Uint8Array(datos));
    const ctx = new OfflineAudioContext(2, 48000 * 3, 48000);
    const motor = new window.Sintetizador.Motor(ctx, { volumen: 1 });
    motor.ponerSoundFont(sf);
    // programa 1 «Modulado», programa 2 «Estéreo» y el piano
    for (const [prog, nota] of [[1, 69], [1, 60], [2, 69], [0, 57]]) {
      motor.tocar({ canal: 0, programa: prog, nota: nota, vel: 100, dur: 1.5, voz: 'melodia' }, 0.1);
    }
    const buf = await ctx.startRendering();
    let pico = 0;
    let finito = true;
    for (let c = 0; c < 2; c++) for (const v of buf.getChannelData(c)) { if (!Number.isFinite(v)) finito = false; pico = Math.max(pico, Math.abs(v)); }
    return { pico: pico, finito: finito };
  }, Array.from(sf2));
  assert.ok(modulado.finito && modulado.pico > 0.01, 'la SoundFont modulada no suena: ' + JSON.stringify(modulado));
  paso('SoundFont con vibrato, LFO y envolvente de modulación: suena (pico ' + modulado.pico.toFixed(2) + ')');

  // SoundFont 3: muestras en Ogg Vorbis hechas con ffmpeg, si está
  const ogg = spawnSync('ffmpeg', ['-v', 'error', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1', '-ar', '22050', '-c:a', 'libvorbis', '-f', 'ogg', 'pipe:1'], { maxBuffer: 1 << 24 });
  if (ogg.status === 0 && ogg.stdout.length > 100) {
    const sf3 = minima.crear({ ogg: [ogg.stdout] });
    const r3 = await pagina.evaluate(async function (datos) {
      const SF = window.SoundFont;
      const sf = SF.leer(new Uint8Array(datos));
      const ctx = new OfflineAudioContext(2, 48000 * 2, 48000);
      const motor = new window.Sintetizador.Motor(ctx, { volumen: 1 });
      motor.ponerSoundFont(sf);
      const ev = { canal: 0, programa: 0, nota: 57, vel: 100, dur: 1, voz: 'melodia' };
      const antes = SF.bufferDecodificado(sf, 1) === undefined;
      await motor.precargarSf([ev]);
      const buf = SF.bufferDecodificado(sf, 1);
      motor.tocar(ev, 0.1);
      const sal = await ctx.startRendering();
      let pico = 0;
      for (const v of sal.getChannelData(0)) pico = Math.max(pico, Math.abs(v));
      return { antes: antes, largo: buf ? buf.length : 0, pico: pico };
    }, Array.from(sf3));
    assert.ok(r3.antes, 'la muestra comprimida no debería estar decodificada de antemano');
    assert.ok(r3.largo > 10000, 'la muestra Ogg no se decodificó');
    assert.ok(r3.pico > 0.01, 'la SoundFont 3 no suena');
    paso('SoundFont 3 (Ogg Vorbis): decodifica y suena (' + r3.largo + ' muestras)');
  } else {
    paso('SoundFont 3: se salta (ffmpeg con libvorbis no está)');
  }

  // exportar 15 s en FLAC (con la SoundFont de prueba)
  const descarga = pagina.waitForEvent('download', { timeout: 180000 });
  await pagina.evaluate(function () { return window.MusicaInfinita.exportarAudio(0.25, 'flac16', 'prueba', function () {}); });
  const d = await descarga;
  const ruta = await d.path();
  const bytes = new Uint8Array(require('node:fs').readFileSync(ruta));
  const flac = D.decodificar(bytes);
  assert.ok(flac.md5Coincide);
  assert.equal(flac.info.frecuencia, 48000);
  assert.ok(Math.abs(flac.info.total / 48000 - 19) < 0.01, 'dura ' + flac.info.total / 48000 + ' s');
  let pico = 0;
  for (const v of flac.canales[0]) pico = Math.max(pico, Math.abs(v));
  assert.ok(pico > 1000, 'el FLAC exportado está en silencio');
  paso('exporta un FLAC válido de ' + (flac.info.total / 48000).toFixed(1) + ' s (' + d.suggestedFilename() + ')');

  await navegador.close();
  assert.deepEqual(errores, [], 'errores en la página');
  console.log('Todo bien.');
}

principal().catch(function (e) {
  console.error(e);
  process.exit(1);
});
