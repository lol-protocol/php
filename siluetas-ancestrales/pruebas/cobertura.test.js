// Informe de cobertura por país: lector de CSV, mapeo país→región→etnia y semáforo.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join, dirname } from 'node:path';
import { leerCsv, leerPrioridades, coberturaDePais, informeDeCobertura } from '../src/cobertura.js';
import { REGIONES, ETNIAS } from '../src/index.js';

const RAIZ = join(dirname(fileURLToPath(import.meta.url)), '..');
const CSV = join(RAIZ, '..', 'Listado-paises', 'priorizacion.csv');
const mapa = JSON.parse(readFileSync(join(RAIZ, 'datos', 'paises-regiones.json'), 'utf8')).paises;
const hayCsv = existsSync(CSV);

test('leerCsv entiende comillas, comas dentro de un campo, comillas dobles y saltos de línea de Windows', () => {
  const filas = leerCsv('a,b,c\r\n1,"x, y",3\r\n"con ""comillas""",,z\n');
  assert.deepEqual(filas, [{ a: '1', b: 'x, y', c: '3' }, { a: 'con "comillas"', b: '', c: 'z' }]);
  assert.deepEqual(leerCsv('a,b\n'), []);
  assert.deepEqual(leerCsv('a,b\n1,2'), [{ a: '1', b: '2' }]);
});

test('el mapeo apunta solo a regiones y etnias que existen y a códigos de dos letras', () => {
  const etnias = new Set(ETNIAS.map((e) => e.id));
  for (const [codigo, v] of Object.entries(mapa)) {
    assert.match(codigo, /^[A-Z]{2}$/);
    assert.ok(v.regiones.length > 0, `${codigo}: sin regiones`);
    for (const r of v.regiones) assert.ok(r in REGIONES, `${codigo}: región ${r}`);
    for (const e of v.etnias) assert.ok(etnias.has(e), `${codigo}: etnia ${e}`);
  }
});

test('todo país de Listado-paises/priorizacion.csv está en el mapeo, y el mapeo no trae países que no están', { skip: !hayCsv && 'no está Listado-paises/' }, () => {
  const paises = leerPrioridades(readFileSync(CSV, 'utf8'));
  assert.ok(paises.length > 150);
  const codigos = new Set(paises.map((p) => p.codigo));
  for (const p of paises) assert.ok(mapa[p.codigo], `${p.pais} (${p.codigo}) está en Listado-paises/priorizacion.csv pero no en datos/paises-regiones.json: agrégalo con sus regiones y etnias`);
  for (const c of Object.keys(mapa)) assert.ok(codigos.has(c), `${c} está en datos/paises-regiones.json pero ya no en Listado-paises/priorizacion.csv: quítalo`);
  for (const p of paises) { assert.ok(Number.isFinite(p.prioridad), p.pais); assert.equal(typeof p.excluido, 'boolean'); }
});

test('el semáforo distingue ropa propia del pueblo de ropa genérica de la región', () => {
  const corea = coberturaDePais('KR', mapa);
  assert.ok(corea.propios.mujeres > 0 && corea.propios.hombres > 0);
  assert.equal(corea.estado, 'verde');
  const noruega = coberturaDePais('NL', mapa); // Países Bajos: solo «Europa» genérica
  assert.deepEqual(noruega.propios, { mujeres: 0, hombres: 0 });
  assert.ok(noruega.genericos.mujeres > 0 && noruega.genericos.hombres > 0);
  assert.equal(noruega.estado, 'amarillo');
  assert.equal(noruega.decadas, 0);
  const sin = coberturaDePais('ZZ', mapa);
  assert.equal(sin.estado, 'rojo');
  assert.ok(sin.sinMapeo);
  const falso = coberturaDePais('XX', { XX: { regiones: ['artico'], etnias: [] } }, { desde: 1700, hasta: 1710 });
  assert.ok(['amarillo', 'rojo'].includes(falso.estado));
});

test('las décadas cubiertas se miden con la ropa propia, de mujer y de hombre a la vez', () => {
  const c = coberturaDePais('CL', mapa, { desde: 1800, hasta: 1900 });
  assert.ok(c.decadas > 0.5 && c.decadas <= 1);
  assert.equal(coberturaDePais('CL', mapa, { desde: 3000, hasta: 3100 }).decadas, 0);
});

test('el informe ordena por prioridad, excluye los países excluidos y filtra por estado', () => {
  const paises = [
    { codigo: 'NL', pais: 'Países Bajos', prioridad: 25, excluido: false },
    { codigo: 'KR', pais: 'Corea del Sur', prioridad: 25, excluido: false },
    { codigo: 'FI', pais: 'Finlandia', prioridad: 20, excluido: false },
    { codigo: 'AF', pais: 'Afganistán', prioridad: 99, excluido: true },
  ];
  const md = informeDeCobertura(paises, mapa);
  assert.match(md, /3 países no excluidos/);
  assert.ok(!md.includes('Afganistán'));
  assert.ok(md.indexOf('Corea del Sur') < md.indexOf('Países Bajos') && md.indexOf('Países Bajos') < md.indexOf('Finlandia'));
  assert.match(informeDeCobertura(paises, mapa, { todos: true }), /Afganistán \(excluido\)/);
  const rojos = informeDeCobertura(paises, mapa, { estado: 'amarillo' });
  assert.ok(rojos.includes('Países Bajos') && !rojos.includes('Corea del Sur'));
});

test('bin/cobertura.js imprime el informe y falla con un mensaje claro si algo está mal', { skip: !hayCsv && 'no está Listado-paises/' }, () => {
  const ok = spawnSync('node', [join(RAIZ, 'bin', 'cobertura.js'), '--estado', 'amarillo'], { encoding: 'utf8' });
  assert.equal(ok.status, 0, ok.stderr);
  assert.match(ok.stdout, /^# Cobertura del catálogo por país \(1700 a 1950\)/);
  const mal = spawnSync('node', [join(RAIZ, 'bin', 'cobertura.js'), '--estado', 'azul'], { encoding: 'utf8' });
  assert.equal(mal.status, 2);
  assert.match(mal.stderr, /Estado no válido/);
  const sinCsv = spawnSync('node', [join(RAIZ, 'bin', 'cobertura.js'), '--csv', '/no/existe.csv'], { encoding: 'utf8' });
  assert.equal(sinCsv.status, 2);
  assert.match(sinCsv.stderr, /No se pudo leer/);
});
