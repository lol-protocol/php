// Un error de uso (valor mal escrito, fuera de rango o peso inválido) debe fallar con un mensaje claro,
// no dibujar otra cosa en silencio. Hay al menos una prueba por cada fallo que esto corrige.
import test from 'node:test';
import assert from 'node:assert/strict';
import { generarPersona, generarPersonaConRopaNueva, generarRopaNueva, renderizar, PRENDAS } from '../src/index.js';
import { validarPrenda, problemasDeParams, TIPOS } from '../src/tipos-de-ropa.js';

const ids = (svg) => new Set([...svg.matchAll(/ id="([^"]+)"/g)].map((m) => m[1]));

test('sexo no válido: error claro en vez de "Cannot read properties of undefined"', () => {
  assert.throws(() => generarPersona({ sexo: 'z' }), /Sexo no válido.*'f', ?'m'|Sexo no válido.*f, m/);
  assert.throws(() => generarPersonaConRopaNueva({ sexo: 'x' }), /Sexo no válido/);
  assert.throws(() => generarRopaNueva({ sexo: 'x' }), /Sexo no válido/);
});

test('peso de etnia: 0 la excluye; negativo, NaN o todo en 0 lanzan error', () => {
  const p = generarPersona({ semilla: 3, etnia: { mapuche: 1, aymara: 0 } });
  assert.deepEqual(p.etnia.map((e) => e.id), ['mapuche']);
  assert.throws(() => generarPersona({ etnia: { mapuche: -1 } }), /Peso no válido/);
  assert.throws(() => generarPersona({ etnia: { mapuche: NaN } }), /Peso no válido/);
  assert.throws(() => generarPersona({ etnia: { mapuche: 0, aymara: 0 } }), /peso 0/);
});

test('peinado, barba, tocado, región, piel, cabello y calzado desconocidos lanzan error con la lista', () => {
  assert.throws(() => generarPersona({ peinado: 'corot' }), /Peinado no válido.*corto/);
  assert.throws(() => generarPersona({ barba: 'enorme' }), /Barba no válid/);
  assert.throws(() => generarPersona({ tocado: 'gorrito' }), /Tocado no válido/);
  assert.throws(() => generarPersona({ region: 'marte' }), /Región no válid/);
  assert.throws(() => generarPersona({ piel: 'azul' }), /Piel no válid/);
  assert.throws(() => generarPersona({ cabello: '#12' }), /Cabello no válido/);
  assert.throws(() => generarPersona({ calzado: 'sombrero' }), /Calzado no válido/);
  assert.doesNotThrow(() => generarPersona({ tocado: 'ninguno', barba: false, piel: '#aa8866' }));
});

test('edad, altura y corpulencia fuera de rango o no numéricas lanzan error', () => {
  assert.throws(() => generarPersona({ edad: 150 }), /Edad no válid/);
  assert.throws(() => generarPersona({ edad: -3 }), /Edad no válid/);
  assert.throws(() => generarPersona({ edad: NaN }), /Edad no válid/);
  assert.throws(() => generarPersona({ altura: 400 }), /Altura no válid/);
  assert.throws(() => generarPersona({ corpulencia: 9 }), /Corpulencia no válid/);
  assert.throws(() => generarPersona({ corpulencia: 'enorme' }), /Corpulencia no válid/);
  assert.doesNotThrow(() => generarPersona({ edad: 100, altura: 150, corpulencia: 1.2 }));
});

test('dos personas que solo difieren en el rostro no comparten ids en una misma página', () => {
  const a = generarPersona({ semilla: 5 });
  const b = { ...a, rostro: { ...a.rostro, ancho: a.rostro.ancho + 0.05 } };
  const ia = ids(renderizar(a)), ib = ids(renderizar(b));
  assert.equal([...ia].filter((x) => ib.has(x)).length, 0);
  // El texto descriptivo no cambia el dibujo ni el prefijo.
  assert.equal([...ids(renderizar({ ...a, titulo: 'otro' }))].join(), [...ia].join());
});

test('renderizar rechaza un peinado, una barba, un tocado o una prenda mal escritos', () => {
  const a = generarPersona({ semilla: 5 });
  assert.throws(() => renderizar({ ...a, cabello: { ...a.cabello, estilo: 'xx' } }), /Peinado desconocido/);
  assert.throws(() => renderizar({ ...a, barba: { ...a.barba, estilo: 'xx' } }), /Barba desconocido|Barba desconocida/);
  assert.throws(() => renderizar({ ...a, tocado: { id: 'xx', colores: {} } }), /Tocado desconocido/);
  const mala = { id: 't', plantilla: 'tunica', params: { manga: 'larg0' }, colores: {}, ranura: 'torso' };
  assert.throws(() => renderizar({ ...a, prendas: [mala] }), /larg0.*larga/);
});

test('todo el catálogo de prendas cumple las listas de tipos-de-ropa', () => {
  for (const p of PRENDAS) assert.doesNotThrow(() => validarPrenda(p), p.id);
});

test('los errores de parámetros sugieren la palabra correcta', () => {
  assert.match(problemasDeParams('tunica', { escote: 'redondoo' })[0], /redondo/);
  assert.match(problemasDeParams('tunica', { puno: true })[0], /puños/);
  assert.match(problemasDeParams('mono', { torso: { manga: 'larg0' } })[0], /torso.*larga/);
  assert.match(problemasDeParams('tunica', { patron: { tipo: 'nada' } })[0], /no existe/);
});

test('ropa nueva: 3000 semillas dan prendas válidas y se dibujan sin NaN', () => {
  for (let s = 1; s <= 3000; s++) {
    const r = generarRopaNueva({ semilla: s, sexo: s % 2 ? 'f' : 'm', estilo: ['sobrio', 'trabajo', 'festivo', 'ceremonial'][s % 4] });
    for (const p of r.prendas) validarPrenda(p);
  }
  for (let s = 1; s <= 40; s++) assert.ok(!/NaN|undefined/.test(renderizar(generarPersonaConRopaNueva({ semilla: s }))), `semilla ${s}`);
});

test('cada variante enumerada de manga, escote, bajo, calzado y cuello se dibuja sin NaN', () => {
  const base = generarPersona({ semilla: 9, sexo: 'f', edad: 30 });
  const prueba = (plantilla, clave, valores, extra = {}) => {
    for (const v of valores) {
      const prenda = { id: `${plantilla}-${v}`, nombre: v, ranura: 'torso', plantilla, params: { [clave]: v, ...extra }, colores: { principal: '#aa5544', secundario: '#554433', ribete: '#ddcc99', detalle: '#ccaa44' } };
      const svg = renderizar({ ...base, prendas: [prenda] });
      assert.ok(!/NaN|undefined/.test(svg), `${plantilla}.${clave}=${v}`);
    }
  };
  prueba('tunica', 'manga', TIPOS.manga, { largo: 'cadera' });
  prueba('tunica', 'escote', TIPOS.escote, { largo: 'cadera' });
  prueba('tunica', 'bajo', TIPOS.bajo, { largo: 'cadera' });
  prueba('pantalon', 'ancho', TIPOS.anchoPantalon, { largo: 'tobillo' });
  prueba('calzado', 'tipo', TIPOS.calzado);
  prueba('cuello', 'tipo', TIPOS.cuello);
});
