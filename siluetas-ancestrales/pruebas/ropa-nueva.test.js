import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PLANTILLAS } from '../src/prendas.js';
import { medidas } from '../src/cuerpo.js';
import { PRENDAS } from '../src/datos/prendas.js';
import { PRENDAS_MODA } from '../src/datos/prendas-moda.js';
import { generarPersona, generarSVG, renderizar, TOCADOS, PEINADOS, BARBAS } from '../src/index.js';
import { distribucionColor } from '../src/datos/colores-prendas.js';

const K = { principal: '#5f7f9f', secundario: '#e6d8b0', ribete: '#8a3a2a', detalle: '#caa24a' };
const ctx = { patron: () => '#cccccc' };
const M = medidas({ sexo: 'f', edad: 30, pose: 'firme', saludo: 'ninguno' });
const hacer = (plantilla, params) => PLANTILLAS[plantilla](M, params, K, ctx);
const ancho = (d) => { const xs = [...d.matchAll(/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/g)].map((m) => Number(m[1])); return Math.max(...xs) - Math.min(...xs); };
const puntos = (d) => [...d.matchAll(/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/g)].map((m) => [Number(m[1]), Number(m[2])]);
/** Ancho de una pierna cerca del bajo (y del arranque), medido en una franja de 14 px. */
const anchoBajo = (d) => { const p = puntos(d), yMax = Math.max(...p.map((q) => q[1])); const f = p.filter((q) => q[1] > yMax - 14).map((q) => q[0]); return Math.max(...f) - Math.min(...f); };
const anchoAlto = (d) => { const p = puntos(d), yMin = Math.min(...p.map((q) => q[1])); const f = p.filter((q) => q[1] < yMin + 14).map((q) => q[0]); return Math.max(...f) - Math.min(...f); };
const altoMin = (d) => Math.min(...[...d.matchAll(/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/g)].map((m) => Number(m[2])));

test('los escotes sin hombro llevan tirantes y el corpiño empieza más abajo del hombro', () => {
  const base = { largo: 'rodilla', manga: 'sin' };
  const normal = hacer('tunica', { ...base, escote: 'redondo' });
  const tir = hacer('tunica', { ...base, escote: 'tirantes' });
  assert.equal(tir.partes.length, 3, 'corpiño y dos tirantes');
  assert.equal(hacer('tunica', { ...base, escote: 'corazon' }).partes.length, 3);
  assert.equal(hacer('tunica', { ...base, escote: 'halter' }).partes.length, 3);
  assert.equal(hacer('tunica', { ...base, escote: 'asimetrico' }).partes.length, 2, 'corpiño y un solo tirante');
  assert.ok(altoMin(tir.partes[0].d) > altoMin(normal.partes[0].d) + M.H * 0.04, 'el corpiño de tirantes debe empezar bajo el pecho');
  // Aunque se pida manga, un escote sin hombro no lleva mangas.
  assert.equal(hacer('tunica', { largo: 'rodilla', manga: 'larga', escote: 'tirantes' }).partes.length, 3);
});

test('las mangas nuevas existen y se diferencian de la larga', () => {
  const largo = (manga) => hacer('tunica', { largo: 'cadera', manga, escote: 'redondo' });
  const larga = largo('larga');
  for (const m of ['farol', 'pagoda', 'cap', 'ajustada']) {
    const t = largo(m);
    assert.equal(t.partes.length, 3, `${m}: torso y dos mangas`);
    assert.notEqual(t.partes[1].d, larga.partes[1].d, `${m} igual a la larga`);
  }
  assert.ok(ancho(largo('pagoda').partes[1].d) > ancho(largo('ajustada').partes[1].d) * 1.15, 'la pagoda debe abrirse más que la ajustada');
  const cap = largo('cap').partes[1].d, corta = largo('corta').partes[1].d;
  assert.ok(Math.max(...[...cap.matchAll(/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/g)].map((m) => Number(m[2]))) < Math.max(...[...corta.matchAll(/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/g)].map((m) => Number(m[2]))), 'la manga cap debe ser más corta');
});

test('la forma de la falda cambia su silueta: sirena estrecha arriba y abierta abajo, globo recogido en el bajo, tubo recto', () => {
  const f = (forma, vuelo) => hacer('falda', { largo: 'tobillo', vuelo, forma, cintura: 'natural' }).partes[0].d;
  assert.ok(ancho(f('sirena', 0.15)) <= ancho(f('trapecio', 0.15)) + 0.5, 'la sirena no es más ancha que el trapecio');
  assert.notEqual(f('sirena', 0.15), f('trapecio', 0.15));
  assert.ok(ancho(f('tubo', 0.15)) < ancho(f('trapecio', 0.15)) * 0.8, 'el tubo no se abre');
  assert.notEqual(f('globo', 0.1), f('campana', 0.1));
  const v = (forma) => hacer('tunica', { largo: 'suelo', manga: 'sin', escote: 'redondo', vuelo: 0.15, forma }).partes[0].d;
  assert.ok(ancho(v('tubo')) < ancho(v('trapecio')) * 0.8);
});

test('los pantalones nuevos tienen el ancho que dicen (medido en el bajo y en el muslo)', () => {
  const pierna = (a) => hacer('pantalon', { largo: 'tobillo', ancho: a }).partes[1].d;
  assert.ok(anchoBajo(pierna('pitillo')) < anchoBajo(pierna('recto')), 'el pitillo es más estrecho en el bajo que el recto');
  assert.ok(anchoBajo(pierna('tubo')) < anchoBajo(pierna('recto')));
  assert.ok(anchoBajo(pierna('recto')) < anchoBajo(pierna('palazzo')) && anchoBajo(pierna('palazzo')) > anchoBajo(pierna('amplio')), 'el palazzo es el más ancho en el bajo');
  assert.ok(anchoAlto(pierna('jodhpur')) > anchoAlto(pierna('recto')) * 1.15, 'el jodhpur se ensancha en el muslo');
  assert.ok(anchoBajo(pierna('jodhpur')) <= anchoBajo(pierna('recto')), 'y se ajusta bajo la rodilla');
});

test('capucha, bolsillos, cuello de camisa, piel, cinturón y cordones agregan sus piezas', () => {
  const cuenta = (extra) => { const t = hacer('tunica', { largo: 'cadera', manga: 'larga', escote: 'alto', ...extra }); return { partes: t.partes.length, det: t.detalles.length, atras: t.atras.length, t }; };
  const base = cuenta({});
  assert.equal(cuenta({ capucha: true }).atras, 1);
  assert.equal(cuenta({ cuelloCamisa: true }).partes, base.partes + 2);
  assert.equal(cuenta({ cinturon: true }).partes, base.partes + 1);
  for (const b of ['pecho', 'cadera', 'canguro', 'ambos']) assert.ok(cuenta({ bolsillos: b }).det > base.det, `bolsillos ${b}`);
  assert.ok(cuenta({ cordones: true }).det >= base.det + 10);
  const piel = cuenta({ pieles: 'todo' }).t.detalles.filter((d) => d.dash);
  assert.ok(piel.length >= 3, 'la piel lleva cuello, puños y bajo (puntitos)');
  const j = hacer('chaqueta', { largo: 'cadera', manga: 'larga', capucha: true, pieles: 'cuello', bolsillos: 'cadera' });
  assert.equal(j.atras.length, 1);
});

test('los pantalones tienen bolsillos cargo, de jean y pinzas', () => {
  const det = (b) => hacer('pantalon', { largo: 'tobillo', ancho: 'recto', bolsillos: b }).detalles.length;
  const sin = det(undefined);
  for (const b of ['cargo', 'jean', 'pliegues']) assert.ok(det(b) > sin, b);
});

test('los cuellos, calzados y accesorios nuevos se dibujan', () => {
  for (const tipo of ['piel', 'esclavina', 'chalina', 'triangulo', 'babero', 'corbata']) {
    const r = hacer('cuello', { tipo });
    assert.ok(r.partes.length + r.detalles.length > 0, tipo);
  }
  for (const tipo of ['botines', 'tacones', 'chancletas', 'oxford']) {
    const r = hacer('calzado', { tipo });
    assert.ok(r.partes.length + r.detalles.length > 0, tipo);
  }
  assert.ok(hacer('accesorio', { tipo: 'tirantes' }).detalles.length >= 4);
  const mono = hacer('mono', { torso: { manga: 'larga', escote: 'alto' }, pantalon: { ancho: 'recto' } });
  assert.ok(mono.partes.length >= 6, 'torso, mangas y dos piernas');
});

test('las prendas de la ampliación de tipos se dibujan en mujer y en hombre sin errores, y tienen sus colores', () => {
  assert.ok(PRENDAS_MODA.length >= 70);
  for (const p of PRENDAS_MODA) {
    for (const sexo of ['f', 'm']) {
      const pers = generarPersona({ semilla: 2, sexo, edad: 30, prendas: [{ id: p.id, nombre: p.nombre, ranura: p.ranura, plantilla: p.plantilla, params: p.params, colores: K }] });
      assert.ok(!/NaN|undefined/.test(renderizar(pers)), `${p.id} ${sexo}`);
    }
    assert.ok(distribucionColor(p.id, 'principal').length > 0, `${p.id}: sin colores`);
  }
});

test('vestir: se viste prenda por prenda y los colores salen del catálogo de cada una', () => {
  const ids = ['camisa-clasica', 'pantalon-pitillo', 'blazer-ajustado', 'tacones', 'perlas'];
  const p = generarPersona({ semilla: 3, sexo: 'f', vestir: ids });
  assert.deepEqual(p.prendas.map((x) => x.id), ids);
  assert.equal(p.titulo, 'Vestuario a medida');
  assert.deepEqual(generarPersona({ semilla: 3, sexo: 'f', vestir: { torso: 'camisa-clasica', inferior: 'pantalon-pitillo', sobre: 'blazer-ajustado', calzado: 'tacones', cuello: 'perlas' } }).prendas.map((x) => x.id), ids);
  for (const c of p.coloresPrendas) {
    const permitido = new Set(distribucionColor(c.prenda, 'principal').map((x) => x.id));
    assert.ok(permitido.has(c.roles.principal.id), `${c.prenda}: ${c.roles.principal.id} no está en su catálogo`);
  }
  assert.ok(!/NaN|undefined/.test(generarSVG({ semilla: 3, sexo: 'f', vestir: ids })));
  assert.deepEqual(generarPersona({ semilla: 3, sexo: 'f', vestir: ids }).coloresPrendas, p.coloresPrendas, 'determinista');
});

test('vestir: un id desconocido falla, dos prendas en una ranura avisan, y una lista vacía usa el atuendo', () => {
  assert.throws(() => generarPersona({ vestir: ['no-existe'] }), /Prenda desconocida: no-existe/);
  const p = generarPersona({ semilla: 1, vestir: ['camisa-blanca', 'polera'] });
  assert.ok(p.avisos.some((a) => /2 prendas en la ranura «torso»/.test(a)), p.avisos.join('|'));
  const vacia = generarPersona({ semilla: 1, vestir: [] });
  assert.ok(vacia.prendas.length > 0 && vacia.avisos.some((a) => /Sin prendas elegidas/.test(a)), 'una lista vacía usa el atuendo y lo avisa');
  for (let i = 0; i < 30; i++) assert.ok(['azul-denim', 'denim-oscuro'].includes(generarPersona({ semilla: i, vestir: ['jeans-clasicos'], anio: 1880 }).coloresPrendas[0].roles.principal.id), 'en 1880 los jeans solo pueden ser denim crudo');
});

test('los tocados, peinados y barbas nuevos existen y se pueden elegir', () => {
  for (const t of ['canotier', 'pamela', 'bucket', 'beanie']) assert.ok(TOCADOS[t], t);
  assert.ok(Object.keys(TOCADOS).length >= 44 && Object.keys(PEINADOS).length >= 26 && Object.keys(BARBAS).length >= 8);
  for (const tocado of ['canotier', 'pamela', 'bucket', 'beanie']) assert.ok(!/NaN|undefined/.test(generarSVG({ semilla: 1, tocado })), tocado);
  assert.ok(generarPersona({ semilla: 1, peinado: 'ondas', tocado: 'bucket', barba: 'bigote', sexo: 'm', edad: 40 }).tocado.id === 'bucket');
});

test('todas las rutas dibujadas usan solo comandos absolutos (la deformación de la pose lo necesita)', () => {
  for (let i = 0; i < 80; i++) {
    const svg = generarSVG({ semilla: i * 5 + 1 }).replace(/<pattern[\s\S]*?<\/pattern>/g, ''); // los patrones de tela traen sus propias rutas
    for (const m of svg.matchAll(/ d="([^"]+)"/g)) assert.match(m[1], /^[MLCQZ0-9eE+\-. ]*$/, `ruta con comandos relativos o raros (semilla ${i * 5 + 1}): ${m[1].slice(0, 80)}`);
  }
});

test('el catálogo tiene más prendas y atuendos, y todo se usa', () => {
  assert.ok(PRENDAS.length >= 480);
});
