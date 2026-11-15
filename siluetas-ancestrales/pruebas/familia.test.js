// Árbol de ascendencia: estructura, coherencia de fechas y etnias, dibujo y errores.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarFamilia, nombreDeRuta } from '../src/index.js';

const base = { semilla: 4, anio: 1985, edad: 30, sexo: 'f', detalle: 'minimo' };
const porRuta = (f) => Object.fromEntries(f.personas.map((p) => [p.ruta, p]));

test('un árbol de n generaciones trae 2^n - 1 personas, con el padre varón y la madre mujer en cada pareja', () => {
  for (const n of [1, 2, 3, 4]) {
    const f = generarFamilia({ ...base, generaciones: n });
    assert.equal(f.personas.length, 2 ** n - 1, `generaciones ${n}`);
    for (const p of f.personas) {
      assert.ok(p.ruta.length < n);
      assert.equal(p.generacion, p.ruta.length);
      if (p.ruta) assert.equal(p.sexo, p.ruta.endsWith('p') ? 'm' : 'f', p.ruta);
    }
  }
  assert.equal(generarFamilia({ ...base, sexo: 'm', generaciones: 1 }).personas[0].sexo, 'm');
});

test('las fechas son coherentes: cada padre nace al menos 18 años antes que su hijo y se retrata a los 35', () => {
  const f = generarFamilia({ ...base, generaciones: 4 });
  const r = porRuta(f);
  assert.equal(r[''].nacimiento, 1955);
  assert.equal(r[''].anio, 1985);
  for (const p of f.personas.filter((q) => q.ruta)) {
    const hijo = r[p.ruta.slice(0, -1)];
    assert.ok(hijo.nacimiento - p.nacimiento >= 18 && hijo.nacimiento - p.nacimiento <= 40, `${p.ruta}: ${hijo.nacimiento - p.nacimiento} años`);
    assert.equal(p.anio, p.nacimiento + 35);
    assert.equal(p.edad, 35);
  }
  // En promedio el padre es mayor que la madre.
  const dif = (suf) => f.personas.filter((p) => p.ruta.endsWith(suf)).map((p) => r[p.ruta.slice(0, -1)].nacimiento - p.nacimiento);
  assert.ok(dif('p').reduce((a, b) => a + b) / dif('p').length > dif('m').reduce((a, b) => a + b) / dif('m').length);
});

test('el ancestro que falta en el linaje hereda la etnia de su hijo; el que se nombra usa la suya', () => {
  const f = generarFamilia({ ...base, generaciones: 3, etnia: 'mapuche', linaje: { padre: { etnia: 'vasco' }, madre: { etnia: 'aymara', madre: { etnia: 'maori' } } } });
  const id = (p) => p.etnia.map((e) => e.id).join('+');
  const r = porRuta(f);
  assert.equal(id(r['']), 'mapuche');
  assert.equal(id(r.p), 'vasco');
  assert.equal(id(r.pp), 'vasco'); // hereda
  assert.equal(id(r.pm), 'vasco');
  assert.equal(id(r.m), 'aymara');
  assert.equal(id(r.mp), 'aymara');
  assert.equal(id(r.mm), 'maori');
  for (const p of f.personas) assert.ok(!p.persona.avisos.some((a) => /otro pueblo/.test(a)), `${p.ruta}: ${p.persona.avisos.join('|')}`);
});

test('mismas opciones, mismo árbol; otra semilla, otro árbol', () => {
  const a = generarFamilia({ ...base, generaciones: 3 }).svg;
  assert.equal(generarFamilia({ ...base, generaciones: 3 }).svg, a);
  assert.notEqual(generarFamilia({ ...base, generaciones: 3, semilla: 5 }).svg, a);
});

test('el SVG trae una figura por persona, ids únicos, textos y ningún NaN', () => {
  const f = generarFamilia({ ...base, generaciones: 3, etnia: 'mapuche' });
  assert.match(f.svg, /^<svg xmlns="http:\/\/www.w3.org\/2000\/svg" viewBox="0 0 600 \d+"/);
  assert.ok(f.svg.endsWith('</svg>'));
  assert.equal((f.svg.match(/<svg /g) ?? []).length, f.personas.length + 1);
  assert.ok(!/NaN|undefined|Infinity/.test(f.svg));
  const ids = [...f.svg.matchAll(/ id="([^"]+)"/g)].map((m) => m[1]);
  assert.equal(new Set(ids).size, ids.length, 'ids repetidos entre figuras');
  for (const t of ['Padre', 'Madre', 'Abuelo paterno', 'Abuela materna', 'Abuelos', 'Mapuche']) assert.ok(f.svg.includes(t), t);
  assert.match(f.titulo, /3 generaciones/);
});

test('el nombre de un nodo reemplaza al rol y se escapa', () => {
  const f = generarFamilia({ ...base, generaciones: 2, linaje: { nombre: 'Ana <b>&', padre: { nombre: 'Don José' } } });
  assert.equal(f.personas[0].rol, 'Ana <b>&');
  assert.match(f.svg, /Ana &lt;b&gt;&amp;/);
  assert.ok(!f.svg.includes('<b>'));
  assert.match(f.svg, /Don José/);
});

test('un saludo se aplica a todos y por defecto cada uno saluda con la derecha', () => {
  assert.equal((generarFamilia({ ...base, generaciones: 2, saludo: 'ninguno' }).svg.match(/data-saludo/g) ?? []).length, 0);
  assert.equal((generarFamilia({ ...base, generaciones: 2 }).svg.match(/data-saludo="derecha"/g) ?? []).length, 3);
});

test('nombres de lugar del árbol', () => {
  assert.equal(nombreDeRuta(''), 'Persona');
  assert.equal(nombreDeRuta('pm'), 'Abuela paterna');
  assert.equal(nombreDeRuta('mpp'), 'Bisabuelo materno, paterno');
  assert.equal(nombreDeRuta('pmm'), 'Bisabuela paterno, materno');
});

test('los errores dicen qué está mal y dónde', () => {
  assert.throws(() => generarFamilia({ generaciones: 0 }), /generaciones no válido.*1 a 4/);
  assert.throws(() => generarFamilia({ generaciones: 5 }), /generaciones no válido/);
  assert.throws(() => generarFamilia({ generaciones: 2.5 }), /generaciones no válido/);
  assert.throws(() => generarFamilia({ anio: NaN }), /anio no válido/);
  assert.throws(() => generarFamilia({ detalle: 'enorme' }), /detalle no válido/);
  assert.throws(() => generarFamilia({ linaje: { abuelo: {} } }), /clave desconocida «abuelo»/);
  assert.throws(() => generarFamilia({ linaje: [] }), /debe ser un objeto/);
  assert.throws(() => generarFamilia({ linaje: { nombre: 3 } }), /«nombre» debe ser un texto/);
  assert.throws(() => generarFamilia({ generaciones: 1, linaje: { padre: {} } }), /solo llega a 1 generaciones/);
  assert.throws(() => generarFamilia({ generaciones: 2, linaje: { padre: { madre: {} } } }), /Abuelo paterno|Padre/);
  assert.throws(() => generarFamilia({ generaciones: 2, linaje: { madre: { etnia: 'atlante' } } }), /Madre: Etnia desconocida: atlante/);
  assert.throws(() => generarFamilia({ sexo: 'z' }), /Sexo no válido/);
});
