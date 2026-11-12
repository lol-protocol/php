// Revisiones del catálogo que no se ven al dibujar: que un traje no use un tinte de otra época sin avisar
// y que cada pueblo tenga ropa de mujer y de hombre. Complementan a catalogo.test.js y etnia.test.js.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ATUENDOS, ETNIAS, buscarAtuendos, generarPersona } from '../src/index.js';
import { tintesDelAnio } from '../src/generador.js';
import { TINTES_POR_ID } from '../src/datos/colores.js';

const aniosDe = ([d, h]) => {
  const s = new Set([d, Math.round((d + h) / 2), h]);
  for (let y = Math.ceil(d / 10) * 10; y <= h; y += 10) s.add(y);
  return [...s];
};

test('en cualquier año de su época, cada letra de la paleta de un atuendo da tintes que ya existían', () => {
  for (const a of ATUENDOS) {
    for (const [letra, ids] of Object.entries(a.paleta)) {
      for (const anio of aniosDe(a.epoca)) {
        const { ok } = tintesDelAnio(ids, anio);
        assert.ok(ok.length > 0, `${a.id} ${letra} ${anio}: sin tintes`);
        for (const id of ok) {
          const t = TINTES_POR_ID[id];
          assert.ok(anio >= t.desde && anio <= t.hasta, `${a.id} ${letra}: ${id} no existía en ${anio} (${t.desde} a ${t.hasta})`);
        }
      }
    }
  }
});

test('si el tinte pedido no existía ese año se usa el más parecido de su familia y se avisa', () => {
  const t = tintesDelAnio(['rojo-granza'], 1950);
  assert.ok(t.sustituye && t.sustituye.id === 'rojo-granza');
  for (const id of t.ok) assert.equal(TINTES_POR_ID[id].familia, 'rojo');
  const p = generarPersona({ atuendo: 'huaso', anio: 1950, semilla: 3 });
  assert.ok(p.avisos.some((a) => /rojo de granza/i.test(a) && /1950/.test(a)), p.avisos.join(' | '));
  // Cuando el tinte sí existía no hay aviso ni sustitución.
  assert.equal(tintesDelAnio(['rojo-granza'], 1800).sustituye, null);
});

test('cada pueblo tiene al menos un atuendo de mujer y uno de hombre en su región', () => {
  for (const e of ETNIAS) {
    for (const sexo of ['f', 'm']) {
      assert.ok(buscarAtuendos({ regiones: e.regiones, prefijos: e.prefijos, sexo }).length > 0, `${e.id}: sin atuendo ${sexo}`);
    }
  }
});

test('un atuendo no repite su nombre dentro del mismo sexo', () => {
  const vistos = new Set();
  for (const a of ATUENDOS) {
    const k = `${a.nombre}|${a.sexo}`;
    assert.ok(!vistos.has(k), `nombre repetido: ${a.nombre} (${a.sexo})`);
    vistos.add(k);
  }
});

test('la confianza «alta» exige una fuente, y toda fuente es un texto con contenido', () => {
  for (const x of [...ATUENDOS, ...ETNIAS]) {
    assert.equal(typeof x.fuente, 'string', `${x.id}: fuente`);
    if (x.fuente) assert.ok(x.fuente.trim().length >= 15, `${x.id}: fuente demasiado corta`);
    if (x.confianza === 'alta') assert.ok(x.fuente, `${x.id}: confianza alta sin fuente`);
  }
});

test('persona.fuentes reúne las del atuendo y de las etnias, sin repetir', () => {
  const p = generarPersona({ semilla: 1, atuendo: 'huaso' });
  assert.ok(Array.isArray(p.fuentes));
  assert.equal(new Set(p.fuentes).size, p.fuentes.length);
});
