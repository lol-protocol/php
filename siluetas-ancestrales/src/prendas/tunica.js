import { suavizar, espejo, poligono, lerp, oscurecer } from '../util.js';
import { yLargo, anchoEn, relleno, parte, linea, sombraDe, pliegues, ESCOTES_SIN_HOMBRO, perfilVestido, mangas, adornosTorso, tirantesDe, flecos } from './comun.js';

export function tunica(M, p, k, ctx) {
  const { H, y, cx } = M;
  const d = (p.holgura ?? 0.012) * H;
  const yHem = yLargo(M, p.largo ?? 'rodilla');
  const F = relleno(p, k, ctx);
  const sinHombro = ESCOTES_SIN_HOMBRO.includes(p.escote);
  const sinM = p.manga === 'sin' || sinHombro;
  const per = perfilVestido(M, { d, cin: p.cintura ?? 1, vuelo: p.vuelo ?? 0.03, yHem, sinMangas: sinM, escote: p.escote ?? 'redondo', prof: p.prof ?? 1, bajo: p.bajo ?? 'recto', forma: p.forma });
  const partes = [parte(suavizar(espejo(per.completo, cx)), F, k)];
  if (sinHombro) partes.push(...tirantesDe(M, p.escote, p, F, k, p.lado === 'izq' ? -1 : 1));
  const m = mangas(M, sinHombro ? 'sin' : (p.manga ?? 'larga'), d * 0.9, k, F, p.vuelo ?? 0.03, p.puños !== false && p.ribete !== false && !!k.ribete && p.puños);
  partes.push(...m.partes);
  const detalles = [...m.detalles];
  const prof = per.perfil;
  const ad = adornosTorso(M, p, k, per, m.fins);
  partes.push(...ad.partes); detalles.push(...ad.detalles);
  // Ribete del escote.
  if (p.ribete !== false && k.ribete) {
    const E = per.escote.map((q) => [q[0], q[1], q[2]]);
    const neck = suavizar([...E.slice().reverse().map(([x, yy, c]) => [cx - x, yy, c]).slice(0, -1), ...E.map(([x, yy, c]) => [cx + x, yy, c])], false);
    detalles.push(linea(neck, k.ribete, Math.max(3, H * 0.008), 1, { cap: 'butt' }));
  }
  // Ribete del bajo.
  if (p.ribeteBajo) {
    const bh = H * (p.ribeteBajo === true ? 0.025 : p.ribeteBajo);
    const xa = anchoEn(prof, per.yBajo - bh), xb = per.xBajo;
    const pat = p.bandaPatron ? ctx.patron(p.bandaPatron, k.ribete, k.detalle, 0.8) : k.ribete;
    detalles.push({ d: poligono([[cx - xa, per.yBajo - bh], [cx + xa, per.yBajo - bh], [cx + xb, per.yBajo], [cx - xb, per.yBajo]]), fill: pat, op: 1 });
  }
  // Bandas verticales (clavus, rayas de túnica).
  if (p.bandas) {
    const bw = H * (p.bandasAncho ?? 0.012);
    for (const f of p.bandas === 'central' ? [0] : [-0.32, 0.32]) {
      const xs = (yy) => f * anchoEn(prof, yy) * (p.bandas === 'central' ? 1 : 1.35);
      detalles.push(linea(`M${cx + xs(y.hombro + H * 0.02)} ${y.hombro + H * 0.02}L${cx + xs(per.yBajo)} ${per.yBajo - 1}`, k.ribete, bw, 1, { cap: 'butt' }));
    }
  }
  // Cruzada (kimono, hanfu, hanbok, camisón cruzado).
  if (p.cruzada) {
    const sg = p.cruzada === 'izq' ? -1 : 1;
    const a = [cx - sg * M.w.cuello * 1.1, y.hombro - H * 0.012], b = [cx + sg * anchoEn(prof, y.cintura) * 0.55, y.cintura + H * 0.05], c2 = [cx + sg * anchoEn(prof, y.cadera) * 0.85, per.yBajo - 1];
    detalles.push(linea(`M${a[0]} ${a[1]}Q${lerp(a[0], b[0], 0.4)} ${lerp(a[1], b[1], 0.7)} ${b[0]} ${b[1]}L${c2[0]} ${c2[1]}`, k.ribete, Math.max(3, H * 0.008), 1, { cap: 'butt' }));
    detalles.push(linea(`M${a[0]} ${a[1]}Q${lerp(a[0], b[0], 0.3) - sg * 5} ${lerp(a[1], b[1], 0.8)} ${b[0] - sg * 4} ${b[1]}L${c2[0] - sg * 4} ${c2[1]}`, sombraDe(k.principal), 1.4, 0.55));
  }
  // Abertura central con botones.
  if (p.botones) {
    const n = p.botones, y1 = p.escote === 'v' ? y.hombro + H * 0.1 : y.hombro + H * 0.05;
    detalles.push(linea(`M${cx} ${y1}L${cx} ${per.yBajo - 2}`, sombraDe(k.principal), 1.3, 0.6));
    for (let i = 0; i < n; i++) {
      const yy = lerp(y1 + H * 0.02, Math.min(per.yBajo - H * 0.03, y.entrepierna), i / Math.max(1, n - 1));
      detalles.push({ d: suavizar([[cx - 3, yy], [cx, yy + 3], [cx + 3, yy], [cx, yy - 3]]), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 0.8 });
    }
  }
  if (p.pliegues) detalles.push(...pliegues(M, prof.map(([x, yy]) => [x, yy]), y.cintura + H * 0.02, per.yBajo, p.pliegues, k.principal));
  if (p.flecos) detalles.push(...flecos(M, per.yBajo, per.xBajo, k.ribete));
  for (let i = 1; i <= (p.volantes ?? 0); i++) {
    const yy = lerp(y.cadera, per.yBajo, i / (p.volantes + 1));
    const xx = anchoEn(prof, yy);
    detalles.push(linea(`M${cx - xx} ${yy}Q${cx} ${yy + H * 0.02} ${cx + xx} ${yy}`, k.ribete ?? sombraDe(k.principal), Math.max(2, H * 0.006), 0.95));
  }
  if (p.bordadoPecho) {
    const yy = y.pecho + H * 0.02;
    detalles.push({ d: poligono([[cx - M.w.pecho * 0.6, yy], [cx + M.w.pecho * 0.6, yy], [cx + M.w.pecho * 0.6, yy + H * 0.03], [cx - M.w.pecho * 0.6, yy + H * 0.03]]), fill: ctx.patron(p.bordadoPecho, k.ribete, k.detalle, 0.7) });
  }
  return { atras: ad.atras, partes, detalles };
}
