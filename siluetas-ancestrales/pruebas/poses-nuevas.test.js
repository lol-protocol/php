// Poses nuevas: mano sobre el pecho, manos juntas, brazos cruzados y las poses por edad.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarPersona, renderizar, PRENDAS } from '../src/index.js';
import { medidas, poseAleatoria } from '../src/cuerpo.js';
import { crearAzar } from '../src/util.js';
import { puntosDeRuta } from './ayuda.js';

const media = (xs) => xs.reduce((a, b) => a + b, 0) / xs.length;

test('manos juntas y brazos cruzados ocupan los dos brazos cuando no hay saludo', () => {
  for (const manos of ['juntas', 'cruzados']) {
    const p = generarPersona({ semilla: 3, saludo: 'ninguno', pose: { manos } });
    const M = medidas({ ...p.cuerpo, saludo: null });
    assert.deepEqual([M.brazos[1].tipo, M.brazos[-1].tipo], manos === 'juntas' ? ['juntas', 'juntas'] : ['cruzado', 'cruzado']);
    assert.match(renderizar(p), /data-brazos-frente/);
  }
});

test('con saludo, pose.manos se ignora y el brazo del saludo sigue levantado', () => {
  const p = generarPersona({ semilla: 3, saludo: 'derecha', pose: { manos: 'juntas' } });
  const svg = renderizar(p);
  assert.match(svg, /data-saludo="derecha"/);
  assert.doesNotMatch(svg, /data-brazos-frente/);
});

test('la mano sobre el pecho va en el brazo libre, y el otro puede saludar', () => {
  const p = generarPersona({ semilla: 3, saludo: 'derecha', pose: { brazoLibre: 'pecho' } });
  const M = medidas(p.cuerpo);
  assert.equal(M.brazos[1].tipo, 'pecho');
  assert.equal(M.brazos[-1].tipo, 'saludo');
  const svg = renderizar(p);
  assert.match(svg, /data-brazos-frente/);
  assert.match(svg, /data-saludo="derecha"/);
});

test('valores de pose no válidos lanzan error con la lista', () => {
  assert.throws(() => generarPersona({ pose: { manos: 'rezando' } }), /pose\.manos.*juntas, cruzados/);
  assert.throws(() => generarPersona({ pose: { brazoLibre: 'cabeza' } }), /pose\.brazoLibre.*pecho/);
  assert.throws(() => generarPersona({ pose: 'bailando' }), /Pose no válid/);
});

test('toda prenda, con cada pose de brazos al frente, se dibuja sin NaN y dentro del lienzo', () => {
  const poses = [{ manos: 'juntas' }, { manos: 'cruzados' }, { brazoLibre: 'pecho' }];
  for (const pr of PRENDAS) {
    for (const pose of poses) {
      const p = generarPersona({ semilla: 7, sexo: pr.id.length % 2 ? 'f' : 'm', edad: 35, vestir: [pr.id], saludo: 'ninguno', pose });
      const svg = renderizar(p, { detalle: 'minimo' });
      assert.ok(!/NaN|undefined|Infinity/.test(svg), `${pr.id} ${JSON.stringify(pose)}`);
      for (const m of svg.matchAll(/ d="([^"]+)"/g)) for (const [x, y] of puntosDeRuta(m[1])) assert.ok(x > -80 && x < 680 && y > -100 && y < 1080, `${pr.id}: (${x}, ${y})`);
    }
  }
});

test('poses por edad: el niño abre más los pies y casi nunca carga el peso en una pierna; el mayor, al revés', () => {
  const muestra = (perfil) => Array.from({ length: 300 }, (_, i) => poseAleatoria(crearAzar(`edad:${perfil}:${i}`), 1, perfil));
  const ninos = muestra('infantil'), mayores = muestra('mayor');
  assert.ok(media(ninos.map((p) => p.abre)) > media(mayores.map((p) => p.abre)) + 0.3);
  assert.ok(ninos.filter((p) => p.carga === 0).length > 300 * 0.6);
  assert.ok(mayores.filter((p) => p.carga === 0).length < 300 * 0.25);
  assert.ok(mayores.some((p) => p.brazoLibre === 'pecho'));
  assert.ok(ninos.every((p) => p.brazoLibre !== 'pecho'));
});

test('pose "segun-edad" elige la pose infantil, la de mayor o la casual de siempre; sin ella la pose no cambia', () => {
  const abre = (opc) => generarPersona({ semilla: 9, ...opc }).cuerpo.pose.abre;
  assert.equal(abre({ edad: 8, pose: 'segun-edad' }), abre({ edad: 8, pose: 'infantil' }));
  assert.equal(abre({ edad: 80, pose: 'segun-edad' }), abre({ edad: 80, pose: 'mayor' }));
  assert.equal(abre({ edad: 30, pose: 'segun-edad' }), abre({ edad: 30 }));
});
