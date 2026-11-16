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
  assert.deepEqual(SF.listaPresets(sf).map(function (p) { return [p.banco, p.programa, p.nombre]; }), [[0, 0, 'Piano de prueba'], [128, 0, 'Bateria de prueba']]);
  assert.equal(sf.muestras.length, 3); // dos y la terminal
});

test('zona grave: muestra 1, afinación por su tono original y bucle', function () {
  const r = SF.regiones(sf, 0, 57, 100, false);
  assert.equal(r.length, 1);
  assert.equal(r[0].muestra, 1);
  cerca(r[0].semitonos, 57 - 45, 1e-9);
  assert.equal(r[0].bucle, 1);
  cerca(r[0].env.ataque, 0.25, 1e-9);
  // atenuación: 60 cB del instrumento + 20 cB del preset = 8 dB
  cerca(r[0].ganancia, Math.pow(10, -80 / 200), 1e-9);
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

test('un archivo que no es SoundFont da un error claro', function () {
  assert.throws(function () { SF.leer(new Uint8Array(64)); }, /No es una SoundFont/);
});
