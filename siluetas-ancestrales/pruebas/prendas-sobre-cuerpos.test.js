// Cada prenda del catálogo, sola, sobre un niño, un adulto y un anciano corpulento, de mujer y de hombre: la misma
// comprobación que hace la página prendas.html a ojo. Detecta prendas que se deforman o dejan de dibujarse al agregar plantillas.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarPersona, renderizar, PRENDAS } from '../src/index.js';
import { puntosDeRuta } from './ayuda.js';

const CUERPOS = [
  { nombre: 'niño', edad: 8, corpulencia: 'media' },
  { nombre: 'adulto', edad: 30, corpulencia: 'media' },
  { nombre: 'anciano corpulento', edad: 78, corpulencia: 'corpulenta' },
];

test('cada prenda, sola, se dibuja sin NaN y dentro del lienzo sobre cuerpos de varias edades y complexiones', () => {
  for (const pr of PRENDAS) {
    for (const sexo of ['f', 'm']) {
      for (const c of CUERPOS) {
        const p = generarPersona({ semilla: 5, sexo, edad: c.edad, corpulencia: c.corpulencia, vestir: [pr.id], saludo: 'ninguno', pose: 'firme' });
        const svg = renderizar(p, { detalle: 'minimo' });
        const donde = `${pr.id} (${sexo}, ${c.nombre})`;
        assert.ok(!/NaN|undefined|Infinity/.test(svg), `${donde}: valor no válido en el SVG`);
        assert.ok(p.prendas.length === 1 && p.prendas[0].id === pr.id, `${donde}: no quedó vestida solo con esa prenda`);
        for (const m of svg.matchAll(/ d="([^"]+)"/g)) {
          for (const [x, y] of puntosDeRuta(m[1])) {
            assert.ok(x > -60 && x < 660 && y > -80 && y < 1060, `${donde}: punto fuera del lienzo (${x}, ${y})`);
          }
        }
      }
    }
  }
});
