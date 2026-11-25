// Poses nuevas: mano sobre el pecho, manos juntas, brazos cruzados y las poses por edad.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarPersona, renderizar, PRENDAS } from '../src/index.js';
import { medidas, poseAleatoria, SALUDOS_ESTILO } from '../src/cuerpo.js';
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
  assert.throws(() => generarPersona({ pose: { manos: 'rezando' } }), /pose\.manos.*juntas, plegaria, cruzados, jarras/);
  assert.throws(() => generarPersona({ pose: { brazoLibre: 'cabeza' } }), /pose\.brazoLibre.*pecho/);
  assert.throws(() => generarPersona({ pose: 'bailando' }), /Pose no válid/);
});

test('toda prenda, con cada pose de brazos al frente, se dibuja sin NaN y dentro del lienzo', () => {
  const poses = [{ manos: 'juntas' }, { manos: 'plegaria' }, { manos: 'cruzados' }, { manos: 'jarras' }, { brazoLibre: 'pecho' }, { saludoEstilo: 'alto' }, { saludoEstilo: 'bajo' }];
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

test('manos en jarras y en plegaria ocupan los dos brazos cuando no hay saludo', () => {
  for (const [manos, tipo] of [['jarras', 'cintura'], ['plegaria', 'juntas']]) {
    const p = generarPersona({ semilla: 3, saludo: 'ninguno', pose: { manos } });
    const M = medidas({ ...p.cuerpo, saludo: null });
    assert.deepEqual([M.brazos[1].tipo, M.brazos[-1].tipo], [tipo, tipo], manos);
  }
  // Con saludo, el brazo que saluda sigue levantado.
  const conSaludo = generarPersona({ semilla: 3, saludo: 'derecha', pose: { manos: 'jarras' } });
  assert.equal(medidas(conSaludo.cuerpo).brazos[-1].tipo, 'saludo');
  // La plegaria junta las manos más arriba que las manos juntas.
  const y = (manos) => medidas({ ...generarPersona({ semilla: 3, saludo: 'ninguno', pose: { manos } }).cuerpo, saludo: null }).brazos[1].linea.at(-1)[1];
  assert.ok(y('plegaria') < y('juntas'), 'la plegaria va a la altura del pecho');
});

test('los estilos de saludo levantan la mano a distinta altura y un valor explícito manda sobre el estilo', () => {
  const alturaMano = (pose) => { const p = generarPersona({ semilla: 3, saludo: 'derecha', pose }); return medidas(p.cuerpo).brazos[-1].linea.at(-1)[1]; };
  const alto = alturaMano({ saludoEstilo: 'alto' }), medio = alturaMano({ saludoEstilo: 'medio' }), bajo = alturaMano({ saludoEstilo: 'bajo' });
  assert.ok(alto < medio && medio < bajo, `alto ${alto} < medio ${medio} < bajo ${bajo}`);
  for (const [nombre, v] of Object.entries(SALUDOS_ESTILO)) assert.equal(generarPersona({ semilla: 3, pose: { saludoEstilo: nombre } }).cuerpo.pose.saludo.codo, v.codo, nombre);
  assert.equal(generarPersona({ semilla: 3, pose: { saludoEstilo: 'alto', saludo: { codo: 40 } } }).cuerpo.pose.saludo.codo, 40);
  assert.throws(() => generarPersona({ pose: { saludoEstilo: 'frenetico' } }), /pose\.saludoEstilo.*alto, medio, bajo, inclinado/);
  assert.throws(() => generarPersona({ pose: { manos: 'rezando' } }), /pose\.manos.*juntas, plegaria, cruzados, jarras/);
});
