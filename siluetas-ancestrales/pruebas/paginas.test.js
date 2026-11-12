// Las páginas HTML (galeria.html, prendas.html) se abren desde el disco con dist/: aquí se comprueba, sin navegador,
// que cargan el paquete que existe y que cada elemento que su script busca por id está en la página.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join, dirname } from 'node:path';

const RAIZ = join(dirname(fileURLToPath(import.meta.url)), '..');

for (const pagina of ['galeria.html', 'prendas.html']) {
  test(`${pagina}: carga dist/ y cada id que busca su script existe`, () => {
    const html = readFileSync(join(RAIZ, pagina), 'utf8');
    for (const [, src] of html.matchAll(/<script src="([^"]+)"/g)) assert.ok(existsSync(join(RAIZ, src)), `${pagina}: no existe ${src}`);
    assert.match(html, /<html lang="es">/);
    assert.match(html, /<meta name="viewport"/);
    const ids = new Set([...html.matchAll(/ id="([^"]+)"/g)].map((m) => m[1]));
    for (const m of html.matchAll(/\$\('([\w-]+)'\)/g)) assert.ok(ids.has(m[1]), `${pagina}: el script busca #${m[1]}, que no está`);
    for (const [, usado] of html.matchAll(/for \(const id of \[([^\]]+)\]\)/g)) for (const id of usado.match(/'([\w-]+)'/g).map((x) => x.slice(1, -1))) assert.ok(ids.has(id), `${pagina}: falta #${id}`);
  });
}
