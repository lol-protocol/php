'use strict';

// Valores derivados y formatos de generar.js, y que lo committeado esté al día con el JSON.

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const RAIZ = path.join(__dirname, '..');
const G = require(path.join(RAIZ, 'generar.js'));
const DATOS = JSON.parse(fs.readFileSync(path.join(RAIZ, 'datos', 'astros.json'), 'utf8'));

const porId = id => G.ajustarDerivados(DATOS.astros.find(a => a.id === id));

test('numero usa coma decimal y punto de miles', () => {
  assert.equal(G.numero(2439.7, 1), '2.439,7');
  assert.equal(G.numero(384400, 0), '384.400');
  assert.equal(G.numero(0.5, 2), '0,50');
});

test('cientifico escribe notación con superíndices', () => {
  assert.equal(G.cientifico(5.972e24), '5,97 × 10²⁴');
  assert.equal(G.cientifico(3.301e-5), '3,30 × 10⁻⁵');
});

test('horas: retrógrado con signo menos, días a partir de dos días', () => {
  assert.equal(G.horas(23.934), '23,9 h');
  assert.equal(G.horas(-17.24), '−17,2 h');
  assert.equal(G.horas(1407.6), '58,65 d');
  assert.equal(G.horas(-5832.5), '−243,02 d');
});

test('gravedad de la Tierra sale cerca de 9,8 m/s²', () => {
  const g = porId('tierra').gravedad_ms2;
  assert.ok(Math.abs(g - 9.82) < 0.05, `g = ${g}`);
});

test('densidad de la Tierra sale cerca de 5.513 kg/m³', () => {
  const d = porId('tierra').densidad_kg_m3;
  assert.ok(Math.abs(d - 5513) < 10, `densidad = ${d}`);
});

test('gravedad de Marte sale cerca de 3,7 m/s²', () => {
  const g = porId('marte').gravedad_ms2;
  assert.ok(Math.abs(g - 3.71) < 0.05, `g = ${g}`);
});

test('los soles reciben radio y masa en km y kg, sin gravedad', () => {
  const sol = porId('sol');
  assert.equal(sol.radio_km, 695700);
  assert.ok(Math.abs(sol.masa_kg - 1.989e30) / 1.989e30 < 1e-9);
  assert.equal(sol.gravedad_ms2, null);
  assert.equal(sol.densidad_kg_m3, null);
});

test('la tabla de planetas trae una fila por planeta, y la de soles por sol', () => {
  const md = G.generarMarkdown(DATOS);
  const encabezados = ['| Planeta | Tipo', '| Luna | Cuerpo padre', '| Estrella | Tipo espectral'];
  const filas = md.split('\n').filter(l => l.startsWith('| ') && !encabezados.some(h => l.startsWith(h)));
  const cuentaPlanetas = DATOS.astros.filter(a => a.categoria === 'planeta').length;
  const cuentaSoles = DATOS.astros.filter(a => a.categoria === 'sol').length;
  const cuentaLunas = DATOS.astros.filter(a => a.categoria === 'luna').length;
  assert.equal(filas.length, cuentaPlanetas + cuentaLunas + cuentaSoles);
  assert.match(md, /\| Tierra \| Planeta rocoso/);
  assert.match(md, /\| Sirio A \| A1V/);
});

test('lo committeado (js/datos.js y caracteristicas.md) está al día con el JSON', () => {
  const js = fs.readFileSync(path.join(RAIZ, 'js', 'datos.js'), 'utf8');
  const md = fs.readFileSync(path.join(RAIZ, 'caracteristicas.md'), 'utf8');
  assert.equal(js, G.generarJs(DATOS), 'js/datos.js desactualizado: corre node generar.js');
  assert.equal(md, G.generarMarkdown(DATOS), 'caracteristicas.md desactualizado: corre node generar.js');
});
