import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarSVG, ATUENDOS } from '../src/index.js';
import { medidas } from '../src/cuerpo.js';
import { puntosDeRuta } from './ayuda.js';

/** Contenido del grupo `<g data-saludo>` (con sus grupos anidados bien cerrados), o null. */
function grupoSaludo(svg) {
  const ini = svg.indexOf('<g data-saludo="');
  if (ini < 0) return null;
  const lado = svg.slice(ini).match(/^<g data-saludo="(\w+)">/);
  let prof = 0, i = ini;
  for (const m of svg.slice(ini).matchAll(/<g[ >]|<\/g>/g)) {
    prof += m[0].startsWith('</') ? -1 : 1;
    i = ini + m.index + m[0].length;
    if (prof === 0) break;
  }
  return { lado: lado[1], cuerpo: svg.slice(ini + lado[0].length, i - 4) };
}

/** Coordenadas absolutas (x, y) de todas las formas del grupo del saludo (están definidas aparte y se usan con <use>). */
function puntosSaludo(svg) {
  const g = grupoSaludo(svg);
  if (!g) return null;
  const ids = [...new Set([...g.cuerpo.matchAll(/<use href="#([^"]+)" fill=/g)].map((m) => m[1]))];
  const pts = [];
  for (const id of ids) {
    const d = svg.match(new RegExp(`<path id="${id}" d="([^"]+)"`))[1];
    pts.push(...puntosDeRuta(d));
  }
  return { lado: g.lado, pts, formas: ids.length };
}

test('por defecto saluda la mano derecha de la persona, a la izquierda de quien mira', () => {
  const r = puntosSaludo(generarSVG({ semilla: 1 }));
  assert.equal(r.lado, 'derecha');
  assert.ok(r.pts.length > 40);
  assert.ok(r.pts.every(([x]) => x < 300), 'toda la mano debe quedar a la izquierda del eje');
  assert.ok(Math.max(...r.pts.map((p) => p[0])) < 300);
});

test('la mano izquierda saluda del otro lado y sin saludo no hay mano levantada', () => {
  const i = puntosSaludo(generarSVG({ semilla: 1, saludo: 'izquierda' }));
  assert.equal(i.lado, 'izquierda');
  assert.ok(i.pts.every(([x]) => x > 300));
  for (const v of ['ninguno', false]) assert.equal(puntosSaludo(generarSVG({ semilla: 1, saludo: v })), null);
  assert.notEqual(generarSVG({ semilla: 1 }), generarSVG({ semilla: 1, saludo: 'ninguno' }));
});

test('la mano queda levantada por encima del hombro, cerca de la cabeza y sin salirse del lienzo', () => {
  for (const sexo of ['f', 'm']) for (const edad of [3, 9, 16, 35, 80]) for (const corpulencia of ['delgada', 'corpulenta']) {
    const M = medidas({ sexo, edad, corpulencia });
    const { pts } = puntosSaludo(generarSVG({ semilla: 2, sexo, edad, corpulencia, saludo: 'derecha' }));
    const ymin = Math.min(...pts.map((p) => p[1]));
    assert.ok(ymin < M.y.hombro, `${sexo} ${edad}: la mano no sube del hombro`);
    assert.ok(ymin > 0, `${sexo} ${edad}: la mano se sale por arriba`);
    assert.ok(Math.min(...pts.map((p) => p[0])) > 0);
  }
});

test('todos los atuendos se dibujan con saludo a ambos lados', () => {
  for (const a of ATUENDOS) for (const saludo of ['derecha', 'izquierda']) {
    const svg = generarSVG({ atuendo: a.id, sexo: a.sexo, edad: 30, semilla: 9, saludo });
    assert.ok(!/NaN|undefined/.test(svg), `${a.id} ${saludo}`);
    assert.equal(puntosSaludo(svg).lado, saludo);
  }
});

test('la mano abierta tiene palma, cuatro dedos y pulgar', () => {
  const r = puntosSaludo(generarSVG({ semilla: 1, atuendo: 'egipto-mujer-comun' }));
  assert.equal(r.formas, 6, 'palma + 4 dedos + pulgar');
});
