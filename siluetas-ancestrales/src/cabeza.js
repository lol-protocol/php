// Cabeza de frente, orejas, rostro opcional, peinados, barbas y tocados.
// Las formas se dibujan en unidades de la cabeza: x en semianchos (1 = sienes), y en alturas de cabeza desde la coronilla.

import { suavizar, espejo, oscurecer, aclarar, mezclar, limitar, lerp } from './util.js';

const S = (d, fill, extra = {}) => ({ d, fill, ...extra });

export function geometriaCabeza(M, rostro = {}) {
  const headH = M.y.menton - M.y.cabeza;
  const ancho = limitar(rostro.ancho ?? 1, 0.85, 1.2);
  const hw = headH * (M.u < 0.5 ? 0.40 : 0.365) * ancho;
  const top = M.y.cabeza;
  const cx = M.cx;
  const P = (xu, yu) => [cx + xu * hw, top + yu * headH];
  /** Contorno cerrado a partir de la mitad derecha en unidades; el primer y último punto van sobre el eje. */
  const E = (mitad) => suavizar(espejo(mitad.map((p) => [p[0] * hw, top + p[1] * headH, p[2]]), cx));
  const C = (xu, yu, rx, ry) => { // elipse (en unidades)
    const [x, y] = P(xu, yu), a = rx * hw, b = ry * headH;
    return suavizar([[x + a, y], [x, y + b], [x - a, y], [x, y - b]]);
  };
  return { M, P, E, C, hw, headH, top, cx, rostro };
}

const MANDIBULAS = {
  ovalada: [[0.74, 0.82], [0.44, 0.96]],
  cuadrada: [[0.84, 0.76], [0.66, 0.93], [0.4, 0.99]],
  redonda: [[0.84, 0.78], [0.58, 0.95]],
  afilada: [[0.64, 0.82], [0.34, 0.97]],
};
export const MANDIBULAS_ID = Object.keys(MANDIBULAS);

export function trazoCabeza(G) {
  const mand = MANDIBULAS[G.rostro?.mandibula] ?? MANDIBULAS.ovalada;
  return G.E([[0, 0], [0.55, 0.025], [0.9, 0.12], [1.0, 0.3], [0.97, 0.5], [0.92, 0.64], ...mand, [0, 1.0]]);
}

export function trazoOrejas(G) {
  const o = (s) => {
    const [x, y] = G.P(s * 1.0, 0.52);
    const a = G.hw * 0.17, b = G.headH * 0.115;
    return suavizar([[x + s * a * 1.3, y - b * 0.2], [x + s * a * 0.4, y + b], [x - s * a * 0.2, y + b * 0.4], [x - s * a * 0.2, y - b * 0.8], [x + s * a * 0.7, y - b]]);
  };
  return [o(1), o(-1)];
}

/** Rasgos suaves, solo si se piden: la silueta por defecto no los lleva. */
export function rasgosRostro(G, piel) {
  const osc = oscurecer(piel, 0.42), suave = oscurecer(piel, 0.18);
  const f = [];
  for (const s of [-1, 1]) {
    f.push(S(G.C(s * 0.42, 0.43, 0.15, 0.028), osc, { op: 0.8 }));
    f.push(S(suavizar([G.P(s * 0.62, 0.36), G.P(s * 0.4, 0.34), G.P(s * 0.2, 0.355)], false), 'none', { stroke: oscurecer(piel, 0.5), sw: 1.6, op: 0.55, cap: 'round' }));
  }
  f.push(S(suavizar([G.P(-0.06, 0.45), G.P(-0.1, 0.62), G.P(0, 0.67), G.P(0.1, 0.62), G.P(0.06, 0.45)], false), 'none', { stroke: suave, sw: 1.6, op: 0.7, cap: 'round' }));
  f.push(S(suavizar([G.P(-0.2, 0.79), G.P(0, 0.815), G.P(0.2, 0.79)], false), 'none', { stroke: oscurecer(piel, 0.45), sw: 1.8, op: 0.75, cap: 'round' }));
  return f;
}

// ---------- Cabello ----------

/** Casquete que cubre el cráneo; `lat` es hasta dónde baja por los lados y `frente` dónde queda la línea del pelo. */
function casquete(G, { vol = 0.06, frente = 0.17, lat = 0.34, ancho = 0 } = {}) {
  return G.E([
    [0, -vol * 1.3], [0.55 + ancho * 0.4, -vol * 1.0], [0.95 + ancho * 0.8, 0.07 - vol * 0.4],
    [1.03 + vol + ancho, 0.27], [1.0 + vol * 0.6 + ancho, lat],
    [0.88 + ancho * 0.3, lat - 0.08], [0.8, 0.2], [0.5, frente - 0.01], [0.25, frente + 0.015], [0, frente + 0.02],
  ]);
}

function mechonesLado(G, s, { largo = 1.9, ancho = 0.32, salida = 1.08, onda = 0.04 } = {}) {
  const pts = [
    G.P(s * (0.96), 0.14), G.P(s * (salida + 0.05), 0.45), G.P(s * (salida + onda), 0.9),
    G.P(s * (salida - 0.05 + onda), largo * 0.8), G.P(s * (salida - ancho), largo),
    G.P(s * (0.62), largo * 0.8), G.P(s * 0.9, 0.55),
  ];
  return suavizar(pts, true);
}

function rastas(G, color, { n = 9, largo = 1.7 } = {}) {
  const out = [];
  for (let i = 0; i < n; i++) {
    const t = (i / (n - 1)) * 2 - 1;
    const x0 = t * 0.95, x1 = t * 1.15 + (t < 0 ? -0.05 : 0.05);
    const y0 = 0.1 + Math.abs(t) * 0.1;
    const pts = [G.P(x0, y0), G.P(x1, 0.5), G.P(x1 * 1.04 + (i % 2 ? 0.03 : -0.03), (0.5 + largo) / 2), G.P(x1 * 0.95, largo + (i % 3) * 0.05)];
    out.push(S(suavizar(pts, false), 'none', { stroke: color, sw: Math.max(2, G.hw * 0.13), cap: 'round' }));
  }
  return out;
}

export const PEINADOS = {
  calvo: () => ({ atras: [], frente: [] }),
  rapado: (G, c) => ({ atras: [], frente: [S(casquete(G, { vol: 0.012, frente: 0.15, lat: 0.3 }), c, { op: 0.42 })] }),
  corto: (G, c) => ({ atras: [], frente: [S(casquete(G, { vol: 0.05 }), c)] }),
  'corto-raya': (G, c) => ({
    atras: [], frente: [S(casquete(G, { vol: 0.07, frente: 0.19 }), c),
      S(suavizar([G.P(-0.5, 0.02), G.P(-0.35, 0.1), G.P(-0.2, 0.19)], false), 'none', { stroke: oscurecer(c, 0.45), sw: 1.8, op: 0.8, cap: 'round' })],
  }),
  flequillo: (G, c) => ({
    atras: [S(G.E([[0, -0.08], [0.9, 0.0], [1.12, 0.3], [1.1, 0.5], [0.9, 0.6], [0, 0.6]]), c)],
    frente: [S(G.E([[0, -0.08], [0.6, -0.06], [1.0, 0.08], [1.08, 0.3], [1.05, 0.44], [0.85, 0.36], [0.6, 0.26], [0, 0.25]]), c)],
  }),
  romano: (G, c) => ({
    atras: [], frente: [S(G.E([[0, -0.07], [0.6, -0.05], [1.0, 0.1], [1.05, 0.3], [0.95, 0.36], [0.8, 0.22], [0.5, 0.2], [0.2, 0.17], [0, 0.2]]), c)],
  }),
  'melena-media': (G, c) => ({
    atras: [S(mechonesLado(G, 1, { largo: 1.15, ancho: 0.3 }), c), S(mechonesLado(G, -1, { largo: 1.15, ancho: 0.3 }), c), S(G.E([[0, 0], [1, 0.4], [0.9, 1.0], [0, 1.05]]), c)],
    frente: [S(casquete(G, { vol: 0.08, frente: 0.2, lat: 0.4 }), c)],
  }),
  'largo-liso': (G, c) => ({
    atras: [S(G.E([[0, -0.05], [0.95, 0.0], [1.15, 0.5], [1.22, 1.3], [1.2, 2.0], [0, 2.05]]), c)],
    frente: [S(casquete(G, { vol: 0.07, frente: 0.2, lat: 0.42 }), c),
      S(mechonesLado(G, 1, { largo: 2.0, ancho: 0.42, salida: 1.03 }), c), S(mechonesLado(G, -1, { largo: 2.0, ancho: 0.42, salida: 1.03 }), c)],
  }),
  'largo-ondulado': (G, c) => ({
    atras: [S(G.E([[0, -0.05], [0.95, 0.0], [1.2, 0.5], [1.33, 1.3], [1.28, 2.1], [0.6, 2.28], [0, 2.2]]), c)],
    frente: [S(casquete(G, { vol: 0.09, frente: 0.2, lat: 0.42 }), c),
      S(mechonesLado(G, 1, { largo: 2.05, ancho: 0.5, salida: 1.1, onda: 0.12 }), c), S(mechonesLado(G, -1, { largo: 2.05, ancho: 0.5, salida: 1.1, onda: 0.12 }), c)],
  }),
  bob: (G, c) => ({
    atras: [S(G.E([[0, -0.05], [0.95, 0.0], [1.15, 0.4], [1.17, 0.9], [0.9, 1.08], [0, 1.1]]), c)],
    frente: [S(casquete(G, { vol: 0.07, frente: 0.2, lat: 0.45 }), c),
      S(mechonesLado(G, 1, { largo: 1.02, ancho: 0.35, salida: 1.06 }), c), S(mechonesLado(G, -1, { largo: 1.02, ancho: 0.35, salida: 1.06 }), c)],
  }),
  'trenza-una': (G, c) => {
    const [x, y] = G.P(0.95, 0.9);
    const seg = [];
    for (let i = 0; i < 9; i++) seg.push(S(suavizar([[x + G.hw * 0.0, y + i * G.headH * 0.2 + G.headH * 0.55], [x + G.hw * 0.16, y + i * G.headH * 0.2 + G.headH * 0.65], [x, y + i * G.headH * 0.2 + G.headH * 0.75], [x - G.hw * 0.16, y + i * G.headH * 0.2 + G.headH * 0.65]]), c, { stroke: oscurecer(c, 0.35), sw: 1 }));
    return { atras: [], frente: [S(casquete(G, { vol: 0.06, frente: 0.2, lat: 0.45 }), c), ...seg] };
  },
  'trenzas-dos': (G, c) => {
    const seg = [];
    for (const s of [-1, 1]) {
      const [x, y] = G.P(s * 1.12, 0.8);
      for (let i = 0; i < 10; i++) {
        const yy = y + i * G.headH * 0.2;
        seg.push(S(suavizar([[x, yy], [x + G.hw * 0.16, yy + G.headH * 0.1], [x, yy + G.headH * 0.22], [x - G.hw * 0.16, yy + G.headH * 0.1]]), c, { stroke: oscurecer(c, 0.35), sw: 1 }));
      }
    }
    return { atras: [], frente: [S(casquete(G, { vol: 0.07, frente: 0.2, lat: 0.5 }), c), S(mechonesLado(G, 1, { largo: 0.85, ancho: 0.2, salida: 1.05 }), c), S(mechonesLado(G, -1, { largo: 0.85, ancho: 0.2, salida: 1.05 }), c), ...seg] };
  },
  'mono-alto': (G, c) => ({
    atras: [S(G.C(0, -0.2, 0.34, 0.2), c)],
    frente: [S(casquete(G, { vol: 0.05, frente: 0.15, lat: 0.3 }), c)],
  }),
  'mono-chonmage': (G, c) => ({
    atras: [S(suavizar([G.P(-0.1, -0.02), G.P(0.5, -0.18), G.P(0.75, -0.3), G.P(0.45, -0.25), G.P(0.1, -0.08)]), c)],
    frente: [S(casquete(G, { vol: 0.02, frente: 0.1, lat: 0.3 }), c)],
  }),
  recogido: (G, c) => ({
    atras: [S(G.C(0, -0.1, 0.45, 0.2), c)],
    frente: [S(casquete(G, { vol: 0.09, frente: 0.19, lat: 0.5, ancho: 0.04 }), c)],
  }),
  afro: (G, c) => ({
    atras: [S(G.E([[0, -0.55], [0.8, -0.45], [1.5, -0.05], [1.65, 0.4], [1.45, 0.8], [0, 0.75]]), c)],
    frente: [S(G.E([[0, -0.46], [0.75, -0.38], [1.3, -0.04], [1.35, 0.32], [1.1, 0.35], [0.95, 0.15], [0.6, 0.0], [0, 0.0]]), c)],
  }),
  rizos: (G, c) => {
    const bolas = [];
    const pos = [[0, -0.1, 0.4], [-0.55, -0.02, 0.38], [0.55, -0.02, 0.38], [-0.95, 0.12, 0.34], [0.95, 0.12, 0.34], [-1.12, 0.38, 0.28], [1.12, 0.38, 0.28], [-0.3, 0.12, 0.3], [0.3, 0.12, 0.3]];
    for (const [x, y, r] of pos) bolas.push(S(G.C(x, y, r * 0.55, r * 0.5), c));
    return { atras: [S(casquete(G, { vol: 0.12, frente: 0.14, lat: 0.45, ancho: 0.1 }), c)], frente: bolas };
  },
  rastas: (G, c) => ({ atras: [], frente: [S(casquete(G, { vol: 0.06, frente: 0.15, lat: 0.35 }), c), ...rastas(G, c)] }),
  'corona-trenzas': (G, c) => ({
    atras: [],
    frente: [S(casquete(G, { vol: 0.05, frente: 0.19, lat: 0.35 }), c),
      S(suavizar([G.P(-1.0, 0.2), G.P(-0.6, 0.03), G.P(0, -0.02), G.P(0.6, 0.03), G.P(1.0, 0.2)], false), 'none', { stroke: c, sw: G.headH * 0.14, cap: 'round' }),
      S(suavizar([G.P(-1.0, 0.2), G.P(-0.6, 0.03), G.P(0, -0.02), G.P(0.6, 0.03), G.P(1.0, 0.2)], false), 'none', { stroke: oscurecer(c, 0.3), sw: 1, dash: '3 5', cap: 'round' })],
  }),
  coletas: (G, c) => {
    const man = (s) => S(G.E([[0, 0], [0.34, 0.2], [0.3, 0.9], [0, 1.0]]).replace(/^/, ''), c);
    const bolsa = (s) => { const [x, y] = G.P(s * 1.3, 0.55); return S(suavizar([[x, y - G.headH * 0.12], [x + s * G.hw * 0.3, y + G.headH * 0.35], [x + s * G.hw * 0.12, y + G.headH * 1.05], [x - s * G.hw * 0.2, y + G.headH * 0.5]]), c); };
    return { atras: [], frente: [S(casquete(G, { vol: 0.06, frente: 0.19, lat: 0.4 }), c), bolsa(1), bolsa(-1)] };
  },
  tonsura: (G, c) => ({
    atras: [],
    frente: [S(G.E([[0, 0.12], [0.8, 0.11], [1.04, 0.3], [1.0, 0.4], [0.82, 0.3], [0.6, 0.16], [0.0, 0.14]]), c)],
  }),
  'peluca-egipcia': (G, c) => ({
    atras: [S(G.E([[0, -0.06], [0.95, 0.0], [1.22, 0.4], [1.28, 1.2], [1.3, 1.5], [0, 1.55]]), c)],
    frente: [S(casquete(G, { vol: 0.08, frente: 0.2, lat: 0.4 }), c),
      S(mechonesLado(G, 1, { largo: 1.5, ancho: 0.45, salida: 1.12 }), c), S(mechonesLado(G, -1, { largo: 1.5, ancho: 0.45, salida: 1.12 }), c)],
  }),
  'peluca-barroca': (G, c) => {
    const rizos = [];
    for (const s of [-1, 1]) for (let i = 0; i < 5; i++) rizos.push(S(G.C(s * (1.15 + (i % 2) * 0.08), 0.35 + i * 0.28, 0.3, 0.17), c, { stroke: oscurecer(c, 0.25), sw: 1 }));
    return { atras: [S(G.E([[0, -0.1], [1, 0], [1.45, 0.8], [1.5, 1.7], [0, 1.8]]), c)], frente: [S(casquete(G, { vol: 0.14, frente: 0.15, lat: 0.4, ancho: 0.06 }), c), ...rizos] };
  },
};
export const PEINADOS_ID = Object.keys(PEINADOS);

// ---------- Barbas ----------

export const BARBAS = {
  ninguna: () => [],
  bigote: (G, c) => [S(suavizar([G.P(-0.42, 0.74), G.P(-0.15, 0.7), G.P(0, 0.72), G.P(0.15, 0.7), G.P(0.42, 0.74), G.P(0.2, 0.775), G.P(0, 0.755), G.P(-0.2, 0.775)]), c)],
  perilla: (G, c) => [S(G.E([[0, 0.83], [0.2, 0.84], [0.28, 0.93], [0.15, 1.04], [0, 1.08]]), c)],
  candado: (G, c) => [S(suavizar([G.P(-0.4, 0.74), G.P(0, 0.71), G.P(0.4, 0.74), G.P(0.3, 0.8), G.P(0, 0.77), G.P(-0.3, 0.8)]), c), S(G.E([[0, 0.83], [0.2, 0.84], [0.28, 0.93], [0.15, 1.04], [0, 1.08]]), c)],
  patillas: (G, c) => [1, -1].map((s) => S(suavizar([G.P(s * 0.97, 0.25), G.P(s * 0.9, 0.5), G.P(s * 0.8, 0.7), G.P(s * 0.7, 0.68), G.P(s * 0.86, 0.45), G.P(s * 0.88, 0.25)]), c)),
  'barba-corta': (G, c) => [S(G.E([[0, 0.7], [0.55, 0.66], [0.9, 0.52], [0.97, 0.55], [0.9, 0.7], [0.72, 0.86], [0.42, 1.0], [0, 1.03]]).replace(/^/, ''), c),
    S(suavizar([G.P(-0.35, 0.745), G.P(0, 0.72), G.P(0.35, 0.745)], false), 'none', { stroke: oscurecer(c, 0.2), sw: 1.5, op: 0.5, cap: 'round' })],
  'barba-larga': (G, c) => [S(G.E([[0, 0.66], [0.5, 0.62], [0.92, 0.5], [1.0, 0.6], [0.95, 0.85], [0.8, 1.2], [0.5, 1.65], [0, 1.9]]), c)],
  'barba-completa': (G, c) => [S(G.E([[0, 0.68], [0.55, 0.64], [0.92, 0.5], [1.0, 0.6], [0.94, 0.85], [0.7, 1.12], [0.35, 1.3], [0, 1.35]]), c)],
};
export const BARBAS_ID = Object.keys(BARBAS);

// ---------- Tocados ----------
// colores: { principal, secundario, detalle }

const ala = (G, rx, y, ry = 0.08) => G.C(0, y, rx, ry);

export const TOCADOS = {
  ninguno: () => ({ atras: [], frente: [] }),
  'sombrero-ala-ancha': (G, k) => ({ atras: [], frente: [
    S(ala(G, 1.9, 0.14, 0.13), k.principal), S(G.E([[0, -0.46], [0.7, -0.44], [0.95, 0.04], [0.97, 0.14], [0, 0.17]]), k.principal),
    S(G.E([[0, 0.05], [0.96, 0.03], [0.98, 0.14], [0, 0.16]]), k.secundario) ] }),
  'sombrero-copa': (G, k) => ({ atras: [], frente: [
    S(ala(G, 1.35, 0.1, 0.07), k.principal), S(G.E([[0, -0.85], [0.78, -0.85], [0.84, 0.1], [0, 0.12]]), k.principal),
    S(G.E([[0, -0.06], [0.84, -0.04], [0.86, 0.08], [0, 0.1]]), k.secundario) ] }),
  bombin: (G, k) => ({ atras: [], frente: [
    S(ala(G, 1.25, 0.1, 0.08), k.principal), S(G.E([[0, -0.46], [0.7, -0.4], [0.9, -0.1], [0.92, 0.09], [0, 0.11]]), k.principal),
    S(G.E([[0, 0.0], [0.91, 0.0], [0.92, 0.09], [0, 0.11]]), k.secundario) ] }),
  boina: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.3], [0.8, -0.28], [1.3, -0.05], [1.2, 0.17], [0.7, 0.17], [0, 0.15]]), k.principal),
    S(G.C(0, -0.3, 0.08, 0.03), k.secundario)] }),
  'gorra-plana': (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.2], [0.8, -0.2], [1.25, 0.0], [1.2, 0.18], [0, 0.14]]), k.principal),
    S(G.E([[0, 0.1], [0.9, 0.12], [0.95, 0.2], [0, 0.18]]), k.secundario)] }),
  turbante: (G, k) => {
    const caps = [];
    for (let i = 0; i < 5; i++) caps.push(S(suavizar([G.P(-1.15 + i * 0.02, 0.28 - i * 0.12), G.P(-0.5, 0.08 - i * 0.13), G.P(0.5, 0.08 - i * 0.13), G.P(1.15 - i * 0.02, 0.28 - i * 0.12), G.P(0.5, 0.22 - i * 0.12), G.P(-0.5, 0.22 - i * 0.12)]), i % 2 ? k.secundario : k.principal, { stroke: oscurecer(k.principal, 0.3), sw: 1 }));
    return { atras: [], frente: [S(G.E([[0, -0.6], [0.9, -0.5], [1.3, -0.1], [1.25, 0.3], [0, 0.3]]), k.principal), ...caps.reverse()] };
  },
  fez: (G, k) => ({ atras: [], frente: [S(G.E([[0, -0.5], [0.62, -0.48], [0.78, 0.15], [0, 0.17]]), k.principal), S(suavizar([G.P(0, -0.5), G.P(0.3, -0.35), G.P(0.5, 0.0)], false), 'none', { stroke: k.detalle, sw: 3, cap: 'round' })] }),
  kufi: (G, k) => ({ atras: [], frente: [S(G.E([[0, -0.15], [0.7, -0.1], [1.0, 0.1], [0.96, 0.2], [0, 0.17]]), k.principal)] }),
  'gorro-lana': (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.45], [0.5, -0.4], [1.0, -0.05], [1.1, 0.22], [0, 0.22]]), k.principal),
    ...[1, -1].map((s) => S(suavizar([G.P(s * 1.0, 0.25), G.P(s * 1.1, 0.55), G.P(s * 1.0, 0.95), G.P(s * 0.88, 0.58)]), k.principal)),
    S(G.E([[0, 0.1], [1.06, 0.1], [1.1, 0.24], [0, 0.26]]), k.secundario),
    S(G.C(0, -0.48, 0.08, 0.06), k.detalle)] }),
  'gorro-frigio': (G, k) => ({ atras: [], frente: [S(suavizar([G.P(-1.05, 0.25), G.P(-0.9, -0.15), G.P(-0.2, -0.45), G.P(0.5, -0.6), G.P(0.7, -0.45), G.P(0.35, -0.3), G.P(0.95, -0.1), G.P(1.05, 0.25), G.P(0, 0.2)]), k.principal)] }),
  capucha: (G, k) => ({
    atras: [S(G.E([[0, -0.4], [0.9, -0.3], [1.5, 0.3], [1.55, 1.1], [1.7, 1.6], [0, 1.7]]), k.principal)],
    frente: [S(suavizar([G.P(-1.55, 1.3), G.P(-1.4, 0.2), G.P(-0.9, -0.3), G.P(0, -0.42), G.P(0.9, -0.3), G.P(1.4, 0.2), G.P(1.55, 1.3), G.P(1.2, 0.6), G.P(1.1, 0.2), G.P(0.6, 0.0), G.P(0, -0.02), G.P(-0.6, 0.0), G.P(-1.1, 0.2), G.P(-1.2, 0.6)]), k.principal)],
  }),
  velo: (G, k) => ({
    atras: [S(G.E([[0, -0.2], [0.9, -0.1], [1.4, 0.4], [1.6, 1.6], [1.8, 2.6], [0, 2.7]]), k.principal, { op: 0.96 })],
    frente: [S(suavizar([G.P(-1.2, 0.5), G.P(-1.05, 0.0), G.P(-0.5, -0.17), G.P(0, -0.2), G.P(0.5, -0.17), G.P(1.05, 0.0), G.P(1.2, 0.5), G.P(1.0, 0.25), G.P(0.6, 0.12), G.P(0, 0.1), G.P(-0.6, 0.12), G.P(-1.0, 0.25)]), k.principal),
      S(suavizar([G.P(-1.4, 2.5), G.P(-1.1, 1.0), G.P(-1.05, 0.6)], false), 'none', { stroke: k.secundario, sw: 2, op: 0.6 })],
  }),
  panuelo: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.15], [0.9, -0.08], [1.15, 0.3], [1.1, 0.6], [0.95, 0.5], [0.8, 0.25], [0.45, 0.14], [0, 0.12]]), k.principal),
    S(suavizar([G.P(-1.0, 0.18), G.P(-0.4, 0.04), G.P(0.4, 0.04), G.P(1.0, 0.18)], false), 'none', { stroke: k.secundario, sw: 3, op: 0.6 })] }),
  corona: (G, k) => {
    const pts = [];
    for (let i = 0; i < 5; i++) { const x = -0.8 + i * 0.4; pts.push(G.P(x, -0.3), G.P(x + 0.2, -0.05)); }
    return { atras: [], frente: [S(suavizar([G.P(-0.9, 0.12), ...pts, G.P(0.9, 0.12), G.P(0.9, 0.12), G.P(0.6, 0.15), G.P(0, 0.16), G.P(-0.6, 0.15)].map((p, i, a) => (i > 0 && i < a.length - 4 ? [p[0], p[1], true] : p))), k.principal, { stroke: oscurecer(k.principal, 0.35), sw: 1 }),
      S(G.C(0, -0.02, 0.07, 0.05), k.detalle)] };
  },
  diadema: (G, k) => ({ atras: [], frente: [S(suavizar([G.P(-1.0, 0.24), G.P(-0.5, 0.1), G.P(0, 0.07), G.P(0.5, 0.1), G.P(1.0, 0.24)], false), 'none', { stroke: k.principal, sw: G.headH * 0.06, cap: 'round' }), S(G.C(0, 0.07, 0.07, 0.04), k.detalle)] }),
  'tocado-plumas': (G, k) => {
    const f = [];
    for (let i = 0; i < 13; i++) {
      const t = (i / 12) * 2 - 1, ang = t * 1.15;
      const [bx, by] = G.P(t * 0.7, 0.04);
      const L = G.headH * (1.0 + 0.2 * Math.cos(t * 1.4));
      const ex = bx + Math.sin(ang) * L, ey = by - Math.cos(ang) * L;
      f.push(S(suavizar([[bx, by], [(bx + ex) / 2 + 6 * Math.cos(ang), (by + ey) / 2 + 6 * Math.sin(ang)], [ex, ey], [(bx + ex) / 2 - 6 * Math.cos(ang), (by + ey) / 2 - 6 * Math.sin(ang)]]), i % 2 ? k.secundario : k.principal, { stroke: oscurecer(k.principal, 0.3), sw: 1 }));
    }
    return { atras: f, frente: [S(suavizar([G.P(-1.05, 0.2), G.P(-0.5, 0.05), G.P(0.5, 0.05), G.P(1.05, 0.2), G.P(0.5, 0.14), G.P(-0.5, 0.14)]), k.detalle)] };
  },
  'casco-romano': (G, k) => ({
    atras: [S(suavizar([G.P(-0.05, -0.5), G.P(0, -0.7), G.P(0.05, -0.5)]), k.secundario), S(G.E([[0, -0.62], [0.12, -0.6], [0.14, -0.3], [0, -0.28]]), k.secundario)],
    frente: [S(G.E([[0, -0.22], [0.6, -0.2], [1.0, 0.0], [1.12, 0.4], [1.05, 0.7], [0.95, 0.55], [0.85, 0.2], [0, 0.18]]), k.principal),
      S(G.E([[0, 0.1], [0.95, 0.1], [0.97, 0.18], [0, 0.2]]), k.detalle)],
  }),
  'casco-nasal': (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.3], [0.6, -0.26], [1.0, 0.0], [1.06, 0.3], [0.98, 0.4], [0.82, 0.22], [0, 0.2]]), k.principal),
    S(G.E([[0, 0.12], [0.06, 0.12], [0.1, 0.6], [0, 0.66]]), k.principal)] }),
  'sombrero-conico': (G, k) => ({ atras: [], frente: [
    S(suavizar([G.P(-1.9, 0.25), G.P(-0.5, -0.1), G.P(0, -0.85), G.P(0.5, -0.1), G.P(1.9, 0.25), G.P(0, 0.1)]), k.principal, { stroke: oscurecer(k.principal, 0.3), sw: 1 }),
    S(suavizar([G.P(-0.7, 0.03), G.P(0, 0.12), G.P(0.7, 0.03)], false), 'none', { stroke: k.secundario, sw: 2 })] }),
  tricornio: (G, k) => ({ atras: [], frente: [
    S(suavizar([G.P(-1.8, 0.05), G.P(-0.9, -0.35), G.P(0, -0.1), G.P(0.9, -0.35), G.P(1.8, 0.05), G.P(1.0, 0.15), G.P(0, 0.16), G.P(-1.0, 0.15)].map((p, i) => (i === 0 || i === 4 || i === 2 ? [p[0], p[1], true] : p))), k.principal),
    S(suavizar([G.P(-1.7, 0.06), G.P(-0.9, -0.34), G.P(0, -0.09), G.P(0.9, -0.34), G.P(1.7, 0.06)], false), 'none', { stroke: k.detalle, sw: 2 })] }),
  bicornio: (G, k) => ({ atras: [], frente: [
    S(suavizar([[...G.P(-2.0, 0.0), true], G.P(-1.0, -0.6), G.P(0, -0.7), G.P(1.0, -0.6), [...G.P(2.0, 0.0), true], G.P(1.0, 0.1), G.P(0, 0.14), G.P(-1.0, 0.1)]), k.principal),
    S(suavizar([G.P(-1.85, 0.0), G.P(-1.0, -0.55), G.P(0, -0.64), G.P(1.0, -0.55), G.P(1.85, 0.0)], false), 'none', { stroke: k.detalle, sw: 2.5 })] }),
  chupalla: (G, k) => ({ atras: [], frente: [
    S(ala(G, 1.8, 0.14, 0.12), k.principal, { stroke: oscurecer(k.principal, 0.25), sw: 1 }),
    S(G.E([[0, -0.38], [0.68, -0.36], [0.82, 0.04], [0.85, 0.14], [0, 0.17]]), k.principal),
    S(G.E([[0, 0.02], [0.84, 0.03], [0.86, 0.13], [0, 0.16]]), k.secundario)] }),
  'sombrero-bombin-andino': (G, k) => ({ atras: [], frente: [
    S(suavizar([G.P(-0.82, 0.1), G.P(-0.78, -0.2), G.P(0, -0.34), G.P(0.78, -0.2), G.P(0.82, 0.1), G.P(0, 0.14)]), k.principal),
    S(G.E([[0, 0.02], [0.82, 0.02], [0.82, 0.1], [0, 0.14]]), k.secundario)] }),
  birrete: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.15], [0.86, -0.1], [0.98, 0.15], [0, 0.18]]), k.principal),
    S(G.C(0, -0.17, 0.07, 0.05), k.detalle)] }),
  kokoshnik: (G, k) => ({ atras: [], frente: [
    S(suavizar([G.P(-1.0, 0.25), G.P(-0.8, -0.1), G.P(-0.35, -0.38), G.P(0, -0.42), G.P(0.35, -0.38), G.P(0.8, -0.1), G.P(1.0, 0.25), G.P(0.5, 0.2), G.P(0, 0.18), G.P(-0.5, 0.2)]), k.principal, { stroke: k.detalle, sw: 2 }),
    ...[-0.5, 0, 0.5].map((x) => S(G.C(x, x ? -0.12 : -0.2, 0.07, 0.05), k.detalle))] }),
  nemes: (G, k) => ({
    atras: [S(G.E([[0, -0.1], [0.95, 0.0], [1.1, 0.6], [1.0, 1.5], [0.72, 1.5], [0, 1.45]]), k.principal)],
    frente: [S(casquete(G, { vol: 0.1, frente: 0.14, lat: 0.5, ancho: 0.1 }), k.principal),
      ...[0.15, 0.05, -0.05].map((o, i) => S(suavizar([G.P(-1.05, 0.05 + i * 0.1), G.P(0, -0.1 + i * 0.1 + o * 0), G.P(1.05, 0.05 + i * 0.1)], false), 'none', { stroke: k.secundario, sw: 3, op: 0.9 }))],
  }),
  trarilonko: (G, k) => ({ atras: [], frente: [
    S(suavizar([G.P(-1.02, 0.28), G.P(-0.5, 0.08), G.P(0, 0.05), G.P(0.5, 0.08), G.P(1.02, 0.28)], false), 'none', { stroke: k.principal, sw: G.headH * 0.08, cap: 'round' }),
    ...[-0.8, -0.4, 0, 0.4, 0.8].map((x) => S(G.C(x, 0.15 + Math.abs(x) * 0.12, 0.07, 0.07), k.secundario, { stroke: oscurecer(k.secundario, 0.3), sw: 0.8 }))] }),
  ushanka: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.4], [0.7, -0.35], [1.2, 0.0], [1.28, 0.3], [1.1, 0.55], [1.0, 0.1], [0.9, 0.2], [0, 0.2]]), k.principal),
    S(G.E([[0, -0.4], [0.7, -0.35], [1.2, 0.0], [1.28, 0.3], [1.1, 0.55], [1.0, 0.1], [0.9, 0.2], [0, 0.2]]), k.secundario, { op: 0.55 })] }),
};
export const TOCADOS_ID = Object.keys(TOCADOS);

/** Atributos de volumen de un cabello para el sombreado: devuelve un color de brillo. */
export const brilloCabello = (c) => aclarar(c, 0.18);
export { mezclar, lerp };
