'use strict';

// Arma una SoundFont mínima y válida, para probar el lector sin bajar una de verdad.
//
//   Preset 0:0  "Piano de prueba": dos zonas (graves con bucle, agudos con raíz forzada).
//   Preset 0:1  "Modulado": vibrato, LFO de modulación, envolvente de modulación y dos
//               moduladores explícitos (velocidad al filtro y nota al paneo).
//   Preset 0:2  "Estéreo": una muestra izquierda y una derecha enlazadas, sin paneo.
//   Preset 128:0 "Batería": una zona de bombo con clase exclusiva.
//
// Las muestras son senos con bucle. Con opciones.ogg (cuatro trozos de Ogg Vorbis) arma una
// SoundFont 3: las muestras son esos trozos y la versión del archivo es la 3.

function trozo(id, datos) {
  const b = Buffer.alloc(8 + datos.length + (datos.length & 1));
  b.write(id, 0, 'latin1');
  b.writeUInt32LE(datos.length, 4);
  Buffer.from(datos).copy(b, 8);
  return b;
}

function lista(tipo, trozos) {
  const cuerpo = Buffer.concat([Buffer.from(tipo, 'latin1')].concat(trozos));
  return trozo('LIST', cuerpo);
}

function nombre20(s) {
  const b = Buffer.alloc(20);
  b.write(s.slice(0, 19), 0, 'latin1');
  return b;
}

function gen(op, valor) {
  const b = Buffer.alloc(4);
  b.writeUInt16LE(op, 0);
  b.writeInt16LE(valor, 2);
  return b;
}

function rango(op, lo, hi) {
  const b = Buffer.alloc(4);
  b.writeUInt16LE(op, 0);
  b[2] = lo;
  b[3] = hi;
  return b;
}

function bag(genNdx, modNdx) {
  const b = Buffer.alloc(4);
  b.writeUInt16LE(genNdx, 0);
  b.writeUInt16LE(modNdx || 0, 2);
  return b;
}

function mod(src, dest, cant, amtSrc, trans) {
  const b = Buffer.alloc(10);
  b.writeUInt16LE(src, 0);
  b.writeUInt16LE(dest, 2);
  b.writeInt16LE(cant, 4);
  b.writeUInt16LE(amtSrc || 0, 6);
  b.writeUInt16LE(trans || 0, 8);
  return b;
}

function crear(opciones) {
  opciones = opciones || {};
  const fr = 22050;
  const largo = fr; // 1 s
  // muestras 0 y 2/3: seno de 440 Hz (nota 69); muestra 1: seno de 110 Hz (nota 45)
  const defs = [
    { f: 440, tono: 69, tipo: 1, correccion: -5 },
    { f: 110, tono: 45, tipo: 1, correccion: 0 },
    { f: 440, tono: 69, tipo: 4, correccion: 0 }, // izquierda
    { f: 440, tono: 69, tipo: 2, correccion: 0 }, // derecha
  ];
  const muestras = [];
  let smpl;
  if (opciones.ogg) {
    // SoundFont 3: inicio y fin son bytes; los bucles, muestras desde el inicio de la propia muestra
    const trozos = [];
    let pos = 0;
    defs.forEach(function (d, i) {
      const bytes = Buffer.from(opciones.ogg[i % opciones.ogg.length]);
      muestras.push({ ini: pos, fin: pos + bytes.length, loopIni: Math.round(fr / 2), loopFin: Math.round(fr / 2 + (fr / d.f) * 20), fr: fr, tono: d.tono, tipo: d.tipo | 0x10, correccion: d.correccion });
      trozos.push(bytes);
      pos += bytes.length;
    });
    smpl = Buffer.concat(trozos);
  } else {
    const datos = [];
    let pos = 0;
    defs.forEach(function (d) {
      const ini = pos;
      for (let i = 0; i < largo; i++) datos.push(Math.round(16000 * Math.sin((2 * Math.PI * d.f * i) / fr)));
      for (let i = 0; i < 46; i++) datos.push(0); // 46 ceros entre muestras, como pide la norma
      pos += largo + 46;
      muestras.push({ ini: ini, fin: ini + largo, loopIni: ini + fr / 2, loopFin: ini + fr / 2 + (fr / d.f) * 20, fr: fr, tono: d.tono, tipo: d.tipo, correccion: d.correccion });
    });
    smpl = Buffer.alloc(datos.length * 2);
    datos.forEach(function (v, i) { smpl.writeInt16LE(v, i * 2); });
  }

  // instrumentos: 0 "Piano", 1 "Bombo", 2 "Modulado", 3 "Estéreo"
  const igen = [];
  const ibag = [];
  const imod = [];
  const nuevaZona = function () { ibag.push(bag(igen.length, imod.length)); };
  // Piano: zona global
  nuevaZona();
  igen.push(gen(34, -2400)); // attackVolEnv: 2^(-2) = 0,25 s
  igen.push(gen(48, 60)); // initialAttenuation 6 dB
  // zona grave: teclas 0-59, muestra 1 con bucle continuo
  nuevaZona();
  igen.push(rango(43, 0, 59));
  igen.push(gen(54, 1));
  igen.push(gen(53, 1));
  // zona aguda: teclas 60-127, muestra 0, raíz forzada a 81, paneo a la derecha
  nuevaZona();
  igen.push(rango(43, 60, 127));
  igen.push(gen(58, 81));
  igen.push(gen(17, 250));
  igen.push(gen(52, 10)); // fineTune +10 cents
  igen.push(gen(53, 0));
  // Bombo
  nuevaZona();
  igen.push(rango(43, 35, 36));
  igen.push(gen(57, 3)); // exclusiveClass
  igen.push(gen(53, 1));
  // Modulado
  nuevaZona();
  igen.push(gen(6, 50)); // vibLfoToPitch: 50 cents
  igen.push(gen(24, -1200)); // freqVibLFO: 4,09 Hz
  igen.push(gen(23, -1200)); // delayVibLFO: 0,5 s
  igen.push(gen(5, 100)); // modLfoToPitch
  igen.push(gen(10, -1200)); // modLfoToFilterFc
  igen.push(gen(13, 60)); // modLfoToVolume: 6 dB
  igen.push(gen(22, 0)); // freqModLFO: 8,176 Hz
  igen.push(gen(7, 1200)); // modEnvToPitch: una octava
  igen.push(gen(11, 2400)); // modEnvToFilterFc
  igen.push(gen(8, 6000)); // initialFilterFc: ~ 232 Hz
  igen.push(gen(26, -1200)); // attackModEnv: 0,5 s
  igen.push(gen(29, 500)); // sustainModEnv: la mitad
  igen.push(gen(28, -1200)); // decayModEnv
  igen.push(gen(53, 0));
  // velocidad al filtro, explícito: -3600 cents a velocidad 127 (lineal positivo), y nota al paneo (bipolar)
  imod.push(mod(0x0002, 8, -3600, 0, 0));
  imod.push(mod(0x0203, 17, 500, 0, 0));
  // Estéreo: una izquierda y una derecha, sin paneo
  nuevaZona();
  igen.push(gen(53, 2));
  nuevaZona();
  igen.push(gen(53, 3));
  // terminales
  ibag.push(bag(igen.length, imod.length));
  igen.push(Buffer.alloc(4));
  imod.push(Buffer.alloc(10));

  const inst = [];
  const insReg = function (n, b) {
    const r = Buffer.alloc(22);
    nombre20(n).copy(r, 0);
    r.writeUInt16LE(b, 20);
    inst.push(r);
  };
  insReg('Piano', 0);
  insReg('Bombo', 3);
  insReg('Modulado', 4);
  insReg('Estereo', 5);
  insReg('EOI', 7);

  const pgen = [];
  const pbag = [];
  // preset Piano: una zona con +20 cB de atenuación (se suma a la del instrumento)
  pbag.push(bag(pgen.length));
  pgen.push(gen(48, 20));
  pgen.push(gen(41, 0));
  // preset Batería
  pbag.push(bag(pgen.length));
  pgen.push(gen(41, 1));
  // preset Modulado
  pbag.push(bag(pgen.length));
  pgen.push(gen(41, 2));
  // preset Estéreo
  pbag.push(bag(pgen.length));
  pgen.push(gen(41, 3));
  pbag.push(bag(pgen.length));
  pgen.push(Buffer.alloc(4));

  const phdr = [];
  const preReg = function (n, prog, banco, b) {
    const r = Buffer.alloc(38);
    nombre20(n).copy(r, 0);
    r.writeUInt16LE(prog, 20);
    r.writeUInt16LE(banco, 22);
    r.writeUInt16LE(b, 24);
    phdr.push(r);
  };
  preReg('Piano de prueba', 0, 0, 0);
  preReg('Bateria de prueba', 0, 128, 1);
  preReg('Modulado', 1, 0, 2);
  preReg('Estereo', 2, 0, 3);
  preReg('EOP', 0, 0, 4);

  const shdr = [];
  muestras.forEach(function (m, i) {
    const r = Buffer.alloc(46);
    nombre20('seno' + i).copy(r, 0);
    r.writeUInt32LE(m.ini, 20);
    r.writeUInt32LE(m.fin, 24);
    r.writeUInt32LE(Math.round(m.loopIni), 28);
    r.writeUInt32LE(Math.round(m.loopFin), 32);
    r.writeUInt32LE(m.fr, 36);
    r[40] = m.tono;
    r.writeInt8(m.correccion, 41);
    r.writeUInt16LE(m.tipo, 44);
    shdr.push(r);
  });
  shdr.push(Buffer.alloc(46)); // EOS

  const pdta = lista('pdta', [
    trozo('phdr', Buffer.concat(phdr)),
    trozo('pbag', Buffer.concat(pbag)),
    trozo('pmod', Buffer.alloc(10)),
    trozo('pgen', Buffer.concat(pgen)),
    trozo('inst', Buffer.concat(inst)),
    trozo('ibag', Buffer.concat(ibag)),
    trozo('imod', Buffer.concat(imod)),
    trozo('igen', Buffer.concat(igen)),
    trozo('shdr', Buffer.concat(shdr)),
  ]);
  const ifil = Buffer.alloc(4);
  ifil.writeUInt16LE(opciones.ogg ? 3 : 2, 0);
  ifil.writeUInt16LE(opciones.ogg ? 0 : 4, 2);
  const info = lista('INFO', [trozo('ifil', ifil), trozo('INAM', Buffer.from('SoundFont de prueba\0', 'latin1'))]);
  const sdta = lista('sdta', [trozo('smpl', smpl)]);
  const cuerpo = Buffer.concat([Buffer.from('sfbk', 'latin1'), info, sdta, pdta]);
  const riff = Buffer.alloc(8);
  riff.write('RIFF', 0, 'latin1');
  riff.writeUInt32LE(cuerpo.length, 4);
  return new Uint8Array(Buffer.concat([riff, cuerpo]));
}

module.exports = { crear: crear };
