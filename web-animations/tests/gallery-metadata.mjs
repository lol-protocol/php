// Cada animación se nombra en tres lugares que tienen que hablar de lo mismo: la tarjeta de la galería (src/animations/data.js,
// la que abre la página), la propia página (su <title>, su encabezado y su descripción) y la tabla del README. Una tarjeta que
// describe otra animación que la que abre es un error que ninguna otra prueba ve: las páginas cargan bien y los botones andan.
//
// No hace falta navegador: el encabezado de cada página es HTML estático. `npm test` lo corre antes de abrir las páginas.
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..');
const dirAnimaciones = join(raiz, 'src', 'animations');
const problemas = [];
const falla = (msg) => problemas.push(msg);

// ---- data.js: es un script con `const animationsData = {...}`, no un módulo; se evalúa aparte.
const datos = vm.runInNewContext(`${readFileSync(join(dirAnimaciones, 'data.js'), 'utf8')}\nanimationsData;`);
const grupos = Object.entries(datos);
const entradas = grupos.flatMap(([grupo, g]) => g.animations.map(a => ({ ...a, grupo })));

// ---- las palabras que importan de un texto (sin tildes, mayúsculas ni palabras de relleno)
const RELLENO = new Set(['los', 'las', 'del', 'con', 'una', 'uno', 'por', 'que', 'sus', 'como', 'para', 'desde', 'entre', 'sobre', 'cada', 'sin', 'mas']);
const palabras = (texto) => new Set(
    texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().match(/[a-z0-9]+/g)
        ?.filter(p => p.length >= 3 && !RELLENO.has(p)) ?? []
);
const enComun = (a, b) => [...palabras(a)].filter(p => palabras(b).has(p));

// ---- las páginas
const paginas = readdirSync(dirAnimaciones).filter(f => /^animacion-[a-z]+\.html$/.test(f))
    .map(f => f.replace(/^animacion-|\.html$/g, ''));
const idsDeDatos = entradas.map(a => a.id);
const repetidos = idsDeDatos.filter((id, i) => idsDeDatos.indexOf(id) !== i);
if (repetidos.length) falla(`data.js: ids repetidos: ${repetidos.join(', ')}`);
for (const id of paginas.filter(id => !idsDeDatos.includes(id))) falla(`animacion-${id}.html no tiene tarjeta en data.js`);
for (const id of idsDeDatos.filter(id => !paginas.includes(id))) falla(`data.js tiene la tarjeta «${id}» pero no existe animacion-${id}.html`);

const MINIMO_DESCRIPCION = 2; // palabras en común entre la descripción de la tarjeta y la de la página
for (const a of entradas.filter(a => paginas.includes(a.id))) {
    const html = readFileSync(join(dirAnimaciones, `animacion-${a.id}.html`), 'utf8');
    const titulo = html.match(/<title>(.*?)<\/title>/s)?.[1].trim() ?? '';
    const panel = html.match(/<div class="info-panel">\s*<h2>(.*?)<\/h2>\s*<p>(.*?)<\/p>/s);
    if (!panel) { falla(`animacion-${a.id}.html: no tiene el panel de información (h2 + p)`); continue; }
    const [, encabezado, descripcion] = panel;
    const [, idEncabezado, nombre] = encabezado.match(/^([A-Z]+)\)\s*(.*)$/) ?? [];

    if (idEncabezado?.toLowerCase() !== a.id) falla(`animacion-${a.id}.html: el encabezado «${encabezado}» no empieza con ${a.id.toUpperCase()})`);
    if (enComun(a.title, nombre ?? encabezado).length === 0) {
        falla(`«${a.id}»: la tarjeta dice «${a.title}» pero la página es «${nombre}»`);
    }
    if (enComun(a.description, descripcion).length < MINIMO_DESCRIPCION) {
        falla(`«${a.id}»: la descripción de la tarjeta («${a.description}») no habla de lo que dice la página («${descripcion}»)`);
    }
    if (enComun(titulo, nombre ?? encabezado).length === 0) {
        falla(`«${a.id}»: el <title> de la página dice «${titulo}» y su encabezado «${nombre}»`);
    }
}

// ---- la tabla del README: grupo, id, título y categoría de cada animación
const readme = readFileSync(join(raiz, 'README.md'), 'utf8');
const tabla = readme.split(/^## Animaciones$/m)[1]?.split(/^## /m)[0] ?? '';
const filasDeTabla = tabla.split('\n').filter(l => l.startsWith('|')).slice(2); // sin el encabezado ni la línea |---|
const filas = filasDeTabla.map(l => l.match(/^\|[^|]*\|\s*([A-Z]{1,2})\s*\|\s*(.+?)\s*\|\s*(.+?)\s*\|$/))
    .filter(Boolean).map(m => ({ id: m[1].toLowerCase(), titulo: m[2], categoria: m[3] }));
if (filas.length !== filasDeTabla.length) falla(`README: ${filasDeTabla.length - filas.length} fila(s) de la tabla de animaciones no tienen el formato | grupo | ID | título | categoría |`);
if (filas.length !== entradas.length) falla(`README: la tabla de animaciones tiene ${filas.length} filas y data.js ${entradas.length} animaciones`);
for (const f of filas) {
    const a = entradas.find(e => e.id === f.id);
    if (!a) { falla(`README: la fila «${f.id.toUpperCase()}» no existe en data.js`); continue; }
    if (a.title !== f.titulo || a.category !== f.categoria) {
        falla(`README: «${f.id.toUpperCase()}» dice «${f.titulo}» (${f.categoria}) y data.js «${a.title}» (${a.category})`);
    }
}

// ---- la dificultad: el modal de detalles le busca color en DIFFICULTY_COLORS, y sin él la insignia queda sin fondo
const fuenteModal = readFileSync(join(dirAnimaciones, 'dashboard-interactive.js'), 'utf8');
const conColor = [...(fuenteModal.match(/DIFFICULTY_COLORS:\s*\{([^}]*)\}/)?.[1] ?? '').matchAll(/'([^']+)'\s*:/g)].map(m => m[1]);
if (conColor.length === 0) falla('dashboard-interactive.js: no se encontró DIFFICULTY_COLORS');
for (const a of entradas.filter(a => !conColor.includes(a.difficulty))) {
    falla(`«${a.id}»: su dificultad «${a.difficulty}» no tiene color en DIFFICULTY_COLORS (${conColor.join(', ')})`);
}

problemas.forEach(p => console.log(`✗ ${p}`));
console.log(`\n${entradas.length} tarjetas, ${paginas.length} páginas, ${filas.length} filas del README: ${problemas.length ? `${problemas.length} problemas` : 'todo coincide'}`);
process.exit(problemas.length ? 1 : 0);
