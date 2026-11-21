'use strict';

// Decodificador FLAC mínimo, solo para las pruebas: entiende lo que escribe js/flac.js
// (subtramas constante, literal y de predictor fijo, Rice y Rice2, y las cuatro
// asignaciones estéreo) y comprueba los CRC de cada trama y el MD5 del flujo.

const F = require('../../js/flac.js');

function LectorBits(b, pos) {
  this.b = b;
  this.pos = pos * 8;
}
LectorBits.prototype.leer = function (n) {
  let v = 0;
  for (let i = 0; i < n; i++) {
    const byte = this.b[this.pos >> 3];
    v = v * 2 + ((byte >> (7 - (this.pos & 7))) & 1);
    this.pos++;
  }
  return v;
};
LectorBits.prototype.conSigno = function (n) {
  const v = this.leer(n);
  return v >= Math.pow(2, n - 1) ? v - Math.pow(2, n) : v;
};
LectorBits.prototype.unario = function () {
  let q = 0;
  while (this.leer(1) === 0) q++;
  return q;
};
LectorBits.prototype.alinear = function () {
  this.pos = Math.ceil(this.pos / 8) * 8;
};

function decodificar(bytes) {
  const b = bytes;
  if (String.fromCharCode(b[0], b[1], b[2], b[3]) !== 'fLaC') throw new Error('sin marca fLaC');
  let p = 4;
  let info = null;
  const etiquetas = {};
  for (;;) {
    const ultimo = b[p] & 0x80;
    const tipo = b[p] & 0x7f;
    const largo = (b[p + 1] << 16) | (b[p + 2] << 8) | b[p + 3];
    const r = new LectorBits(b, p + 4);
    if (tipo === 0) {
      info = {
        minBloque: r.leer(16),
        maxBloque: r.leer(16),
        minTrama: r.leer(24),
        maxTrama: r.leer(24),
        frecuencia: r.leer(20),
        canales: r.leer(3) + 1,
        bits: r.leer(5) + 1,
        total: r.leer(36),
        md5: Array.from(b.subarray(p + 4 + 18, p + 4 + 34)),
      };
    } else if (tipo === 4) {
      const dv = new DataView(b.buffer, b.byteOffset + p + 4, largo);
      let q = 0;
      const vl = dv.getUint32(q, true);
      q += 4 + vl;
      const n = dv.getUint32(q, true);
      q += 4;
      for (let i = 0; i < n; i++) {
        const l = dv.getUint32(q, true);
        q += 4;
        const s = Buffer.from(b.subarray(p + 4 + q, p + 4 + q + l)).toString('utf8');
        q += l;
        const k = s.indexOf('=');
        etiquetas[s.slice(0, k)] = s.slice(k + 1);
      }
    }
    p += 4 + largo;
    if (ultimo) break;
  }
  const canales = [];
  for (let c = 0; c < info.canales; c++) canales.push([]);
  let numTrama = 0;
  while (p < b.length) {
    const inicioTrama = p;
    const r = new LectorBits(b, p);
    if (r.leer(14) !== 0x3ffe) throw new Error('sincronía perdida en la trama ' + numTrama);
    r.leer(2);
    const codBloque = r.leer(4);
    r.leer(4); // frecuencia
    const asignacion = r.leer(4);
    const codBits = r.leer(3);
    r.leer(1);
    // número de trama en UTF-8
    const x = r.leer(8);
    let n = 0;
    while (x & (0x80 >> n)) n++;
    let num = n ? x & (0xff >> (n + 1)) : x;
    for (let i = 1; i < n; i++) num = num * 64 + (r.leer(8) & 0x3f);
    if (num !== numTrama) throw new Error('número de trama ' + num + ' en vez de ' + numTrama);
    let bloque;
    if (codBloque === 12) bloque = 4096;
    else if (codBloque === 6) bloque = r.leer(8) + 1;
    else if (codBloque === 7) bloque = r.leer(16) + 1;
    else throw new Error('código de bloque no esperado ' + codBloque);
    const finCab = r.pos >> 3;
    const crc8 = r.leer(8);
    if (crc8 !== F.crc8(b, inicioTrama, finCab)) throw new Error('CRC-8 mal en la trama ' + numTrama);
    const bps = { 4: 16, 6: 24, 1: 8 }[codBits] || info.bits;
    const nc = asignacion < 8 ? asignacion + 1 : 2;
    const subs = [];
    for (let c = 0; c < nc; c++) {
      const extra = (asignacion === 8 && c === 1) || (asignacion === 9 && c === 0) || (asignacion === 10 && c === 1) ? 1 : 0;
      subs.push(subtrama(r, bloque, bps + extra));
    }
    r.alinear();
    const fin = r.pos >> 3;
    const crc16 = r.leer(16);
    if (crc16 !== F.crc16(b, inicioTrama, fin)) throw new Error('CRC-16 mal en la trama ' + numTrama);
    let L;
    let R;
    if (asignacion === 8) {
      L = subs[0];
      R = subs[0].map(function (v, i) { return v - subs[1][i]; });
    } else if (asignacion === 9) {
      R = subs[1];
      L = subs[1].map(function (v, i) { return v + subs[0][i]; });
    } else if (asignacion === 10) {
      L = [];
      R = [];
      for (let i = 0; i < bloque; i++) {
        const s = subs[1][i];
        const m = subs[0][i] * 2 + (s & 1);
        L.push((m + s) >> 1);
        R.push((m - s) >> 1);
      }
    }
    const salida = asignacion >= 8 ? [L, R] : subs;
    for (let c = 0; c < salida.length; c++) Array.prototype.push.apply(canales[c], salida[c]);
    p = fin + 2;
    numTrama++;
  }
  // MD5
  const bm = info.bits / 8;
  const crudo = new Uint8Array(canales[0].length * info.canales * bm);
  let k = 0;
  for (let i = 0; i < canales[0].length; i++) {
    for (let c = 0; c < info.canales; c++) {
      const v = canales[c][i];
      for (let j = 0; j < bm; j++) crudo[k++] = (v >> (8 * j)) & 0xff;
    }
  }
  const md5 = Array.from(F.md5(crudo));
  return { info: info, etiquetas: etiquetas, canales: canales, tramas: numTrama, md5Coincide: md5.join(',') === info.md5.join(',') };
}

function subtrama(r, n, bps) {
  r.leer(1);
  const tipo = r.leer(6);
  if (r.leer(1)) throw new Error('bits desperdiciados no soportados');
  const out = new Array(n);
  if (tipo === 0) {
    const v = r.conSigno(bps);
    out.fill(v);
    return out;
  }
  if (tipo === 1) {
    for (let i = 0; i < n; i++) out[i] = r.conSigno(bps);
    return out;
  }
  if ((tipo & 0x38) !== 8) throw new Error('subtrama no esperada ' + tipo);
  const orden = tipo & 7;
  for (let i = 0; i < orden; i++) out[i] = r.conSigno(bps);
  const metodo = r.leer(2);
  const ancho = metodo === 1 ? 5 : 4;
  const po = r.leer(4);
  const partes = 1 << po;
  const res = [];
  for (let p = 0; p < partes; p++) {
    const k = r.leer(ancho);
    const cuantos = (n >> po) - (p === 0 ? orden : 0);
    for (let i = 0; i < cuantos; i++) {
      const q = r.unario();
      const u = q * Math.pow(2, k) + (k ? r.leer(k) : 0);
      res.push(u & 1 ? -(u + 1) / 2 : u / 2);
    }
  }
  for (let i = orden; i < n; i++) {
    const e = res[i - orden];
    let pred = 0;
    if (orden === 1) pred = out[i - 1];
    else if (orden === 2) pred = 2 * out[i - 1] - out[i - 2];
    else if (orden === 3) pred = 3 * out[i - 1] - 3 * out[i - 2] + out[i - 3];
    else if (orden === 4) pred = 4 * out[i - 1] - 6 * out[i - 2] + 4 * out[i - 3] - out[i - 4];
    out[i] = pred + e;
  }
  return out;
}

module.exports = { decodificar: decodificar };
