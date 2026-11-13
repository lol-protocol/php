'use strict';
/*
 * Pruebas del port a JavaScript: todo contra datos/vectores.json (lo que responde consultas.py), más los
 * ejemplos del README y los casos de error. Uso, desde la carpeta del proyecto: node --test 'js/*.test.js'
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const Mortalidad = require('./mortalidad.js');

const DATOS = path.join(__dirname, '..', 'datos');

// CSV de RFC 4180 lo bastante completo para estos archivos (comillas, comas y comillas dobles dentro de un campo).
function leerCsv(nombre) {
  const texto = fs.readFileSync(path.join(DATOS, nombre + '.csv'), 'utf8').replace(/\r\n/g, '\n');
  const filas = [];
  let fila = [];
  let campo = '';
  let entreComillas = false;
  for (let i = 0; i < texto.length; i++) {
    const c = texto[i];
    if (entreComillas) {
      if (c === '"' && texto[i + 1] === '"') { campo += '"'; i++; }
      else if (c === '"') entreComillas = false;
      else campo += c;
    } else if (c === '"') entreComillas = true;
    else if (c === ',') { fila.push(campo); campo = ''; }
    else if (c === '\n') { fila.push(campo); filas.push(fila); fila = []; campo = ''; }
    else campo += c;
  }
  if (campo !== '' || fila.length) { fila.push(campo); filas.push(fila); }
  return { columnas: filas[0], filas: filas.slice(1) };
}

const tablas = {};
for (const n of ['paises', 'causas_modernas', 'causas_historicas', 'epidemias', 'hitos_registro', 'londres_muertes', 'esperanza_vida']) {
  tablas[n] = leerCsv(n);
}
const m = Mortalidad.crear(tablas);
const vectores = JSON.parse(fs.readFileSync(path.join(DATOS, 'vectores.json'), 'utf8'));

const casi = (esperado, obtenido, donde) => {
  if (esperado === null || obtenido === null) assert.equal(obtenido, esperado, donde);
  else assert.ok(Math.abs(esperado - obtenido) < 1e-9, `${donde}: esperado ${esperado}, obtenido ${obtenido}`);
};

test('el lector de CSV lee lo que dice el archivo', () => {
  assert.equal(tablas.paises.filas.length, 208);
  assert.deepEqual(tablas.epidemias.columnas.slice(0, 3), ['id', 'nombre', 'agente']);
  // Una nota con comas y comillas queda en un solo campo.
  assert.ok(tablas.epidemias.filas.every((f) => f.length === tablas.epidemias.columnas.length));
});

test('causa_moderna: todo el glosario, en mayúsculas y los casos especiales', () => {
  assert.ok(vectores.causa_moderna.length > 200);
  for (const [termino, idioma, esperado] of vectores.causa_moderna) {
    const obtenido = m.causaModerna(termino, idioma).map((r) => [r.causa_id, r.confianza, r.cie10]);
    assert.deepEqual(obtenido, esperado, `${termino} (${idioma})`);
  }
});

test('epidemias_en: por año, región y país', () => {
  assert.ok(vectores.epidemias_en.length > 400);
  for (const [anio, region, pais, ids] of vectores.epidemias_en) {
    assert.deepEqual(m.epidemiasEn(anio, region, pais).map((e) => e.id), ids, JSON.stringify([anio, region, pais]));
  }
});

test('region_de y hitos_de', () => {
  for (const [codigo, region] of vectores.region_de) assert.equal(m.regionDe(codigo), region, codigo);
  for (const [codigo, esperado] of vectores.hitos_de) {
    assert.deepEqual(m.hitosDe(codigo).map((h) => [h.tipo, parseInt(h.desde, 10)]), esperado, codigo);
  }
});

test('esperanza de vida y supervivencia, con los «no hay dato»', () => {
  assert.ok(vectores.esperanza.length > 500);
  let conDato = 0;
  for (const [codigo, anio, edad, sexo, esperanza, supervivencia] of vectores.esperanza) {
    casi(esperanza, m.esperanzaRestante(codigo, anio, edad, sexo), `esperanza ${codigo} ${anio} ${edad} ${sexo}`);
    casi(supervivencia, m.supervivencia(codigo, anio, edad, sexo), `supervivencia ${codigo} ${anio} ${edad} ${sexo}`);
    if (esperanza !== null) conDato++;
  }
  assert.ok(conDato > 100);
});

test('muertes en Londres y su exceso', () => {
  for (const [anio, totales, viruela, exceso] of vectores.londres) {
    const obtenido = m.muertesLondres(anio);
    assert.equal(obtenido === null ? null : obtenido.totales, totales, `totales ${anio}`);
    assert.equal(obtenido === null ? null : obtenido.viruela, viruela, `viruela ${anio}`);
    casi(exceso, m.excesoDeMuertesLondres(anio), `exceso ${anio}`);
  }
});

test('los ejemplos del README', () => {
  casi(36.2, m.esperanzaRestante('SE', 1805), 'Suecia al nacer');
  casi(47.3, m.esperanzaRestante('SE', 1805, 5), 'Suecia a los 5');
  casi(0.687, m.supervivencia('SE', 1805), 'supervivencia a los 5');
  assert.deepEqual(m.muertesLondres(1665), { totales: 97306, viruela: 655 });
  assert.equal(m.causaModerna('Apoplejía', 'spa')[0].causa_id, 'ictus');
  assert.equal(m.epidemiasEn(1348, null, 'IT')[0].id, 'peste_negra');
  assert.deepEqual(m.epidemiasEn(1348, null, 'CL'), []);
});

test('normalizar', () => {
  assert.equal(Mortalidad.normalizar('  Schlagfluß '), 'schlagfluss');
  assert.equal(Mortalidad.normalizar('CÓLERA   morbo'), 'colera morbo');
  assert.equal(Mortalidad.normalizar('اَلسُّلّ'), Mortalidad.normalizar('السل'));
  assert.equal(Mortalidad.normalizar('ΓΉΡΑΣ'), Mortalidad.normalizar('γήρας'));
});

test('errores y valores por omisión', () => {
  assert.throws(() => m.causaModerna('tisis', 'es'), RangeError);
  assert.throws(() => m.epidemiasEn(1900, 'Atlantida'), RangeError);
  assert.throws(() => m.epidemiasEn(1900, null, 'ZZ'), RangeError);
  assert.throws(() => m.esperanzaRestante('CL', 1900, 0, 'X'), RangeError);
  assert.equal(m.esperanzaRestante('XX', 1900), null);
  assert.equal(m.esperanzaRestante('CL', 1900, 20), null);
  assert.notEqual(m.esperanzaRestante('CL', 1900, 15), null);  // la edad 15 está (modelada)
  assert.equal(m.supervivencia('CL', 1895), m.supervivencia('CL', 1895, 5, 'T'));
});
