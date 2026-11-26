'use strict';

// La medición de sonoridad de las pruebas de la mezcla: contra valores conocidos de la norma
// y, si está ffmpeg, contra su filtro ebur128.

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const L = require('./ayudas/sonoridad.js');
const F = require('../js/flac.js');

function seno(frec, amp, seg) {
  const x = new Float32Array(48000 * seg);
  for (let i = 0; i < x.length; i++) x[i] = amp * Math.sin((2 * Math.PI * frec * i) / 48000);
  return x;
}

test('sonoridad: un seno de 997 Hz a -20 dBFS en los dos canales mide -20 LUFS (el caso de la norma)', function () {
  const x = seno(997, 0.1, 10);
  const v = L.lufs([x, x], 48000);
  assert.ok(Math.abs(v - -20.0) < 0.1, 'mide ' + v.toFixed(2) + ' LUFS');
  // y en un solo canal, 3 LU menos
  const mono = L.lufs([x, new Float32Array(x.length)], 48000);
  assert.ok(Math.abs(mono - (v - 3.01)) < 0.05);
});

test('sonoridad: el silencio no tiene sonoridad, los graves pesan menos y la puerta ignora los pasajes callados', function () {
  assert.equal(L.lufs([new Float32Array(48000 * 3)], 48000), -Infinity);
  const agudo = L.lufs([seno(1000, 0.1, 5)], 48000);
  const grave = L.lufs([seno(40, 0.1, 5)], 48000);
  assert.ok(grave < agudo - 5, 'el filtro K debe restar bastante a un grave: ' + grave.toFixed(1) + ' contra ' + agudo.toFixed(1));
  // diez segundos a -20 dBFS y diez a -60: la puerta relativa deja fuera los de -60
  const fuerte = seno(997, 0.1, 10);
  const mezcla = new Float32Array(fuerte.length * 2);
  mezcla.set(fuerte, 0);
  mezcla.set(seno(997, 0.001, 10), fuerte.length);
  assert.ok(Math.abs(L.lufs([mezcla], 48000) - L.lufs([fuerte], 48000)) < 0.1);
  assert.throws(function () { L.lufs([fuerte], 44100); }, /48 kHz/);
});

test('pico y corriente continua', function () {
  const x = new Float32Array([0.1, -0.5, 0.25, 0.5]);
  assert.ok(Math.abs(L.picoDb([x]) - 20 * Math.log10(0.5)) < 1e-9);
  assert.equal(L.picoDb([new Float32Array(4)]), -Infinity);
  assert.ok(Math.abs(L.continua([new Float32Array([0.2, 0.2, 0.2]), new Float32Array([0.1, 0.1, 0.1])]) - 0.2) < 1e-6);
  assert.ok(L.continua([seno(100, 0.5, 1)]) < 1e-3);
});

test('WAV: lo que escribe F.wav se lee igual', function () {
  const x = seno(440, 0.3, 1);
  const w = L.leerWav16(F.wav([x, x], 48000, 16));
  assert.equal(w.sr, 48000);
  assert.equal(w.canales.length, 2);
  assert.ok(Math.abs(w.canales[0][1000] - x[1000]) < 1 / 32767);
  assert.throws(function () { L.leerWav16(new Uint8Array(12)); }, /sin datos/);
});

let hayFfmpeg = true;
try {
  execFileSync('ffmpeg', ['-version'], { stdio: 'ignore' });
} catch (e) {
  hayFfmpeg = false;
}

test('sonoridad: coincide con el filtro ebur128 de ffmpeg en música de verdad', { skip: !hayFfmpeg && 'ffmpeg no está instalado' }, function () {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'lufs-'));
  try {
    // una señal con silencios, dos tonos y un pasaje más fuerte, para que las puertas trabajen
    const n = 48000 * 12;
    const x = new Float32Array(n);
    for (let i = 0; i < n; i++) {
      const t = i / 48000;
      const gan = t < 4 ? 0.05 : t < 5 ? 0 : t < 9 ? 0.2 : 0.4;
      x[i] = gan * (Math.sin(2 * Math.PI * 330 * t) + 0.5 * Math.sin(2 * Math.PI * 2100 * t) + 0.3 * Math.sin(2 * Math.PI * 90 * t));
    }
    const wav = path.join(dir, 'a.wav');
    fs.writeFileSync(wav, F.wav([x, x.slice().reverse()], 48000, 16));
    const sal = execFileSync('ffmpeg', ['-nostats', '-i', wav, '-af', 'ebur128=framelog=quiet', '-f', 'null', '-'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
    void sal;
    const err = require('node:child_process').spawnSync('ffmpeg', ['-nostats', '-i', wav, '-af', 'ebur128=framelog=quiet', '-f', 'null', '-'], { encoding: 'utf8' }).stderr;
    const m = /I:\s+(-?[\d.]+) LUFS/.exec(err);
    assert.ok(m, 'ffmpeg no dio la sonoridad integrada');
    const w = L.leerWav16(fs.readFileSync(wav));
    const mia = L.lufs(w.canales, 48000);
    assert.ok(Math.abs(mia - Number(m[1])) < 0.3, 'la mía da ' + mia.toFixed(2) + ' y ffmpeg ' + m[1]);
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});
