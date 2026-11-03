// Generador de personas: elige un atuendo del catálogo (o inventa uno), le pone colores y devuelve la
// descripción que `renderizar` dibuja. Con la misma semilla y las mismas opciones sale siempre lo mismo.

import { crearAzar, variar, mezclar, oscurecer, aclarar, luminancia, hexAHsl, hslAHex, limitar, esHex } from './util.js';
import { PIELES, CABELLOS, TINTES, TINTES_POR_ID } from './datos/colores.js';
import { PRENDAS_POR_ID, PRENDAS } from './datos/prendas.js';
import { ATUENDOS, ATUENDOS_POR_ID, REGIONES } from './datos/atuendos.js';
import { MANDIBULAS_ID, PEINADOS, BARBAS, TOCADOS } from './cabeza.js';
import { PATRONES_ID } from './patrones.js';
import { CORPULENCIAS, POSTURAS } from './cuerpo.js';
import { renderizar } from './svg.js';
import { ETNIAS, ETNIAS_POR_ID } from './datos/etnias.js';
import { medidas } from './cuerpo.js';

// ---------- Consulta del catálogo ----------

const contiene = (epoca, anio) => anio === undefined || anio === null || (anio >= epoca[0] && anio <= epoca[1]);

/** Atuendos que cumplen los filtros: { region, anio, sexo, clase }. */
export function buscarAtuendos({ region, regiones, prefijos, anio, sexo, clase } = {}) {
  const rs = regiones ?? (region ? [region] : null);
  return ATUENDOS.filter((a) =>
    (!rs || rs.includes(a.region)) && (!prefijos || prefijos.some((p) => a.id.startsWith(p))) && contiene(a.epoca, anio) && (!sexo || a.sexo === sexo) && (!clase || a.clase === clase));
}

export function listarRegiones() { return { ...REGIONES }; }
export function listarCatalogo() {
  return { atuendos: ATUENDOS.length, prendas: PRENDAS.length, tintes: TINTES.length, pieles: PIELES.length, cabellos: CABELLOS.length,
    etnias: ETNIAS.length, peinados: Object.keys(PEINADOS).length, barbas: Object.keys(BARBAS).length, tocados: Object.keys(TOCADOS).length, patrones: PATRONES_ID.length };
}

// ---------- Paletas ----------

/** Un tinte disponible en el año dado (si hay uno en la lista), con una pequeña variación. */
function tinteDe(ids, azar, anio) {
  const ok = ids.filter((id) => {
    const t = TINTES_POR_ID[id];
    return t && (anio === undefined || (anio >= t.desde && anio <= t.hasta));
  });
  const id = azar.elegir(ok.length ? ok : ids);
  const t = TINTES_POR_ID[id];
  if (!t) throw new Error(`Tinte desconocido: ${id}`);
  return { id, nombre: t.nombre, hex: variar(t.hex, azar, { tono: 4, sat: 0.04, luz: 0.04 }) };
}

/**
 * Paleta de cuatro colores {A, B, C, D} (principal, secundario, ribete, detalle).
 * esquema: 'analogo' | 'complementario' | 'triada' | 'monocromo' | 'tierra' | 'festivo'.
 */
export function generarPaleta({ esquema = 'analogo', semilla = 1, base } = {}) {
  const azar = crearAzar(`paleta:${semilla}`);
  const h0 = base ? hexAHsl(base)[0] : azar.entre(0, 360);
  const s0 = azar.entre(0.35, 0.7), l0 = azar.entre(0.28, 0.5);
  const c = (h, s, l) => hslAHex(h, limitar(s, 0.05, 0.95), limitar(l, 0.08, 0.92));
  let A, B, C, D;
  switch (esquema) {
    case 'complementario': A = c(h0, s0, l0); B = c(h0 + 180, s0 * 0.9, l0 + 0.08); C = c(h0 + 180, s0, l0 - 0.1); D = c(h0 + 40, 0.7, 0.55); break;
    case 'triada': A = c(h0, s0, l0); B = c(h0 + 120, s0 * 0.85, l0 + 0.05); C = c(h0 + 240, s0 * 0.85, l0); D = c(h0 + 60, 0.75, 0.55); break;
    case 'monocromo': A = c(h0, s0, l0); B = c(h0, s0 * 0.8, l0 + 0.18); C = c(h0, s0, l0 - 0.14); D = c(h0, s0 * 0.6, l0 + 0.32); break;
    case 'tierra': { const h = azar.entre(18, 48); A = c(h, azar.entre(0.3, 0.55), azar.entre(0.28, 0.45)); B = c(h + 10, 0.3, 0.6); C = c(h - 8, 0.45, 0.22); D = c(h + 5, 0.6, 0.5); break; }
    case 'festivo': A = c(h0, 0.5, 0.36); B = c(h0 + 150, 0.45, 0.5); C = c(h0 + 210, 0.5, 0.38); D = c(h0 + 45, 0.62, 0.55); break;
    default: A = c(h0, s0, l0); B = c(h0 + 28, s0 * 0.9, l0 + 0.1); C = c(h0 - 28, s0, l0 - 0.1); D = c(h0 + 60, 0.7, 0.55);
  }
  return { A, B, C, D };
}

/** Paleta histórica: un tinte por letra, de las listas del atuendo, respetando la época. */
function paletaDeAtuendo(a, azar, anio) {
  const p = {}, nombres = {};
  for (const [letra, ids] of Object.entries(a.paleta)) {
    const t = tinteDe(ids, azar, anio);
    p[letra] = t.hex; nombres[letra] = t.nombre;
  }
  return { colores: p, nombres };
}

function coloresDePrenda(pr, pal) {
  const A = pal.A ?? '#c9b99a';
  const B = pal.B ?? mezclar(A, '#ffffff', 0.3);
  const C = pal.C ?? oscurecer(A, 0.28);
  const D = pal.D ?? '#caa24a';
  const letra = { A, B, C, D };
  const k = {};
  for (const [rol, l] of Object.entries(pr.col)) k[rol] = letra[l] ?? A;
  // Que el ribete y el detalle se distingan del color principal.
  if (Math.abs(luminancia(k.ribete) - luminancia(k.principal)) < 0.04 && k.ribete === k.principal) {
    k.ribete = luminancia(k.principal) > 0.35 ? oscurecer(k.principal, 0.35) : aclarar(k.principal, 0.35);
  }
  return k;
}

/** Una sobreprenda o un manto con el mismo color que la túnica de debajo se pierde: se pasa al color secundario o se oscurece. */
function distinguirCapas(prendas, pal) {
  const torso = prendas.find((p) => p.ranura === 'torso');
  if (!torso) return;
  for (const p of prendas) {
    if (!['sobre', 'capa'].includes(p.ranura) || p.colores.principal !== torso.colores.principal || p.plantilla === 'delantal') continue;
    const alt = pal.B && Math.abs(luminancia(pal.B) - luminancia(pal.A)) > 0.05 ? pal.B : (luminancia(pal.A) > 0.4 ? oscurecer(pal.A, 0.32) : aclarar(pal.A, 0.3));
    p.colores = { ...p.colores, principal: alt, secundario: pal.A };
  }
}

// ---------- Variación de parámetros ----------

function variarParams(params, azar, fuerza) {
  const p = { ...params };
  if (typeof p.holgura === 'number') p.holgura = Math.max(0.002, p.holgura * azar.entre(1 - 0.25 * fuerza, 1 + 0.3 * fuerza));
  if (typeof p.vuelo === 'number') p.vuelo = Math.max(0, p.vuelo * azar.entre(1 - 0.25 * fuerza, 1 + 0.25 * fuerza));
  if (typeof p.pliegues === 'number') p.pliegues = Math.max(0, p.pliegues + azar.int(-1, 1) * Math.round(fuerza));
  if (typeof p.ancho === 'number' && p.ancho < 3) p.ancho = p.ancho * azar.entre(0.9, 1.1);
  return p;
}

// ---------- Rasgos de la persona ----------

const SEXOS = { f: 'mujer', m: 'hombre' };

function edadAleatoria(azar) {
  return Math.round(azar.ponderado([[azar.entre(3, 12), 1.2], [azar.entre(13, 19), 1], [azar.entre(20, 39), 3.4], [azar.entre(40, 59), 2.4], [azar.entre(60, 88), 1.2]]));
}

function elegirPiel(azar, atuendo, opc, etnia) {
  if (opc.piel) return esHex(opc.piel) ? opc.piel : (PIELES.find((x) => x.id === opc.piel)?.hex ?? PIELES[4].hex);
  const hexDe = (ids) => (PIELES.find((p) => p.id === azar.elegir(ids)) ?? PIELES[4]).hex;
  if (etnia) {
    // Una mezcla promedia un tono de cada pueblo, según su peso.
    let acum = null, peso = 0;
    for (const x of etnia) {
      const h = hexDe(x.e.pieles);
      acum = acum === null ? h : mezclar(acum, h, x.peso / (peso + x.peso));
      peso += x.peso;
    }
    return variar(acum, azar, { tono: 3, sat: 0.03, luz: 0.025 });
  }
  const base = hexDe(atuendo.pieles ?? PIELES.map((p) => p.id));
  return variar(base, azar, { tono: 3, sat: 0.03, luz: 0.025 });
}

function indicePiel(hex) {
  let mejor = 0, d = 1e9;
  const l = luminancia(hex);
  PIELES.forEach((p, i) => { const dd = Math.abs(luminancia(p.hex) - l); if (dd < d) { d = dd; mejor = i; } });
  return mejor;
}

function elegirCabello(azar, piel, edad, opc, etnia) {
  if (opc.cabello) return esHex(opc.cabello) ? opc.cabello : (CABELLOS.find((c) => c.id === opc.cabello)?.hex ?? CABELLOS[0].hex);
  const ip = indicePiel(piel);
  let pesos;
  if (etnia) pesos = sumaPesos(etnia, (e) => e.cabellos);
  else if (ip >= 7) pesos = [['negro', 6], ['negro-azulado', 2], ['castano-oscuro', 1]];
  else if (ip >= 3) pesos = [['negro', 5], ['negro-azulado', 2], ['castano-oscuro', 3], ['castano', 2], ['cobrizo', 0.2]];
  else pesos = [['negro', 2], ['castano-oscuro', 3], ['castano', 3], ['castano-claro', 2], ['rubio-oscuro', 1.5], ['rubio', 1.5], ['rubio-ceniza', 0.7], ['pelirrojo', 0.8], ['cobrizo', 0.6]];
  let id = azar.ponderado(pesos);
  // Canas.
  const probCanas = limitar((edad - 42) / 38, 0, 1);
  if (azar.n() < probCanas) id = azar.elegir(edad > 68 ? ['gris', 'blanco', 'blanco', 'canoso'] : ['canoso', 'gris']);
  const base = CABELLOS.find((c) => c.id === id).hex;
  return variar(base, azar, { tono: 3, sat: 0.03, luz: 0.03 });
}

// ---------- Etnia ----------

/** Etnia como texto, lista o {id: peso}: devuelve [{ e, peso }] con los pesos sumando 1. La primera manda en la ropa. */
function normalizarEtnia(spec) {
  if (spec === undefined || spec === null || spec === '') return null;
  let pares;
  if (typeof spec === 'string') pares = spec.split(',').map((x) => [x.trim(), 1]);
  else if (Array.isArray(spec)) pares = spec.map((x) => [x, 1]);
  else pares = Object.entries(spec);
  const lista = pares.filter(([id]) => id).map(([id, peso]) => {
    const e = ETNIAS_POR_ID[id];
    if (!e) throw new Error(`Etnia desconocida: ${id}. Disponibles: ${ETNIAS.map((x) => x.id).join(', ')}`);
    return { e, peso: Number(peso) > 0 ? Number(peso) : 1 };
  });
  if (!lista.length) return null;
  const total = lista.reduce((t, x) => t + x.peso, 0);
  return lista.map((x) => ({ e: x.e, peso: x.peso / total }));
}

const sumaPesos = (lista, fn) => {
  const m = new Map();
  for (const { e, peso } of lista) for (const [id, w] of fn(e)) m.set(id, (m.get(id) ?? 0) + w * peso);
  return [...m.entries()];
};

/** Ajuste de estatura por época (poblaciones más bajas antes del siglo XX), aproximado. */
function ajusteEstaturaAnio(anio) {
  if (anio === undefined || anio === null) return 0;
  if (anio < 1000) return -0.02;
  if (anio < 1800) return -0.015;
  if (anio < 1950) return -0.005;
  return 0.01;
}

const ESTATURA_REF = { f: 161, m: 174 }; // los promedios con los que medidas() calcula la altura por edad

// ---------- Persona ----------

/**
 * Opciones (todas opcionales): semilla, sexo ('f'|'m'), edad, corpulencia, altura (cm), postura,
 * atuendo (id), region, anio, clase ('baja'|'media'|'alta'), piel, cabello, barba (true|false|id),
 * peinado, tocado, calzado, paleta ({A,B,C,D} en hex), variacion (0..1), detalle ('minimo'|'medio'|'alto'),
 * rasgos (true: ojos, cejas y boca suaves), fondo, prendas (lista propia de prendas ya armadas).
 */
export function generarPersona(opc = {}) {
  const semilla = opc.semilla ?? 1;
  const azar = crearAzar(`persona:${semilla}`);
  const sexo = opc.sexo ?? (azar.bool() ? 'f' : 'm');
  const edad = Math.round(opc.edad ?? edadAleatoria(azar));
  const anio = opc.anio;

  const etnia = normalizarEtnia(opc.etnia);
  const estimaciones = [], avisos = [];

  // Atuendo: se afloja un filtro a la vez si no hay coincidencias, y se avisa de cuáles.
  let atuendo;
  if (opc.atuendo) {
    atuendo = ATUENDOS_POR_ID[opc.atuendo];
    if (!atuendo) throw new Error(`Atuendo desconocido: ${opc.atuendo}`);
  } else {
    const regiones = opc.region ? [opc.region] : (etnia ? etnia[0].e.regiones : null);
    const prefijos = opc.region ? null : (etnia ? etnia[0].e.prefijos : null);
    const f = (extra) => ({ regiones, prefijos, anio, sexo, clase: opc.clase, ...extra });
    const intentos = [
      [f({}), null],
      [f({ clase: undefined }), 'la clase social'],
      [f({ anio: undefined }), 'el año'],
      [f({ clase: undefined, anio: undefined }), 'el año y la clase social'],
      [f({ prefijos: null, clase: undefined, anio: undefined }), 'el año, la clase social ni el pueblo concreto: es un atuendo de otro pueblo de la misma región'],
      [{ anio, sexo, clase: opc.clase }, 'la región'],
      [{ anio, sexo }, 'la región y la clase social'],
      [{ sexo }, 'la región, el año y la clase social'],
    ].filter(([filtro], i, todos) => i === 0 || JSON.stringify(filtro) !== JSON.stringify(todos[i - 1][0]));
    for (const [filtro, relajado] of intentos) {
      const r = buscarAtuendos(filtro);
      if (r.length) {
        atuendo = azar.elegir(r);
        if (relajado) avisos.push(`No hay atuendo que cumpla ${relajado}${etnia ? ` para ${etnia[0].e.nombre}` : ''}: se eligió «${atuendo.nombre}» (${atuendo.epoca[0]} a ${atuendo.epoca[1]}).`);
        break;
      }
    }
    if (etnia && !opc.region) estimaciones.push(`Atuendo: «${atuendo.nombre}» (${REGIONES[atuendo.region]}), elegido por ser ${etnia.map((x) => x.e.nombre).join(' y ')}.`);
  }
  const anioPaleta = anio ?? Math.round((atuendo.epoca[0] + atuendo.epoca[1]) / 2);

  // Colores
  const pal = opc.paleta ?? paletaDeAtuendo(atuendo, azar.hijo('paleta'), anioPaleta).colores;
  const nombresPaleta = opc.paleta ? null : paletaDeAtuendo(atuendo, azar.hijo('paleta'), anioPaleta).nombres;
  const piel = elegirPiel(azar.hijo('piel'), atuendo, opc, etnia);
  const colorPelo = elegirCabello(azar.hijo('cabello'), piel, edad, opc, etnia);

  // Prendas
  const az = azar.hijo('prendas');
  const variacion = opc.variacion ?? 0.5;
  let ids = atuendo.prendas.slice();
  if (opc.calzado) ids = ids.filter((id) => PRENDAS_POR_ID[id].ranura !== 'calzado').concat(opc.calzado);
  const prendas = opc.prendas ?? ids.map((id) => {
    const pr = PRENDAS_POR_ID[id];
    if (!pr) throw new Error(`Prenda desconocida en ${atuendo.id}: ${id}`);
    return { id, nombre: pr.nombre, ranura: pr.ranura, plantilla: pr.plantilla, params: variarParams(pr.params, az, variacion), colores: coloresDePrenda(pr, pal) };
  });
  if (!opc.prendas) distinguirCapas(prendas, pal);

  // Cabeza
  const ah = azar.hijo('cabeza');
  const peinadosEtnia = etnia ? atuendo.peinados.filter((p) => etnia[0].e.peinados.includes(p)) : [];
  const peinadoId = opc.peinado ?? ah.elegir(peinadosEtnia.length ? peinadosEtnia : atuendo.peinados);
  let estilo = PEINADOS[peinadoId] ? peinadoId : 'corto';
  // Los mayores a veces pierden el pelo; la tonsura y las pelucas no se tocan.
  if (!opc.peinado && edad > 55 && sexo === 'm' && !['tonsura', 'peluca-barroca', 'peluca-egipcia'].includes(estilo) && ah.bool(0.25)) estilo = 'calvo';
  const quiereBarba = opc.barba === undefined ? true : opc.barba;
  let barbaId = 'ninguna';
  if (sexo === 'm' && edad >= 17 && quiereBarba) {
    if (typeof opc.barba === 'string') barbaId = opc.barba;
    else if (etnia) {
      const prob = etnia.reduce((t, x) => t + x.e.barba * x.peso, 0);
      const con = atuendo.barbas.filter((b) => b !== 'ninguna');
      barbaId = con.length && ah.bool(prob) ? ah.elegir(con) : 'ninguna';
    } else barbaId = ah.elegir(atuendo.barbas);
    if (typeof opc.barba !== 'string' && edad < 22 && ah.bool(0.6)) barbaId = 'ninguna';
  }
  const tocadoId = opc.tocado ?? ah.elegir(atuendo.tocados);
  const tocado = tocadoId && tocadoId !== 'ninguno' && TOCADOS[tocadoId]
    ? { id: tocadoId, colores: { principal: pal.A ?? '#c9b99a', secundario: pal.C ?? pal.B ?? '#8a6a3a', detalle: pal.D ?? '#caa24a' } }
    : null;
  if (tocado && (tocado.id === 'diadema' || tocado.id === 'corona' || tocado.id === 'trarilonko')) {
    tocado.colores.principal = pal.D ?? tocado.colores.principal;
    tocado.colores.secundario = pal.B ?? tocado.colores.secundario;
  }
  if (tocado && ['trarilonko'].includes(tocado.id)) tocado.colores.principal = '#b9bec6';

  const rostro = { ancho: 0.96 + ah.n() * 0.12, mandibula: ah.elegir(MANDIBULAS_ID), rasgos: !!opc.rasgos };
  const az2 = azar.hijo('cuerpo');
  const corpulenciaEstimada = etnia ? az2.ponderado(sumaPesos(etnia, (e) => e.corpulencia)) : az2.elegir(['delgada', 'media', 'media', 'media', 'robusta']);
  let escalaAltura;
  if (etnia && opc.altura === undefined) {
    const media = etnia.reduce((t, x) => t + x.e.altura[sexo === 'f' ? 0 : 1] * x.peso, 0);
    escalaAltura = (media / ESTATURA_REF[sexo]) * (1 + ajusteEstaturaAnio(anio)) * az2.entre(0.97, 1.03);
  }
  const cuerpo = {
    sexo, edad, escalaAltura,
    corpulencia: opc.corpulencia ?? corpulenciaEstimada,
    altura: opc.altura, postura: opc.postura ?? 'firme',
    saludo: opc.saludo === false || opc.saludo === 'ninguno' || opc.saludo === 'ninguna' ? null : (opc.saludo ?? 'derecha'),
  };

  if (etnia) {
    const nombres = etnia.map((x) => x.e.nombre).join(' y ');
    if (!opc.piel) estimaciones.push(`Piel: ${etnia.length > 1 ? `promedio de ${nombres}` : `rango habitual de ${nombres}`}, con variación individual.`);
    if (!opc.cabello) estimaciones.push(`Cabello: color habitual de ${nombres}${edad > 45 ? ', con canas según la edad' : ''}.`);
    if (opc.altura === undefined) estimaciones.push(`Estatura: unos ${Math.round(medidas(cuerpo).cm)} cm, estimada con el promedio de ${nombres}, la edad${anio !== undefined ? ' y el año' : ''}.`);
    if (!opc.corpulencia) estimaciones.push(`Complexión: ${cuerpo.corpulencia}, la más frecuente en ${nombres}.`);
    if (sexo === 'm' && edad >= 17 && opc.barba === undefined) estimaciones.push(`Barba: ${barbaId === 'ninguna' ? 'sin barba' : barbaId}, según la frecuencia en ${nombres}.`);
  }
  const decada = (n) => (n < 0 ? `${-n} a. C.` : `${n}`);
  return {
    semilla, titulo: `${atuendo.nombre}`,
    descripcion: `${SEXOS[sexo][0].toUpperCase() + SEXOS[sexo].slice(1)} de ${edad} años; ${atuendo.nombre} (${REGIONES[atuendo.region]}, ${decada(atuendo.epoca[0])} a ${decada(atuendo.epoca[1])}).`,
    sexo, edad, atuendo: atuendo.id, region: atuendo.region, epoca: atuendo.epoca, clase: atuendo.clase,
    cuerpo, rostro, piel,
    cabello: { estilo, color: colorPelo }, barba: { estilo: barbaId, color: colorPelo }, tocado,
    prendas, paleta: pal, nombresPaleta,
    etnia: etnia ? etnia.map((x) => ({ id: x.e.id, nombre: x.e.nombre, peso: Math.round(x.peso * 100) / 100 })) : null,
    estimaciones, avisos,
    detalle: opc.detalle ?? 'medio', fondo: opc.fondo ?? 'ninguno',
  };
}

// ---------- Ropa nueva ----------

const L = (a, b) => [a, b];
const LARGOS_TORSO = ['pecho', 'cintura', 'cadera', 'muslo-alto', 'muslo', 'rodilla', 'pantorrilla', 'tobillo', 'suelo'];
const MANGAS = ['sin', 'corta', 'codo', 'tres-cuartos', 'larga', 'campana', 'ancha', 'globo', 'abullonada', 'kimono'];
const ESCOTES = ['redondo', 'v', 'cuadrado', 'barco', 'alto', 'ojal'];
const BAJOS = ['recto', 'curvo', 'ondulado', 'dentado'];
const ANCHOS_PANTALON = ['ajustado', 'recto', 'amplio', 'bombacho', 'campana'];
const CALZADOS = ['sandalias', 'zapatos', 'botas', 'botas-altas', 'mocasines', 'zuecos', 'polainas', 'descalzo', 'zapatillas'];
const CUELLOS = ['collar', 'gorguera', 'alto', 'bufanda', 'pectoral', 'cuentas-largas', 'gargantilla', 'lazo'];
const ROLES_PATRON = PATRONES_ID.filter((p) => !['piel', 'jaspe', 'malla', 'escamas'].includes(p));

function patronAzar(azar, prob) {
  return azar.bool(prob) ? { tipo: azar.elegir(ROLES_PATRON), escala: Math.round(azar.entre(0.7, 1.4) * 10) / 10, rol: 'secundario' } : undefined;
}

/**
 * Inventa una ropa que no está en el catálogo: combina plantillas con parámetros al azar.
 * estilo: 'sobrio' | 'trabajo' | 'festivo' | 'ceremonial'. Devuelve { prendas, paleta, nombre }.
 */
export function generarRopaNueva({ semilla = 1, sexo = 'f', estilo = 'sobrio', esquema, paleta } = {}) {
  const azar = crearAzar(`ropa:${semilla}:${sexo}:${estilo}`);
  const festivo = estilo === 'festivo' || estilo === 'ceremonial';
  const pal = paleta ?? generarPaleta({ semilla, esquema: esquema ?? (estilo === 'trabajo' ? 'tierra' : festivo ? 'festivo' : azar.elegir(['analogo', 'monocromo', 'complementario'])) });
  const col = (r = {}) => ({ principal: pal.A, secundario: pal.B, ribete: pal.C, detalle: pal.D, ...r });
  const out = [];
  const add = (ranura, plantilla, params, colores, nombre) => out.push({ id: `nueva-${plantilla}-${out.length}`, nombre, ranura, plantilla, params, colores: colores ?? col() });
  const largoLargo = sexo === 'f' ? azar.bool(0.6) : azar.bool(0.25);
  const ornato = festivo ? 0.8 : estilo === 'trabajo' ? 0.1 : 0.4;

  // Torso (y a veces falda)
  const vestido = largoLargo && azar.bool(0.55);
  const torso = {
    largo: vestido ? azar.elegir(['pantorrilla', 'tobillo', 'suelo']) : azar.elegir(['cadera', 'muslo-alto', 'muslo', 'rodilla']),
    manga: estilo === 'trabajo' ? azar.elegir(['corta', 'tres-cuartos', 'larga']) : azar.elegir(MANGAS.filter((m) => m !== 'sin' || azar.bool(0.5))),
    escote: azar.elegir(ESCOTES), prof: Math.round(azar.entre(0.5, 1.4) * 10) / 10,
    cintura: vestido ? azar.elegir([0, 0.3, 0.6, 1]) : azar.elegir([0.7, 0.9, 1]),
    holgura: Math.round(azar.entre(0.006, 0.03) * 1000) / 1000, vuelo: vestido ? Math.round(azar.entre(0.02, 0.18) * 100) / 100 : 0.02,
    bajo: azar.elegir(BAJOS), ribete: azar.bool(0.6), puños: azar.bool(ornato),
    pliegues: azar.bool(0.5) ? azar.int(2, 8) : 0,
    ribeteBajo: azar.bool(ornato) ? Math.round(azar.entre(0.015, 0.035) * 1000) / 1000 : false,
    bandas: azar.bool(ornato * 0.5) ? azar.elegir(['central', 'lateral']) : undefined,
    botones: azar.bool(0.3) ? azar.int(3, 8) : 0,
    cruzada: azar.bool(0.12) ? azar.elegir(['izq', 'der']) : undefined,
    volantes: vestido && azar.bool(festivo ? 0.4 : 0.1) ? azar.int(1, 3) : 0,
    patron: patronAzar(azar, festivo ? 0.35 : 0.15),
    bandaPatron: azar.bool(ornato * 0.5) ? azar.elegir(ROLES_PATRON) : undefined,
  };
  add('torso', 'tunica', torso, col(), vestido ? 'Vestido nuevo' : 'Túnica nueva');

  if (!vestido) {
    if (sexo === 'f' && azar.bool(0.55)) {
      add('inferior', 'falda', { largo: azar.elegir(['rodilla', 'pantorrilla', 'tobillo', 'suelo']), vuelo: Math.round(azar.entre(0.03, 0.16) * 100) / 100, cintura: azar.elegir(['natural', 'alta', 'baja']), pliegues: azar.int(2, 8), volantes: azar.bool(0.2) ? azar.int(1, 3) : 0, bajo: azar.elegir(['recto', 'curvo', 'ondulado']), ribeteBajo: azar.bool(ornato) ? 0.02 : false, patron: patronAzar(azar, 0.2) }, col({ principal: pal.B ?? pal.A, secundario: pal.A }), 'Falda nueva');
    } else {
      add('inferior', 'pantalon', { largo: azar.elegir(['bajo-rodilla', 'pantorrilla', 'tobillo']), ancho: azar.elegir(ANCHOS_PANTALON), cintura: azar.elegir(['natural', 'baja']), cordon: azar.bool(0.3), puños: azar.bool(0.3), patron: patronAzar(azar, 0.1) }, col({ principal: pal.C ?? pal.A, ribete: pal.A }), 'Pantalón nuevo');
    }
  }
  // Sobreprenda
  if (azar.bool(festivo ? 0.55 : 0.4)) {
    add('sobre', 'chaqueta', { largo: azar.elegir(['cintura', 'cadera', 'muslo-alto', 'muslo', 'rodilla', 'pantorrilla']), manga: azar.elegir(['sin', 'sin', 'corta', 'larga', 'ancha']), botones: azar.int(0, 8), cintura: azar.elegir([0.3, 0.6, 0.9]), solapa: azar.bool(0.4), abertura: 0.01, aberturaBajo: azar.elegir([0.012, 0.03, 0.05]), vuelo: 0.04, puños: azar.bool(0.3), pliegues: azar.bool(0.3) ? azar.int(2, 5) : 0, bordadoBorde: azar.bool(ornato * 0.5) }, col({ principal: pal.C ?? pal.A, ribete: pal.D }), 'Sobreprenda nueva');
  }
  // Capa o manto
  if (azar.bool(festivo ? 0.5 : 0.3)) {
    const tipo = azar.elegir(['capa', 'poncho', 'drapeado', 'chal']);
    const params = { capa: { largo: azar.elegir(['cadera', 'muslo', 'pantorrilla', 'tobillo', 'suelo']), ancho: Math.round(azar.entre(0.6, 1.3) * 10) / 10, cierre: 'broche', ribete: azar.bool(0.6), ribeteBajo: azar.bool(ornato) },
      poncho: { largo: azar.elegir(['cadera', 'muslo-alto', 'muslo']), ancho: Math.round(azar.entre(0.8, 1.2) * 10) / 10, listas: azar.int(0, 4), punta: Math.round(azar.entre(0.02, 0.09) * 100) / 100, patron: patronAzar(azar, 0.35) },
      drapeado: { largo: azar.elegir(['pantorrilla', 'tobillo', 'suelo']), lado: azar.elegir(['izq', 'der']), pliegues: azar.int(3, 7), orla: azar.bool(0.6), patron: patronAzar(azar, 0.25) },
      chal: { largo: azar.elegir(['pecho', 'cintura', 'cadera']), flecos: azar.bool(0.5), patron: patronAzar(azar, 0.3) } }[tipo];
    add('capa', tipo, params, col({ principal: pal.B ?? pal.A, secundario: pal.A }), 'Manto nuevo');
  }
  // Faja, cuello, calzado, accesorios
  if (azar.bool(vestido ? 0.45 : 0.55)) add('faja', 'faja', { y: azar.elegir(['cintura', 'cintura', 'cadera', 'pecho']), ancho: Math.round(azar.entre(0.008, 0.05) * 1000) / 1000, hebilla: azar.bool(0.4), cuelga: azar.bool(0.3) ? azar.elegir(['izq', 'der']) : undefined, doble: azar.bool(0.2), patron: patronAzar(azar, 0.2) }, col({ principal: pal.C ?? pal.A, ribete: pal.D }), 'Faja nueva');
  if (azar.bool(festivo ? 0.8 : 0.4)) add('cuello', 'cuello', { tipo: azar.elegir(CUELLOS), colgante: azar.bool(0.4) }, col({ principal: pal.D ?? pal.B, secundario: pal.B }), 'Adorno de cuello nuevo');
  add('calzado', 'calzado', { tipo: azar.elegir(CALZADOS), hebilla: azar.bool(0.2) }, col({ principal: oscurecer(pal.C ?? pal.A, 0.35), secundario: pal.B }), 'Calzado nuevo');
  if (azar.bool(ornato * 0.7)) add('accesorio', 'accesorio', { tipo: azar.elegir(['brazaletes', 'bolso']), alto: azar.bool(0.3), lado: azar.elegir(['izq', 'der']) }, col({ principal: pal.D }), 'Accesorio nuevo');
  return { prendas: out, paleta: pal, nombre: `Ropa nueva (${estilo}), semilla ${semilla}` };
}

/** Una persona con ropa inventada en lugar de un atuendo del catálogo. */
export function generarPersonaConRopaNueva(opc = {}) {
  const semilla = opc.semilla ?? 1;
  const sexo = opc.sexo ?? (crearAzar(`persona:${semilla}`).bool() ? 'f' : 'm');
  const ropa = generarRopaNueva({ semilla, sexo, estilo: opc.estilo ?? 'sobrio', esquema: opc.esquema, paleta: opc.paleta });
  const base = generarPersona({ ...opc, sexo, prendas: ropa.prendas, paleta: ropa.paleta });
  base.titulo = ropa.nombre;
  base.descripcion = `${SEXOS[sexo][0].toUpperCase() + SEXOS[sexo].slice(1)} de ${base.edad} años con ${ropa.nombre.toLowerCase()}.`;
  base.atuendo = null;
  return base;
}

export function generarSVG(opc = {}) {
  const persona = opc.ropaNueva || opc.estilo ? generarPersonaConRopaNueva(opc) : generarPersona(opc);
  return renderizar(persona, { detalle: opc.detalle, fondo: opc.fondo });
}

export { renderizar, PIELES, CABELLOS, TINTES, ATUENDOS, PRENDAS, REGIONES, CORPULENCIAS, POSTURAS, ETNIAS };
export { PEINADOS, BARBAS, TOCADOS } from './cabeza.js';
export { PATRONES_ID } from './patrones.js';
