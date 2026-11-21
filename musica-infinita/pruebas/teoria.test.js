'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const T = require('../js/teoria.js');

test('grados: ida y vuelta para todas las notas y tonalidades', function () {
  for (const modo of ['mayor', 'menor', 'dorica']) {
    for (let ton = 0; ton < 12; ton++) {
      for (let n = 30; n < 100; n++) {
        const g = T.gradoDe(n, ton, modo);
        assert.ok(g.alt >= -1 && g.alt <= 1, 'alteración fuera de rango');
        assert.equal(T.notaDeGrado(g.grado, g.alt, ton, modo), n, 'nota ' + n + ' en ' + T.nombreTonalidad(ton, modo));
      }
    }
  }
});

test('una frase en grados suena igual en otra tonalidad', function () {
  // do re mi en do mayor -> re mi fa♯ en re mayor
  const grados = [60, 62, 64].map(function (n) { return T.gradoDe(n, 0, 'mayor'); });
  assert.deepEqual(grados.map(function (g) { return T.notaDeGrado(g.grado, g.alt, 2, 'mayor'); }), [62, 64, 66]);
  // y en la menor la tercera es menor: la si do
  assert.deepEqual(grados.map(function (g) { return T.notaDeGrado(g.grado, g.alt, 9, 'menor'); }), [69, 71, 72]);
});

test('tonalidad: escalas puras y una cadencia', function () {
  const escala = function (ton, modo) {
    return T.ESCALAS[modo].map(function (iv) { return { nota: 60 + ton + iv, dur: iv === 0 ? 3 : 1 }; });
  };
  assert.deepEqual(pick(T.detectarTonalidad(escala(0, 'mayor'))), { tonica: 0, modo: 'mayor' });
  assert.deepEqual(pick(T.detectarTonalidad(escala(7, 'mayor'))), { tonica: 7, modo: 'mayor' });
  assert.deepEqual(pick(T.detectarTonalidad(escala(9, 'menor'))), { tonica: 9, modo: 'menor' });
  // la pista del final desempata do mayor contra la menor
  const ambigua = [60, 62, 64, 65, 67, 69, 71].map(function (n) { return { nota: n, dur: 1 }; });
  assert.equal(T.detectarTonalidad(ambigua, { final: 9 }).tonica, 9);
  assert.equal(T.detectarTonalidad(ambigua, { final: 0 }).tonica, 0);
});

function pick(t) {
  return { tonica: t.tonica, modo: t.modo };
}

test('acordes: tríadas y cuatríadas', function () {
  const h = function (notas) {
    const x = new Array(12).fill(0);
    for (const n of notas) x[n % 12] += 1;
    return x;
  };
  assert.deepEqual(pickA(T.detectarAcorde(h([60, 64, 67]), 48)), { raiz: 0, tipo: 'M' });
  assert.deepEqual(pickA(T.detectarAcorde(h([57, 60, 64]), 45)), { raiz: 9, tipo: 'm' });
  assert.deepEqual(pickA(T.detectarAcorde(h([67, 71, 74, 77]), 43)), { raiz: 7, tipo: '7' });
  assert.equal(T.detectarAcorde(new Array(12).fill(0)), null);
  assert.deepEqual(T.acordeDiatonico(4, 'mayor', true), { raizRel: 7, tipo: '7' });
  assert.deepEqual(T.acordeDiatonico(1, 'menor', false), { raizRel: 2, tipo: 'dim' });
  assert.equal(T.romano(9, 'm'), 'vi');
  assert.equal(T.romano(10, 'M'), '♭VII');
  assert.equal(T.nombreAcorde(6, 'm'), 'Fa♯m');
});

function pickA(a) {
  return { raiz: a.raiz, tipo: a.tipo };
}
