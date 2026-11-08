import { suavizar, espejo, poligono, lerp, limitar, aclarar } from '../util.js';
import { yLargo, anchoEn, relleno, parte, linea, sombraDe, pliegues, puntosBajo, vueloSegunForma, flecos } from './comun.js';

export function falda(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const yHem = yLargo(M, p.largo ?? 'pantorrilla');
  const F = relleno(p, k, ctx);
  const alto = p.cintura === 'alta' ? y.pecho + H * 0.015 : p.cintura === 'baja' ? y.cintura + H * 0.04 : y.cintura - H * 0.01;
  const d = (p.holgura ?? 0.006) * H;
  const vuelo = (p.vuelo ?? 0.06) * H;
  const wA = alto < y.cintura ? lerp(w.cintura, w.pecho, limitar((y.cintura - alto) / (y.cintura - y.pecho), 0, 1)) : w.cintura;
  const pts = [[0, alto, true], [wA + d, alto, true], [w.cintura + d, y.cintura], [lerp(w.cintura, w.cadera, 0.7) + d, lerp(y.cintura, y.cadera, 0.6)], [w.cadera + d * 1.3, y.cadera]];
  for (const t of [0.3, 0.6, 0.85, 1]) pts.push([w.cadera + d * 1.3 + vuelo * vueloSegunForma(p.forma ?? 'campana', t), lerp(y.cadera, yHem, t)]);
  const ult = pts[pts.length - 1];
  const half = pts.slice(0, -1).concat(puntosBajo(p.bajo ?? 'curvo', ult[0], ult[1], H));
  const prof = pts.map(([x, yy]) => [x, yy]);
  const partes = [parte(suavizar(espejo(half, cx)), F, k)];
  const detalles = [linea(`M${cx - wA - d} ${alto + 2}L${cx + wA + d} ${alto + 2}`, k.ribete ?? sombraDe(k.principal), Math.max(3, H * 0.009), 1, { cap: 'butt' })];
  detalles.push(...pliegues(M, prof, y.cadera - H * 0.01, yHem, p.pliegues ?? 5, k.principal, 0.008));
  const nv = p.volantes ?? 0;
  for (let i = 1; i <= nv; i++) {
    const yy = lerp(y.cadera, yHem, i / (nv + 1));
    const xx = anchoEn(prof, yy);
    detalles.push(linea(`M${cx - xx} ${yy}Q${cx} ${yy + H * 0.018} ${cx + xx} ${yy}`, k.ribete ?? sombraDe(k.principal), Math.max(2, H * 0.006), 0.95));
  }
  if (p.ribeteBajo) {
    const bh = H * (p.ribeteBajo === true ? 0.03 : p.ribeteBajo);
    const xa = anchoEn(prof, yHem - bh), xb = ult[0];
    const pat = p.bandaPatron ? ctx.patron(p.bandaPatron, k.ribete, k.detalle, 0.8) : k.ribete;
    detalles.push({ d: poligono([[cx - xa, yHem - bh], [cx + xa, yHem - bh], [cx + xb, yHem], [cx - xb, yHem]]), fill: pat });
  }
  if (p.flecos) detalles.push(...flecos(M, ult[1], ult[0], k.ribete ?? k.detalle));
  if (p.abertura) {
    const largoAb = (yHem - y.cadera) * 0.38, xAb = p.abertura === 'lado' ? anchoEn(prof, yHem - largoAb) * 0.55 : 0;
    detalles.push(linea(`M${cx + xAb} ${yHem - 1}L${cx + xAb + (p.abertura === 'lado' ? 2 : 0)} ${yHem - largoAb}`, sombraDe(k.principal), 2.6, 0.55, { suave: true }));
    detalles.push(linea(`M${cx + xAb + 3} ${yHem - 1}L${cx + xAb + 4} ${yHem - largoAb + 4}`, aclarar(k.principal, 0.3), 1.6, 0.4, { suave: true }));
  }
  return { atras: [], partes, detalles };
}
