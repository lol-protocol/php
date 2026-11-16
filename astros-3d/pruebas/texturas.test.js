'use strict';

// Texturas y colores: que se generen igual cada vez, con el tamaño pedido, sin costura
// en el meridiano, y que las estrellas sigan su temperatura.

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');

const T = require('../js/texturas.js');
const DATOS = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'datos', 'astros.json'), 'utf8'));
const ASTROS = DATOS.astros;

const ANCHO = 128;
const ALTO = 64;

// Media de un canal (0 = rojo, 1 = verde, 2 = azul) sobre toda la textura.
function mediaCanal(buf, canal) {
  let s = 0;
  const n = buf.length / 4;
  for (let i = 0; i < n; i++) s += buf[i * 4 + canal];
  return s / n;
}

test('hexARgb convierte #rrggbb a tres canales', () => {
  assert.deepEqual(T.hexARgb('#ff8000'), [255, 128, 0]);
  assert.deepEqual(T.hexARgb('#000000'), [0, 0, 0]);
});

test('crearRng con la misma semilla da la misma secuencia', () => {
  const a = T.crearRng(42), b = T.crearRng(42), c = T.crearRng(43);
  const sa = [a(), a(), a()], sb = [b(), b(), b()], sc = [c(), c(), c()];
  assert.deepEqual(sa, sb);
  assert.notDeepEqual(sa, sc);
  for (const v of sa) assert.ok(v >= 0 && v < 1);
});

test('semillaDe es estable y distingue ids', () => {
  assert.equal(T.semillaDe('marte'), T.semillaDe('marte'));
  assert.notEqual(T.semillaDe('marte'), T.semillaDe('venus'));
});

test('rampa devuelve los extremos y el punto medio de una paleta de dos colores', () => {
  const p = [[0, 0, 0], [200, 100, 50]];
  assert.deepEqual(T.rampa(p, 0), [0, 0, 0]);
  assert.deepEqual(T.rampa(p, 0.5), [100, 50, 25]);
  // Fuera de [0, 1] se sujeta al extremo: no se sale de la paleta.
  const fuera = T.rampa(p, 2);
  [200, 100, 50].forEach((v, i) => assert.ok(Math.abs(fuera[i] - v) < 0.01, `canal ${i}: ${fuera[i]}`));
});

test('temperaturaARgb: frío anaranjado, sol casi blanco, caliente azulado', () => {
  const [r3, g3, b3] = T.temperaturaARgb(3000);
  assert.equal(r3, 255);
  assert.ok(r3 > g3 && g3 > b3, '3000 K debe ser rojo-anaranjado');

  const [r6, g6, b6] = T.temperaturaARgb(6500);
  assert.ok(Math.min(r6, g6, b6) > 230, '6500 K debe ser casi blanco');

  const [r2, , b2] = T.temperaturaARgb(20000);
  assert.equal(b2, 255);
  assert.ok(b2 > r2, '20000 K debe ser azulado');
});

test('temperaturaARgb sujeta valores fuera de rango, sin NaN', () => {
  for (const k of [0, 500, 1000, 40000, 1e6]) {
    for (const c of T.temperaturaARgb(k)) {
      assert.ok(Number.isInteger(c) && c >= 0 && c <= 255, `K=${k}: canal ${c}`);
    }
  }
});

test('generarTextura: tamaño, opacidad total y determinismo para todos los astros', () => {
  for (const a of ASTROS) {
    const x = T.generarTextura(a, ANCHO, ALTO);
    const y = T.generarTextura(a, ANCHO, ALTO);
    assert.equal(x.length, ANCHO * ALTO * 4, `${a.id}: tamaño`);
    assert.deepEqual(x, y, `${a.id}: no es determinista`);
    for (let i = 3; i < x.length; i += 4) assert.equal(x[i], 255, `${a.id}: alfa`);
  }
});

test('generarTextura: sin costura en el meridiano (primera y última columna parecidas)', () => {
  // Compara la columna 0 con la última: el ruido se muestrea sobre la esfera, así que
  // deben ser vecinas. Un valor alto delataría un salto en la longitud 0.
  for (const a of ASTROS.filter(x => x.id !== 'sol')) {
    const buf = T.generarTextura(a, ANCHO, ALTO);
    let dif = 0;
    for (let y = 0; y < ALTO; y++) {
      const i0 = (y * ANCHO) * 4, i1 = (y * ANCHO + ANCHO - 1) * 4;
      dif += Math.abs(buf[i0] - buf[i1]) + Math.abs(buf[i0 + 2] - buf[i1 + 2]);
    }
    const media = dif / (ALTO * 2);
    assert.ok(media < 60, `${a.id}: diferencia media en la costura ${media.toFixed(1)}`);
  }
});

test('generarTextura: cada astro tiene algo de contraste (no es un color plano)', () => {
  for (const a of ASTROS) {
    const buf = T.generarTextura(a, ANCHO, ALTO);
    let min = 255, max = 0;
    for (let i = 0; i < buf.length; i += 4) {
      const l = buf[i] + buf[i + 1] + buf[i + 2];
      if (l < min) min = l;
      if (l > max) max = l;
    }
    assert.ok(max - min > 12, `${a.id}: rango de luminancia ${max - min}`);
  }
});

test('las estrellas toman el color de su temperatura: Sirio azulado, Betelgeuse rojizo', () => {
  const sirio = ASTROS.find(a => a.id === 'sirio');
  const betel = ASTROS.find(a => a.id === 'betelgeuse');
  const bs = T.generarTextura(sirio, ANCHO, ALTO);
  const bb = T.generarTextura(betel, ANCHO, ALTO);
  assert.ok(mediaCanal(bs, 2) > mediaCanal(bs, 0), 'Sirio debe tener más azul que rojo');
  assert.ok(mediaCanal(bb, 0) > mediaCanal(bb, 2) + 40, 'Betelgeuse debe tener mucho más rojo que azul');
});

test('una estrella con color propio lo usa; sin él, usa el de su temperatura', () => {
  const base = { id: 'prueba', temperatura_superficial_k: 5800, textura: { tipo: 'estrella', granulos: 6, escala: 4 } };
  const conColor = { id: 'prueba', temperatura_superficial_k: 5800, textura: { tipo: 'estrella', granulos: 6, escala: 4, color: '#6f9bff' } };
  const a = T.generarTextura(base, ANCHO, ALTO);
  const b = T.generarTextura(conColor, ANCHO, ALTO);
  assert.ok(mediaCanal(a, 0) > mediaCanal(a, 2), 'sin color propio debe verse cálida (5800 K)');
  assert.ok(mediaCanal(b, 2) > mediaCanal(b, 0), 'con #6f9bff debe verse azul');
});

test('las texturas de las estrellas son distintas entre sí por su color', () => {
  const medias = ASTROS.filter(a => a.categoria === 'sol').map(a => {
    const buf = T.generarTextura(a, ANCHO, ALTO);
    return [0, 1, 2].map(c => Math.round(mediaCanal(buf, c) / 4)).join(',');
  });
  assert.ok(new Set(medias).size >= medias.length - 3, 'demasiadas estrellas con el mismo color medio');
});

test('generarAnillo: alfa entre 0 y 255, con los bordes más transparentes que el centro', () => {
  const anillo = ASTROS.find(a => a.id === 'saturno').textura.anillos;
  const buf = T.generarAnillo(anillo, 256, 1);
  assert.equal(buf.length, 256 * 4);
  const alfa = i => buf[i * 4 + 3];
  for (let i = 0; i < 256; i++) assert.ok(alfa(i) >= 0 && alfa(i) <= 255);
  const bordes = (alfa(0) + alfa(255)) / 2;
  const centro = alfa(128);
  assert.ok(centro > bordes, 'el centro del anillo debe ser más denso que sus bordes');
});

test('generarAnillo respeta la opacidad máxima', () => {
  const anillo = ASTROS.find(a => a.id === 'urano').textura.anillos;
  const buf = T.generarAnillo(anillo, 128, 0.5);
  let max = 0;
  for (let i = 3; i < buf.length; i += 4) if (buf[i] > max) max = buf[i];
  assert.ok(max <= 128, `alfa máximo ${max} con opacidad 0,5`);
});
