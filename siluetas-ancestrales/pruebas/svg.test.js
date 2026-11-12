import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarSVG, generarPersona, renderizar, ATUENDOS } from '../src/index.js';
import { puntosDeRuta } from './ayuda.js';

/** Comprobación mínima de que el XML está bien formado: etiquetas balanceadas y atributos entre comillas. */
function bienFormado(svg) {
  const pila = [];
  const re = /<(\/?)([a-zA-Z][\w:-]*)((?:\s+[\w:-]+="[^"]*")*)\s*(\/?)>/g;
  let resto = svg.replace(re, (m, cierra, nombre, _attrs, auto) => {
    if (auto) return '';
    if (cierra) { assert.equal(pila.pop(), nombre, `etiqueta de cierre inesperada </${nombre}>`); return ''; }
    pila.push(nombre);
    return '';
  });
  assert.equal(pila.length, 0, `etiquetas sin cerrar: ${pila.join(',')}`);
  assert.equal(resto.replace(/[^<>]/g, '').length, 0, `quedaron < o > sueltos: ${resto.slice(0, 80)}`);
}

function revisar(svg, etiqueta) {
  assert.ok(svg.startsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 1000"'), etiqueta);
  bienFormado(svg);
  assert.ok(!/NaN|undefined|Infinity|null/.test(svg), `${etiqueta}: valor inválido en el SVG`);
  assert.ok(!/<script|javascript:|\son\w+=|<foreignObject|<image|href="(?!#)/i.test(svg), `${etiqueta}: contenido activo o externo`);
  // Toda referencia interna (href="#x", url(#x)) debe apuntar a un id definido, y ningún id se repite.
  const idsDef = [...svg.matchAll(/ id="([^"]+)"/g)].map((m) => m[1]);
  assert.equal(new Set(idsDef).size, idsDef.length, `${etiqueta}: ids repetidos`);
  const defIds = new Set(idsDef);
  for (const m of svg.matchAll(/href="#([^"]+)"|url\(#([^)]+)\)/g)) assert.ok(defIds.has(m[1] ?? m[2]), `${etiqueta}: referencia a un id que no existe: ${m[1] ?? m[2]}`);
  // Todos los fill/stroke son colores, none o patrones definidos.
  const ids = new Set([...svg.matchAll(/<(?:pattern|linearGradient|radialGradient) id="([^"]+)"/g)].map((m) => m[1]));
  for (const m of svg.matchAll(/(?:fill|stroke)="([^"]*)"/g)) {
    const v = m[1];
    if (v === 'none' || /^#[0-9a-f]{6}$/i.test(v) || v === '#000') continue;
    const u = v.match(/^url\(#([^)]+)\)$/);
    assert.ok(u && ids.has(u[1]), `${etiqueta}: relleno no definido ${v}`);
  }
}

test('cada atuendo del catálogo se dibuja bien a varias edades y complexiones', () => {
  const casos = [[2, 'delgada'], [8, 'media'], [15, 'media'], [30, 'robusta'], [30, 'corpulenta'], [80, 'media']];
  let n = 0;
  for (const a of ATUENDOS) {
    for (const [edad, corp] of casos) {
      const svg = generarSVG({ atuendo: a.id, sexo: a.sexo, edad, corpulencia: corp, semilla: n });
      revisar(svg, `${a.id} ${edad} ${corp}`);
      n++;
    }
  }
  assert.ok(n >= 600);
});

test('los tres niveles de detalle, los rasgos y las tres posturas dibujan', () => {
  for (const detalle of ['minimo', 'medio', 'alto']) for (const postura of ['firme', 'relajada', 'abierta']) {
    revisar(generarSVG({ semilla: 3, detalle, postura, rasgos: detalle === 'alto' }), `${detalle}/${postura}`);
  }
  const plano = generarSVG({ semilla: 3, detalle: 'minimo' });
  const medio = generarSVG({ semilla: 3, detalle: 'medio' });
  const alto = generarSVG({ semilla: 3, detalle: 'alto' });
  assert.ok(!plano.includes('-g-m-') && !plano.includes('filter='), 'el nivel plano no lleva degradados ni sombras');
  assert.ok(medio.includes('-g-m-') && /filter="url\(#x\w+-bl\)"/.test(medio), 'el nivel medio lleva degradados y sombras proyectadas');
  assert.ok(alto.includes('-g-a-') && /url\(#x\w+-sv\)/.test(alto));
});

test('el fondo y el título salen en el SVG y se escapan', () => {
  const p = generarPersona({ semilla: 4 });
  p.titulo = 'A <b> & "c"';
  const svg = renderizar(p, { fondo: '#efe9dd' });
  assert.ok(svg.includes('<title>A &lt;b&gt; &amp; &quot;c&quot;</title>'));
  assert.ok(svg.includes('<rect width="600" height="1000" fill="#efe9dd"/>'));
  bienFormado(svg);
});

test('la figura queda dentro del lienzo (comprobación aproximada por las coordenadas absolutas)', () => {
  for (const a of ATUENDOS.slice(0, 40)) {
    const svg = generarSVG({ atuendo: a.id, sexo: a.sexo, edad: 35, corpulencia: 'media', semilla: 1 });
    for (const m of svg.matchAll(/ d="([^"]+)"/g)) {
      if (/[a-z]/.test(m[1])) continue; // trazos relativos: se saltan
      for (const [x, y] of puntosDeRuta(m[1])) {
        assert.ok(x > -30 && x < 630, `${a.id}: x fuera del lienzo (${x})`);
        assert.ok(y > -60 && y < 1040, `${a.id}: y fuera del lienzo (${y})`);
      }
    }
  }
});

test('por defecto no hay bordes: ninguna forma de la figura lleva trazo; con contorno suave sí, y tenue', () => {
  const sin = generarSVG({ semilla: 6, atuendo: 'huaso' });
  assert.ok(!/<use[^>]*stroke=/.test(sin), 'una forma lleva trazo');
  const con = generarSVG({ semilla: 6, atuendo: 'huaso', contorno: 'suave' });
  const trazos = [...con.matchAll(/<use[^>]*stroke="([^"]+)"[^>]*stroke-width="([\d.]+)"[^>]*opacity="([\d.]+)"/g)];
  assert.ok(trazos.length > 10);
  for (const [, , ancho, op] of trazos) assert.ok(Number(ancho) <= 1 && Number(op) <= 0.5, 'el contorno suave debe ser fino y tenue');
});

test('el volumen: cada color usa su degradado y las capas proyectan sombra recortada a la silueta', () => {
  const svg = generarSVG({ semilla: 6, atuendo: 'huaso' });
  assert.ok((svg.match(/<linearGradient id="x\w+-g-m-/g) ?? []).length >= 5);
  assert.ok((svg.match(/clip-path="url\(#x/g) ?? []).length >= 10);
  assert.ok(/<clipPath id="x[\w]+s">/.test(svg));
});

test('varias figuras en una misma página no comparten ids de forma (cada una lleva su prefijo)', () => {
  const ids = (svg) => new Set([...svg.matchAll(/ id="(x[^"]+)"/g)].map((m) => m[1]));
  const todos = (svg) => [...svg.matchAll(/ id="([^"]+)"/g)].map((m) => m[1]);
  assert.ok(todos(generarSVG({ semilla: 1 })).every((id) => id.startsWith('x')), 'todo id de una figura lleva su prefijo');
  const a = ids(generarSVG({ semilla: 1 })), b = ids(generarSVG({ semilla: 2 }));
  assert.ok(a.size > 20 && b.size > 20);
  for (const id of a) assert.ok(!b.has(id), `id compartido: ${id}`);
  assert.deepEqual([...ids(generarSVG({ semilla: 1 }))], [...a], 'el prefijo debe ser determinista');
});

test('compacto: el SVG pesa menos, solo cambian los decimales de las rutas y sigue bien formado', () => {
  for (const semilla of [1, 2, 3, 4, 5]) {
    const normal = generarSVG({ semilla });
    const liviano = generarSVG({ semilla, compacto: true });
    assert.ok(liviano.length < normal.length * 0.96, `semilla ${semilla}: ${liviano.length} de ${normal.length}`);
    bienFormado(liviano);
    // Lo que no es una ruta queda idéntico.
    assert.equal(liviano.replace(/ d="[^"]*"/g, ''), normal.replace(/ d="[^"]*"/g, ''));
    // Cada coordenada difiere a lo más 0,1 de la normal.
    const a = normal.match(/ d="[^"]*"/g), b = liviano.match(/ d="[^"]*"/g);
    assert.equal(a.length, b.length);
    a.forEach((d, i) => {
      const xs = d.match(/-?\d+(?:\.\d+)?/g).map(Number), ys = b[i].match(/-?\d+(?:\.\d+)?/g).map(Number);
      assert.equal(xs.length, ys.length);
      xs.forEach((x, j) => assert.ok(Math.abs(x - ys[j]) <= 0.1, `${x} vs ${ys[j]}`));
    });
  }
});
