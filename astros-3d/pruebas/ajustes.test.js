'use strict';

// Ajustes de comodidad: el astro empieza quieto, la velocidad se limita y todo es predecible
// aunque lo guardado esté roto o falte.

const test = require('node:test');
const assert = require('node:assert/strict');

const A = require('../js/ajustes.js');

test('por defecto el astro empieza quieto y lento', () => {
  const a = A.ajustesIniciales(null, false);
  assert.equal(a.girando, false);
  assert.equal(a.velocidad, A.VELOCIDAD_POR_DEFECTO);
});

test('lo guardado se respeta: puede dejarse girando y con otra velocidad', () => {
  const a = A.ajustesIniciales({ girando: true, velocidad: 0.3 }, false);
  assert.equal(a.girando, true);
  assert.equal(a.velocidad, 0.3);
});

test('con «reducir movimiento» el astro empieza quieto aunque se hubiera dejado girando', () => {
  const a = A.ajustesIniciales({ girando: true, velocidad: 0.3 }, true);
  assert.equal(a.girando, false);
  assert.equal(a.velocidad, 0.3);
});

test('girando solo es verdadero si lo guardado es exactamente true', () => {
  for (const raro of ['true', 1, 'sí', {}, [], null, undefined]) {
    assert.equal(A.ajustesIniciales({ girando: raro }, false).girando, false, String(raro));
  }
});

test('limitarVelocidad sujeta al rango y se recupera de valores que no son número', () => {
  assert.equal(A.limitarVelocidad(100), A.VELOCIDAD_MAXIMA);
  assert.equal(A.limitarVelocidad(0), A.VELOCIDAD_MINIMA);
  assert.equal(A.limitarVelocidad(-3), A.VELOCIDAD_MINIMA);
  assert.equal(A.limitarVelocidad('0.2'), 0.2);
  for (const roto of ['abc', NaN, Infinity, null, undefined, '', {}]) {
    assert.equal(A.limitarVelocidad(roto), A.VELOCIDAD_POR_DEFECTO, String(roto));
  }
});

test('lo guardado que no es un objeto no rompe nada', () => {
  for (const roto of ['texto', 42, true, [], undefined]) {
    const a = A.ajustesIniciales(roto, false);
    assert.equal(a.girando, false);
    assert.equal(a.velocidad, A.VELOCIDAD_POR_DEFECTO);
  }
});

test('segundosPorVuelta: la velocidad por defecto es una vuelta cada ~52 s, y es lenta', () => {
  assert.equal(A.segundosPorVuelta(A.VELOCIDAD_POR_DEFECTO), 52);
  assert.ok(A.segundosPorVuelta(A.VELOCIDAD_MINIMA) > 100, 'la más lenta debe pasar de 100 s');
  assert.ok(A.segundosPorVuelta(A.VELOCIDAD_MAXIMA) >= 10, 'la más rápida no baja de 10 s por vuelta');
});

test('el rango de velocidad es coherente y el predeterminado cae dentro', () => {
  assert.ok(A.VELOCIDAD_MINIMA < A.VELOCIDAD_POR_DEFECTO);
  assert.ok(A.VELOCIDAD_POR_DEFECTO < A.VELOCIDAD_MAXIMA);
  assert.ok(A.VELOCIDAD_MAXIMA <= 0.6, 'más rápido que una vuelta cada 10 s cansa');
});
