// Utilidades sin dependencias: azar con semilla, color y trazos suaves.

// ---------- Azar con semilla (determinista) ----------

function xmur3(texto) {
  let h = 1779033703 ^ texto.length;
  for (let i = 0; i < texto.length; i++) {
    h = Math.imul(h ^ texto.charCodeAt(i), 3432918353);
    h = (h << 13) | (h >>> 19);
  }
  return () => {
    h = Math.imul(h ^ (h >>> 16), 2246822507);
    h = Math.imul(h ^ (h >>> 13), 3266489909);
    return (h ^= h >>> 16) >>> 0;
  };
}

function mulberry32(a) {
  return () => {
    a |= 0; a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/** Azar determinista a partir de un número o un texto. Misma semilla, mismos resultados. */
export function crearAzar(semilla = 1) {
  const n = typeof semilla === 'number' ? semilla >>> 0 : xmur3(String(semilla))();
  const f = mulberry32(n);
  const azar = {
    n: f,
    entre: (a, b) => a + (b - a) * f(),
    int: (a, b) => a + Math.floor(f() * (b - a + 1)),
    bool: (p = 0.5) => f() < p,
    elegir: (lista) => lista[Math.floor(f() * lista.length)],
    /** pares [valor, peso] */
    ponderado(pares) {
      const total = pares.reduce((s, p) => s + p[1], 0);
      let r = f() * total;
      for (const [v, w] of pares) { if ((r -= w) <= 0) return v; }
      return pares[pares.length - 1][0];
    },
    mezclar(lista) {
      const a = lista.slice();
      for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(f() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; }
      return a;
    },
    /** Otro azar independiente derivado de este y de una etiqueta. */
    hijo: (etiqueta) => crearAzar(`${n}:${etiqueta}`),
  };
  return azar;
}

// ---------- Números ----------

export const limitar = (v, a, b) => Math.min(b, Math.max(a, v));
export const lerp = (a, b, t) => a + (b - a) * t;

/** Redondea a 2 decimales y falla si no es finito: un NaN en un trazo no debe llegar al SVG. */
export function num(v) {
  if (!Number.isFinite(v)) throw new Error(`Coordenada no finita en un trazo: ${v}`);
  return Math.round(v * 100) / 100;
}

// ---------- Color ----------

export function hexARgb(hex) {
  const h = hex.replace('#', '');
  const t = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
  return [parseInt(t.slice(0, 2), 16), parseInt(t.slice(2, 4), 16), parseInt(t.slice(4, 6), 16)];
}
export function rgbAHex(r, g, b) {
  const c = (v) => limitar(Math.round(v), 0, 255).toString(16).padStart(2, '0');
  return `#${c(r)}${c(g)}${c(b)}`;
}
export function rgbAHsl(r, g, b) {
  r /= 255; g /= 255; b /= 255;
  const mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2;
  if (mx === mn) return [0, 0, l];
  const d = mx - mn, s = l > 0.5 ? d / (2 - mx - mn) : d / (mx + mn);
  let h = mx === r ? (g - b) / d + (g < b ? 6 : 0) : mx === g ? (b - r) / d + 2 : (r - g) / d + 4;
  return [h * 60, s, l];
}
export function hslARgb(h, s, l) {
  h = ((h % 360) + 360) % 360 / 360;
  if (s === 0) return [l * 255, l * 255, l * 255];
  const q = l < 0.5 ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
  const f = (t) => {
    t = (t + 1) % 1;
    if (t < 1 / 6) return p + (q - p) * 6 * t;
    if (t < 1 / 2) return q;
    if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
    return p;
  };
  return [f(h + 1 / 3) * 255, f(h) * 255, f(h - 1 / 3) * 255];
}
export const hexAHsl = (hex) => rgbAHsl(...hexARgb(hex));
export const hslAHex = (h, s, l) => rgbAHex(...hslARgb(h, limitar(s, 0, 1), limitar(l, 0, 1)));

export function mezclar(a, b, t) {
  const A = hexARgb(a), B = hexARgb(b);
  return rgbAHex(lerp(A[0], B[0], t), lerp(A[1], B[1], t), lerp(A[2], B[2], t));
}
export const oscurecer = (hex, t) => mezclar(hex, '#000000', t);
export const aclarar = (hex, t) => mezclar(hex, '#ffffff', t);
export function luminancia(hex) {
  const [r, g, b] = hexARgb(hex).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
/** Cambia un color un poco (tono en grados, saturación y luminosidad en 0..1), para que dos prendas del mismo tinte no sean idénticas. */
export function variar(hex, azar, { tono = 6, sat = 0.06, luz = 0.05 } = {}) {
  const [h, s, l] = hexAHsl(hex);
  return hslAHex(h + azar.entre(-tono, tono), s + azar.entre(-sat, sat), l + azar.entre(-luz, luz));
}
export const esHex = (v) => typeof v === 'string' && /^#[0-9a-f]{6}$/i.test(v);

// ---------- Trazos ----------
// Un punto es [x, y] o [x, y, true]; el tercer valor marca una esquina (sin suavizar).

/** Curva suave cerrada o abierta por los puntos (Catmull-Rom pasada a Bézier). */
export function suavizar(pts, cerrado = true, tension = 1) {
  const n = pts.length;
  if (n < 2) return '';
  const P = (i) => (cerrado ? pts[((i % n) + n) % n] : pts[limitar(i, 0, n - 1)]);
  let d = `M${num(pts[0][0])} ${num(pts[0][1])}`;
  const tramos = cerrado ? n : n - 1;
  for (let i = 0; i < tramos; i++) {
    const p0 = P(i - 1), p1 = P(i), p2 = P(i + 1), p3 = P(i + 2);
    const c1 = p1[2] ? p1 : [p1[0] + ((p2[0] - p0[0]) / 6) * tension, p1[1] + ((p2[1] - p0[1]) / 6) * tension];
    const c2 = p2[2] ? p2 : [p2[0] - ((p3[0] - p1[0]) / 6) * tension, p2[1] - ((p3[1] - p1[1]) / 6) * tension];
    d += `C${num(c1[0])} ${num(c1[1])} ${num(c2[0])} ${num(c2[1])} ${num(p2[0])} ${num(p2[1])}`;
  }
  return d + (cerrado ? 'Z' : '');
}

/** Polilínea recta cerrada por los puntos. */
export function poligono(pts) {
  return `M${pts.map((p) => `${num(p[0])} ${num(p[1])}`).join('L')}Z`;
}

/**
 * Contorno simétrico: recibe la mitad derecha (x relativa al eje) de arriba abajo y devuelve
 * el contorno completo. El primer y el último punto deben estar sobre el eje (x = 0).
 */
export function espejo(mitad, cx) {
  const der = mitad.map((p) => [cx + p[0], p[1], p[2]]);
  const izq = [];
  for (let i = mitad.length - 2; i >= 1; i--) izq.push([cx - mitad[i][0], mitad[i][1], mitad[i][2]]);
  return der.concat(izq);
}

/**
 * Contorno de un tubo (brazo, pierna, manga) a partir de una línea central y el semiancho en cada punto.
 * `capa`: 'redonda' cierra el extremo final con un arco; 'plana' lo deja recto.
 */
export function tubo(linea, anchos, capa = 'redonda') {
  const n = linea.length;
  const dir = (i) => {
    const a = linea[Math.max(i - 1, 0)], b = linea[Math.min(i + 1, n - 1)];
    const dx = b[0] - a[0], dy = b[1] - a[1], l = Math.hypot(dx, dy) || 1;
    return [dx / l, dy / l];
  };
  const izq = [], der = [];
  for (let i = 0; i < n; i++) {
    const [dx, dy] = dir(i), w = anchos[i];
    izq.push([linea[i][0] - dy * w, linea[i][1] + dx * w]);
    der.push([linea[i][0] + dy * w, linea[i][1] - dx * w]);
  }
  const [ex, ey] = dir(n - 1), w = anchos[n - 1];
  const fin = capa === 'redonda'
    ? [[linea[n - 1][0] + ex * w * 0.9, linea[n - 1][1] + ey * w * 0.9]]
    : [];
  const esquinas = capa === 'plana';
  if (esquinas) { izq[n - 1][2] = true; der[n - 1][2] = true; }
  return [...izq, ...fin, ...der.reverse()];
}

export function escapar(texto) {
  return String(texto).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}
