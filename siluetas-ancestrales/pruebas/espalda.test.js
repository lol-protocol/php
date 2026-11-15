// Vista de espalda: la figura reflejada, sin rostro ni barba y sin las prendas ni los detalles que solo se ven por delante.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarPersona, generarSVG, renderizar, PRENDAS } from '../src/index.js';
import { prendaDeEspalda, prendasDeEspalda } from '../src/espalda.js';
import { puntosDeRuta } from './ayuda.js';

const caminos = (svg) => (svg.match(/<path /g) ?? []).length;
const ids = (svg) => new Set([...svg.matchAll(/ id="([^"]+)"/g)].map((m) => m[1]));

test('vista: solo «frente» o «espalda»; la persona de frente no lleva el campo `vista`', () => {
  assert.throws(() => generarPersona({ vista: 'lado' }), /Vista no válid.*frente, espalda/);
  assert.equal(generarPersona({ semilla: 3 }).vista, undefined);
  assert.equal(generarPersona({ semilla: 3, vista: 'frente' }).vista, undefined);
  assert.equal(generarPersona({ semilla: 3, vista: 'espalda' }).vista, 'espalda');
});

test('de espalda la figura va reflejada, con su descripción, y de frente no cambia', () => {
  const espalda = generarSVG({ semilla: 3, atuendo: 'huaso', vista: 'espalda' });
  const frente = generarSVG({ semilla: 3, atuendo: 'huaso' });
  assert.match(espalda, /<g data-vista="espalda" transform="translate\(600 0\) scale\(-1 1\)">/);
  assert.match(espalda, /Vista de espalda\./);
  assert.doesNotMatch(frente, /data-vista|scale\(-1 1\)|Vista de espalda/);
  assert.ok(!/NaN|undefined/.test(espalda));
  assert.equal(generarSVG({ semilla: 3, atuendo: 'huaso', vista: 'espalda' }), espalda, 'determinista');
});

test('la misma persona de frente y de espalda no comparte ids (pueden ir juntas en una página)', () => {
  const a = ids(generarSVG({ semilla: 3, atuendo: 'huaso' })), b = ids(generarSVG({ semilla: 3, atuendo: 'huaso', vista: 'espalda' }));
  assert.equal([...a].filter((x) => b.has(x)).length, 0);
});

test('por detrás no hay rasgos del rostro ni barba, y por delante sí', () => {
  const base = { semilla: 4, sexo: 'm', edad: 40, atuendo: 'huaso', barba: 'barba-larga' };
  assert.equal(caminos(generarSVG({ ...base, vista: 'espalda' })), caminos(generarSVG({ ...base, vista: 'espalda', barba: 'ninguna' })));
  assert.notEqual(caminos(generarSVG(base)), caminos(generarSVG({ ...base, barba: 'ninguna' })));
  assert.equal(caminos(generarSVG({ ...base, vista: 'espalda', rasgos: true })), caminos(generarSVG({ ...base, vista: 'espalda', rasgos: false })));
  assert.notEqual(caminos(generarSVG({ ...base, rasgos: true })), caminos(generarSVG({ ...base, rasgos: false })));
});

test('la iluminación sigue viniendo de la izquierda: los degradados se invierten junto con la figura', () => {
  const luz = (svg) => svg.match(/<linearGradient id="[^"]*-g-[^"]*" x1="(\d)" y1="0" x2="(\d)"/).slice(1);
  assert.deepEqual(luz(generarSVG({ semilla: 3 })), ['0', '1']);
  assert.deepEqual(luz(generarSVG({ semilla: 3, vista: 'espalda' })), ['1', '0']);
  assert.match(generarSVG({ semilla: 3, vista: 'espalda' }), /translate\(-3\.5 5\)/);
  assert.match(generarSVG({ semilla: 3 }), /translate\(3\.5 5\)/);
});

test('prendaDeEspalda quita lo que solo se ve por delante y deja lo demás', () => {
  const tunica = { id: 't', plantilla: 'tunica', params: { escote: 'v', botones: 6, bolsillos: 'pecho', cruzada: 'izq', manga: 'larga', largo: 'rodilla', pliegues: 4 } };
  const e = prendaDeEspalda(tunica);
  assert.deepEqual(e.params, { escote: 'redondo', manga: 'larga', largo: 'rodilla', pliegues: 4 });
  assert.equal(tunica.params.botones, 6, 'no modifica la original');
  assert.equal(prendaDeEspalda({ id: 'd', plantilla: 'delantal', params: {} }), null);
  assert.equal(prendaDeEspalda({ id: 'c', plantilla: 'cuello', params: { tipo: 'collar' } }), null);
  assert.ok(prendaDeEspalda({ id: 'b', plantilla: 'cuello', params: { tipo: 'bufanda' } }));
  assert.equal(prendaDeEspalda({ id: 'f', plantilla: 'accesorio', params: { tipo: 'cinta-frente' } }), null);
  assert.equal(prendaDeEspalda({ id: 'h', plantilla: 'faja', params: { hebilla: true, y: 'cintura' } }).params.hebilla, undefined);
  const mono = prendaDeEspalda({ id: 'm', plantilla: 'mono', params: { torso: { escote: 'cuadrado', botones: 3 }, pantalon: { bolsillos: 'cargo', largo: 'tobillo' } } });
  assert.deepEqual(mono.params, { torso: { escote: 'redondo' }, pantalon: { largo: 'tobillo' } });
  assert.equal(prendasDeEspalda([tunica, { id: 'd', plantilla: 'delantal', params: {} }]).length, 1);
});

test('toda prenda, de espalda, se dibuja sin NaN y dentro del lienzo, de mujer y de hombre', () => {
  for (const pr of PRENDAS) {
    for (const sexo of ['f', 'm']) {
      const p = generarPersona({ semilla: 5, sexo, edad: 33, vestir: [pr.id], vista: 'espalda' });
      const svg = renderizar(p, { detalle: 'minimo' });
      assert.ok(!/NaN|undefined|Infinity/.test(svg), `${pr.id} (${sexo})`);
      for (const m of svg.matchAll(/ d="([^"]+)"/g)) for (const [x, y] of puntosDeRuta(m[1])) assert.ok(x > -60 && x < 660 && y > -80 && y < 1060, `${pr.id}: (${x}, ${y})`);
    }
  }
});

test('un calvo se ve calvo por detrás y con el pelo largo el pelo cae sobre la espalda', () => {
  const calvo = generarSVG({ semilla: 2, sexo: 'm', edad: 60, atuendo: 'huaso', peinado: 'calvo', vista: 'espalda' });
  const largo = generarSVG({ semilla: 2, sexo: 'm', edad: 60, atuendo: 'huaso', peinado: 'largo-liso', vista: 'espalda' });
  assert.ok(caminos(largo) > caminos(calvo));
});
