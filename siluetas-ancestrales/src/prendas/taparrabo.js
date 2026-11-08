import { suavizar, espejo } from '../util.js';
import { yLargo, relleno, parte, linea } from './comun.js';

export function taparrabo(M, p, k, ctx) {
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
