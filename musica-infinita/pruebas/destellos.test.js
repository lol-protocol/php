'use strict';

// El análisis de destellos de las pruebas de seguridad visual: que detecte un estroboscopio,
// deje pasar lo suave y respete las condiciones de la norma (área, umbral y luminancia).

const test = require('node:test');
const assert = require('node:assert/strict');
const D = require('./ayudas/destellos.js');

const CELDAS = 144; // 16 x 9

/** Frames a `fps` durante `seg` segundos; f(t) da la luminancia de las primeras `celdas` celdas (las demás, 0,05). */
function secuencia(fps, seg, f, celdas) {
  const frames = [];
  for (let i = 0; i < fps * seg; i++) {
    const t = i / fps;
    const lum = new Float32Array(CELDAS).fill(0.05);
    for (let k = 0; k < (celdas == null ? CELDAS : celdas); k++) lum[k] = f(t);
    frames.push({ t: t, lum: lum });
  }
  return frames;
}

const cuadrada = function (hz, bajo, alto) {
  return function (t) { return Math.floor(t * hz * 2) % 2 ? alto : bajo; };
};

test('luminancia relativa: negro, blanco y gris', function () {
  assert.equal(D.luminancia(0, 0, 0), 0);
  assert.ok(Math.abs(D.luminancia(255, 255, 255) - 1) < 1e-9);
  assert.ok(Math.abs(D.luminancia(128, 128, 128) - 0.2159) < 1e-3);
  assert.ok(D.luminancia(0, 255, 0) > D.luminancia(255, 0, 0));
});

test('un estroboscopio de 10 Hz a pantalla completa se detecta con claridad', function () {
  const r = D.analizar(secuencia(60, 3, cuadrada(10, 0.02, 0.9)));
  assert.ok(r.maxPorSegundo >= 9, 'detectó solo ' + r.maxPorSegundo + ' destellos por segundo');
  assert.ok(r.alternados > 40);
});

test('el límite de la norma: 3 destellos por segundo pasan y 4 no', function () {
  assert.equal(D.analizar(secuencia(120, 4, cuadrada(2, 0.02, 0.9))).maxPorSegundo, 2);
  assert.equal(D.analizar(secuencia(120, 4, cuadrada(3, 0.02, 0.9))).maxPorSegundo, 3);
  assert.ok(D.analizar(secuencia(120, 4, cuadrada(4, 0.02, 0.9))).maxPorSegundo >= 4);
  assert.ok(D.analizar(secuencia(120, 4, cuadrada(6, 0.02, 0.9))).maxPorSegundo >= 6);
});

test('un solo destello (sube y baja una vez) cuenta uno; el silencio y lo constante, cero', function () {
  const uno = secuencia(60, 2, function (t) { return t >= 0.5 && t < 0.6 ? 0.9 : 0.02; });
  assert.equal(D.analizar(uno).maxPorSegundo, 1);
  assert.equal(D.analizar(secuencia(60, 2, function () { return 0.3; })).maxPorSegundo, 0);
  assert.equal(D.analizar([]).maxPorSegundo, 0);
  assert.equal(D.analizar(secuencia(60, 1, function () { return 0.3; }).slice(0, 1)).maxPorSegundo, 0);
});

test('lo suave no es un destello: una onda lenta cambia menos de 0,1 por cuadro', function () {
  // 1 Hz entre 0,05 y 0,75, a 60 cuadros por segundo: el salto máximo por cuadro es de ~0,07
  const r = D.analizar(secuencia(60, 5, function (t) { return 0.4 + 0.35 * Math.sin(2 * Math.PI * t); }));
  assert.equal(r.cambios, 0);
  assert.equal(r.maxPorSegundo, 0);
});

test('el área cuenta: 3 celdas que parpadean no son un destello, 4 sí', function () {
  assert.equal(D.analizar(secuencia(60, 3, cuadrada(10, 0.02, 0.9), 3)).maxPorSegundo, 0);
  assert.ok(D.analizar(secuencia(60, 3, cuadrada(10, 0.02, 0.9), 4)).maxPorSegundo >= 9);
});

test('entre dos tonos ya muy claros (el más oscuro sobre 0,8) no hay destello', function () {
  assert.equal(D.analizar(secuencia(60, 3, cuadrada(10, 0.82, 1.0))).maxPorSegundo, 0);
  // pero de 0,7 a 1,0 sí (la norma mira el más oscuro de los dos)
  assert.ok(D.analizar(secuencia(60, 3, cuadrada(10, 0.7, 1.0))).maxPorSegundo >= 9);
});

test('la ventana es deslizante: tres ráfagas de dos destellos separadas por un segundo no suman', function () {
  const f = function (t) {
    const m = t % 1;
    return m < 0.1 || (m >= 0.2 && m < 0.3) ? 0.9 : 0.02; // dos destellos al principio de cada segundo
  };
  const r = D.analizar(secuencia(120, 4, f));
  assert.equal(r.maxPorSegundo, 2);
});

test('con otra frecuencia de cuadros da lo mismo: la medida es por segundo, no por cuadro', function () {
  for (const fps of [24, 30, 60, 144]) {
    assert.equal(D.analizar(secuencia(fps, 3, cuadrada(2, 0.02, 0.9))).maxPorSegundo, 2, fps + ' cuadros por segundo');
  }
});
