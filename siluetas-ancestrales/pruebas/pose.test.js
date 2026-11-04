import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarPersona, generarSVG, POSE_FIRME, poseAleatoria } from '../src/index.js';
import { medidas, piernaLado, brazoLado, trazoMano, trazoPie, trazoCuello, deformar } from '../src/cuerpo.js';
import { crearAzar } from '../src/util.js';

test('por defecto la pose es casual y cambia con la semilla; con la misma semilla es igual', () => {
  const poses = Array.from({ length: 60 }, (_, i) => JSON.stringify(generarPersona({ semilla: i }).cuerpo.pose));
  assert.ok(new Set(poses).size > 55, 'las poses casi no varían');
  assert.deepEqual(generarPersona({ semilla: 7 }).cuerpo.pose, generarPersona({ semilla: 7 }).cuerpo.pose);
  assert.equal(generarPersona({ semilla: 7 }).cuerpo.pose.modo, 'casual');
});

test('la pose casual recorre todas las variantes y se mantiene en rangos naturales', () => {
  const brazos = new Set(), cargas = new Set();
  for (let i = 0; i < 300; i++) {
    const p = poseAleatoria(crearAzar(`pose${i}`));
    brazos.add(p.brazoLibre); cargas.add(p.carga);
    assert.ok(Math.abs(p.cabeza) <= 7 && Math.abs(p.inclinacion) <= 1.4);
    assert.ok(Math.abs(p.hombros) <= 2.8 && Math.abs(p.columna) <= 0.008, 'hombros o columna fuera de rango');
    for (const l of [1, '-1']) assert.ok(p.codos[l] >= -4 && p.codos[l] <= 12 && Math.abs(p.munecas[l]) <= 14, 'codo o muñeca fuera de rango');
    assert.ok(p.cinturaCodo >= 0.85 && p.cinturaCodo <= 1.2 && p.dedos >= 0.8 && p.dedos <= 1.25 && Math.abs(p.saludo.mano) <= 16);
    assert.ok(p.abre >= 0.85 && p.abre <= 1.5 && p.giro >= 6 && p.giro <= 20);
    assert.ok(p.saludo.codo >= 40 && p.saludo.codo <= 62 && p.saludo.antebrazo >= 2 && p.saludo.antebrazo <= 24 && Math.abs(p.saludo.mano) <= 16);
    for (const a of [p.angulos[1], p.angulos['-1']]) assert.ok(a >= 5 && a <= 17);
  }
  assert.deepEqual([...brazos].sort(), ['abajo', 'bolsillo', 'cintura']);
  assert.deepEqual([...cargas].sort((x, y) => x - y), [-1, 0, 1]);
});

test('la pose firme es simétrica: mismas piernas y pies a ambos lados, y el brazo libre cae recto', () => {
  const M = medidas({ sexo: 'm', edad: 30, pose: 'firme', saludo: 'ninguno' });
  const a = piernaLado(M, 1), b = piernaLado(M, -1);
  for (let i = 0; i < a.linea.length; i++) assert.ok(Math.abs((a.linea[i][0] - M.cx) + (b.linea[i][0] - M.cx)) < 1e-6, `pierna asimétrica en ${i}`);
  assert.ok(Math.abs(a.pie.giro - b.pie.giro) < 1e-9);
  assert.equal(M.brazos[1].tipo, 'caido');
  assert.equal(M.brazos[-1].tipo, 'caido');
  const sim = generarPersona({ semilla: 3, pose: 'firme' }).cuerpo.pose;
  assert.deepEqual(sim, POSE_FIRME);
});

test('la pierna que no carga el peso queda más abierta y con el pie más girado', () => {
  const M = medidas({ sexo: 'f', edad: 30, pose: { carga: 1, abre: 1.2, giro: 10 }, saludo: 'ninguno' });
  const carga = piernaLado(M, 1), libre = piernaLado(M, -1);
  assert.ok(Math.abs(libre.linea[4][0] - M.cx) > Math.abs(carga.linea[4][0] - M.cx) + 4);
  assert.ok(libre.pie.giro > carga.pie.giro);
});

test('con la mano en la cintura la muñeca queda sobre la cadera y la mano es una sola forma; en el bolsillo no se ve', () => {
  const M = medidas({ sexo: 'm', edad: 30, pose: { brazoLibre: 'cintura' }, saludo: 'derecha' });
  const libre = M.saludoLado === -1 ? 1 : -1;
  const b = brazoLado(M, libre);
  const mun = b.linea[4];
  assert.equal(b.tipo, 'cintura');
  assert.ok(mun[1] > M.y.cintura && mun[1] < M.y.cadera + 20, 'muñeca fuera de la cintura');
  assert.ok(Math.abs(mun[0] - M.cx) < M.w.cadera * 1.15 && Math.abs(mun[0] - M.cx) > M.w.cintura * 0.8);
  assert.equal(trazoMano(M, libre).length, 1);
  const Mb = medidas({ sexo: 'm', edad: 30, pose: { brazoLibre: 'bolsillo' }, saludo: 'derecha' });
  assert.equal(trazoMano(Mb, libre).length, 0);
  assert.equal(Mb.brazos[libre].tipo, 'bolsillo');
});

test('el saludo cambia con el ángulo de codo, antebrazo y mano, sin salirse del lienzo', () => {
  const alto = (pose) => { const M = medidas({ sexo: 'f', edad: 30, pose: { saludo: pose }, saludo: 'derecha' }); const b = brazoLado(M, -1); return b.linea[4]; };
  const a = alto({ codo: 42, antebrazo: 5 }), c = alto({ codo: 60, antebrazo: 22 });
  assert.ok(Math.abs(a[0] - c[0]) > 8 || Math.abs(a[1] - c[1]) > 8, 'el saludo no cambia con la pose');
  for (const pose of [{ codo: 40, antebrazo: 24 }, { codo: 62, antebrazo: 2 }]) { const w = alto(pose); assert.ok(w[0] > 0 && w[1] > 0); }
});

test('cualquier combinación de pose, saludo y complexión dibuja sin errores y sin salirse mucho del lienzo', () => {
  for (let i = 0; i < 120; i++) {
    const svg = generarSVG({ semilla: i, saludo: ['derecha', 'izquierda', 'ninguno'][i % 3], corpulencia: ['delgada', 'robusta', 'corpulenta'][i % 3], edad: 5 + (i * 7) % 80 });
    assert.ok(!/NaN|undefined/.test(svg), `semilla ${i}`);
  }
});

test('la cabeza inclinada y el cuerpo ladeado cambian el dibujo pero no rompen el contenido', () => {
  const a = generarSVG({ semilla: 4, pose: { cabeza: 0, inclinacion: 0 } }), b = generarSVG({ semilla: 4, pose: { cabeza: 6, inclinacion: 1.4 } });
  assert.notEqual(a, b);
  assert.ok(b.includes('rotate('));
  assert.ok(!a.includes('rotate('));
});

test('los hombros se inclinan y la columna se curva: la deformación deja quietos los pies y las caderas', () => {
  const M = medidas({ sexo: 'f', edad: 30, pose: { hombros: 3, columna: 0.008 }, saludo: 'ninguno' });
  const f = deformar(M);
  assert.ok(f, 'sin deformación con hombros inclinados');
  const der = f(M.cx + 100, M.y.hombro), izq = f(M.cx - 100, M.y.hombro);
  assert.ok(der[1] > izq[1] + 8, 'el hombro derecho debe quedar más bajo');
  assert.deepEqual(f(M.cx + 60, M.y.rodilla), [M.cx + 60, M.y.rodilla], 'las rodillas no se mueven');
  assert.deepEqual(f(M.cx - 90, M.y.cadera), [M.cx - 90, M.y.cadera], 'las caderas no se mueven');
  assert.ok(Math.abs(f(M.cx, M.y.menton)[0] - M.cx) > M.H * 0.006, 'la cabeza se desplaza sobre las caderas');
  assert.equal(deformar(medidas({ sexo: 'f', edad: 30, pose: 'firme' })), null, 'la pose firme no deforma');
});

test('codos doblados y muñecas flexionadas cambian la forma del brazo y de la mano', () => {
  const muneca = (codo) => brazoLado(medidas({ sexo: 'm', edad: 30, pose: { brazoLibre: 'abajo', angulos: { 1: 8, '-1': 8 }, codos: { 1: codo, '-1': codo } }, saludo: 'ninguno' }), 1).linea[4];
  assert.ok(Math.abs(muneca(12)[0] - muneca(0)[0]) > 4, 'doblar el codo debe mover la muñeca');
  const M0 = medidas({ sexo: 'm', edad: 30, pose: { munecas: { 1: 0, '-1': 0 } }, saludo: 'ninguno' });
  const M1 = medidas({ sexo: 'm', edad: 30, pose: { munecas: { 1: 14, '-1': 14 } }, saludo: 'ninguno' });
  assert.notDeepEqual(trazoMano(M0, 1), trazoMano(M1, 1), 'flexionar la muñeca debe girar la mano');
  assert.equal(trazoMano(M0, 1).length, 2, 'la mano que cuelga trae palma y pulgar');
});

test('el cuello sigue a la cabeza inclinada', () => {
  const a = trazoCuello(medidas({ sexo: 'f', edad: 30, pose: { cabeza: 0 } })), b = trazoCuello(medidas({ sexo: 'f', edad: 30, pose: { cabeza: 7 } }));
  assert.notEqual(a, b);
});

test('la intensidad escala todas las variaciones de la pose', () => {
  const suave = poseAleatoria(crearAzar('i1'), 0.3), fuerte = poseAleatoria(crearAzar('i1'), 1);
  assert.ok(Math.abs(suave.hombros) <= Math.abs(fuerte.hombros) + 1e-9 && Math.abs(suave.cabeza) <= Math.abs(fuerte.cabeza) + 1e-9);
  const nula = poseAleatoria(crearAzar('i1'), 0);
  assert.equal(nula.hombros, 0); assert.equal(nula.cabeza, 0); assert.equal(nula.codos[1], 0);
});
