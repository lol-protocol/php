'use strict';

// Recetas de los instrumentos propios (la parte pura del sintetizador): que den sonido
// válido, a la altura pedida, y que se apague.

const test = require('node:test');
const assert = require('node:assert/strict');
const S = require('../js/sintetizador.js');

const SR = 48000;

function pico(x) {
  let m = 0;
  for (const v of x) m = Math.max(m, Math.abs(v));
  return m;
}

function energia(x, desde, hasta) {
  let e = 0;
  for (let i = desde; i < hasta; i++) e += x[i] * x[i];
  return e / (hasta - desde);
}

/**
 * Frecuencia fundamental por autocorrelación (entre 40 y 2000 Hz): el primer retardo cuya
 * correlación llega al 90 % de la mayor (los múltiplos del período también correlacionan).
 */
function fundamental(x, desde) {
  const N = 4096;
  const corr = [];
  let max = 0;
  const l0 = Math.floor(SR / 2000);
  for (let l = l0; l < SR / 40; l++) {
    let s = 0;
    for (let i = 0; i < N; i++) s += x[desde + i] * x[desde + i + l];
    corr[l] = s;
    if (s > max) max = s;
  }
  for (let l = l0 + 1; l < corr.length - 1; l++) {
    if (corr[l] >= 0.9 * max && corr[l] >= corr[l - 1] && corr[l] >= corr[l + 1]) return SR / l;
  }
  return 0;
}

test('todas las recetas pre-renderizadas: finitas, normalizadas y se apagan', function () {
  for (const tipo of S.PRERENDER) {
    for (const nota of [40, 60, 84]) {
      const x = S.renderizar(tipo, nota, SR);
      assert.ok(x.every(Number.isFinite), tipo + ' ' + nota + ' tiene NaN');
      assert.ok(Math.abs(pico(x) - 0.9) < 1e-4, tipo + ' pico ' + pico(x));
      const n = x.length;
      assert.ok(energia(x, n - 2000, n) < energia(x, 0, 4000) * 0.2, tipo + ' ' + nota + ' no decae');
    }
  }
});

test('afinación: el piano, la guitarra y el bajo suenan a la nota pedida', function () {
  for (const [tipo, nota] of [['piano', 57], ['guitarra', 52], ['bajo', 40], ['arpa', 64], ['epiano', 60]]) {
    const x = S.renderizar(tipo, nota, SR);
    const f = fundamental(x, 4800);
    const esperado = S.frecuencia(nota);
    const cents = 1200 * Math.log2(f / esperado);
    assert.ok(Math.abs(cents) < 30, tipo + ': ' + f.toFixed(1) + ' Hz en vez de ' + esperado.toFixed(1));
  }
});

test('batería: cada golpe suena y es corto o largo según su tipo', function () {
  for (const n of [36, 38, 42, 46, 49, 45, 56, 81]) {
    const x = S.renderizarBateria(n, SR);
    assert.ok(x.every(Number.isFinite));
    assert.ok(pico(x) > 0.5);
  }
  assert.ok(S.renderizarBateria(42, SR).length < S.renderizarBateria(49, SR).length);
});

test('familias General MIDI', function () {
  assert.equal(S.receta(0), 'piano');
  assert.equal(S.receta(4), 'epiano');
  assert.equal(S.receta(25), 'guitarra');
  assert.equal(S.receta(33), 'bajo');
  assert.equal(S.receta(48), 'cuerdas');
  assert.equal(S.receta(73), 'flauta');
  assert.equal(S.receta(80), 'cuadrada');
  assert.equal(S.receta(89), 'pad');
  assert.equal(S.receta(127), 'pad');
});
