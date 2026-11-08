import { poligono, oscurecer } from '../util.js';
import { linea } from './comun.js';

export function bandolera(M, p, k) {
  const { H, y, cx, w } = M;
  const sg = p.lado === 'der' ? 1 : -1;
  const d = `M${cx + sg * w.cuello * 1.5} ${y.hombro}Q${cx} ${y.pecho + H * 0.05} ${cx - sg * (w.cadera + H * 0.008)} ${y.cadera + H * 0.01}`;
  const det = [linea(d, k.principal, Math.max(4, (p.ancho ?? 0.017) * H), 1, { cap: 'butt', contorno: oscurecer(k.principal, 0.5) })];
  if (p.bolsa) det.push({ d: poligono([[cx - sg * w.cadera - 12, y.cadera], [cx - sg * w.cadera + 12, y.cadera], [cx - sg * w.cadera + 14, y.cadera + H * 0.06], [cx - sg * w.cadera - 14, y.cadera + H * 0.06]]), fill: k.secundario, stroke: oscurecer(k.secundario, 0.45), sw: 1.2 });
  return { atras: [], partes: [], detalles: det };
}
