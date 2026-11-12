// Dibuja una persona descrita por `generarPersona` como SVG.
//
// Sin bordes: el volumen sale de degradados propios de cada color (luz desde arriba a la izquierda, sombra y calor
// en el lado opuesto) y de sombras suaves que cada capa proyecta sobre las de abajo (la manga sobre el torso, la
// cabeza sobre el cuello, el pelo sobre la frente). Cada forma se define una vez y se reutiliza con <use>.

import { LIENZO, medidas, deformar, trazoTorso, trazoBrazo, trazoPierna, trazoMano, trazoPie, trazoCuello, trazoAntebrazo } from './cuerpo.js';
import { geometriaCabeza, trazoCabeza, trazoOrejas, rasgosRostro, PEINADOS, BARBAS, TOCADOS } from './cabeza.js';
import { PLANTILLAS, ORDEN_RANURAS, finManga } from './prendas.js';
import { PATRONES } from './patrones.js';
import { validarPrenda } from './tipos-de-ropa.js';
import { mezclar, escapar, num } from './util.js';

function contexto(pref) {
  const defs = new Map();
  return {
    defs,
    patron(tipo, a, b, escala = 1) {
      const f = PATRONES[tipo] ?? PATRONES.rayas;
      const e = Math.round(escala * 100) / 100;
      const id = `${pref}-p-${tipo}-${a.slice(1)}-${b.slice(1)}-${String(e).replace('.', '_')}`;
      if (!defs.has(id)) {
        const [w, h, cuerpo] = f(a, b, e);
        defs.set(id, `<pattern id="${id}" width="${num(w)}" height="${num(h)}" patternUnits="userSpaceOnUse">${cuerpo}</pattern>`);
      }
      return `url(#${id})`;
    },
  };
}

/** Hash corto y determinista (FNV-1a) para que los id de cada figura no choquen con los de otra en la misma página. */
function hashCorto(texto) {
  let h = 0x811c9dc5;
  for (let i = 0; i < texto.length; i++) { h ^= texto.charCodeAt(i); h = Math.imul(h, 0x01000193) >>> 0; }
  return h.toString(36);
}

const attr = (o) => Object.entries(o).filter(([, v]) => v !== undefined && v !== null && v !== false).map(([k, v]) => ` ${k}="${escapar(v)}"`).join('');

/** Gira una ruta de comandos absolutos (M, L, C, Z) `ang` grados alrededor de (cx, cy). */
function rotarRuta(d, ang, cx, cy) {
  const r = (ang * Math.PI) / 180, co = Math.cos(r), si = Math.sin(r);
  return d.replace(/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/g, (_, a, b) => {
    const x = Number(a) - cx, y = Number(b) - cy;
    return `${num(cx + x * co - y * si)} ${num(cy + x * si + y * co)}`;
  });
}

/** Aplica una deformación (x, y) → (x', y') a todos los puntos de una ruta de comandos absolutos. */
function deformarRuta(d, f) {
  return d.replace(/(-?\d+(?:\.\d+)?(?:e[-+]?\d+)?) (-?\d+(?:\.\d+)?(?:e[-+]?\d+)?)/g, (_, a, b) => { const [x, y] = f(Number(a), Number(b)); return `${num(x)} ${num(y)}`; });
}

function trazoDe(s) {
  const o = { d: s.d, fill: s.fill ?? 'none' };
  if (s.stroke) {
    o.stroke = s.stroke;
    o['stroke-width'] = s.sw ?? 1;
    o['stroke-linecap'] = s.cap ?? 'round';
    o['stroke-linejoin'] = 'round';
    if (s.dash) o['stroke-dasharray'] = s.dash;
  }
  if (s.op !== undefined && s.op !== 1) o.opacity = s.op;
  return o;
}

// Campos que solo describen a la persona en texto: no cambian el dibujo, así que no entran en el prefijo de ids.
const CAMPOS_DESCRIPTIVOS = new Set(['titulo', 'descripcion', 'estimaciones', 'avisos', 'notas', 'coloresPrendas', 'nombresPaleta', 'confianza', 'fuentes', 'fondo', 'colorInfo', 'nombre']);

/** Un estilo de peinado, barba o tocado que no existe, o una prenda con parámetros mal escritos, lanza un error en vez de dibujarse distinto. */
function validarPersona(persona) {
  const quiero = (campo, id, tabla, extra = []) => { if (id !== undefined && id !== null && !(id in tabla) && !extra.includes(id)) throw new Error(`${campo} desconocido: ${JSON.stringify(id)}. Disponibles: ${Object.keys(tabla).join(', ')}.`); };
  quiero('Peinado', persona.cabello?.estilo, PEINADOS);
  quiero('Barba', persona.barba?.estilo, BARBAS);
  quiero('Tocado', persona.tocado?.id, TOCADOS);
  for (const prenda of persona.prendas ?? []) validarPrenda(prenda);
}

/**
 * Versión más liviana (unos 7 % menos): las coordenadas de las formas pasan de 2 a 1 decimal (0,1 unidad de un lienzo
 * de 600 de ancho). Solo toca el atributo `d`; colores, opacidades y degradados quedan igual.
 */
export function compactar(svg) {
  return svg.replace(/ d="([^"]*)"/g, (_, d) => ` d="${d.replace(/-?\d+\.\d\d+/g, (m) => String(Math.round(Number(m) * 10) / 10))}"`);
}

/** Devuelve el SVG (texto) de la persona descrita. `opciones.compacto` da la versión liviana (ver `compactar`). */
export function renderizar(persona, opciones = {}) {
  validarPersona(persona);
  const nivel = opciones.detalle ?? persona.detalle ?? 'medio';
  const contorno = opciones.contorno ?? persona.contorno ?? 'ninguno'; // 'ninguno' | 'suave'
  validarPersona(persona);
  const fondo = opciones.fondo ?? persona.fondo ?? 'ninguno';
  const M = medidas(persona.cuerpo);
  const G = geometriaCabeza(M, persona.rostro);
  const piel = persona.piel;
  const { cx } = M;
  const alto = nivel === 'alto';
  const plano = nivel === 'minimo';

  // --- Formas, degradados y sombras ---
  // Las formas y el recorte son propios de cada figura: llevan un prefijo derivado de su contenido.
  const pref = `x${hashCorto(JSON.stringify([persona, nivel, contorno], (k, v) => (CAMPOS_DESCRIPTIVOS.has(k) ? undefined : v)))}`;
  const ctx = contexto(pref);
  const caminos = [], idsSilueta = [], degradados = new Map();
  let nForma = 0;
  const def = deformar(M);
  const W = def ? (d) => deformarRuta(d, def) : (d) => d;
  const reg = (d0) => { const d = W(d0); const id = `${pref}f${nForma++}`; caminos.push(`<path id="${id}" d="${d}"/>`); idsSilueta.push(id); return id; };

  const TONOS = {
    piel: { sombra: '#5a1c10', luz: '#ffe6c4' },
    pelo: { sombra: '#0c0710', luz: '#eadfcf' },
    tela: { sombra: '#1a1230', luz: '#fff3da' },
  };
  /** Degradado propio de un color: luz a la izquierda, terminador y sombra cálida a la derecha. */
  const grad = (hex, tipo) => {
    const id = `${pref}-g-${alto ? 'a' : 'm'}-${tipo}-${hex.slice(1)}`;
    if (!degradados.has(id)) {
      const t = TONOS[tipo] ?? TONOS.tela, e = alto ? 1.2 : 1;
      const st = (o, c) => `<stop offset="${o}" stop-color="${c}"/>`;
      degradados.set(id, `<linearGradient id="${id}" x1="0" y1="0" x2="1" y2="0.22">${st(0, mezclar(hex, t.sombra, 0.12 * e))}${st(0.13, mezclar(hex, t.luz, 0.14))}${st(0.33, mezclar(hex, t.luz, 0.28 * e))}${st(0.58, hex)}${st(0.82, mezclar(hex, t.sombra, 0.3 * e))}${st(1, mezclar(hex, t.sombra, 0.52 * e))}</linearGradient>`);
    }
    return `url(#${id})`;
  };

  const sombraProyectada = (ids) =>
    `<g clip-path="url(#${pref}s)" opacity="${alto ? 0.44 : 0.34}"><g filter="url(#${pref}-bl)" transform="translate(3.5 5)" fill="#1c0f0c">${ids.map((id) => `<use href="#${id}"/>`).join('')}</g></g>`;

  /** Capa de formas: su sombra proyectada sobre lo de abajo y luego los rellenos con degradado. */
  const capa = (piezas, tipo = 'tela') => {
    if (!piezas.length) return '';
    const ids = piezas.map((p) => reg(p.d));
    let out = plano ? '' : sombraProyectada(ids);
    piezas.forEach((p, i) => {
      const op = p.op !== undefined && p.op !== 1 ? p.op : undefined;
      const esHex = typeof p.fill === 'string' && p.fill.startsWith('#');
      const rellena = plano || p.plano || !esHex ? p.fill : grad(p.fill, tipo);
      out += `<use${attr({ href: `#${ids[i]}`, fill: rellena, opacity: op })}/>`;
      if (!plano && !p.plano && p.volumen !== false) {
        if (!esHex) out += `<use href="#${ids[i]}" fill="url(#${pref}-sh)" opacity="0.6"/>`;
        if (alto) out += `<use href="#${ids[i]}" fill="url(#${pref}-sv)" opacity="0.5"/>`;
      }
      if (contorno === 'suave' && esHex) out += `<use${attr({ href: `#${ids[i]}`, fill: 'none', stroke: mezclar(p.fill, '#000000', 0.35), 'stroke-width': 1, opacity: 0.35 })}/>`;
    });
    return out;
  };

  /** Detalles (trazos, botones, ribetes): se dibujan tal cual; los marcados `suave` van desenfocados, como pliegues. */
  const detalles = (lista) => {
    let duros = '', suaves = '';
    for (const s of lista) {
      const trazo = `<path${attr(trazoDe({ ...s, d: W(s.d) }))}/>`;
      if (s.suave && !plano) suaves += trazo; else duros += trazo;
    }
    return duros + (suaves ? `<g filter="url(#${pref}-bs)">${suaves}</g>` : '');
  };

  /** Capas con relleno y trazos sueltos (cabello, barba, tocado). */
  const figuras = (lista, tipo, girar) => {
    let out = '';
    for (const s0 of lista) {
      const s = girar ? { ...s0, d: girar(s0.d) } : s0;
      if (s.fill === 'none') out += detalles([s]);
      else out += capa([s], tipo);
    }
    return out;
  };

  // --- Pose de la cabeza: se giran las formas (no un grupo) para que sombras y recortes sigan coincidiendo ---
  const angCabeza = M.pose.cabeza ?? 0;
  const girar = Math.abs(angCabeza) > 0.05 ? (d) => rotarRuta(d, angCabeza, cx, M.y.hombro) : null;
  const giraLista = (l) => (girar ? l.map((s) => ({ ...s, d: girar(s.d) })) : l);

  // --- Prendas ---
  const prendas = persona.prendas.map((pr) => {
    const f = PLANTILLAS[pr.plantilla];
    if (!f) throw new Error(`Plantilla desconocida: ${pr.plantilla}`);
    return { ...pr, r: f(M, pr.params ?? {}, pr.colores, ctx) };
  });

  // --- Cabello, barba y tocado ---
  const colorPelo = persona.cabello?.color ?? '#3b2a20';
  const peinado = (PEINADOS[persona.cabello?.estilo] ?? PEINADOS.calvo)(G, colorPelo);
  const barba = (BARBAS[persona.barba?.estilo] ?? BARBAS.ninguna)(G, persona.barba?.color ?? colorPelo).flat();
  const toc = persona.tocado ? (TOCADOS[persona.tocado.id] ?? TOCADOS.ninguno)(G, persona.tocado.colores) : { atras: [], frente: [] };

  let cuerpo = '';
  // 1. Detrás: capas, cabello largo, tocado trasero
  for (const p of prendas.filter((q) => q.r.atras?.length)) {
    cuerpo += capa(p.r.atras.filter((s) => !s.atras));
    cuerpo += detalles(p.r.atras.filter((s) => s.atras));
  }
  cuerpo += figuras(toc.atras, 'tela', girar);
  cuerpo += figuras(peinado.atras, 'pelo', girar);

  // 2. Piel: piernas y pies, torso, brazos, manos y cuello, cada uno proyectando su sombra sobre lo anterior
  const sp = { fill: piel };
  const brazosPiel = [1, -1].map((s) => ({ d: trazoBrazo(M, s), ...sp }));
  const manosPiel = [1, -1].filter((s) => s !== M.saludoLado).flatMap((s) => trazoMano(M, s).map((d) => ({ d, ...sp })));
  cuerpo += capa([...[1, -1].map((s) => ({ d: trazoPierna(M, s), ...sp })), ...[1, -1].map((s) => ({ d: trazoPie(M, s), ...sp }))], 'piel');
  cuerpo += capa([{ d: trazoTorso(M), ...sp }], 'piel');
  for (const b of brazosPiel) cuerpo += capa([b], 'piel');
  cuerpo += capa(manosPiel, 'piel');
  cuerpo += capa([{ d: trazoCuello(M), ...sp }], 'piel');

  // 3. Ropa por ranura; las mangas proyectan su propia sombra sobre el torso
  const ordenadas = prendas.slice().sort((a, b) => (ORDEN_RANURAS[a.ranura] ?? 50) - (ORDEN_RANURAS[b.ranura] ?? 50));
  for (const p of ordenadas) {
    cuerpo += `<g data-prenda="${escapar(p.id)}">`;
    let lote = [];
    for (const pieza of p.r.partes) {
      if (pieza.proyecta) { cuerpo += capa(lote); lote = []; cuerpo += capa([pieza]); } else lote.push(pieza);
    }
    cuerpo += capa(lote);
    cuerpo += detalles(p.r.detalles);
    cuerpo += '</g>';
  }

  // 3b. Mano en saludo: encima de la ropa para que ni mangas ni mantos la tapen.
  if (M.saludoLado) {
    const piezas = [];
    if (prendas.some((p) => p.ranura === 'capa')) {
      const finMax = Math.max(M.y.codo, ...prendas.filter((p) => p.params?.manga && p.params.manga !== 'sin').map((p) => finManga(M, p.params.manga)));
      if (finMax < M.y.muneca - 2) piezas.push({ d: trazoAntebrazo(M, M.saludoLado, finMax), ...sp });
    }
    for (const d of trazoMano(M, M.saludoLado)) piezas.push({ d, ...sp });
    cuerpo += `<g data-saludo="${M.saludoLado === -1 ? 'derecha' : 'izquierda'}">${capa(piezas, 'piel')}</g>`;
  }

  // 4. Cabeza
  const cabezaPieza = { d: trazoCabeza(G), ...sp };
  const orejas = trazoOrejas(G).map((d) => ({ d, ...sp }));
  cuerpo += capa(giraLista([...orejas, cabezaPieza]), 'piel');
  if (persona.rostro?.rasgos) cuerpo += detalles(giraLista(rasgosRostro(G, piel)));
  cuerpo += figuras(barba, 'pelo', girar);
  cuerpo += figuras(peinado.frente, 'pelo', girar);
  cuerpo += figuras(toc.frente, 'tela', girar);

  // --- Sombra en el suelo ---
  const suelo = plano ? '' : `<ellipse cx="${cx}" cy="${LIENZO.suelo + 1}" rx="${num(M.H * 0.2)}" ry="${num(M.H * 0.017)}" fill="url(#${pref}-su)"/>`;

  const inclina = M.pose.inclinacion ?? 0;
  const defs = [
    `<linearGradient id="${pref}-sh" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#000" stop-opacity="0.18"/><stop offset="0.25" stop-color="#fff" stop-opacity="0.16"/><stop offset="0.6" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.4"/></linearGradient>`,
    `<linearGradient id="${pref}-sv" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity="0.14"/><stop offset="0.5" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.22"/></linearGradient>`,
    `<radialGradient id="${pref}-su"><stop offset="0" stop-color="#1c0f0c" stop-opacity="0.34"/><stop offset="0.6" stop-color="#1c0f0c" stop-opacity="0.14"/><stop offset="1" stop-color="#1c0f0c" stop-opacity="0"/></radialGradient>`,
    `<filter id="${pref}-bl" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="3.6"/></filter>`,
    `<filter id="${pref}-bs" x="-5%" y="-5%" width="110%" height="110%"><feGaussianBlur stdDeviation="0.9"/></filter>`,
    ...degradados.values(),
    ...ctx.defs.values(),
    ...caminos,
    `<clipPath id="${pref}s">${idsSilueta.map((id) => `<use href="#${id}"/>`).join('')}</clipPath>`,
  ].join('');
  const bg = fondo && fondo !== 'ninguno' ? `<rect width="${LIENZO.ancho}" height="${LIENZO.alto}" fill="${escapar(fondo)}"/>` : '';
  const titulo = persona.titulo ? `<title>${escapar(persona.titulo)}</title>` : '';
  const desc = persona.descripcion ? `<desc>${escapar(persona.descripcion)}</desc>` : '';
  const figura = Math.abs(inclina) > 0.02 ? `<g transform="rotate(${num(inclina)} ${cx} ${LIENZO.suelo})">${cuerpo}</g>` : cuerpo;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${LIENZO.ancho} ${LIENZO.alto}" role="img">${titulo}${desc}<defs>${defs}</defs>${bg}${suelo}${figura}</svg>`;
  return opciones.compacto ? compactar(svg) : svg;
}
