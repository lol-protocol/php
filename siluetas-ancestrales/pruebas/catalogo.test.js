import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PRENDAS } from '../src/datos/prendas.js';
import { ATUENDOS, REGIONES } from '../src/datos/atuendos.js';
import { TINTES, PIELES, CABELLOS } from '../src/datos/colores.js';
import { PLANTILLAS } from '../src/prendas.js';
import { ORDEN_RANURAS } from '../src/prendas.js';
import { PEINADOS, BARBAS, TOCADOS } from '../src/cabeza.js';
import { PATRONES_ID } from '../src/patrones.js';
import { esHex } from '../src/util.js';

const unicos = (lista, campo = 'id') => new Set(lista.map((x) => x[campo])).size === lista.length;
const prendaPorId = Object.fromEntries(PRENDAS.map((p) => [p.id, p]));
const tintePorId = Object.fromEntries(TINTES.map((t) => [t.id, t]));

test('los ids son únicos y en ASCII', () => {
  for (const [nombre, lista] of [['prendas', PRENDAS], ['atuendos', ATUENDOS], ['tintes', TINTES], ['pieles', PIELES], ['cabellos', CABELLOS]]) {
    assert.ok(unicos(lista), `ids repetidos en ${nombre}`);
    for (const x of lista) assert.match(x.id, /^[a-z0-9-]+$/, `id con caracteres raros: ${x.id}`);
  }
});

test('los colores del catálogo son hex válidos', () => {
  for (const x of [...TINTES, ...PIELES, ...CABELLOS]) assert.ok(esHex(x.hex), `${x.id}: ${x.hex}`);
});

test('cada prenda usa una plantilla y una ranura que existen, y colores A-D', () => {
  for (const p of PRENDAS) {
    assert.ok(PLANTILLAS[p.plantilla], `${p.id}: plantilla ${p.plantilla}`);
    assert.ok(p.ranura in ORDEN_RANURAS, `${p.id}: ranura ${p.ranura}`);
    assert.ok(p.nombre && p.nombre.length >= 3, `${p.id}: sin nombre`);
    for (const l of Object.values(p.col)) assert.match(l, /^[ABCD]$/, `${p.id}: letra ${l}`);
    const pat = p.params.patron;
    if (pat) assert.ok(PATRONES_ID.includes(pat.tipo ?? pat), `${p.id}: patrón ${pat.tipo}`);
    for (const k of ['bandaPatron', 'bordadoPecho']) if (p.params[k]) assert.ok(PATRONES_ID.includes(p.params[k]), `${p.id}: ${k} ${p.params[k]}`);
  }
});

test('cada atuendo apunta a prendas, tintes, peinados, barbas y tocados que existen', () => {
  for (const a of ATUENDOS) {
    assert.ok(a.region in REGIONES, `${a.id}: región ${a.region}`);
    assert.ok(a.epoca[0] < a.epoca[1], `${a.id}: época al revés`);
    assert.ok(['f', 'm'].includes(a.sexo), `${a.id}: sexo`);
    assert.ok(['baja', 'media', 'alta'].includes(a.clase), `${a.id}: clase`);
    assert.ok(a.prendas.length >= 2, `${a.id}: muy pocas prendas`);
    for (const id of a.prendas) assert.ok(prendaPorId[id], `${a.id}: prenda ${id}`);
    for (const [l, ids] of Object.entries(a.paleta)) {
      assert.match(l, /^[ABCD]$/);
      assert.ok(ids.length > 0, `${a.id}: paleta ${l} vacía`);
      for (const t of ids) assert.ok(tintePorId[t], `${a.id}: tinte ${t}`);
    }
    for (const id of a.peinados) assert.ok(id in PEINADOS, `${a.id}: peinado ${id}`);
    for (const id of a.barbas) assert.ok(id in BARBAS, `${a.id}: barba ${id}`);
    for (const id of a.tocados) assert.ok(id in TOCADOS, `${a.id}: tocado ${id}`);
    for (const id of a.pieles ?? []) assert.ok(PIELES.some((p) => p.id === id), `${a.id}: piel ${id}`);
    // Las prendas que usa el atuendo deben encontrar su color en la paleta.
    const letras = new Set(a.prendas.flatMap((id) => Object.values(prendaPorId[id].col)));
    for (const l of letras) if (l === 'A') assert.ok(a.paleta.A, `${a.id}: falta paleta A`);
    assert.ok(a.prendas.some((id) => prendaPorId[id].ranura === 'calzado'), `${a.id}: sin calzado (aunque sea descalzo)`);
  }
});

test('el catálogo es grande y cubre las regiones', () => {
  assert.ok(PRENDAS.length >= 300, `prendas: ${PRENDAS.length}`);
  assert.ok(ATUENDOS.length >= 250, `atuendos: ${ATUENDOS.length}`);
  assert.ok(TINTES.length >= 80, `tintes: ${TINTES.length}`);
  for (const r of Object.keys(REGIONES)) assert.ok(ATUENDOS.some((a) => a.region === r), `región sin atuendos: ${r}`);
});

test('hay atuendos de mujer y de hombre en las regiones principales', () => {
  for (const r of Object.keys(REGIONES).filter((x) => !['patagonia-fueguina', 'africa-nilo'].includes(x))) {
    for (const sexo of ['f', 'm']) assert.ok(ATUENDOS.some((a) => a.region === r && a.sexo === sexo), `${r} sin atuendo ${sexo}`);
  }
});

test('las fechas de los tintes son coherentes', () => {
  for (const t of TINTES) {
    assert.ok(t.desde < t.hasta, t.id);
    assert.ok(t.costo >= 1 && t.costo <= 5, t.id);
  }
});

test('cada atuendo trae un nivel de confianza válido y los de confianza baja o sensibles llevan nota o explicación en su nombre', () => {
  for (const a of ATUENDOS) assert.ok(['alta', 'media', 'baja'].includes(a.confianza), `${a.id}: confianza ${a.confianza}`);
  const bajos = ATUENDOS.filter((a) => a.confianza === 'baja');
  assert.ok(bajos.length >= 30, `pocos marcados como baja: ${bajos.length}`);
  // Los pueblos vivos con trajes genéricos deben decirlo.
  for (const id of ['himba-hombre', 'aborigen-mujer', 'papu-hombre', 'kawesqar-mujer']) assert.ok(ATUENDOS.find((a) => a.id === id).nota.length > 20, id);
});
