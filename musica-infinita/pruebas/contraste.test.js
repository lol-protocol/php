'use strict';

// Contraste del texto de la aplicación en el PEOR CASO: los paneles son translúcidos y están
// sobre visualizaciones que pueden ser muy claras, así que axe-core no puede decidir el
// contraste de esos textos (los deja «por revisar»). Aquí se calcula contra fondo blanco y
// negro detrás y se exige el mínimo de la WCAG AA (4,5:1 para texto normal). Los colores
// salen de css/estilo.css, para que un cambio de colores no pase sin pasar por aquí.

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const D = require('./ayudas/destellos.js');

const css = fs.readFileSync(path.join(__dirname, '..', 'css', 'estilo.css'), 'utf8');

function color(s) {
  s = s.trim();
  let m = /^#([0-9a-f]{6})$/i.exec(s);
  if (m) return { r: parseInt(m[1].slice(0, 2), 16), g: parseInt(m[1].slice(2, 4), 16), b: parseInt(m[1].slice(4), 16), a: 1 };
  m = /^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/.exec(s);
  if (m) return { r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] };
  throw new Error('color no entendido: ' + s);
}

function variable(nombre) {
  const m = new RegExp('--' + nombre + ':\\s*([^;]+?)\\s*(?:/\\*.*?\\*/)?;').exec(css);
  assert.ok(m, 'falta --' + nombre + ' en el CSS');
  return color(m[1]);
}

function fondoDe(selector) {
  const i = css.indexOf(selector + ' {');
  assert.ok(i >= 0, 'falta ' + selector);
  const bloque = css.slice(i, css.indexOf('}', i));
  const m = /background:\s*([^;]+);/.exec(bloque);
  assert.ok(m, selector + ' no tiene background');
  return color(m[1]);
}

/** El color de `c` sobre `base` (opaco). */
function sobre(c, base) {
  return { r: c.r * c.a + base.r * (1 - c.a), g: c.g * c.a + base.g * (1 - c.a), b: c.b * c.a + base.b * (1 - c.a), a: 1 };
}

function lum(c) {
  return D.luminancia(c.r, c.g, c.b);
}

function contraste(a, b) {
  const x = lum(a);
  const y = lum(b);
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

const BLANCO = { r: 255, g: 255, b: 255, a: 1 };
const NEGRO = { r: 0, g: 0, b: 0, a: 1 };

test('el cálculo de contraste da los valores conocidos de la norma', function () {
  assert.ok(Math.abs(contraste(BLANCO, NEGRO) - 21) < 1e-9);
  assert.ok(Math.abs(contraste(color('#767676'), BLANCO) - 4.54) < 0.01); // el gris mínimo de la WCAG AA
  assert.ok(Math.abs(contraste(color('#777777'), BLANCO) - 4.48) < 0.01);
});

test('el panel y la pantallita: todo el texto cumple 4,5:1 aunque detrás haya una visualización blanca o negra', function () {
  const panel = variable('panel');
  const lcd = fondoDe('.lcd');
  const pares = [
    ['texto sobre el panel', variable('texto'), panel],
    ['texto tenue sobre el panel', variable('tenue'), panel],
    ['acento sobre el panel', variable('acento'), panel],
    ['lcd sobre su fondo', variable('lcd'), lcd],
    ['tiempo de grabación sobre el panel', color('#ff7a88'), panel],
    ['elemento roto sobre el panel', color('#ff8a95'), panel],
  ];
  for (const [nombre, fg, fondo] of pares) {
    for (const [donde, base] of [['blanco', BLANCO], ['negro', NEGRO]]) {
      const r = contraste(fg, sobre(fondo, base));
      assert.ok(r >= 4.5, nombre + ' sobre ' + donde + ': ' + r.toFixed(2) + ':1 (el mínimo es 4,5:1)');
    }
  }
});

test('el botón grande de la bienvenida y el de grabar cumplen 4,5:1', function () {
  const texto = variable('texto');
  assert.ok(contraste(texto, color('#2b4dbb')) >= 4.5);
  assert.ok(contraste(texto, color('#a01d2b')) >= 4.5);
});

test('quien pide menos movimiento al sistema no ve transiciones', function () {
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)[\s\S]*transition: none !important/);
});
