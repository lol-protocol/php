import { suavizar, espejo, lerp } from '../util.js';
import { yLargo, relleno, parte, pliegues, flecos } from './comun.js';

export function chal(M, p, k, ctx) {
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
