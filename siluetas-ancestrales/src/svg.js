// Dibuja una persona descrita por `generarPersona` como SVG.

import { LIENZO, medidas, trazoTorso, trazoBrazo, trazoPierna, trazoMano, trazoPie, trazoCuello, trazoAntebrazo } from './cuerpo.js';
import { geometriaCabeza, trazoCabeza, trazoOrejas, rasgosRostro, PEINADOS, BARBAS, TOCADOS } from './cabeza.js';
import { PLANTILLAS, ORDEN_RANURAS, finManga } from './prendas.js';
import { PATRONES } from './patrones.js';
import { oscurecer, aclarar, escapar, num } from './util.js';

function contexto() {
  const defs = new Map();
  return {
    defs,
    patron(tipo, a, b, escala = 1) {
      const f = PATRONES[tipo] ?? PATRONES.rayas;
      const e = Math.round(escala * 100) / 100;
      const id = `p-${tipo}-${a.slice(1)}-${b.slice(1)}-${String(e).replace('.', '_')}`;
      if (!defs.has(id)) {
        const [w, h, cuerpo] = f(a, b, e);
        defs.set(id, `<pattern id="${id}" width="${num(w)}" height="${num(h)}" patternUnits="userSpaceOnUse">${cuerpo}</pattern>`);
      }
      return `url(#${id})`;
    },
  };
}

const attr = (o) => Object.entries(o).filter(([, v]) => v !== undefined && v !== null && v !== false).map(([k, v]) => ` ${k}="${escapar(v)}"`).join('');

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

/** Dibuja un detalle; si trae `contorno` pone antes un trazo más grueso oscuro. */
function dibujarDetalle(s) {
  let out = '';
  if (s.contorno && s.stroke) out += `<path${attr({ d: s.d, fill: 'none', stroke: s.contorno, 'stroke-width': (s.sw ?? 1) + 2.2, 'stroke-linecap': s.cap === 'butt' ? 'butt' : 'round', 'stroke-dasharray': s.dash })}/>`;
  out += `<path${attr(trazoDe(s))}/>`;
  return out;
}

/** Grupo de piezas unidas: primero el contorno de todas, luego el relleno, luego el sombreado. */
function dibujarGrupo(piezas, nivel, esquema) {
  if (!piezas.length) return '';
  let out = '';
  for (const s of piezas) out += `<path${attr({ d: s.d, fill: s.fill, stroke: s.contorno, 'stroke-width': s.swContorno ?? 3, 'stroke-linejoin': 'round', opacity: s.op !== undefined && s.op !== 1 ? s.op : undefined })}/>`;
  for (const s of piezas) out += `<path${attr({ d: s.d, fill: s.fill, opacity: s.op !== undefined && s.op !== 1 ? s.op : undefined })}/>`;
  if (nivel !== 'minimo') {
    for (const s of piezas) {
      if (s.volumen === false || s.plano) continue;
      out += `<path${attr({ d: s.d, fill: 'url(#sombra-h)', opacity: s.sombra ?? (nivel === 'alto' ? 1 : 0.75) })}/>`;
      if (nivel === 'alto') out += `<path${attr({ d: s.d, fill: 'url(#sombra-v)', opacity: 0.55 })}/>`;
    }
  }
  return out;
}

function pielDe(piel) {
  return { fill: piel, contorno: oscurecer(piel, 0.42), swContorno: 2.8 };
}

/** Devuelve el SVG (texto) de la persona descrita. */
export function renderizar(persona, opciones = {}) {
  const nivel = opciones.detalle ?? persona.detalle ?? 'medio';
  const fondo = opciones.fondo ?? persona.fondo ?? 'ninguno';
  const M = medidas(persona.cuerpo);
  const G = geometriaCabeza(M, persona.rostro);
  const ctx = contexto();
  const piel = persona.piel;
  const { cx } = M;

  const capas = []; // [orden, svg]
  // --- Sombra en el suelo ---
  let suelo = '';
  if (nivel !== 'minimo') {
    suelo = `<ellipse cx="${cx}" cy="${LIENZO.suelo + 4}" rx="${num(M.H * 0.17)}" ry="${num(M.H * 0.014)}" fill="#000" opacity="0.16"/>`;
  }

  // --- Prendas ---
  const prendas = persona.prendas.map((pr) => {
    const f = PLANTILLAS[pr.plantilla];
    if (!f) throw new Error(`Plantilla desconocida: ${pr.plantilla}`);
    return { ...pr, r: f(M, pr.params ?? {}, pr.colores, ctx) };
  });

  // --- Cabello, barba y tocado ---
  const colorPelo = persona.cabello?.color ?? '#3b2a20';
  const peinado = (PEINADOS[persona.cabello?.estilo] ?? PEINADOS.calvo)(G, colorPelo);
  const barbaFn = BARBAS[persona.barba?.estilo] ?? BARBAS.ninguna;
  const barba = barbaFn(G, persona.barba?.color ?? colorPelo);
  const toc = persona.tocado ? (TOCADOS[persona.tocado.id] ?? TOCADOS.ninguno)(G, persona.tocado.colores) : { atras: [], frente: [] };

  const pelo = (lista) => lista.map((s) => ({ ...s, contorno: oscurecer(typeof s.fill === 'string' && s.fill.startsWith('#') ? s.fill : colorPelo, 0.5), swContorno: 2.4 }));

  let cuerpo = '';
  // 1. Detrás: capas, cabello largo, tocado trasero
  const atrasPrendas = prendas.filter((p) => p.r.atras?.length);
  for (const p of atrasPrendas) {
    const piezas = p.r.atras.filter((s) => !s.atras);
    cuerpo += dibujarGrupo(piezas, nivel);
    for (const s of p.r.atras.filter((q) => q.atras)) cuerpo += dibujarDetalle(s);
  }
  const dibujarLista = (lista, op) => {
    let o = '';
    for (const s of lista) {
      if (s.stroke && s.fill === 'none') o += dibujarDetalle(s);
      else o += dibujarGrupo([{ ...s, contorno: s.stroke ?? oscurecer(s.fill, 0.5), swContorno: 2.4, sombra: op }], nivel);
    }
    return o;
  };
  cuerpo += dibujarLista(toc.atras);
  cuerpo += dibujarLista(pelo(peinado.atras), 0.5);

  // 2. Piel
  const sp = pielDe(piel);
  const partesPiel = [
    ...[1, -1].map((s) => ({ d: trazoPierna(M, s), ...sp })),
    ...[1, -1].map((s) => ({ d: trazoPie(M, s), ...sp })),
    { d: trazoTorso(M), ...sp },
    ...[1, -1].map((s) => ({ d: trazoBrazo(M, s), ...sp })),
    ...[1, -1].filter((s) => s !== M.saludoLado).flatMap((s) => trazoMano(M, s).map((d) => ({ d, ...sp }))),
    { d: trazoCuello(M), ...sp },
  ];
  cuerpo += dibujarGrupo(partesPiel, nivel);
  if (nivel === 'alto') {
    // Sombra del mentón sobre el cuello.
    cuerpo += `<path d="${suavizar2(G)}" fill="#000" opacity="0.18"/>`;
  }

  // 3. Ropa por ranura
  const ordenadas = prendas.slice().sort((a, b) => (ORDEN_RANURAS[a.ranura] ?? 50) - (ORDEN_RANURAS[b.ranura] ?? 50));
  for (const p of ordenadas) {
    cuerpo += `<g data-prenda="${escapar(p.id)}">`;
    cuerpo += dibujarGrupo(p.r.partes, nivel);
    for (const s of p.r.detalles) cuerpo += dibujarDetalle(s);
    cuerpo += '</g>';
  }

  // 3b. Mano en saludo: encima de la ropa para que ni mangas ni mantos la tapen.
  if (M.saludoLado) {
    const piezas = [];
    const tieneManto = prendas.some((p) => p.ranura === 'capa');
    if (tieneManto) {
      const finMax = Math.max(M.y.codo, ...prendas.filter((p) => p.params?.manga && p.params.manga !== 'sin').map((p) => finManga(M, p.params.manga)));
      if (finMax < M.y.muneca - 2) piezas.push({ d: trazoAntebrazo(M, M.saludoLado, finMax), ...sp });
    }
    for (const d of trazoMano(M, M.saludoLado)) piezas.push({ d, ...sp });
    cuerpo += `<g data-saludo="${M.saludoLado === -1 ? 'derecha' : 'izquierda'}">${dibujarGrupo(piezas, nivel)}</g>`;
  }

  // 4. Cabeza
  const cabezaPieza = { d: trazoCabeza(G), ...sp };
  const orejas = trazoOrejas(G).map((d) => ({ d, ...sp }));
  cuerpo += dibujarGrupo([...orejas, cabezaPieza], nivel);
  if (nivel !== 'minimo') {
    cuerpo += `<path d="${G.E([[0, 0.5], [0.9, 0.6], [0.92, 0.64], [0.74, 0.82], [0.44, 0.96], [0, 1.0]])}" fill="#000" opacity="0.07"/>`;
  }
  if (persona.rostro?.rasgos) for (const s of rasgosRostro(G, piel)) cuerpo += dibujarDetalle(s);
  cuerpo += dibujarLista(pelo(barba.flat()), 0.5);
  cuerpo += dibujarLista(pelo(peinado.frente), 0.5);
  cuerpo += dibujarLista(toc.frente);

  const alto = LIENZO.alto;
  const defs = [
    '<linearGradient id="sombra-h" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#000" stop-opacity="0.34"/><stop offset="0.2" stop-color="#000" stop-opacity="0"/><stop offset="0.58" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.4"/></linearGradient>',
    '<linearGradient id="sombra-v" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity="0.12"/><stop offset="0.5" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.2"/></linearGradient>',
    ...ctx.defs.values(),
  ].join('');
  const bg = fondo && fondo !== 'ninguno' ? `<rect width="${LIENZO.ancho}" height="${alto}" fill="${escapar(fondo)}"/>` : '';
  const titulo = persona.titulo ? `<title>${escapar(persona.titulo)}</title>` : '';
  const desc = persona.descripcion ? `<desc>${escapar(persona.descripcion)}</desc>` : '';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${LIENZO.ancho} ${alto}" role="img">${titulo}${desc}<defs>${defs}</defs>${bg}${suelo}${cuerpo}</svg>`;
}

function suavizar2(G) {
  // Sombra bajo el mentón: elipse achatada sobre el cuello.
  return G.C(0, 1.05, 0.62, 0.1);
}
