// Cuerpo de frente: proporciones según edad, sexo y corpulencia, y las piezas de piel.
// Todo se mide en píxeles del lienzo (600 × 1000), con el eje en x = 300 y los pies en y = 980.

import { limitar, lerp, espejo, tubo, suavizar } from './util.js';

export const LIENZO = { ancho: 600, alto: 1000, cx: 300, suelo: 980 };

// Altura media (cm) por edad, para mujer y hombre.
const ALTURA = {
  f: [[2, 86], [5, 108], [8, 127], [11, 143], [14, 156], [17, 160], [20, 161]],
  m: [[2, 87], [5, 109], [8, 128], [11, 143], [14, 160], [17, 171], [20, 174]],
};
// Cuántas cabezas mide el cuerpo, según la edad.
const CABEZAS = [[2, 4.5], [5, 5.7], [8, 6.4], [11, 6.9], [14, 7.2], [18, 7.5]];

function porTabla(tabla, x) {
  if (x <= tabla[0][0]) return tabla[0][1];
  for (let i = 1; i < tabla.length; i++) {
    if (x <= tabla[i][0]) return lerp(tabla[i - 1][1], tabla[i][1], (x - tabla[i - 1][0]) / (tabla[i][0] - tabla[i - 1][0]));
  }
  return tabla[tabla.length - 1][1];
}

// Puntos de referencia verticales (fracción de la altura desde la coronilla): [niño de 2 años, adulto].
const Y = {
  menton: [0.222, 0.133], hombro: [0.30, 0.19], axila: [0.36, 0.245], pecho: [0.385, 0.275],
  cintura: [0.50, 0.385], cadera: [0.535, 0.47], entrepierna: [0.57, 0.50],
  codo: [0.43, 0.352], muneca: [0.54, 0.46], puntaMano: [0.615, 0.55],
  rodilla: [0.77, 0.725], tobillo: [0.95, 0.947],
};
// Semianchos (fracción de la altura): [niño, adulto hombre, adulto mujer].
const A = {
  cuello: [0.032, 0.029, 0.024], hombro: [0.112, 0.132, 0.114], pecho: [0.098, 0.112, 0.104],
  cintura: [0.092, 0.088, 0.075], cadera: [0.090, 0.098, 0.108],
  muslo: [0.045, 0.056, 0.062], rodilla: [0.030, 0.034, 0.034], pantorrilla: [0.028, 0.035, 0.034], tobillo: [0.019, 0.0205, 0.0195],
  brazoSup: [0.022, 0.026, 0.022], codo: [0.018, 0.020, 0.017], muneca: [0.0125, 0.0135, 0.0115],
};

export const CORPULENCIAS = { delgada: 0.86, media: 1, robusta: 1.2, corpulenta: 1.34 };
export const POSTURAS = { firme: 7, relajada: 11, abierta: 22 };

/**
 * Calcula todas las medidas del cuerpo.
 * opc: { sexo: 'f'|'m', edad (años), corpulencia (nombre o número), altura (cm, opcional), postura }
 */
export function medidas(opc = {}) {
  const sexo = opc.sexo === 'f' ? 'f' : 'm';
  const edad = limitar(Number(opc.edad ?? 30), 2, 100);
  const corp = typeof opc.corpulencia === 'number' ? opc.corpulencia : (CORPULENCIAS[opc.corpulencia] ?? 1);
  const cm = limitar(Number(opc.altura ?? porTabla(ALTURA[sexo], Math.min(edad, 20)) * (edad > 60 ? 1 - (edad - 60) * 0.0009 : 1)), 70, 190);
  const H = cm * 4.9;
  const cabezas = porTabla(CABEZAS, Math.min(edad, 18));
  // u = 0 niño pequeño, 1 adulto (según proporción de la cabeza)
  const u = limitar((cabezas - 4.5) / 3, 0, 1);
  const { cx, suelo } = LIENZO;
  const top = suelo - H;
  const y = {};
  for (const k of Object.keys(Y)) y[k] = top + H * lerp(Y[k][0], Y[k][1], u);
  y.menton = top + H / cabezas;
  y.cabeza = top;
  y.suela = suelo;
  y.cuello = y.menton + (y.hombro - y.menton) * 0.12;

  const esF = sexo === 'f' ? 1 : 0;
  const ancho = (k, factor = 1) => {
    const adulto = lerp(A[k][1], A[k][2], esF);
    return H * lerp(A[k][0], adulto, u) * factor;
  };
  // La corpulencia ensancha tronco y piernas más que el cuello y las muñecas; los mayores pierden hombro.
  const mayor = limitar((edad - 55) / 40, 0, 1);
  const c = (extra = 1) => 1 + (corp - 1) * extra;
  const w = {
    cuello: ancho('cuello', c(0.4)),
    hombro: ancho('hombro', c(0.45) * (1 - 0.04 * mayor)),
    pecho: ancho('pecho', c(0.9)),
    cintura: ancho('cintura', c(1.35) * (1 + 0.06 * mayor * (corp > 0.95 ? 1 : 0))),
    cadera: ancho('cadera', c(1.0)),
    muslo: ancho('muslo', c(1.1)),
    rodilla: ancho('rodilla', c(0.6)),
    pantorrilla: ancho('pantorrilla', c(0.8)),
    tobillo: ancho('tobillo', c(0.3)),
    brazoSup: ancho('brazoSup', c(1.0)),
    codo: ancho('codo', c(0.7)),
    muneca: ancho('muneca', c(0.35)),
  };

  const grados = typeof opc.postura === 'number' ? opc.postura : (POSTURAS[opc.postura] ?? POSTURAS.firme);
  const tan = Math.tan((grados * Math.PI) / 180);

  // Brazo derecho de la figura (x positiva). Nace dentro del hombro y cae con la inclinación pedida.
  const hombroX = w.hombro;
  const brazoY0 = y.hombro + H * 0.02;
  const bx = (yy) => hombroX - w.brazoSup * 0.95 + (yy - brazoY0) * tan;
  const brazo = {
    linea: [
      [bx(brazoY0), brazoY0],
      [bx((brazoY0 + y.codo) / 2), (brazoY0 + y.codo) / 2],
      [bx(y.codo), y.codo],
      [bx((y.codo + y.muneca) / 2) + (grados > 9 ? H * 0.004 : 0), (y.codo + y.muneca) / 2],
      [bx(y.muneca) + (grados > 9 ? H * 0.006 : 0), y.muneca],
    ],
    anchos: [w.brazoSup * 1.08, w.brazoSup, w.codo, (w.codo + w.muneca) / 2 * 1.04, w.muneca],
    mano: { x: bx(y.muneca) + (grados > 9 ? H * 0.006 : 0), y0: y.muneca, y1: y.puntaMano },
  };

  // Pierna derecha.
  const sep = (typeof opc.piernas === 'number' ? opc.piernas : 1) * 0.0;
  const piernaX = (yy) => {
    const t = (yy - y.cadera) / (y.tobillo - y.cadera);
    return w.cadera * 0.5 + (H * 0.006) * t + sep;
  };
  const pierna = {
    linea: [
      [piernaX(y.cadera), y.cadera],
      [piernaX(lerp(y.cadera, y.rodilla, 0.5)), lerp(y.cadera, y.rodilla, 0.5)],
      [piernaX(y.rodilla), y.rodilla],
      [piernaX(lerp(y.rodilla, y.tobillo, 0.35)), lerp(y.rodilla, y.tobillo, 0.35)],
      [piernaX(y.tobillo), y.tobillo],
    ],
    anchos: [w.muslo * 1.05, w.muslo * 0.9, w.rodilla, w.pantorrilla, w.tobillo],
    pie: { x: piernaX(y.tobillo) + H * 0.004, y0: y.tobillo, y1: suelo, ancho: H * 0.021 + w.tobillo * 0.7, largo: H * 0.045 },
  };

  return { sexo, edad, corpulencia: corp, cm, H, cx, top, suelo, y, w, grados, tan, brazo, pierna, cabezas, u, hombroX };
}

/** Mitad derecha del contorno del torso (de la base del cuello a la entrepierna), desplazada `d` hacia afuera. */
export function perfilTorso(M, d = 0, { desde = 'cuello', hasta = 'entrepierna' } = {}) {
  const { y, w, H } = M;
  const f = M.sexo === 'f' && M.u > 0.6 ? 1 : 0;
  const pts = [
    [0, y.cuello + (y.hombro - y.cuello) * 0.55, true],
    [w.cuello * 1.08 + d * 0.3, y.hombro - H * 0.016],
    [w.cuello + (w.hombro - w.cuello) * 0.58 + d * 0.5, y.hombro - H * 0.004],
    [w.hombro + d * 0.9, y.hombro + H * 0.014],
    [w.hombro - H * 0.003 + d, y.hombro + H * 0.045],
    [w.pecho + H * 0.003 * f + d, lerp(y.axila, y.pecho, 0.6)],
    [w.pecho + H * 0.006 * f + d, y.pecho + H * 0.012],
    [lerp(w.pecho, w.cintura, 0.6) + d, lerp(y.pecho, y.cintura, 0.6)],
    [w.cintura + d, y.cintura],
    [lerp(w.cintura, w.cadera, 0.7) + d, lerp(y.cintura, y.cadera, 0.6)],
    [w.cadera + d, y.cadera],
    [w.cadera * 0.97 + d, lerp(y.cadera, y.entrepierna, 0.6)],
    [0, y.entrepierna + H * 0.004, true],
  ];
  return pts;
}

/** Contorno completo del torso (para la piel y para ropa ceñida). */
export function trazoTorso(M, d = 0) {
  return suavizar(espejo(perfilTorso(M, d), M.cx));
}

const lado = (pts, s, cx) => pts.map((p) => [cx + s * (p[0]), p[1], p[2]]);

/** Líneas centrales del brazo y de la pierna en el lado `s` (+1 derecha de la figura, -1 izquierda). */
export function brazoLado(M, s) {
  return { linea: lado(M.brazo.linea, s, M.cx), anchos: M.brazo.anchos };
}
export function piernaLado(M, s) {
  return { linea: lado(M.pierna.linea, s, M.cx), anchos: M.pierna.anchos };
}

export function trazoBrazo(M, s, holgura = 0) {
  const b = brazoLado(M, s);
  return suavizar(tubo(b.linea, b.anchos.map((a) => a + holgura)));
}
export function trazoPierna(M, s, holgura = 0) {
  const p = piernaLado(M, s);
  return suavizar(tubo(p.linea, p.anchos.map((a) => a + holgura)));
}

export function trazoMano(M, s) {
  const { x, y0, y1 } = M.brazo.mano;
  const wm = M.w.muneca;
  const xx = M.cx + s * x;
  const lx = s * (y1 - y0) * M.tan * 0.5;
  const pts = [
    [xx - wm * 1.0, y0 - 2],
    [xx - wm * 1.25 + lx * 0.3, y0 + (y1 - y0) * 0.35],
    [xx - wm * 1.1 + lx * 0.7, y0 + (y1 - y0) * 0.75],
    [xx + lx, y1],
    [xx + wm * 1.1 + lx * 0.7, y0 + (y1 - y0) * 0.75],
    [xx + wm * 1.25 + lx * 0.3, y0 + (y1 - y0) * 0.35],
    [xx + wm * 1.0, y0 - 2],
  ];
  return suavizar(pts, true);
}

export function trazoPie(M, s) {
  const { x, y0, y1, ancho, largo } = M.pierna.pie;
  const xx = M.cx + s * x;
  const pts = [
    [xx - ancho * 0.78, y0 - 4],
    [xx - ancho * 0.9, y0 + (y1 - y0) * 0.55],
    [xx - ancho * 1.25 + s * ancho * 0.1, y1 - largo * 0.15],
    [xx + s * ancho * 0.3, y1 + 0.5],
    [xx + ancho * 1.25 + s * ancho * 0.1, y1 - largo * 0.15],
    [xx + ancho * 0.9, y0 + (y1 - y0) * 0.55],
    [xx + ancho * 0.78, y0 - 4],
  ];
  return suavizar(pts, true);
}

export function trazoCuello(M) {
  const { cx, y, w, H } = M;
  const top = y.menton - H * 0.025;
  const pts = [
    [cx - w.cuello, top], [cx + w.cuello, top, true],
    [cx + w.cuello * 1.05, lerp(top, y.hombro, 0.5)],
    [cx + w.cuello * 1.35, y.hombro - H * 0.012],
    [cx, y.hombro + H * 0.012, true],
    [cx - w.cuello * 1.35, y.hombro - H * 0.012],
    [cx - w.cuello * 1.05, lerp(top, y.hombro, 0.5)],
  ];
  return suavizar(pts, true);
}
