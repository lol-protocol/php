import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarSVG, generarPersona, renderizar, ATUENDOS } from '../src/index.js';

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
  assert.ok(!/<script|javascript:|\son\w+=|<foreignObject|<image|href=/i.test(svg), `${etiqueta}: contenido activo o externo`);
  // Todos los fill/stroke son colores, none o patrones definidos.
  const ids = new Set([...svg.matchAll(/<(?:pattern|linearGradient) id="([^"]+)"/g)].map((m) => m[1]));
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
  const alto = generarSVG({ semilla: 3, detalle: 'alto' });
  assert.ok(!plano.includes('url(#sombra-h)') && alto.includes('url(#sombra-v)'));
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
      if (/[a-z]/.test(m[1].replace(/[MLCQZ]/g, '').replace(/[a-z](?=[^a-z]*$)/g, ''))) { /* trazos relativos: se saltan */ }
      for (const par of m[1].matchAll(/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/g)) {
        const x = Number(par[1]), y = Number(par[2]);
        if (/[a-z]/.test(m[1])) continue;
        assert.ok(x > -30 && x < 630, `${a.id}: x fuera del lienzo (${x})`);
        assert.ok(y > -60 && y < 1040, `${a.id}: y fuera del lienzo (${y})`);
      }
    }
  }
});
