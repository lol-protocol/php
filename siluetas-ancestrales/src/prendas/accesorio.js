import { poligono, oscurecer } from '../util.js';
import { linea, sombraDe, brazoEnY } from './comun.js';

export function accesorio(M, p, k) {
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
  } else if (tipo === 'tirantes') {
    for (const sg of [-1, 1]) {
      det.push(linea(`M${cx + sg * M.w.pecho * 0.55} ${y.cintura - H * 0.004}L${cx + sg * M.w.cuello * 1.6} ${y.hombro - H * 0.01}`, k.principal, Math.max(4, H * 0.011), 1, { cap: 'butt' }));
      det.push({ d: poligono([[cx + sg * M.w.pecho * 0.55 - 5, y.cintura - 5], [cx + sg * M.w.pecho * 0.55 + 5, y.cintura - 5], [cx + sg * M.w.pecho * 0.55 + 5, y.cintura + 5], [cx + sg * M.w.pecho * 0.55 - 5, y.cintura + 5]]), fill: k.detalle ?? sombraDe(k.principal), stroke: oscurecer(k.detalle ?? k.principal, 0.4), sw: 0.8 });
    }
  } else if (tipo === 'cinta-frente') {
    det.push(linea(`M${cx - M.hombroX * 0.5} ${y.menton - H * 0.04}L${cx + M.hombroX * 0.5} ${y.menton - H * 0.04}`, k.principal, 3, 1));
  }
  return { atras: [], partes: [], detalles: det };
}
