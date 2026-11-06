import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  generarPersona, generarSVG, generarPaleta, generarRopaNueva, generarPersonaConRopaNueva, buscarAtuendos, listarCatalogo,
  ATUENDOS, renderizar,
} from '../src/index.js';
import { esHex } from '../src/util.js';

test('misma semilla, mismo resultado; semillas distintas, personas distintas', () => {
  assert.equal(generarSVG({ semilla: 7 }), generarSVG({ semilla: 7 }));
  assert.equal(generarSVG({ semilla: 'abuela' }), generarSVG({ semilla: 'abuela' }));
  const distintos = new Set(Array.from({ length: 60 }, (_, i) => generarSVG({ semilla: i })));
  assert.equal(distintos.size, 60);
});

test('los filtros de región, año, sexo y clase se respetan', () => {
  for (let i = 0; i < 40; i++) {
    const p = generarPersona({ semilla: i, region: 'andes', anio: 1500, sexo: 'f' });
    const a = ATUENDOS.find((x) => x.id === p.atuendo);
    assert.equal(a.region, 'andes');
    assert.equal(a.sexo, 'f');
    assert.ok(a.epoca[0] <= 1500 && a.epoca[1] >= 1500, a.id);
    assert.equal(p.sexo, 'f');
  }
  assert.ok(buscarAtuendos({ region: 'roma', sexo: 'm', clase: 'alta' }).every((a) => a.region === 'roma' && a.clase === 'alta'));
});

test('si los filtros no encuentran nada se aflojan, y un atuendo desconocido falla', () => {
  const p = generarPersona({ semilla: 1, region: 'rapa-nui', anio: 1980, sexo: 'm', clase: 'alta' });
  assert.ok(p.atuendo);
  assert.throws(() => generarPersona({ atuendo: 'no-existe' }), /Atuendo desconocido/);
});

test('la edad cambia las proporciones: un niño es más bajo y de cabeza proporcionalmente mayor', async () => {
  const { medidas } = await import('../src/cuerpo.js');
  const nino = medidas({ sexo: 'm', edad: 5 }), adulto = medidas({ sexo: 'm', edad: 30 });
  assert.ok(nino.H < adulto.H * 0.7);
  assert.ok((nino.y.menton - nino.top) / nino.H > (adulto.y.menton - adulto.top) / adulto.H * 1.2);
});

test('las medidas están ordenadas de arriba abajo y son positivas', async () => {
  const { medidas } = await import('../src/cuerpo.js');
  for (const sexo of ['f', 'm']) for (const edad of [2, 6, 12, 18, 40, 85]) for (const corpulencia of ['delgada', 'media', 'robusta', 'corpulenta']) {
    const M = medidas({ sexo, edad, corpulencia });
    const orden = ['cabeza', 'menton', 'hombro', 'axila', 'cintura', 'cadera', 'entrepierna', 'rodilla', 'tobillo', 'suela'];
    for (let i = 1; i < orden.length; i++) assert.ok(M.y[orden[i]] > M.y[orden[i - 1]], `${sexo} ${edad} ${corpulencia}: ${orden[i]} <= ${orden[i - 1]}`);
    for (const [k, v] of Object.entries(M.w)) assert.ok(v > 0 && Number.isFinite(v), `${k}`);
    assert.ok(M.top >= 0 && M.suelo <= 1000);
  }
});

test('la paleta devuelve cuatro colores hex para cada esquema, y es determinista', () => {
  for (const esquema of ['analogo', 'complementario', 'triada', 'monocromo', 'tierra', 'festivo']) {
    const p = generarPaleta({ esquema, semilla: 5 });
    assert.deepEqual(Object.keys(p), ['A', 'B', 'C', 'D']);
    for (const c of Object.values(p)) assert.ok(esHex(c), `${esquema}: ${c}`);
    assert.deepEqual(p, generarPaleta({ esquema, semilla: 5 }));
  }
  assert.notDeepEqual(generarPaleta({ semilla: 1 }), generarPaleta({ semilla: 2 }));
  assert.equal(generarPaleta({ esquema: 'monocromo', semilla: 1, base: '#2255aa' }).A.length, 7);
});

test('la ropa nueva es válida, distinta según semilla y estilo, y se dibuja', () => {
  const vistas = new Set();
  for (let i = 0; i < 150; i++) {
    for (const estilo of ['sobrio', 'trabajo', 'festivo', 'ceremonial']) {
      const r = generarRopaNueva({ semilla: i, sexo: i % 2 ? 'f' : 'm', estilo });
      assert.ok(r.prendas.some((p) => p.ranura === 'torso'));
      assert.ok(r.prendas.some((p) => p.ranura === 'calzado'));
      for (const p of r.prendas) for (const c of Object.values(p.colores)) assert.ok(esHex(c), `${p.id}: ${c}`);
      if (estilo === 'sobrio') vistas.add(JSON.stringify(r.prendas.map((p) => [p.plantilla, p.params])));
    }
  }
  assert.ok(vistas.size > 120, `poca variedad: ${vistas.size}`);
  for (let i = 0; i < 80; i++) {
    const p = generarPersonaConRopaNueva({ semilla: i, estilo: ['sobrio', 'trabajo', 'festivo', 'ceremonial'][i % 4], edad: 10 + (i % 70) });
    const svg = renderizar(p);
    assert.ok(!/NaN|undefined/.test(svg), `ropa nueva ${i}`);
  }
});

test('se puede dar una paleta propia, un calzado y piel o cabello a mano', () => {
  const pal = { A: '#112233', B: '#445566', C: '#778899', D: '#aabbcc' };
  const p = generarPersona({ semilla: 3, atuendo: 'huaso', paleta: pal, piel: '#aa7755', cabello: '#101010', calzado: 'sandalias-cuero', edad: 40 });
  assert.equal(p.piel, '#aa7755');
  assert.equal(p.cabello.color, '#101010');
  assert.ok(p.prendas.some((x) => x.id === 'sandalias-cuero'));
  assert.ok(!p.prendas.some((x) => x.id === 'botas-cuero'));
  assert.ok(generarSVG({ semilla: 3, atuendo: 'huaso', paleta: pal }).includes('#112233'));
});

test('los niños no llevan barba y los mayores a veces tienen canas', () => {
  for (let i = 0; i < 60; i++) assert.equal(generarPersona({ semilla: i, sexo: 'm', edad: 9, atuendo: 'beduino' }).barba.estilo, 'ninguna');
  let canosos = 0;
  for (let i = 0; i < 60; i++) {
    const c = generarPersona({ semilla: i, edad: 82, piel: '#e6b894' }).cabello.color;
    if (c.slice(1, 3) >= '88') canosos++;
  }
  assert.ok(canosos > 30, `pocos con canas: ${canosos}`);
});

test('el resumen del catálogo coincide con los datos', () => {
  const r = listarCatalogo();
  assert.equal(r.atuendos, ATUENDOS.length);
  assert.ok(r.prendas >= 200 && r.tintes >= 80 && r.peinados >= 20 && r.tocados >= 25);
});

test('cientos de personas aleatorias se dibujan sin valores inválidos', () => {
  for (let i = 0; i < 600; i++) {
    const svg = generarSVG({ semilla: i * 7 + 1, detalle: ['minimo', 'medio', 'alto'][i % 3] });
    assert.ok(!/NaN|undefined/.test(svg), `semilla ${i * 7 + 1}`);
  }
});
