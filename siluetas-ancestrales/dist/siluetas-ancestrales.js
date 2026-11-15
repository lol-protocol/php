/* Generado por bin/construir.js a partir de src/: no lo edites a mano. */
(function (global) {
'use strict';
const __m = {};
__m['util'] = (() => {
const __e = {};
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
function crearAzar(semilla = 1) {
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

const limitar = (v, a, b) => Math.min(b, Math.max(a, v));
const lerp = (a, b, t) => a + (b - a) * t;

/** Redondea a 2 decimales y falla si no es finito: un NaN en un trazo no debe llegar al SVG. */
function num(v) {
  if (!Number.isFinite(v)) throw new Error(`Coordenada no finita en un trazo: ${v}`);
  return Math.round(v * 100) / 100;
}

// ---------- Color ----------

function hexARgb(hex) {
  const h = hex.replace('#', '');
  const t = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
  return [parseInt(t.slice(0, 2), 16), parseInt(t.slice(2, 4), 16), parseInt(t.slice(4, 6), 16)];
}
function rgbAHex(r, g, b) {
  const c = (v) => limitar(Math.round(v), 0, 255).toString(16).padStart(2, '0');
  return `#${c(r)}${c(g)}${c(b)}`;
}
function rgbAHsl(r, g, b) {
  r /= 255; g /= 255; b /= 255;
  const mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2;
  if (mx === mn) return [0, 0, l];
  const d = mx - mn, s = l > 0.5 ? d / (2 - mx - mn) : d / (mx + mn);
  let h = mx === r ? (g - b) / d + (g < b ? 6 : 0) : mx === g ? (b - r) / d + 2 : (r - g) / d + 4;
  return [h * 60, s, l];
}
function hslARgb(h, s, l) {
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
const hexAHsl = (hex) => rgbAHsl(...hexARgb(hex));
const hslAHex = (h, s, l) => rgbAHex(...hslARgb(h, limitar(s, 0, 1), limitar(l, 0, 1)));

function mezclar(a, b, t) {
  const A = hexARgb(a), B = hexARgb(b);
  return rgbAHex(lerp(A[0], B[0], t), lerp(A[1], B[1], t), lerp(A[2], B[2], t));
}
const oscurecer = (hex, t) => mezclar(hex, '#000000', t);
const aclarar = (hex, t) => mezclar(hex, '#ffffff', t);
function luminancia(hex) {
  const [r, g, b] = hexARgb(hex).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
/** Cambia un color un poco (tono en grados, saturación y luminosidad en 0..1), para que dos prendas del mismo tinte no sean idénticas. */
function variar(hex, azar, { tono = 6, sat = 0.06, luz = 0.05 } = {}) {
  const [h, s, l] = hexAHsl(hex);
  return hslAHex(h + azar.entre(-tono, tono), s + azar.entre(-sat, sat), l + azar.entre(-luz, luz));
}
const esHex = (v) => typeof v === 'string' && /^#[0-9a-f]{6}$/i.test(v);

// ---------- Trazos ----------
// Un punto es [x, y] o [x, y, true]; el tercer valor marca una esquina (sin suavizar).

/** Curva suave cerrada o abierta por los puntos (Catmull-Rom pasada a Bézier). */
function suavizar(pts, cerrado = true, tension = 1) {
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
function poligono(pts) {
  return `M${pts.map((p) => `${num(p[0])} ${num(p[1])}`).join('L')}Z`;
}

/**
 * Contorno simétrico: recibe la mitad derecha (x relativa al eje) de arriba abajo y devuelve
 * el contorno completo. El primer y el último punto deben estar sobre el eje (x = 0).
 */
function espejo(mitad, cx) {
  const der = mitad.map((p) => [cx + p[0], p[1], p[2]]);
  const izq = [];
  for (let i = mitad.length - 2; i >= 1; i--) izq.push([cx - mitad[i][0], mitad[i][1], mitad[i][2]]);
  return der.concat(izq);
}

/**
 * Contorno de un tubo (brazo, pierna, manga) a partir de una línea central y el semiancho en cada punto.
 * `capa`: 'redonda' cierra el extremo final con un arco; 'plana' lo deja recto.
 */
function tubo(linea, anchos, capa = 'redonda') {
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

function escapar(texto) {
  return String(texto).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

Object.assign(__e, { crearAzar, limitar, lerp, num, hexARgb, rgbAHex, rgbAHsl, hslARgb, hexAHsl, hslAHex, mezclar, oscurecer, aclarar, luminancia, variar, esHex, suavizar, poligono, espejo, tubo, escapar });
return __e;
})();

__m['datos/colores'] = (() => {
const __e = {};
// Pieles, cabellos y tintes con nombre. Los tintes traen época y costo aproximados para que el
// generador no vista a un campesino medieval con púrpura de Tiro: es una guía, no una tesis.

const PIELES = [
  { id: 'porcelana', nombre: 'Muy clara', hex: '#f0cdb4' },
  { id: 'clara', nombre: 'Clara', hex: '#e6b894' },
  { id: 'clara-calida', nombre: 'Clara cálida', hex: '#dba676' },
  { id: 'oliva-clara', nombre: 'Oliva clara', hex: '#cf9a6a' },
  { id: 'oliva', nombre: 'Oliva', hex: '#c08a5a' },
  { id: 'canela', nombre: 'Canela', hex: '#b07a4e' },
  { id: 'bronce', nombre: 'Bronce', hex: '#9c6a42' },
  { id: 'cobre', nombre: 'Cobre', hex: '#8f5c38' },
  { id: 'castana', nombre: 'Castaña', hex: '#7b4a2c' },
  { id: 'ebano-claro', nombre: 'Ébano claro', hex: '#65381f' },
  { id: 'ebano', nombre: 'Ébano', hex: '#4f2c18' },
  { id: 'ebano-profundo', nombre: 'Ébano profundo', hex: '#3b2113' },
];

const CABELLOS = [
  { id: 'negro', nombre: 'Negro', hex: '#17110e' },
  { id: 'negro-azulado', nombre: 'Negro azulado', hex: '#1b1a24' },
  { id: 'castano-oscuro', nombre: 'Castaño oscuro', hex: '#2e1f17' },
  { id: 'castano', nombre: 'Castaño', hex: '#4a3022' },
  { id: 'castano-claro', nombre: 'Castaño claro', hex: '#6d4b32' },
  { id: 'cobrizo', nombre: 'Cobrizo', hex: '#8a4a28' },
  { id: 'pelirrojo', nombre: 'Pelirrojo', hex: '#a2441f' },
  { id: 'rubio-oscuro', nombre: 'Rubio oscuro', hex: '#8b6b3e' },
  { id: 'rubio', nombre: 'Rubio', hex: '#c0975a' },
  { id: 'rubio-ceniza', nombre: 'Rubio ceniza', hex: '#b5a283' },
  { id: 'canoso', nombre: 'Canoso', hex: '#8f8c88' },
  { id: 'gris', nombre: 'Gris', hex: '#b5b3b0' },
  { id: 'blanco', nombre: 'Blanco', hex: '#e2e0dc' },
];

// [id, nombre, hex, familia, origen, costo 1-5, desde (año), hasta (año)]
const T = (id, nombre, hex, familia, origen, costo, desde = -10000, hasta = 3000) => ({ id, nombre, hex, familia, origen, costo, desde, hasta });

const TINTES = [
  // Neutros y fibras sin teñir
  T('blanco-lino', 'Blanco de lino blanqueado', '#efeadb', 'neutro', 'lino blanqueado al sol', 2),
  T('crudo', 'Crudo', '#dcd2b8', 'neutro', 'fibra sin teñir', 1),
  T('lana-clara', 'Lana clara', '#d8cdb3', 'neutro', 'lana de oveja blanca', 1),
  T('lana-parda', 'Lana parda', '#8a7556', 'neutro', 'lana de oveja parda', 1),
  T('lana-negra', 'Lana negra natural', '#3a332d', 'neutro', 'lana de oveja negra', 1),
  T('alpaca-cafe', 'Alpaca café', '#6e5239', 'neutro', 'fibra de alpaca', 1),
  T('alpaca-gris', 'Alpaca gris', '#8d8a85', 'neutro', 'fibra de alpaca', 1),
  T('algodon-crudo', 'Algodón crudo', '#e1d6bd', 'neutro', 'algodón', 1),
  T('cuero-claro', 'Cuero claro', '#b88c5c', 'cuero', 'cuero curtido vegetal', 2),
  T('cuero', 'Cuero', '#7d5533', 'cuero', 'cuero curtido', 2),
  T('cuero-oscuro', 'Cuero oscuro', '#4a2f1d', 'cuero', 'cuero con taninos', 2),
  T('piel-guanaco', 'Piel de guanaco', '#a8794a', 'cuero', 'piel de guanaco', 2),
  T('piel-nutria', 'Piel de nutria', '#4a3626', 'cuero', 'piel', 3),
  T('negro-humo', 'Negro de humo', '#1f1b19', 'neutro', 'negro de humo', 2),
  T('negro-agalla', 'Negro de agalla y hierro', '#262321', 'neutro', 'agallas y sulfato de hierro', 2, 100),
  // Azules
  T('indigo', 'Índigo', '#29407a', 'azul', 'índigo (Indigofera)', 3),
  T('indigo-claro', 'Índigo claro', '#5873b0', 'azul', 'índigo, baño corto', 2),
  T('indigo-profundo', 'Índigo profundo', '#1c2a58', 'azul', 'índigo, baños repetidos', 3),
  T('pastel', 'Azul de pastel (glasto)', '#3f5d93', 'azul', 'glasto (Isatis tinctoria)', 2, -500, 1900),
  T('azul-egipcio', 'Azul egipcio', '#2f8ca3', 'azul', 'silicato de cobre y calcio', 4, -3000, 400),
  T('azul-celeste', 'Azul celeste', '#7aa4cc', 'azul', 'índigo diluido', 2),
  T('turquesa', 'Turquesa', '#2a9d9a', 'azul', 'mezcla de índigo y amarillo vegetal', 3),
  T('azul-ultramar', 'Azul ultramar', '#23338f', 'azul', 'lapislázuli', 5, 600, 1830),
  T('azul-prusia', 'Azul de Prusia', '#1d3a5c', 'azul', 'pigmento sintético', 2, 1710),
  T('azul-andino', 'Azul andino', '#305f8e', 'azul', 'índigo y tara', 2),
  // Rojos
  T('rojo-granza', 'Rojo de granza', '#a8382a', 'rojo', 'raíz de granza (Rubia)', 2, -2000, 1870),
  T('rojo-cochinilla', 'Rojo de cochinilla', '#9e1b32', 'rojo', 'cochinilla (Dactylopius)', 4, 1000),
  T('carmin', 'Carmín', '#8c1233', 'rojo', 'cochinilla y alumbre', 5, 1500),
  T('escarlata', 'Escarlata', '#c2271f', 'rojo', 'quermes o cochinilla', 4, 600),
  T('rojo-ocre', 'Rojo ocre', '#a14a2a', 'rojo', 'ocre rojo', 1),
  T('rojo-tierra', 'Rojo tierra', '#8a3a24', 'rojo', 'tierras con óxido de hierro', 1),
  T('rojo-brasil', 'Rojo palo de Brasil', '#b03a34', 'rojo', 'palo de Brasil', 3, 1100, 1870),
  T('rojo-cartamo', 'Rojo de cártamo', '#c8463c', 'rojo', 'cártamo', 3),
  T('rosa-palido', 'Rosa pálido', '#e0a9a0', 'rojo', 'granza diluida', 2),
  T('rosa-cochinilla', 'Rosa cochinilla', '#c4617c', 'rojo', 'cochinilla diluida', 3, 1000),
  T('bermellon', 'Bermellón', '#d3402c', 'rojo', 'cinabrio', 5, -200, 1900),
  T('borravino', 'Borravino', '#5d1a2c', 'rojo', 'cochinilla y hierro', 4, 1000),
  T('rojo-mapuche', 'Rojo mapuche', '#9b2a22', 'rojo', 'tintes de raíces y cortezas', 2, 1000),
  // Púrpuras
  T('purpura-tiro', 'Púrpura de Tiro', '#6c1f5a', 'purpura', 'molusco Murex', 5, -1500, 1450),
  T('purpura-lichen', 'Púrpura de líquenes', '#6a4a78', 'purpura', 'orchilla', 3, 1200, 1860),
  T('malva', 'Malva', '#8d6aa0', 'purpura', 'índigo y cochinilla', 3),
  T('violeta', 'Violeta', '#54307a', 'purpura', 'índigo sobre cochinilla', 3),
  T('mauveina', 'Malva sintético', '#8e3f84', 'purpura', 'anilina de Perkin', 2, 1856),
  // Amarillos y naranjas
  T('azafran', 'Azafrán', '#e2a227', 'amarillo', 'estigmas de azafrán', 5),
  T('gualda', 'Gualda', '#d8b838', 'amarillo', 'gualda (Reseda luteola)', 2, -1000, 1900),
  T('cartamo-amarillo', 'Amarillo de cártamo', '#e5c04c', 'amarillo', 'cártamo', 3),
  T('curcuma', 'Cúrcuma', '#d9962a', 'amarillo', 'cúrcuma', 2),
  T('ocre-amarillo', 'Ocre amarillo', '#c49a3f', 'amarillo', 'ocre', 1),
  T('amarillo-chilca', 'Amarillo de chilca', '#c9b04a', 'amarillo', 'planta tintórea andina', 1),
  T('naranja-achiote', 'Naranja de achiote', '#d9692a', 'naranja', 'achiote (Bixa orellana)', 2),
  T('naranja-henna', 'Naranja de henna', '#b5602a', 'naranja', 'henna', 2),
  T('naranja-quercitron', 'Naranja quercitrón', '#d28526', 'naranja', 'corteza de quercitrón', 2, 1780, 1900),
  T('oro', 'Oro', '#caa24a', 'metal', 'hilo de oro', 5),
  T('plata', 'Plata', '#b9bec6', 'metal', 'hilo de plata', 4),
  T('bronce', 'Bronce', '#a2713a', 'metal', 'bronce', 3, -3000),
  T('cobre', 'Cobre', '#a85b3a', 'metal', 'cobre', 2, -5000),
  T('hierro', 'Hierro', '#5c5e63', 'metal', 'hierro', 3, -1200),
  // Verdes
  T('verde-olivo', 'Verde olivo', '#6b6d33', 'verde', 'índigo sobre gualda', 2),
  T('verde-musgo', 'Verde musgo', '#566133', 'verde', 'índigo sobre gualda', 2),
  T('verde-hoja', 'Verde hoja', '#4c7a3c', 'verde', 'índigo y gualda', 2),
  T('verde-botella', 'Verde botella', '#1f4a38', 'verde', 'índigo y gualda, baños repetidos', 3),
  T('verde-jade', 'Verde jade', '#4a8a6c', 'verde', 'jade, malaquita o tintes', 5),
  T('verde-esmeralda', 'Verde esmeralda', '#117a4d', 'verde', 'pigmento mineral', 4, 1800),
  T('verde-malaquita', 'Verde malaquita', '#2f8a5a', 'verde', 'malaquita', 4),
  T('verde-andino', 'Verde andino', '#5a7a3a', 'verde', 'índigo y chilca', 2),
  // Marrones y tierras
  T('nogal', 'Nogal', '#5a3b22', 'marron', 'cáscara de nuez', 1),
  T('castana', 'Castaña', '#7b5434', 'marron', 'corteza de castaño', 1),
  T('tabaco', 'Tabaco', '#8a5f35', 'marron', 'corteza y cáscaras', 1),
  T('siena', 'Siena', '#a05f2c', 'marron', 'tierra de siena', 1),
  T('umbra', 'Sombra natural', '#5d4630', 'marron', 'tierra de sombra', 1),
  T('arcilla', 'Arcilla', '#b27a52', 'marron', 'arcilla', 1),
  T('barro', 'Barro', '#9a7a5a', 'marron', 'barro', 1),
  T('kaki', 'Caqui', '#a39466', 'marron', 'tintes vegetales', 1, 1840),
  T('gris-ceniza', 'Gris ceniza', '#8b8a86', 'neutro', 'ceniza y taninos', 1),
  T('gris-pizarra', 'Gris pizarra', '#5d646b', 'neutro', 'agalla y hierro', 2),
  T('piedra', 'Piedra', '#b4ab99', 'neutro', 'tierras claras', 1),
  T('marfil', 'Marfil', '#ece3cc', 'neutro', 'blanqueado', 2),
  T('rosa-antiguo', 'Rosa antiguo', '#b98a8e', 'rojo', 'granza y ceniza', 2, 1650),
  T('verde-agua', 'Verde agua', '#8dbba3', 'verde', 'índigo claro y gualda', 2),
  T('azul-plomo', 'Azul plomo', '#4b6178', 'azul', 'índigo y agalla', 2, 1600),
  T('mostaza', 'Mostaza', '#b99a2a', 'amarillo', 'gualda y hierro', 2),
  // Tintes y pigmentos industriales (siglos XIX y XX)
  T('azul-denim', 'Azul denim', '#3a5784', 'azul', 'índigo sobre sarga de algodón', 2, 1873),
  T('denim-oscuro', 'Denim crudo oscuro', '#1f3358', 'azul', 'índigo sobre sarga de algodón', 2, 1873),
  T('denim-claro', 'Denim lavado claro', '#7a96bf', 'azul', 'índigo desteñido', 2, 1900),
  T('denim-gris', 'Denim gris', '#5c6470', 'neutro', 'índigo con negro de azufre', 2, 1900),
  T('denim-negro', 'Denim negro', '#2a2d33', 'neutro', 'negro de azufre', 2, 1900),
  T('azul-marino', 'Azul marino', '#1d2a4a', 'azul', 'índigo y campeche', 2, 1750),
  T('negro-carbon', 'Negro carbón', '#1c1c1e', 'neutro', 'negro de anilina', 1, 1860),
  T('blanco-optico', 'Blanco brillante', '#f5f4ef', 'neutro', 'blanqueo químico', 1, 1800),
  T('beige', 'Beige', '#cdb99a', 'neutro', 'fibra clara', 1),
  T('gris-claro', 'Gris claro', '#b9b9b6', 'neutro', 'mezcla de lanas', 1),
  T('gris-oscuro', 'Gris oscuro', '#55565a', 'neutro', 'mezcla de lanas', 1),
  T('caqui-militar', 'Caqui militar', '#7a7448', 'marron', 'tintes de azufre', 1, 1880),
  T('verde-militar', 'Verde militar', '#4b5239', 'verde', 'tintes de azufre', 1, 1900),
  T('azul-militar', 'Azul de uniforme', '#2c3e63', 'azul', 'índigo', 2, 1750),
  T('terracota', 'Terracota', '#b5623b', 'naranja', 'tierras y óxidos', 1),
  T('rosa-fucsia', 'Fucsia', '#c2338a', 'rojo', 'anilina (fucsina)', 2, 1859),
  T('amarillo-limon', 'Amarillo limón', '#dccf44', 'amarillo', 'amarillo de cromo', 2, 1810),
  T('verde-lima', 'Verde manzana', '#8fb63c', 'verde', 'anilina', 2, 1860),
  T('naranja-vivo', 'Naranja vivo', '#e2762a', 'naranja', 'anilina', 2, 1870),
  T('azul-real', 'Azul real', '#2a4fa8', 'azul', 'anilina', 2, 1860),
  T('rojo-vivo', 'Rojo vivo', '#c82a2a', 'rojo', 'anilina (alizarina)', 2, 1868),
  T('burdeos', 'Burdeos', '#6b1d2e', 'rojo', 'alizarina', 2, 1868),
  T('verde-bosque', 'Verde bosque', '#2e5a3a', 'verde', 'mezcla de anilinas', 2, 1870),
  T('lila', 'Lila', '#a58bc4', 'purpura', 'anilina (mauveína)', 2, 1856),
  T('crema', 'Crema', '#efe2c0', 'neutro', 'blanqueo suave', 1),
  T('camel', 'Camel', '#b58a55', 'marron', 'lana de camello', 2),
  T('chocolate', 'Chocolate', '#4a2e22', 'marron', 'taninos', 1),
  T('rosa-viejo', 'Rosa empolvado', '#d4a5a5', 'rojo', 'granza diluida', 1, 1900),
  T('verde-oliva-oscuro', 'Verde oliva oscuro', '#4a4d28', 'verde', 'índigo y gualda', 2),
];

const TINTES_POR_ID = Object.fromEntries(TINTES.map((t) => [t.id, t]));

Object.assign(__e, { PIELES, CABELLOS, TINTES, TINTES_POR_ID });
return __e;
})();

__m['datos/ayuda'] = (() => {
const __e = {};
// Ayudantes para escribir los datos de forma compacta.

const L = (s) => s.split(/\s+/).filter(Boolean);

const COL = { principal: 'A', secundario: 'B', ribete: 'C', detalle: 'D' };
/** Prenda: id, nombre, ranura, plantilla, parámetros y papeles de color (A principal, B secundario, C ribete, D detalle). */
const P = (id, nombre, ranura, plantilla, params = {}, col = {}) => ({ id, nombre, ranura, plantilla, params, col: { ...COL, ...col } });

/**
 * Atuendo: id, nombre, región, [año desde, hasta], sexo, clase, prendas, paleta de tintes por letra y extras
 * (peinados, barbas, tocados, pieles, nota, fuente —la referencia que respalda el traje— y conf: 'alta' | 'media' | 'baja', cuán respaldada y específica es la representación;
 * 'alta' exige `fuente`).
 */
const A = (id, nombre, region, epoca, sexo, clase, prendas, paleta, extra = {}) => ({
  id, nombre, region, epoca, sexo, clase,
  prendas: L(prendas),
  paleta: Object.fromEntries(Object.entries(paleta).map(([k, v]) => [k, L(v)])),
  peinados: L(extra.peinados ?? 'corto'),
  barbas: L(extra.barbas ?? 'ninguna'),
  tocados: L(extra.tocados ?? 'ninguno'),
  pieles: extra.pieles ? L(extra.pieles) : null,
  nota: extra.nota ?? '',
  fuente: extra.fuente ?? '',
  confianza: extra.conf ?? 'media',
});

/** Etnia o cultura (ver etnias.js para el significado de cada campo). */
const E = (id, nombre, d) => ({
  id, nombre, grupo: d.grupo, regiones: L(d.regiones), prefijos: d.prefijos ? L(d.prefijos) : null,
  pieles: L(d.pieles), cabellos: d.cabellos, peinados: L(d.peinados ?? ''), barba: d.barba ?? 0.3,
  altura: d.altura, corpulencia: d.corpulencia ?? [['delgada', 1], ['media', 4], ['robusta', 1.5]], nota: d.nota ?? '', fuente: d.fuente ?? '',
  confianza: d.conf ?? 'media',
});

// Distribuciones de color de cabello ([id, peso]) que comparten varias etnias.
const OSC = [['negro', 7], ['negro-azulado', 2], ['castano-oscuro', 1]];
const MORENO = [['negro', 5], ['negro-azulado', 1.5], ['castano-oscuro', 3], ['castano', 1.5]];
const CLARO = [['castano', 3], ['castano-claro', 3], ['rubio-oscuro', 2], ['rubio', 2], ['castano-oscuro', 2], ['pelirrojo', 0.8], ['negro', 1]];
const MED = [['negro', 4], ['castano-oscuro', 4], ['castano', 2], ['castano-claro', 0.5]];
const MESTIZO = [['negro', 4], ['castano-oscuro', 4], ['castano', 2], ['castano-claro', 1]];

// Paletas de tintes y tonos de piel que comparten varios archivos de atuendos (antes copiadas en cada uno).
const BL = 'blanco-lino crudo';
const CLAROS = 'porcelana clara clara-calida';
const MEST = 'clara-calida oliva-clara oliva canela';
const OLIVA = 'clara-calida oliva-clara oliva';
const OSCURA = 'canela bronce cobre';
const EBANO = 'castana ebano-claro ebano ebano-profundo';
const CIUDAD = { A: 'lana-negra gris-oscuro azul-marino', B: 'crema', C: 'lana-negra', D: 'plata' };
const TRAB = { A: 'caqui-militar azul-marino kaki', B: 'crudo', C: 'lana-negra', D: 'cobre' };
const VEST = { A: 'azul-marino burdeos verde-bosque rosa-viejo', B: 'crema', C: 'lana-negra', D: 'plata' };
const GALA = { A: 'negro-carbon burdeos azul-marino verde-esmeralda', B: 'crema', C: 'plata', D: 'oro' };
const TIERRA = { A: 'lana-parda umbra nogal', B: 'crudo', C: 'rojo-ocre', D: 'cobre' };

/** Atuendo de mujer o de hombre: igual que A con el sexo ya puesto. */
const F = (id, n, region, ep, clase, prendas, paleta, extra) => A(id, n, region, ep, 'f', clase, prendas, paleta, extra);
const M = (id, n, region, ep, clase, prendas, paleta, extra) => A(id, n, region, ep, 'm', clase, prendas, paleta, extra);

Object.assign(__e, { L, P, A, E, OSC, MORENO, CLARO, MED, MESTIZO, BL, CLAROS, MEST, OLIVA, OSCURA, EBANO, CIUDAD, TRAB, VEST, GALA, TIERRA, F, M });
return __e;
})();

__m['datos/prendas-ampliadas'] = (() => {
const __e = {};
// Prendas de la ampliación: Asia central y el Himalaya, Europa regional, el mundo antiguo, África, la India y el
// sudeste asiático, las Américas indígenas y mestizas, y Oceanía. Misma forma que prendas.js.

const { P } = __m['datos/ayuda'];

const pat = (tipo, escala = 1, rol = 'secundario') => ({ tipo, escala, rol });

const PRENDAS_AMPLIADAS = [
  // ===== Asia central, Siberia, Cáucaso, Himalaya =====
  P('del-mongol', 'Del mongol (túnica larga cruzada)', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'alto', cruzada: 'der', cintura: 0.7, holgura: 0.025, ribete: true, puños: true, ribeteBajo: 0.02, pliegues: 3 }),
  P('faja-del', 'Faja ancha del del', 'faja', 'faja', { y: 'cintura', ancho: 0.04, doble: true, cuelga: 'der' }, { principal: 'C' }),
  P('botas-mongolas', 'Botas mongolas de punta alzada', 'calzado', 'calzado', { tipo: 'botas-altas' }),
  P('chuba-tibetana', 'Chuba tibetana', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'ancha', escote: 'v', prof: 0.9, cruzada: 'der', cintura: 0.5, holgura: 0.035, ribete: true, ribeteBajo: 0.03, pliegues: 4 }),
  P('pangden', 'Pangden (delantal rayado)', 'sobre', 'delantal', { largo: 'tobillo', peto: false, vuelo: 0.03, patron: pat('rayas-h', 0.9) }),
  P('botas-tibetanas', 'Botas de fieltro tibetanas', 'calzado', 'calzado', { tipo: 'botas' }),
  P('chapan', 'Chapán (abrigo acolchado)', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'larga', botones: 0, cintura: 0.8, holgura: 0.03, abertura: 0.01, aberturaBajo: 0.03, ribete: true, puños: true, bordadoBorde: true }),
  P('chokha', 'Chokha caucásica', 'sobre', 'chaqueta', { largo: 'rodilla', manga: 'larga', botones: 0, cintura: 0.2, abertura: 0.01, aberturaBajo: 0.06, vuelo: 0.05, puños: true, bordadoBorde: true, ribete: true }),
  P('vestido-caucasico', 'Vestido largo caucásico de talle ajustado', 'torso', 'tunica', { largo: 'suelo', manga: 'campana', escote: 'cuadrado', prof: 0.8, cintura: 0, holgura: 0.002, vuelo: 0.07, ribeteBajo: 0.03, puños: true, ribete: true }),
  P('parka-siberiana', 'Parka larga de piel de reno', 'sobre', 'chaqueta', { largo: 'rodilla', manga: 'larga', botones: 0, cintura: 1, holgura: 0.045, abertura: 0.008, aberturaBajo: 0.01, ribete: true, patron: pat('piel', 1.2) }),
  // ===== Europa rural y regional =====
  P('dirndl-corpino', 'Corpiño de dirndl', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'sin', botones: 8, cintura: 0, abertura: 0.006, aberturaBajo: 0.008, ribete: true }),
  P('falda-dirndl', 'Falda de dirndl', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.1, pliegues: 8, ribeteBajo: 0.02 }),
  P('delantal-dirndl', 'Delantal de dirndl', 'sobre', 'delantal', { largo: 'pantorrilla', peto: false, vuelo: 0.04 }),
  P('lederhosen', 'Lederhosen (pantalón corto de cuero)', 'inferior', 'pantalon', { largo: 'rodilla', ancho: 'recto', cintura: 'natural', cordon: true }),
  P('gunj', 'Gunj (abrigo de lana bordado)', 'sobre', 'chaqueta', { largo: 'muslo', manga: 'larga', botones: 0, cintura: 0.7, abertura: 0.008, aberturaBajo: 0.012, ribete: true, bordadoBorde: true, puños: true }),
  P('opanke', 'Opanke (sandalias de cuero atadas)', 'calzado', 'calzado', { tipo: 'sandalia-romana' }),
  P('alpargatas', 'Alpargatas', 'calzado', 'calzado', { tipo: 'zapatillas' }),
  P('traje-corto', 'Chaqueta corta andaluza', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'larga', botones: 0, cintura: 0.3, abertura: 0.01, aberturaBajo: 0.02, bordadoBorde: true, puños: true }),
  P('pantalon-alto', 'Pantalón ajustado de talle alto', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'ajustado', cordon: false }),
  P('faja-vasca', 'Faja roja', 'faja', 'faja', { y: 'cintura', ancho: 0.034, doble: true }, { principal: 'C' }),
  P('chaleco-campesino', 'Chaleco de paño', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 6, cintura: 0.5, abertura: 0.01, aberturaBajo: 0.014 }),
  P('blusa-regional', 'Blusa blanca regional', 'torso', 'tunica', { largo: 'cadera', manga: 'globo', escote: 'cuadrado', cintura: 0.9, holgura: 0.014, puños: true, ribete: true, bordadoPecho: 'flores' }),
  P('vestido-regional', 'Vestido regional de lana', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'tres-cuartos', escote: 'cuadrado', prof: 0.8, cintura: 0.2, holgura: 0.004, vuelo: 0.09, ribeteBajo: 0.025, ribete: true, pliegues: 4 }),
  P('mandil-regional', 'Mandil bordado', 'sobre', 'delantal', { largo: 'pantorrilla', peto: false, vuelo: 0.03, patron: pat('flores', 0.8) }),
  // ===== Mundo antiguo (Mediterráneo y Asia occidental) =====
  P('falda-minoica', 'Falda minoica de volantes', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.13, cintura: 'baja', volantes: 4, pliegues: 4, bajo: 'ondulado', ribeteBajo: 0.025, bandaPatron: 'zigzag' }),
  P('faldellin-egeo', 'Faldellín egeo de hombre', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.03, cintura: 'baja', pliegues: 3, ribeteBajo: 0.03, bandaPatron: 'rombos' }),
  P('tunica-fenicia', 'Túnica fenicia con orla', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'redondo', cintura: 0.7, holgura: 0.016, bandas: 'lateral', ribeteBajo: 0.03, puños: true }),
  P('kandys', 'Kandys persa (túnica sobre los hombros)', 'sobre', 'chaqueta', { largo: 'rodilla', manga: 'ancha', botones: 0, cintura: 0.8, abertura: 0.01, aberturaBajo: 0.03, ribete: true, bordadoBorde: true, holgura: 0.025 }),
  P('pantalon-persa', 'Anaxyrides (pantalón ajustado)', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'ajustado', patron: pat('rombos', 0.7) }),
  P('chaqueta-escita', 'Chaqueta escita cruzada', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'v', prof: 0.9, cruzada: 'der', cintura: 0.7, holgura: 0.016, ribete: true, puños: true, ribeteBajo: 0.015 }),
  P('pantalon-escita', 'Pantalón escita', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', puños: true }),
  P('tunica-etrusca', 'Túnica etrusca corta', 'torso', 'tunica', { largo: 'rodilla', manga: 'corta', escote: 'redondo', cintura: 0.9, holgura: 0.014, ribeteBajo: 0.02, ribete: false }),
  P('sagum', 'Sagum (capa corta de lana)', 'capa', 'capa', { largo: 'muslo', ancho: 0.8, cierre: 'broche', ribete: false }),
  P('tunica-ibera', 'Túnica ibera con orla', 'torso', 'tunica', { largo: 'rodilla', manga: 'corta', escote: 'ojal', cintura: 0.9, holgura: 0.016, ribeteBajo: 0.02, bajo: 'dentado' }),
  P('manto-persa', 'Manto largo persa', 'capa', 'capa', { largo: 'tobillo', ancho: 1, cierre: 'broche', ribete: true, ribeteBajo: true }),
  // ===== Medio Oriente =====
  P('thobe-palestino', 'Thobe palestino bordado (tatreez)', 'torso', 'tunica', { largo: 'suelo', manga: 'ancha', escote: 'cuadrado', prof: 0.5, cintura: 0.8, holgura: 0.02, bordadoPecho: 'cruces', bandaPatron: 'cruces', ribeteBajo: 0.04, puños: true, ribete: true }),
  P('jalabiya-algodon', 'Jalabiya de algodón', 'torso', 'tunica', { largo: 'suelo', manga: 'larga', escote: 'ojal', cintura: 1, holgura: 0.03, botones: 3, puños: false, ribete: false }),
  P('chaleco-kurdo', 'Chaleco kurdo bordado', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 0, cintura: 0.5, abertura: 0.012, aberturaBajo: 0.016, bordadoBorde: true, ribete: true }),
  // ===== África =====
  P('shamma', 'Shamma (manto de algodón con orla)', 'capa', 'drapeado', { largo: 'tobillo', lado: 'izq', pliegues: 5, orla: true }),
  P('habesha-kemis', 'Habesha kemis', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'cuadrado', prof: 0.7, cintura: 0.5, holgura: 0.012, vuelo: 0.06, bordadoPecho: 'cruces', bandaPatron: 'cruces', ribeteBajo: 0.04, puños: true, ribete: true }),
  P('gandora', 'Gandora (túnica larga del Sahel)', 'torso', 'tunica', { largo: 'suelo', manga: 'ancha', escote: 'v', prof: 0.6, cintura: 1, holgura: 0.045, bordadoPecho: 'zigzag', pliegues: 4, ribete: true }),
  P('buba', 'Buba (blusa holgada yoruba)', 'torso', 'tunica', { largo: 'cadera', manga: 'ancha', escote: 'redondo', cintura: 1, holgura: 0.03, bordadoPecho: 'rombos', ribete: true }),
  P('iro', 'Iro (falda envuelta yoruba)', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.01, pliegues: 2, ribeteBajo: 0.02, patron: pat('rombos', 1.1) }),
  P('sokoto', 'Sokoto (pantalón amplio)', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'amplio', cordon: true }),
  P('manta-xhosa', 'Manta xhosa', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'der', pliegues: 4, orla: true, patron: pat('bandas', 1) }),
  P('falda-cuero', 'Falda de cuero con flecos', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.03, flecos: true, bajo: 'dentado', pliegues: 6, cintura: 'baja' }),
  P('kaross', 'Kaross (capa de piel de antílope)', 'capa', 'capa', { largo: 'cadera', ancho: 0.6, cierre: 'abierta', ribete: false, patron: pat('piel', 1.1) }),
  P('lamba', 'Lamba malgache', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'izq', pliegues: 4, orla: true, patron: pat('rayas', 1.1) }),
  P('tob-sudanes', 'Tob sudanés', 'capa', 'drapeado', { largo: 'suelo', lado: 'der', pliegues: 6, orla: true, patron: pat('flores', 1.2) }),
  P('collar-ancho-cuentas', 'Collares anchos de cuentas', 'cuello', 'cuello', { tipo: 'pectoral' }, { principal: 'D' }),
  // ===== India y sudeste asiático =====
  P('ghagra', 'Ghagra (falda amplia)', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.17, pliegues: 8, volantes: 2, bandaPatron: 'flores', ribeteBajo: 0.04 }),
  P('kameez', 'Kameez (túnica larga)', 'torso', 'tunica', { largo: 'rodilla', manga: 'larga', escote: 'redondo', prof: 0.6, cintura: 0.6, holgura: 0.012, bordadoPecho: 'flores', ribete: true, puños: true }),
  P('salwar', 'Salwar (pantalón bombacho)', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'bombacho', puños: true }),
  P('pheran', 'Pheran de Cachemira', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'ancha', escote: 'redondo', cintura: 1, holgura: 0.05, bordadoPecho: 'flores', ribeteBajo: 0.03, puños: true, ribete: true }),
  P('veshti', 'Veshti (lienzo envuelto)', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.01, pliegues: 3, ribeteBajo: 0.03, cintura: 'natural' }),
  P('angavastram', 'Angavastram (paño sobre los hombros)', 'capa', 'chal', { largo: 'cintura', flecos: false }),
  P('ao-dai', 'Áo dài', 'torso', 'tunica', { largo: 'suelo', manga: 'larga', escote: 'alto', cintura: 0, holgura: 0.002, vuelo: 0.01, cruzada: 'der', ribete: true, bajo: 'recto' }),
  P('pantalon-seda', 'Pantalón amplio de seda', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'amplio' }),
  P('barong', 'Barong tagalog', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'ojal', cintura: 1, holgura: 0.016, botones: 0, bordadoPecho: 'rombos', puños: true, ribete: false, bajo: 'curvo' }),
  P('baro-saya', 'Baro (blusa de mangas amplias)', 'torso', 'tunica', { largo: 'cadera', manga: 'campana', escote: 'cuadrado', cintura: 0.9, holgura: 0.012, ribete: true, vuelo: 0.05 }),
  P('saya', 'Saya (falda larga)', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.1, pliegues: 6, ribeteBajo: 0.02 }),
  P('panuelo-filipino', 'Pañuelo de piña', 'capa', 'chal', { largo: 'pecho', flecos: false }),
  P('longyi', 'Longyi birmano', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.01, pliegues: 2, cintura: 'natural', patron: pat('cuadros', 1) }),
  P('beskap', 'Beskap javanés', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 8, cintura: 0.4, abertura: 0.006, aberturaBajo: 0.008, puños: true }),
  P('sarong-batik', 'Sarong de batik', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.01, pliegues: 2, ribeteBajo: 0.02, patron: pat('rombos', 1) }),
  P('attus', 'Attus ainu (túnica con motivos)', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'larga', escote: 'v', prof: 0.6, cruzada: 'izq', cintura: 0.9, holgura: 0.025, bandaPatron: 'escalonado', bordadoPecho: 'escalonado', ribeteBajo: 0.04, puños: true }),
  P('changpao', 'Changpao manchú', 'torso', 'tunica', { largo: 'tobillo', manga: 'campana', escote: 'alto', cruzada: 'der', cintura: 0.6, holgura: 0.016, ribete: true, ribeteBajo: 0.025, puños: true, vuelo: 0.01 }),
  P('magua', 'Magua (chaqueta corta qing)', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'ancha', botones: 5, cintura: 0.8, abertura: 0.008, aberturaBajo: 0.01, puños: true, ribete: true }),
  // ===== Américas =====
  P('cushma', 'Cushma amazónica con diseños geométricos', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'ojal', cintura: 1, holgura: 0.03, patron: pat('kene', 1), ribeteBajo: 0.02, ribete: false }),
  P('tipoy', 'Tipoy (vestido largo sin mangas)', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'sin', escote: 'redondo', cintura: 0.8, holgura: 0.012, bandaPatron: 'rombos', ribeteBajo: 0.04 }),
  P('faja-fibra', 'Cordón de fibra a la cintura', 'faja', 'faja', { y: 'cadera', ancho: 0.008, cuelga: 'der' }),
  P('nagua', 'Nagua (falda corta de algodón)', 'inferior', 'falda', { largo: 'muslo', vuelo: 0.02, pliegues: 3, cintura: 'baja', ribeteBajo: 0.02, patron: pat('rayas', 1) }),
  P('collar-cemi', 'Collar con colgante de oro', 'cuello', 'cuello', { tipo: 'collar', colgante: true }, { principal: 'D' }),
  P('blusa-terciopelo', 'Blusa de terciopelo', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'cuadrado', cintura: 0.9, holgura: 0.014, puños: true, ribete: true, botones: 0, bajo: 'curvo' }),
  P('falda-navajo', 'Falda de tres volantes', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.15, pliegues: 4, volantes: 3, ribeteBajo: 0.025 }),
  P('cinturon-concho', 'Cinturón de plata de conchas', 'faja', 'faja', { y: 'cintura', ancho: 0.026, doble: true, hebilla: true }, { principal: 'D' }),
  P('kilt-haudenosaunee', 'Falda corta de gamuza con cuentas', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.03, cintura: 'baja', pliegues: 3, flecos: true, ribeteBajo: 0.04, bandaPatron: 'rombos' }),
  P('manto-chilkat', 'Manto tejido del noroeste del Pacífico', 'capa', 'capa', { largo: 'rodilla', ancho: 0.7, cierre: 'abierta', ribete: true, ribeteBajo: true, patron: pat('jaspe', 1) }),
  P('chaqueta-patchwork', 'Chaqueta de retazos', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'larga', botones: 0, cintura: 0.7, abertura: 0.008, aberturaBajo: 0.012, patron: pat('bandas', 0.7), puños: true }),
  P('vestido-calico', 'Vestido de percal estampado', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'tres-cuartos', escote: 'cuadrado', cintura: 0.4, holgura: 0.012, vuelo: 0.07, patron: pat('flores', 0.9), ribete: true }),
  P('huipil-tehuano', 'Huipil tehuano bordado', 'torso', 'tunica', { largo: 'cadera', manga: 'sin', escote: 'cuadrado', cintura: 0.9, holgura: 0.012, bordadoPecho: 'flores', bandaPatron: 'flores', ribeteBajo: 0.04, ribete: true }),
  P('enagua-tehuana', 'Enagua tehuana de volantes', 'inferior', 'falda', { largo: 'suelo', vuelo: 0.16, pliegues: 6, volantes: 2, bandaPatron: 'flores', ribeteBajo: 0.05 }),
  P('quechquemitl', 'Quechquémitl (manto triangular)', 'capa', 'poncho', { largo: 'pecho', ancho: 0.6, listas: 0, punta: 0.07, patron: pat('rombos', 0.9) }),
  P('falda-raramuri', 'Falda rarámuri de colores', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.14, pliegues: 6, volantes: 3, patron: pat('flores', 1) }),
  P('camisa-raramuri', 'Camisa rarámuri de mangas amplias', 'torso', 'tunica', { largo: 'muslo', manga: 'ancha', escote: 'ojal', cintura: 1, holgura: 0.03, puños: true, ribete: false }),
  P('delantal-bordado', 'Delantal bordado de flores', 'sobre', 'delantal', { largo: 'pantorrilla', peto: true, vuelo: 0.03, patron: pat('flores', 1) }),
  P('chaqueta-charra', 'Chaqueta de charro con botonadura', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'larga', botones: 8, cintura: 0.4, abertura: 0.006, aberturaBajo: 0.008, bordadoBorde: true, puños: true, solapa: false }),
  P('pantalon-charro', 'Pantalón de charro con botonadura', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'campana', puños: false }),
  P('camisa-wixarika', 'Camisa wixárika bordada', 'torso', 'tunica', { largo: 'muslo', manga: 'ancha', escote: 'cuadrado', cintura: 1, holgura: 0.025, bordadoPecho: 'rombos', bandaPatron: 'rombos', ribeteBajo: 0.03, ribete: true }),
  P('pantalon-wixarika', 'Pantalón wixárika bordado', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'amplio', puños: true, patron: pat('rombos', 0.8) }),
  P('sarape', 'Sarape', 'capa', 'poncho', { largo: 'muslo', ancho: 1.05, listas: 5, punta: 0.04, patron: pat('rombos', 1) }),
  // ===== Oceanía =====
  P('korowai', 'Korowai (manto de fibra y plumas)', 'capa', 'capa', { largo: 'pantorrilla', ancho: 0.8, cierre: 'abierta', ribete: true, ribeteBajo: true, patron: pat('piel', 1) }),
  P('piupiu', 'Piupiu (falda de lino con flecos)', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.03, cintura: 'baja', flecos: true, bajo: 'dentado', pliegues: 9, bandaPatron: 'escalonado' }),
  P('hei-tiki', 'Colgante de jade (hei-tiki)', 'cuello', 'cuello', { tipo: 'collar', colgante: true }, { principal: 'D' }),
  P('capa-canguro', 'Capa de piel de canguro', 'capa', 'capa', { largo: 'cadera', ancho: 0.6, cierre: 'abierta', ribete: false, patron: pat('piel', 1.1) }),
  P('ahu-ula', 'Ahu ula (capa de plumas)', 'capa', 'capa', { largo: 'pantorrilla', ancho: 0.7, cierre: 'broche', ribete: true, ribeteBajo: true, patron: pat('rombos', 0.9) }),
  P('kihei', 'Kihei (manto de tapa)', 'capa', 'drapeado', { largo: 'rodilla', lado: 'der', pliegues: 3, orla: false, patron: pat('rayas', 1) }),
  P('malo', 'Malo (taparrabo de tapa)', 'inferior', 'taparrabo', { largo: 'muslo', ancho: 0.035, punta: 0.015, ribeteBajo: true }),
  P('pau', 'Pa\'u (falda de tapa)', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.03, cintura: 'baja', pliegues: 3, patron: pat('zigzag', 1) }),
  P('taovala', 'Taʻovala (esterilla a la cintura)', 'faja', 'faja', { y: 'cintura', ancho: 0.06, patron: pat('rayas-finas', 1.3), cuelga: 'der' }),
  P('sulu', 'Sulu (falda envuelta)', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.02, pliegues: 3, ribeteBajo: 0.03, patron: pat('ondas', 1) }),
  P('camisa-hawaiana-antigua', 'Blusa de tapa', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'redondo', cintura: 0.9, holgura: 0.014, ribete: true, patron: pat('zigzag', 1) }),
];

Object.assign(__e, { PRENDAS_AMPLIADAS });
return __e;
})();

__m['datos/prendas-siglo20'] = (() => {
const __e = {};
// Prendas de 1850 a 1980 (ropa de trabajo, de ciudad y de fiesta, uniformes) y prendas de Latinoamérica,
// Norteamérica y Europa de esa época. Misma forma que prendas.js. Sus colores (el denim solo en azules y grises,
// la camisa blanca casi siempre blanca…) están en colores-prendas.js.

const { P } = __m['datos/ayuda'];

const pat = (tipo, escala = 1, rol = 'secundario') => ({ tipo, escala, rol });

const PRENDAS_SIGLO20 = [
  // ===== Camisas, poleras, suéteres y blusas =====
  P('camisa-cuadros', 'Camisa a cuadros', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'alto', botones: 6, cintura: 0.9, holgura: 0.016, puños: true, ribete: false, patron: pat('cuadros', 0.8) }),
  P('camisa-trabajo', 'Camisa de trabajo', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'alto', botones: 5, cintura: 1, holgura: 0.02, puños: true, ribete: false }),
  P('camisa-manga-corta', 'Camisa de manga corta', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'alto', botones: 6, cintura: 1, holgura: 0.016, ribete: false }),
  P('camisa-denim', 'Camisa vaquera', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'alto', botones: 6, cintura: 0.9, holgura: 0.016, puños: true, ribete: false }),
  P('polera', 'Polera', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'redondo', prof: 0.5, cintura: 1, holgura: 0.014, ribete: true }),
  P('polera-rayas', 'Polera de rayas', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'barco', cintura: 1, holgura: 0.014, ribete: false, patron: pat('rayas-h', 0.9) }),
  P('sueter-lana', 'Suéter de lana', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'redondo', prof: 0.5, cintura: 1, holgura: 0.022, puños: true, ribete: true }),
  P('sueter-cuello-alto', 'Suéter de cuello alto', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'alto', cintura: 1, holgura: 0.018, puños: true, ribete: false }),
  P('sueter-nordico', 'Suéter tejido con motivos', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'redondo', prof: 0.4, cintura: 1, holgura: 0.024, puños: true, ribete: true, patron: pat('rombos', 0.9), bandaPatron: 'zigzag', ribeteBajo: 0.03 }),
  P('blusa-seda', 'Blusa de seda', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'v', prof: 0.7, cintura: 0.8, holgura: 0.012, puños: true, ribete: false }),
  P('blusa-cuello-bebe', 'Blusa de cuello redondo', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'alto', botones: 5, cintura: 0.9, holgura: 0.012, ribete: true }),
  P('guayabera', 'Guayabera', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'alto', botones: 8, pliegues: 4, cintura: 1, holgura: 0.02, ribete: false, bajo: 'recto' }),
  P('liquiliqui', 'Liquiliqui', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'alto', botones: 8, cintura: 0.9, holgura: 0.014, puños: true, ribete: true }),
  P('guardapolvo', 'Guardapolvo', 'torso', 'tunica', { largo: 'rodilla', manga: 'larga', escote: 'alto', botones: 6, cintura: 1, holgura: 0.02, puños: true, ribete: false }),
  P('camisa-manta', 'Camisa de manta', 'torso', 'tunica', { largo: 'muslo', manga: 'larga', escote: 'ojal', cintura: 1, holgura: 0.024, ribete: false }),
  P('camisa-aloha', 'Camisa estampada de manga corta', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'v', prof: 0.5, botones: 6, cintura: 1, holgura: 0.018, ribete: false, patron: pat('flores', 1.1) }),
  // ===== Vestidos =====
  P('vestido-flapper', 'Vestido de talle bajo de 1920', 'torso', 'tunica', { largo: 'rodilla', manga: 'sin', escote: 'v', prof: 0.8, cintura: 1, holgura: 0.012, bajo: 'ondulado', ribeteBajo: 0.02, ribete: true }),
  P('vestido-30', 'Vestido de corte al bies de 1930', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'v', prof: 0.8, cintura: 0.5, holgura: 0.006, vuelo: 0.08, pliegues: 4, ribete: false }),
  P('vestido-40', 'Vestido de hombros marcados de 1940', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'cuadrado', prof: 0.6, cintura: 0.1, holgura: 0.004, vuelo: 0.07, pliegues: 4, botones: 6, ribete: false }),
  P('vestido-50', 'Vestido de falda amplia de 1950', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'cuadrado', prof: 0.8, cintura: 0, holgura: 0.002, vuelo: 0.15, pliegues: 8, ribete: false }),
  P('vestido-casa', 'Vestido de casa abotonado', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'tres-cuartos', escote: 'redondo', botones: 8, cintura: 0.6, holgura: 0.008, vuelo: 0.07, ribete: false, pliegues: 3 }),
  P('vestido-novia', 'Vestido de novia', 'torso', 'tunica', { largo: 'suelo', manga: 'larga', escote: 'alto', botones: 9, cintura: 0, holgura: 0.002, vuelo: 0.15, pliegues: 6, ribeteBajo: 0.04, puños: true, ribete: false }),
  P('vestido-luto', 'Vestido de luto', 'torso', 'tunica', { largo: 'suelo', manga: 'larga', escote: 'alto', botones: 10, cintura: 0, holgura: 0.002, vuelo: 0.12, pliegues: 5, ribete: false }),
  P('vestido-fiesta', 'Vestido de fiesta largo', 'torso', 'tunica', { largo: 'suelo', manga: 'sin', escote: 'cuadrado', prof: 1, cintura: 0, holgura: 0.002, vuelo: 0.16, pliegues: 6, ribeteBajo: 0.03, ribete: true }),
  P('vestido-fiesta-manga', 'Vestido de gala con mangas', 'torso', 'tunica', { largo: 'suelo', manga: 'codo', escote: 'barco', cintura: 0, holgura: 0.002, vuelo: 0.15, pliegues: 6, ribeteBajo: 0.03, puños: true }),
  P('camison', 'Camisón', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'redondo', botones: 5, cintura: 1, holgura: 0.024, puños: true, ribete: true }),
  // ===== Sacos, chaquetas y abrigos =====
  P('saco-terno', 'Saco de terno', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 3, solapa: true, cintura: 0.4, abertura: 0.014, aberturaBajo: 0.05, puños: true, ribete: false }),
  P('saco-cruzado', 'Saco cruzado', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 6, solapa: true, cintura: 0.3, abertura: 0.01, aberturaBajo: 0.03, puños: true, ribete: false }),
  P('chaleco-terno', 'Chaleco de terno', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'sin', botones: 5, solapa: true, cintura: 0.3, abertura: 0.008, aberturaBajo: 0.012, ribete: false }),
  P('chaleco-lana', 'Chaleco de lana', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 5, cintura: 0.7, abertura: 0.012, aberturaBajo: 0.014, ribete: true }),
  P('cardigan', 'Cárdigan', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 5, cintura: 0.8, abertura: 0.012, aberturaBajo: 0.014, puños: true, ribete: true }),
  P('chaqueta-cuero', 'Chaqueta de cuero', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'larga', botones: 0, solapa: true, cintura: 0.6, abertura: 0.008, aberturaBajo: 0.01, puños: true, ribete: false }),
  P('chaqueta-denim', 'Chaqueta vaquera', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'larga', botones: 6, cintura: 0.7, abertura: 0.01, aberturaBajo: 0.012, puños: true, ribete: true }),
  P('chaqueta-militar', 'Chaqueta de uniforme', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 6, solapa: true, cintura: 0.4, abertura: 0.01, aberturaBajo: 0.014, puños: true, ribete: true }),
  P('gabardina', 'Gabardina', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'larga', botones: 6, solapa: true, cintura: 0.7, vuelo: 0.04, abertura: 0.012, aberturaBajo: 0.02, holgura: 0.022, puños: true, ribete: false }),
  P('abrigo-corto', 'Abrigo corto de lana', 'sobre', 'chaqueta', { largo: 'muslo', manga: 'larga', botones: 5, solapa: true, cintura: 0.6, abertura: 0.012, aberturaBajo: 0.02, holgura: 0.02, puños: false, ribete: false }),
  P('abrigo-pieles', 'Abrigo de piel', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'larga', botones: 0, cintura: 0.8, abertura: 0.01, aberturaBajo: 0.02, holgura: 0.03, patron: pat('piel', 1.2), ribete: false }),
  P('impermeable', 'Impermeable', 'sobre', 'chaqueta', { largo: 'rodilla', manga: 'larga', botones: 5, solapa: false, cintura: 0.9, abertura: 0.01, aberturaBajo: 0.014, holgura: 0.03, ribete: false }),
  P('traje-sastre', 'Chaqueta de traje sastre', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 4, solapa: true, cintura: 0.1, abertura: 0.01, aberturaBajo: 0.02, puños: false, ribete: false }),
  P('delantal-cocina', 'Delantal de cocina', 'sobre', 'delantal', { largo: 'pantorrilla', peto: true, vuelo: 0.02 }),
  P('delantal-obrero', 'Delantal de cuero de obrero', 'sobre', 'delantal', { largo: 'rodilla', peto: true, vuelo: 0.005 }),
  // ===== Pantalones, faldas =====
  P('pantalon-vestir', 'Pantalón de vestir', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'natural', puños: true }),
  P('pantalon-vaquero', 'Pantalón vaquero (jeans)', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'natural', puños: true }),
  P('pantalon-campana', 'Pantalón de campana', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'campana', cintura: 'baja' }),
  P('pantalon-trabajo', 'Pantalón de trabajo', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'natural', cordon: false, puños: false }),
  P('pantalon-overol', 'Overol: pantalón', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'natural', puños: true }),
  P('overol-peto', 'Overol: peto y tirantes', 'sobre', 'delantal', { largo: 'tobillo', peto: true, vuelo: 0 }),
  P('pantalon-militar', 'Pantalón de uniforme', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'natural', puños: true }),
  P('pantalon-golf', 'Pantalón bombacho a la rodilla', 'inferior', 'pantalon', { largo: 'bajo-rodilla', ancho: 'bombacho' }),
  P('short', 'Pantalón corto', 'inferior', 'pantalon', { largo: 'rodilla', ancho: 'recto', cintura: 'natural' }),
  P('calzon-manta', 'Calzón de manta', 'inferior', 'pantalon', { largo: 'pantorrilla', ancho: 'amplio', cordon: true }),
  P('falda-lapiz', 'Falda recta', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.01, cintura: 'natural', pliegues: 0 }),
  P('falda-campana', 'Falda de campana', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.16, cintura: 'natural', pliegues: 8 }),
  P('falda-plisada', 'Falda plisada', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.06, cintura: 'natural', pliegues: 12 }),
  P('falda-colegial', 'Falda de uniforme escolar', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.05, cintura: 'natural', pliegues: 10 }),
  // ===== Faja, capas y mantos =====
  P('cinturon-vaquero', 'Cinturón con hebilla grande', 'faja', 'faja', { y: 'cintura', ancho: 0.016, hebilla: true }),
  P('cinturon-fino', 'Cinturón fino', 'faja', 'faja', { y: 'cintura', ancho: 0.01, hebilla: true }),
  P('chamanto', 'Chamanto', 'capa', 'poncho', { largo: 'cadera', ancho: 1, listas: 4, punta: 0.05, patron: pat('bandas', 0.9) }),
  P('chamanto-fiesta', 'Chamanto de fiesta', 'capa', 'poncho', { largo: 'muslo-alto', ancho: 1.05, listas: 7, punta: 0.06, patron: pat('rombos', 0.9) }),
  P('ruana', 'Ruana', 'capa', 'poncho', { largo: 'muslo', ancho: 1.1, listas: 0, punta: 0.04 }),
  P('estola', 'Estola', 'capa', 'chal', { largo: 'pecho', flecos: false }),
  P('chal-seda', 'Chal de seda con flecos', 'capa', 'chal', { largo: 'cadera', flecos: true }),
  P('capa-opera', 'Capa de gala', 'capa', 'capa', { largo: 'suelo', ancho: 1.1, cierre: 'broche', ribete: true, ribeteBajo: true }),
  // ===== Calzado y cuello =====
  P('zapatos-vestir', 'Zapatos de vestir', 'calzado', 'calzado', { tipo: 'zapatos' }),
  P('botas-trabajo', 'Botas de trabajo', 'calzado', 'calzado', { tipo: 'botas' }),
  P('botas-goma', 'Botas de goma', 'calzado', 'calzado', { tipo: 'botas' }),
  P('botas-vaqueras', 'Botas vaqueras', 'calzado', 'calzado', { tipo: 'botas-altas' }),
  P('botas-militares', 'Botas de uniforme', 'calzado', 'calzado', { tipo: 'botas-altas' }),
  P('zapatillas-lona', 'Zapatillas de lona', 'calzado', 'calzado', { tipo: 'zapatillas' }),
  P('corbata', 'Corbata', 'cuello', 'cuello', { tipo: 'corbata' }),
  P('perlas', 'Collar de perlas', 'cuello', 'cuello', { tipo: 'collar', colgante: false }),
  P('panuelo-cuello', 'Pañuelo al cuello', 'cuello', 'cuello', { tipo: 'lazo' }),
];

Object.assign(__e, { PRENDAS_SIGLO20 });
return __e;
})();

__m['datos/prendas-regionales-2'] = (() => {
const __e = {};
// Más prendas regionales: Asia, África, Oceanía y Medio Oriente. Misma forma que prendas.js.

const { P } = __m['datos/ayuda'];

const pat = (tipo, escala = 1, rol = 'secundario') => ({ tipo, escala, rol });

const PRENDAS_REGIONALES_2 = [
  P('lehenga', 'Lehenga (falda bordada de gala)', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.18, pliegues: 9, volantes: 3, bandaPatron: 'flores', ribeteBajo: 0.05 }),
  P('chaqueta-nehru', 'Chaqueta de cuello alto (nehru)', 'sobre', 'chaqueta', { largo: 'muslo', manga: 'larga', botones: 7, cintura: 0.6, abertura: 0.008, aberturaBajo: 0.012, puños: false }),
  P('durumagi', 'Durumagi (abrigo coreano)', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'ancha', botones: 0, cintura: 0.8, abertura: 0.01, aberturaBajo: 0.03, holgura: 0.03, ribete: true }),
  P('yukata', 'Yukata', 'torso', 'tunica', { largo: 'tobillo', manga: 'kimono', escote: 'v', prof: 0.8, cruzada: 'izq', cintura: 0.8, holgura: 0.018, ribete: false, patron: pat('ondas', 1) }),
  P('happi', 'Happi (chaqueta corta)', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'ancha', botones: 0, cintura: 1, abertura: 0.01, aberturaBajo: 0.03, ribete: true }),
  P('changshan', 'Changshan (túnica larga de hombre)', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'alto', botones: 5, cintura: 0.9, holgura: 0.016, ribete: false, puños: false }),
  P('chaqueta-zhongshan', 'Chaqueta zhongshan', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'alto', botones: 5, cintura: 0.9, holgura: 0.018, ribete: false }),
  P('cheongsam-invierno', 'Qipao acolchado', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'alto', cintura: 0.1, holgura: 0.008, cruzada: 'der', ribete: true, puños: true }),
  P('chador', 'Chador', 'capa', 'capa', { largo: 'suelo', ancho: 1, cierre: 'abierta', ribete: false }),
  P('kitenge-top', 'Blusa de tela estampada africana', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'v', prof: 0.6, cintura: 0.8, holgura: 0.016, ribete: true, patron: pat('rombos', 1) }),
  P('kitenge-falda', 'Falda de tela estampada africana', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.05, pliegues: 4, patron: pat('flores', 1.1) }),
  P('kikoi', 'Kikoi (paño a rayas)', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.01, pliegues: 2, patron: pat('rayas', 1) }),
  P('kaftan-corto', 'Kaftán corto', 'torso', 'tunica', { largo: 'rodilla', manga: 'ancha', escote: 'v', prof: 0.6, cintura: 1, holgura: 0.03, bordadoPecho: 'zigzag', ribete: true }),
  P('muumuu', 'Vestido amplio estampado', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'redondo', cintura: 1, holgura: 0.03, vuelo: 0.1, patron: pat('flores', 1.2), ribete: true }),
  P('pareo-estampado', 'Pareo estampado', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.01, pliegues: 2, patron: pat('flores', 1.2) }),
  P('kurta-corta', 'Kurta corta', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'ojal', botones: 4, cintura: 1, holgura: 0.016, puños: false, ribete: false }),
  P('sherwani-corta', 'Chaqueta sherwani corta', 'sobre', 'chaqueta', { largo: 'muslo', manga: 'larga', botones: 8, cintura: 0.4, abertura: 0.006, aberturaBajo: 0.01, puños: true, bordadoBorde: true }),
  P('dhoti-sin-camisa', 'Paño envuelto de algodón', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.01, pliegues: 3, ribeteBajo: 0.03 }),
  P('capa-lluvia-paja', 'Capa de paja para la lluvia', 'capa', 'poncho', { largo: 'muslo-alto', ancho: 0.95, listas: 0, punta: 0.03 }),
  P('sombrero-paja-capa', 'Capa corta de fibra', 'capa', 'capa', { largo: 'cintura', ancho: 0.6, cierre: 'abierta', ribete: false, patron: pat('rayas-finas', 1) }),
  P('manto-lana-ancho', 'Manto ancho de lana', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'der', pliegues: 4, orla: true }),
  P('chaleco-pieles', 'Chaleco de piel', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 0, cintura: 0.8, abertura: 0.012, aberturaBajo: 0.016, patron: pat('piel', 1.1) }),
  P('falda-lana-gruesa', 'Falda gruesa de lana', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.1, pliegues: 6, ribeteBajo: 0.03 }),
  P('camisa-lino-larga', 'Camisa larga de lino', 'torso', 'tunica', { largo: 'rodilla', manga: 'larga', escote: 'ojal', cintura: 0.9, holgura: 0.02, puños: true, ribete: false }),
  P('tunica-corta-lana', 'Túnica corta de lana', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'redondo', cintura: 1, holgura: 0.02, ribete: false }),
  P('vestido-lino-largo', 'Vestido largo de lino', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'redondo', cintura: 0.7, holgura: 0.014, vuelo: 0.05, ribete: false, pliegues: 3 }),
];

Object.assign(__e, { PRENDAS_REGIONALES_2 });
return __e;
})();

__m['datos/prendas-moda'] = (() => {
const __e = {};
// Prendas con los tipos de ropa nuevos: escotes de tirantes, halter, asimétrico y corazón; mangas farol, pagoda, cap y
// ajustada; vestidos y faldas de sirena, trapecio y globo; capuchas, bolsillos, cuellos de camisa, ribetes de piel y
// cordones; pantalones pitillo, palazzo, cargo y jodhpur; monos; cuellos, calzado y accesorios nuevos.
// Misma forma que prendas.js; sus colores están en colores-prendas.js.

const { P } = __m['datos/ayuda'];


const PRENDAS_MODA = [
  // ===== Vestidos =====
  P('vestido-tirantes', 'Vestido de tirantes', 'torso', 'tunica', { largo: 'rodilla', manga: 'sin', escote: 'tirantes', cintura: 0.2, holgura: 0.004, vuelo: 0.07, pliegues: 4, ribete: false }),
  P('vestido-tirantes-largo', 'Vestido largo de tirantes', 'torso', 'tunica', { largo: 'suelo', manga: 'sin', escote: 'tirantes', cintura: 0.1, holgura: 0.004, vuelo: 0.12, pliegues: 6, ribete: false }),
  P('vestido-halter', 'Vestido halter', 'torso', 'tunica', { largo: 'rodilla', manga: 'sin', escote: 'halter', cintura: 0.1, holgura: 0.004, vuelo: 0.09, pliegues: 4, ribete: false }),
  P('vestido-asimetrico', 'Vestido de un hombro', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'sin', escote: 'asimetrico', lado: 'der', cintura: 0.1, holgura: 0.004, vuelo: 0.06, pliegues: 4, ribete: false }),
  P('vestido-corazon', 'Vestido de escote corazón', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'sin', escote: 'corazon', cintura: 0, holgura: 0.003, vuelo: 0.11, pliegues: 5, ribete: true }),
  P('vestido-corazon-gala', 'Vestido de gala de escote corazón', 'torso', 'tunica', { largo: 'suelo', manga: 'sin', escote: 'corazon', cintura: 0, holgura: 0.003, vuelo: 0.16, pliegues: 7, ribeteBajo: 0.03, ribete: true }),
  P('vestido-sirena', 'Vestido de sirena', 'torso', 'tunica', { largo: 'suelo', manga: 'sin', escote: 'redondo', prof: 0.6, cintura: 0, holgura: 0.002, vuelo: 0.13, forma: 'sirena', pliegues: 4, ribete: false }),
  P('vestido-sirena-tirantes', 'Vestido de sirena con tirantes', 'torso', 'tunica', { largo: 'suelo', manga: 'sin', escote: 'tirantes', cintura: 0, holgura: 0.002, vuelo: 0.15, forma: 'sirena', pliegues: 4, ribete: false }),
  P('vestido-trapecio', 'Vestido trapecio', 'torso', 'tunica', { largo: 'rodilla', manga: 'corta', escote: 'redondo', prof: 0.4, cintura: 1, holgura: 0.01, vuelo: 0.14, forma: 'trapecio', ribete: true, pliegues: 0 }),
  P('vestido-globo', 'Vestido globo', 'torso', 'tunica', { largo: 'rodilla', manga: 'sin', escote: 'v', prof: 0.7, cintura: 0.5, holgura: 0.004, vuelo: 0.075, forma: 'globo', ribete: false }),
  P('vestido-tubo', 'Vestido recto', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'tres-cuartos', escote: 'barco', cintura: 0, holgura: 0.002, vuelo: 0, forma: 'tubo', ribete: false }),
  P('vestido-camisero', 'Vestido camisero', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'alto', cuelloCamisa: true, botones: 8, cinturon: true, cintura: 0.5, holgura: 0.01, vuelo: 0.06, ribete: false }),
  P('vestido-volantes', 'Vestido de volantes', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'cuadrado', cintura: 0.3, holgura: 0.004, vuelo: 0.1, volantes: 3, ribete: false }),
  P('vestido-pico', 'Vestido de bajo en pico', 'torso', 'tunica', { largo: 'rodilla', manga: 'corta', escote: 'v', cintura: 0.5, holgura: 0.006, vuelo: 0.08, bajo: 'pico', ribete: false }),
  // ===== Blusas, camisas y poleras =====
  P('blusa-farol', 'Blusa de manga farol', 'torso', 'tunica', { largo: 'cadera', manga: 'farol', escote: 'cuadrado', cintura: 0.8, holgura: 0.012, puños: true, ribete: true }),
  P('blusa-pagoda', 'Blusa de manga pagoda', 'torso', 'tunica', { largo: 'cadera', manga: 'pagoda', escote: 'v', prof: 0.7, cintura: 0.7, holgura: 0.012, ribete: false }),
  P('camisa-clasica', 'Camisa clásica de cuello', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'alto', cuelloCamisa: true, botones: 7, bolsillos: 'pecho', puños: true, cintura: 0.9, holgura: 0.014, ribete: false }),
  P('camisa-ajustada', 'Camisa ajustada', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'ajustada', escote: 'alto', cuelloCamisa: true, botones: 7, puños: true, cintura: 0.3, holgura: 0.004, ribete: false }),
  P('camisa-safari', 'Camisa safari', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'corta', escote: 'alto', cuelloCamisa: true, botones: 5, bolsillos: 'pecho', cinturon: true, cintura: 0.7, holgura: 0.016, ribete: false }),
  P('polo', 'Polo', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'ojal', cuelloCamisa: true, botones: 3, cintura: 1, holgura: 0.012, ribete: false }),
  P('camiseta-cap', 'Camiseta de manga corta ajustada', 'torso', 'tunica', { largo: 'cadera', manga: 'cap', escote: 'redondo', prof: 0.4, cintura: 0.8, holgura: 0.008, ribete: false }),
  P('camiseta-sisa', 'Camiseta sin mangas', 'torso', 'tunica', { largo: 'cadera', manga: 'sin', escote: 'redondo', prof: 0.8, cintura: 0.8, holgura: 0.01, ribete: false }),
  P('top-tirantes', 'Top de tirantes', 'torso', 'tunica', { largo: 'cintura', manga: 'sin', escote: 'tirantes', cintura: 0, holgura: 0.003, ribete: false }),
  P('top-halter', 'Top halter', 'torso', 'tunica', { largo: 'cintura', manga: 'sin', escote: 'halter', cintura: 0, holgura: 0.003, ribete: false }),
  P('sudadera-capucha', 'Sudadera con capucha', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'redondo', prof: 0.4, capucha: true, bolsillos: 'canguro', puños: true, cintura: 1, holgura: 0.03, ribete: false }),
  P('sueter-oversize', 'Suéter holgado', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'redondo', prof: 0.4, cintura: 1, holgura: 0.042, puños: true, ribete: true }),
  // ===== Sacos, chaquetas y abrigos =====
  P('bomber', 'Chaqueta bomber', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'larga', botones: 0, cintura: 0.7, abertura: 0.008, aberturaBajo: 0.01, puños: true, ribete: true, bolsillos: 'pecho' }),
  P('parka-capucha', 'Parka con capucha', 'sobre', 'chaqueta', { largo: 'muslo', manga: 'larga', botones: 5, capucha: true, pieles: 'cuello', bolsillos: 'cadera', cintura: 0.9, holgura: 0.032, abertura: 0.01, aberturaBajo: 0.014, ribete: true }),
  P('anorak', 'Anorak', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 3, capucha: true, bolsillos: 'pecho', cinturon: true, cintura: 0.9, holgura: 0.028, abertura: 0.01, aberturaBajo: 0.012, puños: true }),
  P('abrigo-piel-total', 'Abrigo con ribetes de piel', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'larga', botones: 6, solapa: true, pieles: 'todo', cintura: 0.7, vuelo: 0.06, abertura: 0.012, aberturaBajo: 0.02, holgura: 0.022, ribete: true }),
  P('abrigo-punos-piel', 'Abrigo con puños de piel', 'sobre', 'chaqueta', { largo: 'rodilla', manga: 'larga', botones: 6, solapa: true, pieles: 'puños', cintura: 0.6, vuelo: 0.05, abertura: 0.012, aberturaBajo: 0.02, holgura: 0.02, ribete: false }),
  P('blazer-ajustado', 'Blazer ajustado', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 2, solapa: true, cintura: 0.1, abertura: 0.012, aberturaBajo: 0.03, puños: false, bolsillos: 'cadera', ribete: false }),
  P('chaqueta-safari', 'Chaqueta safari', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 5, bolsillos: 'ambos', cinturon: true, cintura: 0.7, abertura: 0.01, aberturaBajo: 0.012, puños: true }),
  P('chaqueta-motera', 'Chaqueta de motociclista', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'larga', botones: 0, solapa: true, cinturon: true, bolsillos: 'pecho', cintura: 0.5, abertura: 0.008, aberturaBajo: 0.01, puños: true, ribete: false }),
  P('bata-casa', 'Bata de casa', 'sobre', 'chaqueta', { largo: 'tobillo', manga: 'larga', botones: 0, solapa: true, cinturon: true, cintura: 0.9, holgura: 0.03, abertura: 0.012, aberturaBajo: 0.05, ribete: true }),
  P('batin-seda', 'Batín de seda', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'ancha', botones: 0, solapa: true, cinturon: true, cintura: 0.8, holgura: 0.03, abertura: 0.012, aberturaBajo: 0.05, ribete: true }),
  P('corse', 'Corsé con cordones', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 0, cordones: true, cintura: 0, abertura: 0.006, aberturaBajo: 0.008, ribete: true }),
  P('chaleco-acolchado', 'Chaleco acolchado', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 5, bolsillos: 'cadera', cintura: 0.9, holgura: 0.03, abertura: 0.01, aberturaBajo: 0.012, ribete: false }),
  // ===== Pantalones, faldas y monos =====
  P('pantalon-pitillo', 'Pantalón pitillo', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'pitillo', cintura: 'natural', bolsillos: 'jean' }),
  P('pantalon-palazzo', 'Pantalón palazzo', 'inferior', 'pantalon', { largo: 'suelo', ancho: 'palazzo', cintura: 'alta', holgura: 0.006 }),
  P('pantalon-jodhpur', 'Pantalón de montar (jodhpur)', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'jodhpur', cintura: 'natural' }),
  P('pantalon-cargo', 'Pantalón cargo', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'natural', bolsillos: 'cargo', puños: false }),
  P('jeans-clasicos', 'Jeans de cinco bolsillos', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'natural', bolsillos: 'jean', puños: true }),
  P('pantalon-pliegues', 'Pantalón de pinzas', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'alta', bolsillos: 'pliegues' }),
  P('pantalon-tubo', 'Pantalón recto estrecho', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'tubo', cintura: 'natural' }),
  P('bermuda', 'Bermudas', 'inferior', 'pantalon', { largo: 'rodilla', ancho: 'recto', cintura: 'natural', bolsillos: 'cargo' }),
  P('pantalon-capri', 'Pantalón capri', 'inferior', 'pantalon', { largo: 'pantorrilla', ancho: 'pitillo', cintura: 'natural' }),
  P('pantalon-pescador', 'Pantalón de pescador', 'inferior', 'pantalon', { largo: 'pantorrilla', ancho: 'amplio', cintura: 'baja', cordon: true }),
  P('leggings', 'Mallas', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'ajustado', cintura: 'alta', holgura: 0.001 }),
  P('sarouel', 'Pantalón sarouel', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'bombacho', cintura: 'baja' }),
  P('culotte', 'Pantalón culotte', 'inferior', 'pantalon', { largo: 'pantorrilla', ancho: 'amplio', cintura: 'alta' }),
  P('falda-sirena', 'Falda de sirena', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.12, forma: 'sirena', cintura: 'natural', pliegues: 3 }),
  P('falda-globo', 'Falda globo', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.07, forma: 'globo', cintura: 'natural', pliegues: 4 }),
  P('falda-trapecio', 'Falda trapecio', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.12, forma: 'trapecio', cintura: 'natural', pliegues: 0 }),
  P('falda-abertura', 'Falda recta con abertura', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.01, forma: 'tubo', cintura: 'natural', abertura: 'frente', pliegues: 0 }),
  P('falda-abertura-lado', 'Falda larga con abertura lateral', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.05, cintura: 'natural', abertura: 'lado', pliegues: 3 }),
  P('falda-mini', 'Minifalda', 'inferior', 'falda', { largo: 'muslo-alto', vuelo: 0.04, forma: 'trapecio', cintura: 'baja', pliegues: 0 }),
  P('falda-midi', 'Falda midi', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.1, cintura: 'natural', pliegues: 6 }),
  P('falda-capas', 'Falda de capas', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.14, volantes: 4, cintura: 'natural', pliegues: 2 }),
  P('mono-trabajo', 'Mono de trabajo', 'torso', 'mono', { torso: { manga: 'larga', escote: 'alto', cuelloCamisa: true, botones: 6, bolsillos: 'pecho', cinturon: true, largo: 'cadera', puños: true }, pantalon: { ancho: 'recto', largo: 'tobillo', cintura: 'alta' } }),
  P('mono-elegante', 'Mono de noche', 'torso', 'mono', { torso: { manga: 'sin', escote: 'halter', largo: 'cintura', cintura: 0 }, pantalon: { ancho: 'palazzo', largo: 'suelo', cintura: 'alta', holgura: 0.006 } }),
  // ===== Cuello y accesorios =====
  P('cuello-piel', 'Cuello de piel', 'cuello', 'cuello', { tipo: 'piel' }),
  P('esclavina', 'Esclavina', 'cuello', 'cuello', { tipo: 'esclavina' }),
  P('chalina', 'Chalina', 'cuello', 'cuello', { tipo: 'chalina' }),
  P('panuelo-triangulo', 'Pañuelo anudado al cuello', 'cuello', 'cuello', { tipo: 'triangulo' }),
  P('babero-cuello', 'Cuello ancho redondeado', 'cuello', 'cuello', { tipo: 'babero' }),
  P('tirantes-pantalon', 'Tirantes', 'accesorio', 'accesorio', { tipo: 'tirantes' }),
  // ===== Calzado =====
  P('botines', 'Botines', 'calzado', 'calzado', { tipo: 'botines' }),
  P('tacones', 'Zapatos de taco', 'calzado', 'calzado', { tipo: 'tacones' }),
  P('chancletas', 'Chancletas', 'calzado', 'calzado', { tipo: 'chancletas' }),
  P('zapatos-oxford', 'Zapatos oxford', 'calzado', 'calzado', { tipo: 'oxford' }),
];

Object.assign(__e, { PRENDAS_MODA });
return __e;
})();

__m['datos/prendas'] = (() => {
const __e = {};
// Catálogo de prendas. Cada una es una plantilla (src/prendas.js) con sus parámetros, la ranura donde se
// pone y qué color de la paleta del atuendo usa en cada papel: A = principal, B = secundario, C = ribete,
// D = detalle. Las fechas y regiones viven en los atuendos (atuendos.js), no aquí.

const { P } = __m['datos/ayuda'];
const { PRENDAS_AMPLIADAS } = __m['datos/prendas-ampliadas'];
const { PRENDAS_SIGLO20 } = __m['datos/prendas-siglo20'];
const { PRENDAS_REGIONALES_2 } = __m['datos/prendas-regionales-2'];
const { PRENDAS_MODA } = __m['datos/prendas-moda'];

const PRENDAS = [
  // ===== Egipto y Mesopotamia =====
  P('kalasiris', 'Kalasiris: vestido de lino ceñido con tirantes', 'torso', 'tunica', { largo: 'tobillo', manga: 'sin', escote: 'v', prof: 1.4, cintura: 0, holgura: 0.002, vuelo: 0, ribete: false }),
  P('kalasiris-plisada', 'Túnica de lino plisado de mangas amplias', 'torso', 'tunica', { largo: 'tobillo', manga: 'campana', escote: 'v', prof: 1.0, cintura: 0.4, holgura: 0.012, pliegues: 7, vuelo: 0.05 }),
  P('shenti', 'Shenti: falda corta de lino', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.025, cintura: 'baja', pliegues: 4, bajo: 'curvo' }),
  P('shenti-plisado', 'Shenti plisado de gala', 'inferior', 'falda', { largo: 'bajo-rodilla', vuelo: 0.07, cintura: 'baja', pliegues: 8, bajo: 'curvo', ribeteBajo: 0.02 }),
  P('collar-usej', 'Collar ancho usej', 'cuello', 'cuello', { tipo: 'pectoral' }),
  P('kaunakes', 'Falda de lana con flecos (kaunakes)', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.06, cintura: 'baja', pliegues: 3, flecos: true, bajo: 'ondulado', patron: { tipo: 'piel', escala: 0.9, rol: 'secundario' } }),
  P('manto-asirio', 'Manto de borlas asirio', 'capa', 'drapeado', { largo: 'tobillo', lado: 'izq', pliegues: 4 }),
  P('tunica-sumeria', 'Túnica de lana de un hombro', 'torso', 'tunica', { largo: 'rodilla', manga: 'corta', escote: 'barco', holgura: 0.014, ribeteBajo: 0.025, bajo: 'ondulado' }),
  // ===== Grecia, Roma, Bizancio =====
  P('peplos', 'Peplo dórico', 'torso', 'tunica', { largo: 'tobillo', manga: 'sin', escote: 'ojal', cintura: 0.7, holgura: 0.018, vuelo: 0.05, pliegues: 6, ribeteBajo: 0.02, ribete: true }),
  P('quiton-jonico', 'Quitón jónico plisado', 'torso', 'tunica', { largo: 'tobillo', manga: 'ancha', escote: 'barco', cintura: 0.4, holgura: 0.012, pliegues: 8, vuelo: 0.05, ribete: false }),
  P('quiton-corto', 'Quitón corto de hombre', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'sin', escote: 'redondo', holgura: 0.014, cintura: 0.8, pliegues: 3, ribeteBajo: 0.015 }),
  P('himation', 'Himatión', 'capa', 'drapeado', { largo: 'tobillo', lado: 'izq', pliegues: 6, orla: true }),
  P('himation-corto', 'Himatión corto', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'der', pliegues: 4, orla: false }),
  P('clamide', 'Clámide: capa corta de jinete', 'capa', 'capa', { largo: 'muslo', ancho: 0.8, cierre: 'broche', ribete: false }),
  P('tunica-romana', 'Túnica romana con clavus', 'torso', 'tunica', { largo: 'rodilla', manga: 'corta', escote: 'redondo', cintura: 0.9, holgura: 0.015, bandas: 'lateral', bandasAncho: 0.012, ribete: false }),
  P('tunica-plebeya', 'Túnica corta de trabajo', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'corta', escote: 'ojal', cintura: 1, holgura: 0.02, ribete: false, bajo: 'dentado' }),
  P('toga', 'Toga', 'capa', 'drapeado', { largo: 'suelo', lado: 'izq', pliegues: 7, orla: false }),
  P('toga-praetexta', 'Toga con orla púrpura', 'capa', 'drapeado', { largo: 'suelo', lado: 'izq', pliegues: 7, orla: true }),
  P('stola', 'Estola matronal', 'torso', 'tunica', { largo: 'suelo', manga: 'corta', escote: 'redondo', cintura: 0.5, vuelo: 0.06, holgura: 0.014, pliegues: 5, ribeteBajo: 0.025 }),
  P('palla', 'Palla: manto de mujer', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'der', pliegues: 5, orla: true }),
  P('cinturon-cuerda', 'Cuerda al talle', 'faja', 'faja', { y: 'cintura', ancho: 0.008 }),
  P('cinta-pecho', 'Cinta bajo el pecho', 'faja', 'faja', { y: 'pecho', ancho: 0.012 }),
  P('bragae', 'Bragas (pantalón de lana)', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cordon: true }),
  P('sandalia-romana', 'Sandalia romana atada', 'calzado', 'calzado', { tipo: 'sandalia-romana' }),
  P('caliga', 'Caliga militar', 'calzado', 'calzado', { tipo: 'sandalia-romana' }),
  P('lorica-segmentata', 'Coraza segmentada', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'corta', botones: 0, cintura: 0.6, abertura: 0.008, aberturaBajo: 0.012, ribete: true, patron: { tipo: 'bandas', escala: 0.45, rol: 'secundario' } }),
  P('lorica-escamas', 'Coraza de escamas', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'corta', cintura: 0.6, abertura: 0.006, aberturaBajo: 0.01, patron: { tipo: 'escamas', escala: 0.6, rol: 'secundario' } }),
  P('pteruges', 'Faldellín de tiras de cuero', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.03, cintura: 'baja', bajo: 'dentado', pliegues: 9 }),
  P('tunica-bizantina', 'Túnica bizantina con tablion', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'redondo', prof: 0.5, cintura: 0.7, holgura: 0.016, bandas: 'lateral', ribeteBajo: 0.03, bandaPatron: 'rombos', puños: true, ribete: true }),
  P('dalmatica', 'Dalmática', 'sobre', 'tunica', { largo: 'pantorrilla', manga: 'ancha', escote: 'redondo', holgura: 0.03, bandas: 'lateral', bandasAncho: 0.016, ribeteBajo: 0.02, puños: true }),
  P('clamide-bizantina', 'Clámide imperial con tablion', 'capa', 'capa', { largo: 'pantorrilla', ancho: 1, cierre: 'broche', ribete: true, ribeteBajo: true }),
  P('collar-imperial', 'Collar de gemas', 'cuello', 'cuello', { tipo: 'pectoral' }),
  // ===== Europa medieval =====
  P('camisa-medieval', 'Camisa de lino con escote atado', 'torso', 'tunica', { largo: 'muslo', manga: 'larga', escote: 'ojal', cintura: 1, holgura: 0.016, puños: false, ribete: false }),
  P('cota-campesina', 'Cota de lana de campesino', 'torso', 'tunica', { largo: 'rodilla', manga: 'larga', escote: 'redondo', cintura: 1, holgura: 0.02, ribete: false, bajo: 'curvo' }),
  P('bliaut', 'Bliaut de manga de campana', 'torso', 'tunica', { largo: 'suelo', manga: 'campana', escote: 'redondo', prof: 1.2, cintura: 0.1, holgura: 0.004, vuelo: 0.09, pliegues: 4, ribeteBajo: 0.03 }),
  P('sobreveste', 'Sobrevesta sin mangas', 'sobre', 'tunica', { largo: 'pantorrilla', manga: 'sin', escote: 'redondo', cintura: 0.8, holgura: 0.03, vuelo: 0.05, ribeteBajo: 0.015 }),
  P('surcote', 'Sobrecota de damas con aberturas', 'sobre', 'tunica', { largo: 'suelo', manga: 'sin', escote: 'redondo', prof: 1.4, cintura: 0.2, holgura: 0.03, vuelo: 0.08, ribete: true }),
  P('jubon-medieval', 'Jubón acolchado', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 6, cintura: 0.5, solapa: false, abertura: 0.004, aberturaBajo: 0.006 }),
  P('calzas', 'Calzas ajustadas', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'ajustado', holgura: 0.001 }),
  P('calzones-medievales', 'Calzones de lino hasta la rodilla', 'inferior', 'pantalon', { largo: 'rodilla', ancho: 'recto' }),
  P('bragas-holgadas', 'Pantalón holgado de campesino', 'inferior', 'pantalon', { largo: 'pantorrilla', ancho: 'amplio', cordon: true }),
  P('capa-medieval', 'Capa con broche', 'capa', 'capa', { largo: 'pantorrilla', ancho: 1, cierre: 'broche', ribete: true }),
  P('capa-corta-ribete', 'Capa corta con ribete', 'capa', 'capa', { largo: 'cadera', ancho: 0.7, cierre: 'broche', ribete: true }),
  P('manto-largo', 'Manto largo de nobles', 'capa', 'capa', { largo: 'suelo', ancho: 1.2, cierre: 'broche', ribete: true, ribeteBajo: true }),
  P('habito-monje', 'Hábito de monje', 'torso', 'tunica', { largo: 'suelo', manga: 'ancha', escote: 'redondo', prof: 0.5, cintura: 1, holgura: 0.03, ribete: false, pliegues: 4 }),
  P('escapulario', 'Escapulario', 'sobre', 'delantal', { largo: 'tobillo', peto: true, vuelo: 0.01 }),
  P('cuerda-franciscana', 'Cordón franciscano', 'faja', 'faja', { y: 'cintura', ancho: 0.01, cuelga: 'der' }),
  P('sotana', 'Sotana', 'torso', 'tunica', { largo: 'suelo', manga: 'larga', escote: 'alto', cintura: 1, holgura: 0.014, botones: 12, puños: false, ribete: false }),
  P('sobrepelliz', 'Sobrepelliz', 'sobre', 'tunica', { largo: 'pantorrilla', manga: 'ancha', escote: 'redondo', holgura: 0.03, bajo: 'curvo', ribete: false }),
  P('delantal-campesino', 'Delantal de trabajo', 'sobre', 'delantal', { largo: 'pantorrilla', peto: false, vuelo: 0.02 }),
  P('delantal-peto', 'Delantal con peto', 'sobre', 'delantal', { largo: 'rodilla', peto: true }),
  P('faldon-campesina', 'Falda de lana con orla', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.08, pliegues: 6, ribeteBajo: 0.02 }),
  P('cinturon-cuero', 'Cinturón de cuero con hebilla', 'faja', 'faja', { y: 'cintura', ancho: 0.016, hebilla: true }),
  P('cinturon-bolsa', 'Cinturón con bolsa', 'faja', 'faja', { y: 'cadera', ancho: 0.014, hebilla: true, cuelga: 'izq' }),
  P('bolso-cuero', 'Bolsa de cuero cruzada', 'accesorio', 'accesorio', { tipo: 'bolso', lado: 'izq' }),
  P('botas-cuero', 'Botas de cuero', 'calzado', 'calzado', { tipo: 'botas' }),
  P('botas-altas', 'Botas altas', 'calzado', 'calzado', { tipo: 'botas-altas' }),
  P('zapatos-cuero', 'Zapatos de cuero', 'calzado', 'calzado', { tipo: 'zapatos' }),
  P('zapatos-hebilla', 'Zapatos de hebilla', 'calzado', 'calzado', { tipo: 'zapatos', hebilla: true }),
  P('zuecos', 'Zuecos de madera', 'calzado', 'calzado', { tipo: 'zuecos' }),
  P('polainas-lana', 'Polainas de lana', 'calzado', 'calzado', { tipo: 'polainas' }),
  P('sandalias-cuero', 'Sandalias de cuero', 'calzado', 'calzado', { tipo: 'sandalias' }),
  P('descalzo', 'Descalzo', 'calzado', 'calzado', { tipo: 'descalzo' }),
  P('mocasines', 'Mocasines', 'calzado', 'calzado', { tipo: 'mocasines' }),
  P('gorguera', 'Gorguera de lino', 'cuello', 'cuello', { tipo: 'gorguera' }),
  P('cuello-alto', 'Cuello alto', 'cuello', 'cuello', { tipo: 'alto' }),
  P('bufanda-lana', 'Bufanda de lana', 'cuello', 'cuello', { tipo: 'bufanda' }),
  P('collar-cuentas', 'Collar de cuentas', 'cuello', 'cuello', { tipo: 'collar' }),
  P('collar-colgante', 'Collar con colgante', 'cuello', 'cuello', { tipo: 'collar', colgante: true }),
  P('cuentas-largas', 'Cuentas largas', 'cuello', 'cuello', { tipo: 'cuentas-largas' }),
  P('gargantilla', 'Gargantilla', 'cuello', 'cuello', { tipo: 'gargantilla' }),
  P('lazo-cuello', 'Lazo al cuello', 'cuello', 'cuello', { tipo: 'lazo' }),
  P('brazaletes', 'Brazaletes', 'accesorio', 'accesorio', { tipo: 'brazaletes' }),
  P('brazaletes-altos', 'Brazaletes sobre el codo', 'accesorio', 'accesorio', { tipo: 'brazaletes', alto: true }),
  // ===== Europa: Renacimiento a siglo XIX =====
  P('camisa-globo', 'Camisa de mangas abullonadas', 'torso', 'tunica', { largo: 'muslo', manga: 'globo', escote: 'cuadrado', cintura: 0.8, holgura: 0.014, puños: true, ribete: true }),
  P('jubon-renacentista', 'Jubón con botonadura', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 8, cintura: 0.3, solapa: false, abertura: 0.004, aberturaBajo: 0.006, puños: true, bajo: 'dentado' }),
  P('gregueescos', 'Gregüescos abullonados', 'inferior', 'pantalon', { largo: 'muslo', ancho: 'bombacho' }),
  P('calzas-largas', 'Medias largas', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'ajustado' }),
  P('vestido-renacentista', 'Vestido de corpiño y falda amplia', 'torso', 'tunica', { largo: 'suelo', manga: 'abullonada', escote: 'cuadrado', prof: 1.2, cintura: 0, holgura: 0.002, vuelo: 0.14, pliegues: 5, ribeteBajo: 0.03, puños: true }),
  P('vestido-barroco', 'Vestido de corte barroco', 'torso', 'tunica', { largo: 'suelo', manga: 'codo', escote: 'barco', cintura: 0, holgura: 0.002, vuelo: 0.17, pliegues: 6, ribeteBajo: 0.025, puños: true, volantes: 1 }),
  P('vestido-imperio', 'Vestido de talle imperio', 'torso', 'tunica', { largo: 'suelo', manga: 'corta', escote: 'cuadrado', prof: 1.1, cintura: 1, holgura: 0.008, vuelo: 0.05, pliegues: 6, ribeteBajo: 0.02 }),
  P('vestido-victoriano', 'Vestido victoriano ceñido', 'torso', 'tunica', { largo: 'suelo', manga: 'larga', escote: 'alto', cintura: 0, holgura: 0.002, vuelo: 0.19, pliegues: 7, volantes: 2, puños: true, botones: 9 }),
  P('vestido-sencillo', 'Vestido sencillo de lana', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'redondo', cintura: 0.5, holgura: 0.012, vuelo: 0.07, ribete: false, pliegues: 3 }),
  P('vestido-sencillo-corto', 'Vestido sencillo hasta la pantorrilla', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'tres-cuartos', escote: 'v', cintura: 0.5, holgura: 0.012, vuelo: 0.06, ribete: true }),
  P('blusa-campesina', 'Blusa de campesina', 'torso', 'tunica', { largo: 'cadera', manga: 'tres-cuartos', escote: 'barco', cintura: 0.9, holgura: 0.016, puños: true, ribete: true }),
  P('corpino', 'Corpiño', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'sin', botones: 6, cintura: 0, abertura: 0.006, aberturaBajo: 0.008, ribete: true }),
  P('chaleco-corto', 'Chaleco corto', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 6, cintura: 0.4, solapa: true, abertura: 0.01, aberturaBajo: 0.014 }),
  P('chaleco-largo', 'Chaleco largo bordado', 'sobre', 'chaqueta', { largo: 'muslo-alto', manga: 'sin', botones: 10, cintura: 0.6, abertura: 0.008, aberturaBajo: 0.016, bordadoBorde: true }),
  P('levita', 'Levita', 'sobre', 'chaqueta', { largo: 'rodilla', manga: 'larga', botones: 6, solapa: true, cintura: 0.3, vuelo: 0.05, abertura: 0.012, aberturaBajo: 0.04, puños: true, ribete: true }),
  P('casaca', 'Casaca del siglo XVIII', 'sobre', 'chaqueta', { largo: 'muslo', manga: 'codo', botones: 10, solapa: false, cintura: 0.5, vuelo: 0.07, abertura: 0.012, aberturaBajo: 0.05, puños: true, bordadoBorde: true }),
  P('frac', 'Frac', 'sobre', 'chaqueta', { largo: 'muslo-alto', manga: 'larga', botones: 4, solapa: true, solapaColor: 'ribete', cintura: 0.2, abertura: 0.02, aberturaBajo: 0.06, vuelo: 0.03, puños: true }),
  P('abrigo-largo', 'Abrigo largo', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'larga', botones: 6, solapa: true, cintura: 0.7, vuelo: 0.07, abertura: 0.012, aberturaBajo: 0.02, holgura: 0.02 }),
  P('chaqueta-corta', 'Chaqueta corta', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 5, solapa: true, cintura: 0.6, abertura: 0.01, aberturaBajo: 0.014, puños: false }),
  P('camisa-blanca', 'Camisa blanca de cuello', 'torso', 'tunica', { largo: 'muslo', manga: 'larga', escote: 'alto', botones: 6, cintura: 0.9, holgura: 0.012, puños: true, ribete: false }),
  P('pantalon-recto', 'Pantalón recto', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'natural' }),
  P('pantalon-ajustado', 'Pantalón ajustado', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'ajustado' }),
  P('pantalon-corte-alto', 'Pantalón de cintura alta', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', puños: true }),
  P('calzones-rodilla', 'Calzones a la rodilla', 'inferior', 'pantalon', { largo: 'bajo-rodilla', ancho: 'ajustado' }),
  P('falda-larga', 'Falda larga', 'inferior', 'falda', { largo: 'suelo', vuelo: 0.12, pliegues: 6, ribeteBajo: 0.015 }),
  P('falda-volantes', 'Falda de volantes', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.14, pliegues: 4, volantes: 3 }),
  P('enaguas', 'Enaguas', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.16, pliegues: 3, volantes: 1 }),
  P('delantal-blanco', 'Delantal blanco', 'sobre', 'delantal', { largo: 'tobillo', peto: false, vuelo: 0.03 }),
  P('chal-lana', 'Chal de lana', 'capa', 'chal', { largo: 'cadera', flecos: true }),
  P('chal-corto', 'Chal corto sobre los hombros', 'capa', 'chal', { largo: 'pecho', flecos: false }),
  P('capa-viaje', 'Capa de viaje', 'capa', 'capa', { largo: 'pantorrilla', ancho: 0.9, cierre: 'broche', ribete: false }),
  P('capa-ceremonial', 'Capa ceremonial', 'capa', 'capa', { largo: 'suelo', ancho: 1.3, cierre: 'broche', ribete: true, ribeteBajo: true }),
  P('zapatillas', 'Zapatillas de tela', 'calzado', 'calzado', { tipo: 'zapatillas' }),
  // ===== Eslavo, celta, vikingo =====
  P('rubakha', 'Rubajá bordada', 'torso', 'tunica', { largo: 'rodilla', manga: 'larga', escote: 'ojal', cintura: 1, holgura: 0.016, puños: true, bordadoPecho: 'cruces', ribete: true, bajo: 'recto' }),
  P('sarafan', 'Sarafán', 'sobre', 'tunica', { largo: 'tobillo', manga: 'sin', escote: 'cuadrado', cintura: 0.7, vuelo: 0.1, holgura: 0.012, ribeteBajo: 0.02, botones: 7 }),
  P('rubakha-larga', 'Camisa larga de mujer eslava', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'ojal', cintura: 1, holgura: 0.014, puños: true, bordadoPecho: 'cruces', ribete: true }),
  P('kaftan-eslavo', 'Cafetán', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'larga', botones: 9, cintura: 0.6, abertura: 0.006, aberturaBajo: 0.012, puños: true, ribete: true }),
  P('tunica-vikinga', 'Túnica vikinga con cenefa', 'torso', 'tunica', { largo: 'rodilla', manga: 'larga', escote: 'ojal', cintura: 0.9, holgura: 0.016, ribeteBajo: 0.02, puños: true, bandaPatron: 'grecas' }),
  P('vestido-delantal-vikingo', 'Vestido con tirantes (hangerok)', 'sobre', 'tunica', { largo: 'tobillo', manga: 'sin', escote: 'cuadrado', cintura: 0.8, holgura: 0.012, vuelo: 0.04, ribeteBajo: 0.02 }),
  P('broche-ovalado', 'Fíbulas ovaladas y cuentas', 'cuello', 'cuello', { tipo: 'cuentas-largas' }),
  P('kilt-plaid', 'Falda escocesa de tartán', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.045, cintura: 'natural', pliegues: 9, bajo: 'recto', patron: { tipo: 'tartan', escala: 1.1, rol: 'secundario' } }),
  P('plaid-escoces', 'Plaid sobre el hombro', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'izq', pliegues: 4, orla: false, patron: { tipo: 'tartan', escala: 1.1, rol: 'secundario' } }),
  P('bratt-celta', 'Manto celta con broche', 'capa', 'capa', { largo: 'pantorrilla', ancho: 0.9, cierre: 'broche', ribete: true, patron: { tipo: 'cuadros', escala: 1.1, rol: 'secundario' } }),
  P('pantalones-celtas', 'Pantalones de lana a cuadros', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', patron: { tipo: 'cuadros', escala: 0.9, rol: 'secundario' } }),
  // ===== Judío, mediterráneo =====
  P('caftan-askenazi', 'Cafetán largo', 'sobre', 'chaqueta', { largo: 'tobillo', manga: 'larga', botones: 9, cintura: 0.7, abertura: 0.006, aberturaBajo: 0.012, puños: false, holgura: 0.02, solapa: false }),
  P('tallit', 'Manto de oración (talit)', 'capa', 'drapeado', { largo: 'rodilla', lado: 'izq', pliegues: 3, orla: true }),
  P('tunica-hebrea', 'Túnica larga con bandas', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'redondo', cintura: 0.8, holgura: 0.02, bandas: 'lateral', ribeteBajo: 0.02, puños: true }),
  P('manto-hebreo', 'Manto de lana a rayas', 'capa', 'drapeado', { largo: 'tobillo', lado: 'der', pliegues: 5, orla: false, patron: { tipo: 'rayas', escala: 1.2, rol: 'secundario' } }),
  // ===== Medio Oriente =====
  P('thobe', 'Zobe: túnica larga', 'torso', 'tunica', { largo: 'suelo', manga: 'larga', escote: 'alto', botones: 4, cintura: 1, holgura: 0.02, puños: false, ribete: false }),
  P('dishdasha', 'Dishdasha', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'ojal', cintura: 1, holgura: 0.018, botones: 3, puños: true, ribete: false }),
  P('abaya', 'Abaya', 'torso', 'tunica', { largo: 'suelo', manga: 'ancha', escote: 'redondo', prof: 0.5, cintura: 1, holgura: 0.03, pliegues: 4, ribete: false }),
  P('bisht', 'Bisht (manto con orla)', 'capa', 'capa', { largo: 'tobillo', ancho: 0.9, cierre: 'broche', ribete: true, ribeteBajo: true }),
  P('chaleco-beduino', 'Chaleco bordado', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 5, cintura: 0.5, bordadoBorde: true, abertura: 0.01, aberturaBajo: 0.012 }),
  P('sirwal', 'Sirwal (pantalón bombacho)', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'bombacho' }),
  P('caftan-otomano', 'Caftán otomano', 'sobre', 'chaqueta', { largo: 'tobillo', manga: 'codo', botones: 0, cintura: 0.8, abertura: 0.01, aberturaBajo: 0.05, holgura: 0.025, ribete: true, bordadoBorde: true }),
  P('faja-turbante', 'Faja ancha', 'faja', 'faja', { y: 'cintura', ancho: 0.04, doble: true }),
  P('chilaba', 'Chilaba con capucha', 'torso', 'tunica', { largo: 'tobillo', manga: 'ancha', escote: 'v', prof: 0.7, cintura: 1, holgura: 0.035, bordadoPecho: 'rombos', ribete: true, puños: false, pliegues: 4 }),
  // ===== Asia =====
  P('kurta', 'Kurta', 'torso', 'tunica', { largo: 'rodilla', manga: 'larga', escote: 'ojal', botones: 4, cintura: 1, holgura: 0.016, puños: false, ribete: true }),
  P('sherwani', 'Sherwani', 'sobre', 'chaqueta', { largo: 'rodilla', manga: 'larga', botones: 8, cintura: 0.4, abertura: 0.006, aberturaBajo: 0.01, puños: true, bordadoBorde: true }),
  P('pyjama-indio', 'Pantalón ajustado a la pantorrilla (churidar)', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'ajustado', cordon: true }),
  P('dhoti', 'Dhoti', 'inferior', 'pantalon', { largo: 'pantorrilla', ancho: 'bombacho', puños: true }),
  P('lungi', 'Lungi', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.01, cintura: 'natural', pliegues: 2, patron: { tipo: 'cuadros', escala: 0.9, rol: 'secundario' } }),
  P('sari-falda', 'Falda plisada del sari', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.05, pliegues: 9, ribeteBajo: 0.03, bandaPatron: 'rombos' }),
  P('sari-pallu', 'Pallu del sari', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'izq', pliegues: 4, orla: true }),
  P('choli', 'Choli (blusa corta)', 'torso', 'tunica', { largo: 'pecho', manga: 'corta', escote: 'v', prof: 1.2, cintura: 0, holgura: 0.002, ribete: true }),
  P('dupatta', 'Dupatta', 'capa', 'chal', { largo: 'cintura', flecos: false }),
  P('kimono', 'Kimono', 'torso', 'tunica', { largo: 'tobillo', manga: 'kimono', escote: 'v', prof: 0.9, cruzada: 'izq', cintura: 0.6, holgura: 0.02, ribete: true, vuelo: 0.01 }),
  P('hakama', 'Hakama', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'amplio', cintura: 'baja', puños: false }),
  P('haori', 'Haori', 'sobre', 'chaqueta', { largo: 'muslo', manga: 'ancha', botones: 0, cintura: 0.9, abertura: 0.01, aberturaBajo: 0.012, ribete: true, holgura: 0.025 }),
  P('obi', 'Obi', 'faja', 'faja', { y: 'cintura', ancho: 0.07, doble: true }),
  P('obijime', 'Cordón obijime', 'faja', 'faja', { y: 'cintura', ancho: 0.008, cuelga: 'izq' }),
  P('geta', 'Geta de madera', 'calzado', 'calzado', { tipo: 'zuecos' }),
  P('hanbok-jeogori', 'Jeogori (chaqueta corta)', 'torso', 'tunica', { largo: 'pecho', manga: 'ancha', escote: 'v', prof: 0.8, cruzada: 'izq', cintura: 0.5, holgura: 0.012, puños: true, ribete: true }),
  P('hanbok-chima', 'Chima (falda de talle alto)', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.13, cintura: 'alta', pliegues: 6 }),
  P('hanbok-baji', 'Baji (pantalón holgado)', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'amplio', puños: true }),
  P('hanfu-ruqun', 'Hanfu con falda (ruqun)', 'torso', 'tunica', { largo: 'tobillo', manga: 'campana', escote: 'v', prof: 0.8, cruzada: 'der', cintura: 0.5, holgura: 0.012, vuelo: 0.08, ribeteBajo: 0.03, bandaPatron: 'ondas', ribete: true }),
  P('hanfu-pao', 'Túnica larga (pao) de hombre', 'torso', 'tunica', { largo: 'tobillo', manga: 'ancha', escote: 'v', prof: 0.9, cruzada: 'der', cintura: 0.8, holgura: 0.02, ribete: true, ribeteBajo: 0.02 }),
  P('cheongsam', 'Qipao', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'alto', cintura: 0, holgura: 0.002, vuelo: 0.01, cruzada: 'der', ribete: true, patron: { tipo: 'flores', escala: 0.8, rol: 'secundario' } }),
  P('pantalon-chino', 'Pantalón chino de algodón', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'amplio', cintura: 'natural' }),
  P('chaqueta-mao', 'Chaqueta de cuello mao', 'torso', 'tunica', { largo: 'muslo', manga: 'larga', escote: 'alto', botones: 5, cintura: 0.9, holgura: 0.014, puños: false, ribete: false }),
  P('zapatos-tela', 'Zapatos de tela', 'calzado', 'calzado', { tipo: 'zapatillas' }),
  P('sarong', 'Sarong', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.0, cintura: 'natural', pliegues: 2, patron: { tipo: 'ondas', escala: 0.9, rol: 'secundario' } }),
  P('baju-kurung', 'Baju kurung', 'torso', 'tunica', { largo: 'rodilla', manga: 'larga', escote: 'ojal', cintura: 1, holgura: 0.02, puños: true, ribete: true }),
  P('kebaya', 'Kebaya', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'v', prof: 1.1, cintura: 0.2, holgura: 0.004, puños: true, ribete: true, bandaPatron: 'flores' }),
  // ===== África =====
  P('boubou', 'Boubou', 'torso', 'tunica', { largo: 'tobillo', manga: 'kimono', escote: 'ojal', cintura: 1, holgura: 0.05, bordadoPecho: 'rombos', pliegues: 4, ribete: true }),
  P('agbada', 'Agbada', 'sobre', 'tunica', { largo: 'pantorrilla', manga: 'kimono', escote: 'redondo', prof: 0.8, cintura: 1, holgura: 0.045, bordadoPecho: 'zigzag', ribete: true }),
  P('dashiki', 'Dashiki', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'v', prof: 0.9, cintura: 1, holgura: 0.02, bordadoPecho: 'rombos', ribete: true, patron: { tipo: 'rombos', escala: 1.2, rol: 'secundario' } }),
  P('kente', 'Kente', 'capa', 'drapeado', { largo: 'tobillo', lado: 'izq', pliegues: 4, orla: false, patron: { tipo: 'bandas', escala: 0.9, rol: 'secundario' } }),
  P('kente-falda', 'Falda de kente', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.03, pliegues: 3, patron: { tipo: 'bandas', escala: 0.8, rol: 'secundario' } }),
  P('shuka', 'Shuka maasái', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'der', pliegues: 3, orla: false, patron: { tipo: 'tartan', escala: 1.2, rol: 'secundario' } }),
  P('kanga', 'Kanga estampada', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.01, pliegues: 2, patron: { tipo: 'flores', escala: 1.2, rol: 'secundario' } }),
  P('wrap-top', 'Blusa amarrada', 'torso', 'tunica', { largo: 'pecho', manga: 'corta', escote: 'barco', cintura: 0, holgura: 0.002, ribete: false }),
  P('taparrabo-zulu', 'Faldellín de pieles (umutsha)', 'inferior', 'taparrabo', { largo: 'muslo', ancho: 0.05, punta: 0.02, ribeteBajo: true, patron: { tipo: 'piel', escala: 1, rol: 'secundario' } }),
  P('collares-zulu', 'Collares de cuentas anchos', 'cuello', 'cuello', { tipo: 'pectoral' }),
  P('falda-rafia', 'Falda de rafia', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.05, flecos: true, bajo: 'dentado', pliegues: 8 }),
  P('djellaba', 'Chilaba larga', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'v', prof: 0.9, cintura: 1, holgura: 0.03, ribete: true, pliegues: 4 }),
  // ===== Américas =====
  P('unku', 'Unku inca', 'torso', 'tunica', { largo: 'rodilla', manga: 'sin', escote: 'ojal', cintura: 1, holgura: 0.01, ribeteBajo: 0.02, bandaPatron: 'escalonado', bordadoPecho: 'escalonado', bajo: 'recto', patron: { tipo: 'escalonado', escala: 1.3, rol: 'secundario' } }),
  P('unku-liso', 'Unku de lana lisa', 'torso', 'tunica', { largo: 'rodilla', manga: 'sin', escote: 'ojal', cintura: 1, holgura: 0.012, ribeteBajo: 0.015 }),
  P('aqsu', 'Aqsu (vestido de lana andino)', 'torso', 'tunica', { largo: 'tobillo', manga: 'sin', escote: 'cuadrado', cintura: 0.4, holgura: 0.01, vuelo: 0.03, ribeteBajo: 0.03, bandaPatron: 'grecas' }),
  P('lliclla', 'Lliclla (manto de hombros)', 'capa', 'chal', { largo: 'cadera', flecos: false, patron: { tipo: 'rayas', escala: 1.1, rol: 'secundario' } }),
  P('chumpi', 'Chumpi (faja tejida)', 'faja', 'faja', { y: 'cintura', ancho: 0.028, patron: { tipo: 'grecas', escala: 0.8, rol: 'secundario' } }),
  P('pollera', 'Pollera de volantes', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.14, pliegues: 4, volantes: 3, cintura: 'natural' }),
  P('poncho-andino', 'Poncho andino a listas', 'capa', 'poncho', { largo: 'cadera', ancho: 1, listas: 4, punta: 0.04, patron: { tipo: 'rayas', escala: 1.2, rol: 'secundario' } }),
  P('poncho-liso', 'Poncho de lana lisa', 'capa', 'poncho', { largo: 'muslo-alto', ancho: 1.05, listas: 2, punta: 0.07 }),
  P('manta-huasa', 'Manta de Castilla (poncho huaso)', 'capa', 'poncho', { largo: 'muslo', ancho: 1.1, listas: 3, punta: 0.08 }),
  P('chaqueta-huasa', 'Chaqueta corta huasa', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'larga', botones: 5, cintura: 0.5, abertura: 0.008, aberturaBajo: 0.01, solapa: false, puños: true }),
  P('faja-huasa', 'Faja de lana', 'faja', 'faja', { y: 'cintura', ancho: 0.034, cuelga: 'der', doble: true }),
  P('bombachas', 'Bombachas de campo', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'bombacho', puños: true }),
  P('chiripa', 'Chiripá', 'inferior', 'pantalon', { largo: 'pantorrilla', ancho: 'bombacho', cintura: 'baja' }),
  P('chamal', 'Chamal mapuche', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'sin', escote: 'cuadrado', prof: 0.9, cintura: 0.7, holgura: 0.012, vuelo: 0.03, ribeteBajo: 0.02, bajo: 'recto' }),
  P('ukulla', 'Ükülla (manto)', 'capa', 'chal', { largo: 'muslo', flecos: true }),
  P('trariwe', 'Trarüwe (faja mapuche)', 'faja', 'faja', { y: 'cintura', ancho: 0.03, patron: { tipo: 'grecas', escala: 0.8, rol: 'secundario' } }),
  P('trapelacucha', 'Trapelacucha (pectoral de plata)', 'cuello', 'cuello', { tipo: 'trapelacucha' }),
  P('kepam', 'Küpam (vestido de una pieza)', 'torso', 'tunica', { largo: 'tobillo', manga: 'sin', escote: 'cuadrado', cintura: 0.6, holgura: 0.01, vuelo: 0.02, ribeteBajo: 0.03, bandaPatron: 'escalonado' }),
  P('makun', 'Makuñ (poncho mapuche)', 'capa', 'poncho', { largo: 'cadera', ancho: 0.9, listas: 3, punta: 0.03, patron: { tipo: 'grecas', escala: 1, rol: 'secundario' } }),
  P('huipil', 'Huipil bordado', 'torso', 'tunica', { largo: 'muslo', manga: 'corta', escote: 'ojal', cintura: 1, holgura: 0.02, bordadoPecho: 'flores', bandaPatron: 'flores', ribeteBajo: 0.03, ribete: true }),
  P('huipil-largo', 'Huipil largo', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'ojal', cintura: 1, holgura: 0.02, bordadoPecho: 'rombos', ribeteBajo: 0.03, bandaPatron: 'rombos', ribete: true }),
  P('enagua-maya', 'Enagua de tela envuelta', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.02, pliegues: 3, ribeteBajo: 0.02, patron: { tipo: 'rayas', escala: 1, rol: 'secundario' } }),
  P('maxtlatl', 'Maxtlatl (braguero)', 'inferior', 'taparrabo', { largo: 'muslo', ancho: 0.04, punta: 0.04, ribeteBajo: true }),
  P('tilmatli', 'Tilmatli (manto anudado)', 'capa', 'capa', { largo: 'pantorrilla', ancho: 0.8, cierre: 'broche', ribete: true, patron: { tipo: 'rombos', escala: 1, rol: 'secundario' } }),
  P('cueitl', 'Cueitl (falda envuelta)', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.02, pliegues: 4, ribeteBajo: 0.025, bandaPatron: 'escalonado' }),
  P('quillango', 'Quillango de piel de guanaco', 'capa', 'capa', { largo: 'pantorrilla', ancho: 0.8, cierre: 'abierta', ribete: false, patron: { tipo: 'piel', escala: 1.1, rol: 'secundario' } }),
  P('taparrabo-cuero', 'Taparrabo de cuero', 'inferior', 'taparrabo', { largo: 'muslo-alto', ancho: 0.04, punta: 0.01 }),
  P('vestido-cuero-flecos', 'Vestido de gamuza con flecos', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'ancha', escote: 'v', prof: 0.5, cintura: 0.7, holgura: 0.02, flecos: true, ribeteBajo: 0.02, bordadoPecho: 'rombos', bandaPatron: 'rombos', bajo: 'dentado' }),
  P('camisa-gamuza', 'Camisa de gamuza con flecos', 'torso', 'tunica', { largo: 'muslo', manga: 'larga', escote: 'v', prof: 0.5, cintura: 0.9, holgura: 0.02, flecos: true, bajo: 'dentado', puños: true }),
  P('leggings-gamuza', 'Mitasas de gamuza', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'ajustado', puños: true }),
  P('manta-pueblo', 'Manta tejida', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'izq', pliegues: 4, orla: true, patron: { tipo: 'bandas', escala: 0.9, rol: 'secundario' } }),
  P('parka', 'Parka de piel con ribete', 'torso', 'tunica', { largo: 'muslo', manga: 'larga', escote: 'redondo', cintura: 1, holgura: 0.04, patron: { tipo: 'piel', escala: 1.2, rol: 'secundario' }, puños: true, ribeteBajo: 0.03, bajo: 'curvo' }),
  P('pantalon-piel', 'Pantalón de piel', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'amplio', puños: true }),
  P('botas-piel', 'Botas de piel', 'calzado', 'calzado', { tipo: 'botas' }),
  P('pollera-huacha', 'Pollera de baile', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.17, pliegues: 7, volantes: 2, ribeteBajo: 0.03 }),
  P('rebozo', 'Rebozo', 'capa', 'chal', { largo: 'cadera', flecos: true, patron: { tipo: 'rayas-finas', escala: 1.2, rol: 'secundario' } }),
  P('camisa-rotos', 'Camisa de algodón del siglo XIX', 'torso', 'tunica', { largo: 'muslo', manga: 'larga', escote: 'ojal', botones: 3, cintura: 0.9, holgura: 0.016, puños: false, ribete: false }),
  P('mantilla', 'Mantilla de encaje', 'capa', 'chal', { largo: 'pecho', flecos: false }),
  // ===== Oceanía =====
  P('lava-lava', 'Lava-lava', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.01, pliegues: 2, cintura: 'baja', patron: { tipo: 'ondas', escala: 0.9, rol: 'secundario' } }),
  P('tapa-pareo', 'Pareo de tapa', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.01, pliegues: 2, patron: { tipo: 'rombos', escala: 1.1, rol: 'secundario' } }),
  P('tapa-manto', 'Manto de tapa', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'izq', pliegues: 3, orla: false, patron: { tipo: 'zigzag', escala: 1, rol: 'secundario' } }),
  P('top-fibra', 'Corpiño de fibra', 'torso', 'tunica', { largo: 'pecho', manga: 'sin', escote: 'barco', cintura: 0, holgura: 0.002, ribete: false }),
  P('guirnalda', 'Collar de flores (lei)', 'cuello', 'cuello', { tipo: 'cuentas-largas' }),
  P('maro', 'Maro (taparrabo de tapa)', 'inferior', 'taparrabo', { largo: 'muslo', ancho: 0.035, punta: 0.01 }),
  ...PRENDAS_AMPLIADAS,
  ...PRENDAS_SIGLO20,
  ...PRENDAS_REGIONALES_2,
  ...PRENDAS_MODA,
];

const PRENDAS_POR_ID = Object.fromEntries(PRENDAS.map((p) => [p.id, p]));

Object.assign(__e, { PRENDAS, PRENDAS_POR_ID });
return __e;
})();

__m['datos/atuendos-ampliados'] = (() => {
const __e = {};
// Atuendos de la ampliación. Misma forma que atuendos.js. El campo `conf` marca cuán específica y respaldada es la
// representación: 'media' es una aproximación razonable de un traje conocido; 'baja' es genérica o muy simplificada y
// conviene revisarla con fuentes del propio pueblo antes de publicarla.

const { A, BL, CLAROS, OLIVA, OSCURA, EBANO } = __m['datos/ayuda'];


const ATUENDOS_AMPLIADOS = [
  // ===== Europa rural y regional =====
  A('vasco-hombre', 'Vasco con boina y faja', 'europa-campesina', [1700, 1950], 'm', 'baja', 'camisa-blanca chaleco-campesino pantalon-recto faja-vasca alpargatas', { A: 'lana-negra gris-pizarra indigo-profundo', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'boina', pieles: CLAROS }),
  A('vasco-mujer', 'Vasca con mandil y chal', 'europa-campesina', [1700, 1950], 'f', 'baja', 'blusa-regional falda-larga mandil-regional chal-lana alpargatas', { A: 'lana-negra rojo-granza indigo', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'trenza-una recogido', tocados: 'panuelo ninguno', pieles: CLAROS }),
  A('andaluz-hombre', 'Andaluz con traje corto', 'europa-campesina', [1800, 1950], 'm', 'media', 'camisa-blanca traje-corto pantalon-alto faja-vasca botas-cuero', { A: 'lana-negra gris-ceniza tabaco', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha', pieles: OLIVA }),
  A('andaluza-mujer', 'Andaluza con falda de volantes', 'europa-campesina', [1800, 1950], 'f', 'media', 'blusa-regional falda-volantes chal-lana zapatos-hebilla', { A: 'rojo-cochinilla azul-prusia lana-negra verde-botella', B: BL, C: 'lana-negra', D: 'oro' }, { peinados: 'recogido', tocados: 'velo ninguno', pieles: OLIVA }),
  A('gallego-hombre', 'Gallego con chaleco y zuecos', 'europa-campesina', [1700, 1950], 'm', 'baja', 'camisa-blanca chaleco-campesino pantalon-recto zuecos', { A: 'lana-negra lana-parda indigo', B: BL, C: 'lana-negra', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'boina', pieles: CLAROS }),
  A('gallega-mujer', 'Gallega con mandil y pañuelo', 'europa-campesina', [1700, 1950], 'f', 'baja', 'blusa-regional falda-larga delantal-blanco chal-lana zuecos', { A: 'lana-negra rojo-granza indigo-profundo', B: BL, C: 'lana-negra', D: 'cobre' }, { peinados: 'trenza-una recogido', tocados: 'panuelo', pieles: CLAROS }),
  A('italiano-campesino', 'Campesino italiano', 'europa-campesina', [1750, 1950], 'm', 'baja', 'camisa-blanca chaleco-campesino pantalon-recto faja-vasca zapatos-cuero', { A: 'lana-parda nogal indigo verde-musgo', B: BL, C: 'rojo-granza', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana boina', pieles: OLIVA }),
  A('italiana-campesina', 'Campesina italiana', 'europa-campesina', [1750, 1950], 'f', 'baja', 'blusa-regional corpino falda-larga mandil-regional zapatos-cuero', { A: 'rojo-granza indigo verde-hoja lana-parda', B: BL, C: 'lana-negra', D: 'cobre' }, { peinados: 'trenza-una recogido', tocados: 'panuelo ninguno', pieles: OLIVA }),
  A('croata-hombre', 'Croata con gunj bordado', 'europa-campesina', [1700, 1950], 'm', 'media', 'camisa-blanca gunj pantalon-recto opanke', { A: 'lana-negra azul-plomo lana-parda', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana ninguno', pieles: CLAROS }),
  A('croata-mujer', 'Croata con vestido y mandil bordado', 'europa-campesina', [1700, 1950], 'f', 'media', 'vestido-regional mandil-regional opanke collar-cuentas', { A: 'lana-negra azul-plomo rojo-granza', B: BL, C: 'rojo-granza', D: 'oro' }, { peinados: 'trenza-una recogido', tocados: 'panuelo ninguno', pieles: CLAROS }),
  A('bavaro-hombre', 'Bávaro con lederhosen', 'europa-campesina', [1800, 1950], 'm', 'media', 'camisa-blanca chaleco-campesino lederhosen polainas-lana zapatos-cuero', { A: 'cuero lana-parda verde-botella', B: BL, C: 'verde-botella', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha ninguno', pieles: CLAROS }),
  A('bavara-mujer', 'Bávara con dirndl', 'europa-campesina', [1800, 1950], 'f', 'media', 'blusa-regional dirndl-corpino falda-dirndl delantal-dirndl zapatos-cuero', { A: 'verde-botella azul-prusia borravino', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'trenzas-dos corona-trenzas', tocados: 'ninguno', pieles: CLAROS }),
  A('frances-hombre', 'Campesino francés', 'europa-campesina', [1750, 1950], 'm', 'baja', 'camisa-blanca chaleco-campesino pantalon-recto zuecos', { A: 'indigo lana-parda azul-plomo', B: BL, C: 'lana-negra', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'boina', pieles: CLAROS }),
  A('francesa-mujer', 'Campesina francesa', 'europa-campesina', [1750, 1950], 'f', 'baja', 'blusa-regional falda-larga delantal-blanco chal-lana zuecos', { A: 'indigo azul-plomo lana-parda borravino', B: BL, C: BL, D: 'cobre' }, { peinados: 'recogido', tocados: 'panuelo ninguno', pieles: CLAROS }),
  A('irlandes-hombre', 'Campesino irlandés', 'europa-campesina', [1750, 1950], 'm', 'baja', 'camisa-medieval chaleco-campesino calzones-rodilla calzas-largas zapatos-cuero', { A: 'lana-parda umbra azul-plomo', B: BL, C: 'lana-negra', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana', pieles: CLAROS }),
  A('irlandesa-mujer', 'Campesina irlandesa', 'europa-campesina', [1750, 1950], 'f', 'baja', 'blusa-campesina falda-larga chal-lana delantal-blanco zapatos-cuero', { A: 'rojo-granza lana-parda azul-plomo verde-musgo', B: BL, C: BL, D: 'cobre' }, { peinados: 'trenza-una recogido', tocados: 'panuelo ninguno', pieles: CLAROS }),
  // ===== Cáucaso, Asia central, Siberia, Himalaya =====
  A('caucasico-hombre', 'Hombre del Cáucaso con chokha', 'caucaso', [1700, 1950], 'm', 'media', 'camisa-blanca chokha pantalon-recto faja-del botas-altas', { A: 'lana-negra gris-ceniza tabaco borravino', B: BL, C: 'plata', D: 'plata' }, { peinados: 'corto', barbas: 'bigote barba-corta', tocados: 'kalpak', pieles: CLAROS + ' oliva-clara' }),
  A('caucasica-mujer', 'Mujer del Cáucaso', 'caucaso', [1700, 1950], 'f', 'media', 'vestido-caucasico zapatillas collar-colgante', { A: 'borravino azul-ultramar verde-botella lana-negra', B: BL, C: 'oro plata', D: 'oro' }, { peinados: 'trenzas-dos recogido', tocados: 'velo diadema', pieles: CLAROS + ' oliva-clara' }),
  A('mongol-hombre', 'Mongol con del', 'asia-central', [1200, 1950], 'm', 'media', 'del-mongol faja-del botas-mongolas', { A: 'indigo rojo-granza verde-botella azafran', B: 'azafran', C: 'azafran oro', D: 'plata' }, { peinados: 'trenza-una corto', barbas: 'bigote ninguna', tocados: 'gorro-mongol', pieles: 'clara-calida oliva-clara oliva' }),
  A('mongola-mujer', 'Mongola con del y collares', 'asia-central', [1200, 1950], 'f', 'media', 'del-mongol faja-del botas-mongolas collar-ancho-cuentas', { A: 'indigo rojo-cochinilla verde-botella azul-ultramar', B: 'azafran', C: 'azafran oro', D: 'plata' }, { peinados: 'trenzas-dos monos-laterales', tocados: 'gorro-mongol ninguno', pieles: 'clara-calida oliva-clara oliva' }),
  A('kazajo-hombre', 'Kazajo con chapán', 'asia-central', [1500, 1950], 'm', 'media', 'camisa-blanca chapan pantalon-recto faja-del botas-altas', { A: 'indigo rojo-cochinilla verde-botella lana-negra', B: BL, C: 'azafran', D: 'plata' }, { peinados: 'corto', barbas: 'bigote barba-corta ninguna', tocados: 'kalpak', pieles: 'clara-calida oliva-clara oliva' }),
  A('kazaja-mujer', 'Kazaja con del bordado', 'asia-central', [1500, 1950], 'f', 'media', 'del-mongol chaleco-kurdo botas-mongolas collar-colgante', { A: 'borravino verde-esmeralda azul-ultramar rojo-cochinilla', B: BL, C: 'oro', D: 'oro' }, { peinados: 'trenzas-dos recogido', tocados: 'gorro-mongol velo', pieles: 'clara-calida oliva-clara oliva' }),
  A('siberiano-hombre', 'Cazador siberiano', 'siberia', [1000, 1950], 'm', 'baja', 'parka-siberiana pantalon-piel botas-piel', { A: 'piel-guanaco cuero lana-parda', B: 'piel-nutria', C: 'rojo-ocre', D: 'crudo' }, { peinados: 'corto largo-liso', barbas: 'ninguna bigote', tocados: 'ushanka', pieles: 'clara clara-calida oliva-clara' }),
  A('siberiana-mujer', 'Mujer siberiana', 'siberia', [1000, 1950], 'f', 'baja', 'parka-siberiana pantalon-piel botas-piel collar-ancho-cuentas', { A: 'piel-guanaco cuero lana-parda', B: 'piel-nutria', C: 'rojo-cochinilla', D: 'plata' }, { peinados: 'trenzas-dos largo-liso', tocados: 'ushanka', pieles: 'clara clara-calida oliva-clara' }),
  A('tibetano-hombre', 'Tibetano con chuba', 'asia-himalaya', [1000, 1950], 'm', 'media', 'chuba-tibetana faja-del botas-tibetanas collar-ancho-cuentas', { A: 'rojo-cochinilla lana-parda borravino indigo', B: 'azafran', C: 'azafran', D: 'plata' }, { peinados: 'trenza-una corto', barbas: 'ninguna bigote', tocados: 'gorro-mongol ninguno', pieles: 'clara-calida oliva-clara oliva canela' }),
  A('tibetana-mujer', 'Tibetana con chuba y pangden', 'asia-himalaya', [1000, 1950], 'f', 'media', 'chuba-tibetana pangden botas-tibetanas collar-ancho-cuentas', { A: 'lana-negra indigo-profundo borravino', B: 'rojo-cochinilla azul-celeste azafran', C: 'azafran', D: 'turquesa' }, { peinados: 'trenzas-dos', tocados: 'ninguno', pieles: 'clara-calida oliva-clara oliva canela' }),
  // ===== India: trajes regionales =====
  A('rajput-hombre', 'Rajput con pagri', 'asia-sur-regional', [1200, 1950], 'm', 'alta', 'sherwani pyjama-indio zapatillas collar-colgante', { A: 'rojo-cochinilla azafran verde-esmeralda marfil', B: BL, C: 'oro', D: 'oro' }, { peinados: 'corto', barbas: 'bigote barba-corta', tocados: 'turbante', pieles: 'oliva canela bronce' }),
  A('rajasthani-mujer', 'Rajastaní con ghagra y odhni', 'asia-sur-regional', [1200, 1950], 'f', 'media', 'choli ghagra dupatta brazaletes-altos collar-colgante sandalias-cuero', { A: 'rojo-cartamo azafran verde-esmeralda rosa-cochinilla', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'trenza-una recogido', tocados: 'velo', pieles: 'oliva canela bronce' }),
  A('punjabi-hombre', 'Panyabí con turbante', 'asia-sur-regional', [1500, 1950], 'm', 'media', 'kurta salwar zapatillas', { A: 'blanco-lino azul-celeste azafran verde-hoja', B: BL, C: 'azul-ultramar', D: 'plata' }, { peinados: 'corto', barbas: 'barba-completa barba-larga', tocados: 'turbante', pieles: 'oliva-clara oliva canela', conf: 'media' }),
  A('punjabi-mujer', 'Panyabí con salwar kameez', 'asia-sur-regional', [1500, 1950], 'f', 'media', 'kameez salwar dupatta sandalias-cuero brazaletes', { A: 'rojo-cartamo verde-esmeralda azul-ultramar azafran', B: BL, C: 'oro', D: 'oro' }, { peinados: 'trenza-una recogido', tocados: 'ninguno', pieles: 'oliva-clara oliva canela' }),
  A('bengali-hombre', 'Bengalí con kurta y dhoti', 'asia-sur-regional', [1700, 1950], 'm', 'media', 'kurta dhoti sandalias-cuero', { A: 'blanco-lino crudo azafran', B: BL, C: 'rojo-cartamo', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'ninguno', pieles: 'oliva canela bronce cobre' }),
  A('bengali-mujer', 'Bengalí con sari de orilla roja', 'asia-sur-regional', [1700, 1950], 'f', 'media', 'sari-falda choli sari-pallu brazaletes sandalias-cuero', { A: 'blanco-lino marfil', B: 'rojo-cartamo', C: 'rojo-cartamo', D: 'oro' }, { peinados: 'trenza-una recogido', tocados: 'ninguno', pieles: 'oliva canela bronce cobre' }),
  A('tamil-hombre', 'Tamil con veshti', 'asia-sur-regional', [800, 1950], 'm', 'baja', 'veshti angavastram descalzo', { A: 'blanco-lino crudo', B: BL, C: 'rojo-cartamo azafran', D: 'oro' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'ninguno', pieles: 'canela bronce cobre castana' }),
  A('tamil-mujer', 'Tamil con sari', 'asia-sur-regional', [800, 1950], 'f', 'media', 'choli sari-falda sari-pallu collar-colgante brazaletes descalzo', { A: 'verde-esmeralda rojo-cartamo azafran azul-ultramar', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'trenza-una recogido', tocados: 'corona-flores ninguno', pieles: 'canela bronce cobre castana' }),
  A('cachemir-hombre', 'Cachemiro con pheran', 'asia-sur-regional', [1200, 1950], 'm', 'media', 'pheran salwar zapatillas', { A: 'lana-parda gris-pizarra borravino verde-musgo', B: BL, C: 'rojo-cartamo', D: 'plata' }, { peinados: 'corto', barbas: 'barba-completa barba-corta', tocados: 'kufi', pieles: 'clara-calida oliva-clara oliva' }),
  A('cachemir-mujer', 'Cachemira con pheran bordado', 'asia-sur-regional', [1200, 1950], 'f', 'media', 'pheran salwar dupatta zapatillas collar-ancho-cuentas', { A: 'rojo-cartamo borravino verde-esmeralda lana-parda', B: 'azafran', C: 'oro', D: 'plata' }, { peinados: 'trenzas-dos', tocados: 'velo', pieles: 'clara-calida oliva-clara oliva' }),
  // ===== Sudeste asiático: trajes regionales =====
  A('vietnamita-hombre', 'Campesino vietnamita', 'sudeste-regional', [1000, 1950], 'm', 'baja', 'chaqueta-mao pantalon-chino sandalias-cuero', { A: 'indigo lana-negra crudo', B: BL, C: 'indigo', D: 'cobre' }, { peinados: 'corto rapado', barbas: 'ninguna bigote', tocados: 'sombrero-conico', pieles: 'clara-calida oliva-clara oliva' }),
  A('vietnamita-mujer', 'Vietnamita con áo dài', 'sudeste-regional', [1700, 1950], 'f', 'media', 'ao-dai pantalon-seda zapatos-tela', { A: 'rosa-cochinilla azul-celeste verde-agua azafran blanco-lino', B: BL, C: 'oro', D: 'oro' }, { peinados: 'largo-liso recogido', tocados: 'sombrero-conico ninguno', pieles: 'clara-calida oliva-clara oliva' }),
  A('tailandes-hombre', 'Tailandés con chaqueta y chong kraben', 'sudeste-regional', [1500, 1950], 'm', 'media', 'chaqueta-mao dhoti sandalias-cuero', { A: 'blanco-lino azul-ultramar rojo-cartamo', B: BL, C: 'oro', D: 'oro' }, { peinados: 'corto', barbas: 'ninguna', tocados: 'ninguno', pieles: 'oliva-clara oliva canela', conf: 'baja' }),
  A('tailandesa-mujer', 'Tailandesa con sabai', 'sudeste-regional', [1500, 1950], 'f', 'media', 'choli angavastram sarong-batik collar-colgante sandalias-cuero', { A: 'verde-jade rojo-cartamo azul-ultramar azafran', B: 'oro', C: 'oro', D: 'oro' }, { peinados: 'recogido mono-alto', tocados: 'diadema ninguno', pieles: 'oliva-clara oliva canela', conf: 'baja' }),
  A('filipino-hombre', 'Filipino con barong', 'sudeste-regional', [1800, 1950], 'm', 'media', 'barong pantalon-recto zapatos-cuero', { A: BL + ' marfil', B: BL, C: BL, D: 'plata' }, { peinados: 'corto-raya corto', barbas: 'ninguna bigote', tocados: 'sombrero-ala-ancha ninguno', pieles: 'oliva canela bronce' }),
  A('filipina-mujer', 'Filipina con baro\'t saya', 'sudeste-regional', [1700, 1950], 'f', 'media', 'baro-saya saya panuelo-filipino zapatillas', { A: 'rojo-cochinilla azul-celeste rosa-antiguo marfil', B: BL, C: 'oro', D: 'oro' }, { peinados: 'recogido largo-liso', tocados: 'ninguno', pieles: 'oliva canela bronce' }),
  A('birmano-hombre', 'Birmano con longyi', 'sudeste-regional', [1500, 1950], 'm', 'baja', 'baju-kurung longyi sandalias-cuero', { A: 'blanco-lino indigo verde-hoja', B: BL, C: 'azafran', D: 'oro' }, { peinados: 'corto', barbas: 'ninguna', tocados: 'turbante ninguno', pieles: 'oliva canela bronce' }),
  A('birmana-mujer', 'Birmana con longyi', 'sudeste-regional', [1500, 1950], 'f', 'media', 'kebaya longyi sandalias-cuero collar-colgante', { A: 'rosa-cochinilla verde-hoja azul-celeste malva', B: BL, C: 'oro', D: 'oro' }, { peinados: 'recogido mono-alto', tocados: 'corona-flores ninguno', pieles: 'oliva canela bronce' }),
  A('javanes-hombre', 'Javanés con beskap y batik', 'sudeste-regional', [1500, 1950], 'm', 'media', 'beskap sarong-batik sandalias-cuero', { A: 'lana-negra indigo-profundo borravino', B: 'tabaco', C: 'oro', D: 'oro' }, { peinados: 'corto', barbas: 'ninguna bigote', tocados: 'ninguno', pieles: 'oliva canela bronce' }),
  A('javanesa-mujer', 'Javanesa con kebaya y batik', 'sudeste-regional', [1500, 1950], 'f', 'media', 'kebaya sarong-batik sandalias-cuero collar-colgante', { A: 'verde-jade rojo-cartamo azul-celeste malva', B: 'tabaco', C: 'oro', D: 'oro' }, { peinados: 'recogido mono-alto', tocados: 'corona-flores ninguno', pieles: 'oliva canela bronce' }),
  // ===== Asia oriental: ainu y manchú =====
  A('ainu-hombre', 'Ainu con attus', 'asia-este', [1500, 1950], 'm', 'baja', 'attus cinturon-cuero pantalon-chino botas-piel', { A: 'indigo lana-parda crudo', B: 'rojo-granza', C: 'crudo', D: 'cobre' }, { peinados: 'largo-ondulado corto', barbas: 'barba-larga barba-completa', tocados: 'ninguno', pieles: 'clara clara-calida oliva-clara', conf: 'baja' }),
  A('ainu-mujer', 'Ainu con attus y collares', 'asia-este', [1500, 1950], 'f', 'baja', 'attus collar-ancho-cuentas botas-piel', { A: 'indigo lana-parda crudo', B: 'rojo-granza', C: 'crudo', D: 'plata' }, { peinados: 'largo-liso largo-ondulado', tocados: 'diadema ninguno', pieles: 'clara clara-calida oliva-clara', conf: 'baja' }),
  A('manchu-hombre', 'Manchú de la dinastía Qing', 'asia-este', [1644, 1912], 'm', 'alta', 'changpao magua zapatos-tela', { A: 'azul-ultramar rojo-cochinilla verde-esmeralda azul-celeste', B: 'oro', C: 'oro', D: 'oro' }, { peinados: 'trenza-una', barbas: 'ninguna bigote', tocados: 'gorra-plana', pieles: 'clara clara-calida' }),
  A('manchu-mujer', 'Manchú con changpao', 'asia-este', [1644, 1912], 'f', 'alta', 'changpao zapatos-tela collar-colgante', { A: 'rosa-cochinilla azul-ultramar verde-jade violeta', B: 'oro', C: 'oro', D: 'oro' }, { peinados: 'recogido monos-laterales', tocados: 'diadema', pieles: 'porcelana clara clara-calida' }),
  // ===== Mundo antiguo =====
  A('minoico-hombre', 'Hombre minoico', 'mundo-antiguo', [-2700, -1100], 'm', 'media', 'faldellin-egeo cinturon-cuero sandalias-cuero', { A: BL + ' azafran', B: 'azul-egipcio', C: 'rojo-granza', D: 'oro' }, { peinados: 'rizos largo-ondulado', barbas: 'ninguna', tocados: 'ninguno', pieles: 'clara-calida oliva-clara oliva', conf: 'baja' }),
  A('minoica-mujer', 'Mujer minoica', 'mundo-antiguo', [-2700, -1100], 'f', 'media', 'falda-minoica corpino collar-cuentas descalzo', { A: 'azul-egipcio azafran rojo-granza marfil', B: 'rojo-granza', C: 'azul-egipcio', D: 'oro' }, { peinados: 'rizos largo-ondulado', tocados: 'diadema ninguno', pieles: 'clara-calida oliva-clara', conf: 'baja' }),
  A('fenicio-hombre', 'Fenicio', 'mundo-antiguo', [-1200, -300], 'm', 'media', 'tunica-fenicia sagum sandalias-cuero', { A: 'purpura-tiro borravino azul-egipcio blanco-lino', B: 'azafran', C: 'purpura-tiro', D: 'oro' }, { peinados: 'corto rizos', barbas: 'barba-completa barba-corta', tocados: 'gorro-frigio ninguno', pieles: 'oliva-clara oliva canela' }),
  A('fenicia-mujer', 'Fenicia', 'mundo-antiguo', [-1200, -300], 'f', 'media', 'tunica-fenicia chal-lana collar-colgante sandalias-cuero', { A: 'purpura-tiro azul-egipcio rojo-granza blanco-lino', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'recogido trenzas-dos', tocados: 'velo diadema', pieles: 'oliva-clara oliva canela' }),
  A('persa-antiguo-hombre', 'Persa aqueménida', 'mundo-antiguo', [-550, -330], 'm', 'alta', 'tunica-etrusca kandys pantalon-persa zapatos-cuero collar-colgante', { A: 'azul-egipcio rojo-cochinilla purpura-tiro verde-esmeralda', B: BL, C: 'oro', D: 'oro' }, { peinados: 'rizos corto', barbas: 'barba-larga barba-completa', tocados: 'gorro-frigio corona', pieles: 'oliva-clara oliva canela' }),
  A('persa-antigua-mujer', 'Persa aqueménida (mujer)', 'mundo-antiguo', [-550, -330], 'f', 'alta', 'tunica-fenicia manto-persa zapatos-cuero collar-colgante', { A: 'azul-egipcio rojo-cochinilla purpura-tiro verde-esmeralda', B: BL, C: 'oro', D: 'oro' }, { peinados: 'recogido trenzas-dos', tocados: 'velo diadema', pieles: 'oliva-clara oliva canela', conf: 'baja' }),
  A('escita-hombre', 'Escita', 'mundo-antiguo', [-700, -200], 'm', 'media', 'chaqueta-escita pantalon-escita botas-cuero cinturon-cuero', { A: 'rojo-granza lana-parda indigo verde-musgo', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'largo-liso corto', barbas: 'barba-completa', tocados: 'gorro-frigio', pieles: 'clara clara-calida oliva-clara', conf: 'baja' }),
  A('escita-mujer', 'Escita (mujer)', 'mundo-antiguo', [-700, -200], 'f', 'media', 'chaqueta-escita pantalon-escita botas-cuero collar-colgante', { A: 'rojo-granza lana-parda indigo verde-musgo', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'trenzas-dos largo-liso', tocados: 'gorro-frigio diadema', pieles: 'clara clara-calida oliva-clara', conf: 'baja' }),
  A('etrusco-hombre', 'Etrusco', 'mundo-antiguo', [-800, -100], 'm', 'media', 'tunica-etrusca himation-corto sandalias-cuero', { A: 'blanco-lino rojo-granza azafran', B: 'purpura-tiro', C: 'purpura-tiro', D: 'oro' }, { peinados: 'corto rizos', barbas: 'barba-corta ninguna', tocados: 'ninguno', pieles: 'clara-calida oliva-clara oliva' }),
  A('etrusca-mujer', 'Etrusca', 'mundo-antiguo', [-800, -100], 'f', 'alta', 'tunica-etrusca himation collar-colgante sandalias-cuero', { A: 'blanco-lino rojo-granza azafran azul-celeste', B: 'purpura-tiro', C: 'oro', D: 'oro' }, { peinados: 'largo-ondulado trenzas-dos', tocados: 'diadema ninguno', pieles: 'clara-calida oliva-clara oliva' }),
  A('ibero-hombre', 'Íbero', 'mundo-antiguo', [-700, -100], 'm', 'media', 'tunica-ibera sagum cinturon-cuero sandalias-cuero', { A: 'lana-negra lana-parda rojo-granza blanco-lino', B: 'rojo-granza', C: 'rojo-granza', D: 'bronce' }, { peinados: 'largo-liso corto', barbas: 'barba-corta ninguna', tocados: 'ninguno', pieles: 'clara-calida oliva-clara oliva', conf: 'baja' }),
  A('ibera-mujer', 'Íbera', 'mundo-antiguo', [-700, -100], 'f', 'alta', 'tunica-ibera chal-lana collar-colgante sandalias-cuero', { A: 'blanco-lino rojo-granza lana-parda', B: 'azul-celeste', C: 'bronce', D: 'bronce' }, { peinados: 'recogido trenzas-dos', tocados: 'velo diadema', pieles: 'clara-calida oliva-clara oliva', conf: 'baja' }),
  A('hitita-hombre', 'Hitita', 'mundo-antiguo', [-1600, -1180], 'm', 'media', 'tunica-sumeria cinturon-cuero botas-cuero', { A: 'lana-clara rojo-granza indigo', B: 'lana-parda', C: 'rojo-granza', D: 'bronce' }, { peinados: 'largo-liso corto', barbas: 'ninguna bigote', tocados: 'gorro-lana', pieles: 'clara-calida oliva-clara oliva', conf: 'baja' }),
  A('hitita-mujer', 'Hitita (mujer)', 'mundo-antiguo', [-1600, -1180], 'f', 'media', 'tunica-fenicia manto-asirio botas-cuero collar-cuentas', { A: 'lana-clara rojo-granza indigo', B: 'lana-parda', C: 'rojo-granza', D: 'bronce' }, { peinados: 'trenzas-dos recogido', tocados: 'velo', pieles: 'clara-calida oliva-clara oliva', conf: 'baja' }),
  A('anglosajon-hombre', 'Anglosajón', 'germano', [450, 1066], 'm', 'media', 'tunica-vikinga bragas-holgadas capa-medieval botas-cuero cinturon-cuero', { A: 'lana-parda verde-musgo indigo rojo-granza', B: 'lana-clara', C: 'rojo-granza', D: 'bronce' }, { peinados: 'largo-liso corto', barbas: 'barba-completa barba-corta', tocados: 'ninguno', pieles: CLAROS }),
  A('anglosajona-mujer', 'Anglosajona', 'germano', [450, 1066], 'f', 'media', 'tunica-vikinga vestido-delantal-vikingo broche-ovalado zapatos-cuero', { A: 'indigo-claro rojo-granza verde-musgo', B: 'lana-clara', C: 'rojo-granza', D: 'bronce' }, { peinados: 'trenza-una recogido', tocados: 'velo panuelo', pieles: CLAROS }),
  A('visigodo-hombre', 'Visigodo', 'germano', [400, 711], 'm', 'media', 'tunica-vikinga pantalones-celtas capa-medieval botas-cuero cinturon-cuero', { A: 'lana-parda rojo-granza indigo', B: 'lana-clara', C: 'rojo-granza', D: 'bronce' }, { peinados: 'largo-liso corto', barbas: 'barba-completa', tocados: 'ninguno', pieles: CLAROS }),
  A('visigoda-mujer', 'Visigoda', 'germano', [400, 711], 'f', 'media', 'tunica-vikinga vestido-delantal-vikingo broche-ovalado zapatos-cuero', { A: 'azul-plomo rojo-granza verde-musgo', B: 'lana-clara', C: 'rojo-granza', D: 'oro' }, { peinados: 'trenzas-dos recogido', tocados: 'velo', pieles: CLAROS }),
  A('kushita-hombre', 'Kushita de Nubia', 'africa-nilo', [-800, 350], 'm', 'alta', 'shenti-plisado collar-usej sandalias-cuero brazaletes-altos', { A: 'blanco-lino marfil', B: 'azul-egipcio', C: 'oro', D: 'oro' }, { peinados: 'rapado corto', tocados: 'diadema ninguno', pieles: 'ebano-claro ebano castana', conf: 'baja' }),
  A('kushita-mujer', 'Kushita de Nubia (mujer)', 'africa-nilo', [-800, 350], 'f', 'alta', 'kalasiris-plisada collar-usej sandalias-cuero brazaletes-altos', { A: 'blanco-lino marfil', B: 'azul-egipcio', C: 'oro', D: 'oro' }, { peinados: 'trenzas-pegadas peluca-egipcia', tocados: 'diadema ninguno', pieles: 'ebano-claro ebano castana', conf: 'baja' }),
  A('sudanes-hombre', 'Sudanés con jalabiya', 'africa-nilo', [1500, 1950], 'm', 'media', 'jalabiya-algodon faja-del sandalias-cuero', { A: 'blanco-lino crudo marfil', B: BL, C: 'indigo', D: 'plata' }, { peinados: 'rapado corto', barbas: 'bigote barba-corta ninguna', tocados: 'turbante kufi', pieles: 'castana ebano-claro ebano' }),
  A('sudanesa-mujer', 'Sudanesa con tob', 'africa-nilo', [1500, 1950], 'f', 'media', 'tob-sudanes sandalias-cuero collar-ancho-cuentas brazaletes', { A: 'rojo-cartamo azul-ultramar verde-esmeralda azafran malva', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'trenzas-pegadas trenzas-dos', tocados: 'ninguno', pieles: 'castana ebano-claro ebano' }),
  // ===== Medio Oriente =====
  A('palestino-hombre', 'Palestino con kufiya', 'medio-oriente', [1700, 1950], 'm', 'media', 'jalabiya-algodon chaleco-kurdo faja-turbante sandalias-cuero', { A: 'blanco-lino crudo lana-parda indigo', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'corto', barbas: 'bigote barba-corta', tocados: 'kufiya', pieles: 'oliva-clara oliva canela' }),
  A('palestina-mujer', 'Palestina con thobe bordado', 'medio-oriente', [1700, 1950], 'f', 'media', 'thobe-palestino zapatillas collar-colgante', { A: 'lana-negra indigo-profundo borravino', B: 'rojo-cochinilla', C: 'rojo-cochinilla', D: 'plata' }, { peinados: 'trenzas-dos recogido', tocados: 'velo panuelo', pieles: 'oliva-clara oliva canela' }),
  A('libanes-hombre', 'Libanés con fez', 'medio-oriente', [1700, 1950], 'm', 'media', 'camisa-blanca chaleco-campesino sirwal faja-turbante zapatillas', { A: 'lana-negra gris-pizarra indigo borravino', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'bigote barba-corta', tocados: 'fez', pieles: 'clara-calida oliva-clara oliva' }),
  A('libanesa-mujer', 'Libanesa con chal', 'medio-oriente', [1700, 1950], 'f', 'media', 'vestido-regional chal-lana zapatillas collar-colgante', { A: 'azul-prusia borravino verde-botella lana-negra', B: BL, C: 'oro', D: 'oro' }, { peinados: 'recogido trenzas-dos', tocados: 'velo ninguno', pieles: 'clara-calida oliva-clara oliva' }),
  A('kurdo-hombre', 'Kurdo con chaleco y faja', 'medio-oriente', [1500, 1950], 'm', 'media', 'camisa-medieval chaleco-kurdo sirwal faja-turbante zapatillas', { A: 'lana-parda gris-pizarra rojo-granza verde-musgo', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'bigote barba-corta', tocados: 'turbante', pieles: 'clara-calida oliva-clara oliva' }),
  A('kurda-mujer', 'Kurda con chaleco y sirwal', 'medio-oriente', [1500, 1950], 'f', 'media', 'vestido-regional chaleco-kurdo sirwal zapatillas collar-ancho-cuentas', { A: 'rojo-cartamo verde-esmeralda azul-ultramar azafran', B: 'azafran', C: 'oro', D: 'plata' }, { peinados: 'trenzas-dos largo-liso', tocados: 'velo diadema', pieles: 'clara-calida oliva-clara oliva' }),
  A('yemenita-hombre', 'Yemenita', 'medio-oriente', [1000, 1950], 'm', 'media', 'camisa-medieval lungi faja-del sandalias-cuero', { A: BL + ' lana-parda indigo', B: 'indigo', C: 'rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'barba-corta bigote', tocados: 'turbante', pieles: 'oliva canela bronce' }),
  A('yemenita-mujer', 'Yemenita con thobe y joyas', 'medio-oriente', [1000, 1950], 'f', 'media', 'thobe-palestino sirwal zapatillas collar-ancho-cuentas', { A: 'indigo-profundo rojo-cartamo verde-esmeralda lana-negra', B: 'azafran', C: 'plata', D: 'plata' }, { peinados: 'trenzas-dos', tocados: 'velo', pieles: 'oliva canela bronce' }),
  // ===== África =====
  A('etiope-hombre', 'Etíope con shamma', 'africa-cuerno', [1000, 1950], 'm', 'media', 'shamma sandalias-cuero', { A: 'algodon-crudo blanco-lino', B: 'rojo-cartamo', C: 'rojo-cartamo azafran verde-hoja', D: 'oro' }, { peinados: 'rapado corto', barbas: 'bigote barba-corta ninguna', tocados: 'ninguno', pieles: 'canela bronce cobre castana' }),
  A('etiope-mujer', 'Etíope con habesha kemis', 'africa-cuerno', [1000, 1950], 'f', 'media', 'habesha-kemis shamma collar-ancho-cuentas sandalias-cuero', { A: 'algodon-crudo blanco-lino', B: 'rojo-cartamo', C: 'rojo-cartamo azafran verde-hoja', D: 'oro' }, { peinados: 'trenzas-pegadas trenzas-dos', tocados: 'ninguno', pieles: 'canela bronce cobre castana' }),
  A('tuareg-hombre', 'Tuareg con túnica índigo', 'africa-sahel', [1000, 1950], 'm', 'media', 'gandora sirwal sandalias-cuero cinturon-cuero', { A: 'indigo indigo-profundo indigo-claro', B: 'indigo-claro', C: 'plata', D: 'plata' }, { peinados: 'corto', barbas: 'ninguna', tocados: 'turbante', pieles: 'oliva canela bronce cobre' }),
  A('tuareg-mujer', 'Tuareg con velo y joyas de plata', 'africa-sahel', [1000, 1950], 'f', 'media', 'gandora sirwal sandalias-cuero collar-ancho-cuentas brazaletes', { A: 'indigo indigo-profundo lana-negra', B: 'indigo-claro', C: 'plata', D: 'plata' }, { peinados: 'trenzas-dos', tocados: 'velo', pieles: 'oliva canela bronce cobre' }),
  A('hausa-hombre', 'Hausa con babban riga', 'africa-sahel', [1000, 1950], 'm', 'alta', 'boubou sokoto sandalias-cuero', { A: 'azul-celeste blanco-lino indigo azafran', B: 'blanco-lino', C: 'azafran', D: 'plata' }, { peinados: 'rapado', barbas: 'bigote barba-corta ninguna', tocados: 'kufi', pieles: 'castana ebano-claro ebano' }),
  A('fulani-mujer', 'Fulani con collares y faldas', 'africa-sahel', [1000, 1950], 'f', 'media', 'wrap-top kanga collar-ancho-cuentas brazaletes sandalias-cuero', { A: 'rojo-cartamo azafran indigo verde-hoja', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'trenzas-pegadas trenzas-dos', tocados: 'sombrero-conico ninguno', pieles: 'canela bronce cobre castana' }),
  A('yoruba-hombre', 'Yoruba con agbada', 'africa-oeste', [1000, 1950], 'm', 'alta', 'buba sokoto agbada sandalias-cuero', { A: 'indigo azul-celeste blanco-lino verde-hoja', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'rapado', barbas: 'bigote ninguna', tocados: 'kufi', pieles: EBANO }),
  A('yoruba-mujer', 'Yoruba con buba, iro y gele', 'africa-oeste', [1000, 1950], 'f', 'media', 'buba iro collar-ancho-cuentas sandalias-cuero', { A: 'indigo rojo-cartamo verde-hoja azafran', B: 'rojo-cartamo', C: 'oro', D: 'oro' }, { peinados: 'trenzas-pegadas', tocados: 'gele', pieles: EBANO }),
  A('xhosa-hombre', 'Xhosa con manta', 'africa-este-sur', [1700, 1950], 'm', 'media', 'manta-xhosa pantalon-recto collar-ancho-cuentas sandalias-cuero', { A: 'rojo-ocre rojo-tierra lana-parda', B: 'rojo-ocre', C: 'azul-celeste', D: 'turquesa' }, { peinados: 'rapado corto', barbas: 'bigote ninguna', tocados: 'ninguno', pieles: EBANO, conf: 'baja' }),
  A('xhosa-mujer', 'Xhosa con manta y cuentas', 'africa-este-sur', [1700, 1950], 'f', 'media', 'manta-xhosa falda-larga collar-ancho-cuentas brazaletes-altos sandalias-cuero', { A: 'rojo-ocre rojo-tierra lana-parda', B: 'rojo-ocre', C: 'azul-celeste', D: 'turquesa' }, { peinados: 'rapado', tocados: 'turbante panuelo', pieles: EBANO, conf: 'baja' }),
  A('himba-hombre', 'Himba (representación genérica)', 'africa-este-sur', [1500, 1950], 'm', 'baja', 'falda-cuero collar-ancho-cuentas brazaletes-altos descalzo', { A: 'cuero-claro rojo-ocre piel-guanaco', B: 'cuero', C: 'rojo-ocre', D: 'bronce' }, { peinados: 'trenzas-pegadas rapado', tocados: 'ninguno', pieles: 'ebano-claro ebano', conf: 'baja', nota: 'No se puede reproducir la pintura corporal de ocre y grasa (otjize): el tono de piel es el del catálogo.' }),
  A('himba-mujer', 'Himba (representación genérica)', 'africa-este-sur', [1500, 1950], 'f', 'baja', 'falda-cuero collar-ancho-cuentas brazaletes-altos descalzo', { A: 'cuero-claro rojo-ocre piel-guanaco', B: 'cuero', C: 'rojo-ocre', D: 'bronce' }, { peinados: 'trenzas-pegadas', tocados: 'ninguno', pieles: 'ebano-claro ebano', conf: 'baja', nota: 'No se puede reproducir la pintura corporal de ocre y grasa (otjize): el tono de piel es el del catálogo.' }),
  A('khoisan-hombre', 'Khoisan (representación genérica)', 'africa-este-sur', [-2000, 1900], 'm', 'baja', 'kaross taparrabo-cuero collar-cuentas sandalias-cuero', { A: 'piel-guanaco cuero-claro', B: 'cuero', C: 'rojo-ocre', D: 'crudo' }, { peinados: 'rizos rapado', barbas: 'ninguna', tocados: 'ninguno', pieles: 'canela bronce cobre castana', conf: 'baja' }),
  A('khoisan-mujer', 'Khoisan (representación genérica)', 'africa-este-sur', [-2000, 1900], 'f', 'baja', 'kaross falda-cuero collar-cuentas sandalias-cuero', { A: 'piel-guanaco cuero-claro', B: 'cuero', C: 'rojo-ocre', D: 'crudo' }, { peinados: 'rizos rapado', tocados: 'ninguno', pieles: 'canela bronce cobre castana', conf: 'baja' }),
  A('swahili-hombre', 'Suajili con kanzu', 'africa-este-sur', [1000, 1950], 'm', 'media', 'jalabiya-algodon chaleco-kurdo sandalias-cuero', { A: 'blanco-lino crudo marfil', B: BL, C: 'rojo-cartamo', D: 'plata' }, { peinados: 'rapado corto', barbas: 'bigote barba-corta ninguna', tocados: 'kufi', pieles: 'castana ebano-claro ebano' }),
  A('swahili-mujer', 'Suajili con kanga', 'africa-este-sur', [1000, 1950], 'f', 'media', 'wrap-top kanga collar-colgante sandalias-cuero', { A: 'rojo-cartamo azul-ultramar verde-hoja azafran', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'trenzas-pegadas', tocados: 'velo panuelo', pieles: 'castana ebano-claro ebano' }),
  A('malgache-hombre', 'Malgache con lamba', 'africa-este-sur', [1500, 1950], 'm', 'media', 'camisa-blanca lamba sandalias-cuero', { A: 'blanco-lino crudo rojo-ocre', B: 'rojo-cartamo', C: 'rojo-cartamo', D: 'plata' }, { peinados: 'corto', barbas: 'ninguna bigote', tocados: 'sombrero-conico ninguno', pieles: 'canela bronce cobre castana', conf: 'baja' }),
  A('malgache-mujer', 'Malgache con lamba', 'africa-este-sur', [1500, 1950], 'f', 'media', 'blusa-campesina lamba collar-cuentas sandalias-cuero', { A: 'blanco-lino crudo rojo-ocre', B: 'rojo-cartamo', C: 'rojo-cartamo', D: 'plata' }, { peinados: 'trenzas-pegadas trenzas-dos', tocados: 'panuelo ninguno', pieles: 'canela bronce cobre castana', conf: 'baja' }),
  // ===== Amazonia y Caribe =====
  A('amazonico-hombre', 'Hombre amazónico con cushma', 'amazonia', [1500, 1950], 'm', 'media', 'cushma collar-ancho-cuentas descalzo', { A: 'algodon-crudo tabaco rojo-ocre', B: 'nogal', C: 'rojo-ocre', D: 'turquesa' }, { peinados: 'flequillo largo-liso', barbas: 'ninguna', tocados: 'tocado-plumas ninguno', pieles: OSCURA, conf: 'baja' }),
  A('amazonica-mujer', 'Mujer amazónica con cushma', 'amazonia', [1500, 1950], 'f', 'media', 'cushma collar-ancho-cuentas descalzo', { A: 'algodon-crudo tabaco rojo-ocre', B: 'nogal', C: 'rojo-ocre', D: 'turquesa' }, { peinados: 'largo-liso trenzas-dos', tocados: 'diadema ninguno', pieles: OSCURA, conf: 'baja' }),
  A('guarani-hombre', 'Guaraní', 'amazonia', [1400, 1950], 'm', 'baja', 'camisa-rotos pantalon-recto collar-ancho-cuentas descalzo', { A: 'algodon-crudo lana-parda rojo-ocre', B: 'rojo-ocre', C: 'rojo-ocre', D: 'turquesa' }, { peinados: 'flequillo largo-liso', barbas: 'ninguna', tocados: 'tocado-plumas ninguno', pieles: OSCURA, conf: 'baja' }),
  A('guarani-mujer', 'Guaraní con tipoy', 'amazonia', [1400, 1950], 'f', 'baja', 'tipoy collar-ancho-cuentas descalzo', { A: 'algodon-crudo rojo-ocre azul-celeste', B: 'rojo-ocre', C: 'rojo-ocre', D: 'turquesa' }, { peinados: 'largo-liso trenzas-dos', tocados: 'ninguno', pieles: OSCURA, conf: 'baja' }),
  A('yanomami-hombre', 'Yanomami (representación genérica)', 'amazonia', [1000, 1950], 'm', 'baja', 'taparrabo-cuero faja-fibra collar-ancho-cuentas brazaletes-altos descalzo', { A: 'cuero-claro crudo', B: 'rojo-ocre', C: 'rojo-ocre', D: 'naranja-achiote' }, { peinados: 'flequillo', barbas: 'ninguna', tocados: 'tocado-plumas ninguno', pieles: OSCURA, conf: 'baja' }),
  A('yanomami-mujer', 'Yanomami (representación genérica)', 'amazonia', [1000, 1950], 'f', 'baja', 'nagua faja-fibra collar-ancho-cuentas brazaletes-altos descalzo', { A: 'cuero-claro crudo rojo-ocre', B: 'rojo-ocre', C: 'rojo-ocre', D: 'naranja-achiote' }, { peinados: 'flequillo largo-liso', tocados: 'ninguno', pieles: OSCURA, conf: 'baja' }),
  A('taino-hombre', 'Taíno (cacique y pueblo)', 'caribe', [1000, 1550], 'm', 'media', 'maxtlatl faja-fibra collar-cemi brazaletes-altos descalzo', { A: 'algodon-crudo crudo', B: 'rojo-ocre', C: 'rojo-ocre', D: 'oro' }, { peinados: 'flequillo largo-liso', barbas: 'ninguna', tocados: 'tocado-plumas diadema ninguno', pieles: OSCURA, conf: 'baja' }),
  A('taina-mujer', 'Taína con nagua', 'caribe', [1000, 1550], 'f', 'media', 'nagua collar-cemi brazaletes-altos descalzo', { A: 'algodon-crudo rojo-ocre azul-celeste', B: 'rojo-ocre', C: 'rojo-ocre', D: 'oro' }, { peinados: 'largo-liso trenzas-dos flequillo', tocados: 'diadema corona-flores ninguno', pieles: OSCURA, conf: 'baja' }),
  // ===== Norteamérica =====
  A('navajo-hombre', 'Diné (navajo) con camisa de terciopelo', 'norteamerica', [1850, 1950], 'm', 'media', 'blusa-terciopelo pantalon-recto cinturon-concho mocasines collar-ancho-cuentas', { A: 'borravino azul-prusia verde-botella', B: BL, C: 'plata', D: 'turquesa' }, { peinados: 'largo-liso mono-chonmage', barbas: 'ninguna', tocados: 'diadema ninguno', pieles: OSCURA }),
  A('navajo-mujer', 'Diné (navajo) con falda de volantes', 'norteamerica', [1850, 1950], 'f', 'media', 'blusa-terciopelo falda-navajo cinturon-concho collar-ancho-cuentas mocasines', { A: 'borravino azul-prusia verde-botella malva', B: 'rojo-cartamo azul-celeste', C: 'plata', D: 'turquesa' }, { peinados: 'recogido largo-liso', tocados: 'ninguno', pieles: OSCURA }),
  A('haudenosaunee-hombre', 'Haudenosaunee (iroqués)', 'norteamerica', [1600, 1900], 'm', 'media', 'camisa-gamuza kilt-haudenosaunee leggings-gamuza mocasines collar-ancho-cuentas', { A: 'cuero-claro piel-guanaco', B: 'rojo-cartamo azul-ultramar', C: 'rojo-cartamo', D: 'azul-ultramar' }, { peinados: 'rapado trenzas-dos', barbas: 'ninguna', tocados: 'diadema tocado-plumas', pieles: OSCURA, conf: 'baja' }),
  A('haudenosaunee-mujer', 'Haudenosaunee (mujer)', 'norteamerica', [1600, 1900], 'f', 'media', 'vestido-cuero-flecos kilt-haudenosaunee leggings-gamuza mocasines collar-ancho-cuentas', { A: 'cuero-claro piel-guanaco', B: 'rojo-cartamo azul-ultramar', C: 'rojo-cartamo', D: 'azul-ultramar' }, { peinados: 'trenzas-dos largo-liso', tocados: 'diadema ninguno', pieles: OSCURA, conf: 'baja' }),
  A('tlingit-hombre', 'Tlingit (noroeste del Pacífico)', 'norteamerica', [1700, 1950], 'm', 'alta', 'camisa-gamuza leggings-gamuza manto-chilkat mocasines', { A: 'lana-negra cuero-claro', B: 'azul-egipcio verde-jade', C: 'rojo-cartamo', D: 'azafran' }, { peinados: 'largo-liso corto', barbas: 'ninguna', tocados: 'sombrero-conico ninguno', pieles: OSCURA, conf: 'baja' }),
  A('tlingit-mujer', 'Tlingit (mujer)', 'norteamerica', [1700, 1950], 'f', 'alta', 'vestido-cuero-flecos manto-chilkat mocasines collar-ancho-cuentas', { A: 'lana-negra cuero-claro', B: 'azul-egipcio verde-jade', C: 'rojo-cartamo', D: 'azafran' }, { peinados: 'trenzas-dos largo-liso', tocados: 'sombrero-conico ninguno', pieles: OSCURA, conf: 'baja' }),
  A('seminola-hombre', 'Seminola con chaqueta de retazos', 'norteamerica', [1800, 1950], 'm', 'media', 'camisa-blanca chaqueta-patchwork pantalon-recto mocasines', { A: 'rojo-cartamo azul-ultramar azafran verde-hoja', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'corto', barbas: 'ninguna', tocados: 'turbante', pieles: OSCURA, conf: 'baja' }),
  A('seminola-mujer', 'Seminola con collares y falda de volantes', 'norteamerica', [1800, 1950], 'f', 'media', 'blusa-regional falda-volantes collar-ancho-cuentas mocasines', { A: 'rojo-cartamo azul-ultramar azafran verde-hoja', B: BL, C: 'lana-negra', D: 'turquesa' }, { peinados: 'recogido largo-liso', tocados: 'ninguno', pieles: OSCURA, conf: 'baja' }),
  A('cherokee-hombre', 'Cheroqui (siglo XIX)', 'norteamerica', [1790, 1900], 'm', 'media', 'camisa-rotos chaleco-corto pantalon-recto mocasines faja-huasa', { A: 'rojo-cartamo azul-ultramar lana-parda', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'largo-liso corto', barbas: 'ninguna', tocados: 'turbante', pieles: OSCURA }),
  A('cherokee-mujer', 'Cheroqui (siglo XIX, mujer)', 'norteamerica', [1790, 1900], 'f', 'media', 'vestido-calico chal-lana mocasines collar-cuentas', { A: 'rojo-cartamo azul-ultramar verde-hoja', B: BL, C: 'lana-negra', D: 'turquesa' }, { peinados: 'recogido trenzas-dos', tocados: 'panuelo ninguno', pieles: OSCURA }),
  // ===== México regional =====
  A('zapoteca-hombre', 'Zapoteco del Istmo', 'mexico-regional', [1800, 1950], 'm', 'baja', 'camisa-blanca pantalon-recto faja-huasa sandalias-cuero', { A: BL, B: 'rojo-cartamo', C: 'rojo-cartamo', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha', pieles: OSCURA }),
  A('tehuana-mujer', 'Tehuana con resplandor', 'mexico-regional', [1800, 1950], 'f', 'media', 'huipil-tehuano enagua-tehuana collar-ancho-cuentas descalzo', { A: 'rojo-cartamo azul-ultramar verde-esmeralda azafran', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'trenzas-dos recogido', tocados: 'resplandor ninguno', pieles: OSCURA }),
  A('purepecha-hombre', 'Purépecha', 'mexico-regional', [1800, 1950], 'm', 'baja', 'camisa-blanca pantalon-recto sarape sandalias-cuero', { A: BL, B: 'lana-negra', C: 'rojo-cartamo', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha', pieles: OSCURA }),
  A('purepecha-mujer', 'Purépecha con rebozo y delantal bordado', 'mexico-regional', [1800, 1950], 'f', 'media', 'blusa-regional falda-larga delantal-bordado rebozo sandalias-cuero', { A: 'indigo lana-negra verde-botella', B: BL, C: 'rojo-cartamo', D: 'plata' }, { peinados: 'trenzas-dos', tocados: 'ninguno', pieles: OSCURA }),
  A('raramuri-hombre', 'Rarámuri con tagora', 'mexico-regional', [1700, 1950], 'm', 'baja', 'camisa-raramuri maxtlatl sandalias-cuero', { A: 'blanco-lino azul-celeste rojo-cartamo', B: BL, C: 'rojo-cartamo', D: 'plata' }, { peinados: 'largo-liso corto', barbas: 'ninguna', tocados: 'diadema', pieles: OSCURA }),
  A('raramuri-mujer', 'Rarámuri con falda de colores', 'mexico-regional', [1700, 1950], 'f', 'baja', 'camisa-raramuri falda-raramuri collar-ancho-cuentas sandalias-cuero', { A: 'rojo-cartamo azul-celeste verde-hoja azafran', B: 'azafran', C: 'rosa-cochinilla', D: 'turquesa' }, { peinados: 'largo-liso trenzas-dos', tocados: 'panuelo ninguno', pieles: OSCURA }),
  A('wixarika-hombre', 'Wixárika con camisa bordada', 'mexico-regional', [1700, 1950], 'm', 'media', 'camisa-wixarika pantalon-wixarika faja-huasa sandalias-cuero', { A: BL, B: 'rojo-cartamo', C: 'rojo-cartamo', D: 'azul-ultramar' }, { peinados: 'largo-liso', barbas: 'ninguna', tocados: 'sombrero-ala-ancha', pieles: OSCURA }),
  A('wixarika-mujer', 'Wixárika con blusa y falda bordadas', 'mexico-regional', [1700, 1950], 'f', 'media', 'camisa-wixarika falda-raramuri collar-ancho-cuentas sandalias-cuero', { A: 'blanco-lino azul-ultramar rojo-cartamo', B: 'rojo-cartamo', C: 'rojo-cartamo', D: 'azul-ultramar' }, { peinados: 'trenzas-dos largo-liso', tocados: 'ninguno', pieles: OSCURA }),
  A('charro-mexicano', 'Charro mexicano', 'mexico-regional', [1850, 1950], 'm', 'alta', 'camisa-blanca lazo-cuello chaqueta-charra pantalon-charro botas-cuero faja-huasa', { A: 'lana-negra borravino gris-pizarra azul-prusia', B: BL, C: 'plata', D: 'plata' }, { peinados: 'corto', barbas: 'bigote', tocados: 'sombrero-ala-ancha', pieles: 'clara-calida oliva-clara oliva canela' }),
  A('mexicana-mestiza-1900', 'Mexicana mestiza de 1900', 'mexico-regional', [1850, 1950], 'f', 'media', 'blusa-regional falda-larga rebozo zapatos-cuero collar-colgante', { A: 'rojo-granza azul-prusia verde-hoja lana-parda', B: BL, C: BL, D: 'plata' }, { peinados: 'trenzas-dos recogido', tocados: 'ninguno', pieles: 'clara-calida oliva-clara oliva canela' }),
  // ===== Andes: culturas prehispánicas del norte =====
  A('moche-noble', 'Noble moche', 'andes', [100, 800], 'm', 'alta', 'unku chumpi sandalias-cuero brazaletes-altos collar-usej', { A: 'rojo-cochinilla azul-andino crudo', B: 'azafran crudo', C: 'oro', D: 'oro' }, { peinados: 'largo-liso flequillo', tocados: 'tocado-plumas diadema', pieles: OSCURA, conf: 'baja' }),
  A('moche-mujer', 'Mujer moche', 'andes', [100, 800], 'f', 'media', 'aqsu chumpi lliclla sandalias-cuero collar-cuentas', { A: 'rojo-cochinilla azul-andino crudo lana-parda', B: 'azafran', C: 'azafran', D: 'oro' }, { peinados: 'trenzas-dos largo-liso', tocados: 'diadema ninguno', pieles: OSCURA, conf: 'baja' }),
  // ===== Chile y la Patagonia: más pueblos y oficios =====
  A('tehuelche-hombre', 'Tehuelche (aonikenk)', 'patagonia-fueguina', [1500, 1930], 'm', 'media', 'quillango pantalon-piel botas-piel', { A: 'piel-guanaco cuero-claro', B: 'cuero-oscuro', C: 'rojo-ocre', D: 'rojo-ocre' }, { peinados: 'largo-liso', barbas: 'ninguna', tocados: 'diadema ninguno', pieles: OSCURA }),
  A('tehuelche-mujer', 'Tehuelche (mujer)', 'patagonia-fueguina', [1500, 1930], 'f', 'media', 'quillango botas-piel cuentas-largas', { A: 'piel-guanaco cuero-claro', B: 'cuero-oscuro', C: 'rojo-ocre', D: 'rojo-ocre' }, { peinados: 'trenzas-dos largo-liso', tocados: 'diadema ninguno', pieles: OSCURA }),
  A('kawesqar-hombre', 'Kawésqar canoero', 'patagonia-fueguina', [-5000, 1950], 'm', 'baja', 'capa-canguro taparrabo-cuero cuentas-largas descalzo', { A: 'piel-nutria cuero-oscuro', B: 'cuero', C: 'rojo-ocre', D: 'crudo' }, { peinados: 'largo-liso corto', barbas: 'ninguna', tocados: 'ninguno', pieles: OSCURA, conf: 'baja', nota: 'Pueblo canoero que usaba poca ropa: la capa de piel es una simplificación.' }),
  A('kawesqar-mujer', 'Kawésqar (mujer)', 'patagonia-fueguina', [-5000, 1950], 'f', 'baja', 'capa-canguro cuentas-largas descalzo', { A: 'piel-nutria cuero-oscuro', B: 'cuero', C: 'rojo-ocre', D: 'crudo' }, { peinados: 'largo-liso', tocados: 'ninguno', pieles: OSCURA, conf: 'baja', nota: 'Pueblo canoero que usaba poca ropa: la capa de piel es una simplificación.' }),
  A('minero-salitrero', 'Minero del salitre', 'chile-rural', [1880, 1930], 'm', 'baja', 'camisa-rotos chaleco-corto pantalon-recto lazo-cuello botas-cuero faja-huasa', { A: 'kaki lana-parda gris-pizarra umbra', B: 'crudo', C: 'rojo-granza', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote barba-corta ninguna', tocados: 'sombrero-ala-ancha gorra-plana', pieles: 'clara-calida oliva-clara oliva canela bronce' }),
  A('campesina-chilena-1900', 'Campesina chilena de 1900', 'chile-rural', [1850, 1950], 'f', 'baja', 'blusa-campesina falda-larga delantal-blanco chal-lana zapatos-cuero', { A: 'azul-plomo rojo-granza verde-musgo lana-parda', B: BL, C: BL, D: 'cobre' }, { peinados: 'trenzas-dos recogido', tocados: 'panuelo ninguno', pieles: 'clara-calida oliva-clara oliva canela' }),
  A('paisana-rioplatense', 'Paisana rioplatense', 'rio-de-la-plata', [1750, 1950], 'f', 'baja', 'blusa-campesina falda-larga chal-lana delantal-blanco zapatos-cuero', { A: 'azul-plomo rojo-granza lana-parda verde-musgo', B: BL, C: BL, D: 'cobre' }, { peinados: 'trenzas-dos recogido', tocados: 'panuelo ninguno', pieles: 'clara-calida oliva-clara oliva canela' }),
  A('dama-rioplatense-1900', 'Dama rioplatense de 1900', 'rio-de-la-plata', [1860, 1930], 'f', 'alta', 'vestido-victoriano collar-colgante zapatos-cuero chal-corto', { A: 'borravino verde-botella azul-prusia lana-negra', B: BL, C: 'lana-negra', D: 'oro' }, { peinados: 'recogido', tocados: 'velo ninguno', pieles: 'porcelana clara clara-calida' }),
  // ===== Oceanía =====
  A('maori-hombre', 'Maorí con korowai', 'oceania', [1200, 1900], 'm', 'alta', 'piupiu korowai collar-colgante descalzo', { A: 'lana-negra crudo piel-guanaco', B: 'rojo-ocre', C: 'rojo-ocre', D: 'verde-jade' }, { peinados: 'mono-alto largo-liso', barbas: 'ninguna', tocados: 'ninguno', pieles: OSCURA, conf: 'media', nota: 'No incluye el tā moko (tatuaje facial y corporal), que es personal y tiene significado propio.' }),
  A('maori-mujer', 'Maorí con korowai', 'oceania', [1200, 1900], 'f', 'alta', 'piupiu korowai hei-tiki descalzo', { A: 'lana-negra crudo piel-guanaco', B: 'rojo-ocre', C: 'rojo-ocre', D: 'verde-jade' }, { peinados: 'largo-liso trenza-una', tocados: 'ninguno', pieles: OSCURA, conf: 'media', nota: 'No incluye el tā moko (tatuaje facial y corporal), que es personal y tiene significado propio.' }),
  A('aborigen-hombre', 'Aborigen australiano (representación genérica)', 'oceania', [-5000, 1900], 'm', 'baja', 'capa-canguro cinturon-cuerda collar-cuentas descalzo', { A: 'piel-guanaco cuero-claro', B: 'cuero', C: 'rojo-ocre', D: 'crudo' }, { peinados: 'rizos largo-ondulado', barbas: 'barba-completa barba-corta', tocados: 'diadema ninguno', pieles: 'castana ebano-claro ebano', conf: 'baja', nota: 'Cientos de pueblos con culturas muy distintas: es una imagen genérica, no la de un pueblo concreto.' }),
  A('aborigen-mujer', 'Aborigen australiana (representación genérica)', 'oceania', [-5000, 1900], 'f', 'baja', 'capa-canguro cinturon-cuerda collar-cuentas descalzo', { A: 'piel-guanaco cuero-claro', B: 'cuero', C: 'rojo-ocre', D: 'crudo' }, { peinados: 'rizos largo-ondulado', tocados: 'diadema ninguno', pieles: 'castana ebano-claro ebano', conf: 'baja', nota: 'Cientos de pueblos con culturas muy distintas: es una imagen genérica, no la de un pueblo concreto.' }),
  A('papu-hombre', 'Papú (representación genérica)', 'oceania', [-3000, 1950], 'm', 'baja', 'falda-rafia collar-ancho-cuentas brazaletes-altos descalzo', { A: 'crudo barro rojo-ocre', B: 'rojo-ocre', C: 'rojo-ocre', D: 'naranja-achiote' }, { peinados: 'rizos rapado', barbas: 'barba-corta ninguna', tocados: 'tocado-plumas ninguno', pieles: 'ebano-claro ebano ebano-profundo', conf: 'baja', nota: 'Más de mil lenguas y culturas: es una imagen genérica de Melanesia.' }),
  A('papu-mujer', 'Papú (representación genérica)', 'oceania', [-3000, 1950], 'f', 'baja', 'falda-rafia collar-ancho-cuentas brazaletes-altos descalzo', { A: 'crudo barro rojo-ocre', B: 'rojo-ocre', C: 'rojo-ocre', D: 'naranja-achiote' }, { peinados: 'rizos rapado', tocados: 'tocado-plumas ninguno', pieles: 'ebano-claro ebano ebano-profundo', conf: 'baja', nota: 'Más de mil lenguas y culturas: es una imagen genérica de Melanesia.' }),
  A('hawaiano-hombre', 'Hawaiano con ahu ula', 'polinesia', [1200, 1850], 'm', 'alta', 'malo ahu-ula guirnalda descalzo', { A: 'azafran rojo-cartamo crudo', B: 'rojo-cartamo', C: 'azafran', D: 'oro' }, { peinados: 'corto rizos', barbas: 'bigote ninguna', tocados: 'tocado-plumas ninguno', pieles: OSCURA }),
  A('hawaiana-mujer', 'Hawaiana con pa\'u y lei', 'polinesia', [1200, 1900], 'f', 'media', 'camisa-hawaiana-antigua pau guirnalda descalzo', { A: 'crudo barro rojo-ocre', B: 'rojo-ocre', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'largo-ondulado largo-liso', tocados: 'corona-flores ninguno', pieles: OSCURA }),
  A('samoano-hombre', 'Samoano con lavalava', 'polinesia', [1000, 1950], 'm', 'media', 'lava-lava taovala guirnalda descalzo', { A: 'crudo barro rojo-ocre azul-celeste', B: 'rojo-ocre', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'corto mono-alto', barbas: 'ninguna', tocados: 'ninguno', pieles: OSCURA }),
  A('samoana-mujer', 'Samoana con puletasi', 'polinesia', [1000, 1950], 'f', 'media', 'blusa-campesina lava-lava guirnalda descalzo', { A: 'blanco-lino rojo-cartamo azul-celeste azafran', B: 'rojo-ocre', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'largo-ondulado recogido', tocados: 'corona-flores ninguno', pieles: OSCURA }),
  A('fiyiano-hombre', 'Fiyiano con sulu', 'polinesia', [1000, 1950], 'm', 'media', 'sulu camisa-rotos guirnalda descalzo', { A: 'blanco-lino lana-parda azul-prusia', B: 'rojo-ocre', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'rizos corto', barbas: 'ninguna bigote', tocados: 'ninguno', pieles: 'castana ebano-claro ebano', conf: 'baja' }),
  A('fiyiana-mujer', 'Fiyiana con sulu', 'polinesia', [1000, 1950], 'f', 'media', 'blusa-campesina sulu guirnalda descalzo', { A: 'blanco-lino rojo-cartamo azul-celeste', B: 'rojo-ocre', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'rizos trenzas-pegadas', tocados: 'corona-flores ninguno', pieles: 'castana ebano-claro ebano', conf: 'baja' }),
];

Object.assign(__e, { ATUENDOS_AMPLIADOS });
return __e;
})();

__m['datos/atuendos-siglo20'] = (() => {
const __e = {};
// Atuendos de 1850 a 1980 (la ropa de abuelos y bisabuelos) y variantes históricas de oficios. Misma forma que
// atuendos.js. Los colores de casi todas las prendas modernas vienen de su propio perfil (colores-prendas.js); la
// paleta de cada atuendo pesa sobre ellos (los tintes que ya usa salen 2,5 veces más) y manda en las prendas sin perfil.

const { A, BL, CLAROS, MEST, OSCURA, EBANO, CIUDAD, TRAB, VEST } = __m['datos/ayuda'];


const ATUENDOS_SIGLO20 = [
  // ===== Chile, 1850 a 1980 =====
  A('huaso-fiesta', 'Huaso de fiesta con chamanto', 'chile-rural', [1850, 1970], 'm', 'alta', 'camisa-blanca chaqueta-huasa chamanto-fiesta pantalon-vestir faja-huasa botas-altas', { A: 'rojo-granza azul-andino lana-negra', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', tocados: 'chupalla', pieles: MEST }),
  A('huasa-cueca', 'Huasa de cueca', 'chile-rural', [1850, 1970], 'f', 'media', 'blusa-regional pollera-huacha chal-seda zapatos-vestir collar-cuentas', { A: 'rojo-granza azul-prusia verde-hoja', B: BL, C: 'blanco-lino', D: 'plata' }, { peinados: 'trenzas-dos recogido', tocados: 'chupalla ninguno', pieles: MEST }),
  A('chile-obrero-1920', 'Obrero chileno de 1920', 'chile-rural', [1900, 1960], 'm', 'baja', 'camisa-trabajo chaleco-lana pantalon-trabajo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana boina', pieles: MEST }),
  A('chile-obrera-1940', 'Obrera chilena de 1940', 'chile-rural', [1920, 1965], 'f', 'baja', 'vestido-casa cardigan delantal-blanco zapatos-cuero', VEST, { peinados: 'recogido ondas', tocados: 'panuelo ninguno', pieles: MEST }),
  A('chile-oficinista-1930', 'Oficinista chileno de 1930', 'chile-rural', [1910, 1965], 'm', 'media', 'camisa-blanca corbata saco-terno pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'fedora ninguno', pieles: MEST }),
  A('chile-dama-1930', 'Dama chilena de 1930', 'chile-rural', [1920, 1945], 'f', 'media', 'vestido-30 estola perlas zapatos-vestir', VEST, { peinados: 'ondas bob', tocados: 'cloche ninguno', pieles: MEST }),
  A('chile-dama-1955', 'Dama chilena de 1955', 'chile-rural', [1945, 1968], 'f', 'media', 'vestido-50 cardigan perlas zapatos-vestir', VEST, { peinados: 'ondas recogido', pieles: MEST }),
  A('chile-colegial-m', 'Colegial chileno', 'chile-rural', [1920, 1975], 'm', 'media', 'camisa-blanca corbata saco-terno pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya corto', tocados: 'ninguno', pieles: MEST }),
  A('chile-colegiala', 'Colegiala chilena', 'chile-rural', [1920, 1975], 'f', 'media', 'blusa-cuello-bebe falda-colegial cardigan zapatos-vestir', VEST, { peinados: 'trenzas-dos bob', pieles: MEST }),
  A('chile-minero-carbon', 'Minero del carbón (Lota y Coronel)', 'chile-rural', [1930, 1980], 'm', 'baja', 'camisa-trabajo pantalon-trabajo botas-goma', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'casco-obra', pieles: MEST }),
  A('chile-pescador', 'Pescador de la costa', 'chile-rural', [1880, 1980], 'm', 'baja', 'sueter-lana pantalon-trabajo botas-goma', TRAB, { peinados: 'corto', barbas: 'bigote barba-corta ninguna', tocados: 'gorro-lana gorra-visera', pieles: MEST }),
  A('chile-campesino-1950', 'Campesino chileno de 1950', 'chile-rural', [1930, 1975], 'm', 'baja', 'camisa-cuadros chaleco-lana pantalon-trabajo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'chupalla boina', pieles: MEST }),
  A('chile-campesina-1950', 'Campesina chilena de 1950', 'chile-rural', [1930, 1975], 'f', 'baja', 'vestido-casa delantal-blanco chal-lana zapatos-cuero', VEST, { peinados: 'trenzas-dos recogido', tocados: 'panuelo ninguno', pieles: MEST }),
  A('chile-carabinero', 'Carabinero', 'chile-rural', [1927, 1980], 'm', 'media', 'camisa-blanca chaqueta-militar pantalon-militar botas-militares cinturon-cuero', { A: 'verde-militar verde-oliva-oscuro', B: BL, C: 'cuero-oscuro', D: 'oro' }, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', tocados: 'kepi', pieles: MEST }),
  A('chile-novia-1940', 'Novia chilena de 1940', 'chile-rural', [1930, 1970], 'f', 'media', 'vestido-novia perlas zapatos-vestir', { A: 'blanco-optico marfil', B: 'crema', C: 'marfil', D: 'plata' }, { peinados: 'ondas recogido', tocados: 'velo', pieles: MEST }),
  A('chile-novio-1940', 'Novio chileno de 1940', 'chile-rural', [1930, 1970], 'm', 'media', 'camisa-blanca corbata saco-terno pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', pieles: MEST }),
  A('chile-viuda-1900', 'Viuda chilena de luto', 'chile-rural', [1860, 1950], 'f', 'media', 'vestido-luto mantilla zapatos-cuero', { A: 'negro-carbon lana-negra', B: 'lana-negra', C: 'lana-negra', D: 'plata' }, { peinados: 'recogido', tocados: 'velo', pieles: MEST }),
  A('chile-abuelo-1960', 'Abuelo chileno de 1960', 'chile-rural', [1950, 1985], 'm', 'baja', 'camisa-manga-corta chaleco-lana pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', tocados: 'boina fedora', pieles: MEST }),
  A('chile-abuela-1960', 'Abuela chilena de 1960', 'chile-rural', [1950, 1985], 'f', 'baja', 'vestido-casa cardigan zapatos-vestir perlas', VEST, { peinados: 'recogido ondas', pieles: MEST }),
  // ===== Latinoamérica, 1850 a 1980 =====
  A('llanero-venezolano', 'Llanero con liquiliqui', 'latinoamerica-moderna', [1850, 1980], 'm', 'media', 'liquiliqui pantalon-vestir alpargatas', { A: 'blanco-lino crema', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha', pieles: MEST }),
  A('llanera', 'Llanera', 'latinoamerica-moderna', [1850, 1980], 'f', 'media', 'blusa-regional falda-volantes alpargatas collar-cuentas', { A: 'rojo-granza azul-real verde-bosque', B: BL, C: 'blanco-lino', D: 'oro' }, { peinados: 'trenzas-dos recogido', tocados: 'ninguno', pieles: MEST }),
  A('paisa-hombre', 'Paisa antioqueño', 'latinoamerica-moderna', [1850, 1980], 'm', 'baja', 'camisa-manta pantalon-trabajo ruana alpargatas bolso-cuero', { A: 'lana-parda umbra', B: BL, C: 'lana-negra', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha', pieles: MEST }),
  A('paisa-mujer', 'Paisa antioqueña', 'latinoamerica-moderna', [1850, 1980], 'f', 'baja', 'blusa-regional falda-larga delantal-blanco rebozo alpargatas', { A: 'rojo-granza azul-prusia lana-parda', B: BL, C: 'blanco-lino', D: 'cobre' }, { peinados: 'trenzas-dos recogido', tocados: 'panuelo ninguno', pieles: MEST }),
  A('cubano-guayabera', 'Cubano con guayabera', 'latinoamerica-moderna', [1900, 1980], 'm', 'media', 'guayabera pantalon-vestir zapatos-vestir', { A: 'blanco-lino crema', B: BL, C: 'blanco-lino', D: 'plata' }, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha fedora', pieles: MEST + ' ' + OSCURA }),
  A('cubana-1950', 'Cubana de 1950', 'latinoamerica-moderna', [1940, 1965], 'f', 'media', 'vestido-50 perlas zapatos-vestir', VEST, { peinados: 'ondas recogido', pieles: MEST + ' ' + OSCURA }),
  A('jibaro-puertorriqueno', 'Jíbaro puertorriqueño', 'latinoamerica-moderna', [1850, 1960], 'm', 'baja', 'camisa-manta calzon-manta alpargatas', { A: 'crudo algodon-crudo', B: BL, C: 'rojo-granza', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha', pieles: MEST + ' ' + OSCURA }),
  A('caipira-brasileno', 'Caipira brasileño', 'latinoamerica-moderna', [1850, 1970], 'm', 'baja', 'camisa-cuadros pantalon-trabajo alpargatas', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha', pieles: MEST + ' ' + OSCURA }),
  A('baiana', 'Baiana', 'latinoamerica-moderna', [1850, 1980], 'f', 'media', 'blusa-regional falda-volantes chal-seda collar-ancho-cuentas sandalias-cuero', { A: 'blanco-lino crudo', B: BL, C: 'rojo-cartamo azul-ultramar', D: 'oro' }, { peinados: 'recogido', tocados: 'turbante', pieles: EBANO }),
  A('tanguero-1930', 'Tanguero de 1930', 'latinoamerica-moderna', [1910, 1960], 'm', 'media', 'camisa-blanca corbata chaleco-terno saco-terno pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote', tocados: 'fedora', pieles: MEST }),
  A('tanguera-1930', 'Tanguera de 1930', 'latinoamerica-moderna', [1910, 1960], 'f', 'media', 'vestido-30 perlas zapatos-vestir estola', VEST, { peinados: 'ondas bob', tocados: 'cloche ninguno', pieles: MEST }),
  A('inmigrante-1910', 'Inmigrante recién llegado (1910)', 'latinoamerica-moderna', [1880, 1930], 'm', 'baja', 'camisa-blanca chaleco-lana pantalon-vestir zapatos-cuero', CIUDAD, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'boina gorra-plana', pieles: 'porcelana clara clara-calida oliva-clara' }),
  A('obrero-latam-1940', 'Obrero latinoamericano de 1940', 'latinoamerica-moderna', [1920, 1970], 'm', 'baja', 'camisa-manga-corta pantalon-trabajo zapatos-cuero', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana ninguno', pieles: MEST + ' ' + OSCURA }),
  A('mujer-ciudad-latam-1950', 'Mujer de ciudad de 1950', 'latinoamerica-moderna', [1940, 1975], 'f', 'media', 'blusa-cuello-bebe falda-lapiz cardigan perlas zapatos-vestir', VEST, { peinados: 'ondas bob', pieles: MEST + ' ' + OSCURA }),
  A('minero-boliviano', 'Minero boliviano', 'andes', [1900, 1985], 'm', 'baja', 'camisa-trabajo pantalon-trabajo botas-goma', TRAB, { peinados: 'corto', barbas: 'ninguna', tocados: 'casco-obra', pieles: OSCURA }),
  A('chola-pacena-1950', 'Chola paceña de 1950', 'andes', [1900, 1985], 'f', 'media', 'blusa-regional pollera chal-seda zapatos-vestir collar-colgante', { A: 'azul-prusia borravino verde-botella lana-negra', B: BL, C: 'blanco-lino', D: 'oro' }, { peinados: 'trenzas-dos', tocados: 'sombrero-bombin-andino', pieles: OSCURA }),
  // ===== México, 1850 a 1980 =====
  A('mexicano-peon', 'Peón mexicano', 'mexico-regional', [1850, 1950], 'm', 'baja', 'camisa-manta calzon-manta sarape sandalias-cuero', { A: 'crudo algodon-crudo', B: 'lana-negra', C: 'rojo-granza', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha', pieles: OSCURA }),
  A('mexicana-adelita', 'Adelita de la Revolución', 'mexico-regional', [1910, 1920], 'f', 'baja', 'blusa-regional falda-larga rebozo cinturon-cuero zapatos-cuero', { A: 'lana-parda azul-plomo rojo-granza', B: BL, C: 'lana-negra', D: 'cobre' }, { peinados: 'trenzas-dos', tocados: 'sombrero-ala-ancha', pieles: OSCURA }),
  A('mexicano-revolucionario', 'Soldado de la Revolución mexicana', 'mexico-regional', [1910, 1920], 'm', 'baja', 'camisa-trabajo pantalon-trabajo sarape cinturon-vaquero botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote', tocados: 'sombrero-ala-ancha', pieles: MEST + ' ' + OSCURA }),
  A('mexicana-china-poblana', 'China poblana', 'mexico-regional', [1830, 1950], 'f', 'media', 'blusa-regional falda-volantes rebozo zapatos-cuero collar-cuentas', { A: 'rojo-cartamo verde-hoja azul-ultramar', B: BL, C: 'oro', D: 'oro' }, { peinados: 'trenzas-dos', tocados: 'ninguno', pieles: MEST }),
  // ===== Estados Unidos y Canadá, 1840 a 1980 =====
  A('eeuu-vaquero', 'Vaquero del oeste', 'norteamerica-moderna', [1865, 1960], 'm', 'baja', 'camisa-cuadros pantalon-vaquero chaqueta-denim cinturon-vaquero botas-vaqueras panuelo-cuello', TRAB, { peinados: 'corto', barbas: 'bigote barba-corta ninguna', tocados: 'sombrero-ala-ancha', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-vaquera', 'Mujer del oeste', 'norteamerica-moderna', [1865, 1960], 'f', 'baja', 'camisa-cuadros falda-larga cinturon-fino botas-vaqueras', TRAB, { peinados: 'trenza-una recogido', tocados: 'sombrero-ala-ancha panuelo', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-pionera', 'Pionera', 'norteamerica-moderna', [1840, 1895], 'f', 'baja', 'vestido-calico delantal-blanco chal-lana botas-trabajo', { A: 'azul-plomo rojo-granza verde-musgo', B: BL, C: BL, D: 'cobre' }, { peinados: 'recogido', tocados: 'panuelo', pieles: CLAROS }),
  A('eeuu-colono', 'Colono', 'norteamerica-moderna', [1840, 1895], 'm', 'baja', 'camisa-trabajo chaleco-lana pantalon-trabajo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'barba-completa barba-corta', tocados: 'sombrero-ala-ancha', pieles: CLAROS }),
  A('eeuu-granjero-1930', 'Granjero de 1930', 'norteamerica-moderna', [1900, 1960], 'm', 'baja', 'camisa-trabajo pantalon-overol overol-peto botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-visera sombrero-ala-ancha', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-granjera-1930', 'Granjera de 1930', 'norteamerica-moderna', [1900, 1960], 'f', 'baja', 'vestido-casa delantal-cocina zapatos-cuero', VEST, { peinados: 'recogido', tocados: 'panuelo', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-obrero-1920', 'Obrero de 1920', 'norteamerica-moderna', [1900, 1950], 'm', 'baja', 'camisa-trabajo chaleco-lana pantalon-trabajo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-flapper', 'Joven de los años veinte', 'norteamerica-moderna', [1920, 1930], 'f', 'media', 'vestido-flapper perlas zapatos-vestir', VEST, { peinados: 'bob ondas', tocados: 'cloche', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-ejecutivo-1950', 'Ejecutivo de 1950', 'norteamerica-moderna', [1940, 1970], 'm', 'media', 'camisa-blanca corbata saco-terno pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'ninguna', tocados: 'fedora ninguno', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-ama-de-casa-1955', 'Ama de casa de 1955', 'norteamerica-moderna', [1945, 1965], 'f', 'media', 'vestido-50 delantal-cocina perlas zapatos-vestir', VEST, { peinados: 'ondas', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-soldado-1944', 'Soldado de 1944', 'norteamerica-moderna', [1939, 1950], 'm', 'baja', 'camisa-blanca chaqueta-militar pantalon-militar botas-militares cinturon-cuero', { A: 'verde-militar caqui-militar', B: BL, C: 'cuero-oscuro', D: 'bronce' }, { peinados: 'corto rapado', barbas: 'ninguna', tocados: 'kepi gorra-visera', pieles: CLAROS + ' clara-calida ' + OSCURA }),
  A('eeuu-enfermera-1944', 'Enfermera de 1944', 'norteamerica-moderna', [1939, 1960], 'f', 'media', 'guardapolvo zapatos-vestir', { A: 'blanco-optico', B: BL, C: 'azul-marino', D: 'plata' }, { peinados: 'ondas recogido', tocados: 'panuelo', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-estudiante-1955', 'Estudiante de 1955', 'norteamerica-moderna', [1945, 1965], 'm', 'media', 'polera-rayas cardigan pantalon-vestir zapatillas-lona', CIUDAD, { peinados: 'corto-raya', tocados: 'ninguno', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-adolescente-1955', 'Adolescente de 1955', 'norteamerica-moderna', [1945, 1965], 'f', 'media', 'blusa-cuello-bebe falda-campana cardigan zapatillas-lona', VEST, { peinados: 'ondas trenza-una', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-jeans-1965', 'Joven con jeans (1965)', 'norteamerica-moderna', [1955, 1980], 'm', 'baja', 'polera chaqueta-denim pantalon-vaquero zapatillas-lona', TRAB, { peinados: 'corto', barbas: 'ninguna', pieles: CLAROS + ' clara-calida ' + OSCURA }),
  A('eeuu-jeans-1965-f', 'Joven con jeans (1965, mujer)', 'norteamerica-moderna', [1955, 1980], 'f', 'baja', 'blusa-cuello-bebe pantalon-vaquero cardigan zapatillas-lona', VEST, { peinados: 'bob largo-liso', pieles: CLAROS + ' clara-calida ' + OSCURA }),
  A('eeuu-domingo-afroamericano', 'Afroamericano de domingo (1940)', 'norteamerica-moderna', [1900, 1965], 'm', 'media', 'camisa-blanca corbata saco-terno pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto rapado', barbas: 'bigote ninguna', tocados: 'fedora', pieles: EBANO }),
  A('eeuu-domingo-afroamericana', 'Afroamericana de domingo (1940)', 'norteamerica-moderna', [1900, 1965], 'f', 'media', 'vestido-40 perlas zapatos-vestir estola', VEST, { peinados: 'ondas recogido', tocados: 'cloche', pieles: EBANO }),
  // ===== Europa, 1900 a 1975 =====
  A('xx-obrero-fabrica', 'Obrero de fábrica', 'europa-xix', [1900, 1970], 'm', 'baja', 'camisa-trabajo pantalon-trabajo delantal-obrero botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana boina', pieles: CLAROS }),
  A('xx-obrera-1920', 'Obrera de 1920', 'europa-xix', [1900, 1950], 'f', 'baja', 'blusa-cuello-bebe falda-larga delantal-blanco zapatos-cuero', VEST, { peinados: 'recogido', tocados: 'panuelo ninguno', pieles: CLAROS }),
  A('xx-minero-carbon', 'Minero del carbón europeo', 'europa-xix', [1880, 1980], 'm', 'baja', 'camisa-trabajo pantalon-trabajo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana casco-obra', pieles: CLAROS }),
  A('xx-soldado-1914', 'Soldado de 1914', 'europa-xix', [1910, 1920], 'm', 'baja', 'camisa-blanca chaqueta-militar pantalon-golf botas-militares cinturon-cuero', { A: 'verde-militar caqui-militar azul-militar', B: BL, C: 'cuero-oscuro', D: 'bronce' }, { peinados: 'corto rapado', barbas: 'bigote ninguna', tocados: 'kepi', pieles: CLAROS }),
  A('xx-soldado-1940', 'Soldado de 1940', 'europa-xix', [1935, 1950], 'm', 'baja', 'camisa-blanca chaqueta-militar pantalon-militar botas-militares cinturon-cuero', { A: 'verde-militar caqui-militar azul-militar gris-oscuro', B: BL, C: 'cuero-oscuro', D: 'bronce' }, { peinados: 'corto rapado', barbas: 'ninguna', tocados: 'gorra-visera kepi', pieles: CLAROS }),
  A('xx-dama-1905', 'Dama de 1905', 'europa-xix', [1895, 1914], 'f', 'alta', 'vestido-victoriano perlas zapatos-cuero', { A: 'verde-botella borravino azul-prusia lana-negra', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'recogido', tocados: 'velo ninguno', pieles: CLAROS }),
  A('xx-caballero-1925', 'Caballero de 1925', 'europa-xix', [1915, 1940], 'm', 'media', 'camisa-blanca corbata chaleco-terno saco-terno pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'fedora bombin', pieles: CLAROS }),
  A('xx-flapper', 'Joven de los años veinte (Europa)', 'europa-xix', [1920, 1930], 'f', 'media', 'vestido-flapper perlas zapatos-vestir', VEST, { peinados: 'bob ondas', tocados: 'cloche', pieles: CLAROS }),
  A('xx-dama-1938', 'Dama de 1938', 'europa-xix', [1930, 1945], 'f', 'media', 'vestido-30 estola zapatos-vestir perlas', VEST, { peinados: 'ondas bob', tocados: 'cloche ninguno', pieles: CLAROS }),
  A('xx-mujer-guerra-1942', 'Mujer en tiempos de guerra (1942)', 'europa-xix', [1938, 1948], 'f', 'baja', 'vestido-40 cardigan zapatos-cuero', VEST, { peinados: 'ondas recogido', tocados: 'panuelo ninguno', pieles: CLAROS }),
  A('xx-dama-1957', 'Dama de 1957', 'europa-xix', [1948, 1965], 'f', 'media', 'vestido-50 perlas zapatos-vestir', VEST, { peinados: 'ondas', pieles: CLAROS }),
  A('xx-colegial', 'Colegial europeo', 'europa-xix', [1900, 1970], 'm', 'media', 'camisa-blanca corbata chaleco-lana short zapatos-vestir', CIUDAD, { peinados: 'corto-raya corto', tocados: 'gorra-visera ninguno', pieles: CLAROS }),
  A('xx-colegiala', 'Colegiala europea', 'europa-xix', [1900, 1970], 'f', 'media', 'blusa-cuello-bebe falda-colegial cardigan zapatos-vestir', VEST, { peinados: 'trenzas-dos bob', pieles: CLAROS }),
  A('xx-campesino-1930', 'Campesino europeo de 1930', 'europa-xix', [1900, 1960], 'm', 'baja', 'camisa-trabajo chaleco-lana pantalon-trabajo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'boina gorra-plana', pieles: CLAROS }),
  A('xx-campesina-1930', 'Campesina europea de 1930', 'europa-xix', [1900, 1960], 'f', 'baja', 'vestido-casa delantal-blanco chal-lana zuecos', VEST, { peinados: 'trenza-una recogido', tocados: 'panuelo', pieles: CLAROS }),
  A('xx-abuela-1960', 'Abuela europea de 1960', 'europa-xix', [1950, 1980], 'f', 'baja', 'vestido-casa cardigan perlas zapatos-cuero', VEST, { peinados: 'recogido ondas', tocados: 'panuelo ninguno', pieles: CLAROS }),
  A('xx-abuelo-1960', 'Abuelo europeo de 1960', 'europa-xix', [1950, 1980], 'm', 'baja', 'camisa-manga-corta chaleco-lana pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', tocados: 'boina gorra-plana', pieles: CLAROS }),
  A('xx-novia-1930', 'Novia de 1930', 'europa-xix', [1920, 1960], 'f', 'media', 'vestido-novia perlas zapatos-vestir', { A: 'blanco-optico marfil', B: 'crema', C: 'marfil', D: 'plata' }, { peinados: 'ondas recogido', tocados: 'velo', pieles: CLAROS }),
  A('xx-novio-1930', 'Novio de 1930', 'europa-xix', [1920, 1960], 'm', 'media', 'camisa-blanca corbata saco-terno pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'fedora ninguno', pieles: CLAROS }),
  A('xx-viuda', 'Viuda de luto', 'europa-xix', [1850, 1960], 'f', 'media', 'vestido-luto mantilla zapatos-cuero', { A: 'negro-carbon lana-negra', B: 'lana-negra', C: 'lana-negra', D: 'plata' }, { peinados: 'recogido', tocados: 'velo', pieles: CLAROS }),
  A('xx-medico-1930', 'Médico de 1930', 'europa-xix', [1910, 1960], 'm', 'alta', 'camisa-blanca corbata guardapolvo pantalon-vestir zapatos-vestir', { A: 'blanco-optico', B: BL, C: 'azul-marino', D: 'plata' }, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'ninguno', pieles: CLAROS }),
  A('xx-pescador-nordico', 'Pescador nórdico', 'europa-xix', [1880, 1980], 'm', 'baja', 'sueter-nordico pantalon-trabajo botas-goma', TRAB, { peinados: 'corto', barbas: 'barba-completa barba-corta ninguna', tocados: 'gorro-lana', pieles: CLAROS }),
  // Europa regional, siglo XX (con el pueblo en el id)
  A('italiano-obrero-1920', 'Obrero italiano de 1920', 'europa-campesina', [1900, 1960], 'm', 'baja', 'camisa-trabajo chaleco-lana pantalon-trabajo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana boina', pieles: 'clara-calida oliva-clara oliva' }),
  A('italiana-obrera-1920', 'Obrera italiana de 1920', 'europa-campesina', [1900, 1960], 'f', 'baja', 'blusa-cuello-bebe falda-larga delantal-blanco zapatos-cuero', VEST, { peinados: 'recogido', tocados: 'panuelo', pieles: 'clara-calida oliva-clara oliva' }),
  A('croata-obrero-1900', 'Obrero croata de 1900', 'europa-campesina', [1880, 1950], 'm', 'baja', 'camisa-trabajo chaleco-lana pantalon-trabajo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana', pieles: CLAROS }),
  A('croata-mujer-1920', 'Croata de 1920', 'europa-campesina', [1900, 1960], 'f', 'baja', 'vestido-casa cardigan delantal-blanco zapatos-cuero', VEST, { peinados: 'trenzas-dos recogido', tocados: 'panuelo', pieles: CLAROS }),
  A('gallego-emigrante-1930', 'Gallego emigrante (1930)', 'europa-campesina', [1900, 1960], 'm', 'baja', 'camisa-blanca chaleco-terno saco-terno pantalon-vestir zapatos-cuero', CIUDAD, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'boina', pieles: CLAROS }),
  A('vasco-pastor-1930', 'Pastor vasco (1930)', 'europa-campesina', [1900, 1960], 'm', 'baja', 'camisa-cuadros chaleco-lana pantalon-trabajo faja-vasca alpargatas', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'boina', pieles: CLAROS }),
  // ===== Asia, 1900 a 1970 =====
  A('china-1930-hombre', 'Hombre chino de 1930', 'asia-este', [1912, 1949], 'm', 'media', 'changshan zapatos-tela', { A: 'azul-marino gris-oscuro lana-negra', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'corto-raya corto', barbas: 'ninguna bigote', tocados: 'gorra-plana ninguno', pieles: 'clara clara-calida' }),
  A('china-1930-mujer', 'Mujer china de 1930 con qipao', 'asia-este', [1920, 1949], 'f', 'media', 'cheongsam estola zapatos-vestir', { A: 'rojo-vivo verde-jade azul-celeste malva azul-marino', B: BL, C: 'oro', D: 'oro' }, { peinados: 'bob ondas', pieles: 'porcelana clara clara-calida' }),
  A('china-1955-hombre', 'Hombre chino de 1955', 'asia-este', [1949, 1980], 'm', 'baja', 'chaqueta-zhongshan pantalon-trabajo zapatos-tela', { A: 'azul-marino gris-oscuro verde-militar', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'corto', barbas: 'ninguna', tocados: 'gorra-visera ninguno', pieles: 'clara clara-calida' }),
  A('china-1955-mujer', 'Mujer china de 1955', 'asia-este', [1949, 1980], 'f', 'baja', 'chaqueta-zhongshan pantalon-trabajo zapatos-tela', { A: 'azul-marino gris-oscuro verde-militar', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'trenzas-dos bob', tocados: 'ninguno', pieles: 'clara clara-calida' }),
  A('japon-colegiala-1950', 'Colegiala japonesa de 1950', 'asia-este', [1920, 1980], 'f', 'media', 'blusa-cuello-bebe falda-colegial cardigan zapatillas-lona', VEST, { peinados: 'bob trenzas-dos', pieles: 'porcelana clara clara-calida' }),
  A('japon-yukata-hombre', 'Hombre con yukata', 'asia-este', [1800, 1980], 'm', 'baja', 'yukata obi geta', { A: 'indigo azul-celeste gris-pizarra', B: BL, C: 'blanco-lino', D: 'plata' }, { peinados: 'corto', barbas: 'ninguna bigote', pieles: 'porcelana clara clara-calida' }),
  A('japon-yukata-mujer', 'Mujer con yukata', 'asia-este', [1800, 1980], 'f', 'baja', 'yukata obi geta', { A: 'indigo azul-celeste rosa-cochinilla malva', B: BL, C: 'rojo-cartamo', D: 'oro' }, { peinados: 'recogido mono-alto', pieles: 'porcelana clara clara-calida' }),
  A('japon-obrero-1940', 'Obrero japonés de 1940', 'asia-este', [1900, 1960], 'm', 'baja', 'happi pantalon-trabajo zapatos-tela', { A: 'indigo azul-marino gris-oscuro', B: BL, C: 'blanco-lino', D: 'cobre' }, { peinados: 'corto rapado', barbas: 'ninguna', tocados: 'gorra-visera ninguno', pieles: 'porcelana clara clara-calida' }),
  A('corea-1930-hombre', 'Hombre coreano con durumagi', 'asia-este', [1900, 1960], 'm', 'media', 'hanbok-baji durumagi zapatos-tela', { A: 'blanco-lino gris-claro azul-celeste', B: BL, C: 'indigo', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', pieles: 'porcelana clara clara-calida' }),
  A('india-1940-hombre', 'Hombre de la India de 1940', 'asia-sur-regional', [1900, 1980], 'm', 'media', 'kurta-corta chaqueta-nehru dhoti-sin-camisa zapatillas', { A: 'blanco-lino crudo', B: BL, C: 'azul-marino', D: 'plata' }, { peinados: 'corto-raya corto', barbas: 'bigote barba-corta ninguna', tocados: 'ninguno', pieles: 'oliva canela bronce' }),
  A('india-1940-mujer', 'Mujer de la India de 1940 con sari', 'asia-sur-regional', [1900, 1980], 'f', 'media', 'choli sari-falda sari-pallu brazaletes sandalias-cuero', { A: 'azul-real rosa-fucsia verde-bosque naranja-vivo burdeos', B: 'oro', C: 'oro', D: 'oro' }, { peinados: 'trenza-una recogido', pieles: 'oliva canela bronce' }),
  A('india-novia-lehenga', 'Novia india con lehenga', 'asia-sur-regional', [1500, 1980], 'f', 'alta', 'choli lehenga dupatta brazaletes-altos collar-colgante zapatillas', { A: 'rojo-vivo burdeos rojo-cartamo', B: 'oro', C: 'oro', D: 'oro' }, { peinados: 'trenza-una recogido', tocados: 'velo', pieles: 'oliva canela bronce' }),
  A('india-novio-sherwani', 'Novio indio con sherwani', 'asia-sur-regional', [1500, 1980], 'm', 'alta', 'sherwani-corta pyjama-indio zapatillas collar-colgante', { A: 'marfil crema azafran borravino', B: 'oro', C: 'oro', D: 'oro' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'turbante', pieles: 'oliva canela bronce' }),
  // ===== África, Oceanía y Medio Oriente, siglo XX =====
  A('africa-kitenge-mujer', 'Mujer con tela estampada africana', 'africa-oeste', [1900, 1980], 'f', 'media', 'kitenge-top kitenge-falda collar-ancho-cuentas sandalias-cuero', { A: 'azul-real rojo-vivo naranja-vivo verde-bosque amarillo-limon', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'trenzas-pegadas', tocados: 'gele panuelo', pieles: EBANO }),
  A('swahili-kikoi-hombre', 'Hombre suajili con kikoi', 'africa-este-sur', [1900, 1980], 'm', 'baja', 'camisa-manga-corta kikoi sandalias-cuero', { A: BL + ' azul-celeste', B: 'rojo-cartamo', C: 'rojo-cartamo', D: 'plata' }, { peinados: 'rapado corto', barbas: 'bigote ninguna', tocados: 'kufi', pieles: EBANO }),
  A('hawaiano-1950', 'Hawaiano de 1950', 'polinesia', [1920, 1975], 'm', 'baja', 'camisa-aloha pantalon-vestir zapatillas-lona guirnalda', { A: 'azul-real rojo-vivo', B: BL, C: 'blanco-optico', D: 'oro' }, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', pieles: OSCURA }),
  A('hawaiana-1950', 'Hawaiana de 1950', 'polinesia', [1920, 1975], 'f', 'baja', 'muumuu guirnalda sandalias-cuero', { A: 'rojo-vivo azul-real', B: BL, C: 'blanco-optico', D: 'oro' }, { peinados: 'largo-ondulado ondas', tocados: 'corona-flores', pieles: OSCURA }),
  A('arabe-urbano-1950', 'Hombre árabe de ciudad (1950)', 'medio-oriente', [1920, 1975], 'm', 'media', 'camisa-blanca saco-terno pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'fez ninguno', pieles: 'oliva-clara oliva canela' }),
  A('libanesa-1950', 'Libanesa de 1950', 'medio-oriente', [1940, 1975], 'f', 'media', 'vestido-50 perlas zapatos-vestir', VEST, { peinados: 'ondas recogido', pieles: 'clara-calida oliva-clara oliva' }),
  // ===== Variantes históricas de oficios y rangos =====
  A('egipto-escriba', 'Escriba egipcio', 'egipto', [-2600, -300], 'm', 'media', 'shenti-plisado collar-usej sandalias-cuero', { A: 'blanco-lino marfil', B: BL, C: 'azul-egipcio', D: 'oro' }, { peinados: 'rapado peluca-egipcia', pieles: 'oliva canela bronce' }),
  A('roma-liberto', 'Liberto romano', 'roma', [-100, 400], 'm', 'baja', 'tunica-romana cinturon-cuero sandalias-cuero', { A: 'crudo lana-clara', B: 'lana-parda', C: 'rojo-granza', D: 'bronce' }, { peinados: 'romano corto', barbas: 'ninguna', pieles: 'oliva-clara oliva canela' }),
  A('grecia-campesino', 'Campesino griego', 'grecia', [-800, -30], 'm', 'baja', 'quiton-corto cinturon-cuerda sandalias-cuero', { A: 'crudo lana-parda', B: 'lana-parda', C: 'rojo-ocre', D: 'bronce' }, { peinados: 'corto rizos', barbas: 'barba-corta barba-completa', tocados: 'sombrero-ala-ancha ninguno', pieles: 'oliva-clara oliva canela' }),
  A('medieval-juglar', 'Juglar medieval', 'europa-medieval', [1000, 1500], 'm', 'baja', 'camisa-medieval jubon-medieval calzas zapatos-cuero cinturon-bolsa', { A: 'rojo-granza azul-ultramar azafran verde-hoja', B: 'azafran', C: 'azul-ultramar', D: 'cobre' }, { peinados: 'bob flequillo', barbas: 'ninguna bigote', tocados: 'gorro-frigio boina', pieles: CLAROS }),
  A('medieval-mercader', 'Mercader medieval', 'europa-medieval', [1100, 1500], 'm', 'media', 'camisa-medieval jubon-medieval calzas botas-cuero cinturon-bolsa capa-corta-ribete', { A: 'indigo verde-botella borravino azul-ultramar', B: 'crudo', C: 'oro', D: 'oro' }, { peinados: 'bob', barbas: 'barba-corta ninguna', tocados: 'boina birrete', pieles: CLAROS }),
  A('renacimiento-comerciante', 'Comerciante renacentista', 'europa-moderna', [1450, 1650], 'm', 'media', 'camisa-globo jubon-renacentista gregueescos calzas-largas zapatos-cuero capa-corta-ribete', { A: 'lana-negra borravino verde-botella azul-prusia', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'corto flequillo', barbas: 'barba-corta candado', tocados: 'boina sombrero-ala-ancha', pieles: CLAROS }),
  A('vikingo-comerciante', 'Comerciante vikingo', 'germano', [750, 1100], 'm', 'media', 'tunica-vikinga bragas-holgadas capa-medieval botas-cuero cinturon-cuero cuentas-largas', { A: 'azul-egipcio indigo rojo-granza', B: 'lana-parda', C: 'rojo-granza', D: 'plata' }, { peinados: 'corto trenza-una', barbas: 'barba-completa barba-larga', tocados: 'ninguno', pieles: CLAROS }),
  A('inca-chasqui', 'Chasqui (mensajero inca)', 'andes', [1200, 1560], 'm', 'baja', 'unku-liso chumpi sandalias-cuero', { A: 'lana-parda alpaca-cafe rojo-cochinilla', B: 'crudo', C: 'rojo-cochinilla', D: 'cobre' }, { peinados: 'flequillo corto', tocados: 'gorro-lana ninguno', pieles: OSCURA, conf: 'baja' }),
  A('mapuche-lonko', 'Lonko mapuche', 'mapuche', [1550, 1950], 'm', 'alta', 'camisa-rotos chiripa makun trariwe trapelacucha descalzo', { A: 'lana-negra azul-andino alpaca-cafe', B: 'rojo-mapuche', C: 'rojo-mapuche', D: 'plata' }, { peinados: 'largo-liso', barbas: 'ninguna', tocados: 'trarilonko', pieles: OSCURA, conf: 'media' }),
  A('otomano-campesino', 'Campesino otomano', 'medio-oriente', [1400, 1900], 'm', 'baja', 'camisa-medieval chaleco-kurdo sirwal faja-turbante zapatillas', { A: 'lana-parda umbra indigo', B: BL, C: 'rojo-granza', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote barba-corta', tocados: 'turbante fez', pieles: 'oliva-clara oliva canela' }),
  A('china-campesina-qing', 'Campesina china de la época Qing', 'asia-este', [1640, 1930], 'f', 'baja', 'cheongsam-invierno pantalon-chino zapatos-tela', { A: 'indigo crudo lana-negra', B: BL, C: 'indigo', D: 'cobre' }, { peinados: 'recogido trenza-una', tocados: 'panuelo ninguno', pieles: 'clara clara-calida oliva-clara' }),
];

Object.assign(__e, { ATUENDOS_SIGLO20 });
return __e;
})();

__m['datos/atuendos-mas'] = (() => {
const __e = {};
// Más atuendos: invierno y abrigo, gala, viaje, oficios y ceremonias, de la Antigüedad a 1985. Misma forma que atuendos.js.

const { A, BL, CLAROS, MEST, OSCURA, EBANO, CIUDAD, TRAB, VEST, GALA, TIERRA } = __m['datos/ayuda'];


const ATUENDOS_MAS = [
  // ===== Chile: invierno, gala, ciudad y juventud =====
  A('chile-invierno-1950-m', 'Chileno de abrigo (1950)', 'chile-rural', [1935, 1975], 'm', 'media', 'camisa-blanca corbata abrigo-corto pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'fedora boina', pieles: MEST }),
  A('chile-invierno-1950-f', 'Chilena de abrigo de piel (1950)', 'chile-rural', [1935, 1975], 'f', 'alta', 'blusa-seda falda-lapiz abrigo-pieles perlas zapatos-vestir', VEST, { peinados: 'ondas recogido', tocados: 'cloche ninguno', pieles: MEST }),
  A('chile-gala-1950-f', 'Chilena de gala (1950)', 'chile-rural', [1940, 1975], 'f', 'alta', 'vestido-fiesta estola perlas zapatos-vestir', GALA, { peinados: 'ondas recogido', pieles: MEST }),
  A('chile-gala-1900-m', 'Caballero chileno de frac', 'chile-rural', [1860, 1960], 'm', 'alta', 'camisa-blanca lazo-cuello frac pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote barba-corta ninguna', tocados: 'sombrero-copa', pieles: MEST }),
  A('chile-gala-1900-f', 'Dama chilena de gala (1900)', 'chile-rural', [1880, 1935], 'f', 'alta', 'vestido-fiesta-manga capa-opera perlas gargantilla zapatos-vestir', GALA, { peinados: 'recogido ondas', pieles: MEST }),
  A('chile-lluvia-sur', 'Hombre del sur con impermeable', 'chile-rural', [1920, 1985], 'm', 'baja', 'sueter-cuello-alto impermeable pantalon-trabajo botas-goma', TRAB, { peinados: 'corto', barbas: 'bigote barba-corta ninguna', tocados: 'gorro-lana gorra-visera', pieles: MEST }),
  A('chile-joven-1970', 'Joven chileno de 1970', 'chile-rural', [1955, 1985], 'm', 'baja', 'camisa-denim pantalon-vaquero chaqueta-cuero zapatillas-lona', TRAB, { peinados: 'corto largo-liso', barbas: 'bigote ninguna', pieles: MEST }),
  A('chile-joven-1970-f', 'Joven chilena de 1970', 'chile-rural', [1960, 1985], 'f', 'baja', 'blusa-seda pantalon-campana cardigan zapatillas-lona', VEST, { peinados: 'largo-liso trenzas-dos', pieles: MEST }),
  A('chile-novia-1900', 'Novia chilena de 1900', 'chile-rural', [1860, 1920], 'f', 'alta', 'vestido-novia enaguas perlas zapatos-vestir', { A: 'blanco-optico marfil crema', B: 'crema', C: 'marfil', D: 'plata' }, { peinados: 'recogido', tocados: 'velo', pieles: MEST }),
  A('huaso-chamanto', 'Huaso con chamanto de diario', 'chile-rural', [1800, 1960], 'm', 'media', 'camisa-rotos chamanto pantalon-ajustado faja-huasa botas-cuero', { A: 'rojo-granza azul-andino lana-negra', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'chupalla', pieles: MEST }),
  A('mapuche-kepam', 'Mujer mapuche con küpam', 'mapuche', [1500, 1950], 'f', 'media', 'kepam trariwe ukulla trapelacucha collar-cuentas descalzo', { A: 'lana-negra azul-andino', B: 'rojo-mapuche', C: 'rojo-mapuche', D: 'plata' }, { peinados: 'trenzas-dos', tocados: 'trarilonko', pieles: OSCURA }),
  // ===== Latinoamérica y Estados Unidos: abrigo, viaje y gala =====
  A('latam-impermeable-1950', 'Hombre de ciudad con gabardina (1950)', 'latinoamerica-moderna', [1930, 1975], 'm', 'media', 'camisa-blanca corbata gabardina pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'fedora', pieles: MEST }),
  A('latam-gala-1950', 'Dama latinoamericana de gala (1950)', 'latinoamerica-moderna', [1935, 1975], 'f', 'alta', 'vestido-fiesta-manga estola perlas zapatos-vestir', GALA, { peinados: 'ondas recogido', pieles: MEST + ' ' + OSCURA }),
  A('eeuu-detective-1940', 'Hombre con gabardina (1940)', 'norteamerica-moderna', [1925, 1960], 'm', 'media', 'camisa-blanca corbata gabardina pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'fedora', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-dama-abrigo-1940', 'Dama con abrigo de piel (1940)', 'norteamerica-moderna', [1930, 1960], 'f', 'alta', 'vestido-40 abrigo-pieles perlas zapatos-vestir', VEST, { peinados: 'ondas', tocados: 'cloche ninguno', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-motociclista-1955', 'Motociclista de 1955', 'norteamerica-moderna', [1945, 1975], 'm', 'baja', 'polera chaqueta-cuero pantalon-vaquero botas-trabajo', TRAB, { peinados: 'corto', barbas: 'ninguna bigote', pieles: CLAROS + ' clara-calida ' + OSCURA }),
  A('eeuu-secretaria-1955', 'Secretaria de 1955', 'norteamerica-moderna', [1945, 1975], 'f', 'media', 'blusa-seda falda-lapiz traje-sastre perlas zapatos-vestir', VEST, { peinados: 'ondas recogido', pieles: CLAROS + ' clara-calida' }),
  A('eeuu-baile-1950', 'Joven en un baile (1950)', 'norteamerica-moderna', [1940, 1965], 'f', 'media', 'vestido-fiesta estola zapatos-vestir', GALA, { peinados: 'ondas', pieles: CLAROS + ' clara-calida ' + OSCURA }),
  A('eeuu-1970-campana', 'Joven con pantalón de campana (1970)', 'norteamerica-moderna', [1965, 1985], 'm', 'baja', 'sueter-cuello-alto pantalon-campana chaqueta-cuero zapatillas-lona', TRAB, { peinados: 'largo-liso rizos', barbas: 'bigote barba-corta ninguna', pieles: CLAROS + ' ' + OSCURA }),
  A('eeuu-granjero-invierno', 'Granjero en invierno', 'norteamerica-moderna', [1900, 1975], 'm', 'baja', 'camisa-cuadros abrigo-corto pantalon-vaquero botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-visera', pieles: CLAROS + ' clara-calida' }),
  // ===== Europa, 1860 a 1975 =====
  A('xx-viaje-1930-f', 'Viajera de 1930', 'europa-xix', [1920, 1950], 'f', 'media', 'blusa-seda falda-plisada gabardina zapatos-vestir', VEST, { peinados: 'ondas bob', tocados: 'cloche', pieles: CLAROS }),
  A('xx-viaje-1930-m', 'Viajero de 1930', 'europa-xix', [1920, 1950], 'm', 'media', 'camisa-blanca corbata gabardina pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'fedora', pieles: CLAROS }),
  A('xx-ropa-de-dormir', 'Mujer en camisón', 'europa-xix', [1850, 1960], 'f', 'media', 'camison chal-lana descalzo', { A: 'blanco-lino crema', B: 'crema', C: 'rosa-viejo', D: 'plata' }, { peinados: 'trenza-una largo-liso', pieles: CLAROS }),
  A('xx-baile-1950', 'Dama de baile de 1950', 'europa-xix', [1940, 1965], 'f', 'alta', 'vestido-fiesta-manga estola perlas capa-opera zapatos-vestir', GALA, { peinados: 'ondas recogido', pieles: CLAROS }),
  A('xx-sastre-1950-f', 'Mujer con traje sastre (1950)', 'europa-xix', [1940, 1970], 'f', 'media', 'traje-sastre falda-lapiz blusa-seda perlas zapatos-vestir', VEST, { peinados: 'ondas recogido', tocados: 'cloche ninguno', pieles: CLAROS }),
  A('xx-montana-1930', 'Excursionista de 1930', 'europa-xix', [1910, 1960], 'm', 'media', 'sueter-cuello-alto saco-cruzado pantalon-golf botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-visera boina', pieles: CLAROS }),
  A('xx-pieles-1925', 'Mujer con abrigo de piel (1925)', 'europa-xix', [1915, 1940], 'f', 'alta', 'vestido-flapper abrigo-pieles perlas zapatos-vestir', GALA, { peinados: 'bob ondas', tocados: 'cloche', pieles: CLAROS }),
  A('xx-panadero', 'Panadero', 'europa-xix', [1880, 1970], 'm', 'baja', 'camisa-trabajo delantal-peto pantalon-trabajo zapatos-cuero', { A: 'blanco-optico crudo', B: BL, C: 'blanco-lino', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-plana', pieles: CLAROS }),
  A('xx-pastor-protestante', 'Pastor protestante', 'europa-xix', [1850, 1970], 'm', 'media', 'camisa-blanca cuello-alto saco-terno pantalon-vestir zapatos-vestir', { A: 'negro-carbon lana-negra', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'corto-raya', barbas: 'barba-corta ninguna', pieles: CLAROS }),
  A('xx-camarero', 'Camarero de café', 'europa-xix', [1880, 1970], 'm', 'baja', 'camisa-blanca chaleco-terno pantalon-corte-alto zapatos-vestir delantal-blanco', { A: 'lana-negra gris-oscuro', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'corto-raya', barbas: 'bigote ninguna', pieles: CLAROS }),
  A('xx-verano-1930', 'Mujer de verano (1930)', 'europa-xix', [1920, 1960], 'f', 'baja', 'vestido-sencillo-corto chal-lana zapatos-cuero', VEST, { peinados: 'bob recogido', tocados: 'panuelo ninguno', pieles: CLAROS }),
  A('xx-caballero-cruzado-1935', 'Caballero de saco cruzado (1935)', 'europa-xix', [1925, 1950], 'm', 'media', 'camisa-blanca corbata saco-cruzado pantalon-vestir zapatos-vestir', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote', tocados: 'fedora', pieles: CLAROS }),
  A('xix-abrigo-caballero', 'Caballero con abrigo largo', 'europa-xix', [1830, 1920], 'm', 'alta', 'camisa-blanca lazo-cuello abrigo-largo pantalon-recto zapatos-cuero', CIUDAD, { peinados: 'corto-raya', barbas: 'barba-completa patillas bigote', tocados: 'sombrero-copa', pieles: CLAROS }),
  A('renacimiento-ceremonia', 'Caballero de ceremonia renacentista', 'europa-moderna', [1480, 1620], 'm', 'alta', 'camisa-globo jubon-renacentista gregueescos calzas-largas zapatos-cuero gorguera capa-ceremonial', { A: 'lana-negra borravino azul-prusia', B: 'carmin', C: 'oro', D: 'oro' }, { peinados: 'corto flequillo', barbas: 'barba-corta candado', tocados: 'boina sombrero-ala-ancha', pieles: CLAROS }),
  A('medieval-campesino-invierno', 'Campesino medieval en invierno', 'europa-medieval', [500, 1500], 'm', 'baja', 'camisa-medieval tunica-corta-lana calzones-medievales polainas-lana capa-viaje zapatos-cuero', TIERRA, { peinados: 'flequillo corto', barbas: 'ninguna barba-corta', tocados: 'capucha gorro-lana', pieles: CLAROS }),
  A('medieval-campesina-invierno', 'Campesina medieval en invierno', 'europa-medieval', [500, 1500], 'f', 'baja', 'vestido-lino-largo faldon-campesina chal-lana capa-viaje zuecos', TIERRA, { peinados: 'trenza-una recogido', tocados: 'panuelo velo', pieles: CLAROS }),
  A('medieval-verano-f', 'Mujer medieval en verano', 'europa-medieval', [500, 1500], 'f', 'baja', 'vestido-lino-largo cinturon-cuerda zapatos-cuero collar-cuentas', { A: 'crudo lana-clara blanco-lino', B: 'lana-clara', C: 'rojo-ocre', D: 'cobre' }, { peinados: 'trenza-una largo-liso', tocados: 'panuelo ninguno', pieles: CLAROS }),
  A('monje-con-escapulario', 'Monje con escapulario', 'europa-medieval', [500, 1900], 'm', 'baja', 'habito-monje escapulario cuerda-franciscana sandalias-cuero', { A: 'lana-parda lana-negra', B: 'lana-negra', C: 'lana-clara', D: 'cobre' }, { peinados: 'tonsura', barbas: 'ninguna barba-corta', pieles: CLAROS }),
  // ===== Antigüedad, Medio Oriente y Asia: variantes =====
  A('roma-provincial-galo', 'Galo bajo dominio romano', 'roma', [-50, 400], 'm', 'baja', 'tunica-plebeya bragae capa-viaje botas-cuero cinturon-cuero', { A: 'lana-parda verde-musgo rojo-granza', B: 'lana-parda', C: 'rojo-granza', D: 'bronce' }, { peinados: 'largo-liso corto', barbas: 'bigote barba-corta', pieles: CLAROS }),
  A('roma-esclavo', 'Esclavo romano', 'roma', [-300, 400], 'm', 'baja', 'tunica-plebeya cinturon-cuerda sandalia-romana', { A: 'crudo lana-parda', B: 'lana-parda', C: 'lana-parda', D: 'hierro' }, { peinados: 'rapado corto', barbas: 'ninguna barba-corta', pieles: 'clara-calida oliva-clara oliva canela' }),
  A('judio-oracion', 'Hombre en oración con talit', 'judio', [1000, 1950], 'm', 'media', 'caftan-askenazi tallit pantalon-recto botas-cuero', { A: 'lana-negra gris-oscuro', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'corto', barbas: 'barba-larga patillas barba-completa', tocados: 'kufi gorra-plana', pieles: CLAROS + ' oliva-clara' }),
  A('beduino-dishdasha', 'Hombre árabe con dishdasha', 'medio-oriente', [1700, 1980], 'm', 'media', 'dishdasha faja-turbante sandalias-cuero', { A: 'blanco-lino crudo marfil', B: BL, C: 'lana-negra', D: 'plata' }, { peinados: 'corto', barbas: 'bigote barba-corta', tocados: 'kufiya', pieles: 'oliva-clara oliva canela' }),
  A('persa-chador-mujer', 'Mujer persa con chador', 'medio-oriente', [1700, 1980], 'f', 'media', 'vestido-regional chador zapatillas', { A: 'lana-negra azul-marino', B: 'crema', C: 'plata', D: 'plata' }, { peinados: 'largo-liso', tocados: 'velo', pieles: 'oliva-clara oliva' }),
  A('nahua-quechquemitl', 'Mujer nahua con quechquémitl', 'mesoamerica', [1000, 1950], 'f', 'media', 'huipil quechquemitl enagua-maya collar-cuentas descalzo', { A: 'blanco-lino algodon-crudo azul-egipcio', B: 'rojo-cochinilla', C: 'rojo-cochinilla', D: 'verde-jade' }, { peinados: 'trenzas-dos coletas', pieles: OSCURA }),
  A('hawaiano-kihei', 'Hawaiano con kihei', 'polinesia', [1200, 1850], 'm', 'media', 'malo kihei guirnalda descalzo', { A: 'crudo barro rojo-ocre', B: 'rojo-ocre', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'corto rizos', barbas: 'bigote ninguna', pieles: OSCURA }),
  A('hawaiana-pareo', 'Hawaiana con pareo', 'polinesia', [1200, 1980], 'f', 'baja', 'wrap-top pareo-estampado guirnalda descalzo', { A: 'azul-real rojo-vivo turquesa', B: BL, C: 'blanco-optico', D: 'rojo-cartamo' }, { peinados: 'largo-ondulado largo-liso', tocados: 'corona-flores', pieles: OSCURA }),
  A('japon-ceremonia-mujer', 'Mujer japonesa de ceremonia', 'asia-este', [1600, 1960], 'f', 'alta', 'kimono obi obijime geta', { A: 'rojo-cartamo azul-ultramar verde-hoja malva', B: BL, C: 'oro', D: 'oro' }, { peinados: 'recogido mono-alto', pieles: 'porcelana clara clara-calida' }),
  A('japon-campesino-lluvia', 'Campesino japonés bajo la lluvia', 'asia-este', [1200, 1950], 'm', 'baja', 'tunica-plebeya pantalon-chino capa-lluvia-paja zapatos-tela', { A: 'indigo crudo lana-parda', B: 'crudo', C: 'indigo', D: 'cobre' }, { peinados: 'rapado corto', barbas: 'ninguna', tocados: 'sombrero-conico', pieles: 'clara clara-calida' }),
  A('vietnamita-lluvia', 'Vietnamita con capa de paja', 'sudeste-regional', [1000, 1950], 'f', 'baja', 'chaqueta-mao pantalon-chino sombrero-paja-capa sandalias-cuero', { A: 'indigo lana-negra crudo', B: BL, C: 'indigo', D: 'cobre' }, { peinados: 'largo-liso recogido', tocados: 'sombrero-conico', pieles: 'clara-calida oliva-clara oliva' }),
  A('tibetano-invierno', 'Tibetano con manto de lana', 'asia-himalaya', [1000, 1950], 'm', 'baja', 'chuba-tibetana manto-lana-ancho botas-tibetanas', { A: 'lana-parda lana-negra borravino', B: 'lana-negra', C: 'azafran', D: 'plata' }, { peinados: 'trenza-una corto', barbas: 'ninguna bigote', pieles: 'clara-calida oliva-clara oliva canela' }),
  A('croata-invierno-mujer', 'Croata en invierno', 'europa-campesina', [1700, 1960], 'f', 'baja', 'vestido-regional falda-lana-gruesa chal-lana opanke', { A: 'lana-negra azul-plomo borravino', B: BL, C: 'rojo-granza', D: 'plata' }, { peinados: 'trenza-una recogido', tocados: 'panuelo', pieles: CLAROS }),
  A('siberiano-verano', 'Cazador siberiano en verano', 'siberia', [1000, 1950], 'm', 'baja', 'camisa-lino-larga chaleco-pieles pantalon-piel botas-piel', { A: 'crudo lana-parda cuero-claro', B: 'piel-nutria', C: 'rojo-ocre', D: 'cobre' }, { peinados: 'corto largo-liso', barbas: 'ninguna', tocados: 'ushanka', pieles: 'clara clara-calida oliva-clara' }),
  A('africa-kaftan-1970', 'Hombre africano con kaftán (1970)', 'africa-oeste', [1950, 1985], 'm', 'media', 'kaftan-corto sokoto sandalias-cuero', { A: 'azul-real verde-bosque burdeos terracota', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'afro rapado corto', barbas: 'bigote ninguna', tocados: 'kufi ninguno', pieles: EBANO }),
];

Object.assign(__e, { ATUENDOS_MAS });
return __e;
})();

__m['datos/atuendos-moda'] = (() => {
const __e = {};
// Atuendos que usan los tipos de ropa nuevos (tirantes, halter, sirena, trapecio, capuchas, pieles, pantalones y faldas
// variados, monos…). Misma forma que atuendos.js. Los colores de cada prenda vienen de su perfil (colores-prendas.js).

const { CLAROS, MEST, CIUDAD, TRAB, VEST, GALA, F, M } = __m['datos/ayuda'];



const ATUENDOS_MODA = [
  // ===== Mujeres: vestidos =====
  F('moda-chile-verano-1955', 'Chilena de verano con vestido de tirantes (1955)', 'chile-rural', [1948, 1978], 'media', 'vestido-tirantes cardigan tacones perlas', VEST, { peinados: 'ondas bob', tocados: 'pamela ninguno', pieles: MEST }),
  F('moda-chile-gala-1960', 'Chilena de gala con vestido largo de tirantes (1960)', 'chile-rural', [1955, 1985], 'alta', 'vestido-tirantes-largo estola tacones perlas', GALA, { peinados: 'ondas recogido', pieles: MEST }),
  F('moda-chile-halter-1970', 'Chilena con vestido halter (1970)', 'chile-rural', [1965, 1985], 'media', 'vestido-halter chancletas collar-cuentas', VEST, { peinados: 'largo-liso bob', pieles: MEST }),
  F('moda-latam-asimetrico', 'Latinoamericana con vestido de un hombro', 'latinoamerica-moderna', [1960, 1985], 'alta', 'vestido-asimetrico tacones collar-colgante', GALA, { peinados: 'recogido largo-liso', pieles: MEST + ' canela bronce' }),
  F('moda-eeuu-corazon-1955', 'Estadounidense de escote corazón (1955)', 'norteamerica-moderna', [1945, 1965], 'media', 'vestido-corazon tacones perlas', VEST, { peinados: 'ondas', tocados: 'cloche ninguno', pieles: CLAROS }),
  F('moda-gala-corazon', 'Dama de gala con escote corazón', 'europa-xix', [1935, 1975], 'alta', 'vestido-corazon-gala estola tacones perlas', GALA, { peinados: 'ondas recogido', pieles: CLAROS }),
  F('moda-sirena-gala', 'Dama con vestido de sirena y capa de gala', 'europa-xix', [1930, 1985], 'alta', 'vestido-sirena capa-opera tacones perlas', GALA, { peinados: 'ondas recogido', pieles: CLAROS }),
  F('moda-sirena-tirantes', 'Joven con vestido de sirena de tirantes', 'norteamerica-moderna', [1935, 1985], 'alta', 'vestido-sirena-tirantes estola tacones', GALA, { peinados: 'ondas largo-liso', pieles: CLAROS + ' ' + MEST }),
  F('moda-trapecio-1965', 'Joven con vestido trapecio (1965)', 'norteamerica-moderna', [1960, 1975], 'media', 'vestido-trapecio botines perlas', VEST, { peinados: 'bob largo-liso', tocados: 'beanie ninguno', pieles: CLAROS }),
  F('moda-globo', 'Mujer con vestido globo', 'europa-xix', [1955, 1985], 'media', 'vestido-globo botines collar-colgante', VEST, { peinados: 'bob recogido', pieles: CLAROS }),
  F('moda-tubo-1960', 'Ejecutiva con vestido recto y blazer (1960)', 'norteamerica-moderna', [1955, 1975], 'media', 'vestido-tubo blazer-ajustado tacones perlas', CIUDAD, { peinados: 'ondas recogido', pieles: CLAROS }),
  F('moda-chile-camisero-1950', 'Chilena con vestido camisero', 'chile-rural', [1945, 1975], 'media', 'vestido-camisero zapatos-oxford panuelo-triangulo', VEST, { peinados: 'ondas recogido', pieles: MEST }),
  F('moda-latam-volantes', 'Mujer con vestido de volantes y chal', 'latinoamerica-moderna', [1900, 1985], 'media', 'vestido-volantes chal-seda alpargatas', VEST, { peinados: 'trenzas-dos recogido', pieles: MEST + ' canela bronce' }),
  F('moda-pico', 'Mujer con vestido de bajo en pico', 'europa-xix', [1950, 1980], 'media', 'vestido-pico botines panuelo-triangulo', VEST, { peinados: 'bob ondas', pieles: CLAROS }),
  // ===== Mujeres: blusas, faldas y pantalones =====
  F('moda-blusa-farol', 'Mujer con blusa de manga farol', 'europa-xix', [1925, 1965], 'media', 'blusa-farol falda-midi tacones perlas', VEST, { peinados: 'ondas bob', pieles: CLAROS }),
  F('moda-blusa-pagoda', 'Mujer con blusa de manga pagoda', 'norteamerica-moderna', [1950, 1985], 'media', 'blusa-pagoda falda-lapiz tacones panuelo-triangulo', VEST, { peinados: 'ondas recogido', pieles: CLAROS }),
  F('moda-camisa-clasica', 'Chilena con camisa y falda con abertura', 'chile-rural', [1940, 1985], 'media', 'camisa-clasica falda-abertura zapatos-oxford', VEST, { peinados: 'ondas recogido', pieles: MEST }),
  F('moda-camisa-ajustada', 'Joven con camisa ajustada y pitillo', 'norteamerica-moderna', [1955, 1985], 'media', 'camisa-ajustada pantalon-pitillo botines', VEST, { peinados: 'bob largo-liso', pieles: CLAROS + ' ' + MEST }),
  F('moda-camiseta-cap', 'Mujer con camiseta y pantalón capri', 'norteamerica-moderna', [1950, 1985], 'baja', 'camiseta-cap pantalon-capri chancletas', VEST, { peinados: 'bob largo-liso trenza-una', pieles: CLAROS + ' ' + MEST }),
  F('moda-top-tirantes', 'Joven con top de tirantes y culotte', 'latinoamerica-moderna', [1965, 1985], 'baja', 'top-tirantes culotte chancletas', VEST, { peinados: 'largo-liso trenza-una', pieles: MEST + ' canela bronce' }),
  F('moda-top-halter', 'Joven con top halter y pantalón palazzo', 'norteamerica-moderna', [1965, 1985], 'media', 'top-halter pantalon-palazzo tacones', GALA, { peinados: 'largo-liso rizos', pieles: CLAROS + ' ' + MEST }),
  F('moda-sueter-leggings', 'Mujer con suéter holgado y mallas', 'europa-xix', [1970, 1985], 'baja', 'sueter-oversize leggings botines', VEST, { peinados: 'largo-liso bob', tocados: 'beanie ninguno', pieles: CLAROS }),
  F('moda-anorak', 'Mujer con anorak y pantalón de pinzas', 'europa-xix', [1950, 1985], 'baja', 'anorak pantalon-pliegues botines', TRAB, { peinados: 'bob trenza-una', tocados: 'beanie ninguno', pieles: CLAROS }),
  F('moda-abrigo-piel', 'Dama con abrigo de piel y vestido de 1950', 'europa-xix', [1920, 1965], 'alta', 'vestido-50 abrigo-piel-total tacones perlas', GALA, { peinados: 'ondas recogido', tocados: 'cloche ninguno', pieles: CLAROS }),
  F('moda-cuello-piel', 'Mujer con abrigo corto y cuello de piel', 'europa-xix', [1930, 1975], 'alta', 'vestido-40 abrigo-corto cuello-piel tacones perlas', GALA, { peinados: 'ondas recogido', tocados: 'cloche ninguno', pieles: CLAROS }),
  F('moda-blazer', 'Mujer con blazer, blusa y falda', 'norteamerica-moderna', [1950, 1985], 'media', 'blazer-ajustado blusa-seda falda-lapiz tacones perlas', CIUDAD, { peinados: 'ondas recogido bob', pieles: CLAROS + ' ' + MEST }),
  F('moda-bata-casa', 'Mujer en bata de casa', 'europa-xix', [1900, 1980], 'media', 'camison bata-casa descalzo', { A: 'crema rosa-viejo azul-celeste', B: 'crema', C: 'rosa-viejo', D: 'plata' }, { peinados: 'trenza-una largo-liso recogido', pieles: CLAROS }),
  F('moda-corse', 'Mujer con corsé y falda de capas', 'europa-xix', [1850, 1915], 'media', 'blusa-regional corse falda-capas botines', VEST, { peinados: 'recogido', pieles: CLAROS }),
  F('moda-falda-globo', 'Joven con falda globo', 'europa-xix', [1950, 1985], 'media', 'blusa-cuello-bebe falda-globo botines', VEST, { peinados: 'bob ondas', pieles: CLAROS }),
  F('moda-falda-trapecio', 'Joven con falda trapecio', 'norteamerica-moderna', [1960, 1980], 'media', 'polera falda-trapecio botines', VEST, { peinados: 'bob largo-liso', pieles: CLAROS + ' ' + MEST }),
  F('moda-falda-lado', 'Mujer con falda larga de abertura lateral', 'latinoamerica-moderna', [1960, 1985], 'media', 'blusa-seda falda-abertura-lado tacones', VEST, { peinados: 'recogido largo-liso', pieles: MEST }),
  F('moda-mini-1967', 'Joven con minifalda (1967)', 'norteamerica-moderna', [1963, 1975], 'baja', 'polera falda-mini botines', VEST, { peinados: 'bob largo-liso', pieles: CLAROS + ' ' + MEST }),
  F('moda-falda-sirena', 'Mujer con falda de sirena y blusa de seda', 'europa-xix', [1930, 1985], 'alta', 'blusa-seda falda-sirena tacones perlas', GALA, { peinados: 'ondas recogido', pieles: CLAROS }),
  F('moda-mono-gala', 'Mujer con mono de noche', 'norteamerica-moderna', [1970, 1985], 'alta', 'mono-elegante tacones perlas', GALA, { peinados: 'largo-liso recogido', pieles: CLAROS + ' ' + MEST }),
  F('moda-esclavina', 'Dama con vestido y esclavina', 'europa-xix', [1850, 1920], 'alta', 'vestido-victoriano esclavina zapatos-cuero perlas', { A: 'borravino verde-botella azul-prusia lana-negra', B: 'crema', C: 'lana-negra', D: 'plata' }, { peinados: 'recogido', tocados: 'velo ninguno', pieles: CLAROS }),
  F('moda-panuelo', 'Mujer con pañuelo al cuello', 'europa-xix', [1945, 1975], 'media', 'blusa-cuello-bebe falda-lapiz panuelo-triangulo zapatos-vestir', VEST, { peinados: 'ondas bob', pieles: CLAROS }),
  F('moda-babero', 'Mujer con cuello ancho redondeado', 'europa-xix', [1900, 1960], 'media', 'vestido-casa babero-cuello zapatos-vestir', VEST, { peinados: 'recogido ondas', pieles: CLAROS }),
  F('moda-sarouel', 'Mujer con sarouel y camiseta', 'norteamerica-moderna', [1970, 1985], 'baja', 'sarouel camiseta-sisa chancletas', VEST, { peinados: 'largo-liso trenza-una', pieles: CLAROS + ' ' + MEST }),
  // ===== Hombres =====
  M('moda-safari-latam', 'Hombre con camisa safari y bermudas', 'latinoamerica-moderna', [1930, 1975], 'media', 'camisa-safari bermuda zapatillas-lona', TRAB, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', tocados: 'canotier gorra-visera', pieles: MEST }),
  M('moda-polo', 'Hombre con polo y pantalón de pinzas', 'norteamerica-moderna', [1945, 1985], 'media', 'polo pantalon-pliegues zapatos-oxford', CIUDAD, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', pieles: CLAROS }),
  M('moda-camiseta-sisa', 'Hombre con camiseta sin mangas y pantalón cargo', 'norteamerica-moderna', [1950, 1985], 'baja', 'camiseta-sisa pantalon-cargo botas-trabajo', TRAB, { peinados: 'corto rapado', barbas: 'bigote barba-corta ninguna', pieles: CLAROS + ' ' + MEST }),
  M('moda-sudadera', 'Joven con sudadera con capucha y jeans', 'norteamerica-moderna', [1965, 1985], 'baja', 'sudadera-capucha jeans-clasicos zapatillas-lona', TRAB, { peinados: 'corto largo-liso', barbas: 'ninguna', tocados: 'beanie ninguno', pieles: CLAROS + ' ' + MEST }),
  M('moda-bomber', 'Hombre con chaqueta bomber', 'norteamerica-moderna', [1950, 1985], 'baja', 'bomber pantalon-cargo botas-trabajo', TRAB, { peinados: 'corto rapado', barbas: 'bigote ninguna', tocados: 'gorra-visera ninguno', pieles: CLAROS + ' ' + MEST }),
  M('moda-parka', 'Hombre con parka de capucha', 'europa-xix', [1950, 1985], 'baja', 'parka-capucha pantalon-tubo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'barba-corta ninguna', tocados: 'beanie ninguno', pieles: CLAROS }),
  M('moda-abrigo-punos-piel', 'Caballero con abrigo de puños de piel', 'europa-xix', [1900, 1960], 'alta', 'camisa-blanca corbata abrigo-punos-piel pantalon-vestir zapatos-oxford', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote barba-corta', tocados: 'sombrero-copa fedora', pieles: CLAROS }),
  M('moda-chaqueta-safari', 'Hombre con chaqueta safari', 'latinoamerica-moderna', [1950, 1985], 'media', 'chaqueta-safari camisa-cuadros pantalon-cargo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-visera', pieles: MEST }),
  M('moda-motera', 'Motociclista con chaqueta de cuero', 'norteamerica-moderna', [1950, 1985], 'baja', 'chaqueta-motera polera jeans-clasicos botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote barba-corta ninguna', pieles: CLAROS + ' ' + MEST }),
  M('moda-batin', 'Hombre en batín de seda', 'europa-xix', [1900, 1975], 'alta', 'batin-seda pantalon-vestir chancletas', { A: 'burdeos azul-marino verde-bosque', B: 'crema', C: 'oro', D: 'oro' }, { peinados: 'corto-raya', barbas: 'bigote ninguna', pieles: CLAROS }),
  M('moda-chaleco-acolchado', 'Hombre con chaleco acolchado', 'norteamerica-moderna', [1970, 1985], 'baja', 'chaleco-acolchado camisa-cuadros jeans-clasicos botas-trabajo', TRAB, { peinados: 'corto largo-liso', barbas: 'bigote barba-corta ninguna', pieles: CLAROS }),
  M('moda-jinete', 'Jinete con pantalón de montar', 'europa-xix', [1880, 1960], 'alta', 'camisa-clasica pantalon-jodhpur botas-altas cinturon-fino', { A: 'beige crema', B: 'crema', C: 'cuero-oscuro', D: 'plata' }, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'gorra-plana ninguno', pieles: CLAROS }),
  M('moda-pescador', 'Hombre con pantalón de pescador', 'latinoamerica-moderna', [1950, 1985], 'baja', 'camiseta-sisa pantalon-pescador chancletas', TRAB, { peinados: 'corto rapado', barbas: 'bigote ninguna', tocados: 'bucket gorra-visera', pieles: MEST + ' canela bronce' }),
  M('moda-mono-trabajo', 'Obrero con mono de trabajo', 'europa-xix', [1930, 1985], 'baja', 'mono-trabajo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'casco-obra gorra-plana', pieles: CLAROS }),
  M('moda-chalina', 'Hombre con chalina', 'europa-xix', [1930, 1985], 'media', 'camisa-blanca chaleco-terno pantalon-vestir chalina zapatos-oxford', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'fedora ninguno', pieles: CLAROS }),
  M('moda-tirantes', 'Hombre con tirantes', 'europa-xix', [1920, 1960], 'baja', 'camisa-cuadros pantalon-pliegues tirantes-pantalon zapatos-oxford', TRAB, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', tocados: 'gorra-plana', pieles: CLAROS }),
];

Object.assign(__e, { ATUENDOS_MODA });
return __e;
})();

__m['datos/atuendos'] = (() => {
const __e = {};
// Atuendos completos: una combinación de prendas del catálogo (prendas.js) típica de una región, una época,
// un sexo y una clase social. Las épocas son años (negativos = antes de nuestra era) y aproximadas: los trajes
// duraron más que sus reglas. La paleta nombra tintes de colores.js; el generador elige uno por letra.

const { A } = __m['datos/ayuda'];
const { ATUENDOS_AMPLIADOS } = __m['datos/atuendos-ampliados'];
const { ATUENDOS_SIGLO20 } = __m['datos/atuendos-siglo20'];
const { ATUENDOS_MAS } = __m['datos/atuendos-mas'];
const { ATUENDOS_MODA } = __m['datos/atuendos-moda'];

const REGIONES = {
  egipto: 'Egipto antiguo', mesopotamia: 'Mesopotamia', grecia: 'Grecia antigua', roma: 'Roma', bizancio: 'Bizancio',
  'europa-medieval': 'Europa medieval', germano: 'Pueblos celtas y germanos', eslavo: 'Mundo eslavo', 'europa-moderna': 'Europa, siglos XV al XVIII',
  'europa-xix': 'Europa, siglos XIX y XX', judio: 'Comunidades judías', 'medio-oriente': 'Medio Oriente y Persia', 'asia-sur': 'Asia del Sur',
  'asia-este': 'Asia oriental', 'sudeste-asiatico': 'Sudeste asiático', 'africa-norte': 'África del norte', 'africa-oeste': 'África occidental',
  'africa-este-sur': 'África oriental y austral', polinesia: 'Polinesia', andes: 'Andes', mesoamerica: 'Mesoamérica', 'norteamerica': 'Norteamérica',
  artico: 'Ártico', mapuche: 'Pueblo mapuche', 'chile-rural': 'Chile (campo, ciudad y colonia)', 'patagonia-fueguina': 'Patagonia y Tierra del Fuego',
  'rio-de-la-plata': 'Río de la Plata', 'hispanoamerica-colonial': 'Hispanoamérica colonial', 'rapa-nui': 'Rapa Nui',
  'europa-campesina': 'Europa rural y regional', 'asia-central': 'Asia central y Mongolia', siberia: 'Siberia', caucaso: 'Cáucaso', 'mundo-antiguo': 'Mundo antiguo del Mediterráneo y Asia occidental',
  'africa-cuerno': 'Cuerno de África', 'africa-sahel': 'Sahel y Sahara', 'africa-nilo': 'Valle del Nilo (Nubia y Sudán)', 'asia-himalaya': 'Himalaya y Tíbet', amazonia: 'Amazonia y tierras bajas', caribe: 'Caribe indígena',
  oceania: 'Oceanía (Australia, Nueva Zelanda y Melanesia)', 'mexico-regional': 'México regional', 'latinoamerica-moderna': 'Latinoamérica, 1850 a 1980', 'norteamerica-moderna': 'Estados Unidos y Canadá, 1840 a 1980', 'asia-sur-regional': 'India: trajes regionales', 'sudeste-regional': 'Sudeste asiático: trajes regionales',
};

const ATUENDOS = [
  // ===== Egipto =====
  A('egipto-mujer-comun', 'Egipcia del pueblo', 'egipto', [-3000, -300], 'f', 'baja', 'kalasiris descalzo collar-cuentas', { A: 'blanco-lino crudo', B: 'rojo-ocre azul-celeste', C: 'azul-egipcio rojo-ocre', D: 'cobre' }, { peinados: 'bob largo-liso', pieles: 'oliva canela bronce' }),
  A('egipto-mujer-noble', 'Dama egipcia', 'egipto', [-2600, -300], 'f', 'alta', 'kalasiris-plisada collar-usej sandalias-cuero brazaletes', { A: 'blanco-lino marfil', B: 'azul-egipcio verde-malaquita', C: 'azul-egipcio rojo-cartamo', D: 'oro' }, { peinados: 'peluca-egipcia bob', tocados: 'diadema nemes', pieles: 'oliva-clara oliva canela' }),
  A('egipto-hombre-obrero', 'Egipcio del pueblo', 'egipto', [-3000, -300], 'm', 'baja', 'shenti descalzo', { A: 'blanco-lino crudo', B: 'crudo', C: 'crudo', D: 'cobre' }, { peinados: 'rapado corto', pieles: 'canela bronce cobre' }),
  A('egipto-hombre-noble', 'Noble egipcio', 'egipto', [-2600, -300], 'm', 'alta', 'shenti-plisado collar-usej sandalias-cuero brazaletes', { A: 'blanco-lino marfil', B: 'azul-egipcio', C: 'oro rojo-cartamo', D: 'oro' }, { peinados: 'peluca-egipcia calvo', tocados: 'nemes ninguno', pieles: 'oliva canela bronce' }),
  // ===== Mesopotamia =====
  A('mesopotamia-hombre', 'Hombre sumerio', 'mesopotamia', [-3000, -500], 'm', 'media', 'kaunakes sandalias-cuero cuerda-franciscana', { A: 'lana-clara crudo', B: 'lana-parda', C: 'rojo-ocre', D: 'bronce' }, { peinados: 'rizos corto', barbas: 'barba-larga barba-completa', pieles: 'oliva oliva-clara canela' }),
  A('mesopotamia-mujer', 'Mujer sumeria', 'mesopotamia', [-3000, -500], 'f', 'media', 'tunica-sumeria collar-cuentas sandalias-cuero', { A: 'lana-clara crudo azafran', B: 'indigo-claro', C: 'rojo-ocre indigo', D: 'oro bronce' }, { peinados: 'recogido largo-ondulado', tocados: 'diadema ninguno', pieles: 'oliva oliva-clara canela' }),
  A('asiria-noble', 'Noble asirio', 'mesopotamia', [-900, -600], 'm', 'alta', 'tunica-sumeria manto-asirio sandalias-cuero brazaletes cinturon-cuero', { A: 'escarlata purpura-tiro lana-clara', B: 'azafran', C: 'azafran oro', D: 'oro' }, { peinados: 'rizos', barbas: 'barba-larga', tocados: 'diadema ninguno', pieles: 'oliva canela' }),
  // ===== Grecia =====
  A('grecia-mujer-peplos', 'Griega con peplo', 'grecia', [-800, -30], 'f', 'media', 'peplos cinta-pecho sandalias-cuero collar-cuentas', { A: 'blanco-lino crudo rojo-granza indigo-claro', B: 'azafran', C: 'azul-celeste rojo-granza', D: 'oro' }, { peinados: 'recogido corona-trenzas bob', tocados: 'diadema ninguno', pieles: 'clara-calida oliva-clara oliva' }),
  A('grecia-mujer-quiton', 'Griega con quitón y manto', 'grecia', [-600, -30], 'f', 'alta', 'quiton-jonico himation-corto cinta-pecho sandalias-cuero collar-colgante brazaletes', { A: 'blanco-lino azafran verde-hoja', B: 'rojo-granza', C: 'indigo rojo-granza', D: 'oro' }, { peinados: 'recogido corona-trenzas', tocados: 'diadema ninguno', pieles: 'clara-calida oliva-clara oliva' }),
  A('grecia-hombre-comun', 'Griego con quitón corto', 'grecia', [-800, -30], 'm', 'baja', 'quiton-corto cinturon-cuerda sandalias-cuero', { A: 'crudo lana-clara', B: 'lana-parda', C: 'rojo-ocre', D: 'bronce' }, { peinados: 'corto rizos', barbas: 'barba-corta barba-completa ninguna', pieles: 'oliva-clara oliva canela' }),
  A('grecia-hombre-himation', 'Ciudadano griego', 'grecia', [-600, -30], 'm', 'media', 'himation sandalias-cuero', { A: 'blanco-lino crudo lana-clara indigo-claro', B: 'rojo-granza', C: 'indigo', D: 'oro' }, { peinados: 'corto rizos romano', barbas: 'barba-completa barba-corta', pieles: 'oliva-clara oliva canela' }),
  A('grecia-hoplita', 'Hoplita', 'grecia', [-700, -150], 'm', 'media', 'quiton-corto lorica-escamas clamide sandalias-cuero cinturon-cuero', { A: 'rojo-granza lana-clara', B: 'bronce', C: 'bronce', D: 'bronce' }, { peinados: 'corto', barbas: 'barba-corta ninguna', tocados: 'casco-nasal', pieles: 'oliva oliva-clara' }),
  // ===== Roma =====
  A('roma-ciudadano', 'Ciudadano romano', 'roma', [-300, 400], 'm', 'media', 'tunica-romana toga caliga', { A: 'blanco-lino crudo', B: 'purpura-tiro rojo-granza', C: 'rojo-granza', D: 'oro' }, { peinados: 'romano corto', barbas: 'ninguna', pieles: 'oliva-clara oliva canela' }),
  A('roma-senador', 'Senador romano', 'roma', [-100, 400], 'm', 'alta', 'tunica-romana toga-praetexta caliga', { A: 'blanco-lino marfil', B: 'purpura-tiro', C: 'purpura-tiro', D: 'oro' }, { peinados: 'romano corto', barbas: 'ninguna', pieles: 'oliva-clara oliva canela' }),
  A('roma-plebeyo', 'Plebeyo romano', 'roma', [-300, 400], 'm', 'baja', 'tunica-plebeya cinturon-cuerda sandalias-cuero', { A: 'crudo lana-parda rojo-ocre', B: 'lana-parda', C: 'rojo-ocre', D: 'cobre' }, { peinados: 'rapado corto', barbas: 'ninguna barba-corta', pieles: 'oliva canela bronce' }),
  A('roma-matrona', 'Matrona romana', 'roma', [-300, 400], 'f', 'alta', 'stola palla sandalias-cuero collar-colgante', { A: 'azafran rojo-granza verde-hoja blanco-lino', B: 'purpura-tiro', C: 'oro', D: 'oro' }, { peinados: 'recogido corona-trenzas', pieles: 'clara-calida oliva-clara oliva' }),
  A('roma-mujer-comun', 'Romana del pueblo', 'roma', [-300, 400], 'f', 'baja', 'vestido-sencillo cinturon-cuerda sandalias-cuero', { A: 'crudo lana-parda rojo-ocre', B: 'crudo', C: 'rojo-ocre', D: 'cobre' }, { peinados: 'recogido bob', tocados: 'panuelo ninguno', pieles: 'oliva oliva-clara canela' }),
  A('roma-legionario', 'Legionario', 'roma', [-100, 300], 'm', 'media', 'tunica-romana lorica-segmentata pteruges cinturon-cuero caliga', { A: 'rojo-granza escarlata', B: 'hierro', C: 'hierro', D: 'bronce' }, { peinados: 'rapado corto', tocados: 'casco-romano', pieles: 'oliva oliva-clara canela' }),
  // ===== Bizancio =====
  A('bizancio-noble', 'Noble bizantino', 'bizancio', [330, 1453], 'm', 'alta', 'tunica-bizantina clamide-bizantina zapatos-hebilla', { A: 'purpura-tiro azul-ultramar borravino', B: 'oro', C: 'oro', D: 'oro' }, { peinados: 'corto', barbas: 'barba-corta candado', tocados: 'diadema corona', pieles: 'oliva-clara oliva' }),
  A('bizancio-dama', 'Dama bizantina', 'bizancio', [330, 1453], 'f', 'alta', 'tunica-bizantina dalmatica collar-imperial zapatos-hebilla', { A: 'purpura-tiro verde-esmeralda azul-ultramar', B: 'oro', C: 'oro', D: 'oro' }, { peinados: 'recogido', tocados: 'velo diadema', pieles: 'oliva-clara oliva' }),
  // ===== Europa medieval =====
  A('medieval-campesino', 'Campesino medieval', 'europa-medieval', [500, 1500], 'm', 'baja', 'camisa-medieval calzones-medievales polainas-lana zapatos-cuero cinturon-cuerda', { A: 'crudo lana-parda lana-clara verde-musgo', B: 'lana-parda', C: 'lana-parda', D: 'cobre' }, { peinados: 'flequillo corto rapado', barbas: 'ninguna barba-corta', tocados: 'capucha gorro-lana ninguno', pieles: 'porcelana clara clara-calida' }),
  A('medieval-campesina', 'Campesina medieval', 'europa-medieval', [500, 1500], 'f', 'baja', 'cota-campesina delantal-campesino zuecos', { A: 'crudo lana-parda verde-musgo rojo-ocre', B: 'lana-clara', C: 'rojo-ocre', D: 'cobre' }, { peinados: 'trenza-una recogido', tocados: 'panuelo velo', pieles: 'porcelana clara clara-calida' }),
  A('medieval-noble', 'Noble medieval', 'europa-medieval', [900, 1450], 'm', 'alta', 'camisa-medieval sobreveste calzas botas-cuero cinturon-cuero capa-medieval', { A: 'azul-ultramar rojo-cochinilla verde-botella borravino', B: 'indigo', C: 'oro plata', D: 'oro' }, { peinados: 'bob flequillo corto-raya', barbas: 'ninguna barba-corta', tocados: 'birrete boina ninguno', pieles: 'porcelana clara' }),
  A('medieval-dama', 'Dama medieval', 'europa-medieval', [1000, 1450], 'f', 'alta', 'bliaut surcote cinturon-cuerda manto-largo zapatillas collar-colgante', { A: 'azul-ultramar rojo-cochinilla verde-botella purpura-lichen', B: 'blanco-lino', C: 'oro plata', D: 'oro' }, { peinados: 'trenzas-dos recogido largo-liso', tocados: 'velo diadema', pieles: 'porcelana clara' }),
  A('medieval-burgues', 'Burgués medieval', 'europa-medieval', [1100, 1500], 'm', 'media', 'camisa-medieval jubon-medieval calzas zapatos-cuero cinturon-bolsa', { A: 'indigo verde-musgo rojo-granza lana-parda', B: 'crudo', C: 'lana-negra', D: 'cobre' }, { peinados: 'bob flequillo', barbas: 'ninguna barba-corta', tocados: 'gorro-lana boina capucha', pieles: 'porcelana clara clara-calida' }),
  A('monje-benedictino', 'Monje', 'europa-medieval', [500, 1600], 'm', 'baja', 'habito-monje cuerda-franciscana sandalias-cuero', { A: 'lana-negra lana-parda', B: 'lana-negra', C: 'lana-parda', D: 'cobre' }, { peinados: 'tonsura', barbas: 'ninguna barba-corta', pieles: 'porcelana clara clara-calida oliva-clara' }),
  A('monje-franciscano', 'Fraile franciscano', 'europa-medieval', [1210, 1900], 'm', 'baja', 'habito-monje cuerda-franciscana sandalias-cuero', { A: 'lana-parda tabaco', B: 'lana-parda', C: 'crudo', D: 'crudo' }, { peinados: 'tonsura', barbas: 'barba-completa ninguna', tocados: 'capucha ninguno', pieles: 'clara clara-calida oliva-clara oliva' }),
  A('monja', 'Monja', 'europa-medieval', [500, 1900], 'f', 'baja', 'habito-monje cuerda-franciscana sandalias-cuero', { A: 'lana-negra lana-parda', B: 'blanco-lino', C: 'blanco-lino', D: 'crudo' }, { peinados: 'recogido', tocados: 'velo', pieles: 'porcelana clara clara-calida' }),
  A('clerigo', 'Clérigo', 'europa-medieval', [1000, 1950], 'm', 'media', 'sotana sobrepelliz zapatos-cuero', { A: 'negro-agalla lana-negra', B: 'blanco-lino', C: 'blanco-lino', D: 'plata' }, { peinados: 'corto tonsura', pieles: 'porcelana clara clara-calida oliva-clara' }),
  A('caballero-medieval', 'Caballero', 'europa-medieval', [1000, 1450], 'm', 'alta', 'camisa-medieval lorica-escamas sobreveste calzas botas-cuero capa-medieval cinturon-cuero', { A: 'rojo-cochinilla azul-ultramar blanco-lino', B: 'hierro', C: 'plata', D: 'oro' }, { peinados: 'corto bob', barbas: 'ninguna barba-corta', tocados: 'casco-nasal ninguno', pieles: 'porcelana clara' }),
  // ===== Germanos, celtas, vikingos =====
  A('vikingo-hombre', 'Vikingo', 'germano', [750, 1100], 'm', 'media', 'tunica-vikinga bragas-holgadas cinturon-cuero capa-medieval botas-cuero', { A: 'rojo-granza indigo verde-musgo lana-parda', B: 'lana-parda', C: 'azul-egipcio rojo-granza', D: 'bronce' }, { peinados: 'corto trenza-una', barbas: 'barba-larga barba-completa', tocados: 'casco-nasal ninguno', pieles: 'porcelana clara' }),
  A('vikinga-mujer', 'Mujer vikinga', 'germano', [750, 1100], 'f', 'media', 'tunica-vikinga vestido-delantal-vikingo broche-ovalado zapatos-cuero', { A: 'indigo-claro crudo rojo-granza', B: 'verde-musgo rojo-granza', C: 'azul-egipcio', D: 'bronce plata' }, { peinados: 'trenza-una recogido largo-liso', tocados: 'panuelo ninguno', pieles: 'porcelana clara' }),
  A('celta-hombre', 'Celta', 'germano', [-500, 600], 'm', 'media', 'tunica-plebeya pantalones-celtas bratt-celta botas-cuero cinturon-cuero', { A: 'verde-hoja rojo-granza lana-parda indigo', B: 'rojo-granza', C: 'bronce', D: 'bronce oro' }, { peinados: 'largo-liso corto', barbas: 'bigote barba-corta', pieles: 'porcelana clara' }),
  A('escoces-highlander', 'Highlander', 'germano', [1500, 1850], 'm', 'media', 'camisa-medieval kilt-plaid plaid-escoces zapatos-cuero cinturon-cuero', { A: 'rojo-granza verde-botella azul-prusia', B: 'verde-botella lana-negra', C: 'plata', D: 'plata' }, { peinados: 'corto largo-ondulado', barbas: 'ninguna bigote barba-corta', tocados: 'boina', pieles: 'porcelana clara' }),
  // ===== Eslavo =====
  A('eslavo-campesino', 'Campesino eslavo', 'eslavo', [900, 1920], 'm', 'baja', 'rubakha bragas-holgadas faja-huasa botas-cuero', { A: 'crudo blanco-lino rojo-ocre', B: 'rojo-granza', C: 'rojo-granza', D: 'rojo-granza' }, { peinados: 'bob flequillo corto', barbas: 'barba-completa barba-corta', tocados: 'ushanka gorra-plana ninguno', pieles: 'porcelana clara' }),
  A('eslava-campesina', 'Campesina eslava', 'eslavo', [900, 1920], 'f', 'baja', 'rubakha-larga sarafan collar-cuentas botas-cuero', { A: 'rojo-granza indigo verde-hoja', B: 'blanco-lino', C: 'azafran', D: 'oro' }, { peinados: 'trenza-una coletas', tocados: 'kokoshnik panuelo', pieles: 'porcelana clara' }),
  A('eslavo-boyardo', 'Boyardo', 'eslavo', [1000, 1700], 'm', 'alta', 'rubakha kaftan-eslavo faja-turbante botas-altas', { A: 'rojo-cochinilla azul-ultramar verde-botella', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'bob', barbas: 'barba-larga barba-completa', tocados: 'ushanka', pieles: 'porcelana clara' }),
  // ===== Europa moderna =====
  A('renacimiento-noble', 'Caballero renacentista', 'europa-moderna', [1480, 1620], 'm', 'alta', 'camisa-globo jubon-renacentista gregueescos calzas-largas zapatos-cuero gorguera capa-corta-ribete', { A: 'lana-negra borravino azul-plomo', B: 'carmin', C: 'oro', D: 'oro' }, { peinados: 'corto flequillo', barbas: 'barba-corta candado bigote', tocados: 'boina sombrero-ala-ancha', pieles: 'porcelana clara' }),
  A('renacimiento-dama', 'Dama renacentista', 'europa-moderna', [1480, 1620], 'f', 'alta', 'vestido-renacentista gorguera collar-colgante zapatillas', { A: 'verde-botella azul-ultramar carmin borravino', B: 'oro', C: 'oro plata', D: 'oro' }, { peinados: 'recogido corona-trenzas', tocados: 'diadema velo ninguno', pieles: 'porcelana clara' }),
  A('renacimiento-artesano', 'Artesano renacentista', 'europa-moderna', [1450, 1650], 'm', 'media', 'camisa-medieval chaleco-corto calzones-rodilla calzas-largas zapatos-cuero delantal-campesino', { A: 'lana-parda nogal verde-musgo indigo', B: 'crudo', C: 'lana-negra', D: 'cobre' }, { peinados: 'corto flequillo', barbas: 'barba-corta bigote', tocados: 'boina gorra-plana', pieles: 'porcelana clara clara-calida' }),
  A('renacimiento-campesina', 'Campesina europea', 'europa-moderna', [1450, 1800], 'f', 'baja', 'blusa-campesina corpino falda-larga delantal-blanco zuecos', { A: 'rojo-granza indigo verde-musgo', B: 'crudo', C: 'crudo', D: 'cobre' }, { peinados: 'trenza-una recogido', tocados: 'panuelo ninguno', pieles: 'porcelana clara clara-calida' }),
  A('barroco-caballero', 'Caballero barroco', 'europa-moderna', [1600, 1715], 'm', 'alta', 'camisa-blanca lazo-cuello casaca calzones-rodilla calzas-largas zapatos-hebilla capa-corta-ribete', { A: 'azul-ultramar rojo-cochinilla verde-botella lana-negra', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'peluca-barroca largo-ondulado', barbas: 'candado bigote ninguna', tocados: 'sombrero-ala-ancha ninguno', pieles: 'porcelana clara' }),
  A('barroca-dama', 'Dama barroca', 'europa-moderna', [1600, 1715], 'f', 'alta', 'vestido-barroco collar-colgante zapatos-hebilla chal-corto', { A: 'azul-plomo borravino verde-botella rosa-antiguo', B: 'blanco-lino', C: 'plata oro', D: 'plata' }, { peinados: 'rizos largo-ondulado recogido', pieles: 'porcelana clara clara' }),
  A('siglo18-caballero', 'Caballero del siglo XVIII', 'europa-moderna', [1715, 1795], 'm', 'alta', 'camisa-blanca lazo-cuello chaleco-largo casaca calzones-rodilla calzas-largas zapatos-hebilla', { A: 'azul-prusia rosa-antiguo verde-agua borravino', B: 'marfil', C: 'plata oro', D: 'oro' }, { peinados: 'peluca-barroca', barbas: 'ninguna', tocados: 'tricornio bicornio ninguno', pieles: 'porcelana clara' }),
  A('siglo18-dama', 'Dama del siglo XVIII', 'europa-moderna', [1715, 1795], 'f', 'alta', 'vestido-barroco chal-corto collar-colgante zapatos-hebilla', { A: 'rosa-antiguo azul-celeste verde-agua azafran', B: 'marfil', C: 'plata', D: 'plata' }, { peinados: 'peluca-barroca rizos recogido', pieles: 'porcelana clara' }),
  A('siglo18-aldeano', 'Aldeano del siglo XVIII', 'europa-moderna', [1700, 1800], 'm', 'baja', 'camisa-medieval chaleco-corto calzones-rodilla calzas-largas zapatos-hebilla', { A: 'lana-parda nogal indigo', B: 'crudo', C: 'lana-negra', D: 'cobre' }, { peinados: 'corto flequillo', barbas: 'ninguna bigote', tocados: 'tricornio gorra-plana', pieles: 'porcelana clara clara-calida' }),
  // ===== Europa XIX–XX =====
  A('xix-caballero', 'Caballero decimonónico', 'europa-xix', [1820, 1910], 'm', 'alta', 'camisa-blanca lazo-cuello chaleco-corto levita pantalon-recto zapatos-cuero', { A: 'lana-negra gris-pizarra azul-prusia', B: 'gris-ceniza', C: 'lana-negra', D: 'oro' }, { peinados: 'corto-raya corto', barbas: 'barba-completa patillas bigote ninguna', tocados: 'sombrero-copa bombin', pieles: 'porcelana clara' }),
  A('xix-dama-imperio', 'Dama de talle imperio', 'europa-xix', [1795, 1825], 'f', 'alta', 'vestido-imperio chal-lana cinta-pecho zapatillas', { A: 'blanco-lino marfil rosa-palido', B: 'blanco-lino', C: 'azul-celeste', D: 'oro' }, { peinados: 'recogido rizos', pieles: 'porcelana clara' }),
  A('xix-dama-victoriana', 'Dama victoriana', 'europa-xix', [1840, 1900], 'f', 'alta', 'vestido-victoriano collar-colgante zapatos-cuero', { A: 'borravino verde-botella azul-prusia lana-negra', B: 'blanco-lino', C: 'lana-negra', D: 'oro' }, { peinados: 'recogido', tocados: 'ninguno velo', pieles: 'porcelana clara' }),
  A('xix-obrero', 'Obrero', 'europa-xix', [1820, 1930], 'm', 'baja', 'camisa-rotos chaleco-corto pantalon-recto botas-cuero cinturon-cuero', { A: 'lana-parda gris-pizarra azul-plomo', B: 'crudo', C: 'lana-negra', D: 'cobre' }, { peinados: 'corto rapado', barbas: 'bigote barba-corta ninguna', tocados: 'gorra-plana bombin', pieles: 'porcelana clara clara-calida' }),
  A('xix-campesina', 'Campesina del siglo XIX', 'europa-xix', [1800, 1930], 'f', 'baja', 'vestido-sencillo delantal-blanco chal-lana zapatos-cuero', { A: 'lana-parda azul-plomo verde-musgo rojo-ocre', B: 'blanco-lino', C: 'blanco-lino', D: 'cobre' }, { peinados: 'recogido trenza-una', tocados: 'panuelo ninguno', pieles: 'porcelana clara clara-calida' }),
  A('xix-burgues', 'Burgués de 1900', 'europa-xix', [1880, 1925], 'm', 'media', 'camisa-blanca lazo-cuello chaleco-corto chaqueta-corta pantalon-recto zapatos-cuero', { A: 'gris-pizarra lana-parda azul-plomo', B: 'marfil', C: 'lana-negra', D: 'plata' }, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'bombin sombrero-copa', pieles: 'porcelana clara' }),
  A('xix-mujer-trabajadora', 'Mujer de ciudad, 1900', 'europa-xix', [1880, 1930], 'f', 'media', 'blusa-campesina falda-larga delantal-blanco zapatos-cuero', { A: 'azul-plomo lana-parda borravino', B: 'blanco-lino', C: 'blanco-lino', D: 'cobre' }, { peinados: 'recogido', pieles: 'porcelana clara clara' }),
  A('xix-marinero', 'Marinero', 'europa-xix', [1750, 1900], 'm', 'baja', 'camisa-rotos chaqueta-corta pantalon-recto bufanda-lana botas-cuero', { A: 'azul-prusia lana-parda crudo', B: 'blanco-lino', C: 'lana-negra', D: 'cobre' }, { peinados: 'corto', barbas: 'barba-corta barba-completa', tocados: 'boina gorra-plana ninguno', pieles: 'clara clara-calida oliva-clara' }),
  // ===== Judío =====
  A('askenazi-hombre', 'Judío askenazi', 'judio', [1600, 1940], 'm', 'media', 'camisa-blanca caftan-askenazi pantalon-recto botas-cuero', { A: 'lana-negra gris-pizarra', B: 'marfil', C: 'lana-negra', D: 'plata' }, { peinados: 'corto', barbas: 'barba-larga patillas barba-completa', tocados: 'gorra-plana kufi sombrero-copa', pieles: 'porcelana clara clara-calida oliva-clara' }),
  A('hebreo-antiguo', 'Hebreo antiguo', 'judio', [-1200, 100], 'm', 'baja', 'tunica-hebrea manto-hebreo cinturon-cuerda sandalias-cuero', { A: 'crudo lana-clara lana-parda', B: 'indigo', C: 'indigo', D: 'cobre' }, { peinados: 'corto largo-ondulado', barbas: 'barba-completa barba-larga', tocados: 'panuelo ninguno', pieles: 'oliva-clara oliva canela' }),
  A('hebrea-antigua', 'Hebrea antigua', 'judio', [-1200, 100], 'f', 'baja', 'tunica-hebrea manto-hebreo cinturon-cuerda sandalias-cuero collar-cuentas', { A: 'indigo-claro rojo-granza crudo', B: 'crudo', C: 'indigo', D: 'cobre' }, { peinados: 'trenzas-dos recogido', tocados: 'velo panuelo', pieles: 'oliva-clara oliva canela' }),
  // ===== Medio Oriente =====
  A('beduino', 'Beduino', 'medio-oriente', [600, 1950], 'm', 'media', 'thobe bisht faja-turbante sandalias-cuero', { A: 'blanco-lino crudo', B: 'lana-parda', C: 'oro lana-negra', D: 'oro' }, { peinados: 'corto', barbas: 'barba-corta bigote barba-completa', tocados: 'turbante', pieles: 'oliva canela bronce' }),
  A('mujer-arabe', 'Mujer árabe', 'medio-oriente', [600, 1950], 'f', 'media', 'abaya zapatillas collar-colgante', { A: 'lana-negra indigo-profundo borravino', B: 'lana-negra', C: 'plata oro', D: 'plata' }, { peinados: 'recogido', tocados: 'velo panuelo', pieles: 'oliva-clara oliva canela' }),
  A('otomano-noble', 'Noble otomano', 'medio-oriente', [1300, 1850], 'm', 'alta', 'camisa-medieval caftan-otomano sirwal faja-turbante zapatillas', { A: 'rojo-cochinilla azul-ultramar verde-esmeralda purpura-lichen', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'corto', barbas: 'barba-completa bigote', tocados: 'turbante fez', pieles: 'oliva-clara oliva' }),
  A('persa-comerciante', 'Comerciante persa', 'medio-oriente', [1000, 1900], 'm', 'media', 'camisa-medieval chaleco-beduino sirwal faja-turbante zapatillas', { A: 'indigo verde-hoja rojo-granza azafran', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'corto', barbas: 'barba-corta barba-completa', tocados: 'turbante fez kufi', pieles: 'oliva oliva-clara canela' }),
  A('otomana-dama', 'Dama otomana', 'medio-oriente', [1400, 1900], 'f', 'alta', 'caftan-otomano sirwal faja-turbante collar-colgante zapatillas', { A: 'rojo-cochinilla verde-esmeralda purpura-lichen azul-ultramar', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'largo-liso trenzas-dos', tocados: 'velo diadema', pieles: 'oliva-clara oliva' }),
  // ===== África del norte =====
  A('maghreb-hombre', 'Hombre del Magreb', 'africa-norte', [700, 1950], 'm', 'media', 'djellaba sirwal zapatillas', { A: 'crudo lana-parda indigo-claro gris-ceniza', B: 'crudo', C: 'indigo rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'barba-corta bigote', tocados: 'capucha fez turbante', pieles: 'oliva canela bronce' }),
  A('maghreb-mujer', 'Mujer del Magreb', 'africa-norte', [700, 1950], 'f', 'media', 'chilaba zapatillas collar-colgante brazaletes', { A: 'indigo rojo-granza verde-hoja azafran', B: 'blanco-lino', C: 'oro plata', D: 'plata' }, { peinados: 'trenzas-dos recogido', tocados: 'velo panuelo', pieles: 'oliva canela bronce' }),
  // ===== África occidental, oriental y austral =====
  A('africa-oeste-hombre', 'Hombre de África occidental', 'africa-oeste', [900, 2000], 'm', 'media', 'boubou sirwal sandalias-cuero', { A: 'indigo azul-celeste blanco-lino azafran', B: 'blanco-lino', C: 'oro azafran', D: 'oro' }, { peinados: 'rapado corto', barbas: 'ninguna bigote', tocados: 'kufi ninguno', pieles: 'castana ebano-claro ebano' }),
  A('africa-oeste-mujer', 'Mujer de África occidental', 'africa-oeste', [900, 2000], 'f', 'media', 'wrap-top kanga collar-cuentas sandalias-cuero', { A: 'indigo rojo-cartamo azafran verde-hoja', B: 'blanco-lino azafran', C: 'rojo-cartamo', D: 'oro' }, { peinados: 'trenzas-dos rizos', tocados: 'panuelo ninguno', pieles: 'castana ebano-claro ebano ebano-profundo' }),
  A('ashanti-rey', 'Jefe ashanti', 'africa-oeste', [1500, 2000], 'm', 'alta', 'kente collares-zulu brazaletes sandalias-cuero', { A: 'azafran rojo-cartamo verde-hoja', B: 'indigo-profundo', C: 'oro', D: 'oro' }, { peinados: 'rapado corto', tocados: 'corona ninguno', pieles: 'ebano-claro ebano' }),
  A('africa-mujer-kente', 'Mujer con kente', 'africa-oeste', [1500, 2000], 'f', 'alta', 'wrap-top kente-falda collar-cuentas sandalias-cuero', { A: 'azafran rojo-cartamo verde-hoja', B: 'indigo-profundo', C: 'oro', D: 'oro' }, { peinados: 'trenzas-dos rizos', tocados: 'panuelo ninguno', pieles: 'ebano-claro ebano ebano-profundo' }),
  A('maasai', 'Maasái', 'africa-este-sur', [1600, 2000], 'm', 'media', 'shuka collares-zulu brazaletes-altos sandalias-cuero', { A: 'rojo-cartamo escarlata azul-ultramar', B: 'azul-ultramar lana-negra', C: 'rojo-cartamo', D: 'plata' }, { peinados: 'rapado rastas', pieles: 'ebano-claro ebano' }),
  A('maasai-mujer', 'Mujer maasái', 'africa-este-sur', [1600, 2000], 'f', 'media', 'shuka collares-zulu brazaletes-altos sandalias-cuero', { A: 'rojo-cartamo azul-ultramar escarlata', B: 'azul-ultramar lana-negra', C: 'rojo-cartamo', D: 'plata' }, { peinados: 'rapado', pieles: 'ebano-claro ebano' }),
  A('zulu-guerrero', 'Guerrero zulú', 'africa-este-sur', [1700, 1900], 'm', 'media', 'taparrabo-zulu collares-zulu brazaletes-altos descalzo', { A: 'piel-guanaco cuero-claro', B: 'cuero-oscuro', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'rapado corto', tocados: 'tocado-plumas ninguno', pieles: 'ebano-claro ebano' }),
  A('zulu-mujer', 'Mujer zulú (representación genérica)', 'africa-este-sur', [1700, 1950], 'f', 'media', 'falda-cuero collar-ancho-cuentas brazaletes-altos descalzo', { A: 'cuero-claro rojo-ocre piel-guanaco', B: 'cuero', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'rapado corto', tocados: 'ninguno', pieles: 'ebano-claro ebano', conf: 'baja', nota: 'Representación genérica: no reproduce el isidwaba, el tocado isicholo ni los códigos de color de las cuentas, que cambian con la edad, el estado civil y la región.' }),
  A('africa-dashiki', 'Dashiki', 'africa-oeste', [1960, 2000], 'm', 'media', 'dashiki pantalon-recto sandalias-cuero', { A: 'naranja-achiote verde-hoja azafran rojo-cartamo', B: 'lana-negra', C: 'lana-negra', D: 'oro' }, { peinados: 'afro rapado', barbas: 'bigote ninguna', tocados: 'kufi ninguno', pieles: 'castana ebano-claro ebano' }),
  A('rafia-ceremonial', 'Danzante con falda de rafia', 'africa-oeste', [1000, 2000], 'm', 'media', 'falda-rafia collares-zulu brazaletes descalzo', { A: 'crudo barro', B: 'rojo-ocre', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'rapado', tocados: 'tocado-plumas ninguno', pieles: 'ebano-claro ebano' }),
  // ===== Asia del Sur =====
  A('india-hombre', 'Hombre de la India', 'asia-sur', [-500, 1950], 'm', 'baja', 'kurta dhoti sandalias-cuero', { A: 'blanco-lino crudo azafran', B: 'crudo', C: 'rojo-granza', D: 'cobre' }, { peinados: 'corto rapado', barbas: 'bigote barba-corta ninguna', tocados: 'turbante ninguno', pieles: 'oliva canela bronce cobre' }),
  A('india-mujer', 'Mujer de la India con sari', 'asia-sur', [-500, 2000], 'f', 'media', 'sari-falda choli sari-pallu brazaletes collar-colgante sandalias-cuero', { A: 'rojo-cartamo azafran verde-esmeralda azul-ultramar malva', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'trenza-una recogido', pieles: 'oliva canela bronce cobre' }),
  A('mogol-noble', 'Noble mogol', 'asia-sur', [1526, 1857], 'm', 'alta', 'kurta sherwani pyjama-indio faja-turbante zapatillas collar-imperial', { A: 'verde-esmeralda azul-ultramar rojo-cochinilla marfil', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'corto', barbas: 'barba-corta bigote', tocados: 'turbante', pieles: 'oliva canela' }),
  A('india-campesino-lungi', 'Campesino con lungi', 'asia-sur', [1000, 2000], 'm', 'baja', 'lungi camisa-rotos sandalias-cuero', { A: 'indigo rojo-granza verde-hoja', B: 'blanco-lino', C: 'blanco-lino', D: 'cobre' }, { peinados: 'rapado corto', barbas: 'bigote barba-corta', tocados: 'turbante ninguno', pieles: 'canela bronce cobre' }),
  // ===== Asia oriental =====
  A('japon-samurai', 'Samurái', 'asia-este', [1200, 1870], 'm', 'alta', 'kimono hakama haori obi geta', { A: 'indigo-profundo lana-negra gris-pizarra', B: 'lana-negra', C: 'blanco-lino', D: 'oro' }, { peinados: 'mono-chonmage', barbas: 'ninguna bigote', pieles: 'porcelana clara clara-calida' }),
  A('japon-mujer-kimono', 'Mujer con kimono', 'asia-este', [1200, 1950], 'f', 'alta', 'kimono obi geta', { A: 'rojo-cartamo indigo verde-hoja malva rosa-cochinilla', B: 'blanco-lino', C: 'oro azafran', D: 'oro' }, { peinados: 'recogido mono-alto', pieles: 'porcelana clara clara-calida' }),
  A('japon-campesino', 'Campesino japonés', 'asia-este', [1200, 1950], 'm', 'baja', 'tunica-plebeya pantalon-chino cinturon-cuerda geta', { A: 'indigo indigo-claro crudo', B: 'indigo-claro', C: 'crudo', D: 'cobre' }, { peinados: 'rapado mono-chonmage', tocados: 'sombrero-conico ninguno', pieles: 'clara clara-calida' }),
  A('corea-mujer', 'Mujer coreana con hanbok', 'asia-este', [1400, 1950], 'f', 'media', 'hanbok-jeogori hanbok-chima zapatos-tela', { A: 'rosa-cochinilla verde-agua azul-celeste azafran', B: 'rojo-cartamo indigo', C: 'rojo-cartamo', D: 'oro' }, { peinados: 'recogido trenza-una', pieles: 'porcelana clara clara-calida' }),
  A('corea-hombre', 'Hombre coreano con hanbok', 'asia-este', [1400, 1950], 'm', 'media', 'hanbok-jeogori hanbok-baji zapatos-tela', { A: 'blanco-lino azul-celeste crudo', B: 'indigo', C: 'indigo', D: 'plata' }, { peinados: 'mono-chonmage', barbas: 'bigote barba-corta ninguna', tocados: 'ninguno', pieles: 'porcelana clara clara-calida' }),
  A('china-hanfu-hombre', 'Letrado chino', 'asia-este', [-200, 1640], 'm', 'alta', 'hanfu-pao faja-turbante zapatos-tela', { A: 'azul-celeste verde-jade rojo-cochinilla', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'mono-alto', barbas: 'barba-corta candado', pieles: 'clara clara-calida' }),
  A('china-hanfu-mujer', 'Dama china con hanfu', 'asia-este', [-200, 1640], 'f', 'alta', 'hanfu-ruqun faja-turbante zapatos-tela', { A: 'rosa-cochinilla verde-jade azul-celeste rojo-cartamo', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'recogido mono-alto', pieles: 'porcelana clara clara-calida' }),
  A('china-qing-campesino', 'Campesino chino', 'asia-este', [1640, 1930], 'm', 'baja', 'chaqueta-mao pantalon-chino zapatos-tela', { A: 'indigo crudo lana-negra', B: 'crudo', C: 'indigo', D: 'cobre' }, { peinados: 'trenza-una rapado', tocados: 'sombrero-conico ninguno', pieles: 'clara clara-calida oliva-clara' }),
  A('china-cheongsam', 'Mujer con qipao', 'asia-este', [1920, 1960], 'f', 'media', 'cheongsam zapatos-tela', { A: 'rojo-cartamo verde-jade azul-celeste malva', B: 'blanco-lino', C: 'oro', D: 'oro' }, { peinados: 'bob recogido', pieles: 'porcelana clara clara-calida' }),
  // ===== Sudeste asiático y Polinesia =====
  A('sudeste-mujer-kebaya', 'Mujer con kebaya y sarong', 'sudeste-asiatico', [1400, 1980], 'f', 'media', 'kebaya sarong sandalias-cuero collar-colgante', { A: 'verde-jade rojo-cartamo azul-celeste malva', B: 'indigo-profundo rojo-granza', C: 'oro', D: 'oro' }, { peinados: 'recogido mono-alto', pieles: 'oliva canela bronce' }),
  A('sudeste-hombre-sarong', 'Hombre con baju y sarong', 'sudeste-asiatico', [1400, 1980], 'm', 'media', 'baju-kurung sarong sandalias-cuero', { A: 'indigo verde-hoja rojo-granza', B: 'rojo-granza azafran', C: 'oro', D: 'oro' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'kufi ninguno', pieles: 'oliva canela bronce' }),
  A('polinesia-hombre', 'Hombre polinesio', 'polinesia', [-1000, 1900], 'm', 'media', 'lava-lava tapa-manto guirnalda descalzo', { A: 'crudo barro rojo-ocre', B: 'rojo-ocre lana-negra', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'corto mono-alto', tocados: 'tocado-plumas ninguno', pieles: 'canela bronce cobre' }),
  A('polinesia-mujer', 'Mujer polinesia', 'polinesia', [-1000, 1900], 'f', 'media', 'top-fibra tapa-pareo guirnalda descalzo', { A: 'crudo barro rojo-ocre', B: 'rojo-ocre lana-negra', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'largo-ondulado trenza-una', tocados: 'diadema ninguno', pieles: 'canela bronce cobre' }),
  A('rapa-nui-hombre', 'Hombre rapanui', 'rapa-nui', [800, 1900], 'm', 'media', 'maro tapa-manto guirnalda descalzo', { A: 'crudo barro rojo-ocre', B: 'rojo-ocre', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'corto mono-alto', barbas: 'barba-corta ninguna', tocados: 'tocado-plumas ninguno', pieles: 'canela bronce cobre' }),
  A('rapa-nui-mujer', 'Mujer rapanui', 'rapa-nui', [800, 1900], 'f', 'media', 'top-fibra tapa-pareo guirnalda descalzo', { A: 'crudo barro rojo-ocre', B: 'rojo-ocre', C: 'rojo-ocre', D: 'rojo-cartamo' }, { peinados: 'largo-ondulado trenza-una', tocados: 'diadema ninguno', pieles: 'canela bronce cobre' }),
  // ===== Andes =====
  A('inca-hombre', 'Hombre inca', 'andes', [1200, 1560], 'm', 'media', 'unku chumpi sandalias-cuero brazaletes', { A: 'rojo-cochinilla indigo crudo lana-parda', B: 'azafran crudo', C: 'azafran', D: 'oro' }, { peinados: 'flequillo corto', tocados: 'gorro-lana ninguno', pieles: 'canela bronce cobre' }),
  A('inca-noble', 'Noble inca', 'andes', [1300, 1560], 'm', 'alta', 'unku chumpi tilmatli sandalias-cuero brazaletes-altos collar-colgante', { A: 'rojo-cochinilla azul-andino crudo', B: 'azafran', C: 'oro', D: 'oro' }, { peinados: 'flequillo', tocados: 'tocado-plumas diadema', pieles: 'canela bronce cobre' }),
  A('inca-mujer', 'Mujer inca', 'andes', [1200, 1560], 'f', 'media', 'aqsu lliclla chumpi sandalias-cuero collar-cuentas', { A: 'rojo-cochinilla azul-andino lana-parda verde-andino', B: 'azafran crudo', C: 'azafran rojo-cochinilla', D: 'plata oro' }, { peinados: 'largo-liso trenzas-dos', tocados: 'diadema ninguno', pieles: 'canela bronce cobre' }),
  A('andino-hombre', 'Campesino andino', 'andes', [1550, 2000], 'm', 'baja', 'camisa-rotos pantalon-recto poncho-andino sandalias-cuero', { A: 'rojo-cochinilla azul-andino lana-parda alpaca-cafe', B: 'azafran verde-andino', C: 'crudo', D: 'cobre' }, { peinados: 'corto flequillo', tocados: 'gorro-lana sombrero-bombin-andino', pieles: 'canela bronce cobre' }),
  A('andina-mujer', 'Mujer andina con pollera', 'andes', [1550, 2000], 'f', 'baja', 'blusa-campesina pollera lliclla sandalias-cuero collar-cuentas', { A: 'rojo-cochinilla azul-andino verde-andino malva', B: 'azafran rosa-cochinilla', C: 'azafran', D: 'oro' }, { peinados: 'trenzas-dos', tocados: 'sombrero-bombin-andino ninguno', pieles: 'canela bronce cobre' }),
  // ===== Mesoamérica =====
  A('maya-mujer', 'Mujer maya', 'mesoamerica', [-500, 2000], 'f', 'media', 'huipil enagua-maya collar-cuentas descalzo', { A: 'blanco-lino algodon-crudo rojo-cochinilla', B: 'azul-egipcio rojo-cochinilla', C: 'azul-egipcio azafran', D: 'verde-jade' }, { peinados: 'trenzas-dos largo-liso', tocados: 'diadema ninguno', pieles: 'canela bronce cobre' }),
  A('maya-noble', 'Noble maya', 'mesoamerica', [-300, 900], 'm', 'alta', 'maxtlatl tilmatli sandalias-cuero brazaletes-altos collar-usej', { A: 'azul-egipcio rojo-cochinilla blanco-lino', B: 'verde-jade', C: 'verde-jade', D: 'verde-jade' }, { peinados: 'largo-liso', tocados: 'tocado-plumas diadema', pieles: 'canela bronce cobre' }),
  A('azteca-hombre', 'Hombre azteca', 'mesoamerica', [1300, 1521], 'm', 'media', 'maxtlatl tilmatli sandalias-cuero', { A: 'blanco-lino algodon-crudo', B: 'rojo-cochinilla azul-egipcio', C: 'rojo-cochinilla', D: 'verde-jade' }, { peinados: 'flequillo corto', tocados: 'ninguno', pieles: 'canela bronce cobre' }),
  A('azteca-guerrero', 'Guerrero azteca', 'mesoamerica', [1300, 1521], 'm', 'alta', 'maxtlatl tilmatli sandalias-cuero brazaletes-altos collar-usej', { A: 'naranja-achiote rojo-cochinilla azul-egipcio', B: 'verde-jade', C: 'verde-jade', D: 'oro' }, { peinados: 'flequillo', tocados: 'tocado-plumas', pieles: 'canela bronce cobre' }),
  A('azteca-mujer', 'Mujer azteca', 'mesoamerica', [1300, 1521], 'f', 'media', 'huipil-largo cueitl collar-cuentas descalzo', { A: 'blanco-lino algodon-crudo azul-egipcio', B: 'rojo-cochinilla', C: 'rojo-cochinilla', D: 'verde-jade' }, { peinados: 'coletas corona-trenzas', tocados: 'ninguno', pieles: 'canela bronce cobre' }),
  // ===== Norteamérica y Ártico =====
  A('llanuras-hombre', 'Hombre de las llanuras', 'norteamerica', [1500, 1900], 'm', 'media', 'camisa-gamuza leggings-gamuza mocasines cinturon-cuero collar-cuentas', { A: 'cuero-claro piel-guanaco', B: 'rojo-cartamo azul-ultramar', C: 'rojo-cartamo', D: 'azul-ultramar' }, { peinados: 'trenzas-dos largo-liso', tocados: 'tocado-plumas diadema ninguno', pieles: 'canela bronce cobre' }),
  A('llanuras-mujer', 'Mujer de las llanuras', 'norteamerica', [1500, 1900], 'f', 'media', 'vestido-cuero-flecos leggings-gamuza mocasines collar-cuentas', { A: 'cuero-claro piel-guanaco', B: 'rojo-cartamo azul-ultramar', C: 'rojo-cartamo', D: 'azul-ultramar' }, { peinados: 'trenzas-dos largo-liso', tocados: 'diadema ninguno', pieles: 'canela bronce cobre' }),
  A('pueblo-hombre', 'Hombre pueblo', 'norteamerica', [1000, 1900], 'm', 'media', 'tunica-plebeya pantalon-recto manta-pueblo mocasines collar-cuentas', { A: 'algodon-crudo blanco-lino', B: 'rojo-cartamo indigo', C: 'indigo', D: 'turquesa' }, { peinados: 'largo-liso corto', tocados: 'diadema ninguno', pieles: 'canela bronce cobre' }),
  A('inuit', 'Inuit con parka', 'artico', [-1000, 1950], 'm', 'baja', 'parka pantalon-piel botas-piel', { A: 'piel-guanaco cuero lana-parda', B: 'piel-nutria', C: 'piel-nutria', D: 'crudo' }, { peinados: 'corto largo-liso', tocados: 'capucha ninguno', pieles: 'clara-calida oliva-clara oliva' }),
  A('inuit-mujer', 'Mujer inuit con parka', 'artico', [-1000, 1950], 'f', 'baja', 'parka pantalon-piel botas-piel', { A: 'piel-guanaco cuero lana-parda', B: 'piel-nutria', C: 'piel-nutria', D: 'crudo' }, { peinados: 'trenzas-dos largo-liso', tocados: 'capucha ninguno', pieles: 'clara-calida oliva-clara oliva' }),
  // ===== Chile y el cono sur =====
  A('mapuche-mujer', 'Mujer mapuche', 'mapuche', [1500, 1950], 'f', 'media', 'chamal trariwe ukulla trapelacucha collar-cuentas descalzo', { A: 'lana-negra azul-andino', B: 'rojo-mapuche', C: 'rojo-mapuche', D: 'plata' }, { peinados: 'trenzas-dos', tocados: 'trarilonko', pieles: 'canela bronce cobre' }),
  A('mapuche-hombre', 'Hombre mapuche', 'mapuche', [1500, 1950], 'm', 'media', 'camisa-rotos chiripa makun trariwe descalzo', { A: 'lana-negra alpaca-cafe lana-parda', B: 'rojo-mapuche', C: 'rojo-mapuche', D: 'plata' }, { peinados: 'largo-liso flequillo', tocados: 'trarilonko ninguno', pieles: 'canela bronce cobre' }),
  A('huaso', 'Huaso chileno', 'chile-rural', [1750, 1990], 'm', 'media', 'camisa-rotos chaqueta-huasa manta-huasa pantalon-ajustado faja-huasa botas-cuero', { A: 'rojo-tierra lana-negra azul-plomo tabaco', B: 'blanco-lino rojo-granza', C: 'rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'chupalla', pieles: 'clara-calida oliva-clara oliva' }),
  A('huasa', 'Huasa chilena', 'chile-rural', [1750, 1990], 'f', 'media', 'blusa-campesina pollera-huacha chal-lana zapatos-cuero', { A: 'rojo-granza azul-prusia verde-hoja', B: 'blanco-lino', C: 'blanco-lino', D: 'plata' }, { peinados: 'trenzas-dos recogido', tocados: 'chupalla ninguno', pieles: 'clara-calida oliva-clara oliva' }),
  A('roto-chileno', 'Roto chileno del siglo XIX', 'chile-rural', [1800, 1920], 'm', 'baja', 'camisa-rotos chaleco-corto pantalon-recto faja-huasa descalzo', { A: 'lana-parda umbra gris-pizarra', B: 'crudo', C: 'rojo-granza', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha gorra-plana', pieles: 'clara-calida oliva-clara oliva canela' }),
  A('chilote', 'Campesino chilote', 'chile-rural', [1750, 1980], 'm', 'baja', 'camisa-rotos poncho-liso pantalon-recto botas-cuero', { A: 'lana-parda alpaca-cafe lana-negra', B: 'crudo', C: 'rojo-tierra', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote barba-corta ninguna', tocados: 'gorro-lana boina', pieles: 'clara clara-calida oliva-clara' }),
  A('chilota', 'Campesina chilota', 'chile-rural', [1750, 1980], 'f', 'baja', 'vestido-sencillo chal-lana delantal-blanco zuecos', { A: 'lana-parda alpaca-gris indigo', B: 'crudo', C: 'rojo-tierra', D: 'cobre' }, { peinados: 'trenza-una recogido', tocados: 'panuelo ninguno', pieles: 'clara clara-calida oliva-clara' }),
  A('selknam-cazador', 'Cazador selk\'nam', 'patagonia-fueguina', [-6000, 1930], 'm', 'media', 'quillango taparrabo-cuero botas-piel', { A: 'piel-guanaco cuero-claro', B: 'cuero-oscuro', C: 'rojo-ocre', D: 'rojo-ocre' }, { peinados: 'largo-liso corto', tocados: 'sombrero-conico ninguno', pieles: 'canela bronce cobre' }),
  A('selknam-mujer', 'Mujer selk\'nam', 'patagonia-fueguina', [-6000, 1930], 'f', 'media', 'quillango botas-piel cuentas-largas', { A: 'piel-guanaco cuero-claro', B: 'cuero-oscuro', C: 'rojo-ocre', D: 'rojo-ocre' }, { peinados: 'largo-liso', tocados: 'diadema ninguno', pieles: 'canela bronce cobre' }),
  A('gaucho', 'Gaucho', 'rio-de-la-plata', [1750, 1950], 'm', 'baja', 'camisa-rotos chiripa poncho-liso faja-huasa botas-cuero', { A: 'lana-parda rojo-tierra azul-plomo alpaca-cafe', B: 'rojo-granza crudo', C: 'rojo-granza', D: 'plata' }, { peinados: 'corto largo-liso', barbas: 'bigote barba-corta', tocados: 'sombrero-ala-ancha boina', pieles: 'clara-calida oliva-clara oliva' }),
  A('gaucho-bombacha', 'Paisano con bombacha', 'rio-de-la-plata', [1850, 1990], 'm', 'media', 'camisa-blanca chaqueta-huasa bombachas faja-huasa botas-cuero', { A: 'lana-negra gris-pizarra tabaco kaki', B: 'blanco-lino', C: 'rojo-granza', D: 'plata' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'boina sombrero-ala-ancha', pieles: 'clara-calida oliva-clara oliva' }),
  // ===== Hispanoamérica colonial =====
  A('colonial-caballero', 'Caballero colonial', 'hispanoamerica-colonial', [1520, 1810], 'm', 'alta', 'camisa-blanca lazo-cuello chaleco-largo jubon-renacentista calzones-rodilla calzas-largas zapatos-hebilla capa-corta-ribete', { A: 'lana-negra borravino azul-plomo', B: 'blanco-lino', C: 'oro plata', D: 'plata' }, { peinados: 'corto flequillo', barbas: 'candado bigote barba-corta', tocados: 'sombrero-ala-ancha', pieles: 'porcelana clara clara-calida' }),
  A('colonial-dama', 'Dama colonial', 'hispanoamerica-colonial', [1520, 1810], 'f', 'alta', 'vestido-barroco mantilla collar-colgante zapatos-hebilla', { A: 'lana-negra borravino verde-botella azul-prusia', B: 'blanco-lino', C: 'oro plata', D: 'oro' }, { peinados: 'recogido', tocados: 'velo ninguno', pieles: 'porcelana clara clara clara-calida' }),
  A('colonial-mestizo', 'Artesano mestizo colonial', 'hispanoamerica-colonial', [1550, 1820], 'm', 'baja', 'camisa-rotos chaleco-corto calzones-rodilla poncho-liso sandalias-cuero', { A: 'lana-parda nogal indigo alpaca-cafe', B: 'crudo', C: 'rojo-tierra', D: 'cobre' }, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'sombrero-ala-ancha gorro-lana', pieles: 'oliva oliva-clara canela bronce' }),
  A('colonial-mujer-mestiza', 'Mujer mestiza colonial', 'hispanoamerica-colonial', [1550, 1820], 'f', 'baja', 'blusa-campesina falda-larga rebozo delantal-blanco zapatos-cuero', { A: 'azul-andino rojo-granza verde-musgo lana-parda', B: 'blanco-lino', C: 'blanco-lino', D: 'cobre' }, { peinados: 'trenzas-dos recogido', tocados: 'panuelo ninguno', pieles: 'oliva oliva-clara canela bronce' }),
  ...ATUENDOS_AMPLIADOS,
  ...ATUENDOS_SIGLO20,
  ...ATUENDOS_MAS,
  ...ATUENDOS_MODA,
];

const ATUENDOS_POR_ID = Object.fromEntries(ATUENDOS.map((a) => [a.id, a]));

Object.assign(__e, { REGIONES, ATUENDOS, ATUENDOS_POR_ID });
return __e;
})();

__m['cabeza'] = (() => {
const __e = {};
// Cabeza de frente, orejas, rostro opcional, peinados, barbas y tocados.
// Las formas se dibujan en unidades de la cabeza: x en semianchos (1 = sienes), y en alturas de cabeza desde la coronilla.

const { suavizar, espejo, oscurecer, aclarar, mezclar, limitar, lerp } = __m['util'];

const S = (d, fill, extra = {}) => ({ d, fill, ...extra });

function geometriaCabeza(M, rostro = {}) {
  const headH = M.y.menton - M.y.cabeza;
  const ancho = limitar(rostro.ancho ?? 1, 0.85, 1.2);
  const hw = headH * (M.u < 0.5 ? 0.40 : 0.365) * ancho;
  const top = M.y.cabeza;
  const cx = M.cx;
  const P = (xu, yu) => [cx + xu * hw, top + yu * headH];
  /** Contorno cerrado a partir de la mitad derecha en unidades; el primer y último punto van sobre el eje. */
  const E = (mitad) => suavizar(espejo(mitad.map((p) => [p[0] * hw, top + p[1] * headH, p[2]]), cx));
  const C = (xu, yu, rx, ry) => { // elipse (en unidades)
    const [x, y] = P(xu, yu), a = rx * hw, b = ry * headH;
    return suavizar([[x + a, y], [x, y + b], [x - a, y], [x, y - b]]);
  };
  return { M, P, E, C, hw, headH, top, cx, rostro };
}

const MANDIBULAS = {
  ovalada: [[0.74, 0.82], [0.44, 0.96]],
  cuadrada: [[0.84, 0.76], [0.66, 0.93], [0.4, 0.99]],
  redonda: [[0.84, 0.78], [0.58, 0.95]],
  afilada: [[0.64, 0.82], [0.34, 0.97]],
};
const MANDIBULAS_ID = Object.keys(MANDIBULAS);

function trazoCabeza(G) {
  const mand = MANDIBULAS[G.rostro?.mandibula] ?? MANDIBULAS.ovalada;
  return G.E([[0, 0], [0.55, 0.025], [0.9, 0.12], [1.0, 0.3], [0.97, 0.5], [0.92, 0.64], ...mand, [0, 1.0]]);
}

function trazoOrejas(G) {
  const o = (s) => {
    const [x, y] = G.P(s * 1.0, 0.52);
    const a = G.hw * 0.17, b = G.headH * 0.115;
    return suavizar([[x + s * a * 1.3, y - b * 0.2], [x + s * a * 0.4, y + b], [x - s * a * 0.2, y + b * 0.4], [x - s * a * 0.2, y - b * 0.8], [x + s * a * 0.7, y - b]]);
  };
  return [o(1), o(-1)];
}

/** Rasgos suaves, solo si se piden: la silueta por defecto no los lleva. */
function rasgosRostro(G, piel) {
  const osc = oscurecer(piel, 0.42), suave = oscurecer(piel, 0.18);
  const f = [];
  for (const s of [-1, 1]) {
    f.push(S(G.C(s * 0.42, 0.43, 0.15, 0.028), osc, { op: 0.8 }));
    f.push(S(suavizar([G.P(s * 0.62, 0.36), G.P(s * 0.4, 0.34), G.P(s * 0.2, 0.355)], false), 'none', { stroke: oscurecer(piel, 0.5), sw: 1.6, op: 0.55, cap: 'round' }));
  }
  f.push(S(suavizar([G.P(-0.06, 0.45), G.P(-0.1, 0.62), G.P(0, 0.67), G.P(0.1, 0.62), G.P(0.06, 0.45)], false), 'none', { stroke: suave, sw: 1.6, op: 0.7, cap: 'round' }));
  f.push(S(suavizar([G.P(-0.2, 0.79), G.P(0, 0.815), G.P(0.2, 0.79)], false), 'none', { stroke: oscurecer(piel, 0.45), sw: 1.8, op: 0.75, cap: 'round' }));
  return f;
}

// ---------- Cabello ----------

/** Casquete que cubre el cráneo; `lat` es hasta dónde baja por los lados y `frente` dónde queda la línea del pelo. */
function casquete(G, { vol = 0.06, frente = 0.17, lat = 0.34, ancho = 0 } = {}) {
  return G.E([
    [0, -vol * 1.3], [0.55 + ancho * 0.4, -vol * 1.0], [0.95 + ancho * 0.8, 0.07 - vol * 0.4],
    [1.03 + vol + ancho, 0.27], [1.0 + vol * 0.6 + ancho, lat],
    [0.88 + ancho * 0.3, lat - 0.08], [0.8, 0.2], [0.5, frente - 0.01], [0.25, frente + 0.015], [0, frente + 0.02],
  ]);
}

function mechonesLado(G, s, { largo = 1.9, ancho = 0.32, salida = 1.08, onda = 0.04 } = {}) {
  const pts = [
    G.P(s * (0.96), 0.14), G.P(s * (salida + 0.05), 0.45), G.P(s * (salida + onda), 0.9),
    G.P(s * (salida - 0.05 + onda), largo * 0.8), G.P(s * (salida - ancho), largo),
    G.P(s * (0.62), largo * 0.8), G.P(s * 0.9, 0.55),
  ];
  return suavizar(pts, true);
}

function rastas(G, color, { n = 9, largo = 1.7 } = {}) {
  const out = [];
  for (let i = 0; i < n; i++) {
    const t = (i / (n - 1)) * 2 - 1;
    const x0 = t * 0.95, x1 = t * 1.15 + (t < 0 ? -0.05 : 0.05);
    const y0 = 0.1 + Math.abs(t) * 0.1;
    const pts = [G.P(x0, y0), G.P(x1, 0.5), G.P(x1 * 1.04 + (i % 2 ? 0.03 : -0.03), (0.5 + largo) / 2), G.P(x1 * 0.95, largo + (i % 3) * 0.05)];
    out.push(S(suavizar(pts, false), 'none', { stroke: color, sw: Math.max(2, G.hw * 0.13), cap: 'round' }));
  }
  return out;
}

const PEINADOS = {
  calvo: () => ({ atras: [], frente: [] }),
  rapado: (G, c) => ({ atras: [], frente: [S(casquete(G, { vol: 0.012, frente: 0.15, lat: 0.3 }), c, { op: 0.42 })] }),
  corto: (G, c) => ({ atras: [], frente: [S(casquete(G, { vol: 0.05 }), c)] }),
  'corto-raya': (G, c) => ({
    atras: [], frente: [S(casquete(G, { vol: 0.07, frente: 0.19 }), c),
      S(suavizar([G.P(-0.5, 0.02), G.P(-0.35, 0.1), G.P(-0.2, 0.19)], false), 'none', { stroke: oscurecer(c, 0.45), sw: 1.8, op: 0.8, cap: 'round' })],
  }),
  flequillo: (G, c) => ({
    atras: [S(G.E([[0, -0.08], [0.9, 0.0], [1.12, 0.3], [1.1, 0.5], [0.9, 0.6], [0, 0.6]]), c)],
    frente: [S(G.E([[0, -0.08], [0.6, -0.06], [1.0, 0.08], [1.08, 0.3], [1.05, 0.44], [0.85, 0.36], [0.6, 0.26], [0, 0.25]]), c)],
  }),
  romano: (G, c) => ({
    atras: [], frente: [S(G.E([[0, -0.07], [0.6, -0.05], [1.0, 0.1], [1.05, 0.3], [0.95, 0.36], [0.8, 0.22], [0.5, 0.2], [0.2, 0.17], [0, 0.2]]), c)],
  }),
  'melena-media': (G, c) => ({
    atras: [S(mechonesLado(G, 1, { largo: 1.15, ancho: 0.3 }), c), S(mechonesLado(G, -1, { largo: 1.15, ancho: 0.3 }), c), S(G.E([[0, 0], [1, 0.4], [0.9, 1.0], [0, 1.05]]), c)],
    frente: [S(casquete(G, { vol: 0.08, frente: 0.2, lat: 0.4 }), c)],
  }),
  'largo-liso': (G, c) => ({
    atras: [S(G.E([[0, -0.05], [0.95, 0.0], [1.15, 0.5], [1.22, 1.3], [1.2, 2.0], [0, 2.05]]), c)],
    frente: [S(casquete(G, { vol: 0.07, frente: 0.2, lat: 0.42 }), c),
      S(mechonesLado(G, 1, { largo: 2.0, ancho: 0.42, salida: 1.03 }), c), S(mechonesLado(G, -1, { largo: 2.0, ancho: 0.42, salida: 1.03 }), c)],
  }),
  'largo-ondulado': (G, c) => ({
    atras: [S(G.E([[0, -0.05], [0.95, 0.0], [1.2, 0.5], [1.33, 1.3], [1.28, 2.1], [0.6, 2.28], [0, 2.2]]), c)],
    frente: [S(casquete(G, { vol: 0.09, frente: 0.2, lat: 0.42 }), c),
      S(mechonesLado(G, 1, { largo: 2.05, ancho: 0.5, salida: 1.1, onda: 0.12 }), c), S(mechonesLado(G, -1, { largo: 2.05, ancho: 0.5, salida: 1.1, onda: 0.12 }), c)],
  }),
  bob: (G, c) => ({
    atras: [S(G.E([[0, -0.05], [0.95, 0.0], [1.15, 0.4], [1.17, 0.9], [0.9, 1.08], [0, 1.1]]), c)],
    frente: [S(casquete(G, { vol: 0.07, frente: 0.2, lat: 0.45 }), c),
      S(mechonesLado(G, 1, { largo: 1.02, ancho: 0.35, salida: 1.06 }), c), S(mechonesLado(G, -1, { largo: 1.02, ancho: 0.35, salida: 1.06 }), c)],
  }),
  'trenza-una': (G, c) => {
    const [x, y] = G.P(0.95, 0.9);
    const seg = [];
    for (let i = 0; i < 9; i++) seg.push(S(suavizar([[x + G.hw * 0.0, y + i * G.headH * 0.2 + G.headH * 0.55], [x + G.hw * 0.16, y + i * G.headH * 0.2 + G.headH * 0.65], [x, y + i * G.headH * 0.2 + G.headH * 0.75], [x - G.hw * 0.16, y + i * G.headH * 0.2 + G.headH * 0.65]]), c, { stroke: oscurecer(c, 0.35), sw: 1 }));
    return { atras: [], frente: [S(casquete(G, { vol: 0.06, frente: 0.2, lat: 0.45 }), c), ...seg] };
  },
  'trenzas-dos': (G, c) => {
    const seg = [];
    for (const s of [-1, 1]) {
      const [x, y] = G.P(s * 1.12, 0.8);
      for (let i = 0; i < 10; i++) {
        const yy = y + i * G.headH * 0.2;
        seg.push(S(suavizar([[x, yy], [x + G.hw * 0.16, yy + G.headH * 0.1], [x, yy + G.headH * 0.22], [x - G.hw * 0.16, yy + G.headH * 0.1]]), c, { stroke: oscurecer(c, 0.35), sw: 1 }));
      }
    }
    return { atras: [], frente: [S(casquete(G, { vol: 0.07, frente: 0.2, lat: 0.5 }), c), S(mechonesLado(G, 1, { largo: 0.85, ancho: 0.2, salida: 1.05 }), c), S(mechonesLado(G, -1, { largo: 0.85, ancho: 0.2, salida: 1.05 }), c), ...seg] };
  },
  'mono-alto': (G, c) => ({
    atras: [S(G.C(0, -0.2, 0.34, 0.2), c)],
    frente: [S(casquete(G, { vol: 0.05, frente: 0.15, lat: 0.3 }), c)],
  }),
  'mono-chonmage': (G, c) => ({
    atras: [S(suavizar([G.P(-0.1, -0.02), G.P(0.5, -0.18), G.P(0.75, -0.3), G.P(0.45, -0.25), G.P(0.1, -0.08)]), c)],
    frente: [S(casquete(G, { vol: 0.02, frente: 0.1, lat: 0.3 }), c)],
  }),
  recogido: (G, c) => ({
    atras: [S(G.C(0, -0.1, 0.45, 0.2), c)],
    frente: [S(casquete(G, { vol: 0.09, frente: 0.19, lat: 0.5, ancho: 0.04 }), c)],
  }),
  afro: (G, c) => ({
    atras: [S(G.E([[0, -0.55], [0.8, -0.45], [1.5, -0.05], [1.65, 0.4], [1.45, 0.8], [0, 0.75]]), c)],
    frente: [S(G.E([[0, -0.46], [0.75, -0.38], [1.3, -0.04], [1.35, 0.32], [1.1, 0.35], [0.95, 0.15], [0.6, 0.0], [0, 0.0]]), c)],
  }),
  rizos: (G, c) => {
    const bolas = [];
    const pos = [[0, -0.1, 0.4], [-0.55, -0.02, 0.38], [0.55, -0.02, 0.38], [-0.95, 0.12, 0.34], [0.95, 0.12, 0.34], [-1.12, 0.38, 0.28], [1.12, 0.38, 0.28], [-0.3, 0.12, 0.3], [0.3, 0.12, 0.3]];
    for (const [x, y, r] of pos) bolas.push(S(G.C(x, y, r * 0.55, r * 0.5), c));
    return { atras: [S(casquete(G, { vol: 0.12, frente: 0.14, lat: 0.45, ancho: 0.1 }), c)], frente: bolas };
  },
  rastas: (G, c) => ({ atras: [], frente: [S(casquete(G, { vol: 0.06, frente: 0.15, lat: 0.35 }), c), ...rastas(G, c)] }),
  'corona-trenzas': (G, c) => ({
    atras: [],
    frente: [S(casquete(G, { vol: 0.05, frente: 0.19, lat: 0.35 }), c),
      S(suavizar([G.P(-1.0, 0.2), G.P(-0.6, 0.03), G.P(0, -0.02), G.P(0.6, 0.03), G.P(1.0, 0.2)], false), 'none', { stroke: c, sw: G.headH * 0.14, cap: 'round' }),
      S(suavizar([G.P(-1.0, 0.2), G.P(-0.6, 0.03), G.P(0, -0.02), G.P(0.6, 0.03), G.P(1.0, 0.2)], false), 'none', { stroke: oscurecer(c, 0.3), sw: 1, dash: '3 5', cap: 'round' })],
  }),
  coletas: (G, c) => {
    const bolsa = (s) => { const [x, y] = G.P(s * 1.3, 0.55); return S(suavizar([[x, y - G.headH * 0.12], [x + s * G.hw * 0.3, y + G.headH * 0.35], [x + s * G.hw * 0.12, y + G.headH * 1.05], [x - s * G.hw * 0.2, y + G.headH * 0.5]]), c); };
    return { atras: [], frente: [S(casquete(G, { vol: 0.06, frente: 0.19, lat: 0.4 }), c), bolsa(1), bolsa(-1)] };
  },
  tonsura: (G, c) => ({
    atras: [],
    frente: [S(G.E([[0, 0.12], [0.8, 0.11], [1.04, 0.3], [1.0, 0.4], [0.82, 0.3], [0.6, 0.16], [0.0, 0.14]]), c)],
  }),
  'peluca-egipcia': (G, c) => ({
    atras: [S(G.E([[0, -0.06], [0.95, 0.0], [1.22, 0.4], [1.28, 1.2], [1.3, 1.5], [0, 1.55]]), c)],
    frente: [S(casquete(G, { vol: 0.08, frente: 0.2, lat: 0.4 }), c),
      S(mechonesLado(G, 1, { largo: 1.5, ancho: 0.45, salida: 1.12 }), c), S(mechonesLado(G, -1, { largo: 1.5, ancho: 0.45, salida: 1.12 }), c)],
  }),
  'peluca-barroca': (G, c) => {
    const rizos = [];
    for (const s of [-1, 1]) for (let i = 0; i < 5; i++) rizos.push(S(G.C(s * (1.15 + (i % 2) * 0.08), 0.35 + i * 0.28, 0.3, 0.17), c, { stroke: oscurecer(c, 0.25), sw: 1 }));
    return { atras: [S(G.E([[0, -0.1], [1, 0], [1.45, 0.8], [1.5, 1.7], [0, 1.8]]), c)], frente: [S(casquete(G, { vol: 0.14, frente: 0.15, lat: 0.4, ancho: 0.06 }), c), ...rizos] };
  },
  'trenzas-pegadas': (G, c) => {
    const rayas = [];
    for (let i = -3; i <= 3; i++) rayas.push(S(suavizar([G.P(i * 0.26, 0.17 - Math.abs(i) * 0.015), G.P(i * 0.3, -0.02), G.P(i * 0.42, -0.1)], false), 'none', { stroke: oscurecer(c, 0.5), sw: 1.6, op: 0.85, cap: 'round' }));
    return { atras: [], frente: [S(casquete(G, { vol: 0.07, frente: 0.15, lat: 0.4 }), c), ...rayas] };
  },
  'monos-laterales': (G, c) => ({
    atras: [S(G.C(-0.95, -0.08, 0.3, 0.24), c), S(G.C(0.95, -0.08, 0.3, 0.24), c)],
    frente: [S(casquete(G, { vol: 0.05, frente: 0.18, lat: 0.36 }), c)],
  }),
  ondas: (G, c) => {
    const o = [];
    // Ondas solo sobre el pelo: en los lados (fuera de la cara) y arriba.
    for (const s of [-1, 1]) for (let i = 0; i < 3; i++) o.push(S(suavizar([G.P(s * 1.02, 0.34 + i * 0.17), G.P(s * 1.15, 0.42 + i * 0.17), G.P(s * 1.04, 0.5 + i * 0.17)], false), 'none', { stroke: oscurecer(c, 0.35), sw: 1.5, op: 0.8, cap: 'round' }));
    o.push(S(suavizar([G.P(-0.7, 0.03), G.P(-0.4, 0.1), G.P(-0.1, 0.05)], false), 'none', { stroke: oscurecer(c, 0.35), sw: 1.4, op: 0.7, cap: 'round' }));
    o.push(S(suavizar([G.P(0.1, 0.05), G.P(0.4, 0.1), G.P(0.7, 0.03)], false), 'none', { stroke: oscurecer(c, 0.35), sw: 1.4, op: 0.7, cap: 'round' }));
    return { atras: [S(G.E([[0, -0.05], [0.95, 0.0], [1.14, 0.4], [1.16, 0.85], [0.8, 0.95], [0, 0.95]]), c)], frente: [S(casquete(G, { vol: 0.08, frente: 0.17, lat: 0.5, ancho: 0.04 }), c), ...o] };
  },
};
const PEINADOS_ID = Object.keys(PEINADOS);

// ---------- Barbas ----------

const BARBAS = {
  ninguna: () => [],
  bigote: (G, c) => [S(suavizar([G.P(-0.42, 0.74), G.P(-0.15, 0.7), G.P(0, 0.72), G.P(0.15, 0.7), G.P(0.42, 0.74), G.P(0.2, 0.775), G.P(0, 0.755), G.P(-0.2, 0.775)]), c)],
  perilla: (G, c) => [S(G.E([[0, 0.83], [0.2, 0.84], [0.28, 0.93], [0.15, 1.04], [0, 1.08]]), c)],
  candado: (G, c) => [S(suavizar([G.P(-0.4, 0.74), G.P(0, 0.71), G.P(0.4, 0.74), G.P(0.3, 0.8), G.P(0, 0.77), G.P(-0.3, 0.8)]), c), S(G.E([[0, 0.83], [0.2, 0.84], [0.28, 0.93], [0.15, 1.04], [0, 1.08]]), c)],
  patillas: (G, c) => [1, -1].map((s) => S(suavizar([G.P(s * 0.97, 0.25), G.P(s * 0.9, 0.5), G.P(s * 0.8, 0.7), G.P(s * 0.7, 0.68), G.P(s * 0.86, 0.45), G.P(s * 0.88, 0.25)]), c)),
  'barba-corta': (G, c) => [S(G.E([[0, 0.7], [0.55, 0.66], [0.9, 0.52], [0.97, 0.55], [0.9, 0.7], [0.72, 0.86], [0.42, 1.0], [0, 1.03]]).replace(/^/, ''), c),
    S(suavizar([G.P(-0.35, 0.745), G.P(0, 0.72), G.P(0.35, 0.745)], false), 'none', { stroke: oscurecer(c, 0.2), sw: 1.5, op: 0.5, cap: 'round' })],
  'barba-larga': (G, c) => [S(G.E([[0, 0.66], [0.5, 0.62], [0.92, 0.5], [1.0, 0.6], [0.95, 0.85], [0.8, 1.2], [0.5, 1.65], [0, 1.9]]), c)],
  'barba-completa': (G, c) => [S(G.E([[0, 0.68], [0.55, 0.64], [0.92, 0.5], [1.0, 0.6], [0.94, 0.85], [0.7, 1.12], [0.35, 1.3], [0, 1.35]]), c)],
};
const BARBAS_ID = Object.keys(BARBAS);

// ---------- Tocados ----------
// colores: { principal, secundario, detalle }

const ala = (G, rx, y, ry = 0.08) => G.C(0, y, rx, ry);

const TOCADOS = {
  ninguno: () => ({ atras: [], frente: [] }),
  'sombrero-ala-ancha': (G, k) => ({ atras: [], frente: [
    S(ala(G, 1.9, 0.14, 0.13), k.principal), S(G.E([[0, -0.46], [0.7, -0.44], [0.95, 0.04], [0.97, 0.14], [0, 0.17]]), k.principal),
    S(G.E([[0, 0.05], [0.96, 0.03], [0.98, 0.14], [0, 0.16]]), k.secundario) ] }),
  'sombrero-copa': (G, k) => ({ atras: [], frente: [
    S(ala(G, 1.35, 0.1, 0.07), k.principal), S(G.E([[0, -0.85], [0.78, -0.85], [0.84, 0.1], [0, 0.12]]), k.principal),
    S(G.E([[0, -0.06], [0.84, -0.04], [0.86, 0.08], [0, 0.1]]), k.secundario) ] }),
  bombin: (G, k) => ({ atras: [], frente: [
    S(ala(G, 1.25, 0.1, 0.08), k.principal), S(G.E([[0, -0.46], [0.7, -0.4], [0.9, -0.1], [0.92, 0.09], [0, 0.11]]), k.principal),
    S(G.E([[0, 0.0], [0.91, 0.0], [0.92, 0.09], [0, 0.11]]), k.secundario) ] }),
  boina: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.3], [0.8, -0.28], [1.3, -0.05], [1.2, 0.17], [0.7, 0.17], [0, 0.15]]), k.principal),
    S(G.C(0, -0.3, 0.08, 0.03), k.secundario)] }),
  'gorra-plana': (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.2], [0.8, -0.2], [1.25, 0.0], [1.2, 0.18], [0, 0.14]]), k.principal),
    S(G.E([[0, 0.1], [0.9, 0.12], [0.95, 0.2], [0, 0.18]]), k.secundario)] }),
  turbante: (G, k) => {
    const caps = [];
    for (let i = 0; i < 5; i++) caps.push(S(suavizar([G.P(-1.15 + i * 0.02, 0.28 - i * 0.12), G.P(-0.5, 0.08 - i * 0.13), G.P(0.5, 0.08 - i * 0.13), G.P(1.15 - i * 0.02, 0.28 - i * 0.12), G.P(0.5, 0.22 - i * 0.12), G.P(-0.5, 0.22 - i * 0.12)]), i % 2 ? k.secundario : k.principal, { stroke: oscurecer(k.principal, 0.3), sw: 1 }));
    return { atras: [], frente: [S(G.E([[0, -0.6], [0.9, -0.5], [1.3, -0.1], [1.25, 0.3], [0, 0.3]]), k.principal), ...caps.reverse()] };
  },
  fez: (G, k) => ({ atras: [], frente: [S(G.E([[0, -0.5], [0.62, -0.48], [0.78, 0.15], [0, 0.17]]), k.principal), S(suavizar([G.P(0, -0.5), G.P(0.3, -0.35), G.P(0.5, 0.0)], false), 'none', { stroke: k.detalle, sw: 3, cap: 'round' })] }),
  kufi: (G, k) => ({ atras: [], frente: [S(G.E([[0, -0.15], [0.7, -0.1], [1.0, 0.1], [0.96, 0.2], [0, 0.17]]), k.principal)] }),
  'gorro-lana': (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.45], [0.5, -0.4], [1.0, -0.05], [1.1, 0.22], [0, 0.22]]), k.principal),
    ...[1, -1].map((s) => S(suavizar([G.P(s * 1.0, 0.25), G.P(s * 1.1, 0.55), G.P(s * 1.0, 0.95), G.P(s * 0.88, 0.58)]), k.principal)),
    S(G.E([[0, 0.1], [1.06, 0.1], [1.1, 0.24], [0, 0.26]]), k.secundario),
    S(G.C(0, -0.48, 0.08, 0.06), k.detalle)] }),
  'gorro-frigio': (G, k) => ({ atras: [], frente: [S(suavizar([G.P(-1.05, 0.25), G.P(-0.9, -0.15), G.P(-0.2, -0.45), G.P(0.5, -0.6), G.P(0.7, -0.45), G.P(0.35, -0.3), G.P(0.95, -0.1), G.P(1.05, 0.25), G.P(0, 0.2)]), k.principal)] }),
  capucha: (G, k) => ({
    atras: [S(G.E([[0, -0.4], [0.9, -0.3], [1.5, 0.3], [1.55, 1.1], [1.7, 1.6], [0, 1.7]]), k.principal)],
    frente: [S(suavizar([G.P(-1.55, 1.3), G.P(-1.4, 0.2), G.P(-0.9, -0.3), G.P(0, -0.42), G.P(0.9, -0.3), G.P(1.4, 0.2), G.P(1.55, 1.3), G.P(1.2, 0.6), G.P(1.1, 0.2), G.P(0.6, 0.0), G.P(0, -0.02), G.P(-0.6, 0.0), G.P(-1.1, 0.2), G.P(-1.2, 0.6)]), k.principal)],
  }),
  velo: (G, k) => ({
    atras: [S(G.E([[0, -0.2], [0.9, -0.1], [1.4, 0.4], [1.6, 1.6], [1.8, 2.6], [0, 2.7]]), k.principal, { op: 0.96 })],
    frente: [S(suavizar([G.P(-1.2, 0.5), G.P(-1.05, 0.0), G.P(-0.5, -0.17), G.P(0, -0.2), G.P(0.5, -0.17), G.P(1.05, 0.0), G.P(1.2, 0.5), G.P(1.0, 0.25), G.P(0.6, 0.12), G.P(0, 0.1), G.P(-0.6, 0.12), G.P(-1.0, 0.25)]), k.principal),
      S(suavizar([G.P(-1.4, 2.5), G.P(-1.1, 1.0), G.P(-1.05, 0.6)], false), 'none', { stroke: k.secundario, sw: 2, op: 0.6 })],
  }),
  panuelo: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.15], [0.9, -0.08], [1.15, 0.3], [1.1, 0.6], [0.95, 0.5], [0.8, 0.25], [0.45, 0.14], [0, 0.12]]), k.principal),
    S(suavizar([G.P(-1.0, 0.18), G.P(-0.4, 0.04), G.P(0.4, 0.04), G.P(1.0, 0.18)], false), 'none', { stroke: k.secundario, sw: 3, op: 0.6 })] }),
  corona: (G, k) => {
    const pts = [];
    for (let i = 0; i < 5; i++) { const x = -0.8 + i * 0.4; pts.push(G.P(x, -0.3), G.P(x + 0.2, -0.05)); }
    return { atras: [], frente: [S(suavizar([G.P(-0.9, 0.12), ...pts, G.P(0.9, 0.12), G.P(0.9, 0.12), G.P(0.6, 0.15), G.P(0, 0.16), G.P(-0.6, 0.15)].map((p, i, a) => (i > 0 && i < a.length - 4 ? [p[0], p[1], true] : p))), k.principal, { stroke: oscurecer(k.principal, 0.35), sw: 1 }),
      S(G.C(0, -0.02, 0.07, 0.05), k.detalle)] };
  },
  diadema: (G, k) => ({ atras: [], frente: [S(suavizar([G.P(-1.0, 0.24), G.P(-0.5, 0.1), G.P(0, 0.07), G.P(0.5, 0.1), G.P(1.0, 0.24)], false), 'none', { stroke: k.principal, sw: G.headH * 0.06, cap: 'round' }), S(G.C(0, 0.07, 0.07, 0.04), k.detalle)] }),
  'tocado-plumas': (G, k) => {
    const f = [];
    for (let i = 0; i < 13; i++) {
      const t = (i / 12) * 2 - 1, ang = t * 1.15;
      const [bx, by] = G.P(t * 0.7, 0.04);
      const L = G.headH * (1.0 + 0.2 * Math.cos(t * 1.4));
      const ex = bx + Math.sin(ang) * L, ey = by - Math.cos(ang) * L;
      f.push(S(suavizar([[bx, by], [(bx + ex) / 2 + 6 * Math.cos(ang), (by + ey) / 2 + 6 * Math.sin(ang)], [ex, ey], [(bx + ex) / 2 - 6 * Math.cos(ang), (by + ey) / 2 - 6 * Math.sin(ang)]]), i % 2 ? k.secundario : k.principal, { stroke: oscurecer(k.principal, 0.3), sw: 1 }));
    }
    return { atras: f, frente: [S(suavizar([G.P(-1.05, 0.2), G.P(-0.5, 0.05), G.P(0.5, 0.05), G.P(1.05, 0.2), G.P(0.5, 0.14), G.P(-0.5, 0.14)]), k.detalle)] };
  },
  'casco-romano': (G, k) => ({
    atras: [S(suavizar([G.P(-0.05, -0.5), G.P(0, -0.7), G.P(0.05, -0.5)]), k.secundario), S(G.E([[0, -0.62], [0.12, -0.6], [0.14, -0.3], [0, -0.28]]), k.secundario)],
    frente: [S(G.E([[0, -0.22], [0.6, -0.2], [1.0, 0.0], [1.12, 0.4], [1.05, 0.7], [0.95, 0.55], [0.85, 0.2], [0, 0.18]]), k.principal),
      S(G.E([[0, 0.1], [0.95, 0.1], [0.97, 0.18], [0, 0.2]]), k.detalle)],
  }),
  'casco-nasal': (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.3], [0.6, -0.26], [1.0, 0.0], [1.06, 0.3], [0.98, 0.4], [0.82, 0.22], [0, 0.2]]), k.principal),
    S(G.E([[0, 0.12], [0.06, 0.12], [0.1, 0.6], [0, 0.66]]), k.principal)] }),
  'sombrero-conico': (G, k) => ({ atras: [], frente: [
    S(suavizar([G.P(-1.9, 0.25), G.P(-0.5, -0.1), G.P(0, -0.85), G.P(0.5, -0.1), G.P(1.9, 0.25), G.P(0, 0.1)]), k.principal, { stroke: oscurecer(k.principal, 0.3), sw: 1 }),
    S(suavizar([G.P(-0.7, 0.03), G.P(0, 0.12), G.P(0.7, 0.03)], false), 'none', { stroke: k.secundario, sw: 2 })] }),
  tricornio: (G, k) => ({ atras: [], frente: [
    S(suavizar([G.P(-1.8, 0.05), G.P(-0.9, -0.35), G.P(0, -0.1), G.P(0.9, -0.35), G.P(1.8, 0.05), G.P(1.0, 0.15), G.P(0, 0.16), G.P(-1.0, 0.15)].map((p, i) => (i === 0 || i === 4 || i === 2 ? [p[0], p[1], true] : p))), k.principal),
    S(suavizar([G.P(-1.7, 0.06), G.P(-0.9, -0.34), G.P(0, -0.09), G.P(0.9, -0.34), G.P(1.7, 0.06)], false), 'none', { stroke: k.detalle, sw: 2 })] }),
  bicornio: (G, k) => ({ atras: [], frente: [
    S(suavizar([[...G.P(-2.0, 0.0), true], G.P(-1.0, -0.6), G.P(0, -0.7), G.P(1.0, -0.6), [...G.P(2.0, 0.0), true], G.P(1.0, 0.1), G.P(0, 0.14), G.P(-1.0, 0.1)]), k.principal),
    S(suavizar([G.P(-1.85, 0.0), G.P(-1.0, -0.55), G.P(0, -0.64), G.P(1.0, -0.55), G.P(1.85, 0.0)], false), 'none', { stroke: k.detalle, sw: 2.5 })] }),
  chupalla: (G, k) => ({ atras: [], frente: [
    S(ala(G, 1.8, 0.14, 0.12), k.principal, { stroke: oscurecer(k.principal, 0.25), sw: 1 }),
    S(G.E([[0, -0.38], [0.68, -0.36], [0.82, 0.04], [0.85, 0.14], [0, 0.17]]), k.principal),
    S(G.E([[0, 0.02], [0.84, 0.03], [0.86, 0.13], [0, 0.16]]), k.secundario)] }),
  'sombrero-bombin-andino': (G, k) => ({ atras: [], frente: [
    S(suavizar([G.P(-0.82, 0.1), G.P(-0.78, -0.2), G.P(0, -0.34), G.P(0.78, -0.2), G.P(0.82, 0.1), G.P(0, 0.14)]), k.principal),
    S(G.E([[0, 0.02], [0.82, 0.02], [0.82, 0.1], [0, 0.14]]), k.secundario)] }),
  birrete: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.15], [0.86, -0.1], [0.98, 0.15], [0, 0.18]]), k.principal),
    S(G.C(0, -0.17, 0.07, 0.05), k.detalle)] }),
  kokoshnik: (G, k) => ({ atras: [], frente: [
    S(suavizar([G.P(-1.0, 0.25), G.P(-0.8, -0.1), G.P(-0.35, -0.38), G.P(0, -0.42), G.P(0.35, -0.38), G.P(0.8, -0.1), G.P(1.0, 0.25), G.P(0.5, 0.2), G.P(0, 0.18), G.P(-0.5, 0.2)]), k.principal, { stroke: k.detalle, sw: 2 }),
    ...[-0.5, 0, 0.5].map((x) => S(G.C(x, x ? -0.12 : -0.2, 0.07, 0.05), k.detalle))] }),
  nemes: (G, k) => ({
    atras: [S(G.E([[0, -0.1], [0.95, 0.0], [1.1, 0.6], [1.0, 1.5], [0.72, 1.5], [0, 1.45]]), k.principal)],
    frente: [S(casquete(G, { vol: 0.1, frente: 0.14, lat: 0.5, ancho: 0.1 }), k.principal),
      ...[0.15, 0.05, -0.05].map((o, i) => S(suavizar([G.P(-1.05, 0.05 + i * 0.1), G.P(0, -0.1 + i * 0.1 + o * 0), G.P(1.05, 0.05 + i * 0.1)], false), 'none', { stroke: k.secundario, sw: 3, op: 0.9 }))],
  }),
  trarilonko: (G, k) => ({ atras: [], frente: [
    S(suavizar([G.P(-1.02, 0.28), G.P(-0.5, 0.08), G.P(0, 0.05), G.P(0.5, 0.08), G.P(1.02, 0.28)], false), 'none', { stroke: k.principal, sw: G.headH * 0.08, cap: 'round' }),
    ...[-0.8, -0.4, 0, 0.4, 0.8].map((x) => S(G.C(x, 0.15 + Math.abs(x) * 0.12, 0.07, 0.07), k.secundario, { stroke: oscurecer(k.secundario, 0.3), sw: 0.8 }))] }),
  ushanka: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.4], [0.7, -0.35], [1.2, 0.0], [1.28, 0.3], [1.1, 0.55], [1.0, 0.1], [0.9, 0.2], [0, 0.2]]), k.principal),
    S(G.E([[0, -0.4], [0.7, -0.35], [1.2, 0.0], [1.28, 0.3], [1.1, 0.55], [1.0, 0.1], [0.9, 0.2], [0, 0.2]]), k.secundario, { op: 0.55 })] }),
  kufiya: (G, k) => ({
    atras: [S(G.E([[0, -0.3], [0.95, -0.2], [1.35, 0.5], [1.42, 1.35], [0, 1.45]]), k.principal)],
    frente: [S(G.E([[0, -0.34], [0.9, -0.26], [1.25, 0.1], [1.12, 0.32], [0.85, 0.2], [0, 0.2]]), k.principal),
      S(suavizar([G.P(-1.12, 0.22), G.P(-0.5, 0.06), G.P(0.5, 0.06), G.P(1.12, 0.22)], false), 'none', { stroke: k.detalle, sw: G.headH * 0.06, cap: 'round' }),
      S(suavizar([G.P(-1.2, 0.5), G.P(-1.3, 1.2)], false), 'none', { stroke: k.secundario, sw: 2, op: 0.8 })],
  }),
  'gorro-mongol': (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.52], [0.6, -0.46], [0.96, -0.04], [0, 0.04]]), k.principal),
    S(G.E([[0, -0.05], [1.2, 0.0], [1.38, 0.26], [0.95, 0.22], [0, 0.18]]), k.secundario),
    S(G.C(0, -0.55, 0.08, 0.06), k.detalle)] }),
  kalpak: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.82], [0.8, -0.8], [0.9, 0.1], [0, 0.14]]), k.principal),
    S(G.E([[0, -0.84], [0.82, -0.82], [0.8, -0.6], [0, -0.58]]), k.secundario),
    S(G.E([[0, 0.0], [0.92, 0.0], [0.93, 0.13], [0, 0.15]]), k.secundario, { op: 0.8 })] }),
  'corona-flores': (G, k) => {
    const f = [];
    for (let i = 0; i < 9; i++) {
      const a = Math.PI * (0.05 + (i / 8) * 0.9), x = -Math.cos(a) * 1.0, y = 0.2 - Math.sin(a) * 0.3;
      f.push(S(G.C(x, y, 0.13, 0.1), i % 2 ? k.secundario : k.detalle, { stroke: oscurecer(k.principal, 0.2), sw: 0.8 }));
    }
    return { atras: [], frente: [S(suavizar([G.P(-1.02, 0.28), G.P(-0.6, -0.02), G.P(0, -0.12), G.P(0.6, -0.02), G.P(1.02, 0.28)], false), 'none', { stroke: k.principal, sw: 4, cap: 'round' }), ...f] };
  },
  gele: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.85], [0.9, -0.78], [1.55, -0.3], [1.6, 0.15], [1.1, 0.24], [0, 0.22]]), k.principal),
    S(suavizar([G.P(-1.2, 0.0), G.P(-0.3, -0.5), G.P(0.5, -0.65), G.P(1.2, -0.2)], false), 'none', { stroke: oscurecer(k.principal, 0.3), sw: 1.6, op: 0.7 }),
    S(suavizar([G.P(-1.3, 0.1), G.P(-0.2, -0.3), G.P(0.9, -0.4)], false), 'none', { stroke: oscurecer(k.principal, 0.3), sw: 1.6, op: 0.7 })] }),
  resplandor: (G, k) => {
    const pts = [];
    const n = 26;
    for (let i = 0; i <= n; i++) {
      const a = Math.PI * (i / n), r = i % 2 ? 1.55 : 1.75;
      pts.push(G.P(-Math.cos(a) * r, 0.55 - Math.sin(a) * r * 0.78));
    }
    pts.push(G.P(1.45, 0.9), G.P(-1.45, 0.9));
    return { atras: [S(suavizar(pts, true, 0.6), k.principal, { stroke: oscurecer(k.principal, 0.25), sw: 1 })], frente: [] };
  },
  fedora: (G, k) => ({ atras: [], frente: [
    S(ala(G, 1.35, 0.1, 0.075), k.principal), S(G.E([[0, -0.52], [0.72, -0.48], [0.86, 0.0], [0.9, 0.09], [0, 0.11]]), k.principal),
    S(suavizar([G.P(-0.2, -0.5), G.P(0, -0.42), G.P(0.2, -0.5)], false), 'none', { stroke: oscurecer(k.principal, 0.4), sw: 2 }),
    S(G.E([[0, 0.0], [0.89, 0.0], [0.9, 0.09], [0, 0.11]]), k.secundario)] }),
  cloche: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.42], [0.7, -0.36], [1.1, 0.05], [1.12, 0.3], [0.75, 0.38], [0, 0.34]]), k.principal),
    S(suavizar([G.P(-1.05, 0.22), G.P(0, 0.3), G.P(1.05, 0.22)], false), 'none', { stroke: k.secundario, sw: 4, cap: 'round' })] }),
  'gorra-visera': (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.3], [0.8, -0.28], [1.12, 0.0], [1.08, 0.17], [0, 0.14]]), k.principal),
    S(G.E([[0, 0.1], [0.95, 0.1], [1.0, 0.3], [0, 0.34]]), k.secundario),
    S(G.C(0, -0.3, 0.07, 0.04), k.detalle)] }),
  'casco-obra': (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.38], [0.7, -0.33], [1.05, 0.0], [1.1, 0.14], [0, 0.14]]), k.principal),
    S(G.E([[0, 0.06], [1.3, 0.1], [1.32, 0.2], [0, 0.2]]), k.principal),
    S(suavizar([G.P(0, -0.38), G.P(0.02, 0.1)], false), 'none', { stroke: oscurecer(k.principal, 0.35), sw: 2 })] }),
  kepi: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.5], [0.75, -0.46], [0.95, 0.1], [0, 0.12]]), k.principal),
    S(G.E([[0, 0.05], [0.95, 0.05], [1.0, 0.2], [0, 0.22]]), k.secundario),
    S(G.E([[0, 0.14], [0.95, 0.16], [1.05, 0.32], [0, 0.3]]), k.detalle),
    S(G.C(0, -0.1, 0.07, 0.05), k.detalle)] }),
  canotier: (G, k) => ({ atras: [], frente: [
    S(ala(G, 1.25, 0.1, 0.07), k.principal),
    S(G.E([[0, -0.34], [0.78, -0.33], [0.82, 0.04], [0.85, 0.1], [0, 0.12]]), k.principal),
    S(G.E([[0, 0.0], [0.84, 0.0], [0.85, 0.09], [0, 0.11]]), k.secundario)] }),
  pamela: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.1], [1.0, -0.06], [2.1, 0.12], [2.2, 0.26], [1.2, 0.2], [0, 0.17]]), k.principal),
    S(G.E([[0, -0.4], [0.62, -0.36], [0.8, 0.0], [0.82, 0.14], [0, 0.15]]), k.principal),
    S(G.E([[0, 0.03], [0.8, 0.04], [0.82, 0.14], [0, 0.15]]), k.secundario)] }),
  bucket: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.46], [0.74, -0.42], [0.92, 0.04], [1.0, 0.12], [0, 0.14]]), k.principal),
    S(G.E([[0, 0.06], [1.15, 0.1], [1.45, 0.24], [0, 0.22]]), k.principal),
    S(suavizar([G.P(-0.95, 0.04), G.P(0, 0.1), G.P(0.95, 0.04)], false), 'none', { stroke: oscurecer(k.principal, 0.25), sw: 1.4, op: 0.7 })] }),
  beanie: (G, k) => ({ atras: [], frente: [
    S(G.E([[0, -0.46], [0.6, -0.4], [1.04, -0.02], [1.08, 0.2], [0, 0.22]]), k.principal),
    S(G.E([[0, 0.08], [1.08, 0.1], [1.1, 0.24], [0, 0.26]]), k.secundario),
    S(G.C(0, -0.5, 0.1, 0.07), k.detalle)] }),
};
const TOCADOS_ID = Object.keys(TOCADOS);

/** Atributos de volumen de un cabello para el sombreado: devuelve un color de brillo. */
const brilloCabello = (c) => aclarar(c, 0.18);


Object.assign(__e, { mezclar, lerp, geometriaCabeza, MANDIBULAS_ID, trazoCabeza, trazoOrejas, rasgosRostro, PEINADOS, PEINADOS_ID, BARBAS, BARBAS_ID, TOCADOS, TOCADOS_ID, brilloCabello });
return __e;
})();

__m['patrones'] = (() => {
const __e = {};
// Patrones de tela como <pattern> de SVG. Cada uno recibe dos colores y una escala.

const r = (x, y, w, h, fill, op = 1) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${fill}"${op < 1 ? ` opacity="${op}"` : ''}/>`;

const PATRONES = {
  rayas: (a, b, e) => [12 * e, 12 * e, r(0, 0, 12 * e, 12 * e, a) + r(3 * e, 0, 4 * e, 12 * e, b)],
  'rayas-h': (a, b, e) => [12 * e, 12 * e, r(0, 0, 12 * e, 12 * e, a) + r(0, 3 * e, 12 * e, 4 * e, b)],
  'rayas-finas': (a, b, e) => [6 * e, 6 * e, r(0, 0, 6 * e, 6 * e, a) + r(0, 0, 1.6 * e, 6 * e, b, 0.8)],
  bandas: (a, b, e) => [40 * e, 40 * e, r(0, 0, 40 * e, 40 * e, a) + r(0, 12 * e, 40 * e, 6 * e, b) + r(0, 22 * e, 40 * e, 2 * e, b, 0.8)],
  cuadros: (a, b, e) => [16 * e, 16 * e, r(0, 0, 16 * e, 16 * e, a) + r(0, 0, 8 * e, 16 * e, b, 0.5) + r(0, 0, 16 * e, 8 * e, b, 0.5)],
  tartan: (a, b, e) => [24 * e, 24 * e, r(0, 0, 24 * e, 24 * e, a) + r(0, 0, 10 * e, 24 * e, b, 0.55) + r(0, 0, 24 * e, 10 * e, b, 0.55) + r(15 * e, 0, 2 * e, 24 * e, '#ffffff', 0.45) + r(0, 15 * e, 24 * e, 2 * e, '#ffffff', 0.45)],
  puntos: (a, b, e) => [14 * e, 14 * e, r(0, 0, 14 * e, 14 * e, a) + `<circle cx="${7 * e}" cy="${7 * e}" r="${2.2 * e}" fill="${b}"/>`],
  rombos: (a, b, e) => [20 * e, 20 * e, r(0, 0, 20 * e, 20 * e, a) + `<path d="M${10 * e} ${2 * e}L${18 * e} ${10 * e}L${10 * e} ${18 * e}L${2 * e} ${10 * e}Z" fill="none" stroke="${b}" stroke-width="${1.6 * e}"/>`],
  zigzag: (a, b, e) => [20 * e, 12 * e, r(0, 0, 20 * e, 12 * e, a) + `<path d="M0 ${9 * e}L${5 * e} ${3 * e}L${10 * e} ${9 * e}L${15 * e} ${3 * e}L${20 * e} ${9 * e}" fill="none" stroke="${b}" stroke-width="${2 * e}"/>`],
  ondas: (a, b, e) => [24 * e, 12 * e, r(0, 0, 24 * e, 12 * e, a) + `<path d="M0 ${6 * e}Q${6 * e} ${0} ${12 * e} ${6 * e}T${24 * e} ${6 * e}" fill="none" stroke="${b}" stroke-width="${1.8 * e}"/>`],
  grecas: (a, b, e) => [28 * e, 20 * e, r(0, 0, 28 * e, 20 * e, a) + `<path d="M2 ${16 * e}h${6 * e}v${-8 * e}h${8 * e}v${6 * e}h${-4 * e}v${-2 * e}h${12 * e}" fill="none" stroke="${b}" stroke-width="${2.4 * e}" transform="translate(0 ${2 * e})"/>`],
  escalonado: (a, b, e) => [24 * e, 24 * e, r(0, 0, 24 * e, 24 * e, a) + `<path d="M0 ${24 * e}h${8 * e}v${-8 * e}h${8 * e}v${-8 * e}h${8 * e}" fill="none" stroke="${b}" stroke-width="${3 * e}"/>`],
  flores: (a, b, e) => [22 * e, 22 * e, r(0, 0, 22 * e, 22 * e, a) + [[0, -3], [0, 3], [-3, 0], [3, 0]].map(([dx, dy]) => `<circle cx="${11 * e + dx * e}" cy="${11 * e + dy * e}" r="${2 * e}" fill="${b}"/>`).join('') + `<circle cx="${11 * e}" cy="${11 * e}" r="${1.4 * e}" fill="${a}"/>`],
  cruces: (a, b, e) => [14 * e, 14 * e, r(0, 0, 14 * e, 14 * e, a) + `<path d="M${4 * e} ${4 * e}l${6 * e} ${6 * e}m0 ${-6 * e}l${-6 * e} ${6 * e}" stroke="${b}" stroke-width="${1.6 * e}" fill="none"/>`],
  piel: (a, b, e) => [30 * e, 30 * e, r(0, 0, 30 * e, 30 * e, a) + `<ellipse cx="${8 * e}" cy="${9 * e}" rx="${4 * e}" ry="${2.6 * e}" fill="${b}" opacity=".7"/><ellipse cx="${22 * e}" cy="${20 * e}" rx="${3 * e}" ry="${4 * e}" fill="${b}" opacity=".7"/><ellipse cx="${6 * e}" cy="${24 * e}" rx="${2.4 * e}" ry="${1.8 * e}" fill="${b}" opacity=".6"/>`],
  malla: (a, b, e) => [10 * e, 10 * e, r(0, 0, 10 * e, 10 * e, a) + `<path d="M0 0l${5 * e} ${5 * e}l${5 * e} ${-5 * e}M0 ${10 * e}l${5 * e} ${-5 * e}l${5 * e} ${5 * e}" fill="none" stroke="${b}" stroke-width="${1 * e}" opacity=".7"/>`],
  escamas: (a, b, e) => [14 * e, 10 * e, r(0, 0, 14 * e, 10 * e, a) + `<path d="M0 ${6 * e}Q${3.5 * e} ${12 * e} ${7 * e} ${6 * e}Q${10.5 * e} ${12 * e} ${14 * e} ${6 * e}" fill="none" stroke="${b}" stroke-width="${1.2 * e}" opacity=".8"/>`],
  kene: (a, b, e) => [30 * e, 30 * e, r(0, 0, 30 * e, 30 * e, a) + `<path d="M4 4h${22 * e}v${22 * e}h${-16 * e}v${-14 * e}h${10 * e}v${6 * e}h${-4 * e}" fill="none" stroke="${b}" stroke-width="${2.2 * e}" transform="scale(1)"/>`],
  jaspe: (a, b, e) => [26 * e, 26 * e, r(0, 0, 26 * e, 26 * e, a) + `<path d="M0 ${6 * e}Q${8 * e} ${2 * e} ${13 * e} ${8 * e}T${26 * e} ${6 * e}M0 ${18 * e}Q${9 * e} ${14 * e} ${14 * e} ${20 * e}T${26 * e} ${18 * e}" fill="none" stroke="${b}" stroke-width="${2 * e}" opacity=".5"/>`],
};
const PATRONES_ID = Object.keys(PATRONES);

Object.assign(__e, { PATRONES, PATRONES_ID });
return __e;
})();

__m['cuerpo'] = (() => {
const __e = {};
// Cuerpo de frente: proporciones según edad, sexo y corpulencia, y las piezas de piel.
// Todo se mide en píxeles del lienzo (600 × 1000), con el eje en x = 300 y los pies en y = 980.

const { limitar, lerp, espejo, tubo, suavizar } = __m['util'];

const LIENZO = { ancho: 600, alto: 1000, cx: 300, suelo: 980 };

// Altura media (cm) por edad, para mujer y hombre.
const ALTURA = {
  f: [[2, 86], [5, 108], [8, 127], [11, 143], [14, 156], [17, 160], [20, 161]],
  m: [[2, 87], [5, 109], [8, 128], [11, 143], [14, 160], [17, 171], [20, 174]],
};
// Cuántas cabezas mide el cuerpo, según la edad.
const CABEZAS = [[2, 4.5], [5, 5.7], [8, 6.4], [11, 6.9], [14, 7.2], [18, 7.5]];

function porTabla(tabla, x) {
  if (x <= tabla[0][0]) return tabla[0][1];
  for (let i = 1; i < tabla.length; i++) {
    if (x <= tabla[i][0]) return lerp(tabla[i - 1][1], tabla[i][1], (x - tabla[i - 1][0]) / (tabla[i][0] - tabla[i - 1][0]));
  }
  return tabla[tabla.length - 1][1];
}

// Puntos de referencia verticales (fracción de la altura desde la coronilla): [niño de 2 años, adulto].
const Y = {
  menton: [0.222, 0.133], hombro: [0.30, 0.19], axila: [0.36, 0.245], pecho: [0.385, 0.275],
  cintura: [0.50, 0.385], cadera: [0.535, 0.47], entrepierna: [0.57, 0.50],
  codo: [0.43, 0.352], muneca: [0.54, 0.46], puntaMano: [0.615, 0.55],
  rodilla: [0.77, 0.725], tobillo: [0.95, 0.947],
};
// Semianchos (fracción de la altura): [niño, adulto hombre, adulto mujer].
const A = {
  cuello: [0.032, 0.029, 0.024], hombro: [0.112, 0.132, 0.114], pecho: [0.098, 0.112, 0.104],
  cintura: [0.092, 0.088, 0.075], cadera: [0.090, 0.098, 0.108],
  muslo: [0.045, 0.056, 0.062], rodilla: [0.030, 0.034, 0.034], pantorrilla: [0.028, 0.035, 0.034], tobillo: [0.019, 0.0205, 0.0195],
  brazoSup: [0.022, 0.026, 0.022], codo: [0.018, 0.020, 0.017], muneca: [0.0125, 0.0135, 0.0115],
};

/** Rangos que acepta el cuerpo; el generador y las opciones los validan con estos mismos valores. */
const EDAD_MIN = 2, EDAD_MAX = 100;
const ALTURA_MIN = 70, ALTURA_MAX = 190; // cm
const CORPULENCIA_MIN = 0.6, CORPULENCIA_MAX = 1.6; // si se da como número

const CORPULENCIAS = { delgada: 0.86, media: 1, robusta: 1.2, corpulenta: 1.34 };
const POSTURAS = { firme: 7, relajada: 11, abierta: 22 };

/** Pose simétrica y rígida (la de las primeras versiones). */
const POSE_FIRME = Object.freeze({
  modo: 'firme', cabeza: 0, inclinacion: 0, carga: 0, abre: 1, giro: 10, brazoLibre: 'abajo', angulos: null,
  saludo: { codo: 55, antebrazo: 15, mano: 0 },
  // Articulaciones: hombros inclinados (grados), columna curvada (fracción de la altura), codos y muñecas por lado (grados).
  hombros: 0, columna: 0, codos: { 1: 0, '-1': 0 }, munecas: { 1: 0, '-1': 0 }, cinturaCodo: 1, dedos: 1,
});

const redondear = (v, d = 1) => Math.round(v * 10 ** d) / 10 ** d + 0; // + 0 evita el -0

/**
 * Pose casual y distinta cada vez: la cabeza inclinada, el cuerpo apoyado en una pierna, los pies más o menos abiertos,
 * el brazo libre caído, en la cintura o en el bolsillo, y el saludo con otro ángulo de codo, antebrazo y mano.
 * Además, y siempre levemente: un hombro más alto que el otro, la columna con una curva suave, los codos algo doblados,
 * las muñecas flexionadas y los dedos más o menos abiertos. `intensidad` (0 a 1) escala todas esas variaciones.
 * `carga`: -1 o 1 es el lado (de quien mira) que carga el peso; 0, ambos.
 */
function poseAleatoria(azar, intensidad = 1) {
  const k = limitar(intensidad, 0, 1.5);
  const v = (a, b, d = 1) => redondear(azar.entre(a, b) * k, d);
  return {
    modo: 'casual',
    cabeza: v(-7, 7),
    inclinacion: v(-1.4, 1.4, 2),
    carga: azar.ponderado([[-1, 3], [1, 3], [0, 2]]),
    abre: redondear(1 + (azar.entre(0.85, 1.5) - 1) * k, 2),
    giro: redondear(azar.entre(6, 20)),
    brazoLibre: azar.ponderado([['abajo', 58], ['cintura', 27], ['bolsillo', 15]]),
    angulos: { 1: redondear(azar.entre(5, 17)), '-1': redondear(azar.entre(5, 17)) },
    saludo: { codo: redondear(azar.entre(40, 62)), antebrazo: v(2, 24), mano: v(-16, 16) },
    hombros: v(-2.8, 2.8),
    columna: v(-0.008, 0.008, 4),
    codos: { 1: v(-4, 12), '-1': v(-4, 12) },
    munecas: { 1: v(-14, 14), '-1': v(-14, 14) },
    cinturaCodo: redondear(1 + (azar.entre(0.85, 1.2) - 1) * k, 2),
    dedos: redondear(1 + (azar.entre(0.8, 1.25) - 1) * k, 2),
  };
}

/** Completa una pose parcial con la rígida; sin pose, la rígida. */
function normalizarPose(spec) {
  if (!spec || spec === 'firme') return POSE_FIRME;
  const o = typeof spec === 'object' ? spec : {};
  const porLado = (v, por) => ({ 1: v?.[1] ?? por, '-1': v?.['-1'] ?? por });
  return {
    ...POSE_FIRME, ...o,
    angulos: o.angulos ? porLado(o.angulos, 8) : null,
    codos: porLado(o.codos, 0),
    munecas: porLado(o.munecas, 0),
    saludo: { ...POSE_FIRME.saludo, ...(o.saludo ?? {}) },
  };
}

/**
 * Calcula todas las medidas del cuerpo.
 * opc: { sexo: 'f'|'m', edad (años), corpulencia (nombre o número), altura (cm, opcional), postura }
 */
function medidas(opc = {}) {
  if (opc.sexo !== undefined && opc.sexo !== 'f' && opc.sexo !== 'm') throw new Error(`Sexo no válido: ${JSON.stringify(opc.sexo)}. Valores posibles: 'f' o 'm'.`);
  const sexo = opc.sexo === 'f' ? 'f' : 'm';
  const edad = limitar(Number(opc.edad ?? 30), EDAD_MIN, EDAD_MAX);
  const corp = typeof opc.corpulencia === 'number' ? opc.corpulencia : (CORPULENCIAS[opc.corpulencia] ?? 1);
  const escalaAltura = limitar(Number(opc.escalaAltura ?? 1), 0.8, 1.2);
  const cm = limitar(Number(opc.altura ?? porTabla(ALTURA[sexo], Math.min(edad, 20)) * escalaAltura * (edad > 60 ? 1 - (edad - 60) * 0.0009 : 1)), ALTURA_MIN, ALTURA_MAX);
  const H = cm * 4.9;
  const cabezas = porTabla(CABEZAS, Math.min(edad, 18));
  // u = 0 niño pequeño, 1 adulto (según proporción de la cabeza)
  const u = limitar((cabezas - 4.5) / 3, 0, 1);
  const { cx, suelo } = LIENZO;
  const top = suelo - H;
  const y = {};
  for (const k of Object.keys(Y)) y[k] = top + H * lerp(Y[k][0], Y[k][1], u);
  y.menton = top + H / cabezas;
  y.cabeza = top;
  y.suela = suelo;
  y.cuello = y.menton + (y.hombro - y.menton) * 0.12;

  const esF = sexo === 'f' ? 1 : 0;
  const ancho = (k, factor = 1) => {
    const adulto = lerp(A[k][1], A[k][2], esF);
    return H * lerp(A[k][0], adulto, u) * factor;
  };
  // La corpulencia ensancha tronco y piernas más que el cuello y las muñecas; los mayores pierden hombro.
  const mayor = limitar((edad - 55) / 40, 0, 1);
  const c = (extra = 1) => 1 + (corp - 1) * extra;
  const w = {
    cuello: ancho('cuello', c(0.4)),
    hombro: ancho('hombro', c(0.45) * (1 - 0.04 * mayor)),
    pecho: ancho('pecho', c(0.9)),
    cintura: ancho('cintura', c(1.35) * (1 + 0.06 * mayor * (corp > 0.95 ? 1 : 0))),
    cadera: ancho('cadera', c(1.0)),
    muslo: ancho('muslo', c(1.1)),
    rodilla: ancho('rodilla', c(0.6)),
    pantorrilla: ancho('pantorrilla', c(0.8)),
    tobillo: ancho('tobillo', c(0.3)),
    brazoSup: ancho('brazoSup', c(1.0)),
    codo: ancho('codo', c(0.7)),
    muneca: ancho('muneca', c(0.35)),
  };

  const grados0 = typeof opc.postura === 'number' ? opc.postura : (POSTURAS[opc.postura] ?? POSTURAS.firme);
  const pose = normalizarPose(opc.pose);

  // Brazos. Las coordenadas son relativas al eje (x positiva hacia afuera); `lado()` las espeja según el lado.
  const brazoY0 = y.hombro + H * 0.02;
  const S0 = [w.hombro - w.brazoSup * 0.95, brazoY0];
  const anchosBrazo = [w.brazoSup * 1.08, w.brazoSup, w.codo, ((w.codo + w.muneca) / 2) * 1.04, w.muneca];
  const uBrazo = [brazoY0, (brazoY0 + y.codo) / 2, y.codo, (y.codo + y.muneca) / 2, y.muneca];
  const unit = (v) => { const l = Math.hypot(v[0], v[1]) || 1; return [v[0] / l, v[1] / l]; };
  const mitad = (p, q) => [(p[0] + q[0]) / 2, (p[1] + q[1]) / 2];

  // Brazo caído: el codo puede doblarse hacia afuera (`bend`, grados) y la muñeca flexionarse (`flex`).
  const rad = (g) => (g * Math.PI) / 180;
  const brazoCaido = (grados, bend = 0, flex = 0) => {
    const a1 = rad(grados), a2 = rad(grados + bend);
    const codo = [S0[0] + (y.codo - brazoY0) * Math.tan(a1), y.codo];
    const mun = [codo[0] + (y.muneca - y.codo) * Math.tan(a2), y.muneca];
    const m1 = mitad(S0, codo), m2 = mitad(codo, mun);
    const dd = bend > 3 ? H * 0.003 : 0; // el antebrazo abulta un poco al doblar
    const linea = [S0, [m1[0], m1[1]], codo, [m2[0] + dd, m2[1]], mun];
    return { tipo: 'caido', linea, anchos: anchosBrazo, u: uBrazo, mano: { ang: grados + bend + flex } };
  };
  // Mano en la cintura: codo hacia afuera y muñeca sobre la cadera.
  const brazoCintura = () => {
    const codo = [S0[0] + H * 0.064 * pose.cinturaCodo, lerp(y.axila, y.cintura, 0.5)];
    const mun = [w.cadera * 0.98 + H * 0.004, y.cintura + (y.cadera - y.cintura) * 0.72];
    const m1 = mitad(S0, codo), m2 = mitad(codo, mun);
    const dir = unit([mun[0] - m2[0], mun[1] - m2[1]]);
    return { tipo: 'cintura', linea: [S0, [m1[0] + H * 0.006, m1[1]], codo, m2, mun], anchos: anchosBrazo, u: uBrazo, manoDir: dir, manoFlex: pose.munecas[ladoLibre] ?? 0 };
  };
  // Mano en el bolsillo: el antebrazo baja hacia la cadera y la mano no se ve.
  const brazoBolsillo = () => {
    const codo = [S0[0] + H * 0.03, lerp(y.axila, y.cintura, 0.75)];
    const mun = [w.cadera * 0.92, y.cadera + H * 0.012];
    const m1 = mitad(S0, codo), m2 = mitad(codo, mun);
    return { tipo: 'bolsillo', linea: [S0, m1, codo, m2, mun], anchos: anchosBrazo, u: uBrazo };
  };
  // Saludo: el codo baja `codo` grados bajo la horizontal y el antebrazo sube inclinado `antebrazo` grados hacia afuera.
  const brazoSaludo = () => {
    const Lu = H * 0.16, Lf = H * 0.105;
    const codo = [S0[0] + Lu * Math.cos(rad(pose.saludo.codo)), S0[1] + Lu * Math.sin(rad(pose.saludo.codo))];
    const mun = [codo[0] + Lf * Math.sin(rad(pose.saludo.antebrazo)), codo[1] - Lf * Math.cos(rad(pose.saludo.antebrazo))];
    return { tipo: 'saludo', linea: [S0, mitad(S0, codo), codo, mitad(codo, mun), mun], anchos: anchosBrazo, u: uBrazo, manoRot: pose.saludo.mano, dedos: pose.dedos };
  };

  // Saludo: `saludo` 'derecha' levanta la mano derecha de la persona, que de frente queda a la izquierda de quien mira.
  const saludoLado = opc.saludo === 'derecha' ? -1 : opc.saludo === 'izquierda' ? 1 : 0;
  const ladoLibre = saludoLado ? -saludoLado : 1; // el brazo que lleva la pose especial (cintura, bolsillo)
  const anguloDe = (s) => (pose.angulos ? pose.angulos[s] : grados0);
  const brazos = {};
  for (const s of [1, -1]) {
    if (s === saludoLado) brazos[s] = brazoSaludo();
    else if (s === ladoLibre && pose.brazoLibre === 'cintura') brazos[s] = brazoCintura();
    else if (s === ladoLibre && pose.brazoLibre === 'bolsillo') brazos[s] = brazoBolsillo();
    else brazos[s] = brazoCaido(anguloDe(s), pose.codos[s] ?? 0, pose.munecas[s] ?? 0);
  }

  // Piernas. La que no carga el peso se abre un poco más y gira más el pie; `abre` ensancha la postura.
  const baseX = (yy) => w.cadera * 0.5 + H * 0.006 * ((yy - y.cadera) / (y.tobillo - y.cadera));
  const piernaDe = (s) => {
    const libre = pose.carga !== 0 && s !== pose.carga ? 1 : 0;
    const abre = (pose.abre - 1) * H * 0.02;
    const dxTob = abre + libre * H * 0.016, dxRod = abre * 0.5 + libre * H * 0.008;
    const yMuslo = lerp(y.cadera, y.rodilla, 0.5), yPant = lerp(y.rodilla, y.tobillo, 0.35);
    return {
      linea: [
        [baseX(y.cadera), y.cadera], [baseX(yMuslo) + dxRod * 0.25, yMuslo], [baseX(y.rodilla) + dxRod, y.rodilla],
        [baseX(yPant) + (dxRod + dxTob) * 0.6, yPant], [baseX(y.tobillo) + dxTob, y.tobillo],
      ],
      anchos: [w.muslo * 1.05, w.muslo * 0.9, w.rodilla, w.pantorrilla, w.tobillo],
      pie: { x: baseX(y.tobillo) + dxTob + H * 0.004, y0: y.tobillo, y1: suelo, ancho: H * 0.021 + w.tobillo * 0.7, largo: H * 0.045, giro: pose.giro * (libre ? 1.35 : 1) },
    };
  };
  const piernas = { 1: piernaDe(1), '-1': piernaDe(-1) };

  return { sexo, edad, corpulencia: corp, cm, H, cx, top, suelo, y, w, grados: grados0, pose, brazos, saludoLado, piernas, cabezas, u, hombroX: w.hombro };
}

/**
 * Mitad derecha del contorno del torso (de la base del cuello a la entrepierna), desplazada `d` hacia afuera.
 * @deprecated `desde` y `hasta` (desde 2026-11, PR de limpieza) no se usan: ninguna llamada los pasa y la función siempre devuelve el torso completo. Se conservan como referencia.
 */
function perfilTorso(M, d = 0, { desde = 'cuello', hasta = 'entrepierna' } = {}) {
  const { y, w, H } = M;
  const f = M.sexo === 'f' && M.u > 0.6 ? 1 : 0;
  const pts = [
    [0, y.cuello + (y.hombro - y.cuello) * 0.55, true],
    [w.cuello * 1.08 + d * 0.3, y.hombro - H * 0.016],
    [w.cuello + (w.hombro - w.cuello) * 0.58 + d * 0.5, y.hombro - H * 0.004],
    [w.hombro + d * 0.9, y.hombro + H * 0.014],
    [w.hombro - H * 0.003 + d, y.hombro + H * 0.045],
    [w.pecho + H * 0.003 * f + d, lerp(y.axila, y.pecho, 0.6)],
    [w.pecho + H * 0.006 * f + d, y.pecho + H * 0.012],
    [lerp(w.pecho, w.cintura, 0.6) + d, lerp(y.pecho, y.cintura, 0.6)],
    [w.cintura + d, y.cintura],
    [lerp(w.cintura, w.cadera, 0.7) + d, lerp(y.cintura, y.cadera, 0.6)],
    [w.cadera + d, y.cadera],
    [w.cadera * 0.97 + d, lerp(y.cadera, y.entrepierna, 0.6)],
    [0, y.entrepierna + H * 0.004, true],
  ];
  return pts;
}

/** Contorno completo del torso (para la piel y para ropa ceñida). */
function trazoTorso(M, d = 0) {
  return suavizar(espejo(perfilTorso(M, d), M.cx));
}

const lado = (pts, s, cx) => pts.map((p) => [cx + s * (p[0]), p[1], p[2]]);

/**
 * Línea central del brazo en el lado `s` (+1 a la derecha de quien mira, -1 a su izquierda), con la pose que le toca:
 * caído, en la cintura, en el bolsillo o saludando.
 */
function brazoLado(M, s) {
  const b = M.brazos[s];
  return { ...b, linea: lado(b.linea, s, M.cx) };
}

/**
 * Punto del brazo (x, y absolutos y semiancho) según el parámetro `uu`, que para el brazo caído es la altura y
 * y para los demás recorre los mismos tramos. Más allá de la muñeca sigue la dirección del antebrazo.
 */
function brazoEnU(M, s, uu) {
  const { linea: L, anchos: A, u: U } = brazoLado(M, s);
  for (let i = 0; i < L.length - 1; i++) {
    if (uu <= U[i + 1] || i === L.length - 2) {
      const t = limitar((uu - U[i]) / ((U[i + 1] - U[i]) || 1), 0, 1.6);
      return { x: lerp(L[i][0], L[i + 1][0], t), y: lerp(L[i][1], L[i + 1][1], t), w: lerp(A[i], A[i + 1], t) };
    }
  }
  return { x: L[0][0], y: L[0][1], w: A[0] };
}

/**
 * Centro y ancho de una línea con anchos (pierna o brazo) a la altura `yy`, interpolando entre sus puntos.
 * `tope` es cuánto se deja extrapolar por debajo del último punto (1 = nada).
 */
function lineaEnY(Lg, yy, tope = 1.3) {
  const { linea, anchos } = Lg;
  for (let j = 0; j < linea.length - 1; j++) {
    if (yy <= linea[j + 1][1] || j === linea.length - 2) {
      const tt = limitar((yy - linea[j][1]) / ((linea[j + 1][1] - linea[j][1]) || 1), 0, tope);
      return { x: lerp(linea[j][0], linea[j + 1][0], tt), w: lerp(anchos[j], anchos[j + 1], tt) };
    }
  }
  return { x: linea[0][0], w: anchos[0] };
}

/** Línea central de la pierna del lado `s` con su pie, en coordenadas absolutas. */
function piernaLado(M, s) {
  const p = M.piernas[s];
  return { linea: lado(p.linea, s, M.cx), anchos: p.anchos, pie: { ...p.pie, x: M.cx + s * p.pie.x } };
}

function trazoBrazo(M, s, holgura = 0) {
  const b = brazoLado(M, s);
  return suavizar(tubo(b.linea, b.anchos.map((a) => a + holgura)));
}
function trazoPierna(M, s, holgura = 0) {
  const p = piernaLado(M, s);
  return suavizar(tubo(p.linea, p.anchos.map((a) => a + holgura)));
}

/** Piezas (rutas) de la mano; vacío si el brazo está en el bolsillo (la mano no se ve). */
function trazoMano(M, s) {
  const b = M.brazos[s];
  if (b.tipo === 'saludo') return manoSaludo(M, s);
  if (b.tipo === 'bolsillo') return [];
  if (b.tipo === 'cintura') return [manoCerrada(M, s)];
  return manoCaida(M, s);
}

/** Mano abierta, con la palma hacia quien mira, al final del antebrazo levantado. */
function manoSaludo(M, s) {
  const b = brazoLado(M, s);
  const L = b.linea;
  const W = L[L.length - 1], P = L[L.length - 2];
  const l = Math.hypot(W[0] - P[0], W[1] - P[1]) || 1;
  let u = [(W[0] - P[0]) / l, (W[1] - P[1]) / l];
  // La mano puede ir girada respecto del antebrazo: positivo la inclina hacia afuera.
  const rot = ((b.manoRot ?? 0) * Math.PI) / 180 * (s > 0 ? -1 : 1);
  u = [u[0] * Math.cos(rot) - u[1] * Math.sin(rot), u[0] * Math.sin(rot) + u[1] * Math.cos(rot)];
  const v = [-u[1], u[0]];
  const wm = M.w.muneca, largo = M.y.puntaMano - M.y.muneca, palma = largo * 0.5, pw = wm * 1.45;
  const ts = -s; // el pulgar queda del lado del cuerpo
  const loc = (a, c) => [W[0] + u[0] * a + v[0] * c, W[1] + u[1] * a + v[1] * c];
  const dir = (ang) => [u[0] * Math.cos(ang) + v[0] * Math.sin(ang) * ts, u[1] * Math.cos(ang) + v[1] * Math.sin(ang) * ts];
  const partes = [suavizar([loc(-3, -wm), loc(palma * 0.45, -pw), loc(palma, -pw * 0.92), loc(palma, pw * 0.92), loc(palma * 0.45, pw), loc(-3, wm)], true)];
  const ab = b.dedos ?? 1; // apertura de los dedos
  const dedos = [[0.72, 0.9, 0.12 * ab], [0.25, 1.0, 0.03 * ab], [-0.22, 0.93, -0.06 * ab], [-0.68, 0.74, -0.16 * ab]];
  const fl = largo * 0.54, wf = pw * 0.27;
  for (const [c, f, ang] of dedos) {
    const base = loc(palma * 0.85, ts * pw * c), d = dir(ang);
    const fin = [base[0] + d[0] * fl * f, base[1] + d[1] * fl * f];
    partes.push(suavizar(tubo([base, fin], [wf, wf * 0.82], 'redonda'), true));
  }
  const bp = loc(palma * 0.25, ts * pw * 0.95), dp = dir(0.9);
  partes.push(suavizar(tubo([bp, [bp[0] + dp[0] * largo * 0.4, bp[1] + dp[1] * largo * 0.4]], [wf * 1.15, wf * 0.9], 'redonda'), true));
  return partes;
}

/** Antebrazo desde el parámetro `uDesde` hasta la muñeca (se dibuja sobre mantos que lo taparían). */
function trazoAntebrazo(M, s, uDesde) {
  const n = 4, linea = [], anchos = [];
  const uFin = M.brazos[s].u[M.brazos[s].u.length - 1];
  for (let i = 0; i <= n; i++) {
    const q = brazoEnU(M, s, lerp(uDesde, uFin, i / n));
    linea.push([q.x, q.y]); anchos.push(q.w);
  }
  return suavizar(tubo(linea, anchos, 'plana'));
}

/** Mano cerrada apoyada en la cintura. */
function manoCerrada(M, s) {
  const b = brazoLado(M, s);
  const W = b.linea[b.linea.length - 1];
  const fx = ((b.manoFlex ?? 0) * Math.PI) / 180 * s;
  const d0 = [b.manoDir[0] * s, b.manoDir[1]];
  const d = [d0[0] * Math.cos(fx) - d0[1] * Math.sin(fx), d0[0] * Math.sin(fx) + d0[1] * Math.cos(fx)];
  const v = [-d[1], d[0]];
  const wm = M.w.muneca, largo = (M.y.puntaMano - M.y.muneca) * 0.62;
  const loc = (a, c) => [W[0] + d[0] * a + v[0] * c, W[1] + d[1] * a + v[1] * c];
  return suavizar([loc(-2, -wm), loc(largo * 0.35, -wm * 1.45), loc(largo * 0.8, -wm * 1.2), loc(largo, 0), loc(largo * 0.8, wm * 1.2), loc(largo * 0.35, wm * 1.45), loc(-2, wm)], true);
}

/** Mano que cuelga, orientada según el antebrazo y la flexión de la muñeca, con el pulgar hacia el cuerpo. */
function manoCaida(M, s) {
  const b = brazoLado(M, s);
  const W = b.linea[b.linea.length - 1];
  const a = (b.mano.ang * Math.PI) / 180;
  const d = [s * Math.sin(a), Math.cos(a)]; // hacia abajo, abierta hacia afuera
  const v = [-d[1], d[0]];
  const wm = M.w.muneca, largo = M.y.puntaMano - M.y.muneca;
  const loc = (t, c) => [W[0] + d[0] * t + v[0] * c, W[1] + d[1] * t + v[1] * c];
  const palma = suavizar([loc(-2, -wm), loc(largo * 0.35, -wm * 1.25), loc(largo * 0.75, -wm * 1.1), loc(largo, 0), loc(largo * 0.75, wm * 1.1), loc(largo * 0.35, wm * 1.25), loc(-2, wm)], true);
  // Pulgar: asoma por el lado del cuerpo.
  const lado = s; // v apunta hacia -x: el lado del cuerpo es +v para el brazo derecho de quien mira y -v para el otro
  const bp = loc(largo * 0.18, lado * wm * 1.0), fin = loc(largo * 0.56, lado * wm * 1.55);
  return [palma, suavizar(tubo([bp, fin], [wm * 0.62, wm * 0.5], 'redonda'), true)];
}

/** Pie visto de frente; `giro` abre la punta hacia afuera. */
function trazoPie(M, s) {
  const { x: xx, y0, y1, ancho, largo, giro } = piernaLado(M, s).pie;
  const punta = s * largo * Math.tan(((giro ?? 10) * Math.PI) / 180);
  const pts = [
    [xx - ancho * 0.78, y0 - 4],
    [xx - ancho * 0.9, y0 + (y1 - y0) * 0.55],
    [xx - ancho * 1.25 + punta, y1 - largo * 0.15],
    [xx + punta * 1.3, y1 + 0.5],
    [xx + ancho * 1.25 + punta, y1 - largo * 0.15],
    [xx + ancho * 0.9, y0 + (y1 - y0) * 0.55],
    [xx + ancho * 0.78, y0 - 4],
  ];
  return suavizar(pts, true);
}

function trazoCuello(M) {
  const { cx, y, w, H } = M;
  const top = y.menton - H * 0.025;
  // El cuello se inclina con la cabeza: la parte de arriba gira alrededor del arranque en los hombros.
  const ang = ((M.pose.cabeza ?? 0) * Math.PI) / 180;
  const girar = (p, peso) => {
    const a = ang * peso, c = Math.cos(a), n = Math.sin(a), dx = p[0] - cx, dy = p[1] - y.hombro;
    return [cx + dx * c - dy * n, y.hombro + dx * n + dy * c, p[2]];
  };
  const pts = [
    girar([cx - w.cuello, top], 1), girar([cx + w.cuello, top, true], 1),
    girar([cx + w.cuello * 1.05, lerp(top, y.hombro, 0.5)], 0.5),
    [cx + w.cuello * 1.35, y.hombro - H * 0.012],
    [cx, y.hombro + H * 0.012, true],
    [cx - w.cuello * 1.35, y.hombro - H * 0.012],
    girar([cx - w.cuello * 1.05, lerp(top, y.hombro, 0.5)], 0.5),
  ];
  return suavizar(pts, true);
}

/**
 * Deformación suave de la figura por su pose: un hombro más bajo que el otro y la columna con una curva (la cabeza
 * se desplaza sobre las caderas). Se aplica a todas las formas, ropa incluida, y deja quietos los pies y las caderas.
 */
function deformar(M) {
  const { cx, y, H } = M;
  const tilt = Math.tan(((M.pose.hombros ?? 0) * Math.PI) / 180);
  const sway = (M.pose.columna ?? 0) * H;
  if (Math.abs(tilt) < 1e-6 && Math.abs(sway) < 1e-6) return null;
  const paso = (t) => { const c = limitar(t, 0, 1); return c * c * (3 - 2 * c); };
  return (x, yy) => {
    const wTilt = paso((y.cintura - yy) / (y.cintura - y.hombro));
    const wSway = paso((y.cadera - yy) / (y.cadera - y.menton)) ** 1.3;
    return [x + sway * wSway, yy + (x - cx) * tilt * wTilt];
  };
}

Object.assign(__e, { LIENZO, EDAD_MIN, ALTURA_MIN, CORPULENCIA_MIN, CORPULENCIAS, POSTURAS, POSE_FIRME, poseAleatoria, normalizarPose, medidas, perfilTorso, trazoTorso, brazoLado, brazoEnU, lineaEnY, piernaLado, trazoBrazo, trazoPierna, trazoMano, trazoAntebrazo, trazoPie, trazoCuello, deformar });
return __e;
})();

__m['prendas/comun'] = (() => {
const __e = {};
// Piezas que comparten las plantillas de prendas: medidas del cuerpo vestido, escotes, bajos, mangas y adornos.

const { suavizar, espejo, poligono, tubo, lerp, limitar, oscurecer, aclarar } = __m['util'];
const { perfilTorso, brazoEnU } = __m['cuerpo'];

// ---------- Ayudas ----------

function yLargo(M, largo) {
  const { y, H } = M;
  if (typeof largo === 'number') return M.top + H * largo;
  switch (largo) {
    case 'pecho': return y.pecho + H * 0.01;
    case 'cintura': return y.cintura + H * 0.005;
    case 'cadera': return y.cadera + H * 0.035;
    case 'muslo-alto': return lerp(y.entrepierna, y.rodilla, 0.2);
    case 'muslo': return lerp(y.entrepierna, y.rodilla, 0.5);
    case 'rodilla': return y.rodilla - H * 0.004;
    case 'bajo-rodilla': return lerp(y.rodilla, y.tobillo, 0.22);
    case 'pantorrilla': return lerp(y.rodilla, y.tobillo, 0.55);
    case 'tobillo': return y.tobillo - H * 0.012;
    case 'suelo': return M.suelo - H * 0.004;
    default: return y.rodilla;
  }
}

/** Semiancho de un perfil (lista [x, y] de la mitad derecha) a la altura yy. */
function anchoEn(pts, yy) {
  if (yy <= pts[0][1]) return pts[0][0];
  for (let i = 1; i < pts.length; i++) {
    if (yy <= pts[i][1]) {
      const dy = pts[i][1] - pts[i - 1][1] || 1;
      return lerp(pts[i - 1][0], pts[i][0], (yy - pts[i - 1][1]) / dy);
    }
  }
  return pts[pts.length - 1][0];
}

const relleno = (p, k, ctx) =>
  p.patron ? ctx.patron(p.patron.tipo ?? p.patron, k.principal, k[p.patron.rol ?? 'secundario'] ?? k.secundario, p.patron.escala ?? 1) : k.principal;

const parte = (d, fill, k, extra = {}) => ({ d, fill, contorno: oscurecer(k.principal, 0.5), volumen: true, ...extra });
const linea = (d, stroke, sw, op = 1, extra = {}) => ({ d, fill: 'none', stroke, sw, op, cap: 'round', ...extra });
const sombraDe = (c) => oscurecer(c, 0.32);

/** Pliegues verticales entre dos alturas: una línea de sombra y, al lado, otra de luz, ambas suaves, para dar relieve sin dibujar bordes. */
function pliegues(M, perfil, yA, yB, n, color, amp = 0.012) {
  const out = [];
  for (let i = 1; i <= n; i++) {
    const f = (i / (n + 1)) * 2 - 1;
    const xa = f * anchoEn(perfil, yA) * 0.92, xb = f * anchoEn(perfil, yB) * 0.96;
    const mx = (xa + xb) / 2 + (i % 2 ? 1 : -1) * M.H * amp;
    const fin = yB - (i % 2) * M.H * 0.006;
    const ruta = (dx) => `M${M.cx + xa + dx} ${yA}Q${M.cx + mx + dx} ${(yA + yB) / 2} ${M.cx + xb + dx} ${fin}`;
    out.push(linea(ruta(0), sombraDe(color), 2.2, 0.34, { suave: true }));
    out.push(linea(ruta(2.6), aclarar(color, 0.28), 1.6, 0.32, { suave: true }));
  }
  return out;
}

// ---------- Escote, bajo y perfil del cuerpo vestido ----------

function escotePts(M, tipo, prof = 1) {
  const { w, y, H } = M;
  const y0 = y.hombro - H * 0.016, xn = w.cuello * 1.08;
  switch (tipo) {
    case 'v': { const yE = y.hombro + H * (0.04 + 0.09 * prof); return [[0, yE, true], [xn * 0.5, lerp(yE, y0, 0.5)], [xn, y0]]; }
    case 'cuadrado': { const yE = y.hombro + H * (0.022 + 0.03 * prof); return [[0, yE, true], [xn * 0.92, yE, true], [xn * 0.96, y0, true]]; }
    case 'barco': { const xb = w.cuello + (w.hombro - w.cuello) * 0.7; return [[0, y.hombro + H * 0.012], [xb * 0.5, y.hombro + H * 0.006], [xb, y.hombro - H * 0.004]]; }
    case 'alto': { const yE = y.menton + (y.cuello - y.menton) * 0.35; return [[0, yE, true], [w.cuello * 1.03, yE, true], [w.cuello * 1.1, lerp(yE, y0, 0.6)], [xn, y0]]; }
    case 'ojal': { const yE = y.hombro + H * 0.04; return [[0, yE], [xn * 0.5, yE - H * 0.004], [xn * 0.95, lerp(yE, y0, 0.6)], [xn, y0]]; }
    default: { const yE = y.hombro + H * (0.012 + 0.03 * prof); return [[0, yE], [xn * 0.5, yE - H * 0.003], [xn * 0.9, lerp(yE, y0, 0.55)], [xn, y0]]; }
  }
}

function puntosBajo(estilo, xH, yH, H) {
  const base = (a) => [xH, yH + (a ?? 0), true];
  switch (estilo) {
    case 'curvo': return [base(), [xH * 0.5, yH + H * 0.011], [0, yH + H * 0.015, true]];
    case 'ondulado': return [base(), [xH * 0.75, yH + H * 0.012], [xH * 0.5, yH - H * 0.004], [xH * 0.25, yH + H * 0.012], [0, yH + H * 0.002, true]];
    case 'dentado': return [base(), [xH * 0.8, yH + H * 0.022, true], [xH * 0.6, yH, true], [xH * 0.4, yH + H * 0.022, true], [xH * 0.2, yH, true], [0, yH + H * 0.018, true]];
    case 'pico': return [base(), [xH * 0.5, yH + H * 0.02], [0, yH + H * 0.055, true]];
    case 'curvo-alto': return [base(), [xH * 0.5, yH - H * 0.008], [0, yH - H * 0.02, true]];
    case 'flecos': return [base(), [0, yH + H * 0.004, true]];
    default: return [base(), [0, yH + H * 0.004, true]];
  }
}

/** Mitad derecha del contorno de una prenda de torso, del escote al bajo. Devuelve también el perfil sin el bajo. */
/** Escotes que dejan los hombros al aire: el corpiño termina arriba del pecho y los tirantes van aparte. */
const ESCOTES_SIN_HOMBRO = ['tirantes', 'corazon', 'asimetrico', 'halter'];

/** Cuánto se abre la falda hacia el bajo según la forma (t de 0 en la cadera a 1 en el bajo). */
function vueloSegunForma(forma, t) {
  switch (forma) {
    case 'sirena': return t ** 4;
    case 'trapecio': return t ** 0.75;
    case 'globo': return Math.sin(Math.PI * t) * 0.85;
    case 'tubo': return 0;
    default: return t ** 1.15;
  }
}

function perfilVestido(M, o) {
  const { w, y, H } = M;
  const { d = 0, cin = 1, vuelo = 0, yHem, sinMangas = false, escote = 'redondo', prof = 1, bajo = 'recto', forma } = o;
  const T = perfilTorso(M, 0);
  const sinHombro = ESCOTES_SIN_HOMBRO.includes(escote);
  const yTop = y.pecho - H * 0.02 + (prof - 1) * H * 0.02, xTop = w.pecho * 0.95 + d;
  const E = sinHombro
    ? [[0, yTop + (escote === 'corazon' ? H * 0.022 : 0)], [xTop * 0.5, yTop - H * 0.006], [xTop, yTop + H * 0.004, true]]
    : escotePts(M, escote, prof);
  const pts = E.slice();
  const wd = (i, peso) => d * lerp(1, cin, peso);
  if (sinHombro) {
    pts.push([w.pecho * 0.97 + d, lerp(yTop, y.pecho, 0.7) + H * 0.004]);
  } else if (sinMangas) {
    const xs = lerp(w.cuello * 1.08, w.hombro, 0.5);
    pts.push([xs, y.hombro - H * 0.006], [xs + H * 0.003, y.hombro + H * 0.03], [w.pecho * 0.9 + d * 0.3, y.axila + H * 0.008]);
  } else if (escote !== 'barco') {
    pts.push([T[2][0] + d * 0.5, T[2][1]]);
    pts.push([T[3][0] + d, T[3][1]]);
    pts.push([T[4][0] + d, T[4][1]]);
  } else {
    pts.push([T[3][0] + d, T[3][1]], [T[4][0] + d, T[4][1]]);
  }
  pts.push([T[6][0] + d, T[6][1]]);
  pts.push([T[7][0] + wd(7, 0.5), T[7][1]]);
  pts.push([T[8][0] + wd(8, 1), T[8][1]]);
  pts.push([T[9][0] + wd(9, 0.5), T[9][1]]);
  pts.push([T[10][0] + d, T[10][1]]);
  if (yHem > y.cadera) {
    for (const t of [0.25, 0.55, 0.8, 1]) {
      pts.push([w.cadera + d + vuelo * H * vueloSegunForma(forma, t), lerp(y.cadera, yHem, t)]);
    }
  }
  // Cortar en el bajo.
  const corte = [];
  for (let i = 0; i < pts.length; i++) {
    if (pts[i][1] < yHem) corte.push(pts[i]);
    else { const a = corte[corte.length - 1] ?? pts[0], b = pts[i]; const t = (yHem - a[1]) / ((b[1] - a[1]) || 1); corte.push([lerp(a[0], b[0], limitar(t, 0, 1)), yHem]); break; }
  }
  if (corte[corte.length - 1][1] < yHem) corte.push([corte[corte.length - 1][0], yHem]);
  const fin = corte[corte.length - 1];
  const sinBajo = corte.slice();
  const completo = corte.slice(0, -1).concat(puntosBajo(bajo, fin[0], fin[1], H));
  return { completo, perfil: sinBajo, xBajo: fin[0], yBajo: fin[1], escote: E };
}

// ---------- Mangas ----------

const brazoEnY = brazoEnU;

function finManga(M, tipo) {
  const { y, H } = M;
  const y0 = y.hombro + H * 0.02;
  switch (tipo) {
    case 'corta': return lerp(y0, y.codo, 0.55);
    case 'tres-cuartos': return lerp(y.codo, y.muneca, 0.5);
    case 'codo': return y.codo + H * 0.012;
    case 'cap': return lerp(y0, y.codo, 0.28);
    case 'larga-mano': return y.muneca + H * 0.035;
    case 'kimono': return y.muneca + H * 0.045;
    default: return y.muneca - H * 0.006;
  }
}

function mangaForma(M, s, tipo, d, vuelo = 0) {
  const { H, y } = M;
  const yA = y.hombro + H * 0.012, yB = finManga(M, tipo);
  const n = 6, linea = [], anchos = [];
  for (let i = 0; i < n; i++) {
    const t = i / (n - 1);
    const q = brazoEnY(M, s, lerp(yA, yB, t));
    let f = 1, desp = 0;
    if (tipo === 'campana') f = 1 + (1.2 + vuelo * 10) * t ** 1.6;
    else if (tipo === 'ancha') f = 1 + 0.7 * t;
    else if (tipo === 'globo') f = 1.5 - 0.5 * Math.min(1, t * 2.2) + (t > 0.8 ? 0.1 : 0);
    else if (tipo === 'kimono') { f = 1.55 + 0.85 * t; desp = H * 0.026 * t; }
    else if (tipo === 'abullonada') f = 1.55 - 0.55 * Math.min(1, t * 1.6);
    else if (tipo === 'farol') f = 1.5 - 0.5 * Math.min(1, t / 0.3) + 0.42 * Math.max(0, (t - 0.7) / 0.3) ** 1.3; // abullonada arriba y en el puño
    else if (tipo === 'pagoda') f = 1 + 1.5 * Math.max(0, (t - 0.4) / 0.6) ** 1.4;
    else if (tipo === 'ajustada') f = 0.9;
    else if (tipo === 'cap') f = 1.1;
    // Con el brazo levantado la manga ancha se recoge hacia el codo: menos vuelo.
    if (s === M.saludoLado) f = 1 + (f - 1) * 0.55;
    linea.push([q.x + (s === M.saludoLado ? 0 : s * desp), q.y]);
    anchos.push((q.w + d * (i === 0 ? 1.15 : 1)) * f);
  }
  return { pts: tubo(linea, anchos, 'plana'), linea, anchos, fin: { x: linea[n - 1][0], y: linea[n - 1][1], w: anchos[n - 1] } };
}

function mangas(M, tipo, d, k, F, vuelo, puño) {
  const partes = [], detalles = [], fins = [];
  if (!tipo || tipo === 'sin') return { partes, detalles, fins };
  for (const s of [1, -1]) {
    const m = mangaForma(M, s, tipo, d, vuelo);
    partes.push(parte(suavizar(m.pts), F, k, { proyecta: true }));
    fins.push({ s, ...m.fin });
    if (puño) {
      const { x, y, w } = m.fin;
      detalles.push(linea(`M${x - w * 0.97} ${y - 1.5}L${x + w * 0.97} ${y - 1.5}`, k.ribete, Math.max(3, M.H * 0.011), 1, { cap: 'butt' }));
    }
  }
  return { partes, detalles, fins };
}


// ---------- Adornos comunes de las prendas de torso ----------

/** Franja de piel (puntitos mullidos) a lo largo de una ruta. */
const pielRuta = (d, color, ancho = 7) => linea(d, color, ancho, 0.95, { dash: '0.1 5.2', cap: 'round' });

/**
 * Capucha, bolsillos, cuello de camisa, ribetes de piel, cinturón y cordones: lo comparten túnicas, vestidos y chaquetas.
 * `per`: perfil de la prenda; `fins`: extremos de las mangas (para los puños de piel).
 */
function adornosTorso(M, p, k, per, fins = []) {
  const { H, y, cx, w } = M;
  const atras = [], detalles = [], partes = [];
  const prof = per.perfil;
  const sombra = sombraDe(k.principal), luz = aclarar(k.principal, 0.3);
  const piel = k.ribete ?? aclarar(k.principal, 0.35);
  const rect = (x0, y0, x1, y1, ahusa = 0) => poligono([[x0, y0], [x1, y0], [x1 - ahusa, y1], [x0 + ahusa, y1]]);

  if (p.capucha) {
    const mitad = [[0, y.menton - H * 0.004, true], [w.cuello * 1.6, y.menton + H * 0.012], [w.cuello * 2.5, y.cuello + H * 0.012], [w.hombro * 0.8, y.hombro + H * 0.012], [w.cuello * 1.8, y.hombro + H * 0.036], [0, y.hombro + H * 0.038, true]];
    atras.push({ d: suavizar(espejo(mitad, cx)), fill: aclarar(k.principal, 0.04), contorno: sombra, proyecta: true });
    for (const sg of [-1, 1]) detalles.push(linea(`M${cx + sg * w.cuello * 0.8} ${y.hombro + H * 0.02}Q${cx + sg * w.cuello * 0.9} ${y.pecho} ${cx + sg * w.cuello * 0.7} ${y.pecho + H * 0.05}`, k.ribete ?? k.detalle, 1.8, 0.9));
  }
  if (p.cuelloCamisa) {
    for (const sg of [-1, 1]) {
      partes.push({ d: poligono([[cx + sg * w.cuello * 0.15, y.hombro - H * 0.017], [cx + sg * w.cuello * 1.5, y.hombro - H * 0.012], [cx + sg * w.cuello * 1.25, y.hombro + H * 0.03]]), fill: aclarar(k.principal, 0.06), proyecta: true });
    }
  }
  const bol = p.bolsillos;
  if (bol === 'pecho' || bol === 'ambos') {
    for (const sg of [-1, 1]) {
      const x0 = cx + sg * w.pecho * 0.55 - H * 0.019, y0 = y.pecho + H * 0.018;
      detalles.push(linea(`M${x0} ${y0}L${x0 + H * 0.038} ${y0}L${x0 + H * 0.036} ${y0 + H * 0.034}L${x0 + H * 0.002} ${y0 + H * 0.034}Z`, sombra, 1.3, 0.5, { suave: true }));
      detalles.push(linea(`M${x0 + 1} ${y0 + 1.5}L${x0 + H * 0.037} ${y0 + 1.5}`, luz, 1.2, 0.5, { suave: true }));
    }
  }
  if (bol === 'cadera' || bol === 'ambos') {
    for (const sg of [-1, 1]) {
      const xa = cx + sg * anchoEn(prof, y.cadera - H * 0.03) * 0.78, ya = y.cintura + H * 0.045;
      detalles.push(linea(`M${xa} ${ya}L${xa - sg * H * 0.035} ${ya + H * 0.04}`, sombra, 1.6, 0.55, { suave: true }));
      detalles.push(linea(`M${xa + sg * 2} ${ya + 1}L${xa - sg * H * 0.033} ${ya + H * 0.041}`, luz, 1.2, 0.5, { suave: true }));
    }
  }
  if (bol === 'canguro') {
    const ya = y.cintura + H * 0.01, yb = Math.min(per.yBajo - H * 0.02, y.cadera + H * 0.035), xa = anchoEn(prof, ya) * 0.62;
    detalles.push(linea(`M${cx - xa} ${ya}L${cx + xa} ${ya}L${cx + xa * 0.8} ${yb}L${cx - xa * 0.8} ${yb}Z`, sombra, 1.6, 0.5, { suave: true }));
    detalles.push(linea(`M${cx - xa + 2} ${ya + 2}L${cx + xa - 2} ${ya + 2}`, luz, 1.3, 0.5, { suave: true }));
  }
  if (p.pieles) {
    const todo = p.pieles === 'todo';
    if (todo || p.pieles === 'cuello') {
      const E = per.escote;
      const ruta = suavizar([...E.slice().reverse().map(([x, yy, c]) => [cx - x, yy, c]).slice(0, -1), ...E.map(([x, yy, c]) => [cx + x, yy, c])], false);
      detalles.push(pielRuta(ruta, piel, 9));
    }
    if (todo || p.pieles === 'puños') for (const f of fins) detalles.push(pielRuta(`M${f.x - f.w} ${f.y - 2}L${f.x + f.w} ${f.y - 2}`, piel, 8));
    if (todo || p.pieles === 'bajo') detalles.push(pielRuta(`M${cx - per.xBajo} ${per.yBajo - 3}L${cx + per.xBajo} ${per.yBajo - 3}`, piel, 8));
  }
  if (p.cinturon) {
    const yc = y.cintura, xa = anchoEn(prof, yc) + 1;
    partes.push({ d: rect(cx - xa, yc - H * 0.008, cx + xa, yc + H * 0.008), fill: k.secundario ?? sombra, plano: false, volumen: false, op: 0.95 });
    detalles.push({ d: suavizar([[cx - 5, yc - 5], [cx + 5, yc - 5], [cx + 5, yc + 5], [cx - 5, yc + 5]], true, 0.2), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 0.8 });
  }
  if (p.cordones) {
    const y0 = y.pecho - H * 0.005, y1 = y.cadera - H * 0.01, n = 7;
    let ruta = '';
    for (let i = 0; i < n; i++) { const yy = lerp(y0, y1, i / (n - 1)); ruta += `${i ? 'L' : 'M'}${cx + (i % 2 ? 7 : -7)} ${yy}`; }
    detalles.push(linea(ruta, k.detalle, 1.4, 0.95, { cap: 'round' }));
    for (let i = 0; i < n; i++) { const yy = lerp(y0, y1, i / (n - 1)); detalles.push({ d: suavizar([[cx - 8, yy], [cx - 6, yy + 1.5], [cx - 4, yy], [cx - 6, yy - 1.5]], true, 0.4), fill: k.detalle }); detalles.push({ d: suavizar([[cx + 8, yy], [cx + 6, yy + 1.5], [cx + 4, yy], [cx + 6, yy - 1.5]], true, 0.4), fill: k.detalle }); }
  }
  return { atras, partes, detalles };
}

/** Tirantes de los escotes sin hombro. */
function tirantesDe(M, escote, p, F, k, ladoUno = 1) {
  const { H, y, cx, w } = M;
  const yTop = y.pecho - H * 0.02 + ((p.prof ?? 1) - 1) * H * 0.02, xTop = w.pecho * 0.95 + (p.holgura ?? 0.004) * H;
  const tubo2 = (a, b, anchoPx) => parte(suavizar(tubo([a, b], [anchoPx, anchoPx], 'plana')), F, k, { volumen: false, proyecta: false });
  const out = [];
  const an = Math.max(2.5, H * 0.0075);
  if (escote === 'tirantes' || escote === 'corazon') for (const sg of [-1, 1]) out.push(tubo2([cx + sg * xTop * 0.62, yTop + 2], [cx + sg * w.cuello * 1.55, y.hombro - H * 0.008], an));
  if (escote === 'asimetrico') out.push(tubo2([cx + ladoUno * xTop * 0.9, yTop + 2], [cx + ladoUno * w.hombro * 0.85, y.hombro + H * 0.004], an * 2.2));
  if (escote === 'halter') for (const sg of [-1, 1]) out.push(tubo2([cx + sg * xTop * 0.72, yTop + 2], [cx + sg * w.cuello * 0.9, y.cuello + H * 0.005], an));
  return out;
}

function flecos(M, yH, xH, color) {
  const out = [];
  const n = Math.max(6, Math.round((xH * 2) / 7));
  for (let i = 0; i < n; i++) {
    const x = M.cx - xH + (i / (n - 1)) * xH * 2;
    out.push(linea(`M${x} ${yH - 1}L${x + (i % 2 ? 1.5 : -1.5)} ${yH + M.H * 0.022}`, color, 1.6, 0.95));
  }
  return out;
}

Object.assign(__e, { yLargo, anchoEn, relleno, parte, linea, sombraDe, pliegues, escotePts, puntosBajo, ESCOTES_SIN_HOMBRO, vueloSegunForma, perfilVestido, brazoEnY, finManga, mangaForma, mangas, pielRuta, adornosTorso, tirantesDe, flecos });
return __e;
})();

__m['prendas/tunica'] = (() => {
const __e = {};
const { suavizar, espejo, poligono, lerp, oscurecer } = __m['util'];
const { yLargo, anchoEn, relleno, parte, linea, sombraDe, pliegues, ESCOTES_SIN_HOMBRO, perfilVestido, mangas, adornosTorso, tirantesDe, flecos } = __m['prendas/comun'];

function tunica(M, p, k, ctx) {
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

Object.assign(__e, { tunica });
return __e;
})();

__m['prendas/pantalon'] = (() => {
const __e = {};
const { suavizar, espejo, tubo, lerp, aclarar } = __m['util'];
const { piernaLado, lineaEnY } = __m['cuerpo'];
const { yLargo, relleno, parte, linea, sombraDe } = __m['prendas/comun'];

function pantalon(M, p, k, ctx) {
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

Object.assign(__e, { pantalon });
return __e;
})();

__m['prendas/falda'] = (() => {
const __e = {};
const { suavizar, espejo, poligono, lerp, limitar, aclarar } = __m['util'];
const { yLargo, anchoEn, relleno, parte, linea, sombraDe, pliegues, puntosBajo, vueloSegunForma, flecos } = __m['prendas/comun'];

function falda(M, p, k, ctx) {
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

Object.assign(__e, { falda });
return __e;
})();

__m['prendas/taparrabo'] = (() => {
const __e = {};
const { suavizar, espejo } = __m['util'];
const { yLargo, relleno, parte, linea } = __m['prendas/comun'];

function taparrabo(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'muslo');
  const ancho = (p.ancho ?? 0.045) * H;
  const alto = y.cintura + H * 0.03;
  const pts = [[0, alto, true], [w.cadera * 0.95, alto, true], [ancho * 1.05, yH * 0.5 + alto * 0.5], [ancho, yH, true], [0, yH + H * (p.punta ?? 0.012), true]];
  const partes = [parte(suavizar(espejo(pts, cx)), F, k)];
  const detalles = [linea(`M${cx - w.cadera * 0.97} ${alto + 2}L${cx + w.cadera * 0.97} ${alto + 2}`, k.ribete ?? k.detalle, Math.max(3, H * 0.008), 1, { cap: 'butt' })];
  if (p.ribeteBajo) detalles.push(linea(`M${cx - ancho} ${yH - 2}L${cx + ancho} ${yH - 2}`, k.ribete, 4, 1, { cap: 'butt' }));
  return { atras: [], partes, detalles };
}

Object.assign(__e, { taparrabo });
return __e;
})();

__m['prendas/capa'] = (() => {
const __e = {};
const { suavizar, espejo, poligono, lerp, oscurecer } = __m['util'];
const { yLargo, anchoEn, relleno, parte, linea, pliegues, puntosBajo } = __m['prendas/comun'];

function capa(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'tobillo');
  const ancho = (p.ancho ?? 1) * H;
  const half = [
    [0, y.hombro - H * 0.012, true],
    [w.cuello * 1.3, y.hombro - H * 0.014],
    [w.hombro + H * 0.012, y.hombro + H * 0.01],
    [w.hombro + H * 0.025 + 0.01 * ancho, y.axila],
    [w.hombro + H * 0.035 + 0.03 * ancho, lerp(y.axila, yH, 0.35)],
    [w.hombro + H * 0.04 + 0.05 * ancho, lerp(y.axila, yH, 0.7)],
    [w.hombro + H * 0.04 + 0.07 * ancho, yH],
    ...puntosBajo(p.bajo ?? 'curvo', 0, yH, H).slice(1).map((q, i, a) => (i === a.length - 1 ? [0, yH + H * 0.012, true] : q)),
  ];
  half[half.length - 1] = [0, yH + H * 0.012, true];
  const atras = [parte(suavizar(espejo(half, cx)), F, k, { volumen: true })];
  const detalles = [];
  const prof = half.slice(1, -1).map(([x, yy]) => [x, yy]);
  const partes = [];
  // Piezas delanteras: solapas sobre los hombros y cierre.
  if (p.cierre !== 'abierta') {
    const sh = (s) => parte(suavizar([[cx + s * w.cuello * 1.1, y.hombro - H * 0.014], [cx + s * (w.hombro + H * 0.01), y.hombro + H * 0.008], [cx + s * (w.hombro + H * 0.03), y.axila], [cx + s * w.pecho * 0.75, y.pecho + H * 0.01], [cx + s * w.cuello * 0.9, y.hombro + H * 0.04]]), F, k);
    partes.push(sh(1), sh(-1));
  }
  if (p.cierre === 'broche' || p.cierre === 'fibula' || p.cierre === undefined) {
    detalles.push({ d: suavizar([[cx - 5, y.hombro + H * 0.035], [cx, y.hombro + H * 0.035 + 6], [cx + 5, y.hombro + H * 0.035], [cx, y.hombro + H * 0.035 - 6]]), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 1 });
  }
  if (p.ribete !== false && k.ribete) {
    for (const s of [1, -1]) detalles.push(linea(`M${cx + s * w.cuello * 1.1} ${y.hombro - H * 0.014}L${cx + s * w.cuello * 0.9} ${y.hombro + H * 0.04}`, k.ribete, Math.max(3, H * 0.008), 1, { cap: 'butt' }));
  }
  const det2 = pliegues(M, prof, y.axila + H * 0.04, yH, 4, k.principal, 0.012);
  if (p.ribeteBajo) {
    const bh = H * 0.025, xa = anchoEn(prof, yH - bh), xb = anchoEn(prof, yH);
    det2.push({ d: poligono([[cx - xa, yH - bh], [cx + xa, yH - bh], [cx + xb, yH], [cx - xb, yH]]), fill: k.ribete });
  }
  return { atras: [...atras, ...det2.map((x) => ({ ...x, atras: true }))], partes, detalles };
}

Object.assign(__e, { capa });
return __e;
})();

__m['prendas/poncho'] = (() => {
const __e = {};
const { suavizar, espejo, lerp } = __m['util'];
const { yLargo, anchoEn, relleno, parte, linea, sombraDe, flecos } = __m['prendas/comun'];

function poncho(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'cadera');
  const a = (p.ancho ?? 1) * H;
  const xs = w.hombro + 0.06 * a;
  const xe = w.hombro + 0.09 * a;
  const half = [
    [0, y.hombro + H * 0.045, true], [w.cuello * 0.9, y.hombro + H * 0.012], [w.cuello * 1.3, y.hombro - H * 0.014],
    [xs * 0.7, y.hombro + H * 0.005], [xs, lerp(y.hombro, y.codo, 0.45)], [xe, lerp(y.codo, yH, 0.35)],
    [xe * 0.97, yH, true], [xe * 0.5, yH + H * 0.012], [0, yH + H * (p.punta ?? 0.06), true],
  ];
  const partes = [parte(suavizar(espejo(half, cx)), F, k)];
  const detalles = [];
  const prof = half.slice(1).map(([x, yy]) => [x, yy]);
  if (p.listas) {
    for (let i = 1; i <= p.listas; i++) {
      const yy = lerp(y.hombro + H * 0.08, yH, i / (p.listas + 1));
      const xx = anchoEn(prof.slice(2), yy);
      detalles.push(linea(`M${cx - xx} ${yy}L${cx + xx} ${yy + H * 0.012}`, i % 2 ? k.secundario : k.ribete, Math.max(3, H * 0.007), 1, { cap: 'butt' }));
    }
  }
  detalles.push(linea(`M${cx - w.cuello * 1.3} ${y.hombro - H * 0.014}Q${cx} ${y.hombro + H * 0.052} ${cx + w.cuello * 1.3} ${y.hombro - H * 0.014}`, k.ribete ?? sombraDe(k.principal), Math.max(3, H * 0.008), 1, { cap: 'butt' }));
  if (p.flecos !== false) detalles.push(...flecos(M, yH + H * 0.004, xe * 0.97, k.ribete ?? k.detalle).slice(0, 0));
  return { atras: [], partes, detalles };
}

Object.assign(__e, { poncho });
return __e;
})();

__m['prendas/drapeado'] = (() => {
const __e = {};
const { suavizar, lerp } = __m['util'];
const { yLargo, relleno, parte, linea, sombraDe } = __m['prendas/comun'];

/** Manto diagonal: toga, sari, plaid, arisaid, tilma cruzada. */
function drapeado(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'tobillo');
  const sg = p.lado === 'der' ? 1 : -1; // lado del hombro cubierto
  const X = (v) => cx + sg * v;
  const pts = [
    [X(-w.cuello * 1.2), y.hombro - H * 0.014],
    [X(w.hombro + H * 0.015), y.hombro + H * 0.012],
    [X(w.hombro + H * 0.05), y.codo],
    [X(w.cadera + H * 0.075), lerp(y.codo, yH, 0.5)],
    [X(w.cadera + H * 0.09), yH - H * 0.004, true],
    [X(-w.cadera - H * 0.055), yH - H * 0.002, true],
    [X(-w.cadera - H * 0.025), y.cadera + H * 0.02],
    [X(-w.cintura * 0.4), y.cintura - H * 0.01],
    [X(w.pecho * 0.12), y.pecho + H * 0.008],
  ];
  const partes = [parte(suavizar(pts, true), F, k)];
  const detalles = [];
  // Orla a lo largo del borde diagonal.
  if (k.ribete && p.orla !== false) {
    detalles.push(linea(`M${pts[0][0]} ${pts[0][1]}Q${X(w.pecho * 0.1)} ${y.pecho - H * 0.02} ${pts[7][0]} ${pts[7][1]}L${pts[6][0]} ${pts[6][1]}L${pts[5][0]} ${pts[5][1]}`, k.ribete, Math.max(3, H * 0.009), 1, { cap: 'butt' }));
  }
  // Pliegues que caen desde el hombro.
  for (let i = 0; i < (p.pliegues ?? 5); i++) {
    const t = (i + 1) / ((p.pliegues ?? 5) + 1);
    const xa = lerp(pts[7][0], pts[1][0], 0.2 + t * 0.6), ya = lerp(pts[7][1], pts[1][1] + H * 0.05, 0.5 + t * 0.3);
    const xb = lerp(pts[6][0], pts[4][0], t), yb = yH - (i % 2) * H * 0.012;
    detalles.push(linea(`M${xa} ${ya}Q${(xa + xb) / 2 + sg * (i % 2 ? 6 : -6)} ${(ya + yb) / 2} ${xb} ${yb}`, sombraDe(k.principal), 1.4, 0.4));
  }
  return { atras: [], partes, detalles };
}

Object.assign(__e, { drapeado });
return __e;
})();

__m['prendas/chaqueta'] = (() => {
const __e = {};
const { suavizar, poligono, lerp, oscurecer, aclarar } = __m['util'];
const { yLargo, relleno, parte, linea, pliegues, perfilVestido, mangas, adornosTorso } = __m['prendas/comun'];

/** Prendas abiertas por delante: chaleco, chaqueta, abrigo, levita, bolero, bata. */
function chaqueta(M, p, k, ctx) {
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

Object.assign(__e, { chaqueta });
return __e;
})();

__m['prendas/delantal'] = (() => {
const __e = {};
const { suavizar, espejo } = __m['util'];
const { yLargo, relleno, parte, linea, pliegues } = __m['prendas/comun'];

function delantal(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'pantorrilla');
  const bib = p.peto !== false;
  const wt = w.cintura * 0.82, wb = w.cadera * 0.95 + (p.vuelo ?? 0.02) * H;
  const top = bib ? y.pecho + H * 0.005 : y.cintura;
  const pts = [[0, top, true], [bib ? w.pecho * 0.55 : wt, top, true], [wt, y.cintura - H * 0.01], [wb * 0.8, y.cadera], [wb, yH, true], [0, yH + H * 0.006, true]];
  const partes = [parte(suavizar(espejo(pts, cx)), F, k)];
  const detalles = [linea(`M${cx - w.cintura - 2} ${y.cintura + 1}L${cx + w.cintura + 2} ${y.cintura + 1}`, k.ribete ?? k.detalle, Math.max(3, H * 0.008), 1, { cap: 'butt' })];
  if (bib) for (const s of [1, -1]) detalles.push(linea(`M${cx + s * w.pecho * 0.55} ${top}L${cx + s * w.cuello * 1.2} ${y.hombro - H * 0.008}`, k.ribete ?? k.detalle, Math.max(2, H * 0.006), 1));
  detalles.push(...pliegues(M, pts.slice(1, -1).map(([x, yy]) => [x, yy]), y.cintura, yH, 3, k.principal, 0.006));
  return { atras: [], partes, detalles };
}

Object.assign(__e, { delantal });
return __e;
})();

__m['prendas/faja'] = (() => {
const __e = {};
const { poligono, oscurecer } = __m['util'];
const { perfilTorso } = __m['cuerpo'];
const { anchoEn, relleno, parte, linea, flecos } = __m['prendas/comun'];

function faja(M, p, k, ctx) {
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

Object.assign(__e, { faja });
return __e;
})();

__m['prendas/bandolera'] = (() => {
const __e = {};
const { poligono, oscurecer } = __m['util'];
const { linea } = __m['prendas/comun'];

function bandolera(M, p, k) {
  const { H, y, cx, w } = M;
  const sg = p.lado === 'der' ? 1 : -1;
  const d = `M${cx + sg * w.cuello * 1.5} ${y.hombro}Q${cx} ${y.pecho + H * 0.05} ${cx - sg * (w.cadera + H * 0.008)} ${y.cadera + H * 0.01}`;
  const det = [linea(d, k.principal, Math.max(4, (p.ancho ?? 0.017) * H), 1, { cap: 'butt', contorno: oscurecer(k.principal, 0.5) })];
  if (p.bolsa) det.push({ d: poligono([[cx - sg * w.cadera - 12, y.cadera], [cx - sg * w.cadera + 12, y.cadera], [cx - sg * w.cadera + 14, y.cadera + H * 0.06], [cx - sg * w.cadera - 14, y.cadera + H * 0.06]]), fill: k.secundario, stroke: oscurecer(k.secundario, 0.45), sw: 1.2 });
  return { atras: [], partes: [], detalles: det };
}

Object.assign(__e, { bandolera });
return __e;
})();

__m['prendas/chal'] = (() => {
const __e = {};
const { suavizar, espejo, lerp } = __m['util'];
const { yLargo, relleno, parte, pliegues, flecos } = __m['prendas/comun'];

function chal(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const F = relleno(p, k, ctx);
  const yH = yLargo(M, p.largo ?? 'cadera');
  const half = [
    [0, y.hombro - H * 0.01, true], [w.cuello * 1.3, y.hombro - H * 0.016], [w.hombro + H * 0.012, y.hombro + H * 0.01],
    [w.hombro + H * 0.035, lerp(y.hombro, y.codo, 0.5)], [w.hombro + H * 0.045, y.codo], [w.hombro + H * 0.035, yH, true],
    [w.pecho * 0.85, yH + H * 0.01, true], [w.pecho * 0.75, lerp(y.pecho, yH, 0.4)], [w.cuello * 1.2, y.pecho + H * 0.02], [0, y.hombro + H * 0.045, true],
  ];
  const partes = [parte(suavizar(espejo(half, cx)), F, k)];
  const detalles = [];
  if (p.flecos !== false && k.ribete) {
    for (const s of [1, -1]) detalles.push(...flecos({ ...M, cx: cx + s * (w.hombro + w.pecho * 0.8) / 2 }, yH + H * 0.006, (w.hombro - w.pecho * 0.8) / 2 + 10, k.ribete).slice(0, 6));
  }
  detalles.push(...pliegues(M, half.slice(2, 9).map(([x, yy]) => [x, yy]), y.hombro + H * 0.04, yH, 3, k.principal, 0.008).map((q) => q));
  return { atras: [], partes, detalles };
}

Object.assign(__e, { chal });
return __e;
})();

__m['prendas/cuello'] = (() => {
const __e = {};
const { suavizar, espejo, poligono, lerp, oscurecer, aclarar } = __m['util'];
const { parte, linea, sombraDe, flecos } = __m['prendas/comun'];

function cuello(M, p, k, ctx) {
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

Object.assign(__e, { cuello });
return __e;
})();

__m['prendas/calzado'] = (() => {
const __e = {};
const { suavizar, poligono, tubo, lerp, oscurecer } = __m['util'];
const { piernaLado, lineaEnY } = __m['cuerpo'];
const { parte, linea, sombraDe } = __m['prendas/comun'];

function calzado(M, p, k, ctx) {
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

Object.assign(__e, { calzado });
return __e;
})();

__m['prendas/accesorio'] = (() => {
const __e = {};
const { poligono, oscurecer } = __m['util'];
const { linea, sombraDe, brazoEnY } = __m['prendas/comun'];

function accesorio(M, p, k) {
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

Object.assign(__e, { accesorio });
return __e;
})();

__m['prendas/mono'] = (() => {
const __e = {};
const { tunica } = __m['prendas/tunica'];
const { pantalon } = __m['prendas/pantalon'];

/** Mono (jumpsuit) y ropa de trabajo de una pieza: torso y pantalón del mismo color y tela. */
function mono(M, p, k, ctx) {
  const t = tunica(M, { escote: 'alto', manga: 'larga', largo: 'cadera', cintura: 0.9, ...(p.torso ?? {}) }, k, ctx);
  const pa = pantalon(M, { largo: 'tobillo', ancho: 'recto', cintura: 'alta', ...(p.pantalon ?? {}) }, k, ctx);
  return { atras: t.atras, partes: [...pa.partes, ...t.partes], detalles: [...pa.detalles, ...t.detalles] };
}

Object.assign(__e, { mono });
return __e;
})();

__m['prendas'] = (() => {
const __e = {};
// Plantillas de prendas. Cada una recibe las medidas del cuerpo (M), sus parámetros (p), los colores ya
// resueltos (k: principal, secundario, ribete, detalle) y un contexto (ctx.patron) y devuelve:
//   { atras: [...], partes: [...], detalles: [...] }
// `atras` va detrás del cuerpo, `partes` son las piezas que se funden en una sola silueta con contorno y
// `detalles` (trazos, botones, ribetes) se dibujan encima. Las medidas son fracciones de la altura (H).

const { tunica } = __m['prendas/tunica'];
const { pantalon } = __m['prendas/pantalon'];
const { falda } = __m['prendas/falda'];
const { taparrabo } = __m['prendas/taparrabo'];
const { capa } = __m['prendas/capa'];
const { poncho } = __m['prendas/poncho'];
const { drapeado } = __m['prendas/drapeado'];
const { chaqueta } = __m['prendas/chaqueta'];
const { delantal } = __m['prendas/delantal'];
const { faja } = __m['prendas/faja'];
const { bandolera } = __m['prendas/bandolera'];
const { chal } = __m['prendas/chal'];
const { cuello } = __m['prendas/cuello'];
const { calzado } = __m['prendas/calzado'];
const { accesorio } = __m['prendas/accesorio'];
const { mono } = __m['prendas/mono'];


const PLANTILLAS = { tunica, pantalon, falda, taparrabo, capa, poncho, drapeado, chaqueta, delantal, faja, bandolera, chal, cuello, calzado, accesorio, mono };
const PLANTILLAS_ID = Object.keys(PLANTILLAS);

/** Orden de dibujo por ranura (menor = más al fondo). */
const ORDEN_RANURAS = {
  capa_atras: 10, calzado: 20, inferior: 30, torso: 40, sobre: 50, faja: 60, capa: 70, cuello: 80, accesorio: 90,
};

Object.assign(__e, { PLANTILLAS, PLANTILLAS_ID, ORDEN_RANURAS });
__e.yLargo = __m['prendas/comun'].yLargo;
__e.anchoEn = __m['prendas/comun'].anchoEn;
__e.finManga = __m['prendas/comun'].finManga;
__e.ESCOTES_SIN_HOMBRO = __m['prendas/comun'].ESCOTES_SIN_HOMBRO;
return __e;
})();

__m['tipos-de-ropa'] = (() => {
const __e = {};
// Fuente única de los tipos de ropa que entienden las plantillas (src/prendas.js): qué parámetros acepta cada una y
// qué valores puede tomar cada parámetro enumerado. Sirve para validar las prendas (un `manga: 'larg0'` ya no se
// dibuja en silencio con la manga por defecto) y para que el generador de ropa nueva use todas las variantes.
// Si agregas una variante a una plantilla, agrégala aquí; una prueba comprueba que todo el catálogo la cumple.

const { PATRONES_ID } = __m['patrones'];

const LARGOS = ['pecho', 'cintura', 'cadera', 'muslo-alto', 'muslo', 'rodilla', 'bajo-rodilla', 'pantorrilla', 'tobillo', 'suelo'];

/** Valores permitidos de cada parámetro enumerado (los demás son números, booleanos o textos libres). */
const TIPOS = {
  escote: ['redondo', 'v', 'cuadrado', 'barco', 'alto', 'ojal', 'tirantes', 'corazon', 'halter', 'asimetrico'],
  manga: ['sin', 'cap', 'corta', 'codo', 'tres-cuartos', 'larga', 'ajustada', 'larga-mano', 'ancha', 'campana', 'pagoda', 'globo', 'abullonada', 'farol', 'kimono'],
  bajo: ['recto', 'curvo', 'ondulado', 'dentado', 'flecos', 'pico', 'curvo-alto'],
  forma: ['campana', 'sirena', 'trapecio', 'globo', 'tubo'],
  cintura: ['natural', 'baja', 'alta'],
  anchoPantalon: ['ajustado', 'pitillo', 'tubo', 'recto', 'amplio', 'palazzo', 'bombacho', 'jodhpur', 'campana'],
  bolsillosPantalon: ['cargo', 'jean', 'pliegues'],
  bolsillosTorso: ['pecho', 'cadera', 'canguro', 'ambos'],
  pieles: ['cuello', 'puños', 'bajo', 'todo'],
  lado: ['izq', 'der'],
  abertura: ['frente', 'lado'],
  cierre: ['broche', 'fibula', 'abierta'],
  cuello: ['collar', 'gargantilla', 'cuentas-largas', 'pectoral', 'gorguera', 'alto', 'bufanda', 'lazo', 'corbata', 'piel', 'esclavina', 'chalina', 'triangulo', 'babero', 'trapelacucha'],
  calzado: ['descalzo', 'sandalias', 'sandalia-romana', 'chancletas', 'zapatos', 'oxford', 'tacones', 'mocasines', 'zuecos', 'zapatillas', 'botines', 'botas', 'botas-altas', 'polainas'],
  accesorio: ['brazaletes', 'bolso', 'tirantes', 'cinta-frente'],
  fajaY: ['cintura', 'cadera', 'pecho'],
};

const COMUNES = ['patron']; // todas las plantillas que rellenan con tela aceptan un patrón

/** Parámetros que lee cada plantilla. `mono` contiene dos juegos de parámetros anidados (torso y pantalon). */
const PARAMS = {
  tunica: ['bajo', 'bandaPatron', 'bandas', 'bandasAncho', 'bolsillos', 'bordadoPecho', 'botones', 'capucha', 'cintura', 'cinturon', 'cordones', 'cruzada', 'cuelloCamisa', 'escote', 'flecos', 'forma', 'holgura', 'lado', 'largo', 'manga', 'pieles', 'pliegues', 'prof', 'puños', 'ribete', 'ribeteBajo', 'volantes', 'vuelo', ...COMUNES],
  pantalon: ['ancho', 'bolsillos', 'cintura', 'cordon', 'holgura', 'largo', 'puños', ...COMUNES],
  falda: ['abertura', 'bajo', 'bandaPatron', 'cintura', 'flecos', 'forma', 'holgura', 'largo', 'pliegues', 'ribeteBajo', 'volantes', 'vuelo', ...COMUNES],
  taparrabo: ['ancho', 'largo', 'punta', 'ribeteBajo', ...COMUNES],
  capa: ['ancho', 'bajo', 'cierre', 'largo', 'ribete', 'ribeteBajo', ...COMUNES],
  poncho: ['ancho', 'flecos', 'largo', 'listas', 'punta', ...COMUNES],
  drapeado: ['lado', 'largo', 'orla', 'pliegues', ...COMUNES],
  chaqueta: ['abertura', 'aberturaBajo', 'bajo', 'bolsillos', 'bordadoBorde', 'botones', 'capucha', 'cintura', 'cinturon', 'cordones', 'cuelloCamisa', 'holgura', 'largo', 'manga', 'pieles', 'pliegues', 'puños', 'ribete', 'solapa', 'solapaColor', 'vuelo', ...COMUNES],
  delantal: ['largo', 'peto', 'vuelo', ...COMUNES],
  faja: ['ancho', 'cuelga', 'doble', 'hebilla', 'y', ...COMUNES],
  bandolera: ['ancho', 'bolsa', 'lado'],
  chal: ['flecos', 'largo', ...COMUNES],
  cuello: ['colgante', 'cuentas', 'rayas', 'tipo'],
  calzado: ['hebilla', 'tipo'],
  accesorio: ['alto', 'lado', 'tipo'],
  mono: ['pantalon', 'torso'],
};

// Enumeraciones por plantilla y parámetro: [plantilla][parámetro] = lista de valores.
const ENUMS = {
  tunica: { escote: TIPOS.escote, manga: TIPOS.manga, bajo: TIPOS.bajo, forma: TIPOS.forma, bolsillos: TIPOS.bolsillosTorso, pieles: TIPOS.pieles, cruzada: TIPOS.lado, lado: TIPOS.lado },
  pantalon: { ancho: TIPOS.anchoPantalon, cintura: TIPOS.cintura, bolsillos: TIPOS.bolsillosPantalon },
  falda: { bajo: TIPOS.bajo, forma: TIPOS.forma, cintura: TIPOS.cintura, abertura: TIPOS.abertura },
  capa: { bajo: TIPOS.bajo, cierre: TIPOS.cierre },
  drapeado: { lado: TIPOS.lado },
  chaqueta: { manga: TIPOS.manga, bajo: TIPOS.bajo, bolsillos: TIPOS.bolsillosTorso, pieles: TIPOS.pieles },
  faja: { y: TIPOS.fajaY, cuelga: TIPOS.lado },
  bandolera: { lado: TIPOS.lado },
  cuello: { tipo: TIPOS.cuello },
  calzado: { tipo: TIPOS.calzado },
  accesorio: { tipo: TIPOS.accesorio, lado: TIPOS.lado },
};

const PARAMS_DE_PATRON = ['bandaPatron', 'bordadoPecho'];

/** Distancia de edición entre dos textos cortos, para sugerir «¿quisiste decir…?». */
function distancia(a, b) {
  const d = Array.from({ length: a.length + 1 }, (_, i) => [i, ...Array(b.length).fill(0)]);
  for (let j = 1; j <= b.length; j++) d[0][j] = j;
  for (let i = 1; i <= a.length; i++) for (let j = 1; j <= b.length; j++) d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
  return d[a.length][b.length];
}
const parecido = (x, lista) => { const m = lista.map((v) => [v, distancia(String(x), v)]).sort((p, q) => p[1] - q[1])[0]; return m && m[1] <= 2 ? m[0] : null; };

/**
 * Revisa los parámetros de una prenda y devuelve la lista de problemas (vacía si está bien):
 * parámetros que la plantilla no lee, valores fuera de las listas y patrones que no existen.
 */
function problemasDeParams(plantilla, params = {}, ruta = plantilla) {
  const permitidos = PARAMS[plantilla];
  if (!permitidos) return [`Plantilla desconocida: ${plantilla}`];
  const out = [];
  for (const [k, v] of Object.entries(params)) {
    if (!permitidos.includes(k)) { const sug = parecido(k, permitidos); out.push(`${ruta}: la plantilla no lee el parámetro «${k}»${sug ? ` (¿quisiste decir «${sug}»?)` : ''}.`); continue; }
    if (plantilla === 'mono') { out.push(...problemasDeParams(k === 'torso' ? 'tunica' : 'pantalon', v, `${ruta}.${k}`)); continue; }
    const lista = ENUMS[plantilla]?.[k];
    if (lista && v !== undefined && v !== false && v !== null && !lista.includes(v)) { const sug = typeof v === 'string' ? parecido(v, lista) : null; out.push(`${ruta}: «${k}» = ${JSON.stringify(v)} no es un valor válido${sug ? ` (¿quisiste decir «${sug}»?)` : ''}. Valores posibles: ${lista.join(', ')}.`); }
    if (k === 'largo' && v !== undefined && typeof v !== 'number' && !LARGOS.includes(v)) { const sug = typeof v === 'string' ? parecido(v, LARGOS) : null; out.push(`${ruta}: «largo» = ${JSON.stringify(v)} no es un largo válido${sug ? ` (¿quisiste decir «${sug}»?)` : ''}. Valores posibles: ${LARGOS.join(', ')} o un número.`); }
    if (k === 'patron' && v) { const t = v.tipo ?? v; if (!PATRONES_ID.includes(t)) out.push(`${ruta}: el patrón «${t}» no existe. Patrones: ${PATRONES_ID.join(', ')}.`); }
    if (PARAMS_DE_PATRON.includes(k) && v && !PATRONES_ID.includes(v)) out.push(`${ruta}: el patrón «${v}» de «${k}» no existe.`);
  }
  return out;
}

/** Lanza un error con todos los problemas de una prenda `{ id, plantilla, params }`, si los hay. */
function validarPrenda(prenda) {
  const problemas = problemasDeParams(prenda.plantilla, prenda.params ?? {}, `Prenda «${prenda.id ?? prenda.plantilla}»`);
  if (problemas.length) throw new Error(problemas.join(' '));
}

Object.assign(__e, { LARGOS, TIPOS, PARAMS, problemasDeParams, validarPrenda });
return __e;
})();

__m['svg'] = (() => {
const __e = {};
// Dibuja una persona descrita por `generarPersona` como SVG.
//
// Sin bordes: el volumen sale de degradados propios de cada color (luz desde arriba a la izquierda, sombra y calor
// en el lado opuesto) y de sombras suaves que cada capa proyecta sobre las de abajo (la manga sobre el torso, la
// cabeza sobre el cuello, el pelo sobre la frente). Cada forma se define una vez y se reutiliza con <use>.

const { LIENZO, medidas, deformar, trazoTorso, trazoBrazo, trazoPierna, trazoMano, trazoPie, trazoCuello, trazoAntebrazo } = __m['cuerpo'];
const { geometriaCabeza, trazoCabeza, trazoOrejas, rasgosRostro, PEINADOS, BARBAS, TOCADOS } = __m['cabeza'];
const { PLANTILLAS, ORDEN_RANURAS, finManga } = __m['prendas'];
const { PATRONES } = __m['patrones'];
const { validarPrenda } = __m['tipos-de-ropa'];
const { mezclar, escapar, num } = __m['util'];

function contexto(pref) {
  const defs = new Map();
  return {
    defs,
    patron(tipo, a, b, escala = 1) {
      const f = PATRONES[tipo] ?? PATRONES.rayas;
      const e = Math.round(escala * 100) / 100;
      const id = `${pref}-p-${tipo}-${a.slice(1)}-${b.slice(1)}-${String(e).replace('.', '_')}`;
      if (!defs.has(id)) {
        const [w, h, cuerpo] = f(a, b, e);
        defs.set(id, `<pattern id="${id}" width="${num(w)}" height="${num(h)}" patternUnits="userSpaceOnUse">${cuerpo}</pattern>`);
      }
      return `url(#${id})`;
    },
  };
}

/** Hash corto y determinista (FNV-1a) para que los id de cada figura no choquen con los de otra en la misma página. */
function hashCorto(texto) {
  let h = 0x811c9dc5;
  for (let i = 0; i < texto.length; i++) { h ^= texto.charCodeAt(i); h = Math.imul(h, 0x01000193) >>> 0; }
  return h.toString(36);
}

const attr = (o) => Object.entries(o).filter(([, v]) => v !== undefined && v !== null && v !== false).map(([k, v]) => ` ${k}="${escapar(v)}"`).join('');

/** Gira una ruta de comandos absolutos (M, L, C, Z) `ang` grados alrededor de (cx, cy). */
function rotarRuta(d, ang, cx, cy) {
  const r = (ang * Math.PI) / 180, co = Math.cos(r), si = Math.sin(r);
  return d.replace(/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/g, (_, a, b) => {
    const x = Number(a) - cx, y = Number(b) - cy;
    return `${num(cx + x * co - y * si)} ${num(cy + x * si + y * co)}`;
  });
}

/** Aplica una deformación (x, y) → (x', y') a todos los puntos de una ruta de comandos absolutos. */
function deformarRuta(d, f) {
  return d.replace(/(-?\d+(?:\.\d+)?(?:e[-+]?\d+)?) (-?\d+(?:\.\d+)?(?:e[-+]?\d+)?)/g, (_, a, b) => { const [x, y] = f(Number(a), Number(b)); return `${num(x)} ${num(y)}`; });
}

function trazoDe(s) {
  const o = { d: s.d, fill: s.fill ?? 'none' };
  if (s.stroke) {
    o.stroke = s.stroke;
    o['stroke-width'] = s.sw ?? 1;
    o['stroke-linecap'] = s.cap ?? 'round';
    o['stroke-linejoin'] = 'round';
    if (s.dash) o['stroke-dasharray'] = s.dash;
  }
  if (s.op !== undefined && s.op !== 1) o.opacity = s.op;
  return o;
}

// Campos que solo describen a la persona en texto: no cambian el dibujo, así que no entran en el prefijo de ids.
const CAMPOS_DESCRIPTIVOS = new Set(['titulo', 'descripcion', 'estimaciones', 'avisos', 'notas', 'coloresPrendas', 'nombresPaleta', 'confianza', 'fuentes', 'fondo', 'colorInfo', 'nombre']);

/** Un estilo de peinado, barba o tocado que no existe, o una prenda con parámetros mal escritos, lanza un error en vez de dibujarse distinto. */
function validarPersona(persona) {
  const quiero = (campo, id, tabla, extra = []) => { if (id !== undefined && id !== null && !(id in tabla) && !extra.includes(id)) throw new Error(`${campo} desconocido: ${JSON.stringify(id)}. Disponibles: ${Object.keys(tabla).join(', ')}.`); };
  quiero('Peinado', persona.cabello?.estilo, PEINADOS);
  quiero('Barba', persona.barba?.estilo, BARBAS);
  quiero('Tocado', persona.tocado?.id, TOCADOS);
  for (const prenda of persona.prendas ?? []) validarPrenda(prenda);
}

/**
 * Versión más liviana (unos 7 % menos): las coordenadas de las formas pasan de 2 a 1 decimal (0,1 unidad de un lienzo
 * de 600 de ancho). Solo toca el atributo `d`; colores, opacidades y degradados quedan igual.
 */
function compactar(svg) {
  return svg.replace(/ d="([^"]*)"/g, (_, d) => ` d="${d.replace(/-?\d+\.\d\d+/g, (m) => String(Math.round(Number(m) * 10) / 10))}"`);
}

/** Devuelve el SVG (texto) de la persona descrita. `opciones.compacto` da la versión liviana (ver `compactar`). */
function renderizar(persona, opciones = {}) {
  validarPersona(persona);
  const nivel = opciones.detalle ?? persona.detalle ?? 'medio';
  const contorno = opciones.contorno ?? persona.contorno ?? 'ninguno'; // 'ninguno' | 'suave'
  validarPersona(persona);
  const fondo = opciones.fondo ?? persona.fondo ?? 'ninguno';
  const M = medidas(persona.cuerpo);
  const G = geometriaCabeza(M, persona.rostro);
  const piel = persona.piel;
  const { cx } = M;
  const alto = nivel === 'alto';
  const plano = nivel === 'minimo';

  // --- Formas, degradados y sombras ---
  // Las formas y el recorte son propios de cada figura: llevan un prefijo derivado de su contenido.
  const pref = `x${hashCorto(JSON.stringify([persona, nivel, contorno], (k, v) => (CAMPOS_DESCRIPTIVOS.has(k) ? undefined : v)))}`;
  const ctx = contexto(pref);
  const caminos = [], idsSilueta = [], degradados = new Map();
  let nForma = 0;
  const def = deformar(M);
  const W = def ? (d) => deformarRuta(d, def) : (d) => d;
  const reg = (d0) => { const d = W(d0); const id = `${pref}f${nForma++}`; caminos.push(`<path id="${id}" d="${d}"/>`); idsSilueta.push(id); return id; };

  const TONOS = {
    piel: { sombra: '#5a1c10', luz: '#ffe6c4' },
    pelo: { sombra: '#0c0710', luz: '#eadfcf' },
    tela: { sombra: '#1a1230', luz: '#fff3da' },
  };
  /** Degradado propio de un color: luz a la izquierda, terminador y sombra cálida a la derecha. */
  const grad = (hex, tipo) => {
    const id = `${pref}-g-${alto ? 'a' : 'm'}-${tipo}-${hex.slice(1)}`;
    if (!degradados.has(id)) {
      const t = TONOS[tipo] ?? TONOS.tela, e = alto ? 1.2 : 1;
      const st = (o, c) => `<stop offset="${o}" stop-color="${c}"/>`;
      degradados.set(id, `<linearGradient id="${id}" x1="0" y1="0" x2="1" y2="0.22">${st(0, mezclar(hex, t.sombra, 0.12 * e))}${st(0.13, mezclar(hex, t.luz, 0.14))}${st(0.33, mezclar(hex, t.luz, 0.28 * e))}${st(0.58, hex)}${st(0.82, mezclar(hex, t.sombra, 0.3 * e))}${st(1, mezclar(hex, t.sombra, 0.52 * e))}</linearGradient>`);
    }
    return `url(#${id})`;
  };

  const sombraProyectada = (ids) =>
    `<g clip-path="url(#${pref}s)" opacity="${alto ? 0.44 : 0.34}"><g filter="url(#${pref}-bl)" transform="translate(3.5 5)" fill="#1c0f0c">${ids.map((id) => `<use href="#${id}"/>`).join('')}</g></g>`;

  /** Capa de formas: su sombra proyectada sobre lo de abajo y luego los rellenos con degradado. */
  const capa = (piezas, tipo = 'tela') => {
    if (!piezas.length) return '';
    const ids = piezas.map((p) => reg(p.d));
    let out = plano ? '' : sombraProyectada(ids);
    piezas.forEach((p, i) => {
      const op = p.op !== undefined && p.op !== 1 ? p.op : undefined;
      const esHex = typeof p.fill === 'string' && p.fill.startsWith('#');
      const rellena = plano || p.plano || !esHex ? p.fill : grad(p.fill, tipo);
      out += `<use${attr({ href: `#${ids[i]}`, fill: rellena, opacity: op })}/>`;
      if (!plano && !p.plano && p.volumen !== false) {
        if (!esHex) out += `<use href="#${ids[i]}" fill="url(#${pref}-sh)" opacity="0.6"/>`;
        if (alto) out += `<use href="#${ids[i]}" fill="url(#${pref}-sv)" opacity="0.5"/>`;
      }
      if (contorno === 'suave' && esHex) out += `<use${attr({ href: `#${ids[i]}`, fill: 'none', stroke: mezclar(p.fill, '#000000', 0.35), 'stroke-width': 1, opacity: 0.35 })}/>`;
    });
    return out;
  };

  /** Detalles (trazos, botones, ribetes): se dibujan tal cual; los marcados `suave` van desenfocados, como pliegues. */
  const detalles = (lista) => {
    let duros = '', suaves = '';
    for (const s of lista) {
      const trazo = `<path${attr(trazoDe({ ...s, d: W(s.d) }))}/>`;
      if (s.suave && !plano) suaves += trazo; else duros += trazo;
    }
    return duros + (suaves ? `<g filter="url(#${pref}-bs)">${suaves}</g>` : '');
  };

  /** Capas con relleno y trazos sueltos (cabello, barba, tocado). */
  const figuras = (lista, tipo, girar) => {
    let out = '';
    for (const s0 of lista) {
      const s = girar ? { ...s0, d: girar(s0.d) } : s0;
      if (s.fill === 'none') out += detalles([s]);
      else out += capa([s], tipo);
    }
    return out;
  };

  // --- Pose de la cabeza: se giran las formas (no un grupo) para que sombras y recortes sigan coincidiendo ---
  const angCabeza = M.pose.cabeza ?? 0;
  const girar = Math.abs(angCabeza) > 0.05 ? (d) => rotarRuta(d, angCabeza, cx, M.y.hombro) : null;
  const giraLista = (l) => (girar ? l.map((s) => ({ ...s, d: girar(s.d) })) : l);

  // --- Prendas ---
  const prendas = persona.prendas.map((pr) => {
    const f = PLANTILLAS[pr.plantilla];
    if (!f) throw new Error(`Plantilla desconocida: ${pr.plantilla}`);
    return { ...pr, r: f(M, pr.params ?? {}, pr.colores, ctx) };
  });

  // --- Cabello, barba y tocado ---
  const colorPelo = persona.cabello?.color ?? '#3b2a20';
  const peinado = (PEINADOS[persona.cabello?.estilo] ?? PEINADOS.calvo)(G, colorPelo);
  const barba = (BARBAS[persona.barba?.estilo] ?? BARBAS.ninguna)(G, persona.barba?.color ?? colorPelo).flat();
  const toc = persona.tocado ? (TOCADOS[persona.tocado.id] ?? TOCADOS.ninguno)(G, persona.tocado.colores) : { atras: [], frente: [] };

  let cuerpo = '';
  // 1. Detrás: capas, cabello largo, tocado trasero
  for (const p of prendas.filter((q) => q.r.atras?.length)) {
    cuerpo += capa(p.r.atras.filter((s) => !s.atras));
    cuerpo += detalles(p.r.atras.filter((s) => s.atras));
  }
  cuerpo += figuras(toc.atras, 'tela', girar);
  cuerpo += figuras(peinado.atras, 'pelo', girar);

  // 2. Piel: piernas y pies, torso, brazos, manos y cuello, cada uno proyectando su sombra sobre lo anterior
  const sp = { fill: piel };
  const brazosPiel = [1, -1].map((s) => ({ d: trazoBrazo(M, s), ...sp }));
  const manosPiel = [1, -1].filter((s) => s !== M.saludoLado).flatMap((s) => trazoMano(M, s).map((d) => ({ d, ...sp })));
  cuerpo += capa([...[1, -1].map((s) => ({ d: trazoPierna(M, s), ...sp })), ...[1, -1].map((s) => ({ d: trazoPie(M, s), ...sp }))], 'piel');
  cuerpo += capa([{ d: trazoTorso(M), ...sp }], 'piel');
  for (const b of brazosPiel) cuerpo += capa([b], 'piel');
  cuerpo += capa(manosPiel, 'piel');
  cuerpo += capa([{ d: trazoCuello(M), ...sp }], 'piel');

  // 3. Ropa por ranura; las mangas proyectan su propia sombra sobre el torso
  const ordenadas = prendas.slice().sort((a, b) => (ORDEN_RANURAS[a.ranura] ?? 50) - (ORDEN_RANURAS[b.ranura] ?? 50));
  for (const p of ordenadas) {
    cuerpo += `<g data-prenda="${escapar(p.id)}">`;
    let lote = [];
    for (const pieza of p.r.partes) {
      if (pieza.proyecta) { cuerpo += capa(lote); lote = []; cuerpo += capa([pieza]); } else lote.push(pieza);
    }
    cuerpo += capa(lote);
    cuerpo += detalles(p.r.detalles);
    cuerpo += '</g>';
  }

  // 3b. Mano en saludo: encima de la ropa para que ni mangas ni mantos la tapen.
  if (M.saludoLado) {
    const piezas = [];
    if (prendas.some((p) => p.ranura === 'capa')) {
      const finMax = Math.max(M.y.codo, ...prendas.filter((p) => p.params?.manga && p.params.manga !== 'sin').map((p) => finManga(M, p.params.manga)));
      if (finMax < M.y.muneca - 2) piezas.push({ d: trazoAntebrazo(M, M.saludoLado, finMax), ...sp });
    }
    for (const d of trazoMano(M, M.saludoLado)) piezas.push({ d, ...sp });
    cuerpo += `<g data-saludo="${M.saludoLado === -1 ? 'derecha' : 'izquierda'}">${capa(piezas, 'piel')}</g>`;
  }

  // 4. Cabeza
  const cabezaPieza = { d: trazoCabeza(G), ...sp };
  const orejas = trazoOrejas(G).map((d) => ({ d, ...sp }));
  cuerpo += capa(giraLista([...orejas, cabezaPieza]), 'piel');
  if (persona.rostro?.rasgos) cuerpo += detalles(giraLista(rasgosRostro(G, piel)));
  cuerpo += figuras(barba, 'pelo', girar);
  cuerpo += figuras(peinado.frente, 'pelo', girar);
  cuerpo += figuras(toc.frente, 'tela', girar);

  // --- Sombra en el suelo ---
  const suelo = plano ? '' : `<ellipse cx="${cx}" cy="${LIENZO.suelo + 1}" rx="${num(M.H * 0.2)}" ry="${num(M.H * 0.017)}" fill="url(#${pref}-su)"/>`;

  const inclina = M.pose.inclinacion ?? 0;
  const defs = [
    `<linearGradient id="${pref}-sh" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#000" stop-opacity="0.18"/><stop offset="0.25" stop-color="#fff" stop-opacity="0.16"/><stop offset="0.6" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.4"/></linearGradient>`,
    `<linearGradient id="${pref}-sv" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity="0.14"/><stop offset="0.5" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.22"/></linearGradient>`,
    `<radialGradient id="${pref}-su"><stop offset="0" stop-color="#1c0f0c" stop-opacity="0.34"/><stop offset="0.6" stop-color="#1c0f0c" stop-opacity="0.14"/><stop offset="1" stop-color="#1c0f0c" stop-opacity="0"/></radialGradient>`,
    `<filter id="${pref}-bl" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="3.6"/></filter>`,
    `<filter id="${pref}-bs" x="-5%" y="-5%" width="110%" height="110%"><feGaussianBlur stdDeviation="0.9"/></filter>`,
    ...degradados.values(),
    ...ctx.defs.values(),
    ...caminos,
    `<clipPath id="${pref}s">${idsSilueta.map((id) => `<use href="#${id}"/>`).join('')}</clipPath>`,
  ].join('');
  const bg = fondo && fondo !== 'ninguno' ? `<rect width="${LIENZO.ancho}" height="${LIENZO.alto}" fill="${escapar(fondo)}"/>` : '';
  const titulo = persona.titulo ? `<title>${escapar(persona.titulo)}</title>` : '';
  const desc = persona.descripcion ? `<desc>${escapar(persona.descripcion)}</desc>` : '';
  const figura = Math.abs(inclina) > 0.02 ? `<g transform="rotate(${num(inclina)} ${cx} ${LIENZO.suelo})">${cuerpo}</g>` : cuerpo;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${LIENZO.ancho} ${LIENZO.alto}" role="img">${titulo}${desc}<defs>${defs}</defs>${bg}${suelo}${figura}</svg>`;
  return opciones.compacto ? compactar(svg) : svg;
}

Object.assign(__e, { compactar, renderizar });
return __e;
})();

__m['datos/etnias-ampliadas'] = (() => {
const __e = {};
// Etnias y culturas de la ampliación. Misma forma y misma advertencia que etnias.js: promedios aproximados de
// poblaciones, no reglas sobre una persona. `conf: 'baja'` marca los pueblos de los que el catálogo solo tiene una
// imagen genérica o de los que las cifras de estatura son más inciertas.

const { E, OSC, CLARO, MED, MESTIZO } = __m['datos/ayuda'];

const NEG = [['negro', 9], ['castano-oscuro', 1]];
const PIEL_OSC = 'canela bronce cobre';

const ETNIAS_AMPLIADAS = [
  // ----- Europa rural -----
  E('vasco', 'Vasco', { grupo: 'Europa', regiones: 'europa-campesina', prefijos: 'vasco', pieles: 'porcelana clara clara-calida', cabellos: MED, peinados: 'corto trenza-una recogido', barba: 0.4, altura: [157, 169] }),
  E('andaluz', 'Andaluz', { grupo: 'Europa', regiones: 'europa-campesina', prefijos: 'andaluz', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto recogido', barba: 0.5, altura: [156, 168] }),
  E('gallego', 'Gallego', { grupo: 'Europa', regiones: 'europa-campesina', prefijos: 'gallego gallega', pieles: 'porcelana clara clara-calida', cabellos: MED, peinados: 'corto trenza-una recogido', barba: 0.4, altura: [156, 168] }),
  E('italiano', 'Italiano', { grupo: 'Europa', regiones: 'europa-campesina', prefijos: 'italian', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto trenza-una recogido', barba: 0.45, altura: [157, 169] }),
  E('croata', 'Croata', { grupo: 'Europa', regiones: 'europa-campesina', prefijos: 'croata', pieles: 'porcelana clara clara-calida', cabellos: [['castano', 3], ['castano-claro', 3], ['castano-oscuro', 3], ['rubio-oscuro', 1.5], ['negro', 1]], peinados: 'corto trenza-una recogido', barba: 0.45, altura: [165, 178], nota: 'Los croatas figuran entre los pueblos más altos de Europa.' }),
  E('aleman-suizo', 'Alemán del sur y suizo', { grupo: 'Europa', regiones: 'europa-campesina', prefijos: 'bavar', pieles: 'porcelana clara', cabellos: CLARO, peinados: 'corto trenzas-dos corona-trenzas', barba: 0.4, altura: [164, 176] }),
  E('frances', 'Francés', { grupo: 'Europa', regiones: 'europa-campesina', prefijos: 'frances', pieles: 'porcelana clara clara-calida', cabellos: CLARO, peinados: 'corto recogido', barba: 0.4, altura: [160, 171] }),
  E('irlandes', 'Irlandés', { grupo: 'Europa', regiones: 'europa-campesina', prefijos: 'irland', pieles: 'porcelana clara', cabellos: [['pelirrojo', 2.5], ['castano', 3], ['castano-claro', 2], ['castano-oscuro', 2], ['negro', 1.5]], peinados: 'corto trenza-una recogido', barba: 0.4, altura: [162, 174] }),
  E('godo-anglosajon', 'Visigodo y anglosajón', { grupo: 'Europa', regiones: 'germano', prefijos: 'visigod anglosaj', pieles: 'porcelana clara', cabellos: CLARO, peinados: 'largo-liso corto trenza-una', barba: 0.7, altura: [158, 171], conf: 'baja' }),
  // ----- Caucaso, Asia central, Siberia, Himalaya -----
  E('caucasico', 'Georgiano, armenio y circasiano', { grupo: 'Cáucaso', regiones: 'caucaso', pieles: 'porcelana clara clara-calida oliva-clara', cabellos: MED, peinados: 'corto trenzas-dos recogido', barba: 0.6, altura: [158, 171] }),
  E('mongol', 'Mongol', { grupo: 'Asia central', regiones: 'asia-central', prefijos: 'mongol', pieles: 'clara-calida oliva-clara oliva', cabellos: OSC, peinados: 'trenza-una trenzas-dos monos-laterales corto', barba: 0.2, altura: [155, 167] }),
  E('kazajo-turquico', 'Kazajo y pueblos túrquicos de Asia central', { grupo: 'Asia central', regiones: 'asia-central', prefijos: 'kazaj', pieles: 'clara-calida oliva-clara oliva', cabellos: OSC, peinados: 'corto trenzas-dos recogido', barba: 0.35, altura: [157, 169] }),
  E('siberiano-yakuto', 'Pueblos de Siberia (yakutos, evenkos, nenets)', { grupo: 'Siberia', regiones: 'siberia', pieles: 'clara clara-calida oliva-clara', cabellos: OSC, peinados: 'trenzas-dos largo-liso corto', barba: 0.15, altura: [152, 164], corpulencia: [['media', 3], ['robusta', 3]] }),
  E('tibetano', 'Tibetano', { grupo: 'Himalaya', regiones: 'asia-himalaya', pieles: 'clara-calida oliva-clara oliva canela', cabellos: OSC, peinados: 'trenza-una trenzas-dos corto', barba: 0.15, altura: [155, 167], corpulencia: [['media', 3], ['robusta', 2.5]] }),
  // ----- India y sudeste asiático -----
  E('rajasthani', 'Rajastaní y rajput', { grupo: 'Asia', regiones: 'asia-sur-regional', prefijos: 'rajput rajasthan', pieles: 'oliva canela bronce', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'trenza-una recogido corto', barba: 0.6, altura: [152, 167] }),
  E('punjabi-sij', 'Panyabí (incluye sijs)', { grupo: 'Asia', regiones: 'asia-sur-regional', prefijos: 'punjab', pieles: 'oliva-clara oliva canela', cabellos: [['negro', 8], ['negro-azulado', 1], ['castano-oscuro', 1]], peinados: 'trenza-una recogido corto', barba: 0.8, altura: [155, 171] }),
  E('bengali', 'Bengalí', { grupo: 'Asia', regiones: 'asia-sur-regional', prefijos: 'bengal', pieles: 'oliva canela bronce cobre', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'trenza-una recogido corto', barba: 0.4, altura: [149, 162] }),
  E('tamil', 'Tamil', { grupo: 'Asia', regiones: 'asia-sur-regional', prefijos: 'tamil', pieles: 'canela bronce cobre castana', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'trenza-una recogido corto', barba: 0.4, altura: [151, 164] }),
  E('cachemir', 'Cachemiro', { grupo: 'Asia', regiones: 'asia-sur-regional', prefijos: 'cachemir', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'trenzas-dos corto', barba: 0.7, altura: [155, 168] }),
  E('vietnamita', 'Vietnamita', { grupo: 'Asia', regiones: 'sudeste-regional', prefijos: 'vietnam', pieles: 'clara-calida oliva-clara oliva', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'largo-liso recogido corto', barba: 0.15, altura: [152, 164] }),
  E('tailandes', 'Tailandés', { grupo: 'Asia', regiones: 'sudeste-regional', prefijos: 'tailand', pieles: 'oliva-clara oliva canela', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'recogido mono-alto corto', barba: 0.15, altura: [153, 166], conf: 'baja' }),
  E('filipino', 'Filipino', { grupo: 'Asia', regiones: 'sudeste-regional', prefijos: 'filipin', pieles: 'oliva canela bronce', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'recogido largo-liso corto', barba: 0.15, altura: [149, 162] }),
  E('birmano', 'Birmano', { grupo: 'Asia', regiones: 'sudeste-regional', prefijos: 'birman', pieles: 'oliva canela bronce', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'recogido mono-alto corto', barba: 0.15, altura: [150, 163] }),
  E('javanes', 'Javanés', { grupo: 'Asia', regiones: 'sudeste-regional', prefijos: 'javan', pieles: 'oliva canela bronce', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'recogido mono-alto corto', barba: 0.15, altura: [148, 160] }),
  E('ainu', 'Ainu', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'ainu', pieles: 'clara clara-calida oliva-clara', cabellos: [['negro', 6], ['castano-oscuro', 3], ['negro-azulado', 1]], peinados: 'largo-liso largo-ondulado', barba: 0.8, altura: [150, 161], conf: 'baja' }),
  E('manchu', 'Manchú', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'manchu', pieles: 'clara clara-calida', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'trenza-una recogido', barba: 0.15, altura: [156, 168] }),
  // ----- Mundo antiguo -----
  E('minoico', 'Minoico', { grupo: 'Mundo antiguo', regiones: 'mundo-antiguo', prefijos: 'minoic', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'rizos largo-ondulado', barba: 0.05, altura: [152, 164], conf: 'baja' }),
  E('fenicio-cartagines', 'Fenicio y cartaginés', { grupo: 'Mundo antiguo', regiones: 'mundo-antiguo', prefijos: 'fenici', pieles: 'oliva-clara oliva canela', cabellos: MED, peinados: 'corto rizos recogido', barba: 0.7, altura: [153, 166] }),
  E('persa-aquemenida', 'Persa antiguo (aqueménida)', { grupo: 'Mundo antiguo', regiones: 'mundo-antiguo', prefijos: 'persa-antigu', pieles: 'oliva-clara oliva canela', cabellos: [['negro', 7], ['castano-oscuro', 2]], peinados: 'rizos corto recogido', barba: 0.85, altura: [154, 167] }),
  E('escita', 'Escita', { grupo: 'Mundo antiguo', regiones: 'mundo-antiguo', prefijos: 'escita', pieles: 'clara clara-calida oliva-clara', cabellos: [['castano', 3], ['castano-claro', 2], ['castano-oscuro', 2], ['rubio-oscuro', 1.5], ['negro', 1]], peinados: 'largo-liso corto trenzas-dos', barba: 0.8, altura: [158, 171], conf: 'baja' }),
  E('etrusco', 'Etrusco', { grupo: 'Mundo antiguo', regiones: 'mundo-antiguo', prefijos: 'etrusc', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto rizos largo-ondulado', barba: 0.3, altura: [153, 166] }),
  E('ibero', 'Íbero', { grupo: 'Mundo antiguo', regiones: 'mundo-antiguo', prefijos: 'iber', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'largo-liso corto recogido', barba: 0.4, altura: [153, 166], conf: 'baja' }),
  E('hitita', 'Hitita', { grupo: 'Mundo antiguo', regiones: 'mundo-antiguo', prefijos: 'hitita', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'largo-liso corto trenzas-dos', barba: 0.2, altura: [152, 165], conf: 'baja' }),
  E('kushita', 'Kushita de Nubia (histórico)', { grupo: 'Mundo antiguo', regiones: 'africa-nilo', prefijos: 'kushit', pieles: 'castana ebano-claro ebano', cabellos: NEG, peinados: 'rapado corto trenzas-pegadas', barba: 0.05, altura: [155, 168], conf: 'baja' }),
  E('sudanes-nubio', 'Sudanés y nubio', { grupo: 'África subsahariana', regiones: 'africa-nilo', prefijos: 'sudanes', pieles: 'castana ebano-claro ebano', cabellos: NEG, peinados: 'rapado corto trenzas-pegadas', barba: 0.4, altura: [160, 173] }),
  // ----- Medio Oriente -----
  E('palestino', 'Palestino', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente', prefijos: 'palestin', pieles: 'oliva-clara oliva canela', cabellos: [['negro', 6], ['castano-oscuro', 3], ['castano', 1]], peinados: 'corto trenzas-dos recogido', barba: 0.6, altura: [157, 170] }),
  E('libanes-sirio', 'Libanés y sirio', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente', prefijos: 'liban', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto recogido trenzas-dos', barba: 0.55, altura: [158, 172] }),
  E('kurdo', 'Kurdo', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente', prefijos: 'kurd', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto trenzas-dos largo-liso', barba: 0.7, altura: [157, 170] }),
  E('yemenita', 'Yemenita', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente', prefijos: 'yemenit', pieles: 'oliva canela bronce', cabellos: [['negro', 7], ['castano-oscuro', 2]], peinados: 'corto trenzas-dos', barba: 0.7, altura: [152, 165] }),
  // ----- África -----
  E('etiope-habesha', 'Etíope y eritreo (habesha)', { grupo: 'África subsahariana', regiones: 'africa-cuerno', pieles: 'canela bronce cobre castana', cabellos: NEG, peinados: 'trenzas-pegadas trenzas-dos corto rapado', barba: 0.35, altura: [156, 168], corpulencia: [['delgada', 3], ['media', 3]] }),
  E('tuareg', 'Tuareg', { grupo: 'África subsahariana', regiones: 'africa-sahel', prefijos: 'tuareg', pieles: 'oliva canela bronce cobre', cabellos: NEG, peinados: 'corto trenzas-dos', barba: 0.2, altura: [160, 173], corpulencia: [['delgada', 3], ['media', 3]] }),
  E('hausa-fulani', 'Hausa y fulani', { grupo: 'África subsahariana', regiones: 'africa-sahel', prefijos: 'hausa fulani', pieles: 'canela bronce cobre castana ebano-claro', cabellos: NEG, peinados: 'rapado trenzas-pegadas trenzas-dos', barba: 0.4, altura: [158, 171] }),
  E('yoruba', 'Yoruba', { grupo: 'África subsahariana', regiones: 'africa-oeste', prefijos: 'yoruba', pieles: 'castana ebano-claro ebano ebano-profundo', cabellos: NEG, peinados: 'rapado trenzas-pegadas corto', barba: 0.35, altura: [158, 170] }),
  E('xhosa', 'Xhosa', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'xhosa', pieles: 'castana ebano-claro ebano', cabellos: NEG, peinados: 'rapado corto', barba: 0.3, altura: [158, 170], conf: 'baja' }),
  E('himba', 'Himba', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'himba', pieles: 'ebano-claro ebano', cabellos: NEG, peinados: 'trenzas-pegadas rapado', barba: 0.1, altura: [160, 174], corpulencia: [['delgada', 3], ['media', 3]], conf: 'baja' }),
  E('khoisan', 'Khoisan (san y khoikhoi)', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'khoisan', pieles: 'canela bronce cobre castana', cabellos: NEG, peinados: 'rizos rapado', barba: 0.05, altura: [143, 155], corpulencia: [['delgada', 3], ['media', 3]], conf: 'baja', nota: 'Su estatura media es de las menores del mundo; las cifras históricas son inciertas.' }),
  E('swahili', 'Suajili', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'swahili', pieles: 'castana ebano-claro ebano', cabellos: NEG, peinados: 'rapado corto trenzas-pegadas', barba: 0.4, altura: [157, 170] }),
  E('malgache', 'Malgache', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'malgache', pieles: 'canela bronce cobre castana', cabellos: NEG, peinados: 'corto trenzas-pegadas trenzas-dos', barba: 0.2, altura: [152, 164], conf: 'baja' }),
  // ----- Américas -----
  E('amazonico-shipibo', 'Pueblos amazónicos (shipibo y afines)', { grupo: 'Pueblos de las tierras bajas', regiones: 'amazonia', prefijos: 'amazonic', pieles: PIEL_OSC, cabellos: OSC, peinados: 'largo-liso flequillo trenzas-dos', barba: 0.03, altura: [148, 159], conf: 'baja' }),
  E('tupi-guarani', 'Guaraní y pueblos tupí', { grupo: 'Pueblos de las tierras bajas', regiones: 'amazonia', prefijos: 'guarani', pieles: PIEL_OSC, cabellos: OSC, peinados: 'largo-liso flequillo trenzas-dos', barba: 0.04, altura: [150, 161], conf: 'baja' }),
  E('yanomami', 'Yanomami', { grupo: 'Pueblos de las tierras bajas', regiones: 'amazonia', prefijos: 'yanomami', pieles: PIEL_OSC, cabellos: OSC, peinados: 'flequillo largo-liso', barba: 0.03, altura: [145, 155], conf: 'baja' }),
  E('taino', 'Taíno', { grupo: 'Caribe', regiones: 'caribe', pieles: PIEL_OSC, cabellos: OSC, peinados: 'flequillo largo-liso trenzas-dos', barba: 0.03, altura: [150, 162], conf: 'baja', nota: 'Pueblo desaparecido como sociedad en el siglo XVI; las fuentes son escasas y de segunda mano.' }),
  E('navajo', 'Diné (navajo)', { grupo: 'Pueblos de Norteamérica', regiones: 'norteamerica', prefijos: 'navajo', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'largo-liso recogido', barba: 0.05, altura: [157, 171] }),
  E('haudenosaunee', 'Haudenosaunee (iroqueses)', { grupo: 'Pueblos de Norteamérica', regiones: 'norteamerica', prefijos: 'haudenosaunee', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso rapado', barba: 0.05, altura: [159, 173], conf: 'baja' }),
  E('tlingit-haida', 'Tlingit y haida (costa noroeste)', { grupo: 'Pueblos de Norteamérica', regiones: 'norteamerica', prefijos: 'tlingit', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'largo-liso trenzas-dos', barba: 0.05, altura: [157, 170], conf: 'baja' }),
  E('seminola', 'Seminola', { grupo: 'Pueblos de Norteamérica', regiones: 'norteamerica', prefijos: 'seminola', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'largo-liso recogido', barba: 0.05, altura: [158, 172], conf: 'baja' }),
  E('cherokee', 'Cheroqui', { grupo: 'Pueblos de Norteamérica', regiones: 'norteamerica', prefijos: 'cherokee', pieles: 'clara-calida oliva-clara oliva canela', cabellos: [['negro', 6], ['castano-oscuro', 3], ['castano', 1]], peinados: 'largo-liso recogido trenzas-dos', barba: 0.1, altura: [158, 172] }),
  E('zapoteca', 'Zapoteco', { grupo: 'Pueblos de México', regiones: 'mexico-regional', prefijos: 'zapotec tehuan', pieles: PIEL_OSC, cabellos: OSC, peinados: 'trenzas-dos recogido corto', barba: 0.1, altura: [145, 158] }),
  E('purepecha', 'Purépecha', { grupo: 'Pueblos de México', regiones: 'mexico-regional', prefijos: 'purepecha', pieles: PIEL_OSC, cabellos: OSC, peinados: 'trenzas-dos corto', barba: 0.1, altura: [146, 159] }),
  E('raramuri', 'Rarámuri (tarahumara)', { grupo: 'Pueblos de México', regiones: 'mexico-regional', prefijos: 'raramuri', pieles: PIEL_OSC, cabellos: OSC, peinados: 'largo-liso trenzas-dos', barba: 0.05, altura: [148, 161], corpulencia: [['delgada', 3], ['media', 3]] }),
  E('wixarika', 'Wixárika (huichol)', { grupo: 'Pueblos de México', regiones: 'mexico-regional', prefijos: 'wixarika', pieles: PIEL_OSC, cabellos: OSC, peinados: 'largo-liso trenzas-dos', barba: 0.05, altura: [147, 159] }),
  E('mexicano-mestizo', 'Mestizo mexicano', { grupo: 'México', regiones: 'mexico-regional', prefijos: 'charro mexican', pieles: 'clara-calida oliva-clara oliva canela', cabellos: MESTIZO, peinados: 'corto trenzas-dos recogido', barba: 0.45, altura: [152, 165] }),
  E('moche-chimu', 'Moche y chimú', { grupo: 'Pueblos andinos', regiones: 'andes', prefijos: 'moche chimu', pieles: PIEL_OSC, cabellos: OSC, peinados: 'largo-liso flequillo trenzas-dos', barba: 0.03, altura: [150, 160], conf: 'baja' }),
  E('tehuelche', 'Tehuelche (aonikenk)', { grupo: 'Pueblos de la Patagonia y Tierra del Fuego', regiones: 'patagonia-fueguina', prefijos: 'tehuelche', pieles: PIEL_OSC, cabellos: OSC, peinados: 'largo-liso trenzas-dos', barba: 0.05, altura: [163, 176], corpulencia: [['media', 4], ['robusta', 2]], nota: 'Los viajeros del siglo XIX los describieron como muy altos; se usa un promedio más conservador.' }),
  // ----- Oceanía -----
  E('maori', 'Maorí', { grupo: 'Oceanía', regiones: 'oceania', prefijos: 'maori', pieles: PIEL_OSC, cabellos: OSC, peinados: 'largo-liso mono-alto trenza-una', barba: 0.3, altura: [160, 173], corpulencia: [['media', 3], ['robusta', 3]] }),
  E('aborigen-australiano', 'Aborigen australiano', { grupo: 'Oceanía', regiones: 'oceania', prefijos: 'aborigen', pieles: 'castana ebano-claro ebano', cabellos: [['negro', 6], ['castano-oscuro', 2], ['castano-claro', 1], ['rubio', 0.5]], peinados: 'rizos largo-ondulado', barba: 0.6, altura: [155, 169], conf: 'baja', nota: 'Cientos de pueblos distintos: el promedio es solo una referencia.' }),
  E('melanesio-papu', 'Melanesio y papú', { grupo: 'Oceanía', regiones: 'oceania', prefijos: 'papu', pieles: 'ebano-claro ebano ebano-profundo', cabellos: NEG, peinados: 'rizos rapado', barba: 0.4, altura: [146, 160], conf: 'baja' }),
  E('hawaiano', 'Hawaiano', { grupo: 'Polinesia', regiones: 'polinesia', prefijos: 'hawaian', pieles: PIEL_OSC, cabellos: OSC, peinados: 'largo-ondulado corto rizos', barba: 0.3, altura: [160, 172], corpulencia: [['media', 3], ['robusta', 3]] }),
  E('samoano-tongano', 'Samoano y tongano', { grupo: 'Polinesia', regiones: 'polinesia', prefijos: 'samoan tongan', pieles: PIEL_OSC, cabellos: OSC, peinados: 'largo-ondulado corto mono-alto', barba: 0.3, altura: [160, 174], corpulencia: [['media', 3], ['robusta', 3.5]] }),
  E('fiyiano', 'Fiyiano', { grupo: 'Melanesia y Polinesia', regiones: 'polinesia', prefijos: 'fiyian', pieles: 'castana ebano-claro ebano', cabellos: NEG, peinados: 'rizos trenzas-pegadas corto', barba: 0.3, altura: [157, 170], conf: 'baja' }),
  // ----- Américas modernas -----
  E('mestizo-latinoamericano', 'Mestizo latinoamericano', { grupo: 'Latinoamérica', regiones: 'latinoamerica-moderna hispanoamerica-colonial', pieles: 'clara-calida oliva-clara oliva canela bronce', cabellos: MESTIZO, peinados: 'corto trenzas-dos recogido', barba: 0.45, altura: [152, 165], nota: 'Mezcla de ascendencia europea, indígena y africana; la piel se estima en un rango amplio.' }),
  E('afrolatino', 'Afrolatinoamericano y afrocaribeño', { grupo: 'Pueblos africanos y afrodescendientes', regiones: 'latinoamerica-moderna', pieles: 'canela bronce castana ebano-claro ebano', cabellos: [['negro', 9], ['castano-oscuro', 1]], peinados: 'afro rizos trenzas-pegadas rapado corto', barba: 0.4, altura: [156, 169] }),
  E('angloamericano', 'Estadounidense y canadiense de ascendencia europea', { grupo: 'Norteamérica', regiones: 'norteamerica-moderna', prefijos: 'eeuu-vaquer eeuu-pioner eeuu-colon eeuu-granj eeuu-obrero eeuu-flapper eeuu-ejecutivo eeuu-ama eeuu-soldado eeuu-enfermera eeuu-estudiante eeuu-adolescente eeuu-jeans', pieles: 'porcelana clara clara-calida', cabellos: CLARO, peinados: 'corto bob recogido', barba: 0.35, altura: [162, 176] }),
  E('afroamericano', 'Afroamericano', { grupo: 'Pueblos africanos y afrodescendientes', regiones: 'norteamerica-moderna', prefijos: 'eeuu-domingo eeuu-jeans eeuu-obrero eeuu-granj eeuu-soldado eeuu-ama eeuu-flapper eeuu-ejecutivo eeuu-enfermera eeuu-estudiante eeuu-adolescente', pieles: 'canela bronce castana ebano-claro ebano', cabellos: [['negro', 9], ['castano-oscuro', 1]], peinados: 'rapado corto afro ondas', barba: 0.4, altura: [160, 175] }),
];

Object.assign(__e, { ETNIAS_AMPLIADAS });
return __e;
})();

__m['datos/etnias'] = (() => {
const __e = {};
// Pueblos y culturas para estimar, a partir de la etnia, la región del atuendo, la piel, el cabello, los peinados,
// la barba y la estatura. Son promedios aproximados de poblaciones, no reglas sobre una persona: dentro de cada pueblo
// hay mucha variación y por eso el generador siempre elige al azar dentro de un rango. «Etnia» aquí es cultura de
// origen y no una categoría biológica.
//
// pieles/peinados: listas de ids (colores.js, cabeza.js). cabellos: [id, peso]. altura: [mujer, hombre] en cm de adultos.
// regiones: de dónde salen los atuendos (atuendos.js). prefijos: para separar pueblos que comparten región por el id del atuendo.
// barba: probabilidad de que un adulto lleve barba o bigote. corpulencia: [nombre, peso].

const { E, OSC, CLARO, MED, MESTIZO } = __m['datos/ayuda'];
const { ETNIAS_AMPLIADAS } = __m['datos/etnias-ampliadas'];

const ETNIAS = [
  // ----- Chile y el cono sur -----
  E('mapuche', 'Mapuche', { grupo: 'Pueblos originarios de Chile y Argentina', regiones: 'mapuche', pieles: 'canela bronce cobre oliva', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.08, altura: [153, 165], corpulencia: [['delgada', 1], ['media', 3], ['robusta', 2]], nota: 'Incluye pehuenche, huilliche y lafkenche cuando no hay un traje propio en el catálogo.' }),
  E('huilliche', 'Huilliche', { grupo: 'Pueblos originarios de Chile y Argentina', regiones: 'mapuche chile-rural', pieles: 'canela bronce cobre oliva', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.08, altura: [153, 165] }),
  E('aymara', 'Aymara', { grupo: 'Pueblos andinos', regiones: 'andes', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.06, altura: [150, 162], corpulencia: [['media', 3], ['robusta', 3], ['delgada', 0.7]], nota: 'Pecho ancho por la altura; la estimación de estatura es de poblaciones del altiplano.' }),
  E('quechua', 'Quechua', { grupo: 'Pueblos andinos', regiones: 'andes', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.06, altura: [151, 163], corpulencia: [['media', 3], ['robusta', 2.5], ['delgada', 0.8]] }),
  E('inca', 'Inca (histórico)', { grupo: 'Pueblos andinos', regiones: 'andes', prefijos: 'inca', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'flequillo largo-liso trenzas-dos corto', barba: 0.04, altura: [150, 162] }),
  E('atacameno', 'Atacameño (lickanantay)', { grupo: 'Pueblos andinos', regiones: 'andes', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso corto', barba: 0.06, altura: [152, 164] }),
  E('selknam', 'Selk\'nam (ona)', { grupo: 'Pueblos de la Patagonia y Tierra del Fuego', regiones: 'patagonia-fueguina', prefijos: 'selknam', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'largo-liso corto', barba: 0.05, altura: [165, 178], corpulencia: [['media', 4], ['robusta', 2]], nota: 'Se cuentan entre los pueblos más altos de América en los registros del siglo XIX.' }),
  E('kawesqar', 'Kawésqar y yagán', { grupo: 'Pueblos de la Patagonia y Tierra del Fuego', regiones: 'patagonia-fueguina', prefijos: 'kawesqar', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'largo-liso corto', barba: 0.05, altura: [148, 160], nota: 'Pueblos canoeros; el catálogo los representa con trajes de piel genéricos de la región.' }),
  E('rapanui', 'Rapanui', { grupo: 'Polinesia', regiones: 'rapa-nui', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'corto largo-ondulado trenza-una mono-alto', barba: 0.3, altura: [160, 172], corpulencia: [['media', 3], ['robusta', 3]] }),
  E('polinesio', 'Polinesio', { grupo: 'Polinesia', regiones: 'polinesia rapa-nui', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'corto largo-ondulado trenza-una mono-alto', barba: 0.3, altura: [160, 173], corpulencia: [['media', 3], ['robusta', 3]] }),
  E('criollo-chileno', 'Criollo y mestizo chileno', { grupo: 'Chile', regiones: 'chile-rural hispanoamerica-colonial', pieles: 'clara-calida oliva-clara oliva canela', cabellos: MESTIZO, peinados: 'corto trenzas-dos recogido bob', barba: 0.4, altura: [155, 167], nota: 'Mezcla de ascendencia europea e indígena; la piel se estima en un rango amplio.' }),
  E('espanol', 'Español y vasco', { grupo: 'Europa', regiones: 'hispanoamerica-colonial europa-moderna europa-xix europa-medieval', pieles: 'porcelana clara clara-calida oliva-clara', cabellos: MED, peinados: 'corto recogido bob', barba: 0.45, altura: [157, 168] }),
  E('rioplatense', 'Criollo rioplatense', { grupo: 'Río de la Plata', regiones: 'rio-de-la-plata', pieles: 'clara clara-calida oliva-clara oliva', cabellos: MESTIZO, peinados: 'corto', barba: 0.5, altura: [158, 170] }),
  E('afrodescendiente', 'Afrodescendiente de las Américas', { grupo: 'Pueblos africanos y afrodescendientes', regiones: 'hispanoamerica-colonial africa-oeste', pieles: 'castana ebano-claro ebano', cabellos: [['negro', 9], ['castano-oscuro', 1]], peinados: 'afro rizos trenzas-dos rapado corto', barba: 0.4, altura: [158, 170], nota: 'El catálogo no tiene trajes específicos de comunidades afrodescendientes de las Américas: usa los coloniales y los de África occidental.' }),
  // ----- Mesoamérica y Norteamérica -----
  E('maya', 'Maya', { grupo: 'Pueblos de Mesoamérica', regiones: 'mesoamerica', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo', barba: 0.05, altura: [145, 157] }),
  E('nahua', 'Nahua (azteca)', { grupo: 'Pueblos de Mesoamérica', regiones: 'mesoamerica', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'coletas corona-trenzas flequillo corto', barba: 0.05, altura: [147, 160] }),
  E('pueblos-llanuras', 'Pueblos de las llanuras norteamericanas', { grupo: 'Pueblos de Norteamérica', regiones: 'norteamerica', pieles: 'canela bronce cobre oliva', cabellos: OSC, peinados: 'trenzas-dos largo-liso', barba: 0.05, altura: [160, 174] }),
  E('inuit', 'Inuit', { grupo: 'Pueblos del Ártico', regiones: 'artico', pieles: 'clara-calida oliva-clara oliva', cabellos: OSC, peinados: 'trenzas-dos largo-liso corto', barba: 0.08, altura: [150, 160], corpulencia: [['media', 3], ['robusta', 3]] }),
  // ----- Europa -----
  E('europeo-occidental', 'Europeo occidental', { grupo: 'Europa', regiones: 'europa-medieval europa-moderna europa-xix', pieles: 'porcelana clara clara-calida', cabellos: CLARO, peinados: 'corto bob trenza-una recogido', barba: 0.4, altura: [160, 171] }),
  E('europeo-mediterraneo', 'Europeo mediterráneo (italiano, griego)', { grupo: 'Europa', regiones: 'roma grecia bizancio europa-moderna europa-xix europa-medieval', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto recogido romano', barba: 0.5, altura: [157, 169] }),
  E('europeo-del-norte', 'Europeo del norte (nórdico, germano)', { grupo: 'Europa', regiones: 'germano europa-medieval europa-moderna', pieles: 'porcelana clara', cabellos: [['rubio', 4], ['rubio-oscuro', 3], ['castano-claro', 2], ['pelirrojo', 1.5], ['castano', 1]], peinados: 'trenza-una largo-liso corto recogido', barba: 0.5, altura: [166, 178] }),
  E('celta', 'Celta (Irlanda, Escocia, Gales)', { grupo: 'Europa', regiones: 'germano', pieles: 'porcelana clara', cabellos: [['pelirrojo', 2.5], ['castano', 3], ['castano-claro', 2], ['castano-oscuro', 2], ['negro', 1], ['rubio-oscuro', 1]], peinados: 'largo-liso corto largo-ondulado', barba: 0.45, altura: [160, 172] }),
  E('eslavo', 'Eslavo', { grupo: 'Europa', regiones: 'eslavo', pieles: 'porcelana clara clara', cabellos: [['rubio-oscuro', 3], ['castano-claro', 3], ['rubio', 2], ['castano', 2], ['rubio-ceniza', 1]], peinados: 'trenza-una coletas bob corto', barba: 0.55, altura: [162, 174] }),
  E('judio-askenazi', 'Judío askenazi', { grupo: 'Comunidades judías', regiones: 'judio', pieles: 'porcelana clara clara-calida oliva-clara', cabellos: MED, peinados: 'corto', barba: 0.7, altura: [157, 168] }),
  E('hebreo-antiguo', 'Hebreo antiguo', { grupo: 'Comunidades judías', regiones: 'judio', pieles: 'oliva-clara oliva canela', cabellos: [['negro', 6], ['castano-oscuro', 3]], peinados: 'corto largo-ondulado trenzas-dos', barba: 0.75, altura: [152, 164] }),
  E('egipcio-antiguo', 'Egipcio antiguo', { grupo: 'Mundo antiguo', regiones: 'egipto', pieles: 'oliva canela bronce', cabellos: [['negro', 8], ['castano-oscuro', 1]], peinados: 'peluca-egipcia bob rapado', barba: 0.05, altura: [152, 165] }),
  E('mesopotamico', 'Sumerio, acadio y asirio', { grupo: 'Mundo antiguo', regiones: 'mesopotamia', pieles: 'oliva-clara oliva canela', cabellos: [['negro', 7], ['castano-oscuro', 2]], peinados: 'rizos recogido corto', barba: 0.85, altura: [152, 165] }),
  E('griego-antiguo', 'Griego antiguo', { grupo: 'Mundo antiguo', regiones: 'grecia', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto rizos recogido corona-trenzas', barba: 0.6, altura: [153, 167] }),
  E('romano-antiguo', 'Romano antiguo', { grupo: 'Mundo antiguo', regiones: 'roma', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'romano corto recogido', barba: 0.15, altura: [154, 168], nota: 'Los hombres romanos solían ir afeitados.' }),
  E('bizantino', 'Bizantino', { grupo: 'Mundo antiguo', regiones: 'bizancio', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto recogido', barba: 0.55, altura: [154, 167] }),
  // ----- Medio Oriente y África del norte -----
  E('arabe', 'Árabe', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente africa-norte', prefijos: 'beduino mujer-arabe maghreb palestin liban yemenit arabe-urbano', pieles: 'oliva-clara oliva canela bronce', cabellos: [['negro', 7], ['castano-oscuro', 2]], peinados: 'corto recogido', barba: 0.7, altura: [157, 169] }),
  E('persa', 'Persa', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente', prefijos: 'persa', pieles: 'oliva-clara oliva', cabellos: [['negro', 6], ['castano-oscuro', 3]], peinados: 'corto largo-liso', barba: 0.65, altura: [157, 170] }),
  E('turco-otomano', 'Turco y otomano', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente', prefijos: 'otomano otomana', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto', barba: 0.7, altura: [158, 171] }),
  E('bereber', 'Bereber y magrebí', { grupo: 'Medio Oriente y África del norte', regiones: 'africa-norte', pieles: 'oliva-clara oliva canela bronce', cabellos: [['negro', 6], ['castano-oscuro', 3]], peinados: 'corto trenzas-dos recogido', barba: 0.65, altura: [157, 169] }),
  // ----- Asia -----
  E('indio-subcontinente', 'Indio del subcontinente', { grupo: 'Asia', regiones: 'asia-sur asia-sur-regional', pieles: 'oliva canela bronce cobre castana', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'trenza-una recogido corto', barba: 0.55, altura: [151, 164] }),
  E('chino-han', 'Chino han', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'china-', pieles: 'clara clara-calida oliva-clara', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'mono-alto recogido trenza-una bob', barba: 0.2, altura: [155, 167] }),
  E('japones', 'Japonés', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'japon-', pieles: 'porcelana clara clara-calida', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'mono-chonmage recogido mono-alto', barba: 0.2, altura: [153, 165] }),
  E('coreano', 'Coreano', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'corea-', pieles: 'porcelana clara clara-calida', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'recogido trenza-una mono-chonmage', barba: 0.2, altura: [155, 167] }),
  E('malayo-indonesio', 'Malayo e indonesio', { grupo: 'Asia', regiones: 'sudeste-asiatico', prefijos: 'sudeste', pieles: 'oliva canela bronce', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'recogido corto mono-alto', barba: 0.25, altura: [148, 160] }),
  // ----- África subsahariana -----
  E('africano-occidental', 'Africano occidental', { grupo: 'África subsahariana', regiones: 'africa-oeste', pieles: 'castana ebano-claro ebano ebano-profundo', cabellos: [['negro', 10]], peinados: 'rapado corto afro trenzas-dos rizos', barba: 0.4, altura: [160, 172] }),
  E('zulu', 'Zulú y bantú austral', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'zulu-', pieles: 'castana ebano-claro ebano', cabellos: [['negro', 10]], peinados: 'rapado corto', barba: 0.3, altura: [157, 169] }),
  E('maasai', 'Maasái', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'maasai', pieles: 'ebano-claro ebano ebano-profundo', cabellos: [['negro', 10]], peinados: 'rapado rastas', barba: 0.05, altura: [165, 178], corpulencia: [['delgada', 3], ['media', 3]], nota: 'Estatura alta y complexión delgada son típicas de los registros de pastores de África oriental.' }),
  ...ETNIAS_AMPLIADAS,
];

const ETNIAS_POR_ID = Object.fromEntries(ETNIAS.map((e) => [e.id, e]));

Object.assign(__e, { ETNIAS, ETNIAS_POR_ID });
return __e;
})();

__m['datos/colores-prendas'] = (() => {
const __e = {};
// Catálogo de colores por prenda, con la probabilidad de cada color.
//
// Hay dos orígenes:
//  1. PERFIL: la prenda tiene sus propios colores y pesos (los jeans solo en azules y grises; la camisa blanca casi
//     siempre blanca; el calzado en cueros y negros). Los pesos son relativos: se normalizan a 100 %.
//  2. ATUENDO: la prenda no tiene perfil propio y toma el color de la paleta del atuendo donde aparece (el chamal
//     mapuche, el kimono…). Su probabilidad se calcula con lo que el generador hace de verdad: el reparto
//     entre los atuendos que la llevan y, dentro de cada uno, entre los tintes de su letra de la paleta.
// En los dos casos se descartan los tintes que no existían en el año pedido (el denim es posterior a 1873).
//
// Los pesos son una estimación razonada, no una estadística medida: sirven para que la ropa se vea verosímil y
// para que se pueda ver y cambiar con qué frecuencia sale cada color.

const { TINTES_POR_ID } = __m['datos/colores'];
const { ATUENDOS } = __m['datos/atuendos'];
const { PRENDAS_POR_ID } = __m['datos/prendas'];

/** Perfiles reutilizables: nombre → [tinte, peso]. */
const PERFILES = {
  BLANCOS: [['blanco-lino', 30], ['crudo', 22], ['marfil', 14], ['algodon-crudo', 12], ['blanco-optico', 10], ['crema', 8], ['lana-clara', 4]],
  BLANCOS_RUSTICOS: [['crudo', 35], ['algodon-crudo', 20], ['lana-clara', 15], ['blanco-lino', 15], ['beige', 8], ['marfil', 7]],
  LINOS_ANTIGUOS: [['blanco-lino', 55], ['crudo', 25], ['marfil', 12], ['lana-clara', 8]],
  NOVIA: [['blanco-optico', 40], ['marfil', 30], ['crema', 20], ['blanco-lino', 10]],
  LUTO: [['negro-carbon', 45], ['lana-negra', 25], ['negro-agalla', 12], ['gris-oscuro', 10], ['borravino', 4], ['azul-marino', 4]],
  DENIM: [['azul-denim', 40], ['denim-oscuro', 25], ['denim-claro', 18], ['denim-gris', 9], ['denim-negro', 8]],
  OVEROL: [['azul-denim', 35], ['denim-oscuro', 20], ['indigo', 15], ['azul-marino', 10], ['caqui-militar', 8], ['gris-oscuro', 6], ['kaki', 6]],
  PANTALON_70: [['azul-denim', 30], ['denim-claro', 20], ['denim-oscuro', 12], ['camel', 10], ['gris-oscuro', 10], ['negro-carbon', 8], ['burdeos', 5], ['mostaza', 5]],
  AZULES_TRABAJO: [['indigo', 30], ['azul-marino', 15], ['azul-plomo', 15], ['indigo-profundo', 12], ['gris-pizarra', 10], ['denim-gris', 6], ['caqui-militar', 6], ['kaki', 6]],
  TRABAJO: [['caqui-militar', 22], ['azul-marino', 16], ['kaki', 16], ['gris-oscuro', 14], ['indigo', 12], ['nogal', 8], ['verde-oliva-oscuro', 7], ['lana-parda', 5]],
  LANAS_OSCURAS: [['lana-negra', 20], ['gris-oscuro', 18], ['azul-marino', 16], ['gris-pizarra', 12], ['nogal', 8], ['chocolate', 6], ['gris-ceniza', 6], ['borravino', 4], ['verde-oliva-oscuro', 4], ['camel', 3], ['burdeos', 3]],
  ABRIGOS: [['camel', 20], ['gris-claro', 16], ['beige', 14], ['gris-oscuro', 12], ['azul-marino', 10], ['nogal', 8], ['crema', 6], ['lana-negra', 7], ['umbra', 7]],
  TIERRAS: [['lana-parda', 22], ['nogal', 14], ['umbra', 14], ['tabaco', 10], ['castana', 8], ['kaki', 8], ['gris-ceniza', 8], ['verde-musgo', 8], ['siena', 4], ['chocolate', 4]],
  PIELES: [['camel', 20], ['chocolate', 20], ['lana-negra', 18], ['gris-oscuro', 15], ['beige', 12], ['crema', 8], ['nogal', 7]],
  IMPERMEABLE: [['beige', 28], ['caqui-militar', 22], ['gris-oscuro', 16], ['azul-marino', 14], ['lana-negra', 12], ['verde-oliva-oscuro', 8]],
  UNIFORME_MILITAR: [['verde-militar', 30], ['caqui-militar', 28], ['azul-militar', 18], ['gris-oscuro', 10], ['lana-negra', 8], ['verde-oliva-oscuro', 6]],
  UNIFORME_AZUL: [['azul-marino', 40], ['azul-militar', 25], ['gris-oscuro', 15], ['lana-negra', 10], ['gris-pizarra', 10]],
  FALDA_COLEGIAL: [['azul-marino', 35], ['gris-oscuro', 22], ['burdeos', 12], ['verde-bosque', 10], ['gris-claro', 8], ['azul-militar', 8], ['verde-oliva-oscuro', 5]],
  FALDAS_CIUDAD: [['lana-negra', 16], ['azul-marino', 14], ['gris-oscuro', 14], ['burdeos', 10], ['nogal', 8], ['verde-bosque', 8], ['camel', 8], ['gris-claro', 8], ['terracota', 6], ['azul-real', 4], ['rosa-viejo', 4]],
  PANTALON_VESTIR: [['lana-negra', 18], ['gris-oscuro', 18], ['azul-marino', 14], ['gris-claro', 12], ['nogal', 10], ['chocolate', 8], ['beige', 8], ['caqui-militar', 6], ['camel', 6]],
  CAMISA_CUADROS: [['rojo-granza', 18], ['azul-marino', 14], ['verde-bosque', 12], ['indigo', 10], ['burdeos', 10], ['caqui-militar', 8], ['mostaza', 8], ['gris-pizarra', 8], ['terracota', 6], ['azul-real', 6]],
  CAMISA_TRABAJO: [['azul-celeste', 20], ['crudo', 15], ['caqui-militar', 15], ['gris-claro', 12], ['azul-marino', 10], ['kaki', 10], ['blanco-optico', 8], ['verde-militar', 5], ['beige', 5]],
  POLERAS: [['blanco-optico', 20], ['azul-marino', 12], ['gris-claro', 12], ['rojo-vivo', 10], ['negro-carbon', 8], ['verde-bosque', 8], ['azul-real', 8], ['mostaza', 7], ['amarillo-limon', 5], ['burdeos', 5], ['crema', 5]],
  SUETERES: [['crema', 16], ['gris-oscuro', 14], ['azul-marino', 12], ['burdeos', 10], ['verde-bosque', 10], ['camel', 10], ['chocolate', 8], ['lana-parda', 8], ['gris-claro', 8], ['rojo-granza', 4]],
  SEDA: [['crema', 20], ['marfil', 15], ['blanco-optico', 12], ['rosa-viejo', 12], ['azul-celeste', 10], ['lila', 8], ['verde-agua', 8], ['burdeos', 5], ['azafran', 4], ['rosa-fucsia', 3], ['amarillo-limon', 3]],
  VESTIDOS_DIA: [['azul-marino', 12], ['verde-bosque', 9], ['burdeos', 8], ['rosa-viejo', 8], ['azul-celeste', 8], ['lana-negra', 8], ['mostaza', 6], ['crema', 6], ['terracota', 6], ['azul-real', 6], ['verde-agua', 6], ['rojo-vivo', 5], ['gris-claro', 5], ['lila', 4], ['azafran', 3]],
  VESTIDOS_GALA: [['negro-carbon', 16], ['burdeos', 14], ['azul-marino', 10], ['verde-esmeralda', 10], ['rojo-vivo', 10], ['azul-real', 9], ['violeta', 7], ['marfil', 6], ['rosa-fucsia', 5], ['plata', 5], ['rosa-viejo', 4], ['oro', 4]],
  GUAYABERA: [['blanco-lino', 35], ['crema', 20], ['azul-celeste', 15], ['beige', 12], ['marfil', 8], ['verde-agua', 5], ['rosa-viejo', 5]],
  LIQUILIQUI: [['blanco-lino', 30], ['crema', 25], ['beige', 15], ['marfil', 10], ['caqui-militar', 8], ['azul-celeste', 7], ['verde-agua', 5]],
  GUARDAPOLVO: [['blanco-optico', 45], ['blanco-lino', 20], ['azul-celeste', 12], ['crema', 10], ['gris-claro', 8], ['caqui-militar', 5]],
  CAMISA_MANTA: [['crudo', 50], ['algodon-crudo', 25], ['blanco-lino', 15], ['beige', 10]],
  CAMISON: [['blanco-lino', 35], ['crema', 20], ['rosa-viejo', 12], ['azul-celeste', 12], ['marfil', 11], ['lila', 5], ['verde-agua', 5]],
  ESTAMPADOS_VIVOS: [['azul-real', 14], ['turquesa', 12], ['rojo-vivo', 12], ['verde-lima', 10], ['naranja-vivo', 10], ['amarillo-limon', 10], ['rosa-fucsia', 10], ['azul-celeste', 8], ['blanco-optico', 8], ['lila', 6]],
  CHAMANTO: [['rojo-granza', 22], ['azul-andino', 18], ['verde-hoja', 14], ['rojo-cochinilla', 14], ['lana-negra', 12], ['azafran', 10], ['crudo', 6], ['violeta', 4]],
  CORBATA: [['azul-marino', 20], ['burdeos', 18], ['lana-negra', 14], ['verde-bosque', 10], ['gris-oscuro', 8], ['borravino', 8], ['rojo-vivo', 8], ['azul-real', 6], ['mostaza', 5], ['camel', 3]],
  CALZADO_CUERO: [['cuero-oscuro', 34], ['negro-humo', 28], ['cuero', 20], ['nogal', 10], ['chocolate', 5], ['cuero-claro', 3]],
  CUERO_CHAQUETA: [['cuero-oscuro', 38], ['negro-humo', 28], ['cuero', 22], ['tabaco', 8], ['nogal', 4]],
  CALZADO_RUSTICO: [['cuero', 32], ['cuero-oscuro', 25], ['cuero-claro', 18], ['tabaco', 12], ['nogal', 8], ['umbra', 5]],
  CALZADO_TELA: [['crudo', 26], ['negro-carbon', 20], ['azul-marino', 15], ['indigo', 12], ['gris-claro', 10], ['blanco-optico', 10], ['rojo-vivo', 7]],
  CALZADO_GOMA: [['negro-carbon', 60], ['gris-oscuro', 15], ['verde-militar', 15], ['azul-marino', 10]],
  CINTURONES: [['cuero-oscuro', 40], ['negro-humo', 28], ['cuero', 22], ['nogal', 10]],
  METALES: [['oro', 32], ['plata', 30], ['bronce', 14], ['cobre', 12], ['hierro', 6], ['turquesa', 6]],
  PERLAS: [['marfil', 45], ['blanco-optico', 20], ['crema', 20], ['rosa-viejo', 10], ['gris-claro', 5]],
  CUENTAS: [['turquesa', 15], ['rojo-cartamo', 14], ['azul-ultramar', 10], ['azafran', 10], ['blanco-lino', 12], ['verde-malaquita', 8], ['negro-humo', 8], ['azul-celeste', 8], ['bermellon', 8], ['oro', 7]],
  COSTURA_DENIM: [['mostaza', 35], ['ocre-amarillo', 30], ['crudo', 20], ['cobre', 15]],
  BOTONES: [['marfil', 30], ['crudo', 18], ['cuero-oscuro', 14], ['negro-humo', 14], ['bronce', 10], ['plata', 8], ['cobre', 6]],
  BOTONES_METAL: [['cobre', 45], ['bronce', 30], ['hierro', 15], ['plata', 10]],
  CAMISA_VESTIR: [['blanco-optico', 30], ['azul-celeste', 20], ['crema', 12], ['rosa-viejo', 8], ['gris-claro', 8], ['lila', 5], ['verde-agua', 5], ['mostaza', 4], ['azul-marino', 4], ['burdeos', 4]],
  SAFARI: [['beige', 30], ['caqui-militar', 25], ['crema', 15], ['kaki', 12], ['verde-oliva-oscuro', 10], ['blanco-lino', 8]],
  SUDADERA: [['gris-claro', 24], ['azul-marino', 16], ['gris-oscuro', 14], ['burdeos', 10], ['verde-bosque', 10], ['negro-carbon', 10], ['rojo-vivo', 6], ['mostaza', 5], ['azul-real', 5]],
  PARKA: [['verde-militar', 24], ['azul-marino', 20], ['caqui-militar', 15], ['negro-carbon', 14], ['burdeos', 10], ['gris-oscuro', 10], ['mostaza', 7]],
  BOMBER: [['negro-carbon', 28], ['verde-militar', 20], ['azul-marino', 20], ['burdeos', 12], ['gris-oscuro', 10], ['rojo-vivo', 5], ['mostaza', 5]],
  ABRIGOS_LUJO: [['camel', 22], ['lana-negra', 18], ['burdeos', 14], ['chocolate', 14], ['gris-oscuro', 12], ['verde-oliva-oscuro', 8], ['crema', 6], ['azul-marino', 6]],
  SASTRE: [['azul-marino', 22], ['lana-negra', 20], ['gris-oscuro', 15], ['crema', 10], ['burdeos', 10], ['camel', 10], ['verde-bosque', 7], ['rosa-viejo', 6]],
  BATA: [['crema', 20], ['rosa-viejo', 16], ['azul-celeste', 14], ['burdeos', 12], ['verde-agua', 12], ['lila', 10], ['gris-claro', 8], ['azul-marino', 8]],
  BATIN: [['burdeos', 24], ['azul-marino', 20], ['verde-bosque', 16], ['violeta', 12], ['lana-negra', 14], ['mostaza', 7], ['azul-real', 7]],
  CORSE: [['negro-carbon', 28], ['burdeos', 22], ['crema', 14], ['rosa-viejo', 12], ['azul-marino', 10], ['verde-bosque', 8], ['marfil', 6]],
  CARGO: [['caqui-militar', 28], ['verde-militar', 22], ['kaki', 18], ['beige', 14], ['gris-oscuro', 10], ['azul-marino', 8]],
  CAPRI: [['azul-denim', 20], ['crema', 18], ['rosa-viejo', 12], ['beige', 12], ['blanco-optico', 12], ['azul-marino', 10], ['negro-carbon', 8], ['rojo-vivo', 8]],
  PESCADOR: [['crudo', 30], ['beige', 25], ['azul-denim', 15], ['caqui-militar', 12], ['indigo', 10], ['blanco-optico', 8]],
  LEGGINGS: [['negro-carbon', 55], ['gris-oscuro', 15], ['azul-marino', 12], ['burdeos', 8], ['verde-bosque', 5], ['lana-negra', 5]],
  SAROUEL: [['crema', 20], ['terracota', 15], ['azul-marino', 12], ['verde-oliva-oscuro', 12], ['burdeos', 10], ['mostaza', 10], ['gris-claro', 10], ['rosa-viejo', 6], ['camel', 5]],
  PALAZZO: [['negro-carbon', 24], ['crema', 16], ['azul-marino', 14], ['burdeos', 12], ['verde-esmeralda', 10], ['blanco-optico', 10], ['rosa-viejo', 8], ['camel', 6]],
  JODHPUR: [['beige', 40], ['crema', 20], ['camel', 15], ['kaki', 15], ['blanco-optico', 10]],
  MONO: [['azul-marino', 25], ['caqui-militar', 20], ['verde-militar', 15], ['gris-oscuro', 15], ['indigo', 10], ['kaki', 10], ['blanco-optico', 5]],
  ACCESORIO_TELA: [['crema', 20], ['burdeos', 12], ['azul-marino', 12], ['rosa-viejo', 10], ['verde-bosque', 10], ['lana-negra', 10], ['mostaza', 8], ['camel', 8], ['gris-claro', 5], ['rojo-vivo', 5]],
  PIEL_CLARA: [['crema', 25], ['camel', 20], ['gris-claro', 18], ['chocolate', 15], ['lana-negra', 12], ['blanco-optico', 10]],
  TIRANTES: [['negro-carbon', 24], ['cuero-oscuro', 20], ['burdeos', 16], ['azul-marino', 14], ['verde-bosque', 10], ['mostaza', 8], ['rojo-vivo', 8]],
  TACONES: [['negro-carbon', 28], ['cuero-oscuro', 18], ['burdeos', 14], ['crema', 10], ['rojo-vivo', 8], ['azul-marino', 8], ['nogal', 8], ['marfil', 6]],
  CHANCLETAS: [['azul-real', 15], ['negro-carbon', 15], ['rojo-vivo', 12], ['crema', 12], ['verde-lima', 10], ['cuero', 12], ['blanco-optico', 10], ['naranja-vivo', 7], ['rosa-fucsia', 7]],
};

// Asignación: [rol, perfil, ids de prenda separados por espacios]. La primera coincidencia de (prenda, rol) manda.
const GRUPOS = [
  // Blancos y lienzos
  ['principal', 'BLANCOS', 'camisa-blanca sobrepelliz toga toga-praetexta delantal-blanco blusa-regional stola'],
  ['principal', 'LINOS_ANTIGUOS', 'kalasiris kalasiris-plisada shenti shenti-plisado peplos quiton-jonico quiton-corto tunica-bizantina dalmatica tunica-etrusca tunica-ibera tunica-fenicia'],
  ['principal', 'BLANCOS_RUSTICOS', 'camisa-medieval blusa-campesina camisa-lino-larga vestido-lino-largo tunica-corta-lana camisa-rotos camisa-globo cota-campesina rubakha rubakha-larga'],
  ['principal', 'NOVIA', 'vestido-novia'],
  ['principal', 'LUTO', 'vestido-luto sotana'],
  ['principal', 'CAMISA_MANTA', 'camisa-manta'],
  ['principal', 'GUARDAPOLVO', 'guardapolvo'],
  ['principal', 'GUAYABERA', 'guayabera'],
  ['principal', 'LIQUILIQUI', 'liquiliqui'],
  ['principal', 'CAMISON', 'camison'],
  // Azules, grises y trabajo
  ['principal', 'DENIM', 'pantalon-vaquero chaqueta-denim camisa-denim'],
  ['principal', 'OVEROL', 'pantalon-overol overol-peto'],
  ['principal', 'PANTALON_70', 'pantalon-campana'],
  ['principal', 'AZULES_TRABAJO', 'bombachas pantalon-chino'],
  ['principal', 'TRABAJO', 'pantalon-trabajo'],
  ['principal', 'CAMISA_TRABAJO', 'camisa-trabajo camisa-manga-corta'],
  ['principal', 'CAMISA_CUADROS', 'camisa-cuadros'],
  ['principal', 'POLERAS', 'polera polera-rayas'],
  ['principal', 'SUETERES', 'sueter-lana sueter-cuello-alto sueter-nordico cardigan chaleco-lana'],
  ['principal', 'SEDA', 'blusa-seda blusa-cuello-bebe'],
  ['principal', 'ESTAMPADOS_VIVOS', 'camisa-aloha muumuu pareo-estampado'],
  // Ciudad
  ['principal', 'LANAS_OSCURAS', 'saco-terno saco-cruzado chaleco-terno traje-sastre levita frac chaleco-corto chaleco-largo casaca jubon-renacentista chaqueta-corta'],
  ['principal', 'PANTALON_VESTIR', 'pantalon-vestir pantalon-recto pantalon-ajustado pantalon-corte-alto pantalon-golf short calzones-rodilla calzones-medievales'],
  ['principal', 'FALDAS_CIUDAD', 'falda-lapiz falda-campana falda-plisada falda-larga'],
  ['principal', 'FALDA_COLEGIAL', 'falda-colegial'],
  ['principal', 'VESTIDOS_DIA', 'vestido-casa vestido-30 vestido-40 vestido-50 vestido-flapper vestido-sencillo vestido-sencillo-corto'],
  ['principal', 'VESTIDOS_GALA', 'vestido-fiesta vestido-fiesta-manga'],
  ['principal', 'ABRIGOS', 'abrigo-corto abrigo-largo gabardina'],
  ['principal', 'PIELES', 'abrigo-pieles chaleco-pieles'],
  ['principal', 'IMPERMEABLE', 'impermeable'],
  ['principal', 'UNIFORME_MILITAR', 'chaqueta-militar pantalon-militar'],
    ['principal', 'CUERO_CHAQUETA', 'chaqueta-cuero delantal-obrero lederhosen'],
  ['principal', 'CHAMANTO', 'chamanto chamanto-fiesta'],
  ['principal', 'TIERRAS', 'calzon-manta bragas-holgadas ruana poncho-liso manta-huasa'],
  ['principal', 'CORBATA', 'corbata panuelo-cuello lazo-cuello'],
  ['principal', 'CINTURONES', 'cinturon-cuero cinturon-vaquero cinturon-fino cinturon-bolsa'],
  ['principal', 'PERLAS', 'perlas'],
  ['principal', 'METALES', 'collar-usej collar-imperial gargantilla collar-colgante collar-cemi hei-tiki trapelacucha brazaletes brazaletes-altos'],
  ['principal', 'CUENTAS', 'collar-cuentas cuentas-largas collar-ancho-cuentas collares-zulu guirnalda broche-ovalado'],
  ['principal', 'CALZADO_GOMA', 'botas-goma'],
  ['principal', 'CALZADO_TELA', 'zapatillas-lona zapatos-tela zapatillas'],
  ['principal', 'CALZADO_RUSTICO', 'zuecos botas-trabajo botas-cuero polainas-lana sandalias-cuero sandalia-romana caliga opanke mocasines botas-piel'],
  ['principal', 'CALZADO_CUERO', 'zapatos-cuero zapatos-hebilla zapatos-vestir botas-altas botas-vaqueras botas-militares botas-mongolas botas-tibetanas'],
  ['principal', 'BLANCOS', 'enaguas tallit dishdasha cuello-alto kihei'],
  ['principal', 'TIERRAS', 'bragae escapulario faldon-campesina capa-viaje capa-lluvia-paja sombrero-paja-capa manto-lana-ancho falda-lana-gruesa'],
  ['principal', 'BLANCOS_RUSTICOS', 'delantal-peto'],
  ['principal', 'LANAS_OSCURAS', 'kepam'],
  ['principal', 'VESTIDOS_GALA', 'capa-ceremonial capa-opera'],
  ['principal', 'LUTO', 'chador'],
  ['principal', 'SEDA', 'kaftan-corto'],
  ['principal', 'CHAMANTO', 'quechquemitl'],
  ['principal', 'CUENTAS', 'obijime'],
  // Ropa de los tipos nuevos
  ['principal', 'VESTIDOS_DIA', 'vestido-tirantes vestido-halter vestido-asimetrico vestido-corazon vestido-trapecio vestido-globo vestido-tubo vestido-camisero vestido-volantes vestido-pico falda-mini falda-capas'],
  ['principal', 'VESTIDOS_GALA', 'vestido-tirantes-largo vestido-corazon-gala vestido-sirena vestido-sirena-tirantes falda-sirena'],
  ['principal', 'SEDA', 'blusa-farol blusa-pagoda top-tirantes top-halter'],
  ['principal', 'CAMISA_VESTIR', 'camisa-clasica camisa-ajustada'],
  ['principal', 'SAFARI', 'camisa-safari chaqueta-safari'],
  ['principal', 'POLERAS', 'polo camiseta-cap camiseta-sisa'],
  ['principal', 'SUDADERA', 'sudadera-capucha'],
  ['principal', 'SUETERES', 'sueter-oversize'],
  ['principal', 'PARKA', 'parka-capucha anorak chaleco-acolchado'],
  ['principal', 'BOMBER', 'bomber'],
  ['principal', 'ABRIGOS_LUJO', 'abrigo-piel-total abrigo-punos-piel'],
  ['principal', 'SASTRE', 'blazer-ajustado'],
  ['principal', 'CUERO_CHAQUETA', 'chaqueta-motera'],
  ['principal', 'BATA', 'bata-casa'],
  ['principal', 'BATIN', 'batin-seda'],
  ['principal', 'CORSE', 'corse'],
  ['principal', 'DENIM', 'jeans-clasicos'],
  ['principal', 'PANTALON_VESTIR', 'pantalon-pliegues pantalon-tubo'],
  ['principal', 'PANTALON_70', 'pantalon-pitillo'],
  ['principal', 'CARGO', 'pantalon-cargo bermuda'],
  ['principal', 'CAPRI', 'pantalon-capri'],
  ['principal', 'PESCADOR', 'pantalon-pescador'],
  ['principal', 'LEGGINGS', 'leggings'],
  ['principal', 'SAROUEL', 'sarouel'],
  ['principal', 'FALDAS_CIUDAD', 'culotte falda-globo falda-trapecio falda-abertura falda-abertura-lado falda-midi'],
  ['principal', 'PALAZZO', 'pantalon-palazzo mono-elegante'],
  ['principal', 'JODHPUR', 'pantalon-jodhpur'],
  ['principal', 'MONO', 'mono-trabajo'],
  ['principal', 'PIEL_CLARA', 'cuello-piel'],
  ['principal', 'ACCESORIO_TELA', 'esclavina chalina panuelo-triangulo babero-cuello'],
  ['principal', 'TIRANTES', 'tirantes-pantalon'],
  ['principal', 'TACONES', 'tacones'],
  ['principal', 'CHANCLETAS', 'chancletas'],
  ['principal', 'CALZADO_CUERO', 'botines zapatos-oxford'],
  ['ribete', 'COSTURA_DENIM', 'jeans-clasicos'],
  ['detalle', 'BOTONES_METAL', 'jeans-clasicos'],
  ['detalle', 'BOTONES', 'camisa-clasica camisa-ajustada camisa-safari polo vestido-camisero chaqueta-safari parka-capucha anorak abrigo-piel-total abrigo-punos-piel blazer-ajustado chaleco-acolchado vestido-tirantes vestido-halter'],
  // Costuras y botones
  ['ribete', 'COSTURA_DENIM', 'pantalon-vaquero chaqueta-denim camisa-denim pantalon-overol overol-peto'],
  ['detalle', 'BOTONES_METAL', 'pantalon-vaquero chaqueta-denim camisa-denim pantalon-overol overol-peto'],
  ['detalle', 'BOTONES', 'camisa-blanca camisa-cuadros camisa-trabajo camisa-manga-corta polera sueter-lana cardigan chaleco-lana saco-terno saco-cruzado chaleco-terno traje-sastre gabardina abrigo-corto impermeable chaqueta-militar chaqueta-cuero guayabera liquiliqui guardapolvo vestido-casa vestido-30 vestido-40 vestido-50 vestido-novia vestido-luto blusa-seda blusa-cuello-bebe camison chaleco-corto chaleco-largo levita frac'],
];

const PERFIL_PRENDA = {};
for (const [rol, perfil, ids] of GRUPOS) {
  for (const id of ids.split(/\s+/).filter(Boolean)) {
    if (!PRENDAS_POR_ID[id]) continue; // ids de grupos que no existen se ignoran (ver las pruebas)
    PERFIL_PRENDA[id] ??= {};
    PERFIL_PRENDA[id][rol] ??= perfil;
  }
}
/** Ids que los grupos nombran y no existen: para que las pruebas los vean. */
const IDS_DE_GRUPOS = GRUPOS.flatMap(([, , ids]) => ids.split(/\s+/).filter(Boolean));

/** Perfil propio de una prenda y un rol, o null si toma el color del atuendo. */
function perfilDe(prendaId, rol = 'principal') {
  const p = PERFIL_PRENDA[prendaId]?.[rol];
  if (p) return { nombre: p, pesos: PERFILES[p] };
  return null;
}

const disponible = (tinteId, anio) => {
  const t = TINTES_POR_ID[tinteId];
  return !!t && (anio === undefined || anio === null || (anio >= t.desde && anio <= t.hasta));
};

function normalizar(pares, anio) {
  let lista = pares.filter(([id]) => disponible(id, anio));
  if (!lista.length) lista = pares.filter(([id]) => TINTES_POR_ID[id]);
  const total = lista.reduce((s, [, w]) => s + w, 0) || 1;
  return lista.map(([id, w]) => ({ id, nombre: TINTES_POR_ID[id].nombre, hex: TINTES_POR_ID[id].hex, pct: Math.round((w / total) * 1000) / 10 }))
    .sort((a, b) => b.pct - a.pct);
}

const cache = new Map();

/**
 * Probabilidad de cada color de una prenda para un rol ('principal', 'secundario', 'ribete', 'detalle').
 * Devuelve [{ id, nombre, hex, pct }] de mayor a menor, sumando 100. Con `anio` descarta los tintes que aún no existían;
 * con `atuendo` (id) limita el cálculo derivado a ese atuendo.
 */
function distribucionColor(prendaId, rol = 'principal', { anio, atuendo } = {}) {
  const clave = `${prendaId}|${rol}|${anio ?? ''}|${atuendo ?? ''}`;
  if (cache.has(clave)) return cache.get(clave);
  const prenda = PRENDAS_POR_ID[prendaId];
  if (!prenda) throw new Error(`Prenda desconocida: ${prendaId}`);
  let r;
  const propio = perfilDe(prendaId, rol);
  if (propio) r = normalizar(propio.pesos, anio).map((x) => ({ ...x, origen: `perfil ${propio.nombre}` }));
  else {
    const letra = prenda.col[rol];
    let usos = ATUENDOS.filter((a) => a.prendas.includes(prendaId));
    if (atuendo) usos = usos.filter((a) => a.id === atuendo);
    else if (anio !== undefined && anio !== null) { const cerca = usos.filter((a) => anio >= a.epoca[0] && anio <= a.epoca[1]); if (cerca.length) usos = cerca; }
    const acum = new Map();
    for (const a of usos) {
      const ids = (a.paleta[letra] ?? []).filter((id) => disponible(id, anio));
      const validos = ids.length ? ids : (a.paleta[letra] ?? []);
      for (const id of validos) acum.set(id, (acum.get(id) ?? 0) + 1 / usos.length / validos.length);
    }
    r = acum.size ? normalizar([...acum.entries()], anio).map((x) => ({ ...x, origen: 'atuendo' })) : [];
  }
  cache.set(clave, r);
  return r;
}

/** Todos los roles de color de una prenda: { principal: [...], secundario: [...], ... } (solo los que tienen datos). */
function catalogoColores(prendaId, opc = {}) {
  const prenda = PRENDAS_POR_ID[prendaId];
  if (!prenda) throw new Error(`Prenda desconocida: ${prendaId}`);
  const out = {};
  for (const rol of Object.keys(prenda.col)) {
    const d = distribucionColor(prendaId, rol, opc);
    if (d.length) out[rol] = d;
  }
  return out;
}

/**
 * Elige un color del perfil propio de la prenda (null si no tiene perfil para ese rol).
 * `preferidos`: ids de tintes que el atuendo ya usa; pesan `refuerzo` veces más.
 */
function muestrearColor(prendaId, rol, azar, { anio, preferidos = [], refuerzo = 2.5 } = {}) {
  const propio = perfilDe(prendaId, rol);
  if (!propio) return null;
  const base = normalizar(propio.pesos, anio);
  const pesos = base.map((x) => [x, x.pct * (preferidos.includes(x.id) ? refuerzo : 1)]);
  const elegido = azar.ponderado(pesos);
  return { id: elegido.id, nombre: elegido.nombre, hex: elegido.hex, pct: elegido.pct };
}

/**
 * Elige un color para cualquier prenda: del perfil propio si lo tiene y, si no, del reparto derivado de los atuendos
 * que la llevan (para vestir una prenda suelta, sin atuendo). Devuelve null si la prenda no tiene colores para ese papel.
 */
function muestrearColorLibre(prendaId, rol, azar, { anio, preferidos = [], refuerzo = 2.5 } = {}) {
  const lista = distribucionColor(prendaId, rol, { anio });
  if (!lista.length) return null;
  const elegido = azar.ponderado(lista.map((x) => [x, x.pct * (preferidos.includes(x.id) ? refuerzo : 1)]));
  return { id: elegido.id, nombre: elegido.nombre, hex: elegido.hex, pct: elegido.pct, origen: elegido.origen };
}

Object.assign(__e, { PERFILES, IDS_DE_GRUPOS, perfilDe, distribucionColor, catalogoColores, muestrearColor, muestrearColorLibre });
return __e;
})();

__m['validacion'] = (() => {
const __e = {};
// Validación de las opciones que recibe el generador. Un valor mal escrito (`sexo: 'z'`, `peinado: 'corot'`,
// `edad: 150`) lanza un Error que dice qué valores hay, en vez de caer en silencio en uno por defecto o
// fallar más adelante con un mensaje que no ayuda. Es la misma forma que ya usan `etnia` y `atuendo`.

const { PIELES, CABELLOS } = __m['datos/colores'];
const { PRENDAS } = __m['datos/prendas'];
const { REGIONES } = __m['datos/atuendos'];
const { PEINADOS_ID, BARBAS_ID, TOCADOS_ID } = __m['cabeza'];
const { CORPULENCIAS, POSTURAS, EDAD_MIN, EDAD_MAX, ALTURA_MIN, ALTURA_MAX, CORPULENCIA_MIN, CORPULENCIA_MAX } = __m['cuerpo'];
const { esHex } = __m['util'];

const lista = (v) => v.join(', ');
const falla = (campo, valor, validos) => { throw new Error(`${campo} no válido: ${JSON.stringify(valor)}. Valores posibles: ${lista(validos)}.`); };
const enLista = (campo, valor, validos) => { if (!validos.includes(valor)) falla(campo, valor, validos); };
const numero = (campo, valor, min, max, unidad = '') => {
  if (typeof valor !== 'number' || !Number.isFinite(valor) || valor < min || valor > max) {
    throw new Error(`${campo} no válido: ${JSON.stringify(valor)}. Debe ser un número entre ${min} y ${max}${unidad}.`);
  }
};
const hexOId = (campo, valor, ids) => { if (!esHex(valor) && !ids.includes(valor)) falla(campo, valor, [...ids, '#rrggbb']); };

const ESTILOS_ROPA_NUEVA = ['sobrio', 'trabajo', 'festivo', 'ceremonial'];
const ESQUEMAS = ['analogo', 'complementario', 'triada', 'monocromo', 'tierra', 'festivo'];
const SALUDOS = ['derecha', 'izquierda', 'ninguno', 'ninguna'];
const BRAZOS_LIBRES = ['abajo', 'cintura', 'bolsillo'];

/** Comprueba las opciones de `generarPersona` (y de `generarRopaNueva`); no cambia nada, solo lanza si algo no vale. */
function validarOpciones(opc = {}) {
  if (opc === null || typeof opc !== 'object') throw new Error('Las opciones deben ser un objeto.');
  const dado = (k) => opc[k] !== undefined && opc[k] !== null;
  if (opc.sexo !== undefined) enLista('Sexo', opc.sexo, ['f', 'm']);
  if (opc.edad !== undefined) numero('Edad', opc.edad, EDAD_MIN, EDAD_MAX, ' años');
  if (opc.altura !== undefined) numero('Altura', opc.altura, ALTURA_MIN, ALTURA_MAX, ' cm');
  if (opc.corpulencia !== undefined) {
    if (typeof opc.corpulencia === 'number') numero('Corpulencia', opc.corpulencia, CORPULENCIA_MIN, CORPULENCIA_MAX);
    else enLista('Corpulencia', opc.corpulencia, Object.keys(CORPULENCIAS));
  }
  if (opc.postura !== undefined && typeof opc.postura !== 'number') enLista('Postura', opc.postura, Object.keys(POSTURAS));
  if (opc.saludo !== undefined && opc.saludo !== false && opc.saludo !== null) enLista('Saludo', opc.saludo, SALUDOS);
  if (opc.pose !== undefined && opc.pose !== null && typeof opc.pose !== 'object') enLista('Pose', opc.pose, ['casual', 'firme']);
  if (opc.pose && typeof opc.pose === 'object') {
    if (opc.pose.brazoLibre !== undefined) enLista('pose.brazoLibre', opc.pose.brazoLibre, BRAZOS_LIBRES);
    if (opc.pose.carga !== undefined) enLista('pose.carga', opc.pose.carga, [-1, 0, 1]);
  }
  if (opc.detalle !== undefined) enLista('Detalle', opc.detalle, ['minimo', 'medio', 'alto']);
  if (opc.contorno !== undefined) enLista('Contorno', opc.contorno, ['ninguno', 'suave']);
  if (opc.clase !== undefined) enLista('Clase', opc.clase, ['baja', 'media', 'alta']);
  if (opc.anio !== undefined) numero('Año', opc.anio, -100000, 3000);
  if (opc.region !== undefined) enLista('Región', opc.region, Object.keys(REGIONES));
  if (dado('piel')) hexOId('Piel', opc.piel, PIELES.map((p) => p.id));
  if (dado('cabello')) hexOId('Cabello', opc.cabello, CABELLOS.map((c) => c.id));
  if (dado('peinado')) enLista('Peinado', opc.peinado, PEINADOS_ID);
  if (opc.barba !== undefined && typeof opc.barba !== 'boolean') enLista('Barba', opc.barba, [...BARBAS_ID, true, false]);
  if (dado('tocado')) enLista('Tocado', opc.tocado, TOCADOS_ID);
  if (dado('calzado')) enLista('Calzado', opc.calzado, PRENDAS.filter((p) => p.ranura === 'calzado').map((p) => p.id));
  if (opc.colores !== undefined) enLista('Colores', opc.colores, ['atuendo', 'prenda']);
  if (opc.variacion !== undefined) numero('Variación', opc.variacion, 0, 1);
  if (opc.estilo !== undefined) enLista('Estilo', opc.estilo, ESTILOS_ROPA_NUEVA);
  if (opc.esquema !== undefined) enLista('Esquema', opc.esquema, ESQUEMAS);
  if (opc.paleta !== undefined) {
    if (opc.paleta === null || typeof opc.paleta !== 'object') throw new Error('La paleta debe ser un objeto {A, B, C, D} con colores #rrggbb.');
    for (const [k, v] of Object.entries(opc.paleta)) if (!esHex(v)) throw new Error(`Color de paleta no válido en ${k}: ${JSON.stringify(v)}. Debe ser #rrggbb.`);
  }
  if (opc.vestir !== undefined && opc.vestir !== null) {
    const v = Array.isArray(opc.vestir) ? opc.vestir : (typeof opc.vestir === 'object' ? Object.values(opc.vestir) : null);
    if (!v) throw new Error('`vestir` debe ser una lista de ids de prenda o un objeto { ranura: id }.');
  }
}

Object.assign(__e, { ESTILOS_ROPA_NUEVA, ESQUEMAS, SALUDOS, validarOpciones });
return __e;
})();

__m['generador'] = (() => {
const __e = {};
// Generador de personas: elige un atuendo del catálogo (o inventa uno), le pone colores y devuelve la
// descripción que `renderizar` dibuja. Con la misma semilla y las mismas opciones sale siempre lo mismo.

const { hexARgb, crearAzar, variar, mezclar, oscurecer, aclarar, luminancia, hexAHsl, hslAHex, limitar, esHex } = __m['util'];
const { PIELES, CABELLOS, TINTES, TINTES_POR_ID } = __m['datos/colores'];
const { PRENDAS_POR_ID, PRENDAS } = __m['datos/prendas'];
const { ATUENDOS, ATUENDOS_POR_ID, REGIONES } = __m['datos/atuendos'];
const { MANDIBULAS_ID, PEINADOS, BARBAS, TOCADOS } = __m['cabeza'];
const { PATRONES_ID } = __m['patrones'];
const { CORPULENCIAS, POSTURAS, POSE_FIRME, poseAleatoria } = __m['cuerpo'];
const { renderizar } = __m['svg'];
const { ETNIAS, ETNIAS_POR_ID } = __m['datos/etnias'];
const { distribucionColor, catalogoColores, muestrearColor, muestrearColorLibre, perfilDe, PERFILES } = __m['datos/colores-prendas'];
const { medidas } = __m['cuerpo'];
const { validarOpciones } = __m['validacion'];
const { TIPOS } = __m['tipos-de-ropa'];

// ---------- Consulta del catálogo ----------

const contiene = (epoca, anio) => anio === undefined || anio === null || (anio >= epoca[0] && anio <= epoca[1]);

/** Atuendos que cumplen los filtros: { region, anio, sexo, clase }. */
function buscarAtuendos({ region, regiones, prefijos, anio, sexo, clase } = {}) {
  const rs = regiones ?? (region ? [region] : null);
  return ATUENDOS.filter((a) =>
    (!rs || rs.includes(a.region)) && (!prefijos || prefijos.some((p) => a.id.startsWith(p))) && contiene(a.epoca, anio) && (!sexo || a.sexo === sexo) && (!clase || a.clase === clase));
}

function listarRegiones() { return { ...REGIONES }; }
function listarCatalogo() {
  return { perfilesDeColor: Object.keys(PERFILES).length, atuendos: ATUENDOS.length, prendas: PRENDAS.length, tintes: TINTES.length, pieles: PIELES.length, cabellos: CABELLOS.length,
    etnias: ETNIAS.length, peinados: Object.keys(PEINADOS).length, barbas: Object.keys(BARBAS).length, tocados: Object.keys(TOCADOS).length, patrones: PATRONES_ID.length };
}

// ---------- Paletas ----------

const disponible = (t, anio) => anio === undefined || (anio >= t.desde && anio <= t.hasta);
const distanciaColor = (a, b) => { const [r1, g1, b1] = hexARgb(a), [r2, g2, b2] = hexARgb(b); return (r1 - r2) ** 2 + (g1 - g2) ** 2 + (b1 - b2) ** 2; };

/**
 * Los tintes de `ids` que existían en el año dado. Si ninguno (un traje de 1900 con un tinte que dejó de usarse en 1870),
 * se buscan los más parecidos de la misma familia de color que sí existían, para no dibujar un tinte de otra época.
 * Devuelve { ok, sustituye }: `sustituye` es el tinte pedido al que reemplazan, o null.
 */
function tintesDelAnio(ids, anio) {
  const ok = ids.filter((id) => { const t = TINTES_POR_ID[id]; return t && disponible(t, anio); });
  if (ok.length || anio === undefined) return { ok: ok.length ? ok : ids, sustituye: null };
  const original = TINTES_POR_ID[ids[0]];
  if (!original) return { ok: ids, sustituye: null };
  const mismos = (soloFamilia) => TINTES.filter((t) => disponible(t, anio) && (!soloFamilia || t.familia === original.familia));
  const candidatos = mismos(true).length ? mismos(true) : mismos(false);
  const cerca = candidatos.sort((x, y) => distanciaColor(x.hex, original.hex) - distanciaColor(y.hex, original.hex)).slice(0, 3).map((t) => t.id);
  return cerca.length ? { ok: cerca, sustituye: original } : { ok: ids, sustituye: null };
}

/** Un tinte disponible en el año dado (si hay uno en la lista), con una pequeña variación. */
function tinteDe(ids, azar, anio) {
  const { ok, sustituye } = tintesDelAnio(ids, anio);
  const id = azar.elegir(ok);
  const t = TINTES_POR_ID[id];
  if (!t) throw new Error(`Tinte desconocido: ${id}`);
  return { id, nombre: t.nombre, hex: variar(t.hex, azar, { tono: 4, sat: 0.04, luz: 0.04 }), sustituye };
}

/**
 * Paleta de cuatro colores {A, B, C, D} (principal, secundario, ribete, detalle).
 * esquema: 'analogo' | 'complementario' | 'triada' | 'monocromo' | 'tierra' | 'festivo'.
 */
function generarPaleta({ esquema = 'analogo', semilla = 1, base } = {}) {
  const azar = crearAzar(`paleta:${semilla}`);
  const h0 = base ? hexAHsl(base)[0] : azar.entre(0, 360);
  const s0 = azar.entre(0.35, 0.7), l0 = azar.entre(0.28, 0.5);
  const c = (h, s, l) => hslAHex(h, limitar(s, 0.05, 0.95), limitar(l, 0.08, 0.92));
  let A, B, C, D;
  switch (esquema) {
    case 'complementario': A = c(h0, s0, l0); B = c(h0 + 180, s0 * 0.9, l0 + 0.08); C = c(h0 + 180, s0, l0 - 0.1); D = c(h0 + 40, 0.7, 0.55); break;
    case 'triada': A = c(h0, s0, l0); B = c(h0 + 120, s0 * 0.85, l0 + 0.05); C = c(h0 + 240, s0 * 0.85, l0); D = c(h0 + 60, 0.75, 0.55); break;
    case 'monocromo': A = c(h0, s0, l0); B = c(h0, s0 * 0.8, l0 + 0.18); C = c(h0, s0, l0 - 0.14); D = c(h0, s0 * 0.6, l0 + 0.32); break;
    case 'tierra': { const h = azar.entre(18, 48); A = c(h, azar.entre(0.3, 0.55), azar.entre(0.28, 0.45)); B = c(h + 10, 0.3, 0.6); C = c(h - 8, 0.45, 0.22); D = c(h + 5, 0.6, 0.5); break; }
    case 'festivo': A = c(h0, 0.5, 0.36); B = c(h0 + 150, 0.45, 0.5); C = c(h0 + 210, 0.5, 0.38); D = c(h0 + 45, 0.62, 0.55); break;
    default: A = c(h0, s0, l0); B = c(h0 + 28, s0 * 0.9, l0 + 0.1); C = c(h0 - 28, s0, l0 - 0.1); D = c(h0 + 60, 0.7, 0.55);
  }
  return { A, B, C, D };
}

/** Paleta histórica: un tinte por letra, de las listas del atuendo, respetando la época. */
function paletaDeAtuendo(a, azar, anio) {
  const p = {}, nombres = {}, ids = {}, avisos = [];
  for (const [letra, lista] of Object.entries(a.paleta)) {
    const t = tinteDe(lista, azar, anio);
    p[letra] = t.hex; nombres[letra] = t.nombre; ids[letra] = t.id;
    if (t.sustituye) avisos.push(`El tinte «${t.sustituye.nombre}» no existía en el año ${anio}: se usó «${t.nombre}», el más parecido de su época.`);
  }
  return { colores: p, nombres, ids, avisos };
}

/**
 * Colores de una prenda. Si tiene perfil propio para un rol se elige según su %, con los tintes que el atuendo ya usa
 * pesando 2,5 veces más; si no, se usa el color de la letra de la paleta del atuendo.
 * ctx: { azar, anio, usarPerfiles, idsPaleta, preferidos }. Devuelve { k, info } con info[rol] = { id, nombre, pct, origen }.
 */
function coloresDePrenda(pr, pal, ctx) {
  const A = pal.A ?? '#c9b99a';
  const B = pal.B ?? mezclar(A, '#ffffff', 0.3);
  const C = pal.C ?? oscurecer(A, 0.28);
  const D = pal.D ?? '#caa24a';
  const letra = { A, B, C, D };
  const k = {}, info = {};
  for (const [rol, l] of Object.entries(pr.col)) {
    const elegido = ctx.libre ? muestrearColorLibre(pr.id, rol, ctx.azar, { anio: ctx.anio, preferidos: ctx.preferidos })
      : ctx.usarPerfiles ? muestrearColor(pr.id, rol, ctx.azar, { anio: ctx.anio, preferidos: ctx.preferidos }) : null;
    if (elegido) {
      k[rol] = variar(elegido.hex, ctx.azar, { tono: 3, sat: 0.04, luz: 0.04 });
      info[rol] = { id: elegido.id, nombre: elegido.nombre, pct: elegido.pct, origen: ctx.libre ? (elegido.origen === 'atuendo' ? 'catálogo (reparto de atuendos)' : 'perfil') : 'perfil' };
    } else {
      k[rol] = letra[l] ?? A;
      const id = ctx.idsPaleta?.[l];
      if (id) {
        const d = distribucionColor(pr.id, rol, { anio: ctx.anio });
        info[rol] = { id, nombre: TINTES_POR_ID[id]?.nombre ?? id, pct: d.find((x) => x.id === id)?.pct ?? null, origen: 'atuendo' };
      }
    }
  }
  // Que el ribete y el detalle se distingan del color principal.
  if (Math.abs(luminancia(k.ribete) - luminancia(k.principal)) < 0.04 && k.ribete === k.principal) {
    k.ribete = luminancia(k.principal) > 0.35 ? oscurecer(k.principal, 0.35) : aclarar(k.principal, 0.35);
  }
  return { k, info };
}

/** Prendas que van juntas y suelen ser del mismo color: [prenda que manda, prenda que copia, probabilidad]. */
const CONJUNTOS = [
  ['pantalon-overol', 'overol-peto', 1],
  ['saco-terno', 'pantalon-vestir', 0.7],
  ['saco-terno', 'chaleco-terno', 0.8],
  ['saco-cruzado', 'pantalon-vestir', 0.7],
  ['traje-sastre', 'falda-lapiz', 0.75],
  ['saco-terno', 'pantalon-militar', 0.7],
];

function enlazarConjuntos(prendas, azar) {
  for (const [a, b, prob] of CONJUNTOS) {
    const pa = prendas.find((p) => p.id === a), pb = prendas.find((p) => p.id === b);
    if (!pa || !pb || !pa.colorInfo?.principal || azar.n() >= prob) continue;
    pb.colores = { ...pb.colores, principal: pa.colores.principal };
    pb.colorInfo = { ...pb.colorInfo, principal: { ...pa.colorInfo.principal, origen: `igual que ${pa.nombre}` } };
  }
}

/** Una sobreprenda o un manto con el mismo color que la túnica de debajo se pierde: se pasa al color secundario o se oscurece. */
function distinguirCapas(prendas, pal) {
  const torso = prendas.find((p) => p.ranura === 'torso');
  if (!torso) return;
  for (const p of prendas) {
    if (!['sobre', 'capa'].includes(p.ranura) || p.colores.principal !== torso.colores.principal || p.plantilla === 'delantal') continue;
    const alt = pal.B && Math.abs(luminancia(pal.B) - luminancia(pal.A)) > 0.05 ? pal.B : (luminancia(pal.A) > 0.4 ? oscurecer(pal.A, 0.32) : aclarar(pal.A, 0.3));
    p.colores = { ...p.colores, principal: alt, secundario: pal.A };
  }
}

// ---------- Variación de parámetros ----------

function variarParams(params, azar, fuerza) {
  const p = { ...params };
  if (typeof p.holgura === 'number') p.holgura = Math.max(0.002, p.holgura * azar.entre(1 - 0.25 * fuerza, 1 + 0.3 * fuerza));
  if (typeof p.vuelo === 'number') p.vuelo = Math.max(0, p.vuelo * azar.entre(1 - 0.25 * fuerza, 1 + 0.25 * fuerza));
  if (typeof p.pliegues === 'number') p.pliegues = Math.max(0, p.pliegues + azar.int(-1, 1) * Math.round(fuerza));
  if (typeof p.ancho === 'number' && p.ancho < 3) p.ancho = p.ancho * azar.entre(0.9, 1.1);
  return p;
}

// ---------- Rasgos de la persona ----------

const SEXOS = { f: 'mujer', m: 'hombre' };

function edadAleatoria(azar) {
  return Math.round(azar.ponderado([[azar.entre(3, 12), 1.2], [azar.entre(13, 19), 1], [azar.entre(20, 39), 3.4], [azar.entre(40, 59), 2.4], [azar.entre(60, 88), 1.2]]));
}

function elegirPiel(azar, atuendo, opc, etnia) {
  if (opc.piel) return esHex(opc.piel) ? opc.piel : (PIELES.find((x) => x.id === opc.piel)?.hex ?? PIELES[4].hex);
  const hexDe = (ids) => (PIELES.find((p) => p.id === azar.elegir(ids)) ?? PIELES[4]).hex;
  if (etnia) {
    // Una mezcla promedia un tono de cada pueblo, según su peso.
    let acum = null, peso = 0;
    for (const x of etnia) {
      const h = hexDe(x.e.pieles);
      acum = acum === null ? h : mezclar(acum, h, x.peso / (peso + x.peso));
      peso += x.peso;
    }
    return variar(acum, azar, { tono: 3, sat: 0.03, luz: 0.025 });
  }
  const base = hexDe(atuendo.pieles ?? PIELES.map((p) => p.id));
  return variar(base, azar, { tono: 3, sat: 0.03, luz: 0.025 });
}

function indicePiel(hex) {
  let mejor = 0, d = 1e9;
  const l = luminancia(hex);
  PIELES.forEach((p, i) => { const dd = Math.abs(luminancia(p.hex) - l); if (dd < d) { d = dd; mejor = i; } });
  return mejor;
}

function elegirCabello(azar, piel, edad, opc, etnia) {
  if (opc.cabello) return esHex(opc.cabello) ? opc.cabello : (CABELLOS.find((c) => c.id === opc.cabello)?.hex ?? CABELLOS[0].hex);
  const ip = indicePiel(piel);
  let pesos;
  if (etnia) pesos = sumaPesos(etnia, (e) => e.cabellos);
  else if (ip >= 7) pesos = [['negro', 6], ['negro-azulado', 2], ['castano-oscuro', 1]];
  else if (ip >= 3) pesos = [['negro', 5], ['negro-azulado', 2], ['castano-oscuro', 3], ['castano', 2], ['cobrizo', 0.2]];
  else pesos = [['negro', 2], ['castano-oscuro', 3], ['castano', 3], ['castano-claro', 2], ['rubio-oscuro', 1.5], ['rubio', 1.5], ['rubio-ceniza', 0.7], ['pelirrojo', 0.8], ['cobrizo', 0.6]];
  let id = azar.ponderado(pesos);
  // Canas.
  const probCanas = limitar((edad - 42) / 38, 0, 1);
  if (azar.n() < probCanas) id = azar.elegir(edad > 68 ? ['gris', 'blanco', 'blanco', 'canoso'] : ['canoso', 'gris']);
  const base = CABELLOS.find((c) => c.id === id).hex;
  return variar(base, azar, { tono: 3, sat: 0.03, luz: 0.03 });
}

// ---------- Etnia ----------

/** Etnia como texto, lista o {id: peso}: devuelve [{ e, peso }] con los pesos sumando 1. La primera manda en la ropa. */
function normalizarEtnia(spec) {
  if (spec === undefined || spec === null || spec === '') return null;
  let pares;
  if (typeof spec === 'string') pares = spec.split(',').map((x) => [x.trim(), 1]);
  else if (Array.isArray(spec)) pares = spec.map((x) => [x, 1]);
  else pares = Object.entries(spec);
  const lista = pares.filter(([id]) => id).map(([id, peso]) => {
    const e = ETNIAS_POR_ID[id];
    if (!e) throw new Error(`Etnia desconocida: ${id}. Disponibles: ${ETNIAS.map((x) => x.id).join(', ')}`);
    const w = Number(peso);
    if (!Number.isFinite(w) || w < 0) throw new Error(`Peso no válido para la etnia ${id}: ${JSON.stringify(peso)}. Debe ser un número mayor o igual que 0.`);
    return { e, peso: w };
  }).filter((x) => x.peso > 0); // peso 0: esa etnia no cuenta
  if (!lista.length) {
    if (pares.some(([id]) => id)) throw new Error('Todas las etnias tienen peso 0: al menos una debe pesar más que 0.');
    return null;
  }
  const total = lista.reduce((t, x) => t + x.peso, 0);
  return lista.map((x) => ({ e: x.e, peso: x.peso / total }));
}

const sumaPesos = (lista, fn) => {
  const m = new Map();
  for (const { e, peso } of lista) for (const [id, w] of fn(e)) m.set(id, (m.get(id) ?? 0) + w * peso);
  return [...m.entries()];
};

/** Ajuste de estatura por época (poblaciones más bajas antes del siglo XX), aproximado. */
function ajusteEstaturaAnio(anio) {
  if (anio === undefined || anio === null) return 0;
  if (anio < 1000) return -0.02;
  if (anio < 1800) return -0.015;
  if (anio < 1950) return -0.005;
  return 0.01;
}

const RANGO_CONFIANZA = { baja: 0, media: 1, alta: 2 };
/** La menor confianza de las que se combinan: una representación es tan sólida como su parte más débil. */
function menorConfianza(lista) {
  return lista.reduce((m, c) => (RANGO_CONFIANZA[c] < RANGO_CONFIANZA[m] ? c : m), 'alta');
}

const ESTATURA_REF = { f: 161, m: 174 }; // los promedios con los que medidas() calcula la altura por edad

// ---------- Persona ----------

/**
 * Opciones (todas opcionales): semilla, sexo ('f'|'m'), edad, corpulencia, altura (cm), postura,
 * atuendo (id), region, anio, clase ('baja'|'media'|'alta'), piel, cabello, barba (true|false|id),
 * peinado, tocado, calzado, paleta ({A,B,C,D} en hex), variacion (0..1), detalle ('minimo'|'medio'|'alto'),
 * rasgos (true: ojos, cejas y boca suaves), fondo, prendas (lista propia de prendas ya armadas).
 */
function generarPersona(opc = {}) {
  validarOpciones(opc);
  const semilla = opc.semilla ?? 1;
  const azar = crearAzar(`persona:${semilla}`);
  const sexo = opc.sexo ?? (azar.bool() ? 'f' : 'm');
  const edad = Math.round(opc.edad ?? edadAleatoria(azar));
  const anio = opc.anio;

  const etnia = normalizarEtnia(opc.etnia);
  const estimaciones = [], avisos = [];

  // Atuendo: se afloja un filtro a la vez si no hay coincidencias, y se avisa de cuáles.
  let atuendo;
  if (opc.atuendo) {
    atuendo = ATUENDOS_POR_ID[opc.atuendo];
    if (!atuendo) throw new Error(`Atuendo desconocido: ${opc.atuendo}`);
  } else {
    const regiones = opc.region ? [opc.region] : (etnia ? etnia[0].e.regiones : null);
    const prefijos = opc.region ? null : (etnia ? etnia[0].e.prefijos : null);
    const f = (extra) => ({ regiones, prefijos, anio, sexo, clase: opc.clase, ...extra });
    const intentos = [
      [f({}), null],
      [f({ clase: undefined }), 'la clase social'],
      [f({ anio: undefined }), 'el año'],
      [f({ clase: undefined, anio: undefined }), 'el año y la clase social'],
      [f({ prefijos: null, clase: undefined, anio: undefined }), 'el año, la clase social ni el pueblo concreto: es un atuendo de otro pueblo de la misma región'],
      [{ anio, sexo, clase: opc.clase }, 'la región'],
      [{ anio, sexo }, 'la región y la clase social'],
      [{ sexo }, 'la región, el año y la clase social'],
    ].filter(([filtro], i, todos) => i === 0 || JSON.stringify(filtro) !== JSON.stringify(todos[i - 1][0]));
    for (const [filtro, relajado] of intentos) {
      const r = buscarAtuendos(filtro);
      if (r.length) {
        atuendo = azar.elegir(r);
        if (relajado) avisos.push(`No hay atuendo que cumpla ${relajado}${etnia ? ` para ${etnia[0].e.nombre}` : ''}: se eligió «${atuendo.nombre}» (${atuendo.epoca[0]} a ${atuendo.epoca[1]}).`);
        break;
      }
    }
    if (etnia && !opc.region) estimaciones.push(`Atuendo: «${atuendo.nombre}» (${REGIONES[atuendo.region]}), elegido por ser ${etnia.map((x) => x.e.nombre).join(' y ')}.`);
  }
  const anioPaleta = anio ?? Math.round((atuendo.epoca[0] + atuendo.epoca[1]) / 2);

  // Colores
  const paletaAtuendo = paletaDeAtuendo(atuendo, azar.hijo('paleta'), anioPaleta);
  const pal = opc.paleta ?? paletaAtuendo.colores;
  const nombresPaleta = opc.paleta ? null : paletaAtuendo.nombres;
  const paletaIds = paletaAtuendo.ids;
  if (!opc.paleta) avisos.push(...paletaAtuendo.avisos);
  const piel = elegirPiel(azar.hijo('piel'), atuendo, opc, etnia);
  const colorPelo = elegirCabello(azar.hijo('cabello'), piel, edad, opc, etnia);

  // Prendas
  const az = azar.hijo('prendas');
  const variacion = opc.variacion ?? 0.5;
  // `vestir`: prendas elegidas una por una (lista de ids o { ranura: id }); los colores salen del catálogo de cada prenda.
  let ids = atuendo.prendas.slice();
  const vestir = opc.vestir ? (Array.isArray(opc.vestir) ? opc.vestir : Object.values(opc.vestir)).filter(Boolean) : null;
  if (vestir && !vestir.length) avisos.push('Sin prendas elegidas: se usó el atuendo.');
  if (vestir && vestir.length) {
    ids = vestir.slice();
    for (const id of ids) if (!PRENDAS_POR_ID[id]) throw new Error(`Prenda desconocida: ${id}`);
    const porRanura = {};
    for (const id of ids) { const r = PRENDAS_POR_ID[id].ranura; (porRanura[r] ||= []).push(id); }
    for (const [r, l] of Object.entries(porRanura)) if (l.length > 1 && !['accesorio', 'cuello'].includes(r)) avisos.push(`Hay ${l.length} prendas en la ranura «${r}» (${l.join(', ')}): se dibujan superpuestas.`);
  }
  if (opc.calzado) ids = ids.filter((id) => PRENDAS_POR_ID[id].ranura !== 'calzado').concat(opc.calzado);
  const vestirReal = !!vestir && vestir.length > 0;
  const usarPerfiles = !opc.paleta && opc.colores !== 'atuendo';
  const idsPaleta = opc.paleta ? {} : paletaIds;
  const preferidos = Object.values(atuendo.paleta).flat();
  const ctxColor = { azar: az.hijo('colores'), anio: opc.anio ?? anioPaleta, usarPerfiles, idsPaleta, preferidos, libre: vestirReal && !opc.paleta && opc.colores !== 'atuendo' };
  const prendas = opc.prendas ?? ids.map((id) => {
    const pr = PRENDAS_POR_ID[id];
    if (!pr) throw new Error(`Prenda desconocida en ${atuendo.id}: ${id}`);
    const { k, info } = coloresDePrenda(pr, pal, ctxColor);
    return { id, nombre: pr.nombre, ranura: pr.ranura, plantilla: pr.plantilla, params: variarParams(pr.params, az, variacion), colores: k, colorInfo: info };
  });
  if (!opc.prendas) { enlazarConjuntos(prendas, az.hijo('conjuntos')); if (!vestirReal) distinguirCapas(prendas, pal); }

  // Cabeza
  const ah = azar.hijo('cabeza');
  const peinadosEtnia = etnia ? atuendo.peinados.filter((p) => etnia[0].e.peinados.includes(p)) : [];
  const peinadoId = opc.peinado ?? ah.elegir(peinadosEtnia.length ? peinadosEtnia : atuendo.peinados);
  let estilo = PEINADOS[peinadoId] ? peinadoId : 'corto'; // un peinado del atuendo que no exista se cambia por 'corto'; el pedido a mano ya se validó
  // Los mayores a veces pierden el pelo; la tonsura y las pelucas no se tocan.
  if (!opc.peinado && edad > 55 && sexo === 'm' && !['tonsura', 'peluca-barroca', 'peluca-egipcia'].includes(estilo) && ah.bool(0.25)) estilo = 'calvo';
  const quiereBarba = opc.barba === undefined ? true : opc.barba;
  let barbaId = 'ninguna';
  if (sexo === 'm' && edad >= 17 && quiereBarba) {
    if (typeof opc.barba === 'string') barbaId = opc.barba;
    else if (etnia) {
      const prob = etnia.reduce((t, x) => t + x.e.barba * x.peso, 0);
      const con = atuendo.barbas.filter((b) => b !== 'ninguna');
      barbaId = con.length && ah.bool(prob) ? ah.elegir(con) : 'ninguna';
    } else barbaId = ah.elegir(atuendo.barbas);
    if (typeof opc.barba !== 'string' && edad < 22 && ah.bool(0.6)) barbaId = 'ninguna';
  }
  const tocadoId = opc.tocado ?? ah.elegir(atuendo.tocados);
  const tocado = tocadoId && tocadoId !== 'ninguno' && TOCADOS[tocadoId]
    ? { id: tocadoId, colores: { principal: pal.A ?? '#c9b99a', secundario: pal.C ?? pal.B ?? '#8a6a3a', detalle: pal.D ?? '#caa24a' } }
    : null;
  if (tocado && (tocado.id === 'diadema' || tocado.id === 'corona' || tocado.id === 'trarilonko')) {
    tocado.colores.principal = pal.D ?? tocado.colores.principal;
    tocado.colores.secundario = pal.B ?? tocado.colores.secundario;
  }
  if (tocado && ['trarilonko'].includes(tocado.id)) tocado.colores.principal = '#b9bec6';

  const rostro = { ancho: 0.96 + ah.n() * 0.12, mandibula: ah.elegir(MANDIBULAS_ID), rasgos: !!opc.rasgos };
  // Pose: casual y distinta con cada semilla, salvo que se pida 'firme' (simétrica) o una pose a medida.
  let pose;
  if (opc.pose === 'firme') pose = POSE_FIRME;
  else {
    pose = poseAleatoria(azar.hijo('pose'));
    if (opc.pose && typeof opc.pose === 'object') pose = { ...pose, ...opc.pose, saludo: { ...pose.saludo, ...(opc.pose.saludo ?? {}) } };
    if (opc.postura !== undefined && !(opc.pose && opc.pose.angulos)) {
      const base = typeof opc.postura === 'number' ? opc.postura : (POSTURAS[opc.postura] ?? POSTURAS.relajada);
      pose.angulos = { 1: base + pose.angulos[1] * 0.2 - 1, '-1': base + pose.angulos['-1'] * 0.2 - 1 };
    }
  }
  const az2 = azar.hijo('cuerpo');
  const corpulenciaEstimada = etnia ? az2.ponderado(sumaPesos(etnia, (e) => e.corpulencia)) : az2.elegir(['delgada', 'media', 'media', 'media', 'robusta']);
  let escalaAltura;
  if (etnia && opc.altura === undefined) {
    const media = etnia.reduce((t, x) => t + x.e.altura[sexo === 'f' ? 0 : 1] * x.peso, 0);
    escalaAltura = (media / ESTATURA_REF[sexo]) * (1 + ajusteEstaturaAnio(anio)) * az2.entre(0.97, 1.03);
  }
  const cuerpo = {
    sexo, edad, escalaAltura,
    corpulencia: opc.corpulencia ?? corpulenciaEstimada,
    altura: opc.altura, postura: opc.postura ?? 'firme', pose,
    saludo: opc.saludo === false || opc.saludo === 'ninguno' || opc.saludo === 'ninguna' ? null : (opc.saludo ?? 'derecha'),
  };

  if (etnia) {
    const nombres = etnia.map((x) => x.e.nombre).join(' y ');
    if (!opc.piel) estimaciones.push(`Piel: ${etnia.length > 1 ? `promedio de ${nombres}` : `rango habitual de ${nombres}`}, con variación individual.`);
    if (!opc.cabello) estimaciones.push(`Cabello: color habitual de ${nombres}${edad > 45 ? ', con canas según la edad' : ''}.`);
    if (opc.altura === undefined) estimaciones.push(`Estatura: unos ${Math.round(medidas(cuerpo).cm)} cm, estimada con el promedio de ${nombres}, la edad${anio !== undefined ? ' y el año' : ''}.`);
    if (!opc.corpulencia) estimaciones.push(`Complexión: ${cuerpo.corpulencia}, la más frecuente en ${nombres}.`);
    if (sexo === 'm' && edad >= 17 && opc.barba === undefined) estimaciones.push(`Barba: ${barbaId === 'ninguna' ? 'sin barba' : barbaId}, según la frecuencia en ${nombres}.`);
  }
  const decada = (n) => (n < 0 ? `${-n} a. C.` : `${n}`);
  return {
    semilla, titulo: vestirReal ? 'Vestuario a medida' : `${atuendo.nombre}`,
    descripcion: vestirReal ? `${SEXOS[sexo][0].toUpperCase() + SEXOS[sexo].slice(1)} de ${edad} años con vestuario a medida (${ids.map((i) => PRENDAS_POR_ID[i].nombre).join(', ')}).` : `${SEXOS[sexo][0].toUpperCase() + SEXOS[sexo].slice(1)} de ${edad} años; ${atuendo.nombre} (${REGIONES[atuendo.region]}, ${decada(atuendo.epoca[0])} a ${decada(atuendo.epoca[1])}).`,
    sexo, edad, atuendo: atuendo.id, region: atuendo.region, epoca: atuendo.epoca, clase: atuendo.clase,
    cuerpo, rostro, piel,
    cabello: { estilo, color: colorPelo }, barba: { estilo: barbaId, color: colorPelo }, tocado,
    prendas, paleta: pal, nombresPaleta,
    coloresPrendas: prendas.filter((p) => p.colorInfo).map((p) => ({ prenda: p.id, nombre: p.nombre, roles: p.colorInfo })),
    etnia: etnia ? etnia.map((x) => ({ id: x.e.id, nombre: x.e.nombre, peso: Math.round(x.peso * 100) / 100 })) : null,
    estimaciones, avisos,
    confianza: menorConfianza([atuendo.confianza, ...(etnia ? etnia.map((x) => x.e.confianza) : [])]),
    notas: [atuendo.nota, ...(etnia ? etnia.map((x) => x.e.nota) : [])].filter(Boolean),
    fuentes: [...new Set([atuendo.fuente, ...(etnia ? etnia.map((x) => x.e.fuente) : [])].filter(Boolean))],
    detalle: opc.detalle ?? 'medio', contorno: opc.contorno ?? 'ninguno', fondo: opc.fondo ?? 'ninguno',
  };
}

// ---------- Ropa nueva ----------

// Las variantes salen de tipos-de-ropa.js, la fuente única que también valida las prendas.
// 'trapelacucha' es un adorno de un pueblo concreto (mapuche): no se mezcla al azar con otros estilos.
const sinEspecificos = (lista) => lista.filter((x) => x !== 'trapelacucha');
const MANGAS = TIPOS.manga, ESCOTES = TIPOS.escote, BAJOS = TIPOS.bajo, ANCHOS_PANTALON = TIPOS.anchoPantalon;
const CALZADOS = TIPOS.calzado, CUELLOS = sinEspecificos(TIPOS.cuello);
const ROLES_PATRON = PATRONES_ID.filter((p) => !['piel', 'jaspe', 'malla', 'escamas'].includes(p));

function patronAzar(azar, prob) {
  return azar.bool(prob) ? { tipo: azar.elegir(ROLES_PATRON), escala: Math.round(azar.entre(0.7, 1.4) * 10) / 10, rol: 'secundario' } : undefined;
}

/**
 * Inventa una ropa que no está en el catálogo: combina plantillas con parámetros al azar.
 * estilo: 'sobrio' | 'trabajo' | 'festivo' | 'ceremonial'. Devuelve { prendas, paleta, nombre }.
 */
function generarRopaNueva({ semilla = 1, sexo = 'f', estilo = 'sobrio', esquema, paleta } = {}) {
  validarOpciones({ sexo, estilo, esquema, paleta });
  const azar = crearAzar(`ropa:${semilla}:${sexo}:${estilo}`);
  const festivo = estilo === 'festivo' || estilo === 'ceremonial';
  const pal = paleta ?? generarPaleta({ semilla, esquema: esquema ?? (estilo === 'trabajo' ? 'tierra' : festivo ? 'festivo' : azar.elegir(['analogo', 'monocromo', 'complementario'])) });
  const col = (r = {}) => ({ principal: pal.A, secundario: pal.B, ribete: pal.C, detalle: pal.D, ...r });
  const out = [];
  const add = (ranura, plantilla, params, colores, nombre) => out.push({ id: `nueva-${plantilla}-${out.length}`, nombre, ranura, plantilla, params, colores: colores ?? col() });
  const largoLargo = sexo === 'f' ? azar.bool(0.6) : azar.bool(0.25);
  const ornato = festivo ? 0.8 : estilo === 'trabajo' ? 0.1 : 0.4;

  // Torso (y a veces falda)
  const vestido = largoLargo && azar.bool(0.55);
  const torso = {
    largo: vestido ? azar.elegir(['pantorrilla', 'tobillo', 'suelo']) : azar.elegir(['cadera', 'muslo-alto', 'muslo', 'rodilla']),
    manga: estilo === 'trabajo' ? azar.elegir(['corta', 'tres-cuartos', 'larga']) : azar.elegir(MANGAS.filter((m) => m !== 'sin' || azar.bool(0.5))),
    escote: azar.elegir(ESCOTES), prof: Math.round(azar.entre(0.5, 1.4) * 10) / 10,
    cintura: vestido ? azar.elegir([0, 0.3, 0.6, 1]) : azar.elegir([0.7, 0.9, 1]),
    holgura: Math.round(azar.entre(0.006, 0.03) * 1000) / 1000, vuelo: vestido ? Math.round(azar.entre(0.02, 0.18) * 100) / 100 : 0.02,
    bajo: azar.elegir(BAJOS), ribete: azar.bool(0.6), puños: azar.bool(ornato),
    pliegues: azar.bool(0.5) ? azar.int(2, 8) : 0,
    ribeteBajo: azar.bool(ornato) ? Math.round(azar.entre(0.015, 0.035) * 1000) / 1000 : false,
    bandas: azar.bool(ornato * 0.5) ? azar.elegir(['central', 'lateral']) : undefined,
    botones: azar.bool(0.3) ? azar.int(3, 8) : 0,
    cruzada: azar.bool(0.12) ? azar.elegir(['izq', 'der']) : undefined,
    volantes: vestido && azar.bool(festivo ? 0.4 : 0.1) ? azar.int(1, 3) : 0,
    patron: patronAzar(azar, festivo ? 0.35 : 0.15),
    bandaPatron: azar.bool(ornato * 0.5) ? azar.elegir(ROLES_PATRON) : undefined,
  };
  // Variantes extra con su propio azar: no cambian los valores de arriba.
  const extra = azar.hijo('variantes');
  if (extra.bool(0.2)) torso.forma = extra.elegir(TIPOS.forma);
  if (extra.bool(0.12)) torso.capucha = true;
  if (extra.bool(0.15)) torso.bolsillos = extra.elegir(TIPOS.bolsillosTorso);
  if (extra.bool(0.15)) torso.cuelloCamisa = true;
  if (extra.bool(0.15)) torso.cinturon = true;
  if (extra.bool(0.1)) torso.pieles = extra.elegir(TIPOS.pieles);
  add('torso', 'tunica', torso, col(), vestido ? 'Vestido nuevo' : 'Túnica nueva');

  if (!vestido) {
    if (sexo === 'f' && azar.bool(0.55)) {
      add('inferior', 'falda', { largo: azar.elegir(['rodilla', 'pantorrilla', 'tobillo', 'suelo']), vuelo: Math.round(azar.entre(0.03, 0.16) * 100) / 100, cintura: azar.elegir(['natural', 'alta', 'baja']), pliegues: azar.int(2, 8), volantes: azar.bool(0.2) ? azar.int(1, 3) : 0, bajo: azar.elegir(['recto', 'curvo', 'ondulado']), ribeteBajo: azar.bool(ornato) ? 0.02 : false, patron: patronAzar(azar, 0.2) }, col({ principal: pal.B ?? pal.A, secundario: pal.A }), 'Falda nueva');
    } else {
      add('inferior', 'pantalon', { largo: azar.elegir(['bajo-rodilla', 'pantorrilla', 'tobillo']), ancho: azar.elegir(ANCHOS_PANTALON), cintura: azar.elegir(['natural', 'baja']), cordon: azar.bool(0.3), puños: azar.bool(0.3), patron: patronAzar(azar, 0.1) }, col({ principal: pal.C ?? pal.A, ribete: pal.A }), 'Pantalón nuevo');
    }
  }
  // Sobreprenda
  if (azar.bool(festivo ? 0.55 : 0.4)) {
    add('sobre', 'chaqueta', { largo: azar.elegir(['cintura', 'cadera', 'muslo-alto', 'muslo', 'rodilla', 'pantorrilla']), manga: azar.elegir(['sin', 'sin', 'corta', 'larga', 'ancha']), botones: azar.int(0, 8), cintura: azar.elegir([0.3, 0.6, 0.9]), solapa: azar.bool(0.4), abertura: 0.01, aberturaBajo: azar.elegir([0.012, 0.03, 0.05]), vuelo: 0.04, puños: azar.bool(0.3), pliegues: azar.bool(0.3) ? azar.int(2, 5) : 0, bordadoBorde: azar.bool(ornato * 0.5) }, col({ principal: pal.C ?? pal.A, ribete: pal.D }), 'Sobreprenda nueva');
  }
  // Capa o manto
  if (azar.bool(festivo ? 0.5 : 0.3)) {
    const tipo = azar.elegir(['capa', 'poncho', 'drapeado', 'chal']);
    const params = { capa: { largo: azar.elegir(['cadera', 'muslo', 'pantorrilla', 'tobillo', 'suelo']), ancho: Math.round(azar.entre(0.6, 1.3) * 10) / 10, cierre: 'broche', ribete: azar.bool(0.6), ribeteBajo: azar.bool(ornato) },
      poncho: { largo: azar.elegir(['cadera', 'muslo-alto', 'muslo']), ancho: Math.round(azar.entre(0.8, 1.2) * 10) / 10, listas: azar.int(0, 4), punta: Math.round(azar.entre(0.02, 0.09) * 100) / 100, patron: patronAzar(azar, 0.35) },
      drapeado: { largo: azar.elegir(['pantorrilla', 'tobillo', 'suelo']), lado: azar.elegir(['izq', 'der']), pliegues: azar.int(3, 7), orla: azar.bool(0.6), patron: patronAzar(azar, 0.25) },
      chal: { largo: azar.elegir(['pecho', 'cintura', 'cadera']), flecos: azar.bool(0.5), patron: patronAzar(azar, 0.3) } }[tipo];
    add('capa', tipo, params, col({ principal: pal.B ?? pal.A, secundario: pal.A }), 'Manto nuevo');
  }
  // Faja, cuello, calzado, accesorios
  if (azar.bool(vestido ? 0.45 : 0.55)) add('faja', 'faja', { y: azar.elegir(['cintura', 'cintura', 'cadera', 'pecho']), ancho: Math.round(azar.entre(0.008, 0.05) * 1000) / 1000, hebilla: azar.bool(0.4), cuelga: azar.bool(0.3) ? azar.elegir(['izq', 'der']) : undefined, doble: azar.bool(0.2), patron: patronAzar(azar, 0.2) }, col({ principal: pal.C ?? pal.A, ribete: pal.D }), 'Faja nueva');
  if (azar.bool(festivo ? 0.8 : 0.4)) add('cuello', 'cuello', { tipo: azar.elegir(CUELLOS), colgante: azar.bool(0.4) }, col({ principal: pal.D ?? pal.B, secundario: pal.B }), 'Adorno de cuello nuevo');
  add('calzado', 'calzado', { tipo: azar.elegir(CALZADOS), hebilla: azar.bool(0.2) }, col({ principal: oscurecer(pal.C ?? pal.A, 0.35), secundario: pal.B }), 'Calzado nuevo');
  if (azar.bool(ornato * 0.7)) add('accesorio', 'accesorio', { tipo: azar.elegir(['brazaletes', 'bolso']), alto: azar.bool(0.3), lado: azar.elegir(['izq', 'der']) }, col({ principal: pal.D }), 'Accesorio nuevo');
  return { prendas: out, paleta: pal, nombre: `Ropa nueva (${estilo}), semilla ${semilla}` };
}

/** Una persona con ropa inventada en lugar de un atuendo del catálogo. */
function generarPersonaConRopaNueva(opc = {}) {
  const semilla = opc.semilla ?? 1;
  validarOpciones(opc);
  const sexo = opc.sexo ?? (crearAzar(`persona:${semilla}`).bool() ? 'f' : 'm');
  const ropa = generarRopaNueva({ semilla, sexo, estilo: opc.estilo ?? 'sobrio', esquema: opc.esquema, paleta: opc.paleta });
  const base = generarPersona({ ...opc, sexo, prendas: ropa.prendas, paleta: ropa.paleta });
  base.titulo = ropa.nombre;
  base.descripcion = `${SEXOS[sexo][0].toUpperCase() + SEXOS[sexo].slice(1)} de ${base.edad} años con ${ropa.nombre.toLowerCase()}.`;
  base.atuendo = null;
  return base;
}

function generarSVG(opc = {}) {
  const persona = opc.ropaNueva || opc.estilo ? generarPersonaConRopaNueva(opc) : generarPersona(opc);
  return renderizar(persona, { detalle: opc.detalle, fondo: opc.fondo, contorno: opc.contorno, compacto: opc.compacto });
}







Object.assign(__e, { POSE_FIRME, poseAleatoria, muestrearColorLibre, renderizar, PIELES, CABELLOS, TINTES, ATUENDOS, PRENDAS, REGIONES, CORPULENCIAS, POSTURAS, ETNIAS, TINTES_POR_ID, distribucionColor, catalogoColores, muestrearColor, perfilDe, PERFILES, buscarAtuendos, listarRegiones, listarCatalogo, tintesDelAnio, generarPaleta, generarPersona, generarRopaNueva, generarPersonaConRopaNueva, generarSVG });
__e.PEINADOS = __m['cabeza'].PEINADOS;
__e.BARBAS = __m['cabeza'].BARBAS;
__e.TOCADOS = __m['cabeza'].TOCADOS;
__e.PATRONES_ID = __m['patrones'].PATRONES_ID;
return __e;
})();

__m['index'] = (() => {
const __e = {};
Object.assign(__e, __m['generador']);
return __e;
})();

global.SiluetasAncestrales = __m['index'];
})(typeof globalThis !== 'undefined' ? globalThis : this);
