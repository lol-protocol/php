// Piezas que comparten las plantillas de prendas: medidas del cuerpo vestido, escotes, bajos, mangas y adornos.

import { suavizar, espejo, poligono, tubo, lerp, limitar, oscurecer, aclarar } from '../util.js';
import { perfilTorso, brazoEnU } from '../cuerpo.js';

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

export const relleno = (p, k, ctx) =>
  p.patron ? ctx.patron(p.patron.tipo ?? p.patron, k.principal, k[p.patron.rol ?? 'secundario'] ?? k.secundario, p.patron.escala ?? 1) : k.principal;

export const parte = (d, fill, k, extra = {}) => ({ d, fill, contorno: oscurecer(k.principal, 0.5), volumen: true, ...extra });
export const linea = (d, stroke, sw, op = 1, extra = {}) => ({ d, fill: 'none', stroke, sw, op, cap: 'round', ...extra });
export const sombraDe = (c) => oscurecer(c, 0.32);

/** Pliegues verticales entre dos alturas: una línea de sombra y, al lado, otra de luz, ambas suaves, para dar relieve sin dibujar bordes. */
export function pliegues(M, perfil, yA, yB, n, color, amp = 0.012) {
  const out = [];
  for (let i = 1; i <= n; i++) {
    const f = (i / (n + 1)) * 2 - 1;
    const xa = f * anchoEn(perfil, yA) * 0.92, xb = f * anchoEn(perfil, yB) * 0.96;
    const mx = (xa + xb) / 2 + (i % 2 ? 1 : -1) * M.H * amp;
    const fin = yB - (i % 2) * M.H * 0.006;
    const ruta = (dx) => `M${M.cx + xa + dx} ${yA}Q${M.cx + mx + dx} ${(yA + yB) / 2} ${M.cx + xb + dx} ${fin}`;
    out.push(linea(ruta(0), sombraDe(color), 2.2, 0.34, { suave: true }));
    out.push(linea(ruta(2.6), aclarar(color, 0.28), 1.6, 0.32, { suave: true }));
  }
  return out;
}

// ---------- Escote, bajo y perfil del cuerpo vestido ----------

export function escotePts(M, tipo, prof = 1) {
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

export function puntosBajo(estilo, xH, yH, H) {
  const base = (a) => [xH, yH + (a ?? 0), true];
  switch (estilo) {
    case 'curvo': return [base(), [xH * 0.5, yH + H * 0.011], [0, yH + H * 0.015, true]];
    case 'ondulado': return [base(), [xH * 0.75, yH + H * 0.012], [xH * 0.5, yH - H * 0.004], [xH * 0.25, yH + H * 0.012], [0, yH + H * 0.002, true]];
    case 'dentado': return [base(), [xH * 0.8, yH + H * 0.022, true], [xH * 0.6, yH, true], [xH * 0.4, yH + H * 0.022, true], [xH * 0.2, yH, true], [0, yH + H * 0.018, true]];
    case 'pico': return [base(), [xH * 0.5, yH + H * 0.02], [0, yH + H * 0.055, true]];
    case 'curvo-alto': return [base(), [xH * 0.5, yH - H * 0.008], [0, yH - H * 0.02, true]];
    case 'flecos': return [base(), [0, yH + H * 0.004, true]];
    default: return [base(), [0, yH + H * 0.004, true]];
  }
}

/** Mitad derecha del contorno de una prenda de torso, del escote al bajo. Devuelve también el perfil sin el bajo. */
/** Escotes que dejan los hombros al aire: el corpiño termina arriba del pecho y los tirantes van aparte. */
export const ESCOTES_SIN_HOMBRO = ['tirantes', 'corazon', 'asimetrico', 'halter'];

/** Cuánto se abre la falda hacia el bajo según la forma (t de 0 en la cadera a 1 en el bajo). */
export function vueloSegunForma(forma, t) {
  switch (forma) {
    case 'sirena': return t ** 4;
    case 'trapecio': return t ** 0.75;
    case 'globo': return Math.sin(Math.PI * t) * 0.85;
    case 'tubo': return 0;
    default: return t ** 1.15;
  }
}

export function perfilVestido(M, o) {
  const { w, y, H } = M;
  const { d = 0, cin = 1, vuelo = 0, yHem, sinMangas = false, escote = 'redondo', prof = 1, bajo = 'recto', forma } = o;
  const T = perfilTorso(M, 0);
  const sinHombro = ESCOTES_SIN_HOMBRO.includes(escote);
  const yTop = y.pecho - H * 0.02 + (prof - 1) * H * 0.02, xTop = w.pecho * 0.95 + d;
  const E = sinHombro
    ? [[0, yTop + (escote === 'corazon' ? H * 0.022 : 0)], [xTop * 0.5, yTop - H * 0.006], [xTop, yTop + H * 0.004, true]]
    : escotePts(M, escote, prof);
  const pts = E.slice();
  const wd = (i, peso) => d * lerp(1, cin, peso);
  if (sinHombro) {
    pts.push([w.pecho * 0.97 + d, lerp(yTop, y.pecho, 0.7) + H * 0.004]);
  } else if (sinMangas) {
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
      pts.push([w.cadera + d + vuelo * H * vueloSegunForma(forma, t), lerp(y.cadera, yHem, t)]);
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
  const completo = corte.slice(0, -1).concat(puntosBajo(bajo, fin[0], fin[1], H));
  return { completo, perfil: sinBajo, xBajo: fin[0], yBajo: fin[1], escote: E };
}

// ---------- Mangas ----------

export const brazoEnY = brazoEnU;

export function finManga(M, tipo) {
  const { y, H } = M;
  const y0 = y.hombro + H * 0.02;
  switch (tipo) {
    case 'corta': return lerp(y0, y.codo, 0.55);
    case 'tres-cuartos': return lerp(y.codo, y.muneca, 0.5);
    case 'codo': return y.codo + H * 0.012;
    case 'cap': return lerp(y0, y.codo, 0.28);
    case 'larga-mano': return y.muneca + H * 0.035;
    case 'kimono': return y.muneca + H * 0.045;
    default: return y.muneca - H * 0.006;
  }
}

export function mangaForma(M, s, tipo, d, vuelo = 0) {
  const { H, y } = M;
  const yA = y.hombro + H * 0.012, yB = finManga(M, tipo);
  const n = 6, linea = [], anchos = [];
  for (let i = 0; i < n; i++) {
    const t = i / (n - 1);
    const q = brazoEnY(M, s, lerp(yA, yB, t));
    let f = 1, desp = 0;
    if (tipo === 'campana') f = 1 + (1.2 + vuelo * 10) * t ** 1.6;
    else if (tipo === 'ancha') f = 1 + 0.7 * t;
    else if (tipo === 'globo') f = 1.5 - 0.5 * Math.min(1, t * 2.2) + (t > 0.8 ? 0.1 : 0);
    else if (tipo === 'kimono') { f = 1.55 + 0.85 * t; desp = H * 0.026 * t; }
    else if (tipo === 'abullonada') f = 1.55 - 0.55 * Math.min(1, t * 1.6);
    else if (tipo === 'farol') f = 1.5 - 0.5 * Math.min(1, t / 0.3) + 0.42 * Math.max(0, (t - 0.7) / 0.3) ** 1.3; // abullonada arriba y en el puño
    else if (tipo === 'pagoda') f = 1 + 1.5 * Math.max(0, (t - 0.4) / 0.6) ** 1.4;
    else if (tipo === 'ajustada') f = 0.9;
    else if (tipo === 'cap') f = 1.1;
    // Con el brazo levantado la manga ancha se recoge hacia el codo: menos vuelo.
    if (s === M.saludoLado) f = 1 + (f - 1) * 0.55;
    linea.push([q.x + (s === M.saludoLado ? 0 : s * desp), q.y]);
    anchos.push((q.w + d * (i === 0 ? 1.15 : 1)) * f);
  }
  return { pts: tubo(linea, anchos, 'plana'), linea, anchos, fin: { x: linea[n - 1][0], y: linea[n - 1][1], w: anchos[n - 1] } };
}

export function mangas(M, tipo, d, k, F, vuelo, puño) {
  const partes = [], detalles = [], fins = [];
  if (!tipo || tipo === 'sin') return { partes, detalles, fins };
  for (const s of [1, -1]) {
    const m = mangaForma(M, s, tipo, d, vuelo);
    partes.push(parte(suavizar(m.pts), F, k, { proyecta: true }));
    fins.push({ s, ...m.fin });
    if (puño) {
      const { x, y, w } = m.fin;
      detalles.push(linea(`M${x - w * 0.97} ${y - 1.5}L${x + w * 0.97} ${y - 1.5}`, k.ribete, Math.max(3, M.H * 0.011), 1, { cap: 'butt' }));
    }
  }
  return { partes, detalles, fins };
}


// ---------- Adornos comunes de las prendas de torso ----------

/** Franja de piel (puntitos mullidos) a lo largo de una ruta. */
export const pielRuta = (d, color, ancho = 7) => linea(d, color, ancho, 0.95, { dash: '0.1 5.2', cap: 'round' });

/**
 * Capucha, bolsillos, cuello de camisa, ribetes de piel, cinturón y cordones: lo comparten túnicas, vestidos y chaquetas.
 * `per`: perfil de la prenda; `fins`: extremos de las mangas (para los puños de piel).
 */
export function adornosTorso(M, p, k, per, fins = []) {
  const { H, y, cx, w } = M;
  const atras = [], detalles = [], partes = [];
  const prof = per.perfil;
  const sombra = sombraDe(k.principal), luz = aclarar(k.principal, 0.3);
  const piel = k.ribete ?? aclarar(k.principal, 0.35);
  const rect = (x0, y0, x1, y1, ahusa = 0) => poligono([[x0, y0], [x1, y0], [x1 - ahusa, y1], [x0 + ahusa, y1]]);

  if (p.capucha) {
    const mitad = [[0, y.menton - H * 0.004, true], [w.cuello * 1.6, y.menton + H * 0.012], [w.cuello * 2.5, y.cuello + H * 0.012], [w.hombro * 0.8, y.hombro + H * 0.012], [w.cuello * 1.8, y.hombro + H * 0.036], [0, y.hombro + H * 0.038, true]];
    atras.push({ d: suavizar(espejo(mitad, cx)), fill: aclarar(k.principal, 0.04), contorno: sombra, proyecta: true });
    for (const sg of [-1, 1]) detalles.push(linea(`M${cx + sg * w.cuello * 0.8} ${y.hombro + H * 0.02}Q${cx + sg * w.cuello * 0.9} ${y.pecho} ${cx + sg * w.cuello * 0.7} ${y.pecho + H * 0.05}`, k.ribete ?? k.detalle, 1.8, 0.9));
  }
  if (p.cuelloCamisa) {
    for (const sg of [-1, 1]) {
      partes.push({ d: poligono([[cx + sg * w.cuello * 0.15, y.hombro - H * 0.017], [cx + sg * w.cuello * 1.5, y.hombro - H * 0.012], [cx + sg * w.cuello * 1.25, y.hombro + H * 0.03]]), fill: aclarar(k.principal, 0.06), proyecta: true });
    }
  }
  const bol = p.bolsillos;
  if (bol === 'pecho' || bol === 'ambos') {
    for (const sg of [-1, 1]) {
      const x0 = cx + sg * w.pecho * 0.55 - H * 0.019, y0 = y.pecho + H * 0.018;
      detalles.push(linea(`M${x0} ${y0}L${x0 + H * 0.038} ${y0}L${x0 + H * 0.036} ${y0 + H * 0.034}L${x0 + H * 0.002} ${y0 + H * 0.034}Z`, sombra, 1.3, 0.5, { suave: true }));
      detalles.push(linea(`M${x0 + 1} ${y0 + 1.5}L${x0 + H * 0.037} ${y0 + 1.5}`, luz, 1.2, 0.5, { suave: true }));
    }
  }
  if (bol === 'cadera' || bol === 'ambos') {
    for (const sg of [-1, 1]) {
      const xa = cx + sg * anchoEn(prof, y.cadera - H * 0.03) * 0.78, ya = y.cintura + H * 0.045;
      detalles.push(linea(`M${xa} ${ya}L${xa - sg * H * 0.035} ${ya + H * 0.04}`, sombra, 1.6, 0.55, { suave: true }));
      detalles.push(linea(`M${xa + sg * 2} ${ya + 1}L${xa - sg * H * 0.033} ${ya + H * 0.041}`, luz, 1.2, 0.5, { suave: true }));
    }
  }
  if (bol === 'canguro') {
    const ya = y.cintura + H * 0.01, yb = Math.min(per.yBajo - H * 0.02, y.cadera + H * 0.035), xa = anchoEn(prof, ya) * 0.62;
    detalles.push(linea(`M${cx - xa} ${ya}L${cx + xa} ${ya}L${cx + xa * 0.8} ${yb}L${cx - xa * 0.8} ${yb}Z`, sombra, 1.6, 0.5, { suave: true }));
    detalles.push(linea(`M${cx - xa + 2} ${ya + 2}L${cx + xa - 2} ${ya + 2}`, luz, 1.3, 0.5, { suave: true }));
  }
  if (p.pieles) {
    const todo = p.pieles === 'todo';
    if (todo || p.pieles === 'cuello') {
      const E = per.escote;
      const ruta = suavizar([...E.slice().reverse().map(([x, yy, c]) => [cx - x, yy, c]).slice(0, -1), ...E.map(([x, yy, c]) => [cx + x, yy, c])], false);
      detalles.push(pielRuta(ruta, piel, 9));
    }
    if (todo || p.pieles === 'puños') for (const f of fins) detalles.push(pielRuta(`M${f.x - f.w} ${f.y - 2}L${f.x + f.w} ${f.y - 2}`, piel, 8));
    if (todo || p.pieles === 'bajo') detalles.push(pielRuta(`M${cx - per.xBajo} ${per.yBajo - 3}L${cx + per.xBajo} ${per.yBajo - 3}`, piel, 8));
  }
  if (p.cinturon) {
    const yc = y.cintura, xa = anchoEn(prof, yc) + 1;
    partes.push({ d: rect(cx - xa, yc - H * 0.008, cx + xa, yc + H * 0.008), fill: k.secundario ?? sombra, plano: false, volumen: false, op: 0.95 });
    detalles.push({ d: suavizar([[cx - 5, yc - 5], [cx + 5, yc - 5], [cx + 5, yc + 5], [cx - 5, yc + 5]], true, 0.2), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 0.8 });
  }
  if (p.cordones) {
    const y0 = y.pecho - H * 0.005, y1 = y.cadera - H * 0.01, n = 7;
    let ruta = '';
    for (let i = 0; i < n; i++) { const yy = lerp(y0, y1, i / (n - 1)); ruta += `${i ? 'L' : 'M'}${cx + (i % 2 ? 7 : -7)} ${yy}`; }
    detalles.push(linea(ruta, k.detalle, 1.4, 0.95, { cap: 'round' }));
    for (let i = 0; i < n; i++) { const yy = lerp(y0, y1, i / (n - 1)); detalles.push({ d: suavizar([[cx - 8, yy], [cx - 6, yy + 1.5], [cx - 4, yy], [cx - 6, yy - 1.5]], true, 0.4), fill: k.detalle }); detalles.push({ d: suavizar([[cx + 8, yy], [cx + 6, yy + 1.5], [cx + 4, yy], [cx + 6, yy - 1.5]], true, 0.4), fill: k.detalle }); }
  }
  return { atras, partes, detalles };
}

/** Tirantes de los escotes sin hombro. */
export function tirantesDe(M, escote, p, F, k, ladoUno = 1) {
  const { H, y, cx, w } = M;
  const yTop = y.pecho - H * 0.02 + ((p.prof ?? 1) - 1) * H * 0.02, xTop = w.pecho * 0.95 + (p.holgura ?? 0.004) * H;
  const tubo2 = (a, b, anchoPx) => parte(suavizar(tubo([a, b], [anchoPx, anchoPx], 'plana')), F, k, { volumen: false, proyecta: false });
  const out = [];
  const an = Math.max(2.5, H * 0.0075);
  if (escote === 'tirantes' || escote === 'corazon') for (const sg of [-1, 1]) out.push(tubo2([cx + sg * xTop * 0.62, yTop + 2], [cx + sg * w.cuello * 1.55, y.hombro - H * 0.008], an));
  if (escote === 'asimetrico') out.push(tubo2([cx + ladoUno * xTop * 0.9, yTop + 2], [cx + ladoUno * w.hombro * 0.85, y.hombro + H * 0.004], an * 2.2));
  if (escote === 'halter') for (const sg of [-1, 1]) out.push(tubo2([cx + sg * xTop * 0.72, yTop + 2], [cx + sg * w.cuello * 0.9, y.cuello + H * 0.005], an));
  return out;
}

export function flecos(M, yH, xH, color) {
  const out = [];
  const n = Math.max(6, Math.round((xH * 2) / 7));
  for (let i = 0; i < n; i++) {
    const x = M.cx - xH + (i / (n - 1)) * xH * 2;
    out.push(linea(`M${x} ${yH - 1}L${x + (i % 2 ? 1.5 : -1.5)} ${yH + M.H * 0.022}`, color, 1.6, 0.95));
  }
  return out;
}
