'use strict';

// Geometría de las sombras de los anillos, con casos que se verifican a mano.

const test = require('node:test');
const assert = require('node:assert/strict');

const S = require('../js/sombras.js');

const cerca = (a, b, tol = 1e-9) => Math.abs(a - b) < tol;
const unitario = v => { const n = Math.hypot(...v); return v.map(c => c / n); };

test('ejeDeAnillos: sin inclinación es vertical; con 90° queda acostado', () => {
  const a = S.ejeDeAnillos(0);
  assert.ok(cerca(a[0], 0) && cerca(a[1], 1) && cerca(a[2], 0));
  const b = S.ejeDeAnillos(90);
  assert.ok(cerca(b[1], 0) && cerca(b[2], 1));
  assert.deepEqual(S.ejeDeAnillos(undefined), S.ejeDeAnillos(0));
});

test('el polo sur con la luz por encima: el rayo cruza el plano a r = 1,333, dentro de los anillos de Saturno', () => {
  // P = (0, -1, 0); L = (0, 0,6, 0,8); el rayo sube y cruza y = 0 con t = 1/0,6.
  const P = [0, -1, 0], L = [0, 0.6, 0.8], eje = [0, 1, 0];
  const c = S.cruceConElPlanoDeAnillos(P, L, eje);
  assert.ok(cerca(c.t, 1 / 0.6));
  assert.ok(cerca(c.r, 0.8 / 0.6), `r = ${c.r}`);
  assert.equal(S.enSombraDeAnillos(P, L, eje, 1.24, 2.27), true);
  assert.equal(S.enSombraDeAnillos(P, L, eje, 1.5, 2.27), false, 'fuera del anillo (interior 1,5): no hay sombra');
});

test('el polo norte, con la luz por encima, no tiene los anillos entre él y la luz', () => {
  assert.equal(S.cruceConElPlanoDeAnillos([0, 1, 0], [0, 0.6, 0.8], [0, 1, 0]), null);
  assert.equal(S.enSombraDeAnillos([0, 1, 0], [0, 0.6, 0.8], [0, 1, 0], 1.24, 2.27), false);
});

test('luz paralela al plano de los anillos: no hay cruce, no hay sombra', () => {
  assert.equal(S.cruceConElPlanoDeAnillos([0, -1, 0], [1, 0, 0], [0, 1, 0]), null);
});

test('un punto por encima del plano con la luz también por encima no queda a la sombra', () => {
  const L = unitario([0.8, 0.6, 0]);
  assert.equal(S.enSombraDeAnillos(L, L, [0, 1, 0], 1.24, 2.27), false);
});

test('el punto de cruce está de verdad en el plano de los anillos (con el eje inclinado)', () => {
  const eje = S.ejeDeAnillos(26.73);
  const P = [0.3, -0.7, 0.648], L = unitario([-0.6, 0.35, 0.75]);
  const c = S.cruceConElPlanoDeAnillos(P, L, eje);
  assert.ok(c, 'debería haber cruce');
  const Q = [P[0] + c.t * L[0], P[1] + c.t * L[1], P[2] + c.t * L[2]];
  assert.ok(Math.abs(Q[0] * eje[0] + Q[1] * eje[1] + Q[2] * eje[2]) < 1e-9, 'Q debe estar en el plano');
  assert.ok(cerca(Math.hypot(...Q), c.r));
});

test('sombra del planeta sobre los anillos: el rayo por el centro choca; el que se aleja o pasa de largo, no', () => {
  assert.equal(S.enSombraDelPlaneta([2, 0, 0], [-1, 0, 0]), true, 'el rayo atraviesa el planeta');
  assert.equal(S.enSombraDelPlaneta([2, 0, 0], [1, 0, 0]), false, 'el rayo se aleja');
  assert.equal(S.enSombraDelPlaneta([2, 0, 1.5], [-1, 0, 0]), false, 'pasa a 1,5 del centro');
  assert.equal(S.enSombraDelPlaneta([2, 0, 0.9], [-1, 0, 0]), true, 'pasa a 0,9 del centro');
});

test('distanciaDelRayoAlCentro: si el rayo se aleja desde el principio, es la distancia de P al centro', () => {
  assert.ok(cerca(S.distanciaDelRayoAlCentro([3, 0, 0], [1, 0, 0]), 3));
  assert.ok(cerca(S.distanciaDelRayoAlCentro([2, 0, 1.5], [-1, 0, 0]), 1.5));
});
