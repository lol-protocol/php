// Pruebas de citas-pos en un navegador real: el comportamiento de «Adivina la categoría» y una auditoría de
// accesibilidad con axe-core. Las de PHP (../ejecutar.php) no abren un navegador y no pueden probar esto.
//
//   cd citas-pos/pruebas/navegador && npm ci && npx playwright install chromium && npm test
//
// En la nube de las sesiones, en lugar de `npx playwright install`, se usa el Chromium que ya hay: la variable
// CHROMIUM, o /opt/pw-browsers/chromium si existe.
//
// Lo que NO prueba: un lector de pantalla real, ni Firefox ni Safari, ni el correo en clientes de correo.

import { chromium } from 'playwright';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const aqui = path.dirname(fileURLToPath(import.meta.url));
const salida = path.resolve(aqui, '../../salida');
const prototipo = path.resolve(aqui, '../../prototipo/index.html');
const axeRuta = createRequire(import.meta.url).resolve('axe-core/axe.min.js');

const URL_JUEGO = pathToFileURL(path.join(salida, 'demo-juego.html')).href;
const URL_PROTOTIPO = pathToFileURL(prototipo).href;

// axe no puede decidir si un texto de un solo carácter («y», «a») es una letra o un símbolo, y marca como «por
// revisar» que el texto visible esté en el nombre accesible. El nombre accesible de cada palabra revelada empieza
// por su texto visible («y, conjunción coordinante»), así que cumple 2.5.3: es un falso positivo conocido.
const REVISAR_PERMITIDO = new Set(['label-content-name-mismatch']);

const ETIQUETAS_AXE = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

function igual(esperado, obtenido, mensaje = '') {
  if (esperado !== obtenido) {
    throw new Error(`${mensaje} esperado ${JSON.stringify(esperado)}, obtenido ${JSON.stringify(obtenido)}`.trim());
  }
}

function verdadero(condicion, mensaje) {
  if (!condicion) {
    throw new Error(mensaje);
  }
}

const ejecutable = process.env.CHROMIUM || (existsSync('/opt/pw-browsers/chromium') ? '/opt/pw-browsers/chromium' : undefined);
const navegador = await chromium.launch(ejecutable ? { executablePath: ejecutable } : {});
const errores = [];

/** Abre una página con su propio contexto; recoge los errores de consola y de la página. */
async function abrir(url, opciones = {}) {
  const contexto = await navegador.newContext({ viewport: { width: 900, height: 900 }, ...opciones });
  const pagina = await contexto.newPage();
  pagina.on('pageerror', (e) => errores.push(String(e)));
  pagina.on('console', (m) => { if (m.type() === 'error') errores.push(m.text()); });
  if (url) {
    await pagina.goto(url);
  }
  return { pagina, cerrar: () => contexto.close() };
}

async function con(url, opciones, cuerpo) {
  const { pagina, cerrar } = await abrir(url, opciones);
  try {
    return await cuerpo(pagina);
  } finally {
    await cerrar();
  }
}

const primera = (p) => p.locator('figure.citas-pos-juego').first();

/** El HTML de la demo sin su <script>: es lo que ve quien no tiene JavaScript. */
function juegoSinScript() {
  return readFileSync(path.join(salida, 'demo-juego.html'), 'utf8').replace(/<script>[\s\S]*?<\/script>/, '');
}

/** Una página con la hoja del sitio y todos los fragmentos de sitio y de correo. */
function paginaDeFragmentos() {
  const css = readFileSync(path.join(salida, 'citas-pos.css'), 'utf8');
  const carpeta = path.join(salida, 'ejemplos');
  const de = (sufijo) => readdirSync(carpeta).filter((f) => f.endsWith(sufijo)).sort().map((f) => readFileSync(path.join(carpeta, f), 'utf8')).join('\n');
  return `<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light dark"><title>Fragmentos de sitio y de correo</title>`
    + `<style>body{margin:0;padding:20px;background:#d9d9de;color:#1d1d22}@media(prefers-color-scheme:dark){body{background:#0c0c10;color:#ececf1}}${css}</style></head>`
    + `<body><main><h1>Fragmentos</h1><h2>Sitio</h2>${de('.sitio.html')}<h2>Correo</h2>${de('.correo.html')}</main></body></html>`;
}

async function auditar(pagina) {
  await pagina.addScriptTag({ path: axeRuta });
  const r = await pagina.evaluate((etiquetas) => axe.run(document, { runOnly: { type: 'tag', values: etiquetas }, resultTypes: ['violations', 'incomplete'] }), ETIQUETAS_AXE);
  const detalle = (v) => `${v.id} (${v.nodes.length}): ${v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(' | ')}`;
  verdadero(r.violations.length === 0, `axe encontró violaciones: ${r.violations.map(detalle).join('; ')}`);
  const sinPermiso = r.incomplete.filter((v) => !REVISAR_PERMITIDO.has(v.id));
  verdadero(sinPermiso.length === 0, `axe dejó cosas por revisar que no están permitidas: ${sinPermiso.map(detalle).join('; ')}`);
}

const pruebas = [];
const prueba = (nombre, cuerpo) => pruebas.push([nombre, cuerpo]);

// --- axe, en claro y en oscuro ---------------------------------------------------------------------------------

for (const esquema of ['light', 'dark']) {
  const modo = esquema === 'light' ? 'claro' : 'oscuro';
  const opciones = { colorScheme: esquema };

  prueba(`axe: el juego al empezar (${modo})`, () => con(URL_JUEGO, opciones, auditar));
  prueba(`axe: el juego con una palabra revelada en cada frase (${modo})`, () => con(URL_JUEGO, opciones, async (p) => {
    await p.evaluate(() => document.querySelectorAll('figure.citas-pos-juego').forEach((f) => f.querySelector('.jw').click()));
    await auditar(p);
  }));
  prueba(`axe: el juego con todas las palabras reveladas (${modo})`, () => con(URL_JUEGO, opciones, async (p) => {
    await p.evaluate(() => document.querySelectorAll('.jbar [data-accion=todas]').forEach((b) => b.click()));
    await auditar(p);
  }));
  prueba(`axe: el juego sin JavaScript (${modo})`, () => con(null, opciones, async (p) => {
    await p.setContent(juegoSinScript());
    await auditar(p);
  }));
  prueba(`axe: el prototipo de la paleta (${modo})`, () => con(URL_PROTOTIPO, opciones, auditar));
  prueba(`axe: el prototipo con la categoría junto a cada palabra y solo la marca (${modo})`, () => con(URL_PROTOTIPO, opciones, async (p) => {
    await p.selectOption('#etiquetas', 'si');
    await p.selectOption('#marcas', 'marca');
    await auditar(p);
  }));
  prueba(`axe: los fragmentos de sitio y de correo (${modo})`, () => con(null, opciones, async (p) => {
    await p.setContent(paginaDeFragmentos());
    await auditar(p);
  }));
}

// --- el comportamiento del juego -------------------------------------------------------------------------------

prueba('juego: al empezar nada está revelado, la leyenda no se anuncia y se ven la ayuda y los controles', () => con(URL_JUEGO, {}, async (p) => {
  const f = primera(p);
  const palabras = await f.locator('.jw').count();
  verdadero(palabras >= 6, 'la primera frase debía tener al menos 6 palabras etiquetadas');
  igual(palabras, await f.locator('[role=button].jw[aria-pressed="false"]').count(), 'todas deben ser botones sin pulsar:');
  igual(0, await f.locator('.jw[aria-label]').count(), 'ninguna debe tener nombre accesible con la categoría todavía:');
  igual(true, await f.locator('.jleyenda').evaluate((e) => e.hidden), 'una leyenda vacía debe ocultarse entera:');
  igual(true, await f.locator('.jayuda').isVisible(), 'la ayuda:');
  igual(true, await f.locator('.jbar').isVisible(), 'los controles:');
  igual(`Descubiertas 0 de ${palabras}.`, (await f.locator('.jestado').textContent()).trim());
}));

prueba('juego: pulsar una palabra revela su color, su categoría, su nombre accesible y la leyenda', () => con(URL_JUEGO, {}, async (p) => {
  const f = primera(p);
  const palabra = f.locator('.jw').first();
  const texto = (await palabra.textContent()).trim();
  const nombre = await palabra.getAttribute('data-nombre');
  const gris = await palabra.evaluate((e) => getComputedStyle(e).color);
  await palabra.click();
  igual('true', await palabra.getAttribute('aria-pressed'));
  igual(`${texto}, ${nombre}`, await palabra.getAttribute('aria-label'), 'nombre accesible:');
  verdadero((await palabra.evaluate((e) => getComputedStyle(e).color)) !== gris, 'al revelarla debe cambiar de color');
  igual(false, await f.locator('.jleyenda').evaluate((e) => e.hidden), 'la leyenda debe aparecer:');
  igual(1, await f.locator('.jleyenda li:not([hidden])').count());
  verdadero((await f.locator('.jestado').textContent()).startsWith(`${texto}: ${nombre}.`), 'el aviso debe decir qué se descubrió');
}));

prueba('juego: dos palabras de la misma categoría comparten una entrada de leyenda, que sigue hasta ocultar la última', () => con(URL_JUEGO, {}, async (p) => {
  const f = primera(p);
  const indices = await f.locator('.jw').evaluateAll((ws) => {
    const vistos = {};
    for (let i = 0; i < ws.length; i++) {
      const c = ws[i].dataset.c;
      if (vistos[c] !== undefined) { return [vistos[c], i]; }
      vistos[c] = i;
    }
    return null;
  });
  verdadero(indices !== null, 'la primera frase debía tener dos palabras de la misma categoría');
  const [a, b] = indices.map((i) => f.locator('.jw').nth(i));
  await a.click();
  await b.click();
  igual(1, await f.locator('.jleyenda li:not([hidden])').count(), 'una sola entrada:');
  await a.click();
  igual(1, await f.locator('.jleyenda li:not([hidden])').count(), 'sigue mientras quede una revelada:');
  await b.click();
  igual(0, await f.locator('.jleyenda li:not([hidden])').count(), 'desaparece con la última:');
}));

prueba('juego: Intro revela y Espacio oculta, desde el teclado', () => con(URL_JUEGO, {}, async (p) => {
  const palabra = primera(p).locator('.jw').first();
  await palabra.focus();
  await p.keyboard.press('Enter');
  igual('true', await palabra.getAttribute('aria-pressed'), 'tras Intro:');
  await p.keyboard.press('Space');
  igual('false', await palabra.getAttribute('aria-pressed'), 'tras Espacio:');
}));

prueba('juego: «Descubrir todas» y «Volver a ocultar»', () => con(URL_JUEGO, {}, async (p) => {
  const f = primera(p);
  const total = await f.locator('.jw').count();
  await f.getByRole('button', { name: 'Descubrir todas' }).click();
  igual(total, await f.locator('.jw[aria-pressed="true"]').count());
  igual('¡Descubiertas todas las palabras!', (await f.locator('.jestado').textContent()).trim());
  await f.getByRole('button', { name: 'Volver a ocultar' }).click();
  igual(total, await f.locator('.jw[aria-pressed="false"]').count());
  igual(0, await f.locator('.jw[aria-label]').count(), 'al ocultar se quita la categoría del nombre accesible:');
  verdadero((await f.locator('.jestado').textContent()).startsWith('Todas ocultas.'), 'el aviso al ocultar');
}));

prueba('juego: la frase entera está disponible como texto para quien usa un lector de pantalla', () => con(URL_JUEGO, {}, async (p) => {
  const f = primera(p);
  const [lectura, frase] = await Promise.all([f.locator('.jsr').textContent(), f.locator('blockquote').textContent()]);
  igual(frase, lectura);
  igual(1, await f.locator('.jsr').count());
}));

prueba('juego sin JavaScript: la leyenda sale completa, la ayuda y los controles esperan, y nada finge ser un botón', () => con(URL_JUEGO, { javaScriptEnabled: false }, async (p) => {
  const f = primera(p);
  verdadero((await f.locator('.jleyenda li:not([hidden])').count()) >= 3, 'la leyenda debe verse completa');
  igual(false, await f.locator('.jayuda').isVisible(), 'la ayuda:');
  igual(false, await f.locator('.jbar').isVisible(), 'los controles:');
  igual(0, await f.locator('[role=button]').count(), 'ningún elemento debe fingir ser un botón:');
  igual(0, await f.locator('.jsr').count(), 'la frase aparte solo la crea el script:');
  verdadero((await f.locator('.jw').first().evaluate((e) => getComputedStyle(e).textDecorationLine)) !== 'none', 'las palabras salen con su marca');
}));

// --- cómo se ve ----------------------------------------------------------------------------------------------------

prueba('juego: a 320 px de ancho (un zoom del 400 %) no hay desplazamiento horizontal', () => con(URL_JUEGO, { viewport: { width: 320, height: 700 } }, async (p) => {
  await p.evaluate(() => document.querySelectorAll('.jbar [data-accion=todas]').forEach((b) => b.click()));
  igual(false, await p.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth));
}));

prueba('juego: con el espaciado de texto que fija WCAG 1.4.12 no hay desbordamiento ni recortes', () => con(URL_JUEGO, {}, async (p) => {
  await p.addStyleTag({ content: '*{line-height:1.5 !important;letter-spacing:0.12em !important;word-spacing:0.16em !important} p{margin-bottom:2em !important}' });
  await p.evaluate(() => document.querySelectorAll('.jbar [data-accion=todas]').forEach((b) => b.click()));
  const r = await p.evaluate(() => ({
    desborde: document.documentElement.scrollWidth > document.documentElement.clientWidth,
    // El texto solo para lectores de pantalla (.jsr) está recortado a propósito.
    recortes: [...document.querySelectorAll('figure.citas-pos-juego *')].filter((e) => !e.classList.contains('jsr') && e.scrollWidth > e.clientWidth + 1 && getComputedStyle(e).overflow !== 'visible').length,
  }));
  igual(false, r.desborde, 'desborde horizontal:');
  igual(0, r.recortes, 'elementos recortados:');
}));

prueba('juego: en modo de alto contraste las palabras sin descubrir siguen marcadas (con un borde, que se conserva)', () => con(URL_JUEGO, { forcedColors: 'active' }, async (p) => {
  const estilo = await primera(p).locator('.jw[aria-pressed="false"]').first().evaluate((e) => { const s = getComputedStyle(e); return [s.borderBottomStyle, s.borderBottomWidth]; });
  igual('solid', estilo[0], 'estilo del borde:');
  igual('2px', estilo[1], 'ancho del borde:');
}));

prueba('juego: el foco del teclado se ve (contorno de 2 px)', () => con(URL_JUEGO, {}, async (p) => {
  await p.keyboard.press('Tab');
  const contorno = await p.evaluate(() => { const s = getComputedStyle(document.activeElement); return [s.outlineStyle, s.outlineWidth]; });
  igual('solid', contorno[0], 'estilo del contorno:');
  igual('2px', contorno[1], 'ancho del contorno:');
}));

prueba('juego: las palabras de una frase son una sola parada de tabulador', () => con(URL_JUEGO, {}, async (p) => {
  const r = await primera(p).evaluate((fig) => {
    const t = [...fig.querySelectorAll('.jw')].map((e) => e.getAttribute('tabindex'));
    return { n: t.length, en0: t.filter((x) => x === '0').length, enMenos1: t.filter((x) => x === '-1').length };
  });
  verdadero(r.n > 3, 'la frase de prueba debería tener varias palabras');
  igual(1, r.en0, 'palabras con tabindex 0:');
  igual(r.n - 1, r.enMenos1, 'palabras con tabindex -1:');
  await p.keyboard.press('Tab');
  verdadero(await p.evaluate(() => document.activeElement.classList.contains('jw')), 'el primer Tab no llega a una palabra');
  await p.keyboard.press('Tab');
  verdadero(!(await p.evaluate(() => document.activeElement.classList.contains('jw'))), 'el segundo Tab sigue dentro de las palabras de la primera frase');
}));

prueba('juego: las flechas, Inicio y Fin mueven el foco entre las palabras', () => con(URL_JUEGO, {}, async (p) => {
  const indice = () => p.evaluate(() => { const f = document.activeElement.closest('figure'); return [...f.querySelectorAll('.jw')].indexOf(document.activeElement); });
  await p.keyboard.press('Tab');
  igual(0, await indice(), 'primera palabra:');
  await p.keyboard.press('ArrowRight');
  igual(1, await indice(), 'tras →:');
  await p.keyboard.press('ArrowDown');
  igual(2, await indice(), 'tras ↓:');
  await p.keyboard.press('ArrowLeft');
  igual(1, await indice(), 'tras ←:');
  await p.keyboard.press('End');
  const ultima = await indice();
  verdadero(ultima > 2, 'Fin debería ir a la última palabra');
  await p.keyboard.press('ArrowRight');
  igual(ultima, await indice(), 'más allá de la última se queda:');
  await p.keyboard.press('Home');
  igual(0, await indice(), 'tras Inicio:');
  // La palabra activa es la que sigue en el orden de tabulación al volver.
  await p.keyboard.press('ArrowRight');
  await p.keyboard.press('Space');
  igual('true', await p.evaluate(() => document.activeElement.getAttribute('aria-pressed')), 'Espacio descubre la palabra con el foco:');
}));

// --- ejecutar --------------------------------------------------------------------------------------------------------

let fallidas = 0;
for (const [nombre, cuerpo] of pruebas) {
  try {
    await cuerpo();
    console.log(`ok     ${nombre}`);
  } catch (error) {
    fallidas++;
    console.log(`FALLO  ${nombre}\n       ${String(error.message).split('\n')[0]}`);
  }
}
if (errores.length > 0) {
  fallidas++;
  console.log(`FALLO  ninguna página dio errores de consola\n       ${errores.slice(0, 3).join(' | ')}`);
} else {
  console.log('ok     ninguna página dio errores de consola');
}
await navegador.close();
console.log(`${pruebas.length + 1 - fallidas} de ${pruebas.length + 1} pruebas pasan.`);
process.exit(fallidas === 0 ? 0 : 1);
