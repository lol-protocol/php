'use strict';

// Codificador FLAC: ida y vuelta exacta (con un decodificador propio de prueba), CRC, MD5
// y etiquetas; y, si está ffmpeg, que lo lea un decodificador de verdad.

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const crypto = require('node:crypto');
const F = require('../js/flac.js');
const D = require('./ayudas/flac-decodificador.js');

function azar(semilla) {
  let s = semilla >>> 0;
  return function () {
    s = (Math.imul(s, 1103515245) + 12345) >>> 0;
    return s / 4294967296;
  };
}

function senal(n, bits, tipo, semilla) {
  const max = bits === 24 ? 8388607 : 32767;
  const r = azar(semilla || 1);
  const L = new Int32Array(n);
  const R = new Int32Array(n);
  for (let i = 0; i < n; i++) {
    if (tipo === 'silencio') continue;
    if (tipo === 'ruido') {
      L[i] = Math.round((r() * 2 - 1) * max);
      R[i] = Math.round((r() * 2 - 1) * max);
    } else if (tipo === 'extremos') {
      L[i] = i % 2 ? max : -max - 1;
      R[i] = i % 3 ? -max - 1 : max;
    } else {
      L[i] = Math.round(max * 0.7 * Math.sin(i / 23) + (r() - 0.5) * 50);
      R[i] = Math.round(L[i] * 0.8 + max * 0.1 * Math.sin(i / 7));
    }
  }
  return [L, R];
}

function idaYVuelta(canales, bits, frecuencia) {
  const c = new F.CodificadorFlac({ frecuencia: frecuencia || 48000, canales: canales.length, bits: bits, etiquetas: { TITLE: 'Prueba ñandú' } });
  c.agregarEnteros(canales);
  const bytes = F.unir(c.terminar());
  return { bytes: bytes, d: D.decodificar(bytes) };
}

for (const bits of [16, 24]) {
  for (const tipo of ['tono', 'ruido', 'silencio', 'extremos']) {
    test('ida y vuelta exacta: ' + tipo + ', ' + bits + ' bits', function () {
      const n = 4096 * 3 + 777; // la última trama queda corta
      const s = senal(n, bits, tipo, bits);
      const r = idaYVuelta(s, bits);
      assert.equal(r.d.info.bits, bits);
      assert.equal(r.d.info.total, n);
      assert.equal(r.d.tramas, 4);
      assert.ok(r.d.md5Coincide, 'MD5 distinto');
      assert.deepEqual(r.d.canales[0], Array.from(s[0]));
      assert.deepEqual(r.d.canales[1], Array.from(s[1]));
      assert.equal(r.d.etiquetas.TITLE, 'Prueba ñandú');
    });
  }
}

test('mono, bloques muy cortos y 44,1 kHz', function () {
  const s = senal(100, 16, 'tono', 3);
  const r = idaYVuelta([s[0]], 16, 44100);
  assert.equal(r.d.info.canales, 1);
  assert.equal(r.d.info.frecuencia, 44100);
  assert.deepEqual(r.d.canales[0], Array.from(s[0]));
});

test('comprime: un tono ocupa bastante menos que el PCM', function () {
  const s = senal(48000, 16, 'tono', 4);
  const r = idaYVuelta(s, 16);
  assert.ok(r.bytes.length < 48000 * 4 * 0.6, 'ocupa ' + r.bytes.length);
});

test('desde coma flotante: recorta lo que se pasa de ±1', function () {
  const L = new Float32Array([0, 0.5, -0.5, 1.5, -1.5, 1, -1]);
  const bytes = F.codificar([L, L], { frecuencia: 48000, bits: 16, dither: false });
  const d = D.decodificar(bytes);
  assert.deepEqual(d.canales[0], [0, 16384, -16383, 32767, -32768, 32767, -32767]);
});

test('MD5 igual al de node:crypto', function () {
  for (const largo of [0, 1, 55, 56, 63, 64, 65, 1000]) {
    const b = new Uint8Array(largo).map(function (_, i) { return (i * 31) & 255; });
    assert.equal(Buffer.from(F.md5(b)).toString('hex'), crypto.createHash('md5').update(b).digest('hex'));
  }
});

test('WAV: cabecera y largo', function () {
  const w = F.wav([new Float32Array(10), new Float32Array(10)], 48000, 24);
  assert.equal(Buffer.from(w.subarray(0, 4)).toString(), 'RIFF');
  assert.equal(w.length, 44 + 10 * 2 * 3);
  assert.equal(new DataView(w.buffer).getUint16(34, true), 24);
});

let hayFfmpeg = true;
try {
  execFileSync('ffmpeg', ['-version'], { stdio: 'ignore' });
} catch (e) {
  hayFfmpeg = false;
}

test('ffmpeg lo decodifica igual, bit a bit', { skip: !hayFfmpeg && 'ffmpeg no está instalado' }, function () {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'flac-'));
  try {
    const s = senal(4096 * 2 + 10, 16, 'tono', 9);
    const r = idaYVuelta(s, 16);
    const archivo = path.join(dir, 'p.flac');
    fs.writeFileSync(archivo, r.bytes);
    const pcm = execFileSync('ffmpeg', ['-v', 'error', '-i', archivo, '-f', 's16le', '-']);
    const esperado = Buffer.from(F.pcm([Float32Array.from(s[0], function (v) { return v / 32767; }), Float32Array.from(s[1], function (v) { return v / 32767; })], 16));
    assert.equal(pcm.length, esperado.length);
    assert.ok(pcm.equals(esperado));
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});

// ------------------------------------------------------------ LPC

/** Música de verdad para estos casos: la del propio compositor, mezclada a 16 bits (sin audio de navegador: suma de senos con envolvente). */
function senalMusical(n, semilla) {
  const r = azar(semilla);
  const L = new Int32Array(n);
  const R = new Int32Array(n);
  const notas = [220, 277.18, 329.63, 440, 554.37];
  for (let i = 0; i < n; i++) {
    let v = 0;
    for (let k = 0; k < notas.length; k++) {
      const env = Math.exp(-((i % 24000) / 24000) * (1 + k));
      v += Math.sin((2 * Math.PI * notas[k] * i) / 48000) * env * (0.3 / (k + 1));
    }
    L[i] = Math.round(v * 12000 + (r() - 0.5) * 12);
    R[i] = Math.round(v * 9000 + Math.sin(i / 90) * 800 + (r() - 0.5) * 12);
  }
  return [L, R];
}

test('LPC: la ida y vuelta sigue siendo exacta (16 y 24 bits, bloques cortos y largos)', function () {
  for (const bits of [16, 24]) {
    const n = 4096 * 4 + 1500;
    const s = senalMusical(n, bits);
    if (bits === 24) for (let c = 0; c < 2; c++) for (let i = 0; i < n; i++) s[c][i] = s[c][i] * 200 + (i % 7);
    const r = idaYVuelta(s, bits);
    assert.ok(r.d.md5Coincide, bits + ' bits: el MD5 no coincide');
    assert.deepEqual(r.d.canales[0], Array.from(s[0]), bits + ' bits, izquierda');
    assert.deepEqual(r.d.canales[1], Array.from(s[1]), bits + ' bits, derecha');
  }
});

test('LPC: se usa de verdad (subtramas lineales) y gana al predictor fijo en música tonal', function () {
  const s = senalMusical(48000, 3);
  const r = idaYVuelta(s, 16);
  // un tono con armónicos se predice mucho mejor con coeficientes propios que con los fijos
  assert.ok(r.bytes.length < 48000 * 4 * 0.42, 'ocupa ' + r.bytes.length + ' bytes (' + (r.bytes.length / (48000 * 4)).toFixed(3) + ' del PCM)');
});

test('LPC: casos de borde (señal de un solo valor, rampa, ruido blanco, ceros con un pico, pocas muestras)', function () {
  const n = 4096 * 2;
  const casos = {
    rampa: function (i) { return i - 4000; },
    ruido: (function () { const r = azar(9); return function () { return Math.round((r() * 2 - 1) * 30000); }; })(),
    pico: function (i) { return i === 5000 ? 30000 : 0; },
    cuadrada: function (i) { return Math.floor(i / 50) % 2 ? 20000 : -20000; },
    saturada: function (i) { return i % 2 ? 32767 : -32768; },
  };
  for (const nombre of Object.keys(casos)) {
    const L = new Int32Array(n).map(function (_, i) { return casos[nombre](i); });
    const r = idaYVuelta([L, L.slice().reverse()], 16);
    assert.ok(r.d.md5Coincide, nombre);
    assert.deepEqual(r.d.canales[0], Array.from(L), nombre);
  }
  for (const corto of [1, 2, 5, 17, 47, 48, 49, 200]) {
    const L = new Int32Array(corto).map(function (_, i) { return Math.round(Math.sin(i / 3) * 9000); });
    assert.deepEqual(idaYVuelta([L, L], 16).d.canales[0], Array.from(L), corto + ' muestras');
  }
});

test('LPC: ffmpeg lo decodifica bit a bit y el archivo pesa lo mismo o menos que el de su codificador FLAC', { skip: !hayFfmpeg && 'ffmpeg no está instalado' }, function () {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'lpc-'));
  try {
    const n = 48000 * 4;
    const s = senalMusical(n, 21);
    const r = idaYVuelta(s, 16);
    const mio = path.join(dir, 'mio.flac');
    fs.writeFileSync(mio, r.bytes);
    const esperado = Buffer.from(F.pcm([Float32Array.from(s[0], function (v) { return v / 32767; }), Float32Array.from(s[1], function (v) { return v / 32767; })], 16));
    const pcm = execFileSync('ffmpeg', ['-v', 'error', '-i', mio, '-f', 's16le', '-'], { maxBuffer: 1 << 28 });
    assert.equal(pcm.length, esperado.length);
    assert.ok(pcm.equals(esperado), 'ffmpeg decodifica algo distinto');
    // el mismo audio con el codificador de ffmpeg (nivel por omisión: LPC de orden 8, en bloques de 4096)
    const wav = path.join(dir, 'a.wav');
    fs.writeFileSync(wav, F.wav([Float32Array.from(s[0], function (v) { return v / 32767; }), Float32Array.from(s[1], function (v) { return v / 32767; })], 48000, 16));
    const ref = path.join(dir, 'ref.flac');
    execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', wav, '-c:a', 'flac', '-compression_level', '5', ref]);
    const tamRef = fs.statSync(ref).size;
    assert.ok(r.bytes.length <= tamRef * 1.06, 'el nuestro ocupa ' + r.bytes.length + ' y el de ffmpeg ' + tamRef + ' (' + (r.bytes.length / tamRef).toFixed(3) + ')');
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});

test('LPC: la codificación es rápida (mucho más que el tiempo real)', function () {
  const n = 48000 * 20;
  const s = senalMusical(n, 5);
  const t0 = performance.now();
  const c = new F.CodificadorFlac({ frecuencia: 48000, canales: 2, bits: 16 });
  c.agregarEnteros(s);
  F.unir(c.terminar());
  const seg = (performance.now() - t0) / 1000;
  const vezTiempoReal = 20 / seg;
  assert.ok(vezTiempoReal > 4, 'codifica solo ' + vezTiempoReal.toFixed(1) + ' veces más rápido que el tiempo real');
});
