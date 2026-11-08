import { suavizar, espejo, lerp } from '../util.js';
import { yLargo, anchoEn, relleno, parte, linea, sombraDe, flecos } from './comun.js';

export function poncho(M, p, k, ctx) {
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
