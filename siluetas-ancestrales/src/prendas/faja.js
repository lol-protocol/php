import { poligono, oscurecer } from '../util.js';
import { perfilTorso } from '../cuerpo.js';
import { anchoEn, relleno, parte, linea, flecos } from './comun.js';

export function faja(M, p, k, ctx) {
  const { H, y, cx } = M;
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
