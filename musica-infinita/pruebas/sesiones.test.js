'use strict';

// Favoritos, historial, listas de reproducción y exportación e importación de sesiones.

const test = require('node:test');
const assert = require('node:assert/strict');
const S = require('../js/sesiones.js');
const VP = require('../js/visual-puro.js');

const ESTILOS = ['ambiente', 'pop', 'orquestal', 'chip', 'lofi', 'cajita'];
const AJ = { fidelidad: 0.7, variacion: 0.4, colorModal: 0.3, contra: 0.6, cambiosCompas: 0.15, tempo: 1, estilo: 'pop' };

function fav(nombre, semilla, extra) {
  return S.crearFavorito(Object.assign({ nombre: nombre, semilla: semilla, ajustes: AJ, canciones: ['ejemplo:a', 'propio:x:10'], generador: 1, ahora: 1000 }, extra), [], { estilos: ESTILOS });
}

function azarFijo(valores) {
  let i = 0;
  return function () { return valores[i++ % valores.length]; };
}

// ------------------------------------------------------------ ajustes

test('ajustes: se recortan a sus rangos, se descarta lo desconocido y el estilo debe existir', function () {
  const a = S.sanearAjustes({ fidelidad: 5, variacion: -1, tempo: 9, contra: '0.5', colorModal: 'mucho', cambiosCompas: null, estilo: 'pop', otra: 1, __proto__x: 2 }, { estilos: ESTILOS });
  assert.deepEqual(a, { fidelidad: 1, variacion: 0, tempo: 1.4, contra: 0.5, estilo: 'pop' });
  assert.deepEqual(S.sanearAjustes({ estilo: 'rock' }, { estilos: ESTILOS }), {});
  assert.deepEqual(S.sanearAjustes({ estilo: 'auto' }, { estilos: ESTILOS }), { estilo: 'auto' });
  assert.deepEqual(S.sanearAjustes(null), {});
  const app = S.sanearAjustesApp({ volumen: 3, reverb: 0.5, modoVisual: 'espectro', autoVisual: true, bits: 24, semilla: 'a<b>c', xx: 1 }, { modos: ['espectro', 'notas'] });
  assert.deepEqual(app, { volumen: 1, reverb: 0.5, modoVisual: 'espectro', autoVisual: true, bits: '24', semilla: 'abc' });
  assert.equal(S.sanearAjustesApp({ modoVisual: 'raro' }, { modos: ['espectro'] }).modoVisual, undefined);
});

test('misma configuración: la semilla, los ajustes y el conjunto de canciones (sin importar el orden)', function () {
  const a = { semilla: 's', ajustes: AJ, canciones: ['x', 'y'] };
  assert.ok(S.mismaConfiguracion(a, { semilla: 's', ajustes: Object.assign({}, AJ), canciones: ['y', 'x'] }));
  assert.ok(!S.mismaConfiguracion(a, { semilla: 't', ajustes: AJ, canciones: ['x', 'y'] }));
  assert.ok(!S.mismaConfiguracion(a, { semilla: 's', ajustes: Object.assign({}, AJ, { contra: 0.7 }), canciones: ['x', 'y'] }));
  assert.ok(!S.mismaConfiguracion(a, { semilla: 's', ajustes: AJ, canciones: ['x'] }));
});

// ------------------------------------------------------------ favoritos

test('favorito: se crea saneado, con id único y fecha', function () {
  const f = fav('  Mi <b>favorita</b> ', 'semilla-1');
  assert.equal(f.nombre, 'Mi bfavorita/b');
  assert.equal(f.semilla, 'semilla-1');
  assert.deepEqual(f.ajustes, AJ);
  assert.deepEqual(f.canciones, ['ejemplo:a', 'propio:x:10']);
  assert.equal(f.creado, new Date(1000).toISOString());
  const otro = S.crearFavorito({ nombre: 'x', semilla: 'y', ajustes: AJ, ahora: 1000 }, [f], {});
  assert.ok(otro.id !== f.id);
  assert.equal(S.crearFavorito({ nombre: '', semilla: 'y' }, []).nombre, 'Sin nombre');
  assert.equal(S.crearFavorito({ nombre: 'x', semilla: '   ' }, []), null);
});

test('favorito: no repite una configuración, respeta el tope, se renombra y se quita con sus apariciones en listas', function () {
  const f1 = fav('uno', 'a');
  let r = S.agregarFavorito([], f1);
  assert.equal(r.lista.length, 1);
  r = S.agregarFavorito(r.lista, Object.assign({}, f1, { id: 'otro', nombre: 'copia' }));
  assert.equal(r.duplicado.id, f1.id);
  assert.equal(r.lista.length, 1);
  const lleno = [];
  for (let i = 0; i < S.MAX.favoritos; i++) lleno.push(fav('f' + i, 's' + i, { ahora: i }));
  assert.ok(S.agregarFavorito(lleno, fav('nuevo', 'zzz')).lleno);
  assert.equal(S.renombrarFavorito([f1], f1.id, '  Nuevo nombre ')[0].nombre, 'Nuevo nombre');
  assert.equal(S.renombrarFavorito([f1], f1.id, '   ')[0].nombre, 'uno');
  const lista = S.agregarItem(S.agregarItem(S.crearLista('L', [], 5), { tipo: 'favorito', ref: f1.id, minutos: 3 }), { tipo: 'midi', ref: 'propio:x:10' });
  const q = S.quitarFavorito([f1], [lista], f1.id);
  assert.equal(q.favoritos.length, 0);
  assert.deepEqual(q.listas[0].items, [{ tipo: 'midi', ref: 'propio:x:10' }]);
});

test('favorito: lo que viene de fuera se sanea o se descarta', function () {
  assert.equal(S.validarFavorito(null), null);
  assert.equal(S.validarFavorito({ id: 'a' }), null);
  assert.equal(S.validarFavorito({ semilla: 'a' }), null);
  const v = S.validarFavorito({ id: 'a', semilla: 's'.repeat(100), nombre: 'n'.repeat(200), ajustes: { fidelidad: 9 }, canciones: ['x', 'x', 7, '', 'y'], generador: 'raro' }, {});
  assert.equal(v.semilla.length, S.MAX.semilla);
  assert.equal(v.nombre.length, S.MAX.nombre);
  assert.deepEqual(v.ajustes, { fidelidad: 1 });
  assert.deepEqual(v.canciones, ['x', 'y']);
  assert.equal(v.generador, 0);
});

// ------------------------------------------------------------ historial

const CONF = { semilla: 's1', ajustes: AJ, canciones: ['a', 'b'], generador: 1 };

test('historial: la misma semilla es una corrida; suma secciones, estilos y citas', function () {
  let h = [];
  h = S.registrarSeccion(h, CONF, { seccion: 1, estilo: 'Pop', material: 'cita', cancion: 'Himno' }, 1000);
  h = S.registrarSeccion(h, CONF, { seccion: 1, estilo: 'Pop', material: 'libre' }, 2000);
  h = S.registrarSeccion(h, CONF, { seccion: 2, estilo: 'Lo-fi', material: 'cita', cancion: 'Greensleeves' }, 5000);
  h = S.registrarSeccion(h, CONF, { seccion: 2, estilo: 'Lo-fi', material: 'cita', cancion: 'Himno' }, 6000);
  assert.equal(h.length, 1);
  assert.equal(h[0].secciones, 2);
  assert.equal(h[0].bloques, 4);
  assert.deepEqual(h[0].estilos, ['Pop', 'Lo-fi']);
  assert.deepEqual(h[0].citas, ['Himno', 'Greensleeves']);
  assert.ok(h[0].limpia);
  assert.equal(S.duracionCorrida(h[0]), 5);
  // otra semilla abre otra corrida
  h = S.registrarSeccion(h, Object.assign({}, CONF, { semilla: 's2' }), { seccion: 1, estilo: 'Pop' }, 9000);
  assert.equal(h.length, 2);
  assert.equal(h[1].semilla, 's2');
});

test('historial: mover los controles en vivo marca la corrida con cambios y toma la configuración nueva', function () {
  let h = S.registrarSeccion([], CONF, { seccion: 1, estilo: 'Pop' }, 1000);
  const cambiada = Object.assign({}, CONF, { ajustes: Object.assign({}, AJ, { contra: 0.1 }) });
  h = S.registrarSeccion(h, cambiada, { seccion: 2, estilo: 'Pop' }, 2000);
  assert.equal(h.length, 1);
  assert.equal(h[0].limpia, false);
  assert.equal(h[0].ajustes.contra, 0.1);
  // y apagar una canción también
  let g = S.registrarSeccion([], CONF, { seccion: 1 }, 1000);
  g = S.registrarSeccion(g, Object.assign({}, CONF, { canciones: ['a'] }), { seccion: 2 }, 2000);
  assert.equal(g[0].limpia, false);
  assert.deepEqual(g[0].canciones, ['a']);
});

test('historial: tiene tope, no muta la lista anterior y las corridas de fuera se sanean', function () {
  let h = [];
  const antes = h;
  for (let i = 0; i < S.MAX.historial + 30; i++) h = S.registrarSeccion(h, Object.assign({}, CONF, { semilla: 'x' + i }), { seccion: 1 }, 1000 + i);
  assert.equal(h.length, S.MAX.historial);
  assert.equal(h[h.length - 1].semilla, 'x' + (S.MAX.historial + 29));
  assert.equal(antes.length, 0);
  const una = S.registrarSeccion([], CONF, { seccion: 1 }, 1);
  const copia = JSON.parse(JSON.stringify(una));
  S.registrarSeccion(una, CONF, { seccion: 2, estilo: 'X' }, 2);
  assert.deepEqual(una, copia);
  assert.equal(S.validarCorrida({ id: 'h', semilla: '' }), null);
  const c = S.validarCorrida({ id: 'h', semilla: 's', secciones: -4, bloques: 'x', estilos: ['a', 7], citas: 'no', limpia: false });
  assert.equal(c.secciones, 0);
  assert.equal(c.bloques, 0);
  assert.deepEqual(c.estilos, ['a', '7']);
  assert.deepEqual(c.citas, []);
  assert.equal(c.limpia, false);
});

// ------------------------------------------------------------ listas

test('lista: crear, agregar, quitar y mover; los elementos inválidos no entran', function () {
  let l = S.crearLista('Para trabajar', [], 7);
  assert.equal(l.modo, 'orden');
  assert.equal(l.repetir, false);
  l = S.agregarItem(l, { tipo: 'favorito', ref: 'f1', minutos: 999 });
  l = S.agregarItem(l, { tipo: 'midi', ref: 'propio:a:1' });
  l = S.agregarItem(l, { tipo: 'raro', ref: 'x' });
  l = S.agregarItem(l, { tipo: 'midi' });
  assert.deepEqual(l.items, [{ tipo: 'favorito', ref: 'f1', minutos: 120 }, { tipo: 'midi', ref: 'propio:a:1' }]);
  assert.deepEqual(S.moverItem(l, 0, 1).items.map(function (i) { return i.ref; }), ['propio:a:1', 'f1']);
  assert.equal(S.moverItem(l, 0, -1), l);
  assert.equal(S.moverItem(l, 1, 1), l);
  assert.deepEqual(S.quitarItem(l, 0).items.map(function (i) { return i.ref; }), ['propio:a:1']);
  assert.equal(l.items.length, 2); // no muta
  assert.equal(S.validarLista({ nombre: 'sin id' }), null);
  assert.equal(S.validarLista({ id: 'l', modo: 'caos', repetir: 'si', items: [{ tipo: 'favorito', ref: 'a', minutos: 0 }] }).modo, 'orden');
  assert.equal(S.validarLista({ id: 'l', items: [{ tipo: 'favorito', ref: 'a', minutos: 0 }] }).items[0].minutos, 5);
});

test('lista en orden: sigue el orden, y al final termina o vuelve a empezar', function () {
  const l = S.validarLista({ id: 'l', items: [{ tipo: 'midi', ref: 'a' }, { tipo: 'midi', ref: 'b' }, { tipo: 'midi', ref: 'c' }] });
  const rep = S.nuevaReproduccion();
  assert.deepEqual([S.siguiente(l, rep), S.siguiente(l, rep), S.siguiente(l, rep), S.siguiente(l, rep)], [0, 1, 2, null]);
  const r2 = S.nuevaReproduccion();
  const seg = [];
  for (let i = 0; i < 7; i++) seg.push(S.siguiente(Object.assign({}, l, { repetir: true }), r2));
  assert.deepEqual(seg, [0, 1, 2, 0, 1, 2, 0]);
  assert.equal(S.siguiente(S.validarLista({ id: 'v' }), S.nuevaReproduccion()), null);
});

test('lista al azar: todos suenan una vez por vuelta, y al repetir no empieza por el último', function () {
  const items = [0, 1, 2, 3, 4].map(function (i) { return { tipo: 'midi', ref: 'm' + i }; });
  const l = S.validarLista({ id: 'l', modo: 'aleatorio', repetir: true, items: items });
  const az = azarFijo([0.1, 0.9, 0.5, 0.3, 0.7, 0.2]);
  const rep = S.nuevaReproduccion();
  const vuelta1 = [];
  for (let i = 0; i < 5; i++) vuelta1.push(S.siguiente(l, rep, az));
  assert.deepEqual(vuelta1.slice().sort(), [0, 1, 2, 3, 4]);
  const ultimo = vuelta1[4];
  for (let k = 0; k < 20; k++) {
    const r = { indice: ultimo, jugados: [0, 1, 2, 3, 4] };
    assert.notEqual(S.siguiente(l, r, Math.random), ultimo);
  }
  // sin repetir, al terminar la vuelta se acaba; y con un solo elemento y repetir, vuelve a ese
  const sin = Object.assign({}, l, { repetir: false });
  const r3 = S.nuevaReproduccion();
  for (let i = 0; i < 5; i++) S.siguiente(sin, r3, Math.random);
  assert.equal(S.siguiente(sin, r3, Math.random), null);
  const uno = S.validarLista({ id: 'u', modo: 'aleatorio', repetir: true, items: [items[0]] });
  const r4 = S.nuevaReproduccion();
  assert.equal(S.siguiente(uno, r4, Math.random), 0);
  assert.equal(S.siguiente(uno, r4, Math.random), 0);
});

test('lista: resolver elementos avisa de lo que ya no existe', function () {
  const f = fav('uno', 'a');
  const l = S.validarLista({ id: 'l', items: [{ tipo: 'favorito', ref: f.id, minutos: 4 }, { tipo: 'favorito', ref: 'borrado' }, { tipo: 'midi', ref: 'propio:a:1' }, { tipo: 'midi', ref: 'propio:b:2' }, { tipo: 'midi', ref: 'propio:c:3' }] });
  const r = S.resolverItems(l, [f], [{ id: 'propio:a:1', nombre: 'A', tieneMidi: true }, { id: 'propio:c:3', nombre: 'C', tieneMidi: false }]);
  assert.deepEqual(r.map(function (x) { return x.ok; }), [true, false, true, false, false]);
  assert.equal(r[0].minutos, 4);
  assert.equal(r[0].favorito.id, f.id);
  assert.match(r[1].motivo, /favorito ya no existe/);
  assert.match(r[3].motivo, /ya no está/);
  assert.match(r[4].motivo, /no se pudo leer/);
});

// ------------------------------------------------------------ base64

test('base64: ida y vuelta de bytes de todo tipo y de varios tamaños; rechaza lo inválido', function () {
  for (const n of [0, 1, 2, 3, 100, 0x8000, 0x8000 + 5, 100000]) {
    const b = new Uint8Array(n).map(function (_, i) { return (i * 37 + 11) & 255; });
    assert.deepEqual(S.deBase64(S.aBase64(b)), b);
  }
  for (const malo of ['abc', 'a b=', '!!!!', 7, null, 'abc==x']) assert.throws(function () { S.deBase64(malo); }, /base64/);
});

// ------------------------------------------------------------ exportar e importar

function sesion() {
  const f1 = fav('Favorita', 'semilla-1');
  const f2 = fav('Otra', 'semilla-2', { ahora: 2000 });
  const l = S.agregarItem(S.agregarItem(S.crearLista('Mi lista', [], 3), { tipo: 'favorito', ref: f1.id, minutos: 6 }), { tipo: 'midi', ref: 'propio:cancion:4' });
  return {
    ajustes: { fidelidad: 0.8, volumen: 0.5, modoVisual: 'notas', semilla: '' },
    favoritos: [f1, f2],
    listas: [l],
    presets: [VP.PRESETS[0], Object.assign({}, VP.PRESETS[1], { nombre: 'Mío' })],
    canciones: [{ id: 'ejemplo:a', nombre: 'Ejemplo', activo: false }, { id: 'propio:cancion:4', nombre: 'Canción', activo: true, bytes: new Uint8Array([1, 2, 3, 250]) }],
    estilos: ESTILOS,
    modos: ['notas', 'espectro'],
    ahora: 5000,
  };
}

test('exportar e importar sin MIDI: todo vuelve, sin los bytes', function () {
  const d = sesion();
  const txt = S.exportarSesion(d);
  assert.ok(!/"midi":/.test(txt), 'no debe llevar bytes de MIDI');
  const r = S.importarSesion(txt, { estilos: ESTILOS, modos: d.modos });
  assert.deepEqual(r.favoritos, d.favoritos);
  assert.deepEqual(r.listas, d.listas);
  assert.deepEqual(r.presets, d.presets);
  assert.deepEqual(r.ajustes, { fidelidad: 0.8, volumen: 0.5, modoVisual: 'notas', semilla: '' });
  assert.deepEqual(r.canciones.map(function (c) { return [c.id, c.activo, c.bytes]; }), [['ejemplo:a', false, undefined], ['propio:cancion:4', true, undefined]]);
  assert.deepEqual(r.avisos, []);
  assert.equal(r.incluyeMidi, false);
});

test('exportar e importar con MIDI: los bytes de los propios vuelven iguales y los ejemplos nunca van', function () {
  const d = sesion();
  d.canciones[0].bytes = new Uint8Array([9, 9, 9]); // un ejemplo: no se exporta aunque traiga bytes
  d.incluirMidi = true;
  const txt = S.exportarSesion(d);
  const r = S.importarSesion(txt, { estilos: ESTILOS, modos: d.modos });
  assert.equal(r.incluyeMidi, true);
  assert.equal(r.canciones[0].bytes, undefined);
  assert.deepEqual(r.canciones[1].bytes, new Uint8Array([1, 2, 3, 250]));
});

test('importar: errores claros, versiones nuevas, archivos grandes y contenido a medias', function () {
  assert.throws(function () { S.importarSesion('no json'); }, /no es un JSON válido/);
  assert.throws(function () { S.importarSesion('{"a":1}'); }, /no es una sesión/);
  assert.throws(function () { S.importarSesion(JSON.stringify({ formato: S.FORMATO, version: 99 })); }, /versión más nueva/);
  assert.throws(function () { S.importarSesion(JSON.stringify({ formato: S.FORMATO })); }, /versión más nueva/);
  assert.throws(function () { S.importarSesion('x'.repeat(S.MAX.texto + 1)); }, /demasiado grande/);
  assert.throws(function () { S.importarSesion(7); }, /demasiado grande/);
  const raro = JSON.stringify({
    formato: S.FORMATO,
    version: 1,
    favoritos: [{ id: 'a', semilla: 's' }, { id: 'a', semilla: 'repetido' }, { id: 'b' }, 7],
    listas: [{ id: 'l', items: [{ tipo: 'midi', ref: 'x' }] }, { nombre: 'sin id' }],
    presets: [VP.PRESETS[0], { nombre: 'roto' }],
    canciones: [{ id: 'propio:a:1', nombre: 'A', midi: '###' }, { id: 'propio:b:2', midi: 'AQID' }, { nombre: 'sin id' }, { id: 'propio:b:2' }],
    ajustes: { fidelidad: 2 },
  });
  const r = S.importarSesion(raro);
  assert.deepEqual(r.favoritos.map(function (f) { return f.id; }), ['a']);
  assert.equal(r.listas.length, 1);
  assert.equal(r.presets.length, 1);
  assert.deepEqual(r.canciones.map(function (c) { return c.id; }), ['propio:a:1', 'propio:b:2']);
  assert.deepEqual(r.canciones[1].bytes, new Uint8Array([1, 2, 3]));
  assert.equal(r.canciones[0].bytes, undefined);
  assert.equal(r.ajustes.fidelidad, 1);
  assert.ok(r.avisos.some(function (a) { return /favoritos descartados/.test(a); }));
  assert.ok(r.avisos.some(function (a) { return /MIDI no se pudieron leer/.test(a); }));
});

test('importar: un MIDI de más de 4 MB no entra, ni pasarse del total', function () {
  const grande = S.aBase64(new Uint8Array(S.MAX.midi + 1));
  const r = S.importarSesion(JSON.stringify({ formato: S.FORMATO, version: 1, canciones: [{ id: 'propio:g:1', midi: grande }, { id: 'propio:ok:2', midi: 'AQID' }] }));
  assert.equal(r.canciones[0].bytes, undefined);
  assert.deepEqual(r.canciones[1].bytes, new Uint8Array([1, 2, 3]));
  assert.ok(r.avisos.some(function (a) { return /demasiado grandes/.test(a); }));
});

test('fusionar: no repite lo que ya está, renombra los choques y ajusta las listas a los ids nuevos', function () {
  const actual = { favoritos: [], listas: [], presets: [] };
  const mio = fav('Favorita', 'semilla-1');
  actual.favoritos = [mio];
  actual.listas = [S.crearLista('Mi lista', [], 3)];
  actual.presets = [Object.assign({}, VP.PRESETS[2], { nombre: 'Mío' })];
  // lo importado: la misma configuración con otro id, un favorito distinto con el MISMO id que el mío, y una lista que los usa
  const igual = Object.assign({}, mio, { id: 'otro-id', nombre: 'Misma' });
  const choque = Object.assign({}, fav('Distinta', 'semilla-9'), { id: mio.id });
  const homonimo = fav('Favorita', 'semilla-7', { ahora: 77 });
  const lista = S.validarLista({ id: actual.listas[0].id, nombre: 'Mi lista', items: [{ tipo: 'favorito', ref: 'otro-id', minutos: 2 }, { tipo: 'favorito', ref: mio.id, minutos: 3 }, { tipo: 'favorito', ref: homonimo.id }] });
  const imp = { favoritos: [igual, choque, homonimo], listas: [lista], presets: [Object.assign({}, VP.PRESETS[0], { nombre: 'Túnel' }), Object.assign({}, VP.PRESETS[3], { nombre: 'Mío' })] };
  const r = S.fusionar(actual, imp, 9999);
  assert.equal(r.resumen.favoritosRepetidos, 1);
  assert.equal(r.resumen.favoritosNuevos, 2);
  assert.equal(r.favoritos.length, 3);
  assert.equal(new Set(r.favoritos.map(function (f) { return f.id; })).size, 3, 'ids únicos');
  const nuevoChoque = r.favoritos.find(function (f) { return f.semilla === 'semilla-9'; });
  assert.notEqual(nuevoChoque.id, mio.id);
  assert.ok(r.favoritos.some(function (f) { return f.nombre === 'Favorita (importado)' && f.semilla === 'semilla-7'; }));
  // la lista importada apunta a los ids finales: el repetido al mío, el que chocaba a su id nuevo
  const li = r.listas.find(function (l) { return l.nombre === 'Mi lista (importada)'; });
  assert.ok(li);
  assert.notEqual(li.id, actual.listas[0].id);
  assert.deepEqual(li.items.map(function (i) { return i.ref; }), [mio.id, nuevoChoque.id, homonimo.id]);
  // los ajustes de la MilkDrop: uno de fábrica se renombra y uno propio con el mismo nombre se reemplaza
  assert.deepEqual(r.presets.map(function (p) { return p.nombre; }).sort(), ['Mío', 'Túnel (mío)']);
  assert.equal(r.presets.find(function (p) { return p.nombre === 'Mío'; }).zoom, VP.PRESETS[3].zoom);
});

// ------------------------------------------------------------ pistas de melodía elegidas a mano

test('pistas: solo claves bien formadas, y cuentan para decir si dos configuraciones son la misma', function () {
  assert.deepEqual(S.sanearPistas({ a: '1:0', b: 'todas', c: 'xx', d: 7, e: '12345:1', f: '3:12', g: '3:123' }), { a: '1:0', b: 'todas', f: '3:12' });
  assert.deepEqual(S.sanearPistas(null), {});
  assert.deepEqual(S.sanearPistas(['1:0']), {});
  assert.deepEqual(S.sanearPistas({ ['x'.repeat(301)]: '1:0' }), {});
  const base = { semilla: 's', ajustes: AJ, canciones: ['a', 'b'] };
  assert.ok(S.mismaConfiguracion(Object.assign({ pistas: {} }, base), base), 'sin pistas es lo mismo que pistas vacías');
  assert.ok(S.mismaConfiguracion(Object.assign({ pistas: { a: '1:0' } }, base), Object.assign({ pistas: { a: '1:0' } }, base)));
  assert.ok(!S.mismaConfiguracion(Object.assign({ pistas: { a: '1:0' } }, base), Object.assign({ pistas: { a: '2:1' } }, base)));
  assert.ok(!S.mismaConfiguracion(Object.assign({ pistas: { a: '1:0' } }, base), base));
});

test('pistas: el favorito las guarda, no se repite igual con otra pista y vuelven al exportar e importar', function () {
  const f1 = fav('Con cello', 'p', { pistas: { 'propio:x:10': '1:1' } });
  assert.deepEqual(f1.pistas, { 'propio:x:10': '1:1' });
  const sin = fav('Automática', 'p', { ahora: 2000 });
  assert.deepEqual(sin.pistas, {});
  const r = S.agregarFavorito([sin], f1);
  assert.equal(r.duplicado, null, 'la misma semilla con otra pista es otra música');
  assert.equal(r.lista.length, 2);
  assert.ok(S.agregarFavorito(r.lista, Object.assign({}, f1, { id: 'otro' })).duplicado);
  const txt = S.exportarSesion({ ajustes: {}, favoritos: [f1, sin], listas: [], presets: [], canciones: [], estilos: ESTILOS });
  const i = S.importarSesion(txt, { estilos: ESTILOS });
  assert.deepEqual(i.favoritos.map(function (f) { return f.pistas; }), [{ 'propio:x:10': '1:1' }, {}]);
  // un favorito de antes de poder elegir pistas (sin el campo) se lee con las pistas vacías
  const viejo = S.validarFavorito({ id: 'v', semilla: 's', nombre: 'viejo', ajustes: {}, canciones: [] });
  assert.deepEqual(viejo.pistas, {});
});

test('pistas: cambiar la pista en vivo marca la corrida del historial con cambios', function () {
  let h = S.registrarSeccion([], Object.assign({}, CONF, { pistas: {} }), { seccion: 1 }, 1000);
  assert.deepEqual(h[0].pistas, {});
  h = S.registrarSeccion(h, Object.assign({}, CONF, { pistas: { a: '1:0' } }), { seccion: 2 }, 2000);
  assert.equal(h.length, 1);
  assert.equal(h[0].limpia, false);
  assert.deepEqual(h[0].pistas, { a: '1:0' });
  assert.deepEqual(S.validarCorrida({ id: 'h', semilla: 's', pistas: { a: 'todas', b: 'malo' } }).pistas, { a: 'todas' });
});
