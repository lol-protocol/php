'use strict';

// La parte de las visualizaciones que no dibuja: ajustes de la MilkDrop (validación,
// importación y exportación, afinidad con el estilo), partículas, proyección 3D, barras
// del espectro y lluvia de notas.

const test = require('node:test');
const assert = require('node:assert/strict');
const VP = require('../js/visual-puro.js');
const G = require('../js/generador.js');

function cerca(a, b, tol) {
  assert.ok(Math.abs(a - b) <= (tol == null ? 1e-6 : tol), a + ' no está cerca de ' + b);
}

function azarFijo(semilla) {
  return G.crearAzar(semilla).num;
}

// ------------------------------------------------------------ ajustes

test('los ajustes de fábrica son válidos, con nombres distintos y dentro de sus rangos', function () {
  const nombres = new Set();
  for (const p of VP.PRESETS) {
    assert.ok(!nombres.has(p.nombre), 'nombre repetido ' + p.nombre);
    nombres.add(p.nombre);
    const v = VP.validar(p);
    assert.deepEqual(v, p, p.nombre + ' cambia al validarlo');
  }
  assert.ok(VP.PRESETS.length >= 12);
});

test('validar: recorta a los rangos, redondea la onda y rechaza lo que no es número', function () {
  const v = VP.validar({ nombre: '  <b>Raro</b>\u0000 ', zoom: 5, rot: -9, warp: -1, warpFrec: 99, decay: 2, tono: 1, brillo: 0, onda: 9.4, caleido: 0.7 });
  assert.equal(v.nombre, 'bRaro/b');
  assert.equal(v.zoom, VP.LIMITES.zoom.max);
  assert.equal(v.rot, VP.LIMITES.rot.min);
  assert.equal(v.warp, 0);
  assert.equal(v.warpFrec, VP.LIMITES.warpFrec.max);
  assert.equal(v.decay, VP.LIMITES.decay.max);
  assert.equal(v.brillo, VP.LIMITES.brillo.min);
  assert.equal(v.onda, 3);
  assert.equal(v.caleido, 1);
  assert.equal(VP.validar(Object.assign({}, VP.PRESETS[0], { zoom: 'mucho' })), null);
  assert.equal(VP.validar(null), null);
  assert.equal(VP.validar(Object.assign({}, VP.PRESETS[0], { nombre: '   ' })).nombre, 'Sin nombre');
  assert.equal(VP.validar(Object.assign({}, VP.PRESETS[0], { nombre: 'x'.repeat(100) })).nombre.length, 40);
});

test('mezclar: los extremos son los ajustes; en medio, el promedio', function () {
  const a = VP.PRESETS[0];
  const b = VP.PRESETS[2];
  assert.deepEqual(VP.mezclar(a, b, 0), a);
  assert.deepEqual(VP.mezclar(a, b, 1), b);
  const m = VP.mezclar(a, b, 0.5);
  for (const k of VP.CLAVES_NUMERICAS) cerca(m[k], (a[k] + b[k]) / 2);
});

test('exportar e importar: ida y vuelta, y errores claros', function () {
  const texto = VP.serializar(VP.PRESETS.slice(0, 4));
  const r = VP.importar(texto);
  assert.deepEqual(r.presets, VP.PRESETS.slice(0, 4));
  assert.equal(r.rechazados, 0);
  // un arreglo suelto también vale, y lo inválido se cuenta
  const mixto = VP.importar(JSON.stringify([VP.PRESETS[0], { nombre: 'roto' }, 7, Object.assign({}, VP.PRESETS[1], { zoom: 99 })]));
  assert.equal(mixto.presets.length, 2);
  assert.equal(mixto.rechazados, 2);
  assert.equal(mixto.presets[1].zoom, VP.LIMITES.zoom.max);
  assert.throws(function () { VP.importar('esto no es json'); }, /no es un JSON válido/);
  assert.throws(function () { VP.importar('{"a":1}'); }, /lista de ajustes/);
  // un archivo enorme no entra entero
  const muchos = VP.importar(JSON.stringify(new Array(200).fill(VP.PRESETS[0])));
  assert.equal(muchos.presets.length, 50);
  assert.equal(muchos.rechazados, 150);
});

test('cada estilo tiene su grupo de ajustes, que existen, y su color', function () {
  const estilos = Object.keys(G.ESTILOS).map(function (k) { return G.ESTILOS[k].nombre; });
  const nombres = VP.PRESETS.map(function (p) { return p.nombre; });
  for (const e of estilos) {
    assert.ok(VP.ESTILO_PRESETS[e], 'falta el grupo de ' + e);
    for (const n of VP.ESTILO_PRESETS[e]) assert.ok(nombres.indexOf(n) >= 0, e + ' nombra un ajuste que no existe: ' + n);
    assert.ok(Number.isFinite(VP.MATIZ_ESTILO[e]), 'falta el color de ' + e);
  }
  // los estilos tienen colores distintos
  assert.equal(new Set(estilos.map(function (e) { return VP.MATIZ_ESTILO[e]; })).size, estilos.length);
});

test('paraEstilo: sale del grupo del estilo y no repite el actual', function () {
  const az = azarFijo(5);
  for (const e of Object.keys(VP.ESTILO_PRESETS)) {
    let actual = VP.ESTILO_PRESETS[e][0];
    for (let i = 0; i < 40; i++) {
      const p = VP.paraEstilo(e, az, actual);
      assert.ok(VP.ESTILO_PRESETS[e].indexOf(p.nombre) >= 0);
      assert.notEqual(p.nombre, actual);
      actual = p.nombre;
    }
  }
  // un estilo desconocido toma de todos; con una lista de uno solo, no hay otra salida
  assert.ok(VP.paraEstilo('Rock', az, 'Túnel'));
  const solo = [VP.PRESETS[0]];
  assert.equal(VP.paraEstilo('Pop', az, VP.PRESETS[0].nombre, solo), VP.PRESETS[0]);
  // los ajustes propios entran en la elección
  const propio = Object.assign({}, VP.PRESETS[0], { nombre: 'Mío' });
  const vistos = new Set();
  for (let i = 0; i < 60; i++) vistos.add(VP.paraEstilo('Rock', az, '', VP.PRESETS.concat([propio])).nombre);
  assert.ok(vistos.has('Mío'));
});

test('matiz: el camino más corto entre dos colores', function () {
  cerca(VP.difMatiz(350, 10), 20);
  cerca(VP.difMatiz(10, 350), -20);
  cerca(VP.difMatiz(0, 180), -180);
  cerca(VP.difMatiz(90, 90), 0);
  assert.equal(VP.matizDeEstilo('Pop'), 320);
  assert.equal(VP.matizDeEstilo('nada'), 190);
});

// ------------------------------------------------------------ partículas

test('partículas: nacen, se mueven, mueren y el anillo reutiliza las más viejas', function () {
  const s = VP.crearParticulas(10);
  assert.equal(VP.vivas(s), 0);
  const i = VP.emitir(s, { x: 100, y: 100, vx: 50, vy: -20, vida: 1, tam: 4, matiz: 120 });
  assert.equal(VP.vivas(s), 1);
  VP.pasoParticulas(s, 0.5, { arrastre: 0 });
  cerca(s.x[i], 125);
  cerca(s.y[i], 90);
  cerca(VP.vidaRelativa(s, i), 0.5);
  VP.pasoParticulas(s, 0.6, { arrastre: 0 });
  assert.equal(VP.vivas(s), 0);
  // más emisiones que lugares: nunca pasa del máximo
  for (let k = 0; k < 25; k++) VP.emitir(s, { x: k, y: 0, vida: 5 });
  assert.equal(VP.vivas(s), 10);
  assert.equal(s.x[(s.siguiente + 9) % 10], 24); // la última emitida
});

test('partículas: la gravedad las tira, el arrastre las frena y las que salen de la pantalla mueren', function () {
  const s = VP.crearParticulas(4);
  const caida = VP.emitir(s, { x: 50, y: 50, vida: 10 });
  VP.pasoParticulas(s, 1, { gravedad: 100, arrastre: 0 });
  assert.ok(s.vy[caida] > 99 && s.y[caida] > 99);
  const frenada = VP.emitir(s, { x: 0, y: 0, vx: 100, vida: 10 });
  VP.pasoParticulas(s, 1, { arrastre: 0.5 });
  cerca(s.vx[frenada], 50);
  const fuera = VP.emitir(s, { x: 1000, y: 10, vx: 1000, vida: 10 });
  VP.pasoParticulas(s, 0.1, { ancho: 200, alto: 100, arrastre: 0 });
  assert.equal(s.vida[fuera], 0);
});

test('partículas: la nota fija su lugar de izquierda a derecha, de grave a agudo', function () {
  assert.equal(VP.xDeNota(28), 0);
  assert.equal(VP.xDeNota(96), 1);
  assert.equal(VP.xDeNota(0), 0);
  assert.equal(VP.xDeNota(127), 1);
  assert.ok(VP.xDeNota(48) < VP.xDeNota(72));
});

// ------------------------------------------------------------ Lissajous 3D

test('proyección: sin giro un punto en el eje queda en su lugar; el giro conserva la distancia', function () {
  const p = VP.proyectar(0.5, 0.25, 0, 0, 0, 3);
  cerca(p.x, 0.5);
  cerca(p.y, 0.25);
  cerca(p.escala, 1);
  // un cuarto de vuelta en Y lleva x a la profundidad
  const q = VP.proyectar(1, 0, 0, 0, Math.PI / 2, 3);
  cerca(q.x, 0, 1e-9);
  cerca(q.z, -1, 1e-9);
  // lo que está más cerca de la cámara (z negativa) se ve más grande
  assert.ok(q.escala > 1);
  assert.ok(VP.proyectar(0, 0, 1, 0, 0, 3).escala < 1);
  // el giro es una rotación: conserva el largo del vector
  const r = VP.proyectar(0.3, -0.4, 0.5, 0.7, 1.9, 1e9);
  cerca(Math.hypot(r.x, r.y, r.z), Math.hypot(0.3, 0.4, 0.5), 1e-6);
});

test('curva de Lissajous 3D: un punto por muestra, finito y dentro de la pantalla', function () {
  const n = 1024;
  const L = new Float32Array(n);
  const R = new Float32Array(n);
  for (let i = 0; i < n; i++) {
    L[i] = 0.8 * Math.sin((i / n) * Math.PI * 6);
    R[i] = 0.8 * Math.sin((i / n) * Math.PI * 4 + 1);
  }
  const out = new Float32Array(n * 3);
  const gan = VP.gananciaAuto(L, R);
  assert.ok(gan >= 1 && gan <= 6);
  assert.equal(VP.curvaLissajous3d(L, R, 256, 0.5, 0.3, gan, out, 3.2), n);
  assert.ok(out.every(Number.isFinite));
  let max = 0;
  for (let i = 0; i < n; i++) max = Math.max(max, Math.abs(out[i * 3]), Math.abs(out[i * 3 + 1]));
  assert.ok(max < 2.5, 'sale de la pantalla: ' + max);
  // el silencio sube la ganancia al tope sin pasarse
  assert.equal(VP.gananciaAuto(new Float32Array(8), new Float32Array(8)), 6);
});

// ------------------------------------------------------------ espectro

test('barras del espectro: reparto logarítmico, graves a la izquierda y valores de 0 a 1', function () {
  const frec = new Uint8Array(2048);
  const sr = 48000;
  const binHz = sr / 4096;
  frec[Math.round(100 / binHz)] = 255; // 100 Hz
  frec[Math.round(5000 / binHz)] = 128; // 5 kHz
  const barras = VP.barrasEspectro(frec, sr, 4096, 32, 30, 18000);
  assert.equal(barras.length, 32);
  assert.ok(barras.every(function (v) { return v >= 0 && v <= 1; }));
  const grave = barras.indexOf(Math.max.apply(null, barras));
  const agudo = barras.indexOf(Math.max.apply(null, barras.map(function (v, i) { return i === grave ? 0 : v; })));
  assert.ok(grave < agudo, 'el grave va antes que el agudo: ' + grave + ' y ' + agudo);
  cerca(barras[grave], 1);
  cerca(barras[agudo], Math.pow(128 / 255, 1.4), 1e-6);
  // el silencio no mueve nada
  assert.ok(VP.barrasEspectro(new Uint8Array(2048), sr, 4096, 32).every(function (v) { return v === 0; }));
});

test('barras con gravedad y picos que caen acelerando', function () {
  const est = VP.crearBarras(2);
  VP.actualizarBarras(est, [1, 0.2], 100);
  cerca(est.alturas[0], 1);
  cerca(est.picos[0], 92);
  // en silencio la barra baja de a 0,025 por cuadro y el pico se queda un cuadro y luego cae acelerando
  const picos = [];
  for (let i = 0; i < 6; i++) {
    VP.actualizarBarras(est, [0, 0], 100);
    picos.push(est.picos[0]);
  }
  cerca(est.alturas[0], 1 - 6 * 0.025);
  for (let i = 1; i < picos.length; i++) assert.ok(picos[i] < picos[i - 1], 'el pico no baja');
  const caidas = picos.map(function (p, i) { return (i ? picos[i - 1] : 92) - p; });
  for (let i = 1; i < caidas.length; i++) assert.ok(caidas[i] > caidas[i - 1], 'el pico no acelera');
  // una barra nueva más alta que el pico lo reemplaza
  VP.actualizarBarras(est, [1, 0], 100);
  cerca(est.picos[0], 92);
  assert.equal(est.velPicos[0], 0);
});

// ------------------------------------------------------------ lluvia de notas

test('lluvia de notas: «ahora» al 78 %, lo que viene a la derecha, y fuera de pantalla no hay rectángulo', function () {
  const W = 900;
  const H = 700;
  const sonando = VP.rectNota({ nota: 60, t: 10, dur: 2, canal: 0 }, 11, W, H);
  assert.ok(sonando.sonando);
  assert.equal(sonando.alfa, 1);
  cerca(sonando.x0 + (11 - 10) * (W / 9), W * 0.78); // el inicio queda un segundo antes de «ahora»
  const futura = VP.rectNota({ nota: 60, t: 11, dur: 1, canal: 0 }, 10, W, H);
  assert.ok(!futura.sonando && futura.alfa === 0.35);
  assert.ok(futura.x0 > W * 0.78);
  const pasada = VP.rectNota({ nota: 60, t: 8, dur: 0.5, canal: 0 }, 10, W, H);
  assert.equal(pasada.alfa, 0.55);
  assert.equal(VP.rectNota({ nota: 60, t: -50, dur: 1, canal: 0 }, 10, W, H), null);
  assert.equal(VP.rectNota({ nota: 60, t: 100, dur: 1, canal: 0 }, 10, W, H), null);
  // las agudas arriba, las graves abajo; la batería cerca del suelo
  assert.ok(VP.rectNota({ nota: 90, t: 10, dur: 1, canal: 0 }, 10, W, H).y < VP.rectNota({ nota: 40, t: 10, dur: 1, canal: 0 }, 10, W, H).y);
  assert.ok(VP.rectNota({ nota: 36, t: 10, dur: 1, canal: 9 }, 10, W, H).y > H * 0.9);
});

// ------------------------------------------------------------ menos movimiento

test('suavizarPreset: k = 1 no cambia nada y k = 0 casi quieta la imagen', function () {
  for (const p of VP.PRESETS) {
    const igual = VP.suavizarPreset(p, 1);
    for (const c of VP.CLAVES_NUMERICAS) assert.ok(Math.abs(igual[c] - p[c]) < 1e-12, p.nombre + ': ' + c + ' cambió con k = 1');
    const q = VP.suavizarPreset(p, 0);
    assert.equal(q.zoom, 1);
    assert.ok(Math.abs(q.rot) === 0, 'rot debe quedar en 0 (-0 vale)');
    assert.ok(Math.abs(q.warp) === 0, 'warp debe quedar en 0 (-0 vale)');
    assert.ok(Math.abs(q.tono) === 0, 'tono debe quedar en 0 (-0 vale)');
    assert.ok(q.brillo <= 1);
    assert.ok(q.decay >= Math.min(p.decay, 0.985));
    // la forma de la onda, el espejo y el nombre no se tocan
    assert.equal(q.onda, p.onda);
    assert.equal(q.caleido, p.caleido);
    assert.equal(q.nombre, p.nombre);
  }
});

test('suavizarPreset: cada parámetro se acerca a la quietud de forma monótona y queda dentro de los rangos válidos', function () {
  const p = VP.PRESETS.find(function (x) { return x.nombre === 'Pulso'; });
  let previo = null;
  for (const k of [1, 0.75, 0.5, 0.35, 0.1, 0]) {
    const q = VP.suavizarPreset(p, k);
    assert.ok(VP.validar(q) !== null);
    for (const c of VP.CLAVES_NUMERICAS) assert.ok(q[c] >= VP.LIMITES[c].min - 1e-12 && q[c] <= VP.LIMITES[c].max + 1e-12, c + ' fuera de rango con k=' + k);
    if (previo) {
      assert.ok(Math.abs(q.zoom - 1) <= Math.abs(previo.zoom - 1) + 1e-12);
      assert.ok(Math.abs(q.rot) <= Math.abs(previo.rot) + 1e-12);
      assert.ok(q.decay >= previo.decay - 1e-12, 'la estela debe alargarse');
    }
    previo = q;
  }
  // no muta el original, y k fuera de rango se recorta
  const copia = JSON.parse(JSON.stringify(p));
  VP.suavizarPreset(p, 0.3);
  assert.deepEqual(p, copia);
  assert.deepEqual(VP.suavizarPreset(p, 7), VP.suavizarPreset(p, 1));
  assert.deepEqual(VP.suavizarPreset(p, -3), VP.suavizarPreset(p, 0));
});
