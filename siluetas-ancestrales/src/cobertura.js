// Cobertura del catálogo por país: cruza la prioridad genealógica de cada país (Listado-paises/priorizacion.csv) con las
// regiones y etnias que lo representan (datos/paises-regiones.json, un mapeo aproximado y editable) y cuenta qué atuendos
// hay y en qué décadas. Sirve para decidir qué pueblos agregar primero; no es un dato demográfico.

import { ATUENDOS } from './datos/atuendos.js';
import { ETNIAS } from './datos/etnias.js';

export const DESDE = 1700, HASTA = 1950; // las décadas que importan para la genealogía

/** Filas de un CSV con comillas ("a, b" y "" dentro de un campo). Devuelve objetos con los nombres de la primera fila. */
export function leerCsv(texto) {
  const filas = [];
  let fila = [], campo = '', comillas = false;
  for (let i = 0; i < texto.length; i++) {
    const c = texto[i];
    if (comillas) {
      if (c === '"') { if (texto[i + 1] === '"') { campo += '"'; i++; } else comillas = false; } else campo += c;
    } else if (c === '"') comillas = true;
    else if (c === ',') { fila.push(campo); campo = ''; }
    else if (c === '\n' || c === '\r') { if (c === '\r' && texto[i + 1] === '\n') i++; fila.push(campo); campo = ''; if (fila.some((x) => x !== '')) filas.push(fila); fila = []; }
    else campo += c;
  }
  if (campo !== '' || fila.length) { fila.push(campo); if (fila.some((x) => x !== '')) filas.push(fila); }
  const [cab, ...resto] = filas;
  return resto.map((f) => Object.fromEntries(cab.map((n, i) => [n, f[i] ?? ''])));
}

/** Países con su prioridad genealógica (mayor = más prioritario), a partir del texto de priorizacion.csv. */
export function leerPrioridades(textoCsv) {
  return leerCsv(textoCsv).map((r) => ({
    codigo: r.codigo, pais: r.pais, continente: r.region,
    prioridad: Number(r.prioridad_genealogia), excluido: r.excluido_genealogia === 'si', motivo: r.motivo_exclusion_genealogia,
  }));
}

const NOMBRE_ETNIA = Object.fromEntries(ETNIAS.map((e) => [e.id, e.nombre]));
const ETNIA_POR_ID = Object.fromEntries(ETNIAS.map((e) => [e.id, e]));

const decadasCon = (mujeres, hombres, desde, hasta) => {
  let cubiertas = 0, total = 0;
  for (let d = desde; d <= hasta; d += 10) {
    total++;
    const hay = (lista) => lista.some((a) => d >= a.epoca[0] && d <= a.epoca[1]);
    if (hay(mujeres) && hay(hombres)) cubiertas++;
  }
  return total ? cubiertas / total : 0;
};

/**
 * Cobertura de un país. `mapa` es `paises` de datos/paises-regiones.json. Distingue:
 *  - atuendos propios: los de las etnias del país que tienen trajes con su nombre (`prefijos`), es decir, hechos para ese pueblo;
 *  - atuendos genéricos: los de las regiones del país, que sirven a muchos pueblos (p. ej. «Europa rural» para Finlandia).
 * Devuelve { regiones, etnias: [{id, nombre, propia}], propios: {mujeres, hombres}, genericos: {mujeres, hombres}, decadas
 * (0 a 1: décadas con ropa propia de mujer y de hombre), estado }.
 * estado: 'verde' (ropa propia de mujer y de hombre en el 80 % de las décadas o más), 'amarillo' (solo genérica, o propia
 * incompleta) o 'rojo' (ni siquiera genérica de mujer y de hombre).
 */
export function coberturaDePais(codigo, mapa, { desde = DESDE, hasta = HASTA } = {}) {
  const vacio = { regiones: [], etnias: [], propios: { mujeres: 0, hombres: 0 }, genericos: { mujeres: 0, hombres: 0 }, decadas: 0, estado: 'rojo' };
  const m = mapa[codigo];
  if (!m) return { ...vacio, sinMapeo: true };
  const etnias = m.etnias.map((id) => ({ id, nombre: NOMBRE_ETNIA[id] ?? id, propia: !!ETNIA_POR_ID[id]?.prefijos }));
  const prefijos = m.etnias.flatMap((id) => ETNIA_POR_ID[id]?.prefijos ?? []);
  const propios = ATUENDOS.filter((a) => prefijos.some((p) => a.id.startsWith(p)));
  const genericos = ATUENDOS.filter((a) => m.regiones.includes(a.region));
  const cuenta = (l) => ({ mujeres: l.filter((a) => a.sexo === 'f').length, hombres: l.filter((a) => a.sexo === 'm').length });
  const P = cuenta(propios), G = cuenta(genericos);
  const decadas = decadasCon(propios.filter((a) => a.sexo === 'f'), propios.filter((a) => a.sexo === 'm'), desde, hasta);
  let estado;
  if (P.mujeres && P.hombres && decadas >= 0.8) estado = 'verde';
  else if (P.mujeres || P.hombres || (G.mujeres && G.hombres)) estado = 'amarillo';
  else estado = 'rojo';
  return { regiones: m.regiones, etnias, propios: P, genericos: G, decadas, estado };
}

const ICONO = { verde: '🟢', amarillo: '🟡', rojo: '🔴' };

/** Informe en Markdown: un resumen y una tabla ordenada por prioridad. Opciones: { todos, estado, desde, hasta }. */
export function informeDeCobertura(paises, mapa, { todos = false, estado, desde = DESDE, hasta = HASTA } = {}) {
  const filas = paises.filter((p) => todos || !p.excluido)
    .map((p) => ({ ...p, ...coberturaDePais(p.codigo, mapa, { desde, hasta }) }))
    .sort((a, b) => b.prioridad - a.prioridad || a.pais.localeCompare(b.pais, 'es'));
  const cuenta = (e) => filas.filter((f) => f.estado === e).length;
  const visibles = estado ? filas.filter((f) => f.estado === estado) : filas;
  const lineas = [
    `# Cobertura del catálogo por país (${desde} a ${hasta})`, '',
    `${filas.length} países${todos ? '' : ' no excluidos'}: ${ICONO.verde} ${cuenta('verde')} con ropa propia de mujer y de hombre en casi todas las décadas, ${ICONO.amarillo} ${cuenta('amarillo')} solo con ropa genérica de su región o propia incompleta, ${ICONO.rojo} ${cuenta('rojo')} sin ropa de mujer y de hombre.`,
    '«Propia» es la ropa hecha para el pueblo (su nombre está en el atuendo); «genérica», la de la región, que sirve a muchos pueblos.',
    'El mapeo país→región→etnia es aproximado (`datos/paises-regiones.json`): sirve para decidir qué agregar, no como dato demográfico.', '',
    '| País | Prioridad | Estado | Etnias (✔ = con ropa propia) | Ropa propia (mujer / hombre) | Ropa genérica (mujer / hombre) | Décadas con ropa propia |', '|---|---:|:---:|---|---:|---:|---:|',
  ];
  for (const f of visibles) {
    lineas.push(`| ${f.pais}${f.excluido ? ' (excluido)' : ''} | ${f.prioridad} | ${ICONO[f.estado]} | ${f.etnias.map((e) => `${e.nombre}${e.propia ? ' ✔' : ''}`).join(', ') || '—'} | ${f.propios.mujeres} / ${f.propios.hombres} | ${f.genericos.mujeres} / ${f.genericos.hombres} | ${Math.round(f.decadas * 100)} % |`);
  }
  return `${lineas.join('\n')}\n`;
}
