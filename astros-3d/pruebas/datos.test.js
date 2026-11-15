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

test('hay 9 planetas, 10 lunas y 6 soles, con id único', () => {
  const ids = ASTROS.map(a => a.id);
  assert.equal(new Set(ids).size, ids.length, 'ids repetidos');
  const cuenta = c => ASTROS.filter(a => a.categoria === c).length;
  assert.equal(cuenta('planeta'), 9);
  assert.equal(cuenta('luna'), 10);
  assert.equal(cuenta('sol'), 6);
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

test('los planetas traen inclinación del eje; las lunas y soles no', () => {
  for (const a of ASTROS.filter(x => x.categoria === 'planeta')) {
    assert.equal(typeof a.inclinacion_eje_grados, 'number', `${a.id}: inclinación`);
    assert.ok(a.inclinacion_eje_grados >= 0 && a.inclinacion_eje_grados <= 180, `${a.id}: inclinación fuera de 0..180`);
  }
  for (const a of ASTROS.filter(x => x.categoria !== 'planeta')) {
    assert.equal(a.inclinacion_eje_grados, undefined, `${a.id} no debería tener inclinación`);
  }
});

test('rotación retrógrada solo en Venus, Urano, Plutón y Tritón', () => {
  const retro = ASTROS.filter(a => a.periodo_rotacion_h < 0).map(a => a.id).sort();
  assert.deepEqual(retro, ['pluton', 'triton', 'urano', 'venus']);
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

test('la incertidumbre de radio solo toma los tres valores previstos', () => {
  for (const a of ASTROS.filter(x => x.categoria === 'sol')) {
    assert.ok(['baja', 'media', 'alta'].includes(a.incertidumbre_radio), `${a.id}`);
  }
});
