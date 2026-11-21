// El README de la carpeta no debe quedarse atrás del código: las cifras del catálogo y los archivos que describe.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join, dirname } from 'node:path';
import { listarCatalogo } from '../src/index.js';

const RAIZ = join(dirname(fileURLToPath(import.meta.url)), '..');
const readme = readFileSync(join(RAIZ, 'README.md'), 'utf8');
const fila = (nombre) => readme.match(new RegExp(`^\\| ${nombre} \\| ([^|]*)\\|`, 'm'))?.[1];

test('las cifras de la tabla del README coinciden con el catálogo', () => {
  const c = listarCatalogo();
  assert.match(fila('Atuendos completos'), new RegExp(`^${c.atuendos},`), `Atuendos completos: el catálogo trae ${c.atuendos}`);
  assert.match(fila('Prendas'), new RegExp(`^${c.prendas} `), `Prendas: el catálogo trae ${c.prendas}`);
  assert.match(fila('Tintes con nombre'), new RegExp(`^${c.tintes},.* ${c.perfilesDeColor} perfiles`), `Tintes: ${c.tintes} y ${c.perfilesDeColor} perfiles`);
  assert.match(fila('Etnias y culturas'), new RegExp(`^${c.etnias},`), `Etnias: el catálogo trae ${c.etnias}`);
  assert.match(fila('Pieles, cabellos'), new RegExp(`^${c.pieles} tonos de piel y ${c.cabellos} de cabello`));
  assert.match(fila('Peinados, barbas, tocados'), new RegExp(`^${c.peinados}, ${c.barbas} y ${c.tocados}`));
});

test('el README nombra todos los archivos de src/ y de bin/', () => {
  for (const f of readdirSync(join(RAIZ, 'src')).filter((x) => x.endsWith('.js') && x !== 'index.js')) assert.ok(readme.includes(`src/${f}`), `el README no menciona src/${f}`);
  for (const f of readdirSync(join(RAIZ, 'bin')).filter((x) => x.endsWith('.js'))) assert.ok(readme.includes(`bin/${f}`), `el README no menciona bin/${f}`);
});

test('el README no dice que solo hay vista frontal ni cifras viejas de pruebas', () => {
  assert.doesNotMatch(readme, /Solo vista frontal/);
  assert.doesNotMatch(readme, /\b(83|93|105) pruebas\b/);
});
