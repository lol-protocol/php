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

  // ---------------------------------------------------------------- sesiones: favoritos, historial, listas, exportar e importar
  const respuestas = [];
  pagina.on('dialog', function (d) { d.accept(respuestas.length ? respuestas.shift() : d.defaultValue()); });
  await pagina.evaluate(function () {
    window.MusicaInfinita.estado.panelFijo = true; // que el panel no se esconda solo
    document.getElementById('ajustes').hidden = false;
    for (const id of ['s-favoritos', 's-listas', 's-historial', 's-sesion']) document.getElementById(id).open = true;
  });
  await pagina.waitForTimeout(500);
  const sesion = function (f) { return pagina.evaluate(f); };
  // ajustes conocidos y una semilla de la sesión, para guardarla como favorito
  await sesion(function () {
    for (const [id, v] of [['fidelidad', 0.8], ['contra', 0.9]]) {
      const el = document.getElementById(id);
      el.value = v;
      el.dispatchEvent(new Event('input'));
    }
  });
  await pagina.evaluate(function () { return window.MusicaInfinita.aplicarConfiguracion({ semilla: 'semilla-uno', ajustes: { estilo: 'pop', fidelidad: 0.8, contra: 0.9 }, canciones: window.MusicaInfinita.estado.canciones.filter(function (c) { return c.analisis; }).map(function (c) { return c.id; }), generador: window.Generador.VERSION }); });
  await pagina.waitForTimeout(900);
  respuestas.push('Mi favorita');
  await pagina.click('#fav-guardar');
  await pagina.waitForTimeout(300);
  let ses = await sesion(function () {
    const d = window.MusicaInfinita.estado.panelSesiones.datos;
    return { n: d.favoritos.length, f: d.favoritos[0], filas: document.querySelectorAll('#lista-fav li').length, guardado: JSON.parse(localStorage.getItem('musica-infinita:sesiones')).favoritos.length };
  });
  assert.equal(ses.n, 1);
  assert.equal(ses.f.nombre, 'Mi favorita');
  assert.equal(ses.f.semilla, 'semilla-uno');
  assert.equal(ses.f.ajustes.estilo, 'pop');
  assert.equal(ses.f.generador, 1);
  assert.equal(ses.filas, 1);
  assert.equal(ses.guardado, 1);
  // la misma configuración no se guarda dos veces
  respuestas.push('Otra vez');
  await pagina.click('#fav-guardar');
  await pagina.waitForTimeout(200);
  assert.equal(await sesion(function () { return window.MusicaInfinita.estado.panelSesiones.datos.favoritos.length; }), 1);
  paso('guarda un favorito con su semilla, ajustes y canciones, y no lo repite');

  // el historial lleva la corrida; mover un control en vivo la marca «con cambios»
  ses = await sesion(function () { return window.MusicaInfinita.estado.panelSesiones.datos.historial.filter(function (h) { return h.semilla === 'semilla-uno'; }).map(function (h) { return { limpia: h.limpia, secciones: h.secciones, estilos: h.estilos }; }); });
  assert.equal(ses.length, 1, 'la corrida de semilla-uno no está en el historial');
  assert.equal(ses[0].limpia, true);
  assert.deepEqual(ses[0].estilos, ['Pop']);
  await sesion(function () {
    const el = document.getElementById('contra');
    el.value = 0.1;
    el.dispatchEvent(new Event('input'));
    window.MusicaInfinita.siguienteSeccion();
  });
  await pagina.waitForTimeout(900);
  ses = await sesion(function () { return window.MusicaInfinita.estado.panelSesiones.datos.historial.filter(function (h) { return h.semilla === 'semilla-uno'; }).map(function (h) { return { limpia: h.limpia, contra: h.ajustes.contra }; }); });
  assert.equal(ses.length, 1);
  assert.equal(ses[0].limpia, false);
  assert.equal(ses[0].contra, 0.1);
  assert.ok((await sesion(function () { return document.querySelectorAll('#lista-hist li').length; })) >= 1);
  paso('el historial anota las corridas y marca las que tuvieron cambios en vivo');

  // tocar el favorito: vuelve a poner sus ajustes y suena su misma primera sección
  await sesion(function () {
    const MI = window.MusicaInfinita;
    for (const [id, v] of [['fidelidad', 0.1], ['contra', 0.0]]) {
      const el = document.getElementById(id);
      el.value = v;
      el.dispatchEvent(new Event('input'));
    }
    return MI.aplicarConfiguracion({ semilla: 'otra-semilla', ajustes: { estilo: 'chip' }, canciones: [], generador: 1 });
  });
  await pagina.waitForTimeout(500);
  await pagina.mouse.move(300, 300);
  await pagina.locator('#lista-fav li button', { hasText: '▶' }).first().click();
  await pagina.waitForTimeout(1200);
  const rep = await sesion(function () {
    const MI = window.MusicaInfinita;
    const e = MI.estado;
    const f = e.panelSesiones.datos.favoritos[0];
    const cs = f.canciones.map(function (id) { return e.canciones.find(function (c) { return c.id === id; }).analisis; });
    const g = new window.Generador.Generador(Object.assign({ canciones: cs, semilla: f.semilla }, f.ajustes));
    const info = g.eventosHasta(1).find(function (x) { return x.tipo === 'info'; });
    return {
      semilla: e.semillaSesion,
      fidelidad: Number(document.getElementById('fidelidad').value),
      contra: Number(document.getElementById('contra').value),
      estilo: document.getElementById('estilo').value,
      activas: e.canciones.filter(function (c) { return c.activo && c.analisis; }).length,
      de: f.canciones.length,
      detalle: document.getElementById('detalle').textContent,
      esperado: info.estilo + ' · ' + info.tonalidad + ' · ' + info.compas + ' · ' + Math.round(info.bpm) + ' bpm',
      ajustesGuardados: JSON.parse(localStorage.getItem('musica-infinita:ajustes')).semilla,
    };
  });
  assert.equal(rep.semilla, 'semilla-uno');
  assert.equal(rep.fidelidad, 0.8);
  assert.equal(rep.contra, 0.9);
  assert.equal(rep.estilo, 'pop');
  assert.equal(rep.activas, rep.de, 'las canciones en uso no son las del favorito');
  assert.ok(rep.detalle.indexOf(rep.esperado) >= 0, 'otra primera sección: «' + rep.detalle + '» no trae «' + rep.esperado + '»');
  assert.ok(rep.detalle.indexOf('semilla semilla-uno') >= 0);
  assert.equal(rep.ajustesGuardados, '', 'la semilla de un favorito no debe quedar guardada con los ajustes');
  paso('tocar un favorito repone sus ajustes y canciones y suena su misma primera sección: ' + rep.esperado);

  // una canción que falta se avisa y no rompe nada
  await sesion(function () {
    const d = window.MusicaInfinita.estado.panelSesiones.datos;
    d.favoritos.push(Object.assign({}, d.favoritos[0], { id: 'fantasma', nombre: 'Con canción borrada', semilla: 'sem-fantasma', canciones: d.favoritos[0].canciones.concat(['propio:no-existe:1']), generador: 99 }));
    window.MusicaInfinita.estado.panelSesiones.pintar();
  });
  await pagina.locator('#lista-fav li', { hasText: 'Con canción borrada' }).locator('button', { hasText: '▶' }).click();
  await pagina.waitForTimeout(700);
  const aviso = await sesion(function () { return document.getElementById('aviso').textContent; });
  assert.ok(/1 canción\(es\).*ya no están/.test(aviso) && /otra versión del compositor/.test(aviso), aviso);
  paso('un favorito con una canción borrada o de otra versión del compositor avisa y suena igual');

  // listas: favorito, MIDI tal cual y otro favorito; avanzan solas
  respuestas.push('Mi lista');
  await pagina.click('#lista-nueva');
  await pagina.waitForTimeout(200);
  await pagina.selectOption('#lista-agregar-fav', { index: 0 });
  await pagina.fill('#lista-minutos', '1');
  await pagina.click('#lista-agregar-fav-btn');
  await pagina.selectOption('#lista-agregar-midi', { label: 'mi-cancion' });
  await pagina.click('#lista-agregar-midi-btn');
  await pagina.selectOption('#lista-agregar-fav', { index: 1 });
  await pagina.fill('#lista-minutos', '5');
  await pagina.click('#lista-agregar-fav-btn');
  const l0 = await sesion(function () { return window.MusicaInfinita.estado.panelSesiones.datos.listas[0].items.map(function (i) { return i.tipo + ':' + i.minutos; }); });
  assert.deepEqual(l0, ['favorito:1', 'midi:undefined', 'favorito:5']);
  await pagina.click('#lista-tocar');
  await pagina.waitForTimeout(1500);
  const paso1 = await sesion(function () { const e = window.MusicaInfinita.estado; return { semilla: e.semillaSesion, modo: e.modo, indice: e.panelSesiones.lista().indice, texto: document.getElementById('lista-estado').textContent }; });
  assert.equal(paso1.semilla, 'semilla-uno');
  assert.equal(paso1.indice, 0);
  assert.match(paso1.texto, /elemento 1 de 3/);
  // se acaba el tiempo del favorito: sigue el MIDI tal cual
  await sesion(function () { const e = window.MusicaInfinita.estado; e.panelSesiones.lista().hasta = e.ctx.currentTime; });
  await pagina.waitForTimeout(1500);
  const paso2 = await sesion(function () { const e = window.MusicaInfinita.estado; return { modo: e.modo, midi: e.midiActual && e.midiActual.nombre, indice: e.panelSesiones.lista().indice }; });
  assert.deepEqual(paso2, { modo: 'midi', midi: 'mi-cancion', indice: 1 });
  // al terminar el MIDI, sigue el último favorito
  await pagina.waitForFunction(function () { return window.MusicaInfinita.estado.modo === 'infinito'; }, null, { timeout: 30000 });
  await pagina.waitForTimeout(800);
  const paso3 = await sesion(function () { const e = window.MusicaInfinita.estado; return { semilla: e.semillaSesion, indice: e.panelSesiones.lista().indice, favorito: e.panelSesiones.datos.favoritos[1].semilla }; });
  assert.equal(paso3.indice, 2);
  assert.equal(paso3.semilla, paso3.favorito);
  // al acabarse la lista, sigue la música infinita y la lista queda detenida
  await sesion(function () { const e = window.MusicaInfinita.estado; e.panelSesiones.lista().hasta = e.ctx.currentTime; });
  await pagina.waitForTimeout(1200);
  const fin = await sesion(function () { const e = window.MusicaInfinita.estado; return { lista: e.panelSesiones.lista(), modo: e.modo, tocando: e.tocando, aviso: document.getElementById('aviso').textContent }; });
  assert.equal(fin.lista, null);
  assert.equal(fin.modo, 'infinito');
  assert.ok(fin.tocando);
  assert.match(fin.aviso, /La lista terminó/);
  paso('una lista toca un favorito, un MIDI tal cual y otro favorito, avanzando sola, y al terminar sigue la música infinita');

  // una lista que termina en un MIDI reanuda la música infinita; y los elementos rotos se saltan
  await sesion(function () {
    const d = window.MusicaInfinita.estado.panelSesiones.datos;
    d.listas.push({ id: 'l-rota', nombre: 'Rota', modo: 'orden', repetir: false, items: [{ tipo: 'favorito', ref: 'no-existe', minutos: 1 }, { tipo: 'midi', ref: 'propio:mi-cancion:' + window.MusicaInfinita.estado.canciones.find(function (c) { return c.nombre === 'mi-cancion'; }).bytes.length }] });
    window.MusicaInfinita.estado.panelSesiones.tocarLista('l-rota');
  });
  await pagina.waitForTimeout(1500);
  assert.equal(await sesion(function () { return window.MusicaInfinita.estado.modo; }), 'midi', 'se saltó el roto y tocó el MIDI');
  await pagina.waitForFunction(function () { return window.MusicaInfinita.estado.modo === 'infinito'; }, null, { timeout: 30000 });
  await pagina.waitForTimeout(500);
  assert.equal(await sesion(function () { return window.MusicaInfinita.estado.panelSesiones.lista(); }), null);
  assert.ok(await sesion(function () { return window.MusicaInfinita.estado.tocando && window.MusicaInfinita.estado.motor.voces.length >= 0; }));
  paso('una lista que termina en un MIDI reanuda la música infinita, y salta lo que ya no existe');

  // exportar la sesión con los MIDI, borrar todo e importarla de vuelta
  await sesion(function () { const el = document.getElementById('fidelidad'); el.value = 0.35; el.dispatchEvent(new Event('input')); });
  await pagina.check('#ses-incluir-midi');
  const descargaSesion = pagina.waitForEvent('download', { timeout: 20000 });
  await pagina.click('#ses-exportar');
  const sj = await descargaSesion;
  const textoSesion = require('node:fs').readFileSync(await sj.path(), 'utf8');
  const exportada = JSON.parse(textoSesion);
  assert.equal(exportada.formato, 'musica-infinita-sesion');
  assert.equal(exportada.version, 1);
  assert.ok(exportada.favoritos.length >= 2 && exportada.listas.length >= 2);
  assert.equal(exportada.ajustes.fidelidad, 0.35);
  assert.deepEqual(exportada.canciones.filter(function (c) { return c.midi; }).map(function (c) { return c.nombre; }), ['mi-cancion']);
  assert.ok(exportada.canciones.filter(function (c) { return c.id.indexOf('ejemplo:') === 0; }).every(function (c) { return !c.midi; }), 'los ejemplos no se exportan');
  assert.ok(/\.json$/.test(sj.suggestedFilename()) && /sesion/.test(sj.suggestedFilename()));
  // borrar favoritos, listas y el MIDI
  await sesion(function () {
    const MI = window.MusicaInfinita;
    const d = MI.estado.panelSesiones.datos;
    d.favoritos.length = 0;
    d.listas.length = 0;
    MI.estado.canciones.splice(MI.estado.canciones.findIndex(function (c) { return c.nombre === 'mi-cancion'; }), 1);
    const el = document.getElementById('fidelidad');
    el.value = 0.95;
    el.dispatchEvent(new Event('input'));
    MI.estado.panelSesiones.pintar();
  });
  await pagina.setInputFiles('#ses-importar', { name: 'sesion.json', mimeType: 'application/json', buffer: Buffer.from(textoSesion) });
  await pagina.waitForTimeout(1200);
  const importada = await sesion(function () {
    const e = window.MusicaInfinita.estado;
    const d = e.panelSesiones.datos;
    const c = e.canciones.find(function (x) { return x.nombre === 'mi-cancion'; });
    return { favoritos: d.favoritos.length, listas: d.listas.length, midi: !!(c && c.analisis), fidelidad: Number(document.getElementById('fidelidad').value), aviso: document.getElementById('aviso').textContent, filas: document.querySelectorAll('#lista-fav li').length };
  });
  assert.ok(importada.favoritos >= 2 && importada.listas >= 2);
  assert.ok(importada.midi, 'el MIDI no volvió a la biblioteca');
  assert.equal(importada.fidelidad, 0.35, 'no se aplicaron los ajustes importados');
  assert.equal(importada.filas, importada.favoritos);
  assert.match(importada.aviso, /Sesión importada/);
  // importar dos veces no duplica
  await pagina.setInputFiles('#ses-importar', { name: 'sesion.json', mimeType: 'application/json', buffer: Buffer.from(textoSesion) });
  await pagina.waitForTimeout(800);
  assert.equal(await sesion(function () { return window.MusicaInfinita.estado.panelSesiones.datos.favoritos.length; }), importada.favoritos, 'importar dos veces repitió favoritos');
  // un archivo que no es una sesión no rompe nada
  await pagina.setInputFiles('#ses-importar', { name: 'malo.json', mimeType: 'application/json', buffer: Buffer.from('{"hola":1}') });
  await pagina.waitForTimeout(400);
  assert.match(await sesion(function () { return document.getElementById('aviso').textContent; }), /No se pudo importar: El archivo no es una sesión/);
  paso('exporta la sesión con sus MIDI, y al importarla vuelven favoritos, listas, MIDI y ajustes, sin duplicar al repetir');
  await sesion(function () { window.MusicaInfinita.estado.panelFijo = false; });

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
