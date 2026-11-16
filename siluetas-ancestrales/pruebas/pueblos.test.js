// Los pueblos agregados por listado-paises (sami, finés, romaní, bálticos, afrikáner, malayo del Cabo, francocanadiense
// y criollo de las islas): cada uno viste de verdad con su propio traje, con confianza baja y nota.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarPersona, renderizar, ETNIAS, ATUENDOS } from '../src/index.js';

const NUEVOS = ['sami', 'fines', 'romani', 'baltico', 'afrikaner', 'malayo-cabo', 'francocanadiense', 'criollo-isleno'];

test('cada pueblo nuevo existe, es de confianza baja y lleva nota', () => {
  for (const id of NUEVOS) {
    const e = ETNIAS.find((x) => x.id === id);
    assert.ok(e, id);
    assert.equal(e.confianza, 'baja', id);
    assert.ok(e.nota.length > 30, `${id}: nota`);
    assert.equal(e.fuente, '', `${id}: no se inventa una fuente`);
  }
});

test('para cada pueblo nuevo, hombres y mujeres de cualquier edad visten un traje propio y sin avisos de otro pueblo', () => {
  for (const id of NUEVOS) {
    const e = ETNIAS.find((x) => x.id === id);
    for (const sexo of ['f', 'm']) {
      for (const edad of [6, 30, 75]) {
        const p = generarPersona({ semilla: 4, etnia: id, sexo, edad });
        assert.ok(e.prefijos.some((pre) => p.atuendo.startsWith(pre)), `${id}/${sexo}: atuendo ${p.atuendo}`);
        assert.ok(!p.avisos.some((a) => /otro pueblo/.test(a)), `${id}/${sexo}: ${p.avisos.join('|')}`);
        assert.equal(p.confianza, 'baja');
        assert.ok(p.notas.length > 0);
        assert.ok(!/NaN|undefined/.test(renderizar(p)), `${id}/${sexo}/${edad}`);
      }
    }
  }
});

test('el prefijo de cada pueblo nuevo no se cuela en los atuendos de otro pueblo', () => {
  for (const id of NUEVOS) {
    const e = ETNIAS.find((x) => x.id === id);
    const mios = ATUENDOS.filter((a) => e.prefijos.some((p) => a.id.startsWith(p)));
    assert.equal(mios.length, 2, `${id}: debe tener un atuendo de mujer y uno de hombre`);
    for (const otro of ETNIAS.filter((x) => x.id !== id && x.prefijos)) {
      for (const a of mios) assert.ok(!otro.prefijos.some((p) => a.id.startsWith(p)), `${a.id} también cae en ${otro.id}`);
    }
  }
});
