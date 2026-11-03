// Plantillas de prendas. Cada una recibe las medidas del cuerpo (M), sus parámetros (p), los colores ya
// resueltos (k: principal, secundario, ribete, detalle) y un contexto (ctx.patron) y devuelve:
//   { atras: [...], partes: [...], detalles: [...] }
// `atras` va detrás del cuerpo, `partes` son las piezas que se funden en una sola silueta con contorno y
// `detalles` (trazos, botones, ribetes) se dibujan encima. Las medidas son fracciones de la altura (H).

import { suavizar, espejo, poligono, tubo, lerp, limitar, oscurecer, aclarar } from './util.js';
import { perfilTorso, brazoEnU } from './cuerpo.js';

// ---------- Ayudas ----------

export function yLargo(M, largo) {
  const { y, H } = M;
  if (typeof largo === 'number') return M.top + H * largo;
  switch (largo) {
    case 'pecho': return y.pecho + H * 0.01;
    case 'cintura': return y.cintura + H * 0.005;
    case 'cadera': return y.cadera + H * 0.035;
    case 'muslo-alto': return lerp(y.entrepierna, y.rodilla, 0.2);
    case 'muslo': return lerp(y.entrepierna, y.rodilla, 0.5);
    case 'rodilla': return y.rodilla - H * 0.004;
    case 'bajo-rodilla': return lerp(y.rodilla, y.tobillo, 0.22);
    case 'pantorrilla': return lerp(y.rodilla, y.tobillo, 0.55);
    case 'tobillo': return y.tobillo - H * 0.012;
    case 'suelo': return M.suelo - H * 0.004;
    default: return y.rodilla;
  }
}

/** Semiancho de un perfil (lista [x, y] de la mitad derecha) a la altura yy. */
export function anchoEn(pts, yy) {
  if (yy <= pts[0][1]) return pts[0][0];
  for (let i = 1; i < pts.length; i++) {
    if (yy <= pts[i][1]) {
      const dy = pts[i][1] - pts[i - 1][1] || 1;
      return lerp(pts[i - 1][0], pts[i][0], (yy - pts[i - 1][1]) / dy);
    }
  }
  return pts[pts.length - 1][0];
}

const relleno = (p, k, ctx) =>
  p.patron ? ctx.patron(p.patron.tipo ?? p.patron, k.principal, k[p.patron.rol ?? 'secundario'] ?? k.secundario, p.patron.escala ?? 1) : k.principal;

const parte = (d, fill, k, extra = {}) => ({ d, fill, contorno: oscurecer(k.principal, 0.5), volumen: true, ...extra });
const linea = (d, stroke, sw, op = 1, extra = {}) => ({ d, fill: 'none', stroke, sw, op, cap: 'round', ...extra });
const sombraDe = (c) => oscurecer(c, 0.32);

/** Pliegues verticales entre dos alturas, siguiendo el ancho del perfil. */
function pliegues(M, perfil, yA, yB, n, color, amp = 0.012) {
  const out = [];
  for (let i = 1; i <= n; i++) {
    const f = (i / (n + 1)) * 2 - 1;
    const xa = f * anchoEn(perfil, yA) * 0.92, xb = f * anchoEn(perfil, yB) * 0.96;
    const mx = (xa + xb) / 2 + (i % 2 ? 1 : -1) * M.H * amp;
    out.push(linea(`M${M.cx + xa} ${yA}Q${M.cx + mx} ${(yA + yB) / 2} ${M.cx + xb} ${yB - (i % 2) * M.H * 0.006}`, sombraDe(color), 1.3, 0.38));
  }
  return out;
}

// ---------- Escote, bajo y perfil del cuerpo vestido ----------

function escotePts(M, tipo, prof = 1) {
  const { w, y, H } = M;
  const y0 = y.hombro - H * 0.016, xn = w.cuello * 1.08;
  switch (tipo) {
    case 'v': { const yE = y.hombro + H * (0.04 + 0.09 * prof); return [[0, yE, true], [xn * 0.5, lerp(yE, y0, 0.5)], [xn, y0]]; }
    case 'cuadrado': { const yE = y.hombro + H * (0.022 + 0.03 * prof); return [[0, yE, true], [xn * 0.92, yE, true], [xn * 0.96, y0, true]]; }
    case 'barco': { const xb = w.cuello + (w.hombro - w.cuello) * 0.7; return [[0, y.hombro + H * 0.012], [xb * 0.5, y.hombro + H * 0.006], [xb, y.hombro - H * 0.004]]; }
    case 'alto': { const yE = y.menton + (y.cuello - y.menton) * 0.35; return [[0, yE, true], [w.cuello * 1.03, yE, true], [w.cuello * 1.1, lerp(yE, y0, 0.6)], [xn, y0]]; }
    case 'ojal': { const yE = y.hombro + H * 0.04; return [[0, yE], [xn * 0.5, yE - H * 0.004], [xn * 0.95, lerp(yE, y0, 0.6)], [xn, y0]]; }
    default: { const yE = y.hombro + H * (0.012 + 0.03 * prof); return [[0, yE], [xn * 0.5, yE - H * 0.003], [xn * 0.9, lerp(yE, y0, 0.55)], [xn, y0]]; }
  }
}

function puntosBajo(estilo, xH, yH, H) {
  const base = (a) => [xH, yH + (a ?? 0), true];
  switch (estilo) {
    case 'curvo': return [base(), [xH * 0.5, yH + H * 0.011], [0, yH + H * 0.015, true]];
    case 'ondulado': return [base(), [xH * 0.75, yH + H * 0.012], [xH * 0.5, yH - H * 0.004], [xH * 0.25, yH + H * 0.012], [0, yH + H * 0.002, true]];
    case 'dentado': return [base(), [xH * 0.8, yH + H * 0.022, true], [xH * 0.6, yH, true], [xH * 0.4, yH + H * 0.022, true], [xH * 0.2, yH, true], [0, yH + H * 0.018, true]];
    case 'flecos': return [base(), [0, yH + H * 0.004, true]];
    default: return [base(), [0, yH + H * 0.004, true]];
  }
}

/** Mitad derecha del contorno de una prenda de torso, del escote al bajo. Devuelve también el perfil sin el bajo. */
function perfilVestido(M, o) {
  const { w, y, H } = M;
  const { d = 0, cin = 1, vuelo = 0, yHem, sinMangas = false, escote = 'redondo', prof = 1, bajo = 'recto' } = o;
  const T = perfilTorso(M, 0);
  const E = escotePts(M, escote, prof);
  const pts = E.slice();
  const wd = (i, peso) => d * lerp(1, cin, peso);
  if (sinMangas) {
    const xs = lerp(w.cuello * 1.08, w.hombro, 0.5);
    pts.push([xs, y.hombro - H * 0.006], [xs + H * 0.003, y.hombro + H * 0.03], [w.pecho * 0.9 + d * 0.3, y.axila + H * 0.008]);
  } else if (escote !== 'barco') {
    pts.push([T[2][0] + d * 0.5, T[2][1]]);
    pts.push([T[3][0] + d, T[3][1]]);
    pts.push([T[4][0] + d, T[4][1]]);
  } else {
    pts.push([T[3][0] + d, T[3][1]], [T[4][0] + d, T[4][1]]);
  }
  pts.push([T[6][0] + d, T[6][1]]);
  pts.push([T[7][0] + wd(7, 0.5), T[7][1]]);
  pts.push([T[8][0] + wd(8, 1), T[8][1]]);
  pts.push([T[9][0] + wd(9, 0.5), T[9][1]]);
  pts.push([T[10][0] + d, T[10][1]]);
  if (yHem > y.cadera) {
    for (const t of [0.25, 0.55, 0.8, 1]) {
      pts.push([w.cadera + d + vuelo * H * t ** 1.15, lerp(y.cadera, yHem, t)]);
    }
  }
  // Cortar en el bajo.
  const corte = [];
  for (let i = 0; i < pts.length; i++) {
    if (pts[i][1] < yHem) corte.push(pts[i]);
    else { const a = corte[corte.length - 1] ?? pts[0], b = pts[i]; const t = (yHem - a[1]) / ((b[1] - a[1]) || 1); corte.push([lerp(a[0], b[0], limitar(t, 0, 1)), yHem]); break; }
  }
  if (corte[corte.length - 1][1] < yHem) corte.push([corte[corte.length - 1][0], yHem]);
  const fin = corte[corte.length - 1];
  const sinBajo = corte.slice();
  const perfil = corte.slice(0, -1).concat([[fin[0], fin[1]]]);
  const completo = corte.slice(0, -1).concat(puntosBajo(bajo, fin[0], fin[1], H));
  return { completo, perfil: sinBajo, xBajo: fin[0], yBajo: fin[1], escote: E };
}

// ---------- Mangas ----------

const brazoEnY = brazoEnU;

export function finManga(M, tipo) {
  const { y, H } = M;
  const y0 = y.hombro + H * 0.02;
  switch (tipo) {
    case 'corta': return lerp(y0, y.codo, 0.55);
    case 'tres-cuartos': return lerp(y.codo, y.muneca, 0.5);
    case 'codo': return y.codo + H * 0.012;
    case 'larga-mano': return y.muneca + H * 0.035;
    case 'kimono': return y.muneca + H * 0.045;
    default: return y.muneca - H * 0.006;
  }
}

function mangaForma(M, s, tipo, d, vuelo = 0) {
  const { H, y } = M;
  const yA = y.hombro + H * 0.012, yB = finManga(M, tipo);
  const n = 6, linea = [], anchos = [];
  const ini = brazoEnY(M, s, yA);
  for (let i = 0; i < n; i++) {
    const t = i / (n - 1);
    const q = brazoEnY(M, s, lerp(yA, yB, t));
    let f = 1, desp = 0;
    if (tipo === 'campana') f = 1 + (1.2 + vuelo * 10) * t ** 1.6;
    else if (tipo === 'ancha') f = 1 + 0.7 * t;
    else if (tipo === 'globo') f = 1.5 - 0.5 * Math.min(1, t * 2.2) + (t > 0.8 ? 0.1 : 0);
    else if (tipo === 'kimono') { f = 1.7 + 1.0 * t; desp = H * 0.028 * t; }
    else if (tipo === 'abullonada') f = 1.55 - 0.55 * Math.min(1, t * 1.6);
    // Con el brazo levantado la manga ancha se recoge hacia el codo: menos vuelo.
    if (s === M.saludoLado) f = 1 + (f - 1) * 0.55;
    linea.push([q.x + (s === M.saludoLado ? 0 : s * desp), q.y]);
    anchos.push((q.w + d * (i === 0 ? 1.15 : 1)) * f);
  }
  const plana = tipo !== 'campana' && tipo !== 'kimono' && tipo !== 'ancha' ? 'plana' : 'plana';
  return { pts: tubo(linea, anchos, plana), linea, anchos, fin: { x: linea[n - 1][0], y: linea[n - 1][1], w: anchos[n - 1] }, ini };
}

function mangas(M, tipo, d, k, F, vuelo, puño) {
  const partes = [], detalles = [];
  if (!tipo || tipo === 'sin') return { partes, detalles };
  for (const s of [1, -1]) {
    const m = mangaForma(M, s, tipo, d, vuelo);
    partes.push(parte(suavizar(m.pts), F, k));
    if (puño) {
      const { x, y, w } = m.fin;
      detalles.push(linea(`M${x - w * 0.97} ${y - 1.5}L${x + w * 0.97} ${y - 1.5}`, k.ribete, Math.max(3, M.H * 0.011), 1, { cap: 'butt' }));
    }
  }
  return { partes, detalles };
}

// ---------- Plantillas ----------

function tunica(M, p, k, ctx) {
  const { H, y, cx } = M;
  const d = (p.holgura ?? 0.012) * H;
  const yHem = yLargo(M, p.largo ?? 'rodilla');
  const F = relleno(p, k, ctx);
  const sinM = p.manga === 'sin';
  const per = perfilVestido(M, { d, cin: p.cintura ?? 1, vuelo: p.vuelo ?? 0.03, yHem, sinMangas: sinM, escote: p.escote ?? 'redondo', prof: p.prof ?? 1, bajo: p.bajo ?? 'recto' });
  const partes = [parte(suavizar(espejo(per.completo, cx)), F, k)];
  const m = mangas(M, p.manga ?? 'larga', d * 0.9, k, F, p.vuelo ?? 0.03, p.puños !== false && p.ribete !== false && !!k.ribete && p.puños);
  partes.push(...m.partes);
  const detalles = [...m.detalles];
  const prof = per.perfil;
  const lado = (pts) => pts.map(([x, yy]) => [cx + x, yy]);
  // Ribete del escote.
  if (p.ribete !== false && k.ribete) {
    const E = per.escote.map((q) => [q[0], q[1], q[2]]);
    const neck = suavizar([...E.slice().reverse().map(([x, yy, c]) => [cx - x, yy, c]).slice(0, -1), ...E.map(([x, yy, c]) => [cx + x, yy, c])], false);
    detalles.push(linea(neck, k.ribete, Math.max(3, H * 0.008), 1, { cap: 'butt' }));
  }
  // Ribete del bajo.
  if (p.ribeteBajo) {
    const bh = H * (p.ribeteBajo === true ? 0.025 : p.ribeteBajo);
    const xa = anchoEn(prof, per.yBajo - bh), xb = per.xBajo;
    const pat = p.bandaPatron ? ctx.patron(p.bandaPatron, k.ribete, k.detalle, 0.8) : k.ribete;
    detalles.push({ d: poligono([[cx - xa, per.yBajo - bh], [cx + xa, per.yBajo - bh], [cx + xb, per.yBajo], [cx - xb, per.yBajo]]), fill: pat, op: 1 });
  }
  // Bandas verticales (clavus, rayas de túnica).
  if (p.bandas) {
    const bw = H * (p.bandasAncho ?? 0.012);
    for (const f of p.bandas === 'central' ? [0] : [-0.32, 0.32]) {
      const xs = (yy) => f * anchoEn(prof, yy) * (p.bandas === 'central' ? 1 : 1.35);
      detalles.push(linea(`M${cx + xs(y.hombro + H * 0.02)} ${y.hombro + H * 0.02}L${cx + xs(per.yBajo)} ${per.yBajo - 1}`, k.ribete, bw, 1, { cap: 'butt' }));
    }
  }
  // Cruzada (kimono, hanfu, hanbok, camisón cruzado).
  if (p.cruzada) {
    const sg = p.cruzada === 'izq' ? -1 : 1;
    const a = [cx - sg * M.w.cuello * 1.1, y.hombro - H * 0.012], b = [cx + sg * anchoEn(prof, y.cintura) * 0.55, y.cintura + H * 0.05], c2 = [cx + sg * anchoEn(prof, y.cadera) * 0.85, per.yBajo - 1];
    detalles.push(linea(`M${a[0]} ${a[1]}Q${lerp(a[0], b[0], 0.4)} ${lerp(a[1], b[1], 0.7)} ${b[0]} ${b[1]}L${c2[0]} ${c2[1]}`, k.ribete, Math.max(3, H * 0.008), 1, { cap: 'butt' }));
    detalles.push(linea(`M${a[0]} ${a[1]}Q${lerp(a[0], b[0], 0.3) - sg * 5} ${lerp(a[1], b[1], 0.8)} ${b[0] - sg * 4} ${b[1]}L${c2[0] - sg * 4} ${c2[1]}`, sombraDe(k.principal), 1.4, 0.55));
  }
  // Abertura central con botones.
  if (p.botones) {
    const n = p.botones, y1 = p.escote === 'v' ? y.hombro + H * 0.1 : y.hombro + H * 0.05;
    detalles.push(linea(`M${cx} ${y1}L${cx} ${per.yBajo - 2}`, sombraDe(k.principal), 1.3, 0.6));
    for (let i = 0; i < n; i++) {
      const yy = lerp(y1 + H * 0.02, Math.min(per.yBajo - H * 0.03, y.entrepierna), i / Math.max(1, n - 1));
      detalles.push({ d: suavizar([[cx - 3, yy], [cx, yy + 3], [cx + 3, yy], [cx, yy - 3]]), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 0.8 });
    }
  }
  if (p.pliegues) detalles.push(...pliegues(M, prof.map(([x, yy]) => [x, yy]), y.cintura + H * 0.02, per.yBajo, p.pliegues, k.principal));
  if (p.flecos) detalles.push(...flecos(M, per.yBajo, per.xBajo, k.ribete));
  for (let i = 1; i <= (p.volantes ?? 0); i++) {
    const yy = lerp(y.cadera, per.yBajo, i / (p.volantes + 1));
    const xx = anchoEn(prof, yy);
    detalles.push(linea(`M${cx - xx} ${yy}Q${cx} ${yy + H * 0.02} ${cx + xx} ${yy}`, k.ribete ?? sombraDe(k.principal), Math.max(2, H * 0.006), 0.95));
  }
  if (p.bordadoPecho) {
    const yy = y.pecho + H * 0.02;
    detalles.push({ d: poligono([[cx - M.w.pecho * 0.6, yy], [cx + M.w.pecho * 0.6, yy], [cx + M.w.pecho * 0.6, yy + H * 0.03], [cx - M.w.pecho * 0.6, yy + H * 0.03]]), fill: ctx.patron(p.bordadoPecho, k.ribete, k.detalle, 0.7) });
  }
  return { atras: [], partes, detalles };
}

function flecos(M, yH, xH, color) {
  const out = [];
  const n = Math.max(6, Math.round((xH * 2) / 7));
  for (let i = 0; i < n; i++) {
    const x = M.cx - xH + (i / (n - 1)) * xH * 2;
    out.push(linea(`M${x} ${yH - 1}L${x + (i % 2 ? 1.5 : -1.5)} ${yH + M.H * 0.022}`, color, 1.6, 0.95));
  }
  return out;
}

function pantalon(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const d = (p.holgura ?? 0.004) * H;
  const yHem = yLargo(M, p.largo ?? 'tobillo');
  const F = relleno(p, k, ctx);
  const factor = { ajustado: 1, recto: 1.28, amplio: 1.7, bombacho: 1.55, campana: 1.3 }[p.ancho ?? 'recto'] ?? 1.28;
  const partes = [];
  const T = perfilTorso(M, 0);
  const alto = p.cintura === 'baja' ? y.cintura + H * 0.045 : y.cintura - H * 0.005;
  const half = [[0, alto, true], [w.cintura + d * 1.2, alto, true], [lerp(w.cintura, w.cadera, 0.7) + d, lerp(alto, y.cadera, 0.6)], [w.cadera + d * 1.3, y.cadera], [w.cadera * 0.98 + d, lerp(y.cadera, y.entrepierna, 0.7)], [0, y.entrepierna + H * 0.012, true]];
  partes.push(parte(suavizar(espejo(half, cx)), F, k));
  for (const s of [1, -1]) {
    const Lg = M.pierna;
    const pts = [], ws = [];
    const yy0 = y.cadera, n = 6;
    for (let i = 0; i < n; i++) {
      const t = i / (n - 1);
      const yy = lerp(yy0, yHem, t);
      // Interpolar la pierna en yy.
      let q = { x: Lg.linea[0][0], w: Lg.anchos[0] };
      for (let j = 0; j < Lg.linea.length - 1; j++) {
        if (yy <= Lg.linea[j + 1][1] || j === Lg.linea.length - 2) {
          const tt = limitar((yy - Lg.linea[j][1]) / ((Lg.linea[j + 1][1] - Lg.linea[j][1]) || 1), 0, 1.3);
          q = { x: lerp(Lg.linea[j][0], Lg.linea[j + 1][0], tt), w: lerp(Lg.anchos[j], Lg.anchos[j + 1], tt) };
          break;
        }
      }
      let f = 1 + (factor - 1) * (p.ancho === 'bombacho' ? Math.sin(Math.min(1, t * 1.4) * Math.PI * 0.5) * (t > 0.78 ? 1 - (t - 0.78) * 3.4 : 1) : p.ancho === 'campana' ? t ** 2 : Math.min(1, t * 2));
      if (p.ancho === 'ajustado') f = 1;
      pts.push([cx + s * q.x, yy]);
      ws.push(q.w * f + d);
    }
    partes.push(parte(suavizar(tubo(pts, ws, 'plana')), F, k));
  }
  const detalles = [linea(`M${cx - w.cintura - d} ${alto + 3}L${cx + w.cintura + d} ${alto + 3}`, k.ribete ? k.ribete : sombraDe(k.principal), Math.max(3, H * 0.008), 0.9, { cap: 'butt' }),
    linea(`M${cx} ${alto + 5}L${cx} ${y.entrepierna + H * 0.01}`, sombraDe(k.principal), 1.2, 0.5)];
  if (p.cordon) detalles.push(linea(`M${cx - 4} ${alto + 6}q4 10 -2 18M${cx + 4} ${alto + 6}q-4 10 2 18`, k.detalle, 1.6, 0.9));
  if (p.puños && k.ribete) for (const s of [1, -1]) detalles.push(linea(`M${cx + s * (M.pierna.linea[4][0] - M.pierna.anchos[4] * 1.5)} ${yHem - 2}L${cx + s * (M.pierna.linea[4][0] + M.pierna.anchos[4] * 1.5)} ${yHem - 2}`, k.ribete, 4, 1, { cap: 'butt' }));
  return { atras: [], partes, detalles };
}

function falda(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const yHem = yLargo(M, p.largo ?? 'pantorrilla');
  const F = relleno(p, k, ctx);
  const alto = p.cintura === 'alta' ? y.pecho + H * 0.015 : p.cintura === 'baja' ? y.cintura + H * 0.04 : y.cintura - H * 0.01;
  const d = (p.holgura ?? 0.006) * H;
  const vuelo = (p.vuelo ?? 0.06) * H;
  const wA = alto < y.cintura ? lerp(w.cintura, w.pecho, limitar((y.cintura - alto) / (y.cintura - y.pecho), 0, 1)) : w.cintura;
  const pts = [[0, alto, true], [wA + d, alto, true], [w.cintura + d, y.cintura], [lerp(w.cintura, w.cadera, 0.7) + d, lerp(y.cintura, y.cadera, 0.6)], [w.cadera + d * 1.3, y.cadera]];
  for (const t of [0.3, 0.6, 0.85, 1]) pts.push([w.cadera + d * 1.3 + vuelo * t ** 1.2, lerp(y.cadera, yHem, t)]);
  const ult = pts[pts.length - 1];
  const half = pts.slice(0, -1).concat(puntosBajo(p.bajo ?? 'curvo', ult[0], ult[1], H));
  const prof = pts.map(([x, yy]) => [x, yy]);
  const partes = [parte(suavizar(espejo(half, cx)), F, k)];
  const detalles = [linea(`M${cx - wA - d} ${alto + 2}L${cx + wA + d} ${alto + 2}`, k.ribete ?? sombraDe(k.principal), Math.max(3, H * 0.009), 1, { cap: 'butt' })];
  detalles.push(...pliegues(M, prof, y.cadera - H * 0.01, yHem, p.pliegues ?? 5, k.principal, 0.008));
  const nv = p.volantes ?? 0;
  for (let i = 1; i <= nv; i++) {
    const yy = lerp(y.cadera, yHem, i / (nv + 1));
    const xx = anchoEn(prof, yy);
    detalles.push(linea(`M${cx - xx} ${yy}Q${cx} ${yy + H * 0.018} ${cx + xx} ${yy}`, k.ribete ?? sombraDe(k.principal), Math.max(2, H * 0.006), 0.95));
  }
  if (p.ribeteBajo) {
    const bh = H * (p.ribeteBajo === true ? 0.03 : p.ribeteBajo);
    const xa = anchoEn(prof, yHem - bh), xb = ult[0];
    const pat = p.bandaPatron ? ctx.patron(p.bandaPatron, k.ribete, k.detalle, 0.8) : k.ribete;
    detalles.push({ d: poligono([[cx - xa, yHem - bh], [cx + xa, yHem - bh], [cx + xb, yHem], [cx - xb, yHem]]), fill: pat });
  }
  if (p.flecos) detalles.push(...flecos(M, ult[1], ult[0], k.ribete ?? k.detalle));
  return { atras: [], partes, detalles };
}

function taparrabo(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'muslo');
  const ancho = (p.ancho ?? 0.045) * H;
  const alto = y.cintura + H * 0.03;
  const pts = [[0, alto, true], [w.cadera * 0.95, alto, true], [ancho * 1.05, yH * 0.5 + alto * 0.5], [ancho, yH, true], [0, yH + H * (p.punta ?? 0.012), true]];
  const partes = [parte(suavizar(espejo(pts, cx)), F, k)];
  const detalles = [linea(`M${cx - w.cadera * 0.97} ${alto + 2}L${cx + w.cadera * 0.97} ${alto + 2}`, k.ribete ?? k.detalle, Math.max(3, H * 0.008), 1, { cap: 'butt' })];
  if (p.ribeteBajo) detalles.push(linea(`M${cx - ancho} ${yH - 2}L${cx + ancho} ${yH - 2}`, k.ribete, 4, 1, { cap: 'butt' }));
  return { atras: [], partes, detalles };
}

function capa(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'tobillo');
  const ancho = (p.ancho ?? 1) * H;
  const half = [
    [0, y.hombro - H * 0.012, true],
    [w.cuello * 1.3, y.hombro - H * 0.014],
    [w.hombro + H * 0.012, y.hombro + H * 0.01],
    [w.hombro + H * 0.025 + 0.01 * ancho, y.axila],
    [w.hombro + H * 0.035 + 0.03 * ancho, lerp(y.axila, yH, 0.35)],
    [w.hombro + H * 0.04 + 0.05 * ancho, lerp(y.axila, yH, 0.7)],
    [w.hombro + H * 0.04 + 0.07 * ancho, yH],
    ...puntosBajo(p.bajo ?? 'curvo', 0, yH, H).slice(1).map((q, i, a) => (i === a.length - 1 ? [0, yH + H * 0.012, true] : q)),
  ];
  half[half.length - 1] = [0, yH + H * 0.012, true];
  const atras = [parte(suavizar(espejo(half, cx)), F, k, { volumen: true })];
  atras.push(...[]);
  const detalles = [];
  const prof = half.slice(1, -1).map(([x, yy]) => [x, yy]);
  const partes = [];
  const front = [];
  // Piezas delanteras: solapas sobre los hombros y cierre.
  if (p.cierre !== 'abierta') {
    const sh = (s) => parte(suavizar([[cx + s * w.cuello * 1.1, y.hombro - H * 0.014], [cx + s * (w.hombro + H * 0.01), y.hombro + H * 0.008], [cx + s * (w.hombro + H * 0.03), y.axila], [cx + s * w.pecho * 0.75, y.pecho + H * 0.01], [cx + s * w.cuello * 0.9, y.hombro + H * 0.04]]), F, k);
    partes.push(sh(1), sh(-1));
  }
  if (p.cierre === 'broche' || p.cierre === 'fibula' || p.cierre === undefined) {
    detalles.push({ d: suavizar([[cx - 5, y.hombro + H * 0.035], [cx, y.hombro + H * 0.035 + 6], [cx + 5, y.hombro + H * 0.035], [cx, y.hombro + H * 0.035 - 6]]), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 1 });
  }
  if (p.ribete !== false && k.ribete) {
    for (const s of [1, -1]) detalles.push(linea(`M${cx + s * w.cuello * 1.1} ${y.hombro - H * 0.014}L${cx + s * w.cuello * 0.9} ${y.hombro + H * 0.04}`, k.ribete, Math.max(3, H * 0.008), 1, { cap: 'butt' }));
  }
  const det2 = pliegues(M, prof, y.axila + H * 0.04, yH, 4, k.principal, 0.012);
  if (p.ribeteBajo) {
    const bh = H * 0.025, xa = anchoEn(prof, yH - bh), xb = anchoEn(prof, yH);
    det2.push({ d: poligono([[cx - xa, yH - bh], [cx + xa, yH - bh], [cx + xb, yH], [cx - xb, yH]]), fill: k.ribete });
  }
  return { atras: [...atras, ...det2.map((x) => ({ ...x, atras: true }))], partes, detalles };
}

function poncho(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'cadera');
  const a = (p.ancho ?? 1) * H;
  const xs = w.hombro + 0.06 * a;
  const xe = w.hombro + 0.09 * a;
  const half = [
    [0, y.hombro + H * 0.045, true], [w.cuello * 0.9, y.hombro + H * 0.012], [w.cuello * 1.3, y.hombro - H * 0.014],
    [xs * 0.7, y.hombro + H * 0.005], [xs, lerp(y.hombro, y.codo, 0.45)], [xe, lerp(y.codo, yH, 0.35)],
    [xe * 0.97, yH, true], [xe * 0.5, yH + H * 0.012], [0, yH + H * (p.punta ?? 0.06), true],
  ];
  const partes = [parte(suavizar(espejo(half, cx)), F, k)];
  const detalles = [];
  const prof = half.slice(1).map(([x, yy]) => [x, yy]);
  if (p.listas) {
    for (let i = 1; i <= p.listas; i++) {
      const yy = lerp(y.hombro + H * 0.08, yH, i / (p.listas + 1));
      const xx = anchoEn(prof.slice(2), yy);
      detalles.push(linea(`M${cx - xx} ${yy}L${cx + xx} ${yy + H * 0.012}`, i % 2 ? k.secundario : k.ribete, Math.max(3, H * 0.007), 1, { cap: 'butt' }));
    }
  }
  detalles.push(linea(`M${cx - w.cuello * 1.3} ${y.hombro - H * 0.014}Q${cx} ${y.hombro + H * 0.052} ${cx + w.cuello * 1.3} ${y.hombro - H * 0.014}`, k.ribete ?? sombraDe(k.principal), Math.max(3, H * 0.008), 1, { cap: 'butt' }));
  if (p.flecos !== false) detalles.push(...flecos(M, yH + H * 0.004, xe * 0.97, k.ribete ?? k.detalle).slice(0, 0));
  return { atras: [], partes, detalles };
}

/** Manto diagonal: toga, sari, plaid, arisaid, tilma cruzada. */
function drapeado(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'tobillo');
  const sg = p.lado === 'der' ? 1 : -1; // lado del hombro cubierto
  const X = (v) => cx + sg * v;
  const pts = [
    [X(-w.cuello * 1.2), y.hombro - H * 0.014],
    [X(w.hombro + H * 0.015), y.hombro + H * 0.012],
    [X(w.hombro + H * 0.05), y.codo],
    [X(w.cadera + H * 0.075), lerp(y.codo, yH, 0.5)],
    [X(w.cadera + H * 0.09), yH - H * 0.004, true],
    [X(-w.cadera - H * 0.055), yH - H * 0.002, true],
    [X(-w.cadera - H * 0.025), y.cadera + H * 0.02],
    [X(-w.cintura * 0.4), y.cintura - H * 0.01],
    [X(w.pecho * 0.12), y.pecho + H * 0.008],
  ];
  const partes = [parte(suavizar(pts, true), F, k)];
  const detalles = [];
  // Orla a lo largo del borde diagonal.
  if (k.ribete && p.orla !== false) {
    detalles.push(linea(`M${pts[0][0]} ${pts[0][1]}Q${X(w.pecho * 0.1)} ${y.pecho - H * 0.02} ${pts[7][0]} ${pts[7][1]}L${pts[6][0]} ${pts[6][1]}L${pts[5][0]} ${pts[5][1]}`, k.ribete, Math.max(3, H * 0.009), 1, { cap: 'butt' }));
  }
  // Pliegues que caen desde el hombro.
  for (let i = 0; i < (p.pliegues ?? 5); i++) {
    const t = (i + 1) / ((p.pliegues ?? 5) + 1);
    const xa = lerp(pts[7][0], pts[1][0], 0.2 + t * 0.6), ya = lerp(pts[7][1], pts[1][1] + H * 0.05, 0.5 + t * 0.3);
    const xb = lerp(pts[6][0], pts[4][0], t), yb = yH - (i % 2) * H * 0.012;
    detalles.push(linea(`M${xa} ${ya}Q${(xa + xb) / 2 + sg * (i % 2 ? 6 : -6)} ${(ya + yb) / 2} ${xb} ${yb}`, sombraDe(k.principal), 1.4, 0.4));
  }
  return { atras: [], partes, detalles };
}

/** Prendas abiertas por delante: chaleco, chaqueta, abrigo, levita, bolero, bata. */
function chaqueta(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const d = (p.holgura ?? 0.016) * H;
  const yHem = yLargo(M, p.largo ?? 'cadera');
  const F = relleno(p, k, ctx);
  const sinM = !p.manga || p.manga === 'sin';
  const per = perfilVestido(M, { d, cin: p.cintura ?? 0.6, vuelo: p.vuelo ?? 0.04, yHem, sinMangas: sinM, escote: 'redondo', prof: 0.3, bajo: p.bajo ?? 'recto' });
  const gapA = (p.abertura ?? 0.012) * H, gapB = (p.aberturaBajo ?? 0.03) * H;
  const prof = per.perfil;
  const yTop = y.hombro + H * 0.01;
  const partes = [], detalles = [];
  for (const s of [1, -1]) {
    const outer = prof.filter(([, yy]) => yy > y.hombro - H * 0.03).map(([x, yy]) => [cx + s * x, yy]);
    const pts = [[cx + s * gapA, y.hombro - H * 0.02], [cx + s * w.cuello * 1.25, y.hombro - H * 0.02], ...outer.slice(1),
      [cx + s * gapB, per.yBajo], [cx + s * (gapA + (gapB - gapA) * 0.5), lerp(y.hombro, per.yBajo, 0.5)]];
    partes.push(parte(suavizar(pts.map((q, i) => (i === 0 || i === pts.length - 2 ? [q[0], q[1], true] : q)), true), F, k));
    if (p.solapa) {
      const sl = [[cx + s * gapA * 0.5, y.hombro - H * 0.005], [cx + s * w.cuello * 1.5, y.hombro - H * 0.012], [cx + s * w.pecho * 0.82, y.pecho - H * 0.01], [cx + s * (gapA + w.cuello * 0.2), y.pecho + H * 0.055]];
      detalles.push({ d: poligono(sl), fill: p.solapaColor === 'ribete' && k.ribete ? k.ribete : aclarar(k.principal, 0.08), stroke: oscurecer(k.principal, 0.45), sw: 1.1 });
    }
  }
  const m = mangas(M, p.manga ?? 'sin', d * 0.9, k, F, p.vuelo ?? 0.03, p.puños && k.ribete);
  partes.push(...m.partes); detalles.push(...m.detalles);
  if (p.ribete !== false && k.ribete) {
    for (const s of [1, -1]) detalles.push(linea(`M${cx + s * gapA} ${y.hombro}L${cx + s * gapB} ${per.yBajo - 1}`, k.ribete, Math.max(2, H * 0.005), 1, { cap: 'butt' }));
  }
  if (p.botones) {
    for (let i = 0; i < p.botones; i++) {
      const yy = lerp(y.pecho + H * 0.06, Math.min(per.yBajo - H * 0.03, y.cintura + H * 0.08), i / Math.max(1, p.botones - 1));
      detalles.push({ d: suavizar([[cx - 3.2, yy], [cx, yy + 3.2], [cx + 3.2, yy], [cx, yy - 3.2]]), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 0.8 });
    }
  }
  if (p.pliegues) detalles.push(...pliegues(M, prof.map(([x, yy]) => [x, yy]), y.cintura + H * 0.03, per.yBajo, p.pliegues, k.principal));
  if (p.bordadoBorde) {
    for (const s of [1, -1]) detalles.push(linea(`M${cx + s * (gapA + 4)} ${y.hombro + H * 0.03}L${cx + s * (gapB + 4)} ${per.yBajo - 3}`, k.detalle, 1.3, 0.9, { dash: '2 3' }));
  }
  return { atras: [], partes, detalles };
}

function delantal(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'pantorrilla');
  const bib = p.peto !== false;
  const wt = w.cintura * 0.82, wb = w.cadera * 0.95 + (p.vuelo ?? 0.02) * H;
  const top = bib ? y.pecho + H * 0.005 : y.cintura;
  const pts = [[0, top, true], [bib ? w.pecho * 0.55 : wt, top, true], [wt, y.cintura - H * 0.01], [wb * 0.8, y.cadera], [wb, yH, true], [0, yH + H * 0.006, true]];
  const partes = [parte(suavizar(espejo(pts, cx)), F, k)];
  const detalles = [linea(`M${cx - w.cintura - 2} ${y.cintura + 1}L${cx + w.cintura + 2} ${y.cintura + 1}`, k.ribete ?? k.detalle, Math.max(3, H * 0.008), 1, { cap: 'butt' })];
  if (bib) for (const s of [1, -1]) detalles.push(linea(`M${cx + s * w.pecho * 0.55} ${top}L${cx + s * w.cuello * 1.2} ${y.hombro - H * 0.008}`, k.ribete ?? k.detalle, Math.max(2, H * 0.006), 1));
  detalles.push(...pliegues(M, pts.slice(1, -1).map(([x, yy]) => [x, yy]), y.cintura, yH, 3, k.principal, 0.006));
  return { atras: [], partes, detalles };
}

function faja(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const yy = p.y === 'cadera' ? y.cadera - H * 0.03 : p.y === 'pecho' ? y.pecho + H * 0.03 : y.cintura - H * 0.002;
  const bh = (p.ancho ?? 0.02) * H;
  const T = perfilTorso(M, 0).map(([x, q]) => [x, q]);
  const xa = anchoEn(T, yy - bh / 2) + H * 0.014, xb = anchoEn(T, yy + bh / 2) + H * 0.014;
  const F = relleno(p, k, ctx);
  const partes = [parte(poligono([[cx - xa, yy - bh / 2], [cx + xa, yy - bh / 2], [cx + xb, yy + bh / 2], [cx - xb, yy + bh / 2]]), F, k, { volumen: false })];
  const detalles = [];
  if (p.hebilla) detalles.push({ d: poligono([[cx - bh * 0.6, yy - bh * 0.5], [cx + bh * 0.6, yy - bh * 0.5], [cx + bh * 0.6, yy + bh * 0.5], [cx - bh * 0.6, yy + bh * 0.5]]), fill: k.detalle, stroke: oscurecer(k.detalle, 0.45), sw: 1.2 });
  if (p.cuelga) {
    const lx = cx + (p.cuelga === 'izq' ? -1 : 1) * (p.hebilla ? bh * 1.1 : xa * 0.3);
    detalles.push({ d: poligono([[lx - 4, yy], [lx + 4, yy], [lx + 3, yy + H * 0.14], [lx - 3, yy + H * 0.14]]), fill: k.ribete ?? k.principal, stroke: oscurecer(k.ribete ?? k.principal, 0.4), sw: 1 });
    detalles.push(...flecos({ ...M, cx: lx }, yy + H * 0.14, 3.5, k.detalle).slice(0, 4));
  }
  if (p.doble) detalles.push(linea(`M${cx - xa} ${yy + bh * 0.2}L${cx + xb} ${yy + bh * 0.2}`, k.ribete ?? k.detalle, 1.5, 0.9));
  return { atras: [], partes, detalles };
}

function bandolera(M, p, k) {
  const { H, y, cx, w } = M;
  const sg = p.lado === 'der' ? 1 : -1;
  const d = `M${cx + sg * w.cuello * 1.5} ${y.hombro}Q${cx} ${y.pecho + H * 0.05} ${cx - sg * (w.cadera + H * 0.008)} ${y.cadera + H * 0.01}`;
  const det = [linea(d, k.principal, Math.max(4, (p.ancho ?? 0.017) * H), 1, { cap: 'butt', contorno: oscurecer(k.principal, 0.5) })];
  if (p.bolsa) det.push({ d: poligono([[cx - sg * w.cadera - 12, y.cadera], [cx - sg * w.cadera + 12, y.cadera], [cx - sg * w.cadera + 14, y.cadera + H * 0.06], [cx - sg * w.cadera - 14, y.cadera + H * 0.06]]), fill: k.secundario, stroke: oscurecer(k.secundario, 0.45), sw: 1.2 });
  return { atras: [], partes: [], detalles: det };
}

function chal(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'cadera');
  const half = [
    [0, y.hombro - H * 0.01, true], [w.cuello * 1.3, y.hombro - H * 0.016], [w.hombro + H * 0.012, y.hombro + H * 0.01],
    [w.hombro + H * 0.035, lerp(y.hombro, y.codo, 0.5)], [w.hombro + H * 0.045, y.codo], [w.hombro + H * 0.035, yH, true],
    [w.pecho * 0.85, yH + H * 0.01, true], [w.pecho * 0.75, lerp(y.pecho, yH, 0.4)], [w.cuello * 1.2, y.pecho + H * 0.02], [0, y.hombro + H * 0.045, true],
  ];
  const partes = [parte(suavizar(espejo(half, cx)), F, k)];
  const detalles = [];
  if (p.flecos !== false && k.ribete) {
    for (const s of [1, -1]) detalles.push(...flecos({ ...M, cx: cx + s * (w.hombro + w.pecho * 0.8) / 2 }, yH + H * 0.006, (w.hombro - w.pecho * 0.8) / 2 + 10, k.ribete).slice(0, 6));
  }
  detalles.push(...pliegues(M, half.slice(2, 9).map(([x, yy]) => [x, yy]), y.hombro + H * 0.04, yH, 3, k.principal, 0.008).map((q) => q));
  return { atras: [], partes, detalles };
}

function cuello(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const tipo = p.tipo ?? 'collar';
  const yn = lerp(y.menton, y.hombro, 0.52);
  const det = [], partes = [];
  const arc = (yy, ancho, prof) => `M${cx - ancho} ${yy}Q${cx} ${yy + prof} ${cx + ancho} ${yy}`;
  switch (tipo) {
    case 'gorguera': {
      const pts = [];
      const n = 22, rx = w.cuello * 2.5, ry = H * 0.026;
      for (let i = 0; i < n; i++) {
        const a = (i / n) * Math.PI * 2, r = i % 2 ? 1 : 1.13;
        pts.push([cx + Math.cos(a) * rx * r, yn + H * 0.02 + Math.sin(a) * ry * r]);
      }
      partes.push(parte(suavizar(pts, true), k.principal, k, { volumen: true }));
      det.push(linea(`M${cx - rx * 0.8} ${yn + H * 0.026}Q${cx} ${yn + H * 0.05} ${cx + rx * 0.8} ${yn + H * 0.026}`, sombraDe(k.principal), 1.2, 0.5));
      break;
    }
    case 'alto': {
      const top = y.menton + (y.cuello - y.menton) * 0.4;
      const pts = [[cx - w.cuello * 1.02, top], [cx + w.cuello * 1.02, top, true], [cx + w.cuello * 1.2, y.hombro - H * 0.01], [cx - w.cuello * 1.2, y.hombro - H * 0.01]];
      partes.push(parte(poligono(pts), k.principal, k));
      det.push(linea(`M${cx - w.cuello * 1.02} ${top + 2}L${cx + w.cuello * 1.02} ${top + 2}`, k.ribete ?? sombraDe(k.principal), 2.5, 1, { cap: 'butt' }));
      break;
    }
    case 'bufanda': {
      const pts = [[cx - w.cuello * 1.4, yn - H * 0.005], [cx + w.cuello * 1.4, yn - H * 0.005], [cx + w.cuello * 1.7, yn + H * 0.045], [cx - w.cuello * 1.7, yn + H * 0.045]];
      partes.push(parte(suavizar(pts, true, 0.7), k.principal, k));
      const lx = cx + w.cuello * 0.9;
      partes.push(parte(poligono([[lx - 7, yn + H * 0.03], [lx + 9, yn + H * 0.03], [lx + 11, yn + H * 0.19], [lx - 5, yn + H * 0.19]]), k.principal, k));
      det.push(linea(`M${lx - 5} ${yn + H * 0.17}L${lx + 11} ${yn + H * 0.17}`, k.ribete ?? k.secundario, 4, 1, { cap: 'butt' }));
      break;
    }
    case 'pectoral': { // collar ancho egipcio
      for (let i = 0; i < 4; i++) {
        const yy = y.hombro - H * 0.005 + i * H * 0.0, aw = w.cuello * (1.5 + i * 0.55), pr = H * (0.045 + i * 0.026);
        det.push(linea(arc(yy, aw, pr), [k.principal, k.secundario, k.detalle, k.ribete ?? k.principal][i], H * 0.0125, 1, { cap: 'butt', dash: i % 2 ? '1 4' : undefined, contorno: oscurecer(k.principal, 0.5) }));
      }
      break;
    }
    case 'trapelacucha': {
      det.push(linea(arc(y.hombro - H * 0.004, w.cuello * 1.5, H * 0.07), k.principal, 1.8, 1));
      det.push({ d: poligono([[cx - 10, y.pecho + H * 0.035], [cx + 10, y.pecho + H * 0.035], [cx + 14, y.pecho + H * 0.085], [cx, y.pecho + H * 0.1], [cx - 14, y.pecho + H * 0.085]]), fill: k.principal, stroke: oscurecer(k.principal, 0.4), sw: 1.2 });
      for (const dx of [-8, 0, 8]) det.push({ d: suavizar([[cx + dx - 2.5, y.pecho + H * 0.1], [cx + dx, y.pecho + H * 0.115], [cx + dx + 2.5, y.pecho + H * 0.1], [cx + dx, y.pecho + H * 0.095]]), fill: k.secundario });
      break;
    }
    case 'cuentas-largas':
      for (let i = 0; i < 3; i++) det.push(linea(arc(y.hombro - H * 0.002, w.cuello * 1.4, H * (0.1 + i * 0.045)), i % 2 ? k.secundario : k.principal, 4, 1, { dash: '0.1 7' }));
      break;
    case 'corbata': {
      const yk = y.hombro + H * 0.035;
      partes.push(parte(poligono([[cx - 5, yk - 3], [cx + 5, yk - 3], [cx + 3.5, yk + 5], [cx - 3.5, yk + 5]]), k.principal, k, { volumen: false }));
      partes.push(parte(poligono([[cx - 3.5, yk + 4], [cx + 3.5, yk + 4], [cx + 7, yk + H * 0.12], [cx, yk + H * 0.135], [cx - 7, yk + H * 0.12]]), k.principal, k, { volumen: false }));
      if (p.rayas !== false) det.push(linea(`M${cx - 4} ${yk + H * 0.06}L${cx + 4} ${yk + H * 0.05}M${cx - 5} ${yk + H * 0.085}L${cx + 5} ${yk + H * 0.075}`, k.secundario, 1.6, 0.9));
      break;
    }
    case 'gargantilla':
      det.push(linea(arc(yn + H * 0.012, w.cuello * 1.05, H * 0.02), k.principal, 4, 1, { dash: '0.1 6' }));
      break;
    case 'lazo':
      det.push({ d: suavizar([[cx, yn + H * 0.04], [cx - 18, yn + H * 0.02], [cx - 17, yn + H * 0.06]]), fill: k.principal, stroke: oscurecer(k.principal, 0.4), sw: 1 });
      det.push({ d: suavizar([[cx, yn + H * 0.04], [cx + 18, yn + H * 0.02], [cx + 17, yn + H * 0.06]]), fill: k.principal, stroke: oscurecer(k.principal, 0.4), sw: 1 });
      det.push({ d: suavizar([[cx - 3, yn + H * 0.032], [cx + 3, yn + H * 0.032], [cx + 3, yn + H * 0.05], [cx - 3, yn + H * 0.05]]), fill: k.secundario });
      break;
    default:
      det.push(linea(arc(y.hombro - H * 0.002, w.cuello * 1.45, H * 0.065), k.principal, 5, 1, { dash: p.cuentas === false ? undefined : '0.1 8' }));
      if (p.colgante) det.push({ d: suavizar([[cx - 5, y.hombro + H * 0.062], [cx, y.hombro + H * 0.062 + 10], [cx + 5, y.hombro + H * 0.062], [cx, y.hombro + H * 0.062 - 5]]), fill: k.secundario, stroke: oscurecer(k.secundario, 0.4), sw: 1 });
  }
  return { atras: [], partes, detalles: det };
}

function calzado(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const tipo = p.tipo ?? 'zapatos';
  const partes = [], detalles = [];
  const pie = M.pierna.pie;
  for (const s of [1, -1]) {
    const x = cx + s * pie.x;
    const aw = pie.ancho, yT = y.tobillo - H * 0.01;
    const suelaY = M.suelo;
    const zap = (altoY) => [
      [x - aw * 0.92, altoY], [x - aw * 1.0, lerp(altoY, suelaY, 0.6)], [x - aw * 1.36 + s * aw * 0.1, suelaY - pie.largo * 0.2],
      [x + s * aw * 0.35, suelaY + 1], [x + aw * 1.36 + s * aw * 0.1, suelaY - pie.largo * 0.2], [x + aw * 1.0, lerp(altoY, suelaY, 0.6)], [x + aw * 0.92, altoY],
    ];
    switch (tipo) {
      case 'descalzo': break;
      case 'sandalias': case 'sandalia-romana': {
        partes.push(parte(suavizar(zap(suelaY - H * 0.012), true), k.principal, k, { plano: true, volumen: false }));
        for (let i = 0; i < 3; i++) detalles.push(linea(`M${x - aw * 0.95} ${suelaY - H * (0.014 + i * 0.011)}L${x + aw * 0.95} ${suelaY - H * (0.014 + i * 0.011) - 2}`, k.secundario ?? k.principal, 2, 1));
        if (tipo === 'sandalia-romana') for (let i = 0; i < 6; i++) detalles.push(linea(`M${x - M.pierna.anchos[4] * 1.1} ${yT - i * H * 0.018}L${x + M.pierna.anchos[4] * 1.1} ${yT - i * H * 0.018 - 6}`, k.secundario ?? k.principal, 1.8, 0.95));
        break;
      }
      case 'mocasines': case 'zapatos': case 'zuecos': case 'zapatillas': {
        const pts = zap(tipo === 'zapatillas' ? y.tobillo + H * 0.01 : yT - H * 0.004);
        partes.push(parte(suavizar(pts, true), k.principal, k));
        detalles.push(linea(`M${x - aw * 1.25} ${suelaY - 2}L${x + aw * 1.25} ${suelaY - 2}`, k.ribete ?? oscurecer(k.principal, 0.5), 2.4, 1, { cap: 'butt' }));
        if (tipo === 'zapatos') detalles.push(linea(`M${x - aw * 0.5} ${suelaY - H * 0.026}Q${x} ${suelaY - H * 0.034} ${x + aw * 0.5} ${suelaY - H * 0.026}`, sombraDe(k.principal), 1.3, 0.7));
        if (p.hebilla) detalles.push({ d: poligono([[x - 4, suelaY - H * 0.03], [x + 4, suelaY - H * 0.03], [x + 4, suelaY - H * 0.018], [x - 4, suelaY - H * 0.018]]), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 0.9 });
        break;
      }
      case 'botas': case 'botas-altas': case 'polainas': {
        const yTop = tipo === 'botas' ? lerp(y.rodilla, y.tobillo, 0.45) : tipo === 'polainas' ? lerp(y.rodilla, y.tobillo, 0.3) : y.rodilla + H * 0.01;
        const L = M.pierna;
        const linePts = [], ws = [];
        for (let i = 0; i <= 4; i++) {
          const yy = lerp(yTop, y.tobillo, i / 4);
          let q = { x: L.linea[2][0], w: L.anchos[2] };
          for (let j = 0; j < L.linea.length - 1; j++) {
            if (yy <= L.linea[j + 1][1]) { const tt = (yy - L.linea[j][1]) / ((L.linea[j + 1][1] - L.linea[j][1]) || 1); q = { x: lerp(L.linea[j][0], L.linea[j + 1][0], tt), w: lerp(L.anchos[j], L.anchos[j + 1], tt) }; break; }
          }
          linePts.push([cx + s * q.x, yy]); ws.push(q.w * 1.12 + H * 0.003);
        }
        partes.push(parte(suavizar(tubo(linePts, ws, 'plana')), k.principal, k));
        partes.push(parte(suavizar(zap(y.tobillo - H * 0.02), true), k.principal, k));
        detalles.push(linea(`M${x - ws[0] * 0.95} ${yTop + 2}L${x + ws[0] * 0.95} ${yTop + 2}`, k.ribete ?? k.secundario ?? oscurecer(k.principal, 0.4), 3.5, 1, { cap: 'butt' }));
        if (tipo === 'polainas') for (let i = 1; i < 5; i++) detalles.push(linea(`M${x - ws[i] * 0.95} ${lerp(yTop, y.tobillo, i / 5)}L${x + ws[i] * 0.95} ${lerp(yTop, y.tobillo, i / 5) + 3}`, k.secundario ?? sombraDe(k.principal), 1.6, 0.8));
        break;
      }
      default: break;
    }
  }
  return { atras: [], partes, detalles };
}

function accesorio(M, p, k) {
  const { H, y, cx } = M;
  const det = [];
  const tipo = p.tipo ?? 'brazaletes';
  if (tipo === 'brazaletes') {
    for (const s of [1, -1]) {
      for (const yy of p.alto ? [y.codo - H * 0.07] : [y.muneca - H * 0.008]) {
        const q = brazoEnY(M, s, yy);
        det.push(linea(`M${q.x - q.w * 1.05} ${q.y}L${q.x + q.w * 1.05} ${q.y + 1}`, k.principal, Math.max(3, H * 0.008), 1, { cap: 'butt', contorno: oscurecer(k.principal, 0.5) }));
      }
    }
  } else if (tipo === 'bolso') {
    const bx = cx + (p.lado === 'der' ? 1 : -1) * (M.w.cadera + H * 0.035), by = y.cadera + H * 0.02;
    det.push(linea(`M${cx - (p.lado === 'der' ? 1 : -1) * M.w.cuello * 1.3} ${y.hombro}L${bx} ${by}`, k.secundario ?? k.principal, 2.5, 1));
    det.push({ d: poligono([[bx - 14, by], [bx + 14, by], [bx + 16, by + H * 0.08], [bx - 16, by + H * 0.08]]), fill: k.principal, stroke: oscurecer(k.principal, 0.45), sw: 1.3 });
  } else if (tipo === 'cinta-frente') {
    det.push(linea(`M${cx - M.hombroX * 0.5} ${y.menton - H * 0.04}L${cx + M.hombroX * 0.5} ${y.menton - H * 0.04}`, k.principal, 3, 1));
  }
  return { atras: [], partes: [], detalles: det };
}

export const PLANTILLAS = { tunica, pantalon, falda, taparrabo, capa, poncho, drapeado, chaqueta, delantal, faja, bandolera, chal, cuello, calzado, accesorio };
export const PLANTILLAS_ID = Object.keys(PLANTILLAS);

/** Orden de dibujo por ranura (menor = más al fondo). */
export const ORDEN_RANURAS = {
  capa_atras: 10, calzado: 20, inferior: 30, torso: 40, sobre: 50, faja: 60, capa: 70, cuello: 80, accesorio: 90,
};
