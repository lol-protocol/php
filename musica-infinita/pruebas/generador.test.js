'use strict';

// El generador: determinista, continuo, dentro del rango y obediente a sus ajustes.

const test = require('node:test');
const assert = require('node:assert/strict');
const M = require('../js/midi.js');
const A = require('../js/analisis.js');
const E = require('../js/ejemplos.js');
const G = require('../js/generador.js');
const T = require('../js/teoria.js');

const CANCIONES = E.EJEMPLOS.map(function (ej) { return A.analizar(M.leer(E.aMidi(ej)), ej.nombre); });

function generar(opciones, segundos) {
  const g = new G.Generador(Object.assign({ canciones: CANCIONES, semilla: 1234 }, opciones));
  return g.eventosHasta(segundos || 240);
}

test('misma semilla, misma música; otra semilla, otra', function () {
  const a = JSON.stringify(generar({ semilla: 'hola' }, 120));
  const b = JSON.stringify(generar({ semilla: 'hola' }, 120));
  const c = JSON.stringify(generar({ semilla: 'chao' }, 120));
  assert.equal(a, b);
  assert.notEqual(a, c);
});

test('pedir de a poco da lo mismo que pedir de una vez', function () {
  const g = new G.Generador({ canciones: CANCIONES, semilla: 77 });
  let poco = [];
  for (let t = 0.5; t <= 150; t += 0.37) poco = poco.concat(g.eventosHasta(t));
  const todo = generar({ semilla: 77 }, 150);
  assert.equal(poco.length, todo.length);
  assert.deepEqual(poco.map(function (e) { return e.t; }), todo.map(function (e) { return e.t; }));
});

test('los eventos son válidos y están en orden', function () {
  const evs = generar({}, 600);
  let previo = -Infinity;
  for (const e of evs) {
    assert.ok(e.t >= previo, 'fuera de orden');
    previo = e.t;
    assert.ok(Number.isFinite(e.t) && Number.isFinite(e.dur) && e.dur > 0);
    if (e.tipo === 'info') continue;
    assert.ok(Number.isInteger(e.nota) && e.nota >= 21 && e.nota <= 108, 'nota ' + e.nota);
    assert.ok(e.vel >= 1 && e.vel <= 127);
    assert.ok([0, 1, 2, 3, 9].indexOf(e.canal) >= 0);
  }
});

test('la música no se corta: los bloques van uno tras otro sin huecos', function () {
  const infos = generar({}, 900).filter(function (e) { return e.tipo === 'info'; });
  assert.ok(infos.length > 20);
  for (let i = 1; i < infos.length; i++) {
    const fin = infos[i - 1].t + infos[i - 1].dur;
    assert.ok(Math.abs(infos[i].t - fin) < 1e-6, 'hueco o solape en el bloque ' + i);
  }
  // y va cambiando: varias secciones, tonalidades y estilos
  const secciones = new Set(infos.map(function (i) { return i.seccion; }));
  const tonos = new Set(infos.map(function (i) { return i.tonalidad; }));
  const estilos = new Set(infos.map(function (i) { return i.estilo; }));
  assert.ok(secciones.size >= 5, 'secciones: ' + secciones.size);
  assert.ok(tonos.size >= 2, 'tonalidades: ' + tonos.size);
  assert.ok(estilos.size >= 2, 'estilos: ' + estilos.size);
});

test('fidelidad 1: todo son citas; fidelidad 0: todo es frase libre', function () {
  const citas = generar({ fidelidad: 1 }, 300).filter(function (e) { return e.tipo === 'info'; });
  const libres = generar({ fidelidad: 0 }, 300).filter(function (e) { return e.tipo === 'info'; });
  assert.ok(citas.every(function (i) { return i.material === 'cita' && i.cancion; }));
  assert.ok(libres.every(function (i) { return i.material === 'libre'; }));
  // las citas nombran canciones de la biblioteca
  const nombres = new Set(CANCIONES.map(function (c) { return c.nombre; }));
  assert.ok(citas.every(function (i) { return nombres.has(i.cancion); }));
});

test('una cita sin variación suena como la frase original (transportada)', function () {
  const g = new G.Generador({ canciones: [CANCIONES[0]], semilla: 3, fidelidad: 1, variacion: 0, humanizar: false });
  const evs = g.eventosHasta(60);
  const info = evs.find(function (e) { return e.tipo === 'info'; });
  const melodia = evs.filter(function (e) { return e.voz === 'melodia' && e.t < info.t + info.dur - 1e-6; });
  const original = CANCIONES[0];
  const f = original.frases[info.frase - 1];
  const intervalosOrig = [];
  for (let i = f.desde + 1; i < f.hasta && i - f.desde < melodia.length; i++) {
    const a = T.notaDeGrado(original.melodia[i - 1].g, original.melodia[i - 1].alt, original.tonica, original.modo);
    const b = T.notaDeGrado(original.melodia[i].g, original.melodia[i].alt, original.tonica, original.modo);
    intervalosOrig.push(b - a);
  }
  const intervalos = [];
  for (let i = 1; i < melodia.length && intervalos.length < intervalosOrig.length; i++) intervalos.push(melodia[i].nota - melodia[i - 1].nota);
  // misma tonalidad y modo que la canción (es la primera sección): mismos intervalos
  assert.deepEqual(intervalos, intervalosOrig);
});

test('las frases libres quedan en la escala de su sección', function () {
  const evs = generar({ fidelidad: 0, semilla: 9 }, 400);
  let info = null;
  let dentro = 0;
  let total = 0;
  for (const e of evs) {
    if (e.tipo === 'info') {
      info = e;
      continue;
    }
    if (e.voz !== 'melodia') continue;
    const partes = info.tonalidad.split(' ');
    const ton = T.NOMBRES.indexOf(partes[0]);
    const modo = partes[1];
    total++;
    if (T.gradoDe(e.nota, ton, modo).alt === 0) dentro++;
  }
  assert.ok(total > 50);
  assert.equal(dentro, total);
});

test('estilo fijo y tempo', function () {
  const evs = generar({ estilo: 'chip', tempo: 1.2 }, 200);
  const infos = evs.filter(function (e) { return e.tipo === 'info'; });
  assert.ok(infos.every(function (i) { return i.estilo === 'Chiptune'; }));
  assert.ok(evs.some(function (e) { return e.canal === 9; }), 'el chiptune lleva batería');
  const sinBateria = generar({ estilo: 'ambiente' }, 200);
  assert.ok(!sinBateria.some(function (e) { return e.canal === 9; }));
});

test('saltar corta lo que viene y empieza otra sección ahí', function () {
  const g = new G.Generador({ canciones: CANCIONES, semilla: 5 });
  g.eventosHasta(20);
  const antes = g.seccion.numero;
  g.saltar(20);
  const evs = g.eventosHasta(40);
  assert.ok(evs.every(function (e) { return e.t >= 20 - 1e-9; }));
  assert.equal(evs[0].tipo, 'info');
  assert.ok(evs[0].seccion > antes);
});

test('sin canciones también suena (paso a paso por la escala)', function () {
  const evs = new G.Generador({ canciones: [], semilla: 1 }).eventosHasta(60);
  assert.ok(evs.filter(function (e) { return e.voz === 'melodia'; }).length > 10);
});

test('conducción de voces: el acorde siguiente queda cerca del anterior', function () {
  const c = G.conducir(null, [0, 4, 7]);
  const f = G.conducir(c, [5, 9, 0]);
  const mov = f.reduce(function (s, n, i) { return s + Math.abs(n - c[i]); }, 0);
  assert.ok(mov <= 4, 'movimiento ' + mov + ': ' + c + ' -> ' + f);
});

test('eventosDeMidi: un MIDI tal cual', function () {
  const midi = M.leer(E.aMidi(E.EJEMPLOS[0]));
  const evs = G.eventosDeMidi(midi, 10);
  assert.equal(evs.length, midi.notas.length);
  assert.ok(evs.every(function (e) { return e.t >= 10; }));
});
