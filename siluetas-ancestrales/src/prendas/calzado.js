import { suavizar, poligono, tubo, lerp, oscurecer } from '../util.js';
import { piernaLado, lineaEnY } from '../cuerpo.js';
import { parte, linea, sombraDe } from './comun.js';

export function calzado(M, p, k, ctx) {
  const { H, y } = M;
  const tipo = p.tipo ?? 'zapatos';
  const partes = [], detalles = [];
  for (const s of [1, -1]) {
    const PL = piernaLado(M, s);
    const pie = PL.pie;
    const x = pie.x;
    const giro = s * pie.largo * Math.tan(((pie.giro ?? 10) * Math.PI) / 180);
    const aw = pie.ancho, yT = y.tobillo - H * 0.01;
    const suelaY = M.suelo;
    const zap = (altoY) => [
      [x - aw * 0.92, altoY], [x - aw * 1.0, lerp(altoY, suelaY, 0.6)], [x - aw * 1.36 + giro, suelaY - pie.largo * 0.2],
      [x + giro * 1.3, suelaY + 1], [x + aw * 1.36 + giro, suelaY - pie.largo * 0.2], [x + aw * 1.0, lerp(altoY, suelaY, 0.6)], [x + aw * 0.92, altoY],
    ];
    switch (tipo) {
      case 'descalzo': break;
      case 'chancletas': {
        partes.push(parte(suavizar(zap(suelaY - H * 0.012), true), k.principal, k, { plano: true, volumen: false }));
        detalles.push(linea(`M${x - aw * 0.55} ${suelaY - H * 0.012}L${x} ${suelaY - H * 0.038}L${x + aw * 0.55} ${suelaY - H * 0.012}`, k.secundario ?? k.detalle, 2.6, 1));
        break;
      }
      case 'sandalias': case 'sandalia-romana': {
        partes.push(parte(suavizar(zap(suelaY - H * 0.012), true), k.principal, k, { plano: true, volumen: false }));
        for (let i = 0; i < 3; i++) detalles.push(linea(`M${x - aw * 0.95} ${suelaY - H * (0.014 + i * 0.011)}L${x + aw * 0.95} ${suelaY - H * (0.014 + i * 0.011) - 2}`, k.secundario ?? k.principal, 2, 1));
        if (tipo === 'sandalia-romana') for (let i = 0; i < 6; i++) detalles.push(linea(`M${x - PL.anchos[4] * 1.1} ${yT - i * H * 0.018}L${x + PL.anchos[4] * 1.1} ${yT - i * H * 0.018 - 6}`, k.secundario ?? k.principal, 1.8, 0.95));
        break;
      }
      case 'mocasines': case 'zapatos': case 'zuecos': case 'zapatillas': case 'tacones': case 'oxford': {
        const pts = zap(tipo === 'zapatillas' ? y.tobillo + H * 0.01 : tipo === 'tacones' ? yT + H * 0.022 : yT - H * 0.004);
        partes.push(parte(suavizar(pts, true), k.principal, k));
        detalles.push(linea(`M${x - aw * 1.25} ${suelaY - 2}L${x + aw * 1.25} ${suelaY - 2}`, k.ribete ?? oscurecer(k.principal, 0.5), 2.4, 0.55, { cap: 'butt', suave: true }));
        if (tipo === 'oxford') for (let i = 0; i < 3; i++) detalles.push(linea(`M${x - aw * 0.38} ${suelaY - H * (0.026 + i * 0.01)}L${x + aw * 0.38} ${suelaY - H * (0.026 + i * 0.01)}`, k.detalle ?? sombraDe(k.principal), 1.4, 0.8));
        if (tipo === 'tacones') detalles.push(linea(`M${x - aw * 0.5} ${suelaY - H * 0.005}L${x + aw * 0.5} ${suelaY - H * 0.005}`, k.ribete ?? oscurecer(k.principal, 0.3), 3, 0.9, { cap: 'butt' }));
        if (tipo === 'zapatos') detalles.push(linea(`M${x - aw * 0.5} ${suelaY - H * 0.026}Q${x} ${suelaY - H * 0.034} ${x + aw * 0.5} ${suelaY - H * 0.026}`, sombraDe(k.principal), 1.3, 0.7));
        if (p.hebilla) detalles.push({ d: poligono([[x - 4, suelaY - H * 0.03], [x + 4, suelaY - H * 0.03], [x + 4, suelaY - H * 0.018], [x - 4, suelaY - H * 0.018]]), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 0.9 });
        break;
      }
      case 'botas': case 'botas-altas': case 'polainas': case 'botines': {
        const yTop = tipo === 'botines' ? y.tobillo - H * 0.034 : tipo === 'botas' ? lerp(y.rodilla, y.tobillo, 0.45) : tipo === 'polainas' ? lerp(y.rodilla, y.tobillo, 0.3) : y.rodilla + H * 0.01;
        const L = PL;
        const linePts = [], ws = [];
        for (let i = 0; i <= 4; i++) {
          const yy = lerp(yTop, y.tobillo, i / 4);
          const q = lineaEnY(L, yy, 1);
          linePts.push([q.x, yy]); ws.push(q.w * 1.12 + H * 0.003);
        }
        partes.push(parte(suavizar(tubo(linePts, ws, 'plana')), k.principal, k));
        partes.push(parte(suavizar(zap(y.tobillo - H * 0.02), true), k.principal, k));
        detalles.push(linea(`M${x - ws[0] * 0.95} ${yTop + 2}L${x + ws[0] * 0.95} ${yTop + 2}`, k.ribete ?? k.secundario ?? oscurecer(k.principal, 0.4), 3.5, 1, { cap: 'butt' }));
        if (tipo === 'polainas') for (let i = 1; i < 5; i++) detalles.push(linea(`M${x - ws[i] * 0.95} ${lerp(yTop, y.tobillo, i / 5)}L${x + ws[i] * 0.95} ${lerp(yTop, y.tobillo, i / 5) + 3}`, k.secundario ?? sombraDe(k.principal), 1.6, 0.8));
        break;
      }
      default: break;
    }
  }
  return { atras: [], partes, detalles };
}
