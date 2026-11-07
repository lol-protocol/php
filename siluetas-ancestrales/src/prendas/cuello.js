import { suavizar, espejo, poligono, lerp, oscurecer, aclarar } from '../util.js';
import { parte, linea, sombraDe, flecos } from './comun.js';

export function cuello(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const tipo = p.tipo ?? 'collar';
  const yn = lerp(y.menton, y.hombro, 0.52);
  const det = [], partes = [];
  const arc = (yy, ancho, prof) => `M${cx - ancho} ${yy}Q${cx} ${yy + prof} ${cx + ancho} ${yy}`;
  switch (tipo) {
    case 'gorguera': {
      const pts = [];
      const n = 22, rx = w.cuello * 2.5, ry = H * 0.026;
      for (let i = 0; i < n; i++) {
        const a = (i / n) * Math.PI * 2, r = i % 2 ? 1 : 1.13;
        pts.push([cx + Math.cos(a) * rx * r, yn + H * 0.02 + Math.sin(a) * ry * r]);
      }
      partes.push(parte(suavizar(pts, true), k.principal, k, { volumen: true }));
      det.push(linea(`M${cx - rx * 0.8} ${yn + H * 0.026}Q${cx} ${yn + H * 0.05} ${cx + rx * 0.8} ${yn + H * 0.026}`, sombraDe(k.principal), 1.2, 0.5));
      break;
    }
    case 'alto': {
      const top = y.menton + (y.cuello - y.menton) * 0.4;
      const pts = [[cx - w.cuello * 1.02, top], [cx + w.cuello * 1.02, top, true], [cx + w.cuello * 1.2, y.hombro - H * 0.01], [cx - w.cuello * 1.2, y.hombro - H * 0.01]];
      partes.push(parte(poligono(pts), k.principal, k));
      det.push(linea(`M${cx - w.cuello * 1.02} ${top + 2}L${cx + w.cuello * 1.02} ${top + 2}`, k.ribete ?? sombraDe(k.principal), 2.5, 1, { cap: 'butt' }));
      break;
    }
    case 'bufanda': {
      const pts = [[cx - w.cuello * 1.4, yn - H * 0.005], [cx + w.cuello * 1.4, yn - H * 0.005], [cx + w.cuello * 1.7, yn + H * 0.045], [cx - w.cuello * 1.7, yn + H * 0.045]];
      partes.push(parte(suavizar(pts, true, 0.7), k.principal, k));
      const lx = cx + w.cuello * 0.9;
      partes.push(parte(poligono([[lx - 7, yn + H * 0.03], [lx + 9, yn + H * 0.03], [lx + 11, yn + H * 0.19], [lx - 5, yn + H * 0.19]]), k.principal, k));
      det.push(linea(`M${lx - 5} ${yn + H * 0.17}L${lx + 11} ${yn + H * 0.17}`, k.ribete ?? k.secundario, 4, 1, { cap: 'butt' }));
      break;
    }
    case 'pectoral': { // collar ancho egipcio
      for (let i = 0; i < 4; i++) {
        const yy = y.hombro - H * 0.005 + i * H * 0.0, aw = w.cuello * (1.5 + i * 0.55), pr = H * (0.045 + i * 0.026);
        det.push(linea(arc(yy, aw, pr), [k.principal, k.secundario, k.detalle, k.ribete ?? k.principal][i], H * 0.0125, 1, { cap: 'butt', dash: i % 2 ? '1 4' : undefined, contorno: oscurecer(k.principal, 0.5) }));
      }
      break;
    }
    case 'trapelacucha': {
      det.push(linea(arc(y.hombro - H * 0.004, w.cuello * 1.5, H * 0.07), k.principal, 1.8, 1));
      det.push({ d: poligono([[cx - 10, y.pecho + H * 0.035], [cx + 10, y.pecho + H * 0.035], [cx + 14, y.pecho + H * 0.085], [cx, y.pecho + H * 0.1], [cx - 14, y.pecho + H * 0.085]]), fill: k.principal, stroke: oscurecer(k.principal, 0.4), sw: 1.2 });
      for (const dx of [-8, 0, 8]) det.push({ d: suavizar([[cx + dx - 2.5, y.pecho + H * 0.1], [cx + dx, y.pecho + H * 0.115], [cx + dx + 2.5, y.pecho + H * 0.1], [cx + dx, y.pecho + H * 0.095]]), fill: k.secundario });
      break;
    }
    case 'cuentas-largas':
      for (let i = 0; i < 3; i++) det.push(linea(arc(y.hombro - H * 0.002, w.cuello * 1.4, H * (0.1 + i * 0.045)), i % 2 ? k.secundario : k.principal, 4, 1, { dash: '0.1 7' }));
      break;
    case 'piel': { // cuello de piel: puntitos mullidos alrededor del cuello y sobre los hombros
      det.push(linea(`M${cx - w.cuello * 2.6} ${y.hombro - H * 0.002}Q${cx} ${y.hombro + H * 0.085} ${cx + w.cuello * 2.6} ${y.hombro - H * 0.002}`, k.principal, 15, 1, { dash: '0.1 7', cap: 'round' }));
      det.push(linea(`M${cx - w.cuello * 3.1} ${y.hombro + H * 0.012}Q${cx} ${y.hombro + H * 0.1} ${cx + w.cuello * 3.1} ${y.hombro + H * 0.012}`, aclarar(k.principal, 0.1), 13, 1, { dash: '0.1 7.5', cap: 'round' }));
      break;
    }
    case 'esclavina': { // capita corta sobre los hombros
      const mitad = [[0, y.hombro + H * 0.036, true], [w.cuello * 1.3, y.hombro - H * 0.016], [w.hombro + H * 0.025, y.hombro + H * 0.008], [w.hombro + H * 0.045, y.axila + H * 0.03], [w.pecho * 0.85, y.pecho + H * 0.03], [0, y.pecho + H * 0.055, true]];
      partes.push(parte(suavizar(espejo(mitad, cx)), k.principal, k, { proyecta: true }));
      det.push(linea(`M${cx - w.pecho * 0.85} ${y.pecho + H * 0.03}Q${cx} ${y.pecho + H * 0.075} ${cx + w.pecho * 0.85} ${y.pecho + H * 0.03}`, k.ribete ?? k.secundario, 3, 1, { cap: 'butt' }));
      break;
    }
    case 'chalina': { // bufanda larga con dos puntas colgando
      partes.push(parte(suavizar([[cx - w.cuello * 1.5, yn - H * 0.004], [cx + w.cuello * 1.5, yn - H * 0.004], [cx + w.cuello * 1.8, yn + H * 0.04], [cx - w.cuello * 1.8, yn + H * 0.04]], true, 0.7), k.principal, k));
      for (const [sg, largo] of [[-1, 0.15], [1, 0.2]]) {
        const x0 = cx + sg * w.cuello * 0.6;
        partes.push(parte(poligono([[x0 - 8, yn + H * 0.03], [x0 + 8, yn + H * 0.03], [x0 + 9, yn + H * largo], [x0 - 7, yn + H * largo]]), k.principal, k, { proyecta: true }));
        det.push(linea(`M${x0 - 7} ${yn + H * largo - 4}L${x0 + 9} ${yn + H * largo - 4}`, k.secundario ?? k.ribete, 3, 1, { cap: 'butt' }));
        det.push(...flecos({ ...M, cx: x0 + 1 }, yn + H * largo, 8, k.secundario ?? k.principal).slice(0, 5));
      }
      break;
    }
    case 'triangulo': { // pañuelo anudado al cuello
      partes.push(parte(poligono([[cx - w.cuello * 1.5, y.hombro - H * 0.014], [cx + w.cuello * 1.5, y.hombro - H * 0.014], [cx, y.pecho + H * 0.02]]), k.principal, k, { proyecta: true }));
      det.push({ d: suavizar([[cx - 6, yn + H * 0.03], [cx, yn + H * 0.045], [cx + 6, yn + H * 0.03], [cx, yn + H * 0.018]], true, 0.4), fill: aclarar(k.principal, 0.1), stroke: sombraDe(k.principal), sw: 0.8 });
      break;
    }
    case 'babero': { // cuello ancho y redondeado
      const mitad = [[0, y.hombro + H * 0.062, true], [w.cuello * 1.2, y.hombro + H * 0.04], [w.cuello * 2.6, y.hombro + H * 0.034], [w.cuello * 3.3, y.hombro + H * 0.006], [w.cuello * 1.3, y.hombro - H * 0.016], [0, y.hombro - H * 0.012, true]];
      partes.push(parte(suavizar(espejo(mitad, cx)), aclarar(k.principal, 0.1), k, { proyecta: true }));
      break;
    }
    case 'corbata': {
      const yk = y.hombro + H * 0.035;
      partes.push(parte(poligono([[cx - 5, yk - 3], [cx + 5, yk - 3], [cx + 3.5, yk + 5], [cx - 3.5, yk + 5]]), k.principal, k, { volumen: false }));
      partes.push(parte(poligono([[cx - 3.5, yk + 4], [cx + 3.5, yk + 4], [cx + 7, yk + H * 0.12], [cx, yk + H * 0.135], [cx - 7, yk + H * 0.12]]), k.principal, k, { volumen: false }));
      if (p.rayas !== false) det.push(linea(`M${cx - 4} ${yk + H * 0.06}L${cx + 4} ${yk + H * 0.05}M${cx - 5} ${yk + H * 0.085}L${cx + 5} ${yk + H * 0.075}`, k.secundario, 1.6, 0.9));
      break;
    }
    case 'gargantilla':
      det.push(linea(arc(yn + H * 0.012, w.cuello * 1.05, H * 0.02), k.principal, 4, 1, { dash: '0.1 6' }));
      break;
    case 'lazo':
      det.push({ d: suavizar([[cx, yn + H * 0.04], [cx - 18, yn + H * 0.02], [cx - 17, yn + H * 0.06]]), fill: k.principal, stroke: oscurecer(k.principal, 0.4), sw: 1 });
      det.push({ d: suavizar([[cx, yn + H * 0.04], [cx + 18, yn + H * 0.02], [cx + 17, yn + H * 0.06]]), fill: k.principal, stroke: oscurecer(k.principal, 0.4), sw: 1 });
      det.push({ d: suavizar([[cx - 3, yn + H * 0.032], [cx + 3, yn + H * 0.032], [cx + 3, yn + H * 0.05], [cx - 3, yn + H * 0.05]]), fill: k.secundario });
      break;
    default:
      det.push(linea(arc(y.hombro - H * 0.002, w.cuello * 1.45, H * 0.065), k.principal, 5, 1, { dash: p.cuentas === false ? undefined : '0.1 8' }));
      if (p.colgante) det.push({ d: suavizar([[cx - 5, y.hombro + H * 0.062], [cx, y.hombro + H * 0.062 + 10], [cx + 5, y.hombro + H * 0.062], [cx, y.hombro + H * 0.062 - 5]]), fill: k.secundario, stroke: oscurecer(k.secundario, 0.4), sw: 1 });
  }
  return { atras: [], partes, detalles: det };
}
