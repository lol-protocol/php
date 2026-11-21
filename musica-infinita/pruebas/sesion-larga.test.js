'use strict';

// Rendimiento y memoria en sesiones largas: el generador tiene que ser mucho más rápido que
// el tiempo real y no acumular nada, y los instrumentos propios tienen que calcularse a tiempo.
// Los topes son holgados a propósito (una máquina lenta o cargada no debe romper la prueba);
// lo que buscan es un cuadrático o una fuga, no medir milisegundos.

const test = require('node:test');
const assert = require('node:assert/strict');
const v8 = require('node:v8');
const vm = require('node:vm');
const M = require('../js/midi.js');
const A = require('../js/analisis.js');
const E = require('../js/ejemplos.js');
const G = require('../js/generador.js');
const D = require('../js/dsp.js');

const CANCIONES = E.EJEMPLOS.map(function (ej) { return A.analizar(M.leer(E.aMidi(ej)), ej.nombre); });

function recolectar() {
  v8.setFlagsFromString('--expose-gc');
  vm.runInNewContext('gc')();
}

test('una hora de música se genera en una fracción del tiempo real', function () {
  const g = new G.Generador({ canciones: CANCIONES, semilla: 1 });
  const t0 = performance.now();
  const evs = g.eventosHasta(3600);
  const seg = (performance.now() - t0) / 1000;
  assert.ok(evs.length > 8000, 'eventos: ' + evs.length);
  // pedida de una vez, la hora llega entera (una exportación a MIDI pide horas así)
  const ultimo = evs[evs.length - 1].t;
  assert.ok(ultimo > 3590 && ultimo < 3600, 'la música llega hasta ' + ultimo.toFixed(0) + ' s de los 3600 pedidos');
  assert.ok(seg < 20, 'una hora tardó ' + seg.toFixed(1) + ' s en generarse (el tiempo real son 3600 s)');
});

test('dos horas de una vez llegan completas, sin saltos entre bloques', function () {
  const evs = new G.Generador({ canciones: CANCIONES, semilla: 4 }).eventosHasta(7200);
  const infos = evs.filter(function (e) { return e.tipo === 'info'; });
  assert.ok(evs[evs.length - 1].t > 7190);
  for (let i = 1; i < infos.length; i++) assert.ok(Math.abs(infos[i].t - (infos[i - 1].t + infos[i - 1].dur)) < 1e-6);
});

test('pedir de a poco, como en vivo, cuesta lo mismo por cuadro durante horas (nada cuadrático)', function () {
  const g = new G.Generador({ canciones: CANCIONES, semilla: 2 });
  // cada «cuadro» pide 100 ms más, durante 4 horas de música; se compara el costo por cuadro al principio y al final
  const tiempos = [];
  let t = 0;
  for (let bloque = 0; bloque < 8; bloque++) {
    const t0 = performance.now();
    for (let k = 0; k < 18000; k++) {
      t += 0.1;
      g.eventosHasta(t);
    }
    tiempos.push(performance.now() - t0);
  }
  const primero = Math.max(tiempos[0], 5);
  const ultimo = tiempos[tiempos.length - 1];
  assert.ok(ultimo < primero * 4 + 50, 'el costo crece: ' + tiempos.map(function (x) { return x.toFixed(0); }).join(' ms, ') + ' ms');
});

test('doce horas de música no acumulan memoria', function () {
  const g = new G.Generador({ canciones: CANCIONES, semilla: 3 });
  let t = 0;
  const avanzar = function (hasta) {
    for (; t < hasta; t += 5) g.eventosHasta(t);
  };
  avanzar(3600);
  recolectar();
  const antes = process.memoryUsage().heapUsed;
  avanzar(3600 * 12);
  recolectar();
  const despues = process.memoryUsage().heapUsed;
  const mb = (despues - antes) / 1048576;
  assert.ok(mb < 25, 'la memoria creció ' + mb.toFixed(1) + ' MB en 11 horas');
  // y lo que el generador guarda es poco: la cola de lo no pedido, un puñado de materiales
  assert.ok(g.cola.length < 5000, 'cola: ' + g.cola.length);
  assert.ok(Object.keys(g.seccion.materiales).length <= 6);
});

test('los instrumentos propios se calculan a tiempo: un rango de notas en pocos segundos', function () {
  const t0 = performance.now();
  for (const tipo of D.PRERENDER) {
    for (let nota = 36; nota <= 96; nota += 6) D.renderizar(tipo, nota, 48000);
  }
  for (const n of [36, 38, 42, 46, 49, 45, 47, 50, 51, 56]) D.renderizarBateria(n, 48000);
  const seg = (performance.now() - t0) / 1000;
  assert.ok(seg < 15, 'tardó ' + seg.toFixed(1) + ' s');
});
