'use strict';

// Coherencia del catálogo datos/astros.json: lo que el visor y la tabla suponen.

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const RAIZ = path.join(__dirname, '..');
const DATOS = JSON.parse(fs.readFileSync(path.join(RAIZ, 'datos', 'astros.json'), 'utf8'));
const ASTROS = DATOS.astros;
const HEX = /^#[0-9a-f]{6}$/;
const TIPOS_TEXTURA = new Set(['rocoso', 'bandas', 'nubes', 'tierra', 'volcanico', 'hielo', 'estrella']);

test('hay 12 planetas (con 4 enanos), 21 lunas y 19 soles, con id único', () => {
  const ids = ASTROS.map(a => a.id);
  assert.equal(new Set(ids).size, ids.length, 'ids repetidos');
  const cuenta = c => ASTROS.filter(a => a.categoria === c).length;
  assert.equal(cuenta('planeta'), 12);
  assert.equal(cuenta('luna'), 21);
  assert.equal(cuenta('sol'), 19);
});

test('cada categoría del catálogo existe en categorias', () => {
  for (const a of ASTROS) {
    assert.ok(DATOS.categorias[a.categoria], `categoría desconocida en ${a.id}`);
  }
});

test('cada luna apunta a un cuerpo padre que existe', () => {
  const ids = new Set(ASTROS.map(a => a.id));
  for (const a of ASTROS.filter(x => x.categoria === 'luna')) {
    assert.ok(ids.has(a.padre), `${a.id} tiene padre ${a.padre}, que no existe`);
  }
});

test('planetas y lunas traen radio, masa y periodos; los soles, radio y masa solares', () => {
  for (const a of ASTROS.filter(x => x.categoria !== 'sol')) {
    assert.ok(a.radio_km > 0, `${a.id}: radio`);
    assert.ok(a.masa_kg > 0, `${a.id}: masa`);
    assert.notEqual(a.periodo_rotacion_h, 0, `${a.id}: rotación`);
  }
  for (const a of ASTROS.filter(x => x.categoria === 'sol')) {
    assert.ok(a.radio_solar > 0 && a.masa_solar > 0, `${a.id}: radio o masa solar`);
    assert.ok(a.temperatura_superficial_k > 1000, `${a.id}: temperatura`);
  }
});

test('los planetas traen inclinación del eje (null si no se conoce); las lunas y soles no', () => {
  const desconocida = ['eris', 'makemake'];
  for (const a of ASTROS.filter(x => x.categoria === 'planeta')) {
    if (desconocida.includes(a.id)) {
      assert.equal(a.inclinacion_eje_grados, null, `${a.id}: su inclinación no se conoce`);
      continue;
    }
    assert.equal(typeof a.inclinacion_eje_grados, 'number', `${a.id}: inclinación`);
    assert.ok(a.inclinacion_eje_grados >= 0 && a.inclinacion_eje_grados <= 180, `${a.id}: inclinación fuera de 0..180`);
  }
  for (const a of ASTROS.filter(x => x.categoria !== 'planeta')) {
    assert.equal(a.inclinacion_eje_grados, undefined, `${a.id} no debería tener inclinación`);
  }
});

test('rotación retrógrada solo en Venus, Urano, Plutón, Tritón y Caronte', () => {
  const retro = ASTROS.filter(a => a.periodo_rotacion_h < 0).map(a => a.id).sort();
  assert.deepEqual(retro, ['caronte', 'pluton', 'triton', 'urano', 'venus']);
});

test('las lunas giran sincronizadas: su rotación coincide con su periodo orbital', () => {
  // Todas las lunas del catálogo están en rotación sincrónica; si un dato no cuadra, se copió mal.
  for (const a of ASTROS.filter(x => x.categoria === 'luna')) {
    const orbitaH = a.periodo_orbital_dias * 24;
    const dif = Math.abs(Math.abs(a.periodo_rotacion_h) - orbitaH) / orbitaH;
    assert.ok(dif < 0.02, `${a.id}: rotación ${a.periodo_rotacion_h} h frente a órbita ${orbitaH.toFixed(1)} h`);
  }
});

const G = 6.674e-11;
const MASA_SOL_KG = 1.989e30;
const porId = Object.fromEntries(ASTROS.map(a => [a.id, a]));

// Periodo que da la tercera ley de Kepler para una distancia y una masa central.
function periodoKepler(distanciaKm, masaCentralKg) {
  const a = distanciaKm * 1000;
  return (2 * Math.PI * Math.sqrt((a ** 3) / (G * masaCentralKg))) / 86400;
}

test('el año de cada planeta cuadra con su distancia al Sol (tercera ley de Kepler, 2 %)', () => {
  // Cazó un periodo mal copiado de Makemake (111 años en vez de unos 306).
  for (const a of ASTROS.filter(x => x.categoria === 'planeta')) {
    const esperado = periodoKepler(a.distancia_media.valor * 1e6, MASA_SOL_KG);
    const dif = Math.abs(a.periodo_orbital_dias - esperado) / esperado;
    assert.ok(dif < 0.02, `${a.id}: periodo ${a.periodo_orbital_dias} d frente a ${esperado.toFixed(0)} d por Kepler`);
  }
});

test('el periodo de cada luna cuadra con su distancia y la masa de su cuerpo padre (Kepler, 3 %)', () => {
  for (const a of ASTROS.filter(x => x.categoria === 'luna')) {
    const padre = porId[a.padre];
    const esperado = periodoKepler(a.distancia_media.valor, padre.masa_kg + a.masa_kg);
    const dif = Math.abs(a.periodo_orbital_dias - esperado) / esperado;
    assert.ok(dif < 0.03, `${a.id}: periodo ${a.periodo_orbital_dias} d frente a ${esperado.toFixed(3)} d por Kepler`);
  }
});

test('la distancia de cada luna se mide al cuerpo padre y lo nombra bien', () => {
  for (const a of ASTROS.filter(x => x.categoria === 'luna')) {
    assert.equal(a.distancia_media.unidad, 'km', `${a.id}: unidad`);
    assert.equal(a.distancia_media.referencia, porId[a.padre].nombre, `${a.id}: referencia`);
  }
  for (const a of ASTROS.filter(x => x.categoria === 'planeta')) {
    assert.equal(a.distancia_media.referencia, 'Sol', `${a.id}: referencia`);
  }
});

test('las lunas conocidas de cada planeta no son menos que las que trae el catálogo', () => {
  for (const a of ASTROS.filter(x => x.categoria === 'planeta')) {
    const enCatalogo = ASTROS.filter(x => x.categoria === 'luna' && x.padre === a.id).length;
    assert.ok(a.lunas >= enCatalogo, `${a.id}: dice ${a.lunas} lunas y el catálogo trae ${enCatalogo}`);
  }
});

test('Caronte gira a la par de Plutón: el mismo periodo y el mismo sentido', () => {
  const caronte = ASTROS.find(a => a.id === 'caronte');
  const pluton = ASTROS.find(a => a.id === 'pluton');
  assert.equal(caronte.periodo_rotacion_h, pluton.periodo_rotacion_h);
});

test('cada astro trae una textura con tipo válido y colores hexadecimales', () => {
  for (const a of ASTROS) {
    const tx = a.textura;
    assert.ok(TIPOS_TEXTURA.has(tx.tipo), `${a.id}: tipo ${tx.tipo}`);
    for (const c of [...(tx.colores || []), ...(tx.oceano || []), ...(tx.tierra || [])]) {
      assert.match(c, HEX, `${a.id}: color ${c}`);
    }
    for (const c of [tx.halo, tx.hielo, tx.nubes, tx.casquetes, tx.lineas]) {
      if (c !== undefined) assert.match(c, HEX, `${a.id}: color ${c}`);
    }
  }
});

// Parámetros que exige cada tipo de textura y los que se admiten en total. Un astro al que le falte
// uno dibujaría mal o rompería el visor; uno desconocido suele ser un nombre mal escrito.
const EXIGE = {
  rocoso: ['colores'],
  bandas: ['colores', 'bandas', 'contraste'],
  nubes: ['colores'],
  tierra: ['oceano', 'tierra', 'hielo', 'nubes'],
  volcanico: ['colores'],
  hielo: ['colores'],
  estrella: ['color', 'granulos', 'escala']
};
const ADMITIDOS = new Set([
  'tipo', 'colores', 'oceano', 'tierra', 'hielo', 'nubes', 'bandas', 'bandas_amplitud',
  'contraste', 'deformacion', 'grano', 'mancha', 'halo', 'anillos', 'craters', 'escala',
  'maria', 'casquetes', 'lineas', 'dicotomia', 'volcanes', 'granulos', 'color', 'relieve', 'capa_nubes'
]);

test('cada tipo de textura trae los parámetros que necesita y ninguno desconocido', () => {
  for (const a of ASTROS) {
    const tx = a.textura;
    for (const k of EXIGE[tx.tipo]) assert.notEqual(tx[k], undefined, `${a.id} (${tx.tipo}): falta ${k}`);
    for (const k of Object.keys(tx)) assert.ok(ADMITIDOS.has(k), `${a.id}: parámetro de textura desconocido «${k}»`);
  }
});

test('las texturas volcánicas traen los cinco colores que usa su dibujo', () => {
  for (const a of ASTROS.filter(x => x.textura.tipo === 'volcanico')) {
    assert.ok(a.textura.colores.length >= 5, `${a.id}: necesita 5 colores`);
  }
});

test('las paletas que se interpolan tienen al menos dos colores', () => {
  for (const a of ASTROS.filter(x => x.categoria !== 'sol')) {
    const tx = a.textura;
    if (tx.tipo === 'tierra') {
      assert.ok(tx.oceano.length >= 2 && tx.tierra.length >= 2, `${a.id}: paletas de tierra`);
    } else {
      assert.ok(tx.colores.length >= 2, `${a.id}: paleta`);
    }
  }
});

test('las manchas tienen latitud y longitud dentro de rango', () => {
  for (const a of ASTROS) {
    const m = a.textura.mancha;
    if (!m) continue;
    assert.ok(m.lat >= -90 && m.lat <= 90, `${a.id}: lat`);
    assert.ok(m.lon >= -360 && m.lon <= 360, `${a.id}: lon`);
    assert.ok(m.rx > 0 && m.ry > 0, `${a.id}: radios de la mancha`);
  }
});

test('los anillos tienen interior menor que exterior, en radios del planeta', () => {
  for (const a of ASTROS) {
    const an = a.textura.anillos;
    if (!an) continue;
    assert.ok(an.interior > 1 && an.exterior > an.interior, `${a.id}: anillos`);
    assert.ok(an.colores.length >= 2, `${a.id}: colores de anillo`);
  }
});

test('los anillos aparecen solo en los planetas que los tienen', () => {
  const con = ASTROS.filter(a => a.anillos === true).map(a => a.id).sort();
  assert.deepEqual(con, ['jupiter', 'neptuno', 'saturno', 'urano']);
  for (const a of ASTROS.filter(x => x.categoria === 'planeta')) {
    assert.equal(!!a.textura.anillos, a.anillos, `${a.id}: anillos en datos y textura`);
  }
});

test('cada estrella trae constelación; el signo zodiacal solo las seis de constelaciones zodiacales', () => {
  const soles = ASTROS.filter(a => a.categoria === 'sol' && a.id !== 'sol');
  for (const a of soles) assert.ok(a.constelacion, `${a.id}: constelación`);
  const conSigno = soles.filter(a => a.signo).map(a => `${a.id}:${a.signo}`).sort();
  assert.deepEqual(conSigno, [
    'aldebaran:Tauro', 'antares:Escorpio', 'hamal:Aries', 'polux:Géminis', 'regulus:Leo', 'spica:Virgo'
  ]);
});

test('cada estrella tiene un color propio y distinto de las demás', () => {
  const colores = ASTROS.filter(a => a.categoria === 'sol').map(a => a.textura.color);
  for (const c of colores) assert.match(c, HEX);
  assert.equal(new Set(colores).size, colores.length, 'hay colores repetidos entre estrellas');
});

test('el color de cada estrella respeta su familia: las calientes son azuladas y las frías, rojizas', () => {
  for (const a of ASTROS.filter(x => x.categoria === 'sol')) {
    const [r, , b] = [1, 3, 5].map(i => parseInt(a.textura.color.slice(i, i + 2), 16));
    if (a.temperatura_superficial_k >= 10000) assert.ok(b >= r, `${a.id}: debería ser azulada`);
    if (a.temperatura_superficial_k <= 4600) assert.ok(r - b >= 50, `${a.id}: debería ser rojiza o anaranjada`);
  }
});

test('la edad de una estrella es positiva o no consta (null)', () => {
  for (const a of ASTROS.filter(x => x.categoria === 'sol')) {
    const e = a.edad_aprox_mil_millones_anios;
    assert.ok(e === null || e > 0, `${a.id}: edad`);
  }
});

test('la incertidumbre de radio solo toma los tres valores previstos', () => {
  for (const a of ASTROS.filter(x => x.categoria === 'sol')) {
    assert.ok(['baja', 'media', 'alta'].includes(a.incertidumbre_radio), `${a.id}`);
  }
});
