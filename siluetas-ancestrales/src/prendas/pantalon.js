import { suavizar, espejo, tubo, lerp, aclarar } from '../util.js';
import { piernaLado, lineaEnY } from '../cuerpo.js';
import { yLargo, relleno, parte, linea, sombraDe } from './comun.js';

export function pantalon(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const d = (p.holgura ?? 0.004) * H;
  const yHem = yLargo(M, p.largo ?? 'tobillo');
  const F = relleno(p, k, ctx);
  const factor = { ajustado: 1, recto: 1.28, amplio: 1.7, bombacho: 1.55, campana: 1.3, palazzo: 2.3 }[p.ancho ?? 'recto'] ?? 1.28;
  const partes = [];
  const alto = p.cintura === 'baja' ? y.cintura + H * 0.045 : p.cintura === 'alta' ? y.cintura - H * 0.035 : y.cintura - H * 0.005;
  const half = [[0, alto, true], [w.cintura + d * 1.2, alto, true], [lerp(w.cintura, w.cadera, 0.7) + d, lerp(alto, y.cadera, 0.6)], [w.cadera + d * 1.3, y.cadera], [w.cadera * 0.98 + d, lerp(y.cadera, y.entrepierna, 0.7)], [0, y.entrepierna + H * 0.012, true]];
  partes.push(parte(suavizar(espejo(half, cx)), F, k));
  for (const s of [1, -1]) {
    const Lg = piernaLado(M, s);
    const pts = [], ws = [];
    const yy0 = y.cadera, n = 6;
    for (let i = 0; i < n; i++) {
      const t = i / (n - 1);
      const yy = lerp(yy0, yHem, t);
      const q = lineaEnY(Lg, yy);
      let f = 1 + (factor - 1) * (p.ancho === 'bombacho' ? Math.sin(Math.min(1, t * 1.4) * Math.PI * 0.5) * (t > 0.78 ? 1 - (t - 0.78) * 3.4 : 1) : p.ancho === 'campana' ? t ** 2 : Math.min(1, t * 2));
      if (p.ancho === 'ajustado') f = 1;
      else if (p.ancho === 'pitillo') f = 0.93;
      else if (p.ancho === 'tubo') f = 1.1;
      else if (p.ancho === 'jodhpur') f = 0.98 + 0.38 * (1 - Math.min(1, t * 1.7)) ** 1.4; // ancho en el muslo, ajustado bajo la rodilla
      pts.push([q.x, yy]);
      ws.push(q.w * f + d);
    }
    partes.push(parte(suavizar(tubo(pts, ws, 'plana')), F, k));
  }
  const detalles = [linea(`M${cx - w.cintura - d} ${alto + 3}L${cx + w.cintura + d} ${alto + 3}`, k.ribete ? k.ribete : sombraDe(k.principal), Math.max(3, H * 0.008), 0.9, { cap: 'butt' }),
    linea(`M${cx} ${alto + 5}L${cx} ${y.entrepierna + H * 0.01}`, sombraDe(k.principal), 1.2, 0.5)];
  const somb = sombraDe(k.principal), luz = aclarar(k.principal, 0.3);
  for (const s of [1, -1]) {
    const Lg = piernaLado(M, s);
    if (p.bolsillos === 'cargo') {
      const x0 = Lg.linea[1][0] + s * Lg.anchos[1] * 0.78, ya = lerp(y.cadera, y.rodilla, 0.32), yb = lerp(y.cadera, y.rodilla, 0.62), an = H * 0.03;
      detalles.push(linea(`M${x0} ${ya}L${x0 - s * an} ${ya}L${x0 - s * an} ${yb}L${x0} ${yb}`, somb, 1.4, 0.55, { suave: true }));
      detalles.push(linea(`M${x0} ${ya + H * 0.01}L${x0 - s * an} ${ya + H * 0.01}`, somb, 1.2, 0.5, { suave: true }));
      detalles.push(linea(`M${x0 - s * 2} ${ya + 2}L${x0 - s * an + s * 2} ${ya + 2}`, luz, 1.2, 0.45, { suave: true }));
    }
    if (p.bolsillos === 'jean') {
      const xa = cx + s * (w.cintura * 0.45), xb = cx + s * (w.cadera * 0.98 + d);
      detalles.push(linea(`M${xa} ${alto + 8}Q${xa + s * H * 0.02} ${alto + H * 0.06} ${xb} ${alto + H * 0.075}`, somb, 1.5, 0.55, { suave: true }));
      detalles.push(linea(`M${xa + s * 2} ${alto + 10}Q${xa + s * H * 0.02 + s * 2} ${alto + H * 0.062} ${xb} ${alto + H * 0.077}`, luz, 1.1, 0.4, { suave: true }));
    }
    if (p.bolsillos === 'pliegues') {
      detalles.push(linea(`M${Lg.linea[0][0]} ${y.cadera + H * 0.02}L${Lg.linea[4][0]} ${yHem - 3}`, luz, 1.6, 0.45, { suave: true }));
      detalles.push(linea(`M${Lg.linea[0][0] + 2.4} ${y.cadera + H * 0.02}L${Lg.linea[4][0] + 2.4} ${yHem - 3}`, somb, 1.3, 0.35, { suave: true }));
    }
  }
  if (p.cordon) detalles.push(linea(`M${cx - 4} ${alto + 6}Q${cx} ${alto + 16} ${cx - 6} ${alto + 24}M${cx + 4} ${alto + 6}Q${cx} ${alto + 16} ${cx + 6} ${alto + 24}`, k.detalle, 1.6, 0.9));
  if (p.puños && k.ribete) for (const s of [1, -1]) { const Lp = piernaLado(M, s); detalles.push(linea(`M${Lp.linea[4][0] - Lp.anchos[4] * 1.5} ${yHem - 2}L${Lp.linea[4][0] + Lp.anchos[4] * 1.5} ${yHem - 2}`, k.ribete, 4, 1, { cap: 'butt' })); }
  return { atras: [], partes, detalles };
}
