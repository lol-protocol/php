import { suavizar, espejo, poligono, lerp, oscurecer } from '../util.js';
import { yLargo, anchoEn, relleno, parte, linea, pliegues, puntosBajo } from './comun.js';

export function capa(M, p, k, ctx) {
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
