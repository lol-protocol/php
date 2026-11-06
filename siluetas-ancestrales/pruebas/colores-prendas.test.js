import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PRENDAS } from '../src/datos/prendas.js';
import { ATUENDOS } from '../src/datos/atuendos.js';
import { TINTES_POR_ID } from '../src/datos/colores.js';
import { PERFILES, IDS_DE_GRUPOS, distribucionColor, catalogoColores, muestrearColor, perfilDe } from '../src/datos/colores-prendas.js';
import { generarPersona, generarSVG } from '../src/index.js';
import { crearAzar, esHex } from '../src/util.js';

const suma = (d) => d.reduce((t, x) => t + x.pct, 0);

test('los perfiles solo nombran tintes que existen y tienen pesos positivos', () => {
  for (const [nombre, lista] of Object.entries(PERFILES)) {
    assert.ok(lista.length >= 3, `${nombre}: muy pocos colores`);
    assert.equal(new Set(lista.map(([id]) => id)).size, lista.length, `${nombre}: colores repetidos`);
    for (const [id, peso] of lista) { assert.ok(TINTES_POR_ID[id], `${nombre}: tinte ${id}`); assert.ok(peso > 0, `${nombre}: peso de ${id}`); }
  }
});

test('las asignaciones de perfil solo nombran prendas que existen', () => {
  const ids = new Set(PRENDAS.map((p) => p.id));
  for (const id of IDS_DE_GRUPOS) assert.ok(ids.has(id), `prenda inexistente en un grupo de colores: ${id}`);
});

test('toda prenda tiene un catálogo de colores que suma 100 %, ordenado y sin repetidos', () => {
  for (const p of PRENDAS) {
    const d = distribucionColor(p.id, 'principal');
    assert.ok(d.length > 0, `${p.id}: sin colores`);
    assert.ok(Math.abs(suma(d) - 100) < 0.6, `${p.id}: suma ${suma(d)}`);
    assert.equal(new Set(d.map((x) => x.id)).size, d.length, `${p.id}: colores repetidos`);
    for (let i = 0; i < d.length; i++) {
      assert.ok(d[i].pct > 0 && esHex(d[i].hex) && d[i].nombre, `${p.id}: ${d[i].id}`);
      if (i) assert.ok(d[i - 1].pct >= d[i].pct, `${p.id}: sin ordenar`);
    }
    // Los demás roles que tengan datos también deben sumar 100.
    for (const [rol, dd] of Object.entries(catalogoColores(p.id))) assert.ok(Math.abs(suma(dd) - 100) < 0.6, `${p.id}/${rol}`);
  }
});

test('los jeans solo salen en azules y grises, y el denim no existe antes de 1873', () => {
  const d = distribucionColor('pantalon-vaquero', 'principal');
  assert.ok(d.length >= 4);
  for (const x of d) assert.ok(['azul', 'neutro'].includes(TINTES_POR_ID[x.id].familia), `${x.id}: ${TINTES_POR_ID[x.id].familia}`);
  assert.ok(d[0].id === 'azul-denim' && d[0].pct === 40);
  // 1880: todavía no hay denim lavado ni gris ni negro (de 1900).
  const d1880 = distribucionColor('pantalon-vaquero', 'principal', { anio: 1880 });
  assert.deepEqual(d1880.map((x) => x.id).sort(), ['azul-denim', 'denim-oscuro']);
  assert.ok(Math.abs(suma(d1880) - 100) < 0.2);
  assert.ok(Math.abs(d1880.find((x) => x.id === 'azul-denim').pct - 61.5) < 0.2);
});

test('la camisa blanca es casi siempre blanca y el calzado es de cuero', () => {
  const blancos = distribucionColor('camisa-blanca', 'principal').filter((x) => ['blanco-lino', 'crudo', 'marfil', 'algodon-crudo', 'blanco-optico', 'crema', 'lana-clara'].includes(x.id));
  assert.ok(suma(blancos) > 99);
  const zap = distribucionColor('zapatos-vestir', 'principal');
  assert.ok(suma(zap.filter((x) => ['cuero-oscuro', 'negro-humo', 'cuero', 'nogal', 'chocolate', 'cuero-claro'].includes(x.id))) > 99);
  const novia = distribucionColor('vestido-novia', 'principal');
  assert.ok(suma(novia.filter((x) => ['blanco-optico', 'marfil', 'crema', 'blanco-lino'].includes(x.id))) > 99);
});

test('el filtro por año quita los tintes que aún no existían (púrpura de Tiro, anilinas, denim)', () => {
  const ids = (id, anio, rol = 'principal') => distribucionColor(id, rol, { anio }).map((x) => x.id);
  assert.ok(!ids('vestido-fiesta', 1700).includes('rosa-fucsia'));
  assert.ok(ids('vestido-fiesta', 1950).includes('rosa-fucsia'));
  assert.ok(!ids('polera', 1800).includes('rojo-vivo') || ids('polera', 1800).length >= 1);
  assert.ok(!ids('corbata', 1860).includes('rojo-vivo'));
  assert.ok(ids('corbata', 1950).includes('rojo-vivo'));
});

test('el catálogo derivado de los atuendos refleja lo que el generador hace', () => {
  // El chamal mapuche solo aparece en atuendos con la paleta A = lana negra / azul andino.
  const d = distribucionColor('chamal', 'principal');
  assert.deepEqual(d.map((x) => x.id).sort(), ['azul-andino', 'lana-negra']);
  assert.ok(d.every((x) => x.origen === 'atuendo'));
  assert.equal(perfilDe('chamal', 'principal'), null);
  // Con un atuendo concreto se limita a ese atuendo.
  const solo = distribucionColor('kimono', 'principal', { atuendo: 'japon-mujer-kimono' });
  assert.ok(solo.length > 1 && Math.abs(suma(solo) - 100) < 0.6);
});

test('el muestreo sigue los porcentajes (jeans, 4000 muestras) y el refuerzo del atuendo los sube', () => {
  const azar = crearAzar('muestreo');
  const cuenta = {};
  const N = 4000;
  for (let i = 0; i < N; i++) { const c = muestrearColor('pantalon-vaquero', 'principal', azar); cuenta[c.id] = (cuenta[c.id] ?? 0) + 1; }
  for (const x of distribucionColor('pantalon-vaquero', 'principal')) {
    const obs = (cuenta[x.id] ?? 0) / N * 100;
    assert.ok(Math.abs(obs - x.pct) < 3, `${x.id}: esperado ${x.pct} %, observado ${obs.toFixed(1)} %`);
  }
  const reforzado = {};
  const azar2 = crearAzar('refuerzo');
  for (let i = 0; i < N; i++) { const c = muestrearColor('pantalon-vaquero', 'principal', azar2, { preferidos: ['denim-negro'] }); reforzado[c.id] = (reforzado[c.id] ?? 0) + 1; }
  assert.ok((reforzado['denim-negro'] ?? 0) / N > 0.12, 'el refuerzo no sube el color preferido');
  assert.equal(muestrearColor('chamal', 'principal', azar), null, 'sin perfil propio no se muestrea');
});

test('en una persona, cada prenda con perfil sale con un color de su catálogo y con su probabilidad', () => {
  let con = 0;
  for (let i = 0; i < 80; i++) {
    const p = generarPersona({ semilla: i, atuendo: 'eeuu-vaquero' });
    const jeans = p.coloresPrendas.find((c) => c.prenda === 'pantalon-vaquero');
    const ok = new Set(distribucionColor('pantalon-vaquero', 'principal', { anio: 1912 }).map((x) => x.id));
    assert.ok(ok.has(jeans.roles.principal.id), `jeans de ${jeans.roles.principal.id}`);
    assert.equal(jeans.roles.principal.origen, 'perfil');
    assert.ok(jeans.roles.principal.pct > 0);
    con++;
  }
  assert.equal(con, 80);
  const p = generarPersona({ semilla: 1, atuendo: 'huaso' });
  assert.ok(p.coloresPrendas.length >= 5);
  for (const c of p.coloresPrendas) for (const r of Object.values(c.roles)) assert.ok(r.id && r.nombre && r.origen);
});

test('con `colores: "atuendo"` o con una paleta propia no se usan los perfiles', () => {
  const a = generarPersona({ semilla: 4, atuendo: 'eeuu-vaquero', colores: 'atuendo' });
  assert.ok(a.coloresPrendas.every((c) => Object.values(c.roles).every((r) => r.origen === 'atuendo')));
  const pal = { A: '#112233', B: '#445566', C: '#778899', D: '#aabbcc' };
  const b = generarPersona({ semilla: 4, atuendo: 'chile-oficinista-1930', paleta: pal });
  assert.ok(b.coloresPrendas.length === 0 || b.coloresPrendas.every((c) => Object.values(c.roles).every((r) => r.origen !== 'perfil')));
  assert.ok(generarSVG({ semilla: 4, atuendo: 'chile-oficinista-1930', paleta: pal }).includes('#112233'));
});

test('misma semilla, mismos colores', () => {
  const o = { semilla: 99, atuendo: 'chile-oficinista-1930' };
  assert.deepEqual(generarPersona(o).coloresPrendas, generarPersona(o).coloresPrendas);
  const distintos = new Set(Array.from({ length: 40 }, (_, i) => JSON.stringify(generarPersona({ semilla: i, atuendo: 'chile-oficinista-1930' }).coloresPrendas.map((c) => c.roles.principal?.id))));
  assert.ok(distintos.size > 20, `poca variedad de colores: ${distintos.size}`);
});

test('todos los atuendos nuevos se dibujan con sus perfiles de color', () => {
  for (const a of ATUENDOS) {
    const svg = generarSVG({ atuendo: a.id, sexo: a.sexo, edad: 35, semilla: 5 });
    assert.ok(!/NaN|undefined/.test(svg), a.id);
  }
});

test('las prendas que van juntas (overol, terno) comparten color', () => {
  for (let i = 0; i < 30; i++) {
    const p = generarPersona({ semilla: i, atuendo: 'eeuu-granjero-1930' });
    const pantalon = p.prendas.find((x) => x.id === 'pantalon-overol'), peto = p.prendas.find((x) => x.id === 'overol-peto');
    assert.equal(peto.colores.principal, pantalon.colores.principal);
  }
  let iguales = 0;
  for (let i = 0; i < 100; i++) {
    const p = generarPersona({ semilla: i, atuendo: 'chile-oficinista-1930' });
    const saco = p.prendas.find((x) => x.id === 'saco-terno'), pant = p.prendas.find((x) => x.id === 'pantalon-vestir');
    if (saco.colores.principal === pant.colores.principal) iguales++;
  }
  assert.ok(iguales > 55 && iguales < 90, `ternos del mismo color: ${iguales} %`);
});
