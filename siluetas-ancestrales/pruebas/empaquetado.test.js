import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { generarSVG, generarPersona } from '../src/index.js';

const leer = (ruta) => readFileSync(fileURLToPath(new URL(ruta, import.meta.url)), 'utf8');

test('dist/siluetas-ancestrales.js (sin módulos) da el mismo SVG que src/', () => {
  const ctx = {};
  vm.createContext(ctx);
  vm.runInContext(leer('../dist/siluetas-ancestrales.js'), ctx);
  const S = ctx.SiluetasAncestrales;
  for (const f of ['generarSVG', 'generarPersona', 'generarRopaNueva', 'generarPaleta', 'renderizar', 'buscarAtuendos', 'ATUENDOS', 'PRENDAS', 'TINTES', 'REGIONES', 'PIELES']) assert.ok(S[f], f);
  for (const opc of [{ semilla: 1 }, { semilla: 99, region: 'andes' }, { semilla: 5, ropaNueva: true, estilo: 'festivo' }, { semilla: 8, atuendo: 'huaso', detalle: 'alto', rasgos: true }]) {
    assert.equal(S.generarSVG(opc), generarSVG(opc));
  }
  assert.deepEqual(JSON.parse(JSON.stringify(S.generarPersona({ semilla: 4 }))), JSON.parse(JSON.stringify(generarPersona({ semilla: 4 }))));
});

test('el paquete no trae import ni export', () => {
  assert.ok(!/^\s*(import|export)\s/m.test(leer('../dist/siluetas-ancestrales.js')));
});
