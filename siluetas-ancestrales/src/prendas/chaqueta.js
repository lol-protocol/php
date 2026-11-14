import { suavizar, poligono, lerp, oscurecer, aclarar } from '../util.js';
import { yLargo, relleno, parte, linea, pliegues, perfilVestido, mangas, adornosTorso } from './comun.js';

/** Prendas abiertas por delante: chaleco, chaqueta, abrigo, levita, bolero, bata. */
export function chaqueta(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const d = (p.holgura ?? 0.016) * H;
  const yHem = yLargo(M, p.largo ?? 'cadera');
  const F = relleno(p, k, ctx);
  const sinM = !p.manga || p.manga === 'sin';
  const per = perfilVestido(M, { d, cin: p.cintura ?? 0.6, vuelo: p.vuelo ?? 0.04, yHem, sinMangas: sinM, escote: 'redondo', prof: 0.3, bajo: p.bajo ?? 'recto' });
  const gapA = (p.abertura ?? 0.012) * H, gapB = (p.aberturaBajo ?? 0.03) * H;
  const prof = per.perfil;
  const partes = [], detalles = [];
  for (const s of [1, -1]) {
    const outer = prof.filter(([, yy]) => yy > y.hombro - H * 0.03).map(([x, yy]) => [cx + s * x, yy]);
    const pts = [[cx + s * gapA, y.hombro - H * 0.02], [cx + s * w.cuello * 1.25, y.hombro - H * 0.02], ...outer.slice(1),
      [cx + s * gapB, per.yBajo], [cx + s * (gapA + (gapB - gapA) * 0.5), lerp(y.hombro, per.yBajo, 0.5)]];
    partes.push(parte(suavizar(pts.map((q, i) => (i === 0 || i === pts.length - 2 ? [q[0], q[1], true] : q)), true), F, k));
    if (p.solapa) {
      const sl = [[cx + s * gapA * 0.5, y.hombro - H * 0.005], [cx + s * w.cuello * 1.5, y.hombro - H * 0.012], [cx + s * w.pecho * 0.82, y.pecho - H * 0.01], [cx + s * (gapA + w.cuello * 0.2), y.pecho + H * 0.055]];
      detalles.push({ d: poligono(sl), fill: p.solapaColor === 'ribete' && k.ribete ? k.ribete : aclarar(k.principal, 0.08), stroke: oscurecer(k.principal, 0.45), sw: 1.1 });
    }
  }
  const m = mangas(M, p.manga ?? 'sin', d * 0.9, k, F, p.vuelo ?? 0.03, p.puños && k.ribete);
  partes.push(...m.partes); detalles.push(...m.detalles);
  const ad = adornosTorso(M, p, k, per, m.fins);
  partes.push(...ad.partes); detalles.push(...ad.detalles);
  if (p.ribete !== false && k.ribete) {
    for (const s of [1, -1]) detalles.push(linea(`M${cx + s * gapA} ${y.hombro}L${cx + s * gapB} ${per.yBajo - 1}`, k.ribete, Math.max(2, H * 0.005), 1, { cap: 'butt' }));
  }
  if (p.botones) {
    for (let i = 0; i < p.botones; i++) {
      const yy = lerp(y.pecho + H * 0.06, Math.min(per.yBajo - H * 0.03, y.cintura + H * 0.08), i / Math.max(1, p.botones - 1));
      detalles.push({ d: suavizar([[cx - 3.2, yy], [cx, yy + 3.2], [cx + 3.2, yy], [cx, yy - 3.2]]), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 0.8 });
    }
  }
  if (p.pliegues) detalles.push(...pliegues(M, prof.map(([x, yy]) => [x, yy]), y.cintura + H * 0.03, per.yBajo, p.pliegues, k.principal));
  if (p.bordadoBorde) {
    for (const s of [1, -1]) detalles.push(linea(`M${cx + s * (gapA + 4)} ${y.hombro + H * 0.03}L${cx + s * (gapB + 4)} ${per.yBajo - 3}`, k.detalle, 1.3, 0.9, { dash: '2 3' }));
  }
  return { atras: ad.atras, partes, detalles };
}
