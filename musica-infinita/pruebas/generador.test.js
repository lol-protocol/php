'use strict';

// El generador: determinista, continuo, dentro del rango y obediente a sus ajustes.

const test = require('node:test');
const assert = require('node:assert/strict');
const M = require('../js/midi.js');
const A = require('../js/analisis.js');
const E = require('../js/ejemplos.js');
const G = require('../js/generador.js');
const T = require('../js/teoria.js');

const CANCIONES = E.EJEMPLOS.map(function (ej) { return A.analizar(M.leer(E.aMidi(ej)), ej.nombre); });

function generar(opciones, segundos) {
  const g = new G.Generador(Object.assign({ canciones: CANCIONES, semilla: 1234 }, opciones));
  return g.eventosHasta(segundos || 240);
}

test('misma semilla, misma música; otra semilla, otra', function () {
  const a = JSON.stringify(generar({ semilla: 'hola' }, 120));
  const b = JSON.stringify(generar({ semilla: 'hola' }, 120));
  const c = JSON.stringify(generar({ semilla: 'chao' }, 120));
  assert.equal(a, b);
  assert.notEqual(a, c);
});

test('pedir de a poco da lo mismo que pedir de una vez', function () {
  const g = new G.Generador({ canciones: CANCIONES, semilla: 77 });
  let poco = [];
  for (let t = 0.5; t <= 150; t += 0.37) poco = poco.concat(g.eventosHasta(t));
  const todo = generar({ semilla: 77 }, 150);
  assert.equal(poco.length, todo.length);
  assert.deepEqual(poco.map(function (e) { return e.t; }), todo.map(function (e) { return e.t; }));
});

test('los eventos son válidos y están en orden', function () {
  const evs = generar({}, 600);
  let previo = -Infinity;
  for (const e of evs) {
    assert.ok(e.t >= previo, 'fuera de orden');
    previo = e.t;
    assert.ok(Number.isFinite(e.t) && Number.isFinite(e.dur) && e.dur > 0);
    if (e.tipo === 'info') continue;
    assert.ok(Number.isInteger(e.nota) && e.nota >= 21 && e.nota <= 108, 'nota ' + e.nota);
    assert.ok(e.vel >= 1 && e.vel <= 127);
    assert.ok([0, 1, 2, 3, 4, 9].indexOf(e.canal) >= 0);
  }
});

test('la música no se corta: los bloques van uno tras otro sin huecos', function () {
  const infos = generar({}, 900).filter(function (e) { return e.tipo === 'info'; });
  assert.ok(infos.length > 20);
  for (let i = 1; i < infos.length; i++) {
    const fin = infos[i - 1].t + infos[i - 1].dur;
    assert.ok(Math.abs(infos[i].t - fin) < 1e-6, 'hueco o solape en el bloque ' + i);
  }
  // y va cambiando: varias secciones, tonalidades y estilos
  const secciones = new Set(infos.map(function (i) { return i.seccion; }));
  const tonos = new Set(infos.map(function (i) { return i.tonalidad; }));
  const estilos = new Set(infos.map(function (i) { return i.estilo; }));
  assert.ok(secciones.size >= 5, 'secciones: ' + secciones.size);
  assert.ok(tonos.size >= 2, 'tonalidades: ' + tonos.size);
  assert.ok(estilos.size >= 2, 'estilos: ' + estilos.size);
});

test('fidelidad 1: todo son citas; fidelidad 0: todo es frase libre', function () {
  const citas = generar({ fidelidad: 1 }, 300).filter(function (e) { return e.tipo === 'info'; });
  const libres = generar({ fidelidad: 0 }, 300).filter(function (e) { return e.tipo === 'info'; });
  assert.ok(citas.every(function (i) { return i.material === 'cita' && i.cancion; }));
  assert.ok(libres.every(function (i) { return i.material === 'libre'; }));
  // las citas nombran canciones de la biblioteca
  const nombres = new Set(CANCIONES.map(function (c) { return c.nombre; }));
  assert.ok(citas.every(function (i) { return nombres.has(i.cancion); }));
});

test('una cita sin variación suena como la frase original (transportada)', function () {
  const g = new G.Generador({ canciones: [CANCIONES[0]], semilla: 3, fidelidad: 1, variacion: 0, humanizar: false });
  const evs = g.eventosHasta(60);
  const info = evs.find(function (e) { return e.tipo === 'info'; });
  const melodia = evs.filter(function (e) { return e.voz === 'melodia' && e.t < info.t + info.dur - 1e-6; });
  const original = CANCIONES[0];
  const f = original.frases[info.frase - 1];
  const intervalosOrig = [];
  for (let i = f.desde + 1; i < f.hasta && i - f.desde < melodia.length; i++) {
    const a = T.notaDeGrado(original.melodia[i - 1].g, original.melodia[i - 1].alt, original.tonica, original.modo);
    const b = T.notaDeGrado(original.melodia[i].g, original.melodia[i].alt, original.tonica, original.modo);
    intervalosOrig.push(b - a);
  }
  const intervalos = [];
  for (let i = 1; i < melodia.length && intervalos.length < intervalosOrig.length; i++) intervalos.push(melodia[i].nota - melodia[i - 1].nota);
  // misma tonalidad y modo que la canción (es la primera sección): mismos intervalos
  assert.deepEqual(intervalos, intervalosOrig);
});

test('las frases libres quedan en la escala de su sección', function () {
  const evs = generar({ fidelidad: 0, semilla: 9 }, 400);
  let info = null;
  let dentro = 0;
  let total = 0;
  for (const e of evs) {
    if (e.tipo === 'info') {
      info = e;
      continue;
    }
    if (e.voz !== 'melodia') continue;
    const ton = T.NOMBRES.indexOf(info.tonalidad.split(' ')[0]);
    const modo = info.modo;
    total++;
    if (T.gradoDe(e.nota, ton, modo).alt === 0) dentro++;
  }
  assert.ok(total > 50);
  assert.equal(dentro, total);
});

test('estilo fijo y tempo', function () {
  const evs = generar({ estilo: 'chip', tempo: 1.2 }, 200);
  const infos = evs.filter(function (e) { return e.tipo === 'info'; });
  assert.ok(infos.every(function (i) { return i.estilo === 'Chiptune'; }));
  assert.ok(evs.some(function (e) { return e.canal === 9; }), 'el chiptune lleva batería');
  const sinBateria = generar({ estilo: 'ambiente' }, 200);
  assert.ok(!sinBateria.some(function (e) { return e.canal === 9; }));
});

test('saltar corta lo que viene y empieza otra sección ahí', function () {
  const g = new G.Generador({ canciones: CANCIONES, semilla: 5 });
  g.eventosHasta(20);
  const antes = g.seccion.numero;
  g.saltar(20);
  const evs = g.eventosHasta(40);
  assert.ok(evs.every(function (e) { return e.t >= 20 - 1e-9; }));
  assert.equal(evs[0].tipo, 'info');
  assert.ok(evs[0].seccion > antes);
});

test('sin canciones también suena (paso a paso por la escala)', function () {
  const evs = new G.Generador({ canciones: [], semilla: 1 }).eventosHasta(60);
  assert.ok(evs.filter(function (e) { return e.voz === 'melodia'; }).length > 10);
});

test('conducción de voces: el acorde siguiente queda cerca del anterior', function () {
  const c = G.conducir(null, [0, 4, 7]);
  const f = G.conducir(c, [5, 9, 0]);
  const mov = f.reduce(function (s, n, i) { return s + Math.abs(n - c[i]); }, 0);
  assert.ok(mov <= 4, 'movimiento ' + mov + ': ' + c + ' -> ' + f);
});

test('eventosDeMidi: un MIDI tal cual', function () {
  const midi = M.leer(E.aMidi(E.EJEMPLOS[0]));
  const evs = G.eventosDeMidi(midi, 10);
  assert.equal(evs.length, midi.notas.length);
  assert.ok(evs.every(function (e) { return e.t >= 10; }));
});

// ------------------------------------------------------------ color modal, cadencias, contramelodía, compás

const SIN_AZAR = { humanizar: false, estilo: 'orquestal' };

function infosYNotas(opciones, segundos) {
  const evs = generar(Object.assign({}, SIN_AZAR, opciones), segundos);
  return { evs: evs, infos: evs.filter(function (e) { return e.tipo === 'info'; }), notas: evs.filter(function (e) { return e.tipo !== 'info'; }) };
}

test('color modal: con colorModal 0 todo es mayor o menor; con 1, las frases libres son modales y caen en su escala', function () {
  const base = infosYNotas({ fidelidad: 0, colorModal: 0, semilla: 21 }, 600);
  assert.ok(base.infos.every(function (i) { return i.modo === 'mayor' || i.modo === 'menor'; }));
  const col = infosYNotas({ fidelidad: 0, colorModal: 1, semilla: 21 }, 900);
  const modos = new Set(col.infos.map(function (i) { return i.modo; }));
  assert.ok(Array.from(modos).every(function (m) { return ['dorica', 'frigia', 'mixolidia', 'lidia'].indexOf(m) >= 0; }), Array.from(modos).join());
  assert.ok(modos.size >= 2, 'varios modos distintos: ' + Array.from(modos).join());
  // el rótulo de la pantalla lo dice en español
  assert.ok(col.infos.every(function (i) { return /dórica|frigia|mixolidia|lidia/.test(i.tonalidad); }));
  // la melodía queda en la escala del modo de su bloque
  let info = null;
  let fuera = 0;
  let total = 0;
  for (const e of col.evs) {
    if (e.tipo === 'info') { info = e; continue; }
    if (e.voz !== 'melodia') continue;
    total++;
    if (T.gradoDe(e.nota, T.NOMBRES.indexOf(info.tonalidad.split(' ')[0]), info.modo).alt !== 0) fuera++;
  }
  assert.ok(total > 80);
  assert.equal(fuera, 0);
});

test('las citas siguen en el modo de su canción aunque la sección sea modal', function () {
  const r = infosYNotas({ fidelidad: 1, colorModal: 1, semilla: 4 }, 300);
  assert.ok(r.infos.every(function (i) { return i.material === 'cita' && (i.modo === 'mayor' || i.modo === 'menor'); }));
});

test('las progresiones modales usan solo acordes de su escala', function () {
  const g = new G.Generador({ canciones: CANCIONES, semilla: 1 });
  for (const modo of ['dorica', 'mixolidia', 'lidia', 'frigia']) {
    const escala = T.ESCALAS[modo];
    for (const cad of ['libre', 'semi', 'autentica', 'plagal']) {
      for (let k = 0; k < 20; k++) {
        const acordes = g.progresion({ pulsosCompas: k % 2 ? 3 : 4, modo: 'mayor' }, k % 2 ? 6 : 8, modo, cad);
        for (const a of acordes) {
          for (const pc of T.notasAcorde(a.raizRel, a.tipo)) assert.ok(escala.indexOf(pc) >= 0, modo + ' ' + cad + ': ' + a.raizRel + ':' + a.tipo + ' trae ' + pc + ' fuera de la escala');
        }
      }
    }
  }
});

test('cadencias: semi sobre la dominante, auténtica y plagal terminan donde deben', function () {
  const g = new G.Generador({ canciones: CANCIONES, semilla: 2 });
  const sec = { pulsosCompas: 4, modo: 'mayor' };
  const fin = function (modo, cad) {
    const a = g.progresion(sec, 8, modo, cad);
    return { pen: a[a.length - 2], ult: a[a.length - 1] };
  };
  assert.deepEqual([fin('mayor', 'semi').ult.raizRel, fin('mayor', 'semi').ult.tipo], [7, 'M']);
  assert.deepEqual([fin('menor', 'autentica').pen.raizRel, fin('menor', 'autentica').pen.tipo, fin('menor', 'autentica').ult.raizRel, fin('menor', 'autentica').ult.tipo], [7, 'M', 0, 'm']);
  assert.deepEqual([fin('mayor', 'plagal').pen.raizRel, fin('mayor', 'plagal').ult.raizRel], [5, 0]);
  assert.deepEqual([fin('mixolidia', 'autentica').pen.raizRel, fin('mixolidia', 'autentica').ult.raizRel], [10, 0]); // bVII-I
  assert.deepEqual([fin('dorica', 'autentica').pen.raizRel, fin('dorica', 'autentica').ult.tipo], [5, 'm']); // IV-i
});

test('la última frase de cada sección cierra: acorde de tónica y la melodía cae sobre ella', function () {
  const r = infosYNotas({ fidelidad: 0, semilla: 31, colorModal: 0.5 }, 1500);
  const secciones = new Map();
  r.infos.forEach(function (i) { secciones.set(i.seccion, (secciones.get(i.seccion) || []).concat([i])); });
  const numeros = Array.from(secciones.keys());
  let comprobadas = 0;
  for (const n of numeros.slice(0, -1)) { // la última sección puede estar a medias
    const bloques = secciones.get(n);
    const ultimo = bloques[bloques.length - 1];
    assert.equal(ultimo.cadencia, 'autentica', 'sección ' + n);
    const ton = T.NOMBRES.indexOf(ultimo.tonalidad.split(' ')[0]);
    const nombresTonica = ['M', 'm', 'maj7', 'm7', '7'].map(function (t) { return T.nombreAcorde(ton, t); });
    assert.ok(nombresTonica.indexOf(ultimo.acordes[ultimo.acordes.length - 1]) >= 0, 'sección ' + n + ': ' + ultimo.acordes.join(' ') + ' no termina en ' + nombresTonica[0]);
    const finBloque = ultimo.t + ultimo.dur;
    const mel = r.notas.filter(function (e) { return e.voz === 'melodia' && e.t >= ultimo.t - 1e-9 && e.t < finBloque - 1e-9; });
    assert.equal(T.mod(mel[mel.length - 1].nota - ton, 12), 0, 'sección ' + n + ': la melodía no cae en la tónica');
    comprobadas++;
  }
  assert.ok(comprobadas >= 8, 'secciones comprobadas: ' + comprobadas);
});

test('contramelodía: con contra 0 no hay; con 1 aparece, en su canal y siempre por debajo de la melodía', function () {
  assert.ok(!infosYNotas({ contra: 0, semilla: 8 }, 600).notas.some(function (e) { return e.voz === 'contra'; }));
  const r = infosYNotas({ contra: 1, semilla: 8, fidelidad: 0.5 }, 1200);
  const contra = r.notas.filter(function (e) { return e.voz === 'contra'; });
  assert.ok(contra.length > 40, 'notas de contramelodía: ' + contra.length);
  assert.ok(contra.every(function (e) { return e.canal === 4 && e.nota >= 21 && e.nota <= 108; }));
  assert.ok(r.infos.some(function (i) { return i.contra; }) && r.infos.some(function (i) { return !i.contra; }) || r.infos.every(function (i) { return i.contra; }));
  // donde suena a la vez que la melodía, queda debajo (nunca unísono ni encima)
  const mel = new Map();
  for (const e of r.notas) if (e.voz === 'melodia') mel.set(e.t.toFixed(4), e.nota);
  let juntas = 0;
  for (const c of contra) {
    const m = mel.get(c.t.toFixed(4));
    if (m == null) continue;
    juntas++;
    assert.ok(c.nota < m, 'contra ' + c.nota + ' contra melodía ' + m);
  }
  assert.ok(juntas > 20, 'notas a la vez: ' + juntas);
});

test('la contramelodía de respuesta llena silencios y la de terceras sigue a la melodía', function () {
  const g = new G.Generador({ canciones: CANCIONES, semilla: 6, humanizar: false });
  const mat = { pulsos: 8, notas: [{ inicio: 0, dur: 1, g: 4, vel: 90 }, { inicio: 3, dur: 1, g: 5, vel: 90 }, { inicio: 4, dur: 4, g: 2, vel: 90 }], acordes: [{ inicio: 0, pulsos: 8, raizRel: 0, tipo: 'M' }] };
  const resp = g.contramelodia(mat, { contraTipo: 'respuesta', pulsosCompas: 4, tonica: 0, modo: 'mayor' }, 'mayor');
  // el silencio entre 1 y 3 se llena con grados conjuntos, y la nota larga lleva una nota debajo
  const enSilencio = resp.filter(function (n) { return n.inicio >= 1 && n.inicio < 3; });
  assert.ok(enSilencio.length >= 2);
  for (let i = 1; i < enSilencio.length; i++) assert.equal(Math.abs(enSilencio[i].g - enSilencio[i - 1].g), 1);
  assert.ok(resp.some(function (n) { return n.inicio === 4 && n.dur === 4 && n.g < 2; }));
  const ter = g.contramelodia(mat, { contraTipo: 'terceras', pulsosCompas: 4, tonica: 0, modo: 'mayor' }, 'mayor');
  assert.ok(ter.length >= 2);
  for (const n of ter) assert.ok(n.g < mat.notas.find(function (m) { return m.inicio === n.inicio; }).g);
});

test('compás: con cambiosCompas 0 se queda en el de la canción; con 1 alterna 3/4 y 4/4', function () {
  const una = [CANCIONES[0]]; // el Himno, en 4/4
  const fijo = new G.Generador({ canciones: una, semilla: 3, cambiosCompas: 0 }).eventosHasta(900).filter(function (e) { return e.tipo === 'info'; });
  assert.ok(fijo.every(function (i) { return i.compas === '4/4'; }));
  const cambia = new G.Generador({ canciones: una, semilla: 3, cambiosCompas: 1 }).eventosHasta(900).filter(function (e) { return e.tipo === 'info'; });
  const compases = new Set(cambia.map(function (i) { return i.compas; }));
  assert.ok(compases.has('3/4') && compases.has('4/4'));
  // dentro de una sección el compás no cambia
  const porSeccion = new Map();
  for (const i of cambia) {
    if (porSeccion.has(i.seccion)) assert.equal(porSeccion.get(i.seccion), i.compas);
    porSeccion.set(i.seccion, i.compas);
  }
});

test('las citas salen de canciones del mismo compás de la sección', function () {
  const tres = new Set(CANCIONES.filter(function (c) { return c.pulsosCompas === 3; }).map(function (c) { return c.nombre; }));
  assert.ok(tres.size >= 2);
  const infos = infosYNotas({ fidelidad: 1, cambiosCompas: 1, semilla: 12 }, 4800).infos;
  const en3 = infos.filter(function (i) { return i.compas === '3/4'; });
  assert.ok(en3.length > 30);
  const buenas = en3.filter(function (i) { return tres.has(i.cancion); }).length;
  assert.ok(buenas / en3.length > 0.7, 'citas en 3/4 de canciones en 3/4: ' + buenas + ' de ' + en3.length);
});
