'use strict';

// Planificación de la reproducción y de la exportación por tramos.

const test = require('node:test');
const assert = require('node:assert/strict');
const P = require('../js/planificador.js');

test('tramos: cubren el total sin huecos ni solapes, con arranque y cola solo donde toca', function () {
  for (const total of [60, 120, 121, 300, 3600, 7200]) {
    const t = P.tramos(total);
    let esperado = 0;
    t.forEach(function (x, i) {
      assert.equal(x.inicio, esperado, 'hueco en ' + total);
      esperado += Math.min(P.TRAMO, total - x.inicio);
      assert.equal(x.previo, Math.min(x.inicio, P.PREVIO), 'el primero no tiene arranque, los demás sí');
      assert.equal(x.ultimo, i === t.length - 1);
      assert.equal(x.largo, Math.min(P.TRAMO, total - x.inicio) + (x.ultimo ? P.COLA : 0));
    });
    assert.equal(esperado, total);
  }
  assert.equal(P.tramos(60).length, 1);
  assert.equal(P.tramos(7200).length, 60);
  assert.deepEqual(P.tramos(0), []);
  // con otros tamaños
  assert.equal(P.tramos(100, { tramo: 30, previo: 5, cola: 2 }).length, 4);
});

test('adelanto: más con la pestaña oculta', function () {
  assert.equal(P.adelanto(false), P.ADELANTO);
  assert.equal(P.adelanto(true), P.ADELANTO * 3);
});

test('programador: cada evento una sola vez, en orden, y solo los del tramo', function () {
  const evs = [];
  for (let t = 0; t < 100; t += 0.7) evs.push({ t: t, nota: Math.round(t) });
  const prog = P.programador(evs, 10, 40);
  const adentro = evs.filter(function (e) { return e.t >= 10 && e.t < 40; });
  assert.equal(prog.total, adentro.length);
  const vistos = [];
  for (let lim = 3; lim <= 40; lim += 3) {
    const lote = prog.hasta(lim);
    for (const e of lote) assert.ok(e.t - 10 < lim, 'adelantado');
    Array.prototype.push.apply(vistos, lote);
  }
  assert.deepEqual(vistos, adentro);
  assert.equal(prog.pendientes(), 0);
  assert.deepEqual(prog.hasta(1000), []); // nada se repite
});

test('programador: un lote da lo que cae antes del límite y no más', function () {
  const evs = [{ t: 0 }, { t: 1 }, { t: 2 }, { t: 3 }];
  const prog = P.programador(evs, 0, 10);
  assert.equal(prog.hasta(2).length, 2); // 0 y 1
  assert.equal(prog.pendientes(), 2);
  assert.equal(prog.hasta(2).length, 0);
  assert.equal(prog.hasta(3.5).length, 2);
});

test('nombre de archivo: fecha, semilla saneada y extensión', function () {
  const f = new Date(2026, 10, 9, 7, 5);
  assert.equal(P.nombreArchivo('flac', null, f), 'musica-infinita-20261109-0705.flac');
  assert.equal(P.nombreArchivo('mid', 'mi semilla/∞ ñ', f), 'musica-infinita-20261109-0705-mi_semilla_.mid'.replace('mi_semilla_', 'mi_semilla_'));
  assert.ok(P.nombreArchivo('wav', 'x'.repeat(80), f).length < 80);
  assert.ok(!/[\\/:*?"<>|\s]/.test(P.nombreArchivo('flac', 'a b/c:d*e', f)));
});
