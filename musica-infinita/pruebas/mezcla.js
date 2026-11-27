'use strict';

// El balance de la mezcla, medido y no escuchado: se exportan 30 s de cada estilo con dos
// semillas, y de cada audio se mide la sonoridad integrada (LUFS, norma BS.1770), el pico
// de muestra y la corriente continua. Que un estilo o una sección suene mucho más fuerte
// que otro es de lo primero que molesta en una música que no se acaba.
//
//   node pruebas/mezcla.js
//
// Tarda unos 3 minutos (renderiza 6 minutos de audio). Necesita playwright y Chromium.
// Lo medido al fijar estos topes, con 3 semillas por estilo: de -15,1 a -12,0 LUFS (3,2 LU
// de rango; sin el nivelador eran 6,6 LU), pico máximo de -1,3 dBFS y corriente continua
// de 0,002 como mucho.

const path = require('node:path');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const L = require('./ayudas/sonoridad.js');

let playwright;
try {
  playwright = require('playwright');
} catch (e) {
  console.error('Falta el paquete playwright: npm i --no-save playwright');
  process.exit(2);
}

const ESTILOS = ['ambiente', 'pop', 'orquestal', 'chip', 'lofi', 'cajita'];
const SEMILLAS = ['m1', 'm2'];

async function principal() {
  const opciones = {};
  if (process.env.CHROMIUM) opciones.executablePath = process.env.CHROMIUM;
  const navegador = await playwright.chromium.launch(opciones);
  const pagina = await navegador.newPage({ acceptDownloads: true });
  const errores = [];
  pagina.on('pageerror', function (e) { errores.push(e.message); });
  await pagina.goto('file://' + path.resolve(__dirname, '..', 'index.html'));
  await pagina.waitForTimeout(500);

  const medidas = [];
  for (const estilo of ESTILOS) {
    for (const semilla of SEMILLAS) {
      await pagina.evaluate(function (e) { window.MusicaInfinita.estado.ajustes.estilo = e; }, estilo);
      const descarga = pagina.waitForEvent('download', { timeout: 300000 });
      await pagina.evaluate(function (s) { return window.MusicaInfinita.exportarAudio(0.5, 'wav16', s, function () {}); }, semilla);
      const d = await descarga;
      const w = L.leerWav16(new Uint8Array(fs.readFileSync(await d.path())));
      const m = { estilo: estilo, semilla: semilla, lufs: L.lufs(w.canales, 48000), pico: L.picoDb(w.canales), dc: L.continua(w.canales), segundos: w.canales[0].length / 48000 };
      medidas.push(m);
      console.log('· ' + estilo.padEnd(10) + semilla + '  ' + m.lufs.toFixed(1) + ' LUFS  pico ' + m.pico.toFixed(1) + ' dBFS  continua ' + m.dc.toExponential(1));
    }
  }
  await navegador.close();

  for (const m of medidas) {
    const quien = m.estilo + '/' + m.semilla;
    assert.ok(Math.abs(m.segundos - 34) < 0.2, quien + ': dura ' + m.segundos + ' s en vez de 34 (30 más la cola)');
    assert.ok(Number.isFinite(m.lufs), quien + ': en silencio');
    assert.ok(m.lufs > -17 && m.lufs < -10, quien + ': suena a ' + m.lufs.toFixed(1) + ' LUFS (debe estar entre -17 y -10)');
    assert.ok(m.pico < -1, quien + ': pico de ' + m.pico.toFixed(2) + ' dBFS (debe quedar por debajo de -1)');
    assert.ok(m.dc < 0.01, quien + ': corriente continua de ' + m.dc.toExponential(1));
  }
  const l = medidas.map(function (m) { return m.lufs; });
  const rango = Math.max.apply(null, l) - Math.min.apply(null, l);
  assert.ok(rango <= 4.5, 'los estilos y secciones difieren ' + rango.toFixed(1) + ' LU entre el más fuerte y el más suave (el tope es 4,5)');
  assert.deepEqual(errores, [], 'errores en la página');
  console.log('Rango de sonoridad: ' + rango.toFixed(1) + ' LU. Todo bien.');
}

principal().catch(function (e) {
  console.error(e);
  process.exit(1);
});
