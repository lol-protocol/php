import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarPersona, generarSVG, ETNIAS, buscarAtuendos, PIELES } from '../src/index.js';
import { REGIONES, ATUENDOS_POR_ID } from '../src/datos/atuendos.js';
import { CABELLOS } from '../src/datos/colores.js';
import { PEINADOS } from '../src/cabeza.js';
import { medidas } from '../src/cuerpo.js';
import { luminancia } from '../src/util.js';

const media = (xs) => xs.reduce((a, b) => a + b, 0) / xs.length;
const muestra = (opc, n = 60) => Array.from({ length: n }, (_, i) => generarPersona({ ...opc, semilla: i + 1 }));

test('los datos de cada etnia son válidos', () => {
  assert.ok(ETNIAS.length >= 100, `etnias: ${ETNIAS.length}`);
  for (const e of ETNIAS) assert.ok(['alta', 'media', 'baja'].includes(e.confianza), `${e.id}: confianza`);
  assert.equal(new Set(ETNIAS.map((e) => e.id)).size, ETNIAS.length);
  for (const e of ETNIAS) {
    assert.match(e.id, /^[a-z0-9-]+$/);
    assert.ok(e.nombre && e.grupo, e.id);
    for (const r of e.regiones) assert.ok(r in REGIONES, `${e.id}: región ${r}`);
    for (const id of e.pieles) assert.ok(PIELES.some((p) => p.id === id), `${e.id}: piel ${id}`);
    for (const [id, w] of e.cabellos) { assert.ok(CABELLOS.some((c) => c.id === id), `${e.id}: cabello ${id}`); assert.ok(w > 0); }
    for (const id of e.peinados) assert.ok(id in PEINADOS, `${e.id}: peinado ${id}`);
    assert.ok(e.barba >= 0 && e.barba <= 1, e.id);
    const [f, m] = e.altura;
    assert.ok(f >= 140 && m <= 185 && m > f, `${e.id}: estatura ${f}/${m}`);
    assert.ok(buscarAtuendos({ regiones: e.regiones, prefijos: e.prefijos }).length > 0, `${e.id}: sin atuendos`);
  }
});

test('con una etnia se estiman todos los parámetros, para cualquier sexo, edad y año', () => {
  for (const e of ETNIAS) {
    for (const sexo of ['f', 'm']) for (const [edad, anio] of [[6, undefined], [34, 1850], [70, -300], [25, 1100]]) {
      const p = generarPersona({ semilla: 3, etnia: e.id, sexo, edad, anio });
      const svg = generarSVG({ semilla: 3, etnia: e.id, sexo, edad, anio });
      assert.ok(!/NaN|undefined/.test(svg), `${e.id} ${sexo} ${edad} ${anio}`);
      assert.deepEqual(p.etnia.map((x) => x.id), [e.id]);
      assert.ok(p.estimaciones.length >= 4, `${e.id}: pocas estimaciones`);
      // Si el atuendo no es de la región de la etnia, hay que decirlo.
      const delaRegion = e.regiones.includes(p.region);
      assert.ok(delaRegion || p.avisos.length > 0, `${e.id} ${sexo}: atuendo de otra región sin aviso (${p.atuendo})`);
    }
  }
});

test('la etnia empuja la región, el atuendo y respeta el año cuando hay trajes de esa época', () => {
  for (const p of muestra({ etnia: 'mapuche', sexo: 'f', edad: 30, anio: 1850 }, 20)) {
    assert.equal(p.region, 'mapuche');
    assert.deepEqual(p.avisos, []);
    const a = ATUENDOS_POR_ID[p.atuendo];
    assert.ok(a.epoca[0] <= 1850 && a.epoca[1] >= 1850);
  }
  const japones = muestra({ etnia: 'japones', anio: 1700 }, 20);
  assert.ok(japones.every((p) => p.atuendo.startsWith('japon-')));
  assert.ok(muestra({ etnia: 'maasai' }, 20).every((p) => p.atuendo.startsWith('maasai')));
});

test('si no hay traje de esa época se avisa y se elige el más cercano', () => {
  const p = generarPersona({ semilla: 2, etnia: 'mapuche', sexo: 'm', anio: -500 });
  assert.equal(p.region, 'mapuche');
  assert.ok(p.avisos.some((a) => /el año/.test(a)), p.avisos.join('|'));
  const z = generarPersona({ semilla: 2, etnia: 'zulu', sexo: 'f' });
  assert.ok(z.avisos.some((a) => /otro pueblo/.test(a)), 'falta aviso: no hay atuendo femenino zulú');
});

test('la estatura estimada sigue a la etnia, al sexo y a la edad', () => {
  const cm = (opc) => media(muestra(opc, 40).map((p) => medidas({ ...p.cuerpo }).cm));
  assert.ok(cm({ etnia: 'selknam', sexo: 'm', edad: 35 }) > cm({ etnia: 'aymara', sexo: 'm', edad: 35 }) + 8);
  assert.ok(cm({ etnia: 'europeo-del-norte', sexo: 'm', edad: 35 }) > cm({ etnia: 'europeo-del-norte', sexo: 'f', edad: 35 }) + 6);
  assert.ok(cm({ etnia: 'mapuche', sexo: 'm', edad: 8 }) < cm({ etnia: 'mapuche', sexo: 'm', edad: 35 }) - 25);
  // Antes del año 1000 la estimación es algo menor que hoy.
  assert.ok(cm({ etnia: 'romano-antiguo', sexo: 'm', edad: 35, anio: 100 }) < cm({ etnia: 'romano-antiguo', sexo: 'm', edad: 35, anio: 1990 }));
  // Una altura dada a mano manda.
  assert.ok(Math.abs(medidas(generarPersona({ semilla: 1, etnia: 'maasai', sexo: 'm', edad: 35, altura: 150 }).cuerpo).cm - 150) < 0.01);
});

test('el tono de piel y el cabello siguen a la etnia', () => {
  const lum = (opc) => media(muestra(opc, 40).map((p) => luminancia(p.piel)));
  assert.ok(lum({ etnia: 'europeo-del-norte', edad: 30 }) > lum({ etnia: 'africano-occidental', edad: 30 }) + 0.1);
  const rubios = muestra({ etnia: 'europeo-del-norte', edad: 25 }, 80).filter((p) => luminancia(p.cabello.color) > 0.18).length;
  const rubiosAy = muestra({ etnia: 'aymara', edad: 25 }, 80).filter((p) => luminancia(p.cabello.color) > 0.18).length;
  assert.ok(rubios > 25 && rubiosAy === 0, `claros: ${rubios} vs ${rubiosAy}`);
});

test('una mezcla promedia la piel y la primera etnia manda en la ropa', () => {
  const l = (e) => media(muestra({ etnia: e, edad: 30 }, 40).map((p) => luminancia(p.piel)));
  const a = l('europeo-del-norte'), b = l('africano-occidental'), m = l(['europeo-del-norte', 'africano-occidental']);
  assert.ok(m < a && m > b, `${b} < ${m} < ${a}`);
  const p = generarPersona({ semilla: 4, etnia: ['mapuche', 'espanol'], sexo: 'f', edad: 30, anio: 1800 });
  assert.equal(p.region, 'mapuche');
  assert.equal(p.etnia.length, 2);
  assert.deepEqual(generarPersona({ semilla: 4, etnia: 'mapuche,espanol', sexo: 'f', edad: 30, anio: 1800 }).atuendo, p.atuendo);
  assert.equal(generarPersona({ semilla: 4, etnia: { mapuche: 3, espanol: 1 }, sexo: 'f' }).etnia[0].peso, 0.75);
});

test('lo que se da a mano manda sobre la estimación y no se declara estimado', () => {
  const p = generarPersona({ semilla: 5, etnia: 'mapuche', sexo: 'm', edad: 40, piel: '#e6b894', cabello: '#101010', region: 'andes', corpulencia: 'robusta', altura: 170 });
  assert.equal(p.piel, '#e6b894');
  assert.equal(p.cabello.color, '#101010');
  assert.equal(p.region, 'andes');
  assert.equal(p.cuerpo.corpulencia, 'robusta');
  assert.ok(!p.estimaciones.some((t) => /^(Piel|Cabello|Estatura|Complexión)/.test(t)), p.estimaciones.join('|'));
});

test('sin etnia no hay estimaciones y una etnia desconocida falla con la lista', () => {
  assert.deepEqual(generarPersona({ semilla: 1 }).estimaciones, []);
  assert.throws(() => generarPersona({ etnia: 'marciano' }), /Etnia desconocida: marciano\. Disponibles: .*mapuche/);
});

test('la misma semilla y etnia dan siempre lo mismo', () => {
  const o = { semilla: 77, etnia: ['aymara', 'espanol'], sexo: 'f', edad: 52, anio: 1920 };
  assert.equal(generarSVG(o), generarSVG(o));
});

test('la confianza de una persona es la menor entre su atuendo y sus etnias, y las notas se propagan', () => {
  const p = generarPersona({ semilla: 1, etnia: 'himba', sexo: 'f' });
  assert.equal(p.confianza, 'baja');
  assert.ok(p.notas.some((n) => /otjize/.test(n)));
  const q = generarPersona({ semilla: 1, atuendo: 'roma-senador' });
  assert.equal(q.confianza, 'media');
  const m = generarPersona({ semilla: 1, etnia: ['mapuche', 'himba'], sexo: 'f' });
  assert.equal(m.confianza, 'baja', 'una mezcla con una etnia de confianza baja es de confianza baja');
});
