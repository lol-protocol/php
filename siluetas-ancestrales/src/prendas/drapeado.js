import { suavizar, lerp } from '../util.js';
import { yLargo, relleno, parte, linea, sombraDe } from './comun.js';

/** Manto diagonal: toga, sari, plaid, arisaid, tilma cruzada. */
export function drapeado(M, p, k, ctx) {
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
