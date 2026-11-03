import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarSVG, ATUENDOS } from '../src/index.js';
import { medidas } from '../src/cuerpo.js';

/** Coordenadas absolutas (x, y) de todos los trazos del grupo del saludo. */
function puntosSaludo(svg) {
  const g = svg.match(/<g data-saludo="(\w+)">([\s\S]*?)<\/g>/);
  if (!g) return null;
  const pts = [...g[2].matchAll(/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/g)].map((m) => [Number(m[1]), Number(m[2])]);
  return { lado: g[1], pts };
}

test('por defecto saluda la mano derecha de la persona, a la izquierda de quien mira', () => {
  const r = puntosSaludo(generarSVG({ semilla: 1 }));
  assert.equal(r.lado, 'derecha');
  assert.ok(r.pts.length > 40);
  assert.ok(r.pts.every(([x]) => x < 300), 'toda la mano debe quedar a la izquierda del eje');
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
  const g = generarSVG({ semilla: 1, atuendo: 'egipto-mujer-comun' }).match(/<g data-saludo="\w+">([\s\S]*?)<\/g>/)[1];
  const rutas = [...g.matchAll(/<path d="(M[^"]+)" fill="#[0-9a-f]{6}" stroke=/g)];
  assert.equal(rutas.length, 6, 'palma + 4 dedos + pulgar (contornos)');
});
