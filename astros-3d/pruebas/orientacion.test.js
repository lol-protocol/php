'use strict';

// Sentido de giro en el visor: Venus, Urano y Plutón ya traen la inclinación del eje mayor de 90°,
// que por sí sola es un giro retrógrado; invertirlo otra vez los haría girar al revés.

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const O = require('../js/orientacion.js');
const ASTROS = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'datos', 'astros.json'), 'utf8')).astros;

test('sentidoDeGiro: los casos conocidos', () => {
  assert.equal(O.sentidoDeGiro(23.934, 23.44), 1, 'Tierra: directo');
  assert.equal(O.sentidoDeGiro(1407.6, 0.03), 1, 'Mercurio: directo');
  assert.equal(O.sentidoDeGiro(-5832.5, 177.36), 1, 'Venus: el eje ya está dado vuelta, no se invierte otra vez');
  assert.equal(O.sentidoDeGiro(-17.24, 97.77), 1, 'Urano: igual');
  assert.equal(O.sentidoDeGiro(-153.3, 122.53), 1, 'Plutón: igual');
  assert.equal(O.sentidoDeGiro(-141, undefined), -1, 'Tritón: sin inclinación, sí se invierte');
  assert.equal(O.sentidoDeGiro(-153.3, null), -1, 'Caronte: sin inclinación, sí se invierte');
});

test('un valor de inclinación que falta (null o undefined) se trata como sin inclinación', () => {
  assert.equal(O.sentidoDeGiro(10, null), 1);
  assert.equal(O.sentidoDeGiro(-10, undefined), -1);
});

test('en todo el catálogo, el giro que se ve es retrógrado exactamente en los astros de periodo negativo', () => {
  for (const a of ASTROS.filter(x => x.categoria !== 'sol')) {
    const vertical = O.giroVertical(a.periodo_rotacion_h, a.inclinacion_eje_grados);
    assert.ok(Math.abs(vertical) > 0.05, `${a.id}: el eje está casi acostado y el sentido sería ambiguo`);
    assert.equal(vertical < 0, a.periodo_rotacion_h < 0,
      `${a.id}: periodo ${a.periodo_rotacion_h} h e inclinación ${a.inclinacion_eje_grados}° dan un giro ${vertical < 0 ? 'retrógrado' : 'directo'}`);
  }
});
