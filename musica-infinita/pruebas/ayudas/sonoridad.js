'use strict';

// Sonoridad integrada (LUFS) según ITU-R BS.1770-4, para las pruebas de la mezcla: filtro K
// (un estante agudo y un pasa-altos), bloques de 400 ms con 75 % de solape y las dos
// puertas (absoluta de -70 LUFS y relativa de -10 LU). Los coeficientes son los de la norma
// para 48 kHz. También mide el pico de muestra y la corriente continua.
//
// Se verifica contra el filtro ebur128 de ffmpeg (pruebas/mezcla.test.js).

const ESTANTE = { b: [1.53512485958697, -2.69169618940638, 1.19839281085285], a: [1, -1.69065929318241, 0.73248077421585] };
const PASA_ALTOS = { b: [1, -2, 1], a: [1, -1.99004745483398, 0.99007225036621] };

function biquad(x, f) {
  const y = new Float64Array(x.length);
  let x1 = 0;
  let x2 = 0;
  let y1 = 0;
  let y2 = 0;
  for (let i = 0; i < x.length; i++) {
    const v = f.b[0] * x[i] + f.b[1] * x1 + f.b[2] * x2 - f.a[1] * y1 - f.a[2] * y2;
    x2 = x1;
    x1 = x[i];
    y2 = y1;
    y1 = v;
    y[i] = v;
  }
  return y;
}

/** Sonoridad integrada de canales de Float32 (-1..1) a 48 kHz. Devuelve LUFS, o -Infinity si todo es silencio. */
function lufs(canales, sr) {
  if (sr !== 48000) throw new Error('Solo 48 kHz (los coeficientes del filtro son los de la norma para esa frecuencia)');
  const filtrados = canales.map(function (c) { return biquad(biquad(c, ESTANTE), PASA_ALTOS); });
  const bloque = Math.round(0.4 * sr);
  const paso = Math.round(0.1 * sr);
  const energias = [];
  for (let i = 0; i + bloque <= filtrados[0].length; i += paso) {
    let suma = 0;
    for (const f of filtrados) {
      let s = 0;
      for (let k = i; k < i + bloque; k++) s += f[k] * f[k];
      suma += s / bloque;
    }
    energias.push(suma);
  }
  const aLufs = function (e) { return -0.691 + 10 * Math.log10(e); };
  const sobreAbsoluta = energias.filter(function (e) { return e > 0 && aLufs(e) > -70; });
  if (!sobreAbsoluta.length) return -Infinity;
  const media = function (l) { return l.reduce(function (a, b) { return a + b; }, 0) / l.length; };
  const umbral = aLufs(media(sobreAbsoluta)) - 10;
  const sobreRelativa = sobreAbsoluta.filter(function (e) { return aLufs(e) > umbral; });
  return aLufs(media(sobreRelativa));
}

/** Pico de muestra en dBFS (el mayor valor absoluto de todos los canales). */
function picoDb(canales) {
  let m = 0;
  for (const c of canales) for (let i = 0; i < c.length; i++) if (Math.abs(c[i]) > m) m = Math.abs(c[i]);
  return m > 0 ? 20 * Math.log10(m) : -Infinity;
}

/** Corriente continua: el promedio de cada canal (el mayor, en valor absoluto). */
function continua(canales) {
  let m = 0;
  for (const c of canales) {
    let s = 0;
    for (let i = 0; i < c.length; i++) s += c[i];
    m = Math.max(m, Math.abs(s / c.length));
  }
  return m;
}

/** Lee un WAV PCM de 16 bits (el de F.wav o el de ffmpeg) a canales Float32. */
function leerWav16(bytes) {
  const dv = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
  let pos = 12;
  let canales = 2;
  let sr = 48000;
  while (pos + 8 <= bytes.length) {
    const id = String.fromCharCode(bytes[pos], bytes[pos + 1], bytes[pos + 2], bytes[pos + 3]);
    const largo = dv.getUint32(pos + 4, true);
    if (id === 'fmt ') {
      canales = dv.getUint16(pos + 10, true);
      sr = dv.getUint32(pos + 12, true);
    } else if (id === 'data') {
      const n = Math.floor(largo / (2 * canales));
      const out = [];
      for (let c = 0; c < canales; c++) out.push(new Float32Array(n));
      for (let i = 0; i < n; i++) for (let c = 0; c < canales; c++) out[c][i] = dv.getInt16(pos + 8 + (i * canales + c) * 2, true) / 32768;
      return { canales: out, sr: sr };
    }
    pos += 8 + largo + (largo & 1);
  }
  throw new Error('WAV sin datos');
}

module.exports = { lufs: lufs, picoDb: picoDb, continua: continua, leerWav16: leerWav16 };
