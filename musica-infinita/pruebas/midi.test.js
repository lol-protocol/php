'use strict';

// Lector y escritor de MIDI: ida y vuelta, mapa de tempos, running status y casos raros.

const test = require('node:test');
const assert = require('node:assert/strict');
const M = require('../js/midi.js');

function cerca(a, b, tol) {
  assert.ok(Math.abs(a - b) <= (tol || 1e-6), a + ' no está cerca de ' + b);
}

test('ida y vuelta: las notas, el tempo y el compás vuelven iguales', function () {
  const bytes = M.escribir({
    ppq: 480,
    bpm: 90,
    compas: [3, 4],
    pistas: [
      { nombre: 'Melodía ñandú', canal: 0, programa: 73, notas: [{ nota: 60, inicio: 0, dur: 1, vel: 100 }, { nota: 64, inicio: 1, dur: 0.5, vel: 80 }] },
      { nombre: 'Bajo', canal: 2, programa: 32, notas: [{ nota: 36, inicio: 0, dur: 3, vel: 70 }] },
    ],
  });
  const m = M.leer(bytes);
  assert.equal(m.formato, 1);
  assert.equal(m.ppq, 480);
  assert.equal(m.notas.length, 3);
  assert.equal(m.pistas[1].nombre, 'Melodía ñandú');
  assert.deepEqual(m.compases[0], { tick: 0, num: 3, den: 4 });
  cerca(60e6 / m.tempos[0].uspq, 90, 0.01);
  const do4 = m.notas.find(function (n) { return n.nota === 60; });
  assert.equal(do4.programa, 73);
  cerca(do4.dur, 60 / 90, 1e-3); // una negra a 90 bpm
  const mi4 = m.notas.find(function (n) { return n.nota === 64; });
  cerca(mi4.inicio, 60 / 90, 1e-3);
  cerca(mi4.durPulsos, 0.5);
  assert.equal(m.notas.find(function (n) { return n.nota === 36; }).canal, 2);
});

test('mapa de tempos: los segundos siguen los cambios de tempo', function () {
  const bytes = M.escribir({
    ppq: 96,
    tempos: [{ pulso: 0, bpm: 120 }, { pulso: 4, bpm: 60 }],
    pistas: [{ canal: 0, notas: [{ nota: 60, inicio: 2, dur: 1 }, { nota: 62, inicio: 4, dur: 1 }, { nota: 64, inicio: 6, dur: 2 }] }],
  });
  const m = M.leer(bytes);
  const t = m.notas.map(function (n) { return n.inicio; });
  cerca(t[0], 1, 1e-3); // 2 pulsos a 120
  cerca(t[1], 2, 1e-3); // 4 pulsos a 120
  cerca(t[2], 4, 1e-3); // + 2 pulsos a 60
  cerca(m.notas[2].dur, 2, 1e-3);
});

test('running status, nota encendida con velocidad 0 y notas superpuestas', function () {
  // MThd + una pista escrita a mano
  const pista = [
    0x00, 0xc0, 0x05, // programa 5
    0x00, 0x90, 60, 100, // do on
    0x00, 64, 90, // mi on (running status)
    0x60, 60, 0, // do off con velocidad 0 (running status)
    0x00, 64, 0,
    0x00, 0x90, 67, 80, // sol on dos veces seguidas
    0x10, 67, 80,
    0x10, 0x80, 67, 0, // primer apagado: cierra la primera
    0x10, 67, 0,
    0x00, 0xff, 0x2f, 0x00,
  ];
  const cab = [0x4d, 0x54, 0x68, 0x64, 0, 0, 0, 6, 0, 0, 0, 1, 0, 96];
  const trk = [0x4d, 0x54, 0x72, 0x6b, 0, 0, 0, pista.length].concat(pista);
  const m = M.leer(new Uint8Array(cab.concat(trk)));
  assert.equal(m.formato, 0);
  assert.equal(m.notas.length, 4);
  const do4 = m.notas.find(function (n) { return n.nota === 60; });
  assert.equal(do4.durTicks, 0x60);
  assert.equal(do4.programa, 5);
  const soles = m.notas.filter(function (n) { return n.nota === 67; });
  assert.equal(soles[0].durTicks, 0x20);
  assert.equal(soles[1].durTicks, 0x20);
});

test('una nota sin apagar se cierra al final, y un RIFF (RMID) se lee igual', function () {
  const pista = [0x00, 0x90, 60, 100, 0x83, 0x00, 0xff, 0x2f, 0x00];
  const smf = [0x4d, 0x54, 0x68, 0x64, 0, 0, 0, 6, 0, 0, 0, 1, 0, 96, 0x4d, 0x54, 0x72, 0x6b, 0, 0, 0, pista.length].concat(pista);
  const riff = [0x52, 0x49, 0x46, 0x46, 0, 0, 0, 0, 0x52, 0x4d, 0x49, 0x44, 0x64, 0x61, 0x74, 0x61, 0, 0, 0, 0].concat(smf);
  for (const b of [smf, riff]) {
    const m = M.leer(new Uint8Array(b));
    assert.equal(m.notas.length, 1);
    assert.equal(m.notas[0].durTicks, 384);
  }
});

test('cambio de programa a mitad de pista al escribir', function () {
  const bytes = M.escribir({ pistas: [{ canal: 1, programa: 0, notas: [{ nota: 60, inicio: 0, dur: 1, programa: 0 }, { nota: 62, inicio: 1, dur: 1, programa: 40 }] }] });
  const m = M.leer(bytes);
  assert.deepEqual(m.notas.map(function (n) { return n.programa; }), [0, 40]);
});

test('errores claros con archivos que no son MIDI', function () {
  assert.throws(function () { M.leer(new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8])); }, /No es un archivo MIDI/);
  assert.throws(function () { M.leer('hola'); }, /ArrayBuffer/);
});
