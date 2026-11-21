'use strict';

// Análisis de las canciones de ejemplo: tonalidad, melodía, frases y acordes.

const test = require('node:test');
const assert = require('node:assert/strict');
const M = require('../js/midi.js');
const A = require('../js/analisis.js');
const E = require('../js/ejemplos.js');
const T = require('../js/teoria.js');

const analisis = {};
for (const ej of E.EJEMPLOS) analisis[ej.id] = A.analizar(M.leer(E.aMidi(ej)), ej.nombre);

test('cada ejemplo tiene su tonalidad correcta', function () {
  const esperadas = {
    'oda-alegria': 'do mayor',
    greensleeves: 'la menor',
    'para-elisa': 'la menor',
    'frere-jacques': 'fa mayor',
    'canon-pachelbel': 're mayor',
    estrellita: 'do mayor',
    scarborough: 're menor',
  };
  for (const id in esperadas) {
    const a = analisis[id];
    assert.equal(T.nombreTonalidad(a.tonica, a.modo), esperadas[id], id);
  }
});

test('la melodía elegida es la pista de melodía, completa', function () {
  for (const ej of E.EJEMPLOS) {
    const a = analisis[ej.id];
    const notas = E.leerSecuencia(ej.melodia).filter(function (n) { return n.simbolo !== 'R'; });
    assert.equal(a.melodia.length, notas.length, ej.id);
    // y vuelve a las mismas alturas
    a.melodia.forEach(function (m, i) {
      assert.equal(T.notaDeGrado(m.g, m.alt, a.tonica, a.modo), E.alturaDe(notas[i].simbolo), ej.id + ' nota ' + i);
    });
  }
});

test('las frases cubren la melodía sin huecos ni solapes', function () {
  for (const id in analisis) {
    const a = analisis[id];
    let esperado = 0;
    for (const f of a.frases) {
      assert.equal(f.desde, esperado, id);
      assert.ok(f.hasta > f.desde, id);
      assert.ok(f.pulsos > 0, id);
      esperado = f.hasta;
    }
    assert.equal(esperado, a.melodia.length, id);
  }
});

test('la progresión del Canon es la de siempre', function () {
  const a = analisis['canon-pachelbel'];
  const romanos = a.acordes.slice(0, 8).map(function (x) { return T.romano(x.raizRel, x.tipo); });
  assert.deepEqual(romanos, ['I', 'V', 'vi', 'iii', 'IV', 'I', 'IV', 'V']);
});

test('cuantización: duraciones y ataques a la rejilla', function () {
  assert.equal(A.cuantizarDuracion(0.97), 1);
  assert.equal(A.cuantizarDuracion(0.32), 1 / 3);
  assert.equal(A.cuantizarDuracion(1.4), 1.5);
  assert.equal(A.cuantizarInicio(2.26), 2.25);
  assert.equal(A.cuantizarInicio(0.34), 1 / 3);
});

test('línea superior: de un acorde queda la nota más aguda', function () {
  const n = function (nota, ini, dur) { return { nota: nota, inicioPulso: ini, durPulsos: dur }; };
  const linea = A.lineaSuperior([n(60, 0, 1), n(64, 0, 1), n(67, 0, 1), n(65, 1, 1), n(48, 1.5, 0.5)]);
  assert.deepEqual(linea.map(function (x) { return x.nota; }), [67, 65]);
});

test('una canción sin notas tonales no se analiza', function () {
  const bytes = M.escribir({ pistas: [{ canal: 9, notas: [{ nota: 36, inicio: 0, dur: 1 }, { nota: 38, inicio: 1, dur: 1 }] }] });
  assert.equal(A.analizar(M.leer(bytes), 'batería sola'), null);
});
