import { suavizar, espejo } from '../util.js';
import { yLargo, relleno, parte, linea, pliegues } from './comun.js';

export function delantal(M, p, k, ctx) {
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
