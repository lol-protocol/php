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

// ------------------------------------------------------------ elegir la pista de la melodía

function mid(pistas) {
  return M.leer(M.escribir({ ppq: 480, bpm: 100, pistas: pistas }));
}

function linea(notas, inicio0, paso, dur, vel) {
  return notas.map(function (n, i) { return { nota: n, inicio: inicio0 + i * paso, dur: dur == null ? paso * 0.9 : dur, vel: vel || 90 }; });
}

// Do mayor: una melodía grave de violonchelo (registro de bajo: el puntaje automático la descarta)
// y un arpegio continuo de piano en el registro medio (que el automático toma por melodía).
const CELLO = [48, 50, 52, 53, 55, 53, 52, 50, 48, 52, 55, 52, 50, 48, 47, 48, 50, 52, 55, 57, 55, 53, 52, 48];
const ARPEGIO = [];
for (let i = 0; i < 64; i++) ARPEGIO.push([60, 64, 67, 72, 67, 64][i % 6] + (i % 24 < 12 ? 0 : 2));

function cuentoDeGrave() {
  return mid([
    { nombre: 'Piano', canal: 0, programa: 0, notas: linea(ARPEGIO, 0, 0.5) },
    { nombre: 'Cello', canal: 1, programa: 42, notas: linea(CELLO, 0, 1.5, 1.4) },
    { nombre: 'Bajo', canal: 2, programa: 33, notas: linea([36, 36, 43, 36, 41, 36, 43, 36], 0, 4, 3.8) },
  ]);
}

test('candidatas: ordenadas por puntaje, la automática marcada, y «todas» al final si hay más de una', function () {
  const c = A.candidatas(cuentoDeGrave());
  assert.deepEqual(c.map(function (x) { return x.nombre || 'todas'; }), ['Piano', 'Cello', 'Bajo', 'todas']);
  assert.deepEqual(c.map(function (x) { return x.automatica; }), [true, false, false, false]);
  assert.equal(c[1].notas, CELLO.length);
  assert.ok(c[0].puntaje > c[1].puntaje && c[1].puntaje > c[2].puntaje);
  assert.equal(c[3].clave, A.TODAS);
  assert.ok(c[3].todas && c[3].notas === ARPEGIO.length + CELLO.length + 8);
  // una sola pista: no hay nada que elegir
  assert.deepEqual(A.candidatas(mid([{ nombre: 'Unica', canal: 0, notas: linea(CELLO, 0, 1) }])).map(function (x) { return x.todas; }), [false]);
  // un MIDI sin notas tonales: lista vacía
  assert.deepEqual(A.candidatas(mid([{ canal: 9, notas: linea([36, 38, 36, 38], 0, 1) }])), []);
  // un grupo con pocas notas tiene puntaje null y no es la automática
  const pocas = A.candidatas(mid([{ nombre: 'A', canal: 0, notas: linea(CELLO, 0, 1) }, { nombre: 'B', canal: 1, notas: linea([60, 62, 64], 0, 1) }]));
  assert.equal(pocas.find(function (x) { return x.nombre === 'B'; }).puntaje, null);
  assert.ok(pocas.find(function (x) { return x.nombre === 'A'; }).automatica);
});

test('la melodía automática toma el arpegio y, elegida a mano, la del violonchelo da exactamente sus notas', function () {
  const midi = cuentoDeGrave();
  const auto = A.analizar(midi, 'x');
  assert.equal(auto.melodiaAutomatica, true);
  assert.equal(auto.melodia.length, ARPEGIO.length); // el arpegio, que no es la melodía
  const cello = A.candidatas(midi).find(function (x) { return x.nombre === 'Cello'; });
  const manual = A.analizar(midi, 'x', { melodia: cello.clave });
  assert.equal(manual.melodiaAutomatica, false);
  assert.equal(manual.melodiaClave, cello.clave);
  assert.equal(manual.programaMelodia, 42);
  assert.equal(manual.melodia.length, CELLO.length);
  manual.melodia.forEach(function (m, i) {
    assert.equal(T.notaDeGrado(m.g, m.alt, manual.tonica, manual.modo), CELLO[i], 'nota ' + i);
  });
  // la tonalidad y los acordes son del conjunto y no cambian con la pista elegida
  assert.equal(manual.tonica, auto.tonica);
  assert.deepEqual(manual.acordes, auto.acordes);
  assert.ok(manual.frases.length >= 2);
});

test('elegir una pista que no existe, o que no tiene melodía, cae a la automática y lo avisa', function () {
  const midi = cuentoDeGrave();
  const auto = A.analizar(midi, 'x');
  for (const mala of ['9:9', 'xx', '1:7']) {
    const a = A.analizar(midi, 'x', { melodia: mala });
    assert.equal(a.melodiaPedidaInvalida, true, mala);
    assert.equal(a.melodiaAutomatica, true);
    assert.deepEqual(a.melodia, auto.melodia);
  }
  assert.equal(auto.melodiaPedidaInvalida, undefined);
  // una pista de menos de 4 notas
  const m2 = mid([{ nombre: 'A', canal: 0, notas: linea(CELLO, 0, 1) }, { nombre: 'B', canal: 1, notas: linea([60, 62, 64], 0, 1) }]);
  assert.equal(A.analizar(m2, 'x', { melodia: A.candidatas(m2).find(function (x) { return x.nombre === 'B'; }).clave }).melodiaPedidaInvalida, true);
});

test('piano a dos manos en una sola pista: la línea superior es la mano derecha', function () {
  const der = [72, 74, 76, 77, 79, 77, 76, 74, 72, 76, 79, 76, 74, 72, 71, 72];
  const notas = linea(der, 0, 1, 0.9);
  for (let b = 0; b < 16; b += 2) {
    // la mano izquierda: acorde de blancas en el registro grave
    for (const n of [43, 47, 50]) notas.push({ nota: n, inicio: b, dur: 1.9, vel: 70 });
  }
  const a = A.analizar(mid([{ nombre: 'Piano', canal: 0, programa: 0, notas: notas }]), 'x');
  assert.equal(a.melodia.length, der.length);
  a.melodia.forEach(function (m, i) { assert.equal(T.notaDeGrado(m.g, m.alt, a.tonica, a.modo), der[i]); });
});

test('melodía repartida entre dos instrumentos: «todas las pistas juntas» la junta entera', function () {
  const flauta = linea([72, 74, 76, 77, 79, 77, 76, 74, 72, 74, 76, 72], 0, 1);
  const violin = linea([71, 72, 74, 76, 77, 79, 81, 79, 77, 76, 74, 72], 12, 1);
  const midi = mid([
    { nombre: 'Flauta', canal: 0, programa: 73, notas: flauta },
    { nombre: 'Violin', canal: 1, programa: 40, notas: violin },
    { nombre: 'Bajo', canal: 2, programa: 33, notas: linea([36, 43, 36, 41, 36, 43], 0, 4, 3.8) },
  ]);
  const auto = A.analizar(midi, 'x');
  assert.equal(auto.melodia.length, 12, 'el automático se queda con la mitad');
  const todas = A.analizar(midi, 'x', { melodia: A.TODAS });
  assert.equal(todas.melodiaClave, A.TODAS);
  assert.equal(todas.melodiaAutomatica, false);
  assert.equal(todas.melodia.length, 24);
  const pitches = todas.melodia.map(function (m) { return T.notaDeGrado(m.g, m.alt, todas.tonica, todas.modo); });
  assert.deepEqual(pitches, flauta.concat(violin).map(function (n) { return n.nota; }));
});

test('describir: una línea legible por candidata', function () {
  const c = A.candidatas(cuentoDeGrave());
  assert.match(A.describir(c.find(function (x) { return x.nombre === 'Cello'; })), /^pista \d+ «Cello» · canal 2 · 24 notas · /);
  assert.match(A.describir(c[c.length - 1]), /^todas las pistas juntas/);
  assert.match(A.describir({ pista: 0, canal: 0, nombre: '', notas: 10, media: 60 }), /^pista 1 · canal 1 · 10 notas · do4$/);
});
