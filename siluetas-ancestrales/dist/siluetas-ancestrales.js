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
];

const TINTES_POR_ID = Object.fromEntries(TINTES.map((t) => [t.id, t]));

Object.assign(__e, { PIELES, CABELLOS, TINTES, TINTES_POR_ID });
return __e;
})();

__m['datos/prendas'] = (() => {
const __e = {};
// Catálogo de prendas. Cada una es una plantilla (src/prendas.js) con sus parámetros, la ranura donde se
// pone y qué color de la paleta del atuendo usa en cada papel: A = principal, B = secundario, C = ribete,
// D = detalle. Las fechas y regiones viven en los atuendos (atuendos.js), no aquí.

const COL = { principal: 'A', secundario: 'B', ribete: 'C', detalle: 'D' };
const P = (id, nombre, ranura, plantilla, params = {}, col = {}) => ({ id, nombre, ranura, plantilla, params, col: { ...COL, ...col } });

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
  P('bliaut', 'Bliaut de manga de campana', 'torso', 'tunica', { largo: 'suelo', manga: 'campana', escote: 'redondo', prof: 1.2, cintura: 0.1, holgura: 0.004, vuelo: 0.09, pliegues: 4, ribeteBajo: 0.03, vuelo2: 0 }),
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
  P('agbada', 'Agbada', 'sobre', 'tunica', { largo: 'pantorrilla', manga: 'kimono', escote: 'redondo', prof: 0.8, cintura: 1, holgura: 0.06, bordadoPecho: 'zigzag', ribete: true }),
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
];

const PRENDAS_POR_ID = Object.fromEntries(PRENDAS.map((p) => [p.id, p]));

Object.assign(__e, { PRENDAS, PRENDAS_POR_ID });
return __e;
})();

__m['datos/atuendos'] = (() => {
const __e = {};
// Atuendos completos: una combinación de prendas del catálogo (prendas.js) típica de una región, una época,
// un sexo y una clase social. Las épocas son años (negativos = antes de nuestra era) y aproximadas: los trajes
// duraron más que sus reglas. La paleta nombra tintes de colores.js; el generador elige uno por letra.

const L = (s) => s.split(/\s+/).filter(Boolean);
const A = (id, nombre, region, epoca, sexo, clase, prendas, paleta, extra = {}) => ({
  id, nombre, region, epoca, sexo, clase,
  prendas: L(prendas),
  paleta: Object.fromEntries(Object.entries(paleta).map(([k, v]) => [k, L(v)])),
  peinados: L(extra.peinados ?? 'corto'),
  barbas: L(extra.barbas ?? 'ninguna'),
  tocados: L(extra.tocados ?? 'ninguno'),
  pieles: extra.pieles ? L(extra.pieles) : null,
  nota: extra.nota ?? '',
});

const REGIONES = {
  egipto: 'Egipto antiguo', mesopotamia: 'Mesopotamia', grecia: 'Grecia antigua', roma: 'Roma', bizancio: 'Bizancio',
  'europa-medieval': 'Europa medieval', germano: 'Pueblos celtas y germanos', eslavo: 'Mundo eslavo', 'europa-moderna': 'Europa, siglos XV al XVIII',
  'europa-xix': 'Europa, siglos XIX y XX', judio: 'Comunidades judías', 'medio-oriente': 'Medio Oriente y Persia', 'asia-sur': 'Asia del Sur',
  'asia-este': 'Asia oriental', 'sudeste-asiatico': 'Sudeste asiático', 'africa-norte': 'África del norte', 'africa-oeste': 'África occidental',
  'africa-este-sur': 'África oriental y austral', polinesia: 'Polinesia', andes: 'Andes', mesoamerica: 'Mesoamérica', 'norteamerica': 'Norteamérica',
  artico: 'Ártico', mapuche: 'Pueblo mapuche', 'chile-rural': 'Chile rural y colonial', 'patagonia-fueguina': 'Patagonia y Tierra del Fuego',
  'rio-de-la-plata': 'Río de la Plata', 'hispanoamerica-colonial': 'Hispanoamérica colonial', 'rapa-nui': 'Rapa Nui',
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
    const man = (s) => S(G.E([[0, 0], [0.34, 0.2], [0.3, 0.9], [0, 1.0]]).replace(/^/, ''), c);
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

const CORPULENCIAS = { delgada: 0.86, media: 1, robusta: 1.2, corpulenta: 1.34 };
const POSTURAS = { firme: 7, relajada: 11, abierta: 22 };

/**
 * Calcula todas las medidas del cuerpo.
 * opc: { sexo: 'f'|'m', edad (años), corpulencia (nombre o número), altura (cm, opcional), postura }
 */
function medidas(opc = {}) {
  const sexo = opc.sexo === 'f' ? 'f' : 'm';
  const edad = limitar(Number(opc.edad ?? 30), 2, 100);
  const corp = typeof opc.corpulencia === 'number' ? opc.corpulencia : (CORPULENCIAS[opc.corpulencia] ?? 1);
  const escalaAltura = limitar(Number(opc.escalaAltura ?? 1), 0.8, 1.2);
  const cm = limitar(Number(opc.altura ?? porTabla(ALTURA[sexo], Math.min(edad, 20)) * escalaAltura * (edad > 60 ? 1 - (edad - 60) * 0.0009 : 1)), 70, 190);
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

  const grados = typeof opc.postura === 'number' ? opc.postura : (POSTURAS[opc.postura] ?? POSTURAS.firme);
  const tan = Math.tan((grados * Math.PI) / 180);

  // Brazo derecho de la figura (x positiva). Nace dentro del hombro y cae con la inclinación pedida.
  const hombroX = w.hombro;
  const brazoY0 = y.hombro + H * 0.02;
  const bx = (yy) => hombroX - w.brazoSup * 0.95 + (yy - brazoY0) * tan;
  const brazo = {
    linea: [
      [bx(brazoY0), brazoY0],
      [bx((brazoY0 + y.codo) / 2), (brazoY0 + y.codo) / 2],
      [bx(y.codo), y.codo],
      [bx((y.codo + y.muneca) / 2) + (grados > 9 ? H * 0.004 : 0), (y.codo + y.muneca) / 2],
      [bx(y.muneca) + (grados > 9 ? H * 0.006 : 0), y.muneca],
    ],
    anchos: [w.brazoSup * 1.08, w.brazoSup, w.codo, (w.codo + w.muneca) / 2 * 1.04, w.muneca],
    mano: { x: bx(y.muneca) + (grados > 9 ? H * 0.006 : 0), y0: y.muneca, y1: y.puntaMano },
  };

  // Saludo: `saludo` 'derecha' levanta la mano derecha de la persona, que de frente queda a la izquierda de quien mira.
  const saludoLado = opc.saludo === 'derecha' ? -1 : opc.saludo === 'izquierda' ? 1 : 0;
  const S0 = brazo.linea[0];
  const codoS = [S0[0] + H * 0.092, S0[1] + H * 0.131];
  const munS = [codoS[0] + H * 0.028, codoS[1] - H * 0.104];
  brazo.u = brazo.linea.map((p) => p[1]);
  const brazoSal = {
    linea: [S0, [(S0[0] + codoS[0]) / 2, (S0[1] + codoS[1]) / 2], codoS, [(codoS[0] + munS[0]) / 2, (codoS[1] + munS[1]) / 2], munS],
    anchos: brazo.anchos, u: brazo.u,
  };

  // Pierna derecha.
  const sep = (typeof opc.piernas === 'number' ? opc.piernas : 1) * 0.0;
  const piernaX = (yy) => {
    const t = (yy - y.cadera) / (y.tobillo - y.cadera);
    return w.cadera * 0.5 + (H * 0.006) * t + sep;
  };
  const pierna = {
    linea: [
      [piernaX(y.cadera), y.cadera],
      [piernaX(lerp(y.cadera, y.rodilla, 0.5)), lerp(y.cadera, y.rodilla, 0.5)],
      [piernaX(y.rodilla), y.rodilla],
      [piernaX(lerp(y.rodilla, y.tobillo, 0.35)), lerp(y.rodilla, y.tobillo, 0.35)],
      [piernaX(y.tobillo), y.tobillo],
    ],
    anchos: [w.muslo * 1.05, w.muslo * 0.9, w.rodilla, w.pantorrilla, w.tobillo],
    pie: { x: piernaX(y.tobillo) + H * 0.004, y0: y.tobillo, y1: suelo, ancho: H * 0.021 + w.tobillo * 0.7, largo: H * 0.045 },
  };

  return { sexo, edad, corpulencia: corp, cm, H, cx, top, suelo, y, w, grados, tan, brazo, brazoSal, saludoLado, pierna, cabezas, u, hombroX };
}

/** Mitad derecha del contorno del torso (de la base del cuello a la entrepierna), desplazada `d` hacia afuera. */
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
 * Líneas centrales del brazo y de la pierna en el lado `s` (+1 a la derecha de quien mira, -1 a su izquierda).
 * Si ese lado es el del saludo, el brazo sale levantado.
 */
function brazoLado(M, s) {
  const b = s === M.saludoLado ? M.brazoSal : M.brazo;
  return { linea: lado(b.linea, s, M.cx), anchos: b.anchos, u: b.u };
}

/**
 * Punto del brazo (x, y absolutos y semiancho) según el parámetro `uu`, que para el brazo caído es la altura y
 * y para el levantado recorre los mismos tramos. Más allá de la muñeca sigue la dirección del antebrazo.
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
function piernaLado(M, s) {
  return { linea: lado(M.pierna.linea, s, M.cx), anchos: M.pierna.anchos };
}

function trazoBrazo(M, s, holgura = 0) {
  const b = brazoLado(M, s);
  return suavizar(tubo(b.linea, b.anchos.map((a) => a + holgura)));
}
function trazoPierna(M, s, holgura = 0) {
  const p = piernaLado(M, s);
  return suavizar(tubo(p.linea, p.anchos.map((a) => a + holgura)));
}

/** Piezas (rutas) de la mano: una sola si cuelga; palma, cuatro dedos y pulgar si saluda. */
function trazoMano(M, s) {
  return s === M.saludoLado ? manoSaludo(M, s) : [manoCaida(M, s)];
}

/** Mano abierta, con la palma hacia quien mira, al final del antebrazo levantado. */
function manoSaludo(M, s) {
  const { linea: L } = brazoLado(M, s);
  const W = L[L.length - 1], P = L[L.length - 2];
  const l = Math.hypot(W[0] - P[0], W[1] - P[1]) || 1;
  const u = [(W[0] - P[0]) / l, (W[1] - P[1]) / l];
  const v = [-u[1], u[0]];
  const wm = M.w.muneca, largo = M.y.puntaMano - M.y.muneca, palma = largo * 0.5, pw = wm * 1.45;
  const ts = -s; // el pulgar queda del lado del cuerpo
  const loc = (a, c) => [W[0] + u[0] * a + v[0] * c, W[1] + u[1] * a + v[1] * c];
  const dir = (ang) => [u[0] * Math.cos(ang) + v[0] * Math.sin(ang) * ts, u[1] * Math.cos(ang) + v[1] * Math.sin(ang) * ts];
  const partes = [suavizar([loc(-3, -wm), loc(palma * 0.45, -pw), loc(palma, -pw * 0.92), loc(palma, pw * 0.92), loc(palma * 0.45, pw), loc(-3, wm)], true)];
  const dedos = [[0.72, 0.9, 0.12], [0.25, 1.0, 0.03], [-0.22, 0.93, -0.06], [-0.68, 0.74, -0.16]];
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

/** Antebrazo levantado desde el parámetro `uDesde` hasta la muñeca (se dibuja sobre mantos que lo taparían). */
function trazoAntebrazo(M, s, uDesde) {
  const n = 4, linea = [], anchos = [];
  const uFin = M.brazo.u[M.brazo.u.length - 1];
  for (let i = 0; i <= n; i++) {
    const q = brazoEnU(M, s, lerp(uDesde, uFin, i / n));
    linea.push([q.x, q.y]); anchos.push(q.w);
  }
  return suavizar(tubo(linea, anchos, 'plana'));
}

function manoCaida(M, s) {
  const { x, y0, y1 } = M.brazo.mano;
  const wm = M.w.muneca;
  const xx = M.cx + s * x;
  const lx = s * (y1 - y0) * M.tan * 0.5;
  const pts = [
    [xx - wm * 1.0, y0 - 2],
    [xx - wm * 1.25 + lx * 0.3, y0 + (y1 - y0) * 0.35],
    [xx - wm * 1.1 + lx * 0.7, y0 + (y1 - y0) * 0.75],
    [xx + lx, y1],
    [xx + wm * 1.1 + lx * 0.7, y0 + (y1 - y0) * 0.75],
    [xx + wm * 1.25 + lx * 0.3, y0 + (y1 - y0) * 0.35],
    [xx + wm * 1.0, y0 - 2],
  ];
  return suavizar(pts, true);
}

function trazoPie(M, s) {
  const { x, y0, y1, ancho, largo } = M.pierna.pie;
  const xx = M.cx + s * x;
  const pts = [
    [xx - ancho * 0.78, y0 - 4],
    [xx - ancho * 0.9, y0 + (y1 - y0) * 0.55],
    [xx - ancho * 1.25 + s * ancho * 0.1, y1 - largo * 0.15],
    [xx + s * ancho * 0.3, y1 + 0.5],
    [xx + ancho * 1.25 + s * ancho * 0.1, y1 - largo * 0.15],
    [xx + ancho * 0.9, y0 + (y1 - y0) * 0.55],
    [xx + ancho * 0.78, y0 - 4],
  ];
  return suavizar(pts, true);
}

function trazoCuello(M) {
  const { cx, y, w, H } = M;
  const top = y.menton - H * 0.025;
  const pts = [
    [cx - w.cuello, top], [cx + w.cuello, top, true],
    [cx + w.cuello * 1.05, lerp(top, y.hombro, 0.5)],
    [cx + w.cuello * 1.35, y.hombro - H * 0.012],
    [cx, y.hombro + H * 0.012, true],
    [cx - w.cuello * 1.35, y.hombro - H * 0.012],
    [cx - w.cuello * 1.05, lerp(top, y.hombro, 0.5)],
  ];
  return suavizar(pts, true);
}

Object.assign(__e, { LIENZO, CORPULENCIAS, POSTURAS, medidas, perfilTorso, trazoTorso, brazoLado, brazoEnU, piernaLado, trazoBrazo, trazoPierna, trazoMano, trazoAntebrazo, trazoPie, trazoCuello });
return __e;
})();

__m['prendas'] = (() => {
const __e = {};
// Plantillas de prendas. Cada una recibe las medidas del cuerpo (M), sus parámetros (p), los colores ya
// resueltos (k: principal, secundario, ribete, detalle) y un contexto (ctx.patron) y devuelve:
//   { atras: [...], partes: [...], detalles: [...] }
// `atras` va detrás del cuerpo, `partes` son las piezas que se funden en una sola silueta con contorno y
// `detalles` (trazos, botones, ribetes) se dibujan encima. Las medidas son fracciones de la altura (H).

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

/** Pliegues verticales entre dos alturas, siguiendo el ancho del perfil. */
function pliegues(M, perfil, yA, yB, n, color, amp = 0.012) {
  const out = [];
  for (let i = 1; i <= n; i++) {
    const f = (i / (n + 1)) * 2 - 1;
    const xa = f * anchoEn(perfil, yA) * 0.92, xb = f * anchoEn(perfil, yB) * 0.96;
    const mx = (xa + xb) / 2 + (i % 2 ? 1 : -1) * M.H * amp;
    out.push(linea(`M${M.cx + xa} ${yA}Q${M.cx + mx} ${(yA + yB) / 2} ${M.cx + xb} ${yB - (i % 2) * M.H * 0.006}`, sombraDe(color), 1.3, 0.38));
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
    case 'flecos': return [base(), [0, yH + H * 0.004, true]];
    default: return [base(), [0, yH + H * 0.004, true]];
  }
}

/** Mitad derecha del contorno de una prenda de torso, del escote al bajo. Devuelve también el perfil sin el bajo. */
function perfilVestido(M, o) {
  const { w, y, H } = M;
  const { d = 0, cin = 1, vuelo = 0, yHem, sinMangas = false, escote = 'redondo', prof = 1, bajo = 'recto' } = o;
  const T = perfilTorso(M, 0);
  const E = escotePts(M, escote, prof);
  const pts = E.slice();
  const wd = (i, peso) => d * lerp(1, cin, peso);
  if (sinMangas) {
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
      pts.push([w.cadera + d + vuelo * H * t ** 1.15, lerp(y.cadera, yHem, t)]);
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
  const perfil = corte.slice(0, -1).concat([[fin[0], fin[1]]]);
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
    case 'larga-mano': return y.muneca + H * 0.035;
    case 'kimono': return y.muneca + H * 0.045;
    default: return y.muneca - H * 0.006;
  }
}

function mangaForma(M, s, tipo, d, vuelo = 0) {
  const { H, y } = M;
  const yA = y.hombro + H * 0.012, yB = finManga(M, tipo);
  const n = 6, linea = [], anchos = [];
  const ini = brazoEnY(M, s, yA);
  for (let i = 0; i < n; i++) {
    const t = i / (n - 1);
    const q = brazoEnY(M, s, lerp(yA, yB, t));
    let f = 1, desp = 0;
    if (tipo === 'campana') f = 1 + (1.2 + vuelo * 10) * t ** 1.6;
    else if (tipo === 'ancha') f = 1 + 0.7 * t;
    else if (tipo === 'globo') f = 1.5 - 0.5 * Math.min(1, t * 2.2) + (t > 0.8 ? 0.1 : 0);
    else if (tipo === 'kimono') { f = 1.7 + 1.0 * t; desp = H * 0.028 * t; }
    else if (tipo === 'abullonada') f = 1.55 - 0.55 * Math.min(1, t * 1.6);
    // Con el brazo levantado la manga ancha se recoge hacia el codo: menos vuelo.
    if (s === M.saludoLado) f = 1 + (f - 1) * 0.55;
    linea.push([q.x + (s === M.saludoLado ? 0 : s * desp), q.y]);
    anchos.push((q.w + d * (i === 0 ? 1.15 : 1)) * f);
  }
  const plana = tipo !== 'campana' && tipo !== 'kimono' && tipo !== 'ancha' ? 'plana' : 'plana';
  return { pts: tubo(linea, anchos, plana), linea, anchos, fin: { x: linea[n - 1][0], y: linea[n - 1][1], w: anchos[n - 1] }, ini };
}

function mangas(M, tipo, d, k, F, vuelo, puño) {
  const partes = [], detalles = [];
  if (!tipo || tipo === 'sin') return { partes, detalles };
  for (const s of [1, -1]) {
    const m = mangaForma(M, s, tipo, d, vuelo);
    partes.push(parte(suavizar(m.pts), F, k));
    if (puño) {
      const { x, y, w } = m.fin;
      detalles.push(linea(`M${x - w * 0.97} ${y - 1.5}L${x + w * 0.97} ${y - 1.5}`, k.ribete, Math.max(3, M.H * 0.011), 1, { cap: 'butt' }));
    }
  }
  return { partes, detalles };
}

// ---------- Plantillas ----------

function tunica(M, p, k, ctx) {
  const { H, y, cx } = M;
  const d = (p.holgura ?? 0.012) * H;
  const yHem = yLargo(M, p.largo ?? 'rodilla');
  const F = relleno(p, k, ctx);
  const sinM = p.manga === 'sin';
  const per = perfilVestido(M, { d, cin: p.cintura ?? 1, vuelo: p.vuelo ?? 0.03, yHem, sinMangas: sinM, escote: p.escote ?? 'redondo', prof: p.prof ?? 1, bajo: p.bajo ?? 'recto' });
  const partes = [parte(suavizar(espejo(per.completo, cx)), F, k)];
  const m = mangas(M, p.manga ?? 'larga', d * 0.9, k, F, p.vuelo ?? 0.03, p.puños !== false && p.ribete !== false && !!k.ribete && p.puños);
  partes.push(...m.partes);
  const detalles = [...m.detalles];
  const prof = per.perfil;
  const lado = (pts) => pts.map(([x, yy]) => [cx + x, yy]);
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
  return { atras: [], partes, detalles };
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

function pantalon(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const d = (p.holgura ?? 0.004) * H;
  const yHem = yLargo(M, p.largo ?? 'tobillo');
  const F = relleno(p, k, ctx);
  const factor = { ajustado: 1, recto: 1.28, amplio: 1.7, bombacho: 1.55, campana: 1.3 }[p.ancho ?? 'recto'] ?? 1.28;
  const partes = [];
  const T = perfilTorso(M, 0);
  const alto = p.cintura === 'baja' ? y.cintura + H * 0.045 : y.cintura - H * 0.005;
  const half = [[0, alto, true], [w.cintura + d * 1.2, alto, true], [lerp(w.cintura, w.cadera, 0.7) + d, lerp(alto, y.cadera, 0.6)], [w.cadera + d * 1.3, y.cadera], [w.cadera * 0.98 + d, lerp(y.cadera, y.entrepierna, 0.7)], [0, y.entrepierna + H * 0.012, true]];
  partes.push(parte(suavizar(espejo(half, cx)), F, k));
  for (const s of [1, -1]) {
    const Lg = M.pierna;
    const pts = [], ws = [];
    const yy0 = y.cadera, n = 6;
    for (let i = 0; i < n; i++) {
      const t = i / (n - 1);
      const yy = lerp(yy0, yHem, t);
      // Interpolar la pierna en yy.
      let q = { x: Lg.linea[0][0], w: Lg.anchos[0] };
      for (let j = 0; j < Lg.linea.length - 1; j++) {
        if (yy <= Lg.linea[j + 1][1] || j === Lg.linea.length - 2) {
          const tt = limitar((yy - Lg.linea[j][1]) / ((Lg.linea[j + 1][1] - Lg.linea[j][1]) || 1), 0, 1.3);
          q = { x: lerp(Lg.linea[j][0], Lg.linea[j + 1][0], tt), w: lerp(Lg.anchos[j], Lg.anchos[j + 1], tt) };
          break;
        }
      }
      let f = 1 + (factor - 1) * (p.ancho === 'bombacho' ? Math.sin(Math.min(1, t * 1.4) * Math.PI * 0.5) * (t > 0.78 ? 1 - (t - 0.78) * 3.4 : 1) : p.ancho === 'campana' ? t ** 2 : Math.min(1, t * 2));
      if (p.ancho === 'ajustado') f = 1;
      pts.push([cx + s * q.x, yy]);
      ws.push(q.w * f + d);
    }
    partes.push(parte(suavizar(tubo(pts, ws, 'plana')), F, k));
  }
  const detalles = [linea(`M${cx - w.cintura - d} ${alto + 3}L${cx + w.cintura + d} ${alto + 3}`, k.ribete ? k.ribete : sombraDe(k.principal), Math.max(3, H * 0.008), 0.9, { cap: 'butt' }),
    linea(`M${cx} ${alto + 5}L${cx} ${y.entrepierna + H * 0.01}`, sombraDe(k.principal), 1.2, 0.5)];
  if (p.cordon) detalles.push(linea(`M${cx - 4} ${alto + 6}q4 10 -2 18M${cx + 4} ${alto + 6}q-4 10 2 18`, k.detalle, 1.6, 0.9));
  if (p.puños && k.ribete) for (const s of [1, -1]) detalles.push(linea(`M${cx + s * (M.pierna.linea[4][0] - M.pierna.anchos[4] * 1.5)} ${yHem - 2}L${cx + s * (M.pierna.linea[4][0] + M.pierna.anchos[4] * 1.5)} ${yHem - 2}`, k.ribete, 4, 1, { cap: 'butt' }));
  return { atras: [], partes, detalles };
}

function falda(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const yHem = yLargo(M, p.largo ?? 'pantorrilla');
  const F = relleno(p, k, ctx);
  const alto = p.cintura === 'alta' ? y.pecho + H * 0.015 : p.cintura === 'baja' ? y.cintura + H * 0.04 : y.cintura - H * 0.01;
  const d = (p.holgura ?? 0.006) * H;
  const vuelo = (p.vuelo ?? 0.06) * H;
  const wA = alto < y.cintura ? lerp(w.cintura, w.pecho, limitar((y.cintura - alto) / (y.cintura - y.pecho), 0, 1)) : w.cintura;
  const pts = [[0, alto, true], [wA + d, alto, true], [w.cintura + d, y.cintura], [lerp(w.cintura, w.cadera, 0.7) + d, lerp(y.cintura, y.cadera, 0.6)], [w.cadera + d * 1.3, y.cadera]];
  for (const t of [0.3, 0.6, 0.85, 1]) pts.push([w.cadera + d * 1.3 + vuelo * t ** 1.2, lerp(y.cadera, yHem, t)]);
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
  return { atras: [], partes, detalles };
}

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
  atras.push(...[]);
  const detalles = [];
  const prof = half.slice(1, -1).map(([x, yy]) => [x, yy]);
  const partes = [];
  const front = [];
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
  const yTop = y.hombro + H * 0.01;
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
  return { atras: [], partes, detalles };
}

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

function faja(M, p, k, ctx) {
  const { H, y, cx, w } = M;
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

function bandolera(M, p, k) {
  const { H, y, cx, w } = M;
  const sg = p.lado === 'der' ? 1 : -1;
  const d = `M${cx + sg * w.cuello * 1.5} ${y.hombro}Q${cx} ${y.pecho + H * 0.05} ${cx - sg * (w.cadera + H * 0.008)} ${y.cadera + H * 0.01}`;
  const det = [linea(d, k.principal, Math.max(4, (p.ancho ?? 0.017) * H), 1, { cap: 'butt', contorno: oscurecer(k.principal, 0.5) })];
  if (p.bolsa) det.push({ d: poligono([[cx - sg * w.cadera - 12, y.cadera], [cx - sg * w.cadera + 12, y.cadera], [cx - sg * w.cadera + 14, y.cadera + H * 0.06], [cx - sg * w.cadera - 14, y.cadera + H * 0.06]]), fill: k.secundario, stroke: oscurecer(k.secundario, 0.45), sw: 1.2 });
  return { atras: [], partes: [], detalles: det };
}

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

function calzado(M, p, k, ctx) {
  const { H, y, cx, w } = M;
  const tipo = p.tipo ?? 'zapatos';
  const partes = [], detalles = [];
  const pie = M.pierna.pie;
  for (const s of [1, -1]) {
    const x = cx + s * pie.x;
    const aw = pie.ancho, yT = y.tobillo - H * 0.01;
    const suelaY = M.suelo;
    const zap = (altoY) => [
      [x - aw * 0.92, altoY], [x - aw * 1.0, lerp(altoY, suelaY, 0.6)], [x - aw * 1.36 + s * aw * 0.1, suelaY - pie.largo * 0.2],
      [x + s * aw * 0.35, suelaY + 1], [x + aw * 1.36 + s * aw * 0.1, suelaY - pie.largo * 0.2], [x + aw * 1.0, lerp(altoY, suelaY, 0.6)], [x + aw * 0.92, altoY],
    ];
    switch (tipo) {
      case 'descalzo': break;
      case 'sandalias': case 'sandalia-romana': {
        partes.push(parte(suavizar(zap(suelaY - H * 0.012), true), k.principal, k, { plano: true, volumen: false }));
        for (let i = 0; i < 3; i++) detalles.push(linea(`M${x - aw * 0.95} ${suelaY - H * (0.014 + i * 0.011)}L${x + aw * 0.95} ${suelaY - H * (0.014 + i * 0.011) - 2}`, k.secundario ?? k.principal, 2, 1));
        if (tipo === 'sandalia-romana') for (let i = 0; i < 6; i++) detalles.push(linea(`M${x - M.pierna.anchos[4] * 1.1} ${yT - i * H * 0.018}L${x + M.pierna.anchos[4] * 1.1} ${yT - i * H * 0.018 - 6}`, k.secundario ?? k.principal, 1.8, 0.95));
        break;
      }
      case 'mocasines': case 'zapatos': case 'zuecos': case 'zapatillas': {
        const pts = zap(tipo === 'zapatillas' ? y.tobillo + H * 0.01 : yT - H * 0.004);
        partes.push(parte(suavizar(pts, true), k.principal, k));
        detalles.push(linea(`M${x - aw * 1.25} ${suelaY - 2}L${x + aw * 1.25} ${suelaY - 2}`, k.ribete ?? oscurecer(k.principal, 0.5), 2.4, 1, { cap: 'butt' }));
        if (tipo === 'zapatos') detalles.push(linea(`M${x - aw * 0.5} ${suelaY - H * 0.026}Q${x} ${suelaY - H * 0.034} ${x + aw * 0.5} ${suelaY - H * 0.026}`, sombraDe(k.principal), 1.3, 0.7));
        if (p.hebilla) detalles.push({ d: poligono([[x - 4, suelaY - H * 0.03], [x + 4, suelaY - H * 0.03], [x + 4, suelaY - H * 0.018], [x - 4, suelaY - H * 0.018]]), fill: k.detalle, stroke: oscurecer(k.detalle, 0.4), sw: 0.9 });
        break;
      }
      case 'botas': case 'botas-altas': case 'polainas': {
        const yTop = tipo === 'botas' ? lerp(y.rodilla, y.tobillo, 0.45) : tipo === 'polainas' ? lerp(y.rodilla, y.tobillo, 0.3) : y.rodilla + H * 0.01;
        const L = M.pierna;
        const linePts = [], ws = [];
        for (let i = 0; i <= 4; i++) {
          const yy = lerp(yTop, y.tobillo, i / 4);
          let q = { x: L.linea[2][0], w: L.anchos[2] };
          for (let j = 0; j < L.linea.length - 1; j++) {
            if (yy <= L.linea[j + 1][1]) { const tt = (yy - L.linea[j][1]) / ((L.linea[j + 1][1] - L.linea[j][1]) || 1); q = { x: lerp(L.linea[j][0], L.linea[j + 1][0], tt), w: lerp(L.anchos[j], L.anchos[j + 1], tt) }; break; }
          }
          linePts.push([cx + s * q.x, yy]); ws.push(q.w * 1.12 + H * 0.003);
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
  } else if (tipo === 'cinta-frente') {
    det.push(linea(`M${cx - M.hombroX * 0.5} ${y.menton - H * 0.04}L${cx + M.hombroX * 0.5} ${y.menton - H * 0.04}`, k.principal, 3, 1));
  }
  return { atras: [], partes: [], detalles: det };
}

const PLANTILLAS = { tunica, pantalon, falda, taparrabo, capa, poncho, drapeado, chaqueta, delantal, faja, bandolera, chal, cuello, calzado, accesorio };
const PLANTILLAS_ID = Object.keys(PLANTILLAS);

/** Orden de dibujo por ranura (menor = más al fondo). */
const ORDEN_RANURAS = {
  capa_atras: 10, calzado: 20, inferior: 30, torso: 40, sobre: 50, faja: 60, capa: 70, cuello: 80, accesorio: 90,
};

Object.assign(__e, { yLargo, anchoEn, finManga, PLANTILLAS, PLANTILLAS_ID, ORDEN_RANURAS });
return __e;
})();

__m['svg'] = (() => {
const __e = {};
// Dibuja una persona descrita por `generarPersona` como SVG.

const { LIENZO, medidas, trazoTorso, trazoBrazo, trazoPierna, trazoMano, trazoPie, trazoCuello, trazoAntebrazo } = __m['cuerpo'];
const { geometriaCabeza, trazoCabeza, trazoOrejas, rasgosRostro, PEINADOS, BARBAS, TOCADOS } = __m['cabeza'];
const { PLANTILLAS, ORDEN_RANURAS, finManga } = __m['prendas'];
const { PATRONES } = __m['patrones'];
const { oscurecer, aclarar, escapar, num } = __m['util'];

function contexto() {
  const defs = new Map();
  return {
    defs,
    patron(tipo, a, b, escala = 1) {
      const f = PATRONES[tipo] ?? PATRONES.rayas;
      const e = Math.round(escala * 100) / 100;
      const id = `p-${tipo}-${a.slice(1)}-${b.slice(1)}-${String(e).replace('.', '_')}`;
      if (!defs.has(id)) {
        const [w, h, cuerpo] = f(a, b, e);
        defs.set(id, `<pattern id="${id}" width="${num(w)}" height="${num(h)}" patternUnits="userSpaceOnUse">${cuerpo}</pattern>`);
      }
      return `url(#${id})`;
    },
  };
}

const attr = (o) => Object.entries(o).filter(([, v]) => v !== undefined && v !== null && v !== false).map(([k, v]) => ` ${k}="${escapar(v)}"`).join('');

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

/** Dibuja un detalle; si trae `contorno` pone antes un trazo más grueso oscuro. */
function dibujarDetalle(s) {
  let out = '';
  if (s.contorno && s.stroke) out += `<path${attr({ d: s.d, fill: 'none', stroke: s.contorno, 'stroke-width': (s.sw ?? 1) + 2.2, 'stroke-linecap': s.cap === 'butt' ? 'butt' : 'round', 'stroke-dasharray': s.dash })}/>`;
  out += `<path${attr(trazoDe(s))}/>`;
  return out;
}

/** Grupo de piezas unidas: primero el contorno de todas, luego el relleno, luego el sombreado. */
function dibujarGrupo(piezas, nivel, esquema) {
  if (!piezas.length) return '';
  let out = '';
  for (const s of piezas) out += `<path${attr({ d: s.d, fill: s.fill, stroke: s.contorno, 'stroke-width': s.swContorno ?? 3, 'stroke-linejoin': 'round', opacity: s.op !== undefined && s.op !== 1 ? s.op : undefined })}/>`;
  for (const s of piezas) out += `<path${attr({ d: s.d, fill: s.fill, opacity: s.op !== undefined && s.op !== 1 ? s.op : undefined })}/>`;
  if (nivel !== 'minimo') {
    for (const s of piezas) {
      if (s.volumen === false || s.plano) continue;
      out += `<path${attr({ d: s.d, fill: 'url(#sombra-h)', opacity: s.sombra ?? (nivel === 'alto' ? 1 : 0.75) })}/>`;
      if (nivel === 'alto') out += `<path${attr({ d: s.d, fill: 'url(#sombra-v)', opacity: 0.55 })}/>`;
    }
  }
  return out;
}

function pielDe(piel) {
  return { fill: piel, contorno: oscurecer(piel, 0.42), swContorno: 2.8 };
}

/** Devuelve el SVG (texto) de la persona descrita. */
function renderizar(persona, opciones = {}) {
  const nivel = opciones.detalle ?? persona.detalle ?? 'medio';
  const fondo = opciones.fondo ?? persona.fondo ?? 'ninguno';
  const M = medidas(persona.cuerpo);
  const G = geometriaCabeza(M, persona.rostro);
  const ctx = contexto();
  const piel = persona.piel;
  const { cx } = M;

  const capas = []; // [orden, svg]
  // --- Sombra en el suelo ---
  let suelo = '';
  if (nivel !== 'minimo') {
    suelo = `<ellipse cx="${cx}" cy="${LIENZO.suelo + 4}" rx="${num(M.H * 0.17)}" ry="${num(M.H * 0.014)}" fill="#000" opacity="0.16"/>`;
  }

  // --- Prendas ---
  const prendas = persona.prendas.map((pr) => {
    const f = PLANTILLAS[pr.plantilla];
    if (!f) throw new Error(`Plantilla desconocida: ${pr.plantilla}`);
    return { ...pr, r: f(M, pr.params ?? {}, pr.colores, ctx) };
  });

  // --- Cabello, barba y tocado ---
  const colorPelo = persona.cabello?.color ?? '#3b2a20';
  const peinado = (PEINADOS[persona.cabello?.estilo] ?? PEINADOS.calvo)(G, colorPelo);
  const barbaFn = BARBAS[persona.barba?.estilo] ?? BARBAS.ninguna;
  const barba = barbaFn(G, persona.barba?.color ?? colorPelo);
  const toc = persona.tocado ? (TOCADOS[persona.tocado.id] ?? TOCADOS.ninguno)(G, persona.tocado.colores) : { atras: [], frente: [] };

  const pelo = (lista) => lista.map((s) => ({ ...s, contorno: oscurecer(typeof s.fill === 'string' && s.fill.startsWith('#') ? s.fill : colorPelo, 0.5), swContorno: 2.4 }));

  let cuerpo = '';
  // 1. Detrás: capas, cabello largo, tocado trasero
  const atrasPrendas = prendas.filter((p) => p.r.atras?.length);
  for (const p of atrasPrendas) {
    const piezas = p.r.atras.filter((s) => !s.atras);
    cuerpo += dibujarGrupo(piezas, nivel);
    for (const s of p.r.atras.filter((q) => q.atras)) cuerpo += dibujarDetalle(s);
  }
  const dibujarLista = (lista, op) => {
    let o = '';
    for (const s of lista) {
      if (s.stroke && s.fill === 'none') o += dibujarDetalle(s);
      else o += dibujarGrupo([{ ...s, contorno: s.stroke ?? oscurecer(s.fill, 0.5), swContorno: 2.4, sombra: op }], nivel);
    }
    return o;
  };
  cuerpo += dibujarLista(toc.atras);
  cuerpo += dibujarLista(pelo(peinado.atras), 0.5);

  // 2. Piel
  const sp = pielDe(piel);
  const partesPiel = [
    ...[1, -1].map((s) => ({ d: trazoPierna(M, s), ...sp })),
    ...[1, -1].map((s) => ({ d: trazoPie(M, s), ...sp })),
    { d: trazoTorso(M), ...sp },
    ...[1, -1].map((s) => ({ d: trazoBrazo(M, s), ...sp })),
    ...[1, -1].filter((s) => s !== M.saludoLado).flatMap((s) => trazoMano(M, s).map((d) => ({ d, ...sp }))),
    { d: trazoCuello(M), ...sp },
  ];
  cuerpo += dibujarGrupo(partesPiel, nivel);
  if (nivel === 'alto') {
    // Sombra del mentón sobre el cuello.
    cuerpo += `<path d="${suavizar2(G)}" fill="#000" opacity="0.18"/>`;
  }

  // 3. Ropa por ranura
  const ordenadas = prendas.slice().sort((a, b) => (ORDEN_RANURAS[a.ranura] ?? 50) - (ORDEN_RANURAS[b.ranura] ?? 50));
  for (const p of ordenadas) {
    cuerpo += `<g data-prenda="${escapar(p.id)}">`;
    cuerpo += dibujarGrupo(p.r.partes, nivel);
    for (const s of p.r.detalles) cuerpo += dibujarDetalle(s);
    cuerpo += '</g>';
  }

  // 3b. Mano en saludo: encima de la ropa para que ni mangas ni mantos la tapen.
  if (M.saludoLado) {
    const piezas = [];
    const tieneManto = prendas.some((p) => p.ranura === 'capa');
    if (tieneManto) {
      const finMax = Math.max(M.y.codo, ...prendas.filter((p) => p.params?.manga && p.params.manga !== 'sin').map((p) => finManga(M, p.params.manga)));
      if (finMax < M.y.muneca - 2) piezas.push({ d: trazoAntebrazo(M, M.saludoLado, finMax), ...sp });
    }
    for (const d of trazoMano(M, M.saludoLado)) piezas.push({ d, ...sp });
    cuerpo += `<g data-saludo="${M.saludoLado === -1 ? 'derecha' : 'izquierda'}">${dibujarGrupo(piezas, nivel)}</g>`;
  }

  // 4. Cabeza
  const cabezaPieza = { d: trazoCabeza(G), ...sp };
  const orejas = trazoOrejas(G).map((d) => ({ d, ...sp }));
  cuerpo += dibujarGrupo([...orejas, cabezaPieza], nivel);
  if (nivel !== 'minimo') {
    cuerpo += `<path d="${G.E([[0, 0.5], [0.9, 0.6], [0.92, 0.64], [0.74, 0.82], [0.44, 0.96], [0, 1.0]])}" fill="#000" opacity="0.07"/>`;
  }
  if (persona.rostro?.rasgos) for (const s of rasgosRostro(G, piel)) cuerpo += dibujarDetalle(s);
  cuerpo += dibujarLista(pelo(barba.flat()), 0.5);
  cuerpo += dibujarLista(pelo(peinado.frente), 0.5);
  cuerpo += dibujarLista(toc.frente);

  const alto = LIENZO.alto;
  const defs = [
    '<linearGradient id="sombra-h" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#000" stop-opacity="0.34"/><stop offset="0.2" stop-color="#000" stop-opacity="0"/><stop offset="0.58" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.4"/></linearGradient>',
    '<linearGradient id="sombra-v" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity="0.12"/><stop offset="0.5" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.2"/></linearGradient>',
    ...ctx.defs.values(),
  ].join('');
  const bg = fondo && fondo !== 'ninguno' ? `<rect width="${LIENZO.ancho}" height="${alto}" fill="${escapar(fondo)}"/>` : '';
  const titulo = persona.titulo ? `<title>${escapar(persona.titulo)}</title>` : '';
  const desc = persona.descripcion ? `<desc>${escapar(persona.descripcion)}</desc>` : '';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${LIENZO.ancho} ${alto}" role="img">${titulo}${desc}<defs>${defs}</defs>${bg}${suelo}${cuerpo}</svg>`;
}

function suavizar2(G) {
  // Sombra bajo el mentón: elipse achatada sobre el cuello.
  return G.C(0, 1.05, 0.62, 0.1);
}

Object.assign(__e, { renderizar });
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

const L = (s) => s.split(/\s+/).filter(Boolean);
const E = (id, nombre, d) => ({
  id, nombre, grupo: d.grupo, regiones: L(d.regiones), prefijos: d.prefijos ? L(d.prefijos) : null,
  pieles: L(d.pieles), cabellos: d.cabellos, peinados: L(d.peinados ?? ''), barba: d.barba ?? 0.3,
  altura: d.altura, corpulencia: d.corpulencia ?? [['delgada', 1], ['media', 4], ['robusta', 1.5]], nota: d.nota ?? '',
});

const OSC = [['negro', 7], ['negro-azulado', 2], ['castano-oscuro', 1]];
const MORENO = [['negro', 5], ['negro-azulado', 1.5], ['castano-oscuro', 3], ['castano', 1.5]];
const CLARO = [['castano', 3], ['castano-claro', 3], ['rubio-oscuro', 2], ['rubio', 2], ['castano-oscuro', 2], ['pelirrojo', 0.8], ['negro', 1]];
const MED = [['negro', 4], ['castano-oscuro', 4], ['castano', 2], ['castano-claro', 0.5]];
const MESTIZO = [['negro', 4], ['castano-oscuro', 4], ['castano', 2], ['castano-claro', 1]];

const ETNIAS = [
  // ----- Chile y el cono sur -----
  E('mapuche', 'Mapuche', { grupo: 'Pueblos originarios de Chile y Argentina', regiones: 'mapuche', pieles: 'canela bronce cobre oliva', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.08, altura: [153, 165], corpulencia: [['delgada', 1], ['media', 3], ['robusta', 2]], nota: 'Incluye pehuenche, huilliche y lafkenche cuando no hay un traje propio en el catálogo.' }),
  E('huilliche', 'Huilliche', { grupo: 'Pueblos originarios de Chile y Argentina', regiones: 'mapuche chile-rural', pieles: 'canela bronce cobre oliva', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.08, altura: [153, 165] }),
  E('aymara', 'Aymara', { grupo: 'Pueblos andinos', regiones: 'andes', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.06, altura: [150, 162], corpulencia: [['media', 3], ['robusta', 3], ['delgada', 0.7]], nota: 'Pecho ancho por la altura; la estimación de estatura es de poblaciones del altiplano.' }),
  E('quechua', 'Quechua', { grupo: 'Pueblos andinos', regiones: 'andes', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.06, altura: [151, 163], corpulencia: [['media', 3], ['robusta', 2.5], ['delgada', 0.8]] }),
  E('inca', 'Inca (histórico)', { grupo: 'Pueblos andinos', regiones: 'andes', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'flequillo largo-liso trenzas-dos corto', barba: 0.04, altura: [150, 162] }),
  E('atacameno', 'Atacameño (lickanantay)', { grupo: 'Pueblos andinos', regiones: 'andes', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso corto', barba: 0.06, altura: [152, 164] }),
  E('selknam', 'Selk\'nam (ona)', { grupo: 'Pueblos de la Patagonia y Tierra del Fuego', regiones: 'patagonia-fueguina', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'largo-liso corto', barba: 0.05, altura: [165, 178], corpulencia: [['media', 4], ['robusta', 2]], nota: 'Se cuentan entre los pueblos más altos de América en los registros del siglo XIX.' }),
  E('kawesqar', 'Kawésqar y yagán', { grupo: 'Pueblos de la Patagonia y Tierra del Fuego', regiones: 'patagonia-fueguina', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'largo-liso corto', barba: 0.05, altura: [148, 160], nota: 'Pueblos canoeros; el catálogo los representa con trajes de piel genéricos de la región.' }),
  E('rapanui', 'Rapanui', { grupo: 'Polinesia', regiones: 'rapa-nui polinesia', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'corto largo-ondulado trenza-una mono-alto', barba: 0.3, altura: [160, 172], corpulencia: [['media', 3], ['robusta', 3]] }),
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
  E('arabe', 'Árabe', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente africa-norte', pieles: 'oliva-clara oliva canela bronce', cabellos: [['negro', 7], ['castano-oscuro', 2]], peinados: 'corto recogido', barba: 0.7, altura: [157, 169] }),
  E('persa', 'Persa', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente', pieles: 'oliva-clara oliva', cabellos: [['negro', 6], ['castano-oscuro', 3]], peinados: 'corto largo-liso', barba: 0.65, altura: [157, 170] }),
  E('turco-otomano', 'Turco y otomano', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto', barba: 0.7, altura: [158, 171] }),
  E('bereber', 'Bereber y magrebí', { grupo: 'Medio Oriente y África del norte', regiones: 'africa-norte', pieles: 'oliva-clara oliva canela bronce', cabellos: [['negro', 6], ['castano-oscuro', 3]], peinados: 'corto trenzas-dos recogido', barba: 0.65, altura: [157, 169] }),
  // ----- Asia -----
  E('indio-subcontinente', 'Indio del subcontinente', { grupo: 'Asia', regiones: 'asia-sur', pieles: 'oliva canela bronce cobre castana', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'trenza-una recogido corto', barba: 0.55, altura: [151, 164] }),
  E('chino-han', 'Chino han', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'china-', pieles: 'clara clara-calida oliva-clara', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'mono-alto recogido trenza-una bob', barba: 0.2, altura: [155, 167] }),
  E('japones', 'Japonés', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'japon-', pieles: 'porcelana clara clara-calida', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'mono-chonmage recogido mono-alto', barba: 0.2, altura: [153, 165] }),
  E('coreano', 'Coreano', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'corea-', pieles: 'porcelana clara clara-calida', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'recogido trenza-una mono-chonmage', barba: 0.2, altura: [155, 167] }),
  E('malayo-indonesio', 'Malayo e indonesio', { grupo: 'Asia', regiones: 'sudeste-asiatico', pieles: 'oliva canela bronce', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'recogido corto mono-alto', barba: 0.25, altura: [148, 160] }),
  // ----- África subsahariana -----
  E('africano-occidental', 'Africano occidental', { grupo: 'África subsahariana', regiones: 'africa-oeste', pieles: 'castana ebano-claro ebano ebano-profundo', cabellos: [['negro', 10]], peinados: 'rapado corto afro trenzas-dos rizos', barba: 0.4, altura: [160, 172] }),
  E('zulu', 'Zulú y bantú austral', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'zulu-', pieles: 'castana ebano-claro ebano', cabellos: [['negro', 10]], peinados: 'rapado corto', barba: 0.3, altura: [157, 169] }),
  E('maasai', 'Maasái', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'maasai', pieles: 'ebano-claro ebano ebano-profundo', cabellos: [['negro', 10]], peinados: 'rapado rastas', barba: 0.05, altura: [165, 178], corpulencia: [['delgada', 3], ['media', 3]], nota: 'Estatura alta y complexión delgada son típicas de los registros de pastores de África oriental.' }),
];

const ETNIAS_POR_ID = Object.fromEntries(ETNIAS.map((e) => [e.id, e]));

Object.assign(__e, { ETNIAS, ETNIAS_POR_ID });
return __e;
})();

__m['generador'] = (() => {
const __e = {};
// Generador de personas: elige un atuendo del catálogo (o inventa uno), le pone colores y devuelve la
// descripción que `renderizar` dibuja. Con la misma semilla y las mismas opciones sale siempre lo mismo.

const { crearAzar, variar, mezclar, oscurecer, aclarar, luminancia, hexAHsl, hslAHex, limitar, esHex } = __m['util'];
const { PIELES, CABELLOS, TINTES, TINTES_POR_ID } = __m['datos/colores'];
const { PRENDAS_POR_ID, PRENDAS } = __m['datos/prendas'];
const { ATUENDOS, ATUENDOS_POR_ID, REGIONES } = __m['datos/atuendos'];
const { MANDIBULAS_ID, PEINADOS, BARBAS, TOCADOS } = __m['cabeza'];
const { PATRONES_ID } = __m['patrones'];
const { CORPULENCIAS, POSTURAS } = __m['cuerpo'];
const { renderizar } = __m['svg'];
const { ETNIAS, ETNIAS_POR_ID } = __m['datos/etnias'];
const { medidas } = __m['cuerpo'];

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
  return { atuendos: ATUENDOS.length, prendas: PRENDAS.length, tintes: TINTES.length, pieles: PIELES.length, cabellos: CABELLOS.length,
    etnias: ETNIAS.length, peinados: Object.keys(PEINADOS).length, barbas: Object.keys(BARBAS).length, tocados: Object.keys(TOCADOS).length, patrones: PATRONES_ID.length };
}

// ---------- Paletas ----------

/** Un tinte disponible en el año dado (si hay uno en la lista), con una pequeña variación. */
function tinteDe(ids, azar, anio) {
  const ok = ids.filter((id) => {
    const t = TINTES_POR_ID[id];
    return t && (anio === undefined || (anio >= t.desde && anio <= t.hasta));
  });
  const id = azar.elegir(ok.length ? ok : ids);
  const t = TINTES_POR_ID[id];
  if (!t) throw new Error(`Tinte desconocido: ${id}`);
  return { id, nombre: t.nombre, hex: variar(t.hex, azar, { tono: 4, sat: 0.04, luz: 0.04 }) };
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
  const p = {}, nombres = {};
  for (const [letra, ids] of Object.entries(a.paleta)) {
    const t = tinteDe(ids, azar, anio);
    p[letra] = t.hex; nombres[letra] = t.nombre;
  }
  return { colores: p, nombres };
}

function coloresDePrenda(pr, pal) {
  const A = pal.A ?? '#c9b99a';
  const B = pal.B ?? mezclar(A, '#ffffff', 0.3);
  const C = pal.C ?? oscurecer(A, 0.28);
  const D = pal.D ?? '#caa24a';
  const letra = { A, B, C, D };
  const k = {};
  for (const [rol, l] of Object.entries(pr.col)) k[rol] = letra[l] ?? A;
  // Que el ribete y el detalle se distingan del color principal.
  if (Math.abs(luminancia(k.ribete) - luminancia(k.principal)) < 0.04 && k.ribete === k.principal) {
    k.ribete = luminancia(k.principal) > 0.35 ? oscurecer(k.principal, 0.35) : aclarar(k.principal, 0.35);
  }
  return k;
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
    return { e, peso: Number(peso) > 0 ? Number(peso) : 1 };
  });
  if (!lista.length) return null;
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

const ESTATURA_REF = { f: 161, m: 174 }; // los promedios con los que medidas() calcula la altura por edad

// ---------- Persona ----------

/**
 * Opciones (todas opcionales): semilla, sexo ('f'|'m'), edad, corpulencia, altura (cm), postura,
 * atuendo (id), region, anio, clase ('baja'|'media'|'alta'), piel, cabello, barba (true|false|id),
 * peinado, tocado, calzado, paleta ({A,B,C,D} en hex), variacion (0..1), detalle ('minimo'|'medio'|'alto'),
 * rasgos (true: ojos, cejas y boca suaves), fondo, prendas (lista propia de prendas ya armadas).
 */
function generarPersona(opc = {}) {
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
  const pal = opc.paleta ?? paletaDeAtuendo(atuendo, azar.hijo('paleta'), anioPaleta).colores;
  const nombresPaleta = opc.paleta ? null : paletaDeAtuendo(atuendo, azar.hijo('paleta'), anioPaleta).nombres;
  const piel = elegirPiel(azar.hijo('piel'), atuendo, opc, etnia);
  const colorPelo = elegirCabello(azar.hijo('cabello'), piel, edad, opc, etnia);

  // Prendas
  const az = azar.hijo('prendas');
  const variacion = opc.variacion ?? 0.5;
  let ids = atuendo.prendas.slice();
  if (opc.calzado) ids = ids.filter((id) => PRENDAS_POR_ID[id].ranura !== 'calzado').concat(opc.calzado);
  const prendas = opc.prendas ?? ids.map((id) => {
    const pr = PRENDAS_POR_ID[id];
    if (!pr) throw new Error(`Prenda desconocida en ${atuendo.id}: ${id}`);
    return { id, nombre: pr.nombre, ranura: pr.ranura, plantilla: pr.plantilla, params: variarParams(pr.params, az, variacion), colores: coloresDePrenda(pr, pal) };
  });
  if (!opc.prendas) distinguirCapas(prendas, pal);

  // Cabeza
  const ah = azar.hijo('cabeza');
  const peinadosEtnia = etnia ? atuendo.peinados.filter((p) => etnia[0].e.peinados.includes(p)) : [];
  const peinadoId = opc.peinado ?? ah.elegir(peinadosEtnia.length ? peinadosEtnia : atuendo.peinados);
  let estilo = PEINADOS[peinadoId] ? peinadoId : 'corto';
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
    altura: opc.altura, postura: opc.postura ?? 'firme',
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
    semilla, titulo: `${atuendo.nombre}`,
    descripcion: `${SEXOS[sexo][0].toUpperCase() + SEXOS[sexo].slice(1)} de ${edad} años; ${atuendo.nombre} (${REGIONES[atuendo.region]}, ${decada(atuendo.epoca[0])} a ${decada(atuendo.epoca[1])}).`,
    sexo, edad, atuendo: atuendo.id, region: atuendo.region, epoca: atuendo.epoca, clase: atuendo.clase,
    cuerpo, rostro, piel,
    cabello: { estilo, color: colorPelo }, barba: { estilo: barbaId, color: colorPelo }, tocado,
    prendas, paleta: pal, nombresPaleta,
    etnia: etnia ? etnia.map((x) => ({ id: x.e.id, nombre: x.e.nombre, peso: Math.round(x.peso * 100) / 100 })) : null,
    estimaciones, avisos,
    detalle: opc.detalle ?? 'medio', fondo: opc.fondo ?? 'ninguno',
  };
}

// ---------- Ropa nueva ----------

const L = (a, b) => [a, b];
const LARGOS_TORSO = ['pecho', 'cintura', 'cadera', 'muslo-alto', 'muslo', 'rodilla', 'pantorrilla', 'tobillo', 'suelo'];
const MANGAS = ['sin', 'corta', 'codo', 'tres-cuartos', 'larga', 'campana', 'ancha', 'globo', 'abullonada', 'kimono'];
const ESCOTES = ['redondo', 'v', 'cuadrado', 'barco', 'alto', 'ojal'];
const BAJOS = ['recto', 'curvo', 'ondulado', 'dentado'];
const ANCHOS_PANTALON = ['ajustado', 'recto', 'amplio', 'bombacho', 'campana'];
const CALZADOS = ['sandalias', 'zapatos', 'botas', 'botas-altas', 'mocasines', 'zuecos', 'polainas', 'descalzo', 'zapatillas'];
const CUELLOS = ['collar', 'gorguera', 'alto', 'bufanda', 'pectoral', 'cuentas-largas', 'gargantilla', 'lazo'];
const ROLES_PATRON = PATRONES_ID.filter((p) => !['piel', 'jaspe', 'malla', 'escamas'].includes(p));

function patronAzar(azar, prob) {
  return azar.bool(prob) ? { tipo: azar.elegir(ROLES_PATRON), escala: Math.round(azar.entre(0.7, 1.4) * 10) / 10, rol: 'secundario' } : undefined;
}

/**
 * Inventa una ropa que no está en el catálogo: combina plantillas con parámetros al azar.
 * estilo: 'sobrio' | 'trabajo' | 'festivo' | 'ceremonial'. Devuelve { prendas, paleta, nombre }.
 */
function generarRopaNueva({ semilla = 1, sexo = 'f', estilo = 'sobrio', esquema, paleta } = {}) {
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
  return renderizar(persona, { detalle: opc.detalle, fondo: opc.fondo });
}





Object.assign(__e, { renderizar, PIELES, CABELLOS, TINTES, ATUENDOS, PRENDAS, REGIONES, CORPULENCIAS, POSTURAS, ETNIAS, buscarAtuendos, listarRegiones, listarCatalogo, generarPaleta, generarPersona, generarRopaNueva, generarPersonaConRopaNueva, generarSVG });
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
