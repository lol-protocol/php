'use strict';

// Texturas y colores: que se generen igual cada vez, con el tamaño pedido, sin costura
// en el meridiano, y que las estrellas sigan su temperatura.

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');

const T = require('../js/texturas.js');
const DATOS = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'datos', 'astros.json'), 'utf8'));
const ASTROS = DATOS.astros;

const ANCHO = 128;
const ALTO = 64;

// Media de un canal (0 = rojo, 1 = verde, 2 = azul) sobre toda la textura.
function mediaCanal(buf, canal) {
  let s = 0;
  const n = buf.length / 4;
  for (let i = 0; i < n; i++) s += buf[i * 4 + canal];
  return s / n;
}

test('hexARgb convierte #rrggbb a tres canales', () => {
  assert.deepEqual(T.hexARgb('#ff8000'), [255, 128, 0]);
  assert.deepEqual(T.hexARgb('#000000'), [0, 0, 0]);
});

test('crearRng con la misma semilla da la misma secuencia', () => {
  const a = T.crearRng(42), b = T.crearRng(42), c = T.crearRng(43);
  const sa = [a(), a(), a()], sb = [b(), b(), b()], sc = [c(), c(), c()];
  assert.deepEqual(sa, sb);
  assert.notDeepEqual(sa, sc);
  for (const v of sa) assert.ok(v >= 0 && v < 1);
});

test('semillaDe es estable y distingue ids', () => {
  assert.equal(T.semillaDe('marte'), T.semillaDe('marte'));
  assert.notEqual(T.semillaDe('marte'), T.semillaDe('venus'));
});

test('rampa devuelve los extremos y el punto medio de una paleta de dos colores', () => {
  const p = [[0, 0, 0], [200, 100, 50]];
  assert.deepEqual(T.rampa(p, 0), [0, 0, 0]);
  assert.deepEqual(T.rampa(p, 0.5), [100, 50, 25]);
  // Fuera de [0, 1] se sujeta al extremo: no se sale de la paleta.
  const fuera = T.rampa(p, 2);
  [200, 100, 50].forEach((v, i) => assert.ok(Math.abs(fuera[i] - v) < 0.01, `canal ${i}: ${fuera[i]}`));
});

test('temperaturaARgb: frío anaranjado, sol casi blanco, caliente azulado', () => {
  const [r3, g3, b3] = T.temperaturaARgb(3000);
  assert.equal(r3, 255);
  assert.ok(r3 > g3 && g3 > b3, '3000 K debe ser rojo-anaranjado');

  const [r6, g6, b6] = T.temperaturaARgb(6500);
  assert.ok(Math.min(r6, g6, b6) > 230, '6500 K debe ser casi blanco');

  const [r2, , b2] = T.temperaturaARgb(20000);
  assert.equal(b2, 255);
  assert.ok(b2 > r2, '20000 K debe ser azulado');
});

test('temperaturaARgb sujeta valores fuera de rango, sin NaN', () => {
  for (const k of [0, 500, 1000, 40000, 1e6]) {
    for (const c of T.temperaturaARgb(k)) {
      assert.ok(Number.isInteger(c) && c >= 0 && c <= 255, `K=${k}: canal ${c}`);
    }
  }
});

test('generarTextura: tamaño, opacidad total y determinismo para todos los astros', () => {
  for (const a of ASTROS) {
    const x = T.generarTextura(a, ANCHO, ALTO);
    const y = T.generarTextura(a, ANCHO, ALTO);
    assert.equal(x.length, ANCHO * ALTO * 4, `${a.id}: tamaño`);
    assert.deepEqual(x, y, `${a.id}: no es determinista`);
    for (let i = 3; i < x.length; i += 4) assert.equal(x[i], 255, `${a.id}: alfa`);
  }
});

test('generarTextura: sin costura en el meridiano (primera y última columna parecidas)', () => {
  // Compara la columna 0 con la última: el ruido se muestrea sobre la esfera, así que
  // deben ser vecinas. Un valor alto delataría un salto en la longitud 0.
  for (const a of ASTROS.filter(x => x.id !== 'sol')) {
    const buf = T.generarTextura(a, ANCHO, ALTO);
    let dif = 0;
    for (let y = 0; y < ALTO; y++) {
      const i0 = (y * ANCHO) * 4, i1 = (y * ANCHO + ANCHO - 1) * 4;
      dif += Math.abs(buf[i0] - buf[i1]) + Math.abs(buf[i0 + 2] - buf[i1 + 2]);
    }
    const media = dif / (ALTO * 2);
    assert.ok(media < 60, `${a.id}: diferencia media en la costura ${media.toFixed(1)}`);
  }
});

test('generarTextura: cada astro tiene algo de contraste (no es un color plano)', () => {
  for (const a of ASTROS) {
    const buf = T.generarTextura(a, ANCHO, ALTO);
    let min = 255, max = 0;
    for (let i = 0; i < buf.length; i += 4) {
      const l = buf[i] + buf[i + 1] + buf[i + 2];
      if (l < min) min = l;
      if (l > max) max = l;
    }
    assert.ok(max - min > 12, `${a.id}: rango de luminancia ${max - min}`);
  }
});

test('las estrellas toman su color propio: Sirio azulado, Betelgeuse rojizo', () => {
  const sirio = ASTROS.find(a => a.id === 'sirio');
  const betel = ASTROS.find(a => a.id === 'betelgeuse');
  const bs = T.generarTextura(sirio, ANCHO, ALTO);
  const bb = T.generarTextura(betel, ANCHO, ALTO);
  assert.ok(mediaCanal(bs, 2) > mediaCanal(bs, 0), 'Sirio debe tener más azul que rojo');
  assert.ok(mediaCanal(bb, 0) > mediaCanal(bb, 2) + 40, 'Betelgeuse debe tener mucho más rojo que azul');
});

test('Jápeto tiene un hemisferio oscuro y otro claro', () => {
  const japeto = ASTROS.find(a => a.id === 'japeto');
  const buf = T.generarTextura(japeto, 256, 64);
  const brillo = (u0, u1) => {
    let s = 0, n = 0;
    for (let y = 0; y < 64; y++) {
      for (let x = Math.floor(u0 * 256); x < Math.floor(u1 * 256); x++) {
        const i = (y * 256 + x) * 4;
        s += buf[i] + buf[i + 1] + buf[i + 2];
        n += 3;
      }
    }
    return s / n;
  };
  const oscuro = brillo(0.0, 0.1);   // el hemisferio oscuro está centrado en u = 0
  const claro = brillo(0.45, 0.55);  // y el claro en u = 0,5
  assert.ok(claro > oscuro * 1.5, `claro ${claro.toFixed(0)} frente a oscuro ${oscuro.toFixed(0)}`);
});

test('una estrella con color propio lo usa; sin él, usa el de su temperatura', () => {
  const base = { id: 'prueba', temperatura_superficial_k: 5800, textura: { tipo: 'estrella', granulos: 6, escala: 4 } };
  const conColor = { id: 'prueba', temperatura_superficial_k: 5800, textura: { tipo: 'estrella', granulos: 6, escala: 4, color: '#6f9bff' } };
  const a = T.generarTextura(base, ANCHO, ALTO);
  const b = T.generarTextura(conColor, ANCHO, ALTO);
  assert.ok(mediaCanal(a, 0) > mediaCanal(a, 2), 'sin color propio debe verse cálida (5800 K)');
  assert.ok(mediaCanal(b, 2) > mediaCanal(b, 0), 'con #6f9bff debe verse azul');
});

test('las texturas de las estrellas son distintas entre sí por su color', () => {
  const medias = ASTROS.filter(a => a.categoria === 'sol').map(a => {
    const buf = T.generarTextura(a, ANCHO, ALTO);
    return [0, 1, 2].map(c => Math.round(mediaCanal(buf, c) / 4)).join(',');
  });
  assert.ok(new Set(medias).size >= medias.length - 3, 'demasiadas estrellas con el mismo color medio');
});

test('generarAnillo: alfa entre 0 y 255, con los bordes más transparentes que el centro', () => {
  const anillo = ASTROS.find(a => a.id === 'saturno').textura.anillos;
  const buf = T.generarAnillo(anillo, 256, 1);
  assert.equal(buf.length, 256 * 4);
  const alfa = i => buf[i * 4 + 3];
  for (let i = 0; i < 256; i++) assert.ok(alfa(i) >= 0 && alfa(i) <= 255);
  const bordes = (alfa(0) + alfa(255)) / 2;
  const centro = alfa(128);
  assert.ok(centro > bordes, 'el centro del anillo debe ser más denso que sus bordes');
});

test('generarAnillo respeta la opacidad máxima', () => {
  const anillo = ASTROS.find(a => a.id === 'urano').textura.anillos;
  const buf = T.generarAnillo(anillo, 128, 0.5);
  let max = 0;
  for (let i = 3; i < buf.length; i += 4) if (buf[i] > max) max = buf[i];
  assert.ok(max <= 128, `alfa máximo ${max} con opacidad 0,5`);
});

function luminanciaMedia(buf, filtro) {
  let s = 0, n = 0;
  for (let i = 0; i < buf.length / 4; i++) {
    if (!filtro(i)) continue;
    s += buf[i * 4] + buf[i * 4 + 1] + buf[i * 4 + 2];
    n += 3;
  }
  return n ? s / n : NaN;
}

test('generarRelieve: solo los cuerpos rocosos y helados tienen mapa de alturas', () => {
  for (const a of ASTROS) {
    const r = T.generarRelieve(a, ANCHO, ALTO);
    const debeTener = a.textura.tipo === 'rocoso' || a.textura.tipo === 'hielo';
    assert.equal(r !== null, debeTener, `${a.id} (${a.textura.tipo})`);
    if (r) {
      assert.equal(r.length, ANCHO * ALTO, `${a.id}: tamaño`);
      for (const v of r) assert.ok(v >= 0 && v <= 1, `${a.id}: altura fuera de 0..1`);
    }
  }
});

test('generarRelieve es determinista y los cráteres hunden el terreno', () => {
  const luna = ASTROS.find(a => a.id === 'luna');
  const a = T.generarRelieve(luna, 256, 128), b = T.generarRelieve(luna, 256, 128);
  assert.deepEqual(a, b);
  let hondos = 0;
  for (const v of a) if (v < 0.39) hondos++;   // 0,5 es el nivel medio; por debajo de 0,39 es un cuenco
  const parte = hondos / a.length;
  assert.ok(parte > 0.01 && parte < 0.6, `${(100 * parte).toFixed(2)} % de píxeles hundidos: debería haber cráteres, pero no todo hundido`);
});

test('el relieve y el color ven los mismos cráteres: donde el relieve se hunde, el color es más oscuro', () => {
  const luna = ASTROS.find(a => a.id === 'luna');
  const rel = T.generarRelieve(luna, 256, 128);
  const col = T.generarTextura(luna, 256, 128);
  const dentro = luminanciaMedia(col, i => rel[i] < 0.35);
  const fuera = luminanciaMedia(col, i => rel[i] >= 0.47);
  assert.ok(dentro < fuera, `dentro de cráteres ${dentro.toFixed(0)}, fuera ${fuera.toFixed(0)}`);
});

test('generarCapaNubes: solo la Tierra la trae; RGBA con alfa parcial y cobertura razonable', () => {
  for (const a of ASTROS) {
    assert.equal(T.generarCapaNubes(a, ANCHO, ALTO) !== null, a.id === 'tierra', a.id);
  }
  const tierra = ASTROS.find(a => a.id === 'tierra');
  const capa = T.generarCapaNubes(tierra, 256, 128);
  assert.equal(capa.length, 256 * 128 * 4);
  let tapados = 0, maximo = 0;
  for (let i = 0; i < capa.length; i += 4) {
    assert.deepEqual([capa[i], capa[i + 1], capa[i + 2]], [255, 255, 255], 'las nubes son blancas');
    if (capa[i + 3] > 20) tapados++;
    if (capa[i + 3] > maximo) maximo = capa[i + 3];
  }
  const cobertura = tapados / (capa.length / 4);
  assert.ok(cobertura > 0.1 && cobertura < 0.7, `cobertura ${(100 * cobertura).toFixed(0)} %`);
  assert.ok(maximo <= 217, 'ni las nubes más densas son del todo opacas');
});

test('con capa de nubes propia, el color base de la Tierra ya no lleva nubes pintadas', () => {
  const tierra = ASTROS.find(a => a.id === 'tierra');
  const sinCapa = JSON.parse(JSON.stringify(tierra));
  delete sinCapa.textura.capa_nubes;
  const blancos = buf => {
    let n = 0;
    for (let i = 0; i < buf.length; i += 4) if (buf[i] > 215 && buf[i + 1] > 215 && buf[i + 2] > 215) n++;
    return n;
  };
  const conCapa = blancos(T.generarTextura(tierra, 256, 128));
  const pintadas = blancos(T.generarTextura(sinCapa, 256, 128));
  assert.ok(pintadas > conCapa * 3, `con nubes pintadas ${pintadas} píxeles blancos, con capa aparte ${conCapa}`);
});

test('la capa de nubes no tiene costura en el meridiano', () => {
  const tierra = ASTROS.find(a => a.id === 'tierra');
  const capa = T.generarCapaNubes(tierra, 256, 128);
  let dif = 0;
  for (let y = 0; y < 128; y++) dif += Math.abs(capa[(y * 256) * 4 + 3] - capa[(y * 256 + 255) * 4 + 3]);
  assert.ok(dif / 128 < 40, `diferencia media de alfa en la costura ${(dif / 128).toFixed(1)}`);
});

// ---------- Mapa de normales ----------

function decodificar(buf, i) {
  return [buf[i * 4], buf[i * 4 + 1], buf[i * 4 + 2]].map(c => (c / 255) * 2 - 1);
}

test('normalesDeAltura: un terreno plano da la normal recta (0, 0, 1) en todas partes', () => {
  const ancho = 32, alto = 16;
  const plano = new Float32Array(ancho * alto).fill(0.5);
  const n = T.normalesDeAltura(plano, ancho, alto, 0.2);
  for (let i = 0; i < ancho * alto; i++) {
    const [x, y, z] = decodificar(n, i);
    assert.ok(Math.abs(x) < 0.01 && Math.abs(y) < 0.01 && z > 0.99, `píxel ${i}: ${x}, ${y}, ${z}`);
    assert.equal(n[i * 4 + 3], 255);
  }
});

test('normalesDeAltura: en una loma, la normal apunta hacia fuera de ella (este a la derecha, norte arriba)', () => {
  const ancho = 64, alto = 32;
  const loma = new Float32Array(ancho * alto);
  for (let y = 0; y < alto; y++) {
    for (let x = 0; x < ancho; x++) {
      const dx = x - 32, dy = y - 16;
      loma[y * ancho + x] = 0.5 + 0.3 * Math.exp(-(dx * dx + dy * dy) / 40);
    }
  }
  const n = T.normalesDeAltura(loma, ancho, alto, 0.2);
  const [ex] = decodificar(n, 16 * ancho + 38);   // al este del centro (x mayor)
  const [ox] = decodificar(n, 16 * ancho + 26);   // al oeste
  const [, nn] = decodificar(n, 22 * ancho + 32); // al norte (fila mayor: el norte está en las filas altas)
  const [, ss] = decodificar(n, 10 * ancho + 32); // al sur
  assert.ok(ex > 0.02, `este: nx = ${ex}`);
  assert.ok(ox < -0.02, `oeste: nx = ${ox}`);
  assert.ok(nn > 0.02, `norte: ny = ${nn}`);
  assert.ok(ss < -0.02, `sur: ny = ${ss}`);
});

test('normalesDeAltura: las normales tienen longitud 1 (salvo el redondeo a 8 bits) y no salen NaN, ni en los polos', () => {
  const lun = ASTROS.find(a => a.id === 'luna');
  const n = T.generarNormales(lun, 128, 64);
  for (let i = 0; i < 128 * 64; i++) {
    const v = decodificar(n, i);
    const largo = Math.hypot(...v);
    assert.ok(Math.abs(largo - 1) < 0.03, `píxel ${i}: longitud ${largo}`);
    assert.ok(v[2] > 0.3, `píxel ${i}: la normal no puede apuntar casi hacia dentro (z = ${v[2]})`);
  }
});

test('normalesDeAltura: sin costura en el meridiano; una loma sobre el borde de la textura da normales opuestas a cada lado', () => {
  const ancho = 64, alto = 32;
  const loma = new Float32Array(ancho * alto);
  for (let y = 0; y < alto; y++) {
    for (let x = 0; x < ancho; x++) {
      const dx = Math.min(x, ancho - x);   // la loma está centrada en x = 0, que es el mismo punto que x = 64
      const dy = y - 16;
      loma[y * ancho + x] = 0.5 + 0.3 * Math.exp(-(dx * dx + dy * dy) / 40);
    }
  }
  const n = T.normalesDeAltura(loma, ancho, alto, 0.2);
  const [derecha] = decodificar(n, 16 * ancho + 4);
  const [izquierda] = decodificar(n, 16 * ancho + (ancho - 4));
  assert.ok(derecha > 0.02 && izquierda < -0.02, `a un lado ${derecha}, al otro ${izquierda}`);
});

test('normalesDeAltura: la inclinación está limitada, así que ni un cráter diminuto da una pared vertical', () => {
  const ancho = 32, alto = 16;
  const escalon = new Float32Array(ancho * alto);
  for (let y = 0; y < alto; y++) for (let x = 0; x < ancho; x++) escalon[y * ancho + x] = x < 16 ? 0 : 1;
  const n = T.normalesDeAltura(escalon, ancho, alto, 5);
  for (let i = 0; i < ancho * alto; i++) {
    const [, , z] = decodificar(n, i);
    assert.ok(z > 0.8, `z = ${z}: más inclinada que el tope`);
  }
});

test('generarNormales: solo los rocosos y helados las tienen; los helados, con menos relieve que los rocosos', () => {
  for (const a of ASTROS) {
    const n = T.generarNormales(a, 64, 32);
    const debeTener = a.textura.tipo === 'rocoso' || a.textura.tipo === 'hielo';
    assert.equal(n !== null, debeTener, a.id);
  }
  const inclinacion = a => {
    const n = T.generarNormales(a, 128, 64);
    let s = 0;
    for (let i = 0; i < 128 * 64; i++) { const [x, y] = decodificar(n, i); s += Math.hypot(x, y); }
    return s / (128 * 64);
  };
  const roca = JSON.parse(JSON.stringify(ASTROS.find(a => a.id === 'luna')));
  const hielo = JSON.parse(JSON.stringify(roca));
  hielo.textura.tipo = 'hielo';
  assert.ok(inclinacion(hielo) < inclinacion(roca), 'con los mismos cráteres, el hielo debe estar menos inclinado');
});

test('textura.relieve ajusta la fuerza astro por astro (0 = liso)', () => {
  const lun = JSON.parse(JSON.stringify(ASTROS.find(a => a.id === 'luna')));
  lun.textura.relieve = 0;
  const n = T.generarNormales(lun, 64, 32);
  for (let i = 0; i < 64 * 32; i++) {
    const [x, y] = decodificar(n, i);
    assert.ok(Math.abs(x) < 0.01 && Math.abs(y) < 0.01);
  }
});

// ---------- Cráteres planos, sin escalones ni hoyitos ----------

test('formaCrater: -1 en el centro, sin escalón en el borde y 0 lejos del cráter', () => {
  assert.equal(T.formaCrater(0), -1);
  assert.equal(T.formaCrater(1.3), 0);
  assert.equal(T.formaCrater(5), 0);
  // Continua en el borde (d = 1): un escalón ahí se veía como un disco de borde duro.
  assert.ok(Math.abs(T.formaCrater(0.999) - T.formaCrater(1.001)) < 0.01, `salto en el borde: ${T.formaCrater(0.999)} frente a ${T.formaCrater(1.001)}`);
  // Y sin saltos en ningún otro punto: entre dos muestras muy cercanas, la diferencia es pequeña.
  for (let d = 0; d < 1.35; d += 0.005) {
    assert.ok(Math.abs(T.formaCrater(d + 0.005) - T.formaCrater(d)) < 0.08, `salto en d = ${d.toFixed(3)}`);
  }
});

test('valorCrater: el cráter oscurece poco (nada de agujeros negros) y el reborde apenas se insinúa', () => {
  assert.ok(T.valorCrater(0) >= -0.12, `centro: ${T.valorCrater(0)}`);
  assert.ok(T.valorCrater(0) < 0, 'el centro debe oscurecerse algo');
  let rebordeMaximo = 0;
  for (let d = 0.9; d < 1.3; d += 0.01) rebordeMaximo = Math.max(rebordeMaximo, T.valorCrater(d));
  assert.ok(rebordeMaximo <= 0.04, `reborde ${rebordeMaximo}`);
});

test('estamparCrateres dibuja la mitad de los cráteres que pide el catálogo (pocos, no cientos de hoyitos)', () => {
  let llamadas = 0;
  const rng = () => { llamadas++; return 0.5; };
  T.estamparCrateres(new Float32Array(512 * 256), 512, 256, 300, rng);
  assert.equal(T.DENSIDAD_CRATERES, 0.5);
  assert.equal(llamadas / 3, 150, `se dibujaron ${llamadas / 3} cráteres de 300`);
});

test('ningún cráter es un hoyito de uno o dos píxeles: el más pequeño ocupa varios', () => {
  const mapa = new Float32Array(512 * 256);
  const valores = [0.5, 0.5, 0];   // centro de la textura y el radio más pequeño posible
  let k = 0;
  T.estamparCrateres(mapa, 512, 256, 2, () => valores[k++ % 3]);
  // El radio mínimo es 512/170 = 3 px; el cráter (con su reborde, 1,3 radios) ocupa unos 48 píxeles.
  let tocados = 0;
  for (const v of mapa) if (v !== 0) tocados++;
  assert.ok(tocados >= 30, `el cráter más pequeño ocupa solo ${tocados} píxeles`);
});

test('relieve plano: la inclinación media es mínima y ninguna pared pasa de unos 31°', () => {
  for (const a of ASTROS) {
    const n = T.generarNormales(a, 128, 64);
    if (!n) continue;
    let suma = 0, maxima = 0;
    for (let i = 0; i < 128 * 64; i++) {
      const g = Math.hypot(n[i * 4] / 255 * 2 - 1, n[i * 4 + 1] / 255 * 2 - 1);
      suma += g;
      if (g > maxima) maxima = g;
    }
    assert.ok(suma / (128 * 64) < 0.12, `${a.id}: inclinación media ${(suma / (128 * 64)).toFixed(3)}`);
    assert.ok(maxima < 0.56, `${a.id}: la pared más inclinada llega a ${maxima.toFixed(2)}`);
  }
});
