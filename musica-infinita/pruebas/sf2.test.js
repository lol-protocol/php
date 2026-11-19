'use strict';

// Lector de SoundFont, con una SoundFont mínima armada en la prueba.

const test = require('node:test');
const assert = require('node:assert/strict');
const SF = require('../js/sf2.js');
const minima = require('./ayudas/sf2-minimo.js');

const sf = SF.leer(minima.crear());

function cerca(a, b, tol) {
  assert.ok(Math.abs(a - b) <= tol, a + ' no está cerca de ' + b);
}

test('lee la cabecera, los presets y las muestras', function () {
  assert.equal(sf.info.nombre, 'SoundFont de prueba');
  assert.deepEqual(SF.listaPresets(sf).map(function (p) { return [p.banco, p.programa, p.nombre]; }), [[0, 0, 'Piano de prueba'], [0, 1, 'Modulado'], [0, 2, 'Estereo'], [128, 0, 'Bateria de prueba']]);
  assert.equal(sf.muestras.length, 5); // cuatro y la terminal
  assert.equal(sf.esSf3, false);
});

test('zona grave: muestra 1, afinación por su tono original y bucle', function () {
  const r = SF.regiones(sf, 0, 57, 100, false);
  assert.equal(r.length, 1);
  assert.equal(r[0].muestra, 1);
  cerca(r[0].semitonos, 57 - 45, 1e-9);
  assert.equal(r[0].bucle, 1);
  cerca(r[0].env.ataque, 0.25, 1e-9);
  // atenuación: 60 cB del instrumento + 20 cB del preset + la de la velocidad (curva cóncava de la norma)
  cerca(r[0].ganancia, Math.pow(10, -(80 + 960 * SF.concava(1 - 100 / 127)) / 200), 1e-9);
  assert.equal(r[0].frecuencia, 22050);
});

test('zona aguda: raíz forzada, ajuste fino, corrección de la muestra y paneo', function () {
  const r = SF.regiones(sf, 0, 81, 100, false);
  assert.equal(r.length, 1);
  assert.equal(r[0].muestra, 0);
  // 81 - 81 = 0 semitonos, +10 cents de fineTune, -5 de la muestra
  cerca(r[0].semitonos, 0.05, 1e-9);
  cerca(r[0].relacion, Math.pow(2, 5 / 1200), 1e-12);
  cerca(r[0].pan, 0.5, 1e-9);
  assert.equal(r[0].bucle, 0);
});

test('la velocidad por omisión: la amplitud sube como (v/127)² y el filtro se abre', function () {
  // sin atenuación propia: la zona aguda del preset de piano trae 80 cB, así que se compara contra la velocidad 127
  const a127 = SF.regiones(sf, 0, 70, 127, false)[0].ganancia;
  for (const v of [32, 64, 100]) {
    cerca(SF.regiones(sf, 0, 70, v, false)[0].ganancia / a127, Math.pow(v / 127, 2), 1e-9);
  }
  // el filtro de la norma: -2400 cents a velocidad 0 y nada a 127 (sin filtro propio, 13500 es «abierto»)
  assert.equal(SF.regiones(sf, 0, 70, 127, false)[0].filtroHz, null);
  cerca(SF.regiones(sf, 0, 70, 1, false)[0].filtroHz, 8.176 * Math.pow(2, (13500 - 2400 * (126 / 127)) / 1200), 1e-6);
});

test('vibrato, LFO de modulación y envolvente de modulación', function () {
  const r = SF.regiones(sf, 1, 69, 127, false)[0];
  assert.deepEqual(r.vibrato, { retardo: 0.5, frecuencia: 8.176 * Math.pow(2, -1), cents: 50 });
  assert.equal(r.lfoMod.centsPitch, 100);
  assert.equal(r.lfoMod.centsFiltro, -1200);
  assert.equal(r.lfoMod.cbVolumen, 60);
  cerca(r.lfoMod.frecuencia, 8.176, 1e-9);
  assert.equal(r.lfoMod.retardo, 0);
  assert.equal(r.envMod.centsPitch, 1200);
  assert.equal(r.envMod.centsFiltro, 2400);
  cerca(r.envMod.ataque, 0.5, 1e-9);
  cerca(r.envMod.sustain, 0.5, 1e-9);
  // una región sin nada de eso no trae objetos
  const p = SF.regiones(sf, 0, 70, 100, false)[0];
  assert.equal(p.vibrato, null);
  assert.equal(p.lfoMod, null);
  assert.equal(p.envMod, null);
});

test('moduladores explícitos: la velocidad al filtro reemplaza al de la norma, y la nota al paneo', function () {
  for (const v of [1, 64, 127]) {
    const r = SF.regiones(sf, 1, 69, v, false)[0];
    // fc = 6000 - 3600 * v/127, sin el -2400 de la norma
    cerca(r.filtroHz, 8.176 * Math.pow(2, (6000 - 3600 * (v / 127)) / 1200), 1e-6);
  }
  // bipolar lineal: la nota 63,5 es el centro; la 100 queda a 0,5748 hacia la derecha
  cerca(SF.regiones(sf, 1, 100, 100, false)[0].pan, 2 * 100 / 127 - 1, 1e-9);
  cerca(SF.regiones(sf, 1, 0, 100, false)[0].pan, -1, 1e-9);
});

test('curvas de los moduladores', function () {
  const v = SF.valorFuente;
  // velocidad, cóncava, negativa, unipolar (la de la norma): 1 - (v/127)² se ve como atenuación
  cerca(Math.pow(10, -960 * v(0x0502, 0, 64) / 200), Math.pow(64 / 127, 2), 1e-9);
  assert.equal(v(0x0002, 0, 127), 1); // lineal
  cerca(v(0x0002, 0, 127 / 2), 0.5, 1e-9);
  assert.equal(v(0x0000, 0, 5), 1); // sin controlador
  assert.equal(v(0x0C02, 0, 100), 1); // interruptor
  assert.equal(v(0x0C02, 0, 30), 0);
  cerca(v(0x0802, 0, 127 / 2) + SF.concava(0.5), 1, 1e-9); // convexa: espejo de la cóncava
  cerca(v(0x0202, 0, 127), 1, 1e-9); // bipolar: 1
  cerca(v(0x0202, 0, 0), -1, 1e-9);
  assert.equal(v(0x0081, 0, 100), null); // un CC: la aplicación no los genera
  assert.equal(v(0x000e, 0, 100), null); // rueda de tono
});

test('muestras izquierda y derecha enlazadas sin paneo: cada una a su lado', function () {
  const r = SF.regiones(sf, 2, 69, 100, false);
  assert.equal(r.length, 2);
  assert.deepEqual(r.map(function (x) { return x.pan; }).sort(), [-1, 1]);
});

test('batería en el banco 128, con clase exclusiva; y un programa que no existe cae al 0', function () {
  const b = SF.regiones(sf, 0, 36, 100, true);
  assert.equal(b.length, 1);
  assert.equal(b[0].claseExclusiva, 3);
  assert.equal(SF.regiones(sf, 0, 40, 100, true).length, 0);
  assert.equal(SF.regiones(sf, 52, 70, 100, false).length, 1);
});

test('datos de una muestra en coma flotante, con caché', function () {
  const r = SF.regiones(sf, 0, 70, 100, false)[0];
  const d = SF.datosMuestra(sf, r.muestra, r.inicio, r.fin);
  assert.equal(d.length, 22050);
  let max = 0;
  for (const v of d) max = Math.max(max, Math.abs(v));
  cerca(max, 16000 / 32768, 1e-3);
  assert.equal(SF.datosMuestra(sf, r.muestra, r.inicio, r.fin), d);
});

test('SoundFont 3: las muestras comprimidas se reconocen y piden decodificarse', function () {
  const sf3 = SF.leer(minima.crear({ ogg: [Buffer.from('OggS fake 1'), Buffer.from('OggS fake 22'), Buffer.from('OggS fake 333')] }));
  assert.equal(sf3.esSf3, true);
  assert.equal(sf3.info.version, 3);
  const r = SF.regiones(sf3, 0, 57, 100, false)[0];
  assert.equal(r.comprimida, true);
  assert.equal(r.inicio, 0);
  assert.equal(r.loopIni, 11025); // relativo a la propia muestra
  assert.equal(SF.bufferDecodificado(sf3, r.muestra), undefined);
  assert.throws(function () { SF.datosMuestra(sf3, r.muestra, 0, 10); }, /comprimidas/);
  // sin decodificar nada, el resto de parámetros se calcula igual
  cerca(r.semitonos, 12, 1e-9);
});

test('un archivo que no es SoundFont da un error claro', function () {
  assert.throws(function () { SF.leer(new Uint8Array(64)); }, /No es una SoundFont/);
});
