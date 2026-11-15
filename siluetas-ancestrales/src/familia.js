// Árbol de ascendencia en un solo SVG: la persona abajo y, encima, sus padres, abuelos y bisabuelos, cada uno con la ropa
// y los rasgos de su pueblo y de su época. Los nacimientos son coherentes entre generaciones (el padre unos 30 años mayor
// que el hijo y la madre unos 26, con variación) y cada ancestro se retrata a los 35 años, en su propio año.

import { generarPersona, renderizar } from './generador.js';
import { crearAzar, escapar } from './util.js';

export const GENERACIONES_MIN = 1, GENERACIONES_MAX = 4;
const EDAD_RETRATO = 35; // edad con la que se dibuja a cada ancestro
const NACE_PADRE = 30, NACE_MADRE = 26; // años de diferencia con el hijo, en promedio

const NOMBRES = {
  '': 'Persona', p: 'Padre', m: 'Madre',
  pp: 'Abuelo paterno', pm: 'Abuela paterna', mp: 'Abuelo materno', mm: 'Abuela materna',
};
const GENERACION = ['Persona', 'Padres', 'Abuelos', 'Bisabuelos'];

/** Nombre del lugar de la persona en el árbol. `ruta` es la cadena de p (padre) y m (madre) desde la persona. */
export function nombreDeRuta(ruta) {
  if (NOMBRES[ruta] !== undefined) return NOMBRES[ruta];
  const sexo = ruta.endsWith('p') ? 'Bisabuelo' : 'Bisabuela';
  const lado = ruta.slice(0, -1).split('').map((c) => (c === 'p' ? 'paterno' : 'materno')).join(' ');
  return `${sexo} ${lado}`.replace(/^(Bisab\w+) (\w+) (\w+)$/, (_, b, x, y) => `${b} ${x}, ${y}`);
}

const CLAVES_NODO = new Set(['etnia', 'padre', 'madre', 'nombre']);

/** Comprueba la forma del linaje; lanza un error que dice en qué nodo está el problema. */
function validarLinaje(nodo, ruta = '', profundidad = 0, maxima = GENERACIONES_MAX) {
  if (nodo === undefined || nodo === null) return;
  const donde = ruta === '' ? 'linaje' : `linaje (${nombreDeRuta(ruta)})`;
  if (typeof nodo !== 'object' || Array.isArray(nodo)) throw new Error(`${donde}: debe ser un objeto { etnia, padre, madre }.`);
  for (const k of Object.keys(nodo)) if (!CLAVES_NODO.has(k)) throw new Error(`${donde}: clave desconocida «${k}». Claves posibles: ${[...CLAVES_NODO].join(', ')}.`);
  if (nodo.nombre !== undefined && typeof nodo.nombre !== 'string') throw new Error(`${donde}: «nombre» debe ser un texto.`);
  if (profundidad >= maxima - 1 && (nodo.padre || nodo.madre)) throw new Error(`${donde}: tiene padres, pero el árbol solo llega a ${maxima} generaciones (usa generaciones: ${profundidad + 2}).`);
  validarLinaje(nodo.padre, `${ruta}p`, profundidad + 1, maxima);
  validarLinaje(nodo.madre, `${ruta}m`, profundidad + 1, maxima);
}

/**
 * Construye el árbol de ascendencia.
 * opc: { semilla, generaciones (1 a 4, por defecto 3), anio (año en que se retrata a la persona), edad (de la persona, por
 * defecto 30), sexo ('f'|'m'), etnia (de todos, salvo lo que diga el linaje), linaje ({ etnia, nombre, padre: {...}, madre: {...} }
 * con la etnia de cada ancestro; el que falta hereda la de su hijo) y las demás opciones de `generarPersona` para la persona
 * (piel…), `saludo` para todos y `detalle` ('minimo'|'medio'|'alto') }.
 * Devuelve { svg, personas: [{ ruta, rol, generacion, sexo, edad, anio, nacimiento, etnia, nombre, persona }], titulo }.
 */
export function generarFamilia(opc = {}) {
  const { semilla = 1, generaciones = 3, anio = 1950, edad = 30, sexo, etnia, linaje, saludo, detalle = 'medio', ...resto } = opc;
  if (!['minimo', 'medio', 'alto'].includes(detalle)) throw new Error(`detalle no válido: ${JSON.stringify(detalle)}. Valores posibles: minimo, medio, alto.`);
  if (!Number.isInteger(generaciones) || generaciones < GENERACIONES_MIN || generaciones > GENERACIONES_MAX) {
    throw new Error(`generaciones no válido: ${JSON.stringify(generaciones)}. Debe ser un entero de ${GENERACIONES_MIN} a ${GENERACIONES_MAX}.`);
  }
  if (typeof anio !== 'number' || !Number.isFinite(anio)) throw new Error(`anio no válido: ${JSON.stringify(anio)}.`);
  validarLinaje(linaje, '', 0, generaciones);
  const azar = crearAzar(`familia:${semilla}`);
  const personas = [];

  const recorrer = (ruta, nodo, etniaHeredada, nacHijo, generacion) => {
    const g = generacion;
    const sexoDe = g === 0 ? (sexo ?? azar.hijo('sexo').elegir(['f', 'm'])) : (ruta.endsWith('p') ? 'm' : 'f');
    const e = nodo?.etnia ?? etniaHeredada;
    let nacimiento, anioRetrato, edadRetrato;
    if (g === 0) { edadRetrato = edad; anioRetrato = anio; nacimiento = anio - edad; }
    else {
      nacimiento = Math.round(nacHijo - (sexoDe === 'm' ? NACE_PADRE : NACE_MADRE) + azar.hijo(`edad:${ruta}`).entre(-4, 4));
      edadRetrato = EDAD_RETRATO; anioRetrato = nacimiento + EDAD_RETRATO;
    }
    const opciones = { ...(g === 0 ? resto : {}), semilla: `${semilla}:${ruta || 'raiz'}`, sexo: sexoDe, edad: edadRetrato, anio: anioRetrato };
    if (saludo !== undefined) opciones.saludo = saludo; // el mismo saludo para todos
    if (e !== undefined) opciones.etnia = e;
    let persona;
    try { persona = generarPersona(opciones); } catch (err) { throw new Error(`${nombreDeRuta(ruta)}: ${err.message}`); }
    personas.push({ ruta, rol: nodo?.nombre ?? nombreDeRuta(ruta), generacion: g, sexo: sexoDe, edad: edadRetrato, anio: anioRetrato, nacimiento, etnia: persona.etnia, nombre: nodo?.nombre ?? null, persona });
    if (g < generaciones - 1) {
      recorrer(`${ruta}p`, nodo?.padre, e, nacimiento, g + 1);
      recorrer(`${ruta}m`, nodo?.madre, e, nacimiento, g + 1);
    }
  };
  recorrer('', linaje, etnia, anio - edad, 0);

  return { svg: dibujarArbol(personas, generaciones, detalle), personas, titulo: `Árbol de ascendencia de ${generaciones} generaciones` };
}

// ---------- Dibujo ----------

const ANCHO_HUECO = 150; // ancho de cada lugar de la última generación
const ANCHO_FIGURA = 130, ALTO_FIGURA = Math.round((ANCHO_FIGURA * 1000) / 600);
const ALTO_PIE = 40, ALTO_ESPACIO = 34, ALTO_TITULO = 44;
const TINTA = '#2b2620', LINEA = '#8a7f6e';

/** El <svg> de una persona como figura anidada en (x, y). */
function figuraAnidada(persona, x, y, detalle) {
  const svg = renderizar(persona, { detalle });
  return svg.replace(/^<svg [^>]*>/, `<svg x="${x}" y="${y}" width="${ANCHO_FIGURA}" height="${ALTO_FIGURA}" viewBox="0 0 600 1000" role="img">`);
}

function dibujarArbol(personas, generaciones, detalle) {
  const W = 2 ** (generaciones - 1) * ANCHO_HUECO;
  const altoFila = ALTO_FIGURA + ALTO_PIE + ALTO_ESPACIO;
  const H = ALTO_TITULO + generaciones * altoFila;
  const yFila = (g) => ALTO_TITULO + (generaciones - 1 - g) * altoFila;
  const cx = (p) => { const i = parseInt(p.ruta.split('').map((c) => (c === 'p' ? 0 : 1)).join('') || '0', 2); return ((i + 0.5) * W) / 2 ** p.generacion; };
  const lugar = Object.fromEntries(personas.map((p) => [p.ruta, { x: cx(p), y: yFila(p.generacion) }]));
  let lineas = '', figuras = '', pies = '';

  for (const p of personas) {
    const { x, y } = lugar[p.ruta];
    figuras += figuraAnidada(p.persona, x - ANCHO_FIGURA / 2, y, detalle);
    const yTexto = y + ALTO_FIGURA + 14;
    const etnia = p.etnia ? p.etnia.map((e) => e.nombre).join(' y ') : '';
    const lema = `${etnia}${etnia ? ' · ' : ''}${p.anio < 0 ? `${-Math.round(p.anio)} a. C.` : Math.round(p.anio)}`;
    pies += `<text x="${x}" y="${yTexto}" text-anchor="middle" font-size="12" font-weight="600" fill="${TINTA}">${escapar(p.rol)}</text>`
      + `<text x="${x}" y="${yTexto + 14}" text-anchor="middle" font-size="10.5" fill="${LINEA}">${escapar(lema)}</text>`;
    // Conector con sus padres: una barra entre los dos y un tronco hasta la persona.
    const padre = lugar[`${p.ruta}p`], madre = lugar[`${p.ruta}m`];
    if (padre && madre) {
      const yBarra = y - ALTO_ESPACIO / 2;
      const yPies = padre.y + ALTO_FIGURA + ALTO_PIE - 8;
      lineas += `<path d="M${padre.x} ${yPies}V${yBarra}H${madre.x}V${yPies}M${x} ${yBarra}V${y - 2}" fill="none" stroke="${LINEA}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>`;
    }
  }
  const nombresGen = Array.from({ length: generaciones }, (_, g) => GENERACION[g]).reverse();
  const etiquetas = nombresGen.map((n, i) => `<text x="8" y="${ALTO_TITULO + i * altoFila + 14}" font-size="11" fill="${LINEA}" font-weight="600">${escapar(n)}</text>`).join('');
  const titulo = `Árbol de ascendencia de ${generaciones} generaciones`;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" role="img" font-family="system-ui, -apple-system, sans-serif"><title>${escapar(titulo)}</title>`
    + `<text x="${W / 2}" y="26" text-anchor="middle" font-size="16" font-weight="700" fill="${TINTA}">${escapar(titulo)}</text>`
    + `${lineas}${etiquetas}${figuras}${pies}</svg>`;
}
