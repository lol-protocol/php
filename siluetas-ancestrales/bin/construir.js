#!/usr/bin/env node
// Genera lo que se commitea derivado del código:
//   dist/siluetas-ancestrales.js   un solo archivo (sin módulos) para abrir galeria.html desde el disco
//   ejemplos/*.svg                 las siluetas de ejemplo listadas en ejemplos/lista.json
// `--comprobar` no escribe nada: falla si lo commiteado no coincide con lo que saldría hoy.

import { readFileSync, writeFileSync, readdirSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { generarSVG } from '../src/index.js';

const RAIZ = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const SRC = join(RAIZ, 'src');
const comprobar = process.argv.includes('--comprobar');

// ---------- Empaquetado ----------

function listarModulos(dir) {
  const out = [];
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    if (e.isDirectory()) out.push(...listarModulos(join(dir, e.name)));
    else if (e.name.endsWith('.js')) out.push(join(dir, e.name));
  }
  return out;
}
const clave = (ruta) => relative(SRC, ruta).replace(/\\/g, '/').replace(/\.js$/, '');

function transformar(ruta) {
  let src = readFileSync(ruta, 'utf8');
  const deps = [];
  const resolver = (rel) => { const k = clave(resolve(dirname(ruta), rel)); deps.push(k); return k; };
  const exportados = [];
  const extra = [];
  src = src.replace(/^import\s*\{([^}]*)\}\s*from\s*'([^']+)';?$/gm, (_, nombres, rel) => `const {${nombres.replace(/\s+as\s+/g, ': ')}} = __m['${resolver(rel)}'];`);
  src = src.replace(/^export\s*\*\s*from\s*'([^']+)';?$/gm, (_, rel) => { extra.push(`Object.assign(__e, __m['${resolver(rel)}']);`); return ''; });
  src = src.replace(/^export\s*\{([^}]*)\}\s*from\s*'([^']+)';?$/gm, (_, nombres, rel) => {
    const k = resolver(rel);
    for (const n of nombres.split(',').map((s) => s.trim()).filter(Boolean)) extra.push(`__e.${n} = __m['${k}'].${n};`);
    return '';
  });
  src = src.replace(/^export\s*\{([^}]*)\};?$/gm, (_, nombres) => {
    for (const n of nombres.split(',').map((s) => s.trim()).filter(Boolean)) exportados.push(n);
    return '';
  });
  src = src.replace(/^export\s+(const|let|function|class)\s+(\w+)/gm, (_, tipo, nombre) => { exportados.push(nombre); return `${tipo} ${nombre}`; });
  if (/^\s*(import|export)\s/m.test(src)) throw new Error(`${ruta}: forma de import/export que el empaquetador no entiende`);
  const cuerpo = `${src}\n${exportados.length ? `Object.assign(__e, { ${[...new Set(exportados)].join(', ')} });\n` : ''}${extra.join('\n')}`;
  return { clave: clave(ruta), deps: [...new Set(deps)], cuerpo };
}

export function empaquetar() {
  const mods = Object.fromEntries(listarModulos(SRC).sort().map(transformar).map((m) => [m.clave, m]));
  const orden = [], visto = new Set();
  const visitar = (k) => {
    if (visto.has(k)) return;
    visto.add(k);
    if (!mods[k]) throw new Error(`Módulo que no existe: ${k}`);
    for (const d of mods[k].deps) visitar(d);
    orden.push(k);
  };
  visitar('index');
  const partes = orden.map((k) => `__m['${k}'] = (() => {\nconst __e = {};\n${mods[k].cuerpo.trim()}\nreturn __e;\n})();`);
  return `/* Generado por bin/construir.js a partir de src/: no lo edites a mano. */\n(function (global) {\n'use strict';\nconst __m = {};\n${partes.join('\n\n')}\n\nglobal.SiluetasAncestrales = __m['index'];\n})(typeof globalThis !== 'undefined' ? globalThis : this);\n`;
}

// ---------- Ejemplos ----------

function ejemplos() {
  const lista = JSON.parse(readFileSync(join(RAIZ, 'ejemplos', 'lista.json'), 'utf8'));
  return lista.map((e) => [`${e.archivo}.svg`, `${generarSVG(e.opciones)}\n`]);
}

const salidas = [[join('dist', 'siluetas-ancestrales.js'), empaquetar()], ...ejemplos().map(([n, s]) => [join('ejemplos', n), s])];
let mal = 0;
for (const [rel, contenido] of salidas) {
  const ruta = join(RAIZ, rel);
  const actual = existsSync(ruta) ? readFileSync(ruta, 'utf8') : null;
  if (comprobar) {
    if (actual !== contenido) { console.error(`Desactualizado: ${rel} (corre node bin/construir.js)`); mal++; }
  } else if (actual !== contenido) {
    mkdirSync(dirname(ruta), { recursive: true });
    writeFileSync(ruta, contenido);
    console.log(`Escrito ${rel}`);
  }
}
if (comprobar) {
  // Sin SVG sueltos en ejemplos/ que la lista no mencione.
  const esperados = new Set(ejemplos().map(([n]) => n));
  for (const f of readdirSync(join(RAIZ, 'ejemplos'))) if (f.endsWith('.svg') && !esperados.has(f)) { console.error(`Sobra ejemplos/${f}: no está en lista.json`); mal++; }
  if (mal) process.exit(1);
  console.log(`Al día: ${salidas.length} archivo(s).`);
}
