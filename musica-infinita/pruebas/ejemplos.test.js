'use strict';

// Las melodías de ejemplo: bien escritas, con melodía y acordes del mismo largo.

const test = require('node:test');
const assert = require('node:assert/strict');
const E = require('../js/ejemplos.js');

test('cada ejemplo: id único, notas válidas y acordes del mismo largo que la melodía', function () {
  const ids = new Set();
  for (const ej of E.EJEMPLOS) {
    assert.ok(!ids.has(ej.id), 'id repetido ' + ej.id);
    ids.add(ej.id);
    const m = E.leerSecuencia(ej.melodia);
    const a = E.leerSecuencia(ej.acordes);
    const fin = function (s) { return s[s.length - 1].inicio + s[s.length - 1].dur; };
    assert.ok(Math.abs(fin(m) - fin(a)) < 1e-9, ej.id + ': melodía ' + fin(m) + ' y acordes ' + fin(a));
    const pc = (ej.compas[0] * 4) / ej.compas[1];
    for (const n of m) if (n.simbolo !== 'R') E.alturaDe(n.simbolo);
    assert.ok(fin(m) >= pc * 4, ej.id + ' es muy corto');
    assert.ok(/\(.*\)/.test(ej.nombre), ej.id + ': el nombre dice de dónde viene');
  }
});

test('notación', function () {
  assert.equal(E.alturaDe('C4'), 60);
  assert.equal(E.alturaDe('A4'), 69);
  assert.equal(E.alturaDe('Bb3'), 58);
  assert.equal(E.alturaDe('F#5'), 78);
  assert.throws(function () { E.alturaDe('H2'); });
});
