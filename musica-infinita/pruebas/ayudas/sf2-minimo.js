'use strict';

// Arma una SoundFont 2 mínima y válida, para probar el lector sin bajar una de verdad.
// Un preset de piano (banco 0, programa 0) con dos zonas (graves y agudos) y un preset de
// batería (banco 128) con una zona de bombo. Las muestras son senos de 440 Hz con bucle.

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

function bag(genNdx) {
  const b = Buffer.alloc(4);
  b.writeUInt16LE(genNdx, 0);
  return b;
}

function crear() {
  const fr = 22050;
  const largo = fr; // 1 s
  // muestra 0: seno de 440 Hz (nota 69); muestra 1: seno de 110 Hz (nota 45)
  const datos = [];
  const muestras = [];
  let pos = 0;
  for (const [f, tono] of [[440, 69], [110, 45]]) {
    const ini = pos;
    for (let i = 0; i < largo; i++) datos.push(Math.round(16000 * Math.sin((2 * Math.PI * f * i) / fr)));
    for (let i = 0; i < 46; i++) datos.push(0); // 46 ceros entre muestras, como pide la norma
    pos += largo + 46;
    muestras.push({ ini: ini, fin: ini + largo, loopIni: ini + fr / 2, loopFin: ini + fr / 2 + (fr / f) * 20, fr: fr, tono: tono });
  }
  const smpl = Buffer.alloc(datos.length * 2);
  datos.forEach(function (v, i) { smpl.writeInt16LE(v, i * 2); });

  // instrumentos: 0 = "Piano" (zona global con ataque, dos zonas locales), 1 = "Bombo"
  const igen = [];
  const ibag = [];
  // Piano: zona global
  ibag.push(bag(igen.length));
  igen.push(gen(34, -2400)); // attackVolEnv: 2^(-2) = 0,25 s
  igen.push(gen(48, 60)); // initialAttenuation 6 dB
  // zona grave: teclas 0-59, muestra 1 con bucle continuo
  ibag.push(bag(igen.length));
  igen.push(rango(43, 0, 59));
  igen.push(gen(54, 1));
  igen.push(gen(53, 1));
  // zona aguda: teclas 60-127, muestra 0, raíz forzada a 81, paneo a la derecha
  ibag.push(bag(igen.length));
  igen.push(rango(43, 60, 127));
  igen.push(gen(58, 81));
  igen.push(gen(17, 250));
  igen.push(gen(52, 10)); // fineTune +10 cents
  igen.push(gen(53, 0));
  // Bombo
  ibag.push(bag(igen.length));
  igen.push(rango(43, 35, 36));
  igen.push(gen(57, 3)); // exclusiveClass
  igen.push(gen(53, 1));
  // terminal
  ibag.push(bag(igen.length));
  igen.push(Buffer.alloc(4));

  const inst = [];
  const insReg = function (n, b) {
    const r = Buffer.alloc(22);
    nombre20(n).copy(r, 0);
    r.writeUInt16LE(b, 20);
    inst.push(r);
  };
  insReg('Piano', 0);
  insReg('Bombo', 3);
  insReg('EOI', 4);

  const pgen = [];
  const pbag = [];
  // preset Piano: una zona con +20 cB de atenuación (se suma a la del instrumento)
  pbag.push(bag(pgen.length));
  pgen.push(gen(48, 20));
  pgen.push(gen(41, 0));
  // preset Batería
  pbag.push(bag(pgen.length));
  pgen.push(gen(41, 1));
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
  preReg('EOP', 0, 0, 2);

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
    r.writeInt8(i === 0 ? -5 : 0, 41); // corrección de afinación de la muestra 0
    r.writeUInt16LE(1, 44); // mono
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
    trozo('imod', Buffer.alloc(10)),
    trozo('igen', Buffer.concat(igen)),
    trozo('shdr', Buffer.concat(shdr)),
  ]);
  const ifil = Buffer.alloc(4);
  ifil.writeUInt16LE(2, 0);
  ifil.writeUInt16LE(4, 2);
  const info = lista('INFO', [trozo('ifil', ifil), trozo('INAM', Buffer.from('SoundFont de prueba\0', 'latin1'))]);
  const sdta = lista('sdta', [trozo('smpl', smpl)]);
  const cuerpo = Buffer.concat([Buffer.from('sfbk', 'latin1'), info, sdta, pdta]);
  const riff = Buffer.alloc(8);
  riff.write('RIFF', 0, 'latin1');
  riff.writeUInt32LE(cuerpo.length, 4);
  return new Uint8Array(Buffer.concat([riff, cuerpo]));
}

module.exports = { crear: crear };
