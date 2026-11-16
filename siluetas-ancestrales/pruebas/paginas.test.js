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

// ---------- Accesibilidad ----------

const luminancia = (hex) => {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const contraste = (a, b) => { const [x, y] = [luminancia(a), luminancia(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
/** Variables CSS `--nombre:#rrggbb` de un bloque. */
const variables = (bloque) => Object.fromEntries([...bloque.matchAll(/--([\w-]+):\s*(#[0-9a-f]{6})/gi)].map((m) => [m[1], m[2]]));

for (const pagina of ['galeria.html', 'prendas.html']) {
  const html = readFileSync(join(RAIZ, pagina), 'utf8');
  const claro = variables(html.match(/:root \{([^}]*)\}/)[1]);
  const oscuro = variables(html.match(/prefers-color-scheme: dark\) \{ :root \{([^}]*)\}/)[1]);

  test(`${pagina}: el contraste de los textos es de al menos 4,5 a 1 en el tema claro y en el oscuro`, () => {
    for (const [tema, v] of [['claro', claro], ['oscuro', oscuro]]) {
      for (const fg of ['tinta', 'suave', 'acento']) for (const bg of ['fondo', 'panel']) {
        assert.ok(contraste(v[fg], v[bg]) >= 4.5, `${tema}: ${fg} sobre ${bg} = ${contraste(v[fg], v[bg]).toFixed(2)}`);
      }
      assert.ok(contraste(v['sobre-acento'], v.acento) >= 4.5, `${tema}: texto de los botones (${v['sobre-acento']}) sobre ${v.acento} = ${contraste(v['sobre-acento'], v.acento).toFixed(2)}`);
    }
  });

  test(`${pagina}: cada campo tiene su etiqueta, cada botón su nombre y hay un solo <main>`, () => {
    const sinComentarios = html.replace(/<script[\s\S]*?<\/script>/g, '').replace(/<style[\s\S]*?<\/style>/g, '');
    const paraEtiquetas = new Set([...sinComentarios.matchAll(/<label[^>]*\sfor="([^"]+)"/g)].map((m) => m[1]));
    for (const [, tag, atributos] of sinComentarios.matchAll(/<(input|select|textarea)\b([^>]*)>/g)) {
      const id = atributos.match(/\sid="([^"]+)"/)?.[1];
      const dentroDeLabel = new RegExp(`<label[^>]*>[^<]*<${tag}\\b[^>]*${id ? ` id="${id}"` : ''}`).test(sinComentarios);
      assert.ok(/aria-label(ledby)?=/.test(atributos) || (id && paraEtiquetas.has(id)) || dentroDeLabel, `${pagina}: <${tag}${id ? ` id="${id}"` : ''}> sin etiqueta`);
    }
    for (const [, atributos, interior] of sinComentarios.matchAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/g)) {
      assert.ok(/aria-label=/.test(atributos) || interior.replace(/<[^>]*>/g, '').trim().length > 0, `${pagina}: botón sin nombre (${atributos})`);
    }
    assert.equal((sinComentarios.match(/<main\b/g) ?? []).length, 1);
    assert.equal((sinComentarios.match(/<h1\b/g) ?? []).length, 1);
  });
}

test('galeria.html: enlace para saltar a la figura, la figura no es una región «en vivo» y la descripción sí', () => {
  const html = readFileSync(join(RAIZ, 'galeria.html'), 'utf8');
  assert.match(html, /<a class="saltar" href="#escena">/);
  assert.match(html, /id="escena"[^>]*tabindex="-1"/);
  assert.doesNotMatch(html, /id="escena"[^>]*aria-live/);
  assert.match(html, /id="info"[^>]*role="status"/);
  assert.equal((html.match(/<section\b/g) ?? []).length, (html.match(/<\/section>/g) ?? []).length);
  for (const s of html.matchAll(/<section aria-label="([^"]+)"/g)) assert.ok(s[1].length > 3);
});
