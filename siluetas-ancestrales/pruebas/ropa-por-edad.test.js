// La ropa depende de la edad: un niño no se viste con la armadura de un legionario ni un adulto con un traje infantil.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarPersona, buscarAtuendos, ATUENDOS } from '../src/index.js';
import { edadesDe } from '../src/generador.js';

const infantiles = ATUENDOS.filter((a) => a.edades && a.edades[1] <= 12);

test('hay atuendos infantiles de hombre y de mujer en varias regiones, todos de confianza baja y con nota', () => {
  assert.ok(infantiles.length >= 20, `infantiles: ${infantiles.length}`);
  for (const a of infantiles) {
    assert.deepEqual(a.edades, [2, 12], a.id);
    assert.equal(a.confianza, 'baja', a.id);
    assert.ok(a.nota.length > 30, a.id);
    assert.deepEqual(a.barbas, ['ninguna'], a.id);
  }
  for (const r of new Set(infantiles.map((a) => a.region))) {
    if (r === 'africa-oeste') assert.ok(infantiles.some((a) => a.region === r && a.sexo === 'f') && infantiles.some((a) => a.region === r && a.sexo === 'm'));
    else for (const sexo of ['f', 'm']) assert.ok(infantiles.some((a) => a.region === r && a.sexo === sexo), `${r} sin infantil ${sexo}`);
  }
});

test('un atuendo con prenda de adulto solo sale desde los 16 años y uno infantil no sale de adulto', () => {
  const legion = ATUENDOS.filter((a) => a.prendas.includes('lorica-segmentata'));
  assert.ok(legion.length > 0);
  for (const a of legion) assert.deepEqual(edadesDe(a), [16, 100], a.id);
  for (const a of buscarAtuendos({ edad: 8 })) assert.ok(!a.prendas.some((id) => ['lorica-segmentata', 'toga', 'habito-monje', 'sotana', 'stola'].includes(id)), a.id);
  for (const a of buscarAtuendos({ edad: 40 })) assert.ok(!(a.edades && a.edades[1] <= 12), `adulto con ${a.id}`);
  assert.ok(buscarAtuendos({ edad: 16 }).some((a) => a.prendas.includes('lorica-segmentata')));
});

test('hasta los 12 años se prefiere el traje infantil de la región; desde los 13 ya no', () => {
  for (const edad of [3, 8, 12]) {
    for (const sexo of ['f', 'm']) for (let semilla = 1; semilla <= 20; semilla++) {
      const p = generarPersona({ semilla, sexo, edad, region: 'roma' });
      assert.match(p.atuendo, /^roma-nin[oa]$/, `${edad}/${sexo}/${semilla}: ${p.atuendo}`);
    }
  }
  for (let semilla = 1; semilla <= 30; semilla++) assert.doesNotMatch(generarPersona({ semilla, sexo: 'm', edad: 13, region: 'roma' }).atuendo, /roma-nin/);
});

test('un niño de una región sin traje infantil usa uno de adulto sin prendas de adulto y sin avisos de más', () => {
  for (let semilla = 1; semilla <= 20; semilla++) {
    const p = generarPersona({ semilla, sexo: 'm', edad: 7, region: 'egipto' });
    assert.ok(!p.avisos.some((a) => /edad/.test(a)), p.avisos.join('|'));
    assert.ok(!p.prendas.some((q) => ['lorica-segmentata', 'toga', 'sotana'].includes(q.id)));
  }
});

test('un niño con etnia sigue usando los trajes de su pueblo (no se le sale de la etnia)', () => {
  for (const etnia of ['mapuche', 'maori', 'vasco']) {
    const p = generarPersona({ semilla: 2, etnia, edad: 7 });
    assert.equal(p.etnia[0].id, etnia);
    assert.ok(!/nin[oa]$/.test(p.atuendo), `${etnia}: ${p.atuendo}`); // los trajes infantiles genéricos no son de ningún pueblo
    assert.ok(ATUENDOS.find((a) => a.id === p.atuendo).region === p.region);
  }
});

test('sin edad los resultados de buscarAtuendos no cambian', () => {
  assert.equal(buscarAtuendos({ region: 'roma' }).length, ATUENDOS.filter((a) => a.region === 'roma').length);
});

test('la ropa escolar (colegiales, estudiantes y adolescentes) solo sale de los 6 a los 18 años', () => {
  const escolares = ATUENDOS.filter((a) => /colegi|estudiante|adolescente/.test(a.id));
  assert.ok(escolares.length >= 7, `escolares: ${escolares.length}`);
  for (const a of escolares) assert.deepEqual(a.edades, [6, 18], a.id);
  const ids = new Set(escolares.map((a) => a.id));
  // Una persona de 35 años, de cualquier región y época, no se viste de colegial.
  for (const region of ['chile-rural', 'europa-xix', 'asia-este', 'norteamerica-moderna']) {
    for (let semilla = 1; semilla <= 60; semilla++) {
      for (const sexo of ['f', 'm']) {
        const p = generarPersona({ semilla, sexo, edad: 35, region, anio: 1955 });
        assert.ok(!ids.has(p.atuendo), `${region}/${sexo}/${semilla}: ${p.atuendo}`);
      }
    }
  }
  // Una adolescente sí puede.
  assert.ok(buscarAtuendos({ region: 'europa-xix', sexo: 'f', anio: 1955, edad: 14 }).some((a) => ids.has(a.id)));
  assert.ok(!buscarAtuendos({ region: 'europa-xix', sexo: 'f', anio: 1955, edad: 4 }).some((a) => ids.has(a.id)));
});
