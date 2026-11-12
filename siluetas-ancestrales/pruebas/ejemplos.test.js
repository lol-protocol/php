// Los ejemplos commiteados deben ser lo que el código genera hoy. `node bin/construir.js --comprobar` lo mira en la CI;
// esta prueba hace que `node --test` solo también lo note, y que la misma lista dé siempre lo mismo.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join, dirname } from 'node:path';
import { generarSVG } from '../src/index.js';

const DIR = join(dirname(fileURLToPath(import.meta.url)), '..', 'ejemplos');
const lista = JSON.parse(readFileSync(join(DIR, 'lista.json'), 'utf8'));

test('cada ejemplo de lista.json coincide con el SVG commiteado (corre node bin/construir.js si cambió el código)', () => {
  for (const e of lista) {
    const guardado = readFileSync(join(DIR, `${e.archivo}.svg`), 'utf8');
    assert.equal(`${generarSVG(e.opciones)}\n`, guardado, `${e.archivo}.svg está desactualizado`);
  }
});

test('no sobra ningún .svg en ejemplos/ que no esté en lista.json, y cada archivo de la lista es único', () => {
  const nombres = lista.map((e) => e.archivo);
  assert.equal(new Set(nombres).size, nombres.length, 'archivos repetidos en lista.json');
  const enDisco = readdirSync(DIR).filter((f) => f.endsWith('.svg')).map((f) => f.replace(/\.svg$/, '')).sort();
  assert.deepEqual(enDisco, [...nombres].sort());
});
