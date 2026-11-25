'use strict';

// Prueba de la aplicación en Chromium (Playwright): que arranque y suene, que las seis
// visualizaciones dibujen de verdad, que el panel de ajustes de la MilkDrop funcione, que cargue un MIDI y una SoundFont, que toque un
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

  // cada visualización dibuja de verdad: se cuenta cuántos píxeles brillantes deja un cuadro
  const MODOS = ['espectro', 'notas', 'osciloscopio', 'particulas', 'lissajous3d', 'milkdrop'];
  const brillantes = {};
  for (const modo of MODOS) {
    await pagina.mouse.move(200, 300);
    await pagina.selectOption('#modo-visual', modo);
    await pagina.waitForTimeout(1500);
    const r = await pagina.evaluate(function () {
      const vis = window.MusicaInfinita.estado.vis;
      vis.dibujar(); // en la misma tarea, para poder leer el cuadro de WebGL
      let n = 0;
      if (vis.gl) {
        const gl = vis.gl;
        const w = gl.drawingBufferWidth;
        const h = gl.drawingBufferHeight;
        const px = new Uint8Array(w * h * 4);
        gl.readPixels(0, 0, w, h, gl.RGBA, gl.UNSIGNED_BYTE, px);
        for (let i = 0; i < px.length; i += 16) if (px[i] > 40 || px[i + 1] > 40 || px[i + 2] > 40) n++;
      } else {
        const d = vis.c2d.getImageData(0, 0, vis.canvas.width, vis.canvas.height).data;
        for (let i = 0; i < d.length; i += 16) if (d[i] > 40 || d[i + 1] > 40 || d[i + 2] > 40) n++;
      }
      return { modo: vis.modo, n: n, id: vis.canvas.id, gl: !!vis.gl };
    });
    assert.equal(r.modo, modo);
    assert.equal(r.id, 'lienzo', 'el lienzo nuevo perdió su id');
    assert.equal(r.gl, modo === 'milkdrop');
    assert.ok(r.n > 300, modo + ' no dibuja nada (píxeles brillantes: ' + r.n + ')');
    brillantes[modo] = r.n;
  }
  paso('las seis visualizaciones dibujan: ' + MODOS.map(function (m) { return m + ' ' + brillantes[m]; }).join(', '));

  // reaccionan al estilo de la música: el color y los ajustes siguen al de la sección
  const estilo = await pagina.evaluate(async function () {
    const vis = window.MusicaInfinita.estado.vis;
    const VP = window.VisualPuro;
    // la música en vivo también manda sus avisos de sección y cambiaría el color objetivo a mitad
    // de la prueba: se les cierra el paso mientras se prueba a mano
    const real = vis.alInfo;
    vis.alInfo = function () {};
    vis.fijo = false;
    vis.matiz = 0;
    real.call(vis, { seccion: 9001, estilo: 'Chiptune' });
    const grupo = VP.ESTILO_PRESETS.Chiptune;
    const enGrupo = grupo.indexOf(vis.presetObjetivo.nombre) >= 0;
    const objetivo = vis.matizObjetivo;
    await new Promise(function (ok) { setTimeout(ok, 1500); });
    const despues = vis.matiz;
    // fijados, no cambian aunque cambie la sección
    vis.fijo = true;
    const antes = vis.presetObjetivo.nombre;
    real.call(vis, { seccion: 9002, estilo: 'Pop' });
    const r = { enGrupo: enGrupo, objetivo: objetivo, despues: despues, fijoIgual: vis.presetObjetivo.nombre === antes, matizPop: vis.matizObjetivo };
    vis.alInfo = real;
    return r;
  });
  assert.ok(estilo.enGrupo, 'el ajuste no es del grupo del estilo');
  assert.equal(estilo.objetivo, 125);
  assert.ok(estilo.despues > 30 && estilo.despues < 125 + 1, 'el color no se acerca al del estilo: ' + estilo.despues);
  assert.ok(estilo.fijoIgual, 'fijado, el ajuste cambió igual');
  assert.equal(estilo.matizPop, 320, 'fijar el ajuste no debe fijar el color');
  paso('el color y los ajustes siguen al estilo de la música');
  await pagina.evaluate(function () { window.MusicaInfinita.estado.vis.fijo = false; });

  // el panel de ajustes de la MilkDrop: elegir, editar, guardar, exportar, importar y borrar
  await pagina.mouse.move(300, 300);
  await pagina.evaluate(function () { document.getElementById('ajustes').hidden = false; });
  assert.ok(await pagina.locator('#panel-visual').isVisible(), 'el panel de la MilkDrop no aparece');
  await pagina.selectOption('#preset', 'Aurora');
  assert.equal(await pagina.evaluate(function () { return window.MusicaInfinita.estado.vis.presetObjetivo.nombre; }), 'Aurora');
  await pagina.evaluate(function () {
    const i = document.getElementById('preset-zoom');
    i.value = '1.05';
    i.dispatchEvent(new Event('input'));
    const c = document.getElementById('preset-caleido');
    c.checked = true;
    c.dispatchEvent(new Event('change'));
  });
  const editado = await pagina.evaluate(function () { return window.MusicaInfinita.estado.vis.presetEnUso(); });
  assert.ok(Math.abs(editado.zoom - 1.05) < 1e-9 && editado.caleido === 1);
  pagina.once('dialog', function (d) { d.accept('Mi ajuste'); });
  await pagina.mouse.move(310, 300);
  await pagina.click('#preset-guardar');
  await pagina.waitForTimeout(200);
  const guardado = await pagina.evaluate(function () {
    return { lista: window.MusicaInfinita.estado.vis.presetsUsuario.map(function (p) { return p.nombre; }), ls: JSON.parse(localStorage.getItem('musica-infinita:presets') || '[]').map(function (p) { return p.nombre; }), opciones: Array.from(document.querySelectorAll('#preset optgroup[label="Míos"] option')).map(function (o) { return o.value; }) };
  });
  assert.deepEqual(guardado.lista, ['Mi ajuste']);
  assert.deepEqual(guardado.ls, ['Mi ajuste']);
  assert.deepEqual(guardado.opciones, ['Mi ajuste']);
  // exportar: un JSON con el ajuste
  await pagina.mouse.move(320, 300);
  const descargaAjustes = pagina.waitForEvent('download', { timeout: 20000 });
  await pagina.click('#preset-exportar');
  const dj = await descargaAjustes;
  const exportado = JSON.parse(require('node:fs').readFileSync(await dj.path(), 'utf8'));
  assert.equal(exportado.presets.length, 1);
  assert.equal(exportado.presets[0].nombre, 'Mi ajuste');
  assert.ok(Math.abs(exportado.presets[0].zoom - 1.05) < 1e-9);
  // importar: dos válidos, uno roto y uno con un zoom fuera de rango (se recorta)
  const archivo = JSON.stringify({ presets: [Object.assign({}, exportado.presets[0], { nombre: 'Importado A' }), Object.assign({}, exportado.presets[0], { nombre: 'Túnel', zoom: 7 }), { nombre: 'roto' }] });
  await pagina.setInputFiles('#preset-importar', { name: 'ajustes.json', mimeType: 'application/json', buffer: Buffer.from(archivo) });
  await pagina.waitForTimeout(400);
  const importado = await pagina.evaluate(function () {
    const l = window.MusicaInfinita.estado.vis.presetsUsuario;
    return { nombres: l.map(function (p) { return p.nombre; }), zoomTunel: (l.find(function (p) { return p.nombre === 'Túnel (mío)'; }) || {}).zoom, aviso: document.getElementById('aviso').textContent };
  });
  assert.deepEqual(importado.nombres.sort(), ['Importado A', 'Mi ajuste', 'Túnel (mío)']);
  assert.ok(importado.zoomTunel > 1.07 && importado.zoomTunel <= 1.08, 'el zoom importado no se recortó: ' + importado.zoomTunel);
  assert.ok(/2 ajustes importados.*1 descartados/.test(importado.aviso), importado.aviso);
  // un archivo que no es JSON no rompe nada
  await pagina.setInputFiles('#preset-importar', { name: 'malo.json', mimeType: 'application/json', buffer: Buffer.from('esto no es json') });
  await pagina.waitForTimeout(300);
  assert.ok(/No se pudo importar/.test(await pagina.evaluate(function () { return document.getElementById('aviso').textContent; })));
  // borrar uno propio; los de fábrica no se borran
  await pagina.mouse.move(330, 300);
  await pagina.selectOption('#preset', 'Importado A');
  await pagina.click('#preset-borrar');
  assert.ok(!(await pagina.evaluate(function () { return window.MusicaInfinita.estado.vis.presetsUsuario.some(function (p) { return p.nombre === 'Importado A'; }); })));
  await pagina.selectOption('#preset', 'Túnel');
  await pagina.click('#preset-borrar');
  assert.ok(await pagina.evaluate(function () { return window.MusicaInfinita.estado.vis.listaPresets().some(function (p) { return p.nombre === 'Túnel'; }); }));
  paso('el panel de ajustes: elige, edita, guarda, exporta, importa (con recorte y descartes) y borra');

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

  // sesiones largas: ni las voces, ni las notas de la visualización, ni los avisos crecen sin tope
  const largo = await pagina.evaluate(async function () {
    const ctx = new OfflineAudioContext(2, 48000 * 2, 48000);
    const motor = new window.Sintetizador.Motor(ctx, { maxVoces: 40, volumen: 1 });
    for (let i = 0; i < 600; i++) motor.tocar({ canal: i % 3, programa: [0, 48, 33][i % 3], nota: 40 + (i % 40), vel: 90, dur: 0.5, voz: 'melodia' }, i * 0.003);
    const vis = window.MusicaInfinita.estado.vis;
    for (let i = 0; i < 5000; i++) vis.nota({ nota: 60, voz: 'melodia', dur: 1, vel: 90, canal: 0 }, i);
    return { voces: motor.voces.length, notas: vis.notas.length, infos: window.MusicaInfinita.estado.infos.length };
  });
  assert.ok(largo.voces <= 40, 'voces sin tope: ' + largo.voces);
  assert.ok(largo.notas <= 1500, 'notas de la visualización sin tope: ' + largo.notas);
  assert.ok(largo.infos < 50, 'avisos de sección acumulados: ' + largo.infos);
  paso('sesión larga: voces ≤ ' + largo.voces + ', notas de la visualización ≤ ' + largo.notas);

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

  // elegir a mano qué pista es la melodía: un MIDI con un arpegio de piano (que el automático toma por
  // melodía) y una melodía grave de violonchelo; la elección se guarda y sobrevive a recargar la página
  const arpegio = [];
  for (let i = 0; i < 64; i++) arpegio.push({ nota: [60, 64, 67, 72, 67, 64][i % 6], inicio: i * 0.5, dur: 0.45, vel: 80 });
  const cello = [48, 50, 52, 53, 55, 53, 52, 50, 48, 52, 55, 52, 50, 48, 47, 48, 50, 52, 55, 57, 55, 53, 52, 48].map(function (n, i) { return { nota: n, inicio: i * 1.5, dur: 1.4, vel: 90 }; });
  const dosPistas = M.escribir({ bpm: 100, pistas: [{ nombre: 'Piano', canal: 0, programa: 0, notas: arpegio }, { nombre: 'Cello', canal: 1, programa: 42, notas: cello }] });
  await pagina.evaluate(async function (bytes) {
    await window.MusicaInfinita.cargarArchivos([new File([new Uint8Array(bytes)], 'dos-pistas.mid')]);
  }, Array.from(dosPistas));
  await pagina.waitForTimeout(500);
  const antesPista = await pagina.evaluate(function () {
    const c = window.MusicaInfinita.estado.canciones.find(function (x) { return x.nombre === 'dos-pistas'; });
    const fila = Array.from(document.querySelectorAll('#lista li')).find(function (li) { return li.textContent.indexOf('dos-pistas') >= 0; });
    const sel = fila && fila.querySelector('select.pista');
    return { notas: c.analisis.melodia.length, auto: c.analisis.melodiaAutomatica, opciones: sel ? Array.from(sel.options).map(function (o) { return o.textContent; }) : null, valor: sel ? sel.value : null };
  });
  assert.equal(antesPista.notas, 64, 'el automático debería tomar el arpegio');
  assert.ok(antesPista.auto);
  assert.ok(antesPista.opciones && antesPista.opciones.length === 4, 'el selector debe tener: automática, 2 pistas y «todas»: ' + JSON.stringify(antesPista.opciones));
  assert.match(antesPista.opciones[0], /^Melodía: automática \(pista \d+ «Piano»/);
  assert.match(antesPista.opciones[2], /«Cello» · canal 2 · 24 notas/);
  assert.match(antesPista.opciones[3], /^todas las pistas juntas/);
  await pagina.evaluate(function () { document.getElementById('ajustes').hidden = false; window.MusicaInfinita.estado.panelFijo = true; });
  await pagina.mouse.move(300, 300);
  const filaDos = pagina.locator('#lista li', { hasText: 'dos-pistas' });
  await filaDos.locator('select.pista').selectOption({ label: antesPista.opciones[2] });
  await pagina.waitForTimeout(300);
  const despuesPista = await pagina.evaluate(function () {
    const c = window.MusicaInfinita.estado.canciones.find(function (x) { return x.nombre === 'dos-pistas'; });
    return { notas: c.analisis.melodia.length, auto: c.analisis.melodiaAutomatica, programa: c.analisis.programaMelodia, guardado: JSON.parse(localStorage.getItem('musica-infinita:pistas')), id: c.id, aviso: document.getElementById('aviso').textContent };
  });
  assert.equal(despuesPista.notas, 24);
  assert.equal(despuesPista.auto, false);
  assert.equal(despuesPista.programa, 42);
  assert.match(despuesPista.guardado[despuesPista.id], /^\d+:\d+$/);
  assert.match(despuesPista.aviso, /melodía elegida a mano/);
  // recargar: el MIDI sigue en la biblioteca (IndexedDB) y la elección también
  await pagina.reload();
  await pagina.waitForFunction(function () { return window.MusicaInfinita.estado.canciones.some(function (c) { return c.nombre === 'dos-pistas'; }); }, null, { timeout: 15000 });
  await pagina.evaluate(function () { document.getElementById('ajustes').hidden = false; window.MusicaInfinita.estado.panelFijo = true; });
  const trasRecargar = await pagina.evaluate(function () {
    const c = window.MusicaInfinita.estado.canciones.find(function (x) { return x.nombre === 'dos-pistas'; });
    const fila = Array.from(document.querySelectorAll('#lista li')).find(function (li) { return li.textContent.indexOf('dos-pistas') >= 0; });
    return { notas: c.analisis.melodia.length, auto: c.analisis.melodiaAutomatica, valorSelect: fila.querySelector('select.pista').value };
  });
  assert.equal(trasRecargar.notas, 24, 'la elección no sobrevivió a recargar');
  assert.equal(trasRecargar.auto, false);
  assert.ok(trasRecargar.valorSelect !== '');
  // volver a «automática»
  await pagina.mouse.move(320, 300);
  await pagina.locator('#lista li', { hasText: 'dos-pistas' }).locator('select.pista').selectOption({ index: 0 });
  await pagina.waitForTimeout(300);
  assert.equal(await pagina.evaluate(function () { return window.MusicaInfinita.estado.canciones.find(function (x) { return x.nombre === 'dos-pistas'; }).analisis.melodia.length; }), 64);
  assert.deepEqual(await pagina.evaluate(function () { return JSON.parse(localStorage.getItem('musica-infinita:pistas')); }), {});
  paso('elegir a mano la pista de melodía: re-analiza, se guarda, sobrevive a recargar y se puede volver a la automática');

  await navegador.close();
  assert.deepEqual(errores, [], 'errores en la página');
  console.log('Todo bien.');
}

principal().catch(function (e) {
  console.error(e);
  process.exit(1);
});
