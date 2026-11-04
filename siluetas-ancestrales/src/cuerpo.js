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

/** Pose simétrica y rígida (la de las primeras versiones). */
export const POSE_FIRME = Object.freeze({ modo: 'firme', cabeza: 0, inclinacion: 0, carga: 0, abre: 1, giro: 10, brazoLibre: 'abajo', angulos: null, saludo: { codo: 55, antebrazo: 15, mano: 0 } });

const redondear = (v, d = 1) => Math.round(v * 10 ** d) / 10 ** d;

/**
 * Pose casual y distinta cada vez: la cabeza inclinada, el cuerpo apoyado en una pierna, los pies más o menos abiertos,
 * el brazo libre caído, en la cintura o en el bolsillo, y el saludo con otro ángulo de codo, antebrazo y mano.
 * `carga`: -1 o 1 es el lado (de quien mira) que carga el peso; 0, ambos.
 */
export function poseAleatoria(azar) {
  return {
    modo: 'casual',
    cabeza: redondear(azar.entre(-6, 6)),
    inclinacion: redondear(azar.entre(-1.4, 1.4), 2),
    carga: azar.ponderado([[-1, 3], [1, 3], [0, 2]]),
    abre: redondear(azar.entre(0.85, 1.5), 2),
    giro: redondear(azar.entre(6, 20)),
    brazoLibre: azar.ponderado([['abajo', 58], ['cintura', 27], ['bolsillo', 15]]),
    angulos: { 1: redondear(azar.entre(5, 17)), '-1': redondear(azar.entre(5, 17)) },
    saludo: { codo: redondear(azar.entre(40, 62)), antebrazo: redondear(azar.entre(2, 24)), mano: redondear(azar.entre(-14, 14)) },
  };
}

/** Completa una pose parcial con la rígida; sin pose, la rígida. */
export function normalizarPose(spec) {
  if (!spec || spec === 'firme') return POSE_FIRME;
  const o = typeof spec === 'object' ? spec : {};
  return {
    ...POSE_FIRME, ...o,
    angulos: o.angulos ? { 1: o.angulos[1] ?? 8, '-1': o.angulos['-1'] ?? 8 } : null,
    saludo: { ...POSE_FIRME.saludo, ...(o.saludo ?? {}) },
  };
}

/**
 * Calcula todas las medidas del cuerpo.
 * opc: { sexo: 'f'|'m', edad (años), corpulencia (nombre o número), altura (cm, opcional), postura }
 */
export function medidas(opc = {}) {
  const sexo = opc.sexo === 'f' ? 'f' : 'm';
  const edad = limitar(Number(opc.edad ?? 30), 2, 100);
  const corp = typeof opc.corpulencia === 'number' ? opc.corpulencia : (CORPULENCIAS[opc.corpulencia] ?? 1);
  const escalaAltura = limitar(Number(opc.escalaAltura ?? 1), 0.8, 1.2);
  const cm = limitar(Number(opc.altura ?? porTabla(ALTURA[sexo], Math.min(edad, 20)) * escalaAltura * (edad > 60 ? 1 - (edad - 60) * 0.0009 : 1)), 70, 190);
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

  const grados0 = typeof opc.postura === 'number' ? opc.postura : (POSTURAS[opc.postura] ?? POSTURAS.firme);
  const pose = normalizarPose(opc.pose);

  // Brazos. Las coordenadas son relativas al eje (x positiva hacia afuera); `lado()` las espeja según el lado.
  const brazoY0 = y.hombro + H * 0.02;
  const S0 = [w.hombro - w.brazoSup * 0.95, brazoY0];
  const anchosBrazo = [w.brazoSup * 1.08, w.brazoSup, w.codo, ((w.codo + w.muneca) / 2) * 1.04, w.muneca];
  const uBrazo = [brazoY0, (brazoY0 + y.codo) / 2, y.codo, (y.codo + y.muneca) / 2, y.muneca];
  const unit = (v) => { const l = Math.hypot(v[0], v[1]) || 1; return [v[0] / l, v[1] / l]; };
  const mitad = (p, q) => [(p[0] + q[0]) / 2, (p[1] + q[1]) / 2];

  const brazoCaido = (grados) => {
    const tan = Math.tan((grados * Math.PI) / 180);
    const bx = (yy) => S0[0] + (yy - brazoY0) * tan;
    const dd = grados > 9 ? 1 : 0;
    const linea = [
      [bx(uBrazo[0]), uBrazo[0]], [bx(uBrazo[1]), uBrazo[1]], [bx(uBrazo[2]), uBrazo[2]],
      [bx(uBrazo[3]) + dd * H * 0.004, uBrazo[3]], [bx(uBrazo[4]) + dd * H * 0.006, uBrazo[4]],
    ];
    return { tipo: 'caido', linea, anchos: anchosBrazo, u: uBrazo, tan, mano: { x: linea[4][0], y0: y.muneca, y1: y.puntaMano } };
  };
  // Mano en la cintura: codo hacia afuera y muñeca sobre la cadera.
  const brazoCintura = () => {
    const codo = [S0[0] + H * 0.064, lerp(y.axila, y.cintura, 0.5)];
    const mun = [w.cadera * 0.98 + H * 0.004, y.cintura + (y.cadera - y.cintura) * 0.72];
    const m1 = mitad(S0, codo), m2 = mitad(codo, mun);
    return { tipo: 'cintura', linea: [S0, [m1[0] + H * 0.006, m1[1]], codo, m2, mun], anchos: anchosBrazo, u: uBrazo, manoDir: unit([mun[0] - m2[0], mun[1] - m2[1]]) };
  };
  // Mano en el bolsillo: el antebrazo baja hacia la cadera y la mano no se ve.
  const brazoBolsillo = () => {
    const codo = [S0[0] + H * 0.03, lerp(y.axila, y.cintura, 0.75)];
    const mun = [w.cadera * 0.92, y.cadera + H * 0.012];
    const m1 = mitad(S0, codo), m2 = mitad(codo, mun);
    return { tipo: 'bolsillo', linea: [S0, m1, codo, m2, mun], anchos: anchosBrazo, u: uBrazo };
  };
  // Saludo: el codo baja `codo` grados bajo la horizontal y el antebrazo sube inclinado `antebrazo` grados hacia afuera.
  const brazoSaludo = () => {
    const rad = (g) => (g * Math.PI) / 180;
    const Lu = H * 0.16, Lf = H * 0.105;
    const codo = [S0[0] + Lu * Math.cos(rad(pose.saludo.codo)), S0[1] + Lu * Math.sin(rad(pose.saludo.codo))];
    const mun = [codo[0] + Lf * Math.sin(rad(pose.saludo.antebrazo)), codo[1] - Lf * Math.cos(rad(pose.saludo.antebrazo))];
    return { tipo: 'saludo', linea: [S0, mitad(S0, codo), codo, mitad(codo, mun), mun], anchos: anchosBrazo, u: uBrazo, manoRot: pose.saludo.mano };
  };

  // Saludo: `saludo` 'derecha' levanta la mano derecha de la persona, que de frente queda a la izquierda de quien mira.
  const saludoLado = opc.saludo === 'derecha' ? -1 : opc.saludo === 'izquierda' ? 1 : 0;
  const ladoLibre = saludoLado ? -saludoLado : 1; // el brazo que lleva la pose especial (cintura, bolsillo)
  const anguloDe = (s) => (pose.angulos ? pose.angulos[s] : grados0);
  const brazos = {};
  for (const s of [1, -1]) {
    if (s === saludoLado) brazos[s] = brazoSaludo();
    else if (s === ladoLibre && pose.brazoLibre === 'cintura') brazos[s] = brazoCintura();
    else if (s === ladoLibre && pose.brazoLibre === 'bolsillo') brazos[s] = brazoBolsillo();
    else brazos[s] = brazoCaido(anguloDe(s));
  }

  // Piernas. La que no carga el peso se abre un poco más y gira más el pie; `abre` ensancha la postura.
  const baseX = (yy) => w.cadera * 0.5 + H * 0.006 * ((yy - y.cadera) / (y.tobillo - y.cadera));
  const piernaDe = (s) => {
    const libre = pose.carga !== 0 && s !== pose.carga ? 1 : 0;
    const abre = (pose.abre - 1) * H * 0.02;
    const dxTob = abre + libre * H * 0.016, dxRod = abre * 0.5 + libre * H * 0.008;
    const yMuslo = lerp(y.cadera, y.rodilla, 0.5), yPant = lerp(y.rodilla, y.tobillo, 0.35);
    return {
      linea: [
        [baseX(y.cadera), y.cadera], [baseX(yMuslo) + dxRod * 0.25, yMuslo], [baseX(y.rodilla) + dxRod, y.rodilla],
        [baseX(yPant) + (dxRod + dxTob) * 0.6, yPant], [baseX(y.tobillo) + dxTob, y.tobillo],
      ],
      anchos: [w.muslo * 1.05, w.muslo * 0.9, w.rodilla, w.pantorrilla, w.tobillo],
      pie: { x: baseX(y.tobillo) + dxTob + H * 0.004, y0: y.tobillo, y1: suelo, ancho: H * 0.021 + w.tobillo * 0.7, largo: H * 0.045, giro: pose.giro * (libre ? 1.35 : 1) },
    };
  };
  const piernas = { 1: piernaDe(1), '-1': piernaDe(-1) };

  return { sexo, edad, corpulencia: corp, cm, H, cx, top, suelo, y, w, grados: grados0, pose, brazos, saludoLado, piernas, cabezas, u, hombroX: w.hombro };
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

/**
 * Línea central del brazo en el lado `s` (+1 a la derecha de quien mira, -1 a su izquierda), con la pose que le toca:
 * caído, en la cintura, en el bolsillo o saludando.
 */
export function brazoLado(M, s) {
  const b = M.brazos[s];
  return { ...b, linea: lado(b.linea, s, M.cx) };
}

/**
 * Punto del brazo (x, y absolutos y semiancho) según el parámetro `uu`, que para el brazo caído es la altura y
 * y para los demás recorre los mismos tramos. Más allá de la muñeca sigue la dirección del antebrazo.
 */
export function brazoEnU(M, s, uu) {
  const { linea: L, anchos: A, u: U } = brazoLado(M, s);
  for (let i = 0; i < L.length - 1; i++) {
    if (uu <= U[i + 1] || i === L.length - 2) {
      const t = limitar((uu - U[i]) / ((U[i + 1] - U[i]) || 1), 0, 1.6);
      return { x: lerp(L[i][0], L[i + 1][0], t), y: lerp(L[i][1], L[i + 1][1], t), w: lerp(A[i], A[i + 1], t) };
    }
  }
  return { x: L[0][0], y: L[0][1], w: A[0] };
}

/** Línea central de la pierna del lado `s` con su pie, en coordenadas absolutas. */
export function piernaLado(M, s) {
  const p = M.piernas[s];
  return { linea: lado(p.linea, s, M.cx), anchos: p.anchos, pie: { ...p.pie, x: M.cx + s * p.pie.x } };
}

export function trazoBrazo(M, s, holgura = 0) {
  const b = brazoLado(M, s);
  return suavizar(tubo(b.linea, b.anchos.map((a) => a + holgura)));
}
export function trazoPierna(M, s, holgura = 0) {
  const p = piernaLado(M, s);
  return suavizar(tubo(p.linea, p.anchos.map((a) => a + holgura)));
}

/** Piezas (rutas) de la mano; vacío si el brazo está en el bolsillo (la mano no se ve). */
export function trazoMano(M, s) {
  const b = M.brazos[s];
  if (b.tipo === 'saludo') return manoSaludo(M, s);
  if (b.tipo === 'bolsillo') return [];
  if (b.tipo === 'cintura') return [manoCerrada(M, s)];
  return [manoCaida(M, s)];
}

/** Mano abierta, con la palma hacia quien mira, al final del antebrazo levantado. */
function manoSaludo(M, s) {
  const b = brazoLado(M, s);
  const L = b.linea;
  const W = L[L.length - 1], P = L[L.length - 2];
  const l = Math.hypot(W[0] - P[0], W[1] - P[1]) || 1;
  let u = [(W[0] - P[0]) / l, (W[1] - P[1]) / l];
  // La mano puede ir girada respecto del antebrazo: positivo la inclina hacia afuera.
  const rot = ((b.manoRot ?? 0) * Math.PI) / 180 * (s > 0 ? -1 : 1);
  u = [u[0] * Math.cos(rot) - u[1] * Math.sin(rot), u[0] * Math.sin(rot) + u[1] * Math.cos(rot)];
  const v = [-u[1], u[0]];
  const wm = M.w.muneca, largo = M.y.puntaMano - M.y.muneca, palma = largo * 0.5, pw = wm * 1.45;
  const ts = -s; // el pulgar queda del lado del cuerpo
  const loc = (a, c) => [W[0] + u[0] * a + v[0] * c, W[1] + u[1] * a + v[1] * c];
  const dir = (ang) => [u[0] * Math.cos(ang) + v[0] * Math.sin(ang) * ts, u[1] * Math.cos(ang) + v[1] * Math.sin(ang) * ts];
  const partes = [suavizar([loc(-3, -wm), loc(palma * 0.45, -pw), loc(palma, -pw * 0.92), loc(palma, pw * 0.92), loc(palma * 0.45, pw), loc(-3, wm)], true)];
  const dedos = [[0.72, 0.9, 0.12], [0.25, 1.0, 0.03], [-0.22, 0.93, -0.06], [-0.68, 0.74, -0.16]];
  const fl = largo * 0.54, wf = pw * 0.27;
  for (const [c, f, ang] of dedos) {
    const base = loc(palma * 0.85, ts * pw * c), d = dir(ang);
    const fin = [base[0] + d[0] * fl * f, base[1] + d[1] * fl * f];
    partes.push(suavizar(tubo([base, fin], [wf, wf * 0.82], 'redonda'), true));
  }
  const bp = loc(palma * 0.25, ts * pw * 0.95), dp = dir(0.9);
  partes.push(suavizar(tubo([bp, [bp[0] + dp[0] * largo * 0.4, bp[1] + dp[1] * largo * 0.4]], [wf * 1.15, wf * 0.9], 'redonda'), true));
  return partes;
}

/** Antebrazo desde el parámetro `uDesde` hasta la muñeca (se dibuja sobre mantos que lo taparían). */
export function trazoAntebrazo(M, s, uDesde) {
  const n = 4, linea = [], anchos = [];
  const uFin = M.brazos[s].u[M.brazos[s].u.length - 1];
  for (let i = 0; i <= n; i++) {
    const q = brazoEnU(M, s, lerp(uDesde, uFin, i / n));
    linea.push([q.x, q.y]); anchos.push(q.w);
  }
  return suavizar(tubo(linea, anchos, 'plana'));
}

/** Mano cerrada apoyada en la cintura. */
function manoCerrada(M, s) {
  const b = brazoLado(M, s);
  const W = b.linea[b.linea.length - 1];
  const d = [b.manoDir[0] * s, b.manoDir[1]];
  const v = [-d[1], d[0]];
  const wm = M.w.muneca, largo = (M.y.puntaMano - M.y.muneca) * 0.62;
  const loc = (a, c) => [W[0] + d[0] * a + v[0] * c, W[1] + d[1] * a + v[1] * c];
  return suavizar([loc(-2, -wm), loc(largo * 0.35, -wm * 1.45), loc(largo * 0.8, -wm * 1.2), loc(largo, 0), loc(largo * 0.8, wm * 1.2), loc(largo * 0.35, wm * 1.45), loc(-2, wm)], true);
}

function manoCaida(M, s) {
  const b = M.brazos[s];
  const { x, y0, y1 } = b.mano;
  const wm = M.w.muneca;
  const xx = M.cx + s * x;
  const lx = s * (y1 - y0) * b.tan * 0.5;
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

/** Pie visto de frente; `giro` abre la punta hacia afuera. */
export function trazoPie(M, s) {
  const { x: xx, y0, y1, ancho, largo, giro } = piernaLado(M, s).pie;
  const punta = s * largo * Math.tan(((giro ?? 10) * Math.PI) / 180);
  const pts = [
    [xx - ancho * 0.78, y0 - 4],
    [xx - ancho * 0.9, y0 + (y1 - y0) * 0.55],
    [xx - ancho * 1.25 + punta, y1 - largo * 0.15],
    [xx + punta * 1.3, y1 + 0.5],
    [xx + ancho * 1.25 + punta, y1 - largo * 0.15],
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
